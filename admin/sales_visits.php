<?php
include 'dashboard_header.php';

// Auth: Sales reps, Admins, Managers, PMs can access
$allowed_roles = ['sales', 'super_admin', 'superadmin', 'admin', 'manager', 'pm'];
if (!in_array($role, $allowed_roles)) {
    header("Location: index.php?error=unauthorized");
    exit;
}

$user_id_int = intval($_SESSION['user_id'] ?? 0);
$is_admin_user = in_array($role, ['super_admin', 'superadmin', 'admin', 'manager', 'pm']);
?>

<div class="height-100">

    <!-- ── Page Header ── -->
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap" style="margin-bottom: 24px; gap: 14px;">
        <div>
            <h1 style="margin: 0; font-size: 24px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px;">
                <span style="display:inline-flex; align-items:center; justify-content:center; width:40px; height:40px; border-radius:10px; background:#d1fae5; color:#065f46;">
                    <i class="fas fa-street-view"></i>
                </span>
                Client Field Visits
            </h1>
            <p style="margin: 4px 0 0; color: #64748b; font-size: 14px;">
                Quickly record field visits, customer requirements, interest levels, and follow-up availability.
            </p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if ($is_admin_user): ?>
            <a href="sales_response_unit.php" class="btn btn-secondary-custom" style="padding: 10px 18px; font-size: 13.5px; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                <i class="fas fa-bolt" style="color: #f59e0b;"></i> Live Response Unit
            </a>
            <?php endif; ?>
            <button class="add-client" onclick="openVisitModal('add')" style="padding: 10px 20px; font-size: 14px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas fa-plus-circle"></i> Log Client Visit
            </button>
        </div>
    </div>

    <!-- ── Quick Statistics Cards ── -->
    <div class="stats-row" style="margin-bottom: 24px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
        <div class="stat-card" style="border-top: 4px solid var(--primary);">
            <div class="stat-icon"><i class="fas fa-clipboard-list" style="color: var(--primary);"></i></div>
            <div class="stat-number" id="statTotal">--</div>
            <div class="stat-label">Total Visits Logged</div>
        </div>
        <div class="stat-card" style="border-top: 4px solid #ef4444;">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i></div>
            <div class="stat-number" id="statUrgent" style="color: #ef4444;">--</div>
            <div class="stat-label">Urgent Follow-ups</div>
        </div>
        <div class="stat-card" style="border-top: 4px solid #f59e0b;">
            <div class="stat-icon"><i class="fas fa-fire" style="color: #f59e0b;"></i></div>
            <div class="stat-number" id="statHot" style="color: #f59e0b;">--</div>
            <div class="stat-label">Hot / High Interest</div>
        </div>
        <div class="stat-card" style="border-top: 4px solid #10b981;">
            <div class="stat-icon"><i class="fas fa-hourglass-half" style="color: #10b981;"></i></div>
            <div class="stat-number" id="statPending" style="color: #10b981;">--</div>
            <div class="stat-label">Awaiting Contact</div>
        </div>
    </div>

    <!-- ── Main Card: Search, Filters & Visits Table ── -->
    <div class="main-card" style="background:#fff; border-radius:14px; box-shadow:0 4px 20px rgba(0,0,0,0.04); border:1px solid #e2e8f0; overflow:hidden;">
        
        <!-- Filter Bar -->
        <div style="padding: 18px 24px; border-bottom: 1px solid #f1f5f9; display: flex; flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between;">
            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <button type="button" class="tab-pill active" onclick="setVisitFilter('all', this)">All</button>
                <button type="button" class="tab-pill" onclick="setVisitFilter('urgent', this)"><i class="fas fa-exclamation-circle" style="color:#ef4444;"></i> Urgent</button>
                <button type="button" class="tab-pill" onclick="setVisitFilter('hot', this)">🔥 Hot Leads</button>
                <button type="button" class="tab-pill" onclick="setVisitFilter('pending', this)">⏳ Pending Contact</button>
                <button type="button" class="tab-pill" onclick="setVisitFilter('contacted', this)">✅ Contacted</button>
            </div>

            <div style="display: flex; gap: 10px; align-items: center; flex-grow: 1; max-width: 420px; position: relative;">
                <input type="text" id="visitSearchInput" placeholder="Search by name, company, phone..." 
                       style="width: 100%; padding: 9px 14px 9px 36px; border: 1.5px solid #e2e8f0; border-radius: 8px; outline: none; font-size: 13.5px; font-family: inherit; background:#f8fafc;">
                <i class="fas fa-search" style="position: absolute; left: 12px; top: 11px; color: #94a3b8;"></i>
            </div>
        </div>

        <!-- Visits Feed Container -->
        <div id="visitsFeedContainer" style="padding: 16px 24px; min-height: 250px;">
            <div style="text-align: center; padding: 50px 20px; color: #94a3b8;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 12px; color: var(--primary);"></i>
                <p style="margin: 0;">Loading visits...</p>
            </div>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     MODAL: LOG CLIENT VISIT (Clean, Ultra-Fast UX)
     ══════════════════════════════════════════════════════════════════════════════ -->
