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
    'instagram' => 'bi-instagram',
    'facebook'  => 'bi-facebook',
    'tiktok'    => 'bi-tiktok',
    'twitter'   => 'bi-twitter-x',
    'youtube'   => 'bi-youtube',
    'linkedin'  => 'bi-linkedin',
    'telegram'  => 'bi-telegram',
    'snapchat'  => 'bi-snapchat',
    'pinterest' => 'bi-pinterest',
];

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
    if (strpos($st, 'pend') !== false || strpos($st, 'process') !== false) $stats['pending'] += (int)$row['c'];
}

function statusBadge($status) {
    $s = strtolower($status);
    if (strpos($s, 'complet') !== false) return 'success';
    if (strpos($s, 'pend') !== false || strpos($s, 'process') !== false) return 'warning';
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

        /* Platform chips */
        .platform-row { display: flex; gap: .55rem; overflow-x: auto; padding: .2rem .1rem .6rem; scrollbar-width: none; }
        .platform-row::-webkit-scrollbar { display: none; }
        .chip {
            flex: 0 0 auto; border: 1.5px solid #e9edf7; background: #fafbff; border-radius: 16px;
            padding: .6rem .85rem; display: flex; flex-direction: column; align-items: center; gap: .25rem;
            cursor: pointer; min-width: 72px; transition: all .15s; color: var(--muted); font-size: .7rem; font-weight: 600;
        }
        .chip i { font-size: 1.35rem; }
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
    </style>
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

    <!-- Order card -->
    <div class="card-soft">
        <div class="section-title mb-3">
            <div class="section-ico"><i class="bi bi-cart-plus"></i></div>
            Weka Order Mpya
        </div>

        <form id="orderForm" autocomplete="off">
            <!-- Platforms -->
            <label class="form-label">1. Chagua Platform</label>
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
                <div class="svc-detail" id="svcDetail"></div>
            </div>

            <!-- Link -->
            <div class="mt-3">
                <label class="form-label">3. Link / Username</label>
                <input type="text" class="form-control" id="linkInput" name="link" placeholder="https://instagram.com/akaunti..." required>
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

// Platform selection
document.querySelectorAll('.chip').forEach(chip => {
    chip.addEventListener('click', async () => {
        document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        const platform = chip.dataset.platform;

        $service.prop('disabled', true).empty().append('<option>Inapakia huduma...</option>').trigger('change');
        currentService = null; svcDetail.style.display = 'none'; recalc();

        try {
            const r = await fetch(`api-services.php?platform=${encodeURIComponent(platform)}`);
            const j = await r.json();
            if (j.success && j.data && j.data.length) {
                $service.empty().append('<option value="">-- Chagua huduma --</option>');
                j.data.forEach(s => {
                    const opt = new Option(`${s.name.substring(0,70)}  ·  ${fmt(s.price_per_1000)} TZS/1K`, s.id, false, false);
                    opt.dataset.svc = JSON.stringify(s);
                    $service.append(opt);
                });
                $service.prop('disabled', false).trigger('change');
                toast(`${j.data.length} huduma zimepatikana`, 'success');
            } else {
                $service.empty().append('<option value="">Hakuna huduma</option>').prop('disabled', true).trigger('change');
                toast('Hakuna huduma kwa platform hii', 'warning');
            }
        } catch (e) {
            $service.empty().append('<option value="">Kosa la mtandao</option>').prop('disabled', true).trigger('change');
            toast('Imeshindwa kupakua huduma. Jaribu tena.', 'danger');
        }
    });
});

// Service change
$service.on('change', function () {
    const opt = this.options[this.selectedIndex];
    currentService = (opt && opt.dataset.svc) ? JSON.parse(opt.dataset.svc) : null;

    if (currentService) {
        svcDetail.style.display = 'flex';
        svcDetail.innerHTML = `
            <span class="pill blue"><i class="bi bi-arrow-down-up"></i> Min ${fmt(currentService.min)} · Max ${fmt(currentService.max)}</span>
            <span class="pill"><i class="bi bi-cash-coin"></i> ${fmt(currentService.price_per_1000)} TZS / 1000</span>
            ${currentService.refill ? '<span class="pill green"><i class="bi bi-arrow-repeat"></i> Refill</span>' : ''}`;
        qtyRange.textContent = `(min ${fmt(currentService.min)} - max ${fmt(currentService.max)})`;
        quantityInput.min = currentService.min;
        if (parseInt(quantityInput.value) < currentService.min) quantityInput.value = currentService.min;
    } else {
        svcDetail.style.display = 'none'; qtyRange.textContent = '';
    }
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
</body>
</html>
