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
    next_followup_date DATETIME DEFAULT NULL,
    followup_count INT NOT NULL DEFAULT 0,
    last_followup_at DATETIME DEFAULT NULL,
    last_followup_by VARCHAR(150) DEFAULT NULL,
    last_outcome VARCHAR(100) DEFAULT NULL,
    contacted_at DATETIME DEFAULT NULL,
    contacted_by_id INT DEFAULT NULL,
    contacted_by_name VARCHAR(150) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (sales_rep_id),
    INDEX (interest_level),
    INDEX (is_urgent),
    INDEX (status),
    INDEX (next_followup_date),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Auto-provision sales_visit_followups Table ──
$conn->query("CREATE TABLE IF NOT EXISTS sales_visit_followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL,
    user_id INT NOT NULL,
    user_name VARCHAR(150) NOT NULL,
    contact_channel VARCHAR(50) NOT NULL DEFAULT 'call',
    outcome VARCHAR(50) NOT NULL DEFAULT 'followup_needed',
    notes TEXT NOT NULL,
    next_followup_date DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (visit_id),
    INDEX (user_id),
    INDEX (outcome),
    INDEX (next_followup_date),
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
if ($existing_cols && !in_array('next_followup_date', $existing_cols)) {
    @$conn->query("ALTER TABLE sales_visits ADD COLUMN next_followup_date DATETIME DEFAULT NULL, ADD INDEX (next_followup_date)");
}
if ($existing_cols && !in_array('followup_count', $existing_cols)) {
    @$conn->query("ALTER TABLE sales_visits ADD COLUMN followup_count INT NOT NULL DEFAULT 0");
}
if ($existing_cols && !in_array('last_followup_at', $existing_cols)) {
    @$conn->query("ALTER TABLE sales_visits ADD COLUMN last_followup_at DATETIME DEFAULT NULL");
}
if ($existing_cols && !in_array('last_followup_by', $existing_cols)) {
    @$conn->query("ALTER TABLE sales_visits ADD COLUMN last_followup_by VARCHAR(150) DEFAULT NULL");
}
if ($existing_cols && !in_array('last_outcome', $existing_cols)) {
    @$conn->query("ALTER TABLE sales_visits ADD COLUMN last_outcome VARCHAR(100) DEFAULT NULL");
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
        $next_followup_date   = !empty($_POST['next_followup_date']) ? $_POST['next_followup_date'] : null;

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
                    categories = ?, subcategories = ?, remarks = ?, quick_remark = ?, is_urgent = ?,
                    next_followup_date = IF(? IS NOT NULL, ?, next_followup_date)
                    WHERE id = ?";
            
            if (!$is_admin) {
                $sql .= " AND sales_rep_id = " . intval($current_user_id);
            }

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssssssssi", 
                $client_name, $company_name, $phone, $whatsapp,
                $interest_level, $contact_availability, $available_at,
                $categories, $subcategories, $remarks, $quick_remark, $is_urgent,
                $next_followup_date, $next_followup_date,
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
                     remarks, quick_remark, is_urgent, status, next_followup_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssssssssssss",
                $current_user_id, $current_user_name, $client_name, $company_name, $phone, $whatsapp,
                $interest_level, $contact_availability, $available_at, $categories, $subcategories,
                $remarks, $quick_remark, $is_urgent, $next_followup_date
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
    // 2. REAL-TIME POLLING (For Response Unit & Dashboard)
    // ──────────────────────────────────────────────────────────────────────────
    case 'poll':
    case 'poll_latest':
        $last_id = intval($_GET['last_id'] ?? ($_GET['since_id'] ?? 0));

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
                $row['followup_status']   = getFollowupStatus($row['next_followup_date'], $row['status']);
                $row['formatted_next_followup'] = $row['next_followup_date'] ? date('M d, Y h:i A', strtotime($row['next_followup_date'])) : null;
                $new_items[]              = $row;
            }
            $stmt->close();
        }

        // Aggregate statistics for response unit counters
        $stats = getSalesVisitsStats($conn);

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
            'stats'       => $stats
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

        // Non-admin sales rep only sees their own logged visits
        if (!$is_admin) {
            $where_clauses[] = "sales_rep_id = ?";
            $params[]        = $current_user_id;
            $types          .= "i";
        } elseif ($rep_id > 0) {
            $where_clauses[] = "sales_rep_id = ?";
            $params[]        = $rep_id;
            $types          .= "i";
        }

        // Tab filters & Follow-up tracking filters
        if ($filter === 'urgent') {
            $where_clauses[] = "is_urgent = 1 AND status != 'closed'";
        } elseif ($filter === 'hot') {
            $where_clauses[] = "interest_level = 'hot' AND status != 'closed'";
        } elseif ($filter === 'warm') {
            $where_clauses[] = "interest_level = 'warm' AND status != 'closed'";
        } elseif ($filter === 'cold') {
            $where_clauses[] = "interest_level = 'cold' AND status != 'closed'";
        } elseif ($filter === 'pending') {
            $where_clauses[] = "status = 'pending'";
        } elseif ($filter === 'contacted') {
            $where_clauses[] = "status = 'contacted'";
        } elseif ($filter === 'in_discussion') {
            $where_clauses[] = "status = 'in_discussion'";
        } elseif ($filter === 'converted') {
            $where_clauses[] = "status = 'converted'";
        } elseif ($filter === 'closed') {
            $where_clauses[] = "status = 'closed'";
        } elseif ($filter === 'due_today') {
            $where_clauses[] = "DATE(next_followup_date) = CURDATE() AND status NOT IN ('converted', 'closed')";
        } elseif ($filter === 'overdue') {
            $where_clauses[] = "next_followup_date < NOW() AND status NOT IN ('converted', 'closed')";
        } elseif ($filter === 'upcoming') {
            $where_clauses[] = "next_followup_date > NOW() AND status NOT IN ('converted', 'closed')";
        } elseif ($filter === 'scheduled') {
            $where_clauses[] = "next_followup_date IS NOT NULL AND status NOT IN ('converted', 'closed')";
        } elseif ($filter === 'needs_followup') {
            $where_clauses[] = "(next_followup_date < NOW() OR (status = 'pending' AND followup_count = 0)) AND status NOT IN ('converted', 'closed')";
        }

        // Search
        if (!empty($search)) {
            $where_clauses[] = "(client_name LIKE ? OR company_name LIKE ? OR phone LIKE ? OR sales_rep_name LIKE ? OR remarks LIKE ? OR quick_remark LIKE ?)";
            $s_term          = "%" . $search . "%";
            $params[]        = $s_term;
            $params[]        = $s_term;
            $params[]        = $s_term;
            $params[]        = $s_term;
            $params[]        = $s_term;
            $params[]        = $s_term;
            $types          .= "ssssss";
        }

        $where_sql = implode(" AND ", $where_clauses);

        // Sort order
        $order_sql = match ($sort) {
            'interest'     => "FIELD(interest_level, 'hot', 'warm', 'cold') ASC, is_urgent DESC, id DESC",
            'contact_time' => "available_at ASC, is_urgent DESC, id DESC",
            'followup_due' => "CASE WHEN next_followup_date IS NULL THEN 1 ELSE 0 END ASC, next_followup_date ASC, id DESC",
            'newest'       => "id DESC",
            'followup_cnt' => "followup_count DESC, id DESC",
            default        => "is_urgent DESC, FIELD(interest_level, 'hot', 'warm', 'cold') ASC, id DESC",
        };

        $sql = "SELECT * FROM sales_visits WHERE {$where_sql} ORDER BY {$order_sql} LIMIT 250";
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
            $row['last_followup_ago'] = $row['last_followup_at'] ? timeAgoVisits($row['last_followup_at']) : null;
            $row['followup_status']   = getFollowupStatus($row['next_followup_date'], $row['status']);
            $row['formatted_next_followup'] = $row['next_followup_date'] ? date('M d, Y h:i A', strtotime($row['next_followup_date'])) : null;
            $items[]                  = $row;
        }
        $stmt->close();

        // Get aggregate stats
        $stats = getSalesVisitsStats($conn);

        echo json_encode([
            'success'   => true,
            'items'     => $items,
            'count'     => count($items),
            'latest_id' => intval($stats['latest_id'] ?? 0),
            'stats'     => $stats
        ]);
        exit;

    // ──────────────────────────────────────────────────────────────────────────
    // 4. ADD STRUCTURED FOLLOW-UP TRACK RECORD
    // ──────────────────────────────────────────────────────────────────────────
    case 'add_followup':
        $visit_id           = intval($_POST['visit_id'] ?? 0);
        $contact_channel    = strtolower(trim($_POST['contact_channel'] ?? 'call'));
        $outcome            = strtolower(trim($_POST['outcome'] ?? 'followup_needed'));
        $notes              = trim($_POST['notes'] ?? '');
        $next_followup_date = !empty($_POST['next_followup_date']) ? $_POST['next_followup_date'] : null;
        $new_status         = strtolower(trim($_POST['status'] ?? ''));

        if (!$visit_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid visit ID']);
            exit;
        }

        if (empty($notes)) {
            echo json_encode(['success' => false, 'message' => 'Please provide discussion / follow-up notes']);
            exit;
        }

        // Validate channels and outcomes
        $allowed_channels = ['call', 'whatsapp', 'meeting', 'email', 'office_visit', 'other'];
        if (!in_array($contact_channel, $allowed_channels)) {
            $contact_channel = 'call';
        }

        $allowed_outcomes = [
            'interested', 'meeting_scheduled', 'proposal_sent', 'callback_requested',
            'followup_needed', 'no_answer', 'not_interested', 'converted', 'closed'
        ];
        if (!in_array($outcome, $allowed_outcomes)) {
            $outcome = 'followup_needed';
        }

        // Check visit exists
        $v_res = $conn->query("SELECT * FROM sales_visits WHERE id = {$visit_id}");
        $v = $v_res ? $v_res->fetch_assoc() : null;
        if (!$v) {
            echo json_encode(['success' => false, 'message' => 'Visit not found']);
            exit;
        }

        // Insert into sales_visit_followups
        $ins_f = $conn->prepare("INSERT INTO sales_visit_followups 
            (visit_id, user_id, user_name, contact_channel, outcome, notes, next_followup_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $ins_f->bind_param("iisssss", 
            $visit_id, 
            $current_user_id, 
            $current_user_name, 
            $contact_channel, 
            $outcome, 
            $notes, 
            $next_followup_date
        );
        $exec_f = $ins_f->execute();
        $followup_id = $conn->insert_id;
        $ins_f->close();

        if (!$exec_f) {
            echo json_encode(['success' => false, 'message' => 'Failed to record follow-up']);
            exit;
        }

        // Determine updated status
        $allowed_statuses = ['pending', 'contacted', 'in_discussion', 'converted', 'closed'];
        if (empty($new_status) || !in_array($new_status, $allowed_statuses)) {
            if ($outcome === 'converted') {
                $new_status = 'converted';
            } elseif ($outcome === 'not_interested' || $outcome === 'closed') {
                $new_status = 'closed';
            } else {
                $new_status = ($v['status'] === 'pending') ? 'in_discussion' : $v['status'];
            }
        }

        // Format admin notes entry
        $timestamp       = date('M d, Y h:i A');
        $channel_label   = ucfirst($contact_channel);
        $outcome_label   = ucwords(str_replace('_', ' ', $outcome));
        $log_entry       = "[{$timestamp} by {$current_user_name}] Follow-up ({$channel_label} - {$outcome_label}): {$notes}";
        $existing_notes  = trim($v['admin_notes'] ?? '');
        $updated_notes   = !empty($existing_notes) ? "{$existing_notes}\n{$log_entry}" : $log_entry;

        // Contacted timestamp handling
        $contacted_sql = "";
        if (empty($v['contacted_at'])) {
            $contacted_sql = ", contacted_at = NOW(), contacted_by_id = {$current_user_id}, contacted_by_name = '" . $conn->real_escape_string($current_user_name) . "'";
        }

        // Update sales_visits master row
        $stmt_up = $conn->prepare("UPDATE sales_visits SET 
            followup_count = followup_count + 1,
            last_followup_at = NOW(),
            last_followup_by = ?,
            last_outcome = ?,
            next_followup_date = ?,
            status = ?,
            admin_notes = ?
            {$contacted_sql}
            WHERE id = ?");
        $stmt_up->bind_param("sssssi", 
            $current_user_name, 
            $outcome, 
            $next_followup_date, 
            $new_status, 
            $updated_notes, 
            $visit_id
        );
        $stmt_up->execute();
        $stmt_up->close();

        // If converted and not yet converted to CRM lead, create lead
        $lead_id = null;
        if ($new_status === 'converted' && empty($v['converted_lead_id'])) {
            $lead_id = convertVisitToLeadInternal($conn, $v, $current_user_id, $current_user_name);
        }

        echo json_encode([
            'success'            => true,
            'message'            => 'Follow-up interaction recorded successfully!',
            'followup_id'        => $followup_id,
            'visit_id'           => $visit_id,
            'status'             => $new_status,
            'next_followup_date' => $next_followup_date,
            'lead_id'            => $lead_id
        ]);
        exit;

    // ──────────────────────────────────────────────────────────────────────────
    // 5. GET FOLLOW-UPS HISTORY TIMELINE FOR A VISIT
    // ──────────────────────────────────────────────────────────────────────────
    case 'get_followups':
        $visit_id = intval($_GET['visit_id'] ?? ($_POST['visit_id'] ?? 0));
        if (!$visit_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid visit ID']);
            exit;
        }

        $v_res = $conn->query("SELECT * FROM sales_visits WHERE id = {$visit_id}");
        $visit = $v_res ? $v_res->fetch_assoc() : null;
        if (!$visit) {
            echo json_encode(['success' => false, 'message' => 'Visit record not found']);
            exit;
        }

        // Fetch all follow-ups
        $f_stmt = $conn->prepare("SELECT * FROM sales_visit_followups WHERE visit_id = ? ORDER BY id DESC");
        $f_stmt->bind_param("i", $visit_id);
        $f_stmt->execute();
        $f_res = $f_stmt->get_result();

        $followups = [];
        while ($r = $f_res->fetch_assoc()) {
            $r['formatted_date']      = date('M d, Y h:i A', strtotime($r['created_at']));
            $r['time_ago']            = timeAgoVisits($r['created_at']);
            $r['formatted_next_date'] = $r['next_followup_date'] ? date('M d, Y h:i A', strtotime($r['next_followup_date'])) : null;
            $r['channel_label']       = match($r['contact_channel']) {
                'call'         => 'Phone Call',
                'whatsapp'     => 'WhatsApp Chat',
                'meeting'      => 'In-Person Meeting',
                'email'        => 'Email',
                'office_visit' => 'Office Visit',
                default        => ucfirst($r['contact_channel']),
            };
            $r['outcome_label']       = ucwords(str_replace('_', ' ', $r['outcome']));
            $followups[]              = $r;
        }
        $f_stmt->close();

        // Format visit metadata
        $visit['categories_arr']    = json_decode($visit['categories'] ?? '[]', true) ?: [];
        $visit['subcategories_arr'] = json_decode($visit['subcategories'] ?? '[]', true) ?: [];
        $visit['time_ago']          = timeAgoVisits($visit['created_at']);
        $visit['followup_status']   = getFollowupStatus($visit['next_followup_date'], $visit['status']);
        $visit['formatted_next_followup'] = $visit['next_followup_date'] ? date('M d, Y h:i A', strtotime($visit['next_followup_date'])) : null;

        echo json_encode([
            'success'   => true,
            'visit'     => $visit,
            'followups' => $followups,
            'count'     => count($followups)
        ]);
        exit;

    // ──────────────────────────────────────────────────────────────────────────
    // 6. COMPREHENSIVE REPORTING & PERFORMANCE METRICS
    // ──────────────────────────────────────────────────────────────────────────
    case 'get_reporting_metrics':
        $rep_id     = intval($_GET['rep_id'] ?? 0);
        $date_range = trim($_GET['date_range'] ?? 'all');
        $start_date = trim($_GET['start_date'] ?? '');
        $end_date   = trim($_GET['end_date'] ?? '');

        // Resolve date boundaries
        if ($date_range === 'today') {
            $start_date = date('Y-m-d 00:00:00');
            $end_date   = date('Y-m-d 23:59:59');
        } elseif ($date_range === 'yesterday') {
            $start_date = date('Y-m-d 00:00:00', strtotime('-1 day'));
            $end_date   = date('Y-m-d 23:59:59', strtotime('-1 day'));
        } elseif ($date_range === 'week') {
            $start_date = date('Y-m-d 00:00:00', strtotime('-7 days'));
            $end_date   = date('Y-m-d 23:59:59');
        } elseif ($date_range === 'month') {
            $start_date = date('Y-m-01 00:00:00');
            $end_date   = date('Y-m-d 23:59:59');
        } elseif ($date_range === 'last_month') {
            $start_date = date('Y-m-01 00:00:00', strtotime('first day of last month'));
            $end_date   = date('Y-m-t 23:59:59', strtotime('last day of last month'));
        } else {
            if (!empty($start_date)) $start_date .= " 00:00:00";
            if (!empty($end_date))   $end_date   .= " 23:59:59";
        }

        // Build WHERE clauses for visits
        $where_v = ["1=1"];
        if (!$is_admin) {
            $where_v[] = "sales_rep_id = " . intval($current_user_id);
        } elseif ($rep_id > 0) {
            $where_v[] = "sales_rep_id = " . intval($rep_id);
        }
        if (!empty($start_date)) {
            $where_v[] = "created_at >= '" . $conn->real_escape_string($start_date) . "'";
        }
        if (!empty($end_date)) {
            $where_v[] = "created_at <= '" . $conn->real_escape_string($end_date) . "'";
        }
        $where_v_sql = implode(" AND ", $where_v);

        // Build WHERE clauses for follow-ups
        $where_f = ["1=1"];
        if (!$is_admin) {
            $where_f[] = "user_id = " . intval($current_user_id);
        } elseif ($rep_id > 0) {
            $where_f[] = "user_id = " . intval($rep_id);
        }
        if (!empty($start_date)) {
            $where_f[] = "created_at >= '" . $conn->real_escape_string($start_date) . "'";
        }
        if (!empty($end_date)) {
            $where_f[] = "created_at <= '" . $conn->real_escape_string($end_date) . "'";
        }
        $where_f_sql = implode(" AND ", $where_f);

        // 1. Core KPIs
        $kpi_query = $conn->query("
            SELECT 
                COUNT(*) AS total_visits,
                SUM(CASE WHEN followup_count > 0 THEN 1 ELSE 0 END) AS visits_with_followup,
                SUM(followup_count) AS total_followups_on_visits,
                SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted_visits,
                SUM(CASE WHEN status IN ('contacted', 'in_discussion') THEN 1 ELSE 0 END) AS in_pipeline_visits,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_visits,
                SUM(CASE WHEN is_urgent = 1 AND status != 'closed' THEN 1 ELSE 0 END) AS urgent_open,
                SUM(CASE WHEN next_followup_date < NOW() AND status NOT IN ('converted', 'closed') THEN 1 ELSE 0 END) AS overdue_count,
                SUM(CASE WHEN DATE(next_followup_date) = CURDATE() AND status NOT IN ('converted', 'closed') THEN 1 ELSE 0 END) AS due_today_count,
                SUM(CASE WHEN next_followup_date > NOW() AND status NOT IN ('converted', 'closed') THEN 1 ELSE 0 END) AS upcoming_count
            FROM sales_visits
            WHERE {$where_v_sql}
        ")->fetch_assoc();

        $total_f_logged = intval($conn->query("SELECT COUNT(*) AS c FROM sales_visit_followups WHERE {$where_f_sql}")->fetch_assoc()['c'] ?? 0);

        $tot_v = intval($kpi_query['total_visits'] ?? 0);
        $conv_v = intval($kpi_query['converted_visits'] ?? 0);
        $conv_rate = ($tot_v > 0) ? round(($conv_v / $tot_v) * 100, 1) : 0;
        $v_with_f = intval($kpi_query['visits_with_followup'] ?? 0);
        $f_eng_rate = ($tot_v > 0) ? round(($v_with_f / $tot_v) * 100, 1) : 0;
        $avg_f = ($tot_v > 0) ? round($total_f_logged / $tot_v, 1) : 0;

        // 2. Sales Rep Leaderboard
        $reps_data = [];
        $rep_sql = "
            SELECT 
                v.sales_rep_id,
                v.sales_rep_name,
                COUNT(v.id) AS total_visits,
                SUM(v.followup_count) AS total_followups,
                SUM(CASE WHEN v.status = 'converted' THEN 1 ELSE 0 END) AS converted_count,
                SUM(CASE WHEN v.status IN ('contacted', 'in_discussion') THEN 1 ELSE 0 END) AS in_pipeline,
                MAX(v.created_at) AS last_visit_date,
                MAX(v.last_followup_at) AS last_followup_date
            FROM sales_visits v
            WHERE {$where_v_sql}
            GROUP BY v.sales_rep_id, v.sales_rep_name
            ORDER BY total_visits DESC, converted_count DESC
        ";
        $rep_res = $conn->query($rep_sql);
        if ($rep_res) {
            while ($r = $rep_res->fetch_assoc()) {
                $r_tot = intval($r['total_visits']);
                $r_conv = intval($r['converted_count']);
                $r['win_rate'] = ($r_tot > 0) ? round(($r_conv / $r_tot) * 100, 1) : 0;
                $r['last_activity'] = timeAgoVisits($r['last_followup_date'] ?: $r['last_visit_date']);
                $reps_data[] = $r;
            }
        }

        // 3. Outcome Breakdown
        $outcome_counts = [];
        $out_res = $conn->query("
            SELECT outcome, COUNT(*) AS count 
            FROM sales_visit_followups 
            WHERE {$where_f_sql}
            GROUP BY outcome 
            ORDER BY count DESC
        ");
        if ($out_res) {
            while ($r = $out_res->fetch_assoc()) {
                $outcome_counts[] = [
                    'outcome' => $r['outcome'],
                    'label'   => ucwords(str_replace('_', ' ', $r['outcome'])),
                    'count'   => intval($r['count']),
                    'percent' => ($total_f_logged > 0) ? round((intval($r['count']) / $total_f_logged) * 100, 1) : 0
                ];
            }
        }

        // 4. Contact Channel Breakdown
        $channel_counts = [];
        $ch_res = $conn->query("
            SELECT contact_channel, COUNT(*) AS count 
            FROM sales_visit_followups 
            WHERE {$where_f_sql}
            GROUP BY contact_channel 
            ORDER BY count DESC
        ");
        if ($ch_res) {
            while ($r = $ch_res->fetch_assoc()) {
                $lbl = match($r['contact_channel']) {
                    'call'         => 'Phone Call',
                    'whatsapp'     => 'WhatsApp',
                    'meeting'      => 'In-Person Meeting',
                    'email'        => 'Email',
                    'office_visit' => 'Office Visit',
                    default        => ucfirst($r['contact_channel']),
                };
                $channel_counts[] = [
                    'channel' => $r['contact_channel'],
                    'label'   => $lbl,
                    'count'   => intval($r['count']),
                    'percent' => ($total_f_logged > 0) ? round((intval($r['count']) / $total_f_logged) * 100, 1) : 0
                ];
            }
        }

        // 5. Service Demand Categories
        $category_counts = [];
        $cat_res = $conn->query("SELECT categories FROM sales_visits WHERE {$where_v_sql}");
        if ($cat_res) {
            $cat_map = [];
            while ($r = $cat_res->fetch_assoc()) {
                $arr = json_decode($r['categories'] ?? '[]', true) ?: [];
                foreach ($arr as $c) {
                    $c = trim($c);
                    if ($c) {
                        $cat_map[$c] = ($cat_map[$c] ?? 0) + 1;
                    }
                }
            }
            arsort($cat_map);
            foreach ($cat_map as $cname => $cnt) {
                $category_counts[] = [
                    'category' => $cname,
                    'count'    => $cnt,
                    'percent'  => ($tot_v > 0) ? round(($cnt / $tot_v) * 100, 1) : 0
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'kpis' => [
                'total_visits'          => $tot_v,
                'total_followups'       => $total_f_logged,
                'engagement_rate'       => $f_eng_rate,
                'avg_followups'         => $avg_f,
                'converted_visits'      => $conv_v,
                'conversion_rate'       => $conv_rate,
                'in_pipeline'           => intval($kpi_query['in_pipeline_visits'] ?? 0),
                'pending'               => intval($kpi_query['pending_visits'] ?? 0),
                'urgent_open'           => intval($kpi_query['urgent_open'] ?? 0),
                'overdue_count'         => intval($kpi_query['overdue_count'] ?? 0),
                'due_today_count'       => intval($kpi_query['due_today_count'] ?? 0),
                'upcoming_count'        => intval($kpi_query['upcoming_count'] ?? 0),
            ],
            'leaderboard'     => $reps_data,
            'outcomes'        => $outcome_counts,
            'channels'        => $channel_counts,
            'categories'      => $category_counts,
            'filter_metadata' => [
                'date_range' => $date_range,
                'start_date' => $start_date,
                'end_date'   => $end_date,
                'rep_id'     => $rep_id
            ]
        ]);
        exit;

    // ──────────────────────────────────────────────────────────────────────────
    // 7. TOGGLE URGENT STATUS
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
            'message'   => $new_urgent ? 'Marked as URGENT follow-up!' : 'Urgent priority removed.'
        ]);
        exit;

    // ──────────────────────────────────────────────────────────────────────────
    // 8. UPDATE VISIT STATUS
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
    // 9. CONVERT TO CRM LEAD
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

        $lead_id = convertVisitToLeadInternal($conn, $v, $current_user_id, $current_user_name);

        if ($lead_id) {
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
    // 10. DELETE VISIT
    // ──────────────────────────────────────────────────────────────────────────
    case 'delete':
        if (!$is_admin) {
            echo json_encode(['success' => false, 'message' => 'Only administrators can delete visits']);
            exit;
        }
        $visit_id = intval($_POST['visit_id'] ?? 0);
        if ($visit_id) {
            $conn->query("DELETE FROM sales_visits WHERE id = {$visit_id}");
            $conn->query("DELETE FROM sales_visit_followups WHERE visit_id = {$visit_id}");
            echo json_encode(['success' => true, 'message' => 'Visit record and follow-ups deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid visit ID']);
        }
        exit;

    // ──────────────────────────────────────────────────────────────────────────
    // 11. GET SALES REPS FOR FILTER / EXPORT
    // ──────────────────────────────────────────────────────────────────────────
    case 'get_reps':
        $reps = [];
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
    // 12. LOG LEGACY RESPONSE INTERACTION
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
    // 13. EXPORT DATA TO SPREADSHEET (CSV / EXCEL COMPATIBLE)
    // ──────────────────────────────────────────────────────────────────────────
    case 'export_csv':
    case 'export_sheet':
        $export_type = trim($_GET['export_type'] ?? 'visits');
        $rep_id      = intval($_GET['rep_id'] ?? 0);
        $start_date  = trim($_GET['start_date'] ?? '');
        $end_date    = trim($_GET['end_date'] ?? '');
        $status      = trim($_GET['status'] ?? 'all');
        $interest    = trim($_GET['interest'] ?? 'all');
        $urgent_only = intval($_GET['urgent_only'] ?? 0);

        // Clear output buffer
        while (ob_get_level() > 0) ob_end_clean();

        // Send Excel CSV headers
        $filename = ($export_type === 'followup_history')
            ? "sales_followup_history_" . date('Y-m-d_His') . ".csv"
            : "sales_visits_report_" . date('Y-m-d_His') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // UTF-8 BOM for Excel
        echo "\xEF\xBB\xBF";
        $output = fopen('php://output', 'w');

        if ($export_type === 'followup_history') {
            // Detailed Follow-up Touchpoints Export
            fputcsv($output, [
                'Follow-up ID',
                'Visit ID',
                'Date Logged',
                'Logged By Agent',
                'Company / Business',
                'Client Name',
                'Phone',
                'Channel (Method)',
                'Outcome / Response',
                'Next Scheduled Follow-up',
                'Detailed Follow-up Discussion Notes'
            ]);

            $where_f = ["1=1"];
            $params_f = [];
            $types_f  = "";

            if (!$is_admin) {
                $where_f[]  = "f.user_id = ?";
                $params_f[] = $current_user_id;
                $types_f   .= "i";
            } elseif ($rep_id > 0) {
                $where_f[]  = "f.user_id = ?";
                $params_f[] = $rep_id;
                $types_f   .= "i";
            }

            if (!empty($start_date)) {
                $where_f[]  = "f.created_at >= ?";
                $params_f[] = $start_date . " 00:00:00";
                $types_f   .= "s";
            }

            if (!empty($end_date)) {
                $where_f[]  = "f.created_at <= ?";
                $params_f[] = $end_date . " 23:59:59";
                $types_f   .= "s";
            }

            $sql_f = "
                SELECT 
                    f.*, 
                    v.company_name, 
                    v.client_name, 
                    v.phone
                FROM sales_visit_followups f
                LEFT JOIN sales_visits v ON v.id = f.visit_id
                WHERE " . implode(" AND ", $where_f) . "
                ORDER BY f.id DESC
            ";
            $stmt_f = $conn->prepare($sql_f);
            if (!empty($types_f)) {
                $stmt_f->bind_param($types_f, ...$params_f);
            }
            $stmt_f->execute();
            $res_f = $stmt_f->get_result();

            while ($r = $res_f->fetch_assoc()) {
                fputcsv($output, [
                    $r['id'],
                    $r['visit_id'],
                    date('Y-m-d h:i A', strtotime($r['created_at'])),
                    $r['user_name'],
                    $r['company_name'] ?? 'N/A',
                    $r['client_name'] ?? 'N/A',
                    $r['phone'] ?? '',
                    ucfirst($r['contact_channel']),
                    ucwords(str_replace('_', ' ', $r['outcome'])),
                    $r['next_followup_date'] ? date('Y-m-d h:i A', strtotime($r['next_followup_date'])) : 'None',
                    $r['notes']
                ]);
            }
            $stmt_f->close();

        } else {
            // Master Visits & Pipeline Status Export
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
                'Pipeline Status',
                'Follow-ups Completed',
                'Last Follow-up Date',
                'Last Follow-up By',
                'Last Follow-up Outcome',
                'Next Scheduled Follow-up',
                'Follow-up Status Indicator',
                'Preferred Contact Window',
                'Services Discussed',
                'Subcategories',
                'Field Rep Remarks',
                'Quick Remark',
                'Complete Notes Trail'
            ]);

            $where = ["1=1"];
            $params = [];
            $types  = "";

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
                    'converted'     => 'Converted to CRM Lead',
                    'closed'        => 'Closed / Resolved',
                    default         => 'Pending Contact',
                };

                $fo_indicator = match (getFollowupStatus($r['next_followup_date'], $r['status'])) {
                    'overdue'   => 'OVERDUE',
                    'due_today' => 'Due Today',
                    'upcoming'  => 'Upcoming Scheduled',
                    'completed' => 'Completed / Closed',
                    default     => 'No Schedule Set',
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
                    $r['followup_count'],
                    $r['last_followup_at'] ? date('Y-m-d h:i A', strtotime($r['last_followup_at'])) : '',
                    $r['last_followup_by'] ?? '',
                    $r['last_outcome'] ? ucwords(str_replace('_', ' ', $r['last_outcome'])) : '',
                    $r['next_followup_date'] ? date('Y-m-d h:i A', strtotime($r['next_followup_date'])) : '',
                    $fo_indicator,
                    $r['contact_availability'],
                    implode(', ', $cat_arr),
                    implode(', ', $subcat_arr),
                    $r['remarks'] ?? '',
                    $r['quick_remark'] ?? '',
                    $r['admin_notes'] ?? ''
                ]);
            }
            $stmt->close();
        }

        fclose($output);
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
}

