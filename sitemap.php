<?php
$host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
if ($host === '') $host = 'localhost';
$scheme = ($host === 'localhost' || str_starts_with($host, '127.0.0.1')) ? 'http' : 'https';
$origin = $scheme . '://' . $host;
header('Content-Type: application/xml; charset=utf-8');
$today = gmdate('Y-m-d');
?><?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?= htmlspecialchars($origin . '/', ENT_XML1, 'UTF-8') ?></loc><lastmod><?= $today ?></lastmod><changefreq>weekly</changefreq><priority>1.0</priority></url>
  <url><loc><?= htmlspecialchars($origin . '/services.php', ENT_XML1, 'UTF-8') ?></loc><lastmod><?= $today ?></lastmod><changefreq>daily</changefreq><priority>0.8</priority></url>
  <url><loc><?= htmlspecialchars($origin . '/pricing.php', ENT_XML1, 'UTF-8') ?></loc><lastmod><?= $today ?></lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>
  <url><loc><?= htmlspecialchars($origin . '/terms.php', ENT_XML1, 'UTF-8') ?></loc><lastmod><?= $today ?></lastmod><changefreq>yearly</changefreq><priority>0.3</priority></url>
  <url><loc><?= htmlspecialchars($origin . '/privacy.php', ENT_XML1, 'UTF-8') ?></loc><lastmod><?= $today ?></lastmod><changefreq>yearly</changefreq><priority>0.3</priority></url>
  <url><loc><?= htmlspecialchars($origin . '/refund-refill.php', ENT_XML1, 'UTF-8') ?></loc><lastmod><?= $today ?></lastmod><changefreq>yearly</changefreq><priority>0.3</priority></url>
</urlset>
