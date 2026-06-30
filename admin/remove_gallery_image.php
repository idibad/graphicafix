<?php
ob_start();
header('Content-Type: application/json');


  require_once('../config.php');
// ── Auth check ────────────────────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

ob_clean();
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid image ID']);
    exit;
}

// ── Fetch the image path before deleting ─────────────────────────────────────
$stmt = $conn->prepare("SELECT image_path FROM project_gallery WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Image not found']);
    exit;
}

$imagePath = $row['image_path'];

// ── Delete from project_gallery ───────────────────────────────────────────────
$stmt = $conn->prepare("DELETE FROM project_gallery WHERE id = ?");
$stmt->bind_param("i", $id);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to delete from gallery']);
    exit;
}
$stmt->close();

// ── Delete from project_files (same path) ────────────────────────────────────
$stmt = $conn->prepare("DELETE FROM project_files WHERE file_path = ?");
$stmt->bind_param("s", $imagePath);
$stmt->execute();
$stmt->close();

// ── Delete physical file from disk ────────────────────────────────────────────
$fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($imagePath, '/');
if (file_exists($fullPath)) {
    unlink($fullPath);
}

$conn->close();
echo json_encode(['success' => true]);