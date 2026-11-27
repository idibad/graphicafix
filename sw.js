/**
 * Graphicafix PWA Service Worker (v2)
 * - Serves live pages when online (Network-First) and automatically caches visited pages
 * - Serves the cached version of visited pages (with CSS, logos, icons & fonts) when offline
 * - Falls back to offline.html (with inline logo) only for unvisited pages
 * - Automatically clears cached private pages on logout
 */
const SHELL_CACHE = 'graphicafix-shell-v2';
const PAGES_CACHE = 'graphicafix-pages-v2';
const MAX_CACHED_PAGES = 35;

const OFFLINE_URL = './offline.html';
const PRECACHE_SHELL = [
  OFFLINE_URL,
  './assets/images/pwa/icon-192.png',
  './assets/images/pwa/icon-512.png',
  './assets/images/pwa/icon-maskable-192.png',
  './assets/images/icon.png',
  './assets/images/logo-secondary.png',
  './assets/images/logo.png',
  './assets/css/bootstrap.css',
  './assets/css/style.css',
  './assets/css/dashboard.css',
  './assets/css/tasks_workspace.css',
  './assets/js/pwa-install.js'
];

const TRUSTED_CDN_HOSTS = [
  'cdnjs.cloudflare.com',
  'cdn.jsdelivr.net',
  'fonts.googleapis.com',
  'fonts.gstatic.com',
  'code.jquery.com'
];

function isStaticAssetPath(pathname) {
  return /\.(?:css|js|png|jpe?g|webp|svg|gif|ico|woff2?|ttf|eot)$/i.test(pathname);
}

function isAuthOrSensitivePath(pathname) {
  return /(?:login|logout|forgot_password|reset_password|\/api\/|_api\.php|_action\.php|generate_|download_)/i.test(pathname);
}

async function trimPagesCache(cache) {
  try {
    const keys = await cache.keys();
    if (keys.length > MAX_CACHED_PAGES) {
      const toDelete = keys.slice(0, keys.length - MAX_CACHED_PAGES);
      await Promise.all(toDelete.map((k) => cache.delete(k)));
    }
  } catch (e) {}
}

function stripCacheBuster(urlStr) {
  try {
    const u = new URL(urlStr);
    u.searchParams.delete('v');
    u.searchParams.delete('source');
    return u.toString();
  } catch (e) {
    return urlStr;
  }
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(SHELL_CACHE).then((cache) =>
      // Cache each asset individually so one missing optional file never aborts install
      Promise.all(
        PRECACHE_SHELL.map((url) =>
          cache.add(new Request(url, { cache: 'reload' })).catch(() => {})
        )
      )
    ).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  const keep = [SHELL_CACHE, PAGES_CACHE];
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((k) => !keep.includes(k)).map((k) => caches.delete(k))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Never intercept non-GET requests (POST forms, AJAX mutations, uploads)
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // 1. Trusted CDN assets (FontAwesome, Bootstrap, Boxicons, Fonts, jQuery): Stale-While-Revalidate
  if (url.origin !== self.location.origin) {
    if (TRUSTED_CDN_HOSTS.includes(url.hostname)) {
      event.respondWith(
        caches.open(SHELL_CACHE).then(async (cache) => {
          const cached = await cache.match(req);
          const networkPromise = fetch(req).then((res) => {
            if (res && (res.ok || res.type === 'opaque')) {
              cache.put(req, res.clone()).catch(() => {});
            }
            return res;
          }).catch(() => cached);
          return cached || networkPromise;
        })
      );
    }
    return;
  }

  // 2. Logout: immediately wipe cached private pages so nothing lingers on shared devices
  if (/logout(?:\.php)?$/i.test(url.pathname)) {
    event.waitUntil(caches.delete(PAGES_CACHE));
    return;
  }

  // 3. Never cache API endpoints, AJAX actions, PDF generators, or auth endpoints
  if (isAuthOrSensitivePath(url.pathname) && req.mode !== 'navigate') {
    return;
  }

  // 4. Page Navigations: Network-First -> Cache visited pages -> Cached version offline -> offline.html
  if (req.mode === 'navigate') {
    const canCachePage = !isAuthOrSensitivePath(url.pathname);
    event.respondWith(
      fetch(req)
        .then((res) => {
          const ct = (res.headers.get('content-type') || '').toLowerCase();
          if (canCachePage && res.ok && !res.redirected && ct.includes('text/html')) {
            const copy = res.clone();
            const cleanUrl = stripCacheBuster(req.url);
            caches.open(PAGES_CACHE).then(async (cache) => {
              await cache.put(req, copy.clone());
              if (cleanUrl !== req.url) {
                await cache.put(cleanUrl, copy);
              }
              await trimPagesCache(cache);
            }).catch(() => {});
          }
          return res;
        })
        .catch(async () => {
          const pagesCache = await caches.open(PAGES_CACHE);
          // Try exact match, then clean URL match (e.g. stripping ?source=pwa), then ignoreSearch match
          let cached = await pagesCache.match(req);
          if (!cached) {
            cached = await pagesCache.match(stripCacheBuster(req.url));
          }
          if (!cached) {
            cached = await pagesCache.match(req, { ignoreSearch: true });
          }
          // If launching PWA root/admin offline and Overview was cached under /admin/index.php or /admin/
          if (!cached && /\/admin\/(?:index\.php)?$/i.test(url.pathname)) {
            const altPath = url.pathname.endsWith('index.php')
              ? url.pathname.replace(/index\.php$/i, '')
              : url.pathname.replace(/\/?$/, '/index.php');
            cached = await pagesCache.match(new URL(altPath, url.origin).toString(), { ignoreSearch: true });
          }
          if (cached) return cached;

          // Final graceful fallback: embedded-logo offline.html
          const shellCache = await caches.open(SHELL_CACHE);
          return (await shellCache.match(OFFLINE_URL)) || (await caches.match(OFFLINE_URL));
        })
    );
    return;
  }

  // 5. Same-origin Static Assets (CSS, JS, Images, Fonts): Network-First with cache-buster-safe fallback
  if (isStaticAssetPath(url.pathname)) {
    const cleanAssetUrl = stripCacheBuster(req.url);
    event.respondWith(
      caches.open(SHELL_CACHE).then(async (cache) => {
        try {
          const res = await fetch(req);
          if (res && res.ok) {
            cache.put(cleanAssetUrl, res.clone()).catch(() => {});
          }
          return res;
        } catch (err) {
          const cached =
            (await cache.match(cleanAssetUrl)) ||
            (await cache.match(req, { ignoreSearch: true }));
          if (cached) return cached;
          throw err;
        }
      })
    );
    return;
  }
});
