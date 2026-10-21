<?php
include 'dashboard_header.php';

// Auth Check: Super Admins, Admins, Managers, and PMs can access Response Unit
$allowed_roles = ['super_admin', 'superadmin', 'admin', 'manager', 'pm'];
if (!in_array($role, $allowed_roles)) {
    header("Location: index.php?error=unauthorized");
    exit;
}

$user_id_int = intval($_SESSION['user_id'] ?? 0);
$user_display_name = htmlspecialchars($_SESSION['name'] ?? ($_SESSION['username'] ?? 'Graphicafix Team'));
?>

<style>
/* ── Graphicafix Response Unit Modern Theme ── */
.sru-container {
    padding: 0 4px 40px 4px;
}

/* Pulsing dot for live status */
.radar-pulse-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background-color: #10b981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: radarPulse 1.8s infinite;
}
.radar-pulse-dot.urgent-active {
    background-color: #ef4444;
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
    animation: radarPulseRed 1.2s infinite;
}
@keyframes radarPulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1.1); box-shadow: 0 0 0 9px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
@keyframes radarPulseRed {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.8); }
    70% { transform: scale(1.15); box-shadow: 0 0 0 12px rgba(239, 68, 68, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

/* Highlight flash animation for newly polled incoming visits */
@keyframes flashHighlight {
    0% { background-color: rgba(184, 243, 90, 0.4); transform: translateY(-4px); }
    50% { background-color: rgba(16, 185, 129, 0.15); }
    100% { background-color: #ffffff; transform: translateY(0); }
}
.new-arrival-flash {
    animation: flashHighlight 3s ease-out;
}

/* Response Unit Top Stat Cards */
.sru-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.sru-stat-card {
    background: #ffffff;
    border-radius: 14px;
    padding: 18px 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    position: relative;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.sru-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.06);
}
.sru-stat-card.urgent-highlight {
    border-color: #fca5a5;
    background: linear-gradient(135deg, #ffffff 0%, #fff5f5 100%);
}
.sru-stat-card .stat-icon-wrap {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-bottom: 12px;
}
.sru-stat-card .stat-val {
    font-size: 1.85rem;
    font-weight: 800;
    line-height: 1.1;
    color: #0f172a;
}
.sru-stat-card .stat-lbl {
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-top: 4px;
}

/* Urgent Alert Banner */
.urgent-banner {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border: 1.5px solid #ef4444;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.12);
    animation: fadeIn 0.4s ease;
}

/* Filter Tab Pills */
.sru-filter-pill {
    padding: 7px 16px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
    border: 1.5px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}
.sru-filter-pill:hover {
    border-color: var(--primary);
    color: var(--primary);
}
.sru-filter-pill.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #ffffff;
}
.sru-filter-pill .badge-count {
    background: rgba(0,0,0,0.08);
    padding: 2px 7px;
    border-radius: 10px;
    font-size: 11px;
}
.sru-filter-pill.active .badge-count {
    background: rgba(255,255,255,0.25);
    color: #ffffff;
}

/* Item Card Styles */
.visit-item-card {
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    border-radius: 14px;
    padding: 20px 22px;
    margin-bottom: 16px;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    position: relative;
}
.visit-item-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 6px 20px rgba(0,0,0,0.05);
}
.visit-item-card.is-urgent {
    border-left: 6px solid #ef4444;
    background: linear-gradient(90deg, #fffafa 0%, #ffffff 15%);
}
.visit-item-card.is-hot {
    border-left: 6px solid #f97316;
}

/* WhatsApp 1-Click Action Button */
.btn-whatsapp-reach {
    background-color: #25D366;
    color: #ffffff !important;
    font-weight: 700;
    font-size: 13.5px;
    padding: 9px 16px;
    border-radius: 8px;
    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    box-shadow: 0 2px 8px rgba(37, 211, 102, 0.3);
    transition: all 0.2s ease;
    cursor: pointer;
}
.btn-whatsapp-reach:hover {
    background-color: #1eb956;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(37, 211, 102, 0.45);
    color: #ffffff;
}

/* Urgency Action Button */
.btn-toggle-urgency {
    padding: 7px 12px;
    border-radius: 7px;
    font-size: 12.5px;
    font-weight: 700;
    border: 1px solid #fca5a5;
    background: #fff5f5;
    color: #dc2626;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-toggle-urgency:hover {
    background: #fee2e2;
}
.btn-toggle-urgency.active-urgent {
    background: #ef4444;
    color: #ffffff;
    border-color: #ef4444;
}

/* Badges */
.badge-interest {
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.badge-interest.hot { background: #fee2e2; color: #991b1b; }
.badge-interest.warm { background: #fef3c7; color: #92400e; }
.badge-interest.cold { background: #f1f5f9; color: #475569; }

.avail-pill {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
    border-radius: 8px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.req-pill {
    background: #f8fafc;
    color: #334155;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 2px 8px;
    font-size: 11.5px;
    font-weight: 600;
}

/* Modal Overlay */
.sru-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1050;
    overflow-y: auto;
    padding: 24px 16px;
    align-items: center;
    justify-content: center;
}
.sru-modal-card {
    background: #ffffff;
    border-radius: 16px;
    width: 100%;
    max-width: 600px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.18);
    overflow: hidden;
    animation: modalPop 0.25s ease-out;
}
@keyframes modalPop {
    0% { transform: scale(0.95); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
</style>

<div class="height-100 sru-container">

    <!-- ── Header & Radar Control Bar ── -->
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="margin-bottom: 22px; gap: 14px;">
        <div>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 4px;">
                <h1 style="margin: 0; font-size: 24px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px;">
                    <span style="display:inline-flex; align-items:center; justify-content:center; width:42px; height:42px; border-radius:10px; background:linear-gradient(135deg, #024442 0%, #036663 100%); color:#b8f35a;">
                        <i class="fas fa-satellite-dish"></i>
                    </span>
                    Sales Response Unit
                </h1>
                <span id="radarStatusPill" style="display: inline-flex; align-items: center; gap: 7px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 999px;">
                    <span class="radar-pulse-dot" id="radarDot"></span>
                    <span id="radarStatusText">LIVE RADAR ACTIVE</span>
                </span>
            </div>
            <p style="margin: 0; color: #64748b; font-size: 14px;">
                Inbound client visits stream in real-time with zero latency. 1-click WhatsApp outreach, urgency triaging, and instant CRM lead conversion.
            </p>
        </div>

        <!-- Controls: Sound, Push Notifications, Refresh -->
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button type="button" id="btnToggleSound" onclick="toggleSoundAlerts()" 
                    style="padding: 9px 14px; border-radius: 8px; border: 1.5px solid #e2e8f0; background: #ffffff; color: #334155; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; transition: all 0.2s;">
                <i class="fas fa-volume-up" id="soundIcon" style="color: #10b981;"></i>
                <span id="soundText">Sound: ON</span>
            </button>

            <button type="button" id="btnPushNotif" onclick="requestPushNotificationPermission()" 
                    style="padding: 9px 14px; border-radius: 8px; border: 1.5px solid #e2e8f0; background: #ffffff; color: #334155; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; transition: all 0.2s;">
                <i class="fas fa-bell" id="pushIcon" style="color: #6366f1;"></i>
                <span id="pushText">Push Alerts</span>
            </button>

            <button type="button" onclick="manualRefresh()" 
                    style="padding: 9px 15px; border-radius: 8px; border: 1.5px solid #cbd5e1; background: #f8fafc; color: #0f172a; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 7px;">
                <i class="fas fa-sync-alt" id="refreshSpinner"></i> Refresh
            </button>

            <a href="sales_visits.php" class="btn btn-secondary-custom" style="padding: 9px 16px; font-size: 13px; text-decoration:none; display:inline-flex; align-items:center; gap:7px;">
                <i class="fas fa-street-view"></i> Field Visits Log
            </a>
        </div>
    </div>

    <!-- ── Urgent Attention Banner (Hidden by default, shown when urgent items exist) ── -->
    <div id="urgentAlertBanner" class="urgent-banner" style="display: none;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 44px; height: 44px; border-radius: 50%; background: #ef4444; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <h4 style="margin: 0; font-size: 15px; font-weight: 800; color: #991b1b;">
                    <span id="urgentBannerCount">0</span> Client(s) Require Urgent Follow-Up!
                </h4>
                <p style="margin: 2px 0 0; font-size: 13px; color: #b91c1c;">
                    Field visits flagged with high priority or immediate availability. Reach out via WhatsApp or call now to secure the deal.
                </p>
            </div>
        </div>
        <button type="button" onclick="filterByUrgentOnly()" style="background: #dc2626; color: #ffffff; border: none; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fas fa-bolt"></i> View Urgent Only
        </button>
    </div>

    <!-- ── KPI Counter Cards ── -->
    <div class="sru-stats-grid">
        <div class="sru-stat-card">
            <div class="stat-icon-wrap" style="background: #e0f2fe; color: #0284c7;">
                <i class="fas fa-satellite"></i>
            </div>
            <div class="stat-val" id="statTotalVisits">--</div>
            <div class="stat-lbl">Total Field Visits</div>
        </div>

        <div class="sru-stat-card urgent-highlight" id="urgentCardHighlight">
            <div class="stat-icon-wrap" style="background: #fee2e2; color: #ef4444;">
                <i class="fas fa-fire-extinguisher"></i>
            </div>
            <div class="stat-val" id="statUrgentVisits" style="color: #ef4444;">--</div>
            <div class="stat-lbl" style="color: #b91c1c;">Urgent Attention Needed</div>
        </div>

        <div class="sru-stat-card">
            <div class="stat-icon-wrap" style="background: #fff7ed; color: #ea580c;">
                <i class="fas fa-fire"></i>
            </div>
            <div class="stat-val" id="statHotVisits" style="color: #ea580c;">--</div>
            <div class="stat-lbl">Hot Interest Prospects</div>
        </div>

        <div class="sru-stat-card">
            <div class="stat-icon-wrap" style="background: #ecfdf5; color: #059669;">
                <i class="fas fa-hourglass-start"></i>
            </div>
            <div class="stat-val" id="statPendingVisits" style="color: #059669;">--</div>
            <div class="stat-lbl">Pending Initial Reach</div>
        </div>

        <div class="sru-stat-card">
            <div class="stat-icon-wrap" style="background: #f5f3ff; color: #7c3aed;">
                <i class="fas fa-check-double"></i>
            </div>
            <div class="stat-val" id="statContactedVisits" style="color: #7c3aed;">--</div>
            <div class="stat-lbl">Contacted / In Discussion</div>
        </div>
    </div>

    <!-- ── Filter & Search Control Bar ── -->
    <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.02);">
        <div style="display: flex; flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between;">
            
            <!-- Filter Pills -->
            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <button type="button" class="sru-filter-pill active" data-filter="all" onclick="changeFilter('all', this)">
                    All Inbound <span class="badge-count" id="badgeAll">0</span>
                </button>
                <button type="button" class="sru-filter-pill" data-filter="urgent" onclick="changeFilter('urgent', this)">
                    🚨 Urgent Needed <span class="badge-count" id="badgeUrgent" style="color:#ef4444; font-weight:700;">0</span>
                </button>
                <button type="button" class="sru-filter-pill" data-filter="hot" onclick="changeFilter('hot', this)">
                    🔥 Hot Leads <span class="badge-count" id="badgeHot">0</span>
                </button>
                <button type="button" class="sru-filter-pill" data-filter="pending" onclick="changeFilter('pending', this)">
                    ⏳ Awaiting Contact <span class="badge-count" id="badgePending">0</span>
                </button>
                <button type="button" class="sru-filter-pill" data-filter="contacted" onclick="changeFilter('contacted', this)">
                    ✅ Contacted
                </button>
            </div>

            <!-- Sort & Search Bar -->
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; flex-grow: 1; justify-content: flex-end;">
                
                <!-- Sort Dropdown -->
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="font-size: 13px; font-weight: 700; color: #64748b; white-space: nowrap;">
                        <i class="fas fa-sort-amount-down"></i> Sort:
                    </span>
                    <select id="sortSelect" onchange="applySorting()" style="padding: 8px 12px; border-radius: 8px; border: 1.5px solid #e2e8f0; font-size: 13px; font-family: inherit; outline: none; background: #f8fafc; font-weight: 600; color: #1e293b;">
                        <option value="urgency" selected>🚨 Urgency First (Recommended)</option>
                        <option value="interest">🔥 Interest Level (Hot &gt; Warm &gt; Cold)</option>
                        <option value="contact_time">⏰ Contact Availability Window</option>
                        <option value="newest">⏱️ Newest Inbound First</option>
                    </select>
                </div>

                <!-- Instant Search -->
                <div style="position: relative; width: 100%; max-width: 260px;">
                    <input type="text" id="liveSearchInput" onkeyup="handleLiveSearch()" placeholder="Search name, company, phone..." 
                           style="width: 100%; padding: 8px 12px 8px 34px; border: 1.5px solid #e2e8f0; border-radius: 8px; outline: none; font-size: 13px; font-family: inherit; background:#f8fafc;">
                    <i class="fas fa-search" style="position: absolute; left: 11px; top: 11px; color: #94a3b8; font-size: 12.5px;"></i>
                </div>

            </div>

        </div>
    </div>

    <!-- ── Live Items Container ── -->
    <div id="liveVisitsContainer">
        <!-- Spinner placeholder -->
        <div style="text-align: center; padding: 60px 20px; background: #fff; border-radius: 14px; border: 1px solid #e2e8f0;">
            <i class="fas fa-circle-notch fa-spin" style="font-size: 2.2rem; color: var(--primary); margin-bottom: 14px;"></i>
            <h4 style="font-size: 16px; font-weight: 700; color: #1e293b; margin: 0 0 6px;">Connecting to Live Inbound Stream...</h4>
            <p style="font-size: 13px; color: #64748b; margin: 0;">Listening for field visit submissions in real-time.</p>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     MODAL: QUICK STATUS & ADMIN NOTES
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="statusModal" class="sru-modal-overlay" onclick="if(event.target===this)closeStatusModal()">
    <div class="sru-modal-card">
        <div style="padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-tasks" style="color: var(--primary);"></i> Update Outreach Status
            </h3>
            <button type="button" onclick="closeStatusModal()" style="background: none; border: none; font-size: 18px; color: #94a3b8; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="padding: 20px 24px;">
            <input type="hidden" id="modalVisitId" value="0">
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">Client / Company</label>
                <div id="modalClientCompany" style="font-size: 14px; font-weight: 600; color: #0f172a; background: #f8fafc; padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    --
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">Pipeline Status</label>
                <select id="modalStatusSelect" style="width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; font-family: inherit;">
                    <option value="pending">⏳ Pending (Not yet reached)</option>
                    <option value="contacted">✅ Contacted (Spoke via WhatsApp / Call)</option>
                    <option value="in_discussion">💬 In Discussion (Requirement review underway)</option>
                    <option value="converted">🚀 Converted to CRM Lead</option>
                    <option value="closed">🔒 Closed / Archive</option>
                </select>
            </div>

            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    Management Outreach Notes <span style="font-weight: 400; color: #94a3b8;">(Optional)</span>
                </label>
                <textarea id="modalAdminNotes" rows="3" placeholder="e.g. Sent quotation on WhatsApp, client available tomorrow at 4 PM..." 
                          style="width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; font-family: inherit; resize: vertical;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeStatusModal()" class="btn btn-secondary-custom" style="padding: 9px 16px;">Cancel</button>
                <button type="button" onclick="submitStatusUpdate()" class="add-client" style="padding: 9px 20px;">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     MODAL: FULL VISIT DETAILS INSPECTION
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="detailsModal" class="sru-modal-overlay" onclick="if(event.target===this)closeDetailsModal()">
    <div class="sru-modal-card" style="max-width: 680px;">
        <div style="padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-info-circle" style="color: var(--primary);"></i> Inbound Field Visit Details
            </h3>
            <button type="button" onclick="closeDetailsModal()" style="background: none; border: none; font-size: 18px; color: #94a3b8; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="detailsModalBody" style="padding: 24px; max-height: 75vh; overflow-y: auto;">
            <!-- Populated via JS -->
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     REAL-TIME ENGINE JAVASCRIPT
     ══════════════════════════════════════════════════════════════════════════════ -->
<script>
// ── State variables ──
let lastKnownId   = 0;
let currentFilter = 'all';
let currentSort   = 'urgency';
let searchQuery   = '';
let pollingTimer  = null;
const POLL_INTERVAL = 3500; // 3.5 seconds for instant zero-lag responsiveness
let allVisitsData = [];
let soundEnabled  = (localStorage.getItem('sru_sound_enabled') !== '0'); // default enabled
let currentAdminName = <?= json_encode($user_display_name) ?>;

// ── Web Audio API Synthesizer (No external mp3 files required) ──
let audioCtx = null;
function getAudioContext() {
    if (!audioCtx) {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (AudioContextClass) {
            audioCtx = new AudioContextClass();
        }
    }
    if (audioCtx && audioCtx.state === 'suspended') {
        audioCtx.resume();
    }
    return audioCtx;
}

// Play notification sound
function playAlertChime(isUrgent) {
    if (!soundEnabled) return;
    try {
        const ctx = getAudioContext();
        if (!ctx) return;

        const now = ctx.currentTime;

        if (isUrgent) {
            // Rapid high-priority two-step warning chime
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sawtooth';
            osc1.frequency.setValueAtTime(880, now); // A5
            osc1.frequency.setValueAtTime(1175, now + 0.12); // D6
            osc1.frequency.setValueAtTime(1760, now + 0.24); // A6
            
            gain1.gain.setValueAtTime(0.35, now);
            gain1.gain.exponentialRampToValueAtTime(0.01, now + 0.55);

            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(now);
            osc1.stop(now + 0.55);

        } else {
            // Gentle melodic modern bell chime (E5 -> A5)
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(659.25, now); // E5
            osc.frequency.setValueAtTime(880, now + 0.1); // A5

            gain.gain.setValueAtTime(0.3, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.45);

            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(now);
            osc.stop(now + 0.45);
        }
    } catch (e) {
        console.warn('Audio chime error:', e);
    }
}

// Toggle Sound
function toggleSoundAlerts() {
    soundEnabled = !soundEnabled;
    localStorage.setItem('sru_sound_enabled', soundEnabled ? '1' : '0');
    updateSoundUI();
}

function updateSoundUI() {
    const icon = document.getElementById('soundIcon');
    const text = document.getElementById('soundText');
    if (soundEnabled) {
        icon.className = 'fas fa-volume-up';
        icon.style.color = '#10b981';
        text.innerText = 'Sound: ON';
    } else {
        icon.className = 'fas fa-volume-mute';
        icon.style.color = '#94a3b8';
        text.innerText = 'Sound: OFF';
    }
}

// ── Browser Desktop Push Notifications ──
function initPushNotificationUI() {
    const icon = document.getElementById('pushIcon');
    const text = document.getElementById('pushText');
    if (!("Notification" in window)) {
        text.innerText = 'Push Unsupported';
        return;
    }
    if (Notification.permission === 'granted') {
        icon.className = 'fas fa-bell';
        icon.style.color = '#10b981';
        text.innerText = 'Push: Active';
    } else if (Notification.permission === 'denied') {
        icon.className = 'fas fa-bell-slash';
        icon.style.color = '#ef4444';
        text.innerText = 'Push: Blocked';
    } else {
        icon.className = 'fas fa-bell';
        icon.style.color = '#6366f1';
        text.innerText = 'Enable Push';
    }
}

function requestPushNotificationPermission() {
    if (!("Notification" in window)) {
        alert("This browser does not support desktop notifications.");
        return;
    }
    Notification.requestPermission().then(permission => {
        initPushNotificationUI();
        if (permission === 'granted') {
            try {
                new Notification("Graphicafix Response Unit", {
                    body: "Real-time push notifications are now activated!",
                    icon: "../assets/images/favicon.png"
                });
            } catch(e) {}
        }
    });
}

function triggerDesktopNotification(item) {
    if (!("Notification" in window) || Notification.permission !== 'granted') return;
    try {
        const title = item.is_urgent == 1 
            ? `🚨 URGENT: ${item.company_name} Needs Contact!` 
            : `⚡ New Visit: ${item.company_name}`;
        const body = `${item.client_name} (${(item.interest_level || 'warm').toUpperCase()})\nAvailability: ${item.contact_availability || 'Immediate'}\nLogged by: ${item.sales_rep_name}`;

        const notif = new Notification(title, {
            body: body,
            icon: "../assets/images/favicon.png",
            tag: "visit-" + item.id,
            requireInteraction: (item.is_urgent == 1)
        });

        notif.onclick = function() {
            window.focus();
            highlightVisitCard(item.id);
            notif.close();
        };
    } catch(e) {
        console.warn('Desktop notification error:', e);
    }
}

// ── Initial Fetch & Real-Time Polling Loop ──
document.addEventListener('DOMContentLoaded', function() {
    updateSoundUI();
    initPushNotificationUI();
    fetchInitialVisits();
    startPollingLoop();

    // Check if highlight parameter in URL
    const urlParams = new URLSearchParams(window.location.search);
    const highlightId = urlParams.get('highlight');
    if (highlightId) {
        setTimeout(() => highlightVisitCard(highlightId), 800);
    }
});

// Fetch full dataset
function fetchInitialVisits() {
    const url = `sales_visits_api.php?action=fetch_all&filter=${encodeURIComponent(currentFilter)}&sort=${encodeURIComponent(currentSort)}&search=${encodeURIComponent(searchQuery)}`;
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                allVisitsData = data.items || [];
                lastKnownId = data.latest_id || (allVisitsData.length > 0 ? Math.max(...allVisitsData.map(v => parseInt(v.id))) : 0);
                updateStatsUI(data.stats);
                renderVisitsList(allVisitsData);
            }
        })
        .catch(err => {
            console.error('Error fetching visits:', err);
            document.getElementById('liveVisitsContainer').innerHTML = `
                <div style="text-align: center; padding: 40px; background: #fff; border-radius: 14px; border: 1px solid #fee2e2;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #ef4444; margin-bottom: 10px;"></i>
                    <p style="color: #991b1b; font-weight: 700; margin: 0;">Failed to connect to inbound data stream. Retrying...</p>
                </div>
            `;
        });
}

// Continuous polling loop
function startPollingLoop() {
    if (pollingTimer) clearInterval(pollingTimer);

    pollingTimer = setInterval(() => {
        pollForNewVisits();
    }, POLL_INTERVAL);
}

function pollForNewVisits() {
    const url = `sales_visits_api.php?action=poll&last_id=${lastKnownId}`;

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateStatsUI(data.stats);

                if (data.new_count > 0 && Array.isArray(data.items) && data.items.length > 0) {
                    // Update last known ID
                    if (data.latest_id > lastKnownId) {
                        lastKnownId = data.latest_id;
                    }

                    // Sound alert
                    playAlertChime(data.has_urgent);

                    // Add to local dataset
                    data.items.forEach(newItem => {
                        // Desktop notification
                        triggerDesktopNotification(newItem);

                        // Avoid duplicate insertion
                        if (!allVisitsData.some(v => parseInt(v.id) === parseInt(newItem.id))) {
                            allVisitsData.unshift(newItem);
                            prependVisitCard(newItem);
                        }
                    });

                    // Trigger toast notification
                    showRealtimeToast(data.items[0], data.new_count, data.has_urgent);
                }
            }
        })
        .catch(err => {
            console.warn('Polling check error:', err);
        });
}

