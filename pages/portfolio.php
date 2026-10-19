<?php
require_once __DIR__ . '/../core/config.php';
include __DIR__ . '/../templates/header.php';

// Fetch all active, public projects
$projects_stmt = $conn->prepare("
    SELECT id, project_name, category, public_description, thumbnail, show_home
    FROM projects
    WHERE visibility = 'public'
    ORDER BY created_at DESC
");

$projects_stmt->execute();
$projects_result = $projects_stmt->get_result();

$projects = [];
if ($projects_result) {
    while ($row = $projects_result->fetch_assoc()) {
        $projects[] = $row;
    }
}

// Filter featured projects if needed
$featured_projects = array_values(array_filter($projects, fn($p) => $p['show_home']));

?>

    <!-- Hero Banner -->
    <section class="portfolio-hero">
        <div class="container pt-5">
            <h1>Our <span class="highlight">Portfolio</span></h1>
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

    <style>
    /* Inline CSS to bypass aggressive browser caching */
    .portfolio-hero { position: relative; background: #f8fafc; padding: 140px 0 80px; text-align: center; color: #1e293b; border-bottom: 1px solid #e2e8f0; overflow: hidden; z-index: 1; }
    .portfolio-hero::before, .portfolio-hero::after { content: ''; position: absolute; border-radius: 50%; filter: blur(80px); z-index: -1; animation: floatBlob 10s ease-in-out infinite alternate; }
    .portfolio-hero::before { width: 400px; height: 400px; background: rgba(184, 243, 90, 0.1); top: -100px; left: -100px; }
    .portfolio-hero::after { width: 300px; height: 300px; background: rgba(2, 68, 66, 0.05); bottom: -50px; right: 5%; animation-delay: -5s; }
    @keyframes floatBlob { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(30px, 50px) scale(1.1); } }
    .portfolio-hero h1 { font-size: clamp(40px, 6vw, 64px); font-weight: 800; letter-spacing: -1px; margin-bottom: 20px; line-height: 1.1; color: #0f172a; }
    .portfolio-hero .highlight { color: var(--primary); position: relative; display: inline-block; }
    .portfolio-hero .highlight::after { content: ''; position: absolute; bottom: 8px; left: 0; width: 100%; height: 8px; background: var(--primary); opacity: 0.15; z-index: -1; border-radius: 4px; }
    .portfolio-hero p { font-size: clamp(16px, 2vw, 20px); color: #475569; max-width: 650px; margin: 0 auto 40px; line-height: 1.6; }
    
    .filter-section { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(12px); padding: 24px 0; border-bottom: 1px solid #e2e8f0; position: sticky; top: var(--header-height, 80px); z-index: 100; }
    .filter-buttons { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
    .filter-btn { padding: 10px 24px; background: transparent; border: 1px solid #cbd5e1; color: #475569; border-radius: 30px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-weight: 600; font-size: 14px; letter-spacing: 0.5px; }
    .filter-btn:hover { border-color: var(--primary); color: var(--primary); }
    .filter-btn.active { background: var(--primary); border-color: var(--primary); color: white; box-shadow: 0 4px 12px rgba(2, 68, 66, 0.2); }
    
    .portfolio-gallery-section { padding: 60px 0 100px; background: #f8fafc; }
    .dynamic-portfolio-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); grid-auto-rows: 300px; gap: 24px; }
    @media (min-width: 768px) {
        .portfolio-item:nth-child(4n+1) { grid-column: span 2; grid-row: span 2; }
        .portfolio-item:nth-child(8n+5) { grid-column: span 2; }
    }
    .portfolio-item { position: relative; border-radius: 20px; overflow: hidden; cursor: pointer; background: #e2e8f0; transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.4s ease; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1); }
    .portfolio-item.hidden { display: none !important; }
    .portfolio-item img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
    .portfolio-item:hover { transform: translateY(-8px); box-shadow: 0 20px 40px -10px rgba(0,0,0,0.2); z-index: 2; }
    .portfolio-item:hover img { transform: scale(1.08); }
    
    .portfolio-item-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(15, 23, 42, 0.9) 0%, rgba(15, 23, 42, 0.4) 50%, transparent 100%); display: flex; flex-direction: column; justify-content: flex-end; padding: 30px; opacity: 0; transition: opacity 0.4s ease; }
    .portfolio-item:hover .portfolio-item-overlay { opacity: 1; }
    .portfolio-item-category { font-family: 'Courier New', monospace; font-size: 12px; letter-spacing: 2px; text-transform: uppercase; color: var(--accent); margin-bottom: 8px; transform: translateY(20px); transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1) 0.1s; }
    .portfolio-item-title { font-size: 24px; font-weight: 700; color: white; margin-bottom: 0; line-height: 1.2; transform: translateY(20px); transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1) 0.15s; }
    .portfolio-item-arrow { position: absolute; bottom: 30px; right: 30px; width: 40px; height: 40px; background: rgba(255,255,255,0.1); backdrop-filter: blur(8px); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; opacity: 0; transform: translate(-20px, 20px); transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1) 0.2s; }
    .portfolio-item:hover .portfolio-item-category, .portfolio-item:hover .portfolio-item-title { transform: translateY(0); }
    .portfolio-item:hover .portfolio-item-arrow { opacity: 1; transform: translate(0, 0); }
    .portfolio-item-arrow:hover { background: var(--accent); color: var(--dark); }
    
    @media (max-width: 768px) {
        .filter-section { padding: 16px 0; }
        .dynamic-portfolio-grid { grid-template-columns: 1fr; grid-auto-rows: 250px; gap: 16px; }
        @media (hover: none) {
            .portfolio-item-overlay { opacity: 1; background: linear-gradient(to top, rgba(15, 23, 42, 0.9) 0%, transparent 80%); }
            .portfolio-item-category, .portfolio-item-title { transform: translateY(0); }
            .portfolio-item-arrow { opacity: 1; transform: translate(0, 0); }
        }
    }
    </style>


<?php if (!empty($featured_projects)): ?>
<!-- Premium Swiper Featured Showcase (Light Theme) -->
<section class="featured-showcase-section" style="padding: 60px 0; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
    <div class="container">
        <div style="margin-bottom: 40px; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:20px;">
            <div>
                <span style="color: #024442; font-family: 'Courier New', monospace; font-size: 13px; font-weight:700; letter-spacing: 3px; text-transform: uppercase;">Curated Highlights</span>
                <h2 style="color: #024442; font-size: clamp(32px, 4vw, 48px); font-weight: 800; margin: 10px 0 0; line-height: 1.1;">Featured Case Studies</h2>
            </div>
            <!-- Custom Swiper Navigation Controls -->
            <div style="display: flex; gap: 12px; z-index: 100; position: relative;">
                <div class="swiper-button-prev" style="width: 50px; height: 50px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #475569; cursor: pointer; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    <i class="fas fa-chevron-left" style="pointer-events: none;"></i>
                </div>
                <div class="swiper-button-next" style="width: 50px; height: 50px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #475569; cursor: pointer; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    <i class="fas fa-chevron-right" style="pointer-events: none;"></i>
                </div>
            </div>
        </div>

        <div class="featured-slider-container" style="position: relative; overflow: hidden; width: 100%;">
            <div class="featured-slider-track" style="display: flex; transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1); width: 100%;">
                <?php foreach ($featured_projects as $index => $project): ?>
                    <div class="featured-slide" style="flex: 0 0 100%; width: 100%; box-sizing: border-box; padding: 10px;">
                        <div class="featured-card-grid" style="display: grid; grid-template-columns: 1.2fr 1fr; border-radius: 24px; overflow: hidden; background: #ffffff; box-shadow: 0 20px 40px rgba(2, 68, 66, 0.06); border: 1px solid #e2e8f0;">
                            <!-- Image side -->
                            <div style="position: relative; overflow: hidden; aspect-ratio: 16/10; background: #f1f5f9;">
                                <img src="<?= BASE_URL ?>admin/<?= htmlspecialchars($project['thumbnail']) ?>" alt="<?= htmlspecialchars($project['project_name']) ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s ease;" class="showcase-img">
                                <div style="position: absolute; inset: 0; background: linear-gradient(to right, rgba(255,255,255,0.2) 0%, transparent 100%);"></div>
                            </div>
                            <!-- Content side -->
                            <div style="padding: clamp(1.5rem, 4vw, 3.5rem); display: flex; flex-direction: column; justify-content: center; align-items: flex-start;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                                    <span style="color: #024442; font-weight: 800; font-size: 14px;"><?= str_pad($index + 1, 2, '0', STR_PAD_LEFT) ?></span>
                                    <span style="width: 24px; height: 1px; background: #cbd5e1;"></span>
                                    <span style="color: #64748b; font-size: 12px; letter-spacing: 2px; text-transform: uppercase; font-weight: 700;"><?= htmlspecialchars($project['category']) ?></span>
                                </div>
                                <h3 style="color: #024442; font-size: clamp(24px, 3vw, 36px); font-weight: 800; margin: 0 0 16px; line-height: 1.2; text-transform: capitalize;"><?= htmlspecialchars($project['project_name']) ?></h3>
                                <?php if (!empty($project['public_description'])): ?>
                                    <p style="color: #475569; font-size: 15px; line-height: 1.7; margin-bottom: 28px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;"><?= htmlspecialchars(strip_tags($project['public_description'])) ?></p>
                                <?php endif; ?>
                                <button class="main-btn" style="border-radius: 30px; padding: 12px 28px; font-weight: 700; font-size: 13px; letter-spacing: 1px; display: inline-flex; align-items: center; gap: 8px;" onclick="window.location.href='project_details.php?id=<?= $project['id'] ?>'">
                                    VIEW CASE STUDY <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Pagination dots -->
            <div class="swiper-pagination-custom" style="display: flex; justify-content: center; gap: 8px; margin-top: 30px;"></div>
        </div>
    </div>
</section>

<style>
/* Slider Transition Helpers */
.featured-slider-container { width: 100%; position: relative; overflow: hidden; }
.featured-slide { transition: transform 0.4s ease; }
.featured-slide:hover .showcase-img { transform: scale(1.05); }

.swiper-button-prev::after, .swiper-button-next::after { display: none !important; }
.swiper-button-prev, .swiper-button-next { position: static !important; width: 50px !important; height: 50px !important; background: #ffffff !important; border: 1px solid #cbd5e1 !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; color: #475569 !important; cursor: pointer !important; transition: all 0.3s !important; box-shadow: 0 2px 8px rgba(0,0,0,0.05) !important; margin: 0 !important; }
.swiper-button-prev:hover, .swiper-button-next:hover { background: #024442 !important; color: #ffffff !important; border-color: #024442 !important; }
.swiper-pagination-bullet-custom { width: 10px; height: 10px; border-radius: 50%; background: #cbd5e1; cursor: pointer; transition: all 0.3s; }
.swiper-pagination-bullet-custom.active { background: #024442; transform: scale(1.3); }

@media (max-width: 991px) {
    .featured-card-grid { grid-template-columns: 1fr !important; }
    .featured-card-grid > div:first-child { aspect-ratio: 16/9; }
}
</style>
<?php endif; ?>


    <!-- Unified Dynamic Gallery Section -->
    <section class="portfolio-gallery-section">
        <div class="container-fluid" style="max-width: 1600px;">
            <?php if (!empty($projects)): ?>
                <div class="dynamic-portfolio-grid" id="portfolioGrid">
                    <?php foreach ($projects as $project): ?>
                        <div class="portfolio-item" data-category="<?= htmlspecialchars($project['category']) ?>" onclick="window.location.href='project_details.php?id=<?= $project['id'] ?>'">
                            <img src="<?= BASE_URL ?>admin/<?= htmlspecialchars($project['thumbnail']) ?>" alt="<?= htmlspecialchars($project['project_name']) ?>">
                            <div class="portfolio-item-overlay">
                                <div class="portfolio-item-category"><?= htmlspecialchars($project['category']) ?></div>
                                <h3 class="portfolio-item-title"><?= htmlspecialchars($project['project_name']) ?></h3>
                            </div>
                            <div class="portfolio-item-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-folder-open mb-3" style="font-size: 48px; color: #cbd5e1;"></i>
                    <p style="font-size: 18px; color: #64748b;">No projects available in the gallery.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section" style="background: var(--dark); padding: 100px 0; text-align: center;">
        <div class="container">
            <h2 style="color: white; font-size: clamp(32px, 5vw, 48px); font-weight: 800; margin-bottom: 20px;">Have a Project in Mind?</h2>
            <p style="color: #94a3b8; font-size: clamp(16px, 2vw, 20px); margin-bottom: 40px; max-width: 600px; margin-left: auto; margin-right: auto;">Let's work together to bring your vision to life and create something extraordinary.</p>
            <button class="main-btn cta-btn" style="padding: 16px 40px; font-size: 16px; border-radius: 50px; font-weight: 600;" onclick="window.location.href='/contact'">Start Your Project</button>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filter functionality optimized for the CSS Grid layout
        document.addEventListener('DOMContentLoaded', () => {
            const filterBtns = document.querySelectorAll('.filter-btn');
            const projectCards = document.querySelectorAll('.portfolio-item');

            filterBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    // Remove active class from all buttons
                    filterBtns.forEach(b => b.classList.remove('active'));
                    // Add active class to clicked button
                    btn.classList.add('active');

                    const filter = btn.getAttribute('data-filter');

                    projectCards.forEach(card => {
                        // Reset animations
                        card.style.transition = 'none';
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';
                        
                        if (filter === 'all' || card.getAttribute('data-category') === filter) {
                            card.classList.remove('hidden');
                            
                            // Trigger reflow
                            void card.offsetWidth;
                            
                            // Restore transitions and animate in
                            card.style.transition = 'transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.4s ease';
                            card.style.opacity = '1';
                            card.style.transform = 'scale(1)';
                        } else {
                            // First animate out
                            card.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
                            card.style.opacity = '0';
                            card.style.transform = 'scale(0.9)';
                            
                            // Then hide completely from DOM flow after animation
                            setTimeout(() => {
                                if (!card.style.opacity || card.style.opacity === '0') {
                                    card.classList.add('hidden');
                                }
                            }, 300);
                        }
                    });
                });
            });
        });
    </script>

    <script>
    (function() {
        function initSlider() {
            const track = document.querySelector('.featured-slider-track');
            const slides = document.querySelectorAll('.featured-slide');
            const btnPrev = document.querySelector('.swiper-button-prev');
            const btnNext = document.querySelector('.swiper-button-next');
            const dotsContainer = document.querySelector('.swiper-pagination-custom');
            
            if (!slides || !slides.length || !track) {
                // Retry if DOM is still painting
                setTimeout(initSlider, 50);
                return;
            }
            
            let currentIndex = 0;
            let autoplayTimer = null;
            
            // Create pagination dots dynamically
            if (dotsContainer && slides.length > 1) {
                dotsContainer.innerHTML = '';
                slides.forEach((_, idx) => {
                    const dot = document.createElement('span');
                    dot.className = `swiper-pagination-bullet-custom ${idx === 0 ? 'active' : ''}`;
                    dot.addEventListener('click', (e) => {
                        e.preventDefault();
                        goToSlide(idx);
                        resetAutoplay();
                    });
                    dotsContainer.appendChild(dot);
                });
            }
            
            function goToSlide(index) {
                if (index < 0) index = slides.length - 1;
                if (index >= slides.length) index = 0;
                
                // Horizontal flex track translation
                const offset = -index * 100;
                track.style.transform = `translateX(${offset}%)`;
                
                // Toggle active flags
                slides.forEach((slide, idx) => {
                    if (idx === index) {
                        slide.classList.add('active');
                    } else {
                        slide.classList.remove('active');
                    }
                });
                
                // Update pagination dots
                if (dotsContainer) {
                    const dots = dotsContainer.querySelectorAll('.swiper-pagination-bullet-custom');
                    dots.forEach((dot, idx) => {
                        if (idx === index) {
                            dot.classList.add('active');
                        } else {
                            dot.classList.remove('active');
                        }
                    });
                }
                currentIndex = index;
            }
            
            function nextSlide() {
                goToSlide(currentIndex + 1);
            }
            
            function prevSlide() {
                goToSlide(currentIndex - 1);
            }
            
            if (btnPrev) {
                btnPrev.addEventListener('click', (e) => {
                    e.preventDefault();
                    prevSlide();
                    resetAutoplay();
                });
            }
            
            if (btnNext) {
                btnNext.addEventListener('click', (e) => {
                    e.preventDefault();
                    nextSlide();
                    resetAutoplay();
                });
            }
            
            function startAutoplay() {
                if (slides.length > 1) {
                    autoplayTimer = setInterval(nextSlide, 6000);
                }
            }
            
            function resetAutoplay() {
                if (autoplayTimer) {
                    clearInterval(autoplayTimer);
                    startAutoplay();
                }
            }
            
            goToSlide(0);
            startAutoplay();
        }
        
        // Execute immediately if DOM ready, otherwise wait for loading
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSlider);
        } else {
            initSlider();
        }
    })();
</script>
<?php include __DIR__ . '/../templates/footer.php'; ?>