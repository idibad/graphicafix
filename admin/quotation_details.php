<?php
include('dashboard_header.php');

$id = intval($_GET['id'] ?? 0);
if (!$id) { echo "<script>alert('Invalid quotation.');window.location='manage_invoices.php?tab=quotations';</script>"; exit; }

// ── Inline actions ────────────────────────────────────────────────────────────
if (isset($_GET['action'])) {
    match($_GET['action']) {
        'mark_sent'     => $conn->query("UPDATE quotations SET status='sent',     updated_at=NOW() WHERE id={$id}"),
        'mark_accepted' => $conn->query("UPDATE quotations SET status='accepted', updated_at=NOW() WHERE id={$id}"),
        'mark_rejected' => $conn->query("UPDATE quotations SET status='rejected', updated_at=NOW() WHERE id={$id}"),
        'delete'        => null,
        default         => null,
    };
    if ($_GET['action'] === 'delete') {
        $conn->query("DELETE FROM quotations WHERE id={$id}");
        echo "<script>alert('Quotation deleted.');window.location='manage_invoices.php?tab=quotations';</script>";
        exit;
    }
    header("Location: quotation_details.php?id={$id}");
    exit;
}

// ── Auto-generate PDF on redirect from create ─────────────────────────────────
$justCreated = isset($_GET['gen']) && $_GET['gen'] == '1';

// ── Fetch quotation ───────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT q.*, c.client_name AS reg_client_name FROM quotations q LEFT JOIN clients c ON q.client_id=c.id WHERE q.id=?");
$stmt->bind_param("i",$id); $stmt->execute();
$q = $stmt->get_result()->fetch_assoc();
if (!$q) { echo "<script>alert('Quotation not found.');window.location='manage_invoices.php?tab=quotations';</script>"; exit; }

// ── Fetch line items ──────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id=? ORDER BY sort_order");
$stmt->bind_param("i",$id); $stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Check converted invoice ───────────────────────────────────────────────────
$convertedInv = null;
if ($q['converted_invoice_id']) {
    $convertedInv = $conn->query("SELECT id, invoice_number, status FROM invoices WHERE id={$q['converted_invoice_id']}")->fetch_assoc();
}

// ── Helpers ───────────────────────────────────────────────────────────────────
$cur = htmlspecialchars($q['currency']);
$daysLeft    = ceil((strtotime($q['valid_until']) - time()) / 86400);
$isExpired   = strtotime($q['valid_until']) < time() && !in_array($q['status'], ['accepted','rejected']);

function quotStatusClass($s) {
    return match($s) {
        'accepted' => 'status-completed',
        'sent'     => 'status-in-progress',
        'rejected' => 'status-on-hold',
        'expired'  => 'status-not-started',
        default    => 'status-default',
    };
}
function quotStatusColor($s) {
    return match($s) {
        'accepted' => '#16a34a',
        'sent'     => '#3b82f6',
        'rejected' => '#dc2626',
        'expired'  => '#f97316',
        default    => '#6b7280',
    };
}
?>

<div class="height-100">

