<?php

include('dashboard_header.php');

$total_notices = $conn->query("SELECT COUNT(*) AS c FROM notices")->fetch_assoc()['c'] ?? 0;
$today_notices = $conn->query("SELECT COUNT(*) AS c FROM notices WHERE DATE(`date`) = CURDATE()")->fetch_assoc()['c'] ?? 0;
$week_notices  = $conn->query("SELECT COUNT(*) AS c FROM notices WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc()['c'] ?? 0;
$notices_result = $conn->query("SELECT * FROM notices ORDER BY `date` DESC");
?>

<div class="height-100">

    <!-- Stats -->
    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clipboard"></i></div>
            <div class="stat-number"><?= $total_notices ?></div>
            <div class="stat-label">Total Notices</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-number"><?= $today_notices ?></div>
            <div class="stat-label">Posted Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-number"><?= $week_notices ?></div>
            <div class="stat-label">This Week</div>
        </div>
    </div>

    <!-- Table -->
    <div class="main-card">
        <div class="main-header">
            <h3><i class="fas fa-bullhorn"></i> All Notices</h3>
            <button class="add-client" onclick="openModal()">+ Post Notice</button>
        </div>

        <div class="main-table">
            <div class="table-head" style="grid-template-columns:2.5fr 1fr 1.2fr 0.8fr;">
                <span>Notice</span>
                <span>Date</span>
                <span>Posted By</span>
                <span>Actions</span>
            </div>

            <?php if ($notices_result && $notices_result->num_rows > 0):
                while ($row = $notices_result->fetch_assoc()):
                    $noticeDate   = strtotime($row['date']);
                    $postedBy     = $row['created_by'] ?? 'Admin';
                    $dateClass    = $noticeDate >= strtotime('today') ? 'today' : ($noticeDate >= strtotime('-7 days') ? 'recent' : '');
            ?>
            <div class="table-row" style="grid-template-columns:2.5fr 1fr 1.2fr 0.8fr;">
                <div>
                    <strong style="display:block;font-size:14px;color:#333;"><?= htmlspecialchars($row['notice_title']) ?></strong>
                    <?php if (!empty($row['content'])): ?>
                    <span style="font-size:12.5px;color:#888;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;max-width:380px;">
                        <?= htmlspecialchars($row['content']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <span data-label="Date">
                    <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:10px;font-size:12px;font-weight:500;
                        background:<?= $dateClass==='today'?'#e5fff5':($dateClass==='recent'?'#fff4e5':'#f0f0f0') ?>;
                        color:<?= $dateClass==='today'?'#00b894':($dateClass==='recent'?'#e17055':'#555') ?>;">
                        <i class="fas fa-calendar-alt"></i> <?= date('M d, Y', $noticeDate) ?>
                    </span>
                </span>
                <span data-label="Posted By">
                    <div style="display:inline-flex;align-items:center;gap:7px;font-size:13px;color:#555;">
                        <div style="width:26px;height:26px;border-radius:7px;background:linear-gradient(135deg,var(--accent),#87bd0aff);display:flex;align-items:center;justify-content:center;color:white;font-size:11px;font-weight:700;flex-shrink:0;">
                            <?= strtoupper(substr($postedBy, 0, 1)) ?>
                        </div>
                        <?= htmlspecialchars($postedBy) ?>
                    </div>
                </span>
                <div style="display:flex;gap:6px;">
                    <button class="dots" onclick="openEditModal(<?= $row['notice_id'] ?>)" title="Edit"><i class="fas fa-pencil-alt"></i>️</button>
                    <button class="dots" onclick="deleteNotice(<?= $row['notice_id'] ?>)" title="Delete" style="color:#ef4444;"><i class="fas fa-trash"></i>️</button>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div style="text-align:center;padding:60px 24px;color:#888;">
                <div style="font-size:36px;margin-bottom:8px;"><i class="fas fa-envelope-open"></i></div>
                No notices posted yet.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Notice Modal -->
<div id="noticeModal" class="modal-overlay" onclick="if(event.target===this)closeModal()">
    <div class="modal-content">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon" id="modalIcon"><i class="fas fa-bullhorn"></i></div>
                <span id="modalTitle">Post Notice</span>
            </h4>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form id="noticeForm">
            <input type="hidden" id="notice_id" name="notice_id" value="">
            <div class="modal-body">
                <div class="info-grid">
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:7px;">
                            Notice Title <span style="color:#ef4444;">*</span>
                        </label>
                        <input type="text" id="f_title" name="notice_title" placeholder="Enter notice title" required
                            style="width:100%;padding:11px 14px;border:2px solid #f0f0f0;border-radius:10px;font-size:14px;background:#f8f9fa;outline:none;transition:border-color .2s;box-sizing:border-box;font-family:inherit;">
                    </div>
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:7px;">Body / Details</label>
                        <textarea id="f_body" name="notice_body" rows="3" placeholder="Additional details (optional)"
                            style="width:100%;padding:11px 14px;border:2px solid #f0f0f0;border-radius:10px;font-size:14px;background:#f8f9fa;outline:none;resize:vertical;transition:border-color .2s;box-sizing:border-box;font-family:inherit;"></textarea>
                    </div>
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:7px;">
                            Date <span style="color:#ef4444;">*</span>
                        </label>
                        <input type="date" id="f_date" name="date" required
                            style="width:100%;padding:11px 14px;border:2px solid #f0f0f0;border-radius:10px;font-size:14px;background:#f8f9fa;outline:none;box-sizing:border-box;font-family:inherit;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="saveBtn"><i class="fas fa-bullhorn"></i> Post Notice</button>
            </div>
        </form>
    </div>
</div>

<div id="toastContainer" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;"></div>

<script>
// Set today's date on load
document.getElementById('f_date').value = new Date().toISOString().split('T')[0];

function openModal() {
    document.getElementById('modalTitle').textContent  = 'Post Notice';
    document.getElementById('modalIcon').textContent   = '<i class="fas fa-bullhorn"></i>';
    document.getElementById('saveBtn').textContent     = '<i class="fas fa-bullhorn"></i> Post Notice';
    document.getElementById('notice_id').value         = '';
    document.getElementById('noticeForm').reset();
    document.getElementById('f_date').value = new Date().toISOString().split('T')[0];
    document.getElementById('noticeModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function openEditModal(id) {
    document.getElementById('modalTitle').textContent  = 'Edit Notice';
    document.getElementById('modalIcon').textContent   = '<i class="fas fa-pencil-alt"></i>️';
    document.getElementById('saveBtn').textContent     = '<i class="fas fa-pencil-alt"></i>️ Update Notice';
    document.getElementById('saveBtn').style.opacity   = '.6';
    document.getElementById('saveBtn').style.pointerEvents = 'none';
    document.getElementById('noticeModal').classList.add('active');
    document.body.style.overflow = 'hidden';

    fetch('notice_action.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'get', id: id })
    })
    .then(r => r.text())
    .then(text => {
        const start = text.lastIndexOf('{"');
        try {
            const data = JSON.parse(start !== -1 ? text.slice(start) : text);
            if (data.success) {
                document.getElementById('notice_id').value = data.notice.notice_id;
                document.getElementById('f_title').value  = data.notice.notice_title || '';
                document.getElementById('f_body').value   = data.notice.content || data.notice.notice_body || '';
                document.getElementById('f_date').value   = data.notice.date         || '';
            } else {
                showToast(data.message || 'Failed to load', 'error');
                closeModal();
            }
        } catch(e) { showToast('Server error', 'error'); closeModal(); }
        document.getElementById('saveBtn').style.opacity = '1';
        document.getElementById('saveBtn').style.pointerEvents = 'auto';
    });
}

function closeModal() {
    document.getElementById('noticeModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('noticeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const id  = document.getElementById('notice_id').value;
    const btn = document.getElementById('saveBtn');
    btn.textContent = 'Saving…';
    btn.disabled    = true;

    const data = new URLSearchParams(new FormData(this));
    data.set('action', id ? 'edit' : 'add');

    fetch('notice_action.php', { method: 'POST', body: data })
    .then(r => r.text())
    .then(text => {
        const start = text.lastIndexOf('{"');
        try {
            const res = JSON.parse(start !== -1 ? text.slice(start) : text);
            if (res.success) {
                closeModal();
                showToast(id ? 'Notice updated!' : 'Notice posted!');
                setTimeout(() => location.reload(), 900);
            } else {
                showToast(res.message || 'Failed to save', 'error');
            }
        } catch(e) { showToast('Server error', 'error'); }
        btn.textContent = id ? '<i class="fas fa-pencil-alt"></i>️ Update Notice' : '<i class="fas fa-bullhorn"></i> Post Notice';
        btn.disabled    = false;
    });
});

function deleteNotice(id) {
    if (!confirm('Delete this notice? This cannot be undone.')) return;

    fetch('notice_action.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'delete', id: id })
    })
    .then(r => r.text())
    .then(text => {
        const start = text.lastIndexOf('{"');
        try {
            const res = JSON.parse(start !== -1 ? text.slice(start) : text);
            if (res.success) {
                showToast('Notice deleted.');
                setTimeout(() => location.reload(), 900);
            } else {
                showToast(res.message || 'Delete failed', 'error');
            }
        } catch(e) { showToast('Server error', 'error'); }
    });
}

function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.style.cssText = `background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:220px;border-left:4px solid ${type==='success'?'#10b981':'#ef4444'};`;
    t.innerHTML = `<span style="font-weight:700;color:${type==='success'?'#10b981':'#ef4444'}">${type==='success'?'<i class="fas fa-check"></i>':'<i class="fas fa-times"></i>'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3000);
}
</script>

<?php include('dashboard_footer.php'); ?>