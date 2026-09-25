const rupiah = (n) => "Rp" + Number(n || 0).toLocaleString("id-ID");
const esc = (s) =>
  String(s ?? "").replace(/[&<>'"]/g, (c) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;",
  })[c]);


function iconSvg(name) {
  const icons = {
    crown: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 18h16"/><path d="m5 18 1.5-9 5 4 4-7 4 7 2-4 1 9"/></svg>',
    wallet: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5h15a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-12a2 2 0 0 1 2-2h12"/><path d="M16 12h5"/><path d="M17.5 12h.01"/></svg>',
    target: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><path d="M12 2v2M22 12h-2M12 22v-2M2 12h2"/></svg>',
    receipt: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10v18l-2-1.5L12 21l-3-1.5L7 21V3Z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
    repeat: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 2l4 4-4 4"/><path d="M3 11V9a3 3 0 0 1 3-3h15"/><path d="M7 22l-4-4 4-4"/><path d="M21 13v2a3 3 0 0 1-3 3H3"/></svg>',
    flag: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 21V4"/><path d="M5 5h11l-1.5 3L16 11H5"/></svg>',
    chart: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19h16"/><path d="M7 16V9"/><path d="M12 16V5"/><path d="M17 16v-4"/></svg>',
    cloud: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 18a4 4 0 1 1 .6-7.96A5.5 5.5 0 0 1 18 12a3.5 3.5 0 1 1 0 7H7Z"/></svg>',
    lock: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 1 1 8 0v3"/></svg>',
    trash: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 10v6M14 10v6"/></svg>',
    settings: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-.4-1.1 1.7 1.7 0 0 0-1-.6 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H2.8a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.1-.4 1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V2.8a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 .4 1.1 1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.18.31.45.55.77.69.32.14.67.21 1.03.21h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.03.21c-.32.14-.59.38-.77.69Z"/></svg>',
    logout: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M20 21H12a2 2 0 0 1-2-2v-2"/><path d="M20 3H12a2 2 0 0 0-2 2v2"/></svg>',
    circleDollar: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v10"/><path d="M15 9.5c0-1.1-1.34-2-3-2s-3 .9-3 2 1.34 2 3 2 3 .9 3 2-1.34 2-3 2-3-.9-3-2"/></svg>',
    income: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 17 17 7"/><path d="M10 7h7v7"/></svg>',
    expense: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7 17 17"/><path d="M10 17h7v-7"/></svg>',
    shieldCheck: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6l-7-3Z"/><path d="m9.5 12 1.8 1.8 3.7-3.8"/></svg>',
    sparkles: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 1.6 4.4L18 9l-4.4 1.6L12 15l-1.6-4.4L6 9l4.4-1.6L12 3Z"/><path d="M19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8L19 16Z"/><path d="M5 14l.7 1.8L7.5 16l-1.8.7L5 18.5l-.7-1.8L2.5 16l1.8-.2L5 14Z"/></svg>',
    image: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.3"/><path d="m21 16-5.2-5.2a1.5 1.5 0 0 0-2.1 0L7 17"/></svg>',
    camera: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h3l1.5-2h7L17 8h3a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2Z"/><circle cx="12" cy="14" r="3.5"/></svg>',
    send: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12 20 4l-4 16-4.5-6L4 12Z"/><path d="M20 4 11.5 14"/></svg>',
    mail: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>',
    at: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M16 16.2c-1 .7-2.2 1.1-3.5 1.1-3 0-5.5-2.2-5.5-5.2S9.5 7 12.5 7c2.7 0 4.8 1.9 4.8 4.4v3.1c0 1 .8 1.8 1.8 1.8"/><path d="M14.6 12a2.6 2.6 0 1 1-5.2 0 2.6 2.6 0 0 1 5.2 0Z"/></svg>',
    fingerprint: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 11a4 4 0 1 1 8 0v1"/><path d="M6 11a6 6 0 1 1 12 0v2"/><path d="M4 12a8 8 0 1 1 16 0v2"/><path d="M10 16v1a2 2 0 0 0 4 0v-4"/><path d="M8 16v1a4 4 0 0 0 8 0v-3"/><path d="M6 16v1a6 6 0 0 0 12 0v-1"/></svg>',
    key: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="15" r="4"/><path d="M12 15h9"/><path d="M18 12v6"/><path d="M21 12v3"/></svg>',
    hash: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3 7 21M17 3l-2 18M4 9h16M3 15h16"/></svg>',
    smartphone: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 18h2"/></svg>',
    tablet: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M11.5 17h1"/></svg>',
    desktop: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8"/><path d="M12 16v4"/></svg>',
    check: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4 10-10"/></svg>',
    creditCard: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/><path d="M7 15h3"/></svg>',
    utensils: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3v8"/><path d="M4 3v5a2 2 0 0 0 4 0V3"/><path d="M10 3v18"/><path d="M16 3v8"/><path d="M16 7h4"/><path d="M20 3v18"/></svg>',
    fuel: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5h7a2 2 0 0 1 2 2v12H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/><path d="M16 9h2l2 2v6a2 2 0 0 1-4 0v-3"/><path d="M9 9h4"/></svg>',
    cart: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/><path d="M3 4h2l2.4 10.2A2 2 0 0 0 9.35 16H18a2 2 0 0 0 1.94-1.5L21 8H7"/></svg>',
    scooter: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="7" cy="17" r="2.5"/><circle cx="17" cy="17" r="2.5"/><path d="M9.5 17h5l2.2-7H11l-1 3H7"/><path d="M15 6h3l2 4"/></svg>',
    swap: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h11"/><path d="m14 4 4 3-4 3"/><path d="M17 17H6"/><path d="m10 14-4 3 4 3"/></svg>',
    bolt: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 5 14h5l-1 8 8-12h-5l1-8Z"/></svg>',
    search: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
    filter: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16"/><path d="M7 12h10"/><path d="M10 18h4"/></svg>'
  };
  return icons[name] || icons.wallet;
}
window.financeLineIcon = iconSvg;

let state = {};
let selectedPhoto = null;
let premiumProofPrepared = null;
let premiumProofPreparePromise = null;


let appInitialDataLoaded = false;
let appDataLoadSequence = 0;

function appLoaderElements() {
  return {
    root: document.getElementById("appDataLoader"),
    title: document.getElementById("appDataLoaderTitle"),
    text: document.getElementById("appDataLoaderText"),
    retry: document.getElementById("appDataLoaderRetry"),
  };
}

function showAppDataLoader(title = "Memuat data...", text = "Mengambil saldo, transaksi, chat, dan fitur keuangan.") {
  const ui = appLoaderElements();
  if (!ui.root) return;
  ui.root.hidden = false;
  ui.root.classList.remove("is-error", "is-offline-ready");
  ui.root.classList.add("is-loading");
  ui.root.setAttribute("aria-busy", "true");
  if (ui.title) ui.title.textContent = title;
  if (ui.text) ui.text.textContent = text;
  if (ui.retry) ui.retry.hidden = true;
  document.body.classList.add("app-data-loading");
}

function updateAppDataLoader(title, text) {
  const ui = appLoaderElements();
  if (ui.title && title) ui.title.textContent = title;
  if (ui.text && text) ui.text.textContent = text;
}

function hideAppDataLoader() {
  const ui = appLoaderElements();
  if (!ui.root) return;
  ui.root.classList.remove("is-loading", "is-error", "is-offline-ready");
  ui.root.setAttribute("aria-busy", "false");
  document.body.classList.remove("app-data-loading");
  window.setTimeout(() => {
    if (!ui.root.classList.contains("is-loading") && !ui.root.classList.contains("is-error")) ui.root.hidden = true;
  }, 180);
}

function showAppDataLoadError(err) {
  const ui = appLoaderElements();
  if (!ui.root) return;
  const message = String(err?.message || "Data belum berhasil dimuat.");
  ui.root.hidden = false;
  ui.root.classList.remove("is-loading", "is-offline-ready");
  ui.root.classList.add("is-error");
  ui.root.setAttribute("aria-busy", "false");
  if (ui.title) ui.title.textContent = "Data belum berhasil dimuat";
  if (ui.text) ui.text.textContent = message.length > 180 ? message.slice(0, 177) + "..." : message;
  if (ui.retry) ui.retry.hidden = false;
  document.body.classList.add("app-data-loading");
}

async function runInitialDataLoad() {
  const seq = ++appDataLoadSequence;
  showAppDataLoader();
  try {
    updateAppDataLoader("Memuat data...", "Mengambil ringkasan saldo dan transaksi.");
    await load();
    if (seq !== appDataLoadSequence) return;
    appInitialDataLoaded = true;
    updateAppDataLoader(
      state.offline_mode ? "Data offline siap" : "Data berhasil dimuat",
      state.offline_mode ? "Menampilkan salinan data terakhir dari perangkat." : "Saldo, transaksi, chat, dan fitur sudah diperbarui."
    );
    window.setTimeout(hideAppDataLoader, 220);
    await updateConnectionUi();
    if (navigator.onLine && window.FinanceOffline && (await FinanceOffline.listQueue()).length) setTimeout(() => syncOfflineQueue(false), 700);
  } catch (err) {
    if (seq !== appDataLoadSequence) return;
    showAppDataLoadError(err);
  }
}


function isPremiumUser() {
  return state.account?.role === "super_admin" || !!state.account?.plan?.active;
}

function isTrialPremium() {
  const plan = state.account?.plan || {};
  return !!plan.active && plan.source === "trial";
}

function formatPremiumDateTime(value = "") {
  const raw = String(value || "").trim();
  if (!raw) return "";
  const dateOnly = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (dateOnly) return `${dateOnly[3]}/${dateOnly[2]}/${dateOnly[1]}`;
  const normalized = raw.includes("T") ? raw : raw.replace(" ", "T");
  const d = new Date(normalized);
  if (Number.isNaN(d.getTime())) return raw;
  try {
    return new Intl.DateTimeFormat("id-ID", {
      day: "2-digit", month: "2-digit", year: "numeric",
      hour: "2-digit", minute: "2-digit"
    }).format(d).replace(",", "");
  } catch (_) {
    return raw;
  }
}

function premiumTrialState() {
  return state.account?.trial || state.account?.plan?.trial || {};
}

function premiumExpiryText() {
  const plan = state.account?.plan || {};
  if (!plan.active) return "Akun Free";
  if (plan.source === "trial") {
    const trial = premiumTrialState();
    const days = Number(trial.remaining_days || 0);
    const until = formatPremiumDateTime(trial.expires_at || plan.expires_at || "");
    if (days > 0) return `Free Trial · ${days} hari tersisa${until ? ` · sampai ${until}` : ""}`;
    return until ? `Free Trial sampai ${until}` : "Free Trial Premium aktif";
  }
  return plan.expires_at ? `Premium sampai ${formatPremiumDateTime(plan.expires_at)}` : "Premium Permanen";
}

function renderAccountPlan() {
  const premium = isPremiumUser();
  const trialActive = isTrialPremium();
  const plan = state.account?.plan || {};
  const trial = premiumTrialState();
  document.querySelectorAll(".account-plan-badge").forEach((badge) => {
    badge.classList.toggle("premium", premium);
    badge.classList.toggle("free", !premium);
    badge.textContent = trialActive ? "Trial" : (premium ? "Premium" : "Free");
  });
  const sideText = document.getElementById("sidebarPremiumText");
  const sideBadge = document.getElementById("sidebarPremiumBadge");
  if (sideText) sideText.textContent = premium ? premiumExpiryText() : "Lihat paket & status berlangganan";
  if (sideBadge) {
    sideBadge.textContent = trialActive ? "TRIAL" : (premium ? "AKTIF" : "UPGRADE");
    sideBadge.classList.toggle("active", premium);
    sideBadge.classList.toggle("trial", trialActive);
  }
  document.querySelectorAll("[data-premium-required], [data-premium-tab]").forEach((node) => {
    node.classList.toggle("premium-locked", !premium);
    node.classList.toggle("premium-unlocked", premium);
  });
  const accountStatus = document.getElementById("premiumAccountStatus");
  if (accountStatus) {
    if (trialActive) {
      const days = Number(trial.remaining_days || 0);
      accountStatus.textContent = days > 0 ? `TRIAL · ${days} HARI` : "TRIAL";
    } else {
      accountStatus.textContent = premium ? (plan.expires_at ? "PREMIUM" : "PREMIUM ∞") : "FREE";
    }
    accountStatus.classList.toggle("active", premium);
    accountStatus.classList.toggle("trial", trialActive);
  }
}

function openPremiumUpsell(message = "Fitur ini tersedia untuk akun Premium.") {
  showFeatureToast(message);
  return openFinanceCenter("premium");
}

// ===== REALTIME / AUTO REFRESH =====
// Hosting PHP gratis umumnya tidak menyediakan WebSocket server persisten.
// Karena itu aplikasi memakai polling ringan: endpoint realtime hanya mengirim
// data lengkap jika JSON akun benar-benar berubah.
let realtimeRevision = 0;
let realtimeChatSignature = "";
let realtimeTransactionSignature = "";
let realtimeAccountSignature = "";
let subscriptionState = null;
let subscriptionCouponPreview = null;
let realtimeTimer = null;
let realtimeRequestRunning = false;
const REALTIME_ACTIVE_MS = 2000;
const REALTIME_HIDDEN_MS = 8000;
const realtimeChannel = ("BroadcastChannel" in window && window.FINANCE_APP?.userId)
  ? new BroadcastChannel("finance-realtime-user-" + String(window.FINANCE_APP.userId))
  : null;

function announceRealtimeMutation() {
  try { realtimeChannel?.postMessage({ type: "data-changed", at: Date.now() }); } catch (_) {}
}

let ocrWorker = null;
let ocrWorkerPromise = null;
const OCR_CDN = "https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js";


const BALANCE_VISIBILITY_KEY = "finance_balance_hidden";
const DAILY_BUDGET_MINIMIZED_KEY = "finance_daily_budget_minimized";
const DAILY_BUDGET_DISMISSED_KEY = "finance_daily_budget_dismissed";

function balanceIsHidden() { return localStorage.getItem(BALANCE_VISIBILITY_KEY) === "1"; }
function totalAvailableFromWallets() {
  const wallets = ((state.features || {}).wallets || []).filter(w => !w.archived);
  if (!wallets.length) return null;
  return wallets.reduce((sum, w) => {
    const gross = Number(w.balance || 0);
    const reserved = Math.max(0, Number(w.reserved_balance || 0));
    const minimum = Math.max(0, Number(w.minimum_balance || 0));
    const available = Number.isFinite(Number(w.available_balance))
      ? Math.max(0, Number(w.available_balance))
      : Math.max(0, gross - reserved - minimum);
    return sum + available;
  }, 0);
}
function summaryDisplayValue(key) {
  if (key === "balance") {
    const walletTotal = totalAvailableFromWallets();
    if (walletTotal !== null) return walletTotal;
  }
  if ((key === "income" || key === "expense") && state.period_summary && Number.isFinite(Number(state.period_summary[key]))) {
    return Number(state.period_summary[key] || 0);
  }
  return Number(state.summary?.[key] || 0);
}
function balanceEyeSvg(hidden) {
  return hidden
    ? '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 6.2A10.8 10.8 0 0 1 12 6c6 0 9.5 6 9.5 6a16.7 16.7 0 0 1-2.5 3.2M6.1 6.1C3.8 7.7 2.5 12 2.5 12S6 18 12 18a10.8 10.8 0 0 0 3-.4"/><path d="M9.9 9.9A3 3 0 0 0 14.1 14.1"/></svg>'
    : '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6S2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.8"/></svg>';
}
function applyBalanceVisibility() {
  const hidden = balanceIsHidden();
  const btn = document.getElementById("balanceVisibilityToggle");
  ["balance", "income", "expense", "initial"].forEach(key => {
    const value = document.getElementById(key);
    if (value) value.textContent = hidden ? "Rp ••••••" : rupiah(summaryDisplayValue(key));
  });
  if (btn) {
    btn.innerHTML = balanceEyeSvg(hidden);
    btn.setAttribute("aria-label", hidden ? "Tampilkan saldo, pemasukan, pengeluaran, dan saldo awal" : "Sembunyikan saldo, pemasukan, pengeluaran, dan saldo awal");
    btn.title = hidden ? "Tampilkan saldo, pemasukan, pengeluaran, dan saldo awal" : "Sembunyikan saldo, pemasukan, pengeluaran, dan saldo awal";
    btn.setAttribute("aria-pressed", String(hidden));
    btn.classList.toggle("is-hidden-balance", hidden);
  }
}
function walletBalanceIcon(type = "") {
  const t = String(type || "").toLowerCase();
  if (t === "bank") return iconSvg("wallet");
  if (t === "ewallet") return iconSvg("smartphone");
  if (t === "savings") return iconSvg("flag");
  return iconSvg("wallet");
}

function categoryLineIcon(category = {}) {
  const source = `${category.name || ""} ${category.type || ""} ${category.icon || ""} ${(category.keywords || []).join(" ")}`.toLowerCase();
  if (source.includes("makan") || source.includes("minum") || source.includes("kuliner")) return iconSvg("utensils");
  if (source.includes("bensin") || source.includes("bbm") || source.includes("pertamax") || source.includes("transport")) return iconSvg("fuel");
  if (source.includes("belanja") || source.includes("shopping")) return iconSvg("cart");
  if (source.includes("cicilan") || source.includes("angsuran") || source.includes("tagihan")) return iconSvg("receipt");
  if (source.includes("transfer")) return iconSvg("swap");
  if (source.includes("gaji") || source.includes("income") || source.includes("pemasukan")) return iconSvg("circleDollar");
  if (source.includes("listrik") || source.includes("air")) return iconSvg("bolt");
  return category.type === "income" ? iconSvg("circleDollar") : iconSvg("creditCard");
}

function renderWalletBalanceDetails() {
  const modal = document.getElementById("walletBalanceModal");
  const list = document.getElementById("walletBalanceList");
  const total = document.getElementById("walletBalanceTotal");
  if (!modal || !list || !total) return;

  const wallets = ((state.features || {}).wallets || []).filter((w) => !w.archived);
  const walletTotal = totalAvailableFromWallets();
  total.textContent = rupiah(walletTotal !== null ? walletTotal : (state.summary?.balance || 0));

  if (!wallets.length) {
    list.innerHTML = '<div class="empty">Belum ada dompet atau rekening.</div>';
    return;
  }

  list.innerHTML = wallets.map((w) => `
    <div class="wallet-balance-row">
      <div class="wallet-balance-icon">${walletBalanceIcon(w.type)}</div>
      <div class="wallet-balance-name">
        <b>${esc(w.name || "Dompet")}</b>
        <small>${esc(w.type === "bank" ? "Bank" : w.type === "ewallet" ? "E-Wallet" : w.type === "savings" ? "Tabungan" : "Cash")} · total ${rupiah(w.balance || 0)}${Number(w.reserved_balance||0)>0?` · disisihkan ${rupiah(w.reserved_balance)}`:""}${Number(w.minimum_balance||0)>0?` · minimum ${rupiah(w.minimum_balance)}`:""}${Number(w.minimum_balance||0)>0 && Number(w.balance||0)<Number(w.minimum_balance||0)?` · di bawah minimum`:""}</small>
      </div>
      <strong>${rupiah(w.available_balance ?? w.balance ?? 0)}</strong>
    </div>
  `).join("");
}

function openWalletBalanceDetails() {
  renderWalletBalanceDetails();
  const modal = document.getElementById("walletBalanceModal");
  if (!modal) return;
  if (typeof modal.showModal === "function") modal.showModal();
  else modal.setAttribute("open", "open");
}

function dailyBudgetIsMinimized() { return localStorage.getItem(DAILY_BUDGET_MINIMIZED_KEY) === "1"; }
function dailyBudgetIsDismissed() { return sessionStorage.getItem(DAILY_BUDGET_DISMISSED_KEY) === "1"; }
function setDailyBudgetMinimized(minimized) {
  localStorage.setItem(DAILY_BUDGET_MINIMIZED_KEY, minimized ? "1" : "0");
  const card = document.getElementById("dailyBudgetCard");
  if (card) card.classList.toggle("is-minimized", minimized);
  syncDailyBudgetMinimizeButton();
}
function syncDailyBudgetMinimizeButton() {
  const btn = document.getElementById("budgetCardMinimize");
  const minimized = dailyBudgetIsMinimized();
  if (!btn) return;
  btn.innerHTML = minimized
    ? '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 14 5-5 5 5"/></svg>'
    : '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>';
  btn.setAttribute("aria-label", minimized ? "Besarkan peringatan" : "Kecilkan peringatan");
  btn.title = minimized ? "Besarkan peringatan" : "Kecilkan peringatan";
}

function txLocalYmd(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

function txCurrentMonthRange() {
  const now = new Date();
  return {
    from: txLocalYmd(new Date(now.getFullYear(), now.getMonth(), 1)),
    to: txLocalYmd(new Date(now.getFullYear(), now.getMonth() + 1, 0)),
  };
}

function txCurrentMonthCaption() {
  try {
    return new Intl.DateTimeFormat("id-ID", { month: "long", year: "numeric" }).format(new Date());
  } catch (_) {
    return txCurrentMonthRange().from.slice(0, 7);
  }
}

let txPeriodMode = "month"; // month | all | custom
const txInitialMonth = txCurrentMonthRange();
const txFilters = {
  type: "all",
  search: "",
  wallet_id: 0,
  category: "",
  from: txInitialMonth.from,
  to: txInitialMonth.to,
  sort: "date_desc",
};

function txIcon(category = "") {
  const c = String(category).toLowerCase();
  if (c.includes("makan") || c.includes("minum")) return "🍽️";
  if (c.includes("bensin") || c.includes("bbm") || c.includes("pertamax")) return "⛽";
  if (c.includes("belanja")) return "🛒";
  if (c.includes("cicilan") || c.includes("angsuran")) return "🧾";
  if (c.includes("transport")) return "🛵";
  if (c.includes("transfer")) return "↔️";
  if (c.includes("gaji") || c.includes("pemasukan")) return "💰";
  if (c.includes("tagihan") || c.includes("listrik") || c.includes("air")) return "⚡";
  return "💳";
}

function formatTransactionDate(value = "") {
  const raw = String(value || "").slice(0, 10);
  const m = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!m) return raw || "-";
  const months = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
  return `${Number(m[3])} ${months[Math.max(0, Number(m[2]) - 1)]} ${m[1]}`;
}

function transactionKindLabel(type = "") {
  if (type === "expense") return "Pengeluaran";
  if (type === "income") return "Pemasukan";
  if (type === "transfer") return "Transfer";
  return "Transaksi";
}

function transactionPatternLabel(value = "") {
  if (value === "daily") return "Harian";
  if (value === "recurring") return "Berulang";
  if (value === "once") return "Sekali bayar";
  return "";
}

const OFFLINE_QUEUE_ENDPOINTS = [
  "ajax/finance.php",
  "ajax/settings.php",
  "ajax/features.php",
  "ajax/edit_transaction.php",
  "ajax/delete_transaction.php",
  "ajax/delete_message.php",
  "ajax/learning.php",
];

