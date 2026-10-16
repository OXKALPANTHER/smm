/* Royal SMM — service worker (offline support + installable PWA) */
const VERSION   = 'royal-v6';
const SHELL     = 'royal-shell-' + VERSION;
const RUNTIME   = 'royal-runtime-' + VERSION;
// Minimal app shell precached on install.
const SHELL_ASSETS = [
  
  './offline.html',
  './assets/style.css',
  
  './manifest.json',

  './manifest.webmanifest',
  
  './assets/icon.svg',
  
  './assets/icon-192.png',
  
  './assets/icon-512.png',
  
  './assets/icon-192-maskable.png',

  './assets/icon-512-maskable.png',

  './assets/lazy-topup.js',
  
  './assets/payment-icons.svg'
  
];
// Never let the SW touch these (auth / live order / keep-alive / dynamic JSON).
const BYPASS = ['ping.php', 'place-order.php', 'api-services.php', 'public-prices.php', 'logout.php', 'login.php', 'register.php', 'webhooks/'];
self.addEventListener('install', (event) => {
  
  event.waitUntil(
    
    caches.open(SHELL).then((cache) => cache.addAll(SHELL_ASSETS)).then(() => self.skipWaiting())
    
  );
  
});
self.addEventListener('activate', (event) => {
  
  event.waitUntil(
    
    caches.keys().then((keys) =>
      
      Promise.all(keys.filter((k) => k !== SHELL && k !== RUNTIME).map((k) => caches.delete(k)))
                       
    ).then(() => self.clients.claim())
    
  );
  
});
self.addEventListener('fetch', (event) => {
  
  const req = event.request;
  
  
  // Only handle GET; let everything else hit the network untouched.
  
  if (req.method !== 'GET') return;
  
  
  const url = new URL(req.url);
  
  if (BYPASS.some((p) => url.pathname.includes(p))) return;
  
  
  // Navigations (HTML pages): network-first, fall back to cache, then offline page.
  
  if (req.mode === 'navigate') {
    
    event.respondWith(
      
      fetch(req)
      
        .then((res) => {
          if (res && res.ok) {
            const copy = res.clone();
            caches.open(RUNTIME).then((c) => c.put(req, copy)).catch(() => {});
          }
          return res;
        })
      
        .catch(() => caches.match(req).then((hit) => hit || caches.match('./offline.html')))
      
    );
    
    return;
    
  }
  
  
  // CSS is network-first. Versioned URLs prevent obsolete deployed styles from winning.
  if (url.pathname.toLowerCase().endsWith('.css')) {
    event.respondWith(
      fetch(req).then((res) => {
        if (res && res.ok) {
          const copy = res.clone();
          caches.open(RUNTIME).then((cache) => cache.put(req, copy)).catch(() => {});
        }
        return res;
      }).catch(() => caches.match(req).then((hit) => hit || new Response('/* stylesheet unavailable */', {
        status: 503,
        headers: { 'Content-Type': 'text/css; charset=utf-8', 'Cache-Control': 'no-store' }
      })))
    );
    return;
  }

  // Other static assets (js/fonts/images, incl. CDN): cache-first, then network.
  
  event.respondWith(
    
    caches.match(req).then((hit) => {
      
      if (hit) return hit;
      
      return fetch(req).then((res) => {
        
        if (res && (res.ok || res.type === 'opaque')) {
          
          const copy = res.clone();
          
          caches.open(RUNTIME).then((c) => c.put(req, copy)).catch(() => {});
          
        }
        
        return res;
        
      }).catch(() => hit);
      
    })
    
  );
  
});
