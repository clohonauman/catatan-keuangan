<?php
require_once __DIR__.'/../../legacy/faq_helper.php';
$faqCategories = faqCategoryLabels();
$faqEntries = faqKnowledgeBase();
$publicBase=rtrim((string)(Yii::$app->params['appUrl'] ?: Yii::$app->request->hostInfo),'/');
if (!function_exists('ck_icon')) {
    function ck_icon(string $name): string {
        $icons = [
            'crown' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 18h16"/><path d="m5 18 1.5-9 5 4 4-7 4 7 2-4 1 9"/></svg>',
            'wallet' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5h15a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-12a2 2 0 0 1 2-2h12"/><path d="M16 12h5"/><path d="M17.5 12h.01"/></svg>',
            'target' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><path d="M12 2v2M22 12h-2M12 22v-2M2 12h2"/></svg>',
            'receipt' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10v18l-2-1.5L12 21l-3-1.5L7 21V3Z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
            'repeat' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 2l4 4-4 4"/><path d="M3 11V9a3 3 0 0 1 3-3h15"/><path d="M7 22l-4-4 4-4"/><path d="M21 13v2a3 3 0 0 1-3 3H3"/></svg>',
            'flag' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 21V4"/><path d="M5 5h11l-1.5 3L16 11H5"/></svg>',
            'chart' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19h16"/><path d="M7 16V9"/><path d="M12 16V5"/><path d="M17 16v-4"/></svg>',
            'cloud' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 18a4 4 0 1 1 .6-7.96A5.5 5.5 0 0 1 18 12a3.5 3.5 0 1 1 0 7H7Z"/></svg>',
            'lock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 1 1 8 0v3"/></svg>',
            'trash' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 10v6M14 10v6"/></svg>',
            'settings' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-.4-1.1 1.7 1.7 0 0 0-1-.6 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H2.8a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.1-.4 1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V2.8a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 .4 1.1 1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.18.31.45.55.77.69.32.14.67.21 1.03.21h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.03.21c-.32.14-.59.38-.77.69Z"/></svg>',
            'logout' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M20 21H12a2 2 0 0 1-2-2v-2"/><path d="M20 3H12a2 2 0 0 0-2 2v2"/></svg>',
            'circle-dollar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v10"/><path d="M15 9.5c0-1.1-1.34-2-3-2s-3 .9-3 2 1.34 2 3 2 3 .9 3 2-1.34 2-3 2-3-.9-3-2"/></svg>',
            'income' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 17 17 7"/><path d="M10 7h7v7"/></svg>',
            'expense' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7 17 17"/><path d="M10 17h7v-7"/></svg>',
            'shield-check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6l-7-3Z"/><path d="m9.5 12 1.8 1.8 3.7-3.8"/></svg>',
            'sparkles' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 1.6 4.4L18 9l-4.4 1.6L12 15l-1.6-4.4L6 9l4.4-1.6L12 3Z"/><path d="M19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8L19 16Z"/><path d="M5 14l.7 1.8L7.5 16l-1.8.7L5 18.5l-.7-1.8L2.5 16l1.8-.2L5 14Z"/></svg>',
            'image' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.3"/><path d="m21 16-5.2-5.2a1.5 1.5 0 0 0-2.1 0L7 17"/></svg>',
            'camera' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h3l1.5-2h7L17 8h3a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2Z"/><circle cx="12" cy="14" r="3.5"/></svg>',
            'send' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12 20 4l-4 16-4.5-6L4 12Z"/><path d="M20 4 11.5 14"/></svg>',
            'mail' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>',
            'at' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M16 16.2c-1 .7-2.2 1.1-3.5 1.1-3 0-5.5-2.2-5.5-5.2S9.5 7 12.5 7c2.7 0 4.8 1.9 4.8 4.4v3.1c0 1 .8 1.8 1.8 1.8"/><path d="M14.6 12a2.6 2.6 0 1 1-5.2 0 2.6 2.6 0 0 1 5.2 0Z"/></svg>',
            'fingerprint' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 11a4 4 0 1 1 8 0v1"/><path d="M6 11a6 6 0 1 1 12 0v2"/><path d="M4 12a8 8 0 1 1 16 0v2"/><path d="M10 16v1a2 2 0 0 0 4 0v-4"/><path d="M8 16v1a4 4 0 0 0 8 0v-3"/><path d="M6 16v1a6 6 0 0 0 12 0v-1"/></svg>',
            'key' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="15" r="4"/><path d="M12 15h9"/><path d="M18 12v6"/><path d="M21 12v3"/></svg>',
            'hash' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3 7 21M17 3l-2 18M4 9h16M3 15h16"/></svg>',
            'smartphone' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 18h2"/></svg>',
            'tablet' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M11.5 17h1"/></svg>',
            'desktop' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8"/><path d="M12 16v4"/></svg>',
            'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4 10-10"/></svg>',
            'credit-card' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/><path d="M7 15h3"/></svg>',
            'utensils' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3v8"/><path d="M4 3v5a2 2 0 0 0 4 0V3"/><path d="M10 3v18"/><path d="M16 3v8"/><path d="M16 7h4"/><path d="M20 3v18"/></svg>',
            'fuel' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5h7a2 2 0 0 1 2 2v12H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/><path d="M16 9h2l2 2v6a2 2 0 0 1-4 0v-3"/><path d="M9 9h4"/></svg>',
            'cart' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/><path d="M3 4h2l2.4 10.2A2 2 0 0 0 9.35 16H18a2 2 0 0 0 1.94-1.5L21 8H7"/></svg>',
            'scooter' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="7" cy="17" r="2.5"/><circle cx="17" cy="17" r="2.5"/><path d="M9.5 17h5l2.2-7H11l-1 3H7"/><path d="M15 6h3l2 4"/></svg>',
            'swap' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h11"/><path d="m14 4 4 3-4 3"/><path d="M17 17H6"/><path d="m10 14-4 3 4 3"/></svg>',
            'bolt' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 5 14h5l-1 8 8-12h-5l1-8Z"/></svg>',
            'database' => '<svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg>',
            'bell' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>',
            'ticket' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7a2 2 0 0 0 2-2h12a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H6a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4V7Z"/><path d="M12 6v12"/></svg>',
            'landmark' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 9 9-5 9 5"/><path d="M5 10h14"/><path d="M7 10v7M12 10v7M17 10v7"/><path d="M4 20h16M3 17h18"/></svg>',
            'users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'search' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
            'filter' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16"/><path d="M7 12h10"/><path d="M10 18h4"/></svg>',
        ];
        return $icons[$name] ?? $icons['wallet'];
    }
}
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#175cd3">
    <meta name="csrf-token" content="<?= h(Yii::$app->request->csrfToken) ?>">
    <meta name="csrf-param" content="<?= h(Yii::$app->request->csrfParam) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Catatan Keuangan">
    <title><?= h(APP_NAME) ?></title>
    <link rel="icon" type="image/webp" href="assets/icon.webp">
    <link rel="shortcut icon" type="image/webp" href="assets/icon.webp">
    <link rel="apple-touch-icon" href="assets/icon.webp">
    <link rel="manifest" href="manifest.json">
    <link rel="stylesheet" href="assets/style.css?v=<?= h($assetVersion) ?>">

    <style id="wallet-balance-ui-v11">
    /* ===== v11 - Rincian saldo: clean wallet sheet ===== */
    #walletBalanceModal.wallet-balance-dialog {
        border: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        width: min(94vw, 430px) !important;
        max-width: 430px !important;
        border-radius: 28px !important;
        overflow: visible !important;
    }

    #walletBalanceModal::backdrop {
        background: rgba(15, 23, 42, .58) !important;
        backdrop-filter: blur(6px) !important;
        -webkit-backdrop-filter: blur(6px) !important;
    }

    #walletBalanceModal .wallet-balance-card {
        position: relative;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden;
        border: 1px solid rgba(226, 232, 240, .92);
        border-radius: 28px !important;
        background: #fff !important;
        box-shadow: 0 28px 80px rgba(15, 23, 42, .28) !important;
    }

    #walletBalanceModal .wallet-balance-handle {
        display: none
    }

    #walletBalanceModal .wallet-balance-head {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 20px 20px 14px;
        margin: 0 !important;
    }

    #walletBalanceModal .wallet-balance-head-icon {
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        display: grid;
        place-items: center;
        border-radius: 14px;
        background: #edf4ff;
        color: #175cd3;
    }

    #walletBalanceModal .wallet-balance-head-icon svg {
        width: 21px;
        height: 21px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.9;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    #walletBalanceModal .wallet-balance-title {
        min-width: 0;
        flex: 1
    }

    #walletBalanceModal .wallet-balance-title h3 {
        margin: 0 !important;
        color: #101828;
        font-size: 19px;
        line-height: 1.2;
        letter-spacing: -.35px;
    }

    #walletBalanceModal .wallet-balance-title p {
        margin: 4px 0 0 !important;
        color: #98a2b3 !important;
        font-size: 11px !important;
        line-height: 1.35;
    }

    #walletBalanceModal #closeWalletBalanceModal {
        width: 36px !important;
        height: 36px !important;
        flex: 0 0 36px;
        padding: 0 !important;
        display: grid;
        place-items: center;
        border: 1px solid #e7ebf1 !important;
        border-radius: 12px !important;
        background: #f8fafc !important;
        color: #344054 !important;
        font-size: 22px !important;
        line-height: 1 !important;
        box-shadow: none !important;
    }

    #walletBalanceModal #closeWalletBalanceModal:active {
        transform: scale(.94)
    }

    #walletBalanceModal .wallet-balance-body {
        padding: 0 20px 20px
    }

    #walletBalanceModal .wallet-balance-total {
        position: relative;
        overflow: hidden;
        margin: 0 0 18px !important;
        padding: 18px 18px 17px !important;
        border: 0 !important;
        border-radius: 20px !important;
        background: linear-gradient(135deg, #2f6ee5 0%, #1754c4 58%, #10479f 100%) !important;
        box-shadow: 0 14px 28px rgba(23, 92, 211, .22) !important;
        color: #fff;
    }

    #walletBalanceModal .wallet-balance-total::after {
        content: "";
        position: absolute;
        width: 130px;
        height: 130px;
        right: -46px;
        top: -60px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .10);
    }

    #walletBalanceModal .wallet-balance-total::before {
        content: "";
        position: absolute;
        width: 72px;
        height: 72px;
        right: 36px;
        bottom: -49px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .07);
    }

    #walletBalanceModal .wallet-balance-total-label {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 6px;
    }

    #walletBalanceModal .wallet-balance-total-label small {
        margin: 0 !important;
        color: rgba(255, 255, 255, .76) !important;
        font-size: 11px !important;
        font-weight: 700;
    }

    #walletBalanceModal .wallet-balance-total-badge {
        padding: 4px 8px;
        border: 1px solid rgba(255, 255, 255, .18);
        border-radius: 999px;
        background: rgba(255, 255, 255, .10);
        color: rgba(255, 255, 255, .9);
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .35px;
    }

    #walletBalanceModal .wallet-balance-total strong {
        position: relative;
        z-index: 1;
        display: block;
        margin: 0 !important;
        color: #fff !important;
        font-size: 30px !important;
        line-height: 1.12 !important;
        letter-spacing: -.85px;
    }

    #walletBalanceModal .wallet-balance-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin: 0 2px 9px;
    }

    #walletBalanceModal .wallet-balance-section-head b {
        color: #344054;
        font-size: 12px;
        letter-spacing: -.1px;
    }

    #walletBalanceModal .wallet-balance-section-head span {
        color: #98a2b3;
        font-size: 10px;
    }

    #walletBalanceModal .wallet-balance-list {
        display: grid !important;
        gap: 8px !important;
        max-height: min(46vh, 350px) !important;
        overflow: auto !important;
        padding: 1px 1px 2px !important;
        scrollbar-width: none;
    }

    #walletBalanceModal .wallet-balance-list::-webkit-scrollbar {
        display: none
    }

    #walletBalanceModal .wallet-balance-row {
        display: grid !important;
        grid-template-columns: 42px minmax(0, 1fr) auto !important;
        align-items: center !important;
        gap: 11px !important;
        min-height: 64px;
        padding: 10px 12px !important;
        border: 1px solid #e9edf3 !important;
        border-radius: 17px !important;
        background: #fff !important;
        box-shadow: 0 3px 10px rgba(16, 24, 40, .025) !important;
    }

    #walletBalanceModal .wallet-balance-row:hover {
        border-color: #dbe6f7 !important;
        background: #fbfdff !important;
    }

    #walletBalanceModal .wallet-balance-icon {
        width: 42px !important;
        height: 42px !important;
        display: grid !important;
        place-items: center !important;
        border-radius: 14px !important;
        background: #f0f5ff !important;
        font-size: 19px !important;
    }

    #walletBalanceModal .wallet-balance-name {
        min-width: 0 !important
    }

    #walletBalanceModal .wallet-balance-name b {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #101828 !important;
        font-size: 14px !important;
        line-height: 1.25;
    }

    #walletBalanceModal .wallet-balance-name small {
        display: block;
        margin-top: 3px !important;
        color: #98a2b3 !important;
        font-size: 10px !important;
        line-height: 1.2;
    }

    #walletBalanceModal .wallet-balance-row>strong {
        max-width: 145px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap !important;
        color: #101828 !important;
        font-size: 13px !important;
        font-weight: 800;
        letter-spacing: -.15px;
    }

    #walletBalanceModal .wallet-balance-footnote {
        display: flex;
        align-items: flex-start;
        gap: 7px;
        margin: 12px 2px 0;
        color: #98a2b3;
        font-size: 9.5px;
        line-height: 1.4;
    }

    #walletBalanceModal .wallet-balance-footnote svg {
        width: 13px;
        height: 13px;
        flex: 0 0 13px;
        margin-top: 1px;
        fill: none;
        stroke: #667085;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    @media(max-width:760px) {
        #walletBalanceModal.wallet-balance-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: auto 0 0 !important;
            border-radius: 26px 26px 0 0 !important;
        }

        #walletBalanceModal .wallet-balance-card {
            border-width: 1px 0 0 !important;
            border-radius: 26px 26px 0 0 !important;
            box-shadow: 0 -18px 55px rgba(15, 23, 42, .22) !important;
        }

        #walletBalanceModal .wallet-balance-handle {
            display: block;
            width: 38px;
            height: 4px;
            margin: 9px auto 0;
            border-radius: 999px;
            background: #d7dde6;
        }

        #walletBalanceModal .wallet-balance-head {
            padding: 12px 17px 12px
        }

        #walletBalanceModal .wallet-balance-head-icon {
            width: 38px;
            height: 38px;
            flex-basis: 38px;
            border-radius: 13px
        }

        #walletBalanceModal .wallet-balance-title h3 {
            font-size: 17px
        }

        #walletBalanceModal .wallet-balance-title p {
            font-size: 10px !important
        }

        #walletBalanceModal #closeWalletBalanceModal {
            width: 34px !important;
            height: 34px !important;
            flex-basis: 34px;
            border-radius: 11px !important;
            font-size: 20px !important
        }

        #walletBalanceModal .wallet-balance-body {
            padding: 0 15px calc(16px + env(safe-area-inset-bottom))
        }

        #walletBalanceModal .wallet-balance-total {
            margin-bottom: 15px !important;
            padding: 16px !important;
            border-radius: 18px !important
        }

        #walletBalanceModal .wallet-balance-total strong {
            font-size: 27px !important
        }

        #walletBalanceModal .wallet-balance-list {
            max-height: min(43dvh, 330px) !important;
            gap: 7px !important
        }

        #walletBalanceModal .wallet-balance-row {
            min-height: 60px;
            padding: 9px 10px !important;
            border-radius: 15px !important;
            grid-template-columns: 38px minmax(0, 1fr) auto !important;
            gap: 9px !important
        }

        #walletBalanceModal .wallet-balance-icon {
            width: 38px !important;
            height: 38px !important;
            border-radius: 12px !important;
            font-size: 17px !important
        }

        #walletBalanceModal .wallet-balance-name b {
            font-size: 13px !important
        }

        #walletBalanceModal .wallet-balance-row>strong {
            font-size: 12.5px !important;
            max-width: 125px
        }
    }

    @media(max-width:360px) {
        #walletBalanceModal .wallet-balance-head-icon {
            display: none
        }

        #walletBalanceModal .wallet-balance-total strong {
            font-size: 25px !important
        }

        #walletBalanceModal .wallet-balance-row>strong {
            max-width: 105px;
            font-size: 12px !important
        }
    }
    </style>

    <?php if (!$user || empty($user['pin_hash']) || !$unlocked): ?>
    <style>
    /* Auth/recovery pages must scroll independently from the locked app viewport.
           The main app intentionally locks body scrolling on mobile/desktop, but that
           rule must not affect login, registration, PIN, and password/PIN recovery. */
    html,
    body {
        height: auto !important;
        min-height: 100% !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;
        overscroll-behavior-y: auto !important;
        -webkit-overflow-scrolling: touch;
    }

    body {
        min-height: 100dvh !important;
        padding-bottom: 0 !important;
    }

    .auth-shell {
        width: 100%;
        min-height: 100dvh !important;
        height: auto !important;
        overflow: visible !important;
        align-items: start !important;
        justify-items: center !important;
        padding-top: max(24px, env(safe-area-inset-top)) !important;
        padding-bottom: max(40px, calc(24px + env(safe-area-inset-bottom))) !important;
    }

    .auth-card {
        margin: 0 auto !important;
    }

    @media (min-height: 900px) {
        .auth-shell {
            align-items: center !important;
        }
    }

    @media (max-width: 760px) {
        .auth-shell {
            padding-left: 16px !important;
            padding-right: 16px !important;
        }
    }
    </style>
    <?php endif; ?>
