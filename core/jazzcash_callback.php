<?php
/**
 * jazzcash_callback.php
 * JazzCash redirects here after online payment completes.
 * Verifies HMAC signature, activates enrollment, and redirects top window to thank you page.
 */
error_reporting(0);
ini_set('display_errors', '0');

require_once 'core/config.php';

function doTopRedirect($url) {
    echo "<!DOCTYPE html><html><head><meta name='viewport' content='width=device-width, initial-scale=1'><title>Payment Processing</title></head>
    <body style='background:#f0fdf4; text-align:center; padding:50px 20px; font-family:sans-serif; margin:0;'>
    <script>window.location.href = '$url';</script>
    </body></html>";
    exit;
}

function displayError($msg, $ref) {
    echo "<!DOCTYPE html><html><head><meta name='viewport' content='width=device-width, initial-scale=1'><title>Payment Error</title></head>
    <body style='background:#fef2f2; text-align:center; padding:50px 20px; font-family:sans-serif; margin:0;'>
    <div style='background:#ffffff; max-width:400px; margin:0 auto; padding:40px 30px; border-radius:16px; border: 1px solid #fca5a5; box-shadow:0 10px 25px rgba(0,0,0,0.05);'>
        <div style='font-size:4rem; margin-bottom:15px; color:#ef4444;'>⚠️</div>
        <h2 style='color:#b91c1c; margin:0 0 10px;'>Payment Failed</h2>
        <p style='color:#991b1b; line-height:1.6; margin-bottom:20px; font-size:0.95rem;'>$msg</p>
        <a href='courses.php' style='display:inline-block; padding:12px 24px; background:#b91c1c; color:#fff; text-decoration:none; border-radius:8px; font-weight:bold;'>Try Again</a>
    </div>
    </body></html>";
    exit;
}

@file_put_contents(__DIR__ . '/jazzcash_callback_debug.log', date('Y-m-d H:i:s') . " - POST: " . json_encode($_POST) . " - GET: " . json_encode($_GET) . "\n", FILE_APPEND);

// 1. Validate response exists
if (empty($_POST)) {
    die("Invalid Request. No Data Received.");
}

// 2. Validate Signature (HMAC SHA256)
$response_hash = $_POST['pp_SecureHash'] ?? '';
$sorted_data = $_POST;
unset($sorted_data['pp_SecureHash']);
ksort($sorted_data);

$hash_string = JAZZCASH_INTEGRITY_SALT . '&';
foreach ($sorted_data as $key => $val) {
    if ($val != null && $val != "") {
        $hash_string .= $val . '&';
    }
}
$hash_string = rtrim($hash_string, '&');
$calculated_hash = hash_hmac('sha256', $hash_string, JAZZCASH_INTEGRITY_SALT);

if (strtoupper($calculated_hash) !== strtoupper($response_hash)) {
    displayError("Security Error: Invalid signature from payment gateway.", "");
}

// 3. Check Response Code
$responseCode = $_POST['pp_ResponseCode'] ?? '';
$responseMessage = $_POST['pp_ResponseMessage'] ?? 'Transaction failed.';
$ref = $_GET['ref'] ?? ''; // We passed ref in ReturnURL

if ($responseCode !== '000' && $responseCode !== '121' && $responseCode !== '200') {
    // 000 is success, 121 is also sometimes successful for MWALLET, 200 is successful for some cards in JC. Usually 000 is standard.
    // If it's a failure (like 157, 114, etc):
    displayError("JazzCash: " . htmlspecialchars($responseMessage), $ref);
}

// Ensure table exists
@$conn->query("CREATE TABLE IF NOT EXISTS pending_jazzcash_orders (
    order_id VARCHAR(100) PRIMARY KEY,
    form_data LONGTEXT,
    enrolled_id INT DEFAULT 0,
    created_at DATETIME
)");

