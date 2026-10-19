<?php
http_response_code(404);
include 'templates/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Poppins:wght@400;500;600;700&display=swap');

:root {
    --primary-dark: #024442;
    --primary-glow: #036b68;
    --accent-lime: #b8f35a;
    --accent-hover: #a3e63b;
    --bg-obsidian: #081212;
    --glass-bg: rgba(255, 255, 255, 0.05);
    --glass-border: rgba(255, 255, 255, 0.12);
    --text-white: #ffffff;
    --text-muted: #94a3b8;
}

body {
    background-color: var(--bg-obsidian) !important;
    color: var(--text-white);
    font-family: 'Poppins', sans-serif;
    margin: 0;
    padding: 0;
    overflow-x: hidden;
}

.error-hero-section {
    position: relative;
    min-height: 90vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 80px 20px;
    background: radial-gradient(circle at 50% 30%, rgba(3, 107, 104, 0.35) 0%, rgba(2, 68, 66, 0.15) 45%, var(--bg-obsidian) 100%);
    overflow: hidden;
}

/* Animated Background Orbs & Grid */
.error-grid-bg {
    position: absolute;
    inset: 0;
    background-image: 
        linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
    background-size: 40px 40px;
    opacity: 0.7;
    z-index: 1;
}

.orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    z-index: 0;
    pointer-events: none;
    animation: floatOrb 12s ease-in-out infinite alternate;
}

.orb-1 {
    width: 380px;
    height: 380px;
    background: rgba(184, 243, 90, 0.15);
    top: 15%;
    left: 10%;
}

.orb-2 {
    width: 450px;
    height: 450px;
    background: rgba(2, 68, 66, 0.6);
    bottom: 10%;
    right: 15%;
    animation-delay: -5s;
}

@keyframes floatOrb {
    0% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(40px, -30px) scale(1.1); }
    100% { transform: translate(-20px, 40px) scale(0.95); }
}

.error-container {
    position: relative;
    z-index: 2;
    max-width: 860px;
    width: 100%;
    margin: 0 auto;
    text-align: center;
    animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Status Badge */
.error-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    background: rgba(184, 243, 90, 0.12);
    border: 1px solid rgba(184, 243, 90, 0.3);
    border-radius: 50px;
    color: var(--accent-lime);
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 25px;
    box-shadow: 0 0 25px rgba(184, 243, 90, 0.15);
}

.error-badge i {
    font-size: 1rem;
    animation: pulseGlow 2s infinite;
}

@keyframes pulseGlow {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.85); }
}

/* Glowing 404 Title */
.error-code-wrap {
    position: relative;
    margin-bottom: 15px;
}

.error-code {
    font-family: 'Outfit', sans-serif;
    font-size: clamp(110px, 22vw, 210px);
    font-weight: 900;
    line-height: 0.9;
    margin: 0;
    background: linear-gradient(135deg, #ffffff 0%, rgba(255, 255, 255, 0.7) 40%, var(--accent-lime) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    filter: drop-shadow(0 15px 35px rgba(2, 68, 66, 0.5));
    letter-spacing: -6px;
    user-select: none;
    transition: transform 0.3s ease;
}

.error-code:hover {
    transform: scale(1.02);
}

.error-title {
    font-family: 'Outfit', sans-serif;
    font-size: clamp(26px, 5vw, 42px);
    font-weight: 800;
    color: #ffffff;
    margin: 0 0 16px;
    letter-spacing: -0.5px;
}

.error-subtitle {
    font-size: clamp(16px, 2.5vw, 19px);
    color: var(--text-muted);
    line-height: 1.6;
    max-width: 620px;
    margin: 0 auto 40px;
    font-weight: 400;
}

/* Interactive Search Box */
.search-glass-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 10px;
    max-width: 580px;
    margin: 0 auto 45px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
    position: relative;
    transition: all 0.3s ease;
}

.search-glass-card:focus-within {
    border-color: rgba(184, 243, 90, 0.5);
    box-shadow: 0 20px 60px rgba(184, 243, 90, 0.15);
    transform: translateY(-2px);
}

.search-input-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 4px 8px;
}

.search-input-wrap i {
    color: var(--accent-lime);
    font-size: 1.2rem;
    margin-left: 10px;
}

.search-input {
    flex: 1;
    background: transparent;
    border: none;
    padding: 14px 10px;
    color: #ffffff;
    font-size: 1rem;
    font-family: inherit;
    outline: none;
}

.search-input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.search-btn {
    background: linear-gradient(135deg, var(--accent-lime) 0%, #92cf34 100%);
    color: var(--primary-dark);
    border: none;
    padding: 14px 26px;
    border-radius: 14px;
    font-weight: 700;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-btn:hover {
    background: var(--accent-hover);
    transform: scale(1.03);
    box-shadow: 0 8px 20px rgba(184, 243, 90, 0.3);
}

/* Instant Search Dropdown Results */
#searchDropdown {
    position: absolute;
    top: calc(100% + 12px);
    left: 0;
    right: 0;
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(25px);
    border: 1px solid rgba(184, 243, 90, 0.25);
    border-radius: 16px;
    padding: 12px;
    text-align: left;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.7);
    display: none;
    z-index: 100;
    max-height: 350px;
    overflow-y: auto;
}