<!-- ── Page header ─────────────────────────────────────────────────────────── -->
<div class="page-header">
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
        <a href="manage_invoices.php?tab=quotations" class="back-btn">← Quotations</a>
        <div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <h1 style="margin:0;"><?= htmlspecialchars($q['quotation_number']) ?></h1>
                <span class="badge <?= quotStatusClass($q['status']) ?>" style="font-size:12px;"><?= ucfirst($q['status']) ?></span>
                <?php if ($q['status'] === 'sent' && $daysLeft > 0): ?>
                    <span style="font-size:12px;color:#3b82f6;font-weight:600;"><?= $daysLeft ?> days left</span>
                <?php elseif ($isExpired): ?>
                    <span style="font-size:12px;color:#f97316;font-weight:600;">Expired</span>
                <?php endif; ?>
            </div>
            <p style="margin:4px 0 0;color:#888;font-size:13.5px;">
                <?= htmlspecialchars($q['client_name']) ?>
                <?= !empty($q['client_company']) ? ' · ' . htmlspecialchars($q['client_company']) : '' ?>
            </p>
        </div>
    </div>
    <div class="header-actions">
        <a href="generate_quotation_pdf.php?id=<?= $id ?>"     target="_blank" class="btn btn-secondary">📥 View PDF</a>
        <a href="generate_quotation_pdf.php?id=<?= $id ?>&dl=1" class="btn btn-secondary">⬇️ Download PDF</a>
        <a href="edit_quotation.php?id=<?= $id ?>"             class="btn btn-secondary">✏️ Edit</a>
        <?php if (!$convertedInv): ?>
        <a href="create_invoice.php?from_quot=<?= $id ?>"      class="btn btn-primary">🧾 Convert to Invoice</a>
        <?php endif; ?>
        <button class="btn btn-danger" onclick="deleteQuot(<?= $id ?>,'<?= htmlspecialchars($q['quotation_number']) ?>')">🗑️ Delete</button>
    </div>
</div>

<!-- ── Alerts ──────────────────────────────────────────────────────────────── -->
<?php if ($justCreated): ?>
<div class="alert-success">
    ✅ <strong>Quotation created successfully!</strong>
    <a href="generate_quotation_pdf.php?id=<?= $id ?>" target="_blank">Generate & View PDF →</a>
</div>
<?php endif; ?>

<?php if ($q['status'] === 'accepted' && $convertedInv): ?>
<div class="alert-success">
    ✅ <strong>Accepted.</strong> Converted to invoice
    <a href="invoice_details.php?id=<?= $convertedInv['id'] ?>" style="font-weight:700;color:#16a34a;">
        <?= htmlspecialchars($convertedInv['invoice_number']) ?>
    </a>
</div>
<?php elseif ($q['status'] === 'accepted'): ?>
<div class="alert-success">
    ✅ <strong>This quotation has been accepted.</strong>
    <a href="create_invoice.php?from_quot=<?= $id ?>">Convert to Invoice →</a>
</div>
<?php elseif ($q['status'] === 'rejected'): ?>
<div class="alert-rejected">
    ❌ <strong>This quotation was rejected by the client.</strong>
</div>
<?php elseif ($isExpired): ?>
<div class="alert-expired">
    ⏰ <strong>This quotation expired on <?= date('M d, Y', strtotime($q['valid_until'])) ?>.</strong>
    <a href="edit_quotation.php?id=<?= $id ?>">Update & re-send →</a>
</div>
<?php elseif ($q['status'] === 'sent' && $daysLeft <= 5 && $daysLeft > 0): ?>
<div class="alert-warn">
    ⚠️ <strong>Expiring soon.</strong> This quotation is valid for <?= $daysLeft ?> more day<?= $daysLeft!==1?'s':'' ?>, until <?= date('M d, Y', strtotime($q['valid_until'])) ?>.
</div>
<?php endif; ?>