// Update Top KPI Counters and Urgent Banner
function updateStatsUI(stats) {
    if (!stats) return;

    document.getElementById('statTotalVisits').innerText     = stats.total || 0;
    document.getElementById('statUrgentVisits').innerText    = stats.urgent || 0;
    document.getElementById('statHotVisits').innerText       = stats.hot || 0;
    document.getElementById('statPendingVisits').innerText   = stats.pending || 0;
    document.getElementById('statContactedVisits').innerText = stats.contacted || 0;

    // Filter pill counts
    document.getElementById('badgeAll').innerText     = stats.total || 0;
    document.getElementById('badgeUrgent').innerText  = stats.urgent || 0;
    document.getElementById('badgeHot').innerText     = stats.hot || 0;
    document.getElementById('badgePending').innerText = stats.pending || 0;

    // Urgent banner & radar dot state
    const banner = document.getElementById('urgentAlertBanner');
    const radarDot = document.getElementById('radarDot');
    const urgentCount = parseInt(stats.urgent || 0);

    if (urgentCount > 0) {
        banner.style.display = 'flex';
        document.getElementById('urgentBannerCount').innerText = urgentCount;
        radarDot.className = 'radar-pulse-dot urgent-active';
        document.getElementById('radarStatusText').innerText = 'URGENT OUTREACH ACTIVE';
        document.getElementById('radarStatusPill').style.background = '#fef2f2';
        document.getElementById('radarStatusPill').style.borderColor = '#fca5a5';
        document.getElementById('radarStatusPill').style.color = '#dc2626';
    } else {
        banner.style.display = 'none';
        radarDot.className = 'radar-pulse-dot';
        document.getElementById('radarStatusText').innerText = 'LIVE RADAR ACTIVE';
        document.getElementById('radarStatusPill').style.background = '#ecfdf5';
        document.getElementById('radarStatusPill').style.borderColor = '#a7f3d0';
        document.getElementById('radarStatusPill').style.color = '#065f46';
    }
}

