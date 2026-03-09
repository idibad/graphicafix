<?php
/**
 * notifications.php
 * Include this file in dashboard_header.php where you want the bell icon to appear.
 * Also handles AJAX requests for mark-as-read and mark-all-as-read.
 *
 * REQUIRES: A `notifications` table — run the SQL below once:
 *
 * CREATE TABLE IF NOT EXISTS notifications (
 *   id          INT(11)      NOT NULL AUTO_INCREMENT,
 *   type        VARCHAR(50)  NOT NULL,              -- 'project_request','career_application','contact','review'
 *   title       VARCHAR(255) NOT NULL,
 *   message     VARCHAR(500) NOT NULL,
 *   link        VARCHAR(255) DEFAULT NULL,          -- admin page to view the entry
 *   reference_id INT(11)     DEFAULT NULL,          -- id in the source table
 *   is_read     TINYINT(1)   NOT NULL DEFAULT 0,
 *   created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *   PRIMARY KEY (id)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */

// ── Handle AJAX actions ───────────────────────────────────────────────────────
if (isset($_GET['notif_action'])) {
    header('Content-Type: application/json');

    if ($_GET['notif_action'] === 'mark_read' && isset($_GET['id'])) {
        $id   = intval($_GET['id']);
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        echo json_encode(['success' => $stmt->execute()]);
        exit;
    }

    if ($_GET['notif_action'] === 'mark_all_read') {
        $conn->query("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_GET['notif_action'] === 'get_count') {
        $res   = $conn->query("SELECT COUNT(*) AS cnt FROM notifications WHERE is_read = 0");
        $count = $res->fetch_assoc()['cnt'];
        echo json_encode(['count' => (int)$count]);
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

// ── Sync new entries into notifications table ─────────────────────────────────
// Checks each source table for entries that don't yet have a notification row.

// 1. Project Requests
$newRequests = $conn->query("
    SELECT pr.id, pr.name, pr.project_type, pr.created_at
    FROM project_requests pr
    LEFT JOIN notifications n ON n.type = 'project_request' AND n.reference_id = pr.id
    WHERE n.id IS NULL
");
if ($newRequests && $newRequests->num_rows > 0) {
    $ins = $conn->prepare("INSERT INTO notifications (type, title, message, link, reference_id, created_at)
                           VALUES ('project_request', ?, ?, ?, ?, ?)");
    while ($r = $newRequests->fetch_assoc()) {
        $title   = "New Project Request";
        $message = htmlspecialchars($r['name']) . " submitted a request for " . htmlspecialchars($r['project_type']);
        $link    = "project_requests.php";
        $ins->bind_param("sssss", $title, $message, $link, $r['id'], $r['created_at']);
        $ins->execute();
    }
}

// 2. Career Applications
$newApps = $conn->query("
    SELECT ca.id, ca.name, ca.position, ca.applied_at
    FROM career_applications ca
    LEFT JOIN notifications n ON n.type = 'career_application' AND n.reference_id = ca.id
    WHERE n.id IS NULL
");
if ($newApps && $newApps->num_rows > 0) {
    $ins = $conn->prepare("INSERT INTO notifications (type, title, message, link, reference_id, created_at)
                           VALUES ('career_application', ?, ?, ?, ?, ?)");
    while ($r = $newApps->fetch_assoc()) {
        $title   = "New Job Application";
        $message = htmlspecialchars($r['name']) . " applied for " . htmlspecialchars($r['position']);
        $link    = "manage_applications.php";
        $ins->bind_param("sssss", $title, $message, $link, $r['id'], $r['applied_at']);
        $ins->execute();
    }
}

// 3. Contact Messages
$newContacts = $conn->query("
    SELECT c.id, c.name, c.email, c.created_at
    FROM contacts c
    LEFT JOIN notifications n ON n.type = 'contact' AND n.reference_id = c.id
    WHERE n.id IS NULL
");
if ($newContacts && $newContacts->num_rows > 0) {
    $ins = $conn->prepare("INSERT INTO notifications (type, title, message, link, reference_id, created_at)
                           VALUES ('contact', ?, ?, ?, ?, ?)");
    while ($r = $newContacts->fetch_assoc()) {
        $title   = "New Contact Message";
        $message = htmlspecialchars($r['name']) . " sent a message (" . htmlspecialchars($r['email']) . ")";
        $link    = "messages.php";
        $ins->bind_param("sssss", $title, $message, $link, $r['id'], $r['created_at']);
        $ins->execute();
    }
}

// 4. Reviews (pending / not yet visible)
$newReviews = $conn->query("
    SELECT rv.id, rv.client_name, rv.company, rv.rating, rv.created_at
    FROM reviews rv
    LEFT JOIN notifications n ON n.type = 'review' AND n.reference_id = rv.id
    WHERE n.id IS NULL
");
if ($newReviews && $newReviews->num_rows > 0) {
    $ins = $conn->prepare("INSERT INTO notifications (type, title, message, link, reference_id, created_at)
                           VALUES ('review', ?, ?, ?, ?, ?)");
    while ($r = $newReviews->fetch_assoc()) {
        $stars   = str_repeat('★', intval($r['rating']));
        $title   = "New Review Submitted";
        $message = htmlspecialchars($r['client_name']) . " (" . htmlspecialchars($r['company']) . ") left a {$stars} review";
        $link    = "reviews.php";
        $ins->bind_param("sssss", $title, $message, $link, $r['id'], $r['created_at']);
        $ins->execute();
    }
}

// ── Fetch latest 15 notifications ────────────────────────────────────────────
$notifs = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 15");
$allNotifs = $notifs ? $notifs->fetch_all(MYSQLI_ASSOC) : [];
$unreadCount = 0;
foreach ($allNotifs as $n) { if (!$n['is_read']) $unreadCount++; }

// ── Icon map ──────────────────────────────────────────────────────────────────
if (!function_exists('notifIcon')) {
    function notifIcon($type) {
        return match($type) {
            'project_request'    => '📋',
            'career_application' => '💼',
            'contact'            => '✉️',
            'review'             => '⭐',
            default              => '🔔',
        };
    }
}

if (!function_exists('notifColor')) {
    function notifColor($type) {
        return match($type) {
            'project_request'    => '#024442',
            'career_application' => '#7c3aed',
            'contact'            => '#0ea5e9',
            'review'             => '#d97706',
            default              => '#6b7280',
        };
    }
}

if (!function_exists('notifLabel')) {
    function notifLabel($type) {
        return match($type) {
            'project_request'    => 'Project Request',
            'career_application' => 'Job Application',
            'contact'            => 'Contact Message',
            'review'             => 'Review',
            default              => 'Notification',
        };
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $now  = new DateTime();
        $then = new DateTime($datetime);
        $diff = $now->diff($then);
        if ($diff->days >= 7)  return date('M d', strtotime($datetime));
        if ($diff->days >= 1)  return $diff->days . 'd ago';
        if ($diff->h >= 1)     return $diff->h . 'h ago';
        if ($diff->i >= 1)     return $diff->i . 'm ago';
        return 'Just now';
    }
}
?>
<!-- ── Bell icon HTML (drop into your dashboard header nav) ──────────────── -->
<div class="notif-wrap" id="notifWrap">
    <button class="notif-bell" id="notifBell" onclick="toggleNotifPanel()" aria-label="Notifications">
        🔔
        <?php if ($unreadCount > 0): ?>
            <span class="notif-badge" id="notifBadge"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
        <?php else: ?>
            <span class="notif-badge notif-badge-hidden" id="notifBadge"></span>
        <?php endif; ?>
    </button>

    <!-- ── Dropdown panel ────────────────────────────────────────────────── -->
    <div class="notif-panel" id="notifPanel">
        <div class="notif-panel-header">
            <div class="notif-panel-title">
                🔔 Notifications
                <?php if ($unreadCount > 0): ?>
                    <span class="notif-unread-chip"><?= $unreadCount ?> new</span>
                <?php endif; ?>
            </div>
            <?php if ($unreadCount > 0): ?>
                <button class="notif-mark-all" onclick="markAllRead()">Mark all read</button>
            <?php endif; ?>
        </div>

        <div class="notif-list" id="notifList">
            <?php if (empty($allNotifs)): ?>
                <div class="notif-empty">
                    <div class="notif-empty-icon">🎉</div>
                    <div>All caught up! No notifications.</div>
                </div>
            <?php else: ?>
                <?php foreach ($allNotifs as $n): ?>
                <div class="notif-item <?= !$n['is_read'] ? 'notif-unread' : '' ?>"
                     id="notif-<?= $n['id'] ?>"
                     onclick="handleNotifClick(<?= $n['id'] ?>, '<?= htmlspecialchars($n['link'] ?? '#') ?>')">

                    <div class="notif-icon-wrap" style="background: <?= notifColor($n['type']) ?>1a; color: <?= notifColor($n['type']) ?>;">
                        <?= notifIcon($n['type']) ?>
                    </div>

                    <div class="notif-content">
                        <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
                        <div class="notif-message"><?= htmlspecialchars($n['message']) ?></div>
                        <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
                    </div>

                    <?php if (!$n['is_read']): ?>
                        <div class="notif-dot"></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="notif-panel-footer">
            <a href="manage_notifications.php" class="notif-view-all">View all notifications →</a>
        </div>
    </div>
</div>

<!-- ── Styles ─────────────────────────────────────────────────────────────── -->
<style>
/* ── Wrapper ───────────────────────────────────────────────── */
.notif-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
}

/* ── Bell button ───────────────────────────────────────────── */
.notif-bell {
    position: relative;
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    padding: 6px 8px;
    border-radius: 8px;
    transition: background .2s;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notif-bell:hover { background: rgba(2,68,66,.08); }

/* ── Badge ─────────────────────────────────────────────────── */
.notif-badge {
    position: absolute;
    top: 2px;
    right: 2px;
    min-width: 17px;
    height: 17px;
    background: #e53e3e;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    border-radius: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    line-height: 1;
    border: 2px solid #fff;
    transition: transform .2s;
}

.notif-badge-hidden { display: none; }

/* ── Panel ─────────────────────────────────────────────────── */
.notif-panel {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 360px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    box-shadow: 0 16px 48px rgba(0,0,0,.14);
    z-index: 9999;
    display: none;
    flex-direction: column;
    overflow: hidden;
    animation: notifSlideIn .2s cubic-bezier(.22,.88,.36,1) both;
}

.notif-panel.open { display: flex; }

@keyframes notifSlideIn {
    from { opacity: 0; transform: translateY(-8px) scale(.98); }
    to   { opacity: 1; transform: translateY(0)    scale(1); }
}

/* ── Panel header ──────────────────────────────────────────── */
.notif-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 18px 12px;
    border-bottom: 1px solid #f0f0f0;
}

.notif-panel-title {
    font-size: 14px;
    font-weight: 800;
    color: #111;
    display: flex;
    align-items: center;
    gap: 8px;
}

.notif-unread-chip {
    background: #e53e3e;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 100px;
}

.notif-mark-all {
    font-size: 12px;
    color: #024442;
    font-weight: 600;
    background: none;
    border: none;
    cursor: pointer;
    transition: color .2s;
    white-space: nowrap;
}

.notif-mark-all:hover { color: #01796f; text-decoration: underline; }

/* ── List ──────────────────────────────────────────────────── */
.notif-list {
    max-height: 400px;
    overflow-y: auto;
    overscroll-behavior: contain;
}

.notif-list::-webkit-scrollbar { width: 4px; }
.notif-list::-webkit-scrollbar-track { background: transparent; }
.notif-list::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }

/* ── Individual item ───────────────────────────────────────── */
.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 13px 18px;
    border-bottom: 1px solid #f9f9f9;
    cursor: pointer;
    transition: background .15s;
    position: relative;
}

.notif-item:last-child { border-bottom: none; }
.notif-item:hover      { background: #f9fafb; }
.notif-item.notif-unread { background: #f0f9f8; }
.notif-item.notif-unread:hover { background: #e6f5f3; }

.notif-icon-wrap {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    flex-shrink: 0;
}

.notif-content { flex: 1; min-width: 0; }

.notif-title {
    font-size: 13px;
    font-weight: 700;
    color: #111;
    margin-bottom: 2px;
}

.notif-message {
    font-size: 12.5px;
    color: #666;
    line-height: 1.5;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.notif-time {
    font-size: 11px;
    color: #aaa;
    margin-top: 4px;
}

.notif-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #024442;
    flex-shrink: 0;
    margin-top: 4px;
}

/* ── Empty state ───────────────────────────────────────────── */
.notif-empty {
    text-align: center;
    padding: 2.5rem 1rem;
    color: #999;
    font-size: 13.5px;
}

.notif-empty-icon { font-size: 36px; margin-bottom: 8px; }

/* ── Footer ────────────────────────────────────────────────── */
.notif-panel-footer {
    padding: 12px 18px;
    border-top: 1px solid #f0f0f0;
    text-align: center;
}

.notif-view-all {
    font-size: 13px;
    color: #024442;
    font-weight: 600;
    text-decoration: none;
    transition: color .2s;
}

.notif-view-all:hover { color: #01796f; }

/* ── Responsive ────────────────────────────────────────────── */
@media (max-width: 480px) {
    .notif-panel {
        width: calc(100vw - 24px);
        right: -12px;
    }
}
</style>

<!-- ── Scripts ────────────────────────────────────────────────────────────── -->
<script>
// Toggle panel open/close
function toggleNotifPanel() {
    const panel = document.getElementById('notifPanel');
    panel.classList.toggle('open');
}

// Close when clicking outside
document.addEventListener('click', function (e) {
    const wrap = document.getElementById('notifWrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('notifPanel').classList.remove('open');
    }
});

// Click a notification — mark read then navigate
function handleNotifClick(id, link) {
    fetch(`?notif_action=mark_read&id=${id}`)
        .then(() => {
            const item = document.getElementById('notif-' + id);
            if (item) {
                item.classList.remove('notif-unread');
                const dot = item.querySelector('.notif-dot');
                if (dot) dot.remove();
            }
            updateBadgeCount(-1);
            if (link && link !== '#') window.location.href = link;
        });
}

// Mark all read
function markAllRead() {
    fetch('?notif_action=mark_all_read')
        .then(() => {
            document.querySelectorAll('.notif-item').forEach(item => {
                item.classList.remove('notif-unread');
                const dot = item.querySelector('.notif-dot');
                if (dot) dot.remove();
            });
            updateBadgeCount(0, true);
            // Hide the mark-all button and unread chip
            const btn = document.querySelector('.notif-mark-all');
            const chip = document.querySelector('.notif-unread-chip');
            if (btn)  btn.remove();
            if (chip) chip.remove();
        });
}

// Update the bell badge count
let currentBadgeCount = <?= $unreadCount ?>;

function updateBadgeCount(delta, reset = false) {
    const badge = document.getElementById('notifBadge');
    if (!badge) return;
    if (reset) {
        currentBadgeCount = 0;
    } else {
        currentBadgeCount = Math.max(0, currentBadgeCount + delta);
    }
    if (currentBadgeCount <= 0) {
        badge.classList.add('notif-badge-hidden');
        badge.textContent = '';
    } else {
        badge.classList.remove('notif-badge-hidden');
        badge.textContent = currentBadgeCount > 99 ? '99+' : currentBadgeCount;
    }
}

// Auto-poll for new notifications every 60 seconds
setInterval(function () {
    fetch('?notif_action=get_count')
        .then(r => r.json())
        .then(data => {
            if (data.count !== currentBadgeCount) {
                currentBadgeCount = data.count;
                const badge = document.getElementById('notifBadge');
                if (!badge) return;
                if (currentBadgeCount <= 0) {
                    badge.classList.add('notif-badge-hidden');
                    badge.textContent = '';
                } else {
                    badge.classList.remove('notif-badge-hidden');
                    badge.textContent = currentBadgeCount > 99 ? '99+' : currentBadgeCount;
                    badge.style.transform = 'scale(1.3)';
                    setTimeout(() => badge.style.transform = '', 300);
                }
            }
        });
}, 60000);
</script>

