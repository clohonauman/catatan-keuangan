<?php
namespace app\repositories;

use Yii;
use yii\db\Query;

final class DocumentRepository
{
    private static function enc($v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    public static function get(string $ns,string $key,array $default=[]): array {
        $r=(new Query())->from('{{%app_document}}')->where(['namespace'=>$ns,'document_key'=>$key])->one();
        if(!$r)return $default; $d=json_decode((string)$r['payload_json'],true); return is_array($d)?$d:$default;
    }
    public static function put(string $ns,string $key,array $data): void {
        $db=Yii::$app->db;$old=(new Query())->from('{{%app_document}}')->where(['namespace'=>$ns,'document_key'=>$key])->one();$rev=(int)($old['revision']??0)+1;
        $db->createCommand()->upsert('{{%app_document}}',['namespace'=>$ns,'document_key'=>$key,'payload_json'=>self::enc($data),'revision'=>$rev,'updated_at'=>date('Y-m-d H:i:s')],['payload_json'=>self::enc($data),'revision'=>$rev,'updated_at'=>date('Y-m-d H:i:s')])->execute();
    }
    public static function revision(string $ns,string $key): int {
        return (int)((new Query())->from('{{%app_document}}')->where(['namespace'=>$ns,'document_key'=>$key])->select('revision')->scalar() ?: 0);
    }
    public static function mutate(string $ns,string $key,array $default,callable $fn){
        $db=Yii::$app->db;$tx=$db->beginTransaction();
        try{
            // Ensure row exists first, then lock it. This prevents lost updates when
            // multiple users/processes mutate the same document concurrently.
            $db->createCommand()->upsert('{{%app_document}}',[
                'namespace'=>$ns,'document_key'=>$key,'payload_json'=>self::enc($default),'revision'=>0,'updated_at'=>date('Y-m-d H:i:s')
            ],['document_key'=>$key])->execute();
            $row=$db->createCommand('SELECT payload_json, revision FROM {{%app_document}} WHERE namespace=:n AND document_key=:k FOR UPDATE',[
                ':n'=>$ns,':k'=>$key
            ])->queryOne();
            $d=$row?json_decode((string)$row['payload_json'],true):$default;
            if(!is_array($d))$d=$default;
            $r=$fn($d);
            $rev=(int)($row['revision']??0)+1;
            $db->createCommand()->update('{{%app_document}}',[
                'payload_json'=>self::enc($d),'revision'=>$rev,'updated_at'=>date('Y-m-d H:i:s')
            ],['namespace'=>$ns,'document_key'=>$key])->execute();
            $tx->commit();return $r;
        }catch(\Throwable $e){if($tx->getIsActive())$tx->rollBack();throw $e;}
    }
}
