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
    .portfolio-hero { position: relative; background: #0f172a; padding: 140px 0 80px; text-align: center; color: white; overflow: hidden; z-index: 1; }
    .portfolio-hero::before, .portfolio-hero::after { content: ''; position: absolute; border-radius: 50%; filter: blur(80px); z-index: -1; animation: floatBlob 10s ease-in-out infinite alternate; }
    .portfolio-hero::before { width: 400px; height: 400px; background: rgba(var(--accent-rgb, 181, 255, 107), 0.15); top: -100px; left: -100px; }
    .portfolio-hero::after { width: 300px; height: 300px; background: rgba(var(--primary-rgb, 2, 68, 66), 0.4); bottom: -50px; right: 5%; animation-delay: -5s; }
    @keyframes floatBlob { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(30px, 50px) scale(1.1); } }
    .portfolio-hero h1 { font-size: clamp(40px, 6vw, 64px); font-weight: 800; letter-spacing: -1px; margin-bottom: 20px; line-height: 1.1; }
    .portfolio-hero .highlight { color: var(--accent); position: relative; display: inline-block; }
    .portfolio-hero .highlight::after { content: ''; position: absolute; bottom: 8px; left: 0; width: 100%; height: 8px; background: var(--accent); opacity: 0.3; z-index: -1; border-radius: 4px; }
    .portfolio-hero p { font-size: clamp(16px, 2vw, 20px); color: #94a3b8; max-width: 650px; margin: 0 auto 40px; line-height: 1.6; }
    
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
<section class="portfolio-section" style="padding-top: 40px;">
    <div class="featured-slider-wrapper" style="max-width: 1400px; margin: 0 auto; border-radius: 24px; box-shadow: 0 30px 60px rgba(0,0,0,0.4); overflow:hidden;">
        <div class="featured-slider" id="featuredSlider" style="aspect-ratio: 21/9; min-height: 400px; position:relative;">
            <?php foreach ($featured_projects as $index => $project): ?>
                <div class="slide <?= $index === 0 ? 'active' : '' ?>"
                     style="background-image: url('admin/<?= htmlspecialchars($project['thumbnail']) ?>');"
                     aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>">
                    <div class="slide-bg-overlay" style="background: linear-gradient(to top, rgba(15,23,42,0.95) 0%, rgba(15,23,42,0.4) 50%, transparent 100%);"></div>
                    <div class="featured-overlay" style="padding: clamp(2rem, 5vw, 4rem);">
                        <div class="featured-meta" style="gap: 1rem; margin-bottom: 1rem;">
                            <span class="featured-index" style="color: var(--accent); font-size: clamp(1rem, 2vw, 1.2rem); font-weight: 800;"><?= str_pad($index + 1, 2, '0', STR_PAD_LEFT) ?></span>
                            <span class="featured-divider" style="width: 40px; height: 2px; background: var(--accent);"></span>
                            <span class="featured-category" style="color: var(--accent); font-size: clamp(0.8rem, 1.5vw, 1rem); letter-spacing: 0.25em;"><?= htmlspecialchars($project['category']) ?></span>
                        </div>
                        <h2 class="featured-title" style="font-size: clamp(2rem, 5vw, 4rem); font-weight: 800; letter-spacing: -1px; text-transform: capitalize; margin-bottom: 1rem; max-width: 800px;"><?= htmlspecialchars($project['project_name']) ?></h2>
                        <?php if (!empty($project['public_description'])): ?>
                        <p class="featured-description" style="font-size: clamp(1rem, 2vw, 1.2rem); color: #cbd5e1; max-width: 600px; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?= htmlspecialchars($project['public_description']) ?></p>
                        <?php endif; ?>
                        
                        <button class="main-btn mt-4" style="border-radius: 30px; padding: 12px 30px; font-weight: 600; font-size: 14px; letter-spacing: 1px;" onclick="window.location.href='project_details.php?id=<?= $project['id'] ?>'">VIEW CASE STUDY <i class="fas fa-arrow-right ml-2"></i></button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Controls -->
        <button class="slider-btn slider-btn--prev" id="sliderPrev" aria-label="Previous slide" style="width: 50px; height: 50px; background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 24px; height: 24px;"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <button class="slider-btn slider-btn--next" id="sliderNext" aria-label="Next slide" style="width: 50px; height: 50px; background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 24px; height: 24px;"><polyline points="9 18 15 12 9 6"/></svg>
        </button>

        <!-- Dots -->
        <div class="slider-dots" id="sliderDots" role="tablist">
            <?php foreach ($featured_projects as $index => $project): ?>
                <button class="slider-dot <?= $index === 0 ? 'active' : '' ?>"
                        role="tab"
                        aria-label="Go to slide <?= $index + 1 ?>"
                        aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
                        data-index="<?= $index ?>"></button>
            <?php endforeach; ?>
        </div>

        <!-- Progress bar -->
        <div class="slider-progress">
            <div class="slider-progress-bar" id="sliderProgress"></div>
        </div>
    </div>
</section>
<style>
/* Inline fixes for slider structure */
.slide {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    opacity: 0;
    transition: opacity 0.8s ease, transform 0.8s ease;
    transform: scale(1.05);
    pointer-events: none;
}

.slide.active {
    opacity: 1;
    transform: scale(1);
    pointer-events: auto;
}

.slide-bg-overlay {
    position: absolute;
    inset: 0;
}

.featured-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    align-items: flex-start;
    z-index: 2;
}

.featured-meta { display: flex; align-items: center; }

.slider-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
    border-radius: 50%;
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.slider-btn:hover {
    background: var(--accent) !important;
    color: #000;
    border-color: var(--accent) !important;
}

