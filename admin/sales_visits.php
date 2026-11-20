<?php
include 'dashboard_header.php';

// Auth: Sales reps, Admins, Super Admins, PMs, Managers can access
$allowed_roles = ['sales', 'super_admin', 'superadmin', 'admin', 'manager', 'pm'];
if (!in_array($role, $allowed_roles)) {
    header("Location: index.php?error=unauthorized");
    exit;
}

$user_id_int   = intval($_SESSION['user_id'] ?? 0);
$is_admin_user = in_array($role, ['super_admin', 'superadmin', 'admin', 'manager', 'pm']);
?>

<div class="gfx-sales-page">

    <!-- ── Top Page Header ── -->
    <div class="gfx-page-head">
        <div>
            <h1 class="gfx-h1">Client Field Visits</h1>
            <p class="gfx-sub">Log ground visits in seconds and keep every prospect moving forward.</p>
        </div>
        <div class="gfx-head-actions">
            <button type="button" class="gfx-btn gfx-btn-ghost" onclick="loadVisits()" title="Refresh list">
                <i class="fas fa-sync-alt" id="refreshIcon"></i>
            </button>
            <button type="button" class="gfx-btn gfx-btn-outline" onclick="openExportModal()">
                <i class="fas fa-download"></i> <span>Export</span>
            </button>
            <?php if ($is_admin_user): ?>
            <a href="sales_response_unit.php" class="gfx-btn gfx-btn-outline">
                <i class="fas fa-satellite-dish"></i> <span>Response Unit</span>
            </a>
            <?php endif; ?>
            <button type="button" class="gfx-btn gfx-btn-primary" onclick="openVisitDrawer('add')">
                <i class="fas fa-plus"></i> <span>Log Visit</span>
            </button>
        </div>
    </div>

    <!-- ── 4 Clean KPI Cards (Click to Filter) ── -->
    <div class="gfx-kpi-grid">
        <button type="button" class="gfx-kpi active" id="kpi_all" onclick="setFilter('all')">
            <div class="gfx-kpi-top">
                <span class="gfx-kpi-label">Total Visits</span>
                <span class="gfx-kpi-dot dot-slate"></span>
            </div>
            <div class="gfx-kpi-val" id="statTotal">--</div>
        </button>

        <button type="button" class="gfx-kpi" id="kpi_overdue" onclick="setFilter('overdue')">
            <div class="gfx-kpi-top">
                <span class="gfx-kpi-label">Overdue</span>
                <span class="gfx-kpi-dot dot-red"></span>
            </div>
            <div class="gfx-kpi-val val-red" id="statOverdue">--</div>
        </button>

        <button type="button" class="gfx-kpi" id="kpi_due_today" onclick="setFilter('due_today')">
            <div class="gfx-kpi-top">
                <span class="gfx-kpi-label">Due Today</span>
                <span class="gfx-kpi-dot dot-amber"></span>
            </div>
            <div class="gfx-kpi-val val-amber" id="statDueToday">--</div>
        </button>

        <button type="button" class="gfx-kpi" id="kpi_converted" onclick="setFilter('converted')">
            <div class="gfx-kpi-top">
                <span class="gfx-kpi-label">Converted to Leads</span>
                <span class="gfx-kpi-dot dot-green"></span>
            </div>
            <div class="gfx-kpi-val val-green" id="statConverted">--</div>
        </button>
    </div>

    <!-- ── Main Workspace Surface ── -->
    <div class="gfx-surface">

        <!-- Single-Row Clean Filter & Search Toolbar -->
        <div class="gfx-toolbar">
            <div class="gfx-seg-tabs" id="quickFilterPills">
                <button type="button" class="gfx-seg active" data-filter="all" onclick="setFilter('all')">All</button>
                <button type="button" class="gfx-seg" data-filter="pending" onclick="setFilter('pending')">Pending</button>
                <button type="button" class="gfx-seg" data-filter="overdue" onclick="setFilter('overdue')">Overdue</button>
                <button type="button" class="gfx-seg" data-filter="due_today" onclick="setFilter('due_today')">Due Today</button>
                <button type="button" class="gfx-seg" data-filter="in_discussion" onclick="setFilter('in_discussion')">In Discussion</button>
                <button type="button" class="gfx-seg" data-filter="converted" onclick="setFilter('converted')">Converted</button>
            </div>

            <div class="gfx-toolbar-right">
                <select id="extraFilterSelect" class="gfx-select" onchange="handleExtraFilter(this.value)" title="More filters">
                    <option value="">More filters...</option>
                    <option value="urgent">Urgent Only</option>
                    <option value="hot">Hot Priority</option>
                    <option value="contacted">Contacted</option>
                    <option value="closed">Closed / Archived</option>
                </select>

                <select id="sortSelect" class="gfx-select" onchange="setSort(this.value)" title="Sort order">
                    <option value="urgency">Sort: Urgency</option>
                    <option value="followup_due">Sort: Follow-Up Due</option>
                    <option value="newest">Sort: Newest</option>
                    <option value="interest">Sort: Heat Level</option>
                </select>

                <div class="gfx-search">
                    <i class="fas fa-search gfx-search-ico"></i>
                    <input type="text" id="searchInput" oninput="handleSearch(this.value)" placeholder="Search client, company, phone...">
                    <button type="button" id="searchClearBtn" class="gfx-search-clear" onclick="clearSearch()" style="display:none;">&times;</button>
                </div>
            </div>
        </div>

        <!-- Clean 5-Column Table -->
        <div class="gfx-table-wrap">
            <table class="gfx-table" id="visitsTable">
                <thead>
                    <tr>
                        <th style="width:28%;">Client & Company</th>
                        <th style="width:16%;">Stage & Heat</th>
                        <th style="width:22%;">Next Follow-Up</th>
                        <th style="width:18%;">Services & Window</th>
                        <th style="width:16%; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="visitsTableBody">
                    <tr>
                        <td colspan="5" class="gfx-state-cell">
                            <div class="gfx-loader"><i class="fas fa-circle-notch fa-spin"></i> Loading field visits...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Subtle Footer Count -->
        <div class="gfx-table-footer">
            <span id="visibleCountText">Showing 0 visits</span>
            <span class="gfx-footer-tip">Tip: Click any row to open quick follow-up &amp; history</span>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     DRAWER 1: UNIFIED CLIENT WORKSPACE (Follow-Up + Overview & Timeline in ONE Drawer)
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="workspaceDrawer" class="modal-overlay" onclick="if(event.target===this)closeWorkspaceDrawer()">
    <div class="modal-content">
        <div class="modal-header">
            <div style="display:flex; align-items:center; gap:12px; min-width:0;">
                <div class="gfx-avatar-lg" id="wsAvatar">C</div>
                <div style="min-width:0;">
                    <h4 id="wsCompany" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Client Workspace</h4>
                    <div id="wsMeta" class="gfx-drawer-sub">Client · Phone · Rep</div>
                </div>
            </div>
            <button type="button" class="modal-close" onclick="closeWorkspaceDrawer()">&times;</button>
        </div>

        <!-- Sticky Quick Contact & Stage Bar -->
        <div class="gfx-ws-quickbar">
            <div class="gfx-ws-actions" id="wsQuickActions"></div>
            <div class="gfx-ws-stage-row">
                <label class="gfx-micro-label">Stage:</label>
                <div class="gfx-stage-switcher" id="wsStageSwitcher"></div>
            </div>
        </div>

        <!-- Clean 2-Tab Switcher inside Drawer -->
        <div class="gfx-drawer-tabs">
            <button type="button" class="gfx-dtab active" id="dtabLog" onclick="switchDrawerTab('log')">
                <i class="fas fa-plus-circle"></i> Log Follow-Up
            </button>
            <button type="button" class="gfx-dtab" id="dtabHistory" onclick="switchDrawerTab('history')">
                <i class="fas fa-history"></i> Details &amp; Timeline <span class="gfx-dtab-count" id="wsTouchCount">0</span>
            </button>
        </div>

        <!-- Drawer Body -->
        <div class="modal-body" style="padding:20px 24px !important;">

            <!-- PANEL A: Fast 3-Step Follow-Up Logger -->
            <div id="drawerPanelLog" class="gfx-dpanel active">
                <form id="followupForm" onsubmit="submitFollowupRecord(event)">
                    <input type="hidden" name="visit_id" id="fModalVisitId" value="">
                    <input type="hidden" name="status" id="fModalStatus" value="in_discussion">

                    <div class="gfx-field">
                        <label class="gfx-label">1. How did you contact them?</label>
                        <div class="gfx-chip-row" id="channelChipGroup">
                            <label class="gfx-chip active"><input type="radio" name="contact_channel" value="call" checked onchange="syncChipGroup(this)"><i class="fas fa-phone-alt"></i> Call</label>
                            <label class="gfx-chip"><input type="radio" name="contact_channel" value="whatsapp" onchange="syncChipGroup(this)"><i class="fab fa-whatsapp"></i> WhatsApp</label>
                            <label class="gfx-chip"><input type="radio" name="contact_channel" value="meeting" onchange="syncChipGroup(this)"><i class="fas fa-handshake"></i> Meeting</label>
                            <label class="gfx-chip"><input type="radio" name="contact_channel" value="email" onchange="syncChipGroup(this)"><i class="fas fa-envelope"></i> Email</label>
                            <label class="gfx-chip"><input type="radio" name="contact_channel" value="office_visit" onchange="syncChipGroup(this)"><i class="fas fa-building"></i> Office</label>
                        </div>
                    </div>

                    <div class="gfx-field">
                        <label class="gfx-label">2. What was the outcome?</label>
                        <div class="gfx-outcome-grid" id="outcomeChipGroup">
                            <label class="gfx-chip active"><input type="radio" name="outcome" value="followup_needed" checked onchange="syncOutcomeChip(this)">Follow-Up Needed</label>
                            <label class="gfx-chip"><input type="radio" name="outcome" value="interested" onchange="syncOutcomeChip(this)">Interested / Hot</label>
                            <label class="gfx-chip"><input type="radio" name="outcome" value="proposal_sent" onchange="syncOutcomeChip(this)">Quote / Proposal Sent</label>
                            <label class="gfx-chip"><input type="radio" name="outcome" value="meeting_scheduled" onchange="syncOutcomeChip(this)">Meeting Booked</label>
                            <label class="gfx-chip"><input type="radio" name="outcome" value="callback_requested" onchange="syncOutcomeChip(this)">Callback Requested</label>
                            <label class="gfx-chip"><input type="radio" name="outcome" value="no_answer" onchange="syncOutcomeChip(this)">No Answer</label>
                            <label class="gfx-chip chip-green"><input type="radio" name="outcome" value="converted" onchange="syncOutcomeChip(this)">Won / Converted</label>
                            <label class="gfx-chip chip-muted"><input type="radio" name="outcome" value="not_interested" onchange="syncOutcomeChip(this)">Not Interested</label>
                        </div>
                    </div>

                    <div class="gfx-field">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <label class="gfx-label" style="margin:0;">3. Quick Summary Note <span class="req">*</span></label>
                        </div>
                        <div class="gfx-quick-notes">
                            <button type="button" class="gfx-mini-chip" onclick="appendFollowupNote('Called client and discussed requirements.')">+ Discussed scope</button>
                            <button type="button" class="gfx-mini-chip" onclick="appendFollowupNote('Sent pricing & portfolio on WhatsApp.')">+ Sent quote</button>
                            <button type="button" class="gfx-mini-chip" onclick="appendFollowupNote('Will confirm after discussing with partner.')">+ Partner review</button>
                            <button type="button" class="gfx-mini-chip" onclick="appendFollowupNote('No answer — dropped a WhatsApp message.')">+ No answer</button>
                        </div>
                        <textarea name="notes" id="fModalNotes" rows="3" required class="gfx-input" placeholder="Key takeaway, budget, or client feedback..."></textarea>
                    </div>

                    <div class="gfx-field gfx-schedule-box">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <label class="gfx-label" style="margin:0;"><i class="far fa-calendar-alt"></i> Next Follow-Up Reminder</label>
                            <button type="button" class="gfx-text-btn" onclick="document.getElementById('fModalNextDate').value=''">Clear</button>
                        </div>
                        <div class="gfx-quick-notes" style="margin-bottom:8px;">
                            <button type="button" class="gfx-mini-chip" onclick="setNextDatePreset('fModalNextDate','today_pm')">Today 5 PM</button>
                            <button type="button" class="gfx-mini-chip" onclick="setNextDatePreset('fModalNextDate','tomorrow_am')">Tomorrow 11 AM</button>
                            <button type="button" class="gfx-mini-chip" onclick="setNextDatePreset('fModalNextDate','tomorrow_pm')">Tomorrow 4 PM</button>
                            <button type="button" class="gfx-mini-chip" onclick="setNextDatePreset('fModalNextDate','in_2_days')">In 2 Days</button>
                            <button type="button" class="gfx-mini-chip" onclick="setNextDatePreset('fModalNextDate','next_week')">Next Monday</button>
                        </div>
                        <input type="datetime-local" name="next_followup_date" id="fModalNextDate" class="gfx-input">
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px;">
                        <button type="submit" class="gfx-btn gfx-btn-primary" id="fModalSubmitBtn" style="width:100%; justify-content:center; padding:11px 18px;">
                            <i class="fas fa-check"></i> <span>Save Follow-Up</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- PANEL B: Visit Details + Full Timeline -->
            <div id="drawerPanelHistory" class="gfx-dpanel">
                <div id="wsDetailsCard" class="gfx-info-card"></div>
                <div class="gfx-section-heading" style="margin-top:18px;">Interaction History</div>
                <div id="wsTimelineBody">
                    <div class="gfx-loader"><i class="fas fa-circle-notch fa-spin"></i> Loading history...</div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     DRAWER 2: STREAMLINED LOG / EDIT FIELD VISIT (Takes <20 seconds)
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="visitModal" class="modal-overlay" onclick="if(event.target===this)closeVisitDrawer()">
    <div class="modal-content">
        <div class="modal-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <div class="modal-header-icon"><i class="fas fa-street-view"></i></div>
                <div>
                    <h4 id="visitModalTitle">Log Client Visit</h4>
                    <div class="gfx-drawer-sub">Quick field capture — takes less than 20 seconds</div>
                </div>
            </div>
            <button type="button" class="modal-close" onclick="closeVisitDrawer()">&times;</button>
        </div>

        <form id="visitForm" onsubmit="submitVisitForm(event)">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="visit_id" id="f_visit_id" value="">
            <input type="hidden" name="quick_remark" id="f_quick_remark" value="">

            <div class="modal-body" style="padding:20px 24px !important;">

                <!-- Step 1: Client Info -->
                <div class="gfx-form-block">
                    <div class="gfx-block-title">1. Client &amp; Business</div>
                    <div class="gfx-grid-2">
                        <div class="gfx-field">
                            <label class="gfx-label">Company / Brand <span class="req">*</span></label>
                            <input type="text" name="company_name" id="f_company_name" required class="gfx-input" placeholder="e.g. Acme Logistics">
                        </div>
                        <div class="gfx-field">
                            <label class="gfx-label">Contact Person <span class="req">*</span></label>
                            <input type="text" name="client_name" id="f_client_name" required class="gfx-input" placeholder="e.g. Ali Raza">
                        </div>
                    </div>
                    <div class="gfx-grid-2">
                        <div class="gfx-field">
                            <label class="gfx-label">Phone Number <span class="req">*</span></label>
                            <input type="tel" name="phone" id="f_phone" required class="gfx-input" placeholder="0300 1234567">
                        </div>
                        <div class="gfx-field">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <label class="gfx-label">WhatsApp <span class="gfx-opt">(optional)</span></label>
                                <button type="button" class="gfx-text-btn" style="color:#16a34a;" onclick="copyPhoneToWa()">Same as phone</button>
                            </div>
                            <input type="tel" name="whatsapp" id="f_whatsapp" class="gfx-input" placeholder="Leave blank if same">
                        </div>
                    </div>
                </div>

                <!-- Step 2: Lead Heat & Timing -->
                <div class="gfx-form-block">
                    <div class="gfx-block-title">2. Lead Heat &amp; Best Time to Call</div>
                    <div class="gfx-heat-grid">
                        <label class="gfx-heat-card" id="chip_hot">
                            <input type="radio" name="interest_level" value="hot" onchange="setInterestUI('hot')">
                            <span class="heat-dot dot-red"></span>
                            <strong>Hot</strong>
                            <small>Ready to buy</small>
                        </label>
                        <label class="gfx-heat-card active" id="chip_warm">
                            <input type="radio" name="interest_level" value="warm" checked onchange="setInterestUI('warm')">
                            <span class="heat-dot dot-amber"></span>
                            <strong>Warm</strong>
                            <small>Interested</small>
                        </label>
                        <label class="gfx-heat-card" id="chip_cold">
                            <input type="radio" name="interest_level" value="cold" onchange="setInterestUI('cold')">
                            <span class="heat-dot dot-slate"></span>
                            <strong>Cold</strong>
                            <small>Just exploring</small>
                        </label>
                    </div>

                    <label class="gfx-urgent-bar">
                        <input type="checkbox" name="is_urgent" id="f_is_urgent" value="1">
                        <div>
                            <strong>Flag as Urgent Priority</strong>
                            <span>Alerts the Sales Response Unit immediately</span>
                        </div>
                    </label>

                    <div class="gfx-field" style="margin-top:12px;">
                        <label class="gfx-label">When is the client free to talk? <span class="req">*</span></label>
                        <div class="gfx-quick-notes">
                            <button type="button" class="gfx-mini-chip" onclick="setAvailPreset('Call right now / Today')">Right Now</button>
                            <button type="button" class="gfx-mini-chip" onclick="setAvailPreset('Today Evening (5 PM - 8 PM)')">Today Evening</button>
                            <button type="button" class="gfx-mini-chip" onclick="setAvailPreset('Tomorrow Morning (10 AM - 1 PM)')">Tomorrow AM</button>
                            <button type="button" class="gfx-mini-chip" onclick="setAvailPreset('Tomorrow Afternoon (2 PM - 5 PM)')">Tomorrow PM</button>
                            <button type="button" class="gfx-mini-chip" onclick="setAvailPreset('Next Week')">Next Week</button>
                        </div>
                        <input type="text" name="contact_availability" id="f_contact_availability" required class="gfx-input" placeholder="e.g. Tomorrow afternoon around 3 PM">
                    </div>

                    <div class="gfx-field">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <label class="gfx-label">Schedule First Follow-Up <span class="gfx-opt">(optional)</span></label>
                            <button type="button" class="gfx-text-btn" onclick="document.getElementById('f_next_followup_date').value=''">Clear</button>
                        </div>
                        <div class="gfx-quick-notes">
                            <button type="button" class="gfx-mini-chip" onclick="setNextDatePreset('f_next_followup_date','tomorrow_am')">Tomorrow 11 AM</button>
                            <button type="button" class="gfx-mini-chip" onclick="setNextDatePreset('f_next_followup_date','tomorrow_pm')">Tomorrow 4 PM</button>
                            <button type="button" class="gfx-mini-chip" onclick="setNextDatePreset('f_next_followup_date','in_2_days')">In 2 Days</button>
                        </div>
                        <input type="datetime-local" name="next_followup_date" id="f_next_followup_date" class="gfx-input">
                    </div>
                </div>

                <!-- Step 3: Services & Remarks -->
                <div class="gfx-form-block">
                    <div class="gfx-block-title">3. Services &amp; Meeting Notes</div>
                    <div class="gfx-cat-list">
                        <div class="gfx-cat-item">
                            <label class="gfx-cat-toggle">
                                <input type="checkbox" name="categories[]" value="Website" onchange="toggleSub('sub_web', this.checked)">
                                <span>Website Development</span>
                            </label>
                            <div id="sub_web" class="gfx-sub-pills" style="display:none;">
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Website: E-commerce Store"><span>E-commerce</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Website: Corporate / Portfolio"><span>Corporate</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Website: Custom Web App"><span>Web App</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Website: Redesign & Revamp"><span>Redesign</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Website: Maintenance & Speed"><span>Maintenance</span></label>
                            </div>
                        </div>

                        <div class="gfx-cat-item">
                            <label class="gfx-cat-toggle">
                                <input type="checkbox" name="categories[]" value="Printing" onchange="toggleSub('sub_print', this.checked)">
                                <span>Printing &amp; Packaging</span>
                            </label>
                            <div id="sub_print" class="gfx-sub-pills" style="display:none;">
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Printing: Business Cards"><span>Business Cards</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Printing: Letterheads & Stationery"><span>Stationery</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Printing: Flyers & Brochures"><span>Flyers / Brochures</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Printing: Packaging Boxes"><span>Packaging</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Printing: Banners & Signboards"><span>Banners / Signs</span></label>
                            </div>
                        </div>

                        <div class="gfx-cat-item">
                            <label class="gfx-cat-toggle">
                                <input type="checkbox" name="categories[]" value="Design" onchange="toggleSub('sub_design', this.checked)">
                                <span>Design &amp; Branding</span>
                            </label>
                            <div id="sub_design" class="gfx-sub-pills" style="display:none;">
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Design: Logo & Brand Identity"><span>Logo &amp; Brand</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Design: UI/UX App Design"><span>UI/UX Design</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Design: Social Media Graphics"><span>Social Posts</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Design: Catalogs & Company Profile"><span>Company Profile</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Design: Packaging Artworks"><span>Packaging Art</span></label>
                            </div>
                        </div>

                        <div class="gfx-cat-item">
                            <label class="gfx-cat-toggle">
                                <input type="checkbox" name="categories[]" value="Social Media Marketing" onchange="toggleSub('sub_smm', this.checked)">
                                <span>Paid Ads (SMM / PPC)</span>
                            </label>
                            <div id="sub_smm" class="gfx-sub-pills" style="display:none;">
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="SMM: Meta Ads (FB & IG)"><span>Meta Ads</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="SMM: Google Ads PPC"><span>Google Ads</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="SMM: TikTok Ads"><span>TikTok Ads</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="SMM: Lead Generation Campaigns"><span>Lead Gen</span></label>
                            </div>
                        </div>

                        <div class="gfx-cat-item">
                            <label class="gfx-cat-toggle">
                                <input type="checkbox" name="categories[]" value="Managing Social Media" onchange="toggleSub('sub_manage', this.checked)">
                                <span>Social Media Management</span>
                            </label>
                            <div id="sub_manage" class="gfx-sub-pills" style="display:none;">
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Management: Monthly Content Creation"><span>Content Creation</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Management: Daily Scheduling"><span>Scheduling</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Management: Community & DM Handling"><span>Inbox / DMs</span></label>
                                <label class="gfx-sub-pill"><input type="checkbox" name="subcategories[]" value="Management: Reels & Video Editing"><span>Reels &amp; Video</span></label>
                            </div>
                        </div>
                    </div>

                    <div class="gfx-field" style="margin-top:14px; margin-bottom:0;">
                        <label class="gfx-label">Remarks</label>
                        <div class="gfx-quick-notes">
                            <button type="button" class="gfx-mini-chip" onclick="addRemark('Contact owner directly')">+ Contact owner</button>
                            <button type="button" class="gfx-mini-chip" onclick="addRemark('Requested formal quotation')">+ Wants quote</button>
                            <button type="button" class="gfx-mini-chip" onclick="addRemark('Ready to close / High intent')">+ Ready to close</button>
                            <button type="button" class="gfx-mini-chip" onclick="addRemark('Budget sensitive')">+ Budget sensitive</button>
                        </div>
                        <textarea name="remarks" id="f_remarks" rows="3" class="gfx-input" placeholder="Key requirements, budget, or observations from your visit..."></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="gfx-btn gfx-btn-outline" onclick="closeVisitDrawer()">Cancel</button>
                <button type="submit" class="gfx-btn gfx-btn-primary" id="submitBtn">
                    <i class="fas fa-check"></i> <span>Save Visit</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     DRAWER 3: EXPORT SHEETS
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="exportModal" class="modal-overlay" onclick="if(event.target===this)closeExportModal()">
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <div class="modal-header-icon"><i class="fas fa-file-excel"></i></div>
                <div>
                    <h4>Export Spreadsheet</h4>
                    <div class="gfx-drawer-sub">Download Excel / CSV report</div>
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

                <?php if ($is_admin_user): ?>
                <div class="gfx-field">
                    <label class="gfx-label">Sales Representative</label>
                    <select id="exportRepSelect" class="gfx-input">
                        <option value="0">All Representatives</option>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" id="exportRepSelect" value="<?= $user_id_int ?>">
                <?php endif; ?>

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

