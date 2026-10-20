<?php
require_once __DIR__ . '/../core/config.php';

// Verify role access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'superadmin', 'admin', 'manager', 'pm'])) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

if (isset($_POST['id'], $_POST['status'])) {
    $id = intval($_POST['id']);
    $status = trim($_POST['status']);
    
    $allowed = ['pending', 'in progress', 'completed'];
    if (in_array($status, $allowed)) {
        $stmt = $conn->prepare("UPDATE project_deliverables SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        
        if ($stmt->execute()) {
            echo "Success";
        } else {
            http_response_code(500);
            echo "Failed to update status: " . $conn->error;
        }
        $stmt->close();
    } else {
        http_response_code(400);
        echo "Invalid status value";
    }
} else {
    http_response_code(400);
    echo "Bad request";
}
?>
