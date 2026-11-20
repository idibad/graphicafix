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

<div class="height-100 sales-unit-wrapper">

    <!-- ── Universal KPI Scorecards Row (Interactive Click-to-Filter) ── -->
    <div class="su-stats-grid" style="margin-bottom:20px;">
        <div class="su-kpi-card" id="kpiCardAll" onclick="handleKpiCardClick('all')" title="Filter: All Inbound Visits">
            <div class="stat-header">
                <span class="stat-label">Inbound Visits</span>
                <div class="stat-icon su-icon-primary"><i class="fas fa-street-view"></i></div>
            </div>
            <div class="stat-number" id="statTotal"><span class="su-skeleton-pulse">--</span></div>
            <div class="su-stat-subtext">Total field visits logged</div>
        </div>

        <div class="su-kpi-card su-kpi-overdue" id="kpiCardOverdue" onclick="handleKpiCardClick('overdue')" title="Filter: Overdue Follow-ups">
            <div class="stat-header">
                <span class="stat-label">Overdue Follow-ups</span>
                <div class="stat-icon su-icon-danger"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
            <div class="stat-number" id="statOverdue" style="color:#ef4444;"><span class="su-skeleton-pulse">--</span></div>
            <div class="su-stat-subtext" style="color:#ef4444; font-weight:700;">
                <i class="fas fa-circle" style="font-size:7px;"></i> Requires immediate attention
            </div>
        </div>

        <div class="su-kpi-card su-kpi-duetoday" id="kpiCardDueToday" onclick="handleKpiCardClick('due_today')" title="Filter: Follow-ups Due Today">
            <div class="stat-header">
                <span class="stat-label">Due Today</span>
                <div class="stat-icon su-icon-warning"><i class="fas fa-clock"></i></div>
            </div>
            <div class="stat-number" id="statDueToday" style="color:#d97706;"><span class="su-skeleton-pulse">--</span></div>
            <div class="su-stat-subtext" style="color:#b45309; font-weight:600;">
                <i class="fas fa-calendar-day" style="font-size:11px;"></i> Scheduled touchpoints for today
            </div>
        </div>

        <div class="su-kpi-card su-kpi-converted" id="kpiCardConverted" onclick="handleKpiCardClick('converted')" title="Filter: Converted to CRM Leads">
            <div class="stat-header">
                <span class="stat-label">Converted / Won</span>
                <div class="stat-icon su-icon-success"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="stat-number" id="statConverted" style="color:#16a34a;"><span class="su-skeleton-pulse">--</span></div>
            <div class="su-stat-subtext" style="color:#16a34a; font-weight:600;">
                <i class="fas fa-arrow-trend-up" style="font-size:11px;"></i> Converted to CRM leads
            </div>
        </div>
    </div>

    <!-- ── Main Navigation Tabs ── -->
    <div class="unit-tabs-nav">
        <button type="button" class="unit-tab-btn active" id="tabBtnRadar" onclick="switchUnitTab('radar')">
            <i class="fas fa-satellite-dish"></i>
            <span>Follow-Up & Response Radar</span>
            <span class="unit-tab-badge" id="radarCountBadge">0</span>
        </button>
        <button type="button" class="unit-tab-btn" id="tabBtnScheduled" onclick="switchUnitTab('scheduled')">
            <i class="far fa-calendar-check"></i>
            <span>Scheduled & Due Pipeline</span>
            <span class="unit-tab-badge alert-badge" id="scheduledAlertBadge">0</span>
        </button>
        <button type="button" class="unit-tab-btn" id="tabBtnReporting" onclick="switchUnitTab('reporting')">
            <i class="fas fa-chart-line"></i>
            <span>Performance & Follow-up Reporting</span>
        </button>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════════
         TAB 1: FOLLOW-UP & RESPONSE RADAR
         ══════════════════════════════════════════════════════════════════════════ -->
    <div id="tabContentRadar" class="unit-tab-content active">
        <div class="su-main-card">
            <div class="su-main-header">
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <div class="su-title-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div>
                        <h3 class="su-page-title">
                            Sales Response & Follow-up Radar
                        </h3>
                        <span class="su-page-subtitle">Real-time inbound queue, immediate outreach, and live pipeline status</span>
                    </div>

                    <div style="display:flex; align-items:center; gap:8px; margin-left:6px;">
                        <span id="radarPulseBadge" class="live-status-pill">
                            <span class="live-status-dot"></span> Live Radar (3.5s)
                        </span>
                        <button type="button" id="pauseResumeBtn" onclick="toggleRadarPolling()" class="su-btn-icon-toggle" title="Pause or Resume Live Polling">
                            <i class="fas fa-pause"></i>
                        </button>
                    </div>
                </div>

                <div class="su-header-actions">
                    <button type="button" onclick="openExportModal()" class="btn btn-secondary-custom su-btn-pill">
                        <i class="fas fa-file-excel" style="color:#16a34a;"></i>
                        <span>Export Sheets</span>
                    </button>
                    <button type="button" onclick="enablePushNotifications()" id="pushNotifyBtn" class="btn btn-secondary-custom su-btn-pill">
                        <i class="fas fa-bell"></i>
                        <span>Desktop Alerts</span>
                    </button>
                    <button type="button" onclick="toggleAudioMute()" id="audioMuteBtn" class="btn btn-secondary-custom su-btn-icon" title="Notification Audio: Sound On">
                        <i class="fas fa-volume-up"></i>
                    </button>
                    <button type="button" onclick="fetchFullRadar(true)" class="btn btn-secondary-custom su-btn-icon" title="Refresh Live Radar Now">
                        <i class="fas fa-sync-alt" id="refreshRadarIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Filter & Search Toolbar -->
            <div class="su-toolbar">
                <!-- Quick Filter Pills -->
                <div class="su-filter-pills-wrap">
                    <div class="su-quick-pills">
                        <button type="button" class="su-pill active" data-filter="pending" onclick="setFilter('pending')">
                            <i class="fas fa-hourglass-start"></i> Pending Contact
                        </button>
                        <button type="button" class="su-pill su-pill-duetoday" data-filter="due_today" onclick="setFilter('due_today')">
                            <i class="fas fa-clock"></i> ⏰ Due Today
                        </button>
                        <button type="button" class="su-pill su-pill-overdue" data-filter="overdue" onclick="setFilter('overdue')">
                            <i class="fas fa-exclamation-triangle"></i> 🚨 Overdue
                        </button>
                        <button type="button" class="su-pill su-pill-urgent" data-filter="urgent" onclick="setFilter('urgent')">
                            <i class="fas fa-bell"></i> 🚨 Urgent Flagged
                        </button>
                        <button type="button" class="su-pill su-pill-hot" data-filter="hot" onclick="setFilter('hot')">
                            <i class="fas fa-fire"></i> 🔥 Hot Priority
                        </button>
                        <button type="button" class="su-pill su-pill-discussion" data-filter="in_discussion" onclick="setFilter('in_discussion')">
                            <i class="fas fa-comments"></i> In Discussion
                        </button>
                        <button type="button" class="su-pill" data-filter="all" onclick="setFilter('all')">
                            <i class="fas fa-layer-group"></i> All Inbound Visits
                        </button>
                        <button type="button" class="su-pill su-pill-contacted" data-filter="contacted" onclick="setFilter('contacted')">
                            <i class="fas fa-phone-volume"></i> Contacted
                        </button>
                        <button type="button" class="su-pill su-pill-converted" data-filter="converted" onclick="setFilter('converted')">
                            <i class="fas fa-check-circle"></i> Converted Leads
                        </button>
                        <button type="button" class="su-pill" data-filter="closed" onclick="setFilter('closed')">
                            <i class="fas fa-archive"></i> Closed / Archived
                        </button>
                    </div>
                </div>

                <div class="su-search-sort-bar">
                    <div class="su-control-group">
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span style="font-size:12px; font-weight:700; color:var(--sv-slate); text-transform:uppercase;">Sort By:</span>
                            <select id="sortSelect" onchange="setSort(this.value)" class="form-select su-select" style="width:auto; height:36px;">
                                <option value="urgency" selected>Urgency & Priority First</option>
                                <option value="followup_due">Next Follow-up Due Date</option>
                                <option value="followup_cnt">Most Follow-ups Completed</option>
                                <option value="interest">Interest Level (High to Low)</option>
                                <option value="contact_time">Preferred Contact Window</option>
                                <option value="newest">Newest Logged First</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:flex; align-items:center; gap:12px; flex:1; max-width:440px; justify-content:flex-end;">
                        <!-- Live Search with clear button -->
                        <div class="su-search-box">
                            <i class="fas fa-search su-search-icon"></i>
                            <input type="text" id="searchInput" oninput="handleSearch(this.value)" placeholder="Search company, client, phone, requirements..."
                                   class="form-control su-search-input">
                            <button type="button" id="searchClearBtn" class="su-search-clear" onclick="clearSearch()" title="Clear search" style="display:none;">
                                &times;
                            </button>
                        </div>

                        <div class="su-record-count" id="radarVisibleBadge">
                            Showing <strong id="visibleCount">0</strong> visits
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Container -->
            <div class="su-table-responsive">
                <table class="data-table su-table" id="radarTable">
                    <thead>
                        <tr>
                            <th style="width:22%;">Client & Company</th>
                            <th style="width:14%;">Direct Connect</th>
                            <th style="width:13%;">Stage & Priority</th>
                            <th style="width:20%;">Follow-Up Track Record</th>
                            <th style="width:13%;">Requirements</th>
                            <th style="width:9%;">Sales Rep</th>
                            <th style="width:9%; text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="radarTableBody">
                        <tr>
                            <td colspan="7" class="su-loading-cell">
                                <div class="su-spinner-box">
                                    <i class="fas fa-satellite-dish fa-spin su-primary-spin"></i>
                                    <p>Connecting to Live Response Radar...</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════════
         TAB 2: SCHEDULED & DUE FOLLOW-UPS PIPELINE
         ══════════════════════════════════════════════════════════════════════════ -->
    <div id="tabContentScheduled" class="unit-tab-content">
        <div class="su-main-card">
            <div class="su-main-header">
                <div>
                    <h3 class="su-page-title">
                        <i class="far fa-calendar-check" style="color:var(--sv-primary);"></i> Scheduled Follow-Up Pipeline
                    </h3>
                    <p class="su-page-subtitle">
                        Zero dropped leads. Track upcoming client touchpoints and clear overdue calls.
                    </p>
                </div>

                <div class="scheduled-filter-chips">
                    <button type="button" class="subtab-pill active" onclick="setScheduledFilter('all', this)">
                        All Scheduled (<span id="schedCountAll">0</span>)
                    </button>
                    <button type="button" class="subtab-pill subtab-pill-overdue" onclick="setScheduledFilter('overdue', this)">
                        🚨 Overdue (<span id="schedCountOverdue">0</span>)
                    </button>
                    <button type="button" class="subtab-pill subtab-pill-due" onclick="setScheduledFilter('due_today', this)">
                        ⏰ Due Today (<span id="schedCountDue">0</span>)
                    </button>
                    <button type="button" class="subtab-pill" onclick="setScheduledFilter('upcoming', this)">
                        📅 Upcoming (<span id="schedCountUpcoming">0</span>)
                    </button>
                </div>
            </div>

            <!-- Scheduled Table -->
            <div class="su-table-responsive">
                <table class="data-table su-table" id="scheduledTable">
                    <thead>
                        <tr>
                            <th style="width:20%;">Client & Company</th>
                            <th style="width:16%;">Follow-Up Due Time</th>
                            <th style="width:15%;">Contact Details</th>
                            <th style="width:16%;">History & Count</th>
                            <th style="width:14%;">Last Touch Outcome</th>
                            <th style="width:9%;">Sales Rep</th>
                            <th style="width:10%; text-align:right;">Quick Action</th>
                        </tr>
                    </thead>
                    <tbody id="scheduledTableBody">
                        <tr>
                            <td colspan="7" class="su-loading-cell">
                                <div class="su-spinner-box">
                                    <i class="fas fa-spinner fa-spin su-primary-spin"></i>
                                    <p>Loading scheduled follow-ups...</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════════
         TAB 3: FOLLOW-UP & SALES PERFORMANCE REPORTING
         ══════════════════════════════════════════════════════════════════════════ -->
    <div id="tabContentReporting" class="unit-tab-content">

        <!-- Reporting Control & Filter Toolbar -->
        <div class="su-main-card" style="margin-bottom:20px; padding:18px 22px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
                <div>
                    <h3 class="su-page-title" style="font-size:16px;">
                        <i class="fas fa-file-invoice" style="color:var(--sv-primary);"></i> Sales Follow-up & Conversion Reporting
                    </h3>
                    <p class="su-page-subtitle">
                        Filter by date range and sales representative to generate executive performance analytics.
                    </p>
                </div>

                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <!-- Date Presets -->
                    <select id="reportDatePreset" onchange="handleReportPresetChange(this.value)" class="form-select su-select" style="font-size:12.5px; height:36px; padding:6px 12px;">
                        <option value="all">All Time</option>
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="week">Last 7 Days</option>
                        <option value="month" selected>This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="custom">Custom Date Range</option>
                    </select>

                    <div id="customDateRangeWrap" style="display:none; gap:6px; align-items:center;">
                        <input type="date" id="reportStartDate" class="form-control su-date-input" style="height:36px;">
                        <span style="color:#64748b; font-size:12px; font-weight:600;">to</span>
                        <input type="date" id="reportEndDate" class="form-control su-date-input" style="height:36px;">
                    </div>

                    <!-- Rep Filter -->
                    <select id="reportRepSelect" class="form-select su-select" style="font-size:12.5px; height:36px; max-width:200px;">
                        <option value="0">All Sales Reps</option>
                    </select>

                    <button type="button" onclick="loadReportingData()" class="btn btn-primary-custom su-btn-pill" style="height:36px;">
                        <i class="fas fa-sync-alt"></i> Apply Filter
                    </button>

                    <button type="button" onclick="exportReportingCSV('visits')" class="btn btn-secondary-custom su-btn-pill" style="height:36px;" title="Export Master Pipeline CSV">
                        <i class="fas fa-file-excel" style="color:#16a34a;"></i> Pipeline CSV
                    </button>
                    <button type="button" onclick="exportReportingCSV('followup_history')" class="btn btn-secondary-custom su-btn-pill" style="height:36px;" title="Export Detailed Touchpoints Trail">
                        <i class="fas fa-history" style="color:#0284c7;"></i> Touchpoints CSV
                    </button>
                    <button type="button" onclick="window.print()" class="btn btn-secondary-custom su-btn-icon" style="height:36px;" title="Print Report">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Reporting KPI Summary Cards -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin-bottom:20px;">
            <div class="su-kpi-card" style="padding:16px;">
                <div style="font-size:11.5px; font-weight:700; color:var(--sv-slate); text-transform:uppercase;">Visits Analyzed</div>
                <div style="font-size:26px; font-weight:800; color:var(--sv-dark); margin-top:4px;" id="repKpiVisits">--</div>
                <div style="font-size:11.5px; color:var(--sv-slate); margin-top:2px;" id="repKpiVisitsSub">In selected period</div>
            </div>

            <div class="su-kpi-card" style="padding:16px;">
                <div style="font-size:11.5px; font-weight:700; color:var(--sv-slate); text-transform:uppercase;">Follow-ups Logged</div>
                <div style="font-size:26px; font-weight:800; color:#0284c7; margin-top:4px;" id="repKpiFollowups">--</div>
                <div style="font-size:11.5px; color:var(--sv-slate); margin-top:2px;">
                    <span id="repKpiAvgF" style="font-weight:700; color:#0284c7;">0</span> avg per visit
                </div>
            </div>

            <div class="su-kpi-card" style="padding:16px;">
                <div style="font-size:11.5px; font-weight:700; color:var(--sv-slate); text-transform:uppercase;">Engagement Rate</div>
                <div style="font-size:26px; font-weight:800; color:#8b5cf6; margin-top:4px;" id="repKpiEngagement">--%</div>
                <div style="font-size:11.5px; color:var(--sv-slate); margin-top:2px;">Visits with &ge; 1 follow-up</div>
            </div>

            <div class="su-kpi-card su-kpi-converted" style="padding:16px;">
                <div style="font-size:11.5px; font-weight:700; color:var(--sv-slate); text-transform:uppercase;">Converted Leads</div>
                <div style="font-size:26px; font-weight:800; color:#16a34a; margin-top:4px;" id="repKpiConverted">--</div>
                <div style="font-size:11.5px; color:#16a34a; font-weight:600; margin-top:2px;">
                    <span id="repKpiWinRate" style="font-weight:800;">0%</span> conversion win rate
                </div>
            </div>

            <div class="su-kpi-card su-kpi-overdue" style="padding:16px;">
                <div style="font-size:11.5px; font-weight:700; color:var(--sv-slate); text-transform:uppercase;">Overdue Follow-ups</div>
                <div style="font-size:26px; font-weight:800; color:#ef4444; margin-top:4px;" id="repKpiOverdue">--</div>
                <div style="font-size:11.5px; color:#ef4444; font-weight:600; margin-top:2px;">Missed scheduled calls</div>
            </div>
        </div>

        <!-- Reporting Grid: Leaderboard & Distribution Breakdown -->
        <div style="display:grid; grid-template-columns:1.8fr 1.2fr; gap:16px; margin-bottom:20px;">

            <!-- Sales Rep Performance Leaderboard -->
            <div class="su-main-card">
                <div class="su-main-header" style="padding:14px 18px;">
                    <h4 style="margin:0; font-size:14px; font-weight:700; color:var(--sv-dark); display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-trophy" style="color:#eab308;"></i> Sales Rep Follow-Up & Conversion Leaderboard
                    </h4>
                </div>
                <div class="su-table-responsive">
                    <table class="data-table su-table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Sales Representative</th>
                                <th style="text-align:center;">Visits</th>
                                <th style="text-align:center;">Follow-ups</th>
                                <th style="text-align:center;">Converted</th>
                                <th style="text-align:center;">Win Rate</th>
                                <th style="text-align:right;">Last Active</th>
                            </tr>
                        </thead>
                        <tbody id="leaderboardTableBody">
                            <tr>
                                <td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">Loading leaderboard...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Follow-up Outcome & Channel Breakdown -->
            <div style="display:flex; flex-direction:column; gap:16px;">

                <!-- Follow-up Outcomes -->
                <div class="su-main-card" style="padding:16px 18px;">
                    <h4 style="margin:0 0 12px; font-size:14px; font-weight:700; color:var(--sv-dark); display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-pie-chart" style="color:var(--sv-primary);"></i> Follow-Up Outcomes Distribution
                    </h4>
                    <div id="outcomeDistributionWrap" style="display:flex; flex-direction:column; gap:10px;">
                        <div style="color:#94a3b8; font-size:12px;">Loading outcomes...</div>
                    </div>
                </div>

                <!-- Contact Channels Used -->
                <div class="su-main-card" style="padding:16px 18px;">
                    <h4 style="margin:0 0 12px; font-size:14px; font-weight:700; color:var(--sv-dark); display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-comments" style="color:#0284c7;"></i> Channels Utilized
                    </h4>
                    <div id="channelDistributionWrap" style="display:flex; flex-direction:column; gap:10px;">
                        <div style="color:#94a3b8; font-size:12px;">Loading channels...</div>
                    </div>
                </div>

            </div>

        </div>

    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     MODAL 1: LOG STRUCTURED FOLLOW-UP TRACK RECORD (Universal Modal)
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="followupModal" class="modal-overlay su-modal" onclick="if(event.target===this)closeFollowupModal()">
    <div class="modal-content su-modal-content" style="max-width:640px;">
        <div class="modal-header su-modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <div class="modal-header-icon">
                    <i class="fas fa-phone-volume"></i>
                </div>
                <div>
                    <h4 id="fModalCompanyTitle">Log Follow-Up Track Record</h4>
                    <span id="fModalClientSub" class="su-modal-subtitle">Record interaction details and schedule next touchpoint</span>
                </div>
            </div>
            <button class="modal-close" onclick="closeFollowupModal()">&times;</button>
        </div>

        <form id="followupForm" onsubmit="submitFollowupRecord(event)">
            <input type="hidden" name="visit_id" id="fModalVisitId" value="">

            <div class="modal-body su-modal-body">
                <!-- Contact Channels Selector -->
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="su-field-label">
                        Contact Channel Used <span class="text-danger">*</span>
                    </label>
                    <div class="channel-chip-group">
                        <label class="channel-chip active">
                            <input type="radio" name="contact_channel" value="call" checked onchange="updateChannelChipUI(this)">
                            <i class="fas fa-phone-alt"></i> Phone Call
                        </label>
                        <label class="channel-chip">
                            <input type="radio" name="contact_channel" value="whatsapp" onchange="updateChannelChipUI(this)">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </label>
                        <label class="channel-chip">
                            <input type="radio" name="contact_channel" value="meeting" onchange="updateChannelChipUI(this)">
                            <i class="fas fa-handshake"></i> Meeting
                        </label>
                        <label class="channel-chip">
                            <input type="radio" name="contact_channel" value="email" onchange="updateChannelChipUI(this)">
                            <i class="fas fa-envelope"></i> Email
                        </label>
                        <label class="channel-chip">
                            <input type="radio" name="contact_channel" value="office_visit" onchange="updateChannelChipUI(this)">
                            <i class="fas fa-building"></i> Office Visit
                        </label>
                    </div>
                </div>

                <!-- Outcome & Pipeline Stage -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div class="form-group">
                        <label class="su-field-label">
                            Interaction Outcome <span class="text-danger">*</span>
                        </label>
                        <select name="outcome" id="fModalOutcome" class="form-select su-select" required onchange="handleOutcomeChange(this.value)">
                            <option value="followup_needed" selected>⏳ Follow-up Needed</option>
                            <option value="interested">🔥 Highly Interested / Ready</option>
                            <option value="meeting_scheduled">📅 Meeting Scheduled</option>
                            <option value="proposal_sent">📄 Proposal / Quote Sent</option>
                            <option value="callback_requested">🔄 Client Requested Callback</option>
                            <option value="no_answer">📵 No Answer / Busy</option>
                            <option value="converted">🎉 Deal Converted (Won)</option>
                            <option value="not_interested">❌ Not Interested / Lost</option>
                            <option value="closed">📁 Closed / Resolved</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="su-field-label">
                            Update Pipeline Stage
                        </label>
                        <select name="status" id="fModalStatus" class="form-select su-select">
                            <option value="in_discussion" selected>In Discussion</option>
                            <option value="contacted">Contacted</option>
                            <option value="converted">Converted to CRM Lead</option>
                            <option value="closed">Closed / Archived</option>
                        </select>
                    </div>
                </div>

                <!-- Discussion Notes with Quick Tags -->
                <div class="form-group" style="margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <label class="su-field-label" style="margin:0;">
                            Follow-up Discussion Notes <span class="text-danger">*</span>
                        </label>
                        <span class="su-hint-text">Be concise & specific</span>
                    </div>
                    <div class="su-preset-chips" style="margin-bottom:6px;">
                        <button type="button" class="preset-tag" onclick="appendFollowupNote('Called client. Discussed scope of work.')">+ Discussed scope</button>
                        <button type="button" class="preset-tag" onclick="appendFollowupNote('Shared catalog and price quotation via WhatsApp.')">+ Sent Quotation</button>
                        <button type="button" class="preset-tag" onclick="appendFollowupNote('Client needs 2 days to consult with partner.')">+ Partner decision</button>
                        <button type="button" class="preset-tag" onclick="appendFollowupNote('Client agreed to meeting at their office.')">+ Office meeting</button>
                        <button type="button" class="preset-tag" onclick="appendFollowupNote('No answer, sent follow-up text.')">+ No response</button>
                    </div>
                    <textarea name="notes" id="fModalNotes" rows="3" required placeholder="What was discussed? Client feedback? Any pricing or timeline agreements made?" class="form-control" style="font-size:12.5px; resize:vertical;"></textarea>
                </div>

                <!-- Schedule Next Follow-up -->
                <div class="form-group" style="margin-bottom:6px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label class="su-field-label" style="margin:0;">
                            <i class="far fa-calendar-alt" style="color:var(--sv-primary); margin-right:4px;"></i> Schedule Next Follow-Up Date & Time
                        </label>
                        <button type="button" onclick="clearNextFollowupDate()" class="su-clear-link">
                            Clear Schedule
                        </button>
                    </div>

                    <div class="su-preset-chips" style="margin-bottom:8px;">
                        <button type="button" class="preset-tag" onclick="setNextDatePreset('tomorrow_am')">Tomorrow Morning (11:00 AM)</button>
                        <button type="button" class="preset-tag" onclick="setNextDatePreset('tomorrow_pm')">Tomorrow Afternoon (4:00 PM)</button>
                        <button type="button" class="preset-tag" onclick="setNextDatePreset('in_2_days')">In 2 Days</button>
                        <button type="button" class="preset-tag" onclick="setNextDatePreset('next_week')">Next Week Monday</button>
                    </div>

                    <input type="datetime-local" name="next_followup_date" id="fModalNextDate" class="form-control su-datetime-input">
                    <small style="font-size:11px; color:#64748b; margin-top:4px; display:block;">
                        Scheduled touchpoints automatically activate alerts when due.
                    </small>
                </div>
            </div>

            <div class="modal-footer su-modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeFollowupModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="fModalSubmitBtn" style="display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-check"></i> <span>Save Follow-Up Record</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     MODAL 2: COMPLETE FOLLOW-UP TIMELINE & HISTORY (Universal Modal)
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="timelineModal" class="modal-overlay su-modal" onclick="if(event.target===this)closeTimelineModal()">
    <div class="modal-content su-modal-content" style="max-width:700px;">
        <div class="modal-header su-modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <div class="modal-header-icon">
                    <i class="fas fa-history"></i>
                </div>
                <div>
                    <h4 id="tModalCompany">Follow-Up Track Record Timeline</h4>
                    <span id="tModalSub" class="su-modal-subtitle">Chronological interaction history</span>
                </div>
            </div>
            <button class="modal-close" onclick="closeTimelineModal()">&times;</button>
        </div>

        <div style="padding:12px 22px; background:#fff; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; flex-shrink:0;">
            <div id="tModalQuickBadges" style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;"></div>
            <button type="button" id="tModalAddBtn" class="btn btn-primary-custom su-btn-pill" style="font-size:12px;">
                <i class="fas fa-plus"></i> <span>Log New Follow-Up</span>
            </button>
        </div>

        <div class="modal-body su-modal-body" id="tModalTimelineBody" style="background:#fafbfc;">
            <!-- Rendered dynamically -->
        </div>

        <div class="modal-footer su-modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
            <div id="tModalDirectWa"></div>
            <button type="button" class="btn btn-secondary-custom" onclick="closeTimelineModal()">Close Timeline</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     MODAL 3: RESPONSE WORKSPACE & CANNED WHATSAPP TEMPLATES (Universal Modal)
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="detailModal" class="modal-overlay su-modal" onclick="if(event.target===this)closeDetailModal()">
    <div class="modal-content su-modal-content" style="max-width:720px;">
        <div class="modal-header su-modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <div class="modal-header-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <div>
                    <h4 id="detailModalTitle">Response Workspace</h4>
                    <span id="detailModalSub" class="su-modal-subtitle">Client Follow-up & Rapid Response</span>
                </div>
            </div>
            <button class="modal-close" onclick="closeDetailModal()">&times;</button>
        </div>

        <div class="modal-body su-modal-body" id="detailModalBody">
            <!-- Populated dynamically -->
        </div>

        <div class="modal-footer su-modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
            <div id="detailWaWrap"></div>
            <button type="button" class="btn btn-secondary-custom" onclick="closeDetailModal()">Close Workspace</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     MODAL 4: EXPORT SHEETS MODAL (Universal Modal)
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="exportModal" class="modal-overlay su-modal" onclick="if(event.target===this)closeExportModal()">
    <div class="modal-content su-modal-content" style="max-width:540px;">
        <div class="modal-header su-modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <div class="modal-header-icon">
                    <i class="fas fa-file-excel"></i>
                </div>
                <div>
                    <h4>Export Field Visits & Follow-up Sheets</h4>
                    <span class="su-modal-subtitle">Download formatted spreadsheet (Excel & CSV compatible)</span>
                </div>
            </div>
            <button class="modal-close" onclick="closeExportModal()">&times;</button>
        </div>

        <form id="exportForm" onsubmit="executeExport(event)">
            <div class="modal-body su-modal-body">
                <!-- Export Type -->
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="su-field-label">Select Report Type</label>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                        <label class="channel-chip active" style="justify-content:center;">
                            <input type="radio" name="export_type" value="visits" checked onchange="updateChannelChipUI(this)">
                            <i class="fas fa-table"></i> Master Visits & Status
                        </label>
                        <label class="channel-chip" style="justify-content:center;">
                            <input type="radio" name="export_type" value="followup_history" onchange="updateChannelChipUI(this)">
                            <i class="fas fa-history"></i> Touchpoints Trail
                        </label>
                    </div>
                </div>

                <!-- Filter by Sales Rep -->
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="su-field-label">Sales Representative</label>
                    <select id="exportRepSelect" class="form-select su-select" style="width:100%;">
                        <option value="0">All Representatives (Company-wide)</option>
                    </select>
                </div>

                <!-- Date Presets -->
                <div style="margin-bottom:14px;">
                    <label class="su-field-label">Date Range Preset</label>
                    <div class="su-preset-chips">
                        <button type="button" class="preset-pill active" onclick="setExportDatePreset('all', this)">All Time</button>
                        <button type="button" class="preset-pill" onclick="setExportDatePreset('today', this)">Today</button>
                        <button type="button" class="preset-pill" onclick="setExportDatePreset('yesterday', this)">Yesterday</button>
                        <button type="button" class="preset-pill" onclick="setExportDatePreset('week', this)">Last 7 Days</button>
                        <button type="button" class="preset-pill" onclick="setExportDatePreset('month', this)">This Month</button>
                    </div>
                </div>

                <!-- Custom Date Range -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="su-field-label">From Date</label>
                        <input type="date" id="exportStartDate" class="form-control su-date-input">
                    </div>
                    <div class="form-group">
                        <label class="su-field-label">To Date</label>
                        <input type="date" id="exportEndDate" class="form-control su-date-input">
                    </div>
                </div>
            </div>

            <div class="modal-footer su-modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeExportModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom su-btn-pill">
                    <i class="fas fa-download"></i> <span>Download Spreadsheet</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     MODERN TOAST NOTIFICATION CONTAINER
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="suToastContainer" class="su-toast-container"></div>

