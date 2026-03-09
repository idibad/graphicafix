<?php
include('dashboard_header.php');

$service_id = intval($_GET['id'] ?? 0);

if (!$service_id) {
    echo "<script>alert('Invalid service ID.');window.location='manage_services.php';</script>";
    exit;
}

// Verify service exists
$stmt = $conn->prepare("SELECT id, title FROM services WHERE id = ?");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();

if (!$service) {
    echo "<script>alert('Service not found.');window.location='manage_services.php';</script>";
    exit;
}

// ── Delete in correct order (features → packages → service) ──────────────

// 1. Delete all features belonging to this service's packages
$stmt = $conn->prepare("
    DELETE spf FROM service_package_features spf
    JOIN service_packages sp ON spf.package_id = sp.id
    WHERE sp.service_id = ?
");
$stmt->bind_param("i", $service_id);
$stmt->execute();

// 2. Delete all packages belonging to this service
$stmt = $conn->prepare("DELETE FROM service_packages WHERE service_id = ?");
$stmt->bind_param("i", $service_id);
$stmt->execute();

// 3. Delete the service itself
$stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
$stmt->bind_param("i", $service_id);
$stmt->execute();

if ($conn->affected_rows >= 0) {
    echo "<script>alert('Service \"" . addslashes($service['title']) . "\" has been deleted successfully.');window.location='manage_services.php';</script>";
} else {
    echo "<script>alert('Something went wrong. Please try again.');window.location='manage_services.php';</script>";
}
exit;
?>