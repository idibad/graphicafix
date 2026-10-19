<?php
/**
 * generate_quotation_pdf.php
 * PDF Generator for Quotations using mPDF
 */

// Hide errors to prevent PDF stream corruption
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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) die('Invalid ID.');

$stmt = $conn->prepare("SELECT * FROM quotations WHERE id=?");
$stmt->bind_param("i", $id); 
$stmt->execute();
$q = $stmt->get_result()->fetch_assoc();
if (!$q) die('Quotation not found.');

// Authorize access
if (in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'hrm', 'user'])) {
    // Allowed
} elseif ($role === 'client' || $role === 'client_sub') {
    if (intval($q['client_id']) !== $client_id || $client_id <= 0) {
        die('Access Denied. You do not have permission to view this quotation.');
    }
} else {
    die('Access Denied.');
}

$stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id=? ORDER BY sort_order");
$stmt->bind_param("i", $id); 
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$status = strtolower($q['status']);
$cur = htmlspecialchars($q['currency']);

// Currency Symbols dictionary - using standard printable glyphs
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

// Check if any items have line tax
$hasLineTax = false;
foreach ($items as $it) {
    if (floatval($it['tax_percent'] ?? 0) > 0) {
        $hasLineTax = true;
        break;
    }
}

// Convert amount in words if missing
$amtInWords = trim($q['amount_in_words'] ?? '');
if (empty($amtInWords)) {
    $amtInWords = amountToWords($q['total_amount'], $q['currency']);
} else {
    $amtInWords = str_ireplace([' and 00/100 Only.', ' and 00/100 Only', ' and 00/100', ' 00/100'], [' Only.', ' Only', '', ''], $amtInWords);
}

// Decode Scope of Work items
$scopeItems = [];
if (!empty($q['scope_deliverables'])) {
    $decoded = json_decode($q['scope_deliverables'], true);
    if (is_array($decoded)) {
        $scopeItems = $decoded;
    } else {
        $scopeItems = [['description' => 'Scope of Work Summary', 'details' => $q['scope_deliverables']]];
    }
}

// Decode Payment Schedule
$paymentSchedule = [];
if (!empty($q['payment_terms'])) {
    $decoded = json_decode($q['payment_terms'], true);
    if (is_array($decoded)) {
        $paymentSchedule = $decoded;
    } else {
        $paymentSchedule = [['milestone' => 'Standard Payment Terms', 'amount' => $q['payment_terms']]];
    }
}

// Split deliverables
$included_list = array_filter(array_map('trim', explode("\n", $q['scope_included'] ?? '')));
$excluded_list = array_filter(array_map('trim', explode("\n", $q['scope_excluded'] ?? '')));

// Split repeatable Notes
$scope_notes_list = [];
if (!empty($q['scope_notes'])) {
    $scope_notes_list = array_filter(array_map('trim', explode("\n", $q['scope_notes'])));
}

