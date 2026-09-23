<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/auth.php';

use app\repositories\FinanceRepository;

if (!defined('DATA_DIR')) define('DATA_DIR', Yii::$app->params['privateStorage']);
if (!defined('USER_DATA_DIR')) define('USER_DATA_DIR', DATA_DIR.'/user_finance');
if (!defined('LEGACY_DATA_FILE')) define('LEGACY_DATA_FILE', DATA_DIR.'/finance.json');

function defaultDailyBudgetSettings() {
    return ['enabled'=>false,'warning_percent'=>80,'days'=>[
        '1'=>['enabled'=>false,'limit'=>0],'2'=>['enabled'=>false,'limit'=>0],'3'=>['enabled'=>false,'limit'=>0],
        '4'=>['enabled'=>false,'limit'=>0],'5'=>['enabled'=>false,'limit'=>0],'6'=>['enabled'=>false,'limit'=>0],'7'=>['enabled'=>false,'limit'=>0]
    ]];
}
function defaultData() {
    return ['settings'=>['initial_balance'=>0,'daily_budget'=>defaultDailyBudgetSettings()],'transactions'=>[],'chats'=>[],'meta'=>['next_transaction_id'=>1,'next_chat_id'=>1,'revision'=>0]];
}
function currentUserDataFile(){ $u=authCurrentUser(); if(!$u) throw new RuntimeException('User belum login.'); return 'mysql://user/'.(int)$u['id']; }
function userDataFileById($userId){ return 'mysql://user/'.(int)$userId; }
function ensureUserDataById($userId){ FinanceRepository::ensureUser((int)$userId); return userDataFileById($userId); }
function ensureData(){ $u=authCurrentUser(); if(!$u) throw new RuntimeException('User belum login.'); FinanceRepository::ensureUser((int)$u['id']); }
function readData(){ $u=authCurrentUser(); if(!$u) throw new RuntimeException('User belum login.'); return array_replace_recursive(defaultData(), FinanceRepository::read((int)$u['id'])); }
function mutateData($fn){ $u=authCurrentUser(); if(!$u) throw new RuntimeException('User belum login.'); return FinanceRepository::mutate((int)$u['id'],$fn); }
function mutateUserDataById($userId,$fn){ return FinanceRepository::mutate((int)$userId,$fn); }
function addChatForUser($userId,$role,$message,$attachment=null){
    $r=FinanceRepository::mutate((int)$userId,function(&$d)use($role,$message,$attachment){
        if(!isset($d['meta']['next_chat_id'])){$max=0;foreach(($d['chats']??[]) as $c)$max=max($max,(int)($c['id']??0));$d['meta']['next_chat_id']=$max+1;}
        $id=(int)$d['meta']['next_chat_id']++;$x=['id'=>$id,'role'=>$role,'message'=>$message,'created_at'=>date('Y-m-d H:i:s')];
        if(is_array($attachment)&&!empty($attachment['file']))$x['attachment']=$attachment;
        if(!isset($d['chats'])||!is_array($d['chats']))$d['chats']=[];$d['chats'][]=$x;return $x;
    }); return $r['result'];
}
function db(){ensureData();return true;}
function setting($key,$default=null){$d=readData();return array_key_exists($key,$d['settings'])?$d['settings'][$key]:$default;}
function setSetting($key,$value){mutateData(function(&$d)use($key,$value){$d['settings'][$key]=$value;});}
function normalizeDailyBudgetSettings($raw) {
    $defaults = defaultDailyBudgetSettings();
    if (!is_array($raw)) return $defaults;

    $out = $defaults;
    $out['enabled'] = !empty($raw['enabled']);
    $warning = isset($raw['warning_percent']) ? (int)$raw['warning_percent'] : 80;
    $out['warning_percent'] = max(50, min(99, $warning));

    $rawDays = isset($raw['days']) && is_array($raw['days']) ? $raw['days'] : [];
    for ($i=1; $i<=7; $i++) {
        $key = (string)$i;
        $day = isset($rawDays[$key]) && is_array($rawDays[$key]) ? $rawDays[$key] : (isset($rawDays[$i]) && is_array($rawDays[$i]) ? $rawDays[$i] : []);
        $out['days'][$key] = [
            'enabled'=>!empty($day['enabled']),
            'limit'=>max(0, (int)($day['limit'] ?? 0))
        ];
    }
    return $out;
}

