<?php
require_once 'config.php';
require_once 'includes/ui.php';
ui_head('Pricing — ' . APP_NAME, 'app');
?>
<div class="container py-4">
  <div class="hero mb-3"><span class="badge bg-light text-primary rounded-pill mb-2">Live catalogue pricing</span><h1 class="h3 fw-bold mb-2">Simple pricing for every campaign</h1><p class="mb-0 opacity-75">Rates are shown per 1,000 units in the live catalogue. The final amount depends on the service, quantity, and account pricing tier.</p></div>
  <div class="card-soft mb-3"><h2 class="h5 fw-bold">How pricing works</h2><div class="row g-3 mt-1"><div class="col-md-4"><strong>1. Browse</strong><p class="small text-muted mb-0">Open the live services catalogue and compare minimums, maximums, refill terms, and current rate.</p></div><div class="col-md-4"><strong>2. Review</strong><p class="small text-muted mb-0">The order preview calculates the exact amount before you confirm.</p></div><div class="col-md-4"><strong>3. Track</strong><p class="small text-muted mb-0">Your dashboard shows the order status and progress after submission.</p></div></div><a href="services.php" class="btn btn-primary rounded-pill mt-3">View live services <i class="bi bi-arrow-right"></i></a></div>
  <div class="card-soft"><h2 class="h5 fw-bold">Tanzanian payments</h2><p class="text-muted mb-0">Top up in TZS using the payment methods shown in your account. Service availability and prices can change when the supplier catalogue is refreshed.</p></div>
</div>
<?php ui_foot(); ?>
