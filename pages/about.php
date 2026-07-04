<?php
    // Prevent 500 errors visually
    error_reporting(0);
    ini_set('display_errors', '0');

    require_once __DIR__ . '/../core/config.php';
include __DIR__ . '/../templates/header.php';

    $query = "
        SELECT * FROM team
        ORDER BY 
            CASE role
                WHEN 'CEO' THEN 1
                WHEN 'Manager' THEN 2
                ELSE 3
            END,
            role ASC
    ";
    $result = @$conn->query($query);
?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --primary-dark: #024442;
        --accent-lime: #b8f35a;
        --text-gray: #666666;
        --bg-light: #f7f9f5;
    }

    body {
        font-family: 'Poppins', sans-serif;
    }

    /* ── HERO SECTION ── */
    .about-hero {
        padding: 140px 0 100px;
        text-align: center;
        position: relative;
        overflow: hidden;
        background: var(--primary-dark);
        color: #ffffff;
    }
    .about-hero::before {
        content: ''; position: absolute; inset: 0;
        background-image: radial-gradient(rgba(255,255,255,0.1) 1px, transparent 1px);
        background-size: 30px 30px; opacity: 0.3;
    }
    .about-hero h1 {
        font-size: clamp(2.5rem, 5vw, 4rem);
        font-weight: 800;
        margin-bottom: 24px;
        position: relative;
        color: #ffffff;
        letter-spacing: -1px;
    }
    .about-hero h1 span {
        color: var(--accent-lime);
    }
    .about-hero p {
        font-size: 1.15rem;
        max-width: 650px;
        margin: 0 auto;
        line-height: 1.8;
        position: relative;
        color: rgba(255, 255, 255, 0.8);
        font-weight: 300;
    }

    /* ── MISSION / VISION / VALUES ── */
    .mv-section {
        padding: 100px 0;
        background: #ffffff;
    }
    .mv-card {
        background: #ffffff;
        padding: 50px 40px;
        border-radius: 24px;
        text-align: center;
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        height: 100%;
        position: relative;
        border: 1px solid #ebebeb;
        z-index: 1;
    }
    .mv-card::after {
        content: ''; position: absolute; inset: 0;
        border-radius: 24px;
        background: linear-gradient(135deg, rgba(2,68,66,0.03) 0%, rgba(184,243,90,0.1) 100%);
        opacity: 0; transition: opacity 0.4s ease; z-index: -1;
    }
    .mv-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px rgba(2,68,66,0.08);
        border-color: transparent;
    }
    .mv-card:hover::after { opacity: 1; }
    .mv-icon {
        width: 70px; height: 70px; margin: 0 auto 24px;
        background: rgba(184,243,90,0.2); color: var(--primary-dark);
        border-radius: 20px; display: flex; align-items: center; justify-content: center;
        font-size: 1.8rem; transform: rotate(-5deg); transition: transform 0.4s ease;
    }
    .mv-card:hover .mv-icon { transform: rotate(0deg) scale(1.1); background: var(--accent-lime); }
    .mv-card h3 {
        font-size: 1.5rem; font-weight: 800; margin-bottom: 16px; color: var(--primary-dark);
    }
    .mv-card p {
        font-size: 0.95rem; color: var(--text-gray); line-height: 1.7; margin: 0;
    }

    /* ── STORYTELLING SECTION ── */
    .story-section {
        padding: 100px 0;
        background: var(--bg-light);
        border-top: 1px solid #e8e8e8;
    }
    .section-header-small {
        font-size: 0.8rem; font-weight: 700; letter-spacing: 2px;
        text-transform: uppercase; color: var(--primary-dark); margin-bottom: 16px; display: block;
    }
    .section-title {
        font-size: clamp(2rem, 4vw, 2.8rem); font-weight: 800; color: var(--primary-dark);
        line-height: 1.2; margin-bottom: 24px;
    }
    .story-content {
        font-size: 1.1rem; color: #555; line-height: 1.8; font-weight: 300;
    }
    .story-content strong { color: var(--primary-dark); font-weight: 600; }

    /* ── STATS SECTION ── */
    .stats-section {
        padding: 80px 0;
        background: var(--primary-dark);
        color: #ffffff;
        text-align: center;
    }
    .stat-item h4 {
        font-size: 3.5rem; font-weight: 800; color: var(--accent-lime); margin-bottom: 5px; line-height: 1;
    }
    .stat-item p {
        font-size: 1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.8);
    }

    /* ── APPROACH SECTION ── */
    .approach-section {
        padding: 100px 0;
        background: #ffffff;
    }
    .approach-card {
        padding: 30px;
        border-left: 3px solid #ebebeb;
        height: 100%;
        transition: all 0.3s ease;
    }
    .approach-card:hover {
        border-left-color: var(--accent-lime);
        background: var(--bg-light);
        border-radius: 0 16px 16px 0;
    }
    .approach-step {
        font-size: 1rem; font-weight: 800; color: #ccc; margin-bottom: 10px; display: block;
    }
    .approach-card:hover .approach-step { color: var(--primary-dark); }
    .approach-card h3 { font-size: 1.3rem; font-weight: 700; color: var(--primary-dark); margin-bottom: 12px; }
    .approach-card p { font-size: 0.95rem; color: var(--text-gray); line-height: 1.6; margin: 0; }

    /* ── TEAM SECTION ── */
    .team-section { padding: 100px 0; background: var(--bg-light); border-top: 1px solid #e8e8e8; }
    .team-header { text-align: center; margin-bottom: 70px; }
    .team-header-title span { color: var(--accent-lime); background: var(--primary-dark); padding: 0 10px; border-radius: 8px; }
    
    .member-card {
        background: #ffffff; border-radius: 24px; padding: 40px 30px;
        text-align: center; transition: all 0.4s ease;
        border: 1px solid #e8e8e8; height: 100%;
    }
    .member-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(2,68,66,0.08);
        border-color: transparent;
    }
    .member-avatar {
        width: 120px; height: 120px; border-radius: 50%; margin: 0 auto 25px;
        display: flex; align-items: center; justify-content: center;
        font-size: 60px; position: relative; transition: all 0.4s ease;
        background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
    }
    .col-lg-4:nth-child(3n+1) .member-avatar { background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%); }
    .col-lg-4:nth-child(3n+2) .member-avatar { background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); }
    .col-lg-4:nth-child(3n+3) .member-avatar { background: linear-gradient(135deg, #ffedd5 0%, #fecdd3 100%); }
    
    .member-card:hover .member-avatar { transform: scale(1.08); }
    .member-name { font-size: 1.3rem; font-weight: 700; color: var(--primary-dark); margin-bottom: 6px; }
    .member-role {
        font-size: 0.75rem; color: var(--primary-dark); font-weight: 700;
        text-transform: uppercase; letter-spacing: 1px; margin-bottom: 18px;
        background: rgba(184,243,90,0.3); display: inline-block; padding: 4px 12px; border-radius: 20px;
    }
    .member-bio { font-size: 0.9rem; color: var(--text-gray); line-height: 1.6; margin: 0; font-weight: 300; }

    /* ── FAQ SECTION (Dynamic) ── */
    .faq-section { padding: 100px 0; background: #ffffff; }
    .faq-header { text-align: center; margin-bottom: 50px; }
    .faq-header-small {
        color: var(--primary-dark); font-size: 0.8rem; font-weight: 700;
        letter-spacing: 2px; text-transform: uppercase; margin-bottom: 12px;
    }
    .faq-header-title {
        font-size: clamp(2rem, 4vw, 2.8rem); font-weight: 800; color: var(--primary-dark); margin-bottom: 20px;
    }
    .faq-header-title span { color: var(--accent-lime); background: var(--primary-dark); padding: 0 10px; border-radius: 8px; }
    
    .faq-container { max-width: 800px; margin: 0 auto; padding: 0 20px; }
    .faq-item {
        background: #fff; border: 1px solid #e8e8e8; border-radius: 12px;
        margin-bottom: 16px; overflow: hidden; transition: all 0.3s ease;
    }
    .faq-item.active { border-color: var(--primary-dark); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
    .faq-question {
        padding: 20px 24px; cursor: pointer; display: flex; justify-content: space-between; align-items: center;
        background: var(--bg-light); transition: all 0.3s;
    }
    .faq-item.active .faq-question { background: var(--primary-dark); color: #fff; }
    .faq-question-text { font-size: 1.1rem; font-weight: 600; color: var(--primary-dark); }
    .faq-item.active .faq-question-text { color: #fff; }
    .faq-icon {
        width: 24px; height: 24px; position: relative;
    }
    .faq-icon::before, .faq-icon::after {
        content: ''; position: absolute; background: var(--primary-dark); transition: all 0.3s;
    }
    .faq-icon::before { top: 11px; left: 4px; width: 16px; height: 2px; }
    .faq-icon::after { top: 4px; left: 11px; width: 2px; height: 16px; }
    .faq-item.active .faq-icon::before, .faq-item.active .faq-icon::after { background: var(--accent-lime); }
    .faq-item.active .faq-icon::after { transform: rotate(90deg); opacity: 0; }
    
    .faq-answer {
        max-height: 0; overflow: hidden; transition: max-height 0.4s ease;
    }
    .faq-item.active .faq-answer { max-height: 500px; }
    .faq-answer-text { padding: 20px 24px; font-size: 0.95rem; color: var(--text-gray); line-height: 1.7; }
    .faq-empty { text-align: center; color: #888; font-style: italic; }

    /* ── CTA SECTION ── */
    .about-cta {
        padding: 80px 0; background: var(--accent-lime); text-align: center;
    }
    .about-cta h2 { font-size: 2.5rem; font-weight: 800; color: var(--primary-dark); margin-bottom: 20px; }
    .about-cta .btn-dark-custom {
        background: var(--primary-dark); color: #fff; padding: 15px 40px; border-radius: 50px;
        font-weight: 700; text-decoration: none; display: inline-block; transition: all 0.3s;
    }
    .about-cta .btn-dark-custom:hover { background: #111; transform: translateY(-3px); }

    @media (max-width: 768px) {
        .about-hero { padding: 100px 20px 60px; }
        .mv-section, .story-section, .team-section, .approach-section, .faq-section { padding: 60px 0; }
        .section-title { margin-bottom: 16px; }
        .stat-item { margin-bottom: 30px; }
        .approach-card { border-left: none; border-top: 3px solid #ebebeb; padding: 25px 0; }
        .approach-card:hover { border-radius: 0 0 16px 16px; border-top-color: var(--accent-lime); }
    }
</style>

<div class="about-wrapper">

    <section class="about-hero">
        <div class="container">
            <h1>About <span>Graphicafix</span></h1>
            <p>We are a full-service creative digital agency helping brands grow through strategic graphic design, web development, and digital marketing excellence.</p>
        </div>
    </section>

    <section class="mv-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="mv-card">
                        <div class="mv-icon"><i class="fas fa-bullseye"></i></div>
                        <h3>Mission</h3>
                        <p>To empower modern businesses with creative, effective, and scalable digital design solutions that drive real ROI.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mv-card">
                        <div class="mv-icon"><i class="fas fa-eye"></i></div>
                        <h3>Vision</h3>
                        <p>To be the globally recognized creative agency partner for ambitious brands ready to scale their digital presence.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mv-card">
                        <div class="mv-icon"><i class="fas fa-gem"></i></div>
                        <h3>Values</h3>
                        <p>Driven by creativity, built on transparent integrity, and executed with absolute technical precision.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="story-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-5 mb-4 mb-lg-0">
                    <span class="section-header-small">Our Agency Story</span>
                    <h2 class="section-title">Design that actually drives business.</h2>
                </div>
                <div class="col-lg-7">
                    <p class="story-content">
                        Graphicafix began with a simple yet powerful idea: <strong>bridging the gap between beautiful aesthetics and functional business growth</strong>. What started as a dedicated graphic design studio has rapidly evolved into a comprehensive digital agency. 
                        <br><br>
                        Today, we provide end-to-end services including <strong>custom logo design, corporate branding, SEO-optimized web development, and digital content creation</strong>. We proudly serve a diverse portfolio of clients worldwide, ensuring that every project we deliver is built on strategic clarity, fueled by bold creativity, and designed for market dominance.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-4 stat-item">
                    <h4>100+</h4>
                    <p>Projects Completed</p>
                </div>
                <div class="col-md-4 stat-item">
                    <h4>5+</h4>
                    <p>Years of Expertise</p>
                </div>
                <div class="col-md-4 stat-item">
                    <h4>98%</h4>
                    <p>Client Satisfaction</p>
                </div>
            </div>
        </div>
    </section>

    <section class="approach-section">
        <div class="container">
            <div class="text-center mb-5">
                <span class="section-header-small">How We Work</span>
                <h2 class="section-title">Our Proven Creative Process</h2>
            </div>
            <div class="row g-0">
                <div class="col-md-4">
                    <div class="approach-card">
                        <span class="approach-step">Step 01</span>
                        <h3>Discovery & Strategy</h3>
                        <p>We start by deeply understanding your brand, target audience, and market competitors. We build a strategic roadmap tailored to your specific business goals.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="approach-card">
                        <span class="approach-step">Step 02</span>
                        <h3>Design & Development</h3>
                        <p>Our UI/UX designers and developers bring the strategy to life. We craft visually stunning, responsive designs and robust code that performs seamlessly across all devices.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="approach-card">
                        <span class="approach-step">Step 03</span>
                        <h3>Launch & Optimization</h3>
                        <p>We don't just hand over files. We assist with deployment, conduct rigorous quality assurance, and provide ongoing support to ensure your brand continues to grow.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="team-section">
        <div class="container">
            <div class="team-header">
                <div class="section-header-small">The Experts</div>
                <h2 class="section-title">Meet the <span>Minds</span></h2>
                <p class="story-content text-center mx-auto" style="max-width: 600px; font-size: 1rem;">
                    A collective of talented digital professionals dedicated to bringing your brand's vision to life with passion and technical expertise.
                </p>
            </div>
            
            <div class="row g-4 justify-content-center">
            <?php
                if ($result && $result->num_rows > 0) {
                    while ($member = $result->fetch_assoc()):
                        $name = htmlspecialchars($member['member_name']);
                        $role = htmlspecialchars($member['role']);
                        $bio = htmlspecialchars(isset($member['bio']) ? $member['bio'] : 'Dedicated professional bringing expertise and creativity to every project.');
                        $gender = isset($member['gender']) ? $member['gender'] : ''; 
                        
                        if ($gender === 'Male') {
                            $avatar = '👨‍🎨';
                        } elseif ($gender === 'Female') {
                            $avatar = '👩‍🎨';
                        } else {
                            $avatar = '🧑‍🎨'; 
                        }
            ?>
                <div class="col-lg-4 col-md-6">
                    <div class="member-card">
                        <div class="member-avatar"><?php echo $avatar; ?></div>
                        <h3 class="member-name"><?php echo $name; ?></h3>
                        <div class="member-role"><?php echo $role; ?></div>
                        <p class="member-bio"><?php echo $bio; ?></p>
                    </div>
                </div>
            <?php 
                    endwhile; 
                } else {
                    echo "<p class='text-center text-muted'>No team members found.</p>";
                }
            ?>
            </div>
        </div>
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
            $faqResult = @$conn->query($faqQuery);
            if ($faqResult && $faqResult->num_rows > 0): 
                while ($faq = $faqResult->fetch_assoc()):
                    $faqQuestion = htmlspecialchars($faq['question']);
                    $faqAnswer = htmlspecialchars($faq['answer']);
            ?>
                <div class="faq-item">
                    <div class="faq-question">
                        <div class="faq-question-text"><?php echo $faqQuestion; ?></div>
                        <div class="faq-icon"></div>
                    </div>
                    <div class="faq-answer">
                        <div class="faq-answer-text">
                            <?php echo $faqAnswer; ?>
                        </div>
                    </div>
                </div>
            <?php 
                endwhile; 
            else: 
            ?>
                <p class="faq-empty">No FAQs available yet.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="about-cta">
        <div class="container">
            <h2>Ready to transform your brand?</h2>
            <p class="mb-4 text-dark font-weight-bold">Let's discuss your next big project.</p>
            <a href="contact.php" class="btn-dark-custom">Start a Project</a>
        </div>
    </section>

</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>

<script>
// Image Error Fallbacks
document.addEventListener('DOMContentLoaded', () => {
    const images = document.querySelectorAll('img');
    images.forEach(img => {
        img.addEventListener('error', () => {
            img.src = 'assets/images/placeholder.png';
        });
        if (!img.src || img.src.trim() === '') {
            img.src = 'assets/images/placeholder.png';
        }
    });
});

// FAQ Accordion Logic
document.addEventListener('DOMContentLoaded', () => {
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        if (question) {
            question.addEventListener('click', () => {
                const isActive = item.classList.contains('active');
                faqItems.forEach(otherItem => {
                    if (otherItem !== item) {
                        otherItem.classList.remove('active');
                    }
                });
                item.classList.toggle('active', !isActive);
            });
        }
    });
});
</script>