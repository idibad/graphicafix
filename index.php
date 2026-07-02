<?php
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
    $p_start = !empty($promoCourse['start_date']) ? date('F j, Y', strtotime($promoCourse['start_date'])) : 'Start Immediately';
    
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

/* ── NEW SPLIT HERO SECTION WITH FLOATING CARDS ── */
.hero-section {
    background: #024442; /* Deep Emerald */
    padding: 140px 0 100px;
    position: relative;
    overflow: hidden;
    color: #ffffff;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: -20%; left: -10%;
    width: 50vw; height: 50vw;
    background: radial-gradient(circle, rgba(184,243,90,0.08) 0%, rgba(184,243,90,0) 70%);
    border-radius: 50%;
    z-index: 0;
    pointer-events: none;
}

.hero-section::after {
    content: '';
    position: absolute;
    bottom: -20%; right: -5%;
    width: 40vw; height: 40vw;
    background: radial-gradient(circle, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    z-index: 0;
    pointer-events: none;
}

.hero-grid {
    display: grid;
    grid-template-columns: 1.1fr 0.9fr; /* Left side slightly larger */
    gap: 40px;
    align-items: center;
    position: relative;
    z-index: 1;
}

/* ── LEFT SIDE: CONTENT ── */
.hero-content {
    text-align: left;
    animation: heroFadeRight 0.8s ease-out forwards;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.15);
    color: #f2f5f7;
    padding: 8px 20px;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 28px;
}

.hero-badge i {
    color: #b8f35a; /* Accent Lime */
}

.hero-title {
    font-size: clamp(2.8rem, 5vw, 4.5rem);
    font-weight: 800;
    line-height: 1.1;
    margin-bottom: 24px;
    color: #ffffff;
}

.hero-title .highlight {
    color: var(--primary);
    position: relative;
    display: inline-block;
    z-index: 1;
}

.hero-title .highlight::before {
    content: '';
    position: absolute;
    bottom: 6px;
    left: -2%;
    width: 104%;
    height: 30%;
    background: rgba(184,243,90,0.2);
    z-index: -1;
    border-radius: 4px;
    transform: rotate(-1deg);
}

.hero-subtitle {
    font-size: 1.15rem;
    color: rgba(255,255,255,0.8);
    font-weight: 400;
    line-height: 1.6;
    margin-bottom: 40px;
    max-width: 550px;
}

.hero-actions {
    display: flex;
    gap: 16px;
    justify-content: flex-start;
    flex-wrap: wrap;
}

.btn-hero-primary {
    background: #b8f35a;
    color: #024442;
    padding: 16px 36px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-hero-primary:hover {
    background: #a4e048;
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(184,243,90,0.3);
    color: #024442;
}

.btn-hero-secondary {
    background: transparent;
    color: #ffffff;
    padding: 16px 36px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 2px solid rgba(255,255,255,0.2);
}

.btn-hero-secondary:hover {
    background: rgba(255,255,255,0.1);
    border-color: #ffffff;
    color: #ffffff;
    transform: translateY(-3px);
}

/* ── RIGHT SIDE: FLOATING CARDS ── */
.hero-visuals {
    position: relative;
    height: 450px;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: heroFadeLeft 1s ease-out forwards;
}

.floating-card {
    position: absolute;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.02) 100%);
    border: 1px solid rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    padding: 20px;
    border-radius: 20px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    animation: floatCard 6s ease-in-out infinite;
    z-index: 2;
}

.fc-icon {
    width: 54px;
    height: 54px;
    border-radius: 14px;
    background: rgba(184,243,90,0.15);
    color: #b8f35a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.fc-text h4 { 
    margin: 0 0 4px 0; 
    font-size: 1.05rem; 
    font-weight: 800; 
    color: #fff;
}
.fc-text p { 
    margin: 0; 
    font-size: 0.85rem; 
    color: rgba(255,255,255,0.7); 
    line-height: 1.3;
}

