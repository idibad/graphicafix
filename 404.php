<?php include 'templates/header.php'; ?>

<style>
:root {
    --primary-color: #024442;
    --primary-hover: #01796f;
    --text-main: #1a1a1a;
    --text-muted: #666;
    --bg-light: #f8faf9;
}

.error-page {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 85vh;
    padding: 60px 20px;
    background: radial-gradient(circle at center, #ffffff 0%, var(--bg-light) 100%);
    text-align: center;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

.error-inner {
    max-width: 600px;
    width: 100%;
    animation: fadeIn 0.8s ease-out;
}

/* Animated 404 Header */
.error-header {
    position: relative;
    margin-bottom: 20px;
}

.error-code {
    font-size: clamp(100px, 25vw, 180px);
    font-weight: 900;
    line-height: 1;
    color: var(--primary-color);
    letter-spacing: -6px;
    margin: 0;
    display: inline-block;
    filter: drop-shadow(0 10px 20px rgba(2, 68, 66, 0.1));
    animation: float 6s ease-in-out infinite;
}

/* Search Bar Update */
.error-search {
    margin: 30px auto;
    max-width: 400px;
    position: relative;
}

.error-search input {
    width: 100%;
    padding: 14px 20px;
    border: 2px solid #eee;
    border-radius: 12px;
    font-size: 16px;
    transition: all 0.3s ease;
}

.error-search input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(2, 68, 66, 0.05);
}

.error-title {
    font-size: clamp(24px, 5vw, 32px);
    font-weight: 800;
    color: var(--text-main);
    margin: 0 0 15px;
}

.error-desc {
    font-size: 18px;
    color: var(--text-muted);
    line-height: 1.6;
    margin-bottom: 40px;
}

/* Refined Buttons */
.error-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-bottom: 50px;
}

.btn-custom {
    padding: 14px 32px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.btn-primary {
    background: var(--primary-color);
    color: #fff;
    box-shadow: 0 4px 14px rgba(2, 68, 66, 0.2);
}

.btn-primary:hover {
    background: var(--primary-hover);
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(2, 68, 66, 0.3);
}

.btn-secondary {
    background: #fff;
    color: var(--primary-color);
    border: 2px solid var(--primary-color);
}

.btn-secondary:hover {
    background: var(--primary-color);
    color: #fff;
    transform: translateY(-3px);
}

/* Minimal Links */
.error-links p {
    font-size: 14px;
    color: #999;
    margin-bottom: 20px;
}

.links-row {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}

.links-row a {
    color: var(--text-main);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    position: relative;
    transition: color 0.3s;
}

.links-row a::after {
    content: '';
    position: absolute;
    width: 0;
    height: 2px;
    bottom: -4px;
    left: 0;
    background-color: var(--primary-color);
    transition: width 0.3s;
}

.links-row a:hover {
    color: var(--primary-color);
}

.links-row a:hover::after {
    width: 100%;
}

/* Animations */
@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-15px); }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 580px) {
    .error-actions { flex-direction: column; }
    .btn-custom { width: 100%; text-align: center; }
    .links-row { gap: 15px; }
}
</style>

<main class="error-page">
    <div class="error-inner">
        <div class="error-header">
            <h1 class="error-code">404</h1>
        </div>

        <h2 class="error-title">Lost in Space?</h2>
        <p class="error-desc">We can't find the page you're looking for, but we can help you find your way back home.</p>

        <form class="error-search" action="search.php" method="GET">
            <input type="text" name="q" placeholder="Search for services, projects..." aria-label="Search">
        </form>

        <div class="error-actions">
            <a href="index.php" class="btn-custom btn-primary">Back to Home</a>
            <a href="contact_us.php" class="btn-custom btn-secondary">Contact Support</a>
        </div>

        <div class="error-links">
            <p>Popular Destinations</p>
            <div class="links-row">
                <a href="services.php">Our Services</a>
                <a href="portfolio.php">Case Studies</a>
                <a href="about.php">Our Story</a>
                <a href="career.php">Join the Team</a>
            </div>
        </div>
    </div>
</main>

<?php include 'templates/footer.php'; ?>