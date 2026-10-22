<?php
/**
 * api/print_cron.php
 * Automated Heartbeat & Expiration Worker for Print Vendor Rates.
 * Can be run via Windows Task Scheduler / Cron or via HTTP ping.
 */

header('Content-Type: application/json; charset=utf-8');

$_SERVER['SERVER_PORT'] = $_SERVER['SERVER_PORT'] ?? 80;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'off';

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/print_db.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

init_print_database($conn);

$now = date('Y-m-d H:i:s');

// 1. Identify and expire stale rates
$stale_query = "SELECT id, vendor_id, product_id FROM print_vendor_rates WHERE status = 'active' AND expires_at < '{$now}'";
$stale_res = $conn->query($stale_query);
$expired_ids = [];
$vendor_affected = [];

while ($r = $stale_res->fetch_assoc()) {
    $expired_ids[] = intval($r['id']);
    $vendor_affected[$r['vendor_id']] = ($vendor_affected[$r['vendor_id']] ?? 0) + 1;
}

$expired_count = count($expired_ids);
if ($expired_count > 0) {
    $id_list = implode(',', $expired_ids);
    $conn->query("UPDATE print_vendor_rates SET status = 'expired' WHERE id IN ({$id_list})");

    // Log expirations
    $log_stmt = $conn->prepare("INSERT INTO print_rate_refresh_logs (vendor_id, event_type, rates_affected_count, triggered_by) VALUES (?, 'auto_expired', ?, 'cron_heartbeat')");
    foreach ($vendor_affected as $v_id => $count) {
        $log_stmt->bind_param("ii", $v_id, $count);
        $log_stmt->execute();
    }
}

// 2. Query summary statistics
$stats = [
    'total_vendors' => intval($conn->query("SELECT COUNT(*) AS c FROM print_vendors WHERE status = 'active'")->fetch_assoc()['c']),
    'total_rates'   => intval($conn->query("SELECT COUNT(*) AS c FROM print_vendor_rates")->fetch_assoc()['c']),
    'active_rates'  => intval($conn->query("SELECT COUNT(*) AS c FROM print_vendor_rates WHERE status = 'active'")->fetch_assoc()['c']),
    'expired_rates' => intval($conn->query("SELECT COUNT(*) AS c FROM print_vendor_rates WHERE status = 'expired'")->fetch_assoc()['c']),
    'newly_expired' => $expired_count,
    'timestamp'     => $now
];

echo json_encode([
    'success' => true,
    'message' => "Expiration sweep executed. {$expired_count} rate(s) marked as expired.",
    'stats' => $stats
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
