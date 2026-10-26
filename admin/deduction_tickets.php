<?php
/**
 * admin/deduction_tickets.php
 * Deduction Dispute & Ticket Resolution Center
 * Allows employees to track their dispute tickets, and HR/Admins to review and waive penalties.
 */

require_once __DIR__ . '/dashboard_header.php';
require_once __DIR__ . '/../core/attendance_schema.php';
require_once __DIR__ . '/../core/attendance_helper.php';

ensure_attendance_schema($conn);

$is_hr_admin = ($is_super_admin || $is_admin_officer || $role === 'hrm' || $role === 'manager');
$filter_status = $_GET['status'] ?? '';

$toast_message = '';
$toast_type    = '';

if (isset($_SESSION['toast_msg'])) {
    $toast_message = $_SESSION['toast_msg'];
    $toast_type    = $_SESSION['toast_type'] ?? 'success';
    unset($_SESSION['toast_msg'], $_SESSION['toast_type']);
}

// ── Query Tickets ─────────────────────────────────────────────────────────────
$where_clauses = [];
$params = [];
$types = '';

if (!$is_hr_admin) {
    // Regular employees only see their own tickets
    $where_clauses[] = "t.user_id = ?";
    $params[] = $user_id;
    $types .= 'i';
}

if (!empty($filter_status)) {
    $where_clauses[] = "t.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$sql = "
    SELECT t.*, 
           u.name as user_name, u.username, u.department,
           d.deduction_type, d.amount, d.description as deduction_desc, d.date_incurred, d.salary_month,
           rev.name as reviewer_name
    FROM deduction_tickets t
    INNER JOIN users u ON t.user_id = u.user_id
    INNER JOIN salary_deductions d ON t.deduction_id = d.id
    LEFT JOIN users rev ON t.reviewed_by = rev.user_id
    {$where_sql}
    ORDER BY t.created_at DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Stats counts
$cnt_open = 0; $cnt_waived = 0; $cnt_rejected = 0;
$count_stmt = $conn->query("SELECT status, COUNT(*) as cnt FROM deduction_tickets " . (!$is_hr_admin ? "WHERE user_id = {$user_id}" : "") . " GROUP BY status");
if ($count_stmt) {
    while ($r = $count_stmt->fetch_assoc()) {
        if ($r['status'] === 'open' || $r['status'] === 'under_review') $cnt_open += $r['cnt'];
        elseif ($r['status'] === 'approved_waived') $cnt_waived += $r['cnt'];
        elseif ($r['status'] === 'rejected') $cnt_rejected += $r['cnt'];
    }
}
?>

<style>
.ticket-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 10px 25px -3px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    border: 1px solid #f0f3f2;
}
.ticket-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    padding: 24px 28px;
    border-bottom: 1px solid #f4f5f4;
}
.ticket-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary, #024442);
    display: flex;
    align-items: center;
    gap: 10px;
}
.ticket-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}
.tstat-card {
    background: #fff;
    border-radius: 14px;
    padding: 20px 22px;
    border: 1px solid #f0f3f2;
    display: flex;
    align-items: center;
    gap: 16px;
}
.tstat-icon {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}
.badge-status {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    display: inline-block;
}
.badge-status.open { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.badge-status.under_review { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; }
.badge-status.approved_waived { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.badge-status.rejected { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

.ticket-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
}
.ticket-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 14px 16px;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}
.ticket-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    vertical-align: middle;
}
.ticket-table tr:hover td { background: #fbfdfc; }

/* Modal */
.modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px);
    z-index: 9999; display: none; align-items: center; justify-content: center; padding: 20px;
    overflow-y: auto !important; -webkit-overflow-scrolling: touch; box-sizing: border-box;
}
.modal-overlay.active { display: flex; }
.modal-content {
    background: #fff; border-radius: 16px; width: 100%; max-width: 580px; max-height: 90vh !important;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); overflow-y: auto !important;
    display: flex; flex-direction: column; box-sizing: border-box;
}
.modal-header {
    background: var(--primary, #024442); color: #fff; padding: 18px 24px;
    display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;
}
.modal-header h4 { margin: 0; font-size: 1.15rem; font-weight: 700; display:flex; align-items:center; gap:8px;}
.modal-body { padding: 24px; overflow-y: auto !important; flex: 1 1 auto; min-height: 0; }
.modal-footer {
    padding: 16px 24px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 12px; background: #fbfcfc; flex-shrink: 0;
}
</style>

<div class="height-100">

    <!-- Ticket Stats Grid -->
    <div class="ticket-stats-grid">
        <div class="tstat-card">
            <div class="tstat-icon" style="background:#fffbeb; color:#d97706;"><i class="fas fa-ticket-alt"></i></div>
            <div>
                <div style="font-size:22px; font-weight:800; color:#d97706;"><?= $cnt_open ?></div>
                <div style="font-size:12.5px; font-weight:600; color:#64748b;">Pending Dispute Tickets</div>
            </div>
        </div>

        <div class="tstat-card">
            <div class="tstat-icon" style="background:#dcfce7; color:#15803d;"><i class="fas fa-check-double"></i></div>
            <div>
                <div style="font-size:22px; font-weight:800; color:#15803d;"><?= $cnt_waived ?></div>
                <div style="font-size:12.5px; font-weight:600; color:#64748b;">Approved & Waived</div>
            </div>
        </div>

        <div class="tstat-card">
            <div class="tstat-icon" style="background:#fee2e2; color:#dc2626;"><i class="fas fa-times-circle"></i></div>
            <div>
                <div style="font-size:22px; font-weight:800; color:#dc2626;"><?= $cnt_rejected ?></div>
                <div style="font-size:12.5px; font-weight:600; color:#64748b;">Rejected Disputes</div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="ticket-card">
        <div class="ticket-header">
            <div>
                <h2><i class="fas fa-ticket-alt"></i> <?= $is_hr_admin ? 'Deduction Dispute Tickets Management' : 'My Deduction Dispute Tickets' ?></h2>
                <span style="font-size:13.5px; color:#64748b;">
                    <?= $is_hr_admin ? 'Review employee disputes against late penalties and attendance deductions.' : 'Track the status of your submitted dispute tickets.' ?>
                </span>
            </div>

            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <form method="GET" style="display:flex; gap:8px; align-items:center; margin:0;">
                    <select name="status" onchange="this.form.submit()" style="padding:8px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:13.5px; outline:none; background:#f8fafc;">
                        <option value="">All Statuses</option>
                        <option value="open" <?= $filter_status === 'open' ? 'selected' : '' ?>>Open / Pending</option>
                        <option value="approved_waived" <?= $filter_status === 'approved_waived' ? 'selected' : '' ?>>Approved & Waived</option>
                        <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </form>

                <?php if ($is_hr_admin): ?>
                <a href="salaries.php" class="btn btn-secondary-custom" style="padding:8px 16px; border-radius:8px; font-size:13px; font-weight:600;">
                    <i class="fas fa-arrow-left"></i> Back to Payroll
                </a>
                <?php else: ?>
                <a href="my_salary.php" class="btn btn-secondary-custom" style="padding:8px 16px; border-radius:8px; font-size:13px; font-weight:600;">
                    <i class="fas fa-receipt"></i> View My Salary & Deductions
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table class="ticket-table">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <?php if ($is_hr_admin): ?>
                        <th>Employee</th>
                        <?php endif; ?>
                        <th>Deduction Details</th>
                        <th>Amount</th>
                        <th>Employee's Dispute Reason</th>
                        <th>Proof</th>
                        <th>Status</th>
                        <th>Resolution Notes</th>
                        <?php if ($is_hr_admin): ?>
                        <th>Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="<?= $is_hr_admin ? 9 : 7 ?>" style="text-align:center; padding:50px 20px; color:#94a3b8;">
                            <i class="fas fa-inbox" style="font-size:2.5rem; margin-bottom:10px; display:block;"></i>
                            <strong>No dispute tickets found.</strong>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td>
                            <strong style="color:var(--primary, #024442); font-family:monospace;"><?= htmlspecialchars($t['ticket_number']) ?></strong>
                            <small style="display:block; color:#94a3b8; font-size:11px;"><?= date('d M Y, h:i A', strtotime($t['created_at'])) ?></small>
                        </td>

                        <?php if ($is_hr_admin): ?>
                        <td>
                            <strong style="color:#0f172a; font-size:13.5px;"><?= htmlspecialchars($t['user_name']) ?></strong>
                            <small style="display:block; color:#64748b; font-size:11.5px;"><?= htmlspecialchars($t['department'] ?: 'General') ?></small>
                        </td>
                        <?php endif; ?>

                        <td>
                            <strong style="color:#334155; font-size:13px;"><?= htmlspecialchars($t['deduction_type']) ?></strong>
                            <small style="display:block; color:#64748b; font-size:11.5px;"><?= date('d M Y', strtotime($t['date_incurred'])) ?> · <?= htmlspecialchars($t['deduction_desc']) ?></small>
                        </td>

                        <td style="font-weight:800; color:#dc2626; white-space:nowrap;">
                            -PKR <?= number_format($t['amount']) ?>
                        </td>

                        <td style="max-width:250px;">
                            <div style="font-weight:600; color:#0f172a; font-size:12.5px; margin-bottom:2px;"><?= htmlspecialchars($t['subject']) ?></div>
                            <div style="color:#475569; font-size:12px; line-height:1.3;"><?= nl2br(htmlspecialchars($t['reason'])) ?></div>
                        </td>

                        <td>
                            <?php if (!empty($t['attachment'])): ?>
                                <a href="<?= htmlspecialchars($t['attachment']) ?>" target="_blank" style="display:inline-flex; align-items:center; gap:4px; background:#eff6ff; color:#2563eb; padding:4px 8px; border-radius:6px; font-size:11.5px; font-weight:700; text-decoration:none; border:1px solid #bfdbfe;">
                                    <i class="fas fa-paperclip"></i> View Proof
                                </a>
                            <?php else: ?>
                                <span style="color:#cbd5e1; font-size:12px;">None</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="badge-status <?= $t['status'] ?>"><?= str_replace('_', ' ', $t['status']) ?></span>
                        </td>

                        <td style="font-size:12px; color:#64748b; max-width:180px;">
                            <?php if (!empty($t['admin_remarks'])): ?>
                                <div><?= htmlspecialchars($t['admin_remarks']) ?></div>
                                <small style="color:#94a3b8;">By: <?= htmlspecialchars($t['reviewer_name'] ?: 'Admin') ?></small>
                            <?php else: ?>
                                <span style="color:#cbd5e1;">—</span>
                            <?php endif; ?>
                        </td>

                        <?php if ($is_hr_admin): ?>
                        <td>
                            <?php if ($t['status'] === 'open' || $t['status'] === 'under_review'): ?>
                                <button type="button" class="btn btn-sm btn-primary-custom" onclick="openReviewModal(<?= htmlspecialchars(json_encode($t)) ?>)" style="padding:5px 12px; font-size:12px; border-radius:6px; font-weight:600; white-space:nowrap;">
                                    <i class="fas fa-gavel"></i> Resolve
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm" onclick="openReviewModal(<?= htmlspecialchars(json_encode($t)) ?>)" style="padding:4px 8px; font-size:11.5px; border-radius:6px; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;">
                                    Revisit
                                </button>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Review Ticket Modal (HR/Admin only) -->
<?php if ($is_hr_admin): ?>
<div id="reviewTicketModal" class="modal-overlay" onclick="if(event.target===this)closeModal('reviewTicketModal')">
    <div class="modal-content">
        <div class="modal-header">
            <h4><i class="fas fa-gavel"></i> Review Deduction Dispute</h4>
            <button type="button" onclick="closeModal('reviewTicketModal')" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;">&times;</button>
        </div>
        <form id="reviewTicketForm" onsubmit="submitTicketReview(event)">
            <input type="hidden" name="action" value="review_ticket">
            <input type="hidden" name="ticket_id" id="rev_ticket_id">
            
            <div class="modal-body">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; margin-bottom:18px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <strong id="rev_emp_name" style="color:var(--primary, #024442); font-size:14px;"></strong>
                        <span id="rev_ticket_num" style="font-family:monospace; font-weight:700; color:#64748b;"></span>
                    </div>
                    <div style="font-size:13px; color:#475569;">
                        Deduction: <strong id="rev_ded_type" style="color:#0f172a;"></strong> (<span id="rev_ded_amount" style="color:#dc2626; font-weight:700;"></span>)
                    </div>
                    <div style="margin-top:8px; padding-top:8px; border-top:1px dashed #cbd5e1; font-size:12.5px; color:#334155;">
                        <strong>Employee's Reason:</strong>
                        <p id="rev_reason" style="margin:4px 0 0; font-style:italic; color:#475569;"></p>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:16px;">
                    <label style="font-weight:700; color:#334155; margin-bottom:6px; display:block;">Action Decision <span style="color:#ef4444;">*</span></label>
                    <select name="decision" id="rev_decision" required style="width:100%; padding:10px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:14px; outline:none;">
                        <option value="approved_waived">✅ Approve & Waive Deduction (Refund Penalty)</option>
                        <option value="rejected">❌ Reject Dispute (Keep Deduction Applied)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:16px;">
                    <label style="font-weight:700; color:#334155; margin-bottom:6px; display:block;">HR / Admin Remarks</label>
                    <textarea name="remarks" id="rev_remarks" rows="3" placeholder="Enter reason for approval or rejection..." style="width:100%; padding:10px; border:1.5px solid #e2e8f0; border-radius:8px; font-family:inherit; font-size:13.5px; outline:none;"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeModal('reviewTicketModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="submitReviewBtn"><i class="fas fa-check-circle"></i> Save Decision</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div id="toastContainer" style="position:fixed; bottom:24px; right:24px; z-index:9999; display:flex; flex-direction:column; gap:8px;"></div>

<script>
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
    document.body.style.overflow = '';
}

function openReviewModal(t) {
    document.getElementById('rev_ticket_id').value = t.id;
    document.getElementById('rev_emp_name').textContent = t.user_name;
    document.getElementById('rev_ticket_num').textContent = t.ticket_number;
    document.getElementById('rev_ded_type').textContent = t.deduction_type + ' on ' + t.date_incurred;
    document.getElementById('rev_ded_amount').textContent = '-PKR ' + Number(t.amount).toLocaleString();
    document.getElementById('rev_reason').textContent = t.reason;
    document.getElementById('rev_remarks').value = t.admin_remarks || '';
    if (t.status === 'rejected') {
        document.getElementById('rev_decision').value = 'rejected';
    } else {
        document.getElementById('rev_decision').value = 'approved_waived';
    }

    openModal('reviewTicketModal');
}

function submitTicketReview(e) {
    e.preventDefault();
    const btn = document.getElementById('submitReviewBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const formData = new FormData(document.getElementById('reviewTicketForm'));

    fetch('attendance_api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Save Decision';
        if (data.success) {
            closeModal('reviewTicketModal');
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 1200);
        } else {
            showToast(data.message || 'Failed to update ticket.', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Save Decision';
        showToast('Network error processing review.', 'error');
    });
}

function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.style.cssText = `background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:240px;border-left:4px solid ${type==='success'?'#10b981':'#ef4444'};`;
    t.innerHTML = `<span style="font-weight:700;color:${type==='success'?'#10b981':'#ef4444'}">${type==='success'?'<i class="fas fa-check"></i>':'<i class="fas fa-times"></i>'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3500);
}
</script>

<?php include('dashboard_footer.php'); ?>
