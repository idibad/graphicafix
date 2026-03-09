<?php
include('header.php');

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
            <h1>Our <span class="highlight" style="color: var(--primary);">Portfolio</span></h1>
            <p>Explore our creative work across branding, web design, digital marketing, and more. Each project tells a unique story of innovation and excellence.</p>
        </div>
    </section>

   
<?php if (!empty($featured_projects)): ?>
<section class="portfolio-section">
    <div class="featured-slider-wrapper">
        <div class="featured-slider" id="featuredSlider">
            <?php foreach ($featured_projects as $index => $project): ?>
                <div class="slide <?= $index === 0 ? 'active' : '' ?>"
                     style="background-image: url('admin/<?= htmlspecialchars($project['thumbnail']) ?>');"
                     aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>">
                    <div class="slide-bg-overlay"></div>
                    <div class="featured-overlay">
                        <div class="featured-meta">
                            <span class="featured-index"><?= str_pad($index + 1, 2, '0', STR_PAD_LEFT) ?></span>
                            <span class="featured-divider"></span>
                            <span class="featured-category"><?= htmlspecialchars($project['category']) ?></span>
                        </div>
                        <h2 class="featured-title"><?= htmlspecialchars($project['project_name']) ?></h2>
                        <?php if (!empty($project['public_description'])): ?>
                        <p class="featured-description"><?= htmlspecialchars($project['public_description']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Controls -->
        <button class="slider-btn slider-btn--prev" id="sliderPrev" aria-label="Previous slide">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <button class="slider-btn slider-btn--next" id="sliderNext" aria-label="Next slide">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
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

<?php else: ?>
<div class="no-projects">
    <p>No featured projects available at the moment.</p>
</div>
<?php endif; ?>


<style>

/* ── Section ───────────────────────────────────────────── */
.portfolio-section {
    width: 100%;
    padding: clamp(1.5rem, 4vw, 3rem) clamp(1rem, 4vw, 2rem);
    box-sizing: border-box;
}

/* ── Wrapper ───────────────────────────────────────────── */
.featured-slider-wrapper {
    position: relative;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    border-radius: var(--sl-radius);
    overflow: hidden;
    box-shadow: 0 24px 64px rgba(0,0,0,.35);
    background: #111;
}

/* ── Slider track ──────────────────────────────────────── */
.featured-slider {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 7;      /* desktop ratio */
    min-height: 240px;
}

@media (max-width: 600px) {
    .featured-slider { aspect-ratio: 4 / 3; }
}

/* ── Individual slides ─────────────────────────────────── */
.slide {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    opacity: 0;
    transition: opacity var(--sl-duration) ease, transform var(--sl-duration) ease;
    transform: scale(1.04);
    will-change: opacity, transform;
    pointer-events: none;
}

.slide.active {
    opacity: 1;
    transform: scale(1);
    pointer-events: auto;
}

/* subtle gradient overlay */
.slide-bg-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        to top,
        rgba(0,0,0,.85) 0%,
        rgba(0,0,0,.25) 55%,
        rgba(0,0,0,.05) 100%
    );
}

/* ── Text overlay ──────────────────────────────────────── */
.featured-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: clamp(1.25rem, 4vw, 2.5rem);
    box-sizing: border-box;
    z-index: 2;
}

.featured-meta {
    display: flex;
    align-items: center;
    gap: .6rem;
    margin-bottom: .55rem;
}

.featured-index {
    font-family: 'Courier New', monospace;
    font-size: clamp(.65rem, 1.5vw, .8rem);
    color: var(--accent);
    letter-spacing: .15em;
    font-weight: 600;
}

.featured-divider {
    display: inline-block;
    width: 28px;
    height: 1px;
    background: var(--accent);
    opacity: .7;
}

.featured-category {
    font-size: clamp(.65rem, 1.5vw, .78rem);
    font-family: 'Courier New', monospace;
    letter-spacing: .18em;
    text-transform: uppercase;
    color: rgba(255,255,255,.75);
}

.featured-title {
    font-size: clamp(1.2rem, 4vw, 2.4rem);
    font-family: Georgia, 'Times New Roman', serif;
    font-weight: 700;
    color: #fff;
    line-height: 1.15;
    margin: 0 0 .5rem;
    max-width: 680px;
    text-shadow: 0 2px 12px rgba(0,0,0,.5);
}

.featured-description {
    font-size: clamp(.8rem, 1.8vw, 1rem);
    color: rgba(255,255,255,.7);
    margin: 0;
    max-width: 520px;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* ── Nav buttons ───────────────────────────────────────── */
.slider-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
    width: clamp(36px, 5vw, 48px);
    height: clamp(36px, 5vw, 48px);
    border: 1.5px solid rgba(255,255,255,.3);
    border-radius: 50%;
    background: rgba(0,0,0,.35);
    backdrop-filter: blur(6px);
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .2s, border-color .2s, transform .2s;
    padding: 0;
}

