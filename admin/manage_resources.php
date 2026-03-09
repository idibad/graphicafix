<?php

include('dashboard_header.php');
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

// ── Save (add / edit) ─────────────────────────────────────────────────────────
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_resource'])) {
    $rid         = intval($_POST['resource_id'] ?? 0);
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $link        = trim($_POST['link']        ?? '');

    if (!$title) $errors[] = 'Title is required.';
    if ($link && !filter_var($link, FILTER_VALIDATE_URL)) $errors[] = 'Link must be a valid URL.';

    if (empty($errors)) {
        if ($rid) {
            $stmt = $conn->prepare("UPDATE resources SET title=?, description=?, link=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("sssi", $title, $description, $link, $rid);
        } else {
            $stmt = $conn->prepare("INSERT INTO resources (title, description, link, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("sss", $title, $description, $link);
        }
        $stmt->execute();
        header('Location: manage_resources.php?saved=1'); exit;
    }
}

// ── Fetch all resources ───────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
if ($search) {
    $s = "%$search%";
    $stmt = $conn->prepare("SELECT * FROM resources WHERE title LIKE ? OR description LIKE ? ORDER BY created_at DESC");
    $stmt->bind_param("ss", $s, $s);
    $stmt->execute();
    $resources = $stmt->get_result();
} else {
    $resources = $conn->query("SELECT * FROM resources ORDER BY created_at DESC");
}
$total = $conn->query("SELECT COUNT(*) FROM resources")->fetch_row()[0];
?>
<div class="height-100">

    <?php if (!empty($errors)): ?>
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:#b91c1c;font-size:13.5px;">
        <?php foreach($errors as $e): ?><p style="margin:4px 0;">⚠️ <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="main-card">
        <div class="main-header">
            <h3>📦 Resources</h3>
            <div style="display:flex;align-items:center;gap:10px;">
                <form method="GET" style="display:flex;align-items:center;gap:8px;">
                    <div class="search-box" style="min-width:0;">
                        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search resources…" style="padding:8px 36px 8px 12px;border:2px solid #e0e0e0;border-radius:8px;font-size:13.5px;outline:none;width:220px;">
                        <span class="search-icon">🔍</span>
                    </div>
                    <?php if ($search): ?>
                    <a href="manage_resources.php" style="font-size:13px;color:#ef4444;text-decoration:none;padding:6px 10px;background:#fef2f2;border-radius:6px;white-space:nowrap;">✕ Clear</a>
                    <?php endif; ?>
                </form>
                <button class="add-client" onclick="openModal()">+ Add Resource</button>
            </div>
        </div>

        <div class="main-table">
            <div class="table-head" style="grid-template-columns:2fr 2.5fr 0.8fr 0.9fr 0.9fr 0.4fr;">
                <span>Title</span>
                <span>Description</span>
                <span>Link</span>
                <span>Created</span>
                <span>Updated</span>
                <span></span>
            </div>

            <?php if ($resources->num_rows === 0): ?>
            <div style="text-align:center;padding:48px 20px;color:#888;">
                <div style="font-size:40px;margin-bottom:10px;">📭</div>
                <p style="margin:0;"><?= $search ? 'No resources match your search.' : 'No resources yet. Click "+ Add Resource" to get started.' ?></p>
            </div>
            <?php else: ?>
            <?php while ($r = $resources->fetch_assoc()): ?>
            <div class="table-row" id="row-<?= $r['id'] ?>" style="grid-template-columns:2fr 2.5fr 0.8fr 0.9fr 0.9fr 0.4fr;">
                <div class="client">
                    <div class="avatar" style="background:#e8f5e9;flex-shrink:0;">📄</div>
                    <div>
                        <strong><?= htmlspecialchars($r['title']) ?></strong>
                    </div>
                </div>
                <span style="color:#666;font-size:13.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;">
                    <?= htmlspecialchars($r['description'] ?: '—') ?>
                </span>
                <span>
                    <?php if ($r['link']): ?>
                    <a href="<?= htmlspecialchars($r['link']) ?>" target="_blank" style="color:#024442;font-weight:600;font-size:13px;text-decoration:none;">🔗 Open</a>
                    <?php else: ?>
                    <span style="color:#aaa;">—</span>
                    <?php endif; ?>
                </span>
                <span style="color:#888;font-size:13px;"><?= date('M d, Y', strtotime($r['created_at'])) ?></span>
                <span style="color:#888;font-size:13px;"><?= date('M d, Y', strtotime($r['updated_at'])) ?></span>
                <div style="position:relative;">
                    <button class="dots" onclick="toggleMenu(<?= $r['id'] ?>)">⋮</button>
                    <div id="menu-<?= $r['id'] ?>" style="display:none;position:absolute;right:0;top:100%;background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.12);min-width:150px;z-index:200;overflow:hidden;">
                        <a href="#" onclick="openEdit(<?= $r['id'] ?>);return false;" style="display:block;padding:10px 16px;font-size:13.5px;color:#374151;text-decoration:none;transition:background .15s;">✏️ Edit</a>
                        <a href="#" onclick="deleteResource(<?= $r['id'] ?>);return false;" style="display:block;padding:10px 16px;font-size:13.5px;color:#ef4444;text-decoration:none;transition:background .15s;">🗑️ Delete</a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Modal — uses your existing .modal-overlay / .modal-content classes ── -->
<div id="resModal" class="modal-overlay" onclick="if(event.target===this)closeModal()">
    <div class="modal-content">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon">📦</div>
                <span id="modalTitle">Add Resource</span>
            </h4>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>

        <form method="POST" id="resForm">
            <input type="hidden" name="save_resource" value="1">
            <input type="hidden" name="resource_id" id="resource_id" value="">

            <div class="modal-body">
                <div class="info-grid">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-500,#6b7280);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Title <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="title" id="f_title" placeholder="e.g. Brand Guidelines" required
                            style="width:100%;padding:10px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;font-family:inherit;transition:border-color .2s;">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-500,#6b7280);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Description</label>
                        <textarea name="description" id="f_description" rows="3" placeholder="Brief description of this resource…"
                            style="width:100%;padding:10px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;font-family:inherit;resize:vertical;transition:border-color .2s;"></textarea>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-500,#6b7280);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Link <span style="color:#aaa;font-weight:400;text-transform:none;">(optional)</span></label>
                        <input type="url" name="link" id="f_link" placeholder="https://…"
                            style="width:100%;padding:10px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;font-family:inherit;transition:border-color .2s;">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="saveBtn">💾 Save Resource</button>
            </div>
        </form>
    </div>
</div>

<!-- Toast -->
<div id="toastContainer" style="position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;"></div>

<style>
/* Focus states for modal inputs */
#resModal input:focus, #resModal textarea:focus {
    border-color: var(--accent, #b8f35a) !important;
    box-shadow: 0 0 0 3px rgba(184,243,90,.15);
}
/* Hover on dropdown items */
#resModal ~ * a:hover { background: #f9fafb; }
.dots-menu-item:hover { background: #f9fafb; }
</style>

<script>
// ── Dots menu toggle ──────────────────────────────────────────────────────────
function toggleMenu(id) {
    document.querySelectorAll('[id^="menu-"]').forEach(m => {
        if (m.id !== 'menu-' + id) m.style.display = 'none';
    });
    const menu = document.getElementById('menu-' + id);
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}
document.addEventListener('click', function(e) {
    if (!e.target.classList.contains('dots')) {
        document.querySelectorAll('[id^="menu-"]').forEach(m => m.style.display = 'none');
    }
});

// ── Modal ─────────────────────────────────────────────────────────────────────
function openModal() {
    document.getElementById('modalTitle').textContent = 'Add Resource';
    document.getElementById('resForm').reset();
    document.getElementById('resource_id').value = '';
    document.getElementById('saveBtn').textContent = '💾 Save Resource';
    document.getElementById('resModal').classList.add('active');
}

function openEdit(id) {
    document.getElementById('modalTitle').textContent = 'Edit Resource';
    document.getElementById('resForm').reset();
    document.getElementById('saveBtn').textContent = '✏️ Update Resource';
    document.getElementById('saveBtn').style.opacity = '.6';
    document.getElementById('saveBtn').style.pointerEvents = 'none';
    document.getElementById('resModal').classList.add('active');

    fetch('manage_resources.php', {
        method: 'POST',
        body: new URLSearchParams({ ajax_action: 'fetch', id: id })
    })
    .then(r => r.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                document.getElementById('resource_id').value   = data.data.id;
                document.getElementById('f_title').value       = data.data.title || '';
                document.getElementById('f_description').value = data.data.description || '';
                document.getElementById('f_link').value        = data.data.link || '';
            } else {
                showToast(data.message || 'Failed to load', 'error');
                closeModal();
            }
        } catch(e) {
            showToast('Server error', 'error');
            closeModal();
        }
        document.getElementById('saveBtn').style.opacity = '1';
        document.getElementById('saveBtn').style.pointerEvents = 'auto';
    });
}

function closeModal() {
    document.getElementById('resModal').classList.remove('active');
}

// ── Delete ────────────────────────────────────────────────────────────────────
function deleteResource(id) {
    if (!confirm('Delete this resource? This cannot be undone.')) return;
    fetch('manage_resources.php', {
        method: 'POST',
        body: new URLSearchParams({ ajax_action: 'delete', id: id })
    })
    .then(r => r.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                const row = document.getElementById('row-' + id);
                if (row) { row.style.transition = 'opacity .3s'; row.style.opacity = '0'; setTimeout(() => row.remove(), 300); }
                showToast('Resource deleted');
            } else {
                showToast(data.message || 'Delete failed', 'error');
            }
        } catch(e) { showToast('Server error', 'error'); }
    });
}

// ── Toast ─────────────────────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.style.cssText = `background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:220px;border-left:4px solid ${type==='success'?'#10b981':'#ef4444'};`;
    t.innerHTML = `<span>${type==='success'?'✓':'✕'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3000);
}

<?php if (isset($_GET['saved'])): ?>
showToast('Resource saved successfully');
<?php endif; ?>
</script>

<?php include('dashboard_footer.php'); ?>