<?php
require_once __DIR__ . '/../core/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please log in.']);
    exit();
}

$user_id = intval($_SESSION['user_id'] ?? 0);
$role = strtolower($_SESSION['role'] ?? '');

if (empty($role) && $conn) {
    $u_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $u_stmt->bind_param("i", $user_id);
    $u_stmt->execute();
    $role = strtolower($u_stmt->get_result()->fetch_assoc()['role'] ?? 'user');
    $_SESSION['role'] = $role;
}

// Only internal staff and admins can access internal messages
if (!in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'manager', 'hrm', 'sales', 'user'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden. Internal staff access only.']);
    exit();
}

if (!$conn) {
    echo json_encode([]);
    exit();
}

$conn->query("CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$sql = "SELECT m.id, m.message, u.username, m.user_id, m.timestamp 
        FROM messages m 
        JOIN users u ON m.user_id = u.user_id 
        ORDER BY m.timestamp ASC";
$result = $conn->query($sql);

$messages = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => intval($row['id']),
            'message' => htmlspecialchars($row['message']),
            'username' => htmlspecialchars($row['username']),
            'user_id' => intval($row['user_id']),
            'timestamp' => $row['timestamp']
        ];
    }
}

echo json_encode($messages);
exit();
?>
