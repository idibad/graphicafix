<?php
include 'dashboard_header.php';


$is_admin   = $role === 'admin';

// ── Query — JOIN users to get username ────────────────────────────────────────
if ($is_admin) {
    $sql = "SELECT l.id, l.user_id, u.name, u.username, l.login_time, 
                   l.ip_address, l.user_agent, l.status
            FROM login_logs l
            LEFT JOIN users u ON l.user_id = u.user_id
            ORDER BY l.login_time DESC";
    $stmt = $conn->prepare($sql);
} else {
    $sql = "SELECT l.id, l.user_id, u.name, u.username, l.login_time,
                   l.ip_address, l.user_agent, l.status
            FROM login_logs l
            LEFT JOIN users u ON l.user_id = u.user_id
            WHERE l.user_id = ?
            ORDER BY l.login_time DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$total  = $result->num_rows;

// ── Helper: parse user agent ──────────────────────────────────────────────────
function parseAgent($ua) {
    $os      = 'Unknown';
    $browser = 'Unknown';
    if (empty($ua)) return "$os • $browser";
    if      (preg_match('/Windows/i', $ua)) $os = 'Windows';
    elseif  (preg_match('/iPhone|iPad/i', $ua)) $os = 'iOS';
    elseif  (preg_match('/Android/i', $ua)) $os = 'Android';
    elseif  (preg_match('/Mac/i', $ua)) $os = 'Mac';
    elseif  (preg_match('/Linux/i', $ua)) $os = 'Linux';
    if      (preg_match('/Edg/i', $ua))     $browser = 'Edge';
    elseif  (preg_match('/Chrome/i', $ua))  $browser = 'Chrome';
    elseif  (preg_match('/Firefox/i', $ua)) $browser = 'Firefox';
    elseif  (preg_match('/Safari/i', $ua))  $browser = 'Safari';
    return "$os • $browser";
}
?>

<style>
    .table-head, .table-row {
        grid-template-columns: <?= $is_admin ? '1.4fr 1.2fr 1fr 1fr 1.4fr 1fr 0.5fr' : '1.4fr 1.2fr 1.2fr 1.4fr 1fr 0.5fr' ?>;
    }
    .status.active { background: #e8f5e9; color: #2e7d32; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
    .status.failed { background: #fef2f2; color: #c62828; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
    .user-chip { display: flex; align-items: center; gap: 8px; }
    .user-chip .mini-avatar {
        width: 28px; height: 28px; border-radius: 50%;
        background: linear-gradient(135deg, var(--accent, #b8f35a), #87bd0a);
        color: #1a1a1a; font-size: 11px; font-weight: 700;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .user-chip small { color: #888; font-size: 12px; display: block; }
</style>

<div class="main-card">
    <div class="main-header">
        <h3><i class="fas fa-clock"></i> <?= $is_admin ? 'All Login History' : 'My Login History' ?></h3>
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:13px;color:#888;"><?= $total ?> record<?= $total !== 1 ? 's' : '' ?></span>
            <button class="add-client" onclick="window.print()">⬇ Download</button>
        </div>
    </div>

    <div class="main-table">
        <div class="table-head">
            <?php if ($is_admin): ?>
            <span>User</span>
            <?php endif; ?>
            <span>Date</span>
            <span>Time</span>
            <span>IP Address</span>
            <span>Device</span>
            <span>Status</span>
            <span></span>
        </div>

        <?php if ($total === 0): ?>
        <div style="text-align:center;padding:48px 24px;color:#aaa;">
            <div style="font-size:36px;margin-bottom:8px;"><i class="fas fa-envelope-open"></i></div>
            <p style="margin:0;">No login records found.</p>
        </div>
        <?php else: ?>
        <?php while ($row = $result->fetch_assoc()):
            $date    = date('d M Y', strtotime($row['login_time']));
            $time    = date('h:i A', strtotime($row['login_time']));
            $device  = parseAgent($row['user_agent']);
            $statusClass = $row['status'] === 'success' ? 'active' : 'failed';
            $statusText  = ucfirst($row['status']);
            $displayName = $row['name']     ?? 'Unknown';
            $username    = $row['username'] ?? '—';
        ?>
        <div class="table-row">
            <?php if ($is_admin): ?>
            <div class="user-chip">
                <div class="mini-avatar"><?= strtoupper(substr($displayName, 0, 1)) ?></div>
                <div>
                    <strong style="font-size:13px;"><?= htmlspecialchars($displayName) ?></strong>
                    <small>@<?= htmlspecialchars($username) ?></small>
                </div>
            </div>
            <?php endif; ?>
            <span><?= $date ?></span>
            <span><?= $time ?></span>
            <span style="font-family:monospace;font-size:13px;"><?= htmlspecialchars($row['ip_address'] ?? '—') ?></span>
            <span style="font-size:13px;"><?= $device ?></span>
            <span><span class="status <?= $statusClass ?>"><?= $statusText ?></span></span>
            <button class="dots">⋮</button>
        </div>
        <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<?php
$stmt->close();
include('dashboard_footer.php');
?>