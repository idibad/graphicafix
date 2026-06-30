<?php
include('header.php');

// ── Fetch services with packages + features ───────────────────────────────────
$stmt = $conn->prepare("
    SELECT 
        s.id AS service_id, s.title AS service_title, s.description AS service_desc, s.icon AS service_icon,
        p.id AS package_id, p.name AS package_name, p.price, p.is_featured,
        f.feature AS feature_text
    FROM services s
    LEFT JOIN service_packages p ON p.service_id = s.id
    LEFT JOIN service_package_features f ON f.package_id = p.id
    ORDER BY s.id ASC, p.id ASC, f.id ASC
");
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $sid = $row['service_id'];
    $pid = $row['package_id'];
    if (!isset($data[$sid])) {
        $data[$sid] = [
            'title'    => $row['service_title'],
            'desc'     => $row['service_desc'] ?? '',
            'icon'     => $row['service_icon'] ?? '',
            'packages' => []
        ];
    }
    if ($pid && !isset($data[$sid]['packages'][$pid])) {
        $data[$sid]['packages'][$pid] = [
            'id'          => $pid,
            'name'        => $row['package_name'],
            'price'       => $row['price'] ?? null,
            'is_featured' => (bool)$row['is_featured'],
            'features'    => []
        ];
    }
    if ($pid && !empty($row['feature_text'])) {
        $data[$sid]['packages'][$pid]['features'][] = $row['feature_text'];
    }
}

// ── Active discounts (FIXED) ──────────────────────────────────────────────────
$discounts = [];
$disc_result = @$conn->query("
    SELECT * FROM discounts 
    WHERE status = 'active' 
      AND (
          expires_date IS NULL 
          OR expires_date = '' 
          OR expires_date = '0000-00-00' 
          OR expires_date = '0000-00-00 00:00:00' 
          OR expires_date >= CURDATE()
      )
    ORDER BY id DESC
");
if ($disc_result) {
    while ($d = $disc_result->fetch_assoc()) {
        $discounts[] = $d;
    }
}

$fallback_icons = ['fa-bullhorn','fa-pen-nib','fa-laptop-code','fa-feather-alt','fa-share-nodes','fa-bullseye','fa-star','fa-paint-brush'];
$fallback_descs = [
    'Branding'            => 'Complete identity systems that define your brand with clarity and long-term consistency.',
    'Logo Design'         => 'Minimal, modern and timeless logos built for strong recognition.',
    'Web Design'          => 'Clean responsive websites that convert visitors into customers.',
    'Content Writing'     => 'Professional content crafted for clarity, SEO, engagement and brand tone.',
    'Social Media'        => 'Consistent creative visuals and strategy for strong brand presence.',
    'Marketing Campaigns' => 'Creative, targeted campaigns that grab attention and deliver results.',
];
?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
.srv-page * { font-family: 'Poppins', Helvetica, Arial, sans-serif; }

/* ── Hero ── */
.srv-hero {
    background: var(--primary);
    padding: 130px 0 50px;
    position: relative; overflow: hidden;
}
.srv-hero::before {
    content: ''; position: absolute; inset: 0;
    background: url('../images/doodles-bg.png') center/cover no-repeat;
    opacity: .06; z-index: 0;
}
.srv-hero-inner { position: relative; z-index: 1; }
.srv-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(184,243,90,.1); border: 1px solid rgba(184,243,90,.25);
    color: var(--accent); font-size: .65rem; font-weight: 700;
    letter-spacing: 2px; text-transform: uppercase;
    padding: 5px 14px; border-radius: 30px; margin-bottom: 16px;
}
.srv-hero-title {
    font-size: clamp(1.8rem, 3.5vw, 2.6rem);
    font-weight: 700; color: #fff; line-height: 1.15; margin-bottom: 12px;
}
.srv-hero-title span { color: var(--accent); }
.srv-hero-sub {
    font-size: .9rem; font-weight: 300;
    color: rgba(255,255,255,.6); max-width: 480px;
    line-height: 1.7; margin-bottom: 0;
}

