<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../core/config.php';

// ── Session must start before anything ───────────────────────────────────────
// config.php may or may not start a session — we ensure it here safely
if (session_status() === PHP_SESSION_NONE) session_start();

function clean(string $data): string {
    return trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8'));
}

// ── Only accept POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

// ─────────────────────────────────────────────────────────────────────────────
//  DEBUG MODE — set to false in production
// ─────────────────────────────────────────────────────────────────────────────
define('SPAM_DEBUG', false);

function reject(string $reason, string $redirect = 'index.php'): never {
    if (SPAM_DEBUG) {
        die("<pre style='font-family:monospace;padding:20px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;margin:20px;border-radius:8px;'>
<strong>Spam Protection Blocked This Submission</strong>
Reason: $reason
</pre>");
    }
    $safe = urlencode($reason ?: "Submission blocked. Please try again.");
    header("Location: $redirect?err=$safe");
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
//  COLLECT INPUTS
// ══════════════════════════════════════════════════════════════════════════════
$name          = clean($_POST['name']               ?? '');
$company       = clean($_POST['company']            ?? '');
$email         = clean($_POST['email']              ?? '');
$phone         = clean($_POST['phone']              ?? '');
$projectType   = clean($_POST['projectType']        ?? '');
$budget        = clean($_POST['budget']             ?? '');
$timeframe     = clean($_POST['time']               ?? '');
$description   = clean($_POST['description']        ?? '');
$discount_code = clean($_POST['discount_code']      ?? '');
$service_type  = clean($_POST['modal_service_type']  ?? '');
$package_name  = clean($_POST['modal_package_name']  ?? '');
$package_price = clean($_POST['modal_package_price'] ?? '');

// ══════════════════════════════════════════════════════════════════════════════
//  ANTI-SPAM SECURITY SHIELD VERIFICATION
// ══════════════════════════════════════════════════════════════════════════════
$spam_check = gfx_verify_form_submission($conn, 'project_request', [
    'name'        => $name,
    'company'     => $company,
    'email'       => $email,
    'phone'       => $phone,
    'description' => $description
], [
    'require_csrf' => true,
    'require_time' => true,
    'max_attempts' => 5,
    'window_seconds' => 600
]);

if (!$spam_check['success']) {
    if ($spam_check['is_bot']) {
        // Silently deflect bots with fake success to prevent automated retry mutations
        $redirect = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        header("Location: $redirect?success=1");
        exit;
    }
    reject($spam_check['error']);
}

if (!$name || !$email || !$phone || !$projectType || !$timeframe || !$description) {
    reject("Required fields are missing. Please complete all required inputs.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    reject("Please enter a valid email address.");
}

if (mb_strlen($description) < 15) {
    reject("Project description is too short (minimum 15 characters required).");
}

// ── Discount code ─────────────────────────────────────────────────────────────
$discount_amount = 0;
if (!empty($discount_code)) {
    $dstmt = $conn->prepare("SELECT id, applied_count FROM discounts WHERE code = ? AND status = 'active' AND (expires_date IS NULL OR expires_date > NOW()) LIMIT 1");
    $dstmt->bind_param("s", $discount_code);
    $dstmt->execute();
    $drow = $dstmt->get_result()->fetch_assoc();
    if ($drow) {
        $discount_amount = $drow['applied_count'];
        $conn->query("UPDATE discounts SET applied_count = applied_count + 1 WHERE id = {$drow['id']}");
    }
}

// ── File upload ───────────────────────────────────────────────────────────────
$uploadDir      = 'uploads/project_requests/';
$maxFileSize    = 5 * 1024 * 1024;
$allowedTypes   = ['pdf','doc','docx','jpg','jpeg','png','gif','zip'];
$attachmentPath = null;

if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['attachment'];
    if ($file['size'] > $maxFileSize) reject("File too large: " . $file['size']);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) reject("Invalid file type: $ext");
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $newName = uniqid('proj_', true) . '.' . $ext;
    $target  = $uploadDir . $newName;
    if (move_uploaded_file($file['tmp_name'], $target)) {
        $attachmentPath = $target;
    } else {
        reject("File upload move failed");
    }
}

// ── Build description ─────────────────────────────────────────────────────────
$full_description = $description;
if ($service_type || $package_name) {
    $context  = "\n\n--- Package Selected ---";
    if ($service_type)  $context .= "\nService: $service_type";
    if ($package_name)  $context .= "\nPackage: $package_name";
    if ($package_price) $context .= "\nPrice: $package_price";
    if ($discount_code && $discount_amount) $context .= "\nDiscount: $discount_code ({$discount_amount}% off)";
    $full_description .= $context;
}

// ── Insert ────────────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO project_requests
        (name, company, email, phone, project_type, budget, timeframe, description, attachment)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) reject("Prepare failed: " . $conn->error);

$stmt->bind_param(
    "sssssssss",
    $name, $company, $email, $phone,
    $projectType, $budget, $timeframe,
    $full_description, $attachmentPath
);

if (!$stmt->execute()) {
    reject("DB execute failed: " . $stmt->error);
}
$stmt->close();

// Send confirmation to client and alert to management
require_once __DIR__ . '/../core/notification_mailer.php';
notifyInboundProjectLead($conn, $name, $email, $phone, $projectType, $budget, $full_description);

// ── Log for rate limiting (only if table exists) ──────────────────────────────
if ($table_check > 0) {
    $lstmt = $conn->prepare("INSERT INTO form_submissions_log (ip_hash, form_name) VALUES (?, 'project_request')");
    $lstmt->bind_param("s", $ip_hash);
    $lstmt->execute();
}

$conn->close();
$redirect = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header("Location: $redirect?success=1");
exit;