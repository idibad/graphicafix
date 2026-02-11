<?php
   
include('dashboard_header.php');
    
    if(isset($_SESSION['username'])){
		$username = $_SESSION['username'];
        $query = "SELECT * FROM users WHERE username = '$username'";
        $result = mysqli_query($conn, $query);

    $data = mysqli_fetch_assoc($result);
        $name = $data['name'];
        $phone = $data['phone'];
        $email = $data['email'];
        $password = $data['password'];
        $role = $data['role'];
        
	}else{
		header("Location: login.php");
	}

    //function to make the password encoded
    function maskText($text, $visibleChars = 1) {
        $maskedText = str_repeat('*', strlen($text) - $visibleChars) . substr($text, -$visibleChars);
        return $maskedText;
    }

    $maskpass = maskText($password);
?>

    <!-- Page content-->
    <div class="height-100" >
    <div class="profile-card">
    <div class="profile-header">
        <h3>👤 Your Profile</h3>
        <a href="profile_edit.php" class="main-btn">Edit</a>
    </div>

    <div class="profile-body">
        <div class="profile-row">
            <span>Name</span>
            <strong><?php echo $name; ?></strong>
        </div>

        <div class="profile-row">
            <span>Username</span>
            <strong><?php echo $username; ?></strong>
        </div>

        <div class="profile-row">
            <span>Email</span>
            <strong><?php echo $email; ?></strong>
        </div>

        <div class="profile-row">
            <span>Phone</span>
            <strong><?php echo $phone; ?></strong>
        </div>

        <div class="profile-row">
            <span>Password</span>
            <strong><?php echo $maskpass; ?></strong>
        </div>
    </div>
</div>


    </div>
    <style>
        .profile-card {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.06);
    max-width: 500px;
    margin:100px auto;
}

.profile-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.profile-header h3 {
    font-size: 18px;
    font-weight: 600;
}

.profile-body {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.profile-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 14px;
    background: #f7f7f7;
    border-radius: 10px;
    font-size: 14px;
}

.profile-row span {
    color: #888;
}

.profile-row strong {
    color: #222;
}

    </style>
<?php 
include('dashboard_footer.php');
?>