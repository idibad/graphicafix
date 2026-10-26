<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/config.php';

// Auth check
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$username = trim($_SESSION['username'] ?? '');
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();




$user_status = strtolower(trim($data['status'] ?? 'active'));
if (!$data || $user_status !== 'active') {
    session_unset();
    session_destroy();
    header("Location: login.php?error=deactivated");
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

// ── Create core LMS tables if they don't exist ──
$conn->query("CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    thumbnail VARCHAR(500) DEFAULT NULL,
    instructor_id INT DEFAULT NULL,
    duration_mins INT DEFAULT 0,
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS course_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    student_email VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'active',
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS recorded_lectures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    video_file VARCHAR(500) DEFAULT NULL,
    video_type VARCHAR(50) DEFAULT 'mp4',
    video_url VARCHAR(500) DEFAULT NULL,
    vimeo_id VARCHAR(100) DEFAULT NULL,
    duration_sec INT DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATE DEFAULT NULL,
    max_marks INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS assignment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    comments TEXT,
    marks INT DEFAULT NULL,
    graded_at DATETIME DEFAULT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    certificate_code VARCHAR(100) DEFAULT NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS course_queries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    student_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    reply TEXT DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS student_video_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    lecture_id INT NOT NULL,
    course_id INT NOT NULL,
    is_completed TINYINT(1) DEFAULT 1,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY student_lecture (student_id, lecture_id)
)");

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

// ── Column patches for recorded_lectures (add Vimeo support columns if missing) ──
$lec_col_result = @$conn->query("SHOW COLUMNS FROM recorded_lectures");
if ($lec_col_result) {
    $existing_lec_cols = array_column($lec_col_result->fetch_all(MYSQLI_ASSOC), 'Field');
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
}

// ── Column patch for notices (add created_by if missing) ──
$not_col_result = @$conn->query("SHOW COLUMNS FROM notices");
if ($not_col_result) {
    $existing_not_cols = array_column($not_col_result->fetch_all(MYSQLI_ASSOC), 'Field');
    if (!in_array('created_by', $existing_not_cols)) {
        @$conn->query("ALTER TABLE notices ADD COLUMN created_by VARCHAR(100) DEFAULT 'Admin'");
    }
}

// ── Helper: check active state for menu / subitems ────────────────────────────
if (!function_exists('isMenuItemActive')) {
    function isMenuItemActive($link) {
        if (empty($link) || $link === '#') return false;
        $current_file = basename($_SERVER['PHP_SELF']);
        $parsed_link = parse_url($link);
        $link_file = basename($parsed_link['path'] ?? '');
        
        if ($current_file !== $link_file) {
            return false;
        }
        
        if (isset($parsed_link['query'])) {
            parse_str($parsed_link['query'], $link_query);
            foreach ($link_query as $k => $v) {
                if (($_GET[$k] ?? '') !== $v) {
                    return false;
                }
            }
            return true;
        } else {
            return true;
        }
    }
}

// Role-based LMS navigation
$menus = [
    'teacher' => [
        [
            'title' => 'Dashboard',       
            'link'  => 'index.php',               
            'icon'  => 'fas fa-tachometer-alt',
            'submenus' => [
                ['title' => 'Overview',         'link' => 'index.php',               'icon' => 'fas fa-home'],
            ]
        ],
        [
            'title' => 'Courses & Lectures',
            'link'  => '#',
            'icon'  => 'fas fa-book-open',
            'submenus' => [
                ['title' => 'My Courses',      'link' => 'teacher_courses.php',     'icon' => 'fas fa-book-reader'],
                ['title' => 'Video Lectures',  'link' => 'teacher_lectures.php',    'icon' => 'fas fa-video'],
            ]
        ],
        [
            'title' => 'Academic Activities',
            'link'  => '#',
            'icon'  => 'fas fa-tasks',
            'submenus' => [
                ['title' => 'Assignments',     'link' => 'teacher_assignments.php', 'icon' => 'fas fa-clipboard-list'],
                ['title' => 'Quizzes',         'link' => 'teacher_quizzes.php',     'icon' => 'fas fa-question-circle'],
            ]
        ],
        [
            'title' => 'Course Resources',
            'link'  => '#',
            'icon'  => 'fas fa-file-archive',
            'submenus' => [
                ['title' => 'Resources',       'link' => 'teacher_resources.php',   'icon' => 'fas fa-file-download'],
                ['title' => 'My Documents',    'link' => 'teacher_documents.php',   'icon' => 'fas fa-folder-open'],
            ]
        ],
        [
            'title' => 'Communications',
            'link'  => '#',
            'icon'  => 'fas fa-bullhorn',
            'submenus' => [
                ['title' => 'Announcements',   'link' => 'teacher_announcements.php','icon' => 'fas fa-comment-dots'],
                ['title' => 'Student Queries', 'link' => 'teacher_queries.php',     'icon' => 'fas fa-comments'],
            ]
        ],
        [
            'title' => 'Settings',        
            'link'  => 'profile.php',             
            'icon'  => 'fas fa-cog',
            'submenus' => [
                ['title' => 'My Profile',      'link' => 'profile.php',             'icon' => 'fas fa-user-circle'],
            ]
        ],
    ],
    'student' => [
        [
            'title' => 'Dashboard',       
            'link'  => 'index.php',               
            'icon'  => 'fas fa-tachometer-alt',
            'submenus' => [
                ['title' => 'Overview',        'link' => 'index.php',               'icon' => 'fas fa-home'],
            ]
        ],
        [
            'title' => 'Learning Hub',     
            'link'  => '#',
            'icon'  => 'fas fa-graduation-cap',
            'submenus' => [
                ['title' => 'My Learning',     'link' => 'student_courses.php',     'icon' => 'fas fa-book-open'],
                ['title' => 'Live Classes',    'link' => 'student_live.php',        'icon' => 'fas fa-video'],
            ]
        ],
        [
            'title' => 'Tasks & Exams',     
            'link'  => '#',
            'icon'  => 'fas fa-tasks',
            'submenus' => [
                ['title' => 'Assignments',     'link' => 'student_assignments.php', 'icon' => 'fas fa-clipboard-list'],
                ['title' => 'My Q&A',          'link' => 'student_queries.php',     'icon' => 'fas fa-question-circle'],
            ]
        ],
        [
            'title' => 'Achievements',  
            'link'  => '#',
            'icon'  => 'fas fa-certificate',
            'submenus' => [
                ['title' => 'Certifications',  'link' => 'student_certificates.php','icon' => 'fas fa-award'],
            ]
        ],
        [
            'title' => 'Settings',        
            'link'  => 'profile.php',             
            'icon'  => 'fas fa-cog',
            'submenus' => [
                ['title' => 'My Profile',      'link' => 'profile.php',             'icon' => 'fas fa-user-circle'],
            ]
        ],
    ],
];

// Capture notifications HTML (graceful: empty string if admin panel not accessible cross-domain)
ob_start();
$_notifications_file = __DIR__ . '/../admin/notifications.php';
if (file_exists($_notifications_file)) {
    @include($_notifications_file);
}
$notifications_html = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Graphicafix Education Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="icon" type="image/png" href="<?= MAIN_SITE_URL ?>assets/images/icon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <link href="<?= MAIN_SITE_URL ?>assets/css/dashboard.css?v=<?= time() ?>" rel="stylesheet">

    <!-- Vimeo Player JS API -->
    <script src="https://player.vimeo.com/api/player.js"></script>

    <style>
        .header-right { display: flex; align-items: center; gap: 12px; }

        .header-profile-wrap  { position: relative; }
        .header-profile-btn   { display: flex; align-items: center; gap: 10px; background: #ffffff; border: 1px solid var(--border-color); border-radius: 50px; padding: 6px 16px 6px 6px; cursor: pointer; transition: all 0.2s ease; font-family: inherit; font-size: 13.5px; font-weight: 600; color: var(--text-dark); }
        .header-profile-btn:hover,
        .header-profile-wrap.open .header-profile-btn { background: var(--bg-light); border-color: var(--primary-color); }
        .header-avatar        { width: 32px; height: 32px; border-radius: 50%; background: var(--primary-color); color: var(--accent-color); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; flex-shrink: 0; }
        .header-name          { max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .header-chevron       { color: var(--text-muted); flex-shrink: 0; transition: transform .25s; pointer-events: none; }
        .header-profile-wrap.open .header-chevron { transform: rotate(180deg); }

        @media (max-width: 480px) {
            .header-name { display: none; }
            .header-profile-btn { padding: 6px; border-radius: 50%; }
        }

        .header-dropdown      { display: none; position: absolute; top: calc(100% + 10px); right: 0; min-width: 220px; background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); z-index: 9998; overflow: hidden; }
        .header-profile-wrap.open .header-dropdown { display: block; }

        .header-dropdown-top    { display: flex; align-items: center; gap: 10px; padding: 16px 16px 12px; border-bottom: 1px solid var(--border-color); }
        .header-dropdown-avatar { width: 38px; height: 38px; border-radius: 50%; background: var(--primary-color); color: var(--accent-color); display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 700; flex-shrink: 0; }
        .header-dropdown-name   { font-size: 14px; font-weight: 700; color: var(--text-dark); }
        .header-dropdown-role   { font-size: 11.5px; color: var(--text-muted); margin-top: 1px; }
        .header-dropdown-item   { display: flex; align-items: center; gap: 10px; padding: 12px 16px; font-size: 13.5px; color: var(--text-dark); text-decoration: none; font-weight: 500; transition: background .15s; }
        .header-dropdown-item:hover          { background: var(--bg-light); color: var(--primary-color); }
        .header-dropdown-logout              { color: #e53e3e; border-top: 1px solid var(--border-color); }
        .header-dropdown-logout:hover        { background: #fef2f2; color: #c53030; }

        .lms-role-badge {
            background: var(--primary-color);
            color: var(--accent-color);
            font-size: 11px;
            font-weight: 800;
            padding: 5px 12px;
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
        <span class="lms-role-badge"><i class="fas fa-graduation-cap"></i> <?= strtoupper($portal_role) ?> — EDUCATION PORTAL</span>
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
                <a class="header-dropdown-item" href="<?= MAIN_SITE_URL ?>"><i class="fas fa-external-link-alt"></i> Main Website</a>
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
                <img src="<?= MAIN_SITE_URL ?>assets/images/icon.png" class="nav_logo-icon-img" width="32px" alt="G">
                <span class="nav_logo-name">
                    <img src="<?= MAIN_SITE_URL ?>assets/images/logo-secondary.png" width="100px" alt="Graphicafix">
                </span>
            </a>
            <div class="nav_list">
                <?php foreach ($menus[$portal_role] ?? [] as $menu):
                    if (!empty($menu['submenus'])) {
                        $is_group_active = false;
                        if (isMenuItemActive($menu['link'])) {
                            $is_group_active = true;
                        } else {
                            foreach ($menu['submenus'] as $sub) {
                                if (isMenuItemActive($sub['link'])) {
                                    $is_group_active = true;
                                    break;
                                }
                            }
                        }
                ?>
                <div class="nav_group <?= $is_group_active ? 'open' : '' ?>">
                    <div class="nav_group-toggle <?= $is_group_active ? 'active' : '' ?>" onclick="toggleNavGroup(this)">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <i class="<?= $menu['icon'] ?> nav_icon"></i>
                            <span class="nav_name"><?= $menu['title'] ?></span>
                        </div>
                        <i class="fas fa-chevron-down nav_chevron"></i>
                    </div>
                    <div class="nav_submenus">
                        <?php foreach ($menu['submenus'] as $sub): 
                            $sub_active = isMenuItemActive($sub['link']) ? 'active' : '';
                        ?>
                        <a class="nav_sublink <?= $sub_active ?>" href="<?= $sub['link'] ?>">
                            <span class="nav_subname"><?= $sub['title'] ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php } else {
                    $active = isMenuItemActive($menu['link']) ? 'active' : '';
                ?>
                <a class="nav_link <?= $active ?>" href="<?= $menu['link'] ?>">
                    <i class="<?= $menu['icon'] ?> nav_icon"></i>
                    <span class="nav_name"><?= $menu['title'] ?></span>
                </a>
                <?php } endforeach; ?>
                <a href="logout.php" class="nav_link">
                    <i class="fas fa-sign-out-alt nav_icon"></i>
                    <span class="nav_name">Sign Out</span>
                </a>
            </div>
        </div>
    </nav>
</div>

<script>
function toggleNavGroup(el) {
    var navbar = document.getElementById('nav-bar');
    var header = document.getElementById('header');
    var bodyEl = document.getElementById('body-pd');
    var group  = el.parentElement;

    if (navbar && navbar.classList.contains('collapsed')) {
        navbar.classList.remove('collapsed');
        if (bodyEl) bodyEl.classList.remove('collapsed-body');
        if (header) header.classList.remove('collapsed-header');
    }
    group.classList.toggle('open');
}

(function () {
    var btn      = document.getElementById('sidebarToggleBtn');
    var navbar   = document.getElementById('nav-bar');
    var header   = document.getElementById('header');
    var bodyEl   = document.getElementById('body-pd');
    var backdrop = document.getElementById('navBackdrop');

    if (!btn || !navbar || !header || !bodyEl) return;

    function isMobile() { return window.innerWidth < 768; }

    function toggleSidebar(e) {
        e.stopPropagation();
        if (isMobile()) {
            navbar.classList.toggle('show');
            if (backdrop) backdrop.classList.toggle('visible');
            if (navbar.classList.contains('show')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        } else {
            navbar.classList.toggle('collapsed');
            bodyEl.classList.toggle('collapsed-body');
            header.classList.toggle('collapsed-header');
        }
    }

    btn.addEventListener('click', toggleSidebar);
    if (backdrop) {
        backdrop.addEventListener('click', function() {
            navbar.classList.remove('show');
            backdrop.classList.remove('visible');
            document.body.style.overflow = '';
        });
    }

    function initLayout() {
        if (isMobile()) {
            navbar.classList.remove('show');
            navbar.classList.remove('collapsed');
            bodyEl.classList.remove('collapsed-body');
            header.classList.remove('collapsed-header');
            if (backdrop) backdrop.classList.remove('visible');
            document.body.style.overflow = '';
        } else {
            navbar.classList.remove('show');
            if (backdrop) backdrop.classList.remove('visible');
            document.body.style.overflow = '';
        }
    }

    window.addEventListener('resize', initLayout);
    initLayout();
})();

function toggleProfileMenu() {
    document.getElementById('headerProfileWrap').classList.toggle('open');
}

document.addEventListener('click', function (e) {
    var wrap = document.getElementById('headerProfileWrap');
    if (wrap && !wrap.contains(e.target)) wrap.classList.remove('open');
});
</script>
