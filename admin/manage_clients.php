<?php
include 'dashboard_header.php';

// ── Handle form submissions ───────────────────────────────────────────────────
$toast_message = '';
$toast_type    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action']      ?? '';
    $client_id   = intval($_POST['client_id'] ?? 0);
    $client_name = trim($_POST['client_name'] ?? '');
    $company     = trim($_POST['company']     ?? '');
    $email       = trim($_POST['email']       ?? '');
    $phone       = trim($_POST['phone']       ?? '');
    $address     = trim($_POST['address']     ?? '');
    $logo        = trim($_POST['logo']        ?? '');

    if ($action === 'add_client') {
        if (!$client_name || !$company || !$email) {
            $toast_message = 'Name, company and email are required.';
            $toast_type    = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $toast_message = 'Invalid email address.';
            $toast_type    = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO clients (client_name, logo, company, email, phone, address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssssss", $client_name, $logo, $company, $email, $phone, $address);
            if ($stmt->execute()) {
                $toast_message = 'Client added successfully!';
                $toast_type    = 'success';
            } else {
                $toast_message = 'Failed to add client.';
                $toast_type    = 'error';
            }
        }

    } elseif ($action === 'edit_client') {
        if (!$client_id || !$client_name || !$company || !$email) {
            $toast_message = 'Name, company and email are required.';
            $toast_type    = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $toast_message = 'Invalid email address.';
            $toast_type    = 'error';
        } else {
            $stmt = $conn->prepare("UPDATE clients SET client_name=?, logo=?, company=?, email=?, phone=?, address=? WHERE id=?");
            $stmt->bind_param("ssssssi", $client_name, $logo, $company, $email, $phone, $address, $client_id);
            if ($stmt->execute()) {
                $toast_message = 'Client updated successfully!';
                $toast_type    = 'success';
            } else {
                $toast_message = 'Failed to update client.';
                $toast_type    = 'error';
            }
        }

    } elseif ($action === 'delete_client') {
        if ($client_id) {
            $stmt = $conn->prepare("DELETE FROM clients WHERE id=?");
            $stmt->bind_param("i", $client_id);
            if ($stmt->execute()) {
                $toast_message = 'Client deleted.';
                $toast_type    = 'success';
            } else {
                $toast_message = 'Failed to delete client.';
                $toast_type    = 'error';
            }
        }
    }
}

// ── Fetch all clients ─────────────────────────────────────────────────────────
$client_result = $conn->query("SELECT * FROM clients ORDER BY created_at DESC");

function avatarColor($name) {
    $colors = ['pink', 'blue', 'green', 'orange', 'purple'];
    return $colors[ord(strtoupper($name[0])) % count($colors)];
}

function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)          return $diff . " seconds ago";
    elseif ($diff < 3600)    return round($diff/60)   . " minutes ago";
    elseif ($diff < 86400)   return round($diff/3600)  . " hours ago";
    elseif ($diff < 604800)  return round($diff/86400) . " days ago";
    else return date("M j, Y", strtotime($datetime));
}

$total_clients  = $client_result->num_rows;
$total_projects = $conn->query("SELECT COUNT(*) AS c FROM projects")->fetch_assoc()['c'] ?? 0;
?>

<div class="height-100">

    <!-- Stats -->
    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <span class="stat-label">Total Clients</span>
                <div class="stat-icon" style="background:var(--light);color:var(--primary);"><i class="fas fa-building"></i></div>
            </div>
            <div class="stat-number"><?= $total_clients ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <span class="stat-label">Total Projects</span>
                <div class="stat-icon" style="background:#e0f2fe;color:#0284c7;"><i class="fas fa-folder"></i></div>
            </div>
            <div class="stat-number"><?= $total_projects ?></div>
        </div>
    </div>

    <!-- Table -->
    <div class="main-card">
        <div class="main-header">
            <h3><i class="fas fa-building"></i> Clients</h3>
            <button class="add-client" onclick="openAddModal()">+ Add Client</button>
        </div>

        <div class="main-table">
            <div class="table-head" style="grid-template-columns:2fr 1.5fr 1fr 0.8fr 1.2fr 1fr;">
                <span>Client</span>
                <span>Company</span>
                <span>Phone</span>
                <span>Projects</span>
                <span>Added</span>
                <span>Actions</span>
            </div>

            <?php if ($total_clients > 0):
                $client_result->data_seek(0);
                while ($client = $client_result->fetch_assoc()):
                    $projects_count = $conn->query("SELECT COUNT(*) AS c FROM projects WHERE client_id = {$client['id']}")->fetch_assoc()['c'] ?? 0;
            ?>
            <div class="table-row" style="grid-template-columns:2fr 1.5fr 1fr 0.8fr 1.2fr 1fr;">
                <div class="client">
                    <?php if (!empty($client['logo'])): ?>
                        <img src="<?= htmlspecialchars($client['logo']) ?>" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                    <?php else: ?>
                        <div class="avatar <?= avatarColor($client['client_name']) ?>">
                            <?= strtoupper($client['client_name'][0]) ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <strong><?= htmlspecialchars($client['client_name']) ?></strong>
                        <small><?= htmlspecialchars($client['email']) ?></small>
                    </div>
                </div>
                <span data-label="Company"><?= htmlspecialchars($client['company']) ?></span>
                <span data-label="Phone"><?= htmlspecialchars($client['phone'] ?: '—') ?></span>
                <span data-label="Projects"><?= $projects_count ?></span>
                <span data-label="Added" style="font-size:13px;color:#888;"><?= timeAgo($client['created_at']) ?></span>
                <div style="display:flex;gap:6px;align-items:center;">
                    <button class="dots" onclick="openViewModal(<?= $client['id'] ?>)" title="View"><i class="fas fa-eye"></i>️</button>
                    <button class="dots" onclick="openEditModal(<?= $client['id'] ?>)" title="Edit"><i class="fas fa-pencil-alt"></i>️</button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this client? This cannot be undone.');">
                        <input type="hidden" name="action"    value="delete_client">
                        <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                        <button type="submit" class="dots" title="Delete" style="color:#ef4444;"><i class="fas fa-trash"></i>️</button>
                    </form>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div style="text-align:center;padding:60px 24px;color:#888;">
                No clients yet. <a href="#" onclick="openAddModal();return false;">Add your first client →</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Add / Edit Modal ───────────────────────────────────────────────────── -->
