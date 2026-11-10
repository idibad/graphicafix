<?php
/**
 * admin/generate_salary_pdf.php
 * Official Payroll Register & Employee Salary Slip PDF Generator using mPDF
 * 
 * Supports two modes:
 *  1. Monthly Payroll Register (A4-Landscape):
 *     When called without salary_id / user_id, or with ?month=YYYY-MM
 *  2. Individual Employee Pay Slip (A4-Portrait):
 *     When called with ?salary_id=... or ?user_id=...
 */

// Error suppression for clean PDF stream
error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/attendance_schema.php';
require_once __DIR__ . '/../core/attendance_helper.php';

if (!isset($_SESSION['user_id'])) {
    die('Access Denied. Please log in.');
}

$current_user_id = intval($_SESSION['user_id']);
$current_role    = strtolower($_SESSION['role'] ?? 'user');
$is_admin_hrm    = in_array($current_role, ['super_admin', 'superadmin', 'admin', 'hrm', 'pm']);

// Input params
$salary_id    = intval($_GET['salary_id'] ?? 0);
$target_user  = intval($_GET['user_id'] ?? 0);
$filter_month = trim($_GET['month'] ?? '');
if (empty($filter_month) || !preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
    $filter_month = date('Y-m');
}
$filter_dept   = trim($_GET['dept'] ?? 'all');
$filter_status = trim($_GET['status'] ?? 'all');
$search_q      = trim($_GET['search'] ?? '');
$dest          = (isset($_GET['dest']) && $_GET['dest'] === 'S') ? 'S' : ((isset($_GET['dl']) && $_GET['dl'] == '1') ? 'D' : 'I');

// Ensure schema is up to date
ensure_attendance_schema($conn);