<style>
/* ── Unified Modern Design Variables ── */
:root {
    --sv-primary: #024442;
    --sv-primary-hover: #013331;
    --sv-primary-light: #e6f4f2;
    --sv-accent: #0f766e;
    --sv-success: #16a34a;
    --sv-warning: #d97706;
    --sv-danger: #ef4444;
    --sv-dark: #0f172a;
    --sv-slate: #64748b;
    --sv-border: #e2e8f0;
    --sv-card-bg: #ffffff;
    --sv-radius: 12px;
}

.sales-unit-wrapper {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ── KPI Scorecards with Interactive Filter ── */
.su-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 16px;
}
.su-kpi-card {
    background: #ffffff;
    border: 1px solid var(--sv-border);
    border-radius: var(--sv-radius);
    padding: 16px 20px;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
.su-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
}
.su-kpi-card.active-kpi {
    border-color: var(--sv-primary);
    box-shadow: 0 0 0 2px rgba(2, 68, 66, 0.2);
    background: #fbfdfd;
}
.su-kpi-card .stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.su-kpi-card .stat-label {
    font-size: 13px;
    font-weight: 700;
    color: var(--sv-slate);
    text-transform: uppercase;
    letter-spacing: .04em;
}
.su-kpi-card .stat-number {
    font-size: 28px;
    font-weight: 800;
    color: var(--sv-dark);
    line-height: 1.1;
}
.su-kpi-overdue   { border-left: 4px solid var(--sv-danger); }
.su-kpi-duetoday  { border-left: 4px solid var(--sv-warning); }
.su-kpi-converted { border-left: 4px solid var(--sv-success); }

