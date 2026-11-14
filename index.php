<?php
    $page_title = "Graphicafix | Digital Solutions & Services Agency";
    $meta_description = "Graphicafix delivers end-to-end digital solutions, including branding, web and software development, digital marketing, AI automation, and creative design.";
    $meta_keywords = "Digital services agency, digital solutions agency, software development company, web design and development, branding and graphic design, AI automation services, digital marketing agency";
    $canonical_url = "https://graphicafix.com/";

    include 'templates/header.php';

    if (isset($_POST['submit_contact'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $message = trim($_POST['message']);

    // Optional: basic validation
    if (!empty($name) && !empty($email) && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO contacts (name, email, message) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $message);

        if ($stmt->execute()) {
            echo "<script>alert('Message sent successfully!');window.location='contact.php';</script>";
        } else {
            echo "<script>alert('Failed to send message. Please try again.');</script>";
        }

        $stmt->close();
    } else {
        echo "<script>alert('Please fill all fields.');</script>";
    }
}

    $logoFolder = 'assets/images/client_logo/';
    $logos = glob($logoFolder . '*.png'); // get all logos


    $projectQuery = "SELECT id, project_name, thumbnail FROM projects  WHERE visibility = 'public' AND show_home = '1' ORDER BY created_at DESC";
    $result = $conn->query($projectQuery);
    $counter = 0;

// ── 1. DYNAMIC FETCH: Get the top featured/active course ──
$promo_query = $conn->query("
    SELECT * FROM courses 
    WHERE is_published = 1 
    ORDER BY is_featured DESC, created_at DESC 
    LIMIT 1
");

if ($promo_query && $promo_query->num_rows > 0):
    $promoCourse = $promo_query->fetch_assoc();
    
    // Calculate display variables
    $p_title = htmlspecialchars($promoCourse['title']);
    $p_img = !empty($promoCourse['thumbnail']) ? htmlspecialchars($promoCourse['thumbnail']) : 'https://images.unsplash.com/photo-1651608671719-fcccf959672f?q=80&w=1170&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'; // Fallback image
    $p_price = floatval($promoCourse['price']);
    $p_disc = intval($promoCourse['discount_percent'] ?? 0);
    if (!empty($promoCourse['start_date'])) {
        $start_ts = strtotime($promoCourse['start_date']);
        $p_start = ($start_ts < time()) ? 'Next Cohort Enrolling Now' : date('F j, Y', $start_ts);
    } else {
        $p_start = 'Start Immediately';
    }
    
    // The link can go to the course details or the enrollment page directly
    $p_link = "courses.php?course_id=" . $promoCourse['id'];
?>


<style>
/* ── Promo Popup Styles ── */
:root {
    --promo-primary: #024442;
    --promo-accent: #b8f35a;
}

.promo-overlay {
    position: fixed;
    inset: 0;
    background: rgba(2, 68, 66, 0.85);
    z-index: 99999;
    display: none; /* Hidden by default, revealed by JS */
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
}

.promo-overlay.active {
    display: flex;
    animation: promoFadeIn 0.3s ease forwards;
}

.promo-content {
    background: #ffffff;
    width: 100%;
    max-width: 450px;
    border-radius: 20px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    animation: promoPopIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}

/* Image Section */
.promo-img-wrapper {
    position: relative;
    width: 100%;
    height: 200px;
    background: #eee;
}

.promo-img-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.promo-close {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(0,0,0,0.4);
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    font-size: 20px;
    line-height: 1;
    color: #fff;
    cursor: pointer;
    z-index: 10;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}

.promo-close:hover {
    background: var(--promo-primary);
    transform: scale(1.1);
}

.promo-badge {
    position: absolute;
    bottom: -14px;
    left: 24px;
    background: var(--promo-accent);
    color: var(--promo-primary);
    padding: 6px 16px;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    z-index: 5;
}

/* Body Section */
.promo-body {
    padding: 35px 24px 24px;
    text-align: center;
}

.promo-title {
    color: var(--promo-primary);
    font-size: 22px;
    font-weight: 800;
    margin-bottom: 12px;
    line-height: 1.3;
}

.promo-meta {
    background: #f8fafc;
    border: 1px dashed #e2e8f0;
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 24px;
    text-align: left;
}

.promo-meta-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 600;
    color: #444;
    margin-bottom: 8px;
}

.promo-meta-item:last-child { 
    margin-bottom: 0; 
}

.promo-meta-item i {
    color: var(--promo-primary);
    font-size: 14px;
    width: 16px;
    text-align: center;
}

/* Buttons */
.promo-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.promo-btn-primary {
    background: var(--promo-primary);
    color: #fff;
    padding: 14px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
    transition: all 0.25s ease;
    border: none;
    display: block;
}

.promo-btn-primary:hover {
    background: #035b58;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(2, 68, 66, 0.25);
    color: #fff;
}

.promo-btn-link {
    background: transparent;
    border: none;
    color: #888;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: underline;
    padding: 8px;
    transition: color 0.2s ease;
}

.promo-btn-link:hover {
    color: #555;
}

/* Animations */
@keyframes promoFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes promoPopIn {
    from { opacity: 0; transform: scale(0.9) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
</style>

<div id="promoPopup" class="promo-overlay">
    <div class="promo-content">
        <div class="promo-img-wrapper">
            <button class="promo-close" onclick="closePromo()">&times;</button>
            <img src="<?= $p_img ?>" alt="<?= $p_title ?>">
            <div class="promo-badge">Now Enrolling</div>
        </div>
        
        <div class="promo-body">
            <h2 class="promo-title"><?= $p_title ?></h2>
            
            <div class="promo-meta">
                <div class="promo-meta-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span><strong>Starts:</strong> <?= $p_start ?></span>
                </div>
                <div class="promo-meta-item">
                                       <!--<span>
                    <i class="fas fa-tag"></i>

                        <?php if ($promoCourse['is_free']): ?>
                            <strong style="color:#10b981;">100% Free Course</strong>
                        <?php elseif ($p_disc > 0): ?>
                            <strong>$<?= number_format($p_price - ($p_price * $p_disc / 100)) ?></strong> 
                            <small style="text-decoration:line-through; color:#aaa;">$<?= number_format($p_price) ?></small>
                        <?php else: ?>
                            <strong>$<?= number_format($p_price) ?> USD</strong>
                        <?php endif; ?>
                    </span>-->
                </div>
                <!--<div class="promo-meta-item">-->
                <!--    <i class="fas fa-check"></i>-->
                <!--    <span><strong>7 Day Free Trial</strong></span>-->
                <!--</div>-->
                <div class="promo-meta-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span><strong>10% Extra Student Discount</strong> </span>
                </div>
                
               
            </div>

            <div class="promo-actions">
                <a href="<?= $p_link ?>" class="promo-btn-primary">View Details & Enroll</a>
                <button type="button" onclick="closePromo()" class="promo-btn-link">Maybe Later</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Only show the popup if they haven't closed it in this browsing session
    if (!sessionStorage.getItem('graphicafixPromoClosed')) {
        setTimeout(function() {
            document.getElementById('promoPopup').classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }, 2000); // Waits 2 seconds before popping up
    }
});

function closePromo() {
    const popup = document.getElementById('promoPopup');
    popup.classList.remove('active');
    document.body.style.overflow = ''; // Restore scrolling
    
    // Remember that the user closed it so it doesn't bother them again
    sessionStorage.setItem('graphicafixPromoClosed', 'true');
    
    // Slight delay to allow CSS animation to finish before hiding display
    setTimeout(() => {
        popup.style.display = 'none';
    }, 300);
}
</script>
<?php endif; ?>


<style>
/* ── GRAPHICAFIX EDITORIAL AGENCY HERO ── */
.hero-section {
    position: relative;
    background-color: #F8FAF8;
    padding: 140px 0 25px;
    overflow: hidden;
    color: #111111;
    text-align: center;
}

/* Subtle architectural drafting grid & ambient brand glow */
.hero-section::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: 
        radial-gradient(circle at 50% 20%, rgba(182, 247, 99, 0.16) 0%, rgba(2, 68, 66, 0.04) 45%, transparent 70%),
        linear-gradient(to right, rgba(2, 68, 66, 0.025) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(2, 68, 66, 0.025) 1px, transparent 1px);
    background-size: 100% 100%, 48px 48px, 48px 48px;
    pointer-events: none;
    z-index: 1;
}

