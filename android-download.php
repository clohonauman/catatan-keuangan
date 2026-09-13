<?php
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$appName = 'Catatan Keuangan';
$apkDir = __DIR__ . '/apks';
$files = [];
if (is_dir($apkDir)) {
    foreach (glob($apkDir . '/*.apk') ?: [] as $file) {
        if (!is_file($file)) continue;
        $files[] = [
            'path' => $file,
            'name' => basename($file),
            'mtime' => @filemtime($file) ?: 0,
            'size' => @filesize($file) ?: 0,
        ];
    }
}
usort($files, static fn($a, $b) => ($b['mtime'] <=> $a['mtime']) ?: strcmp($b['name'], $a['name']));
$latest = $files[0] ?? null;

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function formatBytes($bytes) {
    $bytes = max(0, (int)$bytes);
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024*1024) return number_format($bytes/1024, 1, ',', '.') . ' KB';
    return number_format($bytes/(1024*1024), 1, ',', '.') . ' MB';
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Unduh <?=h($appName)?> untuk Android</title>
<style>
:root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#172235;background:#f4f7fb}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px}.card{width:min(560px,100%);background:#fff;border:1px solid #e2e8f0;border-radius:24px;padding:28px;box-shadow:0 20px 60px rgba(23,34,53,.12)}.logo{width:64px;height:64px;border-radius:18px;background:#eaf2ff;display:grid;place-items:center;margin-bottom:18px}.logo img{width:42px;height:42px;object-fit:contain}h1{font-size:26px;line-height:1.2;margin:0 0 8px}p{color:#667085;line-height:1.7;margin:0 0 18px}.info{padding:14px 16px;border-radius:14px;background:#f8fafc;border:1px solid #e7edf4;margin:18px 0}.info b{display:block;margin-bottom:4px}.meta{font-size:13px;color:#7b8798}.btn{display:block;text-align:center;text-decoration:none;background:#175cd3;color:#fff;font-weight:800;padding:14px 18px;border-radius:14px}.btn:hover{background:#124cad}.warn{margin-top:16px;font-size:12px;color:#8792a3;line-height:1.6}.missing{padding:14px 16px;border-radius:14px;background:#fff4e5;color:#9a6700;border:1px solid #fedf89}
</style>
</head>
<body>
<main class="card">
  <div class="logo"><img src="assets/icon.webp" alt=""></div>
  <h1>Unduh <?=h($appName)?> untuk Android</h1>
  <p>Gunakan halaman resmi ini untuk mengunduh versi APK terbaru Catatan Keuangan.</p>
  <?php if ($latest): ?>
    <div class="info"><b><?=h($latest['name'])?></b><div class="meta">Ukuran <?=h(formatBytes($latest['size']))?> · diperbarui <?=h(date('d/m/Y H:i', $latest['mtime']))?></div></div>
    <a class="btn" href="apks/<?=rawurlencode($latest['name'])?>" download>Unduh APK Terbaru</a>
    <div class="warn">Jika Android menampilkan konfirmasi instalasi dari sumber ini, ikuti petunjuk perangkat Anda. Unduh hanya dari domain resmi Catatan Keuangan.</div>
  <?php else: ?>
    <div class="missing">File APK belum tersedia. Silakan coba lagi nanti.</div>
  <?php endif; ?>
</main>
</body>
</html>