.slider-btn--prev { left: 20px; }
.slider-btn--next { right: 20px; }

.slider-dots {
    position: absolute;
    bottom: 25px;
    right: 40px;
    display: flex;
    gap: 10px;
    z-index: 10;
}

.slider-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.5);
    background: transparent;
    cursor: pointer;
    padding: 0;
    transition: all 0.3s ease;
}

.slider-dot.active {
    background: var(--accent);
    border-color: var(--accent);
    transform: scale(1.3);
}

.slider-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: rgba(255,255,255,0.1);
    z-index: 10;
}

.slider-progress-bar {
    height: 100%;
    background: var(--accent);
    width: 0%;
    transition: width linear 5s;
}

@media (max-width: 768px) {
    .featured-slider { aspect-ratio: 1/1 !important; min-height: 400px; }
    .slider-btn { display: none; }
    .slider-dots { right: 50%; transform: translateX(50%); bottom: 15px; }
    .featured-overlay { padding: 20px !important; text-align: center; align-items: center; }
    .featured-title { font-size: 28px !important; }
    .featured-meta { justify-content: center; }
}
</style>

<?php else: ?>
<div class="container text-center py-5">
    <p class="text-muted">No featured projects available at the moment.</p>
</div>
<?php endif; ?>


    <!-- Unified Dynamic Gallery Section -->
    <section class="portfolio-gallery-section">
        <div class="container-fluid" style="max-width: 1600px;">
            <?php if (!empty($projects)): ?>
                <div class="dynamic-portfolio-grid" id="portfolioGrid">
                    <?php foreach ($projects as $project): ?>
                        <div class="portfolio-item" data-category="<?= htmlspecialchars($project['category']) ?>" onclick="window.location.href='project_details.php?id=<?= $project['id'] ?>'">
                            <img src="admin/<?= htmlspecialchars($project['thumbnail']) ?>" alt="<?= htmlspecialchars($project['project_name']) ?>">
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

        // Slider functionality
        (function () {
            const wrapper   = document.querySelector('.featured-slider-wrapper');
            if (!wrapper) return;

            const slides    = wrapper.querySelectorAll('.slide');
            const dots      = wrapper.querySelectorAll('.slider-dot');
            const prevBtn   = wrapper.querySelector('#sliderPrev');
            const nextBtn   = wrapper.querySelector('#sliderNext');
            const progressBar = wrapper.querySelector('#sliderProgress');

            if (slides.length <= 1) return;

            let current  = 0;
            let timer    = null;
            let paused   = false;
            const AUTO   = 5000;

            function goTo(index) {
                slides[current].classList.remove('active');
                slides[current].setAttribute('aria-hidden', 'true');
                if(dots[current]) {
                    dots[current].classList.remove('active');
                    dots[current].setAttribute('aria-selected', 'false');
                }

                current = (index + slides.length) % slides.length;

                slides[current].classList.add('active');
                slides[current].setAttribute('aria-hidden', 'false');
                if(dots[current]) {
                    dots[current].classList.add('active');
                    dots[current].setAttribute('aria-selected', 'true');
                }

                resetProgress();
            }

            function next() { goTo(current + 1); }
            function prev() { goTo(current - 1); }

            function resetProgress() {
                if (!progressBar) return;
                progressBar.style.transition = 'none';
                progressBar.style.width = '0%';
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        progressBar.style.transition = `width linear ${AUTO}ms`;
                        progressBar.style.width = '100%';
                    });
                });
            }

            function startAuto() {
                clearInterval(timer);
                timer = setInterval(next, AUTO);
                resetProgress();
            }

            function stopAuto() {
                clearInterval(timer);
                if (progressBar) {
                    const computed = getComputedStyle(progressBar).width;
                    progressBar.style.transition = 'none';
                    progressBar.style.width = computed;
                }
            }

            // Button listeners
            if(nextBtn) nextBtn.addEventListener('click', () => { next(); stopAuto(); if (!paused) startAuto(); });
            if(prevBtn) prevBtn.addEventListener('click', () => { prev(); stopAuto(); if (!paused) startAuto(); });

            // Dot listeners
            dots.forEach(dot => {
                dot.addEventListener('click', () => {
                    goTo(parseInt(dot.dataset.index));
                    stopAuto();
                    if (!paused) startAuto();
                });
            });

            // Pause on hover / touch focus
            wrapper.addEventListener('mouseenter', () => { paused = true; stopAuto(); });
            wrapper.addEventListener('mouseleave', () => { paused = false; startAuto(); });
            wrapper.addEventListener('focusin',    () => { paused = true; stopAuto(); });
            wrapper.addEventListener('focusout',   () => { paused = false; startAuto(); });

            // Swipe / touch support
            let touchStartX = 0;
            wrapper.addEventListener('touchstart', e => { touchStartX = e.changedTouches[0].clientX; }, { passive: true });
            wrapper.addEventListener('touchend', e => {
                const dx = e.changedTouches[0].clientX - touchStartX;
                if (Math.abs(dx) > 40) { dx < 0 ? next() : prev(); stopAuto(); startAuto(); }
            }, { passive: true });

            // Keyboard
            wrapper.setAttribute('tabindex', '0');
            wrapper.addEventListener('keydown', e => {
                if (e.key === 'ArrowRight') { next(); stopAuto(); startAuto(); }
                if (e.key === 'ArrowLeft')  { prev(); stopAuto(); startAuto(); }
            });

            // Init
            startAuto();
        })();
    </script>
<?php include __DIR__ . '/../templates/footer.php'; ?>