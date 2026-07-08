<?php
/**
 * Shared UI theme + layout helpers for the Royal SMM front-end.
 * Gives every page the same look (palette, cards, forms, nav, toasts).
 *
 * Usage:
 *   ui_head('Page title', 'app');   // body classes: app | auth | admin
 *   ... page content ...
 *   ui_bottom_nav('home');          // app pages only
 *   ui_topup_modal();
 *   ui_foot();                      // closes body/html + loads scripts
 */

if (!defined('APP_NAME')) { define('APP_NAME', 'Royal'); }

require_once __DIR__ . '/pwa.php';

/**
 * Premium gold crown mark for the Royal brand. Returns a self-contained inline
 * <svg> sized to $px (no external CSS, so it renders anywhere). Features curved
 * arches, a jewelled band, a ruby centre gem and a soft highlight for sheen.
 */
function ui_crown_svg($px = 26) {
    $px = (int)$px;
    // Unique gradient ids per size so multiple crowns on one page don't clash.
    $g  = 'rcg' . $px;   // gold body
    $gb = 'rcb' . $px;   // band
    $gh = 'rch' . $px;   // highlight
    return <<<SVG
<svg width="{$px}" height="{$px}" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
  <defs>
    <linearGradient id="{$g}" x1="10" y1="6" x2="34" y2="34" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#FFF7D6"/>
      <stop offset=".4" stop-color="#FFD75E"/>
      <stop offset=".75" stop-color="#F6B12B"/>
      <stop offset="1" stop-color="#E08C12"/>
    </linearGradient>
    <linearGradient id="{$gb}" x1="0" y1="32" x2="0" y2="41" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#FFE69A"/>
      <stop offset="1" stop-color="#E89A18"/>
    </linearGradient>
    <radialGradient id="{$gh}" cx="0.32" cy="0.26" r="0.7">
      <stop offset="0" stop-color="#FFFFFF" stop-opacity=".75"/>
      <stop offset="1" stop-color="#FFFFFF" stop-opacity="0"/>
    </radialGradient>
  </defs>
  <!-- crown body: curved arches between three jewelled peaks -->
  <path d="M6.5 31.5 L9 12.5 Q16.5 21 12.8 19 L16.8 23.2 Q20 16.5 24 9.8 Q28 16.5 31.2 23.2 L35.2 19 Q31.5 21 39 12.5 L41.5 31.5 Z"
        fill="url(#{$g})" stroke="#C97E12" stroke-width="1.1" stroke-linejoin="round"/>
  <path d="M6.5 31.5 L9 12.5 L16.8 23.2 L24 9.8 L31.2 23.2 L39 12.5 L41.5 31.5 Z"
        fill="url(#{$gh})"/>
  <!-- jewelled band -->
  <rect x="6" y="31.3" width="36" height="7" rx="2.6" fill="url(#{$gb})" stroke="#C97E12" stroke-width="1.1"/>
  <!-- peak gems -->
  <circle cx="9"  cy="11.4" r="2.1" fill="#FFF6DC" stroke="#C97E12" stroke-width=".7"/>
  <circle cx="24" cy="8.6"  r="2.5" fill="#FFF6DC" stroke="#C97E12" stroke-width=".7"/>
  <circle cx="39" cy="11.4" r="2.1" fill="#FFF6DC" stroke="#C97E12" stroke-width=".7"/>
  <!-- centre ruby on the band -->
  <circle cx="24" cy="34.8" r="2.1" fill="#E8466B" stroke="#B12B49" stroke-width=".7"/>
  <circle cx="23.3" cy="34.1" r=".6" fill="#fff" opacity=".8"/>
</svg>
SVG;
}

/**
 * Royal logo lockup: a purple gradient badge holding the gold crown, with an
 * optional "Royal SMM" wordmark. Fully inline-styled so it works on pages that
 * don't load the shared <style> block (e.g. index.php).
 *
 * @param bool $wordmark show the text wordmark next to the emblem
 * @param int  $size     emblem size in px
 */
