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
                <div class="stat-icon" style="background:var(--light);color:var(--primary);"><i class="fas fa-satellite-dish"></i></div>
            </div>
            <div class="stat-number" id="statTotal">--</div>
        </div>
        <div class="stat-card" style="border-left: 3px solid #ef4444;">
            <div class="stat-header">
                <span class="stat-label">🚨 Urgently Need Contact</span>
                <div class="stat-icon" style="background:#fee2e2;color:#ef4444;"><i class="fas fa-bell"></i></div>
            </div>
            <div class="stat-number" id="statUrgent" style="color:#ef4444;">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">🔥 Hot Priority Leads</span>
                <div class="stat-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-fire"></i></div>
            </div>
            <div class="stat-number" id="statHot" style="color:#d97706;">--</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">✅ Contacted / Resolved</span>
                <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-check-double"></i></div>
            </div>
            <div class="stat-number" id="statContacted" style="color:#16a34a;">--</div>
        </div>
    </div>

    <!-- ── Universal Main Card & Radar Table ── -->
    <div class="main-card">
        <div class="main-header">
            <h3>
                <i class="fas fa-satellite-dish"></i> Sales Response Radar
                <span id="radarPulseBadge" style="font-size:11.5px; font-weight:700; color:#16a34a; background:#dcfce7; padding:3px 10px; border-radius:12px; margin-left:8px; display:inline-flex; align-items:center; gap:6px;">
                    <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#16a34a;"></span>
                    Live Polling (3.5s)
                </span>
            </h3>
            <div style="display:flex; gap:10px; align-items:center;">
                <button type="button" onclick="enablePushNotifications()" id="pushNotifyBtn" class="btn btn-secondary-custom" style="padding:8px 14px; font-size:13px;">
                    <i class="fas fa-bell"></i> Enable Push Alerts
                </button>
                <button type="button" onclick="testNotificationChime()" class="btn btn-secondary-custom" style="padding:8px 14px; font-size:13px;" title="Test Audio Sound">
                    <i class="fas fa-volume-up"></i> Test Chime
                </button>
            </div>
        </div>

        <!-- Filter & Sorting Toolbar -->
        <div style="padding:14px 24px; border-bottom:1px solid #f1f5f9; background:#fafafa; display:flex; flex-wrap:wrap; gap:14px; align-items:center; justify-content:space-between;">
            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                
                <!-- Filter Dropdown -->
                <div style="display:flex; align-items:center; gap:6px;">
                    <span style="font-size:13px; font-weight:600; color:#64748b;">Filter:</span>
                    <select id="filterSelect" onchange="setFilter(this.value)" class="form-select" style="width:auto; padding:6px 12px; font-size:13px;">
                        <option value="all">All Records</option>
                        <option value="urgent">🚨 Urgent Need to Contact</option>
                        <option value="pending" selected>⏳ Pending Contact Only</option>
                        <option value="hot">🔥 Hot Deals</option>
                        <option value="warm">⚡ Warm Deals</option>
                        <option value="contacted">✅ Contacted</option>
                    </select>
                </div>

                <!-- Sort By Dropdown -->
                <div style="display:flex; align-items:center; gap:6px;">
                    <span style="font-size:13px; font-weight:600; color:#64748b;">Sort By:</span>
                    <select id="sortSelect" onchange="setSort(this.value)" class="form-select" style="width:auto; padding:6px 12px; font-size:13px;">
                        <option value="urgency">🚨 Urgency First</option>
                        <option value="interest">🔥 Interest Level (Hot &rarr; Cold)</option>
                        <option value="contact_time">⏰ Contact Availability</option>
                        <option value="newest">🕒 Newest First</option>
                    </select>
                </div>

            </div>

            <!-- Search Input -->
            <div style="position:relative; width:100%; max-width:300px;">
                <input type="text" id="searchInput" oninput="handleSearch(this.value)" placeholder="Quick search radar..."
                       class="form-control" style="padding-left:34px; font-size:13px; height:36px;">
                <i class="fas fa-search" style="position:absolute; left:12px; top:11px; color:#94a3b8; font-size:13px;"></i>
            </div>
        </div>

        <!-- Table Container -->
        <div class="main-table">
            <div class="table-head" style="grid-template-columns: 2fr 1.6fr 1.1fr 1.5fr 1.5fr 1fr 1.2fr;">
                <span>Client & Company</span>
                <span>1-Click Reach Out</span>
                <span>Interest</span>
                <span>Available Window</span>
                <span>Requirements</span>
                <span>Rep & Time</span>
                <span style="text-align:right;">Actions</span>
            </div>

            <div id="radarTableBody">
                <div style="text-align:center; padding:50px 20px; color:#888;">
                    <i class="fas fa-spinner fa-spin" style="font-size:24px; margin-bottom:10px; color:var(--primary);"></i>
                    <p style="margin:0;">Connecting to Live Response Radar...</p>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     UNIVERSAL MODAL: VIEW & UPDATE STATUS
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="detailModal" class="modal-overlay" onclick="if(event.target===this)closeDetailModal()">
    <div class="modal-content" style="max-width:620px;">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon"><i class="fas fa-satellite-dish"></i></div>
                <span id="detailModalTitle">Response Unit Details</span>
            </h4>
            <button class="modal-close" onclick="closeDetailModal()">×</button>
        </div>

        <div class="modal-body" id="detailModalBody" style="padding:22px;">
            <!-- Populated dynamically -->
        </div>

        <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
            <div id="detailWaWrap"></div>
            <button type="button" class="btn btn-secondary-custom" onclick="closeDetailModal()">Close</button>
        </div>
    </div>
