<?php
namespace app\repositories;

use Yii;
use yii\db\Query;

final class AdaptiveLearningRepository
{
    private const MAX_EXAMPLES_PER_USER = 600;
    private const FALLBACK_NS = 'assistant_adaptive';
    private static $storageCache = null;

    private static function db(){ return Yii::$app->db; }
    private static function jenc($v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    private static function jdec($v, $default=[]){ $x=json_decode((string)$v,true); return is_array($x)?$x:$default; }
    private static function tableName(string $name): string { return (string)self::db()->tablePrefix.$name; }
    private static function fallbackKey(int $userId): string { return 'user_'.$userId; }
    private static function fallbackDefault(): array { return ['examples'=>[],'stats'=>[],'next_id'=>1]; }

    /**
     * Prefer the dedicated MySQL tables. If they are unavailable (for example a
     * live/shared-hosting database where the migration was not executed yet),
     * transparently fall back to app_document, which already exists in the app.
     * This keeps adaptive learning functional without requiring a deployment-time
     * migration.
     */
    private static function storageModeInternal(): string
    {
        if(self::$storageCache!==null) return (string)self::$storageCache;
        try {
            $schema=self::db()->schema;
            $ok=$schema->getTableSchema(self::tableName('assistant_training_example'), true)!==null
                && $schema->getTableSchema(self::tableName('assistant_token_stat'), true)!==null;
            if($ok){ self::$storageCache='mysql'; return 'mysql'; }
        } catch (\Throwable $e) {
            // fall through to document storage
        }

        try {
            // DocumentRepository will throw if app_document is not available.
            DocumentRepository::get(self::FALLBACK_NS,'health_check',[]);
            self::$storageCache='document';
            return 'document';
        } catch (\Throwable $e) {
            self::$storageCache='none';
            return 'none';
        }
    }

    public static function storageMode(): string { return self::storageModeInternal(); }
    public static function available(): bool { return self::storageModeInternal()!=='none'; }

    private static function fallbackRead(int $userId): array
    {
        if($userId<=0) return self::fallbackDefault();
        $d=DocumentRepository::get(self::FALLBACK_NS,self::fallbackKey($userId),self::fallbackDefault());
        if(!is_array($d))$d=self::fallbackDefault();
        $d=array_replace_recursive(self::fallbackDefault(),$d);
        if(!is_array($d['examples']??null))$d['examples']=[];
        if(!is_array($d['stats']??null))$d['stats']=[];
        return $d;
    }

    public static function countExamples(int $userId): int
    {
        if($userId<=0 || !self::available()) return 0;
        if(self::storageModeInternal()==='document') return count(self::fallbackRead($userId)['examples']);
        return (int)(new Query())->from('{{%assistant_training_example}}')->where(['user_id'=>$userId])->count('*', self::db());
    }

    public static function recentExamples(int $userId,int $limit=120): array
    {
        if($userId<=0 || !self::available()) return [];
        $limit=max(1,min(250,$limit));
        if(self::storageModeInternal()==='document'){
            $rows=array_reverse(self::fallbackRead($userId)['examples']);
            $rows=array_slice($rows,0,$limit);
            foreach($rows as &$row){
                $row['id']=(int)($row['id']??0);
                $row['tokens']=is_array($row['tokens']??null)?$row['tokens']:self::jdec($row['tokens_json']??'[]',[]);
                $row['parsed']=is_array($row['parsed']??null)?$row['parsed']:self::jdec($row['parsed_json']??'[]',[]);
                $row['confirmed']=is_array($row['confirmed']??null)?$row['confirmed']:self::jdec($row['confirmed_json']??'[]',[]);
                $row['correction']=is_array($row['correction']??null)?$row['correction']:self::jdec($row['correction_json']??'[]',[]);
            }
            unset($row);
            return $rows;
        }
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
        $tokens=array_values(array_unique(array_slice(array_filter(array_map('strval',$tokens)),0,80)));
        if(!$tokens) return [];
        if(self::storageModeInternal()==='document'){
            $d=self::fallbackRead($userId);$out=[];
            foreach($d['stats'] as $row){
                if(!is_array($row))continue;
                if(!in_array((string)($row['token']??''),$tokens,true))continue;
                if((int)($row['positive_count']??0)<=0)continue;
                $out[]=$row;
            }
            return $out;
        }
        return (new Query())->from('{{%assistant_token_stat}}')->where(['user_id'=>$userId,'token'=>$tokens])->all(self::db());
    }

    public static function recordExample(int $userId,array $row,array $tokenSignals): void
    {
        if($userId<=0 || !self::available()) return;
        if(self::storageModeInternal()==='document'){
            DocumentRepository::mutate(self::FALLBACK_NS,self::fallbackKey($userId),self::fallbackDefault(),function(&$d)use($row,$tokenSignals){
                $id=(int)($d['next_id']??1);
                $example=$row;
                $example['id']=$id;
                $example['created_at']=date('Y-m-d H:i:s');
                $example['updated_at']=date('Y-m-d H:i:s');
                $example['use_count']=(int)($example['use_count']??0);
                $example['last_used_at']=$example['last_used_at']??null;
                $d['next_id']=$id+1;
                $d['examples'][]=$example;
                foreach($tokenSignals as $sig){
                    $token=substr((string)($sig['token']??''),0,80);
                    $field=substr((string)($sig['field_name']??''),0,40);
                    $value=substr((string)($sig['field_value']??''),0,190);
                    $weight=max(1,min(5,(int)($sig['weight']??1)));
                    if($token===''||$field===''||$value==='') continue;
                    $found=false;
                    foreach($d['stats'] as &$stat){
                        if((string)($stat['token']??'')===$token && (string)($stat['field_name']??'')===$field && (string)($stat['field_value']??'')===$value){
                            $stat['positive_count']=(int)($stat['positive_count']??0)+$weight;
                            $stat['last_seen_at']=date('Y-m-d H:i:s');
                            $found=true;break;
                        }
                    }
                    unset($stat);
                    if(!$found)$d['stats'][]=['token'=>$token,'field_name'=>$field,'field_value'=>$value,'positive_count'=>$weight,'last_seen_at'=>date('Y-m-d H:i:s')];
                }
                self::fallbackPruneData($d);
                return $d;
            });
            return;
        }
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
        if(self::storageModeInternal()==='document'){
            DocumentRepository::mutate(self::FALLBACK_NS,self::fallbackKey($userId),self::fallbackDefault(),function(&$d)use($signals){
                foreach($signals as $sig){
                    $token=substr((string)($sig['token']??''),0,80);
                    $field=substr((string)($sig['field_name']??''),0,40);
                    $value=substr((string)($sig['field_value']??''),0,190);
                    $weight=max(1,min(5,(int)($sig['weight']??1)));
                    if($token===''||$field===''||$value==='')continue;
                    foreach($d['stats'] as &$stat){
                        if((string)($stat['token']??'')===$token && (string)($stat['field_name']??'')===$field && (string)($stat['field_value']??'')===$value){
                            $stat['positive_count']=max(0,(int)($stat['positive_count']??0)-$weight);
                            $stat['last_seen_at']=date('Y-m-d H:i:s');
                            break;
                        }
                    }
                    unset($stat);
                }
                return $d;
            });
            return;
        }
        $db=self::db();
        foreach($signals as $sig){
            $token=substr((string)($sig['token']??''),0,80);
            $field=substr((string)($sig['field_name']??''),0,40);
            $value=substr((string)($sig['field_value']??''),0,190);
            $weight=max(1,min(5,(int)($sig['weight']??1)));
            if($token===''||$field===''||$value==='') continue;
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
        if(self::storageModeInternal()==='document') return;
        self::db()->createCommand()->update('{{%assistant_training_example}}',[
            'use_count'=>new \yii\db\Expression('use_count + 1'),
            'last_used_at'=>date('Y-m-d H:i:s'),
        ],['id'=>$ids])->execute();
    }

    private static function fallbackPruneData(array &$d): void
    {
        $max=self::MAX_EXAMPLES_PER_USER;
        if(count($d['examples'])>$max)$d['examples']=array_slice($d['examples'],-$max);
        if(count($d['stats'])>4000){
            usort($d['stats'],fn($a,$b)=>(int)($b['positive_count']??0)<=> (int)($a['positive_count']??0));
            $d['stats']=array_slice($d['stats'],0,4000);
        }
    }

    private static function prune(int $userId): void
    {
        $count=self::countExamples($userId);$excess=$count-self::MAX_EXAMPLES_PER_USER;
        if($excess<=0) return;
        if(self::storageModeInternal()==='document'){
            DocumentRepository::mutate(self::FALLBACK_NS,self::fallbackKey($userId),self::fallbackDefault(),function(&$d)use($excess){$d['examples']=array_values(array_slice($d['examples'],$excess));return $d;});
            return;
        }
        $ids=(new Query())->select('id')->from('{{%assistant_training_example}}')->where(['user_id'=>$userId])->orderBy(['id'=>SORT_ASC])->limit($excess)->column(self::db());
        if($ids) self::db()->createCommand()->delete('{{%assistant_training_example}}',['id'=>array_map('intval',$ids)])->execute();
    }

    public static function exportUser(int $userId): array
    {
        if($userId<=0 || !self::available()) return ['version'=>1,'examples'=>[],'token_stats'=>[],'storage'=>self::storageModeInternal()];
        if(self::storageModeInternal()==='document'){
            $d=self::fallbackRead($userId);
            return ['version'=>1,'examples'=>$d['examples'],'token_stats'=>$d['stats'],'storage'=>'document'];
        }
        $examples=(new Query())->from('{{%assistant_training_example}}')->where(['user_id'=>$userId])->orderBy(['id'=>SORT_ASC])->all(self::db());
        $stats=(new Query())->from('{{%assistant_token_stat}}')->where(['user_id'=>$userId])->orderBy(['token'=>SORT_ASC,'field_name'=>SORT_ASC])->all(self::db());
        foreach($examples as &$x) unset($x['id'],$x['user_id']); unset($x);
        foreach($stats as &$x) unset($x['user_id']); unset($x);
        return ['version'=>1,'examples'=>$examples,'token_stats'=>$stats,'storage'=>'mysql'];
    }

    public static function importUser(int $userId,array $payload,bool $replace=true): void
    {
        if($userId<=0 || !self::available()) return;
        if(self::storageModeInternal()==='document'){
            DocumentRepository::mutate(self::FALLBACK_NS,self::fallbackKey($userId),self::fallbackDefault(),function(&$d)use($payload,$replace){
                if($replace)$d=self::fallbackDefault();
                $examples=(array)($payload['examples']??[]);$stats=(array)($payload['token_stats']??[]);
                foreach(array_slice($examples,-self::MAX_EXAMPLES_PER_USER) as $x){
                    if(!is_array($x))continue;
                    $x['id']=(int)($d['next_id']??1);$d['next_id']=$x['id']+1;$d['examples'][]=$x;
                }
                $d['stats']=$stats;self::fallbackPruneData($d);return $d;
            });
            return;
        }
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
