 <?php
    include('header.php');
 ?>
</head>
<body>
    <!-- Hero Banner -->
    <section class="portfolio-hero">
        <div class="container pt-5">
            <h1>Our <span class="highlight" style="color: var(--primary);">Portfolio</span></h1>
            <p>Explore our creative work across branding, web design, digital marketing, and more. Each project tells a unique story of innovation and excellence.</p>
        </div>
    </section>

    <!-- Filter Section -->
    <section class="filter-section">
        <div class="container">
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">All Projects</button>
                <button class="filter-btn" data-filter="branding">Branding</button>
                <button class="filter-btn" data-filter="web-design">Web Design</button>
                <button class="filter-btn" data-filter="social-media">Social Media</button>
                <button class="filter-btn" data-filter="marketing">Marketing</button>
                <button class="filter-btn" data-filter="packaging">Packaging</button>
            </div>
        </div>
    </section>

    <!-- Featured Project Banner -->
    <section class="portfolio-section">
        <div class="container">
            <div class="featured-banner" onclick="openProject('featured-project')">
                <img src="https://images.unsplash.com/photo-1558655146-9f40138edfeb?w=1200&h=500&fit=crop" alt="Featured Project">
                <div class="featured-overlay">
                    <div class="featured-category">Branding & Identity</div>
                    <div class="featured-title">TechVibe Startup Branding</div>
                    <div class="featured-description">Complete brand identity design for innovative tech startup including logo, guidelines, and collateral.</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Standard Grid Section -->
    <section class="portfolio-section bg-white">
        <div class="container">
            <h2 class="section-title">Recent <span>Projects</span></h2>
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="project-card" data-category="branding" onclick="openProject('brand-1')">
                        <div class="project-image">
                            <img src="https://images.unsplash.com/photo-1561070791-2526d30994b5?w=600&h=400&fit=crop" alt="Branding Project">
                        </div>
                        <div class="project-info">
                            <div class="project-category">Branding</div>
                            <div class="project-title">Organic Cafe Brand Identity</div>
                            <div class="project-description">Modern brand identity for eco-friendly cafe featuring earthy tones and natural aesthetics.</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="project-card" data-category="web-design" onclick="openProject('web-1')">
                        <div class="project-image">
                            <img src="https://images.unsplash.com/photo-1547658719-da2b51169166?w=600&h=400&fit=crop" alt="Web Design">
                        </div>
                        <div class="project-info">
                            <div class="project-category">Web Design</div>
                            <div class="project-title">E-Commerce Platform</div>
                            <div class="project-description">Responsive online store with seamless checkout and modern UI/UX design.</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="project-card" data-category="social-media" onclick="openProject('social-1')">
                        <div class="project-image">
                            <img src="https://images.unsplash.com/photo-1611162617474-5b21e879e113?w=600&h=400&fit=crop" alt="Social Media">
                        </div>
                        <div class="project-info">
                            <div class="project-category">Social Media</div>
                            <div class="project-title">Instagram Campaign Design</div>
                            <div class="project-description">Engaging social media templates and content strategy for fashion brand.</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="project-card" data-category="packaging" onclick="openProject('pack-1')">
                        <div class="project-image">
                            <img src="https://images.unsplash.com/photo-1563298723-dcfebaa392e3?w=600&h=400&fit=crop" alt="Packaging">
                        </div>
                        <div class="project-info">
                            <div class="project-category">Packaging</div>
                            <div class="project-title">Premium Skincare Packaging</div>
                            <div class="project-description">Luxury packaging design with minimalist aesthetics and premium materials.</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="project-card" data-category="marketing" onclick="openProject('market-1')">
                        <div class="project-image">
                            <img src="https://images.unsplash.com/photo-1557804506-669a67965ba0?w=600&h=400&fit=crop" alt="Marketing">
                        </div>
                        <div class="project-info">
                            <div class="project-category">Marketing</div>
                            <div class="project-title">Product Launch Campaign</div>
                            <div class="project-description">Comprehensive digital marketing campaign with email and ad creatives.</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="project-card" data-category="web-design" onclick="openProject('web-2')">
                        <div class="project-image">
                            <img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=600&h=400&fit=crop" alt="Web Design">
                        </div>
                        <div class="project-info">
                            <div class="project-category">Web Design</div>
                            <div class="project-title">Corporate Website Redesign</div>
                            <div class="project-description">Professional website redesign with improved UX and brand consistency.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Overlay Cards Section -->
    <section class="portfolio-section">
        <div class="container">
            <h2 class="section-title">Featured <span>Work</span></h2>
            <div class="row">
                <div class="col-lg-6">
                    <div class="overlay-card" data-category="branding" onclick="openProject('brand-2')">
                        <img src="https://images.unsplash.com/photo-1542744094-24638eff58bb?w=700&h=500&fit=crop" alt="Project">
                        <div class="overlay-content">
                            <div class="project-category">Branding</div>
                            <div class="project-title">Fitness App Branding</div>
                            <div class="project-description">Vibrant brand identity for fitness tracking application.</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="overlay-card" data-category="web-design" onclick="openProject('web-3')">
                        <img src="https://images.unsplash.com/photo-1517292987719-0369a794ec0f?w=700&h=500&fit=crop" alt="Project">
                        <div class="overlay-content">
                            <div class="project-category">Web Design</div>
                            <div class="project-title">Restaurant Booking Platform</div>
                            <div class="project-description">Interactive web platform with reservation system.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Masonry Grid Section -->
    <section class="portfolio-section bg-white">
        <div class="container">
            <h2 class="section-title">Creative <span>Showcase</span></h2>
            <div class="masonry-grid">

                <div class="masonry-item" data-category="branding" onclick="openProject('brand-3')">
                    <img src="https://images.unsplash.com/photo-1572044162444-ad60f128bdea?w=500&h=350&fit=crop" alt="Branding">
                    <div class="masonry-overlay">
                        <div class="project-category">Branding</div>
                        <div class="project-title">Logo Suite</div>
                    </div>
                </div>

                <div class="masonry-item" data-category="packaging" onclick="openProject('pack-2')">
                    <img src="https://images.unsplash.com/photo-1620799140408-edc6dcb6d633?w=500&h=700&fit=crop" alt="Packaging">
                    <div class="masonry-overlay">
                        <div class="project-category">Packaging</div>
                        <div class="project-title">Coffee Packaging</div>
                    </div>
                </div>

                <div class="masonry-item" data-category="marketing" onclick="openProject('market-2')">
                    <img src="https://images.unsplash.com/photo-1533750349088-cd871a92f312?w=500&h=400&fit=crop" alt="Marketing">
                    <div class="masonry-overlay">
                        <div class="project-category">Marketing</div>
                        <div class="project-title">Ad Campaign</div>
                    </div>
                </div>

                <div class="masonry-item" data-category="web-design" onclick="openProject('web-4')">
                    <img src="https://images.unsplash.com/photo-1499951360447-b19be8fe80f5?w=500&h=500&fit=crop" alt="Web Design">
                    <div class="masonry-overlay">
                        <div class="project-category">Web Design</div>
                        <div class="project-title">Portfolio Site</div>
                    </div>
                </div>
                <div class="masonry-item" data-category="packaging" onclick="openProject('pack-2')">
                    <img src="https://images.unsplash.com/photo-1620799140408-edc6dcb6d633?w=500&h=700&fit=crop" alt="Packaging">
                    <div class="masonry-overlay">
                        <div class="project-category">Packaging</div>
                        <div class="project-title">Coffee Packaging</div>
                    </div>
                </div>
                <div class="masonry-item" data-category="social-media" onclick="openProject('social-3')">
                    <img src="https://images.unsplash.com/photo-1611162616305-c69b3fa7fbe0?w=500&h=650&fit=crop" alt="Social Media">
                    <div class="masonry-overlay">
                        <div class="project-category">Social Media</div>
                        <div class="project-title">Feed Design</div>
                    </div>
                </div>
                   <div class="masonry-item" data-category="web-design" onclick="openProject('web-4')">
                    <img src="https://images.unsplash.com/photo-1499951360447-b19be8fe80f5?w=500&h=500&fit=crop" alt="Web Design">
                    <div class="masonry-overlay">
                        <div class="project-category">Web Design</div>
                        <div class="project-title">Portfolio Site</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2>Have a Project in Mind?</h2>
            <p>Let's work together to bring your vision to life</p>
            <button class="main-btn cta-btn" onclick="window.location.href='/contact'">Start Your Project</button>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filter functionality
        const filterBtns = document.querySelectorAll('.filter-btn');
        const projectCards = document.querySelectorAll('[data-category]');

        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all buttons
                filterBtns.forEach(b => b.classList.remove('active'));
                // Add active class to clicked button
                btn.classList.add('active');

                const filter = btn.getAttribute('data-filter');

                projectCards.forEach(card => {
                    if (filter === 'all' || card.getAttribute('data-category') === filter) {
                        card.style.display = 'block';
                        setTimeout(() => {
                            card.style.opacity = '1';
                            card.style.transform = 'scale(1)';
                        }, 10);
                    } else {
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.8)';
                        setTimeout(() => {
                            card.style.display = 'none';
                        }, 300);
                    }
                });
            });
        });

        // Project click handler
        function openProject(projectId) {
            // In a real application, this would navigate to a project detail page
            alert(`Opening project: ${projectId}\n\nThis would redirect to: /portfolio/${projectId}`);
            // Uncomment the line below for actual navigation
            // window.location.href = `/portfolio/${projectId}`;
        }
    </script>

    <?php
        include('footer.php');
    ?>