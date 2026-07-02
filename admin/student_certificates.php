<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 1. Trap the dashboard header HTML so we can delete it if we are downloading a certificate
ob_start();
include 'dashboard_header.php';

$user_id = intval($_SESSION['user_id'] ?? 0);

// Fallback: If email isn't in session, forcefully grab it from the database using user_id
$email = $_SESSION['email'] ?? '';
if (empty($email) && $user_id > 0) {
    $u_q = $conn->query("SELECT email FROM users WHERE user_id = $user_id");
    if ($u_q && $u_q->num_rows > 0) {
        $email = $u_q->fetch_assoc()['email'];
    }
}
$safe_email = mysqli_real_escape_string($conn, strtolower(trim($email)));

// ── 2. HANDLE PDF/PRINT DOWNLOAD (Bypasses the rest of the page) ──
if (isset($_GET['download_cert'])) {
    $course_id = intval($_GET['download_cert']);
    
    ob_clean(); // Erase the trapped dashboard HTML
    
    // Fetch Student Name
    $user_q = $conn->query("SELECT name FROM users WHERE user_id = $user_id");
    $student_name = $user_q && $user_q->num_rows > 0 ? $user_q->fetch_assoc()['name'] : 'Student';

    // Fetch Certificate Data Safely
    $cert_query = $conn->query("
        SELECT c.title, cert.certificate_code, cert.issue_date, u.name as instructor_name
        FROM certificates cert
        JOIN courses c ON cert.course_id = c.id
        LEFT JOIN users u ON c.instructor_id = u.user_id
        WHERE cert.course_id = $course_id AND cert.student_id = $user_id
    ");
    
    $cert_data = $cert_query ? $cert_query->fetch_assoc() : null;

    if ($cert_data) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Certificate - <?= htmlspecialchars($cert_data['certificate_code']) ?></title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Montserrat:wght@400;600&family=Pinyon+Script&display=swap');
                
                @page { size: A4 landscape; margin: 0; }
                
                body {
                    margin: 0; padding: 0;
                    background: #2b2b2b; 
                    display: flex; justify-content: center; align-items: center;
                    min-height: 100vh; font-family: 'Montserrat', sans-serif;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                
                .cert-wrapper {
                    width: 297mm; height: 210mm; /* A4 Landscape */
                    background-color: #fff; 
                    background-image: url('../images/cert_bg.png');
                    background-size: 100%;
                    
                    position: relative;
                    box-shadow: 0 15px 40px rgba(0,0,0,0.4);
                }

                /* Layered Border Design */
                .cert-border-outer {
                    position: absolute;
                    top: 12mm; left: 12mm; right: 12mm; bottom: 12mm;
                    border: 1.5px solid #D4AF37; /* Gold */
                    padding: 4px;
                }

                .cert-border-inner {
                    width: 100%; height: 100%;
                    border: 8px solid #024442; /* Emerald Green */
                    background: rgba(255, 255, 255, 0.94); /* Softens the background pattern behind text */
                    position: relative;
                    box-sizing: border-box;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    padding: 0 15mm;
                }

                /* Inner Gold Trim */
                .cert-border-inner::before {
                    content: '';
                    position: absolute;
                    top: 5px; left: 5px; right: 5px; bottom: 5px;
                    border: 1px solid #D4AF37;
                    pointer-events: none;
                }

                /* PERFECT CENTERING FIXES BELOW */
                .logo { 
                    height: 50px; 
                    margin: 15mm auto 10px auto; 
                    display: block; 
                    object-fit: contain; 
                }
                
                .cert-header, .cert-subheader, .cert-text {
                    text-align: center;
                    width: 100%;
                    display: block;
                }

                .cert-header { 
                    font-family: 'Cinzel', serif; 
                    font-size: 42px; 
                    color: #024442; 
                    font-weight: 700; 
                    letter-spacing: 6px; 
                    margin-bottom: 8px; 
                }
                
                .cert-subheader { 
                    font-family: 'Montserrat', sans-serif;
                    font-size: 14px; 
                    color: #D4AF37; 
                    font-weight: 600; 
                    letter-spacing: 4px; 
                    text-transform: uppercase; 
                    margin-bottom: 30px; 
                }
                
                .cert-text { 
                    font-size: 15px; 
                    color: #444; 
                    margin-bottom: 10px; 
                }
                
                /* The Name Wrapper ensures 100% width block centering */
                .cert-name-wrapper {
                    width: 100%;
                    display: block;
                    text-align: center;
                    margin: 5px 0 20px 0;
                }

                .cert-name {
                    font-family: 'Pinyon Script', cursive; 
                    font-size: 68px; 
                    color: #024442;
                    line-height: 1.1;
                    border-bottom: 1.5px solid #D4AF37; 
                    padding: 0 50px 10px 50px;
                    display: inline-block; /* Shrink-wraps the text for the underline */
                }
                
                .cert-course-title {
                    font-family: 'Cinzel', serif; 
                    font-size: 28px; 
                    color: #111; 
                    font-weight: 700;
                    margin: 10px auto 40px auto; 
                    max-width: 85%; 
                    line-height: 1.3; 
                    text-transform: uppercase;
                    letter-spacing: 2px;
                    text-align: center;
                }

                /* Footer Alignment Fix */
                .cert-footer { 
                    display: flex; 
                    justify-content: space-between; 
                    align-items: flex-end; 
                    width: 100%;
                    position: absolute; 
                    bottom: 15mm; 
                    left: 0;
                    padding: 0 25mm; /* Inward padding so it balances perfectly */
                    box-sizing: border-box;
                }
                
                /* Hardcode signature block widths to guarantee the middle stamp stays perfectly centered */
                .signature-block { width: 220px; text-align: center; flex-shrink: 0; }
                
                .date-line {
                    font-family: 'Montserrat', sans-serif; 
                    font-size: 16px; 
                    font-weight: 600;
                    color: #111;
                    border-bottom: 1px solid #333; 
                    padding-bottom: 8px;
                    margin-bottom: 5px;
                }

                .signature-line { 
                    font-family: 'Pinyon Script', cursive; 
                    font-size: 38px; 
                    color: #024442; 
                    border-bottom: 1px solid #333; 
                    margin-bottom: 5px; 
                    line-height: 0.8;
                    padding-bottom: 5px;
                }
                
                .signature-text { 
                    font-size: 12px; 
                    color: #777; 
                    font-weight: 600; 
                    letter-spacing: 1px; 
                    text-transform: uppercase; 
                    margin-top: 5px; 
                }
                
                .seal-img { 
                    width: 140px; 
                    height: 140px; 
                    object-fit: contain; 
                    position: relative; 
                    top: 10px; 
                    flex-shrink: 0;
                }

                .cert-id { 
                    position: absolute; 
                    bottom: 6mm; 
                    left: 0; right: 0;
                    font-size: 10px; 
                    color: #999; 
                    letter-spacing: 1px; 
                    font-weight: 600; 
                    text-align: center; 
                    width: 100%;
                }

                @media print {
                    body { background: none; }
                    .cert-wrapper { box-shadow: none; margin: 0; }
                }
            </style>
        </head>
        <body onload="setTimeout(() => window.print(), 500);">
            <div class="cert-wrapper">
                <div class="cert-border-outer">
                    <div class="cert-inner-border">
                        
                        <img src="../images/logo-secondary.png" alt="Graphicafix" class="logo" onerror="this.style.display='none'">
                        
                        <div class="cert-header">Certificate of Completion</div>
                        <div class="cert-subheader">Proudly Presented To</div>
                        
                        <div class="cert-text">This is to proudly certify that</div>
                        
                        <div class="cert-name-wrapper">
                            <div class="cert-name"><?= htmlspecialchars($student_name) ?></div>
                        </div>
                        
                        <div class="cert-text">has successfully fulfilled the requirements and demonstrated mastery in</div>
                        <div class="cert-course-title"><?= htmlspecialchars($cert_data['title']) ?></div>
                        
                        <div class="cert-footer">
                            <div class="signature-block">
                                <div class="date-line"><?= date('F j, Y', strtotime($cert_data['issue_date'])) ?></div>
                                <div class="signature-text">Date of Issue</div>
                            </div>
                            
                            <img src="../images/cert_stamp.png" class="seal-img" alt="Certified Stamp" onerror="this.style.display='none'">
                            
                            <div class="signature-block">
                                <div class="signature-line"><?= htmlspecialchars($cert_data['instructor_name'] ?? 'Graphicafix') ?></div>
                                <div class="signature-text">Lead Instructor</div>
                            </div>
                        </div>
                        
                        <div class="cert-id">
                            CERTIFICATE ID: <?= htmlspecialchars($cert_data['certificate_code']) ?> &nbsp;|&nbsp; 
                            VERIFY AT: <?= defined('SITE_URL') ? SITE_URL : 'GRAPHICAFIX.COM' ?>
                        </div>
                        
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    } else {
        echo "<h3 style='font-family:sans-serif; text-align:center; margin-top:50px; color:#555;'>Certificate not found or course not completed.</h3>";
        exit;
    }
}

// 3. If we are NOT downloading a certificate, flush the trapped HTML and show the normal dashboard!
ob_end_flush();


// ── 4. MISSING TABLE FIX: Auto-Create Certificates Table ──
$conn->query("
    CREATE TABLE IF NOT EXISTS certificates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        certificate_code VARCHAR(50) UNIQUE NOT NULL,
        issue_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");


// ── 5. Auto-Issue missing certificates for COMPLETED courses ──
$unissued = $conn->query("
    SELECT e.course_id 
    FROM course_enrollments e 
    LEFT JOIN certificates cert ON e.course_id = cert.course_id AND cert.student_id = $user_id
    WHERE LOWER(TRIM(e.student_email)) = '$safe_email' AND LOWER(TRIM(e.status)) = 'completed' AND cert.id IS NULL
");

if ($unissued && $unissued->num_rows > 0) {
    $stmt = $conn->prepare("INSERT INTO certificates (student_id, course_id, certificate_code) VALUES (?, ?, ?)");
    while ($row = $unissued->fetch_assoc()) {
        $code = 'GFX-' . date('Ym') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $stmt->bind_param("iis", $user_id, $row['course_id'], $code);
        $stmt->execute();
    }
}

// ── 6. Fetch and Filter ALL Registered Courses securely in PHP ──
$certs_query = $conn->query("
    SELECT 
        e.status AS enroll_status, 
        c.id AS course_id,
        c.title, 
        c.thumbnail, 
        cert.certificate_code, 
        cert.issue_date
    FROM course_enrollments e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN certificates cert ON (cert.course_id = c.id AND cert.student_id = $user_id)
    WHERE LOWER(TRIM(e.student_email)) = '$safe_email' 
");

$display_courses = [];

if ($certs_query && $certs_query->num_rows > 0) {
    while ($row = $certs_query->fetch_assoc()) {
        $st = strtolower(trim($row['enroll_status']));
        
        // Skip rejected/cancelled entirely
        if ($st === 'rejected' || $st === 'cancelled') continue;
        
        $cid = $row['course_id'];
        
        // Ensure "Completed" always overwrites "Pending/Active" if there are duplicates
        if (!isset($display_courses[$cid]) || $st === 'completed') {
            $display_courses[$cid] = $row;
        }
    }
}

// Sort the courses so Completed is first
usort($display_courses, function($a, $b) {
    $stA = strtolower(trim($a['enroll_status']));
    $stB = strtolower(trim($b['enroll_status']));
    if ($stA === 'completed' && $stB !== 'completed') return -1;
    if ($stA !== 'completed' && $stB === 'completed') return 1;
    return 0;
});

?>
<div class="height-100">
    <div class="main-header" style="margin-bottom: 20px;">
        <h3><i class="fas fa-scroll"></i> My Certifications</h3>
        <p style="color:#666; font-size:14px;">Track your progress and download certificates for completed courses.</p>
    </div>

    <?php if (!$certs_query): ?>
        <div style="background:#fee2e2; color:#ef4444; padding:15px; border-radius:10px; margin-bottom:20px;">
            <strong>SQL Error:</strong> <?= $conn->error ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if(!empty($display_courses)): foreach($display_courses as $cert): 
            $status = strtolower(trim($cert['enroll_status']));
            $is_completed = ($status === 'completed');
        ?>
        <div class="col-md-6 col-lg-4">
            
            <?php if($is_completed): ?>
                <div class="d-card" style="padding:0; overflow:hidden; border:2px solid var(--accent); position:relative; height:100%; display:flex; flex-direction:column;">
                    <div style="background:var(--primary); padding:30px 20px; text-align:center; color:#fff; position:relative;">
                        <div style="position:absolute; top:10px; right:10px; background:#10b981; color:#fff; font-size:10px; font-weight:800; padding:4px 10px; border-radius:20px; text-transform:uppercase;">Earned</div>
                        <i class="fas fa-award" style="font-size:3rem; color:var(--accent); margin-bottom:10px;"></i>
                        <h4 style="font-size:1.1rem; font-weight:700; margin:0; line-height:1.4;"><?= htmlspecialchars($cert['title']) ?></h4>
                    </div>
                    <div style="padding: 20px; background:#fff; text-align:center; flex-grow:1; display:flex; flex-direction:column;">
                        <div style="font-size:11px; color:#888; text-transform:uppercase; font-weight:700; letter-spacing:1px;">Certificate ID</div>
                        <div style="font-family:monospace; font-size:16px; font-weight:800; color:#333; margin-bottom:15px;"><?= $cert['certificate_code'] ?></div>
                        <div style="font-size:13px; color:#666; margin-bottom:20px;">Issued: <?= date('F j, Y', strtotime($cert['issue_date'])) ?></div>
                        
                        <a href="student_certificates.php?download_cert=<?= $cert['course_id'] ?>" target="_blank" class="btn btn-primary-custom" style="width:100%; padding:10px; margin-top:auto; text-decoration:none;">
                            <i class="fas fa-download"></i> Download PDF
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="d-card" style="padding:0; overflow:hidden; border:1px solid #e0e0e0; position:relative; height:100%; display:flex; flex-direction:column; background:#f9f9f9;">
                    <div style="background:#e5e7eb; padding:30px 20px; text-align:center; color:#888; position:relative;">
                        <div style="position:absolute; top:10px; right:10px; background:#9ca3af; color:#fff; font-size:10px; font-weight:800; padding:4px 10px; border-radius:20px; text-transform:uppercase;">
                            <?= $status === 'active' ? 'In Progress' : ucfirst($status) ?>
                        </div>
                        <i class="fas fa-lock" style="font-size:2.5rem; color:#cbd5e1; margin-bottom:10px;"></i>
                        <h4 style="font-size:1.1rem; font-weight:700; margin:0; line-height:1.4; color:#6b7280;"><?= htmlspecialchars($cert['title']) ?></h4>
                    </div>
                    <div style="padding: 20px; background:#f9f9f9; text-align:center; flex-grow:1; display:flex; flex-direction:column;">
                        <div style="font-size:13px; color:#666; margin-bottom:10px; font-weight:600;">Course not yet completed</div>
                        <div style="font-size:12px; color:#999; margin-bottom:20px; padding:0 10px;">Finish all your course requirements to automatically unlock this certificate.</div>
                        
                        <button class="btn" style="width:100%; padding:10px; margin-top:auto; background:#f3f4f6; color:#9ca3af; border:1px solid #e5e7eb; border-radius:50px; font-weight:700; cursor:not-allowed;" disabled>
                            <i class="fas fa-lock"></i> Locked
                        </button>
                    </div>
                </div>
            <?php endif; ?>

        </div>
        <?php endforeach; else: ?>
            <div class="col-12 text-center" style="padding: 60px 20px; color:#888;">
                <i class="fas fa-certificate" style="font-size:40px; margin-bottom:15px; color:#ddd;"></i>
                <p>You haven't enrolled in any courses yet.</p>
                <a href="courses.php" class="btn btn-primary-custom" style="text-decoration:none; margin-top:10px; display:inline-block;">Browse Courses</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include('dashboard_footer.php'); ?>