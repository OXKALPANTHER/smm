/* Royal SMM — service worker for static PWA assets only */
const VERSION = 'royal-v8';
const SHELL = 'royal-shell-' + VERSION;
const RUNTIME = 'royal-runtime-' + VERSION;
const SHELL_ASSETS = [
  './favicon.ico', './offline.html', './assets/style.css',
  './manifest.json', './manifest.webmanifest', './assets/icon.svg',
  './assets/icon-192.png', './assets/icon-512.png',
  './assets/icon-192-maskable.png', './assets/icon-512-maskable.png',
  './assets/lazy-topup.js', './assets/payment-icons.svg'
];
const LIVE_PATHS = [
  'ping.php', 'place-order.php', 'api-services.php', 'public-prices.php',
  'logout.php', 'login.php', 'register.php', 'webhooks/'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(SHELL).then(cache => cache.addAll(SHELL_ASSETS)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(key => key !== SHELL && key !== RUNTIME).map(key => caches.delete(key))
    )).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const req = event.request;
  const url = new URL(req.url);

  // Never intercept POST/AJAX, HTML navigations, or live service/order endpoints.
  if (req.method !== 'GET' || req.mode === 'navigate' || LIVE_PATHS.some(path => url.pathname.includes(path))) return;

  // Static CSS uses network-first so deployed fixes arrive immediately.
  if (url.pathname.toLowerCase().endsWith('.css')) {
    event.respondWith(
      fetch(req).then(response => {
        if (response && response.ok) {
          const copy = response.clone();
          caches.open(RUNTIME).then(cache => cache.put(req, copy)).catch(() => {});
        }
        return response;
      }).catch(() => caches.match(req))
    );
    return;
  }

  // Other static assets may use the versioned runtime cache.
  event.respondWith(
    caches.match(req).then(hit => hit || fetch(req).then(response => {
      if (response && (response.ok || response.type === 'opaque')) {
        const copy = response.clone();
        caches.open(RUNTIME).then(cache => cache.put(req, copy)).catch(() => {});
      }
      return response;
    }))
  );
});