.hero-container {
    position: relative;
    z-index: 2;
    max-width: 1080px;
    margin: 0 auto;
    padding: 0 24px;
}

/* Floating 4-Point Sparkles (matching reference image) */
.hero-sparkle {
    position: absolute;
    pointer-events: none;
    z-index: 2;
    animation: sparkleFloat 4s ease-in-out infinite alternate;
}
.sparkle-left {
    top: 40px;
    left: 4%;
    width: 36px;
    height: 36px;
    fill: #024442;
    opacity: 0.85;
}
.sparkle-right {
    top: 52%;
    right: 5%;
    width: 38px;
    height: 38px;
    fill: #024442;
    opacity: 0.9;
    animation-delay: -2s;
}

@keyframes sparkleFloat {
    0% { transform: translateY(0) rotate(0deg) scale(0.95); }
    100% { transform: translateY(-12px) rotate(15deg) scale(1.05); }
}

/* Centered Copy */
.hero-copy-centered {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    margin: 0 auto;
    animation: heroTextReveal 0.75s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

/* Status / Pill Badge */
.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 18px;
    background: #ffffff;
    border: 1px solid rgba(2, 68, 66, 0.12);
    border-radius: 999px;
    box-shadow: 0 2px 10px rgba(2, 68, 66, 0.04);
    font-size: 13.5px;
    font-weight: 600;
    color: #024442;
    margin-bottom: 24px;
}

.hero-badge-pulse {
    width: 8px;
    height: 8px;
    background: #10b981;
    border-radius: 50%;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: pulseDot 2s infinite;
}

@keyframes pulseDot {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* Main Heading */
.hero-title {
    font-size: clamp(2.75rem, 6.2vw, 5.2rem);
    font-weight: 850;
    line-height: 1.08;
    letter-spacing: -0.04em;
    color: #111111;
    margin: 0 0 16px 0;
    max-width: 900px;
}

.hero-support-title {
    font-size: clamp(1.15rem, 2.2vw, 1.65rem);
    font-weight: 700;
    color: #024442;
    margin: 0 0 16px 0;
    letter-spacing: -0.02em;
    max-width: 800px;
}

.hero-accent {
    position: relative;
    display: inline-block;
    color: #024442;
    z-index: 1;
}

.hero-accent::after {
    content: '';
    position: absolute;
    left: -4%;
    bottom: 0.06em;
    width: 108%;
    height: 0.34em;
    background: #B6F763;
    z-index: -1;
    border-radius: 4px;
}

/* Subtext */
.hero-subtext {
    font-size: clamp(1.05rem, 1.35vw, 1.25rem);
    color: #4a5568;
    line-height: 1.65;
    max-width: 600px;
    margin: 0 auto 34px auto;
    letter-spacing: -0.01em;
}

/* Actions */
.hero-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.hero-btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: #024442;
    color: #ffffff !important;
    font-size: 15.5px;
    font-weight: 600;
    padding: 16px 36px;
    border-radius: 999px;
    text-decoration: none !important;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1px solid #024442;
    box-shadow: 0 6px 20px rgba(2, 68, 66, 0.18);
}

.hero-btn-primary:hover {
    background: #035a57;
    transform: translateY(-2px);
    box-shadow: 0 10px 28px rgba(2, 68, 66, 0.28);
}

.hero-btn-primary i {
    color: #B6F763;
    font-size: 13px;
    transition: transform 0.2s ease;
}

.hero-btn-primary:hover i {
    transform: translateX(3px);
}

.hero-btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: #ffffff;
    color: #024442 !important;
    font-size: 15.5px;
    font-weight: 600;
    padding: 15px 32px;
    border-radius: 999px;
    text-decoration: none !important;
    transition: all 0.25s ease;
    border: 1.5px solid rgba(2, 68, 66, 0.25);
    box-shadow: 0 2px 8px rgba(2, 68, 66, 0.04);
}

.hero-btn-secondary:hover {
    background: rgba(2, 68, 66, 0.05);
    border-color: #024442;
    transform: translateY(-2px);
}

/* ── STATIC DIAGONAL STRIPES WITH SCROLL-DRIVEN TEXT ── */
.hero-stripes-container {
    position: relative;
    width: 100%;
    margin-top: 45px;
    padding: 38px 0 28px;
    overflow: hidden;
    user-select: none;
    -webkit-user-select: none;
}

/* The stripe containers remain 100% static at all times */
.hero-stripe {
    width: 130vw;
    margin-left: -15vw;
    padding: 15px 0;
    white-space: nowrap;
    overflow: hidden;
    display: flex;
    align-items: center;
    position: relative;
    transform-origin: center center;
    pointer-events: auto;
}

/* Stripe 1: Deep Teal (Static fixed rotation) */
.stripe-teal {
    background: #024442;
    color: #ffffff;
    transform: rotate(-2.6deg) !important;
    box-shadow: 0 10px 30px rgba(2, 68, 66, 0.22);
    z-index: 2;
    margin-bottom: -18px; /* Overlap with second stripe */
}

/* Stripe 2: Vibrant Electric Lime (Static fixed rotation) */
.stripe-lime {
    background: #B6F763;
    color: #024442;
    transform: rotate(2.2deg) !important;
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.12);
    z-index: 3;
}

/* Scroll track: text is static when idle, moves ONLY on scroll */
.stripe-scroll-track {
    display: flex;
    width: max-content;
    will-change: transform;
    animation: none !important; /* No continuous auto-marquee */
}

