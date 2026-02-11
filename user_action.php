<?php
include('dashboard_header.php');

$action = $_GET['action'] ?? '';

if($action == 'fetch' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $res = mysqli_query($conn, "SELECT user_id, name, email, role FROM users WHERE user_id=$id");
    echo json_encode(mysqli_fetch_assoc($res));
    exit;
}

if($action == 'save') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $password = $_POST['password'] ?? '';

    if($user_id) {
        // Update user
        $sql = "UPDATE users SET name='$name', email='$email', role='$role'";
        if(!empty($password)) $sql .= ", password='".password_hash($password, PASSWORD_DEFAULT)."'";
        $sql .= " WHERE user_id=$user_id";
    } else {
        // Add new user
        $sql = "INSERT INTO users (name,email,role,password) VALUES ('$name','$email','$role','".password_hash($password, PASSWORD_DEFAULT)."')";
    }

    if(mysqli_query($conn, $sql)) echo json_encode(['success'=>true]);
    else echo json_encode(['success'=>false,'message'=>mysqli_error($conn)]);
    exit;
}

if($action == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if(mysqli_query($conn, "DELETE FROM users WHERE user_id=$id")) echo json_encode(['success'=>true]);
    else echo json_encode(['success'=>false,'message'=>mysqli_error($conn)]);
    exit;
}
?>
