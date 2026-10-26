<?php
require_once '../core/config.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

if (!$conn) { echo json_encode(['success' => false, 'message' => 'DB error']); exit; }

$action = $_POST['action'] ?? '';
$user_role = strtolower($_SESSION['role'] ?? '');
$is_admin = in_array($user_role, ['super_admin', 'superadmin', 'admin', 'manager', 'hrm', 'pm']);

if (in_array($action, ['add', 'edit', 'delete']) && !$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Forbidden: insufficient permissions']); exit;
}

// ── Get single notice ─────────────────────────────────────────────────────────
if ($action === 'get') {
    $id   = intval($_POST['id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM notices WHERE notice_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) echo json_encode(['success' => true, 'notice' => $row]);
    else      echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

// ── Add notice ────────────────────────────────────────────────────────────────
if ($action === 'add') {
    $title      = trim($_POST['notice_title'] ?? '');
    $body       = trim($_POST['notice_body']  ?? '');
    $date       = $_POST['date'] ?? date('Y-m-d');
    $by         = $_SESSION['name'] ?? 'Admin';
    $send_email = isset($_POST['send_email']) ? ($_POST['send_email'] == '1' || $_POST['send_email'] === 'true') : true;

    if (!$title) { echo json_encode(['success' => false, 'message' => 'Title is required']); exit; }

    $stmt = $conn->prepare("INSERT INTO notices (notice_title, content, `date`, created_at, created_by) VALUES (?, ?, ?, NOW(), ?)");
    $stmt->bind_param("ssss", $title, $body, $date, $by);
    if ($stmt->execute()) {
        $new_notice_id = $conn->insert_id;

        // In-app notification for all users
        $notifStmt = $conn->prepare("INSERT INTO notifications (type, title, message, link, reference_id, user_id, created_at) VALUES ('notice', 'New Notice Posted', ?, 'manage_notices.php', ?, 0, NOW())");
        if ($notifStmt) {
            $notifStmt->bind_param("si", $title, $new_notice_id);
            @$notifStmt->execute();
        }

        // Email broadcast notification
        if ($send_email) {
            require_once __DIR__ . '/../core/notification_mailer.php';
            if (function_exists('notifyNoticePublished')) {
                @notifyNoticePublished($conn, $new_notice_id, $title, $body, $date, $by);
            }
        }

        logActivity($conn, 'create_notice', "Created notice: '$title' (Date: $date)", 'notice', $new_notice_id);
        echo json_encode(['success' => true, 'notice_id' => $new_notice_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Insert failed']);
    }
    exit;
}

// ── Edit notice ───────────────────────────────────────────────────────────────
if ($action === 'edit') {
    $id    = intval($_POST['notice_id'] ?? 0);
    $title = trim($_POST['notice_title'] ?? '');
    $body  = trim($_POST['notice_body']  ?? '');
    $date  = $_POST['date'] ?? date('Y-m-d');

    if (!$id || !$title) { echo json_encode(['success' => false, 'message' => 'Invalid data']); exit; }

    $stmt = $conn->prepare("UPDATE notices SET notice_title = ?, content = ?, `date` = ? WHERE notice_id = ?");
    $stmt->bind_param("sssi", $title, $body, $date, $id);
    if ($stmt->execute()) {
        logActivity($conn, 'edit_notice', "Updated notice #$id: '$title'", 'notice', $id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
    exit;
}

// ── Delete notice ─────────────────────────────────────────────────────────────
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }

    $stmt = $conn->prepare("DELETE FROM notices WHERE notice_id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        logActivity($conn, 'delete_notice', "Deleted notice #$id", 'notice', $id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Delete failed']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
