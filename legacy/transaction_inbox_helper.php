<?php

use yii\db\Query;

require_once __DIR__.'/receipt_helper.php';

/**
 * Smart Transaction Assistant V29
 *
 * Stores quick-capture drafts in the existing user_setting table under a
 * private key. This keeps the feature backward-compatible and avoids a new
 * database migration while still getting FK/CASCADE cleanup with the user.
 */

function transactionInboxSettingKey(): string { return '__transaction_inbox'; }

function transactionInboxDefaultData(): array {
    return [
        'version'=>1,
        'next_id'=>1,
        'items'=>[],
        'reconciliation'=>[
            'date'=>'',
            'completed_at'=>'',
            'last_prompt_at'=>'',
        ],
    ];
}

function transactionInboxUserId(): int {
    $u=authCurrentUser();
    return $u?(int)($u['id']??0):0;
}

function transactionInboxNormalize(array $data): array {
    $base=transactionInboxDefaultData();
    $data=array_replace_recursive($base,$data);
    if(!isset($data['items'])||!is_array($data['items']))$data['items']=[];
    if(!isset($data['reconciliation'])||!is_array($data['reconciliation']))$data['reconciliation']=$base['reconciliation'];
    $max=0;$now=time();$clean=[];
    foreach($data['items'] as $item){
        if(!is_array($item))continue;
        $id=max(0,(int)($item['id']??0));if($id<=0)continue;
        $max=max($max,$id);
        $status=(string)($item['status']??'pending');
        // Keep pending items. Resolved/dismissed items are retained briefly so
        // retries can be idempotent, then automatically pruned.
        if(in_array($status,['resolved','dismissed'],true)){
            $stamp=strtotime((string)($item['updated_at']??$item['created_at']??''));
            if($stamp && $stamp < $now-14*86400)continue;
        }
        if(count($clean)>=220)continue;
        $clean[]=$item;
    }
    $data['items']=$clean;
    $data['next_id']=max((int)($data['next_id']??1),$max+1,1);
    return $data;
}

function transactionInboxReadForUser(int $uid): array {
    if($uid<=0)return transactionInboxDefaultData();
    $row=(new Query())->from('{{%user_setting}}')->where(['user_id'=>$uid,'setting_key'=>transactionInboxSettingKey()])->one();
    $raw=$row?json_decode((string)($row['value_json']??''),true):null;
    return transactionInboxNormalize(is_array($raw)?$raw:[]);
}

function transactionInboxWriteForUser(int $uid,array $data): void {
    if($uid<=0)throw new RuntimeException('User belum login.');
    $data=transactionInboxNormalize($data);
    $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    \Yii::$app->db->createCommand()->upsert('{{%user_setting}}',[
        'user_id'=>$uid,
        'setting_key'=>transactionInboxSettingKey(),
        'value_json'=>$json,
        'updated_at'=>date('Y-m-d H:i:s'),
    ],[
        'value_json'=>$json,
        'updated_at'=>date('Y-m-d H:i:s'),
    ])->execute();
}

function transactionInboxMutate(callable $fn){
    $uid=transactionInboxUserId();if($uid<=0)throw new RuntimeException('User belum login.');
    $db=\Yii::$app->db;$tx=$db->beginTransaction();
    try{
        $row=$db->createCommand('SELECT value_json FROM {{%user_setting}} WHERE user_id=:u AND setting_key=:k FOR UPDATE',[
            ':u'=>$uid,':k'=>transactionInboxSettingKey()
        ])->queryOne();
        $raw=$row?json_decode((string)($row['value_json']??''),true):[];
        $data=transactionInboxNormalize(is_array($raw)?$raw:[]);
        $result=$fn($data);
        transactionInboxWriteForUser($uid,$data);
        $tx->commit();
        return $result;
    }catch(Throwable $e){if($tx->getIsActive())$tx->rollBack();throw $e;}
}

function transactionInboxTextNorm(string $text): string {
    $t=strtolower(trim($text));$t=str_replace(['–','—'],'-',$t);
    $t=preg_replace('/[^\p{L}\p{N}\s]+/u',' ',$t);
    return trim(preg_replace('/\s+/u',' ',$t));
}

