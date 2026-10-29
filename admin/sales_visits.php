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
    <div class="stats-row" style="margin-bottom:20px;">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total Field Visits</span>
                <div class="stat-icon" style="background:var(--light);color:var(--primary);"><i class="fas fa-street-view"></i></div>
            </div>
            <div class="stat-number" id="statTotal">--</div>
            <div style="font-size:11.5px; color:#64748b; margin-top:4px;">Total client visits logged</div>
        </div>
        <div class="stat-card" style="border-left: 3.5px solid #ef4444;">
            <div class="stat-header">
                <span class="stat-label">Overdue Follow-ups</span>
                <div class="stat-icon" style="background:#fee2e2;color:#ef4444;"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
            <div class="stat-number" id="statOverdue" style="color:#ef4444;">--</div>
            <div style="font-size:11.5px; color:#ef4444; margin-top:4px; font-weight:600;">Missed follow-up schedule</div>
        </div>
        <div class="stat-card" style="border-left: 3.5px solid #d97706;">
            <div class="stat-header">
                <span class="stat-label">Due Today</span>
                <div class="stat-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-clock"></i></div>
            </div>
            <div class="stat-number" id="statDueToday" style="color:#d97706;">--</div>
            <div style="font-size:11.5px; color:#b45309; margin-top:4px;">Scheduled for today</div>
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

    <!-- ── Universal Main Card & Table ── -->
    <div class="main-card">
        <div class="main-header" style="flex-wrap:wrap; gap:12px;">
            <div>
                <h3 style="margin:0; font-size:17px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-street-view" style="color:var(--primary);"></i> Client Field Visits Log & Follow-Up Tracker
                </h3>
                <span style="font-size:12px; color:#64748b;">Manage field visits, log multi-stage follow-ups, and convert prospects.</span>
            </div>

            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <button type="button" onclick="openExportModal()" class="btn btn-secondary-custom" style="padding:7px 14px; font-size:12.5px; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-file-excel" style="color:#16a34a;"></i> Export Sheets
                </button>
                <?php if ($is_admin_user): ?>
                <a href="sales_response_unit.php" class="btn btn-secondary-custom" style="padding:7px 14px; font-size:12.5px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-satellite-dish" style="color:var(--primary);"></i> Response Unit
                </a>
                <?php endif; ?>
                <button class="add-client" onclick="openVisitModal('add')">
                    <i class="fas fa-plus"></i> Log Client Visit
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div style="padding:14px 20px; border-bottom:1px solid #f1f5f9; background:#fafbfc; display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between;">
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <div style="display:flex; align-items:center; gap:6px;">
                    <span style="font-size:12.5px; font-weight:600; color:#64748b;">Filter:</span>
                    <select id="filterSelect" onchange="setFilter(this.value)" class="form-select" style="width:auto; padding:5px 12px; font-size:12.5px; height:34px;">
                        <option value="all">All Field Visits</option>
                        <option value="pending">Pending Initial Contact</option>
                        <option value="in_discussion">In Discussion</option>
                        <option value="due_today">⏰ Due Today</option>
                        <option value="overdue">🚨 Overdue Follow-ups</option>
                        <option value="urgent">🚨 Urgent Flagged</option>
                        <option value="hot">🔥 High Priority (Hot)</option>
                        <option value="warm">⚡ Medium Priority (Warm)</option>
                        <option value="cold">❄️ Standard Priority (Cold)</option>
                        <option value="contacted">Contacted</option>
                        <option value="converted">Converted to CRM</option>
                        <option value="closed">Closed / Archived</option>
                    </select>
                </div>

                <div style="display:flex; align-items:center; gap:6px;">
                    <span style="font-size:12.5px; font-weight:600; color:#64748b;">Sort:</span>
                    <select id="sortSelect" onchange="setSort(this.value)" class="form-select" style="width:auto; padding:5px 12px; font-size:12.5px; height:34px;">
                        <option value="urgency">Urgency & Priority First</option>
                        <option value="followup_due">Next Follow-Up Due Date</option>
                        <option value="followup_cnt">Most Follow-ups Completed</option>
                        <option value="interest">Interest Level (High to Low)</option>
                        <option value="contact_time">Preferred Contact Window</option>
                        <option value="newest">Newest Logged First</option>
                    </select>
                </div>
            </div>

            <div style="position:relative; width:100%; max-width:300px;">
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
                        <th style="width:10%;">Logged By</th>
                        <th style="width:10%; text-align:right;">Actions</th>
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
            <button class="modal-close" onclick="closeVisitModal()">&times;</button>
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
                    <div style="flex-wrap:wrap; display:flex; gap:6px; margin-bottom:8px;">
                        <button type="button" class="preset-tag" onclick="setAvailPreset('Available in 1-2 hours')">In 1-2 hours</button>
                        <button type="button" class="preset-tag" onclick="setAvailPreset('Evening today (5:00 PM - 8:00 PM)')">Today Evening</button>
                        <button type="button" class="preset-tag" onclick="setAvailPreset('Tomorrow morning (10:00 AM - 12:00 PM)')">Tomorrow Morning</button>
                        <button type="button" class="preset-tag" onclick="setAvailPreset('Tomorrow afternoon (2:00 PM - 5:00 PM)')">Tomorrow Afternoon</button>
                        <button type="button" class="preset-tag" onclick="setAvailPreset('After 2 days')">After 2 days</button>
                        <button type="button" class="preset-tag" onclick="setAvailPreset('Next week Monday')">Next week</button>
                    </div>
                    <input type="text" name="contact_availability" id="f_contact_availability" required placeholder="e.g. Tomorrow afternoon at 3:00 PM" class="form-control">
                </div>

                <!-- Row 5: Initial Scheduled Follow-Up (Optional) -->
                <div class="form-group" style="margin-bottom:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px;">
                    <label style="font-size:12.5px; font-weight:700; color:#0f172a; margin-bottom:4px; display:block;">
                        <i class="far fa-calendar-alt" style="color:var(--primary); margin-right:4px;"></i> Schedule Initial Follow-Up (Optional)
                    </label>
                    <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:6px;">
                        <button type="button" class="preset-tag" onclick="setVisitNextPreset('tomorrow_am')">Tomorrow Morning (11:00 AM)</button>
                        <button type="button" class="preset-tag" onclick="setVisitNextPreset('tomorrow_pm')">Tomorrow Afternoon (4:00 PM)</button>
                        <button type="button" class="preset-tag" onclick="setVisitNextPreset('in_2_days')">In 2 Days</button>
                        <button type="button" class="preset-tag" onclick="setVisitNextPreset('next_week')">Next Week</button>
                        <button type="button" class="preset-tag" onclick="document.getElementById('f_next_followup_date').value=''" style="color:#ef4444;">Clear</button>
                    </div>
                    <input type="datetime-local" name="next_followup_date" id="f_next_followup_date" class="form-control" style="font-size:12.5px; max-width:280px;">
                </div>

                <!-- Row 6: Requirements Categories & Subcategories -->
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

                <!-- Row 7: Remarks & Presets -->
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
     UNIVERSAL MODAL: VIEW VISIT DETAILS & TIMELINE
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="viewModal" class="modal-overlay" onclick="if(event.target===this)closeViewModal()">
    <div class="modal-content" style="max-width:680px;">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon"><i class="fas fa-eye"></i></div>
                <span>Visit Details & History</span>
            </h4>
            <button class="modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body" id="viewModalBody">
            <!-- Populated dynamically -->
        </div>
        <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
            <div id="viewModalActions"></div>
            <button type="button" class="btn btn-secondary-custom" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     MODAL: LOG STRUCTURED FOLLOW-UP
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="followupModal" class="modal-overlay" onclick="if(event.target===this)closeFollowupModal()">
    <div class="modal-content" style="max-width:640px;">
        <div class="modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <div class="modal-header-icon">
                    <i class="fas fa-phone-volume"></i>
                </div>
                <div>
                    <h4 id="fModalCompanyTitle" style="color:#fff;">Log Follow-Up Track Record</h4>
                    <span id="fModalClientSub" style="font-size:12px; color:rgba(255,255,255,0.85); display:block; margin-top:2px;">Record interaction details and schedule next touchpoint</span>
                </div>
            </div>
            <button class="modal-close" onclick="closeFollowupModal()">&times;</button>
        </div>

        <form id="followupForm" onsubmit="submitFollowupRecord(event)">
            <input type="hidden" name="visit_id" id="fModalVisitId" value="">

            <div class="modal-body">
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
                <div class="form-group" style="margin-bottom:6px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px;">
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
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeFollowupModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="fModalSubmitBtn" style="display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-check"></i> Save Follow-Up Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     MODAL: FOLLOW-UP TIMELINE & HISTORY
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="timelineModal" class="modal-overlay" onclick="if(event.target===this)closeTimelineModal()">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <div class="modal-header-icon">
                    <i class="fas fa-history"></i>
                </div>
                <div>
                    <h4 id="tModalCompany" style="color:#fff;">Follow-Up Track Record Timeline</h4>
                    <span id="tModalSub" style="font-size:12px; color:rgba(255,255,255,0.85); display:block; margin-top:2px;">Chronological interaction history</span>
                </div>
            </div>
            <button class="modal-close" onclick="closeTimelineModal()">&times;</button>
        </div>

        <div style="padding:12px 22px; background:#fff; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; flex-shrink:0;">
            <div id="tModalQuickBadges" style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;"></div>
            <button type="button" id="tModalAddBtn" class="btn btn-primary-custom" style="padding:5px 12px; font-size:12px; font-weight:600; display:inline-flex; align-items:center; gap:5px;">
                <i class="fas fa-plus"></i> Log New Follow-Up
            </button>
        </div>

        <div class="modal-body" id="tModalTimelineBody" style="background:#fafbfc;">
            <!-- Rendered dynamically -->
        </div>

        <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
            <div id="tModalDirectWa"></div>
            <button type="button" class="btn btn-secondary-custom" onclick="closeTimelineModal()">Close Timeline</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     EXPORT SHEETS MODAL
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="exportModal" class="modal-overlay" onclick="if(event.target===this)closeExportModal()">
    <div class="modal-content" style="max-width:540px;">
        <div class="modal-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <div class="modal-header-icon">
                    <i class="fas fa-file-excel"></i>
                </div>
                <div>
                    <h4 style="color:#fff;">Export Field Visits & Follow-up Sheets</h4>
                    <span style="font-size:12px; color:rgba(255,255,255,0.85); display:block; margin-top:2px;">Download formatted spreadsheet (Excel & CSV compatible)</span>
                </div>
            </div>
            <button class="modal-close" onclick="closeExportModal()">&times;</button>
        </div>

        <form id="exportForm" onsubmit="executeExport(event)">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="font-size:12.5px; font-weight:700; color:#334155; margin-bottom:5px; display:block;">Select Report Type</label>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                        <label class="channel-chip active" style="justify-content:center;">
                            <input type="radio" name="export_type" value="visits" checked onchange="updateChannelChipUI(this)">
                            <i class="fas fa-table"></i> Master Visits & Pipeline
                        </label>
                        <label class="channel-chip" style="justify-content:center;">
                            <input type="radio" name="export_type" value="followup_history" onchange="updateChannelChipUI(this)">
                            <i class="fas fa-history"></i> Follow-ups Activity Log
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
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label style="font-size:12px; font-weight:600; color:#64748b; margin-bottom:4px; display:block;">From Date</label>
                        <input type="date" id="exportStartDate" class="form-control" style="font-size:12.5px; padding:6px 10px;">
                    </div>
                    <div class="form-group">
                        <label style="font-size:12px; font-weight:600; color:#64748b; margin-bottom:4px; display:block;">To Date</label>
                        <input type="date" id="exportEndDate" class="form-control" style="font-size:12.5px; padding:6px 10px;">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeExportModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" style="display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-download"></i> Download Spreadsheet
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* ── Universal Modal Framework (Scroll enabled with overflow-y: auto) ── */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
    box-sizing: border-box;
}
.modal-overlay.active {
    display: flex;
}
.modal-content {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 680px;
    max-height: 90vh !important;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
    overflow-y: auto !important;
    animation: modalSlideUp 0.25s ease-out;
    border: none;
    margin: auto;
}
@keyframes modalSlideUp {
    from { transform: translateY(15px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.modal-header {
    background: var(--primary, #024442);
    color: #fff;
    padding: 16px 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
    border-radius: 16px 16px 0 0;
}
.modal-header h4 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #fff;
}
.modal-header-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 8px;
    color: #fff;
    font-size: 14px;
}
.modal-close {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.75);
    font-size: 24px;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    transition: color 0.15s ease;
}
.modal-close:hover {
    color: #fff;
}
.modal-content form {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    flex: 1 1 auto;
    margin: 0;
    min-height: 0;
}
.modal-body {
    padding: 22px 24px;
    overflow-y: auto !important;
    flex: 1 1 auto;
    min-height: 0;
    -webkit-overflow-scrolling: touch;
}
.modal-footer {
    padding: 14px 22px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    background: #fbfcfc;
    flex-shrink: 0;
    border-radius: 0 0 16px 16px;
}

/* ── Clean Form Helpers ── */
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
    cursor: pointer;
    transition: all 0.15s ease;
}
.preset-tag:hover {
    background: #e2e8f0;
    color: #0f172a;
}

