<?php
    require_once __DIR__ . '/../core/config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']); 

include(__DIR__ . '/../visitor_counter.php');

// Check if any courses are currently active/published
$has_active_courses = false;
if (isset($conn) && $conn instanceof mysqli) {
    $active_courses_check = $conn->query("SELECT id FROM courses WHERE is_published = 1 LIMIT 1");
    if ($active_courses_check && $active_courses_check->num_rows > 0) {
        $has_active_courses = true;
    }
}
?>
<!DOCTYPE html>

<html>

<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-TZM2VBHLHQ"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
    
      gtag('config', 'G-TZM2VBHLHQ');
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <?php
    // ── Dynamic SEO & Metadata Determination ────────────────────────────────
    $script_file = basename($_SERVER['PHP_SELF'], '.php');
    $detected_page = ($script_file === 'index') ? 'home' : $script_file;

    // Fallbacks per page
    $seo_defaults = [
        'home' => [
            'title'       => 'Graphicafix | Digital Solutions & Services Agency',
            'description' => 'Graphicafix delivers end-to-end digital solutions, including branding, web and software development, digital marketing, AI automation, and creative design.',
            'keywords'    => 'Digital services agency, digital solutions agency, software development company, web design and development, branding and graphic design, AI automation services, digital marketing agency',
            'canonical'   => 'https://graphicafix.com/'
        ],
        'services' => [
            'title'       => 'Digital Services | Web, Software, Marketing & AI – Graphicafix',
            'description' => 'Explore Graphicafix\'s complete digital services, including branding, graphic design, web development, custom software, digital marketing, and AI automation.',
            'keywords'    => 'Digital services, website development company, web design services, custom software development, digital marketing services, AI automation services, business automation, mobile app development, website maintenance services',
            'canonical'   => 'https://graphicafix.com/services'
        ],
        'portfolio' => [
            'title'       => 'Our Work & Projects | Graphicafix Portfolio',
            'description' => 'Explore Graphicafix\'s portfolio of branding, website development, graphic design, and digital projects delivered for businesses and organizations.',
            'keywords'    => 'Digital agency portfolio, branding portfolio, website development portfolio, case studies, graphic design projects, digital solutions showcase',
            'canonical'   => 'https://graphicafix.com/portfolio'
        ],
        'about' => [
            'title'       => 'About Graphicafix | Your Digital Solutions Partner',
            'description' => 'Discover Graphicafix, a full-service digital solutions agency combining creative design, technology, marketing, and automation to help businesses grow.',
            'keywords'    => 'Digital solutions company, full-service digital agency, technology agency, AI automation, branding agency, Graphicafix about',
            'canonical'   => 'https://graphicafix.com/about'
        ],
        'contact' => [
            'title'       => 'Contact Graphicafix | Discuss Your Digital Project',
            'description' => 'Have a project in mind? Contact Graphicafix for branding, website development, software solutions, digital marketing, and AI automation services.',
            'keywords'    => 'Contact digital agency, discuss digital project, hire digital solutions agency, software development enquiry, web development quote',
            'canonical'   => 'https://graphicafix.com/contact'
        ],
        'career' => [
            'title'       => 'Careers at Graphicafix | Join Our Team',
            'description' => 'Explore career opportunities at Graphicafix. Join our creative and technology team and contribute to branding, software, web development, and digital projects.',
            'keywords'    => 'Digital agency careers, tech jobs, software developer jobs, UI UX designer jobs, graphic design vacancies, join Graphicafix',
            'canonical'   => 'https://graphicafix.com/career'
        ],
        'courses' => [
            'title'       => 'Professional IT & Digital Skills Courses | Graphicafix',
            'description' => 'Explore professional courses at Graphicafix in programming, Python, artificial intelligence, and digital skills. Build practical skills for your career.',
            'keywords'    => 'IT courses, programming courses, Python courses, artificial intelligence training, digital skills courses, tech education',
            'canonical'   => 'https://graphicafix.com/courses'
        ],
        'privacy-policy' => [
            'title'       => 'Privacy Policy | Graphicafix',
            'description' => 'Read Graphicafix\'s Privacy Policy to understand how we collect, use, protect, and manage personal information submitted through our website.',
            'keywords'    => 'Privacy policy, data protection, personal information policy, Graphicafix legal',
            'canonical'   => 'https://graphicafix.com/privacy-policy'
        ],
        'terms' => [
            'title'       => 'Terms of Service | Graphicafix',
            'description' => 'Review Graphicafix\'s Terms of Service, including website usage conditions, service agreements, project responsibilities, and applicable policies.',
            'keywords'    => 'Terms of service, website terms, service agreements, project responsibilities, Graphicafix terms',
            'canonical'   => 'https://graphicafix.com/terms'
        ],
        'project_details' => [
            'title'       => 'Case Study & Project Details | Graphicafix',
            'description' => 'Explore detailed case studies and digital project deliverables by Graphicafix, spanning branding, custom software, and website development.',
            'keywords'    => 'Client case study, digital solutions project, branding portfolio, software development showcase',
            'canonical'   => 'https://graphicafix.com/portfolio'
        ]
    ];

    $cur_def = $seo_defaults[$detected_page] ?? [
        'title'       => ucfirst($detected_page) . ' | Graphicafix | Digital Solutions & Services Agency',
        'description' => 'Graphicafix delivers end-to-end digital solutions, including branding, web and software development, digital marketing, AI automation, and creative design.',
        'keywords'    => 'Digital services agency, digital solutions, software development, web design, AI automation, branding',
        'canonical'   => 'https://graphicafix.com/' . ($detected_page !== 'home' ? $detected_page : '')
    ];

    $resolved_title = !empty($page_title) ? $page_title : $cur_def['title'];
    $resolved_desc  = !empty($meta_description) ? $meta_description : (!empty($page_desc) ? $page_desc : $cur_def['description']);
    $resolved_keys  = !empty($meta_keywords) ? $meta_keywords : $cur_def['keywords'];
    $resolved_canon = !empty($canonical_url) ? $canonical_url : $cur_def['canonical'];

    // Robots meta determination
    $request_uri_str = $_SERVER['REQUEST_URI'] ?? '';
    $is_noindex = (
        strpos($request_uri_str, 'admin') !== false ||
        strpos($request_uri_str, 'portal') !== false ||
        strpos($request_uri_str, 'login') !== false ||
        strpos($request_uri_str, 'reset_password') !== false ||
        strpos($request_uri_str, 'forgot_password') !== false ||
        strpos($request_uri_str, 'download_form') !== false ||
        strpos($request_uri_str, '404') !== false ||
        strpos($request_uri_str, '403') !== false
    );
    $resolved_robots = !empty($robots_meta) ? $robots_meta : ($is_noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large');

    // Social sharing meta
    $resolved_og_title = !empty($og_title) ? $og_title : $resolved_title;
    $resolved_og_desc  = !empty($og_description) ? $og_description : $resolved_desc;
    $resolved_og_image = !empty($og_image) ? $og_image : 'https://graphicafix.com/assets/images/social-preview.jpg';
    $resolved_og_url   = !empty($og_url) ? $og_url : $resolved_canon;
    $resolved_og_type  = !empty($og_type) ? $og_type : 'website';

    $resolved_tw_title = !empty($twitter_title) ? $twitter_title : $resolved_og_title;
    $resolved_tw_desc  = !empty($twitter_description) ? $twitter_description : $resolved_og_desc;
    $resolved_tw_image = !empty($twitter_image) ? $twitter_image : $resolved_og_image;
    ?>
    <title><?= htmlspecialchars($resolved_title) ?></title>

    <!-- SEO Meta Tags -->
    <meta name="description" content="<?= htmlspecialchars($resolved_desc) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($resolved_keys) ?>">
    <meta name="author" content="Graphicafix">
    <meta name="robots" content="<?= htmlspecialchars($resolved_robots) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($resolved_canon) ?>">

    <!-- Open Graph (Facebook, LinkedIn, etc.) -->
    <meta property="og:site_name" content="Graphicafix">
    <meta property="og:type" content="<?= htmlspecialchars($resolved_og_type) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($resolved_og_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($resolved_og_desc) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($resolved_og_url) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($resolved_og_image) ?>">
    <meta property="og:locale" content="en_US">

    <!-- Twitter / X Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@graphicafix">
    <meta name="twitter:title" content="<?= htmlspecialchars($resolved_tw_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($resolved_tw_desc) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($resolved_tw_image) ?>">

    <!-- Theme & Mobile / PWA Settings -->
    <meta name="theme-color" content="#024442">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Graphicafix">
    <link rel="manifest" href="<?= BASE_URL ?>manifest.json">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= BASE_URL ?>assets/images/pwa/apple-touch-icon-180.png">
    <script defer src="<?= BASE_URL ?>assets/js/pwa-install.js?v=1"></script>

    <!-- Structured Data (Schema.org JSON-LD) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "Organization",
          "@id": "https://graphicafix.com/#organization",
          "name": "Graphicafix",
          "url": "https://graphicafix.com/",
          "logo": {
            "@type": "ImageObject",
            "url": "https://graphicafix.com/assets/images/logo.png"
          },
          "email": "contact@graphicafix.com",
          "description": "A full-service digital solutions agency providing branding, creative design, web development, custom software, digital marketing, and AI automation.",
          "sameAs": [
            "https://facebook.com/graphicafix",
            "https://instagram.com/graphicafix",
            "https://linkedin.com/company/graphicafix",
            "https://youtube.com/@graphicafix"
          ],
          "contactPoint": [
            {
              "@type": "ContactPoint",
              "telephone": "+92-345-4568986",
              "contactType": "customer service",
              "areaServed": ["PK", "GB", "US", "AE"],
              "availableLanguage": ["English", "Urdu"]
            },
            {
              "@type": "ContactPoint",
              "telephone": "+44-7903-442499",
              "contactType": "customer service",
              "areaServed": "GB",
              "availableLanguage": ["English"]
            }
          ]
        },
        <?php if ($detected_page === 'home'): ?>
        {
          "@type": "WebSite",
          "@id": "https://graphicafix.com/#website",
          "url": "https://graphicafix.com/",
          "name": "Graphicafix",
          "publisher": {
            "@id": "https://graphicafix.com/#organization"
          },
          "potentialAction": {
            "@type": "SearchAction",
            "target": "https://graphicafix.com/portfolio?search={search_term_string}",
            "query-input": "required name=search_term_string"
          }
        },
        {
          "@type": "ProfessionalService",
          "@id": "https://graphicafix.com/#service",
          "name": "Graphicafix - Digital Solutions Agency",
          "url": "https://graphicafix.com/",
          "image": "https://graphicafix.com/assets/images/social-preview.jpg",
          "priceRange": "$$",
          "email": "contact@graphicafix.com",
          "telephone": "+923454568986",
          "description": "End-to-end digital solutions, including branding, website design, custom software development, SaaS, digital marketing, and AI automation.",
          "areaServed": ["Pakistan", "United Kingdom", "United States", "Global"],
          "openingHours": "Mo-Fr 09:00-18:00"
        }
        <?php else: ?>
        {
          "@type": "BreadcrumbList",
          "@id": "<?= htmlspecialchars($resolved_canon) ?>#breadcrumb",
          "itemListElement": [
            {
              "@type": "ListItem",
              "position": 1,
              "name": "Home",
              "item": "https://graphicafix.com/"
            },
            {
              "@type": "ListItem",
              "position": 2,
              "name": "<?= htmlspecialchars($resolved_title) ?>",
              "item": "<?= htmlspecialchars($resolved_canon) ?>"
            }
          ]
        }
        <?php endif; ?>
        <?php if (!empty($extra_schema_json)): ?>
        , <?= $extra_schema_json ?>
        <?php endif; ?>
      ]
    }
    </script>


    <!-- Linked Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" referrerpolicy="no-referrer" />    
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/icon.png">
    <!-- iPhone / iPad home screen icon -->
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>assets/images/icon2.png">

    <!-- Swiper CSS & JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <!-- Active Nav Link Detection -->
    <script>
      document.addEventListener("DOMContentLoaded", function () {
        const currentPath = window.location.pathname.replace(/\/+$/, "");
        const currentFile = currentPath.split("/").pop().replace(/\.php$/, "") || "home";

        const allNavLinks = document.querySelectorAll(".navbar-menu .nav-link, .mobile-menu .mobile-nav-link");
        allNavLinks.forEach(link => {
            const href = link.getAttribute("href");
            if (!href || href === "#") return;
            try {
                const url = new URL(href, window.location.origin);
                const linkPath = url.pathname.replace(/\/+$/, "");
                const linkFile = linkPath.split("/").pop().replace(/\.php$/, "") || "home";

                const isHomeCurrent = (currentFile === "home" || currentFile === "graphicafix" || currentFile === "index" || currentPath === "" || currentPath === "/graphicafix");
                const isHomeLink = (linkFile === "home" || linkFile === "graphicafix" || linkFile === "index" || linkPath === "" || linkPath === "/graphicafix");

                if ((isHomeCurrent && isHomeLink) || (currentFile && currentFile === linkFile)) {
                    link.classList.add("active");
                } else {
                    link.classList.remove("active");
                }
            } catch (e) {
                // Ignore parsing errors for custom protocols
            }
        });
      });
    </script>
    
    <!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '798320433152391');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=798320433152391&ev=PageView&noscript=1"
