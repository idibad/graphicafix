 <?php
    include('header.php');
 ?>
 <style>
 
 /* Hero Banner */
        .portfolio-hero {
            background: linear-gradient(135deg, var(--dark) 0%, var(--primary) 100%);
            padding: 100px 0 60px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .portfolio-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="2" fill="white" opacity="0.05"/></svg>');
        }

        .portfolio-hero h1 {
            font-size: 52px;
            font-weight: 700;
            margin-bottom: 15px;
            position: relative;
        }

        .portfolio-hero .highlight {
            color: var(--accent);
        }

        .portfolio-hero p {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.8);
            max-width: 700px;
            margin: 0 auto 30px;
            position: relative;
        }

        /* Filter Buttons */
        .filter-section {
            background: white;
            padding: 30px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .filter-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 28px;
            border: 2px solid #e0e0e0;
            background: white;
            color: #666;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 14px;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
            transform: translateY(-2px);
        }

        /* Portfolio Sections */
        .portfolio-section {
            padding: 20px 0;
        }

        .section-title {
            font-size: 36px;
            font-weight: 600;
            color: #333;
            margin-bottom: 40px;
            text-align: center;
        }

        .section-title span {
            color: var(--accent);
        }

        /* Featured Project Banner */
        .featured-banner {
            position: relative;
            height: 500px;
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 10px;
            cursor: pointer;
            transition: transform 0.4s ease;
        }

        .featured-banner:hover {
            transform: scale(1.02);
        }

        .featured-banner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .featured-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.9) 10%,  transparent );
            padding: 50px 40px;
            color: white;
        }

        .featured-category {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--accent);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .featured-title {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .featured-description {
            font-size: 16px;
            opacity: 0.9;
        }

        /* Grid Cards - Style 1: Standard Grid */
        .project-card {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.4s ease;
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 10px;
        }
        
        .project-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);

        }

        .project-image {
            width: 100%;
            height: 280px;
            border-radius: 10px;
            position: relative;
            overflow: hidden;
        }
        .project-image::before{
            content: "";
            background: linear-gradient(transparent, var(--dark));
            inset: 0;
            position: absolute;
            z-index: 1;
            transition: transform 0.4s ease;
        }
       
        .project-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: relative;
            z-index: 0;
            transition: transform 0.4s ease;
        }

        .project-card:hover .project-image img {
            transform: scale(1.1);
        }
        .project-card:hover .project-image::before{
            transform: translateY(100%);
        }

        .project-info {
            padding: 25px;
            z-index: 2;
            transition: opacity 0.4s linear;
        }

        .project-category {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--accent);
            font-weight: 600;
            margin-bottom: 8px;
            transition: transform 0.2s ease-out;
        }

        .project-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--light) ;
            margin-bottom: 10px;
            width: 95%;
            transition: transform 0.2s ease-out;
        }

        .project-description {
            font-size: 14px;
            color: var(--light);
            line-height: 1.6;
            width: 90%;
            transition: transform 0.2s ease-out;
        }

        
        .project-card:hover .project-info{
            opacity: 0;
        }


        /* Grid Cards - Style 2: Overlay Cards */
        .overlay-card {
            position: relative;
            height: 350px;
            border-radius: 16px;
            overflow: hidden;
            cursor: pointer;
            margin-bottom: 30px;
        }

        .overlay-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .overlay-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 30px;
            background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
            color: white;
            transform: translateY(60px);
            transition: transform 0.4s ease;
        }

        .overlay-card:hover .overlay-content {
            transform: translateY(0);
        }

        .overlay-card:hover img {
            transform: scale(1.1);
        }

        /* Masonry Grid */
        .masonry-grid {
            column-count: 3;
            column-gap: 30px;
        }

        .masonry-item {
            break-inside: avoid;
            margin-bottom: 30px;
            border-radius: 16px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s ease;
            position: relative;
        }

        .masonry-item:hover {
            transform: scale(1.02);
        }

        .masonry-item img {
            width: 100%;
            display: block;
            transition: filter 0.3s ease;
        }

        .masonry-item:hover img {
            filter: brightness(0.9);
        }

        .masonry-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            padding: 20px;
            color: white;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .masonry-item:hover .masonry-overlay {
            opacity: 1;
        }

        /* CTA Section */
        .cta-section {
            background: var(--dark);
            padding: 80px 0;
            text-align: center;
        }

        .cta-section h2 {
            color: var(--accent);
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .cta-section p {
            color: var(--light);
            font-size: 18px;
            margin-bottom: 30px;
            opacity: 0.95;
        }

        .cta-btn {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .cta-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        @media (max-width: 992px) {
            .masonry-grid {
                column-count: 2;
            }

            .portfolio-hero h1 {
                font-size: 38px;
            }

            .featured-banner {
                height: 400px;
            }
        }

        @media (max-width: 768px) {
            .masonry-grid {
                column-count: 1;
            }

            .filter-buttons {
                gap: 10px;
            }

            .filter-btn {
                padding: 8px 20px;
                font-size: 13px;
            }

            .portfolio-hero h1 {
                font-size: 32px;
            }

            .featured-banner {
                height: 350px;
            }

            .featured-title {
                font-size: 32px;
            }

            .project-image {
                height: 220px;
            }
        }
    </style>
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