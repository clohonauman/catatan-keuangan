<?php
/**
 * Full migration backup for the legacy JSON application.
 *
 * The ZIP format intentionally matches the Yii2 LegacyBackupService importer:
 *   manifest.json
 *   data/*.json
 *   data/user_finance/*.json
 *   data/receipts/**
 *   data/payment_proofs/**
 */

function legacyFullBackupCollectFiles(string $dataDir): array
{
    $dataDir = rtrim($dataDir, '/\\');
    $rows = [];

    // All JSON documents in data root are application data and must be preserved.
    foreach (glob($dataDir . '/*.json') ?: [] as $path) {
        if (is_file($path)) {
            $rows[] = ['path' => $path, 'relative' => basename($path), 'json' => true];
        }
    }

    foreach (['user_finance', 'receipts', 'payment_proofs'] as $folder) {
        $root = $dataDir . '/' . $folder;
        if (!is_dir($root)) continue;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $file) {
            if (!$file->isFile() || strpos($file->getFilename(), '.') === 0) continue;
            $full = $file->getPathname();
            $sub = substr($full, strlen($root) + 1);
            if ($sub === false || $sub === '' || strpos($sub, '..') !== false) continue;
            $rows[] = [
                'path' => $full,
                'relative' => $folder . '/' . str_replace('\\', '/', $sub),
                'json' => strtolower($file->getExtension()) === 'json',
            ];
        }
    }

    usort($rows, fn(array $a, array $b) => strcmp($a['relative'], $b['relative']));
    return $rows;
}

function legacyFullBackupDataStats(string $dataDir): array
{
    $stats = [
        'users' => 0,
        'finance_files' => 0,
        'transactions' => 0,
        'wallets' => 0,
        'categories' => 0,
        'chats' => 0,
        'receipts' => 0,
        'payment_proofs' => 0,
    ];

    $usersPath = rtrim($dataDir, '/\\') . '/users.json';
    if (is_file($usersPath)) {
        $u = json_decode((string)@file_get_contents($usersPath), true);
        if (is_array($u)) $stats['users'] = count((array)($u['users'] ?? []));
    }

    foreach (glob(rtrim($dataDir, '/\\') . '/user_finance/user_*.json') ?: [] as $path) {
        $stats['finance_files']++;
        $d = json_decode((string)@file_get_contents($path), true);
        if (!is_array($d)) continue;
        $stats['transactions'] += count((array)($d['transactions'] ?? []));
        $stats['wallets'] += count((array)($d['wallets'] ?? []));
        $stats['categories'] += count((array)($d['categories'] ?? []));
        $stats['chats'] += count((array)($d['chats'] ?? []));
    }

    foreach (['receipts', 'payment_proofs'] as $folder) {
        $root = rtrim($dataDir, '/\\') . '/' . $folder;
        if (!is_dir($root)) continue;
        $count = 0;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) if ($file->isFile() && $file->getFilename()[0] !== '.') $count++;
        $stats[$folder] = $count;
    }

    return $stats;
}

