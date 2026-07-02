<?php
include 'dashboard_header.php';

// ── Database Auto-Patch ──
// Ensure the role column can accept 'teacher' and 'student' by changing it to a VARCHAR
$conn->query("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'student'");

// Fetch all users
$sql = "SELECT user_id, name, email, username, role, last_login, created_at FROM users ORDER BY created_at DESC";
$result = $conn->query($sql);

function getStatus($last_login) {
    if (!empty($last_login) && strtotime($last_login) > strtotime('-7 days')) {
        return ['class' => 'active', 'text' => 'Active'];
    }
    return ['class' => 'inactive', 'text' => 'Inactive'];
}

// Role Badge Color Helper
function getRoleBadge($role) {
    $r = strtolower($role);
    if ($r === 'admin')   return ['bg' => '#fff4e5', 'text' => '#e17055']; // Orange
    if ($r === 'pm')      return ['bg' => '#fce7f3', 'text' => '#db2777']; // Pink
    if ($r === 'teacher') return ['bg' => '#e0f2fe', 'text' => '#0284c7']; // Blue
    if ($r === 'student') return ['bg' => '#d7f8b8', 'text' => '#2b7a2b']; // Green
    if ($r === 'user')    return ['bg' => '#f3e8ff', 'text' => '#7c3aed']; // Purple
    return ['bg' => '#f0f0f0', 'text' => '#555']; // Gray fallback
}

// ── Group Users by Role & Count Stats ──
$total_users = $result->num_rows;
$active_users = 0;
$admin_count = 0;

$users_grouped = [
    'admin'   => [],
    'pm'      => [],
    'teacher' => [],
    'student' => [],
    'hrm'     => [],
    'user'    => [],
    'other'   => []
];

foreach($result as $row) {
    if(getStatus($row['last_login'])['class'] === 'active') $active_users++;
    if(strtolower($row['role']) === 'admin') $admin_count++;
    
    $r = strtolower($row['role']);
    if (array_key_exists($r, $users_grouped)) {
        $users_grouped[$r][] = $row;
    } else {
        $users_grouped['other'][] = $row;
    }
}
$result->data_seek(0); // Reset pointer

// FontAwesome integrated Role Titles
$role_titles = [
    'admin'   => '<i class="fas fa-user-shield" style="color: var(--primary); margin-right: 6px;"></i> Administrators',
    'pm'      => '<i class="fas fa-project-diagram" style="color: var(--primary); margin-right: 6px;"></i> Project Managers',
    'teacher' => '<i class="fas fa-chalkboard-teacher" style="color: var(--primary); margin-right: 6px;"></i> Teachers',
    'student' => '<i class="fas fa-user-graduate" style="color: var(--primary); margin-right: 6px;"></i> Students',
    'hrm'     => '<i class="fas fa-briefcase" style="color: var(--primary); margin-right: 6px;"></i> HR Management',
    'user'    => '<i class="fas fa-users" style="color: var(--primary); margin-right: 6px;"></i> Staff / Users',
    'other'   => '<i class="fas fa-user-tag" style="color: var(--primary); margin-right: 6px;"></i> Other Roles'
];
?>

