<?php
// Hide errors to prevent visual crashes
error_reporting(0);
ini_set('display_errors', '0');

include 'dashboard_header.php';
require_once __DIR__ . '/../core/attendance_schema.php';
require_once __DIR__ . '/../core/attendance_helper.php';

ensure_attendance_schema($conn);

$is_admin = ($role === 'super_admin' || $role === 'superadmin' || $role === 'admin' || $role === 'hrm' || $role === 'manager');
$user_id  = $_SESSION['user_id'] ?? 0;

// Filter month (validated YYYY-MM)
$filter_month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
    $filter_month = date('Y-m');
}

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
        $leave_category  = $_POST['leave_category'] ?? 'casual';
        $reason          = trim($_POST['reason'] ?? '');
        $approved_by     = $user_id;
        
        if ($target_user_id > 0 && !empty($attendance_date) && !empty($attendance_type)) {
            try {
                $dup_stmt = $conn->prepare("SELECT id FROM attendance_overrides WHERE user_id = ? AND attendance_date = ?");
                if ($dup_stmt) {
                    $dup_stmt->bind_param("is", $target_user_id, $attendance_date);
                    $dup_stmt->execute();
                    $dup_res = $dup_stmt->get_result()->fetch_assoc();
                    $dup_stmt->close();
                    
                    if ($dup_res) {
                        $upd_stmt = $conn->prepare("UPDATE attendance_overrides SET attendance_type = ?, leave_category = ?, reason = ?, approved_by = ? WHERE id = ?");
                        if ($upd_stmt) {
                            $upd_stmt->bind_param("sssii", $attendance_type, $leave_category, $reason, $approved_by, $dup_res['id']);
                            if ($upd_stmt->execute()) {
                                $_SESSION['toast_msg']  = 'Attendance override updated successfully!';
                                $_SESSION['toast_type'] = 'success';
                            } else {
                                $_SESSION['toast_msg']  = 'Failed to update attendance override.';
                                $_SESSION['toast_type'] = 'error';
                            }
                            $upd_stmt->close();
                        }
                    } else {
                        $ins_stmt = $conn->prepare("INSERT INTO attendance_overrides (user_id, attendance_date, attendance_type, leave_category, reason, approved_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        if ($ins_stmt) {
                            $ins_stmt->bind_param("issssi", $target_user_id, $attendance_date, $attendance_type, $leave_category, $reason, $approved_by);
                            if ($ins_stmt->execute()) {
                                $_SESSION['toast_msg']  = 'Attendance override added successfully!';
                                $_SESSION['toast_type'] = 'success';
                            } else {
                                $_SESSION['toast_msg']  = 'Failed to add attendance override.';
                                $_SESSION['toast_type'] = 'error';
                            }
                            $ins_stmt->close();
                        }
                    }
                }
            } catch (Throwable $e_add_ov) {
                $_SESSION['toast_msg']  = 'Database error saving override: ' . $e_add_ov->getMessage();
                $_SESSION['toast_type'] = 'error';
            }
        } else {
            $_SESSION['toast_msg']  = 'All fields are required.';
            $_SESSION['toast_type'] = 'error';
        }
        header("Location: attendance.php" . (isset($_GET['month']) ? "?month={$_GET['month']}" : ""));
        exit;
    }
    
    if ($action === 'delete_override') {
        $override_id = intval($_POST['override_id'] ?? 0);
        if ($override_id > 0) {
            try {
                $del_stmt = $conn->prepare("DELETE FROM attendance_overrides WHERE id = ?");
                if ($del_stmt) {
                    $del_stmt->bind_param("i", $override_id);
                    if ($del_stmt->execute()) {
                        $_SESSION['toast_msg']  = 'Attendance override removed successfully!';
                        $_SESSION['toast_type'] = 'success';
                    } else {
                        $_SESSION['toast_msg']  = 'Failed to remove override.';
                        $_SESSION['toast_type'] = 'error';
                    }
                    $del_stmt->close();
                }
            } catch (Throwable $e_del) {
                $_SESSION['toast_msg']  = 'Database error deleting override: ' . $e_del->getMessage();
                $_SESSION['toast_type'] = 'error';
            }
        }
        header("Location: attendance.php" . (isset($_GET['month']) ? "?month={$_GET['month']}" : ""));
        exit;
    }
}

