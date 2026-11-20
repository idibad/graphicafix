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

<div class="height-100 sales-visits-wrapper">

    <!-- ── Universal KPI Stats Row (Interactive Click-to-Filter) ── -->
    <div class="stats-row sv-stats-grid" style="margin-bottom:22px;">
        <div class="stat-card sv-kpi-card" id="kpiCard_all" onclick="setFilter('all')" title="Click to view all visits">
            <div class="stat-header">
                <span class="stat-label">Total Field Visits</span>
                <div class="stat-icon sv-icon-primary"><i class="fas fa-street-view"></i></div>
            </div>
            <div class="stat-number" id="statTotal"><span class="sv-skeleton-pulse">--</span></div>
            <div class="sv-stat-subtext"><i class="fas fa-filter"></i> Click to view all visits</div>
        </div>

        <div class="stat-card sv-kpi-card sv-kpi-overdue" id="kpiCard_overdue" onclick="setFilter('overdue')" title="Click to filter overdue follow-ups">
            <div class="stat-header">
                <span class="stat-label">Overdue Follow-ups</span>
                <div class="stat-icon sv-icon-danger"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
            <div class="stat-number text-danger" id="statOverdue"><span class="sv-skeleton-pulse">--</span></div>
            <div class="sv-stat-subtext text-danger font-semibold"><i class="fas fa-bell"></i> Missed follow-up schedule</div>
        </div>

        <div class="stat-card sv-kpi-card sv-kpi-duetoday" id="kpiCard_due_today" onclick="setFilter('due_today')" title="Click to filter due today">
            <div class="stat-header">
                <span class="stat-label">Due Today</span>
                <div class="stat-icon sv-icon-warning"><i class="fas fa-clock"></i></div>
            </div>
            <div class="stat-number text-warning" id="statDueToday"><span class="sv-skeleton-pulse">--</span></div>
            <div class="sv-stat-subtext text-warning"><i class="far fa-calendar-check"></i> Action scheduled for today</div>
        </div>

        <div class="stat-card sv-kpi-card sv-kpi-converted" id="kpiCard_converted" onclick="setFilter('converted')" title="Click to filter converted deals">
            <div class="stat-header">
                <span class="stat-label">Converted / Won</span>
                <div class="stat-icon sv-icon-success"><i class="fas fa-trophy"></i></div>
            </div>
            <div class="stat-number text-success" id="statConverted"><span class="sv-skeleton-pulse">--</span></div>
            <div class="sv-stat-subtext text-success"><i class="fas fa-check-circle"></i> Converted to CRM leads</div>
        </div>
    </div>

    <!-- ── Universal Main Card & Table ── -->
    <div class="main-card sv-main-card">
        <div class="main-header sv-main-header">
            <div>
                <h3 class="sv-page-title">
                    <span class="sv-title-icon"><i class="fas fa-street-view"></i></span>
                    <span>Client Field Visits & Follow-Up Tracker</span>
                </h3>
                <span class="sv-page-subtitle">Track ground visits, log multi-stage follow-ups, and convert prospective clients into deals.</span>
            </div>

            <div class="sv-header-actions">
                <button type="button" onclick="loadVisits()" class="btn btn-secondary-custom sv-btn-icon" title="Refresh visit records">
                    <i class="fas fa-sync-alt" id="refreshIcon"></i>
                </button>
                <button type="button" onclick="openExportModal()" class="btn btn-secondary-custom sv-btn-pill">
                    <i class="fas fa-file-excel text-success"></i> <span>Export Sheets</span>
                </button>
                <?php if ($is_admin_user): ?>
                <a href="sales_response_unit.php" class="btn btn-secondary-custom sv-btn-pill">
                    <i class="fas fa-satellite-dish" style="color:var(--primary);"></i> <span>Response Unit</span>
                </a>
                <?php endif; ?>
                <button class="add-client sv-btn-primary" onclick="openVisitModal('add')">
                    <i class="fas fa-plus"></i> <span>Log Client Visit</span>
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="sv-toolbar">
            <div class="sv-filter-pills-wrap">
                <div class="sv-quick-pills" id="quickFilterPills">
                    <button type="button" class="sv-pill active" data-filter="all" onclick="setFilter('all')">
                        <i class="fas fa-list-ul"></i> All Visits
                    </button>
                    <button type="button" class="sv-pill sv-pill-urgent" data-filter="urgent" onclick="setFilter('urgent')">
                        <i class="fas fa-bolt"></i> Urgent Only
                    </button>
                    <button type="button" class="sv-pill sv-pill-duetoday" data-filter="due_today" onclick="setFilter('due_today')">
                        <i class="fas fa-clock"></i> Due Today
                    </button>
                    <button type="button" class="sv-pill sv-pill-overdue" data-filter="overdue" onclick="setFilter('overdue')">
                        <i class="fas fa-exclamation-triangle"></i> Overdue
                    </button>
                    <button type="button" class="sv-pill sv-pill-hot" data-filter="hot" onclick="setFilter('hot')">
                        <i class="fas fa-fire"></i> Hot Priority
                    </button>
                    <button type="button" class="sv-pill sv-pill-discussion" data-filter="in_discussion" onclick="setFilter('in_discussion')">
                        <i class="fas fa-comments"></i> In Discussion
                    </button>
                    <button type="button" class="sv-pill sv-pill-contacted" data-filter="contacted" onclick="setFilter('contacted')">
                        <i class="fas fa-phone-alt"></i> Contacted
                    </button>
                    <button type="button" class="sv-pill sv-pill-converted" data-filter="converted" onclick="setFilter('converted')">
                        <i class="fas fa-trophy"></i> Converted
                    </button>
                    <button type="button" class="sv-pill sv-pill-closed" data-filter="closed" onclick="setFilter('closed')">
                        <i class="fas fa-archive"></i> Closed
                    </button>
                </div>
            </div>

            <div class="sv-search-sort-bar">
                <!-- Sort Select -->
                <div class="sv-control-group">
                    <label for="sortSelect" class="sv-control-label"><i class="fas fa-sort-amount-down"></i> Sort:</label>
                    <select id="sortSelect" onchange="setSort(this.value)" class="form-select sv-select">
                        <option value="urgency">Urgency & Priority First</option>
                        <option value="followup_due">Next Follow-Up Due Date</option>
                        <option value="followup_cnt">Most Follow-ups Completed</option>
                        <option value="interest">Interest Level (High to Low)</option>
                        <option value="contact_time">Preferred Contact Window</option>
                        <option value="newest">Newest Logged First</option>
                    </select>
                </div>

                <!-- Search Input with Clear Button -->
                <div class="sv-search-box">
                    <i class="fas fa-search sv-search-icon"></i>
                    <input type="text" id="searchInput" oninput="handleSearch(this.value)" placeholder="Search company, client, phone, remarks..." class="form-control sv-search-input">
                    <button type="button" id="searchClearBtn" class="sv-search-clear" onclick="clearSearch()" style="display:none;" title="Clear search">&times;</button>
                </div>

                <div class="sv-record-count" id="recordCountBadge">
                    Showing <strong id="visibleCount">0</strong> visits
                </div>
            </div>
        </div>

        <!-- Table Container -->
        <div class="sv-table-responsive">
            <table class="data-table sv-table" id="visitsTable">
                <thead>
                    <tr>
                        <th style="width:22%;">Client & Company</th>
                        <th style="width:15%;">Direct Connect</th>
                        <th style="width:13%;">Stage & Priority</th>
                        <th style="width:20%;">Follow-Up Track Record</th>
                        <th style="width:14%;">Requirements</th>
                        <th style="width:9%;">Logged By</th>
                        <th style="width:7%; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="visitsTableBody">
                    <tr>
                        <td colspan="7" class="sv-loading-cell">
                            <div class="sv-spinner-box">
                                <i class="fas fa-spinner fa-spin sv-primary-spin"></i>
                                <p>Loading field visits data...</p>
                            </div>
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
<div id="visitModal" class="modal-overlay sv-modal" onclick="if(event.target===this)closeVisitModal()">
    <div class="modal-content sv-modal-content" style="max-width:760px;">
        <div class="modal-header sv-modal-header">
            <h4>
                <div class="modal-header-icon"><i class="fas fa-street-view"></i></div>
                <span id="visitModalTitle">Log Client Visit</span>
            </h4>
            <button class="modal-close" onclick="closeVisitModal()">&times;</button>
        </div>

        <form id="visitForm" onsubmit="submitVisitForm(event)">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="visit_id" id="f_visit_id" value="">

            <div class="modal-body sv-modal-body">

                <!-- Section: Client & Business -->
                <div class="sv-form-section">
                    <div class="sv-section-title"><i class="fas fa-building"></i> Client & Business Information</div>
                    <div class="sv-form-grid-2">
                        <div class="form-group">
                            <label>Client Name <span class="text-danger">*</span></label>
                            <input type="text" name="client_name" id="f_client_name" required placeholder="e.g. John Doe / Business Owner" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Company / Brand Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" id="f_company_name" required placeholder="e.g. Acme Logistics LLC" class="form-control">
                        </div>
                    </div>

                    <div class="sv-form-grid-2" style="margin-top:12px;">
                        <div class="form-group">
                            <label>Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" id="f_phone" required placeholder="e.g. 03001234567" class="form-control">
                        </div>
                        <div class="form-group">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                <label style="margin:0;">WhatsApp Number</label>
                                <button type="button" onclick="copyPhoneToWa()" class="sv-link-btn">
                                    <i class="fab fa-whatsapp"></i> Same as Phone
                                </button>
                            </div>
                            <input type="tel" name="whatsapp" id="f_whatsapp" placeholder="e.g. 03001234567" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- Section: Priority & Urgency -->
                <div class="sv-form-section" style="margin-top:14px;">
                    <div class="sv-section-title"><i class="fas fa-signal"></i> Priority Level & Urgency</div>
                    <div class="sv-form-grid-2">
                        <div class="form-group">
                            <label>Interest Level <span class="text-danger">*</span></label>
                            <div class="sv-interest-chips">
                                <label class="interest-chip" id="chip_hot">
                                    <input type="radio" name="interest_level" value="hot" required onchange="setInterestUI('hot')">
                                    <span>🔥 Hot / Ready</span>
                                </label>
                                <label class="interest-chip active-chip" id="chip_warm">
                                    <input type="radio" name="interest_level" value="warm" checked required onchange="setInterestUI('warm')">
                                    <span>⚡ Warm / Interested</span>
                                </label>
                                <label class="interest-chip" id="chip_cold">
                                    <input type="radio" name="interest_level" value="cold" required onchange="setInterestUI('cold')">
                                    <span>❄️ Cold / Exploring</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Urgent Flag</label>
                            <label class="sv-urgent-toggle">
                                <input type="checkbox" name="is_urgent" id="f_is_urgent" value="1">
                                <div>
                                    <span class="sv-urgent-text">🚨 Mark as URGENT Priority</span>
                                    <small class="sv-urgent-subtext">Triggers high-priority audio & visual radar alerts</small>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Section: Timing & Availability -->
                <div class="sv-form-section" style="margin-top:14px;">
                    <div class="sv-section-title"><i class="far fa-clock"></i> Client Availability & Follow-up Window</div>
                    <div class="form-group">
                        <label>When is the client available to talk? <span class="text-danger">*</span></label>
                        <div class="sv-preset-chips" style="margin-bottom:8px;">
                            <button type="button" class="preset-tag" onclick="setAvailPreset('Available in 1-2 hours')">In 1-2 hours</button>
                            <button type="button" class="preset-tag" onclick="setAvailPreset('Today Evening (5:00 PM - 8:00 PM)')">Today Evening</button>
                            <button type="button" class="preset-tag" onclick="setAvailPreset('Tomorrow Morning (10:00 AM - 1:00 PM)')">Tomorrow Morning</button>
                            <button type="button" class="preset-tag" onclick="setAvailPreset('Tomorrow Afternoon (2:00 PM - 5:00 PM)')">Tomorrow Afternoon</button>
                            <button type="button" class="preset-tag" onclick="setAvailPreset('In 2 Days')">In 2 Days</button>
                            <button type="button" class="preset-tag" onclick="setAvailPreset('Next Week Monday')">Next Week</button>
                        </div>
                        <input type="text" name="contact_availability" id="f_contact_availability" required placeholder="e.g. Tomorrow afternoon around 3:00 PM" class="form-control">
                    </div>

                    <div class="form-group" style="margin-top:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <label style="font-size:12.5px; font-weight:700; color:#0f172a; margin:0;">
                                <i class="far fa-calendar-alt text-primary"></i> Schedule Initial Follow-Up Date & Time
                            </label>
                            <button type="button" onclick="document.getElementById('f_next_followup_date').value=''" class="sv-clear-link">Clear</button>
                        </div>
                        <div class="sv-preset-chips" style="margin-bottom:8px;">
                            <button type="button" class="preset-tag" onclick="setVisitNextPreset('tomorrow_am')">Tomorrow Morning (11 AM)</button>
                            <button type="button" class="preset-tag" onclick="setVisitNextPreset('tomorrow_pm')">Tomorrow Afternoon (4 PM)</button>
                            <button type="button" class="preset-tag" onclick="setVisitNextPreset('in_2_days')">In 2 Days</button>
                            <button type="button" class="preset-tag" onclick="setVisitNextPreset('next_week')">Next Week</button>
                        </div>
                        <input type="datetime-local" name="next_followup_date" id="f_next_followup_date" class="form-control sv-datetime-input">
                    </div>
                </div>

                <!-- Section: Services Discussed -->
                <div class="sv-form-section" style="margin-top:14px;">
                    <div class="sv-section-title"><i class="fas fa-boxes"></i> Requirements & Services Discussed</div>
                    <div class="sv-categories-grid">
                        <!-- Category: Website -->
                        <div class="cat-box">
                            <label class="cat-label">
                                <input type="checkbox" name="categories[]" value="Website" onchange="toggleSub('sub_web', this.checked)">
                                <strong><i class="fas fa-globe text-primary"></i> Website</strong>
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
                                <strong><i class="fas fa-paint-brush" style="color:#db2777;"></i> Design & Branding</strong>
                            </label>
                            <div id="sub_design" class="sub-box" style="display:none;">
                                <label><input type="checkbox" name="subcategories[]" value="Design: Logo & Brand Identity"> Logo & Brand Identity</label>
                                <label><input type="checkbox" name="subcategories[]" value="Design: UI/UX App Design"> UI/UX Design</label>
                                <label><input type="checkbox" name="subcategories[]" value="Design: Social Media Graphics"> Social Media Graphics</label>
                                <label><input type="checkbox" name="subcategories[]" value="Design: Catalogs & Company Profile"> Catalogs / Profiles</label>
                                <label><input type="checkbox" name="subcategories[]" value="Design: Packaging Artworks"> Packaging Design</label>
                            </div>
                        </div>

                        <!-- Category: SMM -->
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
                                <strong><i class="fas fa-hashtag" style="color:#10b981;"></i> Organic Social Media Management</strong>
                            </label>
                            <div id="sub_manage" class="sub-box" style="display:none;">
                                <label><input type="checkbox" name="subcategories[]" value="Management: Monthly Content Creation"> Content Creation & Design</label>
                                <label><input type="checkbox" name="subcategories[]" value="Management: Daily Scheduling"> Daily Scheduling</label>
                                <label><input type="checkbox" name="subcategories[]" value="Management: Community & DM Handling"> Community & Inbox</label>
                                <label><input type="checkbox" name="subcategories[]" value="Management: Reels & Video Editing"> Reels & Video Editing</label>
                                <label><input type="checkbox" name="subcategories[]" value="Management: Monthly Growth Strategy"> Analytics & Strategy</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Remarks & One-Click Tags -->
                <div class="sv-form-section" style="margin-top:14px;">
                    <div class="sv-section-title"><i class="fas fa-comment-dots"></i> Meeting Remarks & Observations</div>
                    <div class="sv-preset-chips" style="margin-bottom:8px;">
                        <button type="button" class="preset-tag" onclick="addRemark('Contact owner directly')">👤 Contact owner directly</button>
                        <button type="button" class="preset-tag" onclick="addRemark('Interested but budget issues')">💰 Budget constraint</button>
                        <button type="button" class="preset-tag" onclick="addRemark('Not interested at this time')">❌ Not interested</button>
                        <button type="button" class="preset-tag" onclick="addRemark('Ready to close / High intent')">🎯 Ready to close</button>
                        <button type="button" class="preset-tag" onclick="addRemark('Requested formal quotation')">📄 Wants Quotation</button>
                        <button type="button" class="preset-tag" onclick="addRemark('Comparing rates with agency')">🔄 Comparing rates</button>
                    </div>
                    <input type="hidden" name="quick_remark" id="f_quick_remark" value="">
                    <textarea name="remarks" id="f_remarks" rows="3" placeholder="Enter specific client requirements, pricing expectations, or key observations..." class="form-control" style="resize:vertical;"></textarea>
                </div>

            </div>

            <div class="modal-footer sv-modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeVisitModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="submitBtn">
                    <i class="fas fa-save"></i> <span>Save Visit Record</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     UNIVERSAL MODAL: VIEW VISIT DETAILS & TIMELINE
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="viewModal" class="modal-overlay sv-modal" onclick="if(event.target===this)closeViewModal()">
    <div class="modal-content sv-modal-content" style="max-width:700px;">
        <div class="modal-header sv-modal-header">
            <h4>
                <div class="modal-header-icon"><i class="fas fa-eye"></i></div>
                <span>Client Visit Overview</span>
            </h4>
            <button class="modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body sv-modal-body" id="viewModalBody">
            <!-- Populated dynamically -->
        </div>
        <div class="modal-footer sv-modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
            <div id="viewModalActions"></div>
            <button type="button" class="btn btn-secondary-custom" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     MODAL: LOG STRUCTURED FOLLOW-UP
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="followupModal" class="modal-overlay sv-modal" onclick="if(event.target===this)closeFollowupModal()">
    <div class="modal-content sv-modal-content" style="max-width:650px;">
        <div class="modal-header sv-modal-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <div class="modal-header-icon">
                    <i class="fas fa-phone-volume"></i>
                </div>
                <div>
                    <h4 id="fModalCompanyTitle">Log Follow-Up Track Record</h4>
                    <span id="fModalClientSub" class="sv-modal-subtitle">Record touchpoint details and schedule next interaction</span>
                </div>
            </div>
            <button class="modal-close" onclick="closeFollowupModal()">&times;</button>
        </div>

        <form id="followupForm" onsubmit="submitFollowupRecord(event)">
            <input type="hidden" name="visit_id" id="fModalVisitId" value="">

            <div class="modal-body sv-modal-body">
                <!-- Channel Selector -->
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="sv-field-label">Contact Channel Used <span class="text-danger">*</span></label>
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

                <!-- Outcome & Stage -->
                <div class="sv-form-grid-2" style="margin-bottom:14px;">
                    <div class="form-group">
                        <label class="sv-field-label">Interaction Outcome <span class="text-danger">*</span></label>
                        <select name="outcome" id="fModalOutcome" class="form-select sv-select" required onchange="handleOutcomeChange(this.value)">
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
                        <label class="sv-field-label">Update Pipeline Stage</label>
                        <select name="status" id="fModalStatus" class="form-select sv-select">
                            <option value="in_discussion" selected>In Discussion</option>
                            <option value="contacted">Contacted</option>
                            <option value="converted">Converted to CRM Lead</option>
                            <option value="closed">Closed / Archived</option>
                        </select>
                    </div>
                </div>

                <!-- Discussion Notes -->
                <div class="form-group" style="margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                        <label class="sv-field-label" style="margin:0;">Discussion / Interaction Notes <span class="text-danger">*</span></label>
                        <span class="sv-hint-text">Be specific & clear</span>
                    </div>
                    <div class="sv-preset-chips" style="margin-bottom:6px;">
                        <button type="button" class="preset-tag" onclick="appendFollowupNote('Called client. Discussed scope of work.')">+ Discussed scope</button>
                        <button type="button" class="preset-tag" onclick="appendFollowupNote('Shared catalog and pricing via WhatsApp.')">+ Sent Quotation</button>
                        <button type="button" class="preset-tag" onclick="appendFollowupNote('Client needs 2 days to consult with partner.')">+ Partner decision</button>
                        <button type="button" class="preset-tag" onclick="appendFollowupNote('Client agreed to meeting at their office.')">+ Office meeting</button>
                        <button type="button" class="preset-tag" onclick="appendFollowupNote('No answer, sent follow-up text.')">+ No response</button>
                    </div>
                    <textarea name="notes" id="fModalNotes" rows="3" required placeholder="What was agreed? Any pricing discussions, obstacles or deadlines?" class="form-control" style="resize:vertical;"></textarea>
                </div>

                <!-- Schedule Next Date -->
                <div class="form-group" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label class="sv-field-label" style="margin:0;">
                            <i class="far fa-calendar-alt text-primary"></i> Next Scheduled Follow-up
                        </label>
                        <button type="button" onclick="clearNextFollowupDate()" class="sv-clear-link">Clear</button>
                    </div>

                    <div class="sv-preset-chips" style="margin-bottom:8px;">
                        <button type="button" class="preset-tag" onclick="setNextDatePreset('tomorrow_am')">Tomorrow Morning (11 AM)</button>
                        <button type="button" class="preset-tag" onclick="setNextDatePreset('tomorrow_pm')">Tomorrow Afternoon (4 PM)</button>
                        <button type="button" class="preset-tag" onclick="setNextDatePreset('in_2_days')">In 2 Days</button>
                        <button type="button" class="preset-tag" onclick="setNextDatePreset('next_week')">Next Week</button>
                    </div>

                    <input type="datetime-local" name="next_followup_date" id="fModalNextDate" class="form-control sv-datetime-input">
                </div>
            </div>

            <div class="modal-footer sv-modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeFollowupModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="fModalSubmitBtn">
                    <i class="fas fa-check"></i> <span>Save Follow-Up Record</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     MODAL: FOLLOW-UP TIMELINE & HISTORY
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="timelineModal" class="modal-overlay sv-modal" onclick="if(event.target===this)closeTimelineModal()">
    <div class="modal-content sv-modal-content" style="max-width:720px;">
        <div class="modal-header sv-modal-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <div class="modal-header-icon"><i class="fas fa-history"></i></div>
                <div>
                    <h4 id="tModalCompany">Interaction History Timeline</h4>
                    <span id="tModalSub" class="sv-modal-subtitle">Full chronological audit trail of all interactions</span>
                </div>
            </div>
            <button class="modal-close" onclick="closeTimelineModal()">&times;</button>
        </div>

        <div class="sv-timeline-topbar">
            <div id="tModalQuickBadges" style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;"></div>
            <button type="button" id="tModalAddBtn" class="btn btn-primary-custom sv-btn-pill">
                <i class="fas fa-plus"></i> <span>Log New Follow-Up</span>
            </button>
        </div>

        <div class="modal-body sv-modal-body" id="tModalTimelineBody" style="background:#fafbfc;">
            <!-- Rendered dynamically -->
        </div>

        <div class="modal-footer sv-modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
            <div id="tModalDirectWa"></div>
            <button type="button" class="btn btn-secondary-custom" onclick="closeTimelineModal()">Close Timeline</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     EXPORT SHEETS MODAL
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="exportModal" class="modal-overlay sv-modal" onclick="if(event.target===this)closeExportModal()">
    <div class="modal-content sv-modal-content" style="max-width:560px;">
        <div class="modal-header sv-modal-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <div class="modal-header-icon"><i class="fas fa-file-excel"></i></div>
                <div>
                    <h4>Export Field Visits & Follow-up Sheets</h4>
                    <span class="sv-modal-subtitle">Download formatted spreadsheet report (Excel / CSV)</span>
                </div>
            </div>
            <button class="modal-close" onclick="closeExportModal()">&times;</button>
        </div>

        <form id="exportForm" onsubmit="executeExport(event)">
            <div class="modal-body sv-modal-body">
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="sv-field-label">Select Report Structure</label>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <label class="channel-chip active" style="justify-content:center;">
                            <input type="radio" name="export_type" value="visits" checked onchange="updateChannelChipUI(this)">
                            <i class="fas fa-table"></i> Master Visits & Pipeline
                        </label>
                        <label class="channel-chip" style="justify-content:center;">
                            <input type="radio" name="export_type" value="followup_history" onchange="updateChannelChipUI(this)">
                            <i class="fas fa-history"></i> Touchpoints Log
                        </label>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label class="sv-field-label">Sales Representative</label>
                    <select id="exportRepSelect" class="form-select sv-select" style="width:100%;">
                        <option value="0">All Representatives (Company-wide)</option>
                    </select>
                </div>

                <div style="margin-bottom:14px;">
                    <label class="sv-field-label">Date Range Preset</label>
                    <div class="sv-preset-chips">
                        <button type="button" class="preset-pill active" onclick="setExportDatePreset('all', this)">All Time</button>
                        <button type="button" class="preset-pill" onclick="setExportDatePreset('today', this)">Today</button>
                        <button type="button" class="preset-pill" onclick="setExportDatePreset('yesterday', this)">Yesterday</button>
                        <button type="button" class="preset-pill" onclick="setExportDatePreset('week', this)">Last 7 Days</button>
                        <button type="button" class="preset-pill" onclick="setExportDatePreset('month', this)">This Month</button>
                    </div>
                </div>

                <div class="sv-form-grid-2">
                    <div class="form-group">
                        <label class="sv-field-label">From Date</label>
                        <input type="date" id="exportStartDate" class="form-control sv-date-input">
                    </div>
                    <div class="form-group">
                        <label class="sv-field-label">To Date</label>
                        <input type="date" id="exportEndDate" class="form-control sv-date-input">
                    </div>
                </div>
            </div>

            <div class="modal-footer sv-modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeExportModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom">
                    <i class="fas fa-download"></i> <span>Download Spreadsheet</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     MODERN TOAST NOTIFICATION CONTAINER
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="svToastContainer" class="sv-toast-container"></div>