.slider-btn svg {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

.slider-btn:hover {
    background: rgba(232,201,122,.2);
    border-color: var(--accent);
    transform: translateY(-50%) scale(1.08);
}

.slider-btn--prev { left: clamp(.75rem, 2.5vw, 1.5rem); }
.slider-btn--next { right: clamp(.75rem, 2.5vw, 1.5rem); }

/* hide buttons when only 1 slide */
.featured-slider-wrapper:has(.slide:only-child) .slider-btn,
.featured-slider-wrapper:has(.slide:only-child) .slider-dots,
.featured-slider-wrapper:has(.slide:only-child) .slider-progress {
    display: none;
}

/* ── Dots ──────────────────────────────────────────────── */
.slider-dots {
    position: absolute;
    bottom: clamp(.85rem, 2.5vw, 1.4rem);
    right: clamp(1rem, 3vw, 1.75rem);
    display: flex;
    gap: 6px;
    z-index: 10;
}

.slider-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    border: 1.5px solid rgba(255,255,255,.5);
    background: transparent;
    cursor: pointer;
    padding: 0;
    transition: background .25s, border-color .25s, transform .25s;
}

.slider-dot.active {
    background: var(--accent);
    border-color: var(--accent);
    transform: scale(1.25);
}

/* ── Progress bar ──────────────────────────────────────── */
.slider-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: rgba(255,255,255,.12);
    z-index: 10;
}

.slider-progress-bar {
    height: 100%;
    background: var(--accent);
    width: 0%;
    transition: width linear var(--sl-auto);
}

/* ── No projects fallback ──────────────────────────────── */
.no-projects {
    text-align: center;
    padding: 3rem 1rem;
    color: #888;
}
</style>

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
    <!-- Standard Grid Section -->
    <section class="portfolio-section bg-white">
        <div class="container">
            <h2 class="section-title">Recent <span>Projects</span></h2>
            <div class="row">
              <?php if (!empty($projects)): ?>
                <?php foreach ($projects as $project): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="project-card" data-category="<?= htmlspecialchars($project['category']) ?>" onclick="openProject('<?= $project['id'] ?>')">
                            <div class="project-image">
                                <img src="admin/<?= htmlspecialchars($project['thumbnail']) ?>" alt="<?= htmlspecialchars($project['project_name']) ?>">
                            </div>
                            <div class="project-info">
                                <div class="project-category"><?= htmlspecialchars($project['category']) ?></div>
                                <div class="project-title"><?= htmlspecialchars($project['project_name']) ?></div>
                                <!-- <div class="project-description"><?= htmlspecialchars($project['public_description']) ?></div> -->
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <p>No projects available at the moment.</p>
                    </div>
                <?php endif; ?>

               
                </div>
            </div>
        </div>
    </section>

    <!-- Overlay Cards Section -->
    <section class="portfolio-section">
        <div class="container">
            <h2 class="section-title">Featured <span>Work</span></h2>
            <div class="row">
                <?php if (!empty($projects)): ?>
                <?php 
                    // Limit to first 4 projects
                    $limited_projects = array_slice($projects, 0, 4); 
                ?>
                <?php foreach ($limited_projects as $project): ?>
                    <div class="col-lg-6">
                        <div class="overlay-card" data-category="<?= htmlspecialchars($project['category']) ?>" onclick="openProject('<?= $project['id'] ?>')">
                            <img src="admin/<?= htmlspecialchars($project['thumbnail']) ?>" alt="<?= htmlspecialchars($project['project_name']) ?>">
                            <div class="overlay-content">
                                <div class="project-category"><?= htmlspecialchars($project['category']) ?></div>
                                <div class="project-title"><?= htmlspecialchars($project['project_name']) ?></div>
                                <!-- <div class="project-description"><?= htmlspecialchars($project['public_description']) ?></div> -->
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <p>No projects available at the moment.</p>
                </div>
            <?php endif; ?>

                
            </div>
        </div>
    </section>

    <!-- Masonry Grid Section -->
    <section class="portfolio-section bg-white">
        <div class="container">
            <h2 class="section-title">Creative <span>Showcase</span></h2>
            <div class="masonry-grid">

                <?php if (!empty($projects)): ?>
                <?php foreach ($projects as $project): ?>
                    <div class="masonry-item" data-category="<?= htmlspecialchars($project['category']) ?>" onclick="openProject('<?= $project['id'] ?>')">
                        <img src="admin/<?= htmlspecialchars($project['thumbnail']) ?>" alt="<?= htmlspecialchars($project['project_name']) ?>">
                        <div class="masonry-overlay">
                            <div class="project-category"><?= htmlspecialchars($project['category']) ?></div>
                            <div class="project-title"><?= htmlspecialchars($project['project_name']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center">No projects available at the moment.</p>
            <?php endif; ?>

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
<div class="download-btn-cont">
    <a class="download-btn" href="path/to/your/file.pdf" download aria-label="Download PDF">
        <i class="fa-solid fa-file-arrow-down"></i>
    </a>
</div>

<style>
.download-btn-cont {
    width: 60px;
    height: 60px;
    background: var(--accent); /* Blue color for download */
    color: white;
    border-radius: 50%;
    position: fixed;
    bottom: 100px; /* adjust to not overlap WhatsApp button */
    right: 30px;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(157, 255, 0, 0.4), 0 2px 4px rgba(0,0,0,0.2);
    cursor: pointer;
    transition: all 0.3s ease;
    animation: pulse-download 2s infinite;
}

.download-btn-cont:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(140, 255, 0, 0.6), 0 4px 8px rgba(0,0,0,0.3);
}

