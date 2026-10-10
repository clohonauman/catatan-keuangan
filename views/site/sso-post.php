<?php
$endpoint = (string)($endpoint ?? '');
$appName = (string)($app['name'] ?? 'Aplikasi');
$token = (string)($token ?? '');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="referrer" content="no-referrer">
<meta name="robots" content="noindex,nofollow">
<title>Membuka <?= h($appName) ?>…</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100dvh;display:grid;place-items:center;background:#f5f7fb;color:#182338;font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;padding:24px}main{width:min(420px,100%);padding:26px;border:1px solid #e3e9f2;border-radius:22px;background:#fff;box-shadow:0 20px 55px rgba(20,40,75,.1);text-align:center}.spinner{width:34px;height:34px;margin:0 auto 18px;border:3px solid #dbe7ff;border-top-color:#175cd3;border-radius:50%;animation:spin .8s linear infinite}h1{font-size:18px;margin:0 0 8px}p{font-size:12px;line-height:1.6;color:#7d899c;margin:0 0 18px}button{min-height:42px;border:0;border-radius:12px;padding:0 16px;background:#175cd3;color:white;font-weight:750;cursor:pointer}@keyframes spin{to{transform:rotate(360deg)}}@media(prefers-reduced-motion:reduce){.spinner{animation:none}}
</style>
</head>
<body>
<main>
  <div class="spinner" aria-hidden="true"></div>
  <h1>Membuka <?= h($appName) ?></h1>
  <p>Sesi aman sedang diteruskan ke aplikasi tujuan. Anda tidak perlu memasukkan ulang akun Catatan Keuangan.</p>
  <form id="appSsoPostForm" method="post" action="<?= h($endpoint) ?>" autocomplete="off">
    <input type="hidden" name="sso_token" value="<?= h($token) ?>">
    <button type="submit">Lanjutkan ke aplikasi</button>
  </form>
</main>
<script>
(function(){var f=document.getElementById('appSsoPostForm');if(!f)return;window.setTimeout(function(){try{f.requestSubmit?f.requestSubmit():f.submit();}catch(e){/* tombol manual tetap tersedia */}},160);})();
</script>
</body>
</html>
