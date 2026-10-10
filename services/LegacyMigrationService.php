<?php
namespace app\services;

use Yii;
use app\repositories\AuthRepository;
use app\repositories\FinanceRepository;
use app\repositories\SubscriptionRepository;
use app\repositories\DocumentRepository;
use RuntimeException;

final class LegacyMigrationService
{
    /**
     * Menjamin tabel pendukung migrasi tersedia pada instalasi yang pernah
     * menjalankan migration v1 lama (sebelum app_document/migration_history
     * ditambahkan ke file migration pertama).
     *
     * Aman dipanggil berulang kali karena memakai CREATE TABLE IF NOT EXISTS.
     */
    public static function ensureInfrastructure(): void
    {
        $db = Yii::$app->db;
        $db->createCommand(<<<'SQL'
CREATE TABLE IF NOT EXISTS `app_document` (
  `namespace` varchar(80) NOT NULL,
  `document_key` varchar(120) NOT NULL,
  `payload_json` mediumtext NOT NULL,
  `revision` bigint unsigned NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`namespace`,`document_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        )->execute();

        $db->createCommand(<<<'SQL'
CREATE TABLE IF NOT EXISTS `migration_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source_name` varchar(190) NOT NULL,
  `source_sha256` varchar(64) DEFAULT NULL,
  `mode` varchar(30) NOT NULL,
  `status` varchar(30) NOT NULL,
  `summary_json` mediumtext DEFAULT NULL,
  `created_by_user_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_migration_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        )->execute();
        $db->schema->refresh();
    }
    private static function readJson(string $file,$default=[]){if(!is_file($file))return $default;$raw=file_get_contents($file);$d=json_decode((string)$raw,true);if(json_last_error()!==JSON_ERROR_NONE)throw new RuntimeException('JSON rusak: '.basename($file).' - '.json_last_error_msg());return $d;}
    private static function rrmdir(string $dir):void{if(!is_dir($dir))return;$it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}@rmdir($dir);}

    public static function importZip(string $zipPath,int $adminUserId=0,bool $replaceAll=true): array
    {
        $tmp=Yii::getAlias('@runtime/legacy-import-'.bin2hex(random_bytes(6)));$manifest=LegacyBackupService::extractSafe($zipPath,$tmp);
        try{return self::importDirectory($tmp.'/data',$adminUserId,$replaceAll,basename($zipPath),hash_file('sha256',$zipPath),$manifest);}finally{self::rrmdir($tmp);}
    }

