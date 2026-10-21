<?php
ob_start();

include 'dashboard_header.php';

// ── Quick actions ─────────────────────────────────────────────────────────────
if (isset($_GET['action'], $_GET['id'])) {
    if ($role === 'manager') {
        header("Location: manage_invoices.php?error=unauthorized");
        exit;
    }
    $id = intval($_GET['id']);
    $type = $_GET['type'] ?? 'invoice';

    if ($type === 'invoice') {
        match($_GET['action']) {
            'mark_paid'   => $conn->query("UPDATE invoices SET status='paid', amount_paid=total_amount, amount_due=0, paid_at=NOW() WHERE id={$id}"),
            'approve_payment' => $conn->query("UPDATE invoices SET status='paid', amount_paid=total_amount, amount_due=0, paid_at=NOW() WHERE id={$id}"),
            'reject_payment' => $conn->query("UPDATE invoices SET status='sent', payment_screenshot=NULL, payment_uploaded_at=NULL WHERE id={$id}"),
            'mark_sent'   => $conn->query("UPDATE invoices SET status='sent' WHERE id={$id}"),
            'cancel'      => $conn->query("UPDATE invoices SET status='cancelled' WHERE id={$id}"),
            'delete'      => $conn->query("DELETE FROM invoices WHERE id={$id}"),
            default       => null,
        };
    } else {
        match($_GET['action']) {
            'delete'      => $conn->query("DELETE FROM quotations WHERE id={$id}"),
            'mark_sent'   => $conn->query("UPDATE quotations SET status='sent' WHERE id={$id}"),
            'mark_accepted'=> $conn->query("UPDATE quotations SET status='accepted' WHERE id={$id}"),
            default       => null,
        };
    }
    echo "<script>alert('Action completed successfully');</script>";
    header("Location: manage_invoices.php" . (isset($_GET['tab']) ? "?tab={$_GET['tab']}" : ""));
    exit;
}

// Auto-mark overdue
$conn->query("UPDATE invoices SET status='overdue' WHERE due_date < CURDATE() AND status='sent'");
$conn->query("UPDATE quotations SET status='expired' WHERE valid_until < CURDATE() AND status IN ('draft','sent')");

