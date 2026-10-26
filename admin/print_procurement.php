<?php
/**
 * admin/print_procurement.php
 * Graphicafix Print Hub & Multi-Vendor Procurement Engine
 * Clean, Minimal & Super Easy to Use (Global Graphicafix Styling)
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

include 'dashboard_header.php';
?>

<div class="height-100">

    <!-- ── Page Header ── -->
    <div class="page-header" style="margin-bottom:20px;">
        <div>
            <h1 style="font-size:24px; font-weight:800; color:#111; margin:0 0 4px 0;">
                <i class="fas fa-print" style="color:var(--primary, #024442);"></i> Print Procurement & Price Engine
            </h1>
            <p style="font-size:13.5px; color:#6b7280; margin:0;">
                Real-time multi-vendor price discovery, lowest rate comparison, and 1-click quotations & vendor purchase orders.
            </p>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <a href="print_orders.php" class="btn btn-secondary">
                <i class="fas fa-boxes"></i> Production Orders (POs)
            </a>
            <a href="print_vendors.php" class="btn btn-secondary">
                <i class="fas fa-warehouse"></i> Print Vendors
            </a>
            <a href="vendor_login.php" target="_blank" class="btn btn-primary" style="font-weight:700;">
                <i class="fas fa-sign-in-alt"></i> Vendor Rate Portal
            </a>
        </div>
    </div>

    <!-- ── Quick Specification Configurator Ribbon ── -->
    <div class="card" style="border:1px solid #e5e7eb; border-radius:14px; padding:18px 22px; margin-bottom:18px; box-shadow:0 1px 3px rgba(0,0,0,0.05); background:#fff;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:8px;">
            <div style="font-size:13px; font-weight:800; color:var(--primary, #024442); text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:6px;">
                <i class="fas fa-sliders-h"></i> 1. Configure Print Specifications
            </div>
            <div style="font-size:12px; color:#6b7280;">
                <i class="fas fa-bolt text-warning"></i> Instant search updates automatically on any change
            </div>
        </div>

        <div class="row g-2 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label" style="font-size:11.5px; font-weight:700; color:#374151; margin-bottom:4px;">PRODUCT CATEGORY</label>
                <select id="selProduct" class="form-select form-select-sm" onchange="onProductChange()" style="font-weight:600; height:38px; border-radius:8px;">
                    <option value="">Loading products...</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label" style="font-size:11.5px; font-weight:700; color:#374151; margin-bottom:4px;">PAPER STOCK / GSM</label>
                <select id="selPaperStock" class="form-select form-select-sm" onchange="searchRates()" style="height:38px; border-radius:8px;">
                    <option value="all">All Paper Stocks</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label" style="font-size:11.5px; font-weight:700; color:#374151; margin-bottom:4px;">FINISHING / COATING</label>
                <select id="selFinish" class="form-select form-select-sm" onchange="searchRates()" style="height:38px; border-radius:8px;">
                    <option value="all">All Finishes</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label" style="font-size:11.5px; font-weight:700; color:#374151; margin-bottom:4px;">SIZE / CUTTING</label>
                <select id="selSize" class="form-select form-select-sm" onchange="searchRates()" style="height:38px; border-radius:8px;">
                    <option value="all">All Sizes</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-12">
                <label class="form-label" style="font-size:11.5px; font-weight:700; color:#374151; margin-bottom:4px;">QUANTITY (PCS)</label>
                <div style="display:flex; gap:6px;">
                    <input type="number" id="inputQuantity" class="form-control form-control-sm" value="1000" min="50" step="50" oninput="debounceSearch()" style="font-weight:700; height:38px; border-radius:8px; width:110px;">
                    <div style="display:flex; gap:4px; align-items:center; flex-wrap:wrap;">
                        <button type="button" class="btn-qty-pill" onclick="setQuantity(500)">500</button>
                        <button type="button" class="btn-qty-pill active" onclick="setQuantity(1000)">1k</button>
                        <button type="button" class="btn-qty-pill" onclick="setQuantity(2500)">2.5k</button>
                        <button type="button" class="btn-qty-pill" onclick="setQuantity(5000)">5k</button>
                        <button type="button" class="btn-qty-pill" onclick="setQuantity(10000)">10k</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Markup Margin & Profit Controller Ribbon ── -->
    <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; padding:12px 20px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <span style="font-size:13px; font-weight:700; color:#1f2937;">
                <i class="fas fa-percentage text-muted"></i> Graphicafix Markup Margin:
            </span>
            <div style="display:flex; gap:6px;">
                <button type="button" class="inv-filter-btn" onclick="setMargin(20)">+20%</button>
                <button type="button" class="inv-filter-btn active" onclick="setMargin(30)">+30% (Standard)</button>
                <button type="button" class="inv-filter-btn" onclick="setMargin(40)">+40%</button>
                <button type="button" class="inv-filter-btn" onclick="setMargin(50)">+50%</button>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            <span style="font-size:12px; color:#6b7280; font-weight:600;">Custom %:</span>
            <div class="input-group input-group-sm" style="width:105px;">
                <input type="number" id="customMarginInput" class="form-control" value="30" min="0" max="300" onchange="setMargin(this.value)" style="font-weight:700; border-radius:6px 0 0 6px;">
                <span class="input-group-text" style="border-radius:0 6px 6px 0;">%</span>
            </div>
            <span id="activeMarginBadge" class="filter-pill" style="background:#024442; color:#ffffff; font-size:12px; font-weight:700; padding:4px 10px;">
                +30% Active
            </span>
        </div>
    </div>

    <!-- ── Best Deal Highlight Winner Banner ── -->
    <div id="winnerBanner" style="display:none; margin-bottom:18px; background:linear-gradient(135deg, #f0fff4 0%, #dcfce7 100%); border:1.5px solid #86efac; border-radius:14px; padding:18px 22px; box-shadow:0 2px 8px rgba(22, 163, 74, 0.1);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:48px; height:48px; border-radius:12px; background:#16a34a; color:#ffffff; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; box-shadow:0 4px 10px rgba(22,163,74,0.3);">
                    <i class="fas fa-award"></i>
                </div>
                <div>
                    <div style="font-size:11.5px; font-weight:800; color:#15803d; text-transform:uppercase; letter-spacing:0.5px;">
                        LOWEST PRICE RECOMMENDATION
                    </div>
                    <div style="font-size:18px; font-weight:800; color:#0f172a;" id="winnerVendorTitle">
                        --
                    </div>
                    <div style="font-size:12.5px; color:#475569;" id="winnerVendorSub">
                        --
                    </div>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:18px; flex-wrap:wrap;">
                <div style="text-align:right;">
                    <div style="font-size:11.5px; color:#64748b; font-weight:600;">Vendor Cost:</div>
                    <div style="font-size:18px; font-weight:800; color:#0f172a;" id="winnerVendorCost">PKR 0</div>
                </div>
                <div style="text-align:right; border-left:1.5px solid #bbf7d0; padding-left:18px;">
                    <div style="font-size:11.5px; color:#15803d; font-weight:700;">Client Selling Price:</div>
                    <div style="font-size:22px; font-weight:800; color:#024442;" id="winnerBuyerPrice">PKR 0</div>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn btn-sm btn-primary" onclick="triggerWinnerQuote()" style="background:#024442; border-color:#024442; font-weight:700; padding:8px 16px; border-radius:20px;">
                        <i class="fas fa-file-invoice"></i> Click to Quote (Client)
                    </button>
                    <button type="button" class="btn btn-sm btn-success" onclick="triggerWinnerOrder()" style="background:#16a34a; border-color:#16a34a; font-weight:700; padding:8px 16px; border-radius:20px;">
                        <i class="fas fa-shopping-cart"></i> Click to Order (Vendor)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Live Multi-Vendor Comparison Matrix Table ── -->
    <div class="card" style="padding:0; overflow:hidden; border-radius:14px; border:1px solid #e5e7eb; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-bottom:24px; background:#fff;">
        <div id="resultsWrapper">
            <div style="text-align:center; padding:45px 20px; color:#6b7280;">
                <i class="fas fa-spinner fa-spin fa-2x" style="color:#024442; margin-bottom:10px;"></i>
                <p style="margin:0; font-size:13.5px;">Loading active print rates...</p>
            </div>
        </div>
    </div>

</div>

<!-- ─────────────────────────────────────────────────────────────────────────────
     Modal A: "Click to Quote" (Client Quotation Generator)
     ───────────────────────────────────────────────────────────────────────────── -->
<div class="modal fade" id="quoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px; border:none; box-shadow:0 15px 35px rgba(0,0,0,0.18);">
            <div class="modal-header" style="background:#024442; color:#ffffff; padding:14px 20px;">
                <h5 class="modal-title" style="font-size:15px; font-weight:700; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-file-invoice"></i> Click to Quote (Generate Buyer Quotation)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:20px;">
                <div style="background:#f0f9f8; border:1px solid #d1eae8; border-radius:8px; padding:10px 14px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; font-size:13px;">
                    <div>
                        <strong>Selected Vendor:</strong> <span id="qVendorName">--</span> (<span id="qTurnaround">--</span> Turnaround)
                    </div>
                    <div>
                        <strong>Base Vendor Cost:</strong> PKR <span id="qVendorCost" style="font-weight:700; color:#024442;">0</span>
                    </div>
                </div>

                <form id="quoteForm" onsubmit="submitBuyerQuote(event)">
                    <input type="hidden" id="qRateId" name="rate_id">
                    <input type="hidden" id="qVendorCostVal" name="vendor_cost">
                    <input type="hidden" id="qProductName" name="product_name">
                    <input type="hidden" id="qPaperStock" name="paper_stock">
                    <input type="hidden" id="qFinishType" name="finish_type">
                    <input type="hidden" id="qSize" name="size">
                    <input type="hidden" id="qQuantity" name="quantity">
                    <input type="hidden" id="qTurnaroundVal" name="turnaround">

                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:12px; font-weight:700; color:#555;">Select Existing Client</label>
                            <select id="qClientSelect" class="form-select form-select-sm" onchange="onQuoteClientSelect(this)">
                                <option value="">-- Choose Existing Client (Optional) --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:12px; font-weight:700; color:#555;">Client Name <span class="text-danger">*</span></label>
                            <input type="text" id="qClientName" class="form-control form-control-sm" required placeholder="e.g. Ali Ahmad">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:12px; font-weight:700; color:#555;">Company Name</label>
                            <input type="text" id="qClientCompany" class="form-control form-control-sm" placeholder="e.g. ABC Enterprises">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:12px; font-weight:700; color:#555;">Client Phone / WhatsApp</label>
                            <input type="text" id="qClientPhone" class="form-control form-control-sm" placeholder="e.g. 03001234567">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:12px; font-weight:700; color:#555;">Client Email</label>
                            <input type="email" id="qClientEmail" class="form-control form-control-sm" placeholder="e.g. client@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:12px; font-weight:700; color:#555;">Markup Percentage (%)</label>
                            <input type="number" id="qMarginPercent" class="form-control form-control-sm" value="30" oninput="recalcQuoteSellingPrice()">
                        </div>
                        <div class="col-12 mt-3">
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <span style="font-size:12px; color:#666;">Client Selling Price:</span>
                                    <div style="font-size:20px; font-weight:800; color:#024442;">PKR <span id="qSellingPriceDisplay">0</span></div>
                                </div>
                                <div style="text-align:right;">
                                    <span style="font-size:12px; color:#16a34a; font-weight:600;">Profit Margin:</span>
                                    <div style="font-size:16px; font-weight:800; color:#16a34a;">+PKR <span id="qProfitDisplay">0</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 mt-2">
                            <label class="form-label" style="font-size:12px; font-weight:700; color:#555;">Scope Notes (Appears on Quotation)</label>
                            <textarea id="qNotes" class="form-control form-control-sm" rows="2" placeholder="e.g. Client requested sample proof verification before full run."></textarea>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px; padding-top:12px; border-top:1px solid #eee;">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="btnSubmitQuote" class="btn btn-primary btn-sm" style="font-weight:700; background:#024442; border-color:#024442;">
                            <i class="fas fa-check-circle"></i> Create Quotation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ─────────────────────────────────────────────────────────────────────────────
     Modal B: "Click to Order" (Vendor Purchase Order Dispatch)
     ───────────────────────────────────────────────────────────────────────────── -->
<div class="modal fade" id="orderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px; border:none; box-shadow:0 15px 35px rgba(0,0,0,0.18);">
            <div class="modal-header" style="background:#0f172a; color:#ffffff; padding:14px 20px;">
                <h5 class="modal-title" style="font-size:15px; font-weight:700; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-truck-loading" style="color:var(--accent, #B6F763);"></i> Click to Order (Assign Vendor Purchase Order)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:20px;">
                <div style="background:#f0fff4; border:1px solid #bbf7d0; border-radius:8px; padding:10px 14px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; font-size:13px;">
                    <div>
                        <strong>Assigning To:</strong> <span id="oVendorName">--</span> (<span id="oVendorCity">--</span>)
                    </div>
                    <div>
                        <strong>Agreed Vendor PO Total:</strong> PKR <span id="oVendorCost" style="font-weight:700; color:#16a34a;">0</span>
                    </div>
                </div>

                <form id="orderForm" onsubmit="submitVendorOrder(event)">
                    <input type="hidden" id="oVendorId" name="vendor_id">
                    <input type="hidden" id="oRateId" name="rate_id">
                    <input type="hidden" id="oVendorCostVal" name="vendor_cost">
                    <input type="hidden" id="oProductName" name="product_name">
                    <input type="hidden" id="oPaperStock" name="paper_stock">
                    <input type="hidden" id="oFinishType" name="finish_type">
                    <input type="hidden" id="oSize" name="size">
                    <input type="hidden" id="oQuantity" name="quantity">
                    <input type="hidden" id="oTurnaroundDays" name="turnaround_days">

                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:12px; font-weight:700; color:#555;">Client Reference / Job Name <span class="text-danger">*</span></label>
                            <input type="text" id="oClientName" class="form-control form-control-sm" required placeholder="e.g. Matacon Builders Corp">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:12px; font-weight:700; color:#555;">Required Delivery Deadline <span class="text-danger">*</span></label>
                            <input type="date" id="oDeadline" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:12px; font-weight:700; color:#555;">Attach Artwork File (Optional)</label>
                            <input type="file" id="oArtworkFile" class="form-control form-control-sm" accept=".pdf,.ai,.psd,.cdr,.zip,.rar,.png,.jpg">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:12px; font-weight:700; color:#555;">Or Cloud Link (Google Drive / WeTransfer)</label>
                            <input type="url" id="oArtworkUrl" class="form-control form-control-sm" placeholder="https://drive.google.com/...">
                        </div>
                        <div class="col-12">
                            <label class="form-label" style="font-size:12px; font-weight:700; color:#555;">Delivery Destination</label>
                            <input type="text" id="oDeliveryAddress" class="form-control form-control-sm" value="Graphicafix Quality Control Office, Karachi">
                        </div>
                        <div class="col-12">
                            <label class="form-label" style="font-size:12px; font-weight:700; color:#555;">Production Notes & Instructions for Vendor</label>
                            <textarea id="oSpecialInstructions" class="form-control form-control-sm" rows="2" placeholder="e.g. Sharp registration on Spot UV, pack in bundles of 100."></textarea>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px; padding-top:12px; border-top:1px solid #eee;">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="btnSubmitOrder" class="btn btn-success btn-sm" style="background:#16a34a; border-color:#16a34a; font-weight:700; padding:6px 16px;">
                            <i class="fas fa-check-double"></i> Confirm & Generate PO
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Page Header */
.page-header { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:14px; margin-bottom:20px; }
.page-header h1 { margin:0 0 4px; }
.page-header p  { margin:0; color:#888; font-size:14px; }

/* Filter & Margins */
.inv-filter-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:100px; border:1.5px solid #e0e0e0; background:transparent; font-size:12.5px; font-weight:600; color:#555; text-decoration:none; transition:all .2s; white-space:nowrap; cursor:pointer; }
.inv-filter-btn:hover, .inv-filter-btn.active { background:#024442; border-color:#024442; color:#fff; }
.filter-pill { background:rgba(0,0,0,.08); padding:2px 8px; border-radius:100px; font-size:11.5px; font-weight:700; }
.inv-filter-btn.active .filter-pill { background:rgba(255,255,255,.25); }

/* Quantity Preset Pills */
.btn-qty-pill { background:#f1f5f9; border:1px solid #cbd5e1; border-radius:12px; padding:2px 8px; font-size:11.5px; font-weight:700; color:#475569; cursor:pointer; transition:all 0.15s ease; }
.btn-qty-pill:hover, .btn-qty-pill.active { background:#024442; color:#fff; border-color:#024442; }

/* Data Table */
.data-table { width:100%; border-collapse:collapse; font-size:13.5px; }
.data-table th { background:#f9fafb; padding:12px 16px; text-align:left; font-size:11.5px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.06em; border-bottom:1px solid #ebebeb; white-space:nowrap; }
.data-table td { padding:14px 16px; border-bottom:1px solid #f5f5f5; color:#333; vertical-align:middle; }
.data-table tr:last-child td { border-bottom:none; }
.data-table tr:hover td { background:#fafafa; }

/* Status Badges */
.status-completed   { background:#d1fae5; color:#065f46; font-weight:700; font-size:11px; padding:4px 8px; border-radius:12px; }
.status-in-progress { background:#dbeafe; color:#1e40af; font-weight:700; font-size:11px; padding:4px 8px; border-radius:12px; }
.status-on-hold     { background:#fef3c7; color:#92400e; font-weight:700; font-size:11px; padding:4px 8px; border-radius:12px; }
.status-not-started { background:#fee2e2; color:#991b1b; font-weight:700; font-size:11px; padding:4px 8px; border-radius:12px; }
</style>

<script>
let TAXONOMY_DATA = [];
let CLIENTS_DATA = [];
let CURRENT_MARGIN = 30;
let CURRENT_RESULTS = [];
let SELECTED_RATE = null;
let searchTimer = null;

document.addEventListener("DOMContentLoaded", () => {
    loadTaxonomy();
    const d = new Date();
    d.setDate(d.getDate() + 3);
    document.getElementById("oDeadline").value = d.toISOString().split("T")[0];
});

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        searchRates();
    }, 350);
}

function setQuantity(qty) {
    document.getElementById("inputQuantity").value = qty;
    document.querySelectorAll(".btn-qty-pill").forEach(p => {
        if (parseInt(p.innerText.replace('k', '000')) === qty || p.innerText === String(qty)) {
            p.classList.add("active");
        } else {
            p.classList.remove("active");
        }
    });
    searchRates();
}

async function loadTaxonomy() {
    try {
        const res = await fetch("../api/print_procurement_api.php?action=get_taxonomy");
        const json = await res.json();
        if (json.success) {
            TAXONOMY_DATA = json.products;
            CLIENTS_DATA = json.clients;
            populateProductDropdown();
            populateClientDropdown();
        }
    } catch (err) {
        console.error("Failed to load taxonomy", err);
    }
}

function populateProductDropdown() {
    const sel = document.getElementById("selProduct");
    sel.innerHTML = '<option value="">-- Select Product --</option>';
    TAXONOMY_DATA.forEach(p => {
        sel.innerHTML += `<option value="${p.id}">${p.name} (${p.category})</option>`;
    });
    if (TAXONOMY_DATA.length > 0) {
        sel.value = TAXONOMY_DATA[0].id;
        onProductChange();
        searchRates();
    }
}

function populateClientDropdown() {
    const sel = document.getElementById("qClientSelect");
    sel.innerHTML = '<option value="">-- Choose Existing Client (Optional) --</option>';
    CLIENTS_DATA.forEach(c => {
        sel.innerHTML += `<option value="${c.id}" data-name="${escapeHtml(c.client_name)}" data-company="${escapeHtml(c.company || '')}" data-phone="${escapeHtml(c.phone || '')}" data-email="${escapeHtml(c.email || '')}">${c.client_name} ${c.company ? '(' + c.company + ')' : ''}</option>`;
    });
}

function onProductChange() {
    const pId = parseInt(document.getElementById("selProduct").value);
    const prod = TAXONOMY_DATA.find(x => x.id === pId);
    if (!prod) return;

    const selStock = document.getElementById("selPaperStock");
    selStock.innerHTML = '<option value="all">All Paper Stocks</option>';
    prod.allowed_stocks.forEach(s => {
        selStock.innerHTML += `<option value="${escapeHtml(s)}">${escapeHtml(s)}</option>`;
    });

    const selFinish = document.getElementById("selFinish");
    selFinish.innerHTML = '<option value="all">All Finishes</option>';
    prod.allowed_finishes.forEach(f => {
        selFinish.innerHTML += `<option value="${escapeHtml(f)}">${escapeHtml(f)}</option>`;
    });

    const selSize = document.getElementById("selSize");
    selSize.innerHTML = '<option value="all">All Sizes</option>';
    prod.standard_sizes.forEach(sz => {
        selSize.innerHTML += `<option value="${escapeHtml(sz)}">${escapeHtml(sz)}</option>`;
    });

    searchRates();
}

function setMargin(val) {
    CURRENT_MARGIN = parseFloat(val) || 30;
    document.getElementById("activeMarginBadge").innerText = `+${CURRENT_MARGIN}% Active`;
    document.getElementById("customMarginInput").value = CURRENT_MARGIN;

    document.querySelectorAll(".inv-filter-btn").forEach(btn => {
        if (btn.innerText.includes(CURRENT_MARGIN + "%")) {
            btn.classList.add("active");
        } else {
            btn.classList.remove("active");
        }
    });

    renderResults();
}

async function searchRates() {
    const pId = document.getElementById("selProduct").value;
    if (!pId) return;

    const stock = document.getElementById("selPaperStock").value;
    const finish = document.getElementById("selFinish").value;
    const size = document.getElementById("selSize").value;
    const qty = document.getElementById("inputQuantity").value || 1000;

    const cont = document.getElementById("resultsWrapper");
    cont.innerHTML = `<div style="text-align:center; padding:45px 20px; color:#6b7280;"><i class="fas fa-spinner fa-spin fa-2x" style="color:#024442;"></i><p style="margin-top:10px; font-size:13.5px;">Comparing live rates across print vendors...</p></div>`;

    try {
        const url = `../api/print_procurement_api.php?action=search_rates&product_id=${pId}&paper_stock=${encodeURIComponent(stock)}&finish_type=${encodeURIComponent(finish)}&size=${encodeURIComponent(size)}&quantity=${qty}&include_expired=1`;
        const res = await fetch(url);
        const json = await res.json();

        if (json.success) {
            CURRENT_RESULTS = json.results;
            renderResults();
        } else {
            cont.innerHTML = `<div style="padding:20px; color:#ef4444; text-align:center;">${json.message}</div>`;
        }
    } catch (err) {
        cont.innerHTML = `<div style="padding:20px; color:#ef4444; text-align:center;">Error: ${err.message}</div>`;
    }
}

function renderResults() {
    const cont = document.getElementById("resultsWrapper");
    const winnerBanner = document.getElementById("winnerBanner");

    if (!CURRENT_RESULTS || CURRENT_RESULTS.length === 0) {
        winnerBanner.style.display = "none";
        cont.innerHTML = `
            <div class="empty-state" style="padding:50px 20px; text-align:center;">
                <div class="empty-icon" style="font-size:36px; color:#ccc; margin-bottom:12px;"><i class="fas fa-search"></i></div>
                <p style="font-size:15px; color:#444; font-weight:600; margin:0 0 6px 0;">No print vendor rates found matching these criteria.</p>
                <small style="color:#888;">Try selecting 'All Stocks' or adding rate cards in <a href="print_vendors.php" style="color:#024442; font-weight:700;">Print Vendors</a>.</small>
            </div>
        `;
        return;
    }

    // Identify lowest price active winner
    const winner = CURRENT_RESULTS.find(x => x.is_lowest_price && x.dynamic_status !== 'expired');
    if (winner) {
        const wVendorCost = winner.total_vendor_cost;
        const wBuyerPrice = Math.round(wVendorCost * (1 + (CURRENT_MARGIN / 100)));
        document.getElementById("winnerVendorTitle").innerText = winner.vendor_name + " (" + winner.vendor_city + ")";
        document.getElementById("winnerVendorSub").innerText = `${winner.paper_stock} • ${winner.finish_type} • ${winner.turnaround_days} Days Turnaround • ★ ${winner.vendor_rating.toFixed(1)}`;
        document.getElementById("winnerVendorCost").innerText = "PKR " + formatMoney(wVendorCost);
        document.getElementById("winnerBuyerPrice").innerText = "PKR " + formatMoney(wBuyerPrice);
        winnerBanner.style.display = "block";
    } else {
        winnerBanner.style.display = "none";
    }

    let html = `
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Vendor / Venture</th>
                    <th>Specifications</th>
                    <th>Turnaround</th>
                    <th>Rate Status</th>
                    <th>Vendor Base Cost</th>
                    <th>Client Selling (+${CURRENT_MARGIN}%)</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
    `;

    CURRENT_RESULTS.forEach((r, idx) => {
        const isExpired = (r.dynamic_status === 'expired');
        const vendorCost = r.total_vendor_cost;
        const buyerPrice = Math.round(vendorCost * (1 + (CURRENT_MARGIN / 100)));
        const profit = buyerPrice - vendorCost;

        let statusBadgeClass = 'status-completed';
        if (isExpired) {
            statusBadgeClass = 'status-not-started';
        } else if (r.dynamic_status === 'expiring_soon') {
            statusBadgeClass = 'status-on-hold';
        }

        html += `
            <tr style="${r.is_lowest_price && !isExpired ? 'background:#f0fff4;' : ''}">
                <td>
                    <div style="font-weight:700; color:#111; font-size:13.5px; display:flex; align-items:center; gap:6px;">
                        ${escapeHtml(r.vendor_name)}
                        ${r.is_lowest_price && !isExpired ? '<span class="badge status-completed" style="font-size:10px;"><i class="fas fa-trophy"></i> Lowest</span>' : ''}
                        ${r.is_fastest && !isExpired ? '<span class="badge status-in-progress" style="font-size:10px;"><i class="fas fa-bolt"></i> Fastest</span>' : ''}
                    </div>
                    <div style="font-size:11.5px; color:#888;">
                        <i class="fas fa-map-marker-alt"></i> ${escapeHtml(r.vendor_city)} &bull; 
                        <span style="color:#d97706;">★ ${r.vendor_rating.toFixed(1)}</span>
                    </div>
                </td>
                <td>
                    <div style="font-weight:600; color:#024442; font-size:13px;">${escapeHtml(r.paper_stock)}</div>
                    <div style="font-size:11.5px; color:#666;">${escapeHtml(r.finish_type)} &bull; ${escapeHtml(r.size)}</div>
                </td>
                <td>
                    <strong style="font-size:13px;">${r.turnaround_days} Days</strong>
                </td>
                <td>
                    <span class="badge ${statusBadgeClass}">
                        <i class="fas ${isExpired ? 'fa-times' : 'fa-check'}"></i> ${r.status_label}
                    </span>
                </td>
                <td>
                    <div style="font-size:14px; font-weight:700; color:#111;">PKR ${formatMoney(vendorCost)}</div>
                    <div style="font-size:11px; color:#888;">(PKR ${r.effective_unit_cost.toFixed(2)}/pc)</div>
                </td>
                <td>
                    ${!isExpired ? `
                        <div style="font-size:15px; font-weight:800; color:#024442;">PKR ${formatMoney(buyerPrice)}</div>
                        <div style="font-size:11px; color:#16a34a; font-weight:700;">+PKR ${formatMoney(profit)} margin</div>
                    ` : `
                        <span style="font-size:12px; color:#ef4444; font-weight:600;">Rate Stale</span>
                    `}
                </td>
                <td style="text-align:right;">
                    <div style="display:inline-flex; gap:6px;">
                        ${!isExpired ? `
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="openQuoteModal(${idx})" style="padding:4px 10px; font-size:12px; font-weight:700; border-color:#024442; color:#024442;" title="Generate Client Quotation">
                                <i class="fas fa-file-invoice"></i> Quote
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" onclick="openOrderModal(${idx})" style="padding:4px 10px; font-size:12px; font-weight:700; background:#024442; border-color:#024442;" title="Assign Order to Vendor">
                                <i class="fas fa-shopping-cart"></i> Order
                            </button>
                        ` : `
                            <button type="button" class="btn btn-sm" onclick="nudgeVendor(${r.vendor_id})" style="background:#25d366; color:#fff; padding:4px 10px; font-size:12px; font-weight:700;" title="Nudge via WhatsApp">
                                <i class="fab fa-whatsapp"></i> Nudge
                            </button>
                        `}
                    </div>
                </td>
            </tr>
        `;
    });

    html += `
            </tbody>
        </table>
        </div>
    `;
    cont.innerHTML = html;
}

function triggerWinnerQuote() {
    const idx = CURRENT_RESULTS.findIndex(x => x.is_lowest_price && x.dynamic_status !== 'expired');
    if (idx !== -1) openQuoteModal(idx);
}

function triggerWinnerOrder() {
    const idx = CURRENT_RESULTS.findIndex(x => x.is_lowest_price && x.dynamic_status !== 'expired');
    if (idx !== -1) openOrderModal(idx);
}

function openQuoteModal(index) {
    SELECTED_RATE = CURRENT_RESULTS[index];
    if (!SELECTED_RATE) return;

    const vendorCost = SELECTED_RATE.total_vendor_cost;
    const prodName = document.getElementById("selProduct").options[document.getElementById("selProduct").selectedIndex].text;

    document.getElementById("qVendorName").innerText = SELECTED_RATE.vendor_name;
    document.getElementById("qTurnaround").innerText = SELECTED_RATE.turnaround_days + " Business Days";
    document.getElementById("qVendorCost").innerText = formatMoney(vendorCost);

    document.getElementById("qRateId").value = SELECTED_RATE.rate_id;
    document.getElementById("qVendorCostVal").value = vendorCost;
    document.getElementById("qProductName").value = prodName;
    document.getElementById("qPaperStock").value = SELECTED_RATE.paper_stock;
    document.getElementById("qFinishType").value = SELECTED_RATE.finish_type;
    document.getElementById("qSize").value = SELECTED_RATE.size;
    document.getElementById("qQuantity").value = SELECTED_RATE.requested_quantity;
    document.getElementById("qTurnaroundVal").value = SELECTED_RATE.turnaround_days + " Business Days";
    document.getElementById("qMarginPercent").value = CURRENT_MARGIN;

    recalcQuoteSellingPrice();
    new bootstrap.Modal(document.getElementById("quoteModal")).show();
}

function onQuoteClientSelect(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;

    document.getElementById("qClientName").value = opt.getAttribute("data-name") || "";
    document.getElementById("qClientCompany").value = opt.getAttribute("data-company") || "";
    document.getElementById("qClientPhone").value = opt.getAttribute("data-phone") || "";
    document.getElementById("qClientEmail").value = opt.getAttribute("data-email") || "";
}

function recalcQuoteSellingPrice() {
    const vCost = parseFloat(document.getElementById("qVendorCostVal").value) || 0;
    const margin = parseFloat(document.getElementById("qMarginPercent").value) || 0;
    const sellingPrice = Math.round(vCost * (1 + (margin / 100)));
    const profit = sellingPrice - vCost;

    document.getElementById("qSellingPriceDisplay").innerText = formatMoney(sellingPrice);
    document.getElementById("qProfitDisplay").innerText = formatMoney(profit);
}

async function submitBuyerQuote(e) {
    e.preventDefault();
    const btn = document.getElementById("btnSubmitQuote");
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Generating...`;

    const vCost = parseFloat(document.getElementById("qVendorCostVal").value) || 0;
    const margin = parseFloat(document.getElementById("qMarginPercent").value) || 0;
    const sellingPrice = Math.round(vCost * (1 + (margin / 100)));

    const formData = new FormData();
    formData.append("action", "create_buyer_quote");
    formData.append("client_id", document.getElementById("qClientSelect").value);
    formData.append("client_name", document.getElementById("qClientName").value);
    formData.append("client_company", document.getElementById("qClientCompany").value);
    formData.append("client_phone", document.getElementById("qClientPhone").value);
    formData.append("client_email", document.getElementById("qClientEmail").value);
    formData.append("product_name", document.getElementById("qProductName").value);
    formData.append("paper_stock", document.getElementById("qPaperStock").value);
    formData.append("finish_type", document.getElementById("qFinishType").value);
    formData.append("size", document.getElementById("qSize").value);
    formData.append("quantity", document.getElementById("qQuantity").value);
    formData.append("vendor_cost", vCost);
    formData.append("margin_percent", margin);
    formData.append("selling_price", sellingPrice);
    formData.append("turnaround", document.getElementById("qTurnaroundVal").value);
    formData.append("notes", document.getElementById("qNotes").value);

    try {
        const res = await fetch("../api/print_procurement_api.php", { method: "POST", body: formData });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById("quoteModal")).hide();
            if (confirm(`Quotation ${json.quotation_number} created!\n\nClick OK to view quotation details, or Cancel to download PDF directly.`)) {
                window.location.href = json.view_url;
            } else {
                window.open(json.pdf_url, '_blank');
            }
        } else {
            alert(json.message);
        }
    } catch (err) {
        alert("Error: " + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="fas fa-check-circle"></i> Create Quotation`;
    }
}

function openOrderModal(index) {
    SELECTED_RATE = CURRENT_RESULTS[index];
    if (!SELECTED_RATE) return;

    const vendorCost = SELECTED_RATE.total_vendor_cost;
    const prodName = document.getElementById("selProduct").options[document.getElementById("selProduct").selectedIndex].text;

    document.getElementById("oVendorName").innerText = SELECTED_RATE.vendor_name;
    document.getElementById("oVendorCity").innerText = SELECTED_RATE.vendor_city;
    document.getElementById("oVendorCost").innerText = formatMoney(vendorCost);

    document.getElementById("oVendorId").value = SELECTED_RATE.vendor_id;
    document.getElementById("oRateId").value = SELECTED_RATE.rate_id;
    document.getElementById("oVendorCostVal").value = vendorCost;
    document.getElementById("oProductName").value = prodName;
    document.getElementById("oPaperStock").value = SELECTED_RATE.paper_stock;
    document.getElementById("oFinishType").value = SELECTED_RATE.finish_type;
    document.getElementById("oSize").value = SELECTED_RATE.size;
    document.getElementById("oQuantity").value = SELECTED_RATE.requested_quantity;
    document.getElementById("oTurnaroundDays").value = SELECTED_RATE.turnaround_days;

    new bootstrap.Modal(document.getElementById("orderModal")).show();
}

async function submitVendorOrder(e) {
    e.preventDefault();
    const btn = document.getElementById("btnSubmitOrder");
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Placing PO...`;

    const vCost = parseFloat(document.getElementById("oVendorCostVal").value) || 0;
    const clientPrice = Math.round(vCost * (1 + (CURRENT_MARGIN / 100)));

    const formData = new FormData();
    formData.append("action", "create_vendor_order");
    formData.append("vendor_id", document.getElementById("oVendorId").value);
    formData.append("rate_id", document.getElementById("oRateId").value);
    formData.append("client_name", document.getElementById("oClientName").value);
    formData.append("product_name", document.getElementById("oProductName").value);
    formData.append("paper_stock", document.getElementById("oPaperStock").value);
    formData.append("finish_type", document.getElementById("oFinishType").value);
    formData.append("size", document.getElementById("oSize").value);
    formData.append("quantity", document.getElementById("oQuantity").value);
    formData.append("vendor_cost", vCost);
    formData.append("client_price", clientPrice);
    formData.append("deadline", document.getElementById("oDeadline").value);
    formData.append("delivery_address", document.getElementById("oDeliveryAddress").value);
    formData.append("special_instructions", document.getElementById("oSpecialInstructions").value);
    formData.append("artwork_url", document.getElementById("oArtworkUrl").value);

    const artFile = document.getElementById("oArtworkFile").files[0];
    if (artFile) formData.append("artwork_file", artFile);

    try {
        const res = await fetch("../api/print_procurement_api.php", { method: "POST", body: formData });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById("orderModal")).hide();
            if (confirm(`Purchase Order ${json.po_number} assigned to ${json.vendor_name}!\n\nOpen WhatsApp now to dispatch PO details to vendor?`)) {
                window.open(json.whatsapp_url, '_blank');
            }
            window.location.href = json.orders_url;
        } else {
            alert(json.message);
        }
    } catch (err) {
        alert("Order error: " + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i class="fas fa-check-double"></i> Confirm & Generate PO`;
    }
}

async function nudgeVendor(vendorId) {
    const prodName = document.getElementById("selProduct").options[document.getElementById("selProduct").selectedIndex].text;
    try {
        const res = await fetch(`../api/print_procurement_api.php?action=nudge_vendor&vendor_id=${vendorId}&product_name=${encodeURIComponent(prodName)}`);
        const json = await res.json();
        if (json.success && json.whatsapp_url) {
            window.open(json.whatsapp_url, '_blank');
        } else {
            alert(json.message || "Failed to generate WhatsApp nudge link");
        }
    } catch (err) {
        alert("Error: " + err.message);
    }
}

function formatMoney(num) {
    return Number(num).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php include 'dashboard_footer.php'; ?>