<?php
namespace app\services;

use ZipArchive;
use PharData;
use Phar;
use RuntimeException;

final class LegacyBackupService
{
    private const MAX_FILES = 25000;
    private const MAX_TOTAL_BYTES = 1073741824; // 1 GiB extracted
    private const MAX_SINGLE_FILE = 134217728; // 128 MiB

    public static function create(string $dataDir, string $targetZip): array
    {
        $dataDir=rtrim($dataDir,'/\\');
        if(!is_dir($dataDir)) throw new RuntimeException('Folder data JSON legacy tidak ditemukan: '.$dataDir);
        if(!class_exists(ZipArchive::class) && !class_exists(PharData::class)) throw new RuntimeException('Dukungan ZIP tidak tersedia (ZipArchive/PharData).');
        @unlink($targetZip);

        $files=[];$bytes=0;
        $usingZip=class_exists(ZipArchive::class);
        if($usingZip){
            $archive=new ZipArchive();
            if($archive->open($targetZip,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Tidak dapat membuat file backup ZIP.');
        }else{
            try{$archive=new PharData($targetZip,0,null,Phar::ZIP);}catch(\Throwable $e){throw new RuntimeException('Tidak dapat membuat file backup ZIP: '.$e->getMessage(),0,$e);}
        }

        $add=function(string $path,string $rel)use($archive,$usingZip,&$files,&$bytes){
            if(!is_file($path))return;
            $rel=str_replace('\\','/',$rel);
            if(str_contains($rel,'..')||str_starts_with($rel,'/')) throw new RuntimeException('Path sumber backup tidak aman: '.$rel);
            $raw=null;
            if(strtolower(pathinfo($path,PATHINFO_EXTENSION))==='json'){
                $raw=@file_get_contents($path);
                if($raw===false) throw new RuntimeException('Gagal membaca file backup: '.$rel);
                if(json_decode($raw,true)===null && trim($raw)!=='null') throw new RuntimeException('JSON legacy tidak valid: '.$rel);
                if($usingZip){if(!$archive->addFromString('data/'.$rel,$raw))throw new RuntimeException('Gagal menambahkan file backup: '.$rel);}else{$archive->addFromString('data/'.$rel,$raw);}
                $size=strlen($raw);$sha=hash('sha256',$raw);
            }else{
                $size=(int)@filesize($path);
                if($size<0)$size=0;
                if($size>self::MAX_SINGLE_FILE) throw new RuntimeException('Satu file sumber backup terlalu besar: '.$rel);
                $sha=@hash_file('sha256',$path);if($sha===false)throw new RuntimeException('Gagal menghitung checksum: '.$rel);
                if($usingZip){if(!$archive->addFile($path,'data/'.$rel))throw new RuntimeException('Gagal menambahkan file backup: '.$rel);}else{$archive->addFile($path,'data/'.$rel);}
            }
            $bytes+=$size;
            if($bytes>self::MAX_TOTAL_BYTES) throw new RuntimeException('Total data backup melebihi batas aman 1 GiB.');
            $files[]=['path'=>'data/'.$rel,'size'=>$size,'sha256'=>$sha];
        };

        try{
            // Backup semua JSON di root data agar format tetap lengkap jika versi legacy memiliki dokumen tambahan.
            foreach(glob($dataDir.'/*.json')?:[] as $jsonFile)$add($jsonFile,basename($jsonFile));
            foreach(['user_finance','receipts','payment_proofs'] as $folder){
                $root=$dataDir.'/'.$folder;if(!is_dir($root))continue;
                $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS));
                foreach($it as $f){if(!$f->isFile()||str_starts_with($f->getFilename(),'.'))continue;$rel=$folder.'/'.substr($f->getPathname(),strlen($root)+1);$add($f->getPathname(),$rel);}
            }
            if(count($files)>self::MAX_FILES) throw new RuntimeException('Jumlah file backup melebihi batas aman.');
            $manifest=['format'=>'catatan-keuangan-legacy-full-backup','version'=>1,'created_at'=>date(DATE_ATOM),'source'=>['application'=>'Catatan Keuangan Yii2','purpose'=>'legacy_full_backup'],'files'=>$files,'total_files'=>count($files),'total_bytes'=>$bytes];
            $manifestJson=json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if($usingZip){if(!$archive->addFromString('manifest.json',$manifestJson))throw new RuntimeException('Gagal menulis manifest backup.');}else{$archive->addFromString('manifest.json',$manifestJson);}
        }catch(\Throwable $e){
            if($usingZip){@$archive->close();}else{unset($archive);}
            @unlink($targetZip);throw $e;
        }

        if($usingZip){if(!$archive->close())throw new RuntimeException('Gagal menyelesaikan backup ZIP.');}else{unset($archive);clearstatcache(true,$targetZip);}
        return $manifest;
    }

