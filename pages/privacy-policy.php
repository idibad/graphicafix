<?php require_once __DIR__ . '/../core/config.php';
include __DIR__ . '/../templates/header.php'; ?>

<main class="policy-page">
    <style>
        /* =========================
           Privacy Policy Page Styles
        ========================= */
        .policy-page {
            font-family: 'Inter', sans-serif;
            color: #333;
            line-height: 1.8;
        }
        .policy-hero {
            background: #024442;
            color: #fff;
            padding: 110px 20px 40px;
            text-align: center;
        }
        .policy-hero h1 {
            font-size: 40px;
            font-weight: 700;
            margin-bottom: 15px;
        }
        .policy-hero .subtitle {
            font-size: 18px;
            color: #d1f0ef;
        }
        .policy-content .container {
            max-width: 900px;
            margin: -40px auto 80px;
            background: #fff;
            padding: 50px 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .policy-section {
            margin-bottom: 40px;
        }
        .policy-section h2 {
            font-size: 24px;
            font-weight: 600;
            color: #024442;
            margin-bottom: 15px;
        }
        .policy-section p {
            font-size: 16px;
            color: #555;
            margin-bottom: 15px;
        }
        .policy-section ul {
            padding-left: 20px;
            margin-bottom: 15px;
        }
        .policy-section ul li {
            font-size: 16px;
            margin-bottom: 10px;
        }
        .last-updated {
            font-size: 14px;
            color: #999;
            text-align: right;
            margin-top: 20px;
        }
        @media (max-width:768px) {
            .policy-hero h1 { font-size: 28px; }
            .policy-hero .subtitle { font-size: 16px; }
            .policy-content .container { padding: 30px 20px; margin: -30px auto 50px; }
            .policy-section h2 { font-size: 20px; }
            .policy-section p, .policy-section ul li { font-size: 15px; }
        }
    </style>

    <section class="policy-hero">
        <div class="container">
            <h1>Privacy Policy</h1>
            <p class="subtitle">Your privacy matters. Learn how Graphicafix protects your data.</p>
        </div>
    </section>

    <section class="policy-content">
        <div class="container">
            <article class="policy-section">
                <h2>Information We Collect</h2>
                <p>We collect information when you interact with our website:</p>
                <ul>
                    <li>Personal details like name, email, phone, and project info.</li>
                    <li>Non-personal details such as browser type, IP address, and page visits.</li>
                </ul>
            </article>

            <article class="policy-section">
                <h2>How We Use Your Information</h2>
                <p>Your data helps us improve our services, communicate with you, and provide better user experience.</p>
                <ul>
                    <li>Respond to inquiries and provide support.</li>
                    <li>Send occasional updates (only if opted in).</li>
                    <li>Analyze site usage to improve features and content.</li>
                </ul>
            </article>

            <article class="policy-section">
                <h2>Cookies</h2>
                <p>We use cookies to enhance browsing experience. You can disable cookies, but some features may not work properly.</p>
            </article>

            <article class="policy-section">
                <h2>Data Security</h2>
                <p>We implement industry-standard measures to protect your data. However, no system is completely secure online.</p>
            </article>

            <article class="policy-section">
                <h2>Third-Party Links</h2>
                <p>Links to external sites are not under our control. Please review their privacy policies separately.</p>
            </article>

            <article class="policy-section">
                <h2>Changes to This Policy</h2>
                <p>We may update this policy occasionally. The latest version will always appear here.</p>
                <p class="last-updated">Last updated: February 28, 2026</p>
            </article>
        </div>
    </section>
</main>

<?php include __DIR__ . '/../templates/footer.php'; ?>