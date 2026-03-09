<?php
include('dashboard_header.php');

// Stats
$total_result    = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM notices");
$total_notices   = mysqli_fetch_assoc($total_result)['cnt'];

$today_result    = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM notices WHERE DATE(`date`) = CURDATE()");
$today_notices   = mysqli_fetch_assoc($today_result)['cnt'];

$week_result     = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM notices WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$week_notices    = mysqli_fetch_assoc($week_result)['cnt'];

// All notices
$notices_result  = mysqli_query($conn, "SELECT * FROM notices ORDER BY `date` DESC");
?>

<style>
    * { padding: 0; margin: 0; }

    /* ── Page header ── */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
    }

    /* ── Buttons ── */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    .btn-primary {
        background: linear-gradient(135deg, var(--accent) 0%, #87bd0aff 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .btn-primary:hover  { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.2); }
    .btn-secondary {
        background: #f8f9fa;
        color: #333;
        border: 2px solid #f0f0f0;
    }
    .btn-secondary:hover { background: #fcfff5ff; border-color: var(--accent); color: var(--accent); }

    /* ── Stat cards ── */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        border-top: 4px solid var(--accent);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .stat-card::before {
        content: '';
        position: absolute;
        top: -40px; right: -40px;
        width: 100px; height: 100px;
        background: rgba(0,0,0,0.03);
        border-radius: 50%;
    }
    .stat-card:hover  { transform: translateY(-4px); box-shadow: 0 6px 20px rgba(0,0,0,0.12); }
    .stat-card.green  { border-top-color: #00b894; }
    .stat-card.orange { border-top-color: #e17055; }

    .stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
    .stat-label  { font-size: 13px; color: #999; font-weight: 500; }
    .stat-icon   { font-size: 1.6rem; }
    .stat-value  { font-size: 2.2rem; font-weight: 700; color: #333; line-height: 1; }

    /* ── Table column layout ── */
    .table-head,
    .table-row { grid-template-columns: 2.5fr 1fr 1.2fr 0.8fr; }

    .notice-title-cell { font-weight: 600; color: #333; font-size: 14px; }
    .notice-desc-cell  { font-size: 13px; color: #777; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 400px; }

    .date-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #f0f0f0;
        color: #555;
        padding: 4px 10px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 500;
    }
    .date-badge.today   { background: #e5fff5; color: #00b894; }
    .date-badge.recent  { background: #fff4e5; color: #e17055; }

    .posted-by {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: 13px;
        color: #555;
    }
    .posted-avatar {
        width: 26px; height: 26px;
        border-radius: 7px;
        background: linear-gradient(135deg, var(--accent) 0%, #87bd0aff 100%);
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 11px; font-weight: 700; flex-shrink: 0;
    }

    .actions { display: flex; gap: 8px; }
    .btn-icon {
        width: 32px; height: 32px;
        border: none; border-radius: 8px; cursor: pointer;
        font-size: 14px; display: flex; align-items: center; justify-content: center;
        transition: all 0.3s ease; background: #f0f0f0;
    }
    .btn-icon.edit:hover   { background: #fcfff5ff; transform: scale(1.1); }
    .btn-icon.delete:hover { background: #ffe5e5;   transform: scale(1.1); }

    .empty-state { text-align: center; padding: 60px 24px; color: #999; font-size: 15px; }

    /* ── Modal ── */
    .modal {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,0.45); z-index: 1000;
        align-items: center; justify-content: center;
        backdrop-filter: blur(3px);
    }
    .modal.open { display: flex; }
    .modal-content {
        background: white; border-radius: 20px;
        width: 100%; max-width: 520px; margin: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        overflow: hidden; animation: slideUp 0.3s ease;
    }
    @keyframes slideUp {
        from { transform: translateY(30px); opacity: 0; }
        to   { transform: translateY(0);    opacity: 1; }
    }
    .modal-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 22px 24px; border-bottom: 2px solid #f0f0f0;
    }
    .modal-header h3 { display: flex; align-items: center; gap: 10px; font-size: 20px; font-weight: 600; color: #333; }
    .modal-header-icon {
        width: 36px; height: 36px; border-radius: 10px;
        background: linear-gradient(135deg, var(--accent) 0%, #87bd0aff 100%);
        display: flex; align-items: center; justify-content: center; font-size: 18px;
    }
    .modal-close {
        width: 34px; height: 34px; border: none; background: #f8f9fa;
        border-radius: 8px; font-size: 18px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        color: #666; transition: all 0.2s;
    }
    .modal-close:hover { background: #ffe5e5; color: #d63031; }
    .modal-body { padding: 24px; }
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; font-size: 14px; font-weight: 600; color: #333; margin-bottom: 7px; }
    .required { color: #d63031; }
    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%; padding: 11px 14px;
        border: 2px solid #f0f0f0; border-radius: 10px;
        font-size: 14px; color: #333; background: #f8f9fa;
        transition: all 0.3s ease; outline: none;
        font-family: inherit;
    }
    .form-group textarea { resize: vertical; min-height: 100px; }
    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        border-color: var(--accent); background: #fcfff5ff;
        box-shadow: 0 0 0 3px rgba(135,189,10,0.1);
    }
    .form-hint { font-size: 12px; color: #999; margin-top: 5px; }
    .modal-footer {
        display: flex; justify-content: flex-end; gap: 12px;
        padding: 18px 24px; border-top: 2px solid #f0f0f0;
    }

    @media (max-width: 768px) {
        .stats-grid  { grid-template-columns: repeat(2, 1fr); }
        .table-head  { display: none; }
        .table-row   { grid-template-columns: 1fr; gap: 8px; }
    }
</style>

<div class="dashboard-container">

    <!-- Page Header -->
    <div class="page-header">
        <div class="welcome-text" style="margin-bottom:0;">
            <h1>Notices</h1>
            <p>Post and manage announcements for all users</p>
        </div>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <button class="btn btn-primary" onclick="openNoticeModal('add')">
                <span>📢</span> Post Notice
            </button>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header"><span class="stat-label">Total Notices</span><span class="stat-icon">📋</span></div>
            <div class="stat-value"><?= $total_notices ?></div>
        </div>
        <div class="stat-card green">
            <div class="stat-header"><span class="stat-label">Posted Today</span><span class="stat-icon">📅</span></div>
            <div class="stat-value"><?= $today_notices ?></div>
        </div>
        <div class="stat-card orange">
            <div class="stat-header"><span class="stat-label">This Week</span><span class="stat-icon">📆</span></div>
            <div class="stat-value"><?= $week_notices ?></div>
        </div>
    </div>

    <!-- Notices Table -->
    <div class="main-card">
        <div class="main-header">
            <h3>📢 All Notices</h3>
        </div>

        <div class="main-table">

            <div class="table-head">
                <span>Notice</span>
                <span>Date</span>
                <span>Posted By</span>
                <span>Actions</span>
            </div>

            <?php if (mysqli_num_rows($notices_result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($notices_result)):
                    $noticeDate  = strtotime($row['date']);
                    $today       = strtotime('today');
                    $weekAgo     = strtotime('-7 days');
                    $postedBy    = $row['created_by'] ?? 'Admin';
                    $avatarLetter = strtoupper(substr($postedBy, 0, 1));

                    if ($noticeDate >= $today) {
                        $dateClass = 'today';
                    } elseif ($noticeDate >= $weekAgo) {
                        $dateClass = 'recent';
                    } else {
                        $dateClass = '';
                    }
                ?>
                <div class="table-row">
                    <div>
                        <div class="notice-title-cell"><?= htmlspecialchars($row['notice_title']) ?></div>
                        <?php if (!empty($row['notice_body'])): ?>
                        <div class="notice-desc-cell"><?= htmlspecialchars($row['notice_body']) ?></div>
                        <?php endif; ?>
                    </div>
                    <span>
                        <span class="date-badge <?= $dateClass ?>">
                            📅 <?= date('M d, Y', $noticeDate) ?>
                        </span>
                    </span>
                    <span>
                        <div class="posted-by">
                            <div class="posted-avatar"><?= $avatarLetter ?></div>
                            <?= htmlspecialchars($postedBy) ?>
                        </div>
                    </span>
                   <div class="actions">
                        <button class="btn-icon edit" onclick="openNoticeModal('edit', <?= $row['notice_id'] ?>)" title="Edit">✏️</button>
                        <button class="btn-icon delete" onclick="deleteNotice(<?= $row['notice_id'] ?>)" title="Delete">🗑️</button>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">📭 No notices posted yet.</div>
            <?php endif; ?>

        </div>
    </div>

</div>

<!-- Notice Modal -->
<div id="noticeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <div class="modal-header-icon" id="modalIcon">📢</div>
                <span id="modalTitle">Post Notice</span>
            </h3>
            <button class="modal-close" onclick="closeNoticeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="noticeForm">
                <input type="hidden" name="notice_id" id="notice_id">

                <div class="form-group">
                    <label>Notice Title <span class="required">*</span></label>
                    <input type="text" name="notice_title" id="notice_title" placeholder="Enter notice title" required>
                </div>

                <div class="form-group">
                    <label>Body / Details</label>
                    <textarea name="notice_body" id="notice_body" placeholder="Enter additional details (optional)"></textarea>
                    <div class="form-hint">💡 This will appear as a subtitle under the title</div>
                </div>

                <div class="form-group">
                    <label>Date <span class="required">*</span></label>
                    <input type="date" name="date" id="notice_date" required>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeNoticeModal()">Cancel</button>
            <button type="submit" form="noticeForm" class="btn btn-primary" id="saveBtn">
                <span id="saveBtnText">Post Notice</span>
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Set today's date as default
    document.getElementById('notice_date').value = new Date().toISOString().split('T')[0];

    function openNoticeModal(mode, noticeId = null) {
        const modal    = document.getElementById('noticeModal');
        const title    = document.getElementById('modalTitle');
        const icon     = document.getElementById('modalIcon');
        const saveText = document.getElementById('saveBtnText');

        document.getElementById('noticeForm').reset();
        document.getElementById('notice_id').value = '';
        document.getElementById('notice_date').value = new Date().toISOString().split('T')[0];

        if (mode === 'add') {
            title.textContent    = 'Post Notice';
            icon.textContent     = '📢';
            saveText.textContent = 'Post Notice';
        } else {
            title.textContent    = 'Edit Notice';
            icon.textContent     = '✏️';
            saveText.textContent = 'Update Notice';

            $.get('get_notice.php', { id: noticeId }, function(data) {
                $('#notice_id').val(data.id);
                $('#notice_title').val(data.notice_title);
                $('#notice_body').val(data.notice_body);
                $('#notice_date').val(data.date);
            }, 'json');
        }

        modal.classList.add('open');
    }

    function closeNoticeModal() {
        document.getElementById('noticeModal').classList.remove('open');
    }

    document.getElementById('noticeModal').addEventListener('click', function(e) {
        if (e.target === this) closeNoticeModal();
    });

    $('#noticeForm').on('submit', function(e) {
        e.preventDefault();
        const saveBtn  = $('#saveBtn');
        const saveText = $('#saveBtnText');
        const noticeId = $('#notice_id').val();
        const isEdit   = noticeId !== '';

        saveText.text('Saving...');
        saveBtn.prop('disabled', true);

        $.ajax({
            url: isEdit ? 'edit_notice.php' : 'add_notice.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    closeNoticeModal();
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() { alert('Server error. Please try again.'); },
            complete: function() {
                saveText.text(isEdit ? 'Update Notice' : 'Post Notice');
                saveBtn.prop('disabled', false);
            }
        });
    });

    function deleteNotice(noticeId) {
        if (!confirm('Are you sure you want to delete this notice?')) return;

        $.ajax({
            url: 'delete_notice.php',
            type: 'POST',
            data: { notice_id: noticeId },
            dataType: 'json',
            success: function(response) {
                if (response.success) { location.reload(); }
                else { alert('Error: ' + response.message); }
            },
            error: function() { alert('Server error. Please try again.'); }
        });
    }
</script>

<?php include('dashboard_footer.php'); ?>