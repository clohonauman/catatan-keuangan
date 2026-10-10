<?php
namespace app\repositories;

use Yii;
use yii\db\Query;

final class SubscriptionRepository
{
    private static function db(){return Yii::$app->db;}
    private static function enc($v){return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    private static function dec($v,$d=[]){$x=json_decode((string)$v,true);return is_array($x)?$x:$d;}
    private static function doc(): array { $r=(new Query())->from('{{%app_document}}')->where(['namespace'=>'subscription','document_key'=>'meta'])->one(); return $r?self::dec($r['payload_json'],[]):['next_order_id'=>1,'next_coupon_id'=>1,'revision'=>0]; }
    private static function saveDoc(array $m):void{self::db()->createCommand()->upsert('{{%app_document}}',['namespace'=>'subscription','document_key'=>'meta','payload_json'=>self::enc($m),'revision'=>(int)($m['revision']??0),'updated_at'=>date('Y-m-d H:i:s')],['payload_json'=>self::enc($m),'revision'=>(int)($m['revision']??0),'updated_at'=>date('Y-m-d H:i:s')])->execute();}

    public static function read(array $defaults): array
    {
        $plans=[]; foreach((new Query())->from('{{%subscription_plan}}')->all() as $r){$plans[$r['plan_key']]=array_merge(self::dec($r['extra_json'],[]),['key'=>$r['plan_key'],'label'=>$r['label'],'amount'=>(int)$r['amount'],'monthly_equivalent'=>(int)$r['monthly_equivalent'],'months'=>(int)$r['months'],'permanent'=>(bool)$r['permanent'],'description'=>$r['description']]);}
        if(!$plans)$plans=$defaults['plans']??[];
        $banks=[]; foreach((new Query())->from('{{%payment_bank}}')->orderBy(['sort_order'=>SORT_ASC])->all() as $r){$banks[]=array_merge(self::dec($r['extra_json'],[]),['id'=>$r['bank_key'],'name'=>$r['name'],'account_number'=>$r['account_number'],'account_name'=>$r['account_name'],'enabled'=>(bool)$r['enabled'],'sort'=>(int)$r['sort_order']]);}
        if(!$banks)$banks=$defaults['banks']??[];
        $coupons=[];foreach((new Query())->from('{{%coupon}}')->orderBy('id')->all() as $r){$coupons[]=array_merge(self::dec($r['extra_json'],[]),['id'=>(int)$r['id'],'code'=>$r['code'],'discount_type'=>$r['discount_type'],'discount_value'=>(int)$r['discount_value'],'expires_at'=>$r['expires_at']??'','enabled'=>(bool)$r['enabled'],'created_at'=>$r['created_at'],'updated_at'=>$r['updated_at']]);}
        $orders=[];foreach((new Query())->from('{{%subscription_order}}')->orderBy('id')->all() as $r){$x=self::dec($r['order_json'],[]);$x['id']=(int)$r['id'];$x['user_id']=(int)$r['user_id'];$x['invoice_no']=$r['invoice_no'];$x['status']=$r['status'];if($r['proof_json'])$x['proof']=self::dec($r['proof_json'],[]);$orders[]=$x;}
        return ['plans'=>$plans,'banks'=>$banks,'coupons'=>$coupons,'orders'=>$orders,'meta'=>array_replace(['next_order_id'=>1,'next_coupon_id'=>1,'revision'=>0],self::doc())];
    }

    public static function mutate(array $defaults, callable $fn){$tx=self::db()->beginTransaction();try{$d=self::read($defaults);$r=$fn($d);$d['meta']['revision']=(int)($d['meta']['revision']??0)+1;self::replace($d);$tx->commit();return $r;}catch(\Throwable $e){$tx->rollBack();throw $e;}}
    public static function replace(array $d):void{
        self::db()->createCommand()->delete('{{%subscription_plan}}')->execute(); foreach((array)$d['plans'] as $k=>$x){self::db()->createCommand()->insert('{{%subscription_plan}}',['plan_key'=>$x['key']??$k,'label'=>$x['label']??$k,'amount'=>(int)($x['amount']??0),'monthly_equivalent'=>(int)($x['monthly_equivalent']??0),'months'=>(int)($x['months']??0),'permanent'=>!empty($x['permanent']),'description'=>$x['description']??null,'extra_json'=>null])->execute();}
        self::db()->createCommand()->delete('{{%payment_bank}}')->execute(); foreach((array)$d['banks'] as $x){self::db()->createCommand()->insert('{{%payment_bank}}',['bank_key'=>(string)$x['id'],'name'=>$x['name']??'','account_number'=>$x['account_number']??'','account_name'=>$x['account_name']??'','enabled'=>!empty($x['enabled']),'sort_order'=>(int)($x['sort']??0),'extra_json'=>null])->execute();}
        self::db()->createCommand()->delete('{{%coupon}}')->execute(); foreach((array)$d['coupons'] as $x){self::db()->createCommand()->insert('{{%coupon}}',['id'=>(int)$x['id'],'code'=>$x['code']??'','discount_type'=>$x['discount_type']??'fixed','discount_value'=>(int)($x['discount_value']??0),'expires_at'=>($x['expires_at']??'')?:null,'enabled'=>!empty($x['enabled']),'created_at'=>$x['created_at']??null,'updated_at'=>$x['updated_at']??null,'extra_json'=>null])->execute();}
        self::db()->createCommand()->delete('{{%subscription_order}}')->execute(); foreach((array)$d['orders'] as $x){self::db()->createCommand()->insert('{{%subscription_order}}',['id'=>(int)$x['id'],'user_id'=>(int)$x['user_id'],'invoice_no'=>$x['invoice_no']??('INV-'.(int)$x['id']),'status'=>$x['status']??'waiting_payment','plan_key'=>$x['plan_key']??($x['plan']??null),'base_amount'=>(int)($x['base_amount']??$x['amount']??0),'discount_amount'=>(int)($x['discount_amount']??0),'final_amount'=>(int)($x['final_amount']??$x['amount']??0),'bank_key'=>$x['bank_id']??($x['bank']['id']??null),'proof_json'=>isset($x['proof'])?self::enc($x['proof']):null,'order_json'=>self::enc($x),'created_at'=>$x['created_at']??date('Y-m-d H:i:s'),'updated_at'=>$x['updated_at']??($x['created_at']??date('Y-m-d H:i:s'))])->execute();}
        self::saveDoc($d['meta']??[]);
    }
}
