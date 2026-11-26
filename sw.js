/**
 * Graphicafix PWA Service Worker
 * Enables Chrome Android & Desktop WebAPK installation while keeping dynamic PHP sessions 100% live.
 */
const CACHE_NAME = 'graphicafix-pwa-v1';
const OFFLINE_URL = './offline.html';
const PRECACHE_ASSETS = [
  OFFLINE_URL,
  './assets/images/pwa/icon-192.png',
  './assets/images/pwa/icon-512.png',
  './assets/images/pwa/icon-maskable-192.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_ASSETS))
      .then(() => self.skipWaiting())
      .catch(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Never intercept non-GET requests (POST forms, AJAX mutations, uploads)
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // Only handle same-origin requests
  if (url.origin !== self.location.origin) return;

  // Never cache API endpoints, actions, or dynamic JSON/PDF streams
  if (
    url.pathname.includes('/api/') ||
    url.pathname.includes('_api.php') ||
    url.pathname.includes('_action.php') ||
    url.pathname.includes('generate_') ||
    url.pathname.includes('logout')
  ) {
    return;
  }

  // Page navigations: strictly Network-First with graceful offline fallback
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() => caches.match(OFFLINE_URL))
    );
    return;
  }

  // Static PWA icons / offline page: Cache-First with network fallback
  if (url.pathname.includes('/assets/images/pwa/')) {
    event.respondWith(
      caches.match(req).then((cached) => cached || fetch(req))
    );
    return;
  }
});