function ui_logo($wordmark = true, $size = 46) {
    $size   = (int)$size;
    $radius = (int)round($size * 0.30);
    $crown  = ui_crown_svg((int)round($size * 0.58));
    // Rich badge: layered purple gradient, soft drop shadow, inner top sheen
    // and a thin translucent ring for a polished, app-icon feel.
    $badge = "<span style=\"position:relative;display:inline-flex;align-items:center;justify-content:center;"
           . "width:{$size}px;height:{$size}px;border-radius:{$radius}px;flex:0 0 auto;"
           . "background:linear-gradient(150deg,#8a78ff 0%,#6c5ce7 45%,#4226c9 100%);"
           . "box-shadow:0 12px 26px rgba(72,52,212,.40),inset 0 1.5px 0 rgba(255,255,255,.45),inset 0 -3px 8px rgba(28,16,90,.35);"
           . "border:1px solid rgba(255,255,255,.18);\">{$crown}</span>";
    if (!$wordmark) {
        return $badge;
    }
    $app   = htmlspecialchars(APP_NAME);
    $wsize = round($size * 0.5, 1);
    $tsize = round($size * 0.205, 1);
    $word  = "<span style=\"display:inline-flex;flex-direction:column;justify-content:center;line-height:1;\">"
           . "<span style=\"font-family:'Poppins',sans-serif;font-weight:800;font-size:{$wsize}px;letter-spacing:-.5px;"
           . "background:linear-gradient(90deg,#3a2a8c,#6c5ce7);-webkit-background-clip:text;background-clip:text;"
           . "-webkit-text-fill-color:transparent;color:#3a2a8c;\">{$app}</span>"
           . "<span style=\"font-family:'Poppins',sans-serif;font-weight:700;font-size:{$tsize}px;letter-spacing:3px;"
           . "text-transform:uppercase;color:#b8860b;margin-top:2px;\">SMM Panel</span>"
           . "</span>";
    return "<span style=\"display:inline-flex;align-items:center;gap:.6rem;\">{$badge}{$word}</span>";
}

