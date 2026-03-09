<?php
include('dashboard_header.php');

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

// Fetch user data
$query = "SELECT * FROM users WHERE username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

$name = $data['name'];
$phone = $data['phone'];
$email = $data['email'];
$hashed_password = $data['password'];
$role = $data['role'];

$alert = '';

if (isset($_POST['update'])) {
    $up_name = mysqli_real_escape_string($conn, $_POST['up_name'] ?? '');
    $up_email = mysqli_real_escape_string($conn, $_POST['up_email'] ?? '');
    $up_phone = mysqli_real_escape_string($conn, $_POST['up_phone'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Flag to track if password should be updated
    $update_password = false;

    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        // Verify current password
        if (!password_verify($current_password, $hashed_password)) {
            $alert = 'Current password is incorrect!';
        } elseif ($new_password !== $confirm_password) {
            $alert = 'New passwords do not match!';
        } elseif (strlen($new_password) < 8) {
            $alert = 'New password must be at least 8 characters long!';
        } else {
            $update_password = true;
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        }
    }

    if (empty($alert)) {
        // Build update query
        if ($update_password) {
            $update = "UPDATE users SET name=?, email=?, phone=?, password=? WHERE username=?";
            $stmt = $conn->prepare($update);
            $stmt->bind_param('sssss', $up_name, $up_email, $up_phone, $hashed_password, $username);
        } else {
            $update = "UPDATE users SET name=?, email=?, phone=? WHERE username=?";
            $stmt = $conn->prepare($update);
            $stmt->bind_param('ssss', $up_name, $up_email, $up_phone, $username);
        }

        if ($stmt->execute()) {
            $alert = 'Profile updated successfully!';
            // Refresh data
            $name = $up_name;
            $email = $up_email;
            $phone = $up_phone;
        } else {
            $alert = 'Failed to update profile!';
        }
    }

    if (!empty($alert)) {
        echo "<script>alert('{$alert}');</script>";
    }
}
?>

<div class="height-100">
    <div class="profile-card">
        <div class="profile-header">
            <h3>✏️ Edit Profile</h3>
        </div>

        <form method="POST" class="profile-body">
            <div class="section-title">Personal Information</div>

            <div class="profile-row">
                <label>Name</label>
                <input type="text" name="up_name" value="<?= htmlspecialchars($name) ?>" required>
            </div>

            <div class="profile-row">
                <label>Username</label>
                <input type="text" value="<?= htmlspecialchars($username) ?>" disabled>
            </div>

            <div class="profile-row">
                <label>Email</label>
                <input type="email" name="up_email" value="<?= htmlspecialchars($email) ?>" required>
            </div>

            <div class="profile-row">
                <label>Phone</label>
                <input type="text" name="up_phone" value="<?= htmlspecialchars($phone) ?>">
            </div>

            <div class="section-divider"></div>

            <div class="section-title">Change Password <span style="font-size: 12px; color: #888; font-weight: 400;">(Optional)</span></div>

            <div class="profile-row">
                <label>Current Password</label>
                <input type="password" name="current_password" placeholder="Enter current password">
            </div>

            <div class="profile-row">
                <label>New Password</label>
                <input type="password" name="new_password" placeholder="Enter new password (min. 8 characters)">
            </div>

            <div class="profile-row">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm new password">
            </div>

            <button type="submit" name="update" class="main-btn">Save Changes</button>
        </form>
    </div>
</div>

<style>
/* Keep your existing styles */
.profile-card {
    background: #fff;
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    max-width: 600px;
    margin: 50px auto;
}
.profile-header { padding-bottom: 20px; border-bottom: 2px solid #eee; margin-bottom: 30px; }
.profile-header h3 { font-size: 24px; font-weight: 700; color: #222; }
.profile-body { display: flex; flex-direction: column; gap: 18px; }
.section-title { font-size: 16px; font-weight: 700; color: #333; margin-top: 10px; margin-bottom: 5px; }
.section-divider { height: 1px; background: #eee; margin: 20px 0; }
.profile-row { display: flex; flex-direction: column; gap: 8px; }
.profile-row label { font-size: 14px; color: #555; font-weight: 600; }
.profile-row input { background: #f8f8f8; border: 1px solid #e5e5e5; padding: 14px 16px; border-radius: 10px; font-size: 14px; outline: none; transition: all 0.2s; }
.profile-row input:focus { background: #fff; border-color: #024442; box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.1); }
.profile-row input:disabled { background: #eee; color: #777; cursor: not-allowed; }
.main-btn { margin-top: 15px; padding: 14px 24px; background: #024442; color: white; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
.main-btn:hover { background: #035b58; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(2, 68, 66, 0.2); }
@media (max-width: 768px) { .profile-card { margin: 20px; padding: 24px; } .profile-header h3 { font-size: 20px; } }
</style>

<?php include('dashboard_footer.php'); ?>