.download-btn-cont:active {
    transform: scale(0.95);
}

.download-btn {
    color: white;
    text-decoration: none;
    font-size: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    transition: transform 0.3s ease;
}

.download-btn:hover {
    transform: rotate(15deg);
}

@keyframes pulse-download {
    0%, 100% {
        box-shadow: 0 4px 12px rgba(145, 255, 0, 0.4), 0 2px 4px rgba(211, 207, 207, 0.2);
    }
    50% {
        box-shadow: 0 4px 12px rgba(164, 220, 23, 0.4), 0 2px 4px rgba(235, 231, 231, 0.2), 0 0 0 10px rgba(0,123,255,0.1), 0 0 0 20px rgba(0,123,255,0.05);
    }
}

/* Tablet */
@media (max-width: 768px) {
    .download-btn-cont {
        width: 56px;
        height: 56px;
        bottom: 90px;
        right: 25px;
    }

    .download-btn {
        font-size: 24px;
    }
}

/* Mobile */
@media (max-width: 480px) {
    .download-btn-cont {
        width: 50px;
        height: 50px;
        bottom: 80px;
        right: 20px;
    }

    .download-btn {
        font-size: 22px;
    }
}

/* Small mobile */
@media (max-width: 360px) {
    .download-btn-cont {
        width: 46px;
        height: 46px;
        bottom: 75px;
        right: 15px;
    }

    .download-btn {
        font-size: 20px;
    }
}
</style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      const filterBtns = document.querySelectorAll('.filter-btn');

filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {

        filterBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const filter = btn.getAttribute('data-filter');

        // Filter standard grid (Bootstrap columns)
        document.querySelectorAll('.project-card').forEach(card => {
            const column = card.closest('[class*="col-"]');
            const category = card.getAttribute('data-category');

            if (filter === 'all' || category === filter) {
                column.style.display = '';
            } else {
                column.style.display = 'none';
            }
        });

        // Filter overlay cards
        document.querySelectorAll('.overlay-card').forEach(card => {
            const column = card.closest('[class*="col-"]');
            const category = card.getAttribute('data-category');

            if (filter === 'all' || category === filter) {
                column.style.display = '';
            } else {
                column.style.display = 'none';
            }
        });

        // Filter masonry items
        document.querySelectorAll('.masonry-item').forEach(item => {
            const category = item.getAttribute('data-category');

            if (filter === 'all' || category === filter) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });

    });
});

        // Project click handler
        function openProject(projectId) {
            // In a real application, this would navigate to a project detail page
            // alert(`Opening project: ${projectId}\n\nThis would redirect to: /portfolio/${projectId}`);
            // Uncomment the line below for actual navigation
            window.location.href = `project_details.php?id=${projectId}`;
        }
    </script>




<!-- slider -->
<script>
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
    const AUTO   = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--sl-auto')) || 5000;

    function goTo(index) {
        slides[current].classList.remove('active');
        slides[current].setAttribute('aria-hidden', 'true');
        dots[current]?.classList.remove('active');
        dots[current]?.setAttribute('aria-selected', 'false');

        current = (index + slides.length) % slides.length;

        slides[current].classList.add('active');
        slides[current].setAttribute('aria-hidden', 'false');
        dots[current]?.classList.add('active');
        dots[current]?.setAttribute('aria-selected', 'true');

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
    nextBtn?.addEventListener('click', () => { next(); stopAuto(); if (!paused) startAuto(); });
    prevBtn?.addEventListener('click', () => { prev(); stopAuto(); if (!paused) startAuto(); });

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
    <?php
        include('footer.php');
    ?>