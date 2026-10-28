

    <!-- Footer -->
    <footer>
        <div class="footer-container">
            <!-- Newsletter Subscription Section -->
            <div class="footer-newsletter-wrap">
                <div class="footer-newsletter-row">
                    <div class="footer-newsletter-info">
                        <span class="footer-newsletter-badge"><i class="fas fa-paper-plane"></i> Newsletter</span>
                        <h4 class="footer-newsletter-title">Subscribe to Graphicafix</h4>
                        <p class="footer-newsletter-desc">Get insider branding tips, latest project releases, and exclusive design insights straight to your inbox.</p>
                    </div>
                    <div class="footer-newsletter-action">
                        <form id="footerSubscribeForm" class="footer-newsletter-form" onsubmit="handleFooterSubscribe(event)">
                            <div class="footer-newsletter-input-group">
                                <i class="fas fa-envelope footer-input-icon"></i>
                                <input type="email" name="email" id="footerSubEmail" placeholder="Enter your email address..." required autocomplete="email">
                                <button type="submit" id="footerSubBtn" class="footer-newsletter-btn">
                                    <span>Subscribe</span> <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                            <div id="footerSubMsg" class="footer-newsletter-msg"></div>
                        </form>
                        <div class="footer-newsletter-privacy">
                            <i class="fas fa-shield-alt"></i> 100% Secure. We respect your privacy.
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-grid">
                <!-- Brand Section -->
                <div class="footer-brand">
                    <img src="<?= BASE_URL ?>assets/images/logo.png" alt="Graphicafix Logo">
                    <p>With a diverse skill set and experience across hundreds of branding and design projects, we can take on your creative needs—whether it's a single project or your entire design pipeline.</p>
                    <h5>Follow Us</h5>
                    <div class="social-links">
                        <a href="https://facebook.com/graphicafix" target="_blank" aria-label="Facebook">
                            <i class="fab fa-facebook"></i>
                        </a>
                        <a href="https://instagram.com/graphicafix" target="_blank" aria-label="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="https://youtube.com/@graphicafix" target="_blank" aria-label="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                        <a href="https://linkedin.com/company/graphicafix" target="_blank" aria-label="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="footer-section">
                    <h5>Quick Links</h5>
                    <ul>
                        <li><a href="<?= BASE_URL ?>index"><i class="fas fa-chevron-right"></i> Home</a></li>
                        <li><a href="<?= BASE_URL ?>services"><i class="fas fa-chevron-right"></i> Services</a></li>
                        <li><a href="<?= BASE_URL ?>portfolio"><i class="fas fa-chevron-right"></i> Portfolio</a></li>
                        <li><a href="<?= BASE_URL ?>about"><i class="fas fa-chevron-right"></i> About</a></li>
                        <li><a href="<?= BASE_URL ?>contact_us"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                    </ul>
                </div>

                <!-- Services -->
                <div class="footer-section">
                    <h5>Our Services</h5>
                    <ul>
                        <li><a href="<?= BASE_URL ?>services#branding"><i class="fas fa-chevron-right"></i> Branding</a></li>
                        <li><a href="<?= BASE_URL ?>services#web-design"><i class="fas fa-chevron-right"></i> Web Design</a></li>
                        <li><a href="<?= BASE_URL ?>services#graphic-design"><i class="fas fa-chevron-right"></i> Graphic Design</a></li>
                        <li><a href="<?= BASE_URL ?>services#social-media"><i class="fas fa-chevron-right"></i> Social Media</a></li>
                        <li><a href="<?= BASE_URL ?>career"><i class="fas fa-chevron-right"></i> Career</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div class="footer-section">
                    <h5>Get In Touch</h5>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <a href="mailto:contact@graphicafix.com">contact@graphicafix.com</a>
                    </div>
                    <div class="contact-item">
                        <i class="fab fa-whatsapp"></i>
                        <div style="display:flex; flex-direction:column; gap:6px;">
                            <a href="https://wa.me/923454568986" target="_blank" style="display:inline-flex; align-items:center; gap:8px;">
                                <img src="https://flagcdn.com/w20/pk.png" width="18" height="13" alt="PK Flag" style="border-radius:2px; object-fit:cover;"> +92 345 4568986
                            </a>
                            <a href="https://wa.me/447903442499" target="_blank" style="display:inline-flex; align-items:center; gap:8px;">
                                <img src="https://flagcdn.com/w20/gb.png" width="18" height="13" alt="UK Flag" style="border-radius:2px; object-fit:cover;"> +44 7903 442499
                            </a>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span style="color: #a1a1aa;">Pakistan | UK</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Copyright -->
        <div class="copyright">
            <div class="copyright-content">
                <p>© 2025 Graphicafix. Crafted with creativity and dedication.</p>
                <div class="copyright-links">
                    <a href="<?= BASE_URL ?>privacy-policy">Privacy Policy</a>
                    <a href="<?= BASE_URL ?>terms">Terms of Service</a>
                    <a href="sitemap.xml">Sitemap</a>
                </div>
            </div>
        </div>
    </footer>

    

    <!-- Scripts -->
    <script src="<?= BASE_URL ?>assets/js/scripts.js"></script>
    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.js"></script>
    <script src="<?= BASE_URL ?>assets/js/form-validator.js"></script>

    <!-- Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>AOS.init({ duration: 600, once: true });</script>

    <!--MENU -->
    <script>
       






