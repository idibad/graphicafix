<?php
include 'dashboard_header.php';

// Check permissions
if (!in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'hrm', 'user'])) {
    header('location:index.php'); exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: manage_invoices.php'); exit; }

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id       = intval($_POST['client_id']      ?? 0) ?: null;
    $project_id      = intval($_POST['project_id']     ?? 0) ?: null;
    $invoice_number  = trim($_POST['invoice_number']);
    $issue_date      = $_POST['issue_date'];
    $due_date        = $_POST['due_date'];
    $status          = $_POST['status'] ?? 'draft';
    $tax_percent     = floatval($_POST['tax_percent']    ?? 0);
    $discount_amount = floatval($_POST['discount_amount'] ?? 0);
    $notes           = trim($_POST['notes']   ?? '');
    $terms           = trim($_POST['terms']   ?? '');
    $currency        = $_POST['currency'] ?? 'PKR';

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

    // Build items & totals
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

    // ── Paid amount logic ─────────────────────────────────────────────────────
    $existing = $conn->query("SELECT status, amount_paid, paid_at FROM invoices WHERE id={$id}")->fetch_assoc();

    if ($status === 'paid') {
        // Fully paid — force total
        $amount_paid = $total_amount;
        $paid_at     = $existing['paid_at'] ?? date('Y-m-d H:i:s');
    } else {
        // Use manually entered amount, capped at total
        $amount_paid = min(floatval($_POST['amount_paid'] ?? 0), $total_amount);
        $paid_at     = $amount_paid > 0
            ? ($existing['paid_at'] ?? date('Y-m-d H:i:s'))
            : null;
    }

    // Auto-upgrade status if fully paid via amount_paid field
    if ($amount_paid >= $total_amount && $total_amount > 0) {
        $status  = 'paid';
        $paid_at = $existing['paid_at'] ?? date('Y-m-d H:i:s');
    }

    $amount_due = max(0, round($total_amount - $amount_paid, 2));

    $stmt = $conn->prepare("UPDATE invoices SET
        invoice_number=?, client_id=?, project_id=?, issue_date=?, due_date=?, status=?,
        subtotal=?, tax_percent=?, tax_amount=?, discount_amount=?, total_amount=?,
        amount_paid=?, amount_due=?, currency=?, notes=?, terms=?, paid_at=?,
        client_ref_rfq=?, client_tax_id=?, ship_to=?, bank_name=?, bank_branch=?, account_name=?,
        account_number=?, swift_code=?, payment_methods=?, signature_name=?, signature_title=?, signature_date=?, amount_in_words=?,
        updated_at=NOW() WHERE id=?");
    $stmt->bind_param("siisssdddddddsssssssssssssssssi",
        $invoice_number, $client_id, $project_id, $issue_date, $due_date, $status,
        $subtotal, $tax_percent, $tax_amount, $discount_amount, $total_amount,
        $amount_paid, $amount_due, $currency, $notes, $terms, $paid_at,
        $client_ref_rfq, $client_tax_id, $ship_to, $bank_name, $bank_branch, $account_name,
        $account_number, $swift_code, $payment_methods, $sig_name, $sig_title, $sig_date, $amount_in_words,
        $id
    );
    $stmt->execute();

    // Replace all line items
    $conn->query("DELETE FROM invoice_items WHERE invoice_id={$id}");
    $is = $conn->prepare("INSERT INTO invoice_items (invoice_id,description,quantity,unit_price,total,sort_order,unit,tax_code,tax_percent,tax_amount) VALUES (?,?,?,?,?,?,?,?,?,?)");
    foreach ($items as $it) {
        $is->bind_param("isdddissdd", $id, $it['description'], $it['quantity'], $it['unit_price'], $it['total'], $it['sort_order'], $it['unit'], $it['tax_code'], $it['tax_percent'], $it['tax_amount']);
        $is->execute();
    }

    echo "<script>alert('Invoice updated successfully!');window.location='invoice_details.php?id={$id}';</script>";
    exit;
}

// ── Load existing data ────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT i.*, c.client_name FROM invoices i LEFT JOIN clients c ON i.client_id=c.id WHERE i.id=?");
$stmt->bind_param("i", $id); $stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();
if (!$inv) { header('Location: manage_invoices.php'); exit; }

$stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order");
$stmt->bind_param("i", $id); $stmt->execute();
$existingItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$clients  = $conn->query("SELECT id,client_name,company FROM clients ORDER BY client_name");
$projects = $conn->query("SELECT id,project_name FROM projects ORDER BY project_name");
?>

<div class="height-100">
<div class="page-header">
    <div>
        <h1><i class="fas fa-pencil-alt"></i>️ Edit Invoice</h1>
        <p>Editing <strong><?= htmlspecialchars($inv['invoice_number']) ?></strong><?= $inv['client_name'] ? ' · ' . htmlspecialchars($inv['client_name']) : '' ?></p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="invoice_details.php?id=<?= $id ?>" class="btn btn-secondary">← Cancel</a>
    </div>
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

<!-- ── Tab 1: Details ──────────────────────────────────────────────────────── -->
<div id="det" class="tab active">
    <div class="form-grid">
        <div class="form-group">
            <label>Invoice Number <span class="required">*</span></label>
            <input type="text" name="invoice_number" value="<?= htmlspecialchars($inv['invoice_number']) ?>" required>
        </div>
        <div class="form-group">
            <label>Currency</label>
            <select name="currency" id="currSel" onchange="updateCurrency(this.value)">
                <?php foreach (['PKR'=>'PKR — Pakistani Rupee','USD'=>'USD — US Dollar','EUR'=>'EUR — Euro','GBP'=>'GBP — British Pound'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= $inv['currency']===$v ? 'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label>Client <span class="required">*</span></label>
        <select name="client_id" required>
            <option value="">Select a client</option>
            <?php while ($c = $clients->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>" <?= $inv['client_id']==$c['id'] ? 'selected':'' ?>>
                <?= htmlspecialchars($c['client_name']) ?><?= $c['company'] ? ' — '.htmlspecialchars($c['company']):''; ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="form-grid">
        <div class="form-group">
            <label>Client Tax ID / VAT Registration</label>
            <input type="text" name="client_tax_id" id="fClientTaxId" value="<?= htmlspecialchars($inv['client_tax_id'] ?? '') ?>" placeholder="e.g. STRN-987654321">
        </div>
        <div class="form-group">
            <label>Client PO / RFQ Reference Number</label>
            <input type="text" name="client_ref_rfq" id="fClientRefRfq" value="<?= htmlspecialchars($inv['client_ref_rfq'] ?? '') ?>" placeholder="e.g. PO-2026-088">
        </div>
    </div>
    <div class="form-group">
        <label>Ship To / Delivery Address <span style="color:#aaa;font-weight:400;">(optional, only for goods)</span></label>
        <textarea name="ship_to" placeholder="Delivery address..." style="min-height:50px;"><?= htmlspecialchars($inv['ship_to'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label>Linked Project <span style="color:#aaa;font-weight:400;">(optional)</span></label>
        <select name="project_id">
            <option value="">None</option>
            <?php while ($p = $projects->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>" <?= $inv['project_id']==$p['id'] ? 'selected':'' ?>><?= htmlspecialchars($p['project_name']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="form-grid">
        <div class="form-group">
            <label>Issue Date <span class="required">*</span></label>
            <input type="date" name="issue_date" value="<?= $inv['issue_date'] ?>" required>
        </div>
        <div class="form-group">
            <label>Due Date <span class="required">*</span></label>
            <input type="date" name="due_date" value="<?= $inv['due_date'] ?>" required>
        </div>
    </div>
    <div class="form-grid">
        <div class="form-group" style="display:none;">
            <label>Tax % (Global)</label>
            <input type="number" name="tax_percent" id="taxPct" value="<?= $inv['tax_percent'] ?>" step="0.01" min="0" max="100" oninput="recalc()">
        </div>
        <div class="form-group">
            <label>Discount Amount</label>
            <input type="number" name="discount_amount" id="discAmt" value="<?= $inv['discount_amount'] ?>" step="0.01" min="0" oninput="recalc()">
        </div>
    </div>
    <div class="form-group">
        <label>Status</label>
        <select name="status" id="statusSel" onchange="handleStatusChange(this.value)">
            <?php foreach (['draft'=>'Draft','sent'=>'Sent','paid'=>'Paid','cancelled'=>'Cancelled'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= $inv['status']===$v ? 'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <hr style="border:none;border-top:1px solid #f0f0f0;margin:20px 0;">
    <h3 style="margin-bottom:16px;">Remittance Bank Details</h3>
    <div class="form-grid">
        <div class="form-group">
            <label>Bank Name</label>
            <input type="text" name="bank_name" value="<?= htmlspecialchars($inv['bank_name'] ?? 'Meezan Bank Ltd') ?>">
        </div>
        <div class="form-group">
            <label>Branch</label>
            <input type="text" name="bank_branch" value="<?= htmlspecialchars($inv['bank_branch'] ?? 'Sector F-11 Branch, Islamabad') ?>">
        </div>
    </div>
    <div class="form-grid">
        <div class="form-group">
            <label>Account Name / Title</label>
            <input type="text" name="account_name" value="<?= htmlspecialchars($inv['account_name'] ?? 'Graphica Fix') ?>">
        </div>
        <div class="form-group">
            <label>Account Number / IBAN</label>
            <input type="text" name="account_number" value="<?= htmlspecialchars($inv['account_number'] ?? 'PK09MEZN000810011299029') ?>">
        </div>
    </div>
    <div class="form-grid">
        <div class="form-group">
            <label>SWIFT / BIC Code</label>
            <input type="text" name="swift_code" value="<?= htmlspecialchars($inv['swift_code'] ?? 'MEZNPKKA') ?>">
        </div>
        <div class="form-group">
            <label>Accepted Payment Methods</label>
            <input type="text" name="payment_methods" value="<?= htmlspecialchars($inv['payment_methods'] ?? 'Bank Transfer, JazzCash, Safepay') ?>">
        </div>
    </div>

    <hr style="border:none;border-top:1px solid #f0f0f0;margin:20px 0;">
    <h3 style="margin-bottom:16px;">Authorized Signatory</h3>
    <div class="form-grid">
        <div class="form-group">
            <label>Signatory Name</label>
            <input type="text" name="signature_name" value="<?= htmlspecialchars($inv['signature_name'] ?? 'Ibadullah') ?>">
        </div>
        <div class="form-group">
            <label>Title / Designation</label>
            <input type="text" name="signature_title" value="<?= htmlspecialchars($inv['signature_title'] ?? 'Managing Director') ?>">
        </div>
    </div>
    <div class="form-group">
        <label>Signature Date</label>
        <input type="date" name="signature_date" value="<?= htmlspecialchars($inv['signature_date'] ?? date('Y-m-d')) ?>">
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
                <span id="currSymbol" style="font-size:12px;color:#888;font-weight:600;"><?= htmlspecialchars($inv['currency'] ?? 'PKR') ?></span>
                <input type="number"
                       name="amount_paid"
                       id="amountPaidInput"
                       value="<?= floatval($inv['amount_paid'] ?? 0) ?>"
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
            <?= htmlspecialchars($inv['amount_in_words'] ?? 'Pakistani Rupees Zero and 00/100 Only.') ?>
        </div>
        <input type="hidden" name="amount_in_words" id="amountInWordsInput" value="<?= htmlspecialchars($inv['amount_in_words'] ?? 'Pakistani Rupees Zero and 00/100 Only.') ?>">
    </div>
</div>

<!-- ── Tab 3: Notes & Terms ─────────────────────────────────────────────────── -->
<div id="nts" class="tab">
    <div class="form-group">
        <label>Notes <span style="color:#aaa;font-weight:400;">(visible to client)</span></label>
        <textarea name="notes" id="notesEditor" style="min-height:100px; width: 100%;"><?= htmlspecialchars($inv['notes'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label>Terms & Conditions</label>
        <textarea name="terms" style="min-height:120px;"><?= htmlspecialchars($inv['terms'] ?? '') ?></textarea>
    </div>
</div>

<div class="form-actions">
    <button type="button" class="btn btn-secondary" id="prevBtn" onclick="prevTab()" style="display:none;">← Previous</button>
    <button type="button" class="btn btn-secondary" onclick="window.location='invoice_details.php?id=<?= $id ?>'">Cancel</button>
    <button type="button" class="btn btn-primary"   id="nextBtn" onclick="nextTab()">Next →</button>
    <button type="submit" class="btn btn-primary"   id="subBtn"  style="display:none;"><i class="fas fa-check"></i> Save Changes</button>
</div>
</form>
</div> <!-- end form-container -->
</div>

<style>
.page-header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:22px;}
.page-header h1{margin:0 0 4px;}
.page-header p{margin:0;color:#888;font-size:14px;}
.items-table-wrap{overflow-x:auto;}
.items-table{width:100%;border-collapse:collapse;font-size:14px;}
.items-table th{background:#f9fafb;padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid #e5e7eb;}
.items-table td{padding:7px 5px;border-bottom:1px solid #f5f5f5;vertical-align:middle;}
.items-table input{width:100%;padding:8px 10px;border:1px solid #e0e0e0;border-radius:6px;font-size:13.5px;outline:none;box-sizing:border-box;transition:border-color .2s;}
.items-table input:focus{border-color:#024442;}
.row-total{font-weight:700;color:#024442;font-size:13.5px;padding:7px 12px;white-space:nowrap;}
.remove-row-btn{background:none;border:none;color:#e53e3e;font-size:15px;cursor:pointer;padding:4px 7px;border-radius:5px;}
.remove-row-btn:hover{background:#fff5f5;}
.totals-box{margin:18px 0 0 auto;max-width:360px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px 18px;}
.total-row{display:flex;justify-content:space-between;align-items:center;font-size:13.5px;color:#555;padding:5px 0;border-bottom:1px solid #f0f0f0;}
.total-row:last-child{border-bottom:none;}
.total-final{font-size:16px;font-weight:800;color:#024442;padding-top:9px;margin-top:3px;}
#amountPaidInput:focus{border-color:#059669 !important;box-shadow:0 0 0 3px rgba(5,150,105,.12);}
#amountPaidInput:disabled{opacity:.5;cursor:not-allowed;}
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
let ti = 0;
let currentCurrency = '<?= htmlspecialchars($inv['currency'] ?? 'PKR') ?>';

function showTab(id) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.mp-tab-buttons button').forEach(b => b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    ti = TABS.indexOf(id);
    document.querySelectorAll('.mp-tab-buttons button')[ti].classList.add('active');
    syncBtns();
}
function nextTab() { if(ti < TABS.length-1){ ti++; showTab(TABS[ti]); } }
function prevTab() { if(ti > 0)            { ti--; showTab(TABS[ti]); } }
function syncBtns() {
    document.getElementById('prevBtn').style.display = ti===0             ? 'none':'inline-flex';
    document.getElementById('nextBtn').style.display = ti===TABS.length-1 ? 'none':'inline-flex';
    document.getElementById('subBtn').style.display  = ti===TABS.length-1 ? 'inline-flex':'none';
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

function handleStatusChange(val) {
    const paidInput = document.getElementById('amountPaidInput');
    if (val === 'paid') {
        paidInput.value    = getTotal().toFixed(2);
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
    const i  = rowCnt++;
    const tr = document.createElement('tr');
    tr.id    = 'r'+i;
    tr.innerHTML = `
        <td><input type="text"   name="items[${i}][description]" value="${desc.replace(/"/g,'&quot;')}" placeholder="Description"></td>
        <td><input type="number" name="items[${i}][quantity]"    value="${qty}"  step="0.01" min="0" oninput="calcRow(${i})"></td>
        <td><input type="text"   name="items[${i}][unit]"        value="${unit}" list="unitList"></td>
        <td><input type="text"   name="items[${i}][tax_code]"    value="${tax_code}" placeholder="HSN/SAC"></td>
        <td><input type="number" name="items[${i}][unit_price]"  value="${price}"step="0.01" min="0" placeholder="0.00" oninput="calcRow(${i})"></td>
        <td><input type="number" name="items[${i}][tax_percent]" value="${tax_percent}" step="0.01" min="0" max="100" oninput="calcRow(${i})"></td>
        <td class="row-total" id="rt${i}">PKR ₨ 0.00</td>
        <td><button type="button" class="remove-row-btn" onclick="removeRow(${i})"><i class="fas fa-times"></i></button></td>
    `;
    document.getElementById('itemsBody').appendChild(tr);
    calcRow(i);
}
function removeRow(i) { const r = document.getElementById('r'+i); if(r) r.remove(); recalc(); }
function calcRow(i) {
    const q = parseFloat(document.querySelector(`[name="items[${i}][quantity]"]`)?.value  || 0);
    const p = parseFloat(document.querySelector(`[name="items[${i}][unit_price]"]`)?.value || 0);
    const t = parseFloat(document.querySelector(`[name="items[${i}][tax_percent]"]`)?.value || 0);
    const sub   = q * p;
    const taxAmt = sub * (t / 100);
    const c = document.getElementById('rt'+i);
    if (c) c.textContent = fmt(sub + taxAmt);
    recalc();
}

function recalc() {
    const total  = getTotal();
    const disc   = parseFloat(document.getElementById('discAmt')?.value || 0);
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

    // Sync if status=paid
    const statusVal = document.getElementById('statusSel').value;
    const paidInput = document.getElementById('amountPaidInput');
    if (statusVal === 'paid') paidInput.value = total.toFixed(2);

    const paid = Math.min(Math.max(parseFloat(paidInput.value || 0), 0), total);
    const due  = Math.max(total - paid, 0);

    document.getElementById('tSubtotal').textContent = fmt(sub);
    document.getElementById('tTax').textContent      = fmt(totalTax);
    document.getElementById('tDiscount').textContent = '— ' + fmt(disc);
    document.getElementById('tTotal').textContent    = fmt(total);

    // Due row — green when fully paid, orange when outstanding
    const dueRow  = document.getElementById('dueRow');
    const dueSpan = document.getElementById('tDue');
    if (due === 0) {
        dueRow.style.background = '#f0fdf4';
        dueRow.querySelectorAll('span').forEach(s => s.style.color = '#059669');
        dueSpan.innerHTML = '<i class="fas fa-check"></i> Fully Paid';
    } else {
        dueRow.style.background = '#fff7ed';
        dueRow.querySelectorAll('span').forEach(s => s.style.color = '#ea580c');
        dueSpan.textContent = fmt(due);
    }

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

// Prefill existing line items
const existing = <?= json_encode($existingItems) ?>;
existing.forEach(it => addRow(it.description, it.quantity, it.unit_price, it.unit || 'pcs', it.tax_code || '', it.tax_percent || 0));
if (!existing.length) addRow();

// Set initial state based on current status
handleStatusChange(document.getElementById('statusSel').value);
</script>

<?php include('dashboard_footer.php'); ?>