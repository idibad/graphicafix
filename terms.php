<?php include 'templates/header.php'; ?>

<main class="terms-page">
    <style>
        /* =========================
           Terms & Conditions Page Styles
        ========================= */
        .terms-page {
            font-family: 'Inter', sans-serif;
            color: #333;
            line-height: 1.8;
        }
        .terms-hero {
            background: #024442;
            color: #fff;
            padding: 110px 20px 40px;
            text-align: center;
        }
        .terms-hero h1 {
            font-size: 40px;
            font-weight: 700;
            margin-bottom: 15px;
        }
        .terms-hero .subtitle {
            font-size: 18px;
            color: #d1f0ef;
        }
        .terms-content .container {
            max-width: 900px;
            margin: -40px auto 80px;
            background: #fff;
            padding: 50px 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .terms-section {
            margin-bottom: 40px;
        }
        .terms-section h2 {
            font-size: 24px;
            font-weight: 600;
            color: #024442;
            margin-bottom: 15px;
        }
        .terms-section p, .terms-section ul li {
            font-size: 16px;
            color: #555;
            margin-bottom: 15px;
        }
        .terms-section ul {
            padding-left: 20px;
            margin-bottom: 15px;
        }
        .last-updated {
            font-size: 14px;
            color: #999;
            text-align: right;
            margin-top: 20px;
        }
        @media (max-width:768px) {
            .terms-hero h1 { font-size: 28px; }
            .terms-hero .subtitle { font-size: 16px; }
            .terms-content .container { padding: 30px 20px; margin: -30px auto 50px; }
            .terms-section h2 { font-size: 20px; }
            .terms-section p, .terms-section ul li { font-size: 15px; }
        }
    </style>

    <section class="terms-hero">
        <div class="container">
            <h1>Terms & Conditions</h1>
            <p class="subtitle">Please read these terms carefully before using Graphicafix services.</p>
        </div>
    </section>

    <section class="terms-content">
        <div class="container">
            <article class="terms-section">
                <h2>Acceptance of Terms</h2>
                <p>By accessing our website, you agree to comply with these terms. If you do not agree, please do not use our services.</p>
            </article>

            <article class="terms-section">
                <h2>Use of Services</h2>
                <ul>
                    <li>All content is provided for informational purposes only.</li>
                    <li>Unauthorized use of our materials is prohibited.</li>
                    <li>You must not misuse our website in any way.</li>
                </ul>
            </article>

            <article class="terms-section">
                <h2>Intellectual Property</h2>
                <p>All designs, logos, content, and branding created by Graphicafix are the intellectual property of Graphicafix unless otherwise stated.</p>
            </article>

            <article class="terms-section">
                <h2>Payment & Refunds</h2>
                <p>Payments for services are due as per invoices. Refunds are provided under specific conditions as described in project agreements.</p>
            </article>

            <article class="terms-section">
                <h2>Limitation of Liability</h2>
                <p>Graphicafix is not liable for indirect, incidental, or consequential damages arising from the use of our services or website.</p>
            </article>

            <article class="terms-section">
                <h2>Changes to Terms</h2>
                <p>We may update these terms from time to time. The latest version will always appear on this page.</p>
                <p class="last-updated">Last updated: February 28, 2026</p>
            </article>
        </div>
    </section>
</main>

<?php include 'templates/footer.php'; ?>