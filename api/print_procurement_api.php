<?php
/**
 * api/print_procurement_api.php
 * REST API for Print Procurement, Lowest Price Engine,
 * "Click to Quote" (Buyer), and "Click to Order" (Vendor).
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/print_db.php';

// Ensure tables exist
if (isset($conn) && $conn instanceof mysqli) {
    init_print_database($conn);
    run_rates_expiration_check($conn);
} else {
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

// Helper to send json
function send_json($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── Authentication & RBAC Enforcement ──
if (!isset($_SESSION['user_id'])) {
    send_json(['success' => false, 'message' => 'Unauthorized: Please log in']);
}

$current_role = strtolower($_SESSION['role'] ?? '');
if (empty($current_role) && isset($conn)) {
    $u_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $u_uid = intval($_SESSION['user_id']);
    $u_stmt->bind_param("i", $u_uid);
    $u_stmt->execute();
    $current_role = strtolower($u_stmt->get_result()->fetch_assoc()['role'] ?? 'user');
    $_SESSION['role'] = $current_role;
}

if (!in_array($current_role, ['super_admin', 'superadmin', 'admin', 'pm', 'manager', 'sales'])) {
    send_json(['success' => false, 'message' => 'Forbidden: Insufficient privileges for print procurement']);
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Get Products and Specifications Taxonomy
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'get_taxonomy') {
    $p_res = $conn->query("SELECT * FROM print_products WHERE is_active = 1 ORDER BY category ASC, name ASC");
    $products = [];
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

    // Also fetch clients for quick quote dropdown
    $clients = [];
    $c_res = $conn->query("SELECT id, client_name, company, email, phone FROM clients ORDER BY client_name ASC");
    if ($c_res) {
        while ($c = $c_res->fetch_assoc()) {
            $clients[] = $c;
        }
    }

    send_json([
        'success' => true,
        'products' => $products,
        'clients' => $clients
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Search & Compare Rates (Lowest-Price Discovery Engine)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'search_rates') {
    $product_id   = intval($_GET['product_id'] ?? 0);
    $paper_stock  = trim($_GET['paper_stock'] ?? '');
    $finish_type  = trim($_GET['finish_type'] ?? '');
    $size         = trim($_GET['size'] ?? '');
    $quantity     = max(1, intval($_GET['quantity'] ?? 1000));
    $include_exp  = intval($_GET['include_expired'] ?? 1); // 1 = show expired with warning, 0 = active only

    if ($product_id <= 0) {
        send_json(['success' => false, 'message' => 'Please select a print product']);
    }

    // Build dynamic query
    $where = ["pvr.product_id = {$product_id}", "pv.status = 'active'"];
    if (!empty($paper_stock) && $paper_stock !== 'all') {
        $escaped_stock = $conn->real_escape_string($paper_stock);
        $where[] = "pvr.paper_stock = '{$escaped_stock}'";
    }
    if (!empty($finish_type) && $finish_type !== 'all') {
        $escaped_finish = $conn->real_escape_string($finish_type);
        $where[] = "pvr.finish_type = '{$escaped_finish}'";
    }
    if (!empty($size) && $size !== 'all') {
        $escaped_size = $conn->real_escape_string($size);
        $where[] = "pvr.size = '{$escaped_size}'";
    }

    $where_sql = implode(' AND ', $where);

    $sql = "SELECT 
                pvr.*,
                pv.company_name AS vendor_name,
                pv.contact_person AS vendor_contact,
                pv.phone AS vendor_phone,
                pv.whatsapp AS vendor_whatsapp,
                pv.city AS vendor_city,
                pv.rating AS vendor_rating,
                pv.completed_orders_count,
                pv.price_refresh_frequency_days,
                pv.last_price_refresh_at,
                TIMESTAMPDIFF(SECOND, NOW(), pvr.expires_at) AS seconds_to_expiry
            FROM print_vendor_rates pvr
            JOIN print_vendors pv ON pv.id = pvr.vendor_id
            WHERE {$where_sql}";

    $res = $conn->query($sql);
    $records = [];

    $now = time();

    while ($row = $res->fetch_assoc()) {
        $rate_id = intval($row['id']);
        $sec_left = intval($row['seconds_to_expiry']);
        
        // Dynamic status calculation
        if ($sec_left <= 0) {
            $dyn_status = 'expired';
            $status_label = 'Expired';
            $status_class = 'status-expired';
        } elseif ($sec_left <= 43200) { // <= 12 hours
            $dyn_status = 'expiring_soon';
            $hours = max(1, round($sec_left / 3600));
            $status_label = "Expiring in {$hours}h";
            $status_class = 'status-warning';
        } else {
            $dyn_status = 'active';
            $days = round($sec_left / 86400, 1);
            $status_label = $days >= 1 ? "Valid ({$days}d left)" : "Valid (" . round($sec_left / 3600) . "h left)";
            $status_class = 'status-active';
        }

        if (!$include_exp && $dyn_status === 'expired') {
            continue;
        }

        // Calculate Cost for Requested Quantity
        $min_q = max(1, intval($row['min_quantity']));
        $batch_price = floatval($row['batch_price']);
        $unit_price  = floatval($row['unit_price']);
        $add_k       = floatval($row['additional_per_thousand'] ?? ($unit_price * 1000));

        if ($quantity <= $min_q) {
            $total_vendor_cost = $batch_price;
            $effective_unit_cost = $total_vendor_cost / $quantity;
        } else {
            $extra_qty = $quantity - $min_q;
            $extra_cost = ($extra_qty / 1000) * $add_k;
            $total_vendor_cost = $batch_price + $extra_cost;
            $effective_unit_cost = $total_vendor_cost / $quantity;
        }

        $records[] = [
            'rate_id' => $rate_id,
            'vendor_id' => intval($row['vendor_id']),
            'vendor_name' => $row['vendor_name'],
            'vendor_contact' => $row['vendor_contact'],
            'vendor_phone' => $row['vendor_phone'],
            'vendor_whatsapp' => preg_replace('/[^0-9]/', '', $row['vendor_whatsapp']),
            'vendor_city' => $row['vendor_city'],
            'vendor_rating' => floatval($row['vendor_rating']),
            'completed_orders' => intval($row['completed_orders_count']),
            'product_id' => intval($row['product_id']),
            'paper_stock' => $row['paper_stock'],
            'finish_type' => $row['finish_type'],
            'size' => $row['size'],
            'min_quantity' => $min_q,
            'unit_price' => $unit_price,
            'batch_price' => $batch_price,
            'additional_per_thousand' => $add_k,
            'turnaround_days' => intval($row['turnaround_days']),
            'notes' => $row['notes'],
            'last_confirmed_at' => $row['last_confirmed_at'],
            'expires_at' => $row['expires_at'],
            'dynamic_status' => $dyn_status,
            'status_label' => $status_label,
            'status_class' => $status_class,
            'seconds_to_expiry' => $sec_left,
            'requested_quantity' => $quantity,
            'total_vendor_cost' => round($total_vendor_cost, 2),
            'effective_unit_cost' => round($effective_unit_cost, 4)
        ];
    }

    // Sort: Active non-expired first, then Lowest Total Cost ascending, then turnaround days ascending
    usort($records, function ($a, $b) {
        $a_exp = ($a['dynamic_status'] === 'expired') ? 1 : 0;
        $b_exp = ($b['dynamic_status'] === 'expired') ? 1 : 0;
        if ($a_exp !== $b_exp) {
            return $a_exp - $b_exp;
        }
        if ($a['total_vendor_cost'] != $b['total_vendor_cost']) {
            return ($a['total_vendor_cost'] < $b['total_vendor_cost']) ? -1 : 1;
        }
        return $a['turnaround_days'] - $b['turnaround_days'];
    });

    // Tag winner badges
    $lowest_cost_rate_id = 0;
    $fastest_rate_id     = 0;
    $min_cost            = PHP_INT_MAX;
    $min_days            = PHP_INT_MAX;

    foreach ($records as $r) {
        if ($r['dynamic_status'] !== 'expired') {
            if ($r['total_vendor_cost'] < $min_cost) {
                $min_cost = $r['total_vendor_cost'];
                $lowest_cost_rate_id = $r['rate_id'];
            }
            if ($r['turnaround_days'] < $min_days) {
                $min_days = $r['turnaround_days'];
                $fastest_rate_id = $r['rate_id'];
            }
        }
    }

    foreach ($records as &$rec) {
        $rec['is_lowest_price'] = ($rec['rate_id'] === $lowest_cost_rate_id);
        $rec['is_fastest']      = ($rec['rate_id'] === $fastest_rate_id && !$rec['is_lowest_price']);
    }
    unset($rec);

    send_json([
        'success' => true,
        'query' => [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'paper_stock' => $paper_stock,
            'finish_type' => $finish_type
        ],
        'total_found' => count($records),
        'results' => $records
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. "Click to Quote" (Buyer Action)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'create_buyer_quote') {
    if ($method !== 'POST') {
        send_json(['success' => false, 'message' => 'POST method required']);
    }

    $client_id     = intval($_POST['client_id'] ?? 0) ?: null;
    $client_name   = trim($_POST['client_name'] ?? '');
    $client_email  = trim($_POST['client_email'] ?? '');
    $client_phone  = trim($_POST['client_phone'] ?? '');
    $client_company= trim($_POST['client_company'] ?? '');
    
    $product_name  = trim($_POST['product_name'] ?? 'Print Material');
    $paper_stock   = trim($_POST['paper_stock'] ?? '');
    $finish_type   = trim($_POST['finish_type'] ?? '');
    $size          = trim($_POST['size'] ?? 'Standard');
    $quantity      = max(1, intval($_POST['quantity'] ?? 1000));
    $vendor_cost   = floatval($_POST['vendor_cost'] ?? 0);
    $margin_percent= floatval($_POST['margin_percent'] ?? 30);
    $selling_price = floatval($_POST['selling_price'] ?? ($vendor_cost * (1 + ($margin_percent / 100))));
    $turnaround    = trim($_POST['turnaround'] ?? '3-4 Business Days');
    $notes         = trim($_POST['notes'] ?? '');

    if (empty($client_name)) {
        send_json(['success' => false, 'message' => 'Client Name is required']);
    }

    // Auto-generate unique Quotation Number (QUO-2026-XXX)
    $year = date('Y');
    $q_seq = $conn->query("SELECT MAX(id) AS mid FROM quotations")->fetch_assoc();
    $next_id = intval($q_seq['mid'] ?? 0) + 1;
    $quot_number = sprintf("QUO-%s-%03d", $year, $next_id);

    $issue_date  = date('Y-m-d');
    $valid_until = date('Y-m-d', strtotime('+15 days'));
    $status      = 'draft';
    $currency    = 'PKR';
    $subtotal    = $selling_price;
    $total       = $selling_price;
    $sig_name    = 'Ibadullah';
    $sig_title   = 'Managing Director';

    $project_overview = "Commercial Printing: {$product_name} ({$quantity} units)\nSpecifications: {$paper_stock}, {$finish_type}, Size: {$size}.\nEstimated turnaround: {$turnaround}.";
    if (!empty($notes)) {
        $project_overview .= "\nClient Notes: {$notes}";
    }

    $terms = "1. 50% advance along with confirmed purchase order; 50% upon delivery.\n2. Delivery timeline starts after digital artwork proof sign-off.\n3. Quoted price is valid for 15 days.";

    $sig_date = date('Y-m-d');
    $tax_percent = 0.00;
    $tax_amount = 0.00;
    $discount_amount = 0.00;

    // Insert into quotations table
    $stmt = $conn->prepare("INSERT INTO quotations 
        (quotation_number, client_id, client_name, client_email, client_phone, client_company,
         issue_date, valid_until, status, subtotal, tax_percent, tax_amount, discount_amount, total_amount,
         currency, notes, terms, signature_name, signature_title, signature_date,
         scope_project_name, delivery_time, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

    $stmt->bind_param(
        "sisssssssdddddssssssss",
        $quot_number,
        $client_id,
        $client_name,
        $client_email,
        $client_phone,
        $client_company,
        $issue_date,
        $valid_until,
        $status,
        $subtotal,
        $tax_percent,
        $tax_amount,
        $discount_amount,
        $total,
        $currency,
        $project_overview,
        $terms,
        $sig_name,
        $sig_title,
        $sig_date,
        $product_name,
        $turnaround
    );

    if (!$stmt->execute()) {
        send_json(['success' => false, 'message' => 'Database error creating quotation: ' . $stmt->error]);
    }

    $quotation_id = $conn->insert_id;

    // Insert line item into quotation_items
    $item_desc = "{$product_name} - {$paper_stock} | {$finish_type} ({$size})";
    $unit_selling = round($selling_price / $quantity, 4);
    $sort_order = 1;
    $unit = 'pcs';
    $tax_p = 0.00;
    $tax_a = 0.00;

    $stmt_item = $conn->prepare("INSERT INTO quotation_items 
        (quotation_id, description, quantity, unit_price, total, sort_order, unit, tax_percent, tax_amount) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $qty_float = floatval($quantity);
    $stmt_item->bind_param("isdddisdd", $quotation_id, $item_desc, $qty_float, $unit_selling, $selling_price, $sort_order, $unit, $tax_p, $tax_a);
    $stmt_item->execute();

    send_json([
        'success' => true,
        'message' => 'Quotation generated successfully',
        'quotation_id' => $quotation_id,
        'quotation_number' => $quot_number,
        'selling_price' => $selling_price,
        'view_url' => "quotation_details.php?id={$quotation_id}",
        'pdf_url' => "generate_quotation_pdf.php?id={$quotation_id}"
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. "Click to Order" (Vendor Action)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'create_vendor_order') {
    if ($method !== 'POST') {
        send_json(['success' => false, 'message' => 'POST method required']);
    }

    $vendor_id    = intval($_POST['vendor_id'] ?? 0);
    $rate_id      = intval($_POST['rate_id'] ?? 0) ?: null;
    $quotation_id = intval($_POST['quotation_id'] ?? 0) ?: null;
    $client_id    = intval($_POST['client_id'] ?? 0) ?: null;
    $client_name  = trim($_POST['client_name'] ?? 'Direct Sales Client');
    $product_name = trim($_POST['product_name'] ?? 'Print Material');
    $quantity     = max(1, intval($_POST['quantity'] ?? 1000));
    $vendor_cost  = floatval($_POST['vendor_cost'] ?? 0);
    $client_price = floatval($_POST['client_price'] ?? 0);
    $deadline     = trim($_POST['deadline'] ?? date('Y-m-d', strtotime('+3 days')));
    $address      = trim($_POST['delivery_address'] ?? 'Graphicafix Quality Control Office, Karachi');
    $instructions = trim($_POST['special_instructions'] ?? '');
    $artwork_url  = trim($_POST['artwork_url'] ?? '');

    // Handle artwork file upload if uploaded directly
    if (isset($_FILES['artwork_file']) && $_FILES['artwork_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/print_artworks/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['artwork_file']['name']);
        if (move_uploaded_file($_FILES['artwork_file']['tmp_name'], $upload_dir . $filename)) {
            $artwork_url = 'uploads/print_artworks/' . $filename;
        }
    }

    if ($vendor_id <= 0) {
        send_json(['success' => false, 'message' => 'Invalid print vendor selected']);
    }

    // Fetch vendor info
    $v_res = $conn->query("SELECT * FROM print_vendors WHERE id = {$vendor_id}")->fetch_assoc();
    if (!$v_res) {
        send_json(['success' => false, 'message' => 'Vendor not found']);
    }

    // Auto-generate PO Number (e.g. PO-PRINT-2026-001)
    $year = date('Y');
    $last_po = $conn->query("SELECT MAX(id) AS mid FROM print_vendor_orders")->fetch_assoc();
    $next_po = intval($last_po['mid'] ?? 0) + 1;
    $po_number = sprintf("PO-PRINT-%s-%03d", $year, $next_po);

    // Calculate margins
    if ($client_price <= 0) {
        $client_price = $vendor_cost * 1.30;
    }
    $margin_amount = max(0, $client_price - $vendor_cost);
    $margin_percent = ($client_price > 0) ? round(($margin_amount / $client_price) * 100, 2) : 0;
    $vendor_unit_cost = round($vendor_cost / $quantity, 4);

    $specs = [
        'product_name' => $product_name,
        'paper_stock' => trim($_POST['paper_stock'] ?? ''),
        'finish_type' => trim($_POST['finish_type'] ?? ''),
        'size' => trim($_POST['size'] ?? 'Standard'),
        'quantity' => $quantity,
        'turnaround_days' => intval($_POST['turnaround_days'] ?? 2)
    ];
    $specs_json = json_encode($specs, JSON_UNESCAPED_UNICODE);

    $assigned_by = intval($_SESSION['user_id'] ?? 1);

    $ins_po = $conn->prepare("INSERT INTO print_vendor_orders 
        (po_number, quotation_id, client_id, client_name, vendor_id, rate_id, assigned_by_user_id, product_name, specifications_json, quantity, vendor_unit_cost, vendor_total_cost, client_selling_price, gross_margin_amount, gross_margin_percentage, artwork_file_url, delivery_deadline, delivery_address, special_instructions, order_status, payment_status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'assigned', 'unpaid')");

    $ins_po->bind_param(
        "siisiiissiddddsssss",
        $po_number,
        $quotation_id,
        $client_id,
        $client_name,
        $vendor_id,
        $rate_id,
        $assigned_by,
        $product_name,
        $specs_json,
        $quantity,
        $vendor_unit_cost,
        $vendor_cost,
        $client_price,
        $margin_amount,
        $margin_percent,
        $artwork_url,
        $deadline,
        $address,
        $instructions
    );

    if (!$ins_po->execute()) {
        send_json(['success' => false, 'message' => 'Error placing vendor PO: ' . $ins_po->error]);
    }

    $order_id = $conn->insert_id;

    // Compose formatted WhatsApp Dispatch Message
    $vendor_whatsapp = preg_replace('/[^0-9]/', '', $v_res['whatsapp']);
    $wa_text = "📦 *NEW PURCHASE ORDER — GRAPHICAFIX PRINT HUB*\n";
    $wa_text .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $wa_text .= "*PO Number:* {$po_number}\n";
    $wa_text .= "*Product:* {$product_name}\n";
    $wa_text .= "*Quantity:* " . number_format($quantity) . " units\n";
    $wa_text .= "*Paper Stock:* {$specs['paper_stock']}\n";
    $wa_text .= "*Finishing:* {$specs['finish_type']}\n";
    $wa_text .= "*Size:* {$specs['size']}\n";
    $wa_text .= "*Agreed Vendor Total:* PKR " . number_format($vendor_cost, 2) . "\n";
    $wa_text .= "*Required Delivery:* {$deadline}\n";
    if (!empty($artwork_url)) {
        $wa_text .= "*Artwork File:* " . (str_starts_with($artwork_url, 'http') ? $artwork_url : (BASE_URL . $artwork_url)) . "\n";
    }
    if (!empty($instructions)) {
        $wa_text .= "*Instructions:* {$instructions}\n";
    }
    $wa_text .= "━━━━━━━━━━━━━━━━━━━━━━\n";
    $wa_text .= "Please acknowledge receipt and start production. Thank you!";

    $wa_link = "https://wa.me/{$vendor_whatsapp}?text=" . rawurlencode($wa_text);

    send_json([
        'success' => true,
        'message' => 'Vendor Order placed successfully',
        'order_id' => $order_id,
        'po_number' => $po_number,
        'vendor_name' => $v_res['company_name'],
        'vendor_cost' => $vendor_cost,
        'whatsapp_url' => $wa_link,
        'orders_url' => 'print_orders.php'
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. Compose Nudge WhatsApp Message for Stale/Expired Vendors
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'nudge_vendor') {
    $vendor_id = intval($_GET['vendor_id'] ?? 0);
    $product_name = trim($_GET['product_name'] ?? 'Print Materials');

    $v = $conn->query("SELECT * FROM print_vendors WHERE id = {$vendor_id}")->fetch_assoc();
    if (!$v) {
        send_json(['success' => false, 'message' => 'Vendor not found']);
    }

    $wa_num = preg_replace('/[^0-9]/', '', $v['whatsapp']);
    $refresh_url = BASE_URL . "admin/print_vendor_portal.php?token=" . urlencode($v['access_token']);

    $msg = "Salam {$v['contact_person']},\n";
    $msg .= "This is Graphicafix Sales Team. We have client inquiries for *{$product_name}*, but your rate card on our portal has expired.\n\n";
    $msg .= "Please tap below to refresh or confirm your rates for the next {$v['price_refresh_frequency_days']} days:\n";
    $msg .= "🔗 {$refresh_url}\n\n";
    $msg .= "Thank you!";

    send_json([
        'success' => true,
        'whatsapp_url' => "https://wa.me/{$wa_num}?text=" . rawurlencode($msg)
    ]);
}

send_json(['success' => false, 'message' => 'Unknown action requested']);
