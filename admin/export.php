<?php
require_once __DIR__ . '/../core/config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Access Denied. Please log in.');
}

$user_id = intval($_SESSION['user_id']);
$u_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$u_row = $u_stmt->get_result()->fetch_assoc();
$role = strtolower($u_row['role'] ?? '');

if (!in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'manager'])) {
    http_response_code(403);
    die('Access Denied. Insufficient permissions to export project data.');
}

$projects_query = "SELECT * FROM projects ORDER BY created_at DESC";
$projects_result = mysqli_query($conn, $projects_query);

$table = ' <div class="main-table"><table border="1" cellpadding="5" cellspacing="0">
  <tr>
    <th>ID</th>
    <th>Project Title</th>
    <th>Client</th>
    <th>Creation Date</th>
    <th>Start Date</th>
    <th>End Date</th>
    <th>Status</th>
    <th>Priority</th>
  </tr>';

if ($projects_result) {
    while ($data = mysqli_fetch_array($projects_result)) {
        $table .= '
  <tr>
    <td>' . htmlspecialchars($data['id'] ?? '') . '</td>
    <td>' . htmlspecialchars($data['project_name'] ?? '') . '</td>
    <td>' . htmlspecialchars($data['client_id'] ?? '') . '</td>
    <td>' . htmlspecialchars($data['created_at'] ?? '') . '</td>
    <td>' . htmlspecialchars($data['start_date'] ?? '') . '</td>
    <td>' . htmlspecialchars($data['end_date'] ?? '') . '</td>
    <td>' . htmlspecialchars($data['status'] ?? '') . '</td>
    <td>' . htmlspecialchars($data['priority'] ?? '') . '</td>
  </tr>';
    }
}
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=projects_" . date('Y-m-d') . ".xls");

echo $table . "</table></div>";
exit;
?>
