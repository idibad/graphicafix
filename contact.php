<?php
// Hide errors to prevent visual 500 crashes
error_reporting(0);
ini_set('display_errors', '0');

include 'templates/header.php';

// ── Auto-patch contacts table safely to support Phone and Subject ─────────────
$cCols = array();
$cResult = @$conn->query("SHOW COLUMNS FROM contacts");
if ($cResult) {
    while ($row = $cResult->fetch_assoc()) {
        $cCols[] = $row['Field'];
    }
}
if (!in_array('phone', $cCols)) { @$conn->query("ALTER TABLE contacts ADD COLUMN phone VARCHAR(100) DEFAULT NULL AFTER email"); }
if (!in_array('subject', $cCols)) { @$conn->query("ALTER TABLE contacts ADD COLUMN subject VARCHAR(255) DEFAULT NULL AFTER phone"); }

$status_msg = '';
$status_type = '';

// ── Handle form submission ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $name    = trim(htmlspecialchars($_POST['name'] ?? ''));
    $email   = trim(htmlspecialchars($_POST['email'] ?? ''));
    $phone   = trim(htmlspecialchars($_POST['phone'] ?? ''));
    $subject = trim(htmlspecialchars($_POST['subject'] ?? ''));
    $message = trim(htmlspecialchars($_POST['message'] ?? ''));

    if (!empty($name) && !empty($email) && !empty($message)) {
        // Updated insert to include phone and subject safely
        $stmt = $conn->prepare("INSERT INTO contacts (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssss", $name, $email, $phone, $subject, $message);
            if ($stmt->execute()) {
                $status_msg = "Message sent successfully! Our team will get back to you shortly.";
                $status_type = "success";
            } else {
                $status_msg = "Failed to send message. Please try again later.";
                $status_type = "error";
            }
            $stmt->close();
        } else {
            // Fallback for older database structures if the alter table failed
            $stmtFallback = $conn->prepare("INSERT INTO contacts (name, email, message) VALUES (?, ?, ?)");
            if ($stmtFallback) {
                $comboMessage = "Phone: $phone\nSubject: $subject\n\n$message";
                $stmtFallback->bind_param("sss", $name, $email, $comboMessage);
                if ($stmtFallback->execute()) {
                    $status_msg = "Message sent successfully! Our team will get back to you shortly.";
                    $status_type = "success";
                }
                $stmtFallback->close();
            }
        }
    } else {
        $status_msg = "Please fill in all required fields.";
        $status_type = "error";
    }
}
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --primary: #024442;
        --primary-dark: #012d2b;
        --accent: #b8f35a;
        --bg-light: #f7f9f5;
        --text-gray: #555555;
    }

    body { font-family: 'Poppins', sans-serif; background-color: #fcfcfc; }

    /* ── Hero Section ── */
    .contact-hero {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        padding: 120px 0 80px;
        color: #fff;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .contact-hero::before {
        content: ''; position: absolute; inset: 0;
        background-image: radial-gradient(rgba(255,255,255,0.1) 1px, transparent 1px);
        background-size: 30px 30px; opacity: 0.3;
    }
    .contact-hero h1 { font-size: clamp(2.5rem, 4vw, 3.5rem); font-weight: 800; margin-bottom: 16px; position: relative; }
    .contact-hero h1 span { color: var(--accent); }
    .contact-hero p { font-size: 1.1rem; max-width: 600px; margin: 0 auto; color: rgba(255,255,255,0.8); line-height: 1.6; position: relative; }

    /* ── Main Wrapper ── */
    .contact-wrapper { padding: 80px 0; }
    
    /* ── Info Cards (Left Side) ── */
    .info-card {
        background: #fff; padding: 30px; border-radius: 20px;
        border: 1px solid #f0f0f0; display: flex; align-items: flex-start; gap: 20px;
        margin-bottom: 20px; transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0,0,0,0.02);
    }
    .info-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(2,68,66,0.06); border-color: var(--primary); }
    .info-icon {
        width: 60px; height: 60px; border-radius: 16px; background: rgba(184,243,90,0.15);
        color: var(--primary); display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem; flex-shrink: 0;
    }
    .info-card:hover .info-icon { background: var(--accent); }
    .info-content h4 { font-size: 1.1rem; font-weight: 700; color: var(--primary); margin-bottom: 6px; }
    .info-content p, .info-content a { font-size: 0.95rem; color: var(--text-gray); margin: 0 0 4px; text-decoration: none; display: block; }
    .info-content a:hover { color: var(--primary); font-weight: 600; }

    /* ── Form Section (Right Side) ── */
    .form-wrapper {
        background: #fff; border-radius: 24px; padding: 40px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.06); border: 1px solid #f0f0f0;
    }
    .form-wrapper h3 { font-size: 1.8rem; font-weight: 800; color: var(--primary); margin-bottom: 8px; }
    .form-wrapper p { color: var(--text-gray); font-size: 0.95rem; margin-bottom: 30px; }

    .form-label { font-size: 0.9rem; font-weight: 600; color: var(--primary); margin-bottom: 8px; }
    .form-control {
        border: 1.5px solid #e5e7eb; border-radius: 12px; padding: 14px 16px;
        font-size: 0.95rem; background: #f9fafb; transition: all 0.2s; width: 100%;
    }
    .form-control:focus {
        border-color: var(--primary); box-shadow: 0 0 0 4px rgba(2,68,66,0.1); background: #fff; outline: none;
    }
    .btn-submit {
        background: var(--primary); color: #fff; border: none; padding: 14px 30px;
        border-radius: 50px; font-weight: 700; font-size: 1rem; cursor: pointer;
        transition: all 0.2s; width: 100%; display: inline-block; text-align: center;
    }
    .btn-submit:hover { background: var(--accent); color: var(--primary); transform: translateY(-2px); }

    /* ── Status Messages ── */
    .alert-msg { padding: 16px 20px; border-radius: 12px; font-size: 0.95rem; font-weight: 600; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
    .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
    .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }

    /* ── Map Container ── */
    .map-container {
        width: 100%; height: 400px; border-radius: 24px; overflow: hidden;
        border: 1px solid #e5e7eb; margin-top: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.03);
    }
    .map-container iframe { width: 100%; height: 100%; border: 0; }

    @media (max-width: 991px) {
        .contact-hero { padding: 100px 20px 60px; }
        .form-wrapper { padding: 30px 20px; margin-top: 30px; }
    }
