<?php
require_once __DIR__ . '/../core/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['super_admin', 'superadmin'])) {
    header("Location: index.php?error=unauthorized");
    exit;
}

header("Location: messages.php");
exit;
