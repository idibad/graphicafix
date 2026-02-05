<?php
    include('dashboard_header.php');

   
// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Access denied. Please login.");
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'] ?? 'user'; // assuming role stored in session

// Determine query based on role
if ($current_user_role === 'admin') {
    // Admin sees all logs
    $sql = "SELECT id, user_id, login_time, ip_address, user_agent, status 
            FROM login_logs 
            ORDER BY login_time DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) die("Prepare failed: " . $conn->error);
} else {
    // Regular user sees only their own logs
    $sql = "SELECT id, user_id, login_time, ip_address, user_agent, status 
            FROM login_logs 
            WHERE user_id = ? 
            ORDER BY login_time DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) die("Prepare failed: " . $conn->error);
    $stmt->bind_param("i", $current_user_id);
}

$stmt->execute();
$result = $stmt->get_result();


?>
<div class="main-card">
    <div class="main-header">
        <h3>🕒 My Login History</h3>
        <button class="add-client">Download</button>
    </div>

    <div class="main-table">

        <div class="table-head">
            <span>Date</span>
            <span>Time</span>
            <span>IP Address</span>
            <span>Device</span>
            <span>Status</span>
            <span></span>
        </div>
        
        <?php
        
        while ($row = $result->fetch_assoc()):
            // Format date and time
            $date = date('d M Y', strtotime($row['login_time']));
            $time = date('h:i A', strtotime($row['login_time']));

            // Browser/OS detection
            $os = "Unknown OS";
            $browser = "Unknown Browser";
            if (!empty($row['user_agent'])) {
                if (preg_match('/Windows/i', $row['user_agent'])) $os = "Windows";
                elseif (preg_match('/Linux/i', $row['user_agent'])) $os = "Linux";
                elseif (preg_match('/Mac/i', $row['user_agent'])) $os = "Mac";

                if (preg_match('/Chrome/i', $row['user_agent'])) $browser = "Chrome";
                elseif (preg_match('/Firefox/i', $row['user_agent'])) $browser = "Firefox";
                elseif (preg_match('/Safari/i', $row['user_agent'])) $browser = "Safari";
            }

            $statusClass = $row['status'] === 'success' ? 'active' : 'failed';
            $statusText = ucfirst($row['status']);

        ?>

        <div class="table-row">
        <span><?= $date ?></span>
        <span><?= $time ?></span>
        <span><?= htmlspecialchars($row['ip_address']) ?></span>
        <span><?= $os ?> • <?= $browser ?></span>
        <span class="status <?= $statusClass ?>"><?= $statusText ?></span>
        <button class="dots">⋮</button>
</div>
<?php
endwhile;

$stmt->close();
$conn->close();
?>

        

    </div>
</div>

<?php
    include('dashboard_footer.php');
?>