</style>

<section class="contact-hero">
    <div class="container">
        <h1>Let's work <span>together</span></h1>
        <p>Whether you have a question, want to discuss a new project, or just want to say hello, our team is ready to listen.</p>
    </div>
</section>

<section class="contact-wrapper">
    <div class="container">
        <div class="row g-5">

            <div class="col-lg-5" data-aos="fade-right">
                <div style="margin-bottom: 30px;">
                    <h2 style="font-size: 2rem; font-weight: 800; color: var(--primary);">Contact Info</h2>
                    <p style="color: var(--text-gray); font-size: 1rem;">Reach out to us directly through any of the channels below.</p>
                </div>

                <div class="info-card">
                    <div class="info-icon"><i class="fas fa-envelope"></i></div>
                    <div class="info-content">
                        <h4>Email Us</h4>
                        <a href="mailto:contact@graphicafix.com">contact@graphicafix.com</a>
                        <a href="mailto:support@graphicafix.com">support@graphicafix.com</a>
                    </div>
                </div>

                <div class="info-card">
                    <div class="info-icon"><i class="fab fa-whatsapp"></i></div>
                    <div class="info-content">
                        <h4>Call / WhatsApp</h4>
                        <a href="https://wa.me/923454568986" target="_blank">+92 345 4568986</a>
                        <p style="font-size: 0.8rem; margin-top: 4px;">Mon - Fri, 9am - 6pm</p>
                    </div>
                </div>

                <div class="info-card">
                    <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="info-content">
                        <h4>Visit Our Office</h4>
                        <p>Rawalpindi, Pakistan</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-7" data-aos="fade-left">
                <div class="form-wrapper">
                    <h3>Send a Message</h3>
                    <p>Fill out the form below and we will get back to you within 24 hours.</p>

                    <?php if (!empty($status_msg)): ?>
                        <div class="alert-msg <?php echo $status_type === 'success' ? 'alert-success' : 'alert-error'; ?>">
                            <i class="<?php echo $status_type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'; ?>"></i>
                            <?php echo $status_msg; ?>
                        </div>
                    <?php endif; ?>

                    <form id="contactForm" action="" method="POST">
                        <input type="hidden" name="submit_contact" value="1">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Your Name <span style="color:#ef4444;">*</span></label>
                                <input type="text" class="form-control" name="name" placeholder="Name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address <span style="color:#ef4444;">*</span></label>
                                <input type="email" class="form-control" name="email" placeholder="Email" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="phone" placeholder="+92 234 567 890">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subject</label>
                                <input type="text" class="form-control" name="subject" placeholder="Project Inquiry">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Your Message <span style="color:#ef4444;">*</span></label>
                                <textarea class="form-control" name="message" rows="5" placeholder="Tell us about your project or inquiry..." required></textarea>
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn-submit">Send Message</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>

        <div class="row mt-5" data-aos="fade-up">
            <div class="col-12">
                <div class="map-container">
                    <iframe 
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d106300.2227181358!2d72.95543781525997!3d33.60333215288599!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x38df948974419acb%3A0x984357e1632d30f!2sRawalpindi%2C%20Punjab%2C%20Pakistan!5e0!3m2!1sen!2s!4v1700000000000!5m2!1sen!2s" 
                        loading="lazy" 
                        allowfullscreen="" 
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            </div>
        </div>

    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Lightweight Client-side validation to ensure required fields
document.getElementById("contactForm").addEventListener("submit", function(e) {
    const name = this.name.value.trim();
    const email = this.email.value.trim();
    const message = this.message.value.trim();

    if (!name || !email || !message) {
        e.preventDefault();
        alert("Please fill in your Name, Email, and Message before submitting.");
    }
});
</script>

<?php include 'templates/footer.php'; ?>