/* ── Discount ticker — truly infinite ── */
.discount-strip { background: var(--accent); overflow: hidden; }
.discount-track {
    display: flex;
    width: max-content;
    animation: scrollTrack 35s linear infinite;
}
/* No pause on hover — seamless loop */
@keyframes scrollTrack {
    0%   { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}
.discount-chip {
    display: inline-flex; align-items: center; gap: 10px;
    padding: 11px 28px; font-size: .78rem; font-weight: 700;
    color: var(--primary); border-right: 1px solid rgba(2,68,66,.12);
    flex-shrink: 0; white-space: nowrap;
}
.discount-chip .chip-pct {
    background: var(--primary); color: var(--accent);
    font-size: .62rem; font-weight: 800; padding: 2px 8px; border-radius: 20px;
}

/* ── Shared labels ── */
.srv-eyebrow-sm {
    font-size: .62rem; font-weight: 700; letter-spacing: 2px;
    text-transform: uppercase; color: var(--primary); display: block; margin-bottom: 6px;
}
.srv-section-h {
    font-size: clamp(1.5rem, 2.8vw, 2rem);
    font-weight: 700; color: var(--primary); line-height: 1.2;
}

/* ── Overview section ── */
.srv-overview {
    padding: 72px 0 64px; background: #f7f9f5;
    border-bottom: 1px solid #e8e8e8;
}

/* ── Filter bar ── */
.srv-filter-bar {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 12px;
    margin-bottom: 32px; padding-bottom: 20px;
    border-bottom: 1px solid #ebebeb;
}
.srv-filter-pills { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.srv-filter-btn {
    padding: 7px 18px; border-radius: 30px; font-size: .77rem; font-weight: 600;
    border: 1.5px solid #e0e0e0; background: #fff; color: #666; cursor: pointer;
    transition: all .2s; font-family: 'Poppins', Helvetica, sans-serif; white-space: nowrap;
}
.srv-filter-btn:hover { border-color: var(--primary); color: var(--primary); }
.srv-filter-btn.active {
    background: var(--primary); border-color: var(--primary); color: #fff;
    box-shadow: 0 4px 12px rgba(2,68,66,.12);
}
.srv-show-row { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.srv-show-label { font-size: .68rem; font-weight: 700; color: #bbb; text-transform: uppercase; letter-spacing: 1px; }
.srv-show-btns { display: flex; gap: 4px; }
.srv-show-btn {
    width: 34px; height: 34px; border-radius: 8px; font-size: .77rem; font-weight: 600;
    border: 1.5px solid #e0e0e0; background: #fff; color: #999; cursor: pointer;
    transition: all .2s; font-family: 'Poppins', Helvetica, sans-serif;
    display: flex; align-items: center; justify-content: center;
}
.srv-show-btn:hover { border-color: var(--primary); color: var(--primary); }
.srv-show-btn.active { background: var(--accent); border-color: var(--accent); color: var(--primary); font-weight: 700; }

/* ── Overview cards ── */
.overview-card {
    background: #fff; border: 1.5px solid #ebebeb;
    border-radius: 16px; padding: 26px 22px;
    height: 100%; display: flex; flex-direction: column;
    transition: all .28s ease;
}
.overview-card:hover { border-color: var(--primary); transform: translateY(-4px); box-shadow: 0 14px 36px rgba(2,68,66,.09); }
.ov-icon {
    width: 46px; height: 46px; border-radius: 12px; background: rgba(184,243,90,.14);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; color: var(--primary); margin-bottom: 18px;
}
.ov-title { font-size: .98rem; font-weight: 700; color: var(--primary); margin-bottom: 8px; }
.ov-desc  { font-size: .83rem; font-weight: 300; color: #777; line-height: 1.6; flex-grow: 1; margin-bottom: 16px; }
.ov-btn {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: .77rem; font-weight: 600; color: var(--primary);
    text-decoration: none; background: none; border: none;
    cursor: pointer; padding: 0; font-family: 'Poppins', Helvetica, sans-serif; transition: gap .2s;
}
.ov-btn:hover { gap: 10px; }
.ov-pkg-count { font-size: .68rem; font-weight: 600; color: #bbb; margin-top: 10px; }

/* ── Discount cards ── */
.discount-section {
    padding: 72px 0 64px; background: #fff;
    border-top: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0;
}
.disc-card {
    background: var(--primary); border-radius: 16px;
    padding: 28px 24px; height: 100%; display: flex; flex-direction: column;
    position: relative; overflow: hidden;
    transition: transform .28s ease, box-shadow .28s ease;
}
.disc-card::before {
    content: ''; position: absolute; top: -50px; right: -50px;
    width: 130px; height: 130px; background: rgba(184,243,90,.06); border-radius: 50%;
}
.disc-card:hover { transform: translateY(-5px); box-shadow: 0 18px 40px rgba(2,68,66,.18); }
.disc-pct  { font-size: 3.2rem; font-weight: 800; color: var(--accent); line-height: 1; margin-bottom: 2px; }
.disc-off  { font-size: .6rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: rgba(255,255,255,.3); margin-bottom: 12px; }
.disc-title { font-size: .95rem; font-weight: 700; color: #fff; margin-bottom: 7px; }
.disc-desc  { font-size: .82rem; font-weight: 300; color: rgba(255,255,255,.5); line-height: 1.6; flex-grow: 1; margin-bottom: 14px; }
.disc-code-wrap {
    background: rgba(184,243,90,.06); border: 1px solid rgba(184,243,90,.15);
    border-radius: 10px; padding: 12px 14px; margin-bottom: 12px;
}
.disc-code-label { font-size: .6rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(255,255,255,.35); margin-bottom: 6px; }
.disc-code-row {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
}
.disc-code-val {
    font-size: .95rem; font-weight: 800; color: var(--accent); letter-spacing: 2px;
}
.disc-copy-btn {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(184,243,90,.15); border: 1px solid rgba(184,243,90,.3);
    color: var(--accent); padding: 5px 12px; border-radius: 6px;
    font-size: .7rem; font-weight: 700; cursor: pointer; transition: background .2s;
    font-family: 'Poppins', Helvetica, sans-serif;
}
.disc-copy-btn:hover { background: rgba(184,243,90,.28); }
.disc-use-hint {
    font-size: .72rem; color: rgba(255,255,255,.4); line-height: 1.5;
}
.disc-use-hint i { color: var(--accent); margin-right: 4px; }
.disc-expiry { font-size: .68rem; color: rgba(255,255,255,.25); margin-top: 8px; }

/* ── Package sections ── */
.pkg-section { padding: 88px 0; border-bottom: 1px solid rgba(0,0,0,.05); }
.pkg-section:nth-child(even) { background: #f7f9f5; }
.pkg-section:nth-child(odd)  { background: #fff; }
.pkg-section + .pkg-section  { border-top: 1px solid rgba(0,0,0,.05); }

.pkg-section-num   { font-size: 4rem; font-weight: 800; color: rgba(2,68,66,.05); line-height: 1; margin-bottom: -14px; display: block; }
.pkg-section-icon  { width: 46px; height: 46px; border-radius: 12px; background: rgba(184,243,90,.12); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: var(--primary); margin-bottom: 12px; }
.pkg-section-title { font-size: clamp(1.4rem, 2.6vw, 1.9rem); font-weight: 700; color: var(--primary); margin-bottom: 8px; }
.pkg-section-desc  { font-size: .87rem; font-weight: 300; color: #888; line-height: 1.7; max-width: 360px; margin-bottom: 0; }

/* ── Package cards ── */
.pkg-card {
    background: #fff; border: 1.5px solid #e8e8e8; border-radius: 16px; padding: 32px 26px;
    height: 100%; display: flex; flex-direction: column; position: relative; transition: all .28s ease;
}
.pkg-card:hover { border-color: var(--primary); transform: translateY(-4px); box-shadow: 0 16px 40px rgba(2,68,66,.09); }
.pkg-card.featured {
    background: var(--primary); border-color: var(--primary);
    box-shadow: 0 18px 44px rgba(2,68,66,.18); transform: scale(1.03); z-index: 2;
}
.pkg-card.featured:hover { transform: scale(1.03) translateY(-4px); }
.pkg-featured-badge {
    position: absolute; top: -11px; left: 50%; transform: translateX(-50%);
    background: var(--accent); color: var(--primary);
    font-size: .58rem; font-weight: 800; letter-spacing: 1.5px;
    text-transform: uppercase; padding: 4px 12px; border-radius: 20px; white-space: nowrap;
}
.pkg-name  { font-size: 1rem; font-weight: 600; color: var(--primary); margin-bottom: 4px; }
.pkg-card.featured .pkg-name { color: #fff; }
.pkg-price { font-size: 1.7rem; font-weight: 800; color: var(--primary); margin-bottom: 18px; line-height: 1; }
.pkg-price span { font-size: .75rem; font-weight: 400; color: #bbb; }
.pkg-card.featured .pkg-price { color: var(--accent); }
.pkg-card.featured .pkg-price span { color: rgba(255,255,255,.3); }
.pkg-divider { height: 1px; background: #f0f0f0; margin-bottom: 18px; }
.pkg-card.featured .pkg-divider { background: rgba(255,255,255,.1); }
.pkg-features { list-style: none; padding: 0; margin: 0 0 24px; flex-grow: 1; }
.pkg-features li { display: flex; align-items: flex-start; gap: 10px; font-size: .84rem; font-weight: 300; color: #666; line-height: 1.5; margin-bottom: 10px; }
.pkg-features li i { color: var(--primary); font-size: .78rem; margin-top: 3px; flex-shrink: 0; }
.pkg-card.featured .pkg-features li { color: rgba(255,255,255,.7); }
.pkg-card.featured .pkg-features li i { color: var(--accent); }
.pkg-btn {
    display: block; width: 100%; padding: 12px; border-radius: 50px; text-align: center;
    text-decoration: none; font-weight: 600; font-size: .82rem;
    border: 1.5px solid var(--primary); color: var(--primary);
    background: transparent; cursor: pointer; transition: all .22s;
    font-family: 'Poppins', Helvetica, sans-serif;
}
.pkg-btn:hover { background: var(--primary); color: #fff; box-shadow: 0 6px 18px rgba(2,68,66,.18); }
.pkg-card.featured .pkg-btn { background: var(--accent); border-color: var(--accent); color: var(--primary); }
.pkg-card.featured .pkg-btn:hover { background: #fff; border-color: #fff; }

/* ── CTA banner ── */
.cta-banner {
    background: var(--primary); border-radius: 20px;
    padding: 40px 50px; margin-bottom: 0;
    position: relative; overflow: hidden;
}
.cta-banner::after {
    content: ''; position: absolute; right: -60px; bottom: -60px;
    width: 260px; height: 260px;
    background: radial-gradient(circle, rgba(184,243,90,.1) 0%, transparent 70%);
    border-radius: 50%;
}
.cta-title    { font-size: clamp(1.4rem, 2.5vw, 2rem); font-weight: 700; color: #fff; line-height: 1.25; margin-bottom: 8px; }
.cta-subtitle { font-size: .9rem; font-weight: 300; color: rgba(255,255,255,.55); margin-bottom: 0; }
.price-section { text-align: right; position: relative; z-index: 1; }
.price-label  { font-size: .65rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: rgba(255,255,255,.35); margin-bottom: 6px; }
.price-amount { font-size: 2.6rem; font-weight: 800; color: var(--accent); line-height: 1; margin-bottom: 18px; }

/* ══════════════════════════════
   MOBILE: Tab-switcher
══════════════════════════════ */
@media (max-width: 767px) {
    .srv-hero { padding: 110px 0 40px; }

    /* Filter bar stacks vertically */
    .srv-filter-bar { flex-direction: column !important; align-items: stretch !important; gap: 10px !important; padding-bottom: 14px !important; margin-bottom: 20px !important; }
    .srv-filter-pills { display: flex !important; flex-wrap: nowrap !important; overflow-x: scroll !important; overflow-y: hidden !important; -webkit-overflow-scrolling: touch !important; scrollbar-width: none !important; gap: 8px !important; width: 100% !important; padding-bottom: 2px !important; }
    .srv-filter-pills::-webkit-scrollbar { display: none !important; }
    .srv-filter-btn { flex-shrink: 0 !important; flex-grow: 0 !important; white-space: nowrap !important; font-size: .73rem !important; padding: 7px 15px !important; }
    .srv-show-row { display: flex !important; align-items: center !important; gap: 8px !important; background: #f3f4f2 !important; border-radius: 10px !important; padding: 8px 12px !important; width: 100% !important; }
    .srv-show-btns { display: flex !important; flex: 1 !important; gap: 5px !important; }
    .srv-show-btn  { flex: 1 !important; width: auto !important; height: 30px !important; font-size: .73rem !important; }

    /* Hide desktop grid */
    .pkg-desktop-row { display: none !important; }

    /* Mobile tab switcher */
    .pkg-mobile-wrap { display: block; }
    .pkg-tab-ribbon {
        display: flex; background: rgba(2,68,66,.06);
        border-radius: 12px; padding: 4px; margin-bottom: 16px; gap: 3px;
    }
    .pkg-tab-btn {
        flex: 1; padding: 9px 4px; border: none; background: none; border-radius: 9px;
        font-family: 'Poppins', Helvetica, sans-serif; font-size: .7rem; font-weight: 600;
        color: #888; cursor: pointer; transition: all .2s; text-align: center;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .pkg-tab-btn.active { background: var(--primary); color: #fff; box-shadow: 0 2px 8px rgba(2,68,66,.2); }
    .pkg-tab-btn.is-featured.active { color: var(--accent); }

    .pkg-mobile-item { display: none; }
    .pkg-mobile-item.active { display: block; }

    /* Compact card on mobile — no tall height */
    .pkg-mobile-item .pkg-card {
        transform: none !important; border-radius: 16px !important;
        height: auto !important; min-height: unset !important;
        padding: 24px 20px !important;
    }
    .pkg-mobile-item .pkg-card.featured { transform: none !important; }
    /* Limit features list on mobile so card isn't too tall */
    .pkg-mobile-item .pkg-features { margin-bottom: 20px; }
    .pkg-mobile-item .pkg-features li { margin-bottom: 8px; font-size: .82rem; }

    /* Section paddings */
    .pkg-section { padding: 56px 0 !important; }
    .pkg-section-desc { max-width: 100%; }
    .disc-pct { font-size: 2.6rem; }
    .srv-overview { padding: 52px 0 44px; }
    .discount-section { padding: 52px 0 44px; }

    /* CTA responsive */
    .cta-banner { padding: 30px 24px; }
    .price-section { text-align: left; margin-top: 24px; }
    .price-amount { font-size: 2rem; }
}

/* Hide mobile elements on desktop */
@media (min-width: 768px) {
    .pkg-mobile-wrap { display: none !important; }
    .pkg-tab-ribbon  { display: none !important; }
    .srv-show-row.mobile-show { display: none !important; }
}

/* Medium screens */
@media (min-width: 768px) and (max-width: 991px) {
    .overview-card { padding: 22px 18px; }
}
</style>

<div class="srv-page">

<section class="srv-hero">
    <div class="container srv-hero-inner">
        <div class="row">
            <div class="col-lg-8">
                <div class="srv-eyebrow"><i class="fas fa-layer-group"></i> What We Offer</div>
                <h1 class="srv-hero-title">Services built for<br><span>real results</span></h1>
                <p class="srv-hero-sub">From bold brand identities to high-converting websites — every service is crafted with intention, precision, and your growth in mind.</p>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($discounts)): ?>
<div class="discount-strip">
    <div class="discount-track">
        <?php
        for ($r = 0; $r < 4; $r++):
            foreach ($discounts as $d):
                $disc_pct = isset($d['discount_percent']) ? floatval($d['discount_percent']) : 0;
                $disc_title = 'Special Offer';
                if (!empty($d['title'])) $disc_title = $d['title'];
                elseif (!empty($d['name'])) $disc_title = $d['name'];
        ?>
        <div class="discount-chip">
            <span class="chip-pct"><?php echo $disc_pct; ?>% OFF</span>
            <?php echo htmlspecialchars($disc_title); ?>
            <?php if (!empty($d['code'])): ?>&mdash; Code: <strong><?php echo htmlspecialchars($d['code']); ?></strong><?php endif; ?>
        </div>
        <?php endforeach; endfor; ?>
    </div>
</div>
<?php endif; ?>

<section class="srv-overview">
    <div class="container">
        <div class="row mb-5">
            <div class="col-lg-6">
                <span class="srv-eyebrow-sm">Our Services</span>
                <h2 class="srv-section-h">Everything your<br>brand needs</h2>
            </div>
            <div class="col-lg-6 d-flex align-items-end">
                <p style="color:#aaa;font-size:.85rem;font-weight:300;line-height:1.7;margin-bottom:0;">
                    From branding to web design — explore what we do and pick the package that fits.
                </p>
            </div>
        </div>

        <div class="srv-filter-bar">
            <div class="srv-filter-pills">
                <button class="srv-filter-btn active" data-filter="all">All</button>
                <?php foreach ($data as $sid => $s): ?>
                <button class="srv-filter-btn" data-filter="srv-<?= $sid ?>"><?= htmlspecialchars($s['title']) ?></button>
                <?php endforeach; ?>
            </div>
            <div class="srv-show-row">
                <span class="srv-show-label">Show:</span>
                <div class="srv-show-btns">
                    <button class="srv-show-btn active" data-show="4">4</button>
                    <button class="srv-show-btn" data-show="8">8</button>
                    <button class="srv-show-btn" data-show="12">12</button>
                    <button class="srv-show-btn" data-show="all">All</button>
                </div>
            </div>
        </div>

        <div class="row g-4" id="overviewGrid">
            <?php $oi = 0; foreach ($data as $sid => $service):
                $icon      = !empty($service['icon']) ? $service['icon'] : $fallback_icons[$oi % count($fallback_icons)];
                $desc      = !empty($service['desc']) ? $service['desc'] : ($fallback_descs[$service['title']] ?? 'Professional service tailored to your business needs.');
                $pkg_count = count($service['packages']);
            ?>
            <div class="col-lg-3 col-md-6 srv-ov-col" data-aos="fade-up" data-aos-duration="500" data-service="srv-<?= $sid ?>" data-index="<?= $oi ?>">
                <div class="overview-card">
                    <div class="ov-icon"><i class="fas <?= htmlspecialchars($icon) ?>"></i></div>
                    <div class="ov-title"><?= htmlspecialchars($service['title']) ?></div>
                    <div class="ov-desc"><?= htmlspecialchars($desc) ?></div>
                    <a href="#service-<?= $sid ?>" class="ov-btn srv-smooth">Explore Packages <i class="fas fa-arrow-right"></i></a>
                    <div class="ov-pkg-count"><?= $pkg_count ?> package<?= $pkg_count !== 1 ? 's' : '' ?> available</div>
                </div>
            </div>
            <?php $oi++; endforeach; ?>
        </div>
    </div>
</section>

<?php if (!empty($discounts)): ?>
<section class="discount-section" data-aos="fade-up" data-aos-duration="400">
    <div class="container">
        <div class="row mb-4">
            <div class="col-lg-5">
                <span class="srv-eyebrow-sm"><i class="fas fa-bookmark" style="color: var(--primary);"></i> Limited Time Offers</span>
                <h2 class="srv-section-h">Save more on<br>every service</h2>
            </div>
            <div class="col-lg-7 d-flex align-items-end">
                <p style="color:#bbb;font-size:.86rem;font-weight:300;line-height:1.7;margin-bottom:0;">
                    These are time-limited discount codes. Copy the code, then paste it in the <strong style="color:#555;">discount code field</strong> when submitting your project request.
                </p>
            </div>
        </div>
        <div class="row g-4">
            <?php foreach ($discounts as $d): 
                $disc_pct = isset($d['discount_percent']) ? floatval($d['discount_percent']) : 0;
                $disc_title = 'Special Offer';
                if (!empty($d['title'])) $disc_title = $d['title'];
                elseif (!empty($d['name'])) $disc_title = $d['name'];
                $disc_desc = !empty($d['description']) ? $d['description'] : 'Apply this discount to any eligible service.';
            ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-duration="500">
                <div class="disc-card">
                    <div class="disc-pct"><?php echo $disc_pct; ?>%</div>
                    <div class="disc-off">OFF Your Project</div>
                    <div class="disc-title"><?php echo htmlspecialchars($disc_title); ?></div>
                    <div class="disc-desc"><?php echo htmlspecialchars($disc_desc); ?></div>
                    <?php if (!empty($d['code'])): ?>
                    <div class="disc-code-wrap">
                        <div class="disc-code-label">Your Discount Code</div>
                        <div class="disc-code-row">
                            <span class="disc-code-val"><?php echo htmlspecialchars($d['code']); ?></span>
                            <button class="disc-copy-btn" onclick="copyCode('<?php echo htmlspecialchars(addslashes($d['code'])); ?>', this)">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                    <div class="disc-use-hint">
                        <i class="fas fa-info-circle"></i>
                        Paste this code in the <em>Discount Code</em> field when you submit your project request below.
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($d['expires_date']) && $d['expires_date'] !== '0000-00-00' && $d['expires_date'] !== '0000-00-00 00:00:00'): ?>
                    <div class="disc-expiry">⏳ Expires <?php echo date('M d, Y', strtotime($d['expires_date'])); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php $idx = 0; foreach ($data as $sid => $service):
    $idx++;
    $icon     = !empty($service['icon']) ? $service['icon'] : $fallback_icons[($idx-1) % count($fallback_icons)];
    $desc     = !empty($service['desc']) ? $service['desc'] : ($fallback_descs[$service['title']] ?? '');
    $pkg_list = array_values($service['packages']);
?>
<section class="pkg-section" data-aos="fade-up" data-aos-duration="400" id="service-<?= $sid ?>">
    <div class="container">
        <div class="row mb-4 align-items-end">
            <div class="col-lg-5">
                <span class="pkg-section-num"><?= str_pad($idx, 2, '0', STR_PAD_LEFT) ?></span>
                <div class="pkg-section-icon"><i class="fas <?= htmlspecialchars($icon) ?>"></i></div>
                <h2 class="pkg-section-title"><?= htmlspecialchars($service['title']) ?></h2>
                <?php if ($desc): ?><p class="pkg-section-desc"><?= htmlspecialchars($desc) ?></p><?php endif; ?>
            </div>
            <div class="col-lg-7 d-flex justify-content-lg-end align-items-end">
                <a href="#projectRequestModal"
                   data-bs-toggle="modal" data-bs-target="#projectRequestModal"
                   data-service="<?= htmlspecialchars($service['title']) ?>"
                   onclick="setModalService('<?= addslashes($service['title']) ?>', '', '')"
                   style="font-size:.78rem;font-weight:600;color:var(--primary);text-decoration:none;opacity:.55;transition:opacity .2s;display:inline-flex;align-items:center;gap:7px;"
                   onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.55">
                    <i class="fas fa-paper-plane"></i> Need a custom scope?
                </a>
            </div>
        </div>

        <?php if (!empty($pkg_list)): ?>

        <div class="row align-items-stretch justify-content-center g-4 pkg-desktop-row">
            <?php foreach ($pkg_list as $pkg):
                $is_feat = !empty($pkg['is_featured']);
            ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-duration="500">
                <div class="pkg-card <?= $is_feat ? 'featured' : '' ?>">
                    <?php if ($is_feat): ?><div class="pkg-featured-badge"><i class="fas fa-star" style="color: var(--primary);"></i> Most Popular</div><?php endif; ?>
                    <div class="pkg-name"><?= htmlspecialchars($pkg['name']) ?></div>
                    <?php if (!empty($pkg['price'])): ?>
                    <div class="pkg-price">
                        
                        <?php if ($pkg['price'] > 0): ?>
                        Rs. <?= number_format($pkg['price']) ?>
                            <span> / project</span>
                        <?php endif; ?>
                    </div>                    <?php endif; ?>
                    <div class="pkg-divider"></div>
                    <ul class="pkg-features">
                        <?php foreach ($pkg['features'] as $ft): ?>
                        <li><i class="fas fa-check"></i><span><?= htmlspecialchars($ft) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="mt-auto">
                        <a href="#projectRequestModal"
                           data-bs-toggle="modal" data-bs-target="#projectRequestModal"
                           class="pkg-btn"
                           onclick="setModalService('<?= addslashes($service['title']) ?>', '<?= addslashes($pkg['name']) ?>', '<?= !empty($pkg['price']) ? 'Rs. '.number_format($pkg['price']) : '' ?>')">
                            Get Started →
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="pkg-mobile-wrap">
            <div class="pkg-tab-ribbon">
                <?php foreach ($pkg_list as $pi => $pkg):
                    $is_feat = !empty($pkg['is_featured']);
                ?>
                <button class="pkg-tab-btn <?= $is_feat ? 'is-featured' : '' ?> <?= $pi === 0 ? 'active' : '' ?>"
                    onclick="switchPkg('<?= $sid ?>', <?= $pi ?>)">
                    <?= htmlspecialchars($pkg['name']) ?>
                </button>
                <?php endforeach; ?>
            </div>
            <?php foreach ($pkg_list as $pi => $pkg):
                $is_feat = !empty($pkg['is_featured']);
            ?>
            <div class="pkg-mobile-item <?= $pi === 0 ? 'active' : '' ?>" id="pkgitem-<?= $sid ?>-<?= $pi ?>">
                <div class="pkg-card <?= $is_feat ? 'featured' : '' ?>">
                    <div class="pkg-name"><?= htmlspecialchars($pkg['name']) ?></div>
                    <?php if (!empty($pkg['price'])): ?>
                    <div class="pkg-price">Rs. <?= number_format($pkg['price']) ?><span> / project</span></div>
                    <?php endif; ?>
                    <div class="pkg-divider"></div>
                    <ul class="pkg-features">
                        <?php foreach ($pkg['features'] as $ft): ?>
                        <li><i class="fas fa-check"></i><span><?= htmlspecialchars($ft) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <div style="margin-top:20px;">
                        <a href="#projectRequestModal"
                           data-bs-toggle="modal" data-bs-target="#projectRequestModal"
                           class="pkg-btn"
                           onclick="setModalService('<?= addslashes($service['title']) ?>', '<?= addslashes($pkg['name']) ?>', '<?= !empty($pkg['price']) ? 'Rs. '.number_format($pkg['price']) : '' ?>')">
                            Get Started →
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <div class="text-center py-5">
            <p style="color:#bbb;font-weight:300;font-size:.9rem;">Custom scopes available on request.</p>
            <a href="#projectRequestModal" data-bs-toggle="modal" data-bs-target="#projectRequestModal"
               class="pkg-btn" style="display:inline-block;width:auto;padding:12px 32px;margin-top:14px;"
               onclick="setModalService('<?= addslashes($service['title']) ?>', 'Custom', '')">
                Get Started →
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endforeach; ?>

<div class="container my-5">
    <div class="row">
        <div class="col-12">
            <div class="cta-banner" data-aos="zoom-in">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h2 class="cta-title">Your business deserves a better website.</h2>
                        <p class="cta-subtitle">Graphicafix makes it happen — let's talk today.</p>
                    </div>
                    <div class="col-lg-4">
                        <div class="price-section">
                            <div class="price-label">Starting From</div>
                            <div class="price-amount">Rs. 25,000</div>
                            <a href="#projectRequestModal"
                               data-bs-toggle="modal"
                               data-bs-target="#projectRequestModal"
                               class="main-btn"
                               onclick="setModalService('General Inquiry', '', '')"
                               style="display:inline-flex;align-items:center;gap:8px;">
                                Get Started <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div><script>
// ── Pass service/package into modal ───────────────────────────────────────────
function setModalService(service, pkg, price) {
    // Set hidden fields if they exist in the modal form
    const svcField = document.getElementById('modal_service_type');
    const pkgField = document.getElementById('modal_package_name');
    const prcField = document.getElementById('modal_package_price');
    if (svcField) svcField.value = service;
    if (pkgField) pkgField.value = pkg;
    if (prcField) prcField.value = price;

    // Pre-select project type dropdown if it matches
    const typeSelect = document.getElementById('projectType');
    if (typeSelect && service) {
        [...typeSelect.options].forEach(opt => {
            if (opt.text.toLowerCase().includes(service.toLowerCase().split(' ')[0])) {
                opt.selected = true;
            }
        });
    }

    // Show selected package in modal subtitle if elements exist
    const subtitle = document.querySelector('#projectRequestModal .modal-subtitle');
    if (subtitle) {
        if (pkg) {
            subtitle.textContent = `${service} — ${pkg}${price ? ' ('+price+')' : ''}`;
        } else if (service) {
            subtitle.textContent = `${service}`;
        } else {
            subtitle.textContent = "Let's bring your vision to life";
        }
    }
}

// ── Mobile package tab switcher ───────────────────────────────────────────────
function switchPkg(sid, idx) {
    document.querySelectorAll('[id^="pkgitem-' + sid + '-"]').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('#service-' + sid + ' .pkg-tab-btn').forEach(el => el.classList.remove('active'));
    const item = document.getElementById('pkgitem-' + sid + '-' + idx);
    const tabs = document.querySelectorAll('#service-' + sid + ' .pkg-tab-btn');
    if (item)     item.classList.add('active');
    if (tabs[idx]) tabs[idx].classList.add('active');
}

// ── Smooth scroll ─────────────────────────────────────────────────────────────
document.querySelectorAll('.srv-smooth, .ov-btn').forEach(a => {
    a.addEventListener('click', e => {
        const href = a.getAttribute('href');
        if (!href || !href.startsWith('#') || href === '#projectRequestModal') return;
        const target = document.querySelector(href);
        if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
});

// ── Filter + show limit ───────────────────────────────────────────────────────
const ovCols = document.querySelectorAll('.srv-ov-col');
let currentFilter = 'all';
let currentShow   = 4;

function applyGrid() {
    let visible = 0;
    ovCols.forEach(col => {
        const match = currentFilter === 'all' || col.dataset.service === currentFilter;
        const fits  = currentShow === 'all' || visible < currentShow;
        if (match && fits) { col.style.display = ''; visible++; }
        else               { col.style.display = 'none'; }
    });
}

document.querySelectorAll('.srv-filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.srv-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentFilter = btn.dataset.filter;
        applyGrid();
    });
});

document.querySelectorAll('.srv-show-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.srv-show-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentShow = btn.dataset.show === 'all' ? 'all' : parseInt(btn.dataset.show);
        applyGrid();
    });
});

applyGrid();

// ── Copy discount code ────────────────────────────────────────────────────────
function copyCode(code, el) {
    navigator.clipboard.writeText(code).then(() => {
        const orig = el.innerHTML;
        el.innerHTML = '<i class="fas fa-check"></i> Copied!';
        el.style.background = 'rgba(184,243,90,.4)';
        setTimeout(() => { el.innerHTML = orig; el.style.background = ''; }, 2200);
    });
}
</script>

<?php include('footer.php'); ?>