.search-result-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 16px;
    border-radius: 12px;
    color: #ffffff;
    text-decoration: none;
    transition: all 0.2s ease;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-item:hover {
    background: rgba(184, 243, 90, 0.12);
    transform: translateX(5px);
}

.search-result-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: rgba(2, 68, 66, 0.8);
    color: var(--accent-lime);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.search-result-text h5 {
    margin: 0 0 3px;
    font-size: 0.98rem;
    font-weight: 600;
    color: #ffffff;
}

.search-result-text p {
    margin: 0;
    font-size: 0.82rem;
    color: var(--text-muted);
}

/* Action Buttons */
.error-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    justify-content: center;
    margin-bottom: 60px;
}

.btn-404 {
    padding: 16px 32px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.btn-lime {
    background: linear-gradient(135deg, var(--accent-lime) 0%, #95d438 100%);
    color: var(--primary-dark);
    box-shadow: 0 10px 30px rgba(184, 243, 90, 0.25);
}

.btn-lime:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 15px 35px rgba(184, 243, 90, 0.4);
    color: #000;
}

.btn-glass {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
}

.btn-glass:hover {
    background: rgba(255, 255, 255, 0.18);
    border-color: #ffffff;
    transform: translateY(-3px);
}

/* Quick Navigation Cards */
.quick-nav-section {
    max-width: 960px;
    margin: 0 auto;
}

.quick-nav-title {
    font-size: 0.88rem;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: var(--text-muted);
    margin-bottom: 25px;
    font-weight: 700;
}

.quick-nav-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 18px;
    text-align: left;
}

.nav-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 18px;
    padding: 22px 20px;
    text-decoration: none;
    color: #ffffff;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
}

.nav-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--accent-lime);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.nav-card:hover {
    background: rgba(255, 255, 255, 0.09);
    border-color: rgba(184, 243, 90, 0.4);
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
}

.nav-card:hover::before {
    opacity: 1;
}

.nav-card-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: rgba(2, 68, 66, 0.6);
    color: var(--accent-lime);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    margin-bottom: 16px;
    transition: transform 0.3s ease;
}

.nav-card:hover .nav-card-icon {
    transform: scale(1.1) rotate(5deg);
    background: var(--accent-lime);
    color: var(--primary-dark);
}

.nav-card h4 {
    margin: 0 0 6px;
    font-size: 1.05rem;
    font-weight: 700;
    color: #ffffff;
}

.nav-card p {
    margin: 0 0 16px;
    font-size: 0.84rem;
    color: var(--text-muted);
    line-height: 1.5;
}

.nav-card-arrow {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--accent-lime);
    margin-top: auto;
}

.nav-card:hover .nav-card-arrow i {
    transform: translateX(4px);
}

.nav-card-arrow i {
    transition: transform 0.2s ease;
}

@media (max-width: 640px) {
    .error-hero-section { padding: 50px 15px; }
    .search-input-wrap { flex-direction: column; padding: 10px; }
    .search-btn { width: 100%; justify-content: center; }
    .error-actions { flex-direction: column; }
    .btn-404 { width: 100%; justify-content: center; }
}
</style>

