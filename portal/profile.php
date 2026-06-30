<?php
include('dashboard_header.php');

$user_id = intval($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $vtoken = trim($_POST['vimeo_api_token'] ?? '');
    
    if (!empty($_POST['new_password'])) {
        $pw = trim($_POST['new_password']);
        if (strlen($pw) >= 4) {
            $hashed = password_hash($pw, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, password = ?, vimeo_api_token = ? WHERE user_id = ?");
            $stmt->bind_param("ssssi", $name, $phone, $hashed, $vtoken, $user_id);
        } else {
            $error = "Password must be at least 4 characters.";
        }
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, vimeo_api_token = ? WHERE user_id = ?");
        $stmt->bind_param("sssi", $name, $phone, $vtoken, $user_id);
    }
    
    if (!isset($error)) {
        if ($stmt && $stmt->execute()) {
            $_SESSION['name'] = $name;
            header("Location: profile.php?updated=1"); exit;
        } else {
            $error = "Failed to update profile.";
        }
    }
}

$u_res = $conn->query("SELECT * FROM users WHERE user_id = $user_id");
$usr = $u_res ? $u_res->fetch_assoc() : null;
?>

<div class="height-100">
    <div class="main-header mb-4">
        <div>
            <h3 style="font-weight:800; color:#024442; margin:0;"><i class="fas fa-user-cog"></i> Account & Connection Preferences</h3>
            <p style="color:#64748b; font-size:14px; margin:4px 0 0;">Manage security credentials and cloud API authorizations.</p>
        </div>
    </div>

    <?php if(isset($_GET['updated'])): ?><div class="alert alert-success fw-bold">Profile preferences updated successfully!</div><?php endif; ?>
    <?php if(isset($error)): ?><div class="alert alert-danger fw-bold"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="d-card p-4">
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="fw-bold fs-6 mb-2">Username (Locked)</label>
                            <input type="text" class="form-control bg-light" disabled value="<?= htmlspecialchars($usr['username'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold fs-6 mb-2">Email Address (Locked)</label>
                            <input type="text" class="form-control bg-light" disabled value="<?= htmlspecialchars($usr['email'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="fw-bold fs-6 mb-2">Full Name</label>
                            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($usr['name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold fs-6 mb-2">Phone Contact</label>
                            <input type="text" name="phone" class="form-control" required value="<?= htmlspecialchars($usr['phone'] ?? '') ?>">
                        </div>
                    </div>

                    <hr class="my-4">

                    <?php if(strtolower($_SESSION['role'] ?? '') === 'teacher'): ?>
                    <h5 style="font-weight:800; color:#024442; margin-bottom:16px;"><i class="fab fa-vimeo-v text-info me-2"></i> Cloud Video Integration</h5>
                    <div class="form-group mb-4 bg-light p-3 rounded-3 border">
                        <label class="fw-bold fs-6 mb-1 text-dark">Vimeo Personal Access Token</label>
                        <input type="password" name="vimeo_api_token" class="form-control" placeholder="Enter Vimeo access token..." value="<?= htmlspecialchars($usr['vimeo_api_token'] ?? '') ?>">
                        <small class="text-muted mt-2 d-block">Required for Direct 2-Way Vimeo Cloud upload on the lecture management page.</small>
                    </div>
                    <?php endif; ?>

                    <h5 style="font-weight:800; color:#024442; margin-bottom:16px;"><i class="fas fa-lock me-2"></i> Security Password Update</h5>
                    <div class="form-group mb-4">
                        <label class="fw-bold fs-6 mb-2">New Password (Leave blank to keep existing)</label>
                        <input type="password" name="new_password" class="form-control" placeholder="••••••••">
                    </div>

                    <button type="submit" class="btn btn-primary-custom px-5 py-3 fw-bold"><i class="fas fa-save"></i> Save Account Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include('dashboard_footer.php'); ?>