<div id="clientModal" class="modal-overlay" onclick="if(event.target===this)closeModal('clientModal')">
    <div class="modal-content">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon" id="modalIcon"><i class="fas fa-building"></i></div>
                <span id="modalTitle">Add New Client</span>
            </h4>
            <button class="modal-close" onclick="closeModal('clientModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action"    id="formAction"   value="add_client">
            <input type="hidden" name="client_id" id="formClientId" value="">
            <div class="modal-body">
                <div class="info-grid">
                    <div class="form-group">
                        <label>Client Name <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="client_name" id="fName" placeholder="Enter client name" required>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="form-group">
                            <label>Company <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="company" id="fCompany" placeholder="Company name" required>
                        </div>
                        <div class="form-group">
                            <label>Logo URL</label>
                            <input type="text" name="logo" id="fLogo" placeholder="https://...">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="form-group">
                            <label>Email <span style="color:#ef4444;">*</span></label>
                            <input type="email" name="email" id="fEmail" placeholder="client@company.com" required>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" id="fPhone" placeholder="+1 000 000 0000">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" id="fAddress" placeholder="Street, City, Country">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeModal('clientModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="saveBtn"><i class="fas fa-save"></i> Save Client</button>
            </div>
        </form>
    </div>
</div>

<!-- ── View Details Modal ─────────────────────────────────────────────────── -->
<div id="viewModal" class="modal-overlay" onclick="if(event.target===this)closeModal('viewModal')">
    <div class="modal-content">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon"><i class="fas fa-eye"></i>️</div>
                <span>Client Details</span>
            </h4>
            <button class="modal-close" onclick="closeModal('viewModal')">×</button>
        </div>
        <div class="modal-body">
            <div style="text-align:center;padding-bottom:20px;margin-bottom:20px;border-bottom:1px solid #f0f0f0;">
                <div id="vAvatar" style="width:72px;height:72px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:28px;color:white;margin:0 auto 12px;">A</div>
                <div style="font-size:20px;font-weight:700;" id="vName">—</div>
                <div style="font-size:14px;color:#888;margin-top:4px;" id="vCompany">—</div>
            </div>
            <div class="info-grid" style="grid-template-columns:1fr 1fr;gap:14px;">
                <div class="info-row" style="background:#f9fbfc;border-radius:10px;padding:14px;">
                    <div class="info-icon"><i class="fas fa-envelope"></i></div>
                    <div class="info-content">
                        <div class="info-label">Email</div>
                        <div class="info-value" id="vEmail">—</div>
                    </div>
                </div>
                <div class="info-row" style="background:#f9fbfc;border-radius:10px;padding:14px;">
                    <div class="info-icon"><i class="fas fa-phone"></i></div>
                    <div class="info-content">
                        <div class="info-label">Phone</div>
                        <div class="info-value" id="vPhone">—</div>
                    </div>
                </div>
                <div class="info-row" style="background:#f9fbfc;border-radius:10px;padding:14px;grid-column:1/-1;">
                    <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="info-content">
                        <div class="info-label">Address</div>
                        <div class="info-value" id="vAddress">—</div>
                    </div>
                </div>
                <div class="info-row" style="background:#f9fbfc;border-radius:10px;padding:14px;">
                    <div class="info-icon"><i class="fas fa-folder"></i></div>
                    <div class="info-content">
                        <div class="info-label">Projects</div>
                        <div class="info-value"><span id="vProjects" style="background:var(--primary);color:white;padding:3px 10px;border-radius:20px;font-size:13px;">0</span></div>
                    </div>
                </div>
                <div class="info-row" style="background:#f9fbfc;border-radius:10px;padding:14px;">
                    <div class="info-icon"><i class="fas fa-calendar"></i>️</div>
                    <div class="info-content">
                        <div class="info-label">Client Since</div>
                        <div class="info-value" id="vSince">—</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary-custom" onclick="closeModal('viewModal')">Close</button>
            <button type="button" class="btn btn-primary-custom"   id="vEditBtn"><i class="fas fa-pencil-alt"></i>️ Edit Client</button>
        </div>
    </div>
</div>

<!-- Toast container -->
<div id="toastContainer" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;"></div>

<script>
const clientData = {
    <?php
    $all  = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM projects p WHERE p.client_id = c.id) AS project_count FROM clients c");
    $rows = [];
    while ($r = $all->fetch_assoc()) { $rows[] = $r['id'] . ': ' . json_encode($r); }
    echo implode(",\n    ", $rows);
    ?>
};