function dailyBudgetSettings() {
    $d = readData();
    return normalizeDailyBudgetSettings($d['settings']['daily_budget'] ?? []);
}

function setDailyBudgetSettings($raw) {
    $normalized = normalizeDailyBudgetSettings($raw);
    setSetting('daily_budget', $normalized);
    return $normalized;
}

function dailyExpenseTotal($date) {
    $total = 0;
    foreach (allTransactions() as $t) {
        if (($t['type'] ?? '') !== 'expense') continue;
        if ((string)($t['transaction_date'] ?? '') !== (string)$date) continue;
        $total += (int)($t['amount'] ?? 0);
    }
    return $total;
}

function dailyBudgetStatus($date=null) {
    $date = $date ?: date('Y-m-d');
    try {
        $dt = new DateTimeImmutable($date);
    } catch (Throwable $e) {
        $dt = new DateTimeImmutable('today');
        $date = $dt->format('Y-m-d');
    }

    $cfg = dailyBudgetSettings();
    $dayNo = (int)$dt->format('N');
    $dayNames = [1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'];
    $dayCfg = $cfg['days'][(string)$dayNo] ?? ['enabled'=>false,'limit'=>0];
    $spent = dailyExpenseTotal($date);
    $limit = max(0, (int)($dayCfg['limit'] ?? 0));
    $active = !empty($cfg['enabled']) && !empty($dayCfg['enabled']) && $limit > 0;

    $base = [
        'date'=>$date,
        'day_no'=>$dayNo,
        'day_name'=>$dayNames[$dayNo],
        'enabled'=>(bool)$cfg['enabled'],
        'active'=>$active,
        'spent'=>$spent,
        'limit'=>$limit,
        'remaining'=>$active ? max(0, $limit-$spent) : 0,
        'over'=>$active ? max(0, $spent-$limit) : 0,
        'warning_percent'=>(int)$cfg['warning_percent'],
        'percent'=>$active && $limit > 0 ? (int)round(($spent/$limit)*100) : 0,
        'progress_percent'=>$active && $limit > 0 ? min(100, (int)round(($spent/$limit)*100)) : 0,
        'status'=>'inactive',
        'label'=>'Tidak aktif',
        'message'=>'Peringatan pengeluaran tidak aktif untuk hari ini.'
    ];

    if (!$active) return $base;

    $percent = $base['percent'];
    if ($spent > $limit) {
        $base['status'] = 'exceeded';
        $base['label'] = 'Batas terlewati';
        $base['message'] = 'Pengeluaran melewati batas harian sebesar Rp'.number_format($spent-$limit,0,',','.').'.';
    } elseif ($spent === $limit) {
        $base['status'] = 'reached';
        $base['label'] = 'Batas tercapai';
        $base['message'] = 'Pengeluaran hari ini sudah mencapai batas harian.';
    } elseif ($percent >= (int)$cfg['warning_percent']) {
        $base['status'] = 'warning';
        $base['label'] = 'Mendekati batas';
        $base['message'] = 'Pengeluaran sudah mencapai '.$percent.'% dari batas harian.';
    } else {
        $base['status'] = 'safe';
        $base['label'] = 'Masih aman';
        $base['message'] = 'Pengeluaran masih di bawah batas peringatan.';
    }
    return $base;
}


function auditEnsureData(&$d) {
    if (!isset($d['audit_log']) || !is_array($d['audit_log'])) $d['audit_log'] = [];
    if (!isset($d['meta']) || !is_array($d['meta'])) $d['meta'] = [];
    if (!isset($d['meta']['next_audit_id'])) {
        $max=0; foreach($d['audit_log'] as $row) $max=max($max,(int)($row['id']??0));
        $d['meta']['next_audit_id']=$max+1;
    }
}

function auditAdd(&$d, $action, $entityType, $entityId, $before=null, $after=null, $undoable=false, $label='') {
    auditEnsureData($d);
    $row=[
        'id'=>(int)$d['meta']['next_audit_id']++,
        'action'=>(string)$action,
        'entity_type'=>(string)$entityType,
        'entity_id'=>(int)$entityId,
        'label'=>substr(trim((string)$label),0,160),
        'before'=>$before,
        'after'=>$after,
        'undoable'=>(bool)$undoable,
        'undone_at'=>null,
        'created_at'=>date('Y-m-d H:i:s')
    ];
    $d['audit_log'][]=$row;
    if(count($d['audit_log'])>300) $d['audit_log']=array_slice($d['audit_log'],-250);
    return $row;
}

function transactionVersion(array $t): string {
    return (string)($t['updated_at'] ?? $t['created_at'] ?? '');
}

function transactionSpendingKind(array $t): string {
    $kind=strtolower(trim((string)($t['spending_kind']??'')));
    if(in_array($kind,['daily','once','recurring'],true)) return $kind;
    if(($t['type']??'')!=='expense') return 'once';
    $source=strtolower((string)($t['source']??''));
    if($source==='recurring') return 'recurring';
    if($source==='bill') return 'once';
    $text=strtolower((string)($t['category']??'').' '.(string)($t['note']??''));
    if(preg_match('/\b(?:cicilan|angsuran|tagihan|sekali bayar|sekali saja|satu kali|non[ -]?rutin)\b/u',$text)) return 'once';
    return 'daily';
}

function walletBalancesFromData($d,$excludeTransactionId=0) {
    $balances=[];
    foreach((array)($d['wallets']??[]) as $w) $balances[(int)($w['id']??0)]=(int)($w['initial_balance']??0);
    foreach((array)($d['transactions']??[]) as $t){
        if($excludeTransactionId>0 && (int)($t['id']??0)===(int)$excludeTransactionId)continue;
        $type=(string)($t['type']??'');$amount=(int)($t['amount']??0);
        if($type==='income'){$wid=(int)($t['wallet_id']??1);$balances[$wid]=($balances[$wid]??0)+$amount;}
        elseif($type==='expense'){$wid=(int)($t['wallet_id']??1);$balances[$wid]=($balances[$wid]??0)-$amount;}
        elseif($type==='transfer'){
            $from=(int)($t['from_wallet_id']??0);$to=(int)($t['to_wallet_id']??0);
            $balances[$from]=($balances[$from]??0)-$amount;$balances[$to]=($balances[$to]??0)+$amount;
        }
    }
    return $balances;
}
function walletProtectionFromData($d,$walletId) {
    foreach((array)($d['wallets']??[]) as $w)if((int)($w['id']??0)===(int)$walletId){
        $reserved=max(0,(int)($w['reserved_balance']??0));
        $minimum=max(0,(int)($w['minimum_balance']??0));
        return ['name'=>(string)($w['name']??'Dompet'),'reserved'=>$reserved,'minimum'=>$minimum,'protected'=>$reserved+$minimum];
    }
    throw new InvalidArgumentException('Dompet transaksi tidak ditemukan.');
}
function assertWalletSpendAllowedData($d,$walletId,$amount,$excludeTransactionId=0) {
    $walletId=(int)$walletId;$amount=max(0,(int)$amount);
    if($walletId<=0)throw new InvalidArgumentException('Dompet transaksi tidak valid.');
    if($amount<=0)return true;
    $protection=walletProtectionFromData($d,$walletId);
    $balances=walletBalancesFromData($d,(int)$excludeTransactionId);
    $gross=(int)($balances[$walletId]??0);
    $available=max(0,$gross-(int)$protection['protected']);
    if($amount>$available){
        $fmt=function($n){return 'Rp'.number_format((int)$n,0,',','.');};
        $parts=[];
        if((int)$protection['reserved']>0)$parts[]='dana disisihkan '.$fmt($protection['reserved']);
        if((int)$protection['minimum']>0)$parts[]='saldo minimum '.$fmt($protection['minimum']);
        $protectedText=$parts?' '.ucfirst(implode(' dan ',$parts)).' tidak dapat digunakan.':'';
        throw new InvalidArgumentException('Saldo tersedia '.$protection['name'].' hanya '.$fmt($available).'.'.$protectedText);
    }
    return true;
}

function summary() {
    $d=readData(); $income=0; $expense=0;
    foreach($d['transactions'] as $t){
        if(($t['type']??'')==='income')$income+=(int)$t['amount'];
        elseif(($t['type']??'')==='expense')$expense+=(int)$t['amount'];
    }
    $initial=0;$reserved=0;$minimum=0;
    if (isset($d['wallets']) && is_array($d['wallets']) && count($d['wallets'])) {
        foreach($d['wallets'] as $w) if(empty($w['archived'])) {
            $initial+=(int)($w['initial_balance']??0);
            $reserved+=max(0,(int)($w['reserved_balance']??0));
            $minimum+=max(0,(int)($w['minimum_balance']??0));
        }
    } else $initial=(int)($d['settings']['initial_balance']??0);
    $gross=$initial+$income-$expense;
    $protected=$reserved+$minimum;
    $available=max(0,$gross-$protected);
    return ['initial'=>$initial,'income'=>$income,'expense'=>$expense,'gross_balance'=>$gross,'reserved'=>$reserved,'minimum_balance'=>$minimum,'protected_balance'=>$protected,'available_balance'=>$available,'balance'=>$available];
}
function addChat($role,$message,$attachment=null) {
    $r=mutateData(function(&$d)use($role,$message,$attachment){
        $id=(int)$d['meta']['next_chat_id']++;
        $x=['id'=>$id,'role'=>$role,'message'=>$message,'created_at'=>date('Y-m-d H:i:s')];
        if (is_array($attachment) && !empty($attachment['file'])) $x['attachment']=$attachment;
        $d['chats'][]=$x;
        return $x;
    });
    return $r['result'];
}
function addTransaction($t) {
    $r=mutateData(function(&$d)use($t){
        $type=(string)($t['type']??'expense');
        $amount=max(0,(int)($t['amount']??0));
        if($type==='expense')assertWalletSpendAllowedData($d,(int)($t['wallet_id']??1),$amount);
        elseif($type==='transfer')assertWalletSpendAllowedData($d,(int)($t['from_wallet_id']??0),$amount);
        $id=(int)$d['meta']['next_transaction_id']++;
        $x=[
            'id'=>$id,
            'type'=>$type,
            'category'=>$t['category']??'Lainnya',
            'amount'=>$amount,
            'note'=>$t['note']??'',
            'transaction_date'=>$t['transaction_date']??date('Y-m-d'),
            'created_at'=>date('Y-m-d H:i:s')
        ];
        if ($type === 'transfer') {
            $x['from_wallet_id']=(int)($t['from_wallet_id']??0);
            $x['to_wallet_id']=(int)($t['to_wallet_id']??0);
        } else {
            $x['wallet_id']=(int)($t['wallet_id']??1);
            $x['spending_kind']=transactionSpendingKind(array_merge($t,['type'=>$type]));
        }
        foreach(['source','bill_id'] as $key) if(isset($t[$key]) && $t[$key]!=='' && $t[$key]!==null) $x[$key]=$key==='bill_id'?(int)$t[$key]:$t[$key];
        if (isset($t['attachment']) && is_array($t['attachment']) && !empty($t['attachment']['file'])) $x['attachment']=$t['attachment'];
        if (isset($t['ocr']) && is_array($t['ocr'])) $x['ocr']=$t['ocr'];
        $d['transactions'][]=$x;
        auditAdd($d,'create','transaction',$id,null,$x,false,'Transaksi dibuat');
        return $x;
    });
    return $r['result'];
}


function updateTransaction($id, $patch) {
    $id=(int)$id;
    $r=mutateData(function(&$d)use($id,$patch){
        foreach($d['transactions'] as &$t){
            if((int)($t['id']??0)!==$id) continue;
            if(isset($patch['expected_version']) && (string)$patch['expected_version']!=='' && transactionVersion($t)!==(string)$patch['expected_version']) {
                throw new RuntimeException('Transaksi sudah berubah di perangkat lain. Muat ulang sebelum menyimpan perubahan.',409);
            }
            $before=$t;
            if(isset($patch['type']) && in_array($patch['type'],['income','expense','transfer'],true)) $t['type']=$patch['type'];
            if(isset($patch['category'])) $t['category']=substr(trim((string)$patch['category']),0,80);
            if(isset($patch['amount'])) $t['amount']=max(0,(int)$patch['amount']);
            if(isset($patch['note'])) $t['note']=substr(trim((string)$patch['note']),0,255);
            if(isset($patch['transaction_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$patch['transaction_date'])) $t['transaction_date']=$patch['transaction_date'];
            if(($t['type']??'')==='transfer'){
                if(isset($patch['from_wallet_id']))$t['from_wallet_id']=(int)$patch['from_wallet_id'];
                if(isset($patch['to_wallet_id']))$t['to_wallet_id']=(int)$patch['to_wallet_id'];
                unset($t['wallet_id'],$t['spending_kind'],$t['bill_id']);
            } else {
                if(isset($patch['wallet_id']))$t['wallet_id']=(int)$patch['wallet_id'];
                if(isset($patch['spending_kind']) && in_array($patch['spending_kind'],['daily','once','recurring'],true)) $t['spending_kind']=$patch['spending_kind'];
                elseif(!isset($t['spending_kind'])) $t['spending_kind']=transactionSpendingKind($t);
                if(array_key_exists('bill_id',$patch)) { if((int)$patch['bill_id']>0)$t['bill_id']=(int)$patch['bill_id']; else unset($t['bill_id']); }
                unset($t['from_wallet_id'],$t['to_wallet_id']);
            }
            if(($t['type']??'')==='expense')assertWalletSpendAllowedData($d,(int)($t['wallet_id']??1),(int)($t['amount']??0),$id);
            elseif(($t['type']??'')==='transfer')assertWalletSpendAllowedData($d,(int)($t['from_wallet_id']??0),(int)($t['amount']??0),$id);
            $t['updated_at']=date('Y-m-d H:i:s');
            auditAdd($d,'update','transaction',$id,$before,$t,true,'Transaksi diperbarui');
            return $t;
        }
        unset($t);
        throw new InvalidArgumentException('Transaksi tidak ditemukan.');
    });
    return $r['result'];
}
function transactionById($id){foreach(allTransactions() as $t)if((int)($t['id']??0)===(int)$id)return $t;return null;}

function deleteTransaction($id,$expectedVersion='') {
    $id=(int)$id;
    $r=mutateData(function(&$d)use($id,$expectedVersion){
        $deleted=null;$kept=[];
        foreach(($d['transactions']??[]) as $x){
            if((int)($x['id']??0)===$id && $deleted===null){
                if($expectedVersion!=='' && transactionVersion($x)!==$expectedVersion) throw new RuntimeException('Transaksi sudah berubah di perangkat lain. Muat ulang sebelum menghapus.',409);
                $deleted=$x;continue;
            }
            $kept[]=$x;
        }
        if(!$deleted) return null;
        $d['transactions']=array_values($kept);
        if(isset($d['bills']) && is_array($d['bills'])) foreach($d['bills'] as &$bill){
            foreach((array)($bill['payments']??[]) as $key=>$payment){
                if((int)($payment['transaction_id']??0)===$id) unset($bill['payments'][$key]);
            }
        } unset($bill);
        auditAdd($d,'delete','transaction',$id,$deleted,null,true,'Transaksi dihapus');
        return $deleted;
    });
    return $r['result'];
}
function allTransactions(){ return readData()['transactions']; }
function recentTransactions($limit=50) {
    $a=allTransactions(); usort($a,function($x,$y){return strcmp(($y['transaction_date']??'').sprintf('%010d',(int)$y['id']),($x['transaction_date']??'').sprintf('%010d',(int)$x['id']));}); return array_slice($a,0,$limit);
}
function recentChats($limit=40){ $a=readData()['chats']; return array_slice($a,max(0,count($a)-$limit)); }

/** Hapus satu pesan chat tanpa menyentuh transaksi keuangan. */
function deleteChatMessage($id) {
    $id = (int)$id;
    $r = mutateData(function(&$d) use ($id) {
        $deleted = null;
        $kept = [];
        foreach (($d['chats'] ?? []) as $chat) {
            if ((int)($chat['id'] ?? 0) === $id && $deleted === null) {
                $deleted = $chat;
                continue;
            }
            $kept[] = $chat;
        }
        $d['chats'] = array_values($kept);
        return $deleted;
    });
    return $r['result'];
}

/** Hapus seluruh riwayat chat user aktif tanpa menyentuh transaksi. */
function clearChatHistory() {
    $r = mutateData(function(&$d) {
        $deleted = array_values($d['chats'] ?? []);
        $d['chats'] = [];
        return $deleted;
    });
    return $r['result'];
}