/></noscript>
<!-- End Meta Pixel Code -->


    <style>
    /* Force Font Awesome to render */
    .fas, .fa, .fab {
        display: inline-block;
        font-style: normal;
        font-variant: normal;
        text-rendering: auto;
        -webkit-font-smoothing: antialiased;
    }

    /* ── TRANSLUCENT MODERN NAVBAR ── */
    .modern-navbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1030;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(20px) saturate(180%);
        -webkit-backdrop-filter: blur(20px) saturate(180%);
        border-bottom: 2px solid #024442;
        transition: background 0.3s ease, box-shadow 0.3s ease;
    }
    .modern-navbar.scrolled {
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 4px 25px rgba(2, 68, 66, 0.09);
    }
    .navbar-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.95rem 0;
    }
    .navbar-logo img {
        height: 38px;
        width: auto;
        display: block;
        transition: opacity 0.2s ease;
    }
    .navbar-logo:hover img {
        opacity: 0.88;
    }

    /* ── DESKTOP NAVIGATION ── */
    .navbar-menu {
        display: flex;
        list-style: none;
        margin: 0;
        padding: 0;
        gap: 2.2rem;
        align-items: center;
    }
    .navbar-menu .nav-link {
        color: #1a202c;
        text-decoration: none;
        font-weight: 550;
        font-size: 15px;
        padding: 0.25rem 0;
        position: relative;
        transition: color 0.2s ease;
        letter-spacing: -0.01em;
    }
    .navbar-menu .nav-link::after {
        content: '';
        position: absolute;
        bottom: -4px;
        left: 0;
        width: 0;
        height: 2px;
        background: #024442;
        border-radius: 2px;
        transition: width 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .navbar-menu .nav-link:hover,
    .navbar-menu .nav-link.active {
        color: #024442;
    }
    .navbar-menu .nav-link:hover::after,
    .navbar-menu .nav-link.active::after {
        width: 100%;
    }

    /* ── DESKTOP ACTIONS ── */
    .navbar-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .navbar-actions .btn-primary {
        background: #024442;
        color: #ffffff !important;
        padding: 9px 22px;
        border-radius: 50px;
        font-size: 14px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        border: 1px solid #024442;
        transition: all 0.25s ease;
        box-shadow: 0 4px 14px rgba(2, 68, 66, 0.16);
    }
    .navbar-actions .btn-primary:hover {
        background: #035a57;
        color: #ffffff !important;
        transform: translateY(-2px);
        box-shadow: 0 8px 22px rgba(2, 68, 66, 0.24);
    }
    .navbar-actions .btn-primary i {
        color: #B6F763;
    }
    .navbar-actions .btn-secondary {
        background: transparent;
        color: #024442 !important;
        padding: 8px 20px;
        border-radius: 50px;
        font-size: 14px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        text-decoration: none;
        border: 1.5px solid rgba(2, 68, 66, 0.25);
        transition: all 0.25s ease;
    }
    .navbar-actions .btn-secondary:hover {
        background: rgba(2, 68, 66, 0.05);
        border-color: #024442;
        color: #024442 !important;
        transform: translateY(-2px);
    }

    /* ── MOBILE TOGGLE BUTTON ── */
    .mobile-toggle {
        display: none;
        width: 44px;
        height: 44px;
        background: transparent;
        border: 1px solid rgba(2, 68, 66, 0.15);
        border-radius: 12px;
        cursor: pointer;
        padding: 0;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 5px;
        transition: all 0.25s ease;
        position: relative;
        z-index: 1032;
        -webkit-tap-highlight-color: transparent;
    }
    .mobile-toggle:hover,
    .mobile-toggle:focus {
        background: rgba(2, 68, 66, 0.05);
        border-color: #024442;
        outline: none;
    }
    .mobile-toggle .bar {
        display: block;
        width: 20px;
        height: 2px;
        background: #024442;
        border-radius: 2px;
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease;
        transform-origin: center;
    }
    .mobile-toggle.active .bar-1 {
        transform: translateY(7px) rotate(45deg);
    }
    .mobile-toggle.active .bar-2 {
        opacity: 0;
        transform: scaleX(0);
    }
    .mobile-toggle.active .bar-3 {
        transform: translateY(-7px) rotate(-45deg);
    }

    /* ── MOBILE MENU BACKDROP ── */
    .mobile-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        height: 100dvh;
        background: rgba(2, 68, 66, 0.4);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        z-index: 1028;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }
    .mobile-backdrop.active {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }

    /* ── MOBILE MENU DRAWER ── */
    .mobile-menu {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        width: 100%;
        background: #ffffff;
        border-top: 1px solid rgba(2, 68, 66, 0.08);
        border-bottom: 2px solid #024442;
        box-shadow: 0 24px 45px -10px rgba(2, 68, 66, 0.2);
        z-index: 1029;
        max-height: 0;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        transition: max-height 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease, visibility 0.25s ease;
    }
    .mobile-menu.active {
        max-height: calc(100dvh - 65px);
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }

    body.mobile-nav-locked {
        overflow: hidden !important;
        touch-action: none;
    }

    .mobile-menu-container {
        max-width: 620px;
        margin: 0 auto;
        padding: 1rem 1.25rem 1.5rem;
    }

    .mobile-nav-list {
        list-style: none;
        padding: 0;
        margin: 0 0 1.2rem 0;
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .mobile-nav-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 14px;
        border-radius: 12px;
        color: #1a202c !important;
        font-size: 15.5px;
        font-weight: 550;
        text-decoration: none !important;
        transition: all 0.2s ease;
        background: transparent;
        -webkit-tap-highlight-color: transparent;
    }
    .mobile-nav-link .link-label {
        display: inline-flex;
        align-items: center;
    }
    .mobile-nav-link .link-label i {
        width: 22px;
        font-size: 14px;
        color: #024442;
        opacity: 0.8;
    }
    .mobile-nav-link .link-arrow {
        font-size: 11px;
        opacity: 0.35;
        transition: transform 0.2s ease, opacity 0.2s ease;
        color: #024442;
    }
    .mobile-nav-link:hover,
    .mobile-nav-link:focus {
        background: rgba(2, 68, 66, 0.05) !important;
        color: #024442 !important;
        padding-left: 18px;
    }
    .mobile-nav-link:hover .link-arrow {
        opacity: 1;
        transform: translateX(4px);
    }
    .mobile-nav-link.active {
        background: rgba(2, 68, 66, 0.08) !important;
        color: #024442 !important;
        font-weight: 700;
        border-left: 3.5px solid #024442;
        padding-left: 14px;
    }
    .mobile-nav-link.active .link-label i {
        color: #024442;
        opacity: 1;
    }

    .mobile-badge-pulse {
        background: #B6F763;
        color: #024442;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 20px;
        margin-left: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .mobile-menu-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding-top: 14px;
        border-top: 1px solid rgba(2, 68, 66, 0.08);
        margin-bottom: 14px;
    }
    .mobile-btn-primary {
        background: #024442 !important;
        color: #ffffff !important;
        padding: 13px 20px;
        border-radius: 50px;
        font-size: 15px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none !important;
        box-shadow: 0 4px 15px rgba(2, 68, 66, 0.2);
        transition: all 0.2s ease;
        border: 1px solid #024442;
    }
    .mobile-btn-primary:active {
        transform: scale(0.98);
    }
    .mobile-btn-secondary {
        background: rgba(2, 68, 66, 0.04) !important;
        color: #024442 !important;
        padding: 12px 20px;
        border-radius: 50px;
        font-size: 15px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none !important;
        border: 1.5px solid rgba(2, 68, 66, 0.2);
        transition: all 0.2s ease;
    }
    .mobile-btn-secondary:active {
        transform: scale(0.98);
    }

    .mobile-menu-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 6px;
        font-size: 13px;
        color: #718096;
    }
    .mobile-footer-pill {
        color: #024442 !important;
        text-decoration: none !important;
        font-weight: 600;
        background: rgba(2, 68, 66, 0.05);
        padding: 5px 12px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
    }
    .mobile-footer-tag {
        font-size: 12px;
        color: #a0aec0;
        font-weight: 500;
    }

    /* ── RESPONSIVE BREAKPOINT ── */
    @media (max-width: 991.98px) {
        .navbar-menu,
        .navbar-actions {
            display: none !important;
        }
        .mobile-toggle {
            display: flex !important;
        }
    }
    @media (min-width: 992px) {
        .mobile-menu,
        .mobile-backdrop {
            display: none !important;
        }
        .mobile-toggle {
            display: none !important;
        }
    }
    </style>