<!-- ── Main grid ────────────────────────────────────────────────────────────── -->
<div class="qd-grid">

    <!-- ── Left: Main content ───────────────────────────────────────────────── -->
    <div class="qd-main">

        <!-- Client & Quotation meta -->
        <div class="card qd-meta-card">
            <div class="qd-meta-cols">
                <div class="qd-meta-col">
                    <div class="qd-section-label">Prepared For</div>
                    <div class="qd-client-name"><?= htmlspecialchars($q['client_name']) ?></div>
                    <?php if (!empty($q['client_company'])): ?>
                        <div class="qd-client-co"><?= htmlspecialchars($q['client_company']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($q['client_email'])): ?>
                        <div class="qd-client-detail">✉️ <a href="mailto:<?= htmlspecialchars($q['client_email']) ?>"><?= htmlspecialchars($q['client_email']) ?></a></div>
                    <?php endif; ?>
                    <?php if (!empty($q['client_phone'])): ?>
                        <div class="qd-client-detail">📞 <?= htmlspecialchars($q['client_phone']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($q['client_address'])): ?>
                        <div class="qd-client-detail">📍 <?= nl2br(htmlspecialchars($q['client_address'])) ?></div>
                    <?php endif; ?>
                </div>
                <div class="qd-meta-col qd-meta-col-right">
                    <div class="qd-section-label">Quotation Details</div>
                    <table class="qd-meta-table">
                        <tr>
                            <td class="qmt-lbl">Quotation #</td>
                            <td class="qmt-val mono"><?= htmlspecialchars($q['quotation_number']) ?></td>
                        </tr>
                        <tr>
                            <td class="qmt-lbl">Issue Date</td>
                            <td class="qmt-val"><?= date('M d, Y', strtotime($q['issue_date'])) ?></td>
                        </tr>
                        <tr>
                            <td class="qmt-lbl">Valid Until</td>
                            <td class="qmt-val" style="color:<?= $isExpired ? '#f97316' : ($daysLeft<=5&&$daysLeft>0?'#d97706':'inherit') ?>;">
                                <?= date('M d, Y', strtotime($q['valid_until'])) ?>
                                <?php if (!$isExpired && !in_array($q['status'],['accepted','rejected']) && $daysLeft>0): ?>
                                    <span style="font-size:11px;color:#888;">(<?= $daysLeft ?>d left)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="qmt-lbl">Currency</td>
                            <td class="qmt-val"><?= $cur ?></td>
                        </tr>
                        <?php if ($convertedInv): ?>
                        <tr>
                            <td class="qmt-lbl">Invoice</td>
                            <td class="qmt-val">
                                <a href="invoice_details.php?id=<?= $convertedInv['id'] ?>" style="color:#024442;font-weight:600;">
                                    <?= htmlspecialchars($convertedInv['invoice_number']) ?>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Notes / Scope -->
        <?php if (!empty($q['notes'])): ?>
        <div class="card" style="margin-bottom:20px;">
            <div class="qd-section-label" style="margin-bottom:10px;">📋 Scope & Description</div>
            <p style="font-size:14px;color:#444;line-height:1.75;margin:0;white-space:pre-line;"><?= htmlspecialchars($q['notes']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Pricing table -->
        <div class="card" style="padding:0;overflow:hidden;margin-bottom:20px;">
            <div style="padding:16px 20px;border-bottom:1px solid #f0f0f0;">
                <div class="card-title">💰 Pricing Breakdown</div>
            </div>
            <div style="overflow-x:auto;">
                <table class="qd-items-table">
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
                        <tr><td colspan="5" style="text-align:center;padding:20px;color:#aaa;">No line items.</td></tr>
                        <?php else: ?>
                        <?php foreach ($items as $i => $item): ?>
                        <tr>
                            <td class="item-num"><?= $i + 1 ?></td>
                            <td class="item-desc"><?= htmlspecialchars($item['description']) ?></td>
                            <td class="r item-qty"><?= rtrim(rtrim(number_format($item['quantity'],2),'0'),'.') ?></td>
                            <td class="r item-price"><?= $cur ?> <?= number_format($item['unit_price'],2) ?></td>
                            <td class="r item-total"><?= $cur ?> <?= number_format($item['total'],2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="foot-row">
                            <td colspan="3"></td>
                            <td class="foot-lbl">Subtotal</td>
                            <td class="foot-val"><?= $cur ?> <?= number_format($q['subtotal'],2) ?></td>
                        </tr>
                        <?php if ($q['tax_percent'] > 0): ?>
                        <tr class="foot-row">
                            <td colspan="3"></td>
                            <td class="foot-lbl">Tax (<?= $q['tax_percent'] ?>%)</td>
                            <td class="foot-val"><?= $cur ?> <?= number_format($q['tax_amount'],2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($q['discount_amount'] > 0): ?>
                        <tr class="foot-row">
                            <td colspan="3"></td>
                            <td class="foot-lbl">Discount</td>
                            <td class="foot-val">— <?= $cur ?> <?= number_format($q['discount_amount'],2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="foot-total-row">
                            <td colspan="3"></td>
                            <td class="foot-total-lbl">TOTAL</td>
                            <td class="foot-total-val"><?= $cur ?> <?= number_format($q['total_amount'],2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Terms -->
        <?php if (!empty($q['terms'])): ?>
        <div class="card" style="margin-bottom:20px;">
            <div style="background:#f9fafb;border-left:3px solid #024442;border-radius:0 8px 8px 0;padding:14px 18px;">
                <div class="qd-section-label" style="margin-bottom:8px;">📄 Terms & Conditions</div>
                <p style="font-size:13px;color:#666;line-height:1.75;margin:0;white-space:pre-line;"><?= htmlspecialchars($q['terms']) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Signature block -->
        <?php if (!empty($q['signature_name'])): ?>
        <div class="card">
            <div class="qd-section-label" style="margin-bottom:16px;">✍️ Signature</div>
            <div class="sig-block">
                <div class="sig-col">
                    <div class="sig-label">Authorized By</div>
                    <div class="sig-line-bar"></div>
                    <div class="sig-name"><?= htmlspecialchars($q['signature_name']) ?></div>
                    <?php if (!empty($q['signature_title'])): ?>
                    <div class="sig-title"><?= htmlspecialchars($q['signature_title']) ?></div>
                    <?php endif; ?>
                    <div class="sig-date">Date: <?= !empty($q['signature_date']) ? date('M d, Y', strtotime($q['signature_date'])) : '—' ?></div>
                    <div class="sig-co">Graphicafix Creative Agency</div>
                </div>
                <div class="sig-col" style="text-align:right;">
                    <div class="sig-label">Client Acceptance</div>
                    <div class="sig-line-bar" style="margin-left:auto;"></div>
                    <div class="sig-name" style="color:#ccc;">Client Signature</div>
                    <div class="sig-date" style="color:#ccc;">Date: _______________</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.qd-main -->

    <!-- ── Right: Sidebar ──────────────────────────────────────────────────── -->
    <div class="qd-sidebar">

        <!-- Total card -->
        <div class="card sidebar-widget" style="border-top:3px solid <?= quotStatusColor($q['status']) ?>;">
            <div class="card-title" style="margin-bottom:14px;">💰 Quotation Value</div>
            <div class="qd-total-amount"><?= $cur ?> <?= number_format($q['total_amount'],2) ?></div>
            <div style="font-size:12px;color:#aaa;margin-top:4px;"><?= count($items) ?> line item<?= count($items)!==1?'s':'' ?></div>

            <?php if (!$convertedInv && in_array($q['status'],['draft','sent','accepted'])): ?>
            <a href="create_invoice.php?from_quot=<?= $id ?>" class="btn btn-primary" style="width:100%;margin-top:14px;justify-content:center;">
                🧾 Convert to Invoice
            </a>
            <?php elseif ($convertedInv): ?>
            <a href="invoice_details.php?id=<?= $convertedInv['id'] ?>" class="btn btn-secondary" style="width:100%;margin-top:14px;justify-content:center;">
                📄 View Invoice <?= htmlspecialchars($convertedInv['invoice_number']) ?>
            </a>
            <?php endif; ?>
        </div>

        <!-- Timeline -->
        <div class="card sidebar-widget">
            <div class="card-title" style="margin-bottom:16px;">📅 Timeline</div>
            <div class="timeline">
                <div class="timeline-item">
                    <div class="tl-dot" style="background:#024442;"></div>
                    <div class="tl-body">
                        <div class="tl-lbl">Created</div>
                        <div class="tl-val"><?= date('M d, Y · h:i A', strtotime($q['created_at'])) ?></div>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="tl-dot" style="background:#3b82f6;"></div>
                    <div class="tl-body">
                        <div class="tl-lbl">Issue Date</div>
                        <div class="tl-val"><?= date('M d, Y', strtotime($q['issue_date'])) ?></div>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="tl-dot" style="background:<?= $isExpired ? '#f97316' : ($daysLeft<=5&&$daysLeft>0 ? '#d97706' : '#22c55e') ?>;"></div>
                    <div class="tl-body">
                        <div class="tl-lbl">Valid Until</div>
                        <div class="tl-val" style="color:<?= $isExpired ? '#f97316' : 'inherit' ?>;">
                            <?= date('M d, Y', strtotime($q['valid_until'])) ?>
                            <?php if (!in_array($q['status'],['accepted','rejected'])): ?>
                                <?php if ($isExpired): ?><span style="font-size:11px;">(expired)</span>
                                <?php elseif ($daysLeft > 0): ?><span style="font-size:11px;">(<?= $daysLeft ?>d left)</span><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php if ($convertedInv): ?>
                <div class="timeline-item">
                    <div class="tl-dot" style="background:#16a34a;"></div>
                    <div class="tl-body">
                        <div class="tl-lbl">Converted to Invoice</div>
                        <div class="tl-val" style="color:#16a34a;"><?= htmlspecialchars($convertedInv['invoice_number']) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="timeline-item">
                    <div class="tl-dot" style="background:#aaa;"></div>
                    <div class="tl-body">
                        <div class="tl-lbl">Last Updated</div>
                        <div class="tl-val"><?= date('M d, Y · h:i A', strtotime($q['updated_at'])) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card sidebar-widget">
            <div class="card-title" style="margin-bottom:14px;">⚡ Quick Actions</div>
            <div class="quick-list">
                <a href="generate_quotation_pdf.php?id=<?= $id ?>" target="_blank" class="quick-btn">
                    <span>📄</span> View PDF
                </a>
                <a href="generate_quotation_pdf.php?id=<?= $id ?>&dl=1" class="quick-btn">
                    <span>⬇️</span> Download PDF
                </a>
                <a href="edit_quotation.php?id=<?= $id ?>" class="quick-btn">
                    <span>✏️</span> Edit Quotation
                </a>
                <?php if ($q['status'] === 'draft'): ?>
                <a href="quotation_details.php?id=<?= $id ?>&action=mark_sent"
                   class="quick-btn" onclick="return confirm('Mark as sent to client?')">
                    <span>📤</span> Mark as Sent
                </a>
                <?php endif; ?>
                <?php if (in_array($q['status'],['draft','sent'])): ?>
                <a href="quotation_details.php?id=<?= $id ?>&action=mark_accepted"
                   class="quick-btn quick-btn-green" onclick="return confirm('Mark as accepted?')">
                    <span>✅</span> Mark as Accepted
                </a>
                <a href="quotation_details.php?id=<?= $id ?>&action=mark_rejected"
                   class="quick-btn quick-btn-warn" onclick="return confirm('Mark as rejected?')">
                    <span>❌</span> Mark as Rejected
                </a>
                <?php endif; ?>
                <button onclick="deleteQuot(<?= $id ?>,'<?= htmlspecialchars($q['quotation_number']) ?>')"
                        class="quick-btn quick-btn-red">
                    <span>🗑️</span> Delete Quotation
                </button>
            </div>
        </div>

        <!-- Breakdown -->
        <div class="card sidebar-widget">
            <div class="card-title" style="margin-bottom:14px;">🧮 Breakdown</div>
            <div class="breakdown-rows">
                <div class="brow"><span>Subtotal</span><span><?= $cur ?> <?= number_format($q['subtotal'],2) ?></span></div>
                <?php if ($q['tax_percent'] > 0): ?>
                <div class="brow"><span>Tax (<?= $q['tax_percent'] ?>%)</span><span>+ <?= $cur ?> <?= number_format($q['tax_amount'],2) ?></span></div>
                <?php endif; ?>
                <?php if ($q['discount_amount'] > 0): ?>
                <div class="brow" style="color:#16a34a;"><span>Discount</span><span>— <?= $cur ?> <?= number_format($q['discount_amount'],2) ?></span></div>
                <?php endif; ?>
                <div class="brow brow-total"><span>Total</span><span><?= $cur ?> <?= number_format($q['total_amount'],2) ?></span></div>
            </div>
        </div>

    </div><!-- /.qd-sidebar -->
</div>
</div><!-- /.height-100 -->

<style>
.page-header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:20px;}
.page-header h1{font-size:22px;}
.back-btn{font-size:13px;color:#888;text-decoration:none;padding:6px 0;display:inline-flex;align-items:center;gap:4px;transition:color .2s;}
.back-btn:hover{color:#024442;}
.header-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}

/* Alerts */
.alert-success,.alert-rejected,.alert-expired,.alert-warn{
    padding:12px 18px;border-radius:8px;font-size:13.5px;margin-bottom:18px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;
}
.alert-success  {background:#f0fff4;border:1px solid #86efac;color:#16a34a;}
.alert-success a{color:#16a34a;font-weight:700;margin-left:4px;}
.alert-rejected {background:#fff5f5;border:1px solid #fecaca;color:#dc2626;}
.alert-expired  {background:#fff7ed;border:1px solid #fdba74;color:#c2410c;}
.alert-expired a{color:#c2410c;font-weight:700;margin-left:4px;}
.alert-warn     {background:#fffbeb;border:1px solid #fcd34d;color:#d97706;}

/* Grid */
.qd-grid{display:grid;grid-template-columns:1fr 310px;gap:20px;align-items:start;}
@media(max-width:1024px){.qd-grid{grid-template-columns:1fr;}}

/* Meta card */
.qd-meta-card{margin-bottom:20px;}
.qd-meta-cols{display:grid;grid-template-columns:1fr 1fr;gap:24px;}
@media(max-width:600px){.qd-meta-cols{grid-template-columns:1fr;}}
.qd-meta-col-right{text-align:right;}
.qd-section-label{font-size:10px;font-weight:700;color:#024442;text-transform:uppercase;letter-spacing:.14em;border-bottom:2px solid #e8c97a;display:inline-block;padding-bottom:3px;margin-bottom:10px;}
.qd-client-name{font-size:17px;font-weight:800;color:#111;margin-bottom:2px;}
.qd-client-co{font-size:13px;color:#555;margin-bottom:6px;}
.qd-client-detail{font-size:13px;color:#777;margin-top:4px;line-height:1.5;}
.qd-client-detail a{color:#024442;text-decoration:none;}
.qd-client-detail a:hover{text-decoration:underline;}
.qd-meta-table{border-collapse:collapse;width:100%;}
.qd-meta-table tr td{padding:5px 0;font-size:13.5px;}
.qmt-lbl{color:#999;text-align:right;padding-right:14px;white-space:nowrap;width:40%;}
.qmt-val{color:#111;font-weight:600;text-align:right;}
.mono{font-family:'Courier New',monospace;}

/* Items table */
.qd-items-table{width:100%;border-collapse:collapse;font-size:14px;}
.qd-items-table th{background:#f9fafb;padding:11px 16px;text-align:left;font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid #ebebeb;}
.qd-items-table th.r{text-align:right;}
.qd-items-table tbody tr:hover{background:#fafafa;}
.qd-items-table td{padding:12px 16px;border-bottom:1px solid #f5f5f5;color:#444;vertical-align:top;}
.qd-items-table tbody tr:last-child td{border-bottom:1px solid #ebebeb;}
.qd-items-table td.r{text-align:right;}
.item-num{color:#bbb;font-size:12px;}
.item-desc{font-weight:500;color:#222;}
.item-qty{color:#666;}
.item-price{color:#666;font-family:'Courier New',monospace;}
.item-total{font-weight:700;color:#024442;font-family:'Courier New',monospace;}
.foot-row td{padding:8px 16px;border-bottom:1px solid #f5f5f5;font-size:13.5px;}
.foot-lbl{text-align:right;color:#888;}
.foot-val{text-align:right;font-weight:600;color:#111;font-family:'Courier New',monospace;}
.foot-total-row td{background:#024442;color:#fff;padding:10px 16px;}
.foot-total-lbl{text-align:right;font-size:13px;font-weight:700;}
.foot-total-val{text-align:right;font-size:15px;font-weight:800;color:#e8c97a;font-family:'Courier New',monospace;}

/* Signature */
.sig-block{display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:20px;}
.sig-col{}
.sig-label{font-size:10px;font-weight:700;color:#024442;text-transform:uppercase;letter-spacing:.12em;margin-bottom:10px;}
.sig-line-bar{width:180px;height:1px;background:#333;margin-bottom:6px;}
.sig-name{font-size:14px;font-weight:700;color:#111;}
.sig-title{font-size:12px;color:#555;margin-top:2px;}
.sig-date{font-size:11px;color:#888;margin-top:3px;}
.sig-co{font-size:12px;font-weight:700;color:#024442;margin-top:6px;}

/* Sidebar */
.sidebar-widget{margin-bottom:20px;}
.sidebar-widget:last-child{margin-bottom:0;}
.qd-total-amount{font-size:26px;font-weight:800;color:#024442;}

/* Timeline */
.timeline{display:flex;flex-direction:column;gap:0;}
.timeline-item{display:flex;gap:12px;padding-bottom:14px;position:relative;}
.timeline-item:last-child{padding-bottom:0;}
.timeline-item::before{content:'';position:absolute;left:6px;top:14px;width:1px;height:calc(100% - 4px);background:#f0f0f0;}
.timeline-item:last-child::before{display:none;}
.tl-dot{width:13px;height:13px;border-radius:50%;flex-shrink:0;margin-top:2px;}
.tl-lbl{font-size:11px;color:#aaa;font-weight:600;text-transform:uppercase;letter-spacing:.06em;}
.tl-val{font-size:13px;color:#333;font-weight:500;margin-top:2px;}

/* Quick actions */
.quick-list{display:flex;flex-direction:column;gap:6px;}
.quick-btn{display:flex;align-items:center;gap:9px;padding:9px 13px;border-radius:8px;border:1px solid #e5e7eb;background:#fff;font-size:13.5px;color:#333;text-decoration:none;cursor:pointer;width:100%;text-align:left;transition:all .15s;}
.quick-btn:hover{background:#f0f5f4;border-color:#024442;color:#024442;}
.quick-btn-green:hover{background:#f0fff4;border-color:#16a34a;color:#16a34a;}
.quick-btn-warn:hover{background:#fff7ed;border-color:#f97316;color:#f97316;}
.quick-btn-red:hover{background:#fff5f5;border-color:#dc2626;color:#dc2626;}

/* Breakdown */
.breakdown-rows{display:flex;flex-direction:column;}
.brow{display:flex;justify-content:space-between;font-size:13px;padding:6px 0;border-bottom:1px solid #f5f5f5;color:#555;}
.brow:last-child{border-bottom:none;}
.brow-total{font-weight:800;color:#024442;font-size:14px;padding-top:8px;border-top:2px solid #f0f0f0;border-bottom:1px solid #f5f5f5;}
</style>

<script>
function deleteQuot(id, num) {
    if (!confirm('Permanently delete quotation ' + num + '?\n\nThis cannot be undone.')) return;
    window.location = 'quotation_details.php?id=' + id + '&action=delete';
}
</script>

<?php include('dashboard_footer.php'); ?>