// ──────────────────────────────────────────────────────────────────────────────
// Helper Functions
// ──────────────────────────────────────────────────────────────────────────────

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

function getFollowupStatus($next_date, $status) {
    if (in_array($status, ['converted', 'closed'])) return 'completed';
    if (empty($next_date)) return 'none';
    $ts = strtotime($next_date);
    $today_start = strtotime('today 00:00:00');
    $today_end   = strtotime('today 23:59:59');
    $now         = time();

    if ($ts < $now && $ts < $today_start) {
        return 'overdue';
    } elseif ($ts >= $today_start && $ts <= $today_end) {
        return 'due_today';
    } elseif ($ts < $now) {
        return 'overdue';
    } else {
        return 'upcoming';
    }
}

function getSalesVisitsStats($conn) {
    $row = $conn->query("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN is_urgent = 1 AND status != 'closed' THEN 1 ELSE 0 END) AS urgent_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN interest_level = 'hot' AND status != 'closed' THEN 1 ELSE 0 END) AS hot_count,
            SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) AS contacted_count,
            SUM(CASE WHEN status = 'in_discussion' THEN 1 ELSE 0 END) AS in_discussion_count,
            SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS converted_count,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_count,
            SUM(CASE WHEN next_followup_date < NOW() AND status NOT IN ('converted', 'closed') THEN 1 ELSE 0 END) AS overdue_count,
            SUM(CASE WHEN DATE(next_followup_date) = CURDATE() AND status NOT IN ('converted', 'closed') THEN 1 ELSE 0 END) AS due_today_count,
            SUM(CASE WHEN next_followup_date > NOW() AND status NOT IN ('converted', 'closed') THEN 1 ELSE 0 END) AS upcoming_count,
            SUM(followup_count) AS total_followups,
            MAX(id) AS latest_id
        FROM sales_visits
    ")->fetch_assoc();

    return [
        'total'         => intval($row['total'] ?? 0),
        'urgent'        => intval($row['urgent_count'] ?? 0),
        'pending'       => intval($row['pending_count'] ?? 0),
        'hot'           => intval($row['hot_count'] ?? 0),
        'contacted'     => intval($row['contacted_count'] ?? 0),
        'in_discussion' => intval($row['in_discussion_count'] ?? 0),
        'converted'     => intval($row['converted_count'] ?? 0),
        'closed'        => intval($row['closed_count'] ?? 0),
        'overdue'       => intval($row['overdue_count'] ?? 0),
        'due_today'     => intval($row['due_today_count'] ?? 0),
        'upcoming'      => intval($row['upcoming_count'] ?? 0),
        'followups'     => intval($row['total_followups'] ?? 0),
        'latest_id'     => intval($row['latest_id'] ?? 0),
    ];
}