const avatarGradients = {
    pink:   'linear-gradient(135deg,#f093fb,#f5576c)',
    blue:   'linear-gradient(135deg,#4facfe,#00f2fe)',
    green:  'linear-gradient(135deg,#43e97b,#38f9d7)',
    orange: 'linear-gradient(135deg,#f7971e,#ffd200)',
    purple: 'linear-gradient(135deg,#a18cd1,#fbc2eb)',
};
const colorKeys = ['pink','blue','green','orange','purple'];
function getAvatarColor(name) { return colorKeys[name.charCodeAt(0) % colorKeys.length]; }

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Client';
    document.getElementById('modalIcon').textContent  = '<i class="fas fa-building"></i>';
    document.getElementById('formAction').value       = 'add_client';
    document.getElementById('formClientId').value     = '';
    document.getElementById('saveBtn').textContent    = '<i class="fas fa-save"></i> Save Client';
    ['fName','fCompany','fLogo','fEmail','fPhone','fAddress'].forEach(id => document.getElementById(id).value = '');
    openModal('clientModal');
}

function openEditModal(id) {
    const c = clientData[id]; if (!c) return;
    document.getElementById('modalTitle').textContent = 'Edit Client';
    document.getElementById('modalIcon').textContent  = '<i class="fas fa-pencil-alt"></i>️';
    document.getElementById('formAction').value       = 'edit_client';
    document.getElementById('formClientId').value     = id;
    document.getElementById('saveBtn').textContent    = '<i class="fas fa-pencil-alt"></i>️ Update Client';
    document.getElementById('fName').value    = c.client_name || '';
    document.getElementById('fCompany').value = c.company     || '';
    document.getElementById('fLogo').value    = c.logo        || '';
    document.getElementById('fEmail').value   = c.email       || '';
    document.getElementById('fPhone').value   = c.phone       || '';
    document.getElementById('fAddress').value = c.address     || '';
    openModal('clientModal');
}

function openViewModal(id) {
    const c = clientData[id]; if (!c) return;
    const avatar = document.getElementById('vAvatar');
    avatar.textContent       = c.client_name[0].toUpperCase();
    avatar.style.background  = avatarGradients[getAvatarColor(c.client_name)];
    document.getElementById('vName').textContent     = c.client_name;
    document.getElementById('vCompany').textContent  = c.company || '—';
    document.getElementById('vPhone').textContent    = c.phone   || '—';
    document.getElementById('vAddress').textContent  = c.address || '—';
    document.getElementById('vProjects').textContent = c.project_count + ' project' + (c.project_count != 1 ? 's' : '');
    document.getElementById('vSince').textContent    = new Date(c.created_at).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'});
    const emailEl = document.getElementById('vEmail');
    emailEl.innerHTML = c.email ? `<a href="mailto:${c.email}" style="color:var(--primary);">${c.email}</a>` : '—';
    document.getElementById('vEditBtn').onclick = function() { closeModal('viewModal'); openEditModal(id); };
    openModal('viewModal');
}

function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}

function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.style.cssText = `background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:240px;border-left:4px solid ${type==='success'?'#10b981':'#ef4444'};`;
    t.innerHTML = `<span style="font-weight:700;color:${type==='success'?'#10b981':'#ef4444'}">${type==='success'?'<i class="fas fa-check"></i>':'<i class="fas fa-times"></i>'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3000);
}

<?php if ($toast_message): ?>
document.addEventListener('DOMContentLoaded', () => showToast(<?= json_encode($toast_message) ?>, <?= json_encode($toast_type) ?>));
<?php endif; ?>
</script>

<?php include('dashboard_footer.php'); ?>