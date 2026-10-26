<?php
/**
 * admin/my_salary.php
 * Employee Dashboard: My Salary, Itemized Deductions & Dispute Ticket Portal
 * Allows employees to view their detailed salary slip, examine each deduction reason, and dispute penalties directly.
 */

require_once __DIR__ . '/dashboard_header.php';
require_once __DIR__ . '/../core/attendance_schema.php';
require_once __DIR__ . '/../core/attendance_helper.php';

ensure_attendance_schema($conn);

$filter_month = $_GET['month'] ?? date('Y-m');

// Ensure user's salary is synced for this month
syncUserMonthlySalary($conn, $user_id, $filter_month);

// Fetch user's salary row
$stmt_sal = $conn->prepare("SELECT * FROM salaries WHERE user_id = ? AND salary_month = ? LIMIT 1");
$stmt_sal->bind_param("is", $user_id, $filter_month);
$stmt_sal->execute();
$my_salary = $stmt_sal->get_result()->fetch_assoc();
$stmt_sal->close();

// Fallback if not found
if (!$my_salary) {
    $meta = getUserAttendanceMeta($conn, $user_id);
    $my_salary = [
        'base_salary'      => $meta['base_salary'],
        'allowances'       => $meta['allowances'],
        'gross_pay'        => $meta['base_salary'] + $meta['allowances'],
        'tax'              => 0,
        'advance'          => 0,
        'deductions'       => 0,
        'previous_pending' => 0,
        'net_salary'       => $meta['base_salary'] + $meta['allowances'],
        'payment_status'   => 'Pending',
        'employee_id'      => $meta['employee_id'],
        'department'       => $meta['department'],
        'job_title'        => $meta['job_title']
    ];
}

