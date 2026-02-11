<?php

include('dashboard_header.php');
// Fetch all users
$sql = "SELECT user_id, name, email, role, last_login, created_at FROM users ORDER BY created_at DESC";
$result = $conn->query($sql);

function getStatus($last_login) {
    if (!empty($last_login) && strtotime($last_login) > strtotime('-7 days')) {
        return ['class' => 'active', 'text' => 'Active'];
    }
    return ['class' => 'inactive', 'text' => 'Inactive'];
}

// Count stats
$total_users = $result->num_rows;
$active_users = 0;
$admin_count = 0;
foreach($result as $row) {
    if(getStatus($row['last_login'])['class'] === 'active') $active_users++;
    if($row['role'] === 'Admin') $admin_count++;
}
$result->data_seek(0); // Reset pointer
?>

<style>
    :root {
        --primary: #024442;
        --accent: #B6F763;
        --light: #f0ffff;
        --dark: #171717;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-400: #9ca3af;
        --gray-500: #6b7280;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --success: #10b981;
        --success-light: #d1fae5;
        --warning: #f59e0b;
        --warning-light: #fef3c7;
        --danger: #ef4444;
        --danger-light: #fee2e2;
        --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
        --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
        --radius-sm: 6px;
        --radius: 10px;
        --radius-lg: 14px;
        --radius-xl: 18px;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
        background: var(--gray-50);
        color: var(--dark);
    }

    .dashboard {
        max-width: 1600px;
        margin: 0 auto;
        padding: 32px 20px;
    }

    /* Header */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 32px;
        gap: 20px;
        flex-wrap: wrap;
    }

    .header-content h1 {
        font-size: 32px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 6px;
    }

    .header-content p {
        color: var(--gray-600);
        font-size: 15px;
    }

    .header-actions {
        display: flex;
        gap: 10px;
    }

    .btn {
        padding: 11px 20px;
        border: none;
        border-radius: var(--radius);
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary {
        background: var(--primary);
        color: white;
    }

    .btn-primary:hover {
        background: #035b58;
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-secondary {
        background: white;
        color: var(--gray-600);
        border: 1px solid var(--gray-300);
    }

    .btn-secondary:hover {
        background: var(--gray-50);
        border-color: var(--gray-400);
    }

    .btn-accent {
        background: var(--accent);
        color: var(--dark);
    }

    .btn-accent:hover {
        background: #a8e856;
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 32px;
    }

    .stat-card {
        background: white;
        padding: 24px;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--gray-200);
        transition: all 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .stat-label {
        font-size: 14px;
        color: var(--gray-600);
        font-weight: 500;
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: var(--dark);
    }

    /* Main Card */
    .main-card {
        background: white;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow);
        border: 1px solid var(--gray-200);
        overflow: hidden;
    }

    .main-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 24px;
        border-bottom: 1px solid var(--gray-200);
    }

    .main-header h3 {
        font-size: 20px;
        font-weight: 700;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Table */
    .main-table {
        overflow-x: auto;
    }

    .table-head {
        display: grid;
        grid-template-columns: 2fr 2fr 1fr 1fr 1.5fr 0.8fr;
        gap: 16px;
        padding: 16px 24px;
        background: var(--gray-50);
        border-bottom: 1px solid var(--gray-200);
        font-size: 13px;
        font-weight: 600;
        color: var(--gray-600);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table-row {
        display: grid;
        grid-template-columns: 2fr 2fr 1fr 1fr 1.5fr 0.8fr;
        gap: 16px;
        padding: 16px 24px;
        border-bottom: 1px solid var(--gray-200);
        align-items: center;
        transition: background 0.2s;
    }

    .table-row:hover {
        background: var(--gray-50);
    }

    .table-row:last-child {
        border-bottom: none;
    }

    .user {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), #035b58);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 16px;
    }

    .user strong {
        color: var(--dark);
        font-weight: 600;
        font-size: 14px;
    }

    .status {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 16px;
        font-size: 12px;
        font-weight: 600;
    }

    .status.active {
        background: var(--success-light);
        color: var(--success);
    }

    .status.inactive {
        background: var(--gray-200);
        color: var(--gray-600);
    }

    .actions {
        display: flex;
        gap: 8px;
    }

    .actions button {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        padding: 6px;
        border-radius: var(--radius-sm);
        transition: all 0.2s;
    }

    .actions .edit:hover {
        background: var(--light);
    }

    .actions .delete:hover {
        background: var(--danger-light);
    }

    .empty-row {
        text-align: center;
        padding: 60px 24px;
        color: var(--gray-500);
        font-size: 15px;
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
        animation: fadeIn 0.2s;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 0;
        border-radius: var(--radius-xl);
        width: 500px;
        max-width: 90%;
        box-shadow: var(--shadow-lg);
        animation: slideIn 0.3s;
    }

    @keyframes slideIn {
        from { transform: translateY(-30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .modal-header {
        padding: 24px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        font-size: 20px;
        font-weight: 700;
        color: var(--dark);
    }

    .close {
        background: none;
        border: none;
        font-size: 24px;
        color: var(--gray-400);
        cursor: pointer;
        padding: 4px;
        border-radius: var(--radius-sm);
        transition: all 0.2s;
    }

    .close:hover {
        background: var(--gray-100);
        color: var(--gray-600);
    }

    .modal-body {
        padding: 24px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 8px;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid var(--gray-300);
        border-radius: var(--radius);
        font-size: 14px;
        font-family: inherit;
        transition: all 0.2s;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.1);
    }

    .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid var(--gray-200);
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .table-head,
        .table-row {
            grid-template-columns: 2fr 2fr 1fr 1fr 1.5fr 0.8fr;
            font-size: 13px;
        }
    }

    @media (max-width: 768px) {
        .dashboard {
            padding: 20px 16px;
        }

        .dashboard-header {
            flex-direction: column;
        }

        .header-actions {
            width: 100%;
        }

        .btn {
            flex: 1;
            justify-content: center;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .table-head {
            display: none;
        }

        .table-row {
            grid-template-columns: 1fr;
            gap: 12px;
            padding: 16px;
            border: 1px solid var(--gray-200);
            margin-bottom: 12px;
            border-radius: var(--radius);
        }

        .table-row > span:before {
            content: attr(data-label);
            font-weight: 600;
            color: var(--gray-600);
            display: block;
            margin-bottom: 4px;
            font-size: 12px;
            text-transform: uppercase;
        }

        .actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .header-content h1 {
            font-size: 24px;
        }
    }
</style>

<div class="dashboard">
    <!-- Header -->
    <div class="dashboard-header">
        <div class="header-content">
            <h1>Users Management</h1>
            <p>Manage user accounts, roles, and permissions</p>
        </div>
        <div class="header-actions">
            <button class="btn btn-secondary">
                <span>📥</span> Export
            </button>
            <button class="btn btn-primary" onclick="openUserModal('add')">
                <span>+</span> Add User
            </button>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total Users</span>
                <div class="stat-icon" style="background: var(--light); color: var(--primary);">👥</div>
            </div>
            <div class="stat-value"><?= $total_users ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Active Users</span>
                <div class="stat-icon" style="background: var(--success-light); color: var(--success);">✅</div>
            </div>
            <div class="stat-value"><?= $active_users ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Administrators</span>
                <div class="stat-icon" style="background: var(--warning-light); color: var(--warning);">👑</div>
            </div>
            <div class="stat-value"><?= $admin_count ?></div>
        </div>

        
    </div>

    <!-- Main Card -->
    <div class="main-card">
        <div class="main-header">
            <h3>👥 All Users</h3>
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

            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): 
                    $avatarLetter = strtoupper(substr($row['name'], 0, 1));
                    $status = getStatus($row['last_login']);
                ?>
                    <div class="table-row">
                        <div class="user">
                            <div class="avatar"><?= $avatarLetter ?></div>
                            <div>
                                <strong><?= htmlspecialchars($row['name']) ?></strong>
                            </div>
                        </div>
                        <span data-label="Email"><?= htmlspecialchars($row['email']) ?></span>
                        <span data-label="Role"><?= htmlspecialchars($row['role']) ?></span>
                        <span data-label="Status"><span class="status <?= $status['class'] ?>"><?= $status['text'] ?></span></span>
                        <span data-label="Last Login"><?= !empty($row['last_login']) ? date('M d, Y H:i', strtotime($row['last_login'])) : 'Never' ?></span>
                        <div class="actions">
                            <button class="edit" onclick="openUserModal('edit', <?= $row['user_id'] ?>)" title="Edit">✏️</button>
                            <button class="delete" onclick="deleteUser(<?= $row['user_id'] ?>)" title="Delete">🗑️</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-row">
                    <span>No users found.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- User Modal -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add User</h3>
            <button class="close" onclick="closeUserModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="userForm">
                <input type="hidden" name="user_id" id="user_id">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" id="name" placeholder="Enter full name" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" id="email" placeholder="user@example.com" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="role" required>
                        <option value="User">User</option>
                        <option value="Manager">Manager</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password <span style="color: var(--gray-500); font-weight: 400;">(leave blank to keep current)</span></label>
                    <input type="password" name="password" id="password" placeholder="Enter password">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeUserModal()">Cancel</button>
            <button type="submit" form="userForm" class="btn btn-primary">Save User</button>
        </div>
    </div>
</div>

<script>
const modal = document.getElementById('userModal');
const modalTitle = document.getElementById('modalTitle');
const userForm = document.getElementById('userForm');

function openUserModal(type, id = null) {
    modal.style.display = 'block';
    if(type === 'add') {
        modalTitle.textContent = 'Add User';
        userForm.reset();
        document.getElementById('user_id').value = '';
        document.getElementById('password').required = true;
    } else if(type === 'edit') {
        modalTitle.textContent = 'Edit User';
        document.getElementById('password').required = false;

        // Fetch user data via AJAX
        fetch('user_action.php?action=fetch&id=' + id)
        .then(res => res.json())
        .then(data => {
            document.getElementById('user_id').value = data.user_id;
            document.getElementById('name').value = data.name;
            document.getElementById('email').value = data.email;
            document.getElementById('role').value = data.role;
        });
    }
}

function closeUserModal() {
    modal.style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target == modal) {
        closeUserModal();
    }
}

// Submit form via AJAX
userForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(userForm);
    fetch('user_action.php?action=save', {method: 'POST', body: formData})
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                closeUserModal();
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            alert('An error occurred. Please try again.');
        });
});

function deleteUser(id) {
    if(confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        fetch('user_action.php?action=delete&id=' + id)
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            alert('An error occurred. Please try again.');
        });
    }
}
</script>

<?php 
include('dashboard_footer.php');
?>