.stripe-unit {
    display: flex;
    align-items: center;
    gap: 34px;
    padding-right: 34px;
    font-size: clamp(1.1rem, 1.8vw, 1.45rem);
    font-weight: 850;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

/* 4-point Sparkle Star Divider */
.stripe-star {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    fill: currentColor;
    transition: transform 0.3s ease;
}
.stripe-teal .stripe-star {
    fill: #B6F763;
}
.stripe-lime .stripe-star {
    fill: #024442;
}

/* Outlined typography matching reference */
.text-outline-light {
    color: transparent !important;
    -webkit-text-stroke: 1.5px rgba(255, 255, 255, 0.85);
    transition: all 0.25s ease;
}
.text-outline-dark {
    color: transparent !important;
    -webkit-text-stroke: 1.5px #024442;
    transition: all 0.25s ease;
}

.stripe-unit span {
    transition: all 0.25s ease;
    cursor: default;
}
.stripe-unit span:hover {
    color: #ffffff !important;
    -webkit-text-stroke: 0 !important;
    text-shadow: 0 0 14px rgba(255, 255, 255, 0.7);
}
.stripe-lime .stripe-unit span:hover {
    color: #024442 !important;
}

/* ── UPGRADED CLIENT LOGOS SECTION ── */
.trusted-by {
    position: relative;
    background: #ffffff;
    padding: 50px 0 45px;
    width: 100%;
    border-top: 1px solid rgba(2, 68, 66, 0.08);
    border-bottom: 1px solid rgba(2, 68, 66, 0.08);
    overflow: hidden;
}

.trusted-header {
    margin-bottom: 28px;
}

.trusted-label {
    display: inline-flex;
    align-items: center;
    gap: 16px;
    font-size: 12.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: #024442;
    opacity: 0.75;
    margin: 0;
}

.trusted-line {
    width: 40px;
    height: 1px;
    background: rgba(2, 68, 66, 0.2);
    display: inline-block;
}

.logo-slider {
    position: relative;
    overflow: hidden;
    width: 100%;
    padding: 6px 0;
}

/* Luxury edge gradient dissolve masks */
.logo-fade {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 160px;
    z-index: 4;
    pointer-events: none;
}
.logo-fade-left {
    left: 0;
    background: linear-gradient(to right, #ffffff 15%, rgba(255, 255, 255, 0) 100%);
}
.logo-fade-right {
    right: 0;
    background: linear-gradient(to left, #ffffff 15%, rgba(255, 255, 255, 0) 100%);
}

.logo-track {
    display: flex;
    align-items: center;
    gap: 50px;
    width: max-content;
    will-change: transform;
    animation: clientLogoGlide 30s linear infinite;
}

.logo-slider:hover .logo-track {
    animation-play-state: paused;
}

.client-logo-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 52px;
    min-width: 140px;
    padding: 4px 14px;
    border-radius: 12px;
    transition: all 0.25s ease;
}

.client-logo-wrapper:hover {
    background: rgba(2, 68, 66, 0.03);
}

.client-logo {
    max-height: 42px;
    width: auto;
    max-width: 130px;
    object-fit: contain;
    filter: grayscale(100%);
    opacity: 0.55;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    display: block;
}

.client-logo:hover,
.client-logo-wrapper:hover .client-logo {
    filter: grayscale(0%);
    opacity: 1;
    transform: scale(1.08);
}

@keyframes clientLogoGlide {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}

/* ── ANIMATIONS ── */
@keyframes heroTextReveal {
    from {
        opacity: 0;
        transform: translateY(22px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ── ACCESSIBILITY ── */
@media (prefers-reduced-motion: reduce) {
    .hero-copy-centered,
    .hero-sparkle,
    .hero-badge-pulse,
    .logo-track {
        animation: none !important;
    }
    .hero-btn-primary,
    .hero-btn-secondary {
        transition: none !important;
    }
}

/* ── RESPONSIVE ── */
@media (max-width: 991px) {
    .hero-section {
        padding: 120px 0 25px;
    }
    .hero-title {
        font-size: clamp(2.5rem, 6.8vw, 3.8rem);
    }
    .hero-subtext {
        font-size: 1.05rem;
        margin-bottom: 28px;
    }
    .sparkle-left {
        top: 20px;
        left: 2%;
        width: 28px;
        height: 28px;
    }
    .sparkle-right {
        right: 2%;
        width: 30px;
        height: 30px;
    }
    .stripe-unit {
        gap: 26px;
        padding-right: 26px;
        font-size: 1.15rem;
    }
    .stripe-star {
        width: 17px;
        height: 17px;
    }
    .trusted-by {
        padding: 40px 0 35px;
    }
    .logo-fade {
        width: 80px;
    }
    .logo-track {
        gap: 36px;
    }
    .client-logo {
        max-height: 36px;
        max-width: 110px;
    }
}

@media (max-width: 576px) {
    .hero-section {
        padding: 100px 0 20px;
    }
    .hero-badge {
        font-size: 12px;
        padding: 6px 14px;
        margin-bottom: 18px;
    }
    .hero-title {
        font-size: clamp(2.15rem, 9.5vw, 2.85rem);
        line-height: 1.12;
        margin-bottom: 16px;
    }
    .hero-subtext {
        font-size: 0.98rem;
        line-height: 1.55;
        margin-bottom: 24px;
    }
    .hero-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
        width: 100%;
        max-width: 340px;
        margin: 0 auto 15px auto;
    }
    .hero-btn-primary,
    .hero-btn-secondary {
        width: 100%;
        padding: 15px 24px;
        font-size: 15px;
    }
    .sparkle-left,
    .sparkle-right {
        display: none;
    }
    .hero-stripes-container {
        margin-top: 30px;
        padding: 25px 0 20px;
    }
    .stripe-unit {
        gap: 20px;
        padding-right: 20px;
        font-size: 0.98rem;
    }
    .stripe-star {
        width: 15px;
        height: 15px;
    }
    .hero-stripe {
        padding: 11px 0;
    }
    .stripe-teal {
        margin-bottom: -12px;
    }
    .trusted-label {
        font-size: 11px;
        gap: 10px;
    }
    .trusted-line {
        width: 20px;
    }
}
</style>

<section class="hero-section" id="hero">
    <!-- Floating 4-point design sparkles matching reference image -->
    <div class="hero-sparkle sparkle-left" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5Z"/></svg>
    </div>
    <div class="hero-sparkle sparkle-right" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5Z"/></svg>
    </div>

    <div class="container hero-container">
        <div class="hero-copy-centered">
            <!-- Primary H1 -->
            <h1 class="hero-title">
                Don't worry,<br>
                we will fix <span class="hero-accent">IT.</span>
            </h1>

            <!-- Supporting Heading -->
            <h2 class="hero-support-title">Your End-to-End Digital Solutions Partner</h2>
            
            <!-- Supporting Description -->
            <p class="hero-subtext">
                From branding and creative design to websites, custom software, digital marketing, and AI automation, Graphicafix brings everything together to help businesses build, operate, and grow.
            </p>
            
            <div class="hero-actions">
                <a href="#" data-bs-toggle="modal" data-bs-target="#projectRequestModal" class="hero-btn-primary">
                    <span>Start a Project</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
                
                <a href="<?= BASE_URL ?>portfolio" class="hero-btn-secondary">
                    <span>Explore Work</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Diagonal Ribbon Stripes (Static Stripes, Text Scrolls Directly With Page Scroll) -->
    <div class="hero-stripes-container" id="heroStripesContainer">
        <!-- Stripe 1: Deep Forest Teal Ribbon (Static Container) -->
        <div class="hero-stripe stripe-teal" id="stripeTeal">
            <div class="stripe-scroll-track" id="trackTeal">
                <?php for ($s = 0; $s < 4; $s++): ?>
                <div class="stripe-unit" <?= ($s > 0) ? 'aria-hidden="true"' : ''; ?>>
                    <span>BRAND IDENTITY</span>
                    <svg class="stripe-star" viewBox="0 0 24 24"><path d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5Z"/></svg>
                    <span class="text-outline-light">VISUAL STORYTELLING</span>
                    <svg class="stripe-star" viewBox="0 0 24 24"><path d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5Z"/></svg>
                    <span>UI/UX DESIGN</span>
                    <svg class="stripe-star" viewBox="0 0 24 24"><path d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5Z"/></svg>
                    <span class="text-outline-light">WEB DEVELOPMENT</span>
                    <svg class="stripe-star" viewBox="0 0 24 24"><path d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5Z"/></svg>
                    <span>WEBSITE MANAGEMENT</span>
                    <svg class="stripe-star" viewBox="0 0 24 24"><path d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5Z"/></svg>
                    <span class="text-outline-light">PACKAGING &amp; PRINT</span>
                    <svg class="stripe-star" viewBox="0 0 24 24"><path d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5Z"/></svg>
                    <span>CREATIVE DIRECTION</span>
                    <svg class="stripe-star" viewBox="0 0 24 24"><path d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5Z"/></svg>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Stripe 2: Vibrant Electric Lime Ribbon (Static Container) -->
        <div class="hero-stripe stripe-lime" id="stripeLime">
            <div class="stripe-scroll-track" id="trackLime">
                <?php for ($s = 0; $s < 4; $s++): ?>
                <div class="stripe-unit" <?= ($s > 0) ? 'aria-hidden="true"' : ''; ?>>
                    <span>WE WILL FIX IT</span>
                    <svg class="stripe-star" viewBox="0 0 24 24"><path d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5Z"/></svg>
                    <span class="text-outline-dark">GRAPHICAFIX</span>
                    <svg class="stripe-star" viewBox="0 0 24 24"><path d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5Z"/></svg>
                    <span>PIXEL PERFECT</span>
                    <svg class="stripe-star" viewBox="0 0 24 24"><path d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5Z"/></svg>
                    <span class="text-outline-dark">DIGITAL CRAFT</span>
                    <svg class="stripe-star" viewBox="0 0 24 24"><path d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5Z"/></svg>
                    <span>BESPOKE DESIGN STUDIO</span>
                    <svg class="stripe-star" viewBox="0 0 24 24"><path d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5Z"/></svg>
                    <span class="text-outline-dark">MOVE BUSINESS FORWARD</span>
                    <svg class="stripe-star" viewBox="0 0 24 24"><path d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5Z"/></svg>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</section>

<!-- Scroll-Linked Parallax Script: Buttery-Smooth Lerp Physics -->
<script>
(function() {
    const trackTeal = document.getElementById('trackTeal');
    const trackLime = document.getElementById('trackLime');
    if (!trackTeal || !trackLime) return;

    let targetScrollY = window.pageYOffset || document.documentElement.scrollTop;
    let currentScrollY = targetScrollY;
    let isTicking = false;

    // Cache repeating unit widths to eliminate layout thrashing
    let widthTeal = 1350;
    let widthLime = 1350;

    function measureWidths() {
        const unitTeal = trackTeal.querySelector('.stripe-unit');
        const unitLime = trackLime.querySelector('.stripe-unit');
        if (unitTeal && unitTeal.offsetWidth > 100) widthTeal = unitTeal.offsetWidth;
        if (unitLime && unitLime.offsetWidth > 100) widthLime = unitLime.offsetWidth;
    }
    measureWidths();
    window.addEventListener('resize', measureWidths, { passive: true });
    window.addEventListener('load', measureWidths, { passive: true });
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(measureWidths);
    }

    function renderScroll() {
        // Smooth linear interpolation (lerp) for liquid ease-out inertia
        const diff = targetScrollY - currentScrollY;
        currentScrollY += diff * 0.085;

        // Modulo calculations for seamless wrapping
        const offsetTeal = - ((currentScrollY * 0.85) % widthTeal);
        const offsetLime = -widthLime + ((currentScrollY * 0.85) % widthLime);

        trackTeal.style.transform = `translate3d(${offsetTeal.toFixed(2)}px, 0, 0)`;
        trackLime.style.transform = `translate3d(${offsetLime.toFixed(2)}px, 0, 0)`;

        // Continue RAF loop while there is momentum
        if (Math.abs(diff) > 0.08) {
            requestAnimationFrame(renderScroll);
        } else {
            currentScrollY = targetScrollY;
            isTicking = false;
        }
    }

    window.addEventListener('scroll', function() {
        targetScrollY = window.pageYOffset || document.documentElement.scrollTop;
        if (!isTicking) {
            isTicking = true;
            requestAnimationFrame(renderScroll);
        }
    }, { passive: true });

    // Initial render
    renderScroll();
})();
</script>

<!-- Upgraded Client Logos Section -->
<section class="trusted-by" id="trustedBy">
    <div class="container">
        <div class="trusted-header text-center">
            <p class="trusted-label">
                <span class="trusted-line"></span>
                <span>TRUSTED BY INDUSTRY LEADERS &amp; GROWING BRANDS</span>
                <span class="trusted-line"></span>
            </p>
        </div>
    </div>
    
    <div class="logo-slider">
        <!-- Edge Gradient Dissolve Masks -->
        <div class="logo-fade logo-fade-left"></div>
        <div class="logo-fade logo-fade-right"></div>

        <div class="logo-track">
           <?php
               // Loop 4 times for silky smooth infinite loop on all display sizes
            for ($repeat = 0; $repeat < 4; $repeat++) {
                foreach ($logos as $index => $logoPath) {
                    echo '<div class="client-logo-wrapper">';
                    echo '<img src="' . htmlspecialchars($logoPath) . '" alt="Client partner brand logo" class="client-logo" loading="lazy">';
                    echo '</div>';
                }
            }
            ?>
        </div>
    </div>
</section>

<!-- ── UPGRADED CREATIVE SOLUTIONS SECTION ── -->
<style>
.services-clean-section {
    background-color: #F8FAF8;
    padding: 95px 0 90px;
    position: relative;
    overflow: hidden;
}

.services-clean-header {
    margin-bottom: 50px;
}

.services-pill-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 16px;
    background: #ffffff;
    border: 1px solid rgba(2, 68, 66, 0.12);
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.12em;
    color: #024442;
    margin-bottom: 16px;
    box-shadow: 0 2px 8px rgba(2, 68, 66, 0.04);
}

.services-pill-badge .pill-dot {
    width: 7px;
    height: 7px;
    background: #B6F763;
    border-radius: 50%;
    box-shadow: 0 0 6px rgba(182, 247, 99, 0.9);
}

.services-clean-title {
    font-size: clamp(2.3rem, 4.4vw, 3.4rem);
    font-weight: 850;
    color: #111111;
    letter-spacing: -0.035em;
    margin-bottom: 14px;
    line-height: 1.15;
}

.services-clean-accent {
    position: relative;
    display: inline-block;
    color: #024442;
    z-index: 1;
}

.services-clean-accent::after {
    content: '';
    position: absolute;
    left: -3%;
    bottom: 0.08em;
    width: 106%;
    height: 0.3em;
    background: #B6F763;
    z-index: -1;
    border-radius: 3px;
}

.services-clean-subtext {
    font-size: clamp(1rem, 1.25vw, 1.14rem);
    color: #64748b;
    max-width: 560px;
    margin: 0 auto;
    line-height: 1.6;
}

/* Service Clean Cards */
.service-clean-card {
    position: relative;
    background: #ffffff;
    border: 1px solid rgba(2, 68, 66, 0.09);
    border-radius: 22px;
    padding: 34px 26px 30px;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    text-align: left;
    box-shadow: 0 4px 20px -2px rgba(2, 68, 66, 0.04);
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s ease, border-color 0.3s ease;
    overflow: hidden;
}

.service-clean-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3.5px;
    background: #B6F763;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.service-clean-card:hover {
    transform: translateY(-8px);
    border-color: rgba(2, 68, 66, 0.22);
    box-shadow: 0 20px 42px -10px rgba(2, 68, 66, 0.12);
}

.service-clean-card:hover::before {
    opacity: 1;
}

.card-number {
    position: absolute;
    top: 24px;
    right: 24px;
    font-family: monospace;
    font-size: 13px;
    font-weight: 700;
    color: rgba(2, 68, 66, 0.25);
    letter-spacing: 1px;
}

.card-icon-box {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: rgba(2, 68, 66, 0.05);
    color: #024442;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    margin-bottom: 22px;
    transition: all 0.3s ease;
}

.service-clean-card:hover .card-icon-box {
    background: #024442;
    color: #B6F763;
    transform: scale(1.06);
}

.card-body-content {
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}

.card-title {
    font-size: 1.28rem;
    font-weight: 750;
    color: #111111;
    margin-bottom: 12px;
    letter-spacing: -0.02em;
}

.card-desc {
    font-size: 0.94rem;
    color: #4b5563;
    line-height: 1.62;
    margin-bottom: 24px;
    flex-grow: 1;
}

.card-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #024442 !important;
    text-decoration: none !important;
    transition: gap 0.25s ease, color 0.25s ease;
    margin-top: auto;
}

