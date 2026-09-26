<?php
namespace app\services;

use RuntimeException;
use ZipArchive;
use PharData;
use Phar;

final class AppDataBackupService
{
    private const FORMAT = 'catatan-keuangan-app-data-backup';
    private const VERSION = 1;
    private const MAX_FILES = 30000;
    private const MAX_TOTAL_BYTES = 1073741824; // 1 GiB
    private const MAX_SINGLE_FILE = 134217728; // 128 MiB

    public static function create(string $sourceDir, string $targetZip): array
    {
        $sourceDir = rtrim($sourceDir, '/\\');
        if (!is_dir($sourceDir)) {
            if (!@mkdir($sourceDir, 0750, true) && !is_dir($sourceDir)) {
                throw new RuntimeException('Folder data aplikasi tidak dapat dibuat.');
            }
        }
        if (!class_exists(ZipArchive::class) && !class_exists(PharData::class)) {
            throw new RuntimeException('Server tidak memiliki dukungan ZIP (ZipArchive/PharData).');
        }
        @unlink($targetZip);
        $usingZip = class_exists(ZipArchive::class);
        if ($usingZip) {
            $archive = new ZipArchive();
            if ($archive->open($targetZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Gagal membuat ZIP backup data.');
            }
        } else {
            $archive = new PharData($targetZip, 0, null, Phar::ZIP);
        }

        $files=[]; $total=0;
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $f) {
                if (!$f->isFile()) continue;
                $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($sourceDir)+1));
                if ($rel === '' || str_contains($rel, '..') || str_starts_with($rel, '/')) continue;
                $size=(int)$f->getSize();
                if ($size > self::MAX_SINGLE_FILE) throw new RuntimeException('File data terlalu besar: '.$rel);
                $total += $size;
                if ($total > self::MAX_TOTAL_BYTES) throw new RuntimeException('Total backup data melebihi 1 GiB.');
                if (count($files) >= self::MAX_FILES) throw new RuntimeException('Jumlah file data melebihi batas aman.');
                $name='app-data/'.$rel;
                if ($usingZip) {
                    if (!$archive->addFile($f->getPathname(), $name)) throw new RuntimeException('Gagal menambahkan '.$rel.' ke backup.');
                } else {
                    $archive->addFile($f->getPathname(), $name);
                }
                $sha=@hash_file('sha256', $f->getPathname());
                if ($sha===false) throw new RuntimeException('Gagal menghitung checksum '.$rel.'.');
                $files[]=['path'=>$name,'size'=>$size,'sha256'=>$sha];
            }
            $manifest=[
                'format'=>self::FORMAT,
                'version'=>self::VERSION,
                'created_at'=>date(DATE_ATOM),
                'files'=>$files,
                'total_files'=>count($files),
                'total_bytes'=>$total,
            ];
            $raw=json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if ($usingZip) $archive->addFromString('manifest.json', $raw); else $archive->addFromString('manifest.json', $raw);
        } catch (\Throwable $e) {
            if ($usingZip) @$archive->close(); else unset($archive);
            @unlink($targetZip);
            throw $e;
        }
        if ($usingZip) {
            if (!$archive->close()) throw new RuntimeException('Gagal menyelesaikan ZIP backup data.');
        } else unset($archive);
        return $manifest;
    }

    public static function restore(string $zipPath, string $targetDir): array
    {
        $manifest=self::validate($zipPath);
        $tmp=rtrim(sys_get_temp_dir(),'/\\').'/ck-appdata-'.bin2hex(random_bytes(8));
        if (!@mkdir($tmp,0700,true) && !is_dir($tmp)) throw new RuntimeException('Folder restore sementara tidak dapat dibuat.');
        try {
            self::extractValidated($zipPath,$tmp,$manifest);
            $incoming=$tmp.'/app-data';
            if (!is_dir($incoming)) @mkdir($incoming,0700,true);
            if (!is_dir($targetDir) && !@mkdir($targetDir,0750,true) && !is_dir($targetDir)) throw new RuntimeException('Folder tujuan restore tidak dapat dibuat.');
            self::clearDirectory($targetDir);
            self::copyDirectory($incoming,$targetDir);
            return ['files'=>(int)($manifest['total_files']??0),'bytes'=>(int)($manifest['total_bytes']??0),'created_at'=>(string)($manifest['created_at']??'')];
        } finally {
            self::removeDirectory($tmp);
        }
    }

    private static function validate(string $zipPath): array
    {
        if (!is_file($zipPath)) throw new RuntimeException('File backup data tidak ditemukan.');
        if (class_exists(ZipArchive::class)) {
            $zip=new ZipArchive();
            if ($zip->open($zipPath)!==true) throw new RuntimeException('ZIP backup data tidak dapat dibuka.');
            try {
                $raw=$zip->getFromName('manifest.json');
                if ($raw===false) throw new RuntimeException('manifest.json tidak ditemukan.');
                $m=json_decode($raw,true);
                if (!is_array($m) || ($m['format']??'')!==self::FORMAT || (int)($m['version']??0)!==self::VERSION) throw new RuntimeException('Format backup data tidak dikenali.');
                self::validateManifest($m, function($name) use($zip){ $s=$zip->statName($name); return $s===false?null:(int)($s['size']??0); }, function($name) use($zip){ $st=$zip->getStream($name); if(!$st) throw new RuntimeException('File backup tidak dapat dibaca: '.$name); $ctx=hash_init('sha256'); hash_update_stream($ctx,$st); fclose($st); return hash_final($ctx); });
                return $m;
            } finally { $zip->close(); }
        }
        if (!class_exists(PharData::class)) throw new RuntimeException('Dukungan ZIP tidak tersedia.');
        $phar=new PharData($zipPath);
        if (!isset($phar['manifest.json'])) throw new RuntimeException('manifest.json tidak ditemukan.');
        $m=json_decode($phar['manifest.json']->getContent(),true);
        if (!is_array($m) || ($m['format']??'')!==self::FORMAT || (int)($m['version']??0)!==self::VERSION) throw new RuntimeException('Format backup data tidak dikenali.');
        $real=realpath($zipPath)?:$zipPath;
        self::validateManifest($m, fn($name)=>isset($phar[$name])?(int)$phar[$name]->getSize():null, function($name)use($real){$h=@hash_file('sha256','phar://'.$real.'/'.$name);if($h===false)throw new RuntimeException('File backup tidak dapat dibaca: '.$name);return $h;});
        return $m;
    }

    private static function validateManifest(array $m, callable $stat, callable $hash): void
    {
        $files=$m['files']??[];
        if (!is_array($files) || count($files)>self::MAX_FILES) throw new RuntimeException('Manifest backup data tidak valid.');
        $sum=0; $seen=[];
        foreach($files as $f){
            $name=(string)($f['path']??'');
            if(!str_starts_with($name,'app-data/')||str_contains($name,'..')||str_starts_with($name,'/'))throw new RuntimeException('Path backup data tidak aman.');
            if(isset($seen[$name]))throw new RuntimeException('File duplikat pada backup data.'); $seen[$name]=true;
            $size=$stat($name); if($size===null)throw new RuntimeException('File backup hilang: '.$name); $size=(int)$size;
            if($size>self::MAX_SINGLE_FILE||(int)($f['size']??$size)!==$size)throw new RuntimeException('Ukuran file backup tidak valid: '.$name);
            $sum+=$size;if($sum>self::MAX_TOTAL_BYTES)throw new RuntimeException('Backup data terlalu besar.');
            $expected=(string)($f['sha256']??''); if($expected!==''&&!hash_equals($expected,$hash($name)))throw new RuntimeException('Checksum backup tidak cocok: '.$name);
        }
        if(isset($m['total_files'])&&(int)$m['total_files']!==count($files))throw new RuntimeException('Jumlah file backup tidak konsisten.');
        if(isset($m['total_bytes'])&&(int)$m['total_bytes']!==$sum)throw new RuntimeException('Ukuran total backup tidak konsisten.');
    }

    private static function extractValidated(string $zipPath,string $target,array $manifest): void
    {
        $allowed=[]; foreach(($manifest['files']??[]) as $f)$allowed[(string)$f['path']]=true;
        if(class_exists(ZipArchive::class)){
            $zip=new ZipArchive(); if($zip->open($zipPath)!==true)throw new RuntimeException('ZIP backup data tidak dapat dibuka.');
            try{foreach(array_keys($allowed) as $name){$dest=$target.'/'.$name;@mkdir(dirname($dest),0700,true);$in=$zip->getStream($name);if(!$in)throw new RuntimeException('Gagal membaca '.$name);$out=fopen($dest,'wb');if(!$out){fclose($in);throw new RuntimeException('Gagal membuat file restore.');}stream_copy_to_stream($in,$out);fclose($in);fclose($out);}}finally{$zip->close();}
            return;
        }
        $real=realpath($zipPath)?:$zipPath; foreach(array_keys($allowed) as $name){$dest=$target.'/'.$name;@mkdir(dirname($dest),0700,true);if(!@copy('phar://'.$real.'/'.$name,$dest))throw new RuntimeException('Gagal mengekstrak '.$name);}
    }

    private static function clearDirectory(string $dir): void
    {
        if(!is_dir($dir))return;
        foreach(new \FilesystemIterator($dir,\FilesystemIterator::SKIP_DOTS) as $f){
            if($f->getFilename()==='.htaccess') continue;
            if($f->isDir()&&!$f->isLink())self::removeDirectory($f->getPathname()); else @unlink($f->getPathname());
        }
    }
    private static function copyDirectory(string $src,string $dst): void
    {
        if(!is_dir($src))return;
        $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::SELF_FIRST);
        foreach($it as $f){$rel=substr($f->getPathname(),strlen($src)+1);$to=$dst.'/'.$rel;if($f->isDir()){@mkdir($to,0750,true);}else{@mkdir(dirname($to),0750,true);if(!@copy($f->getPathname(),$to))throw new RuntimeException('Gagal mengembalikan file data: '.$rel);}}
    }
    private static function removeDirectory(string $dir): void
    {
        if(!is_dir($dir))return;$it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){$f->isDir()&&!$f->isLink()?@rmdir($f->getPathname()):@unlink($f->getPathname());}@rmdir($dir);
    }
}
