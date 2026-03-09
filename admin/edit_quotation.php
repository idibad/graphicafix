<?php
include('dashboard_header.php');

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: manage_invoices.php?tab=quotations'); exit; }

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
    $notes           = trim($_POST['notes']   ?? '');
    $terms           = trim($_POST['terms']   ?? '');
    $sig_name        = trim($_POST['signature_name']  ?? '');
    $sig_title       = trim($_POST['signature_title'] ?? '');
    $sig_date        = $_POST['signature_date'] ?? date('Y-m-d');

    $subtotal = 0;
    $items = [];
    foreach (($_POST['items'] ?? []) as $i => $item) {
        if (empty(trim($item['description'] ?? ''))) continue;
        $qty   = floatval($item['quantity']   ?? 1);
        $price = floatval($item['unit_price'] ?? 0);
        $tot   = round($qty * $price, 2);
        $subtotal += $tot;
        $items[] = ['description'=>trim($item['description']),'quantity'=>$qty,'unit_price'=>$price,'total'=>$tot,'sort_order'=>(int)$i];
    }

    $tax_amount   = round($subtotal * ($tax_percent / 100), 2);
    $total_amount = round($subtotal + $tax_amount - $discount_amount, 2);

    $stmt = $conn->prepare("UPDATE quotations SET
        quotation_number=?, client_id=?, client_name=?, client_email=?, client_phone=?,
        client_company=?, client_address=?, issue_date=?, valid_until=?, status=?,
        subtotal=?, tax_percent=?, tax_amount=?, discount_amount=?, total_amount=?,
        currency=?, notes=?, terms=?, signature_name=?, signature_title=?, signature_date=?,
        updated_at=NOW() WHERE id=?");
        $stmt->bind_param(
    "sissssssssdddddssssssi",
    $quot_number, $client_id, $client_name, $client_email, $client_phone,
    $client_company, $client_address, $issue_date, $valid_until, $status,
    $subtotal, $tax_percent, $tax_amount, $discount_amount, $total_amount,
    $currency, $notes, $terms, $sig_name, $sig_title, $sig_date, $id
);
    $stmt->execute();

    // Replace line items
    $conn->query("DELETE FROM quotation_items WHERE quotation_id={$id}");
    $is = $conn->prepare("INSERT INTO quotation_items (quotation_id,description,quantity,unit_price,total,sort_order) VALUES (?,?,?,?,?,?)");
    foreach ($items as $it) {
        $is->bind_param("isdddi", $id, $it['description'], $it['quantity'], $it['unit_price'], $it['total'], $it['sort_order']);
        $is->execute();
    }

    echo "<script>alert('Quotation updated successfully!');window.location='quotation_details.php?id={$id}';</script>";
    exit;
}

// ── Load existing data ────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM quotations WHERE id=?");
$stmt->bind_param("i",$id); $stmt->execute();
$q = $stmt->get_result()->fetch_assoc();
if (!$q) { header('Location: manage_invoices.php?tab=quotations'); exit; }

$stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id=? ORDER BY sort_order");
$stmt->bind_param("i",$id); $stmt->execute();
$existingItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$clients = $conn->query("SELECT id,client_name,company,email,phone,address FROM clients ORDER BY client_name");
$cData   = [];
while ($c = $clients->fetch_assoc()) $cData[$c['id']] = $c;
?>

<div class="height-100">
<div class="page-header">
    <div>
        <h1>✏️ Edit Quotation</h1>
        <p>Editing <strong><?= htmlspecialchars($q['quotation_number']) ?></strong><?= $q['client_name'] ? ' · ' . htmlspecialchars($q['client_name']) : '' ?></p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="quotation_details.php?id=<?= $id ?>" class="btn btn-secondary">← Cancel</a>
    </div>
</div>

<div class="mp-tabs-container">
    <div class="mp-tab-buttons">
        <button type="button" class="active" onclick="showTab('cli')"><span class="mp-tab-icon">🏢</span> Client</button>
        <button type="button"                onclick="showTab('pri')"><span class="mp-tab-icon">💰</span> Pricing</button>
        <button type="button"                onclick="showTab('nts')"><span class="mp-tab-icon">📝</span> Notes & Terms</button>
        <button type="button"                onclick="showTab('sig')"><span class="mp-tab-icon">✍️</span> Signature</button>
    </div>
</div>

