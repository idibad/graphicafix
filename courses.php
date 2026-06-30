<?php
// ── AJAX ENDPOINT FOR REFERRAL CODE VERIFICATION ──
if (isset($_GET['verify_code'])) {
    // 1. Trap all output (stops config.php from leaking HTML)
    ob_start(); 
    
    require_once 'config.php'; 
    
    $code = $conn->real_escape_string(trim($_GET['verify_code']));
    $chk = $conn->query("SELECT id FROM referral_codes WHERE code='$code' AND is_used=0");
    
    $is_valid = ($chk && $chk->num_rows > 0);
    
    // 2. Erase the trapped HTML (deletes the <link> tags)
    ob_clean(); 
    
    // 3. Output pure JSON
    header('Content-Type: application/json');
    echo json_encode(['valid' => $is_valid]);
    exit;
}

// ── PROCESS ENROLLMENT FORM SUBMISSION DIRECTLY IN COURSES.PHP ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id'])) {
    error_reporting(0);
    ini_set('display_errors', '0');
    require_once 'config.php';

    function clean_reg($v) { 
        return trim(htmlspecialchars($v, ENT_QUOTES, 'UTF-8')); 
    }

    $course_id    = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $student_name = isset($_POST['student_name']) ? clean_reg($_POST['student_name']) : '';
    $student_email= isset($_POST['student_email']) ? trim($_POST['student_email']) : '';
    $student_phone= isset($_POST['student_phone']) ? clean_reg($_POST['student_phone']) : '';
    $student_city = isset($_POST['student_city']) ? clean_reg($_POST['student_city']) : '';
    $student_occ  = isset($_POST['student_occupation']) ? clean_reg($_POST['student_occupation']) : '';
    $student_qual = isset($_POST['student_qualification']) ? clean_reg($_POST['student_qualification']) : '';
    $discount_pct = isset($_POST['discount_pct']) ? intval($_POST['discount_pct']) : 0;

    $father_name  = isset($_POST['father_name']) ? clean_reg($_POST['father_name']) : '';
    $address      = isset($_POST['address']) ? clean_reg($_POST['address']) : '';
    $dob          = !empty($_POST['dob']) ? clean_reg($_POST['dob']) : null;
    $gender       = isset($_POST['gender']) ? clean_reg($_POST['gender']) : '';
    $education_level = isset($_POST['education_level']) ? clean_reg($_POST['education_level']) : '';
    $payment_type = isset($_POST['payment_type']) ? clean_reg($_POST['payment_type']) : 'full';

    if (!isset($_POST['course_id']) || $_POST['course_id'] === '' || !$student_name || !$student_email || !$student_phone || !$student_occ) {
        die("Required fields missing.");
    }

    $cstmt = @$conn->prepare("SELECT * FROM courses WHERE id=? AND is_published=1");
    if (!$cstmt) die("Database error.");
    $cstmt->bind_param("i", $course_id); 
    $cstmt->execute();
    $courseResult = $cstmt->get_result();
    $course = $courseResult ? $courseResult->fetch_assoc() : null;
    $cstmt->close();

    if (!$course) die("Course not found.");

    $estmt = @$conn->prepare("SELECT id FROM course_enrollments WHERE course_id=? AND student_email=? AND status IN ('pending','active','completed')");
    if ($estmt) {
        $estmt->bind_param("is", $course_id, $student_email); 
        $estmt->execute();
        $existingResult = $estmt->get_result();
        $existing = $existingResult ? $existingResult->fetch_assoc() : null;
        $estmt->close();

        if ($existing) {
            $ex_id = $existing['id'];
            header("Location: course_thank_you.php?eid=" . $ex_id . "&course=" . urlencode($course['title']) . "&already=1");
            exit;
        }
    }

    $price       = !empty($course['is_free']) ? 0 : (float)$course['price'];
    $disc_amount = 0;
    $valid_code  = '';

    if ($discount_pct > 0 && empty($course['is_free'])) {
        $valid_code = "OCCUPATION_" . strtoupper($student_occ);
        $disc_amount = round($price * $discount_pct / 100, 2);
    }
    $amount_paid = max(0, $price - $disc_amount);

    if (!empty($_POST['applied_referral_code'])) {
        $ref_code = clean_reg($_POST['applied_referral_code']);
        $ref_chk = @$conn->query("SELECT id FROM referral_codes WHERE code='$ref_code' AND is_used=0");
        if ($ref_chk && $ref_chk->num_rows > 0) {
            $valid_code = $ref_code;
            $amount_paid = 0;
        }
    }

    if ($payment_type === 'installment' && !empty($course['installments_enabled']) && $course['installment_count'] > 1 && $amount_paid > 0) {
        $amount_paid = ceil($amount_paid / $course['installment_count']);
    }

    $student_picture_path = null;
    if (!empty($_FILES['student_picture']['name']) && $_FILES['student_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'images/courses/student_img/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['student_picture']['name'], PATHINFO_EXTENSION));
        $allowed = array('jpg','jpeg','png','webp');
        if (!in_array($ext, $allowed)) die("Invalid file type for student picture.");
        if ($_FILES['student_picture']['size'] > 5*1024*1024) die("Student picture file too large (max 5MB).");
        $newName = 'pic_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['student_picture']['tmp_name'], $uploadDir.$newName)) {
            $student_picture_path = $uploadDir.$newName;
        }
    }

    $student_card_path = null;
    if ($student_occ === 'student' && empty($course['is_free']) && !empty($course['student_discount']) && $course['student_discount'] > 0) {
        if (!empty($_FILES['student_card']['name']) && $_FILES['student_card']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'images/courses/student_idcard/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['student_card']['name'], PATHINFO_EXTENSION));
            $allowed = array('jpg','jpeg','png','webp','pdf');
            if (!in_array($ext, $allowed)) die("Invalid file type for student card.");
            if ($_FILES['student_card']['size'] > 5*1024*1024) die("Student card file too large (max 5MB).");
            $newName = 'id_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['student_card']['tmp_name'], $uploadDir.$newName)) {
                $student_card_path = $uploadDir.$newName;
            }
        } else {
            die("CNIC / Form B / Student ID Card is required to claim the student discount.");
        }
    }

    $payment_path = null;
    if ($amount_paid > 0 && !empty($_FILES['payment_proof']['name']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/payment_proofs/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
        $ext     = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
        $allowed = array('jpg','jpeg','png','webp','pdf');
        if (!in_array($ext, $allowed)) die("Invalid file type for payment proof.");
        $newName = 'pay_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $uploadDir.$newName)) {
            $payment_path = $uploadDir.$newName;
        }
    }

    $temp_order_id = "ORD" . date('ymdHis') . rand(100, 999);

    // SCENARIO: SAFEPAY ONLINE CARD PAYMENT (DELAY DB ENROLLMENT UNTIL PAYMENT SUCCESS)
    if ($amount_paid > 0 && ($_POST['payment_method'] ?? '') === 'online_card') {
        if (!defined('SAFEPAY_API_URL') || !defined('SAFEPAY_API_KEY')) {
            die("Safepay configuration missing in config.php. Please set SAFEPAY_API_KEY.");
        }

        @$conn->query("CREATE TABLE IF NOT EXISTS pending_safepay_orders (
            order_id VARCHAR(100) PRIMARY KEY,
            form_data LONGTEXT,
            enrolled_id INT DEFAULT 0,
            created_at DATETIME
        )");

        $form_data_json = json_encode([
            'course_id' => $course_id,
            'student_name' => $student_name,
            'student_email' => $student_email,
            'student_phone' => $student_phone,
            'student_city' => $student_city,
            'student_occ' => $student_occ,
            'student_qual' => $student_qual,
            'student_card_path' => $student_card_path,
            'amount_paid' => $amount_paid,
            'valid_code' => $valid_code,
            'father_name' => $father_name,
            'address' => $address,
            'dob' => $dob,
            'gender' => $gender,
            'student_picture_path' => $student_picture_path,
            'education_level' => $education_level,
            'course_title' => $course['title'] ?? '',
            'course_category' => $course['category'] ?? '',
            'course_level' => $course['level'] ?? '',
            'course_is_free' => $course['is_free'] ?? 0
        ]);

        $stmt_temp = @$conn->prepare("INSERT INTO pending_safepay_orders (order_id, form_data, created_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE form_data = VALUES(form_data)");
        if ($stmt_temp) {
            $stmt_temp->bind_param("ss", $temp_order_id, $form_data_json);
            $stmt_temp->execute();
            $stmt_temp->close();
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $base_url = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $raw_success = $base_url . "/safepay_callback.php?ref=" . urlencode($temp_order_id);
        $raw_cancel  = $base_url . "/courses.php?course_id=" . $course_id . "&payment=cancelled";

        $api_url = rtrim(SAFEPAY_API_URL, '/') . "/order/v1/init";
        $post_data = json_encode([
            "client"       => SAFEPAY_API_KEY,
            "amount"       => floatval($amount_paid),
            "currency"     => "PKR",
            "environment"  => SAFEPAY_ENVIRONMENT,
            "redirect_url" => $raw_success,
            "success_url"  => $raw_success,
            "cancel_url"   => $raw_cancel,
            "source"       => "custom",
            "webhooks"     => "true"
        ]);

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $api_headers = ["Content-Type: application/json"];
        if (strpos(SAFEPAY_API_KEY, 'sec_') === 0) {
            $api_headers[] = "X-SFPY-MERCHANT-SECRET: " . SAFEPAY_API_KEY;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $api_headers);
        $response = curl_exec($ch);
        $curl_err = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $safepay_data = json_decode($response, true);
        if (isset($safepay_data['data']['token'])) {
            $tracker = $safepay_data['data']['token'];
            $safepay_checkout_url = rtrim(SAFEPAY_API_URL, '/') . "/checkout/pay"
                . "?env=" . SAFEPAY_ENVIRONMENT
                . "&beacon=" . $tracker
                . "&order_id=" . $temp_order_id
                . "&source=custom"
                . "&redirect_url=" . urlencode($raw_success)
                . "&success_url=" . urlencode($raw_success)
                . "&cancel_url=" . urlencode($raw_cancel);
                
            include('header.php');
            ?>
            <style>
                .embedded-checkout-container {
                    max-width: 960px;
                    margin: 40px auto 80px;
                    background: #ffffff;
                    border-radius: 20px;
                    box-shadow: 0 20px 50px rgba(2, 68, 66, 0.12);
                    border: 1px solid rgba(2, 68, 66, 0.08);
                    overflow: hidden;
                    font-family: 'Poppins', sans-serif;
                }
                .embedded-checkout-header {
                    background: #024442;
                    padding: 30px 35px;
                    color: #fff;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 15px;
                }
                .embedded-checkout-badge {
                    background: #b8f35a;
                    color: #024442;
                    font-size: 0.75rem;
                    font-weight: 800;
                    text-transform: uppercase;
                    letter-spacing: 1.2px;
                    padding: 5px 14px;
                    border-radius: 50px;
                    display: inline-block;
                    margin-bottom: 8px;
                }
                .embedded-checkout-title {
                    font-size: 1.5rem;
                    font-weight: 700;
                    margin: 0;
                    color: #ffffff;
                }
                .embedded-checkout-price-box {
                    text-align: right;
                    background: rgba(255,255,255,0.08);
                    padding: 10px 22px;
                    border-radius: 14px;
                    border: 1px solid rgba(255,255,255,0.15);
                }
                .embedded-checkout-amount {
                    font-size: 1.6rem;
                    font-weight: 800;
                    color: #b8f35a;
                    line-height: 1.2;
                }
                .embedded-checkout-iframe-wrapper {
                    position: relative;
                    background: #fcfcfc;
                    min-height: 650px;
                }
            </style>
            <div class="container">
                <div class="embedded-checkout-container">
                    <div class="embedded-checkout-header">
                        <div>
                            <span class="embedded-checkout-badge">Online Card Payment</span>
                            <h1 class="embedded-checkout-title"><?php echo htmlspecialchars($course['title'] ?? 'Course Enrollment'); ?></h1>
                            <p style="margin: 4px 0 0; color: rgba(255,255,255,0.8); font-size: 0.9rem;">
                                Student Name: <strong><?php echo htmlspecialchars($student_name); ?></strong>
                            </p>
                        </div>
                        <div class="embedded-checkout-price-box">
                            <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.7);">Total Amount</div>
                            <div class="embedded-checkout-amount">Rs. <?php echo number_format($amount_paid); ?></div>
                        </div>
                    </div>
                    <div style="padding: 45px 30px; background: #ffffff;">
                        <div style="max-width: 540px; margin: 0 auto;">
                            <div style="text-align: center; margin-bottom: 30px;">
                                <div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 50%; background: rgba(2, 68, 66, 0.08); color: #024442; font-size: 26px; margin-bottom: 15px;">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <h3 style="margin: 0; font-size: 1.35rem; color: #1e293b; font-weight: 700;">Card Checkout Portal</h3>
                                <p style="margin: 8px 0 0; color: #64748b; font-size: 0.95rem;">Enter your debit or credit card details securely below.</p>
                            </div>

                            <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 20px; padding: 30px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; padding-bottom: 15px; border-bottom: 1px dashed #cbd5e1;">
                                    <span style="font-size: 0.92rem; font-weight: 600; color: #475569;">Accepted Cards:</span>
                                    <div style="display: flex; gap: 12px; font-size: 24px; color: #64748b;">
                                        <i class="fab fa-cc-visa" style="color:#1a1f71;"></i>
                                        <i class="fab fa-cc-mastercard" style="color:#eb001b;"></i>
                                        <i class="fas fa-credit-card" style="color:#024442;"></i>
                                    </div>
                                </div>

                                <div style="margin-bottom: 20px;">
                                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: #475569; margin-bottom: 6px;">
                                        <span>Course Fee</span>
                                        <span style="font-weight: 700; color: #1e293b;">Rs. <?php echo number_format($amount_paid); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: #10b981; font-weight: 600;">
                                        <span>Processing & Gateway Fees</span>
                                        <span>Rs. 0 (Free)</span>
                                    </div>
                                </div>

                                <!-- Primary action button launching top-level secure card form without iframe restrictions -->
                                <div id="safepay-action-wrapper">
                                    <button onclick="openSafepaySecureModal()" id="safepay-pay-btn" style="width: 100%; background: #024442; color: #ffffff; border: none; padding: 18px 24px; border-radius: 14px; font-weight: 700; font-size: 1.08rem; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 12px; box-shadow: 0 10px 25px -5px rgba(2, 68, 66, 0.35);">
                                        <i class="fas fa-lock" style="color: #b8f35a;"></i>
                                        <span>Enter Card & Pay Rs. <?php echo number_format($amount_paid); ?></span>
                                    </button>
                                </div>
                            </div>

                            <div style="text-align: center; font-size: 0.84rem; color: #94a3b8; line-height: 1.6;">
                                <i class="fas fa-shield-alt" style="color: #10b981; font-size: 1rem; margin-right: 4px;"></i> 
                                <strong>100% Secure Checkout.</strong> Clicking the button opens a clean, PCI-compliant authorization window where your card details are processed without browser cookie restrictions.
                            </div>
                        </div>
                    </div>
                    <script>
    function openSafepaySecureModal() {
        var wrapper = document.getElementById('safepay-action-wrapper');
        
        // Change UI to Loading State
        wrapper.innerHTML = `
            <button disabled style="width: 100%; background: #024442; color: #ffffff; border: none; padding: 18px 24px; border-radius: 14px; font-weight: 700; font-size: 1.05rem; display: flex; align-items: center; justify-content: center; gap: 12px; box-shadow: 0 10px 25px -5px rgba(2, 68, 66, 0.35);">
                <i class="fas fa-spinner fa-spin" style="color: #b8f35a;"></i>
                <span>Processing in Secure Window...</span>
            </button>
            <div style="margin-top: 14px; font-size: 0.88rem; color: #024442; text-align: center; font-weight: 600; background: #f0fdf4; padding: 10px; border-radius: 10px; border: 1px solid #bbf7d0;">
                <i class="fas fa-satellite-dish" style="color: #10b981;"></i> Waiting for payment confirmation. This page will update automatically.
            </div>
        `;
        
        var checkoutUrl = <?php echo json_encode($safepay_checkout_url); ?>;
        var orderId = <?php echo json_encode($temp_order_id); ?>;
        
        // Open the Popup
        var width = 650;
        var height = 750;
        var left = (screen.width - width) / 2;
        var top = (screen.height - height) / 2;
        var popup = window.open(checkoutUrl, 'SafepaySecurePayment', 'width=' + width + ',height=' + height + ',top=' + top + ',left=' + left + ',scrollbars=yes,resizable=yes');
        
        // The Bulletproof Polling Loop: Check database every 2 seconds
        var pollTimer = setInterval(function() {
            fetch('courses.php?check_order_status=' + orderId)
                .then(response => response.json())
                .then(data => {
                    if (data.paid) {
                        // Payment found in DB! Stop checking.
                        clearInterval(pollTimer);
                        
                        // Force close the popup if it's still open
                        if (popup && !popup.closed) { popup.close(); }
                        
                        wrapper.innerHTML = '<button disabled style="width: 100%; background: #10b981; color: #ffffff; border: none; padding: 18px 24px; border-radius: 14px; font-weight: 700; font-size: 1.05rem; display: flex; align-items: center; justify-content: center; gap: 12px;"><i class="fas fa-check-circle"></i> <span>Payment Successful! Redirecting...</span></button>';
                        
                        // Go to Thank You page
                        window.location.href = data.redirectUrl;
                    } else if (popup && popup.closed) {
                        // User closed the window manually without paying
                        clearInterval(pollTimer);
                        wrapper.innerHTML = '<button onclick="openSafepaySecureModal()" style="width: 100%; background: #ef4444; color: #ffffff; border: none; padding: 18px 24px; border-radius: 14px; font-weight: 700; font-size: 1.05rem; display: flex; align-items: center; justify-content: center; gap: 12px; cursor: pointer; transition: 0.3s;"><i class="fas fa-redo"></i> <span>Payment Incomplete — Click to Try Again</span></button>';
                    }
                })
                .catch(err => console.error("Polling error:", err));
        }, 2000);
    }
</script>
                    <div style="padding: 18px 35px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <div style="font-size: 0.85rem; color: #64748b;">
                            <i class="fas fa-shield-alt" style="color: #10b981; margin-right: 5px;"></i> Secured by Safepay 256-bit Encryption
                        </div>
                        <a href="<?php echo htmlspecialchars($raw_cancel); ?>" style="color: #ef4444; font-weight: 600; font-size: 0.88rem; text-decoration: none;">
                            <i class="fas fa-times-circle"></i> Cancel Payment
                        </a>
                    </div>
                </div>
            </div>
            <?php
            include('footer.php');
            exit;
        } else {
            $err_detail = $curl_err ?: ($safepay_data['message'] ?? $response);
            die("<div style='font-family:Poppins,sans-serif;max-width:600px;margin:80px auto;padding:30px;background:#fff;border:2px solid #fca5a5;border-radius:16px;text-align:center;'>
                <div style='font-size:3rem;margin-bottom:15px;'>⚠️</div>
                <h2 style='color:#dc2626;margin:0 0 10px;'>Payment Gateway Error</h2>
                <p style='color:#555;line-height:1.6;'>Unable to connect to the card payment gateway. Please try again or use Bank Transfer / EasyPaisa instead.</p>
                <p style='font-size:12px;color:#999;margin-top:15px;'>Debug: HTTP $http_code — " . htmlspecialchars(substr($err_detail, 0, 200)) . "</p>
                <a href='courses.php?course_id=$course_id' style='display:inline-block;margin-top:20px;background:#024442;color:#fff;padding:12px 28px;border-radius:50px;text-decoration:none;font-weight:700;'>← Go Back & Try Again</a>
            </div>");
        }
    }

    // SCENARIO: MANUAL BANK TRANSFER / EASYPAISA (DEFAULT)
    $colsResult = @$conn->query("SHOW COLUMNS FROM course_enrollments");
    $col_names = array();
    if ($colsResult) {
        while ($row = $colsResult->fetch_assoc()) {
            $col_names[] = $row['Field'];
        }
    }
    $columns_to_check = array('student_city','student_occupation','student_qualification','student_card','payment_proof', 'father_name', 'address', 'dob', 'gender', 'student_picture', 'education_level');
    foreach ($columns_to_check as $col) {
        if (!in_array($col, $col_names)) {
            if ($col === 'dob') {
                $type = 'DATE';
            } else if (in_array($col, array('payment_proof', 'student_card', 'student_picture', 'address'))) {
                $type = 'VARCHAR(500)';
            } else if ($col === 'gender') {
                $type = 'VARCHAR(20)';
            } else {
                $type = 'VARCHAR(255)';
            }
            @$conn->query("ALTER TABLE course_enrollments ADD COLUMN $col $type DEFAULT NULL");
        }
    }

    $stmt = @$conn->prepare("
        INSERT INTO course_enrollments
            (course_id, student_name, student_email, student_phone, student_city,
             student_occupation, student_qualification, student_card, amount_paid, discount_code, payment_proof, status,
             father_name, address, dob, gender, student_picture, education_level)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending',?,?,?,?,?,?)
    ");

    if (!$stmt) die("Enrollment prepare failed: " . $conn->error);

    $stmt->bind_param("isssssssdssssssss",
        $course_id, $student_name, $student_email, $student_phone, $student_city,
        $student_occ, $student_qual, $student_card_path, $amount_paid, $valid_code, $payment_path,
        $father_name, $address, $dob, $gender, $student_picture_path, $education_level
    );

    if (!$stmt->execute()) die("Enrollment failed: " . $stmt->error);

    $raw_id = $stmt->insert_id;
    $stmt->close();

    $formatted_eid = "B" . date('y') . "M" . $raw_id;
    @$conn->query("UPDATE courses SET total_students=total_students+1 WHERE id=$course_id");

    if (!empty($_POST['applied_referral_code'])) {
        $ref_code = trim($_POST['applied_referral_code']);
        $ref_stmt = @$conn->prepare("UPDATE referral_codes SET is_used = 1, used_by_email = ? WHERE code = ? AND is_used = 0");
        if ($ref_stmt) {
            $ref_stmt->bind_param("ss", $student_email, $ref_code);
            $ref_stmt->execute();
            $ref_stmt->close();
        }
    }
// ── AJAX ENDPOINT FOR SAFEPAY STATUS POLLING ──
if (isset($_GET['check_order_status'])) {
    ob_start();
    require_once 'config.php';
    
    $order_id = $conn->real_escape_string(trim($_GET['check_order_status']));
    $res = $conn->query("SELECT enrolled_id, form_data FROM pending_safepay_orders WHERE order_id = '$order_id' AND enrolled_id > 0 LIMIT 1");
    
    $paid = false;
    $url = '';
    
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $fd = json_decode($row['form_data'], true);
        $paid = true;
        // Generate the success URL
        $url = "course_thank_you.php?eid=" . $row['enrolled_id'] . "&course=" . urlencode($fd['course_title'] ?? 'Course');
    }
    
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['paid' => $paid, 'redirectUrl' => $url]);
    exit;
}
    if (!function_exists('buildEmailWrapperReg')) {
        function buildEmailWrapperReg($icon, $heading, $body_html, $btn_text='', $btn_url='') {
            $site    = 'Graphicafix';
            $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://graphicafix.com';
            $btn = $btn_text ? "<div style='text-align:center;margin-top:24px;'><a href='$btn_url' style='display:inline-block;background:#024442;color:#fff;padding:13px 30px;border-radius:50px;text-decoration:none;font-weight:700;font-size:.88rem;'>$btn_text →</a></div>" : '';
            return "<!DOCTYPE html><html><head><meta charset='utf-8'></head><body style='margin:0;padding:0;background:#f4f6f8;font-family:Arial,sans-serif;'><div style='max-width:580px;margin:36px auto;'><div style='background:#024442;border-radius:16px 16px 0 0;padding:32px 40px;text-align:center;'><div style='font-size:2.2rem;margin-bottom:8px;'>$icon</div><h1 style='margin:0;color:#fff;font-size:1.45rem;font-weight:700;'>$heading</h1></div><div style='background:#fff;padding:32px 40px;'>$body_html$btn</div><div style='background:#f7f9f5;border-radius:0 0 16px 16px;padding:14px 40px;text-align:center;border-top:1px solid #e0e0e0;'><p style='margin:0;font-size:11.5px;color:#aaa;'>$site &middot; <a href='$siteUrl' style='color:#024442;'>$siteUrl</a></p></div></div></body></html>";
        }
    }

    $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://graphicafix.com';
    $priceHtml = !empty($course['is_free']) ? "<span style='color:#10b981;font-weight:700;'>Free</span>" : "Rs. " . number_format($amount_paid);
    $discHtml = $valid_code ? "<tr><td style='padding:7px 0;color:#777;font-size:13px;'>Discount Status</td><td style='padding:7px 0;font-weight:700;color:#10b981;'>Applied ✓</td></tr>" : '';
    $category = isset($course['category']) ? $course['category'] : '';
    $title    = isset($course['title']) ? $course['title'] : '';
    $level    = isset($course['level']) ? $course['level'] : '';

    $body = "<p style='font-size:15px;color:#333;margin:0 0 16px;'>Hi <strong>$student_name</strong>,</p><p style='font-size:14px;color:#555;line-height:1.7;margin:0 0 22px;'>Thank you for registering! We've received your enrollment. Our team will review your details within 24 hours.</p><div style='background:#f7f9f5;border-left:4px solid #b8f35a;border-radius:10px;padding:16px 20px;margin-bottom:22px;'><div style='font-size:.65rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#666;margin-bottom:4px;'>$category</div><div style='font-size:1.05rem;font-weight:700;color:#1a1a1a;'>$title</div><div style='font-size:.8rem;color:#888;margin-top:3px;'>$level</div></div><table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;margin-bottom:22px;'><tr><td style='padding:7px 0;color:#777;font-size:13px;'>Amount Due</td><td style='padding:7px 0;font-weight:700;'>$priceHtml</td></tr>$discHtml<tr><td style='padding:7px 0;color:#777;font-size:13px;'>Enrollment #</td><td style='padding:7px 0;font-weight:700;color:#024442;'>$formatted_eid</td></tr><tr><td style='padding:7px 0;color:#777;font-size:13px;'>Status</td><td style='padding:7px 0;'><span style='background:#fef9c3;color:#a16207;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700;'>Pending Review</span></td></tr></table>";
    $html = buildEmailWrapperReg('🎓', 'Registration Received!', $body, 'Browse More Courses', "$siteUrl/courses.php");
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: Graphicafix <noreply@graphicafix.com>\r\nReply-To: info@graphicafix.com\r\n";
    @mail($student_email, "🎓 Registration Received — " . $title, $html, $headers);

    $payRow  = $payment_path ? "<tr><td style='font-weight:700;padding:8px 12px;'>Payment Proof</td><td style='padding:8px 12px;'><a href='$siteUrl/$payment_path' style='color:#024442;'>View Receipt</a></td></tr>" : '';
    $cardRow = $student_card_path ? "<tr style='background:#f7f9f5;'><td style='font-weight:700;padding:8px 12px;'>Student Card</td><td style='padding:8px 12px;'><a href='$siteUrl/$student_card_path' style='color:#024442;'>View ID Card</a></td></tr>" : '';
    $admBody = "<p style='font-size:14px;color:#555;margin:0 0 16px;'>A new course registration has been submitted.</p><table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;margin-bottom:20px;'><tr><td style='font-weight:700;padding:8px 12px;background:#f7f9f5;width:120px;'>Student</td><td style='padding:8px 12px;'>$student_name</td></tr><tr><td style='font-weight:700;padding:8px 12px;'>Email</td><td style='padding:8px 12px;'>$student_email</td></tr><tr style='background:#f7f9f5;'><td style='font-weight:700;padding:8px 12px;'>Phone</td><td style='padding:8px 12px;'>$student_phone</td></tr><tr><td style='font-weight:700;padding:8px 12px;'>Course</td><td style='padding:8px 12px;'>$title</td></tr><tr style='background:#f7f9f5;'><td style='font-weight:700;padding:8px 12px;'>Amount</td><td style='padding:8px 12px;font-weight:700;color:#024442;'>Rs. " . number_format($amount_paid) . "</td></tr>$payRow$cardRow</table>";
    $admHtml = buildEmailWrapperReg('📋', 'New Enrollment #' . $formatted_eid, $admBody, 'Manage in Admin', "$siteUrl/admin/manage_courses.php");
    @mail('info@graphicafix.com', "📋 New Course Registration — " . $title, $admHtml, $headers);

    header("Location: course_thank_you.php?eid=" . $raw_id . "&course=" . urlencode($course['title']));
    exit;
}
include('header.php');

