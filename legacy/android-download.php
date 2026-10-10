<?php
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$appName = 'Catatan Keuangan';
$release = \app\services\AndroidReleaseService::latestPublic();

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function formatBytes($bytes) {
    $bytes = max(0, (int)$bytes);
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024*1024) return number_format($bytes/1024, 1, ',', '.') . ' KB';
    return number_format($bytes/(1024*1024), 1, ',', '.') . ' MB';
}

$fileName = $release ? basename((string)$release['file_name']) : '';
$apkExists = $fileName !== '' && is_file(Yii::getAlias('@webroot/apks/') . $fileName);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Unduh <?=h($appName)?> untuk Android</title>
<style>
:root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#172235;background:#f4f7fb}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px}.card{width:min(620px,100%);background:#fff;border:1px solid #e2e8f0;border-radius:24px;padding:28px;box-shadow:0 20px 60px rgba(23,34,53,.12)}.logo{width:64px;height:64px;border-radius:18px;background:#eaf2ff;display:grid;place-items:center;margin-bottom:18px}.logo img{width:42px;height:42px;object-fit:contain}h1{font-size:26px;line-height:1.2;margin:0 0 8px}p{color:#667085;line-height:1.7;margin:0 0 18px}.info{padding:16px;border-radius:16px;background:#f8fafc;border:1px solid #e7edf4;margin:18px 0}.info b{display:block;margin-bottom:5px}.meta{font-size:13px;color:#7b8798}.notes{margin:14px 0 18px;padding:16px;border-radius:16px;background:#fff;border:1px solid #e7edf4}.notes b{display:block;margin-bottom:9px}.notes div{font-size:14px;line-height:1.7;color:#475467;white-space:normal}.btn{display:block;text-align:center;text-decoration:none;background:#175cd3;color:#fff;font-weight:800;padding:14px 18px;border-radius:14px}.btn:hover{background:#124cad}.warn{margin-top:16px;font-size:12px;color:#8792a3;line-height:1.6}.missing{padding:14px 16px;border-radius:14px;background:#fff4e5;color:#9a6700;border:1px solid #fedf89}.hash{margin-top:10px;font-size:10px;color:#98a2b3;overflow-wrap:anywhere}@media(max-width:520px){body{padding:14px}.card{padding:21px;border-radius:20px}h1{font-size:22px}}
</style>
</head>
<body>
<main class="card">
  <div class="logo"><img src="assets/icon.webp" alt=""></div>
  <h1>Unduh <?=h($appName)?> untuk Android</h1>
  <p>Halaman resmi untuk mengunduh versi APK terbaru beserta detail pembaruannya.</p>
  <?php if ($release && $apkExists): ?>
    <div class="info">
      <b>Versi <?=h($release['version_name'] ?: 'terbaru')?><?=((int)$release['version_code']>0?' · build '.(int)$release['version_code']:'')?></b>
      <div class="meta">Ukuran <?=h(formatBytes($release['size']))?><?=!empty($release['published_at'])?' · dirilis '.h(date('d/m/Y H:i', strtotime($release['published_at']))):''?></div>
      <?php if (!empty($release['sha256'])): ?><div class="hash">SHA-256: <?=h($release['sha256'])?></div><?php endif; ?>
    </div>
    <?php if (trim((string)$release['notes']) !== ''): ?>
    <div class="notes"><b>Apa yang diperbarui</b><div><?=nl2br(h($release['notes']))?></div></div>
    <?php endif; ?>
    <a class="btn" href="apks/<?=rawurlencode($fileName)?>" download>Unduh APK v<?=h($release['version_name'] ?: 'Terbaru')?></a>
    <div class="warn">Jika Android menampilkan konfirmasi instalasi dari sumber ini, ikuti petunjuk perangkat. Unduh hanya dari domain resmi Catatan Keuangan.</div>
  <?php else: ?>
    <div class="missing">File APK belum tersedia. Silakan coba lagi nanti.</div>
  <?php endif; ?>
</main>
</body>
</html>
