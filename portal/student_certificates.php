<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

ob_start();
include('dashboard_header.php');

$user_id = intval($_SESSION['user_id'] ?? 0);

// Handle certificate claim URL parameter
if (isset($_GET['claim'])) {
    $cid = intval($_GET['claim']);
    
    // Verify 100% completion
    $tot = $conn->query("SELECT COUNT(*) as c FROM recorded_lectures WHERE course_id = $cid AND status = 'approved'")->fetch_assoc()['c'] ?? 0;
    $com = $conn->query("SELECT COUNT(*) as c FROM student_video_progress WHERE course_id = $cid AND student_id = $user_id AND is_completed = 1")->fetch_assoc()['c'] ?? 0;
    
    if ($tot > 0 && $com >= $tot) {
        // Check if certificate already exists
        $chk = $conn->query("SELECT id FROM certificates WHERE student_id = $user_id AND course_id = $cid");
        if ($chk && $chk->num_rows === 0) {
            $code = 'GFX-CERT-' . strtoupper(substr(md5($user_id . '_' . $cid . '_' . time()), 0, 8));
            $s = $conn->prepare("INSERT INTO certificates (student_id, course_id, certificate_code) VALUES (?, ?, ?)");
            $s->bind_param("iis", $user_id, $cid, $code);
            $s->execute();
        }
    }
    header("Location: student_certificates.php?congrats=1"); exit;
}

