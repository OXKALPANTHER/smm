<?php
$host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
if ($host === '') $host = 'localhost';
$scheme = ($host === 'localhost' || str_starts_with($host, '127.0.0.1')) ? 'http' : 'https';
$origin = $scheme . '://' . $host;
header('Content-Type: text/plain; charset=utf-8');
?>User-agent: *
Allow: /
Disallow: /admin.php
Disallow: /moderator.php
Disallow: /profile.php
Disallow: /orders.php
Disallow: /notifications.php
Disallow: /topup.php
Sitemap: <?= $origin ?>/sitemap.xml
