<?php
include('dashboard_header.php');

// ── Prefill from quotation if converting ──────────────────────────────────────
$prefill = [];
$prefillItems = [];
if (!empty($_GET['from_quot'])) {
    $qid  = intval($_GET['from_quot']);
    $qrow = $conn->query("SELECT * FROM quotations WHERE id={$qid}")->fetch_assoc();
    if ($qrow) {
        $prefill = $qrow;
        $res = $conn->query("SELECT * FROM quotation_items WHERE quotation_id={$qid} ORDER BY sort_order");
        while ($r = $res->fetch_assoc()) $prefillItems[] = $r;
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

    // Build items & subtotal
    $subtotal = 0;
    $items = [];
    foreach (($_POST['items'] ?? []) as $i => $item) {
        if (empty(trim($item['description'] ?? ''))) continue;
        $qty   = floatval($item['quantity']   ?? 1);
        $price = floatval($item['unit_price'] ?? 0);
        $tot   = round($qty * $price, 2);
        $subtotal += $tot;
        $items[]   = ['description' => trim($item['description']), 'quantity' => $qty, 'unit_price' => $price, 'total' => $tot, 'sort_order' => (int)$i];
    }

    $tax_amount   = round($subtotal * ($tax_percent / 100), 2);
    $total_amount = round($subtotal + $tax_amount - $discount_amount, 2);
    $amount_paid  = $status === 'paid' ? $total_amount : 0.00;
    $amount_due   = $total_amount - $amount_paid;
    $paid_at      = $status === 'paid' ? date('Y-m-d H:i:s') : null;

    $stmt = $conn->prepare("INSERT INTO invoices
        (invoice_number,client_id,project_id,issue_date,due_date,status,
         subtotal,tax_percent,tax_amount,discount_amount,total_amount,
         amount_paid,amount_due,currency,notes,terms,paid_at,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    $stmt->bind_param("siisssdddddddssss",
        $invoice_number,$client_id,$project_id,$issue_date,$due_date,$status,
        $subtotal,$tax_percent,$tax_amount,$discount_amount,$total_amount,
        $amount_paid,$amount_due,$currency,$notes,$terms,$paid_at
    );
    $stmt->execute();
    $inv_id = $conn->insert_id;

    $is = $conn->prepare("INSERT INTO invoice_items (invoice_id,description,quantity,unit_price,total,sort_order) VALUES (?,?,?,?,?,?)");
    foreach ($items as $it) {
        $is->bind_param("isdddi", $inv_id, $it['description'], $it['quantity'], $it['unit_price'], $it['total'], $it['sort_order']);
        $is->execute();
    }

    // Mark source quotation as converted
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
if ($lastRow) { preg_match('/(\d+)$/', $lastRow['invoice_number'], $m); $nextNum = 'INV-' . date('Y') . '-' . str_pad((intval($m[1]??0)+1), 3, '0', STR_PAD_LEFT); }
?>

<div class="height-100">
<div class="page-header">
    <div>
        <h1>➕ Create Invoice</h1>
        <p><?= !empty($prefill) ? "Converted from quotation <strong>" . htmlspecialchars($prefill['quotation_number']) . "</strong>" : "Create a new client invoice" ?></p>
    </div>
    <a href="manage_invoices.php" class="btn btn-secondary">← Back</a>
</div>

<div class="mp-tabs-container">
    <div class="mp-tab-buttons">
        <button type="button" class="active" onclick="showTab('det')"><span class="mp-tab-icon">📋</span> Details</button>
        <button type="button"                onclick="showTab('itm')"><span class="mp-tab-icon">📝</span> Line Items</button>
        <button type="button"                onclick="showTab('nts')"><span class="mp-tab-icon">💬</span> Notes & Terms</button>
    </div>
</div>

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
            <select name="currency">
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
                <?= htmlspecialchars($c['client_name']) ?><?= $c['company'] ? ' — '.htmlspecialchars($c['company']) : '' ?>
            </option>
            <?php endwhile; ?>
        </select>
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
        <div class="form-group">
            <label>Tax %</label>
            <input type="number" name="tax_percent" id="taxPct" value="<?= $prefill['tax_percent'] ?? 0 ?>" step="0.01" min="0" max="100" oninput="recalc()">
        </div>
        <div class="form-group">
            <label>Discount Amount</label>
            <input type="number" name="discount_amount" id="discAmt" value="<?= $prefill['discount_amount'] ?? 0 ?>" step="0.01" min="0" oninput="recalc()">
        </div>
    </div>
    <div class="form-group">
        <label>Status</label>
        <select name="status">
            <option value="draft">Draft</option>
            <option value="sent">Sent</option>
            <option value="paid">Paid</option>
        </select>
    </div>
</div>

<!-- ── Tab 2: Line Items ────────────────────────────────────────────────────── -->
<div id="itm" class="tab">
    <div class="items-table-wrap">
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:48%">Description</th>
                    <th style="width:13%">Qty</th>
                    <th style="width:17%">Unit Price</th>
                    <th style="width:15%">Total</th>
                    <th style="width:7%"></th>
                </tr>
            </thead>
            <tbody id="itemsBody"></tbody>
        </table>
    </div>
    <button type="button" class="add-feature-btn" onclick="addRow()" style="margin:12px 0;">＋ Add Line Item</button>

    <div class="totals-box">
        <div class="total-row"><span>Subtotal</span><span id="tSubtotal">PKR 0.00</span></div>
        <div class="total-row"><span>Tax (<span id="tTaxPct">0</span>%)</span><span id="tTax">PKR 0.00</span></div>
        <div class="total-row"><span>Discount</span><span id="tDiscount">— PKR 0.00</span></div>
        <div class="total-row total-final"><span>TOTAL</span><span id="tTotal">PKR 0.00</span></div>
    </div>
</div>

<!-- ── Tab 3: Notes & Terms ─────────────────────────────────────────────────── -->
<div id="nts" class="tab">
    <div class="form-group">
        <label>Notes <span style="color:#aaa;font-weight:400;">(visible to client)</span></label>
        <textarea name="notes" style="min-height:100px;" placeholder="Thank you for your business! Payment within 14 days is appreciated."><?= htmlspecialchars($prefill['notes'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label>Terms & Conditions</label>
        <textarea name="terms" style="min-height:120px;" placeholder="1. Payment is due within 14 days.&#10;2. Late fees may apply after the due date."><?= htmlspecialchars($prefill['terms'] ?? '') ?></textarea>
    </div>
</div>

<!-- Actions -->
<div class="form-actions">
    <button type="button" class="btn btn-secondary" id="prevBtn" onclick="prevTab()" style="display:none;">← Previous</button>
    <button type="button" class="btn btn-secondary" onclick="window.location='manage_invoices.php'">Cancel</button>
    <button type="button" class="btn btn-primary"   id="nextBtn" onclick="nextTab()">Next →</button>
    <button type="submit" class="btn btn-primary"   id="subBtn"  style="display:none;">✓ Create Invoice</button>
</div>
</form>
</div>

<style>
.page-header { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:14px; margin-bottom:22px; }
.page-header h1 { margin:0 0 4px; }
.page-header p { margin:0; color:#888; font-size:14px; }
.items-table-wrap { overflow-x:auto; }
.items-table { width:100%; border-collapse:collapse; font-size:14px; }
.items-table th { background:#f9fafb; padding:10px 12px; text-align:left; font-size:11px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.06em; border-bottom:1px solid #e5e7eb; }
.items-table td { padding:7px 5px; border-bottom:1px solid #f5f5f5; vertical-align:middle; }
.items-table input { width:100%; padding:8px 10px; border:1px solid #e0e0e0; border-radius:6px; font-size:13.5px; outline:none; box-sizing:border-box; transition:border-color .2s; }
.items-table input:focus { border-color:#024442; }
.row-total { font-weight:700; color:#024442; font-size:13.5px; padding:7px 12px; white-space:nowrap; }
.remove-row-btn { background:none; border:none; color:#e53e3e; font-size:15px; cursor:pointer; padding:4px 7px; border-radius:5px; }
.remove-row-btn:hover { background:#fff5f5; }
.totals-box { margin:18px 0 0 auto; max-width:320px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:14px 18px; }
.total-row { display:flex; justify-content:space-between; font-size:13.5px; color:#555; padding:5px 0; border-bottom:1px solid #f0f0f0; }
.total-row:last-child { border-bottom:none; }
.total-final { font-size:16px; font-weight:800; color:#024442; padding-top:9px; margin-top:3px; }
</style>

<script>
const TABS = ['det','itm','nts'];
let tabIdx = 0;

function showTab(id) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.mp-tab-buttons button').forEach(b => b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    tabIdx = TABS.indexOf(id);
    document.querySelectorAll('.mp-tab-buttons button')[tabIdx].classList.add('active');
    syncBtns();
}
function nextTab()     { if(tabIdx < TABS.length-1){ tabIdx++; showTab(TABS[tabIdx]); } }
function prevTab()     { if(tabIdx > 0)            { tabIdx--; showTab(TABS[tabIdx]); } }
function syncBtns() {
    document.getElementById('prevBtn').style.display = tabIdx===0               ? 'none':'inline-flex';
    document.getElementById('nextBtn').style.display = tabIdx===TABS.length-1   ? 'none':'inline-flex';
    document.getElementById('subBtn').style.display  = tabIdx===TABS.length-1   ? 'inline-flex':'none';
}

let rowCnt = 0;
function addRow(desc='', qty=1, price='') {
    const i   = rowCnt++;
    const row = document.createElement('tr');
    row.id    = 'r'+i;
    row.innerHTML = `
        <td><input type="text"   name="items[${i}][description]" value="${desc}" placeholder="Service or product description"></td>
        <td><input type="number" name="items[${i}][quantity]"    value="${qty}"  step="0.01" min="0" oninput="calcRow(${i})"></td>
        <td><input type="number" name="items[${i}][unit_price]"  value="${price}"step="0.01" min="0" placeholder="0.00" oninput="calcRow(${i})"></td>
        <td class="row-total" id="rt${i}">PKR 0.00</td>
        <td><button type="button" class="remove-row-btn" onclick="removeRow(${i})">✕</button></td>
    `;
    document.getElementById('itemsBody').appendChild(row);
    if (price) calcRow(i);
}
function removeRow(i) { const r = document.getElementById('r'+i); if(r) r.remove(); recalc(); }
function calcRow(i) {
    const qty   = parseFloat(document.querySelector(`[name="items[${i}][quantity]"]`)?.value  || 0);
    const price = parseFloat(document.querySelector(`[name="items[${i}][unit_price]"]`)?.value || 0);
    const cell  = document.getElementById('rt'+i);
    if(cell) cell.textContent = fmt(qty * price);
    recalc();
}
function recalc() {
    let sub = 0;
    document.querySelectorAll('[name^="items["]').forEach(inp => {
        const m = inp.name.match(/items\[(\d+)\]\[quantity\]/);
        if(m) {
            const q = parseFloat(document.querySelector(`[name="items[${m[1]}][quantity]"]`)?.value  || 0);
            const p = parseFloat(document.querySelector(`[name="items[${m[1]}][unit_price]"]`)?.value || 0);
            sub += q * p;
        }
    });
    const tax  = parseFloat(document.getElementById('taxPct')?.value  || 0);
    const disc = parseFloat(document.getElementById('discAmt')?.value || 0);
    const taxAmt = sub * (tax/100);
    const total  = sub + taxAmt - disc;
    document.getElementById('tSubtotal').textContent = fmt(sub);
    document.getElementById('tTaxPct').textContent   = tax;
    document.getElementById('tTax').textContent      = fmt(taxAmt);
    document.getElementById('tDiscount').textContent = '— ' + fmt(disc);
    document.getElementById('tTotal').textContent    = fmt(total);
}
function fmt(v) { return 'PKR ' + v.toLocaleString('en-PK',{minimumFractionDigits:2,maximumFractionDigits:2}); }

// Prefill items from converted quotation
const prefillItems = <?= json_encode($prefillItems) ?>;
if (prefillItems.length) {
    prefillItems.forEach(it => addRow(it.description, it.quantity, it.unit_price));
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