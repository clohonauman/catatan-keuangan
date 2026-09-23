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
    public static function mutate(string $ns,string $key,array $default,callable $fn){$tx=Yii::$app->db->beginTransaction();try{$d=self::get($ns,$key,$default);$r=$fn($d);self::put($ns,$key,$d);$tx->commit();return $r;}catch(\Throwable $e){$tx->rollBack();throw $e;}}
}