</head>

<body<?= ($user && $unlocked) ? ' data-offline-shell="1" data-user-id="' . (int)$user['id'] . '"' : '' ?>>
    <?php if ($user && $unlocked): ?>
    <div class="app-data-loader is-loading" id="appDataLoader" role="status" aria-live="polite" aria-busy="true">
        <div class="app-data-loader-card">
            <div class="app-data-loader-visual" aria-hidden="true">
                <span class="app-data-spinner"></span>
                <span class="app-data-loader-pulse pulse-one"></span>
                <span class="app-data-loader-pulse pulse-two"></span>
            </div>
            <div class="app-data-loader-copy">
                <b id="appDataLoaderTitle">Memuat data...</b>
                <small id="appDataLoaderText">Mengambil saldo, transaksi, chat, dan fitur keuangan.</small>
            </div>
            <div class="app-data-loader-progress" aria-hidden="true"><span></span></div>
            <button type="button" class="btn primary app-data-loader-retry" id="appDataLoaderRetry" hidden>Coba Lagi</button>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!$user): ?>
    <main class="auth-shell">
        <section class="auth-card">
            <div class="auth-brand">
                <div class="auth-logo"><img src="assets/icon.webp" alt="Logo aplikasi"></div>
                <h1>Catatan Keuangan</h1>
                <p>Keuangan pribadi anda, 100% gratis.</p>
            </div>
            <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
            <?php if ($notice): ?><div class="alert success"><?= h($notice) ?></div><?php endif; ?>
            <?php if ($mode === 'forgot'): ?>
            <h2>Reset Password / PIN</h2>
            <?php if (!isset($_GET['sent'])): ?>
            <p class="muted">Masukkan username atau email akun. Token 6 digit akan dikirim ke email yang sudah
                terverifikasi.</p>
            <form method="post" class="auth-form"><?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <input type="hidden" name="action" value="request_recovery">
                <label>Username atau Email</label>
                <input name="identifier" required autocomplete="username" placeholder="username atau nama@email.com"
                    value="<?= h($_SESSION['recovery_identifier'] ?? '') ?>">
                <button class="btn primary wide">Kirim Token Pemulihan</button>
            </form>
            <?php else: ?>
            <p class="muted">Masukkan token dari email. Isi password baru, PIN baru, atau keduanya.</p>
            <form method="post" class="auth-form"><?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <input type="hidden" name="action" value="reset_with_email_token">
                <label>Username atau Email</label>
                <input name="identifier" required autocomplete="username"
                    value="<?= h($_SESSION['recovery_identifier'] ?? '') ?>">
                <label>Token Email</label>
                <input class="recovery-token-input" name="token" required inputmode="numeric" pattern="[0-9]{6}"
                    maxlength="6" autocomplete="one-time-code" placeholder="000000">
                <div class="recovery-divider"><span>Reset password (opsional)</span></div>
                <label>Password baru</label>
                <div class="secret-field">
                    <input type="password" name="password" minlength="6" autocomplete="new-password"
                        placeholder="Kosongkan jika tidak ingin mengubah password" data-secret-input>
                    <button type="button" class="secret-toggle" data-secret-toggle aria-label="Lihat password"
                        title="Lihat password"></button>
                </div>
                <label>Ulangi password baru</label>
                <div class="secret-field">
                    <input type="password" name="password_confirm" minlength="6" autocomplete="new-password"
                        placeholder="Ulangi password baru" data-secret-input>
                    <button type="button" class="secret-toggle" data-secret-toggle aria-label="Lihat password"
                        title="Lihat password"></button>
                </div>
                <div class="recovery-divider"><span>Reset PIN (opsional)</span></div>
                <label>PIN baru</label>
                <div class="secret-field">
                    <input class="pin-input" type="password" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6"
                        name="pin" autocomplete="new-password" placeholder="4–6 digit" data-secret-input>
                    <button type="button" class="secret-toggle" data-secret-toggle aria-label="Lihat PIN"
                        title="Lihat PIN"></button>
                </div>
                <label>Ulangi PIN baru</label>
                <div class="secret-field">
                    <input class="pin-input" type="password" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6"
                        name="pin_confirm" autocomplete="new-password" placeholder="Ulangi PIN" data-secret-input>
                    <button type="button" class="secret-toggle" data-secret-toggle aria-label="Lihat PIN"
                        title="Lihat PIN"></button>
                </div>
                <small class="recovery-note">Minimal isi salah satu: password baru atau PIN baru.</small>
                <button class="btn primary wide">Verifikasi Token & Simpan</button>
            </form>
            <form method="post" class="center recovery-resend-form"><?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <input type="hidden" name="action" value="request_recovery">
                <input type="hidden" name="identifier" value="<?= h($_SESSION['recovery_identifier'] ?? '') ?>">
                <button class="link-button" type="submit">Kirim ulang token</button>
            </form>
            <?php endif; ?>
            <p class="auth-switch"><a href="index.php">← Kembali ke Login</a></p>
            <?php elseif ($mode === 'register'): ?>
            <h2>Buat Akun</h2>
            <p class="muted">Setiap akun memiliki data keuangan yang terpisah.</p>
            <form method="post" class="auth-form"><?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?><input type="hidden" name="action" value="register">
                <label>Username</label><input name="username" required minlength="3" autocomplete="username"
                    placeholder="contoh: charlie">
                <label>Email</label><input type="email" name="email" required autocomplete="email"
                    placeholder="nama@email.com">
                <small class="registration-email-note">Email digunakan untuk menerima token pemulihan password dan
                    PIN.</small>
                <label>Password</label>
                <div class="secret-field">
                    <input type="password" name="password" required minlength="6" autocomplete="new-password"
                        placeholder="Minimal 6 karakter" data-secret-input>
                    <button type="button" class="secret-toggle" data-secret-toggle aria-label="Lihat password"
                        title="Lihat password"></button>
                </div>
                <button class="btn primary wide">Buat Akun</button>
            </form>
            <p class="auth-switch">Sudah punya akun? <a href="index.php">Login</a></p>
            <?php else: ?>
            <h2>Login</h2>
            <p class="muted">Masuk dengan username dan password. Setelah itu perangkat ini bisa menggunakan PIN.</p>
            <form method="post" class="auth-form"><?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?><input type="hidden" name="action" value="login">
                <label>Username</label><input name="username" required autocomplete="username">
                <label>Password</label>
                <div class="secret-field">
                    <input type="password" name="password" required autocomplete="current-password" data-secret-input>
                    <button type="button" class="secret-toggle" data-secret-toggle aria-label="Lihat password"
                        title="Lihat password"></button>
                </div>
                <div class="forgot-password-row"><a href="?mode=forgot">Lupa password / PIN?</a></div>
                <button class="btn primary wide">Login</button>
            </form>
            <p class="auth-switch">Belum punya akun? <a href="?mode=register">Buat akun</a></p>
            <?php endif; ?>
            <div class="auth-legal-links"><a href="privacy-policy.php">Kebijakan Privasi</a><span>·</span><a
                    href="delete-account.php">Hapus Akun</a></div>
        </section>
    </main>
    <?php elseif (empty($user['pin_hash'])): ?>
    <main class="auth-shell">
        <section class="auth-card pin-card">
            <div class="auth-brand">
                <div class="avatar"><?= h(strtoupper(substr($user['username'], 0, 1))) ?></div>
                <h1>Halo, <?= h($user['username']) ?></h1>
                <p>Buat PIN agar akses berikutnya lebih cepat.</p>
            </div>
            <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
            <?php if ($notice): ?><div class="alert success"><?= h($notice) ?></div><?php endif; ?>
            <form method="post" class="auth-form"><?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?><input type="hidden" name="action" value="set_pin">
                <label>PIN baru</label>
                <div class="secret-field">
                    <input class="pin-input" type="password" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6"
                        name="pin" required autocomplete="new-password" placeholder="••••" data-secret-input>
                    <button type="button" class="secret-toggle" data-secret-toggle aria-label="Lihat PIN"
                        title="Lihat PIN"></button>
                </div>
                <label>Ulangi PIN</label>
                <div class="secret-field">
                    <input class="pin-input" type="password" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6"
                        name="pin_confirm" required autocomplete="new-password" placeholder="••••" data-secret-input>
                    <button type="button" class="secret-toggle" data-secret-toggle aria-label="Lihat PIN"
                        title="Lihat PIN"></button>
                </div>
                <button class="btn primary wide">Simpan PIN</button>
            </form>
            <p class="muted center">PIN harus 4–6 digit angka.</p>
            <div class="auth-legal-links"><a href="privacy-policy.php">Kebijakan Privasi</a><span>·</span><a
                    href="delete-account.php">Hapus Akun</a></div>
        </section>
    </main>
    <?php elseif (!$unlocked): ?>
    <main class="auth-shell">
        <section class="auth-card pin-card">
            <div class="auth-brand">
                <div class="avatar"><?= h(strtoupper(substr($user['username'], 0, 1))) ?></div>
                <h1><?= h($user['username']) ?></h1>
                <p>Masukkan PIN untuk membuka data keuangan.</p>
            </div>
            <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
            <?php if ($notice): ?><div class="alert success"><?= h($notice) ?></div><?php endif; ?>
            <form method="post" class="auth-form"><?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?><input type="hidden" name="action" value="unlock">
                <div class="secret-field">
                    <input class="pin-input pin-center" type="password" inputmode="numeric" pattern="[0-9]{4,6}"
                        maxlength="6" name="pin" required autofocus autocomplete="off" placeholder="••••"
                        data-secret-input>
                    <button type="button" class="secret-toggle" data-secret-toggle aria-label="Lihat PIN"
                        title="Lihat PIN"></button>
                </div>
                <button class="btn primary wide">Buka</button>
            </form>
            <?php if (!empty($nativeBiometricAutoUnlockAllowed) && !empty($nativeBiometricUnlockNonce)): ?>
            <form method="post" id="nativeBiometricUnlockForm" hidden aria-hidden="true">
                <?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <input type="hidden" name="action" value="native_biometric_unlock">
                <input type="hidden" name="biometric_nonce" value="<?= h($nativeBiometricUnlockNonce) ?>">
            </form>
            <div class="muted center native-biometric-wait" id="nativeBiometricWait" hidden>
                Biometrik perangkat aktif. PIN hanya diperlukan jika Anda memilih fallback PIN.
            </div>
            <?php endif; ?>
            <form method="post" class="center logout-under"><?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?><input type="hidden" name="action" value="logout"><button
                    class="link-button" type="submit">Login dengan akun lain</button></form>
            <div class="auth-legal-links"><a href="privacy-policy.php">Kebijakan Privasi</a><span>·</span><a
                    href="delete-account.php">Hapus Akun</a></div>
        </section>
    </main>
    <?php else: db(); ?>
    <div class="sync-banner" id="syncBanner" hidden role="status" aria-live="polite">
        <span class="sync-banner-dot"></span>
        <div><b id="syncBannerTitle">Mode Offline</b><small id="syncBannerText">Data akan disimpan di perangkat dan
                disinkronkan saat internet kembali.</small></div>
        <button type="button" id="syncNowBtn" hidden>Sinkronkan</button>
    </div>
    <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Buka menu" aria-controls="appSidebar"
        aria-expanded="false">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M5 7h14M5 12h14M5 17h14" />
        </svg>
    </button>
    <div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>
    <aside class="app-sidebar" id="appSidebar" aria-hidden="true">
        <div class="sidebar-head">
            <div class="sidebar-brand">
                <span class="sidebar-brand-mark"><img src="assets/icon.webp" alt=""></span>
                <div><b>Catatan Keuangan</b><small>Asisten keuangan pribadi</small></div>
            </div>
            <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Tutup menu">×</button>
        </div>
        <?php $initialAccessPlan = authPlan($user); $initialTrialState = authTrialStatus($user); $initialPremiumAccess = authIsSuperAdmin($user) || !empty($initialAccessPlan['active']); $initialTrialActive = (($initialAccessPlan['source'] ?? '') === 'trial'); ?>
        <div class="sidebar-profile">
            <span class="avatar small"><?= h(strtoupper(substr($user['username'], 0, 1))) ?></span>
            <div><small>Akun aktif</small><strong><?= h($user['username']) ?></strong><span
                    class="account-plan-badge <?= $initialPremiumAccess ? 'premium' : 'free' ?>"><?= $initialTrialActive ? 'Trial' : ($initialPremiumAccess ? 'Premium' : 'Free') ?></span>
            </div>
        </div>
        <div class="sidebar-section-label">MENU UTAMA</div>
        <nav class="sidebar-menu" aria-label="Menu akun">
            <button type="button" class="sidebar-menu-item sidebar-menu-item-active" id="sidebarSummary">
                <span class="sidebar-menu-icon"><svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-13h6V4h-6v3Z" />
                    </svg></span>
                <span><b>Ringkasan</b><small>Saldo dan aktivitas terbaru</small></span>
                <span class="sidebar-arrow">›</span>
            </button>
            <button type="button" class="sidebar-menu-item" id="openNotifications">
                <span class="sidebar-menu-icon"><?= ck_icon('bell') ?></span>
                <span><b>Pemberitahuan</b><small>Login, pengumuman & peringatan aplikasi</small></span>
                <span class="user-notification-badge" id="sidebarNotificationBadge" hidden>0</span>
            </button>
            <button type="button" class="sidebar-menu-item" id="openBudget">
                <span class="sidebar-menu-icon"><svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 3a9 9 0 1 0 9 9" />
                        <path d="M12 7v5l3 2" />
                        <path d="M17 3h4v4" />
                        <path d="m21 3-5 5" />
                    </svg></span>
                <span><b>Batas harian</b><small>Atur peringatan pengeluaran</small></span>
                <span class="sidebar-arrow">›</span>
            </button>
            <button type="button" class="sidebar-menu-item" id="openSetting">
                <span class="sidebar-menu-icon"><svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 7.5h15a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-12a2 2 0 0 1 2-2h12" />
                        <path d="M16 12h5M17.5 12h.01" />
                    </svg></span>
                <span><b>Atur saldo awal</b><small>Ubah saldo dasar akun</small></span>
                <span class="sidebar-arrow">›</span>
            </button>
            <button type="button" class="sidebar-menu-item" id="openEmailSecurity">
                <span class="sidebar-menu-icon"><svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 5h16v14H4z" />
                        <path d="m4 7 8 6 8-6" />
                    </svg></span>
                <span><b>Email &
                        Keamanan</b><small><?= $emailStatus['verified'] ? h($emailStatus['masked']) : ($emailStatus['has_email'] ? 'Email belum diverifikasi' : 'Lengkapi email pemulihan') ?></small></span>
                <span
                    class="email-menu-state <?= $emailStatus['verified'] ? 'ok' : 'warn' ?>"><?= $emailStatus['verified'] ? '✓' : '!' ?></span>
            </button>
            <button type="button" class="sidebar-menu-item sidebar-premium-item" data-finance-open="premium">
                <span class="sidebar-menu-icon"><?= ck_icon('crown') ?></span>
                <span><b>Premium</b><small id="sidebarPremiumText">Lihat paket & status berlangganan</small></span>
                <span class="sidebar-premium-badge <?= $initialPremiumAccess ? 'active' : '' ?> <?= $initialTrialActive ? 'trial' : '' ?>" id="sidebarPremiumBadge"><?= $initialTrialActive ? 'TRIAL' : ($initialPremiumAccess ? 'AKTIF' : 'UPGRADE') ?></span>
            </button>
            <button type="button" class="sidebar-menu-item premium-feature-link" data-premium-required="1"
                data-finance-open="wallets">
                <span class="sidebar-menu-icon"><?= ck_icon('wallet') ?></span>
                <span><b>Dompet & Rekening</b><small>Cash, bank, e-wallet & transfer</small></span><span
                    class="sidebar-arrow">›</span>
            </button>
            <button type="button" class="sidebar-menu-item premium-feature-link" data-premium-required="1"
                data-finance-open="budgets">
                <span class="sidebar-menu-icon"><?= ck_icon('target') ?></span>
                <span><b>Budget Bulanan</b><small>Kategori & batas per bulan</small></span><span
                    class="sidebar-arrow">›</span>
            </button>
            <button type="button" class="sidebar-menu-item premium-feature-link" data-premium-required="1"
                data-finance-open="bills">
                <span class="sidebar-menu-icon"><?= ck_icon('receipt') ?></span>
                <span><b>Tagihan & Cicilan</b><small>Jatuh tempo dan status bayar</small></span><span
                    class="sidebar-arrow">›</span>
            </button>
            <button type="button" class="sidebar-menu-item premium-feature-link" data-premium-required="1"
                data-finance-open="recurring">
                <span class="sidebar-menu-icon"><?= ck_icon('repeat') ?></span>
                <span><b>Transaksi Berulang</b><small>Otomatis harian/mingguan/bulanan</small></span><span
                    class="sidebar-arrow">›</span>
            </button>
            <button type="button" class="sidebar-menu-item premium-feature-link" data-premium-required="1"
                data-finance-open="goals">
                <span class="sidebar-menu-icon"><?= ck_icon('flag') ?></span>
                <span><b>Target Menabung</b><small>Progress dan target dana</small></span><span
                    class="sidebar-arrow">›</span>
            </button>
            <button type="button" class="sidebar-menu-item premium-feature-link" data-premium-required="1"
                data-finance-open="analytics">
                <span class="sidebar-menu-icon"><?= ck_icon('chart') ?></span>
                <span><b>Analitik & Prediksi</b><small>Grafik dan cukup sampai gajian</small></span><span
                    class="sidebar-arrow">›</span>
            </button>
            <button type="button" class="sidebar-menu-item" data-finance-open="backup">
                <span class="sidebar-menu-icon"><?= ck_icon('cloud') ?></span>
                <span><b>Backup & Aplikasi</b><small>Backup, restore, PWA & notifikasi</small></span><span
                    class="sidebar-arrow">›</span>
            </button>
            <button type="button" class="sidebar-menu-item" id="openHelpFaq">
                <span class="sidebar-menu-icon"><svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M9.7 9a2.45 2.45 0 0 1 4.75.8c0 1.8-2.45 2.1-2.45 4" />
                        <path d="M12 17.2h.01" />
                    </svg></span>
                <span><b>Bantuan & FAQ</b><small>Panduan penggunaan aplikasi</small></span>
                <span class="sidebar-arrow">›</span>
            </button>
            <a class="sidebar-menu-item sidebar-link" href="privacy-policy.php">
                <span class="sidebar-menu-icon"><?= ck_icon('lock') ?></span>
                <span><b>Kebijakan Privasi</b><small>Privasi & penggunaan data</small></span><span
                    class="sidebar-arrow">›</span>
            </a>
            <a class="sidebar-menu-item sidebar-link danger-link" href="delete-account.php">
                <span class="sidebar-menu-icon"><?= ck_icon('trash') ?></span>
                <span><b>Hapus Akun</b><small>Hapus akun & data secara permanen</small></span><span
                    class="sidebar-arrow">›</span>
            </a>
        </nav>
        <?php if (authIsSuperAdmin($user)): ?>
        <div class="sidebar-section-label sidebar-super-admin-label">SUPER ADMIN</div>
        <nav class="sidebar-menu sidebar-super-admin-menu" aria-label="Menu Super Admin">
            <button type="button" class="sidebar-menu-item sidebar-admin-item" data-finance-open="admin-overview">
                <span class="sidebar-menu-icon"><?= ck_icon('chart') ?></span>
                <span><b>Ringkasan Admin</b><small>Statistik akun & aktivitas</small></span>
                <span class="sidebar-arrow">›</span>
            </button>
            <button type="button" class="sidebar-menu-item sidebar-admin-item" id="openLearning">
                <span class="sidebar-menu-icon"><?= ck_icon('sparkles') ?></span>
                <span><b>Pembelajaran Asisten</b><small>Ajarkan balasan bersama</small></span>
                <span class="learning-count" id="learningRuleCount">0</span>
            </button>
            <a class="sidebar-menu-item sidebar-link sidebar-admin-item" href="<?= h(Yii::$app->urlManager->createUrl(['admin/migration'])) ?>">
                <span class="sidebar-menu-icon"><?= ck_icon('database') ?></span>
                <span><b>Migrasi & Backup</b><small>Backup, restore & riwayat MySQL</small></span>
                <span class="sidebar-arrow">›</span>
            </a>
            <button type="button" class="sidebar-menu-item sidebar-admin-item" data-finance-open="admin-broadcast">
                <span class="sidebar-menu-icon"><?= ck_icon('send') ?></span>
                <span><b>Broadcast Email</b><small>Kirim pengumuman pengguna</small></span>
                <span class="sidebar-arrow">›</span>
            </button>
            <button type="button" class="sidebar-menu-item sidebar-admin-item" data-finance-open="admin-notifications">
                <span class="sidebar-menu-icon"><?= ck_icon('bell') ?></span>
                <span><b>Notifikasi Admin</b><small>Invoice & bukti pembayaran baru</small></span>
                <span class="admin-notification-badge" id="adminNotificationBadge" hidden>0</span>
                <span class="sidebar-arrow">›</span>
            </button>
            <button type="button" class="sidebar-menu-item sidebar-admin-item" data-finance-open="admin-payments">
                <span class="sidebar-menu-icon"><?= ck_icon('credit-card') ?></span>
                <span><b>Pembayaran Premium</b><small>Verifikasi invoice pengguna</small></span>
                <span class="sidebar-arrow">›</span>
            </button>
            <button type="button" class="sidebar-menu-item sidebar-admin-item" data-finance-open="admin-plans">
                <span class="sidebar-menu-icon"><?= ck_icon('crown') ?></span>
                <span><b>Paket Langganan</b><small>Harga & durasi Premium</small></span>
                <span class="sidebar-arrow">›</span>
            </button>
            <button type="button" class="sidebar-menu-item sidebar-admin-item" data-finance-open="admin-coupons">
                <span class="sidebar-menu-icon"><?= ck_icon('ticket') ?></span>
                <span><b>Kupon Premium</b><small>Diskon & masa berlaku kupon</small></span>
                <span class="sidebar-arrow">›</span>
            </button>
            <button type="button" class="sidebar-menu-item sidebar-admin-item" data-finance-open="admin-banks">
                <span class="sidebar-menu-icon"><?= ck_icon('landmark') ?></span>
                <span><b>Rekening Pembayaran</b><small>Kelola rekening tujuan</small></span>
                <span class="sidebar-arrow">›</span>
            </button>
            <button type="button" class="sidebar-menu-item sidebar-admin-item" data-finance-open="admin-users">
                <span class="sidebar-menu-icon"><?= ck_icon('users') ?></span>
                <span><b>Akun Pengguna</b><small>Paket & akses pengguna</small></span>
                <span class="sidebar-arrow">›</span>
            </button>
        </nav>
        <?php endif; ?>
        <div class="sidebar-local-status" id="sidebarConnectionStatus">
            <span class="sidebar-local-dot"></span>
            <div><b id="sidebarConnectionTitle">Online</b><small id="sidebarConnectionText">Tersambung ke
                    server.</small></div>
            <span class="sidebar-sync-count" id="sidebarSyncCount" hidden>0</span>
        </div>
        <form method="post" class="sidebar-logout-form"><?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
            <input type="hidden" name="action" value="logout">
            <button class="sidebar-logout" type="submit">
                <span class="sidebar-logout-icon"><?= ck_icon('logout') ?></span><b>Logout</b>
            </button>
        </form>
    </aside>
    <main class="container app-main">
        <?php if ($error): ?>
        <div class="app-flash-message error"><?= h($error) ?></div>
        <?php elseif ($notice): ?>
        <div class="app-flash-message success"><?= h($notice) ?></div>
        <?php endif; ?>

        <?php if (!$emailStatus['verified']): ?>
        <section class="email-security-banner <?= $emailStatus['has_email'] ? 'unverified' : 'missing' ?>"
            id="emailSecurityBanner">
            <div class="email-security-banner-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M4 5h16v14H4z" />
                    <path d="m4 7 8 6 8-6" />
                </svg>
            </div>
            <div class="email-security-banner-copy">
                <b><?= $emailStatus['has_email'] ? 'Verifikasi email pemulihan' : 'Lengkapi email pemulihan' ?></b>
                <span><?= $emailStatus['has_email']
                                    ? 'Email ' . h($emailStatus['masked']) . ' belum diverifikasi. Verifikasi agar token reset password/PIN dapat dikirim.'
                                    : 'Tambahkan email agar password dan PIN dapat dipulihkan menggunakan token keamanan.' ?></span>
            </div>
            <button type="button"
                id="openEmailSecurityBanner"><?= $emailStatus['has_email'] ? 'Verifikasi' : 'Lengkapi' ?></button>
        </section>
        <?php endif; ?>
        <section class="stats">
            <article class="stat balance-stat" id="balanceStatCard" role="button" tabindex="0"
                aria-label="Lihat rincian saldo setiap dompet">
                <div class="stat-icon"><?= ck_icon('circle-dollar') ?></div>
                <div class="balance-stat-content">
                    <div class="balance-stat-copy"><small>Saldo tersedia</small><strong id="balance">Rp0</strong><span
                            class="balance-detail-hint">Akumulatif seluruh bulan · lihat saldo tiap dompet</span></div>
                    <div class="balance-stat-actions">
                        <button type="button" class="balance-visibility-toggle" id="balanceVisibilityToggle"
                            aria-label="Sembunyikan saldo, pemasukan, dan pengeluaran"
                            title="Sembunyikan nominal"></button>
                        <button type="button" class="balance-visibility-toggle" id="balanceRefresh"
                            aria-label="Muat ulang aplikasi" title="Muat ulang aplikasi">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M20 7v5h-5" />
                                <path d="M20 12a8 8 0 1 0-2.3 5.7M20 7l-2.3-2.3" />
                            </svg>
                        </button>
                    </div>
                </div>
            </article>
            <article class="stat income-stat">
                <div class="stat-icon"><?= ck_icon('income') ?></div>
                <div><small id="incomePeriodLabel">Pemasukan · bulan berjalan</small><strong id="income">Rp0</strong>
                </div>
            </article>
            <article class="stat expense-stat">
                <div class="stat-icon"><?= ck_icon('expense') ?></div>
                <div><small id="expensePeriodLabel">Pengeluaran · bulan berjalan</small><strong
                        id="expense">Rp0</strong></div>
            </article>
            <article class="stat initial-stat">
                <div class="stat-icon"><?= ck_icon('wallet') ?></div>
                <div><small>Saldo Awal</small><strong id="initial">Rp0</strong></div>
            </article>
        </section>
        <section class="daily-budget-card is-hidden" id="dailyBudgetCard" aria-live="polite">
            <div class="daily-budget-main">
                <div class="daily-budget-icon" id="dailyBudgetIcon"><?= ck_icon('shield-check') ?></div>
                <div class="daily-budget-copy">
                    <div class="daily-budget-title-row"><b id="dailyBudgetLabel">Batas pengeluaran hari ini</b><span
                            class="daily-budget-status" id="dailyBudgetStatus">Aman</span></div>
                    <div class="daily-budget-values"><strong id="dailyBudgetSpent">Rp0</strong><span>dari</span><strong
                            id="dailyBudgetLimit">Rp0</strong><span class="daily-budget-remaining"
                            id="dailyBudgetRemaining"></span></div>
                    <div class="daily-budget-progress"><span id="dailyBudgetProgress"></span></div>
                    <small id="dailyBudgetMessage">Pengeluaran masih di bawah batas.</small>
                </div>
                <div class="daily-budget-actions">
                    <button type="button" class="budget-card-icon-btn" id="budgetCardMinimize"
                        aria-label="Kecilkan peringatan" title="Kecilkan peringatan"></button>
                    <button type="button" class="budget-edit-btn" id="budgetCardEdit">Atur</button>
                    <button type="button" class="budget-card-icon-btn close" id="budgetCardClose"
                        aria-label="Tutup peringatan" title="Tutup peringatan">×</button>
                </div>
            </div>
        </section>
        <section class="reconciliation-card" id="dailyReconciliationCard" hidden aria-live="polite">
            <div class="reconciliation-icon" aria-hidden="true"><?= ck_icon('check') ?></div>
            <div class="reconciliation-copy">
                <div class="reconciliation-title-row">
                    <b>Sudah lengkap transaksi hari ini?</b>
                    <span id="reconciliationPendingBadge" hidden>0 draf</span>
                </div>
                <p id="reconciliationSummary">Periksa transaksi hari ini sebelum menutup hari.</p>
                <small>Catat yang terlupa sekarang, atau tandai selesai jika semuanya sudah masuk.</small>
            </div>
            <div class="reconciliation-actions">
                <button type="button" class="btn secondary" id="reconciliationQuickAdd">+ Yang terlupa</button>
                <button type="button" class="btn secondary" id="reconciliationOpenInbox">Rapikan Draf Transaksi</button>
                <button type="button" class="btn primary" id="reconciliationDone">Sudah lengkap</button>
            </div>
        </section>
        <section class="grid">
            <article class="panel chat-card mobile-view active" id="chatPanel">
                <div class="panel-head chat-panel-head">
                    <b>Asisten Keuangan Online</b>
                    <div class="chat-head-actions">
                        <button type="button" class="clear-chat-btn" id="clearChatsBtn" hidden
                            aria-label="Hapus semua chat" title="Hapus semua chat">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 10v6M14 10v6" />
                            </svg>
                            <span>Hapus chat</span>
                        </button>
                        <span class="offline" id="chatConnectionStatus">● Online</span>
                    </div>
                </div>
                <div class="chat-body" id="chatBody"></div>
                <div class="chat-foot">
                    <?php if (authIsSuperAdmin($user)): ?>
                    <div class="learning-pending-card" id="learningPendingCard" hidden>
                        <div class="learning-pending-icon"><?= ck_icon('sparkles') ?></div>
                        <div class="learning-pending-copy"><b>Mode belajar aktif</b><span
                                id="learningPendingText">Ajarkan balasan untuk pesan ini.</span></div>
                        <button type="button" id="cancelLearningPending">Batal</button>
                    </div>
                    <?php endif; ?>
                    <div class="photo-preview-wrap" id="photoPreviewWrap" hidden>
                        <div class="photo-preview-thumb"><img id="photoPreview" alt="Preview foto"></div>
                        <div class="photo-preview-info">
                            <b id="photoPreviewName">Foto</b>
                            <span id="photoCompressionInfo">Foto akan dikompres otomatis</span>
                            <span id="ocrStatus">Siap dipindai</span>
                            <div class="ocr-result" id="ocrResult" hidden></div>
                            <select id="imageMode" aria-label="Cara memproses foto">
                                <option value="auto">Otomatis</option>
                                <option value="receipt">Scan nota — nominal dari foto</option>
                                <option value="attachment">Lampiran saja</option>
                            </select>
                        </div>
                        <button type="button" class="remove-photo-btn" id="removePhoto"
                            aria-label="Hapus foto">×</button>
                    </div>
                    <div class="composer-wrap">
                        <div class="photo-source-menu" id="photoSourceMenu" hidden>
                            <button type="button" id="chooseGallery"><span><?= ck_icon('image') ?></span><b>Galeri</b><small>Pilih foto yang
                                    sudah ada</small></button>
                            <button type="button" id="chooseCamera"><span><?= ck_icon('camera') ?></span><b>Kamera</b><small>Ambil foto nota
                                    sekarang</small></button>
                        </div>
                        <form id="chatForm">
                            <button type="button" class="attach-btn" id="attachPhotoBtn" aria-label="Tambah foto"
                                title="Tambah foto">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path
                                        d="M21.4 11.6 12 21a6 6 0 0 1-8.5-8.5l10-10a4 4 0 0 1 5.7 5.7L9.8 17.6a2 2 0 0 1-2.8-2.8l8.7-8.7" />
                                </svg>
                            </button>
                            <input type="file" id="galleryPhoto" accept="image/jpeg,image/png,image/webp" hidden>
                            <input type="file" id="cameraPhoto" accept="image/*" capture="environment" hidden>
                            <input id="message" placeholder="Tulis transaksi atau keterangan foto..." autocomplete="off"
                                enterkeyhint="send">
                            <button class="btn primary send-btn" id="sendBtn" aria-label="Kirim"><span
                                    class="send-label">Kirim</span><span class="send-icon"><?= ck_icon('send') ?></span></button>
                        </form>
                    </div>
                    <small>Foto nota dipindai di perangkat menggunakan OCR gratis. Data akun
                        <b><?= h($user['username']) ?></b> tetap tersimpan terpisah dan terenkripsi.</small>
                </div>
            </article>
            <article class="panel transactions-panel mobile-view" id="txPanel">
                <div class="panel-head">
                    <div><b>Transaksi</b><small class="panel-subtitle">Filter tanggal dan urutkan nominal</small></div>
                    <div class="tx-head-actions">
                        <button type="button" class="tx-inbox-button" id="transactionInboxBtn" aria-label="Buka Draf Transaksi" title="Draf Transaksi">
                            <span class="tx-inbox-icon" aria-hidden="true"><?= ck_icon('receipt') ?></span>
                            <span class="tx-inbox-label">Draf Transaksi</span>
                            <b id="transactionInboxCount" hidden>0</b>
                        </button>
                        <button type="button" class="tx-add-button" id="addTransactionBtn" aria-label="Tambah transaksi manual" title="Tambah transaksi manual">
                            <span class="tx-add-icon" aria-hidden="true">+</span>
                            <span class="tx-add-label">Tambah</span>
                        </button>
                        <span class="badge" id="txCount">0</span>
                    </div>
                </div>

                <div class="tx-filter-wrap">
                    <div class="tx-period-bar">
                        <div class="tx-period-copy">
                            <b>Periode data</b>
                            <small id="txPeriodCaption">Bulan berjalan</small>
                        </div>
                        <div class="tx-period-switch" role="group" aria-label="Periode transaksi">
                            <button type="button" class="active" data-tx-period="month" aria-pressed="true">Bulan
                                berjalan</button>
                            <button type="button" data-tx-period="all" aria-pressed="false">Semua</button>
                        </div>
                    </div>
                    <div class="tx-search-export-row">
                        <label class="tx-search-box" for="txFilterSearch">
                            <span class="tx-search-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <circle cx="11" cy="11" r="6"></circle>
                                    <path d="m16 16 4 4"></path>
                                </svg>
                            </span>
                            <input type="search" id="txFilterSearch" placeholder="Cari transaksi, kategori, catatan..."
                                autocomplete="off">
                        </label>
                        <div class="tx-export-menu-wrap">
                            <button type="button" class="tx-export-toggle" id="txExportToggle" aria-expanded="false"
                                aria-controls="txExportMenu">
                                <span>Unduh</span><span class="tx-export-chevron">⌄</span>
                            </button>
                            <div class="tx-export-menu" id="txExportMenu" hidden>
                                <button type="button" id="txDownloadReport">
                                    <b>PDF</b><small>Laporan sesuai filter</small>
                                </button>
                                <button type="button" id="txDownloadXls">
                                    <b>Excel</b><small>Format .xls</small>
                                </button>
                                <button type="button" id="txDownloadCsv">
                                    <b>CSV</b><small>Data tabel .csv</small>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="tx-filter-toggle" id="txFilterToggle" aria-expanded="false"
                        aria-controls="txFilterPanel">
                        <span class="tx-filter-toggle-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M4 6h16M7 12h10M10 18h4" />
                            </svg>
                        </span>
                        <span>Filter & Urutkan</span>
                        <span class="tx-filter-active-count" id="txFilterActiveCount" hidden>0</span>
                        <span class="tx-filter-chevron">⌄</span>
                    </button>

                    <div class="tx-filter-panel" id="txFilterPanel" hidden>
                        <div class="tx-filter-grid">
                            <label>Jenis transaksi
                                <select id="txFilterType">
                                    <option value="all">Semua transaksi</option>
                                    <option value="expense">Pengeluaran saja</option>
                                    <option value="income">Pemasukan saja</option>
                                    <option value="transfer">Transfer antar dompet</option>
                                </select>
                            </label>
                            <label>Dompet / rekening
                                <select id="txFilterWallet">
                                    <option value="0">Semua dompet</option>
                                </select>
                            </label>
                            <label>Kategori
                                <select id="txFilterCategory">
                                    <option value="">Semua kategori</option>
                                </select>
                            </label>
                            <label>Dari tanggal
                                <input type="date" id="txFilterFrom">
                            </label>
                            <label>Sampai tanggal
                                <input type="date" id="txFilterTo">
                            </label>
                            <label>Urutkan
                                <select id="txFilterSort">
                                    <option value="date_desc">Tanggal terbaru</option>
                                    <option value="date_asc">Tanggal terlama</option>
                                    <option value="amount_desc">Nominal terbesar</option>
                                    <option value="amount_asc">Nominal terkecil</option>
                                </select>
                            </label>
                        </div>
                        <div class="tx-filter-actions">
                            <button type="button" class="tx-filter-reset" id="txFilterReset">Reset</button>
                            <button type="button" class="btn primary tx-filter-apply"
                                id="txFilterApply">Terapkan</button>
                        </div>
                    </div>

                    <div class="tx-filter-summary" id="txFilterSummary">
                        <span><b id="txFilteredCount">0</b> transaksi</span>
                        <span class="tx-summary-expense">Keluar <b id="txFilteredExpense">Rp0</b></span>
                        <span class="tx-summary-income">Masuk <b id="txFilteredIncome">Rp0</b></span>
                    </div>
                </div>

                <div class="tx-list" id="txList"></div>
            </article>
        </section>
    </main>
    <nav class="mobile-bottom-nav" aria-label="Navigasi utama">
        <button type="button" class="mobile-nav-item active" data-view="chatPanel" aria-label="Chat">
            <span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true">
                    <path
                        d="M7 18.5 3.5 21v-4.7A8 8 0 0 1 3 13.5v-3A7.5 7.5 0 0 1 10.5 3h3A7.5 7.5 0 0 1 21 10.5v3A7.5 7.5 0 0 1 13.5 21h-3A7.4 7.4 0 0 1 7 20.1" />
                    <path d="M8 11.8h.01M12 11.8h.01M16 11.8h.01" />
                </svg></span>
            <span>Chat</span>
        </button>
        <button type="button" class="mobile-nav-item" data-view="txPanel" aria-label="Transaksi">
            <span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M6 3h12a2 2 0 0 1 2 2v16l-3-2-2 2-3-2-3 2-2-2-3 2V5a2 2 0 0 1 2-2Z" />
                    <path d="M8 8h8M8 12h8M8 16h5" />
                </svg></span>
            <span>Transaksi</span><span class="nav-count" id="mobileTxCount">0</span>
        </button>
        <button type="button" class="mobile-nav-item" id="mobileOpenNotifications" aria-label="Pemberitahuan">
            <span class="nav-icon"><?= ck_icon('bell') ?></span>
            <span>Pemberitahuan</span><span class="user-notification-badge mobile" id="mobileNotificationBadge" hidden>0</span>
        </button>
        <button type="button" class="mobile-nav-item" id="mobileOpenMore" aria-label="Lainnya, buka menu"
            aria-controls="appSidebar" aria-expanded="false">
            <span class="nav-icon"><svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="5" cy="12" r="1.5" />
                    <circle cx="12" cy="12" r="1.5" />
                    <circle cx="19" cy="12" r="1.5" />
                </svg></span>
            <span>Lainnya</span>
        </button>
    </nav>
    <dialog id="budgetModal">
        <form method="dialog" class="modal-card budget-modal-card">
            <div class="modal-head">
                <div>
                    <h3>Batas Pengeluaran Harian</h3>
                    <p class="modal-subtitle">Atur hari yang dipantau dan batas maksimalnya.</p>
                </div>
                <button class="icon-btn" value="cancel" aria-label="Tutup">×</button>
            </div>

            <div class="budget-toggle-row">
                <div><b>Aktifkan peringatan</b><small>Pengeluaran akan dipantau berdasarkan hari.</small></div>
                <label class="switch"><input type="checkbox" id="dailyBudgetEnabled"><span
                        class="switch-slider"></span></label>
            </div>

            <div class="warning-setting">
                <label for="warningPercent">Beri warning saat mencapai</label>
                <div class="warning-percent-input"><input type="number" id="warningPercent" min="50" max="99" step="1"
                        value="80"><span>%</span></div>
            </div>

            <div class="budget-quick-actions">
                <button type="button" class="chip-btn" id="selectAllDays">Semua hari</button>
                <button type="button" class="chip-btn" id="selectWeekdays">Sen–Jum</button>
                <button type="button" class="chip-btn" id="clearDays">Kosongkan</button>
            </div>

            <div class="budget-bulk">
                <div><label for="bulkLimit">Nominal untuk hari yang dipilih</label><small>Opsional, untuk mengisi
                        beberapa hari sekaligus.</small></div>
                <div class="budget-bulk-action"><input type="number" id="bulkLimit" min="0" step="1000"
                        placeholder="50000"><button type="button" class="btn secondary"
                        id="applyBulkLimit">Terapkan</button></div>
            </div>

            <div class="day-budget-list">
                <div class="day-budget-row" data-day="1"><label class="day-check"><input type="checkbox"
                            class="day-enabled" data-day="1"><span>Senin</span></label>
                    <div class="money-field"><span>Rp</span><input type="number" class="day-limit" data-day="1" min="0"
                            step="1000" placeholder="0"></div>
                </div>
                <div class="day-budget-row" data-day="2"><label class="day-check"><input type="checkbox"
                            class="day-enabled" data-day="2"><span>Selasa</span></label>
                    <div class="money-field"><span>Rp</span><input type="number" class="day-limit" data-day="2" min="0"
                            step="1000" placeholder="0"></div>
                </div>
                <div class="day-budget-row" data-day="3"><label class="day-check"><input type="checkbox"
                            class="day-enabled" data-day="3"><span>Rabu</span></label>
                    <div class="money-field"><span>Rp</span><input type="number" class="day-limit" data-day="3" min="0"
                            step="1000" placeholder="0"></div>
                </div>
                <div class="day-budget-row" data-day="4"><label class="day-check"><input type="checkbox"
                            class="day-enabled" data-day="4"><span>Kamis</span></label>
                    <div class="money-field"><span>Rp</span><input type="number" class="day-limit" data-day="4" min="0"
                            step="1000" placeholder="0"></div>
                </div>
                <div class="day-budget-row" data-day="5"><label class="day-check"><input type="checkbox"
                            class="day-enabled" data-day="5"><span>Jumat</span></label>
                    <div class="money-field"><span>Rp</span><input type="number" class="day-limit" data-day="5" min="0"
                            step="1000" placeholder="0"></div>
                </div>
                <div class="day-budget-row" data-day="6"><label class="day-check"><input type="checkbox"
                            class="day-enabled" data-day="6"><span>Sabtu</span></label>
                    <div class="money-field"><span>Rp</span><input type="number" class="day-limit" data-day="6" min="0"
                            step="1000" placeholder="0"></div>
                </div>
                <div class="day-budget-row" data-day="7"><label class="day-check"><input type="checkbox"
                            class="day-enabled" data-day="7"><span>Minggu</span></label>
                    <div class="money-field"><span>Rp</span><input type="number" class="day-limit" data-day="7" min="0"
                            step="1000" placeholder="0"></div>
                </div>
            </div>

            <div class="budget-legend">
                <span><i class="legend-dot safe"></i>Aman</span><span><i
                        class="legend-dot warning"></i>Mendekati</span><span><i
                        class="legend-dot reached"></i>Tercapai</span><span><i
                        class="legend-dot exceeded"></i>Terlewati</span>
            </div>

            <div class="modal-actions"><button class="btn secondary" value="cancel">Batal</button><button
                    class="btn primary" id="saveDailyBudget" type="button">Simpan Batas</button></div>
        </form>
    </dialog>

    <dialog id="notificationCenterModal" class="notification-center-dialog">
        <div class="notification-center-card">
            <div class="modal-head notification-center-head">
                <div>
                    <h3>Pemberitahuan</h3>
                    <p class="modal-subtitle">Aktivitas login, broadcast admin, dan peringatan penting aplikasi.</p>
                </div>
                <button class="icon-btn" type="button" id="closeNotificationCenter" aria-label="Tutup">×</button>
            </div>
            <div class="notification-center-toolbar">
                <div><span class="notification-unread-dot"></span><b id="notificationUnreadText">Tidak ada yang belum dibaca</b></div>
                <button type="button" class="btn secondary" id="markAllNotificationsRead">Tandai Semua Dibaca</button>
            </div>
            <div class="user-notification-list" id="userNotificationList">
                <div class="empty compact">Memuat pemberitahuan...</div>
            </div>
        </div>
    </dialog>

    <dialog id="financeCenter" class="finance-center-dialog">
        <div class="finance-center-card">
            <div class="modal-head finance-center-head">
                <div>
                    <h3 id="financeCenterTitle">Pusat Keuangan</h3>
                    <p class="modal-subtitle" id="financeCenterSubtitle">Kelola dompet, budget, tagihan, target, analitik, backup dan aplikasi.</p>
                </div>
                <button class="icon-btn" type="button" id="closeFinanceCenter" aria-label="Tutup">×</button>
            </div>
            <div class="finance-tabs" id="financeTabs">
                <button data-finance-tab="premium"><span class="premium-tab-icon"><?= ck_icon('crown') ?></span>Premium</button>
                <button data-finance-tab="analytics" data-premium-tab="1">Analitik</button>
                <button data-finance-tab="wallets" data-premium-tab="1">Dompet</button>
                <button data-finance-tab="budgets" data-premium-tab="1">Budget</button>
                <button data-finance-tab="bills" data-premium-tab="1">Tagihan</button>
                <button data-finance-tab="recurring" data-premium-tab="1">Berulang</button>
                <button data-finance-tab="goals" data-premium-tab="1">Target</button>
                <button data-finance-tab="history">Riwayat</button>
                <button data-finance-tab="backup">Backup & PWA</button>
            </div>
            <div class="finance-content">
                <section class="finance-tab-panel" data-finance-panel="premium" hidden>
                    <div class="premium-hero" id="premiumHero">
                        <div>
                            <span class="premium-kicker">CATATAN KEUANGAN PREMIUM</span>
                            <h3 id="premiumHeroTitle">Buka fitur keuangan unggulan</h3>
                            <p id="premiumHeroText">Pilih paket, transfer ke rekening yang tersedia, lalu unggah bukti
                                pembayaran untuk diverifikasi admin.</p>
                        </div>
                        <span class="premium-account-status <?= $initialPremiumAccess ? 'active' : '' ?> <?= $initialTrialActive ? 'trial' : '' ?>" id="premiumAccountStatus"><?= $initialTrialActive ? 'TRIAL' : ($initialPremiumAccess ? 'PREMIUM' : 'FREE') ?></span>
                    </div>

                    <div class="premium-trial-card" id="premiumTrialCard" hidden>
                        <div class="premium-trial-icon" aria-hidden="true"><?= ck_icon('sparkles') ?></div>
                        <div class="premium-trial-copy">
                            <span class="premium-trial-kicker" id="premiumTrialKicker">FREE TRIAL PREMIUM</span>
                            <b id="premiumTrialTitle">Coba seluruh fitur Premium selama 7 hari</b>
                            <p id="premiumTrialText">Trial tidak aktif otomatis. Anda harus mengonfirmasi aktivasi terlebih dahulu.</p>
                            <small id="premiumTrialMeta">Hanya dapat digunakan satu kali untuk setiap akun.</small>
                        </div>
                        <button type="button" class="btn primary premium-trial-action" id="startPremiumTrial">Coba Premium 7 Hari</button>
                    </div>

                    <div class="premium-checkout-grid">
                        <div class="feature-card premium-checkout-card">
                            <div class="premium-step-head"><span>1</span>
                                <div><b>Pilih Paket</b><small>Pilih masa akses Premium.</small></div>
                            </div>
                            <div class="premium-plan-grid" id="premiumPlanList"></div>

                            <div class="premium-step-head"><span>2</span>
                                <div><b>Kupon Diskon</b><small>Opsional. Masukkan kode kupon dari admin.</small></div>
                            </div>
                            <div class="premium-coupon-box">
                                <div class="premium-coupon-input-row">
                                    <input id="premiumCouponCode" maxlength="32" autocomplete="off"
                                        placeholder="Contoh: HEMAT20">
                                    <button type="button" class="btn secondary" id="applyPremiumCoupon">Gunakan</button>
                                </div>
                                <div class="premium-coupon-status" id="premiumCouponStatus">Belum ada kupon digunakan.
                                </div>
                            </div>

                            <div class="premium-step-head"><span>3</span>
                                <div><b>Pilih Bank</b><small>Rekening tujuan dikelola oleh admin.</small></div>
                            </div>
                            <label>Bank tujuan<select id="premiumBankSelect">
                                    <option value="">Pilih bank</option>
                                </select></label>
                            <div class="premium-bank-preview" id="premiumBankPreview">Pilih bank untuk melihat rekening
                                tujuan.</div>

                            <div class="premium-step-head"><span>4</span>
                                <div><b>Data Pengirim</b><small>Masukkan nama dan nomor rekening asal transfer.</small>
                                </div>
                            </div>
                            <label>Nama Pengirim<input id="premiumSenderName" maxlength="100"
                                    placeholder="Nama sesuai rekening"></label>
                            <label>Nomor Rekening Pengirim<input id="premiumSenderAccount" maxlength="40"
                                    inputmode="numeric" placeholder="Contoh: 1234567890"></label>
                            <button type="button" class="btn primary wide" id="createPremiumInvoice">Buat
                                Invoice</button>
                        </div>

                        <div class="feature-card premium-invoice-card">
                            <div class="premium-step-head"><span>5</span>
                                <div><b>Invoice Tagihan</b><small>Invoice aktif dan status pembayaran Anda.</small>
                                </div>
                            </div>
                            <div id="premiumInvoiceArea" class="premium-invoice-empty">Belum ada invoice aktif.</div>
                            <div id="premiumUploadArea" class="premium-upload-area" hidden>
                                <div class="premium-step-head"><span>6</span>
                                    <div><b>Unggah Bukti Bayar</b><small>JPG, PNG, atau WEBP. Gambar dikompres otomatis
                                            sebelum disimpan.</small></div>
                                </div>
                                <label class="premium-proof-picker">Pilih gambar bukti pembayaran<input type="file"
                                        id="premiumProofFile" accept="image/jpeg,image/png,image/webp" hidden><span
                                        id="premiumProofName">Belum ada gambar dipilih</span></label>
                                <button type="button" class="btn primary wide" id="uploadPremiumProof">Kirim Bukti
                                    Pembayaran</button>
                            </div>
                        </div>
                    </div>

                    <div class="premium-basic-note">
                        <b>Akun Free tetap dapat menggunakan fitur dasar.</b> Chat pencatatan transaksi, kalkulator,
                        saldo, transaksi, batas harian, foto nota, dan akses aplikasi tetap tersedia. Fitur lanjutan
                        seperti analitik, banyak dompet, budget kategori, tagihan, transaksi berulang, target menabung,
                        export, dan backup memerlukan Premium. Edit transaksi dasar tetap tersedia untuk semua akun.
                    </div>
                </section>
                <section class="finance-tab-panel" data-finance-panel="analytics">
                    <div class="feature-toolbar">
                        <div><b>Analitik & Prediksi Sampai Gajian</b><small>Perhitungan native berdasarkan transaksi
                                aktual.</small></div><label>Tanggal gajian <select
                                id="paydayDay"><?php for ($d = 1; $d <= 31; $d++): ?><option value="<?= $d ?>"><?= $d ?>
                                </option><?php endfor; ?></select></label>
                    </div>
                    <div class="prediction-card" id="predictionCard"></div>
                    <div class="analytics-kpis" id="analyticsKpis"></div>
                    <div class="analytics-layout">
                        <div class="feature-card">
                            <h4>Pengeluaran per kategori</h4>
                            <div id="analyticsCategoryBars"></div>
                        </div>
                        <div class="feature-card">
                            <h4>Budget kategori bulan ini</h4>
                            <div id="analyticsBudgetBars"></div>
                        </div>
                    </div>
                </section>
                <section class="finance-tab-panel" data-finance-panel="wallets" hidden>
                    <div class="two-col-feature">
                        <div class="feature-card">
                            <h4>Tambah / edit dompet</h4><input type="hidden" id="walletId"><label>Nama<input
                                    id="walletName" placeholder="Contoh: BCA"></label><label>Jenis<select
                                    id="walletType">
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank</option>
                                    <option value="ewallet">E-Wallet</option>
                                    <option value="savings">Tabungan</option>
                                </select></label><label>Saldo awal<input type="number" id="walletInitial" min="0"
                                    step="1000"></label><label>Dana disisihkan<input type="number" id="walletReserved"
                                    min="0" step="1000" placeholder="0"></label><label>Saldo minimum<input type="number"
                                    id="walletMinimum" min="0" step="1000" placeholder="Contoh: 50000"><small>Saldo yang
                                    wajib tetap tersisa dan tidak dapat dipakai.</small></label><button
                                class="btn primary" type="button" id="saveWallet">Simpan
                                Dompet</button>
                        </div>
                        <div class="feature-card">
                            <h4>Transfer antar dompet</h4><label>Dari<select
                                    id="transferFrom"></select></label><label>Ke<select
                                    id="transferTo"></select></label><label>Nominal<input type="number"
                                    id="transferAmount" min="1" step="1000"></label><label>Keterangan<input
                                    id="transferNote" placeholder="Opsional"></label><button class="btn primary"
                                type="button" id="saveTransfer">Catat Transfer</button>
                        </div>
                    </div>
                    <div class="feature-list" id="walletList"></div>
                </section>
                <section class="finance-tab-panel" data-finance-panel="budgets" hidden>
                    <div class="two-col-feature">
                        <div class="feature-card">
                            <h4>Kategori custom</h4><input type="hidden" id="categoryId"><label>Nama<input
                                    id="categoryName" placeholder="Contoh: Motor"></label><label>Jenis<select
                                    id="categoryType">
                                    <option value="expense">Pengeluaran</option>
                                    <option value="income">Pemasukan</option>
                                    <option value="both">Keduanya</option>
                                </select></label><label>Icon / Emoji<input id="categoryIcon" maxlength="8"
                                    placeholder="🏍️"></label><label>Keyword parser<input id="categoryKeywords"
                                    placeholder="motor, oli, servis"></label><button class="btn primary"
                                id="saveCategory" type="button">Simpan Kategori</button>
                        </div>
                        <div class="feature-card">
                            <h4>Budget kategori bulanan</h4><label>Bulan<input type="month"
                                    id="monthlyBudgetMonth"></label><label>Kategori<select
                                    id="monthlyBudgetCategory"></select></label><label>Batas<input type="number"
                                    id="monthlyBudgetLimit" min="0" step="1000"></label><button class="btn primary"
                                id="saveMonthlyBudget" type="button">Simpan Budget</button><small>Isi 0 untuk
                                menonaktifkan budget kategori pada bulan tersebut.</small>
                        </div>
                    </div>
                    <div class="feature-list" id="categoryList"></div>
                </section>
                <section class="finance-tab-panel" data-finance-panel="bills" hidden>
                    <div class="feature-card feature-form-row"><input type="hidden" id="billId"><label>Nama
                            tagihan<input id="billName" placeholder="Cicilan rumah"></label><label>Nominal<input
                                type="number" id="billAmount" min="1" step="1000"></label><label>Jatuh tempo<input
                                type="date" id="billDueDate" required></label><label>Kategori<select
                                id="billCategory"></select></label><label>Dompet<select
                                id="billWallet"></select></label><button class="btn primary" id="saveBill"
                            type="button">Simpan</button></div>
                    <div class="feature-list" id="billList"></div>
                </section>
                <section class="finance-tab-panel" data-finance-panel="recurring" hidden>
                    <div class="feature-card feature-form-row"><input type="hidden" id="recurringId"><label>Nama<input
                                id="recurringName" placeholder="Gaji bulanan"></label><label>Jenis<select
                                id="recurringType">
                                <option value="income">Pemasukan</option>
                                <option value="expense">Pengeluaran</option>
                            </select></label><label>Nominal<input type="number" id="recurringAmount" min="1"
                                step="1000"></label><label>Kategori<select
                                id="recurringCategory"></select></label><label>Dompet<select
                                id="recurringWallet"></select></label><label>Frekuensi<select id="recurringFrequency">
                                <option value="monthly">Bulanan</option>
                                <option value="weekly">Mingguan</option>
                                <option value="daily">Harian</option>
                            </select></label><label>Setiap<input type="number" id="recurringInterval" min="1"
                                value="1"></label><label>Berikutnya<input type="date"
                                id="recurringNextRun"></label><button class="btn primary" id="saveRecurring"
                            type="button">Simpan</button></div>
                    <div class="feature-list" id="recurringList"></div>
                </section>
                <section class="finance-tab-panel" data-finance-panel="goals" hidden>
                    <div class="feature-card feature-form-row"><input type="hidden" id="goalId"><label>Nama target<input
                                id="goalName" placeholder="Dana darurat"></label><label>Target<input type="number"
                                id="goalTarget" min="1" step="1000"></label><label>Sudah terkumpul<input type="number"
                                id="goalCurrent" min="0" step="1000"></label><label>Deadline<input type="date"
                                id="goalDeadline"></label><button class="btn primary" id="saveGoal" type="button">Simpan
                            Target</button></div>
                    <div class="feature-list" id="goalList"></div>
                </section>
                <section class="finance-tab-panel" data-finance-panel="history" hidden>
                    <div class="feature-card">
                        <div class="feature-toolbar">
                            <div><b>Riwayat perubahan</b><small>Perubahan transaksi dan saldo awal tercatat di sini.
                                    Item yang mendukung undo dapat dipulihkan.</small></div>
                        </div>
                        <div class="feature-list" id="historyList"></div>
                    </div>
                </section>
                <section class="finance-tab-panel" data-finance-panel="backup" hidden>
                    <div class="feature-card sync-status-card">
                        <div class="feature-toolbar">
                            <div><b>Status Sinkronisasi</b><small>Lihat antrean offline, item gagal, dan konflik
                                    antarperangkat.</small></div><button type="button" class="btn secondary"
                                id="syncQueueRetry">Coba Sinkronkan</button>
                        </div>
                        <div class="feature-list" id="syncQueueList">
                            <div class="empty compact">Memuat status sinkronisasi…</div>
                        </div>
                    </div>
                    <div class="two-col-feature">
                        <div class="feature-card premium-backup-card">
                            <div class="premium-card-title">
                                <h4>Backup & Restore</h4><span>PREMIUM</span>
                            </div>
                            <p>Download seluruh data keuangan akun dalam satu file JSON. File ini tidak berisi password
                                atau PIN.</p>
                            <a class="btn primary feature-link-btn" id="downloadBackupBtn"
                                href="ajax/backup.php">Download Backup</a>
                            <label class="restore-label">Restore dari backup
                                <input type="file" id="restoreBackupFile" accept="application/json,.json">
                            </label>
                            <button class="btn secondary" id="restoreBackupBtn" type="button">Restore Data</button>
                        </div>

                        <div class="feature-card">
                            <h4>Aplikasi Mobile</h4>

                            <div id="androidInstallArea" hidden>
                                <p>Gunakan aplikasi Android untuk akses yang lebih praktis langsung dari perangkat Anda.
                                </p>
                                <a class="btn primary feature-link-btn" id="downloadAndroidApk"
                                    href="<?= h($publicBase) ?>/apks/Catatan%20Keuangan-v1.0.0.apk"
                                    download>Unduh APK Android</a><br>
                                <small>Setelah selesai diunduh, buka file APK untuk memasang aplikasi. Android mungkin
                                    meminta izin instalasi dari sumber ini.</small>
                            </div>

                            <div id="iosInstallArea" hidden>
                                <p>Di iPhone/iPad tidak perlu mengunduh APK. Website dapat dipasang ke Home Screen
                                    seperti aplikasi.</p>
                                <button class="btn primary" id="showIosInstallGuide" type="button">Pasang di Home
                                    Screen</button>
                                <div id="iosInstallGuide" hidden style="margin-top:12px">
                                    <small>
                                        <b>Cara memasang di iPhone/iPad:</b><br>
                                        1. Buka website ini menggunakan <b>Safari</b>.<br>
                                        2. Tekan tombol <b>Bagikan/Share</b> (ikon kotak dengan panah ke atas).<br>
                                        3. Pilih <b>Tambahkan ke Layar Utama / Add to Home Screen</b>.<br>
                                        4. Tekan <b>Tambah / Add</b>.
                                    </small>
                                </div>
                                <div id="iosStandaloneMessage" hidden style="margin-top:10px">
                                    <small>✓ Catatan Keuangan sudah dibuka dari Home Screen.</small>
                                </div>
                            </div>

                            <div id="desktopInstallArea" hidden>
                                <p><b>Android:</b> unduh APK <b>iPhone/iPad:</b> buka website ini di Safari
                                    lalu tambahkan ke Home Screen.</p>
                                <a class="btn primary feature-link-btn"
                                    href="<?= h($publicBase) ?>/apks/Catatan%20Keuangan-v1.0.0.apk"
                                    download>Unduh APK Android</a>
                                <button class="btn secondary" id="installPwaBtn" type="button">Install Web App</button>
                            </div>

                            <hr style="margin:18px 0;border:0;border-top:1px solid rgba(127,127,127,.2)">

                            <h4>Notifikasi</h4>
                            <button class="btn secondary" id="requestNotifyBtn" type="button">Izinkan
                                Notifikasi</button>
                            <label class="toggle-line"><input type="checkbox" id="notifyEnabled"> Aktifkan peringatan
                                browser</label>
                            <label class="toggle-line"><input type="checkbox" id="notifyDaily"> Batas harian</label>
                            <label class="toggle-line"><input type="checkbox" id="notifyBills"> Tagihan jatuh
                                tempo</label>
                            <label class="toggle-line"><input type="checkbox" id="notifyLow"> Saldo rendah</label>
                            <label class="toggle-line"><input type="checkbox" id="notifyReconciliation"> Rekonsiliasi transaksi harian</label>
                            <label class="toggle-line"><input type="checkbox" id="notifyEmailEnabled"> Kirim notifikasi
                                juga ke email terverifikasi</label>
                            <label>Ambang saldo rendah<input type="number" id="notifyLowThreshold" min="0"
                                    step="1000"></label>
                            <button class="btn primary" id="saveNotificationSettings" type="button">Simpan
                                Notifikasi</button>
                            <small>Peringatan browser bekerja saat aplikasi sedang dibuka/aktif. Jika email diaktifkan,
                                peringatan yang sama juga dikirim ke email terverifikasi dan dideduplikasi agar tidak
                                terkirim berulang kali.</small>
                        </div>
                    </div>
                </section>
                <?php if (authIsSuperAdmin($user)): ?>
                <section class="finance-tab-panel admin-standalone-panel" data-finance-panel="admin-overview" hidden>
                    <div class="feature-toolbar admin-menu-panel-head">
                        <div><b>Ringkasan Super Admin</b><small>Statistik akun, Premium, aktivitas, dan transaksi seluruh pengguna.</small></div>
                    </div>
                    <div class="analytics-kpis" id="adminKpis"></div>
                </section>
                <section class="finance-tab-panel admin-standalone-panel" data-finance-panel="admin-broadcast" hidden>
