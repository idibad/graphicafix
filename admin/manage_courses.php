<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

include 'dashboard_header.php';

if ($role !== 'admin' && $role !== 'teacher') {
    header("Location: index.php?error=unauthorized");
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
//  AUTO-PATCH: ensure all columns exist before anything else runs
// ══════════════════════════════════════════════════════════════════════════════
$existing_cols = array_column(
    $conn->query("SHOW COLUMNS FROM courses")->fetch_all(MYSQLI_ASSOC),
    'Field'
);
$patch_cols = [
    'thumbnail'             => "VARCHAR(500) DEFAULT NULL AFTER slug",
    'discount_percent'      => "INT DEFAULT 0 AFTER price",
    'discount_end_date'     => "DATE DEFAULT NULL AFTER discount_percent",
    'installments_enabled'  => "TINYINT(1) DEFAULT 0 AFTER discount_end_date",
    'installment_count'     => "INT DEFAULT 1 AFTER installments_enabled",
    'student_discount'      => "INT DEFAULT 0 AFTER installment_count",
    'start_date'            => "DATE DEFAULT NULL AFTER duration_mins",
    'instructor_id'         => "INT DEFAULT NULL AFTER id",
];
foreach ($patch_cols as $col => $def) {
    if (!in_array($col, $existing_cols)) {
        $conn->query("ALTER TABLE courses ADD COLUMN $col $def");
    }
}

// Drop stale FK if it blocks alters
$fk = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='courses' AND CONSTRAINT_NAME='courses_ibfk_1'");
if ($fk && $fk->num_rows > 0) $conn->query("ALTER TABLE courses DROP FOREIGN KEY courses_ibfk_1");

// Referral codes table
$conn->query("CREATE TABLE IF NOT EXISTS referral_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    used_by_email VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Fetch teachers, students, and courses for dropdowns
$teachers = $conn->query("SELECT user_id, name FROM users WHERE role='teacher' ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC) ?? [];
$all_students = $conn->query("SELECT user_id, name, email FROM users WHERE role='student' ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC) ?? [];
$all_courses_list = $conn->query("SELECT id, title FROM courses ORDER BY title ASC")->fetch_all(MYSQLI_ASSOC) ?? [];

// ══════════════════════════════════════════════════════════════════════════════
//  EMAIL HELPERS
// ══════════════════════════════════════════════════════════════════════════════
function buildEmailWrapper($icon, $heading, $body_html, $btn_text='', $btn_url='') {
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://graphicafix.com';
    $btn = $btn_text
        ? "<div style='text-align:center;margin-top:24px;'><a href='$btn_url' style='display:inline-block;background:#024442;color:#fff;padding:13px 30px;border-radius:50px;text-decoration:none;font-weight:700;font-size:.88rem;'>$btn_text &rarr;</a></div>"
        : '';
    return "<!DOCTYPE html><html><head><meta charset='utf-8'></head>
    <body style='margin:0;padding:0;background:#f4f6f8;font-family:Arial,sans-serif;'>
    <div style='max-width:580px;margin:36px auto;'>
        <div style='background:#024442;border-radius:16px 16px 0 0;padding:32px 40px;text-align:center;'>
            <div style='font-size:2.2rem;margin-bottom:8px;'>$icon</div>
            <h1 style='margin:0;color:#fff;font-size:1.45rem;font-weight:700;'>$heading</h1>
        </div>
        <div style='background:#fff;padding:32px 40px;'>$body_html$btn</div>
        <div style='background:#f7f9f5;border-radius:0 0 16px 16px;padding:14px 40px;text-align:center;border-top:1px solid #e0e0e0;'>
            <p style='margin:0;font-size:11.5px;color:#aaa;'>Graphicafix &middot; <a href='$siteUrl' style='color:#024442;'>$siteUrl</a></p>
        </div>
    </div></body></html>";
}

function sendStatusUpdateEmail($enrollment, $course, $status, $account_details = null) {
    $name  = $enrollment['student_name'];
    $email = $enrollment['student_email'];
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://graphicafix.com';
    $templates = [
        'active'    => ['<i class="fas fa-tada"></i>', 'Enrollment Confirmed!',  "Your enrollment in <strong>{$course['title']}</strong> is confirmed and your access is now active.", 'Start Learning', "$siteUrl/student_courses.php"],
        'reviewing' => ['<i class="fas fa-search"></i>', 'Under Review',            "We&rsquo;re currently reviewing your enrollment for <strong>{$course['title']}</strong>. We&rsquo;ll update you soon.", 'View Courses', "$siteUrl/courses.php"],
        'rejected'  => ['<i class="fas fa-times-circle"></i>', 'Not Approved',            "Your enrollment for <strong>{$course['title']}</strong> was not approved. Please contact us for more information.", 'Contact Us', "$siteUrl/contact.php"],
        'completed' => ['🏆', 'Course Completed!',       "Congratulations on completing <strong>{$course['title']}</strong>! Your certificate is ready.", 'Get Certificate', "$siteUrl/student_certificates.php"],
        'cancelled' => ['<i class="fas fa-ban"></i>', 'Enrollment Cancelled',   "Your enrollment in <strong>{$course['title']}</strong> has been cancelled. Please contact us if you have any questions.", 'Contact Us', "$siteUrl/contact.php"],
    ];
    if (!isset($templates[$status])) return;
    [$icon, $heading, $msg, $btnText, $btnUrl] = $templates[$status];
    $body = "<p style='font-size:15px;color:#333;margin:0 0 14px;'>Hi <strong>$name</strong>,</p>
             <p style='font-size:14px;color:#555;line-height:1.7;'>$msg</p>";
    // Include account credentials if just approved
    if ($status === 'active' && $account_details) {
        $body .= "
        <div style='background:#f0f9f0;border:1px solid #c3e6cb;padding:20px;border-radius:10px;margin-top:22px;'>
            <h4 style='margin:0 0 10px;color:#2b7a2b;font-size:15px;'><i class='fas fa-lock'></i> Your Student Account</h4>
            <p style='margin:0 0 6px;font-size:14px;color:#444;'>Login to access your courses, live classes and certificates.</p>
            <p style='margin:0 0 5px;font-size:14px;'><strong>Login URL:</strong> <a href='{$account_details['login_url']}' style='color:#0284c7;'>{$account_details['login_url']}</a></p>
            <p style='margin:0 0 5px;font-size:14px;'><strong>Email:</strong> {$account_details['email']}</p>
            <p style='margin:0;font-size:14px;'><strong>Temporary Password:</strong> <span style='font-family:monospace;background:#fff;padding:3px 8px;border:1px solid #ddd;border-radius:4px;'>{$account_details['password']}</span></p>
        </div>";
    }
    $html    = buildEmailWrapper($icon, $heading, $body, $btnText, $btnUrl);
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: Graphicafix Courses <noreply@graphicafix.com>\r\nReply-To: info@graphicafix.com\r\n";
    mail($email, "$icon $heading — {$course['title']}", $html, $headers);
}

// ══════════════════════════════════════════════════════════════════════════════
//  HANDLE POST ACTIONS
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Directly assign course to student ─────────────────────────────────────
    if (isset($_POST['direct_assign_course'])) {
        $student_id = intval($_POST['student_user_id'] ?? 0);
        $course_id  = intval($_POST['assign_course_id'] ?? 0);
        
        $stu_res = $conn->query("SELECT name, email, phone FROM users WHERE user_id = $student_id");
        $stu = $stu_res ? $stu_res->fetch_assoc() : null;
        
        if ($stu && $course_id) {
            $name  = $conn->real_escape_string($stu['name']);
            $email = $conn->real_escape_string($stu['email']);
            $phone = $conn->real_escape_string($stu['phone'] ?? '');
            
            $chk = $conn->query("SELECT id FROM course_enrollments WHERE course_id = $course_id AND LOWER(TRIM(student_email)) = LOWER(TRIM('$email'))");
            if ($chk && $chk->num_rows > 0) {
                $conn->query("UPDATE course_enrollments SET status = 'active' WHERE course_id = $course_id AND LOWER(TRIM(student_email)) = LOWER(TRIM('$email'))");
            } else {
                $conn->query("INSERT INTO course_enrollments (course_id, student_name, student_email, student_phone, amount, status, enrolled_at) VALUES ($course_id, '$name', '$email', '$phone', 0, 'active', NOW())");
            }
            
            $c_res = $conn->query("SELECT title, slug FROM courses WHERE id = $course_id");
            $crs = $c_res ? $c_res->fetch_assoc() : null;
            if ($crs) {
                $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://graphicafix.com';
                $en_mock = ['student_name' => $stu['name'], 'student_email' => $stu['email']];
                if (function_exists('sendStatusUpdateEmail')) {
                    sendStatusUpdateEmail($en_mock, ['title' => $crs['title'], 'slug' => $crs['slug'] ?? ''], 'active', ['email' => $stu['email'], 'password' => '(Existing Password)', 'login_url' => "$siteUrl/portal/login.php"]);
                }
            }
        }
        header("Location: manage_courses.php?tab=registrations&assigned_direct=1"); exit;
    }

    // ── Generate referral code ────────────────────────────────────────────────
    if (isset($_POST['generate_referral'])) {
        $code = 'GFX-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        $s = $conn->prepare("INSERT INTO referral_codes (code) VALUES (?)");
        $s->bind_param("s", $code); $s->execute();
        header("Location: manage_courses.php?tab=referrals&generated=1"); exit;
    }

    // ── Update enrollment status ──────────────────────────────────────────────
    if (isset($_POST['update_enrollment'])) {
        $eid     = intval($_POST['enrollment_id'] ?? 0);
        $status  = trim($_POST['status'] ?? '');
        $back    = trim($_POST['current_tab']     ?? 'all');
        $allowed = ['pending','reviewing','active','completed','rejected','cancelled'];
        if ($eid && in_array($status, $allowed)) {
            $u = $conn->prepare("UPDATE course_enrollments SET status=? WHERE id=?");
            $u->bind_param("si", $status, $eid); $u->execute();
            // Fetch full enrollment+course for email
            $es = $conn->prepare("SELECT e.*, c.title, c.slug FROM course_enrollments e JOIN courses c ON c.id=e.course_id WHERE e.id=?");
            $es->bind_param("i", $eid); $es->execute();
            $en = $es->get_result()->fetch_assoc();
            $account_details = null;
            // Auto-create student account on first approval
            if ($status === 'active' && $en) {
                $em = strtolower(trim($en['student_email']));
                $chk = $conn->prepare("SELECT user_id FROM users WHERE LOWER(TRIM(email))=?");
                $chk->bind_param("s", $em); $chk->execute();
                if ($chk->get_result()->num_rows === 0) {
                    $raw_pw  = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#'), 0, 9);
                    $hash    = password_hash($raw_pw, PASSWORD_DEFAULT);
                    $uname   = preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $em)[0]) . rand(100, 999);
                    $nm      = $en['student_name'];
                    $ins     = $conn->prepare("INSERT INTO users (name,email,username,password,role) VALUES (?,?,?,?,'student')");
                    $ins->bind_param("ssss", $nm, $em, $uname, $hash); $ins->execute();
                    if ($ins->affected_rows > 0) {
                        $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://graphicafix.com';
                        $account_details = ['email'=>$en['student_email'],'password'=>$raw_pw,'login_url'=>"$siteUrl/login.php"];
                    }
                }
            }
            if ($en) sendStatusUpdateEmail($en, ['title'=>$en['title'],'slug'=>$en['slug']??''], $status, $account_details);
        }
        header("Location: manage_courses.php?tab=registrations&esub=" . rawurlencode($back) . "&updated=1");
        exit;
    }

    // ── Delete enrollment ─────────────────────────────────────────────────────
    if (isset($_POST['delete_enrollment'])) {
        $eid  = intval($_POST['enrollment_id'] ?? 0);
        $back = trim($_POST['current_tab'] ?? 'all');
        if ($eid) { $s=$conn->prepare("DELETE FROM course_enrollments WHERE id=?"); $s->bind_param("i",$eid); $s->execute(); }
        header("Location: manage_courses.php?tab=registrations&esub=" . rawurlencode($back) . "&deleted=1");
        exit;
    }

    // ── Toggle publish ────────────────────────────────────────────────────────
    if (isset($_POST['toggle_publish'])) {
        $cid = intval($_POST['course_id'] ?? 0); $cur = intval($_POST['current_pub'] ?? 0);
        if ($cid) $conn->query("UPDATE courses SET is_published=" . ($cur ? 0 : 1) . " WHERE id=$cid");
        header("Location: manage_courses.php?tab=courses&updated=1"); exit;
    }

    // ── Delete course ─────────────────────────────────────────────────────────
    if (isset($_POST['delete_course'])) {
        $cid = intval($_POST['course_id'] ?? 0);
        if ($cid) { $s=$conn->prepare("DELETE FROM courses WHERE id=?"); $s->bind_param("i",$cid); $s->execute(); }
        header("Location: manage_courses.php?tab=courses&deleted=1"); exit;
    }

    // ── Add course ────────────────────────────────────────────────────────────
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'add_course') {
        $title     = trim($_POST['c_title']        ?? '');
        $tagline   = trim($_POST['c_tagline']      ?? '');
        $desc      = trim($_POST['c_description']  ?? '');
        $cat       = trim($_POST['c_category']     ?? '');
        $level     = trim($_POST['c_level']        ?? 'Beginner');
        $price     = floatval($_POST['c_price']    ?? 0);
        $thumb     = trim($_POST['c_thumbnail']    ?? '');
        $discount  = intval($_POST['c_discount']   ?? 0);
        $student_discount = intval($_POST['c_student_discount'] ?? 0);
        $disc_end  = !empty($_POST['c_discount_end'])  ? $_POST['c_discount_end']  : null;
        $inst_en   = isset($_POST['c_installments_enabled']) ? 1 : 0;
        $inst_cnt  = max(1, intval($_POST['c_installment_count'] ?? 1));
        $is_free   = isset($_POST['c_is_free'])      ? 1 : 0;
        $is_feat   = isset($_POST['c_is_featured'])  ? 1 : 0;
        $dur       = intval($_POST['c_duration']   ?? 0);
        $start_d   = !empty($_POST['c_start_date']) ? $_POST['c_start_date'] : null;
        $inst_id   = intval($_POST['c_instructor_id'] ?? 0); if (!$inst_id) $inst_id = null;
        
        if ($is_free) { $price=0; $discount=0; $inst_en=0; }
        
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', trim($title, '-')));
        $slug_safe = $conn->real_escape_string($slug);
        if ($conn->query("SELECT id FROM courses WHERE slug='$slug_safe'")->num_rows) $slug .= '-' . time();
        
        $s = $conn->prepare("INSERT INTO courses (title,slug,thumbnail,tagline,description,category,level,price,discount_percent,student_discount,discount_end_date,installments_enabled,installment_count,is_free,is_featured,is_published,duration_mins,start_date,instructor_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?)");
        
        // 18 parameters matched
        $s->bind_param("sssssssdiisiiiiisi", $title, $slug, $thumb, $tagline, $desc, $cat, $level, $price, $discount, $student_discount, $disc_end, $inst_en, $inst_cnt, $is_free, $is_feat, $dur, $start_d, $inst_id);
        $s->execute();
        
        header("Location: manage_courses.php?tab=courses&course_added=1"); exit;
    }

    // ── Edit course ───────────────────────────────────────────────────────────
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'edit_course') {
        $cid      = intval($_POST['course_id']    ?? 0);
        $title    = trim($_POST['c_title']        ?? '');
        $tagline  = trim($_POST['c_tagline']      ?? '');
        $desc     = trim($_POST['c_description']  ?? '');
        $cat      = trim($_POST['c_category']     ?? '');
        $level    = trim($_POST['c_level']        ?? 'Beginner');
        $price    = floatval($_POST['c_price']    ?? 0);
        $thumb    = trim($_POST['c_thumbnail']    ?? '');
        $discount = intval($_POST['c_discount']   ?? 0);
        $student_discount = intval($_POST['c_student_discount'] ?? 0);
        $disc_end = !empty($_POST['c_discount_end'])  ? $_POST['c_discount_end']  : null;
        $inst_en  = isset($_POST['c_installments_enabled']) ? 1 : 0;
        $inst_cnt = max(1, intval($_POST['c_installment_count'] ?? 1));
        $is_free  = isset($_POST['c_is_free'])      ? 1 : 0;
        $is_feat  = isset($_POST['c_is_featured'])  ? 1 : 0;
        $is_pub   = isset($_POST['c_is_published']) ? 1 : 0;
        $dur      = intval($_POST['c_duration']   ?? 0);
        $start_d  = !empty($_POST['c_start_date']) ? $_POST['c_start_date'] : null;
        $inst_id  = intval($_POST['c_instructor_id'] ?? 0); if (!$inst_id) $inst_id = null;
        
        if ($is_free) { $price=0; $discount=0; $inst_en=0; }
        
        if ($cid) {
            $s = $conn->prepare("UPDATE courses SET title=?,thumbnail=?,tagline=?,description=?,category=?,level=?,price=?,discount_percent=?,student_discount=?,discount_end_date=?,installments_enabled=?,installment_count=?,is_free=?,is_featured=?,is_published=?,duration_mins=?,start_date=?,instructor_id=? WHERE id=?");
            
            // 19 parameters matched
            $s->bind_param("ssssssdiisiiiiiisii", $title, $thumb, $tagline, $desc, $cat, $level, $price, $discount, $student_discount, $disc_end, $inst_en, $inst_cnt, $is_free, $is_feat, $is_pub, $dur, $start_d, $inst_id, $cid);
            $s->execute();
        }
        header("Location: manage_courses.php?tab=courses&updated=1"); exit;
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  STATE
// ══════════════════════════════════════════════════════════════════════════════
$tab  = $_GET['tab']  ?? 'registrations';
if (!in_array($tab, ['registrations','courses','referrals'])) $tab = 'registrations';
$esub = $_GET['esub'] ?? 'all';
$esub_allowed = ['all','pending','reviewing','active','completed','rejected','cancelled'];
if (!in_array($esub, $esub_allowed)) $esub = 'all';

// ══════════════════════════════════════════════════════════════════════════════
//  COUNTS — always fresh, independent of tab
// ══════════════════════════════════════════════════════════════════════════════
$ecnt = ['all' => 0];
foreach (['pending','reviewing','active','completed','rejected','cancelled'] as $s) {
    $row       = $conn->query("SELECT COUNT(*) AS c FROM course_enrollments WHERE LOWER(TRIM(status))='$s'")->fetch_assoc();
    $ecnt[$s]  = (int)($row['c'] ?? 0);
}
$ecnt['all'] = array_sum($ecnt) - $ecnt['all']; // exclude 'all' key itself
$ecnt['all'] = array_sum(array_filter($ecnt, fn($k)=>$k!=='all', ARRAY_FILTER_USE_KEY));

$total_courses    = (int)($conn->query("SELECT COUNT(*) AS c FROM courses")->fetch_assoc()['c'] ?? 0);
$published_courses= (int)($conn->query("SELECT COUNT(*) AS c FROM courses WHERE is_published=1")->fetch_assoc()['c'] ?? 0);

// ── Pre-fetch emails that have successfully used a referral code FIRST
$ref_emails = [];
$refs_q = $conn->query("SELECT LOWER(TRIM(used_by_email)) as em FROM referral_codes WHERE is_used=1 AND used_by_email IS NOT NULL AND used_by_email != ''");
if ($refs_q) {
    while ($r = $refs_q->fetch_assoc()) {
        $ref_emails[] = $conn->real_escape_string($r['em']);
    }
}

// Build a safe exclusion string for the SQL query using PHP
$email_exclusion_sql = "";
if (count($ref_emails) > 0) {
    $email_list = "'" . implode("','", $ref_emails) . "'";
    $email_exclusion_sql = "AND LOWER(TRIM(student_email)) NOT IN ($email_list)";
}

// REVENUE LOGIC FIX: Safely calculates revenue without triggering Collation errors
$total_revenue = (float)($conn->query("
    SELECT COALESCE(SUM(amount_paid),0) AS r 
    FROM course_enrollments 
    WHERE LOWER(TRIM(status)) IN ('active','completed') 
    AND (discount_code IS NULL OR UPPER(TRIM(discount_code)) NOT LIKE 'GFX-%')
    $email_exclusion_sql
")->fetch_assoc()['r'] ?? 0);


$total_referrals  = (int)($conn->query("SELECT COUNT(*) AS c FROM referral_codes")->fetch_assoc()['c'] ?? 0);
// Recalculate ecnt['all'] cleanly
$ecnt['all'] = 0; foreach(['pending','reviewing','active','completed','rejected','cancelled'] as $s) $ecnt['all'] += $ecnt[$s];

// ══════════════════════════════════════════════════════════════════════════════
//  FETCH ENROLLMENTS — GROUPED BY COURSE
// ══════════════════════════════════════════════════════════════════════════════
$base_sql = "SELECT e.*,
                    c.title     AS course_title,
                    c.price     AS course_price,
                    c.is_free   AS course_is_free
             FROM course_enrollments e
             JOIN courses c ON c.id = e.course_id";
if ($esub === 'all') {
    $enrollments = $conn->query($base_sql . " ORDER BY c.title ASC, e.enrolled_at DESC");
} else {
    $esub_safe = strtolower(trim($esub));
    $es = $conn->prepare($base_sql . " WHERE LOWER(TRIM(e.status))=? ORDER BY c.title ASC, e.enrolled_at DESC");
    $es->bind_param("s", $esub_safe); $es->execute();
    $enrollments = $es->get_result();
}
$enroll_count = $enrollments ? (int)$enrollments->num_rows : 0;

// Grouping enrollments by course title
$enrollments_grouped = [];
if ($enroll_count > 0) {
    while ($e = $enrollments->fetch_assoc()) {
        $c_name = $e['course_title'] ?: 'Unknown Course';
        if (!isset($enrollments_grouped[$c_name])) {
            $enrollments_grouped[$c_name] = [];
        }
        $enrollments_grouped[$c_name][] = $e;
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  FETCH COURSES
// ══════════════════════════════════════════════════════════════════════════════
$courses_result = $conn->query("
    SELECT c.*,
           u.name AS instructor_name,
           (SELECT COUNT(*) FROM course_enrollments
            WHERE course_id=c.id AND LOWER(TRIM(status)) IN ('active','completed')) AS enroll_count
    FROM courses c
    LEFT JOIN users u ON u.user_id = c.instructor_id
    ORDER BY c.is_published DESC, c.created_at DESC
");
$courses_js = []; // keyed by string(id) — critical for JS modal lookup
if ($courses_result) {
    while ($c = $courses_result->fetch_assoc()) {
        $courses_js[(string)$c['id']] = $c; // string key ensures JSON object not array
    }
    $courses_result->data_seek(0);
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function timeAgo($d) {
    $s=time()-strtotime($d);
    if($s<60)   return $s."s ago";
    if($s<3600) return round($s/60)."m ago";
    if($s<86400)return round($s/3600)."h ago";
    if($s<604800)return round($s/86400)."d ago";
    return date("M j, Y",strtotime($d));
}
function eBadge($s) {
    return match(strtolower(trim($s??''))) {
        'pending'   => ['Pending',   '#fef9c3','#a16207'],
        'reviewing' => ['Reviewing', '#e0f2fe','#0284c7'],
        'active'    => ['Active',    '#d7f8b8','#2b7a2b'],
        'completed' => ['Completed', '#dbeaff','#1b4ed8'],
        'rejected'  => ['Rejected',  '#ffe5e5','#d63031'],
        'cancelled' => ['Cancelled', '#f0f0f0','#666'],
        default     => [ucfirst($s??''),'#f0f0f0','#555'],
    };
}
function aColor($n) {
    $c=['pink','blue','green','orange','purple'];
    return $c[ord(strtoupper($n[0]??'A'))%5];
}
// ──────────────────────────────────────────────────────────────────────────────
//  PAYMENT RESOLVER - Upgraded to check email against referral_codes table
// ──────────────────────────────────────────────────────────────────────────────
function resolvePayment($e) {
    global $ref_emails;
    $course_is_free = !empty($e['course_is_free']);
    $amount_paid    = floatval($e['amount_paid'] ?? 0);
    $has_proof      = !empty($e['payment_proof']);
    
    $has_code          = !empty(trim($e['discount_code'] ?? ''));
    $is_referral_code  = $has_code && strpos(strtoupper(trim($e['discount_code'])), 'GFX-') === 0;
    $is_referral_email = in_array(strtolower(trim($e['student_email'])), $ref_emails);
    
    $is_referral    = $is_referral_code || $is_referral_email;
    $course_price   = floatval($e['course_price'] ?? 0);

    if ($course_is_free)               return ['type'=>'free',     'label'=>'Free',             'color'=>'#10b981'];
    if ($is_referral)                  return ['type'=>'referral', 'label'=>'Referral',         'color'=>'#10b981'];
    if ($amount_paid > 0)              return ['type'=>'paid',     'label'=>'Rs.'.number_format($amount_paid), 'color'=>'var(--primary)'];
    if ($has_proof  && $amount_paid<=0) return ['type'=>'paid',    'label'=>'Rs.'.number_format($course_price).' (unconfirmed)', 'color'=>'#e17055'];
    if ($has_code   && $course_price>0) return ['type'=>'discount','label'=>'Discount Code',   'color'=>'#7c3aed'];
    if ($course_price <= 0)            return ['type'=>'free',     'label'=>'Free',             'color'=>'#10b981'];
    return ['type'=>'unpaid', 'label'=>'Not Paid', 'color'=>'#d63031'];
}
?>

<div class="height-100">

<div class="stats-row" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-book"></i></div>
        <div class="stat-number"><?= $total_courses ?></div>
        <div class="stat-label">Total Courses</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="color:#2b7a2b;"><i class="fas fa-globe"></i></div>
        <div class="stat-number" style="color:#2b7a2b;"><?= $published_courses ?></div>
        <div class="stat-label">Published</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="stat-number"><?= $ecnt['all'] ?></div>
        <div class="stat-label">Registrations</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="color:#0284c7;">⏳</div>
        <div class="stat-number" style="color:#0284c7;"><?= $ecnt['pending'] ?></div>
        <div class="stat-label">Pending</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="color:#7c3aed;"><i class="fas fa-dollar-sign"></i></div>
        <div class="stat-number" style="color:#7c3aed;font-size:1.1rem;">Rs.<?= number_format($total_revenue) ?></div>
        <div class="stat-label">Revenue</div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     NEW: ADVANCED INLINE FILTERS ROW
     ══════════════════════════════════════════════════════════════════════════════ -->
<?php if ($tab === 'registrations'): ?>
<div style="background: #fff; padding: 16px 24px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); border: 1px solid #f0f3f2; margin-bottom: 24px; display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
    <!-- Live Search Input -->
    <div style="flex: 1; min-width: 260px; position: relative;">
        <input type="text" id="regSearchInput" placeholder="Search student name or email..." 
               style="width: 100%; padding: 10px 14px 10px 36px; border: 1.5px solid #e2e8f0; border-radius: 8px; outline: none; font-size: 13.5px; font-family: inherit;">
        <i class="fas fa-search" style="position: absolute; left: 14px; top: 13px; color: #a0aec0;"></i>
    </div>
    
    <!-- Course Dropdown Filter -->
    <div style="min-width: 220px;">
        <select id="courseFilterDropdown" style="width: 100%; padding: 10px 14px; border: 1.5px solid #e2e8f0; border-radius: 8px; outline: none; font-size: 13.5px; font-family: inherit; background-color: #fff; cursor: pointer;">
            <option value="all">All Courses</option>
            <?php foreach (array_keys($enrollments_grouped) as $c_title): ?>
                <option value="<?= htmlspecialchars($c_title) ?>"><?= htmlspecialchars($c_title) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Amount / Payment Type Dropdown Filter -->
    <div style="min-width: 180px;">
        <select id="amountFilterDropdown" style="width: 100%; padding: 10px 14px; border: 1.5px solid #e2e8f0; border-radius: 8px; outline: none; font-size: 13.5px; font-family: inherit; background-color: #fff; cursor: pointer;">
            <option value="all">All Amount Types</option>
            <option value="free">Free</option>
            <option value="paid">Paid (Rs.)</option>
            <option value="referral">Referral</option>
            <option value="unpaid">Unpaid</option>
        </select>
    </div>
</div>
<?php endif; ?>

<div class="main-card">

    <div style="display:flex;border-bottom:2px solid #f0f0f0;padding:0 24px;overflow-x:auto;">
        <?php
        $main_tabs = [
            'registrations' => ['<i class="fas fa-graduation-cap"></i>', 'Registrations', $ecnt['all']],
            'courses'       => ['<i class="fas fa-book"></i>', 'Courses',        $total_courses],
            'referrals'     => ['<i class="fas fa-ticket-alt"></i>️', 'Referrals',      $total_referrals],
        ];
        foreach ($main_tabs as $tk => $td): $a = ($tab === $tk); ?>
        <a href="manage_courses.php?tab=<?= $tk ?>&esub=<?= $esub ?>"
           style="display:inline-flex;align-items:center;gap:7px;padding:14px 20px;font-size:13.5px;font-weight:700;text-decoration:none;white-space:nowrap;border-bottom:2px solid <?= $a?'var(--primary)':'transparent' ?>;color:<?= $a?'var(--primary)':'#888' ?>;margin-bottom:-2px;transition:color .2s;">
            <?= $td[0] ?> <?= $td[1] ?>
            <span style="background:<?= $a?'var(--primary)':'#f0f0f0' ?>;color:<?= $a?'#fff':'#888' ?>;padding:1px 8px;border-radius:20px;font-size:11px;font-weight:800;"><?= $td[2] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($tab === 'registrations'): ?>
    <div class="main-header" style="margin-top:4px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <h3 style="margin:0;"><i class="fas fa-graduation-cap"></i> Course Registrations</h3>
            <span style="font-size:13px;color:#888;"><?= $enroll_count ?> result<?= $enroll_count!=1?'s':'' ?></span>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="openDirectAssignModal()" style="background:#024442; border:none; padding:10px 18px; border-radius:8px; font-size:13px; font-weight:700; color:white;">
                <i class="fas fa-user-plus me-1"></i> Directly Assign Course
            </button>
        </div>
    </div>

    <div style="display:flex;padding:0 24px;border-bottom:1px solid #f0f0f0;overflow-x:auto;scrollbar-width:none;">
        <?php
        $sub_tabs = [
            'all'       => ['All',       '<i class="fas fa-clipboard"></i>'],
            'pending'   => ['Pending',   '⏳'],
            'reviewing' => ['Reviewing', '<i class="fas fa-search"></i>'],
            'active'    => ['Active',    '<i class="fas fa-check-circle"></i>'],
            'completed' => ['Completed', '🏆'],
            'rejected'  => ['Rejected',  '<i class="fas fa-times-circle"></i>'],
            'cancelled' => ['Cancelled', '<i class="fas fa-ban"></i>'],
        ];
        foreach ($sub_tabs as $k => $e): $a = ($esub === $k); ?>
        <a href="manage_courses.php?tab=registrations&esub=<?= $k ?>"
           style="display:inline-flex;align-items:center;gap:5px;padding:10px 14px;font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap;color:<?= $a?'var(--primary)':'#999' ?>;border-bottom:2px solid <?= $a?'var(--primary)':'transparent' ?>;margin-bottom:-1px;">
            <?= $e[1] ?> <?= $e[0] ?>
            <span style="background:<?= $a?'var(--primary)':'#f0f0f0' ?>;color:<?= $a?'#fff':'#aaa' ?>;padding:1px 6px;border-radius:20px;font-size:10px;font-weight:800;"><?= (int)($ecnt[$k] ?? 0) ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <div style="padding: 10px 24px 24px;">
        <?php if (!empty($enrollments_grouped)): ?>
            <?php foreach ($enrollments_grouped as $c_name => $students): ?>
                <div class="course-table-section" data-course-title="<?= htmlspecialchars($c_name) ?>" style="margin-bottom: 30px; margin-top: 15px;">
                    <h4 class="course-section-title" style="margin: 0 0 12px 0; color: #333; font-size: 15px; font-weight: 700; border-bottom: 2px solid #f0f0f0; padding-bottom: 8px; display:flex; align-items:center;">
                        <i class="fas fa-book"></i> <?= htmlspecialchars($c_name) ?> <span style="color:#aaa; font-size:12px; margin-left:6px;">(<?= count($students) ?> students)</span>
                    </h4>
                    
                    <div class="main-table" style="border: 1px solid #f0f0f0; border-radius: 10px; padding: 0;">
                        <div class="table-head" style="grid-template-columns:3fr 1fr 1.5fr 1fr 1fr 1fr; background: #f9fbfc; border-radius: 10px 10px 0 0; padding: 12px 20px;">
                            <span>Student</span>
                            <span>Amount</span>
                            <span>Payment</span>
                            <span>Status</span>
                            <span>Date</span>
                            <span>Actions</span>
                        </div>

                        <?php foreach ($students as $e): 
                            $badge   = eBadge($e['status']);
                            $pay     = resolvePayment($e);
                            $has_pay = !empty($e['payment_proof']);
                            $pay_url = $has_pay
                                ? (str_starts_with($e['payment_proof'],'http') ? $e['payment_proof'] : '../'.ltrim($e['payment_proof'],'/'))
                                : '';
                        ?>
                        <div class="table-row student-data-row" data-pay-type="<?= htmlspecialchars($pay['type']) ?>" style="grid-template-columns:3fr 1fr 1.5fr 1fr 1fr 1fr; padding: 12px 20px; border-bottom: 1px solid #f5f5f5;">
                            
                            <div class="client">
                                <div class="avatar <?= aColor($e['student_name']) ?>"><?= strtoupper($e['student_name'][0]) ?></div>
                                <div>
                                    <strong><?= htmlspecialchars($e['student_name']) ?></strong>
                                    <small><?= htmlspecialchars($e['student_email']) ?></small>
                                </div>
                            </div>

                            <span data-label="Amount">
                                <?php if ($pay['type'] === 'free'): ?>
                                    <span style="background:#d7f8b8;color:#2b7a2b;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;">FREE</span>
                                <?php elseif ($pay['type'] === 'referral'): ?>
                                    <span style="background:#d7f8b8;color:#2b7a2b;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;">REFERRAL</span>
                                <?php elseif ($pay['type'] === 'unpaid'): ?>
                                    <span style="background:#ffe5e5;color:#d63031;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;">UNPAID</span>
                                <?php else: ?>
                                    <span style="font-weight:700;color:<?= $pay['color'] ?>;"><?= $pay['label'] ?></span>
                                <?php endif; ?>
                            </span>

                            <span data-label="Payment">
                                <?php if ($has_pay): ?>
                                <a href="<?= htmlspecialchars($pay_url) ?>" target="_blank"
                                   style="display:inline-flex;align-items:center;gap:4px;background:#d7f8b8;color:#2b7a2b;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;text-decoration:none;">
                                    <i class="fas fa-paperclip"></i> View
                                </a>
                                <?php elseif (in_array($pay['type'],['free','referral'])): ?>
                                <span style="font-size:11px;color:#aaa;">—</span>
                                <?php else: ?>
                                <span style="background:#ffe5e5;color:#d63031;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;"><i class="fas fa-exclamation-triangle"></i>️ Missing</span>
                                <?php endif; ?>
                            </span>

                            <span>
                                <span style="background:<?= $badge[1] ?>;color:<?= $badge[2] ?>;padding:4px 11px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;">
                                    <?= $badge[0] ?>
                                </span>
                            </span>

                            <span data-label="Date" style="font-size:12px;color:#888;"><?= timeAgo($e['enrolled_at']) ?></span>

                            <div style="display:flex;align-items:center;gap:3px;flex-wrap:wrap;">
                                <button class="dots view-enrollment" title="View"
                                    data-id="<?= $e['id'] ?>"
                                    data-name="<?= htmlspecialchars($e['student_name']) ?>"
                                    data-email="<?= htmlspecialchars($e['student_email']) ?>"
                                    data-phone="<?= htmlspecialchars($e['student_phone']??'') ?>"
                                    data-occ="<?= htmlspecialchars($e['student_occupation']??'') ?>"
                                    data-qual="<?= htmlspecialchars($e['student_qualification']??'') ?>"
                                    data-exp="<?= htmlspecialchars($e['student_experience']??'') ?>"
                                    data-motive="<?= htmlspecialchars($e['student_motivation']??'') ?>"
                                    data-course="<?= htmlspecialchars($e['course_title']) ?>"
                                    data-paid="<?= $e['amount_paid'] ?>"
                                    data-courseprice="<?= $e['course_price'] ?>"
                                    data-coursefree="<?= $e['course_is_free'] ?>"
                                    data-code="<?= htmlspecialchars($e['discount_code']??'') ?>"
                                    data-payment="<?= htmlspecialchars($e['payment_proof']??'') ?>"
                                    data-status="<?= htmlspecialchars(strtolower(trim($e['status']??'pending'))) ?>"
                                    data-date="<?= date('M j, Y g:i A',strtotime($e['enrolled_at'])) ?>"
                                    data-esub="<?= htmlspecialchars($esub) ?>"
                                    data-paytype="<?= $pay['type'] ?>"
                                    data-father="<?= htmlspecialchars($e['father_name']??'') ?>"
                                    data-address="<?= htmlspecialchars($e['address']??'') ?>"
                                    data-dob="<?= htmlspecialchars($e['dob']??'') ?>"
                                    data-gender="<?= htmlspecialchars($e['gender']??'') ?>"
                                    data-picture="<?= htmlspecialchars($e['student_picture']??'') ?>"
                                    data-education="<?= htmlspecialchars($e['education_level']??'') ?>"
                                    data-studentcard="<?= htmlspecialchars($e['student_card']??'') ?>"><i class="fas fa-eye"></i>️</button>

                                <a class="dots" href="mailto:<?= htmlspecialchars($e['student_email']) ?>" title="Email student" style="text-decoration:none;"><i class="fas fa-envelope"></i>️</a>

                                <?php if (in_array(strtolower(trim($e['status']??'')),['pending','reviewing'])): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="update_enrollment" value="1">
                                    <input type="hidden" name="enrollment_id"     value="<?= $e['id'] ?>">
                                    <input type="hidden" name="status"             value="active">
                                    <input type="hidden" name="current_tab"        value="<?= htmlspecialchars($esub) ?>">
                                    <button type="submit" class="dots" title="Approve" style="color:#2b7a2b;"><i class="fas fa-check-circle"></i></button>
                                </form>
                                <?php endif; ?>

                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this registration permanently?');">
                                    <input type="hidden" name="delete_enrollment" value="1">
                                    <input type="hidden" name="enrollment_id"     value="<?= $e['id'] ?>">
                                    <input type="hidden" name="current_tab"        value="<?= htmlspecialchars($esub) ?>">
                                    <button type="submit" class="dots" title="Delete" style="color:#ef4444;"><i class="fas fa-trash"></i>️</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align:center;padding:60px 24px;color:#888;">
                <div style="font-size:2.5rem;margin-bottom:8px;"><i class="fas fa-envelope-open"></i></div>
                No registrations<?= $esub!=='all' ? ' with status <strong>'.htmlspecialchars($esub).'</strong>' : '' ?> yet.
            </div>
        <?php endif; ?>
    </div>

    <?php elseif ($tab === 'referrals'): ?>
    <div class="main-header" style="margin-top:4px;">
        <h3><i class="fas fa-ticket-alt"></i>️ Referral Codes</h3>
        <form method="POST">
            <input type="hidden" name="generate_referral" value="1">
            <button type="submit" class="add-client">+ Generate Code</button>
        </form>
    </div>
    <div class="main-table">
        <div class="table-head" style="grid-template-columns:1fr 1fr 1fr 2fr;">
            <span>Code</span><span>Status</span><span>Created</span><span>Used By</span>
        </div>
        <?php
        $refs = $conn->query("SELECT * FROM referral_codes ORDER BY created_at DESC");
        if ($refs && $refs->num_rows > 0): while ($r = $refs->fetch_assoc()): ?>
        <div class="table-row" style="grid-template-columns:1fr 1fr 1fr 2fr;">
            <span data-label="Code" style="font-family:monospace;font-weight:800;font-size:14px;color:var(--primary);letter-spacing:1px;"><?= htmlspecialchars($r['code']) ?></span>
            <span data-label="Status">
                <span style="background:<?= $r['is_used']?'#ffe5e5':'#d7f8b8' ?>;color:<?= $r['is_used']?'#d63031':'#2b7a2b' ?>;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;">
                    <?= $r['is_used'] ? 'Used' : 'Unused' ?>
                </span>
            </span>
            <span data-label="Created" style="font-size:13px;color:#888;"><?= date('M j, Y',strtotime($r['created_at'])) ?></span>
            <span data-label="Used By" style="font-size:13px;color:#555;"><?= $r['used_by_email'] ? htmlspecialchars($r['used_by_email']) : '—' ?></span>
        </div>
        <?php endwhile; else: ?>
        <div style="text-align:center;padding:60px 24px;color:#888;">
            <div style="font-size:2.5rem;margin-bottom:8px;"><i class="fas fa-ticket-alt"></i>️</div>
            No referral codes generated yet.
        </div>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <div class="main-header" style="margin-top:4px;">
        <h3><i class="fas fa-book"></i> Manage Courses</h3>
        <button class="add-client" onclick="openCourseModal('add')">+ Add Course</button>
    </div>
    <div class="main-table">
        <div class="table-head" style="grid-template-columns:2.5fr 1.2fr 1fr .7fr 1fr 1fr 1fr;">
            <span>Course</span><span>Category</span><span>Price</span>
            <span>Level</span><span>Students</span><span>Status</span><span>Actions</span>
        </div>
        <?php if ($courses_result && $courses_result->num_rows > 0):
              while ($c = $courses_result->fetch_assoc()): ?>
        <div class="table-row" style="grid-template-columns:2.5fr 1.2fr 1fr .7fr 1fr 1fr 1fr;">
            <div class="client">
                <?php if (!empty($c['thumbnail'])): ?>
                <img src="<?= htmlspecialchars($c['thumbnail']) ?>" style="width:36px;height:36px;border-radius:8px;object-fit:cover;flex-shrink:0;" onerror="this.style.display='none'">
                <?php else: ?>
                <div class="avatar $c['is_featured']?'orange':'blue'" style="font-size:.85rem;"><?= $c['is_featured']?'<i class="fas fa-star"></i>':'<i class="fas fa-book"></i>' ?></div>
                <?php endif; ?>
                <div>
                    <strong style="font-size:13.5px;"><?= htmlspecialchars($c['title']) ?></strong>
                    <small style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        <?= htmlspecialchars($c['instructor_name'] ?? 'Unassigned') ?>
                        <?php if (!empty($c['discount_end_date']) && $c['discount_end_date'] > date('Y-m-d')): ?>
                        <span style="background:#fef9c3;color:#a16207;padding:1px 6px;border-radius:10px;font-size:10px;font-weight:700;">⏳ <?= date('M j',strtotime($c['start_date'])) ?></span>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            <span data-label="Category" style="font-size:13px;color:#666;"><?= htmlspecialchars($c['category']??'—') ?></span>
            <span style="font-weight:700;font-size:13.5px;color:<?= $c['is_free']?'#10b981':'var(--primary)' ?>;">
                <?= $c['is_free'] ? 'Free' : 'Rs.'.number_format($c['price']) ?>
                <?php if (!$c['is_free'] && !empty($c['discount_percent'])): ?>
                <small style="color:#ef4444;">-<?= $c['discount_percent'] ?>%</small>
                <?php endif; ?>
            </span>
            <span style="font-size:12.5px;color:#888;"><?= htmlspecialchars($c['level']) ?></span>
            <span style="font-size:13.5px;font-weight:600;"><?= number_format($c['enroll_count']) ?></span>
            <span><span class="status <?= $c['is_published']?'active':'inactive' ?>"><?= $c['is_published']?'Published':'Draft' ?></span></span>
            <div style="display:flex;gap:3px;">
                <button class="dots" title="Edit" onclick="openCourseModal('edit','<?= $c['id'] ?>')"><i class="fas fa-pencil-alt"></i>️</button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="toggle_publish" value="1">
                    <input type="hidden" name="course_id"      value="<?= $c['id'] ?>">
                    <input type="hidden" name="current_pub"    value="<?= $c['is_published'] ?>">
                    <button type="submit" class="dots" title="<?= $c['is_published']?'Unpublish':'Publish' ?>"><?= $c['is_published']?'<i class="fas fa-eye-slash"></i>':'<i class="fas fa-globe"></i>' ?></button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this course?')">
                    <input type="hidden" name="delete_course" value="1">
                    <input type="hidden" name="course_id"     value="<?= $c['id'] ?>">
                    <button type="submit" class="dots" style="color:#ef4444;" title="Delete"><i class="fas fa-trash"></i>️</button>
                </form>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div style="text-align:center;padding:60px 24px;color:#888;">
            <div style="font-size:2.5rem;margin-bottom:8px;"><i class="fas fa-book"></i></div>
            No courses yet. <a href="#" onclick="openCourseModal('add');return false;" style="color:var(--primary);font-weight:700;">Add your first →</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div></div><div id="enrollModal" class="modal-overlay" onclick="if(event.target===this)closeEnrollModal()">
    <div class="modal-content" style="max-width:680px;">
        <div class="modal-header">
            <h4><div class="modal-header-icon"><i class="fas fa-graduation-cap"></i></div><span>Registration Details</span></h4>
            <button type="button" class="modal-close" onclick="closeEnrollModal()">×</button>
        </div>
        <div class="modal-body">
            <div id="emPictureWrap" style="text-align:center;margin-bottom:18px;display:none;">
                <img id="emPictureImg" src="" style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid var(--primary);box-shadow:0 4px 12px rgba(0,0,0,.1);" onerror="this.parentElement.style.display='none'">
            </div>
            <div class="info-grid">
                <div class="info-row"><div class="info-icon"><i class="fas fa-user"></i></div><div class="info-content"><div class="info-label">Student</div><div class="info-value" id="emName"></div></div></div>
                <div class="info-row" id="emFatherRow" style="display:none;"><div class="info-icon"><i class="fas fa-user-tie"></i></div><div class="info-content"><div class="info-label">Father / Husband</div><div class="info-value" id="emFather"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-envelope"></i>️</div><div class="info-content"><div class="info-label">Email</div><div class="info-value" id="emEmail"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-phone"></i></div><div class="info-content"><div class="info-label">Phone</div><div class="info-value" id="emPhone"></div></div></div>
                <div class="info-row" id="emAddressRow" style="display:none;"><div class="info-icon"><i class="fas fa-map-marker-alt"></i></div><div class="info-content"><div class="info-label">Address</div><div class="info-value" id="emAddress"></div></div></div>
                <div class="info-row" id="emDobRow" style="display:none;"><div class="info-icon"><i class="fas fa-birthday-cake"></i></div><div class="info-content"><div class="info-label">Date of Birth</div><div class="info-value" id="emDob"></div></div></div>
                <div class="info-row" id="emGenderRow" style="display:none;"><div class="info-icon"><i class="fas fa-venus-mars"></i></div><div class="info-content"><div class="info-label">Gender</div><div class="info-value" id="emGender"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-briefcase"></i></div><div class="info-content"><div class="info-label">Occupation</div><div class="info-value" id="emOcc"></div></div></div>
                <div class="info-row" id="emEducationRow" style="display:none;"><div class="info-icon"><i class="fas fa-graduation-cap"></i></div><div class="info-content"><div class="info-label">Education Level</div><div class="info-value" id="emEducation"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-book"></i></div><div class="info-content"><div class="info-label">Course</div><div class="info-value" id="emCourse"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-dollar-sign"></i></div><div class="info-content"><div class="info-label">Amount</div><div class="info-value" id="emPaid"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-tag"></i>️</div><div class="info-content"><div class="info-label">Discount / Referral Code</div><div class="info-value" id="emCode"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-calendar-alt"></i></div><div class="info-content"><div class="info-label">Registered On</div><div class="info-value" id="emDate"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-dot-circle"></i></div><div class="info-content"><div class="info-label">Status</div><div class="info-value" id="emStatus"></div></div></div>
            </div>

            <div class="message-box" id="emMotivBox" style="margin-bottom:14px;display:none;">
                <span class="message-label"><i class="fas fa-comment"></i> Motivation</span>
                <div class="message-content" id="emMotiv"></div>
            </div>

            <div class="message-box" id="emStudentCardBox" style="margin-bottom:14px;display:none;">
                <span class="message-label"><i class="fas fa-id-card"></i> CNIC / Student Card</span>
                <div id="emStudentCardWrap"></div>
            </div>

            <div class="message-box" id="emPaymentBox">
                <span class="message-label"><i class="fas fa-paperclip"></i> Payment Screenshot</span>
                <div id="emPaymentWrap"></div>
            </div>

            <div style="margin-top:18px;padding:16px;background:#f9fbfc;border-radius:12px;">
                <div style="font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">
                    Update Status — sends email automatically
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;" id="emStatusBtns"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary-custom" onclick="closeEnrollModal()">Close</button>
            <a id="emReplyLink" href="#" class="btn btn-primary-custom"><i class="fas fa-envelope"></i>️ Email Student</a>
        </div>
    </div>
</div>

<div id="courseModal" class="modal-overlay" onclick="if(event.target===this)closeCourseModal()">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="cTitle">Add Course</h4>
            <button type="button" class="modal-close" onclick="closeCourseModal()">×</button>
        </div>
        <form method="POST" id="courseForm">
            
            <input type="hidden" name="action_type" id="cAction" value="add_course">
            <input type="hidden" name="course_id"   id="cId"   value="">
            
            <div class="modal-body">
                <div class="info-grid">
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Course Title <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="c_title" id="cFTitle" required placeholder="e.g. Complete Brand Design Masterclass"
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;">
                    </div>
                    
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Thumbnail Image URL</label>
                        <input type="url" name="c_thumbnail" id="cFThumb" placeholder="https://example.com/image.jpg"
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;">
                        
                        <div id="thumbPreview" style="display:none; margin-top:10px;">
                            <img id="thumbPreviewImg" src="" style="max-width:100%; height:auto; border-radius:8px; max-height:150px; object-fit:cover;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Tagline</label>
                        <input type="text" name="c_tagline" id="cFTagline" placeholder="Short compelling description"
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                        <div><label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Category</label>
                        <input type="text" name="c_category" id="cFCat" placeholder="e.g. Branding"
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;"></div>
                        <div><label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Level</label>
                        <select name="c_level" id="cFLevel"
                                style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;">
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate">Intermediate</option>
                            <option value="Advanced">Advanced</option>
                        </select></div>
                        <div><label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Assign Teacher</label>
                        <select name="c_instructor_id" id="cFInstructor"
                                style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;">
                            <option value="0">-- Unassigned --</option>
                            <?php foreach($teachers as $t): ?>
                                <option value="<?= $t['user_id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    </div>
                    
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px; margin-top:10px;">
                        <div><label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Price (Rs.)</label>
                        <input type="number" name="c_price" id="cFPrice" placeholder="0" min="0"
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;"></div>
                        <div><label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Gen. Discount (%)</label>
                        <input type="number" name="c_discount" id="cFDisc" placeholder="0" min="0" max="100"
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;"></div>
                        <div><label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Student Discount (%)</label>
                        <input type="number" name="c_student_discount" id="cFStudentDisc" placeholder="0" min="0" max="100"
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;"></div>
                        <div><label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Discount End Date</label>
                        <input type="date" name="c_discount_end" id="cFDiscEnd" 
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;"></div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px; margin-top:10px;">
                        <div><label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Duration (mins)</label>
                        <input type="number" name="c_duration" id="cFDur" placeholder="e.g. 480"
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;"></div>
                        <div><label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Starting Date</label>
                        <input type="date" name="c_start_date" id="cFStartDate" 
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;"></div>
                        <div><label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Installment Count</label>
                        <input type="number" name="c_installment_count" id="cFInstCnt" min="1" max="12" value="1"
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;"></div>
                    </div>

                    <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:center;margin-top:15px;">
                        <label style="display:flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:#333;cursor:pointer;">
                            <input type="checkbox" name="c_is_free"      id="cFree"    style="width:16px;height:16px;accent-color:var(--primary);"> Free Course</label>
                        <label style="display:flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:#333;cursor:pointer;">
                            <input type="checkbox" name="c_is_featured"  id="cFeat"    style="width:16px;height:16px;accent-color:var(--primary);"> Featured</label>
                        <label style="display:flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:#333;cursor:pointer;">
                            <input type="checkbox" name="c_installments_enabled" id="cInstEn" style="width:16px;height:16px;accent-color:var(--primary);"> Allow Installments</label>
                        <label id="cPubRow" style="display:flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:#333;cursor:pointer;">
                            <input type="checkbox" name="c_is_published" id="cPub"     style="width:16px;height:16px;accent-color:var(--primary);"> Published</label>
                    </div>

                    <div class="form-group" style="margin-top:10px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Description</label>
                        <div id="cFDescEditor" style="height: 200px;"></div>
                        <input type="hidden" name="c_description" id="cFDesc">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeCourseModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="cSubmit"><i class="fas fa-book"></i> Save Course</button>
            </div>
        </form>
    </div>
</div>

<div id="toastContainer" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;"></div>

<script>
// ── NEW: MULTI-LEVEL FILTER LOGIC ──
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('regSearchInput');
    const courseDropdown = document.getElementById('courseFilterDropdown');
    const amountDropdown = document.getElementById('amountFilterDropdown');

    if (searchInput && courseDropdown && amountDropdown) {
        function runFilters() {
            const query = searchInput.value.toLowerCase().trim();
            const selectedCourse = courseDropdown.value;
            const selectedAmount = amountDropdown.value;

            const courseSections = document.querySelectorAll('.course-table-section');

            courseSections.forEach(section => {
                const sectionCourseTitle = section.getAttribute('data-course-title');
                const studentRows = section.querySelectorAll('.student-data-row');
                let matchCount = 0;

                // Match Course Dropdown first
                const courseMatches = (selectedCourse === 'all' || selectedCourse === sectionCourseTitle);

                studentRows.forEach(row => {
                    const rowText = row.innerText.toLowerCase();
                    const rowPayType = row.getAttribute('data-pay-type');

                    // Evaluate row data matches
                    const textMatches = (query === '' || rowText.includes(query));
                    const amountMatches = (selectedAmount === 'all' || selectedAmount === rowPayType);

                    if (courseMatches && textMatches && amountMatches) {
                        row.style.display = ''; // Show row
                        matchCount++;
                    } else {
                        row.style.display = 'none'; // Hide row
                    }
                });

                // Control section title/outer framework visibility
                if (courseMatches && matchCount > 0) {
                    section.style.display = '';
                    const counter = section.querySelector('.course-section-title span');
                    if (counter) counter.textContent = `(${matchCount} match${matchCount !== 1 ? 'es' : ''})`;
                } else {
                    section.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('keyup', runFilters);
        courseDropdown.addEventListener('change', runFilters);
        amountDropdown.addEventListener('change', runFilters);
    }
});
// ─────────────────────────────────────

// ── coursesData: string-keyed object — critical for modal lookup ──────────────
const coursesData = <?= json_encode($courses_js, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
const currentEsub = '<?= addslashes($esub) ?>';

const enrollStatuses = {
    pending:   { label: 'Set Pending',   bg: '#fef9c3', color: '#a16207' },
    reviewing: { label: 'Set Reviewing', bg: '#e0f2fe', color: '#0284c7' },
    active:    { label: 'Approve',       bg: '#d7f8b8', color: '#2b7a2b' },
    completed: { label: 'Mark Complete', bg: '#dbeaff', color: '#1b4ed8' },
    rejected:  { label: 'Reject',        bg: '#ffe5e5', color: '#d63031' },
    cancelled: { label: 'Cancel',        bg: '#f0f0f0', color: '#666'    },
};

// ── Payment preview renderer ──────────────────────────────────────────────────
function renderPaymentPreview(url, wrapId, fallbackHtml) {
    const wrap = document.getElementById(wrapId);
    if (!wrap) return;
    if (!url || url === 'undefined' || !url.trim()) { wrap.innerHTML = fallbackHtml; return; }
    const finalUrl = (url.startsWith('http') || url.startsWith('/')) ? url : '../' + url;
    const isPdf    = finalUrl.toLowerCase().split('?')[0].endsWith('.pdf');
    wrap.innerHTML = isPdf
        ? `<a href="${finalUrl}" target="_blank" style="display:inline-flex;align-items:center;gap:6px;background:#e0f2fe;color:#0284c7;padding:9px 16px;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;"><i class="fas fa-file-alt"></i> Open PDF</a>`
        : `<a href="${finalUrl}" target="_blank" title="Click to view full size"><img src="${finalUrl}" style="width:100%;max-height:280px;border-radius:10px;border:1.5px solid #e5e5e5;object-fit:contain;background:#f9fbfc;" onerror="this.parentElement.innerHTML='<span style=color:#d63031;><i class="fas fa-exclamation-triangle"></i>️ Image failed to load</span>'"></a>`;
}

// ── Enrollment view modal ─────────────────────────────────────────────────────
document.querySelectorAll('.view-enrollment').forEach(btn => {
    btn.addEventListener('click', function () {
        const d    = this.dataset;
        const paid = parseFloat(d.paid)        || 0;
        const cp   = parseFloat(d.courseprice) || 0;
        const cf   = parseInt(d.coursefree)    || 0;
        const pType = d.paytype                || 'unpaid';

        document.getElementById('emName').textContent   = d.name;
        document.getElementById('emPhone').textContent  = d.phone  || '—';
        document.getElementById('emOcc').textContent    = d.occ    || '—';
        document.getElementById('emCourse').textContent = d.course;
        document.getElementById('emCode').textContent   = d.code   || '—';
        document.getElementById('emDate').textContent   = d.date;
        document.getElementById('emEmail').innerHTML    = `<a href="mailto:${d.email}" style="color:var(--primary);">${d.email}</a>`;
        document.getElementById('emReplyLink').href     = `mailto:${d.email}?subject=Re: Course Registration`;

        // New fields
        const showField = (rowId, valId, val) => {
            if (val && val.trim()) { document.getElementById(rowId).style.display = ''; document.getElementById(valId).textContent = val; }
            else { document.getElementById(rowId).style.display = 'none'; }
        };
        showField('emFatherRow', 'emFather', d.father);
        showField('emAddressRow', 'emAddress', d.address);
        showField('emGenderRow', 'emGender', d.gender);
        showField('emEducationRow', 'emEducation', d.education);
        if (d.dob && d.dob.trim() && d.dob !== '0000-00-00') {
            document.getElementById('emDobRow').style.display = '';
            const dobDate = new Date(d.dob);
            document.getElementById('emDob').textContent = dobDate.toLocaleDateString('en-US', {year:'numeric',month:'long',day:'numeric'});
        } else { document.getElementById('emDobRow').style.display = 'none'; }

        // Student picture
        const picWrap = document.getElementById('emPictureWrap');
        if (d.picture && d.picture.trim()) {
            const picUrl = (d.picture.startsWith('http') || d.picture.startsWith('/')) ? d.picture : '../' + d.picture;
            document.getElementById('emPictureImg').src = picUrl;
            picWrap.style.display = 'block';
        } else { picWrap.style.display = 'none'; }

        // Amount
        const paidEl = document.getElementById('emPaid');
        if (cf || pType === 'free') {
            paidEl.innerHTML = '<span style="color:#10b981;font-weight:700;">Free Course</span>';
        } else if (pType === 'referral') {
            paidEl.innerHTML = '<span style="color:#10b981;font-weight:700;">Free (Referral)</span>';
        } else if (paid > 0) {
            paidEl.innerHTML = `<strong>Rs. ${paid.toLocaleString()}</strong>`;
        } else if (d.payment) {
            paidEl.innerHTML = `<span style="color:#e17055;">Rs. ${cp.toLocaleString()} (receipt uploaded, unconfirmed)</span>`;
        } else if (d.code) {
            paidEl.innerHTML = `<span style="color:#7c3aed;">Discount Code applied</span>`;
        } else {
            paidEl.innerHTML = `<span style="color:#d63031;font-weight:700;">Not Paid</span>`;
        }

        // Status badge
        const st = d.status;
        const sb = enrollStatuses[st] || { label: st, bg: '#f0f0f0', color: '#555' };
        const label = st.charAt(0).toUpperCase() + st.slice(1);
        document.getElementById('emStatus').innerHTML =
            `<span style="background:${sb.bg};color:${sb.color};padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;">${label}</span>`;

        // Motivation
        const motBox = document.getElementById('emMotivBox');
        if (d.motive && d.motive.trim()) {
            motBox.style.display = ''; document.getElementById('emMotiv').textContent = d.motive;
        } else { motBox.style.display = 'none'; }

        // Payment proof
        const payBox = document.getElementById('emPaymentBox');
        payBox.style.display = '';
        let fallback = '<span style="color:#d63031;font-weight:600;"><i class="fas fa-exclamation-triangle"></i>️ No receipt uploaded</span>';
        if (cf || pType === 'free') {
            fallback = '<span style="color:#aaa;">Free — no payment required</span>';
        } else if (pType === 'referral') {
            fallback = '<span style="color:#aaa;">Referral Code — no payment required</span>';
        }
        renderPaymentPreview(d.payment, 'emPaymentWrap', fallback);

        // Student ID Card preview
        const cardBox = document.getElementById('emStudentCardBox');
        if (d.studentcard && d.studentcard.trim() && d.occ === 'student') {
            cardBox.style.display = '';
            renderPaymentPreview(d.studentcard, 'emStudentCardWrap', '<span style="color:#aaa;">No student ID uploaded</span>');
        } else { cardBox.style.display = 'none'; }

        // Status update buttons
        const btns = document.getElementById('emStatusBtns');
        btns.innerHTML = '';
        const esub = d.esub || currentEsub;
        Object.entries(enrollStatuses).forEach(([key, val]) => {
            const f = document.createElement('form');
            f.method = 'POST'; f.style.display = 'inline';
            const isCur = key === st;
            f.innerHTML = `
                <input type="hidden" name="update_enrollment" value="1">
                <input type="hidden" name="enrollment_id"     value="${d.id}">
                <input type="hidden" name="status"             value="${key}">
                <input type="hidden" name="current_tab"        value="${esub}">
                <button type="submit" style="background:${val.bg};color:${val.color};border:none;padding:7px 14px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;opacity:${isCur?'.4':'1'};${isCur?'pointer-events:none;':''}" ${isCur?'disabled':''}>
                    ${val.label}
                </button>`;
            btns.appendChild(f);
        });

        document.getElementById('enrollModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    });
});

function closeEnrollModal() {
    document.getElementById('enrollModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ── Quill editor setup ────────────────────────────────────────────────────────
let descEditor = null;
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Quill !== 'undefined') {
        descEditor = new Quill('#cFDescEditor', {
            modules: { toolbar: [[{header:[2,3,false]}],['bold','italic','underline'],[{list:'ordered'},{list:'bullet'}],['link']] },
            placeholder: 'What will students learn?',
            theme: 'snow',
        });
        const descInput = document.getElementById('cFDesc');
        descEditor.on('text-change', () => { descInput.value = descEditor.root.innerHTML; });
    }
});

// ── Course add/edit modal ─────────────────────────────────────────────────────
function openCourseModal(mode, id) {
    const isEdit = mode === 'edit';
    const sid    = String(id ?? '');

    // FIX: Using correct html IDs 
    const titleEl = document.getElementById('cTitle');
    if (titleEl) titleEl.textContent = isEdit ? 'Edit Course' : 'Add Course';
    
    const submitBtn = document.getElementById('cSubmit');
    if (submitBtn) submitBtn.textContent = isEdit ? '<i class="fas fa-pencil-alt"></i>️ Update Course' : '<i class="fas fa-book"></i> Save Course';

    // <i class="fas fa-circle text-success"></i> SINGLE ACTION INPUT FIX
    document.getElementById('cAction').value = isEdit ? 'edit_course' : 'add_course';
    document.getElementById('cId').value     = sid;

    // Published checkbox only shown in edit mode
    const pubRow = document.getElementById('cPubRow');
    if (pubRow) pubRow.style.display = isEdit ? 'flex' : 'none';

    if (isEdit && coursesData[sid]) {
        const c = coursesData[sid];

        document.getElementById('cFTitle').value      = c.title              || '';
        document.getElementById('cFThumb').value      = c.thumbnail          || '';
        document.getElementById('cFTagline').value    = c.tagline            || '';
        document.getElementById('cFCat').value        = c.category           || '';
        document.getElementById('cFLevel').value      = c.level              || 'Beginner';
        document.getElementById('cFPrice').value      = c.is_free == 1 ? '' : (c.price || '');
        document.getElementById('cFDisc').value       = c.discount_percent   || '0';
        document.getElementById('cFStudentDisc').value= c.student_discount   || '0';
        document.getElementById('cFDiscEnd').value    = c.discount_end_date  || '';
        document.getElementById('cFDur').value        = c.duration_mins      || '';
        document.getElementById('cFStartDate').value  = c.start_date         || '';
        document.getElementById('cFInstCnt').value    = c.installment_count  || '1';

        const instrSel = document.getElementById('cFInstructor');
        if (instrSel) instrSel.value = c.instructor_id || '0';

        document.getElementById('cFree').checked   = c.is_free              == 1;
        document.getElementById('cFeat').checked   = c.is_featured          == 1;
        document.getElementById('cPub').checked    = c.is_published         == 1;
        document.getElementById('cInstEn').checked = c.installments_enabled == 1;

        if (typeof descEditor !== 'undefined' && descEditor) {
            const desc = c.description || '';
            descEditor.root.innerHTML = desc;
            document.getElementById('cFDesc').value = desc;
        }

        updateThumbPreview(c.thumbnail || '');

    } else {
        document.getElementById('courseForm').reset();
        
        if (typeof descEditor !== 'undefined' && descEditor) { 
            descEditor.root.innerHTML = ''; 
            document.getElementById('cFDesc').value = ''; 
        }
        
        updateThumbPreview('');
    }

    document.getElementById('courseModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeCourseModal() {
    document.getElementById('courseModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Live thumbnail preview
document.getElementById('cFThumb').addEventListener('input', function () {
    updateThumbPreview(this.value);
});

// FIX: Null checks inside updateThumbPreview
function updateThumbPreview(url) {
    const wrap = document.getElementById('thumbPreview');
    const img  = document.getElementById('thumbPreviewImg');
    
    if (!wrap || !img) return; // Prevent crashes if HTML is missing
    
    if (url && url.trim()) {
        img.src = url; 
        wrap.style.display = '';
    } else { 
        wrap.style.display = 'none'; 
    }
}

// ── Toasts ────────────────────────────────────────────────────────────────────
function showToast(msg, type='success') {
    const color = type === 'success' ? '#10b981' : '#ef4444';
    const t = document.createElement('div');
    t.style.cssText = `background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:220px;border-left:4px solid ${color};z-index:9999;`;
    t.innerHTML = `<span style="font-weight:700;color:${color}">${type==='success'?'<i class="fas fa-check"></i>':'<i class="fas fa-times"></i>'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3500);
}

document.addEventListener('DOMContentLoaded', () => {
    <?php if (isset($_GET['updated'])):         ?> showToast('Updated successfully.');                                         <?php endif; ?>
    <?php if (isset($_GET['deleted'])):         ?> showToast('Deleted.');                                                      <?php endif; ?>
    <?php if (isset($_GET['course_added'])):    ?> showToast('Course saved successfully!');                                    <?php endif; ?>
    <?php if (isset($_GET['generated'])):       ?> showToast('Referral code generated!');                                      <?php endif; ?>
    <?php if (isset($_GET['assigned_direct'])): ?> showToast('Course directly assigned to student successfully!');             <?php endif; ?>
});

function openDirectAssignModal() {
    document.getElementById('directAssignModalWrap').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeDirectAssignModal() {
    document.getElementById('directAssignModalWrap').style.display = 'none';
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeEnrollModal(); closeCourseModal(); closeDirectAssignModal(); }
});
</script>

<!-- Direct Course Assignment Modal -->
<div id="directAssignModalWrap" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:99999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:500px;box-shadow:0 20px 40px rgba(0,0,0,0.2);overflow:hidden;animation:modalSlideIn .25s ease;">
        <div style="background:#024442;padding:20px 24px;color:white;display:flex;justify-content:space-between;align-items:center;">
            <h4 style="margin:0;font-size:16px;font-weight:800;"><i class="fas fa-user-plus me-2"></i> Directly Assign Course to Student</h4>
            <button type="button" onclick="closeDirectAssignModal()" style="background:none;border:none;color:white;font-size:18px;cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" style="padding:24px;">
            <input type="hidden" name="direct_assign_course" value="1">
            <div style="margin-bottom:20px;">
                <label style="display:block;font-weight:700;font-size:13.5px;color:#333;margin-bottom:6px;">Select Student Account</label>
                <select name="student_user_id" required style="width:100%;padding:12px;border:1.5px solid #ccc;border-radius:8px;font-size:14px;outline:none;">
                    <option value="">-- Choose Student --</option>
                    <?php foreach ($all_students as $as): ?>
                        <option value="<?= $as['user_id'] ?>"><?= htmlspecialchars($as['name']) ?> (<?= htmlspecialchars($as['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:24px;">
                <label style="display:block;font-weight:700;font-size:13.5px;color:#333;margin-bottom:6px;">Select Course to Grant Access</label>
                <select name="assign_course_id" required style="width:100%;padding:12px;border:1.5px solid #ccc;border-radius:8px;font-size:14px;outline:none;">
                    <option value="">-- Choose Course --</option>
                    <?php foreach ($all_courses_list as $acl): ?>
                        <option value="<?= $acl['id'] ?>"><?= htmlspecialchars($acl['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:12px;">
                <button type="button" onclick="closeDirectAssignModal()" style="padding:10px 20px;border-radius:8px;border:1px solid #ccc;background:#f8f9fa;font-weight:600;cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:10px 24px;border-radius:8px;border:none;background:#024442;color:#b8f35a;font-weight:800;cursor:pointer;">Assign Course & Notify</button>
            </div>
        </form>
    </div>
</div>

<?php include('dashboard_footer.php'); ?>