function isMutationRequest(options = {}) {
  return String(options.method || "GET").toUpperCase() !== "GET";
}
function canQueueOffline(url, options = {}) {
  if (!window.FinanceOffline || !isMutationRequest(options)) return false;
  const clean = String(url).split("?")[0].replace(/^\//, "");
  return OFFLINE_QUEUE_ENDPOINTS.includes(clean);
}
function isNetworkError(err) {
  const msg = String(err?.message || err || "").toLowerCase();
  return !navigator.onLine || msg.includes("failed to fetch") || msg.includes("networkerror") || msg.includes("network request failed") || msg.includes("load failed");
}
function attachClientOpId(options = {}, opId = "") {
  if (!opId) return options;
  const out = { ...options };
  const headers = new Headers(options.headers || {});
  headers.set("X-Client-Op-Id", opId);
  out.headers = headers;
  if (options.body instanceof FormData) {
    if (!options.body.has("client_op_id")) options.body.append("client_op_id", opId);
    out.body = options.body;
  } else if (typeof options.body === "string" && String(headers.get("content-type") || "").includes("application/json")) {
    try {
      const obj = JSON.parse(options.body || "{}");
      if (obj && typeof obj === "object" && !Array.isArray(obj)) {
        obj.client_op_id = opId;
        out.body = JSON.stringify(obj);
      }
    } catch (_) {}
  }
  return out;
}

async function queueOfflineMutation(url, options = {}, extra = {}) {
  const rec = await FinanceOffline.queueRequest(url, options, extra);
  updateConnectionUi();
  return { ok: true, offline_queued: true, client_op_id: rec.id, message: "Tersimpan offline dan akan disinkronkan otomatis." };
}

function legacyApiUrl(url) {
  const raw = String(url || "");
  const m = raw.match(/^ajax\/([a-z0-9_]+)\.php(?:\?(.*))?$/i);
  if (!m) return raw;
  const params = new URLSearchParams(m[2] || "");
  const out = new URLSearchParams();
  out.set("r", "legacy-api/bridge");
  out.set("name", m[1].toLowerCase());
  params.forEach((value, key) => {
    if (key !== "r" && key !== "name") out.append(key, value);
  });
  return "index.php?" + out.toString();
}
window.financeLegacyApiUrl = legacyApiUrl;

function parseApiJsonPayload(raw) {
  const original = String(raw ?? "");
  const clean = original.replace(/^\uFEFF/, "").trim();
  if (!clean) throw new Error("Response server kosong.");
  try { return JSON.parse(clean); } catch (_) {}

  // Safety-net kompatibilitas: jika server lama menambahkan output setelah JSON
  // (misalnya karakter `1` atau HTML/warning), ambil hanya object/array JSON pertama.
  if (clean[0] !== "{" && clean[0] !== "[") throw new Error("Response bukan JSON.");
  const stack = [];
  let inString = false, escaped = false;
  for (let i = 0; i < clean.length; i++) {
    const ch = clean[i];
    if (inString) {
      if (escaped) { escaped = false; continue; }
      if (ch === "\\") { escaped = true; continue; }
      if (ch === '\"') inString = false;
      continue;
    }
    if (ch === '\"') { inString = true; continue; }
    if (ch === "{" || ch === "[") { stack.push(ch); continue; }
    if (ch === "}" || ch === "]") {
      const open = stack.pop();
      if (!open || (open === "{" && ch !== "}") || (open === "[" && ch !== "]")) break;
      if (!stack.length) {
        const candidate = clean.slice(0, i + 1);
        try {
          const parsed = JSON.parse(candidate);
          if (clean.slice(i + 1).trim()) console.warn("Response API memiliki trailing output dan dipulihkan otomatis.");
          return parsed;
        } catch (_) { break; }
      }
    }
  }
  throw new Error("Response JSON tidak valid.");
}
window.financeParseApiJsonPayload = parseApiJsonPayload;

async function fetchJson(url, options = {}, offlineExtra = {}) {
  const queueable = canQueueOffline(url, options);
  const opId = queueable ? (offlineExtra.clientOpId || FinanceOffline.uuid()) : "";
  let requestOptions = queueable ? attachClientOpId(options, opId) : { ...options };
  const method = String(requestOptions.method || "GET").toUpperCase();
  if (method !== "GET" && method !== "HEAD") {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
    const headers = new Headers(requestOptions.headers || {});
    if (csrf) headers.set("X-CSRF-Token", csrf);
    requestOptions = { ...requestOptions, headers };
  }
  const queueExtra = queueable ? { ...offlineExtra, clientOpId: opId } : offlineExtra;
  if (queueable && !navigator.onLine) {
    return queueOfflineMutation(url, requestOptions, queueExtra);
  }
  let r;
  try {
    const requestUrl = legacyApiUrl(url);
    r = await fetch(requestUrl, { credentials: "same-origin", cache: "no-store", ...requestOptions });
  } catch (err) {
    if (queueable && isNetworkError(err)) return queueOfflineMutation(url, requestOptions, queueExtra);
    throw err;
  }
  if (r.status === 401) {
    // Saat online, session memang perlu dibuka ulang. Queue offline tidak dihapus.
    if (navigator.onLine) location.reload();
    throw new Error("Sesi terkunci.");
  }
  const raw = await r.text();
  let j;
  try { j = parseApiJsonPayload(raw); }
  catch (_) {
    throw new Error("Endpoint " + String(url) + " tidak mengembalikan JSON valid. HTTP " + r.status + (raw ? " — " + raw.substring(0, 180) : ""));
  }
  if (!r.ok || j.ok === false) throw new Error(j.error || "Request gagal.");
  if (isMutationRequest(options) && !j.offline_queued) announceRealtimeMutation();
  return j;
}

async function fetchTransactionView() {
  const params = new URLSearchParams();
  params.set("type", txFilters.type);
  params.set("sort", txFilters.sort);
  if (txFilters.search) params.set("search", txFilters.search);
  if (txFilters.wallet_id) params.set("wallet_id", String(txFilters.wallet_id));
  if (txFilters.category) params.set("category", txFilters.category);
  if (txFilters.from) params.set("from", txFilters.from);
  if (txFilters.to) params.set("to", txFilters.to);
  return fetchJson("ajax/transactions.php?" + params.toString());
}

// Ringkasan kartu pemasukan/pengeluaran hanya mengikuti PERIODE, bukan filter
// kategori/dompet/pencarian. Saldo tetap berasal dari summary server yang
// menghitung seluruh riwayat transaksi.
async function fetchPeriodSummaryView() {
  const params = new URLSearchParams({ type: "all", sort: "date_desc" });
  if (txFilters.from) params.set("from", txFilters.from);
  if (txFilters.to) params.set("to", txFilters.to);
  return fetchJson("ajax/transactions.php?" + params.toString());
}

function offlineAmount(raw) {
  let s = String(raw || "").toLowerCase().trim().replace(/\s+/g, "").replace(/^rp\.?/i, "");
  let mult = 1;
  if (/(jt|juta)$/.test(s)) { mult = 1000000; s = s.replace(/(jt|juta)$/, ""); }
  else if (/(rb|ribu|k)$/.test(s)) { mult = 1000; s = s.replace(/(rb|ribu|k)$/, ""); }
  if (mult > 1) {
    if (/^\d+[.,]\d+$/.test(s) && !/^\d{1,3}([.,]\d{3})+$/.test(s)) return Math.round(Number(s.replace(",", ".")) * mult);
    return Number(s.replace(/[.,]/g, "")) * mult || 0;
  }
  if (/^\d{1,3}(?:\.\d{3})+$/.test(s)) return Number(s.replace(/\./g, ""));
  if (/^\d{1,3}(?:,\d{3})+$/.test(s)) return Number(s.replace(/,/g, ""));
  return Number(s.replace(/\D/g, "")) || 0;
}

function offlineCategory(text, type = "expense") {
  const x = String(text || "").toLowerCase();
  if (/bensin|bbm|pertamax|pertalite/.test(x)) return "Bensin";
  if (/makan|minum|kopi|sarapan|lunch|dinner/.test(x)) return "Makan";
  if (/cicilan|angsuran|kredit/.test(x)) return "Cicilan";
  if (/belanja|indomaret|alfamart|supermarket/.test(x)) return "Belanja";
  if (/listrik|air|internet|wifi|tagihan/.test(x)) return "Tagihan";
  if (/transport|ojol|grab|gojek|parkir/.test(x)) return "Transportasi";
  if (type === "income" && /gaji|salary/.test(x)) return "Gaji";
  return type === "income" ? "Pemasukan" : "Lainnya";
}

function offlineParsePendingFinance(record) {
  // Chat/scan yang belum tersinkron hanya dianggap draft. Saldo baru berubah
  // setelah server menampilkan konfirmasi dan pengguna menyimpannya.
  return [];
}

function localPeriodSummary(items) {
  let income = 0, expense = 0, count = 0;
  (items || []).forEach(t => {
    const date = String(t.transaction_date || "");
    if (txFilters.from && date < txFilters.from) return;
    if (txFilters.to && date > txFilters.to) return;
    count++;
    if (t.type === "income") income += Number(t.amount || 0);
    if (t.type === "expense") expense += Number(t.amount || 0);
  });
  return { income, expense, count, from: txFilters.from, to: txFilters.to, mode: txPeriodMode };
}

function localFilterTransactions(items) {
  const out = (items || []).filter(t => {
    const type = String(t.type || "");
    const date = String(t.transaction_date || "");
    if (txFilters.type !== "all" && type !== txFilters.type) return false;
    if (txFilters.from && date < txFilters.from) return false;
    if (txFilters.to && date > txFilters.to) return false;
    if (txFilters.category && String(t.category || "").toLowerCase() !== txFilters.category.toLowerCase()) return false;
    if (txFilters.wallet_id) {
      const wid = Number(txFilters.wallet_id);
      if (type === "transfer") {
        if (Number(t.from_wallet_id || 0) !== wid && Number(t.to_wallet_id || 0) !== wid) return false;
      } else if (Number(t.wallet_id || 1) !== wid) return false;
    }
    if (txFilters.search) {
      const hay = `${t.note || ""} ${t.category || ""} ${t.amount || ""}`.toLowerCase();
      if (!hay.includes(txFilters.search.toLowerCase())) return false;
    }
    return true;
  });
  out.sort((a,b) => {
    const aa=Number(a.amount||0), ab=Number(b.amount||0), da=String(a.transaction_date||""), db=String(b.transaction_date||"");
    if (txFilters.sort === "amount_desc" && aa !== ab) return ab-aa;
    if (txFilters.sort === "amount_asc" && aa !== ab) return aa-ab;
    if (da !== db) return txFilters.sort === "date_asc" ? da.localeCompare(db) : db.localeCompare(da);
    return String(b.id).localeCompare(String(a.id));
  });
  let income=0, expense=0;
  out.forEach(t=>{ if(t.type==="income") income+=Number(t.amount||0); if(t.type==="expense") expense+=Number(t.amount||0); });
  return { transactions: out, meta: { count: out.length, income, expense, ...txFilters } };
}

async function applyOfflineQueueOverlay(baseState, allTransactions) {
  if (!window.FinanceOffline) return { state: baseState, allTransactions };
  const queue = await FinanceOffline.listQueue();
  const s = JSON.parse(JSON.stringify(baseState || {}));
  const all = [...(allTransactions || [])];
  s.chats = [...(s.chats || [])];
  let chatSeq = 0;
  for (const rec of queue) {
    if (String(rec.url).split("?")[0].replace(/^\//, "") !== "ajax/finance.php") continue;
    const p = rec.preview || {};
    const label = p.message || (p.photo ? "📷 Foto tersimpan offline" : "Permintaan chat offline");
    s.chats.push({ id: `offline-chat-${rec.id}-${chatSeq++}`, role: "user", message: label, created_at: new Date(rec.created_at).toISOString(), offline_pending: true });
    s.chats.push({ id: `offline-chat-${rec.id}-${chatSeq++}`, role: "assistant", message: "⏳ Tersimpan offline. Setelah tersinkron, transaksi akan ditampilkan untuk konfirmasi sebelum disimpan.", created_at: new Date(rec.created_at).toISOString(), offline_pending: true });
  }
  return { state: s, allTransactions: all, queue };
}

async function loadFromOfflineSnapshot() {
  updateAppDataLoader("Memuat data offline...", "Server belum dapat dijangkau. Membuka salinan data terakhir di perangkat.");
  if (!window.FinanceOffline) throw new Error("Penyimpanan offline tidak tersedia.");
  const dashboard = await FinanceOffline.getSnapshot("dashboard");
  const allTransactions = await FinanceOffline.getSnapshot("all_transactions");
  if (!dashboard) throw new Error("Belum ada data offline. Buka aplikasi sekali saat online agar data disimpan ke perangkat.");
  const overlay = await applyOfflineQueueOverlay(dashboard, allTransactions || dashboard.transactions || []);
  const filtered = localFilterTransactions(overlay.allTransactions);
  const periodSummary = localPeriodSummary(overlay.allTransactions);
  state = overlay.state;
  state.transactions = filtered.transactions;
  state.transaction_meta = filtered.meta;
  state.period_summary = periodSummary;
  state.offline_mode = true;
  render();
  updateConnectionUi();
  return state;
}

async function cacheOnlineSnapshot(dashboard, allTx) {
  if (!window.FinanceOffline) return;
  try {
    await Promise.all([
      FinanceOffline.saveSnapshot("dashboard", dashboard),
      FinanceOffline.saveSnapshot("all_transactions", allTx || []),
    ]);
  } catch (_) {}
}

async function load() {
  try {
    if (!appInitialDataLoaded && document.getElementById("appDataLoader")?.classList.contains("is-loading")) {
      updateAppDataLoader("Memuat data...", "Menghubungkan ke server dan mengambil data terbaru.");
    }
    const [dashboard, txView, allView, periodView] = await Promise.all([
      fetchJson("ajax/dashboard.php"),
      fetchTransactionView(),
      fetchJson("ajax/transactions.php?type=all&sort=date_desc"),
      fetchPeriodSummaryView(),
    ]);
    state = dashboard;
    state.transactions = txView.transactions || [];
    state.transaction_meta = txView.meta || {};
    state.period_summary = {
      income: Number(periodView.meta?.income || 0),
      expense: Number(periodView.meta?.expense || 0),
      count: Number(periodView.meta?.count || 0),
      from: txFilters.from,
      to: txFilters.to,
      mode: txPeriodMode,
    };
    state.offline_mode = false;
    realtimeRevision = Number(dashboard.realtime?.revision ?? dashboard.revision ?? realtimeRevision ?? 0);
    realtimeChatSignature = String(dashboard.realtime?.chat_signature || realtimeChatSignature || "");
    realtimeTransactionSignature = String(dashboard.realtime?.transaction_signature || realtimeTransactionSignature || "");
    realtimeAccountSignature = String(dashboard.realtime?.account_signature || realtimeAccountSignature || "");
    await cacheOnlineSnapshot(dashboard, allView.transactions || []);
    render();
    updateConnectionUi();
  } catch (err) {
    if (isNetworkError(err) || !navigator.onLine) return loadFromOfflineSnapshot();
    throw err;
  }
}

async function reloadTransactionsOnly() {
  try {
    const [txView, periodView] = await Promise.all([fetchTransactionView(), fetchPeriodSummaryView()]);
    state.transactions = txView.transactions || [];
    state.transaction_meta = txView.meta || {};
    state.period_summary = {
      income: Number(periodView.meta?.income || 0),
      expense: Number(periodView.meta?.expense || 0),
      count: Number(periodView.meta?.count || 0),
      from: txFilters.from,
      to: txFilters.to,
      mode: txPeriodMode,
    };
    renderSummaryCards();
    renderTransactions();
  } catch (err) {
    if (!(isNetworkError(err) || !navigator.onLine)) throw err;
    const allTransactions = (await FinanceOffline?.getSnapshot("all_transactions")) || state.transactions || [];
    const overlay = await applyOfflineQueueOverlay(state, allTransactions);
    const filtered = localFilterTransactions(overlay.allTransactions);
    const periodSummary = localPeriodSummary(overlay.allTransactions);
    state = overlay.state;
    state.transactions = filtered.transactions;
    state.transaction_meta = filtered.meta;
    state.period_summary = periodSummary;
    renderSummaryCards();
    renderTransactions();
  }
}

function photoUrl(att) {
  return att && att.file ? legacyApiUrl("ajax/photo.php?f=" + encodeURIComponent(att.file) + "&pv=7") : "";
}

function attachmentHtml(att, compact = false) {
  if (!att || !att.file) return "";
  const url = photoUrl(att);
  return `<button type="button" class="stored-photo ${compact ? "compact" : ""}" data-photo-url="${esc(url)}" aria-label="Lihat foto"><img src="${esc(url)}" alt="Lampiran foto" loading="lazy"></button>`;
}

function bindStoredPhotos(root = document) {
  root.querySelectorAll(".stored-photo[data-photo-url]").forEach((btn) => {
    btn.addEventListener("click", () => openPhotoViewer(btn.dataset.photoUrl));
  });
}

function renderSummaryCards() {
  const s = state.summary || {};
  const period = state.period_summary || {};
  const income = document.getElementById("income");
  const expense = document.getElementById("expense");
  const initialValue = document.getElementById("initial");
  if (income) income.textContent = rupiah(period.income ?? s.income ?? 0);
  if (expense) expense.textContent = rupiah(period.expense ?? s.expense ?? 0);
  if (initialValue) initialValue.textContent = rupiah(s.initial || 0);

  const incomeLabel = document.getElementById("incomePeriodLabel");
  const expenseLabel = document.getElementById("expensePeriodLabel");
  const labelSuffix = txPeriodMode === "all" ? "semua data" : (txPeriodMode === "month" ? "bulan berjalan" : "periode terpilih");
  if (incomeLabel) incomeLabel.textContent = `Pemasukan · ${labelSuffix}`;
  if (expenseLabel) expenseLabel.textContent = `Pengeluaran · ${labelSuffix}`;
  applyBalanceVisibility();

  const initial = document.getElementById("initialBalance");
  const primaryWallet = ((state.features || {}).wallets || [])[0];
  if (initial) initial.value = primaryWallet ? Number(primaryWallet.initial_balance || 0) : (s.initial || 0);
  if (document.getElementById("walletBalanceModal")?.open) renderWalletBalanceDetails();
}

function spendingKindLabel(kind) {
  return ({daily:"Harian", once:"Sekali bayar", recurring:"Berulang"})[String(kind || "")] || "Sekali bayar";
}

function pendingWalletOptions(selected) {
  const wallets = ((state.features || {}).wallets || []).filter(w => !w.archived);
  return wallets.map(w => optionHtml(String(w.id), `${w.name} · tersedia ${rupiah(w.available_balance ?? w.balance ?? 0)}`, String(w.id) === String(selected))).join("");
}

function pendingCategoryOptions(selected, type) {
  const categories = (state.features || {}).categories || [];
  const rows = categories.filter(c => c.type === "both" || c.type === type || !c.type);
  if (selected && selected !== "Transfer Antar Dompet" && !rows.some(c => String(c.name) === String(selected))) rows.unshift({name:selected, icon:""});
  if (!rows.length) rows.push({name:"Lainnya", icon:""});
  return rows.map(c => optionHtml(c.name, `${c.icon || ""} ${c.name}`.trim(), String(c.name) === String(selected))).join("");
}

function pendingConfirmationMarkup(pending) {
  const wallets = ((state.features || {}).wallets || []).filter(w => !w.archived);
  const allBills = (state.features || {}).bills || [];
  const drafts = pending?.drafts || [];
  if (!pending || !drafts.length) return "";
  const billOptions = (candidates) => {
    const seen = new Set();
    const rows = [];
    (candidates || []).forEach(b => { if (!seen.has(Number(b.id))) { seen.add(Number(b.id)); rows.push({...b, suggested:true}); } });
    allBills.filter(b => !b.paid).forEach(b => { if (!seen.has(Number(b.id))) { seen.add(Number(b.id)); rows.push(b); } });
    return rows.map(b => optionHtml(String(b.id), `${b.suggested ? "Saran · " : ""}${b.name} · ${rupiah(b.amount)}${b.match_score ? ` · cocok ${b.match_score}%` : ""}`)).join("");
  };
  return `<div class="chat-confirm-card" data-confirmation-id="${Number(pending.id || 0)}">
    <div class="chat-confirm-head"><div><b>Konfirmasi sebelum disimpan</b><small>Jenis transaksi, nominal, tanggal, dan dompet dapat diubah sebelum disimpan.</small></div><span>${drafts.length} draft</span></div>
    ${pending.warning ? `<div class="chat-confirm-warning">⚠️ ${esc(pending.warning)}</div>` : ""}
    <div class="chat-confirm-list">${drafts.map((d, i) => {
      const type = ["expense","income","transfer"].includes(String(d.type || "")) ? String(d.type) : "expense";
      const candidates = d.bill_candidates || [];
      const singleWallet = Number(d.wallet_id || d.from_wallet_id || wallets[0]?.id || 1);
      const fromWallet = Number(d.from_wallet_id || d.wallet_id || wallets[0]?.id || 1);
      const firstOther = wallets.find(w => Number(w.id) !== fromWallet)?.id || wallets[0]?.id || 1;
      const toWallet = Number(d.to_wallet_id || firstOther);
      return `<div class="chat-confirm-row" data-confirm-index="${i}">
        <div class="chat-confirm-number">#${i+1}</div>
        <div class="chat-confirm-fields">
          <label class="chat-confirm-type-field">Jenis transaksi<select data-confirm-field="type"><option value="expense"${type==='expense'?' selected':''}>Pengeluaran</option><option value="income"${type==='income'?' selected':''}>Pemasukan</option><option value="transfer"${type==='transfer'?' selected':''}>Transfer Antar Dompet</option></select></label>
          <label>Nominal<input type="number" min="1" step="1" data-confirm-field="amount" value="${Number(d.amount || 0)}"></label>
          <label data-confirm-group="category">Kategori<select data-confirm-field="category">${pendingCategoryOptions(d.category || "Lainnya", type === "transfer" ? "expense" : type)}</select></label>
          <label>Tanggal<input type="date" data-confirm-field="transaction_date" value="${esc(d.transaction_date || new Date().toISOString().slice(0,10))}"></label>
          <label data-confirm-group="wallet">Dompet<select data-confirm-field="wallet_id">${pendingWalletOptions(singleWallet)}</select></label>
          <label data-confirm-group="from-wallet">Dompet asal<select data-confirm-field="from_wallet_id">${pendingWalletOptions(fromWallet)}</select></label>
          <label data-confirm-group="to-wallet">Dompet tujuan<select data-confirm-field="to_wallet_id">${pendingWalletOptions(toWallet)}</select></label>
          <label data-confirm-group="spending-kind">Pola<select data-confirm-field="spending_kind"><option value="daily"${d.spending_kind==='daily'?' selected':''}>Harian</option><option value="once"${d.spending_kind!=='daily'&&d.spending_kind!=='recurring'?' selected':''}>Sekali bayar</option><option value="recurring"${d.spending_kind==='recurring'?' selected':''}>Berulang</option></select></label>
          <label data-confirm-group="bill">Hubungkan tagihan<select data-confirm-field="bill_id"><option value="0">Tidak dihubungkan</option>${billOptions(candidates)}</select></label>
        </div>
        ${d.note ? `<small class="chat-confirm-note">${esc(d.note)}</small>` : ""}
      </div>`;
    }).join("")}</div>
    <div class="chat-confirm-actions"><button type="button" class="secondary-btn" data-confirm-cancel>Batal</button><button type="button" class="primary-btn" data-confirm-save>Simpan transaksi</button></div>
  </div>`;
}

function syncPendingConfirmationRow(row, resetCategory = false) {
  if (!row) return;
  const get = name => row.querySelector(`[data-confirm-field="${name}"]`);
  const type = get("type")?.value || "expense";
  const category = get("category");
  const wallet = get("wallet_id");
  const from = get("from_wallet_id");
  const to = get("to_wallet_id");
  const spending = get("spending_kind");
  const bill = get("bill_id");
  const show = (group, visible) => { const node = row.querySelector(`[data-confirm-group="${group}"]`); if (node) node.hidden = !visible; };

  show("wallet", type !== "transfer");
  show("from-wallet", type === "transfer");
  show("to-wallet", type === "transfer");
  show("category", type !== "transfer");
  show("spending-kind", type === "expense");
  show("bill", type === "expense");

  if (type === "transfer") {
    if (bill) bill.value = "0";
    if (spending) spending.value = "once";
    if (from && !from.value && wallet?.value) from.value = wallet.value;
    if (from && to && String(from.value) === String(to.value)) {
      const option = [...to.options].find(o => String(o.value) !== String(from.value));
      if (option) to.value = option.value;
    }
  } else {
    if (wallet && !wallet.value && from?.value) wallet.value = from.value;
    if (type === "income") {
      if (bill) bill.value = "0";
      if (spending) spending.value = "once";
    }
    if (category && resetCategory) {
      const preferred = type === "income" ? "Transfer" : "Lainnya";
      category.innerHTML = pendingCategoryOptions(preferred, type);
      if ([...category.options].some(o => o.value === preferred)) category.value = preferred;
      else if (category.options.length) category.selectedIndex = 0;
    }
  }
}

function wirePendingConfirmationCard(card) {
  if (!card) return;
  card.querySelectorAll('[data-confirm-index]').forEach(row => {
    syncPendingConfirmationRow(row, false);
    row.querySelector('[data-confirm-field="type"]')?.addEventListener('change', () => syncPendingConfirmationRow(row, true));
    row.querySelector('[data-confirm-field="from_wallet_id"]')?.addEventListener('change', () => syncPendingConfirmationRow(row, false));
  });
}

function collectPendingOverrides(card) {
  return [...card.querySelectorAll('[data-confirm-index]')].map(row => {
    const get = name => row.querySelector(`[data-confirm-field="${name}"]`)?.value ?? "";
    const type = get("type") || "expense";
    const base = {
      type,
      amount: Number(get("amount") || 0),
      transaction_date: get("transaction_date")
    };
    if (type === "transfer") {
      return {
        ...base,
        category: "Transfer Antar Dompet",
        from_wallet_id: Number(get("from_wallet_id") || 0),
        to_wallet_id: Number(get("to_wallet_id") || 0),
        spending_kind: "once",
        bill_id: 0
      };
    }
    return {
      ...base,
      category: get("category") || "Lainnya",
      wallet_id: Number(get("wallet_id") || 0),
      spending_kind: type === "expense" ? (get("spending_kind") || "once") : "once",
      bill_id: type === "expense" ? Number(get("bill_id") || 0) : 0
    };
  });
}

async function submitPendingConfirmation(card, action) {
  const id = Number(card?.dataset.confirmationId || 0);
  if (!id) return;
  const save = card.querySelector('[data-confirm-save]');
  const cancel = card.querySelector('[data-confirm-cancel]');
  if (save) save.disabled = true;
  if (cancel) cancel.disabled = true;
  try {
    const payload = {confirmation_action: action, confirmation_id: id};
    if (action === "confirm") {
      const overrides = collectPendingOverrides(card);
      if (overrides.some(x => x.amount <= 0 || !/^\d{4}-\d{2}-\d{2}$/.test(x.transaction_date))) throw new Error("Periksa nominal dan tanggal transaksi terlebih dahulu.");
      if (overrides.some(x => x.type === "transfer" && (!x.from_wallet_id || !x.to_wallet_id || Number(x.from_wallet_id) === Number(x.to_wallet_id)))) throw new Error("Transfer harus memakai dompet asal dan tujuan yang berbeda.");
      if (overrides.some(x => x.type !== "transfer" && !x.wallet_id)) throw new Error("Pilih dompet transaksi terlebih dahulu.");
      payload.overrides = overrides;
    }
    const result = await fetchJson("ajax/finance.php", {method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(payload)}, {kind:"finance_confirmation",preview:{confirmation_action:action}});
    await load();
    if (result?.offline_queued) showOfflineToast(action === "confirm" ? "Konfirmasi tersimpan offline dan akan dikirim saat online." : "Pembatalan tersimpan offline.");
    else showFeatureToast(action === "confirm" ? "Transaksi berhasil disimpan" : "Draft transaksi dibatalkan");
  } catch (e) {
    alert(e.message || "Konfirmasi transaksi gagal diproses.");
    if (save) save.disabled = false;
    if (cancel) cancel.disabled = false;
  }
}

function renderChats(forceBottom = false) {
  const cb = document.getElementById("chatBody");
  if (!cb) return;

  const distanceFromBottom = cb.scrollHeight - cb.scrollTop - cb.clientHeight;
  const wasNearBottom = distanceFromBottom < 90;

  cb.innerHTML = "";
  (state.chats || []).forEach((c) => {
    const row = document.createElement("div");
    row.className = "chat-row " + c.role + (c.offline_pending ? " offline-pending" : "");
    const bubble = document.createElement("div");
    bubble.className = "bubble " + c.role + (c.attachment ? " has-photo" : "") + (c.offline_pending ? " offline-pending" : "");
    bubble.innerHTML = `${attachmentHtml(c.attachment)}${c.message ? `<div class="bubble-text">${esc(c.message)}</div>` : ""}${c.offline_pending ? '<span class="offline-pending-label">menunggu sinkronisasi</span>' : ''}`;

    row.appendChild(bubble);
    if (!c.offline_pending) {
      const del = document.createElement("button");
      del.type = "button";
      del.className = "chat-delete-btn";
      del.dataset.deleteMessageId = Number(c.id);
      del.setAttribute("aria-label", "Hapus pesan");
      del.title = "Hapus pesan";
      del.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 10v6M14 10v6"/></svg>';
      row.appendChild(del);
    }
    cb.appendChild(row);
  });

  if (!(state.chats || []).length) {
    cb.innerHTML = '<div class="chat-row assistant system-greeting"><div class="bubble assistant"><div class="bubble-text">Halo! <b>Smart Chat</b> siap membantu. Selain mencatat transaksi/foto nota, Anda bisa bertanya seperti <b>“pengeluaran tanggal 10 September”</b>, <b>“pengeluaran terbesar bulan ini”</b>, <b>“saldo SeaBank”</b>, atau <b>“ringkasan keuangan bulan ini”</b>.</div></div></div>';
  }

  const pending = (state.features || {}).pending_confirmation || state.pending_confirmation || null;
  if (pending?.drafts?.length) {
    const row = document.createElement("div");
    row.className = "chat-row assistant chat-confirm-wrap";
    row.innerHTML = pendingConfirmationMarkup(pending);
    cb.appendChild(row);
    wirePendingConfirmationCard(row.querySelector('.chat-confirm-card'));
    row.querySelector('[data-confirm-save]')?.addEventListener('click', () => submitPendingConfirmation(row.querySelector('.chat-confirm-card'), 'confirm'));
    row.querySelector('[data-confirm-cancel]')?.addEventListener('click', () => submitPendingConfirmation(row.querySelector('.chat-confirm-card'), 'cancel'));
  }

  const clearChatsBtn = document.getElementById("clearChatsBtn");
  if (clearChatsBtn) clearChatsBtn.hidden = !(state.chats || []).length;
  document.querySelectorAll("[data-delete-message-id]").forEach((btn) => btn.addEventListener("click", () => delMessage(Number(btn.dataset.deleteMessageId))));
  bindStoredPhotos(cb);

  if (forceBottom || wasNearBottom) cb.scrollTop = cb.scrollHeight;
}

function render() {
  renderAccountPlan();
  renderSummaryCards();
  renderFeatureSelectOptions();
  renderChats(true);

  renderTransactions();

  renderDailyBudget();
  renderLearningPending();
  if (typeof renderFinanceCenter === "function" && document.getElementById("financeCenter")?.open) renderFinanceCenter();
  if (typeof checkFinanceNotifications === "function") setTimeout(checkFinanceNotifications, 0);
}

// Invoice-style, read-only preview of the transaction already loaded for this account.
function transactionDetailMarkup(t, wallets = []) {
  const names = Object.fromEntries(wallets.map(w => [String(w.id), w.name]));
  const wallet = id => names[String(id)] || (id ? `Dompet #${id}` : 'Tidak tersedia');
  const pending = !!t.offline_pending;
  const transfer = t.type === 'transfer';
  const type = transfer ? 'Transfer Antar Dompet' : (t.type === 'income' ? 'Pemasukan' : 'Pengeluaran');
  const tone = transfer ? 'transfer' : (t.type === 'income' ? 'income' : 'expense');
  const rawDate = String(t.transaction_date || '');
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(rawDate);
  const date = match ? `${match[3]}/${match[2]}/${match[1]}` : rawDate || 'Tidak tersedia';
  const ref = pending ? `LOKAL-${String(t.id)}` : `TRX-${String(t.id).padStart(6, '0')}`;
  const sources = {bill:'Pembayaran tagihan',recurring:'Transaksi berulang',receipt_scan:'Scan nota',chat_with_photo:'Chat dengan foto',wallet_transfer:'Transfer dompet',offline_pending:'Catatan offline',manual:'Catatan manual'};
  const source = sources[t.source] || (t.source ? String(t.source) : 'Catatan transaksi');
  const billRow = ((state.features || {}).bills || []).find(b => Number(b.id) === Number(t.bill_id || 0));
  const billLabel = billRow ? `${billRow.name} · ${rupiah(billRow.amount)}` : (t.bill_id ? `Tagihan #${t.bill_id}` : '');
  const field = (label, value) => `<div><dt>${esc(label)}</dt><dd>${esc(String(value))}</dd></div>`;
  const route = transfer ? field('Dari dompet',wallet(t.from_wallet_id))+field('Ke dompet',wallet(t.to_wallet_id)) : field('Dompet',wallet(t.wallet_id || 1));
  const photo = photoUrl(t.attachment);
  return `<header class="tx-invoice-brand"><div><span class="tx-invoice-eyebrow">CATATAN KEUANGAN</span><h2 id="txDetailTitle">Bukti Transaksi</h2></div><span class="tx-invoice-status ${pending?'pending':''}">${pending?'Menunggu sinkronisasi':'Tercatat'}</span></header>
    <div class="tx-invoice-reference"><span>${esc(ref)}</span><span>${esc(date)}</span></div>
    <section class="tx-invoice-amount ${tone}"><span>${esc(type)}</span><strong>${esc(rupiah(t.amount))}</strong><small>${transfer?'Perpindahan dana antar-dompet':t.type==='income'?'Dana masuk':'Dana keluar'}</small></section>
    <dl class="tx-invoice-meta">${field('Kategori',t.category || 'Lainnya')}${!transfer?field('Pola pengeluaran',spendingKindLabel(t.spending_kind || (t.source==='recurring'?'recurring':t.source==='bill'?'once':'daily'))):''}${t.bill_id?field('Terhubung ke tagihan',billLabel):''}${route}${field('Sumber pencatatan',source)}${field('Waktu pencatatan',t.created_at || 'Tidak tersedia')}</dl>
    <section class="tx-invoice-note"><h3>Catatan</h3><p>${esc(t.note || 'Tidak ada catatan tambahan.')}</p></section>
    <div class="tx-invoice-total"><span>Total ${transfer?'transfer':'transaksi'}</span><strong>${esc(rupiah(t.amount))}</strong></div>
    ${photo?`<details class="tx-invoice-proof"><summary>Lihat bukti terlampir</summary><img src="${esc(photo)}" alt="Bukti transaksi terlampir" loading="lazy"><p class="tx-proof-error" hidden>Bukti belum dapat dimuat. Coba kembali saat terhubung internet.</p></details>`:''}
    <footer class="tx-invoice-foot">${pending?'Catatan ini masih tersimpan di perangkat dan belum tersinkron ke server.':'Ringkasan dari catatan keuangan pribadi.'}<br>Referensi TRX adalah nomor catatan aplikasi, bukan nomor invoice penjual.</footer>`;
}

function openTransactionDetail(id) {
  const t = (state.transactions || []).find(row => String(row.id) === String(id));
  if (!t) return;
  let dialog = document.getElementById('txDetailDialog');
  if (!dialog) {
    dialog = document.createElement('dialog');
    dialog.id = 'txDetailDialog';
    dialog.className = 'tx-detail-dialog';
    dialog.setAttribute('aria-labelledby', 'txDetailTitle');
    dialog.innerHTML = '<div class="tx-detail-toolbar"><span>Detail transaksi</span><button type="button" class="tx-detail-close" aria-label="Tutup detail transaksi">×</button></div><article class="tx-invoice"></article>';
    document.body.appendChild(dialog);
    dialog.querySelector('.tx-detail-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
      const rect = dialog.getBoundingClientRect();
      if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
    });
  }
  dialog.querySelector('.tx-invoice').innerHTML = transactionDetailMarkup(t, (state.features || {}).wallets || []);
  const img = dialog.querySelector('.tx-invoice-proof img');
  if (img) img.addEventListener('error', () => {
    img.hidden = true;
    dialog.querySelector('.tx-proof-error').hidden = false;
  }, {once:true});
  if (!dialog.open) dialog.showModal();
  dialog.scrollTop = 0;
  dialog.querySelector('.tx-detail-close').focus({preventScroll:true});
}

function renderTransactions() {
  const transactions = state.transactions || [];
  const meta = state.transaction_meta || {};
  const list = document.getElementById("txList");
  if (!list) return;

  const wallets = Object.fromEntries(((state.features || {}).wallets || []).map(w => [Number(w.id), w.name]));
  list.innerHTML = "";
  transactions.forEach((t) => {
    const isTransfer = t.type === "transfer";
    const sign = t.type === "expense" ? "-" : (t.type === "income" ? "+" : "↔ ");
    const cls = t.type === "expense" ? "expense" : (t.type === "income" ? "income" : "transfer");
    const x = document.createElement("div");
    x.className = `tx tx-card tx-${cls}` + (t.offline_pending ? " offline-pending" : "");

    const walletLabel = isTransfer
      ? `${wallets[Number(t.from_wallet_id)] || "Dompet"} → ${wallets[Number(t.to_wallet_id)] || "Dompet"}`
      : (wallets[Number(t.wallet_id || 1)] || "Utama");
    const pattern = transactionPatternLabel(String(t.spending_kind || ""));
    const typeText = transactionKindLabel(String(t.type || ""));
    const displayDate = formatTransactionDate(t.transaction_date);
    const note = String(t.note || "").trim();

    const statusBadges = [
      `<span class="tx-kind-badge ${cls}">${esc(typeText)}</span>`,
      pattern ? `<span class="tx-pattern-badge">${esc(pattern)}</span>` : "",
      t.source === "receipt_scan" ? '<span class="scan-badge">scan nota</span>' : "",
      t.offline_pending ? '<span class="sync-badge">offline</span>' : ""
    ].filter(Boolean).join("");

    const detailAction = `<button type="button" class="tx-detail-btn" data-detail-id="${esc(String(t.id))}" aria-label="Lihat detail transaksi">Detail</button>`;
    const actions = detailAction + (t.offline_pending
      ? '<span class="pending-sync-text">Menunggu sinkronisasi</span>'
      : `<button type="button" class="tx-edit-btn" data-edit-id="${Number(t.id)}">Edit</button><button type="button" data-delete-id="${Number(t.id)}">Hapus</button>`);

    x.innerHTML = `
      <div class="tx-card-head">
        <div class="tx-main">
          ${t.attachment ? attachmentHtml(t.attachment, true) : `<div class="tx-icon" aria-hidden="true">${txIcon(t.category)}</div>`}
          <div class="tx-info">
            <div class="tx-title-row">
              <b class="tx-title">${esc(t.category || "Lainnya")}</b>
              <div class="tx-badges">${statusBadges}</div>
            </div>
            <div class="tx-meta">
              <span class="tx-meta-item tx-meta-date"><span aria-hidden="true">◷</span>${esc(displayDate)}</span>
              <span class="tx-meta-item tx-meta-wallet"><span aria-hidden="true">${isTransfer ? "↔" : "⌑"}</span>${esc(walletLabel)}</span>
            </div>
          </div>
        </div>
        <div class="tx-right">
          <small class="tx-amount-label">${esc(typeText)}</small>
          <b class="${cls}">${sign}${rupiah(t.amount)}</b>
        </div>
      </div>
      <div class="tx-note${note ? "" : " is-empty"}">
        <span class="tx-note-label">Keterangan</span>
        <p>${esc(note || "Tanpa keterangan")}</p>
      </div>
      <div class="tx-card-footer">
        <div class="tx-card-id">#${esc(String(t.id))}</div>
        <div class="tx-actions">${actions}</div>
      </div>`;
    list.appendChild(x);
  });

  if (!transactions.length) {
    list.innerHTML = '<div class="empty tx-empty-filter">Tidak ada transaksi yang cocok dengan filter.<br><button type="button" id="txEmptyReset">Reset filter</button></div>';
    document.getElementById("txEmptyReset")?.addEventListener("click", resetTransactionFilters);
  }

  const count = Number(meta.count ?? transactions.length);
  const txCount = document.getElementById("txCount");
  if (txCount) txCount.textContent = count;
  const mobileCount = document.getElementById("mobileTxCount");
  if (mobileCount) mobileCount.textContent = count;

  const filteredCount = document.getElementById("txFilteredCount");
  const filteredExpense = document.getElementById("txFilteredExpense");
  const filteredIncome = document.getElementById("txFilteredIncome");
  if (filteredCount) filteredCount.textContent = count;
  if (filteredExpense) filteredExpense.textContent = rupiah(meta.expense || 0);
  if (filteredIncome) filteredIncome.textContent = rupiah(meta.income || 0);

  syncTransactionFilterUi();
  document.querySelectorAll("[data-delete-id]").forEach((btn) => btn.addEventListener("click", () => delTx(Number(btn.dataset.deleteId))));
  document.querySelectorAll("[data-edit-id]").forEach((btn) => btn.addEventListener("click", () => openTransactionEdit(Number(btn.dataset.editId))));
  list.querySelectorAll("[data-detail-id]").forEach(btn => btn.addEventListener("click", () => openTransactionDetail(btn.dataset.detailId)));
  bindStoredPhotos(list);
}

function transactionFilterCount() {
  let count = 0;
  if (txFilters.type !== "all") count++;
  if (txFilters.wallet_id) count++;
  if (txFilters.category) count++;
  // Bulan berjalan adalah tampilan default, bukan dianggap filter tambahan.
  if (txPeriodMode === "custom") {
    if (txFilters.from) count++;
    if (txFilters.to) count++;
  }
  if (txFilters.sort !== "date_desc") count++;
  return count;
}

function syncTransactionPeriodUi() {
  document.querySelectorAll("[data-tx-period]").forEach(btn => {
    const active = btn.dataset.txPeriod === txPeriodMode;
    btn.classList.toggle("active", active);
    btn.setAttribute("aria-pressed", String(active));
  });
  const caption = document.getElementById("txPeriodCaption");
  if (!caption) return;
  if (txPeriodMode === "month") caption.textContent = `Bulan berjalan · ${txCurrentMonthCaption()}`;
  else if (txPeriodMode === "all") caption.textContent = "Seluruh riwayat transaksi";
  else {
    const a = txFilters.from || "awal";
    const b = txFilters.to || "sekarang";
    caption.textContent = `Rentang khusus · ${a} s.d. ${b}`;
  }
}

function syncTransactionFilterUi() {
  const type = document.getElementById("txFilterType");
  const search = document.getElementById("txFilterSearch");
  const wallet = document.getElementById("txFilterWallet");
  const category = document.getElementById("txFilterCategory");
  const from = document.getElementById("txFilterFrom");
  const to = document.getElementById("txFilterTo");
  const sort = document.getElementById("txFilterSort");
  if (type) type.value = txFilters.type;
  if (search) search.value = txFilters.search;
  if (wallet) wallet.value = String(txFilters.wallet_id || 0);
  if (category) category.value = txFilters.category;
  if (from) { from.value = txFilters.from; from.max = txFilters.to || ""; }
  if (to) { to.value = txFilters.to; to.min = txFilters.from || ""; }
  if (sort) sort.value = txFilters.sort;
  syncTransactionPeriodUi();

  const n = transactionFilterCount();
  const badge = document.getElementById("txFilterActiveCount");
  const toggle = document.getElementById("txFilterToggle");
  if (badge) {
    badge.textContent = n;
    badge.hidden = n === 0;
  }
  if (toggle) toggle.classList.toggle("has-active-filter", n > 0);
}

function readTransactionFilterForm() {
  const from = document.getElementById("txFilterFrom")?.value || "";
  const to = document.getElementById("txFilterTo")?.value || "";
  if (from && to && from > to) throw new Error("Tanggal awal tidak boleh lebih besar dari tanggal akhir.");
  txFilters.type = document.getElementById("txFilterType")?.value || "all";
  txFilters.search = (document.getElementById("txFilterSearch")?.value || "").trim();
  txFilters.wallet_id = Number(document.getElementById("txFilterWallet")?.value || 0);
  txFilters.category = document.getElementById("txFilterCategory")?.value || "";
  txFilters.from = from;
  txFilters.to = to;
  txFilters.sort = document.getElementById("txFilterSort")?.value || "date_desc";
  const month = txCurrentMonthRange();
  if (from === month.from && to === month.to) txPeriodMode = "month";
  else if (!from && !to) txPeriodMode = "all";
  else txPeriodMode = "custom";
}

async function applyTransactionFilters() {
  try {
    readTransactionFilterForm();
    await reloadTransactionsOnly();
    if (window.matchMedia("(max-width: 760px)").matches) setTransactionFilterPanel(false);
  } catch (err) {
    alert(err.message);
  }
}

async function resetTransactionFilters() {
  const month = txCurrentMonthRange();
  txPeriodMode = "month";
  txFilters.type = "all";
  txFilters.search = "";
  txFilters.wallet_id = 0;
  txFilters.category = "";
  txFilters.from = month.from;
  txFilters.to = month.to;
  txFilters.sort = "date_desc";
  syncTransactionFilterUi();
  try { await reloadTransactionsOnly(); } catch (err) { alert(err.message); }
}

async function setTransactionPeriod(mode) {
  if (!['month','all'].includes(mode)) return;
  txPeriodMode = mode;
  if (mode === 'month') {
    const month = txCurrentMonthRange();
    txFilters.from = month.from;
    txFilters.to = month.to;
  } else {
    txFilters.from = '';
    txFilters.to = '';
  }
  syncTransactionFilterUi();
  try { await reloadTransactionsOnly(); } catch (err) { alert(err.message); }
}

function setTransactionFilterPanel(open) {
  const panel = document.getElementById("txFilterPanel");
  const toggle = document.getElementById("txFilterToggle");
  if (!panel || !toggle) return;
  panel.hidden = !open;
  toggle.setAttribute("aria-expanded", open ? "true" : "false");
  toggle.classList.toggle("is-open", open);
}

function downloadTransactionReport() {
  if (!isPremiumUser()) return openPremiumUpsell("Laporan PDF tersedia untuk akun Premium.");
  const params = new URLSearchParams();
  params.set("type", txFilters.type);
  params.set("sort", txFilters.sort);
  if (txFilters.search) params.set("search", txFilters.search);
  if (txFilters.wallet_id) params.set("wallet_id", String(txFilters.wallet_id));
  if (txFilters.category) params.set("category", txFilters.category);
  if (txFilters.from) params.set("from", txFilters.from);
  if (txFilters.to) params.set("to", txFilters.to);

  // Filter yang dikirim adalah filter AKTIF yang sedang menghasilkan daftar transaksi di layar.
  const url = legacyApiUrl("ajax/report.php?" + params.toString());
  const a = document.createElement("a");
  a.href = url;
  a.style.display = "none";
  document.body.appendChild(a);
  a.click();
  a.remove();
}

function renderDailyBudget() {
  const b = state.daily_budget || {};
  const card = document.getElementById("dailyBudgetCard");
  if (!card) return;
  const alertStatuses = ["warning", "reached", "exceeded"];
  const navAlert = document.getElementById("budgetNavAlert");
  const openBtn = document.getElementById("openBudget");
  card.classList.remove("status-safe", "status-warning", "status-reached", "status-exceeded");
  if (openBtn) openBtn.classList.remove("budget-warning", "budget-reached", "budget-exceeded");
  if (!b.active) {
    card.classList.add("is-hidden");
    document.body.classList.remove("has-daily-budget");
    if (navAlert) navAlert.hidden = true;
    return;
  }
  if (dailyBudgetIsDismissed()) {
    card.classList.add("is-hidden");
    document.body.classList.remove("has-daily-budget");
  } else {
    card.classList.remove("is-hidden");
    card.classList.toggle("is-minimized", dailyBudgetIsMinimized());
    document.body.classList.add("has-daily-budget");
  }
  card.classList.add("status-" + (b.status || "safe"));
  const icons = { safe: iconSvg("shieldCheck"), warning: iconSvg("target"), reached: iconSvg("target"), exceeded: iconSvg("expense") };
  document.getElementById("dailyBudgetIcon").innerHTML = icons[b.status] || iconSvg("shieldCheck");
  document.getElementById("dailyBudgetLabel").textContent = "Batas pengeluaran " + (b.day_name || "hari ini");
  document.getElementById("dailyBudgetStatus").textContent = b.label || "";
  document.getElementById("dailyBudgetSpent").textContent = rupiah(b.spent);
  document.getElementById("dailyBudgetLimit").textContent = rupiah(b.limit);
  document.getElementById("dailyBudgetProgress").style.width = Math.max(0, Math.min(100, Number(b.progress_percent || 0))) + "%";
  document.getElementById("dailyBudgetMessage").textContent = b.message || "";
  const remaining = document.getElementById("dailyBudgetRemaining");
  remaining.textContent = b.status === "exceeded" ? "Lebih " + rupiah(b.over) : "Sisa " + rupiah(b.remaining);
  if (navAlert) {
    navAlert.hidden = !alertStatuses.includes(b.status);
    navAlert.textContent = b.status === "warning" ? "!" : "!!";
    navAlert.className = "budget-nav-alert " + (b.status || "");
  }
  if (openBtn && alertStatuses.includes(b.status)) openBtn.classList.add("budget-" + b.status);
  syncDailyBudgetMinimizeButton();
}

document.getElementById("balanceVisibilityToggle")?.addEventListener("click", (event) => {
  event.stopPropagation();
  localStorage.setItem(BALANCE_VISIBILITY_KEY, balanceIsHidden() ? "0" : "1");
  applyBalanceVisibility();
});
document.getElementById("balanceRefresh")?.addEventListener("click", async (event) => {
  event.stopPropagation();
  showAppDataLoader("Memperbarui data...", "Mengambil saldo dan transaksi terbaru dari server.");
  try {
    await load();
    appInitialDataLoaded = true;
    updateAppDataLoader("Data berhasil diperbarui", "Saldo, transaksi, chat, dan fitur sudah sinkron.");
    window.setTimeout(hideAppDataLoader, 220);
  } catch (err) {
    showAppDataLoadError(err);
  }
});
document.getElementById("balanceStatCard")?.addEventListener("click", (event) => {
  if (event.target.closest("#balanceVisibilityToggle, #balanceRefresh")) return;
  openWalletBalanceDetails();
});
document.getElementById("balanceStatCard")?.addEventListener("keydown", (event) => {
  if (event.target !== event.currentTarget) return;
  if (event.key === "Enter" || event.key === " ") {
    event.preventDefault();
    openWalletBalanceDetails();
  }
});
document.getElementById("closeWalletBalanceModal")?.addEventListener("click", () => {
  document.getElementById("walletBalanceModal")?.close();
});
document.getElementById("walletBalanceModal")?.addEventListener("click", (event) => {
  if (event.target === event.currentTarget) event.currentTarget.close();
});
document.getElementById("budgetCardMinimize")?.addEventListener("click", () => {
  setDailyBudgetMinimized(!dailyBudgetIsMinimized());
});
document.getElementById("budgetCardClose")?.addEventListener("click", () => {
  sessionStorage.setItem(DAILY_BUDGET_DISMISSED_KEY, "1");
  const card = document.getElementById("dailyBudgetCard");
  if (card) card.classList.add("is-hidden");
  document.body.classList.remove("has-daily-budget");
});

// ---------------- FOTO + OCR NOTA ----------------
function loadScriptOnce(src) {
  return new Promise((resolve, reject) => {
    if (window.Tesseract) return resolve();
    const existing = document.querySelector(`script[data-ocr-src="${src}"]`);
    if (existing) {
      existing.addEventListener("load", resolve, { once: true });
      existing.addEventListener("error", reject, { once: true });
      return;
    }
    const s = document.createElement("script");
    s.src = src;
    s.async = true;
    s.dataset.ocrSrc = src;
    s.onload = resolve;
    s.onerror = () => reject(new Error("Engine OCR gagal dimuat. Periksa koneksi internet."));
    document.head.appendChild(s);
  });
}

function setOcrStatus(text, kind = "") {
  const el = document.getElementById("ocrStatus");
  if (!el) return;
  el.textContent = text;
  el.className = kind ? "ocr-status " + kind : "ocr-status";
}

async function ensureOcrWorker() {
  if (ocrWorker) return ocrWorker;
  if (ocrWorkerPromise) return ocrWorkerPromise;
  ocrWorkerPromise = (async () => {
    setOcrStatus("Menyiapkan OCR…");
    await loadScriptOnce(OCR_CDN);
    if (!window.Tesseract) throw new Error("Tesseract OCR tidak tersedia.");
    const worker = await Tesseract.createWorker("eng", 1, {
      logger: (m) => {
        if (m && m.status === "recognizing text") {
          const pct = Math.round(Number(m.progress || 0) * 100);
          setOcrStatus("Memindai nota " + pct + "%…", "scanning");
        }
      },
    });
    try { await worker.setParameters({ preserve_interword_spaces: "1" }); } catch (_) {}
    ocrWorker = worker;
    return worker;
  })().catch((err) => {
    ocrWorkerPromise = null;
    throw err;
  });
  return ocrWorkerPromise;
}

function parseReceiptMoney(raw) {
  let s = String(raw || "").toLowerCase().replace(/idr|rp\.?/g, "").replace(/\s/g, "").replace(/[^0-9.,]/g, "");
  if (!s) return 0;
  let m;
  if ((m = s.match(/^(\d{1,3}(?:\.\d{3})+),\d{2}$/))) return Number(m[1].replace(/\./g, ""));
  if ((m = s.match(/^(\d{1,3}(?:,\d{3})+)\.\d{2}$/))) return Number(m[1].replace(/,/g, ""));
  if (/^\d{1,3}(?:\.\d{3})+$/.test(s)) return Number(s.replace(/\./g, ""));
  if (/^\d{1,3}(?:,\d{3})+$/.test(s)) return Number(s.replace(/,/g, ""));
  if ((s.match(/[.,]/g) || []).length > 1) return Number(s.replace(/[.,]/g, ""));
  if (/^\d+[.,]\d{2}$/.test(s)) return Math.floor(Number(s.replace(",", ".")));
  if (/^\d+[.,]\d{3}$/.test(s)) return Number(s.replace(/[.,]/g, ""));
  return Number(s.replace(/\D/g, "")) || 0;
}

function detectReceiptAmount(text) {
  const lines = String(text || "").split(/\r?\n/).map((x) => x.trim()).filter(Boolean);
  let best = { amount: 0, score: 0, line: "" };
  lines.forEach((line, i) => {
    const low = line.toLowerCase();
    if (/\b(telp|telepon|phone|whatsapp|npwp|invoice\s*no|no\.?\s*struk|order\s*id)\b/i.test(low)) return;
    if (/\b\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}\b/.test(line) && !/\b(total|jumlah|bayar|amount)\b/i.test(low)) return;
    const matches = line.match(/(?:rp\.?\s*)?\d{1,3}(?:[.,]\d{3})+(?:[.,]\d{2})?|(?:rp\.?\s*)?\d{4,9}(?:[.,]\d{2})?/gi) || [];
    matches.forEach((token) => {
      const amount = parseReceiptMoney(token);
      if (amount < 500 || amount > 500000000) return;
      let score = 0;
      if (/\bgrand\s*total\b/i.test(low)) score += 125;
      else if (/\b(total\s*(bayar|payment|amount)|amount\s*due|total)\b/i.test(low)) score += 105;
      else if (/\b(jumlah|bayar|payment|tagihan)\b/i.test(low)) score += 75;
      if (/\bsub\s*total|subtotal\b/i.test(low)) score -= 55;
      if (/\b(kembali|kembalian|change|diskon|discount|hemat|ppn|pajak|tax|service|dpp)\b/i.test(low)) score -= 70;
      if (/rp\.?/i.test(line)) score += 16;
      if (/[.,]\d{3}/.test(token)) score += 8;
      score += Math.round((i / Math.max(1, lines.length)) * 18);
      if (amount >= 1000) score += 4;
      if (score > best.score || (score === best.score && amount > best.amount)) best = { amount, score, line };
    });
  });
  return best;
}

function formatImageBytes(bytes) {
  const n = Math.max(0, Number(bytes || 0));
  if (n < 1024) return `${Math.round(n)} B`;
  if (n < 1024 * 1024) return `${Math.round(n / 1024)} KB`;
  return `${(n / 1024 / 1024).toFixed(n >= 10 * 1024 * 1024 ? 1 : 2)} MB`;
}

async function decodeImageForCompression(file) {
  if (typeof createImageBitmap === "function") {
    try {
      const bitmap = await createImageBitmap(file, { imageOrientation: "from-image" });
      return {
        source: bitmap,
        width: bitmap.width,
        height: bitmap.height,
        close: () => { try { bitmap.close?.(); } catch (_) {} },
      };
    } catch (_) {}
  }

  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => resolve({
      source: img,
      width: img.naturalWidth || img.width,
      height: img.naturalHeight || img.height,
      close: () => URL.revokeObjectURL(url),
    });
    img.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error("Gambar tidak dapat dibaca untuk dikompres."));
    };
    img.src = url;
  });
}

