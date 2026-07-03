<?php
// ── Prevent caching ───────────────────────────────────────────────────────────
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once $_SERVER['DOCUMENT_ROOT'] . '/graphicafix/core/config.php';
// ── Auth check ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// ── Fetch user ────────────────────────────────────────────────────────────────
$username = $_SESSION['username'];
$result   = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
$data     = mysqli_fetch_assoc($result);

$name    = $data['name'];
$phone   = $data['phone'];
$email   = $data['email'];
$user_id = $data['user_id'];
$role    = strtolower($data['role']); // admin | user | client | teacher | student

$can_edit = ($role === 'admin' || $role === 'pm');

// Auto-patch notices table schema
$existing_not_cols = @array_column(
    @$conn->query("SHOW COLUMNS FROM notices")->fetch_all(MYSQLI_ASSOC),
    'Field'
);
if ($existing_not_cols && !in_array('created_by', $existing_not_cols)) {
    @$conn->query("ALTER TABLE notices ADD COLUMN created_by VARCHAR(100) DEFAULT 'Admin'");
}

// ── Role-based menus ──────────────────────────────────────────────────────────
$menus = [
    'admin' => [
        ['title' => 'Dashboard',           'link' => 'index.php',               'icon' => 'fas fa-tachometer-alt'],
        ['title' => 'Projects',            'link' => 'manage_projects.php',     'icon' => 'fas fa-tasks'],
        ['title' => 'Tasks',               'link' => 'tasks.php',               'icon' => 'fas fa-check-square'],
        ['title' => 'Manage Invoices',     'link' => 'manage_invoices.php',     'icon' => 'fas fa-file-invoice-dollar'],
        ['title' => 'Manage Courses',      'link' => 'manage_courses.php',      'icon' => 'fas fa-graduation-cap'],
        ['title' => 'LMS Portal',          'link' => '../portal/index.php',     'icon' => 'fas fa-external-link-alt'],
        ['title' => 'Teacher Documents',   'link' => 'admin_teacher_docs.php',  'icon' => 'fas fa-file-alt'],
        ['title' => 'Recorded Lectures',   'link' => 'admin_lectures.php',      'icon' => 'fas fa-video'],
        ['title' => 'Clients',             'link' => 'manage_clients.php',      'icon' => 'fas fa-user-tie'],
        ['title' => 'Project Requests',    'link' => 'project_requests.php',    'icon' => 'fas fa-paper-plane'],
        ['title' => 'Correspondence',            'link' => 'correspondence.php',            'icon' => 'fas fa-envelope'],
        ['title' => 'Reviews',             'link' => 'reviews.php',             'icon' => 'fas fa-star'],
        ['title' => 'Users',               'link' => 'manage_users.php',        'icon' => 'fas fa-users'],
        ['title' => 'HR Management',        'link' => 'manage_applications.php', 'icon' => 'fas fa-file-alt'],
        ['title' => 'Services',            'link' => 'manage_services.php',     'icon' => 'fas fa-cogs'],
        ['title' => 'Manage Leads',            'link' => 'manage_leads.php',     'icon' => 'fas fa-phone'],
        ['title' => 'Blogs',               'link' => 'manage_blogs.php',        'icon' => 'fas fa-blog'],
        ['title' => 'Daily Reports',       'link' => 'dpr.php',                 'icon' => 'fas fa-calendar-check'],
        ['title' => 'Entertainment',       'link' => 'entertainment.php',       'icon' => 'fas fa-gamepad'],
        ['title' => 'Settings',            'link' => 'profile.php',             'icon' => 'fas fa-cog'],
    ],
    'user' => [ // Standard Staff
        ['title' => 'Dashboard',           'link' => 'index.php',               'icon' => 'fas fa-tachometer-alt'],
        ['title' => 'Projects',            'link' => 'manage_projects.php',     'icon' => 'fas fa-tasks'],
        ['title' => 'Tasks',               'link' => 'tasks.php',               'icon' => 'fas fa-check-square'],
        ['title' => 'Daily Reports',       'link' => 'dpr.php',                 'icon' => 'fas fa-calendar-check'],
        ['title' => 'Entertainment',       'link' => 'entertainment.php',       'icon' => 'fas fa-gamepad'],
        ['title' => 'Settings',            'link' => 'profile.php',             'icon' => 'fas fa-cog'],
    ],
    'client' => [
        ['title' => 'Dashboard',           'link' => 'index.php',               'icon' => 'fas fa-tachometer-alt'],
        ['title' => 'My Projects',         'link' => 'client_projects.php',     'icon' => 'fas fa-tasks'],
        ['title' => 'Invoices',            'link' => 'invoices.php',            'icon' => 'fas fa-file-invoice'],
        ['title' => 'Messages',            'link' => 'messages.php',            'icon' => 'fas fa-envelope'],
        ['title' => 'Settings',            'link' => 'profile.php',             'icon' => 'fas fa-cog'],
    ],
    'hrm' => [ // NEW Student Role
        ['title' => 'Dashboard',           'link' => 'index.php',               'icon' => 'fas fa-tachometer-alt'],
        ['title' => 'HR Management',        'link' => 'manage_applications.php', 'icon' => 'fas fa-file-alt'],
        ['title' => 'Daily Reports',       'link' => 'dpr.php',                 'icon' => 'fas fa-calendar-check'],
        ['title' => 'Entertainment',       'link' => 'entertainment.php',       'icon' => 'fas fa-gamepad'],
        ['title' => 'Settings',            'link' => 'profile.php',             'icon' => 'fas fa-cog'],
    ],
    'pm' => [
        ['title' => 'Dashboard',           'link' => 'index.php',               'icon' => 'fas fa-tachometer-alt'],
        ['title' => 'Projects',            'link' => 'manage_projects.php',     'icon' => 'fas fa-tasks'],
        ['title' => 'Tasks',               'link' => 'tasks.php',               'icon' => 'fas fa-check-square'],
        ['title' => 'Project Requests',    'link' => 'project_requests.php',    'icon' => 'fas fa-paper-plane'],
        ['title' => 'Clients',             'link' => 'manage_clients.php',      'icon' => 'fas fa-user-tie'],
        ['title' => 'Daily Reports',       'link' => 'dpr.php',                 'icon' => 'fas fa-calendar-check'],
        ['title' => 'Settings',            'link' => 'profile.php',             'icon' => 'fas fa-cog'],
    ],
];

