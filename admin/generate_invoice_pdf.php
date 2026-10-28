<?php
/**
 * generate_invoice_pdf.php
 * Requires mPDF: composer require mpdf/mpdf
 */

// Hide errors to prevent 500 crashes
error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/../core/config.php';
include __DIR__ . '/../core/functions.php';

if (!isset($_SESSION['user_id'])) {
    die('Access Denied. Please log in.');
}

// Fetch current user details
$username = $_SESSION['username'];
$user_stmt = $conn->prepare("SELECT role, client_id FROM users WHERE username = ?");
$user_stmt->bind_param("s", $username);
$user_stmt->execute();
$current_user_data = $user_stmt->get_result()->fetch_assoc();
if (!$current_user_data) {
    die('User session invalid.');
}
$role = strtolower($current_user_data['role']);
$client_id = intval($current_user_data['client_id'] ?? 0);

$id = intval($_GET['id'] ?? 0);
if (!$id) die('Invalid ID.');

$stmt = $conn->prepare("SELECT i.*,c.client_name,c.company,c.email,c.phone,c.address FROM invoices i LEFT JOIN clients c ON i.client_id=c.id WHERE i.id=?");
$stmt->bind_param("i",$id); 
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();
if (!$inv) die('Invoice not found.');

// Authorize access
if (in_array($role, ['super_admin', 'superadmin'])) {
    // Allowed
} elseif ($role === 'client' || $role === 'client_sub') {
    if (intval($inv['client_id']) !== $client_id || $client_id <= 0) {
        die('Access Denied. You do not have permission to view this invoice.');
    }
} else {
    die('Access Denied.');
}

$stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order");
$stmt->bind_param("i",$id); 
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$sc = match($inv['status']) {
    'paid'      => '#16a34a',
    'sent'      => '#3b82f6',
    'overdue'   => '#dc2626',
    'cancelled' => '#9ca3af',
    default     => '#6b7280',
};

$cur = htmlspecialchars($inv['currency']);
$currencySymbols = [
    'USD' => '$',
    'PKR' => 'Rs',
    'EUR' => '€',
    'GBP' => '£',
    'AED' => 'AED'
];
$curSym = isset($currencySymbols[$cur]) ? $currencySymbols[$cur] : $cur;

// Helper to format currency
function fmtPDF($val, $code) {
    global $currencySymbols;
    $sym = isset($currencySymbols[$code]) ? $currencySymbols[$code] : $code;
    return $code . ' ' . $sym . ' ' . number_format($val, 2);
}

// Check if any items have line tax/tax code
$hasLineTax = false;
$hasTaxCode = false;
foreach ($items as $it) {
    if (floatval($it['tax_percent'] ?? 0) > 0) {
        $hasLineTax = true;
    }
    if (!empty($it['tax_code'])) {
        $hasTaxCode = true;
    }
}

// Convert amount in words if missing
$amtInWords = trim($inv['amount_in_words'] ?? '');
if (empty($amtInWords)) {
    $amtInWords = amountToWords($inv['total_amount'], $inv['currency']);
} else {
    $amtInWords = str_ireplace([' and 00/100 Only.', ' and 00/100 Only', ' and 00/100', ' 00/100'], [' Only.', ' Only', '', ''], $amtInWords);
}

