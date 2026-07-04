<?php

if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}
?>
<style>
/* Enhanced Project Request Modal */
.modal { background: rgba(2,68,66,.5); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
.modal.show { animation: fadeIn .3s ease; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
.modal-dialog { animation: slideUp .4s cubic-bezier(.4,0,.2,1); }
@keyframes slideUp { from { transform:translateY(50px); opacity:0; } to { transform:translateY(0); opacity:1; } }
.modal-content { border-radius:24px; background:white; border:none; overflow:hidden; box-shadow:0 20px 60px rgba(2,68,66,.2); }
.modal-header {
    background: linear-gradient(135deg,#024442 0%,#035b58 100%);
    color:white; border-bottom:none; padding:2rem; position:relative; overflow:hidden;
}
.modal-header::before { content:''; position:absolute; top:0; right:0; width:200px; height:200px; background:radial-gradient(circle,rgba(182,247,99,.2) 0%,transparent 70%); border-radius:50%; }
.modal-header-content { display:flex; align-items:center; gap:1.25rem; position:relative; z-index:1; }
.modal-icon { width:56px; height:56px; background:rgba(182,247,99,.2); backdrop-filter:blur(10px); border-radius:16px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; color:#B6F763; flex-shrink:0; }
.modal-title  { font-size:1.75rem; font-weight:700; margin:0; color:white; }
.modal-subtitle { font-size:.9375rem; color:rgba(255,255,255,.8); margin:.25rem 0 0; transition: all .3s; }
.modal-close { background:rgba(255,255,255,.1); backdrop-filter:blur(10px); border:none; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-size:1.25rem; cursor:pointer; transition:all .3s; position:relative; z-index:1; }
.modal-close:hover { background:rgba(255,255,255,.2); transform:rotate(90deg); }

.modal-body { padding:2rem; max-height:70vh; overflow-y:auto; }
.modal-body::-webkit-scrollbar { width:8px; }
.modal-body::-webkit-scrollbar-track { background:#f1f5f9; border-radius:10px; }
.modal-body::-webkit-scrollbar-thumb { background:#024442; border-radius:10px; }

.form-section { margin-bottom:2rem; }
.form-section:last-of-type { margin-bottom:1rem; }
.section-title { display:flex; align-items:center; gap:.75rem; font-size:1.125rem; font-weight:700; color:#024442; margin-bottom:1.5rem; padding-bottom:.75rem; border-bottom:2px solid rgba(2,68,66,.1); }
.section-title i { font-size:1.25rem; color:#B6F763; }

.form-label  { font-size:.875rem; font-weight:600; color:#1e293b; margin-bottom:.5rem; display:block; }
.required    { color:#ef4444; margin-left:2px; }
.input-wrapper { position:relative; display:flex; align-items:center; }
.input-icon  { position:absolute; left:1rem; color:#64748b; font-size:1rem; z-index:1; pointer-events:none; }

.pop-up-form-control, .form-select {
    width:100%; padding:.875rem 1rem .875rem 3rem; border:2px solid #e2e8f0;
    border-radius:12px; font-size:.9375rem; transition:all .3s; background:#f8fafc;
}
.pop-up-form-control:focus, .form-select:focus {
    outline:none; border-color:#024442; background:white; box-shadow:0 0 0 4px rgba(2,68,66,.08);
}
.pop-up-form-control::placeholder { color:#94a3b8; }
textarea.pop-up-form-control { resize:vertical; min-height:120px; }

/* Discount code input — highlighted */
.discount-input-wrap {
    background: rgba(184,243,90,.06);
    border: 1.5px dashed rgba(2,68,66,.2);
    border-radius: 12px;
    padding: 12px 14px;
    display: flex; align-items: center; gap: 10px;
}
.discount-input-wrap i { color: var(--primary, #024442); font-size: 1rem; flex-shrink: 0; }
.discount-input-wrap input {
    background: none; border: none; outline: none;
    font-size: .95rem; font-weight: 700; color: #024442;
    letter-spacing: 1.5px; width: 100%; font-family: 'Poppins', Helvetica, sans-serif;
}
.discount-input-wrap input::placeholder { font-weight: 400; letter-spacing: 0; color: #aaa; font-size: .85rem; }
.discount-hint { font-size:.75rem; color:#94a3b8; margin-top:5px; }

.file-upload-wrapper { position:relative; }
.file-input { position:absolute; opacity:0; width:0; height:0; }
.file-upload-label { display:flex; align-items:center; gap:.75rem; padding:.875rem 1.5rem; background:#f8fafc; border:2px dashed #cbd5e1; border-radius:12px; cursor:pointer; transition:all .3s; font-size:.9375rem; color:#64748b; font-weight:500; }
.file-upload-label:hover { background:white; border-color:#024442; color:#024442; }

.form-actions { display:flex; gap:1rem; justify-content:flex-end; padding-top:1.5rem; border-top:1px solid #e2e8f0; margin-top:1rem; }
.form-actions .btn { padding:.875rem 2rem; border-radius:50px; font-weight:600; font-size:.9375rem; display:inline-flex; align-items:center; gap:.625rem; transition:all .3s; border:none; cursor:pointer; }
.btn-secondary { background:#f1f5f9; color:#64748b; }
.btn-secondary:hover { background:#e2e8f0; color:#475569; }
.btn-primary { background:linear-gradient(135deg,#024442 0%,#035b58 100%); color:white; box-shadow:0 4px 16px rgba(2,68,66,.3); }
.btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(2,68,66,.4); }
.btn-primary:disabled { opacity:.6; cursor:not-allowed; }

@media (max-width:768px) {
    .modal-dialog { margin:.5rem; }
    .modal-header { padding:1.5rem; }
    .modal-body   { padding:1.5rem; max-height:65vh; }
    .modal-title  { font-size:1.4rem; }
    .form-actions { flex-direction:column-reverse; }
    .form-actions .btn { width:100%; justify-content:center; }
}
</style>

<script src="<?= BASE_URL ?>assets/js/scripts.js"></script>

<!-- Project Request Modal -->
<div class="modal fade" id="projectRequestModal" tabindex="-1" aria-labelledby="projectRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-content">
                    <div class="modal-icon"><i class="fa fa-rocket"></i></div>
                    <div>
                        <h5 class="modal-title" id="projectRequestModalLabel">Request a Project</h5>
                        <p class="modal-subtitle">Let's bring your vision to life</p>
                    </div>
                </div>
                <button type="button" class="modal-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="projectRequestForm" action="/project_request_submission" method="POST" enctype="multipart/form-data" onsubmit="return validateForm(this)">

                    <!-- ── Spam protection fields ── -->

                    <!-- CSRF token (set by PHP via setModalCsrf() called when modal opens) -->
                    <input type="hidden" name="_csrf_token" value="<?= $_SESSION['_csrf_token']; ?>">

                    <!-- Timing field: JS sets this to current timestamp when modal opens -->
                    <input type="hidden" name="_form_time" id="formTime" value="">

                    <!-- Honeypot: hidden from humans via CSS, bots fill it -->
                    <div style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true" tabindex="-1">
                        <label for="_hp_website">Leave this field empty</label>
                        <input type="text" name="_hp_website" id="_hp_website" value="" autocomplete="off" tabindex="-1" style="display:none;">
                    </div>

                    <!-- Hidden: auto-filled from package selection -->
                    <input type="hidden" id="modal_service_type"  name="modal_service_type"  value="">
                    <input type="hidden" id="modal_package_name"  name="modal_package_name"  value="">
                    <input type="hidden" id="modal_package_price" name="modal_package_price" value="">

                    <!-- Personal Information -->
                    <div class="form-section">
                        <div class="section-title"><i class="fa fa-user"></i><span>Personal Information</span></div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="input-icon fa fa-user"></i>
                                    <input type="text" class="pop-up-form-control" name="name" placeholder="Enter your full name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Company Name</label>
                                <div class="input-wrapper">
                                    <i class="input-icon fa fa-building"></i>
                                    <input type="text" class="pop-up-form-control" name="company" placeholder="Your company name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="input-icon fa fa-envelope"></i>
                                    <input type="email" class="pop-up-form-control" name="email" placeholder="your@email.com" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="input-icon fa fa-phone"></i>
                                    <input type="tel" class="pop-up-form-control" name="phone" placeholder="+92 300 1234567" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Project Details -->
                    <div class="form-section">
                        <div class="section-title"><i class="fa fa-briefcase"></i><span>Project Details</span></div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Project Type <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="input-icon fa fa-layer-group"></i>
                                    <select class="form-select" id="projectType" name="projectType" required>
                                        <option value="" selected disabled>Select project type</option>
                                        <option value="Website Design">Website Design</option>
                                        <option value="Mobile App">Mobile App</option>
                                        <option value="Graphic Design">Graphic Design</option>
                                        <option value="Branding">Branding &amp; Identity</option>
                                        <option value="Marketing Campaign">Marketing Campaign</option>
                                        <option value="Content Writing">Content Writing</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Estimated Budget (PKR)</label>
                                <div class="input-wrapper">
                                    <i class="input-icon fa fa-money-bill"></i>
                                    <input type="number" class="pop-up-form-control" name="budget" placeholder="e.g., 50000">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Estimated Timeframe <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="input-icon fa fa-clock"></i>
                                    <input type="text" class="pop-up-form-control" name="time" placeholder="e.g., 2 weeks, 1 month" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Attach File</label>
                                <div class="file-upload-wrapper">
                                    <input type="file" class="file-input" id="attachment" name="attachment">
                                    <label for="attachment" class="file-upload-label">
                                        <i class="fa fa-paperclip"></i>
                                        <span class="file-text">Choose file</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Project Description <span class="required">*</span></label>
                                <div class="input-wrapper">
                                    <i class="input-icon fa fa-align-left"></i>
                                    <textarea class="pop-up-form-control" name="description" rows="4" placeholder="Tell us about your project, goals, and any specific requirements..." required></textarea>
                                </div>
                            </div>

                            <!-- Discount Code -->
                            <div class="col-12">
                                <label class="form-label">Discount Code <span style="color:#bbb;font-weight:400;">(optional)</span></label>
                                <div class="discount-input-wrap">
                                    <i class="fas fa-tag"></i>
                                    <input type="text" name="discount_code" id="discountCode" placeholder="Paste your discount code here" autocomplete="off">
                                </div>
                                <div class="discount-hint"><i class="fas fa-lightbulb" style="color: var(--accent);"></i> Have a discount code from our offers section? Paste it here to apply your savings.</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fa fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-paper-plane"></i> Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// ── CSRF token ────────────────────────────────────────────────────────────────

function initFormProtection() {
    var timeField = document.getElementById('formTime');
    if (timeField) { timeField.value = Math.floor(Date.now() / 1000); }
    var hp = document.getElementById('_hp_website');
    if (hp) { hp.value = ''; }
}

function validateForm(form) {
    var prev = document.getElementById('formErrorMsg');
    if (prev) { prev.remove(); }

    var name  = (form.querySelector('[name="name"]')        || {}).value || '';
    var email = (form.querySelector('[name="email"]')       || {}).value || '';
    var phone = (form.querySelector('[name="phone"]')       || {}).value || '';
    var desc  = (form.querySelector('[name="description"]') || {}).value || '';
    var csrf  = (form.querySelector('[name="_csrf_token"]') || {}).value || '';

    name = name.trim(); email = email.trim(); phone = phone.trim(); desc = desc.trim();

    if (!name || !email || !phone || !desc) {
        showFormError('Please fill in all required fields.'); return false;
    }
    var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRe.test(email)) {
        showFormError('Please enter a valid email address.'); return false;
    }
    if (desc.length < 20) {
        showFormError('Please describe your project in at least 20 characters.'); return false;
    }
    if (!csrf) {
        fetchCsrfToken(1);
        showFormError('Security check still loading — please try submitting again in a moment.'); return false;
    }
    return true;
}

function showFormError(msg) {
    var err = document.createElement('div');
    err.id = 'formErrorMsg';
    err.style.cssText = 'background:#fef2f2;border:1px solid #fecaca;color:#dc2626;border-radius:10px;padding:10px 14px;font-size:.82rem;font-weight:500;margin-bottom:14px;display:flex;align-items:center;gap:8px;';
    err.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + msg;
    var form    = document.getElementById('projectRequestForm');
    var actions = form && form.querySelector('.form-actions');
    if (actions) { form.insertBefore(err, actions); }
    setTimeout(function(){ if(err.parentNode){ err.remove(); } }, 6000);
}

document.addEventListener('DOMContentLoaded', function() {
    var prModal = document.getElementById('projectRequestModal');
    if (prModal) {
        prModal.addEventListener('show.bs.modal', initFormProtection);
    }
    var _orig = window.setModalService;
    window.setModalService = function() {
        if (_orig) { _orig.apply(this, arguments); }
        initFormProtection();
    };
});

document.addEventListener("DOMContentLoaded", function() {
    const params = new URLSearchParams(window.location.search);

    if (params.get("success") === "1") {
        alert("Your request has been submitted successfully!");

        // Optional: auto-open modal again (if you want)
        // var modal = new bootstrap.Modal(document.getElementById('projectRequestModal'));
        // modal.show();

        // Clean URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
</script>