// ── Capture notifications HTML ────────────────────────────────────────────────
ob_start();
include('notifications.php');
$notifications_html = ob_get_clean();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1, shrink-to-fit=no'>
    <title>Graphicafix - Dashboard</title>
    <link href="<?= BASE_URL ?>css/bootstrap.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" referrerpolicy="no-referrer" />
    <link rel="icon" type="image/png" href="assets/images/icon.png">
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css' rel='stylesheet'>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js'></script>
    <link href="<?= BASE_URL ?>css/dashboard.css" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

    <style>
        /* ── Mobile sidebar ── */
        @media (max-width: 767px) {
            body, body.body-pd                { padding-left: 1rem !important; margin-left: 0 !important; }
            #header, #header.body-pd          { left: 0 !important; width: 100% !important; padding-left: 1rem !important; padding-right: 1rem !important; }
            #nav-bar                          { left: -100% !important; width: 240px !important; z-index: 200 !important; transition: left 0.3s ease !important; }
            #nav-bar.show                     { left: 0 !important; }
            #navBackdrop                      { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 150; }
            #navBackdrop.visible              { display: block; }
        }

        /* ── Header right ── */
        .header-right { display: flex; align-items: center; gap: 8px; }

        /* ── Profile button ── */
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

        /* ── Dropdown panel ── */
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
                        <div class="header-dropdown-role"><?= htmlspecialchars(ucfirst($role ?? 'Member')) ?></div>
                    </div>
                </div>
                <div class="header-dropdown-divider"></div>
                <a class="header-dropdown-item" href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                <a class="header-dropdown-item" href="login_history.php"><i class="fas fa-clock"></i> Login History</a>
                <div class="header-dropdown-divider"></div>
                <a class="header-dropdown-item header-dropdown-logout" href="logout.php">🚪 Logout</a>
            </div>
        </div>

    </div>
</div>

<div class="l-navbar" id="nav-bar">
    <nav class="nav">
        <div>
            <a href="#" class="nav_logo">
                <span class="nav_logo-name">
                    <img src="<?= BASE_URL ?>images/logo-secondary.png" width='100px'>
                </span>
            </a>
            <div class="nav_list">
                <?php foreach ($menus[$role] ?? [] as $menu):
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