.card-link i {
    font-size: 12px;
    transition: transform 0.25s ease;
}

.service-clean-card:hover .card-link {
    color: #035a57 !important;
    gap: 12px;
}

.service-clean-card:hover .card-link i {
    transform: translateX(4px);
}

/* Explore all services button */
.services-btn-explore {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: #024442;
    color: #ffffff !important;
    font-size: 15px;
    font-weight: 600;
    padding: 15px 36px;
    border-radius: 999px;
    text-decoration: none !important;
    box-shadow: 0 4px 18px rgba(2, 68, 66, 0.16);
    transition: all 0.25s ease;
    border: 1px solid #024442;
}

.services-btn-explore:hover {
    background: #035a57;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(2, 68, 66, 0.24);
}

.services-btn-explore i {
    color: #B6F763;
    font-size: 13px;
    transition: transform 0.2s ease;
}

.services-btn-explore:hover i {
    transform: translateX(3px);
}

@media (max-width: 768px) {
    .services-clean-section {
        padding: 65px 0 60px;
    }
    .service-clean-card {
        padding: 26px 20px 24px;
    }
    .card-title {
        font-size: 1.2rem;
    }
}
</style>

<section class="services-clean-section text-center" id="services">
    <div class="container">
        <!-- Section Header -->
        <div class="services-clean-header" data-aos="fade-up">
            <div class="services-pill-badge">
                <span class="pill-dot"></span>
                <span>WHAT WE DO</span>
            </div>
            <h2 class="services-clean-title">Our Digital <span class="services-clean-accent">Services.</span></h2>
            <p class="services-clean-subtext">Complete digital solutions for modern businesses — from branding and custom websites to scalable software, performance marketing, and AI automation.</p>
        </div>

        <!-- Clean Service Cards Grid (Expanded Core Services) -->
        <div class="row g-4" data-aos="fade-up">
            <!-- Card 1: Branding & Creative Design -->
            <div class="col-lg-4 col-md-6">
                <div class="service-clean-card">
                    <span class="card-number">01</span>
                    <div class="card-icon-box">
                        <i class="fas fa-palette"></i>
                    </div>
                    <div class="card-body-content">
                        <h3 class="card-title">Branding &amp; Creative Design</h3>
                        <p class="card-desc">Bespoke brand strategy, visual identities, logos, style guidelines, and marketing collaterals that distinguish your business.</p>
                        <a href="<?= BASE_URL ?>services#branding-creative-design" class="card-link">
                            <span>Explore Service</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Card 2: Website Design & Development -->
            <div class="col-lg-4 col-md-6">
                <div class="service-clean-card">
                    <span class="card-number">02</span>
                    <div class="card-icon-box">
                        <i class="fas fa-laptop-code"></i>
                    </div>
                    <div class="card-body-content">
                        <h3 class="card-title">Website Design &amp; Development</h3>
                        <p class="card-desc">High-speed, conversion-focused, and SEO-optimized responsive websites built on modern architectures that drive sustainable growth.</p>
                        <a href="<?= BASE_URL ?>services#website-design-development" class="card-link">
                            <span>Explore Service</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Card 3: Custom Software & SaaS Development -->
            <div class="col-lg-4 col-md-6">
                <div class="service-clean-card">
                    <span class="card-number">03</span>
                    <div class="card-icon-box">
                        <i class="fas fa-cubes"></i>
                    </div>
                    <div class="card-body-content">
                        <h3 class="card-title">Custom Software &amp; SaaS</h3>
                        <p class="card-desc">End-to-end custom software development, cloud web applications, CRM/ERP platforms, and scalable multi-tenant SaaS products.</p>
                        <a href="<?= BASE_URL ?>services#custom-software-saas" class="card-link">
                            <span>Explore Service</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Card 4: Digital Marketing & Content -->
            <div class="col-lg-4 col-md-6">
                <div class="service-clean-card">
                    <span class="card-number">04</span>
                    <div class="card-icon-box">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="card-body-content">
                        <h3 class="card-title">Digital Marketing &amp; Content</h3>
                        <p class="card-desc">Targeted search engine optimization (SEO), paid ads, brand storytelling, and strategic content pipelines that convert prospects into clients.</p>
                        <a href="<?= BASE_URL ?>services#digital-marketing-growth" class="card-link">
                            <span>Explore Service</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Card 5: AI & Business Automation -->
            <div class="col-lg-4 col-md-6">
                <div class="service-clean-card">
                    <span class="card-number">05</span>
                    <div class="card-icon-box">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="card-body-content">
                        <h3 class="card-title">AI &amp; Business Automation</h3>
                        <p class="card-desc">Custom AI assistants, intelligent workflow automations, API integrations, and operational tools that streamline work and eliminate bottlenecks.</p>
                        <a href="<?= BASE_URL ?>services#ai-business-automation" class="card-link">
                            <span>Explore Service</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Card 6: Mobile App Development -->
            <div class="col-lg-4 col-md-6">
                <div class="service-clean-card">
                    <span class="card-number">06</span>
                    <div class="card-icon-box">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div class="card-body-content">
                        <h3 class="card-title">Mobile App Development</h3>
                        <p class="card-desc">Intuitive, cross-platform and native iOS and Android applications engineered for fluid user journeys and high customer retention.</p>
                        <a href="<?= BASE_URL ?>services#mobile-app-development" class="card-link">
                            <span>Explore Service</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Action Button -->
            <div class="col-12 text-center mt-5">
                <a href="<?= BASE_URL ?>services" class="services-btn-explore">
                    <span>Explore All Services &amp; Packages</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</section>
        <div class="work-cards" id="portfolio">
            <div class="container">        
                <div class="row g-4 align-items-stretch">
                        <div class="col-lg-3 col-md-6">
                            <div class="work-card-intro">
                                <span class="work-intro-badge">
                                    <i class="fas fa-star" style="color: #024442; font-size: 11px;"></i> Featured Work
                                </span>
                                <h3 class="work-intro-title">
                                    Creative Portfolio
                                </h3>
                                <p class="work-intro-desc">
                                    Explore some of the high-impact branding, custom websites, and digital solutions we have delivered for growing businesses and organizations.
                                </p>
                                <a href="#" class="work-intro-btn" data-bs-toggle="modal" data-bs-target="#projectRequestModal">
                                    <span>Request Project</span>
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                        <?php
                        while ($project = $result->fetch_assoc()): 
                            $p_name = htmlspecialchars($project['project_name']);
                            $p_alt = $p_name . " by Graphicafix digital solutions agency";
                            if (stripos($p_name, 'Qaswa') !== false) {
                                $p_alt = "Qaswa Elevators brand identity and logo design by Graphicafix";
                            } elseif (stripos($p_name, 'Zarmall') !== false) {
                                $p_alt = "Zarmall clothing brand identity designed by Graphicafix";
                            } elseif (stripos($p_name, 'Emanon') !== false) {
                                $p_alt = "Emanon Institute website and branding project by Graphicafix";
                            } elseif (stripos($p_name, 'Islamabad Elevators') !== false) {
                                $p_alt = "Islamabad Elevators visual identity project by Graphicafix";
                            } elseif (stripos($p_name, 'Bab-ul-Ilm') !== false) {
                                $p_alt = "Bab-ul-Ilm digital platform project by Graphicafix";
                            } elseif (stripos($p_name, 'Glowing Star') !== false) {
                                $p_alt = "Glowing Star Windows Design Project by Graphicafix";
                            } elseif (stripos($p_name, 'KBRose') !== false) {
                                $p_alt = "KBRose Foundation School branding project by Graphicafix";
                            }
                        ?>
                            <div class="col-lg-3 col-md-6">
                                <a href="project_details.php?id=<?= intval($project['id']) ?>" class="work-card-link" aria-label="<?= $p_name ?>">
                                    <div class="work-card-single">
                                        <img src="<?= !empty($project['thumbnail']) ? 'admin/' . htmlspecialchars($project['thumbnail']) : 'assets/images/placeholder.png' ?>" alt="<?= htmlspecialchars($p_alt) ?>" loading="lazy">
                                        <div class="work-card-overlay">
                                            <div class="work-card-overlay-inner">
                                                <div class="work-card-meta">
                                                    <span class="work-card-pill">Case Study</span>
                                                    <span class="work-card-icon"><i class="fas fa-arrow-right"></i></span>
                                                </div>
                                                <h4 class="work-card-title"><?= $p_name ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            
                        <?php $counter++; 
                        
                        if ($counter >= 7) break; 
                    endwhile; ?>
                        
                    </div>
                </div>
            </div>
            <!-- Infinite Marquee Text Section (Scroll-Linked Animation) -->
            <div class="scroll-text-wrapper" id="marqueeWrapper">
                <div class="scroll-text" id="marqueeScrollTrack">
                    <?php for ($m = 0; $m < 5; $m++): ?>
                    <span class="line" <?= ($m > 0) ? 'aria-hidden="true"' : ''; ?>>
                        <span class="w1">FIX</span>
                        <span class="w2">ENHANCE</span>
                        <span class="w3">AND</span>
                        <span class="w4">ELEVATE</span>
                        <span class="w5">YOUR</span>
                        <span class="w6">BRANDS</span>
                        <svg class="scroll-star" viewBox="0 0 24 24"><path d="M12 0L14.5 9.5L24 12L14.5 14.5L12 24L9.5 14.5L0 12L9.5 9.5Z"/></svg>
                    </span>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Scroll-Linked Script for Post-Portfolio Marquee -->
            <script>
            (function() {
                const scrollTrack = document.getElementById('marqueeScrollTrack');
                if (!scrollTrack) return;

                let targetScrollY = window.pageYOffset || document.documentElement.scrollTop;
                let currentScrollY = targetScrollY;
                let isTicking = false;
                let unitWidth = 1400;

                function measureUnitWidth() {
                    const firstLine = scrollTrack.querySelector('.line');
                    if (firstLine && firstLine.offsetWidth > 100) {
                        unitWidth = firstLine.offsetWidth;
                    }
                }
                measureUnitWidth();
                window.addEventListener('resize', measureUnitWidth, { passive: true });
                window.addEventListener('load', measureUnitWidth, { passive: true });
                if (document.fonts && document.fonts.ready) {
                    document.fonts.ready.then(measureUnitWidth);
                }

                function renderMarquee() {
                    const diff = targetScrollY - currentScrollY;
                    currentScrollY += diff * 0.085;

                    // Positive modulo logic ensures flawless forward & reverse looping
                    const positiveOffset = ((currentScrollY * 0.95) % unitWidth + unitWidth) % unitWidth;
                    const offset = -unitWidth - positiveOffset;

                    scrollTrack.style.transform = `translate3d(${offset.toFixed(2)}px, 0, 0)`;

                    if (Math.abs(diff) > 0.08) {
                        requestAnimationFrame(renderMarquee);
                    } else {
                        currentScrollY = targetScrollY;
                        isTicking = false;
                    }
                }

                window.addEventListener('scroll', function() {
                    targetScrollY = window.pageYOffset || document.documentElement.scrollTop;
                    if (!isTicking) {
                        isTicking = true;
                        requestAnimationFrame(renderMarquee);
                    }
                }, { passive: true });

                // Initial render
                renderMarquee();
            })();
            </script>
