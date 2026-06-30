<?php
/**
 * notifications.php — Role-based
 *
 * user_id = NULL  → admin-only (users, contacts, reviews, job apps, project requests, enrollments)
 * user_id = 0     → everyone (notices, resources)
 * user_id = N     → specific user only (their assigned tasks)
 *
 * Run once to add the column:
 * ALTER TABLE notifications ADD COLUMN IF NOT EXISTS user_id INT(11) DEFAULT NULL;
 */

$_notif_uid      = intval($_SESSION['user_id'] ?? 0);
$_notif_is_admin = strtolower($_SESSION['role'] ?? '') === 'admin';

// ── AJAX actions ──────────────────────────────────────────────────────────────
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
        if ($_notif_is_admin) {
            $conn->query("UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND is_deleted = 0");
        } else {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND is_deleted = 0 AND (user_id = ? OR user_id = 0)");
            $stmt->bind_param("i", $_notif_uid);
            $stmt->execute();
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_GET['notif_action'] === 'delete' && isset($_GET['id'])) {
        $id   = intval($_GET['id']);
        $stmt = $conn->prepare("UPDATE notifications SET is_deleted = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        echo json_encode(['success' => $stmt->execute()]);
        exit;
    }

    if ($_GET['notif_action'] === 'delete_all') {
        if ($_notif_is_admin) {
            $conn->query("UPDATE notifications SET is_deleted = 1");
        } else {
            $stmt = $conn->prepare("UPDATE notifications SET is_deleted = 1 WHERE user_id = ? OR user_id = 0");
            $stmt->bind_param("i", $_notif_uid);
            $stmt->execute();
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_GET['notif_action'] === 'get_count') {
        if ($_notif_is_admin) {
            $res = $conn->query("SELECT COUNT(*) AS cnt FROM notifications WHERE is_read = 0 AND is_deleted = 0");
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE is_read = 0 AND is_deleted = 0 AND (user_id = ? OR user_id = 0)");
            $stmt->bind_param("i", $_notif_uid);
            $stmt->execute();
            $res = $stmt->get_result();
        }
        echo json_encode(['count' => (int)$res->fetch_assoc()['cnt']]);
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

// ── Ensure columns exist ──────────────────────────────────────────────────────
$conn->query("ALTER TABLE notifications MODIFY id INT(11) AUTO_INCREMENT");
$conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS user_id INT(11) DEFAULT NULL");

// ── Sync notifications ────────────────────────────────────────────────────────

// 1. Tasks → notify the assigned user (user_id = assigned_to)
$newTasks = $conn->query("
    SELECT t.id, t.title, t.priority, t.assigned_to, t.created_at
    FROM tasks t
    LEFT JOIN notifications nb ON nb.type = 'task' AND nb.reference_id = t.id
    WHERE nb.id IS NULL
");
if ($newTasks && $newTasks->num_rows > 0) {
    $ins = $conn->prepare("INSERT INTO notifications (type, title, message, link, reference_id, user_id, created_at)
                           VALUES ('task', ?, ?, ?, ?, ?, ?)");
    while ($r = $newTasks->fetch_assoc()) {
        $title   = "New Task Assigned";
        $message = htmlspecialchars($r['title']) . " — " . htmlspecialchars($r['priority']) . " priority";
        $link    = "tasks.php";
        $uid     = intval($r['assigned_to']); // specific user sees this
        $ins->bind_param("ssssss", $title, $message, $link, $r['id'], $uid, $r['created_at']);
        $ins->execute();
    }
}

// 2. Notices → everyone (user_id = 0)
$noticePK = 'id';
$_pkRes = $conn->query("SHOW KEYS FROM notices WHERE Key_name = 'PRIMARY'");
if ($_pkRes && $_pkRow = $_pkRes->fetch_assoc()) { $noticePK = $_pkRow['Column_name']; }

$newNotices = $conn->query("
    SELECT nc.`{$noticePK}` AS notice_pk, nc.notice_title, nc.`date`
    FROM notices nc
    LEFT JOIN notifications nb ON nb.type = 'notice' AND nb.reference_id = nc.`{$noticePK}`
    WHERE nb.id IS NULL
");
if ($newNotices && $newNotices->num_rows > 0) {
    $ins = $conn->prepare("INSERT INTO notifications (type, title, message, link, reference_id, user_id, created_at)
                           VALUES ('notice', ?, ?, ?, ?, 0, ?)");
    while ($r = $newNotices->fetch_assoc()) {
        $title = "New Notice Posted";
        $msg   = htmlspecialchars($r['notice_title']);
        $link  = "manage_notices.php";
        $ref   = $r['notice_pk'];
        $dt    = $r['date'];
        $ins->bind_param("sssss", $title, $msg, $link, $ref, $dt);
        $ins->execute();
    }
}

// 3. Resources → everyone (user_id = 0)
$newResources = $conn->query("
    SELECT rs.id, rs.title, rs.created_at
    FROM resources rs
    LEFT JOIN notifications nb ON nb.type = 'resource' AND nb.reference_id = rs.id
    WHERE nb.id IS NULL
");
if ($newResources && $newResources->num_rows > 0) {
    $ins = $conn->prepare("INSERT INTO notifications (type, title, message, link, reference_id, user_id, created_at)
                           VALUES ('resource', ?, ?, ?, ?, 0, ?)");
    while ($r = $newResources->fetch_assoc()) {
        $title = "New Resource Added";
        $msg   = htmlspecialchars($r['title']) . " has been added to resources";
        $link  = "manage_resources.php";
        $ins->bind_param("sssss", $title, $msg, $link, $r['id'], $r['created_at']);
        $ins->execute();
    }
}

// 4. New users → admin only (user_id = NULL)
$newUsers = $conn->query("
    SELECT u.user_id, u.name, u.role, u.created_at
    FROM users u
    LEFT JOIN notifications nb ON nb.type = 'user' AND nb.reference_id = u.user_id
    WHERE nb.id IS NULL
");
if ($newUsers && $newUsers->num_rows > 0) {
    $ins = $conn->prepare("INSERT INTO notifications (type, title, message, link, reference_id, user_id, created_at)
                           VALUES ('user', ?, ?, ?, ?, NULL, ?)");
    while ($r = $newUsers->fetch_assoc()) {
        $title = "New User Registered";
        $msg   = htmlspecialchars($r['name']) . " joined as " . htmlspecialchars($r['role']);
        $link  = "manage_users.php";
        $ins->bind_param("sssss", $title, $msg, $link, $r['user_id'], $r['created_at']);
        $ins->execute();
    }
}

// 5. Project Requests → admin only (user_id = NULL)
$tableCheck = $conn->query("SHOW TABLES LIKE 'project_requests'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $newRequests = $conn->query("
        SELECT pr.id, pr.name, pr.project_type, pr.created_at
        FROM project_requests pr
        LEFT JOIN notifications nb ON nb.type = 'project_request' AND nb.reference_id = pr.id
        WHERE nb.id IS NULL
    ");
    if ($newRequests && $newRequests->num_rows > 0) {
        $ins = $conn->prepare("INSERT INTO notifications (type, title, message, link, reference_id, user_id, created_at)
                               VALUES ('project_request', ?, ?, ?, ?, NULL, ?)");
        while ($r = $newRequests->fetch_assoc()) {
            $title = "New Project Request";
            $msg   = htmlspecialchars($r['name']) . " submitted a request for " . htmlspecialchars($r['project_type']);
            $link  = "project_requests.php";
            $ins->bind_param("sssss", $title, $msg, $link, $r['id'], $r['created_at']);
            $ins->execute();
        }
    }
}

// 6. Career Applications → admin only (user_id = NULL)
$tableCheck = $conn->query("SHOW TABLES LIKE 'career_applications'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $newApps = $conn->query("
        SELECT ca.id, ca.name, ca.position, ca.applied_at
        FROM career_applications ca
        LEFT JOIN notifications nb ON nb.type = 'career_application' AND nb.reference_id = ca.id
        WHERE nb.id IS NULL
    ");
    if ($newApps && $newApps->num_rows > 0) {
        $ins = $conn->prepare("INSERT INTO notifications (type, title, message, link, reference_id, user_id, created_at)
                               VALUES ('career_application', ?, ?, ?, ?, NULL, ?)");
        while ($r = $newApps->fetch_assoc()) {
            $title = "New Job Application";
            $msg   = htmlspecialchars($r['name']) . " applied for " . htmlspecialchars($r['position']);
            $link  = "manage_applications.php";
            $ins->bind_param("sssss", $title, $msg, $link, $r['id'], $r['applied_at']);
            $ins->execute();
        }
    }
}

// 7. Contact Messages → admin only (user_id = NULL)
$tableCheck = $conn->query("SHOW TABLES LIKE 'contacts'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $newContacts = $conn->query("
        SELECT ct.id, ct.name, ct.email, ct.created_at
        FROM contacts ct
        LEFT JOIN notifications nb ON nb.type = 'contact' AND nb.reference_id = ct.id
        WHERE nb.id IS NULL
    ");
    if ($newContacts && $newContacts->num_rows > 0) {
        $ins = $conn->prepare("INSERT INTO notifications (type, title, message, link, reference_id, user_id, created_at)
                               VALUES ('contact', ?, ?, ?, ?, NULL, ?)");
        while ($r = $newContacts->fetch_assoc()) {
            $title = "New Contact Message";
            $msg   = htmlspecialchars($r['name']) . " sent a message (" . htmlspecialchars($r['email']) . ")";
            $link  = "messages.php";
            $ins->bind_param("sssss", $title, $msg, $link, $r['id'], $r['created_at']);
            $ins->execute();
        }
    }
}

// 8. Reviews → admin only (user_id = NULL)
$tableCheck = $conn->query("SHOW TABLES LIKE 'reviews'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $newReviews = $conn->query("
        SELECT rv.id, rv.client_name, rv.company, rv.rating, rv.created_at
        FROM reviews rv
        LEFT JOIN notifications nb ON nb.type = 'review' AND nb.reference_id = rv.id
        WHERE nb.id IS NULL
    ");
    if ($newReviews && $newReviews->num_rows > 0) {
        $ins = $conn->prepare("INSERT INTO notifications (type, title, message, link, reference_id, user_id, created_at)
                               VALUES ('review', ?, ?, ?, ?, NULL, ?)");
        while ($r = $newReviews->fetch_assoc()) {
            $stars = str_repeat('<i class="fas fa-star"></i>', intval($r['rating']));
            $title = "New Review Submitted";
            $msg   = htmlspecialchars($r['client_name']) . " (" . htmlspecialchars($r['company']) . ") left a {$stars} review";
            $link  = "reviews.php";
            $ins->bind_param("sssss", $title, $msg, $link, $r['id'], $r['created_at']);
            $ins->execute();
        }
    }
}

// 9. Course Registrations → admin only (user_id = NULL)
$tableCheck = $conn->query("SHOW TABLES LIKE 'course_enrollments'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $newEnrollments = $conn->query("
        SELECT e.id, e.student_name, e.enrolled_at, c.title AS course_title
        FROM course_enrollments e
        LEFT JOIN courses c ON e.course_id = c.id
        LEFT JOIN notifications nb ON nb.type = 'course_registration' AND nb.reference_id = e.id
        WHERE nb.id IS NULL
    ");
    if ($newEnrollments && $newEnrollments->num_rows > 0) {
        $ins = $conn->prepare("INSERT INTO notifications (type, title, message, link, reference_id, user_id, created_at)
                               VALUES ('course_registration', ?, ?, ?, ?, NULL, ?)");
        while ($r = $newEnrollments->fetch_assoc()) {
            $title = "New Course Registration";
            $courseTitle = $r['course_title'] ? $r['course_title'] : 'a course';
            $msg   = htmlspecialchars($r['student_name']) . " enrolled in " . htmlspecialchars($courseTitle);
            $link  = "manage_courses.php?tab=registrations";
            $ins->bind_param("sssss", $title, $msg, $link, $r['id'], $r['enrolled_at']);
            $ins->execute();
        }
    }
}

// ── Fetch notifications based on role ─────────────────────────────────────────
// Admin  → everything
// User   → their tasks (user_id = their id) + shared content (user_id = 0)
//          NOT null rows (those are admin-only)
if ($_notif_is_admin) {
    $notifs = $conn->query("
        SELECT * FROM notifications
        WHERE is_deleted = 0
        ORDER BY created_at DESC LIMIT 15
    ");
} else {
    $stmt = $conn->prepare("
        SELECT * FROM notifications
        WHERE is_deleted = 0
          AND (user_id = ? OR user_id = 0)
        ORDER BY created_at DESC LIMIT 15
    ");
    $stmt->bind_param("i", $_notif_uid);
    $stmt->execute();
    $notifs = $stmt->get_result();
}

$allNotifs   = $notifs ? $notifs->fetch_all(MYSQLI_ASSOC) : [];
$unreadCount = 0;
foreach ($allNotifs as $n) { if (!$n['is_read']) $unreadCount++; }

// ── Helpers ───────────────────────────────────────────────────────────────────
if (!function_exists('notifIcon')) {
    function notifIcon($type) {
        return match($type) {
            'task'               => '<i class="fas fa-check-circle"></i>',
            'notice'             => '<i class="fas fa-bullhorn"></i>',
            'resource'           => '<i class="fas fa-book"></i>',
            'user'               => '<i class="fas fa-user"></i>',
            'project_request'    => '<i class="fas fa-clipboard"></i>',
            'career_application' => '<i class="fas fa-briefcase"></i>',
            'contact'            => '<i class="fas fa-envelope"></i>️',
            'review'             => '<i class="fas fa-star"></i>',
            'course_registration'=> '<i class="fas fa-graduation-cap"></i>',
            default              => '<i class="fas fa-bell"></i>',
        };
    }
}
if (!function_exists('notifColor')) {
    function notifColor($type) {
        return match($type) {
            'task'               => '#00b894',
            'notice'             => '#e17055',
            'resource'           => '#0984e3',
            'user'               => '#6c5ce7',
            'project_request'    => '#024442',
            'career_application' => '#7c3aed',
            'contact'            => '#0ea5e9',
            'review'             => '#d97706',
            'course_registration'=> '#10b981',
            default              => '#6b7280',
        };
    }
}
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $now  = new DateTime();
        $then = new DateTime($datetime);
        $diff = $now->diff($then);
        if ($diff->days >= 7) return date('M d', strtotime($datetime));
        if ($diff->days >= 1) return $diff->days . 'd ago';
        if ($diff->h >= 1)    return $diff->h . 'h ago';
        if ($diff->i >= 1)    return $diff->i . 'm ago';
        return 'Just now';
    }
}
?>

<div class="notif-wrap" id="notifWrap">
    <button class="notif-bell" id="notifBell" onclick="toggleNotifPanel()" aria-label="Notifications">
        <i class="fas fa-bell"></i>
        <span class="notif-badge <?= $unreadCount === 0 ? 'notif-badge-hidden' : '' ?>" id="notifBadge">
            <?= $unreadCount > 99 ? '99+' : ($unreadCount > 0 ? $unreadCount : '') ?>
        </span>
    </button>

    <div class="notif-panel" id="notifPanel">
        <div class="notif-panel-header">
            <div class="notif-panel-title">
                <i class="fas fa-bell"></i> Notifications
                <?php if ($unreadCount > 0): ?>
                    <span class="notif-unread-chip" id="unreadChip"><?= $unreadCount ?> new</span>
                <?php else: ?>
                    <span class="notif-unread-chip notif-badge-hidden" id="unreadChip"></span>
                <?php endif; ?>
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
                <?php if ($unreadCount > 0): ?>
                    <button class="notif-mark-all" id="markAllBtn" onclick="markAllRead()">Mark all read</button>
                <?php endif; ?>
                <?php if (!empty($allNotifs)): ?>
                    <button class="notif-clear-all" id="clearAllBtn" onclick="clearAll()">Clear all</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="notif-list" id="notifList">
            <?php if (empty($allNotifs)): ?>
                <div class="notif-empty" id="notifEmpty">
                    <div class="notif-empty-icon"><i class="fas fa-tada"></i></div>
                    <div>All caught up! No notifications.</div>
                </div>
            <?php else: ?>
                <?php foreach ($allNotifs as $n): ?>
                <div class="notif-item <?= !$n['is_read'] ? 'notif-unread' : '' ?>" id="notif-<?= $n['id'] ?>">
                    <div class="notif-icon-wrap" style="background:<?= notifColor($n['type']) ?>1a; color:<?= notifColor($n['type']) ?>;">
                        <?= notifIcon($n['type']) ?>
                    </div>
                    <div class="notif-content" onclick="handleNotifClick(<?= $n['id'] ?>, '<?= htmlspecialchars($n['link'] ?? '#') ?>')">
                        <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
                        <div class="notif-message"><?= htmlspecialchars($n['message']) ?></div>
                        <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
                    </div>
                    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0;">
                        <?php if (!$n['is_read']): ?>
                            <div class="notif-dot"></div>
                        <?php endif; ?>
                        <button class="notif-delete-btn" onclick="deleteNotif(event, <?= $n['id'] ?>)" title="Dismiss">×</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="notif-panel-footer">
            <a href="manage_notifications.php" class="notif-view-all">View all notifications →</a>
        </div>
    </div>
</div>

<style>
.notif-wrap { position: relative; display: inline-flex; align-items: center; }
.notif-bell { position: relative; background: none; border: none; font-size: 20px; cursor: pointer; padding: 6px 8px; border-radius: 8px; transition: background .2s; line-height: 1; display: flex; align-items: center; justify-content: center; }
.notif-bell:hover { background: rgba(0,0,0,.06); }
.notif-badge { position: absolute; top: 2px; right: 2px; min-width: 17px; height: 17px; background: #e53e3e; color: #fff; font-size: 10px; font-weight: 700; border-radius: 100px; display: flex; align-items: center; justify-content: center; padding: 0 4px; line-height: 1; border: 2px solid #fff; transition: transform .2s; }
.notif-badge-hidden { display: none !important; }
.notif-panel { position: absolute; top: calc(100% + 10px); right: 0; width: 360px; background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 16px 48px rgba(0,0,0,.14); z-index: 9999; display: none; flex-direction: column; overflow: hidden; animation: notifSlideIn .2s cubic-bezier(.22,.88,.36,1) both; }
.notif-panel.open { display: flex; }
@keyframes notifSlideIn { from { opacity: 0; transform: translateY(-8px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
.notif-panel-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 18px 12px; border-bottom: 1px solid #f0f0f0; gap: 8px; }
.notif-panel-title { font-size: 14px; font-weight: 800; color: #111; display: flex; align-items: center; gap: 8px; flex: 1; }
.notif-unread-chip { background: #e53e3e; color: #fff; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 100px; }
.notif-mark-all { font-size: 12px; color: var(--accent, #7cb800); font-weight: 600; background: none; border: none; cursor: pointer; white-space: nowrap; transition: color .2s; }
.notif-mark-all:hover { text-decoration: underline; }
.notif-clear-all { font-size: 12px; color: #999; font-weight: 600; background: none; border: none; cursor: pointer; white-space: nowrap; transition: color .2s; }
.notif-clear-all:hover { color: #e53e3e; text-decoration: underline; }
.notif-list { max-height: 400px; overflow-y: auto; overscroll-behavior: contain; }
.notif-list::-webkit-scrollbar { width: 4px; }
.notif-list::-webkit-scrollbar-track { background: transparent; }
.notif-list::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }
.notif-item { display: flex; align-items: flex-start; gap: 12px; padding: 13px 18px; border-bottom: 1px solid #f9f9f9; transition: background .15s; text-align: left; position: relative; }
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: #f9fafb; }
.notif-item.notif-unread { background: #f5fff8; }
.notif-item.notif-unread:hover { background: #edfff3; }
.notif-icon-wrap { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }
.notif-content { flex: 1; min-width: 0; cursor: pointer; }
.notif-title   { font-size: 13px; font-weight: 700; color: var(--primary) !important; margin-bottom: 2px; }
.notif-message { font-size: 12.5px; color: var(--primary) !important; line-height: 1.5; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.notif-time    { font-size: 11px; color: #aaa; margin-top: 4px; }
.notif-dot { width: 8px; height: 8px; border-radius: 50%; background: #e53e3e; flex-shrink: 0; margin-top: 2px; }
.notif-delete-btn { background: none; border: none; cursor: pointer; color: #ccc; font-size: 16px; line-height: 1; padding: 0 2px; transition: color .2s; font-weight: 700; }
.notif-delete-btn:hover { color: #e53e3e; }
.notif-empty { text-align: center; padding: 2.5rem 1rem; color: #999; font-size: 13.5px; }
.notif-empty-icon { font-size: 36px; margin-bottom: 8px; }
.notif-panel-footer { padding: 12px 18px; border-top: 1px solid #f0f0f0; text-align: center; }
.notif-view-all { font-size: 13px; color: #000; font-weight: 600; text-decoration: none; transition: color .2s; }
.notif-view-all:hover { opacity: 0.8; }
@media (max-width: 480px) { .notif-panel { width: calc(100vw - 24px); right: -12px; } }
</style>

<script>
let currentBadgeCount = <?= $unreadCount ?>;

function toggleNotifPanel() {
    document.getElementById('notifPanel').classList.toggle('open');
}
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('notifWrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('notifPanel').classList.remove('open');
    }
});
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
function deleteNotif(e, id) {
    e.stopPropagation();
    fetch(`?notif_action=delete&id=${id}`)
        .then(() => {
            const item = document.getElementById('notif-' + id);
            if (item) {
                if (item.classList.contains('notif-unread')) updateBadgeCount(-1);
                item.style.transition = 'opacity .25s, max-height .25s';
                item.style.opacity    = '0';
                item.style.maxHeight  = item.offsetHeight + 'px';
                setTimeout(() => { item.style.maxHeight = '0'; item.style.padding = '0'; item.style.overflow = 'hidden'; }, 10);
                setTimeout(() => { item.remove(); checkEmpty(); }, 300);
            }
        });
}
function clearAll() {
    if (!confirm('Clear all notifications?')) return;
    fetch('?notif_action=delete_all')
        .then(() => {
            document.getElementById('notifList').innerHTML =
                `<div class="notif-empty" id="notifEmpty"><div class="notif-empty-icon"><i class="fas fa-tada"></i></div><div>All caught up! No notifications.</div></div>`;
            updateBadgeCount(0, true);
            const chip = document.getElementById('unreadChip');
            const markBtn = document.getElementById('markAllBtn');
            const clearBtn = document.getElementById('clearAllBtn');
            if (chip)    chip.classList.add('notif-badge-hidden');
            if (markBtn) markBtn.remove();
            if (clearBtn) clearBtn.remove();
        });
}
function markAllRead() {
    fetch('?notif_action=mark_all_read')
        .then(() => {
            document.querySelectorAll('.notif-item').forEach(item => {
                item.classList.remove('notif-unread');
                const dot = item.querySelector('.notif-dot');
                if (dot) dot.remove();
            });
            updateBadgeCount(0, true);
            const chip = document.getElementById('unreadChip');
            const markBtn = document.getElementById('markAllBtn');
            if (chip)    chip.classList.add('notif-badge-hidden');
            if (markBtn) markBtn.remove();
        });
}
function checkEmpty() {
    const list = document.getElementById('notifList');
    if (list && list.querySelectorAll('.notif-item').length === 0) {
        list.innerHTML = `<div class="notif-empty" id="notifEmpty"><div class="notif-empty-icon"><i class="fas fa-tada"></i></div><div>All caught up! No notifications.</div></div>`;
        const clearBtn = document.getElementById('clearAllBtn');
        if (clearBtn) clearBtn.remove();
    }
}
function updateBadgeCount(delta, reset = false) {
    const badge = document.getElementById('notifBadge');
    if (!badge) return;
    currentBadgeCount = reset ? 0 : Math.max(0, currentBadgeCount + delta);
    if (currentBadgeCount <= 0) {
        badge.classList.add('notif-badge-hidden');
        badge.textContent = '';
    } else {
        badge.classList.remove('notif-badge-hidden');
        badge.textContent = currentBadgeCount > 99 ? '99+' : currentBadgeCount;
    }
}
setInterval(function() {
    fetch('?notif_action=get_count')
        .then(r => r.json())
        .then(data => {
            if (data.count !== currentBadgeCount) {
                const prev = currentBadgeCount;
                currentBadgeCount = data.count;
                const badge = document.getElementById('notifBadge');
                if (!badge) return;
                if (currentBadgeCount <= 0) {
                    badge.classList.add('notif-badge-hidden');
                    badge.textContent = '';
                } else {
                    badge.classList.remove('notif-badge-hidden');
                    badge.textContent = currentBadgeCount > 99 ? '99+' : currentBadgeCount;
                    if (data.count > prev) {
                        badge.style.transform = 'scale(1.3)';
                        setTimeout(() => badge.style.transform = '', 300);
                    }
                }
            }
        });
}, 60000);
</script>