</head>
<body>

<nav class="modern-navbar" id="modernNavbar">
    <div class="container">
        <div class="navbar-content">
            <!-- Logo -->
            <a class="navbar-logo" href="<?= BASE_URL ?>" aria-label="Graphicafix Home">
                <img src="<?= BASE_URL ?>assets/images/logo.png" alt="Graphicafix digital solutions agency logo">
            </a>

            <!-- Desktop Menu -->
            <ul class="navbar-menu">
                <li><a href="<?= BASE_URL ?>" class="nav-link">Home</a></li>
                <li><a href="<?= BASE_URL ?>services" class="nav-link">Services</a></li>
                <li><a href="<?= BASE_URL ?>portfolio" class="nav-link">Portfolio</a></li>
                <li><a href="<?= BASE_URL ?>career" class="nav-link">Career</a></li>
                <?php if (!empty($has_active_courses)): ?>
                <li class="animated-beat"><a href="<?= BASE_URL ?>courses" class="nav-link">Courses</a></li>
                <?php endif; ?>
                <li><a href="<?= BASE_URL ?>about" class="nav-link">About</a></li>
                <li><a href="<?= BASE_URL ?>contact" class="nav-link">Contact</a></li>
            </ul>

            <!-- Action Buttons -->
            <div class="navbar-actions">
                <a href="#" data-bs-toggle="modal" data-bs-target="#projectRequestModal" class="btn-primary">
                    <span><i class="fas fa-star" style="color: var(--accent);"></i></span> Request Project
                </a>
                <a href="<?= BASE_URL ?><?= $isLoggedIn ? 'admin/' : 'admin/login.php'; ?>" 
                   class="<?= $isLoggedIn ? 'btn-primary' : 'btn-secondary'; ?>">
                    <?= $isLoggedIn ? 'Dashboard' : 'Login'; ?>
                </a>                                                            
            </div>

            <!-- Mobile Toggle Button -->
            <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="mobileMenu">
                <span class="bar bar-1"></span>
                <span class="bar bar-2"></span>
                <span class="bar bar-3"></span>
            </button>
        </div>
    </div>

    <!-- Mobile Menu Backdrop -->
    <div class="mobile-backdrop" id="mobileBackdrop"></div>

    <!-- Mobile Menu Dropdown -->
    <div class="mobile-menu" id="mobileMenu" aria-hidden="true">
        <div class="mobile-menu-container">
            <ul class="mobile-nav-list">
                <li>
                    <a href="<?= BASE_URL ?>" class="mobile-nav-link">
                        <span class="link-label"><i class="fas fa-home me-2"></i>Home</span>
                        <i class="fas fa-chevron-right link-arrow"></i>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>services" class="mobile-nav-link">
                        <span class="link-label"><i class="fas fa-th-large me-2"></i>Services</span>
                        <i class="fas fa-chevron-right link-arrow"></i>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>portfolio" class="mobile-nav-link">
                        <span class="link-label"><i class="fas fa-briefcase me-2"></i>Portfolio</span>
                        <i class="fas fa-chevron-right link-arrow"></i>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>career" class="mobile-nav-link">
                        <span class="link-label"><i class="fas fa-user-tie me-2"></i>Career</span>
                        <i class="fas fa-chevron-right link-arrow"></i>
                    </a>
                </li>
                <?php if (!empty($has_active_courses)): ?>
                <li>
                    <a href="<?= BASE_URL ?>courses" class="mobile-nav-link">
                        <span class="link-label"><i class="fas fa-graduation-cap me-2"></i>Courses <span class="mobile-badge-pulse">Hot</span></span>
                        <i class="fas fa-chevron-right link-arrow"></i>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="<?= BASE_URL ?>about" class="mobile-nav-link">
                        <span class="link-label"><i class="fas fa-info-circle me-2"></i>About</span>
                        <i class="fas fa-chevron-right link-arrow"></i>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>contact" class="mobile-nav-link">
                        <span class="link-label"><i class="fas fa-envelope me-2"></i>Contact</span>
                        <i class="fas fa-chevron-right link-arrow"></i>
                    </a>
                </li>
            </ul>

            <div class="mobile-menu-actions">
                <a href="#" data-bs-toggle="modal" data-bs-target="#projectRequestModal" class="mobile-btn-primary">
                    <i class="fas fa-star me-2" style="color: #B6F763;"></i> Request Project
                </a>
                <a href="<?= BASE_URL ?><?= $isLoggedIn ? 'admin/' : 'admin/login.php'; ?>" class="mobile-btn-secondary">
                    <i class="fas fa-user-circle me-2"></i> <?= $isLoggedIn ? 'Dashboard' : 'Client Login'; ?>
                </a>
            </div>

            <div class="mobile-menu-footer">
                <a href="https://wa.me/+923454568986" target="_blank" class="mobile-footer-pill">
                    <i class="fab fa-whatsapp me-1 text-success"></i> +92 345 4568986
                </a>
                <span class="mobile-footer-tag">Crafted with precision</span>
            </div>
        </div>
    </div>