    public static function importDirectory(string $dataDir,int $adminUserId=0,bool $replaceAll=true,string $source='legacy-data',string $sha='',array $manifest=[]): array
    {
        self::ensureInfrastructure();
        if(!is_dir($dataDir))throw new RuntimeException('Folder data legacy tidak ditemukan.');
        $usersPayload=self::readJson($dataDir.'/users.json',['users'=>[]]);
        $users=(array)($usersPayload['users']??[]);if(!$users)throw new RuntimeException('Backup tidak memiliki akun pada users.json.');
        $activeUserIds=[];
        foreach($users as $i=>$u){
            if(empty($u['id'])||trim((string)($u['username']??''))===''||trim((string)($u['password_hash']??''))==='') throw new RuntimeException('users.json tidak lengkap pada item #'.($i+1).'. Restore dibatalkan sebelum database diubah.');
            $activeUserIds[(int)$u['id']]=true;
        }
        // Validate finance JSON before changing database.
        $financeFiles=glob($dataDir.'/user_finance/user_*.json')?:[];$financeByUser=[];
        foreach($financeFiles as $f){if(!preg_match('/user_(\d+)\.json$/',basename($f),$m))continue;$financeByUser[(int)$m[1]]=self::readJson($f,[]);}
        $subscriptions=self::readJson($dataDir.'/subscriptions.json',[]);
        $orphanSubscriptionOrders=[];
        if(!empty($subscriptions['orders'])&&is_array($subscriptions['orders'])){
            $validOrders=[];
            foreach($subscriptions['orders'] as $order){$uid=(int)($order['user_id']??0);if($uid>0&&isset($activeUserIds[$uid]))$validOrders[]=$order;else $orphanSubscriptionOrders[]=$order;}
            $subscriptions['orders']=$validOrders;
        }
        $docs=[
            ['assistant','learning',$dataDir.'/assistant_learning.json',['rules'=>[],'meta'=>['next_id'=>1]]],
            ['admin','notifications',$dataDir.'/admin_notifications.json',['notifications'=>[],'meta'=>['next_id'=>1]]],
            ['email','delivery_log',$dataDir.'/email_delivery_log.json',['emails'=>[]]],
            ['privacy','requests',$dataDir.'/privacy_requests.json',[]],
            ['email','notifications',$dataDir.'/user_email_notifications.json',['campaigns'=>[],'automatic'=>[],'meta'=>['next_campaign_id'=>1,'next_auto_id'=>1]]],
        ];
        foreach($docs as &$d)$d[3]=self::readJson($d[2],$d[3]);unset($d);
        // Arsipkan finance user yang sudah tidak memiliki akun aktif agar tidak ada JSON legacy yang hilang.
        $orphanFinance=[];
        foreach($financeByUser as $uid=>$payload){if(!isset($activeUserIds[(int)$uid]))$orphanFinance[(string)$uid]=$payload;}
        // Simpan dokumen JSON root yang tidak memiliki tabel khusus sebagai arsip MySQL.
        $knownRoot=['users.json','subscriptions.json','assistant_learning.json','admin_notifications.json','email_delivery_log.json','privacy_requests.json','user_email_notifications.json'];
        $extraRootDocs=[];
        foreach(glob($dataDir.'/*.json')?:[] as $jf){$bn=basename($jf);if(in_array($bn,$knownRoot,true))continue;$extraRootDocs[$bn]=self::readJson($jf,[]);}

        $historyId=Yii::$app->db->createCommand()->insert('{{%migration_history}}',['source_name'=>$source,'source_sha256'=>$sha?:null,'mode'=>$replaceAll?'replace_all':'merge','status'=>'running','summary_json'=>null,'created_by_user_id'=>$adminUserId?:null,'created_at'=>date('Y-m-d H:i:s'),'completed_at'=>null])->execute();
        $historyId=(int)Yii::$app->db->getLastInsertID();
        try{
            // Satu transaksi MySQL untuk seluruh perubahan data relasional. Jika satu tabel gagal, semuanya rollback.
            $tx=Yii::$app->db->beginTransaction();
            try{
                if($replaceAll) self::purgeDatabase();
                foreach($users as $u){
                    if(empty($u['id'])||empty($u['username'])||empty($u['password_hash']))continue;
                    AuthRepository::saveUser($u);
                }
                foreach($users as $u){$uid=(int)($u['id']??0);if($uid<=0)continue;FinanceRepository::ensureUser($uid);if(isset($financeByUser[$uid]))FinanceRepository::replace($uid,$financeByUser[$uid]);}
                if($subscriptions){
                    require_once Yii::getAlias('@app/legacy/subscription_helper.php');
                    $normalized=subscriptionNormalizeData($subscriptions);SubscriptionRepository::replace($normalized);
                }
                foreach($docs as $d)DocumentRepository::put($d[0],$d[1],is_array($d[3])?$d[3]:[]);
                if($orphanFinance) DocumentRepository::put('legacy_archive','orphan_finance',$orphanFinance);
                if($orphanSubscriptionOrders) DocumentRepository::put('legacy_archive','orphan_subscription_orders',$orphanSubscriptionOrders);
                foreach($extraRootDocs as $name=>$payload) DocumentRepository::put('legacy_archive','root_'.preg_replace('/[^a-zA-Z0-9_.-]+/','_',$name),is_array($payload)?$payload:[]);
                $tx->commit();
            }catch(\Throwable $e){if($tx->getIsActive())$tx->rollBack();throw $e;}

            // Lampiran disalin setelah data relasional commit. Proses copy sekarang
            // diverifikasi byte/hash-nya agar restore tidak dianggap berhasil bila
            // foto nota atau bukti pembayaran gagal masuk ke private storage.
            $copied=self::copyPrivateFiles($dataDir);
            $attachmentCheck=self::verifyPrivateAttachments($financeByUser,$activeUserIds,$subscriptions);
            $summary=self::verify($users,$financeByUser,$subscriptions,$copied,$manifest,array_keys($orphanFinance),array_keys($extraRootDocs),count($orphanSubscriptionOrders),$attachmentCheck);
            Yii::$app->db->createCommand()->update('{{%migration_history}}',['status'=>'completed','summary_json'=>json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'completed_at'=>date('Y-m-d H:i:s')],['id'=>$historyId])->execute();
            return $summary;
        }catch(\Throwable $e){Yii::$app->db->createCommand()->update('{{%migration_history}}',['status'=>'failed','summary_json'=>json_encode(['error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE),'completed_at'=>date('Y-m-d H:i:s')],['id'=>$historyId])->execute();throw $e;}
    }

    private static function purgeDatabase(): void
    {
        $db=Yii::$app->db;
        foreach(['subscription_order','coupon','payment_bank','subscription_plan','app_document'] as $t)$db->createCommand()->delete('{{%'.$t.'}}')->execute();
        $db->createCommand()->delete('{{%user}}')->execute(); // cascades user-owned tables
    }

    private static function copyPrivateFiles(string $dataDir): array
    {
        $storage=rtrim((string)Yii::$app->params['privateStorage'],'/\\');
        if(!is_dir($storage)&&!mkdir($storage,0770,true)&&!is_dir($storage)){
            throw new RuntimeException('Private storage tidak dapat dibuat: '.$storage);
        }
        if(!is_writable($storage)){
            throw new RuntimeException('Private storage tidak writable: '.$storage);
        }

        $result=[
            'receipts'=>0,
            'payment_proofs'=>0,
            'expected_receipts'=>0,
            'expected_payment_proofs'=>0,
            'failed'=>[],
        ];

        foreach(['receipts','payment_proofs'] as $folder){
            $src=$dataDir.'/'.$folder;
            if(!is_dir($src)) continue;
            $dst=$storage.'/'.$folder;
            if(!is_dir($dst)&&!mkdir($dst,0770,true)&&!is_dir($dst)){
                throw new RuntimeException('Folder private storage tidak dapat dibuat: '.$folder);
            }

            $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src,\FilesystemIterator::SKIP_DOTS));
            foreach($it as $f){
                if(!$f->isFile()||str_starts_with($f->getFilename(),'.')) continue;
                $rel=substr($f->getPathname(),strlen($src)+1);
                if($rel===''||str_contains($rel,'..')) continue;

                if($folder==='receipts') $result['expected_receipts']++;
                else $result['expected_payment_proofs']++;

                $to=$dst.'/'.$rel;
                if(!is_dir(dirname($to))&&!mkdir(dirname($to),0770,true)&&!is_dir(dirname($to))){
                    $result['failed'][]=$folder.'/'.$rel.' (folder tujuan gagal dibuat)';
                    continue;
                }
                $ok=@copy($f->getPathname(),$to);
                if(!$ok||!is_file($to)||filesize($to)!==$f->getSize()){
                    $result['failed'][]=$folder.'/'.$rel.' (copy/ukuran gagal)';
                    @unlink($to);
                    continue;
                }
                $srcHash=@hash_file('sha256',$f->getPathname());
                $dstHash=@hash_file('sha256',$to);
                if($srcHash===false||$dstHash===false||!hash_equals($srcHash,$dstHash)){
                    $result['failed'][]=$folder.'/'.$rel.' (checksum gagal)';
                    @unlink($to);
                    continue;
                }
                @chmod($to,0640);
                if($folder==='receipts') $result['receipts']++;
                else $result['payment_proofs']++;
            }
        }

        if($result['failed']){
            $examples=array_slice($result['failed'],0,5);
            throw new RuntimeException(
                'Restore data MySQL berhasil, tetapi '.count($result['failed']).' file lampiran gagal disalin ke private storage. '.
                'Contoh: '.implode('; ',$examples).'. Periksa permission storage/private lalu jalankan restore kembali.'
            );
        }
        return $result;
    }

    private static function verifyPrivateAttachments(array $financeByUser,array $activeUserIds,array $subscriptions): array
    {
        $storage=rtrim((string)Yii::$app->params['privateStorage'],'/\\');
        $receiptRefs=[];
        $missingReceipts=[];
        foreach($financeByUser as $uid=>$data){
            $uid=(int)$uid;
            if(!isset($activeUserIds[$uid])) continue;
            foreach(['transactions','chats'] as $key){
                foreach((array)($data[$key]??[]) as $row){
                    $file=basename((string)($row['attachment']['file']??''));
                    if($file==='') continue;
                    $ref=$uid.'/'.$file;
                    $receiptRefs[$ref]=true;
                }
            }
        }
        foreach(array_keys($receiptRefs) as $ref){
            [$uid,$file]=explode('/',$ref,2);
            $path=$storage.'/receipts/user_'.(int)$uid.'/'.$file;
            if(!is_file($path)||!is_readable($path)) $missingReceipts[]=$ref;
        }

        $proofRefs=[];$missingProofs=[];
        foreach((array)($subscriptions['orders']??[]) as $order){
            $file=basename((string)($order['proof']['file']??''));
            if($file!=='') $proofRefs[$file]=true;
        }
        foreach(array_keys($proofRefs) as $file){
            $path=$storage.'/payment_proofs/'.$file;
            if(!is_file($path)||!is_readable($path)) $missingProofs[]=$file;
        }

        return [
            'receipt_references'=>count($receiptRefs),
            'receipt_missing'=>count($missingReceipts),
            'receipt_missing_examples'=>array_slice($missingReceipts,0,10),
            'payment_proof_references'=>count($proofRefs),
            'payment_proof_missing'=>count($missingProofs),
            'payment_proof_missing_examples'=>array_slice($missingProofs,0,10),
        ];
    }

    private static function verify(array $users,array $finance,array $subscriptions,array $copied,array $manifest,array $orphanFinanceIds=[],array $extraRootDocs=[],int $orphanOrderCount=0,array $attachmentCheck=[]): array
    {
        $db=Yii::$app->db;$dbUsers=(int)(new \yii\db\Query())->from('{{%user}}')->count('*',$db);$tx=(int)(new \yii\db\Query())->from('{{%finance_transaction}}')->count('*',$db);$wallets=(int)(new \yii\db\Query())->from('{{%wallet}}')->count('*',$db);$chats=(int)(new \yii\db\Query())->from('{{%chat_message}}')->count('*',$db);
        $activeIds=[];foreach($users as $u){if(!empty($u['id']))$activeIds[(int)$u['id']]=true;}
        $expectedTx=0;$expectedWallets=0;$expectedChats=0;foreach($finance as $uid=>$d){if(!isset($activeIds[(int)$uid]))continue;$expectedTx+=count($d['transactions']??[]);$expectedWallets+=count($d['wallets']??[]);$expectedChats+=count($d['chats']??[]);}
        $warnings=[];if($dbUsers<count($users))$warnings[]='Jumlah user MySQL lebih sedikit dari backup.';if($tx!==$expectedTx)$warnings[]="Transaksi aktif: backup $expectedTx, MySQL $tx.";if($wallets!==$expectedWallets)$warnings[]="Wallet aktif: backup $expectedWallets, MySQL $wallets.";if($chats!==$expectedChats)$warnings[]="Chat aktif: backup $expectedChats, MySQL $chats.";
        if($orphanFinanceIds)$warnings[]='Ditemukan data keuangan tanpa akun aktif (user_id: '.implode(', ',$orphanFinanceIds).'). Data tidak dibuang dan diarsipkan di app_document namespace legacy_archive.';if($orphanOrderCount>0)$warnings[]=$orphanOrderCount.' order langganan tanpa akun aktif diarsipkan di app_document namespace legacy_archive.';
        if((int)($attachmentCheck['receipt_missing']??0)>0)$warnings[]='Ada '.(int)$attachmentCheck['receipt_missing'].' foto transaksi/chat yang direferensikan data tetapi tidak ditemukan di private storage.';
        if((int)($attachmentCheck['payment_proof_missing']??0)>0)$warnings[]='Ada '.(int)$attachmentCheck['payment_proof_missing'].' bukti pembayaran yang direferensikan data tetapi tidak ditemukan di private storage.';
        return ['ok'=>!array_filter($warnings,fn($w)=>!str_starts_with($w,'Ditemukan data keuangan tanpa akun aktif')),'backup_created_at'=>(string)($manifest['created_at']??''),'backup_source'=>(array)($manifest['source']??[]),'backup_stats'=>(array)($manifest['stats']??[]),'users_imported'=>$dbUsers,'finance_users_active'=>count($activeIds),'finance_users_archived'=>count($orphanFinanceIds),'transactions_imported'=>$tx,'wallets_imported'=>$wallets,'chats_imported'=>$chats,'subscription_orders'=>count($subscriptions['orders']??[]),'subscription_orders_archived'=>$orphanOrderCount,'files_copied'=>$copied,'attachment_check'=>$attachmentCheck,'extra_root_json_archived'=>$extraRootDocs,'manifest_files'=>(int)($manifest['total_files']??0),'warnings'=>$warnings,'completed_at'=>date(DATE_ATOM)];
    }
}
