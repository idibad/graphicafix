<?php

// Prevent caching
// header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
// header("Pragma: no-cache"); // HTTP 1.0
// header("Expires: 0"); // Proxies

require_once $_SERVER['DOCUMENT_ROOT'] . '/graphicafix/config.php';

session_start();

// Redirect to login if not logged in
if(!isset($_SESSION['username'])){
    header("Location: login.php");
    exit;
}

// Fetch logged-in user details
$username = $_SESSION['username'];
$query = "SELECT * FROM users WHERE username = '$username'";
$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

$name = $data['name'];
$phone = $data['phone'];
$email = $data['email'];
$user_id = $data['user_id'];
// $designation = $data['designation'];
$role = $data['role']; // admin, team_member, client


// Role-based menu definitions
$menus = [
    'admin' => [
    ['title' => 'Dashboard', 'link' => 'index.php', 'icon' => 'fas fa-tachometer-alt'],
    ['title' => 'Projects', 'link' => 'manage_projects.php', 'icon' => 'fas fa-tasks'],
    ['title' => 'Tasks', 'link' => 'tasks.php', 'icon' => 'fas fa-check-square'],
    ['title' => 'Manage Clients', 'link' => 'manage_clients.php', 'icon' => 'fas fa-users'],
    ['title' => 'Manage Services', 'link' => 'manage_services.php', 'icon' => 'fas fa-cogs'],
    ['title' => 'Reviews', 'link' => 'reviews.php', 'icon' => 'fas fa-star'],
    ['title' => 'Messages', 'link' => 'messages.php', 'icon' => 'fas fa-envelope'],
    ['title' => 'Project Requests', 'link' => 'project_requests.php', 'icon' => 'fas fa-paper-plane'],
    ['title' => 'Manage Users', 'link' => 'manage_users.php', 'icon' => 'fas fa-users'],
    ['title' => 'Manage Applications', 'link' => 'manage_applications.php', 'icon' => 'fas fa-file-alt'],
    ['title' => 'Entertainment', 'link' => 'entertainment.php', 'icon' => 'fas fa-gamepad'],
    ['title' => 'Manage Invoices', 'link' => 'manage_invoices.php', 'icon' => 'fas fa-file-invoice-dollar'], // added
    ['title' => 'Settings', 'link' => 'profile.php', 'icon' => 'fas fa-cog'],
],
    'user' => [
        ['title' => 'Dashboard', 'link' => 'index.php', 'icon' => 'fas fa-tachometer-alt'],
        ['title' => 'Projects', 'link' => 'manage_projects.php', 'icon' => 'fas fa-tasks'],
        ['title' => 'Tasks', 'link' => 'tasks.php', 'icon' => 'fas fa-check-square'],
        ['title' => 'Messages', 'link' => 'messages.php', 'icon' => 'fas fa-envelope'],
        ['title' => 'Entertainment', 'link' => 'entertainment.php', 'icon' => 'fas fa-gamepad'],
        ['title' => 'Settings', 'link' => 'profile.php', 'icon' => 'fas fa-cog'],
    ],
    'client' => [
        ['title' => 'Dashboard', 'link' => 'index.php', 'icon' => 'fas fa-tachometer-alt'],
        ['title' => 'My Projects', 'link' => 'client_projects.php', 'icon' => 'fas fa-tasks'],
        ['title' => 'Invoices', 'link' => 'invoices.php', 'icon' => 'fas fa-file-invoice'],
        ['title' => 'Messages', 'link' => 'messages.php', 'icon' => 'fas fa-envelope'],
        ['title' => 'Settings', 'link' => 'profile.php', 'icon' => 'fas fa-cog'],
    ]
];

if ($role !== 'admin') {
    $can_edit = false; // used in your HTML/UI
} else {
    $can_edit = true;

} 
?>


<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1, shrink-to-fit=no'>
    <title>Graphicafix - Dashboard</title>
    <link href="<?= BASE_URL ?>css/bootstrap.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>css/all.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/icon.png">
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css' rel='stylesheet'>
       <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">

    <script type='text/javascript' src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js'></script>
    <link href="<?= BASE_URL ?>/css/dashboard.css" rel="stylesheet">

   <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

</head>
<body id="body-pd" class="body-pd">

<!-- Header -->
<div class="header body-pd mb-5" id="header">
    <div class="header_toggle"> 
        <i class='bx bx-menu' id="header-toggle"></i> 
    </div>
    <div class="dropdown" style="width: 200px;">
        <div class="header-menu dropdown-toggle" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <?php include('notifications.php'); ?>

            <div class="profile-icon">
                <span><?php echo strtoupper(substr($name,0,1)); ?></span>
            </div>
            <div><?php echo $name; ?></div>
        </div>
        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
            <a class="dropdown-item" href="profile.php">Profile</a>
            <a class="dropdown-item" href="login_history.php">Login History</a>
            <a class="dropdown-item" href="logout.php">Logout</a>
        </div>
    </div>
</div>

<!-- Sidebar -->
<div class="l-navbar show" id="nav-bar">
    <nav class="nav">
        <div>
            <a href="#" class="nav_logo"> 
                <span class="nav_logo-name"><img src="<?= BASE_URL ?>images/logo-secondary.png" width='100px'></span> 
            </a>
            <div class="nav_list">
                <?php
                if(isset($menus[$role])){
                    foreach($menus[$role] as $menu){
                        $active = (basename($_SERVER['PHP_SELF']) == basename($menu['link'])) ? 'active' : '';
                        echo "<a class='nav_link $active' href='{$menu['link']}'>
                                <i class='{$menu['icon']} nav_icon'></i>
                                <span class='nav_name'>{$menu['title']}</span>
                              </a>";
                    }
                }
                ?>
                <!-- Sign Out link always visible -->
                <a href="logout.php" class="nav_link">
                    <i class='fas fa-sign-out-alt nav_icon'></i>
                    <span class="nav_name">Sign Out</span>
                </a>
            </div>
        </div>
    </nav>
</div>


