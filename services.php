<?php
include('header.php');

// 1️⃣ Fetch all services
$services_result = $conn->query("SELECT * FROM services ORDER BY created_at ASC");
$services = $services_result->fetch_all(MYSQLI_ASSOC);

// 2️⃣ Fetch all packages and features
$stmt = $conn->prepare("
    SELECT 
        s.id AS service_id,
        s.title AS service_title,
        p.id AS package_id,
        p.name AS package_name,
        p.is_featured,
        f.feature AS feature_text
    FROM services s
    LEFT JOIN service_packages p ON p.service_id = s.id
    LEFT JOIN service_package_features f ON f.package_id = p.id
    ORDER BY s.id ASC, p.id ASC, f.id ASC
");

$stmt->execute();
$result = $stmt->get_result();

// Organize data into a nested array
$data = [];

while ($row = $result->fetch_assoc()) {
    $sid = $row['service_id'];
    $pid = $row['package_id'];

    if (!isset($data[$sid])) {
        $data[$sid] = [
            'title' => $row['service_title'],
            'packages' => []
        ];
    }

    if ($pid) {
        if (!isset($data[$sid]['packages'][$pid])) {
            $data[$sid]['packages'][$pid] = [
                'id' => $pid,
                'name' => $row['package_name'],
                'is_featured' => (bool)$row['is_featured'],
                'features' => []
            ];
        }

        if (!empty($row['feature_text'])) {
            $data[$sid]['packages'][$pid]['features'][] = $row['feature_text'];
        }
    }
}

// Debug: check data structure
// echo '<pre>'; print_r($data); echo '</pre>';
?>
<style>
    .bg-paper { background-color: var(--light); }
    .bg-white { background-color: #FFFFFF; }
    
    .service-chapter { padding: 100px 0; border-bottom: 1px solid rgba(0,0,0,0.05); }
    
    .eyebrow-text {
        font-size: 0.8rem; letter-spacing: 2px; text-transform: uppercase;
        color: var(--primary); font-weight: 600; margin-bottom: 15px; display: block;
    }
    
    .service-heading {
        font-family: 'DM Serif Display', sans-serif; font-size: 3.5rem; line-height: 1.1; margin-bottom: 0;
    }

    /* Standard Package */
    .package-card {
        background: #fff; border: 1px solid #EAEAEA; border-radius: 12px;
        padding: 50px 40px; height: 100%; display: flex; flex-direction: column;
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .package-card:hover { border-color: #ccc; transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.04); }
    
    .package-name { font-family: 'Outfit', sans-serif; font-size: 1.5rem; font-weight: 400; margin-bottom: 30px; }
    
    .feature-list { list-style: none; padding: 0; margin: 0 0 40px 0; flex-grow: 1; }
    .feature-list li {
        display: flex; align-items: flex-start; gap: 12px; margin-bottom: 15px;
        font-size: 1.05rem; color: #555; font-weight: 300; line-height: 1.5;
    }
    .feature-list li i { color: var(--accent, #D96C4A); font-size: 1.1rem; margin-top: 4px; }

    .btn-quote {
        display: block; width: 100%; padding: 16px; border-radius: 50px; text-align: center;
        text-decoration: none; font-weight: 500; transition: 0.3s;
        border: 1px solid #222; color: #222; background: transparent;
    }
    .btn-quote:hover { background: #222; color: #fff; }

    /* Featured Package (Inverted) */
    .package-featured {
        background: var(--primary); color: #fff; border-color: #1A1A1A;
        transform: scale(1.03); /* Pops out of the grid slightly */
        box-shadow: 0 30px 60px rgba(0,0,0,0.15);
        z-index: 2; position: relative;
    }
    .package-featured:hover { transform: scale(1.03) translateY(-5px); border-color: #1A1A1A; }
    
    .package-featured .package-name { color: #fff; }
    .package-featured .feature-list li { color: #E0E0E0; }
    .package-featured .feature-list li i { color: #fff; }
    
    .package-featured .btn-quote {
        background: var(--accent); border-color: var(--accent); color: #fff;
    }
    .package-featured .btn-quote:hover { background: #fff; color: #1A1A1A; border-color: #fff; }

    .featured-label {
        position: absolute; top: -15px; left: 50%; transform: translateX(-50%);
        background: var(--accent); color: #fff; font-size: 0.75rem;
        text-transform: uppercase; letter-spacing: 1px; padding: 6px 16px;
        border-radius: 20px; font-weight: 600;
    }

    /* Responsive adjustments */
    @media (max-width: 991px) {
        .package-featured { transform: scale(1); margin-top: 20px; margin-bottom: 20px; }
        .package-featured:hover { transform: translateY(-5px); }
        .service-heading { font-size: 2.5rem; }
    }
</style>
<section class="services-section">
    <div class="services-header text-center mb-5">
      <h2 class="services-heading">Our Services</h2>
      <p class="services-subtext">
        We offer high-quality creative and digital services tailored to your business.
      </p>
    </div>

  <div class="container">
    <div class="service-tagline">
      <h2>
        Our mission is to grow your business through <span class="highlight">smart tech</span> solutions.
      </h2>
    </div>
    <div class="row">
        <div class="col-lg-4 col-md-6">
            <div class="service-item" data-aos="zoom-in">
                <div class="service-card">
                <i class="fas fa-bullhorn service-icon"></i>
                <h3 class="service-title">Branding</h3>
                <p class="service-text">
                    Complete identity systems that define your brand with clarity and long-term consistency.
                </p>
                <a href="#" class="explore-service-btn main-btn">Explore Packages</a>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="service-item" data-aos="zoom-in">
                <div class="service-card">
                <i class="fas fa-pen-nib service-icon"></i>
                <h3 class="service-title">Logo Design</h3>
                <p class="service-text">
                    Minimal, modern and timeless logos built for strong recognition.
                </p>
                <a href="#" class="explore-service-btn main-btn">Explore Packages</a>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="service-item" data-aos="zoom-in">
                <div class="service-card">
                <i class="fas fa-laptop-code service-icon"></i>
                <h3 class="service-title">Web Design</h3>
                <p class="service-text">
                    Clean responsive websites that convert visitors into customers and elevate online presence.
                </p>
                <a href="#" class="explore-service-btn main-btn">Explore Packages</a>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="service-item" data-aos="zoom-in">
                <div class="service-card">
                <i class="fas fa-feather-alt service-icon"></i>
                <h3 class="service-title">Content Writing</h3>
                <p class="service-text">
                    Professional content crafted for clarity, SEO, engagement and brand tone.
                </p>
                <a href="#" class="explore-service-btn main-btn">Explore Packages</a>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="service-item" data-aos="zoom-in">
                <div class="service-card">
                <i class="fas fa-share-nodes service-icon"></i>
                <h3 class="service-title">Social Media</h3>
                <p class="service-text">
                    Consistent creative visuals and strategy for strong brand presence across platforms.
                </p>
                <a href="#" class="explore-service-btn main-btn">Explore Packages</a>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="service-item" data-aos="zoom-in">
                <div class="service-card">
                <i class="fas fa-bullseye service-icon"></i>
                <h3 class="service-title">Marketing Campaigns</h3>
                <p class="service-text">
                    Creative, targeted campaigns that grab attention and deliver measurable results.
                </p>
                <a href="#" class="explore-service-btn main-btn">Explore Packages</a>
                </div>
            </div>
        </div>
        </div>
  </div>
</section>

<?php 
$index = 0; 
foreach ($data as $service): 
    $is_even = ($index % 2 == 0);
    $bg_class = $is_even ? 'bg-paper' : 'bg-white';
    $index++;
?>

<section class="service-chapter <?= $bg_class ?>">
    <div class="container">
        
        <div class="row mb-5 pb-3">
            <div class="col-lg-8">
                <span class="eyebrow-text">Service Suite <?= str_pad($index, 2, '0', STR_PAD_LEFT) ?></span>
                <h2 class="service-heading"><?= htmlspecialchars($service['title']) ?></h2>
            </div>
        </div>

        <div class="row align-items-stretch justify-content-center g-4">
            <?php if (!empty($service['packages'])): ?>
                <?php foreach ($service['packages'] as $package): 
                    $is_featured = !empty($package['is_featured']);
                    $card_class = $is_featured ? 'package-card package-featured' : 'package-card';
                ?>
                
                <div class="col-lg-4 col-md-6">
                    <div class="<?= $card_class ?>">
                        
                        <?php if ($is_featured): ?>
                            <div class="featured-label">Recommended</div>
                        <?php endif; ?>

                        <h3 class="package-name"><?= htmlspecialchars($package['name']) ?></h3>
                        
                        <ul class="feature-list">
                            <?php foreach ($package['features'] as $feature): ?>
                                <li>
                                    <i class="fa-solid fa-check"></i>
                                    <span><?= htmlspecialchars($feature) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="mt-auto">
                            <a href="quote.php?service_id=<?= $package['id'] ?>" class="btn-quote">
                                Get Qoute
                            </a>
                        </div>
                        
                    </div>
                </div>

                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <p class="text-secondary" style="font-weight: 300;">Custom scopes available upon request.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</section>

<?php endforeach; ?>

    <div class="container">
        <div class="row mt-5">
            <div class="col-12">
                <div class="cta-banner" data-aos="zoom-in">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h2 class="cta-title">Your business deserves a better website.</h2>
                            <p class="cta-subtitle">Graphicafix makes it happen</p>
                        </div>
                        <div class="col-lg-4">
                            <div class="price-section">
                                <div class="price-label">Starting From</div>
                                <div class="price-amount">Rs. 25000</div>
                                <button class="main-btn">Get Started</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


<?php
    include('footer.php');
?>
