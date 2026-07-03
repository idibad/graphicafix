<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once $_SERVER['DOCUMENT_ROOT'] . '/graphicafix/core/config.php';

// Auth check
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$username = mysqli_real_escape_string($conn, $_SESSION['username']);
$result   = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
$data     = mysqli_fetch_assoc($result);

if (!$data) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

$name    = $data['name'];
$phone   = $data['phone'];
$email   = $data['email'];
$user_id = intval($data['user_id']);
$role    = strtolower(trim($data['role']));

$_SESSION['user_id'] = $user_id;
$_SESSION['email']   = $email;
$_SESSION['role']    = $role;

// Allow admins/staff to view portal as teacher if they test it
$portal_role = $role;
if (!in_array($role, ['student', 'teacher'])) {
    $portal_role = 'teacher'; // fallback preview role for admin
}

// ══════════════════════════════════════════════════════════════════════════════
//  AUTO-PATCH SCHEMA FOR LMS PORTAL & VIMEO INTEGRATION
// ══════════════════════════════════════════════════════════════════════════════
$existing_lec_cols = array_column(
    $conn->query("SHOW COLUMNS FROM recorded_lectures")->fetch_all(MYSQLI_ASSOC),
    'Field'
);
$lec_patch = [
    'video_type'   => "VARCHAR(50) DEFAULT 'mp4'",
    'video_url'    => "VARCHAR(500) DEFAULT NULL",
    'vimeo_id'     => "VARCHAR(100) DEFAULT NULL",
    'duration_sec' => "INT DEFAULT 0",
];
foreach ($lec_patch as $col => $def) {
    if (!in_array($col, $existing_lec_cols)) {
        @$conn->query("ALTER TABLE recorded_lectures ADD COLUMN $col $def");
    }
}

// Auto-patch notices table schema
$existing_not_cols = @array_column(
    @$conn->query("SHOW COLUMNS FROM notices")->fetch_all(MYSQLI_ASSOC),
    'Field'
);
if ($existing_not_cols && !in_array('created_by', $existing_not_cols)) {
    @$conn->query("ALTER TABLE notices ADD COLUMN created_by VARCHAR(100) DEFAULT 'Admin'");
}

// Student video watch progress table
$conn->query("CREATE TABLE IF NOT EXISTS student_video_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    lecture_id INT NOT NULL,
    course_id INT NOT NULL,
    is_completed TINYINT(1) DEFAULT 1,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY student_lecture (student_id, lecture_id)
)");

