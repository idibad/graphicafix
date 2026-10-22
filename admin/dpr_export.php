<?php
/**
 * dpr_export.php
 * Corporate Standard Daily Progress Report (DPR) Export Engine
 * Generates ISO-compliant CSV spreadsheets and executive letterhead PDF dossiers
 */

ob_start();
session_start();
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/dpr_schema.php';
ensure_dpr_schema($conn);

// Auth check
if (!isset($_SESSION['user_id'])) {
    die('Access Denied. Please log in to export reports.');
}

$user_id = intval($_SESSION['user_id']);
$user_res = $conn->query("SELECT role, name, email FROM users WHERE user_id = $user_id");
$current_user = $user_res ? $user_res->fetch_assoc() : null;
if (!$current_user) {
    die('User account not found.');
}

$role = strtolower($current_user['role'] ?? ($_SESSION['role'] ?? 'user'));
$is_manager = in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'hrm', 'manager']);

// Export filters
$format          = strtolower($_GET['format'] ?? 'csv'); // csv | pdf
$single_id       = intval($_GET['id'] ?? 0);
$filter_user     = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? intval($_GET['user_id']) : 0;
$filter_project  = isset($_GET['project_id']) && $_GET['project_id'] !== '' ? intval($_GET['project_id']) : 0;
$filter_severity = trim($_GET['severity'] ?? '');
$date            = trim($_GET['date'] ?? '');
$start_date      = trim($_GET['start_date'] ?? '');
$end_date        = trim($_GET['end_date'] ?? '');
$has_blockers    = !empty($_GET['has_blockers']);
$search          = trim($_GET['q'] ?? '');

// Build query
$where = "1=1";
$params = [];
$types = "";

// Security: non-managers can ONLY export their own reports
if (!$is_manager) {
    $where .= " AND dpr.user_id = ?";
    $params[] = $user_id;
    $types .= "i";
} else {
    if ($filter_user > 0) {
        $where .= " AND dpr.user_id = ?";
        $params[] = $filter_user;
        $types .= "i";
    }
}

if ($single_id > 0) {
    $where .= " AND dpr.id = ?";
    $params[] = $single_id;
    $types .= "i";
}

if ($filter_project > 0) {
    $where .= " AND dpr.project_id = ?";
    $params[] = $filter_project;
    $types .= "i";
}

if (!empty($filter_severity)) {
    $where .= " AND dpr.blocker_severity = ?";
    $params[] = $filter_severity;
    $types .= "s";
}

if (!empty($date)) {
    $where .= " AND dpr.report_date = ?";
    $params[] = $date;
    $types .= "s";
} else {
    if (!empty($start_date)) {
        $where .= " AND dpr.report_date >= ?";
        $params[] = $start_date;
        $types .= "s";
    }
    if (!empty($end_date)) {
        $where .= " AND dpr.report_date <= ?";
        $params[] = $end_date;
        $types .= "s";
    }
}

if ($has_blockers) {
    $where .= " AND (dpr.blocker_severity IN ('minor', 'critical') OR (dpr.challenges IS NOT NULL AND TRIM(dpr.challenges) != ''))";
}

if (!empty($search)) {
    $where .= " AND (dpr.tasks_completed LIKE ? OR dpr.tasks_in_progress LIKE ? OR dpr.challenges LIKE ? OR dpr.waiting_on LIKE ? OR dpr.plan_tomorrow LIKE ? OR u.name LIKE ? OR p.project_name LIKE ?)";
    $like_search = "%$search%";
    for ($i = 0; $i < 7; $i++) {
        $params[] = $like_search;
        $types .= "s";
    }
}

$query = "
    SELECT dpr.*, u.name as user_name, u.role as user_role, u.email as user_email, p.project_name
    FROM daily_progress_reports dpr
    JOIN users u ON dpr.user_id = u.user_id
    LEFT JOIN projects p ON dpr.project_id = p.id
    WHERE $where
    ORDER BY dpr.report_date DESC, dpr.created_at DESC
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$reports = [];
$roadblocks_count = 0;
$critical_count = 0;
$total_hours = 0.0;
$unique_members = [];

while ($row = $result->fetch_assoc()) {
    $reports[] = $row;
    $severity = $row['blocker_severity'] ?? 'none';
    if ($severity === 'critical') {
        $critical_count++;
        $roadblocks_count++;
    } elseif ($severity === 'minor' || !empty(trim($row['challenges'] ?? ''))) {
        $roadblocks_count++;
    }
    $total_hours += floatval($row['hours_worked'] ?? 8.0);
    $unique_members[$row['user_id']] = true;
}