</nav>
   <div class="floating-btn-group">
    
    <div class="wp-btn-cont">
        <a class="whatsapp-btn" href="https://wa.me/+923454568986" target="_blank" aria-label="Chat on WhatsApp">
            <i class="fab fa-whatsapp"></i>
        </a>
    </div>

    <?php if (!empty($has_active_courses)): ?>
    <a href="<?= BASE_URL ?>courses" class="enroll-float-btn">
        <i class="fas fa-user-graduate"></i> Enroll to Courses
    </a>
    <?php endif; ?>

</div>

<style>
    /* Group Container handles the fixed positioning */
    .floating-btn-group {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        align-items: flex-end; /* Keeps both buttons aligned to the right edge */
        gap: 15px; /* Space between the WhatsApp and Enroll button */
    }

    /* ── WhatsApp Button Styles ── */
    .wp-btn-cont {
        width: 60px;
        height: 60px;
        background: #25D366;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4), 0 2px 4px rgba(0, 0, 0, 0.2);
        cursor: pointer;
        transition: all 0.3s ease;
        animation: pulse 2s infinite;
    }

    .wp-btn-cont:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(37, 211, 102, 0.6), 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    .wp-btn-cont:active {
        transform: scale(0.95);
    }

    .whatsapp-btn {
        color: white;
        text-decoration: none;
        font-size: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        transition: transform 0.3s ease;
    }

    .whatsapp-btn:hover {
        transform: rotate(15deg);
    }

    @keyframes pulse {
        0%, 100% {
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4), 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        50% {
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4), 0 2px 4px rgba(0, 0, 0, 0.2), 
                        0 0 0 10px rgba(37, 211, 102, 0.1), 0 0 0 20px rgba(37, 211, 102, 0.05);
        }
    }

    /* ── Enroll Button Styles ── */
    .enroll-float-btn {
        background: var(--accent);
        color: var(--dark);
        text-decoration: none;
        padding: 12px 24px;
        border-radius: 50px; /* Rounded rectangular look */
        font-size: 15px;
        /*font-weight: 700;*/
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 15px rgba(2, 132, 199, 0.4);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        white-space: nowrap;
    }

    .enroll-float-btn:hover {
        transform: translateY(-3px); /* Gentle lift on hover */
        box-shadow: 0 8px 20px rgba(2, 132, 199, 0.5);
        color: #000000 ;
    }

    /* ── Responsive Adjustments ── */
    /* Tablet */
    @media (max-width: 768px) {
        .floating-btn-group {
            bottom: 25px;
            right: 25px;
            gap: 12px;
        }
        .wp-btn-cont {
            width: 56px;
            height: 56px;
        }
        .whatsapp-btn {
            font-size: 28px;
        }
        .enroll-float-btn {
            padding: 10px 20px;
            font-size: 14px;
        }
    }

    /* Mobile */
    @media (max-width: 480px) {
        .floating-btn-group {
            bottom: 20px;
            right: 20px;
            gap: 10px;
        }
        .wp-btn-cont {
            width: 50px;
            height: 50px;
        }
        .whatsapp-btn {
            font-size: 26px;
        }
        .enroll-float-btn {
            padding: 10px 16px;
            font-size: 13px;
        }
    }

    /* Small mobile */
    @media (max-width: 360px) {
        .floating-btn-group {
            bottom: 15px;
            right: 15px;
        }
        .wp-btn-cont {
            width: 46px;
            height: 46px;
        }
        .whatsapp-btn {
            font-size: 24px;
        }
    }
</style>
<?php
    include(__DIR__ . '/../projects/project_request_popup.php');
?>

<!-- Navbar End -->

<script>
document.addEventListener("scroll", function () {
    const navbar = document.querySelector(".navbar");
    if (navbar) {
        if (window.scrollY > 50) {
            navbar.classList.add("scrolled");
        } else {
            navbar.classList.remove("scrolled");
        }
    }
});

</script>