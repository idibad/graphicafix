<?php
/**
 * generate_quotation_pdf.php
 * Requires mPDF: composer require mpdf/mpdf
 */

// Hide errors to prevent 500 crashes
error_reporting(0);
ini_set('display_errors', '0');

require_once '../vendor/autoload.php';
include('../config.php');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) die('Invalid ID.');

$stmt = $conn->prepare("SELECT * FROM quotations WHERE id=?");
$stmt->bind_param("i", $id); 
$stmt->execute();
$q = $stmt->get_result()->fetch_assoc();
if (!$q) die('Quotation not found.');

$stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id=? ORDER BY sort_order");
$stmt->bind_param("i", $id); 
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Status Badge Colors
$statusColors = [
    'draft'    => ['bg' => '#f3f4f6', 'text' => '#4b5563'],
    'sent'     => ['bg' => '#dbeafe', 'text' => '#2563eb'],
    'accepted' => ['bg' => '#dcfce7', 'text' => '#16a34a'],
    'rejected' => ['bg' => '#fee2e2', 'text' => '#dc2626'],
    'expired'  => ['bg' => '#ffedd5', 'text' => '#ea580c']
];
$status = strtolower($q['status']);
$badgeBg = isset($statusColors[$status]) ? $statusColors[$status]['bg'] : '#f3f4f6';
$badgeText = isset($statusColors[$status]) ? $statusColors[$status]['text'] : '#4b5563';

$cur = htmlspecialchars($q['currency']);

ob_start(); 
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">

<style>
/* ── BASE TYPOGRAPHY & COLORS ── */
body {
    font-family: 'DejaVu Sans', Helvetica, sans-serif;
    color: #333333;
    font-size: 13px;
    line-height: 1.5;
    margin: 0;
    padding: 0;
}

