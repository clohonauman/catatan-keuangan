<?php
use yii\helpers\Html;
$title = trim((string)($maintenance['title'] ?? '')) ?: 'Mode Maintenance';
$message = trim((string)($maintenance['message'] ?? '')) ?: 'Aplikasi sedang dalam proses pemeliharaan.';
$isLoggedIn = !empty($user);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<title><?= Html::encode($title) ?> · Catatan Keuangan</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;background:radial-gradient(circle at top,#e8efff 0,#f6f8fc 38%,#eef2f7 100%);color:#172033;display:grid;place-items:center;padding:24px}.card{width:min(100%,560px);background:rgba(255,255,255,.96);border:1px solid rgba(148,163,184,.2);border-radius:28px;padding:38px;box-shadow:0 28px 70px rgba(15,23,42,.12);text-align:center}.mark{width:76px;height:76px;margin:0 auto 22px;border-radius:24px;display:grid;place-items:center;background:#eef3ff;border:1px solid #dce7ff}.mark svg{width:38px;height:38px;stroke:#315ecf;fill:none;stroke-width:1.8}.badge{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#fff7e6;color:#a15c00;font-size:12px;font-weight:800;letter-spacing:.03em}.dot{width:8px;height:8px;border-radius:50%;background:#f59e0b;box-shadow:0 0 0 5px rgba(245,158,11,.12)}h1{font-size:30px;line-height:1.15;margin:18px 0 12px}p{font-size:15px;line-height:1.75;color:#667085;margin:0 auto;max-width:460px}.actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:28px}.btn{appearance:none;border:0;border-radius:14px;padding:12px 17px;font-weight:750;font-size:14px;text-decoration:none;cursor:pointer}.primary{background:#172033;color:#fff}.secondary{background:#eef2f7;color:#344054}.foot{margin-top:22px;font-size:12px;color:#98a2b3}@media(max-width:560px){body{padding:16px}.card{padding:30px 22px;border-radius:24px}h1{font-size:26px}.actions{flex-direction:column}.btn{width:100%}}
</style>
</head>
<body>
<main class="card">
  <div class="mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14.7 6.3a4 4 0 0 0-5 5L4 17l3 3 5.7-5.7a4 4 0 0 0 5-5l-2.2 2.2-3-3 2.2-2.2Z"/><path d="m6.5 16.5 1 1"/></svg></div>
  <span class="badge"><span class="dot"></span> MAINTENANCE AKTIF</span>
  <h1><?= Html::encode($title) ?></h1>
  <p><?= nl2br(Html::encode($message)) ?></p>
  <div class="actions">
    <form method="post" style="margin:0"><?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?><input type="hidden" name="action" value="logout"><button class="btn secondary" type="submit">Keluar dari akun</button></form>
  </div>
  <div class="foot">Catatan Keuangan · halaman ini akan memeriksa status layanan secara otomatis.</div>
</main>
<script>
(() => {
  // V44: ketika Super Admin mematikan maintenance atau memberi pengecualian
  // kepada akun ini, halaman otomatis kembali ke aplikasi tanpa refresh manual.
  let checking = false;
  const checkAccess = async () => {
    if (checking || !navigator.onLine) return;
    checking = true;
    try {
      const url = new URL(window.location.href);
      url.searchParams.set('_maintenance_check', String(Date.now()));
      const response = await fetch(url.toString(), {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'X-Maintenance-Check': '1' }
      });
      if (response.status !== 503 && response.ok) {
        window.location.replace('<?= Html::encode(Yii::$app->urlManager->createUrl(['site/index'])) ?>');
      }
    } catch (_) {
      // Gangguan jaringan tidak mengubah halaman maintenance.
    } finally {
      checking = false;
    }
  };
  setInterval(checkAccess, 3000);
  window.addEventListener('online', checkAccess);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) checkAccess(); });
})();
</script>
</body>
</html>
