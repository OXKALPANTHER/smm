<?php
/**
 * Lightweight keep-alive endpoint.
 *
 * Hit this URL on a schedule (e.g. UptimeRobot every 5 min — see KEEPALIVE.md)
 * to stop free hosts (Render/Fly/etc.) from sleeping the app. The in-page
 * heartbeat in includes/pwa.php also pings it while a tab is open.
 *
 * Intentionally does NO database or session work so it stays fast and cheap.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
http_response_code(200);

echo json_encode(['ok' => true, 't' => time()]);
