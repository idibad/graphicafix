<?php
include('dashboard_header.php');

$package_id = intval($_GET['id'] ?? 0);
$service_id = intval($_GET['service_id'] ?? 0);

if (!$package_id) {
    echo "<script>alert('Invalid package ID.');window.location='manage_services.php';</script>";
    exit;
}

// Verify package exists and belongs to the given service
$stmt = $conn->prepare("SELECT id, name, service_id FROM service_packages WHERE id = ?");
$stmt->bind_param("i", $package_id);
$stmt->execute();
$package = $stmt->get_result()->fetch_assoc();

if (!$package) {
    echo "<script>alert('Package not found.');window.location='manage_services.php';</script>";
    exit;
}

// Use service_id from DB record (not just URL param) for security
$service_id = $package['service_id'];

// 1. Delete package features
$stmt = $conn->prepare("DELETE FROM service_package_features WHERE package_id = ?");
$stmt->bind_param("i", $package_id);
$stmt->execute();

// 2. Delete the package
$stmt = $conn->prepare("DELETE FROM service_packages WHERE id = ?");
$stmt->bind_param("i", $package_id);
$stmt->execute();

if ($conn->affected_rows >= 0) {
    echo "<script>alert('Package \"" . addslashes($package['name']) . "\" has been deleted successfully.');window.location='service_details.php?id={$service_id}';</script>";
} else {
    echo "<script>alert('Something went wrong. Please try again.');window.location='service_details.php?id={$service_id}';</script>";
}
exit;
?>