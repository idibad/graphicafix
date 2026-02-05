<?php
// LOGIN LOGS
function logLogin($conn, $user_id, $status){
    $ip = $_SERVER['REMOTE_ADDR'];
    $agent = $_SERVER['HTTP_USER_AGENT'];

    $stmt = $conn->prepare("
        INSERT INTO login_logs (user_id, login_time, ip_address, user_agent, status)
        VALUES (?, NOW(), ?, ?, ?)
    ");

    $stmt->bind_param("isss", $user_id, $ip, $agent, $status);
    $stmt->execute();
}


?>