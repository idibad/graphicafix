<?php
ob_start();
// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

require_once __DIR__ . '/../core/config.php';

// Redirect to login if not logged in
if(!isset($_SESSION['username'])){
    header("Location: login.php");
    exit;
}

// Fetch logged-in user details
$username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();





$name = $data['name'];
$phone = $data['phone'];
$email = $data['email'];
$user_id = $data['user_id'];
$role = strtolower($data['role'] ?? '');

if (in_array($role, ['client', 'client_sub'])) {
    header("Location: ../admin/client_projects.php");
    exit;
}
if ($role === 'student') {
    header("Location: ../portal/");
    exit;
}
if (!in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'manager', 'teacher', 'hrm', 'sales', 'user'])) {
    header("Location: ../admin/login.php?error=unauthorized");
    exit;
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

// Role-based menu definitions
$menus = [
    'admin' => [
        [
            'title' => 'Dashboard',       
            'link'  => 'dashboard.php',               
            'icon'  => 'fas fa-tachometer-alt',
            'submenus' => [
                ['title' => 'Overview',         'link' => 'dashboard.php',           'icon' => 'fas fa-home'],
            ]
        ],
        [
            'title' => 'Users & Staff',
            'link'  => '#',
            'icon'  => 'fas fa-users',
            'submenus' => [
                ['title' => 'Manage Users',     'link' => 'manage_users.php',        'icon' => 'fas fa-user-cog'],
                ['title' => 'Add User',         'link' => 'manage_users.php?action=add', 'icon' => 'fas fa-user-plus'],
            ]
        ],
        [
            'title' => 'Projects Management',
            'link'  => '#',
            'icon'  => 'fas fa-tasks',
            'submenus' => [
                ['title' => 'Manage Projects',  'link' => 'manage_projects.php',     'icon' => 'fas fa-folder-open'],
            ]
        ],
        [
            'title' => 'Tasks & Workflow',
            'link'  => '#',
            'icon'  => 'fas fa-check-square',
            'submenus' => [
                ['title' => 'Task Board',       'link' => 'tasks.php',               'icon' => 'fas fa-clipboard-list'],
            ]
        ],
        [
            'title' => 'Clients Management',
            'link'  => '#',
            'icon'  => 'fas fa-user-tie',
            'submenus' => [
                ['title' => 'Manage Clients',   'link' => 'manage_clients.php',      'icon' => 'fas fa-building'],
                ['title' => 'Client Reviews',   'link' => 'reviews.php',             'icon' => 'fas fa-star'],
            ]
        ],
        [
            'title' => 'Services Catalog',
            'link'  => '#',
            'icon'  => 'fas fa-cogs',
            'submenus' => [
                ['title' => 'Manage Services',  'link' => 'manage_services.php',     'icon' => 'fas fa-concierge-bell'],
            ]
        ],
        [
            'title' => 'HR Management',
            'link'  => '#',
            'icon'  => 'fas fa-briefcase',
            'submenus' => [
                ['title' => 'Applications',     'link' => 'manage_applications.php', 'icon' => 'fas fa-file-alt'],
            ]
        ],
        [
            'title' => 'Messages',        
            'link'  => '#',             
            'icon'  => 'fas fa-envelope',
            'submenus' => [
                ['title' => 'Inbox',            'link' => 'messages.php',            'icon' => 'fas fa-comments'],
            ]
        ],
        [
            'title' => 'Settings',        
            'link'  => 'profile.php',             
            'icon'  => 'fas fa-cog',
            'submenus' => [
                ['title' => 'My Profile',       'link' => 'profile.php',             'icon' => 'fas fa-user-circle'],
            ]
        ],
    ],
    'user' => [
        [
            'title' => 'Dashboard',       
            'link'  => 'dashboard.php',               
            'icon'  => 'fas fa-tachometer-alt',
            'submenus' => [
                ['title' => 'Overview',         'link' => 'dashboard.php',           'icon' => 'fas fa-home'],
            ]
        ],
        [
            'title' => 'Projects Management',
            'link'  => '#',
            'icon'  => 'fas fa-tasks',
            'submenus' => [
                ['title' => 'My Projects',      'link' => 'manage_projects.php',     'icon' => 'fas fa-folder-open'],
            ]
        ],
        [
            'title' => 'Tasks & Workflow',
            'link'  => '#',
            'icon'  => 'fas fa-check-square',
            'submenus' => [
                ['title' => 'My Tasks',         'link' => 'tasks.php',               'icon' => 'fas fa-clipboard-list'],
            ]
        ],
        [
            'title' => 'Messages',        
            'link'  => '#',             
            'icon'  => 'fas fa-envelope',
            'submenus' => [
                ['title' => 'Inbox',            'link' => 'messages.php',            'icon' => 'fas fa-comments'],
            ]
        ],
        [
            'title' => 'Settings',        
            'link'  => 'profile.php',             
            'icon'  => 'fas fa-cog',
            'submenus' => [
                ['title' => 'My Profile',       'link' => 'profile.php',             'icon' => 'fas fa-user-circle'],
            ]
        ],
    ],
    'client' => [
        [
            'title' => 'Dashboard',
            'link'  => 'index.php',
            'icon'  => 'fas fa-tachometer-alt',
            'submenus' => [
                ['title' => 'Overview',         'link' => 'index.php',               'icon' => 'fas fa-home'],
            ]
        ],
        [
            'title' => 'My Projects',
            'link'  => '#',
            'icon'  => 'fas fa-tasks',
            'submenus' => [
                ['title' => 'Active Projects',  'link' => 'client_projects.php',     'icon' => 'fas fa-folder-open'],
            ]
        ],
        [
            'title' => 'Billing & Invoices',
            'link'  => '#',
            'icon'  => 'fas fa-file-invoice',
            'submenus' => [
                ['title' => 'My Invoices',      'link' => 'client_invoices.php',     'icon' => 'fas fa-file-invoice-dollar'],
            ]
        ],
        [
            'title' => 'Client Users',
            'link'  => '#',
            'icon'  => 'fas fa-users',
            'submenus' => [
                ['title' => 'Manage Users',     'link' => 'client_users.php',        'icon' => 'fas fa-user-friends'],
            ]
        ],
        [
            'title' => 'Settings',
            'link'  => '#',
            'icon'  => 'fas fa-cog',
            'submenus' => [
                ['title' => 'My Profile',       'link' => 'profile.php',             'icon' => 'fas fa-user-circle'],
                ['title' => 'Company Info',     'link' => 'client_info.php',         'icon' => 'fas fa-building'],
            ]
        ],
    ],
    'client_sub' => [
        [
            'title' => 'Dashboard',
            'link'  => 'index.php',
            'icon'  => 'fas fa-tachometer-alt',
            'submenus' => [
                ['title' => 'Overview',         'link' => 'index.php',               'icon' => 'fas fa-home'],
            ]
        ],
        [
            'title' => 'My Projects',
            'link'  => '#',
            'icon'  => 'fas fa-tasks',
            'submenus' => [
                ['title' => 'Active Projects',  'link' => 'client_projects.php',     'icon' => 'fas fa-folder-open'],
            ]
        ],
        [
            'title' => 'Settings',
            'link'  => '#',
            'icon'  => 'fas fa-cog',
            'submenus' => [
                ['title' => 'My Profile',       'link' => 'profile.php',             'icon' => 'fas fa-user-circle'],
            ]
        ],
    ]
];

if ($role !== 'admin') {
    $can_edit = false; // used in your HTML/UI
} else {
    $can_edit = true;

}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1, shrink-to-fit=no'>
    <title>Graphicafix - Dashboard</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/all.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/icon.png">
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css' rel='stylesheet'>
    <script type='text/javascript' src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js'></script>
    <link href="<?= BASE_URL ?>assets/css/dashboard.css" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
</head>

<body id="body-pd">

<!-- Backdrop overlay (mobile only) -->
<div class="nav-backdrop" id="navBackdrop"></div>

<!-- Header -->
<div class="header mb-5" id="header">
    <div class="header_toggle">
        <i class='bx bx-menu' id="header-toggle"></i>
    </div>
    <div class="dropdown" style="width: 200px;">
        <div class="header-menu dropdown-toggle" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <div class="profile-icon">
                <span><?php echo strtoupper(substr($name, 0, 1)); ?></span>
            </div>
            <div><?php echo $name; ?></div>
        </div>
        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
            <a class="dropdown-item" href="profile.php">Profile</a>
            <a class="dropdown-item" href="login_history.php">Login History</a>
            <a class="dropdown-item" href="logout.php">Logout</a>
        </div>
    </div>
</div>

<!-- Sidebar -->
<!-- NOTE: removed hardcoded 'show' — JS now controls it based on screen size -->
<div class="l-navbar" id="nav-bar">
    <nav class="nav">
        <div>
            <a href="#" class="nav_logo">
                <img src="<?= BASE_URL ?>assets/images/icon.png" class="nav_logo-icon-img" width="32px" alt="G">
                <span class="nav_logo-name">
                    <img src="<?= BASE_URL ?>assets/images/logo-secondary.png" width='100px'>
                </span>
            </a>
            <div class="nav_list">
                <?php foreach ($menus[$role] ?? [] as $menu):
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
    var group  = el.parentElement;
    if (navbar && !navbar.classList.contains('show')) {
        navbar.classList.add('show');
        var header = document.getElementById('header');
        var bodyEl = document.getElementById('body-pd');
        if (header) header.classList.add('body-pd');
        if (bodyEl) bodyEl.classList.add('body-pd');
    }
    group.classList.toggle('open');
}

