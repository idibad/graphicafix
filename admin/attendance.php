<?php
// Hide errors to prevent visual 500 crashes
error_reporting(0);
ini_set('display_errors', '0');

include('dashboard_header.php');

$is_admin = ($role === 'admin');
$user_id  = $_SESSION['user_id'] ?? 0;

// ── Configuration ─────────────────────────────────────────────────────────────
// Array of allowed Office IPs (including localhost for XAMPP testing)
$office_ips = [
    '2402:e000:6ad:80d8:c46f:951c:5385:fcd9', // Original Public IPv6
    '127.0.0.1',                              // Localhost IPv4
    '::1'                                     // Localhost IPv6
]; 
$late_cutoff_time = '09:30:00'; // 9:30 AM

// Default to current month if no filter is applied
$filter_month = $_GET['month'] ?? date('Y-m');

// ── Query — Get EXACTLY the FIRST successful login per user per day ───────────
// Wrapped in CAST(... AS CHAR) to prevent Illegal mix of collations errors
if ($is_admin) {
    $sql = "SELECT l.user_id, l.login_time, l.ip_address, u.name, u.username
            FROM login_logs l
            LEFT JOIN users u ON CAST(l.user_id AS UNSIGNED) = CAST(u.user_id AS UNSIGNED)
            WHERE l.status = 'success' 
              AND DATE_FORMAT(l.login_time, '%Y-%m') = ?
              AND l.login_time = (
                  SELECT MIN(login_time) 
                  FROM login_logs l2 
                  WHERE CAST(l2.user_id AS UNSIGNED) = CAST(l.user_id AS UNSIGNED) 
                    AND DATE(l2.login_time) = DATE(l.login_time) 
                    AND l2.status = 'success'
              )
            ORDER BY l.login_time DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $filter_month);
    }
} else {
    $sql = "SELECT l.user_id, l.login_time, l.ip_address, u.name, u.username
            FROM login_logs l
            LEFT JOIN users u ON CAST(l.user_id AS UNSIGNED) = CAST(u.user_id AS UNSIGNED)
            WHERE l.status = 'success' 
              AND CAST(l.user_id AS UNSIGNED) = CAST(? AS UNSIGNED)
              AND DATE_FORMAT(l.login_time, '%Y-%m') = ?
              AND l.login_time = (
                  SELECT MIN(login_time) 
                  FROM login_logs l2 
                  WHERE CAST(l2.user_id AS UNSIGNED) = CAST(l.user_id AS UNSIGNED) 
                    AND DATE(l2.login_time) = DATE(l.login_time) 
                    AND l2.status = 'success'
              )
            ORDER BY l.login_time DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $user_id_str = (string)$user_id;
        $stmt->bind_param("ss", $user_id_str, $filter_month);
    }
}

// ── Process Data & Calculate Stats (Crash-Proof) ──────────────────────────────
$attendance_records = [];
$stat_present = 0;
$stat_late    = 0;
$stat_onsite  = 0;
$stat_remote  = 0;

if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $login_timestamp = strtotime($row['login_time']);
            $time_only = date('H:i:s', $login_timestamp);
            
            // Check if Late or Present
            if ($time_only <= $late_cutoff_time) {
                $row['att_status'] = 'Present';
                $row['att_class']  = 'present';
                $stat_present++;
            } else {
                $row['att_status'] = 'Late';
                $row['att_class']  = 'late';
                $stat_late++;
            }

            // Check Location via IP Array
            $user_ip = trim($row['ip_address'] ?? '');
            if (in_array($user_ip, $office_ips)) {
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

            $row['date_str'] = date('d M Y', $login_timestamp);
            $row['time_str'] = date('h:i A', $login_timestamp);
            $attendance_records[] = $row;
        }
    }
} else {
    // Fail gracefully if there's an unforeseen DB error
    $db_error = $conn->error ?: "Query failed.";
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
    grid-template-columns: <?php echo $is_admin ? '1.8fr 1.2fr 1fr 1.2fr 1fr 1fr' : '1.2fr 1fr 1.2fr 1fr 1fr'; ?>;
    gap: 16px;
}