// Handle Print / Download PDF view
if (isset($_GET['download'])) {
    $cid = intval($_GET['download']);
    ob_clean(); // wipe trapped header

    $user_q = $conn->query("SELECT name FROM users WHERE user_id = $user_id");
    $sname = $user_q && $user_q->num_rows > 0 ? $user_q->fetch_assoc()['name'] : 'Student';

    $cert_q = $conn->query("
        SELECT c.title, cert.certificate_code, cert.issue_date, u.name as instructor_name
        FROM certificates cert
        JOIN courses c ON cert.course_id = c.id
        LEFT JOIN users u ON c.instructor_id = u.user_id
        WHERE cert.course_id = $cid AND cert.student_id = $user_id
    ");
    $cert = $cert_q ? $cert_q->fetch_assoc() : null;

    if ($cert):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate - <?= htmlspecialchars($cert['certificate_code']) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=Montserrat:wght@400;600;700&family=Pinyon+Script&display=swap');
        @page { size: A4 landscape; margin: 0; }
        body {
            margin: 0; padding: 0; background: #1e293b; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: 'Montserrat', sans-serif;
            -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
        }
        .cert-wrapper {
            width: 297mm; height: 210mm; background: #fff; background-image: url('../images/cert_bg.png'); background-size: cover; position: relative; box-shadow: 0 25px 50px rgba(0,0,0,0.5); overflow: hidden;
        }
        .outer-border { position: absolute; inset: 12mm; border: 2px solid #D4AF37; padding: 6px; }
        .inner-border { width: 100%; height: 100%; border: 10px solid #024442; background: rgba(255,255,255,0.96); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0 20mm; text-align: center; position: relative; }
        .inner-border::before { content: ''; position: absolute; inset: 6px; border: 1px solid #D4AF37; pointer-events: none; }
        .logo-box img { height: 45px; margin-bottom: 10px; }
        h1 { font-family: 'Cinzel', serif; font-size: 38px; color: #024442; margin: 0; letter-spacing: 3px; font-weight: 800; }
        .sub-heading { font-size: 14px; text-transform: uppercase; color: #888; letter-spacing: 4px; margin-top: 5px; margin-bottom: 25px; }
        .certifies { font-size: 16px; color: #444; margin-bottom: 15px; font-style: italic; }
        .student-name { font-family: 'Pinyon Script', cursive; font-size: 64px; color: #D4AF37; margin: 0 0 10px; line-height: 1; border-bottom: 2px solid #D4AF37; padding: 0 40px 10px; }
        .reason { font-size: 16px; color: #333; line-height: 1.6; max-width: 800px; margin-top: 15px; }
        .course-title { font-family: 'Cinzel', serif; font-size: 26px; font-weight: 700; color: #024442; margin: 10px 0; }
        .footer-signatures { display: flex; justify-content: space-between; width: 100%; margin-top: 45px; padding: 0 30px; }
        .sig-block { text-align: center; width: 220px; }
        .sig-line { border-bottom: 1.5px solid #333; margin-bottom: 8px; height: 35px; font-family: 'Pinyon Script', cursive; font-size: 28px; color: #024442; display: flex; align-items: flex-end; justify-content: center; }
        .sig-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #555; }
        .cert-badge { position: absolute; bottom: 25mm; width: 85px; height: 85px; background: #D4AF37; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 35px; }
        .code-tag { position: absolute; bottom: 8px; right: 15px; font-size: 10px; font-family: monospace; color: #888; }
        .print-btn { position: fixed; top: 20px; right: 20px; background: #10b981; color: white; border: none; padding: 14px 28px; font-size: 16px; font-weight: 800; border-radius: 50px; cursor: pointer; box-shadow: 0 10px 25px rgba(0,0,0,0.3); z-index: 999; }
        @media print { .print-btn { display: none; } }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ Print Certificate</button>
    <div class="cert-wrapper">
        <div class="outer-border">
            <div class="inner-border">
                <div class="logo-box"><img src="../images/logo.png" alt="Graphicafix"></div>
                <h1>Certificate of Achievement</h1>
                <div class="sub-heading">Excellence in Professional Education</div>
                
                <div class="certifies">This is proudly presented to</div>
                <div class="student-name"><?= htmlspecialchars($sname) ?></div>
                
                <div class="reason">For successfully completing the comprehensive curriculum and mastering all required professional standards in</div>
                <div class="course-title"><?= htmlspecialchars($cert['title']) ?></div>

                <div class="footer-signatures">
                    <div class="sig-block">
                        <div class="sig-line"><?= date('M d, Y', strtotime($cert['issue_date'])) ?></div>
                        <div class="sig-label">Date of Issuance</div>
                    </div>
                    <div class="sig-block">
                        <div class="sig-line">Graphicafix</div>
                        <div class="sig-label">Authorized Director</div>
                    </div>
                </div>

                <div class="code-tag">Verification Code: <?= htmlspecialchars($cert['certificate_code']) ?></div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
    exit;
    endif;
}

// Fetch all earned certificates
$certs = $conn->query("
    SELECT cert.*, c.title, c.thumbnail 
    FROM certificates cert 
    JOIN courses c ON cert.course_id = c.id 
    WHERE cert.student_id = $user_id 
    ORDER BY cert.issue_date DESC
");
?>

<div class="height-100">
    <div class="main-header mb-4">
        <div>
            <h3 style="font-weight:800; color:#024442; margin:0;"><i class="fas fa-award"></i> My Certifications</h3>
            <p style="color:#64748b; font-size:14px; margin:4px 0 0;">Download official verified Graphicafix course certificates.</p>
        </div>
    </div>

    <?php if(isset($_GET['congrats'])): ?>
        <div class="alert alert-success fw-bold p-4 rounded-3 shadow-sm mb-4" style="background:#f0fdf4; border:2px solid #10b981; color:#15803d;">
            <h4>🏆 Congratulations!</h4>
            <p class="mb-0">You have successfully claimed your official verified course certificate. You can print or download it below.</p>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if($certs && $certs->num_rows > 0): while($c = $certs->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-4">
                <div class="d-card text-center" style="padding:30px; border:2px solid #d4af37; background:linear-gradient(180deg,#fff,#fffdf0); position:relative; overflow:hidden;">
                    <div style="position:absolute; top:-10px; right:-10px; width:70px; height:70px; background:#d4af37; color:white; display:flex; align-items:flex-end; justify-content:flex-start; padding:8px; font-size:24px; border-radius:0 0 0 60px;"><i class="fas fa-ribbon"></i></div>
                    
                    <div style="font-size:3.5rem; color:#d4af37; margin-bottom:16px;"><i class="fas fa-certificate"></i></div>
                    <h4 style="font-weight:800; color:#0f172a; margin-bottom:8px;"><?= htmlspecialchars($c['title']) ?></h4>
                    <div style="font-size:12px; font-family:monospace; color:#888; margin-bottom:16px;">ID: <?= htmlspecialchars($c['certificate_code']) ?></div>
                    <div style="font-size:12.5px; color:#64748b; margin-bottom:24px;">Issued on <?= date('M d, Y', strtotime($c['issue_date'])) ?></div>

                    <a href="student_certificates.php?download=<?= $c['course_id'] ?>" target="_blank" class="btn btn-primary-custom" style="background:#024442; color:#b8f35a; width:100%; justify-content:center; padding:12px; font-weight:800;"><i class="fas fa-print"></i> View & Print Certificate</a>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div class="col-12 text-center py-5">
                <i class="fas fa-award fs-1 text-muted mb-3"></i>
                <h4 class="fw-bold">No Certificates Earned Yet</h4>
                <p class="text-muted">Complete 100% of the video lectures in an enrolled course to unlock your certificate.</p>
                <a href="student_courses.php" class="btn btn-primary-custom">Go to My Courses</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include('dashboard_footer.php'); ?>
