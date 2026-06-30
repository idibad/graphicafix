<?php
// Prevent PHP errors from breaking the JSON response visually
error_reporting(0);
ini_set('display_errors', '0');

// Tell the browser to expect pure JSON
header('Content-Type: application/json');

// ══════════════════════════════════════════════════════════════════════════════
//  EMAIL HELPERS (Copied exactly from your dashboard for brand consistency)
// ══════════════════════════════════════════════════════════════════════════════
function buildEmailWrapper($icon, $heading, $body_html, $btn_text='', $btn_url='') {
    $siteUrl = 'https://graphicafix.com';
    $btn = $btn_text
        ? "<div style='text-align:center;margin-top:24px;'><a href='$btn_url' style='display:inline-block;background:#024442;color:#fff;padding:13px 30px;border-radius:50px;text-decoration:none;font-weight:700;font-size:.88rem;'>$btn_text &rarr;</a></div>"
        : '';
    return "<!DOCTYPE html><html><head><meta charset='utf-8'></head>
    <body style='margin:0;padding:0;background:#f4f6f8;font-family:Arial,sans-serif;'>
    <div style='max-width:580px;margin:36px auto;'>
        <div style='background:#024442;border-radius:16px 16px 0 0;padding:32px 40px;text-align:center;'>
            <div style='margin-bottom:8px;'><img src='$icon' alt='GraphicaFix Logo' width='50px'></div>
            <h1 style='margin:0;color:#fff;font-size:1.45rem;font-weight:700;'>$heading</h1>
        </div>
        <div style='background:#fff;padding:32px 40px;'>$body_html$btn</div>
        <div style='background:#f7f9f5;border-radius:0 0 16px 16px;padding:14px 40px;text-align:center;border-top:1px solid #e0e0e0;'>
            <p style='margin:0;font-size:11.5px;color:#aaa;'>Graphicafix &middot; <a href='$siteUrl' style='color:#024442;'>$siteUrl</a></p>
        </div>
    </div></body></html>";
}

function sendWelcomeEmail($email) {
    $icon = 'images/logo.png';
    $heading = 'Welcome to Graphicafix!';
    $siteUrl = 'https://graphicafix.com';
    
    // The email body delivering the promised 10% discount
    $body = "
        <p style='font-size:15px;color:#333;margin:0 0 14px;'>Hi there,</p>
        <p style='font-size:14px;color:#555;line-height:1.7;'>Thank you for joining the Graphicafix community! As promised, here is your exclusive 10% discount code for your first course.</p>
        
        <div style='background:#f0f9f0;border:1px solid #c3e6cb;padding:20px;border-radius:10px;margin-top:22px;text-align:center;'>
            <h4 style='margin:0 0 10px;color:#2b7a2b;font-size:15px;'>🎁 Your Discount Code</h4>
            <span style='font-family:monospace;background:#fff;padding:8px 16px;border:2px dashed #2b7a2b;border-radius:6px;font-size:20px;font-weight:bold;color:#2b7a2b;letter-spacing:2px;'>GFX-WELCOME10</span>
        </div>
        
        <p style='font-size:14px;color:#555;line-height:1.7;margin-top:20px;'>Keep an eye on your inbox. We'll be sending you insider branding tips, development tutorials, and early access to our upcoming programs (like the Applied AI launch).</p>
    ";

    $btnText = 'Explore Courses';
    $btnUrl = "$siteUrl/courses"; // Assuming your courses page based on htaccess

    $html = buildEmailWrapper($icon, $heading, $body, $btnText, $btnUrl);
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Graphicafix <noreply@graphicafix.com>\r\n";
    $headers .= "Reply-To: info@graphicafix.com\r\n";
    
    // Send the email
    mail($email, "$icon Welcome to Graphicafix! Here is your 10% Off code.", $html, $headers);
}

// ══════════════════════════════════════════════════════════════════════════════
//  SUBSCRIPTION LOGIC
// ══════════════════════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    $server = "localhost";
    $username = "u935542951_graphicafix";
    $password = "2025.Graphica_fix";
    $db = "u935542951_graphicafix_db";


    $conn = @mysqli_connect($server, $username,$password, $db);

    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit;
    }

    $conn->query("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $check = $conn->prepare("SELECT id FROM newsletter_subscribers WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You are already subscribed!']);
        $check->close();
        $conn->close();
        exit;
    }
    $check->close();

    $insert = $conn->prepare("INSERT INTO newsletter_subscribers (email) VALUES (?)");
    $insert->bind_param("s", $email);
    
    if ($insert->execute()) {
        // 🔥 Send the welcome email immediately after saving to DB!
        sendWelcomeEmail($email);
        
        // Return a slightly modified success message
        echo json_encode(['success' => true, 'message' => 'Success! Check your email for your 10% code.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    }
    
    $insert->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>