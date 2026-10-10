<?php
use yii\helpers\Url;
$apps = is_array($apps ?? null) ? $apps : [];
$assetVersion = (string)($assetVersion ?? '260');
$baseUrl = rtrim((string)(Yii::$app->params['appUrl'] ?: Yii::$app->request->hostInfo), '/');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#f6f8fc">
<title>Manajemen Aplikasi · Catatan Keuangan</title>
<style>
:root{--ink:#172033;--muted:#7e899c;--blue:#175cd3;--border:#e5eaf2;--panel:#fff;--bg:#f5f7fb;--danger:#c9362b}
*{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;color:var(--ink);background:var(--bg);-webkit-font-smoothing:antialiased}
body{min-height:100dvh;overflow-x:hidden;overflow-y:auto;padding:calc(18px + env(safe-area-inset-top,0px)) max(14px,env(safe-area-inset-right,0px)) calc(20px + env(safe-area-inset-bottom,0px)) max(14px,env(safe-area-inset-left,0px))}
.manager-shell{width:min(980px,100%);margin:0 auto}.manager-header{display:flex;align-items:center;gap:12px;margin:0 0 20px}.manager-header-copy{flex:1;min-width:0}.manager-header h1{font-size:clamp(22px,3vw,29px);letter-spacing:-.6px;margin:0;line-height:1.15}.manager-header p{margin:6px 0 0;color:var(--muted);font-size:12px;line-height:1.45}.manager-header-actions{display:flex;gap:8px;flex:0 0 auto}.manager-btn{appearance:none;min-height:40px;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 13px;border:1px solid var(--border);border-radius:12px;background:#fff;color:#344054;text-decoration:none;font-weight:700;font-size:12px;cursor:pointer;transition:background .15s,border-color .15s}.manager-btn:hover{background:#f6f8fb;border-color:#d6deeb}.manager-btn.primary{background:var(--blue);border-color:var(--blue);color:#fff}.manager-btn.primary:hover{background:#124cba}.manager-btn.danger{color:var(--danger);background:#fff8f7;border-color:#f5d7d4}.manager-alert{padding:11px 13px;border-radius:12px;font-size:12px;margin:0 0 13px;line-height:1.5}.manager-alert.error{color:#a92e25;background:#fff0ef;border:1px solid #ffd6d2}.manager-alert.success{color:#166c3b;background:#edf9f1;border:1px solid #c8ecd4}.manager-card{background:var(--panel);border:1px solid var(--border);border-radius:20px;box-shadow:0 12px 36px rgba(23,42,75,.045);overflow:hidden}.manager-card-head{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:17px 18px;border-bottom:1px solid #edf0f5}.manager-card-head h2{font-size:15px;margin:0}.manager-card-head p{font-size:11px;color:var(--muted);margin:5px 0 0}.app-list{display:flex;flex-direction:column}.app-row{display:grid;grid-template-columns:52px minmax(0,1fr) auto;gap:13px;align-items:center;padding:14px 17px;border-bottom:1px solid #f0f2f6}.app-row:last-child{border-bottom:0}.app-row-icon{width:48px;height:48px;border-radius:15px;background:#f1f5fb;border:1px solid #e7ecf4;display:grid;place-items:center;font-size:25px;overflow:hidden}.app-row-icon img{width:100%;height:100%;object-fit:cover}.app-row-main{min-width:0}.app-row-title{display:flex;align-items:center;flex-wrap:wrap;gap:7px;margin:0 0 4px;font-size:13px;font-weight:800}.app-row-url{color:#7e899c;font-size:11px;line-height:1.45;overflow-wrap:anywhere}.app-row-meta{display:flex;flex-wrap:wrap;gap:5px;margin-top:7px}.app-badge{padding:4px 7px;border-radius:999px;background:#f1f4f8;color:#66758b;font-size:9px;font-weight:800}.app-badge.active{background:#e8f8ee;color:#167447}.app-badge.off{background:#fff0ef;color:#b52d24}.app-badge.premium{background:#fff5d8;color:#8c5a00}.app-badge.sso{background:#eaf2ff;color:#175cd3}.app-row-actions{display:flex;gap:6px;align-items:center}.app-row-actions form{margin:0}.manager-footnote{padding:15px 18px;background:#fafbfd;border-top:1px solid #edf0f5;color:#8994a6;font-size:10px;line-height:1.55}
/* Modal: panel tidak memotong form. Hanya area body yang scroll; safe area kamera dan home-indicator dihormati. */
.app-editor-overlay[hidden]{display:none!important}.app-editor-overlay{position:fixed;z-index:2000;inset:0;display:grid;place-items:center;overflow:hidden;padding:calc(12px + env(safe-area-inset-top,0px)) max(12px,env(safe-area-inset-right,0px)) calc(12px + env(safe-area-inset-bottom,0px)) max(12px,env(safe-area-inset-left,0px));background:rgba(15,23,42,.54);backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px)}.app-editor-panel{width:min(620px,100%);height:min(88dvh,760px);max-height:calc(100dvh - env(safe-area-inset-top,0px) - env(safe-area-inset-bottom,0px) - 24px);min-height:min(64dvh,520px);display:flex;flex-direction:column;overflow:hidden;border:1px solid rgba(255,255,255,.65);border-radius:22px;background:#fff;box-shadow:0 30px 90px rgba(12,25,48,.28);overscroll-behavior:contain}.app-editor-head{display:flex;align-items:center;gap:12px;flex:0 0 auto;padding:17px 19px 14px;border-bottom:1px solid #edf0f5;background:#fff}.app-editor-head-copy{flex:1;min-width:0}.app-editor-head h2{margin:0;font-size:17px;letter-spacing:-.3px}.app-editor-head p{margin:5px 0 0;color:var(--muted);font-size:11px;line-height:1.4}.app-editor-close{width:36px;height:36px;flex:0 0 36px;display:grid;place-items:center;border:0;border-radius:12px;background:#f1f4f8;color:#344054;font-size:22px;cursor:pointer}.app-editor-form{display:flex;flex-direction:column;min-height:0;flex:1}.app-editor-body{min-height:0;min-width:0;flex:1 1 0;overflow-y:auto;overflow-x:hidden;overscroll-behavior:contain;touch-action:pan-y;-webkit-overflow-scrolling:touch;scrollbar-gutter:stable;padding:17px 19px calc(24px + env(safe-area-inset-bottom,0px))}.app-editor-fields{display:grid;grid-template-columns:1fr 1fr;gap:13px}.form-field{display:flex;flex-direction:column;gap:6px;min-width:0}.form-field.full{grid-column:1/-1}.form-field label,.field-label{font-size:11px;color:#536176;font-weight:800}.form-field input,.form-field textarea,.form-field select{width:100%;min-width:0;min-height:42px;padding:10px 11px;border:1px solid #dfe5ef;border-radius:11px;background:#fff;color:#263348;font:inherit;font-size:12px;outline:none}.form-field textarea{min-height:78px;resize:vertical;line-height:1.5}.form-field input:focus,.form-field textarea:focus,.form-field select:focus{border-color:#80a9ff;box-shadow:0 0 0 3px rgba(23,92,211,.09)}.field-help{color:#919bad;font-size:10px;line-height:1.5}.form-checks{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:16px}.form-check{display:flex;align-items:flex-start;gap:8px;padding:11px;border:1px solid #e6ebf3;border-radius:12px;background:#fafbfd;font-size:11px;color:#475467;line-height:1.35}.form-check input{margin:2px 0 0;accent-color:var(--blue);flex:0 0 auto}.app-editor-section{margin:18px 0 11px;padding-top:15px;border-top:1px solid #edf0f5;font-size:12px;font-weight:850;color:#344054}.app-editor-footer{display:flex;flex:0 0 auto;align-items:center;justify-content:flex-end;gap:8px;padding:12px 19px calc(12px + env(safe-area-inset-bottom,0px));border-top:1px solid #e9edf4;background:rgba(255,255,255,.97)}.sso-fields-note{padding:10px 11px;border-radius:10px;background:#f4f7fc;color:#78859a;font-size:10px;line-height:1.55}.builtin-note{padding:10px 11px;border-radius:10px;background:#edf4ff;color:#315c9e;font-size:10px;line-height:1.5}
@media(max-width:640px){.manager-header{align-items:flex-start;flex-wrap:wrap}.manager-header-actions{width:100%}.manager-header-actions .manager-btn{flex:1}.manager-card{border-radius:16px}.app-row{grid-template-columns:42px minmax(0,1fr);gap:10px;padding:12px}.app-row-icon{width:40px;height:40px;border-radius:13px;font-size:21px}.app-row-actions{grid-column:2;justify-content:flex-start;margin-top:1px}.app-row-actions .manager-btn{min-height:34px;padding:0 10px}.manager-card-head{padding:14px 13px}.manager-footnote{padding:12px 13px}.app-editor-overlay{place-items:end center;padding:calc(8px + env(safe-area-inset-top,0px)) max(0px,env(safe-area-inset-right,0px)) calc(0px + env(safe-area-inset-bottom,0px)) max(0px,env(safe-area-inset-left,0px))}.app-editor-panel{width:100%;height:calc(100dvh - env(safe-area-inset-top,0px) - env(safe-area-inset-bottom,0px) - 8px);max-height:calc(100dvh - env(safe-area-inset-top,0px) - env(safe-area-inset-bottom,0px) - 8px);min-height:0;border-radius:22px 22px 0 0;border-bottom:0}.app-editor-head{padding:15px 16px 12px}.app-editor-body{padding:15px 16px calc(20px + env(safe-area-inset-bottom,0px))}.app-editor-fields{grid-template-columns:1fr;gap:12px}.form-field.full{grid-column:auto}.form-checks{grid-template-columns:1fr}.app-editor-footer{padding:10px 16px calc(11px + env(safe-area-inset-bottom,0px))}.app-editor-footer .manager-btn{flex:1}}
@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;transition:none!important}}