.table-row {
    padding: 16px 28px;
    border-bottom: 1px solid #f1f5f9;
    display: grid;
    grid-template-columns: <?php echo $is_admin ? '1.8fr 1.2fr 1fr 1.2fr 1fr 1fr' : '1.2fr 1fr 1.2fr 1fr 1fr'; ?>;
    gap: 16px;
    align-items: center;
    transition: background 0.2s ease;
}
.table-row:hover { background: #fbfdfc; }

/* Attendance Badges */
.status.present { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; text-align: center; }
.status.late    { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; text-align: center; }

/* Location Badges */
.loc-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; padding: 5px 12px; border-radius: 8px; }
.loc-onsite { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.loc-remote { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }

.user-chip { display: flex; align-items: center; gap: 12px; }
.user-chip .mini-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg, var(--accent, #b8f35a), #87bd0a);
    color: var(--primary); font-size: 14px; font-weight: 800;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(184,243,90,0.3);
}
.user-chip small { color: #888; font-size: 12px; display: block; font-weight: 500; margin-top: 2px;}
</style>

<div class="height-100">

    <?php if (isset($db_error)): ?>
    <div style="background:#fee2e2; border:1px solid #fca5a5; color:#dc2626; padding:16px 20px; border-radius:12px; margin-bottom:24px; font-weight:600;">
        <i class="fas fa-exclamation-triangle"></i>️ System Error: <?php echo htmlspecialchars($db_error); ?>
    </div>
    <?php endif; ?>

    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number" style="color:#16a34a;"><?php echo $stat_present; ?></div>
            <div class="stat-label">On Time</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fee2e2;color:#dc2626;">⏰</div>
            <div class="stat-number" style="color:#dc2626;"><?php echo $stat_late; ?></div>
            <div class="stat-label">Late Logins</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#f0fdf4;color:#15803d;"><i class="fas fa-building"></i></div>
            <div class="stat-number" style="color:#15803d;"><?php echo $stat_onsite; ?></div>
            <div class="stat-label">On-site Days</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#f8fafc;color:#475569;">🏠</div>
            <div class="stat-number" style="color:#475569;"><?php echo $stat_remote; ?></div>
            <div class="stat-label">Remote Days</div>
        </div>
    </div>

    <div class="main-card">
        <div class="page-top-actions">
            <div>
                <h2><i class="fas fa-calendar-alt"></i> <?php echo $is_admin ? 'Team Attendance Register' : 'My Attendance Record'; ?></h2>
                <span style="font-size:13.5px; color:#64748b; font-weight: 500;">Showing exactly 1 record per user, per day.</span>
            </div>
            
            <div class="filter-bar">
                <form method="GET" style="display: flex; gap: 8px; align-items: center; margin: 0;">
                    <input type="month" name="month" value="<?php echo htmlspecialchars($filter_month); ?>" onchange="this.form.submit()">
                </form>
                <button class="btn-primary-custom" onclick="window.print()" style="padding: 9px 18px; border-radius: 8px; font-size: 13px;">
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
                
                <span style="font-weight: 800; font-size: 14px; color: <?php echo ($row['att_status'] === 'Late') ? '#dc2626' : '#16a34a'; ?>;">
                    <?php echo $row['time_str']; ?>
                </span>
                
                <span style="font-family:monospace; font-size:12px; color:#64748b; background: #f8fafc; padding: 4px 8px; border-radius: 6px; border: 1px solid #e2e8f0; display: inline-block; text-align: center;">
                    <?php echo htmlspecialchars($row['ip_address'] ?? 'Unknown'); ?>
                </span>
                
                <span>
                    <span class="loc-badge <?php echo $row['loc_class']; ?>">
                        <?php echo $row['loc_icon'] . ' ' . $row['loc_text']; ?>
                    </span>
                </span>
                
                <span>
                    <span class="status <?php echo $row['att_class']; ?>"><?php echo $row['att_status']; ?></span>
                </span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
if ($stmt) $stmt->close();
include('dashboard_footer.php');
?>