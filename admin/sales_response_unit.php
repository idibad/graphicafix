<?php
include 'dashboard_header.php';

// Auth Check: Super Admins, Admins, PMs, Managers can access Response Unit
$allowed_roles = ['super_admin', 'superadmin', 'admin', 'manager', 'pm'];
if (!in_array($role, $allowed_roles)) {
    header("Location: index.php?error=unauthorized");
    exit;
}

$user_id_int = intval($_SESSION['user_id'] ?? 0);
$user_display_name = htmlspecialchars($_SESSION['name'] ?? ($_SESSION['username'] ?? 'Admin'));
?>

<div class="height-100">

    <!-- ── Universal Stats Row ── -->
    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Inbound Visits</span>
                <div class="stat-icon" style="background:#f1f5f9;color:#0f172a;"><i class="fas fa-satellite-dish"></i></div>
            </div>
            <div class="stat-number" id="statTotal">--</div>
        </div>
        <div class="stat-card" style="border-left: 3.5px solid #ef4444;">
            <div class="stat-header">
                <span class="stat-label">Urgent Follow-ups</span>
                <div class="stat-icon" style="background:#fee2e2;color:#ef4444;"><i class="fas fa-exclamation-circle"></i></div>
            </div>
            <div class="stat-number" id="statUrgent" style="color:#ef4444;">--</div>
        </div>
        <div class="stat-card" style="border-left: 3.5px solid #d97706;">
            <div class="stat-header">
                <span class="stat-label">High Priority Leads</span>
                <div class="stat-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-layer-group"></i></div>
            </div>
            <div class="stat-number" id="statHot" style="color:#d97706;">--</div>
        </div>
        <div class="stat-card" style="border-left: 3.5px solid #16a34a;">
            <div class="stat-header">
                <span class="stat-label">Contacted & Resolved</span>
                <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="stat-number" id="statContacted" style="color:#16a34a;">--</div>
        </div>
    </div>

    <!-- ── Main Card & Radar Table ── -->
    <div class="main-card">
        <div class="main-header" style="flex-wrap:wrap; gap:12px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <h3 style="margin:0; font-size:17px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-headset" style="color:var(--primary);"></i> Sales Response Unit
                </h3>
                <span id="radarPulseBadge" class="live-status-pill">
                    <span class="live-status-dot"></span> Live Radar (3.5s)
                </span>
            </div>

            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <button type="button" onclick="openExportModal()" class="btn btn-secondary-custom" style="padding:7px 14px; font-size:12.5px; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-file-excel" style="color:#16a34a;"></i> Export Sheets
                </button>
                <button type="button" onclick="enablePushNotifications()" id="pushNotifyBtn" class="btn btn-secondary-custom" style="padding:7px 14px; font-size:12.5px; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-bell"></i> Desktop Alerts
                </button>
                <button type="button" onclick="testNotificationChime()" class="btn btn-secondary-custom" style="padding:7px 12px; font-size:12.5px;" title="Test Audio Chime">
                    <i class="fas fa-volume-up"></i>
                </button>
            </div>
        </div>

        <!-- Filter & Sorting Toolbar -->
        <div style="padding:14px 20px; border-bottom:1px solid #f1f5f9; background:#fafbfc; display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between;">
            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                
                <!-- Filter Dropdown -->
                <div style="display:flex; align-items:center; gap:6px;">
                    <span style="font-size:12.5px; font-weight:600; color:#64748b;">Filter:</span>
                    <select id="filterSelect" onchange="setFilter(this.value)" class="form-select" style="width:auto; padding:5px 12px; font-size:12.5px; height:34px;">
                        <option value="all">All Inbound Visits</option>
                        <option value="urgent">Urgent Follow-ups Only</option>
                        <option value="pending" selected>Pending Contact</option>
                        <option value="hot">High Priority (Hot)</option>
                        <option value="warm">Medium Priority (Warm)</option>
                        <option value="contacted">Contacted</option>
                        <option value="converted">Converted to CRM</option>
                    </select>
                </div>

                <!-- Sort By Dropdown -->
                <div style="display:flex; align-items:center; gap:6px;">
                    <span style="font-size:12.5px; font-weight:600; color:#64748b;">Sort:</span>
                    <select id="sortSelect" onchange="setSort(this.value)" class="form-select" style="width:auto; padding:5px 12px; font-size:12.5px; height:34px;">
                        <option value="urgency">Urgency First</option>
                        <option value="interest">Priority Level (High to Standard)</option>
                        <option value="contact_time">Preferred Contact Window</option>
                        <option value="newest">Newest Logged First</option>
                    </select>
                </div>

            </div>

            <!-- Search Input -->
            <div style="position:relative; width:100%; max-width:280px;">
                <input type="text" id="searchInput" oninput="handleSearch(this.value)" placeholder="Search client, company, phone..."
                       class="form-control" style="padding-left:32px; font-size:12.5px; height:34px;">
                <i class="fas fa-search" style="position:absolute; left:11px; top:10px; color:#94a3b8; font-size:12px;"></i>
            </div>
        </div>

        <!-- Table Container -->
        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%; border-collapse:collapse; min-width:920px;">
                <thead>
                    <tr>
                        <th style="width:23%;">Client & Company</th>
                        <th style="width:17%;">Direct Connect</th>
                        <th style="width:12%;">Priority</th>
                        <th style="width:16%;">Contact Window</th>
                        <th style="width:14%;">Requirements</th>
                        <th style="width:10%;">Sales Rep</th>
                        <th style="width:8%; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="radarTableBody">
                    <tr>
                        <td colspan="7" style="text-align:center; padding:50px 20px; color:#888;">
                            <i class="fas fa-spinner fa-spin" style="font-size:24px; margin-bottom:10px; color:var(--primary);"></i>
                            <p style="margin:0; font-size:13.5px;">Connecting to Live Response Radar...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     RESPONSE WORKSPACE MODAL (Rapid Response & Canned WhatsApp Templates)
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="detailModal" class="modal-overlay" onclick="if(event.target===this)closeDetailModal()">
    <div class="modal-content" style="max-width:720px; padding:0; overflow:hidden;">
        <div class="modal-header" style="padding:16px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0;">
            <div style="display:flex; align-items:center; gap:8px;">
                <div class="modal-header-icon" style="background:var(--light); color:var(--primary); width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-headset"></i>
                </div>
                <div>
                    <h4 id="detailModalTitle" style="margin:0; font-size:15px; font-weight:700; color:#0f172a;">Response Workspace</h4>
                    <span id="detailModalSub" style="font-size:12px; color:#64748b;">Client Follow-up & Rapid Response</span>
                </div>
            </div>
            <button class="modal-close" onclick="closeDetailModal()" style="font-size:20px; color:#94a3b8; background:none; border:none; cursor:pointer;">×</button>
        </div>

        <div class="modal-body" id="detailModalBody" style="padding:20px; max-height:75vh; overflow-y:auto;">
            <!-- Populated dynamically -->
        </div>

        <div class="modal-footer" style="padding:12px 20px; background:#f8fafc; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <div id="detailWaWrap"></div>
            <button type="button" class="btn btn-secondary-custom" onclick="closeDetailModal()">Close Workspace</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     EXPORT SHEETS MODAL (Sales Rep & Date-wise Spreadsheet Export)
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="exportModal" class="modal-overlay" onclick="if(event.target===this)closeExportModal()">
    <div class="modal-content" style="max-width:540px; padding:0; overflow:hidden;">
        <div class="modal-header" style="padding:16px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0;">
            <div style="display:flex; align-items:center; gap:8px;">
                <div class="modal-header-icon" style="background:#dcfce7; color:#16a34a; width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-file-excel"></i>
                </div>
                <div>
                    <h4 style="margin:0; font-size:15px; font-weight:700; color:#0f172a;">Export Field Visits Sheet</h4>
                    <span style="font-size:12px; color:#64748b;">Download formatted spreadsheet (Excel & CSV compatible)</span>
                </div>
            </div>
            <button class="modal-close" onclick="closeExportModal()" style="font-size:20px; color:#94a3b8; background:none; border:none; cursor:pointer;">×</button>
        </div>

        <form id="exportForm" onsubmit="executeExport(event)" style="padding:20px;">
            <!-- Filter by Sales Rep -->
            <div class="form-group" style="margin-bottom:16px;">
                <label style="font-size:12.5px; font-weight:700; color:#334155; margin-bottom:5px; display:block;">
                    Sales Representative
                </label>
                <select id="exportRepSelect" class="form-select" style="width:100%; font-size:13px; padding:8px 12px;">
                    <option value="0">All Representatives (Company-wide)</option>
                    <!-- Populated dynamically -->
                </select>
            </div>

            <!-- Date Presets -->
            <div style="margin-bottom:14px;">
                <label style="font-size:12.5px; font-weight:700; color:#334155; margin-bottom:6px; display:block;">
                    Date Range Preset
                </label>
                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                    <button type="button" class="preset-pill active" onclick="setExportDatePreset('all', this)">All Time</button>
                    <button type="button" class="preset-pill" onclick="setExportDatePreset('today', this)">Today</button>
                    <button type="button" class="preset-pill" onclick="setExportDatePreset('yesterday', this)">Yesterday</button>
                    <button type="button" class="preset-pill" onclick="setExportDatePreset('week', this)">Last 7 Days</button>
                    <button type="button" class="preset-pill" onclick="setExportDatePreset('month', this)">This Month</button>
                </div>
            </div>

            <!-- Custom Date Range -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                <div class="form-group">
                    <label style="font-size:12px; font-weight:600; color:#64748b; margin-bottom:4px; display:block;">From Date</label>
                    <input type="date" id="exportStartDate" class="form-control" style="font-size:12.5px; padding:6px 10px;">
                </div>
                <div class="form-group">
                    <label style="font-size:12px; font-weight:600; color:#64748b; margin-bottom:4px; display:block;">To Date</label>
                    <input type="date" id="exportEndDate" class="form-control" style="font-size:12.5px; padding:6px 10px;">
                </div>
            </div>

            <!-- Status & Priority Filters -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:18px;">
                <div class="form-group">
                    <label style="font-size:12px; font-weight:600; color:#64748b; margin-bottom:4px; display:block;">Status</label>
                    <select id="exportStatusSelect" class="form-select" style="font-size:12.5px; padding:6px 10px;">
                        <option value="all">All Statuses</option>
                        <option value="pending">Pending Contact</option>
                        <option value="contacted">Contacted</option>
                        <option value="in_discussion">In Discussion</option>
                        <option value="converted">Converted to Lead</option>
                        <option value="closed">Closed / Resolved</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="font-size:12px; font-weight:600; color:#64748b; margin-bottom:4px; display:block;">Priority</label>
                    <select id="exportInterestSelect" class="form-select" style="font-size:12.5px; padding:6px 10px;">
                        <option value="all">All Priorities</option>
                        <option value="hot">High (Hot)</option>
                        <option value="warm">Medium (Warm)</option>
                        <option value="cold">Standard (Cold)</option>
                    </select>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid #f1f5f9; padding-top:16px;">
                <button type="button" class="btn btn-secondary-custom" onclick="closeExportModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" style="display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-download"></i> Download Spreadsheet
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* ── Clean Universal Table Layout ── */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    background: #fff;
}
.data-table th {
    background: #f8fafc;
    padding: 12px 16px;
    text-align: left;
    font-size: 11px;
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

/* ── Live Pulse Pill ── */
.live-status-pill {
    font-size: 11.5px;
    font-weight: 700;
    color: #16a34a;
    background: #dcfce7;
    padding: 3px 10px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.live-status-dot {
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #16a34a;
}

/* ── Direct Reach Out Button ── */
.btn-wa-direct {
    background: #16a34a !important;
    color: #ffffff !important;
    font-weight: 600;
    font-size: 12px;
    padding: 5px 12px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none !important;
    border: none;
    box-shadow: 0 1px 3px rgba(22, 163, 74, 0.2);
    white-space: nowrap;
    transition: all 0.15s ease;
}
.btn-wa-direct:hover {
    background: #15803d !important;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(22, 163, 74, 0.3);
}

.contact-phone-link {
    font-size: 12px;
    color: #64748b;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 3px;
    font-weight: 500;
}
.contact-phone-link:hover {
    color: #0f172a;
    text-decoration: underline;
}

/* ── Professional Badges ── */
.badge-priority {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
}
.badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}
.badge-hot   { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }
.badge-dot-hot { background: #ef4444; }
.badge-warm  { background: #fffbeb; color: #b45309; border: 1px solid #fef3c7; }
.badge-dot-warm { background: #f59e0b; }
.badge-cold  { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
.badge-dot-cold { background: #94a3b8; }

.badge-urgent-tag {
    background: #ef4444;
    color: #ffffff;
    font-size: 9.5px;
    font-weight: 800;
    padding: 2px 5px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-left: 5px;
}

.badge-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}
.badge-status-pending    { background: #fef3c7; color: #92400e; }
.badge-status-contacted  { background: #e0f2fe; color: #0369a1; }
.badge-status-discussion { background: #ede9fe; color: #6d28d9; }
.badge-status-converted  { background: #dcfce7; color: #15803d; }
.badge-status-closed     { background: #f1f5f9; color: #64748b; }

/* ── Action Buttons ── */
.dots {
    width: 30px;
    height: 30px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    transition: all 0.15s ease;
}
.dots:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

/* ── Date Preset Pills ── */
.preset-pill {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 600;
    padding: 4px 10px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.preset-pill:hover,
.preset-pill.active {
    background: var(--primary, #024442);
    color: #ffffff;
    border-color: var(--primary, #024442);
}

/* ── Template Cards in Modal ── */
.template-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 12px;
    margin-bottom: 8px;
    transition: all 0.15s ease;
}
.template-card:hover {
    border-color: #cbd5e1;
    background: #fff;
}
.template-text {
    font-size: 12px;
    color: #334155;
    line-height: 1.45;
    margin: 6px 0 8px;
    background: #fff;
    padding: 8px 10px;
    border-radius: 6px;
    border: 1px dashed #cbd5e1;
}
</style>

<script>
let lastKnownId   = 0;
let pollingTimer  = null;
let syncTimer     = null;
let radarItems    = [];
let currentFilter = 'pending';
let currentSort   = 'urgency';
let searchQuery   = '';
let audioCtx      = null;

document.addEventListener('DOMContentLoaded', () => {
    checkNotificationPermission();
    fetchFullRadar(true);
    startPolling();
    loadSalesRepsForExport();

    // Listen to global sales visit events dispatched from dashboard_header.php
    window.addEventListener('globalSalesVisitArrived', (e) => {
        fetchFullRadar(false);
    });
});

// ── Smart Phone Formatter (Pakistan / International wa.me standard) ──
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

// ── Radar Polling Engine ──
function startPolling() {
    if (pollingTimer) clearInterval(pollingTimer);
    pollingTimer = setInterval(pollLatestVisits, 3500);

    // Silent background sync every 12s so status updates refresh automatically
    if (syncTimer) clearInterval(syncTimer);
    syncTimer = setInterval(() => {
        fetchFullRadar(false);
    }, 12000);
}

function pollLatestVisits() {
    fetch(`sales_visits_api.php?action=poll&last_id=${lastKnownId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (data.has_new || (data.items && data.items.length > 0)) {
                    playNotificationChime();
                    const newItems = data.items || data.new_records || [];
                    newItems.forEach(v => triggerDesktopAlert(v));
                    if (data.latest_id && data.latest_id > lastKnownId) {
                        lastKnownId = data.latest_id;
                    }
                    fetchFullRadar(false);
                } else if (data.stats) {
                    renderStats(data.stats);
                }
            }
        })
        .catch(e => console.warn('Radar poll note:', e));
}

function fetchFullRadar(showLoading = true) {
    if (showLoading && radarItems.length === 0) {
        document.getElementById('radarTableBody').innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center; padding:50px 20px; color:#888;">
                    <i class="fas fa-spinner fa-spin" style="font-size:24px; margin-bottom:10px; color:var(--primary);"></i>
                    <p style="margin:0;">Scanning live radar...</p>
                </td>
            </tr>
        `;
    }

    fetch(`sales_visits_api.php?action=fetch_all&filter=${currentFilter}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                radarItems = data.items || [];
                
                radarItems.forEach(v => {
                    const vid = parseInt(v.id);
                    if (vid > lastKnownId) lastKnownId = vid;
                });
                if (data.latest_id && data.latest_id > lastKnownId) {
                    lastKnownId = data.latest_id;
                }

                renderStats(data.stats || {});
                renderRadarTable();
            }
        })
        .catch(err => {
            console.error('Radar fetch error:', err);
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
    fetchFullRadar(true);
}

function setSort(val) {
    currentSort = val;
    renderRadarTable();
}

function handleSearch(val) {
    searchQuery = val.trim().toLowerCase();
    renderRadarTable();
}

function renderRadarTable() {
    const container = document.getElementById('radarTableBody');
    let items = [...radarItems];

    if (searchQuery) {
        items = items.filter(v =>
            (v.client_name && v.client_name.toLowerCase().includes(searchQuery)) ||
            (v.company_name && v.company_name.toLowerCase().includes(searchQuery)) ||
            (v.phone && v.phone.includes(searchQuery)) ||
            (v.categories && v.categories.toLowerCase().includes(searchQuery)) ||
            (v.remarks && v.remarks.toLowerCase().includes(searchQuery))
        );
    }

    if (currentSort === 'urgency') {
        items.sort((a, b) => {
            if (b.is_urgent !== a.is_urgent) return b.is_urgent - a.is_urgent;
            const rank = { 'hot': 3, 'warm': 2, 'cold': 1 };
            return (rank[b.interest_level] || 0) - (rank[a.interest_level] || 0);
        });
    } else if (currentSort === 'interest') {
        items.sort((a, b) => {
            const rank = { 'hot': 3, 'warm': 2, 'cold': 1 };
            if ((rank[b.interest_level] || 0) !== (rank[a.interest_level] || 0)) {
                return (rank[b.interest_level] || 0) - (rank[a.interest_level] || 0);
            }
            return b.is_urgent - a.is_urgent;
        });
    } else if (currentSort === 'contact_time') {
        items.sort((a, b) => (a.contact_availability || '').localeCompare(b.contact_availability || ''));
    } else if (currentSort === 'newest') {
        items.sort((a, b) => parseInt(b.id) - parseInt(a.id));
    }

    if (items.length === 0) {
        container.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center; padding:60px 24px; color:#888;">
                    <i class="fas fa-check-circle" style="font-size:30px; color:#cbd5e1; margin-bottom:10px;"></i>
                    <p style="margin:0; font-size:14px; font-weight:600; color:#475569;">No visits matching this filter.</p>
                    <small style="color:#94a3b8;">Radar is active and listening for new field visits...</small>
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    items.forEach(v => {
        const isUrgent   = (v.is_urgent == 1);
        const rawWa      = (v.whatsapp || v.phone || '').trim();
        const cleanWa    = formatWhatsAppNumber(rawWa);
        const waMsg      = encodeURIComponent(`Hello ${v.client_name}, this is Graphicafix management reaching out regarding your discussion for ${v.company_name}. When is a convenient time for a quick 5-minute call?`);

        let priorityBadge = '';
        if (v.interest_level === 'hot') {
            priorityBadge = '<span class="badge-priority badge-hot"><span class="badge-dot badge-dot-hot"></span>High</span>';
        } else if (v.interest_level === 'warm') {
            priorityBadge = '<span class="badge-priority badge-warm"><span class="badge-dot badge-dot-warm"></span>Medium</span>';
        } else {
            priorityBadge = '<span class="badge-priority badge-cold"><span class="badge-dot badge-dot-cold"></span>Standard</span>';
        }

        let catBadges = '';
        if (v.categories_arr && v.categories_arr.length > 0) {
            catBadges = v.categories_arr.slice(0, 2).map(c => `<span style="background:#e0f2fe; color:#0284c7; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600;">${escapeHtml(c)}</span>`).join(' ');
            if (v.categories_arr.length > 2) {
                catBadges += ` <span style="color:#64748b; font-size:11px;">+${v.categories_arr.length - 2}</span>`;
            }
        } else {
            catBadges = '<span style="color:#94a3b8; font-size:12px;">General</span>';
        }

        let reachOutHtml = '';
        if (cleanWa) {
            reachOutHtml = `
                <div style="display:flex; flex-direction:column; gap:2px; align-items:flex-start;">
                    <a href="https://wa.me/${cleanWa}?text=${waMsg}" target="_blank" class="btn-wa-direct" title="Open WhatsApp Chat">
                        <i class="fab fa-whatsapp"></i>
                        <span>WhatsApp</span>
                    </a>
                    <a href="tel:${escapeHtml(v.phone)}" class="contact-phone-link" title="Call directly">
                        <i class="fas fa-phone-alt" style="font-size:10px;"></i> ${escapeHtml(v.phone)}
                    </a>
                </div>
            `;
        } else {
            reachOutHtml = `
                <a href="tel:${escapeHtml(v.phone)}" class="contact-phone-link" style="font-weight:600; color:#334155;">
                    <i class="fas fa-phone-alt" style="font-size:11px;"></i> ${escapeHtml(v.phone)}
                </a>
            `;
        }

        html += `
            <tr style="${isUrgent ? 'background:#fffbfb; border-left:4px solid #ef4444;' : ''}">
                <td>
                    <div style="font-weight:700; color:#0f172a; font-size:13.5px; display:inline-flex; align-items:center;">
                        ${escapeHtml(v.company_name)}
                        ${isUrgent ? '<span class="badge-urgent-tag">Urgent</span>' : ''}
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
                    ${priorityBadge}
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
                        <button class="btn btn-primary-custom" onclick="openDetailModal(${v.id})" style="padding:4px 10px; font-size:11.5px; font-weight:600;" title="Open Response Workspace">
                            Respond
                        </button>
                        <button class="dots" onclick="toggleUrgent(${v.id})" title="${isUrgent ? 'Remove Urgent Priority' : 'Mark as Urgent'}" style="${isUrgent ? 'color:#ef4444; border-color:#fca5a5;' : ''}">
                            <i class="fas fa-bell"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    container.innerHTML = html;
}

// ── Urgent Toggle ──
function toggleUrgent(id) {
    const fd = new FormData();
    fd.append('action', 'toggle_urgent');
    fd.append('id', id);
    fd.append('visit_id', id);
    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                fetchFullRadar(false);
            } else {
                alert('Error: ' + data.message);
            }
        });
}

// ── Response Workspace Modal ──
function openDetailModal(id) {
    const v = radarItems.find(item => item.id == id);
    if (!v) return;

    const rawWa   = (v.whatsapp || v.phone || '').trim();
    const cleanWa = formatWhatsAppNumber(rawWa);
    const isUrgent = (v.is_urgent == 1);

    document.getElementById('detailModalTitle').textContent = v.company_name;
    document.getElementById('detailModalSub').textContent = `Client: ${v.client_name} · Logged by ${v.sales_rep_name} (${v.time_ago || 'Recent'})`;

    let cats = (v.categories_arr && v.categories_arr.length) ? v.categories_arr.join(', ') : 'None';
    let subs = (v.subcategories_arr && v.subcategories_arr.length) ? v.subcategories_arr.join('; ') : 'None';

    // Canned Response Templates
    const tpl1 = `Hello ${v.client_name}, this is Graphicafix management following up on your discussion with our team member (${v.sales_rep_name}) regarding ${v.company_name}. We would like to connect and assist you. When is a convenient time for a quick 5-minute call?`;
    const tpl2 = `Hello ${v.client_name}, regarding your interest in ${cats !== 'None' ? cats : 'our creative services'} for ${v.company_name}, our team at Graphicafix has prepared initial details for you. Would you like us to share our portfolio and package options here?`;
    const tpl3 = `Hello ${v.client_name}, following up on your meeting regarding ${v.company_name}, are you available for a brief discovery call today around ${v.contact_availability || 'your preferred time'} to finalize the scope?`;

    const encTpl1 = encodeURIComponent(tpl1);
    const encTpl2 = encodeURIComponent(tpl2);
    const encTpl3 = encodeURIComponent(tpl3);

    document.getElementById('detailModalBody').innerHTML = `
        <!-- Client Overview Cards -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:10px; margin-bottom:16px;">
            <div style="background:#f8fafc; padding:10px 12px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase;">Client & Phone</div>
                <div style="font-weight:700; color:#0f172a; font-size:13px; margin-top:2px;">${escapeHtml(v.client_name)}</div>
                <div style="font-size:12px; color:#475569;">${escapeHtml(v.phone)}</div>
            </div>
            <div style="background:#f8fafc; padding:10px 12px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase;">Availability Window</div>
                <div style="font-weight:700; color:#0f172a; font-size:13px; margin-top:2px;">${escapeHtml(v.contact_availability)}</div>
                <div style="font-size:11.5px; color:#64748b;">Logged: ${v.time_ago || 'Recently'}</div>
            </div>
            <div style="background:#f8fafc; padding:10px 12px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase;">Priority & Status</div>
                <div style="display:flex; align-items:center; gap:6px; margin-top:3px;">
                    <span class="badge-priority badge-${v.interest_level}"><span class="badge-dot badge-dot-${v.interest_level}"></span>${v.interest_level.toUpperCase()}</span>
                    ${isUrgent ? '<span class="badge-urgent-tag">Urgent</span>' : ''}
                </div>
            </div>
        </div>

        <!-- Requirements & Field Remarks -->
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; margin-bottom:16px;">
            <div style="font-size:11.5px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:4px;">Requirements & Services</div>
            <div style="font-size:13px; color:#0f172a; font-weight:600;">${escapeHtml(cats)}</div>
            ${subs !== 'None' ? `<div style="font-size:12px; color:#64748b; margin-top:3px;">Sub-items: ${escapeHtml(subs)}</div>` : ''}

            <div style="font-size:11.5px; font-weight:700; color:#475569; text-transform:uppercase; margin-top:10px; margin-bottom:4px;">Field Rep Remarks</div>
            <div style="background:#f8fafc; padding:8px 10px; border-radius:6px; font-size:12.5px; color:#334155; line-height:1.4;">
                ${escapeHtml(v.remarks || 'No specific remarks noted.')}
            </div>
        </div>

        <!-- Canned Rapid Response Messages -->
        <div style="margin-bottom:18px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <div style="font-size:12.5px; font-weight:700; color:#0f172a;">Rapid WhatsApp Templates</div>
                <span style="font-size:11px; color:#64748b;">Click to send directly or copy</span>
            </div>

            <div class="template-card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <strong style="font-size:12px; color:#0f172a;">1. Official Follow-up & Greeting</strong>
                    <div style="display:flex; gap:6px;">
                        <button type="button" class="btn btn-secondary-custom" style="padding:3px 8px; font-size:11px;" onclick="copyTemplateText('${escapeJsString(tpl1)}', this)">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                        ${cleanWa ? `
                            <a href="https://wa.me/${cleanWa}?text=${encTpl1}" target="_blank" class="btn-wa-direct" style="padding:3px 10px; font-size:11.5px;">
                                <i class="fab fa-whatsapp"></i> Send
                            </a>
                        ` : ''}
                    </div>
                </div>
                <div class="template-text">${escapeHtml(tpl1)}</div>
            </div>

            <div class="template-card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <strong style="font-size:12px; color:#0f172a;">2. Services Portfolio & Packages</strong>
                    <div style="display:flex; gap:6px;">
                        <button type="button" class="btn btn-secondary-custom" style="padding:3px 8px; font-size:11px;" onclick="copyTemplateText('${escapeJsString(tpl2)}', this)">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                        ${cleanWa ? `
                            <a href="https://wa.me/${cleanWa}?text=${encTpl2}" target="_blank" class="btn-wa-direct" style="padding:3px 10px; font-size:11.5px;">
                                <i class="fab fa-whatsapp"></i> Send
                            </a>
                        ` : ''}
                    </div>
                </div>
                <div class="template-text">${escapeHtml(tpl2)}</div>
            </div>

            <div class="template-card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <strong style="font-size:12px; color:#0f172a;">3. Schedule Discovery Session</strong>
                    <div style="display:flex; gap:6px;">
                        <button type="button" class="btn btn-secondary-custom" style="padding:3px 8px; font-size:11px;" onclick="copyTemplateText('${escapeJsString(tpl3)}', this)">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                        ${cleanWa ? `
                            <a href="https://wa.me/${cleanWa}?text=${encTpl3}" target="_blank" class="btn-wa-direct" style="padding:3px 10px; font-size:11.5px;">
                                <i class="fab fa-whatsapp"></i> Send
                            </a>
                        ` : ''}
                    </div>
                </div>
                <div class="template-text">${escapeHtml(tpl3)}</div>
            </div>
        </div>

        <!-- Quick Interaction Note & Status Update -->
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px;">
            <div style="font-size:12.5px; font-weight:700; color:#0f172a; margin-bottom:6px;">Record Response Interaction & Status</div>
            
            <div style="margin-bottom:10px;">
                <textarea id="modalInteractionNote" class="form-control" rows="2" placeholder="e.g. Called client at 4:30 PM. Client asked to share portfolio via WhatsApp. Sent proposal..." style="font-size:12.5px;"></textarea>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:12px; font-weight:600; color:#475569;">Set Status:</span>
                    <select id="modalStatusSelect" class="form-select" style="width:auto; font-size:12.5px; padding:4px 10px; height:32px;">
                        <option value="pending" ${v.status==='pending'?'selected':''}>Pending</option>
                        <option value="contacted" ${v.status==='contacted'?'selected':''}>Contacted</option>
                        <option value="in_discussion" ${v.status==='in_discussion'?'selected':''}>In Discussion</option>
                        <option value="converted" ${v.status==='converted'?'selected':''}>Converted to Lead</option>
                        <option value="closed" ${v.status==='closed'?'selected':''}>Closed / Resolved</option>
                    </select>
                </div>

                <div style="display:flex; gap:8px;">
                    ${v.status !== 'converted' ? `
                        <button type="button" class="btn btn-secondary-custom" onclick="convertToLeadModal(${v.id})" style="padding:6px 12px; font-size:12px; font-weight:600;">
                            <i class="fas fa-filter"></i> Convert to CRM Lead
                        </button>
                    ` : ''}
                    <button type="button" class="btn btn-primary-custom" onclick="saveResponseInteraction(${v.id})" style="padding:6px 14px; font-size:12px; font-weight:600;">
                        Save Interaction
                    </button>
                </div>
            </div>

            ${v.admin_notes ? `
                <div style="margin-top:12px; padding-top:10px; border-top:1px dashed #cbd5e1;">
                    <div style="font-size:11px; font-weight:700; color:#64748b; margin-bottom:4px; text-transform:uppercase;">Communication History:</div>
                    <pre style="background:#fff; padding:8px 10px; border-radius:6px; font-size:11.5px; color:#475569; white-space:pre-wrap; border:1px solid #e2e8f0; margin:0; font-family:inherit;">${escapeHtml(v.admin_notes)}</pre>
                </div>
            ` : ''}
        </div>
    `;

    document.getElementById('detailWaWrap').innerHTML = cleanWa ? `
        <a href="https://wa.me/${cleanWa}?text=${encodeURIComponent(`Hello ${v.client_name}, this is Graphicafix management reaching out regarding ${v.company_name}.`)}" target="_blank" class="btn-wa-direct">
            <i class="fab fa-whatsapp"></i> Direct WhatsApp Chat
        </a>
    ` : '';

    document.getElementById('detailModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('active');
    document.body.style.overflow = '';
}

function copyTemplateText(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check" style="color:#16a34a;"></i> Copied!';
        setTimeout(() => { btn.innerHTML = orig; }, 2000);
    });
}

function saveResponseInteraction(id) {
    const sel  = document.getElementById('modalStatusSelect');
    const note = document.getElementById('modalInteractionNote');
    if (!sel) return;

    const fd = new FormData();
    fd.append('action', 'log_response_interaction');
    fd.append('visit_id', id);
    fd.append('status', sel.value);
    fd.append('interaction_note', note ? note.value : '');

    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeDetailModal();
                fetchFullRadar(false);
            } else {
                alert('Error: ' + data.message);
            }
        });
}

function convertToLeadModal(id) {
    if (!confirm('Convert this client field visit into a pipeline CRM Lead?')) return;
    const fd = new FormData();
    fd.append('action', 'convert_to_lead');
    fd.append('visit_id', id);

    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                closeDetailModal();
                fetchFullRadar(false);
            } else {
                alert('Error: ' + data.message);
            }
        });
}

// ── Export Sheets Logic ──
function loadSalesRepsForExport() {
    fetch('sales_visits_api.php?action=get_reps')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.reps) {
                const sel = document.getElementById('exportRepSelect');
                if (sel) {
                    sel.innerHTML = '<option value="0">All Representatives (Company-wide)</option>';
                    data.reps.forEach(rep => {
                        sel.innerHTML += `<option value="${rep.id}">${escapeHtml(rep.name)} (${rep.role})</option>`;
                    });
                }
            }
        })
        .catch(() => {});
}

