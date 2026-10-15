<?php
require_once 'config.php';
require_once 'includes/ui.php';

if (isLoggedIn()) { header("Location: index.php"); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!rate_limit('login', 8, 900)) {
        $error = 'Majaribio mengi sana. Subiri dakika chache kisha ujaribu tena.';
    }
    if ($error === '' && !captcha_is_valid($_POST['cf-turnstile-response'] ?? '', $_POST['website'] ?? '')) {
        $error = 'Thibitisha kuwa wewe si roboti kisha ujaribu tena.';
    }
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($error !== '') {
        $stmt = null;
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $conn->query("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = " . (int)$user['id']);
            $destination = in_array($user['role'], ['admin', 'moderator'], true) ? 'moderator.php' : 'index.php';
            if ($user['role'] === 'admin') {
                $destination = 'admin.php';
            }
            header("Location: " . $destination);
            exit;
        }
        $error = "Jina la mtumiaji au neno siri si sahihi.";
    }
}

ui_head('Sign in — ' . APP_NAME, 'auth', '<meta name="theme-color" content="#191536"><style>
body.auth{padding:0;padding-bottom:calc(84px + env(safe-area-inset-bottom));min-height:100dvh;display:flex;flex-direction:column;align-items:stretch;justify-content:flex-start;overflow-x:hidden;background:radial-gradient(ellipse at 16% 20%,#5944ba 0,transparent 44%),radial-gradient(ellipse at 85% 85%,#135d65 0,transparent 45%),#11142b}
.auth-stage{min-height:0;flex:1 0 auto;width:100%;max-width:1240px;margin:0 auto;display:grid;grid-template-columns:minmax(0,1fr) minmax(350px,420px);grid-template-rows:auto 1fr;grid-template-areas:"brand brand" "story panel";align-items:center;gap:clamp(22px,5vw,76px);padding:clamp(20px,4vw,50px) clamp(20px,6vw,84px)}
.auth-brand{grid-area:brand;display:flex;align-items:center;gap:9px;color:#fff;font-size:1.05rem;font-weight:800;letter-spacing:-.02em}
.auth-brand-mark{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(145deg,#937dff,#5132d2);box-shadow:0 10px 24px #07071855}
.login-story{grid-area:story;color:#fff;max-width:570px;animation:entryUp .6s both}.login-story .app-chip{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:99px;background:#ffffff16;border:1px solid #ffffff2a;color:#e6e1ff;font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.login-story h1{font-size:clamp(2.5rem,5.5vw,4.6rem);font-weight:800;letter-spacing:-.065em;line-height:1.02;margin:20px 0 16px}.login-story h1 span{color:#67e4c6}.login-story p{color:#e0def2;line-height:1.75;max-width:480px}.login-points{display:flex;flex-wrap:wrap;gap:9px;margin-top:24px}.login-points span{font-size:.74rem;color:#f5f3ff;background:#ffffff12;border:1px solid #ffffff2b;border-radius:99px;padding:8px 11px}
.login-panel{grid-area:panel;position:relative;z-index:2;width:100%;max-width:420px;justify-self:end;animation:panelIn .65s .06s both}.login-panel .glass-card{max-width:none;width:100%;padding:clamp(20px,3vw,32px);border-radius:24px;box-shadow:0 30px 85px #07071855}.login-panel h3{color:#241b48!important;font-size:1.35rem}.login-panel p.text-muted{color:#5f5b78!important}.login-panel .form-label{color:#514a70!important;font-size:.78rem}.login-panel .form-control{min-height:50px;font-size:16px;background:#fafbff}.login-panel .form-control::placeholder{color:#625d78;opacity:1}.login-panel .btn-grad{min-height:48px}.login-panel .create-account{color:#514a70}
.app-splash{position:fixed;inset:0;z-index:2500;display:grid;place-items:center;background:radial-gradient(circle at 50% 35%,#5c47bd,#171431 70%);color:#fff;transition:opacity .35s,visibility .35s}.app-splash.is-hidden{opacity:0;visibility:hidden;pointer-events:none}.splash-core{text-align:center;animation:splashFloat 1.2s ease-in-out infinite alternate}.splash-logo{width:76px;height:76px;border:1px solid #ffffff35;border-radius:25px;display:grid;place-items:center;margin:0 auto 15px;background:linear-gradient(145deg,#937dff,#5132d2);box-shadow:0 16px 42px #0004}.splash-logo svg{width:40px;height:40px}.splash-name{font-weight:800;letter-spacing:.02em}.splash-line{width:90px;height:3px;border-radius:99px;background:#ffffff25;margin:17px auto 0;overflow:hidden}.splash-line:after{content:"";display:block;height:100%;width:38%;border-radius:99px;background:#62e3c4;animation:loadingLine .85s ease-in-out infinite alternate}
@keyframes entryUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}@keyframes panelIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}@keyframes splashFloat{to{transform:translateY(-5px)}}@keyframes loadingLine{to{transform:translateX(165%);width:55%}}
@media(prefers-color-scheme:dark){.login-panel .glass-card{background:#181737!important;color:#fff!important;border-color:#ffffff24}.login-panel h3{color:#fff!important}.login-panel p.text-muted{color:#b8b3e0!important}.login-panel .form-label{color:#e3defc!important}.login-panel .form-control{background:#11132b!important;color:#fff!important;border-color:#554d7a!important}.login-panel .form-control::placeholder{color:#b8b3e0!important;opacity:1}.login-panel .create-account{color:#e3defc}.login-panel a{color:#c0b5ff!important}}
@media(max-width:768px){.auth-stage{display:grid;grid-template-columns:minmax(0,1fr);grid-template-rows:auto auto auto;grid-template-areas:"brand" "story" "panel";align-content:start;align-items:start;gap:9px;max-width:452px;padding:calc(12px + env(safe-area-inset-top)) 16px 10px}.auth-brand{justify-content:center;font-size:.98rem}.auth-brand-mark{width:34px;height:34px;border-radius:11px}.login-story{max-width:100%;margin:0;text-align:center}.login-story .app-chip,.login-story p,.login-points{display:none}.login-story h1{font-size:22px;line-height:1.2;letter-spacing:-.025em;margin:3px 0 2px}.login-panel{justify-self:center;max-width:420px}.login-panel .glass-card{padding:16px;border-radius:20px}.login-panel h3{font-size:1.15rem}.login-panel p.text-muted{font-size:.85rem!important;margin-bottom:.65rem!important}.login-panel .mb-3{margin-bottom:.65rem!important}.login-panel .mb-2{margin-bottom:.45rem!important}.login-panel .form-label{font-size:.72rem;margin-bottom:.25rem}.login-panel .form-control{min-height:46px;padding:.6rem .85rem}.login-panel .btn-grad{margin-top:.55rem!important}.login-panel .create-account{margin-top:.7rem!important;font-size:.82rem!important}body.auth .royal-whatsapp{right:14px;bottom:calc(10px + env(safe-area-inset-bottom))}}
@media(max-height:700px) and (max-width:768px){.auth-stage{gap:6px;padding-top:calc(8px + env(safe-area-inset-top));padding-bottom:6px}.login-story h1{font-size:21px}.login-panel .glass-card{padding:13px 15px}.login-panel p.text-muted{margin-bottom:.45rem!important}.login-panel .mb-3{margin-bottom:.45rem!important}.login-panel .form-control{min-height:43px}.login-panel .btn-grad{min-height:44px}.login-panel .create-account{margin-top:.45rem!important}}
@media(max-width:430px){.site-footer nav{padding-right:92px}.site-footer nav a{position:relative;left:46px}}
@media(prefers-reduced-motion:reduce){*,*:before,*:after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}
</style>');
?>
<div class="app-splash" id="appSplash" aria-label="Loading Royal SMM sign in"><div class="splash-core"><div class="splash-logo"><?= ui_crown_svg(40) ?></div><div class="splash-name"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?> SMM</div><div class="splash-line"></div></div></div>
<main class="auth-stage" id="mainContent">
  <header class="auth-brand"><span class="auth-brand-mark"><?= ui_crown_svg(23) ?></span><span>Royal SMM</span></header>
  <section class="login-story" aria-labelledby="loginHeadline"><span class="app-chip"><i class="bi bi-stars" aria-hidden="true"></i> Your creator workspace</span><h1 id="loginHeadline">Make your next move <span>count.</span></h1><p>Sign in to discover social media services, manage orders, and follow every update from your Royal SMM dashboard.</p><div class="login-points"><span>Mobile-ready panel</span><span>Clear order tracking</span><span>Creator-friendly tools</span></div></section>
  <section class="login-panel" aria-label="Sign in"><div class="glass-card">
    <h3 class="fw-bold text-center mb-1">Sign in</h3>
    <p class="text-center text-muted mb-4" style="font-size:.9rem;">Welcome back. Sign in to continue.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger rounded-4 border-0 py-2"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">Username or email</label>
            <input type="text" name="username" class="form-control" placeholder="royal" required autofocus>
        </div>
        <div class="mb-2">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <?php captcha_widget(); ?>
        <button type="submit" class="btn-grad mt-3"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> SIGN IN</button>
    </form>

    <p class="text-center create-account mt-4 mb-0" style="font-size:.9rem;">
        New to Royal SMM? <a href="register.php" class="fw-semibold">Create an account</a>
    </p>
  </div></section>
</main>
<script>
(function(){
  const splash=document.getElementById('appSplash');
  const hide=()=>{if(splash){splash.classList.add('is-hidden');setTimeout(()=>splash.remove(),400);}};
  window.addEventListener('load',()=>setTimeout(hide,350),{once:true});
  setTimeout(hide,1200);
})();
</script>
<?php ui_foot(); ?>
