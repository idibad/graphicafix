<?php
/**
 * admin/vendor_login.php
 * Print Vendor Partner Login Interface
 * Bilingual (English & Urdu) Support with Graphicafix Global Design
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/print_db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in as vendor
if (!empty($_SESSION['vendor_logged_in']) && !empty($_SESSION['vendor_id'])) {
    header("Location: print_vendor_portal.php");
    exit;
}

if (isset($conn) && $conn instanceof mysqli) {
    init_print_database($conn);
}
?>
<!DOCTYPE html>
<html lang="en" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Vendor Portal — Graphicafix</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #024442;
            --primary-hover: #012e2c;
            --accent: #B6F763;
            --dark: #171717;
            --light: #f0ffff;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-500: #6b7280;
            --radius-lg: 14px;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', 'Nunito', sans-serif;
            background: linear-gradient(135deg, #024442 0%, #012221 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            margin: 0;
            color: #1f2937;
        }
        body.lang-ur {
            font-family: 'Noto Nastaliq Urdu', 'Plus Jakarta Sans', sans-serif;
            direction: rtl;
            text-align: right;
        }
        .login-card {
            background: #ffffff;
            border-radius: 20px;
            width: 100%;
            max-width: 460px;
            padding: 36px 32px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.28);
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .lang-switcher-wrap {
            position: absolute;
            top: 22px;
            right: 22px;
            display: flex;
            gap: 4px;
            background: #f1f5f9;
            padding: 3px;
            border-radius: 20px;
        }
        body.lang-ur .lang-switcher-wrap {
            right: auto;
            left: 22px;
        }
        .lang-btn {
            background: transparent;
            border: none;
            color: #475569;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .lang-btn.active {
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .brand-logo-area {
            text-align: center;
            margin-bottom: 24px;
        }
        .brand-icon-circle {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: #f0f9f8;
            color: var(--primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-bottom: 12px;
            border: 1.5px solid #d1eae8;
        }
        .form-control {
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            padding: 11px 14px;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.12);
        }
        .btn-submit-login {
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 13px 20px;
            font-size: 14.5px;
            font-weight: 700;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-submit-login:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(2, 68, 66, 0.2);
        }
        .quick-vendor-pills {
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px dashed #e2e8f0;
        }
        .vendor-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 4px 9px;
            border-radius: 14px;
            font-size: 11.5px;
            color: #334155;
            cursor: pointer;
            transition: all 0.15s ease;
            margin: 2px 2px;
            text-decoration: none;
        }
        .vendor-pill:hover {
            background: #e2e8f0;
            border-color: var(--primary);
            color: var(--primary);
        }
    </style>
</head>
<body>

<div class="login-card">

    <!-- Language Switcher Toggle -->
    <div class="lang-switcher-wrap">
        <button type="button" class="lang-btn active" id="btnEn" onclick="switchLang('en')">English</button>
        <button type="button" class="lang-btn" id="btnUr" onclick="switchLang('ur')">اردو</button>
    </div>

    <!-- Brand Header -->
    <div class="brand-logo-area">
        <div class="brand-icon-circle">
            <i class="fas fa-print"></i>
        </div>
        <h4 style="font-weight:800; color:#0f172a; margin:0 0 4px 0;" id="tTitle">Vendor Rate Portal</h4>
        <p style="font-size:13px; color:#64748b; margin:0;" id="tSub">Graphicafix Commercial Printing Network</p>
    </div>

    <!-- Alert Box -->
    <div id="loginAlert" style="display:none; margin-bottom:16px; border-radius:10px; font-size:13px;" class="alert alert-danger" role="alert"></div>

    <!-- Login Form -->
    <form id="vendorLoginForm" onsubmit="handleVendorLogin(event)">
        <div class="mb-3">
            <label class="form-label" style="font-weight:700; font-size:12.5px; color:#334155;" id="tPhoneLabel">
                Phone Number / WhatsApp / Email
            </label>
            <div class="input-group">
                <span class="input-group-text" style="background:#f8fafc; border-radius:10px 0 0 10px;"><i class="fas fa-phone text-muted"></i></span>
                <input type="text" id="loginInput" class="form-control" placeholder="e.g. 03001234567" required autofocus style="font-weight:600; border-radius:0 10px 10px 0;">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" style="font-weight:700; font-size:12.5px; color:#334155;" id="tPassLabel">
                Password
            </label>
            <div class="input-group">
                <span class="input-group-text" style="background:#f8fafc; border-radius:10px 0 0 10px;"><i class="fas fa-lock text-muted"></i></span>
                <input type="password" id="loginPassword" class="form-control" placeholder="••••••••" required style="font-weight:600; border-radius:0 10px 10px 0;">
            </div>
        </div>

        <div style="margin-top:20px;">
            <button type="submit" id="btnLoginSubmit" class="btn-submit-login">
                <i class="fas fa-sign-in-alt"></i> <span id="tBtnSignIn">Sign In to Update Prices</span>
            </button>
        </div>
    </form>

    <!-- Quick Demo Vendors Login Pills -->
    <div class="quick-vendor-pills">
        <div style="font-size:11.5px; font-weight:700; color:#64748b; margin-bottom:6px;" id="tDemoTitle">
            <i class="fas fa-bolt text-warning"></i> 1-Click Demo Vendors:
        </div>
        <div style="display:flex; flex-wrap:wrap;">
            <button type="button" class="vendor-pill" onclick="quickFill('03001234567', 'vendor123')">
                <i class="fas fa-industry text-primary"></i> Al-Madina Print
            </button>
            <button type="button" class="vendor-pill" onclick="quickFill('03219876543', 'vendor123')">
                <i class="fas fa-print text-primary"></i> Royal Offset
            </button>
            <button type="button" class="vendor-pill" onclick="quickFill('03335557799', 'vendor123')">
                <i class="fas fa-shipping-fast text-primary"></i> FastTrack Express
            </button>
            <button type="button" class="vendor-pill" onclick="quickFill('03452223344', 'vendor123')">
                <i class="fas fa-box text-primary"></i> Apex Packaging
            </button>
        </div>
    </div>

    <!-- Back to Admin link -->
    <div style="text-align:center; margin-top:16px;">
        <a href="index.php" style="font-size:12px; color:#64748b; text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Return to Graphicafix Admin
        </a>
    </div>

</div>

<script>
let CURRENT_LANG = localStorage.getItem('graphicafix_vendor_lang') || 'en';

const TRANSLATIONS = {
    en: {
        title: "Vendor Rate Portal",
        sub: "Graphicafix Commercial Printing Network",
        phoneLabel: "Phone Number / WhatsApp / Email",
        phonePlaceholder: "e.g. 03001234567",
        passLabel: "Password",
        btnSignIn: "Sign In to Update Prices",
        demoTitle: "<i class='fas fa-bolt text-warning'></i> 1-Click Demo Vendors:",
        signingIn: "Verifying credentials..."
    },
    ur: {
        title: "پرنٹ وینڈر پورٹل",
        sub: "گرافیکافکس پرنٹنگ نیٹ ورک — ریٹ لسٹ و قیمتیں",
        phoneLabel: "فون نمبر / واٹس ایپ یا ای میل",
        phonePlaceholder: "مثلاً 03001234567",
        passLabel: "پاس ورڈ درج کریں",
        btnSignIn: "لاگ اِن کریں اور ریٹس تبدیل کریں",
        demoTitle: "<i class='fas fa-bolt text-warning'></i> ٹیسٹنگ کے لیے وینڈر منتخب کریں:",
        signingIn: "تصدیق کی جا رہی ہے..."
    }
};

document.addEventListener("DOMContentLoaded", () => {
    switchLang(CURRENT_LANG);
});

function switchLang(lang) {
    CURRENT_LANG = lang;
    localStorage.setItem('graphicafix_vendor_lang', lang);

    const body = document.body;
    const btnEn = document.getElementById("btnEn");
    const btnUr = document.getElementById("btnUr");

    if (lang === 'ur') {
        body.classList.add('lang-ur');
        btnUr.classList.add('active');
        btnEn.classList.remove('active');
    } else {
        body.classList.remove('lang-ur');
        btnEn.classList.add('active');
        btnUr.classList.remove('active');
    }

    const t = TRANSLATIONS[lang];
    document.getElementById("tTitle").innerText = t.title;
    document.getElementById("tSub").innerText = t.sub;
    document.getElementById("tPhoneLabel").innerText = t.phoneLabel;
    document.getElementById("loginInput").placeholder = t.phonePlaceholder;
    document.getElementById("tPassLabel").innerText = t.passLabel;
    document.getElementById("tBtnSignIn").innerText = t.btnSignIn;
    document.getElementById("tDemoTitle").innerHTML = t.demoTitle;
}

function quickFill(phone, pass) {
    document.getElementById("loginInput").value = phone;
    document.getElementById("loginPassword").value = pass;
    document.getElementById("btnLoginSubmit").click();
}

async function handleVendorLogin(e) {
    e.preventDefault();
    const alertBox = document.getElementById("loginAlert");
    alertBox.style.display = "none";

    const btn = document.getElementById("btnLoginSubmit");
    const originalText = document.getElementById("tBtnSignIn").innerText;
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${TRANSLATIONS[CURRENT_LANG].signingIn}`;

    const formData = new FormData();
    formData.append("action", "vendor_login");
    formData.append("login_input", document.getElementById("loginInput").value.trim());
    formData.append("password", document.getElementById("loginPassword").value.trim());

    try {
        const res = await fetch("../api/print_vendor_api.php", {
            method: "POST",
            body: formData
        });
        const json = await res.json();

        if (json.success) {
            window.location.href = json.redirect_url;
        } else {
            alertBox.style.display = "block";
            alertBox.innerText = (CURRENT_LANG === 'ur' && json.message_ur) ? json.message_ur : json.message;
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-sign-in-alt"></i> <span id="tBtnSignIn">${originalText}</span>`;
        }
    } catch (err) {
        alertBox.style.display = "block";
        alertBox.innerText = "Connection error. Please try again.";
        btn.disabled = false;
        btn.innerHTML = `<i class="fas fa-sign-in-alt"></i> <span id="tBtnSignIn">${originalText}</span>`;
    }
}
</script>
</body>
</html>