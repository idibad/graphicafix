

    <!-- Footer -->
    <footer>
        <div class="footer-container">
            <div class="footer-grid">
                <!-- Brand Section -->
                <div class="footer-brand">
                    <img src="images/logo.png" alt="Graphicafix Logo">
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
                        <span style="color: #a1a1aa;">Lahore, Pakistan</span>
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

    <!-- Back to Top Script -->
    <script>
        const backToTop = document.getElementById('backToTop');

        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        });

        backToTop.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    </script>
</body>
</html>