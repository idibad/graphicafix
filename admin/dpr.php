<?php
include 'dashboard_header.php';

$is_manager = in_array($role, ['admin', 'pm', 'hrm']);

// Create table if it doesn't exist (safety fallback)
$conn->query("CREATE TABLE IF NOT EXISTS daily_progress_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    report_date DATE NOT NULL,
    tasks_completed TEXT NOT NULL,
    challenges TEXT,
    plan_tomorrow TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, report_date)
)");

// Fetch all users for the filter dropdown
$users_result = $conn->query("SELECT user_id, name, role FROM users ORDER BY name ASC");
$users = [];
if ($users_result) {
    while ($u = $users_result->fetch_assoc()) {
        $users[] = $u;
    }
}

// Handle date/user filters
$filter_date = $_GET['date'] ?? '';
$filter_user = isset($_GET['user_id']) ? intval($_GET['user_id']) : '';

// Handle pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12; // 12 reports per page (nice grid of 3x4)
$offset = ($page - 1) * $limit;

// ── Fetch Reports ─────────────────────────────────────────────────────────────
if ($is_manager) {
    $where = "1=1";
    if (!empty($filter_date)) {
        $where .= " AND dpr.report_date = '" . $conn->real_escape_string($filter_date) . "'";
    }
    if (!empty($filter_user)) {
        $where .= " AND dpr.user_id = $filter_user";
    }
    
    // Count total for pagination
    $count_query = "SELECT COUNT(*) as total FROM daily_progress_reports dpr WHERE $where";
    $total_records = $conn->query($count_query)->fetch_assoc()['total'];
    
    $query = "
        SELECT dpr.*, u.name as user_name, u.role as user_role 
        FROM daily_progress_reports dpr
        JOIN users u ON dpr.user_id = u.user_id
        WHERE $where
        ORDER BY dpr.report_date DESC, dpr.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
} else {
    // Count total for pagination
    $count_query = "SELECT COUNT(*) as total FROM daily_progress_reports WHERE user_id = $user_id";
    $total_records = $conn->query($count_query)->fetch_assoc()['total'];

    $query = "
        SELECT * FROM daily_progress_reports 
        WHERE user_id = $user_id
        ORDER BY report_date DESC, created_at DESC
        LIMIT $limit OFFSET $offset
    ";
}
$reports_result = $conn->query($query);
$total_pages = ceil($total_records / $limit);

// Check if user already submitted today
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT * FROM daily_progress_reports WHERE user_id = ? AND report_date = ?");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$today_report = $stmt->get_result()->fetch_assoc();

