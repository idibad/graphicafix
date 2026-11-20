<?php
include 'dashboard_header.php';

// Auth Check: Super Admins, Admins, PMs, Managers can access Response Unit
$allowed_roles = ['super_admin', 'superadmin', 'admin', 'manager', 'pm'];
if (!in_array($role, $allowed_roles)) {
    header("Location: index.php?error=unauthorized");
    exit;
}

$user_id_int = intval($_SESSION['user_id'] ?? 0);
?>

<div class="gfx-sales-page">

    <!-- ── Clean Page Header ── -->
    <div class="gfx-page-head">
        <div>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <h1 class="gfx-h1">Sales Response Unit</h1>
                <button type="button" id="radarStatusBtn" class="gfx-live-pill" onclick="toggleRadarPolling()" title="Click to pause or resume live updates">
                    <span class="gfx-live-dot"></span>
                    <span id="radarStatusText">Live · 3.5s</span>
                </button>
            </div>
            <p class="gfx-sub">Respond fast to incoming field visits, clear due follow-ups, and track team conversions.</p>
        </div>

        <div class="gfx-head-actions">
            <button type="button" id="audioMuteBtn" class="gfx-btn gfx-btn-ghost" onclick="toggleAudioMute()" title="Sound notifications on">
                <i class="fas fa-volume-up"></i>
            </button>
            <button type="button" id="pushNotifyBtn" class="gfx-btn gfx-btn-ghost" onclick="enablePushNotifications()" title="Enable desktop alerts">
                <i class="fas fa-bell"></i>
            </button>
            <a href="sales_visits.php" class="gfx-btn gfx-btn-outline">
                <i class="fas fa-street-view"></i> <span>Field Visits</span>
            </a>
            <button type="button" class="gfx-btn gfx-btn-primary" onclick="openExportModal()">
                <i class="fas fa-download"></i> <span>Export</span>
            </button>
        </div>
    </div>

    <!-- ── 4 Minimal Clickable KPI Cards ── -->
    <div class="gfx-kpi-grid">
        <button type="button" class="gfx-kpi" id="kpi_all" onclick="handleKpiClick('all')">
            <div class="gfx-kpi-top">
                <span class="gfx-kpi-label">Inbound Visits</span>
                <span class="gfx-kpi-dot dot-slate"></span>
            </div>
            <div class="gfx-kpi-val" id="statTotal">--</div>
        </button>

        <button type="button" class="gfx-kpi" id="kpi_overdue" onclick="handleKpiClick('overdue')">
            <div class="gfx-kpi-top">
                <span class="gfx-kpi-label">Overdue</span>
                <span class="gfx-kpi-dot dot-red"></span>
            </div>
            <div class="gfx-kpi-val val-red" id="statOverdue">--</div>
        </button>

        <button type="button" class="gfx-kpi" id="kpi_due_today" onclick="handleKpiClick('due_today')">
            <div class="gfx-kpi-top">
                <span class="gfx-kpi-label">Due Today</span>
                <span class="gfx-kpi-dot dot-amber"></span>
            </div>
            <div class="gfx-kpi-val val-amber" id="statDueToday">--</div>
        </button>

        <button type="button" class="gfx-kpi" id="kpi_converted" onclick="handleKpiClick('converted')">
            <div class="gfx-kpi-top">
                <span class="gfx-kpi-label">Converted / Won</span>
                <span class="gfx-kpi-dot dot-green"></span>
            </div>
            <div class="gfx-kpi-val val-green" id="statConverted">--</div>
        </button>
    </div>

    <!-- ── Clean Primary Workspace Switcher ── -->
    <div class="gfx-nav-tabs">
        <button type="button" class="gfx-ntab active" id="tabBtnRadar" onclick="switchUnitTab('radar')">
            <i class="fas fa-satellite-dish"></i>
            <span>Response Queue</span>
            <span class="gfx-ntab-badge" id="radarCountBadge">0</span>
        </button>
        <button type="button" class="gfx-ntab" id="tabBtnScheduled" onclick="switchUnitTab('scheduled')">
            <i class="far fa-calendar-check"></i>
            <span>Follow-Up Schedule</span>
            <span class="gfx-ntab-badge badge-alert" id="scheduledAlertBadge" style="display:none;">0</span>
        </button>
        <button type="button" class="gfx-ntab" id="tabBtnReporting" onclick="switchUnitTab('reporting')">
            <i class="fas fa-chart-bar"></i>
            <span>Performance Report</span>
        </button>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════════
         TAB 1: LIVE RESPONSE QUEUE
         ══════════════════════════════════════════════════════════════════════════ -->
    <div id="tabContentRadar" class="gfx-tab-panel active">
        <div class="gfx-surface">
            <div class="gfx-toolbar">
                <div class="gfx-seg-tabs" id="radarFilterPills">
                    <button type="button" class="gfx-seg active" data-filter="pending" onclick="setFilter('pending')">Pending</button>
                    <button type="button" class="gfx-seg" data-filter="overdue" onclick="setFilter('overdue')">Overdue</button>
                    <button type="button" class="gfx-seg" data-filter="due_today" onclick="setFilter('due_today')">Due Today</button>
                    <button type="button" class="gfx-seg" data-filter="urgent" onclick="setFilter('urgent')">Urgent</button>
                    <button type="button" class="gfx-seg" data-filter="hot" onclick="setFilter('hot')">Hot</button>
                    <button type="button" class="gfx-seg" data-filter="all" onclick="setFilter('all')">All</button>
                </div>

                <div class="gfx-toolbar-right">
                    <select id="extraFilterSelect" class="gfx-select" onchange="handleExtraFilter(this.value)">
                        <option value="">Stage filter...</option>
                        <option value="in_discussion">In Discussion</option>
                        <option value="contacted">Contacted</option>
                        <option value="converted">Converted</option>
                        <option value="closed">Closed</option>
                    </select>

                    <select id="sortSelect" class="gfx-select" onchange="setSort(this.value)">
                        <option value="urgency">Sort: Urgency</option>
                        <option value="followup_due">Sort: Due Date</option>
                        <option value="newest">Sort: Newest</option>
                        <option value="interest">Sort: Heat Level</option>
                    </select>

                    <div class="gfx-search">
                        <i class="fas fa-search gfx-search-ico"></i>
                        <input type="text" id="searchInput" oninput="handleSearch(this.value)" placeholder="Search company, client, phone...">
                        <button type="button" id="searchClearBtn" class="gfx-search-clear" onclick="clearSearch()" style="display:none;">&times;</button>
                    </div>
                </div>
            </div>

            <div class="gfx-table-wrap">
                <table class="gfx-table" id="radarTable">
                    <thead>
                        <tr>
                            <th style="width:28%;">Client & Company</th>
                            <th style="width:16%;">Stage & Heat</th>
                            <th style="width:22%;">Follow-Up Status</th>
                            <th style="width:18%;">Services & Window</th>
                            <th style="width:16%; text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="radarTableBody">
                        <tr>
                            <td colspan="5" class="gfx-state-cell">
                                <div class="gfx-loader"><i class="fas fa-circle-notch fa-spin"></i> Connecting to Live Response Queue...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="gfx-table-footer">
                <span id="visibleCountText">Showing 0 leads</span>
                <span class="gfx-footer-tip">Tip: Click any row to log calls, send WhatsApp templates &amp; view timeline</span>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════════
         TAB 2: SCHEDULED FOLLOW-UPS PIPELINE
         ══════════════════════════════════════════════════════════════════════════ -->
    <div id="tabContentScheduled" class="gfx-tab-panel">
        <div class="gfx-surface">
            <div class="gfx-toolbar">
                <div class="gfx-seg-tabs" id="schedFilterPills">
                    <button type="button" class="gfx-seg active" data-sched="all" onclick="setScheduledFilter('all', this)">All Scheduled (<span id="schedCountAll">0</span>)</button>
                    <button type="button" class="gfx-seg" data-sched="overdue" onclick="setScheduledFilter('overdue', this)">Overdue (<span id="schedCountOverdue">0</span>)</button>
                    <button type="button" class="gfx-seg" data-sched="due_today" onclick="setScheduledFilter('due_today', this)">Due Today (<span id="schedCountDue">0</span>)</button>
                    <button type="button" class="gfx-seg" data-sched="upcoming" onclick="setScheduledFilter('upcoming', this)">Upcoming (<span id="schedCountUpcoming">0</span>)</button>
                </div>

                <button type="button" class="gfx-btn gfx-btn-ghost gfx-btn-sm" onclick="loadScheduledFollowups(true)">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>

            <div class="gfx-table-wrap">
                <table class="gfx-table" id="scheduledTable">
                    <thead>
                        <tr>
                            <th style="width:27%;">Client & Company</th>
                            <th style="width:21%;">Due Schedule</th>
                            <th style="width:20%;">Last Interaction</th>
                            <th style="width:14%;">Sales Rep</th>
                            <th style="width:18%; text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="scheduledTableBody">
                        <tr>
                            <td colspan="5" class="gfx-state-cell">
                                <div class="gfx-loader"><i class="fas fa-circle-notch fa-spin"></i> Loading scheduled follow-ups...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════════
         TAB 3: PERFORMANCE & CONVERSION REPORT
         ══════════════════════════════════════════════════════════════════════════ -->
    <div id="tabContentReporting" class="gfx-tab-panel">

        <!-- Clean Filter Bar -->
        <div class="gfx-surface" style="margin-bottom:18px; padding:14px 18px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div class="gfx-seg-tabs" id="reportPresetTabs">
                    <button type="button" class="gfx-seg" data-preset="today" onclick="setReportPreset('today', this)">Today</button>
                    <button type="button" class="gfx-seg" data-preset="week" onclick="setReportPreset('week', this)">7 Days</button>
                    <button type="button" class="gfx-seg active" data-preset="month" onclick="setReportPreset('month', this)">This Month</button>
                    <button type="button" class="gfx-seg" data-preset="last_month" onclick="setReportPreset('last_month', this)">Last Month</button>
                    <button type="button" class="gfx-seg" data-preset="all" onclick="setReportPreset('all', this)">All Time</button>
                    <button type="button" class="gfx-seg" data-preset="custom" onclick="setReportPreset('custom', this)">Custom</button>
                </div>

                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <div id="customDateRangeWrap" style="display:none; align-items:center; gap:6px;">
                        <input type="date" id="reportStartDate" class="gfx-select">
                        <span style="font-size:12px; color:#94a3b8;">to</span>
                        <input type="date" id="reportEndDate" class="gfx-select">
                        <button type="button" class="gfx-btn gfx-btn-primary gfx-btn-sm" onclick="loadReportingData()">Apply</button>
                    </div>

                    <select id="reportRepSelect" class="gfx-select" onchange="loadReportingData()">
                        <option value="0">All Sales Reps</option>
                    </select>

                    <button type="button" class="gfx-btn gfx-btn-outline gfx-btn-sm" onclick="exportReportingCSV('visits')">
                        <i class="fas fa-file-excel" style="color:#16a34a !important;"></i> Pipeline CSV
                    </button>
                    <button type="button" class="gfx-btn gfx-btn-outline gfx-btn-sm" onclick="exportReportingCSV('followup_history')">
                        <i class="fas fa-history"></i> Touchpoints CSV
                    </button>
                </div>
            </div>
        </div>

        <!-- 5 Clean Metric Cards -->
        <div class="gfx-report-kpis">
            <div class="gfx-rkpi">
                <div class="gfx-kpi-label">Visits Logged</div>
                <div class="gfx-kpi-val" id="repKpiVisits">--</div>
            </div>
            <div class="gfx-rkpi">
                <div class="gfx-kpi-label">Follow-Ups</div>
                <div class="gfx-kpi-val" style="color:#0284c7;" id="repKpiFollowups">--</div>
                <div class="gfx-rkpi-sub"><span id="repKpiAvgF">0</span> avg / visit</div>
            </div>
            <div class="gfx-rkpi">
                <div class="gfx-kpi-label">Engagement Rate</div>
                <div class="gfx-kpi-val" style="color:#7c3aed;" id="repKpiEngagement">--%</div>
                <div class="gfx-rkpi-sub">Visits followed up</div>
            </div>
            <div class="gfx-rkpi">
                <div class="gfx-kpi-label">Converted Leads</div>
                <div class="gfx-kpi-val val-green" id="repKpiConverted">--</div>
                <div class="gfx-rkpi-sub" style="color:#16a34a;"><span id="repKpiWinRate">0%</span> win rate</div>
            </div>
            <div class="gfx-rkpi">
                <div class="gfx-kpi-label">Missed / Overdue</div>
                <div class="gfx-kpi-val val-red" id="repKpiOverdue">--</div>
            </div>
        </div>

        <!-- Leaderboard & Distribution -->
        <div class="gfx-report-grid">
            <div class="gfx-surface">
                <div class="gfx-card-bar">Sales Rep Leaderboard</div>
                <div class="gfx-table-wrap">
                    <table class="gfx-table" style="min-width:520px;">
                        <thead>
                            <tr>
                                <th>Representative</th>
                                <th style="text-align:center;">Visits</th>
                                <th style="text-align:center;">Follow-Ups</th>
                                <th style="text-align:center;">Converted</th>
                                <th style="text-align:center;">Win Rate</th>
                                <th style="text-align:right;">Last Active</th>
                            </tr>
                        </thead>
                        <tbody id="leaderboardTableBody">
                            <tr><td colspan="6" class="gfx-state-cell">Loading leaderboard...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:16px;">
                <div class="gfx-surface" style="padding:18px;">
                    <div class="gfx-section-heading" style="margin-bottom:14px;">Follow-Up Outcomes</div>
                    <div id="outcomeDistributionWrap" style="display:flex; flex-direction:column; gap:12px;">
                        <span style="font-size:12.5px; color:#94a3b8;">Loading...</span>
                    </div>
                </div>

                <div class="gfx-surface" style="padding:18px;">
                    <div class="gfx-section-heading" style="margin-bottom:14px;">Contact Channels Used</div>
                    <div id="channelDistributionWrap" style="display:flex; flex-direction:column; gap:12px;">
                        <span style="font-size:12.5px; color:#94a3b8;">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     UNIFIED RESPONSE WORKSPACE DRAWER (Follow-Up + Templates + Timeline in ONE Place)
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="responseDrawer" class="modal-overlay" onclick="if(event.target===this)closeResponseDrawer()">
    <div class="modal-content">
        <div class="modal-header">
            <div style="display:flex; align-items:center; gap:12px; min-width:0;">
                <div class="gfx-avatar-lg" id="rdAvatar">C</div>
                <div style="min-width:0;">
                    <h4 id="rdCompany" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Response Workspace</h4>
                    <div id="rdMeta" class="gfx-drawer-sub">Client · Phone · Rep</div>
                </div>
            </div>
            <button type="button" class="modal-close" onclick="closeResponseDrawer()">&times;</button>
        </div>

        <!-- Sticky Quick Contact & Stage Bar -->
        <div class="gfx-ws-quickbar">
            <div class="gfx-ws-actions" id="rdQuickActions"></div>
            <div class="gfx-ws-stage-row">
                <label class="gfx-micro-label">Stage:</label>
                <div class="gfx-stage-switcher" id="rdStageSwitcher"></div>
            </div>
        </div>

        <!-- 3 Seamless Drawer Tabs -->
        <div class="gfx-drawer-tabs">
            <button type="button" class="gfx-dtab active" id="dtabLog" onclick="switchDrawerTab('log')">
                <i class="fas fa-phone-volume"></i> Log Follow-Up
            </button>
            <button type="button" class="gfx-dtab" id="dtabTemplates" onclick="switchDrawerTab('templates')">
                <i class="fab fa-whatsapp"></i> Quick Messages
            </button>
            <button type="button" class="gfx-dtab" id="dtabHistory" onclick="switchDrawerTab('history')">
                <i class="fas fa-history"></i> Timeline <span class="gfx-dtab-count" id="rdTouchCount">0</span>
            </button>
        </div>

        <!-- Drawer Body -->
        <div class="modal-body" style="padding:20px 24px !important;">

            <!-- PANEL 1: Fast Follow-Up Logger -->
            <div id="drawerPanelLog" class="gfx-dpanel active">
                <form id="followupForm" onsubmit="submitFollowupRecord(event)">
                    <input type="hidden" name="visit_id" id="fModalVisitId" value="">
                    <input type="hidden" name="status" id="fModalStatus" value="in_discussion">

                    <div class="gfx-field">
                        <label class="gfx-label">1. Contact Channel</label>
                        <div class="gfx-chip-row" id="channelChipGroup">
                            <label class="gfx-chip active"><input type="radio" name="contact_channel" value="call" checked onchange="syncChipGroup(this)"><i class="fas fa-phone-alt"></i> Call</label>
                            <label class="gfx-chip"><input type="radio" name="contact_channel" value="whatsapp" onchange="syncChipGroup(this)"><i class="fab fa-whatsapp"></i> WhatsApp</label>
                            <label class="gfx-chip"><input type="radio" name="contact_channel" value="meeting" onchange="syncChipGroup(this)"><i class="fas fa-handshake"></i> Meeting</label>
                            <label class="gfx-chip"><input type="radio" name="contact_channel" value="email" onchange="syncChipGroup(this)"><i class="fas fa-envelope"></i> Email</label>
                            <label class="gfx-chip"><input type="radio" name="contact_channel" value="office_visit" onchange="syncChipGroup(this)"><i class="fas fa-building"></i> Office</label>
                        </div>
                    </div>

                    <div class="gfx-field">
                        <label class="gfx-label">2. Outcome</label>
                        <div class="gfx-outcome-grid" id="outcomeChipGroup">
                            <label class="gfx-chip active"><input type="radio" name="outcome" value="followup_needed" checked onchange="syncOutcomeChip(this)">Follow-Up Needed</label>
                            <label class="gfx-chip"><input type="radio" name="outcome" value="interested" onchange="syncOutcomeChip(this)">Interested / Hot</label>
                            <label class="gfx-chip"><input type="radio" name="outcome" value="proposal_sent" onchange="syncOutcomeChip(this)">Proposal / Quote Sent</label>
                            <label class="gfx-chip"><input type="radio" name="outcome" value="meeting_scheduled" onchange="syncOutcomeChip(this)">Meeting Booked</label>
                            <label class="gfx-chip"><input type="radio" name="outcome" value="callback_requested" onchange="syncOutcomeChip(this)">Callback Requested</label>
                            <label class="gfx-chip"><input type="radio" name="outcome" value="no_answer" onchange="syncOutcomeChip(this)">No Answer</label>
                            <label class="gfx-chip chip-green"><input type="radio" name="outcome" value="converted" onchange="syncOutcomeChip(this)">Won / Converted</label>
                            <label class="gfx-chip chip-muted"><input type="radio" name="outcome" value="not_interested" onchange="syncOutcomeChip(this)">Not Interested</label>
                        </div>
                    </div>

                    <div class="gfx-field">
                        <label class="gfx-label">3. Discussion Notes <span class="req">*</span></label>
                        <div class="gfx-quick-notes">
                            <button type="button" class="gfx-mini-chip" onclick="appendFollowupNote('Called client and discussed scope of work.')">+ Discussed scope</button>
                            <button type="button" class="gfx-mini-chip" onclick="appendFollowupNote('Shared portfolio & pricing on WhatsApp.')">+ Sent quote</button>
                            <button type="button" class="gfx-mini-chip" onclick="appendFollowupNote('Client will confirm after checking with partner.')">+ Partner review</button>
                            <button type="button" class="gfx-mini-chip" onclick="appendFollowupNote('No answer — sent WhatsApp follow-up.')">+ No answer</button>
                        </div>
                        <textarea name="notes" id="fModalNotes" rows="3" required class="gfx-input" placeholder="What was discussed? Any pricing or next commitment?"></textarea>
                    </div>

                    <div class="gfx-field gfx-schedule-box">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <label class="gfx-label" style="margin:0;"><i class="far fa-calendar-alt"></i> Schedule Next Follow-Up</label>
                            <button type="button" class="gfx-text-btn" onclick="document.getElementById('fModalNextDate').value=''">Clear</button>
                        </div>
                        <div class="gfx-quick-notes">
                            <button type="button" class="gfx-mini-chip" onclick="setNextDatePreset('fModalNextDate','today_pm')">Today 5 PM</button>
                            <button type="button" class="gfx-mini-chip" onclick="setNextDatePreset('fModalNextDate','tomorrow_am')">Tomorrow 11 AM</button>
                            <button type="button" class="gfx-mini-chip" onclick="setNextDatePreset('fModalNextDate','tomorrow_pm')">Tomorrow 4 PM</button>
                            <button type="button" class="gfx-mini-chip" onclick="setNextDatePreset('fModalNextDate','in_2_days')">In 2 Days</button>
                            <button type="button" class="gfx-mini-chip" onclick="setNextDatePreset('fModalNextDate','next_week')">Next Monday</button>
                        </div>
                        <input type="datetime-local" name="next_followup_date" id="fModalNextDate" class="gfx-input">
                    </div>

                    <button type="submit" class="gfx-btn gfx-btn-primary" id="fModalSubmitBtn" style="width:100%; justify-content:center; padding:11px 18px; margin-top:6px;">
                        <i class="fas fa-check"></i> <span>Save Follow-Up</span>
                    </button>
                </form>
            </div>

            <!-- PANEL 2: 1-Click Personalized WhatsApp Templates -->
            <div id="drawerPanelTemplates" class="gfx-dpanel">
                <div id="rdTemplatesList"></div>
            </div>

            <!-- PANEL 3: Visit Info + Full Timeline -->
            <div id="drawerPanelHistory" class="gfx-dpanel">
                <div id="rdDetailsCard" class="gfx-info-card"></div>
                <div class="gfx-section-heading" style="margin-top:18px;">Interaction Timeline</div>
                <div id="rdTimelineBody">
                    <div class="gfx-loader"><i class="fas fa-circle-notch fa-spin"></i> Loading history...</div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     EXPORT DRAWER
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="exportModal" class="modal-overlay" onclick="if(event.target===this)closeExportModal()">
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <div class="modal-header-icon"><i class="fas fa-file-excel"></i></div>
                <div>
                    <h4>Export Spreadsheet</h4>
                    <div class="gfx-drawer-sub">Download formatted Excel / CSV sheet</div>
                </div>
            </div>
            <button type="button" class="modal-close" onclick="closeExportModal()">&times;</button>
        </div>

        <form id="exportForm" onsubmit="executeExport(event)">
            <div class="modal-body" style="padding:20px 24px !important;">
                <div class="gfx-field">
                    <label class="gfx-label">Report Type</label>
                    <div class="gfx-chip-row">
                        <label class="gfx-chip active" style="flex:1; justify-content:center;"><input type="radio" name="export_type" value="visits" checked onchange="syncChipGroup(this)">Master Visits</label>
                        <label class="gfx-chip" style="flex:1; justify-content:center;"><input type="radio" name="export_type" value="followup_history" onchange="syncChipGroup(this)">Follow-Ups Log</label>
                    </div>
                </div>

                <div class="gfx-field">
                    <label class="gfx-label">Sales Representative</label>
                    <select id="exportRepSelect" class="gfx-input">
                        <option value="0">All Representatives</option>
                    </select>
                </div>

                <div class="gfx-field">
                    <label class="gfx-label">Date Range</label>
                    <div class="gfx-quick-notes" id="exportPresetGroup">
                        <button type="button" class="gfx-mini-chip active" onclick="setExportDatePreset('all', this)">All Time</button>
                        <button type="button" class="gfx-mini-chip" onclick="setExportDatePreset('today', this)">Today</button>
                        <button type="button" class="gfx-mini-chip" onclick="setExportDatePreset('week', this)">Last 7 Days</button>
                        <button type="button" class="gfx-mini-chip" onclick="setExportDatePreset('month', this)">This Month</button>
                    </div>
                </div>

                <div class="gfx-grid-2">
                    <div class="gfx-field">
                        <label class="gfx-label">From</label>
                        <input type="date" id="exportStartDate" class="gfx-input">
                    </div>
                    <div class="gfx-field">
                        <label class="gfx-label">To</label>
                        <input type="date" id="exportEndDate" class="gfx-input">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="gfx-btn gfx-btn-outline" onclick="closeExportModal()">Cancel</button>
                <button type="submit" class="gfx-btn gfx-btn-primary">
                    <i class="fas fa-download"></i> <span>Download CSV</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Host -->
