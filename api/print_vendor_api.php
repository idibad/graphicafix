<?php
/**
 * api/print_vendor_api.php
 * REST API for Vendor Authentication, Rate Card Management,
 * Pricing Updates, Frequency Configuration, and One-Click Refresh.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/print_db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure tables exist & check expirations
if (isset($conn) && $conn instanceof mysqli) {
    init_print_database($conn);
    run_rates_expiration_check($conn);
} else {
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function send_json($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Vendor Authentication (Login via Phone / WhatsApp / Email + Password)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'vendor_login') {
    if ($method !== 'POST') {
        send_json(['success' => false, 'message' => 'POST method required']);
    }

    $login_input = trim($_POST['login_input'] ?? '');
    $password    = trim($_POST['password'] ?? '');

    if (empty($login_input) || empty($password)) {
        send_json([
            'success' => false, 
            'message' => 'Please enter your phone number / email and password.',
            'message_ur' => 'براہ کرم اپنا فون نمبر یا ای میل اور پاس ورڈ درج کریں۔'
        ]);
    }

    // Clean phone number for flexible matching (e.g. 03001234567, 923001234567, 0300-1234567)
    $clean_digits = preg_replace('/[^0-9]/', '', $login_input);
    $sub_digits = (strlen($clean_digits) >= 10) ? substr($clean_digits, -10) : $clean_digits;

    $stmt = $conn->prepare("SELECT * FROM print_vendors WHERE email = ? OR phone = ? OR whatsapp = ? OR phone LIKE ? OR whatsapp LIKE ?");
    $phone_like = "%{$sub_digits}%";
    $stmt->bind_param("sssss", $login_input, $login_input, $login_input, $phone_like, $phone_like);
    $stmt->execute();
    $res = $stmt->get_result();

    $found_vendor = null;
    while ($v = $res->fetch_assoc()) {
        if (!empty($v['password_hash']) && password_verify($password, $v['password_hash'])) {
            $found_vendor = $v;
            break;
        }
        // Fallback: If password matches default 'vendor123' and password_hash is empty
        if (empty($v['password_hash']) && $password === 'vendor123') {
            $def_hash = password_hash('vendor123', PASSWORD_DEFAULT);
            $conn->query("UPDATE print_vendors SET password_hash = '{$def_hash}' WHERE id = {$v['id']}");
            $found_vendor = $v;
            break;
        }
    }

    if (!$found_vendor) {
        send_json([
            'success' => false, 
            'message' => 'Invalid phone number or password. Please try again.',
            'message_ur' => 'فون نمبر یا پاس ورڈ درست نہیں ہے۔ براہ کرم دوبارہ کوشش کریں۔'
        ]);
    }

    if ($found_vendor['status'] !== 'active') {
        send_json([
            'success' => false, 
            'message' => 'This vendor account is inactive or suspended. Please contact Graphicafix.',
            'message_ur' => 'یہ وینڈر اکاؤنٹ غیر فعال ہے۔ برائے مہربانی ایڈمن سے رابطہ کریں۔'
        ]);
    }

    // Set vendor session
    $_SESSION['vendor_logged_in'] = true;
    $_SESSION['vendor_id']        = intval($found_vendor['id']);
    $_SESSION['vendor_name']      = $found_vendor['company_name'];
    $_SESSION['vendor_token']     = $found_vendor['access_token'];

    send_json([
        'success' => true,
        'message' => 'Login successful! Welcome back.',
        'message_ur' => 'خوش آمدید! آپ کامیابی سے لاگ ان ہو چکے ہیں۔',
        'vendor' => [
            'id' => $found_vendor['id'],
            'company_name' => $found_vendor['company_name'],
            'contact_person' => $found_vendor['contact_person']
        ],
        'redirect_url' => 'print_vendor_portal.php'
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Vendor Logout
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'vendor_logout') {
    unset($_SESSION['vendor_logged_in']);
    unset($_SESSION['vendor_id']);
    unset($_SESSION['vendor_name']);
    unset($_SESSION['vendor_token']);
    send_json(['success' => true, 'redirect_url' => 'vendor_login.php']);
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Get Vendor Info & Rates
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'get_vendor_data') {
    $token     = trim($_GET['token'] ?? '');
    $vendor_id = intval($_GET['vendor_id'] ?? 0);

    // If session exists
    if ($vendor_id <= 0 && empty($token) && !empty($_SESSION['vendor_id'])) {
        $vendor_id = intval($_SESSION['vendor_id']);
    }

    $vendor = null;
    if (!empty($token)) {
        $stmt = $conn->prepare("SELECT * FROM print_vendors WHERE access_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $vendor = $stmt->get_result()->fetch_assoc();
    } elseif ($vendor_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM print_vendors WHERE id = ?");
        $stmt->bind_param("i", $vendor_id);
        $stmt->execute();
        $vendor = $stmt->get_result()->fetch_assoc();
    }

    if (!$vendor) {
        send_json(['success' => false, 'message' => 'Vendor not found or session expired']);
    }

    $v_id = intval($vendor['id']);

    // Fetch vendor rates
    $sql = "SELECT pvr.*, pp.name AS product_name, pp.category AS product_category, pp.unit_label,
                   TIMESTAMPDIFF(SECOND, NOW(), pvr.expires_at) AS seconds_to_expiry
            FROM print_vendor_rates pvr
            JOIN print_products pp ON pp.id = pvr.product_id
            WHERE pvr.vendor_id = {$v_id}
            ORDER BY pp.name ASC, pvr.paper_stock ASC";
    $rates_res = $conn->query($sql);
    $rates = [];
    $active_count = 0;
    $expired_count = 0;
    $min_sec_left = PHP_INT_MAX;

    while ($r = $rates_res->fetch_assoc()) {
        $sec_left = intval($r['seconds_to_expiry']);
        if ($sec_left <= 0) {
            $dyn_status = 'expired';
            $expired_count++;
        } else {
            $dyn_status = 'active';
            $active_count++;
            if ($sec_left < $min_sec_left) {
                $min_sec_left = $sec_left;
            }
        }

        $r['dynamic_status'] = $dyn_status;
        $r['unit_price'] = floatval($r['unit_price']);
        $r['batch_price'] = floatval($r['batch_price']);
        $r['additional_per_thousand'] = floatval($r['additional_per_thousand'] ?? 0);
        $r['min_quantity'] = intval($r['min_quantity']);
        $r['turnaround_days'] = intval($r['turnaround_days']);
        $rates[] = $r;
    }

    $is_expired = ($active_count === 0 && count($rates) > 0) || ($min_sec_left <= 0);
    $hours_left = ($min_sec_left !== PHP_INT_MAX && $min_sec_left > 0) ? round($min_sec_left / 3600, 1) : 0;

    // Also fetch product catalog for "Add New Rate" modal
    $products = [];
    $p_res = $conn->query("SELECT * FROM print_products WHERE is_active = 1 ORDER BY name ASC");
    while ($p = $p_res->fetch_assoc()) {
        $products[] = [
            'id' => intval($p['id']),
            'name' => $p['name'],
            'category' => $p['category'],
            'allowed_stocks' => json_decode($p['allowed_stocks'], true) ?: [],
            'allowed_finishes' => json_decode($p['allowed_finishes'], true) ?: [],
            'standard_sizes' => json_decode($p['standard_sizes'], true) ?: [],
            'unit_label' => $p['unit_label']
        ];
    }

    send_json([
        'success' => true,
        'vendor' => [
            'id' => $v_id,
            'company_name' => $vendor['company_name'],
            'contact_person' => $vendor['contact_person'],
            'phone' => $vendor['phone'],
            'whatsapp' => $vendor['whatsapp'],
            'email' => $vendor['email'],
            'city' => $vendor['city'],
            'address' => $vendor['address'],
            'specialties' => $vendor['specialties'],
            'frequency_days' => intval($vendor['price_refresh_frequency_days']),
            'last_price_refresh_at' => $vendor['last_price_refresh_at'],
            'rating' => floatval($vendor['rating']),
            'access_token' => $vendor['access_token']
        ],
        'stats' => [
            'total_rates' => count($rates),
            'active_rates' => $active_count,
            'expired_rates' => $expired_count,
            'is_expired' => $is_expired,
            'hours_left' => $hours_left,
            'countdown_seconds' => max(0, ($min_sec_left === PHP_INT_MAX ? 0 : $min_sec_left))
        ],
        'rates' => $rates,
        'catalog' => $products
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. Update Rate Prices (Single or Bulk Edit by Vendor)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'update_rates_batch') {
    if ($method !== 'POST') {
        send_json(['success' => false, 'message' => 'POST method required']);
    }

    $vendor_id = intval($_POST['vendor_id'] ?? ($_SESSION['vendor_id'] ?? 0));
    $token     = trim($_POST['token'] ?? ($_SESSION['vendor_token'] ?? ''));

    if ($vendor_id <= 0 && !empty($token)) {
        $v_row = $conn->query("SELECT id, price_refresh_frequency_days FROM print_vendors WHERE access_token = '" . $conn->real_escape_string($token) . "'")->fetch_assoc();
        if ($v_row) $vendor_id = intval($v_row['id']);
    }

    if ($vendor_id <= 0) {
        send_json(['success' => false, 'message' => 'Unauthorized or vendor not identified']);
    }

    $v = $conn->query("SELECT price_refresh_frequency_days FROM print_vendors WHERE id = {$vendor_id}")->fetch_assoc();
    $freq_days = max(1, intval($v['price_refresh_frequency_days'] ?? 1));
    $now = date('Y-m-d H:i:s');
    $new_expires_at = date('Y-m-d H:i:s', time() + ($freq_days * 86400));

    // Handle updates array
    $updates_json = $_POST['rates'] ?? '';
    $updates = is_array($updates_json) ? $updates_json : json_decode($updates_json, true);

    $updated_count = 0;

    if (!empty($updates) && is_array($updates)) {
        $stmt_up = $conn->prepare("UPDATE print_vendor_rates SET 
            batch_price = ?, unit_price = ?, additional_per_thousand = ?, 
            turnaround_days = ?, min_quantity = ?, last_confirmed_at = ?, expires_at = ?, status = 'active'
            WHERE id = ? AND vendor_id = ?");

        foreach ($updates as $u) {
            $r_id   = intval($u['rate_id'] ?? 0);
            $batch  = floatval($u['batch_price'] ?? 0);
            $min_q  = max(1, intval($u['min_quantity'] ?? 1000));
            $unit   = ($batch > 0) ? ($batch / $min_q) : 0;
            $add_k  = floatval($u['additional_per_thousand'] ?? ($unit * 1000));
            $days   = max(1, intval($u['turnaround_days'] ?? 2));

            if ($r_id > 0 && $batch > 0) {
                $stmt_up->bind_param("dddiissii", $batch, $unit, $add_k, $days, $min_q, $now, $new_expires_at, $r_id, $vendor_id);
                $stmt_up->execute();
                $updated_count++;
            }
        }
    }

    // Update vendor refresh timestamp
    $conn->query("UPDATE print_vendors SET last_price_refresh_at = '{$now}' WHERE id = {$vendor_id}");

    // Log the event
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $log_stmt = $conn->prepare("INSERT INTO print_rate_refresh_logs (vendor_id, event_type, rates_affected_count, new_expires_at, triggered_by, ip_address) VALUES (?, 'bulk_updated', ?, ?, 'vendor_pricing_edit', ?)");
    $log_stmt->bind_param("iiss", $vendor_id, $updated_count, $new_expires_at, $ip);
    $log_stmt->execute();

    send_json([
        'success' => true,
        'message' => "Successfully updated {$updated_count} rate card(s)! Prices are active and extended for {$freq_days} days.",
        'message_ur' => "{$updated_count} ریٹس کامیابی سے اپ ڈیٹ ہو گئے۔ تمام قیمتیں اگلے {$freq_days} دن کے لیے ایکٹو ہیں۔",
        'rates_updated' => $updated_count,
        'new_expires_at' => $new_expires_at
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. Add New Rate Card Entry by Vendor
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'add_rate') {
    if ($method !== 'POST') {
        send_json(['success' => false, 'message' => 'POST method required']);
    }

    $vendor_id   = intval($_POST['vendor_id'] ?? ($_SESSION['vendor_id'] ?? 0));
    $product_id  = intval($_POST['product_id'] ?? 0);
    $paper_stock = trim($_POST['paper_stock'] ?? '');
    $finish_type = trim($_POST['finish_type'] ?? 'None');
    $size        = trim($_POST['size'] ?? 'Standard');
    $min_qty     = max(1, intval($_POST['min_quantity'] ?? 1000));
    $batch_price = floatval($_POST['batch_price'] ?? 0);
    $unit_price  = floatval($_POST['unit_price'] ?? ($batch_price / $min_qty));
    $add_k       = floatval($_POST['additional_per_thousand'] ?? ($unit_price * 1000));
    $turnaround  = max(1, intval($_POST['turnaround_days'] ?? 2));
    $notes       = trim($_POST['notes'] ?? '');

    if ($vendor_id <= 0 || $product_id <= 0 || empty($paper_stock) || $batch_price <= 0) {
        send_json([
            'success' => false, 
            'message' => 'Please provide valid product, paper stock, and batch price.',
            'message_ur' => 'براہ کرم پروڈکٹ، کاغذ کی قسم اور قیمت درست درج کریں۔'
        ]);
    }

    $v = $conn->query("SELECT price_refresh_frequency_days FROM print_vendors WHERE id = {$vendor_id}")->fetch_assoc();
    $freq = intval($v['price_refresh_frequency_days'] ?? 1);
    $now = date('Y-m-d H:i:s');
    $expires_at = date('Y-m-d H:i:s', time() + ($freq * 86400));

    $stmt = $conn->prepare("INSERT INTO print_vendor_rates 
        (vendor_id, product_id, paper_stock, finish_type, size, min_quantity, unit_price, batch_price, additional_per_thousand, turnaround_days, notes, last_confirmed_at, expires_at, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
    $stmt->bind_param("iisssiddsdssss", $vendor_id, $product_id, $paper_stock, $finish_type, $size, $min_qty, $unit_price, $batch_price, $add_k, $turnaround, $notes, $now, $expires_at);
    $stmt->execute();
    $rate_id = $conn->insert_id;

    $conn->query("UPDATE print_vendors SET last_price_refresh_at = '{$now}' WHERE id = {$vendor_id}");

    send_json([
        'success' => true,
        'message' => 'New rate card added and verified as active!',
        'message_ur' => 'نیا پروڈکٹ ریٹ کامیابی سے شامل کر دیا گیا۔',
        'rate_id' => $rate_id,
        'expires_at' => $expires_at
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. Delete Rate Entry
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'delete_rate') {
    if ($method !== 'POST') {
        send_json(['success' => false, 'message' => 'POST method required']);
    }

    $rate_id   = intval($_POST['rate_id'] ?? 0);
    $vendor_id = intval($_POST['vendor_id'] ?? ($_SESSION['vendor_id'] ?? 0));

    if ($rate_id <= 0 || $vendor_id <= 0) {
        send_json(['success' => false, 'message' => 'Invalid rate or vendor']);
    }

    $conn->query("DELETE FROM print_vendor_rates WHERE id = {$rate_id} AND vendor_id = {$vendor_id}");

    send_json([
        'success' => true, 
        'message' => 'Rate card removed.',
        'message_ur' => 'ریٹ کامیابی سے ختم کر دیا گیا۔'
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 7. One-Click Confirm All Existing Rates
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'confirm_rates') {
    if ($method !== 'POST') {
        send_json(['success' => false, 'message' => 'POST method required']);
    }

    $vendor_id = intval($_POST['vendor_id'] ?? ($_SESSION['vendor_id'] ?? 0));
    $token     = trim($_POST['token'] ?? ($_SESSION['vendor_token'] ?? ''));

    if ($vendor_id <= 0 && !empty($token)) {
        $v_row = $conn->query("SELECT id, price_refresh_frequency_days, company_name FROM print_vendors WHERE access_token = '" . $conn->real_escape_string($token) . "'")->fetch_assoc();
        if ($v_row) $vendor_id = intval($v_row['id']);
    }

    $v_check = $conn->query("SELECT id, price_refresh_frequency_days, company_name FROM print_vendors WHERE id = {$vendor_id}")->fetch_assoc();
    if (!$v_check || $vendor_id <= 0) {
        send_json(['success' => false, 'message' => 'Vendor not found']);
    }

    $freq_days = max(1, intval($v_check['price_refresh_frequency_days']));
    $now = date('Y-m-d H:i:s');
    $new_expires_at = date('Y-m-d H:i:s', time() + ($freq_days * 86400));

    $conn->query("UPDATE print_vendors SET last_price_refresh_at = '{$now}' WHERE id = {$vendor_id}");

    $stmt = $conn->prepare("UPDATE print_vendor_rates 
                            SET last_confirmed_at = ?, expires_at = ?, status = 'active' 
                            WHERE vendor_id = ?");
    $stmt->bind_param("ssi", $now, $new_expires_at, $vendor_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $log_stmt = $conn->prepare("INSERT INTO print_rate_refresh_logs (vendor_id, event_type, rates_affected_count, new_expires_at, triggered_by, ip_address) VALUES (?, 'refreshed', ?, ?, 'vendor_one_click', ?)");
    $log_stmt->bind_param("iiss", $vendor_id, $affected, $new_expires_at, $ip);
    $log_stmt->execute();

    send_json([
        'success' => true,
        'message' => "All pricing confirmed! Rates extended by {$freq_days} day(s) until " . date('M d, Y h:i A', strtotime($new_expires_at)),
        'message_ur' => "تمام قیمتوں کی تصدیق ہو گئی! ریٹس اگلے {$freq_days} دن تک قابل قبول ہیں۔",
        'rates_updated' => $affected,
        'new_expires_at' => $new_expires_at,
        'validity_days' => $freq_days
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 8. Update Refresh Frequency Setting
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'update_frequency') {
    if ($method !== 'POST') {
        send_json(['success' => false, 'message' => 'POST method required']);
    }

    $vendor_id = intval($_POST['vendor_id'] ?? ($_SESSION['vendor_id'] ?? 0));
    $frequency = intval($_POST['frequency_days'] ?? 1);

    if ($vendor_id <= 0 || $frequency <= 0) {
        send_json(['success' => false, 'message' => 'Invalid parameters']);
    }

    $stmt = $conn->prepare("UPDATE print_vendors SET price_refresh_frequency_days = ? WHERE id = ?");
    $stmt->bind_param("ii", $frequency, $vendor_id);
    $stmt->execute();

    send_json([
        'success' => true,
        'message' => "Refresh frequency set to every {$frequency} day(s)",
        'message_ur' => "ریٹ اپ ڈیٹ کا دورانیہ ہر {$frequency} دن پر مقرر کر دیا گیا ہے۔",
        'frequency_days' => $frequency
    ]);
}

send_json(['success' => false, 'message' => 'Unknown vendor action']);
