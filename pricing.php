<?php
require_once 'config.php';
require_once 'includes/ui.php';
require_once 'includes/public-price-table.php';
ui_head('Public Pricing — ' . APP_NAME, 'app');
?>
<div class="container py-4">
  <div class="hero mb-3"><span class="badge bg-light text-primary rounded-pill mb-2">Public catalogue pricing</span><h1 class="h3 fw-bold mb-2">Current starting rates</h1><p class="mb-0 opacity-75">Browse read-only starting prices before you sign in. Rates are per 1,000 units; exact totals depend on the service and quantity.</p></div>
  <section class="card-soft mb-3" aria-labelledby="prices-heading"><h2 id="prices-heading" class="h5 fw-bold">Catalogue snapshot</h2><?php renderPublicPriceTable(); ?></section>
  <div class="card-soft mb-3"><h2 class="h5 fw-bold">How pricing works</h2><p class="text-muted">The catalogue shows service limits, refill availability and starting rate. Review the final order amount after signing in and before confirming.</p><a href="services.php" class="btn btn-primary rounded-pill mt-2">View services <i class="bi bi-arrow-right"></i></a></div>
  <div class="card-soft"><h2 class="h5 fw-bold">Tanzanian payments</h2><p class="text-muted mb-0">Top up in TZS using the payment methods available to your account. Payment availability depends on your region.</p></div>
</div>
<?php ui_foot(); ?>
