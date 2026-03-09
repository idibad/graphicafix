<?php
include('dashboard_header.php');

// Handle mark read / delete actions
if (isset($_GET['action'])) {
    $id = intval($_GET['id'] ?? 0);

    if ($_GET['action'] === 'mark_read' && $id) {
        $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$id]) ;
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    if ($_GET['action'] === 'mark_all_read') {
        $conn->query("UPDATE notifications SET is_read = 1");
    }

    if ($_GET['action'] === 'delete' && $id) {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    if ($_GET['action'] === 'delete_all_read') {
        $conn->query("DELETE FROM notifications WHERE is_read = 1");
    }

    header("Location: manage_notifications.php");
    exit;
}

// Filter
$filter = $_GET['filter'] ?? 'all';
$allowedFilters = ['all','unread','project_request','career_application','contact','review'];
if (!in_array($filter, $allowedFilters)) $filter = 'all';

$where = "WHERE 1=1";
if ($filter === 'unread')                          $where .= " AND is_read = 0";
elseif (in_array($filter, ['project_request','career_application','contact','review']))
                                                   $where .= " AND type = '{$filter}'";

// Counts
$totalCount   = $conn->query("SELECT COUNT(*) AS c FROM notifications")->fetch_assoc()['c'];
$unreadCount  = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE is_read = 0")->fetch_assoc()['c'];
$prCount      = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE type='project_request'")->fetch_assoc()['c'];
$caCount      = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE type='career_application'")->fetch_assoc()['c'];
$ctCount      = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE type='contact'")->fetch_assoc()['c'];
$rvCount      = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE type='review'")->fetch_assoc()['c'];

// Fetch
$notifs = $conn->query("SELECT * FROM notifications {$where} ORDER BY created_at DESC");

// notifIcon(), notifColor(), notifLabel(), timeAgo() are declared in notifications.php
// which is loaded via dashboard_header.php — no need to redeclare them here.
?>

<div class="height-100">

    <div class="page-header">
        <h1>🔔 Notifications</h1>
        <p>Stay updated with new project requests, applications, messages, and reviews</p>
    </div>

    <!-- ── Stats row ──────────────────────────────────────────────────────── -->
    <div class="notif-stats-row">
        <div class="notif-stat-card">
            <div class="notif-stat-icon" style="background:#f0f9f8;color:#024442;">🔔</div>
            <div class="notif-stat-info">
                <div class="notif-stat-num"><?= $totalCount ?></div>
                <div class="notif-stat-label">Total</div>
            </div>
        </div>
        <div class="notif-stat-card">
            <div class="notif-stat-icon" style="background:#fff5f5;color:#e53e3e;">🔴</div>
            <div class="notif-stat-info">
                <div class="notif-stat-num"><?= $unreadCount ?></div>
                <div class="notif-stat-label">Unread</div>
            </div>
        </div>
        <div class="notif-stat-card">
            <div class="notif-stat-icon" style="background:#f0f9f8;color:#024442;">📋</div>
            <div class="notif-stat-info">
                <div class="notif-stat-num"><?= $prCount ?></div>
                <div class="notif-stat-label">Project Requests</div>
            </div>
        </div>
        <div class="notif-stat-card">
            <div class="notif-stat-icon" style="background:#f5f3ff;color:#7c3aed;">💼</div>
            <div class="notif-stat-info">
                <div class="notif-stat-num"><?= $caCount ?></div>
                <div class="notif-stat-label">Applications</div>
            </div>
        </div>
        <div class="notif-stat-card">
            <div class="notif-stat-icon" style="background:#f0f9ff;color:#0ea5e9;">✉️</div>
            <div class="notif-stat-info">
                <div class="notif-stat-num"><?= $ctCount ?></div>
                <div class="notif-stat-label">Messages</div>
            </div>
        </div>
        <div class="notif-stat-card">
            <div class="notif-stat-icon" style="background:#fffbeb;color:#d97706;">⭐</div>
            <div class="notif-stat-info">
                <div class="notif-stat-num"><?= $rvCount ?></div>
                <div class="notif-stat-label">Reviews</div>
            </div>
        </div>
    </div>

    <!-- ── Filters & Actions ───────────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:20px;">
        <div class="notif-toolbar">
            <div class="notif-filter-btns">
                <?php
                $filters = [
                    'all'                => ['All', $totalCount],
                    'unread'             => ['Unread', $unreadCount],
                    'project_request'    => ['📋 Requests', $prCount],
                    'career_application' => ['💼 Applications', $caCount],
                    'contact'            => ['✉️ Messages', $ctCount],
                    'review'             => ['⭐ Reviews', $rvCount],
                ];
                foreach ($filters as $key => [$label, $count]): ?>
                <a href="manage_notifications.php?filter=<?= $key ?>"
                   class="notif-filter-btn <?= $filter === $key ? 'active' : '' ?>">
                    <?= $label ?>
                    <span class="notif-filter-count"><?= $count ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="notif-actions-btns">
                <?php if ($unreadCount > 0): ?>
                <a href="manage_notifications.php?action=mark_all_read"
                   class="btn btn-secondary" style="font-size:13px;padding:7px 14px;">
                    ✅ Mark All Read
                </a>
                <?php endif; ?>
                <a href="manage_notifications.php?action=delete_all_read"
                   class="btn btn-danger" style="font-size:13px;padding:7px 14px;"
                   onclick="return confirm('Delete all read notifications?')">
                    🗑️ Clear Read
                </a>
            </div>
        </div>
    </div>

    <!-- ── Notifications list ──────────────────────────────────────────────── -->
    <div class="card" style="padding:0;overflow:hidden;">
        <?php if ($notifs && $notifs->num_rows > 0): ?>
            <?php while ($n = $notifs->fetch_assoc()):
                $color = notifColor($n['type']);
            ?>
            <div class="notif-row <?= !$n['is_read'] ? 'notif-row-unread' : '' ?>">

                <div class="notif-row-icon" style="background:<?= $color ?>1a;color:<?= $color ?>;">
                    <?= notifIcon($n['type']) ?>
                </div>

                <div class="notif-row-body">
                    <div class="notif-row-top">
                        <span class="notif-row-title"><?= htmlspecialchars($n['title']) ?></span>
                        <span class="notif-type-badge" style="background:<?= $color ?>1a;color:<?= $color ?>;">
                            <?= notifLabel($n['type']) ?>
                        </span>
                        <?php if (!$n['is_read']): ?>
                            <span class="notif-new-badge">NEW</span>
                        <?php endif; ?>
                    </div>
                    <div class="notif-row-message"><?= htmlspecialchars($n['message']) ?></div>
                    <div class="notif-row-time">🕐 <?= date('M d, Y · h:i A', strtotime($n['created_at'])) ?> &nbsp;·&nbsp; <?= timeAgo($n['created_at']) ?></div>
                </div>

                <div class="notif-row-actions">
                    <?php if (!empty($n['link'])): ?>
                    <a href="<?= htmlspecialchars($n['link']) ?>"
                       class="btn btn-secondary" style="font-size:12px;padding:6px 12px;"
                       onclick="markRead(<?= $n['id'] ?>)">
                        👁️ View
                    </a>
                    <?php endif; ?>
                    <?php if (!$n['is_read']): ?>
                    <a href="manage_notifications.php?action=mark_read&id=<?= $n['id'] ?>&filter=<?= $filter ?>"
                       class="btn btn-secondary" style="font-size:12px;padding:6px 12px;">
                        ✅ Read
                    </a>
                    <?php endif; ?>
                    <a href="manage_notifications.php?action=delete&id=<?= $n['id'] ?>&filter=<?= $filter ?>"
                       class="btn btn-danger" style="font-size:12px;padding:6px 12px;"
                       onclick="return confirm('Delete this notification?')">
                        🗑️
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state" style="padding:3rem;">
                <div class="empty-icon">🎉</div>
                <div>No notifications found<?= $filter !== 'all' ? ' for this filter' : '' ?>.</div>
            </div>
        <?php endif; ?>
    </div>

