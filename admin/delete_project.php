<?php
include('dashboard_header.php');

$project_id = intval($_GET['id'] ?? 0);

if (!$project_id) {
    echo "<script>alert('Invalid project ID.');window.location='manage_projects.php';</script>";
    exit;
}

// Verify project exists
$stmt = $conn->prepare("SELECT id, project_name, thumbnail FROM projects WHERE id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    echo "<script>alert('Project not found.');window.location='manage_projects.php';</script>";
    exit;
}

// ── Collect physical files to delete from server ─────────────────────────────

$files_to_delete = [];

// Thumbnail
if (!empty($project['thumbnail']) && file_exists($project['thumbnail'])) {
    $files_to_delete[] = $project['thumbnail'];
}

// project_files (thumbnails, images, designs, documents)
$stmt = $conn->prepare("SELECT file_path FROM project_files WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if (!empty($row['file_path']) && file_exists($row['file_path'])) {
        $files_to_delete[] = $row['file_path'];
    }
}

// project_gallery images
$stmt = $conn->prepare("SELECT image_path FROM project_gallery WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if (!empty($row['image_path']) && file_exists($row['image_path'])) {
        $files_to_delete[] = $row['image_path'];
    }
}

// ── Delete from DB in correct order ──────────────────────────────────────────
// (CASCADE handles these automatically, but we delete explicitly for clarity)

// 1. project_team
$stmt = $conn->prepare("DELETE FROM project_team WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();

// 2. project_gallery
$stmt = $conn->prepare("DELETE FROM project_gallery WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();

// 3. project_files
$stmt = $conn->prepare("DELETE FROM project_files WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();

// 4. Delete the project itself
$stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();

if ($conn->affected_rows >= 0) {

    // ── Delete physical files from server ─────────────────────────────────────
    foreach ($files_to_delete as $file_path) {
        @unlink($file_path);
    }

    echo "<script>alert('Project \"" . addslashes($project['project_name']) . "\" has been deleted successfully.');window.location='manage_projects.php';</script>";
} else {
    echo "<script>alert('Something went wrong. Please try again.');window.location='manage_projects.php';</script>";
}
exit;
?>