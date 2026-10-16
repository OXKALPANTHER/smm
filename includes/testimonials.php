<?php
/** Fetch only approved reviews with explicit publication consent. */
function royalPublicTestimonials($conn, int $limit = 12): array
{
    $limit = max(3, min(24, $limit));
    try {
        $sql = "SELECT t.id, t.display_name, t.rating, t.message, t.platform,
                       CASE WHEN t.order_id IS NOT NULL AND o.id IS NOT NULL
                                  AND LOWER(TRIM(COALESCE(o.status, ''))) = 'completed'
                                  AND o.user_id = t.user_id
                            THEN 1 ELSE 0 END AS verified_customer
                  FROM testimonials t
                  LEFT JOIN orders o ON o.id = t.order_id
                 WHERE t.status = 'approved' AND t.consent_to_publish = 1
                 ORDER BY t.approved_at DESC, t.id DESC
                 LIMIT {$limit}";
        $result = $conn->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Throwable $e) {
        error_log('Public testimonial query failed: ' . $e->getMessage());
        return [];
    }
}

/** Public name is limited to a first name and last initial. */
function royalTestimonialPublicName(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', strip_tags($name)) ?? '');
    if ($name === '') {
        return '';
    }
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $first = (string) ($parts[0] ?? '');
    if (count($parts) < 2) {
        return $first;
    }
    $last = trim((string) end($parts), "., ");
    $initial = function_exists('mb_substr') ? mb_substr($last, 0, 1, 'UTF-8') : substr($last, 0, 1);
    $initial = function_exists('mb_strtoupper') ? mb_strtoupper($initial, 'UTF-8') : strtoupper($initial);
    return $first . ' ' . $initial . '.';
}