// Fetch all published courses
$courses_result = $conn->query("
    SELECT c.*, i.name AS instructor_name, i.bio AS instructor_bio, i.avatar AS instructor_avatar
    FROM courses c
    LEFT JOIN course_instructors i ON c.instructor_id = i.id
    WHERE c.is_published = 1
    ORDER BY c.is_featured DESC, c.title ASC
");
$courses = $courses_result ? $courses_result->fetch_all(MYSQLI_ASSOC) : [];

function fmtDur($m) {
    $h = floor($m/60); $m = $m%60;
    return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
}

function starRating($r) {
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= $i <= $r
            ? '<i class="fas fa-star" style="color:#f59e0b;font-size:.72rem;"></i>'
            : '<i class="far fa-star" style="color:#ddd;font-size:.72rem;"></i>';
    }
    return $out;
}

// Pre-select from URL
$preselect_id = intval($_GET['course_id'] ?? 0);
$preselect = null;
foreach ($courses as $c) {
    if ($c['id'] === $preselect_id) { $preselect = $c; break; }
}
?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" referrerpolicy="no-referrer" />

<style>
.creg *:not(i) { font-family: 'Poppins', Helvetica, Arial, sans-serif; }.creg-hero { background: var(--primary); padding: 140px 0 60px; position: relative; }
.creg-hero::before { content:''; position:absolute; inset:0; background:url('../images/doodles-bg.png') center/cover; opacity:.05; }
.creg-hero .container { position: relative; z-index: 1; }
.creg-hero h1 { color: #fff; font-size: clamp(2rem,4vw,3rem); font-weight: 800; margin-bottom: 12px; }
.creg-hero p  { color: rgba(255,255,255,.7); font-size: 1rem; max-width: 520px; line-height: 1.7; }

.creg-body { padding: 60px 0; background: #f7f9f5; min-height: 60vh; }

/* Wizard Steps */
.creg-steps { display: flex; align-items: center; justify-content: center; margin-bottom: 40px; flex-wrap: wrap; gap:10px; }
.creg-step { display: flex; align-items: center; gap: 8px; font-size: .8rem; font-weight: 600; color: #bbb; transition: color .3s; }
.creg-step.active { color: var(--primary); }
.creg-step-num { width: 28px; height: 28px; border-radius: 50%; background: #e0e0e0; color: #888; display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 800; }
.creg-step.active .creg-step-num { background: var(--primary); color: #fff; }
.creg-step.done .creg-step-num   { background: var(--accent);  color: var(--primary); }
.creg-step.done  { color: var(--primary); }
.creg-step-line  { width: 30px; height: 2px; background: #e0e0e0; margin: 0 5px; transition: background .3s; }
.creg-step-line.done { background: var(--accent); }

/* ── Rich Course Cards (Step 1) ── */
.crs-card { background: #fff; border-radius: 16px; overflow: hidden; border: 1.5px solid #ebebeb; transition: all .3s ease; display: flex; flex-direction: column; height: 100%; cursor: pointer; text-align: left; position: relative; }
.crs-card:hover { transform: translateY(-5px); box-shadow: 0 18px 44px rgba(2,68,66,.1); border-color: var(--primary); }
.crs-card-thumb { position: relative; aspect-ratio: 16/9; overflow: hidden; background: var(--primary); }
.crs-card-thumb img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s; }
.crs-card:hover .crs-card-thumb img { transform: scale(1.05); }
.crs-thumb-fallback { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; background: linear-gradient(135deg,var(--primary),#035b58); }

.crs-badge-left { position: absolute; top: 10px; left: 10px; z-index: 2; }
.crs-badge-right { position: absolute; top: 10px; right: 10px; z-index: 2; }
.crs-thumb-badge { background: var(--accent); color: var(--primary); font-size: .6rem; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; padding: 4px 10px; border-radius: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }

.crs-play-overlay { position: absolute; inset: 0; background: rgba(2,68,66,.45); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity .25s; z-index:1; }
.crs-card:hover .crs-play-overlay { opacity: 1; }
.crs-play-btn { width: 52px; height: 52px; background: rgba(255,255,255,.95); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--primary); transform: scale(.8); transition: transform .25s; }
.crs-card:hover .crs-play-btn { transform: scale(1); }

.crs-card-body { padding: 18px 20px; display: flex; flex-direction: column; flex-grow: 1; }
.crs-card-cat  { font-size: .65rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--primary); margin-bottom: 6px; }
.crs-card-title { font-size: .95rem; font-weight: 700; color: #1a1a1a; line-height: 1.35; margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.crs-card-tagline { font-size: .8rem; font-weight: 300; color: #777; line-height: 1.5; margin-bottom: 12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.crs-card-instructor { font-size: .74rem; color: #aaa; margin-bottom: 10px; }
.crs-card-instructor span { font-weight: 600; color: #666; }

.crs-card-meta { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; }
.crs-students { font-size: .72rem; color: #aaa; }
.crs-level-badge { padding: 3px 10px; border-radius: 20px; font-size: .65rem; font-weight: 700; background: #f0f0f0; color: #666; }
.crs-duration { font-size: .72rem; color: #aaa; display: flex; align-items: center; gap: 4px; }

.crs-card-footer { margin-top: auto; display: flex; align-items: center; justify-content: space-between; padding: 14px 20px 18px; border-top: 1px solid #f5f5f5; }
.crs-price { font-size: 1.15rem; font-weight: 800; color: var(--primary); }
.crs-price.free { color: #10b981; }
.crs-enroll-btn { display: inline-flex; align-items: center; gap: 6px; background: var(--primary); color: #fff; padding: 8px 18px; border-radius: 50px; font-size: .78rem; font-weight: 700; border: none; cursor: pointer; transition: all .22s; font-family: 'Poppins', Helvetica, sans-serif; pointer-events: none; }
.crs-card:hover .crs-enroll-btn { background: #035b58; color: #fff; box-shadow: 0 4px 14px rgba(2,68,66,.25); transform: translateY(-1px); }
.crs-enroll-btn.free-btn { background: var(--accent); color: var(--primary); }
.crs-card:hover .crs-enroll-btn.free-btn { background: #a8e835; }

/* Selected Bar Banner */
.creg-selected-banner { display: none; background: #fff; border: 2px solid var(--primary); border-radius: 16px; padding: 20px 24px; margin-bottom: 24px; align-items: center; justify-content: space-between; box-shadow: 0 12px 32px rgba(2,68,66,.08); }
.creg-sb-info h3 { font-size: 1.2rem; font-weight: 800; color: #1a1a1a; margin: 0 0 4px; }
.creg-sb-info p { margin: 0; font-size: .85rem; color: #666; }
.creg-change-btn { background: #f0f0f0; color: #555; border: none; padding: 8px 16px; border-radius: 50px; font-size: .75rem; font-weight: 700; cursor: pointer; }
.creg-change-btn:hover { background: #e0e0e0; }

/* Step 2: Course Details */
.creg-details-card { background: #fff; border-radius: 20px; padding: 40px; box-shadow: 0 4px 24px rgba(0,0,0,.04); border: 1px solid #ebebeb; max-width: 800px; margin: 0 auto; }
.creg-d-title { font-size: 2rem; font-weight: 800; color: var(--primary); margin-bottom: 10px; }
.creg-d-desc-wrap { position: relative; margin-bottom: 30px; }
.creg-d-desc { max-height: 150px; overflow: hidden; position: relative; transition: max-height 0.5s ease; font-size: .95rem; color: #555; line-height: 1.8; }
.creg-d-desc::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 60px; background: linear-gradient(transparent, #fff); transition: opacity 0.3s; }
.creg-d-desc.expanded { max-height: 3000px; }
.creg-d-desc.expanded::after { opacity: 0; pointer-events: none; }
.creg-d-desc.no-fade::after { display: none; }
.read-more-btn { background: none; border: none; color: var(--primary); font-weight: 700; cursor: pointer; padding: 0; margin-top: 8px; font-size: .9rem; display: inline-flex; align-items: center; gap: 4px; }
.read-more-btn:hover { text-decoration: underline; }

.creg-inst-box { display: flex; align-items: center; gap: 16px; background: #f9fbfc; padding: 16px; border-radius: 12px; border: 1px dashed #d0d0d0; }
.creg-inst-box img { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; }

/* Step 3 & 4: Forms */
.creg-form-card { background: #fff; border-radius: 20px; padding: 40px; box-shadow: 0 4px 24px rgba(0,0,0,.04); border: 1px solid #ebebeb; max-width: 800px; margin: 0 auto; }
.creg-form-section { font-size: .9rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--primary); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid rgba(184,243,90,.35); display: flex; align-items: center; gap: 10px; }
.creg-form-section i { color: var(--accent); background: var(--primary); width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.75rem; }
.creg-label { display: block; font-size: .85rem; font-weight: 600; color: #333; margin-bottom: 8px; }
.creg-input { width: 100%; padding: 12px 16px; border: 1.5px solid #e0e0e0; border-radius: 10px; font-size: .9rem; background: #f9fbfc; outline: none; transition: all .2s; }
.creg-input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(2,68,66,.06); }
.creg-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23aaa' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 16px center; }
.creg-input.is-error { border-color: #ef4444 !important; background: #fef2f2 !important; box-shadow: 0 0 0 3px rgba(239,68,68,.1) !important; }
.upload-error { border-color: #ef4444 !important; background: rgba(239, 68, 68, 0.05) !important; }

/* Responsive Action Buttons */
.step-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; gap: 16px; }
.creg-btn-next { background: var(--primary); color: #fff; border: none; padding: 14px 32px; border-radius: 50px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all .2s; display: inline-flex; align-items: center; justify-content: center; gap: 10px; flex-shrink: 0; }
.creg-btn-next:hover { background: #035b58; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(2,68,66,.2); }
.creg-btn-back { background: transparent; color: #555; border: 1.5px solid transparent; padding: 14px 24px; font-size: .95rem; font-weight: 600; cursor: pointer; border-radius: 50px; transition: all .2s; display: inline-flex; align-items: center; gap: 8px; flex-shrink: 0; }
.creg-btn-back:hover { background: #f0f0f0; color: #111; }

/* Checkout Summary, Upload & Installments */
.creg-summary { background: #f7f9f5; border-radius: 12px; padding: 20px; border: 1px solid #e5e5e5; margin-bottom: 24px; }
.creg-sum-row { display: flex; justify-content: space-between; font-size: .9rem; color: #555; margin-bottom: 10px; }
.creg-sum-row.total { font-weight: 800; font-size: 1.2rem; color: var(--primary); border-top: 1px solid #ddd; padding-top: 12px; margin-top: 12px; margin-bottom: 0; }
.creg-upload-area { border: 2px dashed #d0d0d0; border-radius: 12px; padding: 32px; text-align: center; cursor: pointer; transition: all .2s; background: #fafafa; position: relative; }
.creg-upload-area.has-file { border-color: #10b981; background: rgba(16,185,129,.04); }
.creg-upload-area input { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }

.plan-option { flex: 1; border: 2px solid #e0e0e0; border-radius: 12px; padding: 15px; cursor: pointer; transition: 0.2s; text-align: center; display: block; }
.plan-option.active { border-color: var(--primary); background: rgba(2,68,66,0.03); }
.plan-title { font-size: 0.85rem; font-weight: 600; color: #555; }
.plan-price { font-size: 1.1rem; font-weight: 800; color: var(--primary); margin-top: 5px; }
.crs-wa-btn {
    display: inline-flex; align-items: center; justify-content: center;
    background: #25D366; color: #fff; width: 36px; height: 36px; border-radius: 50%;
    text-decoration: none; font-size: 1.2rem; transition: all 0.2s ease;
}
.crs-wa-btn:hover {
    transform: scale(1.1); box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4); color: #fff;
}
.crs-details-btn-block {
    background: #f9fbfc;
    color: var(--primary);
    text-align: center;
    padding: 12px;
    font-size: 0.85rem;
    font-weight: 700;
    border-top: 1px solid #ebebeb;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
/* 1. Apply the infinite shake loop to the button */
.crs-enroll-btn {
    animation: attention-shake 3s infinite;
    transform-origin: center; /* Keeps the shake perfectly centered */
}

/* 2. Stop the shake when they hover over the card so it's easy to click */
.crs-card:hover .crs-enroll-btn {
    animation: none; 
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(2, 68, 66, 0.25);
}

/* 3. The Delayed Shake Keyframes */
@keyframes attention-shake {
    /* 0% to 80% of the 3 seconds: Do absolutely nothing (The Pause) */
    0%, 80%, 100% {
        transform: rotate(0deg) scale(1);
    }
    /* 82% to 90%: The quick 0.3-second jiggle */
    82% {
        transform: rotate(-4deg) scale(1.05);
    }
    84% {
        transform: rotate(4deg) scale(1.05);
    }
    86% {
        transform: rotate(-4deg) scale(1.05);
    }
    88% {
        transform: rotate(4deg) scale(1.05);
    }
    90% {
        transform: rotate(0deg) scale(1.05);
    }
}
.crs-card:hover .crs-details-btn-block {
    background: var(--primary);
    color: #fff;
}
@media (max-width: 767px) { 
    .creg-steps { flex-wrap: wrap; gap: 10px; } 
    .creg-step-line { display: none; }
    .creg-details-card, .creg-form-card { padding: 24px 20px; }
    .step-actions { flex-direction: column-reverse; }
    .creg-btn-next, .creg-btn-back { width: 100%; padding: 14px; font-size: .95rem; }
    .creg-btn-back { border-color: #e0e0e0; }
}
</style>

<div class="creg">
    <section class="creg-hero">
        <div class="container">
            <h1>Course Enrollment</h1>
            <p>Select a course, review the details, and complete your registration in seconds.</p>
        </div>
    </section>

    <section class="creg-body">
        <div class="container">
            
            <div class="creg-steps">
                <div class="creg-step active" id="pill-1"><div class="creg-step-num">1</div> Courses</div>
                <div class="creg-step-line" id="line-1"></div>
                <div class="creg-step" id="pill-2"><div class="creg-step-num">2</div> Details</div>
                <div class="creg-step-line" id="line-2"></div>
                <div class="creg-step" id="pill-3"><div class="creg-step-num">3</div> Personal Info</div>
                <div class="creg-step-line" id="line-3"></div>
                <div class="creg-step" id="pill-4"><div class="creg-step-num">4</div> Checkout</div>
            </div>

            <div class="creg-selected-banner" id="selectedBanner">
                <div class="creg-sb-info">
                    <h3 id="bannerTitle">Course Title</h3>
                    <p id="bannerPrice">Rs. 0</p>
                </div>
                <button class="creg-change-btn" onclick="goToStep(1)">Change Course</button>
            </div>

            <div id="toastContainer" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;"></div>

            <form id="regForm" action="courses.php" method="POST" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="course_id" id="regCourseId" value="<?= $preselect_id ?>">
                <input type="hidden" name="discount_pct" id="regDiscPct" value="0">
                <input type="hidden" name="applied_referral_code" id="appliedRefCode" value="">

                <div id="step1-area">
                    <div class="row g-4">
                        <?php foreach ($courses as $crs): 
                            $basePrice = floatval($crs['price']);
                            
                            $dp = intval($crs['discount_percent'] ?? 0);
                            $raw_end = $crs['discount_end_date'] ?? '';
                            
                            if ($dp > 0 && !empty($raw_end) && strpos($raw_end, '0000') === false) {
                                $exp_time = strtotime($raw_end . ' 23:59:59');
                                if ($exp_time > 0 && time() > $exp_time) {
                                    $dp = 0; 
                                }
                            }
                            
                            $inst_en = !empty($crs['installments_enabled']) ? 1 : 0;
                            $inst_c = intval($crs['installment_count'] ?? 1);
                            $student_disc = intval($crs['student_discount'] ?? 0);
                            $safe_title_js = htmlspecialchars(json_encode($crs['title']), ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="col-lg-3 col-md-6">
                            <div class="crs-card" onclick='selectCourse(<?= intval($crs['id']) ?>, <?= $safe_title_js ?>, <?= $basePrice ?>, <?= intval($crs['is_free']) ?>, <?= $dp ?>, <?= $inst_en ?>, <?= $inst_c ?>, <?= $student_disc ?>)'>
    <div class="crs-card-thumb">
        <?php if (!empty($crs['thumbnail'])): ?>
        <img src="<?= htmlspecialchars($crs['thumbnail']) ?>" alt="<?= htmlspecialchars($crs['title']) ?>" loading="lazy">
        <?php else: ?>
        <div class="crs-thumb-fallback">🎓</div>
        <?php endif; ?>
        
        <?php if ($crs['is_featured']): ?>
            <div class="crs-badge-left crs-thumb-badge">⭐ Featured</div>
        <?php endif; ?>
        
        <?php if ($crs['is_free']): ?>
            <div class="crs-badge-right crs-thumb-badge" style="background: #10b981; color: #fff;">Free</div>
        <?php elseif ($dp > 0): ?>
            <div class="crs-badge-right crs-thumb-badge" style="background: #ef4444; color: #fff;">-<?= $dp ?>% OFF</div>
        <?php endif; ?>

        <div class="crs-play-overlay"><div class="crs-play-btn"><i class="fas fa-play"></i></div></div>
    </div>

    <div class="crs-card-body">
        <div class="crs-card-cat"><?= htmlspecialchars($crs['category']) ?></div>
        <div class="crs-card-title"><?= htmlspecialchars($crs['title']) ?></div>
        <div class="crs-card-tagline"><?= htmlspecialchars($crs['tagline']) ?></div>
        <div class="crs-card-instructor">by <span><?= htmlspecialchars($crs['instructor_name'] ?? 'Graphicafix') ?></span></div>

        <?php if (!$crs['is_free']): ?>
        <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:8px; margin-bottom:10px;">
            <span style="font-size:.65rem; background:#fef2f2; color:#dc2626; padding:3px 10px; border-radius:20px; font-weight:700;"><i class="fas fa-gift" style="margin-right:3px;"></i> 7-Day Free Trial</span>
            <?php if (!empty($crs['student_discount']) && $crs['student_discount'] > 0): ?>
            <span style="font-size:.65rem; background:#f0fdf4; color:#16a34a; padding:3px 10px; border-radius:20px; font-weight:700;"><i class="fas fa-user-graduate" style="margin-right:3px;"></i> <?= intval($crs['student_discount']) ?>% Student Discount</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="crs-card-meta" style="margin-bottom:10px;">
            <?php if ($inst_en && $inst_c > 1 && !$crs['is_free']): ?>
                <span style="font-size:.65rem; background:#e0f2fe; color:#0284c7; padding:3px 10px; border-radius:20px; font-weight:700;">Installments</span>
            <?php endif; ?>
            <span class="crs-students">(<?= number_format($crs['total_students']) ?> students)</span>
            <span class="crs-level-badge"><?= $crs['level'] ?></span>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div class="crs-duration"><i class="fas fa-clock" style="font-size:.65rem;"></i> <?= fmtDur($crs['duration_mins']) ?></div>
            <?php if (!empty($crs['start_date']) && strpos($crs['start_date'], '0000') === false): 
                $sd = strtotime($crs['start_date']); $today = strtotime(date('Y-m-d'));
            ?>
                <div style="font-size:.65rem; font-weight:600;">
                    <?php if ($sd > $today): ?>
                        <span style="color:#0284c7;">📅 Starts <?= date('M j', $sd) ?></span>
                    <?php else: ?>
                        <span style="color:#10b981;">● Enrolling Now</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="crs-card-footer">
        <div>
            <?php if ($crs['is_free']): ?>
                <div class="crs-price free">Free</div>
            <?php else: ?>
                <?php if ($dp > 0): ?>
                    <div style="display:flex; align-items:baseline; gap:6px;">
                        <span class="crs-price">Rs. <?= number_format($basePrice - ($basePrice * $dp / 100)) ?></span>
                        <span style="text-decoration:line-through; color:#aaa; font-size:.8rem;">Rs. <?= number_format($basePrice) ?></span>
                    </div>
                <?php else: ?>
                    <div class="crs-price">Rs. <?= number_format($basePrice) ?></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div style="display:flex; align-items:center; gap:8px;">
            <?php 
                $wa_text = urlencode("Hello! I am interested in joining the course: *" . $crs['title'] . "*. Can you provide more details?");
            ?>
            <a href="https://wa.me/923454568986?text=<?= $wa_text ?>" target="_blank" onclick="event.stopPropagation();" class="crs-wa-btn" title="Ask on WhatsApp">
                <i class="fab fa-whatsapp"></i>
            </a>
            
            <button type="button" class="crs-enroll-btn <?= $crs['is_free'] ? 'free-btn' : '' ?>">
                <?= $crs['is_free'] ? 'Enroll Free' : 'Enroll Now' ?>
            </button>
        </div>
    </div>
    
    <div class="crs-details-btn-block">
        Course Details <i class="fas fa-arrow-right" style="font-size: 10px;"></i>
    </div>
    
</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="step2-area" style="display:none;">
                    <?php foreach ($courses as $c): ?>
                    <div class="creg-details-card detail-block" id="detail-box-<?= $c['id'] ?>" style="display:none;">
                        <div class="creg-card-cat" style="margin-bottom:8px; display:inline-block;"><?= htmlspecialchars($c['category']) ?></div>
                        <h2 class="creg-d-title"><?= htmlspecialchars($c['title']) ?></h2>
                        <div class="creg-d-desc-wrap">
                            <div class="creg-d-desc" id="desc-text-<?= $c['id'] ?>"><?= !empty($c['description']) ? nl2br(htmlspecialchars_decode($c['description'])) : 'No detailed description available.' ?></div>
                            <button type="button" class="read-more-btn" id="read-btn-<?= $c['id'] ?>" onclick="toggleReadMore(<?= $c['id'] ?>)">Read More <i class="fas fa-chevron-down" style="font-size:10px;"></i></button>
                        </div>
                        <div class="creg-inst-box">
                            <?php if(!empty($c['instructor_avatar'])): ?>
                                <img src="<?= $c['instructor_avatar'] ?>" alt="Instructor">
                            <?php else: ?>
                                <div style="width:50px;height:50px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:700;">G</div>
                            <?php endif; ?>
                            <div>
                                <div style="font-weight:700; color:var(--primary);"><?= htmlspecialchars($c['instructor_name'] ?? 'Graphicafix Expert') ?></div>
                                <div style="font-size:.8rem; color:#666;">Course Instructor</div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="step-actions">
                        <button type="button" class="creg-btn-back" onclick="goToStep(1)"><i class="fas fa-arrow-left"></i> Back to Courses</button>
                        <button type="button" class="creg-btn-next" onclick="goToStep(3)">Enroll in this Course <i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>

                <div id="step3-area" style="display:none;">
                    <div class="creg-form-card">
                        <div class="creg-form-section"><i class="fas fa-user"></i> Student Information</div>
                        <div class="row g-4">
                            <div class="col-md-6"><label class="creg-label">Full Name <span style="color:#ef4444;">*</span></label><input type="text" name="student_name" class="creg-input" required></div>
                            <div class="col-md-6"><label class="creg-label">Father/Husband Name</label><input type="text" name="father_name" class="creg-input"></div>
                            <div class="col-md-6"><label class="creg-label">Email <span style="color:#ef4444;">*</span></label><input type="email" name="student_email" class="creg-input" required></div>
                            <div class="col-md-6"><label class="creg-label">Phone <span style="color:#ef4444;">*</span></label><input type="tel" name="student_phone" class="creg-input" required></div>
                            <div class="col-md-12"><label class="creg-label">Address</label><input type="text" name="address" class="creg-input"></div>
                            <div class="col-md-6"><label class="creg-label">Date of Birth</label><input type="date" name="dob" class="creg-input"></div>
                            <div class="col-md-6"><label class="creg-label">Gender</label>
                                <select name="gender" class="creg-input creg-select">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6"><label class="creg-label">Student Picture</label><input type="file" name="student_picture" class="creg-input" accept="image/*"></div>
                            <div class="col-md-6"><label class="creg-label">Latest Completed Degree / Education Level</label><input type="text" name="education_level" class="creg-input"></div>
                            <div class="col-md-12">
                                <label class="creg-label">Current Occupation <span style="color:#ef4444;">*</span></label>
                                <select name="student_occupation" class="creg-input creg-select" id="occSelect" onchange="applyDiscount()" required>
                                    <option value="">Select your status</option>
                                    <option value="student">Student</option>
                                    <option value="job">Job / Employed </option>
                                    <option value="other">Other</option>
                                </select>
                                <div id="occHint" style="font-size:.75rem; color:#10b981; margin-top:6px; font-weight:600;"></div>
                            </div>
                            <div class="col-md-12" id="studentCardWrap" style="display:none; background:#f0f9f0; border:1px dashed #c3e6cb; padding:16px; border-radius:10px; margin-top:10px;">
                                <label class="creg-label" style="color:#2b7a2b;">Upload CNIC / Form B / Student Card <span style="color:#ef4444;">*</span></label>
                                <input type="file" name="student_card" id="studentCardFile" class="creg-input" accept="image/*,application/pdf" style="background:#fff;">
                                <div style="font-size:.75rem; color:#555; margin-top:6px;">Upload CNIC, Form B, or Student ID to verify your status for the discount. (Max 5MB)</div>
                            </div>
                        </div>
                        <div class="step-actions">
                            <button type="button" class="creg-btn-back" onclick="goToStep(2)"><i class="fas fa-arrow-left"></i> Back to Details</button>
                            <button type="button" class="creg-btn-next" onclick="goToStep(4)">Proceed to Checkout <i class="fas fa-arrow-right"></i></button>
                        </div>
                    </div>
                </div>

                <div id="step4-area" style="display:none;">
                    <div class="creg-form-card">
                        
                        <div id="paymentPlanBlock" style="display:none; margin-bottom: 30px;">
                            <div class="creg-form-section"><i class="fas fa-calendar-alt"></i> Payment Plan</div>
                            <div style="display:flex; gap:15px; flex-wrap:wrap;">
                                <label class="plan-option active" id="planFullLabel">
                                    <input type="radio" name="payment_type" value="full" checked onchange="updateCheckoutUI()" style="display:none;">
                                    <div class="plan-title">Pay in Full</div>
                                    <div class="plan-price" id="planFullPrice">Rs. 0</div>
                                </label>
                                <label class="plan-option" id="planInstLabel">
                                    <input type="radio" name="payment_type" value="installment" onchange="updateCheckoutUI()" style="display:none;">
                                    <div class="plan-title">Installments (<span id="instCountText"></span>)</div>
                                    <div class="plan-price" id="planInstPrice">Rs. 0 <small style="font-size:12px;color:#888;">/mo</small></div>
                                </label>
                            </div>
                        </div>

                        <div class="creg-form-section"><i class="fas fa-file-invoice-dollar"></i> Order Summary</div>
                        
                        <div class="creg-summary">
                            <div class="creg-sum-row"><span>Course Base Fee</span><span id="sumFee">Rs. 0</span></div>
                            <div class="creg-sum-row" id="sumDiscRow" style="display:none; color:#10b981; font-weight:600;"><span>Discount Applied</span><span id="sumDisc">- Rs. 0</span></div>
                            
                            <div style="margin-top:15px; padding-top:15px; border-top:1px solid #e5e5e5;" id="referralContainer">
                                <label style="display:block; font-size:12px; font-weight:700; color:#555; margin-bottom:6px;">Have a Referral Code?</label>
                                <div style="display:flex; gap:10px;">
                                    <input type="text" id="refCodeInput" placeholder="Enter code for free enrollment" class="creg-input" style="flex:1; padding:8px 12px; font-family:monospace;">
                                    <button type="button" class="btn btn-secondary-custom" onclick="applyReferral()" style="padding:8px 16px;">Apply</button>
                                </div>
                                <div id="refMessage" style="font-size:12px; margin-top:6px; font-weight:600;"></div>
                            </div>
                            
                            <div class="creg-sum-row total"><span>Grand Total</span><span id="sumTotal">Rs. 0</span></div>
                            
                            <div class="creg-sum-row" id="sumDueRow" style="display:none; font-weight:800; color:#ef4444; border-top:1px dashed #ddd; padding-top:12px; margin-top:12px;">
                                <span>Amount to Pay Now (1st Installment)</span>
                                <span id="sumDueAmt">Rs. 0</span>
                            </div>
                        </div>

                        <div id="paymentBlock">
                            <div class="creg-form-section"><i class="fas fa-wallet"></i> Select Payment Method</div>
                            
                            <div style="display:flex; gap:15px; flex-wrap:wrap; margin-bottom:25px;">
                                <label class="plan-option active" id="methodBankLabel" style="border-color:#10b981; background:#f0fdf4;">
                                    <input type="radio" name="payment_method" value="bank_transfer" checked onchange="togglePaymentMethod()" style="display:none;">
                                    <div style="font-size:1.1rem; color:#10b981; margin-bottom:4px;"><i class="fas fa-mobile-alt"></i> / <i class="fas fa-university"></i></div>
                                    <div class="plan-title" style="color:#166534; font-weight:700;">EasyPaisa / JazzCash / Bank Transfer</div>
                                    <div style="font-size:12px; color:#15803d; margin-top:3px;">Manual transfer (Default)</div>
                                </label>
                                <label class="plan-option" id="methodCardLabel">
                                    <input type="radio" name="payment_method" value="online_card" onchange="togglePaymentMethod()" style="display:none;">
                                    <div style="font-size:1.1rem; color:#024442; margin-bottom:4px;"><i class="fas fa-credit-card"></i></div>
                                    <div class="plan-title" style="color:#024442; font-weight:700;">Pay Online via Credit / Debit Card</div>
                                    <div style="font-size:12px; color:#666; margin-top:3px;">Safepay / Stripe Gateway</div>
                                </label>
                            </div>

                            <div id="bankDetailsPanel">
                                <div style="background:#f0f9f0; border:1px solid #c3e6cb; border-radius:12px; padding:20px; margin-bottom:20px;">
                                    <p style="font-size:.85rem; color:#2b7a2b; margin-bottom:12px;" id="transferInstructText">Please transfer the total amount via EasyPaisa, JazzCash, or Bank Transfer to the account below, then upload the screenshot.</p>
                                    <div style="font-weight:700; color:#2b7a2b; margin-bottom:8px;">Meezan Bank Limited</div>
                                    <div style="color:#333; line-height:1.6; font-size:.9rem;">
                                        Account Number: <strong style="font-size:1.1rem;">PK09MEZN0008100112990298</strong><br>
                                        Account Title: <strong>Graphica Fix</strong>
                                    </div>
                                </div>

                                <label class="creg-label">Upload Payment Screenshot <span style="color:#ef4444;">*</span></label>
                                <div class="creg-upload-area" id="uploadArea">
                                    <input type="file" name="payment_proof" id="paymentFile" accept="image/*,application/pdf" onchange="onFileChange(this)">
                                    <div style="font-size: 2rem; margin-bottom: 10px;" id="uploadIcon">📄</div>
                                    <div style="font-weight: 600; color: #333;" id="uploadTitle">Click to upload receipt</div>
                                    <div style="font-size: .8rem; color: #aaa;" id="uploadSub">Max 5MB (JPG, PNG, PDF)</div>
                                </div>
                            </div>
                        </div>

                        <div class="step-actions">
                            <button type="button" class="creg-btn-back" onclick="goToStep(3)"><i class="fas fa-arrow-left"></i> Back to Personal Info</button>
                            <button type="submit" class="creg-btn-next" id="finalSubmitBtn"><i class="fas fa-check-circle"></i> Complete Enrollment</button>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </section>
</div>

<script>
let _cid = null; let _price = 0; let _isFree = false; let _baseDiscPct = 0; let _studentDiscPct = 0;
let _finalDiscPct = 0; let _instEnabled = false; let _instCount = 1;
let _isReferralFree = false; // Tracks if a valid referral code was applied

function showToast(msg, type='success') {
    const t = document.createElement('div');
    t.style.cssText = `background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:220px;border-left:4px solid ${type==='success'?'#10b981':'#ef4444'};z-index:9999;`;
    t.innerHTML = `<span style="font-weight:700;color:${type==='success'?'#10b981':'#ef4444'}">${type==='success'?'✓':'✕'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3500);
}
function applyReferral() {
    const code = document.getElementById('refCodeInput').value.trim();
    if(!code) return;
    
    // Show a loading state
    document.getElementById('refMessage').innerHTML = '<span style="color:#888;">Verifying...</span>';
    
    fetch('courses.php?verify_code=' + encodeURIComponent(code))
        .then(async (r) => {
            // Read the raw text first before forcing it to JSON
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                // If it fails to parse, it means PHP spit out a visible error. Log it to the console!
                console.error("🚨 PHP ERROR OUTPUT:", text);
                throw new Error("PHP returned invalid JSON. Check console.");
            }
        })
        .then(data => {
            if(data.valid) {
                _isReferralFree = true;
                document.getElementById('appliedRefCode').value = code;
                document.getElementById('refMessage').innerHTML = '<span style="color:#10b981;">✅ Valid code applied! Course is 100% Free.</span>';
                updateCheckout(); // Recalculate totals
            } else {
                _isReferralFree = false;
                document.getElementById('appliedRefCode').value = '';
                document.getElementById('refMessage').innerHTML = '<span style="color:#ef4444;">❌ Invalid or already used code.</span>';
                updateCheckout();
            }
        }).catch(err => {
            document.getElementById('refMessage').innerHTML = '<span style="color:#ef4444;">❌ Server error. Check browser console (F12) for details.</span>';
        });
}

function toggleReadMore(id) {
    const desc = document.getElementById('desc-text-' + id);
    const btn = document.getElementById('read-btn-' + id);
    if (desc.classList.contains('expanded')) { desc.classList.remove('expanded'); btn.innerHTML = 'Read More <i class="fas fa-chevron-down" style="font-size:10px;"></i>'; } 
    else { desc.classList.add('expanded'); btn.innerHTML = 'Read Less <i class="fas fa-chevron-up" style="font-size:10px;"></i>'; }
}

function selectCourse(id, name, price, isFree, defaultDisc, instEn, instC, studentDisc) {
    _cid = id; _price = parseFloat(price) || 0; _isFree = (parseInt(isFree) === 1);
    _baseDiscPct = _isFree ? 0 : (parseInt(defaultDisc) || 0); _studentDiscPct = _isFree ? 0 : (parseInt(studentDisc) || 0); _instEnabled = (parseInt(instEn) === 1); _instCount = Math.max(1, parseInt(instC) || 1);
    
    document.getElementById('regCourseId').value = id;
    document.getElementById('bannerTitle').textContent = name;
    
    if (_isFree) { document.getElementById('bannerPrice').textContent = 'Free Course'; } 
    else if (_baseDiscPct > 0) {
        const discounted = _price - (_price * _baseDiscPct / 100);
        document.getElementById('bannerPrice').innerHTML = `Rs. ${discounted.toLocaleString()} <small style="text-decoration:line-through;color:#aaa;">Rs. ${_price.toLocaleString()}</small>`;
    } else { document.getElementById('bannerPrice').textContent = 'Rs. ' + _price.toLocaleString(); }
    
    document.querySelectorAll('.detail-block').forEach(b => b.style.display = 'none');
    document.getElementById('detail-box-' + id).style.display = 'block';
    
    setTimeout(() => {
        const descBox = document.getElementById('desc-text-' + id);
        if (descBox && descBox.scrollHeight <= 110) {
            const btn = document.getElementById('read-btn-' + id);
            if (btn) btn.style.display = 'none'; descBox.classList.add('no-fade');
        }
    }, 50);

    applyDiscount(); 
    goToStep(2);
}

function applyDiscount() {
    const occ = document.getElementById('occSelect').value;
    const hint = document.getElementById('occHint');
    const studentCardWrap = document.getElementById('studentCardWrap');
    const studentCardFile = document.getElementById('studentCardFile');
    let additionalDisc = 0;
    
    if (_isFree) {
        studentCardWrap.style.display = 'none';
        studentCardFile.required = false;
        studentCardFile.value = '';
        hint.textContent = '';
        _finalDiscPct = 0;
        document.getElementById('regDiscPct').value = 0;
        updateCheckout();
        return;
    }
    
    // Set the EXTRA student discount amount
    if (occ === 'student') { 
        additionalDisc = _studentDiscPct; 
        if (!_isFree && _studentDiscPct > 0) {
            studentCardWrap.style.display = 'block'; 
            studentCardFile.required = true; 
        } else {
            studentCardWrap.style.display = 'none'; 
            studentCardFile.required = false; 
            studentCardFile.value = ''; 
        }
    } 
    else if (occ === 'job') { 
        additionalDisc = 0; 
        studentCardWrap.style.display = 'none'; 
        studentCardFile.required = false; 
        studentCardFile.value = ''; 
    } 
    else { 
        additionalDisc = 0; 
        studentCardWrap.style.display = 'none'; 
        studentCardFile.required = false; 
        studentCardFile.value = ''; 
    }
    
    // Stack the discounts: Base Discount + Additional Occupation Discount
    _finalDiscPct = _baseDiscPct + additionalDisc;
    
    // Cap the total discount at 100% just to be safe
    if (_finalDiscPct > 100) {
        _finalDiscPct = 100;
    }
    
    // Update the hint text dynamically
    if (additionalDisc > 0 && _baseDiscPct > 0) {
        hint.textContent = `Standard ${_baseDiscPct}% + Extra ${additionalDisc}% Occupation Discount = ${_finalDiscPct}% Total!`;
    } else if (additionalDisc > 0) {
        hint.textContent = `Special ${additionalDisc}% Occupation Discount Applied!`;
    } else if (_baseDiscPct > 0) {
        hint.textContent = `Standard ${_baseDiscPct}% Course Discount Applied.`;
    } else {
        hint.textContent = "";
    }
    
    document.getElementById('regDiscPct').value = _finalDiscPct;
    updateCheckout();
}

function updateCheckout() {
    const discAmount = Math.round(_price * _finalDiscPct / 100);
    let total = Math.max(0, _price - discAmount);
    
    // Referral Override
    if (_isReferralFree) { total = 0; }
    
    document.getElementById('sumFee').textContent = _isFree ? 'Free' : 'Rs. ' + _price.toLocaleString();
    document.getElementById('sumTotal').textContent = _isFree ? 'Rs. 0' : 'Rs. ' + total.toLocaleString();
    
    const discRow = document.getElementById('sumDiscRow');
    if (_finalDiscPct > 0 && !_isFree && !_isReferralFree) {
        discRow.style.display = 'flex';
        document.getElementById('sumDisc').textContent = '- Rs. ' + discAmount.toLocaleString() + ' (' + _finalDiscPct + '%)';
    } else { discRow.style.display = 'none'; }

    const planBlock = document.getElementById('paymentPlanBlock');
    if (_instEnabled && total > 0 && !_isFree && !_isReferralFree && _instCount > 1) {
        planBlock.style.display = 'block';
        document.getElementById('planFullPrice').textContent = 'Rs. ' + total.toLocaleString();
        document.getElementById('instCountText').textContent = _instCount + ' Months';
        let instAmount = Math.ceil(total / _instCount);
        document.getElementById('planInstPrice').innerHTML = 'Rs. ' + instAmount.toLocaleString() + ' <small style="font-size:12px;color:#888;">/mo</small>';
    } else {
        planBlock.style.display = 'none';
        document.querySelector('input[name="payment_type"][value="full"]').checked = true;
    }

    // Hide Referral box if already free
    if (_isFree) { document.getElementById('referralContainer').style.display = 'none'; } 
    else { document.getElementById('referralContainer').style.display = 'block'; }

    updateCheckoutUI();
}

function updateCheckoutUI() {
    const planRadios = document.querySelectorAll('input[name="payment_type"]');
    let selectedPlan = 'full';
    
    planRadios.forEach(r => {
        if(r.checked) { selectedPlan = r.value; r.closest('.plan-option').classList.add('active'); } 
        else { r.closest('.plan-option').classList.remove('active'); }
    });

    let total = Math.max(0, _price - Math.round(_price * _finalDiscPct / 100));
    if (_isReferralFree) total = 0; // Referral bypass
    
    const payBlock = document.getElementById('paymentBlock');
    const payDueRow = document.getElementById('sumDueRow');
    const instructText = document.getElementById('transferInstructText');
    const payInput = document.getElementById('paymentFile');

    if (_isFree || total === 0 || _isReferralFree) {
        payBlock.style.display = 'none';
        payInput.required = false;
        document.getElementById('finalSubmitBtn').innerHTML = '<i class="fas fa-graduation-cap"></i> Enroll for Free';
        if (payDueRow) payDueRow.style.display = 'none';
    } else {
        payBlock.style.display = 'block';

        let amountToPay = total;
        if (selectedPlan === 'installment') {
            amountToPay = Math.ceil(total / _instCount);
            if (payDueRow) { payDueRow.style.display = 'flex'; document.getElementById('sumDueAmt').textContent = 'Rs. ' + amountToPay.toLocaleString(); }
        } else { if (payDueRow) payDueRow.style.display = 'none'; }
        
        instructText.innerHTML = `Please transfer <strong>Rs. ${amountToPay.toLocaleString()}</strong> to the account below, then upload the receipt.`;
        if (typeof togglePaymentMethod === 'function') { togglePaymentMethod(); }
    }
}

function togglePaymentMethod() {
    const method = document.querySelector('input[name="payment_method"]:checked')?.value || 'bank_transfer';
    const cardLbl = document.getElementById('methodCardLabel');
    const bankLbl = document.getElementById('methodBankLabel');
    const bankPanel = document.getElementById('bankDetailsPanel');
    const tidInput = document.getElementById('paymentFile');
    const submitBtn = document.getElementById('finalSubmitBtn');
    
    if (method === 'bank_transfer') {
        if (cardLbl) { cardLbl.style.borderColor = '#cbd5e1'; cardLbl.style.background = '#fff'; }
        if (bankLbl) { bankLbl.style.borderColor = '#10b981'; bankLbl.style.background = '#f0fdf4'; }
        if (bankPanel) bankPanel.style.display = 'block';
        if (tidInput) tidInput.required = true;
        if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Complete Enrollment';
    } else {
        if (cardLbl) { cardLbl.style.borderColor = '#024442'; cardLbl.style.background = '#f8fafc'; }
        if (bankLbl) { bankLbl.style.borderColor = '#cbd5e1'; bankLbl.style.background = '#fff'; }
        if (bankPanel) bankPanel.style.display = 'none';
        if (tidInput) tidInput.required = false;
        if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-lock"></i> Proceed to Secure Card Checkout';
    }
}

function validateStep3() {
    let isValid = true; let firstError = "Please fill all required fields.";
    const name = document.querySelector('input[name="student_name"]');
    const email = document.querySelector('input[name="student_email"]');
    const phone = document.querySelector('input[name="student_phone"]');
    const occ = document.querySelector('select[name="student_occupation"]');
    const card = document.querySelector('input[name="student_card"]');

    [name, email, phone, occ, card].forEach(el => el.classList.remove('is-error'));

    if (!name.value.trim()) { name.classList.add('is-error'); isValid = false; }
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email.value.trim() || !emailRegex.test(email.value)) { email.classList.add('is-error'); isValid = false; if (email.value.trim()) firstError = "Please enter a valid email address."; }
    if (!phone.value.trim()) { phone.classList.add('is-error'); isValid = false; }
    if (!occ.value) { occ.classList.add('is-error'); isValid = false; }
    if (occ.value === 'student' && !_isFree && _studentDiscPct > 0 && card.files.length === 0) { card.classList.add('is-error'); isValid = false; firstError = "Please upload your CNIC / Form B / Student Card to claim the student discount."; }

    if (!isValid) showToast(firstError, "error");
    return isValid;
}

document.getElementById('regForm').addEventListener('submit', function(e) {
    const payBlock = document.getElementById('paymentBlock');
    const payInput = document.getElementById('paymentFile');
    const uploadArea = document.getElementById('uploadArea');
    const method = document.querySelector('input[name="payment_method"]:checked')?.value || 'bank_transfer';
    if (payBlock.style.display !== 'none' && method === 'bank_transfer' && payInput.files.length === 0) {
        e.preventDefault(); uploadArea.classList.add('upload-error'); showToast("Please upload your payment receipt.", "error");
    }
});

function goToStep(step) {
    if (step === 2 && _cid === null) { showToast("Please select a course.", "error"); return; }
    if (step === 4) { if (!validateStep3()) return; }
    [1,2,3,4].forEach(i => document.getElementById('step'+i+'-area').style.display = (i === step) ? 'block' : 'none');
    document.getElementById('selectedBanner').style.display = step > 1 ? 'flex' : 'none';
    [1,2,3,4].forEach(i => {
        const pill = document.getElementById('pill-' + i); const line = document.getElementById('line-' + i);
        pill.classList.remove('active', 'done');
        if (i < step) pill.classList.add('done');
        if (i === step) pill.classList.add('active');
        if (line) { line.classList.remove('done'); if (i < step) line.classList.add('done'); }
    });
    if (step === 4) { togglePaymentMethod(); }
    window.scrollTo({ top: document.querySelector('.creg-steps').offsetTop - 20, behavior: 'smooth' });
}

function onFileChange(input) {
    const area = document.getElementById('uploadArea');
    const icon = document.getElementById('uploadIcon');
    const title = document.getElementById('uploadTitle');
    if (input.files && input.files[0]) {
        area.classList.remove('upload-error'); area.classList.add('has-file'); icon.textContent = '✅'; title.textContent = input.files[0].name;
    }
}

<?php if ($preselect): ?>
window.addEventListener('DOMContentLoaded', () => {
    <?php
        $bs = floatval($preselect['price']);
        $dp = intval($preselect['discount_percent'] ?? 0);
        $ed = $preselect['discount_end_date'] ?? null;
        if ($dp > 0 && !empty($ed) && strpos($ed, '0000') === false) {
            $exp_time = strtotime($ed . ' 23:59:59');
            if ($exp_time > 0 && time() > $exp_time) { $dp = 0; }
        }
        $ien = !empty($preselect['installments_enabled']) ? 1 : 0;
        $icnt = intval($preselect['installment_count'] ?? 1);
        $sdisc = intval($preselect['student_discount'] ?? 0);
        $tjs = htmlspecialchars(json_encode($preselect['title']), ENT_QUOTES, 'UTF-8');
    ?>
    selectCourse(<?= intval($preselect['id']) ?>, <?= $tjs ?>, <?= $bs ?>, <?= intval($preselect['is_free']) ?>, <?= $dp ?>, <?= $ien ?>, <?= $icnt ?>, <?= $sdisc ?>);
});
<?php endif; ?>
</script>

<?php include('footer.php'); ?>