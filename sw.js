const CACHE_NAME = 'lista-secenja-v48';
// Sličice (slike.js, ~700 KB) imaju svoj keš koji se NE briše pri svakoj novoj verziji —
// telefon ih skine jednom. Kad se slike menjaju: povećati broj ovde i ?v= u index.html.
const SLIKE_VER = 2;
const SLIKE_CACHE = 'ag-slike-v' + SLIKE_VER;
const SLIKE_URL = './slike.js?v=' + SLIKE_VER;
const STATIC_ASSETS = [
  './manifest.json',
  './icons/icon-192.png',
  './icons/icon-512.png'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS))
      // slike se dovlače unapred, ali greška ovde ne sme da spreči instalaciju
      .then(() => caches.open(SLIKE_CACHE).then(c => c.match(SLIKE_URL).then(m => m || c.add(SLIKE_URL))).catch(() => {}))
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME && k !== SLIKE_CACHE).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // API pozivi — NIKAD ne keširati, uvek direktno na server
  if (event.request.method !== 'GET' || url.pathname.includes('/api/')) {
    return; // browser sam obrađuje, bez keša
  }

  // Sličice — cache-first iz posebnog keša
  if (url.pathname.endsWith('/slike.js')) {
    event.respondWith(
      caches.open(SLIKE_CACHE).then(cache =>
        cache.match(event.request).then(cached => cached || fetch(event.request).then(response => {
          if (response && response.status === 200) cache.put(event.request, response.clone());
          return response;
        }))
      )
    );
    return;
  }

  // Network-first za HTML — uvek uzima novu verziju kad je online
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
          return response;
        })
        .catch(() => caches.match(event.request).then(m => m || caches.match('./index.html')))
    );
    return;
  }

  // Cache-first za ikone i ostale statične fajlove
  event.respondWith(
    caches.match(event.request).then(cached => {
      if (cached) return cached;
      return fetch(event.request).then(response => {
        if (!response || response.status !== 200 || response.type !== 'basic') return response;
        const clone = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        return response;
      }).catch(() => caches.match('./index.html'));
    })
  );
});