<div class="admin-premium-section admin-email-broadcast-section">
                        <div class="feature-toolbar">
                            <div><b>Broadcast Email Pengguna</b><small>Kirim informasi pembaruan Android, pengumuman,
                                    maintenance, atau pemberitahuan keamanan ke email pengguna yang sudah
                                    terverifikasi.</small></div>
                        </div>
                        <div class="admin-broadcast-form">
                            <label>Jenis Notifikasi
                                <select id="adminBroadcastKind">
                                    <option value="android_update">Pembaruan Aplikasi Android</option>
                                    <option value="announcement">Pengumuman</option>
                                    <option value="maintenance">Maintenance / Layanan</option>
                                    <option value="security">Keamanan</option>
                                </select>
                            </label>
                            <label>Target Pengguna
                                <select id="adminBroadcastTarget">
                                    <option value="all">Semua pengguna</option>
                                    <option value="free">Pengguna Free</option>
                                    <option value="premium">Pengguna Premium</option>
                                </select>
                            </label>
                            <label class="admin-broadcast-wide">Judul
                                <input id="adminBroadcastTitle" maxlength="120"
                                    placeholder="Contoh: Pembaruan Aplikasi Android Tersedia">
                            </label>
                            <label class="admin-broadcast-wide">Subjek Email
                                <input id="adminBroadcastSubject" maxlength="140"
                                    placeholder="Contoh: Versi terbaru Catatan Keuangan sudah tersedia">
                            </label>
                            <label class="admin-broadcast-wide">Isi Pesan
                                <textarea id="adminBroadcastMessage" rows="5" maxlength="4000"
                                    placeholder="Jelaskan informasi yang ingin disampaikan kepada pengguna."></textarea>
                            </label>
                            <label>Link Tombol (opsional)
                                <input id="adminBroadcastActionUrl" type="url"
                                    placeholder="<?= h($publicBase) ?>/android-download.php">
                                <small>Untuk update Android, hindari link <b>.apk</b> langsung. Sistem otomatis memakai
                                    halaman download resmi agar email tidak mudah difilter.</small>
                            </label>
                            <label>Label Tombol
                                <input id="adminBroadcastActionLabel" maxlength="50" placeholder="Unduh APK Terbaru">
                            </label>
                            <label class="admin-broadcast-wide admin-broadcast-safe-mode">
                                <span>Mode Pengiriman</span>
                                <span class="admin-broadcast-safe-row"><input id="adminBroadcastIncludeLink"
                                        type="checkbox"> Sertakan tombol/link eksternal di email</span>
                                <small>Disarankan tetap nonaktif. Email tanpa link lebih mudah masuk Inbox; pengguna
                                    dapat membuka aplikasi untuk melihat pembaruan.</small>
                            </label>
                        </div>
                        <div class="admin-broadcast-actions">
                            <div id="adminBroadcastProgress" class="admin-broadcast-progress">Siap mengirim ke email
                                terverifikasi.</div>
                            <div class="admin-broadcast-action-buttons">
                                <button type="button" class="btn secondary" id="adminBroadcastTest">Kirim Tes ke
                                    Saya</button>
                                <button type="button" class="btn primary" id="adminBroadcastSend">Kirim
                                    Broadcast</button>
                            </div>
                        </div>
                        <div class="feature-list admin-broadcast-history" id="adminBroadcastHistory">
                            <div class="empty">Belum ada riwayat broadcast.</div>
                        </div>
                    </div>
                </section>
                <section class="finance-tab-panel admin-standalone-panel" data-finance-panel="admin-notifications" hidden>