function ui_head($title, $bodyClass = 'app', $extraHead = '') {
    $app = APP_NAME;
    echo <<<HTML
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>{$title}</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{
  --primary:#6c5ce7; --primary-2:#4834d4; --accent:#00cec9;
  --success:#00b894; --warning:#fdcb6e; --danger:#e17055;
  --ink:#2b3674; --muted:#8a93b2; --card:#ffffff; --bg:#eef1fb; --line:#e9edf7;
}
*{-webkit-tap-highlight-color:transparent;}
body{font-family:'Poppins',sans-serif;color:var(--ink);margin:0;}
a{text-decoration:none;}

/* ---- app (mobile) pages ---- */
body.app{
  background:radial-gradient(1200px 600px at 100% -10%,#e8ecff 0,transparent 60%),
             radial-gradient(900px 500px at -10% 10%,#e6fbfa 0,transparent 55%),var(--bg);
  margin:0 0 30px;
}
body.app .container{max-width:540px;}

/* ---- auth pages ---- */
body.auth{
  min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;
  background:linear-gradient(145deg,var(--primary) 0%,var(--primary-2) 100%);
}
.glass-card{
  background:rgba(255,255,255,.96);backdrop-filter:blur(12px);border-radius:28px;
  box-shadow:0 25px 50px rgba(0,0,0,.25);padding:2.4rem 2rem;width:100%;max-width:430px;border:1px solid rgba(255,255,255,.3);
}
.brand-icon{
  width:68px;height:68px;background:linear-gradient(145deg,var(--primary),var(--primary-2));border-radius:20px;
  display:flex;align-items:center;justify-content:center;margin:0 auto 1.3rem;color:#fff;font-size:1.9rem;
  box-shadow:0 12px 24px rgba(108,92,231,.35);
}

/* ---- shared components ---- */
.card-soft{background:var(--card);border-radius:24px;padding:1.4rem 1.25rem;box-shadow:0 18px 40px rgba(43,54,116,.06);border:1px solid rgba(43,54,116,.04);}
.section-title{font-weight:700;font-size:1.05rem;display:flex;align-items:center;gap:.55rem;}
.section-ico{width:40px;height:40px;border-radius:13px;background:linear-gradient(135deg,var(--primary),var(--primary-2));color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.15rem;}
.form-label{font-weight:600;font-size:.74rem;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:.4rem;}
.form-control,.form-select{border-radius:15px;border:1.5px solid var(--line);padding:.8rem 1rem;background:#fafbff;font-size:.95rem;}
.form-control:focus,.form-select:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(108,92,231,.12);background:#fff;}
.btn-grad{background:linear-gradient(135deg,var(--primary),var(--primary-2));border:none;border-radius:15px;padding:.85rem;font-weight:700;color:#fff;width:100%;box-shadow:0 12px 26px rgba(72,52,212,.30);transition:.2s;display:flex;align-items:center;justify-content:center;gap:.5rem;}
.btn-grad:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 16px 32px rgba(72,52,212,.42);color:#fff;}
.btn-grad:disabled{opacity:.55;}
.hero{border-radius:26px;padding:1.5rem 1.4rem;color:#fff;position:relative;overflow:hidden;background:linear-gradient(135deg,var(--primary),var(--primary-2));box-shadow:0 18px 40px rgba(72,52,212,.30);}
.hero::after{content:'';position:absolute;right:-40px;top:-40px;width:160px;height:160px;background:rgba(255,255,255,.12);border-radius:50%;}
.pill{font-size:.7rem;font-weight:600;padding:.35rem .7rem;border-radius:20px;background:#f0f2fb;color:var(--ink);display:inline-flex;align-items:center;gap:.3rem;}
.badge-soft{padding:.35rem .7rem;border-radius:20px;font-size:.7rem;font-weight:600;}
.badge-success{background:#e4faf3;color:#00876a;}
.badge-warning{background:#fff6e5;color:#b8860b;}
.badge-danger{background:#fdeee9;color:#c0392b;}
.badge-secondary{background:#eef1f8;color:#5a6a85;}

/* hamburger button (top-right, animated to X) */
.hamburger{position:fixed;top:14px;right:14px;z-index:1300;width:46px;height:46px;border-radius:14px;border:none;background:rgba(255,255,255,.9);backdrop-filter:blur(10px);box-shadow:0 10px 26px rgba(43,54,116,.18);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;transition:transform .2s;}
.hamburger:active{transform:scale(.92);}
.hamburger span{display:block;width:20px;height:2.4px;border-radius:3px;background:var(--ink);transition:.3s;}
body.drawer-open .hamburger span:nth-child(1){transform:translateY(6.4px) rotate(45deg);}
body.drawer-open .hamburger span:nth-child(2){opacity:0;}
body.drawer-open .hamburger span:nth-child(3){transform:translateY(-6.4px) rotate(-45deg);}

/* slide-in drawer nav */
.nav-backdrop{position:fixed;inset:0;background:rgba(18,22,45,.5);backdrop-filter:blur(3px);opacity:0;visibility:hidden;transition:.3s;z-index:1400;}
.nav-backdrop.open{opacity:1;visibility:visible;}
.drawer{position:fixed;top:0;right:0;height:100%;width:300px;max-width:84vw;background:#fff;z-index:1500;transform:translateX(106%);transition:transform .38s cubic-bezier(.5,.05,.2,1);display:flex;flex-direction:column;box-shadow:-24px 0 60px rgba(18,22,45,.28);border-top-left-radius:28px;border-bottom-left-radius:28px;overflow:hidden;}
.drawer.open{transform:translateX(0);}
.drawer-head{background:linear-gradient(140deg,var(--primary),var(--primary-2));color:#fff;padding:1.7rem 1.3rem 1.5rem;position:relative;overflow:hidden;}
.drawer-head::after{content:'';position:absolute;right:-30px;top:-30px;width:120px;height:120px;background:rgba(255,255,255,.12);border-radius:50%;}
.drawer-head .dclose{position:absolute;top:14px;right:14px;width:34px;height:34px;border-radius:11px;border:none;background:rgba(255,255,255,.2);color:#fff;font-size:1.05rem;display:flex;align-items:center;justify-content:center;z-index:1;}
.drawer-ava{width:54px;height:54px;border-radius:16px;background:rgba(255,255,255,.22);border:2px solid rgba(255,255,255,.35);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;}
.drawer-bal{margin-top:1rem;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.26);border-radius:14px;padding:.55rem .9rem;display:flex;justify-content:space-between;align-items:center;position:relative;z-index:1;}
.drawer-nav{flex:1;padding:1rem .8rem;overflow-y:auto;}
.drawer-link{display:flex;align-items:center;gap:.85rem;padding:.8rem .85rem;border-radius:15px;color:var(--ink);font-weight:600;font-size:.92rem;margin-bottom:.25rem;transition:.15s;}
.drawer-link .di{width:38px;height:38px;border-radius:12px;background:#f0f2fb;color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:1.1rem;transition:.15s;flex:0 0 auto;}
.drawer-link:hover{background:#f6f7fd;}
.drawer-link.active{background:linear-gradient(135deg,rgba(108,92,231,.13),rgba(72,52,212,.10));color:var(--primary-2);}
.drawer-link.active .di{background:linear-gradient(135deg,var(--primary),var(--primary-2));color:#fff;box-shadow:0 8px 16px rgba(108,92,231,.35);}
.drawer-link.danger{color:var(--danger);}
.drawer-link.danger .di{background:#fdeee9;color:#c0392b;}
.drawer-foot{padding:.9rem 1.3rem;border-top:1px solid #f0f2f8;font-size:.7rem;color:var(--muted);text-align:center;}

/* toast */
.toast-wrap{position:fixed;top:14px;left:50%;transform:translateX(-50%);z-index:2000;width:calc(100% - 28px);max-width:512px;}
.skeleton{background:linear-gradient(90deg,#eef1f8 25%,#f7f9ff 50%,#eef1f8 75%);background-size:200% 100%;animation:sk 1.2s infinite;border-radius:10px;height:14px;}
@keyframes sk{0%{background-position:200% 0}100%{background-position:-200% 0}}
</style>
{$extraHead}
HTML;
    pwa_head();
    echo <<<HTML
</head>
<body class="{$bodyClass}">
<div class="toast-wrap" id="toastWrap"></div>
HTML;
}

/**
 * Top-right hamburger + slide-in drawer navigation (replaces the bottom nav).
 * $opts['balance'] (optional) shows a balance pill in the drawer header.
 */
function ui_nav($active = 'home', $opts = []) {
    $username = $_SESSION['username'] ?? 'Mteja';
    $role     = $_SESSION['role'] ?? 'user';
    $initial  = strtoupper(mb_substr($username, 0, 1));
    $appName  = APP_NAME;

    $links = [
        'home'    => ['index.php',      'bi-grid-1x2-fill',  'Dashboard'],
        'orders'  => ['orders.php',     'bi-bag-check-fill', 'Orders Zangu'],
        'pro'     => ['pro-dashboard.php', 'bi-rocket-fill', 'Pro Dashboard'],
        'topup'   => ['#',              'bi-wallet2',        'Ongeza Salio'],
        'howto'   => ['howto.php',      'bi-journal-text',   'Mwongozo'],
        'profile' => ['profile.php',    'bi-person-fill',    'Profile'],
    ];
    if ($role === 'admin') {
        $links['admin'] = ['admin.php', 'bi-speedometer2', 'Admin Panel'];
    }

    $linksHtml = '';
    foreach ($links as $key => [$href, $icon, $label]) {
        $cls  = $active === $key ? 'drawer-link active' : 'drawer-link';
        $attr = $key === 'topup' ? ' data-bs-toggle="modal" data-bs-target="#topUpModal"' : '';
        $linksHtml .= "<a href=\"{$href}\"{$attr} class=\"{$cls} js-nav-link\"><span class=\"di\"><i class=\"bi {$icon}\"></i></span>{$label}</a>";
    }

    $balHtml = '';
    if (isset($opts['balance'])) {
        $bal = number_format((float)$opts['balance']);
        $balHtml = "<div class=\"drawer-bal\"><span style=\"font-size:.72rem;opacity:.85;\"><i class=\"bi bi-wallet2 me-1\"></i>Salio</span><strong>{$bal} TZS</strong></div>";
    }

    // Support / community (WhatsApp group + channel)
    $waGroup   = defined('WHATSAPP_GROUP_URL')   ? WHATSAPP_GROUP_URL   : '#';
    $waChannel = defined('WHATSAPP_CHANNEL_URL') ? WHATSAPP_CHANNEL_URL : '#';
    $supportHtml = <<<HTML
<div style="padding:.6rem .85rem .1rem;font-size:.64rem;font-weight:700;letter-spacing:.6px;color:#8a93b2;text-transform:uppercase;">Msaada &amp; Jamii</div>
<a href="{$waGroup}" target="_blank" rel="noopener" class="drawer-link js-nav-link"><span class="di" style="background:#e7fbef;color:#25D366;"><i class="bi bi-whatsapp"></i></span>WhatsApp Group</a>
<a href="{$waChannel}" target="_blank" rel="noopener" class="drawer-link js-nav-link"><span class="di" style="background:#e7fbef;color:#25D366;"><i class="bi bi-megaphone-fill"></i></span>WhatsApp Channel</a>
HTML;

    echo <<<HTML
<button class="hamburger" id="navToggle" aria-label="Menu"><span></span><span></span><span></span></button>
<div class="nav-backdrop" id="navBackdrop"></div>
<aside class="drawer" id="appDrawer">
  <div class="drawer-head">
    <button class="dclose" id="navClose" aria-label="Funga"><i class="bi bi-x-lg"></i></button>
    <div class="d-flex align-items-center gap-3">
      <div class="drawer-ava">{$initial}</div>
      <div>
        <div class="fw-bold" style="font-size:1.05rem;line-height:1.15;">{$username}</div>
        <div style="font-size:.72rem;opacity:.8;">{$appName} Member</div>
      </div>
    </div>
    {$balHtml}
  </div>
  <nav class="drawer-nav">{$linksHtml}{$supportHtml}</nav>
  <a href="logout.php" class="drawer-link danger js-nav-link" style="margin:0 .8rem .7rem;"><span class="di"><i class="bi bi-box-arrow-right"></i></span>Toka (Logout)</a>
  <div class="drawer-foot">{$appName} SMM Panel &middot; v2.0</div>
</aside>
<script>
(function(){
  var t=document.getElementById('navToggle'),d=document.getElementById('appDrawer'),b=document.getElementById('navBackdrop'),c=document.getElementById('navClose');
  function openD(){d.classList.add('open');b.classList.add('open');document.body.classList.add('drawer-open');}
  function closeD(){d.classList.remove('open');b.classList.remove('open');document.body.classList.remove('drawer-open');}
  t.addEventListener('click',function(){d.classList.contains('open')?closeD():openD();});
  b.addEventListener('click',closeD); c.addEventListener('click',closeD);
  document.querySelectorAll('.js-nav-link').forEach(function(a){a.addEventListener('click',closeD);});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')closeD();});
})();
</script>
HTML;
}

// Backward-compatible alias
function ui_bottom_nav($active = 'home') { ui_nav($active); }

function ui_topup_modal() {
    echo <<<HTML
<div class="modal fade" id="topUpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content rounded-4 overflow-hidden border-0">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">Ongeza Salio</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <iframe src="topup.php" class="w-100" style="height:600px;border:none;"></iframe>
      </div>
    </div>
  </div>
</div>
HTML;
}

function ui_foot($extraScript = '') {
    echo <<<HTML
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toast(msg,type='primary'){
  const ic=type==='success'?'check-circle-fill':type==='danger'?'x-circle-fill':type==='warning'?'exclamation-triangle-fill':'info-circle-fill';
  const el=document.createElement('div');
  el.className='alert alert-'+type+' shadow rounded-4 border-0 d-flex align-items-center gap-2 mb-2';
  el.innerHTML='<i class="bi bi-'+ic+'"></i><div>'+msg+'</div>';
  document.getElementById('toastWrap').appendChild(el);
  setTimeout(()=>{el.style.transition='opacity .4s';el.style.opacity='0';setTimeout(()=>el.remove(),400);},4200);
}
</script>
{$extraScript}
HTML;
    pwa_foot();
    echo <<<HTML
</body>
</html>
HTML;
}
