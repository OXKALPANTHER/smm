<?php
/**
 * Public starting-price snapshot. Uses the existing provider catalogue cache and
 * keeps a compact presentation cache for ten minutes. It is read-only and does
 * not participate in order placement or account-specific price calculations.
 */
function royalPublicPriceSnapshot(): array
{
    $cacheDir = dirname(__DIR__) . '/data/cache';
    $cacheFile = $cacheDir . '/public-prices-v1.json';
    $stale = [];

    if (is_file($cacheFile)) {
        $decoded = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($decoded) && isset($decoded['data']) && is_array($decoded['data'])) {
            $stale = $decoded['data'];
            if (count($stale) > 1 && count($stale) % 2 === 1) array_pop($stale);
            $age = time() - (int) ($decoded['updated_at'] ?? 0);
            if ($age >= 0 && $age < 600) {
                return $stale;
            }
        }
    }

    try {
        require_once __DIR__ . '/APIHandler.php';
        $provider = defined('PRIMARY_PROVIDER') ? PRIMARY_PROVIDER : 'fastway';
        $api = new APIHandler($provider);
        $markup = defined('UNVERIFIED_PRICE_MARKUP_PERCENT')
            ? (float) UNVERIFIED_PRICE_MARKUP_PERCENT
            : 100.0;
        // This reads the existing provider catalogue cache; it never calls an
        // order, payment, login, or user-specific pricing endpoint.
        $services = $api->getAllServices(true, $markup);
        if (!is_array($services) || !$services) {
            return $stale;
        }

        $chosen = [];
        foreach ($services as $service) {
            $name = (string) ($service['name'] ?? '');
            $haystack = strtolower($name . ' ' . (string) ($service['category'] ?? ''));
            $platform = 'Social media';
            foreach (['instagram', 'tiktok', 'youtube', 'facebook', 'telegram', 'twitter', 'x'] as $candidate) {
                if (strpos($haystack, $candidate) !== false) {
                    $platform = ucfirst($candidate === 'x' ? 'X' : $candidate);
                    break;
                }
            }
            $rate = max(0.0, (float) ($service['rate'] ?? 0));
            $reportedPrice = max(0.0, (float) ($service['price_per_1000'] ?? 0));
            // Keep the public quote aligned with the order calculator's 1K math.
            $price = $rate > 0 ? (int) ceil($rate * 1000) : (int) ceil($reportedPrice);
            if ($price <= 0 || (isset($chosen[$platform]) && $chosen[$platform]['price'] <= $price)) {
                continue;
            }
            $chosen[$platform] = [
                'platform' => $platform,
                'name' => $name,
                'price' => $price,
                'currency' => 'TZS',
                'service_id' => (int) ($service['id'] ?? 0),
                'min' => (int) ($service['min'] ?? 0),
                'max' => (int) ($service['max'] ?? 0),
                'refill' => !empty($service['refill']),
            ];
        }
        $data = array_values($chosen);
        usort($data, static fn($a, $b) => $a['price'] <=> $b['price']);
        $data = array_slice($data, 0, 6);
        if (count($data) > 1 && count($data) % 2 === 1) array_pop($data);
        if (!$data) {
            return $stale;
        }

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $encoded = json_encode(['updated_at' => time(), 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded !== false && is_dir($cacheDir) && is_writable($cacheDir)) {
            $tempFile = $cacheFile . '.' . getmypid() . '.tmp';
            if (@file_put_contents($tempFile, $encoded, LOCK_EX) !== false) {
                @rename($tempFile, $cacheFile);
                if (is_file($tempFile)) {
                    @unlink($tempFile);
                }
            }
        }
        return $data;
    } catch (Throwable $e) {
        error_log('Public price snapshot refresh failed: ' . $e->getMessage());
        return $stale;
    }
}