<div id="gfxToastHost" class="gfx-toast-host"></div>

<style>
/* ══════════════════════════════════════════════════════════════════════════════
   CLEAN MODERN CRM UI — HARMONIZED WITH DASHBOARD.CSS (ZERO ICON/MODAL CLASHES)
   ══════════════════════════════════════════════════════════════════════════════ */
.gfx-sales-page {
    padding: 72px 8px 48px;
    max-width: 1440px;
    margin: 0 auto;
    color: #0f172a;
}

/* Override dashboard.css global i.fas { color: var(--primary) } inside colored controls */
.gfx-sales-page .gfx-btn-primary i,
.gfx-sales-page .gfx-wa-icon-btn i,
.gfx-sales-page .gfx-wa-pill-btn i,
.gfx-sales-page .gfx-chip.active i,
.gfx-sales-page .gfx-seg.active i,
.gfx-sales-page .gfx-ntab i,
.gfx-sales-page .gfx-pill i,
.gfx-sales-page .gfx-toast i,
.modal-overlay .gfx-btn-primary i,
.modal-overlay .gfx-wa-pill-btn i,
.modal-overlay .gfx-chip.active i,
.modal-overlay .gfx-stage-btn.active i,
.modal-overlay .gfx-dtab i {
    color: inherit !important;
}

