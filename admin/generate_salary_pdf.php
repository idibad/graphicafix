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
            SUM(IF(status = 'leave', 1, 0)) as days_leave
        FROM attendance_records
        WHERE user_id = ? AND DATE_FORMAT(attendance_date, '%Y-%m') = ?
    ");
    $att_stmt->bind_param("is", $emp_id, $sal_month);
    $att_stmt->execute();
    $att_summary = $att_stmt->get_result()->fetch_assoc();
    $att_stmt->close();

    // Header & Footer Image Assets
    $headerImgPath = realpath(__DIR__ . '/../assets/images/letter_header.png');
    $footerImgPath = realpath(__DIR__ . '/../assets/images/letter_footer.png');
    $hasHeader = ($headerImgPath && file_exists($headerImgPath));
    $hasFooter = ($footerImgPath && file_exists($footerImgPath));

    $net_in_words = amountToWords($salary['net_salary'], 'PKR');
    $net_in_words = str_ireplace([' and 00/100 Only.', ' and 00/100 Only', ' and 00/100', ' 00/100'], [' Only.', ' Only', '', ''], $net_in_words);

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <title>Pay Slip - <?= htmlspecialchars($emp_name) ?> (<?= $period_label ?>)</title>
    <style>
        @page {
            margin-top: <?= $hasHeader ? '42mm' : '15mm' ?>;
            margin-bottom: <?= $hasFooter ? '32mm' : '15mm' ?>;
            margin-left: 12mm;
            margin-right: 12mm;
            header: html_LetterHeader;
            footer: html_LetterFooter;
        }
        body {
            font-family: 'dejavusans', sans-serif;
            color: #1e293b;
            font-size: 9pt;
            line-height: 1.4;
        }
        .slip-header-title {
            text-align: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #024442;
        }
        .slip-header-title h1 {
            font-size: 16pt;
            color: #024442;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .slip-header-title p {
            font-size: 9pt;
            color: #64748b;
            margin: 3px 0 0;
            font-weight: bold;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .meta-table td {
            padding: 6px 10px;
            font-size: 8.5pt;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .meta-lbl {
            font-weight: bold;
            color: #475569;
            width: 18%;
            background: #f1f5f9;
        }
        .meta-val {
            color: #0f172a;
            width: 32%;
        }
        .section-heading {
            font-size: 10pt;
            font-weight: bold;
            color: #024442;
            margin: 12px 0 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .dual-breakdown {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .dual-breakdown td {
            vertical-align: top;
            padding: 0;
        }
        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
        }
        .breakdown-table th {
            background: #024442;
            color: #ffffff;
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            text-align: left;
        }
        .breakdown-table th.r { text-align: right; }
        .breakdown-table td {
            padding: 6px 8px;
            font-size: 8.5pt;
            border-bottom: 1px solid #f1f5f9;
        }
        .breakdown-table tr:nth-child(even) td {
            background: #fafbfc;
        }
        .breakdown-table tfoot td {
            background: #e6f4f1;
            font-weight: bold;
            border-top: 1px solid #024442;
            padding: 7px 8px;
        }
        .net-card {
            background: #f0fdf4;
            border: 1.5px solid #16a34a;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 14px;
        }
        .net-title {
            font-size: 9pt;
            font-weight: bold;
            color: #166534;
            text-transform: uppercase;
        }
        .net-amount {
            font-size: 16pt;
            font-weight: bold;
            color: #15803d;
            margin-top: 2px;
        }
        .net-words {
            font-size: 8.5pt;
            color: #334155;
            font-style: italic;
            margin-top: 3px;
        }
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-paid { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .badge-partial { background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; }
        .badge-pending { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 35px;
        }
        .sig-table td {
            width: 50%;
            vertical-align: bottom;
            font-size: 8.5pt;
        }
        .sig-line {
            width: 75%;
            border-top: 1px solid #334155;
            margin-top: 40px;
            padding-top: 4px;
            font-weight: bold;
            color: #1e293b;
        }
    </style>
    </head>
    <body>

        <!-- Edge-to-Edge Letterhead Header -->
        <htmlpageheader name="LetterHeader">
            <?php if ($hasHeader): ?>
                <div style="position: absolute; top:0; left:0; right:0; margin:0; padding:0; width:100%;">
                    <img src="<?= $headerImgPath ?>" style="width: 100%; margin:0; padding:0;" />
                </div>
            <?php else: ?>
                <div style="padding: 10px 12mm 0; border-bottom: 2px solid #024442;">
                    <strong style="font-size:14pt; color:#024442;">GRAPHICA FIX</strong>
                    <span style="font-size:8pt; color:#64748b; margin-left:10px;">Human Resources & Payroll</span>
                </div>
            <?php endif; ?>
        </htmlpageheader>

        <!-- Edge-to-Edge Letterhead Footer -->
        <htmlpagefooter name="LetterFooter">
            <?php if ($hasFooter): ?>
                <div style="position: absolute; bottom:0; left:0; right:0; margin:0; padding:0; width:100%;">
                    <img src="<?= $footerImgPath ?>" style="width: 100%; margin:0; padding:0;" />
                </div>
            <?php else: ?>
                <div style="padding: 0 12mm 6mm; text-align:center; font-size:7.5pt; color:#64748b; border-top: 1px solid #e2e8f0;">
                    Graphica Fix · Confidential Employee Salary Payslip · Generated on <?= date('d M Y, h:i A') ?>
                </div>
            <?php endif; ?>
        </htmlpagefooter>

        <!-- Payslip Title Block -->
        <div class="slip-header-title">
            <h1>CONFIDENTIAL SALARY PAYSLIP</h1>
            <p>PAY PERIOD: <?= strtoupper($period_label) ?> · REF: <?= $slip_ref ?></p>
        </div>

        <!-- Employee Info Metadata -->
        <table class="meta-table">
            <tr>
                <td class="meta-lbl">Employee Name:</td>
                <td class="meta-val"><strong><?= htmlspecialchars($emp_name) ?></strong></td>
                <td class="meta-lbl">Employee ID:</td>
                <td class="meta-val"><strong><?= htmlspecialchars($salary['employee_id'] ?: ('EMP' . str_pad($salary['user_id'], 4, '0', STR_PAD_LEFT))) ?></strong></td>
            </tr>
            <tr>
                <td class="meta-lbl">Designation:</td>
                <td class="meta-val"><?= htmlspecialchars($salary['job_title'] ?: ($salary['user_job_title'] ?: 'Staff')) ?></td>
                <td class="meta-lbl">Department:</td>
                <td class="meta-val"><?= htmlspecialchars($salary['department'] ?: ($salary['user_dept'] ?: 'General')) ?></td>
            </tr>
            <tr>
                <td class="meta-lbl">Shift Timing:</td>
                <td class="meta-val"><?= $shift_hours ?></td>
                <td class="meta-lbl">Payment Status:</td>
                <td class="meta-val">
                    <?php if ($salary['payment_status'] === 'Paid'): ?>
                        <span class="badge badge-paid">PAID IN FULL</span>
                    <?php elseif ($salary['payment_status'] === 'Partially Paid'): ?>
                        <span class="badge badge-partial">PARTIALLY PAID</span>
                    <?php else: ?>
                        <span class="badge badge-pending">PENDING</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (!empty($att_summary['days_present'])): ?>
            <tr>
                <td class="meta-lbl">Days Present:</td>
                <td class="meta-val"><?= intval($att_summary['days_present']) ?> Days</td>
                <td class="meta-lbl">Late Arrivals:</td>
                <td class="meta-val"><?= intval($att_summary['days_late']) ?> Day(s)<?= intval($att_summary['days_leave']) > 0 ? " (Leaves: {$att_summary['days_leave']})" : '' ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <!-- Dual Column Breakdown: Earnings vs Deductions -->
        <table class="dual-breakdown">
            <tr>
                <!-- LEFT: EARNINGS -->
                <td style="width: 49%; padding-right: 1%;">
                    <div class="section-heading">Earnings (Gross)</div>
                    <table class="breakdown-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="r">Amount (PKR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Basic Salary</td>
                                <td align="right"><?= number_format($salary['base_salary'], 2) ?></td>
                            </tr>
                            <tr>
                                <td>Allowances & Benefits</td>
                                <td align="right"><?= number_format($salary['allowances'], 2) ?></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td><strong>Gross Earnings</strong></td>
                                <td align="right"><strong>PKR <?= number_format($salary['gross_pay'], 2) ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </td>

                <!-- RIGHT: DEDUCTIONS -->
                <td style="width: 49%; padding-left: 1%;">
                    <div class="section-heading">Deductions & Recoveries</div>
                    <table class="breakdown-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="r">Amount (PKR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($itemized_deductions)): ?>
                                <?php foreach ($itemized_deductions as $id_row): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($id_row['deduction_type']) ?>
                                        <?php if (!empty($id_row['description'])): ?>
                                            <div style="font-size:7pt; color:#64748b;"><?= htmlspecialchars($id_row['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td align="right" style="color:#dc2626;">-<?= number_format($id_row['amount'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php if ($salary['deductions'] > 0): ?>
                                <tr>
                                    <td>Itemized Deductions</td>
                                    <td align="right" style="color:#dc2626;">-<?= number_format($salary['deductions'], 2) ?></td>
                                </tr>
                                <?php else: ?>
                                <tr>
                                    <td colspan="2" style="color:#64748b; font-style:italic;">No deductions incurred.</td>
                                </tr>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($salary['tax'] > 0): ?>
                            <tr>
                                <td>Income Tax</td>
                                <td align="right" style="color:#dc2626;">-<?= number_format($salary['tax'], 2) ?></td>
                            </tr>
                            <?php endif; ?>

                            <?php if ($salary['advance'] > 0): ?>
                            <tr>
                                <td>Salary Advance / Loan Recovery</td>
                                <td align="right" style="color:#dc2626;">-<?= number_format($salary['advance'], 2) ?></td>
                            </tr>
                            <?php endif; ?>

                            <?php if ($salary['previous_pending'] > 0): ?>
                            <tr>
                                <td>Previous Arrears Added</td>
                                <td align="right" style="color:#16a34a;">+<?= number_format($salary['previous_pending'], 2) ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td><strong>Total Deductions</strong></td>
                                <td align="right" style="color:#dc2626;"><strong>-PKR <?= number_format($salary['deductions'] + $salary['tax'] + $salary['advance'], 2) ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Net Take Home Pay Card -->
        <div class="net-card">
            <table width="100%" style="border-collapse:collapse;">
                <tr>
                    <td style="width:65%; vertical-align:middle;">
                        <div class="net-title">Net Take-Home Salary Payable</div>
                        <div class="net-amount">PKR <?= number_format($salary['net_salary'], 2) ?></div>
                        <div class="net-words">Amount in Words: <strong><?= $net_in_words ?></strong></div>
                    </td>
                    <td style="width:35%; text-align:right; vertical-align:middle;">
                        <div style="font-size:8pt; color:#475569;">Amount Disbursed:</div>
                        <div style="font-size:11pt; font-weight:bold; color:#0f172a;">PKR <?= number_format($salary['amount_paid'], 2) ?></div>
                        <?php $bal = max(0, $salary['net_salary'] - $salary['amount_paid']); ?>
                        <?php if ($bal > 0): ?>
                            <div style="font-size:8pt; color:#dc2626; font-weight:bold; margin-top:2px;">Remaining Balance: PKR <?= number_format($bal, 2) ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Note & Dispute Info -->
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; padding:6px 10px; font-size:7.5pt; color:#64748b; margin-top:6px;">
            <strong>Note:</strong> Standard shift: <strong><?= $shift_hours ?></strong> (8 hrs standard). Late arrival penalty applies after grace period (10 min Male / 30 min Female). If you find any discrepancies, you may raise a dispute ticket via the Employee Portal within 5 days of payslip issuance.
        </div>

        <!-- Signatures Block -->
        <table class="sig-table">
            <tr>
                <td>
                    <div class="sig-line">
                        Employee Signature: <?= htmlspecialchars($emp_name) ?><br>
                        <span style="font-size:7.5pt; font-weight:normal; color:#64748b;">Date: ________________________</span>
                    </div>
                </td>
                <td align="right">
                    <div class="sig-line" style="margin-left:auto; text-align:left;">
                        Authorized Signatory: Ibadullah<br>
                        <span style="font-size:7.5pt; font-weight:normal; color:#64748b;">Managing Director · Graphica Fix</span>
                    </div>
                </td>
            </tr>
        </table>

    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_top'    => $hasHeader ? 42 : 15,
        'margin_bottom' => $hasFooter ? 32 : 15,
        'margin_left'   => 12,
        'margin_right'  => 12,
        'margin_header' => 0,
        'margin_footer' => 0,
        'default_font'  => 'dejavusans'
    ]);

    $mpdf->SetTitle("Payslip - {$emp_name} - {$period_label}");
    $mpdf->SetAuthor('Graphica Fix Human Resources');
    $mpdf->WriteHTML($html);

    $outFileName = 'Payslip-' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $emp_name) . '-' . $sal_month . '.pdf';
    $mpdf->Output($outFileName, $dest);
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
$mpdf->Output($outFileName, $dest);
exit;