(function () {
    const toggle   = document.getElementById('header-toggle');
    const navbar   = document.getElementById('nav-bar');
    const header   = document.getElementById('header');
    const bodyEl   = document.getElementById('body-pd');
    const backdrop = document.getElementById('navBackdrop');

    function isMobile() {
        return window.innerWidth < 768;
    }

    function openSidebar() {
        navbar.classList.add('show');
        if (isMobile()) {
            backdrop.classList.add('visible');
            document.body.style.overflow = 'hidden';
        } else {
            header.classList.add('body-pd');
            bodyEl.classList.add('body-pd');
        }
    }

    function closeSidebar() {
        navbar.classList.remove('show');
        backdrop.classList.remove('visible');
        document.body.style.overflow = '';
        // On desktop keep body-pd when closing (sidebar stays narrow, not hidden)
        // only remove if explicitly collapsed on desktop — keep as-is
    }

    function toggleSidebar() {
        navbar.classList.contains('show') ? closeSidebar() : openSidebar();
    }

    // ── Init: open on desktop, closed on mobile ───────────────────────────
    if (isMobile()) {
        navbar.classList.remove('show');
        header.classList.remove('body-pd');
        bodyEl.classList.remove('body-pd');
    } else {
        navbar.classList.add('show');
        header.classList.add('body-pd');
        bodyEl.classList.add('body-pd');
    }

    toggle.addEventListener('click', toggleSidebar);

    // Close when backdrop is tapped on mobile
    backdrop.addEventListener('click', closeSidebar);

    // On resize — re-initialise so switching desktop↔mobile works cleanly
    window.addEventListener('resize', function () {
        if (!isMobile()) {
            // Going to desktop — ensure sidebar is open, backdrop hidden
            backdrop.classList.remove('visible');
            document.body.style.overflow = '';
            navbar.classList.add('show');
            header.classList.add('body-pd');
            bodyEl.classList.add('body-pd');
        } else {
            // Going to mobile — close sidebar, remove layout padding
            navbar.classList.remove('show');
            backdrop.classList.remove('visible');
            header.classList.remove('body-pd');
            bodyEl.classList.remove('body-pd');
            document.body.style.overflow = '';
        }
    });
})();
</script>