<section class="process-section">
        <div class="header">
            <div class="header-small">STEP BY STEP PROCESS</div>
            <h2 class="header-title">We Complete Every <span>Step Carefully</span></h2>
        </div>

        <div class="flow-container">
            <div class="flow-row">
                <div class="step">
                    <div class="step-icon"><i class="fas fa-cog" style="color: var(--primary);"></i></div>
                    <div class="step-title">Planning</div>
                    <div class="step-description">We analyze your business objectives, target audience, and project scope to map out an agile, goal-driven roadmap.</div>
                </div>

                <div class="step">
                    <div class="step-icon"><i class="fas fa-palette" style="color: var(--primary);"></i></div>
                    <div class="step-title">Design</div>
                    <div class="step-description">Our creative team crafts bespoke, high-conversion UI/UX wireframes, visual prototypes, and brand identities tailored to your audience.</div>
                </div>

                <div class="step">
                    <div class="step-icon"><i class="fas fa-laptop-code" style="color: var(--primary);"></i></div>
                    <div class="step-title">Development</div>
                    <div class="step-description">We engineer scalable, secure, and clean-code architectures using modern frameworks and performance-optimized standards.</div>
                </div>

                <div class="connector connector-top"></div>
            </div>


            <div class="flow-row">
                <div class="step">
                    <div class="step-icon"><i class="fas fa-clipboard-check" style="color: var(--primary);"></i></div>
                    <div class="step-title">Testing</div>
                    <div class="step-description">Rigorous quality assurance, cross-browser compatibility, security audits, and performance testing ensure flawless execution.</div>
                </div>

                <div class="step">
                    <div class="step-icon"><i class="fas fa-rocket" style="color: var(--primary);"></i></div>
                    <div class="step-title">Launch</div>
                    <div class="step-description">Seamless deployment to production environments with zero downtime, comprehensive SEO setup, and live monitoring.</div>
                </div>

                <div class="step">
                    <div class="step-icon"><i class="fas fa-life-ring" style="color: var(--primary);"></i></div>
                    <div class="step-title">Support</div>
                    <div class="step-description">Continuous maintenance, security patches, performance tuning, and scalable feature updates to ensure long-term growth.</div>
                </div>

                <div class="connector connector-bottom"></div>
            </div>
        </div>
    </section>
        <section class="why-choose-us">
    <h2>Why Businesses Choose Graphicafix</h2>
    <div class="container">
    <div class="wcu-grid">
        <div class="wcu-card">
            <i class="far fa-lightbulb"></i>
            <h3>Integrated Expertise</h3>
            <p>From visual branding to custom code and AI, we bring all digital capabilities under one agile roof.</p>
        </div>

        <div class="wcu-card">
            <i class="far fa-gem"></i>
            <h3>High Standards</h3>
            <p>Every digital product is engineered with precision, exceptional UX, clean code, and search visibility.</p>
        </div>

        <div class="wcu-card">
            <i class="far fa-clock"></i>
            <h3>On-Time Delivery</h3>
            <p>Transparent sprint planning and agile communication ensure your projects launch reliably on schedule.</p>
        </div>

        <div class="wcu-card">
            <i class="far fa-handshake"></i>
            <h3>Long-Term Partnership</h3>
            <p>We serve as an ongoing technology and growth partner for businesses across Pakistan, the UK, and globally.</p>
        </div>
    </div>
    </div>