function canvasToImageBlob(canvas, type, quality) {
  return new Promise((resolve) => canvas.toBlob(resolve, type, quality));
}

/**
 * Kompres gambar sebelum upload agar pemakaian storage dan data lebih hemat.
 * File yang sudah kecil tidak dipaksa dikompres ulang agar kualitas tidak turun.
 */
async function compressImageForStorage(file, options = {}) {
  if (!file || !String(file.type || "").startsWith("image/")) throw new Error("File harus berupa gambar.");
  const maxInputBytes = Number(options.maxInputBytes || 20 * 1024 * 1024);
  if (file.size > maxInputBytes) throw new Error(`Foto terlalu besar. Maksimal ${formatImageBytes(maxInputBytes)} sebelum kompresi.`);

  const maxSide = Math.max(1000, Number(options.maxSide || 1600));
  const targetBytes = Math.max(250 * 1024, Number(options.targetBytes || 600 * 1024));
  const initialQuality = Math.min(0.9, Math.max(0.68, Number(options.initialQuality || 0.82)));
  const minQuality = Math.min(initialQuality, Math.max(0.58, Number(options.minQuality || 0.64)));
  const originalSize = Number(file.size || 0);
  const decoded = await decodeImageForCompression(file);

  try {
    const originalWidth = Math.max(1, Number(decoded.width || 1));
    const originalHeight = Math.max(1, Number(decoded.height || 1));
    const originalMaxSide = Math.max(originalWidth, originalHeight);

    // Sudah efisien: tidak perlu recompress file JPEG/WebP yang kecil dan dimensinya wajar.
    if (originalSize <= targetBytes && originalMaxSide <= maxSide && /^image\/(jpeg|webp)$/i.test(file.type || "")) {
      return {
        file,
        originalSize,
        finalSize: originalSize,
        originalWidth,
        originalHeight,
        width: originalWidth,
        height: originalHeight,
        compressed: false,
        savedPercent: 0,
      };
    }

    let scale = Math.min(1, maxSide / originalMaxSide);
    let width = Math.max(1, Math.round(originalWidth * scale));
    let height = Math.max(1, Math.round(originalHeight * scale));
    let canvas = null;

    const draw = (w, h) => {
      const c = document.createElement("canvas");
      c.width = w;
      c.height = h;
      const ctx = c.getContext("2d", { alpha: false });
      if (!ctx) throw new Error("Perangkat tidak mendukung kompresi gambar.");
      ctx.fillStyle = "#fff";
      ctx.fillRect(0, 0, w, h);
      ctx.imageSmoothingEnabled = true;
      try { ctx.imageSmoothingQuality = "high"; } catch (_) {}
      ctx.drawImage(decoded.source, 0, 0, w, h);
      return c;
    };

    canvas = draw(width, height);
    let quality = initialQuality;
    let blob = await canvasToImageBlob(canvas, "image/jpeg", quality);
    if (!blob) throw new Error("Kompresi gambar gagal pada perangkat ini.");

    // Turunkan kualitas secara bertahap sampai mendekati target ukuran.
    while (blob.size > targetBytes && quality - 0.06 >= minQuality) {
      quality = Math.max(minQuality, quality - 0.06);
      const next = await canvasToImageBlob(canvas, "image/jpeg", quality);
      if (!next) break;
      blob = next;
    }

    // Jika masih besar, kecilkan resolusi sedikit. Maksimal 2 iterasi agar teks nota tetap terbaca.
    for (let i = 0; i < 2 && blob.size > targetBytes * 1.18 && Math.max(width, height) > 1100; i++) {
      const ratio = Math.max(0.78, Math.min(0.94, Math.sqrt(targetBytes / blob.size) * 0.97));
      width = Math.max(1, Math.round(width * ratio));
      height = Math.max(1, Math.round(height * ratio));
      canvas.width = 1;
      canvas.height = 1;
      canvas = draw(width, height);
      const next = await canvasToImageBlob(canvas, "image/jpeg", Math.max(minQuality, quality));
      if (!next) break;
      blob = next;
    }

    // Jangan mengganti file asli bila hasil JPEG justru lebih besar dan resize tidak diperlukan.
    const resized = width < originalWidth || height < originalHeight;
    if (!resized && originalSize > 0 && blob.size >= originalSize) {
      return {
        file,
        originalSize,
        finalSize: originalSize,
        originalWidth,
        originalHeight,
        width: originalWidth,
        height: originalHeight,
        compressed: false,
        savedPercent: 0,
      };
    }

    const base = String(file.name || "gambar").replace(/\.[^.]+$/, "") || "gambar";
    const output = new File([blob], `${base}.jpg`, { type: "image/jpeg", lastModified: Date.now() });
    const savedPercent = originalSize > 0 ? Math.max(0, Math.round((1 - output.size / originalSize) * 100)) : 0;
    return {
      file: output,
      originalSize,
      finalSize: output.size,
      originalWidth,
      originalHeight,
      width,
      height,
      compressed: output.size < originalSize || resized,
      savedPercent,
    };
  } finally {
    decoded.close?.();
  }
}

async function preparePhoto(file) {
  return compressImageForStorage(file, {
    maxSide: 1600,
    targetBytes: 600 * 1024,
    initialQuality: 0.82,
    minQuality: 0.64,
    maxInputBytes: 20 * 1024 * 1024,
  });
}

function clearSelectedPhoto() {
  if (selectedPhoto?.previewUrl) URL.revokeObjectURL(selectedPhoto.previewUrl);
  selectedPhoto = null;
  document.getElementById("photoPreviewWrap").hidden = true;
  document.getElementById("ocrResult").hidden = true;
  document.getElementById("galleryPhoto").value = "";
  document.getElementById("cameraPhoto").value = "";
  const compressionInfo = document.getElementById("photoCompressionInfo");
  if (compressionInfo) compressionInfo.textContent = "Foto akan dikompres otomatis";
  setOcrStatus("Siap dipindai");
}

async function scanSelectedPhoto() {
  if (!selectedPhoto) return null;
  const mode = document.getElementById("imageMode").value;
  if (mode === "attachment") {
    setOcrStatus("Lampiran saja — OCR dilewati", "muted");
    selectedPhoto.ocr = { text: "", amount: 0, score: 0, line: "", confidence: 0 };
    return selectedPhoto.ocr;
  }
  if (selectedPhoto.ocrPromise) return selectedPhoto.ocrPromise;
  selectedPhoto.ocrPromise = (async () => {
    try {
      const worker = await ensureOcrWorker();
      setOcrStatus("Memindai nota…", "scanning");
      const ret = await worker.recognize(selectedPhoto.file);
      const text = ret?.data?.text || "";
      const confidence = Number(ret?.data?.confidence || 0);
      const candidate = detectReceiptAmount(text);
      selectedPhoto.ocr = { text, confidence, ...candidate };
      const box = document.getElementById("ocrResult");
      if (candidate.amount > 0) {
        box.hidden = false;
        box.className = "ocr-result " + (candidate.score >= 42 ? "found" : "possible");
        box.textContent = (candidate.score >= 42 ? "Total terdeteksi " : "Kemungkinan total ") + rupiah(candidate.amount);
        setOcrStatus("Scan selesai", "success");
      } else {
        box.hidden = false;
        box.className = "ocr-result not-found";
        box.textContent = "Total nota belum ditemukan";
        setOcrStatus("Scan selesai — total belum jelas", "warning");
      }
      return selectedPhoto.ocr;
    } catch (err) {
      selectedPhoto.ocr = { text: "", amount: 0, score: 0, line: "", confidence: 0 };
      const box = document.getElementById("ocrResult");
      box.hidden = false;
      box.className = "ocr-result not-found";
      box.textContent = "OCR tidak tersedia — foto tetap bisa dikirim";
      setOcrStatus(err.message || "OCR gagal", "warning");
      return selectedPhoto.ocr;
    }
  })();
  return selectedPhoto.ocrPromise;
}

async function handlePhotoSelection(file) {
  if (!file) return;
  clearSelectedPhoto();
  setOcrStatus("Mengompres foto…");
  const prepared = await preparePhoto(file);
  const processed = prepared.file;
  const previewUrl = URL.createObjectURL(processed);
  selectedPhoto = { file: processed, previewUrl, ocr: null, ocrPromise: null, compression: prepared };
  document.getElementById("photoPreview").src = previewUrl;
  document.getElementById("photoPreviewName").textContent = file.name || "Foto nota";
  const compressionInfo = document.getElementById("photoCompressionInfo");
  if (compressionInfo) {
    compressionInfo.textContent = prepared.compressed
      ? `${formatImageBytes(prepared.originalSize)} → ${formatImageBytes(prepared.finalSize)} · hemat ${prepared.savedPercent}%`
      : `${formatImageBytes(prepared.finalSize)} · ukuran sudah efisien`;
  }
  document.getElementById("photoPreviewWrap").hidden = false;
  document.getElementById("imageMode").value = "auto";
  scanSelectedPhoto();
}