.su-icon-primary { background: #e0f2fe; color: #0284c7; }
.su-icon-danger  { background: #fee2e2; color: #ef4444; }
.su-icon-warning { background: #fef3c7; color: #d97706; }
.su-icon-success { background: #dcfce7; color: #16a34a; }

.su-stat-subtext {
    font-size: 11.5px;
    color: var(--sv-slate);
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.su-skeleton-pulse {
    display: inline-block;
    color: #cbd5e1;
    animation: pulse 1.2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 0.5; }
    50% { opacity: 1; }
}

/* ── Main Navigation Tabs ── */
.unit-tabs-nav {
    display: flex;
    gap: 8px;
    margin-bottom: 18px;
    border-bottom: 1.5px solid var(--sv-border);
    padding-bottom: 2px;
}
.unit-tab-btn {
    background: transparent;
    border: none;
    outline: none;
    padding: 11px 18px;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--sv-slate);
    border-radius: 8px 8px 0 0;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.15s ease;
    border-bottom: 2.5px solid transparent;
}
.unit-tab-btn:hover {
    color: var(--sv-dark);
    background: #f8fafc;
}
.unit-tab-btn.active {
    color: var(--sv-primary);
    border-bottom: 2.5px solid var(--sv-primary);
    background: #fff;
    font-weight: 700;
}
.unit-tab-badge {
    background: #e2e8f0;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 12px;
}
.unit-tab-badge.alert-badge {
    background: #fee2e2;
    color: #b91c1c;
    animation: pulse 1.8s infinite;
}
.unit-tab-content {
    display: none;
}
.unit-tab-content.active {
    display: block;
    animation: fadeIn 0.25s ease;
}

/* ── Main Card & Header ── */
.su-main-card {
    background: #fff;
    border-radius: var(--sv-radius);
    border: 1px solid var(--sv-border);
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}
.su-main-header {
    padding: 18px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--sv-border);
    background: #ffffff;
    flex-wrap: wrap;
    gap: 12px;
}
.su-page-title {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    color: var(--sv-dark);
    display: flex;
    align-items: center;
    gap: 10px;
}
.su-title-icon {
    width: 36px;
    height: 36px;
    background: var(--sv-primary-light);
    color: var(--sv-primary);
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}
.su-page-subtitle {
    font-size: 12.5px;
    color: var(--sv-slate);
    display: block;
    margin-top: 3px;
}
.su-header-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}
.su-btn-pill {
    padding: 7px 14px;
    font-size: 12.5px;
    font-weight: 600;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none !important;
}
.su-btn-icon {
    padding: 7px 11px;
    font-size: 13px;
    border-radius: 8px;
    color: var(--sv-slate);
    background: #fff;
    border: 1px solid var(--sv-border);
    cursor: pointer;
    transition: all 0.15s ease;
}
.su-btn-icon:hover {
    background: #f8fafc;
    color: var(--sv-dark);
}
.su-btn-icon-toggle {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #475569;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    transition: all 0.15s ease;
}
.su-btn-icon-toggle:hover {
    background: #f1f5f9;
    color: #0f172a;
}

/* ── Live Pulse Badge ── */
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
    transition: all 0.2s ease;
}
.live-status-pill.paused {
    color: #b45309;
    background: #fef3c7;
}
.live-status-dot {
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #16a34a;
    animation: livePulse 1.8s infinite;
}
.live-status-pill.paused .live-status-dot {
    background: #d97706;
    animation: none;
}
@keyframes livePulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.85); }
}

