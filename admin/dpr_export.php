<?php
/**
 * dpr_export.php
 * Corporate Standard Daily Progress Report (DPR) Export Engine
 * Generates ISO-compliant CSV spreadsheets and executive letterhead PDF dossiers
 */

ob_start();
session_start();
require_once __DIR__ . '/../core/config.php';

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

    // Helper: Convert multiline or bulleted text into clean HTML list
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
            return '<div style="color:#94a3b8; font-style:italic;">No details recorded.</div>';
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
        'margin_top'    => 44,    // Space for running header image
        'margin_bottom' => 34,    // Space for running footer image
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
            font-size: 9pt;
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
            padding: 4mm 20mm 15mm 20mm;
        }

        /* ── Document Top Header ── */
        .header-table {
            width: 100%;
            margin-bottom: 14px;
            border-bottom: 2px solid #024442;
            padding-bottom: 8px;
        }
        .corp-meta-header {
            font-size: 7.5pt;
            font-weight: 800;
            color: #024442;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .corp-confidential {
            font-size: 7.5pt;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-title {
            font-size: 19pt;
            font-weight: 800;
            color: #024442;
            text-transform: uppercase;
            letter-spacing: -0.5px;
            margin: 0 0 3px 0;
            line-height: 1.1;
        }
        .doc-subtitle {
            font-size: 9pt;
            color: #475569;
            margin-bottom: 5px;
        }
        .badge-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 7.5pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-status-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #f87171;
        }
        .badge-status-warning {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
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
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 14px;
        }
        .meta-lbl {
            font-size: 7.5pt;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .meta-val-primary {
            font-size: 12pt;
            font-weight: 800;
            color: #024442;
            line-height: 1.2;
        }
        .meta-val-date {
            font-size: 11pt;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
        }
        .meta-sub {
            font-size: 8pt;
            color: #475569;
            margin-top: 2px;
        }

        /* ── Highlights Grid ── */
        .highlights-table {
            width: 100%;
            margin-bottom: 14px;
        }
        .hl-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-top: 3px solid #024442;
            border-radius: 6px;
            padding: 8px 10px;
            text-align: center;
        }
        .hl-num {
            font-size: 10.5pt;
            font-weight: 800;
            color: #024442;
        }
        .hl-lbl {
            font-size: 7pt;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        /* ── Section Blocks ── */
        .section-block {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 9pt;
            font-weight: 800;
            color: #024442;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .section-title-danger { color: #dc2626; }
        .section-title-warning { color: #b45309; }
        .section-title-success { color: #16a34a; }
        .section-title-info { color: #0284c7; }
        .section-title-purple { color: #7c3aed; }

        .section-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #024442;
            border-radius: 6px;
            padding: 9px 12px;
            font-size: 8.5pt;
        }
        .section-box-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
            color: #7f1d1d;
        }
        .section-box-warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }
        .section-box-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-left: 4px solid #10b981;
            color: #14532d;
        }
        .section-box-info {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-left: 4px solid #0284c7;
            color: #0c4a6e;
        }
        .section-box-purple {
            background: #f5f3ff;
            border: 1px solid #ddd6fe;
            border-left: 4px solid #8b5cf6;
            color: #4c1d95;
        }

        /* ── Multi-Summary Matrix Table ── */
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            font-size: 8pt;
        }
        .matrix-table th {
            background: #024442;
            color: #ffffff;
            font-weight: 800;
            text-transform: uppercase;
            padding: 6px 8px;
            border: 1px solid #024442;
            font-size: 7.5pt;
            letter-spacing: 0.5px;
        }
        .matrix-table td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .matrix-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        /* ── Summary Entries Dossier Cards ── */
        .entry-card {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            margin-bottom: 12px;
            page-break-inside: avoid;
            background: #ffffff;
            overflow: hidden;
        }
        .entry-header {
            background: #024442;
            color: #ffffff;
            padding: 7px 12px;
            width: 100%;
        }
        .entry-user-name {
            font-size: 10pt;
            font-weight: 800;
            color: #ffffff;
        }
        .entry-user-role {
            font-size: 7.5pt;
            color: #bbf7d0;
            margin-left: 6px;
        }
        .entry-date {
            font-size: 8.5pt;
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
            padding: 9px 12px;
            border-right: 1px solid #e2e8f0;
            font-size: 8pt;
        }
        .entry-col-side {
            width: 45%;
            padding: 9px 12px;
            background: #fafbfc;
            font-size: 8pt;
        }
        .entry-subheading {
            font-size: 7.5pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        /* ── Signatures Table ── */
        .signatures-table {
            width: 100%;
            margin-top: 20px;
        }
        .sig-box {
            width: 100%;
        }
        .sig-title {
            font-size: 7.5pt;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .sig-line {
            border-bottom: 1px solid #94a3b8;
            height: 30px;
            margin-bottom: 5px;
        }
        .sig-name {
            font-size: 9pt;
            font-weight: 800;
            color: #024442;
        }
        .sig-sub {
            font-size: 7.5pt;
            color: #64748b;
        }
        .audit-disclaimer {
            font-size: 7pt;
            color: #94a3b8;
            text-align: center;
            margin-top: 14px;
            padding-top: 6px;
            border-top: 1px dashed #cbd5e1;
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
                    <td width="35%">
                        <div class="corp-meta-header">GRAPHICAFIX (PVT.) LTD. &bull; OPERATIONS</div>
                        <div class="corp-confidential">INTERNAL RECORD &bull; STRICTLY CONFIDENTIAL</div>
                    </td>
                    <td width="65%" style="text-align: right; vertical-align: bottom;">
                        <h1 class="doc-title"><?= $is_single ? 'DAILY PROGRESS REPORT' : 'OPERATIONS SUMMARY DOSSIER' ?></h1>
                        <div class="doc-subtitle">
                            <?= $is_single 
                                ? ('DPR Reference: <strong>#GFX-DPR-' . date('Ymd', strtotime($single_report['report_date'])) . '-' . str_pad($single_report['id'], 4, '0', STR_PAD_LEFT) . '</strong>') 
                                : ('Reporting Period: <strong>' . htmlspecialchars($period_label) . '</strong>') ?>
                        </div>
                        <?php if ($is_single): ?>
                            <?php 
                                $sev = $single_report['blocker_severity'] ?? 'none';
                                if ($sev === 'critical'): 
                            ?>
                                <div class="badge-status badge-status-danger">&#9888; CRITICAL ROADBLOCK &bull; ESCALATION LOGGED</div>
                            <?php elseif ($sev === 'minor' || !empty(trim($single_report['challenges'] ?? ''))): ?>
                                <div class="badge-status badge-status-warning">&#9888; MINOR OBSTACLE NOTED</div>
                            <?php else: ?>
                                <div class="badge-status badge-status-success">&#10003; ON TRACK &bull; OPERATING SMOOTHLY</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="badge-status badge-status-info">EXECUTIVE MANAGEMENT CONSOLIDATION</div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <?php if ($is_single): ?>
                <!-- ═══════════════════════════════════════════════════════════════ -->
                <!-- SINGLE REPORT DOSSIER LAYOUT                                    -->
                <!-- ═══════════════════════════════════════════════════════════════ -->

                <!-- Metadata Card -->
                <table class="meta-card">
                    <tr>
                        <td width="55%">
                            <div class="meta-lbl">Employee Profile & Designation</div>
                            <div class="meta-val-primary"><?= htmlspecialchars($single_report['user_name']) ?></div>
                            <div class="meta-sub">
                                <strong><?= ucfirst($single_report['user_role'] ?? 'Staff') ?></strong>
                                <?php if (!empty($single_report['user_email'])): ?>
                                    &bull; <?= htmlspecialchars($single_report['user_email']) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td width="45%" style="text-align: right;">
                            <div class="meta-lbl">Reporting Date & Timestamp</div>
                            <div class="meta-val-date"><?= date('l, F j, Y', strtotime($single_report['report_date'])) ?></div>
                            <div class="meta-sub">Recorded at: <?= date('M j, Y h:i A', strtotime($single_report['created_at'])) ?> (PKT)</div>
                        </td>
                    </tr>
                </table>

                <!-- Detailed Stats Highlights Row -->
                <table class="highlights-table">
                    <tr>
                        <td width="25%" style="padding: 0 4px;">
                            <div class="hl-box">
                                <div class="hl-num"><?= number_format($single_report['hours_worked'] ?? 8, 1) ?>h</div>
                                <div class="hl-lbl">Hours Worked</div>
                            </div>
                        </td>
                        <td width="25%" style="padding: 0 4px;">
                            <div class="hl-box" style="border-top-color:#10b981;">
                                <div class="hl-num" style="color:#047857; font-size:9pt; white-space:nowrap; overflow:hidden;">
                                    <?= htmlspecialchars($single_report['project_name'] ?? 'General / Internal Work') ?>
                                </div>
                                <div class="hl-lbl">Project Engagement</div>
                            </div>
                        </td>
                        <td width="25%" style="padding: 0 4px;">
                            <div class="hl-box" style="border-top-color:#f59e0b;">
                                <div class="hl-num" style="color:#b45309;">
                                    ★ <?= intval($single_report['productivity_rating'] ?? 5) ?> / 5.0
                                </div>
                                <div class="hl-lbl">Productivity Score</div>
                            </div>
                        </td>
                        <td width="25%" style="padding: 0 4px;">
                            <?php 
                                $s_sev = $single_report['blocker_severity'] ?? 'none';
                                $border_col = ($s_sev === 'critical') ? '#ef4444' : (($s_sev === 'minor' || !empty(trim($single_report['challenges'] ?? ''))) ? '#f59e0b' : '#10b981');
                                $text_col = ($s_sev === 'critical') ? '#dc2626' : (($s_sev === 'minor' || !empty(trim($single_report['challenges'] ?? ''))) ? '#b45309' : '#166534');
                                $label_val = ($s_sev === 'critical') ? 'CRITICAL' : (($s_sev === 'minor' || !empty(trim($single_report['challenges'] ?? ''))) ? 'MINOR DELAY' : 'SMOOTH');
                            ?>
                            <div class="hl-box" style="border-top-color:<?= $border_col ?>;">
                                <div class="hl-num" style="color:<?= $text_col ?>;"><?= $label_val ?></div>
                                <div class="hl-lbl">Obstacle Status</div>
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- Section 1: Tasks Completed -->
                <div class="section-block">
                    <div class="section-title section-title-success">&#10003; 1. Key Accomplishments & Deliverables (Completed Today)</div>
                    <div class="section-box section-box-success">
                        <?= renderPdfList($single_report['tasks_completed']) ?>
                    </div>
                </div>

                <!-- Section 2: Tasks In Progress (if present) -->
                <?php if (!empty(trim($single_report['tasks_in_progress'] ?? ''))): ?>
                <div class="section-block">
                    <div class="section-title section-title-info">&#9203; 2. Work In Progress & Continuing Objectives</div>
                    <div class="section-box section-box-info">
                        <?= renderPdfList($single_report['tasks_in_progress']) ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Section 3: Challenges, Roadblocks & Waiting On -->
                <?php 
                    $has_single_blocker = !empty(trim($single_report['challenges'] ?? '')) || in_array($single_report['blocker_severity'] ?? '', ['minor', 'critical']);
                    $has_waiting = !empty(trim($single_report['waiting_on'] ?? ''));
                ?>
                <div class="section-block">
                    <div class="section-title <?= $has_single_blocker ? 'section-title-danger' : 'section-title-success' ?>">
                        <?= $has_single_blocker ? '&#9888; 3. Obstacles, Roadblocks & Dependency Log' : '&#10003; 3. Obstacles, Roadblocks & Dependency Log' ?>
                    </div>
                    <?php if ($has_single_blocker): ?>
                        <div class="section-box <?= ($single_report['blocker_severity'] === 'critical') ? 'section-box-danger' : 'section-box-warning' ?>">
                            <div style="margin-bottom: <?= $has_waiting ? '6px' : '0' ?>;">
                                <?= nl2br(htmlspecialchars($single_report['challenges'] ?: 'Roadblock reported.')) ?>
                            </div>
                            <?php if ($has_waiting): ?>
                                <div style="font-size: 8pt; font-weight: 700; margin-top: 6px; padding-top: 5px; border-top: 1px dashed rgba(0,0,0,0.15);">
                                    &#128338; Dependency / Action Required From: <span style="font-weight: 600;"><?= htmlspecialchars($single_report['waiting_on']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="section-box section-box-success">
                            No operational impediments reported. Daily tasks proceeded smoothly according to schedule.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Section 4: Deliverables & Proof Links (if present) -->
                <?php if (!empty(trim($single_report['deliverable_links'] ?? ''))): ?>
                <div class="section-block">
                    <div class="section-title section-title-purple">&#128279; 4. Deliverables, Artifacts & Work Proof</div>
                    <div class="section-box section-box-purple">
                        <?= nl2br(htmlspecialchars($single_report['deliverable_links'])) ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Section 5: Plan for Tomorrow -->
                <div class="section-block">
                    <div class="section-title section-title-info">&#10148; 5. Next Action Plan & Priorities for Tomorrow</div>
                    <div class="section-box section-box-info">
                        <?= renderPdfList($single_report['plan_tomorrow']) ?>
                    </div>
                </div>

                <!-- Sign-off Block -->
                <table class="signatures-table" style="page-break-inside: avoid;">
                    <tr>
                        <td width="48%">
                            <div class="sig-box">
                                <div class="sig-title">Report Prepared & Submitted By:</div>
                                <div class="sig-line"></div>
                                <div class="sig-name"><?= htmlspecialchars($single_report['user_name']) ?></div>
                                <div class="sig-sub"><?= ucfirst($single_report['user_role'] ?? 'Staff') ?> &bull; Logged: <?= date('M j, Y', strtotime($single_report['created_at'])) ?></div>
                            </div>
                        </td>
                        <td width="4%">&nbsp;</td>
                        <td width="48%">
                            <div class="sig-box">
                                <div class="sig-title">Project Management Office (PMO) Verification:</div>
                                <div class="sig-line"></div>
                                <div class="sig-name">Operations & Engineering Management</div>
                                <div class="sig-sub">Verified & Recorded &bull; Graphicafix Operations</div>
                            </div>
                        </td>
                    </tr>
                </table>

                <div class="audit-disclaimer">
                    This document constitutes an official internal Daily Progress Report (DPR) generated within the Graphicafix Operations Portal. Confidential and proprietary.
                </div>

            <?php else: ?>
                <!-- ═══════════════════════════════════════════════════════════════ -->
                <!-- MULTI-REPORT / SUMMARY DOSSIER LAYOUT                           -->
                <!-- ═══════════════════════════════════════════════════════════════ -->

                <!-- Executive Summary KPI Row -->
                <table class="highlights-table" style="margin-bottom: 16px;">
                    <tr>
                        <td width="25%" style="padding: 0 4px;">
                            <div class="hl-box">
                                <div class="hl-num"><?= count($reports) ?></div>
                                <div class="hl-lbl">Reports Logged</div>
                            </div>
                        </td>
                        <td width="25%" style="padding: 0 4px;">
                            <div class="hl-box">
                                <div class="hl-num"><?= number_format($total_hours, 1) ?>h</div>
                                <div class="hl-lbl">Total Hours Logged</div>
                            </div>
                        </td>
                        <td width="25%" style="padding: 0 4px;">
                            <div class="hl-box" style="border-top-color:<?= $roadblocks_count > 0 ? '#ef4444' : '#10b981' ?>;">
                                <div class="hl-num" style="color:<?= $roadblocks_count > 0 ? '#dc2626' : '#166534' ?>;"><?= $roadblocks_count ?></div>
                                <div class="hl-lbl">Roadblock Incidents</div>
                            </div>
                        </td>
                        <td width="25%" style="padding: 0 4px;">
                            <div class="hl-box">
                                <div class="hl-num"><?= count($unique_members) ?></div>
                                <div class="hl-lbl">Active Staff Members</div>
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- Executive Matrix Table -->
                <?php if (!empty($reports)): ?>
                <div style="margin-bottom: 16px;">
                    <div style="font-size: 8.5pt; font-weight: 800; color: #024442; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px;">
                        Consolidated Activity Matrix
                    </div>
                    <table class="matrix-table">
                        <thead>
                            <tr>
                                <th style="width: 14%;">Date</th>
                                <th style="width: 20%;">Staff Member</th>
                                <th style="width: 22%;">Project Engagement</th>
                                <th style="width: 10%; text-align: center;">Hours</th>
                                <th style="width: 10%; text-align: center;">Score</th>
                                <th style="width: 24%;">Operational Status</th>
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
                                    <td><?= htmlspecialchars($r['user_name']) ?> <span style="font-size:7pt; color:#64748b;">(<?= ucfirst($r['user_role'] ?? 'User') ?>)</span></td>
                                    <td><?= htmlspecialchars($r['project_name'] ?? 'General Work') ?></td>
                                    <td style="text-align: center; font-weight: 700;"><?= number_format($r['hours_worked'] ?? 8, 1) ?>h</td>
                                    <td style="text-align: center; color: #b45309; font-weight: 700;">★ <?= intval($r['productivity_rating'] ?? 5) ?>/5</td>
                                    <td>
                                        <?php if ($m_sev === 'critical'): ?>
                                            <span style="color: #dc2626; font-weight: 800;">&#9888; CRITICAL ROADBLOCK</span>
                                        <?php elseif ($m_has_b): ?>
                                            <span style="color: #b45309; font-weight: 700;">&#9888; Minor Delay</span>
                                        <?php else: ?>
                                            <span style="color: #166534; font-weight: 700;">&#10003; Operating Smoothly</span>
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
                    <div style="text-align:center; padding: 50px 20px; color: #64748b; font-size: 11pt; border: 1px dashed #cbd5e1; border-radius: 8px;">
                        No daily progress reports found matching the specified export parameters.
                    </div>
                <?php else: ?>
                    <div style="font-size: 8.5pt; font-weight: 800; color: #024442; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">
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
                                    <td width="55%">
                                        <span class="entry-user-name"><?= htmlspecialchars($r['user_name']) ?></span>
                                        <span class="entry-user-role">(<?= ucfirst($r['user_role'] ?? 'User') ?>)</span>
                                        &bull;
                                        <span style="font-size: 7.5pt; color:#e0f2fe;">
                                            <?= htmlspecialchars($r['project_name'] ?? 'General Work') ?>
                                        </span>
                                    </td>
                                    <td width="45%" class="entry-date">
                                        <?= date('l, M j, Y', $r_date_ts) ?>
                                        &nbsp;
                                        <span style="background:rgba(255,255,255,0.25); color:#ffffff; padding:1px 6px; border-radius:4px; font-size:7.5pt; font-weight:700;">
                                            <?= number_format($r['hours_worked'] ?? 8, 1) ?>h
                                        </span>
                                        &nbsp;
                                        <?php if ($r_severity === 'critical'): ?>
                                            <span style="background:#ef4444; color:#ffffff; padding:2px 6px; border-radius:4px; font-size:7pt; font-weight:800;">CRITICAL</span>
                                        <?php elseif ($r_has_blocker): ?>
                                            <span style="background:#f59e0b; color:#ffffff; padding:2px 6px; border-radius:4px; font-size:7pt; font-weight:800;">ROADBLOCK</span>
                                        <?php else: ?>
                                            <span style="background:#10b981; color:#ffffff; padding:2px 6px; border-radius:4px; font-size:7pt; font-weight:800;">SMOOTH</span>
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
                                            &#10003; Accomplishments & Delivery
                                        </div>
                                        <?= renderPdfList($r['tasks_completed']) ?>

                                        <?php if (!empty(trim($r['tasks_in_progress'] ?? ''))): ?>
                                            <div class="entry-subheading" style="color:#0284c7; margin-top:8px;">
                                                &#9203; Work In Progress
                                            </div>
                                            <?= renderPdfList($r['tasks_in_progress']) ?>
                                        <?php endif; ?>

                                        <?php if (!empty(trim($r['deliverable_links'] ?? ''))): ?>
                                            <div class="entry-subheading" style="color:#7c3aed; margin-top:8px;">
                                                &#128279; Deliverables Attached
                                            </div>
                                            <div style="font-size:7.5pt; color:#6d28d9; word-break:break-all;">
                                                <?= nl2br(htmlspecialchars($r['deliverable_links'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Side Column: Blockers & Plan -->
                                    <td class="entry-col-side">
                                        <!-- Blockers -->
                                        <div style="margin-bottom: 8px;">
                                            <div class="entry-subheading" style="color:<?= $r_has_blocker ? '#dc2626' : '#16a34a' ?>;">
                                                <?= $r_has_blocker ? '&#9888; Roadblocks & Impediments' : '&#10003; Operating Status' ?>
                                            </div>
                                            <?php if ($r_has_blocker): ?>
                                                <div style="background:#fef2f2; border:1px solid #fecaca; border-left:3px solid #ef4444; border-radius:4px; padding:6px 8px; color:#991b1b; font-size:7.5pt;">
                                                    <?= nl2br(htmlspecialchars($r['challenges'] ?: 'Roadblock reported.')) ?>
                                                    <?php if (!empty(trim($r['waiting_on'] ?? ''))): ?>
                                                        <div style="font-size:7.5pt; color:#b91c1c; font-weight:700; margin-top:4px;">
                                                            Wait: <?= htmlspecialchars($r['waiting_on']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div style="color:#16a34a; font-size:8pt;">None (Smooth & On Schedule)</div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Plan for Tomorrow -->
                                        <div>
                                            <div class="entry-subheading" style="color:#0284c7;">
                                                &#10148; Tomorrow's Action Plan
                                            </div>
                                            <div style="font-size:8pt; color:#334155;">
                                                <?= renderPdfList($r['plan_tomorrow']) ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="audit-disclaimer">
                    This document constitutes an official consolidated Daily Progress Report (DPR) summary generated within the Graphicafix Operations Portal. Confidential and proprietary.
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
