<?php
    require_once $_SERVER['DOCUMENT_ROOT'] . '/graphicafix/config.php';

?>
<!DOCTYPE html>

<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>
        <?php 
            $page = basename($_SERVER['PHP_SELF'], '.php'); 
            if ($page == "index"){$page = 'home';}
            echo ucfirst($page) . " | Graphicafix | Graphic Design Agency";
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
    <meta property="og:image" content="images/preview.jpg">
    <meta property="og:url" content="https://graphicafix.com">
    <meta property="og:type" content="website">

    <link rel="canonical" href="https://graphicafix.com">


    <!-- Linked Files -->
    <link rel="stylesheet" href="<?= BASE_URL ?>css/all.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/bootstrap.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="icon" type="image/png" href="images/icon.png">
    


    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
<script>
    
// Highlight active link
document.addEventListener("DOMContentLoaded", function () {
    const links = document.querySelectorAll(".navbar-menu .nav-link");
    const currentPage = window.location.pathname.split("/").pop();

    links.forEach(link => {
        const linkPage = link.getAttribute("href");

        if (linkPage === currentPage) {
            link.classList.add("active");
        } else {
            link.classList.remove("active");
        }
    });
});
</script>


</head>
<body>

<nav class="modern-navbar">
    <div class="container">
        <div class="navbar-content">
            <!-- Logo -->
            <a class="navbar-logo" href="index.php">
                <img src="<?= BASE_URL ?>images/logo.png" alt="Graphicafix">
            </a>

            <!-- Desktop Menu -->
            <ul class="navbar-menu">
                <li><a href="index.php" class="nav-link active">Home</a></li>
                <li><a href="services.php" class="nav-link">Services</a></li>
                <li><a href="portfolio.php" class="nav-link">Portfolio</a></li>
                <li><a href="career.php" class="nav-link">Career</a></li>
                <li><a href="about.php" class="nav-link">About</a></li>
                <li><a href="contact.php" class="nav-link">Contact</a></li>
            </ul>

            <!-- Action Buttons -->
            <div class="navbar-actions">
                <a href="#" data-bs-toggle="modal" data-bs-target="#projectRequestModal" class="btn-primary">
                    <span>✨</span> Request Project
                </a>
                <a href="admin/login.php" class="btn-secondary">
                    Login
                </a>
            </div>

            <!-- Mobile Toggle -->
            <button class="mobile-toggle" id="mobileToggle">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>

        <!-- Mobile Menu -->
        <div class="mobile-menu" id="mobileMenu">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="services.php">Services</a></li>
                <li><a href="portfolio.php">Portfolio</a></li>
                <li><a href="career.php">Career</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li class="mobile-divider"></li>
                <li><a href="#" data-bs-toggle="modal" data-bs-target="#projectRequestModal">Request Project</a></li>
                <li><a href="admin/login.php">Login</a></li>
            </ul>
        </div>
    </div>
</nav>
   <div class="wp-btn-cont">
        <a class="whatsapp-btn" href="https://wa.me/+923454568986" target="_blank" aria-label="Chat on WhatsApp">
            <i class="fa-brands fa-whatsapp"></i>
        </a>
    </div>
<style>
   .wp-btn-cont {
            width: 60px;
            height: 60px;
            background: #25D366;
            color: white;
            border-radius: 50%;
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 9999;
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
                box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4), 0 2px 4px rgba(0, 0, 0, 0.2), 0 0 0 10px rgba(37, 211, 102, 0.1), 0 0 0 20px rgba(37, 211, 102, 0.05);
            }
        }

        /* Tablet */
        @media (max-width: 768px) {
            .wp-btn-cont {
                width: 56px;
                height: 56px;
                bottom: 25px;
                right: 25px;
            }

            .whatsapp-btn {
                font-size: 28px;
            }
        }

        /* Mobile */
        @media (max-width: 480px) {
            .wp-btn-cont {
                width: 50px;
                height: 50px;
                bottom: 20px;
                right: 20px;
            }

            .whatsapp-btn {
                font-size: 26px;
            }
        }

        /* Small mobile */
        @media (max-width: 360px) {
            .wp-btn-cont {
                width: 46px;
                height: 46px;
                bottom: 15px;
                right: 15px;
            }

            .whatsapp-btn {
                font-size: 24px;
            }
        }
</style>
<?php
    include('project_request_popup.php');
?>

<!-- Navbar End -->

<script>
document.addEventListener("scroll", function () {
    const navbar = document.querySelector(".navbar");
    if (window.scrollY > 50) {
        navbar.classList.add("scrolled");
    } else {
        navbar.classList.remove("scrolled");
    }
});

</script>