.text-primary { color: #024442; }
.text-accent { color: #e8c97a; }
.text-gray { color: #666666; }
.text-light { color: #999999; }
.font-bold { font-weight: bold; }

/* ── STRUCTURAL TABLES (mPDF Safe) ── */
table { width: 100%; border-collapse: collapse; }
td { vertical-align: top; }

/* ── HEADER SECTION ── */
.header-table {
    margin-bottom: 40px;
    border-bottom: 2px solid #024442;
    padding-bottom: 20px;
}
.doc-title {
    font-size: 32px;
    font-weight: bold;
    color: #024442;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin: 0 0 5px 0;
}
.doc-number {
    font-size: 14px;
    color: #666666;
}
.status-badge {
    background-color: <?php echo $badgeBg; ?>;
    color: <?php echo $badgeText; ?>;
    font-size: 11px;
    font-weight: bold;
    padding: 4px 12px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 1px;
    display: inline-block;
    margin-top: 8px;
}

/* ── META INFO SECTION ── */
.meta-table { margin-bottom: 40px; }
.meta-heading {
    font-size: 11px;
    font-weight: bold;
    color: #999999;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
}
.client-name {
    font-size: 18px;
    font-weight: bold;
    color: #024442;
    margin-bottom: 4px;
}
.client-details {
    font-size: 13px;
    color: #555555;
    line-height: 1.6;
}
.quote-details-table td {
    padding: 4px 0;
    font-size: 13px;
}
.quote-details-label {
    color: #666666;
    padding-right: 15px !important;
}
.quote-details-value {
    font-weight: bold;
    color: #333333;
    text-align: right;
}

/* ── CONTENT BLOCKS ── */
.content-block {
    margin-bottom: 30px;
}
.block-title {
    font-size: 14px;
    font-weight: bold;
    color: #024442;
    border-bottom: 1px solid #eeeeee;
    padding-bottom: 8px;
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.html-content {
    font-size: 13px;
    color: #444444;
    line-height: 1.6;
}

/* ── ITEMS TABLE ── */
.items-table {
    margin-bottom: 30px;
    border: 1px solid #e5e7eb;
}
.items-table thead {
    background-color: #024442;
}
.items-table th {
    color: #ffffff;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 12px;
    text-align: left;
}
.items-table th.r, .items-table td.r { text-align: right; }
.items-table th.c, .items-table td.c { text-align: center; }
.items-table td {
    padding: 14px 12px;
    border-bottom: 1px solid #e5e7eb;
    color: #333333;
}
.items-table tbody tr:nth-child(even) {
    background-color: #f9fafb;
}

/* ── TOTALS TABLE ── */
.totals-table {
    width: 350px;
    float: right;
    margin-bottom: 40px;
}
.totals-table td {
    padding: 8px 12px;
    border-bottom: 1px solid #eeeeee;
}
.totals-table .totals-label {
    color: #666666;
    text-align: right;
}
.totals-table .totals-val {
    font-weight: bold;
    text-align: right;
    width: 120px;
}
.totals-table .grand-total td {
    background-color: #024442;
    color: #ffffff;
    font-size: 15px;
    border: none;
    padding: 12px;
}
.totals-table .grand-total .totals-val {
    color: #e8c97a;
}

/* ── SIGNATURES ── */
.signatures-table {
    margin-top: 50px;
    page-break-inside: avoid;
}
.sign-box {
    width: 250px;
}
.sign-line {
    border-bottom: 1px solid #333333;
    height: 60px;
    margin-bottom: 8px;
}
.sign-name {
    font-weight: bold;
    color: #024442;
    font-size: 14px;
}
.sign-title {
    color: #666666;
    font-size: 11px;
}

</style>
</head>
<body>

    <htmlpageheader name="pageHeader">
        <img src="../images/letter_header.png" style="width: 100%;" />
    </htmlpageheader>
    <sethtmlpageheader name="pageHeader" page="O" value="on" show-this-page="1" />

    <htmlpagefooter name="pageFooter">
        <img src="../images/letter_footer.png" style="width: 100%;" />
    </htmlpagefooter>
    <sethtmlpagefooter name="pageFooter" page="O" value="on" show-this-page="1" />


    <div style="padding: 20px 40px;">

        <table class="header-table">
            <tr>
                <td width="50%">
                    <div style="height: 60px;"></div> </td>
                <td width="50%" style="text-align: right;">
                    <h1 class="doc-title">QUOTATION</h1>
                    <div class="doc-number">REF: <?php echo htmlspecialchars($q['quotation_number']); ?></div>
                    <div class="status-badge"><?php echo strtoupper($q['status']); ?></div>
                </td>
            </tr>
        </table>

        <table class="meta-table">
            <tr>
                <td width="55%">
                    <div class="meta-heading">Prepared For</div>
                    <div class="client-name"><?php echo htmlspecialchars($q['client_name']); ?></div>
                    <div class="client-details">
                        <?php if (!empty($q['client_company'])) echo '<strong>' . htmlspecialchars($q['client_company']) . '</strong><br>'; ?>
                        <?php if (!empty($q['client_email'])) echo htmlspecialchars($q['client_email']) . '<br>'; ?>
                        <?php if (!empty($q['client_phone'])) echo htmlspecialchars($q['client_phone']) . '<br>'; ?>
                        <?php if (!empty($q['client_address'])) echo nl2br(htmlspecialchars($q['client_address'])); ?>
                    </div>
                </td>
                
                <td width="45%">
                    <div class="meta-heading">Quotation Details</div>
                    <table class="quote-details-table">
                        <tr>
                            <td class="quote-details-label">Issue Date:</td>
                            <td class="quote-details-value"><?php echo date('F j, Y', strtotime($q['issue_date'])); ?></td>
                        </tr>
                        <tr>
                            <td class="quote-details-label">Valid Until:</td>
                            <td class="quote-details-value"><?php echo date('F j, Y', strtotime($q['valid_until'])); ?></td>
                        </tr>
                        <tr>
                            <td class="quote-details-label">Currency:</td>
                            <td class="quote-details-value"><?php echo $cur; ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <?php if (!empty($q['notes'])): ?>
        <div class="content-block">
            <div class="block-title">Scope of Work</div>
            <div class="html-content">
                <?php echo $q['notes']; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="content-block">
            <div class="block-title">Payment Breakdown</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th width="5%" class="c">#</th>
                        <th width="50%">Description</th>
                        <th width="15%" class="c">Qty</th>
                        <th width="15%" class="r">Unit Price</th>
                        <th width="15%" class="r">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $it): ?>
                    <tr>
                        <td class="c"><?php echo $i + 1; ?></td>
                        <td><?php echo $it['description']; ?></td>
                        <td class="c"><?php echo floatval($it['quantity']); ?></td>
                        <td class="r"><?php echo number_format($it['unit_price'], 2); ?></td>
                        <td class="r font-bold"><?php echo number_format($it['total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <table class="totals-table">
            <tr>
                <td class="totals-label">Subtotal</td>
                <td class="totals-val"><?php echo $cur; ?> <?php echo number_format($q['subtotal'], 2); ?></td>
            </tr>
            
            <?php if ($q['tax_percent'] > 0): ?>
            <tr>
                <td class="totals-label">Tax (<?php echo floatval($q['tax_percent']); ?>%)</td>
                <td class="totals-val"><?php echo $cur; ?> <?php echo number_format($q['tax_amount'], 2); ?></td>
            </tr>
            <?php endif; ?>
            
            <?php if ($q['discount_amount'] > 0): ?>
            <tr>
                <td class="totals-label">Discount</td>
                <td class="totals-val" style="color: #dc2626;">- <?php echo $cur; ?> <?php echo number_format($q['discount_amount'], 2); ?></td>
            </tr>
            <?php endif; ?>
            
            <tr class="grand-total">
                <td class="totals-label" style="color:#fff;">GRAND TOTAL</td>
                <td class="totals-val"><?php echo $cur; ?> <?php echo number_format($q['total_amount'], 2); ?></td>
            </tr>
        </table>
        
        <div style="clear: both; height: 20px;"></div>

        <?php if (!empty($q['terms'])): ?>
        <div class="content-block" style="page-break-inside: avoid;">
            <div class="block-title">Terms & Conditions</div>
            <div class="html-content" style="background: #f8f9fb; padding: 15px; border-left: 3px solid #024442;">
                <?php echo $q['terms']; ?>
            </div>
        </div>
        <?php endif; ?>
<!--
        <table class="signatures-table">
            <tr>
                <td width="50%">
                    <div class="sign-box">
                        <div class="block-title" style="border:none; margin:0;">Authorized By</div>
                        <div class="sign-line"></div>
                        <?php $sigName = !empty($q['signature_name']) ? $q['signature_name'] : 'Graphicafix Team'; ?>
                        <div class="sign-name"><?php echo htmlspecialchars($sigName); ?></div>
                        
                        <?php $sigTitle = (!empty($q['signature_title'])) ? $q['signature_title'] : 'Authorized Representative'; ?>
                        <div class="sign-title"><?php echo htmlspecialchars($sigTitle); ?></div>
                    </div>
                </td>
                <td width="50%" align="right">
                    <div class="sign-box" style="text-align: left;">
                        <div class="block-title" style="border:none; margin:0;">Client Acceptance</div>
                        <div class="sign-line"></div>
                        <div class="sign-name"><?php echo htmlspecialchars($q['client_name']); ?></div>
                        <div class="sign-title">Signature & Date</div>
                    </div>
                </td>
            </tr>
        </table>
-->
    </div>

</body>
</html>
<?php
$html = ob_get_clean();

// mPDF configuration for absolute edge-to-edge rendering of headers/footers
$mpdf = new \Mpdf\Mpdf([
    'margin_top' => 35,    // Leave space for the header
    'margin_bottom' => 35, // Leave space for the footer
    'margin_left' => 0,
    'margin_right' => 0,
    'format' => 'A4'
]);

$mpdf->SetTitle('Quotation ' . $q['quotation_number']);
$mpdf->SetAuthor('Graphicafix');
$mpdf->WriteHTML($html);

// Save to disk
$dir = '../quotations/';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$file = $dir . $q['quotation_number'] . '.pdf';
$path = 'quotations/' . $q['quotation_number'] . '.pdf';
$mpdf->Output($file, 'F');

// Update pdf_path in DB
$s = $conn->prepare("UPDATE quotations SET pdf_path=? WHERE id=?");
$s->bind_param("si", $path, $id); 
$s->execute();

// Output to browser
$dest = isset($_GET['dl']) ? 'D' : 'I';
$mpdf->Output('Quotation-' . $q['quotation_number'] . '.pdf', $dest);
exit;
?>