<?php

header('Content-Type: application/json');

    require_once('../config.php');
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}


$id      = intval($_POST['id']      ?? 0);
$visible = intval($_POST['visible'] ?? 0); // 1 or 0

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$stmt = $conn->prepare("UPDATE reviews SET visible = ? WHERE id = ?");
$stmt->bind_param("ii", $visible, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'visible' => $visible]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}

$stmt->close();
$conn->close();
?>