<?php
$assetVersion = @filemtime(Yii::getAlias('@webroot/assets/style.css')) ?: 1;
$appUrl = rtrim((string)$appUrl, '/');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#175cd3">
<meta name="csrf-token" content="<?= h(Yii::$app->request->csrfToken) ?>">
<title>Perencanaan Keuangan · Premium</title>
<link rel="icon" href="/assets/icon.webp">
<link rel="stylesheet" href="/assets/style.css?v=<?= h($assetVersion) ?>">
<style>
body{margin:0;min-height:100vh;background:linear-gradient(145deg,#f5f8fd,#eef4ff);font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#172033}.planning-gate{min-height:100vh;display:grid;place-items:center;padding:24px}.planning-gate-card{width:min(720px,100%);background:#fff;border:1px solid #e1e8f3;border-radius:30px;padding:36px;box-shadow:0 30px 80px rgba(22,49,92,.14);text-align:center}.planning-gate-icon{width:72px;height:72px;border-radius:22px;background:#edf4ff;color:#175cd3;display:grid;place-items:center;margin:0 auto 18px;font-size:34px}.planning-gate h1{margin:0 0 10px;font-size:30px}.planning-gate p{margin:0 auto 24px;max-width:560px;color:#667085;line-height:1.65}.planning-gate-actions{display:flex;justify-content:center;gap:10px;flex-wrap:wrap}.planning-gate-actions a{text-decoration:none}.planning-gate-note{margin-top:18px;font-size:12px;color:#98a2b3}
@media(max-width:640px){.planning-gate{padding:14px}.planning-gate-card{padding:28px 20px;border-radius:24px}.planning-gate h1{font-size:25px}}
</style>
</head>
<body>
<main class="planning-gate"><section class="planning-gate-card">
<div class="planning-gate-icon">📊</div>
<h1>Perencanaan Keuangan</h1>
<p>Aplikasi ini membantu menyusun rencana belanja bulan berikutnya berdasarkan riwayat Catatan Keuangan, kewajiban terjadwal, dan perkiraan pemasukan.</p>
<div class="planning-gate-actions">
<a class="btn primary" href="<?= h($appUrl) ?>/index.php?app=main">Kembali ke Catat Keuangan</a>
<a class="btn secondary" href="<?= h($appUrl) ?>/index.php?app=chooser">Pilih Aplikasi</a>
</div>
<div class="planning-gate-note">Fitur Perencanaan Keuangan tersedia untuk akun Premium.</div>
</section></main>
</body></html>
