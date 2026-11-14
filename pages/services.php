<?php
require_once __DIR__ . '/../core/config.php';

// ── SEO & Structured Data Parameters ─────────────────────────────────────────
$page_title = "Digital Services | Web, Software, Marketing & AI – Graphicafix";
$meta_description = "Discover end-to-end digital services from Graphicafix, including branding, web development, software solutions, digital marketing, and AI automation.";
$meta_keywords = "Digital services, website development company, web design services, custom software development, digital marketing services, AI automation services, business automation, mobile app development, website maintenance services, branding agency, graphic design services";
$canonical_url = "https://graphicafix.com/services";

$extra_schema_json = json_encode([
    "@type" => "ItemList",
    "name" => "Graphicafix Digital Solutions & Services",
    "description" => "Complete digital solutions portfolio including branding, web design, software development, marketing, and AI automation.",
    "itemListElement" => [
        [
            "@type" => "Service",
            "position" => 1,
            "name" => "Branding & Creative Design",
            "description" => "Bespoke brand strategy, visual identities, logos, style guidelines, and marketing collaterals.",
            "provider" => ["@id" => "https://graphicafix.com/#organization"],
            "url" => "https://graphicafix.com/services#branding-creative-design"
        ],
        [
            "@type" => "Service",
            "position" => 2,
            "name" => "Website Design & Development",
            "description" => "High-performance, responsive websites and web applications built with modern frameworks and SEO.",
            "provider" => ["@id" => "https://graphicafix.com/#organization"],
            "url" => "https://graphicafix.com/services#website-design-development"
        ],
        [
            "@type" => "Service",
            "position" => 3,
            "name" => "Custom Software & SaaS Development",
            "description" => "Tailored business software, CRM systems, web applications, and scalable multi-tenant SaaS products.",
            "provider" => ["@id" => "https://graphicafix.com/#organization"],
            "url" => "https://graphicafix.com/services#custom-software-saas"
        ],
        [
            "@type" => "Service",
            "position" => 4,
            "name" => "Digital Marketing & Growth",
            "description" => "Targeted search engine optimization (SEO), performance campaigns, and strategic content pipelines.",
            "provider" => ["@id" => "https://graphicafix.com/#organization"],
            "url" => "https://graphicafix.com/services#digital-marketing-growth"
        ],
        [
            "@type" => "Service",
            "position" => 5,
            "name" => "AI & Business Automation",
            "description" => "Custom AI assistants, intelligent workflow automations, and operational integrations.",
            "provider" => ["@id" => "https://graphicafix.com/#organization"],
            "url" => "https://graphicafix.com/services#ai-business-automation"
        ],
        [
            "@type" => "Service",
            "position" => 6,
            "name" => "Mobile App Development",
            "description" => "Intuitive, cross-platform and native iOS and Android applications engineered for fluid user journeys.",
            "provider" => ["@id" => "https://graphicafix.com/#organization"],
            "url" => "https://graphicafix.com/services#mobile-app-development"
        ],
        [
            "@type" => "Service",
            "position" => 7,
            "name" => "Website Management & Support",
            "description" => "Proactive maintenance, 24/7 security monitoring, speed optimization, and ongoing technical support.",
            "provider" => ["@id" => "https://graphicafix.com/#organization"],
            "url" => "https://graphicafix.com/services#website-management-support"
        ]
    ]
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

include __DIR__ . '/../templates/header.php';

// ── Fetch services with packages + features from database ─────────────────────
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
$stmt->close();

// ── Active discounts ─────────────────────────────────────────────────────────
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

// ── Define 7 Core Pillars (SEO-Driven Architecture) ──────────────────────────
$core_pillars = [
    'branding-creative-design' => [
        'num'      => '01',
        'title'    => 'Branding & Creative Design',
        'keywords' => 'Branding agency, graphic design services',
        'tagline'  => 'Brand Strategy, Visual Identity & Creative Direction',
        'icon'     => 'fa-palette',
        'desc'     => 'We craft distinctive visual identities, bespoke logo systems, corporate style guidelines, and high-impact marketing collateral. Our branding agency approach balances psychological resonance with timeless design to ensure your business cuts through saturated markets.',
        'benefits' => [
            'Complete Brand Systems'      => 'Comprehensive logo marks, typography scales, color rules, and brand manuals.',
            'Omnichannel Consistency'     => 'Cohesive visual presentation across web platforms, social channels, and print.',
            'Vector & Print-Ready Assets' => 'Master SVGs, CMYK packaging files, and web-optimized PNGs delivered ready for production.',
            'Enhanced Customer Loyalty'   => 'Memorable brand recognition that creates long-term value and elevated perceived trust.'
        ],
        'process'  => [
            '1. Discovery & Strategy'     => 'Analyzing your audience, competitors, and value proposition to establish a definitive brand direction.',
            '2. Visual Prototyping'       => 'Exploring diverse logo concepts, typeface pairings, and visual moodboards.',
            '3. Refinement & Calibration' => 'Refining the chosen concept through structured review cycles for optimal impact.',
            '4. Style Guide & Delivery'   => 'Exporting complete asset kits accompanied by a practical corporate brand guideline.'
        ],
        'db_match_ids' => [14, 15, 16, 17, 18, 20] // Matches existing DB services like Branding Bundles, Logo, Rebranding
    ],
    'website-design-development' => [
        'num'      => '02',
        'title'    => 'Website Design & Development',
        'keywords' => 'Website development company, web design services',
        'tagline'  => 'High-Performance, Responsive & SEO-Ready Web Solutions',
        'icon'     => 'fa-laptop-code',
        'desc'     => 'As a full-service website development company, Graphicafix engineers ultra-responsive, accessible, and conversion-focused web properties. We build with clean architectures, ensuring rapid loading speeds, top-tier Core Web Vitals, and an intuitive user experience on every device.',
        'benefits' => [
            'Mobile-First Responsiveness' => 'Fluid layouts designed to convert seamlessly across smartphones, tablets, and desktops.',
            'Core Web Vitals Optimized'  => 'Under-2-second page loads engineered with lightweight modern code and asset caching.',
            'Native Search Engine Optimization' => 'Semantic HTML5 structure, Open Graph tags, canonical links, and Schema.org markup.',
            'Empowered Content Control'   => 'Intuitive content management interfaces allowing your team to update content effortlessly.'
        ],
        'process'  => [
            '1. Information Architecture' => 'Organizing page hierarchies, sitemaps, and user flow wireframes focused on conversions.',
            '2. UI/UX Design Mockups'    => 'High-fidelity Figma prototypes highlighting brand personality and interaction states.',
            '3. Clean Code Engineering'   => 'Building frontend layouts backed by performant, secure backend logic.',
            '4. Cross-Browser QA & Launch' => 'Comprehensive responsiveness, security, and speed testing followed by live deployment.'
        ],
        'db_match_ids' => []
    ],
    'custom-software-saas' => [
        'num'      => '03',
        'title'    => 'Custom Software & SaaS Development',
        'keywords' => 'Custom software development, SaaS development company',
        'tagline'  => 'Scalable Web Applications, Business Portals & Multi-Tenant SaaS',
        'icon'     => 'fa-cubes',
        'desc'     => 'We engineer custom business software, client portals, internal CRM/ERP platforms, and enterprise SaaS solutions designed around your exact business model. Eliminate the limitations of generic SaaS with purpose-built systems that scale seamlessly with your growth.',
        'benefits' => [
            'Tailored Business Logic'     => 'Software engineered to match your operational workflows without generic restrictions.',
            'Scalable Cloud Architecture' => 'Multi-tenant database structures designed to handle expanding transaction volumes.',
            'Secure API Integrations'     => 'Robust integrations with payment gateways, third-party APIs, and automation webhooks.',
            'Role-Based Access & Security'=> 'Enterprise-grade encryption, JWT/session authentication, and granular user roles.'
        ],
        'process'  => [
            '1. Architecture & Blueprint' => 'System requirements analysis, ERD database design, and technical stack specification.',
            '2. Agile Sprint Milestones'  => 'Incremental feature delivery with interactive sprint reviews and clear milestones.',
            '3. Rigorous Security Audits' => 'Penetration testing, load profiling, and automated edge-case validation.',
            '4. Production Cloud Rollout' => 'Automated CI/CD deployment, server hardening, and disaster recovery setups.'
        ],
        'db_match_ids' => []
    ],
    'digital-marketing-growth' => [
        'num'      => '04',
        'title'    => 'Digital Marketing & Growth',
        'keywords' => 'Digital marketing services, digital marketing agency',
        'tagline'  => 'Data-Driven SEO, Paid Advertising & Content Strategy',
        'icon'     => 'fa-bullhorn',
        'desc'     => 'Scale your business pipeline with data-driven digital marketing services. We combine organic search engine optimization (SEO), paid search and social campaigns, and persuasive content creation to attract high-intent leads and maximize your return on ad spend (ROAS).',
        'benefits' => [
            'High-Intent Lead Generation' => 'Campaigns crafted to target decision-makers actively searching for your solutions.',
            'Organic Search Dominance'    => 'Targeted keyword clustering, on-page optimization, and authoritative backlink strategies.',
            'Compelling Content Assets'   => 'SEO-optimized articles, whitepapers, social collateral, and high-conversion landing pages.',
            'Transparent Performance ROI' => 'Real-time performance analytics dashboards with honest attribution metrics.'
        ],
        'process'  => [
            '1. Market & Keyword Audit'   => 'Deep research into competitor vulnerabilities, search intent, and paid bid opportunities.',
            '2. Strategy & Funnel Setup'  => 'Configuring conversion tracking pixels, custom landing pages, and automated funnels.',
            '3. Campaign Launch & Testing' => 'Running multivariant A/B tests across creatives, hooks, and audience segments.',
            '4. Scaling & Optimization'   => 'Doubling down on top-performing ad sets and search queries for compounding returns.'
        ],
        'db_match_ids' => [19] // Matches Content Writing
    ],
    'ai-business-automation' => [
        'num'      => '05',
        'title'    => 'AI & Business Automation',
        'keywords' => 'AI automation services, business automation',
        'tagline'  => 'Intelligent Workflows, AI Chatbots & Operational Automation',
        'icon'     => 'fa-robot',
        'desc'     => 'Empower your organization with cutting-edge AI automation services. From bespoke generative AI assistants and intelligent customer service agents to automated operational workflows between CRMs, databases, and emails, we eradicate manual busywork and lower operating costs.',
        'benefits' => [
            '24/7 AI Customer Concierge'  => 'Intelligent agents trained on your documentation resolving inquiries instantly.',
            'Zero-Touch Workflow Pipelines' => 'Automated data handoffs across CRMs, invoicing software, spreadsheets, and emails.',
            'Document Processing at Scale' => 'Instant AI extraction, classification, and summarization of forms and contracts.',
            'Massive Productivity Gains'  => 'Reclaim hundreds of team hours per month while eliminating human error.'
        ],
        'process'  => [
            '1. Workflow Audit'           => 'Identifying high-friction manual operational bottlenecks across your business.',
            '2. Architecture & Model Match' => 'Selecting optimal LLMs (Gemini, Claude, OpenAI) and secure webhook architectures.',
            '3. Security & Sandboxing'    => 'Strict data privacy controls, hallucination guards, and rigorous testing.',
            '4. Live Integration & Training'=> 'Deploying into live production systems with comprehensive staff enablement.'
        ],
        'db_match_ids' => []
    ],
    'mobile-app-development' => [
        'num'      => '06',
        'title'    => 'Mobile App Development',
        'keywords' => 'Mobile app development, mobile applications',
        'tagline'  => 'Intuitive iOS & Android Mobile Applications',
        'icon'     => 'fa-mobile-alt',
        'desc'     => 'We engineer high-performance mobile applications that deliver smooth gesture navigation, rapid responsiveness, and elegant UI design. Whether creating a customer-facing app or a secure field-operations tool, our cross-platform and native builds maximize user retention.',
        'benefits' => [
            'Cost-Effective Cross-Platform' => 'Unified codebase efficiency across iOS and Android with native performance.',
            'Native Hardware Integrations' => 'Seamless camera, GPS location, push notifications, and biometrics utilization.',
            'Offline-First Capability'    => 'Local data caching allowing users to work seamlessly even without network connectivity.',
            'App Store Publishing'        => 'Comprehensive management of Apple App Store and Google Play compliance and release.'
        ],
        'process'  => [
            '1. User Journey Mapping'     => 'Wireframing intuitive tap flows, navigation states, and screen transitions.',
            '2. Interactive Figma Preview' => 'Full-screen mobile prototypes simulating actual device behavior.',
            '3. Agile Mobile Engineering'  => 'Modular frontend coding integrated with resilient cloud REST/GraphQL APIs.',
            '4. Beta Testing & Store Launch'=> 'Internal TestFlight testing, security review, and public app store publishing.'
        ],
        'db_match_ids' => []
    ],
    'website-management-support' => [
        'num'      => '07',
        'title'    => 'Website Management & Support',
        'keywords' => 'Website maintenance services, website support',
        'tagline'  => 'Proactive Maintenance, 24/7 Security & Performance Tuning',
        'icon'     => 'fa-tools',
        'desc'     => 'Keep your website fast, secure, and always accessible. Our website maintenance services provide 24/7 uptime monitoring, daily encrypted backups, proactive security patching, speed optimizations, and priority development support whenever you need an update.',
        'benefits' => [
            '24/7 Health & Uptime Checks' => 'Automated monitoring alerting our engineers before issues impact your users.',
            'Daily Encrypted Backups'     => 'Secure offsite restoration points ensuring zero risk of irreversible data loss.',
            'Continuous Speed Tuning'     => 'Database query optimization, image compression, and cache refreshing for maximum speed.',
            'Dedicated Priority Hours'    => 'Direct access to senior developers for emergency fixes, banners, and feature tweaks.'
        ],
        'process'  => [
            '1. Comprehensive Baseline'   => 'Full security, performance, and code audit to identify latent vulnerabilities.',
            '2. Hardening & Backup Setup' => 'Configuring enterprise firewalls, malware scanners, and automated daily backups.',
            '3. Weekly Maintenance Cycles'=> 'Applying updates, verifying plugin compatibility, and auditing server metrics.',
            '4. Monthly Executive Reports'=> 'Clear summaries of site health, uptime percentages, and security interventions.'
        ],
        'db_match_ids' => []
    ]
];
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
.srv-page * { font-family: 'Poppins', Helvetica, Arial, sans-serif; }

/* ── Hero ── */
.srv-hero {
    background: var(--primary);
    padding: 135px 0 65px;
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
    color: var(--accent); font-size: .68rem; font-weight: 700;
    letter-spacing: 2px; text-transform: uppercase;
    padding: 6px 16px; border-radius: 30px; margin-bottom: 18px;
}
.srv-hero-title {
    font-size: clamp(2rem, 4vw, 3.2rem);
    font-weight: 800; color: #fff; line-height: 1.15; margin-bottom: 16px;
    letter-spacing: -0.02em;
}
.srv-hero-title span { color: var(--accent); }
.srv-hero-sub {
    font-size: 1.05rem; font-weight: 300;
    color: rgba(255,255,255,.8); max-width: 650px;
    line-height: 1.7; margin-bottom: 24px;
}
.srv-hero-actions { display: flex; gap: 14px; flex-wrap: wrap; }
.srv-hero-btn-primary {
    background: var(--accent); color: var(--primary); font-weight: 700;
    padding: 12px 28px; border-radius: 50px; text-decoration: none;
    display: inline-flex; align-items: center; gap: 8px; font-size: .88rem;
    transition: transform .2s ease, box-shadow .2s ease;
}
.srv-hero-btn-primary:hover {
    transform: translateY(-2px); box-shadow: 0 8px 24px rgba(184,243,90,.3); color: var(--primary);
}
.srv-hero-btn-secondary {
    background: transparent; color: #ffffff; border: 1.5px solid rgba(255,255,255,.3);
    font-weight: 600; padding: 12px 26px; border-radius: 50px; text-decoration: none;
    display: inline-flex; align-items: center; gap: 8px; font-size: .88rem;
    transition: all .2s ease;
}
.srv-hero-btn-secondary:hover {
    background: rgba(255,255,255,.1); border-color: #ffffff; color: #ffffff;
}

/* ── Discount ticker ── */
.discount-strip { background: var(--accent); overflow: hidden; }
.discount-track {
    display: flex;
    width: max-content;
    animation: scrollTrack 35s linear infinite;
}
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

/* ── Overview Section & Quick Anchor Nav ── */
.srv-overview {
    padding: 75px 0 65px; background: #f7f9f5;
    border-bottom: 1px solid #e8e8e8;
}
.srv-eyebrow-sm {
    font-size: .65rem; font-weight: 700; letter-spacing: 2px;
    text-transform: uppercase; color: var(--primary); display: block; margin-bottom: 8px;
}
.srv-section-h {
    font-size: clamp(1.6rem, 3vw, 2.3rem);
    font-weight: 800; color: var(--primary); line-height: 1.2;
}

/* Filter / Pillar jump bar */
.srv-filter-bar {
    display: flex; align-items: center; justify-content: flex-start;
    flex-wrap: wrap; gap: 10px; margin-bottom: 35px; padding-bottom: 22px;
    border-bottom: 1px solid #ebebeb;
}
.srv-filter-btn {
    padding: 8px 18px; border-radius: 30px; font-size: .78rem; font-weight: 600;
    border: 1.5px solid #e0e0e0; background: #fff; color: #555; text-decoration: none;
    transition: all .2s; font-family: 'Poppins', Helvetica, sans-serif; white-space: nowrap;
    display: inline-flex; align-items: center; gap: 7px;
}
.srv-filter-btn:hover, .srv-filter-btn.active {
    background: var(--primary); border-color: var(--primary); color: #fff;
    box-shadow: 0 4px 14px rgba(2,68,66,.15);
}
.srv-filter-btn i { font-size: .75rem; color: var(--accent); }

/* Overview Cards */
.overview-card {
    background: #fff; border: 1.5px solid #ebebeb;
    border-radius: 18px; padding: 28px 24px;
    height: 100%; display: flex; flex-direction: column;
    transition: all .28s ease;
}
.overview-card:hover {
    border-color: var(--primary); transform: translateY(-5px);
    box-shadow: 0 16px 36px rgba(2,68,66,.09);
}
.ov-icon {
    width: 48px; height: 48px; border-radius: 14px; background: rgba(184,243,90,.16);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; color: var(--primary); margin-bottom: 18px;
}
.ov-title { font-size: 1.05rem; font-weight: 700; color: var(--primary); margin-bottom: 8px; }
.ov-desc  { font-size: .83rem; font-weight: 300; color: #666; line-height: 1.6; flex-grow: 1; margin-bottom: 18px; }
.ov-btn {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: .78rem; font-weight: 600; color: var(--primary);
    text-decoration: none; cursor: pointer; transition: gap .2s;
}
.ov-btn:hover { gap: 10px; color: var(--primary); }

/* ── Service Pillar Deep-Dive Sections ── */
.pillar-section {
    padding: 95px 0;
    border-bottom: 1px solid rgba(0,0,0,.06);
    position: relative;
}
.pillar-section:nth-child(even) { background: #ffffff; }
.pillar-section:nth-child(odd)  { background: #f9fbf8; }

.pillar-header { margin-bottom: 35px; }
.pillar-num {
    font-size: 3.5rem; font-weight: 850; color: rgba(2,68,66,.08);
    line-height: 1; margin-bottom: -15px; display: block;
}
.pillar-icon-box {
    width: 52px; height: 52px; border-radius: 14px;
    background: rgba(184,243,90,.2); color: var(--primary);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.25rem; margin-bottom: 14px;
}
.pillar-h2 {
    font-size: clamp(1.8rem, 3.2vw, 2.4rem);
    font-weight: 800; color: var(--primary); margin-bottom: 10px;
    letter-spacing: -0.02em;
}
.pillar-tagline {
    font-size: .95rem; font-weight: 600; color: #024442; opacity: .85;
    margin-bottom: 12px; display: block;
}
.pillar-desc {
    font-size: .95rem; font-weight: 300; color: #555;
    line-height: 1.75; max-width: 820px; margin-bottom: 25px;
}
.pillar-keywords {
    display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap;
    background: #ffffff; border: 1px solid rgba(2,68,66,.1);
    padding: 6px 14px; border-radius: 20px; font-size: .75rem; color: #475569;
    margin-bottom: 32px;
}
.pillar-keywords strong { color: var(--primary); }

/* Benefits Grid */
.benefits-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px; margin-bottom: 45px;
}
.benefit-card {
    background: #ffffff; border: 1.5px solid #ebebeb; border-radius: 16px;
    padding: 22px 20px; transition: all .25s ease;
}
.benefit-card:hover {
    border-color: var(--primary); box-shadow: 0 10px 25px rgba(2,68,66,.06);
    transform: translateY(-3px);
}
.benefit-card h4 {
    font-size: .92rem; font-weight: 700; color: var(--primary);
    display: flex; align-items: center; gap: 9px; margin-bottom: 8px;
}
.benefit-card h4 i { color: #10b981; font-size: .85rem; }
.benefit-card p {
    font-size: .82rem; font-weight: 300; color: #666; line-height: 1.6; margin: 0;
}

/* Process Timeline Row */
.process-box {
    background: #ffffff; border: 1px solid #e5e9e2; border-radius: 18px;
    padding: 30px; margin-bottom: 40px; box-shadow: 0 4px 18px rgba(2,68,66,.03);
}
.process-box-title {
    font-size: 1.1rem; font-weight: 750; color: var(--primary); margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
}
.process-box-title i { color: var(--primary); }
.process-steps-row {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}
.process-step-item {
    border-left: 2.5px solid var(--accent); padding-left: 14px;
}
.process-step-name {
    font-size: .86rem; font-weight: 700; color: var(--primary); margin-bottom: 4px;
}
.process-step-desc {
    font-size: .78rem; font-weight: 300; color: #666; line-height: 1.55; margin: 0;
}

/* Pillar Action Footer */
.pillar-action-bar {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 16px; padding-top: 25px;
    border-top: 1px solid rgba(0,0,0,.06);
}
.pillar-cta-btn {
    background: var(--primary); color: #fff; font-weight: 600; font-size: .84rem;
    padding: 12px 28px; border-radius: 50px; text-decoration: none;
    display: inline-flex; align-items: center; gap: 8px; transition: all .22s;
    border: none; cursor: pointer;
}
.pillar-cta-btn:hover {
    background: #035a57; color: var(--accent); transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(2,68,66,.2);
}
.pillar-custom-link {
    font-size: .82rem; font-weight: 600; color: var(--primary); text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px; opacity: .75; transition: opacity .2s;
}
.pillar-custom-link:hover { opacity: 1; color: var(--primary); }

/* Embedded Package Cards within Pillar (e.g. Branding) */
.pkg-card {
    background: #fff; border: 1.5px solid #e8e8e8; border-radius: 16px; padding: 30px 24px;
    height: 100%; display: flex; flex-direction: column; position: relative; transition: all .28s ease;
}
.pkg-card:hover { border-color: var(--primary); transform: translateY(-4px); box-shadow: 0 16px 40px rgba(2,68,66,.09); }
.pkg-card.featured {
    background: var(--primary); border-color: var(--primary);
    box-shadow: 0 18px 44px rgba(2,68,66,.18); transform: scale(1.02); z-index: 2;
}
.pkg-featured-badge {
    position: absolute; top: -11px; left: 50%; transform: translateX(-50%);
    background: var(--accent); color: var(--primary);
    font-size: .58rem; font-weight: 800; letter-spacing: 1.5px;
    text-transform: uppercase; padding: 4px 12px; border-radius: 20px; white-space: nowrap;
}
.pkg-name  { font-size: 1rem; font-weight: 600; color: var(--primary); margin-bottom: 4px; }
.pkg-card.featured .pkg-name { color: #fff; }
.pkg-price { font-size: 1.7rem; font-weight: 800; color: var(--primary); margin-bottom: 16px; line-height: 1; }
.pkg-price span { font-size: .75rem; font-weight: 400; color: #bbb; }
.pkg-card.featured .pkg-price { color: var(--accent); }
.pkg-card.featured .pkg-price span { color: rgba(255,255,255,.3); }
.pkg-divider { height: 1px; background: #f0f0f0; margin-bottom: 16px; }
.pkg-card.featured .pkg-divider { background: rgba(255,255,255,.1); }
.pkg-features { list-style: none; padding: 0; margin: 0 0 22px; flex-grow: 1; }
.pkg-features li { display: flex; align-items: flex-start; gap: 9px; font-size: .82rem; font-weight: 300; color: #666; line-height: 1.5; margin-bottom: 9px; }
.pkg-features li i { color: var(--primary); font-size: .76rem; margin-top: 3px; flex-shrink: 0; }
.pkg-card.featured .pkg-features li { color: rgba(255,255,255,.8); }
.pkg-card.featured .pkg-features li i { color: var(--accent); }
.pkg-btn {
    display: block; width: 100%; padding: 11px; border-radius: 50px; text-align: center;
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
    padding: 45px 50px; margin-bottom: 0;
    position: relative; overflow: hidden;
}
.cta-banner::after {
    content: ''; position: absolute; right: -60px; bottom: -60px;
    width: 260px; height: 260px;
    background: radial-gradient(circle, rgba(184,243,90,.12) 0%, transparent 70%);
    border-radius: 50%;
}
.cta-title    { font-size: clamp(1.5rem, 2.8vw, 2.2rem); font-weight: 800; color: #fff; line-height: 1.25; margin-bottom: 8px; }
.cta-subtitle { font-size: .95rem; font-weight: 300; color: rgba(255,255,255,.7); margin-bottom: 0; }
.price-section { text-align: right; position: relative; z-index: 1; }

@media (max-width: 767px) {
    .srv-hero { padding: 110px 0 45px; }
    .pillar-section { padding: 60px 0; }
    .pillar-action-bar { flex-direction: column; align-items: stretch; text-align: center; }
    .pillar-cta-btn { justify-content: center; width: 100%; }
    .price-section { text-align: left; margin-top: 24px; }
    .cta-banner { padding: 32px 24px; }
}
</style>

<div class="srv-page">

<!-- ════════ HERO SECTION (H1 & Supporting Copy) ════════ -->
<section class="srv-hero">
    <div class="container srv-hero-inner">
        <div class="row align-items-center">
            <div class="col-lg-8" data-aos="fade-up">
                <div class="srv-eyebrow"><i class="fas fa-layer-group"></i> What We Offer</div>
                <h1 class="srv-hero-title">Complete Digital Solutions for<br><span>Modern Businesses</span></h1>
                <p class="srv-hero-sub">Discover end-to-end digital services from Graphicafix, including branding, web development, custom software solutions, digital marketing, and AI automation.</p>
                <div class="srv-hero-actions">
                    <a href="#projectRequestModal" data-bs-toggle="modal" data-bs-target="#projectRequestModal" onclick="setModalService('General Project Inquiry', '', '')" class="srv-hero-btn-primary">
                        <span>Start a Project</span> <i class="fas fa-arrow-right"></i>
                    </a>
                    <a href="<?= BASE_URL ?>portfolio" class="srv-hero-btn-secondary">
                        <span>View Our Portfolio</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════════ DISCOUNT TICKER STRIP ════════ -->
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

<!-- ════════ OVERVIEW & PILLAR NAVIGATION ════════ -->
<section class="srv-overview">
    <div class="container">
        <div class="row mb-4">
            <div class="col-lg-6">
                <span class="srv-eyebrow-sm">Core Capabilities</span>
                <h2 class="srv-section-h">Integrated Services for<br>Sustainable Growth</h2>
            </div>
            <div class="col-lg-6 d-flex align-items-end">
                <p style="color:#64748b;font-size:.9rem;font-weight:300;line-height:1.7;margin-bottom:0;">
                    From bold visual identities to high-converting software and automation pipelines, explore our core capabilities below and jump directly to any discipline.
                </p>
            </div>
        </div>

        <!-- Pillar Jump Navigation -->
        <div class="srv-filter-bar">
            <?php foreach ($core_pillars as $p_id => $pillar): ?>
            <a href="#<?= $p_id ?>" class="srv-filter-btn">
                <i class="fas <?= $pillar['icon'] ?>"></i>
                <span><?= htmlspecialchars($pillar['title']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Overview Cards Grid -->
        <div class="row g-4">
            <?php foreach ($core_pillars as $p_id => $pillar): ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up">
                <div class="overview-card">
                    <div class="ov-icon"><i class="fas <?= $pillar['icon'] ?>"></i></div>
                    <div class="ov-title"><?= htmlspecialchars($pillar['title']) ?></div>
                    <div class="ov-desc"><?= htmlspecialchars($pillar['desc']) ?></div>
                    <a href="#<?= $p_id ?>" class="ov-btn">Explore Discipline &amp; Process <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ════════ 7 CORE SERVICE SECTIONS (H2 & KEYWORDS) ════════ -->
<?php foreach ($core_pillars as $p_id => $pillar): ?>
<section class="pillar-section" id="<?= $p_id ?>" data-aos="fade-up">
    <div class="container">
        <!-- Pillar Header -->
        <div class="pillar-header">
            <span class="pillar-num"><?= $pillar['num'] ?></span>
            <div class="pillar-icon-box"><i class="fas <?= $pillar['icon'] ?>"></i></div>
            <span class="pillar-tagline"><?= htmlspecialchars($pillar['tagline']) ?></span>
            <h2 class="pillar-h2"><?= htmlspecialchars($pillar['title']) ?></h2>
            <p class="pillar-desc"><?= htmlspecialchars($pillar['desc']) ?></p>
            <div class="pillar-keywords">
                <i class="fas fa-search" style="color: var(--primary);"></i>
                <strong>Target Expertise:</strong> <?= htmlspecialchars($pillar['keywords']) ?>
            </div>
        </div>

        <!-- Key Benefits Grid -->
        <div class="benefits-grid">
            <?php foreach ($pillar['benefits'] as $b_title => $b_desc): ?>
            <div class="benefit-card">
                <h4><i class="fas fa-check-circle"></i> <?= htmlspecialchars($b_title) ?></h4>
                <p><?= htmlspecialchars($b_desc) ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Delivery Process Timeline -->
        <div class="process-box">
            <div class="process-box-title">
                <i class="fas fa-stream"></i>
                <span>Our <?= htmlspecialchars($pillar['title']) ?> Delivery Process</span>
            </div>
            <div class="process-steps-row">
                <?php foreach ($pillar['process'] as $step_title => $step_desc): ?>
                <div class="process-step-item">
                    <div class="process-step-name"><?= htmlspecialchars($step_title) ?></div>
                    <p class="process-step-desc"><?= htmlspecialchars($step_desc) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- If this pillar matches existing DB packages, render interactive cards -->
        <?php 
        $has_matched_packages = false;
        foreach ($pillar['db_match_ids'] as $matched_sid) {
            if (isset($data[$matched_sid]) && !empty($data[$matched_sid]['packages'])) {
                $has_matched_packages = true;
                break;
            }
        }
        if ($has_matched_packages):
        ?>
        <div class="mb-4">
            <h4 style="font-size: 1.15rem; font-weight: 700; color: var(--primary); margin-bottom: 16px;">
                <i class="fas fa-tags" style="color: var(--primary);"></i> Ready-to-Order <?= htmlspecialchars($pillar['title']) ?> Packages
            </h4>
            <div class="row g-4">
                <?php 
                foreach ($pillar['db_match_ids'] as $matched_sid):
                    if (!isset($data[$matched_sid])) continue;
                    $srv_item = $data[$matched_sid];
                    foreach ($srv_item['packages'] as $pkg):
                        $is_feat = !empty($pkg['is_featured']);
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="pkg-card <?= $is_feat ? 'featured' : '' ?>">
                        <?php if ($is_feat): ?><div class="pkg-featured-badge"><i class="fas fa-star" style="color: var(--primary);"></i> Most Popular</div><?php endif; ?>
                        <div class="pkg-name"><?= htmlspecialchars($srv_item['title']) ?> — <?= htmlspecialchars($pkg['name']) ?></div>
                        <?php if (!empty($pkg['price']) && $pkg['price'] > 0): ?>
                        <div class="pkg-price">$<?= number_format($pkg['price']) ?><span> / package</span></div>
                        <?php endif; ?>
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
                               onclick="setModalService('<?= addslashes($pillar['title']) ?>', '<?= addslashes($pkg['name']) ?>', '<?= !empty($pkg['price']) ? '$'.number_format($pkg['price']) : '' ?>')">
                                Select Package →
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pillar Action Bar -->
        <div class="pillar-action-bar">
            <div>
                <a href="#projectRequestModal"
                   data-bs-toggle="modal" data-bs-target="#projectRequestModal"
                   class="pillar-cta-btn"
                   onclick="setModalService('<?= addslashes($pillar['title']) ?>', 'Custom Scope', '')">
                    <span>Enquire About <?= htmlspecialchars($pillar['title']) ?></span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div>
                <a href="<?= BASE_URL ?>contact" class="pillar-custom-link">
                    <i class="fas fa-comments"></i> Need a custom enterprise consultation? Contact us
                </a>
            </div>
        </div>
    </div>
</section>
<?php endforeach; ?>

<!-- ════════ BOTTOM CTA BANNER ════════ -->
<div class="container my-5">
    <div class="row">
        <div class="col-12">
            <div class="cta-banner" data-aos="zoom-in">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h2 class="cta-title">Ready to fix, enhance, and scale your digital presence?</h2>
                        <p class="cta-subtitle">Graphicafix brings branding, custom software, digital marketing, and AI automation together under one agile roof.</p>
                    </div>
                    <div class="col-lg-4">
                        <div class="price-section">
                            <a href="#projectRequestModal"
                               data-bs-toggle="modal"
                               data-bs-target="#projectRequestModal"
                               class="srv-hero-btn-primary"
                               onclick="setModalService('General Project Inquiry', '', '')">
                                <span>Start Your Project</span> <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- /.srv-page -->

<script>
// ── Pass service/package into modal ───────────────────────────────────────────
function setModalService(service, pkg, price) {
    const svcField = document.getElementById('modal_service_type');
    const pkgField = document.getElementById('modal_package_name');
    const prcField = document.getElementById('modal_package_price');
    if (svcField) svcField.value = service;
    if (pkgField) pkgField.value = pkg;
    if (prcField) prcField.value = price;

    const typeSelect = document.getElementById('projectType');
    if (typeSelect && service) {
        [...typeSelect.options].forEach(opt => {
            if (opt.text.toLowerCase().includes(service.toLowerCase().split(' ')[0])) {
                opt.selected = true;
            }
        });
    }

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

// ── Smooth scroll for anchor jump links ───────────────────────────────────────
document.querySelectorAll('.srv-filter-btn, .ov-btn').forEach(a => {
    a.addEventListener('click', e => {
        const href = a.getAttribute('href');
        if (!href || !href.startsWith('#') || href === '#projectRequestModal') return;
        const target = document.querySelector(href);
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