</div>

<style>
/* Standard Status Badge Helpers */
.badge-hot  { background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 12px; font-size: 11.5px; font-weight: 700; }
.badge-warm { background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 12px; font-size: 11.5px; font-weight: 700; }
.badge-cold { background: #f1f5f9; color: #475569; padding: 3px 8px; border-radius: 12px; font-size: 11.5px; font-weight: 700; }
.badge-urg  { background: #ef4444; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 800; margin-left: 4px; }
.btn-wa     { background: #16a34a; color: #fff !important; font-weight: 700; font-size: 12.5px; padding: 6px 12px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; border: 1px solid #15803d; transition: all 0.15s ease; }
.btn-wa:hover { background: #15803d; color: #fff !important; }
</style>

<script>
let lastKnownId   = 0;
let pollingTimer  = null;
let radarItems    = [];
let currentFilter = 'pending';
let currentSort   = 'urgency';
let searchQuery   = '';
let audioCtx      = null;

document.addEventListener('DOMContentLoaded', () => {
    checkNotificationPermission();
    fetchFullRadar();
    startShortPolling();
});

// ── Polling Engine (3.5s zero-lag) ──
function startShortPolling() {
    if (pollingTimer) clearInterval(pollingTimer);
    pollingTimer = setInterval(() => {
        pollLatestVisits();
    }, 3500);
}

function pollLatestVisits() {
    fetch(`sales_visits_api.php?action=poll_latest&since_id=${lastKnownId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.has_new && data.new_records && data.new_records.length > 0) {
                // Play pleasant notification audio chime
                playNotificationChime();

                // Desktop Notification for new entries
                data.new_records.forEach(v => {
                    triggerDesktopAlert(v);
                });

                // Update lastKnownId
                data.new_records.forEach(v => {
                    if (parseInt(v.id) > lastKnownId) lastKnownId = parseInt(v.id);
                });

                // Refresh table seamlessly
                fetchFullRadar(false);
            }
        })
        .catch(e => console.warn('Radar poll error:', e));
}

function fetchFullRadar(showLoading = true) {
    if (showLoading && radarItems.length === 0) {
        document.getElementById('radarTableBody').innerHTML = `
            <div style="text-align:center; padding:50px 20px; color:#888;">
                <i class="fas fa-spinner fa-spin" style="font-size:24px; margin-bottom:10px; color:var(--primary);"></i>
                <p style="margin:0;">Scanning live radar...</p>
            </div>
        `;
    }

    fetch(`sales_visits_api.php?action=fetch_all&filter=${currentFilter}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                radarItems = data.items || [];
                
                // Track max ID
                radarItems.forEach(v => {
                    if (parseInt(v.id) > lastKnownId) lastKnownId = parseInt(v.id);
                });

                renderStats(data.stats || {});
                renderRadarTable();
            }
        })
        .catch(err => {
            console.error(err);
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

    // Search filter
    if (searchQuery) {
        items = items.filter(v =>
            (v.client_name && v.client_name.toLowerCase().includes(searchQuery)) ||
            (v.company_name && v.company_name.toLowerCase().includes(searchQuery)) ||
            (v.phone && v.phone.includes(searchQuery)) ||
            (v.categories && v.categories.toLowerCase().includes(searchQuery)) ||
            (v.remarks && v.remarks.toLowerCase().includes(searchQuery))
        );
    }

    // Sort order
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
            <div style="text-align:center; padding:60px 24px; color:#888;">
                <i class="fas fa-check-circle" style="font-size:32px; color:#cbd5e1; margin-bottom:12px;"></i>
                <p style="margin:0; font-size:14px;">No visits matching this filter.</p>
                <small style="color:#94a3b8;">Radar is continuously listening for new submissions...</small>
            </div>
        `;
        return;
    }

    let html = '';
    items.forEach(v => {
        const isUrgent = (v.is_urgent == 1);
        const cleanWa  = (v.whatsapp || v.phone || '').replace(/[^0-9]/g, '');
        const waMsg    = encodeURIComponent(`Hello ${v.client_name}, this is Graphicafix management reaching out following your visit with our team. When is a convenient time for a quick 5-minute call?`);

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
            catBadges = v.categories_arr.slice(0, 2).map(c => `<span style="background:#e0f2fe; color:#0284c7; padding:2px 7px; border-radius:6px; font-size:11px; font-weight:600; margin-right:4px;">${escapeHtml(c)}</span>`).join('');
            if (v.categories_arr.length > 2) {
                catBadges += `<span style="color:#64748b; font-size:11px;">+${v.categories_arr.length - 2}</span>`;
            }
        } else {
            catBadges = '<span style="color:#94a3b8; font-size:12px;">General</span>';
        }

        html += `
            <div class="table-row" style="grid-template-columns: 2fr 1.6fr 1.1fr 1.5fr 1.5fr 1fr 1.2fr; ${isUrgent ? 'background:#fffbfb; border-left:3px solid #ef4444;' : ''}">
                <div>
                    <strong style="color:#0f172a; font-size:13.5px; display:inline-flex; align-items:center;">
                        ${escapeHtml(v.company_name)}
                        ${isUrgent ? '<span class="badge-urg">URGENT</span>' : ''}
                    </strong>
                    <div style="font-size:12px; color:#64748b;">${escapeHtml(v.client_name)}</div>
                </div>
                <div>
                    ${cleanWa ? `
                        <a href="https://wa.me/${cleanWa}?text=${waMsg}" target="_blank" class="btn-wa" title="1-Click WhatsApp Reach Out">
                            <i class="fab fa-whatsapp"></i> WhatsApp Reach Out
                        </a>
                    ` : `
                        <span style="font-size:12.5px; font-weight:600; color:#334155;">${escapeHtml(v.phone)}</span>
                    `}
                </div>
                <div>${interestBadge}</div>
                <div style="font-size:12.5px; color:#334155;">
                    <i class="far fa-clock" style="color:#64748b; margin-right:4px;"></i>
                    <strong>${escapeHtml(v.contact_availability)}</strong>
                </div>
                <div>${catBadges}</div>
                <div style="font-size:12px; color:#64748b;">
                    <div>${escapeHtml(v.sales_rep_name)}</div>
                    <small style="color:#94a3b8;">${v.time_ago || ''}</small>
                </div>
                <div style="display:flex; gap:6px; justify-content:flex-end; align-items:center;">
                    <button class="dots" onclick="toggleUrgent(${v.id})" title="${isUrgent ? 'Mark as Not Urgent' : 'Mark as URGENT'}" style="${isUrgent ? 'color:#ef4444; border-color:#fca5a5; background:#fff5f5;' : ''}">
                        <i class="fas fa-exclamation-triangle"></i>
                    </button>
                    <button class="dots" onclick="openDetailModal(${v.id})" title="View Details / Update Status"><i class="fas fa-eye"></i></button>
                    ${v.status !== 'contacted' ? `
                        <button class="dots" onclick="markContacted(${v.id})" title="Mark Contacted" style="color:#16a34a;"><i class="fas fa-check"></i></button>
                    ` : ''}
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

// ── Urgent Toggle ──
function toggleUrgent(id) {
    const fd = new FormData();
    fd.append('action', 'toggle_urgent');
    fd.append('id', id);
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

function markContacted(id) {
    const fd = new FormData();
    fd.append('action', 'update_status');
    fd.append('id', id);
    fd.append('status', 'contacted');
    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                fetchFullRadar(false);
            }
        });
}

// ── Detail Modal ──
function openDetailModal(id) {
    const v = radarItems.find(item => item.id == id);
    if (!v) return;

    const cleanWa  = (v.whatsapp || v.phone || '').replace(/[^0-9]/g, '');
    const waMsg    = encodeURIComponent(`Hello ${v.client_name}, this is Graphicafix management following up on your discussion regarding ${v.company_name}.`);

    let cats = (v.categories_arr && v.categories_arr.length) ? v.categories_arr.join(', ') : 'None';
    let subs = (v.subcategories_arr && v.subcategories_arr.length) ? v.subcategories_arr.join('; ') : 'None';

    document.getElementById('detailModalTitle').textContent = v.company_name + (v.is_urgent == 1 ? ' [URGENT]' : '');

    document.getElementById('detailModalBody').innerHTML = `
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px; font-size:13px;">
            <div style="background:#f8faf9; padding:10px 12px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11.5px; font-weight:600;">CLIENT NAME</div>
                <div style="font-weight:700; color:#0f172a;">${escapeHtml(v.client_name)}</div>
            </div>
            <div style="background:#f8faf9; padding:10px 12px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11.5px; font-weight:600;">PHONE NUMBER</div>
                <div style="font-weight:700; color:#0f172a;">${escapeHtml(v.phone)}</div>
            </div>
            <div style="background:#f8faf9; padding:10px 12px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11.5px; font-weight:600;">INTEREST LEVEL</div>
                <div style="font-weight:700; text-transform:uppercase;">${v.interest_level}</div>
            </div>
            <div style="background:#f8faf9; padding:10px 12px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11.5px; font-weight:600;">AVAILABLE CONTACT TIME</div>
                <div style="font-weight:700; color:#0f172a;">${escapeHtml(v.contact_availability)}</div>
            </div>
        </div>

        <div style="margin-bottom:12px; font-size:13px;">
            <div style="color:#64748b; font-size:11.5px; font-weight:600;">SERVICES REQUESTED</div>
            <div style="color:#0f172a; margin-top:2px;">${escapeHtml(cats)}</div>
            ${subs !== 'None' ? `<div style="color:#64748b; font-size:12px; margin-top:3px;"><em>Sub-items:</em> ${escapeHtml(subs)}</div>` : ''}
        </div>

        <div style="margin-bottom:14px; font-size:13px;">
            <div style="color:#64748b; font-size:11.5px; font-weight:600;">SALES REP REMARKS</div>
            <div style="background:#f1f5f9; padding:10px; border-radius:8px; color:#334155; margin-top:4px; font-size:12.5px;">${escapeHtml(v.remarks || 'No remarks provided.')}</div>
        </div>

        <!-- Quick Status Update Form -->
        <div style="border-top:1px solid #f1f5f9; padding-top:14px;">
            <div style="font-weight:700; font-size:13px; color:#0f172a; margin-bottom:8px;">Update Response Status</div>
            <div style="display:flex; gap:10px;">
                <select id="modalStatusSelect" class="form-select" style="flex:1;">
                    <option value="pending" ${v.status==='pending'?'selected':''}>⏳ Pending Contact</option>
                    <option value="contacted" ${v.status==='contacted'?'selected':''}>📞 Contacted Client</option>
                    <option value="in_discussion" ${v.status==='in_discussion'?'selected':''}>💬 In Discussion</option>
                    <option value="converted" ${v.status==='converted'?'selected':''}>🎯 Converted Deal</option>
                    <option value="closed" ${v.status==='closed'?'selected':''}>📁 Closed / Archive</option>
                </select>
                <button type="button" class="btn btn-primary-custom" onclick="saveModalStatus(${v.id})">Save Status</button>
            </div>
        </div>
    `;

    document.getElementById('detailWaWrap').innerHTML = cleanWa ? `
        <a href="https://wa.me/${cleanWa}?text=${waMsg}" target="_blank" class="btn-wa">
            <i class="fab fa-whatsapp"></i> Open WhatsApp Reach Out
        </a>
    ` : '';

    document.getElementById('detailModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('active');
    document.body.style.overflow = '';
}

function saveModalStatus(id) {
    const sel = document.getElementById('modalStatusSelect');
    if (!sel) return;
    const fd = new FormData();
    fd.append('action', 'update_status');
    fd.append('id', id);
    fd.append('status', sel.value);
    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeDetailModal();
                fetchFullRadar(false);
            }
        });
}

// ── Web Audio Chime Synthesis (Zero Lag, Clean Bell) ──
function playNotificationChime() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        if (!audioCtx) audioCtx = new AudioContext();
        if (audioCtx.state === 'suspended') audioCtx.resume();

        const now = audioCtx.currentTime;
        
        // Tone 1: High crisp bell
        const osc1 = audioCtx.createOscillator();
        const gain1 = audioCtx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(880, now); // A5
        osc1.frequency.exponentialRampToValueAtTime(1760, now + 0.15);
        gain1.gain.setValueAtTime(0.3, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
        osc1.connect(gain1);
        gain1.connect(audioCtx.destination);
        osc1.start(now);
        osc1.stop(now + 0.45);

        // Tone 2: Harmonic resolution
        const osc2 = audioCtx.createOscillator();
        const gain2 = audioCtx.createGain();
        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(1318.5, now + 0.12); // E6
        gain2.gain.setValueAtTime(0.25, now + 0.12);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.55);
        osc2.connect(gain2);
        gain2.connect(audioCtx.destination);
        osc2.start(now + 0.12);
        osc2.stop(now + 0.55);
    } catch(e) {
        console.warn('Audio chime note:', e);
    }
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
            btn.innerHTML = '<i class="fas fa-check-circle" style="color:#16a34a;"></i> Push Alerts Active';
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
            new Notification('Sales Response Radar', {
                body: 'Desktop notifications enabled for incoming client field visits.',
                icon: '<?= BASE_URL ?>assets/images/icon.png'
            });
        }
    });
}

function triggerDesktopAlert(v) {
    if ('Notification' in window && Notification.permission === 'granted') {
        const title = (v.is_urgent == 1 ? '🚨 URGENT: ' : '⚡ New Client Visit: ') + v.company_name;
        const n = new Notification(title, {
            body: `Client: ${v.client_name}\nInterest: ${v.interest_level.toUpperCase()}\nAvailable: ${v.contact_availability}`,
            icon: '<?= BASE_URL ?>assets/images/icon.png',
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
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
</script>

<?php include 'dashboard_footer.php'; ?>