<style>
/* ── Modern Design Variables & Foundation ── */
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

.sales-visits-wrapper {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ── KPI Scorecards with Interactive Filter ── */
.sv-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 16px;
}
.sv-kpi-card {
    background: #ffffff;
    border: 1px solid var(--sv-border);
    border-radius: var(--sv-radius);
    padding: 16px 20px;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
.sv-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
}
.sv-kpi-card.active-kpi {
    border-color: var(--sv-primary);
    box-shadow: 0 0 0 2px rgba(2, 68, 66, 0.2);
    background: #fbfdfd;
}
.sv-kpi-card .stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.sv-kpi-card .stat-label {
    font-size: 13px;
    font-weight: 700;
    color: var(--sv-slate);
    text-transform: uppercase;
    letter-spacing: .04em;
}
.sv-kpi-card .stat-number {
    font-size: 28px;
    font-weight: 800;
    color: var(--sv-dark);
    line-height: 1.1;
}
.sv-kpi-overdue { border-left: 4px solid var(--sv-danger); }
.sv-kpi-duetoday { border-left: 4px solid var(--sv-warning); }
.sv-kpi-converted { border-left: 4px solid var(--sv-success); }

.sv-icon-primary { background: #e0f2fe; color: #0284c7; }
.sv-icon-danger  { background: #fee2e2; color: #ef4444; }
.sv-icon-warning { background: #fef3c7; color: #d97706; }
.sv-icon-success { background: #dcfce7; color: #16a34a; }

.sv-stat-subtext {
    font-size: 11.5px;
    color: var(--sv-slate);
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.sv-skeleton-pulse {
    display: inline-block;
    color: #cbd5e1;
    animation: pulse 1.2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 0.5; }
    50% { opacity: 1; }
}

/* ── Main Card & Header ── */
.sv-main-card {
    background: #fff;
    border-radius: var(--sv-radius);
    border: 1px solid var(--sv-border);
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}
.sv-main-header {
    padding: 18px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--sv-border);
    background: #ffffff;
}
.sv-page-title {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    color: var(--sv-dark);
    display: flex;
    align-items: center;
    gap: 10px;
}
.sv-title-icon {
    width: 34px;
    height: 34px;
    background: var(--sv-primary-light);
    color: var(--sv-primary);
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
}
.sv-page-subtitle {
    font-size: 12.5px;
    color: var(--sv-slate);
    display: block;
    margin-top: 3px;
}
.sv-header-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}
.sv-btn-pill {
    padding: 7px 14px;
    font-size: 12.5px;
    font-weight: 600;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none !important;
}
.sv-btn-icon {
    padding: 7px 11px;
    font-size: 13px;
    border-radius: 8px;
    color: var(--sv-slate);
}
.sv-btn-primary {
    background: var(--sv-primary);
    color: #fff !important;
    border: none;
    border-radius: 8px;
    padding: 7px 16px;
    font-size: 12.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.sv-btn-primary:hover {
    background: var(--sv-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(2, 68, 66, 0.25);
}

/* ── Filter Toolbar ── */
.sv-toolbar {
    background: #fafbfc;
    border-bottom: 1px solid var(--sv-border);
    padding: 12px 20px;
}
.sv-filter-pills-wrap {
    margin-bottom: 10px;
    overflow-x: auto;
    padding-bottom: 2px;
}
.sv-quick-pills {
    display: flex;
    gap: 6px;
    align-items: center;
    white-space: nowrap;
}
.sv-pill {
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
.sv-pill:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #cbd5e1;
}
.sv-pill.active {
    background: var(--sv-primary);
    border-color: var(--sv-primary);
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(2, 68, 66, 0.2);
}
.sv-pill-urgent.active     { background: #ef4444; border-color: #ef4444; }
.sv-pill-duetoday.active   { background: #d97706; border-color: #d97706; }
.sv-pill-overdue.active    { background: #b91c1c; border-color: #b91c1c; }
.sv-pill-hot.active        { background: #dc2626; border-color: #dc2626; }
.sv-pill-discussion.active { background: #6d28d9; border-color: #6d28d9; }
.sv-pill-contacted.active  { background: #0284c7; border-color: #0284c7; }
.sv-pill-converted.active  { background: #16a34a; border-color: #16a34a; }

.sv-search-sort-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.sv-control-group {
    display: flex;
    align-items: center;
    gap: 8px;
}
.sv-control-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--sv-slate);
    white-space: nowrap;
}
.sv-select {
    height: 34px;
    font-size: 12.5px;
    padding: 4px 10px;
    border-radius: 7px;
    border: 1px solid var(--sv-border);
    background: #fff;
}
.sv-search-box {
    position: relative;
    width: 100%;
    max-width: 320px;
}
.sv-search-icon {
    position: absolute;
    left: 11px;
    top: 10px;
    color: #94a3b8;
    font-size: 12px;
}
.sv-search-input {
    padding-left: 32px;
    padding-right: 28px;
    font-size: 12.5px;
    height: 34px;
    border-radius: 7px;
    border: 1px solid var(--sv-border);
    transition: all 0.15s ease;
}
.sv-search-input:focus {
    border-color: var(--sv-primary);
    box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.12);
}
.sv-search-clear {
    position: absolute;
    right: 8px;
    top: 6px;
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 16px;
    cursor: pointer;
    padding: 0 4px;
    line-height: 1;
}
.sv-search-clear:hover { color: #334155; }
.sv-record-count {
    font-size: 12px;
    color: var(--sv-slate);
}

/* ── Modern Table Styling ── */
.sv-table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.sv-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 980px;
    background: #fff;
    font-size: 13px;
}
.sv-table th {
    background: #f8fafc;
    padding: 12px 16px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: .05em;
    border-bottom: 1.5px solid var(--sv-border);
    white-space: nowrap;
}
.sv-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}
.sv-table tr {
    transition: background 0.1s ease;
}
.sv-table tr:hover td {
    background: #fbfcfd;
}
.sv-row-urgent {
    background: #fffbfb !important;
    border-left: 4px solid var(--sv-danger) !important;
}
.sv-row-overdue {
    background: #fffafa !important;
    border-left: 4px solid #fca5a5;
}

/* ── Interactive Cells ── */
.sv-company-title {
    font-weight: 700;
    color: var(--sv-dark);
    font-size: 13.5px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.sv-client-meta {
    font-size: 12px;
    color: var(--sv-slate);
    margin-top: 3px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.sv-urgent-badge {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    font-size: 9.5px;
    font-weight: 800;
    padding: 1px 6px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: .04em;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.sv-copy-btn {
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 0 3px;
    font-size: 11px;
    transition: color 0.15s ease;
}
.sv-copy-btn:hover { color: var(--sv-primary); }

.sv-wa-pill {
    background: #16a34a !important;
    color: #ffffff !important;
    font-weight: 600;
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
.sv-wa-pill:hover {
    background: #15803d !important;
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(22, 163, 74, 0.3);
}
.sv-phone-link {
    font-size: 12px;
    color: var(--sv-slate);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 3px;
    font-weight: 500;
}
.sv-phone-link:hover {
    color: var(--sv-dark);
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
.badge-hot   { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }
.badge-warm  { background: #fffbeb; color: #b45309; border: 1px solid #fef3c7; }
.badge-cold  { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }

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
.sv-actions-cell {
    display: inline-flex;
    gap: 5px;
    justify-content: flex-end;
    align-items: center;
}
.sv-btn-followup {
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
.sv-btn-followup:hover {
    background: var(--sv-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(2, 68, 66, 0.2);
}

.sv-icon-btn {
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
.sv-icon-btn:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: var(--sv-dark);
}
.sv-btn-lead-convert {
    color: #16a34a;
}
.sv-btn-lead-convert:hover {
    background: #dcfce7;
    border-color: #86efac;
    color: #15803d;
}

/* ── Modals Framework ── */
.sv-modal {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
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
.sv-modal.active { display: flex; }
.sv-modal-content {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-height: 90vh !important;
    box-shadow: 0 24px 48px -12px rgba(15, 23, 42, 0.25);
    display: flex;
    flex-direction: column;
    overflow-y: auto !important;
    animation: modalSlideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    margin: auto;
}
@keyframes modalSlideUp {
    from { transform: translateY(18px) scale(0.98); opacity: 0; }
    to { transform: translateY(0) scale(1); opacity: 1; }
}
.sv-modal-header {
    background: linear-gradient(135deg, var(--sv-primary) 0%, #035a58 100%);
    color: #fff;
    padding: 16px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 16px 16px 0 0;
    flex-shrink: 0;
}
.sv-modal-header h4 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #fff;
}
.sv-modal-subtitle {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.85);
    display: block;
    margin-top: 2px;
}
.sv-modal-body {
    padding: 20px 24px;
    overflow-y: auto !important;
    flex: 1 1 auto;
}
.sv-modal-footer {
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

/* ── Form Section Containers ── */
.sv-form-section {
    background: #ffffff;
    border: 1px solid var(--sv-border);
    border-radius: 10px;
    padding: 14px 16px;
}
.sv-section-title {
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--sv-slate);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.sv-form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
@media (max-width: 600px) {
    .sv-form-grid-2 { grid-template-columns: 1fr; }
}

.sv-field-label {
    font-size: 12.5px;
    font-weight: 700;
    color: #334155;
    margin-bottom: 5px;
    display: block;
}
.sv-hint-text {
    font-size: 11px;
    color: #94a3b8;
}
.sv-link-btn {
    background: none;
    border: none;
    color: var(--sv-success);
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    padding: 0;
}
.sv-clear-link {
    background: none;
    border: none;
    color: var(--sv-danger);
    font-size: 11px;
    cursor: pointer;
    font-weight: 600;
}

/* ── Chips & Presets ── */
.sv-preset-chips {
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

.sv-interest-chips {
    display: flex;
    gap: 8px;
}
.interest-chip {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 9px 8px;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    font-size: 12.5px;
    font-weight: 600;
    color: #475569;
    background: #fff;
    transition: all 0.15s ease;
}
.interest-chip input { display: none; }
.interest-chip.active-chip {
    border-color: var(--sv-primary);
    background: #f0fdfa;
    color: var(--sv-primary);
    font-weight: 700;
}

.sv-urgent-toggle {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
    background: #fff5f5;
    border: 1px solid #fecaca;
    border-radius: 8px;
    cursor: pointer;
}
.sv-urgent-toggle input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--sv-danger);
}
.sv-urgent-text {
    font-size: 13px;
    font-weight: 700;
    color: #b91c1c;
    display: block;
}
.sv-urgent-subtext {
    font-size: 11px;
    color: #991b1b;
    display: block;
}

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

/* ── Timeline Styles ── */
.sv-timeline-topbar {
    padding: 12px 24px;
    background: #fff;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    flex-shrink: 0;
}
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

/* ── Categories Checkboxes Grid ── */
.sv-categories-grid {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.cat-box {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 9px 12px;
    background: #fbfcfd;
}
.cat-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 13px;
    color: #0f172a;
    margin: 0;
}
.cat-label input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: var(--sv-primary);
}
.sub-box {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed #e2e8f0;
}
.sub-box label {
    font-size: 12px;
    color: #475569;
    font-weight: normal;
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    margin: 0;
}
.sub-box label input[type="checkbox"] {
    accent-color: var(--sv-primary);
}

/* ── Modern Toast Notifications ── */
.sv-toast-container {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 10px;
    pointer-events: none;
}
.sv-toast {
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
.sv-toast-success { border-left: 4px solid var(--sv-success); }
.sv-toast-error   { border-left: 4px solid var(--sv-danger); }
.sv-toast-warning { border-left: 4px solid var(--sv-warning); }
.sv-toast-info    { border-left: 4px solid var(--sv-primary); }

.sv-toast-icon {
    font-size: 16px;
    margin-top: 2px;
}
.sv-toast-success .sv-toast-icon { color: var(--sv-success); }
.sv-toast-error .sv-toast-icon   { color: var(--sv-danger); }
.sv-toast-warning .sv-toast-icon { color: var(--sv-warning); }
.sv-toast-info .sv-toast-icon    { color: var(--sv-primary); }

.sv-toast-body { flex: 1; }
.sv-toast-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--sv-dark);
}
.sv-toast-message {
    font-size: 12px;
    color: var(--sv-slate);
    margin-top: 2px;
    line-height: 1.35;
}
.sv-toast-close {
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 15px;
    cursor: pointer;
    line-height: 1;
    padding: 0;
}
.sv-toast-close:hover { color: #334155; }
</style>

<script>
let currentFilter = 'all';
let currentSort   = 'urgency';
let searchQuery   = '';
let allVisits     = [];
let searchDebounceTimer = null;

document.addEventListener('DOMContentLoaded', () => {
    loadVisits();
    loadSalesRepsForExport();

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('log') === '1') {
        openVisitModal('add');
    }
});

// ── Modern Toast Notification System ──
function showToast(title, message, type = 'info') {
    const container = document.getElementById('svToastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `sv-toast sv-toast-${type}`;

    let icon = 'fas fa-info-circle';
    if (type === 'success') icon = 'fas fa-check-circle';
    else if (type === 'error') icon = 'fas fa-exclamation-circle';
    else if (type === 'warning') icon = 'fas fa-exclamation-triangle';

    toast.innerHTML = `
        <div class="sv-toast-icon"><i class="${icon}"></i></div>
        <div class="sv-toast-body">
            <div class="sv-toast-title">${escapeHtml(title)}</div>
            <div class="sv-toast-message">${escapeHtml(message)}</div>
        </div>
        <button class="sv-toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        if (toast && toast.parentElement) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            setTimeout(() => toast.remove(), 200);
        }
    }, 4000);
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

// ── Load Visits with Robust Error Handling ──
function loadVisits() {
    const refreshIcon = document.getElementById('refreshIcon');
    if (refreshIcon) refreshIcon.classList.add('fa-spin');

    fetch(`sales_visits_api.php?action=fetch_all&filter=${encodeURIComponent(currentFilter)}`)
        .then(r => {
            if (!r.ok) {
                throw new Error(`Server returned status HTTP ${r.status}`);
            }
            return r.json();
        })
        .then(data => {
            if (refreshIcon) refreshIcon.classList.remove('fa-spin');
            if (data.success) {
                allVisits = data.items || [];
                renderStats(data.stats || {});
                renderTable();
            } else {
                renderErrorState(data.message || 'Failed to retrieve visit records.');
            }
        })
        .catch(err => {
            if (refreshIcon) refreshIcon.classList.remove('fa-spin');
            console.error('Fetch Visits Error:', err);
            renderErrorState('Unable to reach server. Please ensure database is connected and click retry below.');
        });
}

function renderErrorState(msg) {
    document.getElementById('visitsTableBody').innerHTML = `
        <tr>
            <td colspan="7" style="text-align:center; padding:45px 20px;">
                <div style="max-width:440px; margin:0 auto; background:#fff5f5; border:1px solid #fed7d7; border-radius:12px; padding:24px;">
                    <i class="fas fa-exclamation-triangle" style="font-size:32px; color:#ef4444; margin-bottom:10px;"></i>
                    <h5 style="margin:0 0 6px; font-weight:700; color:#991b1b;">Connection Notice</h5>
                    <p style="margin:0 0 16px; font-size:13px; color:#7f1d1d;">${escapeHtml(msg)}</p>
                    <button type="button" class="btn btn-primary-custom" onclick="loadVisits()" style="padding:6px 16px; font-size:12.5px;">
                        <i class="fas fa-sync-alt"></i> Retry Connection
                    </button>
                </div>
            </td>
        </tr>
    `;
}

function renderStats(stats) {
    document.getElementById('statTotal').textContent     = stats.total || 0;
    document.getElementById('statOverdue').textContent   = stats.overdue || 0;
    document.getElementById('statDueToday').textContent  = stats.due_today || 0;
    document.getElementById('statConverted').textContent = stats.converted || 0;

    // Highlight active KPI scorecard
    document.querySelectorAll('.sv-kpi-card').forEach(c => c.classList.remove('active-kpi'));
    const activeKpi = document.getElementById('kpiCard_' + currentFilter);
    if (activeKpi) activeKpi.classList.add('active-kpi');
}

function setFilter(val) {
    currentFilter = val;

    // Update Quick Pills UI
    document.querySelectorAll('#quickFilterPills .sv-pill').forEach(btn => {
        if (btn.getAttribute('data-filter') === val) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    loadVisits();
}

function setSort(val) {
    currentSort = val;
    renderTable();
}

function handleSearch(val) {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        searchQuery = val.trim().toLowerCase();
        const clearBtn = document.getElementById('searchClearBtn');
        if (clearBtn) clearBtn.style.display = searchQuery ? 'block' : 'none';
        renderTable();
    }, 200);
}

function clearSearch() {
    const input = document.getElementById('searchInput');
    input.value = '';
    searchQuery = '';
    document.getElementById('searchClearBtn').style.display = 'none';
    renderTable();
}

// ── Render Modern Table ──
function renderTable() {
    const container = document.getElementById('visitsTableBody');
    let items = [...allVisits];

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

    document.getElementById('visibleCount').textContent = items.length;

    if (items.length === 0) {
        container.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center; padding:60px 24px;">
                    <div style="max-width:380px; margin:0 auto; color:#64748b;">
                        <i class="fas fa-street-view" style="font-size:38px; color:#cbd5e1; margin-bottom:12px;"></i>
                        <h5 style="margin:0 0 6px; font-weight:700; color:#334155;">No Field Visits Found</h5>
                        <p style="margin:0 0 16px; font-size:13px;">No client visits match your current filter or search criteria.</p>
                        <div style="display:flex; justify-content:center; gap:8px;">
                            ${(currentFilter !== 'all' || searchQuery) ? `
                                <button type="button" class="btn btn-secondary-custom" onclick="clearFilters()" style="padding:6px 14px; font-size:12.5px;">
                                    Reset Filters
                                </button>
                            ` : ''}
                            <button type="button" class="sv-btn-primary" onclick="openVisitModal('add')">
                                <i class="fas fa-plus"></i> Log Client Visit
                            </button>
                        </div>
                    </div>
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
        
        let priorityBadge = '';
        if (v.interest_level === 'hot') {
            priorityBadge = '<span class="badge-priority badge-hot"><i class="fas fa-fire"></i> Hot</span>';
        } else if (v.interest_level === 'warm') {
            priorityBadge = '<span class="badge-priority badge-warm"><i class="fas fa-bolt"></i> Warm</span>';
        } else {
            priorityBadge = '<span class="badge-priority badge-cold"><i class="fas fa-snowflake"></i> Standard</span>';
        }

        let stageBadge = '';
        if (v.status === 'converted') {
            stageBadge = '<span class="badge-stage badge-stage-converted"><i class="fas fa-check-circle"></i> Converted</span>';
        } else if (v.status === 'in_discussion') {
            stageBadge = '<span class="badge-stage badge-stage-discussion"><i class="fas fa-comments"></i> In Discussion</span>';
        } else if (v.status === 'contacted') {
            stageBadge = '<span class="badge-stage badge-stage-contacted"><i class="fas fa-phone"></i> Contacted</span>';
        } else if (v.status === 'closed') {
            stageBadge = '<span class="badge-stage badge-stage-closed"><i class="fas fa-archive"></i> Closed</span>';
        } else {
            stageBadge = '<span class="badge-stage badge-stage-pending"><i class="fas fa-clock"></i> Pending Contact</span>';
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
            scheduleBadge = `<span class="fo-schedule-badge fo-completed"><i class="fas fa-check"></i> Resolved</span>`;
        } else {
            scheduleBadge = `<span class="fo-schedule-badge fo-none"><i class="far fa-calendar-times"></i> No schedule set</span>`;
        }

        let outcomeHtml = '';
        if (v.last_outcome) {
            const outClean = v.last_outcome.toLowerCase().replace(/[^a-z]/g, '');
            const outLabel = escapeHtml(v.last_outcome.replace(/_/g, ' '));
            outcomeHtml = `<span class="outcome-pill outcome-pill-${outClean}" style="text-transform:capitalize;">${outLabel}</span>`;
        }

        let catBadges = '';
        if (v.categories_arr && v.categories_arr.length > 0) {
            catBadges = v.categories_arr.slice(0, 2).map(c => `<span style="background:#e0f2fe; color:#0284c7; padding:2px 7px; border-radius:5px; font-size:11px; font-weight:700;">${escapeHtml(c)}</span>`).join(' ');
            if (v.categories_arr.length > 2) {
                catBadges += ` <span style="color:#64748b; font-size:11px; font-weight:600;">+${v.categories_arr.length - 2}</span>`;
            }
        } else {
            catBadges = '<span style="color:#94a3b8; font-size:11.5px;">General</span>';
        }

        let reachOutHtml = '';
        if (cleanWa) {
            reachOutHtml = `
                <div style="display:flex; flex-direction:column; gap:3px; align-items:flex-start;">
                    <a href="https://wa.me/${cleanWa}?text=${waMsg}" target="_blank" class="sv-wa-pill" title="Start WhatsApp conversation">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </a>
                    <div style="display:flex; align-items:center; gap:4px;">
                        <a href="tel:${escapeHtml(v.phone)}" class="sv-phone-link" title="Call directly">
                            <i class="fas fa-phone-alt" style="font-size:10px;"></i> ${escapeHtml(v.phone)}
                        </a>
                        <button type="button" class="sv-copy-btn" onclick="copyTextToClipboard('${escapeHtml(v.phone)}', 'Phone copied!')" title="Copy phone number">
                            <i class="far fa-copy"></i>
                        </button>
                    </div>
                </div>
            `;
        } else {
            reachOutHtml = `
                <div style="display:flex; align-items:center; gap:4px;">
                    <a href="tel:${escapeHtml(v.phone)}" class="sv-phone-link" style="font-weight:600; color:#334155;">
                        <i class="fas fa-phone-alt" style="font-size:11px;"></i> ${escapeHtml(v.phone)}
                    </a>
                    <button type="button" class="sv-copy-btn" onclick="copyTextToClipboard('${escapeHtml(v.phone)}', 'Phone copied!')" title="Copy phone number">
                        <i class="far fa-copy"></i>
                    </button>
                </div>
            `;
        }

        html += `
            <tr class="${isUrgent ? 'sv-row-urgent' : (v.followup_status === 'overdue' ? 'sv-row-overdue' : '')}">
                <td>
                    <div class="sv-company-title">
                        <span>${escapeHtml(v.company_name)}</span>
                        ${isUrgent ? '<span class="sv-urgent-badge"><i class="fas fa-bolt"></i> Urgent</span>' : ''}
                    </div>
                    <div class="sv-client-meta">
                        <i class="fas fa-user-circle" style="color:#94a3b8;"></i>
                        <span>${escapeHtml(v.client_name)}</span>
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
                    <div class="sv-actions-cell">
                        <button class="sv-btn-followup" onclick="openFollowupModal(${v.id})" title="Log Follow-up Interaction">
                            <i class="fas fa-plus"></i> Follow-Up
                        </button>
                        <button class="sv-icon-btn" onclick="openTimelineModal(${v.id})" title="View Complete Interaction History">
                            <i class="fas fa-history" style="color:#6d28d9;"></i>
                        </button>
                        <button class="sv-icon-btn" onclick="viewVisit(${v.id})" title="View Client Summary">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${(v.status !== 'converted') ? `
                            <button class="sv-icon-btn sv-btn-lead-convert" onclick="quickConvertToLead(${v.id})" title="Convert to CRM Lead">
                                <i class="fas fa-user-check"></i>
                            </button>
                        ` : ''}
                        <button class="sv-icon-btn" onclick="openVisitModal('edit', ${v.id})" title="Edit Details">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <?php if ($is_admin_user): ?>
                        <button class="sv-icon-btn" onclick="deleteVisit(${v.id}, '${escapeJsString(v.company_name)}')" title="Delete" style="color:#ef4444;">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        `;
    });

    container.innerHTML = html;
}

function clearFilters() {
    currentFilter = 'all';
    clearSearch();
    setFilter('all');
}

// ── Quick Convert to CRM Lead ──
function quickConvertToLead(visitId) {
    if (!confirm('Convert this client visit into an active CRM Lead?')) return;

    const fd = new FormData();
    fd.append('action', 'convert_to_lead');
    fd.append('visit_id', visitId);

    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Lead Created', data.message || 'Successfully converted to CRM Lead!', 'success');
                loadVisits();
            } else {
                showToast('Conversion Error', data.message || 'Failed to convert to lead.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error', 'Server request failed.', 'error');
        });
}

// ── Follow-Up Modal Logic ──
function openFollowupModal(visitId) {
    const v = allVisits.find(item => item.id == visitId);
    document.getElementById('fModalVisitId').value = visitId;
    if (v) {
        document.getElementById('fModalCompanyTitle').textContent = `Log Follow-Up: ${v.company_name}`;
        document.getElementById('fModalClientSub').textContent = `Client: ${v.client_name} · Phone: ${v.phone} · Current: ${v.status.toUpperCase()}`;
        document.getElementById('fModalStatus').value = (v.status === 'pending') ? 'in_discussion' : v.status;
    } else {
        document.getElementById('fModalCompanyTitle').textContent = `Log Follow-Up (Visit #${visitId})`;
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
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Saving...</span>';

    const fd = new FormData(document.getElementById('followupForm'));
    fd.append('action', 'add_followup');

    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> <span>Save Follow-Up Record</span>';

            if (data.success) {
                closeFollowupModal();
                showToast('Follow-Up Saved', 'Interaction recorded successfully!', 'success');
                loadVisits();
            } else {
                showToast('Error', data.message || 'Failed to save follow-up.', 'error');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> <span>Save Follow-Up Record</span>';
            showToast('Network Error', 'Failed to save follow-up interaction.', 'error');
        });
}

// ── Follow-Up Timeline Modal ──
function openTimelineModal(visitId) {
    document.getElementById('tModalTimelineBody').innerHTML = `
        <div style="text-align:center; padding:40px; color:#888;">
            <i class="fas fa-spinner fa-spin" style="font-size:24px; color:var(--sv-primary); margin-bottom:10px;"></i>
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
                document.getElementById('tModalTimelineBody').innerHTML = `<p style="color:#ef4444; padding:20px;">${escapeHtml(data.message)}</p>`;
            }
        })
        .catch(err => {
            document.getElementById('tModalTimelineBody').innerHTML = `<p style="color:#ef4444; padding:20px;">Failed to load interaction timeline.</p>`;
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
        <a href="https://wa.me/${cleanWa}" target="_blank" class="sv-wa-pill">
            <i class="fab fa-whatsapp"></i> Direct WhatsApp
        </a>
    ` : '';

    const badgeWrap = document.getElementById('tModalQuickBadges');
    badgeWrap.innerHTML = `
        <span class="badge-stage badge-stage-${visit.status}">${visit.status.replace(/_/g, ' ').toUpperCase()}</span>
        <span class="badge-priority badge-${visit.interest_level}">${visit.interest_level.toUpperCase()}</span>
        <span style="font-size:12px; font-weight:700; color:#475569;">${followups.length} Touchpoint${followups.length===1?'':'s'}</span>
    `;

    const container = document.getElementById('tModalTimelineBody');

    let html = '<div class="v-timeline">';
    if (followups.length === 0) {
        html += `
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:20px; text-align:center;">
                <p style="margin:0; font-size:13.5px; font-weight:600; color:#475569;">No follow-up touchpoints recorded yet.</p>
                <small style="color:#94a3b8;">Click "+ Log New Follow-Up" above to add the first interaction record.</small>
            </div>
        `;
    } else {
        followups.forEach(f => {
            let chIcon = '<i class="fas fa-phone-alt"></i>';
            if (f.contact_channel === 'whatsapp') chIcon = '<i class="fab fa-whatsapp"></i>';
            else if (f.contact_channel === 'meeting') chIcon = '<i class="fas fa-handshake"></i>';
            else if (f.contact_channel === 'email') chIcon = '<i class="fas fa-envelope"></i>';
            else if (f.contact_channel === 'office_visit') chIcon = '<i class="fas fa-building"></i>';

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

                        <div style="font-size:12.5px; color:#334155; line-height:1.45; background:#f8fafc; padding:9px 12px; border-radius:7px; border:1px solid #f1f5f9; margin-bottom:6px;">
                            ${escapeHtml(f.notes)}
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:11px; color:#64748b;">
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
                    <strong style="font-size:13px; color:#0f172a;"><i class="fas fa-street-view" style="color:#16a34a;"></i> Field Visit Logged</strong>
                    <span style="font-size:11.5px; color:#64748b;">${visit.time_ago || ''}</span>
                </div>
                <div style="font-size:12px; color:#475569; margin-bottom:4px;">
                    By <strong>${escapeHtml(visit.sales_rep_name)}</strong> · Availability: <strong>${escapeHtml(visit.contact_availability)}</strong>
                </div>
                ${visit.remarks ? `
                    <div style="font-size:12px; color:#334155; background:#f8fafc; padding:7px 10px; border-radius:5px; border:1px dashed #cbd5e1;">
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

// ── Log / Edit Visit Modal ──
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
        if (v.next_followup_date) {
            document.getElementById('f_next_followup_date').value = v.next_followup_date.substring(0, 16);
        }

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
    if (p) {
        document.getElementById('f_whatsapp').value = p;
        showToast('WhatsApp Set', 'Phone number copied to WhatsApp field.', 'info');
    }
}

function setAvailPreset(txt) {
    document.getElementById('f_contact_availability').value = txt;
}

function setVisitNextPreset(preset) {
    const input = document.getElementById('f_next_followup_date');
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
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Saving...</span>';

    const formData = new FormData(document.getElementById('visitForm'));
    fetch('sales_visits_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> <span>Save Visit Record</span>';
            if (data.success) {
                closeVisitModal();
                showToast('Success', data.message || 'Visit saved successfully!', 'success');
                loadVisits();
            } else {
                showToast('Error', data.message || 'Failed to save visit record.', 'error');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> <span>Save Visit Record</span>';
            showToast('Network Error', 'Failed to save visit record. Please check connection.', 'error');
        });
}

function deleteVisit(id, company) {
    if (!confirm(`Are you sure you want to permanently delete the visit record for "${company}"?`)) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fd.append('visit_id', id);
    fetch('sales_visits_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Deleted', 'Visit record deleted.', 'info');
                loadVisits();
            } else {
                showToast('Error', data.message || 'Failed to delete visit.', 'error');
            }
        })
        .catch(err => {
            showToast('Error', 'Server request failed.', 'error');
        });
}

// ── View Visit Modal ──
function viewVisit(id) {
    const v = allVisits.find(item => item.id == id);
    if (!v) return;

    const cleanWa = formatWhatsAppNumber(v.whatsapp || v.phone);
    const waMsg   = encodeURIComponent(`Hello ${v.client_name}, this is Graphicafix following up on your discussion regarding ${v.company_name}.`);

    let cats = (v.categories_arr && v.categories_arr.length) ? v.categories_arr.join(', ') : 'None';
    let subs = (v.subcategories_arr && v.subcategories_arr.length) ? v.subcategories_arr.join('; ') : 'None';

    document.getElementById('viewModalBody').innerHTML = `
        <div style="margin-bottom:16px;">
            <div style="font-size:18px; font-weight:800; color:#0f172a;">${escapeHtml(v.company_name)} ${v.is_urgent == 1 ? '<span class="sv-urgent-badge">URGENT</span>' : ''}</div>
            <div style="color:#64748b; font-size:13px; margin-top:2px;">Contact: <strong>${escapeHtml(v.client_name)}</strong> · Stage: <strong style="text-transform:uppercase;">${v.status}</strong></div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px; font-size:12.5px;">
            <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11px; font-weight:700;">PHONE NUMBER</div>
                <div style="font-weight:700; color:#0f172a; margin-top:2px;">${escapeHtml(v.phone)}</div>
            </div>
            <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11px; font-weight:700;">WHATSAPP NUMBER</div>
                <div style="font-weight:700; color:#16a34a; margin-top:2px;">${escapeHtml(v.whatsapp || v.phone)}</div>
            </div>
            <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11px; font-weight:700;">INTEREST LEVEL</div>
                <div style="font-weight:700; text-transform:uppercase; margin-top:2px;">${v.interest_level}</div>
            </div>
            <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11px; font-weight:700;">CONTACT WINDOW</div>
                <div style="font-weight:700; color:#0f172a; margin-top:2px;">${escapeHtml(v.contact_availability)}</div>
            </div>
        </div>
        <div style="margin-bottom:12px; font-size:13px;">
            <div style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase;">SERVICES DISCUSSED</div>
            <div style="color:#0f172a; margin-top:3px; font-weight:600;">${escapeHtml(cats)}</div>
            ${subs !== 'None' ? `<div style="color:#64748b; font-size:12px; margin-top:3px;"><em>Sub-items:</em> ${escapeHtml(subs)}</div>` : ''}
        </div>
        <div style="margin-bottom:14px; font-size:13px;">
            <div style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase;">FIELD REMARKS</div>
            <div style="background:#f1f5f9; padding:10px 12px; border-radius:8px; color:#334155; margin-top:4px; font-size:12.5px; line-height:1.45;">${escapeHtml(v.remarks || 'No remarks recorded.')}</div>
        </div>

        ${v.admin_notes ? `
            <div style="margin-bottom:14px;">
                <div style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:4px;">COMMUNICATION TRAIL</div>
                <pre style="background:#fff; padding:10px 12px; border-radius:7px; font-size:12px; color:#475569; white-space:pre-wrap; border:1px solid #e2e8f0; margin:0; font-family:inherit;">${escapeHtml(v.admin_notes)}</pre>
            </div>
        ` : ''}

        <div style="display:flex; justify-content:space-between; align-items:center; font-size:11.5px; color:#94a3b8; border-top:1px solid #f1f5f9; padding-top:10px;">
            <span>Logged by ${escapeHtml(v.sales_rep_name)} · ${v.time_ago || ''}</span>
            <button type="button" class="btn btn-secondary-custom" onclick="closeViewModal(); openTimelineModal(${v.id});" style="font-size:11.5px; padding:4px 10px;">
                <i class="fas fa-history"></i> View Full Timeline
            </button>
        </div>
    `;

    document.getElementById('viewModalActions').innerHTML = `
        <div style="display:flex; gap:8px;">
            <button type="button" class="btn btn-primary-custom" onclick="closeViewModal(); openFollowupModal(${v.id});">
                <i class="fas fa-plus"></i> Log Follow-Up
            </button>
            ${cleanWa ? `
                <a href="https://wa.me/${cleanWa}?text=${waMsg}" target="_blank" class="sv-wa-pill">
                    <i class="fab fa-whatsapp"></i> WhatsApp Reach Out
                </a>
            ` : ''}
        </div>
    `;

    document.getElementById('viewModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
    document.body.style.overflow = '';
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
    showToast('Export Started', 'Spreadsheet download initiated.', 'success');
}

// ── Helpers ──
function copyTextToClipboard(text, successMsg = 'Copied to clipboard!') {
    if (!text) return;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Copied', successMsg, 'info');
        });
    } else {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        showToast('Copied', successMsg, 'info');
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
