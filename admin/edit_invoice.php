<?php
include('dashboard_header.php');

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

    // Build items & totals
    $subtotal = 0;
    $items = [];
    foreach (($_POST['items'] ?? []) as $i => $item) {
        if (empty(trim($item['description'] ?? ''))) continue;
        $qty   = floatval($item['quantity']   ?? 1);
        $price = floatval($item['unit_price'] ?? 0);
        $tot   = round($qty * $price, 2);
        $subtotal += $tot;
        $items[] = ['description' => trim($item['description']), 'quantity' => $qty, 'unit_price' => $price, 'total' => $tot, 'sort_order' => (int)$i];
    }

    $tax_amount   = round($subtotal * ($tax_percent / 100), 2);
    $total_amount = round($subtotal + $tax_amount - $discount_amount, 2);

    // Preserve paid amount if already paid
    $existing = $conn->query("SELECT status, amount_paid, paid_at FROM invoices WHERE id={$id}")->fetch_assoc();
    if ($status === 'paid' && $existing['status'] !== 'paid') {
        $amount_paid = $total_amount;
        $paid_at     = date('Y-m-d H:i:s');
    } elseif ($status !== 'paid') {
        $amount_paid = 0.00;
        $paid_at     = null;
    } else {
        $amount_paid = floatval($existing['amount_paid']);
        $paid_at     = $existing['paid_at'];
    }
    $amount_due = max(0, $total_amount - $amount_paid);

    $stmt = $conn->prepare("UPDATE invoices SET
        invoice_number=?, client_id=?, project_id=?, issue_date=?, due_date=?, status=?,
        subtotal=?, tax_percent=?, tax_amount=?, discount_amount=?, total_amount=?,
        amount_paid=?, amount_due=?, currency=?, notes=?, terms=?, paid_at=?, updated_at=NOW()
        WHERE id=?");
    $stmt->bind_param("siisssdddddddssssi",
        $invoice_number, $client_id, $project_id, $issue_date, $due_date, $status,
        $subtotal, $tax_percent, $tax_amount, $discount_amount, $total_amount,
        $amount_paid, $amount_due, $currency, $notes, $terms, $paid_at, $id
    );
    $stmt->execute();

    // Replace all line items
    $conn->query("DELETE FROM invoice_items WHERE invoice_id={$id}");
    $is = $conn->prepare("INSERT INTO invoice_items (invoice_id,description,quantity,unit_price,total,sort_order) VALUES (?,?,?,?,?,?)");
    foreach ($items as $it) {
        $is->bind_param("isdddi", $id, $it['description'], $it['quantity'], $it['unit_price'], $it['total'], $it['sort_order']);
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
        <h1>✏️ Edit Invoice</h1>
        <p>Editing <strong><?= htmlspecialchars($inv['invoice_number']) ?></strong><?= $inv['client_name'] ? ' · ' . htmlspecialchars($inv['client_name']) : '' ?></p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="invoice_details.php?id=<?= $id ?>" class="btn btn-secondary">← Cancel</a>
    </div>
</div>

<div class="mp-tabs-container">
    <div class="mp-tab-buttons">
        <button type="button" class="active" onclick="showTab('det')"><span class="mp-tab-icon">📋</span> Details</button>
        <button type="button"                onclick="showTab('itm')"><span class="mp-tab-icon">📝</span> Line Items</button>
        <button type="button"                onclick="showTab('nts')"><span class="mp-tab-icon">💬</span> Notes & Terms</button>
    </div>
</div>

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
            <select name="currency">
                <?php foreach (['PKR'=>'PKR — Pakistani Rupee','USD'=>'USD — US Dollar','EUR'=>'EUR — Euro','GBP'=>'GBP — British Pound'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= $inv['currency']===$v ? 'selected' : '' ?>><?= $l ?></option>
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
                <?= htmlspecialchars($c['client_name']) ?><?= $c['company'] ? ' — '.htmlspecialchars($c['company']):''; ?>
            </option>
            <?php endwhile; ?>
        </select>
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
        <div class="form-group">
            <label>Tax %</label>
            <input type="number" name="tax_percent" id="taxPct" value="<?= $inv['tax_percent'] ?>" step="0.01" min="0" max="100" oninput="recalc()">
        </div>
        <div class="form-group">
            <label>Discount Amount</label>
            <input type="number" name="discount_amount" id="discAmt" value="<?= $inv['discount_amount'] ?>" step="0.01" min="0" oninput="recalc()">
        </div>
    </div>

    <div class="form-group">
        <label>Status</label>
        <select name="status">
            <?php foreach (['draft'=>'Draft','sent'=>'Sent','paid'=>'Paid','cancelled'=>'Cancelled'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= $inv['status']===$v ? 'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
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
        <textarea name="notes" style="min-height:100px;"><?= htmlspecialchars($inv['notes'] ?? '') ?></textarea>
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
    <button type="submit" class="btn btn-primary"   id="subBtn"  style="display:none;">✓ Save Changes</button>
</div>
</form>
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
.totals-box{margin:18px 0 0 auto;max-width:320px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px 18px;}
.total-row{display:flex;justify-content:space-between;font-size:13.5px;color:#555;padding:5px 0;border-bottom:1px solid #f0f0f0;}
.total-row:last-child{border-bottom:none;}
.total-final{font-size:16px;font-weight:800;color:#024442;padding-top:9px;margin-top:3px;}
</style>

<script>
const TABS=['det','itm','nts'];
let ti=0;
function showTab(id){
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('.mp-tab-buttons button').forEach(b=>b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    ti=TABS.indexOf(id);
    document.querySelectorAll('.mp-tab-buttons button')[ti].classList.add('active');
    syncBtns();
}
function nextTab(){if(ti<TABS.length-1){ti++;showTab(TABS[ti]);}}
function prevTab(){if(ti>0){ti--;showTab(TABS[ti]);}}
function syncBtns(){
    document.getElementById('prevBtn').style.display=ti===0?'none':'inline-flex';
    document.getElementById('nextBtn').style.display=ti===TABS.length-1?'none':'inline-flex';
    document.getElementById('subBtn').style.display=ti===TABS.length-1?'inline-flex':'none';
}

let rowCnt=0;
function addRow(desc='',qty=1,price=''){
    const i=rowCnt++;
    const tr=document.createElement('tr');
    tr.id='r'+i;
    tr.innerHTML=`
        <td><input type="text"   name="items[${i}][description]" value="${desc.replace(/"/g,'&quot;')}" placeholder="Description"></td>
        <td><input type="number" name="items[${i}][quantity]"    value="${qty}"  step="0.01" min="0" oninput="calcRow(${i})"></td>
        <td><input type="number" name="items[${i}][unit_price]"  value="${price}"step="0.01" min="0" placeholder="0.00" oninput="calcRow(${i})"></td>
        <td class="row-total" id="rt${i}">PKR 0.00</td>
        <td><button type="button" class="remove-row-btn" onclick="removeRow(${i})">✕</button></td>
    `;
    document.getElementById('itemsBody').appendChild(tr);
    if(price) calcRow(i);
}
function removeRow(i){const r=document.getElementById('r'+i);if(r)r.remove();recalc();}
function calcRow(i){
    const q=parseFloat(document.querySelector(`[name="items[${i}][quantity]"]`)?.value||0);
    const p=parseFloat(document.querySelector(`[name="items[${i}][unit_price]"]`)?.value||0);
    const c=document.getElementById('rt'+i);
    if(c)c.textContent=fmt(q*p);
    recalc();
}
function recalc(){
    let sub=0;
    document.querySelectorAll('[name^="items["]').forEach(inp=>{
        const m=inp.name.match(/items\[(\d+)\]\[quantity\]/);
        if(m){
            const q=parseFloat(document.querySelector(`[name="items[${m[1]}][quantity]"]`)?.value||0);
            const p=parseFloat(document.querySelector(`[name="items[${m[1]}][unit_price]"]`)?.value||0);
            sub+=q*p;
        }
    });
    const tax=parseFloat(document.getElementById('taxPct')?.value||0);
    const disc=parseFloat(document.getElementById('discAmt')?.value||0);
    const ta=sub*(tax/100),tot=sub+ta-disc;
    document.getElementById('tSubtotal').textContent=fmt(sub);
    document.getElementById('tTaxPct').textContent=tax;
    document.getElementById('tTax').textContent=fmt(ta);
    document.getElementById('tDiscount').textContent='— '+fmt(disc);
    document.getElementById('tTotal').textContent=fmt(tot);
}
function fmt(v){return'PKR '+v.toLocaleString('en-PK',{minimumFractionDigits:2,maximumFractionDigits:2});}

// Prefill existing line items
const existing=<?= json_encode($existingItems) ?>;
existing.forEach(it=>addRow(it.description,it.quantity,it.unit_price));
if(!existing.length) addRow();
</script>
<?php include('dashboard_footer.php'); ?>