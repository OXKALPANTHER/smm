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
  margin-bottom:90px;
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

/* bottom nav */
.bottom-nav{position:fixed;bottom:0;left:0;width:100%;background:rgba(255,255,255,.92);backdrop-filter:blur(10px);display:flex;justify-content:space-around;padding:10px 0 18px;box-shadow:0 -6px 30px rgba(43,54,116,.06);border-top-left-radius:24px;border-top-right-radius:24px;z-index:1000;max-width:540px;margin:0 auto;}
.bottom-nav a{color:var(--muted);text-align:center;font-size:.66rem;flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;font-weight:600;}
.bottom-nav a.active{color:var(--primary);}
.bottom-nav a i{font-size:1.4rem;}

/* toast */
.toast-wrap{position:fixed;top:14px;left:50%;transform:translateX(-50%);z-index:2000;width:calc(100% - 28px);max-width:512px;}
.skeleton{background:linear-gradient(90deg,#eef1f8 25%,#f7f9ff 50%,#eef1f8 75%);background-size:200% 100%;animation:sk 1.2s infinite;border-radius:10px;height:14px;}
@keyframes sk{0%{background-position:200% 0}100%{background-position:-200% 0}}
</style>
{$extraHead}
</head>
<body class="{$bodyClass}">
<div class="toast-wrap" id="toastWrap"></div>
HTML;
}

function ui_bottom_nav($active = 'home') {
    $items = [
        'home'    => ['index.php',   'bi-house-door-fill', 'Home'],
        'topup'   => ['#',           'bi-wallet2',         'Top-Up'],
        'howto'   => ['howto.php',   'bi-journal-text',    'How-To'],
        'profile' => ['profile.php', 'bi-person-circle',   'Profile'],
    ];
    echo '<div class="bottom-nav">';
    foreach ($items as $key => [$href, $icon, $label]) {
        $cls = $active === $key ? ' active' : '';
        $attr = $key === 'topup' ? ' data-bs-toggle="modal" data-bs-target="#topUpModal"' : '';
        echo "<a href=\"{$href}\"{$attr} class=\"{$cls}\"><i class=\"bi {$icon}\"></i><span>{$label}</span></a>";
    }
    echo '</div>';
}

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
</body>
</html>
HTML;
}