<div class="admin-premium-section admin-notification-section">
                        <div class="feature-toolbar">
                            <div><b>Notifikasi Admin</b><small>Invoice Premium baru dan bukti pembayaran dikirim
                                    otomatis ke email Super Admin yang terdaftar. Riwayat tetap tampil di sini saat
                                    aplikasi dibuka.</small></div>
                            <button type="button" class="btn secondary" id="adminMarkNotificationsRead">Tandai Sudah
                                Dibaca</button>
                        </div>
                        <div class="feature-list admin-notification-list" id="adminNotificationList">
                            <div class="empty">Belum ada notifikasi.</div>
                        </div>
                    </div>
                </section>
                <section class="finance-tab-panel admin-standalone-panel" data-finance-panel="admin-payments" hidden>
<div class="admin-premium-section">
                        <div class="feature-toolbar">
                            <div><b>Pembayaran Premium</b><small>Verifikasi invoice, paket, total tagihan, dan bukti
                                    pembayaran pengguna.</small></div>
                        </div>
                        <div class="analytics-kpis admin-payment-kpis" id="adminPaymentKpis"></div>
                        <div class="feature-list" id="adminSubscriptionList"></div>
                    </div>
                </section>
                <section class="finance-tab-panel admin-standalone-panel" data-finance-panel="admin-plans" hidden>