const attachBtn = document.getElementById("attachPhotoBtn");
const sourceMenu = document.getElementById("photoSourceMenu");
attachBtn?.addEventListener("click", (e) => {
  e.stopPropagation();
  sourceMenu.hidden = !sourceMenu.hidden;
});
document.getElementById("chooseGallery")?.addEventListener("click", () => { sourceMenu.hidden = true; document.getElementById("galleryPhoto").click(); });
document.getElementById("chooseCamera")?.addEventListener("click", () => { sourceMenu.hidden = true; document.getElementById("cameraPhoto").click(); });
document.getElementById("galleryPhoto")?.addEventListener("change", (e) => handlePhotoSelection(e.target.files?.[0]).catch((err) => alert(err.message)));
document.getElementById("cameraPhoto")?.addEventListener("change", (e) => handlePhotoSelection(e.target.files?.[0]).catch((err) => alert(err.message)));
document.getElementById("removePhoto")?.addEventListener("click", clearSelectedPhoto);
document.getElementById("imageMode")?.addEventListener("change", () => {
  if (!selectedPhoto) return;
  selectedPhoto.ocrPromise = null;
  document.getElementById("ocrResult").hidden = true;
  scanSelectedPhoto();
});
document.addEventListener("click", (e) => {
  if (sourceMenu && !sourceMenu.hidden && !sourceMenu.contains(e.target) && e.target !== attachBtn) sourceMenu.hidden = true;
});

function openPhotoViewer(url) {
  if (!url) return;
  const dlg = document.getElementById("photoViewer");
  document.getElementById("photoViewerImg").src = url;
  if (dlg?.showModal) dlg.showModal();
}
document.getElementById("closePhotoViewer")?.addEventListener("click", () => document.getElementById("photoViewer").close());
document.getElementById("photoViewer")?.addEventListener("click", (e) => { if (e.target.id === "photoViewer") e.currentTarget.close(); });

// ---------------- CHAT SEND ----------------
const chatForm = document.getElementById("chatForm");
chatForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  const input = document.getElementById("message");
  const msg = input.value.trim();
  if (!msg && !selectedPhoto) return;
  sourceMenu.hidden = true;
  const cb = document.getElementById("chatBody");
  const tempPhoto = selectedPhoto?.previewUrl ? `<div class="stored-photo temp"><img src="${esc(selectedPhoto.previewUrl)}" alt="Foto"></div>` : "";
  cb.insertAdjacentHTML("beforeend", `<div class="bubble user ${selectedPhoto ? "has-photo" : ""}">${tempPhoto}${msg ? `<div class="bubble-text">${esc(msg)}</div>` : ""}</div><div class="bubble assistant" id="typing"><div class="bubble-text">${selectedPhoto ? "Membaca foto dan menyiapkan konfirmasi…" : "Memproses lokal…"}</div></div>`);
  cb.scrollTop = cb.scrollHeight;
  document.getElementById("sendBtn").disabled = true;
  if (attachBtn) attachBtn.disabled = true;

  try {
    let options;
    if (selectedPhoto) {
      const mode = document.getElementById("imageMode").value;
      const ocr = mode === "attachment" ? { text: "", amount: 0, score: 0, line: "", confidence: 0 } : await scanSelectedPhoto();
      const fd = new FormData();
      fd.append("message", msg);
      fd.append("image_mode", mode);
      fd.append("photo", selectedPhoto.file, selectedPhoto.file.name);
      fd.append("ocr_text", ocr?.text || "");
      fd.append("ocr_amount", String(ocr?.amount || 0));
      fd.append("ocr_score", String(ocr?.score || 0));
      fd.append("ocr_line", ocr?.line || "");
      fd.append("ocr_confidence", String(ocr?.confidence || 0));
      options = { method: "POST", body: fd };
    } else {
      options = { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ message: msg }) };
    }
    const offlinePreview = {
      message: msg,
      image_mode: selectedPhoto ? document.getElementById("imageMode").value : "auto",
      photo: !!selectedPhoto,
      ocr_text: selectedPhoto?.ocr?.text || "",
      ocr_amount: Number(selectedPhoto?.ocr?.amount || 0),
      ocr_score: Number(selectedPhoto?.ocr?.score || 0),
      wallet_id: Number(((state.features || {}).wallets || [])[0]?.id || 1),
    };
    const result = await fetchJson("ajax/finance.php", options, { kind: "finance", preview: offlinePreview });
    input.value = "";
    clearSelectedPhoto();
    await load();
    if (result?.offline_queued) showOfflineToast("Tersimpan offline. Setelah tersinkron, transaksi akan diminta konfirmasi sebelum disimpan.");
  } catch (err) {
    document.getElementById("typing")?.remove();
    cb.insertAdjacentHTML("beforeend", `<div class="bubble assistant"><div class="bubble-text">Error: ${esc(err.message)}</div></div>`);
    cb.scrollTop = cb.scrollHeight;
  } finally {
    document.getElementById("sendBtn").disabled = false;
    if (attachBtn) attachBtn.disabled = false;
    input.focus();
  }
});


// ---------------- PEMBELAJARAN ASISTEN ----------------
function renderLearningPending() {
  const pending = state.learning?.pending || null;
  const card = document.getElementById("learningPendingCard");
  const text = document.getElementById("learningPendingText");
  const input = document.getElementById("message");
  const count = document.getElementById("learningRuleCount");
  if (count) count.textContent = Number(state.learning?.rule_count || 0);
  if (!card || !input) return;
  if (pending?.phrase) {
    card.hidden = false;
    if (text) text.textContent = `Balasan berikutnya akan mengajari saya cara menjawab “${pending.phrase}”.`;
    input.placeholder = `Balasan untuk “${pending.phrase}”…`;
    document.body.classList.add("learning-active");
  } else {
    card.hidden = true;
    input.placeholder = "Tulis transaksi atau keterangan foto...";
    document.body.classList.remove("learning-active");
  }
}

async function cancelLearningPending() {
  try {
    await fetchJson("ajax/learning.php", {
      method: "POST", headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "cancel_pending" })
    });
    await load();
  } catch (err) { alert(err.message); }
}

document.getElementById("cancelLearningPending")?.addEventListener("click", cancelLearningPending);

const learningModal = document.getElementById("learningModal");
const learningRuleList = document.getElementById("learningRuleList");

function learningRuleHtml(rule) {
  return `<div class="learning-rule" data-learning-id="${Number(rule.id)}">
    <div class="learning-rule-main"><span class="learning-trigger">${esc(rule.phrase || "")}</span><span class="learning-arrow">→</span><span class="learning-answer">${esc(rule.response || "")}</span></div>
    <button type="button" class="learning-delete" data-learning-delete="${Number(rule.id)}" aria-label="Hapus pembelajaran">hapus</button>
  </div>`;
}

async function refreshLearningList() {
  const data = await fetchJson("ajax/learning.php");
  const rules = data.rules || [];
  if (learningRuleList) learningRuleList.innerHTML = rules.length ? rules.map(learningRuleHtml).join("") : '<div class="empty">Belum ada pembelajaran.</div>';
  const n = document.getElementById("learningListCount");
  if (n) n.textContent = `${rules.length} aturan`;
  const c = document.getElementById("learningRuleCount");
  if (c) c.textContent = rules.length;
  document.querySelectorAll("[data-learning-delete]").forEach((btn) => btn.addEventListener("click", async () => {
    if (!confirm("Hapus pembelajaran ini untuk semua pengguna?")) return;
    try {
      await fetchJson("ajax/learning.php", { method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify({action:"delete", id:Number(btn.dataset.learningDelete)}) });
      await refreshLearningList();
      await load();
    } catch (err) { alert(err.message); }
  }));
}

async function openLearningModal() {
  if (isMobileSidebar()) closeSidebar();
  learningModal?.showModal();
  try { await refreshLearningList(); } catch (err) { alert(err.message); }
}

document.getElementById("openLearning")?.addEventListener("click", openLearningModal);
document.getElementById("closeLearningModal")?.addEventListener("click", () => learningModal?.close());
document.getElementById("saveLearningRule")?.addEventListener("click", async () => {
  const phrase = document.getElementById("learningPhrase")?.value.trim() || "";
  const response = document.getElementById("learningResponse")?.value.trim() || "";
  if (!phrase || !response) return alert("Isi kalimat pemicu dan balasannya.");
  try {
    await fetchJson("ajax/learning.php", { method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify({action:"save", phrase, response}) });
    document.getElementById("learningPhrase").value = "";
    document.getElementById("learningResponse").value = "";
    await refreshLearningList();
    await load();
  } catch (err) { alert(err.message); }
});

// ---------------- SIDEBAR MENU ----------------
const appSidebar = document.getElementById("appSidebar");
const sidebarToggle = document.getElementById("sidebarToggle");
const mobileOpenMore = document.getElementById("mobileOpenMore");
const sidebarClose = document.getElementById("sidebarClose");
const sidebarBackdrop = document.getElementById("sidebarBackdrop");

const sidebarMobileQuery = window.matchMedia("(max-width: 760px)");
function isMobileSidebar() { return sidebarMobileQuery.matches; }

function updateMobileSidebarState(open) {
  mobileOpenMore?.setAttribute("aria-expanded", String(open));
  mobileOpenMore?.classList.toggle("active", open);
  if (appSidebar) appSidebar.inert = isMobileSidebar() && !open;
}

function syncSidebarMode() {
  if (!appSidebar || !sidebarBackdrop) return;
  updateMobileSidebarState(false);
  if (!isMobileSidebar()) {
    appSidebar.classList.add("is-open");
    appSidebar.setAttribute("aria-hidden", "false");
    sidebarBackdrop.classList.remove("is-visible");
    sidebarBackdrop.hidden = true;
    sidebarToggle?.setAttribute("aria-expanded", "true");
    document.body.classList.remove("sidebar-open");
  } else {
    appSidebar.classList.remove("is-open");
    appSidebar.setAttribute("aria-hidden", "true");
    sidebarBackdrop.classList.remove("is-visible");
    sidebarBackdrop.hidden = true;
    sidebarToggle?.setAttribute("aria-expanded", "false");
    document.body.classList.remove("sidebar-open");
  }
}

function openSidebar() {
  if (!appSidebar || !sidebarBackdrop || !isMobileSidebar()) return;
  updateMobileSidebarState(true);
  sidebarBackdrop.hidden = false;
  requestAnimationFrame(() => sidebarBackdrop.classList.add("is-visible"));
  appSidebar.classList.add("is-open");
  appSidebar.setAttribute("aria-hidden", "false");
  sidebarToggle?.setAttribute("aria-expanded", "true");
  document.body.classList.add("sidebar-open");
  sidebarClose?.focus({ preventScroll: true });
}

function closeSidebar() {
  if (!appSidebar || !sidebarBackdrop || !isMobileSidebar()) return;
  if (appSidebar.contains(document.activeElement)) mobileOpenMore?.focus({ preventScroll: true });
  updateMobileSidebarState(false);
  appSidebar.classList.remove("is-open");
  appSidebar.setAttribute("aria-hidden", "true");
  sidebarToggle?.setAttribute("aria-expanded", "false");
  sidebarBackdrop.classList.remove("is-visible");
  document.body.classList.remove("sidebar-open");
  window.setTimeout(() => {
    if (!appSidebar.classList.contains("is-open")) sidebarBackdrop.hidden = true;
  }, 220);
}

mobileOpenMore?.addEventListener("click", openSidebar);
sidebarToggle?.addEventListener("click", openSidebar);
sidebarClose?.addEventListener("click", closeSidebar);
sidebarBackdrop?.addEventListener("click", closeSidebar);
document.addEventListener("keydown", (e) => { if (e.key === "Escape" && isMobileSidebar() && appSidebar?.classList.contains("is-open")) closeSidebar(); });
// Keep keyboard navigation inside the mobile drawer while it is open.
appSidebar?.addEventListener("keydown", (event) => {
  if (event.key !== "Tab" || !isMobileSidebar() || !appSidebar.classList.contains("is-open")) return;
  const items = [...appSidebar.querySelectorAll('button, a[href], input, select, textarea, [tabindex]')]
    .filter(item => !item.disabled && item.tabIndex >= 0 && item.getClientRects().length);
  const first = items[0], last = items[items.length - 1];
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
});
if (sidebarMobileQuery.addEventListener) sidebarMobileQuery.addEventListener("change", syncSidebarMode);
else sidebarMobileQuery.addListener(syncSidebarMode);
syncSidebarMode();

const sidebarSummary = document.getElementById("sidebarSummary");
sidebarSummary?.addEventListener("click", () => {
  if (isMobileSidebar()) closeSidebar();
  document.querySelector(".stats")?.scrollIntoView({ behavior: "smooth", block: "start" });
});


// ---------------- BANTUAN & FAQ ----------------
const helpFaqModal = document.getElementById("helpFaqModal");
const helpFaqSearch = document.getElementById("helpFaqSearch");
const helpFaqList = document.getElementById("helpFaqList");
let helpFaqCategory = "all";

const helpFaqCategoryMeta = {
  transaksi: {
    label: "Transaksi",
    short: "Chat, manual, edit, nota & pola transaksi",
    icon: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h10M7 12h7M7 17h4"/><path d="M18 15v6m-3-3h6"/></svg>`
  },
  asisten: {
    label: "Asisten AI",
    short: "Adaptive Learning, konteks & simulasi keuangan",
    icon: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/><path d="M7.5 7.5 5.5 5.5M18.5 18.5l-2-2M16.5 7.5l2-2M5.5 18.5l2-2"/><circle cx="12" cy="12" r="4"/></svg>`
  },
  saldo: {
    label: "Saldo",
    short: "Dompet, saldo awal & dana disisihkan",
    icon: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5h15a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H5a2 2 0 0 1-2-2v-11a2 2 0 0 1 2-2h12"/><path d="M15 12h5v3h-5a1.5 1.5 0 0 1 0-3Z"/></svg>`
  },
  tagihan: {
    label: "Tagihan",
    short: "Cicilan, pembayaran & transaksi berulang",
    icon: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6"/></svg>`
  },
  premium: {
    label: "Premium",
    short: "Free Trial, paket, invoice & pembayaran",
    icon: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 8 4 4 4-7 4 7 4-4-2 11H6L4 8Z"/><path d="M7 19h10"/></svg>`
  },
  sinkronisasi: {
    label: "Offline & Sinkronisasi",
    short: "Antrean, konflik & penggunaan offline",
    icon: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7h-5V2"/><path d="M20 7a8 8 0 0 0-13.5-2M4 17h5v5"/><path d="M4 17a8 8 0 0 0 13.5 2"/></svg>`
  },
  akun: {
    label: "Akun & Keamanan",
    short: "Login, biometrik, email, perangkat & privasi",
    icon: `<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg>`
  }
};

function normalizeFaqText(value) {
  return String(value || "").toLocaleLowerCase("id-ID").normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim();
}

function faqItems() {
  return [...document.querySelectorAll("#helpFaqList .help-faq-item")];
}

function initHelpFaqGroups() {
  if (!helpFaqList || helpFaqList.dataset.grouped === "1") return;
  helpFaqList.dataset.grouped = "1";

  const seen = new Set();
  faqItems().forEach((item) => {
    const category = item.dataset.faqCategory || "";
    if (!category || seen.has(category)) return;
    seen.add(category);
    const meta = helpFaqCategoryMeta[category];
    if (!meta) return;

    const heading = document.createElement("div");
    heading.className = "help-faq-group-title";
    heading.dataset.faqGroup = category;
    heading.innerHTML = `<span>${meta.icon}</span><div><b>${meta.label}</b><small>${meta.short}</small></div>`;
    helpFaqList.insertBefore(heading, item);
  });
}

function setHelpFaqCategory(category) {
  helpFaqCategory = helpFaqCategoryMeta[category] ? category : "all";
  document.querySelectorAll("#helpFaqCategories [data-faq-category]").forEach((button) => {
    button.classList.toggle("active", button.dataset.faqCategory === helpFaqCategory);
  });
  filterHelpFaq();
}

function filterHelpFaq() {
  const query = normalizeFaqText(helpFaqSearch?.value);
  const items = faqItems();
  let visible = 0;
  const visibleByCategory = {};

  items.forEach((item) => {
    const category = item.dataset.faqCategory || "";
    const categoryMatch = helpFaqCategory === "all" || category === helpFaqCategory;
    const haystack = normalizeFaqText(`${item.textContent} ${item.dataset.faqKeywords || ""}`);
    const words = query.split(/\s+/).filter(Boolean);
    const queryMatch = !words.length || words.every((word) => haystack.includes(word));
    const show = categoryMatch && queryMatch;
    item.hidden = !show;
    if (show) {
      visible += 1;
      visibleByCategory[category] = (visibleByCategory[category] || 0) + 1;
    }
    if (!show) item.open = false;
  });

  const groupedAllMode = helpFaqCategory === "all" && !query;
  helpFaqList?.classList.toggle("is-all-mode", groupedAllMode);

  document.querySelectorAll("#helpFaqList .help-faq-group-title").forEach((heading) => {
    const category = heading.dataset.faqGroup || "";
    heading.hidden = !groupedAllMode || !visibleByCategory[category];
  });

  const empty = document.getElementById("helpFaqEmpty");
  if (empty) empty.hidden = visible !== 0;

  const count = document.getElementById("helpFaqResultCount");
  if (count) {
    if (groupedAllMode) {
      const categoryCount = Object.keys(visibleByCategory).length;
      count.textContent = `${visible} pertanyaan · ${categoryCount} kategori`;
    } else if (helpFaqCategory !== "all" && !query) {
      count.textContent = `${visible} pertanyaan ${helpFaqCategoryMeta[helpFaqCategory]?.label || ""}`.trim();
    } else {
      count.textContent = `${visible} jawaban ditemukan`;
    }
  }

  const clear = document.getElementById("clearHelpFaqSearch");
  if (clear) clear.hidden = !helpFaqSearch?.value;
}

function openHelpFaqModal() {
  if (isMobileSidebar()) closeSidebar();
  initHelpFaqGroups();
  helpFaqCategory = "all";
  document.querySelectorAll("#helpFaqCategories [data-faq-category]").forEach((button) => {
    button.classList.toggle("active", button.dataset.faqCategory === "all");
  });
  if (helpFaqSearch) helpFaqSearch.value = "";
  filterHelpFaq();
  if (helpFaqModal && !helpFaqModal.open) helpFaqModal.showModal();
}

initHelpFaqGroups();

document.getElementById("openHelpFaq")?.addEventListener("click", openHelpFaqModal);
document.getElementById("closeHelpFaq")?.addEventListener("click", () => helpFaqModal?.close());
helpFaqSearch?.addEventListener("input", filterHelpFaq);
document.getElementById("clearHelpFaqSearch")?.addEventListener("click", () => {
  if (!helpFaqSearch) return;
  helpFaqSearch.value = "";
  filterHelpFaq();
  helpFaqSearch.focus();
});
document.getElementById("helpFaqCategories")?.addEventListener("click", (event) => {
  const button = event.target.closest("[data-faq-category]");
  if (!button) return;
  setHelpFaqCategory(button.dataset.faqCategory || "all");
});
document.getElementById("faqAskAssistant")?.addEventListener("click", () => {
  helpFaqModal?.close();
  const message = document.getElementById("message");
  if (message) {
    message.scrollIntoView({ behavior: "smooth", block: "center" });
    window.setTimeout(() => message.focus({ preventScroll: true }), 200);
  }
});

// ---------------- SALDO AWAL ----------------
const modal = document.getElementById("settingModal");
function renderInitialWalletForm() {
  const box = document.getElementById('initialWalletList');
  if (!box) return;
  const wallets = (state.features?.wallets || []).filter(w => !w.archived);
  box.innerHTML = wallets.map((w,index) => {
    const locked = index > 0 && !isPremiumUser();
    return `<div class="initial-wallet-row"><div class="initial-wallet-heading"><b>${esc(w.name)}</b><span>Tersedia: ${esc(rupiah(w.available_balance ?? w.balance ?? 0))}</span></div>
      <div class="initial-wallet-fields"><label for="initial-wallet-${Number(w.id)}">Saldo awal${locked?' · Premium':''}<input id="initial-wallet-${Number(w.id)}" type="number" min="0" step="1" inputmode="numeric" required data-initial-wallet="${Number(w.id)}" data-original-initial="${Number(w.initial_balance || 0)}" value="${Number(w.initial_balance || 0)}" ${locked?'disabled':''}></label>
      <label for="reserved-wallet-${Number(w.id)}">Dana disisihkan<input id="reserved-wallet-${Number(w.id)}" type="number" min="0" step="1" inputmode="numeric" required data-reserved-wallet="${Number(w.id)}" data-original-reserved="${Number(w.reserved_balance || 0)}" value="${Number(w.reserved_balance || 0)}" ${locked?'disabled':''}></label>
      <label for="minimum-wallet-${Number(w.id)}">Saldo minimum<input id="minimum-wallet-${Number(w.id)}" type="number" min="0" step="1" inputmode="numeric" required data-minimum-wallet="${Number(w.id)}" data-original-minimum="${Number(w.minimum_balance || 0)}" value="${Number(w.minimum_balance || 0)}" ${locked?'disabled':''}></label></div>
      <small>Dana disisihkan dan saldo minimum tidak termasuk uang yang dapat dipakai. Saldo minimum cocok untuk saldo mengendap wajib dari bank.</small></div>`;
  }).join('') || '<p class="muted">Belum ada dompet aktif. Tambahkan dompet untuk mulai mengatur saldo.</p>';
  document.getElementById('saveSetting').disabled = !wallets.length;
}

