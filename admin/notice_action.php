<?php
require_once '../core/config.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

if (!$conn) { echo json_encode(['success' => false, 'message' => 'DB error']); exit; }

$action = $_POST['action'] ?? '';

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
    $title  = trim($_POST['notice_title'] ?? '');
    $body   = trim($_POST['notice_body']  ?? '');
    $date   = $_POST['date'] ?? date('Y-m-d');
    $by     = $_SESSION['name'] ?? 'Admin';

    if (!$title) { echo json_encode(['success' => false, 'message' => 'Title is required']); exit; }

    $stmt = $conn->prepare("INSERT INTO notices (notice_title, content, `date`, created_at, created_by) VALUES (?, ?, ?, NOW(), ?)");
    $stmt->bind_param("ssss", $title, $body, $date, $by);
    if ($stmt->execute()) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Insert failed']);
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
    if ($stmt->execute()) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Update failed']);
    exit;
}

// ── Delete notice ─────────────────────────────────────────────────────────────
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }

    $stmt = $conn->prepare("DELETE FROM notices WHERE notice_id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Delete failed']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);