<div class="height-100">

    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users" style="color: var(--primary);"></i></div>
            <div class="stat-number"><?= $total_users ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-check" style="color: var(--primary);"></i></div>
            <div class="stat-number"><?= $active_users ?></div>
            <div class="stat-label">Active Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-shield" style="color: var(--primary);"></i></div>
            <div class="stat-number"><?= $admin_count ?></div>
            <div class="stat-label">Administrators</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-clock" style="color: var(--primary);"></i></div>
            <div class="stat-number"><?= $total_users - $active_users ?></div>
            <div class="stat-label">Inactive</div>
        </div>
    </div>

    <div class="main-card">
        <div class="main-header" style="flex-wrap: wrap; gap: 14px;">
            <h3 style="margin: 0; display:flex; align-items:center;">
                <i class="fas fa-users-cog" style="color: var(--primary); margin-right: 10px;"></i> User Management
            </h3>
            
            <div style="flex-grow: 1; max-width: 400px; margin: 0 14px; position:relative;">
                <input type="text" id="userSearchInput" placeholder="Search by name, email, or username..." 
                       style="width: 100%; padding: 10px 16px 10px 36px; border: 1px solid #e0e0e0; border-radius: 8px; outline: none; font-size: 13.5px; font-family: inherit;">
                <i class="fas fa-search" style="position:absolute; left:14px; top:12px; color:#aaa;"></i>
            </div>

            <button class="add-client" onclick="openUserModal('add')"><i class="fas fa-plus" style="margin-right: 5px;"></i> Add User</button>
        </div>

        <div style="padding: 10px 24px 24px;">
            <?php foreach ($users_grouped as $role_key => $users_in_role): ?>
                <?php if (count($users_in_role) > 0): ?>
                    <div class="role-table-section" style="margin-bottom: 30px;">
                        <h4 style="margin: 0 0 12px 0; color: #333; font-size: 15px; font-weight: 700; border-bottom: 2px solid #f0f0f0; padding-bottom: 8px; display:flex; align-items:center;">
                            <?= $role_titles[$role_key] ?> <span style="color:#aaa; font-size:12px; margin-left:6px;">(<?= count($users_in_role) ?>)</span>
                        </h4>
                        
                        <div class="main-table" style="border: 1px solid #f0f0f0; border-radius: 10px;">
                            <div class="table-head" style="grid-template-columns:2fr 2fr 1fr 1fr 1.5fr 0.6fr; background: #f9fbfc; border-radius: 10px 10px 0 0; padding: 12px 20px;">
                                <span>User</span>
                                <span>Email</span>
                                <span>Role</span>
                                <span>Status</span>
                                <span>Last Login</span>
                                <span>Actions</span>
                            </div>

                            <?php foreach ($users_in_role as $row): 
                                $status = getStatus($row['last_login']);
                                $roleBadge = getRoleBadge($row['role']);
                            ?>
                            <div class="table-row user-data-row" style="grid-template-columns:2fr 2fr 1fr 1fr 1.5fr 0.6fr; padding: 12px 20px; border-bottom: 1px solid #f5f5f5;">
                                <div class="client">
                                    <div class="avatar"><?= strtoupper(substr($row['name'], 0, 1)) ?></div>
                                    <div>
                                        <strong><?= htmlspecialchars($row['name']) ?></strong>
                                        <small><?= htmlspecialchars($row['username']) ?></small>
                                    </div>
                                </div>
                                <span data-label="Email" style="font-size:13.5px;color:#666;"><?= htmlspecialchars($row['email']) ?></span>
                                <span data-label="Role">
                                    <span style="background:<?= $roleBadge['bg'] ?>; color:<?= $roleBadge['text'] ?>; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
                                        <?= htmlspecialchars($row['role']) ?>
                                    </span>
                                </span>
                                <span data-label="Status">
                                    <span class="status <?= $status['class'] ?>"><?= $status['text'] ?></span>
                                </span>
                                <span data-label="Last Login" style="font-size:13px;color:#888;">
                                    <?= !empty($row['last_login']) ? date('M d, Y H:i', strtotime($row['last_login'])) : 'Never' ?>
                                </span>
                                <div style="display:flex;gap:6px;">
                                    <button class="dots" onclick="openUserModal('edit', <?= $row['user_id'] ?>)" title="Edit">
                                        <i class="fas fa-pen" style="color: var(--primary);"></i>
                                    </button>
                                    <button class="dots" onclick="deleteUser(<?= $row['user_id'] ?>)" title="Delete">
                                        <i class="fas fa-trash" style="color:#ef4444;"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($total_users === 0): ?>
                <div style="text-align:center;padding:60px 24px;color:#888;">
                    <i class="fas fa-users-slash" style="font-size: 3rem; margin-bottom: 15px; color: #ddd;"></i><br>
                    No users found in the system.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="userModal" class="modal-overlay" onclick="if(event.target===this)closeUserModal()">
    <div class="modal-content">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon" id="modalIcon"><i class="fas fa-user-plus" style="color: var(--primary);"></i></div>
                <span id="modalTitle">Add User</span>
            </h4>
            <button class="modal-close" onclick="closeUserModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="userForm">
            <input type="hidden" name="user_id" id="user_id">
            <div class="modal-body">
                <div class="info-grid">
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:7px;">Full Name <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="name" id="f_name" placeholder="Enter full name" required
                            style="width:100%;padding:11px 14px;border:2px solid #f0f0f0;border-radius:10px;font-size:14px;background:#f8f9fa;outline:none;box-sizing:border-box;font-family:inherit;">
                    </div>
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:7px;">Username <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="username" id="f_username" placeholder="Enter username" required
                            style="width:100%;padding:11px 14px;border:2px solid #f0f0f0;border-radius:10px;font-size:14px;background:#f8f9fa;outline:none;box-sizing:border-box;font-family:inherit;">
                        <div style="font-size:12px;color:#999;margin-top:5px;"><i class="fas fa-info-circle" style="color: var(--primary);"></i> Username cannot be changed later</div>
                    </div>
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:7px;">Email Address <span style="color:#ef4444;">*</span></label>
                        <input type="email" name="email" id="f_email" placeholder="user@example.com" required
                            style="width:100%;padding:11px 14px;border:2px solid #f0f0f0;border-radius:10px;font-size:14px;background:#f8f9fa;outline:none;box-sizing:border-box;font-family:inherit;">
                    </div>
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:7px;">Role <span style="color:#ef4444;">*</span></label>
                        <select name="role" id="f_role" required
                            style="width:100%;padding:11px 14px;border:2px solid #f0f0f0;border-radius:10px;font-size:14px;background:#f8f9fa;outline:none;box-sizing:border-box;font-family:inherit;">
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="user">Staff / User</option>
                            <option value="pm">Project Manager</option>
                            <option value="hrm">HR Management</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:7px;">
                            Password <span id="passwordRequired" style="color:#ef4444;">*</span>
                        </label>
                        <input type="password" name="password" id="f_password" placeholder="Enter password (min. 8 characters)"
                            style="width:100%;padding:11px 14px;border:2px solid #f0f0f0;border-radius:10px;font-size:14px;background:#f8f9fa;outline:none;box-sizing:border-box;font-family:inherit;">
                        <div style="font-size:12px;color:#999;margin-top:5px;" id="passwordHint"><i class="fas fa-lock" style="color: var(--primary);"></i> Minimum 8 characters required</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeUserModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="saveBtn">
                    <span id="saveBtnText"><i class="fas fa-save"></i> Save User</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div id="toastContainer" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;"></div>

