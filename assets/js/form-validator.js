/**
 * Graphicafix Real-Time Form Validation Engine
 * Implements real-time ("on the go") validation with minimal red light font errors.
 */
(function (window, document) {
    'use strict';

    var GfxValidator = {
        iconSvg: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>',

        /**
         * Clean human label from field or surrounding DOM
         */
        getFieldLabel: function (input) {
            if (input.getAttribute('data-label')) {
                return input.getAttribute('data-label');
            }
            if (input.id) {
                var lbl = document.querySelector('label[for="' + input.id + '"]');
                if (lbl) {
                    return lbl.textContent.replace(/[*:\n\r\t]/g, '').trim();
                }
            }
            var parentLabel = input.closest('label');
            if (parentLabel) {
                var clone = parentLabel.cloneNode(true);
                var nestedInputs = clone.querySelectorAll('input, select, textarea');
                for (var i = 0; i < nestedInputs.length; i++) { nestedInputs[i].remove(); }
                var text = clone.textContent.replace(/[*:\n\r\t]/g, '').trim();
                if (text) return text;
            }
            var prev = input.parentElement ? input.parentElement.querySelector('label') : null;
            if (!prev && input.closest('.form-group, .mb-3, .col-md-6, .col-12, .col-sm-6')) {
                var container = input.closest('.form-group, .mb-3, .col-md-6, .col-12, .col-sm-6');
                prev = container.querySelector('label');
            }
            if (prev) {
                return prev.textContent.replace(/[*:\n\r\t]/g, '').trim();
            }
            if (input.placeholder && input.placeholder.length < 30) {
                return input.placeholder.replace(/[*:\n\r\t]/g, '').trim();
            }
            return input.name ? input.name.replace(/_/g, ' ') : 'This field';
        },

        /**
         * Find appropriate mount element for the error message
         */
        getErrorMountTarget: function (input) {
            // Check for wrappers (to preserve internal flexbox/icon layout)
            var wrapper = input.closest('.input-wrapper, .password-wrapper, .gfx-input-group, .input-group, .file-upload-wrapper, .discount-input-wrap, .footer-newsletter-input-group');
            if (wrapper && wrapper.parentNode) {
                return wrapper;
            }
            if (input.type === 'checkbox' || input.type === 'radio') {
                var formCheck = input.closest('.form-check, .choice-label');
                if (formCheck) return formCheck;
            }
            return input;
        },

        /**
         * Show error message under field
         */
        showError: function (input, message) {
            if (!input) return;

            // Highlight input
            input.classList.add('gfx-input-invalid');
            var wrapper = input.closest('.input-wrapper, .password-wrapper, .gfx-input-group, .input-group, .file-upload-wrapper, .discount-input-wrap, .footer-newsletter-input-group');
            if (wrapper) {
                wrapper.classList.add('gfx-wrapper-invalid');
            }

            var mount = this.getErrorMountTarget(input);
            var errId = 'gfx-err-' + (input.id || input.name || Math.random().toString(36).substr(2, 6));
            var errEl = input._gfxErrorEl || (mount.nextElementSibling && mount.nextElementSibling.classList.contains('gfx-field-error') ? mount.nextElementSibling : null);

            if (!errEl || !errEl.classList.contains('gfx-field-error')) {
                errEl = document.createElement('div');
                errEl.className = 'gfx-field-error';
                errEl.id = errId;
                errEl.setAttribute('role', 'alert');
                errEl.setAttribute('aria-live', 'polite');
                mount.parentNode.insertBefore(errEl, mount.nextSibling);
                input._gfxErrorEl = errEl;
            }

            errEl.innerHTML = this.iconSvg + '<span>' + message + '</span>';
            // Trigger animation frame for smooth opacity transition
            requestAnimationFrame(function () {
                errEl.classList.add('show');
            });
        },

        /**
         * Clear error message for field
         */
        clearError: function (input) {
            if (!input) return;

            input.classList.remove('gfx-input-invalid');
            var wrapper = input.closest('.input-wrapper, .password-wrapper, .gfx-input-group, .input-group, .file-upload-wrapper, .discount-input-wrap, .footer-newsletter-input-group');
            if (wrapper) {
                wrapper.classList.remove('gfx-wrapper-invalid');
            }

            var errEl = input._gfxErrorEl;
            if (!errEl) {
                var mount = this.getErrorMountTarget(input);
                if (mount.nextElementSibling && mount.nextElementSibling.classList.contains('gfx-field-error')) {
                    errEl = mount.nextElementSibling;
                }
            }

            if (errEl) {
                errEl.classList.remove('show');
                setTimeout(function () {
                    if (errEl && !errEl.classList.contains('show') && errEl.parentNode) {
                        errEl.parentNode.removeChild(errEl);
                        input._gfxErrorEl = null;
                    }
                }, 200);
            }
        },

        /**
         * Validate an individual input element
         * Returns true if valid, false if invalid.
         */
        validateField: function (input, isSubmitting) {
            if (!input || input.disabled || input.readOnly || input.type === 'hidden') {
                return true;
            }
            // Skip honeypots
            if (input.name === '_hp_website' || input.id === '_hp_website') {
                return true;
            }
            if (input.getAttribute('data-ignore-validation') === 'true') {
                return true;
            }

            var val = (input.value || '').trim();
            var label = this.getFieldLabel(input);
            var isRequired = input.required || input.getAttribute('aria-required') === 'true' || input.hasAttribute('required');

            // 1. Required Check
            if (isRequired) {
                if (input.type === 'checkbox') {
                    if (!input.checked) {
                        this.showError(input, input.getAttribute('data-error-required') || (label + ' is required.'));
                        return false;
                    }
                } else if (input.type === 'radio') {
                    var groupName = input.name;
                    var anyChecked = false;
                    if (groupName) {
                        var radios = (input.form || document).querySelectorAll('input[type="radio"][name="' + groupName + '"]');
                        for (var r = 0; r < radios.length; r++) {
                            if (radios[r].checked) { anyChecked = true; break; }
                        }
                    }
                    if (!anyChecked) {
                        this.showError(input, input.getAttribute('data-error-required') || 'Please select an option.');
                        return false;
                    }
                } else if (input.type === 'file') {
                    if (!input.files || input.files.length === 0) {
                        this.showError(input, input.getAttribute('data-error-required') || 'Please select a file to upload.');
                        return false;
                    }
                } else if (input.tagName === 'SELECT') {
                    if (!val || val === '' || input.selectedIndex === 0 && input.options[0] && input.options[0].disabled) {
                        this.showError(input, input.getAttribute('data-error-required') || ('Please select ' + (label ? label.toLowerCase() : 'an option') + '.'));
                        return false;
                    }
                } else if (val === '') {
                    this.showError(input, input.getAttribute('data-error-required') || (label + ' is required.'));
                    return false;
                }
            }

            // If empty and not required, clear error and return true
            if (val === '' && input.type !== 'file') {
                this.clearError(input);
                return true;
            }

            // 2. Email Validation
            var isEmail = input.type === 'email' || input.name === 'email' || input.name === 'reset_email' || input.getAttribute('data-type') === 'email';
            if (isEmail && val !== '') {
                var emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;
                if (!emailRegex.test(val)) {
                    this.showError(input, input.getAttribute('data-error-email') || 'Please enter a valid email address.');
                    return false;
                }
            }

            // 3. Phone Number Validation
            var isPhone = input.type === 'tel' || (input.name && input.name.toLowerCase().includes('phone')) || (input.id && input.id.toLowerCase().includes('phone'));
            if (isPhone && val !== '') {
                var digitsOnly = val.replace(/\D/g, '');
                var phoneValid = /^[\d\s+\-()]{7,22}$/.test(val) && digitsOnly.length >= 7;
                if (!phoneValid) {
                    this.showError(input, input.getAttribute('data-error-phone') || 'Please enter a valid phone number (at least 7 digits).');
                    return false;
                }
            }

            // 4. URL Validation
            var isUrl = input.type === 'url' || input.name === 'portfolio' || input.name === 'website';
            if (isUrl && val !== '') {
                var urlRegex = /^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?(\/.*)?$/i;
                if (!urlRegex.test(val)) {
                    this.showError(input, 'Please enter a valid URL (e.g. https://example.com).');
                    return false;
                }
            }

            // 5. Min Length Validation
            var minLength = input.getAttribute('minlength') || input.getAttribute('data-minlength');
            if (!minLength) {
                // Built-in intelligent defaults for specific form fields
                if (input.name === 'description' && input.closest('#projectRequestForm')) {
                    minLength = 20;
                } else if (input.name === 'message' && input.tagName === 'TEXTAREA') {
                    minLength = 10;
                } else if (input.name === 'new_password') {
                    minLength = 8;
                }
            }
            if (minLength && val.length < parseInt(minLength, 10)) {
                var reqLen = parseInt(minLength, 10);
                var msg = input.getAttribute('data-error-minlength') || ('Must be at least ' + reqLen + ' characters (currently ' + val.length + ').');
                this.showError(input, msg);
                return false;
            }

            // 6. Max Length Validation
            var maxLength = input.getAttribute('maxlength') || input.getAttribute('data-maxlength');
            if (maxLength && val.length > parseInt(maxLength, 10)) {
                this.showError(input, 'Cannot exceed ' + maxLength + ' characters.');
                return false;
            }

            // 7. Password Match Validation
            var matchSelector = input.getAttribute('data-match');
            if (!matchSelector && (input.name === 'confirm_password' || input.id === 'confPass')) {
                matchSelector = 'input[name="new_password"], input[name="password"], #newPass';
            }
            if (matchSelector) {
                var target = (input.form || document).querySelector(matchSelector);
                if (target && val !== target.value) {
                    this.showError(input, 'Passwords do not match.');
                    return false;
                }
            }

            // 8. File Upload Validation (Accept & Max Size)
            if (input.type === 'file' && input.files && input.files.length > 0) {
                var file = input.files[0];
                var accept = input.getAttribute('accept');
                if (accept) {
                    var allowedExts = accept.split(',').map(function (s) { return s.trim().toLowerCase(); });
                    var fileName = file.name.toLowerCase();
                    var match = false;
                    for (var a = 0; a < allowedExts.length; a++) {
                        var ext = allowedExts[a];
                        if (ext.startsWith('.') && fileName.endsWith(ext)) { match = true; break; }
                        if (ext === 'image/*' && file.type.startsWith('image/')) { match = true; break; }
                        if (ext === file.type) { match = true; break; }
                    }
                    if (!match) {
                        this.showError(input, 'Allowed file types: ' + accept);
                        return false;
                    }
                }
                var maxBytes = 5 * 1024 * 1024; // 5MB default
                if (file.size > maxBytes) {
                    this.showError(input, 'File size exceeds maximum 5MB limit.');
                    return false;
                }
            }

            // If all checks pass
            this.clearError(input);
            return true;
        },

        /**
         * Initialize validation on a form
         */
        initForm: function (form) {
            if (!form || form._gfxValidatorAttached) return;
            form._gfxValidatorAttached = true;

            // Disable browser's standard ugly popup bubbles
            form.setAttribute('novalidate', 'true');

            var self = this;
            var selector = 'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]), select, textarea';
            var fields = form.querySelectorAll(selector);

            for (var i = 0; i < fields.length; i++) {
                (function (field) {
                    field._gfxTouched = false;

                    // Real-time "on the go" validation on typing
                    field.addEventListener('input', function () {
                        field._gfxTouched = true;
                        self.validateField(field, false);
                    });

                    // Real-time validation on change (selects, radios, checkboxes, files)
                    field.addEventListener('change', function () {
                        field._gfxTouched = true;
                        self.validateField(field, false);
                    });

                    // Validation on blur
                    field.addEventListener('blur', function () {
                        if (field._gfxTouched || field.value.trim() !== '') {
                            self.validateField(field, false);
                        }
                    });
                })(fields[i]);
            }

            // Intercept form submit
            form.addEventListener('submit', function (e) {
                var isValid = self.validateForm(form);
                if (!isValid) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });
        },

        /**
         * Validate all fields in a form
         * Returns true if all valid, false otherwise.
         */
        validateForm: function (form) {
            var selector = 'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]), select, textarea';
            var fields = form.querySelectorAll(selector);
            var isFormValid = true;
            var firstInvalid = null;

            for (var i = 0; i < fields.length; i++) {
                var field = fields[i];
                field._gfxTouched = true;
                var valid = this.validateField(field, true);
                if (!valid) {
                    isFormValid = false;
                    if (!firstInvalid) {
                        firstInvalid = field;
                    }
                }
            }

            if (!isFormValid && firstInvalid) {
                try {
                    firstInvalid.focus({ preventScroll: false });
                    var mount = this.getErrorMountTarget(firstInvalid);
                    mount.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } catch (err) {}
            }

            return isFormValid;
        },

        /**
         * Clear all errors in a form
         */
        clearErrors: function (form) {
            var selector = 'input, select, textarea';
            var fields = form.querySelectorAll(selector);
            for (var i = 0; i < fields.length; i++) {
                this.clearError(fields[i]);
                fields[i]._gfxTouched = false;
            }
        },

        /**
         * Global auto-initialization
         */
        initAll: function () {
            var forms = document.querySelectorAll('form:not([data-no-validate])');
            for (var i = 0; i < forms.length; i++) {
                this.initForm(forms[i]);
            }
        }
    };

    // Attach to window
    window.GfxValidator = GfxValidator;

    // Auto initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            GfxValidator.initAll();
        });
    } else {
        GfxValidator.initAll();
    }

    // Dynamic observer for modal content and dynamic forms
    if (window.MutationObserver) {
        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var added = mutations[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    var node = added[j];
                    if (node.nodeType === 1) {
                        if (node.tagName === 'FORM' && !node.getAttribute('data-no-validate')) {
                            GfxValidator.initForm(node);
                        } else if (node.querySelectorAll) {
                            var nestedForms = node.querySelectorAll('form:not([data-no-validate])');
                            for (var k = 0; k < nestedForms.length; k++) {
                                GfxValidator.initForm(nestedForms[k]);
                            }
                        }
                    }
                }
            }
        });
        observer.observe(document.body || document.documentElement, { childList: true, subtree: true });
    }

})(window, document);
