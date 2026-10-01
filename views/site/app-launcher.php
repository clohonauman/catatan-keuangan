<?php
$publicBase = rtrim((string)(Yii::$app->params['appUrl'] ?: Yii::$app->request->hostInfo), '/');
$premium = !empty($premium);
$plan = is_array($plan ?? null) ? $plan : [];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#175cd3">
<title>Pilih Aplikasi · Catatan Keuangan</title>
<link rel="icon" type="image/webp" href="assets/icon.webp">
<link rel="apple-touch-icon" href="assets/icon.webp">
<link rel="stylesheet" href="assets/style.css?v=<?= h($assetVersion) ?>">
<style>
.app-launcher-page{min-height:100vh;display:grid;place-items:center;padding:28px 18px;background:radial-gradient(circle at top left,rgba(23,92,211,.10),transparent 34%),linear-gradient(145deg,#f6f9fe,#edf4ff)}
.app-launcher-shell{width:min(1060px,100%)}
.app-launcher-head{text-align:center;margin-bottom:22px}.app-launcher-logo{width:64px;height:64px;margin:0 auto 12px;border-radius:20px;overflow:hidden;background:#fff;box-shadow:0 12px 28px rgba(23,92,211,.14)}.app-launcher-logo img{width:100%;height:100%;object-fit:cover}.app-launcher-head h1{margin:0;color:#162136;font-size:30px;letter-spacing:-.7px}.app-launcher-head p{margin:7px auto 0;color:#778398;font-size:13px;max-width:620px;line-height:1.55}.app-launcher-account{display:inline-flex;align-items:center;gap:7px;margin-top:11px;padding:7px 11px;border:1px solid #dfe7f3;border-radius:999px;background:rgba(255,255,255,.86);font-size:10px;color:#5d6a7e}.app-launcher-account b{color:#26364f}.app-launcher-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.app-launcher-card{position:relative;text-align:left;background:#fff;border:1px solid #dfe7f3;border-radius:25px;padding:23px;box-shadow:0 18px 55px rgba(22,49,92,.08);transition:.18s ease}.app-launcher-card:hover{transform:translateY(-2px);box-shadow:0 24px 65px rgba(22,49,92,.12)}.app-launcher-icon{width:58px;height:58px;border-radius:18px;display:grid;place-items:center;font-size:29px;background:#edf4ff;margin-bottom:16px}.app-launcher-card.plan-card .app-launcher-icon{background:#fff4d2}.app-launcher-card h2{margin:0 0 6px;font-size:20px;letter-spacing:-.3px}.app-launcher-card p{margin:0;color:#748096;font-size:12px;line-height:1.6;min-height:58px}.app-launcher-features{display:flex;flex-wrap:wrap;gap:6px;margin:15px 0 19px}.app-launcher-features span{padding:6px 8px;border-radius:9px;background:#f7f9fc;color:#66748a;font-size:9px;font-weight:800}.app-launcher-action{display:flex;align-items:center;justify-content:space-between;gap:12px}.app-launcher-action small{color:#98a2b3;font-size:9px}.app-launcher-action .btn{text-decoration:none}.app-launcher-lock{display:inline-flex;align-items:center;gap:5px;padding:7px 9px;border-radius:10px;background:#fff5d8;color:#8a5a00;font-size:9px;font-weight:900}.app-launcher-error{margin:0 auto 14px;max-width:760px;padding:11px 14px;border-radius:13px;background:#fff1f0;border:1px solid #ffd5d1;color:#a72d26;font-size:11px;text-align:center}.app-launcher-footer{display:flex;justify-content:center;gap:15px;margin-top:18px}.app-launcher-footer form{margin:0}.app-launcher-footer button,.app-launcher-footer a{border:0;background:none;color:#748096;font-size:10px;text-decoration:none;cursor:pointer}.app-launcher-note{margin-top:10px;text-align:center;color:#a0a9b7;font-size:9px}
@media(max-width:720px){.app-launcher-page{padding:18px 12px}.app-launcher-head h1{font-size:25px}.app-launcher-grid{grid-template-columns:1fr}.app-launcher-card{padding:19px;border-radius:21px}.app-launcher-card p{min-height:0}.app-launcher-action{align-items:flex-end}.app-launcher-icon{margin-bottom:12px}}
</style>
</head>
<body>
<main class="app-launcher-page"><div class="app-launcher-shell">
<header class="app-launcher-head">
    <div class="app-launcher-logo"><img src="assets/icon.webp" alt="Catatan Keuangan"></div>
    <h1>Pilih aplikasi</h1>
    <p>Satu akun untuk dua aplikasi yang saling terhubung. Pilih kebutuhan Anda untuk melanjutkan.</p>
    <div class="app-launcher-account">Akun <b><?= h($user['username'] ?? '') ?></b><span>·</span><b><?= h($premium ? 'Premium' : 'Free') ?></b></div>
</header>
<?php if (!empty($error)): ?><div class="app-launcher-error"><?= h($error) ?></div><?php endif; ?>
<section class="app-launcher-grid">
<article class="app-launcher-card">
    <div class="app-launcher-icon">💰</div>
    <h2>Catat Keuangan</h2>
    <p>Pencatatan transaksi, saldo, dompet, tagihan, piutang, analitik, notifikasi, backup, dan fitur keuangan utama.</p>
    <div class="app-launcher-features"><span>Transaksi</span><span>Saldo & Dompet</span><span>Analitik</span><span>Piutang</span></div>
    <div class="app-launcher-action">
        <small>Aplikasi utama</small>
        <form method="post" action="<?= h($publicBase) ?>/index.php">
            <input type="hidden" name="<?= h(Yii::$app->request->csrfParam) ?>" value="<?= h(Yii::$app->request->csrfToken) ?>">
            <input type="hidden" name="action" value="set_preferred_app"><input type="hidden" name="app" value="main">
            <button class="btn primary" type="submit">Buka Catat Keuangan</button>
        </form>
    </div>
</article>
<article class="app-launcher-card plan-card">
    <div class="app-launcher-icon">📊</div>
    <h2>Perencanaan Keuangan</h2>
    <p>Menyusun rencana belanja bulan berikutnya berdasarkan riwayat pengeluaran, pemasukan, tagihan, cicilan, dan transaksi berulang.</p>
    <div class="app-launcher-features"><span>Rencana Bulanan</span><span>Riwayat</span><span>Cicilan & Tagihan</span><span>Peringatan Dana</span></div>
    <div class="app-launcher-action">
        <?php if ($premium): ?>
        <small>Terhubung ke data utama</small>
        <form method="post" action="<?= h($publicBase) ?>/index.php">
            <input type="hidden" name="<?= h(Yii::$app->request->csrfParam) ?>" value="<?= h(Yii::$app->request->csrfToken) ?>">
            <input type="hidden" name="action" value="set_preferred_app"><input type="hidden" name="app" value="planning">
            <button class="btn primary" type="submit">Buka Perencanaan</button>
        </form>
        <?php else: ?>
        <span class="app-launcher-lock">🔒 Khusus Premium</span><a class="btn secondary" href="<?= h($publicBase) ?>/index.php?app=main">Lihat Premium</a>
        <?php endif; ?>
    </div>
</article>
</section>
<footer class="app-launcher-footer">
    <form method="post" action="<?= h($publicBase) ?>/index.php"><input type="hidden" name="<?= h(Yii::$app->request->csrfParam) ?>" value="<?= h(Yii::$app->request->csrfToken) ?>"><input type="hidden" name="action" value="logout"><button type="submit">Login dengan akun lain</button></form>
    <a href="privacy-policy.php">Kebijakan Privasi</a>
</footer>
<div class="app-launcher-note">Jika sudah memilih aplikasi sebelumnya, login berikutnya akan kembali ke aplikasi terakhir yang digunakan.</div>
</div></main>
</body></html>
