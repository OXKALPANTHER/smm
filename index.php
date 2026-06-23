<?php
require_once 'config.php';
require_once 'includes/ui.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Current user + balance
$stmt = $conn->prepare("SELECT username, balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$tzsBalance = (float)($user['balance'] ?? 0);
$username   = $user['username'] ?? 'Mteja';

// Platforms (with bootstrap-icon mapping)
$platformsCfg = json_decode(PLATFORMS, true);
$platformIcons = [
    'instagram'  => 'bi-instagram',
    'facebook'   => 'bi-facebook',
    'tiktok'     => 'bi-tiktok',
    'twitter'    => 'bi-twitter-x',
    'youtube'    => 'bi-youtube',
    'linkedin'   => 'bi-linkedin',
    'telegram'   => 'bi-telegram',
    'snapchat'   => 'bi-snapchat',
    'pinterest'  => 'bi-pinterest',
    'whatsapp'   => 'bi-whatsapp',
    'spotify'    => 'bi-spotify',
    'threads'    => 'bi-threads',
    'discord'    => 'bi-discord',
    'twitch'     => 'bi-twitch',
    'reddit'     => 'bi-reddit',
    'google'     => 'bi-google',
    'soundcloud' => 'bi-cloud-fill',
    'kick'       => 'bi-broadcast',
    'audiomack'  => 'bi-music-note-beamed',
    'shazam'     => 'bi-music-note',
];

// Refresh order statuses from the live provider before reading them.
require_once 'includes/order-sync.php';
syncUserOrders($conn, $user_id, isset($_GET['sync']));

// Recent orders
$recentOrders = [];
$stmt = $conn->prepare("SELECT id, service_name, platform, quantity, price, status, external_order_id, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 8");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $recentOrders[] = $row;
}

// Quick stats
$stats = ['total' => 0, 'pending' => 0, 'completed' => 0];
$stmt = $conn->prepare("SELECT status, COUNT(*) c FROM orders WHERE user_id = ? GROUP BY status");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $stats['total'] += (int)$row['c'];
    $st = strtolower($row['status']);
    if (strpos($st, 'complet') !== false) $stats['completed'] += (int)$row['c'];
    if (strpos($st, 'pend') !== false || strpos($st, 'process') !== false || strpos($st, 'progress') !== false) $stats['pending'] += (int)$row['c'];
}