// Terms & Conditions Override fallback to standard 11 clauses
$termsText = trim($q['terms'] ?? '');
if (empty($termsText)) {
    $termsText = "1. Validity: This quotation is valid for 30 days. Prices are subject to revision after expiry.\n" .
                 "2. Payment Terms: 50% advance payment required to initiate the project, and 50% prior to final delivery/deployment.\n" .
                 "3. Delivery / Lead Time: Timelines are estimates from confirmed order date and receipt of advance payment and assets.\n" .
                 "4. Taxes & Duties: All prices are exclusive of taxes unless stated otherwise; client bears any local duties or tax.\n" .
                 "5. Warranty: Includes 6 months post-deployment technical support for bug fixes and layout issues.\n" .
                 "6. Cancellation & Refunds: Cancelled orders are subject to a fee covering all hours/deliverables completed up to the cancellation date.\n" .
                 "7. Force Majeure: Neither party is liable for failure due to natural disasters, war, strikes, or government actions.\n" .
                 "8. Confidentiality: Both parties agree to keep pricing, assets, and technical details confidential.\n" .
                 "9. Intellectual Property: Ownership of designs, source code, and deliverables transfers to client only upon receipt of full payment.\n" .
                 "10. Governing Law & Jurisdiction: Governed by the laws of Pakistan, with disputes subject to exclusive jurisdiction of its courts.\n" .
                 "11. Acceptance: Signing, stamping, or issuing a purchase order against this quotation constitutes acceptance of these terms in full.";
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
.content-body {
    margin-left: 20mm;
    margin-right: 20mm;
    margin-top: 10px;
}
.text-primary { color: #024442; }
.font-bold { font-weight: bold; }
table { width: 100%; border-collapse: collapse; }
td { vertical-align: top; }

/* Header Block */
.header-table {
    width: 100%;
    margin-bottom: 20px;
    border-bottom: 2px solid #024442;
    padding-bottom: 12px;
}
.doc-title {
    font-size: 24pt;
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
    background-color: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
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
    margin-bottom: 20px;
}
.meta-heading {
    font-size: 8.5pt;
    font-weight: bold;
    color: #888888;
    text-transform: uppercase;
    margin-bottom: 5px;
    letter-spacing: 0.5px;
}
.client-name {
    font-size: 12pt;
    font-weight: bold;
    color: #024442;
    margin-bottom: 4px;
}
.client-details {
    font-size: 9pt;
    color: #444444;
    line-height: 1.4;
}
.quote-details-table td {
    padding: 3px 0;
    font-size: 9pt;
}
.quote-details-label {
    color: #666666;
}
.quote-details-value {
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
    padding-bottom: 4px;
    margin-top: 15px;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.scope-description {
    font-size: 9pt;
    color: #444444;
    line-height: 1.4;
    margin-bottom: 12px;
}
.scope-grid {
    width: 100%;
    margin-top: 10px;
    margin-bottom: 15px;
}
.scope-col {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 10px;
}
.scope-col-title {
    font-weight: bold;
    font-size: 9pt;
    margin-bottom: 6px;
}
.scope-list {
    margin: 0;
    padding-left: 12px;
    font-size: 8.5pt;
    color: #444444;
}
.scope-list li {
    margin-bottom: 4px;
}

/* Items Table */
.items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
}
.items-table th {
    background-color: #024442;
    color: #ffffff;
    font-size: 9pt;
    font-weight: bold;
    text-transform: uppercase;
    padding: 7px 9px;
    border: 1px solid #024442;
    text-align: left;
    white-space: nowrap;
}
.items-table th.c, .items-table td.c { text-align: center; }
.items-table th.r, .items-table td.r { text-align: right; }
.items-table td {
    padding: 8px 9px;
    border: 1px solid #e5e7eb;
    font-size: 8.5pt;
    color: #333333;
}
.items-table tbody tr:nth-child(even) {
    background-color: #f9fafb;
}

/* Totals Box */
.totals-table {
    width: 320px;
    float: right;
    margin-bottom: 15px;
}
.totals-table td {
    padding: 5px 8px;
    font-size: 9pt;
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
    font-size: 10.5pt;
    border: none;
    padding: 8px;
}
.totals-table .grand-total-row .totals-val {
    color: #e8c97a;
}

/* Amount in Words */
.amount-in-words-box {
    border: 1px solid #e5e7eb;
    background-color: #f9fafb;
    padding: 8px 10px;
    font-weight: bold;
    color: #024442;
    font-size: 9pt;
    margin-bottom: 20px;
    border-radius: 6px;
}

/* Terms & Legal page */
.terms-page {
    page-break-before: always;
}
.terms-text {
    font-size: 8pt;
    color: #444444;
    line-height: 1.5;
}

/* Signatures Table */
.signatures-table {
    width: 100%;
    margin-top: 25px;
}
.sig-box {
    width: 250px;
}
.sig-line {
    border-bottom: 1px solid #333333;
    height: 40px;
    margin-bottom: 5px;
}
.sig-name {
    font-weight: bold;
    color: #024442;
    font-size: 9.5pt;
}
.sig-title {
    color: #666666;
    font-size: 8pt;
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
    </htmlpagefooter>
    <sethtmlpagefooter name="pageFooter" page="O" value="on" show-this-page="1" />

    <!-- Left & Right page margin wrapper -->
    <div class="content-body">

        <!-- Header Table -->
        <table class="header-table">
            <tr>
                <td width="50%">&nbsp;</td>
                <td width="50%" style="text-align: right; vertical-align: bottom;">
                    <h1 class="doc-title">QUOTATION</h1>
                    <div class="doc-number">Ref #: <strong><?php echo htmlspecialchars($q['quotation_number']); ?></strong></div>
                    <?php if ($status === 'draft'): ?>
                        <div class="status-badge">DRAFT</div>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <!-- Metadata Section -->
        <table class="meta-table">
            <tr>
                <td width="55%">
                    <div class="meta-heading">Prepared For</div>
                    <div class="client-name"><?php echo htmlspecialchars($q['client_name']); ?></div>
                    <div class="client-details">
                        <?php if (!empty($q['client_company'])) echo '<strong>' . htmlspecialchars($q['client_company']) . '</strong><br>'; ?>
                        <?php if (!empty($q['client_address'])) echo nl2br(htmlspecialchars($q['client_address'])) . '<br>'; ?>
                        <?php if (!empty($q['client_email'])) echo 'Email: ' . htmlspecialchars($q['client_email']) . '<br>'; ?>
                        <?php if (!empty($q['client_phone'])) echo 'Phone: ' . htmlspecialchars($q['client_phone']) . '<br>'; ?>
                        <?php if (!empty($q['client_tax_id'])): ?>
                            <strong>Tax ID/VAT:</strong> <?php echo htmlspecialchars($q['client_tax_id']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($q['client_ref_rfq'])): ?>
                            <strong>RFQ/Reference:</strong> <?php echo htmlspecialchars($q['client_ref_rfq']); ?><br>
                        <?php endif; ?>
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
                            <td class="quote-details-value"><?php echo $cur; ?> (<?php echo $curSym; ?>)</td>
                        </tr>
                        <?php if (!empty($q['delivery_time'])): ?>
                        <tr>
                            <td class="quote-details-label">Lead Time:</td>
                            <td class="quote-details-value"><?php echo htmlspecialchars($q['delivery_time']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Project Scope of Work Section -->
        <div class="section-title">Project Scope of Work</div>
        <?php if (!empty($q['scope_project_name'])): ?>
            <div style="font-size:10pt; font-weight:bold; color:#024442; margin-bottom:8px;">
                Project Name: <?php echo htmlspecialchars($q['scope_project_name']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($q['notes'])): ?>
            <div class="scope-description">
                <?php echo $q['notes']; ?>
            </div>
        <?php endif; ?>

        <!-- Scope of Work Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="10%" class="c">Sr No</th>
                    <th width="35%">Description</th>
                    <th width="55%">Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($scopeItems)): ?>
                    <tr>
                        <td class="c">1</td>
                        <td>Project Deliverables</td>
                        <td>Custom deliverables as per initial engagement.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($scopeItems as $idx => $item): ?>
                    <tr>
                        <td class="c"><?php echo $idx + 1; ?></td>
                        <td class="font-bold"><?php echo htmlspecialchars($item['description']); ?></td>
                        <td><?php echo htmlspecialchars($item['details']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Scope Inclusions / Exclusions Grid -->
        <?php if (!empty($included_list) || !empty($excluded_list)): ?>
        <table class="scope-grid">
            <tr>
                <td width="48%" class="scope-col" style="margin-right:4%;">
                    <div class="scope-col-title text-primary"><span style="color:#16a34a;">✔</span> Included Deliverables</div>
                    <?php if (!empty($included_list)): ?>
                    <ul class="scope-list">
                        <?php foreach ($included_list as $inc): ?>
                            <li><?php echo htmlspecialchars($inc); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                        <span style="font-size:8.5pt;color:#888;">Standard features only.</span>
                    <?php endif; ?>
                </td>
                <td width="4%"></td>
                <td width="48%" class="scope-col">
                    <div class="scope-col-title text-primary" style="color:#b91c1c;">✖ Explicit Exclusions</div>
                    <?php if (!empty($excluded_list)): ?>
                    <ul class="scope-list">
                        <?php foreach ($excluded_list as $exc): ?>
                            <li><?php echo htmlspecialchars($exc); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                        <span style="font-size:8.5pt;color:#888;">No explicit exclusions defined.</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php endif; ?>

        <!-- Repeatable Important Notes -->
        <?php if (!empty($scope_notes_list)): ?>
        <div style="margin-top: 12px; margin-bottom: 15px;">
            <div style="font-weight: bold; font-size: 9pt; color: #024442; margin-bottom: 5px;">Important Notes:</div>
            <ul style="margin: 0; padding-left: 12px; font-size: 8.5pt; color: #555555; line-height: 1.4;">
                <?php foreach ($scope_notes_list as $note): ?>
                    <li style="margin-bottom: 3px;"><?php echo htmlspecialchars($note); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Page break container: forces pricing block to land on a new page -->
        <div style="page-break-before: always;">

            <!-- Pricing Breakdown -->
            <div class="section-title">Pricing Breakdown</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th width="5%" class="c">#</th>
                        <th width="35%">Description</th>
                        <th width="8%" class="c">Qty</th>
                        <th width="8%" class="c">Unit</th>
                        <th width="18%" class="r">Unit Price</th>
                        <?php if ($hasLineTax): ?>
                            <th width="10%" class="r">Tax %</th>
                        <?php endif; ?>
                        <th width="16%" class="r">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $idx => $it): ?>
                    <tr>
                        <td class="c"><?php echo $idx + 1; ?></td>
                        <td><?php echo htmlspecialchars($it['description']); ?></td>
                        <td class="c"><?php echo floatval($it['quantity']); ?></td>
                        <td class="c"><?php echo htmlspecialchars($it['unit'] ?? 'pcs'); ?></td>
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
                    <td class="totals-val"><?php echo fmtPDF($q['subtotal'], $q['currency']); ?></td>
                </tr>
                <?php if ($q['tax_amount'] > 0): ?>
                <tr>
                    <td class="totals-label">Tax Total</td>
                    <td class="totals-val"><?php echo fmtPDF($q['tax_amount'], $q['currency']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($q['discount_amount'] > 0): ?>
                <tr>
                    <td class="totals-label">Discount</td>
                    <td class="totals-val" style="color: #b91c1c;">- <?php echo fmtPDF($q['discount_amount'], $q['currency']); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="grand-total-row">
                    <td class="totals-label" style="color:#ffffff;">GRAND TOTAL</td>
                    <td class="totals-val"><?php echo fmtPDF($q['total_amount'], $q['currency']); ?></td>
                </tr>
            </table>
            <div style="clear: both; height: 10px;"></div>

            <!-- Amount In Words Block -->
            <div class="amount-in-words-box">
                Amount in Words: <span style="font-weight:normal;color:#333;font-style:italic;"><?php echo $amtInWords; ?></span>
            </div>

            <!-- Payment Schedule Block -->
            <?php if (!empty($paymentSchedule)): ?>
            <div style="margin-top:15px; page-break-inside: avoid;">
                <div class="section-title">Payment Schedule</div>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th width="10%" class="c">Sr No</th>
                            <th width="50%">Milestone / Stage</th>
                            <th width="40%">Amount / Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentSchedule as $idx => $sched): ?>
                        <tr>
                            <td class="c"><?php echo $idx + 1; ?></td>
                            <td class="font-bold"><?php echo htmlspecialchars($sched['milestone']); ?></td>
                            <td><?php echo htmlspecialchars($sched['amount']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Signatures block (After pricing & schedule) -->
            <div style="margin-top: 25px; page-break-inside: avoid;">
                <table class="signatures-table">
                    <tr>
                        <td width="50%">
                            <div class="sig-box">
                                <div class="section-title" style="border:none; margin:0; padding-bottom:5px; font-size:10pt;">Graphica Fix</div>
                                <div class="sig-line"></div>
                                <div class="sig-title">Authorized Signature & Seal</div>
                                <div class="sig-title">Date: ________________________</div>
                            </div>
                        </td>
                        <td width="50%" align="right">
                            <div class="sig-box" style="text-align: left; margin-left: auto;">
                                <div class="section-title" style="border:none; margin:0; padding-bottom:5px; font-size:10pt;">Client</div>
                                <div class="sig-line"></div>
                                <div class="sig-name"><?php echo htmlspecialchars($q['client_name']); ?></div>
                                <div class="sig-title">Authorized Signature & Seal</div>
                                <div class="sig-title">Date: ________________________</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Trailing Terms & Conditions (if exist) -->
        <?php if (!empty($termsText)): ?>
        <div class="terms-page">
            <div class="section-title">Terms & Conditions</div>
            <div class="terms-text" style="background:#f9fafb; border:1px solid #e5e7eb; padding:12px; border-radius:6px; margin-bottom:15px;">
                <?php echo nl2br(htmlspecialchars($termsText)); ?>
            </div>
        </div>
        <?php endif; ?>

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

$mpdf->SetTitle('Quotation ' . $q['quotation_number']);
$mpdf->SetAuthor('Graphica Fix');
$mpdf->WriteHTML($html);

// Save to disk
$dir = __DIR__ . '/../quotations/';
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