function openExportModal() {
    document.getElementById('exportModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeExportModal() {
    document.getElementById('exportModal').classList.remove('active');
    document.body.style.overflow = '';
}

function setExportDatePreset(preset, btn) {
    document.querySelectorAll('.preset-pill').forEach(el => el.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const startInput = document.getElementById('exportStartDate');
    const endInput   = document.getElementById('exportEndDate');
    const today      = new Date();

    function formatDate(d) {
        return d.toISOString().split('T')[0];
    }

    if (preset === 'all') {
        startInput.value = '';
        endInput.value   = '';
    } else if (preset === 'today') {
        const dStr = formatDate(today);
        startInput.value = dStr;
        endInput.value   = dStr;
    } else if (preset === 'yesterday') {
        const yest = new Date();
        yest.setDate(yest.getDate() - 1);
        const yStr = formatDate(yest);
        startInput.value = yStr;
        endInput.value   = yStr;
    } else if (preset === 'week') {
        const past = new Date();
        past.setDate(past.getDate() - 7);
        startInput.value = formatDate(past);
        endInput.value   = formatDate(today);
    } else if (preset === 'month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        startInput.value = formatDate(firstDay);
        endInput.value   = formatDate(today);
    }
}

function executeExport(e) {
    e.preventDefault();
    const repId     = document.getElementById('exportRepSelect').value;
    const startDate = document.getElementById('exportStartDate').value;
    const endDate   = document.getElementById('exportEndDate').value;
    const status    = document.getElementById('exportStatusSelect').value;
    const interest  = document.getElementById('exportInterestSelect').value;

    const params = new URLSearchParams({
        action: 'export_csv',
        rep_id: repId,
        start_date: startDate,
        end_date: endDate,
        status: status,
        interest: interest
    });

    // Trigger direct stream download
    window.location.href = `sales_visits_api.php?${params.toString()}`;
    closeExportModal();
}

// ── Web Audio Chime Synthesis (Zero Lag, Clean Bell) ──
function playNotificationChime() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        if (!audioCtx) audioCtx = new AudioContext();
        if (audioCtx.state === 'suspended') audioCtx.resume();

        const now = audioCtx.currentTime;
        const osc1 = audioCtx.createOscillator();
        const gain1 = audioCtx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(880, now);
        osc1.frequency.exponentialRampToValueAtTime(1760, now + 0.15);
        gain1.gain.setValueAtTime(0.3, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
        osc1.connect(gain1);
        gain1.connect(audioCtx.destination);
        osc1.start(now);
        osc1.stop(now + 0.45);

        const osc2 = audioCtx.createOscillator();
        const gain2 = audioCtx.createGain();
        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(1318.5, now + 0.12);
        gain2.gain.setValueAtTime(0.25, now + 0.12);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.55);
        osc2.connect(gain2);
        gain2.connect(audioCtx.destination);
        osc2.start(now + 0.12);
        osc2.stop(now + 0.55);
    } catch(e) {}
}