</section>

<section class="project-grid">
<?php
// Fetch the 4 projects you want to show (latest 4 for example)
$projectQuery = "SELECT id, project_name, public_description, thumbnail FROM projects WHERE visibility = 'public' ORDER BY created_at LIMIT 4";
$result = $conn->query($projectQuery);

// Predefined layout classes in order
$layoutClasses = ['project-large', 'project-card', 'project-card', 'project-wide'];

$index = 0;
while ($project = $result->fetch_assoc()):
    $projectName = htmlspecialchars($project['project_name']);
    $projectDesc = htmlspecialchars($project['public_description']);
    $projectThumb = htmlspecialchars($project['thumbnail'] ?: 'assets/images/placeholder.png');
    $projectID = intval($project['id']);
    $class = $layoutClasses[$index] ?? 'project-card'; // fallback
?>
    <div class="project-item <?= $class ?>" style="background-color: var(--accent);">
        <?php if ($class === 'project-card'): ?>
            <h2 class="project-title"><?= $projectName ?></h2>
            <a href="project_details.php?id=<?= $projectID ?>" class="project-link dark">View Project ></a>
        <?php else: 
            $p_alt_thumb = $projectName . " by Graphicafix digital solutions agency";
            if (stripos($projectName, 'Qaswa') !== false) {
                $p_alt_thumb = "Qaswa Elevators brand identity and logo design by Graphicafix";
            } elseif (stripos($projectName, 'Zarmall') !== false) {
                $p_alt_thumb = "Zarmall clothing brand identity designed by Graphicafix";
            } elseif (stripos($projectName, 'Emanon') !== false) {
                $p_alt_thumb = "Emanon Institute website and branding project by Graphicafix";
            } elseif (stripos($projectName, 'Islamabad Elevators') !== false) {
                $p_alt_thumb = "Islamabad Elevators visual identity project by Graphicafix";
            } elseif (stripos($projectName, 'Bab-ul-Ilm') !== false) {
                $p_alt_thumb = "Bab-ul-Ilm digital platform project by Graphicafix";
            } elseif (stripos($projectName, 'Glowing Star') !== false) {
                $p_alt_thumb = "Glowing Star Windows Design Project by Graphicafix";
            }
        ?>
            <img src="admin/<?= $projectThumb ?>" alt="<?= htmlspecialchars($p_alt_thumb) ?>" loading="lazy">
            <div class="project-info">
                <h3><?= $projectName ?></h3>
                <a href="project_details.php?id=<?= $projectID ?>" class="project-link">View Project ></a>
            </div>
        <?php endif; ?>
    </div>
