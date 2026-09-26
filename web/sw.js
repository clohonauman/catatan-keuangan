const CACHE = 'finance-shell-v73';
const OFFLINE_SHELL_KEY = './__offline_shell__';
const STATIC_ASSETS = [
  './assets/icon.webp',
  './assets/style.css',
  './assets/app.js',
  './assets/offline-store.js',
  './manifest.json'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE)
      .then(cache => cache.addAll(STATIC_ASSETS.map(x => new Request(x, { cache: 'reload' }))))
      .catch(() => null)
  );
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

async function cacheUnlockedShell(response) {
  try {
    if (!response || !response.ok) return;
    const clone = response.clone();
    const text = await clone.text();
    if (!text.includes('data-offline-shell="1"')) return;
    const headers = new Headers(response.headers);
    headers.set('X-Finance-Offline-Shell', '1');
    const cached = new Response(text, { status: 200, statusText: 'OK', headers });
    const cache = await caches.open(CACHE);
    await cache.put(OFFLINE_SHELL_KEY, cached);
  } catch (_) {}
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

  // Navigasi: network-first. Saat offline gunakan halaman aplikasi terakhir yang berhasil dibuka dalam keadaan unlocked.
  if (request.method === 'GET' && request.mode === 'navigate') {
    event.respondWith((async () => {
      try {
        const response = await fetch(request, { cache: 'no-store' });
        cacheUnlockedShell(response.clone());
        return response;
      } catch (_) {
        const cache = await caches.open(CACHE);
        const shell = await cache.match(OFFLINE_SHELL_KEY);
        if (shell) return shell;
        const fallback = await cache.match('./index.php') || await cache.match('./');
        if (fallback) return fallback;
        return new Response(
          '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title></head><body style="font-family:system-ui;padding:30px"><h2>Aplikasi belum siap offline</h2><p>Buka dan login ke aplikasi minimal satu kali saat terhubung internet, lalu coba lagi.</p></body></html>',
          { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
      }
    })());
    return;
  }

  // AJAX/API ditangani langsung oleh aplikasi/IndexedDB. Jangan pernah cache response API.
  const route = String(url.searchParams.get('r') || '').toLowerCase();
  const isLegacyApiRoute = route === 'legacy-api/bridge' || route.startsWith('legacy-api/');
  if (url.pathname.includes('/ajax/') || isLegacyApiRoute) return;

  if (request.method !== 'GET') return;

  // CSS/JS network-first supaya update langsung terbaca, namun tetap punya fallback offline.
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
      try {
        const response = await fetch(event.data.url, { credentials: 'include', cache: 'no-store' });
        await cacheUnlockedShell(response);
      } catch (_) {}
    })());
  }
  if (event.data?.type === 'CLEAR_OFFLINE_SHELL') {
    event.waitUntil(caches.open(CACHE).then(cache => cache.delete(OFFLINE_SHELL_KEY)).catch(() => false));
  }
});