ob_start(); 
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body {
    font-family: 'DejaVu Sans', Helvetica, sans-serif;
    color: #333333;
    font-size: 10pt;
    line-height: 1.5;
}
.text-primary { color: #024442; }
.font-bold { font-weight: bold; }
table { width: 100%; border-collapse: collapse; }
td { vertical-align: top; }

/* Header Block */
.header-table {
    width: 100%;
    margin-bottom: 25px;
    border-bottom: 2px solid #024442;
    padding-bottom: 15px;
}
.doc-title {
    font-size: 26pt;
    font-weight: bold;
    color: #024442;
    text-transform: uppercase;
    margin: 0;
}
.doc-number {
    font-size: 11pt;
    color: #555555;
    margin-top: 5px;
}
.status-badge {
    background-color: <?php echo $sc; ?>;
    color: #ffffff;
    font-size: 8pt;
    font-weight: bold;
    padding: 3px 10px;
    border-radius: 4px;
    text-transform: uppercase;
    margin-top: 6px;
    display: inline-block;
}

/* Metadata */
.meta-table {
    width: 100%;
    margin-bottom: 25px;
}
.meta-heading {
    font-size: 8.5pt;
    font-weight: bold;
    color: #888888;
    text-transform: uppercase;
    margin-bottom: 6px;
    letter-spacing: 0.5px;
}
.client-name {
    font-size: 13pt;
    font-weight: bold;
    color: #024442;
    margin-bottom: 4px;
}
.client-details {
    font-size: 9.5pt;
    color: #444444;
    line-height: 1.4;
}
.invoice-details-table td {
    padding: 3px 0;
    font-size: 9.5pt;
}
.invoice-details-label {
    color: #666666;
}
.invoice-details-value {
    font-weight: bold;
    color: #333333;
    text-align: right;
}

/* Content Blocks */
.section-title {
    font-size: 11pt;
    font-weight: bold;
    color: #024442;
    border-bottom: 1.5px solid #024442;
    padding-bottom: 5px;
    margin-top: 20px;
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Items Table */
.items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}
.items-table th {
    background-color: #024442;
    color: #ffffff;
    font-size: 9pt;
    font-weight: bold;
    text-transform: uppercase;
    padding: 8px 10px;
    border: 1px solid #024442;
    text-align: left;
    white-space: nowrap;
}
.items-table th.c, .items-table td.c { text-align: center; }
.items-table th.r, .items-table td.r { text-align: right; }
.items-table td {
    padding: 9px 10px;
    border: 1px solid #e5e7eb;
    font-size: 9pt;
    color: #333333;
}
.items-table tbody tr:nth-child(even) {
    background-color: #f9fafb;
}

/* Remittance Block */
.remittance-box {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 12px;
    margin-top: 15px;
    margin-bottom: 20px;
}
.remittance-title {
    font-weight: bold;
    font-size: 9.5pt;
    color: #024442;
    margin-bottom: 8px;
    text-transform: uppercase;
}
.remittance-table td {
    padding: 3px 6px;
    font-size: 9pt;
}

/* Totals Box */
.totals-table {
    width: 320px;
    float: right;
    margin-bottom: 20px;
}
.totals-table td {
    padding: 6px 10px;
    font-size: 9.5pt;
}
.totals-table .totals-label {
    color: #666666;
    text-align: right;
}
.totals-table .totals-val {
    font-weight: bold;
    text-align: right;
}
.totals-table .grand-total-row td {
    background-color: #024442;
    color: #ffffff;
    font-size: 11pt;
    border: none;
    padding: 10px;
}
.totals-table .grand-total-row .totals-val {
    color: #e8c97a;
}
.totals-table .due-row td {
    color: #dc2626;
    font-weight: bold;
}

/* Amount in Words */
.amount-in-words-box {
    border: 1px solid #e5e7eb;
    background-color: #f9fafb;
    padding: 10px 12px;
    font-weight: bold;
    color: #024442;
    font-size: 9.5pt;
    margin-bottom: 25px;
    border-radius: 6px;
}

/* Signatures Table */
.signatures-table {
    width: 100%;
    margin-top: 30px;
}
.sig-box {
    width: 250px;
}
.sig-line {
    border-bottom: 1px solid #333333;
    height: 45px;
    margin-bottom: 6px;
}
.sig-name {
    font-weight: bold;
    color: #024442;
    font-size: 10pt;
}
.sig-title {
    color: #666666;
    font-size: 8.5pt;
}
</style>
</head>
<body>

    <!-- Edge-to-edge running header and footer images -->
    <htmlpageheader name="pageHeader">
        <div style="width: 100%;">
            <img src="<?php echo __DIR__ . '/../assets/images/letter_header.png'; ?>" style="width: 100%; margin: 0; padding: 0;" />
        </div>
    </htmlpageheader>
    <sethtmlpageheader name="pageHeader" page="O" value="on" show-this-page="1" />

    <htmlpagefooter name="pageFooter">
        <div style="width: 100%;">
            <img src="<?php echo __DIR__ . '/../assets/images/letter_footer.png'; ?>" style="width: 100%; margin: 0; padding: 0;" />
        </div>
    </htmlpageheader>
    <sethtmlpagefooter name="pageFooter" page="O" value="on" show-this-page="1" />

    <!-- 2cm content left & right padding -->
    <div style="padding: 10px 20mm 20px 20mm;">

        <!-- Header Table -->
        <table class="header-table">
            <tr>
                <td width="50%">&nbsp;</td>
                <td width="50%" style="text-align: right; vertical-align: bottom;">
                    <h1 class="doc-title">INVOICE</h1>
                    <div class="doc-number">Invoice #: <strong><?php echo htmlspecialchars($inv['invoice_number']); ?></strong></div>
                    <div class="status-badge"><?php echo strtoupper($inv['status']); ?></div>
                </td>
            </tr>
        </table>

        <!-- Metadata Section -->
        <table class="meta-table">
            <tr>
                <td width="55%">
                    <div class="meta-heading">Bill To</div>
                    <div class="client-name"><?php echo htmlspecialchars($inv['client_name']); ?></div>
                    <div class="client-details">
                        <?php if (!empty($inv['company'])) echo '<strong>' . htmlspecialchars($inv['company']) . '</strong><br>'; ?>
                        <?php if (!empty($inv['address'])) echo nl2br(htmlspecialchars($inv['address'])) . '<br>'; ?>
                        <?php if (!empty($inv['email'])) echo 'Email: ' . htmlspecialchars($inv['email']) . '<br>'; ?>
                        <?php if (!empty($inv['phone'])) echo 'Phone: ' . htmlspecialchars($inv['phone']) . '<br>'; ?>
                        <?php if (!empty($inv['client_tax_id'])): ?>
                            <strong>Tax ID/VAT:</strong> <?php echo htmlspecialchars($inv['client_tax_id']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($inv['client_ref_rfq'])): ?>
                            <strong>PO/Reference:</strong> <?php echo htmlspecialchars($inv['client_ref_rfq']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($inv['ship_to'])): ?>
                            <br><strong>Ship To:</strong><br>
                            <?php echo nl2br(htmlspecialchars($inv['ship_to'])); ?><br>
                        <?php endif; ?>
                    </div>
                </td>
                <td width="45%">
                    <div class="meta-heading">Invoice Details</div>
                    <table class="invoice-details-table">
                        <tr>
                            <td class="invoice-details-label">Issue Date:</td>
                            <td class="invoice-details-value"><?php echo date('F j, Y', strtotime($inv['issue_date'])); ?></td>
                        </tr>
                        <tr>
                            <td class="invoice-details-label">Due Date:</td>
                            <td class="invoice-details-value"><?php echo date('F j, Y', strtotime($inv['due_date'])); ?></td>
                        </tr>
                        <tr>
                            <td class="invoice-details-label">Currency:</td>
                            <td class="invoice-details-value"><?php echo $cur; ?> (<?php echo $curSym; ?>)</td>
                        </tr>
                        <?php if ($inv['status'] === 'paid' && !empty($inv['paid_at'])): ?>
                        <tr>
                            <td class="invoice-details-label">Paid Date:</td>
                            <td class="invoice-details-value"><?php echo date('F j, Y', strtotime($inv['paid_at'])); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Pricing Breakdown -->
        <div class="section-title">Itemized Details</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%" class="c">#</th>
                    <th width="40%">Description</th>
                    <th width="8%" class="c">Qty</th>
                    <th width="10%" class="c">Unit</th>
                    <?php if ($hasTaxCode): ?>
                        <th width="12%">Tax Code</th>
                    <?php endif; ?>
                    <th width="15%" class="r">Unit Price</th>
                    <?php if ($hasLineTax): ?>
                        <th width="10%" class="r">Tax %</th>
                    <?php endif; ?>
                    <th width="20%" class="r">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $it): ?>
                <tr>
                    <td class="c"><?php echo $idx + 1; ?></td>
                    <td><?php echo htmlspecialchars($it['description']); ?></td>
                    <td class="c"><?php echo floatval($it['quantity']); ?></td>
                    <td class="c"><?php echo htmlspecialchars($it['unit'] ?? 'pcs'); ?></td>
                    <?php if ($hasTaxCode): ?>
                        <td><?php echo htmlspecialchars($it['tax_code'] ?? '—'); ?></td>
                    <?php endif; ?>
                    <td class="r"><?php echo number_format($it['unit_price'], 2); ?></td>
                    <?php if ($hasLineTax): ?>
                        <td class="r"><?php echo floatval($it['tax_percent']) > 0 ? floatval($it['tax_percent'])."%" : "—"; ?></td>
                    <?php endif; ?>
                    <td class="r font-bold"><?php echo number_format($it['total'] + (floatval($it['tax_amount'] ?? 0)), 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals Block -->
        <table class="totals-table">
            <tr>
                <td class="totals-label">Subtotal</td>
                <td class="totals-val"><?php echo fmtPDF($inv['subtotal'], $inv['currency']); ?></td>
            </tr>
            <?php if ($inv['tax_amount'] > 0): ?>
            <tr>
                <td class="totals-label">Tax Total</td>
                <td class="totals-val"><?php echo fmtPDF($inv['tax_amount'], $inv['currency']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($inv['discount_amount'] > 0): ?>
            <tr>
                <td class="totals-label">Discount</td>
                <td class="totals-val" style="color: #b91c1c;">- <?php echo fmtPDF($inv['discount_amount'], $inv['currency']); ?></td>
            </tr>
            <?php endif; ?>
            <tr class="grand-total-row">
                <td class="totals-label" style="color:#ffffff;">GRAND TOTAL</td>
                <td class="totals-val"><?php echo fmtPDF($inv['total_amount'], $inv['currency']); ?></td>
            </tr>
            <?php if ($inv['amount_paid'] > 0): ?>
            <tr>
                <td class="totals-label">Amount Paid</td>
                <td class="totals-val" style="color:#16a34a;"><?php echo fmtPDF($inv['amount_paid'], $inv['currency']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($inv['amount_due'] > 0): ?>
            <tr class="due-row">
                <td class="totals-label">Amount Due</td>
                <td class="totals-val"><?php echo fmtPDF($inv['amount_due'], $inv['currency']); ?></td>
            </tr>
            <?php endif; ?>
        </table>
        <div style="clear: both; height: 10px;"></div>

        <!-- Amount In Words Block -->
        <div class="amount-in-words-box">
            Amount in Words: <span style="font-weight:normal;color:#333;font-style:italic;"><?php echo $amtInWords; ?></span>
        </div>

        <!-- Remittance Bank Details -->
        <?php if (!empty($inv['bank_name'])): ?>
        <div class="remittance-box">
            <div class="remittance-title">Remittance / Bank Details</div>
            <table class="remittance-table">
                <tr>
                    <td width="22%" class="font-bold">Bank Name:</td>
                    <td width="38%"><?php echo htmlspecialchars($inv['bank_name']); ?></td>
                    <td width="18%" class="font-bold">Account Name:</td>
                    <td width="22%"><?php echo htmlspecialchars($inv['account_name'] ?? 'Graphica Fix'); ?></td>
                </tr>
                <tr>
                    <td class="font-bold">Branch/Location:</td>
                    <td><?php echo htmlspecialchars($inv['bank_branch'] ?? ''); ?></td>
                    <td class="font-bold">Account / IBAN:</td>
                    <td><?php echo htmlspecialchars($inv['account_number']); ?></td>
                </tr>
                <tr>
                    <td class="font-bold">SWIFT / BIC Code:</td>
                    <td><?php echo htmlspecialchars($inv['swift_code'] ?? '—'); ?></td>
                    <td class="font-bold">Payment Methods:</td>
                    <td><?php echo htmlspecialchars($inv['payment_methods'] ?? 'Bank Transfer'); ?></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <!-- Notes / Terms section -->
        <?php if (!empty($inv['notes'])): ?>
        <div style="margin-top:15px; font-size:9.5pt; color:#555555; background:#f9fafb; border-left:3px solid #024442; padding:10px 12px; border-radius:4px; margin-bottom:15px;">
            <strong>Notes:</strong><br>
            <?php echo $inv['notes']; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($inv['terms'])): ?>
        <div style="margin-top:10px; font-size:9.5pt; color:#555555; background:#f9fafb; border-left:3px solid #6b7280; padding:10px 12px; border-radius:4px; margin-bottom:20px;">
            <strong>Terms & Conditions:</strong><br>
            <?php echo nl2br(htmlspecialchars($inv['terms'])); ?>
        </div>
        <?php endif; ?>

        <!-- Signatures block -->
        <table class="signatures-table" style="page-break-inside: avoid;">
            <tr>
                <td width="50%">
                    <div class="sig-box">
                        <div class="section-title" style="border:none; margin:0; padding-bottom:5px; font-size:10pt;">Authorized Signatory</div>
                        <div class="sig-line"></div>
                        <?php $sigName = !empty($inv['signature_name']) ? $inv['signature_name'] : 'Ibadullah'; ?>
                        <div class="sig-name"><?php echo htmlspecialchars($sigName); ?></div>
                        <?php $sigTitle = !empty($inv['signature_title']) ? $inv['signature_title'] : 'Managing Director'; ?>
                        <div class="sig-title"><?php echo htmlspecialchars($sigTitle); ?></div>
                        <div class="sig-title">Date: <?php echo !empty($inv['signature_date']) ? date('F j, Y', strtotime($inv['signature_date'])) : date('F j, Y'); ?></div>
                    </div>
                </td>
                <td width="50%" align="right">&nbsp;</td>
            </tr>
        </table>

    </div>

</body>
</html>
<?php
$html = ob_get_clean();

// mPDF configuration for absolute edge-to-edge rendering of headers/footers
$mpdf = new \Mpdf\Mpdf([
    'margin_top' => 45,    // Leave space for the header
    'margin_bottom' => 35, // Leave space for the footer
    'margin_left' => 0,
    'margin_right' => 0,
    'margin_header' => 0,
    'margin_footer' => 0,
    'format' => 'A4'
]);

$mpdf->SetTitle('Invoice ' . $inv['invoice_number']);
$mpdf->SetAuthor('Graphica Fix');
$mpdf->WriteHTML($html);

// Output to browser
$dest = isset($_GET['dl']) ? 'D' : 'I';
$mpdf->Output('Invoice-' . $inv['invoice_number'] . '.pdf', $dest);
exit;
?>