/* Header */
.gfx-page-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.gfx-h1 {
    margin: 0;
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.02em;
}
.gfx-sub {
    margin: 4px 0 0;
    font-size: 13.5px;
    color: #64748b;
}
.gfx-head-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

/* Live Status Pill (Clickable to Pause/Resume) */
.gfx-live-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 11px;
    border-radius: 20px;
    border: 1px solid #bbf7d0;
    background: #f0fdf4;
    color: #15803d;
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.16s ease;
}
.gfx-live-pill:hover { background: #dcfce7; }
.gfx-live-pill.paused {
    border-color: #fde68a;
    background: #fffbeb;
    color: #b45309;
}
.gfx-live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #16a34a;
    animation: livePulse 1.8s infinite;
}
.gfx-live-pill.paused .gfx-live-dot {
    background: #d97706;
    animation: none;
}
@keyframes livePulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.35; }
}

/* Buttons */
.gfx-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 9px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.16s ease;
    text-decoration: none !important;
    white-space: nowrap;
    line-height: 1.2;
}
.gfx-btn-primary {
    background: #024442;
    color: #ffffff !important;
    border-color: #024442;
    box-shadow: 0 1px 3px rgba(2, 68, 66, 0.22);
}
.gfx-btn-primary:hover {
    background: #035a57;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(2, 68, 66, 0.24);
}
.gfx-btn-outline {
    background: #ffffff;
    color: #334155 !important;
    border-color: #dbe2ea;
}
.gfx-btn-outline:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #0f172a !important;
}
.gfx-btn-ghost {
    background: #ffffff;
    color: #64748b !important;
    border-color: #e2e8f0;
    padding: 9px 11px;
}
.gfx-btn-ghost:hover { background: #f1f5f9; color: #0f172a !important; }
.gfx-btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 8px; }

/* KPI Strip */
.gfx-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}
.gfx-kpi {
    background: #ffffff;
    border: 1.5px solid #e5e7eb;
    border-radius: 14px;
    padding: 16px 18px;
    text-align: left;
    cursor: pointer;
    transition: all 0.18s ease;
    font-family: inherit;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}
