<?php
/**
 * admin/print_vendors.php
 * Print Vendors Management, Refresh Frequency Configuration & Rate Cards Manager
 * Styled with Graphicafix Global Design System
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/print_db.php';

// Authentication check
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? '';
$user_id  = $_SESSION['user_id'] ?? 0;
$role     = strtolower($_SESSION['role'] ?? 'user');

if (!in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'manager', 'sales'])) {
    header("Location: index.php?error=unauthorized");
    exit;
}

// Ensure tables exist & check expirations
if (isset($conn) && $conn instanceof mysqli) {
    init_print_database($conn);
    run_rates_expiration_check($conn);
}

// Handle Add/Edit Vendor Form Submission
$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vendor_action'])) {
    $act = $_POST['vendor_action'];
    if ($act === 'save_vendor') {
        $v_id        = intval($_POST['vendor_id'] ?? 0);
        $company     = trim($_POST['company_name'] ?? '');
        $contact     = trim($_POST['contact_person'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $whatsapp    = trim($_POST['whatsapp'] ?? $phone);
        $email       = trim($_POST['email'] ?? '');
        $city        = trim($_POST['city'] ?? 'Karachi');
        $address     = trim($_POST['address'] ?? '');
        $specialties = trim($_POST['specialties'] ?? '');
        $frequency   = max(1, intval($_POST['frequency_days'] ?? 1));
        $raw_pass    = trim($_POST['password'] ?? '');

        if (empty($company) || empty($contact) || empty($phone)) {
            $err = "Company Name, Contact Person, and Phone are required.";
        } else {
            if ($v_id > 0) {
                $stmt = $conn->prepare("UPDATE print_vendors SET company_name=?, contact_person=?, phone=?, whatsapp=?, email=?, city=?, address=?, specialties=?, price_refresh_frequency_days=? WHERE id=?");
                $stmt->bind_param("ssssssssii", $company, $contact, $phone, $whatsapp, $email, $city, $address, $specialties, $frequency, $v_id);
                $stmt->execute();

                if (!empty($raw_pass)) {
                    $p_hash = password_hash($raw_pass, PASSWORD_DEFAULT);
                    $p_stmt = $conn->prepare("UPDATE print_vendors SET password_hash = ? WHERE id = ?");
                    $p_stmt->bind_param("si", $p_hash, $v_id);
                    $p_stmt->execute();
                }

                $msg = "Vendor details updated successfully.";
                logActivity($conn, 'edit_vendor', "Updated vendor details for '$company' (ID: $v_id)", 'vendor', $v_id);
            } else {
                $token = bin2hex(random_bytes(16));
                $now = date('Y-m-d H:i:s');
                $p_hash = !empty($raw_pass) ? password_hash($raw_pass, PASSWORD_DEFAULT) : password_hash('vendor123', PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO print_vendors (company_name, contact_person, phone, whatsapp, email, city, address, specialties, price_refresh_frequency_days, last_price_refresh_at, access_token, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssssisss", $company, $contact, $phone, $whatsapp, $email, $city, $address, $specialties, $frequency, $now, $token, $p_hash);
                $stmt->execute();
                $msg = "New print vendor registered successfully (Default login password: " . (!empty($raw_pass) ? htmlspecialchars($raw_pass) : 'vendor123') . ").";
                logActivity($conn, 'create_vendor', "Created vendor '$company'", 'vendor', $conn->insert_id);
            }
        }
    }
}

// Fetch all vendors with rates summary
$vendors_query = "SELECT pv.*, 
    COUNT(pvr.id) AS total_rates_count,
    SUM(CASE WHEN pvr.status = 'active' AND pvr.expires_at >= NOW() THEN 1 ELSE 0 END) AS active_rates_count,
    SUM(CASE WHEN pvr.status = 'expired' OR pvr.expires_at < NOW() THEN 1 ELSE 0 END) AS expired_rates_count,
    MIN(TIMESTAMPDIFF(SECOND, NOW(), pvr.expires_at)) AS min_seconds_to_expiry
FROM print_vendors pv
LEFT JOIN print_vendor_rates pvr ON pvr.vendor_id = pv.id
GROUP BY pv.id
ORDER BY pv.company_name ASC";
$vendors_res = $conn->query($vendors_query);
$vendors = [];
$total_active_rates = 0;
$total_expired_rates = 0;

while ($v = $vendors_res->fetch_assoc()) {
    $vendors[] = $v;
    $total_active_rates += intval($v['active_rates_count'] ?? 0);
    $total_expired_rates += intval($v['expired_rates_count'] ?? 0);
}

include 'dashboard_header.php';
?>

<div class="height-100">

    <?php if ($msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius:10px;">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius:10px;">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($err) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 style="font-size:24px; font-weight:800; color:#111; margin:0 0 4px 0;">
                <i class="fas fa-warehouse" style="color:var(--primary, #024442);"></i> Print Vendors & Rate Cards
            </h1>
            <p style="font-size:13.5px; color:#6b7280; margin:0;">
                Manage print venture partners, validity frequencies, and rate refresh schedules.
            </p>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <a href="vendor_login.php" target="_blank" class="btn btn-secondary">
                <i class="fas fa-sign-in-alt"></i> Vendor Login Portal
            </a>
            <button type="button" class="btn btn-secondary" onclick="runExpirySweep()">
                <i class="fas fa-sync-alt"></i> Run Expiry Sweep
            </button>
            <a href="print_procurement.php" class="btn btn-secondary">
                <i class="fas fa-search-dollar"></i> Procurement Hub
            </a>
            <button type="button" class="btn btn-primary" onclick="openVendorModal()" style="font-weight:700;">
                <i class="fas fa-plus-circle"></i> Add Print Vendor
            </button>
        </div>
    </div>

    <!-- Stats Grid (Using Global Graphicafix .inv-stats-grid style) -->
    <div class="inv-stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom:20px;">
        <div class="inv-stat-card">
            <div class="inv-stat-icon" style="background:#f0f9f8; color:#024442;"><i class="fas fa-industry"></i></div>
            <div>
                <div class="inv-stat-num"><?= count($vendors) ?></div>
                <div class="inv-stat-label">Registered Vendors</div>
                <div class="inv-stat-sub">Active print partners</div>
            </div>
        </div>
        <div class="inv-stat-card">
            <div class="inv-stat-icon" style="background:#f0fff4; color:#22c55e;"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="inv-stat-num" style="color:#16a34a;"><?= $total_active_rates ?></div>
                <div class="inv-stat-label">Active Valid Rates</div>
                <div class="inv-stat-sub">Available for sales quoting</div>
            </div>
        </div>
        <div class="inv-stat-card">
            <div class="inv-stat-icon" style="background:#fff5f5; color:#e53e3e;"><i class="fas fa-clock"></i></div>
            <div>
                <div class="inv-stat-num" style="color:#e53e3e;"><?= $total_expired_rates ?></div>
                <div class="inv-stat-label">Stale / Expired Rates</div>
                <div class="inv-stat-sub">Pending vendor refresh</div>
            </div>
        </div>
    </div>

    <!-- Vendors Table Card -->
    <div class="card" style="padding:0; overflow:hidden; border-radius:14px; border:1px solid #e5e7eb; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-bottom:24px; background:#fff;">
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Vendor / Venture</th>
                        <th>Contact Person</th>
                        <th>Location & Phone</th>
                        <th>Refresh Frequency</th>
                        <th>Rate Cards & Expiry Status</th>
                        <th>Self-Service Portal</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vendors)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:35px; color:#888;">No print vendors registered yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vendors as $v): 
                            $minSec = intval($v['min_seconds_to_expiry'] ?? -1);
                            $totalR = intval($v['total_rates_count']);
                            $actR   = intval($v['active_rates_count']);
                            $expR   = intval($v['expired_rates_count']);
                            $magicUrl = BASE_URL . "admin/print_vendor_portal.php?token=" . urlencode($v['access_token']);

                            if ($totalR === 0) {
                                $badgeClass = 'status-default';
                                $badgeText = 'No Rates';
                            } elseif ($expR > 0 && $actR === 0) {
                                $badgeClass = 'status-not-started';
                                $badgeText = 'Expired';
                            } elseif ($minSec > 0 && $minSec <= 43200) {
                                $hours = max(1, round($minSec / 3600));
                                $badgeClass = 'status-on-hold';
                                $badgeText = "Expiring in {$hours}h";
                            } else {
                                $badgeClass = 'status-completed';
                                $badgeText = "{$actR} Active";
                            }
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight:700; color:#111; font-size:13.5px;"><?= htmlspecialchars($v['company_name']) ?></div>
                                    <div style="font-size:11.5px; color:#666;">
                                        <span style="color:#d97706;">★ <?= number_format($v['rating'], 1) ?></span> &bull; 
                                        <?= htmlspecialchars($v['specialties'] ?: 'Commercial Printing') ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:600; color:#333; font-size:13px;"><?= htmlspecialchars($v['contact_person']) ?></div>
                                    <div style="font-size:11.5px; color:#888;"><?= htmlspecialchars($v['email'] ?: 'No email') ?></div>
                                </td>
                                <td>
                                    <div style="font-size:13px; color:#444;"><i class="fas fa-phone text-muted"></i> <?= htmlspecialchars($v['phone']) ?></div>
                                    <div style="font-size:11.5px; color:#888;"><i class="fas fa-map-marker-alt text-muted"></i> <?= htmlspecialchars($v['city']) ?></div>
                                </td>
                                <td>
                                    <span class="badge" style="background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; font-weight:700; font-size:11.5px;">
                                        Every <?= intval($v['price_refresh_frequency_days']) ?> Day(s)
                                    </span>
                                    <div style="font-size:11px; color:#999; margin-top:2px;">
                                        <?= $v['last_price_refresh_at'] ? date('M d, H:i', strtotime($v['last_price_refresh_at'])) : 'Never' ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                                    <div style="font-size:11.5px; color:#666; margin-top:2px;">
                                        Total: <strong><?= $totalR ?></strong> (Active: <?= $actR ?> / Expired: <?= $expR ?>)
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:5px;">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyMagicLink('<?= $magicUrl ?>')" style="padding:3px 9px; font-size:11.5px; font-weight:600;" title="Copy Magic Link">
                                            <i class="fas fa-link"></i> Link
                                        </button>
                                        <a href="<?= $magicUrl ?>" target="_blank" class="btn btn-sm btn-outline-primary" style="padding:3px 9px; font-size:11.5px; font-weight:600;" title="Open Vendor Portal Directly">
                                            <i class="fas fa-external-link-alt"></i> Portal
                                        </a>
                                        <button type="button" class="btn btn-sm" onclick="shareVendorLoginWhatsApp('<?= addslashes(htmlspecialchars($v['company_name'])) ?>', '<?= htmlspecialchars($v['phone']) ?>', '<?= $magicUrl ?>')" style="background:#25d366; color:#fff; padding:3px 8px; font-size:11.5px;" title="Send Login via WhatsApp">
                                            <i class="fab fa-whatsapp"></i>
                                        </button>
                                    </div>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex; gap:6px;">
                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="quickConfirmRates(<?= $v['id'] ?>)" style="padding:4px 10px; font-size:12px; font-weight:700;" title="Re-confirm all rates now">
                                            <i class="fas fa-sync"></i> Re-confirm
                                        </button>
                                        <button type="button" class="action-btn" onclick='editVendor(<?= json_encode($v) ?>)' title="Edit Vendor">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ─────────────────────────────────────────────────────────────────────────────
     Add / Edit Vendor Modal
     ───────────────────────────────────────────────────────────────────────────── -->
<div class="modal fade" id="vendorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px; border:none; box-shadow:0 15px 35px rgba(0,0,0,0.18);">
            <div class="modal-header" style="background:#024442; color:#fff; padding:14px 20px;">
                <h5 class="modal-title" id="vendorModalTitle" style="font-size:15px; font-weight:700;"><i class="fas fa-warehouse"></i> Register Print Vendor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="vendor_action" value="save_vendor">
                <input type="hidden" name="vendor_id" id="modalVendorId" value="0">
                <div class="modal-body" style="padding:20px;">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight:700; font-size:12px; color:#555;">Company / Venture Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" id="modalCompany" class="form-control form-control-sm" required placeholder="e.g. Al-Madina Print Solutions">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight:700; font-size:12px; color:#555;">Contact Person <span class="text-danger">*</span></label>
                            <input type="text" name="contact_person" id="modalContact" class="form-control form-control-sm" required placeholder="e.g. Tariq Mehmood">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight:700; font-size:12px; color:#555;">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" name="phone" id="modalPhone" class="form-control form-control-sm" required placeholder="e.g. 03001234567">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight:700; font-size:12px; color:#555;">WhatsApp Number (for POs)</label>
                            <input type="text" name="whatsapp" id="modalWhatsapp" class="form-control form-control-sm" placeholder="e.g. 923001234567">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight:700; font-size:12px; color:#555;">Email Address</label>
                            <input type="email" name="email" id="modalEmail" class="form-control form-control-sm" placeholder="vendor@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight:700; font-size:12px; color:#555;">City</label>
                            <input type="text" name="city" id="modalCity" class="form-control form-control-sm" value="Karachi">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight:700; font-size:12px; color:#555;">Price Refresh Frequency (Days) <span class="text-danger">*</span></label>
                            <select name="frequency_days" id="modalFrequency" class="form-select form-select-sm">
                                <option value="1">1 Day (Daily Refresh - High Paper Volatility)</option>
                                <option value="3">3 Days (Twice Weekly)</option>
                                <option value="7">7 Days (Weekly)</option>
                                <option value="14">14 Days (Bi-Weekly)</option>
                                <option value="30">30 Days (Monthly)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight:700; font-size:12px; color:#555;">Specialties</label>
                            <input type="text" name="specialties" id="modalSpecialties" class="form-control form-control-sm" placeholder="e.g. offset, digital, packaging">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight:700; font-size:12px; color:#555;">Portal Password (for Vendor Login)</label>
                            <input type="text" name="password" id="modalPassword" class="form-control form-control-sm" placeholder="Default: vendor123 (leave blank to keep current)">
                        </div>
                        <div class="col-12">
                            <label class="form-label" style="font-weight:700; font-size:12px; color:#555;">Workshop Address</label>
                            <textarea name="address" id="modalAddress" class="form-control form-control-sm" rows="2" placeholder="Street, Plaza, City..."></textarea>
                        </div>
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding:14px 20px; border-top:1px solid #eee; background:#fafafa;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="font-weight:700; background:#024442; border-color:#024442;">Save Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Page Header */
.page-header { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:14px; margin-bottom:20px; }
.page-header h1 { margin:0 0 4px; }
.page-header p  { margin:0; color:#888; font-size:14px; }

/* Stats Grid */
.inv-stats-grid { display:grid; gap:14px; }
.inv-stat-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px 18px; display:flex; align-items:center; gap:14px; box-shadow:0 1px 3px rgba(0,0,0,0.04); }
.inv-stat-icon { width:46px; height:46px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.inv-stat-num  { font-size:22px; font-weight:800; color:#111; line-height:1; }
.inv-stat-label{ font-size:12.5px; color:#6b7280; font-weight:600; margin-top:4px; }
.inv-stat-sub  { font-size:11.5px; color:#9ca3af; margin-top:2px; }

/* Data Table */
.data-table { width:100%; border-collapse:collapse; font-size:13.5px; }
.data-table th { background:#f9fafb; padding:12px 16px; text-align:left; font-size:11.5px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.06em; border-bottom:1px solid #ebebeb; white-space:nowrap; }
.data-table td { padding:14px 16px; border-bottom:1px solid #f5f5f5; color:#333; vertical-align:middle; }
.data-table tr:last-child td { border-bottom:none; }
.data-table tr:hover td { background:#fafafa; }

/* Badges */
.status-completed   { background:#d1fae5; color:#065f46; font-weight:700; font-size:11px; padding:4px 8px; border-radius:12px; }
.status-in-progress { background:#dbeafe; color:#1e40af; font-weight:700; font-size:11px; padding:4px 8px; border-radius:12px; }
.status-on-hold     { background:#fef3c7; color:#92400e; font-weight:700; font-size:11px; padding:4px 8px; border-radius:12px; }
.status-not-started { background:#fee2e2; color:#991b1b; font-weight:700; font-size:11px; padding:4px 8px; border-radius:12px; }
.status-default     { background:#f3f4f6; color:#6b7280; font-weight:700; font-size:11px; padding:4px 8px; border-radius:12px; }

.action-btn { width:28px; height:28px; border-radius:6px; border:1px solid #e5e7eb; background:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; text-decoration:none; transition:all .15s; cursor:pointer; color:#555; }
.action-btn:hover { background:#f0f5f4; border-color:#024442; color:#024442; }
</style>

<script>
function openVendorModal() {
    document.getElementById("vendorModalTitle").innerHTML = '<i class="fas fa-warehouse"></i> Register Print Vendor';
    document.getElementById("modalVendorId").value = "0";
    document.getElementById("modalCompany").value = "";
    document.getElementById("modalContact").value = "";
    document.getElementById("modalPhone").value = "";
    document.getElementById("modalWhatsapp").value = "";
    document.getElementById("modalEmail").value = "";
    document.getElementById("modalCity").value = "Karachi";
    document.getElementById("modalFrequency").value = "1";
    document.getElementById("modalSpecialties").value = "";
    document.getElementById("modalAddress").value = "";
    new bootstrap.Modal(document.getElementById("vendorModal")).show();
}

function editVendor(v) {
    document.getElementById("vendorModalTitle").innerHTML = '<i class="fas fa-edit"></i> Edit Print Vendor';
    document.getElementById("modalVendorId").value = v.id;
    document.getElementById("modalCompany").value = v.company_name;
    document.getElementById("modalContact").value = v.contact_person;
    document.getElementById("modalPhone").value = v.phone;
    document.getElementById("modalWhatsapp").value = v.whatsapp;
    document.getElementById("modalEmail").value = v.email || "";
    document.getElementById("modalCity").value = v.city || "Karachi";
    document.getElementById("modalFrequency").value = v.price_refresh_frequency_days || 1;
    document.getElementById("modalSpecialties").value = v.specialties || "";
    document.getElementById("modalAddress").value = v.address || "";
    new bootstrap.Modal(document.getElementById("vendorModal")).show();
}

function copyMagicLink(url) {
    navigator.clipboard.writeText(url).then(() => {
        alert("Vendor self-service link copied to clipboard!\nShare this URL with the vendor via WhatsApp.");
    }).catch(err => {
        prompt("Copy this vendor link:", url);
    });
}

function shareVendorLoginWhatsApp(company, phone, portalUrl) {
    const text = encodeURIComponent(
        `Assalam-o-Alaikum ${company} Team,\n\n` +
        `Here is your Graphicafix Print Vendor Portal access to update your rate cards & pricing:\n\n` +
        `Portal Link: ${portalUrl}\n` +
        `Phone: ${phone}\n` +
        `Default Password: vendor123\n\n` +
        `Please confirm your rates daily to continue receiving print orders from our sales team.\n` +
        `Graphicafix Operations`
    );
    window.open(`https://wa.me/?text=${text}`, '_blank');
}

async function quickConfirmRates(vendorId) {
    if (!confirm("Re-confirm and extend all pricing for this vendor starting from NOW?")) return;
    try {
        const formData = new FormData();
        formData.append("action", "confirm_rates");
        formData.append("vendor_id", vendorId);
        const res = await fetch("../api/print_vendor_api.php", { method: "POST", body: formData });
        const json = await res.json();
        if (json.success) {
            alert(json.message);
            window.location.reload();
        } else {
            alert(json.message);
        }
    } catch (err) {
        alert("Error: " + err.message);
    }
}

async function runExpirySweep() {
    try {
        const res = await fetch("../api/print_cron.php");
        const json = await res.json();
        alert(json.message);
        window.location.reload();
    } catch (err) {
        alert("Error running sweep: " + err.message);
    }
}
</script>

<?php include 'dashboard_footer.php'; ?>