<?php
    $index++;
endwhile;
?>
</section>

<section id="contact" class="py-5" style="background-color: #f8f9fa;">
  <div class="container d-flex justify-content-center align-items-center">
    <div class="col-md-8 col-lg-6">
      
      <div class="text-center mb-4">
        <h2 class="fw-bold" style="color: #212529;">Get a Free Quote</h2>
        <p class="text-muted" style="font-size: 1rem;">
          Fill out the form below and we’ll provide a personalized quote for your project.
        </p>
      </div>

      <form class="p-4 rounded shadow-sm" style="background-color: #fff;" action="" method="POST">
        <div class="mb-3">
        <label for="name" class="form-label fw-semibold">Name</label>
        <input type="text" class="form-control" id="name" name="name" placeholder="Your Name" required>
        </div>
        <div class="mb-3">
        <label for="email" class="form-label fw-semibold">Email</label>
        <input type="email" class="form-control" id="email" name="email" placeholder="you@example.com" required>
        </div>
        <div class="mb-3">
        <label for="message" class="form-label fw-semibold">Message</label>
        <textarea class="form-control" id="message" name="message" rows="5" placeholder="Your Message" required></textarea>
        </div>
        <div class="text-center">
        <button type="submit" name="submit_contact" class="main-btn px-4 py-2 fw-semibold">
            Send Message
        </button>
        </div>
    </form>

    </div>
  </div>

  <section class="reviews-section">
    <div class="reviews-header">
        <div class="faq-header-small">TESTIMONIALS</div>
        <h2 class="faq-header-title">What Our <span>Clients Say</span></h2>
    </div>

    <?php
    $reviewsQuery  = "SELECT client_name, company, rating, review FROM reviews WHERE visible = 1 ORDER BY created_at DESC";
    $reviewsResult = $conn->query($reviewsQuery);
    $allReviews    = $reviewsResult ? $reviewsResult->fetch_all(MYSQLI_ASSOC) : [];
    $totalReviews  = count($allReviews);
    $initialShow   = 6;
    ?>

    <?php if ($totalReviews > 0): ?>
    <div class="container">
        <div class="row g-4" id="reviewsGrid">
            <?php foreach ($allReviews as $index => $review):
                $stars    = intval($review['rating']);
                $initials = strtoupper(substr($review['client_name'], 0, 1));
                $hidden   = $index >= $initialShow ? 'review-hidden' : '';
            ?>
            <div class="col-12 col-md-6 col-lg-4 review-col <?= $hidden ?>">
                <div class="review-card">
                    <div class="review-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="review-star <?= $i <= $stars ? 'filled' : 'empty' ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <p class="review-text">"<?= htmlspecialchars($review['review']) ?>"</p>
                    <div class="review-author">
                        <div class="review-avatar"><?= $initials ?></div>
                        <div class="review-author-info">
                            <div class="review-author-name"><?= htmlspecialchars($review['client_name']) ?></div>
                            <?php if (!empty($review['company'])): ?>
                                <div class="review-author-company"><?= htmlspecialchars($review['company']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalReviews > $initialShow): ?>
        <div class="reviews-toggle-wrap">
            <button class="reviews-toggle-btn" id="reviewsToggleBtn" onclick="toggleReviews()">
                <span id="reviewsToggleText">Show All <?= $totalReviews ?> Reviews</span>
                <span class="reviews-toggle-icon" id="reviewsToggleIcon">↓</span>
            </button>
        </div>
        <?php endif; ?>

    </div>
    <?php else: ?>
    <div class="container">
        <p class="faq-empty text-center">No reviews available yet.</p>
    </div>
    <?php endif; ?>
</section>

</section>
    <section class="faq-section">
        <div class="faq-header">
            <div class="faq-header-small">FAQ</div>
            <h2 class="faq-header-title">Frequently Asked Questions <span>About Our Services</span></h2>
        </div>

        <div class="faq-container">
            <?php
            // Fetch all FAQs
            $faqQuery = "SELECT id, question, answer FROM faqs ORDER BY created_at DESC";
            $result = $conn->query($faqQuery);
            if ($result && $result->num_rows > 0): 
            while ($faq = $result->fetch_assoc()):
                $faqQuestion = htmlspecialchars($faq['question']);
                $faqAnswer = htmlspecialchars($faq['answer']);
            ?>
               
                <div class="faq-item">
                <div class="faq-question">
                    <div class="faq-question-text"><?= $faqQuestion ?></div>
                    <div class="faq-icon"></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-text">
                        <?= $faqAnswer ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
            <?php else: ?>
              <p class="faq-empty">No FAQs available yet.</p>
            <?php endif; ?>
        </div>
    </section>
<!-- ════ HIGH-CONVERSION NEWSLETTER MODAL ════ -->
<div id="gfxNewsletterOverlay" class="gfx-popup-overlay" onclick="if(event.target===this)closeGfxPopup()">
    <div class="gfx-popup-modal">
        <button class="gfx-popup-close" onclick="closeGfxPopup()" aria-label="Close">&times;</button>
        
        <!-- Left Side: Visual Hook -->
        <div class="gfx-popup-visual">
            <div class="gfx-visual-content">
                <i class="fas fa-rocket"></i>
                <h3>Level Up.</h3>
            </div>
        </div>
        
        <!-- Right Side: Conversion Copy & Form -->
        <div class="gfx-popup-content">
            <span class="gfx-badge">Exclusive Offer</span>
            <h2>Unlock 10% Off Your First Course</h2>
            <p>Join the Graphicafix community. Get insider branding tips, development tutorials, and early access to upcoming programs like our Applied AI launch.</p>
            
            <form id="gfxSubscribeForm" class="gfx-form">
                <div class="gfx-input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" id="gfxSubEmail" placeholder="Enter your best email..." required>
                </div>
                <button type="submit" id="gfxSubBtn">Claim My Discount <i class="fas fa-arrow-right"></i></button>
            </form>
            
            <div id="gfxSubMessage" class="gfx-message"></div>
            <p class="gfx-spam-notice"><i class="fas fa-shield-alt"></i> 100% Secure. We respect your inbox.</p>
        </div>
    </div>
