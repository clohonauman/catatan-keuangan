<?php
namespace app\repositories;

use Yii;
use yii\db\Query;

final class FinanceRepository
{
    private static function db() { return Yii::$app->db; }
    private static function jenc($v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    private static function jdec($v, $default=[]) { $x=json_decode((string)$v,true); return is_array($x)?$x:$default; }
    private static function mergeExtra(array $base, $extra): array { return array_merge(self::jdec($extra, []), $base); }

    public static function ensureUser(int $userId): void
    {
        $exists=(new Query())->from('{{%finance_meta}}')->where(['user_id'=>$userId])->exists(self::db());
        if(!$exists) self::db()->createCommand()->insert('{{%finance_meta}}',[
            'user_id'=>$userId,'revision'=>0,'next_transaction_id'=>1,'next_chat_id'=>1,'next_wallet_id'=>2,
            'next_category_id'=>14,'next_bill_id'=>1,'next_recurring_id'=>1,'next_goal_id'=>1,'next_audit_id'=>1,
            'next_confirmation_id'=>1,'extra_json'=>null,'updated_at'=>date('Y-m-d H:i:s')
        ])->execute();
    }

    public static function read(int $userId): array
    {
        self::ensureUser($userId);
        $settings=[];
        foreach((new Query())->from('{{%user_setting}}')->where(['user_id'=>$userId])->all() as $r){
            if(str_starts_with($r['setting_key'],'__')) continue;
            $settings[$r['setting_key']]=json_decode((string)$r['value_json'],true);
        }
        $pendingRow=(new Query())->from('{{%user_setting}}')->where(['user_id'=>$userId,'setting_key'=>'__pending_chat_confirmation'])->one();
        $pending=$pendingRow?self::jdec($pendingRow['value_json'],[]):[];

        $m=(new Query())->from('{{%finance_meta}}')->where(['user_id'=>$userId])->one();
        $meta=self::jdec($m['extra_json']??'',[]);
        foreach(['revision','next_transaction_id','next_chat_id','next_wallet_id','next_category_id','next_bill_id','next_recurring_id','next_goal_id','next_audit_id','next_confirmation_id'] as $k) $meta[$k]=(int)($m[$k]??0);
        $ops=[];
        foreach((new Query())->from('{{%offline_operation}}')->where(['user_id'=>$userId])->all() as $r){
            $ops[$r['operation_id']]=['applied_at'=>$r['applied_at'],'response'=>self::jdec($r['response_json'],[])];
        }
        if($ops) $meta['offline_applied_ops']=$ops;

        return [
            'settings'=>$settings,
            'transactions'=>self::readTransactions($userId),
            'chats'=>self::readChats($userId),
            'meta'=>$meta,
            'wallets'=>self::readWallets($userId),
            'categories'=>self::readCategories($userId),
            'monthly_budgets'=>self::readBudgets($userId),
            'bills'=>self::readBills($userId),
            'recurring'=>self::readRecurring($userId),
            'goals'=>self::readGoals($userId),
            'pending_chat_confirmation'=>$pending,
            'audit_log'=>self::readAudit($userId),
        ];
    }

    private static function transactionRow(array $r): array
    {
        $x=['id'=>(int)$r['legacy_id'],'type'=>$r['type'],'category'=>$r['category'],'amount'=>(int)$r['amount'],'note'=>$r['note']??'','transaction_date'=>$r['transaction_date'],'created_at'=>$r['created_at']];
        foreach(['wallet_id','from_wallet_id','to_wallet_id','bill_id'] as $k) if(array_key_exists($k,$r)&&$r[$k]!==null)$x[$k]=(int)$r[$k];
        foreach(['spending_kind','source','updated_at'] as $k) if(array_key_exists($k,$r)&&$r[$k]!==null&&$r[$k]!=='')$x[$k]=$r[$k];
        if(!empty($r['attachment_json']))$x['attachment']=self::jdec($r['attachment_json'],[]);
        if(!empty($r['ocr_json']))$x['ocr']=self::jdec($r['ocr_json'],[]);
        return self::mergeExtra($x,$r['extra_json']??null);
    }

    private static function chatRow(array $r): array
    {
        $x=['id'=>(int)$r['legacy_id'],'role'=>$r['role'],'message'=>$r['message'],'created_at'=>$r['created_at']];
        if(!empty($r['attachment_json']))$x['attachment']=self::jdec($r['attachment_json'],[]);
        return self::mergeExtra($x,$r['extra_json']??null);
    }

    public static function readSettingValue(int $userId,string $key,$default=null)
    {
        $raw=(new Query())->select('value_json')->from('{{%user_setting}}')->where(['user_id'=>$userId,'setting_key'=>$key])->scalar(self::db());
        if($raw===false||$raw===null)return $default;
        $decoded=json_decode((string)$raw,true);
        return json_last_error()===JSON_ERROR_NONE?$decoded:$default;
    }

    public static function dailyExpenseTotal(int $userId,string $date): int
    {
        return (int)(new Query())->from('{{%finance_transaction}}')->where(['user_id'=>$userId,'type'=>'expense','transaction_date'=>$date])->sum('amount',self::db());
    }

    public static function realtimeState(int $userId): array
    {
        self::ensureUser($userId);
        $revision=(int)(new Query())->select('revision')->from('{{%finance_meta}}')->where(['user_id'=>$userId])->scalar(self::db());
        $chat=(new Query())->select(['c'=>'COUNT(*)','m'=>'COALESCE(MAX([[legacy_id]]),0)'])->from('{{%chat_message}}')->where(['user_id'=>$userId])->one(self::db())?:[];
        $tx=self::db()->createCommand(
            'SELECT COUNT(*) c, COALESCE(MAX([[legacy_id]]),0) m, COALESCE(MAX(COALESCE([[updated_at]],[[created_at]])),\'\') u FROM {{%finance_transaction}} WHERE [[user_id]]=:u',
            [':u'=>$userId]
        )->queryOne()?:[];
        return [
            'revision'=>$revision,
            'chat_count'=>(int)($chat['c']??0),
            'chat_signature'=>sha1('c|'.(int)($chat['c']??0).'|'.(int)($chat['m']??0)),
            'transaction_count'=>(int)($tx['c']??0),
            'transaction_signature'=>sha1('t|'.(int)($tx['c']??0).'|'.(int)($tx['m']??0).'|'.(string)($tx['u']??'')),
        ];
    }

    public static function offlineOperation(int $userId,string $operationId): ?array
    {
        $r=(new Query())->from('{{%offline_operation}}')->where(['user_id'=>$userId,'operation_id'=>$operationId])->one(self::db());
        if(!$r)return null;
        return ['applied_at'=>$r['applied_at'],'response'=>self::jdec($r['response_json'],[])];
    }

    public static function rememberOfflineOperation(int $userId,string $operationId,array $response): void
    {
        $db=self::db();$now=date('Y-m-d H:i:s');
        $db->createCommand()->upsert('{{%offline_operation}}',[
            'user_id'=>$userId,'operation_id'=>$operationId,'applied_at'=>$now,'response_json'=>self::jenc($response)
        ],['applied_at'=>$now,'response_json'=>self::jenc($response)])->execute();
        $count=(int)(new Query())->from('{{%offline_operation}}')->where(['user_id'=>$userId])->count('*',$db);
        if($count>300){
            $keep=(new Query())->select('operation_id')->from('{{%offline_operation}}')->where(['user_id'=>$userId])
                ->orderBy(['applied_at'=>SORT_DESC])->limit(250)->column($db);
            if($keep)$db->createCommand()->delete('{{%offline_operation}}',['and',['user_id'=>$userId],['not in','operation_id',$keep]])->execute();
        }
    }

    public static function writeSettingValue(int $userId,string $key,$value): void
    {
        self::ensureUser($userId);$db=self::db();$active=$db->getTransaction();$owned=!$active||!$active->getIsActive();$tx=$owned?$db->beginTransaction():$active;
        try{
            $db->createCommand('SELECT [[user_id]] FROM {{%finance_meta}} WHERE [[user_id]]=:u FOR UPDATE',[':u'=>$userId])->queryOne();
            self::upsertSetting($userId,$key,$value);
            $db->createCommand()->update('{{%finance_meta}}',['revision'=>new \yii\db\Expression('[[revision]] + 1'),'updated_at'=>date('Y-m-d H:i:s')],['user_id'=>$userId])->execute();
            if($owned)$tx->commit();
        }catch(\Throwable $e){if($owned&&$tx->getIsActive())$tx->rollBack();throw $e;}
    }

    private static function walletSpendCapacity(int $userId,int $walletId): array
    {
        $w=(new Query())->from('{{%wallet}}')->where(['user_id'=>$userId,'legacy_id'=>$walletId])->one(self::db());
        if(!$w)throw new \InvalidArgumentException('Dompet transaksi tidak ditemukan.');
        $normal=self::db()->createCommand(
            "SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount WHEN type='expense' THEN -amount ELSE 0 END),0) d FROM {{%finance_transaction}} WHERE user_id=:u AND wallet_id=:w AND type IN ('income','expense')",
            [':u'=>$userId,':w'=>$walletId]
        )->queryScalar();
        $out=self::db()->createCommand("SELECT COALESCE(SUM(amount),0) FROM {{%finance_transaction}} WHERE user_id=:u AND type='transfer' AND from_wallet_id=:w",[':u'=>$userId,':w'=>$walletId])->queryScalar();
        $in=self::db()->createCommand("SELECT COALESCE(SUM(amount),0) FROM {{%finance_transaction}} WHERE user_id=:u AND type='transfer' AND to_wallet_id=:w",[':u'=>$userId,':w'=>$walletId])->queryScalar();
        $gross=(int)$w['initial_balance']+(int)$normal-(int)$out+(int)$in;
        $reserved=max(0,(int)$w['reserved_balance']);$minimum=max(0,(int)$w['minimum_balance']);
        return ['name'=>(string)$w['name'],'gross'=>$gross,'reserved'=>$reserved,'minimum'=>$minimum,'available'=>max(0,$gross-$reserved-$minimum)];
    }

    public static function appendTransaction(int $userId,array $t): array
    {
        self::ensureUser($userId);$db=self::db();$active=$db->getTransaction();$owned=!$active||!$active->getIsActive();$tx=$owned?$db->beginTransaction():$active;
        try{
            $meta=$db->createCommand('SELECT [[next_transaction_id]],[[next_audit_id]] FROM {{%finance_meta}} WHERE [[user_id]]=:u FOR UPDATE',[':u'=>$userId])->queryOne();
            $type=(string)($t['type']??'expense');$amount=max(0,(int)($t['amount']??0));
            if(!in_array($type,['income','expense','transfer'],true))throw new \InvalidArgumentException('Jenis transaksi tidak valid.');
            if($amount<=0)throw new \InvalidArgumentException('Nominal harus lebih dari nol.');
            if($type==='expense'){
                $cap=self::walletSpendCapacity($userId,(int)($t['wallet_id']??1));
                if($amount>$cap['available'])throw new \InvalidArgumentException('Saldo tersedia '.$cap['name'].' hanya Rp'.number_format($cap['available'],0,',','.').'.');
            }elseif($type==='transfer'){
                $from=(int)($t['from_wallet_id']??0);$to=(int)($t['to_wallet_id']??0);
                if($from<=0||$to<=0||$from===$to)throw new \InvalidArgumentException('Dompet asal/tujuan transfer tidak valid.');
                $cap=self::walletSpendCapacity($userId,$from);
                if($amount>$cap['available'])throw new \InvalidArgumentException('Saldo tersedia '.$cap['name'].' hanya Rp'.number_format($cap['available'],0,',','.').'.');
            }
            $id=max(1,(int)($meta['next_transaction_id']??1));$auditId=max(1,(int)($meta['next_audit_id']??1));$now=date('Y-m-d H:i:s');
            $x=[
                'id'=>$id,'type'=>$type,'category'=>$t['category']??'Lainnya','amount'=>$amount,'note'=>$t['note']??'',
                'transaction_date'=>$t['transaction_date']??date('Y-m-d'),'created_at'=>$now
            ];
            if($type==='transfer'){$x['from_wallet_id']=(int)$t['from_wallet_id'];$x['to_wallet_id']=(int)$t['to_wallet_id'];}
            else{$x['wallet_id']=(int)($t['wallet_id']??1);if(isset($t['spending_kind']))$x['spending_kind']=$t['spending_kind'];}
            foreach(['source','bill_id'] as $k)if(isset($t[$k])&&$t[$k]!==''&&$t[$k]!==null)$x[$k]=$k==='bill_id'?(int)$t[$k]:$t[$k];
            if(isset($t['attachment'])&&is_array($t['attachment'])&&!empty($t['attachment']['file']))$x['attachment']=$t['attachment'];
            if(isset($t['ocr'])&&is_array($t['ocr']))$x['ocr']=$t['ocr'];
            $db->createCommand()->insert('{{%finance_transaction}}',[
                'user_id'=>$userId,'legacy_id'=>$id,'type'=>$type,'category'=>$x['category'],'amount'=>$amount,'note'=>$x['note'],
                'transaction_date'=>$x['transaction_date'],'wallet_id'=>$x['wallet_id']??null,'from_wallet_id'=>$x['from_wallet_id']??null,'to_wallet_id'=>$x['to_wallet_id']??null,
                'spending_kind'=>$x['spending_kind']??null,'bill_id'=>$x['bill_id']??null,'source'=>$x['source']??null,
                'attachment_json'=>isset($x['attachment'])?self::jenc($x['attachment']):null,'ocr_json'=>isset($x['ocr'])?self::jenc($x['ocr']):null,
                'created_at'=>$now,'updated_at'=>null,'extra_json'=>null
            ])->execute();
            $db->createCommand()->insert('{{%audit_log}}',[
                'user_id'=>$userId,'legacy_id'=>$auditId,'action'=>'create','entity_type'=>'transaction','entity_id'=>$id,'label'=>'Transaksi dibuat',
                'before_json'=>null,'after_json'=>self::jenc($x),'undoable'=>0,'undone_at'=>null,'created_at'=>$now,'extra_json'=>null
            ])->execute();
            $db->createCommand()->update('{{%finance_meta}}',[
                'next_transaction_id'=>$id+1,'next_audit_id'=>$auditId+1,'revision'=>new \yii\db\Expression('[[revision]] + 1'),'updated_at'=>$now
            ],['user_id'=>$userId])->execute();
            if($owned)$tx->commit();return $x;
        }catch(\Throwable $e){if($owned&&$tx->getIsActive())$tx->rollBack();throw $e;}
    }

    public static function appendChat(int $userId,string $role,string $message,?array $attachment=null): array
    {
        self::ensureUser($userId);$db=self::db();$active=$db->getTransaction();$owned=!$active||!$active->getIsActive();$tx=$owned?$db->beginTransaction():$active;
        try{
            $meta=$db->createCommand('SELECT [[next_chat_id]] FROM {{%finance_meta}} WHERE [[user_id]]=:u FOR UPDATE',[':u'=>$userId])->queryOne();
            $id=max(1,(int)($meta['next_chat_id']??1));$now=date('Y-m-d H:i:s');
            $row=['user_id'=>$userId,'legacy_id'=>$id,'role'=>$role,'message'=>$message,'attachment_json'=>$attachment?self::jenc($attachment):null,'created_at'=>$now,'extra_json'=>null];
            $db->createCommand()->insert('{{%chat_message}}',$row)->execute();
            $db->createCommand()->update('{{%finance_meta}}',['next_chat_id'=>$id+1,'revision'=>new \yii\db\Expression('[[revision]] + 1'),'updated_at'=>$now],['user_id'=>$userId])->execute();
            if($owned)$tx->commit();
            return ['id'=>$id,'role'=>$role,'message'=>$message,'created_at'=>$now]+($attachment?['attachment'=>$attachment]:[]);
        }catch(\Throwable $e){if($owned&&$tx->getIsActive())$tx->rollBack();throw $e;}
    }

    public static function deleteChatDirect(int $userId,int $legacyId): ?array
    {
        $db=self::db();$active=$db->getTransaction();$owned=!$active||!$active->getIsActive();$tx=$owned?$db->beginTransaction():$active;
        try{
            self::ensureUser($userId);$db->createCommand('SELECT [[user_id]] FROM {{%finance_meta}} WHERE [[user_id]]=:u FOR UPDATE',[':u'=>$userId])->queryOne();
            $row=(new Query())->from('{{%chat_message}}')->where(['user_id'=>$userId,'legacy_id'=>$legacyId])->one($db);
            if(!$row){if($owned)$tx->commit();return null;}
            $db->createCommand()->delete('{{%chat_message}}',['user_id'=>$userId,'legacy_id'=>$legacyId])->execute();
            $db->createCommand()->update('{{%finance_meta}}',['revision'=>new \yii\db\Expression('[[revision]] + 1'),'updated_at'=>date('Y-m-d H:i:s')],['user_id'=>$userId])->execute();
            if($owned)$tx->commit();return self::chatRow($row);
        }catch(\Throwable $e){if($owned&&$tx->getIsActive())$tx->rollBack();throw $e;}
    }

    public static function clearChatsDirect(int $userId): array
    {
        $db=self::db();$active=$db->getTransaction();$owned=!$active||!$active->getIsActive();$tx=$owned?$db->beginTransaction():$active;
        try{
            self::ensureUser($userId);$db->createCommand('SELECT [[user_id]] FROM {{%finance_meta}} WHERE [[user_id]]=:u FOR UPDATE',[':u'=>$userId])->queryOne();
            $rows=(new Query())->from('{{%chat_message}}')->where(['user_id'=>$userId])->orderBy('legacy_id')->all($db);
            $deleted=array_map([self::class,'chatRow'],$rows);
            $count=$db->createCommand()->delete('{{%chat_message}}',['user_id'=>$userId])->execute();
            if($count>0)$db->createCommand()->update('{{%finance_meta}}',['revision'=>new \yii\db\Expression('[[revision]] + 1'),'updated_at'=>date('Y-m-d H:i:s')],['user_id'=>$userId])->execute();
            if($owned)$tx->commit();return $deleted;
        }catch(\Throwable $e){if($owned&&$tx->getIsActive())$tx->rollBack();throw $e;}
    }

    public static function recentTransactionsDirect(int $userId,int $limit=10): array
    {
        $limit=max(1,min(100,$limit));
        $rows=(new Query())->from('{{%finance_transaction}}')->where(['user_id'=>$userId])
            ->orderBy(['transaction_date'=>SORT_DESC,'legacy_id'=>SORT_DESC])->limit($limit)->all(self::db());
        return array_map([self::class,'transactionRow'],$rows);
    }

    public static function recentChatsDirect(int $userId,int $limit=10): array
    {
        $limit=max(1,min(100,$limit));
        $rows=(new Query())->from('{{%chat_message}}')->where(['user_id'=>$userId])
            ->orderBy(['legacy_id'=>SORT_DESC])->limit($limit)->all(self::db());
        $rows=array_reverse($rows);
        return array_map([self::class,'chatRow'],$rows);
    }

    public static function paginatedChats(int $userId,int $page=1,int $limit=10): array
    {
        $page=max(1,$page);$limit=max(1,min(50,$limit));$offset=($page-1)*$limit;
        $total=(int)(new Query())->from('{{%chat_message}}')->where(['user_id'=>$userId])->count('*',self::db());
        $rows=(new Query())->from('{{%chat_message}}')->where(['user_id'=>$userId])
            ->orderBy(['legacy_id'=>SORT_DESC])->offset($offset)->limit($limit)->all(self::db());
        $rows=array_reverse($rows);
        $items=array_map([self::class,'chatRow'],$rows);
        $loadedThrough=min($total,$offset+count($items));
        return ['chats'=>$items,'meta'=>[
            'page'=>$page,'limit'=>$limit,'total'=>$total,'loaded'=>count($items),
            'has_more'=>$loadedThrough<$total,'next_page'=>$loadedThrough<$total?$page+1:null,
        ]];
    }

    public static function queryTransactions(int $userId,array $filters,int $page=1,int $limit=10): array
    {
        $page=max(1,$page);$limit=max(1,min(100,$limit));$offset=($page-1)*$limit;
        $where=['and',['user_id'=>$userId]];
        if(($filters['type']??'all')!=='all')$where[]=['type'=>(string)$filters['type']];
        if(!empty($filters['from']))$where[]=['>=','transaction_date',$filters['from']];
        if(!empty($filters['to']))$where[]=['<=','transaction_date',$filters['to']];
        if(!empty($filters['category']))$where[]=['category'=>(string)$filters['category']];
        if(!empty($filters['wallet_id'])){
            $wid=(int)$filters['wallet_id'];
            $where[]=['or',['and',['<>','type','transfer'],['wallet_id'=>$wid]],['and',['type'=>'transfer'],['or',['from_wallet_id'=>$wid],['to_wallet_id'=>$wid]]]];
        }
        if(!empty($filters['search'])){
            $term=(string)$filters['search'];
            $where[]=['or',['like','note',$term],['like','category',$term],['like',new \yii\db\Expression('CAST([[amount]] AS CHAR)'),$term]];
        }
        $base=(new Query())->from('{{%finance_transaction}}')->where($where);
        $agg=(clone $base)->select([
            'count'=>'COUNT(*)',
            'income'=>new \yii\db\Expression("COALESCE(SUM(CASE WHEN [[type]]='income' THEN [[amount]] ELSE 0 END),0)"),
            'expense'=>new \yii\db\Expression("COALESCE(SUM(CASE WHEN [[type]]='expense' THEN [[amount]] ELSE 0 END),0)"),
        ])->one(self::db())?:[];
        $sort=(string)($filters['sort']??'date_desc');
        if($sort==='date_asc')$order=['transaction_date'=>SORT_ASC,'legacy_id'=>SORT_ASC];
        elseif($sort==='amount_desc')$order=['amount'=>SORT_DESC,'transaction_date'=>SORT_DESC,'legacy_id'=>SORT_DESC];
        elseif($sort==='amount_asc')$order=['amount'=>SORT_ASC,'transaction_date'=>SORT_DESC,'legacy_id'=>SORT_DESC];
        else $order=['transaction_date'=>SORT_DESC,'legacy_id'=>SORT_DESC];
        $rows=$base->orderBy($order)->offset($offset)->limit($limit)->all(self::db());
        $items=array_map([self::class,'transactionRow'],$rows);
        $total=(int)($agg['count']??0);$loadedThrough=min($total,$offset+count($items));
        return ['transactions'=>$items,'meta'=>[
            'count'=>$total,'income'=>(int)($agg['income']??0),'expense'=>(int)($agg['expense']??0),
            'type'=>$filters['type']??'all','from'=>$filters['from']??'','to'=>$filters['to']??'',
            'sort'=>$sort,'search'=>$filters['search']??'','wallet_id'=>(int)($filters['wallet_id']??0),'category'=>$filters['category']??'',
            'pagination'=>['page'=>$page,'limit'=>$limit,'total'=>$total,'loaded'=>count($items),'loaded_through'=>$loadedThrough,'has_more'=>$loadedThrough<$total,'next_page'=>$loadedThrough<$total?$page+1:null],
        ]];
    }

    public static function monthlyExpenseCategories(int $userId,string $month): array
    {
        $from=$month.'-01';$to=date('Y-m-t',strtotime($from));
        $rows=(new Query())->select(['category','total'=>'SUM([[amount]])'])->from('{{%finance_transaction}}')
            ->where(['user_id'=>$userId,'type'=>'expense'])->andWhere(['between','transaction_date',$from,$to])
            ->groupBy('category')->orderBy(['total'=>SORT_DESC])->all(self::db());
        return array_map(fn($r)=>['category'=>(string)($r['category']?:'Lainnya'),'total'=>(int)$r['total']],$rows);
    }

    public static function summaryDirect(int $userId): array
    {
        $tot=(new Query())->select([
            'income'=>new \yii\db\Expression("COALESCE(SUM(CASE WHEN [[type]]='income' THEN [[amount]] ELSE 0 END),0)"),
            'expense'=>new \yii\db\Expression("COALESCE(SUM(CASE WHEN [[type]]='expense' THEN [[amount]] ELSE 0 END),0)"),
        ])->from('{{%finance_transaction}}')->where(['user_id'=>$userId])->one(self::db())?:[];
        $wallets=(new Query())->from('{{%wallet}}')->where(['user_id'=>$userId,'archived'=>0])->orderBy('legacy_id')->all(self::db());
        if(!$wallets){
            $initial=(int)self::readSettingValue($userId,'initial_balance',0);
            $gross=$initial+(int)($tot['income']??0)-(int)($tot['expense']??0);
            return ['initial'=>$initial,'income'=>(int)($tot['income']??0),'expense'=>(int)($tot['expense']??0),'gross_balance'=>$gross,'reserved'=>0,'minimum_balance'=>0,'protected_balance'=>0,'available_balance'=>max(0,$gross),'balance'=>max(0,$gross)];
        }
        $sql="SELECT wid,SUM(delta) delta FROM (\n"
            ."SELECT wallet_id wid,SUM(CASE WHEN type='income' THEN amount WHEN type='expense' THEN -amount ELSE 0 END) delta FROM {{%finance_transaction}} WHERE user_id=:u AND type IN ('income','expense') GROUP BY wallet_id\n"
            ."UNION ALL SELECT from_wallet_id wid,-SUM(amount) delta FROM {{%finance_transaction}} WHERE user_id=:u AND type='transfer' GROUP BY from_wallet_id\n"
            ."UNION ALL SELECT to_wallet_id wid,SUM(amount) delta FROM {{%finance_transaction}} WHERE user_id=:u AND type='transfer' GROUP BY to_wallet_id\n"
            .") q WHERE wid IS NOT NULL GROUP BY wid";
        $deltas=[];foreach(self::db()->createCommand($sql,[':u'=>$userId])->queryAll() as $r)$deltas[(int)$r['wid']]=(int)$r['delta'];
        $initial=$reserved=$minimum=$gross=$available=0;
        foreach($wallets as $w){
            $wid=(int)$w['legacy_id'];$wi=(int)$w['initial_balance'];$wr=max(0,(int)$w['reserved_balance']);$wm=max(0,(int)$w['minimum_balance']);$wg=$wi+($deltas[$wid]??0);
            $initial+=$wi;$reserved+=$wr;$minimum+=$wm;$gross+=$wg;$available+=max(0,$wg-$wr-$wm);
        }
        return ['initial'=>$initial,'income'=>(int)($tot['income']??0),'expense'=>(int)($tot['expense']??0),'gross_balance'=>$gross,'reserved'=>$reserved,'minimum_balance'=>$minimum,'protected_balance'=>$reserved+$minimum,'available_balance'=>$available,'balance'=>$available];
    }

    private static function readWallets(int $u): array { $out=[]; foreach((new Query())->from('{{%wallet}}')->where(['user_id'=>$u])->orderBy('legacy_id')->all() as $r){$out[]=self::mergeExtra(['id'=>(int)$r['legacy_id'],'name'=>$r['name'],'type'=>$r['type'],'initial_balance'=>(int)$r['initial_balance'],'reserved_balance'=>(int)$r['reserved_balance'],'minimum_balance'=>(int)$r['minimum_balance'],'archived'=>(bool)$r['archived'],'created_at'=>$r['created_at']],$r['extra_json']);} return $out; }
    private static function readCategories(int $u): array { $out=[]; foreach((new Query())->from('{{%category}}')->where(['user_id'=>$u])->orderBy('legacy_id')->all() as $r){$out[]=self::mergeExtra(['id'=>(int)$r['legacy_id'],'name'=>$r['name'],'type'=>$r['type'],'icon'=>$r['icon'],'keywords'=>self::jdec($r['keywords_json'],[]),'archived'=>(bool)$r['archived']],$r['extra_json']);} return $out; }
    private static function readTransactions(int $u): array { return array_map([self::class,'transactionRow'],(new Query())->from('{{%finance_transaction}}')->where(['user_id'=>$u])->orderBy('legacy_id')->all()); }
    private static function readChats(int $u): array { return array_map([self::class,'chatRow'],(new Query())->from('{{%chat_message}}')->where(['user_id'=>$u])->orderBy('legacy_id')->all()); }
    private static function readBudgets(int $u): array { $out=[]; foreach((new Query())->from('{{%monthly_budget}}')->where(['user_id'=>$u])->orderBy(['month'=>SORT_ASC,'category_id'=>SORT_ASC])->all() as $r){$out[]=self::mergeExtra(['category_id'=>(int)$r['category_id'],'month'=>$r['month'],'limit'=>(int)$r['limit_amount'],'enabled'=>(bool)$r['enabled']],$r['extra_json']);}return $out; }
    private static function readBills(int $u): array { $out=[]; foreach((new Query())->from('{{%bill}}')->where(['user_id'=>$u])->orderBy('legacy_id')->all() as $r){$out[]=self::mergeExtra(['id'=>(int)$r['legacy_id'],'name'=>$r['name'],'amount'=>(int)$r['amount'],'due_date'=>$r['due_date']??'','due_day'=>(int)($r['due_day']??0),'schedule_type'=>$r['schedule_type'],'category'=>$r['category']??'','wallet_id'=>(int)($r['wallet_id']??0),'reminder_days'=>(int)$r['reminder_days'],'active'=>(bool)$r['active'],'payments'=>self::jdec($r['payments_json'],[]),'created_at'=>$r['created_at']],$r['extra_json']);}return $out; }
    private static function readRecurring(int $u): array { $out=[]; foreach((new Query())->from('{{%recurring_transaction}}')->where(['user_id'=>$u])->orderBy('legacy_id')->all() as $r){$out[]=self::mergeExtra(['id'=>(int)$r['legacy_id'],'name'=>$r['name'],'type'=>$r['type'],'amount'=>(int)$r['amount'],'category'=>$r['category']??'','wallet_id'=>(int)($r['wallet_id']??0),'frequency'=>$r['frequency'],'interval'=>(int)$r['interval_value'],'next_run'=>$r['next_run'],'active'=>(bool)$r['active'],'created_at'=>$r['created_at']],$r['extra_json']);}return $out; }
    private static function readGoals(int $u): array { $out=[]; foreach((new Query())->from('{{%saving_goal}}')->where(['user_id'=>$u])->orderBy('legacy_id')->all() as $r){$out[]=self::mergeExtra(['id'=>(int)$r['legacy_id'],'name'=>$r['name'],'target_amount'=>(int)$r['target_amount'],'current_amount'=>(int)$r['current_amount'],'deadline'=>$r['deadline']??'','active'=>(bool)$r['active'],'created_at'=>$r['created_at']],$r['extra_json']);}return $out; }
    private static function readAudit(int $u): array { $out=[]; foreach((new Query())->from('{{%audit_log}}')->where(['user_id'=>$u])->orderBy('legacy_id')->all() as $r){$out[]=self::mergeExtra(['id'=>(int)$r['legacy_id'],'action'=>$r['action'],'entity_type'=>$r['entity_type'],'entity_id'=>(int)($r['entity_id']??0),'label'=>$r['label']??'','before'=>$r['before_json']?self::jdec($r['before_json'],null):null,'after'=>$r['after_json']?self::jdec($r['after_json'],null):null,'undoable'=>(bool)$r['undoable'],'undone_at'=>$r['undone_at'],'created_at'=>$r['created_at']],$r['extra_json']);}return $out; }

    public static function mutate(int $userId, callable $fn): array
    {
        $active=self::db()->getTransaction();
        $owned=!$active || !$active->getIsActive();
        $tx=$owned?self::db()->beginTransaction():$active;
        try{
            self::ensureUser($userId);
            self::db()->createCommand('SELECT user_id FROM {{%finance_meta}} WHERE user_id=:u FOR UPDATE',[':u'=>$userId])->queryOne();
            $before=self::read($userId); $after=$before;
            $result=$fn($after);
            if(!isset($after['meta'])||!is_array($after['meta']))$after['meta']=[];
            $after['meta']['revision']=max(0,(int)($after['meta']['revision']??0))+1;
            self::sync($userId,$before,$after);
            if($owned)$tx->commit();
            return ['data'=>$after,'result'=>$result];
        }catch(\Throwable $e){if($owned&&$tx->getIsActive())$tx->rollBack();throw $e;}
    }

    public static function replace(int $userId, array $after): void
    {
        $active=self::db()->getTransaction();
        $owned=!$active || !$active->getIsActive();
        $tx=$owned?self::db()->beginTransaction():$active;
        try{ $before=self::read($userId); self::sync($userId,$before,$after,true); if($owned)$tx->commit(); }
        catch(\Throwable $e){if($owned&&$tx->getIsActive())$tx->rollBack();throw $e;}
    }

    private static function same($a,$b): bool { return self::jenc($a)===self::jenc($b); }
    private static function sync(int $u,array $b,array $a,bool $force=false): void
    {
        if($force||!self::same($b['settings']??[],$a['settings']??[])) self::replaceSettings($u,$a['settings']??[]);
        if($force||!self::same($b['pending_chat_confirmation']??[],$a['pending_chat_confirmation']??[])) self::upsertSetting($u,'__pending_chat_confirmation',$a['pending_chat_confirmation']??[]);
        if($force||!self::same($b['meta']??[],$a['meta']??[])) self::replaceMeta($u,$a['meta']??[]);
        $map=['transactions'=>'replaceTransactions','chats'=>'replaceChats','wallets'=>'replaceWallets','categories'=>'replaceCategories','monthly_budgets'=>'replaceBudgets','bills'=>'replaceBills','recurring'=>'replaceRecurring','goals'=>'replaceGoals','audit_log'=>'replaceAudit'];
        foreach($map as $k=>$m) if($force||!self::same($b[$k]??[],$a[$k]??[])) self::$m($u,$a[$k]??[]);
    }
    private static function replaceSettings(int $u,array $s): void { self::db()->createCommand()->delete('{{%user_setting}}',['and',['user_id'=>$u],['not like','setting_key','__%',false]])->execute(); foreach($s as $k=>$v)self::upsertSetting($u,(string)$k,$v); }
    private static function upsertSetting(int $u,string $k,$v): void { self::db()->createCommand()->upsert('{{%user_setting}}',['user_id'=>$u,'setting_key'=>$k,'value_json'=>self::jenc($v),'updated_at'=>date('Y-m-d H:i:s')],['value_json'=>self::jenc($v),'updated_at'=>date('Y-m-d H:i:s')])->execute(); }
    private static function replaceMeta(int $u,array $m): void { $ops=$m['offline_applied_ops']??[]; unset($m['offline_applied_ops']); $cols=['revision','next_transaction_id','next_chat_id','next_wallet_id','next_category_id','next_bill_id','next_recurring_id','next_goal_id','next_audit_id','next_confirmation_id']; $row=['updated_at'=>date('Y-m-d H:i:s')]; foreach($cols as $k){$row[$k]=(int)($m[$k]??($k==='revision'?0:1));unset($m[$k]);}$row['extra_json']=$m?self::jenc($m):null;self::db()->createCommand()->update('{{%finance_meta}}',$row,['user_id'=>$u])->execute();self::db()->createCommand()->delete('{{%offline_operation}}',['user_id'=>$u])->execute();foreach((array)$ops as $id=>$x)self::db()->createCommand()->insert('{{%offline_operation}}',['user_id'=>$u,'operation_id'=>(string)$id,'applied_at'=>$x['applied_at']??date('Y-m-d H:i:s'),'response_json'=>self::jenc($x['response']??[])])->execute(); }

    private static function clear(string $table,int $u): void { self::db()->createCommand()->delete($table,['user_id'=>$u])->execute(); }
    private static function extra(array $x,array $known): ?string { $e=array_diff_key($x,array_flip($known));return $e?self::jenc($e):null; }
    private static function replaceWallets(int $u,array $rows):void{self::clear('{{%wallet}}',$u);foreach($rows as $x)self::db()->createCommand()->insert('{{%wallet}}',['user_id'=>$u,'legacy_id'=>(int)$x['id'],'name'=>(string)($x['name']??''),'type'=>(string)($x['type']??'cash'),'initial_balance'=>(int)($x['initial_balance']??0),'reserved_balance'=>(int)($x['reserved_balance']??0),'minimum_balance'=>(int)($x['minimum_balance']??0),'archived'=>!empty($x['archived']),'created_at'=>$x['created_at']??null,'extra_json'=>self::extra($x,['id','name','type','initial_balance','reserved_balance','minimum_balance','archived','created_at'])])->execute();}
    private static function replaceCategories(int $u,array $rows):void{self::clear('{{%category}}',$u);foreach($rows as $x)self::db()->createCommand()->insert('{{%category}}',['user_id'=>$u,'legacy_id'=>(int)$x['id'],'name'=>(string)($x['name']??''),'type'=>(string)($x['type']??'expense'),'icon'=>$x['icon']??null,'keywords_json'=>self::jenc($x['keywords']??[]),'archived'=>!empty($x['archived']),'extra_json'=>self::extra($x,['id','name','type','icon','keywords','archived'])])->execute();}
    private static function replaceTransactions(int $u,array $rows):void{self::clear('{{%finance_transaction}}',$u);foreach($rows as $x)self::db()->createCommand()->insert('{{%finance_transaction}}',['user_id'=>$u,'legacy_id'=>(int)$x['id'],'type'=>(string)($x['type']??'expense'),'category'=>$x['category']??null,'amount'=>(int)($x['amount']??0),'note'=>$x['note']??'','transaction_date'=>$x['transaction_date']??date('Y-m-d'),'wallet_id'=>isset($x['wallet_id'])?(int)$x['wallet_id']:null,'from_wallet_id'=>isset($x['from_wallet_id'])?(int)$x['from_wallet_id']:null,'to_wallet_id'=>isset($x['to_wallet_id'])?(int)$x['to_wallet_id']:null,'spending_kind'=>$x['spending_kind']??null,'bill_id'=>isset($x['bill_id'])?(int)$x['bill_id']:null,'source'=>$x['source']??null,'attachment_json'=>isset($x['attachment'])?self::jenc($x['attachment']):null,'ocr_json'=>isset($x['ocr'])?self::jenc($x['ocr']):null,'created_at'=>$x['created_at']??date('Y-m-d H:i:s'),'updated_at'=>$x['updated_at']??null,'extra_json'=>self::extra($x,['id','type','category','amount','note','transaction_date','wallet_id','from_wallet_id','to_wallet_id','spending_kind','bill_id','source','attachment','ocr','created_at','updated_at'])])->execute();}
    private static function replaceChats(int $u,array $rows):void{self::clear('{{%chat_message}}',$u);foreach($rows as $x)self::db()->createCommand()->insert('{{%chat_message}}',['user_id'=>$u,'legacy_id'=>(int)$x['id'],'role'=>(string)($x['role']??'assistant'),'message'=>(string)($x['message']??''),'attachment_json'=>isset($x['attachment'])?self::jenc($x['attachment']):null,'created_at'=>$x['created_at']??date('Y-m-d H:i:s'),'extra_json'=>self::extra($x,['id','role','message','attachment','created_at'])])->execute();}
    private static function replaceBudgets(int $u,array $rows):void{self::clear('{{%monthly_budget}}',$u);foreach($rows as $x)self::db()->createCommand()->insert('{{%monthly_budget}}',['user_id'=>$u,'category_id'=>(int)($x['category_id']??0),'month'=>(string)($x['month']??date('Y-m')),'limit_amount'=>(int)($x['limit']??0),'enabled'=>!array_key_exists('enabled',$x)||!empty($x['enabled']),'extra_json'=>self::extra($x,['category_id','month','limit','enabled'])])->execute();}
    private static function replaceBills(int $u,array $rows):void{self::clear('{{%bill}}',$u);foreach($rows as $x)self::db()->createCommand()->insert('{{%bill}}',['user_id'=>$u,'legacy_id'=>(int)$x['id'],'name'=>(string)($x['name']??''),'amount'=>(int)($x['amount']??0),'due_date'=>($x['due_date']??'')?:null,'due_day'=>isset($x['due_day'])?(int)$x['due_day']:null,'schedule_type'=>$x['schedule_type']??'once','category'=>$x['category']??null,'wallet_id'=>isset($x['wallet_id'])?(int)$x['wallet_id']:null,'reminder_days'=>(int)($x['reminder_days']??3),'active'=>!array_key_exists('active',$x)||!empty($x['active']),'payments_json'=>self::jenc($x['payments']??[]),'created_at'=>$x['created_at']??null,'extra_json'=>self::extra($x,['id','name','amount','due_date','due_day','schedule_type','category','wallet_id','reminder_days','active','payments','created_at'])])->execute();}
    private static function replaceRecurring(int $u,array $rows):void{self::clear('{{%recurring_transaction}}',$u);foreach($rows as $x)self::db()->createCommand()->insert('{{%recurring_transaction}}',['user_id'=>$u,'legacy_id'=>(int)$x['id'],'name'=>(string)($x['name']??''),'type'=>$x['type']??'expense','amount'=>(int)($x['amount']??0),'category'=>$x['category']??null,'wallet_id'=>isset($x['wallet_id'])?(int)$x['wallet_id']:null,'frequency'=>$x['frequency']??'monthly','interval_value'=>(int)($x['interval']??1),'next_run'=>$x['next_run']??date('Y-m-d'),'active'=>!array_key_exists('active',$x)||!empty($x['active']),'created_at'=>$x['created_at']??null,'extra_json'=>self::extra($x,['id','name','type','amount','category','wallet_id','frequency','interval','next_run','active','created_at'])])->execute();}
    private static function replaceGoals(int $u,array $rows):void{self::clear('{{%saving_goal}}',$u);foreach($rows as $x)self::db()->createCommand()->insert('{{%saving_goal}}',['user_id'=>$u,'legacy_id'=>(int)$x['id'],'name'=>(string)($x['name']??''),'target_amount'=>(int)($x['target_amount']??0),'current_amount'=>(int)($x['current_amount']??0),'deadline'=>($x['deadline']??'')?:null,'active'=>!array_key_exists('active',$x)||!empty($x['active']),'created_at'=>$x['created_at']??null,'extra_json'=>self::extra($x,['id','name','target_amount','current_amount','deadline','active','created_at'])])->execute();}
    private static function replaceAudit(int $u,array $rows):void{self::clear('{{%audit_log}}',$u);foreach($rows as $x)self::db()->createCommand()->insert('{{%audit_log}}',['user_id'=>$u,'legacy_id'=>(int)$x['id'],'action'=>(string)($x['action']??''),'entity_type'=>$x['entity_type']??null,'entity_id'=>isset($x['entity_id'])?(int)$x['entity_id']:null,'label'=>$x['label']??null,'before_json'=>array_key_exists('before',$x)&&$x['before']!==null?self::jenc($x['before']):null,'after_json'=>array_key_exists('after',$x)&&$x['after']!==null?self::jenc($x['after']):null,'undoable'=>!empty($x['undoable']),'undone_at'=>$x['undone_at']??null,'created_at'=>$x['created_at']??date('Y-m-d H:i:s'),'extra_json'=>self::extra($x,['id','action','entity_type','entity_id','label','before','after','undoable','undone_at','created_at'])])->execute();}
}
