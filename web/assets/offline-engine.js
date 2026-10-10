(() => {
  'use strict';

  const DB = () => window.FinanceOffline;
  const CFG = () => window.FINANCE_APP || {};
  const dateKey = (d = new Date()) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const TODAY = () => dateKey(new Date());
  const clone = (v) => {
    try { return typeof structuredClone === 'function' ? structuredClone(v) : JSON.parse(JSON.stringify(v)); }
    catch (_) { return JSON.parse(JSON.stringify(v || {})); }
  };
  const money = (n) => Math.max(0, Math.round(Number(n || 0)));
  const lower = (s) => String(s || '').toLocaleLowerCase('id-ID');
  const cleanUrl = (u) => String(u || '').split('?')[0].replace(/^\//, '');

  function parseAmount(raw) {
    let s = String(raw || '').toLowerCase().trim().replace(/\s+/g, '').replace(/^rp\.?/i, '');
    let mult = 1;
    if (/(jt|juta)$/.test(s)) { mult = 1_000_000; s = s.replace(/(jt|juta)$/, ''); }
    else if (/(rb|ribu|k)$/.test(s)) { mult = 1_000; s = s.replace(/(rb|ribu|k)$/, ''); }
    if (mult > 1) {
      if (/^\d+[.,]\d+$/.test(s) && !/^\d{1,3}([.,]\d{3})+$/.test(s)) return Math.round(Number(s.replace(',', '.')) * mult);
      return Number(s.replace(/[.,]/g, '')) * mult || 0;
    }
    if (/^\d{1,3}(?:\.\d{3})+$/.test(s)) return Number(s.replace(/\./g, ''));
    if (/^\d{1,3}(?:,\d{3})+$/.test(s)) return Number(s.replace(/,/g, ''));
    return Number(s.replace(/\D/g, '')) || 0;
  }

  function categoryFor(text, type = 'expense') {
    const x = lower(text);
    if (/bensin|bbm|pertamax|pertalite/.test(x)) return 'Bensin';
    if (/makan|minum|kopi|sarapan|lunch|dinner/.test(x)) return 'Makan';
    if (/cicilan|angsuran|kredit/.test(x)) return 'Cicilan';
    if (/belanja|indomaret|alfamart|supermarket/.test(x)) return 'Belanja';
    if (/listrik|air|internet|wifi|tagihan|token/.test(x)) return 'Tagihan';
    if (/transport|ojol|grab|gojek|parkir|taxi|taksi/.test(x)) return 'Transportasi';
    if (type === 'income' && /gaji|salary/.test(x)) return 'Gaji';
    if (type === 'income' && /bonus|honor|freelance|fee/.test(x)) return 'Pemasukan';
    return type === 'income' ? 'Pemasukan' : 'Lainnya';
  }

  function normalizeMessage(value) {
    return String(value || '')
      .toLocaleLowerCase('id-ID')
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/\b(nda|nyanda|ndak|nggak|gak)\b/g, 'tidak')
      .replace(/\b(iyah|iyo|yaah)\b/g, 'iya')
      .trim();
  }

  function extractAdminFeeClause(value) {
    const text = String(value || '');
    const amount = '(?:rp\\.?\\s*)?(?:\\d{1,3}(?:[\\.,]\\d{3})+|\\d+(?:[\\.,]\\d+)?)(?:\\s*(?:juta|jt|ribu|rb|k))?';
    const fee = '(?:biaya\\s*(?:admin(?:istrasi)?(?:\\s+bank)?|administrasi|transfer|bank)|admin(?:istrasi)?(?:\\s+bank)?|administrasi|fee(?:\\s+bank)?|charge(?:\\s+bank)?)';
    const patterns = [
      new RegExp(`((?:\\s*(?:dan|serta|ditambah|plus|dengan|[+,;])\\s*)?(?:(?:ada|kena|dikenakan|terkena|termasuk)\\s+)?${fee}(?:nya)?\\s*(?:(?:sebesar|senilai|nominal(?:nya)?|adalah|:|=)\\s*)?(${amount})(?:\\s+rupiah)?)`, 'iu'),
      new RegExp(`((?:\\s*(?:dan|serta|ditambah|plus|dengan|[+,;])\\s*)?(${amount})\\s*(?:untuk\\s*)?${fee}(?:nya)?)`, 'iu'),
    ];
    for (const pattern of patterns) {
      const match = pattern.exec(text);
      if (!match) continue;
      const clause = String(match[1] || '');
      const amountText = String(match[2] || match[3] || '');
      const parsed = parseAmount(amountText);
      if (!clause || parsed <= 0) continue;
      const start = Number(match.index || 0);
      const cleanText = (text.slice(0, start) + ' ' + text.slice(start + clause.length))
        .replace(/\s*(?:dan|serta|ditambah|plus|dengan)\s*$/iu, '')
        .replace(/\s+/g, ' ').replace(/\s+([,.!?;])/g, '$1').trim().replace(/[;,+.]+$/g, '').trim();
      return { amount: parsed, cleanText, clause, index: start };
    }
    return null;
  }

  function localWalletMention(text, wallets) {
    const source = lower(text);
    if (!source) return null;
    const aliasGroups = {
      seabank: ['seabank', 'sea bank'], bca: ['bca', 'bank bca'], bri: ['bri', 'bank bri'],
      bni: ['bni', 'bank bni'], mandiri: ['mandiri', 'bank mandiri'], bsi: ['bsi', 'bank bsi'],
      jago: ['jago', 'bank jago'], dana: ['dana'], ovo: ['ovo'], gopay: ['gopay', 'go pay'],
      shopeepay: ['shopeepay', 'shopee pay'], tunai: ['tunai', 'cash', 'uang tunai']
    };
    const hits = [];
    for (const w of (wallets || [])) {
      if (!w || w.archived) continue;
      const name = lower(w.name).trim();
      if (!name || name === 'utama') continue;
      const variants = [name];
      const compactName = name.replace(/[^a-z0-9]/g, '');
      for (const [key, aliases] of Object.entries(aliasGroups)) {
        if (compactName.includes(key)) variants.push(...aliases);
      }
      for (const variant of new Set(variants)) {
        const escaped = variant.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\s+/g, '\\s*');
        if (!escaped) continue;
        const re = new RegExp(`(^|[^a-z0-9])${escaped}([^a-z0-9]|$)`, 'i');
        const m = re.exec(source);
        if (m) hits.push({ wallet: w, index: Number(m.index || 0), length: String(m[0] || '').length });
      }
    }
    hits.sort((a, b) => a.index - b.index || b.length - a.length);
    return hits[0]?.wallet || null;
  }

  function localCashWallet(wallets, excludeId = 0) {
    const rows = (wallets || []).filter(w => w && !w.archived && Number(w.id) !== Number(excludeId) && !['credit_card', 'receivable'].includes(lower(w.type)));
    const byName = rows.find(w => /\b(?:uang\s*tunai|tunai|cash)\b/i.test(String(w.name || '')));
    if (byName) return byName;
    return rows.find(w => lower(w.type) === 'cash') || null;
  }

  function localFallbackNonCashWallet(wallets, excludeId = 0) {
    const rows = (wallets || []).filter(w => w && !w.archived && Number(w.id) !== Number(excludeId) && !['credit_card', 'receivable'].includes(lower(w.type)));
    return rows.find(w => !/\b(?:uang\s*tunai|tunai|cash)\b/i.test(String(w.name || ''))) || rows[0] || null;
  }

  function localExplicitTransferWallets(text, wallets) {
    const m = String(text || '').match(/\b(?:dari|from)\s+(.+?)(?:\s+\b(?:ke|menuju|into)\s+(.+?))?(?:[,;.]|$)/iu);
    if (!m) return {};
    const fromText = String(m[1] || '').trim(), toText = String(m[2] || '').trim();
    const from = localWalletMention(fromText, wallets), to = toText ? localWalletMention(toText, wallets) : null;
    return { from_wallet_id: Number(from?.id || 0), to_wallet_id: Number(to?.id || 0), fromText, toText };
  }

  function dateFromText(text) {
    const m = String(text || '').match(/\b(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})\b/);
    if (m) return `${m[3]}-${String(m[2]).padStart(2, '0')}-${String(m[1]).padStart(2, '0')}`;
    return TODAY();
  }

  function spendingKind(text, type) {
    if (type !== 'expense') return 'once';
    const x = lower(text);
    if (/cicilan|angsuran|tagihan|sekali bayar|sekali saja|satu kali|non[- ]?rutin/.test(x)) return 'once';
    if (/berulang|bulanan|mingguan|harian|rutin|langganan/.test(x)) return 'recurring';
    return 'daily';
  }


  function normalizeScenarioMessage(value) {
    return normalizeMessage(value)
      .replace(/\bmkn\b/g, 'makan')
      .replace(/\bke depan(?:nya)?\b/g, 'ke depan')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function scenarioMoneyValue(raw, inheritedUnit = '') {
    let value = String(raw || '').trim();
    if (!value) return 0;
    if (inheritedUnit && !/(rb|ribu|k|jt|juta)\b/i.test(value) && !/^rp\b/i.test(value)) value += inheritedUnit;
    return money(parseAmount(value));
  }

  function scenarioMoneyItems(message) {
    const text = normalizeScenarioMessage(message);
    const clauses = text.split(/\s*(?:,|;|\+|\b(?:dan|serta|plus|ditambah)\b)\s*/u).filter(Boolean);
    const moneyToken = '(?:rp\\.?\\s*)?\\d+(?:[\\.,]\\d+)*(?:\\s*(?:rb|ribu|k|jt|juta))?';
    const range = new RegExp(`(${moneyToken})(?:\\s*(?:-|–|—|hingga|sampai|s/d|sd)\\s*(${moneyToken}))?`, 'iu');
    const items = [];
    let number = 1;
    for (const clause of clauses) {
      if (!/(?:per\s*hari|tiap\s*hari|setiap\s*hari|sehari|harian|perhari|\/\s*hari)/iu.test(clause)) continue;
      const m = clause.match(range); if (!m) continue;
      const rawLow = String(m[1] || '').trim(), rawHigh = String(m[2] || '').trim();
      const clearlyMoney = /(?:\brp\.?\s*\d|\d\s*(?:rb|ribu|k|jt|juta)\b|\d{1,3}(?:[\.,]\d{3})+)/iu.test(`${rawLow} ${rawHigh}`);
      if (!clearlyMoney) continue;
      const unitLow = (rawLow.match(/(?:rb|ribu|k|jt|juta)\b/i) || [])[0] || '';
      const unitHigh = (rawHigh.match(/(?:rb|ribu|k|jt|juta)\b/i) || [])[0] || '';
      let low = scenarioMoneyValue(rawLow, unitLow || unitHigh);
      let high = rawHigh ? scenarioMoneyValue(rawHigh, unitHigh || unitLow) : low;
      if (low <= 0 || high <= 0) continue;
      if (high < low) [low, high] = [high, low];
      let label = clause.slice(0, m.index).trim()
        .replace(/[^\p{L}\p{N}\s-]+/gu, ' ')
        .replace(/\b(?:kedepannya|ke depan|mulai sekarang|mulai besok|mulai hari ini|saya|aku|gue|gua|kira-kira|kira|kalau|jika|misalnya|misal|setiap hari|tiap hari|per hari|sehari|harian|perhari|dalam sehari|aman|cukup|bisa|akan|mau|ingin|untuk|buat|biaya|budget|anggaran|pengeluaran|keluar|habis|sekitar|kurang lebih)\b/giu, ' ')
        .replace(/\s+/g, ' ').trim();
      if (!label) {
        let suffix = clause.slice(m.index + m[0].length)
          .replace(/\b(?:per\s*hari|tiap\s*hari|setiap\s*hari|sehari|harian|perhari|hari|sampai|hingga|gajian|cukup|aman|bisa|tidak|ga|gak|nggak)\b/giu, ' ')
          .replace(/[^\p{L}\p{N}\s-]+/gu, ' ')
          .replace(/\s+/g, ' ').trim();
        if (suffix) label = suffix;
      }
      if (!label) label = `Kebutuhan harian ${number}`;
      label = label.toLocaleLowerCase('id-ID').replace(/(^|\s)(\p{L})/gu, (_, a, b) => a + b.toLocaleUpperCase('id-ID'));
      label = label.replace(/\bBbm\b/g, 'BBM');
      items.push({ label, low, high }); number += 1;
    }
    return items;
  }

  function paydayScenarioIntent(message) {
    const text = normalizeScenarioMessage(message);
    if (!/\b(?:gajian|gaji berikutnya|terima gaji|tanggal gaji)\b/u.test(text)) return false;
    if (!/(?:\bper\s*hari\b|\btiap\s*hari\b|\bsetiap\s*hari\b|\bsehari\b|\bharian\b|\bperhari\b|\/\s*hari\b)/u.test(text)) return false;
    if (!/(?:\b(?:cukup|aman|bertahan|nyampe|sampai|kuat|bisa|gak|ga|nggak|tidak|mampu|selamat|survive)\b|\?)/u.test(text)) return false;
    return scenarioMoneyItems(text).length > 0;
  }

  function paydayScenarioReply(data, message) {
    if (!paydayScenarioIntent(message)) return null;
    const items = scenarioMoneyItems(message);
    if (!items.length) return null;
    const p = prediction(data);
    const days = Math.max(0, Number(p.days_left || 0));
    if (days <= 0) return 'Hari ini sudah masuk tanggal gajian berdasarkan pengaturan aplikasi. Jika tanggal gajian berbeda, ubah dulu pengaturan gajian lalu coba lagi.';
    const dailyLow = items.reduce((n, x) => n + Number(x.low || 0), 0);
    const dailyHigh = items.reduce((n, x) => n + Number(x.high || 0), 0);
    const dailyNeedLow = dailyLow * days, dailyNeedHigh = dailyHigh * days;
    const balance = Math.max(0, Number(p.current_balance || 0));
    const bills = Math.max(0, Number(p.upcoming_bills_total || 0));
    const recurringExpense = Math.max(0, Number(p.recurring_expense || 0));
    const urgentReserve = Math.max(0, Number(p.credit_limit_urgent_reserve || 0));
    const fixed = bills + recurringExpense + urgentReserve;
    const needLow = fixed + dailyNeedLow, needHigh = fixed + dailyNeedHigh;
    const remainLow = balance - needLow, remainHigh = balance - needHigh;
    const paydayLabel = formatDate(p.payday_date);
    const rangeText = (lo, hi) => lo === hi ? formatRupiah(lo) : `${formatRupiah(lo)}–${formatRupiah(hi)}`;
    const lines = [];
    if (remainHigh >= 0) {
      lines.push(`Dengan skenario itu, saldo yang tersimpan di perangkat masih cukup sampai gajian ${paydayLabel}.`);
      if (remainHigh < 100000) lines.push('Namun ruang sisanya tipis, jadi kebutuhan tak terduga sebaiknya diperhitungkan.');
    } else if (remainLow >= 0) {
      lines.push(`Skenario tersebut masih bisa menutup kebutuhan sampai gajian ${paydayLabel} jika pengeluaran berada di batas bawah.`);
      lines.push(`Jika mendekati batas atas, dana berpotensi kurang sekitar ${formatRupiah(-remainHigh)} sebelum gajian.`);
    } else {
      lines.push(`Dengan saldo yang tersedia sekarang, skenario tersebut belum cukup sampai gajian ${paydayLabel}.`);
      lines.push(`Pada skenario paling hemat pun masih kurang sekitar ${formatRupiah(-remainLow)}.`);
    }
    lines.push('');
    lines.push('Simulasi:');
    lines.push(`• Saldo tersedia sekarang: ${formatRupiah(balance)}`);
    lines.push(`• Hari yang perlu dibiayai: ${days} hari`);
    items.forEach((item) => lines.push(`• ${item.label}: ${rangeText(item.low, item.high)}/hari`));
    lines.push(`• Total kebutuhan harian: ${rangeText(dailyLow, dailyHigh)}`);
    lines.push(`• Kebutuhan skenario sampai gajian: ${rangeText(dailyNeedLow, dailyNeedHigh)}`);
    if (fixed > 0) lines.push(`• Tagihan + pengeluaran berulang/cadangan kartu sebelum gajian: ${formatRupiah(fixed)}`);
    lines.push(`• Total kebutuhan skenario: ${rangeText(needLow, needHigh)}`);
    if (remainLow >= 0 && remainHigh >= 0) lines.push(`• Perkiraan sisa saat gajian: ${rangeText(remainHigh, remainLow)}`);
    else if (remainLow >= 0) { lines.push(`• Skenario hemat: sisa ${formatRupiah(remainLow)}`); lines.push(`• Skenario batas atas: kurang ${formatRupiah(-remainHigh)}`); }
    else { lines.push(`• Skenario hemat: kurang ${formatRupiah(-remainLow)}`); lines.push(`• Skenario batas atas: kurang ${formatRupiah(-remainHigh)}`); }
    if (Number(p.recurring_income || 0) > 0) lines.push(`• Pemasukan berulang ${formatRupiah(p.recurring_income)} belum dianggap sebagai uang tersedia sekarang.`);
    lines.push('• Ini hanya simulasi; tidak membuat transaksi baru.');
    return lines.join('\n');
  }

  function maxNegativeId(rows) {
    const ids = (rows || []).map((r) => Number(r?.id || 0)).filter((n) => n < 0);
    return ids.length ? Math.min(...ids) : 0;
  }

  async function getBundle() {
    if (!DB()) throw new Error('Penyimpanan offline tidak tersedia.');
    let full = await DB().getSnapshot('offline_full');
    const dashboard = await DB().getSnapshot('dashboard');
    const transactions = await DB().getSnapshot('all_transactions');
    if (!full && dashboard) {
      full = {
        saved_at: Date.now(),
        data: synthesizeData(dashboard, transactions || dashboard.transactions || []),
        state: clone(dashboard),
        summary: clone(dashboard.summary || {}),
        features: clone(dashboard.features || {}),
        account: clone(dashboard.account || {}),
        notification_center: { unread_count: Number(dashboard.notification_unread_count || 0), notifications: [] },
      };
    }
    if (!full) throw new Error('Belum ada data offline. Buka aplikasi sekali saat online agar data tersimpan di perangkat.');
    full.data = full.data || synthesizeData(full.state || dashboard || {}, transactions || []);
    full.data.learning = full.data.learning || clone(full.learning || full.state?.learning || {});
    full.learning = clone(full.data.learning || {});
    full.transaction_inbox = full.transaction_inbox || full.features?.transaction_inbox || { items: [], pending_count: 0, reconciliation: {} };
    full.data.meta = full.data.meta || {};
    full.data.meta.local_transaction_inbox = clone(full.transaction_inbox);
    return full;
  }

  function synthesizeData(state, transactions) {
    const f = state?.features || {};
    return {
      settings: {
        initial_balance: Number(state?.summary?.initial || 0),
        daily_budget: clone(state?.daily_budget_settings || {}),
        payday_day: Number(f.payday_day || 1),
        notifications: clone(f.notifications || {}),
      },
      transactions: clone(transactions || state?.transactions || []),
      chats: clone(state?.chats || []),
      meta: { revision: Number(state?.revision || 0) },
      wallets: clone(f.wallets || []),
      categories: clone(f.categories || []),
      monthly_budgets: clone(f.monthly_budgets || []),
      bills: clone(f.bills || []),
      recurring: clone(f.recurring || []),
      goals: clone(f.goals || []),
      pending_chat_confirmation: clone(f.pending_confirmation || null),
      audit_log: clone(f.history || []),
      learning: clone(state?.learning || {}),
    };
  }

  function walletById(data, id) {
    return (data.wallets || []).find((w) => Number(w.id) === Number(id)) || null;
  }
  function categoryExists(data, name, type) {
    const target = lower(name);
    return target === 'biaya administrasi bank' || (data.categories || []).some((c) => lower(c.name) === target && (!type || c.type === 'both' || c.type === type) && !c.archived);
  }

  function inboxData(data) {
    data.meta = data.meta || {};
    data.meta.local_transaction_inbox = data.meta.local_transaction_inbox || { version: 1, next_id: 1, items: [], reconciliation: { date: '', completed_at: '', last_prompt_at: '' } };
    data.meta.local_transaction_inbox.items = Array.isArray(data.meta.local_transaction_inbox.items) ? data.meta.local_transaction_inbox.items : [];
    return data.meta.local_transaction_inbox;
  }
  function addInboxDraft(data, p) {
    const box = inboxData(data), amount = money(p.amount || p.ocr_amount);
    if (amount <= 0) throw new Error('Nominal harus lebih dari nol.');
    const note = String(p.note || '').trim(); if (!note) throw new Error('Tulis keterangan singkat agar transaksi mudah dirapikan nanti.');
    const type = ['expense','income','transfer'].includes(String(p.type)) ? String(p.type) : 'expense';
    const item = { id: nextLocalId(data, 'inbox_local'), status: 'pending', type, amount, note, transaction_date: String(p.transaction_date || TODAY()), suggested: { type, amount, transaction_date: String(p.transaction_date || TODAY()), note, category: type === 'transfer' ? 'Transfer Antar Dompet' : categoryFor(note, type), wallet_id: Number(p.wallet_id || defaultWalletId(data)), spending_kind: type === 'expense' ? spendingKind(note, type) : 'once' }, source: 'quick_add', created_at: new Date().toISOString(), updated_at: new Date().toISOString(), offline_pending: true };
    if (p.photo instanceof Blob) item.attachment = { local_blob: p.photo, name: p.photo.name || 'receipt.jpg', mime: p.photo.type || 'image/jpeg' };
    if (p.ocr_text || p.ocr_amount) item.ocr = { text: String(p.ocr_text || ''), amount: money(p.ocr_amount), score: Number(p.ocr_score || 0), line: String(p.ocr_line || ''), confidence: Number(p.ocr_confidence || 0) };
    box.items.unshift(item); box.pending_count = box.items.filter((x) => x.status === 'pending').length; return item;
  }
  function defaultWalletId(data) {
    const w = (data.wallets || []).find((x) => !x.archived && String(x.type || '').toLowerCase() !== 'receivable');
    return Number(w?.id || 0);
  }

  function walletGrossBalances(data) {
    const out = {};
    (data.wallets || []).forEach((w) => { out[Number(w.id)] = lower(w.type) === 'credit_card' ? -money(w.opening_debt) : Number(w.initial_balance || 0); });
    (data.transactions || []).forEach((t) => {
      const amount = money(t.amount);
      if (!amount) return;
      if (t.type === 'income') out[Number(t.wallet_id || 0)] = Number(out[Number(t.wallet_id || 0)] || 0) + amount;
      else if (t.type === 'expense') out[Number(t.wallet_id || 0)] = Number(out[Number(t.wallet_id || 0)] || 0) - amount;
      else if (t.type === 'transfer') {
        out[Number(t.from_wallet_id || 0)] = Number(out[Number(t.from_wallet_id || 0)] || 0) - amount;
        out[Number(t.to_wallet_id || 0)] = Number(out[Number(t.to_wallet_id || 0)] || 0) + amount;
      }
    });
    return out;
  }

  function buildSummary(data) {
    let income = 0, expense = 0;
    (data.transactions || []).forEach((t) => {
      if (t.type === 'income') income += money(t.amount);
      else if (t.type === 'expense') expense += money(t.amount);
    });
    const gross = walletGrossBalances(data);
    let initial = 0, reserved = 0, minimum = 0, available = 0, grossNonCredit = 0, creditLimit = 0, creditDebt = 0, creditAvailable = 0;
    (data.wallets || []).forEach((w) => {
      if (w.archived) return;
      const wid = Number(w.id || 0), g = Number(gross[wid] || 0);
      if (lower(w.type) === 'credit_card') {
        const limit = money(w.credit_limit);
        const debt = Math.max(0, -g);
        creditLimit += limit; creditDebt += debt; creditAvailable += Math.max(0, limit - debt);
        return;
      }
      initial += money(w.initial_balance);
      reserved += money(w.reserved_balance);
      minimum += money(w.minimum_balance);
      grossNonCredit += g;
      available += Math.max(0, g - money(w.reserved_balance) - money(w.minimum_balance));
    });
    return {
      initial, income, expense, gross_balance: grossNonCredit,
      reserved, minimum_balance: minimum, protected_balance: reserved + minimum,
      available_balance: available, balance: available,
      credit_card_limit: creditLimit, credit_card_debt: creditDebt, credit_card_available_limit: creditAvailable,
    };
  }

  function walletSnapshot(data) {
    const gross = walletGrossBalances(data);
    return (data.wallets || []).filter((w) => !w.archived).map((w) => {
      const row = clone(w);
      const g = Number(gross[Number(w.id)] || 0);
      if (lower(w.type) === 'credit_card') {
        const limit = money(w.credit_limit), debt = Math.max(0, -g), available = Math.max(0, limit - debt);
        Object.assign(row, { balance: -debt, credit_limit: limit, credit_used: debt, outstanding_balance: debt, available_limit: available, available_balance: available, reserved_balance: 0, minimum_balance: 0, protected_balance: 0 });
      } else {
        const reserved = money(w.reserved_balance), minimum = money(w.minimum_balance), protectedBalance = reserved + minimum;
        Object.assign(row, { balance: g, reserved_balance: reserved, minimum_balance: minimum, protected_balance: protectedBalance, available_balance: Math.max(0, g - protectedBalance) });
      }
      return row;
    });
  }

  function daysInMonth(year, monthIndex) { return new Date(year, monthIndex + 1, 0).getDate(); }
  function creditCardDueDateForMonth(billingDay, year, monthIndex) {
    const day = Math.min(Math.max(1, Number(billingDay || 1)), daysInMonth(year, monthIndex));
    return `${year}-${String(monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
  }
  function creditCardBillDueDateForTransaction(billingDay, transactionDate) {
    let date = /^\d{4}-\d{2}-\d{2}$/.test(String(transactionDate || '')) ? String(transactionDate) : TODAY();
    const base = new Date(`${date}T00:00:00`);
    let due = creditCardDueDateForMonth(billingDay, base.getFullYear(), base.getMonth());
    if (date > due) due = creditCardDueDateForMonth(billingDay, base.getFullYear(), base.getMonth() + 1);
    return due;
  }
  function syncCreditCardBills(data) {
    data.bills = Array.isArray(data.bills) ? data.bills : [];
    data.meta = data.meta || {};
    const cards = {};
    (data.wallets || []).filter((w) => !w.archived && lower(w.type) === 'credit_card' && Number(w.billing_day || 0) > 0).forEach((w) => { cards[Number(w.id)] = w; });
    if (!Object.keys(cards).length) return false;
    const manual = [], existing = {};
    data.bills.forEach((bill) => {
      if (bill.auto_generated && bill.bill_type === 'credit_card') {
        existing[`${Number(bill.credit_card_wallet_id || 0)}|${String(bill.due_date || '')}`] = bill;
      } else manual.push(bill);
    });
    const groups = {};
    const addUsage = (walletId, date, amount, txId, kind='transaction') => {
      if (!cards[walletId] || !amount) return;
      const due = creditCardBillDueDateForTransaction(cards[walletId].billing_day, date);
      const key = `${walletId}|${due}`;
      groups[key] = groups[key] || { wallet_id: walletId, due_date: due, amount: 0, transaction_ids: [], opening_debt_component: 0 };
      groups[key].amount += Number(amount || 0);
      if (Number(txId || 0) > 0) groups[key].transaction_ids.push(Number(txId));
      if (kind === 'opening_debt') groups[key].opening_debt_component += Math.max(0, Number(amount || 0));
    };
    Object.values(cards).forEach((card) => {
      const opening = money(card.opening_debt);
      if (opening > 0) {
        const created = /^\d{4}-\d{2}-\d{2}$/.test(String(card.created_at || '').slice(0,10)) ? String(card.created_at).slice(0,10) : TODAY();
        addUsage(Number(card.id), created, opening, 0, 'opening_debt');
      }
    });
    (data.transactions || []).forEach((tx) => {
      const amount = money(tx.amount); if (!amount) return;
      const date = String(tx.transaction_date || TODAY()), type = String(tx.type || '');
      if (type === 'expense') addUsage(Number(tx.wallet_id || 0), date, amount, tx.id);
      else if (type === 'income') addUsage(Number(tx.wallet_id || 0), date, -amount, tx.id);
      else if (type === 'transfer') addUsage(Number(tx.from_wallet_id || 0), date, amount, tx.id);
    });
    const defaultPaymentWallet = (data.wallets || []).find((w) => !w.archived && lower(w.type) !== 'credit_card' && lower(w.type) !== 'receivable');
    const autoBills = [];
    Object.values(groups).sort((a,b)=>String(a.due_date).localeCompare(String(b.due_date)) || a.wallet_id-b.wallet_id).forEach((group) => {
      const amount = Math.max(0, Math.round(group.amount)); if (!amount) return;
      const card = cards[group.wallet_id]; if (!card) return;
      const key = `${group.wallet_id}|${group.due_date}`;
      const old = existing[key] || {};
      const dueDate = new Date(`${group.due_date}T00:00:00`);
      const prevMonth = new Date(dueDate.getFullYear(), dueDate.getMonth() - 1, 1);
      const prevDue = new Date(creditCardDueDateForMonth(card.billing_day, prevMonth.getFullYear(), prevMonth.getMonth()) + 'T00:00:00');
      prevDue.setDate(prevDue.getDate() + 1);
      const paymentWallet = walletById(data, Number(old.wallet_id || 0)) && lower(walletById(data, Number(old.wallet_id || 0)).type) !== 'credit_card' ? Number(old.wallet_id) : Number(defaultPaymentWallet?.id || 0);
      autoBills.push({
        ...clone(old), id: Number(old.id || nextLocalId(data, 'bills')), name: `Tagihan ${String(card.name || 'Kartu Kredit').slice(0,60)}`, amount,
        due_date: group.due_date, due_day: Number(String(group.due_date).slice(8,10)), schedule_type:'credit_card_cycle', category:'Kartu Kredit', wallet_id:paymentWallet,
        reminder_days:2, active:true, payments: old.payments && typeof old.payments === 'object' ? clone(old.payments) : {}, created_at: old.created_at || new Date().toISOString(),
        auto_generated:true, bill_type:'credit_card', credit_card_wallet_id:Number(card.id), credit_card_name:String(card.name || 'Kartu Kredit'),
        cycle_start:dateKey(prevDue), cycle_end:group.due_date, transaction_ids:[...new Set(group.transaction_ids.map(Number))],
        transaction_count:[...new Set(group.transaction_ids.map(Number))].length, opening_debt_component:group.opening_debt_component,
      });
    });
    // Allocate card payments to oldest bill, respecting explicit bill_id first.
    const billById = {}; const billsByCard = {};
    autoBills.forEach((b,i)=>{billById[Number(b.id)] = i; (billsByCard[Number(b.credit_card_wallet_id)] ||= []).push(i);});
    const paidTotals = autoBills.map((b)=>Object.values(b.payments || {}).reduce((n,p)=>n+money(p?.amount),0));
    const paymentTxs = (data.transactions || []).filter((tx)=>tx.type==='transfer' && cards[Number(tx.to_wallet_id||0)] && money(tx.amount)>0).sort((a,b)=>String(a.transaction_date||'').localeCompare(String(b.transaction_date||'')) || Number(a.id)-Number(b.id));
    paymentTxs.forEach((tx)=>{
      let remaining=money(tx.amount); const cardId=Number(tx.to_wallet_id||0), targets=[]; const preferred=Number(tx.bill_id||0);
      if(preferred && billById[preferred] !== undefined && Number(autoBills[billById[preferred]].credit_card_wallet_id)===cardId) targets.push(billById[preferred]);
      (billsByCard[cardId] || []).forEach((i)=>{if(!targets.includes(i))targets.push(i);});
      targets.forEach((i)=>{if(remaining<=0)return; const need=Math.max(0,money(autoBills[i].amount)-money(paidTotals[i])); if(!need)return; const take=Math.min(need,remaining); autoBills[i].payments[`tx:${Number(tx.id)}`]={date:String(tx.transaction_date||TODAY()),transaction_id:Number(tx.id),amount:take,from_wallet_id:Number(tx.from_wallet_id||0)}; if(Number(tx.from_wallet_id||0)>0)autoBills[i].wallet_id=Number(tx.from_wallet_id); paidTotals[i]+=take; remaining-=take;});
    });
    const merged = [...manual, ...autoBills];
    const before = JSON.stringify(data.bills);
    data.bills = merged;
    return before !== JSON.stringify(merged);
  }

  function billStatus(data, now = TODAY()) {
    const today = new Date(`${now}T00:00:00`);
    const month = now.slice(0, 7);
    const paymentEntries = (bill) => bill.payments && typeof bill.payments === 'object'
      ? (Array.isArray(bill.payments) ? bill.payments : Object.values(bill.payments)).filter(Boolean)
      : [];
    return (data.bills || []).filter((b) => b.active !== false).map((b) => {
      const row = clone(b); let due = String(b.due_date || '');
      if (!/^\d{4}-\d{2}-\d{2}$/.test(due)) {
        const year = Number(now.slice(0, 4)), monthIndex = Number(now.slice(5, 7)) - 1;
        const days = new Date(year, monthIndex + 1, 0).getDate();
        const d = Math.min(Math.max(1, Number(b.due_day || 1)), days);
        due = `${now.slice(0, 8)}${String(d).padStart(2, '0')}`;
      }
      const dueDate = new Date(`${due}T00:00:00`);
      const diff = Math.round((dueDate.getTime() - today.getTime()) / 86400000);
      const paymentEntries = Object.entries(b.payments || {}).filter(([, p]) => p && typeof p === 'object');
      const payments = paymentEntries.map(([, p]) => p);
      const autoCc = Boolean(b.auto_generated && b.bill_type === 'credit_card');
      let paymentTotal = 0, paid = false;
      if (autoCc) {
        paymentTotal = payments.reduce((sum, p) => sum + money(p.amount), 0);
        paid = paymentTotal >= money(b.amount) && money(b.amount) > 0;
      } else {
        const dueKey = `due:${due}`;
        const exact = paymentEntries.some(([key]) => key === dueKey);
        const period = payments.some((p) => String(p.date || '').startsWith(month));
        paid = Boolean(exact || (period && !/^\d{4}-\d{2}-\d{2}$/.test(String(b.due_date || ''))));
        paymentTotal = paid ? money(b.amount) : 0;
      }
      const remaining = Math.max(0, money(b.amount) - paymentTotal);
      row.due_date = due; row.days_left = diff; row.paid = paid; row.payment_total = paymentTotal; row.remaining_amount = remaining;
      row.status = paid ? 'paid' : (diff < 0 ? 'overdue' : (diff <= Number(b.reminder_days || 3) ? 'due_soon' : 'upcoming'));
      row.period = due.slice(0, 7);
      return row;
    }).sort((a, b) => String(a.due_date).localeCompare(String(b.due_date)));
  }

  function nextRecurringDate(date, frequency, interval) {
    const d = new Date(`${date}T00:00:00`);
    const n = Math.max(1, Number(interval || 1));
    if (frequency === 'daily') d.setDate(d.getDate() + n);
    else if (frequency === 'weekly') d.setDate(d.getDate() + (n * 7));
    else d.setMonth(d.getMonth() + n);
    return dateKey(d);
  }

  function processRecurring(data) {
    const today = TODAY();
    const created = [];
    data.recurring = Array.isArray(data.recurring) ? data.recurring : [];
    let nextId = maxNegativeId(data.transactions);
    data.recurring.forEach((r) => {
      if (!r.active) return;
      const beforeCount = created.length;
      let next = String(r.next_run || today), guard = 0;
      while (next <= today && guard++ < 24) {
        nextId -= 1;
        created.push({ id: nextId, type: String(r.type || 'expense'), amount: money(r.amount), transaction_date: next, note: String(r.name || ''), category: String(r.category || 'Lainnya'), wallet_id: Number(r.wallet_id || defaultWalletId(data)), spending_kind: 'recurring', source: 'recurring', created_at: new Date().toISOString(), offline_local: true, offline_pending: true });
        next = nextRecurringDate(next, r.frequency, r.interval);
      }
      if (created.length > beforeCount) r.next_run = next;
    });
    if (created.length) data.transactions.push(...created);
    return created;
  }

  function analytics(data, month = '') {
    const m = /^\d{4}-\d{2}$/.test(month) ? month : TODAY().slice(0, 7);
    let income = 0, expense = 0, previousExpense = 0;
    const byCat = {}, daily = {};
    const prevDate = new Date(`${m}-01T00:00:00`); prevDate.setMonth(prevDate.getMonth() - 1); const prev = `${prevDate.getFullYear()}-${String(prevDate.getMonth() + 1).padStart(2, '0')}`;
    (data.transactions || []).forEach((t) => {
      const date = String(t.transaction_date || ''), amount = money(t.amount);
      if (date.startsWith(m)) {
        if (t.type === 'income') income += amount;
        if (t.type === 'expense') { expense += amount; const cat = String(t.category || 'Lainnya'); byCat[cat] = (byCat[cat] || 0) + amount; daily[date] = (daily[date] || 0) + amount; }
      } else if (date.startsWith(prev) && t.type === 'expense') previousExpense += amount;
    });
    const categories = Object.entries(byCat).sort((a, b) => b[1] - a[1]).map(([category, total]) => ({ category, total, percent: expense ? Math.round((total / expense) * 1000) / 10 : 0 }));
    const days = m === TODAY().slice(0, 7) ? Number(TODAY().slice(8, 10)) : new Date(Number(m.slice(0, 4)), Number(m.slice(5, 7)), 0).getDate();
    const avg = days ? Math.round(expense / days) : 0;
    return { month: m, income, expense, net: income - expense, previous_expense: previousExpense, expense_change_percent: previousExpense ? Math.round(((expense - previousExpense) / previousExpense) * 1000) / 10 : null, average_daily_expense: avg, top_category: categories[0] || null, categories, daily: Object.entries(daily).sort().map(([date, total]) => ({ date, total })), monthly_budgets: budgetStatus(data, m) };
  }

  function budgetStatus(data, month = '') {
    const m = /^\d{4}-\d{2}$/.test(month) ? month : TODAY().slice(0, 7);
    const used = {};
    (data.transactions || []).forEach((t) => {
      if (t.type !== 'expense' || !String(t.transaction_date || '').startsWith(m)) return;
      const name = lower(t.category || 'Lainnya');
      used[name] = (used[name] || 0) + money(t.amount);
    });
    return (data.monthly_budgets || []).filter((b) => String(b.month || '') === m && b.enabled !== false).map((b) => {
      const cat = (data.categories || []).find((c) => Number(c.id) === Number(b.category_id));
      const categoryName = String(cat?.name || b.category || b.category_id || 'Lainnya');
      const usedAmount = Number(used[lower(categoryName)] || 0), limit = money(b.limit), remain = Math.max(0, limit - usedAmount);
      const percent = limit ? Math.round((usedAmount / limit) * 100) : 0;
      return { ...clone(b), category_id: Number(b.category_id || cat?.id || 0), category: categoryName, category_name: categoryName, icon: cat?.icon || b.icon || '', used: usedAmount, spent: usedAmount, remaining: remain, percent, over: Math.max(0, usedAmount - limit), status: usedAmount > limit ? 'exceeded' : (percent >= 80 ? 'warning' : 'safe') };
    });
  }

  function forecastBills(data, today = TODAY(), through = today) {
    const rows = [];
    const todayDate = new Date(`${today}T00:00:00`);
    const throughDate = new Date(`${through}T00:00:00`);
    const daysInMonth = (year, monthIndex) => new Date(year, monthIndex + 1, 0).getDate();
    const paymentTotal = (bill) => Object.values(bill.payments || {}).reduce((n, p) => n + money(p?.amount), 0);
    const makeRow = (bill, due, paymentKey, scheduleType) => {
      const row = clone(bill); const autoCc = Boolean(bill.auto_generated && bill.bill_type === 'credit_card');
      let paid = false, paidAmount = 0, remaining = money(bill.amount);
      if (autoCc) {
        paidAmount = paymentTotal(bill); remaining = Math.max(0, money(bill.amount) - paidAmount); paid = remaining <= 0 && money(bill.amount) > 0;
      } else {
        paid = Boolean(bill.payments && bill.payments[paymentKey]);
        paidAmount = paid ? money(bill.amount) : 0; remaining = paid ? 0 : money(bill.amount);
      }
      const diff = Math.round((new Date(`${due}T00:00:00`).getTime() - todayDate.getTime()) / 86400000);
      row.due_date = due; row.days_left = diff; row.paid = paid; row.payment_total = paidAmount; row.remaining_amount = remaining;
      row.status = paid ? 'paid' : (diff < 0 ? 'overdue' : (diff <= Number(bill.reminder_days || 3) ? 'due_soon' : 'upcoming'));
      row.schedule_type = autoCc ? 'credit_card_cycle' : scheduleType; row.period = scheduleType === 'monthly_legacy' ? due.slice(0, 7) : due;
      return row;
    };
    (data.bills || []).filter((b) => b.active !== false).forEach((b) => {
      const exact = /^\d{4}-\d{2}-\d{2}$/.test(String(b.due_date || ''));
      if (exact) {
        const due = String(b.due_date); const d = new Date(`${due}T00:00:00`);
        if (d <= throughDate) {
          rows.push(makeRow(b, due, `due:${due}`, 'once'));
        }
        return;
      }
      let cursor = new Date(`${today.slice(0, 7)}-01T00:00:00`);
      while (cursor <= throughDate) {
        const year = cursor.getFullYear(), month = cursor.getMonth();
        const day = Math.min(Math.max(1, Number(b.due_day || 1)), daysInMonth(year, month));
        const due = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        if (due <= through) rows.push(makeRow(b, due, `${due.slice(0, 7)}`, 'monthly_legacy'));
        cursor = new Date(year, month + 1, 1);
      }
    });
    const seen = new Set();
    return rows.filter((row) => {
      const key = `${row.id || ''}:${row.due_date}`;
      if (seen.has(key)) return false; seen.add(key); return true;
    }).sort((a, b) => String(a.due_date).localeCompare(String(b.due_date)));
  }

  function upcomingRecurringUntil(data, through) {
    let income = 0, expense = 0;
    (data.recurring || []).forEach((r) => {
      if (!r.active) return;
      let next = String(r.next_run || through), guard = 0;
      while (next <= through && guard++ < 24) {
        if (String(r.type) === 'income') income += money(r.amount); else expense += money(r.amount);
        next = nextRecurringDate(next, r.frequency, r.interval);
      }
    });
    return { income, expense };
  }

  function creditCardUrgency(data, billRows) {
    let totalLimit = 0, totalUsed = 0, totalAvailable = 0, urgentReserve = 0, level = 'none';
    const billByCard = {};
    billRows.filter((b) => b.auto_generated && b.bill_type === 'credit_card' && !b.paid).forEach((b) => {
      const id = Number(b.credit_card_wallet_id || 0); if (!id) return;
      billByCard[id] = (billByCard[id] || 0) + money(b.remaining_amount ?? b.amount);
    });
    const cards = [];
    walletSnapshot(data).filter((w) => lower(w.type) === 'credit_card').forEach((w) => {
      const limit = money(w.credit_limit), used = money(w.credit_used ?? w.outstanding_balance), available = Math.max(0, Math.min(limit, money(w.available_limit ?? limit - used)));
      if (limit <= 0) return;
      const availPct = Math.round((available / limit) * 1000) / 10, utilPct = Math.round((used / limit) * 1000) / 10;
      const target = Math.ceil(limit * 0.20), restoreNeeded = Math.max(0, target - available), covered = Math.max(0, money(billByCard[Number(w.id)])), extra = Math.max(0, restoreNeeded - covered);
      const cardLevel = availPct <= 10 ? 'critical' : (availPct <= 20 ? 'urgent' : (availPct <= 30 ? 'watch' : 'none'));
      if (cardLevel === 'critical') level = 'critical'; else if (cardLevel === 'urgent' && level !== 'critical') level = 'urgent'; else if (cardLevel === 'watch' && level === 'none') level = 'watch';
      totalLimit += limit; totalUsed += used; totalAvailable += available; urgentReserve += extra;
      cards.push({ wallet_id: Number(w.id), name: String(w.name || 'Kartu Kredit'), limit, used, available_limit: available, utilization_percent: utilPct, available_percent: availPct, urgency_level: cardLevel, target_available_limit: target, restore_needed: restoreNeeded, due_before_payday: covered, urgent_extra_reserve: extra });
    });
    cards.sort((a, b) => ({critical:3,urgent:2,watch:1,none:0}[b.urgency_level] || 0) - ({critical:3,urgent:2,watch:1,none:0}[a.urgency_level] || 0));
    const dueBeforePaydayTotal = billRows.filter((b) => b.auto_generated && b.bill_type === 'credit_card' && !b.paid).reduce((n, b) => n + money(b.remaining_amount ?? b.amount), 0);
    return { level, cards, card_count: cards.length, urgent_count: cards.filter((c) => c.urgency_level === 'urgent' || c.urgency_level === 'critical').length, total_limit: totalLimit, total_used: totalUsed, total_available: totalAvailable, utilization_percent: totalLimit ? Math.round((totalUsed / totalLimit) * 1000) / 10 : 0, due_before_payday_total: dueBeforePaydayTotal, urgent_reserve: urgentReserve, target_available_percent: 20 };
  }

  function prediction(data) {
    const sum = buildSummary(data), today = TODAY();
    const recentCut = new Date(`${today}T00:00:00`); recentCut.setDate(recentCut.getDate() - 29);
    let total = 0, count = 0, todaySpent = 0, first = '', excluded = 0;
    (data.transactions || []).forEach((t) => {
      const kind = ['daily', 'once', 'recurring'].includes(String(t.spending_kind || '')) ? String(t.spending_kind) : spendingKind(t.note || '', 'expense');
      if (t.type !== 'expense' || kind !== 'daily') { if (t.type === 'expense' && String(t.transaction_date || '') >= dateKey(recentCut)) excluded += money(t.amount); return; }
      const d = String(t.transaction_date || ''); if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) return;
      const dd = new Date(`${d}T00:00:00`); if (dd < recentCut || d > today) return;
      total += money(t.amount); count += 1; if (!first || d < first) first = d; if (d === today) todaySpent += money(t.amount);
    });
    const historyDays = first ? Math.max(1, Math.round((new Date(`${today}T00:00:00`) - new Date(`${first}T00:00:00`)) / 86400000) + 1) : 0;
    const avg = historyDays ? Math.round(total / historyDays) : 0;
    const paydayDay = Math.max(1, Math.min(31, Number(data.settings?.payday_day || 1)));
    let payday = new Date(`${today.slice(0, 7)}-01T00:00:00`); payday.setDate(Math.min(paydayDay, new Date(payday.getFullYear(), payday.getMonth() + 1, 0).getDate()));
    if (payday <= new Date(`${today}T00:00:00`)) { payday = new Date(payday.getFullYear(), payday.getMonth() + 1, 1); payday.setDate(Math.min(paydayDay, new Date(payday.getFullYear(), payday.getMonth() + 1, 0).getDate())); }
    const paydayDate = dateKey(payday), daysLeft = Math.max(0, Math.round((payday - new Date(`${today}T00:00:00`)) / 86400000));
    const remainingToday = daysLeft > 0 ? Math.max(0, avg - todaySpent) : 0;
    const dailyEstimate = avg * Math.max(0, daysLeft - 1) + remainingToday;
    const beforePayday = dateKey(new Date(payday.getTime() - 86400000));
    const bills = forecastBills(data, today, beforePayday).filter((b) => !b.paid);
    const billTotal = bills.reduce((n, b) => n + money(b.remaining_amount ?? b.amount), 0);
    const recurring = upcomingRecurringUntil(data, beforePayday);
    const credit = creditCardUrgency(data, bills);
    const predBeforeCreditUrgency = Number(sum.balance || 0) - dailyEstimate - billTotal + recurring.income - recurring.expense;
    const predicted = predBeforeCreditUrgency - money(credit.urgent_reserve);
    const sufficient = count > 0 && historyDays >= 7;
    const withoutIncome = predicted - recurring.income;
    const creditUrgent = ['urgent', 'critical'].includes(credit.level);
    const status = predicted < 0 ? 'risk' : (!sufficient ? 'unknown' : (withoutIncome < 0 ? 'conditional' : (creditUrgent ? 'conditional' : (predicted < Math.max(100000, avg * 3) ? 'tight' : 'safe'))));
    return { history_days: historyDays, daily_expense_count: count, daily_history_total: total, excluded_history_total: excluded, daily_spent_today: todaySpent, average_daily_expense: avg, estimated_daily_spend: dailyEstimate, remaining_daily_spend_today: remainingToday, payday_date: paydayDate, days_left: daysLeft, current_balance: Number(sum.balance || 0), gross_balance: Number(sum.gross_balance || 0), reserved_balance: Number(sum.reserved || 0), minimum_balance: Number(sum.minimum_balance || 0), protected_balance: Number(sum.protected_balance || 0), upcoming_bills: bills, upcoming_bills_total: billTotal, recurring_income: recurring.income, recurring_expense: recurring.expense, predicted_balance_before_credit_urgency: predBeforeCreditUrgency, credit_limit_urgent_reserve: money(credit.urgent_reserve), credit_urgency_level: credit.level, credit_urgent_count: credit.urgent_count, credit_total_limit: credit.total_limit, credit_total_used: credit.total_used, credit_total_available: credit.total_available, credit_utilization_percent: credit.utilization_percent, credit_due_before_payday_total: credit.due_before_payday_total, credit_target_available_percent: credit.target_available_percent, credit_cards_urgency: credit.cards, predicted_balance: predicted, status, history_sufficient: sufficient };
  }

  function notificationSnapshot(bundle) {
    return clone(bundle.notification_center || { unread_count: 0, notifications: [] });
  }

  function buildFeatures(data, bundle) {
    return {
      wallets: walletSnapshot(data),
      receivables: buildReceivables(data),
      categories: clone(data.categories || []),
      monthly_budgets: budgetStatus(data),
      bills: billStatus(data),
      recurring: clone(data.recurring || []),
      goals: (data.goals || []).map((g) => ({ ...clone(g), percent: g.target_amount ? Math.min(100, Math.round(Number(g.current_amount || 0) / Number(g.target_amount) * 100)) : 0, remaining: Math.max(0, money(g.target_amount) - money(g.current_amount)), recommended_daily: 0 })),
      analytics: analytics(data),
      prediction: prediction(data),
      payday_day: Number(data.settings?.payday_day || 1),
      notifications: clone(data.settings?.notifications || {}),
      credit_card_reminders: forecastBills(data, TODAY(), dateKey(new Date(new Date(`${TODAY().slice(0, 7)}-01T00:00:00`).getTime() + 40 * 86400000))).filter((b) => Number(b.credit_card_wallet_id || 0) > 0 && !b.paid && Number(b.days_left || 0) >= 0 && Number(b.remaining_amount || b.amount || 0) > 0).map((b) => ({
        wallet_id: Number(b.credit_card_wallet_id || 0), bill_id: Number(b.id || 0), name: String(b.credit_card_name || b.name || 'Kartu Kredit'),
        billing_day: Number(String(b.due_date || '').slice(8, 10) || b.due_day || 0), due_date: String(b.due_date || ''), days_until: Number(b.days_left || 0),
        amount: money(b.remaining_amount ?? b.amount), cycle_start: String(b.cycle_start || ''), cycle_end: String(b.cycle_end || ''),
      })),
      transaction_inbox: clone(data.meta?.local_transaction_inbox || bundle.transaction_inbox || bundle.features?.transaction_inbox || { items: [], pending_count: 0, reconciliation: {} }),
      pending_confirmation: clone(data.pending_chat_confirmation || null),
      history: (data.audit_log || []).slice(-30).reverse(),
    };
  }

  function receivableBorrowerKey(name, fallback = '') {
    const raw = String(name || fallback || '').trim().toLocaleLowerCase('id-ID');
    return raw.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, ' ').trim();
  }

  function buildReceivables(data) {
    const walletNames = {};
    (data.wallets || []).forEach((w) => { walletNames[Number(w.id || 0)] = String(w.name || 'Dompet'); });
    const people = {};
    const entries = [];
    const ensurePerson = (key, name) => {
      const k = String(key || receivableBorrowerKey(name, 'tanpa nama')) || 'tanpa nama';
      if (!people[k]) people[k] = { key: k, name: String(name || 'Tanpa Nama'), lent: 0, interest: 0, repaid: 0, outstanding: 0, entries: 0, last_date: '', last_id: 0, next_due_date: '', overdue: false, loans: [] };
      if (String(name || '').trim()) people[k].name = String(name).trim();
      return people[k];
    };

    (data.transactions || []).forEach((tx) => {
      const source = String(tx.source || '');
      if (source !== 'receivable_lend' && source !== 'receivable_repayment') return;
      const action = source === 'receivable_repayment' ? 'repayment' : 'lend';
      const name = String(tx.receivable_borrower_name || 'Tanpa Nama').trim() || 'Tanpa Nama';
      const key = String(tx.receivable_borrower_key || receivableBorrowerKey(name));
      const amount = money(tx.amount);
      const p = ensurePerson(key, name);
      p.entries += 1;
      const date = String(tx.transaction_date || '');
      if (date > p.last_date || (date === p.last_date && Number(tx.id || 0) > p.last_id)) {
        p.last_date = date; p.last_id = Number(tx.id || 0);
      }
      if (action === 'lend') {
        p.lent += amount;
        p.loans.push({
          id: Number(tx.id || 0),
          borrower_key: key,
          borrower_name: name,
          principal_amount: amount,
          interest_amount: 0,
          amount,
          repaid: 0,
          remaining: amount,
          transaction_date: date,
          due_date: String(tx.receivable_due_date || ''),
          wallet_id: Number(tx.from_wallet_id || 0),
          wallet_name: walletNames[Number(tx.from_wallet_id || 0)] || 'Dompet',
          note: String(tx.note || ''),
          attachment: tx.attachment || null,
        });
      } else {
        p.repaid += amount;
      }
      const walletId = action === 'lend' ? Number(tx.from_wallet_id || 0) : Number(tx.to_wallet_id || 0);
      entries.push({
        id: Number(tx.id || 0), sort_id: Number(tx.id || 0), action, borrower_key: key, borrower_name: name,
        amount, transaction_date: date, due_date: String(tx.receivable_due_date || ''), note: String(tx.note || ''),
        wallet_id: walletId, wallet_name: walletNames[walletId] || 'Dompet', attachment: tx.attachment || null,
        created_at: String(tx.created_at || ''), receivable_lend_id: Number(tx.receivable_lend_id || 0), allocations: [],
      });
    });

    const interestRows = Array.isArray(data.meta?.receivable_interest_entries) ? data.meta.receivable_interest_entries : [];
    interestRows.forEach((row) => {
      const amount = money(row.amount); const id = Number(row.id || 0);
      const name = String(row.borrower_name || 'Tanpa Nama').trim() || 'Tanpa Nama';
      const key = String(row.borrower_key || receivableBorrowerKey(name));
      if (!id || amount <= 0) return;
      const p = ensurePerson(key, name); p.interest += amount; p.entries += 1;
      const date = String(row.transaction_date || '');
      if (date > p.last_date) p.last_date = date;
      entries.push({
        id: -id, interest_id: id, sort_id: 1000000000 + id, action: 'interest', borrower_key: key, borrower_name: name,
        amount, transaction_date: date, due_date: '', note: String(row.note || ''), wallet_id: 0,
        wallet_name: 'Tidak memengaruhi saldo', attachment: null, created_at: String(row.created_at || ''),
        receivable_lend_id: Number(row.lend_id || 0), interest_rate: Number(row.rate_percent || 0), interest_base: money(row.base_amount), allocations: [],
      });
    });

    const loansByPerson = {};
    Object.values(people).forEach((p) => {
      loansByPerson[p.key] = {};
      p.loans.forEach((loan) => { loansByPerson[p.key][Number(loan.id)] = loan; });
    });

    // Attach explicit interest to its loan when the target is still known.
    entries.filter((e) => e.action === 'interest').forEach((e) => {
      const loanId = Number(e.receivable_lend_id || 0), map = loansByPerson[e.borrower_key] || {};
      if (loanId > 0 && map[loanId]) {
        map[loanId].interest_amount += e.amount;
        map[loanId].amount += e.amount;
        map[loanId].remaining += e.amount;
        e.wallet_id = map[loanId].wallet_id;
        e.wallet_name = `Tidak memengaruhi saldo · terkait ${map[loanId].wallet_name || 'Dompet'}`;
      }
    });

    Object.values(loansByPerson).forEach((map) => {
      const loans = Object.values(map).sort((a, b) => String(a.transaction_date).localeCompare(String(b.transaction_date)) || Number(a.id) - Number(b.id));
      loans.forEach((loan) => { loan.repaid = 0; loan.remaining = money(loan.amount); });
    });

    // Allocate repayment target first, then FIFO across the borrower's active loans.
    const repayments = entries.filter((e) => e.action === 'repayment').sort((a, b) => String(a.transaction_date).localeCompare(String(b.transaction_date)) || Number(a.sort_id) - Number(b.sort_id));
    repayments.forEach((entry) => {
      let remaining = money(entry.amount); const map = loansByPerson[entry.borrower_key] || {};
      const target = Number(entry.receivable_lend_id || 0);
      if (remaining > 0 && target > 0 && map[target] && map[target].remaining > 0) {
        const take = Math.min(remaining, map[target].remaining);
        map[target].repaid += take; map[target].remaining -= take; remaining -= take;
        entry.allocations.push({ lend_id: target, amount: take });
      }
      if (remaining > 0) {
        for (const loan of Object.values(map).sort((a, b) => String(a.transaction_date).localeCompare(String(b.transaction_date)) || Number(a.id) - Number(b.id))) {
          if (remaining <= 0) break;
          const available = money(loan.remaining); if (available <= 0) continue;
          const take = Math.min(remaining, available);
          loan.repaid += take; loan.remaining -= take; remaining -= take;
          entry.allocations.push({ lend_id: Number(loan.id), amount: take });
        }
      }
      const first = entry.allocations[0];
      if (first && map[first.lend_id]) entry.allocation_label = `Hutang ${formatDate(map[first.lend_id].transaction_date)} · total ${formatRupiah(map[first.lend_id].amount)}`;
      else entry.allocation_label = 'Akumulasi hutang (FIFO)';
    });

    const today = TODAY();
    Object.values(people).forEach((p) => {
      const loans = Object.values(loansByPerson[p.key] || {});
      p.loans = loans;
      p.outstanding = Math.max(0, p.lent + p.interest - p.repaid);
      const due = loans.filter((loan) => money(loan.remaining) > 0 && /^\d{4}-\d{2}-\d{2}$/.test(String(loan.due_date || ''))).map((loan) => String(loan.due_date)).sort();
      p.next_due_date = due[0] || '';
      p.overdue = Boolean(p.next_due_date && p.next_due_date < today);
    });

    const list = Object.values(people).sort((a, b) => Number(b.outstanding) - Number(a.outstanding) || lower(a.name).localeCompare(lower(b.name)));
    entries.sort((a, b) => String(b.transaction_date).localeCompare(String(a.transaction_date)) || Number(b.sort_id || b.id) - Number(a.sort_id || a.id));
    return {
      people: list,
      entries,
      total_lent: list.reduce((n, p) => n + money(p.lent), 0),
      total_repaid: list.reduce((n, p) => n + money(p.repaid), 0),
      total_interest: list.reduce((n, p) => n + money(p.interest), 0),
      total_outstanding: list.reduce((n, p) => n + money(p.outstanding), 0),
    };
  }

  function ensureMeta(data) {
    data.meta = data.meta || {};
    data.meta.local_sequence = Number(data.meta.local_sequence || 0);
    return data.meta;
  }
  function nextLocalId(data, collection) {
    const rows = Array.isArray(data[collection]) ? data[collection] : (collection === 'inbox_local' ? (data.meta?.local_transaction_inbox?.items || []) : []);
    const n = Math.min(-1, maxNegativeId(rows) - 1);
    ensureMeta(data).local_sequence += 1;
    return n;
  }

  function findTx(data, id) { return (data.transactions || []).find((t) => String(t.id) === String(id)); }
  function addTx(data, tx) { data.transactions = Array.isArray(data.transactions) ? data.transactions : []; data.transactions.push(tx); return tx; }

  function makeLocalAttachment(blob, name = 'receipt.jpg') {
    if (!(blob instanceof Blob)) return null;
    return { local_blob: blob, local: true, local_id: `att_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 10)}`, name: String(name || blob.name || 'receipt.jpg'), mime: String(blob.type || 'image/jpeg'), size: Number(blob.size || 0) };
  }

  function localAudit(data, action, entityType, entityId, before = null, after = null, undoable = false, label = '') {
    ensureMeta(data);
    data.audit_log = Array.isArray(data.audit_log) ? data.audit_log : [];
    const id = nextLocalId(data, 'audit_log');
    data.audit_log.push({ id, action: String(action || ''), entity_type: String(entityType || ''), entity_id: Number(entityId || 0), label: String(label || ''), before: before ? clone(before) : null, after: after ? clone(after) : null, undoable: Boolean(undoable), undone_at: null, created_at: new Date().toISOString(), offline_pending: true });
    if (data.audit_log.length > 300) data.audit_log = data.audit_log.slice(-250);
    return data.audit_log[data.audit_log.length - 1];
  }

  function removeTxAndRelated(data, id) {
    const txId = Number(id);
    const target = findTx(data, txId);
    if (!target) return null;
    const before = clone(target);
    data.transactions = (data.transactions || []).filter((t) => Number(t.id) !== txId);
    (data.bills || []).forEach((b) => { Object.keys(b.payments || {}).forEach((k) => { if (Number(b.payments[k]?.transaction_id || 0) === txId) delete b.payments[k]; }); });
    localAudit(data, 'delete', 'transaction', txId, before, null, true, 'Transaksi dihapus');
    return before;
  }

  function assertLocalWalletSpendAllowed(data, walletId, amount, mode = 'spend') {
    const wallet = walletById(data, walletId);
    if (!wallet) throw new Error('Dompet transaksi tidak ditemukan.');
    const value = money(amount); if (value <= 0) return true;
    if (lower(wallet.type) === 'receivable') return true;
    const gross = Number(walletGrossBalances(data)[Number(walletId)] || 0);
    if (lower(wallet.type) === 'credit_card') {
      const limit = money(wallet.credit_limit), debt = Math.max(0, -gross), available = Math.max(0, limit - debt);
      if (mode === 'credit_payment') {
        if (value > debt) throw new Error(`Pembayaran kartu ${wallet.name || 'kartu'} tidak boleh melebihi tagihan berjalan.`);
      } else if (value > available) {
        throw new Error(`Sisa limit ${wallet.name || 'kartu'} hanya ${formatRupiah(available)}.`);
      }
      return true;
    }
    const reserved = money(wallet.reserved_balance), minimum = money(wallet.minimum_balance), available = Math.max(0, gross - reserved - minimum);
    if (value > available) {
      const details = [];
      if (reserved) details.push(`dana disisihkan ${formatRupiah(reserved)}`);
      if (minimum) details.push(`saldo minimum ${formatRupiah(minimum)}`);
      throw new Error(`Saldo tersedia ${wallet.name || 'dompet'} hanya ${formatRupiah(available)}.${details.length ? ' ' + details.join(' dan ') + ' tidak dapat digunakan.' : ''}`);
    }
    return true;
  }

  function makeTx(data, input) {
    const type = String(input.type || 'expense').toLowerCase();
    const amount = money(input.amount); if (amount <= 0) throw new Error('Nominal harus lebih dari nol.');
    const date = /^\d{4}-\d{2}-\d{2}$/.test(String(input.transaction_date || '')) ? String(input.transaction_date) : TODAY();
    const attachment = input.attachment || makeLocalAttachment(input.photo, input.photoName);
    const ocr = (input.ocr_text || input.ocr_amount) ? { text: String(input.ocr_text || ''), amount: money(input.ocr_amount), score: Number(input.ocr_score || 0), line: String(input.ocr_line || ''), confidence: Number(input.ocr_confidence || 0) } : (input.ocr ? clone(input.ocr) : null);
    const base = {
      id: nextLocalId(data, 'transactions'), type, amount, transaction_date: date, note: String(input.note || ''),
      source: String(input.source || 'manual'), created_at: new Date().toISOString(), offline_pending: true,
      ...(input.receivable_borrower_key ? { receivable_borrower_key: String(input.receivable_borrower_key) } : {}),
      ...(input.receivable_borrower_name ? { receivable_borrower_name: String(input.receivable_borrower_name) } : {}),
      ...(input.receivable_action ? { receivable_action: String(input.receivable_action) } : {}),
      ...(input.receivable_due_date ? { receivable_due_date: String(input.receivable_due_date) } : {}),
      ...(input.receivable_lend_id ? { receivable_lend_id: Number(input.receivable_lend_id) } : {}),
      ...(input.payment_wallet_id ? { payment_wallet_id: Number(input.payment_wallet_id) } : {}),
      ...(attachment ? { attachment } : {}), ...(ocr ? { ocr } : {})
    };
    if (type === 'transfer') {
      const from = Number(input.from_wallet_id || 0), to = Number(input.to_wallet_id || 0);
      if (!walletById(data, from) || !walletById(data, to) || from === to) throw new Error('Dompet transfer tidak valid.');
      assertLocalWalletSpendAllowed(data, from, amount);
      if (lower(walletById(data, to)?.type) === 'credit_card') assertLocalWalletSpendAllowed(data, to, amount, 'credit_payment');
      const tx = addTx(data, { ...base, category: String(input.category || 'Transfer Antar Dompet'), from_wallet_id: from, to_wallet_id: to, spending_kind: 'once' });
      localAudit(data, 'create', 'transaction', tx.id, null, tx, true, 'Transaksi dibuat');
      return tx;
    }
    const walletId = Number(input.wallet_id || defaultWalletId(data)); if (!walletById(data, walletId)) throw new Error('Dompet transaksi tidak ditemukan.');
    const cat = String(input.category || (type === 'income' ? 'Pemasukan' : 'Lainnya'));
    if (!categoryExists(data, cat, type) && !['Lainnya', 'Pemasukan'].includes(cat) && !/^Piutang\s·/u.test(cat)) throw new Error('Kategori tidak sesuai dengan jenis transaksi.');
    if (type === 'expense') assertLocalWalletSpendAllowed(data, walletId, amount);
    const tx = addTx(data, { ...base, category: cat, wallet_id: walletId, spending_kind: type === 'expense' ? (['daily', 'once', 'recurring'].includes(input.spending_kind) ? input.spending_kind : 'once') : 'once', bill_id: Number(input.bill_id || 0) });
    localAudit(data, 'create', 'transaction', tx.id, null, tx, true, 'Transaksi dibuat');
    return tx;
  }

  function localAssistant(data, message, input = {}) {
    const raw = String(message || '').trim(), x = normalizeMessage(raw);
    const wallets = (data.wallets || []).filter((w) => !w.archived && lower(w.type) !== 'receivable');
    const sum = buildSummary(data);
    const now = TODAY(), month = now.slice(0, 7);
    const rules = Array.isArray(data.learning?.rules) ? data.learning.rules : [];
    const learned = rules.find((r) => { const phrase = normalizeMessage(r?.phrase || ''); return phrase && (x === phrase || x.includes(phrase)); });
    if (learned?.response) return { reply: String(learned.response), saved: [] };

    const photo = input.photo instanceof Blob ? input.photo : null;
    const attachment = input.attachment || (photo ? makeLocalAttachment(photo, input.photoName) : null);
    const adminFee = extractAdminFeeClause(raw);
    const transactionText = adminFee ? adminFee.cleanText : raw;
    const transactionNorm = normalizeMessage(transactionText);
    const detectedAmount = money(input.ocr_amount);
    const amountMatch = transactionText.match(/(?:rp\s*)?\d[\d.,]*(?:\s*(?:rb|ribu|k|jt|juta))?/i);
    const typedAmount = amountMatch ? parseAmount(amountMatch[0]) : 0;
    const amountFromReceipt = detectedAmount || typedAmount;

    if (photo && String(input.image_mode || 'auto') === 'attachment') {
      const reply = 'Foto nota sudah tersimpan offline sebagai lampiran. Tidak ada transaksi yang dibuat; lampiran akan ikut tersinkron saat internet kembali.';
      addChat(data, 'assistant', reply);
      return { reply, saved: [] };
    }
    if (!x && photo && amountFromReceipt <= 0) {
      const reply = 'Foto nota tersimpan offline. Saya belum menemukan nominal yang cukup jelas. Anda bisa menuliskan nominal atau keterangan secara manual; foto tetap ikut sinkron.';
      addChat(data, 'assistant', reply);
      return { reply, saved: [] };
    }

    if (data.pending_chat_confirmation?.drafts?.length && /^(ya|iya|yes|oke|ok|simpan|konfirmasi|lanjut|jadi|benar)$/i.test(x)) {
      const saved = [];
      for (const draft of (data.pending_chat_confirmation.drafts || [])) {
        const tx = makeTx(data, draft);
        saved.push(tx);
      }
      data.pending_chat_confirmation = null;
      const reply = `Transaksi berhasil disimpan offline. ${saved.length} transaksi masuk daftar dan diberi tanda “Belum sinkron”.`;
      addChat(data, 'assistant', reply);
      return { reply, saved };
    }
    if (data.pending_chat_confirmation?.drafts?.length && /^(batal|cancel|tidak|no|jangan)$/i.test(x)) {
      data.pending_chat_confirmation = null;
      const reply = 'Baik, draft transaksi dibatalkan. Tidak ada perubahan saldo.';
      addChat(data, 'assistant', reply);
      return { reply, saved: [] };
    }
    if (!x && !photo) return { reply: 'Tuliskan pertanyaan atau transaksi yang ingin dicatat.', saved: [] };

    if (/^(saldo|berapa saldo|cek saldo|sisa saldo)/.test(x)) {
      const named = wallets.find((w) => x.includes(lower(w.name)));
      if (named) {
        const ws = walletSnapshot(data).find((w) => Number(w.id) === Number(named.id));
        return { reply: `Saldo ${named.name} saat ini ${formatRupiah(ws?.available_balance ?? ws?.balance ?? 0)}.`, saved: [] };
      }
      const lines = walletSnapshot(data).filter((w) => lower(w.type) !== 'receivable').map((w) => `• ${w.name}: ${formatRupiah(w.available_balance ?? w.balance ?? 0)}`);
      return { reply: `Saldo tersedia saat ini ${formatRupiah(sum.balance)}.${lines.length ? '\n' + lines.join('\n') : ''}`, saved: [] };
    }
    const scenarioReply = paydayScenarioReply(data, raw);
    if (scenarioReply !== null) return { reply: scenarioReply, saved: [] };
    if (/aman.*gajian|cukup.*gajian|sampai.*gajian|payday/.test(x)) {
      const p = prediction(data);
      const label = p.status === 'safe' ? 'perkiraan saldo masih positif' : p.status === 'tight' ? 'perkiraan saldo cukup ketat' : p.status === 'risk' ? 'perkiraan saldo berisiko negatif' : 'belum cukup riwayat untuk memastikan';
      return { reply: `Berdasarkan data yang tersimpan di perangkat, ${label}. Perkiraan saldo pada ${formatDate(p.payday_date)} sekitar ${formatRupiah(p.predicted_balance)}.`, saved: [] };
    }

    const monthIncome = (data.transactions || []).filter((t) => t.type === 'income' && String(t.transaction_date || '').startsWith(month)).reduce((n, t) => n + money(t.amount), 0);
    const monthExpense = (data.transactions || []).filter((t) => t.type === 'expense' && String(t.transaction_date || '').startsWith(month)).reduce((n, t) => n + money(t.amount), 0);
    const todayIncome = (data.transactions || []).filter((t) => t.type === 'income' && String(t.transaction_date || '') === now).reduce((n, t) => n + money(t.amount), 0);
    const todayExpense = (data.transactions || []).filter((t) => t.type === 'expense' && String(t.transaction_date || '') === now).reduce((n, t) => n + money(t.amount), 0);
    if (/pemasukan.*bulan|total.*pemasukan|income.*bulan/.test(x)) return { reply: `Total pemasukan bulan berjalan: ${formatRupiah(monthIncome)}.`, saved: [] };
    if (/pengeluaran.*bulan|total.*pengeluaran|expense.*bulan/.test(x)) return { reply: `Total pengeluaran bulan berjalan: ${formatRupiah(monthExpense)}.`, saved: [] };
    if (/pengeluaran.*hari ini|belanja.*hari ini|habis.*hari ini/.test(x)) return { reply: `Pengeluaran hari ini: ${formatRupiah(todayExpense)}. Pemasukan hari ini: ${formatRupiah(todayIncome)}.`, saved: [] };
    if (/transaksi.*terakhir|terakhir.*transaksi|transaksi terbaru/.test(x)) {
      const last = [...(data.transactions || [])].sort((a, b) => String(b.transaction_date || '').localeCompare(String(a.transaction_date || '')) || Number(b.id || 0) - Number(a.id || 0))[0];
      return { reply: last ? `Transaksi terakhir: ${last.type === 'income' ? 'pemasukan' : last.type === 'expense' ? 'pengeluaran' : 'transfer'} ${formatRupiah(last.amount)} untuk ${last.note || last.category || 'tanpa keterangan'} pada ${formatDate(last.transaction_date)}.` : 'Belum ada transaksi tersimpan.', saved: [] };
    }
    if (/tagihan|jatuh tempo|bayar tagihan/.test(x)) {
      const due = billStatus(data).filter((b) => !b.paid).slice(0, 5);
      return { reply: due.length ? `Tagihan aktif yang belum dibayar:\n${due.map((b) => `• ${b.name}: ${formatRupiah(b.remaining_amount || b.amount)} · ${b.status === 'overdue' ? 'terlambat' : `jatuh tempo ${formatDate(b.due_date)}`}`).join('\n')}` : 'Tidak ada tagihan aktif yang belum dibayar pada data offline.', saved: [] };
    }
    if (/budget|anggaran|batas pengeluaran/.test(x)) {
      const rows = budgetStatus(data, month);
      return { reply: rows.length ? `Status budget bulan ini:\n${rows.map((b) => `• ${b.category || b.category_name || b.category_id}: ${formatRupiah(b.used)} dari ${formatRupiah(b.limit)} (${b.percent}%)`).join('\n')}` : 'Belum ada budget bulan ini.', saved: [] };
    }
    if (/piutang|hutang|pinjaman/.test(x)) {
      const r = buildReceivables(data);
      return { reply: r.people.length ? `Total piutang yang belum lunas: ${formatRupiah(r.total_outstanding)}.\n${r.people.slice(0, 8).map((p) => `• ${p.name}: ${formatRupiah(p.outstanding)}`).join('\n')}` : 'Belum ada data piutang tersimpan di perangkat.', saved: [] };
    }
    if (/target|tabungan|goal|menabung/.test(x)) {
      const goals = (data.goals || []).filter((g) => g.active !== false);
      return { reply: goals.length ? `Target menabung aktif:\n${goals.map((g) => `• ${g.name}: ${formatRupiah(g.current_amount)} / ${formatRupiah(g.target_amount)} (${g.target_amount ? Math.round(Number(g.current_amount || 0) / Number(g.target_amount) * 100) : 0}%)`).join('\n')}` : 'Belum ada target menabung aktif.', saved: [] };
    }
    if (/pengeluaran.*terbesar|terbesar.*pengeluaran|kategori.*terbesar/.test(x)) {
      const totals = {};
      (data.transactions || []).forEach((t) => { if (t.type === 'expense' && String(t.transaction_date || '').startsWith(month)) totals[t.category] = (totals[t.category] || 0) + money(t.amount); });
      const top = Object.entries(totals).sort((a, b) => b[1] - a[1])[0];
      return { reply: top ? `Kategori pengeluaran terbesar bulan ini adalah ${top[0]} sebesar ${formatRupiah(top[1])}.` : 'Belum ada data pengeluaran bulan ini.', saved: [] };
    }

    const amount = amountFromReceipt;
    const isCashWithdrawal = /\b(?:tarik\s*tunai|ambil\s*tunai|cash\s*withdrawal|withdraw(?:al)?)\b/i.test(transactionNorm);
    const isIncome = /\b(masuk|pemasukan|terima|diterima|gaji|bonus|honor|income)\b/i.test(transactionNorm);
    const transferIntent = /\b(transfer|tf|pindah(?:kan)?|memindahkan|mutasi)\b/i.test(transactionNorm);
    const explicitMove = /\b(?:antar\s+(?:dompet|rekening)|pindah(?:kan)?\s+(?:saldo|uang))\b/i.test(transactionNorm);
    const explicitPair = transferIntent ? localExplicitTransferWallets(transactionText, wallets) : {};
    const hasExplicitWalletPair = Number(explicitPair.from_wallet_id || 0) > 0 && Number(explicitPair.to_wallet_id || 0) > 0 && Number(explicitPair.from_wallet_id) !== Number(explicitPair.to_wallet_id);
    const isTransfer = isCashWithdrawal || (transferIntent && (hasExplicitWalletPair || explicitMove));
    // Transfer ke orang/penerima, bukan antar dompet milik user, adalah pengeluaran.
    const isExpense = /\b(beli|bayar|belanja|pengeluaran|keluar|pakai|habis|makan|minum|bbm|bensin|expense|nota|struk)\b/i.test(transactionNorm) || Boolean(photo) || (transferIntent && !isTransfer);
    if (amount > 0 && (isIncome || isExpense || isTransfer)) {
      const type = isTransfer ? 'transfer' : (isIncome ? 'income' : 'expense');
      const source = photo ? 'chat_with_photo' : 'manual';
      let mainTx = null;
      let fromWallet = null;
      let toWallet = null;
      if (type === 'transfer') {
        if (isCashWithdrawal) {
          const explicit = localExplicitTransferWallets(transactionText, wallets);
          const fromMatch = explicit.from_wallet_id ? walletById(data, explicit.from_wallet_id) : null;
          fromWallet = fromMatch || (explicit.fromText ? localWalletMention(explicit.fromText, wallets) : null) || localFallbackNonCashWallet(wallets);
          toWallet = (explicit.to_wallet_id ? walletById(data, explicit.to_wallet_id) : null) || (explicit.toText ? localWalletMention(explicit.toText, wallets) : null) || localCashWallet(wallets, fromWallet?.id || 0);
          if (!fromWallet || !toWallet || Number(fromWallet.id) === Number(toWallet.id)) {
            return { reply: 'Saya belum menemukan dompet asal bank dan dompet Tunai yang berbeda. Tambahkan/pilih dompet bertipe Tunai atau tuliskan “dari BCA ke Tunai”, lalu coba lagi.', saved: [] };
          }
          mainTx = makeTx(data, { type, amount, transaction_date: dateFromText(transactionText), note: transactionText, from_wallet_id: fromWallet.id, to_wallet_id: toWallet.id, source, attachment, ocr: input.ocr });
          Object.assign(mainTx, { cash_withdrawal: true });
        } else {
          const explicit = localExplicitTransferWallets(transactionText, wallets);
          const fromMatch = explicit.from_wallet_id ? walletById(data, explicit.from_wallet_id) : null;
          const toMatch = explicit.to_wallet_id ? walletById(data, explicit.to_wallet_id) : null;
          if (fromMatch && toMatch && Number(fromMatch.id) !== Number(toMatch.id)) {
            fromWallet = fromMatch; toWallet = toMatch;
          } else {
            const named = wallets.filter((w) => lower(w.name) && transactionNorm.includes(lower(w.name)));
            fromWallet = named[0] || localFallbackNonCashWallet(wallets) || wallets[0];
            toWallet = named.find(w => Number(w.id) !== Number(fromWallet?.id)) || wallets.find(w => Number(w.id) !== Number(fromWallet?.id));
          }
          if (!fromWallet || !toWallet || Number(fromWallet.id) === Number(toWallet.id)) return { reply: 'Untuk transfer offline, minimal harus ada dua dompet aktif.', saved: [] };
          mainTx = makeTx(data, { type, amount, transaction_date: dateFromText(transactionText), note: transactionText, from_wallet_id: fromWallet.id, to_wallet_id: toWallet.id, source, attachment, ocr: input.ocr });
        }
      } else {
        const walletName = localWalletMention(transactionText, wallets);
        const wallet = walletName || walletById(data, defaultWalletId(data));
        mainTx = makeTx(data, { type, amount, transaction_date: dateFromText(transactionText), note: transactionText || (photo ? 'Transaksi dari foto nota' : ''), category: categoryFor(transactionText || String(input.ocr_text || ''), type), wallet_id: wallet?.id || defaultWalletId(data), spending_kind: spendingKind(transactionText, type), source, attachment, ocr: input.ocr });
      }
      // makeTx membantu validasi wallet, tetapi draft belum boleh meninggalkan
      // transaksi/audit permanen sampai user menekan Simpan.
      const removeDraftTx = (tx) => {
        data.transactions = (data.transactions || []).filter((t) => Number(t.id) !== Number(tx.id));
        data.audit_log = (data.audit_log || []).filter((a) => !(String(a.entity_type) === 'transaction' && Number(a.entity_id) === Number(tx.id) && String(a.action) === 'create'));
      };
      removeDraftTx(mainTx);
      const drafts = [mainTx];
      if (adminFee && Number(adminFee.amount) > 0) {
        const feeWalletId = type === 'transfer' ? Number(mainTx.from_wallet_id || 0) : Number(mainTx.wallet_id || 0);
        if (!feeWalletId) return { reply: 'Dompet untuk biaya admin belum dapat ditentukan. Pilih dompet pada keterangan transaksi lalu coba lagi.', saved: [] };
        const feeLabel = isCashWithdrawal ? 'Biaya admin tarik tunai' : (type === 'transfer' ? 'Biaya admin transfer' : 'Biaya admin transaksi');
        const feeTx = makeTx(data, { type: 'expense', amount: Number(adminFee.amount), transaction_date: String(mainTx.transaction_date || dateFromText(transactionText)), note: `${feeLabel} · ${String(mainTx.note || transactionText).slice(0, 180)}`, category: 'Biaya Administrasi Bank', wallet_id: feeWalletId, spending_kind: 'once', source: 'chat_admin_fee' });
        removeDraftTx(feeTx);
        Object.assign(feeTx, { admin_fee_auto_wallet: true, admin_fee_parent_index: 0 });
        drafts.push(feeTx);
      }
      const missingCash = Boolean(isCashWithdrawal && (!toWallet || Number(toWallet.id || 0) <= 0));
      return confirmation(data, raw, drafts, missingCash ? 'Pilih dompet tujuan untuk tarik tunai.' : '');
    }

    if (photo) {
      const reply = amountFromReceipt > 0 ? `Foto tersimpan offline dan nominal ${formatRupiah(amountFromReceipt)} terdeteksi, tetapi saya memerlukan keterangan transaksi untuk membuat draft yang lebih akurat.` : 'Foto tersimpan offline. Saya belum dapat membuat transaksi tanpa nominal yang jelas atau keterangan tambahan. Foto tetap akan ikut sinkron.';
      addChat(data, 'assistant', reply);
      return { reply, saved: [] };
    }

    return { reply: 'Mode offline aktif. Saya tetap bisa membaca saldo/dompet, transaksi, budget, tagihan, piutang, target menabung, analitik, prediksi sampai gajian, serta mencatat transaksi baru untuk dikonfirmasi.', saved: [] };
  }

  function confirmation(data, message, drafts, warning = '') {
    const id = nextLocalId(data, 'transactions');
    const cleanDrafts = drafts.map((t) => ({ ...clone(t), id: Number(t.id), offline_pending: true }));
    data.pending_chat_confirmation = { id, created_at: new Date().toISOString(), message, drafts: cleanDrafts, warning: String(warning || '') };
    const lines = cleanDrafts.map((t, i) => {
      const label = t.type === 'income' ? 'Pemasukan' : t.type === 'expense' ? 'Pengeluaran' : 'Transfer';
      if (t.type === 'transfer') {
        const from = walletById(data, t.from_wallet_id), to = walletById(data, t.to_wallet_id);
        return `${i + 1}. ${label} · ${formatRupiah(t.amount)} · ${from?.name || 'Pilih asal'} → ${to?.name || 'Pilih tujuan'} · ${formatDate(t.transaction_date)}.`;
      }
      const wallet = walletById(data, t.wallet_id);
      const linked = t.admin_fee_auto_wallet ? ' · Dompet mengikuti transaksi utama' : '';
      return `${i + 1}. ${label} · ${formatRupiah(t.amount)} · ${t.category || 'Lainnya'} · ${wallet?.name || 'Dompet utama'} · ${formatDate(t.transaction_date)}${linked}.`;
    });
    const reply = `Periksa dulu sebelum disimpan offline:${warning ? `\n⚠️ ${warning}` : ''}\n${lines.join('\n')}\nSetelah dikonfirmasi, transaksi langsung masuk daftar lokal dengan status “Belum sinkron”.`;
    addChat(data, 'assistant', reply);
    return { reply, saved: [] };
  }
  function addChat(data, role, message, attachment = null) {
    data.chats = Array.isArray(data.chats) ? data.chats : [];
    const id = nextLocalId(data, 'chats');
    data.chats.push({ id, role, message: String(message || ''), created_at: new Date().toISOString(), ...(attachment ? { attachment } : {}) , offline_pending: true });
    return data.chats[data.chats.length - 1];
  }

  function formatRupiah(n) { return 'Rp' + Number(n || 0).toLocaleString('id-ID'); }
  function formatDate(d) { const m = String(d || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? `${m[3]}/${m[2]}/${m[1]}` : String(d || ''); }

  function dashboard(bundle, data, features, summary) {
    const state = clone(bundle.state || {});
    state.ok = true; state.summary = summary; state.features = features; state.account = clone(bundle.account || state.account || {});
    state.transactions = (data.transactions || []).slice().sort((a, b) => String(b.transaction_date || '').localeCompare(String(a.transaction_date || '')) || Number(b.id || 0) - Number(a.id || 0)).slice(0, 10);
    state.chats = (data.chats || []).slice().sort((a, b) => new Date(a.created_at || 0) - new Date(b.created_at || 0)).slice(-10);
    state.chat_meta = { page: 1, limit: 10, total: data.chats.length, loaded: state.chats.length, has_more: data.chats.length > state.chats.length, next_page: data.chats.length > state.chats.length ? 2 : null };
    state.transaction_meta = { count: data.transactions.length, income: summary.income, expense: summary.expense, pagination: { page: 1, limit: 10, total: data.transactions.length, loaded: state.transactions.length, loaded_through: state.transactions.length, has_more: data.transactions.length > state.transactions.length, next_page: data.transactions.length > state.transactions.length ? 2 : null } };
    state.revision = Number(state.revision || 0);
    state.daily_budget = dailyBudgetStatus(data);
    state.daily_budget_settings = clone(data.settings?.daily_budget || {});
    state.offline_mode = true;
    return state;
  }

  function dailyBudgetStatus(data, date = TODAY()) {
    const cfg = data.settings?.daily_budget || {}, dayNo = new Date(`${date}T00:00:00`).getDay() || 7, day = cfg.days?.[String(dayNo)] || { enabled: false, limit: 0 };
    const spent = (data.transactions || []).filter((t) => t.type === 'expense' && String(t.transaction_date || '') === date).reduce((n, t) => n + money(t.amount), 0);
    const limit = money(day.limit), active = Boolean(cfg.enabled && day.enabled && limit > 0), percent = active ? Math.round(spent / limit * 100) : 0;
    return { date, day_no: dayNo, enabled: Boolean(cfg.enabled), active, spent, limit, remaining: active ? Math.max(0, limit - spent) : 0, over: active ? Math.max(0, spent - limit) : 0, warning_percent: Number(cfg.warning_percent || 80), percent, progress_percent: Math.min(100, percent), status: active ? (spent > limit ? 'exceeded' : spent === limit ? 'reached' : percent >= Number(cfg.warning_percent || 80) ? 'warning' : 'safe') : 'inactive', label: active ? (spent > limit ? 'Batas terlewati' : spent === limit ? 'Batas tercapai' : percent >= Number(cfg.warning_percent || 80) ? 'Mendekati batas' : 'Masih aman') : 'Tidak aktif', message: active ? 'Status batas pengeluaran hari ini.' : 'Peringatan pengeluaran tidak aktif untuk hari ini.' };
  }

  function requestBodyToObject(options = {}) {
    const body = options.body;
    if (body instanceof FormData) {
      const out = {};
      for (const [k, v] of body.entries()) { if (v instanceof Blob) out[k] = v; else out[k] = String(v); }
      return out;
    }
    if (typeof body === 'string') { try { return JSON.parse(body || '{}'); } catch (_) { return {}; } }
    return {};
  }

  async function persist(bundle, data, resultState, features, summary, account = null, notificationCenter = null) {
    const full = { ...clone(bundle), saved_at: Date.now(), data: clone(data), learning: clone(data.learning || bundle.learning || {}), state: clone(resultState), features: clone(features), summary: clone(summary), account: clone(account || bundle.account || {}), notification_center: clone(notificationCenter || bundle.notification_center || { unread_count: 0, notifications: [] }), transaction_inbox: clone(data.meta?.local_transaction_inbox || bundle.transaction_inbox || features.transaction_inbox || { items: [], pending_count: 0, reconciliation: {} }) };
    await DB().saveSnapshot('offline_full', full);
    await DB().saveSnapshot('dashboard', resultState);
    await DB().saveSnapshot('all_transactions', clone(data.transactions || []));
  }

  async function localState() {
    const bundle = await getBundle();
    const data = clone(bundle.data || {});
    const recurringCreated = processRecurring(data);
    const billsChanged = syncCreditCardBills(data);
    const features = buildFeatures(data, bundle), summary = buildSummary(data), state = dashboard(bundle, data, features, summary);
    if (recurringCreated.length || billsChanged) {
      await persist(bundle, data, state, features, summary, bundle.account, bundle.notification_center);
      if (recurringCreated.length && DB()?.listQueue && DB()?.queueRequest) {
        try {
          const queue = await DB().listQueue(CFG().userId);
          const already = (queue || []).some((row) => {
            const p = row.preview || {}; return p.action === 'recurring_run' && (p.local_transaction_ids || []).some((id) => recurringCreated.some((tx) => Number(tx.id) === Number(id)));
          });
          if (!already) {
            await DB().queueRequest('ajax/features.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'recurring_run'}) }, { kind:'features', preview:{action:'recurring_run', local_transaction_ids:recurringCreated.map((x)=>Number(x.id)), offline_local_applied:true} });
          }
        } catch (_) {}
      }
    }
    return { bundle, data, features, summary, state, recurringCreated };
  }

  function applyFeatureAction(data, action, p) {
    switch (action) {
      case 'transaction_create': {
        const tx = makeTx(data, p); return tx;
      }
      case 'quick_capture': return addInboxDraft(data, p);
      case 'wallet_save': {
        const id = Number(p.id || 0); let w = id ? walletById(data, id) : null;
        if (w) Object.assign(w, { name: String(p.name || w.name).slice(0, 60), type: String(p.type || w.type), initial_balance: money(p.initial_balance), reserved_balance: money(p.reserved_balance), minimum_balance: money(p.minimum_balance), archived: false });
        else { w = { id: nextLocalId(data, 'wallets'), name: String(p.name || 'Dompet').slice(0, 60), type: String(p.type || 'cash'), initial_balance: money(p.initial_balance), reserved_balance: money(p.reserved_balance), minimum_balance: money(p.minimum_balance), archived: false, created_at: new Date().toISOString() }; data.wallets.push(w); }
        if (w.type === 'credit_card') Object.assign(w, { credit_limit: money(p.credit_limit), opening_debt: money(p.opening_debt), billing_day: Number(p.billing_day || 0) });
        return w;
      }
      case 'wallet_archive': { const w = walletById(data, Number(p.id)); if (!w) throw new Error('Dompet tidak ditemukan.'); w.archived = true; return true; }
      case 'wallet_reorder': { const ids = Array.isArray(p.wallet_ids) ? p.wallet_ids.map(Number) : []; data.wallets = [...data.wallets].sort((a, b) => ids.indexOf(Number(a.id)) - ids.indexOf(Number(b.id))); return true; }
      case 'wallet_transfer': return makeTx(data, { type: 'transfer', amount: p.amount, transaction_date: p.transaction_date || TODAY(), note: p.note || '', from_wallet_id: p.from_wallet_id, to_wallet_id: p.to_wallet_id, source: 'wallet_transfer' });
      case 'category_save': {
        const id = Number(p.id || 0); let c = id ? data.categories.find((x) => Number(x.id) === id) : null;
        if (c) Object.assign(c, { name: String(p.name || c.name).slice(0, 80), type: String(p.type || c.type || 'both') });
        else { c = { id: nextLocalId(data, 'categories'), name: String(p.name || 'Kategori').slice(0, 80), type: String(p.type || 'both'), icon: String(p.icon || ''), keywords: Array.isArray(p.keywords) ? p.keywords : [], archived: false }; data.categories.push(c); }
        return c;
      }
      case 'category_archive': { const c = data.categories.find((x) => Number(x.id) === Number(p.id)); if (!c) throw new Error('Kategori tidak ditemukan.'); c.archived = true; return true; }
      case 'monthly_budget_set': { const month = String(p.month || TODAY().slice(0, 7)); let row = data.monthly_budgets.find((x) => Number(x.category_id) === Number(p.category_id) && String(x.month) === month); if (!row) { row = { category_id: Number(p.category_id), month }; data.monthly_budgets.push(row); } row.limit = money(p.limit); row.enabled = row.limit > 0; return row; }
      case 'bill_save': { const id = Number(p.id || 0); let b = id ? data.bills.find((x) => Number(x.id) === id) : null; if (b) Object.assign(b, clone(p)); else { b = { ...clone(p), id: nextLocalId(data, 'bills'), created_at: new Date().toISOString(), payments: {} }; data.bills.push(b); } return b; }
      case 'bill_delete': { data.bills = data.bills.filter((b) => Number(b.id) !== Number(p.id)); return true; }
      case 'bill_pay': { const b = data.bills.find((x) => Number(x.id) === Number(p.id)); if (!b) throw new Error('Tagihan tidak ditemukan.'); b.payments = b.payments || {}; const payDate = String(p.date || TODAY()); const paymentWallet = Number(p.wallet_id || b.wallet_id || defaultWalletId(data)); if (b.auto_generated && b.bill_type === 'credit_card') { const cardId = Number(b.credit_card_wallet_id || 0); if (!cardId) throw new Error('Kartu kredit tagihan tidak ditemukan.'); if (!paymentWallet || paymentWallet === cardId) throw new Error('Pilih dompet non-kartu kredit untuk pembayaran.'); const remaining = Math.max(0, money(b.amount) - Object.values(b.payments).reduce((n, x) => n + money(x?.amount), 0)); if (remaining <= 0) throw new Error('Tagihan kartu kredit ini sudah lunas.'); const tx = makeTx(data, { type: 'transfer', amount: remaining, transaction_date: payDate, note: `Bayar ${b.name}`, from_wallet_id: paymentWallet, to_wallet_id: cardId, source: 'credit_card_bill_payment', bill_id: b.id }); b.wallet_id = paymentWallet; b.payments[`offline:${Date.now()}`] = { date: payDate, transaction_id: Number(tx.id), amount: remaining }; return tx; } const key = /^\d{4}-\d{2}-\d{2}$/.test(String(b.due_date || '')) ? `due:${String(b.due_date)}` : payDate.slice(0,7); if (b.payments[key]) throw new Error('Tagihan ini sudah dibayar.'); const tx = makeTx(data, { type: 'expense', amount: b.amount, transaction_date: payDate, note: `Bayar ${b.name}`, category: b.category || 'Tagihan', wallet_id: paymentWallet, spending_kind: 'once', bill_id: b.id, source: 'bill' }); b.payments[key] = { date: payDate, amount: money(b.amount), transaction_id: Number(tx.id) }; return tx; }
      case 'recurring_save': { const id = Number(p.id || 0); let r = id ? data.recurring.find((x) => Number(x.id) === id) : null; if (r) Object.assign(r, clone(p)); else { r = { ...clone(p), id: nextLocalId(data, 'recurring'), created_at: new Date().toISOString() }; data.recurring.push(r); } return r; }
      case 'recurring_delete': data.recurring = data.recurring.filter((r) => Number(r.id) !== Number(p.id)); return true;
      case 'recurring_run': return processRecurring(data);
      case 'goal_save': { const id = Number(p.id || 0); let g = id ? data.goals.find((x) => Number(x.id) === id) : null; if (g) Object.assign(g, clone(p)); else { g = { ...clone(p), id: nextLocalId(data, 'goals'), current_amount: money(p.current_amount), active: true, created_at: new Date().toISOString() }; data.goals.push(g); } return g; }
      case 'goal_delete': data.goals = data.goals.filter((g) => Number(g.id) !== Number(p.id)); return true;
      case 'goal_contribute': { const g = data.goals.find((x) => Number(x.id) === Number(p.id)); if (!g) throw new Error('Target tidak ditemukan.'); g.current_amount = Math.min(money(g.target_amount), money(g.current_amount) + money(p.amount)); g.active = g.current_amount < money(g.target_amount); return g; }
      case 'payday_set': data.settings = data.settings || {}; data.settings.payday_day = Math.max(1, Math.min(31, Number(p.day || 1))); return data.settings.payday_day;
      case 'notifications_set': data.settings = data.settings || {}; data.settings.notifications = clone(p.settings || {}); return data.settings.notifications;
      case 'history_undo': {
        const audit = (data.audit_log || []).find((x) => Number(x.id) === Number(p.id));
        if (!audit || !audit.undoable || audit.undone_at) throw new Error('Perubahan tidak dapat dibatalkan dari data offline.');
        const entityId = Number(audit.entity_id || 0);
        if (audit.entity_type === 'transaction') {
          if (audit.action === 'create') {
            data.transactions = (data.transactions || []).filter((t) => Number(t.id) !== entityId);
          } else if (audit.action === 'delete') {
            const before = clone(audit.before);
            if (!before) throw new Error('Data transaksi sebelum dihapus tidak tersedia.');
            before.offline_pending = true;
            data.transactions.push(before);
          } else if (audit.action === 'update') {
            const before = clone(audit.before);
            const idx = (data.transactions || []).findIndex((t) => Number(t.id) === entityId);
            if (!before || idx < 0) throw new Error('Data transaksi sebelum perubahan tidak tersedia.');
            before.offline_pending = true;
            data.transactions[idx] = before;
          } else {
            throw new Error('Jenis perubahan transaksi ini belum bisa dibatalkan offline.');
          }
          audit.undone_at = new Date().toISOString();
          localAudit(data, 'undo', 'transaction', entityId, null, null, false, 'Perubahan transaksi dibatalkan');
          audit.offline_local_undo = true;
          return audit;
        }
        throw new Error('Undo offline saat ini hanya tersedia untuk transaksi.');
      }
      case 'reconciliation_complete': { const box = inboxData(data); box.reconciliation = { date: String(p.date || TODAY()), completed_at: new Date().toISOString(), last_prompt_at: new Date().toISOString() }; return box.reconciliation; }
      case 'inbox_dismiss': { const box = inboxData(data), id = Number(p.id); const item = box.items.find((x) => Number(x.id) === id); if (!item) throw new Error('Draf transaksi tidak ditemukan.'); item.status = 'dismissed'; item.updated_at = new Date().toISOString(); box.pending_count = box.items.filter((x) => x.status === 'pending').length; return true; }
      case 'inbox_confirm': {
        const box = inboxData(data), id = Number(p.id), item = box.items.find((x) => Number(x.id) === id); if (!item) throw new Error('Draf transaksi tidak ditemukan.');
        const s = { ...(item.suggested || {}), ...(p || {}) };
        const tx = makeTx(data, s); Object.assign(tx, { source: 'manual', note: String(s.note || item.note), offline_pending: true });
        item.status = 'resolved'; item.resolved_transaction_id = Number(tx.id); item.updated_at = new Date().toISOString(); box.pending_count = box.items.filter((x) => x.status === 'pending').length;
        ensureMeta(data).local_last_operation = { inbox_ids: [Number(item.id)], transaction_ids: [Number(tx.id)] };
        return tx;
      }
      case 'inbox_confirm_all': {
        const ids = Array.isArray(p.ids) ? p.ids.map(Number) : []; const box = inboxData(data), confirmed = [];
        box.items.filter((x) => x.status === 'pending' && (!ids.length || ids.includes(Number(x.id)))).forEach((item) => { const tx = makeTx(data, item.suggested || item); item.status = 'resolved'; item.resolved_transaction_id = Number(tx.id); item.updated_at = new Date().toISOString(); confirmed.push({ id: Number(item.id), transaction_id: Number(tx.id) }); });
        box.pending_count = box.items.filter((x) => x.status === 'pending').length;
        ensureMeta(data).local_last_operation = { inbox_ids: confirmed.map((x) => Number(x.id)), transaction_ids: confirmed.map((x) => Number(x.transaction_id)) };
        return { confirmed_count: confirmed.length, confirmed, failed_count: 0, failed: [], skipped_count: 0, skipped: [] };
      }
      case 'receivable_lend': {
        const name = String(p.borrower_name || 'Peminjam'), key = String(p.borrower_key || name.toLowerCase()), system = (data.wallets || []).find((w) => w.type === 'receivable' && w.system_key === 'receivable') || (() => { const w = { id: nextLocalId(data, 'wallets'), name: 'Piutang (Sistem)', type: 'receivable', initial_balance: 0, reserved_balance: 0, minimum_balance: 0, archived: true, system_key: 'receivable', created_at: new Date().toISOString() }; data.wallets.push(w); return w; })();
        const attachment = p.photo instanceof Blob ? makeLocalAttachment(p.photo, p.photoName || p.photo?.name || 'receipt.jpg') : (p.attachment || null);
        const tx = makeTx(data, { type: 'transfer', amount: p.amount, transaction_date: p.transaction_date, note: p.note || '', from_wallet_id: p.wallet_id, to_wallet_id: system.id, source: 'receivable_lend', attachment });
        Object.assign(tx, { source: 'receivable_lend', receivable_borrower_key: key, receivable_borrower_name: name, receivable_action: 'lend', receivable_due_date: String(p.due_date || ''), category: `Piutang · ${name}`, attachment: attachment || tx.attachment || undefined });
        return tx;
      }
      case 'receivable_repay': {
        const key = String(p.borrower_key || ''), people = buildReceivables(data).people || [], person = people.find((x) => x.key === key); if (!person || Number(person.outstanding || 0) <= 0) throw new Error('Piutang peminjam ini sudah lunas atau belum tersedia offline.');
        const system = (data.wallets || []).find((w) => w.type === 'receivable' && w.system_key === 'receivable'); if (!system) throw new Error('Data dompet sistem piutang belum tersedia offline. Sinkronkan sekali terlebih dahulu.');
        const attachment = p.photo instanceof Blob ? makeLocalAttachment(p.photo, p.photoName || p.photo?.name || 'receipt.jpg') : (p.attachment || null);
        const tx = makeTx(data, { type: 'transfer', amount: p.amount, transaction_date: p.transaction_date, note: p.note || '', from_wallet_id: system.id, to_wallet_id: p.wallet_id, source: 'receivable_repayment', attachment, receivable_borrower_key: key, receivable_borrower_name: person.name, receivable_action: 'repayment', receivable_lend_id: Number(p.lend_id || p.receivable_lend_id || 0) });
        Object.assign(tx, { source: 'receivable_repayment', receivable_borrower_key: key, receivable_borrower_name: person.name, receivable_action: 'repayment', receivable_lend_id: Number(p.lend_id || p.receivable_lend_id || 0), attachment: attachment || tx.attachment || undefined });
        return tx;
      }
      case 'receivable_interest_add': {
        data.meta = data.meta || {}; data.meta.receivable_interest_entries = Array.isArray(data.meta.receivable_interest_entries) ? data.meta.receivable_interest_entries : [];
        const row = { id: nextLocalId(data, 'meta'), borrower_key: String(p.borrower_key || ''), borrower_name: String(p.borrower_name || ''), lend_id: Number(p.lend_id || 0), amount: money(p.amount || (money(p.base_amount) * Number(p.rate_percent || 0) / 100)), rate_percent: Number(p.rate_percent || 0), base_amount: money(p.base_amount), transaction_date: String(p.transaction_date || TODAY()), note: String(p.note || ''), created_at: new Date().toISOString() }; data.meta.receivable_interest_entries.push(row); return row;
      }
      case 'receivable_interest_delete': data.meta = data.meta || {}; data.meta.receivable_interest_entries = (data.meta.receivable_interest_entries || []).filter((x) => Number(x.id) !== Number(p.id)); return true;
      case 'receivable_delete': data.transactions = data.transactions.filter((t) => Number(t.id) !== Number(p.id)); return true;
      default: throw new Error(`Aksi offline belum dapat dijalankan: ${action}`);
    }
  }

  function updateTransactionLocal(data, p) {
    const t = findTx(data, Number(p.id)); if (!t) throw new Error('Transaksi tidak ditemukan.');
    if (['receivable_lend','receivable_repayment'].includes(String(t.source || ''))) throw new Error('Transaksi piutang dikelola melalui menu Piutang.');
    if (p.expected_version && Number(p.id) >= 0 && String(p.expected_version) !== String(t.updated_at || t.created_at || '')) throw new Error('Transaksi berubah setelah data offline disimpan. Sinkronkan lalu coba lagi.');
    const before = clone(t);
    data.transactions = (data.transactions || []).filter((row) => Number(row.id) !== Number(t.id));
    const billsBefore = clone(data.bills || []);
    const auditBefore = clone(data.meta?.audit_log || []);
    try {
      const type = String(p.type || before.type || 'expense').toLowerCase();
      const amount = money(p.amount); if (amount <= 0) throw new Error('Nominal harus lebih dari nol.');
      const date = /^\d{4}-\d{2}-\d{2}$/.test(String(p.transaction_date || '')) ? String(p.transaction_date) : String(before.transaction_date || TODAY());
      const updated = { ...before, type, amount, transaction_date: date, note: String(p.note ?? before.note ?? ''), updated_at: new Date().toISOString(), offline_pending: true };
      if (type === 'transfer') {
        const from = Number(p.from_wallet_id || 0), to = Number(p.to_wallet_id || 0);
        if (!walletById(data, from) || !walletById(data, to) || from === to) throw new Error('Dompet transfer tidak valid.');
        assertLocalWalletSpendAllowed(data, from, amount);
        if (lower(walletById(data, to)?.type) === 'credit_card') assertLocalWalletSpendAllowed(data, to, amount, 'credit_payment');
        Object.assign(updated, { category: 'Transfer Antar Dompet', from_wallet_id: from, to_wallet_id: to, spending_kind: 'once', wallet_id: undefined, bill_id: 0 });
      } else {
        const walletId = Number(p.wallet_id || before.wallet_id || defaultWalletId(data)); if (!walletById(data, walletId)) throw new Error('Dompet transaksi tidak ditemukan.');
        const category = String(p.category || before.category || (type === 'income' ? 'Pemasukan' : 'Lainnya'));
        if (!categoryExists(data, category, type) && !['Lainnya', 'Pemasukan'].includes(category) && !/^Piutang\s·/u.test(category)) throw new Error('Kategori tidak sesuai dengan jenis transaksi.');
        if (type === 'expense') assertLocalWalletSpendAllowed(data, walletId, amount);
        Object.assign(updated, { category, wallet_id: walletId, spending_kind: type === 'expense' ? (['daily','once','recurring'].includes(String(p.spending_kind || before.spending_kind || 'once')) ? String(p.spending_kind || before.spending_kind || 'once') : 'once') : 'once', bill_id: Number(p.bill_id || 0), from_wallet_id: undefined, to_wallet_id: undefined });
      }
      // Keep the same ID; server edit maps this negative ID only when it represents a local create.
      data.transactions.push(updated);
      // Maintain the local bill payment relation when the edited transaction is linked/unlinked.
      (data.bills || []).forEach((bill) => { Object.keys(bill.payments || {}).forEach((key) => { if (Number(bill.payments[key]?.transaction_id || 0) === Number(updated.id)) delete bill.payments[key]; }); });
      if (Number(updated.bill_id || 0) > 0) {
        const bill = (data.bills || []).find((x) => Number(x.id) === Number(updated.bill_id));
        if (!bill) throw new Error('Tagihan yang dipilih tidak ditemukan.');
        bill.payments = bill.payments || {};
        const key = /^\d{4}-\d{2}-\d{2}$/.test(String(bill.due_date || '')) ? `due:${String(bill.due_date)}` : date.slice(0,7);
        if (bill.payments[key] && Number(bill.payments[key].transaction_id || 0) !== Number(updated.id)) throw new Error('Tagihan ini sudah memiliki pembayaran untuk periode tersebut.');
        bill.payments[key] = { date, transaction_id: Number(updated.id), amount: amount };
      }
      localAudit(data, 'update', 'transaction', updated.id, before, updated, true, 'Transaksi diperbarui');
      return updated;
    } catch (err) {
      data.transactions = (data.transactions || []).filter((row) => Number(row.id) !== Number(t.id));
      data.transactions.push(t);
      data.bills = billsBefore;
      if (data.meta) data.meta.audit_log = auditBefore;
      throw err;
    }
  }

  function recordOfflineServiceAction(data, key, payload) {
    data.meta = data.meta || {};
    data.meta.offline_service_actions = Array.isArray(data.meta.offline_service_actions) ? data.meta.offline_service_actions : [];
    data.meta.offline_service_actions.push({
      id: nextLocalId(data, 'service_actions'),
      key: String(key || ''),
      client_op_id: String(payload?.client_op_id || ''),
      payload: clone(payload || {}),
      created_at: new Date().toISOString(),
      offline_pending: true,
    });
    // Keep the local audit compact so an account used for months does not grow without bound.
    if (data.meta.offline_service_actions.length > 100) data.meta.offline_service_actions = data.meta.offline_service_actions.slice(-100);
  }

  async function handleRequest(url, options = {}) {
    const kind = cleanUrl(url), method = String(options.method || 'GET').toUpperCase();
    if (!CFG().userId) throw new Error('Akun offline belum dikenali. Buka aplikasi sekali saat online terlebih dahulu.');
    const { bundle, data, features, summary, state } = await localState();

    if (method === 'GET') {
      if (kind === 'ajax/dashboard.php') return state;
      if (kind === 'ajax/features.php') return { ok: true, features, summary, account: clone(bundle.account || {}) , offline_local: true };
      if (kind === 'ajax/transactions.php') {
        const q = new URLSearchParams(String(url).split('?')[1] || '');
        const type = q.get('type') || 'all', page = Math.max(1, Number(q.get('page') || 1)), limit = Math.max(1, Math.min(100, Number(q.get('limit') || 10)));
        let rows = (data.transactions || []).filter((t) => (type === 'all' || t.type === type) && (!q.get('from') || String(t.transaction_date) >= q.get('from')) && (!q.get('to') || String(t.transaction_date) <= q.get('to')) && (!q.get('wallet_id') || Number(t.wallet_id || t.from_wallet_id || t.to_wallet_id) === Number(q.get('wallet_id'))) && (!q.get('category') || lower(t.category) === lower(q.get('category'))) && (!q.get('search') || lower(`${t.note} ${t.category} ${t.amount}`).includes(lower(q.get('search')))));
        if ((q.get('sort') || 'date_desc') === 'date_asc') rows.sort((a, b) => String(a.transaction_date).localeCompare(String(b.transaction_date)));
        else if ((q.get('sort') || '') === 'amount_desc') rows.sort((a, b) => money(b.amount) - money(a.amount));
        else if ((q.get('sort') || '') === 'amount_asc') rows.sort((a, b) => money(a.amount) - money(b.amount));
        else rows.sort((a, b) => String(b.transaction_date).localeCompare(String(a.transaction_date)) || Number(b.id) - Number(a.id));
        const total = rows.length, start = (page - 1) * limit, items = rows.slice(start, start + limit);
        return { ok: true, transactions: items, meta: { count: total, income: rows.filter((t) => t.type === 'income').reduce((n, t) => n + money(t.amount), 0), expense: rows.filter((t) => t.type === 'expense').reduce((n, t) => n + money(t.amount), 0), pagination: { page, limit, total, loaded: items.length, loaded_through: start + items.length, has_more: start + items.length < total, next_page: start + items.length < total ? page + 1 : null } }, offline_local: true };
      }
      if (kind === 'ajax/chats.php') {
        const q = new URLSearchParams(String(url).split('?')[1] || ''), page = Math.max(1, Number(q.get('page') || 1)), limit = Math.max(1, Math.min(50, Number(q.get('limit') || 10))), rows = (data.chats || []).slice().sort((a, b) => new Date(a.created_at || 0) - new Date(b.created_at || 0)), total = rows.length, start = Math.max(0, total - page * limit), end = total - (page - 1) * limit;
        return { ok: true, chats: rows.slice(start, end), meta: { page, limit, total, loaded: Math.max(0, end - start), has_more: start > 0, next_page: start > 0 ? page + 1 : null }, offline_local: true };
      }
      if (kind === 'ajax/edit_transaction.php') {
        const q = new URLSearchParams(String(url).split('?')[1] || ''), t = findTx(data, Number(q.get('id') || 0)); if (!t) throw new Error('Transaksi tidak ditemukan.');
        return { ok: true, transaction: clone(t), wallets: walletSnapshot(data), categories: clone(data.categories || []), bills: billStatus(data), offline_local: true };
      }
      if (kind === 'ajax/notifications.php') return { ok: true, ...notificationSnapshot(bundle), offline_local: true };
      if (kind === 'ajax/learning.php') return { ok: true, rules: clone(bundle.learning?.rules || []), pending: clone(bundle.learning?.pending || null), offline_local: true };
      if (kind === 'ajax/subscription.php') return { ok: true, account: clone(bundle.account || {}), offline_local: true };
      if (kind === 'ajax/simulation.php') {
        if (!(bundle.account?.role === 'super_admin' || bundle.account?.plan?.active)) throw new Error('Simulasi Keuangan membutuhkan status Premium yang tersimpan di perangkat.');
        const q = new URLSearchParams(String(url).split('?')[1] || ''); const month = q.get('month') || TODAY().slice(0, 7); const a = analytics(data, month); return { month, income: a.income, expense: a.expense, history: { used_months: 1, from: month, to: month, months: [{ month, income: a.income, expense: a.expense }] }, income: a.income, expenses: a.expense, net: a.net, offline_local: true };
      }
      if (kind === 'ajax/realtime.php') return { ok: true, revision: Number(bundle.state?.revision || 0), changed: false, offline_local: true };
      throw new Error('Data offline untuk menu ini belum tersimpan pada perangkat.');
    }

    const p = requestBodyToObject(options);
    if (options.body instanceof FormData) {
      const photoField = options.body.get('photo');
      if (photoField instanceof Blob && photoField.size > 0) { p.photo = photoField; p.photoName = photoField.name || 'receipt.jpg'; }
    }
    let result = null;
    const premiumActions = new Set(['wallet_save','wallet_archive','wallet_transfer','wallet_reorder','category_save','category_archive','monthly_budget_set','bill_save','bill_delete','bill_pay','recurring_save','recurring_delete','recurring_run','goal_save','goal_delete','goal_contribute','payday_set']);
    if (premiumActions.has(String(p.action || '')) && !(bundle.account?.role === 'super_admin' || bundle.account?.plan?.active)) throw new Error('Fitur ini membutuhkan status Premium yang tersimpan di perangkat.');
    if (kind === 'ajax/learning.php' && bundle.account?.role !== 'super_admin') throw new Error('Fitur pembelajaran AI hanya tersedia untuk Super Admin.');
    if (kind === 'ajax/features.php') result = applyFeatureAction(data, String(p.action || ''), p);
    else if (kind === 'ajax/settings.php') {
      if (Array.isArray(p.wallet_initial_balances)) {
        for (const row of p.wallet_initial_balances) { const w = walletById(data, row.id); if (w) Object.assign(w, { initial_balance: money(row.initial_balance), reserved_balance: money(row.reserved_balance), minimum_balance: money(row.minimum_balance) }); }
      }
      if (p.daily_budget && typeof p.daily_budget === 'object') { data.settings = data.settings || {}; data.settings.daily_budget = clone(p.daily_budget); }
      result = true;
    } else if (kind === 'ajax/edit_transaction.php') result = updateTransactionLocal(data, p);
    else if (kind === 'ajax/delete_transaction.php') { const id = Number(p.id); const before = removeTxAndRelated(data, id); if (!before) throw new Error('Transaksi tidak ditemukan.'); result = true; }
    else if (kind === 'ajax/finance.php') {
      const action = String(p.confirmation_action || '');
      if (action === 'cancel') { data.pending_chat_confirmation = null; addChat(data, 'user', 'Batal transaksi'); const reply = 'Baik, transaksi dibatalkan dan tidak mengubah saldo.'; addChat(data, 'assistant', reply); result = { reply, transactions: [], offline_draft_ids: [] }; }
      else if (action === 'confirm') {
        const pending = data.pending_chat_confirmation; if (!pending || Number(pending.id) !== Number(p.confirmation_id)) throw new Error('Konfirmasi transaksi offline sudah tidak tersedia.');
        const effectiveDrafts = (pending.drafts || []).map((draft, index) => {
          const overrides = Array.isArray(p.overrides) ? (p.overrides[index] || {}) : {};
          return { ...clone(draft), ...clone(overrides), attachment: draft.attachment || null, ocr: draft.ocr || null, source: draft.source || 'chat' };
        });
        effectiveDrafts.forEach((draft, index) => {
          if (!draft.admin_fee_auto_wallet) return;
          const parentIndex = Number(draft.admin_fee_parent_index);
          const parent = Number.isInteger(parentIndex) ? effectiveDrafts[parentIndex] : null;
          if (!parent) throw new Error('Transaksi utama untuk biaya admin tidak ditemukan.');
          const linkedWalletId = parent.type === 'transfer' ? Number(parent.from_wallet_id || parent.wallet_id || 0) : Number(parent.wallet_id || parent.from_wallet_id || 0);
          if (!walletById(data, linkedWalletId)) throw new Error('Dompet biaya admin tidak valid.');
          draft.wallet_id = linkedWalletId;
        });
        const saved = [];
        effectiveDrafts.forEach((draft) => {
          const x = makeTx(data, draft);
          x.offline_pending = true;
          saved.push(x);
        });
        data.pending_chat_confirmation = null; addChat(data, 'user', 'Simpan transaksi'); const reply = `Transaksi berhasil disimpan offline. ${saved.length} transaksi masuk daftar dan berstatus “Belum sinkron”.`; addChat(data, 'assistant', reply); result = { reply, transactions: saved, offline_draft_ids: saved.map((x) => Number(x.id)), local_transaction_ids: saved.map((x) => Number(x.id)) };
      } else {
        const message = String(p.message || '');
        const photo = p.photo instanceof Blob ? p.photo : null;
        const ocr = (p.ocr_text || p.ocr_amount) ? { text: String(p.ocr_text || ''), amount: money(p.ocr_amount), score: Number(p.ocr_score || 0), line: String(p.ocr_line || ''), confidence: Number(p.ocr_confidence || 0) } : null;
        const attachment = photo ? makeLocalAttachment(photo, p.photoName || photo.name || 'receipt.jpg') : null;
        addChat(data, 'user', message || (photo ? '📷 Foto nota' : ''), attachment);
        const r = localAssistant(data, message, { photo, photoName: p.photoName || photo?.name, image_mode: p.image_mode, attachment, ocr, ocr_text: p.ocr_text, ocr_amount: p.ocr_amount, ocr_score: p.ocr_score, ocr_line: p.ocr_line, ocr_confidence: p.ocr_confidence });
        const lastChat = data.chats?.[data.chats.length - 1];
        if (!(lastChat && lastChat.role === 'assistant' && lastChat.message === String(r.reply || ''))) addChat(data, 'assistant', r.reply);
        result = { reply: r.reply, transactions: r.saved || [], offline_draft_ids: [], local_transaction_ids: (r.saved || []).map((x) => Number(x.id || 0)) };
      }
    } else if (kind === 'ajax/notifications.php') {
      const center = notificationSnapshot(bundle); center.notifications = Array.isArray(center.notifications) ? center.notifications : [];
      if (p.action === 'mark_all_read') center.notifications.forEach((n) => { n.read_at = new Date().toISOString(); });
      else if (p.action === 'mark_read') { const ids = Array.isArray(p.ids) ? p.ids.map(Number) : []; center.notifications.forEach((n) => { if (ids.includes(Number(n.id))) n.read_at = new Date().toISOString(); }); }
      else if (p.action === 'create_warning') center.notifications.unshift({ id: nextLocalId(data, 'chats'), type: p.kind || 'warning', title: p.title || 'Peringatan', message: p.message || '', created_at: new Date().toISOString(), payload: { warning_key: p.key || '' } });
      center.unread_count = center.notifications.filter((n) => !n.read_at).length; bundle.notification_center = center; result = center;
    } else if (kind === 'ajax/email_notifications.php') {
      recordOfflineServiceAction(data, 'email_notification', p);
      result = { queued: true, message: 'Pemberitahuan email dicatat di perangkat dan akan dikirim saat internet kembali.' };
    } else if (kind === 'ajax/subscription.php') {
      recordOfflineServiceAction(data, 'subscription', p);
      result = { queued: true, message: 'Permintaan Premium disimpan di perangkat dan akan diproses saat internet kembali.' };
    } else if (kind === 'ajax/learning.php') {
      data.learning = data.learning || { rules: [], pending: null };
      data.learning.rules = Array.isArray(data.learning.rules) ? data.learning.rules : [];
      const action = String(p.action || '');
      if (action === 'save') {
        const phrase = String(p.phrase || '').trim();
        const response = String(p.response || '').trim();
        if (!phrase || !response) throw new Error('Frasa dan jawaban AI wajib diisi.');
        const existing = data.learning.rules.find((r) => String(r.phrase || '').toLocaleLowerCase('id-ID') === phrase.toLocaleLowerCase('id-ID'));
        if (existing) { existing.response = response; existing.updated_at = new Date().toISOString(); existing.offline_pending = true; result = existing; }
        else { const rule = { id: nextLocalId(data, 'audit_log'), phrase, response, created_at: new Date().toISOString(), offline_pending: true }; data.learning.rules.push(rule); result = rule; }
      } else if (action === 'delete') {
        data.learning.rules = data.learning.rules.filter((r) => Number(r.id) !== Number(p.id));
        result = true;
      } else if (action === 'cancel') {
        data.learning.pending = null; result = true;
      } else {
        result = { rules: clone(data.learning.rules), pending: clone(data.learning.pending || null) };
      }
    } else if (kind === 'ajax/delete_message.php') {
      const id = String(p.id || ''); data.chats = data.chats.filter((c) => String(c.id) !== id); result = true;
    } else {
      throw new Error('Fitur ini membutuhkan koneksi internet dan tidak aman untuk disimpan sebagai operasi offline.');
    }

    const nextBundle = clone(bundle); nextBundle.features = buildFeatures(data, nextBundle); nextBundle.summary = buildSummary(data); const nextState = dashboard(nextBundle, data, nextBundle.features, nextBundle.summary); nextBundle.state = nextState;
    if (kind === 'ajax/finance.php') {
      nextState.daily_budget = dailyBudgetStatus(data); nextState.learning = clone(data.learning || bundle.learning || {});
    }
    await persist(nextBundle, data, nextState, nextBundle.features, nextBundle.summary, nextBundle.account, nextBundle.notification_center);
    if (kind === 'ajax/features.php') {
      const action = String(p.action || '');
      const entityByAction = { wallet_save: 'wallet', category_save: 'category', bill_save: 'bill', bill_pay: 'transaction', recurring_save: 'recurring', recurring_run: 'transaction', goal_save: 'goal', transaction_create: 'transaction', wallet_transfer: 'transaction', receivable_lend: 'transaction', receivable_repay: 'transaction', receivable_repayment: 'transaction', receivable_interest_add: 'interest', quick_capture: 'transaction_inbox', inbox_confirm: 'transaction', inbox_confirm_all: 'transaction' };
      const localOperation = clone(data.meta?.local_last_operation || {});
      const arrayTransactionIds = Array.isArray(result) ? result.map((x) => Number(x?.id || 0)).filter(Boolean) : [];
      const objectTransactionId = result && typeof result === 'object' && !Array.isArray(result) ? Number(result.id || 0) : 0;
      return { ok: true, result, features: nextBundle.features, summary: nextBundle.summary, account: nextBundle.account, offline_local: true, offline_local_id: objectTransactionId, offline_entity: entityByAction[action] || '', offline_draft_ids: Array.isArray(result?.confirmed) ? result.confirmed.map((x) => Number(x.id || 0)) : [], local_transaction_ids: Array.isArray(result?.confirmed) ? result.confirmed.map((x) => Number(x.transaction_id || 0)) : (Array.isArray(localOperation.transaction_ids) && localOperation.transaction_ids.length ? localOperation.transaction_ids.map(Number) : arrayTransactionIds) };
    }
    if (kind === 'ajax/settings.php') return { ok: true, summary: nextBundle.summary, daily_budget: dailyBudgetStatus(data), daily_budget_settings: clone(data.settings?.daily_budget || {}), features: nextBundle.features, offline_local: true };
    if (kind === 'ajax/edit_transaction.php') return { ok: true, transaction: result, summary: nextBundle.summary, features: nextBundle.features, offline_local: true };
    if (kind === 'ajax/delete_transaction.php') return { ok: true, summary: nextBundle.summary, deleted: true, offline_local: true };
    if (kind === 'ajax/notifications.php') return { ok: true, ...result, offline_local: true };
    if (kind === 'ajax/email_notifications.php') return { ok: true, ...result, offline_local: true };
    if (kind === 'ajax/subscription.php') return { ok: true, ...result, account: clone(nextBundle.account || {}), offline_local: true };
    if (kind === 'ajax/delete_message.php') return { ok: true, offline_local: true };
    if (kind === 'ajax/learning.php') return { ok: true, rules: clone(nextBundle.learning?.rules || data.learning?.rules || []), pending: clone(nextBundle.learning?.pending || data.learning?.pending || null), offline_local: true };
    if (kind === 'ajax/finance.php') return { ok: true, reply: result.reply, transactions: result.transactions || [], summary: nextBundle.summary, pending_confirmation: nextBundle.features.pending_confirmation, daily_budget: dailyBudgetStatus(data), learning: clone(nextBundle.learning || {}), offline_draft_ids: result.offline_draft_ids || [], local_transaction_ids: (result.transactions || []).map((x) => Number(x.id || 0)).filter(Boolean), offline_local: true };
    return { ok: true, offline_local: true };
  }

  function entityMapRoot(maps, entity) {
    maps[entity] = maps[entity] || {};
    return maps[entity];
  }

  function mappedId(maps, entity, value) {
    const n = Number(value);
    if (!Number.isFinite(n) || n >= 0) return value;
    return entityMapRoot(maps, entity)[String(n)] ?? value;
  }

  function rewritePayloadIds(payload, maps, endpoint) {
    const p = clone(payload || {});
    const action = String(p.action || '');
    const rewrite = (key, entity) => {
      if (Object.prototype.hasOwnProperty.call(p, key)) p[key] = mappedId(maps, entity, p[key]);
    };
    const rewriteWalletFields = (obj) => {
      ['wallet_id','from_wallet_id','to_wallet_id','fee_wallet_id'].forEach((k) => { if (Object.prototype.hasOwnProperty.call(obj, k)) obj[k] = mappedId(maps, 'wallet', obj[k]); });
      if (Array.isArray(obj.wallet_ids)) obj.wallet_ids = obj.wallet_ids.map((x) => mappedId(maps, 'wallet', x));
    };
    if (/transaction|delete_transaction|edit_transaction/i.test(endpoint)) rewrite('id', 'transaction');
    if (/delete_message/i.test(endpoint)) { /* chat ids are not reconciled across server/local; local delete remains cosmetic */ }
    if (action === 'wallet_save' || action === 'wallet_archive' || action === 'wallet_reorder' || action === 'wallet_transfer') rewrite('id','wallet');
    if (action === 'wallet_transfer' || action === 'transaction_create' || action === 'bill_pay' || action === 'receivable_lend' || action === 'receivable_repay') rewriteWalletFields(p);
    if (action === 'category_save' || action === 'category_archive' || action === 'monthly_budget_set') rewrite('category_id','category');
    if (action === 'bill_save' || action === 'bill_delete' || action === 'bill_pay') rewrite('id','bill');
    if (action === 'bill_pay') rewrite('bill_id','bill');
    if (action === 'recurring_save' || action === 'recurring_delete') rewrite('id','recurring');
    if (action === 'goal_save' || action === 'goal_delete' || action === 'goal_contribute') rewrite('id','goal');
    if (action === 'history_undo') rewrite('id','audit');
    if (action === 'receivable_interest_add') { rewrite('lend_id','transaction'); rewrite('id','interest'); }
    if (action === 'receivable_repay' || action === 'receivable_repayment') rewrite('lend_id','transaction');
    if (action === 'receivable_interest_delete') rewrite('id','interest');
    if (action === 'receivable_delete') rewrite('id','transaction');
    if (action === 'inbox_confirm' || action === 'inbox_dismiss') rewrite('id','transaction_inbox');
    if (action === 'inbox_confirm_all' && Array.isArray(p.ids)) p.ids = p.ids.map((x) => mappedId(maps, 'transaction_inbox', x));
    if (action === 'history_undo') rewrite('id','audit');
    if (action === 'confirm') {
      p.confirmation_id = mappedId(maps, 'pending_confirmation', p.confirmation_id);
      if (Array.isArray(p.overrides)) p.overrides = p.overrides.map((x) => { const q = clone(x); rewriteWalletFields(q); if (q.wallet_id != null) q.wallet_id = mappedId(maps, 'wallet', q.wallet_id); if (q.from_wallet_id != null) q.from_wallet_id = mappedId(maps, 'wallet', q.from_wallet_id); if (q.to_wallet_id != null) q.to_wallet_id = mappedId(maps, 'wallet', q.to_wallet_id); if (q.bill_id != null) q.bill_id = mappedId(maps, 'bill', q.bill_id); if (q.id != null) q.id = mappedId(maps, 'transaction', q.id); return q; });
    }
    rewriteWalletFields(p);
    return p;
  }

  async function prepareSyncRecord(row) {
    const maps = (await DB().getMeta('id_maps', {})) || {};
    const send = { ...row, body: row.body ? clone(row.body) : row.body };
    if (!send.body || send.method === 'GET') return send;
    if (send.body.type === 'text') {
      try {
        const original = JSON.parse(send.body.value || '{}');
        const endpoint = cleanUrl(send.url); const rewritten = rewritePayloadIds(original, maps, endpoint);
        if (/ajax\/(edit_transaction|delete_transaction)\.php/i.test(endpoint) && Number(original.id || 0) < 0 && Number(rewritten.id || 0) > 0) delete rewritten.expected_version;
        send.body.value = JSON.stringify(rewritten);
      } catch (_) {}
    } else if (send.body.type === 'formdata') {
      const obj = {};
      for (const e of (send.body.value || [])) if (!e.is_blob) obj[e.key] = e.value;
      const endpoint = cleanUrl(send.url); const rewritten = rewritePayloadIds(obj, maps, endpoint);
      if (/ajax\/(edit_transaction|delete_transaction)\.php/i.test(endpoint) && Number(obj.id || 0) < 0 && Number(rewritten.id || 0) > 0) delete rewritten.expected_version;
      send.body.value = (send.body.value || []).map((e) => e.is_blob ? e : ({ ...e, value: Object.prototype.hasOwnProperty.call(rewritten, e.key) ? String(rewritten[e.key] ?? '') : e.value }));
    }
    return send;
  }

  async function reconcileServerResult(row, json) {
    try {
      const full = await DB().getSnapshot('offline_full'); if (!full) return;
      const data = clone(full.data || {});
      const body = row?.body || {};
      let payload = {};
      if (body.type === 'text') { try { payload = JSON.parse(body.value || '{}'); } catch (_) {} }
      else if (body.type === 'formdata') { for (const e of (body.value || [])) if (!e.is_blob) payload[e.key] = e.value; }
      const action = String(payload.action || '');
      const serviceClientOpId = String(payload.client_op_id || row?.id || '');
      const maps = (await DB().getMeta('id_maps', {})) || {};
      const serverResult = json?.result;
      const serverRows = [];
      if (serverResult && Array.isArray(serverResult)) serverRows.push(...serverResult); else if (serverResult && typeof serverResult === 'object') serverRows.push(serverResult);
      if (Array.isArray(json?.transactions)) serverRows.push(...json.transactions);
      if (json?.transaction && typeof json.transaction === 'object') serverRows.push(json.transaction);
      if (json?.created_transaction && typeof json.created_transaction === 'object') serverRows.push(json.created_transaction);

      const entityByAction = { wallet_save: 'wallet', category_save: 'category', bill_save: 'bill', bill_pay: 'transaction', recurring_save: 'recurring', recurring_run: 'transaction', goal_save: 'goal', transaction_create: 'transaction', wallet_transfer: 'transaction', receivable_lend: 'transaction', receivable_repay: 'transaction', receivable_repayment: 'transaction', receivable_interest_add: 'interest', quick_capture: 'transaction_inbox', inbox_confirm: 'transaction', inbox_confirm_all: 'transaction' };
      const entity = String(row.preview?.offline_entity || entityByAction[action] || '');
      const localId = Number(row.preview?.offline_local_id || 0);
      const serverId = serverRows[0] && Number(serverRows[0].id || 0);
      if (entity && localId < 0 && serverId > 0) {
        entityMapRoot(maps, entity)[String(localId)] = serverId;
        await DB().saveMeta('id_maps', maps);
        const collection = { wallet:'wallets', category:'categories', bill:'bills', recurring:'recurring', goal:'goals', interest:null, transaction_inbox:null }[entity];
        if (collection && Array.isArray(data[collection])) {
          data[collection] = data[collection].map((x) => Number(x.id) === localId ? { ...clone(serverRows[0]), offline_pending: false } : x);
        }
        if (entity === 'transaction' && Array.isArray(data.transactions)) data.transactions = data.transactions.map((x) => Number(x.id) === localId ? { ...clone(serverRows[0]), offline_pending: false } : x);
      }

      if (entity === 'transaction' && Array.isArray(row.preview?.local_transaction_ids) && row.preview.local_transaction_ids.length && Array.isArray(serverRows) && serverRows.length) {
        row.preview.local_transaction_ids.map(Number).forEach((localTxId, index) => {
          const remote = serverRows[index]; if (localTxId >= 0 || !remote?.id) return;
          const remoteId = Number(remote.id);
          if (remoteId <= 0) return;
          entityMapRoot(maps, 'transaction')[String(localTxId)] = remoteId;
          if (Array.isArray(data.transactions)) data.transactions = data.transactions.map((x) => Number(x.id) === localTxId ? { ...clone(remote), offline_pending: false } : x);
        });
        await DB().saveMeta('id_maps', maps);
      }

      if (action === 'finance_message_marker') { /* reserved */ }
      if (entity === 'transaction_inbox' && localId < 0 && serverId > 0) {
        entityMapRoot(maps, 'transaction_inbox')[String(localId)] = serverId;
        const box = inboxData(data);
        box.items = box.items.map((x) => Number(x.id) === localId ? { ...clone(serverRows[0]), offline_pending: false } : x);
        box.pending_count = box.items.filter((x) => x.status === 'pending').length;
        await DB().saveMeta('id_maps', maps);
      }
      if ((action === 'inbox_confirm' || action === 'inbox_confirm_all') && (row.preview?.offline_draft_ids?.length || row.preview?.local_transaction_ids?.length)) {
        const box = inboxData(data);
        const localDraftIds = Array.isArray(row.preview?.offline_draft_ids) ? row.preview.offline_draft_ids.map(Number) : [];
        const localTxIds = Array.isArray(row.preview?.local_transaction_ids) ? row.preview.local_transaction_ids.map(Number) : [];
        const confirmed = Array.isArray(json?.result?.confirmed) ? json.result.confirmed : [];
        confirmed.forEach((pair, index) => {
          const draftId = localDraftIds[index] || Number(pair.id || 0);
          const txId = localTxIds[index] || Number(pair.transaction_id || 0);
          const remoteDraftId = Number(pair.id || 0);
          const remoteTxId = Number(pair.transaction_id || 0);
          if (draftId < 0 && remoteDraftId > 0) entityMapRoot(maps, 'transaction_inbox')[String(draftId)] = remoteDraftId;
          if (txId < 0 && remoteTxId > 0) entityMapRoot(maps, 'transaction')[String(txId)] = remoteTxId;
        });
        if (Array.isArray(data.transactions)) {
          for (let i = 0; i < localTxIds.length; i++) {
            const localId = Number(localTxIds[i] || 0); const remoteId = Number(confirmed[i]?.transaction_id || 0);
            if (localId < 0 && remoteId > 0) {
              const serverTx = Array.isArray(serverRows) ? serverRows.find((x) => Number(x.id) === remoteId) : null;
              if (serverTx) data.transactions = data.transactions.map((x) => Number(x.id) === localId ? { ...clone(serverTx), offline_pending: false } : x);
            }
          }
        }
        localDraftIds.forEach((draftId) => { const item = box.items.find((x) => Number(x.id) === draftId); if (item) { item.status = 'resolved'; item.offline_pending = false; } });
        box.pending_count = box.items.filter((x) => x.status === 'pending').length;
        await DB().saveMeta('id_maps', maps);
      }

      if (!payload.confirmation_action && row.preview?.local_pending_id && json?.pending_confirmation?.id) {
        const localPending = Number(row.preview.local_pending_id);
        const remotePending = Number(json.pending_confirmation.id);
        if (localPending < 0 && remotePending > 0) {
          entityMapRoot(maps, 'pending_confirmation')[String(localPending)] = remotePending;
          await DB().saveMeta('id_maps', maps);
        }
      }
      if (payload.confirmation_action === 'confirm' && Array.isArray(row.preview?.offline_draft_ids) && Array.isArray(json?.transactions)) {
        const ids = row.preview.offline_draft_ids.map(Number);
        json.transactions.forEach((serverTx, i) => {
          const localId = ids[i]; if (!localId || !serverTx?.id) return;
          entityMapRoot(maps, 'transaction')[String(localId)] = Number(serverTx.id);
          data.transactions = (data.transactions || []).map((x) => Number(x.id) === localId ? { ...clone(serverTx), offline_pending: false } : x);
        });
        await DB().saveMeta('id_maps', maps);
      }

      if (data.meta) {
        delete data.meta.local_last_operation;
        const serviceKey = (action === 'email_notification') ? 'email_notification'
          : (['start_trial','create_invoice','upload_proof'].includes(action) ? 'subscription' : '');
        if (serviceKey) {
          data.meta.offline_service_actions = (data.meta.offline_service_actions || []).filter((item) => !(String(item.client_op_id || '') === serviceClientOpId && String(item.key || '') === serviceKey));
        }
      }
      const features = buildFeatures(data, full), summary = buildSummary(data), next = dashboard(full, data, features, summary);
      next.offline_mode = !navigator.onLine;
      await persist({ ...full, features, summary, state: next }, data, next, features, summary, full.account, full.notification_center);
    } catch (_) {}
  }

  async function markLocalApplied(rowId, localApplied = true) {
    const rows = await DB().listQueue(); const row = rows.find((x) => x.id === rowId); if (!row) return; row.preview = { ...(row.preview || {}), local_applied: localApplied }; await DB().updateQueue(row);
  }

  async function previewFinanceChat(options = {}) {
    if (!CFG().userId) throw new Error('Akun offline belum dikenali.');
    const { bundle, data } = await localState();
    const p = requestBodyToObject(options);
    let photo = null;
    let photoName = '';
    let ocr = null;
    if (options.body instanceof FormData) {
      const photoField = options.body.get('photo');
      if (photoField instanceof Blob && photoField.size > 0) {
        photo = photoField;
        photoName = photoField.name || 'receipt.jpg';
      }
    }
    const msg = String(p.message || '').trim();
    if (p.ocr_text || p.ocr_amount) {
      ocr = {
        text: String(p.ocr_text || ''),
        amount: money(p.ocr_amount),
        score: Number(p.ocr_score || 0),
        line: String(p.ocr_line || ''),
        confidence: Number(p.ocr_confidence || 0),
      };
    }
    const attachment = photo ? makeLocalAttachment(photo, photoName) : null;
    const result = localAssistant(data, msg, {
      photo,
      photoName,
      image_mode: p.image_mode,
      attachment,
      ocr,
      ocr_text: p.ocr_text,
      ocr_amount: p.ocr_amount,
      ocr_score: p.ocr_score,
      ocr_line: p.ocr_line,
      ocr_confidence: p.ocr_confidence,
      preview_only: true,
    });
    return { reply: String(result?.reply || '') };
  }

  window.FinanceOfflineEngine = { handleRequest, previewFinanceChat, reconcileServerResult, prepareSyncRecord, markLocalApplied, shouldPreferLocal: () => !navigator.onLine };
})();