function legacyCreateFullBackupZip(string $dataDir, string $targetZip, array $actor = []): array
{
    $dataDir = rtrim($dataDir, '/\\');
    if (!is_dir($dataDir)) throw new RuntimeException('Folder data aplikasi tidak ditemukan.');
    if (!class_exists('ZipArchive') && !class_exists('PharData')) throw new RuntimeException('Server tidak memiliki dukungan pembuatan arsip ZIP (ZipArchive/PharData).');
    if (!is_file($dataDir . '/users.json')) throw new RuntimeException('users.json tidak ditemukan. Backup penuh dibatalkan.');

    $files = legacyFullBackupCollectFiles($dataDir);
    if (!$files) throw new RuntimeException('Tidak ada data yang dapat dibackup.');

    @unlink($targetZip);
    $usingZipArchive = class_exists('ZipArchive');
    if ($usingZipArchive) {
        $zip = new ZipArchive();
        $open = $zip->open($targetZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($open !== true) throw new RuntimeException('Tidak dapat membuat file ZIP backup.');
    } else {
        try { $zip = new PharData($targetZip, 0, null, Phar::ZIP); }
        catch (Throwable $e) { throw new RuntimeException('Tidak dapat membuat ZIP melalui PharData: ' . $e->getMessage(), 0, $e); }
    }

    $manifestFiles = [];
    $totalBytes = 0;

    try {
        foreach ($files as $entry) {
            $source = $entry['path'];
            $relative = $entry['relative'];
            $zipName = 'data/' . $relative;

            // JSON is snapshotted as bytes so its checksum always matches exactly what is inside the ZIP,
            // even if the live application receives another transaction immediately after export starts.
            if (!empty($entry['json'])) {
                $raw = @file_get_contents($source);
                if ($raw === false) throw new RuntimeException('Gagal membaca data: ' . $relative);
                if (json_decode($raw, true) === null && trim($raw) !== 'null') {
                    throw new RuntimeException('JSON tidak valid dan backup dibatalkan: ' . $relative);
                }
                if ($usingZipArchive) { if (!$zip->addFromString($zipName, $raw)) throw new RuntimeException('Gagal memasukkan file: ' . $relative); }
                else { $zip->addFromString($zipName, $raw); }
                $size = strlen($raw);
                $sha = hash('sha256', $raw);
            } else {
                $sizeBefore = (int)@filesize($source);
                if ($sizeBefore < 0) $sizeBefore = 0;
                $sha = @hash_file('sha256', $source);
                if ($sha === false) throw new RuntimeException('Gagal menghitung checksum: ' . $relative);
                if ($usingZipArchive) { if (!$zip->addFile($source, $zipName)) throw new RuntimeException('Gagal memasukkan lampiran: ' . $relative); }
                else { $zip->addFile($source, $zipName); }
                clearstatcache(true, $source);
                $sizeAfter = (int)@filesize($source);
                if ($sizeAfter !== $sizeBefore) throw new RuntimeException('Lampiran berubah saat backup dibuat. Silakan ulangi unduhan: ' . $relative);
                $size = $sizeBefore;
            }

            $manifestFiles[] = ['path' => $zipName, 'size' => $size, 'sha256' => $sha];
            $totalBytes += $size;
        }

        $stats = legacyFullBackupDataStats($dataDir);
        $manifest = [
            'format' => 'catatan-keuangan-legacy-full-backup',
            'version' => 1,
            'created_at' => date(DATE_ATOM),
            'source' => [
                'application' => 'Catatan Keuangan Legacy JSON',
                'purpose' => 'full_mysql_migration',
                'timezone' => date_default_timezone_get(),
                'exported_by_user_id' => isset($actor['id']) ? (int)$actor['id'] : null,
                'exported_by_username' => (string)($actor['username'] ?? ''),
            ],
            'stats' => $stats,
            'files' => $manifestFiles,
            'total_files' => count($manifestFiles),
            'total_bytes' => $totalBytes,
        ];

        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($usingZipArchive) {
            if (!$zip->addFromString('manifest.json', $manifestJson)) throw new RuntimeException('Gagal membuat manifest backup.');
        } else {
            $zip->addFromString('manifest.json', $manifestJson);
        }
    } catch (Throwable $e) {
        if ($usingZipArchive) { @$zip->close(); } else { unset($zip); }
        @unlink($targetZip);
        throw $e;
    }

    if ($usingZipArchive) {
        if (!$zip->close()) {
            @unlink($targetZip);
            throw new RuntimeException('Gagal menyelesaikan file ZIP backup.');
        }
    } else {
        unset($zip); // PharData flushes archive changes when the object is released.
        clearstatcache(true, $targetZip);
    }

    if (!is_file($targetZip) || filesize($targetZip) <= 0) {
        @unlink($targetZip);
        throw new RuntimeException('File backup tidak berhasil dibuat.');
    }

    return $manifest;
}
