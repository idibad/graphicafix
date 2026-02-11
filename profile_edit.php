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

    if(isset($_POST['update'])){
        $up_name = $_POST['up_name'];
        $up_email = $_POST['up_email'];
        $up_phone = $_POST['up_phone'];

        $update = "UPDATE users SET name = '$up_name' , email = '$up_email', phone = '$up_phone' WHERE username = '$username'";
        $update_result = mysqli_query($conn, $update);
        if($update_result){
           echo "<script> alert('Profile Updated');window.location.href='profile.php'</script>";
        }
    }
   
?>

    <!-- Page content-->
    <div class="height-100" >
        <div class="profile-card">
    <div class="profile-header">
        <h3>✏️ Edit Profile</h3>
    </div>

    <form method="POST" action="" class="profile-body">

        <div class="profile-row">
            <label>Name</label>
            <input type="text" name="up_name" value="<?php echo $name; ?>">
        </div>

        <div class="profile-row">
            <label>Username</label>
            <input type="text" value="<?php echo $username; ?>" disabled>
        </div>

        <div class="profile-row">
            <label>Email</label>
            <input type="email" name="up_email" value="<?php echo $email; ?>">
        </div>

        <div class="profile-row">
            <label>Phone</label>
            <input type="text" name="up_phone" value="<?php echo $phone; ?>">
        </div>

        <button type="submit" name="update" class="main-btn">Save Changes</button>

    </form>
</div>

    </div>
    
    <style>
       .profile-card {
    background: #fff;
    border-radius: 16px;
    padding: 22px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    max-width: 520px;
    margin: 100px auto;
}

.profile-header {
    padding-bottom: 14px;
    border-bottom: 1px solid #eee;
    margin-bottom: 20px;
}

.profile-header h3 {
    font-size: 18px;
    font-weight: 600;
    color: #222;
}

.profile-body {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.profile-row {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.profile-row label {
    font-size: 13px;
    color: #888;
    font-weight: 500;
}

.profile-row input {
    background: #f8f8f8;
    border: none;
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 14px;
    outline: none;
}

.profile-row input:disabled {
    background: #eee;
    color: #777;
}


    </style>
<?php 
include('dashboard_footer.php');
?>