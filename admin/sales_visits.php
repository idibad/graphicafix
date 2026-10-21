<?php
include 'dashboard_header.php';

// Auth: Sales reps, Admins, Super Admins, PMs, Managers can access
$allowed_roles = ['sales', 'super_admin', 'superadmin', 'admin', 'manager', 'pm'];
if (!in_array($role, $allowed_roles)) {
    header("Location: index.php?error=unauthorized");
    exit;
}

$user_id_int = intval($_SESSION['user_id'] ?? 0);
$is_admin_user = in_array($role, ['super_admin', 'superadmin', 'admin']);
?>

<div class="height-100">

    <!-- ── Universal Stats Row ── -->
    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total Field Visits</span>
                <div class="stat-icon" style="background:var(--light);color:var(--primary);"><i class="fas fa-street-view"></i></div>
            </div>
            <div class="stat-number" id="statTotal">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Urgent Follow-ups</span>
                <div class="stat-icon" style="background:#fee2e2;color:#ef4444;"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
            <div class="stat-number" id="statUrgent" style="color:#ef4444;">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Hot Leads</span>
                <div class="stat-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-fire"></i></div>
            </div>
            <div class="stat-number" id="statHot" style="color:#d97706;">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Contacted / Closed</span>
                <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="stat-number" id="statContacted" style="color:#16a34a;">--</div>
        </div>
    </div>

    <!-- ── Universal Main Card & Table ── -->
    <div class="main-card">
        <div class="main-header">
            <h3><i class="fas fa-street-view"></i> Client Field Visits Log</h3>
            <div style="display:flex; gap:10px; align-items:center;">
                <?php if ($is_admin_user): ?>
                <a href="sales_response_unit.php" class="btn btn-secondary-custom" style="padding:9px 16px; font-size:13.5px; text-decoration:none;">
                    <i class="fas fa-satellite-dish"></i> Response Unit
                </a>
                <?php endif; ?>
                <button class="add-client" onclick="openVisitModal('add')">
                    <i class="fas fa-plus"></i> Log Client Visit
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div style="padding:14px 24px; border-bottom:1px solid #f1f5f9; background:#fafafa; display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between;">
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <span style="font-size:13px; font-weight:600; color:#64748b;">Filter:</span>
                <select id="filterSelect" onchange="setFilter(this.value)" class="form-select" style="width:auto; padding:6px 12px; font-size:13px;">
                    <option value="all">All Visits</option>
                    <option value="urgent">🚨 Urgent Need to Contact</option>
                    <option value="hot">🔥 Hot Interest</option>
                    <option value="warm">⚡ Warm Interest</option>
                    <option value="cold">❄️ Cold Interest</option>
                    <option value="pending">⏳ Pending Contact</option>
                    <option value="contacted">✅ Contacted</option>
                </select>
            </div>

            <div style="position:relative; width:100%; max-width:320px;">
                <input type="text" id="searchInput" oninput="handleSearch(this.value)" placeholder="Search client, company, phone..."
                       class="form-control" style="padding-left:34px; font-size:13px; height:36px;">
                <i class="fas fa-search" style="position:absolute; left:12px; top:11px; color:#94a3b8; font-size:13px;"></i>
            </div>
        </div>

        <!-- Table Container -->
        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%; border-collapse:collapse; min-width:880px;">
                <thead>
                    <tr>
                        <th style="width:23%;">Client & Company</th>
                        <th style="width:18%;">1-Click WhatsApp</th>
                        <th style="width:11%;">Interest</th>
                        <th style="width:16%;">Contact Window</th>
                        <th style="width:15%;">Requirements</th>
                        <th style="width:10%;">Logged By</th>
                        <th style="width:7%; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="visitsTableBody">
                    <tr>
                        <td colspan="7" style="text-align:center; padding:50px 20px; color:#888;">
                            <i class="fas fa-spinner fa-spin" style="font-size:24px; margin-bottom:10px; color:var(--primary);"></i>
                            <p style="margin:0;">Loading visits...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     UNIVERSAL MODAL: LOG / EDIT CLIENT VISIT
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="visitModal" class="modal-overlay" onclick="if(event.target===this)closeVisitModal()">
    <div class="modal-content" style="max-width:740px;">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon"><i class="fas fa-street-view"></i></div>
                <span id="visitModalTitle">Log Client Visit</span>
            </h4>
            <button class="modal-close" onclick="closeVisitModal()">×</button>
        </div>

        <form id="visitForm" onsubmit="submitVisitForm(event)">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="visit_id" id="f_visit_id" value="">

            <div class="modal-body">

                <!-- Row 1: Client & Company -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                    <div class="form-group">
                        <label>Client Name <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="client_name" id="f_client_name" required placeholder="e.g. John Doe / Muhammad Ali" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Company / Business <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="company_name" id="f_company_name" required placeholder="e.g. Apex Traders" class="form-control">
                    </div>
                </div>

                <!-- Row 2: Phone & WhatsApp -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                    <div class="form-group">
                        <label>Phone Number <span style="color:#ef4444;">*</span></label>
                        <input type="tel" name="phone" id="f_phone" required placeholder="e.g. 03001234567" class="form-control">
                    </div>
                    <div class="form-group">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                            <label style="margin:0;">WhatsApp Number</label>
                            <button type="button" onclick="copyPhoneToWa()" style="background:none; border:none; color:#16a34a; font-size:11.5px; font-weight:700; cursor:pointer; padding:0;">
                                <i class="fab fa-whatsapp"></i> Same as Phone
                            </button>
                        </div>
                        <input type="tel" name="whatsapp" id="f_whatsapp" placeholder="e.g. 03001234567" class="form-control">
                    </div>
                </div>

                <!-- Row 3: Interest Level & Urgency -->
                <div style="display:grid; grid-template-columns:1.5fr 1fr; gap:14px; margin-bottom:14px;">
                    <div class="form-group">
                        <label>Interest Level <span style="color:#ef4444;">*</span></label>
                        <div style="display:flex; gap:8px;">
                            <label class="interest-chip" id="chip_hot">
                                <input type="radio" name="interest_level" value="hot" required onchange="setInterestUI('hot')">
                                <span>🔥 Hot</span>
                            </label>
                            <label class="interest-chip active-chip" id="chip_warm">
                                <input type="radio" name="interest_level" value="warm" checked required onchange="setInterestUI('warm')">
                                <span>⚡ Warm</span>
                            </label>
                            <label class="interest-chip" id="chip_cold">
                                <input type="radio" name="interest_level" value="cold" required onchange="setInterestUI('cold')">
                                <span>❄️ Cold</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Urgent Follow-up</label>
                        <label style="display:flex; align-items:center; gap:8px; padding:9px 12px; background:#fff5f5; border:1px solid #fecaca; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; color:#b91c1c;">
                            <input type="checkbox" name="is_urgent" id="f_is_urgent" value="1" style="width:17px; height:17px; accent-color:#ef4444;">
                            <span>🚨 Urgently Need to Contact</span>
                        </label>
                    </div>
                </div>

                <!-- Row 4: Client Availability Timing -->
                <div class="form-group" style="margin-bottom:14px;">
                    <label>Client Availability (When to Contact?) <span style="color:#ef4444;">*</span></label>
                    <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px;">
                        <button type="button" class="preset-tag" onclick="setAvailPreset('Available in 1-2 hours')">In 1-2 hours</button>
                        <button type="button" class="preset-tag" onclick="setAvailPreset('Evening today (5:00 PM - 8:00 PM)')">Today Evening</button>
                        <button type="button" class="preset-tag" onclick="setAvailPreset('Tomorrow morning (10:00 AM - 12:00 PM)')">Tomorrow Morning</button>
                        <button type="button" class="preset-tag" onclick="setAvailPreset('Tomorrow afternoon (2:00 PM - 5:00 PM)')">Tomorrow Afternoon</button>
                        <button type="button" class="preset-tag" onclick="setAvailPreset('After 2 days')">After 2 days</button>
                        <button type="button" class="preset-tag" onclick="setAvailPreset('Next week Monday')">Next week</button>
                    </div>
                    <input type="text" name="contact_availability" id="f_contact_availability" required placeholder="e.g. Tomorrow afternoon at 3:00 PM" class="form-control">
                </div>

                <!-- Row 5: Requirements Categories & Subcategories -->
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="display:flex; justify-content:space-between; align-items:center;">
                        <span>Requirements / Services Discussed</span>
                        <small style="color:#64748b; font-weight:normal;">(Select categories and optional sub-items)</small>
                    </label>

                    <div style="display:flex; flex-direction:column; gap:8px; margin-top:6px;">
                        <!-- Category: Website -->
                        <div class="cat-box">
                            <label class="cat-label">
                                <input type="checkbox" name="categories[]" value="Website" onchange="toggleSub('sub_web', this.checked)">
                                <strong><i class="fas fa-globe" style="color:#0284c7;"></i> Website</strong>
                            </label>
                            <div id="sub_web" class="sub-box" style="display:none;">
                                <label><input type="checkbox" name="subcategories[]" value="Website: E-commerce Store"> E-commerce Store</label>
                                <label><input type="checkbox" name="subcategories[]" value="Website: Corporate / Portfolio"> Corporate / Portfolio</label>
                                <label><input type="checkbox" name="subcategories[]" value="Website: Custom Web App"> Custom Web App</label>
                                <label><input type="checkbox" name="subcategories[]" value="Website: Redesign & Revamp"> Redesign & Revamp</label>
                                <label><input type="checkbox" name="subcategories[]" value="Website: Maintenance & Speed"> Maintenance</label>
                            </div>
                        </div>

                        <!-- Category: Printing -->
                        <div class="cat-box">
                            <label class="cat-label">
                                <input type="checkbox" name="categories[]" value="Printing" onchange="toggleSub('sub_print', this.checked)">
                                <strong><i class="fas fa-print" style="color:#e17055;"></i> Printing</strong>
                            </label>
                            <div id="sub_print" class="sub-box" style="display:none;">
                                <label><input type="checkbox" name="subcategories[]" value="Printing: Business Cards"> Business Cards</label>
                                <label><input type="checkbox" name="subcategories[]" value="Printing: Letterheads & Stationery"> Letterheads & Stationery</label>
                                <label><input type="checkbox" name="subcategories[]" value="Printing: Flyers & Brochures"> Flyers & Brochures</label>
                                <label><input type="checkbox" name="subcategories[]" value="Printing: Packaging Boxes"> Packaging & Boxes</label>
                                <label><input type="checkbox" name="subcategories[]" value="Printing: Banners & Signboards"> Banners & Signboards</label>
                            </div>
                        </div>

                        <!-- Category: Design -->
                        <div class="cat-box">
                            <label class="cat-label">
                                <input type="checkbox" name="categories[]" value="Design" onchange="toggleSub('sub_design', this.checked)">
                                <strong><i class="fas fa-paint-brush" style="color:#db2777;"></i> Design</strong>
                            </label>
                            <div id="sub_design" class="sub-box" style="display:none;">
                                <label><input type="checkbox" name="subcategories[]" value="Design: Logo & Brand Identity"> Logo & Brand Identity</label>
                                <label><input type="checkbox" name="subcategories[]" value="Design: UI/UX App Design"> UI/UX Design</label>
                                <label><input type="checkbox" name="subcategories[]" value="Design: Social Media Graphics"> Social Media Graphics</label>
                                <label><input type="checkbox" name="subcategories[]" value="Design: Catalogs & Company Profile"> Catalogs / Profiles</label>
                                <label><input type="checkbox" name="subcategories[]" value="Design: Packaging Artworks"> Packaging Design</label>
                            </div>
                        </div>

                        <!-- Category: Social Media Marketing -->
                        <div class="cat-box">
                            <label class="cat-label">
                                <input type="checkbox" name="categories[]" value="Social Media Marketing" onchange="toggleSub('sub_smm', this.checked)">
                                <strong><i class="fas fa-chart-line" style="color:#8b5cf6;"></i> Social Media Marketing (Paid Ads)</strong>
                            </label>
                            <div id="sub_smm" class="sub-box" style="display:none;">
                                <label><input type="checkbox" name="subcategories[]" value="SMM: Meta Ads (FB & IG)"> Meta Ads (FB/IG)</label>
                                <label><input type="checkbox" name="subcategories[]" value="SMM: Google Ads PPC"> Google Ads (PPC)</label>
                                <label><input type="checkbox" name="subcategories[]" value="SMM: TikTok Ads"> TikTok Ads</label>
                                <label><input type="checkbox" name="subcategories[]" value="SMM: Lead Generation Campaigns"> Lead Generation</label>
                                <label><input type="checkbox" name="subcategories[]" value="SMM: Influencer Marketing"> Influencer Outreach</label>
                            </div>
                        </div>

                        <!-- Category: Managing Social Media -->
                        <div class="cat-box">
                            <label class="cat-label">
                                <input type="checkbox" name="categories[]" value="Managing Social Media" onchange="toggleSub('sub_manage', this.checked)">
                                <strong><i class="fas fa-hashtag" style="color:#10b981;"></i> Managing Social Media (Organic)</strong>
                            </label>
                            <div id="sub_manage" class="sub-box" style="display:none;">
                                <label><input type="checkbox" name="subcategories[]" value="Management: Monthly Content Creation"> Content Creation & Design</label>
                                <label><input type="checkbox" name="subcategories[]" value="Management: Daily Scheduling"> Daily Scheduling</label>
                                <label><input type="checkbox" name="subcategories[]" value="Management: Community & DM Handling"> Community & Inbox Handling</label>
                                <label><input type="checkbox" name="subcategories[]" value="Management: Reels & Video Editing"> Reels & Video Editing</label>
                                <label><input type="checkbox" name="subcategories[]" value="Management: Monthly Growth Strategy"> Monthly Analytics & Growth</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 6: Remarks & Presets -->
                <div class="form-group">
                    <label>Remarks & One-Click Notes</label>
                    <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px;">
                        <button type="button" class="preset-tag" onclick="addRemark('Contact owner directly')">👤 Contact owner directly</button>
                        <button type="button" class="preset-tag" onclick="addRemark('Interested but budget issues')">💰 Interested but budget issues</button>
                        <button type="button" class="preset-tag" onclick="addRemark('Not interested at this time')">❌ Not interested</button>
                        <button type="button" class="preset-tag" onclick="addRemark('Ready to close / High intent')">🎯 Ready to close</button>
                        <button type="button" class="preset-tag" onclick="addRemark('Requested formal quotation')">📄 Wants Quotation</button>
                        <button type="button" class="preset-tag" onclick="addRemark('Comparing rates with agency')">🔄 Comparing rates</button>
                    </div>
                    <input type="hidden" name="quick_remark" id="f_quick_remark" value="">
                    <textarea name="remarks" id="f_remarks" rows="3" placeholder="Enter visit remarks, meeting notes, objections, or custom details..." class="form-control" style="resize:vertical;"></textarea>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeVisitModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="submitBtn">
                    <i class="fas fa-save"></i> Save Visit Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     UNIVERSAL MODAL: VIEW VISIT DETAILS
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="viewModal" class="modal-overlay" onclick="if(event.target===this)closeViewModal()">
    <div class="modal-content" style="max-width:580px;">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon"><i class="fas fa-eye"></i></div>
                <span>Visit Details</span>
            </h4>
            <button class="modal-close" onclick="closeViewModal()">×</button>
        </div>
        <div class="modal-body" id="viewModalBody" style="padding:20px;">
            <!-- Populated dynamically -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary-custom" onclick="closeViewModal()">Close</button>
            <span id="viewModalActions"></span>
        </div>
    </div>
