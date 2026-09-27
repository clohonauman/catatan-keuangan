<?php

namespace app\services;

use Yii;
use yii\web\UploadedFile;

class AndroidReleaseService
{
    private static function metadataDir(): string
    {
        return rtrim((string)Yii::$app->params['privateStorage'], '/\\') . '/android_releases';
    }

    private static function metadataFile(): string
    {
        return self::metadataDir() . '/releases.json';
    }

    private static function apkDir(): string
    {
        return Yii::getAlias('@webroot/apks');
    }

    private static function ensureDir(string $dir): void
    {
        if (is_dir($dir)) return;
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Folder penyimpanan rilis Android tidak dapat dibuat: ' . $dir);
        }
    }

    private static function defaultSnapshot(): array
    {
        return ['latest' => null, 'history' => [], 'updated_at' => null];
    }

    public static function snapshot(): array
    {
        $file = self::metadataFile();
        if (!is_file($file)) {
            $fallback = self::scanLatestApk();
            $data = self::defaultSnapshot();
            if ($fallback) $data['latest'] = $fallback;
            return $data;
        }

        $raw = @file_get_contents($file);
        $data = json_decode((string)$raw, true);
        if (!is_array($data)) return self::defaultSnapshot();

        $data += self::defaultSnapshot();
        if (!is_array($data['history'])) $data['history'] = [];
        if (!is_array($data['latest'])) $data['latest'] = null;
        if ($data['latest'] && !self::releaseFileExists($data['latest'])) {
            $data['latest'] = self::scanLatestApk();
        }
        return $data;
    }

    public static function latestPublic(): ?array
    {
        $snapshot = self::snapshot();
        $latest = $snapshot['latest'] ?? null;
        if (!is_array($latest)) return null;

        return [
            'version_name' => (string)($latest['version_name'] ?? ''),
            'version_code' => (int)($latest['version_code'] ?? 0),
            'file_name' => (string)($latest['file_name'] ?? ''),
            'size' => (int)($latest['size'] ?? 0),
            'sha256' => (string)($latest['sha256'] ?? ''),
            'notes' => (string)($latest['notes'] ?? ''),
            'published_at' => (string)($latest['published_at'] ?? ''),
        ];
    }

    private static function releaseFileExists(array $release): bool
    {
        $name = basename((string)($release['file_name'] ?? ''));
        return $name !== '' && is_file(self::apkDir() . '/' . $name);
    }

    private static function scanLatestApk(): ?array
    {
        $dir = self::apkDir();
        if (!is_dir($dir)) return null;
        $files = glob($dir . '/*.apk') ?: [];
        if (!$files) return null;

        usort($files, static function ($a, $b) {
            return ((int)@filemtime($b) <=> (int)@filemtime($a)) ?: strcmp($b, $a);
        });
        $file = $files[0];
        $name = basename($file);
        $version = '';
        if (preg_match('/v([0-9]+(?:\.[0-9]+){0,3}(?:[-+][A-Za-z0-9._-]+)?)/i', $name, $m)) $version = $m[1];

        return [
            'version_name' => $version,
            'version_code' => 0,
            'file_name' => $name,
            'size' => (int)(@filesize($file) ?: 0),
            'sha256' => (string)(@hash_file('sha256', $file) ?: ''),
            'notes' => '',
            'published_at' => date('c', (int)(@filemtime($file) ?: time())),
            'published_by' => null,
            'legacy_scan' => true,
        ];
    }

    public static function publish(UploadedFile $file, string $versionName, int $versionCode, string $notes, array $admin): array
    {
        $versionName = trim($versionName);
        $notes = trim($notes);

        if ($versionName === '' || !preg_match('/^[0-9]+(?:\.[0-9]+){0,3}(?:[-+][A-Za-z0-9._-]+)?$/', $versionName)) {
            throw new \InvalidArgumentException('Versi aplikasi tidak valid. Contoh: 1.5.0');
        }
        if ($versionCode < 1) throw new \InvalidArgumentException('Version code / build harus lebih besar dari 0.');
        if ($notes === '') throw new \InvalidArgumentException('Catatan pembaruan wajib diisi.');
        if (strlen($notes) > 12000) throw new \InvalidArgumentException('Catatan pembaruan terlalu panjang. Maksimal 12.000 karakter.');
        if (!$file || (int)$file->error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload APK gagal. Periksa upload_max_filesize dan post_max_size pada server.');
        }
        if (strtolower((string)$file->extension) !== 'apk') throw new \InvalidArgumentException('File aplikasi harus berformat .apk.');
        if ((int)$file->size <= 0) throw new \InvalidArgumentException('File APK kosong.');
        if ((int)$file->size > 250 * 1024 * 1024) throw new \InvalidArgumentException('Ukuran APK terlalu besar. Maksimal 250 MB.');

        $snapshot = self::snapshot();
        $currentCode = (int)($snapshot['latest']['version_code'] ?? 0);
        if ($currentCode > 0 && $versionCode <= $currentCode) {
            throw new \InvalidArgumentException('Version code harus lebih besar dari rilis saat ini (' . $currentCode . ').');
        }

        self::ensureDir(self::metadataDir());
        self::ensureDir(self::apkDir());

        $safeVersion = preg_replace('/[^A-Za-z0-9._+-]/', '-', $versionName);
        $finalName = 'Catatan-Keuangan-v' . $safeVersion . '-build' . $versionCode . '.apk';
        $tmp = self::apkDir() . '/.upload-' . bin2hex(random_bytes(8)) . '.apk';
        $final = self::apkDir() . '/' . $finalName;

        if (!$file->saveAs($tmp)) throw new \RuntimeException('APK tidak dapat disimpan ke folder web/apks.');

        try {
            $fh = @fopen($tmp, 'rb');
            $signature = $fh ? fread($fh, 4) : false;
            if ($fh) fclose($fh);
            if ($signature === false || substr($signature, 0, 2) !== 'PK') {
                throw new \InvalidArgumentException('File yang diupload bukan paket APK/ZIP Android yang valid.');
            }

            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = $finfo ? (string)finfo_file($finfo, $tmp) : '';
                if ($finfo) finfo_close($finfo);
                $allowed = ['application/vnd.android.package-archive','application/octet-stream','application/zip','application/x-zip-compressed'];
                if ($mime !== '' && !in_array($mime, $allowed, true)) {
                    throw new \InvalidArgumentException('Tipe file APK tidak dikenali: ' . $mime);
                }
            }


            // V64: extension + ZIP signature saja belum cukup. APK normal wajib memiliki
            // AndroidManifest.xml dan setidaknya satu classes*.dex.
            if (class_exists(\ZipArchive::class)) {
                $apk = new \ZipArchive();
                if ($apk->open($tmp) !== true) throw new \InvalidArgumentException('Paket APK tidak dapat dibuka.');
                try {
                    if ($apk->locateName('AndroidManifest.xml', \ZipArchive::FL_NODIR) === false) {
                        throw new \InvalidArgumentException('APK tidak memiliki AndroidManifest.xml.');
                    }
                    $hasDex = false;
                    for ($i = 0; $i < $apk->numFiles; $i++) {
                        $name = (string)$apk->getNameIndex($i);
                        if (preg_match('~^classes(?:[0-9]+)?\\.dex$~', $name)) { $hasDex = true; break; }
                    }
                    if (!$hasDex) throw new \InvalidArgumentException('APK tidak memiliki bytecode Android (classes.dex).');
                } finally {
                    $apk->close();
                }
            }

            if (is_file($final) && !@unlink($final)) throw new \RuntimeException('Versi APK tujuan sudah ada dan tidak dapat diganti.');
            if (!@rename($tmp, $final)) {
                if (!@copy($tmp, $final)) throw new \RuntimeException('APK gagal dipindahkan ke lokasi publik.');
                @unlink($tmp);
            }

            $release = [
                'version_name' => $versionName,
                'version_code' => $versionCode,
                'file_name' => $finalName,
                'size' => (int)(@filesize($final) ?: $file->size),
                'sha256' => (string)(@hash_file('sha256', $final) ?: ''),
                'notes' => $notes,
                'published_at' => date('c'),
                'published_by' => ['id'=>(int)($admin['id'] ?? 0),'username'=>(string)($admin['username'] ?? '')],
            ];

            $history = [$release];
            foreach ((array)($snapshot['history'] ?? []) as $row) {
                if (!is_array($row)) continue;
                if ((int)($row['version_code'] ?? 0) === $versionCode) continue;
                $history[] = $row;
                if (count($history) >= 20) break;
            }

            self::writeSnapshot(['latest'=>$release,'history'=>$history,'updated_at'=>date('c')]);
            return $release;
        } catch (\Throwable $e) {
            @unlink($tmp);
            throw $e;
        }
    }

    private static function writeSnapshot(array $data): void
    {
        self::ensureDir(self::metadataDir());
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new \RuntimeException('Metadata rilis Android gagal dibuat.');

        $file = self::metadataFile();
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(5));
        if (@file_put_contents($tmp, $json, LOCK_EX) === false) throw new \RuntimeException('Metadata rilis Android tidak dapat disimpan.');
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            throw new \RuntimeException('Metadata rilis Android tidak dapat dipublikasikan.');
        }
    }
}
