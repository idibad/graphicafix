<?php
include 'dashboard_header.php';

// Check permissions
if (!in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'hrm', 'user']) || $role === 'sales') {
    header('location:index.php?error=unauthorized'); exit;
}

// ── Prefill from quotation if converting ──────────────────────────────────────
$prefill = [];
$prefillItems = [];
if (!empty($_GET['from_quot'])) {
    $qid  = intval($_GET['from_quot']);
    $qrow = $conn->query("SELECT * FROM quotations WHERE id={$qid}")->fetch_assoc();
    if ($qrow) {
        $prefill = $qrow;
        $res = $conn->query("SELECT * FROM quotation_items WHERE quotation_id={$qid} ORDER BY sort_order");
        while ($r = $res->fetch_assoc()) {
            $prefillItems[] = $r;
        }
    }
}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id       = intval($_POST['client_id'] ?? 0) ?: null;
    $project_id      = intval($_POST['project_id'] ?? 0) ?: null;
    $invoice_number  = trim($_POST['invoice_number']);
    $issue_date      = $_POST['issue_date'];
    $due_date        = $_POST['due_date'];
    $status          = $_POST['status'] ?? 'draft';
    $tax_percent     = floatval($_POST['tax_percent']    ?? 0);
    $discount_amount = floatval($_POST['discount_amount'] ?? 0);
    $notes           = trim($_POST['notes']  ?? '');
    $terms           = trim($_POST['terms']  ?? '');
    $currency        = $_POST['currency'] ?? 'PKR';
    $from_quot       = intval($_POST['from_quot'] ?? 0);

    // New Fields
    $client_ref_rfq  = trim($_POST['client_ref_rfq'] ?? '');
    $client_tax_id   = trim($_POST['client_tax_id'] ?? '');
    $ship_to         = trim($_POST['ship_to'] ?? '');
    $bank_name       = trim($_POST['bank_name'] ?? '');
    $bank_branch     = trim($_POST['bank_branch'] ?? '');
    $account_name    = trim($_POST['account_name'] ?? '');
    $account_number  = trim($_POST['account_number'] ?? '');
    $swift_code      = trim($_POST['swift_code'] ?? '');
    $payment_methods = trim($_POST['payment_methods'] ?? '');
    $sig_name        = trim($_POST['signature_name'] ?? '');
    $sig_title       = trim($_POST['signature_title'] ?? '');
    $sig_date        = !empty($_POST['signature_date']) ? $_POST['signature_date'] : date('Y-m-d');
    $amount_in_words = trim($_POST['amount_in_words'] ?? '');

    // Build items & subtotal
    $subtotal = 0;
    $total_tax = 0;
    $items = [];
    foreach (($_POST['items'] ?? []) as $i => $item) {
        if (empty(trim($item['description'] ?? ''))) continue;
        $qty   = floatval($item['quantity']   ?? 1);
        $price = floatval($item['unit_price'] ?? 0);
        $unit  = trim($item['unit'] ?? 'pcs');
        $tax_c = trim($item['tax_code'] ?? '');
        $tax_p = floatval($item['tax_percent'] ?? 0);
        $tax_a = round(($qty * $price) * ($tax_p / 100), 2);
        $tot   = round($qty * $price, 2);
        
        $subtotal += $tot;
        $total_tax += $tax_a;
        
        $items[] = [
            'description' => trim($item['description']),
            'quantity' => $qty,
            'unit' => $unit,
            'tax_code' => $tax_c,
            'unit_price' => $price,
            'tax_percent' => $tax_p,
            'tax_amount' => $tax_a,
            'total' => $tot,
            'sort_order' => (int)$i
        ];
    }

    $tax_amount   = $total_tax; // line-item tax sum
    $total_amount = round($subtotal + $tax_amount - $discount_amount, 2);

    // Use manually entered paid amount; if status=paid, force full amount
    if ($status === 'paid') {
        $amount_paid = $total_amount;
    } else {
        $amount_paid = min(floatval($_POST['amount_paid'] ?? 0), $total_amount);
    }
    $amount_due = round($total_amount - $amount_paid, 2);
    $paid_at    = $amount_paid > 0 ? date('Y-m-d H:i:s') : null;

    // Auto-set status to paid if fully paid via amount_paid field
    if ($amount_paid >= $total_amount && $total_amount > 0) {
        $status = 'paid';
    }

    $stmt = $conn->prepare("INSERT INTO invoices
        (invoice_number,client_id,project_id,issue_date,due_date,status,
         subtotal,tax_percent,tax_amount,discount_amount,total_amount,
         amount_paid,amount_due,currency,notes,terms,paid_at,
         client_ref_rfq,client_tax_id,ship_to,bank_name,bank_branch,account_name,
         account_number,swift_code,payment_methods,signature_name,signature_title,signature_date,amount_in_words,
         created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    $stmt->bind_param("siisssdddddddsssssssssssssssss",
        $invoice_number,$client_id,$project_id,$issue_date,$due_date,$status,
        $subtotal,$tax_percent,$tax_amount,$discount_amount,$total_amount,
        $amount_paid,$amount_due,$currency,$notes,$terms,$paid_at,
        $client_ref_rfq,$client_tax_id,$ship_to,$bank_name,$bank_branch,$account_name,
        $account_number,$swift_code,$payment_methods,$sig_name,$sig_title,$sig_date,$amount_in_words
    );
    $stmt->execute();
    $inv_id = $conn->insert_id;

    $is = $conn->prepare("INSERT INTO invoice_items (invoice_id,description,quantity,unit_price,total,sort_order,unit,tax_code,tax_percent,tax_amount) VALUES (?,?,?,?,?,?,?,?,?,?)");
    foreach ($items as $it) {
        $is->bind_param("isdddissdd", $inv_id, $it['description'], $it['quantity'], $it['unit_price'], $it['total'], $it['sort_order'], $it['unit'], $it['tax_code'], $it['tax_percent'], $it['tax_amount']);
        $is->execute();
    }

    if ($from_quot) {
        $conn->query("UPDATE quotations SET converted_invoice_id={$inv_id}, status='accepted' WHERE id={$from_quot}");
    }

    echo "<script>alert('Invoice created successfully!');window.location='invoice_details.php?id={$inv_id}';</script>";
    exit;
}

// ── Dropdowns ─────────────────────────────────────────────────────────────────
$clients  = $conn->query("SELECT id,client_name,company FROM clients ORDER BY client_name");
$projects = $conn->query("SELECT id,project_name FROM projects ORDER BY project_name");

// ── Next invoice number ───────────────────────────────────────────────────────
$lastRow = $conn->query("SELECT invoice_number FROM invoices ORDER BY id DESC LIMIT 1")->fetch_assoc();
$nextNum = 'INV-' . date('Y') . '-001';
if ($lastRow) {
    preg_match('/(\d+)$/', $lastRow['invoice_number'], $m);
    $nextNum = 'INV-' . date('Y') . '-' . str_pad((intval($m[1]??0)+1), 3, '0', STR_PAD_LEFT);
}
?>

<div class="height-100">
<div class="page-header">
    <div>
        <h1><i class="fas fa-plus"></i> Create Invoice</h1>
        <p><?= !empty($prefill) ? "Converted from quotation <strong>" . htmlspecialchars($prefill['quotation_number']) . "</strong>" : "Create a new client invoice" ?></p>
    </div>
    <a href="manage_invoices.php" class="btn btn-secondary">← Back</a>
</div>

<div class="mp-tabs-container">
    <div class="mp-tab-buttons">
        <button type="button" class="active" onclick="showTab('det')"><span class="mp-tab-icon"><i class="fas fa-clipboard"></i></span> Details</button>
        <button type="button"                onclick="showTab('itm')"><span class="mp-tab-icon"><i class="fas fa-edit"></i></span> Line Items</button>
        <button type="button"                onclick="showTab('nts')"><span class="mp-tab-icon"><i class="fas fa-comment"></i></span> Notes & Terms</button>
    </div>
</div>

<div class="form-container">
<form method="POST" id="invForm">
<input type="hidden" name="from_quot" value="<?= intval($_GET['from_quot'] ?? 0) ?>">

<!-- ── Tab 1: Details ──────────────────────────────────────────────────────── -->
<div id="det" class="tab active">
    <div class="form-grid">
        <div class="form-group">
            <label>Invoice Number <span class="required">*</span></label>
            <input type="text" name="invoice_number" value="<?= $nextNum ?>" required>
        </div>
        <div class="form-group">
            <label>Currency</label>
            <select name="currency" id="currSel" onchange="updateCurrency(this.value)">
                <option value="PKR" selected>PKR — Pakistani Rupee</option>
                <option value="USD">USD — US Dollar</option>
                <option value="EUR">EUR — Euro</option>
                <option value="GBP">GBP — British Pound</option>
            </select>
        </div>
    </div>
    <div class="form-group">
        <label>Client <span class="required">*</span></label>
        <select name="client_id" required>
            <option value="">Select a client</option>
            <?php $clients->data_seek(0); while ($c = $clients->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>" <?= (!empty($prefill['client_id']) && $prefill['client_id']==$c['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['client_name']) ?><?= $c['company'] ? ' — '.htmlspecialchars($c['company']) : '' ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="form-grid">
        <div class="form-group">
            <label>Client Tax ID / VAT Registration</label>
            <input type="text" name="client_tax_id" id="fClientTaxId" value="<?= htmlspecialchars($prefill['client_tax_id'] ?? '') ?>" placeholder="e.g. STRN-987654321">
        </div>
        <div class="form-group">
            <label>Client PO / RFQ Reference Number</label>
            <input type="text" name="client_ref_rfq" id="fClientRefRfq" value="<?= htmlspecialchars($prefill['client_ref_rfq'] ?? '') ?>" placeholder="e.g. PO-2026-088">
        </div>
    </div>
    <div class="form-group">
        <label>Ship To / Delivery Address <span style="color:#aaa;font-weight:400;">(optional, only for goods)</span></label>
        <textarea name="ship_to" placeholder="Delivery address..." style="min-height:50px;"></textarea>
    </div>

    <div class="form-group">
        <label>Linked Project <span style="color:#aaa;font-weight:400;">(optional)</span></label>
        <select name="project_id">
            <option value="">None</option>
            <?php while ($p = $projects->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="form-grid">
        <div class="form-group">
            <label>Issue Date <span class="required">*</span></label>
            <input type="date" name="issue_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
            <label>Due Date <span class="required">*</span></label>
            <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
        </div>
    </div>
    <div class="form-grid">
        <div class="form-group" style="display:none;">
            <label>Tax % (Global)</label>
            <input type="number" name="tax_percent" id="taxPct" value="<?= $prefill['tax_percent'] ?? 0 ?>" step="0.01" min="0" max="100" oninput="recalc()">
        </div>
        <div class="form-group">
            <label>Discount Amount</label>
            <input type="number" name="discount_amount" id="discAmt" value="<?= $prefill['discount_amount'] ?? 0 ?>" step="0.01" min="0" oninput="recalc()">
        </div>
    </div>
    <div class="form-group">
        <label>Status</label>
        <select name="status" id="statusSel" onchange="handleStatusChange(this.value)">
            <option value="draft">Draft</option>
            <option value="sent">Sent</option>
            <option value="paid">Paid (Full)</option>
        </select>
    </div>

    <hr style="border:none;border-top:1px solid #f0f0f0;margin:20px 0;">
    <h3 style="margin-bottom:16px;">Remittance Bank Details</h3>
    <div class="form-grid">
        <div class="form-group">
            <label>Bank Name</label>
            <input type="text" name="bank_name" value="Meezan Bank Ltd">
        </div>
        <div class="form-group">
            <label>Branch</label>
            <input type="text" name="bank_branch" value="Sector F-11 Branch, Islamabad">
        </div>
    </div>
    <div class="form-grid">
        <div class="form-group">
            <label>Account Name / Title</label>
            <input type="text" name="account_name" value="Graphica Fix">
        </div>
        <div class="form-group">
            <label>Account Number / IBAN</label>
            <input type="text" name="account_number" value="PK09MEZN000810011299029">
        </div>
    </div>
    <div class="form-grid">
        <div class="form-group">
            <label>SWIFT / BIC Code</label>
            <input type="text" name="swift_code" value="MEZNPKKA">
        </div>
        <div class="form-group">
            <label>Accepted Payment Methods</label>
            <input type="text" name="payment_methods" value="Bank Transfer, JazzCash, Safepay">
        </div>
    </div>

    <hr style="border:none;border-top:1px solid #f0f0f0;margin:20px 0;">
    <h3 style="margin-bottom:16px;">Authorized Signatory</h3>
    <div class="form-grid">
        <div class="form-group">
            <label>Signatory Name</label>
            <input type="text" name="signature_name" value="Ibadullah">
        </div>
        <div class="form-group">
            <label>Title / Designation</label>
            <input type="text" name="signature_title" value="Managing Director">
        </div>
    </div>
    <div class="form-group">
        <label>Signature Date</label>
        <input type="date" name="signature_date" value="<?= date('Y-m-d') ?>">
    </div>
</div>

<!-- ── Tab 2: Line Items ────────────────────────────────────────────────────── -->
<div id="itm" class="tab">
    <div class="items-table-wrap">
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:30%">Description</th>
                    <th style="width:8%">Qty</th>
                    <th style="width:10%">Unit</th>
                    <th style="width:12%">Tax Code</th>
                    <th style="width:15%">Unit Price</th>
                    <th style="width:10%">Tax %</th>
                    <th style="width:10%">Total</th>
                    <th style="width:5%"></th>
                </tr>
            </thead>
            <tbody id="itemsBody"></tbody>
        </table>
    </div>
    <button type="button" class="add-feature-btn main-btn" onclick="addRow()" style="margin:12px 0;">＋ Add Line Item</button>
    <datalist id="unitList">
        <option value="pcs"></option>
        <option value="hours"></option>
        <option value="days"></option>
        <option value="lot"></option>
        <option value="units"></option>
    </datalist>

    <div class="totals-box">
        <div class="total-row"><span>Subtotal</span>                                          <span id="tSubtotal">PKR 0.00</span></div>
        <div class="total-row"><span>Tax</span>                                                 <span id="tTax">PKR 0.00</span></div>
        <div class="total-row"><span>Discount</span>                                          <span id="tDiscount">— PKR 0.00</span></div>
        <div class="total-row total-final"><span>TOTAL</span>                                 <span id="tTotal">PKR 0.00</span></div>
        
        <!-- Paid amount input -->
        <div class="total-row" id="paidRow" style="margin-top:12px;padding-top:12px;border-top:2px dashed #d1fae5;border-bottom:none;">
            <label for="amountPaidInput" style="font-size:13.5px;font-weight:700;color:#059669;display:flex;align-items:center;gap:6px;cursor:pointer;">
                <i class="fas fa-dollar-sign"></i> Amount Paid
            </label>
            <div style="display:flex;align-items:center;gap:6px;">
                <span id="currSymbol" style="font-size:12px;color:#888;font-weight:600;">PKR</span>
                <input type="number"
                       name="amount_paid"
                       id="amountPaidInput"
                       value="0"
                       step="0.01" min="0"
                       placeholder="0.00"
                       oninput="recalc()"
                       style="width:130px;padding:8px 10px;border:1.5px solid #6ee7b7;border-radius:8px;font-size:14px;font-weight:700;color:#059669;outline:none;text-align:right;background:#f0fdf4;box-sizing:border-box;">
            </div>
        </div>

        <!-- Amount due -->
        <div class="total-row" id="dueRow" style="padding:8px 10px;border-radius:8px;background:#fff7ed;margin-top:6px;border-bottom:none;">
            <span style="font-weight:700;color:#ea580c;"><i class="fas fa-thumbtack"></i> Amount Due</span>
            <span style="font-weight:800;font-size:15px;color:#ea580c;" id="tDue">PKR 0.00</span>
        </div>
    </div>

    <div class="form-group" style="margin-top: 15px;">
        <label>Amount in Words <span class="required">*</span></label>
        <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 14px; font-weight: bold; color: #024442;" id="tAmountInWords">
            Pakistani Rupees Zero and 00/100 Only.
        </div>
        <input type="hidden" name="amount_in_words" id="amountInWordsInput" value="Pakistani Rupees Zero and 00/100 Only.">
    </div>
</div>

<!-- ── Tab 3: Notes & Terms ─────────────────────────────────────────────────── -->
<div id="nts" class="tab">
    <?php
    $initNotes = !empty($prefill['notes']) ? $prefill['notes'] : 'Thank you for your business! Please make the payment using the bank remittance details provided in the details section within the payment period.';
    $initTerms = !empty($prefill['terms']) ? $prefill['terms'] : "1. Payment is due within 14 days from the invoice issue date.\n2. Kindly mention the invoice number as reference in your bank transfer.\n3. Late payments may be subject to a fee of 1.5% per month on outstanding balances.";
    ?>
    <div class="form-group">
        <label>Notes <span style="color:#aaa;font-weight:400;">(visible to client)</span></label>
        <textarea name="notes" id="notesEditor" style="min-height:100px; width:100%;"><?= htmlspecialchars($initNotes) ?></textarea>
    </div>
    <div class="form-group">
        <label>Terms & Conditions</label>
        <textarea name="terms" style="min-height:120px;"><?= htmlspecialchars($initTerms) ?></textarea>
    </div>
</div>

<!-- Actions -->
<div class="form-actions">
    <button type="button" class="btn btn-secondary" id="prevBtn" onclick="prevTab()" style="display:none;">← Previous</button>
    <button type="button" class="btn btn-secondary" onclick="window.location='manage_invoices.php'">Cancel</button>
    <button type="button" class="btn btn-primary"   id="nextBtn" onclick="nextTab()">Next →</button>
    <button type="submit" class="btn btn-primary"   id="subBtn"  style="display:none;"><i class="fas fa-check"></i> Create Invoice</button>
</div>
</form>
</div> <!-- end form-container -->
</div>

<style>
.page-header { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:14px; margin-bottom:22px; }
.page-header h1 { margin:0 0 4px; }
.page-header p  { margin:0; color:#888; font-size:14px; }
.items-table-wrap { overflow-x:auto; }
.items-table { width:100%; border-collapse:collapse; font-size:14px; }
.items-table th { background:#f9fafb; padding:10px 12px; text-align:left; font-size:11px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.06em; border-bottom:1px solid #e5e7eb; }
.items-table td { padding:7px 5px; border-bottom:1px solid #f5f5f5; vertical-align:middle; }
.items-table input { width:100%; padding:8px 10px; border:1px solid #e0e0e0; border-radius:6px; font-size:13.5px; outline:none; box-sizing:border-box; transition:border-color .2s; }
.items-table input:focus { border-color:#024442; }
.row-total { font-weight:700; color:#024442; font-size:13.5px; padding:7px 12px; white-space:nowrap; }
.remove-row-btn { background:none; border:none; color:#e53e3e; font-size:15px; cursor:pointer; padding:4px 7px; border-radius:5px; }
.remove-row-btn:hover { background:#fff5f5; }
.totals-box { margin:18px 0 0 auto; max-width:360px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:14px 18px; }
.total-row { display:flex; justify-content:space-between; align-items:center; font-size:13.5px; color:#555; padding:5px 0; border-bottom:1px solid #f0f0f0; }
.total-row:last-child { border-bottom:none; }
.total-final { font-size:16px; font-weight:800; color:#024442; padding-top:9px; margin-top:3px; }
#amountPaidInput:focus { border-color:#059669 !important; box-shadow:0 0 0 3px rgba(5,150,105,.12); }
#amountPaidInput:disabled { opacity:.5; cursor:not-allowed; }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    tinymce.init({
        selector: '#notesEditor',
        height: 200,
        menubar: false,
        plugins: 'image table link lists',
        toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image table | removeformat',
        setup: function(editor) {
            editor.on('change keyup', function() {
                editor.save();
            });
        }
    });
});
</script>
<script>
const TABS = ['det','itm','nts'];
let tabIdx = 0;
let currentCurrency = 'PKR';

function showTab(id) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.mp-tab-buttons button').forEach(b => b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    tabIdx = TABS.indexOf(id);
    document.querySelectorAll('.mp-tab-buttons button')[tabIdx].classList.add('active');
    syncBtns();
}
function nextTab() { if(tabIdx < TABS.length-1){ tabIdx++; showTab(TABS[tabIdx]); } }
function prevTab() { if(tabIdx > 0)            { tabIdx--; showTab(TABS[tabIdx]); } }
function syncBtns() {
    document.getElementById('prevBtn').style.display = tabIdx===0             ? 'none':'inline-flex';
    document.getElementById('nextBtn').style.display = tabIdx===TABS.length-1 ? 'none':'inline-flex';
    document.getElementById('subBtn').style.display  = tabIdx===TABS.length-1 ? 'inline-flex':'none';
}

function updateCurrency(val) {
    currentCurrency = val;
    document.getElementById('currSymbol').textContent = val;
    recalc();
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
    let centsString = cents.toString().padStart(2, '0') + '/100';
    return currencyName + ' ' + wholeWords + ' and ' + centsString + ' Only.';
}

// When status = paid, lock paid input to full total
function handleStatusChange(val) {
    const paidInput = document.getElementById('amountPaidInput');
    if (val === 'paid') {
        const total = getTotal();
        paidInput.value    = total.toFixed(2);
        paidInput.disabled = true;
    } else {
        paidInput.disabled = false;
    }
    recalc();
}

function getTotal() {
    let sub = 0;
    let totalTax = 0;
    document.querySelectorAll('[name^="items["]').forEach(inp => {
        const m = inp.name.match(/items\[(\d+)\]\[quantity\]/);
        if (m) {
            const q = parseFloat(document.querySelector(`[name="items[${m[1]}][quantity]"]`)?.value  || 0);
            const p = parseFloat(document.querySelector(`[name="items[${m[1]}][unit_price]"]`)?.value || 0);
            const t = parseFloat(document.querySelector(`[name="items[${m[1]}][tax_percent]"]`)?.value || 0);
            const rowSub = q * p;
            sub += rowSub;
            totalTax += rowSub * (t / 100);
        }
    });
    const disc = parseFloat(document.getElementById('discAmt')?.value || 0);
    return sub + totalTax - disc;
}

let rowCnt = 0;
function addRow(desc='', qty=1, price='', unit='pcs', tax_code='', tax_percent=0) {
    const i   = rowCnt++;
    const row = document.createElement('tr');
    row.id    = 'r'+i;
    row.innerHTML = `
        <td><input type="text"   name="items[${i}][description]" value="${desc.replace(/"/g,'&quot;')}" placeholder="Service or product description"></td>
        <td><input type="number" name="items[${i}][quantity]"    value="${qty}"  step="0.01" min="0" oninput="calcRow(${i})"></td>
        <td><input type="text"   name="items[${i}][unit]"        value="${unit}" list="unitList"></td>
        <td><input type="text"   name="items[${i}][tax_code]"    value="${tax_code}" placeholder="HSN/SAC"></td>
        <td><input type="number" name="items[${i}][unit_price]"  value="${price}"step="0.01" min="0" placeholder="0.00" oninput="calcRow(${i})"></td>
        <td><input type="number" name="items[${i}][tax_percent]" value="${tax_percent}" step="0.01" min="0" max="100" oninput="calcRow(${i})"></td>
        <td class="row-total" id="rt${i}">PKR ₨ 0.00</td>
        <td><button type="button" class="remove-row-btn" onclick="removeRow(${i})"><i class="fas fa-times"></i></button></td>
    `;
    document.getElementById('itemsBody').appendChild(row);
    calcRow(i);
}
function removeRow(i) { const r = document.getElementById('r'+i); if(r) r.remove(); recalc(); }
function calcRow(i) {
    const qty   = parseFloat(document.querySelector(`[name="items[${i}][quantity]"]`)?.value  || 0);
    const price = parseFloat(document.querySelector(`[name="items[${i}][unit_price]"]`)?.value || 0);
    const tax   = parseFloat(document.querySelector(`[name="items[${i}][tax_percent]"]`)?.value || 0);
    const sub   = qty * price;
    const taxAmt = sub * (tax / 100);
    const cell  = document.getElementById('rt'+i);
    if (cell) cell.textContent = fmt(sub + taxAmt);
    recalc();
}

function recalc() {
    const total   = getTotal();
    const disc    = parseFloat(document.getElementById('discAmt')?.value || 0);
    let sub = 0;
    let totalTax = 0;
    document.querySelectorAll('[name^="items["]').forEach(inp => {
        const m = inp.name.match(/items\[(\d+)\]\[quantity\]/);
        if (m) {
            const q = parseFloat(document.querySelector(`[name="items[${m[1]}][quantity]"]`)?.value  || 0);
            const p = parseFloat(document.querySelector(`[name="items[${m[1]}][unit_price]"]`)?.value || 0);
            const t = parseFloat(document.querySelector(`[name="items[${m[1]}][tax_percent]"]`)?.value || 0);
            const rowSub = q * p;
            sub += rowSub;
            totalTax += rowSub * (t / 100);
        }
    });

    // Sync paid input if status = paid
    const statusVal = document.getElementById('statusSel').value;
    const paidInput = document.getElementById('amountPaidInput');
    if (statusVal === 'paid') paidInput.value = total.toFixed(2);

    const paid = Math.min(Math.max(parseFloat(paidInput.value || 0), 0), total);
    const due  = Math.max(total - paid, 0);

    document.getElementById('tSubtotal').textContent = fmt(sub);
    document.getElementById('tTax').textContent      = fmt(totalTax);
    document.getElementById('tDiscount').textContent = '— ' + fmt(disc);
    document.getElementById('tTotal').textContent    = fmt(total);

    // Dim "due" row when fully paid
    const dueRow = document.getElementById('dueRow');
    dueRow.style.background = due === 0 ? '#f0fdf4' : '#fff7ed';
    dueRow.querySelector('span:first-child').style.color = due === 0 ? '#059669' : '#ea580c';
    dueRow.querySelector('span:last-child').style.color  = due === 0 ? '#059669' : '#ea580c';
    document.getElementById('tDue').textContent = due === 0 ? 'Fully Paid' : fmt(due);

    // Amount in words
    const words = jsAmountToWords(total, currentCurrency);
    document.getElementById('tAmountInWords').textContent = words;
    document.getElementById('amountInWordsInput').value = words;
}

function fmt(v) {
    const cur = currentCurrency;
    const symbols = {
        'USD': 'USD $',
        'PKR': 'PKR ₨',
        'EUR': 'EUR €',
        'GBP': 'GBP £',
        'AED': 'AED د.إ'
    };
    const sym = symbols[cur] || cur;
    return sym + ' ' + Math.max(v,0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
}

// Prefill items from converted quotation
const prefillItems = <?= json_encode($prefillItems) ?>;
if (prefillItems.length) {
    prefillItems.forEach(it => addRow(it.description, it.quantity, it.unit_price, it.unit || 'pcs', '', it.tax_percent || 0));
} else {
    addRow();
}

document.getElementById('invForm').addEventListener('submit', function(e) {
    const num    = document.querySelector('[name="invoice_number"]').value.trim();
    const client = document.querySelector('[name="client_id"]').value;
    if (!num || !client) {
        e.preventDefault();
        alert('Invoice Number and Client are required.');
        showTab('det');
    }
});
</script>

<?php include('dashboard_footer.php'); ?>