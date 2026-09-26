<?php
namespace app\repositories;

use Yii;
use yii\db\Query;
use yii\db\Expression;
use yii\db\IntegrityException;

final class DocumentRepository
{
    private static function enc($v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    public static function get(string $ns,string $key,array $default=[]): array {
        $r=(new Query())->from('{{%app_document}}')->where(['namespace'=>$ns,'document_key'=>$key])->one();
        if(!$r)return $default; $d=json_decode((string)$r['payload_json'],true); return is_array($d)?$d:$default;
    }
    public static function revision(string $ns,string $key): int {
        $v=(new Query())->select('revision')->from('{{%app_document}}')->where(['namespace'=>$ns,'document_key'=>$key])->scalar();
        return $v===false||$v===null?0:(int)$v;
    }
    public static function put(string $ns,string $key,array $data): void {
        $db=Yii::$app->db;$json=self::enc($data);$now=date('Y-m-d H:i:s');
        $db->createCommand()->upsert('{{%app_document}}',[
            'namespace'=>$ns,'document_key'=>$key,'payload_json'=>$json,'revision'=>1,'updated_at'=>$now
        ],[
            'payload_json'=>$json,'revision'=>new Expression('[[revision]] + 1'),'updated_at'=>$now
        ])->execute();
    }
    public static function mutate(string $ns,string $key,array $default,callable $fn){
        $db=Yii::$app->db;$active=$db->getTransaction();$owned=!$active||!$active->getIsActive();$tx=$owned?$db->beginTransaction():$active;
        try{
            // Pastikan row ada sebelum FOR UPDATE. Ini mencegah dua request pertama
            // menulis dokumen yang sama secara bersamaan dan saling menimpa.
            try{$db->createCommand()->insert('{{%app_document}}',[
                'namespace'=>$ns,'document_key'=>$key,'payload_json'=>self::enc($default),'revision'=>0,'updated_at'=>date('Y-m-d H:i:s')
            ])->execute();}catch(IntegrityException $ignore){}
            $row=$db->createCommand('SELECT [[payload_json]],[[revision]] FROM {{%app_document}} WHERE [[namespace]]=:ns AND [[document_key]]=:k FOR UPDATE',[
                ':ns'=>$ns,':k'=>$key
            ])->queryOne();
            $d=$default;if($row){$decoded=json_decode((string)$row['payload_json'],true);if(is_array($decoded))$d=$decoded;}
            $r=$fn($d);
            $db->createCommand()->update('{{%app_document}}',[
                'payload_json'=>self::enc($d),'revision'=>(int)($row['revision']??0)+1,'updated_at'=>date('Y-m-d H:i:s')
            ],['namespace'=>$ns,'document_key'=>$key])->execute();
            if($owned)$tx->commit();return $r;
        }catch(\Throwable $e){if($owned&&$tx->getIsActive())$tx->rollBack();throw $e;}
    }
}
