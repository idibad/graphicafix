<?php

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

    require_once('config.php');
    session_start();
	if(isset($_SESSION['username'])){
		$username = $_SESSION['username'];
        $query = "SELECT * FROM users WHERE username = '$username'";
        $result = mysqli_query($conn, $query);

    $data = mysqli_fetch_assoc($result);
        $name = $data['name'];
        $phone = $data['phone'];
        $email = $data['email'];
	}else{
		header("Location: login.php");
	}
    
?>


<!DOCTYPE html>
<html>
  <head>
      <meta charset='utf-8'>
      <meta name='viewport' content='width=device-width, initial-scale=1'>
      <title>Graphicafix - Dashboard</title>
      <style>
          
          </style>
      <link href="css/bootstrap.css" rel="stylesheet">
      <link href="css/style.css" rel="stylesheet">
      <link href="css/all.css" rel="stylesheet">
      
        <link rel="icon" type="image/png" href="images/icon.png">
      <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css' rel='stylesheet'>
      <link href='https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css' rel='stylesheet'>
      <script type='text/javascript' src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js'></script>
      
      <link href="css/dashboard.css" rel="stylesheet">
</head>
<body className='snippet-body'>
<body id="body-pd" class="body-pd">

    <div class="header body-pd mb-5" id="header">
        <div class="header_toggle"> <i class='bx bx-menu' id="header-toggle"></i> </div>
        <!-- <div class="header-menu" style="display: flex;flex-wrap: wrap;background: transparent;border-radius: 5px;text-align: center;padding: 10px;justify-content: center;align-items: center;">
        <div class="img">
            <img src="images/team/blank.jpg" style="border-radius: 50px;width: 35px;height: 35px;object-fit: cover;box-shadow: 0 3px 5px #ccc;margin: 2px;">
        </div>
        <div style="margin: 2px;"> 
            <?php echo "$name";?> 
        </div> -->
        <div class="dropdown" style="width: 200px;">
    <div class="header-menu dropdown-toggle" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
      <div class="profile-icon">
        <span><?php 
        $profile_letter = substr($name, 0, 1);

        echo "$profile_letter"?></span>
      </div>
      <div>
        <?php echo "$name"; ?>
      </div>
    </div>
    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
      <a class="dropdown-item" href="profile.php">Profile</a>
      <a class="dropdown-item" href="login_history.php">Login History</a>
      <a class="dropdown-item" href="logout.php">Logout</a>
    </div>
  </div>
</div>
    </div>
    </div>
    <div class="l-navbar show" id="nav-bar">
        <nav class="nav">
            <div> 
            <a href="#" class="nav_logo"> 
            <span class="nav_logo-name"><img src="images/logo-secondary.png" width='100px'></span> 
            </a>
            <div class="nav_list">
            <a class="nav_link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
            <i class='fas fa-tachometer-alt nav_icon'></i> 
            <span class="nav_name">Dashboard</span>
            </a>
            <a class="nav_link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_projects.php') ? 'active' : ''; ?>" href="manage_projects.php">
            <i class='fas fa-tasks nav_icon'></i> 
            <span class="nav_name">Projects</span>
            </a>
             <a class="nav_link <?php echo (basename($_SERVER['PHP_SELF']) == 'tasks.php') ? 'active' : ''; ?>" href="tasks.php">
            <i class='fas fa-check-square nav_icon'></i> 
            <span class="nav_name">Tasks</span>
            </a>
            <a class="nav_link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_clients.php') ? 'active' : ''; ?>" href="manage_clients.php">
            <i class='fas fa-users nav_icon'></i> 
            <span class="nav_name">Manage Clients</span>
            </a>
            <a class="nav_link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_services.php') ? 'active' : ''; ?>" href="manage_services.php">
            <i class='fas fa-cogs nav_icon'></i> 
            <span class="nav_name">Manage Services</span>
            </a>
            <!-- <a class="nav_link <?php echo (basename($_SERVER['PHP_SELF']) == 'invoices.php') ? 'active' : ''; ?>" href="invoices.php">
            <i class='fas fa-file-invoice nav_icon'></i> 
            <span class="nav_name">Invoices</span>
            </a> -->
            <a class="nav_link <?php echo (basename($_SERVER['PHP_SELF']) == 'reviews.php') ? 'active' : ''; ?>" href="reviews.php">
            <i class='fas fa-star nav_icon'></i> 
            <span class="nav_name">Reviews</span>
            </a>
            <a class="nav_link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_users.php') ? 'active' : ''; ?>" href="manage_users.php">
            <i class='fas fa-users nav_icon'></i> 
            <span class="nav_name">Manage Users</span>
            </a>
            <a class="nav_link <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>" href="profile.php">
            <i class='fas fa-cog nav_icon'></i> 
            <span class="nav_name">Settings</span>
            </a>
            </div>  
            </div> 
            <a href="logout.php" class="nav_link"> 
            <i class='fas fa-sign-out-alt nav_icon'></i> 
            <span class="nav_name">Sign Out</span> 
            </a>
        </nav>
    </div>
