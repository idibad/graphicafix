<?php
/**
 * admin/print_vendor_portal.php
 * Interactive Print Vendor Dashboard & Live Pricing Manager
 * Bilingual Support (English & اردو) with Graphicafix Global Design
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/print_db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($conn) && $conn instanceof mysqli) {
    init_print_database($conn);
    run_rates_expiration_check($conn);
}

// Authentication: Either vendor session, token parameter, or admin preview
$token = trim($_GET['token'] ?? ($_SESSION['vendor_token'] ?? ''));
$vendor_id = intval($_SESSION['vendor_id'] ?? ($_GET['vendor_id'] ?? 0));
$admin_logged_in = isset($_SESSION['user_id']) || isset($_SESSION['username']);

$vendor = null;
if (!empty($token)) {
    $stmt = $conn->prepare("SELECT * FROM print_vendors WHERE access_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $vendor = $stmt->get_result()->fetch_assoc();
} elseif ($vendor_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM print_vendors WHERE id = ?");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $vendor = $stmt->get_result()->fetch_assoc();
}

// If not authenticated, redirect to vendor login
if (!$vendor) {
    header("Location: vendor_login.php");
    exit;
}

$v_id = intval($vendor['id']);
$freq = intval($vendor['price_refresh_frequency_days']);

// Fetch active catalog for adding new rates
$products_res = $conn->query("SELECT * FROM print_products WHERE is_active = 1 ORDER BY category ASC, name ASC");
$catalog = [];
while ($p = $products_res->fetch_assoc()) {
    $catalog[] = [
        'id' => intval($p['id']),
        'name' => $p['name'],
        'category' => $p['category'],
        'allowed_stocks' => json_decode($p['allowed_stocks'], true) ?: [],
        'allowed_finishes' => json_decode($p['allowed_finishes'], true) ?: [],
        'standard_sizes' => json_decode($p['standard_sizes'], true) ?: [],
        'unit_label' => $p['unit_label']
    ];
}
?>
<!DOCTYPE html>
<html lang="en" id="portalHtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($vendor['company_name']) ?> — Print Partner Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #024442;
            --primary-hover: #012e2c;
            --accent: #B6F763;
            --dark: #171717;
            --light: #f0ffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --radius-lg: 14px;
            --radius: 10px;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', 'Nunito', sans-serif;
            background: #f8fafc;
            color: #1e293b;
            padding: 20px 16px 80px 16px;
            margin: 0;
            line-height: 1.5;
        }
        body.lang-ur {
            font-family: 'Noto Nastaliq Urdu', 'Plus Jakarta Sans', sans-serif;
            direction: rtl;
            text-align: right;
        }
        .portal-wrap {
            max-width: 1060px;
            margin: 0 auto;
        }
        .card-custom {
            background: #ffffff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            margin-bottom: 18px;
            box-shadow: var(--shadow-sm);
        }
        .lang-switcher {
            display: inline-flex;
            background: #ffffff;
            border: 1px solid var(--gray-300);
            padding: 3px;
            border-radius: 20px;
            gap: 2px;
        }
        .lang-btn {
            background: transparent;
            border: none;
            color: var(--gray-600);
            padding: 4px 14px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .lang-btn.active {
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .btn-confirm-big {
            background: #16a34a;
            color: #ffffff;
            border: none;
            border-radius: 999px;
            padding: 10px 22px;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(22, 163, 74, 0.25);
        }
        .btn-confirm-big:hover {
            background: #15803d;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.35);
            color: #ffffff;
        }
        .btn-save-rates {
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 999px;
            padding: 9px 20px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-save-rates:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            color: #ffffff;
        }
        .btn-outline-custom {
            background: #ffffff;
            color: var(--primary);
            border: 1.5px solid var(--primary);
            border-radius: 999px;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-outline-custom:hover {
            background: var(--primary);
            color: #ffffff;
        }
        .rate-table {
            width: 100%;
            border-collapse: collapse;
        }
        .rate-table th {
            background: #f8fafc;
            padding: 12px 14px;
            font-size: 12px;
            font-weight: 700;
            color: var(--gray-600);
            border-bottom: 1.5px solid var(--gray-200);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .rate-table td {
            padding: 12px 14px;
            font-size: 13.5px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .rate-table tr:hover {
            background: #fcfdfe;
        }
        .rate-input-box {
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 13.5px;
            font-weight: 700;
            transition: all 0.15s ease;
            background: #ffffff;
        }
        .rate-input-box:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.12);
            outline: none;
        }
        .rate-input-box.changed {
            border-color: #f59e0b;
            background: #fffbeb;
        }
        .badge-status-valid {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-status-expired {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .floating-action-bar {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #024442;
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 999px;
            display: none;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
            z-index: 1000;
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { transform: translate(-50%, 20px); opacity: 0; }
            to { transform: translate(-50%, 0); opacity: 1; }
        }
    </style>
</head>
<body>

<div class="portal-wrap">

    <!-- Top Navigation & Language Switcher -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; align-items:center; gap:10px;">
            <div class="lang-switcher">
                <button type="button" class="lang-btn active" id="btnEn" onclick="switchLang('en')">English</button>
                <button type="button" class="lang-btn" id="btnUr" onclick="switchLang('ur')">اردو</button>
            </div>
            <span style="font-size:12.5px; color:var(--gray-500); display:flex; align-items:center; gap:5px;" id="tLangNotice">
                <i class="fas fa-globe"></i> English / اردو
            </span>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openFrequencyModal()" style="font-size:12px; font-weight:700; border-radius:20px; padding:5px 14px;">
                <i class="fas fa-clock text-primary"></i> <span id="tBtnCycle">Cycle: <?= $freq ?> Day(s)</span>
            </button>
            <?php if ($admin_logged_in): ?>
                <a href="print_procurement.php" class="btn btn-sm btn-outline-primary" style="font-size:12px; font-weight:700; border-radius:20px; padding:5px 14px;">
                    <i class="fas fa-search-dollar"></i> Procurement Hub
                </a>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="logoutVendor()" style="font-size:12px; font-weight:700; border-radius:20px; padding:5px 14px;">
                <i class="fas fa-sign-out-alt"></i> <span id="tBtnLogout">Logout</span>
            </button>
        </div>
    </div>

    <!-- Partner Header Card -->
    <div class="card-custom" style="border-top: 4px solid var(--primary);">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:14px;">
            <div>
                <span class="badge" style="background:#f0f9f8; color:var(--primary); border:1px solid #cce8e5; font-size:11.5px; font-weight:800; padding:4px 10px; border-radius:20px;" id="tBadgePartner">
                    GRAPHICAFIX VERIFIED PRINT PARTNER
                </span>
                <h3 style="font-size:1.45rem; font-weight:800; color:#0f172a; margin:8px 0 4px 0;">
                    <?= htmlspecialchars($vendor['company_name']) ?>
                </h3>
                <div style="font-size:13px; color:var(--gray-500);">
                    <i class="fas fa-user-circle"></i> <?= htmlspecialchars($vendor['contact_person']) ?> &bull; 
                    <i class="fas fa-phone"></i> <?= htmlspecialchars($vendor['phone']) ?> &bull; 
                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($vendor['city']) ?>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:11.5px; color:var(--gray-500); font-weight:700; text-transform:uppercase;" id="tRefreshRule">Pricing Validity Cycle</div>
                <div style="font-size:15px; font-weight:800; color:var(--primary);" id="tRefreshRuleVal">
                    Every <?= $freq ?> Day<?= $freq > 1 ? 's' : '' ?>
                </div>
                <div style="font-size:11.5px; color:var(--gray-400);" id="tExpiryNote">Auto-expires if not refreshed</div>
            </div>
        </div>
    </div>

    <!-- Live Expiry Alert Card & 1-Click Re-Confirmation -->
    <div id="expiryStatusCard" class="card-custom" style="background:#f0fff4; border:1.5px solid #86efac;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <div id="statusIcon" style="font-size:26px; color:#16a34a;"><i class="fas fa-check-circle"></i></div>
                <div>
                    <strong style="font-size:14.5px; color:#15803d;" id="tStatusTitle">Rates Are Currently Active & Live</strong>
                    <div style="font-size:12.5px; color:#475569;" id="tStatusDesc">Checking countdown timer...</div>
                </div>
            </div>
            <div>
                <button type="button" class="btn-confirm-big" onclick="oneClickConfirm()">
                    <i class="fas fa-sync-alt"></i> <span id="tBtnConfirmQuick">Re-Confirm Current Rates</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Rates Management Table Card (With Full Pricing Update Options!) -->
    <div class="card-custom">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
            <div>
                <h5 style="font-size:16px; font-weight:800; color:#0f172a; margin:0;" id="tRateCardsTitle">
                    <i class="fas fa-tags" style="color:var(--primary);"></i> Live Pricing & Rate Cards
                </h5>
                <p style="font-size:12.5px; color:var(--gray-500); margin:2px 0 0 0;" id="tRateCardsSub">
                    Update prices directly in the boxes below and click "Save All Prices", or add new product rates.
                </p>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" class="btn-outline-custom" onclick="openAddRateModal()">
                    <i class="fas fa-plus"></i> <span id="tBtnAddRate">Add Product Rate</span>
                </button>
                <button type="button" class="btn-save-rates" onclick="saveAllEditedRates()">
                    <i class="fas fa-save"></i> <span id="tBtnSaveAll">Save All Prices</span>
                </button>
            </div>
        </div>

        <!-- Feedback Alert -->
        <div id="tableAlert" style="display:none; padding:10px 14px; border-radius:10px; margin-bottom:14px; font-size:13px;"></div>

        <!-- Editable Rates Table -->
        <div style="overflow-x:auto;">
            <table class="rate-table">
                <thead>
                    <tr>
                        <th id="thProduct">Product & Material</th>
                        <th id="thSpecs">Specifications</th>
                        <th id="thMinQty">Min Qty</th>
                        <th id="thBatchPrice" style="width:145px;">Batch Price (PKR)</th>
                        <th id="thExtra" style="width:145px;">Extra / 1,000 (PKR)</th>
                        <th id="thTurnaround" style="width:95px;">Days</th>
                        <th id="thStatus">Status</th>
                        <th style="text-align:right;" id="thActions">Action</th>
                    </tr>
                </thead>
                <tbody id="ratesTableBody">
                    <tr>
                        <td colspan="8" style="text-align:center; padding:35px; color:#888;">
                            <i class="fas fa-spinner fa-spin"></i> Loading rates...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; padding-top:14px; border-top:1px solid #f1f5f9; flex-wrap:wrap; gap:12px;">
            <small style="color:var(--gray-500); font-size:12px;" id="tSaveTip">
                <i class="fas fa-lightbulb text-warning"></i> Tip: Changing any price and saving automatically extends validity for <?= $freq ?> days.
            </small>
            <button type="button" class="btn-save-rates" onclick="saveAllEditedRates()">
                <i class="fas fa-save"></i> <span id="tBtnSaveAllBottom">Save All Prices</span>
            </button>
        </div>
    </div>

</div>

<!-- Floating Action Bar for Unsaved Changes -->
<div id="floatingSaveBar" class="floating-action-bar">
    <div style="display:flex; align-items:center; gap:8px;">
        <i class="fas fa-exclamation-circle text-warning" style="font-size:18px;"></i>
        <span id="tUnsavedText" style="font-size:13.5px; font-weight:700;">You have unsaved price changes!</span>
    </div>
    <button type="button" class="btn btn-sm btn-success" onclick="saveAllEditedRates()" style="border-radius:20px; font-weight:800; padding:6px 16px;">
        <i class="fas fa-save"></i> <span id="tBtnFloatingSave">Save All Now</span>
    </button>
</div>

<!-- ─────────────────────────────────────────────────────────────────────────────
     Modal: Add New Product Rate Card
     ───────────────────────────────────────────────────────────────────────────── -->
<div class="modal fade" id="addRateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-lg); border:none; box-shadow:0 15px 35px rgba(0,0,0,0.18);">
            <div class="modal-header" style="background:var(--primary); color:#ffffff; padding:14px 20px;">
                <h6 class="modal-title" style="font-size:15px; font-weight:700;" id="tModalAddTitle">
                    <i class="fas fa-plus-circle"></i> Add New Product Rate
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addRateForm" onsubmit="submitNewRate(event)">
                <div class="modal-body" style="padding:20px;">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:12px; font-weight:700; color:var(--gray-700);" id="lblModalProduct">Select Product</label>
                        <select id="modalSelProduct" class="form-select form-select-sm" onchange="onModalProductChange()" required>
                            <option value="">-- Choose Product --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:12px; font-weight:700; color:var(--gray-700);" id="lblModalStock">Paper Stock / GSM</label>
                        <select id="modalSelStock" class="form-select form-select-sm" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:12px; font-weight:700; color:var(--gray-700);" id="lblModalFinish">Finishing / Lamination</label>
                        <select id="modalSelFinish" class="form-select form-select-sm" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:12px; font-weight:700; color:var(--gray-700);" id="lblModalSize">Standard Size</label>
                        <select id="modalSelSize" class="form-select form-select-sm" required></select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" style="font-size:12px; font-weight:700; color:var(--gray-700);" id="lblModalMinQty">Min Quantity</label>
                            <input type="number" id="modalMinQty" class="form-control form-control-sm" value="1000" min="50" step="50" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:12px; font-weight:700; color:var(--gray-700);" id="lblModalBatchPrice">Batch Price (PKR)</label>
                            <input type="number" id="modalBatchPrice" class="form-control form-control-sm" placeholder="e.g. 2500" required min="1">
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" style="font-size:12px; font-weight:700; color:var(--gray-700);" id="lblModalAddK">Extra per 1,000 (PKR)</label>
                            <input type="number" id="modalAddK" class="form-control form-control-sm" placeholder="e.g. 2100">
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:12px; font-weight:700; color:var(--gray-700);" id="lblModalDays">Turnaround Days</label>
                            <input type="number" id="modalDays" class="form-control form-control-sm" value="2" min="1" max="30" required>
                        </div>
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding:12px 20px; border-top:1px solid #eee; background:#fafafa;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnModalAddSubmit" class="btn btn-primary btn-sm" style="font-weight:700; background:var(--primary); border-color:var(--primary);">
                        Add Rate Card
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ─────────────────────────────────────────────────────────────────────────────
     Modal: Frequency Setting
     ───────────────────────────────────────────────────────────────────────────── -->
<div class="modal fade" id="freqModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius:var(--radius-lg); border:none; box-shadow:0 15px 35px rgba(0,0,0,0.18);">
            <div class="modal-header" style="background:var(--primary); color:#ffffff; padding:12px 18px;">
                <h6 class="modal-title" style="font-size:14px; font-weight:700;" id="tFreqModalTitle">Refresh Frequency</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:18px;">
                <label class="form-label" style="font-weight:700; font-size:12px; color:var(--gray-700);" id="tFreqLabel">How often do you refresh rates?</label>
                <select id="selFreqModal" class="form-select form-select-sm" style="font-weight:600;">
                    <option value="1" <?= $freq === 1 ? 'selected' : '' ?>>Every 1 Day (Daily / روزانہ)</option>
                    <option value="3" <?= $freq === 3 ? 'selected' : '' ?>>Every 3 Days (ہر 3 دن)</option>
                    <option value="7" <?= $freq === 7 ? 'selected' : '' ?>>Every 7 Days (Weekly / ہفتہ وار)</option>
                    <option value="14" <?= $freq === 14 ? 'selected' : '' ?>>Every 14 Days (پندرہ روزہ)</option>
                    <option value="30" <?= $freq === 30 ? 'selected' : '' ?>>Every 30 Days (Monthly / ماہانہ)</option>
                </select>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:8px; padding:12px 18px; border-top:1px solid #eee; background:#fafafa;">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveFrequency()" style="background:var(--primary); border-color:var(--primary); font-weight:700;">Save</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const VENDOR_ID = <?= $v_id ?>;
const TOKEN = "<?= htmlspecialchars($vendor['access_token']) ?>";
let CURRENT_RATES = [];
let CATALOG_DATA = <?= json_encode($catalog) ?>;
let CURRENT_LANG = localStorage.getItem('graphicafix_vendor_lang') || 'en';
let hasUnsavedChanges = false;

// Bilingual Dictionary
const DICT = {
    en: {
        partnerBadge: "GRAPHICAFIX VERIFIED PRINT PARTNER",
        refreshRule: "Pricing Validity Cycle",
        refreshVal: "Every <?= $freq ?> Day<?= $freq > 1 ? 's' : '' ?>",
        expiryNote: "Auto-expires if not refreshed",
        statusActive: "Rates Are Currently Active & Live",
        statusExpired: "Rates Are Stale / Expired",
        statusDescActive: (h) => `Valid for the next ${h} hours. Available for sales quotations.`,
        statusDescExpired: "Your rates have expired and are currently hidden from sales quotes. Update prices or click re-confirm.",
        btnConfirmQuick: "Re-Confirm Current Rates",
        rateCardsTitle: "Live Pricing & Rate Cards",
        rateCardsSub: "Update prices directly in the boxes below and click 'Save All Prices'.",
        btnAddRate: "Add Product Rate",
        btnSaveAll: "Save All Prices",
        saveTip: "Tip: Changing any price and saving automatically extends validity for <?= $freq ?> days.",
        thProduct: "Product & Material",
        thSpecs: "Specifications",
        thMinQty: "Min Qty",
        thBatchPrice: "Batch Price (PKR)",
        thExtra: "Extra / 1,000 (PKR)",
        thTurnaround: "Days",
        thStatus: "Status",
        thActions: "Action",
        btnCycle: "Cycle: <?= $freq ?> Day(s)",
        btnLogout: "Logout",
        unsavedText: "You have unsaved price changes!",
        btnFloatingSave: "Save All Now",
        modalAddTitle: "Add New Product Rate",
        lblProduct: "Select Product",
        lblStock: "Paper Stock / GSM",
        lblFinish: "Finishing / Coating",
        lblSize: "Standard Size",
        lblMinQty: "Min Quantity",
        lblBatchPrice: "Batch Price (PKR)",
        lblAddK: "Extra per 1,000 (PKR)",
        lblDays: "Turnaround Days",
        freqTitle: "Pricing Refresh Frequency",
        freqLabel: "How often do you refresh or change rates?",
        ratesSavedMsg: "Rates updated and extended successfully!"
    },
    ur: {
        partnerBadge: "گرافیکافکس تصدیق شدہ پرنٹنگ پارٹنر",
        refreshRule: "ریٹ کی میعاد و دورانیہ",
        refreshVal: "ہر <?= $freq ?> دن بعد",
        expiryNote: "بروقت اپ ڈیٹ نہ ہونے پر ریٹس ختم ہو جاتے ہیں",
        statusActive: "آپ کے ریٹس ایکٹو اور تصدیق شدہ ہیں",
        statusExpired: "ریٹس ایکسپائر ہو چکے ہیں",
        statusDescActive: (h) => `اگلے ${h} گھنٹوں تک ریٹس درست ہیں۔ سیلز ٹیم کو دستیاب ہیں۔`,
        statusDescExpired: "آپ کے ریٹس ایکسپائر ہو چکے ہیں۔ براہ کرم نیچے نئی قیمتیں درج کریں یا موجودہ ریٹ کنفرم کریں۔",
        btnConfirmQuick: "موجودہ ریٹس کی توثیق کریں",
        rateCardsTitle: "ریٹ لسٹ اور قیمتیں",
        rateCardsSub: "نیچے خانوں میں براہ راست نئی قیمتیں درج کریں اور 'تمام قیمتیں محفوظ کریں' پر کلک کریں۔",
        btnAddRate: "نیا ریٹ شامل کریں",
        btnSaveAll: "تمام قیمتیں محفوظ کریں",
        saveTip: "مشورہ: قیمت تبدیل کر کے محفوظ کرنے سے میعاد خودکار طور پر <?= $freq ?> دن بڑھ جاتی ہے۔",
        thProduct: "پروڈکٹ و آئٹم",
        thSpecs: "تفصیلات و کوالٹی",
        thMinQty: "تعداد",
        thBatchPrice: "بیچ ریٹ (روپے)",
        thExtra: "اضافی فی 1000",
        thTurnaround: "تیاری کے دن",
        thStatus: "حیثیت",
        thActions: "عمل",
        btnCycle: "دورانیہ: <?= $freq ?> دن",
        btnLogout: "لاگ آؤٹ",
        unsavedText: "آپ نے قیمتیں تبدیل کی ہیں جو ابھی محفوظ نہیں ہوئیں!",
        btnFloatingSave: "ابھی محفوظ کریں",
        modalAddTitle: "نیا پروڈکٹ ریٹ شامل کریں",
        lblProduct: "پروڈکٹ منتخب کریں",
        lblStock: "کاغذ کی کوالٹی / گرام",
        lblFinish: "لیمینیشن / فنشنگ",
        lblSize: "سائز",
        lblMinQty: "کم از کم تعداد",
        lblBatchPrice: "بیچ کی قیمت (روپے)",
        lblAddK: "اضافی فی 1000 (روپے)",
        lblDays: "تیاری کا وقت (دن)",
        freqTitle: "ریٹ تبدیل کرنے کا دورانیہ",
        freqLabel: "آپ عام طور پر ریٹس کتنے دن بعد تبدیل کرتے ہیں؟",
        ratesSavedMsg: "ریٹس کامیابی سے اپ ڈیٹ ہو گئے اور میعاد بڑھا دی گئی!"
    }
};

document.addEventListener("DOMContentLoaded", () => {
    switchLang(CURRENT_LANG);
    loadVendorData();
    populateModalProducts();
});

function switchLang(lang) {
    CURRENT_LANG = lang;
    localStorage.setItem('graphicafix_vendor_lang', lang);

    const body = document.body;
    const btnEn = document.getElementById("btnEn");
    const btnUr = document.getElementById("btnUr");

    if (lang === 'ur') {
        body.classList.add('lang-ur');
        btnUr.classList.add('active');
        btnEn.classList.remove('active');
    } else {
        body.classList.remove('lang-ur');
        btnEn.classList.add('active');
        btnUr.classList.remove('active');
    }

    const t = DICT[lang];
    document.getElementById("tBadgePartner").innerText = t.partnerBadge;
    document.getElementById("tRefreshRule").innerText = t.refreshRule;
    document.getElementById("tRefreshRuleVal").innerText = t.refreshVal;
    document.getElementById("tExpiryNote").innerText = t.expiryNote;
    document.getElementById("tBtnConfirmQuick").innerText = t.btnConfirmQuick;
    document.getElementById("tRateCardsTitle").innerHTML = `<i class="fas fa-tags" style="color:var(--primary);"></i> ${t.rateCardsTitle}`;
    document.getElementById("tRateCardsSub").innerText = t.rateCardsSub;
    document.getElementById("tBtnAddRate").innerText = t.btnAddRate;
    document.getElementById("tBtnSaveAll").innerText = t.btnSaveAll;
    document.getElementById("tBtnSaveAllBottom").innerText = t.btnSaveAll;
    document.getElementById("tBtnCycle").innerText = t.btnCycle;
    document.getElementById("tBtnLogout").innerText = t.btnLogout;
    document.getElementById("tSaveTip").innerHTML = `<i class="fas fa-lightbulb text-warning"></i> ${t.saveTip}`;
    document.getElementById("tUnsavedText").innerText = t.unsavedText;
    document.getElementById("tBtnFloatingSave").innerText = t.btnFloatingSave;

    // Table Headers
    document.getElementById("thProduct").innerText = t.thProduct;
    document.getElementById("thSpecs").innerText = t.thSpecs;
    document.getElementById("thMinQty").innerText = t.thMinQty;
    document.getElementById("thBatchPrice").innerText = t.thBatchPrice;
    document.getElementById("thExtra").innerText = t.thExtra;
    document.getElementById("thTurnaround").innerText = t.thTurnaround;
    document.getElementById("thStatus").innerText = t.thStatus;
    document.getElementById("thActions").innerText = t.thActions;

    // Modal
    document.getElementById("tModalAddTitle").innerHTML = `<i class="fas fa-plus-circle"></i> ${t.modalAddTitle}`;
    document.getElementById("lblModalProduct").innerText = t.lblProduct;
    document.getElementById("lblModalStock").innerText = t.lblStock;
    document.getElementById("lblModalFinish").innerText = t.lblFinish;
    document.getElementById("lblModalSize").innerText = t.lblSize;
    document.getElementById("lblModalMinQty").innerText = t.lblMinQty;
    document.getElementById("lblModalBatchPrice").innerText = t.lblBatchPrice;
    document.getElementById("lblModalAddK").innerText = t.lblAddK;
    document.getElementById("lblModalDays").innerText = t.lblDays;
    document.getElementById("tFreqModalTitle").innerText = t.freqTitle;
    document.getElementById("tFreqLabel").innerText = t.freqLabel;

    renderRatesTable();
}

async function loadVendorData() {
    try {
        const res = await fetch(`../api/print_vendor_api.php?action=get_vendor_data&vendor_id=${VENDOR_ID}&token=${TOKEN}`);
        const json = await res.json();
        if (json.success) {
            CURRENT_RATES = json.rates;
            updateExpiryCard(json.stats);
            renderRatesTable();
        }
    } catch (err) {
        console.error("Failed to load vendor data", err);
    }
}

function updateExpiryCard(stats) {
    const card = document.getElementById("expiryStatusCard");
    const icon = document.getElementById("statusIcon");
    const title = document.getElementById("tStatusTitle");
    const desc = document.getElementById("tStatusDesc");
    const t = DICT[CURRENT_LANG];

    if (stats.is_expired) {
        card.style.background = "#fff5f5";
        card.style.borderColor = "#fca5a5";
        icon.innerHTML = '<i class="fas fa-exclamation-circle" style="color:#e53e3e;"></i>';
        title.style.color = "#b91c1c";
        title.innerText = t.statusExpired;
        desc.innerText = t.statusDescExpired;
    } else {
        card.style.background = "#f0fff4";
        card.style.borderColor = "#86efac";
        icon.innerHTML = '<i class="fas fa-check-circle" style="color:#16a34a;"></i>';
        title.style.color = "#15803d";
        title.innerText = t.statusActive;
        desc.innerText = t.statusDescActive(stats.hours_left);
    }
}

function renderRatesTable() {
    const tbody = document.getElementById("ratesTableBody");
    if (!CURRENT_RATES || CURRENT_RATES.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align:center; padding:35px; color:#888;">
                    No rate cards found. Click "Add Product Rate" to configure your first rate card.
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    CURRENT_RATES.forEach((r, idx) => {
        const isExpired = (r.dynamic_status === 'expired');
        html += `
            <tr data-rate-id="${r.id}">
                <td>
                    <div style="font-weight:700; color:#111; font-size:13.5px;">${escapeHtml(r.product_name)}</div>
                    <div style="font-size:11.5px; color:#888;">${escapeHtml(r.product_category)}</div>
                </td>
                <td>
                    <div style="font-weight:600; color:var(--primary); font-size:13px;">${escapeHtml(r.paper_stock)}</div>
                    <div style="font-size:11.5px; color:#666;">${escapeHtml(r.finish_type)} &bull; ${escapeHtml(r.size)}</div>
                </td>
                <td>
                    <input type="number" class="rate-input-box rate-input-minqty" value="${r.min_quantity}" style="width:80px;" min="50" step="50" oninput="markChanged(this)">
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text" style="font-size:11px; background:#f8fafc;">PKR</span>
                        <input type="number" class="form-control form-control-sm rate-input-box rate-input-batch" value="${r.batch_price}" min="1" step="50" oninput="markChanged(this)" style="font-weight:700;">
                    </div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text" style="font-size:11px; background:#f8fafc;">PKR</span>
                        <input type="number" class="form-control form-control-sm rate-input-box rate-input-addk" value="${r.additional_per_thousand}" min="0" step="50" oninput="markChanged(this)" style="font-weight:700;">
                    </div>
                </td>
                <td>
                    <input type="number" class="rate-input-box rate-input-days" value="${r.turnaround_days}" style="width:65px;" min="1" max="30" oninput="markChanged(this)">
                </td>
                <td>
                    <span class="${isExpired ? 'badge-status-expired' : 'badge-status-valid'}">
                        <i class="fas ${isExpired ? 'fa-times-circle' : 'fa-check-circle'}"></i> 
                        ${isExpired ? (CURRENT_LANG === 'ur' ? 'ایکسپائر' : 'Expired') : (CURRENT_LANG === 'ur' ? 'ایکٹو' : 'Active')}
                    </span>
                </td>
                <td style="text-align:right;">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteRate(${r.id})" style="padding:3px 8px; font-size:11.5px; border-radius:6px;" title="Delete Rate Card">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function markChanged(inputEl) {
    inputEl.classList.add("changed");
    hasUnsavedChanges = true;
    const bar = document.getElementById("floatingSaveBar");
    bar.style.display = "flex";
}

// Save All Edited Rates in the Table
async function saveAllEditedRates() {
    const rows = document.querySelectorAll("#ratesTableBody tr[data-rate-id]");
    if (rows.length === 0) return;

    const updates = [];
    rows.forEach(row => {
        const rateId = parseInt(row.getAttribute("data-rate-id"));
        const minQty = parseInt(row.querySelector(".rate-input-minqty").value) || 1000;
        const batchPrice = parseFloat(row.querySelector(".rate-input-batch").value) || 0;
        const addK = parseFloat(row.querySelector(".rate-input-addk").value) || 0;
        const days = parseInt(row.querySelector(".rate-input-days").value) || 2;

        if (rateId > 0 && batchPrice > 0) {
            updates.push({
                rate_id: rateId,
                min_quantity: minQty,
                batch_price: batchPrice,
                additional_per_thousand: addK,
                turnaround_days: days
            });
        }
    });

    const alertBox = document.getElementById("tableAlert");
    alertBox.style.display = "block";
    alertBox.className = "alert alert-info";
    alertBox.innerText = CURRENT_LANG === 'ur' ? "قیمتیں اپ ڈیٹ کی جا رہی ہیں..." : "Saving price updates...";

    const formData = new FormData();
    formData.append("action", "update_rates_batch");
    formData.append("vendor_id", VENDOR_ID);
    formData.append("token", TOKEN);
    formData.append("rates", JSON.stringify(updates));

    try {
        const res = await fetch("../api/print_vendor_api.php", { method: "POST", body: formData });
        const json = await res.json();
        if (json.success) {
            alertBox.className = "alert alert-success";
            alertBox.innerText = (CURRENT_LANG === 'ur' && json.message_ur) ? json.message_ur : json.message;
            document.getElementById("floatingSaveBar").style.display = "none";
            hasUnsavedChanges = false;
            loadVendorData();
            setTimeout(() => { alertBox.style.display = "none"; }, 4000);
        } else {
            alertBox.className = "alert alert-danger";
            alertBox.innerText = json.message;
        }
    } catch (err) {
        alertBox.className = "alert alert-danger";
        alertBox.innerText = "Error: " + err.message;
    }
}

// 1-Click Confirm (No price changes, just extend validity)
async function oneClickConfirm() {
    try {
        const formData = new FormData();
        formData.append("action", "confirm_rates");
        formData.append("vendor_id", VENDOR_ID);
        formData.append("token", TOKEN);

        const res = await fetch("../api/print_vendor_api.php", { method: "POST", body: formData });
        const json = await res.json();
        if (json.success) {
            alert((CURRENT_LANG === 'ur' && json.message_ur) ? json.message_ur : json.message);
            loadVendorData();
        } else {
            alert(json.message);
        }
    } catch (err) {
        alert("Error: " + err.message);
    }
}

// Modal Handlers: Add Rate
function populateModalProducts() {
    const sel = document.getElementById("modalSelProduct");
    sel.innerHTML = '<option value="">-- Choose Product --</option>';
    CATALOG_DATA.forEach(p => {
        sel.innerHTML += `<option value="${p.id}">${escapeHtml(p.name)}</option>`;
    });
}

function onModalProductChange() {
    const pId = parseInt(document.getElementById("modalSelProduct").value);
    const prod = CATALOG_DATA.find(x => x.id === pId);
    if (!prod) return;

    const selStock = document.getElementById("modalSelStock");
    selStock.innerHTML = '';
    prod.allowed_stocks.forEach(s => {
        selStock.innerHTML += `<option value="${escapeHtml(s)}">${escapeHtml(s)}</option>`;
    });

    const selFinish = document.getElementById("modalSelFinish");
    selFinish.innerHTML = '';
    prod.allowed_finishes.forEach(f => {
        selFinish.innerHTML += `<option value="${escapeHtml(f)}">${escapeHtml(f)}</option>`;
    });

    const selSize = document.getElementById("modalSelSize");
    selSize.innerHTML = '';
    prod.standard_sizes.forEach(sz => {
        selSize.innerHTML += `<option value="${escapeHtml(sz)}">${escapeHtml(sz)}</option>`;
    });
}

function openAddRateModal() {
    if (CATALOG_DATA.length > 0) {
        document.getElementById("modalSelProduct").value = CATALOG_DATA[0].id;
        onModalProductChange();
    }
    new bootstrap.Modal(document.getElementById("addRateModal")).show();
}

async function submitNewRate(e) {
    e.preventDefault();
    const btn = document.getElementById("btnModalAddSubmit");
    btn.disabled = true;

    const formData = new FormData();
    formData.append("action", "add_rate");
    formData.append("vendor_id", VENDOR_ID);
    formData.append("product_id", document.getElementById("modalSelProduct").value);
    formData.append("paper_stock", document.getElementById("modalSelStock").value);
    formData.append("finish_type", document.getElementById("modalSelFinish").value);
    formData.append("size", document.getElementById("modalSelSize").value);
    formData.append("min_quantity", document.getElementById("modalMinQty").value);
    formData.append("batch_price", document.getElementById("modalBatchPrice").value);
    formData.append("additional_per_thousand", document.getElementById("modalAddK").value);
    formData.append("turnaround_days", document.getElementById("modalDays").value);

    try {
        const res = await fetch("../api/print_vendor_api.php", { method: "POST", body: formData });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById("addRateModal")).hide();
            alert((CURRENT_LANG === 'ur' && json.message_ur) ? json.message_ur : json.message);
            loadVendorData();
        } else {
            alert(json.message);
        }
    } catch (err) {
        alert("Error: " + err.message);
    } finally {
        btn.disabled = false;
    }
}

async function deleteRate(rateId) {
    if (!confirm(CURRENT_LANG === 'ur' ? "کیا آپ واقعی یہ ریٹ لسٹ ڈیلیٹ کرنا چاہتے ہیں؟" : "Are you sure you want to remove this rate card?")) return;
    try {
        const formData = new FormData();
        formData.append("action", "delete_rate");
        formData.append("rate_id", rateId);
        formData.append("vendor_id", VENDOR_ID);

        const res = await fetch("../api/print_vendor_api.php", { method: "POST", body: formData });
        const json = await res.json();
        if (json.success) {
            loadVendorData();
        } else {
            alert(json.message);
        }
    } catch (err) {
        alert("Error: " + err.message);
    }
}

function openFrequencyModal() {
    new bootstrap.Modal(document.getElementById("freqModal")).show();
}

async function saveFrequency() {
    const val = document.getElementById("selFreqModal").value;
    try {
        const formData = new FormData();
        formData.append("action", "update_frequency");
        formData.append("vendor_id", VENDOR_ID);
        formData.append("frequency_days", val);

        const res = await fetch("../api/print_vendor_api.php", { method: "POST", body: formData });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById("freqModal")).hide();
            alert((CURRENT_LANG === 'ur' && json.message_ur) ? json.message_ur : json.message);
            window.location.reload();
        } else {
            alert(json.message);
        }
    } catch (err) {
        alert("Error: " + err.message);
    }
}

async function logoutVendor() {
    await fetch("../api/print_vendor_api.php?action=vendor_logout");
    window.location.href = "vendor_login.php";
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>
</body>
</html>