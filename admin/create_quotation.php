<?php
/**
 * create_quotation.php
 * Simplified 3-Section Quotation Creation Form with Native Tabbed UI
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('location:login.php');
    exit;
}

// Fetch current user role
$username = $_SESSION['username'];
$user_stmt = $conn->prepare("SELECT role FROM users WHERE username = ?");
$user_stmt->bind_param("s", $username);
$user_stmt->execute();
$role_data = $user_stmt->get_result()->fetch_assoc();
$role = strtolower($role_data['role'] ?? 'user');

if (!in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'hrm', 'user'])) {
    header('location:index.php');
    exit;
}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id       = intval($_POST['client_id']      ?? 0) ?: null;
    $client_name     = trim($_POST['client_name']);
    $client_email    = trim($_POST['client_email']    ?? '');
    $client_phone    = trim($_POST['client_phone']    ?? '');
    $client_company  = trim($_POST['client_company']  ?? '');
    $client_address  = trim($_POST['client_address']  ?? '');
    $quot_number     = trim($_POST['quotation_number']);
    $issue_date      = $_POST['issue_date'];
    $valid_until     = $_POST['valid_until'];
    $status          = $_POST['status'] ?? 'draft';
    $currency        = $_POST['currency'] ?? 'PKR';
    $tax_percent     = floatval($_POST['tax_percent']    ?? 0);
    $discount_amount = floatval($_POST['discount_amount'] ?? 0);
    $notes           = trim($_POST['notes']   ?? ''); // Project Overview
    $terms           = trim($_POST['terms']   ?? ''); // Terms & Conditions Override
    
    // Fixed signatory block (moved out of form)
    $sig_name        = 'Ibadullah';
    $sig_title       = 'Managing Director';
    $sig_date        = date('Y-m-d');

    // Section 1 & 2 Meta Fields
    $client_ref_rfq     = trim($_POST['client_ref_rfq'] ?? '');
    $client_tax_id      = trim($_POST['client_tax_id'] ?? '');
    $scope_project_name = trim($_POST['scope_project_name'] ?? '');
    $scope_duration     = trim($_POST['scope_duration'] ?? '');
    $delivery_time      = trim($_POST['delivery_time'] ?? '');
    $amount_in_words    = trim($_POST['amount_in_words'] ?? '');

    // Repeatable bullets imploded to newline-separated strings
    $scope_included = implode("\n", array_filter(array_map('trim', $_POST['scope_included'] ?? [])));
    $scope_excluded = implode("\n", array_filter(array_map('trim', $_POST['scope_excluded'] ?? [])));
    $scope_notes    = implode("\n", array_filter(array_map('trim', $_POST['scope_notes'] ?? [])));

    // Scope of Work Table JSON encoded
    $scope_items = [];
    foreach (($_POST['scope_desc'] ?? []) as $index => $desc) {
        if (empty(trim($desc))) continue;
        $scope_items[] = [
            'description' => trim($desc),
            'details' => trim($_POST['scope_details'][$index] ?? '')
        ];
    }
    $scope_deliverables = json_encode($scope_items, JSON_UNESCAPED_UNICODE);

    // Payment Schedule Table JSON encoded
    $payment_schedule = [];
    foreach (($_POST['payment_milestone'] ?? []) as $index => $milestone) {
        if (empty(trim($milestone))) continue;
        $payment_schedule[] = [
            'milestone' => trim($milestone),
            'amount' => trim($_POST['payment_amount'][$index] ?? '')
        ];
    }
    $payment_terms = json_encode($payment_schedule, JSON_UNESCAPED_UNICODE);

    // Calculate Totals
    $subtotal = 0;
    $items = [];
    foreach (($_POST['items'] ?? []) as $i => $item) {
        if (empty(trim($item['description'] ?? ''))) continue;
        $qty   = floatval($item['quantity']   ?? 1);
        $price = floatval($item['unit_price'] ?? 0);
        $unit  = trim($item['unit'] ?? 'pcs');
        $tax_p = floatval($item['tax_percent'] ?? 0);
        $tax_a = round(($qty * $price) * ($tax_p / 100), 2);
        $tot   = round($qty * $price, 2);
        $subtotal += $tot;
        $items[] = [
            'description' => trim($item['description']),
            'quantity' => $qty,
            'unit' => $unit,
            'unit_price' => $price,
            'tax_percent' => $tax_p,
            'tax_amount' => $tax_a,
            'total' => $tot,
            'sort_order' => (int)$i
        ];
    }

    $tax_amount   = round($subtotal * ($tax_percent / 100), 2);
    $total_amount = round($subtotal + $tax_amount - $discount_amount, 2);

    // Insert statement
    $stmt = $conn->prepare("INSERT INTO quotations
        (quotation_number,client_id,client_name,client_email,client_phone,client_company,client_address,
         issue_date,valid_until,status,subtotal,tax_percent,tax_amount,discount_amount,total_amount,
         currency,notes,terms,signature_name,signature_title,signature_date,
         client_ref_rfq,client_tax_id,scope_project_name,scope_deliverables,scope_duration,
         scope_included,scope_excluded,payment_terms,delivery_time,amount_in_words,scope_notes,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    
    $stmt->bind_param("sissssssssdddddsssssssssssssssss",
        $quot_number,$client_id,$client_name,$client_email,$client_phone,$client_company,$client_address,
        $issue_date,$valid_until,$status,$subtotal,$tax_percent,$tax_amount,$discount_amount,$total_amount,
        $currency,$notes,$terms,$sig_name,$sig_title,$sig_date,
        $client_ref_rfq,$client_tax_id,$scope_project_name,$scope_deliverables,$scope_duration,
        $scope_included,$scope_excluded,$payment_terms,$delivery_time,$amount_in_words,$scope_notes
    );
    $stmt->execute();
    $qid = $conn->insert_id;

    // Line items insertion
    $is = $conn->prepare("INSERT INTO quotation_items (quotation_id,description,quantity,unit_price,total,sort_order,unit,tax_percent,tax_amount) VALUES (?,?,?,?,?,?,?,?,?)");
    foreach ($items as $it) {
        $is->bind_param("isdddisdd", $qid, $it['description'], $it['quantity'], $it['unit_price'], $it['total'], $it['sort_order'], $it['unit'], $it['tax_percent'], $it['tax_amount']);
        $is->execute();
    }

    header("Location: quotation_details.php?id={$qid}&gen=1");
    exit;
}

// ── Load clients ──────────────────────────────────────────────────────────────
$clients = $conn->query("SELECT id,client_name,company,email,phone,address FROM clients ORDER BY client_name");
$cData   = [];
while ($c = $clients->fetch_assoc()) $cData[$c['id']] = $c;

// ── Next quotation number ─────────────────────────────────────────────────────
$lastRow = $conn->query("SELECT quotation_number FROM quotations ORDER BY id DESC LIMIT 1")->fetch_assoc();
$nextNum = 'QUO-' . date('Y') . '-001';
if ($lastRow) { 
    preg_match('/(\d+)$/', $lastRow['quotation_number'], $m); 
    $nextNum = 'QUO-' . date('Y') . '-' . str_pad((intval($m[1]??0)+1), 3, '0', STR_PAD_LEFT); 
}

include 'dashboard_header.php';
?>

<div class="height-100">
<div class="page-header">
    <div>
        <h1><i class="fas fa-file-alt"></i> Create Quotation</h1>
        <p>Generate a professional quotation for a client</p>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
        <a href="price_calculator.php" class="btn btn-outline-primary" style="background:#fff; border-color:#024442; color:#024442; font-weight:600;"><i class="fas fa-calculator"></i> Price Calculator</a>
        <a href="manage_invoices.php?tab=quotations" class="btn btn-secondary">← Back</a>
    </div>
</div>

<!-- MP Navigation Tabs switcher -->
<div class="mp-tabs-container" style="margin-bottom: 20px;">
    <div class="mp-tab-buttons">
        <button type="button" class="active" onclick="showTab('cli')"><span class="mp-tab-icon"><i class="fas fa-building"></i></span> Client & Meta</button>
        <button type="button"                onclick="showTab('sco')"><span class="mp-tab-icon"><i class="fas fa-clipboard"></i></span> Scope of Work</button>
        <button type="button"                onclick="showTab('pri')"><span class="mp-tab-icon"><i class="fas fa-dollar-sign"></i></span> Pricing & Payment</button>
    </div>
</div>

<div class="form-container" style="max-width: 1000px; margin: 0 auto; padding-bottom: 50px;">
<form method="POST" id="quotForm">

    <!-- ==================== TAB 1: CLIENT & META ==================== -->
    <div id="cli" class="tab active">
            <div class="form-group">
                <label>Select Client <span class="required">*</span></label>
                <select name="client_id" onchange="autofill(this.value)" required style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #ccc;">
                    <option value="">— Select client —</option>
                    <?php foreach ($cData as $cid => $c): ?>
                        <option value="<?php echo $cid; ?>"><?php echo htmlspecialchars($c['client_name']); ?> <?php echo !empty($c['company']) ? '('.htmlspecialchars($c['company']).')' : ''; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Auto-populated Client details -->
            <div class="form-grid" style="margin-top: 15px;">
                <div class="form-group">
                    <label>Client Name / Contact Person</label>
                    <input type="text" name="client_name" id="fName" required placeholder="Full Name">
                </div>
                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text" name="client_company" id="fCompany" placeholder="Company Name">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="client_email" id="fEmail" placeholder="client@example.com">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="client_phone" id="fPhone" placeholder="+92 300 0000000">
                </div>
            </div>
            <div class="form-group">
                <label>Billing Address</label>
                <textarea name="client_address" id="fAddress" placeholder="Client billing address..." style="min-height: 70px;"></textarea>
            </div>

            <!-- Tax and Reference details -->
            <div class="form-grid">
                <div class="form-group">
                    <label>Client Tax ID / VAT Number</label>
                    <input type="text" name="client_tax_id" id="fClientTaxId" placeholder="e.g. STRN-987654321">
                </div>
                <div class="form-group">
                    <label>Client RFQ / Reference Number</label>
                    <input type="text" name="client_ref_rfq" id="fClientRefRfq" placeholder="e.g. RFQ-2026-004">
                </div>
            </div>

            <hr style="border:none; border-top:1px solid #f0f0f0; margin: 20px 0;">

            <!-- Meta details -->
            <div class="form-grid">
                <div class="form-group">
                    <label>Quotation Number</label>
                    <input type="text" name="quotation_number" value="<?= $nextNum ?>" readonly style="background-color: #f3f4f6; cursor: not-allowed;">
                </div>
                <div class="form-group">
                    <label>Currency</label>
                    <select name="currency" id="fCurrency">
                        <option value="PKR" selected>PKR — Rs (Pakistani Rupee)</option>
                        <option value="USD">USD — $ (US Dollar)</option>
                        <option value="EUR">EUR — € (Euro)</option>
                        <option value="GBP">GBP — £ (British Pound)</option>
                        <option value="AED">AED — AED (UAE Dirham)</option>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Issue Date</label>
                    <input type="date" name="issue_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Valid Until</label>
                    <input type="date" name="valid_until" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Lead Time / Project Duration</label>
                    <input type="text" name="delivery_time" placeholder="e.g. 4-6 weeks from project kick-off" value="">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="draft" selected>Draft</option>
                        <option value="sent">Sent to Client</option>
                    </select>
                </div>
            </div>
    </div>

    <!-- ==================== TAB 2: SCOPE OF WORK ==================== -->
    <div id="sco" class="tab" style="display:none;">
            <div class="form-group">
                <label>Project / Engagement Name <span class="required">*</span></label>
                <input type="text" name="scope_project_name" required placeholder="e.g. Brand Design and Website Development" value="">
            </div>

            <div class="form-group">
                <label>Project Overview (Boilerplate Description)</label>
                <textarea name="notes" style="min-height: 100px;" placeholder="Enter project scope / description overview..."></textarea>
            </div>

            <!-- Notes Repeatable Bullets -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Notes (Repeatable Bullet List)</label>
                <div id="notesBulletsContainer"></div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addNoteBullet()" style="margin-top: 5px;"><i class="fas fa-plus"></i> Add Note</button>
            </div>

            <!-- Scope of Work Table -->
            <div style="margin-bottom: 25px;">
                <label style="font-weight: bold; display: block; margin-bottom: 8px;">Scope of Work Table (JSON Encoded)</label>
                <table class="items-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="width:40%;">Description</th>
                            <th style="width:50%;">Details</th>
                            <th style="width:10%; text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="scopeBody"></tbody>
                </table>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addScopeRow()" style="margin-top: 8px;"><i class="fas fa-plus"></i> Add Scope Row</button>
            </div>

            <!-- Inclusions and Exclusions -->
            <div class="form-grid">
                <div class="form-group">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">Included Deliverables</label>
                    <div id="includedBulletsContainer"></div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addIncludedBullet()" style="margin-top: 5px;"><i class="fas fa-plus"></i> Add Bullet</button>
                </div>

                <div class="form-group">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">Explicit Exclusions</label>
                    <div id="excludedBulletsContainer"></div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addExcludedBullet()" style="margin-top: 5px;"><i class="fas fa-plus"></i> Add Bullet</button>
            </div>
        </div>
    </div>

    <!-- ==================== TAB 3: PRICING & PAYMENT ==================== -->
    <div id="pri" class="tab" style="display:none;">
            
            <!-- Pricing Table -->
            <label style="font-weight: bold; display: block; margin-bottom: 8px;">Pricing Items</label>
            <table class="items-table" style="width:100%;">
                <thead>
                    <tr>
                        <th style="width:38%;">Description</th>
                        <th style="width:10%;">Qty</th>
                        <th style="width:12%;">Unit</th>
                        <th style="width:15%;">Unit Price</th>
                        <th style="width:10%;">Tax % <button type="button" class="btn btn-sm btn-secondary" onclick="toggleTaxColumn()" style="padding:2px 5px;font-size:9px;margin-left:5px;">Toggle</button></th>
                        <th style="width:10%;">Total</th>
                        <th style="width:5%;"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody"></tbody>
            </table>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addRow()" style="margin-top: 8px;"><i class="fas fa-plus"></i> Add Pricing Row</button>

            <!-- Calculations Block -->
            <div style="width:350px; margin-left:auto; margin-top:20px; border-top:2px solid #e5e7eb; padding-top:15px;">
                <table style="width:100%;">
                    <tr>
                        <td style="padding:6px 0; color:#555;">Subtotal</td>
                        <td style="text-align:right; font-weight:bold;" id="tSubtotal">PKR Rs 0.00</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0; color:#555;">Tax Total</td>
                        <td style="text-align:right; font-weight:bold;" id="tTax">PKR Rs 0.00</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0; color:#555;">Discount</td>
                        <td style="text-align:right; font-weight:bold; color:#b91c1c;" id="tDiscount">— PKR Rs 0.00</td>
                    </tr>
                    <tr style="font-size:12pt; border-top:1px solid #e5e7eb; padding-top:10px;">
                        <td style="padding:10px 0; font-weight:bold; color:#024442;">Grand Total</td>
                        <td style="text-align:right; font-weight:bold; color:#024442;" id="tTotal">PKR Rs 0.00</td>
                    </tr>
                </table>
            </div>

            <!-- Tax Rate and Discount Controls -->
            <div class="form-grid" style="margin-top: 15px;">
                <div class="form-group">
                    <label>Global Tax Rate (%)</label>
                    <input type="number" name="tax_percent" id="fTaxPercent" value="0" step="0.01" min="0" oninput="recalc()">
                </div>
                <div class="form-group">
                    <label>Discount Amount</label>
                    <input type="number" name="discount_amount" id="discAmt" value="0" step="0.01" min="0" oninput="recalc()">
                </div>
            </div>

            <!-- Amount in Words -->
            <div style="background:#f9fafb; border: 1px solid #e5e7eb; padding: 12px; border-radius: 6px; font-weight: bold; color: #024442; margin-top:15px; font-size:10pt;">
                Amount in Words: <span style="font-weight:normal; color:#333; font-style:italic;" id="tAmountInWords">Pakistani Rupees Zero Only.</span>
                <input type="hidden" name="amount_in_words" id="amountInWordsInput" value="Pakistani Rupees Zero Only.">
            </div>

            <hr style="border:none; border-top:1px solid #f0f0f0; margin: 25px 0;">

            <!-- Payment Schedule Table -->
            <div style="margin-bottom: 25px;">
                <label style="font-weight: bold; display: block; margin-bottom: 8px;">Payment Schedule</label>
                <table class="items-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="width:50%;">Milestone / Stage</th>
                            <th style="width:40%;">Amount / Percentage</th>
                            <th style="width:10%; text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="paymentBody"></tbody>
                </table>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addPaymentRow()" style="margin-top: 8px;"><i class="fas fa-plus"></i> Add Milestone</button>
            </div>

            <!-- Terms & Conditions -->
            <div class="form-group" style="margin-top:20px;">
                <label style="font-weight:bold; display:block; margin-bottom:8px;"><i class="fas fa-file-alt" style="margin-right:6px;"></i> Terms & Conditions</label>
                <textarea name="terms" style="width:100%; min-height:220px; font-family:monospace; font-size:11px; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">1. Validity: This quotation is valid for 30 days. Prices are subject to revision after expiry.
2. Payment Terms: 50% advance payment required to initiate the project, and 50% prior to final delivery/deployment.
3. Delivery / Lead Time: Timelines are estimates from confirmed order date and receipt of advance payment and assets.
4. Taxes & Duties: All prices are exclusive of taxes unless stated otherwise; client bears any local duties or tax.
5. Warranty: Includes 6 months post-deployment technical support for bug fixes and layout issues.
6. Cancellation & Refunds: Cancelled orders are subject to a fee covering all hours/deliverables completed up to the cancellation date.
7. Force Majeure: Neither party is liable for failure due to natural disasters, war, strikes, or government actions.
8. Confidentiality: Both parties agree to keep pricing, assets, and technical details confidential.
9. Intellectual Property: Ownership of designs, source code, and deliverables transfers to client only upon receipt of full payment.
10. Governing Law & Jurisdiction: Governed by the laws of Pakistan, with disputes subject to exclusive jurisdiction of its courts.
11. Acceptance: Signing, stamping, or issuing a purchase order against this quotation constitutes acceptance of these terms in full.</textarea>
            </div>
    </div>

    <!-- Navigation buttons at bottom of form -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:25px; border-top:1px solid #eee; padding-top:20px; flex-wrap:wrap; gap:12px;">
        <button type="submit" class="btn btn-primary" style="padding:12px 35px; font-size:11pt; font-weight:bold;"><i class="fas fa-save" style="margin-right:6px;"></i> Save & Generate PDF</button>
        <div style="display:flex; gap:10px;">
            <button type="button" class="btn btn-secondary" id="prevBtn" onclick="prevTab()" style="display:none; padding:10px 24px; font-weight:600;">← Previous</button>
            <button type="button" class="btn btn-secondary" id="nextBtn" onclick="nextTab()" style="padding:10px 24px; font-weight:600;">Next →</button>
        </div>
    </div>
</form>
</div>
</div>

<datalist id="unitList">
    <option value="pcs">pcs</option>
    <option value="hours">hours</option>
    <option value="days">days</option>
    <option value="lot">lot</option>
    <option value="flat rate">flat rate</option>
</datalist>

<script>
// ── Native Tab navigation switcher ───────────────────────────────────────────
const TABS = ['cli', 'sco', 'pri'];
let tabIdx = 0;
function showTab(id) {
    TABS.forEach(t => {
        const el = document.getElementById(t);
        if (el) {
            el.style.display = (t === id) ? 'block' : 'none';
            if (t === id) el.classList.add('active');
            else el.classList.remove('active');
        }
    });
    
    const btns = document.querySelectorAll('.mp-tab-buttons button');
    btns.forEach(b => b.classList.remove('active'));
    const btn = Array.from(btns).find(b => b.getAttribute('onclick')?.includes(id));
    if (btn) btn.classList.add('active');
    
    tabIdx = TABS.indexOf(id);
    
    // Update next/prev button visibilities
    document.getElementById('prevBtn').style.display = (tabIdx === 0) ? 'none' : 'inline-block';
    document.getElementById('nextBtn').style.display = (tabIdx === TABS.length - 1) ? 'none' : 'inline-block';
}

function nextTab() {
    if (tabIdx < TABS.length - 1) {
        showTab(TABS[tabIdx + 1]);
    }
}

function prevTab() {
    if (tabIdx > 0) {
        showTab(TABS[tabIdx - 1]);
    }
}

const cData = <?php echo json_encode($cData); ?>;
function autofill(val) {
    if (!val) {
        document.getElementById('fName').value = '';
        document.getElementById('fCompany').value = '';
        document.getElementById('fEmail').value = '';
        document.getElementById('fPhone').value = '';
        document.getElementById('fAddress').value = '';
        return;
    }
    const c = cData[val];
    document.getElementById('fName').value = c.client_name || '';
    document.getElementById('fCompany').value = c.company     || '';
    document.getElementById('fEmail').value = c.email       || '';
    document.getElementById('fPhone').value = c.phone       || '';
    document.getElementById('fAddress').value = c.address     || '';
}

// Global variable to keep track of hiding/showing tax columns
let showTax = true;
function toggleTaxColumn() {
    showTax = !showTax;
    document.querySelectorAll('[name$="[tax_percent]"]').forEach(input => {
        input.style.display = showTax ? 'inline-block' : 'none';
    });
}

// Currency translation logic
function getSelectedCurrency() {
    return document.getElementById('fCurrency')?.value || 'PKR';
}

function jsNumberToWords(number) {
    const dictionary = {
        0: 'Zero', 1: 'One', 2: 'Two', 3: 'Three', 4: 'Four', 5: 'Five', 6: 'Six', 7: 'Seven', 8: 'Eight', 9: 'Nine',
        10: 'Ten', 11: 'Eleven', 12: 'Twelve', 13: 'Thirteen', 14: 'Fourteen', 15: 'Fifteen', 16: 'Sixteen',
        17: 'Seventeen', 18: 'Eighteen', 19: 'Nineteen', 20: 'Twenty', 30: 'Thirty', 40: 'Forty', 50: 'Fifty',
        60: 'Sixty', 70: 'Seventy', 80: 'Eighty', 90: 'Ninety'
    };
    if (number < 21) return dictionary[number];
    if (number < 100) {
        let tens = Math.floor(number / 10) * 10;
        let units = number % 10;
        return dictionary[tens] + (units ? ' ' + dictionary[units] : '');
    }
    if (number < 1000) {
        let hundreds = Math.floor(number / 100);
        let remainder = number % 100;
        return dictionary[hundreds] + ' Hundred' + (remainder ? ' and ' + jsNumberToWords(remainder) : '');
    }
    if (number < 1000000) {
        let thousands = Math.floor(number / 1000);
        let remainder = number % 1000;
        return jsNumberToWords(thousands) + ' Thousand' + (remainder ? (remainder < 100 ? ' and ' : ' ') + jsNumberToWords(remainder) : '');
    }
    if (number < 1000000000) {
        let millions = Math.floor(number / 1000000);
        let remainder = number % 1000000;
        return jsNumberToWords(millions) + ' Million' + (remainder ? ' ' + jsNumberToWords(remainder) : '');
    }
    return number.toString();
}

function jsAmountToWords(amount, currencyCode) {
    amount = Math.round(amount * 100) / 100;
    let whole = Math.floor(amount);
    let cents = Math.round((amount - whole) * 100);
    
    const currencyNames = {
        'USD': 'US Dollars',
        'PKR': 'Pakistani Rupees',
        'EUR': 'Euros',
        'GBP': 'British Pounds',
        'AED': 'UAE Dirhams'
    };
    
    let currencyName = currencyNames[currencyCode] || currencyCode;
    let wholeWords = jsNumberToWords(whole) || 'Zero';
    
    if (cents === 0) {
        return currencyName + ' ' + wholeWords + ' Only.';
    } else {
        let centsString = cents.toString().padStart(2, '0') + '/100';
        return currencyName + ' ' + wholeWords + ' and ' + centsString + ' Only.';
    }
}

// Dynamic elements addition
let rowCnt = 0;
function addRow(desc = '', qty = 1, unit = 'pcs', price = '') {
    const i = rowCnt++;
    const tr = document.createElement('tr');
    tr.id = 'r' + i;
    tr.innerHTML = `
        <td><input type="text"   name="items[${i}][description]" value="${(desc || '').replace(/"/g, '&quot;')}" placeholder="Service description" required style="width:100%; border:1px solid #ccc; padding:6px; border-radius:4px;"></td>
        <td><input type="number" name="items[${i}][quantity]"    value="${qty || 1}"  step="0.01" min="0" oninput="calcRow(${i})" style="width:100%; border:1px solid #ccc; padding:6px; border-radius:4px;"></td>
        <td><input type="text"   name="items[${i}][unit]"        value="${unit || 'pcs'}" list="unitList" style="width:100%; border:1px solid #ccc; padding:6px; border-radius:4px;"></td>
        <td><input type="number" name="items[${i}][unit_price]"  value="${price !== '' ? price : ''}"   step="0.01" min="0" placeholder="0.00" oninput="calcRow(${i})" required style="width:100%; border:1px solid #ccc; padding:6px; border-radius:4px;"></td>
        <td><input type="number" name="items[${i}][tax_percent]" value="0"  step="0.01" min="0" max="100" oninput="calcRow(${i})" style="width:100%; border:1px solid #ccc; padding:6px; border-radius:4px; display: ${showTax ? 'inline-block' : 'none'};"></td>
        <td class="row-total" id="rt${i}" style="font-weight:bold; vertical-align:middle; text-align:right;">PKR Rs 0.00</td>
        <td style="text-align:center;"><button type="button" class="remove-row-btn" onclick="removeRow(${i})"><i class="fas fa-times"></i></button></td>
    `;
    document.getElementById('itemsBody').appendChild(tr);
    calcRow(i);
}
function removeRow(i) {
    document.getElementById('r' + i)?.remove();
    recalc();
}
function calcRow(i) {
    const q = parseFloat(document.querySelector(`[name="items[${i}][quantity]"]`)?.value  || 0);
    const p = parseFloat(document.querySelector(`[name="items[${i}][unit_price]"]`)?.value || 0);
    const t = parseFloat(document.querySelector(`[name="items[${i}][tax_percent]"]`)?.value || 0);
    const sub = q * p;
    const tax = sub * (t / 100);
    const c = document.getElementById('rt'+i);
    if(c) c.textContent = fmt(sub + tax);
    recalc();
}
function recalc(){
    let sub = 0;
    let totalTax = 0;
    document.querySelectorAll('[name^="items["]').forEach(inp => {
        const m = inp.name.match(/items\[(\d+)\]\[quantity\]/);
        if (m) {
            const q = parseFloat(document.querySelector(`[name="items[${m[1]}][quantity]"]`)?.value || 0);
            const p = parseFloat(document.querySelector(`[name="items[${m[1]}][unit_price]"]`)?.value || 0);
            const t = parseFloat(document.querySelector(`[name="items[${m[1]}][tax_percent]"]`)?.value || 0);
            const rowSub = q * p;
            sub += rowSub;
            totalTax += rowSub * (t / 100);
        }
    });
    
    const disc = parseFloat(document.getElementById('discAmt')?.value || 0);
    const tot = sub + totalTax - disc;
    
    document.getElementById('tSubtotal').textContent = fmt(sub);
    document.getElementById('tTax').textContent = fmt(totalTax);
    document.getElementById('tDiscount').textContent = '— ' + fmt(disc);
    document.getElementById('tTotal').textContent = fmt(tot);
    
    const words = jsAmountToWords(tot, getSelectedCurrency());
    document.getElementById('tAmountInWords').textContent = words;
    document.getElementById('amountInWordsInput').value = words;
}
function fmt(v) {
    const cur = getSelectedCurrency();
    const symbols = {
        'USD': 'USD $',
        'PKR': 'PKR Rs',
        'EUR': 'EUR €',
        'GBP': 'GBP £',
        'AED': 'AED'
    };
    const sym = symbols[cur] || cur;
    return sym + ' ' + Math.max(v,0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
}

// Scope Dynamic Rows
let scopeCnt = 0;
function addScopeRow(desc = '', details = '') {
    const i = scopeCnt++;
    const tr = document.createElement('tr');
    tr.id = 'scope_r' + i;
    tr.innerHTML = `
        <td><input type="text" name="scope_desc[]" value="${desc.replace(/"/g, '&quot;')}" placeholder="e.g. UX/UI Design" required style="width:100%; border:1px solid #ccc; padding:6px; border-radius:4px;"></td>
        <td><input type="text" name="scope_details[]" value="${details.replace(/"/g, '&quot;')}" placeholder="e.g. Wireframes and prototype" required style="width:100%; border:1px solid #ccc; padding:6px; border-radius:4px;"></td>
        <td style="text-align:center;"><button type="button" class="remove-row-btn" onclick="removeScopeRow(${i})"><i class="fas fa-times"></i></button></td>
    `;
    document.getElementById('scopeBody').appendChild(tr);
}
function removeScopeRow(i) {
    document.getElementById('scope_r' + i)?.remove();
}

// Payment Dynamic Rows
let paymentCnt = 0;
function addPaymentRow(milestone = '', amount = '') {
    const i = paymentCnt++;
    const tr = document.createElement('tr');
    tr.id = 'payment_r' + i;
    tr.innerHTML = `
        <td><input type="text" name="payment_milestone[]" value="${milestone.replace(/"/g, '&quot;')}" placeholder="e.g. Advance Booking" style="width:100%; border:1px solid #ccc; padding:6px; border-radius:4px;"></td>
        <td><input type="text" name="payment_amount[]" value="${amount.replace(/"/g, '&quot;')}" placeholder="e.g. 50%" style="width:100%; border:1px solid #ccc; padding:6px; border-radius:4px;"></td>
        <td style="text-align:center;"><button type="button" class="remove-row-btn" onclick="removePaymentRow(${i})"><i class="fas fa-times"></i></button></td>
    `;
    document.getElementById('paymentBody').appendChild(tr);
}
function removePaymentRow(i) {
    document.getElementById('payment_r' + i)?.remove();
}

// Repeatable Bullets Logic
function addIncludedBullet(val = '') {
    const container = document.getElementById('includedBulletsContainer');
    const div = document.createElement('div');
    div.style = "display:flex; align-items:center; gap:8px; margin-bottom:6px;";
    div.innerHTML = `
        <span style="color:#16a34a; font-weight:bold;">✔</span>
        <input type="text" name="scope_included[]" value="${val.replace(/"/g, '&quot;')}" placeholder="Included deliverable" style="flex:1; border:1px solid #ccc; padding:6px; border-radius:4px;">
        <button type="button" class="remove-row-btn" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(div);
}

function addExcludedBullet(val = '') {
    const container = document.getElementById('excludedBulletsContainer');
    const div = document.createElement('div');
    div.style = "display:flex; align-items:center; gap:8px; margin-bottom:6px;";
    div.innerHTML = `
        <span style="color:#b91c1c; font-weight:bold;">✖</span>
        <input type="text" name="scope_excluded[]" value="${val.replace(/"/g, '&quot;')}" placeholder="Excluded items" style="flex:1; border:1px solid #ccc; padding:6px; border-radius:4px;">
        <button type="button" class="remove-row-btn" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(div);
}

function addNoteBullet(val = '') {
    const container = document.getElementById('notesBulletsContainer');
    const div = document.createElement('div');
    div.style = "display:flex; align-items:center; gap:8px; margin-bottom:6px;";
    div.innerHTML = `
        <span style="color:#888; font-weight:bold; font-size:12px;">•</span>
        <input type="text" name="scope_notes[]" value="${val.replace(/"/g, '&quot;')}" placeholder="Standard note" style="flex:1; border:1px solid #ccc; padding:6px; border-radius:4px;">
        <button type="button" class="remove-row-btn" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(div);
}

// Initialize - Ingest data from Sales Price Calculator if available
const calcPrefillRaw = sessionStorage.getItem('graphica_calc_quotation');
if (calcPrefillRaw) {
    try {
        const pData = JSON.parse(calcPrefillRaw);
        sessionStorage.removeItem('graphica_calc_quotation');

        if (pData.clientId) {
            const clSel = document.querySelector('select[name="client_id"]');
            if (clSel) {
                clSel.value = pData.clientId;
                if (typeof autofill === 'function') autofill(pData.clientId);
            }
        }
        if (pData.clientName) {
            const fn = document.getElementById('fName');
            if (fn) fn.value = pData.clientName;
        }
        if (pData.clientCompany) {
            const fc = document.getElementById('fCompany');
            if (fc) fc.value = pData.clientCompany;
        }
        if (pData.projectName) {
            const pn = document.querySelector('input[name="scope_project_name"]');
            if (pn) pn.value = pData.projectName;
        }
        if (pData.currency && pData.currency === 'USD') {
            const curSel = document.getElementById('fCurrency');
            if (curSel) curSel.value = 'USD';
        }

        // Add Scope Deliverables
        if (pData.scopeDeliverables && pData.scopeDeliverables.length > 0) {
            const sb = document.getElementById('scopeBody');
            if (sb) sb.innerHTML = '';
            pData.scopeDeliverables.forEach(sd => {
                addScopeRow(sd.description, sd.details);
            });
        }

        // Add Line Items
        if (pData.items && pData.items.length > 0) {
            const ib = document.getElementById('itemsBody');
            if (ib) ib.innerHTML = '';
            pData.items.forEach(it => {
                addRow(it.description, it.quantity, it.unit, it.price);
            });
        } else {
            addRow();
        }
    } catch(err) {
        console.error("Error loading calculator prefill:", err);
        addRow();
    }
} else {
    addRow();
}

recalc();
showTab('cli');

document.getElementById('fCurrency')?.addEventListener('change', recalc);

document.getElementById('quotForm').addEventListener('submit', function(e) {
    if (!document.getElementById('fName').value.trim()) {
        e.preventDefault();
        alert('Client name is required.');
        showTab('cli');
    }
});
</script>

<?php include 'dashboard_footer.php'; ?>