<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('config.php'); // DB connection

if(isset($_POST['id'], $_POST['status'])){
    $task_id = intval($_POST['id']);
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $task_id);

    if($stmt->execute()){
        echo "Updated task $task_id to $status";
    } else {
        echo "DB Error: ".$conn->error;
    }
} else {
    echo "Invalid request: ".print_r($_POST,true);
}
?>
