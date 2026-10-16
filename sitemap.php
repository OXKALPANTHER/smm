<?php
header('Content-Type: application/xml; charset=utf-8');
$origin = 'https://royal.t20tech.site';
$today = gmdate('Y-m-d');
$publicPaths = [
    '/' => ['weekly', '1.0'],
    '/services.php' => ['daily', '0.8'],
    '/pricing.php' => ['weekly', '0.8'],
    '/api.php' => ['monthly', '0.6'],
    '/terms.php' => ['yearly', '0.3'],
    '/privacy.php' => ['yearly', '0.3'],
    '/refund-refill.php' => ['yearly', '0.3'],
];
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($publicPaths as $path => [$frequency, $priority]): ?>
  <url><loc><?= htmlspecialchars($origin . $path, ENT_XML1, 'UTF-8') ?></loc><lastmod><?= $today ?></lastmod><changefreq><?= $frequency ?></changefreq><priority><?= $priority ?></priority></url>
<?php endforeach; ?>
</urlset>