<div id="visitModal" class="modal-overlay" onclick="if(event.target===this)closeVisitModal()">
    <div class="modal-content" style="max-width: 780px; width: 95%; max-height: 90vh; overflow-y: auto; border-radius: 16px; padding: 0;">
        
        <div class="modal-header" style="padding: 20px 26px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #fafafa; border-radius: 16px 16px 0 0;">
            <h4 style="margin: 0; font-size: 18px; font-weight: 800; display: flex; align-items: center; gap: 10px; color: #0f172a;">
                <span id="modalIconWrap" style="display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:8px; background:#d1fae5; color:#065f46;">
                    <i class="fas fa-user-plus"></i>
                </span>
                <span id="visitModalTitle">Log New Client Visit</span>
            </h4>
            <button class="modal-close" onclick="closeVisitModal()" style="background:none; border:none; font-size:18px; color:#94a3b8; cursor:pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="visitForm" onsubmit="submitVisitForm(event)">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="visit_id" id="f_visit_id" value="">

            <div class="modal-body" style="padding: 24px 26px; display: flex; flex-direction: column; gap: 20px;">
                
                <!-- Section 1: Client & Company Information -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px;">
                    <div style="font-weight: 700; font-size: 13.5px; color: #0f172a; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-id-card" style="color: var(--primary);"></i> Client & Company Details
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 14px;">
                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 5px;">Client / Contact Name <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="client_name" id="f_client_name" required placeholder="e.g. John Doe / Muhammad Ali"
                                   style="width: 100%; padding: 10px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; outline: none; background: #fff;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 5px;">Company / Business Name <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="company_name" id="f_company_name" required placeholder="e.g. Apex Global Traders"
                                   style="width: 100%; padding: 10px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; outline: none; background: #fff;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 5px;">Phone Number <span style="color:#ef4444;">*</span></label>
                            <input type="tel" name="phone" id="f_phone" required placeholder="e.g. 03001234567"
                                   style="width: 100%; padding: 10px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; outline: none; background: #fff;">
                        </div>
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                <label style="font-size: 12.5px; font-weight: 600; color: #334155; margin: 0;">WhatsApp Number</label>
                                <button type="button" onclick="copyPhoneToWhatsapp()" style="background: none; border: none; color: #059669; font-size: 11.5px; font-weight: 700; cursor: pointer; padding: 0;">
                                    <i class="fab fa-whatsapp"></i> Same as Phone
                                </button>
                            </div>
                            <input type="tel" name="whatsapp" id="f_whatsapp" placeholder="e.g. 03001234567"
                                   style="width: 100%; padding: 10px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; outline: none; background: #fff;">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Interest Level & Availability Timing -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px;">
                        
                        <!-- Interest Level Selector -->
                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">
                                <i class="fas fa-thermometer-half" style="color: #f59e0b;"></i> Client Interest Level <span style="color:#ef4444;">*</span>
                            </label>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">
                                <label class="interest-radio-card" id="card_hot">
                                    <input type="radio" name="interest_level" value="hot" required onchange="updateInterestUI('hot')">
                                    <div class="interest-inner">
                                        <span class="icon">🔥</span>
                                        <span class="label">Hot</span>
                                        <small>Immediate</small>
                                    </div>
                                </label>
                                <label class="interest-radio-card active" id="card_warm">
                                    <input type="radio" name="interest_level" value="warm" checked required onchange="updateInterestUI('warm')">
                                    <div class="interest-inner">
                                        <span class="icon">⚡</span>
                                        <span class="label">Warm</span>
                                        <small>Interested</small>
                                    </div>
                                </label>
                                <label class="interest-radio-card" id="card_cold">
                                    <input type="radio" name="interest_level" value="cold" required onchange="updateInterestUI('cold')">
                                    <div class="interest-inner">
                                        <span class="icon">❄️</span>
                                        <span class="label">Cold</span>
                                        <small>Exploring</small>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Urgency Checkbox -->
                        <div>
                            <label style="display: block; font-size: 12.5px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">
                                <i class="fas fa-bell" style="color: #ef4444;"></i> Urgent Flag
                            </label>
                            <div style="background: #fff; border: 1.5px solid #fecaca; border-radius: 10px; padding: 12px 14px; display: flex; align-items: center; gap: 12px; cursor: pointer;"
                                 onclick="document.getElementById('f_is_urgent').click();">
                                <input type="checkbox" name="is_urgent" id="f_is_urgent" value="1" style="width: 20px; height: 20px; accent-color: #ef4444; cursor: pointer;" onclick="event.stopPropagation();">
                                <div>
                                    <div style="font-weight: 700; font-size: 13px; color: #b91c1c;">🚨 Urgent Need to Contact</div>
                                    <div style="font-size: 11.5px; color: #64748b;">Alerts response team for immediate reach-out</div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Client Availability Timing -->
                    <div style="margin-top: 14px;">
                        <label style="display: block; font-size: 12.5px; font-weight: 700; color: #0f172a; margin-bottom: 6px;">
                            <i class="fas fa-clock" style="color: #0284c7;"></i> When is client available for contact? <span style="color:#ef4444;">*</span>
                        </label>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px;">
                            <button type="button" class="preset-chip" onclick="setAvailabilityChip('Available in 1-2 hours')">In 1-2 hours</button>
                            <button type="button" class="preset-chip" onclick="setAvailabilityChip('Evening today (5:00 PM - 8:00 PM)')">Today Evening</button>
                            <button type="button" class="preset-chip" onclick="setAvailabilityChip('Tomorrow morning (10:00 AM - 12:00 PM)')">Tomorrow Morning</button>
                            <button type="button" class="preset-chip" onclick="setAvailabilityChip('Tomorrow afternoon (2:00 PM - 5:00 PM)')">Tomorrow Afternoon</button>
                            <button type="button" class="preset-chip" onclick="setAvailabilityChip('After 2 days')">In 2 days</button>
                            <button type="button" class="preset-chip" onclick="setAvailabilityChip('Next week Monday')">Next Week</button>
                        </div>
                        <input type="text" name="contact_availability" id="f_contact_availability" required 
                               placeholder="e.g. Available today after 4 PM, or Tomorrow at 11:30 AM"
                               style="width: 100%; padding: 10px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; outline: none; background: #fff;">
                    </div>
                </div>

                <!-- Section 3: Requirements (Categories & Subcategories) -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px;">
                    <div style="font-weight: 700; font-size: 13.5px; color: #0f172a; margin-bottom: 6px; display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="fas fa-layer-group" style="color: var(--primary);"></i> Client Requirements / Services Needed</span>
                        <span style="font-size: 11px; font-weight: 600; color: #64748b;">(Select categories & optional sub-items)</span>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 12px;">
                        
                        <!-- 1. Website -->
                        <div class="category-block">
                            <label class="category-header">
                                <input type="checkbox" name="categories[]" value="Website" onchange="toggleSubcategories('sub_website', this.checked)">
                                <span class="cat-title"><i class="fas fa-globe" style="color:#0284c7;"></i> Website</span>
                            </label>
                            <div class="subcategories-wrap" id="sub_website" style="display: none;">
                                <label><input type="checkbox" name="subcategories[]" value="Website: E-commerce Store"> E-commerce Store</label>
                                <label><input type="checkbox" name="subcategories[]" value="Website: Corporate / Portfolio"> Corporate / Portfolio</label>
                                <label><input type="checkbox" name="subcategories[]" value="Website: Custom Web App"> Custom Web App</label>
                                <label><input type="checkbox" name="subcategories[]" value="Website: Redesign & Revamp"> Redesign & Revamp</label>
                                <label><input type="checkbox" name="subcategories[]" value="Website: Maintenance & Speed"> Maintenance</label>
                            </div>
                        </div>

                        <!-- 2. Printing -->
                        <div class="category-block">
                            <label class="category-header">
                                <input type="checkbox" name="categories[]" value="Printing" onchange="toggleSubcategories('sub_printing', this.checked)">
                                <span class="cat-title"><i class="fas fa-print" style="color:#e17055;"></i> Printing</span>
                            </label>
                            <div class="subcategories-wrap" id="sub_printing" style="display: none;">
                                <label><input type="checkbox" name="subcategories[]" value="Printing: Business Cards"> Business Cards</label>
                                <label><input type="checkbox" name="subcategories[]" value="Printing: Letterheads & Stationery"> Letterheads & Stationery</label>
                                <label><input type="checkbox" name="subcategories[]" value="Printing: Flyers & Brochures"> Flyers & Brochures</label>
                                <label><input type="checkbox" name="subcategories[]" value="Printing: Packaging Boxes"> Packaging & Boxes</label>
                                <label><input type="checkbox" name="subcategories[]" value="Printing: Banners & Signboards"> Banners & Signage</label>
                            </div>
                        </div>

                        <!-- 3. Design -->
                        <div class="category-block">
                            <label class="category-header">
                                <input type="checkbox" name="categories[]" value="Design" onchange="toggleSubcategories('sub_design', this.checked)">
                                <span class="cat-title"><i class="fas fa-paint-brush" style="color:#db2777;"></i> Design</span>
                            </label>
                            <div class="subcategories-wrap" id="sub_design" style="display: none;">
                                <label><input type="checkbox" name="subcategories[]" value="Design: Logo & Brand Identity"> Logo & Brand Identity</label>
                                <label><input type="checkbox" name="subcategories[]" value="Design: UI/UX App Design"> UI/UX Design</label>
                                <label><input type="checkbox" name="subcategories[]" value="Design: Social Media Graphics"> Social Media Graphics</label>
                                <label><input type="checkbox" name="subcategories[]" value="Design: Catalogs & Company Profile"> Catalogs / Profiles</label>
                                <label><input type="checkbox" name="subcategories[]" value="Design: Packaging Artworks"> Packaging Design</label>
                            </div>
                        </div>

                        <!-- 4. Social Media Marketing -->
                        <div class="category-block">
                            <label class="category-header">
                                <input type="checkbox" name="categories[]" value="Social Media Marketing" onchange="toggleSubcategories('sub_smm', this.checked)">
                                <span class="cat-title"><i class="fas fa-chart-line" style="color:#8b5cf6;"></i> Social Media Marketing (Paid Ads)</span>
                            </label>
                            <div class="subcategories-wrap" id="sub_smm" style="display: none;">
                                <label><input type="checkbox" name="subcategories[]" value="SMM: Meta Ads (FB & IG)"> Meta Ads (FB/IG)</label>
                                <label><input type="checkbox" name="subcategories[]" value="SMM: Google Ads PPC"> Google Ads (PPC)</label>
                                <label><input type="checkbox" name="subcategories[]" value="SMM: TikTok Ads"> TikTok Ads</label>
                                <label><input type="checkbox" name="subcategories[]" value="SMM: Lead Generation Campaigns"> Lead Generation</label>
                                <label><input type="checkbox" name="subcategories[]" value="SMM: Influencer Marketing"> Influencer Outreach</label>
                            </div>
                        </div>

                        <!-- 5. Managing Social Media -->
                        <div class="category-block">
                            <label class="category-header">
                                <input type="checkbox" name="categories[]" value="Managing Social Media" onchange="toggleSubcategories('sub_managing_smm', this.checked)">
                                <span class="cat-title"><i class="fas fa-hashtag" style="color:#10b981;"></i> Managing Social Media (Organic Management)</span>
                            </label>
                            <div class="subcategories-wrap" id="sub_managing_smm" style="display: none;">
                                <label><input type="checkbox" name="subcategories[]" value="Management: Monthly Content Creation"> Content Creation & Design</label>
                                <label><input type="checkbox" name="subcategories[]" value="Management: Daily Scheduling"> Daily Scheduling</label>
                                <label><input type="checkbox" name="subcategories[]" value="Management: Community & DM Handling"> Community & Inbox Handling</label>
                                <label><input type="checkbox" name="subcategories[]" value="Management: Reels & Video Editing"> Reels & Video Editing</label>
                                <label><input type="checkbox" name="subcategories[]" value="Management: Monthly Growth Strategy"> Monthly Analytics & Growth</label>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Section 4: Remarks & Quick Selectors -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px;">
                    <div style="font-weight: 700; font-size: 13.5px; color: #0f172a; margin-bottom: 8px;">
                        <i class="fas fa-comment-dots" style="color: var(--primary);"></i> Remarks & Quick Presets
                    </div>
                    
                    <!-- Quick Preset Buttons -->
                    <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px;">
                        <button type="button" class="preset-chip remark-chip" onclick="appendQuickRemark('Contact owner directly')">👤 Contact owner directly</button>
                        <button type="button" class="preset-chip remark-chip" onclick="appendQuickRemark('Interested but budget issues')">💰 Interested but budget issues</button>
                        <button type="button" class="preset-chip remark-chip" onclick="appendQuickRemark('Not interested at this time')">❌ Not interested</button>
                        <button type="button" class="preset-chip remark-chip" onclick="appendQuickRemark('Ready to close / High intent')">🎯 Ready to close</button>
                        <button type="button" class="preset-chip remark-chip" onclick="appendQuickRemark('Requested formal quotation / proposal')">📄 Wants Quotation</button>
                        <button type="button" class="preset-chip remark-chip" onclick="appendQuickRemark('Has existing agency / comparing rates')">🔄 Comparing rates</button>
                    </div>

                    <input type="hidden" name="quick_remark" id="f_quick_remark" value="">

                    <textarea name="remarks" id="f_remarks" rows="3" placeholder="Enter detailed visit notes, specific requirements discussed, objections, or instructions..."
                              style="width: 100%; padding: 10px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; outline: none; background: #fff; font-family: inherit; resize: vertical;"></textarea>
                </div>

            </div>

            <div class="modal-footer" style="padding: 16px 26px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px; background: #fafafa; border-radius: 0 0 16px 16px;">
                <button type="button" class="btn btn-secondary-custom" onclick="closeVisitModal()" style="padding: 10px 18px; font-size: 13.5px;">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="submitVisitBtn" style="padding: 10px 24px; font-size: 13.5px; font-weight: 700; background: var(--primary); color: #fff; border: none; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-save"></i> Save Visit Record
                </button>
            </div>
        </form>

    </div>