function transactionInboxSuggest(string $type,string $note,int $amount,string $date): array {
    $type=in_array($type,['expense','income','transfer'],true)?$type:'expense';
    $noteNorm=transactionInboxTextNorm($note);
    $wallets=financeWallets(false);
    $suggested=[
        'type'=>$type,
        'amount'=>$amount,
        'transaction_date'=>$date,
        'note'=>$note,
    ];

    // Wallet mention by actual wallet name. Otherwise use default wallet.
    $mentioned=[];
    foreach($wallets as $w){
        $name=trim((string)($w['name']??''));if($name==='')continue;
        if(strpos($noteNorm,transactionInboxTextNorm($name))!==false)$mentioned[]=(int)$w['id'];
    }

    if($type==='transfer'){
        $suggested['category']='Transfer Antar Dompet';
        $suggested['from_wallet_id']=$mentioned[0]??financeDefaultWalletId();
        // Transfer hanya boleh dikonfirmasi cepat jika arah transfer cukup jelas
        // dari dua nama dompet yang disebut. Jika tidak, user wajib Edit & Simpan.
        $to=$mentioned[1]??0;
        if($to>0)$suggested['to_wallet_id']=$to;
        $suggested['spending_kind']='once';
        return $suggested;
    }

    $suggested['wallet_id']=$mentioned[0]??financeDefaultWalletId();
    $bestName='Lainnya';$bestScore=0;
    foreach(financeCategories() as $cat){
        if(!empty($cat['archived']))continue;
        $catType=strtolower((string)($cat['type']??'both'));
        if($catType!=='both'&&$catType!==$type)continue;
        $score=0;$name=transactionInboxTextNorm((string)($cat['name']??''));
        if($name!==''&&preg_match('/(?:^|\s)'.preg_quote($name,'/').'(?:$|\s)/u',$noteNorm))$score+=6;
        foreach((array)($cat['keywords']??[]) as $kw){
            $kw=transactionInboxTextNorm((string)$kw);if($kw!==''&&preg_match('/(?:^|\s)'.preg_quote($kw,'/').'(?:$|\s)/u',$noteNorm))$score+=3;
        }
        if($score>$bestScore){$bestScore=$score;$bestName=(string)($cat['name']??'Lainnya');}
    }
    $suggested['category']=$bestName;
    $suggested['spending_kind']=$type==='expense'?transactionSpendingKind($suggested):'once';
    // Gunakan pola personal yang sudah dipelajari untuk memperbaiki saran
    // kategori/dompet, tetapi jenis transaksi yang dipilih user tetap final.
    if(function_exists('adaptiveApplyToDrafts')){
        try{
            $refined=adaptiveApplyToDrafts($note,[$suggested]);
            if(isset($refined[0])&&is_array($refined[0]))$suggested=array_merge($suggested,$refined[0]);
        }catch(Throwable $e){}
        $suggested['type']=$type;
        $suggested['amount']=$amount;
        $suggested['transaction_date']=$date;
        $suggested['note']=$note;
    }
    return $suggested;
}

function transactionInboxAddQuick(array $input, ?array $attachment=null, array $ocr=[]): array {
    $type=strtolower(trim((string)($input['type']??'expense')));
    if(!in_array($type,['expense','income','transfer'],true))$type='expense';
    $amount=max(0,(int)($input['amount']??0));if($amount<=0)throw new InvalidArgumentException('Nominal harus lebih dari nol.');
    $note=substr(trim((string)($input['note']??'')),0,255);
    if($note==='')throw new InvalidArgumentException('Tulis keterangan singkat agar transaksi mudah dirapikan nanti.');
    $date=trim((string)($input['transaction_date']??date('Y-m-d')));
    if(!financeIsValidDate($date))$date=date('Y-m-d');
    $suggested=transactionInboxSuggest($type,$note,$amount,$date);
    return transactionInboxMutate(function(&$data)use($type,$amount,$note,$date,$suggested,$attachment,$ocr){
        $id=(int)$data['next_id']++;
        $item=[
            'id'=>$id,'status'=>'pending','type'=>$type,'amount'=>$amount,'note'=>$note,
            'transaction_date'=>$date,'suggested'=>$suggested,'source'=>'quick_add',
            'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')
        ];
        if($attachment&&is_array($attachment))$item['attachment']=$attachment;
        if($ocr&&is_array($ocr))$item['ocr']=$ocr;
        array_unshift($data['items'],$item);
        if(count($data['items'])>220)$data['items']=array_slice($data['items'],0,220);
        return $item;
    });
}

function transactionInboxFind(int $id): ?array {
    $data=transactionInboxReadForUser(transactionInboxUserId());
    foreach($data['items'] as $item)if((int)($item['id']??0)===$id)return $item;
    return null;
}