// ─────────────────────────────────────────────────────────────────────────────
// MODE 2: INDIVIDUAL EMPLOYEE SALARY PAY SLIP (A4 PORTRAIT)
// ─────────────────────────────────────────────────────────────────────────────
if ($salary_id > 0 || $target_user > 0) {
    // Non-admin can only view their own slip
    if (!$is_admin_hrm && $target_user > 0 && $target_user !== $current_user_id) {
        die('Access Denied. You can only access your own salary slip.');
    }

    $sql = "
        SELECT s.*, u.name as user_name, u.username, u.email, u.gender, u.role, u.department as user_dept, u.job_title as user_job_title, u.created_at as join_date
        FROM salaries s
        INNER JOIN users u ON s.user_id = u.user_id
        WHERE " . ($salary_id > 0 ? "s.id = ?" : "s.user_id = ? AND s.salary_month = ?") . "
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if ($salary_id > 0) {
        $stmt->bind_param("i", $salary_id);
    } else {
        $stmt->bind_param("is", $target_user, $filter_month);
    }
    $stmt->execute();
    $salary = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$salary) {
        die('Salary slip record not found for the requested period.');
    }

    // Authorization check for single record by ID
    if (!$is_admin_hrm && intval($salary['user_id']) !== $current_user_id) {
        die('Access Denied.');
    }

    $emp_id       = intval($salary['user_id']);
    $sal_month    = $salary['salary_month'];
    $period_label = date('F Y', strtotime($sal_month . '-01'));
    $emp_name     = $salary['name'] ?: $salary['user_name'];
    $gender       = !empty($salary['gender']) ? ucfirst(strtolower(trim($salary['gender']))) : 'Male';
    $in_time      = !empty($salary['in_time']) ? substr($salary['in_time'], 0, 5) : '09:00';
    $out_time     = !empty($salary['out_time']) ? substr($salary['out_time'], 0, 5) : '18:00';
    $shift_hours  = date('h:i A', strtotime("2000-01-01 {$in_time}")) . ' - ' . date('h:i A', strtotime("2000-01-01 {$out_time}"));
    $slip_ref     = 'SLIP-' . date('Ym', strtotime($sal_month . '-01')) . '-EMP' . str_pad($salary['user_id'], 4, '0', STR_PAD_LEFT);

    // Fetch Itemized Deductions from salary_deductions
    $ded_stmt = $conn->prepare("
        SELECT id, deduction_type, amount, description, date_incurred, status
        FROM salary_deductions
        WHERE user_id = ? AND salary_month = ?
        ORDER BY date_incurred ASC, id ASC
    ");
    $ded_stmt->bind_param("is", $emp_id, $sal_month);
    $ded_stmt->execute();
    $itemized_deductions = $ded_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $ded_stmt->close();

    // Fetch Attendance Summary for month
    $att_stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_logged,
            SUM(IF(status = 'present' OR status = 'late', 1, 0)) as days_present,
            SUM(IF(is_late = 1, 1, 0)) as days_late,
            SUM(IF(status = 'on_leave' OR status = 'half_day', 1, 0)) as days_leave
        FROM attendance_records
        WHERE user_id = ? AND DATE_FORMAT(attendance_date, '%Y-%m') = ?
    ");
    $att_stmt->bind_param("is", $emp_id, $sal_month);
    $att_stmt->execute();
    $att_summary = $att_stmt->get_result()->fetch_assoc();
    $att_stmt->close();

    $days_in_month = intval(date('t', strtotime($sal_month . '-01')));
    $paid_days     = intval($att_summary['days_present'] ?? 0);
    if ($paid_days === 0 && floatval($salary['base_salary']) > 0) {
        $paid_days = $days_in_month - intval($att_summary['days_leave'] ?? 0);
    }
    $lop_days      = intval($att_summary['days_leave'] ?? 0);

    $emp_code     = $salary['employee_id'] ?: ('GF-' . str_pad($salary['user_id'], 4, '0', STR_PAD_LEFT));
    $designation  = $salary['job_title'] ?: ($salary['user_job_title'] ?: 'Staff');
    $department   = $salary['department'] ?: ($salary['user_dept'] ?: 'General');
    $join_date    = !empty($salary['join_date']) ? date('d/m/Y', strtotime($salary['join_date'])) : '—';
    $pay_date     = !empty($salary['payment_date']) ? date('d/m/Y', strtotime($salary['payment_date'])) : date('t/m/Y', strtotime($sal_month . '-01'));

    if (!function_exists('fmt_pdf_amt')) {
        function fmt_pdf_amt($val) {
            return 'PKR ' . number_format(floatval($val), 2);
        }
    }

    $net_in_words = amountToWords($salary['net_salary'], 'PKR');
    $net_in_words = str_ireplace([' and 00/100 Only.', ' and 00/100 Only', ' and 00/100', ' 00/100'], [' Only.', ' Only', '', ''], $net_in_words);

    // Build Earnings list
    $earnings = [];
    $earnings[] = ['label' => 'Basic Salary', 'amount' => floatval($salary['base_salary'])];
    if (floatval($salary['allowances']) > 0) {
        $earnings[] = ['label' => 'Allowances & Benefits', 'amount' => floatval($salary['allowances'])];
    }
    if (floatval($salary['previous_pending']) > 0) {
        $earnings[] = ['label' => 'Previous Arrears Added', 'amount' => floatval($salary['previous_pending'])];
    }
    $total_gross = floatval($salary['gross_pay']) + max(0, floatval($salary['previous_pending']));

    // Build Deductions list
    $deductions = [];
    if (!empty($itemized_deductions)) {
        foreach ($itemized_deductions as $id_row) {
            $lbl = $id_row['deduction_type'];
            if (!empty($id_row['date_incurred'])) {
                $lbl .= ' (' . date('d M', strtotime($id_row['date_incurred'])) . ')';
            }
            $deductions[] = ['label' => $lbl, 'amount' => floatval($id_row['amount'])];
        }
    } elseif (floatval($salary['deductions']) > 0) {
        $deductions[] = ['label' => 'Itemized Deductions', 'amount' => floatval($salary['deductions'])];
    }

    if (floatval($salary['tax']) > 0) {
        $deductions[] = ['label' => 'Income Tax', 'amount' => floatval($salary['tax'])];
    }
    if (floatval($salary['advance']) > 0) {
        $deductions[] = ['label' => 'Salary Advance / Loan Recovery', 'amount' => floatval($salary['advance'])];
    }
    $total_deductions = floatval($salary['deductions']) + floatval($salary['tax']) + floatval($salary['advance']);

    // Balanced rows for side-by-side presentation
    $max_rows = max(count($earnings), count($deductions), 4);

    $logoPath = realpath(__DIR__ . '/../assets/images/logo-black.png') ?: realpath(__DIR__ . '/../assets/images/logo-secondary.png');

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <title>Pay Slip - <?= htmlspecialchars($emp_name) ?> (<?= $period_label ?>)</title>
    <style>
        @page {
            margin-top: 14mm;
            margin-bottom: 12mm;
            margin-left: 14mm;
            margin-right: 14mm;
        }
        body {
            font-family: 'dejavusans', sans-serif;
            color: #1e293b;
            font-size: 8.5pt;
            line-height: 1.4;
        }
        table {
            border-collapse: collapse;
        }

        /* ── Header ─────────────────────────────────────────── */
        .header-tbl {
            width: 100%;
            margin-bottom: 12px;
        }
        .company-title {
            font-size: 15pt;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: -0.3px;
            margin: 0;
            line-height: 1.1;
        }
        .company-sub {
            font-size: 8pt;
            color: #64748b;
            margin-top: 3px;
        }
        .period-title-sub {
            font-size: 8pt;
            color: #64748b;
            text-align: right;
        }
        .period-title-main {
            font-size: 13pt;
            font-weight: bold;
            color: #0f172a;
            text-align: right;
            margin-top: 2px;
        }

        .divider-line {
            border-top: 1px solid #e2e8f0;
            margin-bottom: 16px;
        }

        /* ── Employee Summary & Net Pay Box ─────────────────── */
        .top-section-tbl {
            width: 100%;
            margin-bottom: 14px;
        }
        .summary-heading {
            font-size: 8pt;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 8px;
        }
        .meta-list-tbl {
            width: 100%;
        }
        .meta-list-tbl td {
            padding: 3.5px 0;
            font-size: 8.5pt;
            vertical-align: top;
        }
        .meta-lbl {
            color: #64748b;
            width: 38%;
        }
        .meta-sep {
            color: #94a3b8;
            width: 4%;
            text-align: center;
        }
        .meta-val {
            color: #0f172a;
            width: 58%;
            font-weight: 500;
        }

        /* Net Pay Highlight Card (Zoho style) */
        .net-box-outer {
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            background: #ffffff;
            width: 100%;
        }
        .net-box-top {
            background: #f0fdf4;
            padding: 12px 14px;
            border-bottom: 1px dashed #bbf7d0;
            border-radius: 7px 7px 0 0;
        }
        .net-pay-amount {
            font-size: 17pt;
            font-weight: bold;
            color: #0f172a;
            line-height: 1.1;
        }
        .net-pay-label {
            font-size: 8pt;
            color: #166534;
            font-weight: 600;
            margin-top: 3px;
        }
        .net-box-bottom {
            padding: 10px 14px;
            background: #ffffff;
            border-radius: 0 0 7px 7px;
        }
        .net-stat-tbl {
            width: 100%;
        }
        .net-stat-tbl td {
            padding: 2.5px 0;
            font-size: 8.5pt;
        }

        /* ── Sub Metadata Bar ───────────────────────────────── */
        .submeta-bar {
            border-top: 1px dotted #cbd5e1;
            border-bottom: 1px dotted #cbd5e1;
            padding: 6px 4px;
            margin-bottom: 16px;
            font-size: 8pt;
            color: #475569;
        }
        .submeta-bar td {
            font-size: 8pt;
            color: #475569;
            vertical-align: middle;
        }

        /* ── Earnings & Deductions Table Box ─────────────────── */
        .breakdown-box {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            width: 100%;
            margin-bottom: 14px;
        }
        .breakdown-box th {
            font-size: 8pt;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 7px 10px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .breakdown-box td {
            padding: 6.5px 10px;
            font-size: 8.5pt;
            color: #1e293b;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .breakdown-box .col-earn-title { width: 32%; text-align: left; }
        .breakdown-box .col-earn-amt   { width: 18%; text-align: right; font-weight: 600; }
        .breakdown-box .col-ded-title  { width: 32%; text-align: left; border-left: 1px solid #e2e8f0; }
        .breakdown-box .col-ded-amt    { width: 18%; text-align: right; font-weight: 600; }
        .breakdown-total-row td {
            background: #f8fafc;
            font-weight: bold;
            color: #0f172a;
            border-top: 1px solid #e2e8f0;
            border-bottom: none;
            padding: 8px 10px;
        }

        /* ── Net Payable Banner ─────────────────────────────── */
        .net-banner-tbl {
            width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #f8fafc;
            margin-bottom: 10px;
        }
        .net-banner-left {
            padding: 10px 14px;
            vertical-align: middle;
            width: 65%;
        }
        .net-banner-title {
            font-size: 9pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .net-banner-sub {
            font-size: 7.5pt;
            color: #64748b;
            margin-top: 2px;
        }
        .net-banner-right {
            background: #f0fdf4;
            border-left: 1px solid #e2e8f0;
            padding: 10px 18px;
            text-align: right;
            vertical-align: middle;
            width: 35%;
            border-radius: 0 5px 5px 0;
        }
        .net-banner-amt {
            font-size: 15pt;
            font-weight: bold;
            color: #0f172a;
        }

        /* ── Amount In Words ─────────────────────────────────── */
        .amount-words-wrap {
            text-align: right;
            font-size: 8pt;
            color: #475569;
            margin-bottom: 30px;
            padding-right: 4px;
        }
        .amount-words-val {
            font-weight: bold;
            color: #0f172a;
        }

        /* ── Footer Disclaimer ───────────────────────────────── */
        .footer-disclaimer {
            text-align: center;
            font-size: 7.5pt;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            padding-top: 12px;
            margin-top: 20px;
        }
    </style>
    </head>
    <body>

        <!-- 1. Header Row -->
        <table class="header-tbl">
            <tr>
                <td style="width: 60%; vertical-align: middle;">
                    <?php if ($logoPath && file_exists($logoPath)): ?>
                        <img src="<?= $logoPath ?>" style="height: 44px;" />
                    <?php else: ?>
                        <div class="company-title">Graphica Fix</div>
                    <?php endif; ?>
                </td>
                <td style="width: 40%; vertical-align: top; text-align: right;">
                    <div class="period-title-sub">Payslip For the Month</div>
                    <div class="period-title-main"><?= $period_label ?></div>
                </td>
            </tr>
        </table>

        <div class="divider-line"></div>

        <!-- 2. Top Summary Section: Employee Info (Left) + Net Pay Card (Right) -->
        <table class="top-section-tbl">
            <tr>
                <!-- Left: Employee Summary -->
                <td style="width: 58%; vertical-align: top; padding-right: 20px;">
                    <div class="summary-heading">EMPLOYEE SUMMARY</div>
                    <table class="meta-list-tbl">
                        <tr>
                            <td class="meta-lbl">Employee Name</td>
                            <td class="meta-sep">:</td>
                            <td class="meta-val"><strong><?= htmlspecialchars($emp_name) ?></strong></td>
                        </tr>
                        <tr>
                            <td class="meta-lbl">Designation</td>
                            <td class="meta-sep">:</td>
                            <td class="meta-val"><?= htmlspecialchars($designation) ?></td>
                        </tr>
                        <tr>
                            <td class="meta-lbl">Employee ID</td>
                            <td class="meta-sep">:</td>
                            <td class="meta-val"><?= htmlspecialchars($emp_code) ?></td>
                        </tr>
                        <tr>
                            <td class="meta-lbl">Date of Joining</td>
                            <td class="meta-sep">:</td>
                            <td class="meta-val"><?= $join_date ?></td>
                        </tr>
                        <tr>
                            <td class="meta-lbl">Pay Period</td>
                            <td class="meta-sep">:</td>
                            <td class="meta-val"><?= $period_label ?></td>
                        </tr>
                        <tr>
                            <td class="meta-lbl">Pay Date</td>
                            <td class="meta-sep">:</td>
                            <td class="meta-val"><?= $pay_date ?></td>
                        </tr>
                    </table>
                </td>

                <!-- Right: Net Pay Box (Zoho Style) -->
                <td style="width: 42%; vertical-align: top;">
                    <table style="width: 100%; border: 1px solid #cbd5e1; border-collapse: collapse; background: #ffffff;">
                        <tr>
                            <td style="background: #f0fdf4; padding: 12px 14px; border-bottom: 1px dashed #cbd5e1; border-left: 3px solid #16a34a;">
                                <div class="net-pay-amount"><?= fmt_pdf_amt($salary['net_salary']) ?></div>
                                <div class="net-pay-label">Employee Net Pay</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 14px; background: #ffffff;">
                                <table class="net-stat-tbl">
                                    <tr>
                                        <td style="color:#64748b; width:60%;">Paid Days</td>
                                        <td style="color:#94a3b8; width:10%;">:</td>
                                        <td style="color:#0f172a; width:30%; font-weight:600; text-align:right;"><?= $paid_days ?></td>
                                    </tr>
                                    <tr>
                                        <td style="color:#64748b; width:60%;">LOP / Unpaid Days</td>
                                        <td style="color:#94a3b8; width:10%;">:</td>
                                        <td style="color:#0f172a; width:30%; font-weight:600; text-align:right;"><?= $lop_days ?></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- 3. Sub-Metadata Bar -->
        <table class="submeta-bar" width="100%">
            <tr>
                <td style="width: 44%;">
                    <span style="color:#64748b;">Department :</span>
                    <strong><?= htmlspecialchars($department) ?></strong>
                </td>
                <td style="width: 34%; text-align: center;">
                    <span style="color:#64748b;">Shift Timing :</span>
                    <strong><?= $shift_hours ?></strong>
                </td>
                <td style="width: 22%; text-align: right;">
                    <span style="color:#64748b;">Payment Status :</span>
                    <strong><?= htmlspecialchars($salary['payment_status'] ?: 'Paid') ?></strong>
                </td>
            </tr>
        </table>

        <!-- 4. Earnings & Deductions Table (Single Boxed Container) -->
        <table class="breakdown-box" width="100%">
            <thead>
                <tr>
                    <th class="col-earn-title">EARNINGS</th>
                    <th class="col-earn-amt">AMOUNT</th>
                    <th class="col-ded-title">DEDUCTIONS</th>
                    <th class="col-ded-amt">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < $max_rows; $i++): ?>
                    <?php
                        $earn_row = $earnings[$i] ?? null;
                        $ded_row  = $deductions[$i] ?? null;
                    ?>
                    <tr>
                        <td class="col-earn-title"><?= $earn_row ? htmlspecialchars($earn_row['label']) : '' ?></td>
                        <td class="col-earn-amt"><?= $earn_row ? fmt_pdf_amt($earn_row['amount']) : '' ?></td>
                        <td class="col-ded-title"><?= $ded_row ? htmlspecialchars($ded_row['label']) : '' ?></td>
                        <td class="col-ded-amt"><?= $ded_row ? fmt_pdf_amt($ded_row['amount']) : '' ?></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
            <tfoot>
                <tr class="breakdown-total-row">
                    <td class="col-earn-title">Gross Earnings</td>
                    <td class="col-earn-amt"><?= fmt_pdf_amt($total_gross) ?></td>
                    <td class="col-ded-title">Total Deductions</td>
                    <td class="col-ded-amt"><?= fmt_pdf_amt($total_deductions) ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- 5. Total Net Payable Banner -->
        <table class="net-banner-tbl">
            <tr>
                <td class="net-banner-left">
                    <div class="net-banner-title">TOTAL NET PAYABLE</div>
                    <div class="net-banner-sub">Gross Earnings - Total Deductions</div>
                </td>
                <td class="net-banner-right">
                    <div class="net-banner-amt"><?= fmt_pdf_amt($salary['net_salary']) ?></div>
                </td>
            </tr>
        </table>

        <!-- 6. Amount in Words -->
        <div class="amount-words-wrap">
            Amount In Words : <span class="amount-words-val"><?= htmlspecialchars($net_in_words) ?></span>
        </div>

        <!-- 7. Footer Disclaimer -->
        <div class="footer-disclaimer">
            -- This document has been automatically generated by Graphica Fix Payroll System; therefore, a signature is not required. --
        </div>

    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_top'    => 14,
        'margin_bottom' => 12,
        'margin_left'   => 14,
        'margin_right'  => 14,
        'default_font'  => 'dejavusans'
    ]);

    $mpdf->SetTitle("Payslip - {$emp_name} - {$period_label}");
    $mpdf->SetAuthor('Graphica Fix Human Resources');
    $mpdf->WriteHTML($html);

    $outFileName = 'Payslip-' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $emp_name) . '-' . $sal_month . '.pdf';
    if ($dest === 'S') {
        echo $mpdf->Output($outFileName, 'S');
    } else {
        $mpdf->Output($outFileName, $dest);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// MODE 1: MONTHLY PAYROLL REGISTER REPORT (A4 LANDSCAPE)
// ─────────────────────────────────────────────────────────────────────────────
if (!$is_admin_hrm) {
    die('Access Denied. You do not have permission to view full payroll registers.');
}

$period_label = date('F Y', strtotime($filter_month . '-01'));

// Build where clause
$where_clauses = ["s.salary_month = ?"];
$bind_types    = "s";
$bind_params   = [$filter_month];

if ($filter_dept !== 'all' && !empty($filter_dept)) {
    $where_clauses[] = "s.department = ?";
    $bind_types     .= "s";
    $bind_params[]   = $filter_dept;
}
if ($filter_status !== 'all' && !empty($filter_status)) {
    $where_clauses[] = "s.payment_status = ?";
    $bind_types     .= "s";
    $bind_params[]   = $filter_status;
}
if (!empty($search_q)) {
    $where_clauses[] = "(u.name LIKE ? OR s.name LIKE ? OR s.employee_id LIKE ? OR u.username LIKE ?)";
    $bind_types     .= "ssss";
    $searchTerm      = "%{$search_q}%";
    $bind_params[]   = $searchTerm;
    $bind_params[]   = $searchTerm;
    $bind_params[]   = $searchTerm;
    $bind_params[]   = $searchTerm;
}

$where_sql = implode(" AND ", $where_clauses);

$query = "
    SELECT s.*, u.name as user_name, u.username, u.email, u.gender, u.role
    FROM salaries s
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE {$where_sql}
    ORDER BY FIELD(u.role, 'super_admin', 'superadmin', 'admin', 'hrm', 'pm', 'manager', 'sales', 'user', 'teacher'), s.name ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param($bind_types, ...$bind_params);
$stmt->execute();
$res = $stmt->get_result();

$salaries = [];
$tot_base = 0; $tot_allow = 0; $tot_gross = 0;
$tot_ded = 0; $tot_tax = 0; $tot_adv = 0;
$tot_net = 0; $tot_paid = 0; $tot_bal = 0;
$cnt_paid = 0; $cnt_part = 0; $cnt_pend = 0;

if ($res) {
    while ($r = $res->fetch_assoc()) {
        $salaries[] = $r;
        $base  = floatval($r['base_salary']);
        $allow = floatval($r['allowances']);
        $gross = floatval($r['gross_pay']);
        $ded   = floatval($r['deductions']);
        $tax   = floatval($r['tax']);
        $adv   = floatval($r['advance']);
        $net   = floatval($r['net_salary']);
        $paid  = floatval($r['amount_paid'] ?? 0);
        $bal   = max(0, $net - $paid);

        $tot_base  += $base;
        $tot_allow += $allow;
        $tot_gross += $gross;
        $tot_ded   += $ded;
        $tot_tax   += $tax;
        $tot_adv   += $adv;
        $tot_net   += $net;
        $tot_paid  += $paid;
        $tot_bal   += $bal;

        if ($r['payment_status'] === 'Paid') $cnt_paid++;
        elseif ($r['payment_status'] === 'Partially Paid') $cnt_part++;
        else $cnt_pend++;
    }
}
$stmt->close();

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Payroll Register - <?= $period_label ?></title>
<style>
    @page {
        margin-top: 10mm;
        margin-bottom: 12mm;
        margin-left: 8mm;
        margin-right: 8mm;
        footer: html_RegFooter;
    }
    body {
        font-family: 'dejavusans', sans-serif;
        color: #0f172a;
        font-size: 7.5pt;
        line-height: 1.35;
    }
    .header-bar {
        width: 100%;
        border-bottom: 2px solid #024442;
        padding-bottom: 8px;
        margin-bottom: 10px;
    }
    .header-bar td {
        vertical-align: middle;
    }
    .company-name {
        font-size: 16pt;
        font-weight: bold;
        color: #024442;
        letter-spacing: 0.5px;
    }
    .company-sub {
        font-size: 7.5pt;
        color: #64748b;
    }
    .doc-title {
        text-align: right;
    }
    .doc-title h2 {
        font-size: 13pt;
        color: #0f172a;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .doc-title p {
        font-size: 8pt;
        color: #64748b;
        margin: 2px 0 0;
        font-weight: bold;
    }
    .summary-strip {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
    }
    .summary-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 5px 8px;
        text-align: center;
    }
    .summary-box.primary {
        background: #e6f4f1;
        border-color: #024442;
    }
    .summary-box.ded {
        background: #fff5f5;
        border-color: #fecaca;
    }
    .summary-box.success {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }
    .sum-lbl {
        font-size: 6.5pt;
        font-weight: bold;
        color: #64748b;
        text-transform: uppercase;
    }
    .sum-val {
        font-size: 9.5pt;
        font-weight: bold;
        color: #0f172a;
        margin-top: 1px;
    }
    .sum-val.pri { color: #024442; }
    .sum-val.ded { color: #dc2626; }
    .sum-val.suc { color: #16a34a; }
    .reg-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #cbd5e1;
    }
    .reg-table thead th {
        background: #024442;
        color: #ffffff;
        font-size: 7pt;
        font-weight: bold;
        text-transform: uppercase;
        padding: 6px 4px;
        border: 1px solid #01312f;
        text-align: center;
    }
    .reg-table tbody td {
        padding: 5px 4px;
        font-size: 7pt;
        border: 1px solid #e2e8f0;
        vertical-align: middle;
    }
    .reg-table tbody tr:nth-child(even) td {
        background: #fafbfc;
    }
    .reg-table tfoot td {
        background: #e6f4f1;
        font-size: 7.5pt;
        font-weight: bold;
        color: #024442;
        padding: 6px 4px;
        border: 1px solid #024442;
    }
    .badge {
        display: inline-block;
        padding: 1px 5px;
        border-radius: 3px;
        font-size: 6.5pt;
        font-weight: bold;
        text-transform: uppercase;
    }
    .badge-paid { background: #dcfce7; color: #15803d; }
    .badge-partial { background: #fef3c7; color: #b45309; }
    .badge-pending { background: #fee2e2; color: #b91c1c; }
    .sig-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    .sig-table td {
        width: 33.33%;
        vertical-align: top;
        text-align: center;
        padding: 0 15px;
    }
    .sig-line-box {
        border-top: 1px solid #475569;
        margin-top: 30px;
        padding-top: 3px;
        font-size: 7.5pt;
        font-weight: bold;
        color: #1e293b;
    }
    .sig-line-box span {
        font-size: 6.5pt;
        font-weight: normal;
        color: #64748b;
    }
</style>
</head>
<body>

    <!-- Header Section -->
    <table class="header-bar">
        <tr>
            <td style="width: 55%;">
                <div class="company-name">GRAPHICA FIX</div>
                <div class="company-sub">Sector F-11, Islamabad, Pakistan · contact@graphicafix.com · www.graphicafix.com</div>
            </td>
            <td style="width: 45%;" class="doc-title">
                <h2>MONTHLY PAYROLL REGISTER</h2>
                <p>PAY PERIOD: <?= strtoupper($period_label) ?> · GENERATED: <?= date('d M Y, h:i A') ?></p>
            </td>
        </tr>
    </table>

    <!-- Executive KPI Strip -->
    <table class="summary-strip">
        <tr>
            <td class="summary-box" style="width: 15%;">
                <div class="sum-lbl">Total Staff</div>
                <div class="sum-val"><?= count($salaries) ?> Staff</div>
            </td>
            <td class="summary-box" style="width: 17%;">
                <div class="sum-lbl">Gross Payroll</div>
                <div class="sum-val">PKR <?= number_format($tot_gross) ?></div>
            </td>
            <td class="summary-box ded" style="width: 17%;">
                <div class="sum-lbl" style="color:#b91c1c;">Total Deductions</div>
                <div class="sum-val ded">-PKR <?= number_format($tot_ded + $tot_tax + $tot_adv) ?></div>
            </td>
            <td class="summary-box primary" style="width: 18%;">
                <div class="sum-lbl" style="color:#024442;">Net Payable</div>
                <div class="sum-val pri">PKR <?= number_format($tot_net) ?></div>
            </td>
            <td class="summary-box success" style="width: 17%;">
                <div class="sum-lbl" style="color:#15803d;">Disbursed (Paid)</div>
                <div class="sum-val suc">PKR <?= number_format($tot_paid) ?></div>
            </td>
            <td class="summary-box" style="width: 16%;">
                <div class="sum-lbl">Remaining Balance</div>
                <div class="sum-val" style="color:<?= $tot_bal > 0 ? '#dc2626' : '#16a34a' ?>;">PKR <?= number_format($tot_bal) ?></div>
            </td>
        </tr>
    </table>

    <!-- Payroll Register Table -->
    <table class="reg-table">
        <thead>
            <tr>
                <th style="width: 3%;">#</th>
                <th style="width: 17%; text-align: left;">Employee Details</th>
                <th style="width: 9%; text-align: left;">Department</th>
                <th style="width: 9%;">Shift Timing</th>
                <th style="width: 8%; text-align: right;">Base (PKR)</th>
                <th style="width: 7%; text-align: right;">Allowances</th>
                <th style="width: 8%; text-align: right;">Gross Pay</th>
                <th style="width: 8%; text-align: right;">Deductions</th>
                <th style="width: 6%; text-align: right;">Tax/Adv</th>
                <th style="width: 9%; text-align: right;">Net Salary</th>
                <th style="width: 8%; text-align: right;">Paid (PKR)</th>
                <th style="width: 8%; text-align: center;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($salaries)): ?>
            <tr>
                <td colspan="12" style="text-align: center; padding: 20px; color: #94a3b8;">
                    No salary records found for this period.
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($salaries as $idx => $s): ?>
                <?php
                    $emp_in  = !empty($s['in_time']) ? substr($s['in_time'], 0, 5) : '09:00';
                    $emp_out = !empty($s['out_time']) ? substr($s['out_time'], 0, 5) : '18:00';
                    $s_taxadv = floatval($s['tax']) + floatval($s['advance']);
                ?>
                <tr>
                    <td style="text-align: center; color: #64748b; font-weight: bold;"><?= $idx + 1 ?></td>
                    <td>
                        <strong style="color: #0f172a;"><?= htmlspecialchars($s['name'] ?: $s['user_name']) ?></strong>
                        <div style="font-size: 6.5pt; color: #64748b;">
                            <?= htmlspecialchars($s['employee_id'] ?: ('EMP' . str_pad($s['user_id'], 4, '0', STR_PAD_LEFT))) ?> · <?= htmlspecialchars($s['job_title'] ?: 'Staff') ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($s['department'] ?: 'General') ?></td>
                    <td style="text-align: center; font-size: 6.5pt; color: #475569;"><?= $emp_in ?> - <?= $emp_out ?></td>
                    <td style="text-align: right;"><?= number_format($s['base_salary']) ?></td>
                    <td style="text-align: right;"><?= number_format($s['allowances']) ?></td>
                    <td style="text-align: right; font-weight: bold; color: #0284c7;"><?= number_format($s['gross_pay']) ?></td>
                    <td style="text-align: right; color: #dc2626;"><?= $s['deductions'] > 0 ? ('-' . number_format($s['deductions'])) : '0' ?></td>
                    <td style="text-align: right; color: #64748b;"><?= $s_taxadv > 0 ? ('-' . number_format($s_taxadv)) : '0' ?></td>
                    <td style="text-align: right; font-weight: bold; color: #166534; background: #f0fdf4;"><?= number_format($s['net_salary']) ?></td>
                    <td style="text-align: right; font-weight: bold;"><?= number_format($s['amount_paid']) ?></td>
                    <td style="text-align: center;">
                        <?php if ($s['payment_status'] === 'Paid'): ?>
                            <span class="badge badge-paid">Paid</span>
                        <?php elseif ($s['payment_status'] === 'Partially Paid'): ?>
                            <span class="badge badge-partial">Partial</span>
                        <?php else: ?>
                            <span class="badge badge-pending">Pending</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align: right; font-weight: bold; text-transform: uppercase;">Grand Totals (PKR):</td>
                <td style="text-align: right;"><?= number_format($tot_base) ?></td>
                <td style="text-align: right;"><?= number_format($tot_allow) ?></td>
                <td style="text-align: right; color: #0284c7;"><?= number_format($tot_gross) ?></td>
                <td style="text-align: right; color: #dc2626;">-<?= number_format($tot_ded) ?></td>
                <td style="text-align: right; color: #64748b;">-<?= number_format($tot_tax + $tot_adv) ?></td>
                <td style="text-align: right; color: #15803d; background: #dcfce7;"><?= number_format($tot_net) ?></td>
                <td style="text-align: right;"><?= number_format($tot_paid) ?></td>
                <td style="text-align: center; font-size: 6.5pt; color: #64748b;"><?= $cnt_paid ?>P / <?= $cnt_part ?>Par / <?= $cnt_pend ?>Pen</td>
            </tr>
        </tfoot>
    </table>

    <!-- Signature Authorization Block -->
    <table class="sig-table">
        <tr>
            <td>
                <div class="sig-line-box">
                    Prepared By: HR / Accounts Officer<br>
                    <span>Signature & Date</span>
                </div>
            </td>
            <td>
                <div class="sig-line-box">
                    Verified By: Operations Manager<br>
                    <span>Signature & Date</span>
                </div>
            </td>
            <td>
                <div class="sig-line-box">
                    Approved By: Managing Director / CEO<br>
                    <span>Signature & Date</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <htmlpagefooter name="RegFooter">
        <table width="100%" style="font-size:6.5pt; color:#64748b; border-top:1px solid #cbd5e1; padding-top:4px;">
            <tr>
                <td width="40%">CONFIDENTIAL — FOR INTERNAL USE ONLY · Graphica Fix Payroll</td>
                <td width="30%" align="center">Pay Period: <?= $period_label ?></td>
                <td width="30%" align="right">Page {PAGENO} of {nbpg}</td>
            </tr>
        </table>
    </htmlpagefooter>

</body>
</html>
<?php
$html = ob_get_clean();

$mpdf = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4-L',
    'margin_top'    => 8,
    'margin_bottom' => 12,
    'margin_left'   => 8,
    'margin_right'  => 8,
    'margin_header' => 0,
    'margin_footer' => 0,
    'default_font'  => 'dejavusans'
]);

$mpdf->SetTitle("Payroll Register - {$period_label}");
$mpdf->SetAuthor('Graphica Fix Human Resources');
$mpdf->WriteHTML($html);

$outFileName = 'Payroll-Register-' . $filter_month . '.pdf';
if ($dest === 'S') {
    echo $mpdf->Output($outFileName, 'S');
} else {
    $mpdf->Output($outFileName, $dest);
}
exit;
