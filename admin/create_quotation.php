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
$username = $_SESSION['username'] ?? '';
$user_stmt = $conn->prepare("SELECT role FROM users WHERE username = ?");
$user_stmt->bind_param("s", $username);
$user_stmt->execute();
$role_data = $user_stmt->get_result()->fetch_assoc();
$role = strtolower($role_data['role'] ?? 'user');

if (!in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'hrm', 'user', 'sales'])) {
    header('location:index.php');
    exit;
}

// ── Handle POST ───────────────────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $client_id       = intval($_POST['client_id']      ?? 0) ?: null;
    $client_name     = trim($_POST['client_name'] ?? '');
    $client_email    = trim($_POST['client_email']    ?? '');
    $client_phone    = trim($_POST['client_phone']    ?? '');
    $client_company  = trim($_POST['client_company']  ?? '');
    $client_address  = trim($_POST['client_address']  ?? '');

    if (empty($client_name)) {
        if ($client_id) {
            $c_res = $conn->query("SELECT client_name, company, email, phone, address FROM clients WHERE id = " . intval($client_id));
            if ($c_res && $c_row = $c_res->fetch_assoc()) {
                $client_name = $c_row['client_name'] ?? '';
                if (empty($client_company)) $client_company = $c_row['company'] ?? '';
                if (empty($client_email)) $client_email = $c_row['email'] ?? '';
                if (empty($client_phone)) $client_phone = $c_row['phone'] ?? '';
                if (empty($client_address)) $client_address = $c_row['address'] ?? '';
            }
        }
        if (empty($client_name)) {
            $client_name = 'Prospective Client';
        }
    }
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

    // Bank Remittance Details
    $bank_name          = trim($_POST['bank_name'] ?? '');
    $bank_branch        = trim($_POST['bank_branch'] ?? '');
    $account_name       = trim($_POST['account_name'] ?? '');
    $account_number     = trim($_POST['account_number'] ?? '');
    $swift_code         = trim($_POST['swift_code'] ?? '');
    $payment_methods    = trim($_POST['payment_methods'] ?? '');

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
         scope_included,scope_excluded,payment_terms,delivery_time,amount_in_words,scope_notes,
         bank_name,bank_branch,account_name,account_number,swift_code,payment_methods,
         created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    
    $stmt->bind_param("sissssssssdddddsssssssssssssssssssssss",
        $quot_number,$client_id,$client_name,$client_email,$client_phone,$client_company,$client_address,
        $issue_date,$valid_until,$status,$subtotal,$tax_percent,$tax_amount,$discount_amount,$total_amount,
        $currency,$notes,$terms,$sig_name,$sig_title,$sig_date,
        $client_ref_rfq,$client_tax_id,$scope_project_name,$scope_deliverables,$scope_duration,
        $scope_included,$scope_excluded,$payment_terms,$delivery_time,$amount_in_words,$scope_notes,
        $bank_name,$bank_branch,$account_name,$account_number,$swift_code,$payment_methods
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

// ── Load saved bank accounts ──────────────────────────────────────────────────
$savedBankAccounts = [];
$bRes = $conn->query("SELECT id, bank_name, account_title, account_number, status FROM bank_accounts WHERE status = 'active' ORDER BY id ASC");
if ($bRes) {
    while ($ba = $bRes->fetch_assoc()) {
        $savedBankAccounts[] = $ba;
    }
}

// ── Next quotation number ─────────────────────────────────────────────────────
$lastRow = $conn->query("SELECT quotation_number FROM quotations ORDER BY id DESC LIMIT 1")->fetch_assoc();
$nextNum = 'QUO-' . date('Y') . '-001';
if ($lastRow) { 
    preg_match('/(\d+)$/', $lastRow['quotation_number'], $m); 
    $nextNum = 'QUO-' . date('Y') . '-' . str_pad((intval($m[1]??0)+1), 3, '0', STR_PAD_LEFT); 
}

include 'dashboard_header.php';
?>

<style>
/* Modern Scope Builder UI */
.scope-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 22px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    transition: border-color 0.2s, box-shadow 0.2s;
}
.scope-card:focus-within {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
}
.scope-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f5f9;
}
.scope-card-title {
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}
.scope-card-title i {
    color: #024442;
    font-size: 16px;
}
.scope-badge-count {
    background: #e6f4f1;
    color: #024442;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
    letter-spacing: 0.3px;
}
.scope-input-modern {
    width: 100%;
    padding: 10px 14px;
    font-size: 14px;
    color: #1e293b;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    transition: border-color 0.15s, box-shadow 0.15s;
    outline: none;
    box-sizing: border-box;
    font-family: inherit;
}
.scope-input-modern:focus {
    border-color: #024442;
    box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.12);
}
.scope-table-wrap {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}
.scope-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}
.scope-table thead th {
    background: #f8fafc;
    color: #475569;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 14px;
    border-bottom: 1px solid #e2e8f0;
}
.scope-table tbody tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s;
}
.scope-table tbody tr:hover {
    background: #fcfdfe;
}
.scope-table tbody tr:last-child {
    border-bottom: none;
}
.scope-table tbody td {
    padding: 10px 12px;
    vertical-align: middle;
}
.scope-row-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
}
.scope-btn-del {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid #fecaca;
    background: #fff;
    color: #dc2626;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
}
.scope-btn-del:hover {
    background: #dc2626;
    color: #fff;
    border-color: #dc2626;
    transform: scale(1.05);
}
.scope-item-bullet-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    background: #ffffff;
    padding: 6px 10px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.scope-item-bullet-row:focus-within {
    border-color: #024442;
    box-shadow: 0 0 0 2px rgba(2, 68, 66, 0.08);
}
.scope-icon-pill {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
}
.scope-icon-pill.inc {
    background: #dcfce7;
    color: #16a34a;
}
.scope-icon-pill.exc {
    background: #fee2e2;
    color: #dc2626;
}
.scope-icon-pill.note {
    background: #f1f5f9;
    color: #475569;
}
.scope-subcard {
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    padding: 16px;
    background: #f8fafc;
}
.scope-subcard.inc {
    background: #fcfdfa;
    border-color: #bbf7d0;
}
.scope-subcard.exc {
    background: #fffbfa;
    border-color: #fecaca;
}
.scope-subcard-title {
    font-size: 13.5px;
    font-weight: 700;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.scope-subcard-sub {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 12px;
}
.scope-empty-state {
    text-align: center;
    padding: 24px 16px;
    color: #94a3b8;
    font-size: 13px;
}
@media (max-width: 768px) {
    .scope-boundaries-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>
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
                <label>Select Existing Client <span style="font-weight: normal; color: #64748b; font-size: 13px;">(Optional — leave blank for new/fresh clients)</span></label>
                <select name="client_id" id="fClientId" onchange="autofill(this.value)" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 14px;">
                    <option value="">— New / Fresh Client (Enter details below) —</option>
                    <?php foreach ($cData as $cid => $c): ?>
                        <option value="<?php echo $cid; ?>"><?php echo htmlspecialchars($c['client_name']); ?> <?php echo !empty($c['company']) ? '('.htmlspecialchars($c['company']).')' : ''; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Client details -->
            <div class="form-grid" style="margin-top: 15px;">
                <div class="form-group">
                    <label>Client Name / Contact Person <span class="required">*</span></label>
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
                    <input type="email" name="client_email" id="fEmail" placeholder="Email Address">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="client_phone" id="fPhone" placeholder="Phone Number">
                </div>
            </div>
            <div class="form-group">
                <label>Billing Address</label>
                <textarea name="client_address" id="fAddress" placeholder="Billing Address" style="min-height: 70px;"></textarea>
            </div>

            <!-- Tax and Reference details -->
            <div class="form-grid">
                <div class="form-group">
                    <label>Client Tax ID / VAT Number</label>
                    <input type="text" name="client_tax_id" id="fClientTaxId" placeholder="Tax ID / STRN">
                </div>
                <div class="form-group">
                    <label>Client RFQ / Reference Number</label>
                    <input type="text" name="client_ref_rfq" id="fClientRefRfq" placeholder="Reference / RFQ Number">
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
                    <input type="text" name="delivery_time" placeholder="Delivery Time / Project Duration" value="">
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
        <!-- Card 1: Project Overview & Background -->
        <div class="scope-card">
            <div class="scope-card-header">
                <h3 class="scope-card-title"><i class="fas fa-project-diagram"></i> Project Overview & Scope Description</h3>
                <span style="font-size:12px; color:#64748b;">Primary engagement details for the proposal</span>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label style="font-weight:700; color:#1e293b; margin-bottom:6px; display:block;">Project / Engagement Title <span class="required" style="color:#ef4444;">*</span></label>
                <input type="text" name="scope_project_name" required placeholder="Project Title" class="scope-input-modern" style="font-weight:600; font-size:15px;" value="">
            </div>
            <div class="form-group" style="margin-bottom:18px;">
                <label style="font-weight:700; color:#1e293b; margin-bottom:6px; display:block;">Scope Overview & Objectives</label>
                <textarea name="notes" placeholder="Detailed project scope overview, business objectives, and delivery expectations..." class="scope-input-modern" style="min-height:100px; resize:vertical; line-height:1.6;"></textarea>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <label style="font-weight:700; color:#1e293b; margin:0; font-size:13px;"><i class="fas fa-sticky-note" style="color:#64748b; margin-right:4px;"></i> Important Engagement Notes & Assumptions</label>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addNoteBullet()" style="padding:4px 12px; font-size:12px; font-weight:600;"><i class="fas fa-plus"></i> Add Note</button>
                </div>
                <div id="notesBulletsContainer"></div>
            </div>
        </div>

        <!-- Card 2: Scope Deliverables & Milestones Table -->
        <div class="scope-card">
            <div class="scope-card-header">
                <div style="display:flex; align-items:center; gap:10px;">
                    <h3 class="scope-card-title"><i class="fas fa-layer-group"></i> Scope Deliverables & Specifications</h3>
                    <span id="scopeCountBadge" class="scope-badge-count">0 Deliverables</span>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="addScopeRow()" style="padding:6px 14px; font-size:12px; font-weight:700; background:#024442; border-color:#024442;">
                    <i class="fas fa-plus"></i> Add Deliverable
                </button>
            </div>
            <div class="scope-table-wrap">
                <table class="scope-table">
                    <thead>
                        <tr>
                            <th style="width:5%; text-align:center;">#</th>
                            <th style="width:38%;">Deliverable / Phase Title</th>
                            <th style="width:49%;">Detailed Specifications & Requirements</th>
                            <th style="width:8%; text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="scopeBody"></tbody>
                </table>
                <div id="scopeEmptyState" class="scope-empty-state" style="display:none;">
                    <i class="fas fa-cubes" style="font-size:28px; color:#cbd5e1; margin-bottom:8px; display:block;"></i>
                    No deliverables added yet. Click <strong>"+ Add Deliverable"</strong> above to specify key project milestones.
                </div>
            </div>
        </div>

        <!-- Card 3: Project Boundaries (Inclusions & Exclusions) -->
        <div class="scope-card" style="margin-bottom:10px;">
            <div class="scope-card-header">
                <h3 class="scope-card-title"><i class="fas fa-shield-alt"></i> Project Boundaries: Inclusions & Exclusions</h3>
                <span style="font-size:12px; color:#64748b;">Clarifies clear expectations and prevents scope creep</span>
            </div>
            <div class="scope-boundaries-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Inclusions Card -->
                <div class="scope-subcard inc">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <div class="scope-subcard-title" style="color:#166534;"><i class="fas fa-check-circle" style="color:#16a34a;"></i> What's Included</div>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="addIncludedBullet()" style="padding:3px 10px; font-size:11.5px; font-weight:700; border-radius:6px; background:#fff; border-color:#16a34a; color:#16a34a;">
                            <i class="fas fa-plus"></i> Add Item
                        </button>
                    </div>
                    <div class="scope-subcard-sub">Guaranteed items & services covered under this quotation</div>
                    <div id="includedBulletsContainer"></div>
                </div>

                <!-- Exclusions Card -->
                <div class="scope-subcard exc">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <div class="scope-subcard-title" style="color:#991b1b;"><i class="fas fa-times-circle" style="color:#dc2626;"></i> What's Excluded</div>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="addExcludedBullet()" style="padding:3px 10px; font-size:11.5px; font-weight:700; border-radius:6px; background:#fff; border-color:#dc2626; color:#dc2626;">
                            <i class="fas fa-plus"></i> Add Item
                        </button>
                    </div>
                    <div class="scope-subcard-sub">Out-of-scope tasks, additional iterations, or third-party licenses</div>
                    <div id="excludedBulletsContainer"></div>
                </div>
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

            <!-- Remittance Bank Details -->
            <hr style="border:none; border-top:1px solid #f0f0f0; margin: 25px 0;">
            <h3 style="margin-bottom:16px; font-size:15px; font-weight:700; color:#024442;"><i class="fas fa-university" style="margin-right:6px;"></i> Remittance Bank Details</h3>
            
            <?php if (!empty($savedBankAccounts)): ?>
            <div class="form-group" style="margin-bottom:15px;">
                <label>Select Saved Bank Account <span style="font-weight:normal; color:#64748b; font-size:13px;">(Optional — autofills bank fields below)</span></label>
                <select id="savedBankSelect" onchange="fillBankDetails(this.value)" style="width:100%; padding:10px; border-radius:6px; border:1px solid #cbd5e1; font-size:14px;">
                    <option value="">— Choose a saved bank account —</option>
                    <?php foreach ($savedBankAccounts as $bIdx => $bAcc): ?>
                        <option value="<?= $bIdx ?>"><?= htmlspecialchars($bAcc['bank_name']) ?> — <?= htmlspecialchars($bAcc['account_title']) ?> (<?= htmlspecialchars($bAcc['account_number']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label>Bank Name</label>
                    <input type="text" name="bank_name" id="fBankName" value="Meezan Bank Ltd">
                </div>
                <div class="form-group">
                    <label>Branch</label>
                    <input type="text" name="bank_branch" id="fBankBranch" value="Sector F-11 Branch, Islamabad">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Account Name / Title</label>
                    <input type="text" name="account_name" id="fAccountName" value="Graphica Fix">
                </div>
                <div class="form-group">
                    <label>Account Number / IBAN</label>
                    <input type="text" name="account_number" id="fAccountNumber" value="PK09MEZN000810011299029">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>SWIFT / BIC Code</label>
                    <input type="text" name="swift_code" id="fSwiftCode" value="MEZNPKKA">
                </div>
                <div class="form-group">
                    <label>Accepted Payment Methods</label>
                    <input type="text" name="payment_methods" id="fPaymentMethods" value="Bank Transfer, JazzCash, Safepay">
                </div>
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
let prevSelectedClientId = '';
function autofill(val) {
    if (!val) {
        if (prevSelectedClientId) {
            document.getElementById('fName').value = '';
            document.getElementById('fCompany').value = '';
            document.getElementById('fEmail').value = '';
            document.getElementById('fPhone').value = '';
            document.getElementById('fAddress').value = '';
        }
        prevSelectedClientId = '';
        return;
    }
    prevSelectedClientId = val;
    const c = cData[val];
    if (c) {
        document.getElementById('fName').value = c.client_name || '';
        document.getElementById('fCompany').value = c.company     || '';
        document.getElementById('fEmail').value = c.email       || '';
        document.getElementById('fPhone').value = c.phone       || '';
        document.getElementById('fAddress').value = c.address     || '';
    }
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
    return document.getElementById('fCurrency')?.value || 'USD';
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
        <td><input type="text"   name="items[${i}][description]" value="${(desc || '').replace(/"/g, '&quot;')}" placeholder="Service / Item Description" required style="width:100%; border:1px solid #ccc; padding:6px; border-radius:4px;"></td>
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
function updateScopeCountBadge() {
    const rows = document.querySelectorAll('#scopeBody tr');
    const badge = document.getElementById('scopeCountBadge');
    const emptyState = document.getElementById('scopeEmptyState');
    if (badge) {
        badge.textContent = rows.length + (rows.length === 1 ? ' Deliverable' : ' Deliverables');
    }
    if (emptyState) {
        emptyState.style.display = (rows.length === 0) ? 'block' : 'none';
    }
    rows.forEach((tr, idx) => {
        const numSpan = tr.querySelector('.scope-row-num');
        if (numSpan) numSpan.textContent = idx + 1;
    });
}

function addScopeRow(desc = '', details = '') {
    const i = scopeCnt++;
    const tr = document.createElement('tr');
    tr.id = 'scope_r' + i;
    tr.innerHTML = `
        <td style="text-align:center;"><span class="scope-row-num">1</span></td>
        <td>
            <input type="text" name="scope_desc[]" value="${desc.replace(/"/g, '&quot;')}" placeholder="Deliverable / Phase Title" required class="scope-input-modern" style="padding:8px 12px; font-size:13.5px; font-weight:600;">
        </td>
        <td>
            <input type="text" name="scope_details[]" value="${details.replace(/"/g, '&quot;')}" placeholder="Detailed specifications, milestones & requirements" required class="scope-input-modern" style="padding:8px 12px; font-size:13.5px;">
        </td>
        <td style="text-align:center;">
            <button type="button" class="scope-btn-del" onclick="removeScopeRow(${i})" title="Remove Deliverable">
                <i class="fas fa-trash-alt"></i>
            </button>
        </td>
    `;
    document.getElementById('scopeBody').appendChild(tr);
    updateScopeCountBadge();
}

function removeScopeRow(i) {
    document.getElementById('scope_r' + i)?.remove();
    updateScopeCountBadge();
}

// Payment Dynamic Rows
let paymentCnt = 0;
function addPaymentRow(milestone = '', amount = '') {
    const i = paymentCnt++;
    const tr = document.createElement('tr');
    tr.id = 'payment_r' + i;
    tr.innerHTML = `
        <td><input type="text" name="payment_milestone[]" value="${milestone.replace(/"/g, '&quot;')}" placeholder="Payment Milestone" style="width:100%; border:1px solid #cbd5e1; padding:8px 10px; border-radius:6px; font-size:13.5px; font-weight:600;"></td>
        <td><input type="text" name="payment_amount[]" value="${amount.replace(/"/g, '&quot;')}" placeholder="Amount / %" style="width:100%; border:1px solid #cbd5e1; padding:8px 10px; border-radius:6px; font-size:13.5px;"></td>
        <td style="text-align:center;"><button type="button" class="scope-btn-del" onclick="removePaymentRow(${i})" title="Remove Milestone"><i class="fas fa-trash-alt"></i></button></td>
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
    div.className = "scope-item-bullet-row";
    div.innerHTML = `
        <span class="scope-icon-pill inc"><i class="fas fa-check"></i></span>
        <input type="text" name="scope_included[]" value="${val.replace(/"/g, '&quot;')}" placeholder="Included deliverable item" class="scope-input-modern" style="border:none; padding:4px 6px; background:transparent; font-size:13px; font-weight:500;">
        <button type="button" class="scope-btn-del" style="width:26px; height:26px; font-size:11px;" onclick="this.closest('.scope-item-bullet-row').remove()" title="Remove item"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(div);
}

function addExcludedBullet(val = '') {
    const container = document.getElementById('excludedBulletsContainer');
    const div = document.createElement('div');
    div.className = "scope-item-bullet-row";
    div.innerHTML = `
        <span class="scope-icon-pill exc"><i class="fas fa-times"></i></span>
        <input type="text" name="scope_excluded[]" value="${val.replace(/"/g, '&quot;')}" placeholder="Excluded item / service" class="scope-input-modern" style="border:none; padding:4px 6px; background:transparent; font-size:13px; font-weight:500;">
        <button type="button" class="scope-btn-del" style="width:26px; height:26px; font-size:11px;" onclick="this.closest('.scope-item-bullet-row').remove()" title="Remove item"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(div);
}

function addNoteBullet(val = '') {
    const container = document.getElementById('notesBulletsContainer');
    const div = document.createElement('div');
    div.className = "scope-item-bullet-row";
    div.innerHTML = `
        <span class="scope-icon-pill note"><i class="fas fa-circle" style="font-size:7px;"></i></span>
        <input type="text" name="scope_notes[]" value="${val.replace(/"/g, '&quot;')}" placeholder="Project note / condition" class="scope-input-modern" style="border:none; padding:4px 6px; background:transparent; font-size:13px; font-weight:500;">
        <button type="button" class="scope-btn-del" style="width:26px; height:26px; font-size:11px;" onclick="this.closest('.scope-item-bullet-row').remove()" title="Remove note"><i class="fas fa-times"></i></button>
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
updateScopeCountBadge(); showTab('cli');

const savedBanks = <?php echo json_encode($savedBankAccounts); ?>;
function fillBankDetails(idx) {
    if (idx === '' || !savedBanks[idx]) return;
    const b = savedBanks[idx];
    if (b.bank_name) document.getElementById('fBankName').value = b.bank_name;
    if (b.account_title) document.getElementById('fAccountName').value = b.account_title;
    if (b.account_number) document.getElementById('fAccountNumber').value = b.account_number;
}

document.getElementById('fCurrency')?.addEventListener('change', recalc);

document.getElementById('quotForm').addEventListener('submit', function(e) {
    if (!document.getElementById('fName').value.trim()) {
        e.preventDefault();
        alert('Client name is required.');
        updateScopeCountBadge(); showTab('cli');
    }
});
</script>

<?php include 'dashboard_footer.php'; ?>