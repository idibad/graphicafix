<?php
    include('dashboard_header.php');

    
// Fetch all users
$sql = "SELECT user_id, name, email, role, last_login, created_at FROM users ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0):
   
?>
<div class="height-100">
    <div class="main-card">
    <div class="main-header">
        <h3>👥 Users</h3>
        <button class="main-btn add-user">+ Add User</button>
    </div>

    <div class="main-table">
        <div class="table-head">
            <span>User</span>
            <span>Email</span>
            <span>Role</span>
            <span>Status</span>
            <span>Last Login</span>
            <span>Actions</span>
        </div>
        <?php
         while ($row = $result->fetch_assoc()):
        // Get first letter for avatar
        $avatarLetter = strtoupper(substr($row['name'], 0, 1));

        // Determine status (example: active if last_login within 7 days, else inactive)
        $statusClass = "inactive";
        $statusText = "Inactive";
        if (!empty($row['last_login']) && strtotime($row['last_login']) > strtotime('-7 days')) {
            $statusClass = "active";
            $statusText = "Active";
        }
        ?>
        <div class="table-row">
            <div class="user">
                <div class="avatar"><?= $avatarLetter ?></div>
                <div>
                    <strong><?= htmlspecialchars($row['name']) ?></strong>
                </div>
            </div>
            <span><?= htmlspecialchars($row['email']) ?></span>
            <span><?= htmlspecialchars($row['role']) ?></span>
            <span class="status <?= $statusClass ?>"><?= $statusText ?></span>
            <span>
                <?= !empty($row['last_login']) ? date('M d, Y H:i', strtotime($row['last_login'])) : 'Never' ?>
            </span>
            <div class="actions">
                <a class="edit" href="#">✏️</a>
                <a class="delete" href="#">🗑️</a>
            </div>
        </div>

        <?php
            endwhile;
        endif;

        $conn->close();
        ?>

    </div>
</div>

</div>
<?php
    include('dashboard_footer.php');
?>