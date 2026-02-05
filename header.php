<?php
    require_once('config.php');
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
    <link rel="stylesheet" href="css/all.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="images/icon.png">


    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">

</head>
<body>



<nav class="navbar navbar-expand-lg">
    <div class="container">

        <!-- Logo -->
        <a class="navbar-brand fw-bold" href="index.php">
            <img src="images/logo.png" width="160px">
        </a>

        <!-- Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Menu -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">

                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="services.php">Services</a></li>
                <li class="nav-item"><a class="nav-link" href="portfolio.php">Portfolio</a></li>
                <li class="nav-item"><a class="nav-link" href="career.php">Career</a></li>
                <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>

                <!-- Action Buttons -->
                <li class="nav-item">
                    <a class="nav-link main-btn" href="#" data-bs-toggle="modal" data-bs-target="#projectRequestModal">
                        Request Project
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link main-btn" href="login.php">
                        Login
                    </a>
                </li>

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