<div class="admin-premium-section">
                        <div class="feature-toolbar">
                            <div><b>Paket Langganan</b><small>Atur harga, nama, durasi, dan deskripsi paket Premium yang tampil pada halaman pembelian.</small></div>
                        </div>
                        <div class="admin-plan-note">Perubahan harga hanya berlaku untuk invoice baru. Invoice lama tetap memakai harga saat invoice tersebut dibuat.</div>
                        <div class="feature-list" id="adminPlanList"></div>
                    </div>
                </section>
                <section class="finance-tab-panel admin-standalone-panel" data-finance-panel="admin-coupons" hidden>
<div class="admin-premium-section">
                        <div class="feature-toolbar">
                            <div><b>Kupon Premium</b><small>Buat kode diskon persen atau nominal rupiah, atur masa
                                    berlaku, dan aktif/nonaktifkan kupon.</small></div><button type="button"
                                class="btn primary" id="adminAddCoupon">+ Tambah Kupon</button>
                        </div>
                        <div class="feature-list" id="adminCouponList"></div>
                    </div>
                </section>
                <section class="finance-tab-panel admin-standalone-panel" data-finance-panel="admin-banks" hidden>
<div class="admin-premium-section">
                        <div class="feature-toolbar">
                            <div><b>Rekening Pembayaran</b><small>Tambah, edit, aktif/nonaktifkan bank yang muncul pada
                                    halaman pembelian.</small></div><button type="button" class="btn primary"
                                id="adminAddBank">+ Tambah Bank</button>
                        </div>
                        <div class="feature-list" id="adminBankList"></div>
                    </div>
                </section>
                <section class="finance-tab-panel admin-standalone-panel" data-finance-panel="admin-users" hidden>
