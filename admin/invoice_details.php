<?php
include('dashboard_header.php');

$id = intval($_GET['id'] ?? 0);
if (!$id) { echo "<script>alert('Invalid invoice.');window.location='manage_invoices.php';</script>"; exit; }

// ── Handle inline actions ─────────────────────────────────────────────────────
if (isset($_GET['action'])) {
    match($_GET['action']) {
        'mark_paid'     => $conn->query("UPDATE invoices SET status='paid', amount_paid=total_amount, amount_due=0, paid_at=NOW(), updated_at=NOW() WHERE id={$id}"),
        'mark_sent'     => $conn->query("UPDATE invoices SET status='sent', updated_at=NOW() WHERE id={$id}"),
        'mark_draft'    => $conn->query("UPDATE invoices SET status='draft', updated_at=NOW() WHERE id={$id}"),
        'cancel'        => $conn->query("UPDATE invoices SET status='cancelled', updated_at=NOW() WHERE id={$id}"),
        'delete'        => null,
        default         => null,
    };

    if ($_GET['action'] === 'delete') {
        $conn->query("DELETE FROM invoices WHERE id={$id}");
        echo "<script>alert('Invoice deleted.');window.location='manage_invoices.php';</script>";
        exit;
    }

    header("Location: invoice_details.php?id={$id}");
    exit;
}

// ── Fetch invoice + client + project ─────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT i.*,
           c.client_name, c.company, c.email AS client_email, c.phone AS client_phone, c.address AS client_address,
           p.project_name
    FROM invoices i
    LEFT JOIN clients  c ON i.client_id  = c.id
    LEFT JOIN projects p ON i.project_id = p.id
    WHERE i.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();

if (!$inv) { echo "<script>alert('Invoice not found.');window.location='manage_invoices.php';</script>"; exit; }