// ── Stats ─────────────────────────────────────────────────────────────────────
$invStats = $conn->query("
    SELECT
        COUNT(*)                                        AS total,
        SUM(total_amount)                               AS total_value,
        SUM(IF(status='paid',    total_amount, 0))      AS paid_value,
        SUM(IF(status='overdue', total_amount, 0))      AS overdue_value,
        SUM(IF(status='sent',    total_amount, 0))      AS pending_value,
        SUM(amount_due)                                 AS total_due,
        COUNT(IF(status='paid',    1, NULL))            AS paid_count,
        COUNT(IF(status='overdue', 1, NULL))            AS overdue_count,
        COUNT(IF(status='sent',    1, NULL))            AS sent_count,
        COUNT(IF(status='draft',   1, NULL))            AS draft_count,
        COUNT(IF(status='cancelled',1,NULL))            AS cancelled_count
    FROM invoices
")->fetch_assoc();

$quotStats = $conn->query("
    SELECT
        COUNT(*)                                           AS total,
        COUNT(IF(status='draft',    1, NULL))             AS draft_count,
        COUNT(IF(status='sent',     1, NULL))             AS sent_count,
        COUNT(IF(status='accepted', 1, NULL))             AS accepted_count,
        COUNT(IF(status='rejected', 1, NULL))             AS rejected_count,
        COUNT(IF(status='expired',  1, NULL))             AS expired_count,
        SUM(total_amount)                                  AS total_value,
        SUM(IF(status='accepted', total_amount, 0))       AS accepted_value
    FROM quotations
")->fetch_assoc();

// ── Active tab & filters ──────────────────────────────────────────────────────
$activeTab    = $_GET['tab']    ?? 'invoices';
$invFilter    = $_GET['filter'] ?? 'all';
$quotFilter   = $_GET['qfilter'] ?? 'all';

$invWhere  = $invFilter  !== 'all' ? "WHERE i.status = '{$invFilter}'"  : "";
$quotWhere = $quotFilter !== 'all' ? "WHERE q.status = '{$quotFilter}'" : "";

// ── Fetch ─────────────────────────────────────────────────────────────────────
$invoices   = $conn->query("SELECT i.*, c.client_name, c.company FROM invoices i LEFT JOIN clients c ON i.client_id=c.id {$invWhere} ORDER BY i.created_at DESC");
$quotations = $conn->query("SELECT q.* FROM quotations q {$quotWhere} ORDER BY q.created_at DESC");

// ── Helpers ───────────────────────────────────────────────────────────────────
function invBadge($s) {
    return match($s) {
        'paid'      => 'status-completed',
        'sent'      => 'status-in-progress',
        'overdue'   => 'status-on-hold',
        'cancelled' => 'status-not-started',
        default     => 'status-default',
    };
}

function quotBadge($s) {
    return match($s) {
        'accepted' => 'status-completed',
        'sent'     => 'status-in-progress',
        'rejected' => 'status-on-hold',
        'expired'  => 'status-not-started',
        default    => 'status-default',
    };
}
?>

<div class="height-100">

<div class="page-header">
    <div>
        <h1><i class="fas fa-file-invoice-dollar"></i> Invoices & Quotations</h1>
        <p>Manage billing, track payments, and send professional quotations</p>
    </div>
    <div style="display:flex;gap:10px;">
        <?php if ($role !== 'manager'): ?>
        <a href="create_quotation.php" class="btn btn-secondary"><i class="fas fa-file-alt"></i> New Quotation</a>
        <a href="create_invoice.php"   class="btn btn-primary"><i class="fas fa-plus"></i> New Invoice</a>
        <?php endif; ?>
    </div>
</div>

<!-- ── Stats ──────────────────────────────────────────────────────────────── -->
<div class="inv-stats-grid">
    <div class="inv-stat-card">
        <div class="inv-stat-icon" style="background:#fffbeb;color:#d97706;"><i class="fas fa-dollar-sign"></i></div>
        <div>
            <div class="inv-stat-num" style="color:#d97706;">PKR <?= number_format($invStats['total_due'] ?? 0) ?></div>
            <div class="inv-stat-label">Outstanding Balance</div>
            <div class="inv-stat-sub"><?= ($invStats['sent_count'] + $invStats['overdue_count']) ?> unpaid invoices</div>
        </div>
    </div>
    <div class="inv-stat-card">
        <div class="inv-stat-icon" style="background:#f0fff4;color:#22c55e;"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="inv-stat-num"><?= $invStats['paid_count'] ?></div>
            <div class="inv-stat-label">Paid Invoices</div>
            <div class="inv-stat-sub">PKR <?= number_format($invStats['paid_value'] ?? 0) ?></div>
        </div>
    </div>
    <div class="inv-stat-card">
        <div class="inv-stat-icon" style="background:#fff5f5;color:#e53e3e;"><i class="fas fa-exclamation-triangle"></i>️</div>
        <div>
            <div class="inv-stat-num" style="color:#e53e3e;"><?= $invStats['overdue_count'] ?></div>
            <div class="inv-stat-label">Overdue</div>
            <div class="inv-stat-sub">PKR <?= number_format($invStats['overdue_value'] ?? 0) ?></div>
        </div>
    </div>
    <div class="inv-stat-card">
        <div class="inv-stat-icon" style="background:#f0f9f8;color:#024442;"><i class="fas fa-file-invoice-dollar"></i></div>
        <div>
            <div class="inv-stat-num"><?= $invStats['total'] ?></div>
            <div class="inv-stat-label">Total Invoices</div>
            <div class="inv-stat-sub">PKR <?= number_format($invStats['total_value'] ?? 0) ?> total</div>
        </div>
    </div>
    <?php if ($role !== 'manager'): ?>
    <div class="inv-stat-card">
        <div class="inv-stat-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="fas fa-file-alt"></i></div>
        <div>
            <div class="inv-stat-num"><?= $quotStats['total'] ?></div>
            <div class="inv-stat-label">Quotations</div>
            <div class="inv-stat-sub"><?= $quotStats['accepted_count'] ?> accepted</div>
        </div>
    </div>
    <?php endif; ?>
    <div class="inv-stat-card">
        <div class="inv-stat-icon" style="background:#eff6ff;color:#3b82f6;"><i class="fas fa-upload"></i></div>
        <div>
            <div class="inv-stat-num"><?= $invStats['sent_count'] ?></div>
            <div class="inv-stat-label">Awaiting Payment</div>
            <div class="inv-stat-sub">PKR <?= number_format($invStats['pending_value'] ?? 0) ?></div>
        </div>
    </div>
</div>

<!-- ── Main Tabs ───────────────────────────────────────────────────────────── -->
<div class="mp-tabs-container" style="margin-bottom:0;">
    <div class="mp-tab-buttons">
        <button type="button" onclick="switchMainTab('invoices')"   id="tabBtnInvoices"   class="<?= $activeTab==='invoices'   ? 'active' : '' ?>"><span class="mp-tab-icon"><i class="fas fa-file-invoice-dollar"></i></span> Invoices <span class="tab-count"><?= $invStats['total'] ?></span></button>
        <?php if ($role !== 'manager'): ?>
        <button type="button" onclick="switchMainTab('quotations')" id="tabBtnQuotations" class="<?= $activeTab==='quotations' ? 'active' : '' ?>"><span class="mp-tab-icon"><i class="fas fa-file-alt"></i></span> Quotations <span class="tab-count"><?= $quotStats['total'] ?></span></button>
        <?php endif; ?>
    </div>
</div>

<!-- ════════════════════════════ INVOICES TAB ════════════════════════════════ -->
<div id="tabInvoices" class="main-tab-panel" style="display:<?= $activeTab==='invoices' ? 'block' : 'none' ?>;">

    <!-- Filter bar -->
    <div class="inv-filter-bar">
        <?php foreach (['all'=>'All', 'draft'=>'<i class="fas fa-edit"></i> Draft', 'sent'=>'<i class="fas fa-upload"></i> Sent', 'paid'=>'<i class="fas fa-check-circle"></i> Paid', 'overdue'=>'<i class="fas fa-exclamation-triangle"></i>️ Overdue', 'cancelled'=>'<i class="fas fa-times-circle"></i> Cancelled'] as $k => $label): ?>
        <a href="?tab=invoices&filter=<?= $k ?>"
           class="inv-filter-btn <?= $invFilter===$k ? 'active' : '' ?>">
            <?= $label ?>
            <?php
            $cnt = match($k) {
                'all'       => $invStats['total'],
                'paid'      => $invStats['paid_count'],
                'sent'      => $invStats['sent_count'],
                'overdue'   => $invStats['overdue_count'],
                'draft'     => $invStats['draft_count'],
                'cancelled' => $invStats['cancelled_count'],
                default     => 0,
            };
            ?>
            <span class="filter-pill"><?= $cnt ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Invoices table -->
    <div class="card" style="padding:0;overflow:hidden;">
        <?php if ($invoices && $invoices->num_rows > 0): ?>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Client</th>
                    <th>Issue Date</th>
                    <th>Due Date</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Outstanding</th>
                    <th>Status</th>
                    <th>Payment Proof</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($inv = $invoices->fetch_assoc()): ?>
                <tr>
                    <td>
                        <?php if ($role !== 'manager'): ?>
                        <a href="invoice_details.php?id=<?= $inv['id'] ?>" class="inv-number-link">
                            <?= htmlspecialchars($inv['invoice_number']) ?>
                        </a>
                        <?php else: ?>
                        <?= htmlspecialchars($inv['invoice_number']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="client-cell-name"><?= htmlspecialchars($inv['client_name'] ?? '—') ?></div>
                        <?php if (!empty($inv['company'])): ?><div class="client-cell-co"><?= htmlspecialchars($inv['company']) ?></div><?php endif; ?>
                    </td>
                    <td><?= date('M d, Y', strtotime($inv['issue_date'])) ?></td>
                    <td>
                        <?= date('M d, Y', strtotime($inv['due_date'])) ?>
                        <?php if ($inv['status'] === 'overdue'): ?>
                            <div class="overdue-days"><?= ceil((time()-strtotime($inv['due_date']))/86400) ?>d late</div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= $inv['currency'] ?> <?= number_format($inv['total_amount'],0) ?></strong></td>
                    <td style="color:#22c55e;"><?= $inv['currency'] ?> <?= number_format($inv['amount_paid'],0) ?></td>
                    <td style="color:<?= $inv['amount_due']>0 ? '#e53e3e' : '#22c55e' ?>;">
                        <?= $inv['currency'] ?> <?= number_format($inv['amount_due'],0) ?>
                    </td>
                    <td><span class="badge <?= invBadge($inv['status']) ?>"><?= ucfirst($inv['status']) ?></span></td>
                    <td>
                        <?php if ($inv['payment_screenshot']): ?>
                            <a href="javascript:void(0)" onclick="showScreenshot('<?= htmlspecialchars($inv['payment_screenshot']) ?>')" style="color: #024442; font-weight: 700; text-decoration: underline;" title="Uploaded: <?= date('M d, H:i', strtotime($inv['payment_uploaded_at'])) ?>">
                                <i class="fas fa-image"></i> View Screenshot
                            </a>
                        <?php else: ?>
                            <span style="color:#aaa; font-style:italic; font-size:12px;">No proof</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="row-actions">
                            <?php if ($role !== 'manager'): ?>
                            <a href="invoice_details.php?id=<?= $inv['id'] ?>" class="action-btn" title="View"><i class="fas fa-eye"></i>️</a>
                            <a href="edit_invoice.php?id=<?= $inv['id'] ?>"    class="action-btn" title="Edit"><i class="fas fa-pencil-alt"></i>️</a>
                            <a href="generate_invoice_pdf.php?id=<?= $inv['id'] ?>" class="action-btn" title="PDF" target="_blank"><i class="fas fa-inbox"></i></a>
                            <?php if ($inv['payment_screenshot'] && $inv['status'] !== 'paid'): ?>
                                <a href="?action=approve_payment&id=<?= $inv['id'] ?>&type=invoice&tab=invoices&filter=<?= $invFilter ?>"
                                   class="action-btn" style="color:#22c55e;" title="Approve Payment"
                                   onclick="return confirm('Approve this payment proof?')"><i class="fas fa-thumbs-up"></i></a>
                                <a href="?action=reject_payment&id=<?= $inv['id'] ?>&type=invoice&tab=invoices&filter=<?= $invFilter ?>"
                                   class="action-btn action-danger" style="color:#ef4444;" title="Reject Payment"
                                   onclick="return confirm('Reject this payment proof?')"><i class="fas fa-thumbs-down"></i></a>
                            <?php endif; ?>
                            <?php if (!in_array($inv['status'],['paid','cancelled'])): ?>
                            <a href="?action=mark_paid&id=<?= $inv['id'] ?>&type=invoice&tab=invoices&filter=<?= $invFilter ?>"
                               class="action-btn" title="Mark Paid"
                               onclick="return confirm('Mark as paid?')"><i class="fas fa-check-circle"></i></a>
                            <?php endif; ?>
                            <a href="?action=delete&id=<?= $inv['id'] ?>&type=invoice&tab=invoices&filter=<?= $invFilter ?>"
                               class="action-btn action-danger" title="Delete"
                               onclick="return confirm('Delete invoice <?= htmlspecialchars($inv['invoice_number']) ?>?')"><i class="fas fa-trash"></i>️</a>
                            <?php else: ?>
                            <span style="color:#aaa; font-size:12px; font-style:italic;">No Actions</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <p>No invoices found<?= $invFilter!=='all' ? " with status: <strong>{$invFilter}</strong>" : '' ?>.</p>
            <a href="create_invoice.php" class="btn btn-primary" style="margin-top:14px;"><i class="fas fa-plus"></i> Create First Invoice</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ════════════════════════════ QUOTATIONS TAB ══════════════════════════════ -->
<?php if ($role !== 'manager'): ?>
<div id="tabQuotations" class="main-tab-panel" style="display:<?= $activeTab==='quotations' ? 'block' : 'none' ?>;">

    <!-- Filter bar -->
    <div class="inv-filter-bar">
        <?php foreach (['all'=>'All', 'draft'=>'<i class="fas fa-edit"></i> Draft', 'sent'=>'<i class="fas fa-upload"></i> Sent', 'accepted'=>'<i class="fas fa-check-circle"></i> Accepted', 'rejected'=>'<i class="fas fa-times-circle"></i> Rejected', 'expired'=>'⏰ Expired'] as $k => $label): ?>
        <a href="?tab=quotations&qfilter=<?= $k ?>"
           class="inv-filter-btn <?= $quotFilter===$k ? 'active' : '' ?>">
            <?= $label ?>
            <?php
            $cnt = match($k) {
                'all'      => $quotStats['total'],
                'draft'    => $quotStats['draft_count'],
                'sent'     => $quotStats['sent_count'],
                'accepted' => $quotStats['accepted_count'],
                'rejected' => $quotStats['rejected_count'],
                'expired'  => $quotStats['expired_count'],
                default    => 0,
            };
            ?>
            <span class="filter-pill"><?= $cnt ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Quotations table -->
    <div class="card" style="padding:0;overflow:hidden;">
        <?php if ($quotations && $quotations->num_rows > 0): ?>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Quotation #</th>
                    <th>Client</th>
                    <th>Issue Date</th>
                    <th>Valid Until</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Converted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($q = $quotations->fetch_assoc()): ?>
                <tr>
                    <td>
                        <a href="quotation_details.php?id=<?= $q['id'] ?>" class="inv-number-link">
                            <?= htmlspecialchars($q['quotation_number']) ?>
                        </a>
                    </td>
                    <td>
                        <div class="client-cell-name"><?= htmlspecialchars($q['client_name']) ?></div>
                        <?php if (!empty($q['client_company'])): ?><div class="client-cell-co"><?= htmlspecialchars($q['client_company']) ?></div><?php endif; ?>
                    </td>
                    <td><?= date('M d, Y', strtotime($q['issue_date'])) ?></td>
                    <td>
                        <?= date('M d, Y', strtotime($q['valid_until'])) ?>
                        <?php if ($q['status'] === 'expired'): ?>
                            <div class="overdue-days">Expired</div>
                        <?php elseif (strtotime($q['valid_until']) > time()): ?>
                            <div style="font-size:11px;color:#22c55e;"><?= ceil((strtotime($q['valid_until'])-time())/86400) ?>d left</div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= $q['currency'] ?> <?= number_format($q['total_amount'],0) ?></strong></td>
                    <td><span class="badge <?= quotBadge($q['status']) ?>"><?= ucfirst($q['status']) ?></span></td>
                    <td>
                        <?php if ($q['converted_invoice_id']): ?>
                            <a href="invoice_details.php?id=<?= $q['converted_invoice_id'] ?>" style="font-size:12px;color:#024442;"><i class="fas fa-file-invoice-dollar"></i> Invoice</a>
                        <?php else: ?>
                            <span style="font-size:12px;color:#ccc;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="row-actions">
                            <a href="quotation_details.php?id=<?= $q['id'] ?>"         class="action-btn" title="View"><i class="fas fa-eye"></i>️</a>
                            <a href="edit_quotation.php?id=<?= $q['id'] ?>"            class="action-btn" title="Edit"><i class="fas fa-pencil-alt"></i>️</a>
                            <a href="generate_quotation_pdf.php?id=<?= $q['id'] ?>"   class="action-btn" title="PDF" target="_blank"><i class="fas fa-inbox"></i></a>
                            <?php if (!$q['converted_invoice_id']): ?>
                            <a href="create_invoice.php?from_quot=<?= $q['id'] ?>"    class="action-btn" title="Convert to Invoice"><i class="fas fa-file-invoice-dollar"></i></a>
                            <?php endif; ?>
                            <a href="?action=delete&id=<?= $q['id'] ?>&type=quotation&tab=quotations&qfilter=<?= $quotFilter ?>"
                               class="action-btn action-danger" title="Delete"
                               onclick="return confirm('Delete quotation <?= htmlspecialchars($q['quotation_number']) ?>?')"><i class="fas fa-trash"></i>️</a>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-file-alt"></i></div>
            <p>No quotations found<?= $quotFilter!=='all' ? " with status: <strong>{$quotFilter}</strong>" : '' ?>.</p>
            <a href="create_quotation.php" class="btn btn-primary" style="margin-top:14px;"><i class="fas fa-file-alt"></i> Create First Quotation</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

</div><!-- /.height-100 -->

<style>
.page-header { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:14px; margin-bottom:22px; }
.page-header h1 { margin:0 0 4px; }
.page-header p  { margin:0; color:#888; font-size:14px; }

/* Stats */
.inv-stats-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:14px; margin-bottom:22px; }
@media(max-width:1100px){ .inv-stats-grid { grid-template-columns:repeat(3,1fr); } }
@media(max-width:600px) { .inv-stats-grid { grid-template-columns:repeat(2,1fr); } }

.inv-stat-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px 14px; display:flex; align-items:center; gap:12px; }
.inv-stat-icon { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:19px; flex-shrink:0; }
.inv-stat-num  { font-size:19px; font-weight:800; color:#111; line-height:1; }
.inv-stat-label{ font-size:12px; color:#888; margin-top:3px; }
.inv-stat-sub  { font-size:11px; color:#aaa; margin-top:2px; }

/* Main tab buttons */
.tab-count { display:inline-flex; align-items:center; justify-content:center; min-width:20px; height:18px; padding:0 5px; background:rgba(0,0,0,.08); border-radius:100px; font-size:11px; font-weight:700; margin-left:5px; }
.mp-tab-buttons button.active .tab-count { background:rgba(255,255,255,.25); }

/* Filter bar */
.inv-filter-bar { display:flex; gap:8px; flex-wrap:wrap; padding:14px 0 12px; }
.inv-filter-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:100px; border:1.5px solid #e0e0e0; background:transparent; font-size:13px; font-weight:500; color:#555; text-decoration:none; transition:all .2s; white-space:nowrap; }
.inv-filter-btn:hover, .inv-filter-btn.active { background:#024442; border-color:#024442; color:#fff; }
.filter-pill { background:rgba(0,0,0,.08); padding:1px 6px; border-radius:100px; font-size:11px; font-weight:700; }
.inv-filter-btn.active .filter-pill { background:rgba(255,255,255,.25); }

/* Data table */
.data-table { width:100%; border-collapse:collapse; font-size:14px; }
.data-table th { background:#f9fafb; padding:11px 16px; text-align:left; font-size:11.5px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.06em; border-bottom:1px solid #ebebeb; white-space:nowrap; }
.data-table td { padding:12px 16px; border-bottom:1px solid #f5f5f5; color:#444; vertical-align:middle; }
.data-table tr:last-child td { border-bottom:none; }
.data-table tr:hover td { background:#fafafa; }

.inv-number-link { font-weight:700; color:#024442; text-decoration:none; font-family:'Courier New',monospace; font-size:12.5px; }
.inv-number-link:hover { text-decoration:underline; }

.client-cell-name { font-weight:600; color:#111; font-size:13.5px; }
.client-cell-co   { font-size:11.5px; color:#aaa; margin-top:2px; }
.overdue-days     { font-size:11px; color:#e53e3e; margin-top:2px; }

/* Row actions */
.row-actions { display:flex; gap:4px; }
.action-btn { width:28px; height:28px; border-radius:6px; border:1px solid #e5e7eb; background:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; text-decoration:none; transition:all .15s; }
.action-btn:hover { background:#f0f5f4; border-color:#024442; }
.action-danger:hover { background:#fff5f5; border-color:#e53e3e; }
</style>

<script>
function switchMainTab(tab) {
    document.querySelectorAll('.main-tab-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.mp-tab-buttons button').forEach(b => b.classList.remove('active'));
    document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1)).style.display = 'block';
    document.getElementById('tabBtn' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('active');
}

function showScreenshot(src) {
    document.getElementById('screenshotImg').style.display = 'none';
    document.getElementById('screenshotSpinner').style.display = 'flex';
    document.getElementById('screenshotImg').src = src;
    const modal = document.getElementById('screenshotModal');
    modal.style.display = 'flex';
}
function closeScreenshotModal() {
    document.getElementById('screenshotModal').style.display = 'none';
}
</script>

<!-- Screenshot Preview Modal -->
<div id="screenshotModal" class="modal-overlay" onclick="if(event.target===this)closeScreenshotModal()" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; padding:16px; max-width:600px; width:90%; position:relative; box-shadow:0 4px 20px rgba(0,0,0,0.2);">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:8px; margin-bottom:12px;">
            <h4 style="margin:0; color:#1e293b;"><i class="fas fa-image"></i> Payment Proof Screenshot</h4>
            <span style="font-size:24px; font-weight:bold; cursor:pointer;" onclick="closeScreenshotModal()">&times;</span>
        </div>
        <div style="text-align:center; position:relative; min-height:150px; display:flex; align-items:center; justify-content:center;">
            <div id="screenshotSpinner" style="display:flex; align-items:center; justify-content:center; position:absolute; left:0; right:0; top:0; bottom:0;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #024442;"></i>
            </div>
            <img id="screenshotImg" src="" alt="Screenshot Proof" style="max-width:100%; max-height:450px; object-fit:contain; border-radius:6px; border:1px solid #cbd5e1; display:none;" onload="document.getElementById('screenshotSpinner').style.display='none'; this.style.display='inline-block';">
        </div>
    </div>
</div>

<?php include('dashboard_footer.php'); ?>