</div>

<style>
/* ── Clean Universal Form Helpers ── */
.form-group {
    display: flex;
    flex-direction: column;
}
.form-group label {
    font-size: 13px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 5px;
}
.preset-tag {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #334155;
    padding: 4px 10px;
    border-radius: 14px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}
.preset-tag:hover {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
}
.interest-chip {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    background: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}
.interest-chip input { display: none; }
.interest-chip.active-chip {
    border-color: var(--primary);
    background: #f0fdf4;
    color: var(--primary);
}
.cat-box {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    padding: 8px 12px;
}
.cat-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 13px;
    margin: 0;
}
.cat-label input { width: 16px; height: 16px; accent-color: var(--primary); cursor: pointer; }
.sub-box {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed #e2e8f0;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.sub-box label {
    font-size: 12px;
    color: #475569;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    margin: 0;
}
.sub-box input { accent-color: var(--primary); }

/* ── Clean Universal Table Layout ── */
.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table th {
    background: #f8fafc;
    padding: 12px 16px;
    text-align: left;
    font-size: 11.5px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .05em;
    border-bottom: 1.5px solid #e2e8f0;
    white-space: nowrap;
}
.data-table td {
    padding: 13px 16px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}
.data-table tr:hover td {
    background: #f8fafc;
}
.data-table tr:last-child td {
    border-bottom: none;
}