// Mobile Menu Toggle
const mobileToggle = document.getElementById('mobileToggle');
const mobileMenu = document.getElementById('mobileMenu');

mobileToggle.addEventListener('click', () => {
    mobileToggle.classList.toggle('active');
    mobileMenu.classList.toggle('active');
});

// Navbar Scroll Effect
window.addEventListener('scroll', () => {
    const navbar = document.querySelector('.modern-navbar');
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

// Close mobile menu when clicking outside
document.addEventListener('click', (e) => {
    if (!e.target.closest('.modern-navbar')) {
        mobileToggle.classList.remove('active');
        mobileMenu.classList.remove('active');
    }
});
    </script>

<script>

    // Typing Animation
const words = ['Innovation', 'Creativity', 'Excellence', 'Quality', 'Design', 'Passion'];
let wordIndex = 0;
let charIndex = 0;
let isDeleting = false;
const typingElement = document.getElementById('changing-word');
const typingSpeed = 120;
const deletingSpeed = 80;
const pauseTime = 2000;

function type() {
    const currentWord = words[wordIndex];
    
    if (isDeleting) {
        typingElement.textContent = currentWord.substring(0, charIndex - 1);
        charIndex--;
    } else {
        typingElement.textContent = currentWord.substring(0, charIndex + 1);
        charIndex++;
    }
    
    if (!isDeleting && charIndex === currentWord.length) {
        isDeleting = true;
        setTimeout(type, pauseTime);
    } else if (isDeleting && charIndex === 0) {
        isDeleting = false;
        wordIndex = (wordIndex + 1) % words.length;
        setTimeout(type, 500);
    } else {
        setTimeout(type, isDeleting ? deletingSpeed : typingSpeed);
    }
}

// Start typing animation
document.addEventListener('DOMContentLoaded', () => {
    if (typingElement) {
        type();
    }
    animateCounters();
});

// Counter Animation
function animateCounters() {
    const counters = document.querySelectorAll('.stat-number');
    
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-count'));
        const duration = 2000;
        const increment = target / (duration / 16);
        let current = 0;
        
        const updateCounter = () => {
            current += increment;
            if (current < target) {
                counter.textContent = Math.ceil(current) + (target > 10 ? '+' : '');
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target + (target > 10 ? '+' : '');
            }
        };
        
        // Start animation when element is in view
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    updateCounter();
                    observer.unobserve(entry.target);
                }
            });
        });
        
        observer.observe(counter);
    });
}

// Footer Newsletter AJAX Subscription
function handleFooterSubscribe(e) {
    e.preventDefault();
    const form = document.getElementById('footerSubscribeForm');
    if (!form) return;

    if (window.GfxValidator && !window.GfxValidator.validateForm(form)) {
        return;
    }

    const emailInput = document.getElementById('footerSubEmail');
    const btn = document.getElementById('footerSubBtn');
    const msg = document.getElementById('footerSubMsg');
    const email = emailInput ? emailInput.value.trim() : '';

    if (!email) return;

    const originalBtnHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subscribing...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('email', email);

    fetch('<?= BASE_URL ?>api/subscribe_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        msg.style.display = 'block';
        if (data.success) {
            msg.className = 'footer-newsletter-msg success';
            msg.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
            emailInput.value = '';
            if (window.GfxValidator) { window.GfxValidator.clearErrors(form); }
            localStorage.setItem('gfx_newsletter_handled', 'true');
        } else {
            msg.className = 'footer-newsletter-msg error';
            msg.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
        }
    })
    .catch(() => {
        msg.style.display = 'block';
        msg.className = 'footer-newsletter-msg error';
        msg.innerHTML = '<i class="fas fa-exclamation-circle"></i> Connection error. Please try again.';
    })
    .finally(() => {
        btn.innerHTML = originalBtnHtml;
        btn.disabled = false;
        setTimeout(() => {
            if (msg && msg.classList.contains('success')) {
                msg.style.display = 'none';
            }
        }, 6000);
    });
}
</script>

</body>
</html>