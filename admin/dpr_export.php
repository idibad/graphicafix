<?php
/**
 * dpr_export.php
 * Export Daily Progress Reports to CSV or Branded Executive PDF
 * Utilizes official Graphicafix letterhead header and footer graphics
 */

ob_start();
session_start();
require_once __DIR__ . '/../core/config.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    die('Access Denied. Please log in to export reports.');
}

$user_id = intval($_SESSION['user_id']);
$user_res = $conn->query("SELECT role, name FROM users WHERE user_id = $user_id");
$current_user = $user_res ? $user_res->fetch_assoc() : null;
if (!$current_user) {
    die('User account not found.');
}

$role = strtolower($current_user['role'] ?? ($_SESSION['role'] ?? 'user'));
$is_manager = in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'hrm', 'manager']);

// Export filters
$format       = strtolower($_GET['format'] ?? 'csv'); // csv | pdf
$single_id    = intval($_GET['id'] ?? 0);
$filter_user  = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? intval($_GET['user_id']) : 0;
$date         = trim($_GET['date'] ?? '');
$start_date   = trim($_GET['start_date'] ?? '');
$end_date     = trim($_GET['end_date'] ?? '');
$has_blockers = !empty($_GET['has_blockers']);
$search       = trim($_GET['q'] ?? '');

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
    $where .= " AND dpr.challenges IS NOT NULL AND TRIM(dpr.challenges) != ''";
}

if (!empty($search)) {
    $where .= " AND (dpr.tasks_completed LIKE ? OR dpr.challenges LIKE ? OR dpr.plan_tomorrow LIKE ? OR u.name LIKE ?)";
    $like_search = "%$search%";
    $params[] = $like_search;
    $params[] = $like_search;
    $params[] = $like_search;
    $params[] = $like_search;
    $types .= "ssss";
}

$query = "
    SELECT dpr.*, u.name as user_name, u.role as user_role, u.email as user_email
    FROM daily_progress_reports dpr
    JOIN users u ON dpr.user_id = u.user_id
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
$unique_members = [];

while ($row = $result->fetch_assoc()) {
    $reports[] = $row;
    if (!empty(trim($row['challenges'] ?? ''))) {
        $roadblocks_count++;
    }
    $unique_members[$row['user_id']] = true;
}

// Clear any accidental output buffer before sending file
ob_end_clean();