    public static function validateZip(string $zipPath): array
    {
        if(!is_file($zipPath)) throw new RuntimeException('Backup ZIP tidak ditemukan.');
        if(class_exists(ZipArchive::class)) return self::validateWithZipArchive($zipPath);
        if(class_exists(PharData::class)) return self::validateWithPharData($zipPath);
        throw new RuntimeException('Dukungan pembacaan ZIP tidak tersedia (ZipArchive/PharData).');
    }

    private static function validateManifest(array $manifest, callable $stat, callable $hash): array
    {
        if(($manifest['format']??'')!=='catatan-keuangan-legacy-full-backup') throw new RuntimeException('Format backup penuh tidak dikenali.');
        if((int)($manifest['version']??0)!==1) throw new RuntimeException('Versi backup belum didukung.');
        $files=$manifest['files']??[];
        if(!is_array($files)||!$files) throw new RuntimeException('Daftar file backup kosong.');
        if(count($files)>self::MAX_FILES) throw new RuntimeException('Backup memiliki terlalu banyak file.');
        if((int)($manifest['total_files']??count($files))!==count($files)) throw new RuntimeException('Jumlah file pada manifest tidak konsisten.');
        if((int)($manifest['total_bytes']??0)>self::MAX_TOTAL_BYTES) throw new RuntimeException('Ukuran data backup setelah ekstrak terlalu besar.');

        $declared=[];$sum=0;
        foreach($files as $f){
            $name=(string)($f['path']??'');
            if($name===''||str_contains($name,'..')||str_starts_with($name,'/')||!str_starts_with($name,'data/')) throw new RuntimeException('Path backup tidak aman: '.$name);
            if(isset($declared[$name])) throw new RuntimeException('File duplikat pada manifest: '.$name);
            $declared[$name]=true;
            $size=$stat($name);
            if($size===null) throw new RuntimeException('File backup hilang: '.$name);
            $size=(int)$size;$declaredSize=(int)($f['size']??$size);
            if($size!==$declaredSize) throw new RuntimeException('Ukuran file backup tidak cocok: '.$name);
            if($size>self::MAX_SINGLE_FILE) throw new RuntimeException('Satu file backup terlalu besar: '.$name);
            $sum+=$size;if($sum>self::MAX_TOTAL_BYTES) throw new RuntimeException('Ukuran data backup setelah ekstrak terlalu besar.');
            $expected=(string)($f['sha256']??'');
            if($expected!==''&&!hash_equals($expected,$hash($name))) throw new RuntimeException('Checksum backup tidak cocok: '.$name);
        }
        if(!isset($declared['data/users.json'])) throw new RuntimeException('users.json tidak ada pada backup.');
        if(isset($manifest['total_bytes'])&&(int)$manifest['total_bytes']!==$sum) throw new RuntimeException('Total ukuran data pada manifest tidak konsisten.');
        return $manifest;
    }

