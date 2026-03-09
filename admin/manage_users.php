<?php
include('dashboard_header.php');

// Fetch all users
$sql = "SELECT user_id, name, email, username, role, last_login, created_at FROM users ORDER BY created_at DESC";
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
    if($row['role'] === 'admin') $admin_count++;
}
$result->data_seek(0); // Reset pointer
?>

<style>
   
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

    /* Enhanced Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        animation: fadeIn 0.2s;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-content {
        background-color: white;
        margin: 3% auto;
        padding: 0;
        border-radius: var(--radius-xl);
        width: 550px;
        max-width: 90%;
        box-shadow: var(--shadow-xl);
        animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes slideIn {
        from { 
            transform: translateY(-30px) scale(0.95); 
            opacity: 0; 
        }
        to { 
            transform: translateY(0) scale(1); 
            opacity: 1; 
        }
    }

    .modal-header {
        padding: 28px 32px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, var(--light) 0%, white 100%);
    }

    .modal-header h3 {
        font-size: 22px;
        font-weight: 700;
        color: var(--dark);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-header-icon {
        width: 36px;
        height: 36px;
        background: var(--primary);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .close {
        background: var(--gray-100);
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        font-size: 20px;
        color: var(--gray-600);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .close:hover {
        background: var(--gray-200);
        color: var(--dark);
        transform: rotate(90deg);
    }

    .modal-body {
        padding: 32px;
        max-height: 60vh;
        overflow-y: auto;
    }

    .form-group {
        margin-bottom: 24px;
    }

    .form-group label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 8px;
    }

    .form-group label .required {
        color: var(--danger);
        margin-left: 2px;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 13px 16px;
        border: 2px solid var(--gray-200);
        border-radius: var(--radius);
        font-size: 14px;
        font-family: inherit;
        transition: all 0.2s;
        background: var(--gray-50);
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(2, 68, 66, 0.1);
        background: white;
    }

    .form-hint {
        font-size: 12px;
        color: var(--gray-500);
        margin-top: 6px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .modal-footer {
        padding: 20px 32px;
        border-top: 1px solid var(--gray-200);
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        background: var(--gray-50);
    }

    /* Loading State */
    .btn.loading {
        position: relative;
        color: transparent;
        pointer-events: none;
    }

    .btn.loading::after {
        content: '';
        position: absolute;
        width: 16px;
        height: 16px;
        top: 50%;
        left: 50%;
        margin-left: -8px;
        margin-top: -8px;
        border: 2px solid transparent;
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Toast Notification */
    .toast {
        position: fixed;
        top: 24px;
        right: 24px;
        background: white;
        padding: 16px 20px;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        border-left: 4px solid;
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 300px;
        z-index: 2000;
        animation: slideInRight 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .toast.success {
        border-left-color: var(--success);
    }

    .toast.error {
        border-left-color: var(--danger);
    }

    .toast-icon {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 700;
        color: white;
    }

    .toast.success .toast-icon {
        background: var(--success);
    }

    .toast.error .toast-icon {
        background: var(--danger);
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Responsive */
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

        .modal-content {
            width: 95%;
            margin: 10% auto;
        }

        .modal-header,
        .modal-body,
        .modal-footer {
            padding: 20px;
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

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Inactive</span>
                <div class="stat-icon" style="background: var(--gray-200); color: var(--gray-600);">💤</div>
            </div>
            <div class="stat-value"><?= $total_users - $active_users ?></div>
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
                <div class="table-row" style="text-align: center; padding: 60px 24px; color: var(--gray-500);">
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
            <h3>
                <div class="modal-header-icon" id="modalIcon">👤</div>
                <span id="modalTitle">Add User</span>
            </h3>
            <button class="close" onclick="closeUserModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="userForm">
                <input type="hidden" name="user_id" id="user_id">
                
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="name" id="name" placeholder="Enter full name" required>
                </div>

                <div class="form-group">
                    <label>Username <span class="required">*</span></label>
                    <input type="text" name="username" id="username" placeholder="Enter username" required>
                    <div class="form-hint">💡 Username cannot be changed later</div>
                </div>

                <div class="form-group">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" name="email" id="email" placeholder="user@example.com" required>
                </div>

                <div class="form-group">
                    <label>Role <span class="required">*</span></label>
                    <select name="role" id="role" required>
                        <option value="User">User</option>
                        <option value="Manager">Manager</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Password <span id="passwordRequired" class="required">*</span></label>
                    <input type="password" name="password" id="password" placeholder="Enter password (min. 8 characters)">
                    <div class="form-hint" id="passwordHint">🔒 Minimum 8 characters required</div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeUserModal()">Cancel</button>
            <button type="submit" form="userForm" class="btn btn-primary" id="saveBtn">
                <span id="saveBtnText">Save User</span>
            </button>
        </div>
    </div>
</div>

<script>
const modal = document.getElementById('userModal');
const modalTitle = document.getElementById('modalTitle');
const modalIcon = document.getElementById('modalIcon');
const userForm = document.getElementById('userForm');
const saveBtn = document.getElementById('saveBtn');
const saveBtnText = document.getElementById('saveBtnText');

function openUserModal(type, id = null) {
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    if (type === 'add') {
        modalTitle.textContent = 'Add New User';
        modalIcon.textContent = '➕';
        userForm.reset();
        document.getElementById('user_id').value = '';
        document.getElementById('password').required = true;
        document.getElementById('username').disabled = false;
        document.getElementById('passwordRequired').style.display = 'inline';
        document.getElementById('passwordHint').textContent = '🔒 Minimum 8 characters required';
        saveBtnText.textContent = 'Create User';
    } else if (type === 'edit') {
        modalTitle.textContent = 'Edit User';
        modalIcon.textContent = '✏️';
        userForm.reset();
        document.getElementById('password').required = false;
        document.getElementById('passwordRequired').style.display = 'none';
        document.getElementById('passwordHint').textContent = '🔒 Leave blank to keep current password';
        saveBtnText.textContent = 'Update User';

        // Fetch user data
        saveBtn.disabled = true;
        saveBtn.classList.add('loading');
        
        fetch(`user_action.php?action=fetch&id=${id}`)
            .then(res => {
                console.log('Response status:', res.status);
                if (!res.ok) {
                    throw new Error('Network response was not ok: ' + res.status);
                }
                return res.text(); // Get as text first to see what we're getting
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed data:', data);
                    
                    saveBtn.classList.remove('loading');
                    saveBtn.disabled = false;
                    
                    // Check if data has user_id (success)
                    if (data && data.user_id) {
                        document.getElementById('user_id').value = data.user_id;
                        document.getElementById('name').value = data.name || '';
                        document.getElementById('email').value = data.email || '';
                        document.getElementById('username').value = data.username || '';
                        document.getElementById('username').disabled = true;
                        document.getElementById('role').value = data.role || 'User';
                    } else {
                        showToast(data.message || 'Failed to fetch user data', 'error');
                        closeUserModal();
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response was:', text);
                    throw new Error('Invalid JSON response');
                }
            })
            .catch(err => {
                saveBtn.classList.remove('loading');
                saveBtn.disabled = false;
                console.error('Fetch error:', err);
                showToast('Failed to fetch user data: ' + err.message, 'error');
                closeUserModal();
            });
    }
}

function closeUserModal() {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    userForm.reset();
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target == modal) closeUserModal();
}

// Submit form
userForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    saveBtn.classList.add('loading');
    saveBtn.disabled = true;
    const formData = new FormData(userForm);

    fetch('user_action.php?action=save', {
        method: 'POST',
        body: formData
    })
    .then(res => {
        console.log('Save response status:', res.status);
        if (!res.ok) {
            throw new Error('Network response was not ok: ' + res.status);
        }
        return res.text(); // Get as text first
    })
    .then(text => {
        console.log('Save response text:', text);
        try {
            const data = JSON.parse(text);
            console.log('Save parsed data:', data);
            
            saveBtn.classList.remove('loading');
            saveBtn.disabled = false;
            
            if (data.success) {
                closeUserModal();
                showToast(data.message || 'User saved successfully!', 'success');
                // Reload page after short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showToast(data.message || 'Failed to save user', 'error');
            }
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Response was:', text);
            saveBtn.classList.remove('loading');
            saveBtn.disabled = false;
            showToast('Invalid response from server', 'error');
        }
    })
    .catch(err => {
        saveBtn.classList.remove('loading');
        saveBtn.disabled = false;
        console.error('Save error:', err);
        showToast('An error occurred: ' + err.message, 'error');
    });
});

function deleteUser(id) {
    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) return;

    fetch(`user_action.php?action=delete&id=${id}`)
        .then(res => {
            if (!res.ok) {
                throw new Error('Network response was not ok');
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                showToast(data.message || 'User deleted successfully', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'Failed to delete user', 'error');
            }
        })
        .catch(err => {
            console.error('Delete error:', err);
            showToast('An error occurred. Please try again.', 'error');
        });
}

// Auto-open modal if URL has ?add_user=1
document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('add_user') === '1') {
        openUserModal();
    }
});
// Toast Notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <div class="toast-icon">${type === 'success' ? '✓' : '!'}</div>
        <div>${message}</div>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideInRight 0.3s reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>

<?php 
include('dashboard_footer.php');
?>