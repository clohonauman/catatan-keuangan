<?php
$escape = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="referrer" content="no-referrer"><title>Membuka <?= $escape($app['name'] ?? 'Aplikasi') ?></title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f3f7ff;color:#18243a;font:16px system-ui,-apple-system,Segoe UI,sans-serif}.card{max-width:420px;margin:20px;padding:28px;border:1px solid #dde7f5;border-radius:24px;background:#fff;text-align:center;box-shadow:0 18px 60px #16325b14}.spin{width:34px;height:34px;border:3px solid #dce8ff;border-top-color:#175cd3;border-radius:50%;margin:0 auto 16px;animation:r .75s linear infinite}@keyframes r{to{transform:rotate(360deg)}}button{border:0;border-radius:12px;padding:12px 18px;background:#175cd3;color:#fff;font-weight:700}</style>
</head>
<body><main class="card"><div class="spin" aria-hidden="true"></div><h1 style="font-size:21px;margin:0 0 8px">Menyiapkan akses</h1><p style="color:#718096;line-height:1.6">Anda akan dialihkan ke <strong><?= $escape($app['name'] ?? 'aplikasi') ?></strong> dengan sesi Catatan Keuangan.</p><form id="ssoLaunch" method="post" action="<?= $escape($endpoint) ?>"><input type="hidden" name="sso_token" value="<?= $escape($token) ?>"><input type="hidden" name="sso_issuer" value="<?= $escape(rtrim((string)(Yii::$app->params['appUrl'] ?: Yii::$app->request->hostInfo), '/')) ?>"><noscript><button type="submit">Lanjutkan ke aplikasi</button></noscript></form></main><script>document.getElementById('ssoLaunch').submit();</script></body></html>