// ── Fetch line items ──────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC");
$stmt->bind_param("i", $id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Check if converted from a quotation ──────────────────────────────────────
$quotRow = $conn->query("SELECT id, quotation_number FROM quotations WHERE converted_invoice_id = {$id} LIMIT 1")->fetch_assoc();

// ── Helpers ───────────────────────────────────────────────────────────────────
$cur = htmlspecialchars($inv['currency']);

function invStatusClass($s) {
    return match($s) {
        'paid'      => 'status-completed',
        'sent'      => 'status-in-progress',
        'overdue'   => 'status-on-hold',
        'cancelled' => 'status-not-started',
        default     => 'status-default',
    };
}

function invStatusColor($s) {
    return match($s) {
        'paid'      => '#16a34a',
        'sent'      => '#3b82f6',
        'overdue'   => '#dc2626',
        'cancelled' => '#9ca3af',
        default     => '#6b7280',
    };
}

$daysOverdue = 0;
if ($inv['status'] === 'overdue') {
    $daysOverdue = ceil((time() - strtotime($inv['due_date'])) / 86400);
}
$pct_paid = $inv['total_amount'] > 0 ? min(100, round(($inv['amount_paid'] / $inv['total_amount']) * 100)) : 0;
?>

<div class="height-100">

<!-- ── Page header ─────────────────────────────────────────────────────────── -->
<div class="page-header">
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
        <a href="manage_invoices.php" class="back-btn">← Invoices</a>
        <div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <h1 style="margin:0;"><?= htmlspecialchars($inv['invoice_number']) ?></h1>
                <span class="badge <?= invStatusClass($inv['status']) ?>" style="font-size:12px;"><?= ucfirst($inv['status']) ?></span>
                <?php if ($inv['status'] === 'overdue'): ?>
                    <span style="font-size:12px;color:#dc2626;font-weight:600;"><?= $daysOverdue ?> days overdue</span>
                <?php endif; ?>
            </div>
            <p style="margin:4px 0 0;color:#888;font-size:13.5px;">
                <?= htmlspecialchars($inv['client_name'] ?? '—') ?>
                <?= !empty($inv['company']) ? ' · ' . htmlspecialchars($inv['company']) : '' ?>
                <?= !empty($inv['project_name']) ? ' · ' . htmlspecialchars($inv['project_name']) : '' ?>
            </p>
        </div>
    </div>
    <div class="header-actions">
        <a href="generate_invoice_pdf.php?id=<?= $id ?>" target="_blank" class="btn btn-secondary">📥 View PDF</a>
        <a href="generate_invoice_pdf.php?id=<?= $id ?>&dl=1" class="btn btn-secondary">⬇️ Download PDF</a>
        <a href="edit_invoice.php?id=<?= $id ?>" class="btn btn-secondary">✏️ Edit</a>
        <?php if (!in_array($inv['status'], ['paid','cancelled'])): ?>
        <a href="invoice_details.php?id=<?= $id ?>&action=mark_paid"
           class="btn btn-primary"
           onclick="return confirm('Mark invoice as paid?')">✅ Mark as Paid</a>
        <?php endif; ?>
        <button class="btn btn-danger" onclick="deleteInvoice(<?= $id ?>, '<?= htmlspecialchars($inv['invoice_number']) ?>')">🗑️ Delete</button>
    </div>
</div>

<!-- ── Overdue alert ────────────────────────────────────────────────────────── -->
<?php if ($inv['status'] === 'overdue'): ?>
<div class="alert-overdue">
    ⚠️ <strong>This invoice is <?= $daysOverdue ?> days overdue.</strong>
    Payment of <?= $cur ?> <?= number_format($inv['amount_due'], 2) ?> was due on <?= date('M d, Y', strtotime($inv['due_date'])) ?>.
    <a href="invoice_details.php?id=<?= $id ?>&action=mark_paid" onclick="return confirm('Mark as paid?')">Mark as paid →</a>
</div>
<?php endif; ?>

<?php if ($inv['status'] === 'paid'): ?>
<div class="alert-paid">
    ✅ <strong>Payment received.</strong>
    <?= $cur ?> <?= number_format($inv['amount_paid'], 2) ?> paid on <?= !empty($inv['paid_at']) ? date('M d, Y \a\t h:i A', strtotime($inv['paid_at'])) : 'N/A' ?>.
</div>
<?php endif; ?>

<!-- ── Main grid ────────────────────────────────────────────────────────────── -->
<div class="inv-detail-grid">

    <!-- ── Left: Main content ───────────────────────────────────────────────── -->
    <div class="inv-detail-main">

        <!-- Client & Invoice meta -->
        <div class="card inv-meta-card">
            <div class="inv-meta-cols">
                <!-- Client info -->
                <div class="inv-meta-col">
                    <div class="inv-section-label">Bill To</div>
                    <div class="inv-client-name"><?= htmlspecialchars($inv['client_name'] ?? '—') ?></div>
                    <?php if (!empty($inv['company'])): ?>
                        <div class="inv-client-co"><?= htmlspecialchars($inv['company']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($inv['client_email'])): ?>
                        <div class="inv-client-detail">✉️ <?= htmlspecialchars($inv['client_email']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($inv['client_phone'])): ?>
                        <div class="inv-client-detail">📞 <?= htmlspecialchars($inv['client_phone']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($inv['client_address'])): ?>
                        <div class="inv-client-detail">📍 <?= nl2br(htmlspecialchars($inv['client_address'])) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Invoice meta -->
                <div class="inv-meta-col inv-meta-col-right">
                    <div class="inv-section-label">Invoice Details</div>
                    <table class="inv-meta-table">
                        <tr>
                            <td class="imt-lbl">Invoice #</td>
                            <td class="imt-val mono"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                        </tr>
                        <tr>
                            <td class="imt-lbl">Issue Date</td>
                            <td class="imt-val"><?= date('M d, Y', strtotime($inv['issue_date'])) ?></td>
                        </tr>
                        <tr>
                            <td class="imt-lbl">Due Date</td>
                            <td class="imt-val"><?= date('M d, Y', strtotime($inv['due_date'])) ?></td>
                        </tr>
                        <tr>
                            <td class="imt-lbl">Currency</td>
                            <td class="imt-val"><?= $cur ?></td>
                        </tr>
                        <?php if (!empty($inv['project_name'])): ?>
                        <tr>
                            <td class="imt-lbl">Project</td>
                            <td class="imt-val"><?= htmlspecialchars($inv['project_name']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($quotRow): ?>
                        <tr>
                            <td class="imt-lbl">From Quotation</td>
                            <td class="imt-val">
                                <a href="quotation_details.php?id=<?= $quotRow['id'] ?>" style="color:#024442;font-weight:600;">
                                    <?= htmlspecialchars($quotRow['quotation_number']) ?>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Line Items Table -->
        <div class="card" style="padding:0;overflow:hidden;margin-bottom:20px;">
            <div class="card-header" style="padding:16px 20px;">
                <div class="card-title">📝 Line Items</div>
            </div>
            <div style="overflow-x:auto;">
                <table class="inv-items-view-table">
                    <thead>
                        <tr>
                            <th style="width:5%">#</th>
                            <th style="width:52%">Description</th>
                            <th class="r" style="width:10%">Qty</th>
                            <th class="r" style="width:16%">Unit Price</th>
                            <th class="r" style="width:17%">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                        <tr><td colspan="5" style="text-align:center;padding:20px;color:#aaa;">No line items added.</td></tr>
                        <?php else: ?>
                        <?php foreach ($items as $i => $item): ?>
                        <tr>
                            <td class="item-num"><?= $i + 1 ?></td>
                            <td class="item-desc"><?= htmlspecialchars($item['description']) ?></td>
                            <td class="r item-qty"><?= rtrim(rtrim(number_format($item['quantity'], 2), '0'), '.') ?></td>
                            <td class="r item-price"><?= $cur ?> <?= number_format($item['unit_price'], 2) ?></td>
                            <td class="r item-total"><?= $cur ?> <?= number_format($item['total'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="foot-row">
                            <td colspan="3"></td>
                            <td class="foot-lbl">Subtotal</td>
                            <td class="foot-val"><?= $cur ?> <?= number_format($inv['subtotal'], 2) ?></td>
                        </tr>
                        <?php if ($inv['tax_percent'] > 0): ?>
                        <tr class="foot-row">
                            <td colspan="3"></td>
                            <td class="foot-lbl">Tax (<?= $inv['tax_percent'] ?>%)</td>
                            <td class="foot-val"><?= $cur ?> <?= number_format($inv['tax_amount'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($inv['discount_amount'] > 0): ?>
                        <tr class="foot-row">
                            <td colspan="3"></td>
                            <td class="foot-lbl">Discount</td>
                            <td class="foot-val">— <?= $cur ?> <?= number_format($inv['discount_amount'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="foot-total-row">
                            <td colspan="3"></td>
                            <td class="foot-total-lbl">TOTAL</td>
                            <td class="foot-total-val"><?= $cur ?> <?= number_format($inv['total_amount'], 2) ?></td>
                        </tr>
                        <?php if ($inv['amount_paid'] > 0): ?>
                        <tr class="foot-paid-row">
                            <td colspan="3"></td>
                            <td class="foot-lbl" style="color:#16a34a;">Amount Paid</td>
                            <td class="foot-val" style="color:#16a34a;">— <?= $cur ?> <?= number_format($inv['amount_paid'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($inv['amount_due'] > 0): ?>
                        <tr class="foot-due-row">
                            <td colspan="3"></td>
                            <td class="foot-lbl" style="color:#dc2626;font-weight:700;">AMOUNT DUE</td>
                            <td class="foot-val" style="color:#dc2626;font-weight:800;"><?= $cur ?> <?= number_format($inv['amount_due'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Notes & Terms -->
        <?php if (!empty($inv['notes']) || !empty($inv['terms'])): ?>
        <div class="card" style="margin-bottom:20px;">
            <?php if (!empty($inv['notes'])): ?>
            <div style="margin-bottom:<?= !empty($inv['terms']) ? '20px' : '0' ?>;">
                <div class="inv-section-label" style="margin-bottom:8px;">Notes</div>
                <p style="font-size:14px;color:#555;line-height:1.7;margin:0;white-space:pre-line;"><?= htmlspecialchars($inv['notes']) ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($inv['terms'])): ?>
            <div style="background:#f9fafb;border-left:3px solid #024442;border-radius:0 8px 8px 0;padding:14px 18px;">
                <div class="inv-section-label" style="margin-bottom:8px;">Terms & Conditions</div>
                <p style="font-size:13px;color:#666;line-height:1.75;margin:0;white-space:pre-line;"><?= htmlspecialchars($inv['terms']) ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div><!-- /.inv-detail-main -->

    <!-- ── Right: Sidebar ──────────────────────────────────────────────────── -->
    <div class="inv-detail-sidebar">

        <!-- Payment summary -->
        <div class="card sidebar-widget" style="border-top:3px solid <?= invStatusColor($inv['status']) ?>;">
            <div class="card-title" style="margin-bottom:16px;">💰 Payment Summary</div>

            <!-- Progress bar -->
            <div class="payment-progress-wrap">
                <div class="payment-progress-bar">
                    <div class="payment-progress-fill" style="width:<?= $pct_paid ?>%;background:<?= $pct_paid >= 100 ? '#16a34a' : '#024442' ?>;"></div>
                </div>
                <div class="payment-progress-label"><?= $pct_paid ?>% paid</div>
            </div>

            <div class="summary-rows">
                <div class="summary-row">
                    <span class="summary-lbl">Invoice Total</span>
                    <span class="summary-val"><?= $cur ?> <?= number_format($inv['total_amount'], 2) ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-lbl">Amount Paid</span>
                    <span class="summary-val" style="color:#16a34a;"><?= $cur ?> <?= number_format($inv['amount_paid'], 2) ?></span>
                </div>
                <div class="summary-row summary-row-final">
                    <span class="summary-lbl" style="color:<?= $inv['amount_due'] > 0 ? '#dc2626' : '#16a34a' ?>;">
                        <?= $inv['amount_due'] > 0 ? 'Amount Due' : 'Fully Paid' ?>
                    </span>
                    <span class="summary-val" style="color:<?= $inv['amount_due'] > 0 ? '#dc2626' : '#16a34a' ?>;font-size:17px;">
                        <?= $cur ?> <?= number_format($inv['amount_due'], 2) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Status & Timeline -->
        <div class="card sidebar-widget">
            <div class="card-title" style="margin-bottom:16px;">📅 Timeline</div>
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-dot" style="background:#024442;"></div>
                    <div class="timeline-content">
                        <div class="timeline-label">Created</div>
                        <div class="timeline-val"><?= date('M d, Y · h:i A', strtotime($inv['created_at'])) ?></div>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot" style="background:#3b82f6;"></div>
                    <div class="timeline-content">
                        <div class="timeline-label">Issue Date</div>
                        <div class="timeline-val"><?= date('M d, Y', strtotime($inv['issue_date'])) ?></div>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot" style="background:<?= strtotime($inv['due_date']) < time() && $inv['status'] !== 'paid' ? '#dc2626' : '#f97316' ?>;"></div>
                    <div class="timeline-content">
                        <div class="timeline-label">Due Date</div>
                        <div class="timeline-val" style="color:<?= strtotime($inv['due_date']) < time() && $inv['status'] !== 'paid' ? '#dc2626' : 'inherit' ?>;">
                            <?= date('M d, Y', strtotime($inv['due_date'])) ?>
                            <?php if ($inv['status'] === 'overdue'): ?>
                                <span style="font-size:11px;">(<?= $daysOverdue ?>d overdue)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php if (!empty($inv['paid_at'])): ?>
                <div class="timeline-item">
                    <div class="timeline-dot" style="background:#16a34a;"></div>
                    <div class="timeline-content">
                        <div class="timeline-label">Paid On</div>
                        <div class="timeline-val" style="color:#16a34a;"><?= date('M d, Y · h:i A', strtotime($inv['paid_at'])) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="timeline-item">
                    <div class="timeline-dot" style="background:#aaa;"></div>
                    <div class="timeline-content">
                        <div class="timeline-label">Last Updated</div>
                        <div class="timeline-val"><?= date('M d, Y · h:i A', strtotime($inv['updated_at'])) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card sidebar-widget">
            <div class="card-title" style="margin-bottom:14px;">⚡ Quick Actions</div>
            <div class="quick-actions-list">
                <a href="generate_invoice_pdf.php?id=<?= $id ?>" target="_blank" class="quick-action-btn">
                    <span>📄</span> View PDF
                </a>
                <a href="generate_invoice_pdf.php?id=<?= $id ?>&dl=1" class="quick-action-btn">
                    <span>⬇️</span> Download PDF
                </a>
                <a href="edit_invoice.php?id=<?= $id ?>" class="quick-action-btn">
                    <span>✏️</span> Edit Invoice
                </a>
                <?php if ($inv['status'] === 'draft'): ?>
                <a href="invoice_details.php?id=<?= $id ?>&action=mark_sent"
                   class="quick-action-btn" onclick="return confirm('Mark as sent?')">
                    <span>📤</span> Mark as Sent
                </a>
                <?php endif; ?>
                <?php if (!in_array($inv['status'], ['paid','cancelled'])): ?>
                <a href="invoice_details.php?id=<?= $id ?>&action=mark_paid"
                   class="quick-action-btn quick-action-green"
                   onclick="return confirm('Mark this invoice as paid?')">
                    <span>✅</span> Mark as Paid
                </a>
                <?php endif; ?>
                <?php if (!in_array($inv['status'], ['cancelled'])): ?>
                <a href="invoice_details.php?id=<?= $id ?>&action=cancel"
                   class="quick-action-btn quick-action-warn"
                   onclick="return confirm('Cancel this invoice?')">
                    <span>❌</span> Cancel Invoice
                </a>
                <?php endif; ?>
                <button onclick="deleteInvoice(<?= $id ?>, '<?= htmlspecialchars($inv['invoice_number']) ?>')"
                        class="quick-action-btn quick-action-red">
                    <span>🗑️</span> Delete Invoice
                </button>
            </div>
        </div>

        <!-- Breakdown mini-card -->
        <div class="card sidebar-widget">
            <div class="card-title" style="margin-bottom:14px;">🧮 Breakdown</div>
            <div class="breakdown-rows">
                <div class="breakdown-row">
                    <span>Subtotal</span>
                    <span><?= $cur ?> <?= number_format($inv['subtotal'], 2) ?></span>
                </div>
                <?php if ($inv['tax_percent'] > 0): ?>
                <div class="breakdown-row">
                    <span>Tax (<?= $inv['tax_percent'] ?>%)</span>
                    <span>+ <?= $cur ?> <?= number_format($inv['tax_amount'], 2) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($inv['discount_amount'] > 0): ?>
                <div class="breakdown-row" style="color:#16a34a;">
                    <span>Discount</span>
                    <span>— <?= $cur ?> <?= number_format($inv['discount_amount'], 2) ?></span>
                </div>
                <?php endif; ?>
                <div class="breakdown-row breakdown-total">
                    <span>Total</span>
                    <span><?= $cur ?> <?= number_format($inv['total_amount'], 2) ?></span>
                </div>
                <div class="breakdown-row" style="color:#16a34a;">
                    <span>Paid</span>
                    <span><?= $cur ?> <?= number_format($inv['amount_paid'], 2) ?></span>
                </div>
                <div class="breakdown-row" style="color:<?= $inv['amount_due'] > 0 ? '#dc2626' : '#16a34a' ?>;font-weight:700;">
                    <span>Due</span>
                    <span><?= $cur ?> <?= number_format($inv['amount_due'], 2) ?></span>
                </div>
            </div>
        </div>

    </div><!-- /.inv-detail-sidebar -->
</div><!-- /.inv-detail-grid -->
</div><!-- /.height-100 -->

<style>
/* ── Page header ───────────────────────────────────────────── */
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 20px;
}

.page-header h1 { font-size: 22px; }
.back-btn { font-size: 13px; color: #888; text-decoration: none; padding: 6px 0; display: inline-flex; align-items: center; gap: 4px; transition: color .2s; }
.back-btn:hover { color: #024442; }

.header-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

/* ── Alerts ────────────────────────────────────────────────── */
.alert-overdue, .alert-paid {
    padding: 12px 18px;
    border-radius: 8px;
    font-size: 13.5px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.alert-overdue {
    background: #fff5f5;
    border: 1px solid #fecaca;
    color: #dc2626;
}
.alert-overdue a { color: #dc2626; font-weight: 700; margin-left: 6px; }
.alert-paid {
    background: #f0fff4;
    border: 1px solid #86efac;
    color: #16a34a;
}

/* ── Main grid ─────────────────────────────────────────────── */
.inv-detail-grid {
    display: grid;
    grid-template-columns: 1fr 310px;
    gap: 20px;
    align-items: start;
}

@media (max-width: 1024px) { .inv-detail-grid { grid-template-columns: 1fr; } }

/* ── Meta card ─────────────────────────────────────────────── */
.inv-meta-card { margin-bottom: 20px; }

.inv-meta-cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

@media (max-width: 600px) { .inv-meta-cols { grid-template-columns: 1fr; } }

.inv-meta-col-right { text-align: right; }

.inv-section-label {
    font-size: 10px;
    font-weight: 700;
    color: #024442;
    text-transform: uppercase;
    letter-spacing: .14em;
    border-bottom: 2px solid #e8c97a;
    display: inline-block;
    padding-bottom: 3px;
    margin-bottom: 10px;
}

.inv-client-name   { font-size: 17px; font-weight: 800; color: #111; margin-bottom: 2px; }
.inv-client-co     { font-size: 13px; color: #555; margin-bottom: 6px; }
.inv-client-detail { font-size: 13px; color: #777; margin-top: 4px; line-height: 1.5; }

.inv-meta-table { border-collapse: collapse; width: 100%; }
.inv-meta-table tr td { padding: 5px 0; font-size: 13.5px; }
.imt-lbl { color: #999; text-align: right; padding-right: 14px; white-space: nowrap; width: 40%; }
.imt-val { color: #111; font-weight: 600; text-align: right; }
.mono    { font-family: 'Courier New', monospace; }

/* ── Line items table ──────────────────────────────────────── */
.inv-items-view-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.inv-items-view-table th {
    background: #f9fafb;
    padding: 11px 16px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    color: #888;
    text-transform: uppercase;
    letter-spacing: .06em;
    border-bottom: 1px solid #ebebeb;
}
.inv-items-view-table th.r { text-align: right; }
.inv-items-view-table tbody tr:hover { background: #fafafa; }
.inv-items-view-table td { padding: 12px 16px; border-bottom: 1px solid #f5f5f5; color: #444; vertical-align: top; }
.inv-items-view-table tbody tr:last-child td { border-bottom: 1px solid #ebebeb; }
.inv-items-view-table td.r { text-align: right; }

.item-num   { color: #bbb; font-size: 12px; }
.item-desc  { font-weight: 500; color: #222; }
.item-qty   { color: #666; }
.item-price { color: #666; font-family: 'Courier New', monospace; }
.item-total { font-weight: 700; color: #024442; font-family: 'Courier New', monospace; }

/* Footer rows */
.foot-row td { padding: 8px 16px; border-bottom: 1px solid #f5f5f5; font-size: 13.5px; }
.foot-lbl { text-align: right; color: #888; }
.foot-val { text-align: right; font-weight: 600; color: #111; font-family: 'Courier New', monospace; }

.foot-total-row td { background: #024442; color: #fff; padding: 10px 16px; }
.foot-total-lbl { text-align: right; font-size: 13px; font-weight: 700; }
.foot-total-val { text-align: right; font-size: 15px; font-weight: 800; color: #e8c97a; font-family: 'Courier New', monospace; }

.foot-paid-row td, .foot-due-row td { padding: 8px 16px; }

/* ── Sidebar ───────────────────────────────────────────────── */
.sidebar-widget { margin-bottom: 20px; }
.sidebar-widget:last-child { margin-bottom: 0; }

/* Payment progress */
.payment-progress-wrap { margin-bottom: 16px; }
.payment-progress-bar { height: 8px; background: #f0f0f0; border-radius: 100px; overflow: hidden; margin-bottom: 5px; }
.payment-progress-fill { height: 100%; border-radius: 100px; transition: width .5s ease; }
.payment-progress-label { font-size: 12px; color: #888; text-align: right; }

.summary-rows { display: flex; flex-direction: column; gap: 0; }
.summary-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f5f5f5; font-size: 13.5px; }
.summary-row:last-child { border-bottom: none; }
.summary-row-final { padding-top: 10px; margin-top: 4px; border-top: 2px solid #f0f0f0; border-bottom: none; }
.summary-lbl { color: #666; }
.summary-val { font-weight: 700; color: #111; font-size: 14px; }

/* Timeline */
.timeline { display: flex; flex-direction: column; gap: 0; }
.timeline-item { display: flex; gap: 12px; padding-bottom: 14px; position: relative; }
.timeline-item:last-child { padding-bottom: 0; }
.timeline-item::before { content:''; position:absolute; left:6px; top:14px; width:1px; height:calc(100% - 4px); background:#f0f0f0; }
.timeline-item:last-child::before { display: none; }
.timeline-dot { width: 13px; height: 13px; border-radius: 50%; flex-shrink: 0; margin-top: 2px; }
.timeline-label { font-size: 11px; color: #aaa; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
.timeline-val { font-size: 13px; color: #333; font-weight: 500; margin-top: 2px; }

/* Quick actions */
.quick-actions-list { display: flex; flex-direction: column; gap: 6px; }
.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 9px 13px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #fff;
    font-size: 13.5px;
    color: #333;
    text-decoration: none;
    cursor: pointer;
    width: 100%;
    text-align: left;
    transition: all .15s;
}
.quick-action-btn:hover       { background: #f0f5f4; border-color: #024442; color: #024442; }
.quick-action-green:hover     { background: #f0fff4; border-color: #16a34a; color: #16a34a; }
.quick-action-warn:hover      { background: #fffbeb; border-color: #d97706; color: #d97706; }
.quick-action-red:hover       { background: #fff5f5; border-color: #dc2626; color: #dc2626; }

/* Breakdown */
.breakdown-rows { display: flex; flex-direction: column; }
.breakdown-row { display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0; border-bottom: 1px solid #f5f5f5; color: #555; }
.breakdown-row:last-child { border-bottom: none; }
.breakdown-total { font-weight: 800; color: #024442; font-size: 14px; padding-top: 8px; border-top: 2px solid #f0f0f0; border-bottom: 1px solid #f5f5f5; }
</style>

<script>
function deleteInvoice(id, num) {
    if (!confirm('Permanently delete invoice ' + num + '?\n\nThis cannot be undone.')) return;
    window.location = 'invoice_details.php?id=' + id + '&action=delete';
}
</script>

<?php include('dashboard_footer.php'); ?>