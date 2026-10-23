<?php
include 'dashboard_header.php';

// Verify role access
if ($role !== 'client') {
    header("Location: index.php");
    exit;
}

$client_id = intval($data['client_id'] ?? 0);
if ($client_id <= 0) {
    echo "<div class='alert alert-warning' style='margin: 40px;'>Your account is not linked to any client company. Please contact the administrator.</div>";
    include 'dashboard_footer.php';
    exit;
}

$error = '';
$success = '';

// ── CRUD Actions ─────────────────────────────────────────────────────────────
// 1. ADD SUB-USER
if (isset($_POST['add_user'])) {
    $sub_name     = trim($_POST['name'] ?? '');
    $sub_email    = trim($_POST['email'] ?? '');
    $sub_phone    = trim($_POST['phone'] ?? '');
    $sub_username = trim($_POST['username'] ?? '');
    $sub_password = trim($_POST['password'] ?? '');

    if (empty($sub_name) || empty($sub_username) || empty($sub_password)) {
        $error = "Name, Username, and Password are required.";
    } else {
        // Check if username already exists
        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $sub_username, $sub_email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Username or Email is already registered.";
        } else {
            // Hash password
            $hashed_pass = password_hash($sub_password, PASSWORD_DEFAULT);
            $sub_role = 'client_sub';
            $status = 'active';

            $insert = $conn->prepare("
                INSERT INTO users (name, email, phone, username, role, password, client_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $insert->bind_param("ssssssis", $sub_name, $sub_email, $sub_phone, $sub_username, $sub_role, $hashed_pass, $client_id, $status);
            
            if ($insert->execute()) {
                $success = "Client User Added Successfully!";

                if (!empty($sub_email)) {
                    require_once __DIR__ . '/../core/notification_mailer.php';
                    $c_info = $conn->query("SELECT company, client_name FROM clients WHERE id = {$client_id}")->fetch_assoc();
                    $co_name = $c_info['company'] ?? ($c_info['client_name'] ?? '');
                    notifyClientUserWelcome($sub_email, $sub_name, $sub_username, $sub_password, $co_name);
                }
            } else {
                $error = "Error adding user: " . $conn->error;
            }
        }
    }
}

// 2. DELETE SUB-USER
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['uid'])) {
    $uid = intval($_GET['uid']);
    
    // Safety check: ensure target user is a sub-user of the logged-in client
    $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND client_id = ? AND role = 'client_sub'");
    $stmt_check->bind_param("ii", $uid, $client_id);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        $conn->query("DELETE FROM users WHERE user_id = {$uid}");
        $success = "User deleted successfully!";
    } else {
        $error = "Unauthorized action.";
    }
}

// Fetch all sub-users for this client
$stmt_users = $conn->prepare("
    SELECT user_id, name, email, phone, username, last_login, created_at 
    FROM users 
    WHERE client_id = ? AND role = 'client_sub'
    ORDER BY created_at DESC
");
$stmt_users->bind_param("i", $client_id);
$stmt_users->execute();
$sub_users = $stmt_users->get_result();
?>

<div class="height-100">
    <div class="projects-container">
        <!-- Page Header -->
        <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div class="page-title-section">
                <h1>Manage Client Users</h1>
                <p>Add and manage team members from your company who will participate in these projects</p>
            </div>
            <button class="btn btn-primary" onclick="document.getElementById('userFormModal').style.display='block'" style="background:#024442; border:none; padding:8px 20px; font-weight:700;">+ Add User</button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom: 20px; color:#10b981; background:#ecfdf5; border:1px solid #a7f3d0; padding:12px; border-radius:8px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Users Table -->
        <div class="card" style="padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; background: white;">
            <?php if ($sub_users->num_rows > 0): ?>
                <div style="overflow-x:auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse; font-size: 14.5px;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; color: #475569; font-weight:700;">
                                <th style="padding: 12px 10px;">Name</th>
                                <th style="padding: 12px 10px;">Username</th>
                                <th style="padding: 12px 10px;">Email</th>
                                <th style="padding: 12px 10px;">Phone</th>
                                <th style="padding: 12px 10px;">Joined Date</th>
                                <th style="padding: 12px 10px;">Last Login</th>
                                <th style="padding: 12px 10px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($usr = $sub_users->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 14px 10px; font-weight:700; color:#1e293b;"><?= htmlspecialchars($usr['name']) ?></td>
                                    <td style="padding: 14px 10px;"><?= htmlspecialchars($usr['username']) ?></td>
                                    <td style="padding: 14px 10px;"><?= htmlspecialchars($usr['email'] ?: '—') ?></td>
                                    <td style="padding: 14px 10px;"><?= htmlspecialchars($usr['phone'] ?: '—') ?></td>
                                    <td style="padding: 14px 10px;"><?= date('M d, Y', strtotime($usr['created_at'])) ?></td>
                                    <td style="padding: 14px 10px;"><?= $usr['last_login'] ? date('M d, Y H:i', strtotime($usr['last_login'])) : 'Never' ?></td>
                                    <td style="padding: 14px 10px; text-align: center;">
                                        <a href="client_users.php?action=delete&uid=<?= $usr['user_id'] ?>" 
                                           class="btn btn-danger btn-sm" 
                                           style="color:#ef4444; border:1px solid #fee2e2; background:#fef2f2; padding:6px 12px; border-radius:6px; font-weight:700; text-decoration:none;"
                                           onclick="return confirm('Are you sure you want to remove this user from your company profile?')">
                                            Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; color: #94a3b8; padding: 40px;">
                    <i class="fas fa-users-cog" style="font-size:40px; color:#cbd5e1; margin-bottom:12px;"></i>
                    <p>No sub-users have been added yet. Give access to other people in your company using the button above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="userFormModal" class="modal" style="display:none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); padding-top: 60px;">
    <div class="modal-content" style="background-color: #fefefe; margin: 5% auto; padding: 24px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #1e293b;"><i class="fas fa-user-plus"></i> Add Sub-User</h3>
            <span style="font-size: 24px; font-weight: bold; cursor: pointer;" onclick="document.getElementById('userFormModal').style.display='none'">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="add_user" value="1">
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 13px;">Full Name <span style="color:#ef4444;">*</span></label>
                <input type="text" name="name" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;" required placeholder="e.g. John Doe">
            </div>
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 13px;">Username <span style="color:#ef4444;">*</span></label>
                <input type="text" name="username" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;" required placeholder="e.g. johndoe123">
            </div>
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 13px;">Email Address</label>
                <input type="email" name="email" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;" placeholder="e.g. john@yourcompany.com">
            </div>
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 13px;">Phone Number</label>
                <input type="text" name="phone" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;" placeholder="e.g. +923001234567">
            </div>
            <div class="form-group" style="margin-bottom: 24px;">
                <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 13px;">Password <span style="color:#ef4444;">*</span></label>
                <input type="password" name="password" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;" required placeholder="Enter a secure password">
                <small style="color: #64748b; display: block; margin-top: 4px;">This user will have limited rights (cannot view invoices or manage users).</small>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('userFormModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: #024442; border: none; padding: 8px 20px; font-weight:700;">Save User</button>
            </div>
        </form>
    </div>
</div>

<?php include 'dashboard_footer.php'; ?>
