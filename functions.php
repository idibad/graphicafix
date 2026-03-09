<?php
// LOGIN LOGS

function logLogin($conn, $user_id, $status) {
    // Get IP and User Agent
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    $stmt = $conn->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, user_agent, status) VALUES (?, NOW(), ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $ip_address, $user_agent, $status);
    $stmt->execute();
    $stmt->close();
}


?>