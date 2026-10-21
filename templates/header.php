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
    <title>
        <?php
        if (isset($page_title)) {
            echo $page_title;
        } else {
            $page = basename($_SERVER['PHP_SELF'], '.php');
            if ($page == "index") { $page = 'home'; }
            echo ucfirst($page) . " | Graphicafix | Graphic Design Agency";
        }
        ?>
    </title>
    <!-- SEO  Meta Tags -->
    <meta name="description" content="We provide high-quality graphic design services, including branding, logo design, and UI/UX. Elevate your brand with stunning visuals.">
    <meta name="keywords" content="graphic design, logo design, branding, UI/UX, creative agency">
    <meta name="author" content="Ibadullah Shalmany">
    <meta name="robots" content="index, follow">
    <!-- Open Graph (Facebook, LinkedIn, etc.) -->
    <meta property="og:title" content="Graphicafix | Graphic Design Agency ">
    <meta property="og:description" content="We create visually stunning graphics to enhance your brand identity.">
    <meta property="og:image" content="<?= BASE_URL ?>assets/images/preview.jpg">
    <meta property="og:url" content="https://graphicafix.com">
    <meta property="og:type" content="website">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="canonical" href="https://graphicafix.com">


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
    <script>
      document.addEventListener("DOMContentLoaded", function () {
    const links = document.querySelectorAll(".navbar-menu .nav-link");
    const currentPage = window.location.pathname.split("/").pop().replace('.php','');

    links.forEach(link => {
        const linkPage = link.getAttribute("href").replace('.php','');
        if (linkPage === currentPage) {
            link.classList.add("active");
        } else {
            link.classList.remove("active");
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
.fas, .fa {
    font-family: "Font Awesome 5 Free" !important;
    font-weight: 900 !important;
    display: inline-block;
    font-style: normal;
    font-variant: normal;
    text-rendering: auto;
    -webkit-font-smoothing: antialiased;
}

/* Translucent Navbar with Primary Color Bottom Border */
.modern-navbar {
    background: rgba(255, 255, 255, 0.75) !important;
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border-bottom: 2px solid #024442 !important;
    transition: all 0.3s ease;
}
.modern-navbar.scrolled {
    background: rgba(255, 255, 255, 0.9) !important;
    border-bottom: 2px solid #024442 !important;
    box-shadow: 0 4px 25px rgba(2, 68, 66, 0.08) !important;
}
.navbar-menu .nav-link {
    color: #111111 !important;
    font-weight: 500;
}
.navbar-menu .nav-link:hover,
.navbar-menu .nav-link.active {
    color: #024442 !important;
}
.navbar-menu .nav-link::after {
    background: #024442 !important;
}
.navbar-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}
.navbar-actions .btn-primary {
    background: #024442 !important;
    color: #ffffff !important;
    padding: 10px 22px !important;
    border-radius: 50px !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    text-decoration: none !important;
    border: 1px solid #024442 !important;
    transition: all 0.25s ease !important;
    box-shadow: 0 4px 14px rgba(2, 68, 66, 0.16) !important;
}
.navbar-actions .btn-primary:hover {
    background: #035a57 !important;
    color: #ffffff !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 22px rgba(2, 68, 66, 0.24) !important;
}
.navbar-actions .btn-primary i {
    color: #B6F763 !important;
}
.navbar-actions .btn-secondary {
    background: transparent !important;
    color: #024442 !important;
    padding: 9px 20px !important;
    border-radius: 50px !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    display: inline-flex !important;
    align-items: center !important;
    text-decoration: none !important;
    border: 1.5px solid rgba(2, 68, 66, 0.3) !important;
    transition: all 0.25s ease !important;
}
.navbar-actions .btn-secondary:hover {
    background: rgba(2, 68, 66, 0.05) !important;
    border-color: #024442 !important;
    color: #024442 !important;
    transform: translateY(-2px) !important;
}
.mobile-toggle span {
    background: #111111 !important;
}
.mobile-menu {
    background: #ffffff !important;
    border-top: 1px solid rgba(0, 0, 0, 0.07) !important;
    box-shadow: 0 16px 30px rgba(0, 0, 0, 0.08) !important;
}
.mobile-menu ul li a {
    color: #111111 !important;
}
.mobile-menu ul li a:hover {
    color: #024442 !important;
    background: rgba(2, 68, 66, 0.04) !important;
}
    </style>


</head>
<body>

<nav class="modern-navbar">
    <div class="container">
        <div class="navbar-content">
            <!-- Logo -->
            <a class="navbar-logo" href="<?= BASE_URL ?>">
                <img src="<?= BASE_URL ?>assets/images/logo.png" alt="Graphicafix">
            </a>

            <!-- Desktop Menu -->
            <ul class="navbar-menu">
                <li><a href="<?= BASE_URL ?>" class="nav-link active">Home</a></li>
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

            <!-- Mobile Toggle -->
            <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle navigation">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>

        <!-- Mobile Menu -->
        <div class="mobile-menu" id="mobileMenu">
            <ul>
                <li><a href="<?= BASE_URL ?>">Home</a></li>
                <li><a href="<?= BASE_URL ?>services">Services</a></li>
                <li><a href="<?= BASE_URL ?>portfolio">Portfolio</a></li>
                <li><a href="<?= BASE_URL ?>career">Career</a></li>
                <?php if (!empty($has_active_courses)): ?>
                <li><a href="<?= BASE_URL ?>courses">Courses</a></li>
                <?php endif; ?>
                <li><a href="<?= BASE_URL ?>about">About</a></li>
                <li><a href="<?= BASE_URL ?>contact">Contact</a></li>
                <li class="mobile-divider"></li>
                <li>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#projectRequestModal" class="main-btn w-100 text-center justify-content-center d-flex mb-2" style="background:#024442; color:#ffffff; font-weight:600; border-radius: 50px;">
                        <i class="fas fa-star me-2" style="color:#B6F763;"></i> Request Project
                    </a>
                    <a href="<?= BASE_URL ?><?= $isLoggedIn ? 'admin/' : 'admin/login.php'; ?>" 
                       class="main-btn w-100 text-center justify-content-center d-flex" style="color: #024442; background: rgba(2, 68, 66, 0.05); border: 1.5px solid rgba(2, 68, 66, 0.2); font-weight: 600; border-radius: 50px;">
                        <?= $isLoggedIn ? 'Dashboard' : 'Login'; ?>
                    </a>
                </li>
            </ul>
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