/* ── Filter Toolbar ── */
.su-toolbar {
    background: #fafbfc;
    border-bottom: 1px solid var(--sv-border);
    padding: 12px 20px;
}
.su-filter-pills-wrap {
    margin-bottom: 10px;
    overflow-x: auto;
    padding-bottom: 2px;
}
.su-quick-pills {
    display: flex;
    gap: 6px;
    align-items: center;
    white-space: nowrap;
}
.su-pill {
    background: #ffffff;
    border: 1px solid var(--sv-border);
    color: #475569;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.15s ease;
}
.su-pill:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #cbd5e1;
}
.su-pill.active {
    background: var(--sv-primary);
    border-color: var(--sv-primary);
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(2, 68, 66, 0.2);
}
.su-pill-urgent.active     { background: #ef4444; border-color: #ef4444; }
.su-pill-duetoday.active   { background: #d97706; border-color: #d97706; }
.su-pill-overdue.active    { background: #b91c1c; border-color: #b91c1c; }
.su-pill-hot.active        { background: #dc2626; border-color: #dc2626; }
.su-pill-discussion.active { background: #6d28d9; border-color: #6d28d9; }
.su-pill-contacted.active  { background: #0284c7; border-color: #0284c7; }
.su-pill-converted.active  { background: #16a34a; border-color: #16a34a; }

.su-search-sort-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.su-control-group {
    display: flex;
    align-items: center;
    gap: 8px;
}
.su-select {
    font-size: 12.5px;
    height: 36px;
    border: 1px solid var(--sv-border);
    border-radius: 8px;
    padding: 4px 10px;
    background-color: #fff;
    color: #334155;
    font-weight: 500;
}
.su-search-box {
    position: relative;
    width: 100%;
    max-width: 300px;
}
.su-search-icon {
    position: absolute;
    left: 11px;
    top: 11px;
    color: #94a3b8;
    font-size: 12px;
}
.su-search-input {
    padding-left: 32px;
    padding-right: 28px;
    font-size: 12.5px;
    height: 36px;
    border-radius: 8px;
    border: 1px solid var(--sv-border);
    background: #fff;
    width: 100%;
    transition: all 0.15s ease;
}
.su-search-input:focus {
    border-color: var(--sv-primary);
    box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.1);
}
.su-search-clear {
    position: absolute;
    right: 8px;
    top: 7px;
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 16px;
    line-height: 1;
    cursor: pointer;
    padding: 2px 4px;
}
.su-search-clear:hover { color: #334155; }

.su-record-count {
    font-size: 12px;
    color: var(--sv-slate);
    white-space: nowrap;
}

/* ── Scheduled Pipeline Sub-tabs ── */
.scheduled-filter-chips {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}
.subtab-pill {
    background: #f8fafc;
    color: #475569;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    padding: 6px 14px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.subtab-pill:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}
.subtab-pill.active {
    background: var(--sv-primary);
    color: #ffffff;
    border-color: var(--sv-primary);
    box-shadow: 0 2px 4px rgba(2, 68, 66, 0.2);
}
.subtab-pill-overdue.active { background: #b91c1c; border-color: #b91c1c; }
.subtab-pill-due.active     { background: #d97706; border-color: #d97706; }

/* ── Data Tables ── */
.su-table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.su-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    background: #fff;
    min-width: 980px;
}
.su-table th {
    background: #f8fafc;
    padding: 12px 16px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    color: var(--sv-slate);
    text-transform: uppercase;
    letter-spacing: .05em;
    border-bottom: 1.5px solid var(--sv-border);
    white-space: nowrap;
}
.su-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}
.su-table tr:hover td {
    background: #fcfdfe;
}
.su-table tr:last-child td {
    border-bottom: none;
}
.su-loading-cell {
    text-align: center;
    padding: 60px 20px !important;
    color: var(--sv-slate);
}
.su-spinner-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}
.su-primary-spin {
    font-size: 26px;
    color: var(--sv-primary);
}

/* ── Direct WhatsApp Reach Out Button ── */
.su-wa-pill {
    background: #16a34a !important;
    color: #ffffff !important;
    font-weight: 700;
    font-size: 11.5px;
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none !important;
    border: none;
    box-shadow: 0 1px 2px rgba(22, 163, 74, 0.2);
    white-space: nowrap;
    transition: all 0.15s ease;
}
.su-wa-pill:hover {
    background: #15803d !important;
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(22, 163, 74, 0.3);
}
.su-phone-link {
    font-size: 12px;
    color: var(--sv-slate);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 3px;
    font-weight: 500;
}
.su-phone-link:hover {
    color: var(--sv-dark);
    text-decoration: underline;
}
.su-copy-phone-btn {
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 11px;
    cursor: pointer;
    padding: 1px 4px;
    border-radius: 3px;
    transition: all 0.12s ease;
}
.su-copy-phone-btn:hover {
    color: var(--sv-dark);
    background: #e2e8f0;
}

/* ── Badges ── */
.badge-priority {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 7px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
}
.badge-hot   { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }
.badge-warm  { background: #fffbeb; color: #b45309; border: 1px solid #fef3c7; }
.badge-cold  { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }

.badge-urgent-tag {
    background: #ef4444;
    color: #ffffff;
    font-size: 9.5px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-left: 6px;
    display: inline-block;
}

.badge-stage {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 700;
}
.badge-stage-pending    { background: #fef3c7; color: #92400e; }
.badge-stage-contacted  { background: #e0f2fe; color: #0369a1; }
.badge-stage-discussion { background: #ede9fe; color: #6d28d9; }
.badge-stage-converted  { background: #dcfce7; color: #15803d; }
.badge-stage-closed     { background: #f1f5f9; color: #64748b; }

.fo-schedule-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.fo-overdue   { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
.fo-duetoday  { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.fo-upcoming  { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
.fo-none      { background: #f1f5f9; color: #94a3b8; }
.fo-completed { background: #dcfce7; color: #15803d; }

.outcome-pill {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 4px;
    display: inline-block;
}
.outcome-pill-interested  { background: #dcfce7; color: #15803d; }
.outcome-pill-meeting     { background: #ede9fe; color: #6d28d9; }
.outcome-pill-proposal    { background: #e0f2fe; color: #0369a1; }
.outcome-pill-callback    { background: #fef3c7; color: #b45309; }
.outcome-pill-followup    { background: #f1f5f9; color: #475569; }
.outcome-pill-noanswer    { background: #fef2f2; color: #b91c1c; }
.outcome-pill-notinterest { background: #f8fafc; color: #64748b; }
.outcome-pill-converted   { background: #bbf7d0; color: #166534; font-weight:700; }

/* ── Inline Action Row ── */
.su-actions-cell {
    display: inline-flex;
    gap: 5px;
    justify-content: flex-end;
    align-items: center;
}
.su-btn-followup {
    background: var(--sv-primary);
    border: 1px solid var(--sv-primary);
    color: #ffffff;
    font-size: 11.5px;
    font-weight: 700;
    padding: 5px 10px;
    border-radius: 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.15s ease;
    white-space: nowrap;
}
.su-btn-followup:hover {
    background: var(--sv-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(2, 68, 66, 0.2);
}
.su-icon-btn {
    width: 29px;
    height: 29px;
    border-radius: 6px;
    border: 1px solid var(--sv-border);
    background: #fff;
    color: #475569;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    transition: all 0.15s ease;
}
.su-icon-btn:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #0f172a;
}
.su-icon-btn.active-urgent {
    background: #fef2f2;
    border-color: #fca5a5;
    color: #ef4444;
}

/* ── Modals Framework ── */
.su-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    overflow-y: auto !important;
    box-sizing: border-box;
}
.su-modal.active {
    display: flex;
}
.su-modal-content {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-height: 90vh !important;
    box-shadow: 0 25px 35px -5px rgba(0, 0, 0, 0.18), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    display: flex;
    flex-direction: column;
    overflow-y: auto !important;
    animation: modalSlideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    margin: auto;
    border: none;
}
@keyframes modalSlideUp {
    from { transform: translateY(18px) scale(0.98); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}
.su-modal-header {
    background: linear-gradient(135deg, var(--sv-primary) 0%, #035a58 100%);
    color: #fff;
    padding: 16px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 16px 16px 0 0;
    flex-shrink: 0;
}
.su-modal-header h4 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #fff;
}
.modal-header-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    background: rgba(255, 255, 255, 0.18);
    border-radius: 8px;
    color: #fff;
    font-size: 15px;
}
.su-modal-subtitle {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.85);
    display: block;
    margin-top: 2px;
}
.modal-close {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.8);
    font-size: 24px;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    transition: color 0.15s ease;
}
.modal-close:hover { color: #fff; }
.su-modal-body {
    padding: 20px 24px;
    overflow-y: auto !important;
    flex: 1 1 auto;
}
.su-modal-footer {
    padding: 14px 24px;
    border-top: 1px solid var(--sv-border);
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    background: #fafbfc;
    border-radius: 0 0 16px 16px;
    flex-shrink: 0;
}

.su-field-label {
    font-size: 12.5px;
    font-weight: 700;
    color: #334155;
    margin-bottom: 5px;
    display: block;
}
.su-hint-text {
    font-size: 11px;
    color: #94a3b8;
}
.su-clear-link {
    background: none;
    border: none;
    color: var(--sv-danger);
    font-size: 11px;
    cursor: pointer;
    font-weight: 600;
}

/* ── Chips & Presets ── */
.channel-chip-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.channel-chip {
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    color: #475569;
    padding: 7px 12px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
}
.channel-chip input[type="radio"] { display: none; }
.channel-chip:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
}
.channel-chip.active {
    background: var(--sv-primary);
    border-color: var(--sv-primary);
    color: #ffffff;
}

.su-preset-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.preset-tag {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #334155;
    padding: 4px 10px;
    border-radius: 14px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.preset-tag:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.preset-pill {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 600;
    padding: 5px 12px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.preset-pill:hover,
.preset-pill.active {
    background: var(--sv-primary);
    color: #ffffff;
    border-color: var(--sv-primary);
}

.su-datetime-input,
.su-date-input {
    font-size: 12.5px;
    border: 1px solid var(--sv-border);
    border-radius: 8px;
    padding: 6px 10px;
}

/* ── Vertical Timeline CSS ── */
.v-timeline {
    position: relative;
    padding-left: 28px;
}
.v-timeline::before {
    content: '';
    position: absolute;
    left: 11px;
    top: 6px;
    bottom: 6px;
    width: 2px;
    background: #e2e8f0;
}
.v-timeline-item {
    position: relative;
    margin-bottom: 18px;
}
.v-timeline-item:last-child { margin-bottom: 0; }
.v-timeline-dot {
    position: absolute;
    left: -28px;
    top: 2px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid var(--sv-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    color: var(--sv-primary);
    z-index: 1;
}
.v-timeline-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

/* ── Distribution Progress Bars ── */
.progress-bar-track {
    width: 100%;
    height: 8px;
    background: #f1f5f9;
    border-radius: 4px;
    overflow: hidden;
}
.progress-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

/* ── Canned Template Cards ── */
.template-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 10px;
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
    margin: 8px 0;
    background: #fff;
    padding: 9px 12px;
    border-radius: 6px;
    border: 1px dashed #cbd5e1;
}

/* ── Modern Toast Notifications ── */
.su-toast-container {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 10px;
    pointer-events: none;
}
.su-toast {
    background: #ffffff;
    border-radius: 10px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15), 0 8px 10px -6px rgba(0,0,0,0.05);
    border: 1px solid #e2e8f0;
    padding: 12px 16px;
    min-width: 280px;
    max-width: 380px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    pointer-events: auto;
    animation: toastSlideIn 0.25s ease-out;
    transition: all 0.2s ease;
}
@keyframes toastSlideIn {
    from { transform: translateX(40px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
.su-toast-success { border-left: 4px solid var(--sv-success); }
.su-toast-error   { border-left: 4px solid var(--sv-danger); }
.su-toast-warning { border-left: 4px solid var(--sv-warning); }
.su-toast-info    { border-left: 4px solid var(--sv-primary); }

.su-toast-icon {
    font-size: 16px;
    margin-top: 2px;
}
.su-toast-success .su-toast-icon { color: var(--sv-success); }
.su-toast-error .su-toast-icon   { color: var(--sv-danger); }
.su-toast-warning .su-toast-icon { color: var(--sv-warning); }
.su-toast-info .su-toast-icon    { color: var(--sv-primary); }

.su-toast-body { flex: 1; }
.su-toast-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--sv-dark);
}
.su-toast-message {
    font-size: 12px;
    color: var(--sv-slate);
    margin-top: 2px;
    line-height: 1.35;
}
.su-toast-close {
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 15px;
    cursor: pointer;
    line-height: 1;
    padding: 0;
}
.su-toast-close:hover { color: #334155; }
</style>

<script>
let lastKnownId        = 0;
let pollingTimer       = null;
let syncTimer          = null;
let isPollingPaused    = false;
let isAudioMuted       = false;
let radarItems         = [];
let scheduledItems     = [];
let currentTab         = 'radar';
let currentFilter      = 'pending';
let currentSchedFilter = 'all';
let currentSort        = 'urgency';
let searchQuery        = '';
let searchDebounce     = null;
let audioCtx           = null;

document.addEventListener('DOMContentLoaded', () => {
    checkNotificationPermission();
    fetchFullRadar(true);
    startPolling();
    loadSalesReps();

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'scheduled') {
        switchUnitTab('scheduled');
    } else if (urlParams.get('tab') === 'reporting') {
        switchUnitTab('reporting');
    }

    const hlId = urlParams.get('highlight');
    if (hlId) {
        setTimeout(() => {
            openFollowupModal(parseInt(hlId));
        }, 800);
    }

    window.addEventListener('globalSalesVisitArrived', () => {
        fetchFullRadar(false);
    });
});

// ── Tab Switching ──
function switchUnitTab(tabName) {
    currentTab = tabName;
    document.querySelectorAll('.unit-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.unit-tab-content').forEach(c => c.classList.remove('active'));

    if (tabName === 'radar') {
        document.getElementById('tabBtnRadar').classList.add('active');
        document.getElementById('tabContentRadar').classList.add('active');
        renderRadarTable();
    } else if (tabName === 'scheduled') {
        document.getElementById('tabBtnScheduled').classList.add('active');
        document.getElementById('tabContentScheduled').classList.add('active');
        loadScheduledFollowups();
    } else if (tabName === 'reporting') {
        document.getElementById('tabBtnReporting').classList.add('active');
        document.getElementById('tabContentReporting').classList.add('active');
        loadReportingData();
    }
}

// ── KPI Card Click to Filter ──
function handleKpiCardClick(filterKey) {
    if (filterKey === 'overdue' || filterKey === 'due_today') {
        switchUnitTab('scheduled');
        const pill = document.querySelector(`.subtab-pill-${filterKey === 'overdue' ? 'overdue' : 'due'}`);
        setScheduledFilter(filterKey, pill);
    } else {
        switchUnitTab('radar');
        setFilter(filterKey);
    }
}

function updateActiveKpiCard(activeKey) {
    document.querySelectorAll('.su-kpi-card').forEach(c => c.classList.remove('active-kpi'));
    if (activeKey === 'all') {
        const c = document.getElementById('kpiCardAll');
        if (c) c.classList.add('active-kpi');
    } else if (activeKey === 'overdue') {
        const c = document.getElementById('kpiCardOverdue');
        if (c) c.classList.add('active-kpi');
    } else if (activeKey === 'due_today') {
        const c = document.getElementById('kpiCardDueToday');
        if (c) c.classList.add('active-kpi');
    } else if (activeKey === 'converted') {
        const c = document.getElementById('kpiCardConverted');
        if (c) c.classList.add('active-kpi');
    }
}

// ── Smart WhatsApp Phone Formatter & Copier ──
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

function copyPhoneNumber(phone, e) {
    if (e) e.stopPropagation();
    if (!phone) return;
    navigator.clipboard.writeText(phone).then(() => {
        showToast(`Phone number ${phone} copied to clipboard!`, 'info', 'Number Copied');
    }).catch(() => {
        showToast(phone, 'info', 'Phone Number');
    });
}

// ── Real-Time Polling Engine with Pause / Resume ──
function startPolling() {
    if (pollingTimer) clearInterval(pollingTimer);
    pollingTimer = setInterval(() => {
        if (!isPollingPaused) {
            pollLatestVisits();
        }
    }, 3500);

    if (syncTimer) clearInterval(syncTimer);
    syncTimer = setInterval(() => {
        if (!isPollingPaused) {
            if (currentTab === 'radar') fetchFullRadar(false);
            else if (currentTab === 'scheduled') loadScheduledFollowups(false);
        }
    }, 12000);
}

function toggleRadarPolling() {
    isPollingPaused = !isPollingPaused;
    const badge = document.getElementById('radarPulseBadge');
    const btn = document.getElementById('pauseResumeBtn');

    if (isPollingPaused) {
        badge.classList.add('paused');
        badge.innerHTML = '<span class="live-status-dot"></span> Radar Paused';
        btn.innerHTML = '<i class="fas fa-play" style="color:#16a34a;"></i>';
        btn.title = 'Resume Live Polling';
        showToast('Live Radar paused. Table will not auto-refresh.', 'warning', 'Radar Paused');
    } else {
        badge.classList.remove('paused');
        badge.innerHTML = '<span class="live-status-dot"></span> Live Radar (3.5s)';
        btn.innerHTML = '<i class="fas fa-pause"></i>';
        btn.title = 'Pause Live Polling';
        showToast('Live Radar resumed. Scanning every 3.5 seconds.', 'success', 'Radar Active');
        fetchFullRadar(false);
    }
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
                    showToast(`New client visit arrived: ${newItems[0]?.company_name || 'Inbound'}`, 'info', 'New Radar Lead');
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
                <td colspan="7" class="su-loading-cell">
                    <div class="su-spinner-box">
                        <i class="fas fa-satellite-dish fa-spin su-primary-spin"></i>
                        <p>Scanning live radar...</p>
                    </div>
                </td>
            </tr>
        `;
    }

    const refreshIcon = document.getElementById('refreshRadarIcon');
    if (refreshIcon) refreshIcon.classList.add('fa-spin');

    fetch(`sales_visits_api.php?action=fetch_all&filter=${currentFilter}`)
        .then(r => r.json())
        .then(data => {
            if (refreshIcon) refreshIcon.classList.remove('fa-spin');
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
            } else {
                showToast(data.message || 'Error fetching radar data', 'error');
            }
        })
        .catch(err => {
            if (refreshIcon) refreshIcon.classList.remove('fa-spin');
            console.error('Radar fetch error:', err);
            showToast('Unable to connect to server. Checking connection...', 'error');
        });
}

function renderStats(stats) {
    document.getElementById('statTotal').textContent     = stats.total || 0;
    document.getElementById('statOverdue').textContent   = stats.overdue || 0;
    document.getElementById('statDueToday').textContent  = stats.due_today || 0;
    document.getElementById('statConverted').textContent = stats.converted || 0;

    const radarBadge = document.getElementById('radarCountBadge');
    if (radarBadge) radarBadge.textContent = stats.pending || 0;

    const schedBadge = document.getElementById('scheduledAlertBadge');
    if (schedBadge) {
        const schedCount = (parseInt(stats.overdue || 0) + parseInt(stats.due_today || 0));
        schedBadge.textContent = schedCount;
        schedBadge.style.display = schedCount > 0 ? 'inline-block' : 'none';
    }

    const sAll = document.getElementById('schedCountAll');
    if (sAll) sAll.textContent = (parseInt(stats.overdue || 0) + parseInt(stats.due_today || 0) + parseInt(stats.upcoming || 0));
    const sOver = document.getElementById('schedCountOverdue');
    if (sOver) sOver.textContent = stats.overdue || 0;
    const sDue = document.getElementById('schedCountDue');
    if (sDue) sDue.textContent = stats.due_today || 0;
    const sUp = document.getElementById('schedCountUpcoming');
    if (sUp) sUp.textContent = stats.upcoming || 0;
}

function setFilter(val) {
    currentFilter = val;
    document.querySelectorAll('.su-quick-pills .su-pill').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-filter') === val);
    });
    updateActiveKpiCard(val);
    fetchFullRadar(true);
}

function setSort(val) {
    currentSort = val;
    renderRadarTable();
}

function handleSearch(val) {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        searchQuery = val.trim().toLowerCase();
        const clearBtn = document.getElementById('searchClearBtn');
        if (clearBtn) clearBtn.style.display = searchQuery ? 'block' : 'none';
        renderRadarTable();
    }, 180);
}

function clearSearch() {
    const input = document.getElementById('searchInput');
    if (input) input.value = '';
    searchQuery = '';
    const clearBtn = document.getElementById('searchClearBtn');
    if (clearBtn) clearBtn.style.display = 'none';
    renderRadarTable();
}

// ── Render Radar Table (Tab 1) ──
function renderRadarTable() {
    const container = document.getElementById('radarTableBody');
    let items = [...radarItems];

    if (searchQuery) {
        items = items.filter(v =>
            (v.client_name && v.client_name.toLowerCase().includes(searchQuery)) ||
            (v.company_name && v.company_name.toLowerCase().includes(searchQuery)) ||
            (v.phone && v.phone.includes(searchQuery)) ||
            (v.categories && v.categories.toLowerCase().includes(searchQuery)) ||
            (v.remarks && v.remarks.toLowerCase().includes(searchQuery)) ||
            (v.admin_notes && v.admin_notes.toLowerCase().includes(searchQuery))
        );
    }

    if (currentSort === 'urgency') {
        items.sort((a, b) => {
            if (b.is_urgent !== a.is_urgent) return b.is_urgent - a.is_urgent;
            const rank = { 'hot': 3, 'warm': 2, 'cold': 1 };
            return (rank[b.interest_level] || 0) - (rank[a.interest_level] || 0);
        });
    } else if (currentSort === 'followup_due') {
        items.sort((a, b) => {
            if (!a.next_followup_date && !b.next_followup_date) return 0;
            if (!a.next_followup_date) return 1;
            if (!b.next_followup_date) return -1;
            return new Date(a.next_followup_date) - new Date(b.next_followup_date);
        });
    } else if (currentSort === 'followup_cnt') {
        items.sort((a, b) => parseInt(b.followup_count || 0) - parseInt(a.followup_count || 0));
    } else if (currentSort === 'interest') {
        items.sort((a, b) => {
            const rank = { 'hot': 3, 'warm': 2, 'cold': 1 };
            return (rank[b.interest_level] || 0) - (rank[a.interest_level] || 0);
        });
    } else if (currentSort === 'contact_time') {
        items.sort((a, b) => (a.contact_availability || '').localeCompare(b.contact_availability || ''));
    } else if (currentSort === 'newest') {
        items.sort((a, b) => parseInt(b.id) - parseInt(a.id));
    }

    const countBadge = document.getElementById('visibleCount');
    if (countBadge) countBadge.textContent = items.length;

    if (items.length === 0) {
        container.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center; padding:60px 24px; color:var(--sv-slate);">
                    <i class="fas fa-check-circle" style="font-size:32px; color:#cbd5e1; margin-bottom:12px;"></i>
                    <p style="margin:0; font-size:14px; font-weight:700; color:#334155;">No field visits found matching this filter.</p>
                    <small style="color:#94a3b8; display:block; margin-top:4px;">Radar is active and listening for new field visits...</small>
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
        const waMsg      = encodeURIComponent(`Hello ${v.client_name}, this is Graphicafix management following up regarding your discussion for ${v.company_name}. Are you available for a quick 5-minute call?`);

        let priorityBadge = '';
        if (v.interest_level === 'hot') {
            priorityBadge = '<span class="badge-priority badge-hot"><i class="fas fa-fire" style="font-size:9px;"></i> Hot</span>';
        } else if (v.interest_level === 'warm') {
            priorityBadge = '<span class="badge-priority badge-warm"><i class="fas fa-bolt" style="font-size:9px;"></i> Warm</span>';
        } else {
            priorityBadge = '<span class="badge-priority badge-cold"><i class="far fa-snowflake" style="font-size:9px;"></i> Standard</span>';
        }

        let stageBadge = '';
        if (v.status === 'converted') {
            stageBadge = '<span class="badge-stage badge-stage-converted"><i class="fas fa-check-circle" style="font-size:10px;"></i> Converted</span>';
        } else if (v.status === 'in_discussion') {
            stageBadge = '<span class="badge-stage badge-stage-discussion"><i class="fas fa-comments" style="font-size:10px;"></i> In Discussion</span>';
        } else if (v.status === 'contacted') {
            stageBadge = '<span class="badge-stage badge-stage-contacted"><i class="fas fa-phone-alt" style="font-size:10px;"></i> Contacted</span>';
        } else if (v.status === 'closed') {
            stageBadge = '<span class="badge-stage badge-stage-closed"><i class="fas fa-archive" style="font-size:10px;"></i> Closed</span>';
        } else {
            stageBadge = '<span class="badge-stage badge-stage-pending"><i class="fas fa-hourglass-start" style="font-size:10px;"></i> Pending Contact</span>';
        }

        const fCount = parseInt(v.followup_count || 0);
        let scheduleBadge = '';
        if (v.followup_status === 'overdue') {
            scheduleBadge = `<span class="fo-schedule-badge fo-overdue"><i class="fas fa-exclamation-circle"></i> Overdue (${v.formatted_next_followup || 'Missed'})</span>`;
        } else if (v.followup_status === 'due_today') {
            scheduleBadge = `<span class="fo-schedule-badge fo-duetoday"><i class="fas fa-clock"></i> Due Today (${v.formatted_next_followup ? v.formatted_next_followup.split(' ').slice(-2).join(' ') : 'Today'})</span>`;
        } else if (v.followup_status === 'upcoming') {
            scheduleBadge = `<span class="fo-schedule-badge fo-upcoming"><i class="far fa-calendar-alt"></i> ${v.formatted_next_followup}</span>`;
        } else if (v.status === 'converted' || v.status === 'closed') {
            scheduleBadge = `<span class="fo-schedule-badge fo-completed"><i class="fas fa-check"></i> Deal Resolved</span>`;
        } else {
            scheduleBadge = `<span class="fo-schedule-badge fo-none"><i class="far fa-calendar"></i> No follow-up set</span>`;
        }

        let outcomeHtml = '';
        if (v.last_outcome) {
            const outClean = v.last_outcome.toLowerCase().replace(/[^a-z]/g, '');
            const outLabel = escapeHtml(v.last_outcome.replace(/_/g, ' '));
            outcomeHtml = `<span class="outcome-pill outcome-pill-${outClean}" style="text-transform:capitalize;">${outLabel}</span>`;
        }

        let reachOutHtml = '';
        if (cleanWa) {
            reachOutHtml = `
                <div style="display:flex; flex-direction:column; gap:3px; align-items:flex-start;">
                    <a href="https://wa.me/${cleanWa}?text=${waMsg}" target="_blank" class="su-wa-pill" title="Open WhatsApp Chat">
                        <i class="fab fa-whatsapp"></i>
                        <span>WhatsApp</span>
                    </a>
                    <div style="display:inline-flex; align-items:center; gap:3px;">
                        <a href="tel:${escapeHtml(v.phone)}" class="su-phone-link" title="Call directly">
                            <i class="fas fa-phone-alt" style="font-size:10px;"></i> ${escapeHtml(v.phone)}
                        </a>
                        <button type="button" class="su-copy-phone-btn" onclick="copyPhoneNumber('${escapeHtml(v.phone)}', event)" title="Copy phone number">
                            <i class="far fa-copy"></i>
                        </button>
                    </div>
                </div>
            `;
        } else {
            reachOutHtml = `
                <div style="display:inline-flex; align-items:center; gap:3px;">
                    <a href="tel:${escapeHtml(v.phone)}" class="su-phone-link" style="font-weight:600; color:#334155;">
                        <i class="fas fa-phone-alt" style="font-size:11px;"></i> ${escapeHtml(v.phone)}
                    </a>
                    <button type="button" class="su-copy-phone-btn" onclick="copyPhoneNumber('${escapeHtml(v.phone)}', event)" title="Copy phone number">
                        <i class="far fa-copy"></i>
                    </button>
                </div>
            `;
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

        const trStyle = isUrgent
            ? 'background:#fffbfb; border-left:4px solid #ef4444;'
            : (v.followup_status === 'overdue' ? 'background:#fff9f9; border-left:3px solid #fca5a5;' : '');

        html += `
            <tr style="${trStyle}">
                <td>
                    <div style="font-weight:700; color:var(--sv-dark); font-size:13.5px; display:inline-flex; align-items:center;">
                        ${escapeHtml(v.company_name)}
                        ${isUrgent ? '<span class="badge-urgent-tag">Urgent</span>' : ''}
                    </div>
                    <div style="font-size:12px; color:var(--sv-slate); margin-top:2px;">
                        <i class="fas fa-user" style="font-size:10px; color:#94a3b8; margin-right:4px;"></i>
                        ${escapeHtml(v.client_name)}
                    </div>
                </td>
                <td>
                    ${reachOutHtml}
                </td>
                <td>
                    <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                        ${stageBadge}
                        ${priorityBadge}
                    </div>
                </td>
                <td>
                    <div style="display:flex; flex-direction:column; gap:3px; align-items:flex-start;">
                        ${scheduleBadge}
                        <div style="display:flex; align-items:center; gap:6px; margin-top:2px;">
                            <span style="font-size:11.5px; font-weight:700; color:#475569;">${fCount} Touchpoint${fCount===1?'':'s'}</span>
                            ${outcomeHtml}
                        </div>
                        ${v.last_followup_by ? `<small style="font-size:11px; color:#94a3b8;">Last: ${v.last_followup_ago || 'Recent'} by ${escapeHtml(v.last_followup_by)}</small>` : ''}
                    </div>
                </td>
                <td>
                    <div style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:3px;">
                        ${catBadges}
                    </div>
                    <small style="color:var(--sv-slate); font-size:11.5px; display:block;">
                        <i class="far fa-clock" style="font-size:10px;"></i> ${escapeHtml(v.contact_availability)}
                    </small>
                </td>
                <td>
                    <div style="font-size:12px; color:var(--sv-slate);">
                        <div style="font-weight:600; color:#334155;">${escapeHtml(v.sales_rep_name)}</div>
                        <small style="color:#94a3b8;">${v.time_ago || ''}</small>
                    </div>
                </td>
                <td style="text-align:right;">
                    <div class="su-actions-cell">
                        <button class="su-btn-followup" onclick="openFollowupModal(${v.id})" title="Log Follow-up Interaction">
                            <i class="fas fa-plus"></i> Follow-Up
                        </button>
                        <button class="su-icon-btn" onclick="openTimelineModal(${v.id})" title="View Complete Interaction History">
                            <i class="fas fa-history" style="color:#6d28d9;"></i>
                        </button>
                        <button class="su-icon-btn" onclick="openDetailModal(${v.id})" title="Response Workspace & Rapid Templates">
                            <i class="fas fa-headset" style="color:var(--sv-primary);"></i>
                        </button>
                        <button class="su-icon-btn ${isUrgent ? 'active-urgent' : ''}" onclick="toggleUrgent(${v.id})" title="${isUrgent ? 'Remove Urgent Priority' : 'Mark as Urgent'}">
                            <i class="fas fa-bell"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    container.innerHTML = html;
}

// ── Tab 2: Scheduled Follow-ups Pipeline ──
function loadScheduledFollowups(showLoading = true) {
    const container = document.getElementById('scheduledTableBody');
    if (showLoading) {
        container.innerHTML = `
            <tr>
                <td colspan="7" class="su-loading-cell">
                    <div class="su-spinner-box">
                        <i class="fas fa-spinner fa-spin su-primary-spin"></i>
                        <p>Loading scheduled follow-up pipeline...</p>
                    </div>
                </td>
            </tr>
        `;
    }

    fetch(`sales_visits_api.php?action=fetch_all&filter=scheduled&sort=followup_due`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                scheduledItems = data.items || [];
                renderScheduledTable();
            } else {
                showToast(data.message || 'Error loading scheduled follow-ups', 'error');
            }
        })
        .catch(err => {
            console.error('Scheduled load error:', err);
            showToast('Failed to load scheduled pipeline. Check connection.', 'error');
        });
}

function setScheduledFilter(filter, btn) {
    currentSchedFilter = filter;
    document.querySelectorAll('.scheduled-filter-chips .subtab-pill').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    renderScheduledTable();
}

function renderScheduledTable() {
    const container = document.getElementById('scheduledTableBody');
    let items = [...scheduledItems];

    if (currentSchedFilter === 'overdue') {
        items = items.filter(v => v.followup_status === 'overdue');
    } else if (currentSchedFilter === 'due_today') {
        items = items.filter(v => v.followup_status === 'due_today');
    } else if (currentSchedFilter === 'upcoming') {
        items = items.filter(v => v.followup_status === 'upcoming');
    }

    if (items.length === 0) {
        container.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center; padding:50px 20px; color:var(--sv-slate);">
                    <i class="far fa-calendar-check" style="font-size:32px; color:#cbd5e1; margin-bottom:10px;"></i>
                    <p style="margin:0; font-size:14px; font-weight:700; color:#475569;">No follow-ups scheduled under this filter.</p>
                    <small style="color:#94a3b8; display:block; margin-top:4px;">All scheduled touches are up to date.</small>
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    items.forEach(v => {
        let scheduleBadge = '';
        if (v.followup_status === 'overdue') {
            scheduleBadge = `<span class="fo-schedule-badge fo-overdue"><i class="fas fa-exclamation-triangle"></i> OVERDUE: ${v.formatted_next_followup}</span>`;
        } else if (v.followup_status === 'due_today') {
            scheduleBadge = `<span class="fo-schedule-badge fo-duetoday"><i class="fas fa-clock"></i> TODAY: ${v.formatted_next_followup}</span>`;
        } else {
            scheduleBadge = `<span class="fo-schedule-badge fo-upcoming"><i class="far fa-calendar-alt"></i> ${v.formatted_next_followup}</span>`;
        }

        const rawWa = (v.whatsapp || v.phone || '').trim();
        const cleanWa = formatWhatsAppNumber(rawWa);
        const fCount = parseInt(v.followup_count || 0);

        html += `
            <tr style="${v.followup_status === 'overdue' ? 'background:#fff8f8; border-left:4px solid #ef4444;' : ''}">
                <td>
                    <strong style="color:var(--sv-dark); font-size:13.5px;">${escapeHtml(v.company_name)}</strong>
                    <div style="font-size:12px; color:var(--sv-slate); margin-top:2px;">
                        <i class="fas fa-user" style="font-size:10px; color:#94a3b8;"></i> ${escapeHtml(v.client_name)}
                    </div>
                </td>
                <td>
                    ${scheduleBadge}
                </td>
                <td>
                    <div style="display:inline-flex; align-items:center; gap:4px;">
                        <span style="font-size:12.5px; font-weight:600; color:#334155;">${escapeHtml(v.phone)}</span>
                        <button type="button" class="su-copy-phone-btn" onclick="copyPhoneNumber('${escapeHtml(v.phone)}', event)" title="Copy phone number">
                            <i class="far fa-copy"></i>
                        </button>
                    </div>
                    ${cleanWa ? `
                        <div>
                            <a href="https://wa.me/${cleanWa}" target="_blank" style="font-size:11.5px; color:#16a34a; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:3px;">
                                <i class="fab fa-whatsapp"></i> Chat WhatsApp
                            </a>
                        </div>
                    ` : ''}
                </td>
                <td>
                    <span style="font-size:12px; font-weight:700; color:var(--sv-dark);">${fCount} Touchpoint${fCount===1?'':'s'}</span>
                    <button type="button" onclick="openTimelineModal(${v.id})" style="background:none; border:none; color:#6d28d9; font-size:11.5px; cursor:pointer; font-weight:600; display:block; padding:0; margin-top:2px;">
                        View History &rarr;
                    </button>
                </td>
                <td>
                    <div style="font-size:12px; color:#475569;">
                        ${v.last_outcome ? `<span class="outcome-pill outcome-pill-${v.last_outcome.toLowerCase()}">${escapeHtml(v.last_outcome.replace(/_/g, ' '))}</span>` : '<span style="color:#94a3b8;">Initial Visit Log</span>'}
                        ${v.last_followup_by ? `<div style="font-size:11px; color:#94a3b8; margin-top:2px;">by ${escapeHtml(v.last_followup_by)}</div>` : ''}
                    </div>
                </td>
                <td>
                    <div style="font-size:12px; font-weight:600; color:#334155;">${escapeHtml(v.sales_rep_name)}</div>
                </td>
                <td style="text-align:right;">
                    <button class="su-btn-followup" onclick="openFollowupModal(${v.id})" style="padding:6px 12px; font-size:12px;">
                        <i class="fas fa-check"></i> Complete / Reschedule
                    </button>
                </td>
            </tr>
        `;
    });

    container.innerHTML = html;
}

// ── Tab 3: Performance & Reporting Logic ──
function handleReportPresetChange(val) {
    const wrap = document.getElementById('customDateRangeWrap');
    wrap.style.display = (val === 'custom') ? 'inline-flex' : 'none';
    if (val !== 'custom') {
        loadReportingData();
    }
}

function loadReportingData() {
    const preset = document.getElementById('reportDatePreset').value;
    const repId  = document.getElementById('reportRepSelect').value;
    let url = `sales_visits_api.php?action=get_reporting_metrics&date_range=${preset}&rep_id=${repId}`;

    if (preset === 'custom') {
        const s = document.getElementById('reportStartDate').value;
        const e = document.getElementById('reportEndDate').value;
        url += `&start_date=${s}&end_date=${e}`;
    }

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderReportingDashboard(data);
                showToast('Executive performance reporting updated', 'info');
            } else {
                showToast(data.message || 'Error generating report', 'error');
            }
        })
        .catch(err => {
            console.error('Reporting error:', err);
            showToast('Unable to fetch reporting metrics', 'error');
        });
}

function renderReportingDashboard(data) {
    const k = data.kpis || {};
    document.getElementById('repKpiVisits').textContent     = k.total_visits || 0;
    document.getElementById('repKpiFollowups').textContent  = k.total_followups || 0;
    document.getElementById('repKpiAvgF').textContent       = k.avg_followups || 0;
    document.getElementById('repKpiEngagement').textContent = (k.engagement_rate || 0) + '%';
    document.getElementById('repKpiConverted').textContent  = k.converted_visits || 0;
    document.getElementById('repKpiWinRate').textContent    = (k.conversion_rate || 0) + '%';
    document.getElementById('repKpiOverdue').textContent    = k.overdue_count || 0;

    const lbBody = document.getElementById('leaderboardTableBody');
    const reps = data.leaderboard || [];
    if (reps.length === 0) {
        lbBody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">No activity recorded for this period.</td></tr>`;
    } else {
        let lbHtml = '';
        reps.forEach((r, idx) => {
            const medal = (idx === 0) ? '🥇 ' : (idx === 1 ? '🥈 ' : (idx === 2 ? '🥉 ' : ''));
            lbHtml += `
                <tr>
                    <td>
                        <strong style="color:var(--sv-dark); font-size:13px;">${medal}${escapeHtml(r.sales_rep_name)}</strong>
                    </td>
                    <td style="text-align:center; font-weight:700;">${r.total_visits}</td>
                    <td style="text-align:center; font-weight:700; color:#0284c7;">${r.total_followups || 0}</td>
                    <td style="text-align:center; font-weight:700; color:#16a34a;">${r.converted_count}</td>
                    <td style="text-align:center;">
                        <span style="background:#dcfce7; color:#15803d; padding:2px 8px; border-radius:4px; font-weight:700; font-size:11.5px;">
                            ${r.win_rate}%
                        </span>
                    </td>
                    <td style="text-align:right; font-size:11.5px; color:var(--sv-slate);">${r.last_activity}</td>
                </tr>
            `;
        });
        lbBody.innerHTML = lbHtml;
    }

    const outWrap = document.getElementById('outcomeDistributionWrap');
    const outcomes = data.outcomes || [];
    if (outcomes.length === 0) {
        outWrap.innerHTML = `<div style="color:#94a3b8; font-size:12px;">No follow-up outcomes recorded yet.</div>`;
    } else {
        let outHtml = '';
        outcomes.forEach(o => {
            outHtml += `
                <div>
                    <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">
                        <span style="font-weight:600; color:#334155;">${escapeHtml(o.label)}</span>
                        <span style="color:var(--sv-slate);"><strong>${o.count}</strong> (${o.percent}%)</span>
                    </div>
                    <div class="progress-bar-track">
                        <div class="progress-bar-fill" style="width:${o.percent}%; background:var(--sv-primary);"></div>
                    </div>
                </div>
            `;
        });
        outWrap.innerHTML = outHtml;
    }

    const chWrap = document.getElementById('channelDistributionWrap');
    const channels = data.channels || [];
    if (channels.length === 0) {
        chWrap.innerHTML = `<div style="color:#94a3b8; font-size:12px;">No channels recorded yet.</div>`;
    } else {
        let chHtml = '';
        channels.forEach(c => {
            chHtml += `
                <div>
                    <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">
                        <span style="font-weight:600; color:#334155;">${escapeHtml(c.label)}</span>
                        <span style="color:var(--sv-slate);"><strong>${c.count}</strong> (${c.percent}%)</span>
                    </div>
                    <div class="progress-bar-track">
                        <div class="progress-bar-fill" style="width:${c.percent}%; background:#0284c7;"></div>
                    </div>
                </div>
            `;
        });
        chWrap.innerHTML = chHtml;
    }
}

function exportReportingCSV(type) {
    const preset = document.getElementById('reportDatePreset').value;
    const repId  = document.getElementById('reportRepSelect').value;
    const params = new URLSearchParams({
        action: 'export_sheet',
        export_type: type,
        rep_id: repId
    });

    if (preset === 'custom') {
        params.append('start_date', document.getElementById('reportStartDate').value);
        params.append('end_date', document.getElementById('reportEndDate').value);
    } else if (preset !== 'all') {
        params.append('preset', preset);
    }

    showToast('Preparing spreadsheet download...', 'info');
    window.location.href = `sales_visits_api.php?${params.toString()}`;
}

// ── Follow-Up Modal Actions ──
function openFollowupModal(visitId) {
    const v = radarItems.find(item => item.id == visitId) || scheduledItems.find(item => item.id == visitId);

    document.getElementById('fModalVisitId').value = visitId;
    if (v) {
        document.getElementById('fModalCompanyTitle').textContent = `Log Follow-Up: ${v.company_name}`;
        document.getElementById('fModalClientSub').textContent = `Client: ${v.client_name} · Phone: ${v.phone} · Stage: ${v.status}`;
        document.getElementById('fModalStatus').value = (v.status === 'pending') ? 'in_discussion' : v.status;
    } else {
        document.getElementById('fModalCompanyTitle').textContent = `Log Follow-Up Record (Visit #${visitId})`;
    }

    document.getElementById('fModalNotes').value = '';
    document.getElementById('fModalNextDate').value = '';
    document.getElementById('fModalOutcome').value = 'followup_needed';

    document.querySelectorAll('#followupModal .channel-chip').forEach(c => c.classList.remove('active'));
    const callChip = document.querySelector('#followupModal input[value="call"]');
    if (callChip) {
        callChip.checked = true;
        callChip.closest('.channel-chip').classList.add('active');
    }

    document.getElementById('followupModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeFollowupModal() {
    document.getElementById('followupModal').classList.remove('active');
    document.body.style.overflow = '';
}

function updateChannelChipUI(inputEl) {
    const group = inputEl.closest('.channel-chip-group') || inputEl.closest('div');
    group.querySelectorAll('.channel-chip').forEach(c => c.classList.remove('active'));
    inputEl.closest('.channel-chip').classList.add('active');
}

function appendFollowupNote(text) {
    const ta = document.getElementById('fModalNotes');
    ta.value = ta.value ? `${ta.value.trim()} ${text}` : text;
    ta.focus();
}

function handleOutcomeChange(val) {
    const stSel = document.getElementById('fModalStatus');
    if (val === 'converted') {
        stSel.value = 'converted';
    } else if (val === 'not_interested' || val === 'closed') {
        stSel.value = 'closed';
    } else if (stSel.value === 'pending') {
        stSel.value = 'in_discussion';
    }
}

function setNextDatePreset(preset) {
    const input = document.getElementById('fModalNextDate');
    const now = new Date();

    if (preset === 'tomorrow_am') {
        now.setDate(now.getDate() + 1);
        now.setHours(11, 0, 0, 0);
    } else if (preset === 'tomorrow_pm') {
        now.setDate(now.getDate() + 1);
        now.setHours(16, 0, 0, 0);
    } else if (preset === 'in_2_days') {
        now.setDate(now.getDate() + 2);
        now.setHours(15, 0, 0, 0);
    } else if (preset === 'next_week') {
        const day = now.getDay();
        const diff = (day === 0 ? 1 : 8 - day);
        now.setDate(now.getDate() + diff);
        now.setHours(11, 0, 0, 0);
    }

    const yr = now.getFullYear();
    const mo = String(now.getMonth() + 1).padStart(2, '0');
    const da = String(now.getDate()).padStart(2, '0');
    const hr = String(now.getHours()).padStart(2, '0');
    const mi = String(now.getMinutes()).padStart(2, '0');
    input.value = `${yr}-${mo}-${da}T${hr}:${mi}`;
}

function clearNextFollowupDate() {
    document.getElementById('fModalNextDate').value = '';
}

function submitFollowupRecord(e) {
    e.preventDefault();
    const btn = document.getElementById('fModalSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const fd = new FormData(document.getElementById('followupForm'));
    fd.append('action', 'add_followup');

    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> <span>Save Follow-Up Record</span>';

            if (data.success) {
                closeFollowupModal();
                showToast(data.message || 'Follow-up interaction recorded successfully!', 'success');
                fetchFullRadar(false);
                if (currentTab === 'scheduled') loadScheduledFollowups(false);
                else if (currentTab === 'reporting') loadReportingData();
            } else {
                showToast(data.message || 'Error saving follow-up record', 'error');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> <span>Save Follow-Up Record</span>';
            showToast('Failed to save follow-up. Please check your connection.', 'error');
        });
}

// ── Follow-Up Timeline Modal ──
function openTimelineModal(visitId) {
    document.getElementById('tModalTimelineBody').innerHTML = `
        <div style="text-align:center; padding:40px; color:var(--sv-slate);">
            <i class="fas fa-spinner fa-spin su-primary-spin" style="margin-bottom:10px;"></i>
            <p style="margin:0;">Loading full interaction history...</p>
        </div>
    `;
    document.getElementById('timelineModal').classList.add('active');
    document.body.style.overflow = 'hidden';

    fetch(`sales_visits_api.php?action=get_followups&visit_id=${visitId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderTimelineModalContent(data.visit, data.followups || []);
            } else {
                document.getElementById('tModalTimelineBody').innerHTML = `<p style="color:#ef4444; padding:20px;">${data.message}</p>`;
                showToast(data.message || 'Failed to load timeline', 'error');
            }
        })
        .catch(err => {
            document.getElementById('tModalTimelineBody').innerHTML = `<p style="color:#ef4444; padding:20px;">Connection failed.</p>`;
        });
}

function renderTimelineModalContent(visit, followups) {
    document.getElementById('tModalCompany').textContent = visit.company_name;
    document.getElementById('tModalSub').textContent = `Client: ${visit.client_name} · Logged by ${visit.sales_rep_name}`;

    document.getElementById('tModalAddBtn').onclick = () => {
        closeTimelineModal();
        openFollowupModal(visit.id);
    };

    const rawWa = (visit.whatsapp || visit.phone || '').trim();
    const cleanWa = formatWhatsAppNumber(rawWa);
    document.getElementById('tModalDirectWa').innerHTML = cleanWa ? `
        <a href="https://wa.me/${cleanWa}" target="_blank" class="su-wa-pill">
            <i class="fab fa-whatsapp"></i> WhatsApp Chat
        </a>
    ` : '';

    const badgeWrap = document.getElementById('tModalQuickBadges');
    badgeWrap.innerHTML = `
        <span class="badge-stage badge-stage-${visit.status}">${visit.status.replace(/_/g, ' ').toUpperCase()}</span>
        <span class="badge-priority badge-${visit.interest_level}">${visit.interest_level.toUpperCase()}</span>
        <span style="font-size:12px; font-weight:700; color:#475569;">${followups.length} Recorded Touchpoint${followups.length===1?'':'s'}</span>
    `;

    const container = document.getElementById('tModalTimelineBody');

    let html = '<div class="v-timeline">';

    if (followups.length === 0) {
        html += `
            <div style="background:#fff; border:1px solid var(--sv-border); border-radius:10px; padding:18px; margin-bottom:16px; text-align:center;">
                <p style="margin:0; font-size:13px; font-weight:700; color:var(--sv-dark);">No follow-up touchpoints recorded yet.</p>
                <small style="color:var(--sv-slate); display:block; margin-top:3px;">Click "+ Log New Follow-Up" above to add the first interaction.</small>
            </div>
        `;
    } else {
        followups.forEach(f => {
            const chIcon = matchChannelIcon(f.contact_channel);
            html += `
                <div class="v-timeline-item">
                    <div class="v-timeline-dot">${chIcon}</div>
                    <div class="v-timeline-card">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
                            <div>
                                <strong style="font-size:13px; color:var(--sv-dark);">${escapeHtml(f.channel_label)}</strong>
                                <span class="outcome-pill outcome-pill-${f.outcome.toLowerCase()}" style="margin-left:6px;">${escapeHtml(f.outcome_label)}</span>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:11.5px; font-weight:600; color:var(--sv-slate);">${f.formatted_date}</div>
                                <small style="color:#94a3b8; font-size:10.5px;">${f.time_ago}</small>
                            </div>
                        </div>

                        <div style="font-size:12.5px; color:#334155; line-height:1.45; background:#f8fafc; padding:8px 10px; border-radius:6px; border:1px solid #f1f5f9; margin-bottom:6px;">
                            ${escapeHtml(f.notes)}
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:11px; color:var(--sv-slate);">
                            <span>Logged by: <strong>${escapeHtml(f.user_name)}</strong></span>
                            ${f.formatted_next_date ? `
                                <span style="color:var(--sv-primary); font-weight:700;">
                                    <i class="far fa-calendar-alt"></i> Next: ${f.formatted_next_date}
                                </span>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
    }

    html += `
        <div class="v-timeline-item">
            <div class="v-timeline-dot" style="border-color:#16a34a; color:#16a34a;"><i class="fas fa-flag"></i></div>
            <div class="v-timeline-card" style="border-left:3.5px solid #16a34a;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <strong style="font-size:13px; color:var(--sv-dark);"><i class="fas fa-street-view" style="color:#16a34a;"></i> Field Visit Logged</strong>
                    <span style="font-size:11.5px; color:var(--sv-slate);">${visit.time_ago || ''}</span>
                </div>
                <div style="font-size:12px; color:#475569; margin-bottom:4px;">
                    By <strong>${escapeHtml(visit.sales_rep_name)}</strong> · Availability: <strong>${escapeHtml(visit.contact_availability)}</strong>
                </div>
                ${visit.remarks ? `
                    <div style="font-size:12px; color:#334155; background:#f8fafc; padding:6px 8px; border-radius:4px; border:1px dashed #cbd5e1;">
                        ${escapeHtml(visit.remarks)}
                    </div>
                ` : ''}
            </div>
        </div>
    `;

    html += '</div>';
    container.innerHTML = html;
}

function closeTimelineModal() {
    document.getElementById('timelineModal').classList.remove('active');
    document.body.style.overflow = '';
}

function matchChannelIcon(ch) {
    if (ch === 'whatsapp') return '<i class="fab fa-whatsapp"></i>';
    if (ch === 'meeting') return '<i class="fas fa-handshake"></i>';
    if (ch === 'email') return '<i class="fas fa-envelope"></i>';
    if (ch === 'office_visit') return '<i class="fas fa-building"></i>';
    return '<i class="fas fa-phone-alt"></i>';
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
                showToast(data.message || 'Urgent priority toggled', 'success');
                fetchFullRadar(false);
            } else {
                showToast(data.message || 'Failed to toggle urgent flag', 'error');
            }
        })
        .catch(err => showToast('Network error while toggling urgent priority', 'error'));
}

// ── Response Workspace Modal (Rapid WhatsApp Templates) ──
function openDetailModal(id) {
    const v = radarItems.find(item => item.id == id) || scheduledItems.find(item => item.id == id);
    if (!v) return;

    const rawWa   = (v.whatsapp || v.phone || '').trim();
    const cleanWa = formatWhatsAppNumber(rawWa);

    document.getElementById('detailModalTitle').textContent = v.company_name;
    document.getElementById('detailModalSub').textContent = `Client: ${v.client_name} · Logged by ${v.sales_rep_name}`;

    let cats = (v.categories_arr && v.categories_arr.length) ? v.categories_arr.join(', ') : 'None';

    const tpl1 = `Hello ${v.client_name}, this is Graphicafix management following up on your discussion with our team member (${v.sales_rep_name}) regarding ${v.company_name}. We would like to connect and assist you. When is a convenient time for a quick 5-minute call?`;
    const tpl2 = `Hello ${v.client_name}, regarding your interest in ${cats !== 'None' ? cats : 'our creative services'} for ${v.company_name}, our team at Graphicafix has prepared initial details for you. Would you like us to share our portfolio and package options here?`;

    const encTpl1 = encodeURIComponent(tpl1);
    const encTpl2 = encodeURIComponent(tpl2);

    document.getElementById('detailModalBody').innerHTML = `
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:16px;">
            <div style="background:#f8fafc; padding:12px 14px; border-radius:10px; border:1px solid #e2e8f0;">
                <div style="color:var(--sv-slate); font-size:11px; font-weight:700; text-transform:uppercase;">Client & Phone</div>
                <div style="font-weight:700; color:var(--sv-dark); font-size:13.5px; margin-top:2px;">${escapeHtml(v.client_name)}</div>
                <div style="font-size:12px; color:#475569; margin-top:2px; display:flex; align-items:center; gap:4px;">
                    <span>${escapeHtml(v.phone)}</span>
                    <button type="button" class="su-copy-phone-btn" onclick="copyPhoneNumber('${escapeHtml(v.phone)}', event)" title="Copy phone number">
                        <i class="far fa-copy"></i>
                    </button>
                </div>
            </div>
            <div style="background:#f8fafc; padding:12px 14px; border-radius:10px; border:1px solid #e2e8f0;">
                <div style="color:var(--sv-slate); font-size:11px; font-weight:700; text-transform:uppercase;">Availability Window</div>
                <div style="font-weight:700; color:var(--sv-dark); font-size:13.5px; margin-top:2px;">${escapeHtml(v.contact_availability)}</div>
                <div style="font-size:11.5px; color:var(--sv-slate); margin-top:2px;">Logged: ${v.time_ago || 'Recently'}</div>
            </div>
        </div>

        <div style="margin-bottom:16px;">
            <div style="font-size:12.5px; font-weight:700; color:var(--sv-dark); margin-bottom:8px;">Rapid WhatsApp Templates</div>
            <div class="template-card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <strong style="font-size:12px; color:var(--sv-dark);">1. Official Follow-up & Greeting</strong>
                    <div style="display:flex; gap:6px;">
                        <button type="button" class="btn btn-secondary-custom su-btn-pill" style="padding:4px 9px; font-size:11px;" onclick="copyTemplateText('${escapeJsString(tpl1)}', this)">
                            <i class="far fa-copy"></i> Copy
                        </button>
                        ${cleanWa ? `
                            <a href="https://wa.me/${cleanWa}?text=${encTpl1}" target="_blank" class="su-wa-pill" style="padding:4px 10px; font-size:11.5px;">
                                <i class="fab fa-whatsapp"></i> Send
                            </a>
                        ` : ''}
                    </div>
                </div>
                <div class="template-text">${escapeHtml(tpl1)}</div>
            </div>

            <div class="template-card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <strong style="font-size:12px; color:var(--sv-dark);">2. Services Portfolio & Packages</strong>
                    <div style="display:flex; gap:6px;">
                        <button type="button" class="btn btn-secondary-custom su-btn-pill" style="padding:4px 9px; font-size:11px;" onclick="copyTemplateText('${escapeJsString(tpl2)}', this)">
                            <i class="far fa-copy"></i> Copy
                        </button>
                        ${cleanWa ? `
                            <a href="https://wa.me/${cleanWa}?text=${encTpl2}" target="_blank" class="su-wa-pill" style="padding:4px 10px; font-size:11.5px;">
                                <i class="fab fa-whatsapp"></i> Send
                            </a>
                        ` : ''}
                    </div>
                </div>
                <div class="template-text">${escapeHtml(tpl2)}</div>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button type="button" class="btn btn-primary-custom su-btn-pill" onclick="closeDetailModal(); openFollowupModal(${v.id});">
                <i class="fas fa-phone-volume"></i> Record Interaction Now
            </button>
        </div>
    `;

    document.getElementById('detailWaWrap').innerHTML = cleanWa ? `
        <a href="https://wa.me/${cleanWa}?text=${encodeURIComponent(`Hello ${v.client_name}, this is Graphicafix management reaching out regarding ${v.company_name}.`)}" target="_blank" class="su-wa-pill">
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
        showToast('Template copied to clipboard!', 'success', 'Copied');
        setTimeout(() => { btn.innerHTML = orig; }, 2000);
    });
}

// ── Export Sheets Logic ──
function loadSalesReps() {
    fetch('sales_visits_api.php?action=get_reps')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.reps) {
                const selExp = document.getElementById('exportRepSelect');
                const selRep = document.getElementById('reportRepSelect');

                data.reps.forEach(rep => {
                    const opt = `<option value="${rep.id}">${escapeHtml(rep.name)} (${rep.role})</option>`;
                    if (selExp) selExp.innerHTML += opt;
                    if (selRep) selRep.innerHTML += opt;
                });
            }
        });
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
    document.querySelectorAll('#exportModal .preset-pill').forEach(el => el.classList.remove('active'));
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
    const type      = document.querySelector('input[name="export_type"]:checked').value;
    const repId     = document.getElementById('exportRepSelect').value;
    const startDate = document.getElementById('exportStartDate').value;
    const endDate   = document.getElementById('exportEndDate').value;

    const params = new URLSearchParams({
        action: 'export_sheet',
        export_type: type,
        rep_id: repId,
        start_date: startDate,
        end_date: endDate
    });

    showToast('Generating export spreadsheet...', 'info');
    window.location.href = `sales_visits_api.php?${params.toString()}`;
    closeExportModal();
}

// ── Web Audio Chime Synthesis & Mute Toggle ──
function toggleAudioMute() {
    isAudioMuted = !isAudioMuted;
    const btn = document.getElementById('audioMuteBtn');
    if (isAudioMuted) {
        btn.innerHTML = '<i class="fas fa-volume-mute" style="color:#ef4444;"></i>';
        btn.title = 'Notification Audio: Muted';
        showToast('Notification audio chime muted', 'warning', 'Audio Muted');
    } else {
        btn.innerHTML = '<i class="fas fa-volume-up"></i>';
        btn.title = 'Notification Audio: Active';
        showToast('Notification audio chime active', 'info', 'Audio Active');
        playNotificationChime();
    }
}

function playNotificationChime() {
    if (isAudioMuted) return;
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
            btn.innerHTML = '<i class="fas fa-check-circle" style="color:#16a34a;"></i> <span>Alerts Active</span>';
            btn.disabled = true;
        }
    }
}

function enablePushNotifications() {
    if (!('Notification' in window)) {
        showToast('Browser does not support notifications.', 'warning');
        return;
    }
    Notification.requestPermission().then(permission => {
        checkNotificationPermission();
        if (permission === 'granted') {
            showToast('Desktop push notifications enabled!', 'success', 'Alerts Active');
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
            openFollowupModal(v.id);
        };
    }
}

// ── Toast Notification Framework ──
function showToast(message, type = 'info', title = '') {
    const container = document.getElementById('suToastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `su-toast su-toast-${type}`;

    let icon = 'fas fa-info-circle';
    if (type === 'success') icon = 'fas fa-check-circle';
    else if (type === 'error') icon = 'fas fa-exclamation-circle';
    else if (type === 'warning') icon = 'fas fa-exclamation-triangle';

    let displayTitle = title;
    if (!displayTitle) {
        if (type === 'success') displayTitle = 'Success';
        else if (type === 'error') displayTitle = 'Error';
        else if (type === 'warning') displayTitle = 'Warning';
        else displayTitle = 'Notice';
    }

    toast.innerHTML = `
        <div class="su-toast-icon"><i class="${icon}"></i></div>
        <div class="su-toast-body">
            <div class="su-toast-title">${escapeHtml(displayTitle)}</div>
            <div class="su-toast-message">${escapeHtml(message)}</div>
        </div>
        <button class="su-toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(40px)';
        setTimeout(() => toast.remove(), 250);
    }, 4500);
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