function transactionInboxDismiss(int $id): bool {
    if($id<=0)throw new InvalidArgumentException('Draf transaksi tidak valid.');
    $existing=transactionInboxFind($id);
    $result=transactionInboxMutate(function(&$data)use($id){
        foreach($data['items'] as &$item){
            if((int)($item['id']??0)!==$id)continue;
            $item['status']='dismissed';$item['updated_at']=date('Y-m-d H:i:s');return true;
        }unset($item);
        throw new InvalidArgumentException('Draf transaksi tidak ditemukan.');
    });
    $file=basename((string)($existing['attachment']['file']??''));
    if($file!=='')deleteReceiptFileIfUnused($file);
    return $result;
}

function transactionInboxConfirm(int $id,array $input): array {
    if($id<=0)throw new InvalidArgumentException('Draf transaksi tidak valid.');
    $item=transactionInboxFind($id);if(!$item)throw new InvalidArgumentException('Draf transaksi tidak ditemukan.');
    if(($item['status']??'pending')==='resolved' && !empty($item['resolved_transaction_id'])){
        foreach(allTransactions() as $tx)if((int)($tx['id']??0)===(int)$item['resolved_transaction_id'])return $tx;
    }
    if(($item['status']??'pending')==='dismissed')throw new InvalidArgumentException('Draf transaksi ini sudah dihapus.');

    transactionInboxMutate(function(&$data)use($id){
        foreach($data['items'] as &$row)if((int)($row['id']??0)===$id){$row['status']='processing';$row['processing_at']=date('Y-m-d H:i:s');$row['updated_at']=date('Y-m-d H:i:s');return true;}unset($row);
        throw new InvalidArgumentException('Draf transaksi tidak ditemukan.');
    });

    try{
        $merged=array_merge((array)($item['suggested']??[]),$input);
        $merged['amount']=(int)($input['amount']??$item['amount']??0);
        $merged['transaction_date']=(string)($input['transaction_date']??$item['transaction_date']??date('Y-m-d'));
        $merged['note']=substr(trim((string)($input['note']??$item['note']??'')),0,255);
        if(isset($item['attachment'])&&is_array($item['attachment']))$merged['attachment']=$item['attachment'];
        if(isset($item['ocr'])&&is_array($item['ocr']))$merged['ocr']=$item['ocr'];
        $saved=financeCreateManualTransaction($merged);

        transactionInboxMutate(function(&$data)use($id,$saved){
            foreach($data['items'] as &$row)if((int)($row['id']??0)===$id){
                $row['status']='resolved';$row['resolved_transaction_id']=(int)($saved['id']??0);$row['updated_at']=date('Y-m-d H:i:s');unset($row['processing_at']);return true;
            }unset($row);return false;
        });

        // Inbox corrections also teach the personal adaptive model.
        try{
            $pending=['message'=>(string)($item['note']??''),'drafts'=>[(array)($item['suggested']??[])]];
            adaptiveLearnFromConfirmation($pending,[$saved]);
        }catch(Throwable $e){if(class_exists('Yii'))\Yii::warning('Inbox learning gagal: '.$e->getMessage(),'adaptive-learning');}
        return $saved;
    }catch(Throwable $e){
        transactionInboxMutate(function(&$data)use($id){foreach($data['items'] as &$row)if((int)($row['id']??0)===$id){$row['status']='pending';unset($row['processing_at']);$row['updated_at']=date('Y-m-d H:i:s');break;}unset($row);});
        throw $e;
    }
}


function transactionInboxCanConfirmItem(array $item): bool {
    $s=(array)($item['suggested']??[]);
    $type=(string)($s['type']??$item['type']??'expense');
    if($type==='transfer'){
        $from=(int)($s['from_wallet_id']??0);$to=(int)($s['to_wallet_id']??0);
        return $from>0&&$to>0&&$from!==$to;
    }
    return (int)($s['wallet_id']??0)>0;
}