?>
<div class="height-100">
    <div class="projects-container">
        
        <div class="page-header">
            <div class="page-title-section">
                <h1><i class="fas fa-calendar-check" style="color:var(--primary);"></i> Daily Progress Reports</h1>
                <p><?= $is_manager ? 'Review team progress and daily updates' : 'Submit your daily report and track your progress' ?></p>
            </div>
            <div class="header-actions">
                <?php if (!$today_report): ?>
                    <button class="btn btn-primary-custom" onclick="openDprModal()"><i class="fas fa-plus"></i> Submit Today's Report</button>
                <?php else: ?>
                    <button class="btn btn-secondary-custom" onclick="openDprModal(true)"><i class="fas fa-edit"></i> Edit Today's Report</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_manager): ?>
        <!-- Filters for Managers -->
        <div class="d-card" style="margin-bottom: 30px; padding: 20px 25px; border-radius: 16px; background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #f0f0f0;">
            <form method="GET" style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-end;">
                <div style="flex: 1; min-width: 200px;">
                    <label style="display:block; font-size:12px; font-weight:800; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;"><i class="fas fa-calendar-alt"></i> Filter by Date</label>
                    <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" class="form-input-custom" style="width:100%; border:2px solid #e5e7eb; border-radius:10px; padding:10px 14px; background:#f9fafb; font-weight:600; color:#374151; transition:all 0.3s;">
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <label style="display:block; font-size:12px; font-weight:800; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;"><i class="fas fa-user-tag"></i> Filter by User</label>
                    <div style="position:relative;">
                        <select name="user_id" class="form-input-custom" style="width:100%; border:2px solid #e5e7eb; border-radius:10px; padding:10px 14px; background:#f9fafb; font-weight:600; color:#374151; appearance:none; transition:all 0.3s;">
                            <option value="">All Team Members</option>
                            <?php foreach($users as $u): ?>
                                <option value="<?= $u['user_id'] ?>" <?= $filter_user == $u['user_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['name']) ?> (<?= ucfirst($u['role']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down" style="position:absolute; right:14px; top:14px; color:#9ca3af; pointer-events:none;"></i>
                    </div>
                </div>
                <div style="display:flex; gap:12px;">
                    <button type="submit" class="btn-primary-custom" style="padding:12px 24px; border-radius:10px; font-weight:700; box-shadow:0 4px 12px rgba(79,70,229,0.2);"><i class="fas fa-filter"></i> Apply</button>
                    <a href="dpr.php" class="btn-secondary-custom" style="padding:12px 24px; border-radius:10px; font-weight:700; display:inline-flex; align-items:center; text-decoration:none; background:#f3f4f6; color:#4b5563; border:none;"><i class="fas fa-undo"></i> Clear</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Table Card -->
        <div class="main-card">
            <div class="main-header">
                <h3><i class="fas fa-clipboard-list"></i> Progress Reports</h3>
                <span style="font-size:13px;color:#888;"><?= $total_records ?> result<?= $total_records != 1 ? 's' : '' ?></span>
            </div>

            <!-- Table -->
            <div class="main-table" style="padding-top:8px;">
                <div class="table-head" style="grid-template-columns: <?= $is_manager ? '2fr 1.5fr 1fr' : '2fr 1fr' ?>;">
                    <?php if($is_manager): ?><span>Name</span><?php endif; ?>
                    <span>Date</span>
                    <span>Actions</span>
                </div>

                <?php if ($reports_result && $reports_result->num_rows > 0): ?>
                    <?php while ($row = $reports_result->fetch_assoc()): ?>
                        <div class="table-row" style="grid-template-columns: <?= $is_manager ? '2fr 1.5fr 1fr' : '2fr 1fr' ?>; align-items:center;">
                            
                            <?php if($is_manager): ?>
                            <div class="client">
                                <div class="avatar" style="background:var(--primary); color:#fff;">
                                    <?= strtoupper(substr($row['user_name'] ?? 'U', 0, 1)) ?>
                                </div>
                                <div>
                                    <strong><?= htmlspecialchars($row['user_name'] ?? 'Unknown User') ?></strong>
                                    <small><?= ucfirst($row['user_role'] ?? 'User') ?></small>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div style="display:flex; align-items:center; gap:10px;">
                                <span style="font-size:13.5px;color:#666;"><?= date('l, F j, Y', strtotime($row['report_date'])) ?></span>
                                <?php if($row['report_date'] == date('Y-m-d')): ?>
                                    <span style="background:#e0f2fe; color:#0284c7; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:700;">Today</span>
                                <?php endif; ?>
                            </div>

                            <div style="display:flex;align-items:center;gap:4px;">
                                <button class="dots view-details" title="View details"
                                    data-name="<?= htmlspecialchars($row['user_name'] ?? 'Your') ?>"
                                    data-date="<?= date('l, F j, Y', strtotime($row['report_date'])) ?>"
                                    data-tasks="<?= htmlspecialchars($row['tasks_completed']) ?>"
                                    data-challenges="<?= htmlspecialchars($row['challenges']) ?>"
                                    data-plan="<?= htmlspecialchars($row['plan_tomorrow']) ?>"
                                    onclick="openViewModal(this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:40px 20px; color:#888;">
                        There are no daily progress reports matching your criteria.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
        <div style="margin-top: 40px; display:flex; justify-content:center; gap:10px;">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php 
                    $query_params = $_GET;
                    $query_params['page'] = $i;
                    $page_url = '?' . http_build_query($query_params);
                ?>
                <a href="<?= htmlspecialchars($page_url) ?>" class="btn <?= $i === $page ? 'btn-primary-custom' : '' ?>" style="<?= $i !== $page ? 'background:#fff; color:#4b5563; border:2px solid #e5e7eb;' : 'box-shadow:0 4px 12px rgba(79,70,229,0.3);' ?> padding:8px 18px; text-decoration:none; border-radius:12px; font-weight:800; font-size:15px; transition:all 0.3s;">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Modal for User DPR Submission -->
<div id="dprModal" class="modal-overlay" onclick="if(event.target===this)closeDprModal()">
    <div class="modal-content" style="max-width:600px;">
        <div class="modal-header">
            <h4><div class="modal-header-icon"><i class="fas fa-pen-alt"></i></div><span id="modalTitle">Submit Today's Report</span></h4>
            <button class="modal-close" onclick="closeDprModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="dprForm">
            <input type="hidden" name="action" id="formAction" value="save_dpr">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; font-weight:700; color:#333; margin-bottom:6px;">Tasks Completed Today <span style="color:#ef4444;">*</span></label>
                    <textarea name="tasks_completed" id="f_tasks" rows="4" required class="form-input-custom" placeholder="- Finished the homepage UI&#10;- Fixed login bug"></textarea>
                </div>
                <div class="form-group" style="margin-bottom:15px;">
                    <label style="display:block; font-size:13px; font-weight:700; color:#333; margin-bottom:6px;">Challenges / Blockers (Optional)</label>
                    <textarea name="challenges" id="f_challenges" rows="2" class="form-input-custom" placeholder="Any issues blocking your progress?"></textarea>
                </div>
                <div class="form-group" style="margin-bottom:5px;">
                    <label style="display:block; font-size:13px; font-weight:700; color:#333; margin-bottom:6px;">Plan for Tomorrow <span style="color:#ef4444;">*</span></label>
                    <textarea name="plan_tomorrow" id="f_plan" rows="3" required class="form-input-custom" placeholder="What will you focus on tomorrow?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeDprModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="dprSubmitBtn">
                    <span id="dprBtnText"><i class="fas fa-paper-plane"></i> Submit Report</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal for Viewing DPR Details -->
<div id="viewModal" class="modal-overlay" onclick="if(event.target===this)closeViewModal()">
    <div class="modal-content" style="max-width:600px;">
        <div class="modal-header">
            <h4><div class="modal-header-icon"><i class="fas fa-eye"></i></div><span id="viewModalTitle">Report Details</span></h4>
            <button class="modal-close" onclick="closeViewModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom:15px;">
                <label style="display:block; font-size:12px; font-weight:800; color:#0284c7; text-transform:uppercase; margin-bottom:4px;">Tasks Completed</label>
                <div id="viewTasks" style="font-size:14px; color:#444; background:#f8fafc; padding:12px; border-radius:8px; border-left:3px solid #0ea5e9; white-space:pre-wrap;"></div>
            </div>
            <div style="margin-bottom:15px;" id="viewChallengesContainer">
                <label style="display:block; font-size:12px; font-weight:800; color:#ef4444; text-transform:uppercase; margin-bottom:4px;">Challenges & Blockers</label>
                <div id="viewChallenges" style="font-size:14px; color:#444; background:#fef2f2; padding:12px; border-radius:8px; border-left:3px solid #ef4444; white-space:pre-wrap;"></div>
            </div>
            <div style="margin-bottom:5px;">
                <label style="display:block; font-size:12px; font-weight:800; color:#10b981; text-transform:uppercase; margin-bottom:4px;">Plan for Tomorrow</label>
                <div id="viewPlan" style="font-size:14px; color:#444; background:#f0fdf4; padding:12px; border-radius:8px; border-left:3px solid #10b981; white-space:pre-wrap;"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary-custom" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<div id="toastContainer" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;"></div>

<script>
<?php if ($today_report): ?>
const existingReport = <?= json_encode($today_report) ?>;
<?php else: ?>
const existingReport = null;
<?php endif; ?>

function openViewModal(btn) {
    document.getElementById('viewModalTitle').textContent = btn.dataset.name + "'s Report (" + btn.dataset.date + ")";
    document.getElementById('viewTasks').textContent = btn.dataset.tasks;
    
    if(btn.dataset.challenges && btn.dataset.challenges.trim() !== '') {
        document.getElementById('viewChallengesContainer').style.display = 'block';
        document.getElementById('viewChallenges').textContent = btn.dataset.challenges;
    } else {
        document.getElementById('viewChallengesContainer').style.display = 'none';
    }
    
    document.getElementById('viewPlan').textContent = btn.dataset.plan;
    document.getElementById('viewModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
    document.body.style.overflow = '';
}

function openDprModal(isEdit = false) {
    const modal = document.getElementById('dprModal');
    document.getElementById('dprForm').reset();
    
    if (isEdit && existingReport) {
        document.getElementById('modalTitle').textContent = "Edit Today's Report";
        document.getElementById('dprBtnText').innerHTML = '<i class="fas fa-save"></i> Update Report';
        document.getElementById('f_tasks').value = existingReport.tasks_completed;
        document.getElementById('f_challenges').value = existingReport.challenges || '';
        document.getElementById('f_plan').value = existingReport.plan_tomorrow;
    } else {
        document.getElementById('modalTitle').textContent = "Submit Today's Report";
        document.getElementById('dprBtnText').innerHTML = '<i class="fas fa-paper-plane"></i> Submit Report';
    }
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDprModal() {
    document.getElementById('dprModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('dprForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('dprSubmitBtn');
    const txt = document.getElementById('dprBtnText');
    const orig = txt.innerHTML;
    
    btn.disabled = true;
    txt.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    const payload = new URLSearchParams();
    payload.append('action', 'save_dpr');
    payload.append('tasks_completed', document.getElementById('f_tasks').value);
    payload.append('challenges', document.getElementById('f_challenges').value);
    payload.append('plan_tomorrow', document.getElementById('f_plan').value);
    
    fetch('dpr_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: payload.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeDprModal();
            showToast(data.message || 'Report saved successfully!');
            setTimeout(() => location.reload(), 1000);
        } else {
            let errorMsg = data.message || 'Error saving report';
            if (data.debug_post) {
                errorMsg += ' | POST keys: ' + Object.keys(data.debug_post).join(', ');
            }
            showToast(errorMsg, 'error');
            btn.disabled = false;
            txt.innerHTML = orig;
        }
    })
    .catch(err => {
        showToast('Server error', 'error');
        btn.disabled = false;
        txt.innerHTML = orig;
    });
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