// Core LMS expansion tables
$conn->query("CREATE TABLE IF NOT EXISTS course_quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option VARCHAR(5) NOT NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS student_quiz_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    student_id INT NOT NULL,
    score INT NOT NULL,
    total_questions INT NOT NULL,
    taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS course_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS course_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Role-based LMS navigation
$menus = [
    'teacher' => [
        ['title' => 'Dashboard',       'link' => 'index.php',               'icon' => 'fas fa-tachometer-alt'],
        ['title' => 'My Courses',      'link' => 'teacher_courses.php',     'icon' => 'fas fa-book-open'],
        ['title' => 'Video Lectures',  'link' => 'teacher_lectures.php',    'icon' => 'fas fa-video'],
        ['title' => 'Assignments',     'link' => 'teacher_assignments.php', 'icon' => 'fas fa-tasks'],
        ['title' => 'Quizzes',         'link' => 'teacher_quizzes.php',     'icon' => 'fas fa-question-circle'],
        ['title' => 'Resources',       'link' => 'teacher_resources.php',   'icon' => 'fas fa-file-archive'],
        ['title' => 'Announcements',   'link' => 'teacher_announcements.php','icon' => 'fas fa-bullhorn'],
        ['title' => 'Student Queries', 'link' => 'teacher_queries.php',     'icon' => 'fas fa-comments'],
        ['title' => 'My Documents',    'link' => 'teacher_documents.php',   'icon' => 'fas fa-folder-open'],
        ['title' => 'Settings',        'link' => 'profile.php',             'icon' => 'fas fa-cog'],
    ],
    'student' => [
        ['title' => 'Dashboard',       'link' => 'index.php',               'icon' => 'fas fa-tachometer-alt'],
        ['title' => 'My Learning',     'link' => 'student_courses.php',     'icon' => 'fas fa-graduation-cap'],
        ['title' => 'Live Classes',    'link' => 'student_live.php',        'icon' => 'fas fa-video'],
        ['title' => 'Assignments',     'link' => 'student_assignments.php', 'icon' => 'fas fa-tasks'],
        ['title' => 'My Q&A',          'link' => 'student_queries.php',     'icon' => 'fas fa-question-circle'],
        ['title' => 'Certifications',  'link' => 'student_certificates.php','icon' => 'fas fa-certificate'],
        ['title' => 'Settings',        'link' => 'profile.php',             'icon' => 'fas fa-cog'],
    ],
];

// Capture notifications HTML
ob_start();
include('../admin/notifications.php');
$notifications_html = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Graphicafix - LMS Portal</title>
    <link href="<?= BASE_URL ?>css/bootstrap.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>images/icon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <link href="<?= BASE_URL ?>css/dashboard.css" rel="stylesheet">

    <!-- Vimeo Player JS API -->
    <script src="https://player.vimeo.com/api/player.js"></script>

    <style>
        @media (max-width: 767px) {
            body, body.body-pd                { padding-left: 1rem !important; margin-left: 0 !important; }
            #header, #header.body-pd          { left: 0 !important; width: 100% !important; padding-left: 1rem !important; padding-right: 1rem !important; }
            #nav-bar                          { left: -100% !important; width: 240px !important; z-index: 200 !important; transition: left 0.3s ease !important; }
            #nav-bar.show                     { left: 0 !important; }
            #navBackdrop                      { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 150; }
            #navBackdrop.visible              { display: block; }
        }

        .header-right { display: flex; align-items: center; gap: 8px; }

        .header-profile-wrap  { position: relative; }
        .header-profile-btn   { display: flex; align-items: center; gap: 8px; background: #f4f6f8; border: 1.5px solid #e8eaed; border-radius: 50px; padding: 4px 12px 4px 4px; cursor: pointer; transition: background .2s, border-color .2s; font-family: inherit; font-size: 13.5px; font-weight: 600; color: #333; }
        .header-profile-btn:hover,
        .header-profile-wrap.open .header-profile-btn { background: #fff; border-color: var(--accent); }
        .header-avatar        { width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, var(--accent, #b8f35a) 0%, #87bd0aff 100%); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #1a1a1a; flex-shrink: 0; }
        .header-name          { max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .header-chevron       { color: #888; flex-shrink: 0; transition: transform .25s; pointer-events: none; }
        .header-profile-wrap.open .header-chevron { transform: rotate(180deg); }

        @media (max-width: 480px) {
            .header-name { display: none; }
            .header-profile-btn { padding: 4px; border-radius: 50%; }
        }

        .header-dropdown      { display: none; position: absolute; top: calc(100% + 10px); right: 0; min-width: 200px; background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 16px 48px rgba(0,0,0,.13); z-index: 9998; overflow: hidden; animation: profileSlideIn .2s cubic-bezier(.22,.88,.36,1) both; }
        .header-profile-wrap.open .header-dropdown { display: block; }

        @keyframes profileSlideIn {
            from { opacity: 0; transform: translateY(-6px) scale(.98); }
            to   { opacity: 1; transform: translateY(0)    scale(1);   }
        }

        .header-dropdown-top    { display: flex; align-items: center; gap: 10px; padding: 14px 14px 10px; }
        .header-dropdown-avatar { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, var(--accent, #b8f35a) 0%, #87bd0aff 100%); display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 700; color: #1a1a1a; flex-shrink: 0; }
        .header-dropdown-name   { font-size: 13.5px; font-weight: 700; color: #111; }
        .header-dropdown-role   { font-size: 11.5px; color: #888; margin-top: 1px; }
        .header-dropdown-divider { height: 1px; background: #f0f0f0; margin: 2px 0; }
        .header-dropdown-item   { display: flex; align-items: center; gap: 10px; padding: 10px 14px; font-size: 13.5px; color: #374151; text-decoration: none; font-weight: 500; transition: background .15s; }
        .header-dropdown-item:hover          { background: #f4f6f8; color: #111; }
        .header-dropdown-logout              { color: #e53e3e; }
        .header-dropdown-logout:hover        { background: #fef2f2; color: #c53030; }

        .lms-role-badge {
            background: #024442;
            color: #b8f35a;
            font-size: 11px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>

<body id="body-pd">

<div id="navBackdrop"></div>

<div class="header mb-5" id="header">
    <div class="header_toggle">
        <button type="button" id="sidebarToggleBtn"
            style="background:none;border:none;cursor:pointer;padding:6px 8px;line-height:1;display:flex;align-items:center;color:inherit;">
            <i class='bx bx-menu' style="font-size:1.5rem;pointer-events:none;"></i>
        </button>
    </div>

    <div style="display:flex; align-items:center; gap:12px;">
        <span class="lms-role-badge"><i class="fas fa-graduation-cap"></i> <?= strtoupper($portal_role) ?> PORTAL</span>
    </div>

    <div class="header-right">
        <?= $notifications_html ?>

        <div class="header-profile-wrap" id="headerProfileWrap">
            <button type="button" class="header-profile-btn" onclick="toggleProfileMenu()">
                <div class="header-avatar"><?= strtoupper(substr($name, 0, 1)) ?></div>
                <span class="header-name"><?= htmlspecialchars($name) ?></span>
                <svg class="header-chevron" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>

            <div class="header-dropdown">
                <div class="header-dropdown-top">
                    <div class="header-dropdown-avatar"><?= strtoupper(substr($name, 0, 1)) ?></div>
                    <div>
                        <div class="header-dropdown-name"><?= htmlspecialchars($name) ?></div>
                        <div class="header-dropdown-role"><?= htmlspecialchars(ucfirst($role)) ?></div>
                    </div>
                </div>
                <div class="header-dropdown-divider"></div>
                <a class="header-dropdown-item" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                <a class="header-dropdown-item" href="<?= BASE_URL ?>"><i class="fas fa-external-link-alt"></i> Main Website</a>
                <div class="header-dropdown-divider"></div>
                <a class="header-dropdown-item header-dropdown-logout" href="logout.php">🚪 Logout</a>
            </div>
        </div>
    </div>
</div>

<div class="l-navbar" id="nav-bar">
    <nav class="nav">
        <div>
            <a href="index.php" class="nav_logo">
                <span class="nav_logo-name">
                    <img src="<?= BASE_URL ?>images/logo-secondary.png" width="100px" alt="Graphicafix">
                </span>
            </a>
            <div class="nav_list">
                <?php foreach ($menus[$portal_role] ?? [] as $menu):
                    $active = basename($_SERVER['PHP_SELF']) === basename($menu['link']) ? 'active' : '';
                ?>
                <a class="nav_link <?= $active ?>" href="<?= $menu['link'] ?>">
                    <i class="<?= $menu['icon'] ?> nav_icon"></i>
                    <span class="nav_name"><?= $menu['title'] ?></span>
                </a>
                <?php endforeach; ?>
                <a href="logout.php" class="nav_link">
                    <i class="fas fa-sign-out-alt nav_icon"></i>
                    <span class="nav_name">Sign Out</span>
                </a>
            </div>
        </div>
    </nav>
</div>

<script>
(function () {
    var btn      = document.getElementById('sidebarToggleBtn');
    var navbar   = document.getElementById('nav-bar');
    var header   = document.getElementById('header');
    var bodyEl   = document.getElementById('body-pd');
    var backdrop = document.getElementById('navBackdrop');

    if (!btn || !navbar || !header || !bodyEl) return;

    function isMobile() { return window.innerWidth < 768; }

    function openSidebar() {
        navbar.classList.add('show');
        if (isMobile()) {
            if (backdrop) backdrop.classList.add('visible');
            document.body.style.overflow = 'hidden';
        } else {
            header.classList.add('body-pd');
            bodyEl.classList.add('body-pd');
        }
    }

    function closeSidebar() {
        navbar.classList.remove('show');
        if (backdrop) backdrop.classList.remove('visible');
        document.body.style.overflow = '';
        if (!isMobile()) {
            header.classList.remove('body-pd');
            bodyEl.classList.remove('body-pd');
        }
    }

    function toggleSidebar(e) {
        e.stopPropagation();
        navbar.classList.contains('show') ? closeSidebar() : openSidebar();
    }

    isMobile() ? (
        navbar.classList.remove('show'),
        header.classList.remove('body-pd'),
        bodyEl.classList.remove('body-pd')
    ) : (
        navbar.classList.add('show'),
        header.classList.add('body-pd'),
        bodyEl.classList.add('body-pd')
    );

    btn.addEventListener('click', toggleSidebar);
    if (backdrop) backdrop.addEventListener('click', closeSidebar);

    window.addEventListener('resize', function () {
        if (isMobile()) {
            navbar.classList.remove('show');
            if (backdrop) backdrop.classList.remove('visible');
            header.classList.remove('body-pd');
            bodyEl.classList.remove('body-pd');
            document.body.style.overflow = '';
        } else {
            navbar.classList.add('show');
            if (backdrop) backdrop.classList.remove('visible');
            header.classList.add('body-pd');
            bodyEl.classList.add('body-pd');
            document.body.style.overflow = '';
        }
    });
})();

function toggleProfileMenu() {
    document.getElementById('headerProfileWrap').classList.toggle('open');
}

document.addEventListener('click', function (e) {
    var wrap = document.getElementById('headerProfileWrap');
    if (wrap && !wrap.contains(e.target)) wrap.classList.remove('open');
});
</script>
