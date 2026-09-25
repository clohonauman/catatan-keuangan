<?php
require_once __DIR__.'/../auth.php'; authRequireUnlocked();
if($_SERVER['REQUEST_METHOD']==='GET') authRequirePremiumDownload('Backup data tersedia untuk akun Premium.');
else authRequirePremiumJson('Restore backup tersedia untuk akun Premium.');
require_once __DIR__.'/../db.php'; require_once __DIR__.'/../finance_features.php'; require_once __DIR__.'/../adaptive_learning_helper.php';

function backupReferencedPhotoNames($d){
    $names=[];
    foreach(['transactions','chats'] as $bucket)foreach((array)($d[$bucket]??[]) as $row){$f=basename((string)($row['attachment']['file']??''));if($f!=='')$names[$f]=true;}
    return array_keys($names);
}

if($_SERVER['REQUEST_METHOD']==='GET'){
    $user=authCurrentUser();$d=financeReadData();$photos=[];$photoBytes=0;$skipped=[];$dir=DATA_DIR.'/receipts/user_'.(int)$user['id'];
    foreach(backupReferencedPhotoNames($d) as $name){
        $path=$dir.'/'.$name;if(!is_file($path))continue;$size=(int)@filesize($path);
        // Batasi satu backup agar tetap realistis di shared hosting.
        if($size<=0||$size>8*1024*1024||$photoBytes+$size>30*1024*1024){$skipped[]=$name;continue;}
        $raw=@file_get_contents($path);if($raw===false){$skipped[]=$name;continue;}
        $photos[$name]=base64_encode($raw);$photoBytes+=$size;
    }
    $payload=['format'=>'pencatatan-keuangan-backup','version'=>4,'username'=>$user['username']??'user','exported_at'=>date('c'),'data'=>$d,'assistant_learning'=>adaptiveExportCurrentUser(),'photos'=>$photos,'photos_skipped'=>$skipped];
    $json=json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);$safe=preg_replace('/[^A-Za-z0-9_.-]/','-',($user['username']??'user'));
    header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="backup-'.$safe.'-'.date('Ymd-His').'.json"');header('Content-Length: '.strlen($json));header('Cache-Control: private, no-store, max-age=0');echo $json;exit;
}

header('Content-Type: application/json; charset=utf-8');
try{
    if(!isset($_FILES['backup'])||$_FILES['backup']['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('Pilih file backup JSON.');
    if($_FILES['backup']['size']>45*1024*1024)throw new InvalidArgumentException('File backup terlalu besar. Maksimal 45 MB.');
    $raw=file_get_contents($_FILES['backup']['tmp_name']);$payload=json_decode((string)$raw,true);if(!is_array($payload)||($payload['format']??'')!=='pencatatan-keuangan-backup'||!isset($payload['data'])||!is_array($payload['data']))throw new InvalidArgumentException('Format backup tidak valid.');
    $incoming=$payload['data'];financeEnsureFeatureData($incoming);
    mutateData(function(&$d)use($incoming){$d=$incoming;financeEnsureFeatureData($d);return true;});
    // Backup v4 ikut membawa learning pribadi. Backup v1-v3 tetap kompatibel
    // dan tidak menghapus learning yang sudah ada saat field ini tidak tersedia.
    if(isset($payload['assistant_learning']) && is_array($payload['assistant_learning'])) adaptiveImportCurrentUser($payload['assistant_learning'],true);

    $restoredPhotos=0;$user=authCurrentUser();$dir=DATA_DIR.'/receipts/user_'.(int)$user['id'];if(!is_dir($dir))@mkdir($dir,0775,true);
    foreach((array)($payload['photos']??[]) as $name=>$b64){$name=basename((string)$name);if(!preg_match('/^[A-Za-z0-9_.-]+$/',$name))continue;$bytes=base64_decode((string)$b64,true);if($bytes===false||strlen($bytes)>8*1024*1024)continue;if(@file_put_contents($dir.'/'.$name,$bytes,LOCK_EX)!==false){@chmod($dir.'/'.$name,0644);$restoredPhotos++;}}
    echo json_encode(['ok'=>true,'message'=>'Backup berhasil dipulihkan.','photos_restored'=>$restoredPhotos,'summary'=>summary()],JSON_UNESCAPED_UNICODE);
}catch(InvalidArgumentException $e){http_response_code(422);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Gagal memulihkan backup.'],JSON_UNESCAPED_UNICODE);}