/* ── 1-Click WhatsApp Button ── */
.btn-wa {
    background: #16a34a !important;
    color: #ffffff !important;
    font-weight: 700;
    font-size: 12px;
    padding: 6px 14px;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none !important;
    border: none;
    box-shadow: 0 2px 6px rgba(22, 163, 74, 0.25);
    white-space: nowrap;
    transition: all 0.2s ease;
}
.btn-wa:hover {
    background: #15803d !important;
    color: #ffffff !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(22, 163, 74, 0.35);
}

/* ── Action Buttons ── */
.dots {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    transition: all 0.2s ease;
}
.dots:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

/* Standard Status Badge Helpers */
.badge-hot  { background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 12px; font-size: 11.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 3px; }
.badge-warm { background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 12px; font-size: 11.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 3px; }
.badge-cold { background: #f1f5f9; color: #475569; padding: 3px 8px; border-radius: 12px; font-size: 11.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 3px; }
.badge-urg  { background: #ef4444; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 800; margin-left: 4px; }
</style>

<script>
let currentFilter = 'all';
let searchQuery   = '';
let allVisits     = [];

document.addEventListener('DOMContentLoaded', () => {
    loadVisits();
    // Auto-open modal if URL has ?log=1
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('log') === '1') {
        openVisitModal('add');
    }
});

// ── Smart WhatsApp Phone Number Formatter ──
function formatWhatsAppNumber(phone) {
    if (!phone) return '';
    let clean = phone.replace(/[^0-9]/g, '');
    if (clean.startsWith('03') && clean.length === 11) {
        clean = '92' + clean.substring(1);
    } else if (clean.startsWith('00')) {
        clean = clean.substring(2);
    } else if (clean.length === 10 && clean.startsWith('3')) {
        clean = '92' + clean;
    }
    return clean;
}

function loadVisits() {
    fetch(`sales_visits_api.php?action=fetch_all&filter=${currentFilter}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                allVisits = data.items || [];
                renderStats(data.stats || {});
                renderTable();
            } else {
                document.getElementById('visitsTableBody').innerHTML = `<tr><td colspan="7" style="text-align:center;padding:40px;color:#ef4444;">${data.message}</td></tr>`;
            }
        })
        .catch(err => {
            console.error(err);
            document.getElementById('visitsTableBody').innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#ef4444;">Failed to load visits. Please try again.</td></tr>';
        });
}

function renderStats(stats) {
    document.getElementById('statTotal').textContent     = stats.total || 0;
    document.getElementById('statUrgent').textContent    = stats.urgent || 0;
    document.getElementById('statHot').textContent       = stats.hot || 0;
    document.getElementById('statContacted').textContent = (parseInt(stats.contacted || 0) + parseInt(stats.converted || 0));
}

function setFilter(val) {
    currentFilter = val;
    loadVisits();
}

function handleSearch(val) {
    searchQuery = val.trim().toLowerCase();
    renderTable();
}

function renderTable() {
    const container = document.getElementById('visitsTableBody');
    let items = allVisits;

    if (searchQuery) {
        items = items.filter(v =>
            (v.client_name && v.client_name.toLowerCase().includes(searchQuery)) ||
            (v.company_name && v.company_name.toLowerCase().includes(searchQuery)) ||
            (v.phone && v.phone.includes(searchQuery)) ||
            (v.categories && v.categories.toLowerCase().includes(searchQuery)) ||
            (v.remarks && v.remarks.toLowerCase().includes(searchQuery))
        );
    }

    if (items.length === 0) {
        container.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center; padding:60px 24px; color:#888;">
                    <i class="fas fa-street-view" style="font-size:32px; color:#cbd5e1; margin-bottom:12px;"></i>
                    <p style="margin:0; font-size:14px; font-weight:600; color:#475569;">No client field visits found.</p>
                    <button class="add-client" onclick="openVisitModal('add')" style="margin-top:12px;">+ Log First Visit</button>
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    items.forEach(v => {
        const isUrgent = (v.is_urgent == 1);
        const rawWa    = (v.whatsapp || v.phone || '').trim();
        const cleanWa  = formatWhatsAppNumber(rawWa);
        const waMsg    = encodeURIComponent(`Hello ${v.client_name}, this is Graphicafix following up on your discussion with our team regarding ${v.company_name}. Are you available for a quick chat?`);
        
        let interestBadge = '';
        if (v.interest_level === 'hot') {
            interestBadge = '<span class="badge-hot">🔥 Hot</span>';
        } else if (v.interest_level === 'warm') {
            interestBadge = '<span class="badge-warm">⚡ Warm</span>';
        } else {
            interestBadge = '<span class="badge-cold">❄️ Cold</span>';
        }

        let catBadges = '';
        if (v.categories_arr && v.categories_arr.length > 0) {
            catBadges = v.categories_arr.slice(0, 2).map(c => `<span style="background:#e0f2fe; color:#0284c7; padding:2px 7px; border-radius:6px; font-size:11px; font-weight:600;">${escapeHtml(c)}</span>`).join(' ');
            if (v.categories_arr.length > 2) {
                catBadges += ` <span style="color:#64748b; font-size:11px;">+${v.categories_arr.length - 2}</span>`;
            }
        } else {
            catBadges = '<span style="color:#94a3b8; font-size:12px;">General</span>';
        }

        // WhatsApp Reach Out Button
        let reachOutHtml = '';
        if (cleanWa) {
            reachOutHtml = `
                <div style="display:flex; flex-direction:column; gap:3px; align-items:flex-start;">
                    <a href="https://wa.me/${cleanWa}?text=${waMsg}" target="_blank" class="btn-wa" title="1-Click WhatsApp Reach Out">
                        <i class="fab fa-whatsapp" style="font-size:14px;"></i>
                        <span>WhatsApp</span>
                    </a>
                    <a href="tel:${escapeHtml(v.phone)}" style="font-size:12px; color:#64748b; text-decoration:none; display:inline-flex; align-items:center; gap:4px; margin-top:2px;">
                        <i class="fas fa-phone-alt" style="font-size:10px; color:#94a3b8;"></i> ${escapeHtml(v.phone)}
                    </a>
                </div>
            `;
        } else {
            reachOutHtml = `
                <a href="tel:${escapeHtml(v.phone)}" style="font-size:12.5px; font-weight:600; color:#334155; text-decoration:none;">
                    <i class="fas fa-phone-alt" style="font-size:11px; color:#94a3b8; margin-right:4px;"></i> ${escapeHtml(v.phone)}
                </a>
            `;
        }

        html += `
            <tr style="${isUrgent ? 'background:#fffbfb; border-left:4px solid #ef4444;' : ''}">
                <td>
                    <div style="font-weight:700; color:#0f172a; font-size:13.5px; display:inline-flex; align-items:center; gap:6px;">
                        ${escapeHtml(v.company_name)}
                        ${isUrgent ? '<span class="badge-urg">URGENT</span>' : ''}
                    </div>
                    <div style="font-size:12px; color:#64748b; margin-top:2px;">
                        <i class="fas fa-user" style="font-size:10px; color:#94a3b8; margin-right:3px;"></i>
                        ${escapeHtml(v.client_name)}
                    </div>
                </td>
                <td>
                    ${reachOutHtml}
                </td>
                <td>
                    ${interestBadge}
                </td>
                <td>
                    <div style="font-size:12.5px; color:#334155; display:flex; align-items:center; gap:5px;">
                        <i class="far fa-clock" style="color:#64748b;"></i>
                        <strong>${escapeHtml(v.contact_availability)}</strong>
                    </div>
                </td>
                <td>
                    <div style="display:flex; flex-wrap:wrap; gap:4px;">
                        ${catBadges}
                    </div>
                </td>
                <td>
                    <div style="font-size:12px; color:#64748b;">
                        <div style="font-weight:600; color:#334155;">${escapeHtml(v.sales_rep_name)}</div>
                        <small style="color:#94a3b8;">${v.time_ago || ''}</small>
                    </div>
                </td>
                <td style="text-align:right;">
                    <div style="display:inline-flex; gap:6px; justify-content:flex-end; align-items:center;">
                        <button class="dots" onclick="viewVisit(${v.id})" title="View Details"><i class="fas fa-eye"></i></button>
                        <button class="dots" onclick="openVisitModal('edit', ${v.id})" title="Edit"><i class="fas fa-pencil-alt"></i></button>
                        <button class="dots" onclick="deleteVisit(${v.id}, '${escapeHtml(v.company_name)}')" title="Delete" style="color:#ef4444;"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>
        `;
    });

    container.innerHTML = html;
}

// ── Modal Handling ──
function openVisitModal(mode, id = null) {
    document.getElementById('visitForm').reset();
    document.getElementById('f_visit_id').value = '';
    document.querySelectorAll('.sub-box').forEach(el => el.style.display = 'none');
    setInterestUI('warm');
    document.getElementById('chip_warm').querySelector('input').checked = true;

    if (mode === 'add') {
        document.getElementById('visitModalTitle').textContent = 'Log Client Field Visit';
        document.getElementById('visitModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.getElementById('f_client_name').focus();
    } else if (mode === 'edit' && id) {
        document.getElementById('visitModalTitle').textContent = 'Edit Client Visit';
        const v = allVisits.find(item => item.id == id);
        if (!v) return;

        document.getElementById('f_visit_id').value             = v.id;
        document.getElementById('f_client_name').value          = v.client_name;
        document.getElementById('f_company_name').value         = v.company_name;
        document.getElementById('f_phone').value                = v.phone;
        document.getElementById('f_whatsapp').value             = v.whatsapp || v.phone;
        document.getElementById('f_contact_availability').value = v.contact_availability;
        document.getElementById('f_remarks').value              = v.remarks || '';
        document.getElementById('f_quick_remark').value         = v.quick_remark || '';
        document.getElementById('f_is_urgent').checked          = (v.is_urgent == 1);

        const r = document.querySelector(`input[name="interest_level"][value="${v.interest_level}"]`);
        if (r) { r.checked = true; setInterestUI(v.interest_level); }

        if (v.categories_arr) {
            v.categories_arr.forEach(cat => {
                const cb = document.querySelector(`input[name="categories[]"][value="${cat}"]`);
                if (cb) {
                    cb.checked = true;
                    const map = { 'Website':'sub_web', 'Printing':'sub_print', 'Design':'sub_design', 'Social Media Marketing':'sub_smm', 'Managing Social Media':'sub_manage' };
                    if (map[cat]) document.getElementById(map[cat]).style.display = 'flex';
                }
            });
        }
        if (v.subcategories_arr) {
            v.subcategories_arr.forEach(sub => {
                const scb = document.querySelector(`input[name="subcategories[]"][value="${sub}"]`);
                if (scb) scb.checked = true;
            });
        }

        document.getElementById('visitModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeVisitModal() {
    document.getElementById('visitModal').classList.remove('active');
    document.body.style.overflow = '';
}

function setInterestUI(lvl) {
    document.querySelectorAll('.interest-chip').forEach(c => c.classList.remove('active-chip'));
    const target = document.getElementById('chip_' + lvl);
    if (target) target.classList.add('active-chip');
}

function copyPhoneToWa() {
    const p = document.getElementById('f_phone').value.trim();
    if (p) document.getElementById('f_whatsapp').value = p;
}

function setAvailPreset(txt) {
    document.getElementById('f_contact_availability').value = txt;
}

function toggleSub(id, checked) {
    const el = document.getElementById(id);
    if (el) el.style.display = checked ? 'flex' : 'none';
}

function addRemark(preset) {
    document.getElementById('f_quick_remark').value = preset;
    const txt = document.getElementById('f_remarks');
    if (!txt.value.includes(preset)) {
        txt.value = txt.value ? (txt.value + ' | ' + preset) : preset;
    }
}

function submitVisitForm(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const formData = new FormData(document.getElementById('visitForm'));
    fetch('sales_visits_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save Visit Record';
            if (data.success) {
                closeVisitModal();
                loadVisits();
                alert('Client visit record saved successfully!');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save Visit Record';
            alert('Failed to save visit record. Please try again.');
        });
}

function deleteVisit(id, company) {
    if (!confirm(`Are you sure you want to delete the visit record for "${company}"?`)) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadVisits();
            } else {
                alert('Error: ' + data.message);
            }
        });
}

function viewVisit(id) {
    const v = allVisits.find(item => item.id == id);
    if (!v) return;

    const cleanWa = (v.whatsapp || v.phone || '').replace(/[^0-9]/g, '');
    const waMsg   = encodeURIComponent(`Hello ${v.client_name}, this is Graphicafix following up on your discussion regarding ${v.company_name}.`);

    let cats = (v.categories_arr && v.categories_arr.length) ? v.categories_arr.join(', ') : 'None';
    let subs = (v.subcategories_arr && v.subcategories_arr.length) ? v.subcategories_arr.join('; ') : 'None';

    document.getElementById('viewModalBody').innerHTML = `
        <div style="margin-bottom:16px;">
            <div style="font-size:18px; font-weight:800; color:#0f172a;">${escapeHtml(v.company_name)} ${v.is_urgent == 1 ? '<span class="badge-urg">URGENT</span>' : ''}</div>
            <div style="color:#64748b; font-size:13.5px;">Contact: <strong>${escapeHtml(v.client_name)}</strong></div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px; font-size:13px;">
            <div style="background:#f8faf9; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11.5px; font-weight:600;">PHONE</div>
                <div style="font-weight:700; color:#0f172a;">${escapeHtml(v.phone)}</div>
            </div>
            <div style="background:#f8faf9; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11.5px; font-weight:600;">WHATSAPP</div>
                <div style="font-weight:700; color:#16a34a;">${escapeHtml(v.whatsapp || v.phone)}</div>
            </div>
            <div style="background:#f8faf9; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11.5px; font-weight:600;">INTEREST LEVEL</div>
                <div style="font-weight:700; text-transform:uppercase;">${v.interest_level}</div>
            </div>
            <div style="background:#f8faf9; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11.5px; font-weight:600;">CONTACT WINDOW</div>
                <div style="font-weight:700; color:#0f172a;">${escapeHtml(v.contact_availability)}</div>
            </div>
        </div>
        <div style="margin-bottom:12px; font-size:13px;">
            <div style="color:#64748b; font-size:11.5px; font-weight:600;">SERVICES REQUESTED</div>
            <div style="color:#0f172a; margin-top:2px;">${escapeHtml(cats)}</div>
            ${subs !== 'None' ? `<div style="color:#64748b; font-size:12px; margin-top:3px;"><em>Sub-items:</em> ${escapeHtml(subs)}</div>` : ''}
        </div>
        <div style="margin-bottom:12px; font-size:13px;">
            <div style="color:#64748b; font-size:11.5px; font-weight:600;">REMARKS / NOTES</div>
            <div style="background:#f1f5f9; padding:10px; border-radius:8px; color:#334155; margin-top:4px; font-size:12.5px;">${escapeHtml(v.remarks || 'No remarks entered.')}</div>
        </div>
        <div style="font-size:12px; color:#94a3b8; border-top:1px solid #f1f5f9; padding-top:10px;">
            Logged by ${escapeHtml(v.sales_rep_name)} on ${v.created_at}
        </div>
    `;

    document.getElementById('viewModalActions').innerHTML = cleanWa ? `
        <a href="https://wa.me/${cleanWa}?text=${waMsg}" target="_blank" class="btn btn-primary-custom" style="background:#16a34a; border-color:#16a34a;">
            <i class="fab fa-whatsapp"></i> WhatsApp Reach Out
        </a>
    ` : '';

    document.getElementById('viewModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
    document.body.style.overflow = '';
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
</script>

<?php include 'dashboard_footer.php'; ?>