.gfx-kpi:hover {
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
}
.gfx-kpi.active {
    border-color: #024442;
    background: #f6fbfa;
    box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.09);
}
.gfx-kpi-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.gfx-kpi-label {
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.gfx-kpi-dot { width: 8px; height: 8px; border-radius: 50%; }
.dot-slate { background: #94a3b8; }
.dot-red   { background: #ef4444; }
.dot-amber { background: #f59e0b; }
.dot-green { background: #16a34a; }
.gfx-kpi-val { font-size: 28px; font-weight: 800; color: #0f172a; line-height: 1.1; }
.val-red   { color: #dc2626; }
.val-amber { color: #d97706; }
.val-green { color: #16a34a; }

/* Primary Tabs */
.gfx-nav-tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 16px;
    border-bottom: 1px solid #e2e8f0;
    overflow-x: auto;
}
.gfx-ntab {
    background: none;
    border: none;
    border-bottom: 2.5px solid transparent;
    padding: 10px 16px;
    font-size: 13.5px;
    font-weight: 700;
    color: #64748b;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    font-family: inherit;
    transition: all 0.15s ease;
}
.gfx-ntab:hover { color: #0f172a; }
.gfx-ntab.active {
    color: #024442;
    border-bottom-color: #024442;
}
.gfx-ntab-badge {
    background: #eef2f6;
    color: #475569;
    font-size: 11px;
    font-weight: 800;
    padding: 1px 7px;
    border-radius: 10px;
}
.gfx-ntab-badge.badge-alert {
    background: #fee2e2;
    color: #dc2626;
}
.gfx-tab-panel { display: none; }
.gfx-tab-panel.active { display: block; }

/* Surface & Toolbar */
.gfx-surface {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}
.gfx-toolbar {
    padding: 14px 18px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    background: #fafbfc;
}
.gfx-seg-tabs {
    display: inline-flex;
    background: #eef2f6;
    padding: 3px;
    border-radius: 10px;
    gap: 2px;
    flex-wrap: wrap;
}
.gfx-seg {
    background: transparent;
    border: none;
    padding: 6px 13px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 700;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
    font-family: inherit;
}
.gfx-seg:hover { color: #0f172a; }
.gfx-seg.active {
    background: #ffffff;
    color: #024442;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.1);
}
.gfx-toolbar-right {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.gfx-select {
    height: 36px;
    padding: 0 10px;
    border: 1px solid #dbe2ea;
    border-radius: 9px;
    background: #ffffff;
    color: #334155;
    font-size: 12.5px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    outline: none;
}
.gfx-select:focus { border-color: #024442; }

.gfx-search { position: relative; width: 240px; }
.gfx-search-ico {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 12px;
    color: #94a3b8 !important;
    pointer-events: none;
}
.gfx-search input {
    width: 100%;
    height: 36px;
    padding: 0 28px 0 32px;
    border: 1px solid #dbe2ea;
    border-radius: 9px;
    background: #ffffff;
    font-size: 13px;
    color: #0f172a;
    font-family: inherit;
    outline: none;
}
.gfx-search input:focus {
    border-color: #024442;
    box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.08);
}
.gfx-search-clear {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 16px;
    cursor: pointer;
    padding: 0 4px;
}

/* Table */
.gfx-table-wrap { overflow-x: auto; }
.gfx-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
}
.gfx-table th {
    padding: 12px 18px;
    text-align: left;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    background: #ffffff;
    border-bottom: 1px solid #eef2f6;
}
.gfx-table tbody tr {
    cursor: pointer;
    transition: background 0.14s ease;
}
.gfx-table td {
    padding: 15px 18px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.gfx-table tbody tr:hover td { background: #f8fafc; }
.gfx-table tbody tr.row-urgent td:first-child { box-shadow: inset 3px 0 0 #ef4444; }
.gfx-table tbody tr:last-child td { border-bottom: none; }

.gfx-client-cell { display: flex; align-items: center; gap: 12px; }
.gfx-avatar {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #edf5f4;
    color: #024442;
    font-weight: 800;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.gfx-avatar.urgent { background: #fef2f2; color: #dc2626; }
.gfx-company-name {
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 6px;
}
.gfx-urgent-pill {
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #dc2626;
    background: #fee2e2;
    padding: 1px 6px;
    border-radius: 4px;
}
.gfx-client-sub { font-size: 12.5px; color: #64748b; margin-top: 2px; }

.gfx-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    white-space: nowrap;
}
.pill-pending    { background: #fef3c7; color: #92400e; }
.pill-contacted  { background: #e0f2fe; color: #0369a1; }
.pill-discussion { background: #ede9fe; color: #6d28d9; }
.pill-converted  { background: #dcfce7; color: #15803d; }
.pill-closed     { background: #f1f5f9; color: #64748b; }

.gfx-heat-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11.5px;
    font-weight: 700;
    color: #475569;
    margin-top: 5px;
}
.heat-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }

.gfx-due-line {
    font-size: 12.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.due-overdue  { color: #dc2626; }
.due-today    { color: #d97706; }
.due-upcoming { color: #0284c7; }
.due-done     { color: #16a34a; }
.due-none     { color: #94a3b8; font-weight: 600; }
.gfx-touch-meta { font-size: 11.5px; color: #64748b; margin-top: 3px; }

.gfx-service-tag {
    display: inline-block;
    background: #f1f5f9;
    color: #334155;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 600;
    margin-right: 4px;
    margin-bottom: 3px;
}
.gfx-window-text { font-size: 11.5px; color: #64748b; margin-top: 2px; }

.gfx-row-actions {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    justify-content: flex-end;
}
.gfx-icon-act {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #475569 !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none !important;
}
.gfx-icon-act i { color: inherit !important; }
.gfx-icon-act:hover { background: #f1f5f9; color: #0f172a !important; }
.gfx-wa-icon-btn {
    background: #dcfce7;
    border-color: #bbf7d0;
    color: #15803d !important;
}
.gfx-wa-icon-btn:hover { background: #16a34a; border-color: #16a34a; color: #ffffff !important; }

.gfx-table-footer {
    padding: 12px 18px;
    border-top: 1px solid #f1f5f9;
    background: #fafbfc;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: #64748b;
}
.gfx-footer-tip { color: #94a3b8; }
.gfx-state-cell { text-align: center; padding: 56px 20px !important; color: #64748b; }
.gfx-loader { display: inline-flex; align-items: center; gap: 8px; font-size: 13.5px; color: #64748b; }

/* Reporting */
.gfx-report-kpis {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 14px;
    margin-bottom: 18px;
}
.gfx-rkpi {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 16px 18px;
}
.gfx-rkpi-sub { font-size: 11.5px; color: #64748b; margin-top: 4px; font-weight: 600; }
.gfx-report-grid {
    display: grid;
    grid-template-columns: 1.65fr 1fr;
    gap: 16px;
}
.gfx-card-bar {
    padding: 14px 18px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
}
.gfx-prog-track {
    height: 7px;
    background: #f1f5f9;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 5px;
}
.gfx-prog-fill {
    height: 100%;
    border-radius: 4px;
    background: #024442;
    transition: width 0.3s ease;
}

/* Unified Response Drawer */
.gfx-drawer-sub { font-size: 12.5px; color: #64748b !important; margin-top: 2px; font-weight: 500; }
.gfx-avatar-lg {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: #024442;
    color: #ffffff;
    font-size: 16px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.gfx-ws-quickbar {
    padding: 14px 24px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    gap: 12px;
    flex-shrink: 0;
}
.gfx-ws-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.gfx-wa-pill-btn {
    background: #16a34a;
    color: #ffffff !important;
    border: 1px solid #16a34a;
    padding: 7px 13px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none !important;
}
.gfx-wa-pill-btn:hover { background: #15803d; color: #ffffff !important; }
.gfx-ws-stage-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.gfx-micro-label { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin: 0; }
.gfx-stage-switcher { display: flex; gap: 4px; flex-wrap: wrap; }
.gfx-stage-btn {
    background: #ffffff;
    border: 1px solid #dbe2ea;
    color: #475569;
    padding: 4px 10px;
    border-radius: 7px;
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
}
.gfx-stage-btn.active { background: #024442; border-color: #024442; color: #ffffff; }

.gfx-drawer-tabs {
    display: flex;
    border-bottom: 1px solid #e2e8f0;
    background: #ffffff;
    padding: 0 24px;
    gap: 18px;
    flex-shrink: 0;
}
.gfx-dtab {
    background: none;
    border: none;
    border-bottom: 2.5px solid transparent;
    padding: 12px 2px;
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: inherit;
}
.gfx-dtab:hover { color: #0f172a; }
.gfx-dtab.active { color: #024442; border-bottom-color: #024442; }
.gfx-dtab-count { background: #eef2f6; color: #475569; font-size: 11px; padding: 1px 7px; border-radius: 10px; }
.gfx-dpanel { display: none; }
.gfx-dpanel.active { display: block; }

/* Form & Chips inside Drawer */
.gfx-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.gfx-field { margin-bottom: 14px; }
.gfx-label { display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px; }
.gfx-label .req { color: #ef4444; }
.gfx-input {
    width: 100%;
    padding: 9px 12px !important;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    font-size: 13px;
    color: #0f172a;
    background: #ffffff;
    font-family: inherit;
    outline: none;
}
.gfx-input:focus { border-color: #024442; box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.1); }
.gfx-text-btn { background: none; border: none; font-size: 11.5px; font-weight: 700; color: #ef4444; cursor: pointer; padding: 0; }
.gfx-chip-row { display: flex; flex-wrap: wrap; gap: 7px; }
.gfx-outcome-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 7px; }
.gfx-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border-radius: 9px;
    border: 1px solid #dbe2ea;
    background: #ffffff;
    color: #475569;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    margin: 0;
}
.gfx-chip input { display: none; }
.gfx-chip:hover { border-color: #94a3b8; color: #0f172a; }
.gfx-chip.active { background: #024442; border-color: #024442; color: #ffffff; }
.gfx-chip.chip-green.active { background: #16a34a; border-color: #16a34a; }
.gfx-chip.chip-muted.active { background: #64748b; border-color: #64748b; }

.gfx-quick-notes { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.gfx-mini-chip {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #334155;
    padding: 4px 10px;
    border-radius: 16px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
}
.gfx-mini-chip:hover,
.gfx-mini-chip.active { background: #024442; border-color: #024442; color: #ffffff; }
.gfx-schedule-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; }

/* Template Cards */
.gfx-tpl-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 12px;
}
.gfx-tpl-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}
.gfx-tpl-title { font-size: 13px; font-weight: 700; color: #0f172a; }
.gfx-tpl-body {
    font-size: 12.5px;
    color: #475569;
    line-height: 1.55;
    background: #f8fafc;
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid #eef2f6;
}

/* Info Card & Timeline */
.gfx-info-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; font-size: 13px; }
.gfx-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
.gfx-info-k { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #94a3b8; }
.gfx-info-v { font-size: 13px; font-weight: 700; color: #0f172a; margin-top: 2px; }
.gfx-section-heading { font-size: 11.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 12px; }
.gfx-timeline { position: relative; padding-left: 24px; }
.gfx-timeline::before { content: ''; position: absolute; left: 7px; top: 6px; bottom: 6px; width: 2px; background: #e2e8f0; }
.gfx-tl-item { position: relative; margin-bottom: 14px; }
.gfx-tl-dot { position: absolute; left: -24px; top: 4px; width: 16px; height: 16px; border-radius: 50%; background: #ffffff; border: 2.5px solid #024442; }
.gfx-tl-dot.dot-init { border-color: #16a34a; }
.gfx-tl-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 11px 14px; }
.gfx-tl-top { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 6px; }
.gfx-tl-note { font-size: 13px; color: #334155; line-height: 1.5; margin-bottom: 6px; }
.gfx-tl-meta { display: flex; justify-content: space-between; font-size: 11.5px; color: #94a3b8; }

/* Toasts */
.gfx-toast-host {
    position: fixed;
    bottom: 22px;
    right: 22px;
    z-index: 11000;
    display: flex;
    flex-direction: column;
    gap: 8px;
    pointer-events: none;
}
.gfx-toast {
    background: #0f172a;
    color: #ffffff;
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.25);
    pointer-events: auto;
    animation: gfxToastIn 0.2s ease-out;
}
.gfx-toast.toast-success { background: #065f46; }
.gfx-toast.toast-error   { background: #991b1b; }
.gfx-toast.toast-warning { background: #92400e; }
@keyframes gfxToastIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

@media (max-width: 1024px) {
    .gfx-report-kpis { grid-template-columns: repeat(2, 1fr); }
    .gfx-report-grid { grid-template-columns: 1fr; }
}
@media (max-width: 900px) {
    .gfx-kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .gfx-search, .gfx-toolbar-right { width: 100%; }
}
@media (max-width: 640px) {
    .gfx-sales-page { padding: 66px 0 36px; }
    .gfx-grid-2, .gfx-outcome-grid, .gfx-info-grid { grid-template-columns: 1fr; }
    .gfx-footer-tip { display: none; }
}
</style>

<script>
let lastKnownId        = 0;
let pollingTimer       = null;
let syncTimer          = null;
let isPollingPaused    = false;
let isAudioMuted       = false;
let radarItems         = [];
let scheduledItems     = [];
let activeVisit        = null;
let currentTab         = 'radar';
let currentFilter      = 'pending';
let currentSchedFilter = 'all';
let currentSort        = 'urgency';
let currentReportPreset = 'month';
let searchQuery        = '';
let searchTimer        = null;
let audioCtx           = null;

document.addEventListener('DOMContentLoaded', () => {
    checkNotificationPermission();
    fetchFullRadar(true);
    startPolling();
    loadSalesReps();

    const params = new URLSearchParams(window.location.search);
    if (params.get('tab') === 'scheduled') switchUnitTab('scheduled');
    else if (params.get('tab') === 'reporting') switchUnitTab('reporting');

    const hlId = params.get('highlight');
    if (hlId) {
        setTimeout(() => openResponseDrawer(parseInt(hlId), 'log'), 800);
    }

    window.addEventListener('globalSalesVisitArrived', () => {
        if (!isPollingPaused) fetchFullRadar(false);
    });
});

/* ── Navigation & KPI Clicks ── */
function switchUnitTab(tabName) {
    currentTab = tabName;
    document.querySelectorAll('.gfx-ntab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.gfx-tab-panel').forEach(p => p.classList.remove('active'));

    if (tabName === 'radar') {
        document.getElementById('tabBtnRadar').classList.add('active');
        document.getElementById('tabContentRadar').classList.add('active');
        renderRadarTable();
    } else if (tabName === 'scheduled') {
        document.getElementById('tabBtnScheduled').classList.add('active');
        document.getElementById('tabContentScheduled').classList.add('active');
        loadScheduledFollowups(true);
    } else if (tabName === 'reporting') {
        document.getElementById('tabBtnReporting').classList.add('active');
        document.getElementById('tabContentReporting').classList.add('active');
        loadReportingData();
    }
}

function handleKpiClick(key) {
    ['all', 'overdue', 'due_today', 'converted'].forEach(k => {
        const el = document.getElementById('kpi_' + k);
        if (el) el.classList.toggle('active', k === key);
    });

    if (key === 'overdue' || key === 'due_today') {
        switchUnitTab('scheduled');
        const btn = document.querySelector(`#schedFilterPills [data-sched="${key}"]`);
        setScheduledFilter(key, btn);
    } else {
        switchUnitTab('radar');
        setFilter(key);
    }
}

/* ── Live Polling with 1-Click Pause/Resume ── */
function startPolling() {
    if (pollingTimer) clearInterval(pollingTimer);
    pollingTimer = setInterval(() => {
        if (!isPollingPaused) pollLatestVisits();
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
    const btn = document.getElementById('radarStatusBtn');
    const txt = document.getElementById('radarStatusText');
    if (isPollingPaused) {
        btn.classList.add('paused');
        txt.textContent = 'Paused · Click to Resume';
        showToast('Live auto-refresh paused', 'warning');
    } else {
        btn.classList.remove('paused');
        txt.textContent = 'Live · 3.5s';
        showToast('Live radar resumed', 'success');
        fetchFullRadar(false);
    }
}

function pollLatestVisits() {
    fetch(`sales_visits_api.php?action=poll&last_id=${lastKnownId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            if (data.has_new || (data.items && data.items.length > 0)) {
                playNotificationChime();
                const items = data.items || data.new_records || [];
                items.forEach(v => triggerDesktopAlert(v));
                if (data.latest_id && data.latest_id > lastKnownId) lastKnownId = data.latest_id;
                showToast(`New field visit: ${items[0]?.company_name || 'Incoming'}`, 'info');
                fetchFullRadar(false);
            } else if (data.stats) {
                renderStats(data.stats);
            }
        })
        .catch(() => {});
}

function fetchFullRadar(showLoading = true) {
    if (showLoading && radarItems.length === 0) {
        document.getElementById('radarTableBody').innerHTML = `
            <tr><td colspan="5" class="gfx-state-cell"><div class="gfx-loader"><i class="fas fa-circle-notch fa-spin"></i> Scanning response queue...</div></td></tr>
        `;
    }

    fetch(`sales_visits_api.php?action=fetch_all&filter=${encodeURIComponent(currentFilter)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            radarItems = data.items || [];
            radarItems.forEach(v => { if (parseInt(v.id) > lastKnownId) lastKnownId = parseInt(v.id); });
            if (data.latest_id && data.latest_id > lastKnownId) lastKnownId = data.latest_id;

            renderStats(data.stats || {});
            renderRadarTable();

            if (activeVisit) {
                const updated = radarItems.find(x => x.id == activeVisit.id) || scheduledItems.find(x => x.id == activeVisit.id);
                if (updated) {
                    activeVisit = updated;
                    renderResponseHeader(updated);
                }
            }
        })
        .catch(() => {});
}

function renderStats(s) {
    document.getElementById('statTotal').textContent     = s.total ?? 0;
    document.getElementById('statOverdue').textContent   = s.overdue ?? 0;
    document.getElementById('statDueToday').textContent  = s.due_today ?? 0;
    document.getElementById('statConverted').textContent = s.converted ?? 0;
    document.getElementById('radarCountBadge').textContent = s.pending ?? 0;

    const alertCnt = (parseInt(s.overdue || 0) + parseInt(s.due_today || 0));
    const badge = document.getElementById('scheduledAlertBadge');
    badge.textContent = alertCnt;
    badge.style.display = alertCnt > 0 ? 'inline-block' : 'none';

    document.getElementById('schedCountAll').textContent      = parseInt(s.overdue || 0) + parseInt(s.due_today || 0) + parseInt(s.upcoming || 0);
    document.getElementById('schedCountOverdue').textContent  = s.overdue ?? 0;
    document.getElementById('schedCountDue').textContent      = s.due_today ?? 0;
    document.getElementById('schedCountUpcoming').textContent = s.upcoming ?? 0;
}

function setFilter(val) {
    currentFilter = val;
    document.querySelectorAll('#radarFilterPills .gfx-seg').forEach(b => {
        b.classList.toggle('active', b.dataset.filter === val);
    });
    const extraSel = document.getElementById('extraFilterSelect');
    if (extraSel) {
        extraSel.value = ['in_discussion', 'contacted', 'converted', 'closed'].includes(val) ? val : '';
    }
    fetchFullRadar(true);
}

function handleExtraFilter(val) {
    if (val) setFilter(val);
    else setFilter('pending');
}

function setSort(val) {
    currentSort = val;
    renderRadarTable();
}

function handleSearch(val) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        searchQuery = val.trim().toLowerCase();
        document.getElementById('searchClearBtn').style.display = searchQuery ? 'block' : 'none';
        renderRadarTable();
    }, 150);
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    searchQuery = '';
    document.getElementById('searchClearBtn').style.display = 'none';
    renderRadarTable();
}

/* ── Render Tab 1: Response Queue ── */
function renderRadarTable() {
    const tbody = document.getElementById('radarTableBody');
    let items = [...radarItems];

    if (searchQuery) {
        items = items.filter(v =>
            (v.company_name && v.company_name.toLowerCase().includes(searchQuery)) ||
            (v.client_name && v.client_name.toLowerCase().includes(searchQuery)) ||
            (v.phone && v.phone.toLowerCase().includes(searchQuery)) ||
            (v.categories && v.categories.toLowerCase().includes(searchQuery)) ||
            (v.remarks && v.remarks.toLowerCase().includes(searchQuery))
        );
    }

    if (currentSort === 'urgency') {
        const rank = { hot: 3, warm: 2, cold: 1 };
        items.sort((a, b) => (b.is_urgent - a.is_urgent) || ((rank[b.interest_level] || 0) - (rank[a.interest_level] || 0)));
    } else if (currentSort === 'followup_due') {
        items.sort((a, b) => {
            if (!a.next_followup_date && !b.next_followup_date) return 0;
            if (!a.next_followup_date) return 1;
            if (!b.next_followup_date) return -1;
            return new Date(a.next_followup_date) - new Date(b.next_followup_date);
        });
    } else if (currentSort === 'interest') {
        const rank = { hot: 3, warm: 2, cold: 1 };
        items.sort((a, b) => (rank[b.interest_level] || 0) - (rank[a.interest_level] || 0));
    } else if (currentSort === 'newest') {
        items.sort((a, b) => parseInt(b.id) - parseInt(a.id));
    }

    document.getElementById('visibleCountText').textContent = `Showing ${items.length} lead${items.length === 1 ? '' : 's'}`;

    if (items.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="gfx-state-cell">
                    <div style="color:#94a3b8;">
                        <i class="fas fa-check-circle" style="font-size:28px; color:#cbd5e1 !important; margin-bottom:8px;"></i>
                        <div style="font-size:14px; font-weight:700; color:#475569;">Queue is clear for this filter</div>
                        <div style="font-size:12.5px; margin-top:3px;">Live radar is listening for incoming field visits.</div>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    const stageMap = {
        pending:       ['Pending', 'pill-pending'],
        contacted:     ['Contacted', 'pill-contacted'],
        in_discussion: ['In Discussion', 'pill-discussion'],
        converted:     ['Converted', 'pill-converted'],
        closed:        ['Closed', 'pill-closed']
    };

    let html = '';
    items.forEach(v => {
        const isUrgent = v.is_urgent == 1;
        const initial  = (v.company_name || v.client_name || 'C').trim().charAt(0).toUpperCase();
        const cleanWa  = formatWhatsAppNumber((v.whatsapp || v.phone || '').trim());
        const waMsg    = encodeURIComponent(`Hello ${v.client_name}, this is Graphicafix management following up regarding ${v.company_name}.`);
        const fCount   = parseInt(v.followup_count || 0);
        const [stLabel, stClass] = stageMap[v.status] || ['Pending', 'pill-pending'];
        const heatDot = v.interest_level === 'hot' ? 'dot-red' : (v.interest_level === 'warm' ? 'dot-amber' : 'dot-slate');
        const heatLbl = v.interest_level === 'hot' ? 'Hot' : (v.interest_level === 'warm' ? 'Warm' : 'Cold');

        let dueHtml = '<span class="gfx-due-line due-none">No follow-up set</span>';
        if (v.status === 'converted' || v.status === 'closed') {
            dueHtml = `<span class="gfx-due-line due-done"><i class="fas fa-check-circle"></i> ${stLabel}</span>`;
        } else if (v.followup_status === 'overdue') {
            dueHtml = `<span class="gfx-due-line due-overdue"><i class="fas fa-exclamation-circle"></i> Overdue · ${escapeHtml(v.formatted_next_followup || '')}</span>`;
        } else if (v.followup_status === 'due_today') {
            dueHtml = `<span class="gfx-due-line due-today"><i class="fas fa-clock"></i> Today · ${escapeHtml(v.formatted_next_followup || '')}</span>`;
        } else if (v.followup_status === 'upcoming') {
            dueHtml = `<span class="gfx-due-line due-upcoming"><i class="far fa-calendar"></i> ${escapeHtml(v.formatted_next_followup || '')}</span>`;
        }

        const cats = (v.categories_arr || []);
        const catsHtml = cats.length
            ? cats.slice(0, 2).map(c => `<span class="gfx-service-tag">${escapeHtml(c)}</span>`).join('') + (cats.length > 2 ? `<span class="gfx-service-tag">+${cats.length - 2}</span>` : '')
            : `<span class="gfx-service-tag">General</span>`;

        html += `
            <tr class="${isUrgent ? 'row-urgent' : ''}" onclick="openResponseDrawer(${v.id}, 'log')">
                <td>
                    <div class="gfx-client-cell">
                        <div class="gfx-avatar ${isUrgent ? 'urgent' : ''}">${escapeHtml(initial)}</div>
                        <div style="min-width:0;">
                            <div class="gfx-company-name">
                                <span>${escapeHtml(v.company_name)}</span>
                                ${isUrgent ? '<span class="gfx-urgent-pill">Urgent</span>' : ''}
                            </div>
                            <div class="gfx-client-sub">${escapeHtml(v.client_name)} &middot; Rep: ${escapeHtml(v.sales_rep_name)} &middot; ${escapeHtml(v.time_ago || '')}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div><span class="gfx-pill ${stClass}">${stLabel}</span></div>
                    <div class="gfx-heat-tag"><span class="heat-dot ${heatDot}"></span> ${heatLbl}</div>
                </td>
                <td>
                    ${dueHtml}
                    <div class="gfx-touch-meta">${fCount} touchpoint${fCount === 1 ? '' : 's'}${v.last_outcome ? ' &middot; ' + escapeHtml(v.last_outcome.replace(/_/g, ' ')) : ''}</div>
                </td>
                <td>
                    <div>${catsHtml}</div>
                    <div class="gfx-window-text">${escapeHtml(v.contact_availability || 'Anytime')}</div>
                </td>
                <td style="text-align:right;" onclick="event.stopPropagation()">
                    <div class="gfx-row-actions">
                        ${cleanWa ? `<a href="https://wa.me/${cleanWa}?text=${waMsg}" target="_blank" class="gfx-icon-act gfx-wa-icon-btn" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>` : ''}
                        <a href="tel:${escapeHtml(v.phone)}" class="gfx-icon-act" title="Call ${escapeHtml(v.phone)}"><i class="fas fa-phone-alt"></i></a>
                        <button type="button" class="gfx-btn gfx-btn-primary gfx-btn-sm" onclick="openResponseDrawer(${v.id}, 'log')">Work Lead</button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

/* ── Tab 2: Scheduled Follow-Ups ── */
function loadScheduledFollowups(showLoading = true) {
    const tbody = document.getElementById('scheduledTableBody');
    if (showLoading && scheduledItems.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="gfx-state-cell"><div class="gfx-loader"><i class="fas fa-circle-notch fa-spin"></i> Loading schedule...</div></td></tr>`;
    }

    fetch('sales_visits_api.php?action=fetch_all&filter=scheduled&sort=followup_due')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            scheduledItems = data.items || [];
            if (data.stats) renderStats(data.stats);
            renderScheduledTable();
        });
}

function setScheduledFilter(filter, btn) {
    currentSchedFilter = filter;
    document.querySelectorAll('#schedFilterPills .gfx-seg').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    renderScheduledTable();
}

function renderScheduledTable() {
    const tbody = document.getElementById('scheduledTableBody');
    let items = [...scheduledItems];

    if (currentSchedFilter !== 'all') {
        items = items.filter(v => v.followup_status === currentSchedFilter);
    }

    if (items.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="gfx-state-cell">No scheduled follow-ups under this filter.</td></tr>`;
        return;
    }

    let html = '';
    items.forEach(v => {
        const cleanWa = formatWhatsAppNumber((v.whatsapp || v.phone || '').trim());
        const initial = (v.company_name || 'C').trim().charAt(0).toUpperCase();
        const fCount  = parseInt(v.followup_count || 0);
        const isOver  = v.followup_status === 'overdue';

        let dueHtml = `<span class="gfx-due-line due-upcoming"><i class="far fa-calendar"></i> ${escapeHtml(v.formatted_next_followup || '')}</span>`;
        if (isOver) dueHtml = `<span class="gfx-due-line due-overdue"><i class="fas fa-exclamation-triangle"></i> Overdue · ${escapeHtml(v.formatted_next_followup || '')}</span>`;
        else if (v.followup_status === 'due_today') dueHtml = `<span class="gfx-due-line due-today"><i class="fas fa-clock"></i> Today · ${escapeHtml(v.formatted_next_followup || '')}</span>`;

        html += `
            <tr class="${isOver ? 'row-urgent' : ''}" onclick="openResponseDrawer(${v.id}, 'log')">
                <td>
                    <div class="gfx-client-cell">
                        <div class="gfx-avatar ${isOver ? 'urgent' : ''}">${escapeHtml(initial)}</div>
                        <div>
                            <div class="gfx-company-name">${escapeHtml(v.company_name)}</div>
                            <div class="gfx-client-sub">${escapeHtml(v.client_name)} &middot; ${escapeHtml(v.phone)}</div>
                        </div>
                    </div>
                </td>
                <td>${dueHtml}</td>
                <td>
                    <div style="font-size:12.5px; font-weight:700; color:#334155; text-transform:capitalize;">${v.last_outcome ? escapeHtml(v.last_outcome.replace(/_/g, ' ')) : 'Initial Visit'}</div>
                    <div class="gfx-touch-meta">${fCount} touchpoint${fCount === 1 ? '' : 's'}${v.last_followup_by ? ' &middot; ' + escapeHtml(v.last_followup_by) : ''}</div>
                </td>
                <td><span style="font-size:12.5px; font-weight:600; color:#334155;">${escapeHtml(v.sales_rep_name)}</span></td>
                <td style="text-align:right;" onclick="event.stopPropagation()">
                    <div class="gfx-row-actions">
                        ${cleanWa ? `<a href="https://wa.me/${cleanWa}" target="_blank" class="gfx-icon-act gfx-wa-icon-btn" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>` : ''}
                        <button type="button" class="gfx-btn gfx-btn-primary gfx-btn-sm" onclick="openResponseDrawer(${v.id}, 'log')">Log Call</button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

/* ── Unified Response Drawer ── */
function openResponseDrawer(visitId, defaultTab = 'log') {
    const v = radarItems.find(x => x.id == visitId) || scheduledItems.find(x => x.id == visitId);
    if (!v) return;
    activeVisit = v;

    renderResponseHeader(v);
    renderTemplates(v);
    resetFollowupForm(v);
    switchDrawerTab(defaultTab);
    loadDrawerTimeline(visitId);

    document.getElementById('responseDrawer').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeResponseDrawer() {
    document.getElementById('responseDrawer').classList.remove('active');
    document.body.style.overflow = '';
    activeVisit = null;
}

function renderResponseHeader(v) {
    const initial = (v.company_name || v.client_name || 'C').trim().charAt(0).toUpperCase();
    const isUrgent = v.is_urgent == 1;
    document.getElementById('rdAvatar').textContent  = initial;
    document.getElementById('rdCompany').textContent = v.company_name;
    document.getElementById('rdMeta').textContent    = `${v.client_name} · ${v.phone} · Rep: ${v.sales_rep_name}`;
    document.getElementById('rdTouchCount').textContent = v.followup_count || 0;

    const cleanWa = formatWhatsAppNumber((v.whatsapp || v.phone || '').trim());
    const waMsg   = encodeURIComponent(`Hello ${v.client_name}, this is Graphicafix management following up regarding ${v.company_name}.`);

    let actions = '';
    if (cleanWa) {
        actions += `<a href="https://wa.me/${cleanWa}?text=${waMsg}" target="_blank" class="gfx-wa-pill-btn"><i class="fab fa-whatsapp"></i> WhatsApp</a>`;
    }
    actions += `
        <a href="tel:${escapeHtml(v.phone)}" class="gfx-btn gfx-btn-outline gfx-btn-sm"><i class="fas fa-phone-alt"></i> Call</a>
        <button type="button" class="gfx-btn gfx-btn-outline gfx-btn-sm" onclick="copyText('${escapeJsString(v.phone)}', 'Phone copied')"><i class="far fa-copy"></i> Copy</button>
        <button type="button" class="gfx-btn gfx-btn-outline gfx-btn-sm" style="${isUrgent ? 'background:#fef2f2; color:#dc2626 !important; border-color:#fca5a5;' : ''}" onclick="toggleUrgent(${v.id})">
            <i class="fas fa-bell"></i> ${isUrgent ? 'Urgent' : 'Mark Urgent'}
        </button>
    `;
    if (v.status !== 'converted') {
        actions += `<button type="button" class="gfx-btn gfx-btn-outline gfx-btn-sm" style="color:#15803d !important; border-color:#bbf7d0;" onclick="convertToLead(${v.id})"><i class="fas fa-user-check"></i> Convert Lead</button>`;
    }
    document.getElementById('rdQuickActions').innerHTML = actions;

    const stages = [
        ['pending', 'Pending'],
        ['contacted', 'Contacted'],
        ['in_discussion', 'In Discussion'],
        ['converted', 'Converted'],
        ['closed', 'Closed']
    ];
    document.getElementById('rdStageSwitcher').innerHTML = stages.map(([k, lbl]) => `
        <button type="button" class="gfx-stage-btn ${v.status === k ? 'active' : ''}" onclick="quickUpdateStage(${v.id}, '${k}')">${lbl}</button>
    `).join('');

    const cats = (v.categories_arr || []).join(', ') || 'General';
    const subs = (v.subcategories_arr || []).join(', ');
    document.getElementById('rdDetailsCard').innerHTML = `
        <div class="gfx-info-grid">
            <div><div class="gfx-info-k">Preferred Window</div><div class="gfx-info-v">${escapeHtml(v.contact_availability || 'Anytime')}</div></div>
            <div><div class="gfx-info-k">Lead Heat</div><div class="gfx-info-v" style="text-transform:capitalize;">${escapeHtml(v.interest_level)} ${isUrgent ? '· Urgent' : ''}</div></div>
            <div><div class="gfx-info-k">Sales Rep</div><div class="gfx-info-v">${escapeHtml(v.sales_rep_name)} (${escapeHtml(v.time_ago || '')})</div></div>
            <div><div class="gfx-info-k">Next Follow-Up</div><div class="gfx-info-v">${escapeHtml(v.formatted_next_followup || 'Not scheduled')}</div></div>
        </div>
        <div style="margin-top:8px;"><div class="gfx-info-k">Requirements</div><div class="gfx-info-v" style="font-weight:600;">${escapeHtml(cats)}${subs ? ' — ' + escapeHtml(subs) : ''}</div></div>
        ${v.remarks ? `<div style="margin-top:10px; padding-top:10px; border-top:1px solid #e2e8f0;"><div class="gfx-info-k">Field Remarks</div><div style="margin-top:4px; color:#334155; line-height:1.5;">${escapeHtml(v.remarks)}</div></div>` : ''}
    `;
}

function renderTemplates(v) {
    const cleanWa = formatWhatsAppNumber((v.whatsapp || v.phone || '').trim());
    const cats = (v.categories_arr && v.categories_arr.length) ? v.categories_arr.join(', ') : 'our creative & digital services';

    const templates = [
        {
            title: '1. Official Intro & Quick Call',
            text: `Hello ${v.client_name}, this is Graphicafix management following up on your discussion with ${v.sales_rep_name} regarding ${v.company_name}. When is a good time for a quick 5-minute call?`
        },
        {
            title: '2. Portfolio & Pricing Packages',
            text: `Hello ${v.client_name}, regarding your interest in ${cats} for ${v.company_name}, our team at Graphicafix has prepared initial options for you. Would you like us to share our portfolio and packages here?`
        },
        {
            title: '3. Friendly Follow-Up Check-In',
            text: `Hi ${v.client_name}, just checking in regarding our discussion for ${v.company_name}. Please let us know if you need any updated quote or samples — happy to help!`
        }
    ];

    document.getElementById('rdTemplatesList').innerHTML = templates.map(t => `
        <div class="gfx-tpl-card">
            <div class="gfx-tpl-head">
                <span class="gfx-tpl-title">${escapeHtml(t.title)}</span>
                <div style="display:flex; gap:6px;">
                    <button type="button" class="gfx-btn gfx-btn-outline gfx-btn-sm" onclick="copyText('${escapeJsString(t.text)}', 'Message copied')">
                        <i class="far fa-copy"></i> Copy
                    </button>
                    ${cleanWa ? `<a href="https://wa.me/${cleanWa}?text=${encodeURIComponent(t.text)}" target="_blank" class="gfx-wa-pill-btn" style="padding:5px 11px; font-size:12px;"><i class="fab fa-whatsapp"></i> Send</a>` : ''}
                </div>
            </div>
            <div class="gfx-tpl-body">${escapeHtml(t.text)}</div>
        </div>
    `).join('');
}

function switchDrawerTab(tab) {
    ['Log', 'Templates', 'History'].forEach(t => {
        const key = t.toLowerCase();
        document.getElementById('dtab' + t).classList.toggle('active', key === tab);
        document.getElementById('drawerPanel' + t).classList.toggle('active', key === tab);
    });
}

function resetFollowupForm(v) {
    document.getElementById('fModalVisitId').value  = v.id;
    document.getElementById('fModalStatus').value   = (v.status === 'pending') ? 'in_discussion' : v.status;
    document.getElementById('fModalNotes').value    = '';
    document.getElementById('fModalNextDate').value = '';

    const callRadio = document.querySelector('#channelChipGroup input[value="call"]');
    if (callRadio) { callRadio.checked = true; syncChipGroup(callRadio); }
    const outRadio = document.querySelector('#outcomeChipGroup input[value="followup_needed"]');
    if (outRadio) { outRadio.checked = true; syncOutcomeChip(outRadio); }
}

function syncChipGroup(input) {
    const wrap = input.closest('.gfx-chip-row, .gfx-outcome-grid');
    if (!wrap) return;
    wrap.querySelectorAll('.gfx-chip').forEach(c => c.classList.remove('active'));
    input.closest('.gfx-chip').classList.add('active');
}

function syncOutcomeChip(input) {
    syncChipGroup(input);
    const st = document.getElementById('fModalStatus');
    if (input.value === 'converted') st.value = 'converted';
    else if (input.value === 'not_interested' || input.value === 'closed') st.value = 'closed';
    else st.value = 'in_discussion';
}

function appendFollowupNote(text) {
    const ta = document.getElementById('fModalNotes');
    ta.value = ta.value ? ta.value.trim() + ' ' + text : text;
    ta.focus();
}

function submitFollowupRecord(e) {
    e.preventDefault();
    const btn = document.getElementById('fModalSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Saving...';

    const fd = new FormData(document.getElementById('followupForm'));
    fd.append('action', 'add_followup');

    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> <span>Save Follow-Up</span>';
            if (data.success) {
                showToast('Follow-up saved', 'success');
                const vid = document.getElementById('fModalVisitId').value;
                document.getElementById('fModalNotes').value = '';
                document.getElementById('fModalNextDate').value = '';
                fetchFullRadar(false);
                if (currentTab === 'scheduled') loadScheduledFollowups(false);
                else if (currentTab === 'reporting') loadReportingData();
                loadDrawerTimeline(vid);
                switchDrawerTab('history');
            } else {
                showToast(data.message || 'Failed to save follow-up', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> <span>Save Follow-Up</span>';
            showToast('Network error while saving', 'error');
        });
}

function loadDrawerTimeline(visitId) {
    const body = document.getElementById('rdTimelineBody');
    body.innerHTML = '<div class="gfx-loader"><i class="fas fa-circle-notch fa-spin"></i> Loading history...</div>';

    fetch(`sales_visits_api.php?action=get_followups&visit_id=${visitId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                body.innerHTML = `<p style="color:#dc2626; font-size:13px;">${escapeHtml(data.message)}</p>`;
                return;
            }
            const list = data.followups || [];
            document.getElementById('rdTouchCount').textContent = list.length;

            let html = '<div class="gfx-timeline">';
            if (list.length === 0) {
                html += `<div style="padding:14px; background:#fff; border:1px dashed #cbd5e1; border-radius:10px; text-align:center; color:#64748b; font-size:13px; margin-bottom:14px;">No follow-ups logged yet.</div>`;
            } else {
                list.forEach(f => {
                    html += `
                        <div class="gfx-tl-item">
                            <span class="gfx-tl-dot"></span>
                            <div class="gfx-tl-card">
                                <div class="gfx-tl-top">
                                    <div>
                                        <strong style="font-size:13px; color:#0f172a;">${escapeHtml(f.channel_label)}</strong>
                                        <span class="gfx-pill pill-contacted" style="margin-left:6px;">${escapeHtml(f.outcome_label)}</span>
                                    </div>
                                    <span style="font-size:11.5px; color:#94a3b8;">${escapeHtml(f.time_ago)}</span>
                                </div>
                                <div class="gfx-tl-note">${escapeHtml(f.notes)}</div>
                                <div class="gfx-tl-meta">
                                    <span>By ${escapeHtml(f.user_name)}</span>
                                    ${f.formatted_next_date ? `<span style="color:#024442; font-weight:700;">Next: ${escapeHtml(f.formatted_next_date)}</span>` : `<span>${escapeHtml(f.formatted_date)}</span>`}
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            const v = data.visit || activeVisit;
            if (v) {
                html += `
                    <div class="gfx-tl-item">
                        <span class="gfx-tl-dot dot-init"></span>
                        <div class="gfx-tl-card" style="background:#f8fafc;">
                            <div class="gfx-tl-top">
                                <strong style="font-size:13px; color:#065f46;">Initial Field Visit Logged</strong>
                                <span style="font-size:11.5px; color:#94a3b8;">${escapeHtml(v.time_ago || '')}</span>
                            </div>
                            <div class="gfx-tl-meta"><span>By ${escapeHtml(v.sales_rep_name)}</span></div>
                        </div>
                    </div>
                `;
            }
            html += '</div>';
            body.innerHTML = html;
        });
}

function quickUpdateStage(visitId, newStage) {
    const v = radarItems.find(x => x.id == visitId) || scheduledItems.find(x => x.id == visitId);
    if (!v || v.status === newStage) return;

    const fd = new FormData();
    fd.append('action', 'update_status');
    fd.append('visit_id', visitId);
    fd.append('status', newStage);
    fd.append('interest_level', v.interest_level || 'warm');

    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(`Stage updated to ${newStage.replace(/_/g, ' ')}`, 'success');
                fetchFullRadar(false);
                if (currentTab === 'scheduled') loadScheduledFollowups(false);
            } else {
                showToast(data.message || 'Stage update failed', 'error');
            }
        });
}

function toggleUrgent(id) {
    const fd = new FormData();
    fd.append('action', 'toggle_urgent');
    fd.append('visit_id', id);
    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Urgent status updated', 'success');
                fetchFullRadar(false);
            } else {
                showToast(data.message || 'Error updating urgent status', 'error');
            }
        });
}

function convertToLead(visitId) {
    if (!confirm('Convert this visit into a CRM Lead?')) return;
    const fd = new FormData();
    fd.append('action', 'convert_to_lead');
    fd.append('visit_id', visitId);
    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Converted to CRM Lead!', 'success');
                fetchFullRadar(false);
            } else {
                showToast(data.message || 'Conversion failed', 'error');
            }
        });
}

/* ── Tab 3: Reporting ── */
function setReportPreset(preset, btn) {
    currentReportPreset = preset;
    document.querySelectorAll('#reportPresetTabs .gfx-seg').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    const customWrap = document.getElementById('customDateRangeWrap');
    customWrap.style.display = (preset === 'custom') ? 'inline-flex' : 'none';
    if (preset !== 'custom') loadReportingData();
}

function loadReportingData() {
    const repId = document.getElementById('reportRepSelect').value;
    let url = `sales_visits_api.php?action=get_reporting_metrics&date_range=${currentReportPreset}&rep_id=${repId}`;
    if (currentReportPreset === 'custom') {
        url += `&start_date=${document.getElementById('reportStartDate').value}&end_date=${document.getElementById('reportEndDate').value}`;
    }

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const k = data.kpis || {};
            document.getElementById('repKpiVisits').textContent     = k.total_visits ?? 0;
            document.getElementById('repKpiFollowups').textContent  = k.total_followups ?? 0;
            document.getElementById('repKpiAvgF').textContent       = k.avg_followups ?? 0;
            document.getElementById('repKpiEngagement').textContent = (k.engagement_rate ?? 0) + '%';
            document.getElementById('repKpiConverted').textContent  = k.converted_visits ?? 0;
            document.getElementById('repKpiWinRate').textContent    = (k.conversion_rate ?? 0) + '%';
            document.getElementById('repKpiOverdue').textContent    = k.overdue_count ?? 0;

            const lb = data.leaderboard || [];
            document.getElementById('leaderboardTableBody').innerHTML = lb.length === 0
                ? `<tr><td colspan="6" class="gfx-state-cell">No rep activity in this period.</td></tr>`
                : lb.map((r, i) => `
                    <tr style="cursor:default;">
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div class="gfx-avatar" style="width:32px; height:32px; font-size:12px;">${i + 1}</div>
                                <strong style="color:#0f172a; font-size:13.5px;">${escapeHtml(r.sales_rep_name)}</strong>
                            </div>
                        </td>
                        <td style="text-align:center; font-weight:700;">${r.total_visits}</td>
                        <td style="text-align:center; font-weight:700; color:#0284c7;">${r.total_followups || 0}</td>
                        <td style="text-align:center; font-weight:700; color:#16a34a;">${r.converted_count}</td>
                        <td style="text-align:center;"><span class="gfx-pill pill-converted">${r.win_rate}%</span></td>
                        <td style="text-align:right; font-size:12px; color:#64748b;">${escapeHtml(r.last_activity)}</td>
                    </tr>
                `).join('');

            const outcomes = data.outcomes || [];
            document.getElementById('outcomeDistributionWrap').innerHTML = outcomes.length === 0
                ? `<span style="font-size:12.5px; color:#94a3b8;">No outcomes recorded yet.</span>`
                : outcomes.map(o => `
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:12.5px;">
                            <span style="font-weight:700; color:#334155;">${escapeHtml(o.label)}</span>
                            <span style="color:#64748b;"><strong>${o.count}</strong> (${o.percent}%)</span>
                        </div>
                        <div class="gfx-prog-track"><div class="gfx-prog-fill" style="width:${o.percent}%;"></div></div>
                    </div>
                `).join('');

            const channels = data.channels || [];
            document.getElementById('channelDistributionWrap').innerHTML = channels.length === 0
                ? `<span style="font-size:12.5px; color:#94a3b8;">No channels recorded yet.</span>`
                : channels.map(c => `
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:12.5px;">
                            <span style="font-weight:700; color:#334155;">${escapeHtml(c.label)}</span>
                            <span style="color:#64748b;"><strong>${c.count}</strong> (${c.percent}%)</span>
                        </div>
                        <div class="gfx-prog-track"><div class="gfx-prog-fill" style="width:${c.percent}%; background:#0284c7;"></div></div>
                    </div>
                `).join('');
        });
}

function exportReportingCSV(type) {
    const params = new URLSearchParams({
        action: 'export_sheet',
        export_type: type,
        rep_id: document.getElementById('reportRepSelect').value
    });
    if (currentReportPreset === 'custom') {
        params.append('start_date', document.getElementById('reportStartDate').value);
        params.append('end_date', document.getElementById('reportEndDate').value);
    } else if (currentReportPreset !== 'all') {
        params.append('preset', currentReportPreset);
    }
    window.location.href = `sales_visits_api.php?${params.toString()}`;
    showToast('Downloading CSV...', 'info');
}

/* ── Export Modal & Reps ── */
function loadSalesReps() {
    fetch('sales_visits_api.php?action=get_reps')
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.reps) return;
            const s1 = document.getElementById('exportRepSelect');
            const s2 = document.getElementById('reportRepSelect');
            data.reps.forEach(rep => {
                const opt = `<option value="${rep.id}">${escapeHtml(rep.name)}</option>`;
                if (s1) s1.innerHTML += opt;
                if (s2) s2.innerHTML += opt;
            });
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
    document.querySelectorAll('#exportPresetGroup .gfx-mini-chip').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    const s = document.getElementById('exportStartDate');
    const e = document.getElementById('exportEndDate');
    const now = new Date();
    const fmt = d => d.toISOString().split('T')[0];

    if (preset === 'all') { s.value = ''; e.value = ''; }
    else if (preset === 'today') { s.value = fmt(now); e.value = fmt(now); }
    else if (preset === 'week') { const p = new Date(); p.setDate(p.getDate() - 7); s.value = fmt(p); e.value = fmt(now); }
    else if (preset === 'month') { s.value = fmt(new Date(now.getFullYear(), now.getMonth(), 1)); e.value = fmt(now); }
}
function executeExport(e) {
    e.preventDefault();
    const type = document.querySelector('input[name="export_type"]:checked').value;
    const params = new URLSearchParams({
        action: 'export_sheet',
        export_type: type,
        rep_id: document.getElementById('exportRepSelect').value,
        start_date: document.getElementById('exportStartDate').value,
        end_date: document.getElementById('exportEndDate').value
    });
    window.location.href = `sales_visits_api.php?${params.toString()}`;
    closeExportModal();
    showToast('Downloading spreadsheet...', 'info');
}

/* ── Audio & Desktop Alerts ── */
function toggleAudioMute() {
    isAudioMuted = !isAudioMuted;
    const btn = document.getElementById('audioMuteBtn');
    if (isAudioMuted) {
        btn.innerHTML = '<i class="fas fa-volume-mute" style="color:#dc2626 !important;"></i>';
        btn.title = 'Sound muted';
        showToast('Alert sound muted', 'warning');
    } else {
        btn.innerHTML = '<i class="fas fa-volume-up"></i>';
        btn.title = 'Sound active';
        playNotificationChime();
        showToast('Alert sound enabled', 'success');
    }
}

function playNotificationChime() {
    if (isAudioMuted) return;
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        if (!audioCtx) audioCtx = new Ctx();
        if (audioCtx.state === 'suspended') audioCtx.resume();
        const now = audioCtx.currentTime;
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, now);
        osc.frequency.exponentialRampToValueAtTime(1760, now + 0.15);
        gain.gain.setValueAtTime(0.25, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start(now);
        osc.stop(now + 0.45);
    } catch (e) {}
}

function checkNotificationPermission() {
    const btn = document.getElementById('pushNotifyBtn');
    if (!('Notification' in window)) { if (btn) btn.style.display = 'none'; return; }
    if (Notification.permission === 'granted' && btn) {
        btn.innerHTML = '<i class="fas fa-bell" style="color:#16a34a !important;"></i>';
        btn.title = 'Desktop alerts active';
    }
}

function enablePushNotifications() {
    if (!('Notification' in window)) { showToast('Browser does not support desktop alerts', 'warning'); return; }
    Notification.requestPermission().then(p => {
        checkNotificationPermission();
        if (p === 'granted') showToast('Desktop alerts enabled', 'success');
    });
}

function triggerDesktopAlert(v) {
    if ('Notification' in window && Notification.permission === 'granted') {
        const n = new Notification((v.is_urgent == 1 ? 'URGENT: ' : '') + 'New Visit — ' + v.company_name, {
            body: `${v.client_name} · ${v.contact_availability || 'Contact ASAP'}`,
            icon: '../assets/images/icon.png',
            tag: 'visit-' + v.id
        });
        n.onclick = () => { window.focus(); openResponseDrawer(v.id, 'log'); };
    }
}

/* ── Utilities ── */
function setNextDatePreset(inputId, preset) {
    const now = new Date();
    if (preset === 'today_pm') now.setHours(17, 0, 0, 0);
    else if (preset === 'tomorrow_am') { now.setDate(now.getDate() + 1); now.setHours(11, 0, 0, 0); }
    else if (preset === 'tomorrow_pm') { now.setDate(now.getDate() + 1); now.setHours(16, 0, 0, 0); }
    else if (preset === 'in_2_days') { now.setDate(now.getDate() + 2); now.setHours(15, 0, 0, 0); }
    else if (preset === 'next_week') { const d = now.getDay(); now.setDate(now.getDate() + (d === 0 ? 1 : 8 - d)); now.setHours(11, 0, 0, 0); }
    const pad = n => String(n).padStart(2, '0');
    document.getElementById(inputId).value = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
}

function formatWhatsAppNumber(phone) {
    if (!phone) return '';
    let c = phone.replace(/[^0-9]/g, '');
    if (c.startsWith('03') && c.length === 11) c = '92' + c.substring(1);
    else if (c.startsWith('00')) c = c.substring(2);
    else if (c.length === 10 && c.startsWith('3')) c = '92' + c;
    return c;
}

function copyText(txt, label = 'Copied') {
    navigator.clipboard.writeText(txt).then(() => showToast(label, 'success'));
}

function showToast(msg, type = 'info') {
    const host = document.getElementById('gfxToastHost');
    const el = document.createElement('div');
    el.className = `gfx-toast toast-${type}`;
    const ico = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');
    el.innerHTML = `<i class="fas ${ico}"></i><span>${escapeHtml(msg)}</span>`;
    host.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 220); }, 3200);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
function escapeJsString(str) {
    if (!str) return '';
    return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '');
}
</script>

<?php include 'dashboard_footer.php'; ?>