function transactionInboxConfirmAll(array $ids=[]): array {
    $wanted=array_values(array_unique(array_filter(array_map('intval',$ids),fn($v)=>$v>0)));
    $snapshot=transactionInboxSnapshot();
    $items=(array)($snapshot['items']??[]);
    if($wanted){
        $lookup=array_fill_keys($wanted,true);
        $items=array_values(array_filter($items,fn($item)=>isset($lookup[(int)($item['id']??0)])));
    }
    $confirmed=[];$failed=[];$skipped=[];
    foreach($items as $item){
        $id=(int)($item['id']??0);
        if($id<=0)continue;
        if(!transactionInboxCanConfirmItem($item)){
            $skipped[]=['id'=>$id,'reason'=>'Draf perlu dilengkapi terlebih dahulu.'];
            continue;
        }
        try{
            $saved=transactionInboxConfirm($id,(array)($item['suggested']??[]));
            $confirmed[]=['id'=>$id,'transaction_id'=>(int)($saved['id']??0)];
        }catch(Throwable $e){
            $failed[]=['id'=>$id,'error'=>$e->getMessage()];
        }
    }
    return [
        'confirmed_count'=>count($confirmed),
        'confirmed'=>$confirmed,
        'failed_count'=>count($failed),
        'failed'=>$failed,
        'skipped_count'=>count($skipped),
        'skipped'=>$skipped,
    ];
}

function transactionInboxMarkReconciled(string $date=''): array {
    $date=$date?:date('Y-m-d');if(!financeIsValidDate($date))$date=date('Y-m-d');
    transactionInboxMutate(function(&$data)use($date){$data['reconciliation']=['date'=>$date,'completed_at'=>date('Y-m-d H:i:s'),'last_prompt_at'=>date('Y-m-d H:i:s')];return true;});
    return transactionInboxReconciliationSnapshot($date);
}

function transactionInboxReconciliationSnapshot(string $date=''): array {
    $date=$date?:date('Y-m-d');$income=0;$expense=0;$count=0;
    foreach(allTransactions() as $tx){
        if((string)($tx['transaction_date']??'')!==$date)continue;$count++;
        if(($tx['type']??'')==='income')$income+=(int)($tx['amount']??0);
        elseif(($tx['type']??'')==='expense')$expense+=(int)($tx['amount']??0);
    }
    $data=transactionInboxReadForUser(transactionInboxUserId());
    $pending=count(array_filter($data['items'],fn($x)=>($x['status']??'pending')==='pending'));
    $done=((string)($data['reconciliation']['date']??'')===$date)&&!empty($data['reconciliation']['completed_at']);
    $hour=(int)date('G');
    $notify=financeNotificationSettings();
    $enabled=!array_key_exists('daily_reconciliation',$notify)||!empty($notify['daily_reconciliation']);
    return [
        'date'=>$date,'transaction_count'=>$count,'income'=>$income,'expense'=>$expense,
        'pending_count'=>$pending,'completed'=>$done,'completed_at'=>$done?(string)$data['reconciliation']['completed_at']:'',
        'prompt_due'=>$enabled&&!$done&&$hour>=19,
        'prompt_hour'=>19,'enabled'=>$enabled,
    ];
}

function transactionInboxSnapshot(): array {
    $data=transactionInboxReadForUser(transactionInboxUserId());
    $items=array_values(array_filter($data['items'],fn($x)=>($x['status']??'pending')==='pending'));
    usort($items,fn($a,$b)=>(int)($b['id']??0)<=>(int)($a['id']??0));
    return [
        'items'=>$items,
        'pending_count'=>count($items),
        'reconciliation'=>transactionInboxReconciliationSnapshot(),
    ];
}

function transactionInboxRealtimeSignatureFromData(array $data): string {
    $data=transactionInboxNormalize($data);
    // Signature mengikuti isi penyimpanan draf + status rekonsiliasi.
    // Ini sengaja tidak bergantung pada revision transaksi utama karena Draf
    // Transaksi disimpan di user_setting dan dapat berubah secara independen.
    return sha1(json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}

function transactionInboxRealtimeSignatureForUser(int $uid): string {
    return transactionInboxRealtimeSignatureFromData(transactionInboxReadForUser($uid));
}

function transactionInboxExportCurrentUser(): array {
    return transactionInboxReadForUser(transactionInboxUserId());
}

function transactionInboxImportCurrentUser(array $payload,bool $replace=true): void {
    $uid=transactionInboxUserId();if($uid<=0)return;
    $incoming=transactionInboxNormalize($payload);
    if(!$replace){
        $current=transactionInboxReadForUser($uid);
        $incoming['items']=array_merge($current['items'],$incoming['items']);
        $incoming['next_id']=max((int)$current['next_id'],(int)$incoming['next_id']);
    }
    transactionInboxWriteForUser($uid,$incoming);
}
