/* Royal SMM — service worker for static PWA assets only */
const VERSION = 'royal-v10';
const SHELL = 'royal-shell-' + VERSION;
const RUNTIME = 'royal-runtime-' + VERSION;
const CACHE_PREFIX = 'royal-';
const SHELL_ASSETS = [
  './favicon.ico', './offline.html', './assets/style.css',
  './manifest.json', './manifest.webmanifest', './assets/icon.svg',
  './assets/icon-192.png', './assets/icon-512.png',
  './assets/icon-192-maskable.png', './assets/icon-512-maskable.png',
  './assets/lazy-topup.js', './assets/payment-icons.svg'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(SHELL)
      .then(cache => cache.addAll(SHELL_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(key => key.startsWith(CACHE_PREFIX) && key !== SHELL && key !== RUNTIME)
        .map(key => caches.delete(key))
    )).then(() => self.clients.claim())
  );
});

function matchCachedAsset(request, url) {
  return Promise.all([caches.open(SHELL), caches.open(RUNTIME)]).then(([shellCache, runtimeCache]) =>
    shellCache.match(request).then(response => response || runtimeCache.match(request)).then(response => {
      if (response) return response;
      const normalizedUrl = new URL(url.pathname, self.registration.scope);
      return shellCache.match(normalizedUrl).then(shellResponse => shellResponse || runtimeCache.match(normalizedUrl));
    })
  );
}

self.addEventListener('fetch', event => {
  const req = event.request;
  const url = new URL(req.url);

  if (req.method !== 'GET' || url.origin !== self.location.origin) return;

  // Never cache page responses; when a navigation fails, show the offline page.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() => caches.open(SHELL).then(cache =>
        cache.match(new URL('./offline.html', self.registration.scope))
      )
        .then(response => response || new Response('You are offline.', {
          status: 503,
          headers: { 'Content-Type': 'text/plain; charset=utf-8' }
        })))
    );
    return;
  }

  // Cache only static files and the app manifests, never dynamic PHP/API data.
  const isManifest = /(?:^|\/)manifest(?:\.webmanifest|\.json)$/i.test(url.pathname);
  const isStaticAsset = /\.(?:avif|css|gif|ico|jpe?g|js|png|svg|webp|woff2?)$/i.test(url.pathname);
  if (!isManifest && !isStaticAsset) return;

  // Static CSS uses network-first so deployed fixes arrive immediately.
  if (url.pathname.toLowerCase().endsWith('.css')) {
    event.respondWith(
      fetch(req).then(response => {
        if (response && response.ok) {
          const copy = response.clone();
          caches.open(RUNTIME).then(cache => cache.put(req, copy)).catch(error => {
            console.warn('Unable to cache stylesheet:', error);
          });
        }
        return response;
      }).catch(() => matchCachedAsset(req, url).then(response =>
        response || new Response('', {
          status: 503,
          headers: { 'Content-Type': 'text/css; charset=utf-8' }
        })
      ))
    );
    return;
  }

  // Other static assets use the app-owned caches and are stored after a successful fetch.
  event.respondWith(
    matchCachedAsset(req, url).then(hit => hit || fetch(req).then(response => {
      if (response && (response.ok || response.type === 'opaque')) {
        const copy = response.clone();
        caches.open(RUNTIME).then(cache => cache.put(req, copy)).catch(error => {
          console.warn('Unable to cache static asset:', error);
        });
      }
      return response;
    }))
  );
});