$temp_row = null;
if (!empty($ref)) {
    $stmt = @$conn->prepare("SELECT * FROM pending_jazzcash_orders WHERE order_id = ? OR order_id LIKE CONCAT('%', ?, '%') ORDER BY created_at DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ss", $ref, $ref);
        $stmt->execute();
        $res = $stmt->get_result();
        $temp_row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
}

if (!$temp_row) {
    displayError("Order details not found. Please contact support.", $ref);
}

$data = json_decode($temp_row['form_data'], true) ?: [];
$course_title = $data['course_title'] ?? 'Course';

// If already enrolled
if (!empty($temp_row['enrolled_id']) && $temp_row['enrolled_id'] > 0) {
    doTopRedirect("course_thank_you.php?eid=" . $temp_row['enrolled_id'] . "&course=" . urlencode($course_title));
}

// Ensure table columns exist
$colsResult = @$conn->query("SHOW COLUMNS FROM course_enrollments");
$col_names = array();
if ($colsResult) {
    while ($r = $colsResult->fetch_assoc()) $col_names[] = $r['Field'];
}
$columns_to_check = array('student_city','student_occupation','student_qualification','student_card','payment_proof', 'father_name', 'address', 'dob', 'gender', 'student_picture', 'education_level');
foreach ($columns_to_check as $col) {
    if (!in_array($col, $col_names)) {
        $type = ($col === 'dob') ? 'DATE' : (in_array($col, array('payment_proof', 'student_card', 'student_picture', 'address')) ? 'VARCHAR(500)' : (($col === 'gender') ? 'VARCHAR(20)' : 'VARCHAR(255)'));
        @$conn->query("ALTER TABLE course_enrollments ADD COLUMN $col $type DEFAULT NULL");
    }
}

$course_id = intval($data['course_id'] ?? 0);
$student_name = $data['student_name'] ?? '';
$student_email = $data['student_email'] ?? '';
$student_phone = $data['student_phone'] ?? '';
$student_city = $data['student_city'] ?? '';
$student_occ = $data['student_occ'] ?? '';
$student_qual = $data['student_qual'] ?? '';
$student_card_path = $data['student_card_path'] ?? '';
$amount_paid = floatval($data['amount_paid'] ?? 0);
$valid_code = $data['valid_code'] ?? '';
$payment_proof = 'JAZZCASH_PAID:' . ($_POST['pp_TxnRefNo'] ?? $ref);
$father_name = $data['father_name'] ?? '';
$address = $data['address'] ?? '';
$dob = !empty($data['dob']) ? $data['dob'] : null;
$gender = $data['gender'] ?? '';
$student_picture_path = $data['student_picture_path'] ?? '';
$education_level = $data['education_level'] ?? '';

$stmt = @$conn->prepare("
    INSERT INTO course_enrollments
        (course_id, student_name, student_email, student_phone, student_city,
         student_occupation, student_qualification, student_card, amount_paid, discount_code, payment_proof, status,
         father_name, address, dob, gender, student_picture, education_level)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,'active',?,?,?,?,?,?)
");

