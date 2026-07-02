<?php
require_once 'core/config.php';
header('Content-Type: application/json');

$code = trim($_GET['code'] ?? '');
if (!$code) { echo json_encode(['valid'=>false,'message'=>'No code provided']); exit; }

$stmt = $conn->prepare("
    SELECT discount_percent FROM discounts
    WHERE code = ? AND status = 'active'
      AND (expires_date IS NULL OR expires_date > NOW())
    LIMIT 1
");
$stmt->bind_param("s", $code);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row) {
    echo json_encode(['valid'=>true,'percent'=>(int)$row['discount_percent']]);
} else {
    echo json_encode(['valid'=>false,'message'=>'Invalid or expired discount code.']);
}