<div class="admin-premium-section">
                        <div class="feature-toolbar">
                            <div><b>Akun Pengguna</b><small>Pengaturan akses manual untuk kebutuhan
                                    administrasi.</small></div>
                        </div>
                        <div class="feature-list" id="adminUserList"></div>
                    </div>
                </section>
                <?php endif; ?>
            </div>
        </div>
    </dialog>

    <button type="button" class="quick-capture-fab" id="quickCaptureFab" aria-label="Catat cepat. Seret untuk memindahkan posisi." title="Catat cepat · seret untuk pindahkan">
        <span class="quick-capture-plus">+</span>
        <span class="quick-capture-label">Catat cepat</span>
        <b class="quick-capture-count" id="quickCaptureCount" hidden>0</b>
    </button>

    <dialog id="quickCaptureModal" class="quick-capture-dialog">
        <div class="modal-card quick-capture-card">
            <div class="modal-head">
                <div>
                    <h3>Catat Cepat</h3>
                    <p class="modal-subtitle">Tangkap transaksinya dulu. Kategori dan dompet bisa dirapikan nanti di Draf Transaksi.</p>
                </div>
                <button class="icon-btn" type="button" id="closeQuickCapture" aria-label="Tutup">×</button>
            </div>
            <div class="quick-type-switch" role="group" aria-label="Jenis transaksi cepat">
                <button type="button" class="active" data-quick-type="expense">Pengeluaran</button>
                <button type="button" data-quick-type="income">Pemasukan</button>
                <button type="button" data-quick-type="transfer">Transfer</button>
            </div>
            <div class="quick-capture-form">
                <label>Nominal
                    <div class="quick-money-input"><span>Rp</span><input type="number" id="quickCaptureAmount" min="1" step="1000" inputmode="numeric" placeholder="50000"></div>
                </label>
                <label>Keterangan singkat
                    <input id="quickCaptureNote" maxlength="255" placeholder="Contoh: Pertamax motor, makan siang, cashback Seabank">
                </label>
                <label>Tanggal
                    <input type="date" id="quickCaptureDate">
                </label>
                <div class="quick-photo-field">
                    <div class="quick-photo-label-row">
                        <span>Foto nota <small>opsional</small></span>
                        <span id="quickPhotoStatus">Belum ada foto</span>
                    </div>
                    <div class="quick-photo-actions">
                        <button type="button" class="quick-photo-source" id="quickPhotoCameraBtn">
                            <?= ck_icon('camera') ?><span>Ambil Foto</span>
                        </button>
                        <button type="button" class="quick-photo-source" id="quickPhotoGalleryBtn">
                            <?= ck_icon('image') ?><span>Dari Galeri</span>
                        </button>
                    </div>
                    <input type="file" id="quickCameraPhoto" accept="image/*" capture="environment" hidden>
                    <input type="file" id="quickGalleryPhoto" accept="image/jpeg,image/png,image/webp" hidden>
                    <div class="quick-photo-preview" id="quickPhotoPreviewWrap" hidden>
                        <button type="button" class="quick-photo-thumb" id="quickPhotoOpenPreview" aria-label="Lihat foto nota">
                            <img id="quickPhotoPreview" alt="Preview nota">
                        </button>
                        <div>
                            <b id="quickPhotoPreviewName">Foto nota</b>
                            <small id="quickPhotoMeta">Foto akan dikompres otomatis.</small>
                            <span id="quickPhotoOcrResult"></span>
                        </div>
                        <button type="button" class="quick-photo-remove" id="quickPhotoRemove" aria-label="Hapus foto">×</button>
                    </div>
                </div>
            </div>
            <div class="quick-capture-info" role="note" aria-label="Informasi draf transaksi">
                <div class="quick-capture-info-icon" aria-hidden="true">i</div>
                <div>
                    <b>Masih berupa draf sementara</b>
                    <span>Data yang disimpan dari Catat Cepat belum memengaruhi saldo, laporan, maupun total transaksi. Saldo baru berubah setelah draf dikonfirmasi menjadi transaksi final.</span>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn secondary" type="button" id="quickCaptureCancel">Batal</button>
                <button class="btn primary" type="button" id="quickCaptureSave">Simpan ke Draf Transaksi</button>
            </div>
        </div>
    </dialog>

    <dialog id="transactionInboxModal" class="transaction-inbox-dialog">
        <div class="modal-card transaction-inbox-card">
            <div class="modal-head">
                <div>
                    <h3>Draf Transaksi</h3>
                    <p class="modal-subtitle">Draf cepat yang belum memengaruhi saldo. Konfirmasi, edit, atau hapus saat Anda punya waktu.</p>
                </div>
                <button class="icon-btn" type="button" id="closeTransactionInbox" aria-label="Tutup">×</button>
            </div>
            <div class="transaction-inbox-summary">
                <div><small>Belum dirapikan</small><strong id="inboxPendingTotal">0</strong></div>
                <div class="transaction-inbox-summary-actions">
                    <button type="button" class="btn secondary" id="inboxConfirmAll">Simpan semua</button>
                    <button type="button" class="btn primary" id="inboxQuickAdd">+ Catat cepat</button>
                </div>
            </div>
            <div class="transaction-inbox-list" id="transactionInboxList"></div>
        </div>
    </dialog>

    <dialog id="transactionCreateModal" class="transaction-edit-dialog transaction-create-dialog">
        <div class="modal-card transaction-edit-card">
            <div class="modal-head">
                <div>
                    <h3>Tambah Transaksi</h3>
                    <p class="modal-subtitle">Catat pemasukan, pengeluaran, atau transfer antar dompet secara manual.</p>
                </div>
                <button class="icon-btn" type="button" id="closeTransactionCreate" aria-label="Tutup">×</button>
            </div>

            <div class="feature-form-grid">
                <label>Jenis
                    <select id="createTxType">
                        <option value="expense">Pengeluaran</option>
                        <option value="income">Pemasukan</option>
                        <option value="transfer">Transfer antar dompet</option>
                    </select>
                </label>
                <label>Nominal
                    <input type="number" id="createTxAmount" min="1" step="1000" inputmode="numeric" placeholder="Contoh: 50000">
                </label>
                <label>Tanggal
                    <input type="date" id="createTxDate">
                </label>

                <label class="create-standard-field">Kategori
                    <select id="createTxCategory"></select>
                </label>
                <label class="create-standard-field">Dompet
                    <select id="createTxWallet"></select>
                </label>
                <label class="create-expense-field">Pola
                    <select id="createTxSpendingKind">
                        <option value="daily">Harian</option>
                        <option value="once" selected>Sekali bayar</option>
                        <option value="recurring">Berulang</option>
                    </select>
                </label>
                <label class="create-expense-field">Hubungkan tagihan
                    <select id="createTxBill">
                        <option value="0">Tidak dihubungkan</option>
                    </select>
                </label>

                <label class="create-transfer-field" hidden>Dari dompet
                    <select id="createTxFromWallet"></select>
                </label>
                <label class="create-transfer-field" hidden>Ke dompet
                    <select id="createTxToWallet"></select>
                </label>

                <label class="full">Keterangan
                    <input id="createTxNote" maxlength="255" placeholder="Opsional, misalnya: Isi Pertamax">
                </label>
            </div>

            <div class="manual-tx-hint" id="createTxHint">
                Saldo dan laporan akan diperbarui setelah transaksi berhasil disimpan.
            </div>

            <div class="modal-actions">
                <button class="btn secondary" id="cancelTransactionCreate" type="button">Batal</button>
                <button class="btn primary" id="saveTransactionCreate" type="button">Simpan Transaksi</button>
            </div>
        </div>
    </dialog>

    <dialog id="transactionEditModal" class="transaction-edit-dialog">
        <div class="modal-card transaction-edit-card">
            <div class="modal-head">
                <div>
                    <h3>Edit Transaksi</h3>
                    <p class="modal-subtitle">Ubah nominal, tanggal, kategori, dompet, pola pengeluaran, relasi tagihan,
                        dan keterangan.</p>
                </div><button class="icon-btn" type="button" id="closeTransactionEdit">×</button>
            </div>
            <input type="hidden" id="editTxId">
            <div class="feature-form-grid"><label>Jenis<select id="editTxType">
                        <option value="expense">Pengeluaran</option>
                        <option value="income">Pemasukan</option>
                        <option value="transfer">Transfer antar dompet</option>
                    </select></label><label>Nominal<input type="number" id="editTxAmount" min="1"
                        step="1000"></label><label>Tanggal<input type="date" id="editTxDate"></label><label
                    class="edit-standard-field">Kategori<select id="editTxCategory"></select></label><label
                    class="edit-standard-field">Dompet<select id="editTxWallet"></select></label><label
                    class="edit-standard-field">Pola<select id="editTxSpendingKind">
                        <option value="daily">Harian</option>
                        <option value="once">Sekali bayar</option>
                        <option value="recurring">Berulang</option>
                    </select></label><label class="edit-standard-field">Hubungkan tagihan<select id="editTxBill">
                        <option value="0">Tidak dihubungkan</option>
                    </select></label><label class="edit-transfer-field" hidden>Dari dompet<select
                        id="editTxFromWallet"></select></label><label class="edit-transfer-field" hidden>Ke
                    dompet<select id="editTxToWallet"></select></label><label class="full">Keterangan<input
                        id="editTxNote"></label></div>
            <div class="modal-actions"><button class="btn secondary" id="cancelTransactionEdit"
                    type="button">Batal</button><button class="btn primary" id="saveTransactionEdit"
                    type="button">Simpan Perubahan</button></div>
        </div>
    </dialog>

    <dialog id="helpFaqModal" class="help-faq-dialog" aria-labelledby="helpFaqTitle">
        <div class="modal-card help-faq-card">
            <div class="help-faq-head">
                <div class="help-faq-title-wrap">
                    <span class="help-faq-title-icon" aria-hidden="true">?</span>
                    <div>
                        <h3 id="helpFaqTitle">Bantuan & FAQ</h3>
                        <p>Temukan jawaban singkat tentang penggunaan Catatan Keuangan.</p>
                    </div>
                </div>
                <button class="icon-btn" type="button" id="closeHelpFaq" aria-label="Tutup">×</button>
            </div>

            <div class="help-faq-search-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path d="m16.2 16.2 4 4" />
                </svg>
                <input type="search" id="helpFaqSearch" placeholder="Cari bantuan, misalnya: cicilan, offline, saldo..."
                    autocomplete="off">
                <button type="button" id="clearHelpFaqSearch" aria-label="Hapus pencarian" hidden>×</button>
            </div>

            <div class="help-faq-categories" id="helpFaqCategories" role="tablist" aria-label="Kategori bantuan">
                <button type="button" class="active" data-faq-category="all">Semua</button>
                <?php foreach ($faqCategories as $faqCategoryKey => $faqCategoryLabel): ?>
                <button type="button" data-faq-category="<?= h($faqCategoryKey) ?>"><?= h($faqCategoryLabel) ?></button>
                <?php endforeach; ?>
            </div>

            <div class="help-faq-body">
                <div class="help-faq-results-line">
                    <b id="helpFaqResultCount">Semua pertanyaan</b>
                    <span>Klik pertanyaan untuk melihat jawaban</span>
                </div>

                <div class="help-faq-list" id="helpFaqList">
                    <?php foreach ($faqEntries as $faqEntry): ?>
                    <details class="help-faq-item" data-faq-category="<?= h((string)$faqEntry['category']) ?>"
                        data-faq-keywords="<?= h((string)$faqEntry['keywords']) ?>">
                        <summary><span><?= h((string)$faqEntry['question']) ?></span><i>+</i></summary>
                        <div class="help-faq-answer"><?= (string)$faqEntry['answer_html'] ?></div>
                    </details>
                    <?php endforeach; ?>
                </div>

                <div class="help-faq-empty" id="helpFaqEmpty" hidden>
                    <span>?</span>
                    <b>Jawaban belum ditemukan</b>
                    <p>Coba kata kunci lain atau tanyakan langsung ke Asisten.</p>
                </div>
            </div>

            <div class="help-faq-footer">
                <div><b>Masih butuh bantuan?</b><span>Tanyakan kasus Anda langsung melalui chat.</span></div>
                <button type="button" class="btn primary" id="faqAskAssistant">Tanya Asisten</button>
            </div>
        </div>
    </dialog>

    <?php if (authIsSuperAdmin($user)): ?>
    <dialog id="learningModal" class="learning-dialog">
        <div class="modal-card learning-modal-card">
            <div class="modal-head">
                <div>
                    <h3>Pembelajaran Asisten</h3>
                    <small>Khusus Super Admin</small>
                    <p class="modal-subtitle">Balasan yang diajarkan disimpan bersama dan dapat digunakan semua akun.
                    </p>
                </div>
                <button class="icon-btn" type="button" id="closeLearningModal" aria-label="Tutup">×</button>
            </div>
            <div class="learning-info-box">
                <b>Pembelajaran bersama antar pengguna</b>
                <span>Contoh: pemicu <code>oke</code> → balasan <code>Siap {nama}, lanjut saja.</code></span>
                <small>Template tersedia: <code>{nama}</code>, <code>{saldo}</code>, <code>{hari}</code>,
                    <code>{tanggal}</code>.</small>
            </div>
            <div class="learning-form-grid">
                <label>Jika pengguna mengatakan
                    <input id="learningPhrase" maxlength="160" placeholder="Contoh: oke">
                </label>
                <label>Asisten membalas
                    <textarea id="learningResponse" maxlength="1000" rows="3"
                        placeholder="Contoh: Siap {nama}, lanjut saja."></textarea>
                </label>
                <button type="button" class="btn primary" id="saveLearningRule">Simpan Pembelajaran</button>
            </div>
            <div class="learning-list-head"><b>Yang sudah dipelajari</b><span id="learningListCount">0 aturan</span>
            </div>
            <div class="learning-rule-list" id="learningRuleList">
                <div class="empty">Belum ada pembelajaran.</div>
            </div>
        </div>
    </dialog>

    <?php endif; ?>

    <dialog id="photoViewer" class="photo-viewer-dialog">
        <div class="photo-viewer-card">
            <button type="button" class="photo-viewer-close" id="closePhotoViewer" aria-label="Tutup">×</button>
            <img id="photoViewerImg" alt="Foto lampiran">
        </div>
    </dialog>
    <dialog id="walletBalanceModal" class="wallet-balance-dialog">
        <div class="modal-card wallet-balance-card">
            <span class="wallet-balance-handle" aria-hidden="true"></span>
            <div class="wallet-balance-head">
                <span class="wallet-balance-head-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M3 7.5h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-12a2 2 0 0 1 2-2h12" />
                        <path d="M16 12h5" />
                        <path d="M17.5 12h.01" />
                    </svg>
                </span>
                <div class="wallet-balance-title">
                    <h3>Rincian Saldo</h3>
                    <p>Saldo tersedia dihitung per dompet. Saldo di bawah batas minimum dihitung Rp0, bukan minus.</p>
                </div>
                <button class="icon-btn" type="button" id="closeWalletBalanceModal" aria-label="Tutup">×</button>
            </div>
            <div class="wallet-balance-body">
                <div class="wallet-balance-total">
                    <div class="wallet-balance-total-label">
                        <small>Total saldo tersedia</small>
                        <span class="wallet-balance-total-badge">SEMUA DOMPET</span>
                    </div>
                    <strong id="walletBalanceTotal">Rp0</strong>
                </div>
                <div class="wallet-balance-section-head">
                    <b>Dompet & Rekening</b>
                    <span>Saldo tersedia</span>
                </div>
                <div class="wallet-balance-list" id="walletBalanceList">
                    <div class="empty">Belum ada dompet atau rekening.</div>
                </div>
                <div class="wallet-balance-footnote">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 10v6M12 7h.01" />
                    </svg>
                    <span>Saldo aktual tetap mengikuti transaksi. Dompet yang berada di bawah saldo minimum tidak
                        mengurangi saldo tersedia dompet lain; kontribusinya ke total tersedia dihitung Rp0.</span>
                </div>
            </div>
        </div>
    </dialog>

    <dialog id="emailSecurityModal" class="email-security-dialog">
        <div class="modal-card email-security-card">
            <div class="modal-head email-security-head">
                <div>
                    <h3>Email & Keamanan</h3>
                    <p class="modal-subtitle">Email digunakan untuk menerima token reset password dan PIN.</p>
                </div>
                <button class="icon-btn" type="button" id="closeEmailSecurity" aria-label="Tutup">×</button>
            </div>

            <div class="email-status-card <?= $emailStatus['verified'] ? 'verified' : 'pending' ?>">
                <span class="email-status-icon"><?= $emailStatus['verified'] ? ck_icon('check') : ck_icon('mail') ?></span>
                <div>
                    <b><?= $emailStatus['verified'] ? 'Email terverifikasi' : ($emailStatus['has_email'] ? 'Menunggu verifikasi' : 'Email belum dilengkapi') ?></b>
                    <small><?= $emailStatus['has_email'] ? h($emailStatus['email']) : 'Tambahkan alamat email yang aktif dan dapat Anda akses.' ?></small>
                </div>
                <?php if ($emailStatus['verified']): ?><span class="email-verified-badge">AMAN</span><?php endif; ?>
            </div>

            <form method="post" class="email-security-form"><?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <input type="hidden" name="action" value="save_email">
                <label>Alamat Email</label>
                <input type="email" name="email" required autocomplete="email" value="<?= h($emailStatus['email']) ?>"
                    placeholder="nama@email.com">
                <small>Jika email diubah, alamat baru wajib diverifikasi kembali.</small>
                <button type="submit"
                    class="btn primary wide"><?= $emailStatus['has_email'] ? 'Simpan & Verifikasi Email' : 'Simpan Email' ?></button>
            </form>

            <?php if ($emailStatus['has_email'] && !$emailStatus['verified']): ?>
            <div class="email-token-section">
                <div class="email-token-copy">
                    <b>Masukkan kode verifikasi</b>
                    <small>Kode 6 digit dikirim ke <?= h($emailStatus['masked']) ?> dan berlaku 15 menit.</small>
                </div>
                <form method="post" class="email-token-form"><?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                    <input type="hidden" name="action" value="verify_email">
                    <input type="text" name="email_token" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                        autocomplete="one-time-code" placeholder="000000" aria-label="Kode verifikasi email">
                    <button type="submit" class="btn primary">Verifikasi</button>
                </form>
                <form method="post" class="email-resend-form"><?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                    <input type="hidden" name="action" value="resend_email_verification">
                    <button type="submit" class="link-button">Kirim ulang kode</button>
                </form>
            </div>
            <?php elseif ($emailStatus['verified']): ?>
            <div class="email-security-info">
                <b>Pemulihan akun aktif</b>
                <span>Jika lupa password atau PIN, pilih <strong>Lupa password / PIN?</strong> pada halaman login. Token
                    akan dikirim ke email ini.</span>
            </div>
            <?php endif; ?>

            <section class="account-security-section" aria-labelledby="accountSecurityTitle">
                <div class="account-security-title">
                    <div>
                        <b id="accountSecurityTitle">Keamanan Akun</b>
                        <small>Username bersifat tetap. Password dan PIN dapat diubah tanpa kode verifikasi
                            email.</small>
                    </div>
                    <span class="account-security-badge">LOGIN AKTIF</span>
                </div>

                <div class="account-username-row">
                    <div class="account-username-icon" aria-hidden="true"><?= ck_icon('at') ?></div>
                    <div class="account-username-copy">
                        <small>Username</small>
                        <strong><?= h($user['username']) ?></strong>
                        <span>Username tidak dapat diubah.</span>
                    </div>
                    <span class="account-locked-chip">TERKUNCI</span>
                </div>

                <div class="biometric-security-card" id="biometricSecurityCard">
                    <div class="biometric-security-icon" aria-hidden="true"><?= ck_icon('fingerprint') ?></div>
                    <div class="biometric-security-copy">
                        <div class="biometric-security-title-row">
                            <b>Kunci Biometrik Perangkat</b>
                            <span class="biometric-security-state unavailable"
                                id="biometricSecurityState">MEMERIKSA</span>
                        </div>
                        <small id="biometricSecurityText">Mendeteksi dukungan sidik jari atau pengenalan wajah pada
                            perangkat ini.</small>
                        <span class="biometric-security-note" id="biometricSecurityNote">Fitur ini melindungi aplikasi saat dibuka kembali. Setelah biometrik berhasil, web langsung dibuka tanpa meminta PIN lagi; PIN tetap tersedia sebagai fallback.</span>
                    </div>
                    <button type="button" class="btn secondary biometric-security-toggle" id="biometricSecurityToggle"
                        disabled>Aktifkan</button>
                </div>

                <details class="security-change-card"
                    <?= (($_POST['action'] ?? '') === 'change_password') ? 'open' : '' ?>>
                    <summary>
                        <span class="security-change-icon"><?= ck_icon('key') ?></span>
                        <span><b>Ubah Kata Sandi</b><small>Gunakan password saat ini untuk membuat password
                                baru.</small></span>
                        <span class="security-chevron">⌄</span>
                    </summary>
                    <form method="post" class="security-change-form" autocomplete="off"><?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                        <input type="hidden" name="action" value="change_password">
                        <label>Password saat ini</label>
                        <div class="secret-field">
                            <input type="password" name="current_password" required autocomplete="current-password"
                                placeholder="Masukkan password saat ini" data-secret-input>
                            <button type="button" class="secret-toggle" data-secret-toggle aria-label="Lihat password"
                                title="Lihat password"></button>
                        </div>
                        <label>Password baru</label>
                        <div class="secret-field">
                            <input type="password" name="new_password" required minlength="6"
                                autocomplete="new-password" placeholder="Minimal 6 karakter" data-secret-input>
                            <button type="button" class="secret-toggle" data-secret-toggle aria-label="Lihat password"
                                title="Lihat password"></button>
                        </div>
                        <label>Ulangi password baru</label>
                        <div class="secret-field">
                            <input type="password" name="new_password_confirm" required minlength="6"
                                autocomplete="new-password" placeholder="Ulangi password baru" data-secret-input>
                            <button type="button" class="secret-toggle" data-secret-toggle aria-label="Lihat password"
                                title="Lihat password"></button>
                        </div>
                        <div class="security-form-note">Tidak ada kode yang dikirim ke email. Password saat ini
                            digunakan sebagai konfirmasi keamanan.</div>
                        <button type="submit" class="btn primary wide">Simpan Password Baru</button>
                    </form>
                </details>

                <details class="security-change-card" <?= (($_POST['action'] ?? '') === 'change_pin') ? 'open' : '' ?>>
                    <summary>
                        <span class="security-change-icon pin"><?= ck_icon('hash') ?></span>
                        <span><b>Ubah PIN</b><small>PIN digunakan untuk membuka aplikasi pada perangkat
                                terpercaya.</small></span>
                        <span class="security-chevron">⌄</span>
                    </summary>
                    <form method="post" class="security-change-form" autocomplete="off"><?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                        <input type="hidden" name="action" value="change_pin">
                        <label>PIN saat ini</label>
                        <div class="secret-field">
                            <input class="pin-input" type="password" name="current_pin" required inputmode="numeric"
                                pattern="[0-9]{4,6}" minlength="4" maxlength="6" autocomplete="off"
                                placeholder="4–6 digit" data-secret-input>
                            <button type="button" class="secret-toggle" data-secret-toggle aria-label="Lihat PIN"
                                title="Lihat PIN"></button>
                        </div>
                        <label>PIN baru</label>
                        <div class="secret-field">
                            <input class="pin-input" type="password" name="new_pin" required inputmode="numeric"
                                pattern="[0-9]{4,6}" minlength="4" maxlength="6" autocomplete="new-password"
                                placeholder="4–6 digit" data-secret-input>
                            <button type="button" class="secret-toggle" data-secret-toggle aria-label="Lihat PIN"
                                title="Lihat PIN"></button>
                        </div>
                        <label>Ulangi PIN baru</label>
                        <div class="secret-field">
                            <input class="pin-input" type="password" name="new_pin_confirm" required inputmode="numeric"
                                pattern="[0-9]{4,6}" minlength="4" maxlength="6" autocomplete="new-password"
                                placeholder="Ulangi PIN baru" data-secret-input>
                            <button type="button" class="secret-toggle" data-secret-toggle aria-label="Lihat PIN"
                                title="Lihat PIN"></button>
                        </div>
                        <div class="security-form-note">Tidak memerlukan verifikasi email. Masukkan PIN lama yang benar
                            sebelum menggantinya.</div>
                        <button type="submit" class="btn primary wide">Simpan PIN Baru</button>
                    </form>
                </details>

                <div class="device-security-block">
                    <div class="device-security-head">
                        <div>
                            <b>Daftar Perangkat yang Login</b>
                            <small>Perangkat terpercaya yang pernah masuk dengan akun ini. Keluarkan perangkat yang
                                tidak Anda kenali.</small>
                        </div>
                        <span class="device-count-badge" id="loginDeviceCount"><?= count($loginDevices) ?>
                            PERANGKAT</span>
                    </div>

                    <div class="device-list" id="loginDeviceList">
                        <?php if (!$loginDevices): ?>
                        <div class="device-empty-state">Belum ada perangkat terpercaya yang tercatat.</div>
                        <?php else: ?>
                        <?php foreach ($loginDevices as $device): ?>
                        <article class="device-card <?= !empty($device['current']) ? 'current' : '' ?>">
                            <div class="device-icon" aria-hidden="true">
                                <?php if (($device['os'] ?? '') === 'iPhone' || ($device['os'] ?? '') === 'Android'): ?><?= ck_icon('smartphone') ?><?php elseif (($device['os'] ?? '') === 'iPad'): ?><?= ck_icon('tablet') ?><?php else: ?><?= ck_icon('desktop') ?><?php endif; ?>
                            </div>
                            <div class="device-card-copy">
                                <div class="device-card-title">
                                    <strong><?= h($device['name'] ?: 'Perangkat tidak dikenal') ?></strong>
                                    <?php if (!empty($device['current'])): ?><span class="device-current-chip">PERANGKAT
                                        INI</span><?php endif; ?>
                                </div>
                                <span><?= h(($device['browser'] ?? 'Browser') . ' · ' . ($device['os'] ?? 'Perangkat')) ?></span>
                                <small>
                                    Aktif terakhir
                                    <?= !empty($device['last_used_at']) ? h(date('d/m/Y H:i', strtotime($device['last_used_at']))) : '-' ?>
                                    <?php if (!empty($device['last_ip'])): ?> · IP
                                    <?= h($device['last_ip']) ?><?php endif; ?>
                                </small>
                                <?php if (!empty($device['created_at'])): ?><small>Login pertama
                                    <?= h(date('d/m/Y H:i', strtotime($device['created_at']))) ?></small><?php endif; ?>
                            </div>
                            <div class="device-card-action">
                                <?php if (!empty($device['current'])): ?>
                                <span class="device-safe-label">Aktif sekarang</span>
                                <?php else: ?>
                                <form method="post"
                                    onsubmit="return confirm('Keluarkan perangkat ini dari akun? Perangkat tersebut harus login kembali.');"><?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                                    <input type="hidden" name="action" value="revoke_device">
                                    <input type="hidden" name="device_id" value="<?= h($device['id']) ?>">
                                    <button type="submit" class="device-revoke-btn">Keluarkan</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </article>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div id="loginDeviceRevokeAll" <?= count($loginDevices) > 1 ? '' : 'hidden' ?>>
                        <form method="post" class="device-revoke-all-form"
                            onsubmit="return confirm('Keluarkan SEMUA perangkat lain? Hanya perangkat yang sedang Anda gunakan yang akan tetap login.');"><?= \yii\helpers\Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                            <input type="hidden" name="action" value="revoke_other_devices">
                            <button type="submit" class="btn secondary wide device-revoke-all-btn">Keluarkan Semua
                                Perangkat Lain</button>
                        </form>
                    </div>

                    <div class="device-security-note">Jika ada perangkat yang tidak Anda kenali, keluarkan perangkat
                        tersebut lalu ubah password untuk keamanan tambahan.</div>
                </div>
            </section>
        </div>
    </dialog>

    <dialog id="settingModal" class="initial-wallet-dialog" aria-labelledby="initialWalletTitle">
        <form method="dialog" class="modal-card">
            <div class="modal-head">
                <h3 id="initialWalletTitle">Atur Saldo Awal</h3><button type="submit" class="icon-btn" value="cancel"
                    formnovalidate aria-label="Tutup">×</button>
            </div>
            <p class="muted">Atur saldo awal setiap dompet. Saldo saat ini akan dihitung ulang bersama transaksi yang
                sudah tercatat.</p>
            <button type="button" class="btn secondary initial-wallet-add" id="addInitialWallet">＋ Tambah
                Dompet</button>
            <div id="initialWalletList"></div>
            <div class="modal-actions"><button class="btn secondary" value="cancel" formnovalidate>Batal</button><button
                    class="btn primary" id="saveSetting" type="button">Simpan Perubahan</button></div>
        </form>
    </dialog>
    <script>
    // Migrasi cache satu kali: membersihkan Service Worker lama yang mengabaikan ?v=.
    (function() {
        var CACHE_FIX_VERSION = 'finance-cache-fix-v8';
        try {
            if (!('serviceWorker' in navigator) || localStorage.getItem('financeCacheFix') === CACHE_FIX_VERSION)
                return;
            window.addEventListener('load', function() {
                var unregister = navigator.serviceWorker.getRegistrations()
                    .then(function(regs) {
                        return Promise.all(regs.map(function(reg) {
                            return reg.unregister();
                        }));
                    })
                    .catch(function() {
                        return [];
                    });
                var clearCaches = ('caches' in window ? caches.keys().then(function(keys) {
                    return Promise.all(keys.filter(function(key) {
                        return key.indexOf('finance-shell-') === 0;
                    }).map(function(key) {
                        return caches.delete(key);
                    }));
                }) : Promise.resolve());
                Promise.all([unregister, clearCaches]).then(function() {
                    localStorage.setItem('financeCacheFix', CACHE_FIX_VERSION);
                    window.location.reload();
                }).catch(function() {
                    localStorage.setItem('financeCacheFix', CACHE_FIX_VERSION);
                });
            }, {
                once: true
            });
        } catch (e) {}
    })();
    </script>
    <script>
    (function() {
        var modal = document.getElementById('emailSecurityModal');
        var openSidebar = document.getElementById('openEmailSecurity');
        var openBanner = document.getElementById('openEmailSecurityBanner');
        var closeBtn = document.getElementById('closeEmailSecurity');

        function openEmailSecurity() {
            if (!modal) return;
            try {
                modal.showModal();
            } catch (e) {
                modal.setAttribute('open', 'open');
            }
        }

        function closeEmailSecurity() {
            if (!modal) return;
            try {
                modal.close();
            } catch (e) {
                modal.removeAttribute('open');
            }
        }

        if (openSidebar) openSidebar.addEventListener('click', openEmailSecurity);
        if (openBanner) openBanner.addEventListener('click', openEmailSecurity);
        if (closeBtn) closeBtn.addEventListener('click', closeEmailSecurity);
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeEmailSecurity();
            });
        }

        var requested = <?= $emailSecurityRequested ? 'true' : 'false' ?>;
        var missingEmail = <?= !$emailStatus['has_email'] ? 'true' : 'false' ?>;
        var autoKey = 'finance-email-security-prompt-v12';
        if (requested) {
            setTimeout(openEmailSecurity, 80);
        } else if (missingEmail) {
            try {
                if (!sessionStorage.getItem(autoKey)) {
                    sessionStorage.setItem(autoKey, '1');
                    setTimeout(openEmailSecurity, 450);
                }
            } catch (e) {}
        }
    })();
    </script>
    <script>
    // ===== v16: daftar perangkat realtime =====
    (function() {
        var listEl = document.getElementById('loginDeviceList');
        var countEl = document.getElementById('loginDeviceCount');
        var revokeAllWrap = document.getElementById('loginDeviceRevokeAll');
        var securityModal = document.getElementById('emailSecurityModal');
        var pollTimer = null;
        var heartbeatTimer = null;
        var latestServerTime = Math.floor(Date.now() / 1000);

        function esc(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/\"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function relativeLastSeen(ts, online, current) {
            if (current) return 'Aktif sekarang';
            if (online) return 'Online';
            ts = Number(ts || 0);
            if (!ts) return 'Aktivitas terakhir tidak diketahui';
            var diff = Math.max(0, latestServerTime - ts);
            if (diff < 60) return 'Aktif ' + diff + ' detik lalu';
            if (diff < 3600) return 'Aktif ' + Math.floor(diff / 60) + ' menit lalu';
            if (diff < 86400) return 'Aktif ' + Math.floor(diff / 3600) + ' jam lalu';
            return 'Aktif ' + Math.floor(diff / 86400) + ' hari lalu';
        }

        function formatDate(value) {
            if (!value) return '-';
            var normalized = String(value).replace(' ', 'T');
            var d = new Date(normalized);
            if (isNaN(d.getTime())) return esc(value);
            try {
                return d.toLocaleString('id-ID', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (e) {
                return esc(value);
            }
        }

        function iconFor(os) {
            os = String(os || '');
            if (os === 'iPhone' || os === 'Android') return window.financeLineIcon ? window.financeLineIcon('smartphone') : '';
            if (os === 'iPad') return window.financeLineIcon ? window.financeLineIcon('tablet') : '';
            return window.financeLineIcon ? window.financeLineIcon('desktop') : '';
        }

        function renderDevices(devices) {
            if (!listEl) return;
            devices = Array.isArray(devices) ? devices : [];
            if (countEl) countEl.textContent = devices.length + ' PERANGKAT';
            if (revokeAllWrap) revokeAllWrap.hidden = devices.length <= 1;

            if (!devices.length) {
                listEl.innerHTML =
                    '<div class="device-empty-state">Belum ada perangkat terpercaya yang tercatat.</div>';
                return;
            }

            listEl.innerHTML = devices.map(function(device) {
                var current = !!device.current;
                var online = !!device.online;
                var statusText = relativeLastSeen(device.last_used_ts, online, current);
                var statusClass = (online || current) ? ' online' : '';
                var action = current ?
                    '<span class="device-safe-label"><i></i>Aktif sekarang</span>' :
                    '<button type="button" class="device-revoke-btn" data-realtime-revoke="' + esc(device
                        .id) + '">Keluarkan</button>';
                return '<article class="device-card' + (current ? ' current' : '') + '" data-device-id="' +
                    esc(device.id) + '">' +
                    '<div class="device-icon" aria-hidden="true">' + iconFor(device.os) + '</div>' +
                    '<div class="device-card-copy">' +
                    '<div class="device-card-title"><strong>' + esc(device.name ||
                        'Perangkat tidak dikenal') + '</strong>' +
                    (current ? '<span class="device-current-chip">PERANGKAT INI</span>' : '') + '</div>' +
                    '<span>' + esc((device.browser || 'Browser') + ' · ' + (device.os || 'Perangkat')) +
                    '</span>' +
                    '<small class="device-live-status' + statusClass + '"><i></i>' + esc(statusText) +
                    (device.last_ip ? ' · IP ' + esc(device.last_ip) : '') + '</small>' +
                    (device.created_at ? '<small>Login pertama ' + formatDate(device.created_at) +
                        '</small>' : '') +
                    '</div>' +
                    '<div class="device-card-action">' + action + '</div>' +
                    '</article>';
            }).join('');
        }

        async function requestDevices(action, formData) {
            var options = {
                credentials: 'same-origin',
                cache: 'no-store'
            };
            var url = 'index.php?r=legacy-api%2Fbridge&name=devices&action=' + encodeURIComponent(action || 'list') + '&_=' + Date.now();
            if (formData) {
                options.method = 'POST';
                options.body = formData;
                url = 'index.php?r=legacy-api%2Fbridge&name=devices&_=' + Date.now();
            }
            var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            if ((options.method || 'GET').toUpperCase() !== 'GET') {
                options.headers = Object.assign({}, options.headers || {}, {'X-CSRF-Token': csrf});
            }
            var response = await fetch(url, options);
            var data = null;
            try {
                data = await response.json();
            } catch (e) {}
            if (response.status === 401 || (data && data.logged_out)) {
                window.location.replace('index.php?device_logout=1');
                return null;
            }
            if (!response.ok || !data || !data.ok) {
                throw new Error((data && data.error) || 'Gagal memuat daftar perangkat.');
            }
            latestServerTime = Number(data.server_time || latestServerTime);
            return data;
        }

        async function refreshDevices() {
            if (!listEl) return;
            try {
                var data = await requestDevices('list');
                if (data) renderDevices(data.devices);
            } catch (e) {
                // Tidak menimpa daftar lama saat koneksi sesaat terputus.
            }
        }

        async function heartbeat() {
            try {
                await requestDevices('heartbeat');
            } catch (e) {}
        }

        async function revokeDevice(deviceId) {
            if (!deviceId) return;
            if (!window.confirm('Keluarkan perangkat ini dari akun? Perangkat tersebut harus login kembali.'))
                return;
            var fd = new FormData();
            fd.append('action', 'revoke');
            fd.append('device_id', deviceId);
            try {
                var data = await requestDevices('revoke', fd);
                if (data) renderDevices(data.devices);
            } catch (e) {
                window.alert(e.message || 'Gagal mengeluarkan perangkat.');
            }
        }

        async function revokeOthers() {
            if (!window.confirm(
                    'Keluarkan SEMUA perangkat lain? Hanya perangkat yang sedang Anda gunakan yang akan tetap login.'
                    )) return;
            var fd = new FormData();
            fd.append('action', 'revoke_others');
            try {
                var data = await requestDevices('revoke_others', fd);
                if (data) renderDevices(data.devices);
            } catch (e) {
                window.alert(e.message || 'Gagal mengeluarkan perangkat lain.');
            }
        }

        if (listEl) {
            listEl.addEventListener('click', function(e) {
                var button = e.target.closest('[data-realtime-revoke]');
                if (button) revokeDevice(button.getAttribute('data-realtime-revoke'));
            });
        }
        if (revokeAllWrap) {
            var form = revokeAllWrap.querySelector('form');
            if (form) form.addEventListener('submit', function(e) {
                e.preventDefault();
                revokeOthers();
            });
        }

        function modalIsOpen() {
            return !!(securityModal && (securityModal.open || securityModal.hasAttribute('open')));
        }

        function startPolling() {
            if (!listEl || pollTimer) return;
            refreshDevices();
            pollTimer = setInterval(function() {
                if (modalIsOpen() && document.visibilityState !== 'hidden') refreshDevices();
            }, 5000);
        }

        function stopPolling() {
            if (pollTimer) clearInterval(pollTimer);
            pollTimer = null;
        }

        if (securityModal) {
            securityModal.addEventListener('close', stopPolling);
            var observer = new MutationObserver(function() {
                if (modalIsOpen()) startPolling();
                else stopPolling();
            });
            observer.observe(securityModal, {
                attributes: true,
                attributeFilter: ['open']
            });
            if (modalIsOpen()) startPolling();
        }

        // Heartbeat berjalan selama akun sedang terbuka. Jika perangkat dikeluarkan
        // dari perangkat lain, request berikutnya langsung mengembalikan 401 dan
        // perangkat ini dipaksa kembali ke halaman login.
        heartbeat();
        heartbeatTimer = setInterval(function() {
            if (document.visibilityState !== 'hidden') heartbeat();
        }, 25000);
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                heartbeat();
                if (modalIsOpen()) refreshDevices();
            }
        });
    })();
    </script>
    <script>
    window.FINANCE_APP = {
        userId: <?= (int)$user['id'] ?>,
        username: <?= json_encode((string)$user['username'], JSON_UNESCAPED_UNICODE) ?>,
        isSuperAdmin: <?= authIsSuperAdmin($user) ? 'true' : 'false' ?>,
        syncBase: <?= json_encode($publicBase, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>,
        appVersion: "offline-sync-v1"
    };
    </script>
    <script src="assets/offline-store.js?v=<?= h($assetVersion) ?>"></script>
    <script>
    (function() {
        var card = document.getElementById('biometricSecurityCard');
        var state = document.getElementById('biometricSecurityState');
        var text = document.getElementById('biometricSecurityText');
        var note = document.getElementById('biometricSecurityNote');
        var toggle = document.getElementById('biometricSecurityToggle');
        if (!card || !state || !text || !toggle) return;

        function parseStatus(raw) {
            try {
                return typeof raw === 'string' ? JSON.parse(raw) : (raw || {});
            } catch (_) {
                return {};
            }
        }

        function render(status) {
            var nativeBridge = !!(window.AndroidBiometric && typeof window.AndroidBiometric.getStatus ===
                'function');
            var supported = !!status.supported;
            var enabled = !!status.enabled;
            var label = status.label || 'Biometrik perangkat';

            state.className = 'biometric-security-state ' + (enabled ? 'enabled' : (supported ? 'available' :
                'unavailable'));
            state.textContent = enabled ? 'AKTIF' : (supported ? 'TERSEDIA' : 'TIDAK TERSEDIA');
            toggle.disabled = !nativeBridge || !supported;
            toggle.textContent = enabled ? 'Nonaktifkan' : 'Aktifkan';
            toggle.dataset.enabled = enabled ? '1' : '0';

            if (!nativeBridge) {
                text.textContent = 'Kunci biometrik native tersedia saat aplikasi dibuka melalui aplikasi Android.';
                note.textContent =
                    'Di browser/PWA, keamanan tetap menggunakan PIN aplikasi. Face ID/Touch ID web memerlukan integrasi Passkey/WebAuthn terpisah.';
                return;
            }

            if (supported) {
                text.textContent = enabled ?
                    label +
                    ' aktif. Aplikasi akan meminta verifikasi biometrik setelah kembali dari latar belakang.' :
                    'Perangkat mendukung ' + label.toLowerCase() +
                    '. Aktifkan untuk mengunci aplikasi secara lokal.';
                note.textContent =
                    'Verifikasi diproses oleh sistem perangkat. Setelah biometrik berhasil, sesi web dilewati dari PIN; PIN tetap tersedia sebagai fallback. Aplikasi tidak menerima atau menyimpan data sidik jari/wajah.';
            } else {
                text.textContent = status.message || 'Biometrik atau kunci layar perangkat belum tersedia.';
                note.textContent =
                    'Daftarkan sidik jari/wajah atau aktifkan kunci layar perangkat, lalu buka kembali aplikasi.';
            }
        }

        function refresh() {
            if (!(window.AndroidBiometric && typeof window.AndroidBiometric.getStatus === 'function')) {
                render({
                    supported: false,
                    enabled: false
                });
                return;
            }
            try {
                render(parseStatus(window.AndroidBiometric.getStatus()));
            } catch (_) {
                render({
                    supported: false,
                    enabled: false
                });
            }
        }

        toggle.addEventListener('click', function() {
            if (!(window.AndroidBiometric)) return;
            toggle.disabled = true;
            try {
                if (toggle.dataset.enabled === '1') window.AndroidBiometric.requestDisable();
                else window.AndroidBiometric.requestEnable();
            } catch (_) {
                refresh();
            }
            setTimeout(refresh, 1200);
        });

        window.addEventListener('finance-biometric-status', function(event) {
            render((event && event.detail) || {});
        });
        document.getElementById('openEmailSecurity')?.addEventListener('click', function() {
            setTimeout(refresh, 80);
        });
        refresh();
    })();
    </script>
    <script src="assets/app.js?v=<?= h($assetVersion) ?>"></script>
    <?php endif; ?>

    <script>
    (function() {
        var ua = navigator.userAgent || '';
        var platform = navigator.platform || '';
        var isAndroid = /Android/i.test(ua);
        var isIOS = /iPhone|iPad|iPod/i.test(ua) ||
            (platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        var isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone === true;

        var androidArea = document.getElementById('androidInstallArea');
        var iosArea = document.getElementById('iosInstallArea');
        var desktopArea = document.getElementById('desktopInstallArea');
        var iosGuideButton = document.getElementById('showIosInstallGuide');
        var iosGuide = document.getElementById('iosInstallGuide');
        var iosStandaloneMessage = document.getElementById('iosStandaloneMessage');

        if (isAndroid) {
            if (androidArea) androidArea.hidden = false;
        } else if (isIOS) {
            if (iosArea) iosArea.hidden = false;

            if (isStandalone) {
                if (iosGuideButton) iosGuideButton.hidden = true;
                if (iosGuide) iosGuide.hidden = true;
                if (iosStandaloneMessage) iosStandaloneMessage.hidden = false;
            } else if (iosGuideButton && iosGuide) {
                iosGuideButton.addEventListener('click', function() {
                    iosGuide.hidden = !iosGuide.hidden;
                    iosGuideButton.textContent = iosGuide.hidden ?
                        'Pasang di Home Screen' :
                        'Tutup Petunjuk';
                });
            }
        } else {
            if (desktopArea) desktopArea.hidden = false;
        }
    })();
    </script>

    <?php if ($user && !$unlocked && !empty($nativeBiometricAutoUnlockAllowed) && !empty($nativeBiometricUnlockNonce)): ?>
    <script>
    (function() {
        var form = document.getElementById('nativeBiometricUnlockForm');
        var wait = document.getElementById('nativeBiometricWait');
        if (!form) return;
        var submitted = false;

        function parseStatus(raw) {
            try { return typeof raw === 'string' ? JSON.parse(raw) : (raw || {}); }
            catch (_) { return {}; }
        }

        function tryNativeBiometricUnlock(status) {
            if (submitted || !status || !status.supported || !status.enabled) return;
            submitted = true;
            if (wait) wait.hidden = false;
            // Aplikasi native tetap menjadi gate yang menutup UI sampai Face ID /
            // sidik jari / credential perangkat berhasil. Web hanya menghilangkan
            // verifikasi PIN kedua pada trusted-device session yang sama.
            window.setTimeout(function() { form.submit(); }, 60);
        }

        window.addEventListener('finance-biometric-status', function(event) {
            tryNativeBiometricUnlock((event && event.detail) || {});
        });

        if (window.AndroidBiometric && typeof window.AndroidBiometric.getStatus === 'function') {
            try { tryNativeBiometricUnlock(parseStatus(window.AndroidBiometric.getStatus())); } catch (_) {}
        }
    })();
    </script>
    <?php endif; ?>

    <script>
    (function() {
        var eyeOpen =
            '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6S2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.8"/></svg>';
        var eyeClosed =
            '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 6.2A10.8 10.8 0 0 1 12 6c6 0 9.5 6 9.5 6a16.7 16.7 0 0 1-2.5 3.2M6.1 6.1C3.8 7.7 2.5 12 2.5 12S6 18 12 18a10.8 10.8 0 0 0 3-.4"/><path d="M9.9 9.9A3 3 0 0 0 14.1 14.1"/></svg>';
        document.querySelectorAll('[data-secret-toggle]').forEach(function(button) {
            var field = button.closest('.secret-field');
            var input = field ? field.querySelector('[data-secret-input]') : null;
            if (!input) return;

            function sync() {
                var visible = input.type === 'text';
                button.innerHTML = visible ? eyeClosed : eyeOpen;
                button.setAttribute('aria-label', visible ? 'Sembunyikan' : 'Lihat');
                button.title = visible ? 'Sembunyikan' : 'Lihat';
                button.classList.toggle('is-visible', visible);
            }
            button.addEventListener('click', function() {
                input.type = input.type === 'password' ? 'text' : 'password';
                try {
                    input.focus({
                        preventScroll: true
                    });
                } catch (e) {
                    input.focus();
                }
                sync();
            });
            sync();
        });
    })();
    </script>

    </body>

</html>