<!-- Toast Container -->
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

/* Fix dashboard.css global icon color override inside colored elements */
.gfx-sales-page .gfx-btn-primary i,
.gfx-sales-page .gfx-wa-icon-btn i,
.gfx-sales-page .gfx-wa-pill-btn i,
.gfx-sales-page .gfx-chip.active i,
.gfx-sales-page .gfx-seg.active i,
.gfx-sales-page .gfx-stage-btn.active i,
.gfx-sales-page .gfx-pill i,
.gfx-sales-page .gfx-toast i,
.modal-overlay .gfx-btn-primary i,
.modal-overlay .gfx-wa-pill-btn i,
.modal-overlay .gfx-chip.active i,
.modal-overlay .gfx-stage-btn.active i,
.modal-overlay .gfx-dtab.active i,
.modal-overlay .gfx-dtab i {
    color: inherit !important;
}

/* Page Header */
.gfx-page-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 22px;
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

/* Clean Buttons */
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
.gfx-btn-ghost:hover {
    background: #f1f5f9;
    color: #0f172a !important;
}
.gfx-btn-sm {
    padding: 6px 12px;
    font-size: 12px;
    border-radius: 8px;
}

/* Minimal KPI Cards */
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
.gfx-kpi-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}
.dot-slate { background: #94a3b8; }
.dot-red   { background: #ef4444; }
.dot-amber { background: #f59e0b; }
.dot-green { background: #16a34a; }
.gfx-kpi-val {
    font-size: 28px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
}
.val-red   { color: #dc2626; }
.val-amber { color: #d97706; }
.val-green { color: #16a34a; }

/* Main Surface Card */
.gfx-surface {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

/* Single-Row Toolbar */
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

.gfx-search {
    position: relative;
    width: 240px;
}
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
    transition: border-color 0.15s ease;
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

/* Clean 5-Column Table */
.gfx-table-wrap { overflow-x: auto; }
.gfx-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 780px;
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
.gfx-table tbody tr:hover td {
    background: #f8fafc;
}
.gfx-table tbody tr.row-urgent td:first-child {
    box-shadow: inset 3px 0 0 #ef4444;
}
.gfx-table tbody tr:last-child td { border-bottom: none; }

.gfx-client-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
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
.gfx-avatar.urgent {
    background: #fef2f2;
    color: #dc2626;
}
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
.gfx-client-sub {
    font-size: 12.5px;
    color: #64748b;
    margin-top: 2px;
}

/* Calm Semantic Pills */
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
.heat-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    display: inline-block;
}

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
.gfx-touch-meta {
    font-size: 11.5px;
    color: #64748b;
    margin-top: 3px;
}

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
.gfx-window-text {
    font-size: 11.5px;
    color: #64748b;
    margin-top: 2px;
}

/* Clean Row Actions */
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
.gfx-icon-act:hover {
    background: #f1f5f9;
    color: #0f172a !important;
    border-color: #cbd5e1;
}
.gfx-wa-icon-btn {
    background: #dcfce7;
    border-color: #bbf7d0;
    color: #15803d !important;
}
.gfx-wa-icon-btn:hover {
    background: #16a34a;
    border-color: #16a34a;
    color: #ffffff !important;
}

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

.gfx-state-cell {
    text-align: center;
    padding: 56px 20px !important;
    color: #64748b;
}
.gfx-loader {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13.5px;
    color: #64748b;
}
.gfx-empty-state {
    max-width: 340px;
    margin: 0 auto;
    text-align: center;
}
.gfx-empty-state i {
    font-size: 32px;
    color: #cbd5e1 !important;
    margin-bottom: 10px;
}
.gfx-empty-state h5 {
    margin: 0 0 4px;
    font-size: 15px;
    font-weight: 700;
    color: #334155;
}
.gfx-empty-state p {
    margin: 0 0 14px;
    font-size: 13px;
    color: #94a3b8;
}

/* ── Unified Drawer Styling (Harmonized with dashboard.css Light Drawer Header) ── */
.gfx-drawer-sub {
    font-size: 12.5px;
    color: #64748b !important;
    margin-top: 2px;
    font-weight: 500;
}
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
.gfx-ws-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}
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

.gfx-ws-stage-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.gfx-micro-label {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    margin: 0;
}
.gfx-stage-switcher {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}
.gfx-stage-btn {
    background: #ffffff;
    border: 1px solid #dbe2ea;
    color: #475569;
    padding: 4px 10px;
    border-radius: 7px;
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.14s ease;
}
.gfx-stage-btn:hover { border-color: #94a3b8; color: #0f172a; }
.gfx-stage-btn.active {
    background: #024442;
    border-color: #024442;
    color: #ffffff;
}

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
    transition: all 0.15s ease;
    font-family: inherit;
}
.gfx-dtab:hover { color: #0f172a; }
.gfx-dtab.active {
    color: #024442;
    border-bottom-color: #024442;
}
.gfx-dtab-count {
    background: #eef2f6;
    color: #475569;
    font-size: 11px;
    padding: 1px 7px;
    border-radius: 10px;
}
.gfx-dpanel { display: none; }
.gfx-dpanel.active { display: block; }

/* Form Elements inside Drawers */
.gfx-form-block {
    margin-bottom: 20px;
    padding-bottom: 18px;
    border-bottom: 1px solid #f1f5f9;
}
.gfx-form-block:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.gfx-block-title {
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #024442;
    margin-bottom: 12px;
}
.gfx-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.gfx-field { margin-bottom: 14px; }
.gfx-label {
    display: block;
    font-size: 12.5px;
    font-weight: 700;
    color: #334155;
    margin-bottom: 6px;
}
.gfx-label .req { color: #ef4444; }
.gfx-opt { color: #94a3b8; font-weight: 500; font-size: 11.5px; }
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
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.gfx-input:focus {
    border-color: #024442;
    box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.1);
}
.gfx-text-btn {
    background: none;
    border: none;
    font-size: 11.5px;
    font-weight: 700;
    color: #ef4444;
    cursor: pointer;
    padding: 0;
}

/* Interactive Chips */
.gfx-chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}
.gfx-outcome-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 7px;
}
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
    transition: all 0.14s ease;
    margin: 0;
}
.gfx-chip input { display: none; }
.gfx-chip:hover { border-color: #94a3b8; color: #0f172a; }
.gfx-chip.active {
    background: #024442;
    border-color: #024442;
    color: #ffffff;
}
.gfx-chip.chip-green.active { background: #16a34a; border-color: #16a34a; }
.gfx-chip.chip-muted.active { background: #64748b; border-color: #64748b; }

.gfx-quick-notes {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
}
.gfx-mini-chip {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #334155;
    padding: 4px 10px;
    border-radius: 16px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.14s ease;
    font-family: inherit;
}
.gfx-mini-chip:hover,
.gfx-mini-chip.active {
    background: #024442;
    border-color: #024442;
    color: #ffffff;
}

.gfx-schedule-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px;
}

/* Lead Heat Cards in Visit Drawer */
.gfx-heat-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 12px;
}
.gfx-heat-card {
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 8px;
    text-align: center;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    transition: all 0.15s ease;
    background: #ffffff;
    margin: 0;
}
.gfx-heat-card input { display: none; }
.gfx-heat-card strong { font-size: 13px; color: #0f172a; }
.gfx-heat-card small { font-size: 11px; color: #64748b; }
.gfx-heat-card.active {
    border-color: #024442;
    background: #f4faf9;
    box-shadow: 0 0 0 2px rgba(2, 68, 66, 0.12);
}

.gfx-urgent-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 10px;
    background: #fff7f7;
    border: 1px solid #fecaca;
    cursor: pointer;
    margin: 0;
}
.gfx-urgent-bar input { width: 17px; height: 17px; accent-color: #dc2626; cursor: pointer; }
.gfx-urgent-bar strong { display: block; font-size: 12.5px; color: #b91c1c; }
.gfx-urgent-bar span { display: block; font-size: 11.5px; color: #991b1b; }

/* Compact Services List */
.gfx-cat-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.gfx-cat-item {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 12px;
    background: #ffffff;
}
.gfx-cat-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    cursor: pointer;
    margin: 0;
}
.gfx-cat-toggle input { width: 16px; height: 16px; accent-color: #024442; cursor: pointer; }
.gfx-sub-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #e2e8f0;
}
.gfx-sub-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 4px 10px;
    border-radius: 16px;
    font-size: 12px;
    color: #475569;
    cursor: pointer;
    margin: 0;
}
.gfx-sub-pill input { accent-color: #024442; }

/* Details Card & Timeline inside Workspace Drawer */
.gfx-info-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 16px;
    font-size: 13px;
}
.gfx-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 10px;
}
.gfx-info-k { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #94a3b8; }
.gfx-info-v { font-size: 13px; font-weight: 700; color: #0f172a; margin-top: 2px; }
.gfx-section-heading {
    font-size: 11.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    margin-bottom: 12px;
}
.gfx-timeline {
    position: relative;
    padding-left: 24px;
}
.gfx-timeline::before {
    content: '';
    position: absolute;
    left: 7px;
    top: 6px;
    bottom: 6px;
    width: 2px;
    background: #e2e8f0;
}
.gfx-tl-item {
    position: relative;
    margin-bottom: 14px;
}
.gfx-tl-dot {
    position: absolute;
    left: -24px;
    top: 4px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #ffffff;
    border: 2.5px solid #024442;
}
.gfx-tl-dot.dot-init { border-color: #16a34a; }
.gfx-tl-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 11px 14px;
}
.gfx-tl-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}
.gfx-tl-note {
    font-size: 13px;
    color: #334155;
    line-height: 1.5;
    margin-bottom: 6px;
}
.gfx-tl-meta {
    display: flex;
    justify-content: space-between;
    font-size: 11.5px;
    color: #94a3b8;
}

/* Toast Notifications */
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

/* Responsive */
@media (max-width: 900px) {
    .gfx-kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .gfx-search { width: 100%; }
    .gfx-toolbar-right { width: 100%; }
}
@media (max-width: 640px) {
    .gfx-sales-page { padding: 66px 0 36px; }
    .gfx-grid-2, .gfx-outcome-grid, .gfx-info-grid { grid-template-columns: 1fr; }
    .gfx-footer-tip { display: none; }
}
</style>

<script>
const IS_ADMIN_USER = <?= $is_admin_user ? 'true' : 'false' ?>;
let currentFilter   = 'all';
let currentSort     = 'urgency';
let searchQuery     = '';
let allVisits       = [];
let activeVisit     = null;
let searchTimer     = null;

document.addEventListener('DOMContentLoaded', () => {
    loadVisits();
    loadSalesRepsForExport();

    const params = new URLSearchParams(window.location.search);
    if (params.get('log') === '1') {
        openVisitDrawer('add');
    }
    const hlId = params.get('highlight');
    if (hlId) {
        setTimeout(() => openWorkspaceDrawer(parseInt(hlId), 'log'), 700);
    }
});

/* ── Load & Render Visits ── */
function loadVisits() {
    const icon = document.getElementById('refreshIcon');
    if (icon) icon.classList.add('fa-spin');

    if (allVisits.length === 0) {
        document.getElementById('visitsTableBody').innerHTML = `
            <tr><td colspan="5" class="gfx-state-cell"><div class="gfx-loader"><i class="fas fa-circle-notch fa-spin"></i> Loading visits...</div></td></tr>
        `;
    }

    fetch(`sales_visits_api.php?action=fetch_all&filter=${encodeURIComponent(currentFilter)}&sort=${encodeURIComponent(currentSort)}`)
        .then(r => {
            if (!r.ok) throw new Error('Server error ' + r.status);
            return r.json();
        })
        .then(data => {
            if (icon) icon.classList.remove('fa-spin');
            if (!data.success) {
                showToast(data.message || 'Could not load visits', 'error');
                renderErrorState(data.message);
                return;
            }
            allVisits = data.items || [];
            renderStats(data.stats || {});
            renderTable();
            if (activeVisit) {
                const updated = allVisits.find(x => x.id == activeVisit.id);
                if (updated) {
                    activeVisit = updated;
                    renderWorkspaceHeader(updated);
                }
            }
        })
        .catch(() => {
            if (icon) icon.classList.remove('fa-spin');
            renderErrorState('Connection interrupted. Please retry.');
        });
}

function renderErrorState(msg) {
    document.getElementById('visitsTableBody').innerHTML = `
        <tr>
            <td colspan="5" class="gfx-state-cell">
                <div class="gfx-empty-state">
                    <i class="fas fa-exclamation-circle" style="color:#ef4444 !important;"></i>
                    <h5>Unable to load visits</h5>
                    <p>${escapeHtml(msg || 'Please check your connection.')}</p>
                    <button type="button" class="gfx-btn gfx-btn-primary gfx-btn-sm" onclick="loadVisits()">Retry</button>
                </div>
            </td>
        </tr>
    `;
}

function renderStats(s) {
    document.getElementById('statTotal').textContent     = s.total ?? 0;
    document.getElementById('statOverdue').textContent   = s.overdue ?? 0;
    document.getElementById('statDueToday').textContent  = s.due_today ?? 0;
    document.getElementById('statConverted').textContent = s.converted ?? 0;
}

function setFilter(val) {
    currentFilter = val;
    document.querySelectorAll('#quickFilterPills .gfx-seg').forEach(b => {
        b.classList.toggle('active', b.dataset.filter === val);
    });
    const extraSel = document.getElementById('extraFilterSelect');
    if (extraSel) {
        const extraVals = ['urgent', 'hot', 'contacted', 'closed'];
        extraSel.value = extraVals.includes(val) ? val : '';
    }
    ['all', 'overdue', 'due_today', 'converted'].forEach(k => {
        const card = document.getElementById('kpi_' + k);
        if (card) card.classList.toggle('active', k === val);
    });
    loadVisits();
}

function handleExtraFilter(val) {
    if (val) setFilter(val);
    else setFilter('all');
}

function setSort(val) {
    currentSort = val;
    renderTable();
}

function handleSearch(val) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        searchQuery = val.trim().toLowerCase();
        document.getElementById('searchClearBtn').style.display = searchQuery ? 'block' : 'none';
        renderTable();
    }, 150);
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    searchQuery = '';
    document.getElementById('searchClearBtn').style.display = 'none';
    renderTable();
}

function renderTable() {
    const tbody = document.getElementById('visitsTableBody');
    let items = [...allVisits];

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

    document.getElementById('visibleCountText').textContent = `Showing ${items.length} visit${items.length === 1 ? '' : 's'}`;

    if (items.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="gfx-state-cell">
                    <div class="gfx-empty-state">
                        <i class="fas fa-inbox"></i>
                        <h5>No field visits found</h5>
                        <p>${searchQuery ? 'Try a different search keyword.' : 'Log your first client visit to start tracking follow-ups.'}</p>
                        ${searchQuery
                            ? `<button type="button" class="gfx-btn gfx-btn-outline gfx-btn-sm" onclick="clearSearch()">Clear Search</button>`
                            : `<button type="button" class="gfx-btn gfx-btn-primary gfx-btn-sm" onclick="openVisitDrawer('add')"><i class="fas fa-plus"></i> Log Visit</button>`}
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    items.forEach(v => {
        const isUrgent = v.is_urgent == 1;
        const initial  = (v.company_name || v.client_name || 'C').trim().charAt(0).toUpperCase();
        const cleanWa  = formatWhatsAppNumber((v.whatsapp || v.phone || '').trim());
        const waMsg    = encodeURIComponent(`Hello ${v.client_name}, following up from Graphicafix regarding ${v.company_name}.`);
        const fCount   = parseInt(v.followup_count || 0);

        const stageMap = {
            pending:       ['Pending', 'pill-pending'],
            contacted:     ['Contacted', 'pill-contacted'],
            in_discussion: ['In Discussion', 'pill-discussion'],
            converted:     ['Converted', 'pill-converted'],
            closed:        ['Closed', 'pill-closed']
        };
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
            <tr class="${isUrgent ? 'row-urgent' : ''}" onclick="openWorkspaceDrawer(${v.id}, 'log')">
                <td>
                    <div class="gfx-client-cell">
                        <div class="gfx-avatar ${isUrgent ? 'urgent' : ''}">${escapeHtml(initial)}</div>
                        <div style="min-width:0;">
                            <div class="gfx-company-name">
                                <span>${escapeHtml(v.company_name)}</span>
                                ${isUrgent ? '<span class="gfx-urgent-pill">Urgent</span>' : ''}
                            </div>
                            <div class="gfx-client-sub">${escapeHtml(v.client_name)} &middot; ${escapeHtml(v.phone)}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div><span class="gfx-pill ${stClass}">${stLabel}</span></div>
                    <div class="gfx-heat-tag"><span class="heat-dot ${heatDot}"></span> ${heatLbl}</div>
                </td>
                <td>
                    ${dueHtml}
                    <div class="gfx-touch-meta">${fCount} follow-up${fCount === 1 ? '' : 's'}${v.last_outcome ? ' &middot; ' + escapeHtml(v.last_outcome.replace(/_/g, ' ')) : ''}</div>
                </td>
                <td>
                    <div>${catsHtml}</div>
                    <div class="gfx-window-text">${escapeHtml(v.contact_availability || 'Anytime')}</div>
                </td>
                <td style="text-align:right;" onclick="event.stopPropagation()">
                    <div class="gfx-row-actions">
                        ${cleanWa ? `<a href="https://wa.me/${cleanWa}?text=${waMsg}" target="_blank" class="gfx-icon-act gfx-wa-icon-btn" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>` : ''}
                        <a href="tel:${escapeHtml(v.phone)}" class="gfx-icon-act" title="Call ${escapeHtml(v.phone)}"><i class="fas fa-phone-alt"></i></a>
                        <button type="button" class="gfx-btn gfx-btn-primary gfx-btn-sm" onclick="openWorkspaceDrawer(${v.id}, 'log')">Follow Up</button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

/* ── Unified Client Workspace Drawer ── */
function openWorkspaceDrawer(visitId, defaultTab = 'log') {
    const v = allVisits.find(item => item.id == visitId);
    if (!v) return;
    activeVisit = v;

    renderWorkspaceHeader(v);
    resetFollowupForm(v);
    switchDrawerTab(defaultTab);
    loadWorkspaceTimeline(visitId);

    document.getElementById('workspaceDrawer').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeWorkspaceDrawer() {
    document.getElementById('workspaceDrawer').classList.remove('active');
    document.body.style.overflow = '';
    activeVisit = null;
}

function renderWorkspaceHeader(v) {
    const initial = (v.company_name || v.client_name || 'C').trim().charAt(0).toUpperCase();
    document.getElementById('wsAvatar').textContent  = initial;
    document.getElementById('wsCompany').textContent = v.company_name;
    document.getElementById('wsMeta').textContent    = `${v.client_name} · ${v.phone} · Rep: ${v.sales_rep_name}`;
    document.getElementById('wsTouchCount').textContent = v.followup_count || 0;

    const cleanWa = formatWhatsAppNumber((v.whatsapp || v.phone || '').trim());
    const waMsg   = encodeURIComponent(`Hello ${v.client_name}, following up from Graphicafix regarding ${v.company_name}.`);

    let actionsHtml = '';
    if (cleanWa) {
        actionsHtml += `<a href="https://wa.me/${cleanWa}?text=${waMsg}" target="_blank" class="gfx-wa-pill-btn"><i class="fab fa-whatsapp"></i> WhatsApp</a>`;
    }
    actionsHtml += `
        <a href="tel:${escapeHtml(v.phone)}" class="gfx-btn gfx-btn-outline gfx-btn-sm"><i class="fas fa-phone-alt"></i> Call</a>
        <button type="button" class="gfx-btn gfx-btn-outline gfx-btn-sm" onclick="copyText('${escapeJsString(v.phone)}', 'Phone copied')"><i class="far fa-copy"></i> Copy</button>
        <button type="button" class="gfx-btn gfx-btn-outline gfx-btn-sm" onclick="closeWorkspaceDrawer(); openVisitDrawer('edit', ${v.id})"><i class="fas fa-pen"></i> Edit</button>
    `;
    if (v.status !== 'converted') {
        actionsHtml += `<button type="button" class="gfx-btn gfx-btn-outline gfx-btn-sm" style="color:#15803d !important; border-color:#bbf7d0;" onclick="convertToLead(${v.id})"><i class="fas fa-user-check"></i> Convert to Lead</button>`;
    }
    actionsHtml += `<button type="button" class="gfx-btn gfx-btn-ghost gfx-btn-sm" style="color:#dc2626 !important; margin-left:auto;" onclick="deleteVisit(${v.id})" title="Delete visit"><i class="fas fa-trash-alt"></i></button>`;
    document.getElementById('wsQuickActions').innerHTML = actionsHtml;

    const stages = [
        ['pending', 'Pending'],
        ['contacted', 'Contacted'],
        ['in_discussion', 'In Discussion'],
        ['converted', 'Converted'],
        ['closed', 'Closed']
    ];
    document.getElementById('wsStageSwitcher').innerHTML = stages.map(([k, lbl]) => `
        <button type="button" class="gfx-stage-btn ${v.status === k ? 'active' : ''}" onclick="quickUpdateStage(${v.id}, '${k}')">${lbl}</button>
    `).join('');

    const cats = (v.categories_arr || []).join(', ') || 'General';
    const subs = (v.subcategories_arr || []).join(', ');
    document.getElementById('wsDetailsCard').innerHTML = `
        <div class="gfx-info-grid">
            <div><div class="gfx-info-k">Preferred Window</div><div class="gfx-info-v">${escapeHtml(v.contact_availability || 'Anytime')}</div></div>
            <div><div class="gfx-info-k">Lead Heat</div><div class="gfx-info-v" style="text-transform:capitalize;">${escapeHtml(v.interest_level)} ${v.is_urgent == 1 ? '· Urgent' : ''}</div></div>
            <div><div class="gfx-info-k">Logged By</div><div class="gfx-info-v">${escapeHtml(v.sales_rep_name)} (${escapeHtml(v.time_ago || '')})</div></div>
            <div><div class="gfx-info-k">Next Follow-Up</div><div class="gfx-info-v">${escapeHtml(v.formatted_next_followup || 'Not scheduled')}</div></div>
        </div>
        <div style="margin-top:8px;"><div class="gfx-info-k">Services Interested In</div><div class="gfx-info-v" style="font-weight:600;">${escapeHtml(cats)}${subs ? ' — ' + escapeHtml(subs) : ''}</div></div>
        ${v.remarks ? `<div style="margin-top:10px; padding-top:10px; border-top:1px solid #e2e8f0;"><div class="gfx-info-k">Initial Field Remarks</div><div style="margin-top:4px; color:#334155; line-height:1.5;">${escapeHtml(v.remarks)}</div></div>` : ''}
    `;
}

function switchDrawerTab(tab) {
    document.getElementById('dtabLog').classList.toggle('active', tab === 'log');
    document.getElementById('dtabHistory').classList.toggle('active', tab === 'history');
    document.getElementById('drawerPanelLog').classList.toggle('active', tab === 'log');
    document.getElementById('drawerPanelHistory').classList.toggle('active', tab === 'history');
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
                loadVisits();
                loadWorkspaceTimeline(vid);
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

function loadWorkspaceTimeline(visitId) {
    const body = document.getElementById('wsTimelineBody');
    body.innerHTML = '<div class="gfx-loader"><i class="fas fa-circle-notch fa-spin"></i> Loading history...</div>';

    fetch(`sales_visits_api.php?action=get_followups&visit_id=${visitId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                body.innerHTML = `<p style="color:#dc2626; font-size:13px;">${escapeHtml(data.message)}</p>`;
                return;
            }
            const list = data.followups || [];
            document.getElementById('wsTouchCount').textContent = list.length;

            let html = '<div class="gfx-timeline">';
            if (list.length === 0) {
                html += `<div style="padding:14px; background:#fff; border:1px dashed #cbd5e1; border-radius:10px; text-align:center; color:#64748b; font-size:13px; margin-bottom:14px;">No follow-ups logged yet. Use the <strong>Log Follow-Up</strong> tab to add the first touchpoint.</div>`;
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
                            <div class="gfx-tl-meta"><span>Logged by ${escapeHtml(v.sales_rep_name)}</span></div>
                        </div>
                    </div>
                `;
            }
            html += '</div>';
            body.innerHTML = html;
        });
}

function quickUpdateStage(visitId, newStage) {
    const v = allVisits.find(x => x.id == visitId);
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
                loadVisits();
            } else {
                showToast(data.message || 'Could not update stage', 'error');
            }
        });
}

function convertToLead(visitId) {
    if (!confirm('Convert this field visit into a CRM Lead?')) return;
    const fd = new FormData();
    fd.append('action', 'convert_to_lead');
    fd.append('visit_id', visitId);

    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Converted to CRM Lead!', 'success');
                loadVisits();
            } else {
                showToast(data.message || 'Conversion failed', 'error');
            }
        });
}

function deleteVisit(visitId) {
    if (!confirm('Delete this visit record and its follow-up history?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('visit_id', visitId);

    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeWorkspaceDrawer();
                showToast('Visit deleted', 'info');
                loadVisits();
            } else {
                showToast(data.message || 'Delete failed', 'error');
            }
        });
}

/* ── Log / Edit Visit Drawer ── */
function openVisitDrawer(mode, id = null) {
    document.getElementById('visitForm').reset();
    document.getElementById('f_visit_id').value = '';
    document.querySelectorAll('.gfx-sub-pills').forEach(el => el.style.display = 'none');
    setInterestUI('warm');

    if (mode === 'edit' && id) {
        const v = allVisits.find(x => x.id == id);
        if (!v) return;
        document.getElementById('visitModalTitle').textContent        = 'Edit Client Visit';
        document.getElementById('f_visit_id').value                   = v.id;
        document.getElementById('f_client_name').value                = v.client_name || '';
        document.getElementById('f_company_name').value               = v.company_name || '';
        document.getElementById('f_phone').value                      = v.phone || '';
        document.getElementById('f_whatsapp').value                   = v.whatsapp || '';
        document.getElementById('f_contact_availability').value       = v.contact_availability || '';
        document.getElementById('f_remarks').value                    = v.remarks || '';
        document.getElementById('f_is_urgent').checked                = (v.is_urgent == 1);
        if (v.next_followup_date) {
            document.getElementById('f_next_followup_date').value = v.next_followup_date.replace(' ', 'T').slice(0, 16);
        }
        setInterestUI(v.interest_level || 'warm');

        (v.categories_arr || []).forEach(cat => {
            const cb = document.querySelector(`input[name="categories[]"][value="${CSS.escape(cat)}"]`);
            if (cb) { cb.checked = true; cb.dispatchEvent(new Event('change')); }
        });
        (v.subcategories_arr || []).forEach(sub => {
            const cb = document.querySelector(`input[name="subcategories[]"][value="${CSS.escape(sub)}"]`);
            if (cb) cb.checked = true;
        });
    } else {
        document.getElementById('visitModalTitle').textContent = 'Log Client Visit';
    }

    document.getElementById('visitModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeVisitDrawer() {
    document.getElementById('visitModal').classList.remove('active');
    document.body.style.overflow = '';
}

function setInterestUI(level) {
    ['hot', 'warm', 'cold'].forEach(k => {
        const el = document.getElementById('chip_' + k);
        if (el) el.classList.toggle('active', k === level);
    });
    const radio = document.querySelector(`input[name="interest_level"][value="${level}"]`);
    if (radio) radio.checked = true;
}

function copyPhoneToWa() {
    document.getElementById('f_whatsapp').value = document.getElementById('f_phone').value;
    showToast('Copied phone to WhatsApp', 'info');
}

function setAvailPreset(txt) {
    document.getElementById('f_contact_availability').value = txt;
}

function toggleSub(id, show) {
    const el = document.getElementById(id);
    if (el) el.style.display = show ? 'flex' : 'none';
}

function addRemark(txt) {
    const ta = document.getElementById('f_remarks');
    document.getElementById('f_quick_remark').value = txt;
    ta.value = ta.value ? ta.value.trim() + ' — ' + txt : txt;
}

function submitVisitForm(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Saving...';

    const fd = new FormData(document.getElementById('visitForm'));
    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> <span>Save Visit</span>';
            if (data.success) {
                closeVisitDrawer();
                showToast(data.message || 'Visit saved!', 'success');
                loadVisits();
            } else {
                showToast(data.message || 'Failed to save visit', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> <span>Save Visit</span>';
            showToast('Network error while saving visit', 'error');
        });
}

/* ── Export Drawer ── */
function loadSalesRepsForExport() {
    if (!IS_ADMIN_USER) return;
    fetch('sales_visits_api.php?action=get_reps')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('exportRepSelect');
            if (sel && data.success && data.reps) {
                data.reps.forEach(rep => {
                    sel.innerHTML += `<option value="${rep.id}">${escapeHtml(rep.name)}</option>`;
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

/* ── Shared Utilities ── */
function setNextDatePreset(inputId, preset) {
    const now = new Date();
    if (preset === 'today_pm') {
        now.setHours(17, 0, 0, 0);
    } else if (preset === 'tomorrow_am') {
        now.setDate(now.getDate() + 1); now.setHours(11, 0, 0, 0);
    } else if (preset === 'tomorrow_pm') {
        now.setDate(now.getDate() + 1); now.setHours(16, 0, 0, 0);
    } else if (preset === 'in_2_days') {
        now.setDate(now.getDate() + 2); now.setHours(15, 0, 0, 0);
    } else if (preset === 'next_week') {
        const d = now.getDay();
        now.setDate(now.getDate() + (d === 0 ? 1 : 8 - d)); now.setHours(11, 0, 0, 0);
    }
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