// ─────────────────────────────────────────────────────────────────────────────
// FORMAT 1: CSV EXPORT
// ─────────────────────────────────────────────────────────────────────────────
if ($format === 'csv') {
    $filename_suffix = date('Y-m-d');
    if (!empty($date)) {
        $filename_suffix = $date;
    } elseif (!empty($start_date) && !empty($end_date)) {
        $filename_suffix = $start_date . '_to_' . $end_date;
    }
    $filename = "DPR_Report_" . $filename_suffix . ".csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM so Excel opens it with full unicode/line break support
    fwrite($output, "\xEF\xBB\xBF");

    // CSV Header row
    fputcsv($output, [
        'Report ID',
        'Report Date',
        'Day',
        'Team Member',
        'Role',
        'Email',
        'Tasks Completed',
        'Challenges / Blockers',
        'Plan for Tomorrow',
        'Submission Time'
    ]);

    foreach ($reports as $r) {
        $day_name = date('l', strtotime($r['report_date']));
        $formatted_date = date('Y-m-d', strtotime($r['report_date']));
        $challenges_text = trim($r['challenges'] ?? '');
        if (empty($challenges_text)) {
            $challenges_text = 'None (Smooth)';
        }

        fputcsv($output, [
            $r['id'],
            $formatted_date,
            $day_name,
            $r['user_name'] ?? 'Unknown',
            ucfirst($r['user_role'] ?? 'User'),
            $r['user_email'] ?? '',
            $r['tasks_completed'],
            $challenges_text,
            $r['plan_tomorrow'],
            date('Y-m-d H:i:s', strtotime($r['created_at']))
        ]);
    }

    fclose($output);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// FORMAT 2: BRANDED PDF EXPORT (WITH LETTERHEAD HEADER & FOOTER)
// ─────────────────────────────────────────────────────────────────────────────
if ($format === 'pdf') {
    // Suppress notices during PDF generation
    error_reporting(0);
    ini_set('display_errors', '0');

    require_once __DIR__ . '/../vendor/autoload.php';

    // Helper: Convert multiline or bulleted text into clean HTML list
    function renderPdfList($text) {
        $lines = preg_split('/\r\n|\r|\n/', trim($text));
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
            return '<div style="color:#94a3b8; font-style:italic;">No details provided.</div>';
        }
        $html = '<ul style="margin: 0; padding-left: 18px; line-height: 1.55;">';
        foreach ($items as $it) {
            $html .= '<li style="margin-bottom: 4px;">' . $it . '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    $is_single = ($single_id > 0 && count($reports) === 1);
    $single_report = $is_single ? $reports[0] : null;

    // Period label calculation
    $period_label = 'All Time';
    if (!empty($date)) {
        $period_label = date('M j, Y', strtotime($date));
    } elseif (!empty($start_date) && !empty($end_date)) {
        $period_label = date('M j, Y', strtotime($start_date)) . ' – ' . date('M j, Y', strtotime($end_date));
    } elseif (!empty($start_date)) {
        $period_label = 'From ' . date('M j, Y', strtotime($start_date));
    } elseif (!empty($end_date)) {
        $period_label = 'Until ' . date('M j, Y', strtotime($end_date));
    }

    $pdf_title = $is_single 
        ? "Daily Progress Report - " . ($single_report['user_name'] ?? 'Member') . " (" . date('M j, Y', strtotime($single_report['report_date'])) . ")"
        : "Graphicafix Daily Progress Reports Summary";

    // Setup mPDF with letterhead margin spacing
    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_top'    => 44,    // Space for running header image
        'margin_bottom' => 34,    // Space for running footer image
        'margin_left'   => 0,
        'margin_right'  => 0,
        'margin_header' => 0,
        'margin_footer' => 0,
        'default_font'  => 'dejavusans'
    ]);

    $mpdf->SetTitle($pdf_title);
    $mpdf->SetAuthor('Graphicafix Management Portal');

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
            font-size: 10pt;
            line-height: 1.5;
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
            padding: 5px 20mm 15px 20mm;
        }

        /* ── Document Top Header ── */
        .header-table {
            width: 100%;
            margin-bottom: 20px;
            border-bottom: 2px solid #024442;
            padding-bottom: 12px;
        }
        .doc-title {
            font-size: 22pt;
            font-weight: 800;
            color: #024442;
            text-transform: uppercase;
            letter-spacing: -0.5px;
            margin: 0 0 4px 0;
            line-height: 1.1;
        }
        .doc-subtitle {
            font-size: 10pt;
            color: #475569;
            margin-bottom: 6px;
        }
        .badge-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 8pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-status-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #f87171;
        }
        .badge-status-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        .badge-status-info {
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #7dd3fc;
        }

        /* ── Metadata Box ── */
        .meta-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
        .meta-lbl {
            font-size: 8pt;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .meta-val-primary {
            font-size: 13pt;
            font-weight: 800;
            color: #024442;
            line-height: 1.2;
        }
        .meta-val-date {
            font-size: 12pt;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }
        .meta-sub {
            font-size: 9pt;
            color: #475569;
            margin-top: 2px;
        }

        /* ── Section Blocks ── */
        .section-block {
            margin-bottom: 18px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 10pt;
            font-weight: 800;
            color: #024442;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
        }
        .section-title-danger {
            color: #dc2626;
        }
        .section-title-success {
            color: #16a34a;
        }
        .section-title-info {
            color: #0284c7;
        }
        .section-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #024442;
            border-radius: 6px;
            padding: 12px 14px;
            font-size: 9.5pt;
        }
        .section-box-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
            color: #7f1d1d;
        }
        .section-box-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-left: 4px solid #10b981;
            color: #14532d;
        }
        .section-box-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #0284c7;
        }

        /* ── Summary KPI Grid ── */
        .kpi-table {
            width: 100%;
            margin-bottom: 22px;
        }
        .kpi-cell {
            padding: 0 4px;
        }
        .kpi-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-top: 3px solid #024442;
            border-radius: 6px;
            padding: 10px 14px;
            text-align: center;
        }
        .kpi-card.danger {
            border-top-color: #ef4444;
        }
        .kpi-num {
            font-size: 16pt;
            font-weight: 800;
            color: #024442;
            line-height: 1.1;
        }
        .kpi-card.danger .kpi-num {
            color: #dc2626;
        }
        .kpi-label {
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 700;
            margin-top: 4px;
        }

        /* ── Summary Entries Dossier Cards ── */
        .entry-card {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            margin-bottom: 16px;
            page-break-inside: avoid;
            background: #ffffff;
            overflow: hidden;
        }
        .entry-header {
            background: #024442;
            color: #ffffff;
            padding: 8px 14px;
            width: 100%;
        }
        .entry-user-name {
            font-size: 11pt;
            font-weight: 800;
            color: #ffffff;
        }
        .entry-user-role {
            font-size: 8.5pt;
            color: #bbf7d0;
            margin-left: 6px;
        }
        .entry-date {
            font-size: 9.5pt;
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
            padding: 12px 14px;
            border-right: 1px solid #e2e8f0;
            font-size: 9pt;
        }
        .entry-col-side {
            width: 45%;
            padding: 12px 14px;
            background: #fafbfc;
            font-size: 9pt;
        }
        .entry-subheading {
            font-size: 8pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            margin-bottom: 6px;
        }

        /* ── Signatures Table ── */
        .signatures-table {
            width: 100%;
            margin-top: 25px;
        }
        .sig-box {
            width: 100%;
        }
        .sig-title {
            font-size: 8.5pt;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .sig-line {
            border-bottom: 1px solid #94a3b8;
            height: 35px;
            margin-bottom: 6px;
        }
        .sig-name {
            font-size: 10pt;
            font-weight: 800;
            color: #024442;
        }
        .sig-sub {
            font-size: 8.5pt;
            color: #64748b;
        }
    </style>
    </head>
    <body>

        <!-- Edge-to-edge running header and footer images -->
        <htmlpageheader name="pageHeader">
            <div style="width: 100%;">
                <img src="<?php echo __DIR__ . '/../assets/images/letter_header.png'; ?>" style="width: 100%; margin: 0; padding: 0;" />
            </div>
        </htmlpageheader>
        <sethtmlpageheader name="pageHeader" page="O" value="on" show-this-page="1" />

        <htmlpagefooter name="pageFooter">
            <div style="width: 100%; position: relative;">
                <img src="<?php echo __DIR__ . '/../assets/images/letter_footer.png'; ?>" style="width: 100%; margin: 0; padding: 0;" />
                <div style="position: absolute; bottom: 8mm; right: 20mm; font-size: 8pt; color: #64748b; font-family: 'dejavusans', sans-serif;">
                    Page {PAGENO} of {nbpg}
                </div>
            </div>
        </htmlpagefooter>
        <sethtmlpagefooter name="pageFooter" page="O" value="on" show-this-page="1" />

        <!-- Main Content Area with 20mm Margins -->
        <div class="content-body">

            <!-- Document Top Header -->
            <table class="header-table">
                <tr>
                    <td width="40%">&nbsp;</td>
                    <td width="60%" style="text-align: right; vertical-align: bottom;">
                        <h1 class="doc-title"><?= $is_single ? 'PROGRESS REPORT' : 'PROGRESS SUMMARY' ?></h1>
                        <div class="doc-subtitle">
                            <?= $is_single 
                                ? ('DPR Reference: <strong>#DPR-' . str_pad($single_report['id'], 5, '0', STR_PAD_LEFT) . '</strong>') 
                                : ('Reporting Period: <strong>' . htmlspecialchars($period_label) . '</strong>') ?>
                        </div>
                        <?php if ($is_single): ?>
                            <?php if (!empty(trim($single_report['challenges'] ?? ''))): ?>
                                <div class="badge-status badge-status-danger">&#9888; ROADBLOCK REPORTED</div>
                            <?php else: ?>
                                <div class="badge-status badge-status-success">&#10003; ON TRACK / SMOOTH</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="badge-status badge-status-info">EXECUTIVE OPERATIONAL BRIEF</div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <?php if ($is_single): ?>
                <!-- ═══════════════════════════════════════════════════════════════ -->
                <!-- SINGLE REPORT LAYOUT                                           -->
                <!-- ═══════════════════════════════════════════════════════════════ -->

                <!-- Metadata Card -->
                <table class="meta-card">
                    <tr>
                        <td width="55%">
                            <div class="meta-lbl">Team Member</div>
                            <div class="meta-val-primary"><?= htmlspecialchars($single_report['user_name']) ?></div>
                            <div class="meta-sub">
                                <?= ucfirst($single_report['user_role'] ?? 'Staff') ?>
                                <?php if (!empty($single_report['user_email'])): ?>
                                    &bull; <?= htmlspecialchars($single_report['user_email']) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td width="45%" style="text-align: right;">
                            <div class="meta-lbl">Report Date & Submission</div>
                            <div class="meta-val-date"><?= date('l, F j, Y', strtotime($single_report['report_date'])) ?></div>
                            <div class="meta-sub">Submitted: <?= date('M j, Y h:i A', strtotime($single_report['created_at'])) ?></div>
                        </td>
                    </tr>
                </table>

                <!-- Section 1: Tasks Completed -->
                <div class="section-block">
                    <div class="section-title">&#10003; Tasks Completed Today</div>
                    <div class="section-box">
                        <?= renderPdfList($single_report['tasks_completed']) ?>
                    </div>
                </div>

                <!-- Section 2: Challenges & Roadblocks -->
                <?php $has_single_blocker = !empty(trim($single_report['challenges'] ?? '')); ?>
                <div class="section-block">
                    <div class="section-title <?= $has_single_blocker ? 'section-title-danger' : 'section-title-success' ?>">
                        <?= $has_single_blocker ? '&#9888; Roadblocks & Challenges' : '&#10003; Roadblocks & Challenges' ?>
                    </div>
                    <?php if ($has_single_blocker): ?>
                        <div class="section-box section-box-danger">
                            <?= nl2br(htmlspecialchars($single_report['challenges'])) ?>
                        </div>
                    <?php else: ?>
                        <div class="section-box section-box-success">
                            No obstacles or impediments reported. Daily tasks proceeded smoothly according to schedule.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Section 3: Plan for Tomorrow -->
                <div class="section-block">
                    <div class="section-title section-title-info">&#10148; Plan for Tomorrow / Next Objectives</div>
                    <div class="section-box section-box-info">
                        <?= renderPdfList($single_report['plan_tomorrow']) ?>
                    </div>
                </div>

                <!-- Sign-off Block -->
                <table class="signatures-table" style="page-break-inside: avoid;">
                    <tr>
                        <td width="48%">
                            <div class="sig-box">
                                <div class="sig-title">Report Prepared By:</div>
                                <div class="sig-line"></div>
                                <div class="sig-name"><?= htmlspecialchars($single_report['user_name']) ?></div>
                                <div class="sig-sub"><?= ucfirst($single_report['user_role'] ?? 'Staff') ?></div>
                            </div>
                        </td>
                        <td width="4%">&nbsp;</td>
                        <td width="48%">
                            <div class="sig-box">
                                <div class="sig-title">Management Acknowledgement:</div>
                                <div class="sig-line"></div>
                                <div class="sig-name">Operations & PMO</div>
                                <div class="sig-sub">Verified & Recorded &bull; Graphicafix</div>
                            </div>
                        </td>
                    </tr>
                </table>

            <?php else: ?>
                <!-- ═══════════════════════════════════════════════════════════════ -->
                <!-- MULTI-REPORT / SUMMARY DOSSIER LAYOUT                           -->
                <!-- ═══════════════════════════════════════════════════════════════ -->

                <!-- Executive Summary KPI Row -->
                <table class="kpi-table">
                    <tr>
                        <td width="25%" class="kpi-cell">
                            <div class="kpi-card">
                                <div class="kpi-num"><?= count($reports) ?></div>
                                <div class="kpi-label">Reports Logged</div>
                            </div>
                        </td>
                        <td width="25%" class="kpi-cell">
                            <div class="kpi-card <?= $roadblocks_count > 0 ? 'danger' : '' ?>">
                                <div class="kpi-num"><?= $roadblocks_count ?></div>
                                <div class="kpi-label">Roadblocks Flagged</div>
                            </div>
                        </td>
                        <td width="25%" class="kpi-cell">
                            <div class="kpi-card">
                                <div class="kpi-num"><?= count($reports) - $roadblocks_count ?></div>
                                <div class="kpi-label">Smooth Reports</div>
                            </div>
                        </td>
                        <td width="25%" class="kpi-cell">
                            <div class="kpi-card">
                                <div class="kpi-num"><?= count($unique_members) ?></div>
                                <div class="kpi-label">Active Members</div>
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- Report Dossier Cards -->
                <?php if (empty($reports)): ?>
                    <div style="text-align:center; padding: 50px 20px; color: #64748b; font-size: 11pt; border: 1px dashed #cbd5e1; border-radius: 8px;">
                        No daily progress reports found matching the specified export filters.
                    </div>
                <?php else: ?>
                    <?php foreach ($reports as $r): ?>
                        <?php 
                            $r_has_blocker = !empty(trim($r['challenges'] ?? ''));
                            $r_date_ts = strtotime($r['report_date']);
                        ?>
                        <div class="entry-card">
                            <!-- Card Header Bar -->
                            <table class="entry-header">
                                <tr>
                                    <td width="60%">
                                        <span class="entry-user-name"><?= htmlspecialchars($r['user_name']) ?></span>
                                        <span class="entry-user-role">(<?= ucfirst($r['user_role'] ?? 'User') ?>)</span>
                                    </td>
                                    <td width="40%" class="entry-date">
                                        <?= date('l, M j, Y', $r_date_ts) ?>
                                        &nbsp;
                                        <?php if ($r_has_blocker): ?>
                                            <span style="background:#ef4444; color:#ffffff; padding:2px 8px; border-radius:4px; font-size:7.5pt; font-weight:800;">ROADBLOCK</span>
                                        <?php else: ?>
                                            <span style="background:#10b981; color:#ffffff; padding:2px 8px; border-radius:4px; font-size:7.5pt; font-weight:800;">SMOOTH</span>
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
                                            &#10003; Tasks Completed
                                        </div>
                                        <?= renderPdfList($r['tasks_completed']) ?>
                                    </td>

                                    <!-- Side Column: Blockers & Plan -->
                                    <td class="entry-col-side">
                                        <!-- Blockers -->
                                        <div style="margin-bottom: 12px;">
                                            <div class="entry-subheading" style="color:<?= $r_has_blocker ? '#dc2626' : '#16a34a' ?>;">
                                                <?= $r_has_blocker ? '&#9888; Roadblocks / Challenges' : '&#10003; Roadblocks' ?>
                                            </div>
                                            <?php if ($r_has_blocker): ?>
                                                <div style="background:#fef2f2; border:1px solid #fecaca; border-left:3px solid #ef4444; border-radius:4px; padding:6px 8px; color:#991b1b; font-size:8.5pt;">
                                                    <?= nl2br(htmlspecialchars($r['challenges'])) ?>
                                                </div>
                                            <?php else: ?>
                                                <div style="color:#16a34a; font-size:8.5pt;">None (Smooth)</div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Plan for Tomorrow -->
                                        <div>
                                            <div class="entry-subheading" style="color:#0284c7;">
                                                &#10148; Plan for Tomorrow
                                            </div>
                                            <div style="font-size:8.5pt; color:#334155;">
                                                <?= renderPdfList($r['plan_tomorrow']) ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            <?php endif; ?>

        </div>

    </body>
    </html>
    <?php
    $html = ob_get_clean();
    $mpdf->WriteHTML($html);

    $out_name = $is_single 
        ? "DPR_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $single_report['user_name']) . "_" . $single_report['report_date'] . ".pdf"
        : "DPR_Summary_" . date('Y-m-d') . ".pdf";

    $dest = isset($_GET['preview']) ? 'I' : 'D';
    $mpdf->Output($out_name, $dest);
    exit;
}

die('Invalid export format requested.');