// Fetch itemized deductions for this user and month
$stmt_ded = $conn->prepare("
    SELECT d.*, t.id as ticket_id, t.ticket_number, t.status as ticket_status, t.admin_remarks
    FROM salary_deductions d
    LEFT JOIN deduction_tickets t ON d.id = t.deduction_id
    WHERE d.user_id = ? AND d.salary_month = ?
    ORDER BY d.date_incurred DESC
");
$stmt_ded->bind_param("is", $user_id, $filter_month);
$stmt_ded->execute();
$deductions = $stmt_ded->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_ded->close();
?>

<style>
.mysalary-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 10px 25px -3px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    border: 1px solid #f0f3f2;
    margin-bottom: 24px;
}
.mysalary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    padding: 24px 28px;
    border-bottom: 1px solid #f4f5f4;
}
.mysalary-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary, #024442);
    display: flex;
    align-items: center;
    gap: 10px;
}
.salary-slip-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 18px;
    padding: 24px 28px;
    background: #fbfdfc;
    border-bottom: 1px solid #f1f5f9;
}
.slip-box {
    background: #fff;
    border-radius: 12px;
    padding: 16px 20px;
    border: 1px solid #e2e8f0;
}
.slip-lbl {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.slip-val {
    font-size: 19px;
    font-weight: 800;
    color: #0f172a;
}
.slip-val.net {
    color: #16a34a;
    font-size: 24px;
}
.slip-val.ded {
    color: #dc2626;
}

.deduction-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
}
.deduction-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 14px 20px;
    border-bottom: 2px solid #e2e8f0;
}
.deduction-table td {
    padding: 14px 20px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    vertical-align: middle;
}
.deduction-table tr:hover td { background: #fbfdfc; }

/* Modal */
.modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px);
    z-index: 9999; display: none; align-items: center; justify-content: center; padding: 20px;
}
.modal-overlay.active { display: flex; }
.modal-content {
    background: #fff; border-radius: 16px; width: 100%; max-width: 540px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); overflow: hidden;
}
.modal-header {
    background: var(--primary, #024442); color: #fff; padding: 18px 24px;
    display: flex; justify-content: space-between; align-items: center;
}
.modal-header h4 { margin: 0; font-size: 1.15rem; font-weight: 700; display:flex; align-items:center; gap:8px;}
.modal-body { padding: 24px; }
.modal-footer {
    padding: 16px 24px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 12px; background: #fbfcfc;
}
</style>

<div class="height-100">

    <div class="mysalary-card">
        <div class="mysalary-header">
            <div>
                <h2><i class="fas fa-file-invoice-dollar"></i> My Salary Slip & Deductions</h2>
                <span style="font-size:13.5px; color:#64748b;">
                    Month: <strong><?= date('F Y', strtotime($filter_month . '-01')) ?></strong> · Employee ID: <strong><?= htmlspecialchars($my_salary['employee_id'] ?: 'EMP-' . str_pad($user_id, 3, '0', STR_PAD_LEFT)) ?></strong>
                </span>
            </div>

            <div style="display:flex; align-items:center; gap:10px;">
                <form method="GET" style="display:flex; gap:8px; align-items:center; margin:0;">
                    <input type="month" name="month" value="<?= htmlspecialchars($filter_month) ?>" onchange="this.form.submit()" style="padding:8px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:13.5px; outline:none; background:#f8fafc;">
                </form>
                <button type="button" class="btn btn-secondary-custom" onclick="window.print()" style="padding:8px 16px; border-radius:8px; font-size:13px; font-weight:600;">
                    <i class="fas fa-print"></i> Print Slip
                </button>
            </div>
        </div>

        <!-- Slip summary grid -->
        <div class="salary-slip-grid">
            <div class="slip-box">
                <div class="slip-lbl">Base Salary</div>
                <div class="slip-val">PKR <?= number_format($my_salary['base_salary']) ?></div>
            </div>

            <div class="slip-box">
                <div class="slip-lbl">Allowances</div>
                <div class="slip-val">PKR <?= number_format($my_salary['allowances']) ?></div>
            </div>

            <div class="slip-box">
                <div class="slip-lbl">Gross Earnings</div>
                <div class="slip-val" style="color:#0284c7;">PKR <?= number_format($my_salary['gross_pay']) ?></div>
            </div>

            <div class="slip-box" style="border-color:#fecaca; background:#fffafa;">
                <div class="slip-lbl" style="color:#dc2626;">Total Deductions</div>
                <div class="slip-val ded">-PKR <?= number_format($my_salary['deductions']) ?></div>
            </div>

            <div class="slip-box" style="border-color:#bbf7d0; background:#f0fdf4;">
                <div class="slip-lbl" style="color:#15803d;">Take Home Net Salary</div>
                <div class="slip-val net">PKR <?= number_format($my_salary['net_salary']) ?></div>
            </div>

            <div class="slip-box">
                <div class="slip-lbl">Payment Status</div>
                <div style="margin-top:4px;">
                    <?php if (($my_salary['payment_status'] ?? '') === 'Paid'): ?>
                        <span style="display:inline-block; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:800; text-transform:uppercase; background:#dcfce7; color:#15803d;">
                            Paid
                        </span>
                    <?php elseif (($my_salary['payment_status'] ?? '') === 'Partially Paid'): ?>
                        <span style="display:inline-block; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:800; text-transform:uppercase; background:#fff7ed; color:#c2410c; border:1px solid #fed7aa;">
                            Partially Paid
                        </span>
                        <div style="font-size:11px; font-weight:700; color:#c2410c; margin-top:3px;">
                            Paid: PKR <?= number_format($my_salary['amount_paid'] ?? 0) ?> · Due: PKR <?= number_format(max(0, ($my_salary['net_salary'] ?? 0) - ($my_salary['amount_paid'] ?? 0))) ?>
                        </div>
                    <?php else: ?>
                        <span style="display:inline-block; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:800; text-transform:uppercase; background:#fef3c7; color:#b45309;">
                            <?= htmlspecialchars($my_salary['payment_status'] ?? 'Pending') ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Detailed Itemized Deductions Table -->
        <div style="padding: 24px 28px 12px;">
            <h3 style="margin:0 0 4px; font-size:1.15rem; font-weight:800; color:var(--primary, #024442);">
                <i class="fas fa-receipt"></i> Itemized Deductions & Attendance Penalties
            </h3>
            <p style="margin:0; font-size:13px; color:#64748b;">
                Every deduction incurred during this month is listed below. If you believe any penalty was applied incorrectly (e.g. traffic delay, client offsite meeting, medical emergency), you can raise a dispute ticket directly.
            </p>
        </div>

        <div style="overflow-x:auto;">
            <table class="deduction-table">
                <thead>
                    <tr>
                        <th>Date Incurred</th>
                        <th>Deduction Category</th>
                        <th>Explanation & System Notes</th>
                        <th style="text-align:right;">Penalty Amount</th>
                        <th style="text-align:center;">Deduction Status</th>
                        <th style="text-align:center;">Action / Dispute</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deductions)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:50px 20px; color:#16a34a;">
                            <i class="fas fa-smile-beam" style="font-size:2.5rem; margin-bottom:10px; display:block;"></i>
                            <strong>No deductions applied for this month! Great job maintaining on-time attendance.</strong>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($deductions as $d): ?>
                    <tr>
                        <td style="font-weight:700; color:#334155; white-space:nowrap;">
                            <i class="far fa-calendar-alt"></i> <?= date('d M Y', strtotime($d['date_incurred'])) ?>
                        </td>

                        <td>
                            <strong style="color:#0f172a; font-size:13.5px;"><?= htmlspecialchars($d['deduction_type']) ?></strong>
                        </td>

                        <td>
                            <div style="color:#475569; font-size:12.5px;"><?= htmlspecialchars($d['description']) ?></div>
                            <?php if (!empty($d['admin_remarks'])): ?>
                                <small style="display:block; color:#0284c7; margin-top:3px;">
                                    <i class="fas fa-info-circle"></i> HR Note: <?= htmlspecialchars($d['admin_remarks']) ?>
                                </small>
                            <?php endif; ?>
                        </td>

                        <td style="text-align:right; font-weight:800; color:#dc2626; white-space:nowrap;">
                            -PKR <?= number_format($d['amount']) ?>
                        </td>

                        <td style="text-align:center;">
                            <?php if ($d['status'] === 'applied'): ?>
                                <span style="background:#fee2e2; color:#dc2626; border:1px solid #fecaca; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:800; text-transform:uppercase;">Applied</span>
                            <?php elseif ($d['status'] === 'disputed'): ?>
                                <span style="background:#fef3c7; color:#b45309; border:1px solid #fde68a; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:800; text-transform:uppercase;">Dispute Open</span>
                            <?php elseif ($d['status'] === 'waived'): ?>
                                <span style="background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:800; text-transform:uppercase;">Waived / Refunded</span>
                            <?php endif; ?>
                        </td>

                        <td style="text-align:center;">
                            <?php if (!empty($d['ticket_number'])): ?>
                                <a href="deduction_tickets.php" style="display:inline-flex; align-items:center; gap:5px; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; padding:5px 12px; border-radius:6px; font-size:11.5px; font-weight:700; text-decoration:none;">
                                    <i class="fas fa-ticket-alt"></i> <?= htmlspecialchars($d['ticket_number']) ?>
                                </a>
                            <?php elseif ($d['status'] === 'waived'): ?>
                                <span style="color:#16a34a; font-size:12px; font-weight:700;"><i class="fas fa-check"></i> Resolved</span>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="openRaiseTicketModal(<?= htmlspecialchars(json_encode($d)) ?>)" style="padding:5px 12px; font-size:12px; border-radius:6px; font-weight:700; background:#fff; color:#dc2626; border:1.5px solid #fca5a5; cursor:pointer;">
                                    <i class="fas fa-hand-paper"></i> Raise Dispute
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Raise Dispute Ticket -->
<div id="raiseTicketModal" class="modal-overlay" onclick="if(event.target===this)closeModal('raiseTicketModal')">
    <div class="modal-content">
        <div class="modal-header">
            <h4><i class="fas fa-ticket-alt"></i> Raise Ticket Against Deduction</h4>
            <button type="button" onclick="closeModal('raiseTicketModal')" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;">&times;</button>
        </div>
        <form id="raiseTicketForm" onsubmit="submitDisputeTicket(event)" enctype="multipart/form-data">
            <input type="hidden" name="action" value="raise_ticket">
            <input type="hidden" name="deduction_id" id="tkt_deduction_id">

            <div class="modal-body">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; margin-bottom:18px;">
                    <div style="font-size:13px; color:#64748b; margin-bottom:4px;">Disputing Deduction:</div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <strong id="tkt_ded_type" style="color:#0f172a; font-size:14.5px;"></strong>
                        <span id="tkt_ded_amount" style="font-weight:800; color:#dc2626; font-size:15px;"></span>
                    </div>
                    <div id="tkt_ded_desc" style="font-size:12.5px; color:#475569; margin-top:4px;"></div>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label style="font-weight:700; color:#334155; font-size:13px; display:block; margin-bottom:5px;">Subject / Short Title <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="subject" id="tkt_subject" placeholder="e.g. Delayed due to extreme weather / Client site meeting" required style="width:100%; padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:13.5px; outline:none;">
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label style="font-weight:700; color:#334155; font-size:13px; display:block; margin-bottom:5px;">Reason & Justification <span style="color:#ef4444;">*</span></label>
                    <textarea name="reason" rows="4" placeholder="Explain in detail why this penalty should be waived..." required style="width:100%; padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-family:inherit; font-size:13.5px; outline:none; resize:vertical;"></textarea>
                </div>

                <div class="form-group">
                    <label style="font-weight:700; color:#334155; font-size:13px; display:block; margin-bottom:5px;">Optional Proof / Attachment (Doctor slip, client email, photo)</label>
                    <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" style="width:100%; font-size:13px; padding:6px; border:1.5px dashed #cbd5e1; border-radius:8px; background:#f8fafc;">
                    <small style="color:#94a3b8; display:block; margin-top:3px;">Allowed formats: PDF, JPG, PNG, DOC (Max 5MB)</small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeModal('raiseTicketModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="submitTktBtn"><i class="fas fa-paper-plane"></i> Submit Dispute Ticket</button>
            </div>
        </form>
    </div>
</div>

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

function openRaiseTicketModal(d) {
    document.getElementById('tkt_deduction_id').value = d.id;
    document.getElementById('tkt_ded_type').textContent = d.deduction_type + ' (' + d.date_incurred + ')';
    document.getElementById('tkt_ded_amount').textContent = '-PKR ' + Number(d.amount).toLocaleString();
    document.getElementById('tkt_ded_desc').textContent = d.description;
    document.getElementById('tkt_subject').value = 'Dispute on ' + d.deduction_type + ' (' + d.date_incurred + ')';
    openModal('raiseTicketModal');
}

function submitDisputeTicket(e) {
    e.preventDefault();
    const btn = document.getElementById('submitTktBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

    const formData = new FormData(document.getElementById('raiseTicketForm'));

    fetch('attendance_api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Dispute Ticket';
        if (data.success) {
            closeModal('raiseTicketModal');
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 1400);
        } else {
            showToast(data.message || 'Failed to submit ticket.', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Dispute Ticket';
        showToast('Network error submitting dispute ticket.', 'error');
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