</div>
<!-- ════ NEWSLETTER MODAL CSS ════ -->
<style>
    /* Overlay Background (Right-Side Full-Height Slide-In) */
    .gfx-popup-overlay {
        position: fixed !important;
        inset: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        background: rgba(15, 23, 42, 0.6) !important;
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        z-index: 10500 !important;
        display: flex !important;
        align-items: stretch !important;
        justify-content: flex-end !important;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
        padding: 0 !important;
        margin: 0 !important;
        overflow: hidden !important;
    }

    /* Active State */
    .gfx-popup-overlay.show {
        opacity: 1 !important;
        pointer-events: auto !important;
    }

    /* Modal Container (Full-Height Right Drawer) */
    .gfx-popup-modal {
        background: #fff;
        width: 100% !important;
        max-width: 540px !important;
        height: 100vh !important;
        min-height: 100vh !important;
        max-height: 100vh !important;
        margin: 0 0 0 auto !important;
        border-radius: 0 !important;
        box-shadow: -12px 0 45px rgba(0, 0, 0, 0.25);
        display: flex !important;
        flex-direction: column !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        -webkit-overflow-scrolling: touch;
        position: relative;
        transform: translateX(100%);
        transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .gfx-popup-overlay.show .gfx-popup-modal {
        transform: translateX(0);
    }

    /* Close Button */
    .gfx-popup-close {
        position: absolute;
        top: 16px; right: 20px;
        background: #f1f5f9;
        border: none;
        width: 32px; height: 32px;
        border-radius: 50%;
        font-size: 20px;
        color: #64748b;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: all 0.2s;
        z-index: 10;
    }
    .gfx-popup-close:hover { background: #fee2e2; color: #ef4444; }

    /* Left Visual Pane */
    .gfx-popup-visual { flex: 0 0 160px !important; padding: 25px 20px !important; border-radius: 0 !important; flex-shrink: 0;
        flex: 0 0 40%;
        background: linear-gradient(135deg, var(--primary), #026652);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    
    /* Decorative Background Pattern for the visual pane */
    .gfx-popup-visual::after {
        content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 10%, transparent 10%), radial-gradient(circle, rgba(255,255,255,0.1) 10%, transparent 10%);
        background-size: 20px 20px; background-position: 0 0, 10px 10px;
        opacity: 0.3; transform: rotate(30deg);
    }

    .gfx-visual-content {
        text-align: center; z-index: 1;
    }
    .gfx-visual-content i { font-size: 3.5rem; margin-bottom: 15px; opacity: 0.9; }
    .gfx-visual-content h3 { font-size: 1.8rem; font-weight: 800; margin: 0; letter-spacing: -0.5px; }

    /* Right Content Pane */
    .gfx-popup-content {
        flex: 1;
        padding: 48px 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .gfx-badge {
        display: inline-block;
        background: #fef2f2; color: #ef4444;
        padding: 4px 12px; border-radius: 20px;
        font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;
        align-self: flex-start; margin-bottom: 16px;
    }

    .gfx-popup-content h2 { margin: 0 0 12px 0; font-size: 1.8rem; color: #0f172a; font-weight: 800; line-height: 1.2; letter-spacing: -0.5px; }
    .gfx-popup-content p { margin: 0 0 24px 0; font-size: 0.95rem; color: #64748b; line-height: 1.6; }

    /* Form Styles */
    .gfx-form { display: flex; flex-direction: column; gap: 12px; }

    .gfx-input-group {
        position: relative;
        display: flex; align-items: center;
    }
    .gfx-input-group i {
        position: absolute; left: 16px; color: #94a3b8; font-size: 14px;
    }
    .gfx-input-group input {
        width: 100%; padding: 14px 14px 14px 42px;
        border: 2px solid #e2e8f0; border-radius: 12px;
        font-size: 14.5px; outline: none; background: #f8fafc;
        transition: all 0.2s; font-family: inherit;
    }
    .gfx-input-group input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.1); }

    .gfx-form button {
        background: var(--primary); color: #fff;
        border: none; padding: 15px; border-radius: 12px;
        font-size: 15px; font-weight: 700; cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 8px;
        transition: all 0.2s;
    }
    .gfx-form button:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(2, 132, 199, 0.3); }
    .gfx-form button:active { transform: translateY(0); }

    .gfx-message { text-align: center; font-size: 13.5px; font-weight: 600; margin-top: 12px; display: none; padding: 10px; border-radius: 8px; }
    
    .gfx-spam-notice {
        margin: 16px 0 0 0 !important; text-align: center; font-size: 11.5px !important; color: #94a3b8 !important;
    }

    /* Mobile Responsiveness */
    @media (max-width: 768px) {
        .gfx-popup-modal { flex-direction: column; }
        .gfx-popup-visual { flex: 0 0 160px !important; padding: 25px 20px !important; border-radius: 0 !important; flex-shrink: 0; display: none; /* Hide visual pane on mobile for better usability */ }
        .gfx-popup-content { padding: 32px 24px; }
        .gfx-popup-content h2 { font-size: 1.5rem; }
    }
</style>
<!-- ════ NEWSLETTER MODAL JS ════ -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const overlay = document.getElementById('gfxNewsletterOverlay');
    
    // Check if the user has already handled the popup
    if (!localStorage.getItem('gfx_newsletter_handled')) {
        // Trigger the modal after 4 seconds to let them digest the page first
        setTimeout(() => {
            overlay.classList.add('show');
        }, 4000); 
    }

    // Allow closing by clicking the dark overlay background
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            closeGfxPopup();
        }
    });
});

function closeGfxPopup() {
    document.getElementById('gfxNewsletterOverlay').classList.remove('show');
    // Set storage so it doesn't bother them again
    localStorage.setItem('gfx_newsletter_handled', 'true');
}

document.getElementById('gfxSubscribeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('gfxSubBtn');
    const msg = document.getElementById('gfxSubMessage');
    const email = document.getElementById('gfxSubEmail').value;
    const originalBtnText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('email', email);

    // Keep this pointing to your standalone PHP file
    fetch('api/subscribe_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        msg.style.display = 'block';
        if (data.success) {
            msg.style.background = '#ecfdf5';
            msg.style.color = '#059669';
            msg.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
            document.getElementById('gfxSubEmail').value = ''; 
            localStorage.setItem('gfx_newsletter_handled', 'true'); 
            
            // Auto close after 3 seconds on success
            setTimeout(() => { closeGfxPopup(); }, 3000);
        } else {
            msg.style.background = '#fef2f2';
            msg.style.color = '#dc2626';
            msg.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
            btn.innerHTML = originalBtnText;
            btn.disabled = false;
        }
    })
    .catch(error => {
        msg.style.display = 'block';
        msg.style.background = '#fef2f2';
        msg.style.color = '#dc2626';
        msg.textContent = 'A network error occurred. Please try again.';
        btn.innerHTML = originalBtnText;
        btn.disabled = false;
    });
});
</script>
<script>
        
let reviewsExpanded = false;

function toggleReviews() {
    const hiddenCols = document.querySelectorAll('.review-col.review-hidden');
    const visibleExtra = document.querySelectorAll('.review-col.review-visible');
    const btn        = document.getElementById('reviewsToggleBtn');
    const btnText    = document.getElementById('reviewsToggleText');
    const btnIcon    = document.getElementById('reviewsToggleIcon');
    const total      = <?= $totalReviews ?>;

    if (!reviewsExpanded) {
        // Show all hidden cards
        hiddenCols.forEach(col => {
            col.classList.remove('review-hidden');
            col.classList.add('review-visible');
        });
        btnText.textContent = 'Show Less';
        btnIcon.classList.add('rotated');
        reviewsExpanded = true;
    } else {
        // Hide cards beyond the initial 6
        visibleExtra.forEach(col => {
            col.classList.remove('review-visible');
            col.classList.add('review-hidden');
        });
        btnText.textContent = 'Show All ' + total + ' Reviews';
        btnIcon.classList.remove('rotated');
        reviewsExpanded = false;

        // Scroll back up to the section smoothly
        document.querySelector('.reviews-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
    </script>
<?php

    include 'templates/footer.php';
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Select all images
    const images = document.querySelectorAll('img');

    images.forEach(img => {
        // If image fails to load, replace with placeholder
        img.addEventListener('error', () => {
            img.src = 'assets/images/placeholder.png';
        });

        // Optional: if src is empty/null, replace immediately
        if (!img.src || img.src.trim() === '') {
            img.src = 'assets/images/placeholder.png';
        }
    });
});

// FAQs
document.addEventListener('DOMContentLoaded', () => {
  const faqItems = document.querySelectorAll('.faq-item');

  faqItems.forEach(item => {
    const question = item.querySelector('.faq-question');

    question.addEventListener('click', () => {
      const isActive = item.classList.contains('active');

      faqItems.forEach(otherItem => {
        if (otherItem !== item) {
          otherItem.classList.remove('active');
        }
      });

      item.classList.toggle('active', !isActive);
    });
  });
});
</script>