// ── Render List of Visits ──
function renderVisitsList(items) {
    const container = document.getElementById('liveVisitsContainer');
    if (!items || items.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 60px 20px; background: #fff; border-radius: 14px; border: 1.5px dashed #cbd5e1;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 14px;">
                    <i class="fas fa-inbox"></i>
                </div>
                <h4 style="font-size: 16px; font-weight: 700; color: #1e293b; margin: 0 0 6px;">No Inbound Field Visits Found</h4>
                <p style="font-size: 13.5px; color: #64748b; margin: 0;">Visits logged by sales representatives will instantly stream here.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = items.map(v => buildVisitCardHTML(v)).join('');
}

// Prepend newly arrived card with animation
function prependVisitCard(item) {
    const container = document.getElementById('liveVisitsContainer');
    
    // Check if empty message is present
    if (container.querySelector('.fa-inbox') || container.querySelector('.fa-circle-notch')) {
        container.innerHTML = '';
    }

    const html = buildVisitCardHTML(item, true);
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;
    const newCard = tempDiv.firstElementChild;

    container.prepend(newCard);
}

// Build Card HTML
function buildVisitCardHTML(v, isNew = false) {
    const isUrgent = parseInt(v.is_urgent) === 1;
    const isHot    = v.interest_level === 'hot';
    const isWarm   = v.interest_level === 'warm';
    const isCold   = v.interest_level === 'cold';

    const interestClass = isHot ? 'hot' : (isCold ? 'cold' : 'warm');
    const interestIcon  = isHot ? '<i class="fas fa-fire"></i>' : (isCold ? '<i class="fas fa-snowflake"></i>' : '<i class="fas fa-temperature-high"></i>');

    // Requirements Tags
    const cats = Array.isArray(v.categories_arr) ? v.categories_arr : [];
    const subcats = Array.isArray(v.subcategories_arr) ? v.subcategories_arr : [];
    const allTags = [...cats, ...subcats];
    const tagsHTML = allTags.length > 0 
        ? allTags.map(t => `<span class="req-pill">${escapeHtml(t)}</span>`).join(' ') 
        : '<span style="font-size:12px; color:#94a3b8; font-style:italic;">General Consultation</span>';

    // WhatsApp clean phone
    const cleanPhone = (v.whatsapp || v.phone || '').replace(/[^0-9]/g, '');
    const clientName = escapeHtml(v.client_name);
    const compName   = escapeHtml(v.company_name);
    const repName    = escapeHtml(v.sales_rep_name);
    const servicesText = cats.length > 0 ? cats.join(' & ') : 'our services';

    // WhatsApp prefilled message
    const waText = encodeURIComponent(
        `Hello ${v.client_name}, this is ${currentAdminName} from Graphicafix Management. Following up on your discussion with our sales executive ${v.sales_rep_name} regarding ${servicesText}. Are you available for a quick discussion?`
    );
    const waLink = `https://wa.me/${cleanPhone}?text=${waText}`;

    // Pipeline status badge
    const statusBadges = {
        'pending': '<span style="background:#ecfdf5; color:#065f46; font-size:11.5px; font-weight:700; padding:3px 10px; border-radius:999px;">⏳ Pending Reach-out</span>',
        'contacted': '<span style="background:#e0f2fe; color:#0369a1; font-size:11.5px; font-weight:700; padding:3px 10px; border-radius:999px;">✅ Contacted</span>',
        'in_discussion': '<span style="background:#fef3c7; color:#92400e; font-size:11.5px; font-weight:700; padding:3px 10px; border-radius:999px;">💬 In Discussion</span>',
        'converted': '<span style="background:#f3e8ff; color:#6b21a8; font-size:11.5px; font-weight:700; padding:3px 10px; border-radius:999px;">🚀 Converted to CRM</span>',
        'closed': '<span style="background:#f1f5f9; color:#64748b; font-size:11.5px; font-weight:700; padding:3px 10px; border-radius:999px;">🔒 Closed</span>'
    };
    const statusBadgeHTML = statusBadges[v.status] || statusBadges['pending'];

    // Converted Lead Link
    let convertedLeadHTML = '';
    if (v.converted_lead_id) {
        convertedLeadHTML = `
            <a href="manage_leads.php?search=${encodeURIComponent(v.client_name)}" target="_blank" 
               style="font-size:11.5px; font-weight:700; color:#7c3aed; text-decoration:none; display:inline-flex; align-items:center; gap:4px; background:#f5f3ff; border:1px solid #ddd6fe; padding:2px 8px; border-radius:6px;">
                <i class="fas fa-external-link-alt"></i> CRM Lead #${v.converted_lead_id}
            </a>
        `;
    }

    return `
    <div class="visit-item-card ${isUrgent ? 'is-urgent' : (isHot ? 'is-hot' : '')} ${isNew ? 'new-arrival-flash' : ''}" id="visitCard_${v.id}">
        
        <!-- Header: Client & Company + Status & Urgency -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #0f172a;">
                        ${compName}
                    </h3>
                    <span style="color: #64748b; font-size: 13.5px; font-weight: 600;">(${clientName})</span>
                    
                    <!-- Interest Level Badge -->
                    <span class="badge-interest ${interestClass}">
                        ${interestIcon} ${v.interest_level.toUpperCase()}
                    </span>

                    <!-- Urgent Flag Badge -->
                    ${isUrgent ? `
                        <span style="background:#ef4444; color:#fff; font-size:11px; font-weight:800; padding:3px 10px; border-radius:999px; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:5px; animation:radarPulseRed 1.5s infinite;">
                            🚨 URGENT CONTACT NEEDED
                        </span>
                    ` : ''}
                </div>

                <div style="margin-top: 4px; font-size: 12.5px; color: #64748b; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                    <span><i class="fas fa-user-circle" style="color:#024442;"></i> Rep: <strong style="color:#1e293b;">${repName}</strong></span>
                    <span><i class="far fa-clock"></i> ${v.time_ago || 'Recently'}</span>
                    ${statusBadgeHTML}
                    ${convertedLeadHTML}
                </div>
            </div>

            <!-- Outreach CTA Buttons -->
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                
                <!-- 1-Click WhatsApp Reach Out -->
                <a href="${waLink}" target="_blank" onclick="handleWhatsAppClick(${v.id}, '${escapeHtml(v.client_name)}')" class="btn-whatsapp-reach" title="Click to open prefilled WhatsApp chat">
                    <i class="fab fa-whatsapp" style="font-size: 1.15rem;"></i> WhatsApp Reach Out
                </a>

                <!-- Phone Direct Call -->
                <a href="tel:${v.phone}" class="btn btn-secondary-custom" style="padding: 8px 12px; font-size: 13px; text-decoration: none;" title="Direct Call">
                    <i class="fas fa-phone-alt" style="color: #0284c7;"></i>
                </a>

                <!-- Urgency Toggle -->
                <button type="button" class="btn-toggle-urgency ${isUrgent ? 'active-urgent' : ''}" onclick="toggleUrgency(${v.id})" title="${isUrgent ? 'Remove urgent priority' : 'Mark as urgent needed'}">
                    <i class="fas fa-exclamation-triangle"></i> ${isUrgent ? 'Urgent' : 'Mark Urgent'}
                </button>

                <!-- Status & Notes Trigger -->
                <button type="button" class="btn btn-secondary-custom" onclick="openStatusModal(${v.id}, '${escapeHtml(v.client_name)}', '${escapeHtml(v.company_name)}', '${v.status}')" style="padding: 8px 12px; font-size: 13px;" title="Update Status">
                    <i class="fas fa-edit"></i> Status
                </button>

                <!-- Full Details -->
                <button type="button" class="btn btn-secondary-custom" onclick="openDetailsModal(${v.id})" style="padding: 8px 12px; font-size: 13px;" title="View Details">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <!-- Middle Row: Availability Window + Requirements Tags -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; background: #f8fafc; border: 1px solid #f1f5f9; padding: 10px 14px; border-radius: 10px; margin-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <span style="font-size: 12px; font-weight: 700; color: #475569;">
                    <i class="far fa-calendar-check" style="color: #059669;"></i> Available For Contact:
                </span>
                <span class="avail-pill">
                    <i class="far fa-clock"></i> ${escapeHtml(v.contact_availability || 'Flexible')}
                </span>
            </div>

            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                <span style="font-size: 12px; font-weight: 700; color: #475569;">Requirements:</span>
                ${tagsHTML}
            </div>
        </div>

        <!-- Bottom Row: Remarks & Conversion CTA -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; font-size: 13px;">
            <div style="color: #334155; flex-grow: 1;">
                ${v.quick_remark ? `
                    <span style="background: #e2e8f0; color: #1e293b; padding: 2px 8px; border-radius: 6px; font-size: 11.5px; font-weight: 700; margin-right: 6px;">
                        📌 ${escapeHtml(v.quick_remark)}
                    </span>
                ` : ''}
                <span style="color: #475569; font-style: ${v.remarks ? 'normal' : 'italic'};">
                    ${v.remarks ? escapeHtml(v.remarks) : 'No additional remarks provided.'}
                </span>
            </div>

            <!-- Quick Action: Convert to Lead if not yet converted -->
            <div>
                ${!v.converted_lead_id ? `
                    <button type="button" onclick="convertToCRMLead(${v.id}, '${escapeHtml(v.company_name)}')" 
                            style="background: #f5f3ff; color: #6b21a8; border: 1px solid #ddd6fe; border-radius: 8px; padding: 6px 13px; font-size: 12px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;">
                        <i class="fas fa-user-plus"></i> Convert to CRM Lead
                    </button>
                ` : ''}
            </div>
        </div>

    </div>
    `;
}

// ── 1-Click WhatsApp Handler ──
function handleWhatsAppClick(visitId, clientName) {
    // When WhatsApp outreach is initiated, automatically ask if they want to mark as Contacted
    setTimeout(() => {
        if (confirm(`WhatsApp chat launched with ${clientName}.\nWould you like to mark this visit status as "Contacted"?`)) {
            quickUpdateStatus(visitId, 'contacted');
        }
    }, 1200);
}

// ── Urgency Toggle ──
function toggleUrgency(visitId) {
    const card = document.getElementById(`visitCard_${visitId}`);
    if (card) card.style.opacity = '0.6';

    fetch(`sales_visits_api.php?action=toggle_urgent&visit_id=${visitId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Update item in local array
                const item = allVisitsData.find(v => parseInt(v.id) === parseInt(visitId));
                if (item) {
                    item.is_urgent = data.is_urgent;
                }
                // Refresh list with current sort/filter
                applySorting();
                // Refresh top stats
                pollForNewVisits();
            } else {
                alert(data.message || 'Failed to toggle urgency.');
                if (card) card.style.opacity = '1';
            }
        })
        .catch(err => {
            console.error('Error toggling urgency:', err);
            if (card) card.style.opacity = '1';
        });
}

// ── Convert to CRM Lead ──
function convertToCRMLead(visitId, compName) {
    if (!confirm(`Convert visit from "${compName}" into an active CRM Lead?`)) return;

    const fd = new FormData();
    fd.append('action', 'convert_to_lead');
    fd.append('visit_id', visitId);

    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                const item = allVisitsData.find(v => parseInt(v.id) === parseInt(visitId));
                if (item) {
                    item.status = 'converted';
                    item.converted_lead_id = data.lead_id;
                }
                applySorting();
            } else {
                alert(data.message || 'Failed to convert.');
            }
        })
        .catch(err => console.error(err));
}

// ── Status Modal Logic ──
function openStatusModal(visitId, clientName, companyName, currentStatus) {
    document.getElementById('modalVisitId').value = visitId;
    document.getElementById('modalClientCompany').innerText = `${companyName} (${clientName})`;
    document.getElementById('modalStatusSelect').value = currentStatus || 'pending';
    document.getElementById('modalAdminNotes').value = '';

    const modal = document.getElementById('statusModal');
    modal.style.display = 'flex';
}

function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}

function submitStatusUpdate() {
    const visitId = document.getElementById('modalVisitId').value;
    const status  = document.getElementById('modalStatusSelect').value;
    const notes   = document.getElementById('modalAdminNotes').value;

    quickUpdateStatus(visitId, status, notes);
    closeStatusModal();
}

function quickUpdateStatus(visitId, status, notes = '') {
    const fd = new FormData();
    fd.append('action', 'update_status');
    fd.append('visit_id', visitId);
    fd.append('status', status);
    if (notes) fd.append('admin_notes', notes);

    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const item = allVisitsData.find(v => parseInt(v.id) === parseInt(visitId));
                if (item) {
                    item.status = status;
                }
                applySorting();
            } else {
                alert(data.message || 'Failed to update status');
            }
        })
        .catch(err => console.error(err));
}

// ── Full Details Modal ──
function openDetailsModal(visitId) {
    const item = allVisitsData.find(v => parseInt(v.id) === parseInt(visitId));
    if (!item) return;

    const cats = Array.isArray(item.categories_arr) ? item.categories_arr : [];
    const subcats = Array.isArray(item.subcategories_arr) ? item.subcategories_arr : [];

    const body = document.getElementById('detailsModalBody');
    body.innerHTML = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 18px;">
            <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;">
                <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Company</span>
                <div style="font-size:15px; font-weight:800; color:#0f172a; margin-top:2px;">${escapeHtml(item.company_name)}</div>
            </div>
            <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;">
                <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Contact Person</span>
                <div style="font-size:15px; font-weight:700; color:#0f172a; margin-top:2px;">${escapeHtml(item.client_name)}</div>
            </div>
            <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;">
                <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Phone Number</span>
                <div style="font-size:14px; font-weight:700; color:#0284c7; margin-top:2px;"><a href="tel:${item.phone}" style="text-decoration:none; color:inherit;">${item.phone}</a></div>
            </div>
            <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;">
                <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">WhatsApp Number</span>
                <div style="font-size:14px; font-weight:700; color:#059669; margin-top:2px;">${item.whatsapp || item.phone}</div>
            </div>
        </div>

        <div style="margin-bottom: 16px;">
            <span style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:6px;">Contact Availability Window:</span>
            <div style="background:#ecfdf5; border:1px solid #a7f3d0; padding:10px 14px; border-radius:8px; font-weight:600; color:#065f46;">
                <i class="far fa-clock"></i> ${escapeHtml(item.contact_availability || 'Not specified')}
            </div>
        </div>

        <div style="margin-bottom: 16px;">
            <span style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:6px;">Requirements & Categories:</span>
            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                ${cats.map(c => `<span style="background:var(--primary); color:#fff; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600;">${escapeHtml(c)}</span>`).join('')}
                ${subcats.map(s => `<span style="background:#e0f2fe; color:#0369a1; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600;">${escapeHtml(s)}</span>`).join('')}
            </div>
        </div>

        <div style="margin-bottom: 16px;">
            <span style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:6px;">Field Remarks & Notes:</span>
            <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px 14px; border-radius:8px; font-size:13.5px; color:#334155;">
                ${item.quick_remark ? `<div style="font-weight:700; color:#024442; margin-bottom:4px;">Preset: ${escapeHtml(item.quick_remark)}</div>` : ''}
                ${item.remarks ? escapeHtml(item.remarks) : '<span style="color:#94a3b8; font-style:italic;">No custom remarks provided.</span>'}
            </div>
        </div>

        <div style="border-top:1px solid #f1f5f9; padding-top:14px; font-size:12px; color:#64748b; display:flex; justify-content:space-between; align-items:center;">
            <span>Logged By: <strong>${escapeHtml(item.sales_rep_name)}</strong> on ${item.created_at}</span>
            <span>Visit ID: #${item.id}</span>
        </div>
    `;

    document.getElementById('detailsModal').style.display = 'flex';
}

function closeDetailsModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

// ── Filter & Sort Handlers ──
function changeFilter(filter, el) {
    currentFilter = filter;
    document.querySelectorAll('.sru-filter-pill').forEach(btn => btn.classList.remove('active'));
    if (el) el.classList.add('active');
    applySorting();
}

function filterByUrgentOnly() {
    const urgentPill = document.querySelector('[data-filter="urgent"]');
    changeFilter('urgent', urgentPill);
}

function applySorting() {
    currentSort = document.getElementById('sortSelect').value;
    
    // Filter dataset
    let filtered = [...allVisitsData];

    if (currentFilter === 'urgent') {
        filtered = filtered.filter(v => parseInt(v.is_urgent) === 1 && v.status !== 'closed');
    } else if (currentFilter === 'hot') {
        filtered = filtered.filter(v => v.interest_level === 'hot' && v.status !== 'closed');
    } else if (currentFilter === 'pending') {
        filtered = filtered.filter(v => v.status === 'pending');
    } else if (currentFilter === 'contacted') {
        filtered = filtered.filter(v => v.status === 'contacted' || v.status === 'in_discussion');
    }

    // Search query
    if (searchQuery.trim() !== '') {
        const q = searchQuery.toLowerCase();
        filtered = filtered.filter(v => 
            (v.client_name || '').toLowerCase().includes(q) ||
            (v.company_name || '').toLowerCase().includes(q) ||
            (v.phone || '').includes(q) ||
            (v.sales_rep_name || '').toLowerCase().includes(q) ||
            (v.remarks || '').toLowerCase().includes(q)
        );
    }

    // Sort dataset
    filtered.sort((a, b) => {
        if (currentSort === 'urgency') {
            const uA = parseInt(a.is_urgent) || 0;
            const uB = parseInt(b.is_urgent) || 0;
            if (uA !== uB) return uB - uA; // urgent first

            const score = { 'hot': 3, 'warm': 2, 'cold': 1 };
            const iA = score[a.interest_level] || 0;
            const iB = score[b.interest_level] || 0;
            if (iA !== iB) return iB - iA;

            return parseInt(b.id) - parseInt(a.id);

        } else if (currentSort === 'interest') {
            const score = { 'hot': 3, 'warm': 2, 'cold': 1 };
            const iA = score[a.interest_level] || 0;
            const iB = score[b.interest_level] || 0;
            if (iA !== iB) return iB - iA;
            return parseInt(b.id) - parseInt(a.id);

        } else if (currentSort === 'contact_time') {
            // Sort by available_at datetime if present
            if (a.available_at && b.available_at) {
                return new Date(a.available_at) - new Date(b.available_at);
            }
            return parseInt(b.id) - parseInt(a.id);

        } else {
            // newest
            return parseInt(b.id) - parseInt(a.id);
        }
    });

    renderVisitsList(filtered);
}

function handleLiveSearch() {
    searchQuery = document.getElementById('liveSearchInput').value;
    applySorting();
}

function manualRefresh() {
    const spinner = document.getElementById('refreshSpinner');
    spinner.classList.add('fa-spin');
    fetchInitialVisits();
    setTimeout(() => spinner.classList.remove('fa-spin'), 600);
}

// ── Highlight card helper ──
function highlightVisitCard(id) {
    const card = document.getElementById(`visitCard_${id}`);
    if (card) {
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        card.classList.add('new-arrival-flash');
        setTimeout(() => card.classList.remove('new-arrival-flash'), 4000);
    }
}

// ── Realtime Toast Notification ──
function showRealtimeToast(item, count, hasUrgent) {
    const existing = document.getElementById('sruRealtimeToast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'sruRealtimeToast';
    toast.style.cssText = `
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: ${hasUrgent ? '#ef4444' : '#024442'};
        color: #ffffff;
        padding: 14px 20px;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        z-index: 9999;
        display: flex;
        align-items: center;
        gap: 14px;
        animation: toastSlideUp 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        max-width: 380px;
    `;

    toast.innerHTML = `
        <div style="font-size: 1.4rem;">
            ${hasUrgent ? '🚨' : '⚡'}
        </div>
        <div style="flex-grow: 1;">
            <div style="font-weight: 800; font-size: 14px;">
                ${hasUrgent ? 'URGENT Inbound Received!' : 'New Field Visit Logged!'}
            </div>
            <div style="font-size: 12.5px; opacity: 0.9; margin-top: 2px;">
                ${escapeHtml(item.company_name)} (${escapeHtml(item.client_name)})
            </div>
        </div>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; color: #fff; font-size: 16px; cursor: pointer; padding: 0 4px;">
            &times;
        </button>
    `;

    document.body.appendChild(toast);
    setTimeout(() => {
        if (toast.parentElement) toast.remove();
    }, 5000);
}

// Simple HTML escaping helper
function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>

<style>
@keyframes toastSlideUp {
    0% { transform: translateY(40px); opacity: 0; }
    100% { transform: translateY(0); opacity: 1; }
}
</style>

<?php include 'dashboard_footer.php'; ?>
