

    <!-- Footer -->
    <footer>
        <div class="footer-container">
            <div class="footer-grid">
                <!-- Brand Section -->
                <div class="footer-brand">
                    <img src="<?= BASE_URL ?>images/logo.png" alt="Graphicafix Logo">
                    <p>With a diverse skill set and experience across hundreds of branding and design projects, we can take on your creative needs—whether it's a single project or your entire design pipeline.</p>
                    <h5>Follow Us</h5>
                    <div class="social-links">
                        <a href="https://facebook.com/graphicafix" target="_blank" aria-label="Facebook">
                            <i class="fa-brands fa-facebook-f"></i>
                        </a>
                        <a href="https://instagram.com/graphicafix" target="_blank" aria-label="Instagram">
                            <i class="fa-brands fa-instagram"></i>
                        </a>
                        <a href="https://youtube.com/@graphicafix" target="_blank" aria-label="YouTube">
                            <i class="fa-brands fa-youtube"></i>
                        </a>
                        <a href="https://linkedin.com/company/graphicafix" target="_blank" aria-label="LinkedIn">
                            <i class="fa-brands fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="footer-section">
                    <h5>Quick Links</h5>
                    <ul>
                        <li><a href="index.php"><i class="fa-solid fa-chevron-right"></i> Home</a></li>
                        <li><a href="services.php"><i class="fa-solid fa-chevron-right"></i> Services</a></li>
                        <li><a href="portfolio.php"><i class="fa-solid fa-chevron-right"></i> Portfolio</a></li>
                        <li><a href="about.php"><i class="fa-solid fa-chevron-right"></i> About</a></li>
                        <li><a href="contact_us.php"><i class="fa-solid fa-chevron-right"></i> Contact Us</a></li>
                    </ul>
                </div>

                <!-- Services -->
                <div class="footer-section">
                    <h5>Our Services</h5>
                    <ul>
                        <li><a href="services.php#branding"><i class="fa-solid fa-chevron-right"></i> Branding</a></li>
                        <li><a href="services.php#web-design"><i class="fa-solid fa-chevron-right"></i> Web Design</a></li>
                        <li><a href="services.php#graphic-design"><i class="fa-solid fa-chevron-right"></i> Graphic Design</a></li>
                        <li><a href="services.php#social-media"><i class="fa-solid fa-chevron-right"></i> Social Media</a></li>
                        <li><a href="career.php"><i class="fa-solid fa-chevron-right"></i> Career</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div class="footer-section">
                    <h5>Get In Touch</h5>
                    <div class="contact-item">
                        <i class="fa-solid fa-envelope"></i>
                        <a href="mailto:contact@graphicafix.com">contact@graphicafix.com</a>
                    </div>
                    <div class="contact-item">
                        <i class="fa-brands fa-whatsapp"></i>
                        <a href="https://wa.me/923454568986" target="_blank">+92 345 4568986</a>
                    </div>
                    <div class="contact-item">
                        <i class="fa-solid fa-location-dot"></i>
                        <span style="color: #a1a1aa;">Islamabad, Pakistan</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Copyright -->
        <div class="copyright">
            <div class="copyright-content">
                <p>© 2025 Graphicafix. Crafted with creativity and dedication.</p>
                <div class="copyright-links">
                    <a href="privacy-policy.php">Privacy Policy</a>
                    <a href="terms.php">Terms of Service</a>
                    <a href="sitemap.php">Sitemap</a>
                </div>
            </div>
        </div>
    </footer>

    

    <!-- Scripts -->
    <script src="js/scripts.js"></script>
    <script src="js/bootstrap.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>

    <!-- Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>AOS.init({ duration: 600, once: true });</script>

    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

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
    type();
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
</script>
</body>
</html>