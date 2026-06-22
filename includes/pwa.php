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
<link rel="manifest" href="manifest.webmanifest">
<meta name="theme-color" content="#6c5ce7">
<meta name="application-name" content="{$app}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{$app}">
<link rel="apple-touch-icon" href="assets/icon-192.png">
<link rel="icon" type="image/svg+xml" href="assets/icon.svg">
HTML;
    }
}

if (!function_exists('pwa_foot')) {
    function pwa_foot() {
        echo <<<'HTML'
<!-- PWA install button -->
<button id="pwaInstallBtn" type="button" aria-label="Pakua App"
  style="display:none;position:fixed;left:14px;bottom:18px;z-index:1200;border:none;cursor:pointer;
         padding:.7rem 1.15rem;border-radius:30px;font-family:'Poppins',sans-serif;font-weight:700;
         font-size:.85rem;color:#fff;background:linear-gradient(135deg,#6c5ce7,#4834d4);
         box-shadow:0 12px 26px rgba(72,52,212,.4);display:none;align-items:center;gap:.45rem;
         animation:pwaPulse 2.2s ease-in-out infinite;">
  <i class="bi bi-download"></i> Pakua App
</button>
<div id="pwaIosHint"
  style="display:none;position:fixed;left:12px;right:12px;bottom:16px;z-index:1200;
         background:#fff;border:1px solid #e9edf7;border-radius:18px;padding:.85rem 1rem;
         box-shadow:0 16px 40px rgba(43,54,116,.18);font-family:'Poppins',sans-serif;
         font-size:.82rem;color:#2b3674;align-items:center;gap:.6rem;">
  <span style="font-size:1.4rem;">📲</span>
  <span style="flex:1;line-height:1.3;">Pakua app: bofya <b>Share</b> kisha <b>"Add to Home Screen"</b>.</span>
  <button type="button" onclick="this.parentElement.style.display='none'"
          style="border:none;background:#f0f2fb;border-radius:10px;width:30px;height:30px;color:#2b3674;cursor:pointer;">&times;</button>
</div>
<style>@keyframes pwaPulse{0%,100%{transform:translateY(0)}50%{transform:translateY(-4px)}}</style>
<script>
(function(){
  // --- Service worker (offline + installable) ---
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function(){
      navigator.serviceWorker.register('sw.js', { scope: './' }).catch(function(){});
    });
  }

  // --- Install prompt (Android / desktop Chrome) ---
  var deferred = null;
  var btn = document.getElementById('pwaInstallBtn');
  var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

  window.addEventListener('beforeinstallprompt', function(e){
    e.preventDefault();
    deferred = e;
    if (btn && !isStandalone) { btn.style.display = 'inline-flex'; }
  });

  if (btn) {
    btn.addEventListener('click', function(){
      if (!deferred) return;
      deferred.prompt();
      deferred.userChoice.finally(function(){ deferred = null; btn.style.display = 'none'; });
    });
  }

  window.addEventListener('appinstalled', function(){
    if (btn) btn.style.display = 'none';
    deferred = null;
  });

  // --- iOS hint (Safari has no beforeinstallprompt) ---
  var ua = window.navigator.userAgent || '';
  var isIos = /iphone|ipad|ipod/i.test(ua);
  var isSafari = /^((?!chrome|android|crios|fxios).)*safari/i.test(ua);
  if (isIos && isSafari && !isStandalone) {
    try {
      if (!localStorage.getItem('pwaIosHintSeen')) {
        var hint = document.getElementById('pwaIosHint');
        if (hint) {
          hint.style.display = 'flex';
          localStorage.setItem('pwaIosHintSeen', '1');
          setTimeout(function(){ hint.style.display = 'none'; }, 12000);
        }
      }
    } catch (e) {}
  }

  // --- Keep-alive heartbeat: ping while the tab is visible (keeps host warm) ---
  function heartbeat(){
    if (document.visibilityState === 'visible') {
      fetch('ping.php', { cache: 'no-store' }).catch(function(){});
    }
  }
  setInterval(heartbeat, 240000); // every 4 minutes
})();
</script>
HTML;
    }
}
