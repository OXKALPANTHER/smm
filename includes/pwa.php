<?php
/**
 * Progressive Web App (PWA) helpers — makes Royal installable as an app and
 * keeps the host awake while a tab is open.
 *
 * Usage:
 *   pwa_head();   // inside <head>  -> manifest + theme-color + apple icons
 *   pwa_foot();   // before </body> -> service-worker reg + install button + heartbeat
 *
 * Both are include-once safe (function_exists guards) so pages that use the
 * shared ui.php layout AND the standalone index.php can call them freely.
 */

if (!defined('APP_NAME')) { define('APP_NAME', 'Royal'); }

if (!function_exists('pwa_head')) {
    function pwa_head() {
        $app = htmlspecialchars(APP_NAME . ' SMM', ENT_QUOTES);
        echo <<<HTML
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#6c5ce7">
<meta name="application-name" content="{$app}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{$app}">
<link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png">
<link rel="icon" type="image/svg+xml" href="assets/icon.svg">
<link rel="icon" type="image/png" sizes="192x192" href="assets/icon-192.png">
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
HTML;
    }
}

if (!function_exists('pwa_foot')) {
    function pwa_foot() {
        $phone = defined('WHATSAPP_SUPPORT_PHONE') ? preg_replace('/[^0-9]/', '', (string) WHATSAPP_SUPPORT_PHONE) : '255627417402';
        echo <<<HTML
<a class="royal-whatsapp" href="https://wa.me/{$phone}" target="_blank" rel="noopener" aria-label="WhatsApp support"><i class="bi bi-whatsapp" aria-hidden="true"></i><span data-i18n="support.button">Support</span></a>
<div id="pwaInstallBanner" class="pwa-install-banner" role="region" aria-label="Install Royal SMM app">
  <button id="pwaInstallBtn" class="pwa-install-action" type="button"><i class="bi bi-download" aria-hidden="true"></i> Pakua app</button>
  <span class="pwa-install-copy">Add Royal SMM to your home screen for quick access.</span>
  <button id="pwaInstallDismiss" class="pwa-install-dismiss" type="button" aria-label="Dismiss app install banner">&times;</button>
</div>
<div id="pwaIosHint" class="pwa-ios-hint" role="region" aria-label="Install Royal SMM on iPhone">
  <span aria-hidden="true" class="pwa-ios-icon">📲</span>
  <span class="pwa-ios-copy">Pakua app: bofya <b>Share</b> kisha <b>"Add to Home Screen"</b>.</span>
  <button id="pwaIosDismiss" type="button" aria-label="Dismiss installation instructions" class="pwa-install-dismiss">&times;</button>
</div>
<script>
(function(){
  function dismissed(key){try{return localStorage.getItem(key)==='1';}catch(e){return false;}}
  function remember(key){try{localStorage.setItem(key,'1');}catch(e){}}
  // --- Service worker (offline + installable) ---
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function(){
      navigator.serviceWorker.register('sw.js', { scope: './' }).catch(function(){});
    });
  }

  // --- Install prompt (Android / desktop Chrome) ---
  var deferred = null;
  var banner = document.getElementById('pwaInstallBanner');
  var btn = document.getElementById('pwaInstallBtn');
  var close = document.getElementById('pwaInstallDismiss');
  var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  var installDismissed = dismissed('pwaInstallDismissed');
  if (window.top !== window.self || isStandalone || installDismissed) {
    if (banner) banner.style.display = 'none';
  }

  window.addEventListener('beforeinstallprompt', function(e){
    e.preventDefault();
    deferred = e;
    if (banner && btn && !isStandalone && !installDismissed && window.top === window.self) {
      banner.style.display = 'flex';
    }
  });

  if (btn) {
    btn.addEventListener('click', function(){
      if (!deferred) return;
      deferred.prompt();
      deferred.userChoice.finally(function(){ deferred = null; if (banner) banner.style.display = 'none'; });
    });
  }
  if (close) {
    close.addEventListener('click', function(){
      installDismissed = true;
      remember('pwaInstallDismissed');
      if (banner) banner.style.display = 'none';
    });
  }

  window.addEventListener('appinstalled', function(){
    isStandalone = true;
    installDismissed = true;
    remember('pwaInstallDismissed');
    if (banner) banner.style.display = 'none';
    deferred = null;
  });

  // --- iOS hint (Safari has no beforeinstallprompt) ---
  var ua = window.navigator.userAgent || '';
  var isIos = /iphone|ipad|ipod/i.test(ua);
  var isSafari = /^((?!chrome|android|crios|fxios).)*safari/i.test(ua);
  var iosHint = document.getElementById('pwaIosHint');
  var iosClose = document.getElementById('pwaIosDismiss');
  if (isIos && isSafari && !isStandalone && !dismissed('pwaIosHintDismissed') && iosHint && window.top === window.self) {
    iosHint.style.display = 'flex';
  }
  if (iosClose) {
    iosClose.addEventListener('click', function(){
      remember('pwaIosHintDismissed');
      if (iosHint) iosHint.style.display = 'none';
    });
  }

  // --- Keep-alive heartbeat: ping while the tab is visible (keeps host warm) ---
  function heartbeat(){
    if (document.visibilityState === 'visible') {
      fetch('ping.php', { cache: 'no-store' }).catch(function(){});
    }
  }
  // Keep the single floating support link from covering account links or form controls.
  var supportControl = document.querySelector('.royal-whatsapp');
  if (supportControl) {
    var supportCheckQueued = false;
    function checkSupportOverlap() {
      supportCheckQueued = false;
      if (window.innerWidth > 768) { supportControl.classList.remove('support-obstructing'); return; }
      var box = supportControl.getBoundingClientRect();
      var controls = document.querySelectorAll('a[href],button:not(:disabled),input:not([type="hidden"]),select,textarea,[role="button"]');
      var overlaps = false;
      controls.forEach(function(control) {
        if (overlaps || control === supportControl || supportControl.contains(control) || !control.getClientRects().length) return;
        var rect = control.getBoundingClientRect();
        if (rect.width > 0 && rect.height > 0 && box.left < rect.right && box.right > rect.left && box.top < rect.bottom && box.bottom > rect.top) overlaps = true;
      });
      supportControl.classList.toggle('support-obstructing', overlaps);
    }
    function queueSupportCheck() {
      if (supportCheckQueued) return;
      supportCheckQueued = true;
      window.requestAnimationFrame(checkSupportOverlap);
    }
    window.addEventListener('scroll', queueSupportCheck, { passive: true });
    window.addEventListener('resize', queueSupportCheck, { passive: true });
    window.addEventListener('load', queueSupportCheck);
    new MutationObserver(queueSupportCheck).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style'] });
    queueSupportCheck();
  }
  setInterval(heartbeat, 240000); // every 4 minutes
})();
</script>
HTML;
    }
}
