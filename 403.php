<?php include 'header.php'; ?>

<style>
.error-page {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 75vh;
    padding: 60px 20px;
    background: #ffffff;
    text-align: center;
}

.error-inner {
    max-width: 520px;
    width: 100%;
}

/* Big 404 */
.error-code {
    font-size: clamp(90px, 20vw, 160px);
    font-weight: 800;
    line-height: 1;
    color: #024442;
    letter-spacing: -4px;
    margin-bottom: 8px;
    position: relative;
    display: inline-block;
}

.error-code::after {
    content: '';
    display: block;
    width: 60px;
    height: 4px;
    background: #024442;
    border-radius: 2px;
    margin: 12px auto 0;
}

.error-title {
    font-size: clamp(20px, 4vw, 26px);
    font-weight: 700;
    color: #111;
    margin: 24px 0 12px;
}

.error-desc {
    font-size: 16px;
    color: #666;
    line-height: 1.6;
    margin-bottom: 36px;
}

/* Buttons */
.error-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-primary {
    display: inline-block;
    padding: 13px 28px;
    background: #024442;
    color: #fff;
    font-size: 15px;
    font-weight: 600;
    border-radius: 8px;
    text-decoration: none;
    transition: background 0.25s ease, transform 0.2s ease;
}

.btn-primary:hover {
    background: #01796f;
    transform: translateY(-1px);
}

.btn-secondary {
    display: inline-block;
    padding: 13px 28px;
    background: transparent;
    color: #024442;
    font-size: 15px;
    font-weight: 600;
    border-radius: 8px;
    border: 2px solid #024442;
    text-decoration: none;
    transition: background 0.25s ease, color 0.25s ease, transform 0.2s ease;
}

.btn-secondary:hover {
    background: #024442;
    color: #fff;
    transform: translateY(-1px);
}

/* Quick links */
.error-links {
    margin-top: 48px;
    padding-top: 32px;
    border-top: 1px solid #eee;
}

.error-links p {
    font-size: 13px;
    color: #999;
    margin-bottom: 14px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 600;
}

.error-links-grid {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

.error-links-grid a {
    display: inline-block;
    padding: 7px 16px;
    background: #f4f4f4;
    color: #333;
    font-size: 13px;
    font-weight: 500;
    border-radius: 6px;
    text-decoration: none;
    transition: background 0.2s, color 0.2s;
}

.error-links-grid a:hover {
    background: #024442;
    color: #fff;
}

@media (max-width: 480px) {
    .btn-primary,
    .btn-secondary { width: 100%; text-align: center; }
    .error-actions { flex-direction: column; }
}
</style>

<main class="error-page">
    <div class="error-inner">

        <div class="error-code">404</div>

      <h2>Access Forbidden</h2>
        <p>Sorry! You do not have permission to access this page.</p>

        <div class="error-actions">
            <a href="index.php" class="btn-primary">Go to Homepage</a>
            <a href="portfolio.php" class="btn-secondary">View Portfolio</a>
        </div>

        <div class="error-links">
            <p>Or explore</p>
            <div class="error-links-grid">
                <a href="services.php">Services</a>
                <a href="portfolio.php">Portfolio</a>
                <a href="about.php">About</a>
                <a href="contact_us.php">Contact</a>
                <a href="career.php">Career</a>
            </div>
        </div>

    </div>
</main>

<?php include 'footer.php'; ?>