</div>

<style>
/* ── Stats row ─────────────────────────────────────────────── */
.notif-stats-row {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}

@media (max-width: 1100px) { .notif-stats-row { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 600px)  { .notif-stats-row { grid-template-columns: repeat(2, 1fr); } }

.notif-stat-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.notif-stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.notif-stat-num   { font-size: 20px; font-weight: 800; color: #111; line-height: 1; }
.notif-stat-label { font-size: 12px; color: #888; margin-top: 3px; }

/* ── Toolbar ───────────────────────────────────────────────── */
.notif-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding: 16px 18px;
}

.notif-filter-btns {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.notif-filter-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 100px;
    border: 1.5px solid #e0e0e0;
    background: transparent;
    font-size: 13px;
    font-weight: 500;
    color: #555;
    text-decoration: none;
    transition: all .2s;
    white-space: nowrap;
}

.notif-filter-btn:hover,
.notif-filter-btn.active {
    background: #024442;
    border-color: #024442;
    color: #fff;
}

.notif-filter-count {
    background: rgba(0,0,0,.1);
    padding: 1px 6px;
    border-radius: 100px;
    font-size: 11px;
    font-weight: 700;
}

.notif-filter-btn.active .notif-filter-count { background: rgba(255,255,255,.25); }

.notif-actions-btns { display: flex; gap: 8px; flex-wrap: wrap; }

/* ── Notification row ──────────────────────────────────────── */
.notif-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 20px;
    border-bottom: 1px solid #f5f5f5;
    transition: background .15s;
}

.notif-row:last-child  { border-bottom: none; }
.notif-row:hover       { background: #fafafa; }
.notif-row-unread      { background: #f0f9f8; }
.notif-row-unread:hover{ background: #e6f5f3; }

.notif-row-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.notif-row-body { flex: 1; min-width: 0; }

.notif-row-top {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 3px;
}

.notif-row-title {
    font-size: 14px;
    font-weight: 700;
    color: #111;
}

.notif-type-badge {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 100px;
    letter-spacing: .05em;
    text-transform: uppercase;
}

.notif-new-badge {
    font-size: 9px;
    font-weight: 800;
    background: #e53e3e;
    color: #fff;
    padding: 2px 6px;
    border-radius: 100px;
    letter-spacing: .08em;
}

.notif-row-message {
    font-size: 13px;
    color: #666;
    line-height: 1.5;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 500px;
}

.notif-row-time {
    font-size: 11.5px;
    color: #aaa;
    margin-top: 4px;
}

.notif-row-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
    flex-wrap: wrap;
    justify-content: flex-end;
}

@media (max-width: 768px) {
    .notif-row { flex-wrap: wrap; }
    .notif-row-actions { width: 100%; }
    .notif-row-message { max-width: 100%; white-space: normal; }
}
</style>

<script>
function markRead(id) {
    fetch('notifications.php?notif_action=mark_read&id=' + id);
}
</script>

<?php include('dashboard_footer.php'); ?>