<?php
namespace app\repositories;

use Yii;
use yii\db\Query;

final class AuthRepository
{
    private static function db(){return Yii::$app->db;}
    private static function enc($v){return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    private static function dec($v){$x=json_decode((string)$v,true);return is_array($x)?$x:[];}

    public static function findById(int $id): ?array { $r=(new Query())->from('{{%user}}')->where(['id'=>$id])->one(); return $r?self::hydrate($r):null; }
    public static function findByUsername(string $username): ?array { $r=(new Query())->from('{{%user}}')->where(['username'=>trim($username)])->one(); return $r?self::hydrate($r):null; }
    public static function findByEmail(string $email): ?array { $r=(new Query())->from('{{%user}}')->where(['email'=>strtolower(trim($email))])->one(); return $r?self::hydrate($r):null; }
    public static function findByDeviceHash(string $hash): ?array { $r=(new Query())->from('{{%user_device}}')->where(['token_hash'=>$hash])->one(); return $r?self::findById((int)$r['user_id']):null; }

    private static function hydrate(array $r): array {
        $x=self::dec($r['extra_json']??null);
        foreach(['id','username','email','password_hash','pin_hash','role','plan','plan_expires_at','premium_type','premium_last_invoice','email_verified_at','email_verification_token_hash','email_verification_expires_at','email_verification_sent_at','email_verification_attempts','recovery_token_hash','recovery_token_expires_at','recovery_token_sent_at','recovery_token_attempts','password_reset_attempts','password_reset_locked_until','pin_changed_at','created_at','updated_at'] as $k){
            if(array_key_exists($k,$r) && $r[$k]!==null)$x[$k]=in_array($k,['id','email_verification_attempts','recovery_token_attempts','password_reset_attempts'],true)?(int)$r[$k]:$r[$k];
        }
        $x['device_tokens']=[];
        foreach((new Query())->from('{{%user_device}}')->where(['user_id'=>$r['id']])->orderBy('id')->all() as $d){
            $x['device_tokens'][]=['id'=>$d['public_id'],'hash'=>$d['token_hash'],'created_at'=>$d['created_at'],'last_used_at'=>$d['last_used_at'],'device_name'=>$d['device_name'],'os'=>$d['os'],'browser'=>$d['browser'],'user_agent'=>$d['user_agent'],'last_ip'=>$d['last_ip']];
        }
        return $x;
    }

    public static function readAll(): array {
        $users=[];$max=0;
        foreach((new Query())->from('{{%user}}')->orderBy('id')->all() as $r){$u=self::hydrate($r);$users[]=$u;$max=max($max,(int)$u['id']);}
        return ['users'=>$users,'meta'=>['next_user_id'=>$max+1]];
    }

    public static function mutateUserCompat(int $userId, callable $fn){
        $tx=self::db()->beginTransaction();
        try{
            $u=self::findById($userId);
            if(!$u) throw new \RuntimeException('Akun tidak ditemukan.');
            $data=['users'=>[$u],'meta'=>['next_user_id'=>$userId+1]];
            $result=$fn($data);
            if(!empty($data['users'][0])) self::saveUser($data['users'][0]);
            else self::db()->createCommand()->delete('{{%user}}',['id'=>$userId])->execute();
            $tx->commit(); return $result;
        }catch(\Throwable $e){$tx->rollBack();throw $e;}
    }

    public static function mutateAll(callable $fn){
        $tx=self::db()->beginTransaction();
        try{$before=self::readAll();$after=$before;$result=$fn($after);self::sync($before,$after);$tx->commit();return $result;}catch(\Throwable $e){$tx->rollBack();throw $e;}
    }

    public static function saveUser(array $u): void {
        $known=['id','username','email','password_hash','pin_hash','role','plan','plan_expires_at','premium_type','premium_last_invoice','email_verified_at','email_verification_token_hash','email_verification_expires_at','email_verification_sent_at','email_verification_attempts','recovery_token_hash','recovery_token_expires_at','recovery_token_sent_at','recovery_token_attempts','password_reset_attempts','password_reset_locked_until','pin_changed_at','created_at','updated_at','device_tokens'];
        $extra=array_diff_key($u,array_flip($known));
        $now=date('Y-m-d H:i:s');
        $row=[
            'id'=>(int)$u['id'],'username'=>(string)$u['username'],'email'=>($u['email']??'')!==''?strtolower((string)$u['email']):null,
            'password_hash'=>(string)($u['password_hash']??''),'pin_hash'=>($u['pin_hash']??'')?:null,'role'=>$u['role']??'user','plan'=>$u['plan']??'free',
            'plan_expires_at'=>($u['plan_expires_at']??'')?:null,'premium_type'=>($u['premium_type']??'')?:null,'premium_last_invoice'=>($u['premium_last_invoice']??'')?:null,
            'email_verified_at'=>($u['email_verified_at']??'')?:null,'email_verification_token_hash'=>($u['email_verification_token_hash']??'')?:null,
            'email_verification_expires_at'=>($u['email_verification_expires_at']??'')?:null,'email_verification_sent_at'=>($u['email_verification_sent_at']??'')?:null,
            'email_verification_attempts'=>(int)($u['email_verification_attempts']??0),'recovery_token_hash'=>($u['recovery_token_hash']??'')?:null,
            'recovery_token_expires_at'=>($u['recovery_token_expires_at']??'')?:null,'recovery_token_sent_at'=>($u['recovery_token_sent_at']??'')?:null,
            'recovery_token_attempts'=>(int)($u['recovery_token_attempts']??0),'password_reset_attempts'=>(int)($u['password_reset_attempts']??0),
            'password_reset_locked_until'=>($u['password_reset_locked_until']??'')?:null,'pin_changed_at'=>($u['pin_changed_at']??'')?:null,
            'created_at'=>$u['created_at']??$now,'updated_at'=>$now,'extra_json'=>$extra?self::enc($extra):null,
        ];
        self::db()->createCommand()->upsert('{{%user}}',$row,array_diff_key($row,['id'=>1,'created_at'=>1]))->execute();
        self::db()->createCommand()->delete('{{%user_device}}',['user_id'=>(int)$u['id']])->execute();
        foreach((array)($u['device_tokens']??[]) as $d){if(empty($d['hash']))continue;self::db()->createCommand()->insert('{{%user_device}}',[
            'user_id'=>(int)$u['id'],'public_id'=>$d['id']??('legacy_'.substr((string)$d['hash'],0,20)),'token_hash'=>(string)$d['hash'],'device_name'=>$d['device_name']??null,
            'os'=>$d['os']??null,'browser'=>$d['browser']??null,'user_agent'=>$d['user_agent']??null,'last_ip'=>$d['last_ip']??null,
            'created_at'=>$d['created_at']??$now,'last_used_at'=>$d['last_used_at']??($d['created_at']??$now),
        ])->execute();}
    }

    private static function sync(array $before,array $after): void {
        $afterIds=[];foreach((array)($after['users']??[]) as $u){$afterIds[]=(int)$u['id'];self::saveUser($u);} 
        $beforeIds=array_map(fn($u)=>(int)$u['id'],(array)($before['users']??[]));
        $deleted=array_diff($beforeIds,$afterIds);if($deleted)self::db()->createCommand()->delete('{{%user}}',['id'=>$deleted])->execute();
    }
}