    private static function validateWithZipArchive(string $zipPath): array
    {
        $zip=new ZipArchive();if($zip->open($zipPath)!==true)throw new RuntimeException('Backup ZIP tidak dapat dibuka.');
        try{
            $manifestRaw=$zip->getFromName('manifest.json');if($manifestRaw===false)throw new RuntimeException('manifest.json tidak ditemukan pada backup.');
            $manifest=json_decode($manifestRaw,true);if(!is_array($manifest))throw new RuntimeException('manifest.json tidak valid.');
            return self::validateManifest($manifest,
                function(string $name)use($zip){$s=$zip->statName($name);return $s===false?null:(int)($s['size']??0);},
                function(string $name)use($zip){$stream=$zip->getStream($name);if(!$stream)throw new RuntimeException('File backup tidak dapat dibaca: '.$name);$ctx=hash_init('sha256');hash_update_stream($ctx,$stream);fclose($stream);return hash_final($ctx);}
            );
        }finally{$zip->close();}
    }

    private static function validateWithPharData(string $zipPath): array
    {
        try{$phar=new PharData($zipPath);}catch(\Throwable $e){throw new RuntimeException('Backup ZIP tidak dapat dibuka: '.$e->getMessage(),0,$e);}
        if(!isset($phar['manifest.json']))throw new RuntimeException('manifest.json tidak ditemukan pada backup.');
        $manifest=json_decode($phar['manifest.json']->getContent(),true);if(!is_array($manifest))throw new RuntimeException('manifest.json tidak valid.');
        $real=realpath($zipPath)?:$zipPath;
        return self::validateManifest($manifest,
            function(string $name)use($phar){return isset($phar[$name])?(int)$phar[$name]->getSize():null;},
            function(string $name)use($real){$h=@hash_file('sha256','phar://'.$real.'/'.$name);if($h===false)throw new RuntimeException('File backup tidak dapat dibaca: '.$name);return $h;}
        );
    }

    public static function extractSafe(string $zipPath,string $targetDir): array
    {
        $manifest=self::validateZip($zipPath);
        if(!is_dir($targetDir)&&!mkdir($targetDir,0700,true)&&!is_dir($targetDir)) throw new RuntimeException('Folder sementara tidak dapat dibuat.');
        if(class_exists(ZipArchive::class)) self::extractWithZipArchive($zipPath,$targetDir,$manifest);
        elseif(class_exists(PharData::class)) self::extractWithPharData($zipPath,$targetDir,$manifest);
        else throw new RuntimeException('Dukungan ekstraksi ZIP tidak tersedia.');
        return $manifest;
    }

    private static function safeDestination(string $targetDir,string $name): string
    {
        // Name has already passed validateManifest; this method only centralizes directory creation.
        $dest=rtrim($targetDir,'/\\').'/'.$name;$dir=dirname($dest);
        if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir)) throw new RuntimeException('Folder ekstraksi tidak dapat dibuat.');
        return $dest;
    }

    private static function extractWithZipArchive(string $zipPath,string $targetDir,array $manifest): void
    {
        $zip=new ZipArchive();if($zip->open($zipPath)!==true)throw new RuntimeException('Backup ZIP tidak dapat dibuka untuk ekstraksi.');
        try{
            foreach((array)$manifest['files'] as $entry){$name=(string)$entry['path'];$dest=self::safeDestination($targetDir,$name);$in=$zip->getStream($name);if(!$in)throw new RuntimeException('File backup tidak dapat diekstrak: '.$name);$out=fopen($dest,'wb');if(!$out){fclose($in);throw new RuntimeException('File sementara tidak dapat dibuat.');}stream_copy_to_stream($in,$out);fclose($out);fclose($in);@chmod($dest,0600);}
        }finally{$zip->close();}
    }

    private static function extractWithPharData(string $zipPath,string $targetDir,array $manifest): void
    {
        $real=realpath($zipPath)?:$zipPath;
        foreach((array)$manifest['files'] as $entry){$name=(string)$entry['path'];$dest=self::safeDestination($targetDir,$name);$in=@fopen('phar://'.$real.'/'.$name,'rb');if(!$in)throw new RuntimeException('File backup tidak dapat diekstrak: '.$name);$out=fopen($dest,'wb');if(!$out){fclose($in);throw new RuntimeException('File sementara tidak dapat dibuat.');}stream_copy_to_stream($in,$out);fclose($out);fclose($in);@chmod($dest,0600);}
    }
}