// Clear any accidental output buffer before sending file
ob_end_clean();

// ─────────────────────────────────────────────────────────────────────────────
// FORMAT 1: CSV SPREADSHEET EXPORT (CORPORATE STANDARD)
// ─────────────────────────────────────────────────────────────────────────────
if ($format === 'csv') {
    $filename_suffix = date('Y-m-d');
    if ($single_id > 0 && count($reports) === 1) {
        $clean_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $reports[0]['user_name']);
        $filename = "GFX_DPR_Report_" . $clean_name . "_" . $reports[0]['report_date'] . ".csv";
    } elseif (!empty($date)) {
        $filename = "GFX_DPR_Report_" . $date . ".csv";
    } elseif (!empty($start_date) && !empty($end_date)) {
        $filename = "GFX_DPR_Summary_" . $start_date . "_to_" . $end_date . ".csv";
    } else {
        $filename = "GFX_DPR_Report_" . $filename_suffix . ".csv";
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM so Microsoft Excel renders unicode characters and bullets correctly
    fwrite($output, "\xEF\xBB\xBF");

    // Corporate Header row
    fputcsv($output, [
        'DPR Reference ID',
        'Report Date',
        'Day of Week',
        'Employee Name',
        'Designation / Role',
        'Official Email',
        'Project Engagement',
        'Hours Worked',
        'Productivity Score (1-5)',
        'Key Accomplishments & Deliverables',
        'Work In Progress (Ongoing Tasks)',
        'Obstacle Severity',
        'Roadblocks & Impediments',
        'Dependencies (Waiting On)',
        'Deliverables & Work Proof Links',
        'Tomorrow\'s Action Plan',
        'Submission Timestamp'
    ]);

    foreach ($reports as $r) {
        $day_name = date('l', strtotime($r['report_date']));
        $formatted_date = date('Y-m-d', strtotime($r['report_date']));
        $ref_id = 'GFX-DPR-' . date('Ymd', strtotime($r['report_date'])) . '-' . str_pad($r['id'], 4, '0', STR_PAD_LEFT);
        $challenges_text = trim($r['challenges'] ?? '');
        if (empty($challenges_text)) {
            $challenges_text = 'None (Operating Smoothly)';
        }

        fputcsv($output, [
            $ref_id,
            $formatted_date,
            $day_name,
            $r['user_name'] ?? 'Unknown',
            ucfirst($r['user_role'] ?? 'User'),
            $r['user_email'] ?? '',
            $r['project_name'] ?? 'General / Internal Work',
            number_format($r['hours_worked'] ?? 8, 1),
            intval($r['productivity_rating'] ?? 5),
            $r['tasks_completed'],
            $r['tasks_in_progress'] ?? '',
            ucfirst($r['blocker_severity'] ?? 'none'),
            $challenges_text,
            $r['waiting_on'] ?? '',
            $r['deliverable_links'] ?? '',
            $r['plan_tomorrow'],
            date('Y-m-d H:i:s', strtotime($r['created_at']))
        ]);
    }

    fclose($output);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// FORMAT 2: BRANDED EXECUTIVE PDF EXPORT (OFFICIAL LETTERHEAD)
// ─────────────────────────────────────────────────────────────────────────────
if ($format === 'pdf') {
    // Suppress notices during PDF generation
    error_reporting(0);
    ini_set('display_errors', '0');

    require_once __DIR__ . '/../vendor/autoload.php';

    // Helper: Convert multiline or bulleted text into a clean, compact bullet table for mPDF
    function renderPdfList($text) {
        $lines = preg_split('/\r\n|\r|\n/', trim($text ?? ''));
        $items = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // Strip leading bullet markers if present
            $cleaned = preg_replace('/^(\*|\-|\•|\d+\.)\s*/u', '', $line);
            if ($cleaned !== '') {
                $items[] = htmlspecialchars($cleaned);
            }
        }
        if (empty($items)) {
            return '<div style="color:#64748b; font-style:italic; font-size:7.5pt; padding:2px 0;">No details recorded.</div>';
        }
        $html = '<table class="bullet-table">';
        foreach ($items as $it) {
            $html .= '<tr><td class="bullet-dot">&#8226;</td><td class="bullet-text">' . $it . '</td></tr>';
        }
        $html .= '</table>';
        return $html;
    }

    $is_single = ($single_id > 0 && count($reports) === 1);
    $single_report = $is_single ? $reports[0] : null;

    // Period label calculation
    $period_label = 'All Time Records';
    if (!empty($date)) {
        $period_label = date('l, F j, Y', strtotime($date));
    } elseif (!empty($start_date) && !empty($end_date)) {
        $period_label = date('M j, Y', strtotime($start_date)) . ' – ' . date('M j, Y', strtotime($end_date));
    } elseif (!empty($start_date)) {
        $period_label = 'From ' . date('M j, Y', strtotime($start_date));
    } elseif (!empty($end_date)) {
        $period_label = 'Until ' . date('M j, Y', strtotime($end_date));
    }

    $pdf_title = $is_single 
        ? "Daily Progress Report - " . ($single_report['user_name'] ?? 'Staff') . " (" . date('M j, Y', strtotime($single_report['report_date'])) . ")"
        : "Graphicafix Daily Progress Reports Summary";

    // Setup mPDF with letterhead margin spacing
    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_top'    => 35,    // Edge space for letterhead header image
        'margin_bottom' => 26,    // Edge space for letterhead footer image
        'margin_left'   => 0,
        'margin_right'  => 0,
        'margin_header' => 0,
        'margin_footer' => 0,
        'default_font'  => 'dejavusans'
    ]);

    $mpdf->SetTitle($pdf_title);
    $mpdf->SetAuthor('Graphicafix Operations Management');

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'dejavusans', sans-serif;
            color: #1e293b;
            font-size: 8.5pt;
            line-height: 1.45;
            margin: 0;
            padding: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            vertical-align: top;
        }

        /* ── Page Layout Wrapper ── */
        .content-body {
            padding: 0 18mm 8mm 18mm;
        }

        /* ── Document Top Header ── */
        .header-table {
            width: 100%;
            margin-bottom: 8px;
            border-bottom: 2px solid #024442;
            padding-bottom: 5px;
        }
        .doc-badge {
            font-size: 7pt;
            font-weight: 800;
            color: #024442;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            margin-bottom: 1px;
        }
        .doc-title {
            font-size: 15pt;
            font-weight: 800;
            color: #024442;
            letter-spacing: -0.3px;
            margin: 0;
            line-height: 1.1;
            text-transform: uppercase;
        }
        .doc-sub {
            font-size: 7.5pt;
            color: #64748b;
            margin-top: 2px;
        }
        .date-badge-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-left: 3.5px solid #024442;
            padding: 4px 10px;
            display: inline-block;
            text-align: right;
            border-radius: 3px;
        }
        .date-badge-label {
            font-size: 6.5pt;
            font-weight: 800;
            color: #64748b;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .date-badge-val {
            font-size: 9pt;
            font-weight: 800;
            color: #024442;
        }

        /* ── Overview Metadata Grid ── */
        .overview-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            overflow: hidden;
        }
        .overview-table td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 8pt;
        }
        .ov-label {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 7pt;
            letter-spacing: 0.4px;
        }
        .ov-val {
            color: #0f172a;
            font-size: 8pt;
        }
        .ov-sub {
            font-size: 7pt;
            color: #64748b;
        }
        .role-pill {
            display: inline-block;
            background: #e2e8f0;
            color: #334155;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 6.5pt;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* ── Content Sections ── */
        .section-wrap {
            margin-bottom: 8px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 8pt;
            font-weight: 800;
            color: #024442;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding-bottom: 2px;
            border-bottom: 1px solid #cbd5e1;
            margin-bottom: 4px;
        }
        .section-body {
            padding-left: 2px;
        }

        /* ── Bullet List Table ── */
        .bullet-table {
            width: 100%;
            border-collapse: collapse;
        }
        .bullet-table td {
            padding: 1.5px 0;
            vertical-align: top;
            border: none !important;
        }
        .bullet-dot {
            width: 12px;
            font-size: 10pt;
            line-height: 1.2;
            color: #024442;
            font-weight: bold;
        }
        .bullet-text {
            font-size: 8pt;
            line-height: 1.4;
            color: #1e293b;
        }

        /* ── Roadblock Alert & Success Boxes ── */
        .note-box {
            border-radius: 4px;
            padding: 5px 8px;
            font-size: 8pt;
            line-height: 1.35;
        }
        .note-box-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        .note-box-alert {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        /* ── Sign-off Table ── */
        .sig-table {
            width: 100%;
            margin-top: 10px;
            page-break-inside: avoid;
        }
        .sig-title {
            font-size: 7pt;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 2px;
        }
        .sig-line {
            border-bottom: 1px solid #94a3b8;
            height: 20px;
            margin-bottom: 3px;
        }
        .sig-name {
            font-size: 8.5pt;
            font-weight: 800;
            color: #024442;
        }
        .sig-role {
            font-size: 7pt;
            color: #64748b;
        }
        .audit-footer {
            font-size: 6.5pt;
            color: #94a3b8;
            text-align: center;
            margin-top: 8px;
            padding-top: 3px;
            border-top: 1px dashed #e2e8f0;
        }

        /* ── Multi-Report Summary Styles ── */
        .hl-grid {
            width: 100%;
            margin-bottom: 12px;
        }
        .hl-card {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-top: 3px solid #024442;
            border-radius: 4px;
            padding: 6px 8px;
            text-align: center;
        }
        .hl-num {
            font-size: 11pt;
            font-weight: 800;
            color: #024442;
        }
        .hl-lbl {
            font-size: 6.5pt;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-top: 1px;
        }

        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 7.5pt;
        }
        .matrix-table th {
            background: #024442;
            color: #ffffff;
            font-weight: 800;
            text-transform: uppercase;
            padding: 5px 6px;
            border: 1px solid #024442;
            font-size: 7pt;
            letter-spacing: 0.4px;
        }
        .matrix-table td {
            padding: 5px 6px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .matrix-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        .entry-card {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            margin-bottom: 10px;
            page-break-inside: avoid;
            background: #ffffff;
            overflow: hidden;
        }
        .entry-header {
            background: #024442;
            color: #ffffff;
            padding: 5px 8px;
            width: 100%;
        }
        .entry-user-name {
            font-size: 9pt;
            font-weight: 800;
            color: #ffffff;
        }
        .entry-user-role {
            font-size: 7pt;
            color: #bbf7d0;
            margin-left: 4px;
        }
        .entry-date {
            font-size: 7.5pt;
            font-weight: 700;
            color: #ffffff;
            text-align: right;
        }
        .entry-body-table {
            width: 100%;
            border-collapse: collapse;
        }
        .entry-col-tasks {
            width: 55%;
            padding: 7px 9px;
            border-right: 1px solid #e2e8f0;
            font-size: 7.5pt;
        }
        .entry-col-side {
            width: 45%;
            padding: 7px 9px;
            background: #fafbfc;
            font-size: 7.5pt;
        }
        .entry-subheading {
            font-size: 7pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 3px;
        }
    </style>
    </head>
    <body>

        <!-- Running Letterhead Header and Footer Images -->
        <htmlpageheader name="pageHeader">
            <div style="width: 100%;">
                <img src="<?php echo __DIR__ . '/../assets/images/letter_header.png'; ?>" style="width: 100%; margin: 0; padding: 0;" />
            </div>
        </htmlpageheader>
        <sethtmlpageheader name="pageHeader" page="O" value="on" show-this-page="1" />

        <htmlpagefooter name="pageFooter">
            <div style="width: 100%; position: relative;">
                <img src="<?php echo __DIR__ . '/../assets/images/letter_footer.png'; ?>" style="width: 100%; margin: 0; padding: 0;" />
                <div style="position: absolute; bottom: 8mm; right: 18mm; font-size: 7.5pt; color: #64748b; font-family: 'dejavusans', sans-serif;">
                    Page {PAGENO} of {nbpg}
                </div>
            </div>
        </htmlpagefooter>
        <sethtmlpagefooter name="pageFooter" page="O" value="on" show-this-page="1" />

        <!-- Main Content Area with Standard 18mm Margins -->
        <div class="content-body">

            <!-- Document Top Header -->
            <table class="header-table">
                <tr>
                    <td width="58%" style="vertical-align: bottom;">
                        <div class="doc-badge">GRAPHICAFIX OPERATIONS MANAGEMENT</div>
                        <h1 class="doc-title"><?= $is_single ? 'DAILY PROGRESS REPORT' : 'OPERATIONS SUMMARY DOSSIER' ?></h1>
                        <div class="doc-sub">
                            <?= $is_single 
                                ? ('Reference: <strong>#GFX-DPR-' . date('Ymd', strtotime($single_report['report_date'])) . '-' . str_pad($single_report['id'], 4, '0', STR_PAD_LEFT) . '</strong> &bull; Logged: ' . date('M j, Y h:i A', strtotime($single_report['created_at'])) . ' PKT') 
                                : ('Reporting Scope: <strong>' . htmlspecialchars($period_label) . '</strong> &bull; Generated: ' . date('M j, Y h:i A') . ' PKT') ?>
                        </div>
                    </td>
                    <td width="42%" style="text-align: right; vertical-align: bottom;">
                        <div class="date-badge-box">
                            <div class="date-badge-label"><?= $is_single ? 'REPORTING DATE' : 'CONSOLIDATED PERIOD' ?></div>
                            <div class="date-badge-val"><?= $is_single ? date('l, F j, Y', strtotime($single_report['report_date'])) : htmlspecialchars($period_label) ?></div>
                        </div>
                    </td>
                </tr>
            </table>

            <?php if ($is_single): ?>
                <!-- ═══════════════════════════════════════════════════════════════ -->
                <!-- SINGLE REPORT DOSSIER LAYOUT (EXECUTIVE CORPORATE STANDARD)    -->
                <!-- ═══════════════════════════════════════════════════════════════ -->

                <!-- Overview Metadata Grid -->
                <table class="overview-table">
                    <tr>
                        <td class="ov-label" style="width:18%;">Staff Member</td>
                        <td class="ov-val" style="width:32%;">
                            <strong><?= htmlspecialchars($single_report['user_name']) ?></strong>
                            <span class="role-pill"><?= ucfirst($single_report['user_role'] ?? 'Staff') ?></span>
                        </td>
                        <td class="ov-label" style="width:18%;">Time Logged</td>
                        <td class="ov-val" style="width:32%;">
                            <strong><?= number_format($single_report['hours_worked'] ?? 8, 1) ?> Hours</strong>
                        </td>
                    </tr>
                    <tr>
                        <td class="ov-label">Project Focus</td>
                        <td class="ov-val">
                            <strong><?= htmlspecialchars($single_report['project_name'] ?? 'General / Internal Operations') ?></strong>
                        </td>
                        <td class="ov-label">Productivity Rating</td>
                        <td class="ov-val">
                            <span style="color:#b45309; font-weight:800;">★ <?= intval($single_report['productivity_rating'] ?? 5) ?>.0 / 5.0</span>
                            <span class="ov-sub">(<?= ($single_report['productivity_rating'] >= 4) ? 'High Velocity' : 'Standard' ?>)</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="ov-label">Email Address</td>
                        <td class="ov-val" style="color:#475569;">
                            <?= htmlspecialchars($single_report['user_email'] ?? 'operations@graphicafix.com') ?>
                        </td>
                        <td class="ov-label">Obstacle Status</td>
                        <td class="ov-val">
                            <?php 
                                $sev = $single_report['blocker_severity'] ?? 'none';
                                if ($sev === 'critical'): 
                            ?>
                                <span style="color:#dc2626; font-weight:800;">&#9888; Critical Impediment</span>
                            <?php elseif ($sev === 'minor' || !empty(trim($single_report['challenges'] ?? ''))): ?>
                                <span style="color:#b45309; font-weight:800;">&#9888; Minor Delay Logged</span>
                            <?php else: ?>
                                <span style="color:#166534; font-weight:800;">&#10003; Operating Smoothly</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <!-- Section 1: Accomplishments Completed Today -->
                <div class="section-wrap">
                    <div class="section-title">&#10003; 1. Key Accomplishments & Deliverables Completed</div>
                    <div class="section-body">
                        <?= renderPdfList($single_report['tasks_completed']) ?>
                    </div>
                </div>

                <!-- Section 2: In-Progress / Ongoing (if any) -->
                <?php if (!empty(trim($single_report['tasks_in_progress'] ?? ''))): ?>
                <div class="section-wrap">
                    <div class="section-title" style="color:#0284c7;">&#9203; 2. Continuing Objectives & Tasks in Progress</div>
                    <div class="section-body">
                        <?= renderPdfList($single_report['tasks_in_progress']) ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Section 3: Roadblocks, Risks & Dependency Log -->
                <?php 
                    $has_single_blocker = !empty(trim($single_report['challenges'] ?? '')) || in_array($single_report['blocker_severity'] ?? '', ['minor', 'critical']);
                    $has_waiting = !empty(trim($single_report['waiting_on'] ?? ''));
                ?>
                <div class="section-wrap">
                    <div class="section-title" style="color:<?= $has_single_blocker ? '#b91c1c' : '#024442' ?>;">
                        <?= $has_single_blocker ? '&#9888; 3. Operational Impediments & Dependency Log' : '&#10003; 3. Operational Impediments & Dependency Log' ?>
                    </div>
                    <div class="section-body">
                        <?php if ($has_single_blocker): ?>
                            <div class="note-box note-box-alert">
                                <div style="font-weight:bold; margin-bottom:2px; font-size:7.5pt; text-transform:uppercase;">
                                    &#9888; <?= ($single_report['blocker_severity'] === 'critical') ? 'CRITICAL ROADBLOCK' : 'MINOR OBSTACLE NOTED' ?>
                                </div>
                                <div><?= nl2br(htmlspecialchars($single_report['challenges'] ?: 'Obstacle reported.')) ?></div>
                                <?php if ($has_waiting): ?>
                                    <div style="margin-top:3px; padding-top:2px; border-top:1px dashed #fca5a5; font-size:7.5pt; font-weight:bold; color:#7f1d1d;">
                                        Action Required From / Waiting On: <span style="font-weight:normal;"><?= htmlspecialchars($single_report['waiting_on']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="note-box note-box-success">
                                &#10003; <strong>On Track</strong> — No operational impediments or dependency bottlenecks noted. All deliverables proceeded on schedule.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section 4: Deliverables & Proof (if any) -->
                <?php if (!empty(trim($single_report['deliverable_links'] ?? ''))): ?>
                <div class="section-wrap">
                    <div class="section-title" style="color:#6d28d9;">&#128279; 4. Deliverable Artifacts & Work Proof</div>
                    <div class="section-body" style="font-size:7.5pt; color:#4c1d95; line-height:1.4;">
                        <?= nl2br(htmlspecialchars($single_report['deliverable_links'])) ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Section 5: Plan for Tomorrow -->
                <div class="section-wrap">
                    <div class="section-title">&#10148; 5. Tomorrow's Planned Priorities & Objectives</div>
                    <div class="section-body">
                        <?= renderPdfList($single_report['plan_tomorrow']) ?>
                    </div>
                </div>

                <!-- Sign-off Block -->
                <table class="sig-table">
                    <tr>
                        <td width="48%">
                            <div class="sig-title">Report Prepared & Submitted By:</div>
                            <div class="sig-line"></div>
                            <div class="sig-name"><?= htmlspecialchars($single_report['user_name']) ?></div>
                            <div class="sig-role"><?= ucfirst($single_report['user_role'] ?? 'Staff') ?> &bull; Logged: <?= date('M j, Y h:i A', strtotime($single_report['created_at'])) ?> PKT</div>
                        </td>
                        <td width="4%">&nbsp;</td>
                        <td width="48%">
                            <div class="sig-title">Project Management Office (PMO) Verification:</div>
                            <div class="sig-line"></div>
                            <div class="sig-name">Operations & Project Management</div>
                            <div class="sig-role">Graphicafix PMO Operations Verification</div>
                        </td>
                    </tr>
                </table>

                <div class="audit-footer">
                    Official Internal Daily Progress Report (DPR) &bull; Graphicafix (Pvt.) Ltd. &bull; Operations Portal &bull; Strictly Confidential
                </div>

            <?php else: ?>
                <!-- ═══════════════════════════════════════════════════════════════ -->
                <!-- MULTI-REPORT / SUMMARY DOSSIER LAYOUT                           -->
                <!-- ═══════════════════════════════════════════════════════════════ -->

                <!-- Executive Summary KPI Row -->
                <table class="hl-grid">
                    <tr>
                        <td width="25%" style="padding: 0 4px;">
                            <div class="hl-card">
                                <div class="hl-num"><?= count($reports) ?></div>
                                <div class="hl-lbl">Total Reports</div>
                            </div>
                        </td>
                        <td width="25%" style="padding: 0 4px;">
                            <div class="hl-card">
                                <div class="hl-num"><?= number_format($total_hours, 1) ?>h</div>
                                <div class="hl-lbl">Total Work Hours</div>
                            </div>
                        </td>
                        <td width="25%" style="padding: 0 4px;">
                            <div class="hl-card" style="border-top-color:<?= $roadblocks_count > 0 ? '#ef4444' : '#024442' ?>;">
                                <div class="hl-num" style="color:<?= $roadblocks_count > 0 ? '#dc2626' : '#024442' ?>;"><?= $roadblocks_count ?></div>
                                <div class="hl-lbl">Roadblocks Flagged</div>
                            </div>
                        </td>
                        <td width="25%" style="padding: 0 4px;">
                            <div class="hl-card">
                                <div class="hl-num"><?= count($unique_members) ?></div>
                                <div class="hl-lbl">Active Team Members</div>
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- Consolidated Activity Matrix Table -->
                <?php if (!empty($reports)): ?>
                <div style="margin-bottom: 14px;">
                    <div style="font-size: 8pt; font-weight: 800; color: #024442; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.5px;">
                        Consolidated Activity Matrix
                    </div>
                    <table class="matrix-table">
                        <thead>
                            <tr>
                                <th style="width: 13%;">Date</th>
                                <th style="width: 22%;">Staff Member</th>
                                <th style="width: 24%;">Project Engagement</th>
                                <th style="width: 10%; text-align: center;">Hours</th>
                                <th style="width: 11%; text-align: center;">Velocity</th>
                                <th style="width: 20%;">Operational Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $r): ?>
                                <?php 
                                    $m_sev = $r['blocker_severity'] ?? 'none';
                                    $m_has_b = ($m_sev === 'critical' || $m_sev === 'minor' || !empty(trim($r['challenges'] ?? '')));
                                ?>
                                <tr>
                                    <td><strong><?= date('M j, Y', strtotime($r['report_date'])) ?></strong></td>
                                    <td><?= htmlspecialchars($r['user_name']) ?> <span style="font-size:6.5pt; color:#64748b;">(<?= ucfirst($r['user_role'] ?? 'User') ?>)</span></td>
                                    <td><?= htmlspecialchars($r['project_name'] ?? 'General Operations') ?></td>
                                    <td style="text-align: center; font-weight: 700;"><?= number_format($r['hours_worked'] ?? 8, 1) ?>h</td>
                                    <td style="text-align: center; color: #b45309; font-weight: 700;">★ <?= intval($r['productivity_rating'] ?? 5) ?>/5</td>
                                    <td>
                                        <?php if ($m_sev === 'critical'): ?>
                                            <span style="color: #dc2626; font-weight: 800;">&#9888; CRITICAL ROADBLOCK</span>
                                        <?php elseif ($m_has_b): ?>
                                            <span style="color: #b45309; font-weight: 700;">&#9888; Minor Delay</span>
                                        <?php else: ?>
                                            <span style="color: #166534; font-weight: 700;">&#10003; Smooth</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Individual Detailed Dossier Cards -->
                <?php if (empty($reports)): ?>
                    <div style="text-align:center; padding: 40px 20px; color: #64748b; font-size: 10pt; border: 1px dashed #cbd5e1; border-radius: 6px;">
                        No daily progress reports found matching the specified filter parameters.
                    </div>
                <?php else: ?>
                    <div style="font-size: 8pt; font-weight: 800; color: #024442; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px;">
                        Detailed Work Breakdown by Member
                    </div>
                    <?php foreach ($reports as $r): ?>
                        <?php 
                            $r_severity = $r['blocker_severity'] ?? 'none';
                            $r_has_blocker = ($r_severity === 'minor' || $r_severity === 'critical' || !empty(trim($r['challenges'] ?? '')));
                            $r_date_ts = strtotime($r['report_date']);
                        ?>
                        <div class="entry-card">
                            <!-- Card Header Bar -->
                            <table class="entry-header">
                                <tr>
                                    <td width="60%">
                                        <span class="entry-user-name"><?= htmlspecialchars($r['user_name']) ?></span>
                                        <span class="entry-user-role">(<?= ucfirst($r['user_role'] ?? 'User') ?>)</span>
                                        &bull;
                                        <span style="font-size: 7pt; color:#e0f2fe;">
                                            <?= htmlspecialchars($r['project_name'] ?? 'General Operations') ?>
                                        </span>
                                    </td>
                                    <td width="40%" class="entry-date">
                                        <?= date('l, M j, Y', $r_date_ts) ?>
                                        &nbsp;
                                        <span style="background:rgba(255,255,255,0.25); color:#ffffff; padding:1px 5px; border-radius:3px; font-size:7pt; font-weight:700;">
                                            <?= number_format($r['hours_worked'] ?? 8, 1) ?>h
                                        </span>
                                        &nbsp;
                                        <?php if ($r_severity === 'critical'): ?>
                                            <span style="background:#ef4444; color:#ffffff; padding:1px 5px; border-radius:3px; font-size:6.5pt; font-weight:800;">CRITICAL</span>
                                        <?php elseif ($r_has_blocker): ?>
                                            <span style="background:#f59e0b; color:#ffffff; padding:1px 5px; border-radius:3px; font-size:6.5pt; font-weight:800;">ROADBLOCK</span>
                                        <?php else: ?>
                                            <span style="background:#10b981; color:#ffffff; padding:1px 5px; border-radius:3px; font-size:6.5pt; font-weight:800;">SMOOTH</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>

                            <!-- Card Content Grid -->
                            <table class="entry-body-table">
                                <tr>
                                    <!-- Tasks Completed Column -->
                                    <td class="entry-col-tasks">
                                        <div class="entry-subheading" style="color:#024442;">
                                            &#10003; Accomplishments Completed
                                        </div>
                                        <?= renderPdfList($r['tasks_completed']) ?>

                                        <?php if (!empty(trim($r['tasks_in_progress'] ?? ''))): ?>
                                            <div class="entry-subheading" style="color:#0284c7; margin-top:6px;">
                                                &#9203; Work In Progress
                                            </div>
                                            <?= renderPdfList($r['tasks_in_progress']) ?>
                                        <?php endif; ?>

                                        <?php if (!empty(trim($r['deliverable_links'] ?? ''))): ?>
                                            <div class="entry-subheading" style="color:#7c3aed; margin-top:6px;">
                                                &#128279; Deliverables Attached
                                            </div>
                                            <div style="font-size:7pt; color:#6d28d9; word-break:break-all;">
                                                <?= nl2br(htmlspecialchars($r['deliverable_links'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Side Column: Blockers & Plan -->
                                    <td class="entry-col-side">
                                        <!-- Blockers -->
                                        <div style="margin-bottom: 6px;">
                                            <div class="entry-subheading" style="color:<?= $r_has_blocker ? '#dc2626' : '#16a34a' ?>;">
                                                <?= $r_has_blocker ? '&#9888; Roadblocks & Dependencies' : '&#10003; Operating Status' ?>
                                            </div>
                                            <?php if ($r_has_blocker): ?>
                                                <div style="background:#fef2f2; border:1px solid #fecaca; border-left:3px solid #ef4444; border-radius:3px; padding:4px 6px; color:#991b1b; font-size:7pt;">
                                                    <?= nl2br(htmlspecialchars($r['challenges'] ?: 'Obstacle reported.')) ?>
                                                    <?php if (!empty(trim($r['waiting_on'] ?? ''))): ?>
                                                        <div style="font-size:7pt; color:#b91c1c; font-weight:700; margin-top:3px;">
                                                            Wait: <?= htmlspecialchars($r['waiting_on']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div style="color:#16a34a; font-size:7.5pt;">✓ None (Operating on schedule)</div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Plan for Tomorrow -->
                                        <div>
                                            <div class="entry-subheading" style="color:#0284c7;">
                                                &#10148; Tomorrow's Action Plan
                                            </div>
                                            <div style="font-size:7.5pt; color:#334155;">
                                                <?= renderPdfList($r['plan_tomorrow']) ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Executive Sign-off Block for Multi-report -->
                <table class="sig-table">
                    <tr>
                        <td width="48%">
                            <div class="sig-title">Consolidated Report Prepared By:</div>
                            <div class="sig-line"></div>
                            <div class="sig-name"><?= htmlspecialchars($current_user['name']) ?></div>
                            <div class="sig-role"><?= ucfirst($current_user['role']) ?> &bull; Operations Reporting</div>
                        </td>
                        <td width="4%">&nbsp;</td>
                        <td width="48%">
                            <div class="sig-title">Executive PMO Verification & Approval:</div>
                            <div class="sig-line"></div>
                            <div class="sig-name">Executive Operations & PMO</div>
                            <div class="sig-role">Graphicafix (Pvt.) Ltd. Management</div>
                        </td>
                    </tr>
                </table>

                <div class="audit-footer">
                    Official Consolidated Daily Progress Report (DPR) Dossier &bull; Graphicafix (Pvt.) Ltd. &bull; Operations Portal &bull; Strictly Confidential
                </div>

            <?php endif; ?>

        </div>

    </body>
    </html>
    <?php
    $html = ob_get_clean();
    $mpdf->WriteHTML($html);

    $out_name = $is_single 
        ? "GFX_DPR_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $single_report['user_name']) . "_" . $single_report['report_date'] . ".pdf"
        : "GFX_DPR_Summary_" . date('Y-m-d') . ".pdf";

    $dest = isset($_GET['preview']) ? 'I' : 'D';
    $mpdf->Output($out_name, $dest);
    exit;
}

die('Invalid export format requested.');
