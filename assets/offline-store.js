(() => {
  'use strict';

  const DB_NAME = 'charlie_finance_offline_v1';
  const DB_VERSION = 1;
  const STORE_SNAPSHOTS = 'snapshots';
  const STORE_QUEUE = 'queue';
  const STORE_META = 'meta';

  const cfg = () => window.FINANCE_APP || {};
  const currentUserId = () => Number(cfg().userId || 0);
  const currentSyncBase = () => String(cfg().syncBase || location.origin).replace(/\/$/, '');

  let dbPromise = null;
  let syncing = false;
  let listeners = [];

  function openDb() {
    if (!('indexedDB' in window)) return Promise.reject(new Error('IndexedDB tidak tersedia.'));
    if (dbPromise) return dbPromise;
    dbPromise = new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = () => {
        const db = req.result;
        if (!db.objectStoreNames.contains(STORE_SNAPSHOTS)) db.createObjectStore(STORE_SNAPSHOTS, { keyPath: 'key' });
        if (!db.objectStoreNames.contains(STORE_QUEUE)) {
          const store = db.createObjectStore(STORE_QUEUE, { keyPath: 'id' });
          store.createIndex('user_id', 'user_id', { unique: false });
          store.createIndex('created_at', 'created_at', { unique: false });
        }
        if (!db.objectStoreNames.contains(STORE_META)) db.createObjectStore(STORE_META, { keyPath: 'key' });
      };
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error || new Error('Gagal membuka penyimpanan offline.'));
    });
    return dbPromise;
  }

  async function tx(storeName, mode, runner) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction(storeName, mode);
      const store = transaction.objectStore(storeName);
      let result;
      try { result = runner(store, transaction); } catch (err) { reject(err); return; }
      transaction.oncomplete = () => resolve(result);
      transaction.onerror = () => reject(transaction.error || new Error('Penyimpanan offline gagal.'));
      transaction.onabort = () => reject(transaction.error || new Error('Penyimpanan offline dibatalkan.'));
    });
  }

  function reqPromise(req) {
    return new Promise((resolve, reject) => {
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error || new Error('Operasi IndexedDB gagal.'));
    });
  }

  function snapshotKey(name, userId = currentUserId()) {
    return `u:${userId}:${name}`;
  }

  async function saveSnapshot(name, value) {
    const userId = currentUserId();
    if (!userId) return;
    const db = await openDb();
    await new Promise((resolve, reject) => {
      const tr = db.transaction(STORE_SNAPSHOTS, 'readwrite');
      tr.objectStore(STORE_SNAPSHOTS).put({ key: snapshotKey(name, userId), user_id: userId, saved_at: Date.now(), value });
      tr.oncomplete = resolve;
      tr.onerror = () => reject(tr.error);
    });
  }

  async function getSnapshot(name) {
    const userId = currentUserId();
    if (!userId) return null;
    const db = await openDb();
    const tr = db.transaction(STORE_SNAPSHOTS, 'readonly');
    const row = await reqPromise(tr.objectStore(STORE_SNAPSHOTS).get(snapshotKey(name, userId)));
    return row ? row.value : null;
  }

  function uuid() {
    if (crypto?.randomUUID) return crypto.randomUUID();
    return 'op_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2) + '_' + Math.random().toString(36).slice(2);
  }

  function headersToObject(headers) {
    const out = {};
    if (!headers) return out;
    try {
      new Headers(headers).forEach((v, k) => { out[k] = v; });
    } catch (_) {}
    return out;
  }

  async function serializeBody(body) {
    if (!body) return { type: 'none', value: null };
    if (body instanceof FormData) {
      const entries = [];
      for (const [key, value] of body.entries()) {
        if (value instanceof Blob) {
          entries.push({ key, blob: value, filename: value.name || 'upload.bin', mime: value.type || 'application/octet-stream', is_blob: true });
        } else {
          entries.push({ key, value: String(value), is_blob: false });
        }
      }
      return { type: 'formdata', value: entries };
    }
    if (typeof body === 'string') return { type: 'text', value: body };
    if (body instanceof Blob) return { type: 'blob', value: body };
    return { type: 'text', value: String(body) };
  }

  function appendClientOp(bodyInfo, opId, headers) {
    if (bodyInfo.type === 'formdata') {
      if (!(bodyInfo.value || []).some(e => e.key === 'client_op_id')) bodyInfo.value.push({ key: 'client_op_id', value: opId, is_blob: false });
      return;
    }
    const contentType = String(headers['content-type'] || headers['Content-Type'] || '').toLowerCase();
    if (bodyInfo.type === 'text' && contentType.includes('application/json')) {
      try {
        const obj = JSON.parse(bodyInfo.value || '{}');
        if (obj && typeof obj === 'object' && !Array.isArray(obj)) {
          obj.client_op_id = opId;
          bodyInfo.value = JSON.stringify(obj);
        }
      } catch (_) {}
    }
  }

  async function queueRequest(url, options = {}, extra = {}) {
    const userId = currentUserId();
    if (!userId) throw new Error('Akun offline belum dikenali. Buka aplikasi sekali saat online terlebih dahulu.');
    const id = extra.clientOpId || uuid();
    const headers = headersToObject(options.headers);
    const body = await serializeBody(options.body);
    appendClientOp(body, id, headers);
    const record = {
      id,
      user_id: userId,
      url: String(url),
      method: String(options.method || 'POST').toUpperCase(),
      headers,
      body,
      kind: extra.kind || 'mutation',
      preview: extra.preview || null,
      created_at: Date.now(),
      attempts: 0,
      last_error: '',
      http_status: 0,
      conflict: false,
    };
    const db = await openDb();
    await new Promise((resolve, reject) => {
      const tr = db.transaction(STORE_QUEUE, 'readwrite');
      tr.objectStore(STORE_QUEUE).put(record);
      tr.oncomplete = resolve;
      tr.onerror = () => reject(tr.error);
    });
    emit();
    return record;
  }

  async function listQueue(userId = currentUserId()) {
    if (!userId) return [];
    const db = await openDb();
    const tr = db.transaction(STORE_QUEUE, 'readonly');
    const store = tr.objectStore(STORE_QUEUE);
    let rows;
    if (store.indexNames.contains('user_id')) rows = await reqPromise(store.index('user_id').getAll(IDBKeyRange.only(userId)));
    else rows = (await reqPromise(store.getAll())).filter(x => Number(x.user_id) === Number(userId));
    return (rows || []).sort((a, b) => Number(a.created_at || 0) - Number(b.created_at || 0));
  }

  async function deleteQueue(id) {
    const db = await openDb();
    await new Promise((resolve, reject) => {
      const tr = db.transaction(STORE_QUEUE, 'readwrite');
      tr.objectStore(STORE_QUEUE).delete(id);
      tr.oncomplete = resolve;
      tr.onerror = () => reject(tr.error);
    });
    emit();
  }

  async function updateQueue(record) {
    const db = await openDb();
    await new Promise((resolve, reject) => {
      const tr = db.transaction(STORE_QUEUE, 'readwrite');
      tr.objectStore(STORE_QUEUE).put(record);
      tr.oncomplete = resolve;
      tr.onerror = () => reject(tr.error);
    });
    emit();
  }

  function buildBody(record) {
    const body = record.body || { type: 'none', value: null };
    if (body.type === 'none') return undefined;
    if (body.type === 'text') return body.value;
    if (body.type === 'blob') return body.value;
    if (body.type === 'formdata') {
      const fd = new FormData();
      for (const e of (body.value || [])) {
        if (e.is_blob) fd.append(e.key, e.blob, e.filename || 'upload.bin');
        else fd.append(e.key, e.value ?? '');
      }
      return fd;
    }
    return undefined;
  }

  function buildHeaders(record) {
    const h = new Headers(record.headers || {});
    if (record.body?.type === 'formdata') h.delete('content-type');
    h.set('X-Offline-Sync', '1');
    h.set('X-Client-Op-Id', record.id);
    return h;
  }

  async function syncQueue() {
    if (syncing || !navigator.onLine) return { synced: 0, remaining: (await listQueue()).length, status: 'offline' };
    syncing = true;
    emit({ syncing: true });
    let synced = 0;
    let authRequired = false;
    try {
      const rows = await listQueue();
      for (const row of rows) {
        const target = /^https?:\/\//i.test(row.url) ? row.url : currentSyncBase() + '/' + String(row.url).replace(/^\//, '');
        try {
          const response = await fetch(target, {
            method: row.method || 'POST',
            headers: buildHeaders(row),
            body: buildBody(row),
            credentials: 'include',
            cache: 'no-store',
          });
          const raw = await response.text();
          let json = null;
          try { json = raw ? JSON.parse(raw) : null; } catch (_) {}
          if (response.status === 401) {
            authRequired = true;
            row.attempts = Number(row.attempts || 0) + 1;
            row.http_status = 401;
            row.conflict = false;
            row.last_error = 'Sesi online terkunci. Masukkan PIN untuk melanjutkan sinkronisasi.';
            await updateQueue(row);
            break;
          }
          if (!response.ok || (json && json.ok === false)) {
            row.attempts = Number(row.attempts || 0) + 1;
            row.http_status = Number(response.status || 0);
            row.conflict = response.status === 409;
            row.last_error = (json && json.error) ? String(json.error) : `HTTP ${response.status}`;
            await updateQueue(row);
            // 4xx selain auth biasanya data invalid; jangan hentikan item berikutnya.
            if (response.status >= 500) break;
            continue;
          }
          await deleteQueue(row.id);
          synced++;
        } catch (err) {
          row.attempts = Number(row.attempts || 0) + 1;
          row.http_status = 0;
          row.conflict = false;
          row.last_error = err?.message || 'Gagal terhubung ke server.';
          await updateQueue(row);
          break;
        }
      }
    } finally {
      syncing = false;
      emit({ syncing: false });
    }
    const left = await listQueue();
    const remaining = left.length;
    const conflicts = left.filter(row => row.conflict || Number(row.http_status || 0) === 409).length;
    const failed = left.filter(row => !(row.conflict || Number(row.http_status || 0) === 409) && Number(row.attempts || 0) > 0 && String(row.last_error || '').trim()).length;
    return { synced, remaining, failed, conflicts, status: authRequired ? 'auth_required' : (remaining ? 'pending' : 'synced') };
  }

  async function clearUserOfflineData(userId = currentUserId()) {
    if (!userId) return;
    const db = await openDb();
    const snapshots = await new Promise((resolve, reject) => {
      const tr = db.transaction(STORE_SNAPSHOTS, 'readonly');
      const req = tr.objectStore(STORE_SNAPSHOTS).getAll();
      req.onsuccess = () => resolve(req.result || []);
      req.onerror = () => reject(req.error);
    });
    const queue = await listQueue(userId);
    await new Promise((resolve, reject) => {
      const tr = db.transaction([STORE_SNAPSHOTS, STORE_QUEUE], 'readwrite');
      const s = tr.objectStore(STORE_SNAPSHOTS);
      const q = tr.objectStore(STORE_QUEUE);
      snapshots.filter(x => Number(x.user_id) === Number(userId)).forEach(x => s.delete(x.key));
      queue.forEach(x => q.delete(x.id));
      tr.oncomplete = resolve;
      tr.onerror = () => reject(tr.error);
    });
    emit();
  }

  async function status() {
    let rows = [];
    try { rows = await listQueue(); } catch (_) {}
    const pending = rows.length;
    const failed = rows.filter(row => !(row.conflict || Number(row.http_status || 0) === 409) && Number(row.attempts || 0) > 0 && String(row.last_error || '').trim()).length;
    const conflicts = rows.filter(row => row.conflict || Number(row.http_status || 0) === 409).length;
    return { online: navigator.onLine, pending, failed, conflicts, syncing };
  }

  function onStatus(fn) {
    listeners.push(fn);
    status().then(fn).catch(() => {});
    return () => { listeners = listeners.filter(x => x !== fn); };
  }

  function emit(extra = {}) {
    status().then(s => listeners.forEach(fn => { try { fn({ ...s, ...extra }); } catch (_) {} })).catch(() => {});
  }

  window.addEventListener('online', () => emit());
  window.addEventListener('offline', () => emit());

  window.FinanceOffline = {
    openDb,
    saveSnapshot,
    getSnapshot,
    queueRequest,
    listQueue,
    deleteQueue,
    updateQueue,
    syncQueue,
    status,
    onStatus,
    clearUserOfflineData,
    uuid,
    syncBase: currentSyncBase,
  };
})();