function statusBadge($status) {
    $s = strtolower($status);
    if (strpos($s, 'complet') !== false) return 'success';
    if (strpos($s, 'pend') !== false || strpos($s, 'process') !== false || strpos($s, 'progress') !== false) return 'warning';
    if (strpos($s, 'cancel') !== false || strpos($s, 'fail') !== false) return 'danger';
    return 'secondary';
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= APP_NAME ?> — Weka Order</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #6c5ce7;
            --primary-2: #4834d4;
            --accent: #00cec9;
            --success: #00b894;
            --danger: #e17055;
            --ink: #2b3674;
            --muted: #8a93b2;
            --card: #ffffff;
            --bg: #eef1fb;
        }
        * { -webkit-tap-highlight-color: transparent; }
        body {
            font-family: 'Poppins', sans-serif;
            background: radial-gradient(1200px 600px at 100% -10%, #e8ecff 0%, transparent 60%),
                        radial-gradient(900px 500px at -10% 10%, #e6fbfa 0%, transparent 55%),
                        var(--bg);
            color: var(--ink);
            margin: 0 0 30px;
        }
        .container { max-width: 540px; }

        /* Top bar */
        .topbar { padding: 1.1rem 0 0.5rem; }
        .brand { font-weight: 800; letter-spacing: -0.5px; font-size: 1.35rem; }
        .brand span { color: var(--primary); }
        .icon-btn {
            width: 42px; height: 42px; border-radius: 14px;
            background: #fff; box-shadow: 0 6px 18px rgba(43,54,116,0.08);
            display: flex; align-items: center; justify-content: center;
            color: var(--ink); text-decoration: none; position: relative;
        }

        /* Balance hero */
        .hero {
            border-radius: 26px; padding: 1.5rem 1.4rem; color: #fff; position: relative; overflow: hidden;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-2) 100%);
            box-shadow: 0 18px 40px rgba(72,52,212,0.30);
        }
        .hero::after {
            content: ''; position: absolute; right: -40px; top: -40px; width: 160px; height: 160px;
            background: rgba(255,255,255,0.12); border-radius: 50%;
        }
        .hero small { opacity: .85; text-transform: uppercase; letter-spacing: 1px; font-size: .68rem; }
        .hero .amount { font-size: 2.1rem; font-weight: 800; line-height: 1.1; margin: .25rem 0 .9rem; }
        .hero .amount small { font-size: 1rem; font-weight: 600; opacity: .8; }
        .btn-glass {
            background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.32);
            backdrop-filter: blur(6px); border-radius: 30px; padding: .5rem 1.1rem;
            font-size: .82rem; font-weight: 600; color: #fff; text-decoration: none; display: inline-flex; align-items: center; gap: .4rem;
        }
        .btn-glass:hover { background: rgba(255,255,255,0.28); color: #fff; }

        /* Stat chips */
        .stat {
            background: #fff; border-radius: 18px; padding: .8rem .6rem; text-align: center;
            box-shadow: 0 8px 22px rgba(43,54,116,0.05);
        }
        .stat .n { font-weight: 800; font-size: 1.15rem; }
        .stat .l { font-size: .66rem; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; }

        /* Card */
        .card-soft {
            background: var(--card); border-radius: 26px; padding: 1.4rem 1.25rem;
            box-shadow: 0 18px 40px rgba(43,54,116,0.06); border: 1px solid rgba(43,54,116,0.04);
        }
        .section-title { font-weight: 700; font-size: 1.05rem; display: flex; align-items: center; gap: .55rem; }
        .section-ico { width: 40px; height: 40px; border-radius: 13px; background: linear-gradient(135deg,var(--primary),var(--primary-2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.15rem; }

        .form-label { font-weight: 600; font-size: .74rem; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; margin-bottom: .4rem; }
        .form-control, .form-select {
            border-radius: 15px; border: 1.5px solid #e9edf7; padding: .8rem 1rem; background: #fafbff; font-size: .95rem;
        }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(108,92,231,.12); background:#fff; }

        /* Platform chips — wrapped grid so every platform is visible at once */
        .platform-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(70px, 1fr)); gap: .5rem; padding: .2rem 0; }
        .chip {
            border: 1.5px solid #e9edf7; background: #fafbff; border-radius: 16px;
            padding: .6rem .3rem; display: flex; flex-direction: column; align-items: center; gap: .3rem;
            cursor: pointer; transition: all .15s; color: var(--muted); font-size: .64rem; font-weight: 600; text-align: center;
        }
        .chip span { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .chip i { font-size: 1.3rem; }

        /* Global service search */
        .svc-search { position: relative; margin-bottom: .9rem; }
        .svc-search .bi-search { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 1rem; pointer-events: none; }
        .svc-search input { width: 100%; border-radius: 15px; border: 1.5px solid #e9edf7; background: #fafbff; padding: .85rem 1rem .85rem 2.6rem; font-size: .92rem; font-family: inherit; color: var(--ink); }
        .svc-search input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(108,92,231,.12); background: #fff; }
        .svc-search .clr { position: absolute; right: .55rem; top: 50%; transform: translateY(-50%); border: none; background: #eef1f8; color: var(--muted); width: 30px; height: 30px; border-radius: 10px; cursor: pointer; display: none; align-items: center; justify-content: center; }
        .or-divider { display: flex; align-items: center; gap: .7rem; color: var(--muted); font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; margin: .2rem 0 .7rem; }
        .or-divider::before, .or-divider::after { content: ''; flex: 1; height: 1px; background: #e9edf7; }
        .chip.active { border-color: var(--primary); background: linear-gradient(135deg,var(--primary),var(--primary-2)); color: #fff; box-shadow: 0 10px 20px rgba(108,92,231,.28); }

        /* Service detail */
        .svc-detail { display: none; gap: .5rem; flex-wrap: wrap; margin-top: .7rem; }
        .pill { font-size: .7rem; font-weight: 600; padding: .35rem .7rem; border-radius: 20px; background:#f0f2fb; color: var(--ink); display:inline-flex; align-items:center; gap:.3rem; }
        .pill.green { background: #e4faf3; color: #00876a; }
        .pill.blue { background: #e8efff; color: #2f54eb; }

        /* Quantity presets */
        .qty-presets { display: flex; gap: .4rem; margin-top: .55rem; flex-wrap: wrap; }
        .qty-presets button {
            border: 1.5px solid #e9edf7; background: #fff; border-radius: 12px; padding: .35rem .7rem;
            font-size: .78rem; font-weight: 600; color: var(--ink); cursor: pointer;
        }
        .qty-presets button:hover { border-color: var(--primary); color: var(--primary); }

        /* Price summary */
        .price-box {
            background: linear-gradient(135deg,#f3f1ff,#eafcfb); border-radius: 20px; padding: 1.15rem; text-align: center;
            margin: 1.1rem 0; border: 2px dashed rgba(108,92,231,.35);
        }
        .price-box .lbl { font-size: .72rem; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; }
        .price-box .val { font-size: 2rem; font-weight: 800; color: var(--primary-2); line-height: 1.1; }
        .price-box .val small { font-size: .95rem; color: var(--muted); }

        .btn-primary-grad {
            background: linear-gradient(135deg,var(--primary),var(--primary-2)); border: none; border-radius: 16px;
            padding: .95rem; font-weight: 700; font-size: 1.02rem; color: #fff; width: 100%;
            box-shadow: 0 12px 26px rgba(72,52,212,.32); transition: all .2s; display:flex; align-items:center; justify-content:center; gap:.5rem;
        }
        .btn-primary-grad:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 16px 32px rgba(72,52,212,.42); }
        .btn-primary-grad:disabled { opacity: .55; }

        /* Orders list */
        .order-item { display:flex; align-items:center; gap:.8rem; padding:.7rem 0; border-bottom: 1px solid #f0f2f8; }
        .order-item:last-child { border-bottom: none; }
        .order-ico { width: 40px; height:40px; border-radius: 12px; background:#f0f2fb; color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex:0 0 auto; }
        .order-name { font-size: .82rem; font-weight: 600; line-height: 1.2; }
        .order-meta { font-size: .7rem; color: var(--muted); }

        /* Hamburger + slide drawer (shared look) */
        .hamburger{position:fixed;top:14px;right:14px;z-index:1300;width:46px;height:46px;border-radius:14px;border:none;background:rgba(255,255,255,.9);backdrop-filter:blur(10px);box-shadow:0 10px 26px rgba(43,54,116,.18);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;transition:transform .2s;}
        .hamburger:active{transform:scale(.92);}
        .hamburger span{display:block;width:20px;height:2.4px;border-radius:3px;background:var(--ink);transition:.3s;}
        body.drawer-open .hamburger span:nth-child(1){transform:translateY(6.4px) rotate(45deg);}
        body.drawer-open .hamburger span:nth-child(2){opacity:0;}
        body.drawer-open .hamburger span:nth-child(3){transform:translateY(-6.4px) rotate(-45deg);}
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
        .drawer-link{display:flex;align-items:center;gap:.85rem;padding:.8rem .85rem;border-radius:15px;color:var(--ink);font-weight:600;font-size:.92rem;margin-bottom:.25rem;transition:.15s;text-decoration:none;}
        .drawer-link .di{width:38px;height:38px;border-radius:12px;background:#f0f2fb;color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:1.1rem;transition:.15s;flex:0 0 auto;}
        .drawer-link:hover{background:#f6f7fd;}
        .drawer-link.active{background:linear-gradient(135deg,rgba(108,92,231,.13),rgba(72,52,212,.10));color:var(--primary-2);}
        .drawer-link.active .di{background:linear-gradient(135deg,var(--primary),var(--primary-2));color:#fff;box-shadow:0 8px 16px rgba(108,92,231,.35);}
        .drawer-link.danger{color:var(--danger);}
        .drawer-link.danger .di{background:#fdeee9;color:#c0392b;}
        .drawer-foot{padding:.9rem 1.3rem;border-top:1px solid #f0f2f8;font-size:.7rem;color:var(--muted);text-align:center;}

        /* Select2 tweaks */
        .select2-container--bootstrap-5 .select2-selection { border-radius: 15px !important; border: 1.5px solid #e9edf7 !important; min-height: 48px; padding: .35rem .6rem; background:#fafbff; }
        .select2-results__option { font-size: .85rem; }

        .skeleton { background: linear-gradient(90deg,#eef1f8 25%,#f7f9ff 50%,#eef1f8 75%); background-size: 200% 100%; animation: sk 1.2s infinite; border-radius: 10px; height: 14px; }
        @keyframes sk { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

        .toast-wrap { position: fixed; top: 14px; left: 50%; transform: translateX(-50%); z-index: 2000; width: calc(100% - 28px); max-width: 512px; }

        /* Entrance animations */
        @keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
        @keyframes popIn{0%{opacity:0;transform:scale(.96)}100%{opacity:1;transform:scale(1)}}
        .reveal{animation:fadeUp .55s both}
        .reveal-2{animation:fadeUp .55s .1s both}
        .reveal-3{animation:fadeUp .55s .2s both}
        .reveal-4{animation:fadeUp .55s .3s both}

        /* Platform scrolling text belt */
        .belt{overflow:hidden;border-radius:16px;background:linear-gradient(135deg,var(--primary),var(--primary-2));box-shadow:0 12px 30px rgba(72,52,212,.25);margin:.2rem 0 1.1rem;padding:.6rem 0;position:relative;}
        .belt::before,.belt::after{content:'';position:absolute;top:0;bottom:0;width:40px;z-index:2;pointer-events:none;}
        .belt::before{left:0;background:linear-gradient(90deg,#5a45d6,transparent);}
        .belt::after{right:0;background:linear-gradient(270deg,#5a45d6,transparent);}
        .belt-track{display:flex;gap:2rem;width:max-content;animation:scrollX 24s linear infinite;}
        .belt:hover .belt-track{animation-play-state:paused;}
        .belt-item{display:flex;align-items:center;gap:.45rem;color:#fff;font-weight:600;font-size:.85rem;white-space:nowrap;opacity:.96;}
        .belt-item i{font-size:1.15rem;}
        @keyframes scrollX{from{transform:translateX(0)}to{transform:translateX(-50%)}}

        /* Service DETAILS card */
        .details-card{display:none;margin-top:.9rem;border-radius:18px;border:1.5px solid var(--line);background:linear-gradient(180deg,#fbfcff,#f3f5ff);padding:1rem;}
        .details-card.show{display:block;animation:popIn .35s both;}
        .details-head{font-size:.7rem;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--primary-2);display:flex;align-items:center;gap:.4rem;margin-bottom:.55rem;}
        .details-name{font-weight:600;font-size:.88rem;line-height:1.35;margin-bottom:.7rem;color:var(--ink);}
        .details-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;}
        .detail-box{background:#fff;border:1px solid var(--line);border-radius:12px;padding:.5rem .7rem;}
        .detail-box .k{font-size:.62rem;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);font-weight:600;}
        .detail-box .v{font-weight:700;font-size:.9rem;color:var(--ink);}
        .detail-tags{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.55rem;}

        /* Link hint + guidance tips */
        .link-hint{font-size:.74rem;color:var(--muted);margin-top:.4rem;display:flex;align-items:center;gap:.35rem;}
        .tips-box{display:none;margin-top:.65rem;border-radius:14px;background:#fff7e6;border:1px solid #ffe0a3;padding:.75rem .85rem;font-size:.8rem;color:#8a6d3b;}
        .tips-box.show{display:block;animation:popIn .35s both;}
        .tips-box .th{font-weight:700;display:flex;align-items:center;gap:.4rem;color:#b8860b;margin-bottom:.3rem;}
        .tips-box ul{margin:0;padding-left:1.1rem;}
        .tips-box li{margin-bottom:.2rem;}

        /* ---- UI polish: living gradients + motion ---- */
        .hero{background:linear-gradient(135deg,var(--primary),var(--primary-2),#7b6cf0);background-size:220% 220%;animation:gradShift 9s ease infinite;}
        @keyframes gradShift{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}

        /* platform chips: staggered entrance + clearly tappable */
        .chip{transition:transform .15s, box-shadow .15s, border-color .15s, background .2s; animation:popIn .4s both;}
        .platform-row .chip:nth-child(1){animation-delay:.03s}
        .platform-row .chip:nth-child(2){animation-delay:.07s}
        .platform-row .chip:nth-child(3){animation-delay:.11s}
        .platform-row .chip:nth-child(4){animation-delay:.15s}
        .platform-row .chip:nth-child(5){animation-delay:.19s}
        .platform-row .chip:nth-child(6){animation-delay:.23s}
        .platform-row .chip:nth-child(7){animation-delay:.27s}
        .platform-row .chip:nth-child(8){animation-delay:.31s}
        .platform-row .chip:nth-child(9){animation-delay:.35s}
        .chip:hover{transform:translateY(-3px);box-shadow:0 10px 20px rgba(108,92,231,.18);border-color:var(--primary);}
        .chip.active{transform:translateY(-2px) scale(1.03);}

        /* belt: animated gradient + brighter icons so platforms are well seen */
        .belt{background:linear-gradient(135deg,var(--primary),var(--primary-2),#5a45d6);background-size:220% 220%;animation:gradShift 12s ease infinite;}
        .belt-item{text-shadow:0 1px 3px rgba(0,0,0,.18);}
        .belt-item i{filter:drop-shadow(0 2px 6px rgba(0,0,0,.25));}

        /* submit button shimmer */
        .btn-primary-grad{position:relative;overflow:hidden;}
        .btn-primary-grad::after{content:'';position:absolute;top:0;left:-60%;width:40%;height:100%;background:linear-gradient(120deg,transparent,rgba(255,255,255,.35),transparent);transform:skewX(-20deg);animation:shine 3.6s ease-in-out infinite;pointer-events:none;}
        @keyframes shine{0%,58%{left:-60%}100%{left:130%}}

        @media (prefers-reduced-motion: reduce){
            .hero,.belt,.btn-primary-grad::after,.chip{animation:none!important;}
            .belt-track{animation-duration:60s;}
        }
    </style>
    <?php pwa_head(); ?>
</head>
<body>

<div class="toast-wrap" id="toastWrap"></div>

<div class="container">

    <!-- Top bar -->
    <div class="topbar">
        <div class="brand"><?= strtoupper(substr(APP_NAME,0,1)) . substr(APP_NAME,1) ?> <span>SMM</span></div>
        <div class="text-muted" style="font-size:.74rem;">Habari, <?= htmlspecialchars($username) ?> 👋</div>
    </div>

    <!-- Balance hero -->
    <div class="hero mt-2">
        <small><i class="bi bi-wallet2 me-1"></i> Salio Lako</small>
        <div class="amount"><span id="balanceText"><?= number_format($tzsBalance, 0) ?></span> <small>TZS</small></div>
        <a href="#" class="btn-glass" data-bs-toggle="modal" data-bs-target="#topUpModal"><i class="bi bi-plus-circle"></i> Ongeza Salio</a>
    </div>

    <!-- Stats -->
    <div class="row g-2 mt-1 mb-3">
        <div class="col-4"><div class="stat"><div class="n"><?= $stats['total'] ?></div><div class="l">Orders</div></div></div>
        <div class="col-4"><div class="stat"><div class="n text-warning"><?= $stats['pending'] ?></div><div class="l">Pending</div></div></div>
        <div class="col-4"><div class="stat"><div class="n text-success"><?= $stats['completed'] ?></div><div class="l">Done</div></div></div>
    </div>

    <!-- Platform scrolling belt -->
    <div class="belt reveal-2">
        <div class="belt-track">
            <?php for ($i = 0; $i < 2; $i++): foreach ($platformsCfg as $key => $p): $ico = $platformIcons[$key] ?? 'bi-globe'; ?>
                <span class="belt-item"><i class="bi <?= $ico ?>"></i><?= htmlspecialchars($p['name']) ?></span>
            <?php endforeach; endfor; ?>
        </div>
    </div>

    <!-- Order card -->
    <div class="card-soft reveal-3">
        <div class="section-title mb-3">
            <div class="section-ico"><i class="bi bi-cart-plus"></i></div>
            Weka Order Mpya
        </div>

        <form id="orderForm" autocomplete="off">
            <!-- Platforms + global search -->
            <label class="form-label">1. Tafuta au Chagua Platform</label>
            <div class="svc-search">
                <i class="bi bi-search"></i>
                <input type="text" id="svcSearch" autocomplete="off"
                       placeholder="Tafuta huduma yoyote (mf: WhatsApp, Google, Spotify)...">
                <button type="button" class="clr" id="svcSearchClr" aria-label="Futa"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="or-divider">au chagua platform</div>
            <div class="platform-row" id="platformRow">
                <?php foreach ($platformsCfg as $key => $p):
                    $ico = $platformIcons[$key] ?? 'bi-globe'; ?>
                    <div class="chip" data-platform="<?= htmlspecialchars($key) ?>">
                        <i class="bi <?= $ico ?>"></i>
                        <span><?= htmlspecialchars($p['name']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Service -->
            <div class="mt-3">
                <label class="form-label">2. Chagua Huduma</label>
                <select class="form-select" id="serviceSelect" name="service_id" style="width:100%" disabled>
                    <option value="">Chagua platform kwanza...</option>
                </select>
                <!-- DETAILS card -->
                <div class="details-card" id="svcDetail"></div>
            </div>

            <!-- Link -->
            <div class="mt-3">
                <label class="form-label" id="linkLabel">3. Link / Username</label>
                <input type="text" class="form-control" id="linkInput" name="link" placeholder="Chagua huduma kwanza..." required>
                <div class="link-hint" id="linkHint"><i class="bi bi-info-circle"></i> Chagua huduma ili kuona maelekezo sahihi ya link.</div>
                <div class="tips-box" id="igTips"></div>
            </div>

            <!-- Quantity -->
            <div class="mt-3">
                <label class="form-label">4. Idadi <span id="qtyRange" class="text-lowercase"></span></label>
                <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="100" required>
                <div class="qty-presets" id="qtyPresets">
                    <button type="button" data-q="100">100</button>
                    <button type="button" data-q="500">500</button>
                    <button type="button" data-q="1000">1K</button>
                    <button type="button" data-q="5000">5K</button>
                    <button type="button" data-q="max">Max</button>
                </div>
            </div>

            <!-- Price -->
            <div class="price-box">
                <div class="lbl">Jumla ya Malipo</div>
                <div class="val" id="totalPrice">0 <small>TZS</small></div>
                <div class="text-danger small mt-1 d-none" id="balanceWarning"><i class="bi bi-exclamation-triangle-fill"></i> Salio halitoshi</div>
            </div>

            <button type="submit" class="btn-primary-grad" id="submitBtn" disabled>
                <i class="bi bi-rocket-takeoff"></i> <span id="submitText">THIBITISHA ORDER</span>
            </button>
        </form>
    </div>

    <!-- Recent orders -->
    <div class="card-soft mt-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="section-title" style="font-size:.98rem;"><div class="section-ico" style="width:34px;height:34px;font-size:1rem;"><i class="bi bi-clock-history"></i></div> Orders za Karibuni</div>
            <a href="profile.php" class="text-decoration-none small fw-semibold" style="color:var(--primary)">Zote</a>
        </div>
        <?php if (empty($recentOrders)): ?>
            <div class="text-center text-muted py-4" style="font-size:.85rem;">
                <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                Bado hujaweka order yoyote.
            </div>
        <?php else: ?>
            <?php foreach ($recentOrders as $o): ?>
                <div class="order-item">
                    <div class="order-ico"><i class="bi bi-bag-check"></i></div>
                    <div class="flex-grow-1">
                        <div class="order-name"><?= htmlspecialchars(mb_substr($o['service_name'], 0, 42)) ?></div>
                        <div class="order-meta">#<?= $o['id'] ?> · <?= number_format($o['quantity']) ?> · <?= number_format($o['price']) ?> TZS</div>
                    </div>
                    <span class="badge bg-<?= statusBadge($o['status']) ?> rounded-pill" style="font-weight:600;"><?= htmlspecialchars($o['status']) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- Top-right hamburger + slide drawer -->
<?php ui_nav('home', ['balance' => $tzsBalance]); ?>

<!-- Top-Up Modal -->
<div class="modal fade" id="topUpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content rounded-4 overflow-hidden border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Ongeza Salio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe src="topup.php" class="w-100" style="height: 600px; border: none;"></iframe>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
let userBalance = <?= json_encode($tzsBalance) ?>;
let currentService = null;

const $service = $('#serviceSelect');
const quantityInput = document.getElementById('quantity');
const totalPrice = document.getElementById('totalPrice');
const submitBtn = document.getElementById('submitBtn');
const balanceWarning = document.getElementById('balanceWarning');
const svcDetail = document.getElementById('svcDetail');
const qtyRange = document.getElementById('qtyRange');

$service.select2({ theme: 'bootstrap-5', placeholder: 'Tafuta huduma...', width: '100%' });

function toast(msg, type = 'primary') {
    const el = document.createElement('div');
    el.className = `alert alert-${type} shadow rounded-4 border-0 d-flex align-items-center gap-2 mb-2`;
    el.style.animation = 'sk .3s';
    el.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle-fill' : type === 'danger' ? 'x-circle-fill' : 'info-circle-fill'}"></i><div>${msg}</div>`;
    document.getElementById('toastWrap').appendChild(el);
    setTimeout(() => { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, 4200);
}

function fmt(n) { return Number(n).toLocaleString('en-US'); }

// ---- Shared service loader (used by platform chips AND global search) ----
async function loadServices(url, opts = {}) {
    $service.prop('disabled', true).empty().append('<option>Inapakia huduma...</option>').trigger('change');
    currentService = null; resetServiceUI(); recalc();
    try {
        const r = await fetch(url);
        const j = await r.json();
        if (j.success && j.data && j.data.length) {
            $service.empty().append('<option value="">-- Chagua huduma --</option>');
            j.data.forEach(s => {
                const opt = new Option(`${s.name.substring(0,70)}  ·  ${fmt(s.price_per_1000)} TZS/1K`, s.id, false, false);
                opt.dataset.svc = JSON.stringify(s);
                $service.append(opt);
            });
            $service.prop('disabled', false).trigger('change');
            let msg = `${j.data.length} huduma zimepatikana`;
            if (j.truncated) msg = `Zinaonyeshwa ${fmt(j.data.length)} kati ya ${fmt(j.total)}+ — andika zaidi kupunguza`;
            toast(msg, 'success');
            if (opts.openDropdown) { try { $service.select2('open'); } catch (e) {} }
        } else {
            $service.empty().append('<option value="">Hakuna huduma</option>').prop('disabled', true).trigger('change');
            toast(opts.emptyMsg || 'Hakuna huduma iliyopatikana', 'warning');
        }
    } catch (e) {
        $service.empty().append('<option value="">Kosa la mtandao</option>').prop('disabled', true).trigger('change');
        toast('Imeshindwa kupakua huduma. Jaribu tena.', 'danger');
    }
}

const svcSearchEl  = document.getElementById('svcSearch');
const svcSearchClr = document.getElementById('svcSearchClr');

// Platform selection (chips)
document.querySelectorAll('.chip').forEach(chip => {
    chip.addEventListener('click', () => {
        document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        if (svcSearchEl)  svcSearchEl.value = '';
        if (svcSearchClr) svcSearchClr.style.display = 'none';
        loadServices(`api-services.php?platform=${encodeURIComponent(chip.dataset.platform)}`,
                     { emptyMsg: 'Hakuna huduma kwa platform hii' });
    });
});

// Global search across the whole catalogue (debounced)
let searchTimer = null;
if (svcSearchEl) {
    svcSearchEl.addEventListener('input', () => {
        const q = svcSearchEl.value.trim();
        svcSearchClr.style.display = q ? 'flex' : 'none';
        clearTimeout(searchTimer);
        if (q.length < 2) return;
        document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
        searchTimer = setTimeout(() => {
            loadServices(`api-services.php?q=${encodeURIComponent(q)}`,
                         { openDropdown: true, emptyMsg: `Hakuna huduma kwa "${q}"` });
        }, 450);
    });
    svcSearchClr.addEventListener('click', () => {
        svcSearchEl.value = '';
        svcSearchClr.style.display = 'none';
        svcSearchEl.focus();
    });
}

// ---- Service detail + link guidance ----
const linkInput = document.getElementById('linkInput');
const linkLabel = document.getElementById('linkLabel');
const linkHint  = document.getElementById('linkHint');
const igTips    = document.getElementById('igTips');

const EXAMPLES = {
  instagram:{profile:'https://instagram.com/username', post:'https://instagram.com/p/Cxxxx/'},
  tiktok:{profile:'https://tiktok.com/@username', post:'https://tiktok.com/@user/video/123456'},
  facebook:{profile:'https://facebook.com/YourPage', post:'https://facebook.com/YourPage/posts/123'},
  youtube:{profile:'https://youtube.com/@channel', post:'https://youtube.com/watch?v=xxxx'},
  twitter:{profile:'https://x.com/username', post:'https://x.com/user/status/123'},
  telegram:{profile:'https://t.me/channel', post:'https://t.me/channel/123'},
  snapchat:{profile:'https://snapchat.com/add/username', post:'https://snapchat.com/add/username'},
  linkedin:{profile:'https://linkedin.com/in/username', post:'https://linkedin.com/feed/update/...'},
  pinterest:{profile:'https://pinterest.com/username', post:'https://pinterest.com/pin/123'}
};

function svcText(s){ return ((s.name||'')+' '+(s.category||'')).toLowerCase(); }
function platformOf(s){ const t=svcText(s); return ['instagram','tiktok','facebook','youtube','twitter','telegram','snapchat','linkedin','pinterest'].find(p=>t.includes(p))||''; }
function linkType(s){
  const t=svcText(s);
  if(/like|view|comment|share|save|reaction|repost|retweet|vote|play|watch|impression|story/.test(t)) return 'post';
  if(/follower|subscriber|member|connection|page like|fan/.test(t)) return 'profile';
  return 'any';
}
function grab(name,key){ const m=name.match(new RegExp(key+'[:\\s]*([^|►⚡⏱♻]+)','i')); return m?m[1].trim():null; }

function resetServiceUI(){
  svcDetail.classList.remove('show'); svcDetail.innerHTML='';
  igTips.classList.remove('show'); igTips.innerHTML='';
  linkLabel.textContent='3. Link / Username';
  linkInput.placeholder='Chagua huduma kwanza...';
  linkHint.innerHTML='<i class="bi bi-info-circle"></i> Chagua huduma ili kuona maelekezo sahihi ya link.';
  qtyRange.textContent='';
}

function applyServiceUI(s){
  const plat=platformOf(s), type=linkType(s);
  const start=grab(s.name,'start'), speed=grab(s.name,'speed');

  svcDetail.innerHTML = `
    <div class="details-head"><i class="bi bi-card-list"></i> Details za Huduma</div>
    <div class="details-name">${s.name}</div>
    <div class="details-grid">
      <div class="detail-box"><div class="k">Kiwango cha chini</div><div class="v">${fmt(s.min)}</div></div>
      <div class="detail-box"><div class="k">Kiwango cha juu</div><div class="v">${fmt(s.max)}</div></div>
      <div class="detail-box"><div class="k">Bei / 1000</div><div class="v">${fmt(s.price_per_1000)} TZS</div></div>
      <div class="detail-box"><div class="k">Refill</div><div class="v">${s.refill?'Ndiyo ✅':'Hapana'}</div></div>
      ${start?`<div class="detail-box"><div class="k">Muda wa kuanza</div><div class="v" style="font-size:.8rem">${start}</div></div>`:''}
      ${speed?`<div class="detail-box"><div class="k">Kasi</div><div class="v" style="font-size:.8rem">${speed}</div></div>`:''}
    </div>
    <div class="detail-tags">
      ${plat?`<span class="pill blue"><i class="bi bi-hash"></i> ${plat}</span>`:''}
      <span class="pill"><i class="bi bi-link-45deg"></i> ${type==='post'?'Link ya Post':type==='profile'?'Profile/Username':'Link'}</span>
      ${s.refill?'<span class="pill green"><i class="bi bi-arrow-repeat"></i> Refill</span>':''}
    </div>`;
  svcDetail.classList.add('show');

  const ex = (EXAMPLES[plat]||{})[type] || (EXAMPLES[plat]||{}).profile || 'https://...';
  if(type==='post'){ linkLabel.textContent='3. Weka Link ya Post / Video'; linkHint.innerHTML='<i class="bi bi-link-45deg"></i> Weka link KAMILI ya post / video / reel husika.'; }
  else if(type==='profile'){ linkLabel.textContent='3. Weka Link ya Profile / Username'; linkHint.innerHTML='<i class="bi bi-person"></i> Weka profile/username — akaunti iwe PUBLIC.'; }
  else { linkLabel.textContent='3. Link / Username'; linkHint.innerHTML='<i class="bi bi-info-circle"></i> Weka link sahihi ya huduma uliyochagua.'; }
  linkInput.placeholder = ex;

  igTips.classList.remove('show'); igTips.innerHTML='';
  const isFollowers = /follower/.test(svcText(s));
  if(plat==='instagram' && isFollowers){
    igTips.innerHTML = `<div class="th"><i class="bi bi-exclamation-triangle-fill"></i> Maelekezo (Instagram Followers)</div>
      <ul>
        <li>Hakikisha akaunti yako ni <b>Public</b> (siyo Private).</li>
        <li>Ikiwezekana <b>zima "Flag / Restrict"</b> au vizuizi vyovyote vya akaunti.</li>
        <li>Usibadilishe <b>username</b> wala kuifanya private wakati order inaendelea.</li>
        <li>Weka <b>link kamili</b> ya profile, mf: https://instagram.com/username</li>
      </ul>`;
    igTips.classList.add('show');
  } else if(type==='profile' && plat){
    igTips.innerHTML = `<div class="th"><i class="bi bi-info-circle-fill"></i> Kumbuka</div><ul><li>Akaunti iwe <b>Public</b> ili order ipite vizuri.</li></ul>`;
    igTips.classList.add('show');
  }

  qtyRange.textContent = `(min ${fmt(s.min)} - max ${fmt(s.max)})`;
  quantityInput.min = s.min;
  if(parseInt(quantityInput.value) < s.min) quantityInput.value = s.min;
}

// Service change
$service.on('change', function () {
    const opt = this.options[this.selectedIndex];
    currentService = (opt && opt.dataset.svc) ? JSON.parse(opt.dataset.svc) : null;
    if (currentService) applyServiceUI(currentService); else resetServiceUI();
    recalc();
});

quantityInput.addEventListener('input', recalc);

// Quantity presets
document.querySelectorAll('#qtyPresets button').forEach(b => {
    b.addEventListener('click', () => {
        if (b.dataset.q === 'max') {
            quantityInput.value = currentService ? currentService.max : quantityInput.value;
        } else {
            quantityInput.value = b.dataset.q;
        }
        recalc();
    });
});

function recalc() {
    const qty = parseInt(quantityInput.value) || 0;
    if (!currentService || !qty) {
        totalPrice.innerHTML = '0 <small>TZS</small>';
        submitBtn.disabled = true; balanceWarning.classList.add('d-none'); return;
    }
    const min = currentService.min, max = currentService.max;
    const total = Math.ceil(qty * currentService.rate);
    totalPrice.innerHTML = `${fmt(total)} <small>TZS</small>`;

    const validQty = qty >= min && (max === 0 || qty <= max);
    const enough = total <= userBalance;
    balanceWarning.classList.toggle('d-none', enough || !validQty);
    submitBtn.disabled = !(validQty && enough && total > 0);
}

// Submit -> AJAX live order
document.getElementById('orderForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!currentService) return toast('Chagua huduma kwanza.', 'warning');
    const qty = parseInt(quantityInput.value) || 0;
    const link = document.getElementById('linkInput').value.trim();
    if (!link) return toast('Weka link au username.', 'warning');
    if (qty < currentService.min || (currentService.max && qty > currentService.max))
        return toast(`Idadi iwe kati ya ${fmt(currentService.min)} na ${fmt(currentService.max)}.`, 'warning');

    submitBtn.disabled = true;
    document.getElementById('submitText').textContent = 'Inatuma...';

    try {
        const r = await fetch('place-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ service_id: currentService.id, quantity: qty, link })
        });
        const j = await r.json();
        if (j.success) {
            toast(`✅ ${j.message} (Order #${j.order_id})`, 'success');
            if (typeof j.new_balance !== 'undefined') {
                userBalance = j.new_balance;
                document.getElementById('balanceText').textContent = fmt(j.new_balance);
            }
            setTimeout(() => location.reload(), 1400);
        } else {
            toast('⚠️ ' + (j.message || 'Order imeshindikana.'), 'danger');
        }
    } catch (err) {
        toast('Kosa la mtandao. Jaribu tena.', 'danger');
    } finally {
        document.getElementById('submitText').textContent = 'THIBITISHA ORDER';
        recalc();
    }
});
</script>
<?php pwa_foot(); ?>
</body>
</html>
