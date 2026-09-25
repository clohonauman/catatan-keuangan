<?php
namespace app\repositories;

use Yii;
use yii\db\Query;

final class AdaptiveLearningRepository
{
    private const MAX_EXAMPLES_PER_USER = 600;
    private static $availableCache = null;

    private static function db(){ return Yii::$app->db; }
    private static function jenc($v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    private static function jdec($v, $default=[]){ $x=json_decode((string)$v,true); return is_array($x)?$x:$default; }
    private static function tableName(string $name): string { return (string)self::db()->tablePrefix.$name; }

    public static function available(): bool
    {
        if(self::$availableCache!==null) return (bool)self::$availableCache;
        try {
            $schema=self::db()->schema;
            self::$availableCache=$schema->getTableSchema(self::tableName('assistant_training_example'), true)!==null
                && $schema->getTableSchema(self::tableName('assistant_token_stat'), true)!==null;
        } catch (\Throwable $e) {
            self::$availableCache=false;
        }
        return (bool)self::$availableCache;
    }

    public static function countExamples(int $userId): int
    {
        if($userId<=0 || !self::available()) return 0;
        return (int)(new Query())->from('{{%assistant_training_example}}')->where(['user_id'=>$userId])->count('*', self::db());
    }

    public static function recentExamples(int $userId,int $limit=120): array
    {
        if($userId<=0 || !self::available()) return [];
        $limit=max(1,min(250,$limit));
        $rows=(new Query())->from('{{%assistant_training_example}}')->where(['user_id'=>$userId])->orderBy(['id'=>SORT_DESC])->limit($limit)->all(self::db());
        foreach($rows as &$row){
            $row['tokens']=self::jdec($row['tokens_json']??'[]',[]);
            $row['parsed']=self::jdec($row['parsed_json']??'[]',[]);
            $row['confirmed']=self::jdec($row['confirmed_json']??'[]',[]);
            $row['correction']=self::jdec($row['correction_json']??'[]',[]);
        }
        unset($row);
        return $rows;
    }

    public static function tokenStats(int $userId,array $tokens): array
    {
        if($userId<=0 || !$tokens || !self::available()) return [];
        $tokens=array_values(array_unique(array_slice(array_filter(array_map('strval',$tokens)),0,40)));
        if(!$tokens) return [];
        return (new Query())->from('{{%assistant_token_stat}}')->where(['user_id'=>$userId,'token'=>$tokens])->all(self::db());
    }

    public static function recordExample(int $userId,array $row,array $tokenSignals): void
    {
        if($userId<=0 || !self::available()) return;
        $db=self::db();$now=date('Y-m-d H:i:s');
        $db->createCommand()->insert('{{%assistant_training_example}}',[
            'user_id'=>$userId,
            'message_hash'=>(string)($row['message_hash']??hash('sha256',(string)($row['normalized_text']??''))),
            'source_message'=>(string)($row['source_message']??''),
            'normalized_text'=>(string)($row['normalized_text']??''),
            'tokens_json'=>self::jenc((array)($row['tokens']??[])),
            'parsed_json'=>self::jenc((array)($row['parsed']??[])),
            'confirmed_json'=>self::jenc((array)($row['confirmed']??[])),
            'correction_json'=>self::jenc((array)($row['correction']??[])),
            'was_corrected'=>!empty($row['was_corrected']),
            'use_count'=>0,
            'last_used_at'=>null,
            'created_at'=>$now,
            'updated_at'=>$now,
        ])->execute();

        foreach($tokenSignals as $sig){
            $token=substr((string)($sig['token']??''),0,80);
            $field=substr((string)($sig['field_name']??''),0,40);
            $value=substr((string)($sig['field_value']??''),0,190);
            $weight=max(1,min(5,(int)($sig['weight']??1)));
            if($token===''||$field===''||$value==='') continue;
            $db->createCommand()->upsert('{{%assistant_token_stat}}',[
                'user_id'=>$userId,'token'=>$token,'field_name'=>$field,'field_value'=>$value,
                'positive_count'=>$weight,'last_seen_at'=>$now,
            ],[
                'positive_count'=>new \yii\db\Expression('positive_count + '.(int)$weight),
                'last_seen_at'=>$now,
            ])->execute();
        }
        self::prune($userId);
    }


    public static function penalizeTokenSignals(int $userId,array $signals): void
    {
        if($userId<=0 || !$signals || !self::available()) return;
        $db=self::db();
        foreach($signals as $sig){
            $token=substr((string)($sig['token']??''),0,80);
            $field=substr((string)($sig['field_name']??''),0,40);
            $value=substr((string)($sig['field_value']??''),0,190);
            $weight=max(1,min(5,(int)($sig['weight']??1)));
            if($token===''||$field===''||$value==='') continue;
            // Jangan biarkan UNSIGNED menjadi negatif. Jika asosiasi lama
            // sudah 0, biarkan 0 agar koreksi baru dapat mengambil alih.
            $db->createCommand()->update('{{%assistant_token_stat}}',[
                'positive_count'=>new \yii\db\Expression('GREATEST(0, positive_count - '.(int)$weight.')'),
                'last_seen_at'=>date('Y-m-d H:i:s'),
            ],[
                'user_id'=>$userId,
                'token'=>$token,
                'field_name'=>$field,
                'field_value'=>$value,
            ])->execute();
        }
    }

    public static function markExamplesUsed(array $ids): void
    {
        if(!$ids || !self::available()) return;
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),fn($v)=>$v>0)));
        if(!$ids) return;
        self::db()->createCommand()->update('{{%assistant_training_example}}',[
            'use_count'=>new \yii\db\Expression('use_count + 1'),
            'last_used_at'=>date('Y-m-d H:i:s'),
        ],['id'=>$ids])->execute();
    }

    private static function prune(int $userId): void
    {
        $count=self::countExamples($userId);$excess=$count-self::MAX_EXAMPLES_PER_USER;
        if($excess<=0) return;
        $ids=(new Query())->select('id')->from('{{%assistant_training_example}}')->where(['user_id'=>$userId])->orderBy(['id'=>SORT_ASC])->limit($excess)->column(self::db());
        if($ids) self::db()->createCommand()->delete('{{%assistant_training_example}}',['id'=>array_map('intval',$ids)])->execute();
    }

    public static function exportUser(int $userId): array
    {
        if($userId<=0 || !self::available()) return ['version'=>1,'examples'=>[],'token_stats'=>[]];
        $examples=(new Query())->from('{{%assistant_training_example}}')->where(['user_id'=>$userId])->orderBy(['id'=>SORT_ASC])->all(self::db());
        $stats=(new Query())->from('{{%assistant_token_stat}}')->where(['user_id'=>$userId])->orderBy(['token'=>SORT_ASC,'field_name'=>SORT_ASC])->all(self::db());
        foreach($examples as &$x) unset($x['id'],$x['user_id']); unset($x);
        foreach($stats as &$x) unset($x['user_id']); unset($x);
        return ['version'=>1,'examples'=>$examples,'token_stats'=>$stats];
    }

    public static function importUser(int $userId,array $payload,bool $replace=true): void
    {
        if($userId<=0 || !self::available()) return;
        $db=self::db();$tx=$db->beginTransaction();
        try{
            if($replace){
                $db->createCommand()->delete('{{%assistant_training_example}}',['user_id'=>$userId])->execute();
                $db->createCommand()->delete('{{%assistant_token_stat}}',['user_id'=>$userId])->execute();
            }
            foreach(array_slice((array)($payload['examples']??[]),-self::MAX_EXAMPLES_PER_USER) as $x){
                if(!is_array($x)) continue;
                $db->createCommand()->insert('{{%assistant_training_example}}',[
                    'user_id'=>$userId,
                    'message_hash'=>substr((string)($x['message_hash']??hash('sha256',(string)($x['normalized_text']??''))),0,64),
                    'source_message'=>(string)($x['source_message']??''),
                    'normalized_text'=>substr((string)($x['normalized_text']??''),0,500),
                    'tokens_json'=>(string)($x['tokens_json']??'[]'),
                    'parsed_json'=>$x['parsed_json']??null,
                    'confirmed_json'=>(string)($x['confirmed_json']??'[]'),
                    'correction_json'=>$x['correction_json']??null,
                    'was_corrected'=>!empty($x['was_corrected']),
                    'use_count'=>(int)($x['use_count']??0),
                    'last_used_at'=>($x['last_used_at']??'')?:null,
                    'created_at'=>($x['created_at']??'')?:date('Y-m-d H:i:s'),
                    'updated_at'=>($x['updated_at']??'')?:date('Y-m-d H:i:s'),
                ])->execute();
            }
            foreach((array)($payload['token_stats']??[]) as $x){
                if(!is_array($x)) continue;
                $token=substr((string)($x['token']??''),0,80);$field=substr((string)($x['field_name']??''),0,40);$value=substr((string)($x['field_value']??''),0,190);
                if($token===''||$field===''||$value==='') continue;
                $db->createCommand()->upsert('{{%assistant_token_stat}}',[
                    'user_id'=>$userId,'token'=>$token,'field_name'=>$field,'field_value'=>$value,
                    'positive_count'=>max(1,(int)($x['positive_count']??1)),
                    'last_seen_at'=>($x['last_seen_at']??'')?:date('Y-m-d H:i:s'),
                ],[
                    'positive_count'=>max(1,(int)($x['positive_count']??1)),
                    'last_seen_at'=>($x['last_seen_at']??'')?:date('Y-m-d H:i:s'),
                ])->execute();
            }
            $tx->commit();
        }catch(\Throwable $e){$tx->rollBack();throw $e;}
    }
}