/* v2.6.2 VISUAL ONLY — application management */
:root { --blue:#175cd3; --border:#e2e9f4; --panel:#fff; --bg:#f4f7fc; }
body {
    background:
      radial-gradient(ellipse at 5% 0%, rgba(23,92,211,.12), transparent 37%),
      radial-gradient(ellipse at 95% 100%, rgba(117,93,218,.08), transparent 34%),
      linear-gradient(145deg,#f7f9fe 0%,#edf3fc 56%,#f7f9fd 100%) !important;
}
.manager-shell { width:min(1040px,100%) !important; }
.manager-header {
    position:relative;
    isolation:isolate;
    overflow:hidden;
    padding:24px 25px !important;
    margin-bottom:17px !important;
    border:1px solid rgba(255,255,255,.97);
    border-radius:25px;
    background:rgba(255,255,255,.84);
    box-shadow:0 16px 42px rgba(28,48,80,.065);
}
.manager-header::after {
    content:"";
    position:absolute;
    z-index:-1;
    width:260px;
    height:260px;
    top:-165px;
    right:-65px;
    border-radius:50%;
    background:radial-gradient(circle,rgba(47,108,223,.12),rgba(47,108,223,0) 70%);
    pointer-events:none;
}
.manager-header-copy, .manager-header-actions { position:relative; z-index:1; }
.manager-header h1 { color:#17243a !important; font-size:clamp(25px,3vw,31px) !important; font-weight:850 !important; letter-spacing:-.9px !important; }
.manager-header p { margin-top:8px !important; max-width:620px; color:#76849b !important; font-size:12.5px !important; line-height:1.65 !important; }
.manager-header-actions { gap:9px !important; }
.manager-btn { min-height:42px !important; padding:0 15px !important; border-radius:13px !important; border-color:#e1e8f3 !important; font-size:12px !important; font-weight:800 !important; box-shadow:0 3px 10px rgba(25,45,76,.025); }
.manager-btn:hover { transform:translateY(-1px); background:#f7faff !important; border-color:#cbdafa !important; box-shadow:0 7px 16px rgba(28,58,105,.075); }
.manager-btn.primary { border-color:#175cd3 !important; background:linear-gradient(135deg,#2870e8,#1752bf) !important; box-shadow:0 9px 19px rgba(23,92,211,.19) !important; }
.manager-btn.primary:hover { filter:brightness(1.04); }
.manager-btn.danger { border-color:#f4d7d5 !important; background:#fff8f7 !important; }
.manager-alert { padding:13px 15px !important; border-radius:14px !important; font-size:12px !important; box-shadow:0 4px 14px rgba(30,45,70,.035); }
.manager-card { border-color:#e3eaf4 !important; border-radius:23px !important; box-shadow:0 17px 45px rgba(22,45,83,.065) !important; }
.manager-card-head { padding:20px 22px !important; background:linear-gradient(180deg,#fff,#fbfcff); border-bottom-color:#e9eef6 !important; }
.manager-card-head h2 { font-size:16px !important; letter-spacing:-.25px; color:#223149; font-weight:850; }
.manager-card-head p { margin-top:6px !important; font-size:11.5px !important; color:#8491a6 !important; }
.app-row { padding:16px 20px !important; gap:15px !important; border-bottom-color:#edf1f7 !important; transition:background .16s ease; }
.app-row:hover { background:linear-gradient(90deg,#f7faff,#fff 82%); }
.app-row-icon { width:52px !important; height:52px !important; border-radius:17px !important; border-color:#e5ebf5 !important; background:linear-gradient(145deg,#eff5ff,#fff) !important; box-shadow:0 5px 14px rgba(31,56,98,.06); font-size:27px !important; }
.app-row-title { color:#263650 !important; font-size:13.5px !important; gap:8px !important; }
.app-row-url { color:#8793a7 !important; font-size:11px !important; }
.app-row-meta { gap:6px !important; margin-top:8px !important; }
.app-badge { padding:5px 8px !important; border:1px solid rgba(105,122,150,.08); font-size:9px !important; letter-spacing:.1px; }
.app-row-actions { gap:7px !important; }
.manager-footnote { padding:16px 21px !important; background:#f9fbfe !important; border-top-color:#e9eef6 !important; color:#7f8da2 !important; font-size:10.5px !important; }
.app-editor-overlay { background:rgba(13,27,50,.58) !important; backdrop-filter:blur(8px) !important; -webkit-backdrop-filter:blur(8px) !important; }
.app-editor-panel { border:1px solid rgba(255,255,255,.82) !important; border-radius:25px !important; box-shadow:0 34px 100px rgba(5,17,38,.34) !important; }
.app-editor-head { padding:20px 22px 16px !important; background:linear-gradient(180deg,#fff,#fbfcff) !important; border-bottom-color:#e8edf5 !important; }
.app-editor-head h2 { color:#1d2b42 !important; font-size:18px !important; font-weight:850 !important; }
.app-editor-head p { color:#8290a5 !important; font-size:11.5px !important; }
.app-editor-close { width:38px !important; height:38px !important; flex-basis:38px !important; border:1px solid #e5eaf2 !important; border-radius:13px !important; background:#f7f9fc !important; transition:background .15s ease,transform .15s ease; }
.app-editor-close:hover { background:#eaf1ff !important; transform:rotate(4deg); }
.app-editor-body { padding:20px 22px calc(24px + env(safe-area-inset-bottom,0px)) !important; }
.form-field { gap:7px !important; }
.form-field label, .field-label { color:#4c5b72 !important; font-size:11.5px !important; }
.form-field input, .form-field textarea, .form-field select { min-height:45px !important; border-color:#dfe7f2 !important; border-radius:13px !important; background:#fbfcff !important; padding:11px 12px !important; font-size:12.5px !important; transition:border-color .15s ease,box-shadow .15s ease,background .15s ease; }
.form-field input:focus, .form-field textarea:focus, .form-field select:focus { background:#fff !important; border-color:#82a8f4 !important; box-shadow:0 0 0 4px rgba(23,92,211,.09) !important; }
.field-help { color:#8c99ac !important; font-size:10.5px !important; }
.form-check { border-color:#e3eaf4 !important; border-radius:14px !important; background:#f9fbff !important; padding:13px !important; }
.form-check:has(input:checked) { border-color:#b8cef7 !important; background:#f1f6ff !important; }
.form-check b { color:#34435a; }
.app-editor-section { color:#344763 !important; font-size:12.5px !important; }
.app-editor-footer { padding:13px 22px calc(13px + env(safe-area-inset-bottom,0px)) !important; background:rgba(255,255,255,.98) !important; border-top-color:#e6ecf5 !important; }
.sso-fields-note, .builtin-note { padding:12px 13px !important; border-radius:13px !important; font-size:10.5px !important; }
@media(max-width:640px) {
    body { padding-top:calc(10px + env(safe-area-inset-top,0px)) !important; padding-left:max(10px,env(safe-area-inset-left,0px)) !important; padding-right:max(10px,env(safe-area-inset-right,0px)) !important; }
    .manager-header { padding:19px 16px !important; border-radius:21px !important; gap:14px !important; }
    .manager-header h1 { font-size:24px !important; }
    .manager-header p { font-size:11.5px !important; }
    .manager-header-actions { gap:7px !important; }
    .manager-header-actions .manager-btn { padding:0 9px !important; min-width:0; font-size:10.5px !important; }
    .manager-card { border-radius:19px !important; }
    .manager-card-head { padding:16px 14px !important; }
    .app-row { padding:13px 12px !important; gap:10px !important; }
    .app-row-icon { width:43px !important; height:43px !important; border-radius:14px !important; font-size:23px !important; }
    .app-row-title { font-size:12.5px !important; }
    .app-row-url { font-size:10px !important; }
    .app-row-actions .manager-btn { min-height:35px !important; padding:0 11px !important; }
    .app-editor-panel { border-radius:23px 23px 0 0 !important; }
    .app-editor-head { padding:16px 15px 13px !important; }
    .app-editor-body { padding:15px 15px calc(20px + env(safe-area-inset-bottom,0px)) !important; }
    .app-editor-footer { padding:11px 15px calc(11px + env(safe-area-inset-bottom,0px)) !important; }
}
@media(prefers-reduced-motion:reduce) { .manager-btn, .app-editor-close { transition:none !important; } }

</style>
</head>
<body>
<main class="manager-shell">
  <header class="manager-header">
    <div class="manager-header-copy">
      <h1>Manajemen Aplikasi</h1>
      <p>Atur aplikasi yang tersedia di galeri, ikon, tautan, akses Premium, dan SSO lintas server.</p>
    </div>
    <div class="manager-header-actions">
      <a class="manager-btn" href="<?= h(Url::to(['/app-portal/index'])) ?>">← Galeri aplikasi</a>
      <button class="manager-btn primary" type="button" id="createAppButton">＋ Tambah aplikasi</button>
    </div>
  </header>
  <?php if (!empty($error)): ?><div class="manager-alert error" role="alert"><?= h($error) ?></div><?php endif; ?>
  <?php if (!empty($notice)): ?><div class="manager-alert success" role="status"><?= h($notice) ?></div><?php endif; ?>
  <section class="manager-card">
    <div class="manager-card-head"><div><h2>Daftar aplikasi</h2><p><?= count($apps) ?> aplikasi terdaftar di portal ini.</p></div></div>
    <div class="app-list">
      <?php foreach ($apps as $app): ?>
        <?php
          $payload = [
            'id'=>(string)($app['id']??''), 'name'=>(string)($app['name']??''), 'icon'=>(string)($app['icon']??'📱'),
            'description'=>(string)($app['description']??''), 'url'=>(string)($app['url']??''),
            'sso_endpoint'=>(string)($app['sso_endpoint']??''), 'requires_premium'=>!empty($app['requires_premium']),
            'enabled'=>!empty($app['enabled']), 'builtin'=>(string)($app['builtin']??''),
            'has_sso'=>!empty($app['has_sso']), 'sort'=>(int)($app['sort']??100),
          ];
          $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
          $icon = (string)($app['icon']??'📱');
          $isImage = preg_match('#^https://|^/#i', $icon) === 1;
        ?>
        <article class="app-row">
          <div class="app-row-icon"><?php if ($isImage): ?><img src="<?= h($icon) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php else: ?><?= h($icon) ?><?php endif; ?></div>
          <div class="app-row-main">
            <div class="app-row-title"><?= h($app['name']??'') ?><?php if (!empty($app['builtin'])): ?><span class="app-badge">Aplikasi inti</span><?php endif; ?></div>
            <div class="app-row-url"><?= h(!empty($app['builtin']) ? 'Aplikasi Catatan Keuangan utama' : ($app['url']??'')) ?></div>
            <div class="app-row-meta">
              <span class="app-badge <?= !empty($app['enabled'])?'active':'off' ?>"><?= !empty($app['enabled'])?'Aktif':'Nonaktif' ?></span>
              <?php if (!empty($app['requires_premium'])): ?><span class="app-badge premium">Premium</span><?php else: ?><span class="app-badge">Gratis</span><?php endif; ?>
              <?php if (!empty($app['has_sso'])): ?><span class="app-badge sso">SSO aktif</span><?php elseif (empty($app['builtin'])): ?><span class="app-badge">Login aplikasi terpisah</span><?php endif; ?>
            </div>
          </div>
          <div class="app-row-actions">
            <button type="button" class="manager-btn" data-edit-app="<?= h($encoded ?: '{}') ?>">Edit</button>
            <?php if (empty($app['builtin'])): ?>
              <form method="post" action="<?= h(Url::to(['/app-portal/delete','id'=>(string)$app['id']])) ?>" onsubmit="return confirm('Hapus aplikasi <?= h($app['name']??'') ?> dari galeri?')">
                <?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <button type="submit" class="manager-btn danger">Hapus</button>
              </form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <div class="manager-footnote">Token SSO disimpan terenkripsi dan tidak pernah ditampilkan kembali pada form edit. Untuk aplikasi di server berbeda, endpoint aplikasi tujuan juga harus memvalidasi token SSO dan masa berlaku token.</div>
  </section>
</main>

<div class="app-editor-overlay" id="appEditorOverlay" hidden>
  <section class="app-editor-panel" role="dialog" aria-modal="true" aria-labelledby="appEditorTitle" aria-describedby="appEditorDescription">
    <header class="app-editor-head">
      <div class="app-editor-head-copy"><h2 id="appEditorTitle">Tambah aplikasi</h2><p id="appEditorDescription">Lengkapi informasi yang tampil di galeri aplikasi.</p></div>
      <button class="app-editor-close" type="button" id="closeAppEditor" aria-label="Tutup editor">×</button>
    </header>
    <form class="app-editor-form" id="appEditorForm" method="post" action="<?= h(Url::to(['/app-portal/save'])) ?>" autocomplete="off">
      <?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
      <input type="hidden" name="id" id="appEditorId" value="">
      <div class="app-editor-body" id="appEditorScrollArea">
        <div class="app-editor-fields">
          <div class="form-field"><label for="appEditorName">Nama aplikasi</label><input id="appEditorName" name="name" maxlength="70" required placeholder="Contoh: Inventaris Kantor"></div>
          <div class="form-field"><label for="appEditorIcon">Icon / URL gambar</label><input id="appEditorIcon" name="icon" maxlength="240" required placeholder="📦 atau https://.../icon.png"><small class="field-help">Gunakan emoji sederhana atau URL gambar HTTPS.</small></div>
          <div class="form-field full" id="appEditorUrlField"><label for="appEditorUrl">Link aplikasi</label><input id="appEditorUrl" name="url" type="url" maxlength="2048" placeholder="https://aplikasi-anda.example/" autocomplete="url"><small class="field-help" id="appEditorUrlHelp">Harus HTTPS. Untuk localhost pengembangan, HTTP diperbolehkan.</small></div>
          <div class="form-field full"><label for="appEditorDescriptionText">Deskripsi singkat (opsional)</label><textarea id="appEditorDescriptionText" name="description" maxlength="180" placeholder="Penjelasan singkat fungsi aplikasi."></textarea></div>
          <div class="form-field"><label for="appEditorSort">Urutan tampil</label><input id="appEditorSort" type="number" name="sort" min="1" max="9999" value="100"></div>
          <div class="form-field"><label for="appEditorKind">Jenis aplikasi</label><select id="appEditorKind" disabled><option value="external">Aplikasi eksternal</option><option value="main">Catatan Keuangan utama</option></select><small class="field-help">Jenis aplikasi inti tidak dapat diubah menjadi eksternal.</small></div>
        </div>
        <div class="form-checks">
          <label class="form-check"><input type="checkbox" name="enabled" id="appEditorEnabled" value="1" checked><span><b>Tampilkan di galeri</b><br>Aplikasi dapat dipilih pengguna.</span></label>
          <label class="form-check"><input type="checkbox" name="requires_premium" id="appEditorPremium" value="1"><span><b>Khusus Premium</b><br>Pengguna Free tidak dapat membukanya.</span></label>
        </div>
        <div id="appEditorBuiltinNote" class="builtin-note" hidden style="margin-top:14px">Aplikasi inti memakai rute internal Catatan Keuangan; link tidak perlu diisi.</div>
        <div id="appEditorSsoFields">
          <div class="app-editor-section">SSO lintas server (opsional)</div>
          <div class="app-editor-fields">
            <div class="form-field full"><label for="appEditorSsoEndpoint">Endpoint penerima SSO</label><input id="appEditorSsoEndpoint" type="url" name="sso_endpoint" maxlength="2048" placeholder="https://aplikasi-anda.example/sso/callback"><small class="field-help">Sistem mengirim token singkat melalui POST. Endpoint tujuan wajib memvalidasi token sebelum membuat sesi login.</small></div>
            <div class="form-field full"><label for="appEditorSsoSecret">Secret bersama untuk SSO</label><input id="appEditorSsoSecret" type="password" name="sso_secret" minlength="32" maxlength="512" autocomplete="new-password" placeholder="Kosongkan untuk mempertahankan secret sekarang"><small class="field-help" id="appEditorSsoSecretHelp">Gunakan secret acak minimal 32 karakter dan simpan secret yang sama di aplikasi tujuan. Secret lama tidak ditampilkan.</small></div>
            <label class="form-check full" id="appEditorRemoveSsoWrap"><input type="checkbox" name="remove_sso_secret" id="appEditorRemoveSso" value="1"><span><b>Nonaktifkan SSO untuk aplikasi ini</b><br>Pengguna akan membuka link biasa dan login di aplikasi tujuan.</span></label>
          </div>
          <div class="sso-fields-note" style="margin-top:12px">Token yang diterbitkan berlaku 90 detik. Aplikasi tujuan perlu memeriksa signature HMAC-SHA256, issuer, audience, waktu kedaluwarsa, serta mencegah penggunaan ulang nilai <code>jti</code>. Menambahkan secret di portal saja tidak otomatis mengubah sistem login aplikasi tujuan.</div>
        </div>
      </div>
      <footer class="app-editor-footer"><button type="button" class="manager-btn" id="cancelAppEditor">Batal</button><button type="submit" class="manager-btn primary" id="saveAppEditor">Simpan aplikasi</button></footer>
    </form>
  </section>
</div>
<script>
(function(){
  const overlay=document.getElementById('appEditorOverlay');
  const form=document.getElementById('appEditorForm');
  const $=id=>document.getElementById(id);
  const fields={id:$('appEditorId'),name:$('appEditorName'),icon:$('appEditorIcon'),url:$('appEditorUrl'),description:$('appEditorDescriptionText'),sort:$('appEditorSort'),enabled:$('appEditorEnabled'),premium:$('appEditorPremium'),kind:$('appEditorKind'),ssoEndpoint:$('appEditorSsoEndpoint'),ssoSecret:$('appEditorSsoSecret'),removeSso:$('appEditorRemoveSso')};
  let opener=null;
  let previousBodyOverflow='';
  let previousHtmlOverflow='';
  function applyKind(kind){const builtin=kind==='main';fields.kind.value=builtin?'main':'external';$('appEditorUrlField').hidden=builtin;$('appEditorBuiltinNote').hidden=!builtin;$('appEditorSsoFields').hidden=builtin;fields.url.required=!builtin;fields.url.disabled=builtin;fields.url.value=builtin?'':fields.url.value;}
  function openEditor(app,source){
    opener=source||document.activeElement;
    app=app||{};
    fields.id.value=app.id||'';fields.name.value=app.name||'';fields.icon.value=app.icon||'📱';fields.url.value=app.url||'';fields.description.value=app.description||'';fields.sort.value=app.sort||100;fields.enabled.checked=app.id?!!app.enabled:true;fields.premium.checked=!!app.requires_premium;fields.ssoEndpoint.value=app.sso_endpoint||'';fields.ssoSecret.value='';fields.removeSso.checked=false;
    const kind=app.builtin||'external';applyKind(kind);
    $('appEditorTitle').textContent=app.id?'Edit aplikasi':'Tambah aplikasi';
    $('appEditorDescription').textContent=app.id?'Ubah ikon, tautan, dan akses aplikasi.':'Lengkapi informasi aplikasi baru untuk galeri.';
    $('appEditorSsoSecretHelp').textContent=app.has_sso?'SSO sudah aktif. Isi secret baru hanya jika ingin merotasi; kosongkan untuk mempertahankan secret sekarang.':'Gunakan secret acak minimal 32 karakter dan simpan secret yang sama di aplikasi tujuan.';
    $('appEditorRemoveSsoWrap').hidden=!app.has_sso;
    previousBodyOverflow=document.body.style.overflow;previousHtmlOverflow=document.documentElement.style.overflow;
    overlay.hidden=false;document.body.style.overflow='hidden';document.documentElement.style.overflow='hidden';
    $('appEditorScrollArea').scrollTop=0;
    requestAnimationFrame(()=>fields.name.focus({preventScroll:true}));
  }
  function closeEditor(){overlay.hidden=true;document.body.style.overflow=previousBodyOverflow;document.documentElement.style.overflow=previousHtmlOverflow;if(opener&&typeof opener.focus==='function')opener.focus({preventScroll:true});}
  $('createAppButton').addEventListener('click',()=>openEditor(null,$('createAppButton')));
  document.querySelectorAll('[data-edit-app]').forEach(button=>button.addEventListener('click',()=>{try{openEditor(JSON.parse(button.dataset.editApp||'{}'),button)}catch(e){alert('Data aplikasi tidak dapat dibaca. Muat ulang halaman dan coba kembali.')}}));
  $('closeAppEditor').addEventListener('click',closeEditor);$('cancelAppEditor').addEventListener('click',closeEditor);
  overlay.addEventListener('click',event=>{if(event.target===overlay)closeEditor()});
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!overlay.hidden)closeEditor()});
  form.addEventListener('submit',event=>{if(!form.reportValidity()){event.preventDefault();return;}const btn=$('saveAppEditor');btn.disabled=true;btn.textContent='Menyimpan…';});
})();
</script>
</body>
</html>