if ($stmt) {
    $stmt->bind_param("isssssssdssssssss",
        $course_id, $student_name, $student_email, $student_phone, $student_city,
        $student_occ, $student_qual, $student_card_path, $amount_paid, $valid_code, $payment_proof,
        $father_name, $address, $dob, $gender, $student_picture_path, $education_level
    );
    $stmt->execute();
    $new_eid = $stmt->insert_id;
    $stmt->close();

    if ($new_eid > 0) {
        @$conn->query("UPDATE pending_jazzcash_orders SET enrolled_id = $new_eid WHERE order_id = '" . $conn->real_escape_string($temp_row['order_id']) . "'");
        @$conn->query("UPDATE courses SET total_students=total_students+1 WHERE id=$course_id");

        // Send Confirmation Email
        $formatted_eid = "B" . date('y') . "M" . $new_eid;
        if (!function_exists('buildEmailWrapperReg')) {
            function buildEmailWrapperReg($icon, $heading, $body_html, $btn_text='', $btn_url='') {
                $site    = 'Graphicafix';
                $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://graphicafix.com';
                $btn = $btn_text ? "<div style='text-align:center;margin-top:24px;'><a href='$btn_url' style='display:inline-block;background:#024442;color:#fff;padding:13px 30px;border-radius:50px;text-decoration:none;font-weight:700;font-size:.88rem;'>$btn_text →</a></div>" : '';
                return "<!DOCTYPE html><html><head><meta charset='utf-8'></head><body style='margin:0;padding:0;background:#f4f6f8;font-family:Arial,sans-serif;'><div style='max-width:580px;margin:36px auto;'><div style='background:#024442;border-radius:16px 16px 0 0;padding:32px 40px;text-align:center;'><div style='font-size:2.2rem;margin-bottom:8px;'>$icon</div><h1 style='margin:0;color:#fff;font-size:1.45rem;font-weight:700;'>$heading</h1></div><div style='background:#fff;padding:32px 40px;'>$body_html$btn</div><div style='background:#f7f9f5;border-radius:0 0 16px 16px;padding:14px 40px;text-align:center;border-top:1px solid #e0e0e0;'><p style='margin:0;font-size:11.5px;color:#aaa;'>$site &middot; <a href='$siteUrl' style='color:#024442;'>$siteUrl</a></p></div></div></body></html>";
            }
        }
        $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://graphicafix.com';
        $priceHtml = !empty($data['course_is_free']) ? "<span style='color:#10b981;font-weight:700;'>Free</span>" : "Rs. " . number_format($amount_paid);
        $discHtml = $valid_code ? "<tr><td style='padding:7px 0;color:#777;font-size:13px;'>Discount Status</td><td style='padding:7px 0;font-weight:700;color:#10b981;'>Applied ✓</td></tr>" : '';
        $category = $data['course_category'] ?? '';
        $level = $data['course_level'] ?? '';

        $body = "<p style='font-size:15px;color:#333;margin:0 0 16px;'>Hi <strong>$student_name</strong>,</p><p style='font-size:14px;color:#555;line-height:1.7;margin:0 0 22px;'>Thank you for registering and completing your JazzCash payment! Your enrollment is active.</p><div style='background:#f7f9f5;border-left:4px solid #b8f35a;border-radius:10px;padding:16px 20px;margin-bottom:22px;'><div style='font-size:.65rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#666;margin-bottom:4px;'>$category</div><div style='font-size:1.05rem;font-weight:700;color:#1a1a1a;'>$course_title</div><div style='font-size:.8rem;color:#888;margin-top:3px;'>$level</div></div><table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;margin-bottom:22px;'><tr><td style='padding:7px 0;color:#777;font-size:13px;'>Amount Paid</td><td style='padding:7px 0;font-weight:700;'>$priceHtml</td></tr>$discHtml<tr><td style='padding:7px 0;color:#777;font-size:13px;'>Enrollment #</td><td style='padding:7px 0;font-weight:700;color:#024442;'>$formatted_eid</td></tr><tr><td style='padding:7px 0;color:#777;font-size:13px;'>Status</td><td style='padding:7px 0;'><span style='background:#d1fae5;color:#065f46;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700;'>Active / Paid via JazzCash</span></td></tr></table>";
        $html = buildEmailWrapperReg('🎓', 'Enrollment Confirmed!', $body, 'Go To Courses', "$siteUrl/courses.php");
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: Graphicafix <noreply@graphicafix.com>\r\nReply-To: info@graphicafix.com\r\n";
        @mail($student_email, "🎓 Enrollment Confirmed — " . $course_title, $html, $headers);

        doTopRedirect("course_thank_you.php?eid=" . $new_eid . "&course=" . urlencode($course_title));
    } else {
        displayError("Database Error: Could not create enrollment.", $ref);
    }
}
?>
