<?php 
require_once 'config.php';
// =============================
// HELPER
// =============================
function clean($data) {
    return trim(htmlspecialchars($data));
}

// =============================
// VALIDATE REQUEST
// =============================
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request.");
}

$name = clean($_POST['name'] ?? '');
$company = clean($_POST['company'] ?? '');
$email = clean($_POST['email'] ?? '');
$phone = clean($_POST['phone'] ?? '');
$projectType = clean($_POST['projectType'] ?? '');
$budget = clean($_POST['budget'] ?? '');
$timeframe = clean($_POST['time'] ?? '');
$description = clean($_POST['description'] ?? '');

if (!$name || !$email || !$phone || !$projectType || !$timeframe || !$description) {
    die("Required fields missing.");
}

// =============================
// FILE UPLOAD
// =============================
$attachmentPath = null;

if (!empty($_FILES['attachment']['name'])) {

    $file = $_FILES['attachment'];

    if ($file['size'] > $maxFileSize) {
        die("File too large.");
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedTypes)) {
        die("Invalid file type.");
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $newName = uniqid() . "." . $ext;
    $target = $uploadDir . $newName;

    if (move_uploaded_file($file['tmp_name'], $target)) {
        $attachmentPath = $target;
    }
}

// =============================
// INSERT INTO DATABASE
// =============================
$stmt = $conn->prepare("
INSERT INTO project_requests
(name, company, email, phone, project_type, budget, timeframe, description, attachment)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "sssssdsss",
    $name,
    $company,
    $email,
    $phone,
    $projectType,
    $budget,
    $timeframe,
    $description,
    $attachmentPath
);

if ($stmt->execute()) {

    // SUCCESS RESPONSE
    header("Location: thank-you.php");
    exit;

} else {

    die("Submission failed.");

}

$stmt->close();
$conn->close();
?>