<?php
// Prevent any stray output before JSON
ob_start();
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

// ── Authentication Check ──
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in']);
    exit;
}

$current_user_id   = intval($_SESSION['user_id']);
$current_role      = strtolower($_SESSION['role'] ?? 'user');
$current_user_name = $_SESSION['name'] ?? ($_SESSION['username'] ?? 'User');

$is_admin = in_array($current_role, ['super_admin', 'superadmin', 'admin', 'manager', 'pm']);
$is_sales = ($current_role === 'sales');

if (!$is_admin && !$is_sales) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// ── Auto-provision sales_visits Table ──
$conn->query("CREATE TABLE IF NOT EXISTS sales_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sales_rep_id INT NOT NULL,
    sales_rep_name VARCHAR(150) NOT NULL,
    client_name VARCHAR(150) NOT NULL,
    company_name VARCHAR(200) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    whatsapp VARCHAR(50) DEFAULT NULL,
    interest_level ENUM('hot', 'warm', 'cold') NOT NULL DEFAULT 'warm',
    contact_availability VARCHAR(255) NOT NULL,
    available_at DATETIME DEFAULT NULL,
    categories TEXT DEFAULT NULL,
    subcategories TEXT DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    quick_remark VARCHAR(150) DEFAULT NULL,
    is_urgent TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pending', 'contacted', 'in_discussion', 'converted', 'closed') NOT NULL DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    converted_lead_id INT DEFAULT NULL,
    contacted_at DATETIME DEFAULT NULL,
    contacted_by_id INT DEFAULT NULL,
    contacted_by_name VARCHAR(150) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (sales_rep_id),
    INDEX (interest_level),
    INDEX (is_urgent),
    INDEX (status),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Auto-patch columns if needed
$existing_cols = @array_column(
    @$conn->query("SHOW COLUMNS FROM sales_visits")->fetch_all(MYSQLI_ASSOC),
    'Field'
) ?: [];
if ($existing_cols && !in_array('converted_lead_id', $existing_cols)) {
    @$conn->query("ALTER TABLE sales_visits ADD COLUMN converted_lead_id INT DEFAULT NULL");
}
if ($existing_cols && !in_array('admin_notes', $existing_cols)) {
    @$conn->query("ALTER TABLE sales_visits ADD COLUMN admin_notes TEXT DEFAULT NULL");
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

    // ──────────────────────────────────────────────────────────────────────────
    // 1. SAVE VISIT (Insert / Update)
    // ──────────────────────────────────────────────────────────────────────────
    case 'save':
        $visit_id             = intval($_POST['visit_id'] ?? 0);
        $client_name          = trim($_POST['client_name'] ?? '');
        $company_name         = trim($_POST['company_name'] ?? '');
        $phone                = trim($_POST['phone'] ?? '');
        $whatsapp             = trim($_POST['whatsapp'] ?? '');
        $interest_level       = strtolower(trim($_POST['interest_level'] ?? 'warm'));
        $contact_availability = trim($_POST['contact_availability'] ?? '');
        $available_at         = !empty($_POST['available_at']) ? $_POST['available_at'] : null;
        $categories           = isset($_POST['categories']) ? (is_array($_POST['categories']) ? json_encode(array_values($_POST['categories'])) : trim($_POST['categories'])) : '[]';
        $subcategories        = isset($_POST['subcategories']) ? (is_array($_POST['subcategories']) ? json_encode(array_values($_POST['subcategories'])) : trim($_POST['subcategories'])) : '[]';
        $remarks              = trim($_POST['remarks'] ?? '');
        $quick_remark         = trim($_POST['quick_remark'] ?? '');
        $is_urgent            = !empty($_POST['is_urgent']) ? 1 : 0;

        if (empty($client_name) || empty($company_name) || empty($phone)) {
            echo json_encode(['success' => false, 'message' => 'Client Name, Company Name, and Phone are required.']);
            exit;
        }

        if (empty($whatsapp)) {
            $whatsapp = $phone;
        }

        if (!in_array($interest_level, ['hot', 'warm', 'cold'])) {
            $interest_level = 'warm';
        }

        if ($visit_id > 0) {
            // Update
            $sql = "UPDATE sales_visits SET 
                    client_name = ?, company_name = ?, phone = ?, whatsapp = ?, 
                    interest_level = ?, contact_availability = ?, available_at = ?,
                    categories = ?, subcategories = ?, remarks = ?, quick_remark = ?, is_urgent = ?
                    WHERE id = ?";
            
            // If sales, ensure they can only edit their own visits unless admin
            if (!$is_admin) {
                $sql .= " AND sales_rep_id = " . intval($current_user_id);
            }

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssssii", 
                $client_name, $company_name, $phone, $whatsapp,
                $interest_level, $contact_availability, $available_at,
                $categories, $subcategories, $remarks, $quick_remark, $is_urgent,
                $visit_id
            );
            $exec = $stmt->execute();
            $stmt->close();

            echo json_encode([
                'success' => $exec,
                'message' => $exec ? 'Visit information updated successfully' : 'Failed to update visit',
                'id'      => $visit_id
            ]);
            exit;

        } else {
            // Insert
            $sql = "INSERT INTO sales_visits 
                    (sales_rep_id, sales_rep_name, client_name, company_name, phone, whatsapp, 
                     interest_level, contact_availability, available_at, categories, subcategories, 
                     remarks, quick_remark, is_urgent, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssssssssssi",
                $current_user_id, $current_user_name, $client_name, $company_name, $phone, $whatsapp,
                $interest_level, $contact_availability, $available_at, $categories, $subcategories,
                $remarks, $quick_remark, $is_urgent
            );
            $exec = $stmt->execute();
            $new_id = $conn->insert_id;
            $stmt->close();

            if ($exec && $new_id) {
                // Add in-app notification for Admins
                $notif_title = $is_urgent 
                    ? "🚨 URGENT: Visit Logged for {$company_name}" 
                    : "New Sales Visit: {$company_name} ({$client_name})";
                $notif_msg = "Logged by {$current_user_name} (Interest: " . ucfirst($interest_level) . "). Contact window: {$contact_availability}";
                $notif_link = "sales_response_unit.php?highlight={$new_id}";

                $ins_notif = $conn->prepare("INSERT INTO notifications (type, title, message, link, reference_id, user_id, created_at) VALUES ('sales_visit', ?, ?, ?, ?, NULL, NOW())");
                if ($ins_notif) {
                    $ins_notif->bind_param("sssi", $notif_title, $notif_msg, $notif_link, $new_id);
                    $ins_notif->execute();
                    $ins_notif->close();
                }
            }

            echo json_encode([
                'success' => $exec,
                'message' => $exec ? 'Visit information logged successfully' : 'Failed to log visit',
                'id'      => $new_id
            ]);
            exit;
        }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. REAL-TIME POLLING (For Admin Response Unit & Dashboard)
    // ──────────────────────────────────────────────────────────────────────────
    case 'poll':
    case 'poll_latest':
        $last_id = intval($_GET['last_id'] ?? ($_GET['since_id'] ?? 0));
        $since   = trim($_GET['since'] ?? '');

        // Fetch any new entries with id > last_id
        $new_items = [];
        if ($last_id >= 0) {
            $stmt = $conn->prepare("SELECT * FROM sales_visits WHERE id > ? ORDER BY id DESC LIMIT 50");
            $stmt->bind_param("i", $last_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $row['categories_arr']    = json_decode($row['categories'] ?? '[]', true) ?: [];
                $row['subcategories_arr'] = json_decode($row['subcategories'] ?? '[]', true) ?: [];
                $row['time_ago']          = timeAgoVisits($row['created_at']);
                $new_items[]              = $row;
            }
            $stmt->close();
        }

        // Aggregate statistics for response unit counters
        $stats = $conn->query("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN is_urgent = 1 AND status != 'closed' THEN 1 ELSE 0 END) AS urgent_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN interest_level = 'hot' AND status != 'closed' THEN 1 ELSE 0 END) AS hot_count,
                SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) AS contacted_count,
                MAX(id) AS latest_id
            FROM sales_visits
        ")->fetch_assoc();

        $has_urgent = false;
        foreach ($new_items as $item) {
            if ($item['is_urgent'] == 1) {
                $has_urgent = true;
                break;
            }
        }

        echo json_encode([
            'success'     => true,
            'has_new'     => count($new_items) > 0,
            'new_count'   => count($new_items),
            'new_records' => $new_items,
            'items'       => $new_items,
            'has_urgent'  => $has_urgent,
            'latest_id'   => intval($stats['latest_id'] ?? 0),
            'stats'       => [
                'total'     => intval($stats['total'] ?? 0),
                'urgent'    => intval($stats['urgent_count'] ?? 0),
                'pending'   => intval($stats['pending_count'] ?? 0),
                'hot'       => intval($stats['hot_count'] ?? 0),
                'contacted' => intval($stats['contacted_count'] ?? 0),
            ]
        ]);
        exit;

    // ──────────────────────────────────────────────────────────────────────────
    // 3. FETCH ALL / FILTERED LIST
    // ──────────────────────────────────────────────────────────────────────────
    case 'fetch_all':
        $filter      = trim($_GET['filter'] ?? 'all');
        $sort        = trim($_GET['sort'] ?? 'urgency');
        $search      = trim($_GET['search'] ?? '');
        $rep_id      = intval($_GET['rep_id'] ?? 0);

        $where_clauses = ["1=1"];
        $params        = [];
        $types         = "";

        // If non-admin sales rep, only show their own logged visits
        if (!$is_admin) {
            $where_clauses[] = "sales_rep_id = ?";
            $params[]        = $current_user_id;
            $types          .= "i";
        } elseif ($rep_id > 0) {
            $where_clauses[] = "sales_rep_id = ?";
            $params[]        = $rep_id;
            $types          .= "i";
        }

        // Tab filters
        if ($filter === 'urgent') {
            $where_clauses[] = "is_urgent = 1 AND status != 'closed'";
        } elseif ($filter === 'hot') {
            $where_clauses[] = "interest_level = 'hot' AND status != 'closed'";
        } elseif ($filter === 'pending') {
            $where_clauses[] = "status = 'pending'";
        } elseif ($filter === 'contacted') {
            $where_clauses[] = "status = 'contacted'";
        }

        // Search
        if (!empty($search)) {
            $where_clauses[] = "(client_name LIKE ? OR company_name LIKE ? OR phone LIKE ? OR sales_rep_name LIKE ? OR remarks LIKE ?)";
            $s_term          = "%" . $search . "%";
            $params[]        = $s_term;
            $params[]        = $s_term;
            $params[]        = $s_term;
            $params[]        = $s_term;
            $params[]        = $s_term;
            $types          .= "sssss";
        }

        $where_sql = implode(" AND ", $where_clauses);

        // Sort order
        $order_sql = match ($sort) {
            'interest'     => "FIELD(interest_level, 'hot', 'warm', 'cold') ASC, is_urgent DESC, id DESC",
            'contact_time' => "available_at ASC, is_urgent DESC, id DESC",
            'newest'       => "id DESC",
            default        => "is_urgent DESC, FIELD(interest_level, 'hot', 'warm', 'cold') ASC, id DESC",
        };

        $sql = "SELECT * FROM sales_visits WHERE {$where_sql} ORDER BY {$order_sql} LIMIT 200";
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        $items = [];
        while ($row = $res->fetch_assoc()) {
            $row['categories_arr']    = json_decode($row['categories'] ?? '[]', true) ?: [];
            $row['subcategories_arr'] = json_decode($row['subcategories'] ?? '[]', true) ?: [];
            $row['time_ago']          = timeAgoVisits($row['created_at']);
            $items[]                  = $row;
        }
        $stmt->close();

        // Get aggregate stats
        $stats = $conn->query("
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN is_urgent = 1 AND status != 'closed' THEN 1 ELSE 0 END) AS urgent_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN interest_level = 'hot' AND status != 'closed' THEN 1 ELSE 0 END) AS hot_count,
                SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) AS contacted_count,
                MAX(id) AS latest_id
            FROM sales_visits
        ")->fetch_assoc();

        echo json_encode([
            'success'   => true,
            'items'     => $items,
            'count'     => count($items),
            'latest_id' => intval($stats['latest_id'] ?? 0),
            'stats'     => [
                'total'     => intval($stats['total'] ?? 0),
                'urgent'    => intval($stats['urgent_count'] ?? 0),
                'pending'   => intval($stats['pending_count'] ?? 0),
                'hot'       => intval($stats['hot_count'] ?? 0),
                'contacted' => intval($stats['contacted_count'] ?? 0),
            ]
        ]);
        exit;

    // ──────────────────────────────────────────────────────────────────────────
    // 4. TOGGLE URGENT STATUS
    // ──────────────────────────────────────────────────────────────────────────
    case 'toggle_urgent':
        $visit_id = intval($_POST['visit_id'] ?? ($_POST['id'] ?? ($_GET['visit_id'] ?? ($_GET['id'] ?? 0))));
        if (!$visit_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid visit ID']);
            exit;
        }

        $check = $conn->query("SELECT is_urgent, company_name FROM sales_visits WHERE id = {$visit_id}")->fetch_assoc();
        if (!$check) {
            echo json_encode(['success' => false, 'message' => 'Visit not found']);
            exit;
        }

        $new_urgent = $check['is_urgent'] ? 0 : 1;
        $conn->query("UPDATE sales_visits SET is_urgent = {$new_urgent} WHERE id = {$visit_id}");

        echo json_encode([
            'success'   => true,
            'is_urgent' => $new_urgent,
            'message'   => $new_urgent ? 'Marked as URGENT contact required!' : 'Urgent priority removed.'
        ]);
        exit;

    // ──────────────────────────────────────────────────────────────────────────
    // 5. UPDATE VISIT STATUS (Contacted, In Discussion, Closed, etc.)
    // ──────────────────────────────────────────────────────────────────────────
    case 'update_status':
        $visit_id = intval($_POST['visit_id'] ?? ($_POST['id'] ?? 0));
        $status   = strtolower(trim($_POST['status'] ?? ''));
        $notes    = trim($_POST['admin_notes'] ?? '');

        $allowed_statuses = ['pending', 'contacted', 'in_discussion', 'converted', 'closed'];
        if (!$visit_id || !in_array($status, $allowed_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE sales_visits SET 
            status = ?, 
            admin_notes = IF(? != '', ?, admin_notes),
            contacted_at = IF(? = 'contacted' AND contacted_at IS NULL, NOW(), contacted_at),
            contacted_by_id = IF(? = 'contacted' AND contacted_by_id IS NULL, ?, contacted_by_id),
            contacted_by_name = IF(? = 'contacted' AND contacted_by_name IS NULL, ?, contacted_by_name)
            WHERE id = ?");
        $stmt->bind_param("sssssisi", $status, $notes, $notes, $status, $status, $current_user_id, $status, $current_user_name, $visit_id);
        $exec = $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success' => $exec,
            'status'  => $status,
            'message' => 'Status updated successfully'
        ]);
        exit;

    // ──────────────────────────────────────────────────────────────────────────
    // 6. CONVERT TO CRM LEAD
    // ──────────────────────────────────────────────────────────────────────────
    case 'convert_to_lead':
        if (!$is_admin && !$is_sales) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }

        $visit_id = intval($_POST['visit_id'] ?? 0);
        $v_res = $conn->query("SELECT * FROM sales_visits WHERE id = {$visit_id}");
        $v = $v_res ? $v_res->fetch_assoc() : null;

        if (!$v) {
            echo json_encode(['success' => false, 'message' => 'Visit not found']);
            exit;
        }

        // Decode categories for service_interest
        $cat_arr = json_decode($v['categories'] ?? '[]', true) ?: [];
        $subcat_arr = json_decode($v['subcategories'] ?? '[]', true) ?: [];
        $all_interests = array_merge($cat_arr, $subcat_arr);
        $service_interest = implode(', ', array_unique($all_interests));

        $lead_priority = match ($v['interest_level']) {
            'hot'   => 'high',
            'cold'  => 'low',
            default => 'medium',
        };

        $notes = "Converted from Sales Visit (ID #{$visit_id}).\nLogged by: {$v['sales_rep_name']}.\nAvailability: {$v['contact_availability']}.\nQuick Remark: {$v['quick_remark']}.\nRemarks: {$v['remarks']}";

        $stmt = $conn->prepare("INSERT INTO leads 
            (name, company, phone, source, service_interest, pipeline_stage, priority, notes, assigned_to, created_at)
            VALUES (?, ?, ?, 'walk_in', ?, 'contacted', ?, ?, ?, NOW())");
        $stmt->bind_param("ssssssi", 
            $v['client_name'], 
            $v['company_name'], 
            $v['phone'], 
            $service_interest, 
            $lead_priority, 
            $notes, 
            $v['sales_rep_id']
        );
        $exec = $stmt->execute();
        $lead_id = $conn->insert_id;
        $stmt->close();

        if ($exec && $lead_id) {
            $conn->query("UPDATE sales_visits SET status = 'converted', converted_lead_id = {$lead_id} WHERE id = {$visit_id}");
            echo json_encode([
                'success' => true,
                'lead_id' => $lead_id,
                'message' => "Successfully converted to CRM Lead #{$lead_id}!"
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to convert to CRM Lead']);
        }
        exit;

    // ──────────────────────────────────────────────────────────────────────────
    // 7. DELETE VISIT
    // ──────────────────────────────────────────────────────────────────────────
    case 'delete':
        if (!$is_admin) {
            echo json_encode(['success' => false, 'message' => 'Only administrators can delete visits']);
            exit;
        }
        $visit_id = intval($_POST['visit_id'] ?? 0);
        if ($visit_id) {
            $conn->query("DELETE FROM sales_visits WHERE id = {$visit_id}");
            echo json_encode(['success' => true, 'message' => 'Visit record deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid visit ID']);
        }
        exit;

    // ──────────────────────────────────────────────────────────────────────────
    // 8. GET SALES REPS FOR FILTER / EXPORT
    // ──────────────────────────────────────────────────────────────────────────
    case 'get_reps':
        $reps = [];
        // First get distinct reps from sales_visits
        $res = $conn->query("
            SELECT DISTINCT sales_rep_id AS id, sales_rep_name AS name 
            FROM sales_visits 
            WHERE sales_rep_id > 0
            ORDER BY sales_rep_name ASC
        ");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $reps[$r['id']] = [
                    'id'   => intval($r['id']),
                    'name' => $r['name'],
                    'role' => 'Sales Rep'
                ];
            }
        }

        // Also include active users with role sales, admin, or super_admin
        $res_users = $conn->query("
            SELECT user_id AS id, name, role 
            FROM users 
            WHERE role IN ('sales', 'admin', 'super_admin') AND status = 'active'
            ORDER BY name ASC
        ");
        if ($res_users) {
            while ($r = $res_users->fetch_assoc()) {
                if (!isset($reps[$r['id']])) {
                    $reps[$r['id']] = [
                        'id'   => intval($r['id']),
                        'name' => $r['name'],
                        'role' => ucfirst($r['role'])
                    ];
                }
            }
        }

        echo json_encode([
            'success' => true,
            'reps'    => array_values($reps)
        ]);
        exit;

    // ──────────────────────────────────────────────────────────────────────────
    // 9. LOG AGENT RESPONSE INTERACTION & QUICK NOTE
    // ──────────────────────────────────────────────────────────────────────────
    case 'log_response_interaction':
        $visit_id   = intval($_POST['visit_id'] ?? 0);
        $new_status = strtolower(trim($_POST['status'] ?? ''));
        $note       = trim($_POST['interaction_note'] ?? '');

        if (!$visit_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid visit ID']);
            exit;
        }

        $v_res = $conn->query("SELECT * FROM sales_visits WHERE id = {$visit_id}");
        $v = $v_res ? $v_res->fetch_assoc() : null;
        if (!$v) {
            echo json_encode(['success' => false, 'message' => 'Visit not found']);
            exit;
        }

        $existing_notes = trim($v['admin_notes'] ?? '');
        $timestamp      = date('M d, Y h:i A');
        $updated_notes  = $existing_notes;

        if (!empty($note)) {
            $formatted_entry = "[{$timestamp} by {$current_user_name}]: {$note}";
            $updated_notes   = !empty($existing_notes) ? "{$existing_notes}\n{$formatted_entry}" : $formatted_entry;
        }

        $allowed_statuses = ['pending', 'contacted', 'in_discussion', 'converted', 'closed'];
        $final_status     = in_array($new_status, $allowed_statuses) ? $new_status : $v['status'];

        $contacted_sql = "";
        if ($final_status === 'contacted' && empty($v['contacted_at'])) {
            $contacted_sql = ", contacted_at = NOW(), contacted_by_id = {$current_user_id}, contacted_by_name = '" . $conn->real_escape_string($current_user_name) . "'";
        }

        $stmt = $conn->prepare("UPDATE sales_visits SET status = ?, admin_notes = ? {$contacted_sql} WHERE id = ?");
        $stmt->bind_param("ssi", $final_status, $updated_notes, $visit_id);
        $exec = $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success'     => $exec,
            'status'      => $final_status,
            'admin_notes' => $updated_notes,
            'message'     => 'Response interaction recorded successfully'
        ]);
        exit;

    // ──────────────────────────────────────────────────────────────────────────
    // 10. EXPORT DATA TO SPREADSHEET (CSV / EXCEL COMPATIBLE)
    // ──────────────────────────────────────────────────────────────────────────
    case 'export_csv':
    case 'export_sheet':
        $rep_id      = intval($_GET['rep_id'] ?? 0);
        $start_date  = trim($_GET['start_date'] ?? '');
        $end_date    = trim($_GET['end_date'] ?? '');
        $status      = trim($_GET['status'] ?? 'all');
        $interest    = trim($_GET['interest'] ?? 'all');
        $urgent_only = intval($_GET['urgent_only'] ?? 0);

        $where = ["1=1"];
        $params = [];
        $types  = "";

        // Non-admin sales reps only export their own visits
        if (!$is_admin) {
            $where[]  = "sales_rep_id = ?";
            $params[] = $current_user_id;
            $types   .= "i";
        } elseif ($rep_id > 0) {
            $where[]  = "sales_rep_id = ?";
            $params[] = $rep_id;
            $types   .= "i";
        }

        if (!empty($start_date)) {
            $where[]  = "created_at >= ?";
            $params[] = $start_date . " 00:00:00";
            $types   .= "s";
        }

        if (!empty($end_date)) {
            $where[]  = "created_at <= ?";
            $params[] = $end_date . " 23:59:59";
            $types   .= "s";
        }

        if ($status !== 'all' && !empty($status)) {
            $where[]  = "status = ?";
            $params[] = $status;
            $types   .= "s";
        }

        if ($interest !== 'all' && !empty($interest)) {
            $where[]  = "interest_level = ?";
            $params[] = $interest;
            $types   .= "s";
        }

        if ($urgent_only === 1) {
            $where[] = "is_urgent = 1";
        }

        $sql = "SELECT * FROM sales_visits WHERE " . implode(" AND ", $where) . " ORDER BY id DESC";
        $stmt = $conn->prepare($sql);
        if (!empty($types) && count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        // Clear output buffer
        while (ob_get_level() > 0) ob_end_clean();

        // Send Excel CSV headers
        $filename = "sales_visits_export_" . date('Y-m-d_His') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // UTF-8 BOM for flawless Excel opening
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');

        // CSV Header Row
        fputcsv($output, [
            'Visit ID',
            'Date Logged',
            'Time Logged',
            'Sales Representative',
            'Client Name',
            'Company / Business',
            'Phone Number',
            'WhatsApp Number',
            'Priority Level',
            'Urgency',
            'Current Status',
            'Preferred Contact Window',
            'Required Services / Categories',
            'Specific Subcategories',
            'Field Rep Remarks',
            'Quick Remark',
            'Response Interaction Notes',
            'Contacted At',
            'Contacted By'
        ]);

        while ($r = $result->fetch_assoc()) {
            $cat_arr    = json_decode($r['categories'] ?? '[]', true) ?: [];
            $subcat_arr = json_decode($r['subcategories'] ?? '[]', true) ?: [];

            $date_str = date('Y-m-d', strtotime($r['created_at']));
            $time_str = date('h:i A', strtotime($r['created_at']));

            $priority_label = match ($r['interest_level']) {
                'hot'   => 'High (Hot)',
                'cold'  => 'Standard (Cold)',
                default => 'Medium (Warm)',
            };

            $urgency_label = ($r['is_urgent'] == 1) ? 'Urgent' : 'Standard';

            $status_label = match ($r['status']) {
                'contacted'     => 'Contacted',
                'in_discussion' => 'In Discussion',
                'converted'     => 'Converted to Lead',
                'closed'        => 'Closed / Resolved',
                default         => 'Pending Contact',
            };

            fputcsv($output, [
                $r['id'],
                $date_str,
                $time_str,
                $r['sales_rep_name'],
                $r['client_name'],
                $r['company_name'],
                $r['phone'],
                $r['whatsapp'] ?: $r['phone'],
                $priority_label,
                $urgency_label,
                $status_label,
                $r['contact_availability'],
                implode(', ', $cat_arr),
                implode(', ', $subcat_arr),
                $r['remarks'] ?? '',
                $r['quick_remark'] ?? '',
                $r['admin_notes'] ?? '',
                $r['contacted_at'] ? date('Y-m-d h:i A', strtotime($r['contacted_at'])) : '',
                $r['contacted_by_name'] ?? ''
            ]);
        }

        fclose($output);
        $stmt->close();
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
}

function timeAgoVisits($datetime) {
    if (!$datetime) return 'Just now';
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d, Y', $time);
}
