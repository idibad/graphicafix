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
//  When true: shows exactly which check failed instead of generic error
// ─────────────────────────────────────────────────────────────────────────────
define('SPAM_DEBUG', true); // ← change to false after you confirm it works

function reject(string $reason, string $redirect = 'index.php'): never {
    if (SPAM_DEBUG) {
        // Show exact failure reason while debugging
        die("<pre style='font-family:monospace;padding:20px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;margin:20px;border-radius:8px;'>
<strong>Spam Protection Blocked This Submission</strong>
Reason: $reason

POST data received:
" . print_r(array_map('htmlspecialchars', $_POST), true) . "
Session data:
" . print_r($_SESSION, true) . "
</pre>");
    }
    $safe = urlencode("Submission blocked. Please try again.");
    header("Location: $redirect?err=$safe");
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
//  LAYER 1 — CSRF TOKEN
// ══════════════════════════════════════════════════════════════════════════════
$submitted_token = trim($_POST['_csrf_token'] ?? '');
$session_token = trim($_SESSION['_csrf_token'] ?? '');

if (SPAM_DEBUG) {
    // Log to PHP error log for diagnosis (never shown to users in production)
    error_log("CSRF DEBUG — submitted: '$submitted_token' | session: '$session_token'");
}

if (
    empty($submitted_token) ||
    empty($session_token)   ||
    !hash_equals($session_token, $submitted_token)
) {
    unset($_SESSION['_csrf_token']);
    reject("CSRF token mismatch — submitted='$submitted_token' session='$session_token'");
}

// One-time use
unset($_SESSION['_csrf_token']);

// ══════════════════════════════════════════════════════════════════════════════
//  LAYER 2 — HONEYPOT + TIMING
// ══════════════════════════════════════════════════════════════════════════════
$honeypot = $_POST['_hp_website'] ?? '';

if ($honeypot !== '') {
    // Bot filled the honeypot — fake success
    if (SPAM_DEBUG) reject("Honeypot field was filled: '$honeypot'");$redirect = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header("Location: $redirect?success=1");
    exit;
}

$form_open_time = intval($_POST['_form_time'] ?? 0);
$elapsed        = time() - $form_open_time;

if (SPAM_DEBUG) {
    error_log("TIMING DEBUG — form_time: $form_open_time | elapsed: {$elapsed}s");
}

// Only enforce timing if _form_time was actually sent (JS was loaded)
if ($form_open_time > 0 && $elapsed < 4) {
    if (SPAM_DEBUG) reject("Form submitted too fast: {$elapsed}s (minimum 4s required)");
    $redirect = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header("Location: $redirect?success=1");
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
//  LAYER 3 — RATE LIMITING
// ══════════════════════════════════════════════════════════════════════════════
define('RATE_LIMIT_MAX', 7);

$ip = $_SERVER['HTTP_CF_CONNECTING_IP']
  ?? $_SERVER['HTTP_X_FORWARDED_FOR']
  ?? $_SERVER['REMOTE_ADDR']
  ?? '0.0.0.0';
$ip      = trim(explode(',', $ip)[0]);
$ip_hash = hash('sha256', $ip . 'gfx_salt_2024');

// Check if table exists before querying (graceful if schema not yet run)
$table_check = $conn->query("SHOW TABLES LIKE 'form_submissions_log'")->num_rows;
if ($table_check > 0) {
    $conn->query("DELETE FROM form_submissions_log WHERE submitted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $rstmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM form_submissions_log WHERE ip_hash = ? AND form_name = 'project_request' AND submitted_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $rstmt->bind_param("s", $ip_hash);
    $rstmt->execute();
    $rate_count = $rstmt->get_result()->fetch_assoc()['cnt'] ?? 0;

    if ($rate_count >= RATE_LIMIT_MAX) {
        $wait = urlencode("Too many submissions. Please wait an hour before trying again.");
        header("Location: index.php?err=$wait"); exit;
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  COLLECT + VALIDATE
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

if (!$name || !$email || !$phone || !$projectType || !$timeframe || !$description) {
    reject("Required fields missing — name='$name' email='$email' phone='$phone' type='$projectType' time='$timeframe' desc='" . substr($description,0,20) . "'");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    reject("Invalid email: '$email'");
}

if (mb_strlen($description) < 20) {
    reject("Description too short: " . mb_strlen($description) . " chars");
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