</div>

<style>
/* ── Visits Custom Styles ── */
.tab-pill {
    padding: 7px 16px;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}
.tab-pill:hover, .tab-pill.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
}

.interest-radio-card {
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    padding: 10px 6px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    display: block;
    user-select: none;
}
.interest-radio-card input {
    display: none;
}
.interest-radio-card .interest-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}
.interest-radio-card .icon { font-size: 18px; }
.interest-radio-card .label { font-weight: 700; font-size: 12.5px; color: #1e293b; }
.interest-radio-card small { font-size: 10.5px; color: #64748b; }

.interest-radio-card.active-hot { border-color: #ef4444; background: #fff5f5; }
.interest-radio-card.active-warm { border-color: #f59e0b; background: #fffbeb; }
.interest-radio-card.active-cold { border-color: #0284c7; background: #f0f9ff; }

.preset-chip {
    padding: 5px 11px;
    border-radius: 15px;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}
.preset-chip:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: #0f172a;
}
.remark-chip:hover {
    background: #e0f2fe;
    border-color: #38bdf8;
    color: #0369a1;
}

.category-block {
    background: #fff;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 14px;
    transition: border-color 0.2s;
}
.category-block:hover { border-color: #cbd5e1; }
.category-header {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    margin: 0;
    font-size: 13.5px;
    font-weight: 700;
    color: #1e293b;
}
.category-header input[type="checkbox"] {
    width: 17px;
    height: 17px;
    accent-color: var(--primary);
    cursor: pointer;
}
.subcategories-wrap {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #e2e8f0;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.subcategories-wrap label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 4px 10px;
    border-radius: 6px;
    cursor: pointer;
}
.subcategories-wrap label:hover { background: #f1f5f9; }
.subcategories-wrap input { accent-color: var(--primary); }

.visit-row-card {
    border: 1px solid #f1f5f9;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 12px;
    background: #fff;
    transition: all 0.2s ease;
    display: grid;
    grid-template-columns: 2fr 1.8fr 1.2fr 1fr 1.2fr;
    gap: 14px;
    align-items: center;
}
.visit-row-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    border-color: #cbd5e1;
}
.visit-row-card.is-urgent {
    border-left: 5px solid #ef4444;
    background: #fffafb;
}

@media (max-width: 991px) {
    .visit-row-card {
        grid-template-columns: 1fr;
        gap: 10px;
    }
}
</style>

<script>
let currentFilter = 'all';
let visitSearchTimer = null;

document.addEventListener('DOMContentLoaded', function() {
    loadVisits();

    document.getElementById('visitSearchInput').addEventListener('input', function() {
        clearTimeout(visitSearchTimer);
        visitSearchTimer = setTimeout(loadVisits, 300);
    });

    // Check if URL has ?log=1 to auto-open modal
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('log') === '1') {
        openVisitModal('add');
    }
});

function setVisitFilter(filter, btn) {
    currentFilter = filter;
    document.querySelectorAll('.tab-pill').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadVisits();
}

function loadVisits() {
    const search = document.getElementById('visitSearchInput').value.trim();
    const url = `sales_visits_api.php?action=fetch_all&filter=${encodeURIComponent(currentFilter)}&search=${encodeURIComponent(search)}`;

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('visitsFeedContainer').innerHTML = `<div style="text-align:center;padding:40px;color:#ef4444;"><i class="fas fa-exclamation-triangle"></i> Failed to load visits: ${data.message}</div>`;
                return;
            }

            // Update stats
            if (data.stats) {
                document.getElementById('statTotal').textContent = data.stats.total || 0;
                document.getElementById('statUrgent').textContent = data.stats.urgent || 0;
                document.getElementById('statHot').textContent = data.stats.hot || 0;
                document.getElementById('statPending').textContent = data.stats.pending || 0;
            }

            renderVisitsList(data.items);
        })
        .catch(err => {
            console.error(err);
            document.getElementById('visitsFeedContainer').innerHTML = `<div style="text-align:center;padding:40px;color:#ef4444;">Error connecting to server.</div>`;
        });
}

function renderVisitsList(items) {
    const container = document.getElementById('visitsFeedContainer');
    if (!items || items.length === 0) {
        container.innerHTML = `
            <div style="text-align:center; padding:60px 20px; color:#94a3b8;">
                <i class="fas fa-calendar-times" style="font-size:3rem; margin-bottom:14px; color:#cbd5e1;"></i>
                <h5 style="margin:0 0 6px 0; color:#475569; font-weight:700;">No Client Visits Found</h5>
                <p style="margin:0 0 16px; font-size:13.5px;">Click the button below to log your first field visit.</p>
                <button class="add-client" onclick="openVisitModal('add')"><i class="fas fa-plus"></i> Log Client Visit</button>
            </div>
        `;
        return;
    }

    let html = '';
    items.forEach(v => {
        const isUrgent = v.is_urgent == 1;
        const interestClass = v.interest_level === 'hot' ? 'badge-hot' : (v.interest_level === 'warm' ? 'badge-warm' : 'badge-cold');
        const interestLabel = v.interest_level === 'hot' ? '🔥 Hot' : (v.interest_level === 'warm' ? '⚡ Warm' : '❄️ Cold');
        const interestColor = v.interest_level === 'hot' ? '#dc2626' : (v.interest_level === 'warm' ? '#d97706' : '#0284c7');
        const interestBg    = v.interest_level === 'hot' ? '#fee2e2' : (v.interest_level === 'warm' ? '#fef3c7' : '#e0f2fe');

        // Categories pills
        let catHtml = '';
        if (v.categories_arr && v.categories_arr.length > 0) {
            catHtml = v.categories_arr.map(c => `<span style="display:inline-block; background:#f1f5f9; color:#334155; font-size:11px; font-weight:600; padding:2px 8px; border-radius:6px; margin:2px 3px 2px 0;">${escapeHtml(c)}</span>`).join('');
        } else {
            catHtml = '<span style="color:#aaa;font-size:11.5px;">General</span>';
        }

        // WhatsApp clean number
        const cleanWa = (v.whatsapp || v.phone || '').replace(/[^0-9]/g, '');
        const waText = encodeURIComponent(`Hi ${v.client_name}, thank you for meeting our representative from Graphicafix today regarding your interest in ${v.categories_arr.join(', ')}. We are following up as requested. How can we assist you?`);
        const waLink = `https://wa.me/${cleanWa}?text=${waText}`;

        html += `
        <div class="visit-row-card ${isUrgent ? 'is-urgent' : ''}">
            <!-- Col 1: Client & Company -->
            <div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <strong style="font-size:14.5px; color:#0f172a;">${escapeHtml(v.client_name)}</strong>
                    ${isUrgent ? '<span style="background:#fee2e2; color:#dc2626; font-size:10px; font-weight:800; padding:2px 6px; border-radius:4px; text-transform:uppercase; letter-spacing:0.5px;"><i class="fas fa-exclamation-circle"></i> URGENT</span>' : ''}
                </div>
                <div style="font-size:13px; color:#475569; font-weight:600; margin-top:2px;">
                    <i class="fas fa-building" style="color:#94a3b8; font-size:11px; margin-right:4px;"></i>${escapeHtml(v.company_name)}
                </div>
                <div style="font-size:12px; color:#64748b; margin-top:4px;">
                    <i class="fas fa-user-circle" style="color:#94a3b8; font-size:11px; margin-right:3px;"></i> Rep: <strong>${escapeHtml(v.sales_rep_name)}</strong> · ${v.time_ago}
                </div>
            </div>

            <!-- Col 2: Requirements & Remarks -->
            <div>
                <div style="margin-bottom:5px;">${catHtml}</div>
                ${v.quick_remark ? `<div style="display:inline-block; background:#ede9fe; color:#6d28d9; font-size:11px; font-weight:700; padding:2px 8px; border-radius:12px; margin-bottom:4px;">${escapeHtml(v.quick_remark)}</div>` : ''}
                ${v.remarks ? `<div style="font-size:12px; color:#64748b; line-height:1.4; max-height:40px; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(v.remarks)}</div>` : ''}
            </div>

            <!-- Col 3: Interest & Availability -->
            <div>
                <div>
                    <span style="background:${interestBg}; color:${interestColor}; font-size:11.5px; font-weight:700; padding:3px 10px; border-radius:12px; display:inline-block;">
                        ${interestLabel}
                    </span>
                </div>
                <div style="margin-top:6px; font-size:12px; color:#0284c7; font-weight:600;">
                    <i class="fas fa-clock" style="font-size:11px; margin-right:4px;"></i>${escapeHtml(v.contact_availability)}
                </div>
            </div>

            <!-- Col 4: Status -->
            <div>
                <span style="background:#f1f5f9; color:#475569; font-size:11px; font-weight:700; padding:4px 8px; border-radius:6px; text-transform:uppercase;">
                    ${escapeHtml(v.status.replace('_', ' '))}
                </span>
                ${v.converted_lead_id ? `<div style="margin-top:4px;"><a href="manage_leads.php" style="font-size:11px; color:#059669; font-weight:700;"><i class="fas fa-check"></i> CRM Lead #${v.converted_lead_id}</a></div>` : ''}
            </div>

            <!-- Col 5: Actions -->
            <div style="display:flex; gap:6px; justify-content:flex-end; align-items:center;">
                <a href="${waLink}" target="_blank" class="btn" style="background:#25d366; color:#fff; padding:6px 10px; border-radius:6px; font-size:12px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:4px;" title="WhatsApp Reachout">
                    <i class="fab fa-whatsapp"></i> Chat
                </a>
                <a href="tel:${v.phone}" class="btn" style="background:#f1f5f9; color:#0f172a; padding:6px 9px; border-radius:6px; font-size:12px;" title="Call Client">
                    <i class="fas fa-phone"></i>
                </a>
                <button class="btn" onclick="openVisitModal('edit', ${v.id})" style="background:#f1f5f9; color:#024442; padding:6px 9px; border-radius:6px; font-size:12px; border:none; cursor:pointer;" title="Edit Visit">
                    <i class="fas fa-pencil-alt"></i>
                </button>
            </div>
        </div>
        `;
    });

    container.innerHTML = html;
}

function openVisitModal(mode, id = null) {
    document.getElementById('visitForm').reset();
    document.getElementById('f_visit_id').value = '';
    document.querySelectorAll('.subcategories-wrap').forEach(el => el.style.display = 'none');
    updateInterestUI('warm');
    document.getElementById('card_warm').querySelector('input').checked = true;

    if (mode === 'add') {
        document.getElementById('visitModalTitle').textContent = 'Log New Client Visit';
        document.getElementById('modalIconWrap').innerHTML = '<i class="fas fa-user-plus"></i>';
        document.getElementById('visitModal').classList.add('show');
        document.getElementById('f_client_name').focus();
    } else if (mode === 'edit' && id) {
        document.getElementById('visitModalTitle').textContent = 'Edit Client Visit Record';
        document.getElementById('modalIconWrap').innerHTML = '<i class="fas fa-pencil-alt"></i>';
        
        fetch(`sales_visits_api.php?action=fetch_all&filter=all`)
            .then(res => res.json())
            .then(data => {
                const v = data.items.find(item => item.id == id);
                if (!v) return;

                document.getElementById('f_visit_id').value = v.id;
                document.getElementById('f_client_name').value = v.client_name;
                document.getElementById('f_company_name').value = v.company_name;
                document.getElementById('f_phone').value = v.phone;
                document.getElementById('f_whatsapp').value = v.whatsapp || v.phone;
                document.getElementById('f_contact_availability').value = v.contact_availability;
                document.getElementById('f_remarks').value = v.remarks || '';
                document.getElementById('f_quick_remark').value = v.quick_remark || '';
                document.getElementById('f_is_urgent').checked = (v.is_urgent == 1);

                // Interest
                const radio = document.querySelector(`input[name="interest_level"][value="${v.interest_level}"]`);
                if (radio) { radio.checked = true; updateInterestUI(v.interest_level); }

                // Check categories
                if (v.categories_arr) {
                    v.categories_arr.forEach(cat => {
                        const cb = document.querySelector(`input[name="categories[]"][value="${cat}"]`);
                        if (cb) {
                            cb.checked = true;
                            const map = {
                                'Website': 'sub_website',
                                'Printing': 'sub_printing',
                                'Design': 'sub_design',
                                'Social Media Marketing': 'sub_smm',
                                'Managing Social Media': 'sub_managing_smm'
                            };
                            if (map[cat]) document.getElementById(map[cat]).style.display = 'flex';
                        }
                    });
                }

                // Check subcategories
                if (v.subcategories_arr) {
                    v.subcategories_arr.forEach(sub => {
                        const scb = document.querySelector(`input[name="subcategories[]"][value="${sub}"]`);
                        if (scb) scb.checked = true;
                    });
                }

                document.getElementById('visitModal').classList.add('show');
            });
    }
}

function closeVisitModal() {
    document.getElementById('visitModal').classList.remove('show');
}

function updateInterestUI(level) {
    document.getElementById('card_hot').classList.remove('active-hot', 'active-warm', 'active-cold');
    document.getElementById('card_warm').classList.remove('active-hot', 'active-warm', 'active-cold');
    document.getElementById('card_cold').classList.remove('active-hot', 'active-warm', 'active-cold');

    if (level === 'hot') document.getElementById('card_hot').classList.add('active-hot');
    else if (level === 'warm') document.getElementById('card_warm').classList.add('active-warm');
    else if (level === 'cold') document.getElementById('card_cold').classList.add('active-cold');
}

function copyPhoneToWhatsapp() {
    const p = document.getElementById('f_phone').value.trim();
    if (p) document.getElementById('f_whatsapp').value = p;
}

function setAvailabilityChip(txt) {
    document.getElementById('f_contact_availability').value = txt;
}

function toggleSubcategories(id, isChecked) {
    const el = document.getElementById(id);
    if (el) el.style.display = isChecked ? 'flex' : 'none';
}

function appendQuickRemark(preset) {
    document.getElementById('f_quick_remark').value = preset;
    const txt = document.getElementById('f_remarks');
    if (!txt.value.includes(preset)) {
        txt.value = txt.value ? (txt.value + ' | ' + preset) : preset;
    }
}

function submitVisitForm(e) {
    e.preventDefault();
    const btn = document.getElementById('submitVisitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const formData = new FormData(document.getElementById('visitForm'));

    fetch('sales_visits_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Visit Record';

        if (data.success) {
            closeVisitModal();
            loadVisits();
            alert('Client visit logged successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Visit Record';
        alert('Server error while saving visit.');
    });
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
</script>

<?php include 'dashboard_footer.php'; ?>