<script>
// --- Real-time Inline Search Logic ---
document.getElementById('userSearchInput').addEventListener('keyup', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const sections = document.querySelectorAll('.role-table-section');

    sections.forEach(section => {
        let hasVisibleRow = false;
        const rows = section.querySelectorAll('.user-data-row');

        rows.forEach(row => {
            const rowText = row.innerText.toLowerCase();
            if (rowText.includes(searchTerm)) {
                row.style.display = ''; // Show row
                hasVisibleRow = true;
            } else {
                row.style.display = 'none'; // Hide row
            }
        });

        // Hide the entire table section if no rows match the search
        section.style.display = hasVisibleRow ? '' : 'none';
    });
});
// -------------------------------------

function openUserModal(type, id = null) {
    const modal = document.getElementById('userModal');
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = '';

    if (type === 'add') {
        document.getElementById('modalTitle').textContent       = 'Add New User';
        document.getElementById('modalIcon').innerHTML          = '<i class="fas fa-user-plus" style="color: var(--primary);"></i>';
        document.getElementById('saveBtnText').innerHTML        = '<i class="fas fa-save"></i> Save User';
        document.getElementById('f_password').required          = true;
        document.getElementById('f_username').disabled          = false;
        document.getElementById('f_role').value                 = 'student'; // Default to student
        document.getElementById('passwordRequired').style.display = 'inline';
        document.getElementById('passwordHint').innerHTML       = '<i class="fas fa-lock" style="color: var(--primary);"></i> Minimum 8 characters required';
    } else {
        document.getElementById('modalTitle').textContent       = 'Edit User';
        document.getElementById('modalIcon').innerHTML          = '<i class="fas fa-user-edit" style="color: var(--primary);"></i>';
        document.getElementById('saveBtnText').innerHTML        = '<i class="fas fa-save"></i> Update User';
        document.getElementById('f_password').required          = false;
        document.getElementById('f_username').disabled          = true;
        document.getElementById('passwordRequired').style.display = 'none';
        document.getElementById('passwordHint').innerHTML       = '<i class="fas fa-unlock-alt" style="color: var(--primary);"></i> Leave blank to keep current password';

        const btn = document.getElementById('saveBtn');
        btn.disabled = true; btn.style.opacity = '.6';

        fetch(`user_action.php?action=fetch&id=${id}`)
            .then(r => r.text())
            .then(text => {
                const start = text.lastIndexOf('{"');
                try {
                    const data = JSON.parse(start !== -1 ? text.slice(start) : text);
                    if (data && data.user_id) {
                        document.getElementById('user_id').value   = data.user_id;
                        document.getElementById('f_name').value    = data.name      || '';
                        document.getElementById('f_email').value   = data.email    || '';
                        document.getElementById('f_username').value = data.username || '';
                        document.getElementById('f_role').value    = data.role      || 'student';
                    } else {
                        showToast(data.message || 'Failed to load user', 'error');
                        closeUserModal(); return;
                    }
                } catch(e) { showToast('Server error', 'error'); closeUserModal(); return; }
                btn.disabled = false; btn.style.opacity = '1';
            });
    }

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeUserModal() {
    document.getElementById('userModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('userForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn  = document.getElementById('saveBtn');
    const text = document.getElementById('saveBtnText');
    const orig = text.innerHTML;
    btn.disabled = true; text.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

    fetch('user_action.php?action=save', { method: 'POST', body: new FormData(this) })
        .then(r => r.text())
        .then(raw => {
            const start = raw.lastIndexOf('{"');
            try {
                const data = JSON.parse(start !== -1 ? raw.slice(start) : raw);
                if (data.success) {
                    closeUserModal();
                    showToast(data.message || 'User saved!');
                    setTimeout(() => location.reload(), 900);
                } else {
                    showToast(data.message || 'Failed to save', 'error');
                }
            } catch(e) { showToast('Server error', 'error'); }
            btn.disabled = false; text.innerHTML = orig;
        });
});

function deleteUser(id) {
    if (!confirm('Delete this user? This cannot be undone.')) return;
    fetch(`user_action.php?action=delete&id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) { showToast('User deleted.'); setTimeout(() => location.reload(), 900); }
            else showToast(data.message || 'Failed', 'error');
        })
        .catch(() => showToast('Server error', 'error'));
}

document.addEventListener('DOMContentLoaded', () => {
    if (new URLSearchParams(window.location.search).get('add_user') === '1') openUserModal('add');
});

function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-times-circle';
    const color = type === 'success' ? '#10b981' : '#ef4444';
    t.style.cssText = `background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:220px;border-left:4px solid ${color};`;
    t.innerHTML = `<span style="font-weight:700;color:${color}"><i class="fas ${icon}"></i></span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3000);
}
</script>

<?php include('dashboard_footer.php'); ?>