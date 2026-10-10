<?php
use yii\helpers\Url;
$publicBase = rtrim((string)(Yii::$app->params['appUrl'] ?: Yii::$app->request->hostInfo), '/');
$premium = !empty($premium);
$apps = is_array($apps ?? null) ? $apps : [];
$isSuperAdmin = !empty($isSuperAdmin);
// The production site has had two launcher implementations. Detect the schema
// supplied by the active controller so links always target matching routes.
$appHubSchema = true;
foreach ($apps as $candidate) {
    if (!is_array($candidate)) continue;
    if (array_key_exists('is_builtin', $candidate) || array_key_exists('premium_required', $candidate) || array_key_exists('sort_order', $candidate)) {
        $appHubSchema = true;
        break;
    }
    if (array_key_exists('builtin', $candidate) || array_key_exists('requires_premium', $candidate)) {
        $appHubSchema = false;
        break;
    }
}
$manageUrl = Url::to($appHubSchema ? ['/app-hub/manage'] : ['/app-portal/manage']);
$galleryCount = count($apps) + ($isSuperAdmin ? 1 : 0);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#f6f8fc">
<title>Daftar Aplikasi · Catatan Keuangan</title>
<link rel="icon" type="image/webp" href="assets/icon.webp">
<link rel="apple-touch-icon" href="assets/icon.webp">
<style>
:root{color-scheme:light;--portal-ink:#172033;--portal-muted:#79859a;--portal-blue:#175cd3;--portal-line:#e7ebf3;--portal-bg:#f6f8fc}
*{box-sizing:border-box}
html{min-height:100%;background:var(--portal-bg);scrollbar-gutter:stable}
body{margin:0;min-height:100dvh;overflow-x:hidden;overflow-y:auto;background:radial-gradient(ellipse at 8% 0,rgba(46,113,229,.10),transparent 42%),linear-gradient(180deg,#f7f9fd 0%,#f1f5fb 100%);color:var(--portal-ink);font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;-webkit-font-smoothing:antialiased;padding:0}
.app-launcher-page{min-height:100dvh;padding:calc(20px + env(safe-area-inset-top,0px)) max(16px,env(safe-area-inset-right,0px)) calc(24px + env(safe-area-inset-bottom,0px)) max(16px,env(safe-area-inset-left,0px))}
.app-launcher-shell{width:min(100%,1020px);margin:0 auto}
.app-launcher-head{display:flex;align-items:center;gap:13px;margin:0 0 25px;min-width:0}
.app-launcher-logo{flex:0 0 48px;width:48px;height:48px;border-radius:15px;overflow:hidden;background:#fff;box-shadow:0 4px 14px rgba(25,55,100,.08);border:1px solid rgba(220,228,241,.8)}
.app-launcher-logo img{width:100%;height:100%;object-fit:cover}
.app-launcher-head-copy{min-width:0;flex:1}
.app-launcher-head h1{font-size:clamp(21px,3vw,28px);letter-spacing:-.65px;line-height:1.2;margin:0;font-weight:780}
.app-launcher-head p{margin:5px 0 0;font-size:12px;line-height:1.45;color:var(--portal-muted)}
.app-launcher-account{flex:0 0 auto;display:flex;align-items:center;gap:7px;max-width:240px;padding:8px 11px;border:1px solid #e2e8f2;border-radius:999px;background:rgba(255,255,255,.8);color:#66748a;font-size:11px;white-space:nowrap}
.app-launcher-account .account-user{max-width:130px;overflow:hidden;text-overflow:ellipsis;color:#35435a;font-weight:650}
.app-launcher-account .plan-pill{padding:4px 7px;border-radius:999px;background:#e8f7ee;color:#137a43;font-size:9px;font-weight:800}
.app-launcher-error,.app-launcher-notice{margin:0 0 14px;padding:10px 13px;border-radius:12px;font-size:12px;line-height:1.45}
.app-launcher-error{background:#fff1f0;border:1px solid #ffd4cf;color:#a92e25}.app-launcher-notice{background:#edf8f1;border:1px solid #c8ebd6;color:#176e40}
.app-launcher-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 2px 12px}
.app-launcher-section-head h2{margin:0;font-size:13px;color:#8792a5;font-weight:750;letter-spacing:.08em;text-transform:uppercase}
.app-launcher-section-head small{font-size:11px;color:#98a2b3}
.app-launcher-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:10px 6px;align-items:start}
.app-launcher-tile{display:flex;flex-direction:column;align-items:center;justify-content:flex-start;gap:9px;min-width:0;min-height:116px;padding:12px 5px 10px;border:1px solid transparent;border-radius:18px;background:transparent;color:inherit;text-decoration:none;text-align:center;transition:background .16s ease,border-color .16s ease,transform .16s ease;-webkit-tap-highlight-color:transparent}
.app-launcher-tile:hover,.app-launcher-tile:focus-visible{background:rgba(255,255,255,.85);border-color:#e2e8f2;outline:none}
.app-launcher-tile:active{transform:scale(.97)}
.app-launcher-icon{position:relative;display:grid;place-items:center;flex:0 0 58px;width:58px;height:58px;border:1px solid rgba(223,230,241,.92);border-radius:19px;background:linear-gradient(145deg,#fff,#f4f7fc);box-shadow:0 5px 14px rgba(19,43,83,.07);font-size:29px;line-height:1;overflow:hidden}
.app-launcher-icon img{display:block;width:100%;height:100%;object-fit:cover}
.app-launcher-tile:nth-child(4n+1) .app-launcher-icon{background:linear-gradient(145deg,#eaf2ff,#f8fbff);color:#175cd3}
.app-launcher-tile:nth-child(4n+2) .app-launcher-icon{background:linear-gradient(145deg,#e8f8ef,#f8fffb)}
.app-launcher-tile:nth-child(4n+3) .app-launcher-icon{background:linear-gradient(145deg,#fff3df,#fffaf0)}
.app-launcher-tile:nth-child(4n) .app-launcher-icon{background:linear-gradient(145deg,#f2ebff,#fbf9ff)}
.app-launcher-name{display:-webkit-box;max-width:100%;min-height:30px;overflow:hidden;color:#334057;font-size:11.5px;font-weight:650;line-height:1.28;letter-spacing:-.1px;overflow-wrap:anywhere;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.app-launcher-meta{display:flex;align-items:center;justify-content:center;gap:4px;min-height:13px;color:#9aa4b4;font-size:9px;font-weight:700;line-height:1}
.app-launcher-lock{position:absolute;right:-2px;bottom:-2px;width:20px;height:20px;display:grid;place-items:center;border:2px solid #fff;border-radius:50%;background:#fff0c2;color:#8a5a00;font-size:11px;box-shadow:0 2px 5px rgba(30,45,70,.1)}
.app-launcher-tile.is-locked .app-launcher-icon{filter:saturate(.55);opacity:.82}
.app-launcher-divider{height:1px;background:linear-gradient(90deg,transparent,#e0e7f1,transparent);margin:22px 0 16px}
.app-launcher-footer{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-top:24px;padding:12px 2px max(4px,env(safe-area-inset-bottom,0px));border-top:1px solid rgba(224,231,241,.8);color:#96a0b0;font-size:10px}
.app-launcher-footer-links{display:flex;align-items:center;gap:15px}
.app-launcher-footer form{margin:0}
.app-launcher-footer button,.app-launcher-footer a{padding:5px 0;border:0;background:none;color:#748096;font:inherit;text-decoration:none;cursor:pointer}
.app-launcher-footer button:hover,.app-launcher-footer a:hover{color:var(--portal-blue)}
@media(max-width:900px){.app-launcher-grid{grid-template-columns:repeat(6,minmax(0,1fr))}.app-launcher-page{padding-left:max(20px,env(safe-area-inset-left,0px));padding-right:max(20px,env(safe-area-inset-right,0px))}}
@media(max-width:620px){.app-launcher-page{padding-top:calc(14px + env(safe-area-inset-top,0px));padding-right:max(12px,env(safe-area-inset-right,0px));padding-left:max(12px,env(safe-area-inset-left,0px));padding-bottom:calc(20px + env(safe-area-inset-bottom,0px))}.app-launcher-head{gap:10px;margin-bottom:20px}.app-launcher-logo{width:42px;height:42px;flex-basis:42px;border-radius:13px}.app-launcher-head p{font-size:10.5px}.app-launcher-account{gap:5px;padding:6px 8px;max-width:122px;font-size:9px}.app-launcher-account .account-user{max-width:62px}.app-launcher-account .plan-pill{padding:4px 5px;font-size:8px}.app-launcher-grid{grid-template-columns:repeat(4,minmax(0,1fr));gap:4px 1px}.app-launcher-tile{min-height:102px;padding:9px 2px 8px;gap:7px;border-radius:14px}.app-launcher-icon{width:54px;height:54px;flex-basis:54px;border-radius:17px;font-size:27px}.app-launcher-name{font-size:11px;min-height:28px}.app-launcher-section-head{margin-bottom:7px}.app-launcher-section-head small{font-size:10px}.app-launcher-footer{align-items:flex-start;flex-direction:column;margin-top:16px}.app-launcher-footer-links{gap:13px}}
@media(max-width:360px){.app-launcher-account{max-width:106px}.app-launcher-account .account-user{max-width:48px}.app-launcher-icon{width:48px;height:48px;flex-basis:48px;border-radius:15px}.app-launcher-tile{min-height:96px}.app-launcher-name{font-size:10px}}
@media(prefers-reduced-motion:reduce){.app-launcher-tile{transition:none}}


/* v2.6.2 VISUAL ONLY — app gallery cards */
.app-launcher-page {
    background:
      radial-gradient(ellipse at 3% 4%, rgba(23,92,211,.13), transparent 35%),
      radial-gradient(ellipse at 98% 88%, rgba(110,86,207,.09), transparent 30%),
      linear-gradient(150deg,#f5f8fe 0%,#edf3fc 54%,#f7f9fd 100%) !important;
    padding-top: calc(26px + env(safe-area-inset-top,0px)) !important;
    padding-bottom: calc(28px + env(safe-area-inset-bottom,0px)) !important;
}
.app-launcher-shell {
    width: min(100%, 1120px) !important;
    padding: 25px 27px 17px;
    border: 1px solid rgba(255,255,255,.96);
    border-radius: 30px;
    background: rgba(255,255,255,.78);
    box-shadow: 0 25px 75px rgba(29,53,92,.09), 0 2px 8px rgba(29,53,92,.025);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
}
.app-launcher-head {
    gap: 15px !important;
    margin-bottom: 23px !important;
    padding: 0 0 19px;
    border-bottom: 1px solid #e8edf5;
}
.app-launcher-logo {
    flex-basis: 54px !important;
    width: 54px !important;
    height: 54px !important;
    border-radius: 18px !important;
    border-color: #e5ebf5 !important;
    box-shadow: 0 8px 22px rgba(23,92,211,.11) !important;
}
.app-launcher-head h1 { font-size: clamp(24px,3vw,31px) !important; font-weight: 850 !important; letter-spacing: -.9px !important; }
.app-launcher-head p { margin-top: 6px !important; color: #7f8ba0 !important; font-size: 12.5px !important; }
.app-launcher-account {
    padding: 8px 12px !important;
    border-color: #e1e8f3 !important;
    background: #fff !important;
    box-shadow: 0 3px 12px rgba(31,51,84,.035);
}
.app-launcher-account .plan-pill { background: linear-gradient(135deg,#e8f8ef,#dcf5e7) !important; color: #137a43 !important; }
.app-launcher-section-head { margin: 0 2px 14px !important; }
.app-launcher-section-head h2 { color: #52627a !important; font-size: 11px !important; letter-spacing: .11em !important; }
.app-launcher-section-head small { padding: 6px 9px; border: 1px solid #e5ebf4; border-radius: 999px; background: #fff; color: #8a96a8 !important; font-size: 10px !important; }
.app-launcher-grid { grid-template-columns: repeat(6,minmax(0,1fr)) !important; gap: 12px !important; }
.app-launcher-tile {
    min-height: 145px !important;
    padding: 16px 9px 13px !important;
    gap: 10px !important;
    border: 1px solid #e6ecf5 !important;
    border-radius: 21px !important;
    background: linear-gradient(180deg,#fff 0%,#fdfefe 100%) !important;
    box-shadow: 0 6px 18px rgba(28,48,80,.045) !important;
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease !important;
}
.app-launcher-tile:hover, .app-launcher-tile:focus-visible {
    transform: translateY(-3px);
    border-color: #bad0fb !important;
    background: #fff !important;
    box-shadow: 0 15px 30px rgba(23,92,211,.10) !important;
}
.app-launcher-tile:focus-visible { outline: 3px solid rgba(23,92,211,.22) !important; outline-offset: 2px; }
.app-launcher-tile:active { transform: scale(.98) !important; }
.app-launcher-icon {
    flex-basis: 62px !important;
    width: 62px !important;
    height: 62px !important;
    border-radius: 20px !important;
    border-color: rgba(221,230,244,.9) !important;
    box-shadow: 0 7px 17px rgba(28,52,91,.09) !important;
    font-size: 30px !important;
}
.app-launcher-name { color: #263650 !important; font-size: 12px !important; font-weight: 780 !important; line-height: 1.3 !important; min-height: 31px !important; }
.app-launcher-meta { color: #7c8ba1 !important; font-size: 9px !important; font-weight: 800 !important; }
.app-launcher-lock { width: 22px !important; height: 22px !important; font-size: 10px !important; }
.app-launcher-tile.is-locked { background: linear-gradient(180deg,#fffdf8,#fffaf0) !important; border-color: #f1e5c8 !important; }
.app-launcher-tile.is-locked .app-launcher-icon { box-shadow: 0 7px 17px rgba(134,96,16,.07) !important; }
.app-launcher-error, .app-launcher-notice { box-shadow: 0 4px 14px rgba(30,45,70,.035); }
.app-launcher-footer { margin-top: 22px !important; padding-top: 15px !important; border-top-color: #e6ecf4 !important; color: #9aa5b5 !important; }
.app-launcher-footer button, .app-launcher-footer a { min-height: 32px; padding: 7px 10px !important; border-radius: 9px; }
.app-launcher-footer button:hover, .app-launcher-footer a:hover { background: #edf4ff; text-decoration: none; }
@media(max-width:900px) {
    .app-launcher-shell { padding: 22px 20px 15px !important; }
    .app-launcher-grid { grid-template-columns: repeat(5,minmax(0,1fr)) !important; gap: 10px !important; }
    .app-launcher-tile { min-height: 139px !important; }
}
@media(max-width:620px) {
    .app-launcher-page { padding-top: calc(12px + env(safe-area-inset-top,0px)) !important; padding-right: max(10px,env(safe-area-inset-right,0px)) !important; padding-left: max(10px,env(safe-area-inset-left,0px)) !important; padding-bottom: calc(16px + env(safe-area-inset-bottom,0px)) !important; }
    .app-launcher-shell { padding: 17px 11px 10px !important; border-radius: 24px !important; }
    .app-launcher-head { gap: 9px !important; margin-bottom: 18px !important; padding-bottom: 15px !important; }
    .app-launcher-logo { flex-basis: 42px !important; width: 42px !important; height: 42px !important; border-radius: 14px !important; }
    .app-launcher-head h1 { font-size: 22px !important; letter-spacing: -.55px !important; }
    .app-launcher-head p { font-size: 10px !important; }
    .app-launcher-account { max-width: 118px !important; padding: 6px 7px !important; gap: 5px !important; font-size: 9px !important; }
    .app-launcher-account .account-user { max-width: 55px !important; }
    .app-launcher-account .plan-pill { padding: 4px 5px !important; font-size: 8px !important; }
    .app-launcher-section-head { margin-bottom: 11px !important; }
    .app-launcher-grid { grid-template-columns: repeat(4,minmax(0,1fr)) !important; gap: 8px 7px !important; }
    .app-launcher-tile { min-height: 119px !important; padding: 11px 3px 9px !important; gap: 7px !important; border-radius: 16px !important; }
    .app-launcher-icon { width: 48px !important; height: 48px !important; flex-basis: 48px !important; border-radius: 15px !important; font-size: 24px !important; }
    .app-launcher-name { font-size: 10.5px !important; min-height: 27px !important; line-height: 1.28 !important; }
    .app-launcher-meta { font-size: 8px !important; }
    .app-launcher-footer { gap: 8px !important; margin-top: 17px !important; font-size: 9px !important; }
    .app-launcher-footer-links { width: 100%; display: flex; flex-wrap: wrap; gap: 5px !important; }
    .app-launcher-footer button, .app-launcher-footer a { padding: 7px 8px !important; }
}
@media(max-width:360px) {
    .app-launcher-shell { padding-left: 8px !important; padding-right: 8px !important; }
    .app-launcher-grid { gap: 6px 5px !important; }
    .app-launcher-tile { min-height: 112px !important; padding-left: 2px !important; padding-right: 2px !important; }
    .app-launcher-icon { width: 44px !important; height: 44px !important; flex-basis: 44px !important; border-radius: 14px !important; }
    .app-launcher-name { font-size: 9.5px !important; }
}
@media(prefers-reduced-motion:reduce) { .app-launcher-tile { transition: none !important; } }

</style>
</head>
<body>
<main class="app-launcher-page">
  <div class="app-launcher-shell">
    <header class="app-launcher-head">
      <div class="app-launcher-logo"><img src="assets/icon.webp" alt="Catatan Keuangan"></div>
      <div class="app-launcher-head-copy">
        <h1>Daftar aplikasi</h1>
        <p>Pilih aplikasi untuk melanjutkan.</p>
      </div>
      <div class="app-launcher-account" title="Akun <?= h($user['username'] ?? '') ?>">
        <span class="account-user"><?= h($user['username'] ?? '') ?></span>
        <span class="plan-pill"><?= h($premium ? 'Premium' : 'Free') ?></span>
      </div>
    </header>
    <?php if (!empty($error)): ?><div class="app-launcher-error" role="alert"><?= h($error) ?></div><?php endif; ?>
    <?php if (!empty($notice)): ?><div class="app-launcher-notice" role="status"><?= h($notice) ?></div><?php endif; ?>

    <div class="app-launcher-section-head"><h2>Aplikasi Anda</h2><small><?= (int)$galleryCount ?> aplikasi tersedia</small></div>
    <section class="app-launcher-grid" aria-label="Daftar aplikasi">
      <?php foreach ($apps as $app): ?>
        <?php
          $appId = trim((string)($app['id'] ?? ''));
          $isBuiltin = $appId === 'main' || !empty($app['is_builtin']) || !empty($app['builtin']);
          $requiresPremium = !empty($app['requires_premium']) || !empty($app['premium_required']);
          $locked = !empty($app['locked']) || ($requiresPremium && !$premium);
          $icon = (string)($app['icon'] ?? '📱');
          $iconIsImage = !empty($app['is_image_icon']) || preg_match('#^https?://|^/#i', $icon) === 1;
          // Main always opens the real finance dashboard. External apps use the
          // route family that produced this catalogue, avoiding app-hub/app-portal 404s.
          if ($isBuiltin) $href = Url::to(['/site/index', 'app' => 'main']);
          elseif ($appHubSchema) $href = Url::to(['/app-hub/launch', 'id' => $appId]);
          else $href = Url::to(['/app-portal/launch', 'id' => $appId]);
        ?>
        <a class="app-launcher-tile <?= $locked ? 'is-locked' : '' ?>" href="<?= h($href) ?>" aria-label="<?= h($app['name'] ?? 'Aplikasi') ?><?= $locked ? ', khusus Premium' : '' ?>">
          <span class="app-launcher-icon" aria-hidden="true">
            <?php if ($iconIsImage): ?><img src="<?= h($icon) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php else: ?><?= h($icon) ?><?php endif; ?>
            <?php if ($locked): ?><span class="app-launcher-lock" aria-hidden="true">🔒</span><?php endif; ?>
          </span>
          <span class="app-launcher-name"><?= h($app['name'] ?? 'Aplikasi') ?></span>
          <span class="app-launcher-meta"><?= $locked ? 'Premium' : '&nbsp;' ?></span>
        </a>
      <?php endforeach; ?>
      <?php if ($isSuperAdmin): ?>
        <a class="app-launcher-tile" href="<?= h($manageUrl) ?>" aria-label="Manajemen Aplikasi">
          <span class="app-launcher-icon" aria-hidden="true">⚙️</span>
          <span class="app-launcher-name">Manajemen Aplikasi</span>
          <span class="app-launcher-meta">Admin</span>
        </a>
      <?php endif; ?>
    </section>

    <footer class="app-launcher-footer">
      <span>Catatan Keuangan · Satu akun untuk aplikasi yang terhubung</span>
      <div class="app-launcher-footer-links">
        <?php if ($isSuperAdmin): ?><a href="<?= h($manageUrl) ?>">Kelola aplikasi</a><?php endif; ?>
        <a href="privacy-policy.php">Kebijakan privasi</a>
        <form method="post" action="<?= h(Url::to(['/site/index'])) ?>"><?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?><input type="hidden" name="action" value="logout"><button type="submit">Logout</button></form>
      </div>
    </footer>
  </div>
</main>
</body>
</html>
