<?php
// ── Prevent caching ───────────────────────────────────────────────────────────
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../core/config.php';
// ── Auth check ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// ── Fetch user ────────────────────────────────────────────────────────────────
$username = $_SESSION['username'];
$result   = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
$data     = mysqli_fetch_assoc($result);

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
$user_id = $data['user_id'];
$role    = strtolower($data['role']); // admin | user | client | teacher | student
$_SESSION['role'] = $role;

$can_edit = ($role === 'admin' || $role === 'pm' || $role === 'manager');
$can_create_project = ($role === 'admin' || $role === 'pm');

// Auto-patch notices table schema
$existing_not_cols = @array_column(
    @$conn->query("SHOW COLUMNS FROM notices")->fetch_all(MYSQLI_ASSOC),
    'Field'
);
if ($existing_not_cols && !in_array('created_by', $existing_not_cols)) {
    @$conn->query("ALTER TABLE notices ADD COLUMN created_by VARCHAR(100) DEFAULT 'Admin'");
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
            if (isset($_GET['action']) && $_GET['action'] === 'add' && in_array($link_file, ['manage_users.php', 'manage_clients.php', 'manage_leads.php'])) {
                return false;
            }
            if (isset($_GET['add_user']) && $_GET['add_user'] === '1' && $link_file === 'manage_users.php') {
                return false;
            }
            if (isset($_GET['add_client']) && $_GET['add_client'] === '1' && $link_file === 'manage_clients.php') {
                return false;
            }
            return true;
        }
    }
}

