<?php
// Hide errors to prevent visual 500 crashes
error_reporting(0);
ini_set('display_errors', '0');

include 'dashboard_header.php';

$is_admin = ($role === 'admin' || $role === 'manager');
$user_id  = $_SESSION['user_id'] ?? 0;

// ── Configuration ─────────────────────────────────────────────────────────────
// Array of allowed Office IPs (including localhost for XAMPP testing, supports wildcards* and CIDR ranges)
$office_ips = [
    '2402:e000:6ad:80d8:c46f:951c:5385:fcd9', // Original Public IPv6
    '127.0.0.1',                              // Localhost IPv4
    '::1'                                     // Localhost IPv6
]; 
$late_cutoff_time = '09:30:00'; // 9:30 AM

// Helper function to check if IP matches whitelists/wildcards/CIDRs
function isIpInRange($user_ip, $allowed_ips) {
    $user_ip = trim($user_ip);
    if (empty($user_ip)) return false;
    
    foreach ($allowed_ips as $allowed) {
        $allowed = trim($allowed);
        if (empty($allowed)) continue;
        
        // Exact match
        if ($allowed === $user_ip) {
            return true;
        }
        
        // Wildcard match (e.g. 192.168.1.* or 2402:e000:6ad:80d8:*)
        if (strpos($allowed, '*') !== false) {
            $prefix = str_replace('*', '', $allowed);
            if (strpos($user_ip, $prefix) === 0) {
                return true;
            }
        }
        
        // CIDR notation (e.g. 192.168.1.0/24 or 2402:e000:6ad:80d8::/64)
        if (strpos($allowed, '/') !== false) {
            list($subnet, $bits) = explode('/', $allowed);
            $bits = intval($bits);
            
            // IPv4 CIDR
            if (filter_var($user_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && 
                filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ip_dec = ip2long($user_ip);
                $subnet_dec = ip2long($subnet);
                $mask = ~((1 << (32 - $bits)) - 1);
                if (($ip_dec & $mask) === ($subnet_dec & $mask)) {
                    return true;
                }
            } 
            // IPv6 CIDR
            elseif (filter_var($user_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && 
                    filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $user_bytes = inet_pton($user_ip);
                $subnet_bytes = inet_pton($subnet);
                if ($user_bytes === false || $subnet_bytes === false) continue;
                
                $user_hex = unpack("H*", $user_bytes)[1];
                $subnet_hex = unpack("H*", $subnet_bytes)[1];
                $chars_to_match = ceil($bits / 4);
                if (substr($user_hex, 0, $chars_to_match) === substr($subnet_hex, 0, $chars_to_match)) {
                    return true;
                }
            }
        }
    }
    return false;
}

// Default to current month if no filter is applied
$filter_month = $_GET['month'] ?? date('Y-m');

$toast_message = '';
$toast_type    = '';

if (isset($_SESSION['toast_msg'])) {
    $toast_message = $_SESSION['toast_msg'];
    $toast_type    = $_SESSION['toast_type'] ?? 'success';
    unset($_SESSION['toast_msg'], $_SESSION['toast_type']);
}

// ── Actions POST handling ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_admin) {
        header("Location: attendance.php");
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_override') {
        $target_user_id  = intval($_POST['user_id'] ?? 0);
        $attendance_date = $_POST['attendance_date'] ?? '';
        $attendance_type = $_POST['attendance_type'] ?? '';
        $reason          = trim($_POST['reason'] ?? '');
        $approved_by     = $user_id;
        
        if ($target_user_id > 0 && !empty($attendance_date) && !empty($attendance_type)) {
            // Check if duplicate override exists
            $dup_stmt = $conn->prepare("SELECT id FROM attendance_overrides WHERE user_id = ? AND attendance_date = ?");
            $dup_stmt->bind_param("is", $target_user_id, $attendance_date);
            $dup_stmt->execute();
            $dup_res = $dup_stmt->get_result()->fetch_assoc();
            
            if ($dup_res) {
                // Update existing override
                $upd_stmt = $conn->prepare("UPDATE attendance_overrides SET attendance_type = ?, reason = ?, approved_by = ? WHERE id = ?");
                $upd_stmt->bind_param("ssii", $attendance_type, $reason, $approved_by, $dup_res['id']);
                if ($upd_stmt->execute()) {
                    $_SESSION['toast_msg']  = 'Attendance override updated successfully!';
                    $_SESSION['toast_type'] = 'success';
                } else {
                    $_SESSION['toast_msg']  = 'Failed to update attendance override.';
                    $_SESSION['toast_type'] = 'error';
                }
            } else {
                // Insert new override
                $ins_stmt = $conn->prepare("INSERT INTO attendance_overrides (user_id, attendance_date, attendance_type, reason, approved_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $ins_stmt->bind_param("isssi", $target_user_id, $attendance_date, $attendance_type, $reason, $approved_by);
                if ($ins_stmt->execute()) {
                    $_SESSION['toast_msg']  = 'Attendance override added successfully!';
                    $_SESSION['toast_type'] = 'success';
                } else {
                    $_SESSION['toast_msg']  = 'Failed to add attendance override.';
                    $_SESSION['toast_type'] = 'error';
                }
            }
        } else {
            $_SESSION['toast_msg']  = 'All fields are required.';
            $_SESSION['toast_type'] = 'error';
        }
        header("Location: attendance.php" . (isset($_GET['month']) ? "?month={$_GET['month']}" : ""));
        exit;
    } elseif ($action === 'delete_override') {
        $override_id = intval($_POST['override_id'] ?? 0);
        if ($override_id > 0) {
            $del_stmt = $conn->prepare("DELETE FROM attendance_overrides WHERE id = ?");
            $del_stmt->bind_param("i", $override_id);
            if ($del_stmt->execute()) {
                $_SESSION['toast_msg']  = 'Attendance override removed successfully!';
                $_SESSION['toast_type'] = 'success';
            } else {
                $_SESSION['toast_msg']  = 'Failed to remove override.';
                $_SESSION['toast_type'] = 'error';
            }
        }
        header("Location: attendance.php" . (isset($_GET['month']) ? "?month={$_GET['month']}" : ""));
        exit;
    }
}

// ── Query & Merge Logs with Overrides ──────────────────────────────────────────
$attendance_records = [];
$stat_present = 0;
$stat_late    = 0;
$stat_onsite  = 0;
$stat_remote  = 0;
$stat_field   = 0;
$stat_leave   = 0;

// Fetch active users list (for admin override dropdown)
$users_list = [];
if ($is_admin) {
    $u_res = $conn->query("SELECT user_id, name, username, role FROM users WHERE status = 'active' ORDER BY name ASC");
    if ($u_res) {
        while ($u_row = $u_res->fetch_assoc()) {
            $users_list[] = $u_row;
        }
    }
}

// Fetch Overrides for current month
$overrides = [];
if ($is_admin) {
    $stmt_ov = $conn->prepare("
        SELECT o.*, u.name, u.username
        FROM attendance_overrides o
        LEFT JOIN users u ON CAST(o.user_id AS UNSIGNED) = CAST(u.user_id AS UNSIGNED)
        WHERE DATE_FORMAT(o.attendance_date, '%Y-%m') = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
    ");
    if ($stmt_ov) {
        $stmt_ov->bind_param("s", $filter_month);
    }
} else {
    $stmt_ov = $conn->prepare("
        SELECT o.*, u.name, u.username
        FROM attendance_overrides o
        LEFT JOIN users u ON CAST(o.user_id AS UNSIGNED) = CAST(u.user_id AS UNSIGNED)
        WHERE CAST(o.user_id AS UNSIGNED) = CAST(? AS UNSIGNED)
          AND DATE_FORMAT(o.attendance_date, '%Y-%m') = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
    ");
    if ($stmt_ov) {
        $user_id_str = (string)$user_id;
        $stmt_ov->bind_param("ss", $user_id_str, $filter_month);
    }
}

if ($stmt_ov && $stmt_ov->execute()) {
    $res_ov = $stmt_ov->get_result();
    if ($res_ov) {
        while ($row = $res_ov->fetch_assoc()) {
            $key = $row['user_id'] . '_' . $row['attendance_date'];
            $overrides[$key] = $row;
        }
    }
}

// Fetch Logins (Exact first login per user per day)
$logins = [];
if ($is_admin) {
    $stmt_log = $conn->prepare("
        SELECT l.user_id, l.login_time, l.ip_address, u.name, u.username
        FROM login_logs l
        LEFT JOIN users u ON CAST(l.user_id AS UNSIGNED) = CAST(u.user_id AS UNSIGNED)
        WHERE l.status = 'success' 
          AND DATE_FORMAT(l.login_time, '%Y-%m') = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
          AND l.login_time = (
              SELECT MIN(login_time) 
              FROM login_logs l2 
              WHERE CAST(l2.user_id AS UNSIGNED) = CAST(l.user_id AS UNSIGNED) 
                AND DATE(l2.login_time) = DATE(l.login_time) 
                AND l2.status = 'success'
          )
        ORDER BY l.login_time DESC
    ");
    if ($stmt_log) {
        $stmt_log->bind_param("s", $filter_month);
    }
} else {
    $stmt_log = $conn->prepare("
        SELECT l.user_id, l.login_time, l.ip_address, u.name, u.username
        FROM login_logs l
        LEFT JOIN users u ON CAST(l.user_id AS UNSIGNED) = CAST(u.user_id AS UNSIGNED)
        WHERE l.status = 'success' 
          AND CAST(l.user_id AS UNSIGNED) = CAST(? AS UNSIGNED)
          AND DATE_FORMAT(l.login_time, '%Y-%m') = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
          AND l.login_time = (
              SELECT MIN(login_time) 
              FROM login_logs l2 
              WHERE CAST(l2.user_id AS UNSIGNED) = CAST(l.user_id AS UNSIGNED) 
                AND DATE(l2.login_time) = DATE(l.login_time) 
                AND l2.status = 'success'
          )
        ORDER BY l.login_time DESC
    ");
    if ($stmt_log) {
        $user_id_str = (string)$user_id;
        $stmt_log->bind_param("ss", $user_id_str, $filter_month);
    }
}

$final_records = [];

if ($stmt_log && $stmt_log->execute()) {
    $res_log = $stmt_log->get_result();
    if ($res_log) {
        while ($row = $res_log->fetch_assoc()) {
            $login_date = date('Y-m-d', strtotime($row['login_time']));
            $key = $row['user_id'] . '_' . $login_date;
            
            $row['attendance_date'] = $login_date;
            $row['is_override'] = false;
            
            $final_records[$key] = $row;
        }
    }
}

// Merge Overrides
foreach ($overrides as $key => $ov) {
    if (isset($final_records[$key])) {
        $final_records[$key]['is_override'] = true;
        $final_records[$key]['override_id'] = $ov['id'];
        $final_records[$key]['attendance_type'] = $ov['attendance_type'];
        $final_records[$key]['reason'] = $ov['reason'];
    } else {
        $final_records[$key] = [
            'user_id' => $ov['user_id'],
            'login_time' => null,
            'ip_address' => null,
            'name' => $ov['name'],
            'username' => $ov['username'],
            'attendance_date' => $ov['attendance_date'],
            'is_override' => true,
            'override_id' => $ov['id'],
            'attendance_type' => $ov['attendance_type'],
            'reason' => $ov['reason']
        ];
    }
}

// Sort records by date descending
uasort($final_records, function($a, $b) {
    return strcmp($b['attendance_date'], $a['attendance_date']);
});

// Calculate stats and format display variables
foreach ($final_records as $row) {
    if ($row['is_override']) {
        switch ($row['attendance_type']) {
            case 'onsite':
                $row['att_status'] = 'Present';
                $row['att_class']  = 'present';
                $row['loc_text']   = 'On-site (Override)';
                $row['loc_class']  = 'loc-onsite';
                $row['loc_icon']   = '<i class="fas fa-building"></i>';
                $stat_present++;
                $stat_onsite++;
                break;
            case 'remote':
                $row['att_status'] = 'Present';
                $row['att_class']  = 'present';
                $row['loc_text']   = 'Remote (Override)';
                $row['loc_class']  = 'loc-remote';
                $row['loc_icon']   = '🏠';
                $stat_present++;
                $stat_remote++;
                break;
            case 'field_work':
                $row['att_status'] = 'Field Work';
                $row['att_class']  = 'field-work';
                $row['loc_text']   = 'Field Work';
                $row['loc_class']  = 'loc-field-work';
                $row['loc_icon']   = '💼';
                $stat_present++;
                $stat_field++;
                break;
            case 'leave':
                $row['att_status'] = 'On Leave';
                $row['att_class']  = 'leave';
                $row['loc_text']   = '—';
                $row['loc_class']  = 'loc-leave';
                $row['loc_icon']   = '🌴';
                $stat_leave++;
                break;
        }
    } else {
        $login_timestamp = strtotime($row['login_time']);
        $time_only = date('H:i:s', $login_timestamp);
        
        if ($time_only <= $late_cutoff_time) {
            $row['att_status'] = 'Present';
            $row['att_class']  = 'present';
            $stat_present++;
        } else {
            $row['att_status'] = 'Late';
            $row['att_class']  = 'late';
            $stat_late++;
        }

        $user_ip = trim($row['ip_address'] ?? '');
        if (isIpInRange($user_ip, $office_ips)) {
            $row['loc_text']  = 'On-site';
            $row['loc_class'] = 'loc-onsite';
            $row['loc_icon']  = '<i class="fas fa-building"></i>';
            $stat_onsite++;
        } else {
            $row['loc_text']  = 'Remote';
            $row['loc_class'] = 'loc-remote';
            $row['loc_icon']  = '🏠';
            $stat_remote++;
        }
    }
    
    if ($row['login_time']) {
        $login_timestamp = strtotime($row['login_time']);
        $row['date_str'] = date('d M Y', $login_timestamp);
        $row['time_str'] = date('h:i A', $login_timestamp);
    } else {
        $row['date_str'] = date('d M Y', strtotime($row['attendance_date']));
        $row['time_str'] = '—';
    }
    
    $attendance_records[] = $row;
}

$total_records = count($attendance_records);
?>

<style>
/* ── UI/UX OVERHAUL STYLES ── */
.main-card { 
    background: #fff; 
    border-radius: 16px; 
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 10px 25px -3px rgba(0, 0, 0, 0.04); 
    overflow: hidden; 
    border: 1px solid #f0f3f2; 
}

.page-top-actions { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    flex-wrap: wrap;
    gap: 16px;
    padding: 24px 28px; 
    border-bottom: 1px solid #f4f5f4; 
    background: #fff; 
}

.page-top-actions h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary);
}

.filter-bar {
    display: flex;
    align-items: center;
    gap: 12px;
}

.filter-bar input[type="month"] {
    padding: 8px 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    font-family: inherit;
    font-size: 13.5px;
    color: #333;
    outline: none;
    background: #f8fafc;
}

.filter-bar input[type="month"]:focus {
    border-color: var(--primary);
}

.table-head {
    background: #f8faf9;
    padding: 16px 28px;
    border-bottom: 2px solid rgba(0,0,0,0.03);
    font-size: 0.8rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #64748b;
    display: grid;
    grid-template-columns: <?php echo $is_admin ? '1.8fr 1.2fr 1fr 1.2fr 1.2fr 1fr 0.6fr' : '1.2fr 1fr 1.2fr 1.2fr 1fr'; ?>;
    gap: 16px;
}

.table-row {
    padding: 16px 28px;
    border-bottom: 1px solid #f1f5f9;
    display: grid;
    grid-template-columns: <?php echo $is_admin ? '1.8fr 1.2fr 1fr 1.2fr 1.2fr 1fr 0.6fr' : '1.2fr 1fr 1.2fr 1.2fr 1fr'; ?>;
    gap: 16px;
    align-items: center;
    transition: background 0.2s ease;
}
.table-row:hover { background: #fbfdfc; }

/* Attendance Badges */
.status.present { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; text-align: center; }
.status.late    { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; text-align: center; }
.status.field-work { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; text-align: center; }
.status.leave   { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; text-align: center; }

/* Location Badges */
.loc-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; padding: 5px 12px; border-radius: 8px; }
.loc-onsite { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.loc-remote { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
.loc-field-work { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.loc-leave { background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0; }

.user-chip { display: flex; align-items: center; gap: 12px; }
.user-chip .mini-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg, var(--accent, #b8f35a), #87bd0a);
    color: var(--primary); font-size: 14px; font-weight: 800;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(184,243,90,0.3);
}
.user-chip small { color: #888; font-size: 12px; display: block; font-weight: 500; margin-top: 2px;}

/* Stats cards styling */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}
.stat-card {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    border: 1px solid #f0f3f2;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 10px 25px -3px rgba(0, 0, 0, 0.04);
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.stat-info {
    display: flex;
    flex-direction: column;
}
.stat-number {
    font-size: 24px;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 4px;
}
.stat-label {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
}

/* Modals layout */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-overlay.active {
    display: flex;
}
.modal-content {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    animation: modalSlideUp 0.3s ease-out;
}
@keyframes modalSlideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.modal-header {
    background: var(--primary-color);
    color: #fff;
    padding: 20px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header h4 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}
.modal-header-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: rgba(255,255,255,0.15);
    border-radius: 8px;
    font-size: 14px;
}
.modal-close {
    background: none;
    border: none;
    color: rgba(255,255,255,0.7);
    font-size: 24px;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    transition: color 0.2s;
}
.modal-close:hover {
    color: #fff;
}
.modal-body {
    padding: 24px;
}
.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: #fbfcfc;
}
.form-group {
    margin-bottom: 18px;
}
.form-group label {
    display: block;
    font-weight: 600;
    font-size: 13px;
    color: #334155;
    margin-bottom: 6px;
}
.form-group input, .form-group select {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    font-family: inherit;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s;
    background-color: #fff;
}
.form-group input:focus, .form-group select:focus {
    border-color: var(--primary-color);
}
.dots {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: #f1f5f9;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}
.dots:hover {
    background: #e2e8f0;
    color: #0f172a;
}
</style>

<div class="height-100">

    <?php if (isset($db_error)): ?>
    <div style="background:#fee2e2; border:1px solid #fca5a5; color:#dc2626; padding:16px 20px; border-radius:12px; margin-bottom:24px; font-weight:600;">
        <i class="fas fa-exclamation-triangle"></i> System Error: <?php echo htmlspecialchars($db_error); ?>
    </div>
    <?php endif; ?>

    <!-- Stats row -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-number" style="color:#16a34a;"><?php echo $stat_present; ?></div>
                <div class="stat-label">On Time</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fee2e2;color:#dc2626;">⏰</div>
            <div class="stat-info">
                <div class="stat-number" style="color:#dc2626;"><?php echo $stat_late; ?></div>
                <div class="stat-label">Late Logins</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#e0f2fe;color:#0369a1;"><i class="fas fa-building"></i></div>
            <div class="stat-info">
                <div class="stat-number" style="color:#0369a1;"><?php echo ($stat_onsite + $stat_field); ?></div>
                <div class="stat-label">On-site / Field</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#f1f5f9;color:#475569;">🌴</div>
            <div class="stat-info">
                <div class="stat-number" style="color:#475569;"><?php echo $stat_leave; ?></div>
                <div class="stat-label">Approved Leaves</div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="main-card">
        <div class="page-top-actions">
            <div>
                <h2><i class="fas fa-calendar-alt"></i> <?php echo $is_admin ? 'Team Attendance Register' : 'My Attendance Record'; ?></h2>
                <span style="font-size:13.5px; color:#64748b; font-weight: 500;">Showing integrated login logs and approved overrides.</span>
            </div>
            
            <div class="filter-bar">
                <form method="GET" style="display: flex; gap: 8px; align-items: center; margin: 0;">
                    <input type="month" name="month" value="<?php echo htmlspecialchars($filter_month); ?>" onchange="this.form.submit()">
                </form>
                <?php if ($is_admin): ?>
                    <button class="btn-primary-custom" onclick="openOverrideModal()" style="padding: 9px 18px; border-radius: 8px; font-size: 13px; border: none; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fas fa-plus-circle"></i> Add Override
                    </button>
                <?php endif; ?>
                <button class="btn-secondary-custom" onclick="window.print()" style="padding: 9px 18px; border-radius: 8px; font-size: 13px;">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>

        <div class="main-table">
            <div class="table-head">
                <?php if ($is_admin): ?>
                <span>Employee</span>
                <?php endif; ?>
                <span>Date</span>
                <span>Time In</span>
                <span>IP Address</span>
                <span>Location</span>
                <span>Status</span>
                <?php if ($is_admin): ?>
                <span>Actions</span>
                <?php endif; ?>
            </div>

            <?php if ($total_records === 0): ?>
            <div style="text-align:center;padding:80px 24px;color:#888;">
                <div style="font-size:3rem;margin-bottom:12px;"><i class="fas fa-envelope-open"></i></div>
                <h4 style="color: #333; font-weight: 700;">No Records Found</h4>
                <p style="margin:0;">No attendance records found for <?php echo date('F Y', strtotime($filter_month . '-01')); ?>.</p>
            </div>
            <?php else: ?>
            <?php foreach ($attendance_records as $row): ?>
            <div class="table-row">
                <?php if ($is_admin): ?>
                <div class="user-chip">
                    <div class="mini-avatar"><?php echo strtoupper(substr($row['name'], 0, 1)); ?></div>
                    <div>
                        <strong style="font-size:14px; color: var(--primary);"><?php echo htmlspecialchars($row['name']); ?></strong>
                        <small>@<?php echo htmlspecialchars($row['username']); ?></small>
                    </div>
                </div>
                <?php endif; ?>
                
                <span style="font-weight: 600; color: #475569; font-size: 13.5px;">
                    <i class="far fa-calendar-alt"></i> <?php echo $row['date_str']; ?>
                </span>
                
                <span style="font-weight: 800; font-size: 14px; color: <?php echo ($row['att_status'] === 'Late' || $row['att_status'] === 'On Leave') ? '#dc2626' : '#16a34a'; ?>;">
                    <?php echo $row['time_str']; ?>
                </span>
                
                <span style="font-family:monospace; font-size:12px; color:#64748b; background: #f8fafc; padding: 4px 8px; border-radius: 6px; border: 1px solid #e2e8f0; display: inline-block; text-align: center; width: fit-content;">
                    <?php echo htmlspecialchars($row['ip_address'] ?? '—'); ?>
                </span>
                
                <span>
                    <span class="loc-badge <?php echo $row['loc_class']; ?>">
                        <?php echo $row['loc_icon'] . ' ' . $row['loc_text']; ?>
                    </span>
                </span>
                
                <span>
                    <span class="status <?php echo $row['att_class']; ?>"><?php echo $row['att_status']; ?></span>
                    <?php if (!empty($row['reason'])): ?>
                        <br><small style="color: #64748b; font-size: 11px; display: block; white-space: normal; line-height: 1.2; margin-top: 4px;" title="<?= htmlspecialchars($row['reason']) ?>">
                            💬 <?= htmlspecialchars($row['reason']) ?>
                        </small>
                    <?php endif; ?>
                </span>

                <?php if ($is_admin): ?>
                <div>
                    <?php if ($row['is_override']): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this attendance override?');">
                        <input type="hidden" name="action" value="delete_override">
                        <input type="hidden" name="override_id" value="<?= $row['override_id'] ?>">
                        <button type="submit" class="dots" title="Delete Override" style="color:#ef4444;"><i class="fas fa-trash-alt"></i></button>
                    </form>
                    <?php else: ?>
                    <span style="color:#cbd5e1;">—</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Override Modal (Admin Only) -->
<?php if ($is_admin): ?>
<div id="overrideModal" class="modal-overlay" onclick="if(event.target===this)closeModal('overrideModal')">
    <div class="modal-content">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon"><i class="fas fa-plus-circle"></i></div>
                <span id="modalTitle">Add Attendance Override</span>
            </h4>
            <button class="modal-close" onclick="closeModal('overrideModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_override">
            <div class="modal-body">
                <!-- Employee -->
                <div class="form-group">
                    <label>Select Employee <span style="color:#ef4444;">*</span></label>
                    <select name="user_id" required>
                        <option value="">— Select Employee —</option>
                        <?php foreach ($users_list as $u): ?>
                            <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['name']) ?> (@<?= htmlspecialchars($u['username']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date -->
                <div class="form-group">
                    <label>Attendance Date <span style="color:#ef4444;">*</span></label>
                    <input type="date" name="attendance_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <!-- Type -->
                <div class="form-group">
                    <label>Attendance Type <span style="color:#ef4444;">*</span></label>
                    <select name="attendance_type" required>
                        <option value="onsite">On-site (Present)</option>
                        <option value="remote">Remote (Present)</option>
                        <option value="field_work">Field Work</option>
                        <option value="leave">On Leave</option>
                    </select>
                </div>

                <!-- Reason -->
                <div class="form-group">
                    <label>Reason / Notes</label>
                    <textarea name="reason" placeholder="e.g. Official field visit to client site, Approved casual leave, etc." style="width:100%; min-height:80px; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:8px; font-family:inherit; font-size:14px; outline:none; transition:border-color 0.2s; resize:vertical;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeModal('overrideModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-custom"><i class="fas fa-save"></i> Save Override</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Toast container -->
<div id="toastContainer" style="position:fixed; bottom:24px; right:24px; z-index:9999; display:flex; flex-direction:column; gap:8px;"></div>

<script>
function openOverrideModal() {
    openModal('overrideModal');
}
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
    document.body.style.overflow = '';
}

function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.style.cssText = `background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:240px;border-left:4px solid ${type==='success'?'#10b981':'#ef4444'};`;
    t.innerHTML = `<span style="font-weight:700;color:${type==='success'?'#10b981':'#ef4444'}">${type==='success'?'<i class="fas fa-check"></i>':'<i class="fas fa-times"></i>'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3000);
}
</script>

<?php if ($toast_message): ?>
<script>
document.addEventListener('DOMContentLoaded', () => showToast(<?= json_encode($toast_message) ?>, <?= json_encode($toast_type) ?>));
</script>
<?php endif; ?>

<?php
if ($stmt) $stmt->close();
if ($stmt_ov) $stmt_ov->close();
if ($stmt_log) $stmt_log->close();
include('dashboard_footer.php');
?>