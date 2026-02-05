<?php
include('config.php');
// User data to insert
$name = "test user";
$phone = "user";
$email = "tx@gmail.com";
$username = "user";
$role = "user";
$password = "user"; // plain text password

// Hash the password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Prepare SQL statement
$stmt = $conn->prepare("INSERT INTO users (name, phone, email, username, role, password) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssss", $name, $phone, $email, $username, $role, $hashedPassword);

// Execute
if ($stmt->execute()) {
    echo "User inserted successfully!";
} else {
    echo "Error: " . $stmt->error;
}

// Close
$stmt->close();
$conn->close();
?>