/* ── Interest Chips ── */
.interest-chip {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 10px;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    background: #fff;
    transition: all 0.15s ease;
}
.interest-chip input { display: none; }
.interest-chip.active-chip {
    border-color: var(--primary, #024442);
    background: #f0fdfa;
    color: var(--primary, #024442);
    font-weight: 700;
}

/* ── Category Tree Box ── */
.cat-box {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 12px;
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
    accent-color: var(--primary, #024442);
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
    accent-color: var(--primary, #024442);
}

/* ── Data Table Layout ── */
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

/* ── Direct Reach Out Buttons ── */
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
    background: var(--primary, #024442);
    border-color: var(--primary, #024442);
    color: #ffffff;
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
</style>

<script>
let currentFilter = 'all';
let currentSort   = 'urgency';
let searchQuery   = '';
let allVisits     = [];

document.addEventListener('DOMContentLoaded', () => {
    loadVisits();
    loadSalesRepsForExport();

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('log') === '1') {
        openVisitModal('add');
    }
});

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
    document.getElementById('statOverdue').textContent   = stats.overdue || 0;
    document.getElementById('statDueToday').textContent  = stats.due_today || 0;
    document.getElementById('statConverted').textContent = stats.converted || 0;
}

function setFilter(val) {
    currentFilter = val;
    loadVisits();
}

function setSort(val) {
    currentSort = val;
    renderTable();
}

function handleSearch(val) {
    searchQuery = val.trim().toLowerCase();
    renderTable();
}

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
        
        let priorityBadge = '';
        if (v.interest_level === 'hot') {
            priorityBadge = '<span class="badge-priority badge-hot"><span class="badge-dot badge-dot-hot"></span>High</span>';
        } else if (v.interest_level === 'warm') {
            priorityBadge = '<span class="badge-priority badge-warm"><span class="badge-dot badge-dot-warm"></span>Medium</span>';
        } else {
            priorityBadge = '<span class="badge-priority badge-cold"><span class="badge-dot badge-dot-cold"></span>Standard</span>';
        }

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
            scheduleBadge = `<span class="fo-schedule-badge fo-none">No follow-up set</span>`;
        }

        let outcomeHtml = '';
        if (v.last_outcome) {
            const outClean = v.last_outcome.toLowerCase().replace(/[^a-z]/g, '');
            const outLabel = escapeHtml(v.last_outcome.replace(/_/g, ' '));
            outcomeHtml = `<span class="outcome-pill outcome-pill-${outClean}" style="text-transform:capitalize;">${outLabel}</span>`;
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
                        <button class="dots" onclick="viewVisit(${v.id})" title="View Details"><i class="fas fa-eye"></i></button>
                        <button class="dots" onclick="openVisitModal('edit', ${v.id})" title="Edit"><i class="fas fa-pencil-alt"></i></button>
                        <?php if ($is_admin_user): ?>
                        <button class="dots" onclick="deleteVisit(${v.id}, '${escapeJsString(v.company_name)}')" title="Delete" style="color:#ef4444;"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        `;
    });

    container.innerHTML = html;
}

// ── Follow-Up Modal Logic ──
function openFollowupModal(visitId) {
    const v = allVisits.find(item => item.id == visitId);
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
            btn.innerHTML = '<i class="fas fa-check"></i> Save Follow-Up Record';

            if (data.success) {
                closeFollowupModal();
                loadVisits();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Save Follow-Up Record';
            alert('Failed to save follow-up.');
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
    
    document.getElementById('tModalAddBtn').onclick = () => {
        closeTimelineModal();
        openFollowupModal(visit.id);
    };

    const rawWa = (visit.whatsapp || visit.phone || '').trim();
    const cleanWa = formatWhatsAppNumber(rawWa);
    document.getElementById('tModalDirectWa').innerHTML = cleanWa ? `
        <a href="https://wa.me/${cleanWa}" target="_blank" class="btn-wa-direct">
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
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin-bottom:16px; text-align:center;">
                <p style="margin:0; font-size:13px; font-weight:600; color:#475569;">No follow-up touchpoints recorded yet.</p>
                <small style="color:#94a3b8;">Click "+ Log New Follow-Up" above to add the first interaction.</small>
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
    if (p) document.getElementById('f_whatsapp').value = p;
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
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save Visit Record';
            alert('Failed to save visit record.');
        });
}

function deleteVisit(id, company) {
    if (!confirm(`Are you sure you want to delete the visit record for "${company}"?`)) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fd.append('visit_id', id);
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
            <div style="font-size:18px; font-weight:800; color:#0f172a;">${escapeHtml(v.company_name)} ${v.is_urgent == 1 ? '<span class="badge-urgent-tag">URGENT</span>' : ''}</div>
            <div style="color:#64748b; font-size:13px; margin-top:2px;">Contact: <strong>${escapeHtml(v.client_name)}</strong> · Stage: <strong style="text-transform:uppercase;">${v.status}</strong></div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px; font-size:12.5px;">
            <div style="background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11px; font-weight:700;">PHONE</div>
                <div style="font-weight:700; color:#0f172a;">${escapeHtml(v.phone)}</div>
            </div>
            <div style="background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11px; font-weight:700;">WHATSAPP</div>
                <div style="font-weight:700; color:#16a34a;">${escapeHtml(v.whatsapp || v.phone)}</div>
            </div>
            <div style="background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11px; font-weight:700;">INTEREST LEVEL</div>
                <div style="font-weight:700; text-transform:uppercase;">${v.interest_level}</div>
            </div>
            <div style="background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #e2e8f0;">
                <div style="color:#64748b; font-size:11px; font-weight:700;">CONTACT WINDOW</div>
                <div style="font-weight:700; color:#0f172a;">${escapeHtml(v.contact_availability)}</div>
            </div>
        </div>
        <div style="margin-bottom:12px; font-size:13px;">
            <div style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase;">SERVICES REQUESTED</div>
            <div style="color:#0f172a; margin-top:2px; font-weight:600;">${escapeHtml(cats)}</div>
            ${subs !== 'None' ? `<div style="color:#64748b; font-size:12px; margin-top:3px;"><em>Sub-items:</em> ${escapeHtml(subs)}</div>` : ''}
        </div>
        <div style="margin-bottom:14px; font-size:13px;">
            <div style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase;">FIELD REMARKS</div>
            <div style="background:#f1f5f9; padding:10px; border-radius:8px; color:#334155; margin-top:4px; font-size:12.5px; line-height:1.4;">${escapeHtml(v.remarks || 'No remarks entered.')}</div>
        </div>

        ${v.admin_notes ? `
            <div style="margin-bottom:14px;">
                <div style="color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:4px;">COMMUNICATION TRAIL</div>
                <pre style="background:#fff; padding:10px; border-radius:6px; font-size:12px; color:#475569; white-space:pre-wrap; border:1px solid #e2e8f0; margin:0; font-family:inherit;">${escapeHtml(v.admin_notes)}</pre>
            </div>
        ` : ''}

        <div style="display:flex; justify-content:space-between; align-items:center; font-size:11.5px; color:#94a3b8; border-top:1px solid #f1f5f9; padding-top:10px;">
            <span>Logged by ${escapeHtml(v.sales_rep_name)} · ${v.time_ago || ''}</span>
            <button type="button" class="btn-track-action" onclick="closeViewModal(); openTimelineModal(${v.id});" style="font-size:11.5px;">
                <i class="fas fa-history"></i> View Full Timeline
            </button>
        </div>
    `;

    document.getElementById('viewModalActions').innerHTML = `
        <div style="display:flex; gap:8px;">
            <button type="button" class="btn-track-action btn-track-primary" onclick="closeViewModal(); openFollowupModal(${v.id});">
                <i class="fas fa-plus"></i> Log Follow-Up
            </button>
            ${cleanWa ? `
                <a href="https://wa.me/${cleanWa}?text=${waMsg}" target="_blank" class="btn-wa-direct">
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
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
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
