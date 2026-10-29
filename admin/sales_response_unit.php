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

    <!-- ── Universal Universal Stats Row ── -->
    <div class="stats-row" style="margin-bottom:20px;">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Inbound Visits</span>
                <div class="stat-icon" style="background:#f1f5f9;color:#0f172a;"><i class="fas fa-street-view"></i></div>
            </div>
            <div class="stat-number" id="statTotal">--</div>
            <div style="font-size:11.5px; color:#64748b; margin-top:4px;">Total field visits logged</div>
        </div>
        <div class="stat-card" style="border-left: 3.5px solid #ef4444;">
            <div class="stat-header">
                <span class="stat-label">Overdue Follow-ups</span>
                <div class="stat-icon" style="background:#fee2e2;color:#ef4444;"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
            <div class="stat-number" id="statOverdue" style="color:#ef4444;">--</div>
            <div style="font-size:11.5px; color:#ef4444; margin-top:4px; font-weight:600;">Requires immediate attention</div>
        </div>
        <div class="stat-card" style="border-left: 3.5px solid #d97706;">
            <div class="stat-header">
                <span class="stat-label">Due Today</span>
                <div class="stat-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-clock"></i></div>
            </div>
            <div class="stat-number" id="statDueToday" style="color:#d97706;">--</div>
            <div style="font-size:11.5px; color:#b45309; margin-top:4px;">Scheduled touchpoints for today</div>
        </div>
        <div class="stat-card" style="border-left: 3.5px solid #16a34a;">
            <div class="stat-header">
                <span class="stat-label">Converted / Won</span>
                <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="stat-number" id="statConverted" style="color:#16a34a;">--</div>
            <div style="font-size:11.5px; color:#16a34a; margin-top:4px;">Converted to CRM leads</div>
        </div>
    </div>

    <!-- ── Main Navigation Tabs ── -->
    <div class="unit-tabs-nav" style="display:flex; gap:8px; margin-bottom:16px; border-bottom:1px solid #e2e8f0; padding-bottom:2px;">
        <button type="button" class="unit-tab-btn active" id="tabBtnRadar" onclick="switchUnitTab('radar')">
            <i class="fas fa-satellite-dish"></i>
            <span>Follow-Up & Response Radar</span>
            <span class="unit-tab-badge" id="radarCountBadge">0</span>
        </button>
        <button type="button" class="unit-tab-btn" id="tabBtnScheduled" onclick="switchUnitTab('scheduled')">
            <i class="far fa-calendar-check"></i>
            <span>Scheduled & Due Follow-ups</span>
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
        <div class="main-card">
            <div class="main-header" style="flex-wrap:wrap; gap:12px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <h3 style="margin:0; font-size:17px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-headset" style="color:var(--primary);"></i> Sales Response & Follow-up Radar
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
                            <option value="pending" selected>Pending Initial Contact</option>
                            <option value="in_discussion">In Discussion</option>
                            <option value="due_today">⏰ Due Today</option>
                            <option value="overdue">🚨 Overdue Follow-ups</option>
                            <option value="urgent">🚨 Urgent Flagged Only</option>
                            <option value="hot">🔥 High Priority (Hot)</option>
                            <option value="all">All Inbound Visits</option>
                            <option value="contacted">Contacted</option>
                            <option value="converted">Converted to CRM</option>
                            <option value="closed">Closed / Archived</option>
                        </select>
                    </div>

                    <!-- Sort By Dropdown -->
                    <div style="display:flex; align-items:center; gap:6px;">
                        <span style="font-size:12.5px; font-weight:600; color:#64748b;">Sort:</span>
                        <select id="sortSelect" onchange="setSort(this.value)" class="form-select" style="width:auto; padding:5px 12px; font-size:12.5px; height:34px;">
                            <option value="urgency">Urgency & Priority First</option>
                            <option value="followup_due">Next Follow-up Due Date</option>
                            <option value="followup_cnt">Most Follow-ups Completed</option>
                            <option value="interest">Interest Level (High to Low)</option>
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
                <table class="data-table" style="width:100%; border-collapse:collapse; min-width:960px;">
                    <thead>
                        <tr>
                            <th style="width:20%;">Client & Company</th>
                            <th style="width:14%;">Direct Connect</th>
                            <th style="width:12%;">Stage & Priority</th>
                            <th style="width:20%;">Follow-Up Track Record</th>
                            <th style="width:14%;">Requirements</th>
                            <th style="width:10%;">Sales Rep</th>
                            <th style="width:10%; text-align:right;">Actions</th>
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

    <!-- ══════════════════════════════════════════════════════════════════════════
         TAB 2: SCHEDULED & DUE FOLLOW-UPS
         ══════════════════════════════════════════════════════════════════════════ -->
    <div id="tabContentScheduled" class="unit-tab-content">
        <div class="main-card">
            <div class="main-header" style="flex-wrap:wrap; gap:12px;">
                <div>
                    <h3 style="margin:0; font-size:17px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                        <i class="far fa-calendar-check" style="color:var(--primary);"></i> Scheduled Follow-Up Pipeline
                    </h3>
                    <p style="margin:2px 0 0; font-size:12px; color:#64748b;">
                        Track upcoming client touchpoints, prevent missed follow-ups, and ensure zero dropped opportunities.
                    </p>
                </div>

                <div style="display:flex; gap:8px; align-items:center;">
                    <div class="scheduled-filter-chips">
                        <button type="button" class="subtab-pill active" onclick="setScheduledFilter('all', this)">All Scheduled</button>
                        <button type="button" class="subtab-pill" onclick="setScheduledFilter('overdue', this)">🚨 Overdue</button>
                        <button type="button" class="subtab-pill" onclick="setScheduledFilter('due_today', this)">⏰ Due Today</button>
                        <button type="button" class="subtab-pill" onclick="setScheduledFilter('upcoming', this)">📅 Upcoming</button>
                    </div>
                </div>
            </div>

            <!-- Scheduled Table -->
            <div style="overflow-x:auto;">
                <table class="data-table" style="width:100%; border-collapse:collapse; min-width:960px;">
                    <thead>
                        <tr>
                            <th style="width:20%;">Client & Company</th>
                            <th style="width:16%;">Follow-Up Due Time</th>
                            <th style="width:14%;">Contact Details</th>
                            <th style="width:16%;">History & Count</th>
                            <th style="width:14%;">Last Touch Outcome</th>
                            <th style="width:10%;">Sales Rep</th>
                            <th style="width:10%; text-align:right;">Quick Action</th>
                        </tr>
                    </thead>
                    <tbody id="scheduledTableBody">
                        <tr>
                            <td colspan="7" style="text-align:center; padding:40px; color:#888;">
                                <i class="fas fa-spinner fa-spin" style="font-size:20px; margin-bottom:8px; color:var(--primary);"></i>
                                <p style="margin:0;">Loading scheduled follow-ups...</p>
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
        <div class="main-card" style="margin-bottom:20px; padding:16px 20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
                <div>
                    <h3 style="margin:0; font-size:16px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-file-invoice" style="color:var(--primary);"></i> Sales Follow-up & Conversion Reporting
                    </h3>
                    <p style="margin:2px 0 0; font-size:12px; color:#64748b;">
                        Filter by date range and sales representative to generate executive performance analytics.
                    </p>
                </div>

                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <!-- Date Presets -->
                    <select id="reportDatePreset" onchange="handleReportPresetChange(this.value)" class="form-select" style="font-size:12.5px; height:36px; padding:6px 12px;">
                        <option value="all">All Time</option>
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="week">Last 7 Days</option>
                        <option value="month" selected>This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="custom">Custom Date Range</option>
                    </select>

                    <div id="customDateRangeWrap" style="display:none; gap:6px; align-items:center;">
                        <input type="date" id="reportStartDate" class="form-control" style="font-size:12px; height:36px; padding:4px 8px;">
                        <span style="color:#64748b; font-size:12px;">to</span>
                        <input type="date" id="reportEndDate" class="form-control" style="font-size:12px; height:36px; padding:4px 8px;">
                    </div>

                    <!-- Rep Filter -->
                    <select id="reportRepSelect" class="form-select" style="font-size:12.5px; height:36px; padding:6px 12px; max-width:200px;">
                        <option value="0">All Sales Reps</option>
                    </select>

                    <button type="button" onclick="loadReportingData()" class="btn btn-primary-custom" style="height:36px; padding:0 14px; font-size:12.5px; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                        <i class="fas fa-sync-alt"></i> Apply Filter
                    </button>

                    <button type="button" onclick="exportReportingCSV('visits')" class="btn btn-secondary-custom" style="height:36px; padding:0 12px; font-size:12.5px; font-weight:600; display:inline-flex; align-items:center; gap:6px;" title="Export Master Pipeline CSV">
                        <i class="fas fa-file-excel" style="color:#16a34a;"></i> Export Pipeline
                    </button>
                    <button type="button" onclick="exportReportingCSV('followup_history')" class="btn btn-secondary-custom" style="height:36px; padding:0 12px; font-size:12.5px; font-weight:600; display:inline-flex; align-items:center; gap:6px;" title="Export Detailed Touchpoints Trail">
                        <i class="fas fa-history" style="color:#0284c7;"></i> Export Follow-ups Log
                    </button>
                    <button type="button" onclick="window.print()" class="btn btn-secondary-custom" style="height:36px; padding:0 12px; font-size:12.5px;" title="Print Report">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Reporting KPI Summary Cards -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin-bottom:20px;">
            <div class="stat-card" style="padding:16px;">
                <div style="font-size:11.5px; font-weight:700; color:#64748b; text-transform:uppercase;">Visits Analyzed</div>
                <div style="font-size:24px; font-weight:800; color:#0f172a; margin-top:4px;" id="repKpiVisits">--</div>
                <div style="font-size:11.5px; color:#64748b; margin-top:2px;" id="repKpiVisitsSub">In selected period</div>
            </div>
            <div class="stat-card" style="padding:16px;">
                <div style="font-size:11.5px; font-weight:700; color:#64748b; text-transform:uppercase;">Follow-ups Logged</div>
                <div style="font-size:24px; font-weight:800; color:#0284c7; margin-top:4px;" id="repKpiFollowups">--</div>
                <div style="font-size:11.5px; color:#64748b; margin-top:2px;"><span id="repKpiAvgF">0</span> avg per visit</div>
            </div>
            <div class="stat-card" style="padding:16px;">
                <div style="font-size:11.5px; font-weight:700; color:#64748b; text-transform:uppercase;">Engagement Rate</div>
                <div style="font-size:24px; font-weight:800; color:#8b5cf6; margin-top:4px;" id="repKpiEngagement">--%</div>
                <div style="font-size:11.5px; color:#64748b; margin-top:2px;">Visits with >= 1 follow-up</div>
            </div>
            <div class="stat-card" style="padding:16px; border-left:3.5px solid #16a34a;">
                <div style="font-size:11.5px; font-weight:700; color:#64748b; text-transform:uppercase;">Converted Leads</div>
                <div style="font-size:24px; font-weight:800; color:#16a34a; margin-top:4px;" id="repKpiConverted">--</div>
                <div style="font-size:11.5px; color:#16a34a; font-weight:600; margin-top:2px;"><span id="repKpiWinRate">0%</span> conversion rate</div>
            </div>
            <div class="stat-card" style="padding:16px; border-left:3.5px solid #ef4444;">
                <div style="font-size:11.5px; font-weight:700; color:#64748b; text-transform:uppercase;">Overdue Follow-ups</div>
                <div style="font-size:24px; font-weight:800; color:#ef4444; margin-top:4px;" id="repKpiOverdue">--</div>
                <div style="font-size:11.5px; color:#ef4444; font-weight:600; margin-top:2px;">Missed scheduled calls</div>
            </div>
        </div>

        <!-- Reporting Grid: Leaderboard & Distribution Breakdown -->
        <div style="display:grid; grid-template-columns:1.8fr 1.2fr; gap:16px; margin-bottom:20px;">
            
            <!-- Sales Rep Performance Leaderboard -->
            <div class="main-card">
                <div class="main-header" style="padding:14px 18px;">
                    <h4 style="margin:0; font-size:14px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-trophy" style="color:#eab308;"></i> Sales Rep Follow-Up & Conversion Leaderboard
                    </h4>
                </div>
                <div style="overflow-x:auto;">
                    <table class="data-table" style="width:100%;">
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
                <div class="main-card" style="padding:16px 18px;">
                    <h4 style="margin:0 0 12px; font-size:14px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-pie-chart" style="color:var(--primary);"></i> Follow-Up Outcomes Distribution
                    </h4>
                    <div id="outcomeDistributionWrap" style="display:flex; flex-direction:column; gap:10px;">
                        <div style="color:#94a3b8; font-size:12px;">Loading outcomes...</div>
                    </div>
                </div>

                <!-- Contact Channels Used -->
                <div class="main-card" style="padding:16px 18px;">
                    <h4 style="margin:0 0 12px; font-size:14px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
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
     MODAL 1: LOG STRUCTURED FOLLOW-UP TRACK RECORD
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="followupModal" class="modal-overlay" onclick="if(event.target===this)closeFollowupModal()">
    <div class="modal-content" style="max-width:640px; padding:0; overflow:hidden;">
        <div class="modal-header" style="padding:16px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0;">
            <div style="display:flex; align-items:center; gap:8px;">
                <div class="modal-header-icon" style="background:#e0f2fe; color:#0284c7; width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-phone-volume"></i>
                </div>
                <div>
                    <h4 id="fModalCompanyTitle" style="margin:0; font-size:15px; font-weight:700; color:#0f172a;">Log Follow-Up Track Record</h4>
                    <span id="fModalClientSub" style="font-size:12px; color:#64748b;">Record interaction details and schedule next touchpoint</span>
                </div>
            </div>
            <button class="modal-close" onclick="closeFollowupModal()" style="font-size:20px; color:#94a3b8; background:none; border:none; cursor:pointer;">×</button>
        </div>

        <form id="followupForm" onsubmit="submitFollowupRecord(event)" style="padding:20px; max-height:80vh; overflow-y:auto;">
            <input type="hidden" name="visit_id" id="fModalVisitId" value="">

            <!-- Contact Channels Selector -->
            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-size:12.5px; font-weight:700; color:#334155; margin-bottom:6px; display:block;">
                    Contact Channel Used <span style="color:#ef4444;">*</span>
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
                        <i class="fas fa-handshake"></i> In-Person Meeting
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
                    <label style="font-size:12.5px; font-weight:700; color:#334155; margin-bottom:5px; display:block;">
                        Interaction Outcome <span style="color:#ef4444;">*</span>
                    </label>
                    <select name="outcome" id="fModalOutcome" class="form-select" required style="font-size:13px; padding:7px 10px;" onchange="handleOutcomeChange(this.value)">
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
                    <label style="font-size:12.5px; font-weight:700; color:#334155; margin-bottom:5px; display:block;">
                        Update Pipeline Stage
                    </label>
                    <select name="status" id="fModalStatus" class="form-select" style="font-size:13px; padding:7px 10px;">
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
                    <label style="font-size:12.5px; font-weight:700; color:#334155; margin:0;">
                        Follow-up Discussion Notes <span style="color:#ef4444;">*</span>
                    </label>
                    <span style="font-size:11px; color:#64748b;">Be concise & specific</span>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:5px; margin-bottom:6px;">
                    <button type="button" class="preset-tag" onclick="appendFollowupNote('Called client. Discussed scope of work.')">+ Discussed scope</button>
                    <button type="button" class="preset-tag" onclick="appendFollowupNote('Shared catalog and price quotation via WhatsApp.')">+ Sent Quotation</button>
                    <button type="button" class="preset-tag" onclick="appendFollowupNote('Client needs 2 days to consult with partner.')">+ Partner decision</button>
                    <button type="button" class="preset-tag" onclick="appendFollowupNote('Client agreed to meeting at their office.')">+ Office meeting</button>
                    <button type="button" class="preset-tag" onclick="appendFollowupNote('No answer, sent follow-up text.')">+ No response</button>
                </div>
                <textarea name="notes" id="fModalNotes" rows="3" required placeholder="What was discussed? Client feedback? Any pricing or timeline agreements made?" class="form-control" style="font-size:12.5px; resize:vertical;"></textarea>
            </div>

            <!-- Schedule Next Follow-up -->
            <div class="form-group" style="margin-bottom:18px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label style="font-size:12.5px; font-weight:700; color:#0f172a; margin:0;">
                        <i class="far fa-calendar-alt" style="color:var(--primary); margin-right:4px;"></i> Schedule Next Follow-Up Date & Time
                    </label>
                    <button type="button" onclick="clearNextFollowupDate()" style="background:none; border:none; color:#ef4444; font-size:11px; cursor:pointer; font-weight:600;">
                        Clear Schedule
                    </button>
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px;">
                    <button type="button" class="preset-tag" onclick="setNextDatePreset('tomorrow_am')">Tomorrow Morning (11:00 AM)</button>
                    <button type="button" class="preset-tag" onclick="setNextDatePreset('tomorrow_pm')">Tomorrow Afternoon (4:00 PM)</button>
                    <button type="button" class="preset-tag" onclick="setNextDatePreset('in_2_days')">In 2 Days</button>
                    <button type="button" class="preset-tag" onclick="setNextDatePreset('next_week')">Next Week Monday</button>
                </div>

                <input type="datetime-local" name="next_followup_date" id="fModalNextDate" class="form-control" style="font-size:12.5px; max-width:280px; padding:6px 10px;">
                <small style="font-size:11px; color:#64748b; margin-top:4px; display:block;">
                    Setting a date will automatically appear on the Due Today and Overdue alerts when the time comes.
                </small>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid #f1f5f9; padding-top:14px;">
                <button type="button" class="btn btn-secondary-custom" onclick="closeFollowupModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="fModalSubmitBtn" style="display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-check"></i> Save Follow-Up Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     MODAL 2: COMPLETE FOLLOW-UP TIMELINE & HISTORY
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="timelineModal" class="modal-overlay" onclick="if(event.target===this)closeTimelineModal()">
    <div class="modal-content" style="max-width:700px; padding:0; overflow:hidden;">
        <div class="modal-header" style="padding:16px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0;">
            <div style="display:flex; align-items:center; gap:8px;">
                <div class="modal-header-icon" style="background:#ede9fe; color:#6d28d9; width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-history"></i>
                </div>
                <div>
                    <h4 id="tModalCompany" style="margin:0; font-size:15px; font-weight:700; color:#0f172a;">Follow-Up Track Record Timeline</h4>
                    <span id="tModalSub" style="font-size:12px; color:#64748b;">Chronological interaction history</span>
                </div>
            </div>
            <button class="modal-close" onclick="closeTimelineModal()" style="font-size:20px; color:#94a3b8; background:none; border:none; cursor:pointer;">×</button>
        </div>

        <div style="padding:12px 20px; background:#fff; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div id="tModalQuickBadges" style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;"></div>
            <button type="button" id="tModalAddBtn" class="btn btn-primary-custom" style="padding:5px 12px; font-size:12px; font-weight:600; display:inline-flex; align-items:center; gap:5px;">
                <i class="fas fa-plus"></i> Log New Follow-Up
            </button>
        </div>

        <div class="modal-body" id="tModalTimelineBody" style="padding:20px; max-height:65vh; overflow-y:auto; background:#fafbfc;">
            <!-- Rendered dynamically -->
        </div>

        <div class="modal-footer" style="padding:12px 20px; background:#f8fafc; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <div id="tModalDirectWa"></div>
            <button type="button" class="btn btn-secondary-custom" onclick="closeTimelineModal()">Close Timeline</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     MODAL 3: RESPONSE WORKSPACE & CANNED WHATSAPP TEMPLATES
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
     MODAL 4: EXPORT SHEETS MODAL (Visits / Follow-up Logs)
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="exportModal" class="modal-overlay" onclick="if(event.target===this)closeExportModal()">
    <div class="modal-content" style="max-width:540px; padding:0; overflow:hidden;">
        <div class="modal-header" style="padding:16px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0;">
            <div style="display:flex; align-items:center; gap:8px;">
                <div class="modal-header-icon" style="background:#dcfce7; color:#16a34a; width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-file-excel"></i>
                </div>
                <div>
                    <h4 style="margin:0; font-size:15px; font-weight:700; color:#0f172a;">Export Field Visits & Follow-up Sheets</h4>
                    <span style="font-size:12px; color:#64748b;">Download formatted spreadsheet (Excel & CSV compatible)</span>
                </div>
            </div>
            <button class="modal-close" onclick="closeExportModal()" style="font-size:20px; color:#94a3b8; background:none; border:none; cursor:pointer;">×</button>
        </div>

        <form id="exportForm" onsubmit="executeExport(event)" style="padding:20px;">
            <!-- Export Type -->
            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-size:12.5px; font-weight:700; color:#334155; margin-bottom:5px; display:block;">
                    Select Report Type
                </label>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                    <label class="channel-chip active" style="justify-content:center;">
                        <input type="radio" name="export_type" value="visits" checked onchange="updateChannelChipUI(this)">
                        <i class="fas fa-table"></i> Master Visits & Status
                    </label>
                    <label class="channel-chip" style="justify-content:center;">
                        <input type="radio" name="export_type" value="followup_history" onchange="updateChannelChipUI(this)">
                        <i class="fas fa-history"></i> Detailed Follow-ups Trail
                    </label>
                </div>
            </div>

            <!-- Filter by Sales Rep -->
            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-size:12.5px; font-weight:700; color:#334155; margin-bottom:5px; display:block;">
                    Sales Representative
                </label>
                <select id="exportRepSelect" class="form-select" style="width:100%; font-size:13px; padding:7px 12px;">
                    <option value="0">All Representatives (Company-wide)</option>
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
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                <div class="form-group">
                    <label style="font-size:12px; font-weight:600; color:#64748b; margin-bottom:4px; display:block;">From Date</label>
                    <input type="date" id="exportStartDate" class="form-control" style="font-size:12.5px; padding:6px 10px;">
                </div>
                <div class="form-group">
                    <label style="font-size:12px; font-weight:600; color:#64748b; margin-bottom:4px; display:block;">To Date</label>
                    <input type="date" id="exportEndDate" class="form-control" style="font-size:12.5px; padding:6px 10px;">
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
/* ── Clean Universal Navigation Tabs ── */
.unit-tab-btn {
    background: transparent;
    border: none;
    outline: none;
    padding: 10px 16px;
    font-size: 13.5px;
    font-weight: 600;
    color: #64748b;
    border-radius: 8px 8px 0 0;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.15s ease;
    border-bottom: 2.5px solid transparent;
}
.unit-tab-btn:hover {
    color: #0f172a;
    background: #f8fafc;
}
.unit-tab-btn.active {
    color: var(--primary, #024442);
    border-bottom: 2.5px solid var(--primary, #024442);
    background: #fff;
    font-weight: 700;
}
.unit-tab-badge {
    background: #e2e8f0;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 12px;
}
.unit-tab-badge.alert-badge {
    background: #fee2e2;
    color: #b91c1c;
}
.unit-tab-content {
    display: none;
}
.unit-tab-content.active {
    display: block;
}

/* ── Table Layout ── */
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

/* ── Direct WhatsApp Reach Out Button ── */
.btn-wa-direct {
    background: #16a34a !important;
    color: #ffffff !important;
    font-weight: 600;
    font-size: 12px;
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
.btn-wa-direct:hover {
    background: #15803d !important;
    color: #ffffff !important;
    box-shadow: 0 2px 5px rgba(22, 163, 74, 0.3);
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

.badge-stage {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}
.badge-stage-pending    { background: #fef3c7; color: #92400e; }
.badge-stage-contacted  { background: #e0f2fe; color: #0369a1; }
.badge-stage-discussion { background: #ede9fe; color: #6d28d9; }
.badge-stage-converted  { background: #dcfce7; color: #15803d; }
.badge-stage-closed     { background: #f1f5f9; color: #64748b; }

/* ── Follow-Up Track Record Badges ── */
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

/* ── Channel Selector Chips in Modal ── */
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
.channel-chip input[type="radio"] {
    display: none;
}
.channel-chip:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
}
.channel-chip.active {
    background: var(--primary, #024442);
    border-color: var(--primary, #024442);
    color: #ffffff;
}

/* ── Action Buttons ── */
.btn-track-action {
    background: #fff;
    border: 1px solid #cbd5e1;
    color: #334155;
    font-size: 11.5px;
    font-weight: 600;
    padding: 4px 9px;
    border-radius: 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.15s ease;
}
.btn-track-action:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
}
.btn-track-primary {
    background: var(--primary, #024442);
    border: 1px solid var(--primary, #024442);
    color: #ffffff;
}
.btn-track-primary:hover {
    background: #013331;
    color: #ffffff;
}

.dots {
    width: 28px;
    height: 28px;
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

.subtab-pill {
    background: #f8fafc;
    color: #475569;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    padding: 5px 12px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.subtab-pill:hover,
.subtab-pill.active {
    background: var(--primary, #024442);
    color: #ffffff;
    border-color: var(--primary, #024442);
}

.preset-tag {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #334155;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11.5px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.preset-tag:hover {
    background: #e2e8f0;
    color: #0f172a;
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
    margin-bottom: 20px;
}
.v-timeline-item:last-child {
    margin-bottom: 0;
}
.v-timeline-dot {
    position: absolute;
    left: -28px;
    top: 2px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid var(--primary, #024442);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    color: var(--primary, #024442);
    z-index: 1;
}
.v-timeline-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

/* ── Reporting Distribution Bars ── */
.progress-bar-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
}
.progress-bar-track {
    flex: 1;
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
let lastKnownId       = 0;
let pollingTimer      = null;
let syncTimer         = null;
let radarItems        = [];
let scheduledItems    = [];
let currentTab        = 'radar';
let currentFilter     = 'pending';
let currentSchedFilter = 'all';
let currentSort       = 'urgency';
let searchQuery       = '';
let audioCtx          = null;

document.addEventListener('DOMContentLoaded', () => {
    checkNotificationPermission();
    fetchFullRadar(true);
    startPolling();
    loadSalesReps();

    // Check URL params for highlight or tab
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

    // Listen to global sales visit events dispatched from dashboard_header.php
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

// ── Smart WhatsApp Phone Formatter ──
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

// ── Real-Time Polling Engine ──
function startPolling() {
    if (pollingTimer) clearInterval(pollingTimer);
    pollingTimer = setInterval(pollLatestVisits, 3500);

    if (syncTimer) clearInterval(syncTimer);
    syncTimer = setInterval(() => {
        if (currentTab === 'radar') fetchFullRadar(false);
        else if (currentTab === 'scheduled') loadScheduledFollowups(false);
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
    document.getElementById('statOverdue').textContent   = stats.overdue || 0;
    document.getElementById('statDueToday').textContent  = stats.due_today || 0;
    document.getElementById('statConverted').textContent = stats.converted || 0;

    // Badges on tabs
    const radarBadge = document.getElementById('radarCountBadge');
    if (radarBadge) radarBadge.textContent = stats.pending || 0;

    const schedBadge = document.getElementById('scheduledAlertBadge');
    if (schedBadge) {
        const schedCount = (parseInt(stats.overdue || 0) + parseInt(stats.due_today || 0));
        schedBadge.textContent = schedCount;
        schedBadge.style.display = schedCount > 0 ? 'inline-block' : 'none';
    }
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

    // Sort items
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
        const waMsg      = encodeURIComponent(`Hello ${v.client_name}, this is Graphicafix management following up regarding your discussion for ${v.company_name}. Are you available for a quick 5-minute call?`);

        // Priority badge
        let priorityBadge = '';
        if (v.interest_level === 'hot') {
            priorityBadge = '<span class="badge-priority badge-hot"><span class="badge-dot badge-dot-hot"></span>High</span>';
        } else if (v.interest_level === 'warm') {
            priorityBadge = '<span class="badge-priority badge-warm"><span class="badge-dot badge-dot-warm"></span>Medium</span>';
        } else {
            priorityBadge = '<span class="badge-priority badge-cold"><span class="badge-dot badge-dot-cold"></span>Standard</span>';
        }

        // Pipeline stage pill
        let stageBadge = '';
        if (v.status === 'converted') {
            stageBadge = '<span class="badge-stage badge-stage-converted">Converted to Lead</span>';
        } else if (v.status === 'in_discussion') {
            stageBadge = '<span class="badge-stage badge-stage-discussion">In Discussion</span>';
        } else if (v.status === 'contacted') {
            stageBadge = '<span class="badge-stage badge-stage-contacted">Contacted</span>';
        } else if (v.status === 'closed') {
            stageBadge = '<span class="badge-stage badge-stage-closed">Closed</span>';
        } else {
            stageBadge = '<span class="badge-stage badge-stage-pending">Pending Contact</span>';
        }

        // Follow-up Track Record summary
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
            scheduleBadge = `<span class="fo-schedule-badge fo-none">No follow-up set</span>`;
        }

        let outcomeHtml = '';
        if (v.last_outcome) {
            const outClean = v.last_outcome.toLowerCase().replace(/[^a-z]/g, '');
            const outLabel = escapeHtml(v.last_outcome.replace(/_/g, ' '));
            outcomeHtml = `<span class="outcome-pill outcome-pill-${outClean}" style="text-transform:capitalize;">${outLabel}</span>`;
        }

        // Reach out buttons
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

        // Requirements badges
        let catBadges = '';
        if (v.categories_arr && v.categories_arr.length > 0) {
            catBadges = v.categories_arr.slice(0, 2).map(c => `<span style="background:#e0f2fe; color:#0284c7; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600;">${escapeHtml(c)}</span>`).join(' ');
            if (v.categories_arr.length > 2) {
                catBadges += ` <span style="color:#64748b; font-size:11px;">+${v.categories_arr.length - 2}</span>`;
            }
        } else {
            catBadges = '<span style="color:#94a3b8; font-size:12px;">General</span>';
        }

        html += `
            <tr style="${isUrgent ? 'background:#fffbfb; border-left:4px solid #ef4444;' : (v.followup_status === 'overdue' ? 'background:#fffafa;' : '')}">
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
                    <small style="color:#64748b; font-size:11.5px; display:block;">
                        <i class="far fa-clock" style="font-size:10px;"></i> ${escapeHtml(v.contact_availability)}
                    </small>
                </td>
                <td>
                    <div style="font-size:12px; color:#64748b;">
                        <div style="font-weight:600; color:#334155;">${escapeHtml(v.sales_rep_name)}</div>
                        <small style="color:#94a3b8;">${v.time_ago || ''}</small>
                    </div>
                </td>
                <td style="text-align:right;">
                    <div style="display:inline-flex; gap:5px; justify-content:flex-end; align-items:center;">
                        <button class="btn-track-action btn-track-primary" onclick="openFollowupModal(${v.id})" title="Log Follow-up Interaction">
                            <i class="fas fa-plus"></i> Follow-Up
                        </button>
                        <button class="dots" onclick="openTimelineModal(${v.id})" title="View Complete Interaction History">
                            <i class="fas fa-history" style="color:#6d28d9;"></i>
                        </button>
                        <button class="dots" onclick="openDetailModal(${v.id})" title="Response Workspace & Templates">
                            <i class="fas fa-headset" style="color:var(--primary);"></i>
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

// ── Tab 2: Scheduled Follow-ups Pipeline ──
function loadScheduledFollowups(showLoading = true) {
    const container = document.getElementById('scheduledTableBody');
    if (showLoading) {
        container.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:40px; color:#888;"><i class="fas fa-spinner fa-spin"></i> Loading scheduled follow-ups...</td></tr>`;
    }

    fetch(`sales_visits_api.php?action=fetch_all&filter=scheduled&sort=followup_due`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                scheduledItems = data.items || [];
                renderScheduledTable();
            }
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
                <td colspan="7" style="text-align:center; padding:50px 20px; color:#888;">
                    <i class="far fa-calendar-check" style="font-size:28px; color:#cbd5e1; margin-bottom:10px;"></i>
                    <p style="margin:0; font-size:14px; font-weight:600; color:#475569;">No follow-ups scheduled under this filter.</p>
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
            <tr style="${v.followup_status === 'overdue' ? 'background:#fff5f5; border-left:4px solid #ef4444;' : ''}">
                <td>
                    <strong style="color:#0f172a; font-size:13.5px;">${escapeHtml(v.company_name)}</strong>
                    <div style="font-size:12px; color:#64748b; margin-top:2px;">
                        <i class="fas fa-user" style="font-size:10px; color:#94a3b8;"></i> ${escapeHtml(v.client_name)}
                    </div>
                </td>
                <td>
                    ${scheduleBadge}
                </td>
                <td>
                    <div style="font-size:12.5px; font-weight:600; color:#334155;">${escapeHtml(v.phone)}</div>
                    ${cleanWa ? `
                        <a href="https://wa.me/${cleanWa}" target="_blank" style="font-size:11.5px; color:#16a34a; text-decoration:none; display:inline-flex; align-items:center; gap:3px;">
                            <i class="fab fa-whatsapp"></i> Chat on WhatsApp
                        </a>
                    ` : ''}
                </td>
                <td>
                    <span style="font-size:12px; font-weight:700; color:#0f172a;">${fCount} Touchpoint${fCount===1?'':'s'}</span>
                    <button type="button" onclick="openTimelineModal(${v.id})" style="background:none; border:none; color:#6d28d9; font-size:11px; cursor:pointer; font-weight:600; display:block; padding:0; margin-top:2px;">
                        View History →
                    </button>
                </td>
                <td>
                    <div style="font-size:12px; color:#475569;">
                        ${v.last_outcome ? `<span class="outcome-pill outcome-pill-${v.last_outcome.toLowerCase()}">${escapeHtml(v.last_outcome.replace(/_/g, ' '))}</span>` : '<span style="color:#94a3b8;">Initial Log</span>'}
                        ${v.last_followup_by ? `<div style="font-size:11px; color:#94a3b8; margin-top:2px;">by ${escapeHtml(v.last_followup_by)}</div>` : ''}
                    </div>
                </td>
                <td>
                    <div style="font-size:12px; font-weight:600; color:#334155;">${escapeHtml(v.sales_rep_name)}</div>
                </td>
                <td style="text-align:right;">
                    <button class="btn-track-action btn-track-primary" onclick="openFollowupModal(${v.id})" style="padding:5px 12px; font-size:12px;">
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
            }
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

    // Leaderboard table
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
                        <strong style="color:#0f172a; font-size:13px;">${medal}${escapeHtml(r.sales_rep_name)}</strong>
                    </td>
                    <td style="text-align:center; font-weight:700;">${r.total_visits}</td>
                    <td style="text-align:center; font-weight:700; color:#0284c7;">${r.total_followups || 0}</td>
                    <td style="text-align:center; font-weight:700; color:#16a34a;">${r.converted_count}</td>
                    <td style="text-align:center;">
                        <span style="background:#dcfce7; color:#15803d; padding:2px 7px; border-radius:4px; font-weight:700; font-size:11.5px;">
                            ${r.win_rate}%
                        </span>
                    </td>
                    <td style="text-align:right; font-size:11.5px; color:#64748b;">${r.last_activity}</td>
                </tr>
            `;
        });
        lbBody.innerHTML = lbHtml;
    }

    // Outcome Distribution
    const outWrap = document.getElementById('outcomeDistributionWrap');
    const outcomes = data.outcomes || [];
    if (outcomes.length === 0) {
        outWrap.innerHTML = `<div style="color:#94a3b8; font-size:12px;">No follow-up outcomes recorded yet.</div>`;
    } else {
        let outHtml = '';
        outcomes.forEach(o => {
            outHtml += `
                <div>
                    <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:3px;">
                        <span style="font-weight:600; color:#334155;">${escapeHtml(o.label)}</span>
                        <span style="color:#64748b;"><strong>${o.count}</strong> (${o.percent}%)</span>
                    </div>
                    <div class="progress-bar-track">
                        <div class="progress-bar-fill" style="width:${o.percent}%; background:var(--primary, #024442);"></div>
                    </div>
                </div>
            `;
        });
        outWrap.innerHTML = outHtml;
    }

    // Channels Distribution
    const chWrap = document.getElementById('channelDistributionWrap');
    const channels = data.channels || [];
    if (channels.length === 0) {
        chWrap.innerHTML = `<div style="color:#94a3b8; font-size:12px;">No channels recorded yet.</div>`;
    } else {
        let chHtml = '';
        channels.forEach(c => {
            chHtml += `
                <div>
                    <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:3px;">
                        <span style="font-weight:600; color:#334155;">${escapeHtml(c.label)}</span>
                        <span style="color:#64748b;"><strong>${c.count}</strong> (${c.percent}%)</span>
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

    // Reset channel chips to call
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

    // Format YYYY-MM-DDTHH:MM
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
            btn.innerHTML = '<i class="fas fa-check"></i> Save Follow-Up Record';

            if (data.success) {
                closeFollowupModal();
                fetchFullRadar(false);
                if (currentTab === 'scheduled') loadScheduledFollowups(false);
                else if (currentTab === 'reporting') loadReportingData();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Save Follow-Up Record';
            alert('Failed to save follow-up. Please check connection.');
        });
}

// ── Follow-Up Timeline Modal ──
function openTimelineModal(visitId) {
    document.getElementById('tModalTimelineBody').innerHTML = `
        <div style="text-align:center; padding:40px; color:#888;">
            <i class="fas fa-spinner fa-spin" style="font-size:24px; color:var(--primary); margin-bottom:10px;"></i>
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
            }
        });
}

function renderTimelineModalContent(visit, followups) {
    document.getElementById('tModalCompany').textContent = visit.company_name;
    document.getElementById('tModalSub').textContent = `Client: ${visit.client_name} · Logged by ${visit.sales_rep_name}`;
    
    // Quick add button in timeline
    document.getElementById('tModalAddBtn').onclick = () => {
        closeTimelineModal();
        openFollowupModal(visit.id);
    };

    // WhatsApp Direct
    const rawWa = (visit.whatsapp || visit.phone || '').trim();
    const cleanWa = formatWhatsAppNumber(rawWa);
    document.getElementById('tModalDirectWa').innerHTML = cleanWa ? `
        <a href="https://wa.me/${cleanWa}" target="_blank" class="btn-wa-direct">
            <i class="fab fa-whatsapp"></i> WhatsApp Chat
        </a>
    ` : '';

    // Badges
    const badgeWrap = document.getElementById('tModalQuickBadges');
    badgeWrap.innerHTML = `
        <span class="badge-stage badge-stage-${visit.status}">${visit.status.replace(/_/g, ' ').toUpperCase()}</span>
        <span class="badge-priority badge-${visit.interest_level}">${visit.interest_level.toUpperCase()}</span>
        <span style="font-size:12px; font-weight:700; color:#475569;">${followups.length} Recorded Touchpoint${followups.length===1?'':'s'}</span>
    `;

    const container = document.getElementById('tModalTimelineBody');

    let html = '<div class="v-timeline">';

    // Render followups
    if (followups.length === 0) {
        html += `
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin-bottom:16px; text-align:center;">
                <p style="margin:0; font-size:13px; font-weight:600; color:#475569;">No follow-up touchpoints recorded yet.</p>
                <small style="color:#94a3b8;">Click "+ Log New Follow-Up" above to add the first interaction.</small>
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
                                <strong style="font-size:13px; color:#0f172a;">${escapeHtml(f.channel_label)}</strong>
                                <span class="outcome-pill outcome-pill-${f.outcome.toLowerCase()}" style="margin-left:6px;">${escapeHtml(f.outcome_label)}</span>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:11.5px; font-weight:600; color:#64748b;">${f.formatted_date}</div>
                                <small style="color:#94a3b8; font-size:10.5px;">${f.time_ago}</small>
                            </div>
                        </div>

                        <div style="font-size:12.5px; color:#334155; line-height:1.45; background:#f8fafc; padding:8px 10px; border-radius:6px; border:1px solid #f1f5f9; margin-bottom:6px;">
                            ${escapeHtml(f.notes)}
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:11px; color:#64748b;">
                            <span>Logged by: <strong>${escapeHtml(f.user_name)}</strong></span>
                            ${f.formatted_next_date ? `
                                <span style="color:var(--primary); font-weight:600;">
                                    <i class="far fa-calendar-alt"></i> Next: ${f.formatted_next_date}
                                </span>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
    }

    // Original Field Visit Entry
    html += `
        <div class="v-timeline-item">
            <div class="v-timeline-dot" style="border-color:#16a34a; color:#16a34a;"><i class="fas fa-flag"></i></div>
            <div class="v-timeline-card" style="border-left:3.5px solid #16a34a;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <strong style="font-size:13px; color:#0f172a;"><i class="fas fa-street-view" style="color:#16a34a;"></i> Field Visit Logged</strong>
                    <span style="font-size:11.5px; color:#64748b;">${visit.time_ago || ''}</span>
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
                fetchFullRadar(false);
            } else {
                alert('Error: ' + data.message);
            }
        });
}

// ── Response Workspace Modal (Rapid WhatsApp Templates) ──
function openDetailModal(id) {
    const v = radarItems.find(item => item.id == id) || scheduledItems.find(item => item.id == id);
    if (!v) return;

    const rawWa   = (v.whatsapp || v.phone || '').trim();
    const cleanWa = formatWhatsAppNumber(rawWa);
    const isUrgent = (v.is_urgent == 1);

    document.getElementById('detailModalTitle').textContent = v.company_name;
    document.getElementById('detailModalSub').textContent = `Client: ${v.client_name} · Logged by ${v.sales_rep_name}`;

    let cats = (v.categories_arr && v.categories_arr.length) ? v.categories_arr.join(', ') : 'None';

    const tpl1 = `Hello ${v.client_name}, this is Graphicafix management following up on your discussion with our team member (${v.sales_rep_name}) regarding ${v.company_name}. We would like to connect and assist you. When is a convenient time for a quick 5-minute call?`;
    const tpl2 = `Hello ${v.client_name}, regarding your interest in ${cats !== 'None' ? cats : 'our creative services'} for ${v.company_name}, our team at Graphicafix has prepared initial details for you. Would you like us to share our portfolio and package options here?`;
    const tpl3 = `Hello ${v.client_name}, following up on your meeting regarding ${v.company_name}, are you available for a brief discovery call today around ${v.contact_availability || 'your preferred time'} to finalize the scope?`;

    const encTpl1 = encodeURIComponent(tpl1);
    const encTpl2 = encodeURIComponent(tpl2);
    const encTpl3 = encodeURIComponent(tpl3);

    document.getElementById('detailModalBody').innerHTML = `
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
        </div>

        <div style="margin-bottom:16px;">
            <div style="font-size:12.5px; font-weight:700; color:#0f172a; margin-bottom:8px;">Rapid WhatsApp Templates</div>
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
        </div>

        <div style="display:flex; justify-content:flex-end; gap:8px;">
            <button type="button" class="btn btn-primary-custom" onclick="closeDetailModal(); openFollowupModal(${v.id});">
                <i class="fas fa-phone-volume"></i> Record Interaction Now
            </button>
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

    window.location.href = `sales_visits_api.php?${params.toString()}`;
    closeExportModal();
}

// ── Web Audio Chime Synthesis ──
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
