<?php
/**
 * Performance direction: shared chrome keeps visual parity while prioritizing fewer blocking connections and a smaller font payload.
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

if (!defined('APP_NAME')) {
  define('APP_NAME', 'Royal');
}

require_once __DIR__ . '/pwa.php';
require_once __DIR__ . '/chatbot-widget.php';

/**
 * Premium gold crown mark for the Royal brand. Returns a self-contained inline
 * <svg> sized to $px (no external CSS, so it renders anywhere). Features curved
 * arches, a jewelled band, a ruby centre gem and a soft highlight for sheen.
 */
function ui_crown_svg($px = 26)
{
  $px = (int) $px;
  // Unique gradient ids per size so multiple crowns on one page don't clash.
  $g = 'rcg' . $px;   // gold body
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
 * don't load the shared stylesheet (e.g. index.php).
 *
 * @param bool $wordmark show the text wordmark next to the emblem
 * @param int  $size     emblem size in px
 */
function ui_logo($wordmark = true, $size = 46)
{
  $size = (int) $size;
  $radius = (int) round($size * 0.30);
  $crown = ui_crown_svg((int) round($size * 0.58));
  // Rich badge: layered purple gradient, soft drop shadow, inner top sheen
  // and a thin translucent ring for a polished, app-icon feel.
  $badge = "<span style=\"position:relative;display:inline-flex;align-items:center;justify-content:center;"
    . "width:{$size}px;height:{$size}px;border-radius:{$radius}px;flex:0 0 auto;"
    . "background:#fff;"
    . "box-shadow:0 12px 26px rgba(72,52,212,.40),inset 0 1.5px 0 rgba(255,255,255,.45),inset 0 -3px 8px rgba(28,16,90,.35);"
    . "border:1px solid rgba(255,255,255,.18);\">{$crown}</span>";
  if (!$wordmark) {
    return $badge;
  }
  $app = htmlspecialchars(APP_NAME);
  $wsize = round($size * 0.5, 1);
  $tsize = round($size * 0.205, 1);
  $word = "<span style=\"display:inline-flex;flex-direction:column;justify-content:center;line-height:1;\">"
    . "<span style=\"font-family:'Poppins',sans-serif;font-weight:800;font-size:{$wsize}px;letter-spacing:-.5px;"
    . "background:linear-gradient(90deg,#3a2a8c,#6c5ce7);-webkit-background-clip:text;background-clip:text;"
    . "-webkit-text-fill-color:transparent;color:#3a2a8c;\">{$app}</span>"
    . "<span style=\"font-family:'Poppins',sans-serif;font-weight:700;font-size:{$tsize}px;letter-spacing:3px;"
    . "text-transform:uppercase;color:#b8860b;margin-top:2px;\">SMM Panel</span>"
    . "</span>";
  return "<span class=\"royal-brand-lockup\" aria-label=\"" . htmlspecialchars(APP_NAME . ' SMM') . "\" style=\"display:inline-flex;align-items:center;gap:.6rem;position:static;\">{$badge}{$word}</span>";
}

function ui_head($title, $bodyClass = 'app', $extraHead = '')
{
  $app = APP_NAME;
  $styleFile = __DIR__ . '/../assets/style.css';
  $styleVersion = is_file($styleFile) ? substr((string) hash_file('sha256', $styleFile), 0, 12) : '1';
  $page = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
  $pageTitles = [
    'services.php' => 'SMM Services & Rates | Royal SMM Panel',
    'pricing.php' => 'SMM Panel Prices per 1000 | Royal SMM',
    'api.php' => 'SMM Reseller API | Royal SMM Panel',
    'refund-refill.php' => 'Refund & Refill Policy | Royal SMM Panel',
    'terms.php' => 'Terms of Service | Royal SMM Panel',
    'privacy.php' => 'Privacy Policy | Royal SMM Panel',
    'login.php' => 'Log In | Royal SMM Panel',
    'register.php' => 'Create Account | Royal SMM Panel',
    'api-center.php' => 'API Center | Royal SMM Panel',
    'api-docs.php' => 'API Documentation | Royal SMM Panel',
    'testimonial.php' => 'Share Feedback | Royal SMM Panel',
  ];
  $pageDescriptions = [
    'services.php' => 'Browse Instagram, TikTok, YouTube, Facebook and Telegram services with live rates, min/max limits and refill info. Compare and order in minutes.',
    'pricing.php' => 'See Royal SMM rates per 1,000 for followers, likes and views. Pay in TZS with mobile money and review the exact price before every order.',
    'api.php' => "Connect your store to Royal SMM's reseller API for live services, order placement and status tracking. Read the docs and request an API key.",
    'refund-refill.php' => 'How refunds, cancellations and refills work at Royal SMM. Learn eligibility, refill windows and how to contact support with your order ID.',
    'terms.php' => 'Read the Royal SMM terms for accounts, orders, delivery and acceptable use before you place your first order.',
    'privacy.php' => 'How Royal SMM collects, uses and protects your account and payment information, and how to request deletion.',
    'login.php' => 'Sign in to your Royal SMM Panel account to manage balances, orders, services and support.',
    'register.php' => 'Create a Royal SMM Panel account to browse social media services, compare rates and manage orders.',
    'api-center.php' => 'Manage reseller API access and keys for your Royal SMM Panel account.',
    'api-docs.php' => 'Read Royal SMM reseller API documentation for service listings, order placement, authentication and status tracking.',
    'testimonial.php' => 'Share genuine feedback with Royal SMM. Your testimonial is reviewed by an admin before publication.',
  ];
  $metaTitle = $pageTitles[$page] ?? trim(strip_tags((string) $title));
  $metaDescription = $pageDescriptions[$page] ?? ($metaTitle . ' on Royal SMM Panel. Manage social media services, order tracking and account support.');
  $metaTitleHtml = htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8');
  $description = htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8');
  $canonicalUrl = rtrim(APP_URL, '/') . '/' . $page;
  $canonical = htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8');
  $ogImage = htmlspecialchars(rtrim(APP_URL, '/') . '/assets/og-image.png', ENT_QUOTES, 'UTF-8');
  $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
  $publicIndexable = in_array($page, ['services.php','pricing.php','api.php','terms.php','privacy.php','refund-refill.php'], true) && !isLoggedIn();
  $robots = $publicIndexable ? 'index,follow' : 'noindex,follow';
  $htmlLang = (str_contains($bodyClass, 'app') || $page === 'register.php') ? 'sw' : 'en';
  $breadcrumbScript = '';
  if ($publicIndexable) {
    $breadcrumbJson = json_encode([
      '@context' => 'https://schema.org',
      '@type' => 'BreadcrumbList',
      'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => rtrim(APP_URL, '/') . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $metaTitle, 'item' => $canonicalUrl],
      ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $breadcrumbScript = '<script type="application/ld+json">' . $breadcrumbJson . '</script>';
  }
  echo <<<HTML
<!DOCTYPE html>
<html lang="{$htmlLang}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{$metaTitleHtml}</title>
<meta name="description" content="{$description}">
<meta property="og:type" content="website">
<meta property="og:title" content="{$metaTitleHtml}">
<meta property="og:description" content="{$description}">
<meta property="og:url" content="{$canonical}">
<meta property="og:image" content="{$ogImage}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{$metaTitleHtml}">
<meta name="twitter:description" content="{$description}">
<meta name="twitter:image" content="{$ogImage}">
<meta name="robots" content="{$robots}">
<meta name="csrf-token" content="{$csrf}">
<link rel="canonical" href="{$canonical}">
{$breadcrumbScript}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preload" href="https://fonts.gstatic.com/s/poppins/v24/pxiEyp8kv8JHgFVrJJfecg.woff2" as="font" type="font/woff2" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script>(function(){var choice='system';try{choice=localStorage.getItem('royal-theme')||'system'}catch(e){}var dark=!!(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.dataset.theme=choice==='dark'||(choice==='system'&&dark)?'dark':'light'})();</script>
<link rel="stylesheet" href="assets/style.css?v={$styleVersion}">
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
function ui_nav($active = 'home', $opts = [])
{
  global $conn;
  $username = htmlspecialchars((string) ($_SESSION['username'] ?? 'Mteja'), ENT_QUOTES, 'UTF-8');
  $role = (string) ($_SESSION['role'] ?? 'user');
  $initial = strtoupper(function_exists('mb_substr') ? mb_substr($username, 0, 1) : substr($username, 0, 1));
  $appName = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');
  $userId = (int) ($_SESSION['user_id'] ?? 0);
  $notificationCount = $userId > 0 ? getUnreadNotificationCount($userId) : 0;
  $balance = $opts['balance'] ?? null;
  if ($balance === null && $userId > 0 && isset($conn)) {
    try {
      $balanceStmt = $conn->prepare('SELECT balance FROM users WHERE id = ? LIMIT 1');
      $balanceStmt->bind_param('i', $userId);
      $balanceStmt->execute();
      $balance = $balanceStmt->get_result()->fetch_assoc()['balance'] ?? 0;
    } catch (Throwable $e) {
      $balance = 0;
    }
  }
  $balance = (float) ($balance ?? 0);
  $pageMeta = [
    'home' => ['Dashboard', 'Your account at a glance', 'header.home.title', 'header.home.subtitle'],
    'services' => ['Services', 'Browse the available catalogue', 'header.services.title', 'header.services.subtitle'],
    'orders' => ['Orders', 'Track and manage your orders', 'header.orders.title', 'header.orders.subtitle'],
    'profile' => ['Account', 'Profile and account settings', 'header.account.title', 'header.account.subtitle'],
    'favorites' => ['Favorites', 'Your saved orders', 'header.favorites.title', 'header.favorites.subtitle'],
    'notifications' => ['Notifications', 'Your recent updates', 'header.notifications.title', 'header.notifications.subtitle'],
    'api' => ['API Center', 'Manage developer API access', 'header.api.title', 'header.api.subtitle'],
    'howto' => ['Guide', 'Help for using the panel', 'header.guide.title', 'header.guide.subtitle'],
    'testimonials' => ['Share Feedback', 'Tell us about your experience', 'header.testimonials.title', 'header.testimonials.subtitle'],
    'moderator' => ['Moderator Desk', 'Review moderation tasks', 'header.moderator.title', 'header.moderator.subtitle'],
  ];
  [$pageTitle, $pageSubtitle, $titleKey, $subtitleKey] = $pageMeta[$active] ?? $pageMeta['home'];
  $links = [
    'home' => ['index.php', 'bi-grid-1x2-fill', 'Dashboard', 'nav.dashboard'],
    'services' => ['services.php', 'bi-list-stars', 'Services', 'nav.services'],
    'orders' => ['orders.php', 'bi-bag-check-fill', 'My Orders', 'nav.orders'],
    'favorites' => ['favorites.php', 'bi-heart-fill', 'Favorites', 'nav.favorites'],
    'notifications' => ['notifications.php', 'bi-bell-fill', 'Notifications', 'nav.notifications'],
    'pro' => ['pro-dashboard.php', 'bi-rocket-fill', 'Pro Dashboard', 'nav.pro'],
    'api' => ['api-center.php', 'bi-key-fill', 'API Center', 'nav.api'],
    'topup' => ['#', 'bi-wallet2', 'Add Balance', 'nav.topup'],
    'howto' => ['howto.php', 'bi-journal-text', 'Guide', 'nav.guide'],
    'profile' => ['profile.php', 'bi-person-fill', 'Account', 'nav.account'],
    'testimonials' => ['testimonial.php', 'bi-chat-square-quote-fill', 'Share Feedback', 'nav.testimonials'],
  ];
  if ($role === 'admin') $links['admin'] = ['admin.php', 'bi-speedometer2', 'Admin Panel', 'nav.admin'];
  if (in_array($role, ['admin', 'moderator'], true)) $links['moderator'] = ['moderator.php', 'bi-shield-check', 'Moderator Desk', 'nav.moderator'];

  $linksHtml = '';
  foreach ($links as $key => [$href, $icon, $label, $i18n]) {
    $cls = $active === $key ? 'drawer-link active' : 'drawer-link';
    $attr = $key === 'topup' ? ' data-bs-toggle="modal" data-bs-target="#topUpModal"' : '';
    $linksHtml .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' . $attr . ' class="' . $cls . ' js-nav-link"><span class="di"><i class="bi ' . $icon . '"></i></span><span data-i18n="' . $i18n . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></a>';
  }
  $waGroup = defined('WHATSAPP_GROUP_URL') ? WHATSAPP_GROUP_URL : '#';
  $waChannel = defined('WHATSAPP_CHANNEL_URL') ? WHATSAPP_CHANNEL_URL : '#';
  $supportHtml = '<div class="drawer-section-title" data-i18n="nav.support">Help &amp; community</div>'
    . '<a href="' . htmlspecialchars($waGroup, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" class="drawer-link js-nav-link"><span class="di"><i class="bi bi-whatsapp"></i></span><span data-i18n="nav.whatsappGroup">WhatsApp Group</span></a>'
    . '<a href="' . htmlspecialchars($waChannel, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" class="drawer-link js-nav-link"><span class="di"><i class="bi bi-megaphone-fill"></i></span><span data-i18n="nav.whatsappChannel">WhatsApp Channel</span></a>';
  $bellBadge = ($userId > 0 && $notificationCount > 0)
    ? '<span class="nav-bell-badge">' . ($notificationCount > 99 ? '99+' : (int) $notificationCount) . '</span>'
    : '';
  $balanceFormatted = number_format($balance);
  $mobileItems = [
    'home' => ['index.php', 'bi-house-fill', 'Home', 'nav.home'],
    'services' => ['services.php', 'bi-grid-3x3-gap-fill', 'Services', 'nav.services'],
    'new' => ['index.php#orderForm', 'bi-plus-circle-fill', 'New Order', 'nav.newOrder'],
    'orders' => ['orders.php', 'bi-bag-check-fill', 'Orders', 'nav.ordersShort'],
    'profile' => ['profile.php', 'bi-person-fill', 'Account', 'nav.account'],
  ];
  $mobileHtml = '';
  foreach ($mobileItems as $key => [$href, $icon, $label, $i18n]) {
    $cls = $active === $key ? 'active' : '';
    $mobileHtml .= '<a data-tab="' . $key . '" class="' . $cls . '" href="' . $href . '"><i class="bi ' . $icon . '"></i><span data-i18n="' . $i18n . '">' . $label . '</span></a>';
  }
  $styleFile = __DIR__ . '/../assets/style.css';
  $languageFile = __DIR__ . '/../assets/i18n.js';
  $languageScriptVersion = is_file($languageFile) ? substr((string) hash_file('sha256', $languageFile), 0, 12) : '1';
  $defaultTab = in_array($active, ['home', 'services', 'orders', 'profile'], true) ? $active : 'profile';
  $headerClass = $active === 'home' ? ' dashboard-header' : '';
  echo <<<HTML
<header class="app-header{$headerClass}" aria-label="Application navigation">
  <a href="index.php" class="app-header-brand" aria-label="{$appName} Panel home"><img src="assets/icon-192.png" alt=""><span>{$appName} Panel</span></a>
  <div class="app-header-controls"><a href="notifications.php" class="nav-bell" aria-label="Notifications" title="Notifications"><i class="bi bi-bell-fill"></i>{$bellBadge}</a><button class="hamburger" id="navToggle" type="button" aria-label="Menu" aria-controls="appDrawer" aria-expanded="false"><span></span><span></span><span></span></button></div>
</header>
<div class="nav-backdrop" id="navBackdrop"></div>
<aside class="drawer" id="appDrawer" aria-label="Main menu">
  <div class="drawer-head"><button class="dclose" id="navClose" type="button" aria-label="Close menu"><i class="bi bi-x-lg"></i></button><div class="d-flex align-items-center gap-3"><div class="drawer-ava">{$initial}</div><div><div class="fw-bold" style="font-size:1.05rem;line-height:1.15;">{$username}</div><div style="font-size:.72rem;opacity:.8;">{$appName} Member</div></div></div><div class="drawer-bal"><span style="font-size:.72rem;opacity:.85;"><i class="bi bi-wallet2 me-1"></i><span data-i18n="common.balance">Balance</span></span><strong>{$balanceFormatted} TZS</strong></div></div>
  <nav class="drawer-nav">{$linksHtml}{$supportHtml}<a href="profile.php#help-legal" class="drawer-link"><span class="di"><i class="bi bi-life-preserver"></i></span><span data-i18n="nav.helpLegal">Help &amp; legal</span></a></nav>
  <a href="logout.php" class="drawer-link danger js-nav-link" style="margin:0 .8rem .5rem;"><span class="di"><i class="bi bi-box-arrow-right"></i></span><span data-i18n="nav.logout">Log out</span></a>
  <div class="drawer-foot"><div>{$appName} SMM Panel &middot; v2.0</div><div class="drawer-preferences"><div class="language-toggle" role="group" aria-label="Language"><button type="button" data-set-language="en" aria-pressed="false">EN</button><button type="button" data-set-language="sw" aria-pressed="false">SW</button></div><button type="button" class="theme-toggle" data-toggle-theme data-i18n="theme.system" aria-label="Toggle color theme">Theme: System</button></div></div>
</aside>
<nav class="mobile-tabbar" data-default-tab="{$defaultTab}" aria-label="Quick navigation">{$mobileHtml}</nav>
<script>
(function(){
  var t=document.getElementById('navToggle'),d=document.getElementById('appDrawer'),b=document.getElementById('navBackdrop'),c=document.getElementById('navClose');
  function openD(){d.classList.add('open');b.classList.add('open');document.body.classList.add('drawer-open');t.setAttribute('aria-expanded','true');}
  function closeD(){d.classList.remove('open');b.classList.remove('open');document.body.classList.remove('drawer-open');t.setAttribute('aria-expanded','false');}
  t.addEventListener('click',function(){d.classList.contains('open')?closeD():openD();});
  b.addEventListener('click',closeD);c.addEventListener('click',closeD);
  document.querySelectorAll('.js-nav-link').forEach(function(a){a.addEventListener('click',closeD);});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')closeD();});
  var tabs=document.querySelector('.mobile-tabbar');
  function markTab(){if(!tabs)return;var selected=location.hash==='#orderForm'?'new':(tabs.dataset.defaultTab||'home');tabs.querySelectorAll('a').forEach(function(a){a.classList.toggle('active',a.dataset.tab===selected);});}
  markTab();window.addEventListener('hashchange',markTab);
})();
</script>
<script src="assets/i18n.js?v={$languageScriptVersion}" defer></script>
HTML;
}

// Backward-compatible alias
function ui_bottom_nav($active = 'home')
{
  ui_nav($active);
}

function ui_topup_modal()
{
  echo <<<HTML
<div class="modal fade" id="topUpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content rounded-4 overflow-hidden border-0">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">Ongeza Salio</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <iframe src="about:blank" data-src="topup.php" loading="lazy" title="Ongeza salio" class="w-100" style="height:600px;border:none;"></iframe>
      </div>
    </div>
  </div>
</div>
HTML;
  echo <<<'HTML'
<script>
(function(){
  var modal=document.getElementById('topUpModal');
  var frame=modal&&modal.querySelector('iframe');
  window.addEventListener('message',function(event){
    if(event.origin!==window.location.origin||!frame||event.source!==frame.contentWindow||!event.data||event.data.type!=='royal-topup-minimize')return;
    if(window.bootstrap&&window.bootstrap.Modal)window.bootstrap.Modal.getOrCreateInstance(modal).hide();
  });
})();
</script>
HTML;
  echo '<script src="assets/lazy-topup.js"></script>';
}

function ui_foot($extraScript = '')
{
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
  $page = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
  $publicFooterPages = ['services.php', 'pricing.php', 'api.php', 'terms.php', 'privacy.php', 'refund-refill.php'];
  if (!isLoggedIn() && in_array($page, $publicFooterPages, true)) {
    $footerYear = date('Y');
    echo '<footer class="site-footer"><nav aria-label="Footer"><a href="services.php">Services</a><a href="pricing.php">Pricing</a><a href="api.php">Reseller API</a><a href="terms.php">Terms</a><a href="privacy.php">Privacy</a><a href="refund-refill.php">Refund / Refill</a></nav><div class="copyright">&copy; ' . htmlspecialchars($footerYear, ENT_QUOTES, 'UTF-8') . ' Royal SMM Panel</div></footer>';
  }
  echo <<<HTML
</body>
</html>
HTML;
}