<form method="POST" id="quotForm">

<!-- ── Tab 1: Client ───────────────────────────────────────────────────────── -->
<div id="cli" class="tab active">
    <h3 style="margin-bottom:16px;">Client Information</h3>

    <div class="form-group">
        <label>Existing Client <span style="color:#aaa;font-weight:400;">(auto-fills fields below)</span></label>
        <select onchange="autofill(this.value)">
            <option value="">— Select to auto-fill —</option>
            <?php foreach ($cData as $cid => $c): ?>
            <option value="<?= $cid ?>" <?= $q['client_id']==$cid ? 'selected':'' ?>>
                <?= htmlspecialchars($c['client_name']) ?><?= $c['company'] ? ' — '.htmlspecialchars($c['company']):'' ?>
            </option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="client_id" id="hidCid" value="<?= $q['client_id'] ?>">
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label>Client Name <span class="required">*</span></label>
            <input type="text" name="client_name" id="fName" value="<?= htmlspecialchars($q['client_name']) ?>" required>
        </div>
        <div class="form-group">
            <label>Company</label>
            <input type="text" name="client_company" id="fCompany" value="<?= htmlspecialchars($q['client_company'] ?? '') ?>">
        </div>
    </div>
    <div class="form-grid">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="client_email" id="fEmail" value="<?= htmlspecialchars($q['client_email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Phone</label>
            <input type="text" name="client_phone" id="fPhone" value="<?= htmlspecialchars($q['client_phone'] ?? '') ?>">
        </div>
    </div>
    <div class="form-group">
        <label>Address</label>
        <textarea name="client_address" id="fAddress" style="min-height:70px;"><?= htmlspecialchars($q['client_address'] ?? '') ?></textarea>
    </div>

    <hr style="border:none;border-top:1px solid #f0f0f0;margin:20px 0;">
    <h3 style="margin-bottom:16px;">Quotation Details</h3>

    <div class="form-grid">
        <div class="form-group">
            <label>Quotation Number <span class="required">*</span></label>
            <input type="text" name="quotation_number" value="<?= htmlspecialchars($q['quotation_number']) ?>" required>
        </div>
        <div class="form-group">
            <label>Currency</label>
            <select name="currency">
                <?php foreach (['PKR'=>'PKR — Pakistani Rupee','USD'=>'USD — US Dollar','EUR'=>'EUR — Euro','GBP'=>'GBP — British Pound'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $q['currency']===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-grid">
        <div class="form-group">
            <label>Issue Date <span class="required">*</span></label>
            <input type="date" name="issue_date" value="<?= $q['issue_date'] ?>" required>
        </div>
        <div class="form-group">
            <label>Valid Until <span class="required">*</span></label>
            <input type="date" name="valid_until" value="<?= $q['valid_until'] ?>" required>
        </div>
    </div>
    <div class="form-group">
        <label>Status</label>
        <select name="status">
            <?php foreach (['draft'=>'Draft','sent'=>'Sent to Client','accepted'=>'Accepted','rejected'=>'Rejected'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= $q['status']===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- ── Tab 2: Pricing ──────────────────────────────────────────────────────── -->
<div id="pri" class="tab">
    <h3 style="margin-bottom:6px;">Pricing Table</h3>
    <p style="color:#888;font-size:13.5px;margin-bottom:16px;">Update services and deliverables included in this quotation.</p>

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

    <div class="form-grid" style="margin-top:14px;">
        <div class="form-group">
            <label>Tax %</label>
            <input type="number" name="tax_percent" id="taxPct" value="<?= $q['tax_percent'] ?>" step="0.01" min="0" max="100" oninput="recalc()">
        </div>
        <div class="form-group">
            <label>Discount Amount</label>
            <input type="number" name="discount_amount" id="discAmt" value="<?= $q['discount_amount'] ?>" step="0.01" min="0" oninput="recalc()">
        </div>
    </div>

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
        <label>Project Description / Notes</label>
        <textarea name="notes" style="min-height:130px;"><?= htmlspecialchars($q['notes'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label>Terms & Conditions</label>
        <textarea name="terms" style="min-height:130px;"><?= htmlspecialchars($q['terms'] ?? '') ?></textarea>
    </div>
</div>

<!-- ── Tab 4: Signature ─────────────────────────────────────────────────────── -->
<div id="sig" class="tab">
    <h3 style="margin-bottom:6px;">Signature & Authorization</h3>
    <p style="color:#888;font-size:13.5px;margin-bottom:20px;">Appears at the bottom of the PDF.</p>

    <div class="sig-preview-box">
        <div class="sig-preview-label">PDF Preview</div>
        <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:20px;">
            <div>
                <div class="sig-underline"></div>
                <div class="sig-pname"  id="pName"><?= htmlspecialchars($q['signature_name']  ?: 'Your Name') ?></div>
                <div class="sig-ptitle" id="pTitle"><?= htmlspecialchars($q['signature_title'] ?: 'Your Title') ?></div>
                <div class="sig-pdate"  id="pDate">Date: <?= !empty($q['signature_date']) ? date('M d, Y', strtotime($q['signature_date'])) : date('M d, Y') ?></div>
                <div class="sig-pco">Graphicafix Creative Agency</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:11px;color:#aaa;margin-bottom:6px;">Client Acceptance</div>
                <div class="sig-underline" style="margin-left:auto;"></div>
                <div class="sig-pname" style="color:#ccc;">Client Signature</div>
                <div class="sig-pdate" style="color:#ccc;">Date: _______________</div>
            </div>
        </div>
    </div>

    <div class="form-grid" style="margin-top:20px;">
        <div class="form-group">
            <label>Signatory Name</label>
            <input type="text" name="signature_name" value="<?= htmlspecialchars($q['signature_name'] ?? '') ?>"
                   placeholder="e.g. Ahmad Hassan"
                   oninput="document.getElementById('pName').textContent=this.value||'Your Name'">
        </div>
        <div class="form-group">
            <label>Title / Designation</label>
            <input type="text" name="signature_title" value="<?= htmlspecialchars($q['signature_title'] ?? '') ?>"
                   placeholder="e.g. Creative Director"
                   oninput="document.getElementById('pTitle').textContent=this.value||'Your Title'">
        </div>
    </div>
    <div class="form-group">
        <label>Signature Date</label>
        <input type="date" name="signature_date" value="<?= $q['signature_date'] ?? date('Y-m-d') ?>"
               oninput="updateSigDate(this.value)">
    </div>
</div>

<div class="form-actions">
    <button type="button" class="btn btn-secondary" id="prevBtn" onclick="prevTab()" style="display:none;">← Previous</button>
    <button type="button" class="btn btn-secondary" onclick="window.location='quotation_details.php?id=<?= $id ?>'">Cancel</button>
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
.sig-preview-box{background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:22px 26px;}
.sig-preview-label{font-size:10px;font-weight:700;color:#024442;text-transform:uppercase;letter-spacing:.12em;margin-bottom:14px;}
.sig-underline{width:180px;height:1px;background:#333;margin-bottom:6px;}
.sig-pname{font-size:15px;font-weight:700;color:#111;}
.sig-ptitle{font-size:12.5px;color:#555;margin-top:2px;}
.sig-pdate{font-size:11.5px;color:#888;margin-top:3px;}
.sig-pco{font-size:13px;font-weight:700;color:#024442;margin-top:7px;}
</style>

<script>
const TABS=['cli','pri','nts','sig'];
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

const cData=<?= json_encode($cData) ?>;
function autofill(id){
    document.getElementById('hidCid').value=id;
    if(!id||!cData[id])return;
    const c=cData[id];
    document.getElementById('fName').value=c.client_name||'';
    document.getElementById('fCompany').value=c.company||'';
    document.getElementById('fEmail').value=c.email||'';
    document.getElementById('fPhone').value=c.phone||'';
    document.getElementById('fAddress').value=c.address||'';
}

function updateSigDate(val){
    const d=val?new Date(val+' 00:00:00').toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}):'';
    document.getElementById('pDate').textContent='Date: '+d;
}

let rowCnt=0;
function addRow(desc='',qty=1,price=''){
    const i=rowCnt++;
    const tr=document.createElement('tr');
    tr.id='r'+i;
    tr.innerHTML=`
        <td><input type="text"   name="items[${i}][description]" value="${desc.replace(/"/g,'&quot;')}" placeholder="Service or deliverable description"></td>
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

const existing=<?= json_encode($existingItems) ?>;
existing.forEach(it=>addRow(it.description,it.quantity,it.unit_price));
if(!existing.length) addRow();
</script>
<?php include('dashboard_footer.php'); ?>