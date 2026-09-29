const CACHE = 'finance-shell-v105';
const OFFLINE_SHELL_KEY = './__offline_shell__';
const OFFLINE_ROOT_KEY = './';
const OFFLINE_INDEX_KEY = './index.php';
const STATIC_ASSETS = [
  './assets/icon.webp',
  './assets/style.css',
  './assets/app.js',
  './assets/offline-store.js',
  './manifest.json'
];

async function cacheStaticAsset(cache, path) {
  try {
    const request = new Request(path, { cache: 'reload', credentials: 'same-origin' });
    const response = await fetch(request);
    if (response && response.ok) await cache.put(path, response.clone());
  } catch (_) {}
}

self.addEventListener('install', event => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE);
    await Promise.all(STATIC_ASSETS.map(path => cacheStaticAsset(cache, path)));
  })());
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys
        .filter(key => key.startsWith('finance-shell-') && key !== CACHE)
        .map(key => caches.delete(key))
    )).then(() => self.clients.claim())
  );
});

function unlockedShellResponse(text, original) {
  const headers = new Headers(original?.headers || {});
  headers.set('Content-Type', 'text/html; charset=utf-8');
  headers.set('X-Finance-Offline-Shell', '1');
  headers.set('Cache-Control', 'no-store');
  return new Response(text, { status: 200, statusText: 'OK', headers });
}

async function cacheUnlockedShell(response) {
  try {
    if (!response || !response.ok) return false;
    const text = await response.clone().text();
    if (!text.includes('data-offline-shell="1"')) return false;
    const cache = await caches.open(CACHE);
    const cached = unlockedShellResponse(text, response);
    // Simpan satu shell sintetis dan dua URL navigasi umum. Ini membuat cold-start
    // Android lebih tahan ketika WebView dibuka tanpa jaringan.
    await Promise.all([
      cache.put(OFFLINE_SHELL_KEY, cached.clone()),
      cache.put(OFFLINE_ROOT_KEY, cached.clone()),
      cache.put(OFFLINE_INDEX_KEY, cached.clone()),
    ]);
    return true;
  } catch (_) {
    return false;
  }
}

async function clearOfflineShell() {
  try {
    const cache = await caches.open(CACHE);
    await Promise.all([
      cache.delete(OFFLINE_SHELL_KEY),
      cache.delete(OFFLINE_ROOT_KEY),
      cache.delete(OFFLINE_INDEX_KEY),
    ]);
    return true;
  } catch (_) {
    return false;
  }
}

async function cachedOfflineShell() {
  const cache = await caches.open(CACHE);
  return (await cache.match(OFFLINE_SHELL_KEY))
    || (await cache.match(OFFLINE_ROOT_KEY))
    || (await cache.match(OFFLINE_INDEX_KEY));
}

self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);

  // Cache runtime OCR resources setelah pernah dipakai online supaya peluang OCR offline lebih besar.
  if (request.method === 'GET' && url.origin !== self.location.origin) {
    const ocrHost = /(^|\.)jsdelivr\.net$|(^|\.)projectnaptha\.com$|(^|\.)githubusercontent\.com$/i.test(url.hostname);
    if (ocrHost) {
      event.respondWith(
        caches.open(CACHE).then(async cache => {
          const cached = await cache.match(request);
          if (cached) return cached;
          try {
            const response = await fetch(request);
            if (response) await cache.put(request, response.clone()).catch(() => {});
            return response;
          } catch (err) {
            throw err;
          }
        })
      );
    }
    return;
  }

  if (url.origin !== self.location.origin) return;

  // Navigasi: network-first. Bila perangkat benar-benar offline, kembalikan shell
  // akun terakhir yang sudah berhasil login + unlock dan pernah dimuat online.
  if (request.method === 'GET' && request.mode === 'navigate') {
    event.respondWith((async () => {
      try {
        const response = await fetch(request, { cache: 'no-store' });
        if (response && response.ok) await cacheUnlockedShell(response.clone());
        return response;
      } catch (_) {
        const shell = await cachedOfflineShell();
        if (shell) return shell;
        return new Response(
          '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title></head><body style="font-family:system-ui;padding:30px"><h2>Aplikasi belum siap offline</h2><p>Hubungkan internet dan login ke aplikasi minimal satu kali pada perangkat ini. Setelah data tersimpan, aplikasi dapat dibuka kembali tanpa jaringan.</p></body></html>',
          { headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } }
        );
      }
    })());
    return;
  }

  // AJAX/API ditangani oleh aplikasi + IndexedDB. Response API tidak dicache.
  const route = String(url.searchParams.get('r') || '').toLowerCase();
  const isLegacyApiRoute = route === 'legacy-api/bridge' || route.startsWith('legacy-api/');
  if (url.pathname.includes('/ajax/') || isLegacyApiRoute) return;

  if (request.method !== 'GET') return;

  // CSS/JS network-first agar update terbaca, namun fallback ke cache saat offline.
  if (url.pathname.endsWith('.css') || url.pathname.endsWith('.js')) {
    event.respondWith(
      fetch(request, { cache: 'no-store' })
        .then(response => {
          if (response && response.ok) {
            const clone = response.clone();
            caches.open(CACHE).then(cache => cache.put(request, clone));
          }
          return response;
        })
        .catch(async () => {
          const cache = await caches.open(CACHE);
          return (await cache.match(request)) || (await cache.match(url.pathname.replace(/^\//, './')));
        })
    );
    return;
  }

  event.respondWith(
    caches.open(CACHE).then(async cache => {
      const cached = await cache.match(request);
      if (cached) return cached;
      const response = await fetch(request);
      if (response && response.ok) await cache.put(request, response.clone()).catch(() => {});
      return response;
    })
  );
});

self.addEventListener('message', event => {
  if (event.data?.type === 'SKIP_WAITING') self.skipWaiting();

  if (event.data?.type === 'CACHE_CURRENT_SHELL' && event.data.url) {
    event.waitUntil((async () => {
      let ok = false;
      try {
        const response = await fetch(event.data.url, { credentials: 'include', cache: 'no-store' });
        ok = await cacheUnlockedShell(response);
      } catch (_) {}
      try { event.ports?.[0]?.postMessage({ ok }); } catch (_) {}
    })());
  }

  if (event.data?.type === 'HAS_OFFLINE_SHELL') {
    event.waitUntil((async () => {
      let ok = false;
      try { ok = !!(await cachedOfflineShell()); } catch (_) {}
      try { event.ports?.[0]?.postMessage({ ok }); } catch (_) {}
    })());
  }

  if (event.data?.type === 'CLEAR_OFFLINE_SHELL') {
    event.waitUntil((async () => {
      const ok = await clearOfflineShell();
      try { event.ports?.[0]?.postMessage({ ok }); } catch (_) {}
    })());
  }
});
