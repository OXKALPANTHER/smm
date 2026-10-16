<?php
// Performance direction: keep the dashboard’s familiar server-rendered flow and defer nonessential payment content until requested.
require_once 'config.php';
require_once 'includes/ui.php';
$siteCssFile = __DIR__ . '/assets/style.css';
$siteStyleVersion = is_file($siteCssFile) ? substr((string) hash_file('sha256', $siteCssFile), 0, 12) : '1';
if (!isset($_SESSION['user_id'])) {
    require_once 'includes/public-prices-cache.php';
    require_once 'includes/testimonials.php';
    $homepagePriceData = royalPublicPriceSnapshot();
    $homepagePriceSingle = count($homepagePriceData) === 1;
    $homepageTestimonials = royalPublicTestimonials($conn, 6);
    $homepageTestimonialExcerpt = static function (string $text): string {
        $text = trim(strip_tags($text));
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters)) $characters = str_split($text);
        return implode('', array_slice($characters, 0, 180)) . (count($characters) > 180 ? '…' : '');
    };
    $publicBrand = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');
    $publicCanonical = htmlspecialchars(rtrim(APP_URL, '/') . '/', ENT_QUOTES, 'UTF-8');
    $publicDescription = 'Order Instagram, TikTok, YouTube and Facebook growth services in minutes. Clear prices, order tracking and mobile-money top-ups in TZS.';
    $publicOgDescription = 'Grow your social media from one simple dashboard. Browse services, compare rates, order and track progress.';
    $organizationDescription = 'Royal SMM Panel is a social media growth panel offering Instagram, TikTok, YouTube, Facebook and Telegram services with order tracking, TZS mobile-money top-ups and a reseller API.';
    $homepageFaq = [
        ['q' => 'What is an SMM panel?', 'a' => 'An SMM panel is a dashboard for browsing social-media promotion services, placing orders and tracking their status.'],
        ['q' => 'How do I pay for an SMM panel in Tanzania?', 'a' => 'Tanzanian customers using TZS can top up with the mobile-money options shown in their account. Check the available option and amount before paying.'],
        ['q' => 'Is there a refill if followers drop?', 'a' => 'Refill availability and terms vary by service. Check the service details and the order page for eligibility and any listed refill window.'],
        ['q' => 'How long does an order take?', 'a' => 'Timing depends on the selected service and provider. Review its details and track progress from your dashboard; delivery time is not guaranteed.'],
        ['q' => 'Can I resell Royal SMM services with an API?', 'a' => 'Royal SMM provides a reseller API for eligible accounts. Access is gated; review the API documentation and eligibility information in your account.'],
    ];
    $structuredData = json_encode(['@context' => 'https://schema.org', '@graph' => [
        [
            '@type' => 'Organization',
            'name' => 'Royal SMM Panel',
            'url' => rtrim(APP_URL, '/') . '/',
            'logo' => rtrim(APP_URL, '/') . '/assets/icon-512.png',
            'description' => $organizationDescription,
            'contactPoint' => ['@type' => 'ContactPoint', 'contactType' => 'customer support', 'url' => 'https://wa.me/255627417402'],
        ],
        ['@type' => 'WebSite', 'name' => 'Royal SMM Panel', 'url' => rtrim(APP_URL, '/') . '/'],
    ]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $faqStructuredData = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(static fn($item) => [
            '@type' => 'Question',
            'name' => $item['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
        ], $homepageFaq),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Royal SMM Panel | Instagram, TikTok &amp; YouTube Growth</title>
<meta name="description" content="<?= htmlspecialchars($publicDescription, ENT_QUOTES, 'UTF-8') ?>">

<meta name="robots" content="index,follow,max-image-preview:large"><link rel="canonical" href="<?= $publicCanonical ?>">
<meta property="og:type" content="website"><meta property="og:site_name" content="Royal SMM Panel"><meta property="og:title" content="Royal SMM Panel | Instagram, TikTok &amp; YouTube Growth"><meta property="og:description" content="<?= htmlspecialchars($publicOgDescription, ENT_QUOTES, 'UTF-8') ?>"><meta property="og:url" content="<?= $publicCanonical ?>"><meta property="og:image" content="<?= htmlspecialchars(rtrim(APP_URL, '/') . '/assets/og-image.png', ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="Royal SMM Panel | Instagram, TikTok &amp; YouTube Growth"><meta name="twitter:description" content="<?= htmlspecialchars($publicOgDescription, ENT_QUOTES, 'UTF-8') ?>"><meta name="twitter:image" content="<?= htmlspecialchars(rtrim(APP_URL, '/') . '/assets/og-image.png', ENT_QUOTES, 'UTF-8') ?>">
<script type="application/ld+json"><?= $structuredData ?></script>
<script type="application/ld+json"><?= $faqStructuredData ?></script>
<?php pwa_head(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" href="https://fonts.gstatic.com/s/manrope/v20/xn7_YHE41ni1AdIRqAuZuw1Bx9mbZk79FN_C-bk.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="https://fonts.gstatic.com/s/poppins/v24/pxiEyp8kv8JHgFVrJJfecg.woff2" as="font" type="font/woff2" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="assets/style.css?v=<?= $siteStyleVersion ?>">
<style>
:root{--ink:#141b36;--muted:#737c98;--purple:#6848ed;--purple2:#4726d4;--mint:#32d6b2;--line:#e8eaf3;--bg:#f8f9ff;--white:#fff}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--bg);color:var(--ink);font-family:Manrope,system-ui,sans-serif}.shell{max-width:1200px;margin:auto;padding:0 28px}.nav{height:82px;display:flex;align-items:center;justify-content:space-between;gap:20px}.brand{display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--ink);font-weight:800;font-size:1.15rem;letter-spacing:-.04em}.brand-mark{width:39px;height:39px;border-radius:13px;display:grid;place-items:center;background:linear-gradient(145deg,#907dff,#5536df);color:#fff;font-size:1.05rem;box-shadow:0 8px 18px #6445e63a}.brand em{font-style:normal;color:var(--purple)}.navlinks{display:flex;align-items:center;gap:25px}.navlinks a,.footer a{color:#59627c;text-decoration:none;font-size:.87rem;font-weight:700}.navcta{display:flex;gap:10px}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 18px;border-radius:13px;text-decoration:none;font-size:.88rem;font-weight:800;transition:transform .2s,box-shadow .2s}.btn:hover{transform:translateY(-2px)}.btn-primary{color:white;background:linear-gradient(130deg,var(--purple),var(--purple2));box-shadow:0 12px 26px #5637dc36}.btn-plain{color:var(--ink);background:#fff;border:1px solid var(--line)}.hero{min-height:620px;display:grid;grid-template-columns:1.08fr .92fr;align-items:center;gap:44px;padding:54px 0 76px;position:relative}.hero-copy{position:relative;z-index:2}.eyebrow{display:inline-flex;gap:8px;align-items:center;color:#593adf;background:#efecff;border:1px solid #e2dcff;border-radius:99px;padding:8px 12px;font-size:.73rem;font-weight:800;letter-spacing:.02em}.eyebrow i{width:7px;height:7px;border-radius:50%;background:#4bd2ac;box-shadow:0 0 0 4px #4bd2ac24}.hero h1{font:700 clamp(2.8rem,6vw,5.2rem)/1.02 'Space Grotesk',sans-serif;letter-spacing:-.065em;margin:23px 0 18px;max-width:680px}.gradient-text{background:linear-gradient(100deg,#6445e8,#17b89a);background-clip:text;-webkit-background-clip:text;color:transparent}.hero-copy>p{max-width:600px;color:var(--muted);font-size:1.05rem;line-height:1.8}.hero-acts{display:flex;gap:12px;flex-wrap:wrap;margin-top:26px}.trust{display:flex;align-items:center;gap:10px;margin-top:28px;color:#77809a;font-size:.78rem;font-weight:700}.avatars{display:flex;padding-left:4px}.avatars i{font-style:normal;margin-left:-4px;width:26px;height:26px;border:2px solid white;background:linear-gradient(135deg,#d7cbff,#b9f4e7);border-radius:50%;display:grid;place-items:center;color:#4e35d7;font-size:.61rem}.visual{height:440px;position:relative;display:grid;place-items:center}.orb{position:absolute;width:360px;height:360px;border-radius:50%;background:radial-gradient(circle at 35% 30%,#fff 0,#eeeaff 38%,#dcd4ff 72%,#c5f7ea 100%);filter:drop-shadow(0 30px 50px #5542b51c);animation:float 6s ease-in-out infinite}.orb:before,.orb:after{content:'';position:absolute;border:1px solid #745de522;border-radius:50%;inset:30px;transform:rotate(-25deg) scaleX(1.45)}.orb:after{inset:65px;transform:rotate(35deg) scaleX(1.55)}.mock{position:relative;width:min(320px,80%);background:#fff;border:1px solid #ffffff;border-radius:24px;padding:19px;box-shadow:0 24px 80px #30246b29;transform:rotate(-3deg);animation:cardFloat 5s ease-in-out infinite}.mock-top{display:flex;align-items:center;justify-content:space-between;font-weight:800;font-size:.82rem}.live{color:#168e73;background:#e6faf3;border-radius:99px;padding:5px 9px;font-size:.62rem}.mock-label{color:#8991a8;font-size:.68rem;margin:24px 0 5px}.mock-num{font:700 2rem 'Space Grotesk';letter-spacing:-.05em}.mock-chart{height:94px;display:flex;align-items:end;gap:8px;margin-top:16px}.mock-chart i{display:block;flex:1;border-radius:8px 8px 3px 3px;background:linear-gradient(180deg,#8972ff,#5b3ce4);animation:bars 2.2s ease-in-out infinite alternate}.mock-chart i:nth-child(1){height:34%}.mock-chart i:nth-child(2){height:49%;animation-delay:.2s}.mock-chart i:nth-child(3){height:42%;animation-delay:.35s}.mock-chart i:nth-child(4){height:66%;animation-delay:.5s}.mock-chart i:nth-child(5){height:59%;animation-delay:.65s}.mock-chart i:nth-child(6){height:82%;animation-delay:.8s}.mock-chart i:nth-child(7){height:100%;animation-delay:1s}.float-tag{position:absolute;z-index:2;padding:12px 15px;background:#fff;border:1px solid #fff;border-radius:15px;box-shadow:0 14px 35px #28204a18;font-size:.76rem;font-weight:800;animation:float 4s ease-in-out infinite}.float-tag b{color:#6546e8}.tag-one{top:52px;right:0}.tag-two{bottom:68px;left:-12px;animation-delay:-1.2s}.tag-three{bottom:4px;right:40px;animation-delay:-2s}.section{padding:78px 0}.section-head{text-align:center;max-width:690px;margin:0 auto 36px}.kicker{color:var(--purple);font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.14em}.section h2{font:700 clamp(2rem,4vw,3rem)/1.13 'Space Grotesk',sans-serif;letter-spacing:-.05em;margin:12px 0}.section-head p,.section-copy{color:var(--muted);line-height:1.8}.platforms{display:grid;grid-template-columns:repeat(6,1fr);gap:12px}.platform{padding:20px 10px;text-align:center;background:white;border:1px solid var(--line);border-radius:18px;font-size:.8rem;font-weight:800;box-shadow:0 8px 24px #1a24410a;transition:.2s}.platform:hover{transform:translateY(-4px);border-color:#cec5ff}.pico{width:38px;height:38px;border-radius:13px;margin:0 auto 10px;display:grid;place-items:center;background:#f0edff;color:#6546e8;font-size:1.05rem}.platform-logo{width:22px;height:22px;fill:currentColor;stroke:currentColor;stroke-width:1.2}.platform-logo circle{fill:none}.features{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.feature{padding:25px;background:white;border:1px solid var(--line);border-radius:22px;box-shadow:0 12px 30px #20284b08}.feature-icon{width:48px;height:48px;border-radius:16px;display:grid;place-items:center;background:#f1efff;color:#6042e7;font-size:1.2rem}.feature h3{font-size:1rem;margin:18px 0 8px}.feature p{font-size:.86rem;line-height:1.75;color:var(--muted);margin:0}.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;counter-reset:steps}.step{padding:25px;border-radius:20px;background:linear-gradient(145deg,#fff,#f7f6ff);border:1px solid var(--line)}.step-no{font:700 1.7rem 'Space Grotesk';color:#8b78f4}.step h3{font-size:.98rem;margin:14px 0 8px}.step p{font-size:.84rem;color:var(--muted);line-height:1.7;margin:0}.global-note{display:flex;align-items:flex-start;gap:14px;background:#151d3a;color:#fff;border-radius:24px;padding:27px 30px;margin:18px 0}.global-note .pico{margin:0;flex:0 0 auto;background:#ffffff16;color:#72e3c7}.global-note p{color:#c3c9dc;line-height:1.7;margin:6px 0 0;font-size:.84rem}.faq{max-width:800px;margin:auto}.faq details{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px 20px;margin:10px 0}.faq summary{cursor:pointer;font-weight:800;font-size:.9rem}.faq details p{color:var(--muted);line-height:1.75;font-size:.86rem;margin:12px 0 0}.cta{padding:48px 25px;text-align:center;border-radius:28px;background:radial-gradient(500px 180px at 50% 0,#765ff6,transparent 80%),linear-gradient(135deg,#20194d,#35217f);color:white;position:relative;overflow:hidden}.cta h2{font:700 clamp(2rem,4vw,3.2rem)/1.1 'Space Grotesk';letter-spacing:-.05em;margin:0 0 12px}.cta p{color:#d4d0ed;line-height:1.7;margin:0 auto 22px;max-width:580px}.cta .btn{background:#fff;color:#4932c4}.footer{padding:30px 0 42px;display:flex;justify-content:space-between;gap:20px;color:var(--muted);font-size:.77rem}.footer nav{display:flex;gap:18px}.reveal{animation:rise .7s both}@keyframes rise{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}@keyframes cardFloat{0%,100%{transform:rotate(-3deg) translateY(0)}50%{transform:rotate(-1deg) translateY(-8px)}}@keyframes bars{to{filter:saturate(.7);transform:scaleY(.86);transform-origin:bottom}}@media(max-width:900px){.hero{grid-template-columns:1fr .82fr;gap:12px;min-height:560px}.visual{transform:scale(.9)}.platforms{grid-template-columns:repeat(3,1fr)}}@media(max-width:650px){.shell{padding:0 18px}.nav{height:70px}.navlinks{display:none}.navcta .btn{padding:10px 12px;font-size:.77rem}.brand{font-size:1rem}.brand-mark{width:34px;height:34px}.hero{grid-template-columns:1fr;padding:55px 0 35px;gap:0}.hero h1{font-size:clamp(2.8rem,13vw,4.15rem)}.hero-copy>p{font-size:.95rem}.visual{height:360px;margin-top:5px;transform:none}.orb{width:290px;height:290px}.mock{width:min(290px,78%)}.tag-one{right:1px;top:20px}.tag-two{left:0;bottom:40px}.tag-three{right:5px}.section{padding:54px 0}.features,.steps{grid-template-columns:1fr}.platforms{grid-template-columns:repeat(3,1fr);gap:9px}.platform{padding:15px 7px}.global-note{padding:22px}.footer{flex-direction:column;padding-bottom:28px}}@media(prefers-reduced-motion:reduce){*,*:before,*:after{scroll-behavior:auto!important;animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}
.price-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.price-card{display:flex;flex-direction:column;gap:8px;padding:20px;border:1px solid var(--line);border-radius:18px;background:#fff;color:var(--ink);box-shadow:0 10px 28px #20284b08}.price-card strong{font-size:1rem}.price-card span{color:var(--muted);font-size:.82rem}.price-card b{color:var(--purple)}.price-card small{color:var(--purple);font-weight:800;font-size:.72rem}.price-card .service-name{font-size:.76rem;line-height:1.4;overflow-wrap:anywhere}.price-card.empty{grid-column:1/-1;text-align:center;color:var(--muted)}.skeleton{display:block;height:14px;width:75%;border-radius:99px;background:linear-gradient(90deg,#eef0f8 25%,#fafbff 50%,#eef0f8 75%);background-size:200% 100%;animation:sk 1.2s infinite}.skeleton.short{width:48%}.trust-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.trust-grid article{padding:22px;border:1px solid var(--line);border-radius:20px;background:#fff}.trust-mark{width:42px;height:42px;border-radius:14px;background:#efecff;color:var(--purple);display:grid;place-items:center;font-weight:900;font-size:1.2rem}.trust-grid h3{font-size:1rem;margin:14px 0 7px}.trust-grid p{color:var(--muted);font-size:.84rem;line-height:1.7;margin:0}.payments{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:18px;padding:16px 18px;border-radius:16px;background:#151d3a;color:#fff;font-size:.78rem}.payments span{display:inline-flex;align-items:center;gap:6px;color:#d7def3;background:#ffffff12;border-radius:99px;padding:8px 11px}.platform-logo{display:block}@keyframes sk{0%{background-position:200% 0}100%{background-position:-200% 0}}@media(max-width:650px){.price-grid,.trust-grid{grid-template-columns:1fr}.payments{align-items:flex-start;flex-direction:column}}

/* Phone-first refinements: keep the hero legible, visuals contained, and controls easy to tap. */
html{scroll-padding-top:18px}body{overflow-x:hidden}.btn,.navcta a,.navlinks a{min-height:44px}.btn:focus-visible,a:focus-visible,button:focus-visible,input:focus-visible,summary:focus-visible{outline:3px solid #19a884;outline-offset:3px}.nav{gap:10px}.navcta{flex:0 0 auto}.navlinks a,.footer a{display:inline-flex;align-items:center;min-height:44px}.hero h1{max-width:760px;overflow-wrap:anywhere}.price-card{min-width:0;overflow-wrap:anywhere}.platform-logo{width:23px;height:23px;display:block;fill:currentColor;stroke:currentColor;stroke-width:1.2}.platform-logo rect,.platform-logo circle{fill:none}.footer nav{flex-wrap:wrap}.royal-whatsapp{position:fixed;right:16px;bottom:calc(18px + env(safe-area-inset-bottom));z-index:1199;display:inline-flex;align-items:center;justify-content:center;width:50px;height:50px;border-radius:50%;background:#25d366;color:#073b1d;box-shadow:0 10px 26px #25d36655;text-decoration:none}.royal-whatsapp svg{width:25px;height:25px;fill:currentColor}
@media(max-width:650px){.shell{padding-left:18px;padding-right:18px}.nav{height:auto;min-height:70px;flex-wrap:wrap;padding-top:10px;padding-bottom:10px}.navcta .btn{padding:8px 10px;font-size:.76rem}.hero{padding-top:38px;padding-bottom:28px}.hero h1{font-size:clamp(2.15rem,10.4vw,3.15rem);line-height:1.06;letter-spacing:-.055em}.hero-copy>p{font-size:.94rem;line-height:1.65}.hero-acts{margin-top:18px}.visual{height:320px;margin-top:12px;overflow:hidden}.orb{width:min(76vw,280px);height:min(76vw,280px)}.mock{width:min(290px,78vw);padding:16px;transform:none;animation:none}.mock-num{font-size:1.7rem}.tag-one{right:2px;top:14px}.tag-two{left:0;bottom:34px}.tag-three{right:2px;bottom:0}.float-tag{font-size:.68rem;padding:9px 11px}.platforms{grid-template-columns:repeat(2,minmax(0,1fr))}.platform{min-height:88px;display:flex;flex-direction:column;justify-content:center}.section{padding:48px 0}.price-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.price-card{padding:14px}.price-card strong{font-size:.9rem}.price-card span{font-size:.75rem}.price-card .service-name{font-size:.72rem}.footer{gap:12px}.footer nav{gap:6px 14px}.royal-whatsapp{right:14px;bottom:calc(18px + env(safe-area-inset-bottom));width:48px;height:48px;min-height:48px;padding:0;border-radius:50%;justify-content:center;gap:0}.royal-whatsapp span{display:none}.royal-whatsapp i{font-size:1.2rem}}
@media(max-width:360px){.navcta .btn{font-size:.7rem;padding:8px}.hero h1{font-size:2.05rem}.price-grid{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){*,*:before,*:after{scroll-behavior:auto!important;animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}

@media(prefers-color-scheme:dark){
 :root{color-scheme:dark;--ink:#edf1ff;--muted:#b5c0d9;--line:#303958;--bg:#0d1428;--white:#18213c}
 body{background:#0d1428;color:#edf1ff}
 .section[style*="#fff"]{background:#0d1428!important}
 .navlinks a,.footer a{color:#c5cee3}
 .btn-plain{background:#18213c;color:#edf1ff;border-color:#303958}
 .eyebrow{background:#252144;color:#e0dbff;border-color:#423a70}
 .trust{color:#c1cae0}.avatars i{border-color:#0d1428}
 .platform,.feature,.step,.price-card,.faq details,.trust-grid article{background:#18213c;color:#edf1ff;border-color:#303958}
 .platform .pico,.feature-icon,.trust-mark{background:#29234f;color:#d2caff}
 .section-head p,.section-copy,.feature p,.step p,.price-card span,.faq details p,.trust-grid p{color:#b5c0d9}
 .price-card b,.price-card small{color:#c8bfff}
 .global-note{background:#111a30;color:#f1f4ff}.global-note p{color:#c5cee3}
 .mock,.float-tag{background:#18213c;color:#edf1ff;border-color:#303958}
 .mock-label,.mock-top div,.mock>div[style]{color:#c5cee3!important}
 .mock-num{color:#f1f4ff}.float-tag b{color:#c8bfff}
 .payments{background:#090f20;color:#f1f4ff}
}

.shell{padding-left:16px;padding-right:16px}.section .shell{padding-left:16px;padding-right:16px}.price-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.price-details-cta{margin-top:20px!important}.testimonials-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.testimonial-card{min-width:0;overflow-wrap:anywhere}@media(max-width:650px){.price-grid,.testimonials-grid{grid-template-columns:1fr}.shell,.section .shell{padding-left:16px;padding-right:16px}}.royal-whatsapp{z-index:1300;right:16px;bottom:18px}.price-card.empty{grid-column:1/-1}
</style>
</head>
<body>
<main>
<header class="shell nav"><a class="brand" href="/" aria-label="<?= $publicBrand ?> home"><span class="brand-mark">R</span><span><?= $publicBrand ?> <em>SMM</em></span></a><nav class="navlinks"><a href="#services">Services</a><a href="#how-it-works">How it works</a><a href="#faq">FAQ</a></nav><div class="navcta"><a class="btn btn-plain" href="login.php">Log in</a><a class="btn btn-primary" href="register.php">Create account <span aria-hidden="true">↗</span></a></div></header>
<section class="shell hero"><div class="hero-copy reveal"><span class="eyebrow"><i></i> YOUR SOCIAL GROWTH, IN ONE PLACE</span><h1>Grow your social media. Order in under a minute.</h1><p>Royal SMM Panel is a social media marketing panel for creators, businesses and resellers in Tanzania and worldwide. Choose from Instagram, TikTok, YouTube, Facebook and Telegram services, see the rate and limits up front, pay by mobile money, and track every order from your dashboard.</p><div class="hero-acts"><a class="btn btn-primary" href="register.php">Create your account <span aria-hidden="true">→</span></a></div><div class="trust"><span class="avatars"><i>R</i><i>◎</i><i>▶</i></span><span>Simple ordering · Clear order tracking · Mobile-ready</span></div></div><div class="visual" aria-label="Animated preview of the social media growth dashboard"><div class="orb"></div><div class="mock"><div class="mock-top"><span>Growth overview</span><span class="live">Illustrative overview</span></div><div class="mock-label">Campaign activity</div><div class="mock-num">Track every order</div><div class="mock-chart"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div><div style="display:flex;justify-content:space-between;color:#9098ae;font-size:.61rem;margin-top:9px"><span>Explore services</span><span>Monitor progress</span></div></div><div class="float-tag tag-one">✦ <b>One dashboard</b></div><div class="float-tag tag-two">↗ Orders made simple</div><div class="float-tag tag-three">◎ Creator-friendly</div></div></section>
<section class="section" id="services" style="background:#fff"><div class="shell"><div class="section-head"><span class="kicker">Explore the ecosystem</span><h2>Instagram growth services, TikTok views and likes, and YouTube promotion</h2><p>Browse Instagram, TikTok, YouTube, Facebook and Telegram options. Current service details, requirements and starting rates are shown in the catalogue before you order.</p></div><div class="platforms">
<div class="platform"><div class="pico"><svg class="platform-logo" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.8" r="1.2"/></svg></div>Instagram</div>
<div class="platform"><div class="pico"><svg class="platform-logo" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3v11.2a4.3 4.3 0 1 1-3.6-4.25v3.1a1.3 1.3 0 1 0 .6 1.1V3h3c.3 2.1 1.5 3.5 3.8 4v3.1A8 8 0 0 1 14 8.3z"/></svg></div>TikTok</div>
<div class="platform"><div class="pico"><svg class="platform-logo" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 7.2a2.5 2.5 0 0 0-1.8-1.8C17.6 5 12 5 12 5s-5.6 0-7.2.4A2.5 2.5 0 0 0 3 7.2 26 26 0 0 0 2.6 12 26 26 0 0 0 3 16.8a2.5 2.5 0 0 0 1.8 1.8C6.4 19 12 19 12 19s5.6 0 7.2-.4a2.5 2.5 0 0 0 1.8-1.8 26 26 0 0 0 .4-4.8 26 26 0 0 0-.4-4.8ZM10 15.2V8.8l5.5 3.2z"/></svg></div>YouTube</div>
<div class="platform"><div class="pico"><svg class="platform-logo" viewBox="0 0 24 24" aria-hidden="true"><path d="M13.4 21v-8h2.7l.4-3.1h-3.1v-2c0-.9.3-1.5 1.6-1.5h1.7V3.6c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.1H7.2V13H10v8z"/></svg></div>Facebook</div>
<div class="platform"><div class="pico"><svg class="platform-logo" viewBox="0 0 24 24" aria-hidden="true"><path d="M21.7 3.4 18.4 20c-.2 1.2-.9 1.5-1.9.9l-5.2-3.8-2.5 2.4c-.3.3-.5.5-1 .5l.4-5.3 9.7-8.8c.4-.4-.1-.6-.6-.2L5.3 13.2.2 11.6c-1.1-.4-1.1-1.1.2-1.6L20 2.4c.9-.3 1.9.2 1.7 1z"/></svg></div>Telegram</div>
<div class="platform"><div class="pico"><svg class="platform-logo" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.9 2H22l-6.8 7.8L23.2 22h-6.3L12 14.8 5.7 22H2.5l7.3-8.4L1.8 2h6.4l4.5 6.7zM17.8 20h1.7L7.2 3.9H5.4z"/></svg></div>X &amp; more</div></div></div></section>
<section class="section"><div class="shell"><div class="section-head"><span class="kicker">Designed for momentum</span><h2>Everything you need to stay in control</h2><p>Spend less time jumping between tools and more time choosing the right service for your next campaign.</p></div><div class="features"><article class="feature"><div class="feature-icon">⌕</div><h3>Find services faster</h3><p>Search and filter the live catalogue by platform. Compare descriptions, minimums, maximums, and listed rates before submitting.</p></article><article class="feature"><div class="feature-icon">↗</div><h3>Place orders with clarity</h3><p>Choose a service, provide the public link or username it requires, set a quantity, and review the order amount before confirmation.</p></article><article class="feature"><div class="feature-icon">◷</div><h3>Follow progress</h3><p>Check order status and account activity in one place. Availability and completion times depend on the selected service.</p></article></div></div></section>
<section class="section" id="how-it-works" style="background:#fff"><div class="shell"><div class="section-head"><span class="kicker">A clear path from sign-up to tracking</span><h2>Get started in three steps</h2><p>Your panel keeps the workflow straightforward, whether you manage one profile or multiple campaigns.</p></div><div class="steps"><article class="step"><div class="step-no">01</div><h3>Create your account</h3><p>Register and open your dashboard to explore the currently available services and account tools.</p></article><article class="step"><div class="step-no">02</div><h3>Choose and submit</h3><p>Review each service's requirements and price, enter the target link and quantity, then confirm your order.</p></article><article class="step"><div class="step-no">03</div><h3>Monitor and manage</h3><p>Return to your dashboard to follow progress, view order history, and manage your account balance.</p></article></div><div class="global-note"><div class="pico">◎</div><div><strong>SMM panel Tanzania, available worldwide</strong><p>Royal SMM is designed for creators, businesses, and resellers in different markets. Service availability can vary. Payment methods and currency are shown in the account checkout; Tanzanian mobile-money payments are available for customers using TZS.</p></div></div></div></section>
<section class="section" id="live-pricing" style="background:#fff"><div class="shell"><div class="section-head"><span class="kicker">Live catalogue snapshot</span><h2>Starting prices you can verify</h2><p>These are current starting rates from the service catalogue. Open the live panel before ordering because provider availability and rates can change.</p></div><div class="price-grid<?= $homepagePriceSingle ? ' single' : '' ?>" id="publicPrices" aria-live="polite">
<?php if (!empty($homepagePriceData)): foreach ($homepagePriceData as $price): ?>
<a class="price-card" href="services.php"><strong><?= htmlspecialchars((string) $price['platform'], ENT_QUOTES, 'UTF-8') ?></strong><span class="service-name"><?= htmlspecialchars((string) ($price['name'] ?: 'Catalogue service'), ENT_QUOTES, 'UTF-8') ?></span><span>From <b><?= number_format((int) $price['price']) ?> <?= htmlspecialchars((string) $price['currency'], ENT_QUOTES, 'UTF-8') ?></b> / 1,000</span><small>View live service details →</small></a>
<?php endforeach; else: ?>
<div class="price-card empty"><p>Current pricing is temporarily unavailable.</p><div class="d-flex justify-content-center gap-2 flex-wrap"><button id="retryPublicPrices" class="btn btn-primary" type="button">Retry</button><a class="btn btn-plain" href="pricing.php">See pricing</a></div></div>
<?php endif; ?>
</div><div class="text-center price-details-cta"><a class="btn btn-primary" href="pricing.php">See pricing details <span aria-hidden="true">→</span></a></div></div></section>
<?php if (empty($homepagePriceData)): ?><script>
(function(){const root=document.getElementById('publicPrices');if(!root)return;function fallback(message){root.classList.remove('single');root.replaceChildren();const box=document.createElement('div');box.className='price-card empty';const p=document.createElement('p');p.textContent=message||'Current pricing is temporarily unavailable.';const actions=document.createElement('div');actions.className='d-flex justify-content-center gap-2 flex-wrap';const retry=document.createElement('button');retry.type='button';retry.className='btn btn-primary';retry.textContent='Retry';const pricing=document.createElement('a');pricing.className='btn btn-plain';pricing.href='pricing.php';pricing.textContent='See pricing';actions.append(retry,pricing);box.append(p,actions);root.append(box);retry.addEventListener('click',load);}function render(rows){root.classList.toggle('single',rows.length===1);root.replaceChildren();rows.forEach(function(item){const card=document.createElement('a');card.className='price-card';card.href='services.php';const title=document.createElement('strong');title.textContent=item.platform||'Social media';const name=document.createElement('span');name.className='service-name';name.textContent=item.name||'Catalogue service';const price=document.createElement('span');price.append('From ');const amount=document.createElement('b');amount.textContent=Number(item.price).toLocaleString()+' '+(item.currency||'TZS');price.append(amount,' / 1,000');const more=document.createElement('small');more.textContent='View live service details →';card.append(title,name,price,more);root.append(card);});}function load(){const retry=root.querySelector('button');if(retry){retry.disabled=true;retry.textContent='Checking…';}const controller=new AbortController();const timer=setTimeout(()=>controller.abort(),4000);fetch('public-prices.php',{headers:{Accept:'application/json'},signal:controller.signal,cache:'no-store'}).then(function(response){if(!response.ok)throw new Error('unavailable');return response.json();}).then(function(payload){if(payload&&payload.success&&Array.isArray(payload.data)&&payload.data.length){render(payload.data);}else{fallback('Current pricing is temporarily unavailable.');}}).catch(function(){fallback('Current pricing is temporarily unavailable.');}).finally(function(){clearTimeout(timer);});}load();})();
</script><?php endif; ?><section class="section trust-section"><div class="shell"><div class="section-head"><span class="kicker">Built for confident growth</span><h2>SMM panel mobile money support for Tanzania</h2><p>Royal SMM keeps the important details visible before you spend.</p></div><div class="trust-grid"><article><div class="trust-mark">✓</div><h3>Live service details</h3><p>Compare service limits, refill terms, and current rates from the catalogue.</p></article><article><div class="trust-mark">₸</div><h3>TZS top-ups</h3><p>Add wallet balance using the payment options available to your account.</p></article><article><div class="trust-mark">↗</div><h3>Order visibility</h3><p>Track progress, status updates, and order history from a mobile-ready dashboard.</p></article></div><div class="payments"><strong>Payment options shown in your account</strong><span><i class="bi bi-phone-fill"></i> Mobile money</span><span><i class="bi bi-wallet2"></i> Wallet balance</span><span><i class="bi bi-shield-check"></i> Secure checkout</span></div></div></section><?php if (count($homepageTestimonials) >= 3): ?>
<section class="section" id="verified-results"><div class="shell"><div class="section-head"><span class="kicker">Customer outcomes</span><h2>Verified results</h2><p>Reviews shared with permission from Royal SMM customers.</p></div><div class="testimonials-grid">
<?php foreach ($homepageTestimonials as $review): $reviewRating = max(1, min(5, (int) $review['rating'])); $reviewName = royalTestimonialPublicName((string) $review['display_name']); $reviewExcerpt = $homepageTestimonialExcerpt((string) $review['message']); ?>
<article class="testimonial-card"><div class="testimonial-stars" role="img" aria-label="<?= $reviewRating ?> out of 5 stars"><?= str_repeat('★', $reviewRating) . str_repeat('☆', 5 - $reviewRating) ?></div><blockquote>“<?= htmlspecialchars($reviewExcerpt, ENT_QUOTES, 'UTF-8') ?>”</blockquote><div class="testimonial-byline"><span><?= htmlspecialchars($reviewName, ENT_QUOTES, 'UTF-8') ?></span><span>· <?= htmlspecialchars((string) $review['platform'], ENT_QUOTES, 'UTF-8') ?></span><?php if ((int) $review['verified_customer'] === 1): ?><span class="verified-label"><i class="bi bi-patch-check-fill" aria-hidden="true"></i>Verified customer</span><?php endif; ?></div></article>
<?php endforeach; ?>
</div></div></section>
<?php endif; ?>
<section class="section" id="faq"><div class="shell"><div class="section-head"><span class="kicker">Good to know</span><h2>Frequently asked questions about the SMM reseller API and services</h2></div><div class="faq"><?php foreach ($homepageFaq as $item): ?><details><summary><?= htmlspecialchars($item['q'], ENT_QUOTES, 'UTF-8') ?></summary><p><?= htmlspecialchars($item['a'], ENT_QUOTES, 'UTF-8') ?></p></details><?php endforeach; ?></div></div></section>
<section class="shell" style="padding-bottom:55px"><div class="cta"><h2>Ready to make your next move?</h2><p>Create your account to explore the live service catalogue and manage your social media orders in one place.</p><a class="btn" href="register.php">Create your free account <span>→</span></a></div></section>
<footer class="shell footer"><span>© <?= date('Y') ?> <?= $publicBrand ?> SMM Panel · Social growth tools for creators worldwide.<br></span><nav><a href="services.php">Services</a><a href="pricing.php">Pricing</a><a href="api.php">SMM reseller API</a><a href="terms.php">Terms</a><a href="privacy.php">Privacy</a><a href="refund-refill.php">Refund/Refill</a><a href="https://wa.me/<?= rawurlencode(WHATSAPP_SUPPORT_PHONE) ?>">Support</a></nav></footer>
</main><?php pwa_foot(); ?></body></html>
<?php
    exit;
}
requireLogin();
$user_id = $_SESSION['user_id'];

// Current user + balance
$stmt = $conn->prepare("SELECT username, email, phone, balance, is_verified FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$tzsBalance = (float)($user['balance'] ?? 0);
$username   = $user['username'] ?? 'Mteja';
$isVerified = (int) ($user['is_verified'] ?? 0) === 1;
$_SESSION['verification_csrf'] = $_SESSION['verification_csrf'] ?? bin2hex(random_bytes(32));
$verificationToken = (string) $_SESSION['verification_csrf'];
$manualVerificationMessage = 'Hello Royal Admin, I would like to request manual account verification. Account ID: ' . $user_id . '; Username: ' . ($user['username'] ?? '') . '; Email: ' . ($user['email'] ?? '') . '; Phone: ' . ($user['phone'] ?? 'Not added') . '. Please review my account and let me know if you need anything else.';
$requestedServiceId = trim((string) ($_GET['service_id'] ?? ''));
$reorderQuantity = 100;
$reorderLink = '';
$reorderFavoriteId = (int) ($_GET['reorder_id'] ?? 0);
if ($reorderFavoriteId > 0) {
    $reorderStmt = $conn->prepare('SELECT o.quantity, o.link FROM favorite_orders f JOIN orders o ON o.id = f.order_id WHERE f.id = ? AND f.user_id = ?');
    $reorderStmt->bind_param('ii', $reorderFavoriteId, $user_id);
    $reorderStmt->execute();
    if ($saved = $reorderStmt->get_result()->fetch_assoc()) {
        $reorderQuantity = max(1, (int) $saved['quantity']);
        $reorderLink = (string) $saved['link'];
    }
} elseif ((int) ($_GET['reorder_order_id'] ?? 0) > 0) {
    $reorderOrderId = (int) $_GET['reorder_order_id'];
    $reorderStmt = $conn->prepare('SELECT quantity, link FROM orders WHERE id = ? AND user_id = ?');
    $reorderStmt->bind_param('ii', $reorderOrderId, $user_id);
    $reorderStmt->execute();
    if ($saved = $reorderStmt->get_result()->fetch_assoc()) {
        $reorderQuantity = max(1, (int) $saved['quantity']);
        $reorderLink = (string) $saved['link'];
    }
}

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

// Provider status refresh is explicit (`?sync=1`) so the dashboard never
// blocks on multiple live API calls during ordinary navigation.
require_once 'includes/order-sync.php';
$orderNotifications = [];
syncUserOrders($conn, $user_id, isset($_GET['sync']) && $_GET['sync'] === '1', $orderNotifications);

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
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Dashboard | Royal SMM Panel</title>
    <meta name="description" content="Your Royal SMM Panel dashboard for managing account balance, social media orders, order tracking and support.">
    <meta name="robots" content="noindex,follow">
    <link rel="canonical" href="<?= htmlspecialchars(rtrim(APP_URL, '/') . '/index.php', ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="website"><meta property="og:title" content="Dashboard | Royal SMM Panel"><meta property="og:description" content="Your Royal SMM Panel dashboard for managing account balance, social media orders, order tracking and support."><meta property="og:url" content="<?= htmlspecialchars(rtrim(APP_URL, '/') . '/index.php', ENT_QUOTES, 'UTF-8') ?>"><meta property="og:image" content="<?= htmlspecialchars(rtrim(APP_URL, '/') . '/assets/og-image.png', ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:title" content="Dashboard | Royal SMM Panel"><meta name="twitter:description" content="Your Royal SMM Panel dashboard for managing account balance, social media orders, order tracking and support."><meta name="twitter:image" content="<?= htmlspecialchars(rtrim(APP_URL, '/') . '/assets/og-image.png', ENT_QUOTES, 'UTF-8') ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preload" href="https://fonts.gstatic.com/s/poppins/v24/pxiEyp8kv8JHgFVrJJfecg.woff2" as="font" type="font/woff2" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <script>(function(){var choice='system';try{choice=localStorage.getItem('royal-theme')||'system'}catch(e){}var dark=!!(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.dataset.theme=choice==='dark'||(choice==='system'&&dark)?'dark':'light'})();</script>
    <link rel="stylesheet" href="assets/style.css?v=<?= $siteStyleVersion ?>">

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
        @media (min-width: 768px) { .container { max-width: 900px; } }
        /* Large screens use the available width instead of squeezing the order
           form and recent orders into narrow side-by-side columns. */
        @media (min-width: 1100px) {
            .container { width: calc(100% - 64px); max-width: 1480px; }
            .dashboard-content { display: block; }
            .dashboard-content > .card-soft { width: 100%; margin-top: 0 !important; }
            .dashboard-content > .card-soft + .card-soft { margin-top: 1rem !important; }
            .platform-row { grid-template-columns: repeat(auto-fit, minmax(104px, 1fr)); gap: .7rem; }
            .chip { min-height: 76px; font-size: .72rem; }
            .chip i { font-size: 1.5rem; }
            .dashboard-content > .card-soft:last-child { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); column-gap: 1.2rem; }
            .dashboard-content > .card-soft:last-child > .d-flex,
            .dashboard-content > .card-soft:last-child > .text-center { grid-column: 1 / -1; }
            .dashboard-content > .card-soft:last-child .order-item { min-width: 0; }
        }
        @media (min-width: 1600px) {
            .container { width: calc(100% - 96px); max-width: 1680px; }
            .dashboard-content > .card-soft { padding-left: 1.8rem; padding-right: 1.8rem; }
        }
        .verify-nudge{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.95rem 1.1rem;margin-top:.85rem;border:1px solid #dedaff;border-radius:18px;background:linear-gradient(110deg,#fff,#f5f3ff);box-shadow:0 8px 22px rgba(72,52,212,.06)}
        .verify-nudge .vico{width:42px;height:42px;flex:0 0 auto;display:grid;place-items:center;border-radius:14px;background:#eeebff;color:var(--primary-2);font-size:1.2rem}
        .verify-nudge p{margin:0;color:var(--muted);font-size:.76rem}
        .verify-option{border:1px solid #e9edf7;border-radius:17px;padding:1rem;background:#fbfcff;height:100%}
        .verify-option h6{font-weight:800;color:var(--ink)}.verify-option p{font-size:.78rem;color:var(--muted);line-height:1.6}
        .verify-benefits{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}.verify-benefit{font-size:.75rem;color:#4e5977;display:flex;gap:.45rem;align-items:flex-start}.verify-benefit i{color:#00a879;margin-top:2px}
        @media(max-width:575px){.verify-nudge{align-items:flex-start;flex-wrap:wrap}.verify-nudge .btn{width:100%}.verify-benefits{grid-template-columns:1fr}}

        /* Top bar */
        .topbar { padding: 1.1rem 0 0.5rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; position:static; }
        .brand { display:inline-flex; align-items:center; min-height:46px; font-weight:800; letter-spacing: -0.5px; font-size: 1.35rem; position:static; }
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
            appearance: none; width: 100%; min-height: 64px; border: 1.5px solid #e9edf7; background: #fafbff; border-radius: 16px;
            padding: .6rem .3rem; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .3rem;
            cursor: pointer; touch-action: manipulation; transition: all .15s; color: var(--muted); font: inherit; font-size: .64rem; font-weight: 600; text-align: center;
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
        .pro-banner { display: flex; align-items: center; gap: .7rem; background: linear-gradient(135deg,#f3eaff,#eef0ff); border: 1px solid #d9c7ff; color: #5b21b6; border-radius: 16px; padding: .7rem .85rem; margin: .2rem 0 .7rem; font-size: .8rem; }
        .pro-banner > i { font-size: 1.3rem; color: #7c3aed; }
        .pro-banner .pro-exit { background: none; border: none; color: #7c3aed; cursor: pointer; padding: .2rem; font-size: .85rem; }
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
        .service-results { margin-top: .85rem; border: 1px solid #e9edf7; border-radius: 18px; background: #fbfcff; padding: .75rem; }
        .service-results[hidden] { display: none; }
        .service-results-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:.6rem; color:var(--muted); font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.35px; }
        .service-results-list { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.55rem; max-height:520px; overflow:auto; padding:.1rem; }
        .service-option { width:100%; border:1px solid #e6eaf5; border-radius:14px; background:#fff; padding:.75rem .8rem; color:var(--ink); text-align:left; cursor:pointer; transition:border-color .15s, box-shadow .15s, transform .15s; }
        .service-option:hover,.service-option:focus-visible { border-color:var(--primary); box-shadow:0 8px 18px rgba(108,92,231,.13); transform:translateY(-1px); outline:none; }
        .service-option.active { border:2px solid var(--primary); background:#f7f5ff; padding:calc(.75rem - 1px) calc(.8rem - 1px); }
        .service-option-name { display:block; font-size:.82rem; font-weight:700; line-height:1.35; }
        .service-option-meta { display:flex; flex-wrap:wrap; gap:.35rem .7rem; margin-top:.35rem; color:var(--muted); font-size:.68rem; }
        .service-option-price { color:var(--primary-2); font-weight:800; }
        @media(max-width:575px){ .service-results-list{grid-template-columns:1fr;max-height:480px;} .service-option{padding:.8rem;} }

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
        .link-preview{display:none;margin-top:.7rem;padding:.85rem;border:1px solid #dfe4f2;border-radius:18px;background:linear-gradient(135deg,#fafbff,#f2f7ff);}
        .link-preview.show{display:block;}.link-preview.invalid{border-color:#f3c5bb;background:#fff8f6;}
        .link-preview-head{display:flex;align-items:center;gap:.65rem;}.link-preview-icon{width:38px;height:38px;border-radius:12px;background:#e9e6ff;color:var(--primary-2);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex:0 0 auto;}
        .link-preview-title{font-size:.78rem;font-weight:800;color:var(--ink);}.link-preview-status{font-size:.68rem;color:var(--success);}.link-preview.invalid .link-preview-status{color:var(--danger);}
        .link-preview-url{display:block;margin-top:.6rem;padding:.6rem .7rem;border-radius:11px;background:#fff;border:1px solid #e9edf7;color:#526174;font-size:.72rem;line-height:1.35;word-break:break-all;}.link-preview-open{display:inline-flex;align-items:center;gap:.35rem;margin-top:.6rem;padding:.38rem .65rem;border-radius:10px;background:#eef1ff;color:var(--primary-2);font-size:.7rem;font-weight:700;}
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
        /* Platform choices must be immediately visible and responsive; the old
           staggered entrance made the controls feel delayed on mobile. */
        .chip,.platform-row .chip:nth-child(n){animation:none;}

        @media (max-width: 767px) {
            .dashboard-content { display: flex; flex-direction: column; gap: 1rem; }
            .dashboard-content > .card-soft { width: 100%; margin-top: 0 !important; }
            .platform-row { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .7rem; }
            .chip { min-height: 86px; padding: .65rem .25rem; }
            .chip i { font-size: 1.45rem; }
        }
        @media (max-width: 380px) {
            .platform-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

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

        :root{--z-content:1;--z-header:1000;--z-nav:1200;--z-support:1300;--z-backdrop:1400;--z-drawer:1500;--z-modal:1600;--z-toast:1700;--z-loader:3000}
        body.app{padding-top:calc(116px + env(safe-area-inset-top));padding-bottom:calc(140px + env(safe-area-inset-bottom));min-height:100dvh}
        .app-header{position:fixed;inset:0 0 auto;z-index:var(--z-header);display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;padding:calc(9px + env(safe-area-inset-top)) 14px 9px;background:rgba(255,255,255,.96);backdrop-filter:blur(16px);border-bottom:1px solid rgba(108,92,231,.13);box-shadow:0 8px 24px rgba(43,54,116,.08)}
        .app-header-heading{order:1;flex:1 1 180px;min-width:0}.app-header-title{font-size:.95rem;font-weight:800;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.app-header-subtitle{display:block;margin-top:2px;font-size:.67rem;line-height:1.3;color:#59627c}
        .app-header-balance{order:4;flex:1 0 100%;width:100%;display:inline-flex;align-items:center;gap:8px;max-width:100%;min-height:38px;padding:5px 12px;border:1px solid #e4e0f6;border-radius:12px;background:#f8f7ff;color:#4932c4;font-size:.76rem;white-space:nowrap}.app-header-controls{display:contents}.nav-bell,.hamburger{width:44px;height:44px;min-height:44px;border-radius:13px;flex:0 0 44px}
        .mobile-tabbar{display:none}.royal-whatsapp{position:fixed;right:16px;bottom:18px;z-index:var(--z-support);display:inline-flex;align-items:center;gap:.4rem;min-height:48px;padding:.65rem .95rem;border-radius:999px;background:#25d366;color:#073b1d;text-decoration:none}body.app .royal-whatsapp{bottom:calc(76px + env(safe-area-inset-bottom))}
        @media(max-width:768px){.mobile-tabbar{position:fixed;left:0;right:0;bottom:0;z-index:var(--z-nav);display:grid;grid-template-columns:repeat(5,minmax(0,1fr));padding:4px 4px env(safe-area-inset-bottom);min-height:calc(56px + env(safe-area-inset-bottom));background:rgba(255,255,255,.96);border-top:1px solid #ddd8f4}.mobile-tabbar a{min-height:56px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;font-size:11px;white-space:nowrap}.site-footer{padding-bottom:calc(14px + env(safe-area-inset-bottom))}}
        @media(min-width:600px){body.app{padding-top:calc(84px + env(safe-area-inset-top))}.app-header{grid-template-columns:minmax(0,1fr) auto auto;grid-template-rows:auto}.app-header-heading{order:1;flex:1 1 auto}.app-header-balance{order:2;flex:0 0 auto;width:auto}.app-header .nav-bell{order:3;margin-left:0}.app-header .hamburger{order:4}}

.app-header .nav-bell{order:2;margin-left:auto}.app-header .hamburger{order:3}.btn-primary,.btn-primary-grad,.btn-grad{background:#4834d4;color:#fff}
@media(min-width:600px){.app-header{flex-wrap:nowrap}.app-header-heading{order:1;flex:1 1 auto}.app-header-balance{order:2;flex:0 0 auto;width:auto}.app-header .nav-bell{order:3;margin-left:0}.app-header .hamburger{order:4}}

.app-header .hamburger{position:static;inset:auto;top:auto;right:auto;z-index:auto}.price-grid.single{grid-template-columns:1fr}
</style>
    <?php pwa_head(); ?>
</head>
<body class="app">

<div class="toast-wrap" id="toastWrap"></div>

<div class="container">

    <!-- Top bar -->
    <div class="topbar">
        <div class="brand"><?= ui_logo(true, 40) ?></div>
        <div class="text-muted" style="font-size:.74rem;"><span data-i18n="common.greeting">Habari,</span> <?= htmlspecialchars($username) ?> <?= verifiedBadge($isVerified) ?> 👋<?php if ($isVerified): ?> <span class="badge rounded-pill" style="background:#e8f4ff;color:#1475c7;font-size:.65rem;"><i class="bi bi-patch-check-fill me-1"></i><span data-i18n="verification.pricing">Verified pricing</span></span><?php endif; ?></div>
    </div>

    <!-- Balance hero -->
    <div class="hero mt-2">
        <small><i class="bi bi-wallet2 me-1"></i> <span data-i18n="common.yourBalance">Salio Lako</span></small>
        <div class="amount"><span id="balanceText"><?= number_format($tzsBalance, 0) ?></span> <small>TZS</small></div>
        <a href="#" class="btn-glass" data-bs-toggle="modal" data-bs-target="#topUpModal"><i class="bi bi-plus-circle"></i> <span data-i18n="nav.topup">Ongeza Salio</span></a>
    </div>

    <!-- Stats -->
    <div class="row g-2 mt-1 mb-3">
        <div class="col-4"><div class="stat"><div class="n"><?= $stats['total'] ?></div><div class="l" data-i18n="stats.orders">Orders</div></div></div>
        <div class="col-4"><div class="stat"><div class="n text-warning"><?= $stats['pending'] ?></div><div class="l" data-i18n="stats.pending">Pending</div></div></div>
        <div class="col-4"><div class="stat"><div class="n text-success"><?= $stats['completed'] ?></div><div class="l" data-i18n="stats.done">Done</div></div></div>
    </div>

    <!-- Platform scrolling belt -->
    <div class="belt reveal-2">
        <div class="belt-track">
            <?php for ($i = 0; $i < 2; $i++): foreach ($platformsCfg as $key => $p): $ico = $platformIcons[$key] ?? 'bi-globe'; ?>
                <span class="belt-item"><i class="bi <?= $ico ?>"></i><?= htmlspecialchars($p['name']) ?></span>
            <?php endforeach; endfor; ?>
        </div>
    </div>

    <?php if (!$isVerified): ?>
    <div class="verify-nudge" id="verificationNudge"><div class="d-flex align-items-center gap-3"><div class="vico"><i class="bi bi-patch-check-fill"></i></div><div><strong class="d-block" style="font-size:.88rem;" data-i18n="verify.nudgeTitle">Unlock lower verified-account prices</strong><p data-i18n="verify.nudgeBody">Verify once to access the lower verified pricing tier and a verified account badge.</p></div></div><button type="button" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#verificationModal" data-i18n="verify.benefits">Explore benefits</button></div>
    <?php endif; ?>
    <!-- Order card -->
    <div class="dashboard-content">
    <div class="card-soft reveal-3">
            <div class="section-title mb-3">
            <div class="section-ico"><i class="bi bi-cart-plus"></i></div>
            <span data-i18n="order.title">Weka Order Mpya</span>
        </div>

        <form id="orderForm" autocomplete="off">
            <!-- Platforms + global search -->
            <label class="form-label"><span data-i18n="order.platform">1. Tafuta au Chagua Platform</span></label>
            <div class="svc-search">
                <i class="bi bi-search"></i>
                <input type="text" id="svcSearch" autocomplete="off"
                       placeholder="Tafuta jina, platform au Service ID (mf: 5557)..." data-i18n-placeholder="order.searchPlaceholder">
                <button type="button" class="clr" id="svcSearchClr" aria-label="Futa" data-i18n-aria-label="order.clearSearch"><i class="bi bi-x-lg"></i></button>
            </div>

            <!-- Pro / alternative-provider mode banner (shown after a primary failure) -->
            <div id="proBanner" class="pro-banner" style="display:none;">
                <i class="bi bi-rocket-fill"></i>
                <div class="flex-grow-1">
                    <div class="fw-bold" data-i18n="order.proTitle">Unatafuta Huduma Pro</div>
                    <div class="small" data-i18n="order.proBody">Tafuta huduma mbadala hapo juu kisha weka order tena.</div>
                </div>
                <button type="button" class="pro-exit" id="proExit" aria-label="Rudi kawaida" data-i18n-aria-label="order.exitPro"><i class="bi bi-x-lg"></i></button>
            </div>

            <div class="or-divider" data-i18n="order.choosePlatform">au chagua platform</div>
            <div class="platform-row" id="platformRow" role="group" aria-label="Choose a social media platform">
                <?php foreach ($platformsCfg as $key => $p):
                    $ico = $platformIcons[$key] ?? 'bi-globe'; ?>
                    <button type="button" class="chip" data-platform="<?= htmlspecialchars($key) ?>" aria-pressed="false">
                        <i class="bi <?= $ico ?>"></i>
                        <span><?= htmlspecialchars($p['name']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <div id="platformStatus" class="visually-hidden" aria-live="polite"></div>

            <!-- Service -->
            <div class="mt-3">
                <label class="form-label"><span data-i18n="order.service">2. Chagua Huduma</span></label>
                <div id="serviceResults" class="service-results" hidden aria-live="polite">
                    <div class="service-results-head"><span>Huduma za platform hii</span><span id="serviceResultsCount"></span></div>
                    <div id="serviceResultsList" class="service-results-list" role="listbox" aria-label="Available services"></div>
                </div>
                <select class="form-select d-none" id="serviceSelect" name="service_id" disabled>
                    <option value="" data-i18n="order.choosePlatformFirst">Chagua platform kwanza...</option>
                </select>
                <!-- DETAILS card -->
                <div class="details-card" id="svcDetail"></div>
            </div>

            <!-- Link -->
            <div class="mt-3">
                <label class="form-label" id="linkLabel">3. Link / Username</label>
                <input type="text" class="form-control" id="linkInput" name="link" value="<?= htmlspecialchars($reorderLink, ENT_QUOTES, 'UTF-8') ?>" placeholder="Chagua huduma kwanza..." data-i18n-placeholder="order.linkPlaceholder" required>
                <div class="link-hint" id="linkHint"><i class="bi bi-info-circle"></i> <span data-i18n="order.linkHint">Chagua huduma ili kuona maelekezo sahihi ya link.</span></div>
                <div class="link-preview" id="linkPreview" aria-live="polite"><div class="link-preview-head"><div class="link-preview-icon"><i class="bi bi-link-45deg" id="linkPreviewIcon"></i></div><div><div class="link-preview-title" id="linkPreviewTitle" data-i18n="order.previewTitle">Link preview</div><div class="link-preview-status" id="linkPreviewStatus"></div></div></div><div class="link-preview-url" id="linkPreviewUrl"></div><a class="link-preview-open" id="linkPreviewOpen" href="#" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right"></i> <span data-i18n="order.openLink">Fungua link</span></a></div>
                <div class="tips-box" id="igTips"></div>
            </div>

            <!-- Quantity -->
            <div class="mt-3">
                <label class="form-label"><span data-i18n="order.quantity">4. Idadi</span> <span id="qtyRange" class="text-lowercase"></span></label>
                <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="<?= (int) $reorderQuantity ?>" required>
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
                <div class="lbl" data-i18n="order.total">Jumla ya Malipo</div>
                <div class="val" id="totalPrice">0 <small>TZS</small></div>
                <div class="text-danger small mt-1 d-none" id="balanceWarning"><i class="bi bi-exclamation-triangle-fill"></i> <span data-i18n="order.insufficient">Salio halitoshi</span></div>
            </div>

            <button type="submit" class="btn-primary-grad" id="submitBtn" disabled>
                <i class="bi bi-rocket-takeoff"></i> <span id="submitText" data-i18n="order.submit">THIBITISHA ORDER</span>
            </button>
            <div id="orderHint" data-i18n="order.hint.select" role="status" aria-live="polite">Chagua huduma na uingize kiasi ili kuona makadirio ya order.</div>
        </form>
    </div>

    <!-- Recent orders -->
    <div class="card-soft mt-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="section-title" style="font-size:.98rem;"><div class="section-ico" style="width:34px;height:34px;font-size:1rem;"><i class="bi bi-clock-history"></i></div> <span data-i18n="orders.recent">Orders za Karibuni</span></div>
            <div class="d-flex align-items-center gap-2"><a href="index.php?sync=1" class="text-decoration-none small fw-semibold" style="color:var(--primary)" data-i18n="orders.refresh">Refresh</a><a href="orders.php" class="text-decoration-none small fw-semibold" style="color:var(--primary)" data-i18n="orders.all">Zote</a></div>
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
</div>
<!-- Top-right hamburger + slide drawer -->
<?php ui_nav('home', ['balance' => $tzsBalance]); ?>
<?php if (!$isVerified): ?>
<div class="modal fade" id="verificationModal" tabindex="-1" aria-labelledby="verificationTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down"><div class="modal-content border-0 rounded-4 overflow-hidden">
    <div class="modal-header border-0 px-4 pt-4 pb-2"><div><span class="badge rounded-pill text-bg-primary mb-2"><i class="bi bi-patch-check-fill me-1"></i>Verified pricing</span><h5 class="modal-title fw-bold" id="verificationTitle">Get lower prices with a verified account</h5><p class="text-muted small mb-0 mt-1">Choose the verification method that works best for you.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body px-4 pt-3"><div class="verify-benefits mb-3"><div class="verify-benefit"><i class="bi bi-check-circle-fill"></i><span>Access lower prices available to verified accounts.</span></div><div class="verify-benefit"><i class="bi bi-check-circle-fill"></i><span>Show a verified badge on your account.</span></div></div>
      <div id="verificationAlert" class="alert d-none rounded-3 small" role="status"></div>
      <div class="row g-3"><div class="col-md-6"><section class="verify-option"><h6><i class="bi bi-wallet2 text-primary me-1"></i>Pay from your wallet</h6><p>One-time verification fee: <strong>5,000 TZS</strong>. Your balance is <?= number_format($tzsBalance) ?> TZS.</p><?php if ($tzsBalance >= 5000): ?><button class="btn btn-primary w-100 rounded-3 fw-bold" id="verifyWalletBtn" type="button">Confirm wallet payment · 5,000 TZS</button><small class="d-block text-muted mt-2">The fee is deducted only after you confirm.</small><?php else: ?><div class="alert alert-warning py-2 px-3 small mb-2">You need at least 5,000 TZS in your wallet. Top up to continue.</div><button type="button" class="btn btn-outline-primary w-100 rounded-3 fw-bold" id="verificationTopUpBtn" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#topUpModal">Top up my wallet</button><?php endif; ?></section></div>
      <div class="col-md-6"><section class="verify-option"><h6><i class="bi bi-phone text-success me-1"></i>Pay by mobile money</h6><p>Pay the same 5,000 TZS fee using the mobile-money provider used for top-ups. Verification completes after the payment is confirmed.</p><label class="form-label" for="verificationName">Payer full name</label><input type="text" id="verificationName" class="form-control mb-2" maxlength="120" autocomplete="name" value="<?= htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required><label class="form-label" for="verificationPhone">Tanzania mobile-money number</label><input type="tel" id="verificationPhone" class="form-control mb-2" placeholder="07XXXXXXXX" value="<?= htmlspecialchars((string) ($user['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><button class="btn btn-success w-100 rounded-3 fw-bold" id="verifyMobileBtn" type="button">Pay 5,000 TZS by mobile money</button><small class="d-block text-muted mt-2">Approve the payment prompt on your phone. We do not verify until payment succeeds.</small></section></div></div>
      <section class="verify-option mt-3"><h6><i class="bi bi-whatsapp text-success me-1"></i>Request manual verification</h6><p class="mb-2">Contact the admin with your account details and ask for a manual review.</p><a class="btn btn-outline-success rounded-3 fw-bold" href="https://wa.me/<?= rawurlencode(WHATSAPP_SUPPORT_PHONE) ?>?text=<?= rawurlencode($manualVerificationMessage) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i>Contact admin on WhatsApp</a></section>
    </div><div class="modal-footer border-0 pt-0 px-4 pb-4"><button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Maybe later</button></div>
  </div></div>
</div>
<?php endif; ?>
<!-- Top-Up Modal -->
<div class="modal fade" id="topUpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content rounded-4 overflow-hidden border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Ongeza Salio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe src="about:blank" data-src="topup.php" loading="lazy" title="Ongeza salio" class="w-100" style="height: 600px; border: none;"></iframe>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="assets/lazy-topup.js"></script>
<script>
// Refresh the parent dashboard after a successful embedded top-up. If this top-up
// was requested from verification, reopen the dialog with the updated balance.
window.addEventListener('message',event=>{
  if(event.origin!==window.location.origin || event.data?.type!=='royal-topup-complete') return;
  if(typeof event.data.balance==='number' && Number.isFinite(event.data.balance)){
    const balanceText=document.getElementById('balanceText');
    if(balanceText) balanceText.textContent=event.data.balance.toLocaleString('en-US');
  }
  try{
    const returnToVerification=sessionStorage.getItem('royalReturnToVerification')==='1';
    sessionStorage.removeItem('royalReturnToVerification');
    if(returnToVerification) sessionStorage.removeItem('royalVerificationPromptSeen');
  }catch(e){}
  window.setTimeout(()=>window.location.reload(),500);
});
</script>
<script>
(function(){
  const modalEl=document.getElementById('verificationModal');
  if(!modalEl) return;
  const csrf=<?= json_encode($verificationToken) ?>;
  const alertBox=document.getElementById('verificationAlert');
  const verificationTopUpBtn=document.getElementById('verificationTopUpBtn');
  if(verificationTopUpBtn) verificationTopUpBtn.addEventListener('click',()=>{try{sessionStorage.setItem('royalReturnToVerification','1');}catch(e){}});
  const walletBtn=document.getElementById('verifyWalletBtn');
  const mobileBtn=document.getElementById('verifyMobileBtn');
  function showMessage(message,type='info'){
    alertBox.className='alert alert-'+type+' rounded-3 small';
    alertBox.textContent=message;
    alertBox.classList.remove('d-none');
  }
  async function post(data){
    const response=await fetch('topup.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name=csrf-token]')?.content||''},body:JSON.stringify({...data,_token:csrf})});
    return response.json();
  }
  function finish(){
    showMessage('Payment confirmed. Your account is now verified and lower verified-account prices are available. Refreshing…','success');
    setTimeout(()=>window.location.reload(),1400);
  }
  async function poll(orderId){
    let attempts=0;
    while(attempts<40){
      await new Promise(resolve=>setTimeout(resolve,3000));
      attempts++;
      try{
        const result=await post({action:'check_verification_status',order_id:orderId});
        if(result.status==='COMPLETED'||result.verified){finish();return;}
        if(result.status==='FAILED'){showMessage('The mobile-money payment did not complete. You can try again or contact the admin.','danger');return;}
        if(result.status==='ERROR'){showMessage(result.message||'PalmPesa could not check this payment yet. Your payment may still be pending; try again later.','warning');return;}
        if(attempts%5===0) showMessage('Waiting for payment confirmation on your phone…','info');
      }catch(e){if(attempts%5===0) showMessage('Still checking payment status. Please keep this window open.','warning');}
    }
    showMessage('We have not received confirmation yet. Check your phone and reopen verification to check again.','warning');
  }
  if(walletBtn) walletBtn.addEventListener('click',async()=>{
    if(!window.confirm('Confirm that you want to pay the one-time 5,000 TZS verification fee from your wallet? The amount will be deducted only after this confirmation.')) return;
    walletBtn.disabled=true;
    try{
      const result=await post({action:'pay_verification_wallet',confirmed:true});
      if(result.success&&result.verified){finish();return;}
      if(result.insufficient_balance){showMessage('Your wallet needs at least 5,000 TZS. Top up your balance, then return here to continue.','warning');return;}
      showMessage(result.message||'Wallet payment could not be completed.','danger');
    }catch(e){showMessage('Network error. No payment was made. Please try again.','danger');}
    finally{walletBtn.disabled=false;}
  });
  if(mobileBtn) mobileBtn.addEventListener('click',async()=>{
    const phone=document.getElementById('verificationPhone').value.trim();
    const name=document.getElementById('verificationName').value.trim();
    if(name.length<2){showMessage('Enter the payer name used for the mobile-money payment.','warning');return;}
    if(!phone){showMessage('Enter the mobile-money number to receive the payment prompt.','warning');return;}
    if(!window.confirm('Continue to pay 5,000 TZS by mobile money? Your account will be verified automatically only after the provider confirms payment.')) return;
    mobileBtn.disabled=true;mobileBtn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Sending payment request…';
    try{
      const result=await post({action:'initiate_verification_payment',phone,name,confirmed:true});
      if(result.success&&result.order_id){showMessage('Payment request sent. Approve it on your phone; keep this dialog open while we verify the payment.','info');poll(result.order_id);return;}
      showMessage(result.message||'Could not start the mobile-money request.','danger');
    }catch(e){showMessage('Network error. Please try again.','danger');}
    finally{mobileBtn.disabled=false;mobileBtn.innerHTML='Pay 5,000 TZS by mobile money';}
  });
  try{
    if(!sessionStorage.getItem('royalVerificationPromptSeen')){
      sessionStorage.setItem('royalVerificationPromptSeen','1');
      setTimeout(()=>bootstrap.Modal.getOrCreateInstance(modalEl).show(),850);
    }
  }catch(e){setTimeout(()=>bootstrap.Modal.getOrCreateInstance(modalEl).show(),850);}
})();
</script>
<script>
let userBalance = <?= json_encode($tzsBalance) ?>;
let currentService = null;
let currentPlatform = '';            // last platform chip / search context
let currentProvider = <?= json_encode(defined('PRIMARY_PROVIDER') ? PRIMARY_PROVIDER : 'fastway') ?>; // configured primary lane
const requestedServiceId = <?= json_encode($requestedServiceId) ?>;

// Append the active provider to an api-services.php URL.
function withProvider(url) {
    return url + (url.includes('?') ? '&' : '?') + 'provider=' + encodeURIComponent(currentProvider);
}

const quantityInput = document.getElementById('quantity');
const totalPrice = document.getElementById('totalPrice');
const submitBtn = document.getElementById('submitBtn');
const balanceWarning = document.getElementById('balanceWarning');
const svcDetail = document.getElementById('svcDetail');
const qtyRange = document.getElementById('qtyRange');
const serviceResults = document.getElementById('serviceResults');
const serviceResultsList = document.getElementById('serviceResultsList');
const serviceResultsCount = document.getElementById('serviceResultsCount');

// The order form must still work if a CDN library is slow or unavailable.
// Keep the jQuery path when available, with a native fallback for the order API.
const serviceElement = document.getElementById('serviceSelect');
const $service = window.jQuery ? window.jQuery(serviceElement) : {
    0: serviceElement,
    prop(name, value) { if (arguments.length > 1) serviceElement[name] = value; return this; },
    empty() { serviceElement.replaceChildren(); return this; },
    append(value) {
        if (typeof value === 'string') serviceElement.insertAdjacentHTML('beforeend', value);
        else if (value) serviceElement.appendChild(value);
        return this;
    },
    val(value) { if (arguments.length) serviceElement.value = value; return serviceElement.value; },
    trigger(name) { serviceElement.dispatchEvent(new Event(name, { bubbles: true })); return this; },
    on(name, handler) { serviceElement.addEventListener(name, handler); return this; }
};

function toast(msg, type = 'primary') {
    const el = document.createElement('div');
    el.className = `alert alert-${type} shadow rounded-4 border-0 d-flex align-items-center gap-2 mb-2`;
    el.style.animation = 'sk .3s';
    el.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle-fill' : type === 'danger' ? 'x-circle-fill' : 'info-circle-fill'}"></i><div>${msg}</div>`;
    document.getElementById('toastWrap').appendChild(el);
    setTimeout(() => { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, 4200);
}

function fmt(n) { return Number(n).toLocaleString('en-US'); }

let activeServiceRequest = null;
let serviceLoadVersion = 0;
let searchTimer = null;

function setPlatformSelection(platform = '') {
    currentPlatform = platform;
    document.querySelectorAll('.chip').forEach(chip => {
        const active = chip.dataset.platform === platform;
        chip.classList.toggle('active', active);
        chip.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    const status = document.getElementById('platformStatus');
    if (status) status.textContent = platform ? `Platform ${platform} selected.` : 'Platform selection cleared.';
}

function clearServiceResults(message = 'Chagua platform kwanza...') {
    serviceLoadVersion += 1;
    if (activeServiceRequest) {
        activeServiceRequest.abort();
        activeServiceRequest = null;
    }
    currentService = null;
    resetServiceUI();
    recalc();
    if (serviceResultsList) serviceResultsList.replaceChildren();
    if (serviceResultsCount) serviceResultsCount.textContent = '';
    if (serviceResults) serviceResults.hidden = true;
    $service.empty().append(new Option(message, '', true, true)).prop('disabled', true).trigger('change');
}

function renderServiceResults(services) {
    if (!serviceResults || !serviceResultsList) return;
    serviceResultsList.replaceChildren();
    if (serviceResultsCount) serviceResultsCount.textContent = `${fmt(services.length)} huduma`;
    const fragment = document.createDocumentFragment();
    services.forEach(s => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'service-option';
        button.dataset.serviceId = String(s.id);
        button.setAttribute('role', 'option');
        button.setAttribute('aria-selected', 'false');

        const name = document.createElement('span');
        name.className = 'service-option-name';
        name.textContent = s.name || 'Catalogue service';
        const meta = document.createElement('span');
        meta.className = 'service-option-meta';
        const price = document.createElement('strong');
        price.className = 'service-option-price';
        price.textContent = `${fmt(s.price_per_1000)} TZS / 1K`;
        const limits = document.createElement('span');
        limits.textContent = `Min ${fmt(s.min)} · Max ${fmt(s.max)}`;
        const extras = document.createElement('span');
        extras.textContent = `${s.refill ? 'Refill' : 'No refill'}${s.cancel ? ' · Cancel' : ''}`;
        meta.append(price, limits, extras);
        button.append(name, meta);
        button.addEventListener('click', () => {
            $service.val(String(s.id)).trigger('change');
            serviceResultsList.querySelectorAll('.service-option').forEach(option => {
                const active = option === button;
                option.classList.toggle('active', active);
                option.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            document.getElementById('linkInput')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
        fragment.appendChild(button);
    });
    serviceResultsList.appendChild(fragment);
    serviceResults.hidden = false;
}

// ---- Shared service loader (used by platform chips AND global search) ----
async function loadServices(url, opts = {}) {
    const requestVersion = ++serviceLoadVersion;
    if (activeServiceRequest) activeServiceRequest.abort();
    const controller = new AbortController();
    activeServiceRequest = controller;
    $service.prop('disabled', true).empty().append('<option>Inapakia huduma...</option>').trigger('change');
    currentService = null; resetServiceUI(); recalc();
    try {
        const r = await fetch(url, { signal: controller.signal, cache: 'no-store' });
        const j = await r.json();
        // Ignore a response for a platform/search that has since been replaced.
        if (requestVersion !== serviceLoadVersion) return;
        if (!r.ok) throw new Error(j.error || 'Service request failed');
        if (j.success && Array.isArray(j.data) && j.data.length) {
            $service.empty().append('<option value="">-- Chagua huduma --</option>');
            j.data.forEach(s => {
                const opt = new Option(`${s.name.substring(0,70)}  ·  ${fmt(s.price_per_1000)} TZS/1K`, s.id, false, false);
                opt.dataset.svc = JSON.stringify(s);
                $service.append(opt);
            });
            $service.prop('disabled', false).trigger('change');
            renderServiceResults(j.data);
            let msg = `${j.data.length} huduma zimepatikana`;
            if (j.truncated) msg = `Zinaonyeshwa ${fmt(j.data.length)} kati ya ${fmt(j.total)}+ — andika zaidi kupunguza`;
            toast(msg, 'success');
            if (opts.selectId) {
                const wanted = String(opts.selectId);
                const match = Array.from($service[0].options).find(o => String(o.value) === wanted);
                if (match) {
                    $service.val(wanted).trigger('change');
                    serviceResultsList?.querySelector(`[data-service-id="${CSS.escape(wanted)}"]`)?.click();
                    setTimeout(() => document.getElementById('orderForm')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 80);
                } else {
                    toast('Huduma uliyochagua haipatikani kwa sasa.', 'warning');
                }
            }
            if (opts.openDropdown) serviceResults?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            if (serviceResults) serviceResults.hidden = true;
            $service.empty().append('<option value="">Hakuna huduma</option>').prop('disabled', true).trigger('change');
            toast(opts.emptyMsg || 'Hakuna huduma iliyopatikana', 'warning');
        }
    } catch (e) {
        if (e.name === 'AbortError' || requestVersion !== serviceLoadVersion) return;
        if (serviceResults) serviceResults.hidden = true;
        $service.empty().append('<option value="">Kosa la mtandao</option>').prop('disabled', true).trigger('change');
        toast('Imeshindwa kupakua huduma. Jaribu tena.', 'danger');
    } finally {
        if (requestVersion === serviceLoadVersion) activeServiceRequest = null;
    }
}

const svcSearchEl  = document.getElementById('svcSearch');
const svcSearchClr = document.getElementById('svcSearchClr');

// Platform selection (chips)
document.querySelectorAll('.chip').forEach(chip => {
    chip.addEventListener('click', () => {
        clearTimeout(searchTimer);
        setPlatformSelection(chip.dataset.platform);
        if (svcSearchEl)  svcSearchEl.value = '';
        if (svcSearchClr) svcSearchClr.style.display = 'none';
        loadServices(withProvider(`api-services.php?platform=${encodeURIComponent(chip.dataset.platform)}`),
                     { emptyMsg: 'Hakuna huduma kwa platform hii' });
    });
});

// Global search across the whole catalogue (debounced)
if (svcSearchEl) {
    svcSearchEl.addEventListener('input', () => {
        const q = svcSearchEl.value.trim();
        svcSearchClr.style.display = q ? 'flex' : 'none';
        clearTimeout(searchTimer);
        setPlatformSelection('');
        // A numeric value is an exact Service ID lookup and should work even
        // when it is only one character; text searches still wait for 2 chars.
        if (q.length < 1 || (!/^\d+$/.test(q) && q.length < 2)) {
            clearServiceResults(q ? 'Andika angalau herufi 2 kutafuta...' : 'Chagua platform kwanza...');
            return;
        }
        clearServiceResults('Inasubiri utafutaji...');
        searchTimer = setTimeout(() => {
            if (svcSearchEl.value.trim() !== q) return;
            const param = /^\d+$/.test(q)
                ? `service_id=${encodeURIComponent(q)}`
                : `q=${encodeURIComponent(q)}`;
            loadServices(withProvider(`api-services.php?${param}`),
                         { openDropdown: true, emptyMsg: `Hakuna huduma kwa "${q}"` });
        }, 450);
    });
    svcSearchClr.addEventListener('click', () => {
        svcSearchEl.value = '';
        svcSearchClr.style.display = 'none';
        clearTimeout(searchTimer);
        setPlatformSelection('');
        clearServiceResults();
        svcSearchEl.focus();
    });
}

// ---- Provider (Kawaida <-> Pro) switching ----
const proBanner = document.getElementById('proBanner');
const proExit   = document.getElementById('proExit');

// Switch to the alternative provider (FastWay / "Huduma Pro") and reload the
// catalogue so the user can search for a substitute service to order.
function enterProMode() {
    currentProvider = 'fastway';
    if (proBanner) proBanner.style.display = 'flex';
    if (svcSearchEl) { svcSearchEl.value = ''; svcSearchClr.style.display = 'none'; }
    // Reload the same platform from FastWay if one was chosen, else prompt search.
    if (currentPlatform) {
        loadServices(withProvider(`api-services.php?platform=${encodeURIComponent(currentPlatform)}`),
                     { openDropdown: true, emptyMsg: 'Hakuna huduma Pro kwa platform hii' });
    } else if (svcSearchEl) {
        svcSearchEl.focus();
    }
    // Bring the search box into view so the "link to search" is obvious.
    if (svcSearchEl) svcSearchEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function exitProMode() {
    if (currentProvider === 'boost') return;
    currentProvider = 'boost';
    if (proBanner) proBanner.style.display = 'none';
    if (currentPlatform) {
        loadServices(withProvider(`api-services.php?platform=${encodeURIComponent(currentPlatform)}`),
                     { emptyMsg: 'Hakuna huduma kwa platform hii' });
    }
}

if (proExit) proExit.addEventListener('click', exitProMode);

// A service selected from the catalogue is fetched directly by ID, then
// preselected here so the customer only fills link and quantity.
if (/^\d+$/.test(requestedServiceId)) {
    loadServices(withProvider(`api-services.php?service_id=${encodeURIComponent(requestedServiceId)}`), {
        selectId: requestedServiceId,
        emptyMsg: 'Huduma uliyochagua haipatikani kwa sasa'
    });
}

// ---- Service detail + link guidance ----
const linkInput = document.getElementById('linkInput');
const linkLabel = document.getElementById('linkLabel');
const linkHint  = document.getElementById('linkHint');
const igTips    = document.getElementById('igTips');
const linkPreview = document.getElementById('linkPreview');
const linkPreviewIcon = document.getElementById('linkPreviewIcon');
const linkPreviewTitle = document.getElementById('linkPreviewTitle');
const linkPreviewStatus = document.getElementById('linkPreviewStatus');
const linkPreviewUrl = document.getElementById('linkPreviewUrl');
const linkPreviewOpen = document.getElementById('linkPreviewOpen');

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

function renderLinkPreview(){
  const raw=linkInput.value.trim();
  if(!raw){linkPreview.classList.remove('show','invalid');linkPreviewOpen.removeAttribute('href');return;}
  let parsed;
  try{parsed=new URL(/^https?:\/\//i.test(raw)?raw:'https://'+raw);}catch(e){parsed=null;}
  if(!parsed||!/^https?:$/.test(parsed.protocol)||!parsed.hostname.includes('.')){
    linkPreview.classList.add('show','invalid');linkPreviewTitle.textContent='Link haijakamilika';linkPreviewStatus.textContent='Weka link ya http:// au https://';linkPreviewUrl.textContent=raw;linkPreviewIcon.className='bi bi-exclamation-triangle';linkPreviewOpen.removeAttribute('href');return;
  }
  const host=parsed.hostname.toLowerCase(),platform=Object.keys(EXAMPLES).find(p=>host.includes(p))||'web';
  const icons={instagram:'bi-instagram',tiktok:'bi-music-note-beamed',facebook:'bi-facebook',youtube:'bi-youtube',twitter:'bi-twitter-x',telegram:'bi-telegram',web:'bi-globe2'};
  linkPreview.classList.add('show');linkPreview.classList.remove('invalid');linkPreviewTitle.textContent=platform==='web'?'Link preview':platform.charAt(0).toUpperCase()+platform.slice(1)+' link preview';linkPreviewStatus.textContent='Link iko tayari kutumika';linkPreviewUrl.textContent=parsed.href;linkPreviewIcon.className='bi '+(icons[platform]||icons.web);linkPreviewOpen.href=parsed.href;
}
linkInput.addEventListener('input',renderLinkPreview);
linkInput.addEventListener('input',recalc);
linkInput.addEventListener('paste',()=>setTimeout(renderLinkPreview,0));

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
  linkPreview.classList.remove('show','invalid'); linkPreviewOpen.removeAttribute('href');
  linkLabel.textContent='3. Link / Username';
  linkInput.dataset.i18nPlaceholder='order.linkPlaceholder';
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
  linkInput.removeAttribute('data-i18n-placeholder');
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
    const orderHint = document.getElementById('orderHint');
    if (!currentService || !qty) {
        totalPrice.innerHTML = '0 <small>TZS</small>';
        submitBtn.disabled = true; balanceWarning.classList.add('d-none');
        if (orderHint) { orderHint.dataset.i18n = !currentService ? 'order.hint.select' : 'order.hint.quantity'; if (window.royalSetLanguage) window.royalSetLanguage(document.documentElement.lang); }
        return;
    }
    const min = currentService.min, max = currentService.max;
    const total = Math.ceil(qty * currentService.rate);
    totalPrice.innerHTML = `${fmt(total)} <small>TZS</small>`;

    const validQty = qty >= min && (max === 0 || qty <= max);
    const enough = total <= userBalance;
    balanceWarning.classList.toggle('d-none', enough || !validQty);
    submitBtn.disabled = !(validQty && enough && total > 0);
    if (orderHint) {
        if (!validQty) orderHint.dataset.i18n = 'order.hint.range';
        else if (!enough) orderHint.dataset.i18n = 'order.hint.balance';
        else if (!linkInput.value.trim()) orderHint.dataset.i18n = 'order.hint.link';
        else orderHint.dataset.i18n = 'order.hint.ready';
        if (window.royalSetLanguage) window.royalSetLanguage(document.documentElement.lang);
    }
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
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]')?.content || '' },
            body: JSON.stringify({
                service_id: currentService.id,
                quantity: qty,
                link,
                use_fallback: currentProvider === 'fastway',
            })
        });
        const j = await r.json();
        if (j.success) {
            toast(`✅ ${j.message} (Order #${j.order_id})`, 'success');
            if (typeof j.new_balance !== 'undefined') {
                userBalance = j.new_balance;
                document.getElementById('balanceText').textContent = fmt(j.new_balance);
            }
            setTimeout(() => location.reload(), 1400);
        } else if (j.provider_unavailable) {
            // Primary provider couldn't place the order -> offer the Pro provider.
            toast('⚠️ ' + (j.message || 'Huduma ya kawaida haipatikani.'), 'warning');
            if (confirm('Huduma yetu ya kawaida imeshindikana kwa sasa.\n\nUngependa kutafuta huduma mbadala (Huduma Pro)?')) {
                enterProMode();
            }
        } else if (j.both_failed) {
            // Already on Pro and it failed too -> keep them in Pro search to retry.
            toast('⚠️ ' + (j.message || 'Jaribu huduma nyingine ya Pro.'), 'danger');
            if (currentProvider !== 'fastway') enterProMode();
            else if (svcSearchEl) svcSearchEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
