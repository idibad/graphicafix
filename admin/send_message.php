<?php
require_once __DIR__ . '/../core/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    exit();
}

$userId = intval($_SESSION['user_id'] ?? 0);
$role   = strtolower($_SESSION['role'] ?? '');

if (empty($role) && $conn) {
    $u_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $u_stmt->bind_param("i", $userId);
    $u_stmt->execute();
    $role = strtolower($u_stmt->get_result()->fetch_assoc()['role'] ?? 'user');
    $_SESSION['role'] = $role;
}

if (!in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'manager', 'hrm', 'sales', 'user'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Internal staff only']);
    exit();
}

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit();
}

$conn->query("CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;
$message = trim($data['message'] ?? '');

if (empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
    exit();
}

$stmt = $conn->prepare("INSERT INTO messages (user_id, message) VALUES (?, ?)");
if ($stmt) {
    $stmt->bind_param("is", $userId, $message);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to send message']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Query preparation failed']);
}
exit();
?>