// ── Role-based menus ──────────────────────────────────────────────────────────
$menus = [
    'admin' => [
        [
            'title' => 'Dashboard',
            'link'  => 'index.php',
            'icon'  => 'fas fa-tachometer-alt',
            'submenus' => [
                ['title' => 'Overview',         'link' => 'index.php',               'icon' => 'fas fa-home'],
                ['title' => 'Daily Reports',    'link' => 'dpr.php',                 'icon' => 'fas fa-calendar-check'],
                ['title' => 'Notice Board',     'link' => 'manage_notices.php',      'icon' => 'fas fa-bullhorn'],
                ['title' => 'Notifications',    'link' => 'manage_notifications.php', 'icon' => 'fas fa-bell'],
                ['title' => 'Attendance',       'link' => 'attendance.php',          'icon' => 'fas fa-clock'],
            ]
        ],
        [
            'title' => 'Users & Staff',
            'link'  => '#',
            'icon'  => 'fas fa-users',
            'submenus' => [
                ['title' => 'Manage Users',     'link' => 'manage_users.php',        'icon' => 'fas fa-user-cog'],
                ['title' => 'Add User',         'link' => 'manage_users.php?action=add', 'icon' => 'fas fa-user-plus'],
                ['title' => 'Login History',    'link' => 'login_history.php',       'icon' => 'fas fa-history'],
            ]
        ],
        [
            'title' => 'Projects Management',
            'link'  => '#',
            'icon'  => 'fas fa-tasks',
            'submenus' => [
                ['title' => 'Manage Projects',  'link' => 'manage_projects.php',     'icon' => 'fas fa-folder-open'],
                ['title' => 'Add New Project',  'link' => 'project_add.php',         'icon' => 'fas fa-folder-plus'],
                ['title' => 'Project Requests', 'link' => 'project_requests.php',    'icon' => 'fas fa-paper-plane'],
            ]
        ],
        [
            'title' => 'Tasks & Workflow',
            'link'  => '#',
            'icon'  => 'fas fa-check-square',
            'submenus' => [
                ['title' => 'Task Board',       'link' => 'tasks.php',               'icon' => 'fas fa-clipboard-list'],
                ['title' => 'Create New Task',  'link' => 'create_task.php',         'icon' => 'fas fa-plus-circle'],
            ]
        ],
        [
            'title' => 'Clients & Leads',
            'link'  => '#',
            'icon'  => 'fas fa-user-tie',
            'submenus' => [
                ['title' => 'Manage Clients',   'link' => 'manage_clients.php',      'icon' => 'fas fa-building'],
                ['title' => 'Add Client',       'link' => 'manage_clients.php?action=add', 'icon' => 'fas fa-plus-square'],
                ['title' => 'CRM & Leads',      'link' => 'manage_leads.php',        'icon' => 'fas fa-filter'],
                ['title' => 'Add Lead',         'link' => 'manage_leads.php?action=add', 'icon' => 'fas fa-user-tag'],
                ['title' => 'Client Reviews',   'link' => 'reviews.php',             'icon' => 'fas fa-star'],
            ]
        ],
        [
            'title' => 'Invoices & Quotes',
            'link'  => '#',
            'icon'  => 'fas fa-file-invoice-dollar',
            'submenus' => [
                ['title' => 'Manage Invoices',  'link' => 'manage_invoices.php',     'icon' => 'fas fa-file-invoice'],
                ['title' => 'Create Invoice',   'link' => 'create_invoice.php',      'icon' => 'fas fa-plus-circle'],
                ['title' => 'Create Quotation', 'link' => 'create_quotation.php',    'icon' => 'fas fa-file-signature'],
                ['title' => 'Bank Accounts',    'link' => 'manage_bank_accounts.php', 'icon' => 'fas fa-university'],
            ]
        ],
        [
            'title' => 'Services Catalog',
            'link'  => '#',
            'icon'  => 'fas fa-cogs',
            'submenus' => [
                ['title' => 'Manage Services',  'link' => 'manage_services.php',     'icon' => 'fas fa-concierge-bell'],
                ['title' => 'Add Service',      'link' => 'service_add.php',         'icon' => 'fas fa-plus-circle'],
            ]
        ],
        [
            'title' => 'HR Management',
            'link'  => '#',
            'icon'  => 'fas fa-briefcase',
            'submenus' => [
                ['title' => 'Job Applications', 'link' => 'manage_applications.php', 'icon' => 'fas fa-file-alt'],
                ['title' => 'Staff Attendance', 'link' => 'attendance.php',          'icon' => 'fas fa-user-clock'],
                ['title' => 'Teacher Documents','link' => 'admin_teacher_docs.php',  'icon' => 'fas fa-folder-open'],
            ]
        ],
        [
            'title' => 'LMS & Courses',
            'link'  => '#',
            'icon'  => 'fas fa-graduation-cap',
            'submenus' => [
                ['title' => 'Manage Courses',   'link' => 'manage_courses.php',      'icon' => 'fas fa-book-reader'],
                ['title' => 'Recorded Lectures','link' => 'admin_lectures.php',      'icon' => 'fas fa-video'],
                ['title' => 'LMS Portal',       'link' => '../portal/index.php',     'icon' => 'fas fa-external-link-alt'],
            ]
        ],
        [
            'title' => 'Content & Blogs',
            'link'  => '#',
            'icon'  => 'fas fa-blog',
            'submenus' => [
                ['title' => 'Manage Blogs',     'link' => 'manage_blogs.php',        'icon' => 'fas fa-newspaper'],
                ['title' => 'Create Blog',      'link' => 'create_blog.php',         'icon' => 'fas fa-plus-circle'],
                ['title' => 'Newsletter',       'link' => 'newsletter.php',          'icon' => 'fas fa-envelope-open-text'],
                ['title' => 'Correspondence',   'link' => 'correspondence.php',      'icon' => 'fas fa-envelope'],
            ]
        ],
        [
            'title' => 'Entertainment Hub',
            'link'  => '#',
            'icon'  => 'fas fa-gamepad',
            'submenus' => [
                ['title' => 'Entertainment',    'link' => 'entertainment.php',       'icon' => 'fas fa-dice'],
                ['title' => 'Visitor Widget',   'link' => 'visitor_widget.php',      'icon' => 'fas fa-chart-line'],
            ]
        ],
        [
            'title' => 'Settings',
            'link'  => 'profile.php',
            'icon'  => 'fas fa-cog',
            'submenus' => [
                ['title' => 'My Profile',       'link' => 'profile.php',             'icon' => 'fas fa-user-circle'],
                ['title' => 'Login History',    'link' => 'login_history.php',       'icon' => 'fas fa-shield-alt'],
            ]
        ],
    ],
    'user' => [ // Standard Staff
        [
            'title' => 'Dashboard',
            'link'  => 'index.php',
            'icon'  => 'fas fa-tachometer-alt',
            'submenus' => [
                ['title' => 'Overview',         'link' => 'index.php',               'icon' => 'fas fa-home'],
                ['title' => 'Daily Reports',    'link' => 'dpr.php',                 'icon' => 'fas fa-calendar-check'],
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
            'title' => 'Entertainment Hub',
            'link'  => '#',
            'icon'  => 'fas fa-gamepad',
            'submenus' => [
                ['title' => 'Entertainment',    'link' => 'entertainment.php',       'icon' => 'fas fa-dice'],
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
    ],
    'hrm' => [ // Student / HR Role
        [
            'title' => 'Dashboard',
            'link'  => 'index.php',
            'icon'  => 'fas fa-tachometer-alt',
            'submenus' => [
                ['title' => 'Overview',         'link' => 'index.php',               'icon' => 'fas fa-home'],
                ['title' => 'Daily Reports',    'link' => 'dpr.php',                 'icon' => 'fas fa-calendar-check'],
            ]
        ],
        [
            'title' => 'HR Management',
            'link'  => '#',
            'icon'  => 'fas fa-briefcase',
            'submenus' => [
                ['title' => 'Job Applications', 'link' => 'manage_applications.php', 'icon' => 'fas fa-file-alt'],
                ['title' => 'Staff Attendance', 'link' => 'attendance.php',          'icon' => 'fas fa-user-clock'],
            ]
        ],
        [
            'title' => 'Entertainment Hub',
            'link'  => '#',
            'icon'  => 'fas fa-gamepad',
            'submenus' => [
                ['title' => 'Entertainment',    'link' => 'entertainment.php',       'icon' => 'fas fa-dice'],
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
    'pm' => [
        [
            'title' => 'Dashboard',
            'link'  => 'index.php',
            'icon'  => 'fas fa-tachometer-alt',
            'submenus' => [
                ['title' => 'Overview',         'link' => 'index.php',               'icon' => 'fas fa-home'],
                ['title' => 'Daily Reports',    'link' => 'dpr.php',                 'icon' => 'fas fa-calendar-check'],
            ]
        ],
        [
            'title' => 'Projects Management',
            'link'  => '#',
            'icon'  => 'fas fa-tasks',
            'submenus' => [
                ['title' => 'Manage Projects',  'link' => 'manage_projects.php',     'icon' => 'fas fa-folder-open'],
                ['title' => 'Project Requests', 'link' => 'project_requests.php',    'icon' => 'fas fa-paper-plane'],
            ]
        ],
        [
            'title' => 'Tasks & Workflow',
            'link'  => '#',
            'icon'  => 'fas fa-check-square',
            'submenus' => [
                ['title' => 'Task Board',       'link' => 'tasks.php',               'icon' => 'fas fa-clipboard-list'],
                ['title' => 'Create New Task',  'link' => 'create_task.php',         'icon' => 'fas fa-plus-circle'],
            ]
        ],
        [
            'title' => 'Clients Management',
            'link'  => '#',
            'icon'  => 'fas fa-user-tie',
            'submenus' => [
                ['title' => 'Clients List',     'link' => 'manage_clients.php',      'icon' => 'fas fa-building'],
                ['title' => 'Client Reviews',   'link' => 'reviews.php',             'icon' => 'fas fa-star'],
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
    'manager' => [
        [
            'title' => 'Dashboard',
            'link'  => 'index.php',
            'icon'  => 'fas fa-tachometer-alt',
            'submenus' => [
                ['title' => 'Overview',         'link' => 'index.php',               'icon' => 'fas fa-home'],
                ['title' => 'Daily Reports',    'link' => 'dpr.php',                 'icon' => 'fas fa-calendar-check'],
                ['title' => 'Notice Board',     'link' => 'manage_notices.php',      'icon' => 'fas fa-bullhorn'],
                ['title' => 'Notifications',    'link' => 'manage_notifications.php', 'icon' => 'fas fa-bell'],
            ]
        ],
        [
            'title' => 'Projects Management',
            'link'  => '#',
            'icon'  => 'fas fa-tasks',
            'submenus' => [
                ['title' => 'Manage Projects',  'link' => 'manage_projects.php',     'icon' => 'fas fa-folder-open'],
                ['title' => 'Project Requests', 'link' => 'project_requests.php',    'icon' => 'fas fa-paper-plane'],
            ]
        ],
        [
            'title' => 'Tasks & Workflow',
            'link'  => '#',
            'icon'  => 'fas fa-check-square',
            'submenus' => [
                ['title' => 'Task Board',       'link' => 'tasks.php',               'icon' => 'fas fa-clipboard-list'],
                ['title' => 'Create New Task',  'link' => 'create_task.php',         'icon' => 'fas fa-plus-circle'],
            ]
        ],
        [
            'title' => 'Invoices',
            'link'  => '#',
            'icon'  => 'fas fa-file-invoice-dollar',
            'submenus' => [
                ['title' => 'Manage Invoices',  'link' => 'manage_invoices.php',     'icon' => 'fas fa-file-invoice'],
            ]
        ],
        [
            'title' => 'HR Management',
            'link'  => '#',
            'icon'  => 'fas fa-briefcase',
            'submenus' => [
                ['title' => 'Staff Attendance', 'link' => 'attendance.php',          'icon' => 'fas fa-user-clock'],
            ]
        ],
        [
            'title' => 'Content & Blogs',
            'link'  => '#',
            'icon'  => 'fas fa-blog',
            'submenus' => [
                ['title' => 'Manage Blogs',     'link' => 'manage_blogs.php',        'icon' => 'fas fa-newspaper'],
                ['title' => 'Create Blog',      'link' => 'create_blog.php',         'icon' => 'fas fa-plus-circle'],
                ['title' => 'Newsletter',       'link' => 'newsletter.php',          'icon' => 'fas fa-envelope-open-text'],
                ['title' => 'Correspondence',   'link' => 'correspondence.php',      'icon' => 'fas fa-envelope'],
            ]
        ],
        [
            'title' => 'Entertainment Hub',
            'link'  => '#',
            'icon'  => 'fas fa-gamepad',
            'submenus' => [
                ['title' => 'Entertainment',    'link' => 'entertainment.php',       'icon' => 'fas fa-dice'],
            ]
        ],
        [
            'title' => 'Settings',
            'link'  => 'profile.php',
            'icon'  => 'fas fa-cog',
            'submenus' => [
                ['title' => 'My Profile',       'link' => 'profile.php',             'icon' => 'fas fa-user-circle'],
                ['title' => 'Login History',    'link' => 'login_history.php',       'icon' => 'fas fa-shield-alt'],
            ]
        ],
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
    <link href="<?= BASE_URL ?>assets/css/bootstrap.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" referrerpolicy="no-referrer" />
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/icon.png">
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css' rel='stylesheet'>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js'></script>
    <link href="<?= BASE_URL ?>assets/css/dashboard.css?v=<?= time() ?>" rel="stylesheet">
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