<div class="error-hero-section">
    <div class="error-grid-bg"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="error-container">
        <div class="error-badge">
            <i class="fas fa-compass"></i>
            <span>Page Not Found</span>
        </div>

        <div class="error-code-wrap">
            <h1 class="error-code">404</h1>
        </div>

        <h2 class="error-title">Oops! This Creative Canvas is Blank.</h2>
        <p class="error-subtitle">
            The page or course you are looking for has been moved, renamed, or never existed in this dimension. Let's get you back to creating masterpiece designs.
        </p>

        <!-- Interactive Search Card with Live Autocomplete -->
        <div class="search-glass-card">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="liveSearchInput" class="search-input" placeholder="Type to search courses, services, portal, contact..." autocomplete="off">
                <button type="button" class="search-btn" onclick="triggerSearch()">
                    <span>Search</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </div>
            
            <!-- Live Dropdown Results -->
            <div id="searchDropdown"></div>
        </div>

        <div class="error-actions">
            <a href="courses.php" class="btn-404 btn-lime">
                <i class="fas fa-graduation-cap"></i>
                <span>Explore Courses</span>
            </a>
            <a href="index.php" class="btn-404 btn-glass">
                <i class="fas fa-home"></i>
                <span>Return Homepage</span>
            </a>
            <a href="contact_us.php" class="btn-404 btn-glass">
                <i class="fas fa-headset"></i>
                <span>Contact Support</span>
            </a>
        </div>

        <div class="quick-nav-section">
            <div class="quick-nav-title">Popular Creative Destinations</div>
            <div class="quick-nav-grid">
                <a href="courses.php" class="nav-card">
                    <div>
                        <div class="nav-card-icon"><i class="fas fa-laptop-code"></i></div>
                        <h4>Courses & Training</h4>
                        <p>Browse our professional graphic & UI/UX design masterclasses.</p>
                    </div>
                    <div class="nav-card-arrow"><span>Browse Now</span> <i class="fas fa-arrow-right"></i></div>
                </a>

                <a href="services.php" class="nav-card">
                    <div>
                        <div class="nav-card-icon"><i class="fas fa-palette"></i></div>
                        <h4>Creative Services</h4>
                        <p>Branding, UI/UX design, web development & digital marketing.</p>
                    </div>
                    <div class="nav-card-arrow"><span>View Services</span> <i class="fas fa-arrow-right"></i></div>
                </a>

                <a href="portfolio.php" class="nav-card">
                    <div>
                        <div class="nav-card-icon"><i class="fas fa-briefcase"></i></div>
                        <h4>Our Portfolio</h4>
                        <p>Explore award-winning case studies and client projects we've built.</p>
                    </div>
                    <div class="nav-card-arrow"><span>See Our Work</span> <i class="fas fa-arrow-right"></i></div>
                </a>

                <a href="portal/login.php" class="nav-card">
                    <div>
                        <div class="nav-card-icon"><i class="fas fa-user-shield"></i></div>
                        <h4>Student Portal</h4>
                        <p>Log into your personal dashboard to access enrolled courses.</p>
                    </div>
                    <div class="nav-card-arrow"><span>Portal Login</span> <i class="fas fa-arrow-right"></i></div>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Pre-populated Quick Search Index for Instant Autocomplete
const searchIndex = [
    { title: "All Courses & Masterclasses", desc: "Browse graphic design, UI/UX & branding courses", url: "courses.php", icon: "fa-graduation-cap" },
    { title: "Creative Agency Services", desc: "Branding, web design, UI/UX & digital marketing", url: "services.php", icon: "fa-palette" },
    { title: "Our Portfolio & Case Studies", desc: "Explore our latest award-winning client projects", url: "portfolio.php", icon: "fa-briefcase" },
    { title: "Student & Client Portal Login", desc: "Access your dashboard and enrolled course lectures", url: "portal/login.php", icon: "fa-user-lock" },
    { title: "Contact Us & Support", desc: "Get 24/7 assistance or request a custom project quote", url: "contact_us.php", icon: "fa-headset" },
    { title: "Creative Insights & Blogs", desc: "Read tutorials, agency news & design trends", url: "blogs.php", icon: "fa-newspaper" },
    { title: "About Graphicafix", desc: "Learn about our creative team, mission & story", url: "about.php", icon: "fa-info-circle" },
    { title: "Careers & Job Openings", desc: "Join our growing team of creative designers & developers", url: "career.php", icon: "fa-rocket" }
];

const searchInput = document.getElementById('liveSearchInput');
const dropdown = document.getElementById('searchDropdown');

searchInput.addEventListener('input', function() {
    const query = this.value.trim().toLowerCase();
    
    if (!query) {
        dropdown.style.display = 'none';
        return;
    }

    const matches = searchIndex.filter(item => 
        item.title.toLowerCase().includes(query) || 
        item.desc.toLowerCase().includes(query)
    );

    if (matches.length > 0) {
        dropdown.innerHTML = matches.map(item => `
            <a href="${item.url}" class="search-result-item">
                <div class="search-result-icon"><i class="fas ${item.icon}"></i></div>
                <div class="search-result-text">
                    <h5>${item.title}</h5>
                    <p>${item.desc}</p>
                </div>
            </a>
        `).join('');
        dropdown.style.display = 'block';
    } else {
        dropdown.innerHTML = `
            <div style="padding: 16px; text-align: center; color: #94a3b8; font-size: 0.9rem;">
                <i class="fas fa-search" style="font-size: 1.5rem; margin-bottom: 8px; opacity: 0.5; display: block;"></i>
                No direct matches found for "${this.value}". <br>
                <a href="courses.php" style="color: var(--accent-lime); font-weight: 600; text-decoration: underline; margin-top: 6px; display: inline-block;">Browse all courses &rarr;</a>
            </div>
        `;
        dropdown.style.display = 'block';
    }
});

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-glass-card')) {
        dropdown.style.display = 'none';
    }
});

// Search button or Enter key trigger
function triggerSearch() {
    const query = searchInput.value.trim().toLowerCase();
    if (!query) {
        window.location.href = 'courses.php';
        return;
    }
    const firstMatch = searchIndex.find(item => 
        item.title.toLowerCase().includes(query) || 
        item.desc.toLowerCase().includes(query)
    );
    if (firstMatch) {
        window.location.href = firstMatch.url;
    } else {
        window.location.href = 'courses.php';
    }
}

searchInput.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        triggerSearch();
    }
});
</script>

<?php include 'templates/footer.php'; ?>