/* Positioning & Animation Delays for each card */
.card-1 { 
    top: 20px; 
    left: 10%; 
    width: 260px;
    animation-delay: 0s; 
}
.card-2 { 
    bottom: 40px; 
    right: 5%; 
    width: 250px;
    animation-delay: -2s; /* Offsets the floating animation */
}
.card-3 { 
    top: 45%; 
    right: 20%; 
    width: 280px;
    animation-delay: -4s; 
    z-index: 3;
}

/* Animations */
@keyframes heroFadeRight {
    from { opacity: 0; transform: translateX(-30px); }
    to { opacity: 1; transform: translateX(0); }
}
@keyframes heroFadeLeft {
    from { opacity: 0; transform: translateX(30px); }
    to { opacity: 1; transform: translateX(0); }
}
@keyframes floatCard {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}

/* Mobile Responsiveness */
@media (max-width: 991px) {
    .hero-section {
        padding: 120px 0 80px;
    }
    .hero-grid {
        grid-template-columns: 1fr;
        text-align: center;
        gap: 60px;
    }
    .hero-content {
        text-align: center;
    }
    .hero-subtitle {
        margin-inline: auto; /* Centers text block */
    }
    .hero-actions {
        justify-content: center;
    }
    .hero-visuals {
        height: 350px;
        transform: scale(0.9); /* Scales down floating cards slightly to fit screen */
    }
    .card-1 { left: 0; }
    .card-2 { right: 0; }
    .card-3 { right: 10%; }
}
@media (max-width: 576px) {
    .hero-visuals {
        display: none; /* Hide floating cards on very small phones to save space */
    }
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
                            <strong>Rs. <?= number_format($p_price - ($p_price * $p_disc / 100)) ?></strong> 
                            <small style="text-decoration:line-through; color:#aaa;">Rs. <?= number_format($p_price) ?></small>
                        <?php else: ?>
                            <strong>PKR: <?= number_format($p_price) ?> /-</strong>
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


<section class="hero-section">
    <div class="container">
        <div class="hero-grid">
            
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fas fa-bolt"></i> Elevating Brands Worldwide
                </div>

                <h1 class="hero-title">
                    We Create Digital <br><span class="highlight">Excellence</span>
                </h1>

                <p class="hero-subtitle">
                    Transforming bold ideas into modern branding, creative visuals, and highly functional digital experiences that drive growth. Let's build something extraordinary.
                </p>

                <div class="hero-actions">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#projectRequestModal" class="btn-hero-primary">Start a Project</a>
                    <a href="services.php" class="btn-hero-secondary">Explore Services</a>
                </div>
            </div>

            <div class="hero-visuals">
                
                <div class="floating-card card-1">
                    <div class="fc-icon"><i class="fas fa-rocket"></i></div>
                    <div class="fc-text">
                        <h4>Lightning Fast</h4>
                        <p>On-time, high quality delivery.</p>
                    </div>
                </div>

                <div class="floating-card card-2">
                    <div class="fc-icon"><i class="fas fa-palette"></i></div>
                    <div class="fc-text">
                        <h4>Award Winning</h4>
                        <p>Modern & creative aesthetics.</p>
                    </div>
                </div>

                <div class="floating-card card-3">
                    <div class="fc-icon"><i class="fas fa-star"></i></div>
                    <div class="fc-text">
                        <h4>5.0 Rating</h4>
                        <p>100% Client Satisfaction.</p>
                    </div>
                </div>

            </div>

        </div>
    </div>
</section>

<section class="trusted-by">
    <div class="text-center">
        <div class="logo-slider">
            <div class="logo-track">
               <?php
                   // Loop twice
                for ($repeat = 0; $repeat < 2; $repeat++) {
                    foreach ($logos as $index => $logoPath) {
                        echo '<img src="' . htmlspecialchars($logoPath) . '" alt="Client Logo ' . ($index + 1) . '" class="client-logo">';
                    }
                }
                ?>
            </div>
        </div>
    </div>
</section>
          
    <div class="services text-center">
        <div class="container mt-5">
            <div class="section-title color-primary">
                <h1>Creative <span >Solutions</span></h1>
            </div>
            <div class="row cards-cont" data-aos="fade-up">
    <div class="col-lg-3 col-md-6">
        <div class="card-single">
            <div class="card-icon-wrapper">
                <div class="card-icon">
                    <i class="fas fa-palette"></i>
                </div>
            </div>
            <div class="card-info">
                <h3>Graphic Design</h3>
                <p>We craft visually stunning graphics that bring your brand to life, from logos to marketing materials.</p>
                <div class="card-learn-more">
                    <span>Learn More <i class="fas fa-arrow-right"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="card-single">
            <div class="card-icon-wrapper">
                <div class="card-icon">
                    <i class="fas fa-code"></i>
                </div>
            </div>
            <div class="card-info">
                <h3>Web Development</h3>
                <p>We build fast, responsive, and SEO-friendly websites that engage your audience and drive growth.</p>
                <div class="card-learn-more">
                    <span>Learn More <i class="fas fa-arrow-right"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="card-single">
            <div class="card-icon-wrapper">
                <div class="card-icon">
                    <i class="fas fa-pen-nib"></i>
                </div>
            </div>
            <div class="card-info">
                <h3>Content Writing</h3>
                <p>We create compelling, SEO-optimized content for blogs, websites, and social media to engage your audience.</p>
                <div class="card-learn-more">
                    <span>Learn More <i class="fas fa-arrow-right"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6">
        <div class="card-single">
            <div class="card-icon-wrapper">
                <div class="card-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
            </div>
            <div class="card-info">
                <h3>Branding</h3>
                <p>We develop unique brand identities, including logos, color schemes, and brand guidelines.</p>
                <div class="card-learn-more">
                    <span>Learn More <i class="fas fa-arrow-right"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="action-btn mt-4 mb-5">
            <a href="services.php" class="main-btn p-3">See All Services</a>
        </div>
    </div>
</div>
</div>
</div>
        <div class="work-cards">
            <div class="container">        
                <div class="row">
                        <div class="col-md-3 mb-1">
                            <div class="work-card-single">
                                <h4>
                                Creative Portfolio
                                </h4>
                                <p>
                                    Explore some of the creative projects we've crafted for our clients, 
                                    showcasing innovation, quality, and attention to detail.
                                </p>
                                <a href="#" class="main-btn bg-primary-clr" data-bs-toggle="modal" data-bs-target="#projectRequestModal">Request Project</a>
                            </div>
                        </div>
                        <?php
                        while ($project = $result->fetch_assoc()): ?>
                            <div class="col-md-3">
                                <a href="project_details.php?id=<?= intval($project['id']) ?>" class="work-card-link">
                                    <div class="work-card-single">
<img src="admin/<?= htmlspecialchars(!empty($project['thumbnail']) ? $project['thumbnail'] : 'assets/images/placeholder.png') ?>" alt="Project Thumbnail">                                            alt="<?= htmlspecialchars($project['project_name']) ?>">
                                        <div class="overlay">
                                            <h3><?= htmlspecialchars($project['project_name']) ?></h3>
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
         </div>
            <div class="scroll-text-wrapper">
            <div class="scroll-text">
                <span class="line">
                    <span class="w1">FIX</span>
                    <span class="w2">ENHANCE</span>
                    <span class="w3">AND</span>
                    <span class="w4">ELEVATE</span>
                    <span class="w5">YOUR</span>
                    <span class="w6">BRANDS</span>
                </span>

                <span class="line">
                    <span class="w1">FIX</span>
                    <span class="w2">ENHANCE</span>
                    <span class="w3">AND</span>
                    <span class="w4">ELEVATE</span>
                    <span class="w5">YOUR</span>
                    <span class="w6">BRANDS</span>
                </span>
            </div>
        </div>
<section class="process-section">
        <div class="header">
            <div class="header-small">STEP BY STEP PROCESS</div>
            <h1 class="header-title">We Complete every <span>Step Carefully</span></h1>
        </div>

        <div class="flow-container">
            <div class="flow-row">
                <div class="step">
                    <div class="step-icon"><i class="fas fa-cog" style="color: var(--primary);"></i></div>
                    <div class="step-title">Planning</div>
                    <div class="step-description">Design the look and feel, content and structure of your custom web or app</div>
                </div>

                <div class="step">
                    <div class="step-icon"><i class="fas fa-palette" style="color: var(--primary);"></i></div>
                    <div class="step-title">Design</div>
                    <div class="step-description">Use files and adopt a deep-app group context which works with your task</div>
                </div>

                <div class="step">
                    <div class="step-icon"><i class="fas fa-laptop-code" style="color: var(--primary);"></i></div>
                    <div class="step-title">Development</div>
                    <div class="step-description">We provide a design mock up from code to bring them to development</div>
                </div>

                <div class="connector connector-top"></div>
            </div>


            <div class="flow-row">
                <div class="step">
                    <div class="step-icon"><i class="fas fa-clipboard-check" style="color: var(--primary);"></i></div>
                    <div class="step-title">Testing</div>
                    <div class="step-description">Using Data that invoke the execution of your design movements</div>
                </div>

                <div class="step">
                    <div class="step-icon"><i class="fas fa-rocket" style="color: var(--primary);"></i></div>
                    <div class="step-title">Launch</div>
                    <div class="step-description">Organizing them is a core part before of the design movements</div>
                </div>

                <div class="step">
                    <div class="step-icon"><i class="fas fa-life-ring" style="color: var(--primary);"></i></div>
                    <div class="step-title">Support</div>
                    <div class="step-description">Explain and concept of designs helping to deliver the theme</div>
                </div>

                <div class="connector connector-bottom"></div>
            </div>
        </div>
    </section>
        <section class="why-choose-us">
    <h2>Why Choose Us</h2>
    <div class="container">
    <div class="wcu-grid">
        <div class="wcu-card">
            <i class="far fa-lightbulb"></i>
            <h3>Creative Approach</h3>
            <p>We combine strategy, creativity, and clean execution to deliver meaningful results.</p>
        </div>

        <div class="wcu-card">
            <i class="far fa-gem"></i>
            <h3>High Quality</h3>
            <p>Every project is crafted with precision, clarity, and top-tier design standards.</p>
        </div>

        <div class="wcu-card">
            <i class="far fa-clock"></i>
            <h3>On-Time Delivery</h3>
            <p>We deliver work on time without compromising quality or detail.</p>
        </div>

        <div class="wcu-card">
            <i class="far fa-handshake"></i>
            <h3>Trusted Partnership</h3>
            <p>We work closely with clients and ensure long-term, reliable collaboration.</p>
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
        <?php else: ?>
            <img src="admin/<?= $projectThumb ?>" alt="<?= $projectName ?>">
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
        <h1 class="faq-header-title">What Our <span>Clients Say</span></h1>
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
            <h1 class="faq-header-title">Frequently Asked <span>Questions</span></h1>
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
<div id="gfxNewsletterOverlay" class="gfx-popup-overlay">
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
    /* Overlay Background */
    .gfx-popup-overlay {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.4s ease;
        padding: 20px;
    }

    /* Active State */
    .gfx-popup-overlay.show {
        opacity: 1;
        pointer-events: auto;
    }

    /* Modal Container */
    .gfx-popup-modal {
        background: #fff;
        width: 100%;
        max-width: 800px;
        border-radius: 24px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        display: flex;
        overflow: hidden;
        position: relative;
        transform: scale(0.95) translateY(20px);
        transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .gfx-popup-overlay.show .gfx-popup-modal {
        transform: scale(1) translateY(0);
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
    .gfx-popup-visual {
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
        .gfx-popup-visual { display: none; /* Hide visual pane on mobile for better usability */ }
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