// ── Query Active Users ─────────────────────────────────────────────────────────
$users_list = [];
if ($is_admin) {
    try {
        $u_res = $conn->query("SELECT user_id, name, username, gender, role, department FROM users WHERE status = 'active' AND role NOT IN ('client', 'client_sub', 'student') ORDER BY name ASC");
        if ($u_res) {
            while ($u_row = $u_res->fetch_assoc()) {
                $users_list[] = $u_row;
            }
        }
    } catch (Throwable $e) {
        try {
            $u_res = $conn->query("SELECT user_id, name, username, role FROM users WHERE role NOT IN ('client', 'client_sub', 'student') ORDER BY name ASC");
            if ($u_res) {
                while ($u_row = $u_res->fetch_assoc()) {
                    $u_row['gender'] = 'Male';
                    $u_row['department'] = 'General';
                    $users_list[] = $u_row;
                }
            }
        } catch (Throwable $e2) {}
    }
}

// ── Fetch Overrides for Month ─────────────────────────────────────────────────
$overrides = [];
try {
    if ($is_admin) {
        $stmt_ov = $conn->prepare("
            SELECT o.*, COALESCE(u.name, '') as name, COALESCE(u.username, '') as username, 'Male' as gender, 'user' as role
            FROM attendance_overrides o
            LEFT JOIN users u ON CAST(o.user_id AS UNSIGNED) = CAST(u.user_id AS UNSIGNED)
            WHERE DATE_FORMAT(o.attendance_date, '%Y-%m') = ?
        ");
        if ($stmt_ov) $stmt_ov->bind_param("s", $filter_month);
    } else {
        $stmt_ov = $conn->prepare("
            SELECT o.*, COALESCE(u.name, '') as name, COALESCE(u.username, '') as username, 'Male' as gender, 'user' as role
            FROM attendance_overrides o
            LEFT JOIN users u ON CAST(o.user_id AS UNSIGNED) = CAST(u.user_id AS UNSIGNED)
            WHERE o.user_id = ? AND DATE_FORMAT(o.attendance_date, '%Y-%m') = ?
        ");
        if ($stmt_ov) $stmt_ov->bind_param("is", $user_id, $filter_month);
    }

    if ($stmt_ov && $stmt_ov->execute()) {
        $res_ov = $stmt_ov->get_result();
        while ($row = $res_ov->fetch_assoc()) {
            $key = $row['user_id'] . '_' . $row['attendance_date'];
            $overrides[$key] = $row;
        }
        $stmt_ov->close();
    }
} catch (Throwable $e) {
    // Fallback if users table does not have gender column
    try {
        if ($is_admin) {
            $stmt_ov = $conn->prepare("
                SELECT o.*, u.name, u.username, u.role
                FROM attendance_overrides o
                LEFT JOIN users u ON CAST(o.user_id AS UNSIGNED) = CAST(u.user_id AS UNSIGNED)
                WHERE DATE_FORMAT(o.attendance_date, '%Y-%m') = ?
            ");
            if ($stmt_ov) $stmt_ov->bind_param("s", $filter_month);
        } else {
            $stmt_ov = $conn->prepare("
                SELECT o.*, u.name, u.username, u.role
                FROM attendance_overrides o
                LEFT JOIN users u ON CAST(o.user_id AS UNSIGNED) = CAST(u.user_id AS UNSIGNED)
                WHERE o.user_id = ? AND DATE_FORMAT(o.attendance_date, '%Y-%m') = ?
            ");
            if ($stmt_ov) $stmt_ov->bind_param("is", $user_id, $filter_month);
        }

        if ($stmt_ov && $stmt_ov->execute()) {
            $res_ov = $stmt_ov->get_result();
            while ($row = $res_ov->fetch_assoc()) {
                $row['gender'] = 'Male';
                $key = $row['user_id'] . '_' . $row['attendance_date'];
                $overrides[$key] = $row;
            }
            $stmt_ov->close();
        }
    } catch (Throwable $e2) {
        error_log("Overrides fallback notice: " . $e2->getMessage());
    }
}

// ── Fetch Enhanced Attendance Records (`attendance_records`) ─────────────────
$final_records = [];
try {
    $stmt_att = false;
    if ($is_admin) {
        $stmt_att = $conn->prepare("
            SELECT a.*, COALESCE(u.name, '') as name, COALESCE(u.username, '') as username
            FROM attendance_records a
            LEFT JOIN users u ON CAST(a.user_id AS UNSIGNED) = CAST(u.user_id AS UNSIGNED)
            WHERE DATE_FORMAT(a.attendance_date, '%Y-%m') = ?
            ORDER BY a.attendance_date DESC, a.check_in_time DESC
        ");
        if ($stmt_att) $stmt_att->bind_param("s", $filter_month);
    } else {
        $stmt_att = $conn->prepare("
            SELECT a.*, COALESCE(u.name, '') as name, COALESCE(u.username, '') as username
            FROM attendance_records a
            LEFT JOIN users u ON CAST(a.user_id AS UNSIGNED) = CAST(u.user_id AS UNSIGNED)
            WHERE a.user_id = ? AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ?
            ORDER BY a.attendance_date DESC, a.check_in_time DESC
        ");
        if ($stmt_att) $stmt_att->bind_param("is", $user_id, $filter_month);
    }

    // Resilient fallback if join or prepare fails
    if (!$stmt_att) {
        if ($is_admin) {
            $stmt_att = $conn->prepare("SELECT a.*, '' as name, '' as username FROM attendance_records a WHERE DATE_FORMAT(a.attendance_date, '%Y-%m') = ? ORDER BY a.attendance_date DESC, a.check_in_time DESC");
            if ($stmt_att) $stmt_att->bind_param("s", $filter_month);
        } else {
            $stmt_att = $conn->prepare("SELECT a.*, '' as name, '' as username FROM attendance_records a WHERE a.user_id = ? AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ? ORDER BY a.attendance_date DESC, a.check_in_time DESC");
            if ($stmt_att) $stmt_att->bind_param("is", $user_id, $filter_month);
        }
    }

    if ($stmt_att && $stmt_att->execute()) {
        $res_att = $stmt_att->get_result();
        while ($row = $res_att->fetch_assoc()) {
            $row['gender'] = 'Male';
            $key = $row['user_id'] . '_' . $row['attendance_date'];
            $row['is_override'] = false;
            $final_records[$key] = $row;
        }
        $stmt_att->close();
    }
} catch (Throwable $e) {
    error_log("Attendance query notice: " . $e->getMessage());
}

// ── Merge Overrides ───────────────────────────────────────────────────────────
foreach ($overrides as $key => $ov) {
    if (isset($final_records[$key])) {
        $final_records[$key]['is_override']     = true;
        $final_records[$key]['override_id']     = $ov['id'];
        $final_records[$key]['attendance_type'] = $ov['attendance_type'];
        $final_records[$key]['leave_category']  = $ov['leave_category'] ?? 'casual';
        $final_records[$key]['reason']          = $ov['reason'];
    } else {
        $final_records[$key] = [
            'user_id'           => $ov['user_id'],
            'attendance_date'   => $ov['attendance_date'],
            'check_in_time'     => null,
            'check_out_time'    => null,
            'last_heartbeat'    => null,
            'status'            => ($ov['attendance_type'] === 'leave') ? 'on_leave' : 'present',
            'is_late'           => 0,
            'penalty_amount'    => 0,
            'location_type'     => $ov['attendance_type'],
            'location_denied'   => 0,
            'distance_meters'   => null,
            'ip_address'        => null,
            'name'              => $ov['name'],
            'username'          => $ov['username'],
            'gender'            => $ov['gender'] ?? 'Male',
            'role'              => $ov['role'] ?? 'user',
            'is_override'       => true,
            'override_id'       => $ov['id'],
            'attendance_type'   => $ov['attendance_type'],
            'leave_category'    => $ov['leave_category'] ?? 'casual',
            'reason'            => $ov['reason']
        ];
    }
}

// Sort records by date descending
uasort($final_records, function($a, $b) {
    return strcmp($b['attendance_date'], $a['attendance_date']);
});

// ── Calculate Stats ───────────────────────────────────────────────────────────
$stat_present    = 0;
$stat_on_time    = 0;
$stat_grace      = 0;
$stat_late       = 0;
$stat_penalties  = 0.00;
$stat_onsite     = 0;
$stat_remote     = 0;
$stat_denied_loc = 0;
$stat_leave      = 0;
$stat_medical    = 0;

$attendance_records = [];

foreach ($final_records as $row) {
    $row['gender'] = !empty($row['gender']) ? ucfirst(strtolower(trim($row['gender']))) : 'Male';

    // Effective signout time: either check_out_time or last_heartbeat
    $effective_out = $row['check_out_time'] ?? null;
    $time_out_str  = '—';
    $hours_worked  = '—';

    if (!empty($row['check_in_time'])) {
        $in_ts = strtotime($row['attendance_date'] . ' ' . $row['check_in_time']);
        $row['time_in_str'] = date('h:i A', $in_ts);

        if (!empty($effective_out)) {
            $out_ts = strtotime($row['attendance_date'] . ' ' . $effective_out);
            $time_out_str = date('h:i A', $out_ts);
            $diff_hours = max(0, round(($out_ts - $in_ts) / 3600, 1));
            $hours_worked = $diff_hours . ' hrs';
        } elseif (!empty($row['last_heartbeat']) && date('Y-m-d', strtotime($row['last_heartbeat'])) === $row['attendance_date']) {
            $hb_ts = strtotime($row['last_heartbeat']);
            $diff_hb = time() - $hb_ts;
            if ($diff_hb < 900) { // Active in last 15 mins
                $time_out_str = '<span style="color:#16a34a; font-weight:700;"><i class="fas fa-dot-circle fa-fade"></i> Active</span>';
            } else {
                $time_out_str = date('h:i A', $hb_ts) . ' <small style="color:#94a3b8;">(auto)</small>';
                $diff_hours = max(0, round(($hb_ts - $in_ts) / 3600, 1));
                $hours_worked = $diff_hours . ' hrs';
            }
        }
    } else {
        $row['time_in_str'] = '—';
    }
    $row['time_out_str'] = $time_out_str;
    $row['hours_worked'] = $hours_worked;

    // Resolve Status and Badges
    if (!empty($row['is_override'])) {
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
                break;
            case 'leave':
                $is_med = (($row['leave_category'] ?? '') === 'medical' || stripos($row['reason'] ?? '', 'medical') !== false);
                $row['att_status'] = $is_med ? 'Medical Leave' : 'On Leave';
                $row['att_class']  = $is_med ? 'medical-leave' : 'leave';
                $row['loc_text']   = '—';
                $row['loc_class']  = 'loc-leave';
                $row['loc_icon']   = $is_med ? '🏥' : '🌴';
                $stat_leave++;
                if ($is_med) $stat_medical++;
                break;
        }
    } else {
        // Based on attendance record
        if (($row['status'] ?? '') === 'on_leave') {
            $is_med = (($row['leave_type'] ?? '') === 'medical');
            $row['att_status'] = $is_med ? 'Medical Leave' : 'On Leave';
            $row['att_class']  = $is_med ? 'medical-leave' : 'leave';
            $row['loc_text']   = '—';
            $row['loc_class']  = 'loc-leave';
            $row['loc_icon']   = $is_med ? '🏥' : '🌴';
            $stat_leave++;
            if ($is_med) $stat_medical++;
        } elseif (!empty($row['is_late'])) {
            $row['att_status'] = 'Late';
            $row['att_class']  = 'late';
            $stat_late++;
            $stat_penalties += floatval($row['penalty_amount'] ?? 100);
        } else {
            $row['att_status'] = 'On Time';
            $row['att_class']  = 'present';
            if (!empty($row['is_grace_period'])) {
                $stat_grace++;
            } else {
                $stat_on_time++;
            }
            $stat_present++;
        }

        // Location determination
        if (!empty($row['location_denied']) || ($row['location_type'] ?? '') === 'denied') {
            $row['loc_text']  = 'Denied Location';
            $row['loc_class'] = 'loc-denied';
            $row['loc_icon']  = '⚠️';
            $stat_denied_loc++;
        } elseif (($row['location_type'] ?? '') === 'onsite') {
            $dist_text = !empty($row['distance_meters']) ? " (" . round($row['distance_meters']) . "m)" : "";
            $row['loc_text']  = 'On-site' . $dist_text;
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

    $attendance_records[] = $row;
}

$total_records = count($attendance_records);
?>

<style>
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
    color: var(--primary, #024442);
}
.filter-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.filter-bar input[type="month"] {
    padding: 8px 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    font-family: inherit;
    font-size: 13.5px;
    outline: none;
    background: #f8fafc;
}

/* Stats grid */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: #fff;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #f0f3f2;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}
.stat-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.stat-number { font-size: 22px; font-weight: 800; line-height: 1.1; margin-bottom: 2px; }
.stat-label { font-size: 12px; font-weight: 600; color: #64748b; }

/* Table Head and Rows */
.att-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
}
.att-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 14px 18px;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}
.att-table td {
    padding: 14px 18px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    vertical-align: middle;
}
.att-table tr:hover td { background: #fbfdfc; }

/* Status badges */
.status { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; display: inline-block; }
.status.present       { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
.status.grace         { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; }
.status.late          { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
.status.late-pattern  { background: #fff1f2; color: #be123c; border: 1px solid #fda4af; }
.status.medical-leave { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
.status.leave         { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
.status.field-work    { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }

/* Location Badges */
.loc-badge { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 8px; }
.loc-onsite { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.loc-remote { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
.loc-denied { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; font-weight:800; }
.loc-field-work { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.loc-leave { background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0; }

.gender-tag {
    font-size: 10.5px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    display: inline-block;
    margin-left: 4px;
}
.gender-tag.Male { background: #e0f2fe; color: #0369a1; }
.gender-tag.Female { background: #fce7f3; color: #be185d; }

.penalty-tag {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    padding: 3px 8px;
    border-radius: 6px;
    font-weight: 800;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* Modals */
.modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px);
    z-index: 9999; display: none; align-items: center; justify-content: center; padding: 20px;
    overflow-y: auto !important; -webkit-overflow-scrolling: touch; box-sizing: border-box;
}
.modal-overlay.active { display: flex; }
.modal-content {
    background: #fff; border-radius: 16px; width: 100%; max-width: 520px; max-height: 90vh !important;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); overflow-y: auto !important;
    display: flex; flex-direction: column; box-sizing: border-box;
}
.modal-header {
    background: var(--primary, #024442); color: #fff; padding: 18px 24px;
    display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;
}
.modal-header h4 { margin: 0; font-size: 1.15rem; font-weight: 700; display:flex; align-items:center; gap:8px;}
.modal-body { padding: 24px; overflow-y: auto !important; flex: 1 1 auto; min-height: 0; }
.modal-footer {
    padding: 16px 24px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 12px; background: #fbfcfc; flex-shrink: 0;
}
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 5px; }
.form-group input, .form-group select, .form-group textarea {
    width: 100%; padding: 10px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 13.5px; outline: none; background: #fff;
}
</style>

<div class="height-100">

    <!-- Summary Stats Row -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7; color:#16a34a;"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="stat-number" style="color:#16a34a;"><?= $stat_on_time ?></div>
                <div class="stat-label">On Time Arrivals</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:#e0f2fe; color:#0284c7;"><i class="fas fa-stopwatch"></i></div>
            <div>
                <div class="stat-number" style="color:#0284c7;"><?= $stat_grace ?></div>
                <div class="stat-label">Grace Arrivals</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:#fee2e2; color:#dc2626;"><i class="fas fa-clock"></i></div>
            <div>
                <div class="stat-number" style="color:#dc2626;"><?= $stat_late ?></div>
                <div class="stat-label">Late Arrivals / Flagged</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:#fff1f2; color:#be123c;"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <div class="stat-number" style="color:#be123c;">PKR <?= number_format($stat_penalties) ?></div>
                <div class="stat-label">Late Penalties Incurred</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:#f0fdf4; color:#15803d;"><i class="fas fa-building"></i></div>
            <div>
                <div class="stat-number" style="color:#15803d;"><?= $stat_onsite ?> / <?= $stat_remote ?></div>
                <div class="stat-label">On-site / Remote</div>
            </div>
        </div>

        <?php if ($stat_denied_loc > 0): ?>
        <div class="stat-card" style="border-left: 3px solid #f59e0b;">
            <div class="stat-icon" style="background:#fef3c7; color:#b45309;">⚠️</div>
            <div>
                <div class="stat-number" style="color:#b45309;"><?= $stat_denied_loc ?></div>
                <div class="stat-label">Denied Location</div>
            </div>
        </div>
        <?php endif; ?>

        <div class="stat-card">
            <div class="stat-icon" style="background:#f3e8ff; color:#7e22ce;"><i class="fas fa-clinic-medical"></i></div>
            <div>
                <div class="stat-number" style="color:#7e22ce;"><?= $stat_medical ?> <small style="font-size:12px; font-weight:600; color:#94a3b8;">/ 1 mo</small></div>
                <div class="stat-label">Medical Leaves</div>
            </div>
        </div>
    </div>

    <!-- Main Attendance Register Card -->
    <div class="main-card">
        <div class="page-top-actions">
            <div>
                <h2><i class="fas fa-calendar-check"></i> <?= $is_admin ? 'Staff Attendance & Work Register' : 'My Attendance & Work Register' ?></h2>
                <span style="font-size:13px; color:#64748b; font-weight: 500;">
                    Tracking daily work hours, sign-in and sign-out times, and location status.
                </span>
            </div>
            
            <div class="filter-bar">
                <form method="GET" style="display: flex; gap: 8px; align-items: center; margin: 0;">
                    <input type="month" name="month" value="<?= htmlspecialchars($filter_month) ?>" onchange="this.form.submit()">
                </form>

                <?php if ($is_admin): ?>
                    <button class="btn btn-primary-custom" onclick="openOverrideModal()" style="padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fas fa-plus-circle"></i> Add Override / Leave
                    </button>
                    <a href="salaries.php?month=<?= htmlspecialchars($filter_month) ?>" class="btn" style="background:#dc2626; color:#fff; padding:8px 14px; border-radius:8px; font-size:13px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:5px;" onclick="localStorage.setItem('open_add_penalty', '1')">
                        <i class="fas fa-minus-circle"></i> Add Penalty / Deduction
                    </a>
                    <a href="salaries.php?month=<?= htmlspecialchars($filter_month) ?>" class="btn btn-secondary-custom" style="padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600;">
                        <i class="fas fa-money-bill-wave"></i> Payroll Table
                    </a>
                <?php endif; ?>

                <button class="btn btn-secondary-custom" onclick="window.print()" style="padding: 8px 16px; border-radius: 8px; font-size: 13px;">
                    <i class="fas fa-download"></i> Export / Print
                </button>
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table class="att-table">
                <thead>
                    <tr>
                        <?php if ($is_admin): ?>
                        <th>Employee</th>
                        <?php endif; ?>
                        <th>Date</th>
                        <th>Sign In (Time In)</th>
                        <th>Sign Out (Time Out)</th>
                        <th>Working Hours</th>
                        <th>Location Status</th>
                        <th>Attendance Status</th>
                        <th>Penalty (PKR)</th>
                        <?php if ($is_admin): ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendance_records)): ?>
                    <tr>
                        <td colspan="<?= $is_admin ? 9 : 8 ?>" style="text-align:center; padding: 70px 20px; color:#94a3b8;">
                            <i class="fas fa-user-clock" style="font-size: 2.5rem; margin-bottom: 12px; display:block;"></i>
                            <strong>No attendance records found for <?= date('F Y', strtotime($filter_month . '-01')) ?>.</strong>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($attendance_records as $row): 
                        $emp_name = !empty($row['name']) ? $row['name'] : (!empty($row['username']) ? $row['username'] : 'Staff #' . ($row['user_id'] ?? ''));
                        $initial = strtoupper(substr($emp_name, 0, 1));
                    ?>
                    <tr>
                        <?php if ($is_admin): ?>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg, var(--accent, #b8f35a), #87bd0a); color:var(--primary, #024442); font-size:13px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                    <?= $initial ?>
                                </div>
                                <div>
                                    <strong style="color:var(--primary, #024442); font-size:13.5px;"><?= htmlspecialchars($emp_name) ?></strong>
                                    <span class="gender-tag <?= $row['gender'] ?? 'Male' ?>"><?= htmlspecialchars($row['gender'] ?? 'Male') ?></span>
                                    <small style="display:block; color:#64748b; font-size:11.5px;">@<?= htmlspecialchars($row['username'] ?? 'user') ?></small>
                                </div>
                            </div>
                        </td>
                        <?php endif; ?>

                        <td style="white-space:nowrap; font-weight:600; color:#334155;">
                            <?= date('d M Y', strtotime($row['attendance_date'])) ?>
                        </td>

                        <td style="white-space:nowrap; font-weight:800; color:<?= ($row['att_class'] === 'late' || $row['att_class'] === 'late-pattern') ? '#dc2626' : '#16a34a' ?>;">
                            <?= $row['time_in_str'] ?>
                        </td>

                        <td style="white-space:nowrap; font-weight:700; color:#475569;">
                            <?= $row['time_out_str'] ?>
                        </td>

                        <td style="white-space:nowrap; font-weight:600; color:#0284c7;">
                            <?= $row['hours_worked'] ?>
                        </td>

                        <td style="white-space:nowrap;">
                            <span class="loc-badge <?= $row['loc_class'] ?>">
                                <?= $row['loc_icon'] ?> <?= htmlspecialchars($row['loc_text']) ?>
                            </span>
                        </td>

                        <td>
                            <span class="status <?= $row['att_class'] ?>"><?= $row['att_status'] ?></span>
                            <?php if (!empty($row['penalty_reason'])): ?>
                                <small style="display:block; color:#dc2626; font-size:11px; margin-top:2px;" title="<?= htmlspecialchars($row['penalty_reason']) ?>">
                                    <?= htmlspecialchars($row['penalty_reason']) ?>
                                </small>
                            <?php elseif (!empty($row['reason'])): ?>
                                <small style="display:block; color:#64748b; font-size:11px; margin-top:2px;">
                                    💬 <?= htmlspecialchars($row['reason']) ?>
                                </small>
                            <?php endif; ?>
                        </td>

                        <td style="white-space:nowrap;">
                            <?php if (!empty($row['penalty_amount']) && $row['penalty_amount'] > 0): ?>
                                <span class="penalty-tag" title="<?= htmlspecialchars($row['penalty_reason'] ?? 'Late Penalty') ?>">
                                    <i class="fas fa-exclamation-circle"></i> -PKR <?= number_format($row['penalty_amount']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#cbd5e1;">—</span>
                            <?php endif; ?>
                        </td>

                        <?php if ($is_admin): ?>
                        <td>
                            <?php if (!empty($row['is_override'])): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this attendance override?');">
                                <input type="hidden" name="action" value="delete_override">
                                <input type="hidden" name="override_id" value="<?= $row['override_id'] ?>">
                                <button type="submit" title="Delete Override" style="background:#fee2e2; color:#dc2626; border:1px solid #fecaca; width:30px; height:30px; border-radius:6px; cursor:pointer;">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <span style="color:#cbd5e1;">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Override Modal (Admin Only) -->
<?php if ($is_admin): ?>
<div id="overrideModal" class="modal-overlay" onclick="if(event.target===this)closeModal('overrideModal')">
    <div class="modal-content">
        <div class="modal-header">
            <h4><i class="fas fa-plus-circle"></i> Add Attendance / Leave Override</h4>
            <button type="button" onclick="closeModal('overrideModal')" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_override">
            <div class="modal-body">
                <div class="form-group">
                    <label>Select Employee <span style="color:#ef4444;">*</span></label>
                    <select name="user_id" required>
                        <option value="">— Select Employee —</option>
                        <?php foreach ($users_list as $u): ?>
                            <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['name']) ?> (@<?= htmlspecialchars($u['username']) ?> · <?= htmlspecialchars($u['gender'] ?? 'Male') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Attendance Date <span style="color:#ef4444;">*</span></label>
                    <input type="date" name="attendance_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label>Attendance Type <span style="color:#ef4444;">*</span></label>
                    <select name="attendance_type" id="ov_att_type" onchange="toggleLeaveCategory(this.value)" required>
                        <option value="onsite">On-site (Present)</option>
                        <option value="remote">Remote (Present)</option>
                        <option value="field_work">Field Work</option>
                        <option value="leave">On Leave</option>
                    </select>
                </div>

                <div class="form-group" id="leave_category_group" style="display:none;">
                    <label>Leave Category <span style="color:#ef4444;">*</span></label>
                    <select name="leave_category">
                        <option value="casual">Casual Leave</option>
                        <option value="medical">Medical Leave</option>
                        <option value="annual">Annual Leave</option>
                        <option value="unpaid">Unpaid Leave</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Reason / Official Approval Notes</label>
                    <textarea name="reason" placeholder="e.g. Doctor recommendation attached, client emergency, official travel, etc." rows="3"></textarea>
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
function openOverrideModal() { openModal('overrideModal'); }
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

function toggleLeaveCategory(val) {
    const group = document.getElementById('leave_category_group');
    if (group) {
        group.style.display = (val === 'leave') ? 'block' : 'none';
    }
}

function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.style.cssText = `background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:240px;border-left:4px solid ${type==='success'?'#10b981':'#ef4444'};`;
    t.innerHTML = `<span style="font-weight:700;color:${type==='success'?'#10b981':'#ef4444'}">${type==='success'?'<i class="fas fa-check"></i>':'<i class="fas fa-times"></i>'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3500);
}
</script>

<?php if ($toast_message): ?>
<script>
document.addEventListener('DOMContentLoaded', () => showToast(<?= json_encode($toast_message) ?>, <?= json_encode($toast_type) ?>));
</script>
<?php endif; ?>

<?php include('dashboard_footer.php'); ?>
