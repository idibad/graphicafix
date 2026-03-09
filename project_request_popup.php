<style>
    /* ===========================
   Enhanced Project Request Modal
=========================== */

/* Modal Backdrop */
.modal {
    background: rgba(2, 68, 66, 0.5);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

.modal.show {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Modal Dialog */
.modal-dialog {
    animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes slideUp {
    from {
        transform: translateY(50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Modal Content */
.modal-content {
    border-radius: 24px;
    background: white;
    border: none;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(2, 68, 66, 0.2);
}

/* Modal Header */
.modal-header {
    background: linear-gradient(135deg, #024442 0%, #035b58 100%);
    color: white;
    border-bottom: none;
    padding: 2rem;
    position: relative;
    overflow: hidden;
}

.modal-header::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(182, 247, 99, 0.2) 0%, transparent 70%);
    border-radius: 50%;
}

.modal-header-content {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    position: relative;
    z-index: 1;
}

.modal-icon {
    width: 56px;
    height: 56px;
    background: rgba(182, 247, 99, 0.2);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #B6F763;
    flex-shrink: 0;
}

.modal-title {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0;
    color: white;
}

.modal-subtitle {
    font-size: 0.9375rem;
    color: rgba(255, 255, 255, 0.8);
    margin: 0.25rem 0 0;
}

.modal-close {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    z-index: 1;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: rotate(90deg);
}

/* Modal Body */
.modal-body {
    padding: 2rem;
    max-height: 70vh;
    overflow-y: auto;
}

/* Custom Scrollbar */
.modal-body::-webkit-scrollbar {
    width: 8px;
}

.modal-body::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}

.modal-body::-webkit-scrollbar-thumb {
    background: #024442;
    border-radius: 10px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
    background: #035b58;
}

/* Form Sections */
.form-section {
    margin-bottom: 2rem;
}

.form-section:last-of-type {
    margin-bottom: 1rem;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.125rem;
    font-weight: 700;
    color: #024442;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid rgba(2, 68, 66, 0.1);
}

.section-title i {
    font-size: 1.25rem;
    color: #B6F763;
}

/* Form Groups */
.form-group {
    margin-bottom: 0;
}

.form-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.5rem;
    display: block;
}

.required {
    color: #ef4444;
    margin-left: 2px;
}

/* Input Wrapper */
.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 1rem;
    color: #64748b;
    font-size: 1rem;
    z-index: 1;
    pointer-events: none;
}

/* Form Controls */
.pop-up-form-control,
.form-select {
    width: 100%;
    padding: 0.875rem 1rem 0.875rem 3rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.9375rem;
    transition: all 0.3s ease;
    background: #f8fafc;
}

.pop-up-form-control:focus,
.form-select:focus {
    outline: none;
    border-color: #024442;
    background: white;
    box-shadow: 0 0 0 4px rgba(2, 68, 66, 0.08);
}

.pop-up-form-control::placeholder {
    color: #94a3b8;
}

textarea.pop-up-form-control {
    resize: vertical;
    min-height: 120px;
}

/* File Upload */
.file-upload-wrapper {
    position: relative;
}

.file-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.file-upload-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1.5rem;
    background: #f8fafc;
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9375rem;
    color: #64748b;
    font-weight: 500;
}

.file-upload-label:hover {
    background: white;
    border-color: #024442;
    color: #024442;
}

.file-upload-label i {
    font-size: 1.125rem;
}

.file-input:focus + .file-upload-label {
    border-color: #024442;
    box-shadow: 0 0 0 4px rgba(2, 68, 66, 0.08);
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    padding-top: 1.5rem;
    border-top: 1px solid #e2e8f0;
    margin-top: 1rem;
}

.form-actions .btn {
    padding: 0.875rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9375rem;
    display: inline-flex;
    align-items: center;
    gap: 0.625rem;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-secondary {
    background: #f1f5f9;
    color: #64748b;
}

.btn-secondary:hover {
    background: #e2e8f0;
    color: #475569;
}

.btn-primary {
    background: linear-gradient(135deg, #024442 0%, #035b58 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(2, 68, 66, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(2, 68, 66, 0.4);
}

/* Loading State */
.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-primary:disabled:hover {
    transform: none;
}

/* ===========================
   Responsive Design
=========================== */

@media (max-width: 768px) {
    .modal-dialog {
        margin: 0.5rem;
    }

    .modal-header {
        padding: 1.5rem;
    }

    .modal-header-content {
        gap: 1rem;
    }

    .modal-icon {
        width: 48px;
        height: 48px;
        font-size: 1.25rem;
    }

    .modal-title {
        font-size: 1.5rem;
    }

    .modal-subtitle {
        font-size: 0.875rem;
    }

    .modal-body {
        padding: 1.5rem;
        max-height: 60vh;
    }

    .section-title {
        font-size: 1rem;
    }

    .form-actions {
        flex-direction: column-reverse;
    }

    .form-actions .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .modal-content {
        border-radius: 20px;
    }

    .modal-header {
        padding: 1.25rem;
    }

    .modal-body {
        padding: 1.25rem;
    }
}
</style>
<script src="js/scripts.js">    </script>

<!-- Enhanced Project Request Modal -->
<div class="modal fade" id="projectRequestModal" tabindex="-1" aria-labelledby="projectRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <div class="modal-header-content">
                    <div class="modal-icon">
                        <i class="fa fa-rocket"></i>
                    </div>
                    <div>
                        <h5 class="modal-title" id="projectRequestModalLabel">Request a Project</h5>
                        <p class="modal-subtitle">Let's bring your vision to life</p>
                    </div>
                </div>
                <button type="button" class="modal-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="fa fa-times"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <form id="projectRequestForm"
      action="project_request_submission.php"
      method="POST"
      enctype="multipart/form-data">

                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fa fa-user"></i>
                            <span>Personal Information</span>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name" class="form-label">
                                        Full Name <span class="required">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <i class="input-icon fa fa-user"></i>
                                        <input type="text" class="pop-up-form-control" id="name" name="name" placeholder="Enter your full name" required>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="company" class="form-label">Company Name</label>
                                    <div class="input-wrapper">
                                        <i class="input-icon fa fa-building"></i>
                                        <input type="text" class="pop-up-form-control" id="company" name="company" placeholder="Your company name">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="form-label">
                                        Email Address <span class="required">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <i class="input-icon fa fa-envelope"></i>
                                        <input type="email" class="pop-up-form-control" id="email" name="email" placeholder="your@email.com" required>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone" class="form-label">
                                        Phone Number <span class="required">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <i class="input-icon fa fa-phone"></i>
                                        <input type="tel" class="pop-up-form-control" id="phone" name="phone" placeholder="+92 300 1234567" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Project Details Section -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fa fa-briefcase"></i>
                            <span>Project Details</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="projectType" class="form-label">
                                        Project Type <span class="required">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <i class="input-icon fa fa-layer-group"></i>
                                        <select class="form-select" id="projectType" name="projectType" required>
                                            <option value="" selected disabled>Select project type</option>
                                            <option value="Website Design">🌐 Website Design</option>
                                            <option value="Mobile App">📱 Mobile App</option>
                                            <option value="Graphic Design">🎨 Graphic Design</option>
                                            <option value="Branding">✨ Branding & Identity</option>
                                            <option value="Marketing Campaign">📢 Marketing Campaign</option>
                                            <option value="Content Writing">✍️ Content Writing</option>
                                            <option value="Other">💡 Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="budget" class="form-label">Estimated Budget (PKR)</label>
                                    <div class="input-wrapper">
                                        <i class="input-icon fa fa-money-bill"></i>
                                        <input type="number" class="pop-up-form-control" id="budget" name="budget" placeholder="e.g., 50,000">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="time" class="form-label">
                                        Estimated Timeframe <span class="required">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <i class="input-icon fa fa-clock"></i>
                                        <input type="text" class="pop-up-form-control" id="time" name="time" placeholder="e.g., 2 weeks, 1 month" required>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="attachment" class="form-label">Attach File</label>
                                    <div class="file-upload-wrapper">
                                        <input type="file" class="file-input" id="attachment" name="attachment">
                                        <label for="attachment" class="file-upload-label">
                                            <i class="fa fa-paperclip"></i>
                                            <span class="file-text">Choose file</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-group">
                                    <label for="description" class="form-label">
                                        Project Description <span class="required">*</span>
                                    </label>
                                    <div class="input-wrapper">
                                        <i class="input-icon fa fa-align-left"></i>
                                        <textarea class="pop-up-form-control" id="description" name="description" rows="4" placeholder="Tell us about your project, goals, and any specific requirements..." required></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fa fa-times"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-paper-plane"></i>
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>