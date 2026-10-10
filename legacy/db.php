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
    return FinanceRepository::appendChat((int)$userId,(string)$role,(string)$message,$attachment);
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

function walletRecordFromData($d,$walletId) {
    foreach((array)($d['wallets']??[]) as $w) if((int)($w['id']??0)===(int)$walletId) return $w;
    return null;
}
function walletIsCreditCardRecord($w): bool {
    return is_array($w) && strtolower((string)($w['type']??''))==='credit_card';
}
function walletCreditCardDebtFromData($d,$walletId,$excludeTransactionId=0) {
    $walletId=(int)$walletId;
    $card=walletRecordFromData($d,$walletId);
    if(!walletIsCreditCardRecord($card)) return 0;

    $debt=max(0,(int)($card['opening_debt']??0));
    $transactionsById=[];
    foreach((array)($d['transactions']??[]) as $t){
        $txId=(int)($t['id']??0);
        if($txId>0)$transactionsById[$txId]=$t;
        if($excludeTransactionId>0 && $txId===$excludeTransactionId)continue;
        $type=(string)($t['type']??'');
        $amount=max(0,(int)($t['amount']??0));
        if($amount<=0)continue;
        if($type==='expense' && (int)($t['wallet_id']??0)===$walletId){
            $debt+=$amount;
        }elseif($type==='income' && (int)($t['wallet_id']??0)===$walletId){
            $debt-=$amount;
        }elseif($type==='transfer'){
            $from=(int)($t['from_wallet_id']??0);
            $to=(int)($t['to_wallet_id']??0);
            if($from===$walletId)$debt+=$amount;
            elseif($to===$walletId)$debt-=$amount;
        }
    }

    // Fallback untuk data lama/legacy: bila tagihan kartu tercatat lunas tetapi
    // pembayaran belum memiliki transaksi transfer yang mengkredit kartu,
    // pembayaran tetap harus mengembalikan ruang limit kartu.
    foreach((array)($d['bills']??[]) as $bill){
        if(empty($bill['auto_generated']) || ($bill['bill_type']??'')!=='credit_card')continue;
        if((int)($bill['credit_card_wallet_id']??0)!==$walletId)continue;
        foreach((array)($bill['payments']??[]) as $payment){
            $paid=max(0,(int)($payment['amount']??0));
            if($paid<=0)continue;
            $txId=(int)($payment['transaction_id']??0);
            $represented=false;
            if($txId>0 && isset($transactionsById[$txId])){
                $tx=$transactionsById[$txId];
                $represented=(($tx['type']??'')==='transfer'
                    && (int)($tx['to_wallet_id']??0)===$walletId
                    && (int)($tx['amount']??0)>0);
            }
            if(!$represented)$debt-=$paid;
        }
    }
    return max(0,$debt);
}
function walletBalancesFromData($d,$excludeTransactionId=0) {
    $balances=[];
    foreach((array)($d['wallets']??[]) as $w) {
        $id=(int)($w['id']??0);
        $balances[$id]=walletIsCreditCardRecord($w)
            ? -max(0,(int)($w['opening_debt']??0))
            : (int)($w['initial_balance']??0);
    }
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
    foreach((array)($d['wallets']??[]) as $w){
        $id=(int)($w['id']??0);
        if(walletIsCreditCardRecord($w))$balances[$id]=-walletCreditCardDebtFromData($d,$id,$excludeTransactionId);
    }
    return $balances;
}
function walletProtectionFromData($d,$walletId) {
    foreach((array)($d['wallets']??[]) as $w)if((int)($w['id']??0)===(int)$walletId){
        $reserved=max(0,(int)($w['reserved_balance']??0));
        $minimum=max(0,(int)($w['minimum_balance']??0));
        return [
            'name'=>(string)($w['name']??'Dompet'),
            'type'=>(string)($w['type']??'cash'),
            'reserved'=>$reserved,
            'minimum'=>$minimum,
            'protected'=>$reserved+$minimum,
            'credit_limit'=>max(0,(int)($w['credit_limit']??0)),
            'opening_debt'=>max(0,(int)($w['opening_debt']??0)),
        ];
    }
    throw new InvalidArgumentException('Dompet transaksi tidak ditemukan.');
}
function walletCreditCardStatusFromData($d,$walletId,$excludeTransactionId=0) {
    $info=walletProtectionFromData($d,$walletId);
    if(strtolower((string)$info['type'])!=='credit_card') return null;
    $balances=walletBalancesFromData($d,(int)$excludeTransactionId);
    $raw=(int)($balances[(int)$walletId]??0);
    $debt=max(0,-$raw);
    $limit=max(0,(int)$info['credit_limit']);
    return ['name'=>$info['name'],'limit'=>$limit,'debt'=>$debt,'available'=>max(0,$limit-$debt)];
}
function assertWalletCreditPaymentAllowedData($d,$walletId,$amount,$excludeTransactionId=0) {
    $status=walletCreditCardStatusFromData($d,$walletId,$excludeTransactionId);
    if(!$status) return true;
    $amount=max(0,(int)$amount);
    if($amount>$status['debt']){
        $fmt=function($n){return 'Rp'.number_format((int)$n,0,',','.');};
        throw new InvalidArgumentException('Tagihan kartu '.$status['name'].' saat ini hanya '.$fmt($status['debt']).'. Pembayaran tidak boleh melebihi tagihan.');
    }
    return true;
}
function assertWalletSpendAllowedData($d,$walletId,$amount,$excludeTransactionId=0) {
    $walletId=(int)$walletId;$amount=max(0,(int)$amount);
    if($walletId<=0)throw new InvalidArgumentException('Dompet transaksi tidak valid.');
    if($amount<=0)return true;
    $protection=walletProtectionFromData($d,$walletId);
    $balances=walletBalancesFromData($d,(int)$excludeTransactionId);
    $gross=(int)($balances[$walletId]??0);
    if(strtolower((string)$protection['type'])==='credit_card'){
        $debt=max(0,-$gross);
        $available=max(0,(int)$protection['credit_limit']-$debt);
        if($amount>$available){
            $fmt=function($n){return 'Rp'.number_format((int)$n,0,',','.');};
            throw new InvalidArgumentException('Sisa limit '.$protection['name'].' hanya '.$fmt($available).' dari limit '.$fmt($protection['credit_limit']).'.');
        }
        return true;
    }
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

    $initial=0;$reserved=0;$minimum=0;$available=0;$gross=0;$creditLimit=0;$creditDebt=0;$creditAvailable=0;
    if (isset($d['wallets']) && is_array($d['wallets']) && count($d['wallets'])) {
        // Saldo tersedia harus dihitung per dompet terlebih dahulu.
        // Dengan begitu, dompet yang saldonya berada di bawah saldo minimum
        // berkontribusi Rp0 ke total tersedia, bukan nilai negatif yang
        // mengurangi saldo tersedia dari dompet lain.
        $balances=walletBalancesFromData($d);
        foreach($d['wallets'] as $w) if(empty($w['archived'])) {
            $wid=(int)($w['id']??0);
            $walletGross=(int)($balances[$wid]??0);
            if(walletIsCreditCardRecord($w)){
                $limit=max(0,(int)($w['credit_limit']??0));
                $debt=max(0,-$walletGross);
                $creditLimit+=$limit;
                $creditDebt+=$debt;
                $creditAvailable+=max(0,$limit-$debt);
                continue;
            }
            $walletReserved=max(0,(int)($w['reserved_balance']??0));
            $walletMinimum=max(0,(int)($w['minimum_balance']??0));
            $walletProtected=$walletReserved+$walletMinimum;

            $initial+=(int)($w['initial_balance']??0);
            $reserved+=$walletReserved;
            $minimum+=$walletMinimum;
            $gross+=$walletGross;
            $available+=max(0,$walletGross-$walletProtected);
        }
    } else {
        $initial=(int)($d['settings']['initial_balance']??0);
        $gross=$initial+$income-$expense;
        $available=max(0,$gross);
    }

    $protected=$reserved+$minimum;
    return ['initial'=>$initial,'income'=>$income,'expense'=>$expense,'gross_balance'=>$gross,'reserved'=>$reserved,'minimum_balance'=>$minimum,'protected_balance'=>$protected,'available_balance'=>$available,'balance'=>$available,'credit_card_limit'=>$creditLimit,'credit_card_debt'=>$creditDebt,'credit_card_available_limit'=>$creditAvailable];
}
function addChat($role,$message,$attachment=null) {
    $u=authCurrentUser();if(!$u)throw new RuntimeException('User belum login.');
    return FinanceRepository::appendChat((int)$u['id'],(string)$role,(string)$message,$attachment);
}
function addTransaction($t) {
    $u=authCurrentUser();if(!$u)throw new RuntimeException('User belum login.');
    $type=(string)($t['type']??'expense');
    if($type!=='transfer')$t['spending_kind']=transactionSpendingKind(array_merge($t,['type'=>$type]));
    $saved=FinanceRepository::appendTransaction((int)$u['id'],(array)$t);
    if(function_exists('financeSyncCreditCardBills')) financeSyncCreditCardBills();
    return $saved;
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
            elseif(($t['type']??'')==='transfer'){
                assertWalletSpendAllowedData($d,(int)($t['from_wallet_id']??0),(int)($t['amount']??0),$id);
                assertWalletCreditPaymentAllowedData($d,(int)($t['to_wallet_id']??0),(int)($t['amount']??0),$id);
            }
            $t['updated_at']=date('Y-m-d H:i:s');
            auditAdd($d,'update','transaction',$id,$before,$t,true,'Transaksi diperbarui');
            return $t;
        }
        unset($t);
        throw new InvalidArgumentException('Transaksi tidak ditemukan.');
    });
    if(function_exists('financeSyncCreditCardBills')) financeSyncCreditCardBills();
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
    if(function_exists('financeSyncCreditCardBills')) financeSyncCreditCardBills();
    return $r['result'];
}
function allTransactions(){ return readData()['transactions']; }
function recentTransactions($limit=50) { $u=authCurrentUser();if(!$u)return []; $r=FinanceRepository::readTransactionsPage((int)$u['id'],['type'=>'all','from'=>'','to'=>'','sort'=>'date_desc','search'=>'','wallet_id'=>0,'category'=>''],1,max(1,(int)$limit)); return $r['transactions']; }
function recentChats($limit=40){ $u=authCurrentUser();if(!$u)return []; $r=FinanceRepository::readChatsPage((int)$u['id'],1,max(1,(int)$limit)); return $r['items']; }

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
