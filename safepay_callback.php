<?php
/**
 * safepay_callback.php
 * Safepay redirects here after online card payment completes.
 * Automatically activates/creates enrollment and redirects top window to thank you page.
 */
error_reporting(0);
ini_set('display_errors', '0');

require_once 'config.php';

function doTopRedirect($url) {
    echo "<!DOCTYPE html><html><head><meta name='viewport' content='width=device-width, initial-scale=1'><title>Payment Successful</title></head>
    <body style='background:#f0fdf4; text-align:center; padding:50px 20px; font-family:sans-serif; margin:0;'>
    
    <div style='background:#ffffff; max-width:400px; margin:0 auto; padding:40px 30px; border-radius:16px; border: 1px solid #bbf7d0; box-shadow:0 10px 25px rgba(0,0,0,0.05);'>
        <div style='font-size:4rem; margin-bottom:15px; color:#10b981;'>✓</div>
        <h2 style='color:#065f46; margin:0 0 10px;'>Payment Verified!</h2>
        <p style='color:#15803d; line-height:1.6; margin-bottom:20px; font-size:0.95rem;'>Your enrollment is active. You can close this window now.</p>
        <p style='font-size:0.8rem; color:#999;'>Your main screen will update automatically.</p>
    </div>

    <script>
        // Attempt to close the window automatically after 2 seconds
        setTimeout(function() { window.close(); }, 2000);
    </script>
    </body></html>";
    exit;
}
// Log incoming request for debugging
@file_put_contents(__DIR__ . '/safepay_callback_debug.log', date('Y-m-d H:i:s') . " - GET: " . json_encode($_GET) . " - POST: " . json_encode($_POST) . "\n", FILE_APPEND);

$ref = trim($_GET['ref'] ?? ($_GET['order_id'] ?? ($_POST['order_id'] ?? ($_GET['tracker'] ?? ($_GET['beacon'] ?? '')))));
$tracker = $_GET['tracker'] ?? ($_GET['beacon'] ?? ($ref ?: 'SAFEPAY_PAID'));

// Ensure pending_safepay_orders table exists
@$conn->query("CREATE TABLE IF NOT EXISTS pending_safepay_orders (
    order_id VARCHAR(100) PRIMARY KEY,
    form_data LONGTEXT,
    enrolled_id INT DEFAULT 0,
    created_at DATETIME
)");

$temp_row = null;
if (!empty($ref)) {
    $stmt = @$conn->prepare("SELECT * FROM pending_safepay_orders WHERE order_id = ? OR order_id LIKE CONCAT('%', ?, '%') ORDER BY created_at DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ss", $ref, $ref);
        $stmt->execute();
        $res = $stmt->get_result();
        $temp_row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
}

// Fallback: match the latest unenrolled order from pending_safepay_orders
if (!$temp_row) {
    $res = @$conn->query("SELECT * FROM pending_safepay_orders WHERE enrolled_id = 0 ORDER BY created_at DESC LIMIT 1");
    $temp_row = $res ? $res->fetch_assoc() : null;
}

if ($temp_row) {
    $data = json_decode($temp_row['form_data'], true) ?: [];
    $course_title = $data['course_title'] ?? 'Course';

    // If already enrolled (e.g. user refreshed callback page), redirect top window
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
    $payment_proof = 'SAFEPAY_PAID:' . $tracker;
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
            @$conn->query("UPDATE pending_safepay_orders SET enrolled_id = $new_eid WHERE order_id = '" . $conn->real_escape_string($temp_row['order_id']) . "'");
            @$conn->query("UPDATE courses SET total_students=total_students+1 WHERE id=$course_id");

            // Send Confirmation Emails
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

            $body = "<p style='font-size:15px;color:#333;margin:0 0 16px;'>Hi <strong>$student_name</strong>,</p><p style='font-size:14px;color:#555;line-height:1.7;margin:0 0 22px;'>Thank you for registering and completing your online payment! Your enrollment is active.</p><div style='background:#f7f9f5;border-left:4px solid #b8f35a;border-radius:10px;padding:16px 20px;margin-bottom:22px;'><div style='font-size:.65rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#666;margin-bottom:4px;'>$category</div><div style='font-size:1.05rem;font-weight:700;color:#1a1a1a;'>$course_title</div><div style='font-size:.8rem;color:#888;margin-top:3px;'>$level</div></div><table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;margin-bottom:22px;'><tr><td style='padding:7px 0;color:#777;font-size:13px;'>Amount Paid</td><td style='padding:7px 0;font-weight:700;'>$priceHtml</td></tr>$discHtml<tr><td style='padding:7px 0;color:#777;font-size:13px;'>Enrollment #</td><td style='padding:7px 0;font-weight:700;color:#024442;'>$formatted_eid</td></tr><tr><td style='padding:7px 0;color:#777;font-size:13px;'>Status</td><td style='padding:7px 0;'><span style='background:#d1fae5;color:#065f46;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700;'>Active / Paid</span></td></tr></table>";
            $html = buildEmailWrapperReg('🎓', 'Enrollment Confirmed!', $body, 'Go To Courses', "$siteUrl/courses.php");
            $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: Graphicafix <noreply@graphicafix.com>\r\nReply-To: info@graphicafix.com\r\n";
            @mail($student_email, "🎓 Enrollment Confirmed — " . $course_title, $html, $headers);

            doTopRedirect("course_thank_you.php?eid=" . $new_eid . "&course=" . urlencode($course_title));
        }
    }
}

// Check old/legacy direct records just in case
$eid = isset($_GET['eid']) ? intval($_GET['eid']) : 0;
if ($eid > 0 || !empty($ref)) {
    $query = ($eid > 0) ? "WHERE e.id = $eid" : "WHERE e.payment_proof LIKE '%" . $conn->real_escape_string($ref) . "%' ORDER BY e.id DESC LIMIT 1";
    $res = @$conn->query("SELECT e.id, c.title AS course_title FROM course_enrollments e LEFT JOIN courses c ON e.course_id = c.id $query");
    $old_row = $res ? $res->fetch_assoc() : null;
    if (!$old_row) {
        $res = @$conn->query("SELECT e.id, c.title AS course_title FROM course_enrollments e LEFT JOIN courses c ON e.course_id = c.id WHERE e.status = 'pending' AND e.payment_proof LIKE 'CARD_PAYMENT_PENDING%' ORDER BY e.id DESC LIMIT 1");
        $old_row = $res ? $res->fetch_assoc() : null;
    }
    if ($old_row) {
        $matched_eid = intval($old_row['id']);
        @$conn->query("UPDATE course_enrollments SET status = 'active', payment_proof = 'SAFEPAY_PAID:" . $conn->real_escape_string($tracker) . "' WHERE id = $matched_eid");
        doTopRedirect("course_thank_you.php?eid=" . $matched_eid . "&course=" . urlencode($old_row['course_title'] ?? 'Course'));
    }
}

// Fallback error if no pending enrollment found at all
include('header.php');
echo "<div style='text-align:center;padding:100px 20px;font-family:Poppins,sans-serif;'>
        <i class='fas fa-exclamation-triangle' style='font-size:48px;color:#ef4444;margin-bottom:20px;display:block;'></i>
        <h2>Enrollment Not Found</h2>
        <p style='color:#666;max-width:500px;margin:10px auto 25px;'>We could not verify your pending payment. If your payment was completed successfully, please contact support with your payment receipt so we can activate your access.</p>
        <a href='courses.php' style='background:#024442;color:#fff;padding:12px 28px;border-radius:50px;text-decoration:none;font-weight:700;'>Return to Courses</a>
      </div>";
include('footer.php');
exit;