function testNotificationChime() {
    playNotificationChime();
}

// ── Desktop Browser Push Notifications ──
function checkNotificationPermission() {
    const btn = document.getElementById('pushNotifyBtn');
    if (!('Notification' in window)) {
        if (btn) btn.style.display = 'none';
        return;
    }
    if (Notification.permission === 'granted') {
        if (btn) {
            btn.innerHTML = '<i class="fas fa-check-circle" style="color:#16a34a;"></i> Alerts Active';
            btn.disabled = true;
        }
    }
}

function enablePushNotifications() {
    if (!('Notification' in window)) {
        alert('Browser does not support notifications.');
        return;
    }
    Notification.requestPermission().then(permission => {
        checkNotificationPermission();
        if (permission === 'granted') {
            new Notification('Sales Response Unit', {
                body: 'Desktop notifications active for incoming client visits.',
                icon: '../assets/images/icon.png'
            });
        }
    });
}

function triggerDesktopAlert(v) {
    if ('Notification' in window && Notification.permission === 'granted') {
        const isUrgent = (v.is_urgent == 1);
        const title = (isUrgent ? 'URGENT: ' : '') + 'New Client Visit - ' + v.company_name;
        const n = new Notification(title, {
            body: `Client: ${v.client_name}\nPriority: ${v.interest_level.toUpperCase()}\nAvailable: ${v.contact_availability}`,
            icon: '../assets/images/icon.png',
            tag: 'visit-' + v.id
        });
        n.onclick = () => {
            window.focus();
            openDetailModal(v.id);
        };
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeJsString(str) {
    if (!str) return '';
    return String(str)
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/"/g, '\\"')
        .replace(/\n/g, '\\n')
        .replace(/\r/g, '');
}
</script>

<?php include 'dashboard_footer.php'; ?>
