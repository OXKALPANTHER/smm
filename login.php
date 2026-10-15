<?php
require_once 'config.php';
require_once 'includes/ui.php';

if (isLoggedIn()) { header("Location: index.php"); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

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

ui_head('Sign in — ' . APP_NAME, 'auth', '<meta name="theme-color" content="#191536"><style>body.auth{padding:0;min-height:100svh;overflow-x:hidden;background:radial-gradient(ellipse at 16% 20%,#5944ba 0,transparent 44%),radial-gradient(ellipse at 85% 85%,#135d65 0,transparent 45%),#11142b}.auth-stage{min-height:100svh;width:100%;display:grid;grid-template-columns:minmax(0,1fr) minmax(350px,440px);align-items:center;gap:clamp(25px,8vw,110px);padding:clamp(24px,6vw,84px);max-width:1240px;margin:auto}.login-story{color:#fff;max-width:570px;animation:entryUp .7s both}.login-story .app-chip{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:99px;background:#ffffff16;border:1px solid #ffffff2a;color:#d6d1ff;font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.login-story h1{font-size:clamp(2.7rem,6vw,5rem);font-weight:800;letter-spacing:-.07em;line-height:1.02;margin:22px 0 16px}.login-story h1 span{color:#67e4c6}.login-story p{color:#d5d6ea;line-height:1.8;max-width:480px}.login-points{display:flex;flex-wrap:wrap;gap:10px;margin-top:27px}.login-points span{font-size:.74rem;color:#f2f1ff;background:#ffffff12;border:1px solid #ffffff20;border-radius:99px;padding:9px 12px}.login-panel{position:relative;z-index:2;width:100%;max-width:430px;justify-self:end;animation:panelIn .75s .08s both}.login-panel .glass-card{max-width:none;padding:clamp(24px,5vw,38px);border-radius:27px;box-shadow:0 30px 85px #07071855}.login-panel .form-control{min-height:52px}.login-panel .brand-icon{width:62px;height:62px}.app-splash{position:fixed;inset:0;z-index:2500;display:grid;place-items:center;background:radial-gradient(circle at 50% 35%,#5c47bd,#171431 70%);color:white;transition:opacity .45s,visibility .45s}.app-splash.is-hidden{opacity:0;visibility:hidden}.splash-core{text-align:center;animation:splashFloat 1.2s ease-in-out infinite alternate}.splash-logo{width:76px;height:76px;border:1px solid #ffffff35;border-radius:25px;display:grid;place-items:center;margin:0 auto 15px;background:linear-gradient(145deg,#937dff,#5132d2);box-shadow:0 16px 42px #0004}.splash-logo svg{width:40px;height:40px}.splash-name{font-weight:800;letter-spacing:.02em}.splash-line{width:90px;height:3px;border-radius:99px;background:#ffffff25;margin:17px auto 0;overflow:hidden}.splash-line:after{content:"";display:block;height:100%;width:38%;border-radius:99px;background:#62e3c4;animation:loadingLine .85s ease-in-out infinite alternate}@keyframes entryUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}@keyframes panelIn{from{opacity:0;transform:translateY(14px) scale(.98)}to{opacity:1;transform:none}}@keyframes splashFloat{to{transform:translateY(-7px)}}@keyframes loadingLine{to{transform:translateX(165%);width:55%}}@media(max-width:780px){body.auth{align-items:stretch}.auth-stage{grid-template-columns:1fr;align-content:center;gap:24px;min-height:100svh;padding:24px 18px 32px}.login-story{max-width:440px;margin:0 auto;text-align:center}.login-story h1{font-size:clamp(2.25rem,11vw,3.5rem);margin:15px 0 10px}.login-story p{font-size:.87rem;margin:0 auto}.login-points{justify-content:center;margin-top:16px}.login-points span{font-size:.68rem;padding:7px 10px}.login-panel{justify-self:center;max-width:430px}.login-panel .glass-card{padding:26px 23px;border-radius:23px}.login-panel .brand-icon{width:52px;height:52px;margin-bottom:.7rem}.login-panel h3{font-size:1.3rem}.login-panel .text-center.mb-4{margin-bottom:1rem!important}}@media(max-height:740px) and (max-width:780px){.auth-stage{gap:13px;padding-top:16px;padding-bottom:18px}.login-story h1{font-size:2.25rem}.login-story p{font-size:.78rem}.login-points{display:none}.login-panel .glass-card{padding:21px}.login-panel .brand-icon{display:none}}@media(prefers-reduced-motion:reduce){*,*:before,*:after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}</style>');
?>
<div class="app-splash" id="appSplash" aria-label="Loading Royal SMM sign in"><div class="splash-core"><div class="splash-logo"><?= ui_crown_svg(40) ?></div><div class="splash-name"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?> SMM</div><div class="splash-line"></div></div></div>
<div class="auth-stage">
  <section class="login-story"><span class="app-chip"><i class="bi bi-stars"></i> Your creator workspace</span><h1>Make your next move <span>count.</span></h1><p>Sign in to discover social media services, manage orders, and follow every update from your Royal SMM dashboard.</p><div class="login-points"><span>Mobile-ready panel</span><span>Clear order tracking</span><span>Creator-friendly tools</span></div></section>
  <section class="login-panel"><div class="glass-card">
    <div class="brand-icon"><?= ui_crown_svg(36) ?></div>
    <h3 class="fw-bold text-center mb-1" style="color:var(--ink)"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?> SMM</h3>
    <p class="text-center text-muted mb-4" style="font-size:.9rem;">Welcome back. Sign in to continue.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger rounded-4 border-0 py-2"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label class="form-label">Username or email</label>
            <input type="text" name="username" class="form-control" placeholder="royal" required autofocus>
        </div>
        <div class="mb-2">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-grad mt-3"><i class="bi bi-box-arrow-in-right"></i> SIGN IN</button>
    </form>

    <p class="text-center mt-4 mb-0" style="font-size:.9rem;">
        New to Royal SMM? <a href="register.php" class="fw-semibold" style="color:var(--primary)">Create an account</a>
    </p>
  </div></section>
</div>
<script>
(function(){
  const splash=document.getElementById('appSplash');
  const hide=()=>{if(splash){splash.classList.add('is-hidden');setTimeout(()=>splash.remove(),500);}};
  window.addEventListener('load',()=>setTimeout(hide,650),{once:true});
  setTimeout(hide,1800);
})();
</script>
<?php ui_foot();