function convertVisitToLeadInternal($conn, $v, $current_user_id, $current_user_name) {
    $cat_arr = json_decode($v['categories'] ?? '[]', true) ?: [];
    $subcat_arr = json_decode($v['subcategories'] ?? '[]', true) ?: [];
    $all_interests = array_merge($cat_arr, $subcat_arr);
    $service_interest = implode(', ', array_unique($all_interests));

    $lead_priority = match ($v['interest_level']) {
        'hot'   => 'high',
        'cold'  => 'low',
        default => 'medium',
    };

    $notes = "Converted from Sales Visit (ID #{$v['id']}).\nLogged by: {$v['sales_rep_name']}.\nAvailability: {$v['contact_availability']}.\nQuick Remark: {$v['quick_remark']}.\nRemarks: {$v['remarks']}\nHistory:\n{$v['admin_notes']}";

    $stmt = $conn->prepare("INSERT INTO leads 
        (name, company, phone, source, service_interest, pipeline_stage, priority, notes, assigned_to, created_at)
        VALUES (?, ?, ?, 'walk_in', ?, 'contacted', ?, ?, ?, NOW())");
    if (!$stmt) return null;

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
        $conn->query("UPDATE sales_visits SET status = 'converted', converted_lead_id = {$lead_id} WHERE id = {$v['id']}");
        return $lead_id;
    }
    return null;
}