document.getElementById('openSetting').onclick = async () => {
  if (isMobileSidebar()) closeSidebar();
  try { await refreshFeatures(); } catch (_) { /* Use the last loaded wallet snapshot offline. */ }
  renderInitialWalletForm();
  if (!modal.open) modal.showModal();
};
document.getElementById('addInitialWallet')?.addEventListener('click', async () => {
  modal.close();
  await openFinanceCenter('wallets');
  if (isPremiumUser()) {
    document.getElementById('walletId').value = '';
    document.getElementById('walletName').value = '';
    document.getElementById('walletInitial').value = '';
    if (document.getElementById('walletReserved')) document.getElementById('walletReserved').value = '';
    if (document.getElementById('walletMinimum')) document.getElementById('walletMinimum').value = '';
    document.getElementById('walletType').value = 'cash';
    document.getElementById('walletName').focus();
    document.getElementById('walletName').scrollIntoView({block:'center'});
  }
});
document.getElementById('saveSetting').addEventListener('click', async () => {
  const form = modal.querySelector('form');
  if (!form.reportValidity()) return;
  const changes = [...modal.querySelectorAll('[data-initial-wallet]:not(:disabled)')].map(input => {
    const id = Number(input.dataset.initialWallet);
    const reserved = modal.querySelector(`[data-reserved-wallet="${id}"]`);
    const minimum = modal.querySelector(`[data-minimum-wallet="${id}"]`);
    return {
      id,
      initial_balance:Number(input.value),
      expected_initial_balance:Number(input.dataset.originalInitial),
      reserved_balance:Number(reserved?.value || 0),
      expected_reserved_balance:Number(reserved?.dataset.originalReserved || 0),
      minimum_balance:Number(minimum?.value || 0),
      expected_minimum_balance:Number(minimum?.dataset.originalMinimum || 0)
    };
  }).filter(w => w.initial_balance !== w.expected_initial_balance || w.reserved_balance !== w.expected_reserved_balance || w.minimum_balance !== w.expected_minimum_balance);
  if (!changes.length) { modal.close(); return; }
  if (changes.some(w => !Number.isSafeInteger(w.initial_balance) || w.initial_balance < 0 || !Number.isSafeInteger(w.reserved_balance) || w.reserved_balance < 0 || !Number.isSafeInteger(w.minimum_balance) || w.minimum_balance < 0)) return alert('Saldo awal, dana disisihkan, dan saldo minimum harus berupa bilangan bulat rupiah yang valid dan tidak negatif.');
  const button = document.getElementById('saveSetting');
  button.disabled = true;
  try {
    const result = await fetchJson('ajax/settings.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({wallet_initial_balances:changes})});
    modal.close();
    if (result.offline_queued) showOfflineToast('Perubahan saldo awal/dana disisihkan/saldo minimum menunggu sinkronisasi saat online.');
    await load();
  } catch (err) { alert(err.message); }
  finally { button.disabled = false; }
});

// ---------------- BATAS HARIAN ----------------
const budgetModal = document.getElementById("budgetModal");
function daySetting(cfg, day) {
  if (!cfg || !cfg.days) return { enabled: false, limit: 0 };
  return cfg.days[String(day)] || cfg.days[day] || { enabled: false, limit: 0 };
}
function updateDayRow(day) {
  const check = document.querySelector(`.day-enabled[data-day="${day}"]`);
  const input = document.querySelector(`.day-limit[data-day="${day}"]`);
  const row = document.querySelector(`.day-budget-row[data-day="${day}"]`);
  if (!check || !input || !row) return;
  input.disabled = !check.checked;
  row.classList.toggle("disabled", !check.checked);
}
function fillDailyBudgetForm() {
  const cfg = state.daily_budget_settings || {};
  document.getElementById("dailyBudgetEnabled").checked = !!cfg.enabled;
  document.getElementById("warningPercent").value = Number(cfg.warning_percent || 80);
  for (let day = 1; day <= 7; day++) {
    const d = daySetting(cfg, day);
    const check = document.querySelector(`.day-enabled[data-day="${day}"]`);
    const input = document.querySelector(`.day-limit[data-day="${day}"]`);
    check.checked = !!d.enabled;
    input.value = Number(d.limit || 0) || "";
    updateDayRow(day);
  }
  document.getElementById("bulkLimit").value = "";
}
function openDailyBudgetModal() { fillDailyBudgetForm(); budgetModal.showModal(); }
document.getElementById("openBudget").onclick = () => { if (isMobileSidebar()) closeSidebar(); openDailyBudgetModal(); };
document.getElementById("budgetCardEdit").onclick = openDailyBudgetModal;
const mobileOpenBudget = document.getElementById("mobileOpenBudget");
if (mobileOpenBudget) mobileOpenBudget.onclick = openDailyBudgetModal;
document.querySelectorAll(".day-enabled").forEach((check) => check.addEventListener("change", () => updateDayRow(check.dataset.day)));
function setSelectedDays(days) {
  for (let day = 1; day <= 7; day++) {
    const check = document.querySelector(`.day-enabled[data-day="${day}"]`);
    check.checked = days.includes(day); updateDayRow(day);
  }
}
document.getElementById("selectAllDays").onclick = () => setSelectedDays([1,2,3,4,5,6,7]);
document.getElementById("selectWeekdays").onclick = () => setSelectedDays([1,2,3,4,5]);
document.getElementById("clearDays").onclick = () => setSelectedDays([]);
document.getElementById("applyBulkLimit").onclick = () => {
  const amount = Math.max(0, Number(document.getElementById("bulkLimit").value || 0));
  if (amount <= 0) return alert("Masukkan nominal batas terlebih dahulu.");
  let applied = 0;
  for (let day = 1; day <= 7; day++) {
    const check = document.querySelector(`.day-enabled[data-day="${day}"]`);
    const input = document.querySelector(`.day-limit[data-day="${day}"]`);
    if (check.checked) { input.value = amount; applied++; }
  }
  if (!applied) alert("Pilih minimal satu hari terlebih dahulu.");
};
document.getElementById("saveDailyBudget").addEventListener("click", async () => {
  const enabled = document.getElementById("dailyBudgetEnabled").checked;
  const warningPercent = Math.max(50, Math.min(99, Number(document.getElementById("warningPercent").value || 80)));
  const days = {}; let activeWithLimit = 0;
  for (let day = 1; day <= 7; day++) {
    const check = document.querySelector(`.day-enabled[data-day="${day}"]`);
    const input = document.querySelector(`.day-limit[data-day="${day}"]`);
    const limit = Math.max(0, Number(input.value || 0));
    days[String(day)] = { enabled: check.checked, limit };
    if (check.checked && limit > 0) activeWithLimit++;
  }
  if (enabled && activeWithLimit === 0) return alert("Pilih minimal satu hari dan isi batas pengeluarannya.");
  try {
    await fetchJson("ajax/settings.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ daily_budget: { enabled, warning_percent: warningPercent, days } }) });
    budgetModal.close(); await load();
  } catch (err) { alert(err.message); }
});

async function delTx(id) {
  if (!confirm("Hapus transaksi ini? Anda dapat membatalkannya dari menu Riwayat.")) return;
  const tx = (state.transactions || []).find(row => String(row.id) === String(id));
  const expectedVersion = tx?.updated_at || tx?.created_at || "";
  try {
    const result = await fetchJson("ajax/delete_transaction.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ id, expected_version: expectedVersion }) }, {kind:"delete_transaction",preview:{id}});
    await load();
    if (result?.offline_queued) showOfflineToast("Penghapusan menunggu sinkronisasi.");
    else showFeatureToast("Transaksi dihapus · dapat dibatalkan dari Riwayat");
  } catch (err) { alert(err.message); }
}

async function delMessage(id) {
  if (!confirm("Hapus pesan ini dari riwayat chat?\n\nTransaksi keuangan yang sudah tercatat tidak akan ikut terhapus.")) return;
  try {
    await fetchJson("ajax/delete_message.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id })
    });
    await load();
  } catch (err) { alert(err.message); }
}

async function clearAllMessages() {
  if (!(state.chats || []).length) return;
  if (!confirm("Hapus SEMUA riwayat chat?\n\nTransaksi, saldo, batas harian, dan foto yang masih terhubung ke transaksi tetap aman.")) return;
  try {
    await fetchJson("ajax/delete_message.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ all: true })
    });
    await load();
  } catch (err) { alert(err.message); }
}

document.getElementById("clearChatsBtn")?.addEventListener("click", clearAllMessages);

// ---------------- COMPACT TRANSACTION PANEL RUNTIME LAYOUT v7.10.9 ----------------
function installCompactTransactionPanel() {
  const txPanel = document.getElementById("txPanel");
  const filterPanel = document.getElementById("txFilterPanel");
  const filterToggle = document.getElementById("txFilterToggle");
  if (!txPanel || !filterPanel || !filterToggle) return;
  if (txPanel.dataset.compactLayoutV7109 === "1") return;
  txPanel.dataset.compactLayoutV7109 = "1";
  txPanel.classList.add("tx-layout-v7109");

  const periodBar = txPanel.querySelector(".tx-period-bar");
  const searchRow = txPanel.querySelector(".tx-search-export-row");
  const summary = txPanel.querySelector(".tx-filter-summary");
  const filterWrap = filterToggle.closest(".tx-filter-wrap") || filterPanel.parentElement;

  // Pencarian + Unduh harus selalu terlihat di luar dropdown filter.
  if (filterWrap && searchRow && searchRow.parentElement !== filterWrap) {
    filterWrap.insertBefore(searchRow, filterToggle);
  } else if (filterWrap && searchRow && searchRow.nextElementSibling !== filterToggle) {
    filterWrap.insertBefore(searchRow, filterToggle);
  }

  // Semua kontrol lainnya, termasuk periode dan ringkasan, masuk ke dropdown.
  if (periodBar && periodBar.parentElement !== filterPanel) {
    filterPanel.insertBefore(periodBar, filterPanel.firstChild);
  } else if (periodBar && filterPanel.firstElementChild !== periodBar) {
    filterPanel.insertBefore(periodBar, filterPanel.firstChild);
  }
  if (summary && summary.parentElement !== filterPanel) filterPanel.appendChild(summary);

  // Label dibuat singkat dan konsisten.
  const drawerLabel = filterToggle.querySelector(".tx-filter-drawer-label");
  if (drawerLabel) {
    drawerLabel.innerHTML = '<b>Filter & Urutkan</b><small>Periode, jenis, dompet, kategori, tanggal & urutan</small>';
  } else {
    const textNodes = Array.from(filterToggle.childNodes).filter(n => n.nodeType === Node.TEXT_NODE && (n.textContent || "").trim());
    if (textNodes.length) {
      const label = document.createElement("span");
      label.className = "tx-filter-runtime-label";
      label.textContent = "Filter & Urutkan";
      filterToggle.replaceChild(label, textNodes[0]);
    }
  }

  filterPanel.hidden = true;
  filterToggle.setAttribute("aria-expanded", "false");
  filterToggle.classList.remove("is-open");
}

function ensureSidebarHistoryMenu() {
  const menu = document.querySelector("#appSidebar .sidebar-menu");
  if (!menu || menu.querySelector('[data-finance-open="history"]')) return;

  const button = document.createElement("button");
  button.type = "button";
  button.className = "sidebar-menu-item";
  button.dataset.financeOpen = "history";
  button.innerHTML = `
    <span class="sidebar-menu-icon sidebar-history-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24">
        <path d="M3 12a9 9 0 1 0 3-6.7L3 8"></path>
        <path d="M3 3v5h5"></path>
        <path d="M12 7v5l3 2"></path>
      </svg>
    </span>
    <span><b>Riwayat</b><small>Perubahan transaksi & undo</small></span>
    <span class="sidebar-arrow">›</span>`;

  const backup = menu.querySelector('[data-finance-open="backup"]');
  if (backup) menu.insertBefore(button, backup);
  else menu.appendChild(button);
}

installCompactTransactionPanel();
ensureSidebarHistoryMenu();

// ---------------- FILTER & SORT TRANSAKSI ----------------
document.getElementById("txFilterToggle")?.addEventListener("click", () => {
  const panel = document.getElementById("txFilterPanel");
  setTransactionFilterPanel(!!panel?.hidden);
});

function setTransactionExportMenu(open) {
  const menu = document.getElementById("txExportMenu");
  const toggle = document.getElementById("txExportToggle");
  if (!menu || !toggle) return;
  menu.hidden = !open;
  toggle.setAttribute("aria-expanded", open ? "true" : "false");
  toggle.classList.toggle("is-open", open);
}

document.getElementById("txExportToggle")?.addEventListener("click", (event) => {
  event.stopPropagation();
  const menu = document.getElementById("txExportMenu");
  setTransactionExportMenu(!!menu?.hidden);
});

document.getElementById("txExportMenu")?.addEventListener("click", (event) => {
  event.stopPropagation();
  if (event.target.closest("button")) setTransactionExportMenu(false);
});

document.addEventListener("click", (event) => {
  const wrap = document.querySelector(".tx-export-menu-wrap");
  if (wrap && !wrap.contains(event.target)) setTransactionExportMenu(false);
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") setTransactionExportMenu(false);
});
document.querySelectorAll("[data-tx-period]").forEach(btn => btn.addEventListener("click", () => setTransactionPeriod(btn.dataset.txPeriod)));
document.getElementById("txFilterApply")?.addEventListener("click", applyTransactionFilters);
document.getElementById("txFilterReset")?.addEventListener("click", resetTransactionFilters);
document.getElementById("txDownloadReport")?.addEventListener("click", downloadTransactionReport);
document.getElementById("txFilterSort")?.addEventListener("change", applyTransactionFilters);
document.getElementById("txFilterType")?.addEventListener("change", applyTransactionFilters);
document.getElementById("txFilterFrom")?.addEventListener("change", () => {
  const from = document.getElementById("txFilterFrom")?.value || "";
  const toEl = document.getElementById("txFilterTo");
  if (toEl) toEl.min = from;
});
document.getElementById("txFilterTo")?.addEventListener("change", () => {
  const to = document.getElementById("txFilterTo")?.value || "";
  const fromEl = document.getElementById("txFilterFrom");
  if (fromEl) fromEl.max = to;
});

// Navigasi mobile.
document.querySelectorAll(".mobile-nav-item[data-view]").forEach((btn) => {
  btn.addEventListener("click", () => {
    const id = btn.dataset.view;
    document.querySelectorAll(".mobile-view").forEach((v) => v.classList.toggle("active", v.id === id));
    document.querySelectorAll(".mobile-nav-item[data-view]").forEach((b) => b.classList.toggle("active", b === btn));
    if (id === "chatPanel") setTimeout(() => { const el = document.getElementById("chatBody"); el.scrollTop = el.scrollHeight; }, 0);
  });
});

const messageInput = document.getElementById("message");
messageInput.addEventListener("focus", () => document.body.classList.add("composer-focus"));
messageInput.addEventListener("blur", () => setTimeout(() => document.body.classList.remove("composer-focus"), 120));

window.addEventListener("beforeunload", () => { try { ocrWorker?.terminate?.(); } catch (_) {} });

// ===== OFFLINE MODE + AUTO SYNC =====
function showOfflineToast(message) {
  if (typeof showFeatureToast === "function") return showFeatureToast(message);
  console.log(message);
}

async function renderSyncQueueDetails() {
  const box = document.getElementById("syncQueueList");
  if (!box || !window.FinanceOffline) return;
  try {
    const rows = await FinanceOffline.listQueue();
    if (!rows.length) {
      box.innerHTML = '<div class="empty compact">Semua perubahan sudah tersinkron.</div>';
      return;
    }
    const labelFor = row => {
      const path = String(row.url || '').split('?')[0].replace(/^\//,'');
      if (path === 'ajax/finance.php') return 'Chat / konfirmasi transaksi';
      if (path === 'ajax/edit_transaction.php') return 'Perubahan transaksi';
      if (path === 'ajax/delete_transaction.php') return 'Penghapusan transaksi';
      if (path === 'ajax/settings.php') return 'Pengaturan saldo';
      if (path === 'ajax/features.php') return 'Data pusat keuangan';
      return 'Perubahan data';
    };
    box.innerHTML = rows.map(row => {
      const conflict = !!row.conflict || Number(row.http_status||0) === 409;
      const failed = Number(row.attempts||0) > 0 && !!String(row.last_error||'').trim();
      const status = conflict ? 'Konflik' : failed ? 'Gagal' : 'Antre';
      const cls = conflict ? 'conflict' : failed ? 'failed' : 'queued';
      const when = row.created_at ? new Date(Number(row.created_at)).toLocaleString('id-ID') : '-';
      return `<div class="feature-list-row sync-queue-row ${cls}"><div class="feature-row-icon">${conflict?iconSvg('target'):failed?iconSvg('trash'):iconSvg('repeat')}</div><div class="feature-row-main"><b>${esc(labelFor(row))}</b><small>${esc(when)}${row.last_error?` · ${esc(row.last_error)}`:''}</small></div><span class="status-pill ${cls}">${status}</span><div class="row-actions"><button class="danger-link" data-sync-discard="${esc(row.id)}">Buang antrean</button></div></div>`;
    }).join('');
    box.querySelectorAll('[data-sync-discard]').forEach(btn => btn.onclick = async () => {
      if (!confirm('Buang perubahan offline ini? Data tersebut tidak akan dikirim ke server.')) return;
      await FinanceOffline.deleteQueue(btn.dataset.syncDiscard);
      await renderSyncQueueDetails();
      await updateConnectionUi();
    });
  } catch (e) {
    box.innerHTML = `<div class="empty compact">Status antrean tidak dapat dibaca: ${esc(e.message || 'unknown')}</div>`;
  }
}

async function updateConnectionUi(extra = {}) {
  let status = { online: navigator.onLine, pending: 0, failed: 0, conflicts: 0, syncing: false };
  try { if (window.FinanceOffline) status = { ...status, ...(await FinanceOffline.status()) }; } catch (_) {}
  status = { ...status, ...extra };
  const sidebar = document.getElementById("sidebarConnectionStatus");
  const title = document.getElementById("sidebarConnectionTitle");
  const text = document.getElementById("sidebarConnectionText");
  const count = document.getElementById("sidebarSyncCount");
  const chatStatus = document.getElementById("chatConnectionStatus");
  const banner = document.getElementById("syncBanner");
  const bannerTitle = document.getElementById("syncBannerTitle");
  const bannerText = document.getElementById("syncBannerText");
  const syncBtn = document.getElementById("syncNowBtn");

  const pending = Number(status.pending || 0);
  const failed = Number(status.failed || 0);
  const conflicts = Number(status.conflicts || 0);
  const statusParts = [pending ? `${pending} antre` : '', failed ? `${failed} gagal` : '', conflicts ? `${conflicts} konflik` : ''].filter(Boolean).join(' · ');
  document.body.classList.toggle("app-offline", !status.online);
  document.body.classList.toggle("app-syncing", !!status.syncing);
  document.body.classList.toggle("has-sync-banner", !status.online || !!status.syncing || pending > 0 || !!status.auth_required);
  if (sidebar) sidebar.classList.toggle("is-offline", !status.online);
  if (sidebar) sidebar.classList.toggle("is-syncing", !!status.syncing);
  if (sidebar) sidebar.classList.toggle("has-conflict", conflicts > 0);
  if (count) { count.textContent = conflicts ? `${pending}!` : pending; count.hidden = pending <= 0; }

  if (!status.online) {
    if (title) title.textContent = "Offline";
    if (text) text.textContent = pending ? `${statusParts} menunggu internet.` : "Data tersimpan di perangkat.";
    if (chatStatus) { chatStatus.textContent = "● Offline"; chatStatus.classList.add("is-offline"); }
    if (banner) banner.hidden = false;
    if (bannerTitle) bannerTitle.textContent = "Mode Offline";
    if (bannerText) bannerText.textContent = pending ? `${statusParts}. Perubahan akan dicoba lagi saat internet kembali.` : "Aplikasi menggunakan data terakhir yang tersimpan di perangkat.";
    if (syncBtn) syncBtn.hidden = true;
    renderSyncQueueDetails();
    return;
  }

  if (status.syncing) {
    if (title) title.textContent = "Menyinkronkan";
    if (text) text.textContent = pending ? `${pending} perubahan sedang dikirim…` : "Memeriksa data…";
    if (chatStatus) { chatStatus.textContent = "● Sync…"; chatStatus.classList.remove("is-offline"); }
    if (banner) banner.hidden = false;
    if (bannerTitle) bannerTitle.textContent = "Menyinkronkan data";
    if (bannerText) bannerText.textContent = "Menggabungkan perubahan offline dengan data server…";
    if (syncBtn) syncBtn.hidden = true;
    renderSyncQueueDetails();
    return;
  }

  if (pending > 0) {
    if (title) title.textContent = conflicts ? "Online · Konflik" : (failed ? "Online · Ada kegagalan" : "Online · Pending");
    if (text) text.textContent = statusParts;
    if (chatStatus) { chatStatus.textContent = `● Online · ${statusParts}`; chatStatus.classList.remove("is-offline"); }
    if (banner) banner.hidden = false;
    if (bannerTitle) bannerTitle.textContent = status.auth_required ? "Perlu buka PIN" : (conflicts ? "Ada konflik sinkronisasi" : failed ? "Sebagian data gagal tersinkron" : "Menunggu sinkronisasi");
    if (bannerText) bannerText.textContent = status.auth_required ? "Data offline aman tersimpan. Buka sesi/PIN agar sinkronisasi dapat dilanjutkan." : conflicts ? "Data di server berubah lebih dulu pada perangkat lain. Buka Pusat Keuangan → Backup & Sinkronisasi untuk melihat detail konflik." : failed ? "Beberapa perubahan gagal dikirim. Buka status sinkronisasi untuk melihat penyebabnya." : `${pending} perubahan siap dikirim ke server.`;
    if (syncBtn) { syncBtn.hidden = !!status.auth_required; syncBtn.disabled = false; }
  } else {
    if (title) title.textContent = "Online";
    if (text) text.textContent = "Semua data sudah tersinkron.";
    if (chatStatus) { chatStatus.textContent = "● Online"; chatStatus.classList.remove("is-offline"); }
    if (banner) banner.hidden = true;
  }
  renderSyncQueueDetails();
}

async function enrichQueuedPhotoOcr() {
  if (!window.FinanceOffline || !navigator.onLine) return;
  const rows = await FinanceOffline.listQueue();
  let worker = null;
  for (const row of rows) {
    if (String(row.url).split("?")[0].replace(/^\//, "") !== "ajax/finance.php") continue;
    if (row.body?.type !== "formdata") continue;
    const entries = row.body.value || [];
    const valueOf = (key) => entries.find(e => e.key === key && !e.is_blob)?.value ?? "";
    const setValue = (key, value) => {
      const found = entries.find(e => e.key === key && !e.is_blob);
      if (found) found.value = String(value ?? ""); else entries.push({ key, value: String(value ?? ""), is_blob: false });
    };
    const mode = String(valueOf("image_mode") || "auto");
    if (mode === "attachment" || String(valueOf("ocr_text") || "").trim()) continue;
    const photo = entries.find(e => e.key === "photo" && e.is_blob && e.blob);
    if (!photo) continue;
    try {
      worker = worker || await ensureOcrWorker();
      const ret = await worker.recognize(photo.blob);
      const text = ret?.data?.text || "";
      const confidence = Number(ret?.data?.confidence || 0);
      const candidate = detectReceiptAmount(text);
      setValue("ocr_text", text);
      setValue("ocr_amount", candidate.amount || 0);
      setValue("ocr_score", candidate.score || 0);
      setValue("ocr_line", candidate.line || "");
      setValue("ocr_confidence", confidence || 0);
      row.preview = { ...(row.preview || {}), ocr_text: text, ocr_amount: candidate.amount || 0, ocr_score: candidate.score || 0 };
      await FinanceOffline.updateQueue(row);
    } catch (_) {
      // Foto tetap akan disinkronkan sebagai lampiran walau OCR gagal.
    }
  }
}

async function syncOfflineQueue(manual = false) {
  if (!window.FinanceOffline) return;
  if (!navigator.onLine) { await updateConnectionUi(); return; }
  try {
    await updateConnectionUi({ syncing: true });
    await enrichQueuedPhotoOcr();
    const result = await FinanceOffline.syncQueue();
    await updateConnectionUi({ auth_required: result.status === "auth_required" });
    if (result.synced > 0) {
      showOfflineToast(`${result.synced} data offline berhasil disinkronkan.`);
      await load();
    } else if (manual && result.status === "synced") {
      showOfflineToast("Semua data sudah tersinkron.");
    }
  } catch (err) {
    console.warn("Offline sync gagal", err);
    await updateConnectionUi();
    if (manual) alert("Sinkronisasi belum berhasil: " + (err.message || err));
  }
}

document.getElementById("syncNowBtn")?.addEventListener("click", () => syncOfflineQueue(true));
document.getElementById("syncQueueRetry")?.addEventListener("click", () => syncOfflineQueue(true));
window.addEventListener("online", () => setTimeout(() => syncOfflineQueue(false), 500));
window.addEventListener("offline", () => updateConnectionUi());
if (window.FinanceOffline) FinanceOffline.onStatus((s) => updateConnectionUi(s));

document.querySelectorAll('form input[name="action"][value="logout"]').forEach((input) => {
  input.form?.addEventListener("submit", () => {
    try { navigator.serviceWorker?.controller?.postMessage({ type: "CLEAR_OFFLINE_SHELL" }); } catch (_) {}
  });
});

document.getElementById("appDataLoaderRetry")?.addEventListener("click", () => {
  runInitialDataLoad();
});

runInitialDataLoad();


// ===== REALTIME CHAT + DATA TANPA REFRESH =====
function scheduleRealtimePoll(delay = null) {
  clearTimeout(realtimeTimer);
  const ms = delay ?? (document.hidden ? REALTIME_HIDDEN_MS : REALTIME_ACTIVE_MS);
  realtimeTimer = setTimeout(runRealtimePoll, ms);
}

async function runRealtimePoll() {
  if (realtimeRequestRunning) {
    scheduleRealtimePoll();
    return;
  }
  if (!navigator.onLine) {
    scheduleRealtimePoll();
    return;
  }

  realtimeRequestRunning = true;
  try {
    const params = new URLSearchParams({
      since: String(realtimeRevision || 0),
      chat: realtimeChatSignature || "",
      tx: realtimeTransactionSignature || "",
      acc: realtimeAccountSignature || "",
      _: String(Date.now()),
    });

    const j = await fetchJson("ajax/realtime.php?" + params.toString());
    const previousTxSignature = realtimeTransactionSignature;
    const previousChatSignature = realtimeChatSignature;

    realtimeRevision = Number(j.revision || 0);
    realtimeChatSignature = String(j.chat_signature || "");
    realtimeTransactionSignature = String(j.transaction_signature || "");
    realtimeAccountSignature = String(j.account_signature || realtimeAccountSignature || "");

    if (j.changed) {
      if (j.summary) state.summary = j.summary;
      if (j.daily_budget) state.daily_budget = j.daily_budget;
      if (j.account) state.account = j.account;
      renderAccountPlan();

      const chatChanged = previousChatSignature !== realtimeChatSignature;
      const txChanged = previousTxSignature !== realtimeTransactionSignature;

      if (Array.isArray(j.chats)) state.chats = j.chats;
      renderSummaryCards();
      renderDailyBudget();

      if (chatChanged || previousChatSignature === "") {
        renderChats(false);
        const premiumPanel = document.querySelector('[data-finance-panel="premium"]');
        if (premiumPanel && !premiumPanel.hidden) loadSubscriptionPanel();
      }

      if (txChanged || previousTxSignature === "") {
        try { await reloadTransactionsOnly(); } catch (_) {}
      }
    }
  } catch (err) {
    // Poll realtime tidak boleh mengganggu penggunaan aplikasi.
    if (!isNetworkError(err)) console.warn("Realtime refresh gagal", err);
  } finally {
    realtimeRequestRunning = false;
    scheduleRealtimePoll();
  }
}

realtimeChannel?.addEventListener("message", (event) => {
  if (event?.data?.type !== "data-changed") return;
  // Tab lain pada device yang sama bisa update hampir seketika.
  scheduleRealtimePoll(80);
});

document.addEventListener("visibilitychange", () => {
  scheduleRealtimePoll(document.hidden ? REALTIME_HIDDEN_MS : 120);
});
window.addEventListener("focus", () => scheduleRealtimePoll(120));
window.addEventListener("online", () => scheduleRealtimePoll(150));
window.addEventListener("beforeunload", () => {
  clearTimeout(realtimeTimer);
  try { realtimeChannel?.close(); } catch (_) {}
});

scheduleRealtimePoll(900);

// ===== Mobile viewport fit =====
// Menggunakan visualViewport agar composer tetap terlihat saat keyboard HP terbuka.
(function initMobileViewportFit() {
  let lastHeight = 0;

  function updateAppViewportHeight() {
    const viewport = window.visualViewport;
    const height = Math.round(viewport ? viewport.height : window.innerHeight);
    if (!height || height === lastHeight) return;
    lastHeight = height;
    document.documentElement.style.setProperty('--app-vh', height + 'px');
  }

  updateAppViewportHeight();
  window.addEventListener('resize', updateAppViewportHeight, { passive: true });
  window.addEventListener('orientationchange', () => setTimeout(updateAppViewportHeight, 80), { passive: true });

  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', updateAppViewportHeight, { passive: true });
    window.visualViewport.addEventListener('scroll', updateAppViewportHeight, { passive: true });
  }

  const input = document.getElementById('message');
  if (input) {
    input.addEventListener('focus', () => {
      document.body.classList.add('composer-focus');
      requestAnimationFrame(updateAppViewportHeight);
      setTimeout(updateAppViewportHeight, 120);
      setTimeout(() => {
        const chatBody = document.getElementById('chatBody');
        if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;
      }, 160);
    });

    input.addEventListener('blur', () => {
      setTimeout(() => {
        document.body.classList.remove('composer-focus');
        updateAppViewportHeight();
      }, 160);
    });
  }
})();

// ===== FITUR KEUANGAN LANJUTAN (JSON NATIVE) =====
function featureState() { return state.features || {}; }
function el(id) { return document.getElementById(id); }

// ===== BERLANGGANAN / PREMIUM =====
function subscriptionData() { return subscriptionState || {}; }
function premiumStatusClass(status = "") {
  return ({waiting_payment:"waiting",pending_verification:"pending",approved:"approved",rejected:"rejected"})[status] || "waiting";
}
function premiumPlanDescription(plan) {
  if (!plan) return "";
  if (plan.key === "yearly") return `${rupiah(plan.monthly_equivalent)}/bln · ${rupiah(plan.amount)}/tahun`;
  if (plan.key === "lifetime") return `${rupiah(plan.amount)} · sekali beli`;
  return `${rupiah(plan.amount)}/bln`;
}

function selectedPremiumPlanKey() {
  return document.querySelector('input[name="premiumPlan"]:checked')?.value || "";
}
function renderPremiumCouponStatus(message = "", type = "") {
  const box = el("premiumCouponStatus");
  if (!box) return;
  box.className = "premium-coupon-status" + (type ? ` ${type}` : "");
  if (subscriptionCouponPreview && type === "success") {
    const c = subscriptionCouponPreview;
    box.innerHTML = `<b>Kupon ${esc(c.code)} digunakan</b><span>Potongan ${rupiah(c.discount_amount || 0)} · Total ${rupiah(c.final_amount || 0)}${c.expires_at ? ` · Berlaku s/d ${esc(c.expires_at)}` : ""}</span>`;
  } else {
    box.textContent = message || "Belum ada kupon digunakan.";
  }
}
function clearPremiumCouponPreview(message = "Masukkan kode kupon lalu tekan Gunakan.") {
  subscriptionCouponPreview = null;
  renderPremiumCouponStatus(message, "");
}
async function validatePremiumCoupon(showAlert = true) {
  const input = el("premiumCouponCode");
  const code = (input?.value || "").trim().toUpperCase().replace(/[^A-Z0-9_-]+/g, "");
  const plan = selectedPremiumPlanKey();
  if (input) input.value = code;
  if (!code) {
    clearPremiumCouponPreview("Belum ada kupon digunakan.");
    return null;
  }
  if (!plan) {
    if (showAlert) alert("Pilih paket Premium terlebih dahulu.");
    return null;
  }
  renderPremiumCouponStatus("Memeriksa kupon...", "checking");
  try {
    const j = await fetchJson("ajax/subscription.php", {
      method:"POST",
      headers:{"Content-Type":"application/json"},
      body:JSON.stringify({action:"validate_coupon", plan_key:plan, coupon_code:code})
    });
    subscriptionCouponPreview = j.coupon || null;
    renderPremiumCouponStatus("", "success");
    return subscriptionCouponPreview;
  } catch (e) {
    subscriptionCouponPreview = null;
    renderPremiumCouponStatus(e.message || "Kupon tidak valid.", "error");
    if (showAlert) alert(e.message);
    return null;
  }
}
function renderPremiumBankPreview() {
  const sub = subscriptionData();
  const select = el("premiumBankSelect");
  const preview = el("premiumBankPreview");
  if (!select || !preview) return;
  const bank = (sub.banks || []).find(b => String(b.id) === String(select.value));
  if (!bank) {
    preview.innerHTML = "Pilih bank untuk melihat rekening tujuan.";
    preview.classList.remove("configured");
    return;
  }
  if (!bank.configured) {
    preview.innerHTML = `<b>${esc(bank.name)}</b><span>Rekening belum dikonfigurasi oleh admin.</span>`;
    preview.classList.remove("configured");
    return;
  }
  preview.innerHTML = `<b>${esc(bank.name)}</b><strong>${esc(bank.account_number)}</strong><span>a.n. ${esc(bank.account_name)}</span>`;
  preview.classList.add("configured");
}
function renderPremiumPlans() {
  const box = el("premiumPlanList");
  if (!box) return;
  const plans = subscriptionData().plans || [];
  const selected = box.querySelector('input[name="premiumPlan"]:checked')?.value || "";
  box.innerHTML = plans.map((plan, i) => `
    <label class="premium-plan-card ${plan.key === "yearly" ? "recommended" : ""}">
      <input type="radio" name="premiumPlan" value="${esc(plan.key)}" ${selected === plan.key || (!selected && i === 0) ? "checked" : ""}>
      <span class="premium-plan-check">✓</span>
      ${plan.key === "yearly" ? '<span class="premium-plan-tag">HEMAT</span>' : ''}
      <b>${esc(plan.label)}</b>
      <strong>${plan.key === "yearly" ? rupiah(plan.monthly_equivalent) : rupiah(plan.amount)}</strong>
      <small>${plan.key === "yearly" ? "/ bulan" : plan.key === "monthly" ? "/ bulan" : "sekali beli"}</small>
      <p>${esc(premiumPlanDescription(plan))}</p>
    </label>`).join("");
  box.querySelectorAll('input[name="premiumPlan"]').forEach(radio => radio.addEventListener("change", () => {
    subscriptionCouponPreview = null;
    const code = el("premiumCouponCode")?.value.trim() || "";
    if (code) validatePremiumCoupon(false);
    else renderPremiumCouponStatus("Belum ada kupon digunakan.", "");
  }));
}
function renderPremiumBanks() {
  const select = el("premiumBankSelect");
  if (!select) return;
  const prev = select.value;
  select.innerHTML = '<option value="">Pilih bank</option>' + (subscriptionData().banks || []).map(bank =>
    `<option value="${esc(bank.id)}"${!bank.configured ? " disabled" : ""}>${esc(bank.name)}${bank.configured ? "" : " — belum dikonfigurasi"}</option>`
  ).join("");
  if ([...select.options].some(o => o.value === prev && !o.disabled)) select.value = prev;
  renderPremiumBankPreview();
}
function renderPremiumInvoice(order) {
  const area = el("premiumInvoiceArea");
  const upload = el("premiumUploadArea");
  if (!area || !upload) return;
  upload.hidden = true;
  if (!order) {
    area.className = "premium-invoice-empty";
    area.innerHTML = "Belum ada invoice. Pilih paket dan bank untuk membuat tagihan.";
    return;
  }
  const status = String(order.status || "");
  const proof = order.proof_url ? `<button type="button" class="premium-proof-preview stored-photo" data-photo-url="${esc(order.proof_url)}"><img src="${esc(order.proof_url)}" alt="Bukti pembayaran" loading="lazy"></button>` : "";
  let action = "";
  let notice = "";
  if (status === "waiting_payment") {
    action = `<button type="button" class="btn primary wide" data-premium-pay="${Number(order.id)}">Bayar</button>`;
    notice = "Transfer sesuai total invoice ke rekening tujuan, lalu tekan Bayar untuk mengunggah bukti.";
  } else if (status === "pending_verification") {
    notice = Number(order.amount || 0) <= 0
      ? "Kupon menutupi seluruh tagihan. Invoice sedang menunggu verifikasi admin untuk mengaktifkan Premium."
      : "Pembayaran sedang diverifikasi silahkan mengecek secara berkala.";
  } else if (status === "rejected") {
    notice = "Pembelian ditolak karena bukti bayar tidak valid. Jika ingin mengajukan pertanyaan seputar pembelian tersebut silahkan hubungi admin melalui Chat WA Only +6282259866048 (Senin - Sabtu 09.00 WITA - 17.00 WITA).";
  } else if (status === "approved") {
    notice = "Invoice telah LUNAS dan akses Premium sudah diberikan.";
  }
  const hasCoupon = !!order.coupon?.code;
  const couponRows = hasCoupon ? `
      <div><span>Harga awal</span><b>${rupiah(order.base_amount ?? order.amount ?? 0)}</b></div>
      <div><span>Kupon</span><b>${esc(order.coupon.code)} · ${esc(order.coupon.discount_label || "")}</b></div>
      <div><span>Potongan</span><b class="coupon-discount">-${rupiah(order.discount_amount || 0)}</b></div>` : "";
  area.className = `premium-invoice-box ${premiumStatusClass(status)}`;
  area.innerHTML = `
    <div class="premium-invoice-top"><div><small>INVOICE</small><b>${esc(order.invoice_no || "-")}</b></div><span class="premium-order-status ${premiumStatusClass(status)}">${esc(order.status_label || status)}</span></div>
    <div class="premium-invoice-amount">${hasCoupon ? `<span class="premium-original-amount">${rupiah(order.base_amount ?? order.amount ?? 0)}</span>` : ""}<small>Total yang harus dibayarkan</small><strong>${rupiah(order.amount || 0)}</strong></div>
    <div class="premium-invoice-lines">
      <div><span>Paket</span><b>${esc(order.plan_label || "-")}</b></div>
      ${couponRows}
      <div><span>Bank tujuan</span><b>${esc(order.bank_name || "-")}</b></div>
      <div><span>Nomor Rekening</span><b>${esc(order.bank_account_number || "-")}</b></div>
      <div><span>Atas Nama</span><b>${esc(order.bank_account_name || "-")}</b></div>
      <div><span>Nama Pengirim</span><b>${esc(order.sender_name || "-")}</b></div>
      <div><span>Rekening Pengirim</span><b>${esc(order.sender_account_number || "-")}</b></div>
    </div>
    ${proof}
    <div class="premium-invoice-notice ${premiumStatusClass(status)}">${esc(notice)}</div>
    ${action}`;
  area.querySelector("[data-premium-pay]")?.addEventListener("click", () => {
    upload.hidden = false;
    el("premiumProofFile")?.click();
    setTimeout(() => upload.scrollIntoView({behavior:"smooth", block:"nearest"}), 120);
  });
  bindStoredPhotos(area);
}
function renderPremiumTrialCard() {
  const card = el("premiumTrialCard");
  if (!card) return;
  const sub = subscriptionData();
  const account = sub.account || state.account || {};
  const plan = account.plan || {};
  const trial = account.trial || plan.trial || {};
  const title = el("premiumTrialTitle");
  const text = el("premiumTrialText");
  const meta = el("premiumTrialMeta");
  const kicker = el("premiumTrialKicker");
  const btn = el("startPremiumTrial");
  const paidPremium = !!plan.active && plan.source === "paid";

  card.classList.remove("is-active", "is-expired");
  if (account.role === "super_admin" || paidPremium) {
    card.hidden = true;
    return;
  }

  if (trial.active || plan.source === "trial") {
    card.hidden = false;
    card.classList.add("is-active");
    if (kicker) kicker.textContent = "FREE TRIAL SEDANG AKTIF";
    if (title) title.textContent = "Seluruh fitur Premium sudah terbuka";
    const until = formatPremiumDateTime(trial.expires_at || plan.expires_at || "");
    const days = Number(trial.remaining_days || 0);
    if (text) text.textContent = `${days > 0 ? `${days} hari tersisa. ` : ""}${until ? `Trial berlaku sampai ${until}. ` : ""}Setelah berakhir, akses Premium akan terkunci dan Anda perlu memilih paket berbayar.`;
    if (meta) meta.textContent = "Anda tetap dapat membeli paket Premium sekarang jika ingin melanjutkan tanpa jeda.";
    if (btn) { btn.hidden = true; btn.disabled = true; }
    return;
  }

  if (trial.eligible) {
    card.hidden = false;
    if (kicker) kicker.textContent = "FREE TRIAL PREMIUM";
    if (title) title.textContent = "Coba seluruh fitur Premium selama 7 hari";
    if (text) text.textContent = "Trial tidak aktif otomatis. Tekan tombol di samping dan konfirmasikan aktivasi untuk memulai masa coba 7 hari.";
    if (meta) meta.textContent = "Hanya dapat digunakan satu kali untuk setiap akun. Tidak ada penagihan otomatis.";
    if (btn) { btn.hidden = false; btn.disabled = false; btn.textContent = "Coba Premium 7 Hari"; }
    return;
  }

  if (trial.used) {
    card.hidden = false;
    card.classList.add("is-expired");
    if (kicker) kicker.textContent = trial.cancelled ? "FREE TRIAL DIAKHIRI" : "FREE TRIAL SELESAI";
    if (title) title.textContent = "Masa coba Premium sudah digunakan";
    const until = formatPremiumDateTime(trial.expires_at || "");
    if (text) text.textContent = trial.cancelled
      ? "Trial dihentikan dan tidak dapat diaktifkan kembali. Pilih paket Premium untuk melanjutkan akses."
      : `Masa trial telah berakhir${until ? ` pada ${until}` : ""}. Pilih salah satu paket Premium untuk melanjutkan fitur lanjutan.`;
    if (meta) meta.textContent = "Free Trial hanya tersedia satu kali per akun.";
    if (btn) { btn.hidden = true; btn.disabled = true; }
    return;
  }

  card.hidden = true;
}

function renderPremiumPanel() {
  const sub = subscriptionData();
  if (!sub || !Object.keys(sub).length) return;
  if (sub.account) state.account = sub.account;
  renderAccountPlan();
  renderPremiumPlans();
  renderPremiumBanks();
  renderPremiumTrialCard();
  const premium = isPremiumUser();
  const trialActive = isTrialPremium();
  const trial = premiumTrialState();
  const title = el("premiumHeroTitle");
  const text = el("premiumHeroText");
  if (title) {
    if (trialActive) title.textContent = "Free Trial Premium aktif";
    else if (premium) title.textContent = "Premium Anda aktif";
    else if (trial.used) title.textContent = "Free Trial telah berakhir";
    else title.textContent = "Buka fitur keuangan unggulan";
  }
  if (text) {
    if (trialActive) text.textContent = `${premiumExpiryText()}. Setelah masa trial berakhir, pilih paket Premium untuk melanjutkan akses.`;
    else if (premium) text.textContent = `${premiumExpiryText()}. Anda tetap dapat membeli paket lagi untuk perpanjangan akses.`;
    else if (trial.used) text.textContent = "Masa coba 7 hari sudah digunakan. Pilih paket, lakukan pembayaran, lalu unggah bukti untuk melanjutkan fitur Premium.";
    else text.textContent = "Aktifkan Free Trial 7 hari terlebih dahulu jika ingin mencoba, atau langsung pilih paket Premium berbayar.";
  }
  const order = sub.open_order || sub.latest_order || null;
  renderPremiumInvoice(order);
  const createBtn = el("createPremiumInvoice");
  if (createBtn) {
    const open = sub.open_order;
    createBtn.disabled = !!open;
    createBtn.textContent = open ? (open.status === "pending_verification" ? "Menunggu Verifikasi" : "Selesaikan Invoice Aktif") : (trialActive ? "Beli Premium Sekarang" : (premium ? "Buat Invoice Perpanjangan" : "Buat Invoice"));
  }
}
async function loadSubscriptionPanel() {
  try {
    const j = await fetchJson("ajax/subscription.php?_=" + Date.now());
    subscriptionState = j.subscription || {};
    if (subscriptionState.account) state.account = subscriptionState.account;
    renderPremiumPanel();
  } catch (e) {
    const area = el("premiumInvoiceArea");
    if (area) area.innerHTML = `<div class="empty">${esc(e.message)}</div>`;
  }
}
el("startPremiumTrial")?.addEventListener("click", async () => {
  const trial = premiumTrialState();
  if (!trial.eligible) {
    renderPremiumPanel();
    return;
  }
  const confirmed = window.confirm(
    "Aktifkan Free Trial Premium selama 7 hari sekarang?\n\n" +
    "Trial hanya dapat digunakan satu kali untuk setiap akun. Setelah 7 hari, fitur Premium akan terkunci kembali dan Anda perlu membeli paket untuk melanjutkan. Tidak ada penagihan otomatis."
  );
  if (!confirmed) return;

  const btn = el("startPremiumTrial");
  const oldText = btn?.textContent || "Coba Premium 7 Hari";
  try {
    if (btn) { btn.disabled = true; btn.textContent = "Mengaktifkan..."; }
    const j = await fetchJson("ajax/subscription.php", {
      method: "POST",
      headers: {"Content-Type": "application/json"},
      body: JSON.stringify({action: "start_trial", confirm_trial: true})
    });
    subscriptionState = j.subscription || {};
    if (subscriptionState.account) state.account = subscriptionState.account;
    renderPremiumPanel();
    renderAccountPlan();
    showFeatureToast(j.message || "Free Trial Premium 7 hari aktif.");
    await load();
  } catch (e) {
    alert(e.message || "Free Trial belum dapat diaktifkan.");
    if (btn) { btn.disabled = false; btn.textContent = oldText; }
  }
});

el("premiumCouponCode")?.addEventListener("input", () => {
  subscriptionCouponPreview = null;
  const code = el("premiumCouponCode")?.value.trim() || "";
  renderPremiumCouponStatus(code ? "Kupon belum diterapkan. Tekan Gunakan." : "Belum ada kupon digunakan.", "");
});
el("premiumCouponCode")?.addEventListener("keydown", (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    validatePremiumCoupon(true);
  }
});
el("applyPremiumCoupon")?.addEventListener("click", () => validatePremiumCoupon(true));
el("premiumBankSelect")?.addEventListener("change", renderPremiumBankPreview);
el("createPremiumInvoice")?.addEventListener("click", async () => {
  const plan = document.querySelector('input[name="premiumPlan"]:checked')?.value || "";
  const bank = el("premiumBankSelect")?.value || "";
  const senderName = el("premiumSenderName")?.value.trim() || "";
  const senderAccount = el("premiumSenderAccount")?.value.trim() || "";
  const couponCode = el("premiumCouponCode")?.value.trim().toUpperCase() || "";
  if (!plan) return alert("Pilih paket berlangganan.");
  if (!bank) return alert("Pilih bank tujuan pembayaran.");
  if (!senderName || !senderAccount) return alert("Masukkan nama pengirim dan nomor rekening pengirim.");
  try {
    const j = await fetchJson("ajax/subscription.php", {
      method:"POST", headers:{"Content-Type":"application/json"},
      body:JSON.stringify({action:"create_invoice",plan_key:plan,bank_id:bank,sender_name:senderName,sender_account_number:senderAccount,coupon_code:couponCode})
    });
    subscriptionState = j.subscription || subscriptionState;
    renderPremiumPanel();
    showFeatureToast("Invoice berhasil dibuat");
  } catch (e) { alert(e.message); }
});
el("premiumProofFile")?.addEventListener("change", () => {
  const input = el("premiumProofFile");
  const file = input?.files?.[0];
  const label = el("premiumProofName");
  premiumProofPrepared = null;
  premiumProofPreparePromise = null;
  if (!file) {
    if (label) label.textContent = "Belum ada gambar dipilih";
    return;
  }
  if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) {
    if (label) label.textContent = "Format gambar tidak didukung";
    input.value = "";
    return alert("Bukti bayar harus berupa JPG, PNG, atau WEBP.");
  }
  if (file.size > 20 * 1024 * 1024) {
    if (label) label.textContent = "Gambar terlalu besar";
    input.value = "";
    return alert("Ukuran gambar maksimal 20 MB sebelum kompresi.");
  }

  if (label) label.textContent = `${file.name} · mengompres…`;
  premiumProofPreparePromise = compressImageForStorage(file, {
    maxSide: 1600,
    targetBytes: 600 * 1024,
    initialQuality: 0.82,
    minQuality: 0.64,
    maxInputBytes: 20 * 1024 * 1024,
  }).then((prepared) => {
    premiumProofPrepared = prepared;
    if (label) {
      label.textContent = prepared.compressed
        ? `${file.name} · ${formatImageBytes(prepared.originalSize)} → ${formatImageBytes(prepared.finalSize)} (hemat ${prepared.savedPercent}%)`
        : `${file.name} · ${formatImageBytes(prepared.finalSize)} · sudah efisien`;
    }
    return prepared;
  }).catch((err) => {
    premiumProofPrepared = null;
    if (label) label.textContent = "Kompresi gagal — pilih gambar lagi";
    input.value = "";
    alert(err.message || "Gambar gagal dikompres.");
    return null;
  });
});
el("uploadPremiumProof")?.addEventListener("click", async () => {
  const order = subscriptionData().open_order || subscriptionData().latest_order;
  const input = el("premiumProofFile");
  const original = input?.files?.[0];
  if (!order || order.status !== "waiting_payment") return alert("Invoice yang dapat dibayar tidak ditemukan.");
  if (!original) return input?.click();

  let prepared = premiumProofPrepared;
  if (!prepared && premiumProofPreparePromise) prepared = await premiumProofPreparePromise;
  if (!prepared) {
    try {
      prepared = await compressImageForStorage(original, { maxSide:1600, targetBytes:600*1024, initialQuality:0.82, minQuality:0.64 });
      premiumProofPrepared = prepared;
    } catch (e) {
      return alert(e.message || "Gambar gagal dikompres.");
    }
  }
  const file = prepared.file;
  if (file.size > 8*1024*1024) return alert("Gambar masih terlalu besar setelah kompresi. Pilih gambar lain.");

  const fd = new FormData();
  fd.append("action","upload_proof"); fd.append("order_id",String(order.id)); fd.append("proof",file,file.name);
  try {
    const j = await fetchJson("ajax/subscription.php", {method:"POST",body:fd});
    subscriptionState = j.subscription || subscriptionState;
    premiumProofPrepared = null;
    premiumProofPreparePromise = null;
    if (input) input.value = "";
    if (el("premiumProofName")) el("premiumProofName").textContent = "Belum ada gambar dipilih";
    renderPremiumPanel();
    showFeatureToast("Bukti pembayaran dikompres dan dikirim");
    await load();
  } catch (e) { alert(e.message); }
});

function optionHtml(value, label, selected = false) { return `<option value="${esc(value)}"${selected ? " selected" : ""}>${esc(label)}</option>`; }

function fillSelect(id, items, getValue, getLabel, includeEmpty = null) {
  const select = el(id); if (!select) return;
  const prev = select.value;
  let html = includeEmpty ? optionHtml(includeEmpty.value, includeEmpty.label) : "";
  html += (items || []).map(i => optionHtml(getValue(i), getLabel(i))).join("");
  select.innerHTML = html;
  if ([...select.options].some(o => o.value === prev)) select.value = prev;
}

function renderFeatureSelectOptions() {
  const f = featureState();
  const wallets = f.wallets || [];
  const categories = f.categories || [];
  const bills = f.bills || [];
  fillSelect("txFilterWallet", wallets, x => x.id, x => `${x.name} (${rupiah(x.available_balance ?? x.balance ?? 0)} tersedia)`, { value: "0", label: "Semua dompet" });
  fillSelect("txFilterCategory", categories, x => x.name, x => `${x.icon || ""} ${x.name}`.trim(), { value: "", label: "Semua kategori" });
  ["transferFrom","transferTo","billWallet","recurringWallet","editTxWallet","editTxFromWallet","editTxToWallet","createTxWallet","createTxFromWallet","createTxToWallet"].forEach(id => fillSelect(id, wallets, x => x.id, x => `${x.name} · tersedia ${rupiah(x.available_balance ?? x.balance ?? 0)}`));
  fillSelect("monthlyBudgetCategory", categories.filter(x => x.type === "expense" || x.type === "both"), x => x.id, x => `${x.icon || ""} ${x.name}`.trim());
  ["billCategory","recurringCategory","editTxCategory"].forEach(id => fillSelect(id, categories, x => x.name, x => `${x.icon || ""} ${x.name}`.trim()));
  fillSelect("editTxBill", bills, x => x.id, x => `${x.name} · ${rupiah(x.amount)}${x.paid ? " · lunas" : ""}`, {value:"0",label:"Tidak dihubungkan"});
  fillSelect("createTxBill", bills, x => x.id, x => `${x.name} · ${rupiah(x.amount)}${x.paid ? " · lunas" : ""}`, {value:"0",label:"Tidak dihubungkan"});
  const currentMonth = new Date().toISOString().slice(0,7);
  if (el("monthlyBudgetMonth") && !el("monthlyBudgetMonth").value) el("monthlyBudgetMonth").value = currentMonth;
  if (el("recurringNextRun") && !el("recurringNextRun").value) el("recurringNextRun").value = new Date().toISOString().slice(0,10);
  if (el("paydayDay")) el("paydayDay").value = String(f.payday_day || 1);
}

async function refreshFeatures() {
  const j = await fetchJson("ajax/features.php");
  state.features = j.features || {};
  if (j.summary) state.summary = j.summary;
  if (j.account) state.account = j.account;
  renderAccountPlan();
  renderFeatureSelectOptions();
  renderFinanceCenter();
  return j;
}

async function featureAction(payload, successText = "") {
  const j = await fetchJson("ajax/features.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
  state.features = j.features || state.features || {};
  if (j.summary) state.summary = j.summary;
  if (j.account) state.account = j.account;
  renderAccountPlan();
  renderFeatureSelectOptions();
  renderFinanceCenter();
  if (successText) showFeatureToast(successText);
  return j;
}

function showFeatureToast(message) {
  let t = document.querySelector(".feature-toast");
  if (!t) { t = document.createElement("div"); t.className = "feature-toast"; document.body.appendChild(t); }
  t.textContent = message; t.classList.add("show"); clearTimeout(t._tm); t._tm = setTimeout(() => t.classList.remove("show"), 2200);
}

const financeCenter = el("financeCenter");
function isSuperAdminFinanceTab(tab = "") {
  return String(tab).startsWith("admin-");
}
function updateFinanceCenterHeading(tab = "") {
  const titles = {
    "admin-overview": ["Ringkasan Super Admin", "Statistik akun, Premium, aktivitas, dan transaksi seluruh pengguna."],
    "admin-broadcast": ["Broadcast Email", "Kirim pengumuman dan informasi penting ke pengguna terverifikasi."],
    "admin-notifications": ["Notifikasi Admin", "Pantau invoice Premium baru dan bukti pembayaran pengguna."],
    "admin-payments": ["Pembayaran Premium", "Verifikasi invoice dan bukti pembayaran pengguna."],
    "admin-plans": ["Paket Langganan", "Atur harga, nama, durasi, dan deskripsi paket Premium."],
    "admin-coupons": ["Kupon Premium", "Kelola kode diskon, nilai potongan, dan masa berlaku kupon."],
    "admin-banks": ["Rekening Pembayaran", "Kelola rekening tujuan pembayaran Premium."],
    "admin-users": ["Akun Pengguna", "Kelola paket dan akses pengguna secara manual."],
  };
  const title = el("financeCenterTitle");
  const subtitle = el("financeCenterSubtitle");
  const adminCopy = titles[tab];
  if (adminCopy) {
    if (title) title.textContent = adminCopy[0];
    if (subtitle) subtitle.textContent = adminCopy[1];
  } else {
    if (title) title.textContent = "Pusat Keuangan";
    if (subtitle) subtitle.textContent = "Kelola dompet, budget, tagihan, target, analitik, backup dan aplikasi.";
  }
}
function selectFinanceTab(tab) {
  const premiumTabs = new Set(["analytics","wallets","budgets","bills","recurring","goals"]);
  if (premiumTabs.has(tab) && !isPremiumUser()) {
    tab = "premium";
    showFeatureToast("Fitur tersebut tersedia untuk akun Premium.");
  }
  if (isSuperAdminFinanceTab(tab) && !window.FINANCE_APP?.isSuperAdmin) {
    showFeatureToast("Menu ini khusus Super Admin.");
    return;
  }
  updateFinanceCenterHeading(tab);
  document.querySelectorAll("[data-finance-tab]").forEach(b => b.classList.toggle("active", b.dataset.financeTab === tab));
  document.querySelectorAll("[data-finance-panel]").forEach(p => p.hidden = p.dataset.financePanel !== tab);
  if (tab === "premium") loadSubscriptionPanel();
  if (isSuperAdminFinanceTab(tab)) { loadAdminPanel(); refreshAdminNotifications(true); }
}
async function openFinanceCenter(tab = "analytics") {
  if (typeof isMobileSidebar === "function" && isMobileSidebar()) closeSidebar();
  if (isSuperAdminFinanceTab(tab) && !window.FINANCE_APP?.isSuperAdmin) {
    showFeatureToast("Menu ini khusus Super Admin.");
    return;
  }
  try { await refreshFeatures(); } catch (_) {}
  if (!isPremiumUser() && ["analytics","wallets","budgets","bills","recurring","goals"].includes(tab)) tab = "premium";
  selectFinanceTab(tab); financeCenter?.showModal();
}
document.querySelectorAll("[data-finance-open]").forEach(btn => btn.addEventListener("click", () => openFinanceCenter(btn.dataset.financeOpen)));
document.querySelectorAll("[data-finance-tab]").forEach(btn => btn.addEventListener("click", () => selectFinanceTab(btn.dataset.financeTab)));
el("closeFinanceCenter")?.addEventListener("click", () => financeCenter.close());

function renderFinanceCenter() {
  const f = featureState();
  renderWallets(f.wallets || []);
  renderCategories(f.categories || [], f.monthly_budgets || []);
  renderBills(f.bills || []);
  renderRecurring(f.recurring || []);
  renderGoals(f.goals || []);
  renderAnalytics(f.analytics || {}, f.prediction || {});
  renderHistory(f.history || []);
  renderNotificationForm(f.notifications || {});
  renderFeatureSelectOptions();
  renderSyncQueueDetails();
}

function renderWallets(wallets) {
  const box = el("walletList"); if (!box) return;
  box.innerHTML = wallets.length ? wallets.map(w => `<div class="feature-list-row"><div class="feature-row-icon">${walletBalanceIcon(w.type)}</div><div class="feature-row-main"><b>${esc(w.name)}</b><small>${esc(w.type)} · total ${rupiah(w.balance)}${Number(w.reserved_balance||0)>0?` · disisihkan ${rupiah(w.reserved_balance)}`:""}${Number(w.minimum_balance||0)>0?` · minimum ${rupiah(w.minimum_balance)}`:""}</small></div><strong>${rupiah(w.available_balance ?? w.balance ?? 0)}<small class="money-caption"> tersedia</small></strong><div class="row-actions"><button data-wallet-edit="${w.id}">Edit</button><button class="danger-link" data-wallet-archive="${w.id}">Arsip</button></div></div>`).join("") : '<div class="empty">Belum ada dompet.</div>';
  box.querySelectorAll("[data-wallet-edit]").forEach(b => b.onclick = () => { const w=wallets.find(x=>Number(x.id)===Number(b.dataset.walletEdit)); if(!w)return;el("walletId").value=w.id;el("walletName").value=w.name;el("walletType").value=w.type;el("walletInitial").value=w.initial_balance;if(el("walletReserved"))el("walletReserved").value=Number(w.reserved_balance||0);if(el("walletMinimum"))el("walletMinimum").value=Number(w.minimum_balance||0); });
  box.querySelectorAll("[data-wallet-archive]").forEach(b => b.onclick = async()=>{if(confirm("Arsipkan dompet ini? Transaksi lama tetap tersimpan.")){try{await featureAction({action:"wallet_archive",id:Number(b.dataset.walletArchive)},"Dompet diarsipkan");await load();}catch(e){alert(e.message)}}});
}
el("saveWallet")?.addEventListener("click", async()=>{try{await featureAction({action:"wallet_save",id:Number(el("walletId").value||0),name:el("walletName").value,type:el("walletType").value,initial_balance:Number(el("walletInitial").value||0),reserved_balance:Number(el("walletReserved")?.value||0),minimum_balance:Number(el("walletMinimum")?.value||0)},"Dompet disimpan");el("walletId").value="";el("walletName").value="";el("walletInitial").value="";if(el("walletReserved"))el("walletReserved").value="";if(el("walletMinimum"))el("walletMinimum").value="";await load();}catch(e){alert(e.message)}});

el("saveTransfer")?.addEventListener("click", async()=>{try{await featureAction({action:"wallet_transfer",from_wallet_id:Number(el("transferFrom").value),to_wallet_id:Number(el("transferTo").value),amount:Number(el("transferAmount").value||0),note:el("transferNote").value},"Transfer dicatat");el("transferAmount").value="";el("transferNote").value="";await load();}catch(e){alert(e.message)}});

function renderCategories(categories, budgets) {
  const box=el("categoryList"); if(!box)return;
  const budgetMap=Object.fromEntries((budgets||[]).map(b=>[Number(b.category_id),b]));
  box.innerHTML=categories.length?categories.map(c=>{const b=budgetMap[Number(c.id)];return `<div class="feature-list-row"><div class="feature-row-icon">${categoryLineIcon(c)}</div><div class="feature-row-main"><b>${esc(c.name)}</b><small>${esc(c.type)}${(c.keywords||[]).length?" · "+esc(c.keywords.join(", ")):""}</small></div>${b?`<span class="mini-budget">${rupiah(b.spent)} / ${rupiah(b.limit)}</span>`:""}<div class="row-actions"><button data-category-edit="${c.id}">Edit</button><button class="danger-link" data-category-archive="${c.id}">Arsip</button></div></div>`}).join(""):'<div class="empty">Belum ada kategori.</div>';
  box.querySelectorAll("[data-category-edit]").forEach(b=>b.onclick=()=>{const c=categories.find(x=>Number(x.id)===Number(b.dataset.categoryEdit));if(!c)return;el("categoryId").value=c.id;el("categoryName").value=c.name;el("categoryType").value=c.type;el("categoryIcon").value=c.icon||"";el("categoryKeywords").value=(c.keywords||[]).join(", ");});
  box.querySelectorAll("[data-category-archive]").forEach(b=>b.onclick=async()=>{if(confirm("Arsipkan kategori ini?")){try{await featureAction({action:"category_archive",id:Number(b.dataset.categoryArchive)},"Kategori diarsipkan");}catch(e){alert(e.message)}}});
}
el("saveCategory")?.addEventListener("click",async()=>{try{await featureAction({action:"category_save",id:Number(el("categoryId").value||0),name:el("categoryName").value,type:el("categoryType").value,icon:el("categoryIcon").value,keywords:el("categoryKeywords").value},"Kategori disimpan");el("categoryId").value="";el("categoryName").value="";el("categoryIcon").value="";el("categoryKeywords").value="";}catch(e){alert(e.message)}});
el("saveMonthlyBudget")?.addEventListener("click",async()=>{try{await featureAction({action:"monthly_budget_set",month:el("monthlyBudgetMonth").value,category_id:Number(el("monthlyBudgetCategory").value||0),limit:Number(el("monthlyBudgetLimit").value||0)},"Budget disimpan");}catch(e){alert(e.message)}});

function formatBillDate(value){if(!value)return "-";const p=String(value).split("-");if(p.length!==3)return value;return `${p[2]}/${p[1]}/${p[0]}`;}
function renderBills(bills){const box=el("billList");if(!box)return;box.innerHTML=bills.length?bills.map(b=>`<div class="feature-list-row bill-${esc(b.status)}"><div class="feature-row-icon">${b.paid?iconSvg("check"):iconSvg("receipt")}</div><div class="feature-row-main"><b>${esc(b.name)}</b><small>Jatuh tempo ${esc(formatBillDate(b.due_date))} · ${esc(b.category||"Tagihan")}</small></div><strong>${rupiah(b.amount)}</strong><span class="status-pill ${esc(b.status)}">${b.paid?"Lunas":b.status==="overdue"?"Terlambat":b.status==="due_soon"?"Segera":"Belum"}</span><div class="row-actions">${!b.paid?`<button class="success-link" data-bill-pay="${b.id}">Bayar</button>`:""}<button data-bill-edit="${b.id}">Edit</button><button class="danger-link" data-bill-delete="${b.id}">Hapus</button></div></div>`).join(""):'<div class="empty">Belum ada tagihan/cicilan.</div>';
box.querySelectorAll("[data-bill-pay]").forEach(x=>x.onclick=async()=>{if(confirm("Tandai lunas dan catat sebagai pengeluaran?")){try{await featureAction({action:"bill_pay",id:Number(x.dataset.billPay)},"Tagihan dibayar");await load();}catch(e){alert(e.message)}}});box.querySelectorAll("[data-bill-edit]").forEach(x=>x.onclick=()=>{const b=bills.find(v=>Number(v.id)===Number(x.dataset.billEdit));if(!b)return;el("billId").value=b.id;el("billName").value=b.name;el("billAmount").value=b.amount;el("billDueDate").value=b.due_date||"";el("billCategory").value=b.category;el("billWallet").value=String(b.wallet_id||1);});box.querySelectorAll("[data-bill-delete]").forEach(x=>x.onclick=async()=>{if(confirm("Hapus tagihan ini?")){try{await featureAction({action:"bill_delete",id:Number(x.dataset.billDelete)},"Tagihan dihapus");}catch(e){alert(e.message)}}});}
el("saveBill")?.addEventListener("click",async()=>{try{const dueDate=el("billDueDate").value;if(!dueDate)throw new Error("Pilih tanggal jatuh tempo terlebih dahulu.");await featureAction({action:"bill_save",id:Number(el("billId").value||0),name:el("billName").value,amount:Number(el("billAmount").value||0),due_date:dueDate,category:el("billCategory").value,wallet_id:Number(el("billWallet").value||1),reminder_days:3,active:true},"Tagihan disimpan");el("billId").value="";el("billName").value="";el("billAmount").value="";el("billDueDate").value="";}catch(e){alert(e.message)}});

function renderRecurring(items){const box=el("recurringList");if(!box)return;box.innerHTML=items.length?items.map(r=>`<div class="feature-list-row"><div class="feature-row-icon">${iconSvg("repeat")}</div><div class="feature-row-main"><b>${esc(r.name)}</b><small>${r.type==="income"?"Pemasukan":"Pengeluaran"} · ${esc(r.frequency)} setiap ${r.interval} · berikutnya ${esc(r.next_run)}${r.last_error?` · <span class="danger-text">tertahan: ${esc(r.last_error)}</span>`:""}</small></div><strong class="${r.type}">${rupiah(r.amount)}</strong><div class="row-actions"><button data-recurring-edit="${r.id}">Edit</button><button class="danger-link" data-recurring-delete="${r.id}">Hapus</button></div></div>`).join(""):'<div class="empty">Belum ada transaksi berulang.</div>';
box.querySelectorAll("[data-recurring-edit]").forEach(x=>x.onclick=()=>{const r=items.find(v=>Number(v.id)===Number(x.dataset.recurringEdit));if(!r)return;el("recurringId").value=r.id;el("recurringName").value=r.name;el("recurringType").value=r.type;el("recurringAmount").value=r.amount;el("recurringCategory").value=r.category;el("recurringWallet").value=String(r.wallet_id||1);el("recurringFrequency").value=r.frequency;el("recurringInterval").value=r.interval;el("recurringNextRun").value=r.next_run;});box.querySelectorAll("[data-recurring-delete]").forEach(x=>x.onclick=async()=>{if(confirm("Hapus transaksi berulang ini?")){try{await featureAction({action:"recurring_delete",id:Number(x.dataset.recurringDelete)},"Jadwal dihapus");}catch(e){alert(e.message)}}});}
el("saveRecurring")?.addEventListener("click",async()=>{try{await featureAction({action:"recurring_save",id:Number(el("recurringId").value||0),name:el("recurringName").value,type:el("recurringType").value,amount:Number(el("recurringAmount").value||0),category:el("recurringCategory").value,wallet_id:Number(el("recurringWallet").value||1),frequency:el("recurringFrequency").value,interval:Number(el("recurringInterval").value||1),next_run:el("recurringNextRun").value,active:true},"Jadwal berulang disimpan");el("recurringId").value="";el("recurringName").value="";el("recurringAmount").value="";await load();}catch(e){alert(e.message)}});

function renderGoals(goals){const box=el("goalList");if(!box)return;box.innerHTML=goals.length?goals.map(g=>`<div class="goal-row"><div class="goal-top"><div><b>${esc(g.name)}</b><small>${rupiah(g.current_amount)} dari ${rupiah(g.target_amount)}${g.deadline?" · target "+esc(g.deadline):""}</small></div><strong>${g.percent}%</strong></div><div class="goal-progress"><span style="width:${Math.min(100,g.percent)}%"></span></div><div class="goal-actions"><span>Sisa ${rupiah(g.remaining)}${g.recommended_daily?" · saran "+rupiah(g.recommended_daily)+"/hari":""}</span><button data-goal-add="${g.id}">+ Progress</button><button data-goal-edit="${g.id}">Edit</button><button class="danger-link" data-goal-delete="${g.id}">Hapus</button></div></div>`).join(""):'<div class="empty">Belum ada target menabung.</div>';
box.querySelectorAll("[data-goal-add]").forEach(x=>x.onclick=async()=>{const a=Number(prompt("Tambahkan progress berapa rupiah?","100000")||0);if(a>0)try{await featureAction({action:"goal_contribute",id:Number(x.dataset.goalAdd),amount:a},"Progress diperbarui");}catch(e){alert(e.message)}});box.querySelectorAll("[data-goal-edit]").forEach(x=>x.onclick=()=>{const g=goals.find(v=>Number(v.id)===Number(x.dataset.goalEdit));if(!g)return;el("goalId").value=g.id;el("goalName").value=g.name;el("goalTarget").value=g.target_amount;el("goalCurrent").value=g.current_amount;el("goalDeadline").value=g.deadline||"";});box.querySelectorAll("[data-goal-delete]").forEach(x=>x.onclick=async()=>{if(confirm("Hapus target ini?"))try{await featureAction({action:"goal_delete",id:Number(x.dataset.goalDelete)},"Target dihapus");}catch(e){alert(e.message)}});}
el("saveGoal")?.addEventListener("click",async()=>{try{await featureAction({action:"goal_save",id:Number(el("goalId").value||0),name:el("goalName").value,target_amount:Number(el("goalTarget").value||0),current_amount:Number(el("goalCurrent").value||0),deadline:el("goalDeadline").value},"Target disimpan");el("goalId").value="";el("goalName").value="";el("goalTarget").value="";el("goalCurrent").value="";}catch(e){alert(e.message)}});

function auditActionLabel(row) {
  if (row.action === "delete" && row.entity_type === "transaction") return "Transaksi dihapus";
  if (row.action === "update" && row.entity_type === "transaction") return "Transaksi diubah";
  if (row.action === "initial_balance") return "Saldo awal / dana disisihkan / saldo minimum diubah";
  if (row.action === "update" && row.entity_type === "wallet") return "Dompet diubah";
  if (row.action === "create" && row.entity_type === "transaction") return "Transaksi dibuat";
  if (row.action === "undo") return "Perubahan dibatalkan";
  return row.label || `${row.entity_type || "Data"} ${row.action || "diubah"}`;
}
function auditValueSummary(row) {
  const src = row.before || row.after || {};
  if (row.entity_type === "transaction") return `${src.category || "Transaksi"}${src.amount ? ` · ${rupiah(src.amount)}` : ""}${src.transaction_date ? ` · ${src.transaction_date}` : ""}`;
  if (row.entity_type === "wallet") return `${src.name || "Dompet"}${src.initial_balance != null ? ` · saldo awal ${rupiah(src.initial_balance)}` : ""}${src.reserved_balance ? ` · disisihkan ${rupiah(src.reserved_balance)}` : ""}${src.minimum_balance ? ` · minimum ${rupiah(src.minimum_balance)}` : ""}`;
  return row.label || "Perubahan data";
}
function renderHistory(rows) {
  const box = el("historyList"); if (!box) return;
  box.innerHTML = rows.length ? rows.map(row => `<div class="feature-list-row history-row ${row.undone_at?'is-undone':''}"><div class="feature-row-icon">${row.action==='delete'?'↩':row.action==='undo'?'✓':'✎'}</div><div class="feature-row-main"><b>${esc(auditActionLabel(row))}</b><small>${esc(auditValueSummary(row))} · ${esc(row.created_at || "")}${row.undone_at?` · dibatalkan ${esc(row.undone_at)}`:""}</small></div><div class="row-actions">${row.undoable&&!row.undone_at?`<button class="success-link" data-history-undo="${Number(row.id)}">Batalkan</button>`:""}</div></div>`).join("") : '<div class="empty">Belum ada riwayat perubahan.</div>';
  box.querySelectorAll('[data-history-undo]').forEach(btn => btn.onclick = async () => {
    if (!confirm("Batalkan perubahan ini dan pulihkan data sebelumnya?")) return;
    try { await featureAction({action:"history_undo",id:Number(btn.dataset.historyUndo)},"Perubahan berhasil dibatalkan"); await load(); }
    catch(e){ alert(e.message || "Perubahan tidak dapat dibatalkan."); }
  });
}

function barRows(items, total, labelKey="category", valueKey="total") {return (items||[]).length?(items||[]).map(x=>{const v=Number(x[valueKey]||0);const pct=Number(x.percent ?? (total?Math.round(v/total*100):0));return `<div class="analytic-bar-row"><div><span>${esc(x[labelKey]||"")}</span><b>${rupiah(v)}</b></div><div class="analytic-track"><span style="width:${Math.min(100,pct)}%"></span></div><small>${pct}%</small></div>`}).join(""):'<div class="empty compact">Belum ada data.</div>';}
function renderAnalytics(a,p){const card=el("predictionCard");if(card){
const status=p.status||"unknown";
const labels={risk:"Berisiko kurang",tight:"Cukup ketat",safe:"Diperkirakan aman",unknown:"Data harian belum cukup",conditional:"Aman bersyarat"};
card.className="prediction-card "+(["unknown","conditional"].includes(status)?"tight":status);
card.innerHTML=`<div><small>Perkiraan sisa sebelum gajian ${esc(p.payday_date||"-")}</small><strong>${rupiah(p.predicted_balance||0)}</strong><p><b>Saldo tersedia ${rupiah(p.current_balance||0)}</b>${Number(p.reserved_balance||0)>0?` · dana disisihkan ${rupiah(p.reserved_balance)}`:""}${Number(p.minimum_balance||0)>0?` · saldo minimum ${rupiah(p.minimum_balance)}`:""}${Number(p.reserved_balance||0)>0||Number(p.minimum_balance||0)>0?` dari total ${rupiah(p.gross_balance||0)}`:""}</p><p>${p.days_left||0} hari lagi · pola pengeluaran <b>Harian</b> ${rupiah(p.average_daily_expense||0)}/hari. Transaksi Sekali bayar dan Berulang tidak dipaksakan menjadi rata-rata harian.</p><p>Estimasi belanja harian tersisa ${rupiah(p.estimated_daily_spend||0)} · tagihan belum lunas ${rupiah(p.upcoming_bills_total||0)} · pengeluaran berulang ${rupiah(p.recurring_expense||0)}</p><p>Hari ini sudah belanja ${rupiah(p.daily_spent_today||0)}; perkiraan tambahan ${rupiah(p.remaining_daily_spend_today||0)}. Pemasukan terjadwal ${rupiah(p.recurring_income||0)} belum merupakan saldo tersedia.</p><p>Dana disisihkan dan saldo minimum tidak dihitung sebagai uang belanja. Pengeluaran historis non-harian ${rupiah(p.excluded_history_total||0)} tetap masuk laporan aktual tetapi tidak membesar-besarkan proyeksi harian.</p></div><span class="prediction-status">${labels[status]||labels.unknown}</span>`;
}
const k=el("analyticsKpis");if(k)k.innerHTML=`<div><small>Pemasukan bulan ini</small><b>${rupiah(a.income||0)}</b></div><div><small>Pengeluaran bulan ini</small><b>${rupiah(a.expense||0)}</b></div><div><small>Rata-rata aktual / hari (semua pengeluaran)</small><b>${rupiah(a.average_daily_expense||0)}</b></div><div><small>Vs bulan lalu</small><b>${a.expense_change_percent===null?"-":(a.expense_change_percent>0?"+":"")+a.expense_change_percent+"%"}</b></div>`;
const cat=el("analyticsCategoryBars");if(cat)cat.innerHTML=barRows(a.categories||[],a.expense||0);
const bud=el("analyticsBudgetBars");if(bud)bud.innerHTML=(a.monthly_budgets||[]).length?(a.monthly_budgets||[]).map(x=>`<div class="analytic-bar-row ${x.status}"><div><span>${esc(x.icon||"")} ${esc(x.category)}</span><b>${rupiah(x.spent)} / ${rupiah(x.limit)}</b></div><div class="analytic-track"><span style="width:${Math.min(100,x.percent)}%"></span></div><small>${x.percent}%</small></div>`).join(""):'<div class="empty compact">Belum ada budget kategori bulan ini.</div>';}
el("paydayDay")?.addEventListener("change",async()=>{try{await featureAction({action:"payday_set",day:Number(el("paydayDay").value)},"Tanggal gajian disimpan");}catch(e){alert(e.message)}});

function renderNotificationForm(n){if(!el("notifyEnabled"))return;el("notifyEnabled").checked=!!n.enabled;el("notifyDaily").checked=!!n.daily_budget;el("notifyBills").checked=!!n.bills;el("notifyLow").checked=!!n.low_balance;if(el("notifyEmailEnabled"))el("notifyEmailEnabled").checked=n.email_enabled!==false;el("notifyLowThreshold").value=Number(n.low_balance_threshold||100000);}
el("saveNotificationSettings")?.addEventListener("click",async()=>{try{await featureAction({action:"notifications_set",settings:{enabled:el("notifyEnabled").checked,daily_budget:el("notifyDaily").checked,bills:el("notifyBills").checked,low_balance:el("notifyLow").checked,email_enabled:el("notifyEmailEnabled")?.checked!==false,low_balance_threshold:Number(el("notifyLowThreshold").value||0)}},"Pengaturan notifikasi disimpan");checkFinanceNotifications();}catch(e){alert(e.message)}});
el("requestNotifyBtn")?.addEventListener("click",async()=>{if(!("Notification" in window))return alert("Browser ini tidak mendukung notifikasi.");const p=await Notification.requestPermission();showFeatureToast(p==="granted"?"Notifikasi diizinkan":"Izin notifikasi belum diberikan");});

function notificationEmailKind(key){if(String(key).startsWith("bill_"))return "bill";if(String(key).startsWith("budget_"))return "budget";if(String(key)==="low_balance")return "low_balance";return "info";}
async function emailNotificationOnce(key,title,body){const n=featureState()?.notifications||{};if(n.email_enabled===false)return;try{await fetchJson("ajax/email_notifications.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({key,kind:notificationEmailKind(key),title,message:body})});}catch(_){/* email tidak boleh mengganggu notifikasi utama */}}
function notifyOnce(key,title,body){const today=new Date().toISOString().slice(0,10);const k="finance_notify_"+key+"_"+today;if(localStorage.getItem(k))return;localStorage.setItem(k,"1");if(("Notification" in window)&&Notification.permission==="granted"){try{new Notification(title,{body,icon:"assets/icon.webp"});}catch(_){}}emailNotificationOnce(key,title,body);}
function checkFinanceNotifications(){const f=featureState(),n=f.notifications||{};if(!n.enabled)return;const b=state.daily_budget||{};if(n.daily_budget&&["warning","reached","exceeded"].includes(b.status))notifyOnce("budget_"+b.status,"Peringatan batas harian",b.message||"Pengeluaran mendekati batas.");if(n.bills){(f.bills||[]).filter(x=>["due_soon","overdue"].includes(x.status)).forEach(x=>notifyOnce("bill_"+x.id,"Tagihan "+x.name,x.status==="overdue"?"Tagihan sudah melewati jatuh tempo.":"Jatuh tempo "+formatBillDate(x.due_date)+" · "+rupiah(x.amount)));}if(n.low_balance&&Number(state.summary?.balance||0)<=Number(n.low_balance_threshold||0))notifyOnce("low_balance","Saldo rendah","Saldo saat ini "+rupiah(state.summary?.balance||0));}

// Tambah transaksi manual
const txCreateModal=el("transactionCreateModal");

function localTodayValue() {
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

function fillCreateTxCategories() {
  const select = el("createTxCategory");
  if (!select) return;
  const type = el("createTxType")?.value || "expense";
  const categories = (featureState()?.categories || []).filter(c => {
    const catType = String(c.type || "both").toLowerCase();
    return catType === "both" || catType === type;
  });
  const prev = select.value;
  select.innerHTML = categories.map(c => optionHtml(c.name, `${c.icon || ""} ${c.name}`.trim())).join("");
  if ([...select.options].some(o => o.value === prev)) select.value = prev;
  else if ([...select.options].some(o => o.value === "Lainnya")) select.value = "Lainnya";
}

function syncCreateTxType() {
  const type = el("createTxType")?.value || "expense";
  const transfer = type === "transfer";
  const expense = type === "expense";

  document.querySelectorAll(".create-transfer-field").forEach(x => x.hidden = !transfer);
  document.querySelectorAll(".create-standard-field").forEach(x => x.hidden = transfer);
  document.querySelectorAll(".create-expense-field").forEach(x => x.hidden = !expense);

  if (!transfer) fillCreateTxCategories();

  const hint = el("createTxHint");
  if (hint) {
    hint.textContent = transfer
      ? "Transfer memindahkan saldo antar dompet dan tidak mengubah total saldo keseluruhan."
      : expense
        ? "Pengeluaran akan mengurangi saldo tersedia pada dompet yang dipilih."
        : "Pemasukan akan menambah saldo pada dompet yang dipilih.";
  }
}

function resetCreateTransactionForm() {
  if (el("createTxType")) el("createTxType").value = "expense";
  if (el("createTxAmount")) el("createTxAmount").value = "";
  if (el("createTxDate")) el("createTxDate").value = localTodayValue();
  if (el("createTxNote")) el("createTxNote").value = "";
  if (el("createTxSpendingKind")) el("createTxSpendingKind").value = "once";
  if (el("createTxBill")) el("createTxBill").value = "0";

  renderFeatureSelectOptions();
  fillCreateTxCategories();

  const wallets = featureState()?.wallets || [];
  const defaultWallet = wallets.find(w => w.is_default) || wallets[0];
  if (defaultWallet && el("createTxWallet")) el("createTxWallet").value = String(defaultWallet.id);
  if (defaultWallet && el("createTxFromWallet")) el("createTxFromWallet").value = String(defaultWallet.id);
  if (wallets.length > 1 && el("createTxToWallet")) {
    const other = wallets.find(w => Number(w.id) !== Number(defaultWallet?.id));
    if (other) el("createTxToWallet").value = String(other.id);
  }

  syncCreateTxType();
}

el("addTransactionBtn")?.addEventListener("click", async () => {
  try {
    if (!state.features?.wallets?.length) await refreshFeatures();
    resetCreateTransactionForm();
    if (!txCreateModal?.open) txCreateModal?.showModal();
    setTimeout(() => el("createTxAmount")?.focus(), 80);
  } catch (e) {
    alert(e.message || "Form transaksi tidak dapat dibuka.");
  }
});

el("createTxType")?.addEventListener("change", syncCreateTxType);
el("closeTransactionCreate")?.addEventListener("click", () => txCreateModal?.close());
el("cancelTransactionCreate")?.addEventListener("click", () => txCreateModal?.close());

el("saveTransactionCreate")?.addEventListener("click", async () => {
  const saveBtn = el("saveTransactionCreate");
  const type = el("createTxType")?.value || "expense";
  const amount = Number(el("createTxAmount")?.value || 0);
  const transactionDate = el("createTxDate")?.value || "";

  if (amount <= 0) return alert("Nominal harus lebih dari nol.");
  if (!/^\d{4}-\d{2}-\d{2}$/.test(transactionDate)) return alert("Tanggal transaksi belum valid.");

  const payload = {
    action: "transaction_create",
    type,
    amount,
    transaction_date: transactionDate,
    note: el("createTxNote")?.value.trim() || ""
  };

  if (type === "transfer") {
    payload.from_wallet_id = Number(el("createTxFromWallet")?.value || 0);
    payload.to_wallet_id = Number(el("createTxToWallet")?.value || 0);
    if (!payload.from_wallet_id || !payload.to_wallet_id) return alert("Pilih dompet asal dan tujuan.");
    if (payload.from_wallet_id === payload.to_wallet_id) return alert("Dompet asal dan tujuan harus berbeda.");
  } else {
    payload.wallet_id = Number(el("createTxWallet")?.value || 0);
    payload.category = el("createTxCategory")?.value || "Lainnya";
    if (!payload.wallet_id) return alert("Pilih dompet transaksi.");

    if (type === "expense") {
      payload.spending_kind = el("createTxSpendingKind")?.value || "once";
      payload.bill_id = Number(el("createTxBill")?.value || 0);
    }
  }

  const oldText = saveBtn?.textContent || "Simpan Transaksi";
  if (saveBtn) {
    saveBtn.disabled = true;
    saveBtn.textContent = "Menyimpan...";
  }

  try {
    await featureAction(payload);
    txCreateModal?.close();
    await load();
    showFeatureToast(
      type === "transfer"
        ? "Transfer berhasil dicatat"
        : type === "income"
          ? "Pemasukan berhasil ditambahkan"
          : "Pengeluaran berhasil ditambahkan"
    );
  } catch (e) {
    alert(e.message || "Transaksi gagal ditambahkan.");
  } finally {
    if (saveBtn) {
      saveBtn.disabled = false;
      saveBtn.textContent = oldText;
    }
  }
});

// Edit transaksi
const txEditModal=el("transactionEditModal");
function syncEditTxType(){const transfer=el("editTxType")?.value==="transfer";document.querySelectorAll(".edit-transfer-field").forEach(x=>x.hidden=!transfer);document.querySelectorAll(".edit-standard-field").forEach(x=>x.hidden=transfer);}
el("editTxType")?.addEventListener("change",syncEditTxType);
async function openTransactionEdit(id) {
  id = Number(id || 0);
  if (!id) return alert("ID transaksi tidak valid.");

  try {
    const j = await fetchJson("ajax/edit_transaction.php?id=" + encodeURIComponent(id));
    const t = j.transaction;
    if (!t) throw new Error("Data transaksi tidak ditemukan.");

    state.features = state.features || {};
    state.features.wallets = j.wallets || state.features.wallets || [];
    state.features.categories = j.categories || state.features.categories || [];
    state.features.bills = j.bills || state.features.bills || [];
    renderFeatureSelectOptions();

    el("editTxId").value = String(t.id || "");
    el("editTxType").value = t.type || "expense";
    el("editTxAmount").value = Number(t.amount || 0);
    el("editTxDate").value = t.transaction_date || new Date().toISOString().slice(0, 10);
    el("editTxCategory").value = t.category || "Lainnya";
    el("editTxNote").value = t.note || "";
    el("editTxWallet").value = String(t.wallet_id || 1);
    el("editTxFromWallet").value = String(t.from_wallet_id || 1);
    el("editTxToWallet").value = String(t.to_wallet_id || 1);
    if (el("editTxSpendingKind")) el("editTxSpendingKind").value = t.spending_kind || (t.source === "recurring" ? "recurring" : (t.source === "bill" ? "once" : "daily"));
    if (el("editTxBill")) el("editTxBill").value = String(t.bill_id || 0);
    if (txEditModal) txEditModal.dataset.expectedVersion = t.updated_at || t.created_at || "";

    syncEditTxType();
    if (!txEditModal?.open) txEditModal?.showModal();
  } catch (e) {
    alert(e.message || "Transaksi tidak dapat dibuka untuk diedit.");
  }
}

el("closeTransactionEdit")?.addEventListener("click", () => txEditModal?.close());
el("cancelTransactionEdit")?.addEventListener("click", () => txEditModal?.close());

el("saveTransactionEdit")?.addEventListener("click", async () => {
  const saveBtn = el("saveTransactionEdit");
  const type = el("editTxType")?.value || "expense";
  const amount = Number(el("editTxAmount")?.value || 0);
  const txDate = el("editTxDate")?.value || "";

  if (!Number(el("editTxId")?.value || 0)) return alert("ID transaksi tidak valid.");
  if (amount <= 0) return alert("Nominal harus lebih dari nol.");
  if (!/^\d{4}-\d{2}-\d{2}$/.test(txDate)) return alert("Tanggal transaksi belum valid.");

  const payload = {
    id: Number(el("editTxId").value),
    type,
    amount,
    transaction_date: txDate,
    note: el("editTxNote")?.value || "",
    expected_version: txEditModal?.dataset.expectedVersion || ""
  };

  if (type === "transfer") {
    payload.category = "Transfer Antar Dompet";
    payload.bill_id = 0;
    payload.from_wallet_id = Number(el("editTxFromWallet")?.value || 0);
    payload.to_wallet_id = Number(el("editTxToWallet")?.value || 0);
    if (!payload.from_wallet_id || !payload.to_wallet_id) return alert("Pilih dompet asal dan tujuan.");
    if (payload.from_wallet_id === payload.to_wallet_id) return alert("Dompet asal dan tujuan harus berbeda.");
  } else {
    payload.category = el("editTxCategory")?.value || "Lainnya";
    payload.wallet_id = Number(el("editTxWallet")?.value || 1);
    payload.spending_kind = el("editTxSpendingKind")?.value || "once";
    payload.bill_id = Number(el("editTxBill")?.value || 0);
  }

  const oldText = saveBtn?.textContent || "Simpan Perubahan";
  if (saveBtn) {
    saveBtn.disabled = true;
    saveBtn.textContent = "Menyimpan...";
  }

  try {
    await fetchJson("ajax/edit_transaction.php", {
      method: "POST",
      headers: {"Content-Type": "application/json"},
      body: JSON.stringify(payload)
    });
    txEditModal?.close();
    await load();
    showFeatureToast("Transaksi berhasil diperbarui");
  } catch (e) {
    alert(e.message || "Transaksi gagal diperbarui.");
  } finally {
    if (saveBtn) {
      saveBtn.disabled = false;
      saveBtn.textContent = oldText;
    }
  }
});

// Export sesuai filter aktif.
function downloadFiltered(format){if(!isPremiumUser())return openPremiumUpsell("Export CSV/Excel tersedia untuk akun Premium.");const params=new URLSearchParams();params.set("type",txFilters.type);params.set("sort",txFilters.sort);if(txFilters.search)params.set("search",txFilters.search);if(txFilters.wallet_id)params.set("wallet_id",String(txFilters.wallet_id));if(txFilters.category)params.set("category",txFilters.category);if(txFilters.from)params.set("from",txFilters.from);if(txFilters.to)params.set("to",txFilters.to);params.set("format",format);const a=document.createElement("a");a.href=legacyApiUrl("ajax/export.php?"+params.toString());a.style.display="none";document.body.appendChild(a);a.click();a.remove();}
el("txDownloadCsv")?.addEventListener("click",()=>downloadFiltered("csv"));el("txDownloadXls")?.addEventListener("click",()=>downloadFiltered("xls"));
let searchTimer;el("txFilterSearch")?.addEventListener("input",()=>{clearTimeout(searchTimer);searchTimer=setTimeout(applyTransactionFilters,450);});el("txFilterWallet")?.addEventListener("change",applyTransactionFilters);el("txFilterCategory")?.addEventListener("change",applyTransactionFilters);

// Backup / restore
el("downloadBackupBtn")?.addEventListener("click",(e)=>{if(!isPremiumUser()){e.preventDefault();openPremiumUpsell("Download backup tersedia untuk akun Premium.");}});
el("restoreBackupBtn")?.addEventListener("click",async()=>{if(!isPremiumUser())return openPremiumUpsell("Restore backup tersedia untuk akun Premium.");const file=el("restoreBackupFile")?.files?.[0];if(!file)return alert("Pilih file backup JSON terlebih dahulu.");if(!confirm("Restore akan mengganti data keuangan akun saat ini. Lanjutkan?"))return;const fd=new FormData();fd.append("backup",file);try{const j=await fetchJson("ajax/backup.php",{method:"POST",body:fd});showFeatureToast(j.message||"Backup dipulihkan");financeCenter.close();await load();}catch(e){alert(e.message)}});

// PWA install
let deferredInstallPrompt=null;window.addEventListener("beforeinstallprompt",e=>{e.preventDefault();deferredInstallPrompt=e;el("installPwaBtn")?.classList.add("ready");});
el("installPwaBtn")?.addEventListener("click",async()=>{if(deferredInstallPrompt){deferredInstallPrompt.prompt();await deferredInstallPrompt.userChoice;deferredInstallPrompt=null;}else alert("Jika tombol install tidak tersedia, gunakan menu browser → Tambahkan ke layar utama / Install app.");});
if("serviceWorker" in navigator){window.addEventListener("load",()=>navigator.serviceWorker.register("sw.js?v=58",{updateViaCache:"none"}).then(()=>navigator.serviceWorker.ready).then(reg=>{try{reg.active?.postMessage({type:"CACHE_CURRENT_SHELL",url:location.href});}catch(_){}}).catch(()=>{}));}

// Admin
async function adminPost(payload) {
  return fetchJson("ajax/admin.php", {method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(payload)});
}

let adminPanelUsers = [];
let adminBroadcastBusy = false;
const adminBroadcastPresets = {
  android_update:{title:"Pembaruan Aplikasi Android Tersedia",subject:"Versi terbaru Catatan Keuangan sudah tersedia",message:"Versi terbaru aplikasi Catatan Keuangan untuk Android sudah tersedia. Silakan perbarui aplikasi agar mendapatkan peningkatan fitur, keamanan, dan perbaikan terbaru.",action_label:"Unduh APK Terbaru",action_url:`${String(window.FINANCE_APP?.syncBase||location.origin).replace(/\/$/,"")}/android-download.php`},
  announcement:{title:"Informasi dari Catatan Keuangan",subject:"Informasi terbaru Catatan Keuangan",message:"Kami memiliki informasi terbaru untuk pengguna Catatan Keuangan.",action_label:"Buka Catatan Keuangan",action_url:`${String(window.FINANCE_APP?.syncBase||location.origin).replace(/\/$/,"")}/`},
  maintenance:{title:"Informasi Pemeliharaan Layanan",subject:"Informasi maintenance Catatan Keuangan",message:"Akan dilakukan pemeliharaan layanan. Beberapa fitur mungkin tidak dapat digunakan sementara selama proses berlangsung.",action_label:"Buka Catatan Keuangan",action_url:`${String(window.FINANCE_APP?.syncBase||location.origin).replace(/\/$/,"")}/`},
  security:{title:"Pemberitahuan Keamanan Akun",subject:"Informasi keamanan Catatan Keuangan",message:"Terdapat informasi keamanan penting terkait penggunaan akun Catatan Keuangan. Pastikan Anda hanya masuk melalui aplikasi atau situs resmi.",action_label:"Buka Catatan Keuangan",action_url:`${String(window.FINANCE_APP?.syncBase||location.origin).replace(/\/$/,"")}/`}
};
function applyAdminBroadcastPreset(force=false){const kind=el("adminBroadcastKind")?.value||"android_update",p=adminBroadcastPresets[kind];if(!p)return;[["adminBroadcastTitle","title"],["adminBroadcastSubject","subject"],["adminBroadcastMessage","message"],["adminBroadcastActionLabel","action_label"],["adminBroadcastActionUrl","action_url"]].forEach(([id,key])=>{const node=el(id);if(node&&(force||!node.value.trim()))node.value=p[key]||"";});updateAdminBroadcastTargetHint();}
function adminBroadcastEligibleCount(){const target=el("adminBroadcastTarget")?.value||"all";return adminPanelUsers.filter(u=>u.email_verified&&(target==="all"||(target==="premium"&&u.plan?.active)||(target==="free"&&!u.plan?.active))).length;}
function updateAdminBroadcastTargetHint(){const box=el("adminBroadcastProgress");if(!box||adminBroadcastBusy)return;box.textContent=`Target saat ini: ${adminBroadcastEligibleCount()} email terverifikasi.`;}
function renderAdminBroadcastHistory(rows){const box=el("adminBroadcastHistory");if(!box)return;rows=Array.isArray(rows)?rows:[];box.innerHTML=rows.length?rows.map(c=>{const x=c.counts||{},queued=Number(x.queued||0),waiting=Number(x.pending||0)+Number(x.sending||0)+queued,done=Number(x.sent||0)+Number(x.failed||0),total=Number(c.total||0),pct=total?Math.round(done/total*100):0,status=c.status==="completed"?"Selesai":c.status==="completed_with_errors"?"Selesai dengan error":c.status==="paused_quota"?"Dijeda — Kuota Gmail":c.status==="sending"?"Mengirim":"Antre",recipients=Array.isArray(c.recipients)?c.recipients:[],diag=recipients.length?`<details class="admin-broadcast-diagnostics"><summary>Detail pengiriman</summary><div class="admin-broadcast-diagnostic-list">${recipients.map(r=>{const label=r.status==="sent"?"Diterima SMTP":r.status==="failed"?"Gagal":r.status==="queued"?"Antre":r.status==="sending"?"Mengirim":"Menunggu",extra=r.status==="sent"?(r.smtp_response||r.message_id||""):(r.error||"");return `<div class="admin-broadcast-diagnostic-item"><span><b>${esc(r.email||"-")}</b><small>${esc(label)}${r.sent_at?` · ${esc(r.sent_at)}`:""}</small></span>${extra?`<small title="${esc(extra)}">${esc(extra)}</small>`:""}</div>`}).join("")}</div><small class="admin-broadcast-delivery-note">“Diterima SMTP” berarti server email menerima pesan. Status “Antre” berarti email belum dikirim dan tetap tersimpan untuk dilanjutkan nanti.</small></details>`:"",pause=c.status==="paused_quota"?`<small><b>Pengiriman dijeda:</b> ${esc(c.pause_message||"Kuota email harian tercapai. Penerima yang belum terkirim tetap di antrean.")}${c.paused_at?` · ${esc(c.paused_at)}`:""}</small>`:"",showRetry=Number(x.failed||0)>0||waiting>0||c.status==="paused_quota",retryLabel=c.status==="paused_quota"||waiting>0?"Proses Antrean":"Coba Lagi";return `<div class="admin-broadcast-card"><div class="admin-broadcast-row"><div class="admin-broadcast-icon">✉</div><div class="admin-broadcast-main"><b>${esc(c.title||"Broadcast")}</b><small>${esc(c.created_at||"")} · ${esc(c.target||"all")} · ${status}</small><div class="admin-broadcast-meter"><span style="width:${Math.min(100,pct)}%"></span></div><small>${Number(x.sent||0)} diterima SMTP · ${Number(x.failed||0)} gagal · ${waiting} antre/menunggu</small>${pause}${c.action_url_rewritten?`<small>Link APK langsung otomatis dialihkan ke halaman download resmi.</small>`:""}</div>${showRetry?`<button type="button" class="btn secondary admin-broadcast-retry" data-broadcast-retry="${Number(c.id)}">${retryLabel}</button>`:""}</div>${diag}</div>`}).join(""):'<div class="empty">Belum ada riwayat broadcast.</div>';box.querySelectorAll("[data-broadcast-retry]").forEach(btn=>btn.onclick=async()=>{if(adminBroadcastBusy)return;const id=Number(btn.dataset.broadcastRetry||0);try{adminBroadcastBusy=true;btn.disabled=true;btn.textContent="Memproses…";await adminPost({action:"email_broadcast_retry",campaign_id:id});const result=await processAdminBroadcast(id);const queued=Number(result?.counts?.queued||0);if(result?.status==="paused_quota"||queued>0)showFeatureToast(`${queued} email masih aman di antrean. Kuota Gmail belum tersedia.`);else showFeatureToast("Antrean broadcast selesai diproses.");}catch(e){alert(e.message)}finally{adminBroadcastBusy=false;await loadAdminPanel();updateAdminBroadcastTargetHint();}});}
async function processAdminBroadcast(campaignId){let guard=0;while(guard++<200){const j=await adminPost({action:"email_broadcast_process",campaign_id:campaignId,batch_size:3});const c=j.action_result||{},x=c.counts||{},queued=Number(x.queued||0),pending=Number(x.pending||0)+Number(x.sending||0)+queued,sent=Number(x.sent||0),failed=Number(x.failed||0),total=Number(c.total||0);const progress=el("adminBroadcastProgress");if(c.status==="paused_quota"){if(progress)progress.textContent=`Pengiriman dijeda: kuota Gmail habis. ${queued} email tersimpan di antrean.`;renderAdminBroadcastHistory(j.email_broadcasts||[]);return c;}if(progress)progress.textContent=`Mengirim… ${sent}/${total} diterima server email${failed?` · ${failed} gagal`:""}${queued?` · ${queued} antre`:""}`;renderAdminBroadcastHistory(j.email_broadcasts||[]);if(pending<=0)return c;}throw new Error("Proses broadcast dihentikan karena terlalu banyak iterasi.");}

function adminBroadcastPayload(){return {kind:el("adminBroadcastKind")?.value||"announcement",target:el("adminBroadcastTarget")?.value||"all",title:el("adminBroadcastTitle")?.value.trim()||"",subject:el("adminBroadcastSubject")?.value.trim()||"",message:el("adminBroadcastMessage")?.value.trim()||"",action_url:el("adminBroadcastActionUrl")?.value.trim()||"",action_label:el("adminBroadcastActionLabel")?.value.trim()||"",include_link:!!el("adminBroadcastIncludeLink")?.checked};}
async function sendAdminBroadcast(){if(adminBroadcastBusy)return;const payload=adminBroadcastPayload();if(!payload.title||!payload.subject||!payload.message)return alert("Judul, subjek, dan isi pesan wajib diisi.");const count=adminBroadcastEligibleCount();if(!count)return alert("Tidak ada email terverifikasi pada target ini.");if(!confirm(`Kirim broadcast ini ke ${count} email pengguna terverifikasi?`))return;const btn=el("adminBroadcastSend"),old=btn?.textContent||"Kirim Broadcast";try{adminBroadcastBusy=true;if(btn){btn.disabled=true;btn.textContent="Menyiapkan…";}const created=await adminPost({action:"email_broadcast_create",broadcast:payload});const campaignId=Number(created.action_result?.id||0);if(!campaignId)throw new Error("Broadcast tidak berhasil dibuat.");renderAdminBroadcastHistory(created.email_broadcasts||[]);if(btn)btn.textContent="Mengirim…";const result=await processAdminBroadcast(campaignId);const failed=Number(result?.counts?.failed||0),queued=Number(result?.counts?.queued||0);if(result?.status==="paused_quota"||queued>0)showFeatureToast(`Kuota Gmail habis. ${queued} email disimpan aman di antrean.`);else showFeatureToast(failed?`Broadcast selesai, ${failed} email gagal.`:"Broadcast sudah diterima server email. Cek Inbox/Spam penerima.");}catch(e){alert(e.message||"Broadcast gagal dikirim.");}finally{adminBroadcastBusy=false;if(btn){btn.disabled=false;btn.textContent=old;}await loadAdminPanel();updateAdminBroadcastTargetHint();}}
async function sendAdminBroadcastTest(){if(adminBroadcastBusy)return;const payload=adminBroadcastPayload();if(!payload.title||!payload.subject||!payload.message)return alert("Judul, subjek, dan isi pesan wajib diisi.");const btn=el("adminBroadcastTest"),old=btn?.textContent||"Kirim Tes ke Saya";try{adminBroadcastBusy=true;if(btn){btn.disabled=true;btn.textContent="Mengirim Tes…";}const j=await adminPost({action:"email_broadcast_test",broadcast:payload}),r=j.action_result||{};const response=r.smtp_response?`\n\nRespons SMTP: ${r.smtp_response}`:"";alert(`Email tes diterima server email untuk ${r.email||"email admin"}.\n\nMode: ${r.include_link?"dengan link eksternal":"AMAN tanpa link eksternal"}. Silakan cek Inbox, Spam, dan Promotions.${r.action_url_rewritten?"\nLink APK langsung telah dialihkan ke halaman download resmi.":""}${response}`);}catch(e){alert(e.message||"Email tes gagal dikirim.");}finally{adminBroadcastBusy=false;if(btn){btn.disabled=false;btn.textContent=old;}updateAdminBroadcastTargetHint();}}
el("adminBroadcastKind")?.addEventListener("change",()=>applyAdminBroadcastPreset(true));
el("adminBroadcastTarget")?.addEventListener("change",updateAdminBroadcastTargetHint);
el("adminBroadcastSend")?.addEventListener("click",sendAdminBroadcast);
el("adminBroadcastTest")?.addEventListener("click",sendAdminBroadcastTest);

// ===== SUPER ADMIN: NOTIFIKASI PEMBELIAN PREMIUM =====
let adminNotificationLastAlerted = Number(localStorage.getItem("finance_admin_notification_last_alerted") || 0);
let adminNotificationRequestRunning = false;

function updateAdminNotificationBadges(count) {
  count = Math.max(0, Number(count || 0));
  [el("adminNotificationBadge")].forEach((badge) => {
    if (!badge) return;
    badge.textContent = count > 99 ? "99+" : String(count);
    badge.hidden = count <= 0;
  });
}
function adminNotificationTime(value) {
  if (!value) return "";
  const d = new Date(String(value).replace(" ", "T"));
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleString("id-ID", { day:"2-digit", month:"short", hour:"2-digit", minute:"2-digit" });
}
function renderAdminNotificationList(rows) {
  const box = el("adminNotificationList");
  if (!box) return;
  rows = Array.isArray(rows) ? rows : [];
  box.innerHTML = rows.length ? rows.map(n => {
    const p = n.payload || {};
    const unread = !n.read_at;
    return `<div class="admin-notification-row${unread ? " unread" : ""}" data-admin-notification-id="${Number(n.id||0)}">
      <div class="admin-notification-icon">${String(n.type||"").includes("proof") ? iconSvg("check") : iconSvg("crown")}</div>
      <div class="admin-notification-copy">
        <div class="admin-notification-title"><b>${esc(n.title||"Notifikasi")}</b>${unread?'<span>BARU</span>':""}</div>
        <p>${esc(n.message||"")}</p>
        <small>${esc(adminNotificationTime(n.created_at))}${p.invoice_no?` · ${esc(p.invoice_no)}`:""}</small>
      </div>
      ${p.order_id ? `<button type="button" class="admin-notification-open" data-admin-notification-open="${Number(p.order_id)}">Lihat</button>` : ""}
    </div>`;
  }).join("") : '<div class="empty">Belum ada notifikasi admin.</div>';
  box.querySelectorAll("[data-admin-notification-open]").forEach(btn => btn.onclick = async () => {
    const orderId = Number(btn.dataset.adminNotificationOpen || 0);
    selectFinanceTab("admin-payments");
    await loadAdminPanel();
    const row = el("adminSubscriptionList")?.querySelector(`[data-admin-order-id="${orderId}"]`);
    if (row) {
      row.scrollIntoView({behavior:"smooth", block:"center"});
      row.classList.add("admin-payment-highlight");
      setTimeout(()=>row.classList.remove("admin-payment-highlight"), 1800);
    } else {
      showFeatureToast("Invoice tidak ditemukan pada daftar pembayaran.");
    }
  });
}
function alertAdminNotification(n) {
  if (!n) return;
  // Notifikasi Premium untuk Super Admin dikirim keluar aplikasi melalui email.
  // Saat aplikasi sedang dibuka, tampilkan toast + riwayat internal saja (tanpa browser/native push).
  showFeatureToast(`${n.title || "Notifikasi Admin"}: ${n.message || ""}`);
}
async function refreshAdminNotifications(silent=false) {
  if (!window.FINANCE_APP?.isSuperAdmin || adminNotificationRequestRunning) return;
  adminNotificationRequestRunning = true;
  try {
    const j = await fetchJson("ajax/admin_notifications.php?_=" + Date.now());
    const rows = Array.isArray(j.notifications) ? j.notifications : [];
    updateAdminNotificationBadges(j.unread_count || 0);
    renderAdminNotificationList(rows);
    const unread = rows.filter(n => !n.read_at).sort((a,b)=>Number(a.id||0)-Number(b.id||0));
    const newestNew = unread.filter(n => Number(n.id||0) > adminNotificationLastAlerted);
    if (newestNew.length) {
      const n = newestNew[newestNew.length - 1];
      adminNotificationLastAlerted = Math.max(adminNotificationLastAlerted, Number(n.id||0));
      localStorage.setItem("finance_admin_notification_last_alerted", String(adminNotificationLastAlerted));
      if (!silent) alertAdminNotification(n);
    }
  } catch (_) {
    // Notifikasi admin tidak boleh mengganggu fitur utama aplikasi.
  } finally { adminNotificationRequestRunning = false; }
}
async function markAdminNotificationsRead() {
  if (!window.FINANCE_APP?.isSuperAdmin) return;
  try {
    const j = await fetchJson("ajax/admin_notifications.php", {
      method:"POST", headers:{"Content-Type":"application/json"},
      body:JSON.stringify({action:"mark_all_read"})
    });
    updateAdminNotificationBadges(j.unread_count || 0);
    renderAdminNotificationList(j.notifications || []);
  } catch (e) { showFeatureToast(e.message || "Gagal memperbarui notifikasi."); }
}
el("adminMarkNotificationsRead")?.addEventListener("click", markAdminNotificationsRead);
if (window.FINANCE_APP?.isSuperAdmin) {
  setTimeout(() => refreshAdminNotifications(false), 1200);
  setInterval(() => { if (document.visibilityState !== "hidden") refreshAdminNotifications(false); }, 10000);
  document.addEventListener("visibilitychange", () => { if (document.visibilityState === "visible") refreshAdminNotifications(false); });
}


function adminPlanEditorHtml(plan = {}) {
  const key = String(plan.key || "");
  const permanent = !!plan.permanent;
  const months = Number(plan.months || 0);
  const amount = Number(plan.amount || 0);
  const monthlyEquivalent = Number(plan.monthly_equivalent || 0);
  const durationText = permanent ? "Permanen" : `${months} bulan`;
  return `<div class="admin-plan-row" data-admin-plan-row="${esc(key)}" data-plan-original-amount="${amount}">
    <div class="admin-plan-head">
      <div><span class="admin-plan-key">${esc(key)}</span><b>${esc(plan.label || key)}</b><small>${esc(durationText)}</small></div>
      <strong>${rupiah(amount)}</strong>
    </div>
    <div class="admin-plan-fields">
      <label>Kode Paket<input data-plan-key value="${esc(key)}" readonly aria-readonly="true"></label>
      <label>Nama Paket<input data-plan-label maxlength="80" value="${esc(plan.label || "")}" placeholder="Contoh: Bulanan"></label>
      <label>Harga (Rp)<input data-plan-amount type="number" min="1" max="2000000000" step="1000" inputmode="numeric" value="${amount}"></label>
      <label>Ekuivalen / Bulan<input data-plan-monthly type="number" min="0" max="2000000000" step="1000" inputmode="numeric" value="${monthlyEquivalent}"></label>
      <label>Durasi Bulan<input data-plan-months type="number" min="1" max="1200" value="${permanent ? 0 : Math.max(1, months)}" ${permanent ? "disabled" : ""}></label>
      <label class="admin-plan-permanent"><input data-plan-permanent type="checkbox" ${permanent ? "checked" : ""}> Paket permanen</label>
      <label class="admin-plan-description">Deskripsi<input data-plan-description maxlength="255" value="${esc(plan.description || "")}" placeholder="Teks yang tampil pada halaman pembelian"></label>
    </div>
    <div class="row-actions"><button type="button" data-plan-save="${esc(key)}">Simpan Perubahan</button></div>
  </div>`;
}
function bindAdminPlanRows(container) {
  container?.querySelectorAll("[data-admin-plan-row]").forEach(row => {
    const permanent = row.querySelector("[data-plan-permanent]");
    const months = row.querySelector("[data-plan-months]");
    if (permanent && months) permanent.addEventListener("change", () => {
      months.disabled = permanent.checked;
      if (permanent.checked) months.value = "0";
      else if (Number(months.value || 0) < 1) months.value = "1";
    });
  });
  container?.querySelectorAll("[data-plan-save]").forEach(btn => btn.onclick = async () => {
    const row = btn.closest("[data-admin-plan-row]");
    const key = String(btn.dataset.planSave || "");
    const plan = {
      key,
      label:row.querySelector("[data-plan-label]")?.value.trim() || "",
      amount:Number(row.querySelector("[data-plan-amount]")?.value || 0),
      monthly_equivalent:Number(row.querySelector("[data-plan-monthly]")?.value || 0),
      months:Number(row.querySelector("[data-plan-months]")?.value || 0),
      permanent:!!row.querySelector("[data-plan-permanent]")?.checked,
      description:row.querySelector("[data-plan-description]")?.value.trim() || "",
    };
    const oldAmount = Number(row.dataset.planOriginalAmount || 0);
    const priceText = oldAmount !== plan.amount ? ` Harga berubah dari ${rupiah(oldAmount)} menjadi ${rupiah(plan.amount)}.` : "";
    if (!confirm(`Simpan perubahan paket ${plan.label || key}?${priceText} Perubahan hanya berlaku untuk invoice baru.`)) return;
    try {
      await adminPost({action:"plan_save",plan});
      showFeatureToast("Paket langganan diperbarui");
      await loadAdminPanel();
    } catch(e) { alert(e.message); }
  });
}

function adminBankEditorHtml(bank = {}, isNew = false) {
  const id = String(bank.id || "");
  return `<div class="admin-bank-row" data-admin-bank-row="${esc(id || "new")}">
    <div class="admin-bank-fields">
      <label>Nama Bank<input data-bank-name value="${esc(bank.name || "")}" placeholder="Contoh: BNI"></label>
      <label>Nomor Rekening<input data-bank-number value="${esc(bank.account_number || "")}" inputmode="numeric" placeholder="Nomor rekening"></label>
      <label>Atas Nama<input data-bank-owner value="${esc(bank.account_name || "")}" placeholder="Nama pemilik rekening"></label>
      <label>Urutan<input data-bank-sort type="number" min="0" max="9999" value="${Number(bank.sort ?? 100)}"></label>
      <label class="admin-bank-enabled"><input data-bank-enabled type="checkbox" ${bank.enabled !== false ? "checked" : ""}> Tampilkan</label>
    </div>
    <div class="row-actions"><button type="button" data-bank-save="${esc(id)}">Simpan</button>${isNew ? '<button type="button" class="danger-link" data-bank-cancel>Batalkan</button>' : `<button type="button" class="danger-link" data-bank-delete="${esc(id)}">Hapus</button>`}</div>
  </div>`;
}
function bindAdminBankRows(container) {
  container?.querySelectorAll("[data-bank-save]").forEach(btn => btn.onclick = async () => {
    const row = btn.closest("[data-admin-bank-row]");
    const id = btn.dataset.bankSave || "";
    const bank = {
      id,
      name:row.querySelector("[data-bank-name]")?.value.trim() || "",
      account_number:row.querySelector("[data-bank-number]")?.value.trim() || "",
      account_name:row.querySelector("[data-bank-owner]")?.value.trim() || "",
      sort:Number(row.querySelector("[data-bank-sort]")?.value || 100),
      enabled:!!row.querySelector("[data-bank-enabled]")?.checked,
    };
    try { await adminPost({action:"bank_save",bank}); showFeatureToast("Rekening bank disimpan"); loadAdminPanel(); }
    catch(e){ alert(e.message); }
  });
  container?.querySelectorAll("[data-bank-delete]").forEach(btn => btn.onclick = async () => {
    if (!confirm("Hapus bank ini dari pilihan pembayaran? Invoice lama tetap menyimpan data rekening sebelumnya.")) return;
    try { await adminPost({action:"bank_delete",bank_id:btn.dataset.bankDelete}); showFeatureToast("Bank dihapus"); loadAdminPanel(); }
    catch(e){ alert(e.message); }
  });
  container?.querySelectorAll("[data-bank-cancel]").forEach(btn => btn.onclick = () => btn.closest("[data-admin-bank-row]")?.remove());
}
function adminCouponEditorHtml(coupon = {}, isNew = false) {
  const id = Number(coupon.id || 0);
  const type = coupon.discount_type || "percent";
  const expired = !!coupon.expires_at && String(coupon.expires_at) < new Date().toISOString().slice(0,10);
  return `<div class="admin-coupon-row${expired ? " expired" : ""}" data-admin-coupon-row="${id || "new"}">
    <div class="admin-coupon-fields">
      <label>Kode Kupon<input data-coupon-code maxlength="32" value="${esc(coupon.code || "")}" placeholder="HEMAT20"></label>
      <label>Jenis Diskon<select data-coupon-type><option value="percent"${type==="percent"?" selected":""}>Persen (%)</option><option value="fixed"${type==="fixed"?" selected":""}>Rupiah (Rp)</option></select></label>
      <label>Nilai<input data-coupon-value type="number" min="1" value="${Number(coupon.discount_value || 0)}" placeholder="20"></label>
      <label>Kedaluwarsa<input data-coupon-expiry type="date" value="${esc(coupon.expires_at || "")}"></label>
      <label class="admin-bank-enabled"><input data-coupon-enabled type="checkbox" ${coupon.enabled !== false ? "checked" : ""}> Aktif</label>
    </div>
    <div class="admin-coupon-info"><span>${type==="percent" ? `${Number(coupon.discount_value || 0)}%` : rupiah(coupon.discount_value || 0)}</span>${coupon.expires_at ? `<small>${expired ? "Kedaluwarsa" : "Berlaku s/d"} ${esc(coupon.expires_at)}</small>` : "<small>Tanpa batas waktu</small>"}</div>
    <div class="row-actions"><button type="button" data-coupon-save="${id}">Simpan</button>${isNew ? '<button type="button" class="danger-link" data-coupon-cancel>Batalkan</button>' : `<button type="button" class="danger-link" data-coupon-delete="${id}">Hapus</button>`}</div>
  </div>`;
}
function bindAdminCouponRows(container) {
  container?.querySelectorAll("[data-coupon-code]").forEach(input => input.addEventListener("input", () => {
    input.value = input.value.toUpperCase().replace(/[^A-Z0-9_-]+/g, "");
  }));
  container?.querySelectorAll("[data-coupon-save]").forEach(btn => btn.onclick = async () => {
    const row = btn.closest("[data-admin-coupon-row]");
    const coupon = {
      id:Number(btn.dataset.couponSave || 0),
      code:row.querySelector("[data-coupon-code]")?.value.trim() || "",
      discount_type:row.querySelector("[data-coupon-type]")?.value || "percent",
      discount_value:Number(row.querySelector("[data-coupon-value]")?.value || 0),
      expires_at:row.querySelector("[data-coupon-expiry]")?.value || "",
      enabled:!!row.querySelector("[data-coupon-enabled]")?.checked,
    };
    try { await adminPost({action:"coupon_save",coupon}); showFeatureToast("Kupon disimpan"); loadAdminPanel(); }
    catch(e){ alert(e.message); }
  });
  container?.querySelectorAll("[data-coupon-delete]").forEach(btn => btn.onclick = async () => {
    if (!confirm("Hapus kupon ini? Invoice lama yang sudah memakai kupon tetap tidak berubah.")) return;
    try { await adminPost({action:"coupon_delete",coupon_id:Number(btn.dataset.couponDelete)}); showFeatureToast("Kupon dihapus"); loadAdminPanel(); }
    catch(e){ alert(e.message); }
  });
  container?.querySelectorAll("[data-coupon-cancel]").forEach(btn => btn.onclick = () => btn.closest("[data-admin-coupon-row]")?.remove());
}
async function loadAdminPanel(){
  const userBox=el("adminUserList");
  if(!userBox)return;
  try{
    const j=await fetchJson("ajax/admin.php?_="+Date.now());
    adminPanelUsers=Array.isArray(j.users)?j.users:[];
    renderAdminBroadcastHistory(j.email_broadcasts||[]);
    applyAdminBroadcastPreset(false);
    updateAdminBroadcastTargetHint();
    const k=el("adminKpis");
    if(k)k.innerHTML=`<div><small>Total user</small><b>${j.stats.users}</b></div><div><small>Aktif 30 hari</small><b>${j.stats.active_30d}</b></div><div><small>Premium aktif</small><b>${j.stats.premium}</b></div><div><small>Total transaksi</small><b>${j.stats.transactions}</b></div>`;

    const subs=j.subscriptions||{};
    const pk=el("adminPaymentKpis");
    if(pk){const st=subs.stats||{};pk.innerHTML=`<div><small>Menunggu verifikasi</small><b>${Number(st.pending_verification||0)}</b></div><div><small>Belum bayar</small><b>${Number(st.waiting_payment||0)}</b></div><div><small>Disetujui</small><b>${Number(st.approved||0)}</b></div><div><small>Total pembayaran lunas</small><b>${rupiah(st.revenue||0)}</b></div>`;}

    const payBox=el("adminSubscriptionList");
    const orders=subs.orders||[];
    if(payBox){
      payBox.innerHTML=orders.length?orders.map(o=>`<div class="admin-payment-row ${premiumStatusClass(o.status)}" data-admin-order-id="${Number(o.id||0)}">
        <div class="admin-payment-main"><div class="admin-payment-title"><b>${esc(o.invoice_no)}</b><span class="premium-order-status ${premiumStatusClass(o.status)}">${esc(o.status_label||o.status)}</span></div><small>User: <b>${esc(o.username)}</b> · Paket ${esc(o.plan_label)} · Dibuat ${esc(o.created_at||"-")}</small>${o.coupon?.code?`<div class="admin-payment-price"><del>${rupiah(o.base_amount??o.amount??0)}</del><span>Kupon ${esc(o.coupon.code)} · -${rupiah(o.discount_amount||0)}</span></div>`:""}<div class="admin-payment-amount">${rupiah(o.amount||0)}</div><div class="admin-payment-meta"><span>Tujuan: ${esc(o.bank_name)} ${esc(o.bank_account_number)}</span><span>Pengirim: ${esc(o.sender_name)} · ${esc(o.sender_account_number)}</span>${o.coupon?.code?`<span>Kupon: ${esc(o.coupon.code)} (${esc(o.coupon.discount_label||"")})</span>`:""}</div>${o.rejection_reason?`<div class="admin-rejection-reason">${esc(o.rejection_reason)}</div>`:""}</div>
        <div class="admin-payment-proof">${o.proof_url?`<button type="button" class="stored-photo compact" data-photo-url="${esc(o.proof_url)}"><img src="${esc(o.proof_url)}" alt="Bukti bayar" loading="lazy"></button>`:(Number(o.amount||0)<=0?'<span>Tidak perlu bukti<br>Invoice Rp0</span>':'<span>Belum ada bukti</span>')}</div>
        <div class="admin-payment-actions">${o.status==="pending_verification"?`<button type="button" class="success-link" data-order-approve="${Number(o.id)}">Setujui & Aktifkan Premium</button><button type="button" class="danger-link" data-order-reject="${Number(o.id)}">Tolak</button>`:""}</div>
      </div>`).join(""):'<div class="empty">Belum ada pembelian Premium.</div>';
      bindStoredPhotos(payBox);
      payBox.querySelectorAll("[data-order-approve]").forEach(b=>b.onclick=async()=>{if(!confirm("Verifikasi pembayaran ini dan aktifkan Premium sesuai paket yang dibeli?"))return;try{await adminPost({action:"subscription_approve",order_id:Number(b.dataset.orderApprove)});showFeatureToast("Pembayaran disetujui & Premium diaktifkan");loadAdminPanel();}catch(e){alert(e.message)}});
      payBox.querySelectorAll("[data-order-reject]").forEach(b=>b.onclick=async()=>{const reason=prompt("Alasan penolakan (opsional):","Bukti bayar tidak valid.");if(reason===null)return;try{await adminPost({action:"subscription_reject",order_id:Number(b.dataset.orderReject),reason});showFeatureToast("Pembelian ditolak");loadAdminPanel();}catch(e){alert(e.message)}});
    }

    const planBox=el("adminPlanList");
    if(planBox){planBox.innerHTML=(subs.plans||[]).map(p=>adminPlanEditorHtml(p)).join("")||'<div class="empty">Belum ada paket langganan.</div>';bindAdminPlanRows(planBox);}

    const couponBox=el("adminCouponList");
    if(couponBox){couponBox.innerHTML=(subs.coupons||[]).map(c=>adminCouponEditorHtml(c,false)).join("")||'<div class="empty">Belum ada kupon Premium.</div>';bindAdminCouponRows(couponBox);}

    const bankBox=el("adminBankList");
    if(bankBox){bankBox.innerHTML=(subs.banks||[]).map(b=>adminBankEditorHtml(b,false)).join("")||'<div class="empty">Belum ada rekening pembayaran.</div>';bindAdminBankRows(bankBox);}

    userBox.innerHTML=(j.users||[]).map(u=>{const storedPlan=u.stored_plan||"free",storedExpiry=String(u.stored_expires_at||"").slice(0,10),trial=u.trial||{},trialInfo=trial.active?` · Trial aktif s/d ${esc(formatPremiumDateTime(trial.expires_at||""))}`:(trial.used?" · Trial sudah digunakan":"");return `<div class="admin-user-row"><div><b>${esc(u.username)}</b><small>${u.email_verified?"✓ ":""}${esc(u.email||"Email belum diatur")} · ${esc(u.role)} · ${u.transactions} transaksi · ${u.balance===null?"-":rupiah(u.balance)} · aktif ${esc(u.last_activity||"-")}${trialInfo}${u.premium_last_invoice?` · ${esc(u.premium_last_invoice)}`:""}</small></div><select data-admin-plan="${u.id}"><option value="free"${storedPlan==="free"?" selected":""}>Free</option><option value="premium"${storedPlan==="premium"?" selected":""}>Premium</option></select><input type="date" data-admin-exp="${u.id}" value="${esc(storedExpiry)}"><button data-admin-save="${u.id}">Simpan</button></div>`}).join("");
    userBox.querySelectorAll("[data-admin-save]").forEach(b=>b.onclick=async()=>{const id=Number(b.dataset.adminSave),plan=userBox.querySelector(`[data-admin-plan="${id}"]`).value,expires=userBox.querySelector(`[data-admin-exp="${id}"]`).value;try{await adminPost({action:"set_plan",user_id:id,plan,expires_at:expires});showFeatureToast("Paket user diperbarui");loadAdminPanel();}catch(e){alert(e.message)}});
  }catch(e){userBox.innerHTML=`<div class="empty">${esc(e.message)}</div>`;}
}
el("adminAddCoupon")?.addEventListener("click",()=>{const box=el("adminCouponList");if(!box)return;box.insertAdjacentHTML("afterbegin",adminCouponEditorHtml({code:"",discount_type:"percent",discount_value:10,expires_at:"",enabled:true},true));bindAdminCouponRows(box);box.querySelector('[data-admin-coupon-row="new"] input')?.focus();});
el("adminAddBank")?.addEventListener("click",()=>{const box=el("adminBankList");if(!box)return;box.insertAdjacentHTML("afterbegin",adminBankEditorHtml({name:"",account_number:"",account_name:"",enabled:true,sort:100},true));bindAdminBankRows(box);box.querySelector('[data-admin-bank-row="new"] input')?.focus();});

// Jalankan notifikasi setiap kali dashboard selesai dirender.
const originalRenderForFeatures = render;
// render sudah didefinisikan sebagai function declaration; panggil pengecekan melalui event ringan setelah load/update.
window.addEventListener("focus",()=>{if(state.features)checkFinanceNotifications();});
setInterval(()=>{if(state.features)checkFinanceNotifications();},60000);
