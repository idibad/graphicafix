<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ishalmany";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT messages.id, messages.message, users.username, messages.user_id FROM messages JOIN users ON messages.user_id = users.user_id ORDER BY messages.timestamp ASC";
$result = $conn->query($sql);

$messages = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
}

echo json_encode($messages);

$conn->close();
?>
