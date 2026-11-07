<?php
/**
 * admin/salaries.php
 * HR Payroll & Salary Management Module
 * 
 * Features:
 * - 100% responsive layout that fits the screen width with ZERO horizontal scroll (Fit Screen Mode by default).
 * - Instant toggle between "Fit Screen (Consolidated)" and "All 12 Columns (Expanded Grid)".
 * - Responsive mobile card view for small tablets and phone screens.
 * - Prominent RED deduction/penalty buttons (#dc2626) as requested.
 * - Complete itemized deductions breakdown modal with ticket dispute statuses.
 * - Manual deduction/penalty entry modal for HR & Admins.
 * - Edit employee salary modal (Base, Allowances, Tax, Advance, Arrears, Status).
 * - Clean, minimalist executive design with neutral tones and zero rainbow clutter.
 */

require_once __DIR__ . '/dashboard_header.php';
require_once __DIR__ . '/../core/attendance_schema.php';
require_once __DIR__ . '/../core/attendance_helper.php';

ensure_attendance_schema($conn);

$is_hr_admin = ($is_super_admin || $is_admin_officer || $role === 'hrm' || $role === 'manager');
if (!$is_hr_admin) {
    echo "<div class='container my-5'><div class='alert alert-danger'><i class='fas fa-lock'></i> Access Denied. Only HR and Administrative Officers can view payroll.</div></div>";
    include('dashboard_footer.php');
    exit;
}

$filter_month = $_GET['month'] ?? date('Y-m');
$toast_message = '';
$toast_type    = '';

if (isset($_SESSION['toast_msg'])) {
    $toast_message = $_SESSION['toast_msg'];
    $toast_type    = $_SESSION['toast_type'] ?? 'success';
    unset($_SESSION['toast_msg'], $_SESSION['toast_type']);
}

// ── Handle POST Actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── 1. ADD SALARY TO REGISTER (WITH IN/OUT TIME DEDUCTIONS) ───────────────
    if ($action === 'add_salary') {
        $user_id          = intval($_POST['user_id'] ?? 0);
        $in_time          = trim($_POST['in_time'] ?? '09:00:00');
        $out_time         = trim($_POST['out_time'] ?? '18:00:00');
        $base_salary      = floatval($_POST['base_salary'] ?? 0);
        $allowances       = floatval($_POST['allowances'] ?? 0);
        $tax              = floatval($_POST['tax'] ?? 0);
        $advance          = floatval($_POST['advance'] ?? 0);
        $previous_pending = floatval($_POST['previous_pending'] ?? 0);
        $payment_status   = $_POST['payment_status'] ?? 'Pending';
        $department       = trim($_POST['department'] ?? 'General');
        $job_title        = trim($_POST['job_title'] ?? 'Staff');
        $employee_id      = trim($_POST['employee_id'] ?? '');
        $amount_paid      = floatval($_POST['amount_paid'] ?? 0);

        if ($user_id > 0 && $base_salary > 0) {
            $user_meta = getUserAttendanceMeta($conn, $user_id);
            $user_name = $user_meta['name'] ?: 'Staff';
            $gender    = !empty($user_meta['gender']) ? ucfirst(strtolower(trim($user_meta['gender']))) : 'Male';

            if (strlen($in_time) === 5) $in_time .= ':00';
            if (strlen($out_time) === 5) $out_time .= ':00';

            // Timing Deductions Logic
            $daily_wage  = round($base_salary / 30, 2);
            $hourly_wage = round($daily_wage / 8, 2);

            $grace_limit = ($gender === 'Female') ? '09:30:00' : '09:10:00';
            $late_deduction = 0.00;
            $late_desc = null;

            if ($in_time > $grace_limit) {
                $late_seconds = max(0, strtotime("2000-01-01 $in_time") - strtotime("2000-01-01 09:00:00"));
                $late_mins = round($late_seconds / 60);
                $late_deduction = max(100.00, round(($late_mins / 60) * $hourly_wage, 2));
                $late_desc = "Late arrival penalty: Checked in at " . date('h:i A', strtotime($in_time)) . " ({$late_mins} mins late)";
            }

            $shortfall_deduction = 0.00;
            $shortfall_desc = null;
            $worked_seconds = strtotime("2000-01-01 $out_time") - strtotime("2000-01-01 $in_time");
            $hours_worked = max(0, round($worked_seconds / 3600, 2));

            if ($hours_worked < 4.0) {
                $shortfall_deduction = round($daily_wage * 0.5, 2);
                $shortfall_desc = "Half-day attendance deduction: Worked {$hours_worked} hrs (< 4 hrs standard)";
            } elseif ($hours_worked < 8.0) {
                $shortfall_hrs = round(8.0 - $hours_worked, 2);
                $shortfall_deduction = round($shortfall_hrs * $hourly_wage, 2);
                $shortfall_desc = "Short hours deduction: Worked {$hours_worked} hrs ({$shortfall_hrs} hrs shortfall)";
            }

            $date_record = $filter_month . '-01';

            // Clear previous automatic timing deductions for this user & month to avoid duplication
            $conn->query("DELETE FROM salary_deductions WHERE user_id = {$user_id} AND salary_month = '{$filter_month}' AND (deduction_type LIKE 'Late Arrival Penalty%' OR deduction_type LIKE 'Short Hours%' OR deduction_type LIKE 'Half-day%')");

            if ($late_deduction > 0 && $late_desc) {
                $ins_late = $conn->prepare("INSERT INTO salary_deductions (user_id, salary_month, deduction_type, amount, description, date_incurred, status) VALUES (?, ?, 'Late Arrival Penalty', ?, ?, ?, 'applied')");
                if ($ins_late) {
                    $ins_late->bind_param("isdss", $user_id, $filter_month, $late_deduction, $late_desc, $date_record);
                    $ins_late->execute();
                    $ins_late->close();
                }
            }

            if ($shortfall_deduction > 0 && $shortfall_desc) {
                $ins_short = $conn->prepare("INSERT INTO salary_deductions (user_id, salary_month, deduction_type, amount, description, date_incurred, status) VALUES (?, ?, 'Short Hours / Early Exit', ?, ?, ?, 'applied')");
                if ($ins_short) {
                    $ins_short->bind_param("isdss", $user_id, $filter_month, $shortfall_deduction, $shortfall_desc, $date_record);
                    $ins_short->execute();
                    $ins_short->close();
                }
            }

            // Sum all itemized deductions for this user & month
            $stmt_ded = $conn->prepare("SELECT IFNULL(SUM(amount), 0) as total_ded FROM salary_deductions WHERE user_id = ? AND salary_month = ? AND status IN ('applied', 'disputed')");
            $total_deductions = 0.00;
            if ($stmt_ded) {
                $stmt_ded->bind_param("is", $user_id, $filter_month);
                $stmt_ded->execute();
                $total_deductions = floatval($stmt_ded->get_result()->fetch_assoc()['total_ded'] ?? 0);
                $stmt_ded->close();
            }

            $gross_pay = $base_salary + $allowances;
            $net_salary = max(0, $gross_pay - $tax - $advance - $total_deductions + $previous_pending);

            if ($payment_status === 'Paid') {
                $amount_paid = $net_salary;
            } elseif ($payment_status === 'Pending') {
                $amount_paid = 0.00;
            } else {
                $amount_paid = max(0.00, min($net_salary, $amount_paid));
            }

            // Update user profile fields in users table
            $stmt_u = $conn->prepare("UPDATE users SET base_salary = ?, allowances = ?, department = ?, job_title = ?, employee_id = ? WHERE user_id = ?");
            if ($stmt_u) {
                $stmt_u->bind_param("ddsssi", $base_salary, $allowances, $department, $job_title, $employee_id, $user_id);
                $stmt_u->execute();
                $stmt_u->close();
            }

            // Insert or Update into salaries table
            $stmt_sal = $conn->prepare("INSERT INTO salaries 
                (user_id, salary_month, employee_id, name, department, job_title, in_time, out_time, base_salary, allowances, gross_pay, tax, advance, deductions, previous_pending, net_salary, payment_status, amount_paid, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    employee_id = VALUES(employee_id),
                    name = VALUES(name),
                    department = VALUES(department),
                    job_title = VALUES(job_title),
                    in_time = VALUES(in_time),
                    out_time = VALUES(out_time),
                    base_salary = VALUES(base_salary),
                    allowances = VALUES(allowances),
                    gross_pay = VALUES(gross_pay),
                    tax = VALUES(tax),
                    advance = VALUES(advance),
                    deductions = VALUES(deductions),
                    previous_pending = VALUES(previous_pending),
                    net_salary = VALUES(net_salary),
                    payment_status = VALUES(payment_status),
                    amount_paid = VALUES(amount_paid),
                    updated_at = NOW()");

            if ($stmt_sal) {
                $stmt_sal->bind_param(
                    "isssssssddddddddsd",
                    $user_id,
                    $filter_month,
                    $employee_id,
                    $user_name,
                    $department,
                    $job_title,
                    $in_time,
                    $out_time,
                    $base_salary,
                    $allowances,
                    $gross_pay,
                    $tax,
                    $advance,
                    $total_deductions,
                    $previous_pending,
                    $net_salary,
                    $payment_status,
                    $amount_paid
                );
                $stmt_sal->execute();
                $stmt_sal->close();
            }

            $_SESSION['toast_msg'] = "{$user_name} added to salary register!";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_msg'] = "Please select a valid staff member and specify base salary.";
            $_SESSION['toast_type'] = "error";
        }
        header("Location: salaries.php?month={$filter_month}");
        exit;
    }

    // ── 2. EDIT SALARY & SHIFT TIMING ─────────────────────────────────────────
    if ($action === 'edit_salary') {
        $user_id          = intval($_POST['user_id'] ?? 0);
        $in_time          = trim($_POST['in_time'] ?? '09:00:00');
        $out_time         = trim($_POST['out_time'] ?? '18:00:00');
        $base_salary      = floatval($_POST['base_salary'] ?? 0);
        $allowances       = floatval($_POST['allowances'] ?? 0);
        $tax              = floatval($_POST['tax'] ?? 0);
        $advance          = floatval($_POST['advance'] ?? 0);
        $previous_pending = floatval($_POST['previous_pending'] ?? 0);
        $payment_status   = $_POST['payment_status'] ?? 'Pending';
        $department       = trim($_POST['department'] ?? 'General');
        $job_title        = trim($_POST['job_title'] ?? 'Staff');
        $employee_id      = trim($_POST['employee_id'] ?? '');
        $amount_paid      = floatval($_POST['amount_paid'] ?? 0);

        if ($user_id > 0) {
            $user_meta = getUserAttendanceMeta($conn, $user_id);
            $user_name = $user_meta['name'] ?: 'Staff';
            $gender    = !empty($user_meta['gender']) ? ucfirst(strtolower(trim($user_meta['gender']))) : 'Male';

            if (strlen($in_time) === 5) $in_time .= ':00';
            if (strlen($out_time) === 5) $out_time .= ':00';

            $daily_wage  = round($base_salary / 30, 2);
            $hourly_wage = round($daily_wage / 8, 2);

            $grace_limit = ($gender === 'Female') ? '09:30:00' : '09:10:00';
            $late_deduction = 0.00;
            $late_desc = null;

            if ($in_time > $grace_limit) {
                $late_seconds = max(0, strtotime("2000-01-01 $in_time") - strtotime("2000-01-01 09:00:00"));
                $late_mins = round($late_seconds / 60);
                $late_deduction = max(100.00, round(($late_mins / 60) * $hourly_wage, 2));
                $late_desc = "Late arrival penalty: Checked in at " . date('h:i A', strtotime($in_time)) . " ({$late_mins} mins late)";
            }

            $shortfall_deduction = 0.00;
            $shortfall_desc = null;
            $worked_seconds = strtotime("2000-01-01 $out_time") - strtotime("2000-01-01 $in_time");
            $hours_worked = max(0, round($worked_seconds / 3600, 2));

            if ($hours_worked < 4.0) {
                $shortfall_deduction = round($daily_wage * 0.5, 2);
                $shortfall_desc = "Half-day attendance deduction: Worked {$hours_worked} hrs (< 4 hrs standard)";
            } elseif ($hours_worked < 8.0) {
                $shortfall_hrs = round(8.0 - $hours_worked, 2);
                $shortfall_deduction = round($shortfall_hrs * $hourly_wage, 2);
                $shortfall_desc = "Short hours deduction: Worked {$hours_worked} hrs ({$shortfall_hrs} hrs shortfall)";
            }

            // Remove previous auto timing deductions for this month so they don't duplicate
            $conn->query("DELETE FROM salary_deductions WHERE user_id = {$user_id} AND salary_month = '{$filter_month}' AND (deduction_type LIKE 'Late Arrival Penalty%' OR deduction_type LIKE 'Short Hours%' OR deduction_type LIKE 'Half-day%')");

            $date_record = $filter_month . '-01';
            if ($late_deduction > 0 && $late_desc) {
                $ins_late = $conn->prepare("INSERT INTO salary_deductions (user_id, salary_month, deduction_type, amount, description, date_incurred, status) VALUES (?, ?, 'Late Arrival Penalty', ?, ?, ?, 'applied')");
                if ($ins_late) {
                    $ins_late->bind_param("isdss", $user_id, $filter_month, $late_deduction, $late_desc, $date_record);
                    $ins_late->execute();
                    $ins_late->close();
                }
            }
            if ($shortfall_deduction > 0 && $shortfall_desc) {
                $ins_short = $conn->prepare("INSERT INTO salary_deductions (user_id, salary_month, deduction_type, amount, description, date_incurred, status) VALUES (?, ?, 'Short Hours / Early Exit', ?, ?, ?, 'applied')");
                if ($ins_short) {
                    $ins_short->bind_param("isdss", $user_id, $filter_month, $shortfall_deduction, $shortfall_desc, $date_record);
                    $ins_short->execute();
                    $ins_short->close();
                }
            }

            $stmt_u = $conn->prepare("UPDATE users SET base_salary = ?, allowances = ?, department = ?, job_title = ?, employee_id = ? WHERE user_id = ?");
            if ($stmt_u) {
                $stmt_u->bind_param("ddsssi", $base_salary, $allowances, $department, $job_title, $employee_id, $user_id);
                $stmt_u->execute();
                $stmt_u->close();
            }

            $gross_pay = $base_salary + $allowances;
            $stmt_ded = $conn->prepare("SELECT IFNULL(SUM(amount), 0) as total_ded FROM salary_deductions WHERE user_id = ? AND salary_month = ? AND status IN ('applied', 'disputed')");
            $total_deductions = 0.00;
            if ($stmt_ded) {
                $stmt_ded->bind_param("is", $user_id, $filter_month);
                $stmt_ded->execute();
                $total_deductions = floatval($stmt_ded->get_result()->fetch_assoc()['total_ded'] ?? 0);
                $stmt_ded->close();
            }

            $net_salary = max(0, $gross_pay - $tax - $advance - $total_deductions + $previous_pending);

            if ($payment_status === 'Paid') {
                $amount_paid = $net_salary;
            } elseif ($payment_status === 'Pending') {
                $amount_paid = 0.00;
            } else {
                $amount_paid = max(0.00, min($net_salary, $amount_paid));
            }

            $stmt_sal = $conn->prepare("INSERT INTO salaries 
                (user_id, salary_month, employee_id, name, department, job_title, in_time, out_time, base_salary, allowances, gross_pay, tax, advance, deductions, previous_pending, net_salary, payment_status, amount_paid, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    employee_id = VALUES(employee_id),
                    name = VALUES(name),
                    department = VALUES(department),
                    job_title = VALUES(job_title),
                    in_time = VALUES(in_time),
                    out_time = VALUES(out_time),
                    base_salary = VALUES(base_salary),
                    allowances = VALUES(allowances),
                    gross_pay = VALUES(gross_pay),
                    tax = VALUES(tax),
                    advance = VALUES(advance),
                    deductions = VALUES(deductions),
                    previous_pending = VALUES(previous_pending),
                    net_salary = VALUES(net_salary),
                    payment_status = VALUES(payment_status),
                    amount_paid = VALUES(amount_paid),
                    updated_at = NOW()");

            if ($stmt_sal) {
                $stmt_sal->bind_param(
                    "isssssssddddddddsd",
                    $user_id,
                    $filter_month,
                    $employee_id,
                    $user_name,
                    $department,
                    $job_title,
                    $in_time,
                    $out_time,
                    $base_salary,
                    $allowances,
                    $gross_pay,
                    $tax,
                    $advance,
                    $total_deductions,
                    $previous_pending,
                    $net_salary,
                    $payment_status,
                    $amount_paid
                );
                $stmt_sal->execute();
                $stmt_sal->close();
            }

            $_SESSION['toast_msg'] = "Salary record updated successfully!";
            $_SESSION['toast_type'] = "success";
        }
        header("Location: salaries.php?month={$filter_month}");
        exit;
    }

    // ── 3. REMOVE SALARY RECORD DIRECTLY FROM REGISTER ────────────────────────
    if ($action === 'remove_salary') {
        $salary_id = intval($_POST['salary_id'] ?? 0);
        $del_user_id = intval($_POST['user_id'] ?? 0);

        if ($salary_id > 0) {
            $stmt_del = $conn->prepare("DELETE FROM salaries WHERE id = ? AND salary_month = ?");
            if ($stmt_del) {
                $stmt_del->bind_param("is", $salary_id, $filter_month);
                $stmt_del->execute();
                $stmt_del->close();
            }
        } elseif ($del_user_id > 0) {
            $stmt_del = $conn->prepare("DELETE FROM salaries WHERE user_id = ? AND salary_month = ?");
            if ($stmt_del) {
                $stmt_del->bind_param("is", $del_user_id, $filter_month);
                $stmt_del->execute();
                $stmt_del->close();
            }
        }

        $_SESSION['toast_msg'] = "Employee removed from salary register for " . date('F Y', strtotime($filter_month . '-01'));
        $_SESSION['toast_type'] = "success";
        header("Location: salaries.php?month={$filter_month}");
        exit;
    }

    // ── 4. ADD CUSTOM DEDUCTION / PENALTY ──────────────────────────────────────
    if ($action === 'add_custom_deduction') {
        $user_id        = intval($_POST['user_id'] ?? 0);
        $deduction_type = trim($_POST['deduction_type'] ?? 'Penalty');
        $amount         = floatval($_POST['amount'] ?? 0);
        $description    = trim($_POST['description'] ?? '');
        $date_incurred  = $_POST['date_incurred'] ?? date('Y-m-d');

        if ($user_id > 0 && $amount > 0) {
            $stmt = $conn->prepare("INSERT INTO salary_deductions (user_id, salary_month, deduction_type, amount, description, date_incurred, status) VALUES (?, ?, ?, ?, ?, ?, 'applied')");
            if ($stmt) {
                $stmt->bind_param("issdss", $user_id, $filter_month, $deduction_type, $amount, $description, $date_incurred);
                $stmt->execute();
                $stmt->close();

                // If user is registered in this month's salaries, update their deductions & net salary
                $chk_sal = $conn->prepare("SELECT id, base_salary, allowances, tax, advance, previous_pending FROM salaries WHERE user_id = ? AND salary_month = ? LIMIT 1");
                if ($chk_sal) {
                    $chk_sal->bind_param("is", $user_id, $filter_month);
                    $chk_sal->execute();
                    $s_row = $chk_sal->get_result()->fetch_assoc();
                    $chk_sal->close();
                    if ($s_row) {
                        $stmt_dsum = $conn->prepare("SELECT IFNULL(SUM(amount), 0) as tot FROM salary_deductions WHERE user_id = ? AND salary_month = ? AND status IN ('applied', 'disputed')");
                        $stmt_dsum->bind_param("is", $user_id, $filter_month);
                        $stmt_dsum->execute();
                        $tot_ded = floatval($stmt_dsum->get_result()->fetch_assoc()['tot'] ?? 0);
                        $stmt_dsum->close();

                        $gross = floatval($s_row['base_salary']) + floatval($s_row['allowances']);
                        $net = max(0, $gross - floatval($s_row['tax']) - floatval($s_row['advance']) - $tot_ded + floatval($s_row['previous_pending']));

                        $upd_s = $conn->prepare("UPDATE salaries SET deductions = ?, net_salary = ? WHERE id = ?");
                        if ($upd_s) {
                            $upd_s->bind_param("ddi", $tot_ded, $net, $s_row['id']);
                            $upd_s->execute();
                            $upd_s->close();
                        }
                    }
                }

                $_SESSION['toast_msg'] = "Deduction of PKR " . number_format($amount) . " applied successfully!";
                $_SESSION['toast_type'] = "success";
            }
        } else {
            $_SESSION['toast_msg'] = "Please select an employee and specify a valid amount.";
            $_SESSION['toast_type'] = "error";
        }
        header("Location: salaries.php?month={$filter_month}");
        exit;
    }
}

// ── Fetch Active Staff Users list (strictly active staff, excluding student & client roles) ─────
$active_staff_users = [];
$u_all = $conn->query("
    SELECT user_id, name, username, employee_id, department, job_title, role, gender, base_salary, allowances 
    FROM users 
    WHERE status = 'active' AND role NOT IN ('client', 'client_sub', 'student') 
    ORDER BY name ASC
");
if ($u_all) {
    while ($urow = $u_all->fetch_assoc()) {
        $urow['gender']      = !empty($urow['gender']) ? ucfirst(strtolower(trim($urow['gender']))) : 'Male';
        $urow['base_salary'] = floatval($urow['base_salary'] ?: 50000);
        $urow['allowances']  = floatval($urow['allowances'] ?: 0);
        $active_staff_users[] = $urow;
    }
}
$users_for_deduction = $active_staff_users;

// ── Fetch Salaries for Filter Month ───────────────────────────────────────────
$stmt_list = $conn->prepare("
    SELECT s.*, u.name as user_name, u.username, u.email, u.gender, u.role
    FROM salaries s
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE s.salary_month = ?
    ORDER BY FIELD(u.role, 'super_admin', 'superadmin', 'admin', 'hrm', 'pm', 'manager', 'sales', 'user', 'teacher'), s.name ASC
");
$stmt_list->bind_param("s", $filter_month);
$stmt_list->execute();
$salaries_res = $stmt_list->get_result();

$salaries = [];
$total_payroll        = 0.00;
$total_gross          = 0.00;
$total_deductions     = 0.00;
$total_paid           = 0.00;
$total_pending        = 0.00;
$count_paid           = 0;
$count_partially_paid = 0;
$count_pending        = 0;

if ($salaries_res) {
    while ($row = $salaries_res->fetch_assoc()) {
        $salaries[] = $row;
        $total_gross      += floatval($row['gross_pay']);
        $total_payroll    += floatval($row['net_salary']);
        $total_deductions += floatval($row['deductions']);
        $net_sal           = floatval($row['net_salary']);
        $amt_paid          = floatval($row['amount_paid'] ?? 0);

        if ($row['payment_status'] === 'Paid') {
            $paid = ($amt_paid > 0) ? $amt_paid : $net_sal;
            $total_paid += $paid;
            $count_paid++;
        } elseif ($row['payment_status'] === 'Partially Paid') {
            $total_paid += $amt_paid;
            $total_pending += max(0, $net_sal - $amt_paid);
            $count_partially_paid++;
        } else {
            $total_pending += $net_sal;
            $count_pending++;
        }
    }
}
$stmt_list->close();

// Fetch deductions detail map for modal previews
$deductions_map = [];
$existing_deductions_sums = [];
$ded_res = $conn->query("
    SELECT d.*, u.name as user_name, t.status as ticket_status, t.ticket_number 
    FROM salary_deductions d
    LEFT JOIN users u ON d.user_id = u.user_id
    LEFT JOIN deduction_tickets t ON d.id = t.deduction_id
    WHERE d.salary_month = '{$filter_month}'
    ORDER BY d.date_incurred DESC
");
if ($ded_res) {
    while ($d = $ded_res->fetch_assoc()) {
        $deductions_map[$d['user_id']][] = $d;
        if (in_array($d['status'], ['applied', 'disputed'])) {
            $existing_deductions_sums[$d['user_id']] = ($existing_deductions_sums[$d['user_id']] ?? 0.00) + floatval($d['amount']);
        }
    }
}
$registered_user_ids = !empty($salaries) ? array_map('intval', array_column($salaries, 'user_id')) : [];

// Count open dispute tickets
$open_tickets_count = 0;
$tkt_res = $conn->query("SELECT COUNT(*) as cnt FROM deduction_tickets WHERE status IN ('open', 'under_review')");
if ($tkt_res) {
    $open_tickets_count = intval($tkt_res->fetch_assoc()['cnt'] ?? 0);
}
?>

<style>
/* ── Ensure zero window overflow ── */
html, body {
    max-width: 100% !important;
    overflow-x: hidden !important;
}

.height-100 {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
}

.payroll-container {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    padding-bottom: 30px;
}

/* ── Minimalist Stats Row ── */
.payroll-stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
    width: 100%;
    box-sizing: border-box;
}
.payroll-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 14px;
    box-sizing: border-box;
}
.payroll-stat-lbl {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.payroll-stat-num {
    font-size: 17px;
    font-weight: 700;
    color: #0f172a;
    margin-top: 2px;
}
.payroll-stat-sub {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 2px;
}

/* ── Main Clean Card ── */
.payroll-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    overflow: hidden;
}

.payroll-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    padding: 14px 18px;
    border-bottom: 1px solid #e2e8f0;
    background: #ffffff;
    width: 100%;
    box-sizing: border-box;
}
.payroll-header h2 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
}
.payroll-header .subtext {
    font-size: 11.5px;
    color: #64748b;
    display: block;
    margin-top: 2px;
}

.payroll-controls {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

/* ── BOLD RED ACTION BUTTONS ── */
.btn-red-deduction {
    background: #dc2626 !important;
    color: #ffffff !important;
    border: 1px solid #b91c1c !important;
    font-weight: 700 !important;
    padding: 6px 14px !important;
    border-radius: 6px !important;
    font-size: 12px !important;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(220, 38, 38, 0.25);
    transition: all 0.15s ease;
}
.btn-red-deduction:hover {
    background: #b91c1c !important;
    color: #ffffff !important;
    box-shadow: 0 3px 8px rgba(185, 28, 28, 0.35);
}

.btn-table-penalty {
    background: #dc2626 !important;
    border: 1px solid #b91c1c !important;
    color: #ffffff !important;
    padding: 4px 9px !important;
    border-radius: 5px !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.15s ease;
}
.btn-table-penalty:hover {
    background: #b91c1c !important;
    color: #ffffff !important;
}

/* Red Deduction Chip in Tables */
.deduction-chip-red {
    background: #fef2f2 !important;
    color: #dc2626 !important;
    border: 1.5px solid #dc2626 !important;
    padding: 3px 8px !important;
    border-radius: 5px !important;
    font-weight: 700 !important;
    font-size: 11px !important;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.15s ease;
}
.deduction-chip-red:hover {
    background: #dc2626 !important;
    color: #ffffff !important;
}

/* ── View Mode Switcher Toggle ── */
.view-toggle-grp {
    display: inline-flex;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    overflow: hidden;
    background: #f8fafc;
}
.view-toggle-btn {
    padding: 6px 10px;
    font-size: 11.5px;
    font-weight: 600;
    color: #64748b;
    background: transparent;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.15s;
}
.view-toggle-btn.active {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    font-weight: 700;
}

/* Search & Inputs */
.header-search {
    position: relative;
    width: 170px;
}
.header-search input {
    width: 100%;
    padding: 6px 10px 6px 28px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 12px;
    outline: none;
    background: #ffffff;
    font-family: inherit;
    box-sizing: border-box;
    color: #0f172a;
}
.header-search input:focus {
    border-color: #024442;
}
.header-search i {
    position: absolute;
    left: 9px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 11px;
}
.month-picker-input {
    padding: 6px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 12px;
    outline: none;
    background: #ffffff;
    color: #0f172a;
    font-family: inherit;
}
.btn-clean-secondary {
    background: #ffffff;
    color: #475569;
    border: 1px solid #cbd5e1;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-clean-secondary:hover {
    background: #f8fafc;
    color: #0f172a;
}

/* ── 1. FIT-SCREEN SMART TABLE (DEFAULT - 100% WIDTH, ZERO HORIZONTAL SCROLL) ── */
.fit-table-box {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow: hidden;
    background: #ffffff;
}
.fit-table {
    width: 100%;
    max-width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    table-layout: auto;
}
.fit-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
}
.fit-table td {
    padding: 11px 12px;
    border-bottom: 1px solid #f1f5f9;
    color: #0f172a;
    vertical-align: middle;
}
.fit-table tr:hover td {
    background: #f8fafc;
}

/* ── 2. EXPANDED ALL-COLUMNS TABLE (FOR ULTRA-WIDE BROWSING) ── */
.all-cols-box {
    display: none;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    background: #ffffff;
}
.all-cols-table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
    font-size: 12px;
}
.all-cols-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.all-cols-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #f1f5f9;
    color: #0f172a;
    vertical-align: middle;
    white-space: nowrap;
}
.all-cols-table tr:hover td {
    background: #f8fafc;
}
.all-cols-table .sticky-col {
    position: sticky;
    left: 0;
    background: #ffffff;
    z-index: 2;
    border-right: 1px solid #e2e8f0;
    box-shadow: 2px 0 5px rgba(0,0,0,0.03);
}
.all-cols-table th.sticky-col {
    background: #f8fafc;
    z-index: 3;
}
.all-cols-table tr:hover td.sticky-col {
    background: #f8fafc;
}

/* ── 3. RESPONSIVE MOBILE CARD VIEW (< 860px) ── */
.payroll-cards-container {
    display: none;
    padding: 14px;
    gap: 12px;
    flex-direction: column;
}
.payroll-emp-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px;
    box-sizing: border-box;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.payroll-emp-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 10px;
    border-bottom: 1px solid #f1f5f9;
    margin-bottom: 10px;
}
.payroll-emp-card-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 12px;
    font-size: 11.5px;
    margin-bottom: 12px;
}
.card-data-item .card-lbl {
    font-size: 10px;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 600;
}
.card-data-item .card-val {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    margin-top: 1px;
}
.payroll-emp-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 10px;
    border-top: 1px solid #f1f5f9;
}

@media (max-width: 860px) {
    .fit-table-box { display: none !important; }
    .all-cols-box { display: none !important; }
    .payroll-cards-container { display: flex !important; }
    .view-toggle-grp { display: none !important; }
}

/* Status Tags */
.status-clean {
    font-size: 10px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 4px;
    display: inline-block;
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.status-clean.Paid {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
}
.status-clean.Pending {
    background: #f8fafc;
    color: #64748b;
    border: 1px solid #cbd5e1;
}
.status-clean.Partially_Paid {
    background: #fff7ed;
    color: #c2410c;
    border: 1px solid #fed7aa;
}
.status-clean.On_Hold {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fee2e2;
}

/* Action Edit Button */
.btn-table-edit {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
    padding: 4px 8px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.btn-table-edit:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #94a3b8;
}

/* Action Remove Button */
.btn-table-remove {
    background: #ffffff !important;
    border: 1px solid #fecaca !important;
    color: #dc2626 !important;
    padding: 4px 8px !important;
    border-radius: 5px !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    cursor: pointer;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
/* Action Slip Button */
.btn-table-slip {
    background: #f0fdf4 !important;
    border: 1px solid #bbf7d0 !important;
    color: #166534 !important;
    padding: 4px 8px !important;
    border-radius: 5px !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    cursor: pointer;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-decoration: none !important;
}
.btn-table-slip:hover {
    background: #166534 !important;
    color: #ffffff !important;
    border-color: #166534 !important;
}
.btn-table-remove:hover {
    background: #fef2f2 !important;
    border-color: #dc2626 !important;
    color: #b91c1c !important;
}

/* Add Salary Button */
.btn-add-salary {
    background: #024442 !important;
    color: #ffffff !important;
    border: 1px solid #01302e !important;
    font-weight: 700 !important;
    padding: 6px 14px !important;
    border-radius: 6px !important;
    font-size: 12px !important;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(2, 68, 66, 0.25);
    transition: all 0.15s ease;
}
.btn-add-salary:hover {
    background: #035c59 !important;
    color: #ffffff !important;
    box-shadow: 0 3px 8px rgba(2, 68, 66, 0.35);
}

/* Modals */
.modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px);
    z-index: 9999; display: none; align-items: center; justify-content: center; padding: 16px;
    overflow-y: auto !important; -webkit-overflow-scrolling: touch; box-sizing: border-box;
}
.modal-overlay.active { display: flex; }
.modal-content {
    background: #ffffff; border-radius: 8px; width: 100%; max-width: 520px; max-height: 90vh !important;
    display: flex; flex-direction: column; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15); border: 1px solid #e2e8f0; overflow-y: auto !important; box-sizing: border-box;
}
.modal-header {
    background: #f8fafc; color: #0f172a; padding: 12px 18px; border-bottom: 1px solid #e2e8f0;
    display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;
}
.modal-header.modal-header-red {
    background: #dc2626; color: #ffffff; border-bottom: none;
}
.modal-header h4 { margin: 0; font-size: 1.05rem; font-weight: 700; }
.modal-body { padding: 18px; overflow-y: auto !important; flex: 1 1 auto; min-height: 0; }
.modal-footer {
    padding: 12px 18px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 8px; background: #f8fafc; flex-shrink: 0;
}
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.form-group { margin-bottom: 10px; }
.form-group label { display: block; font-weight: 600; font-size: 11.5px; color: #334155; margin-bottom: 3px; }
.form-group input, .form-group select, .form-group textarea {
    width: 100%; padding: 7px 9px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 12px; outline: none; background: #fff; box-sizing: border-box;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    border-color: #024442;
}
</style>

<div class="payroll-container">

    <!-- Minimalist Stats Overview -->
    <div class="payroll-stats-row">
        <div class="payroll-stat-card">
            <div class="payroll-stat-lbl">Net Payroll</div>
            <div class="payroll-stat-num">PKR <?= number_format($total_payroll) ?></div>
            <div class="payroll-stat-sub"><?= count($salaries) ?> Total Staff</div>
        </div>

        <div class="payroll-stat-card">
            <div class="payroll-stat-lbl">Paid Amount</div>
            <div class="payroll-stat-num">PKR <?= number_format($total_paid) ?></div>
            <div class="payroll-stat-sub"><?= $count_paid ?> Paid<?= $count_partially_paid > 0 ? " · {$count_partially_paid} Partial" : '' ?></div>
        </div>

        <div class="payroll-stat-card">
            <div class="payroll-stat-lbl">Pending Amount</div>
            <div class="payroll-stat-num">PKR <?= number_format($total_pending) ?></div>
            <div class="payroll-stat-sub"><?= $count_pending ?> Awaiting<?= $count_partially_paid > 0 ? " · {$count_partially_paid} Balance" : '' ?></div>
        </div>

        <div class="payroll-stat-card">
            <div class="payroll-stat-lbl">Total Deductions</div>
            <div class="payroll-stat-num" style="color: #dc2626;">PKR <?= number_format($total_deductions) ?></div>
            <div class="payroll-stat-sub">Penalties & Adjustments</div>
        </div>

        <?php if ($open_tickets_count > 0): ?>
        <div class="payroll-stat-card">
            <div class="payroll-stat-lbl">Dispute Tickets</div>
            <div class="payroll-stat-num"><?= $open_tickets_count ?></div>
            <div class="payroll-stat-sub"><a href="deduction_tickets.php" style="color:#024442; text-decoration:underline;">Review Tickets</a></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Main Salaries Card -->
    <div class="payroll-card">
        <div class="payroll-header">
            <div>
                <h2>Payroll Register</h2>
                <span class="subtext">
                    Month: <strong><?= date('F Y', strtotime($filter_month . '-01')) ?></strong> · <?= count($salaries) ?> Active Records
                </span>
            </div>

            <div class="payroll-controls">
                <!-- View Mode Toggle (Fit Screen vs All Columns) -->
                <div class="view-toggle-grp">
                    <button type="button" class="view-toggle-btn active" id="btnModeFit" onclick="setViewMode('fit')" title="Clean responsive view that fits your screen width perfectly">
                        <i class="fas fa-compress-arrows-alt"></i> Fit Screen
                    </button>
                    <button type="button" class="view-toggle-btn" id="btnModeAll" onclick="setViewMode('all')" title="Detailed spreadsheet view with all 12 individual columns">
                        <i class="fas fa-table"></i> All Columns
                    </button>
                </div>

                <!-- Clean Search Input -->
                <div class="header-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="payrollSearchInput" placeholder="Search employee..." onkeyup="filterPayrollRecords()">
                </div>

                <!-- Month Picker -->
                <form method="GET" style="margin:0;">
                    <input type="month" name="month" class="month-picker-input" value="<?= htmlspecialchars($filter_month) ?>" onchange="this.form.submit()">
                </form>

                <!-- ADD SALARY BUTTON -->
                <button type="button" class="btn-add-salary" onclick="openAddSalaryModal()" title="Add an employee to this month's register">
                    <i class="fas fa-user-plus"></i> Add Salary
                </button>

                <!-- PROMINENT RED DEDUCTION / PENALTY BUTTON -->
                <button type="button" class="btn-red-deduction" onclick="openAddDeductionModal()" title="Add itemized deduction or penalty">
                    <i class="fas fa-minus-circle"></i> Add Deduction
                </button>

                <!-- Print Payroll PDF (mPDF) -->
                <a href="generate_salary_pdf.php?month=<?= urlencode($filter_month) ?>" target="_blank" class="btn-clean-secondary" title="Export / Print Payroll PDF (mPDF)" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-file-pdf" style="color:#ef4444;"></i> Print PDF
                </a>
            </div>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────── -->
        <!-- VIEW 1: FIT-SCREEN TABLE (100% SCREEN WIDTH, ZERO HORIZONTAL SCROLL)-->
        <!-- ─────────────────────────────────────────────────────────────────── -->
        <div class="fit-table-box" id="fitTableBox">
            <table class="fit-table">
                <thead>
                    <tr>
                        <th style="text-align: left; width: 28%;">Employee & Position</th>
                        <th style="text-align: right; width: 18%;">Earnings (Gross)</th>
                        <th style="text-align: right; width: 20%;">Deductions & Arrears</th>
                        <th style="text-align: right; width: 18%;">Net & Paid</th>
                        <th style="text-align: center; width: 8%;">Status</th>
                        <th style="text-align: center; width: 12%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="fitTableBody">
                    <?php if (empty($salaries)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding: 40px 20px; color:#64748b;">
                            <i class="fas fa-file-invoice-dollar" style="font-size: 32px; color: #cbd5e1; margin-bottom: 8px; display: block;"></i>
                            <strong style="color: #0f172a; font-size: 13px;">No payroll records found for <?= date('F Y', strtotime($filter_month . '-01')) ?>.</strong>
                            <p style="margin: 6px 0 12px; font-size: 11.5px; color: #64748b;">This month's register is currently empty. Add staff members directly using the button below.</p>
                            <button type="button" class="btn-add-salary" onclick="openAddSalaryModal()">
                                <i class="fas fa-user-plus"></i> Add Employee to Register
                            </button>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($salaries as $sal): 
                        $user_deductions = $deductions_map[$sal['user_id']] ?? [];
                        $clean_status = str_replace(' ', '_', $sal['payment_status']);
                    ?>
                    <tr class="payroll-row-fit">
                        <!-- 1. Employee, ID, Department, Job Title & Shift -->
                        <td>
                            <div style="font-weight: 700; color: #0f172a; font-size: 13px;">
                                <?= htmlspecialchars($sal['user_name']) ?>
                            </div>
                            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                <span style="font-family: monospace; font-weight: 600; color: #475569;">
                                    <?= htmlspecialchars($sal['employee_id'] ?: 'EMP-' . str_pad($sal['user_id'], 3, '0', STR_PAD_LEFT)) ?>
                                </span>
                                · <span><?= htmlspecialchars($sal['job_title'] ?: 'Staff') ?></span>
                                <span style="color: #94a3b8;">(<?= htmlspecialchars($sal['department'] ?: 'General') ?>)</span>
                            </div>
                            <div style="font-size: 10.5px; color: #475569; margin-top: 3px; display: inline-flex; align-items: center; gap: 4px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 1px 6px; border-radius: 4px;">
                                <i class="far fa-clock" style="color:#024442;"></i> Shift: <?= date('h:i A', strtotime($sal['in_time'] ?: '09:00:00')) ?> - <?= date('h:i A', strtotime($sal['out_time'] ?: '18:00:00')) ?>
                            </div>
                        </td>

                        <!-- 2. Gross Earnings Breakdown (Gross, Base, Allowances) -->
                        <td style="text-align: right;">
                            <div style="font-weight: 700; color: #0f172a; font-size: 13px; font-variant-numeric: tabular-nums;">
                                PKR <?= number_format($sal['gross_pay']) ?>
                            </div>
                            <div style="font-size: 10.5px; color: #64748b; margin-top: 2px; font-variant-numeric: tabular-nums;">
                                Base: <?= number_format($sal['base_salary']) ?> · Allow: <?= number_format($sal['allowances']) ?>
                            </div>
                        </td>

                        <!-- 3. Deductions & Arrears (RED DEDUCTION BUTTON) -->
                        <td style="text-align: right;">
                            <div>
                                <?php if ($sal['deductions'] > 0): ?>
                                    <button type="button" class="deduction-chip-red" onclick="viewDeductions(<?= htmlspecialchars(json_encode($sal)) ?>, <?= htmlspecialchars(json_encode($user_deductions)) ?>)" title="Click to view detailed itemized deductions">
                                        <i class="fas fa-receipt"></i> -PKR <?= number_format($sal['deductions']) ?>
                                    </button>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 11px; font-weight: 600;">0 Deductions</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 10.5px; color: #64748b; margin-top: 3px; font-variant-numeric: tabular-nums;">
                                Tax: <?= number_format($sal['tax']) ?> · Adv: <?= number_format($sal['advance']) ?>
                                <?php if ($sal['previous_pending'] > 0): ?>
                                    · <span style="color: #15803d; font-weight: 600;">Arr: +<?= number_format($sal['previous_pending']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- 4. Net Salary & Paid/Remaining -->
                        <td style="text-align: right;">
                            <div style="font-weight: 800; color: #0f172a; font-size: 13.5px; font-variant-numeric: tabular-nums;">
                                PKR <?= number_format($sal['net_salary']) ?>
                            </div>
                            <?php if ($sal['payment_status'] === 'Partially Paid'): ?>
                                <div style="font-size: 10.5px; color: #c2410c; margin-top: 2px; font-weight: 700; font-variant-numeric: tabular-nums;">
                                    Paid: <?= number_format($sal['amount_paid']) ?> · Due: <?= number_format(max(0, $sal['net_salary'] - $sal['amount_paid'])) ?>
                                </div>
                            <?php elseif ($sal['payment_status'] === 'Paid'): ?>
                                <div style="font-size: 10px; color: #15803d; margin-top: 2px; font-weight: 600;">
                                    <i class="fas fa-check-circle"></i> Settled (<?= number_format($sal['amount_paid'] > 0 ? $sal['amount_paid'] : $sal['net_salary']) ?>)
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- 5. Payment Status -->
                        <td style="text-align: center;">
                            <span class="status-clean <?= $clean_status ?>"><?= htmlspecialchars($sal['payment_status']) ?></span>
                            <?php if ($sal['payment_status'] === 'Partially Paid'): 
                                $pct = ($sal['net_salary'] > 0) ? round(($sal['amount_paid'] / $sal['net_salary']) * 100) : 0;
                            ?>
                                <div style="font-size: 9.5px; color: #c2410c; font-weight: 700; margin-top: 2px;">
                                    <?= $pct ?>% Paid
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- 6. Actions (Edit, Penalty, Remove) -->
                        <td style="text-align: center;">
                            <div style="display: inline-flex; gap: 4px; align-items: center; justify-content: center;">
                                <button type="button" class="btn-table-edit" onclick="openEditSalaryModal(<?= htmlspecialchars(json_encode($sal)) ?>)" title="Edit Salary Parameters">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button type="button" class="btn-table-penalty" onclick="openAddDeductionModal(<?= $sal['user_id'] ?>, '<?= htmlspecialchars(addslashes($sal['user_name'])) ?>')" title="Add Penalty or Deduction">
                                    <i class="fas fa-minus-circle"></i> Penalty
                                </button>
                                <a href="generate_salary_pdf.php?salary_id=<?= $sal['id'] ?>" target="_blank" class="btn-table-slip" title="Print Salary Slip (mPDF)">
                                    <i class="fas fa-file-invoice"></i> Slip
                                </a>
                                <button type="button" class="btn-table-remove" onclick="confirmRemoveSalary(<?= $sal['id'] ?>, <?= $sal['user_id'] ?>, '<?= htmlspecialchars(addslashes($sal['user_name'])) ?>')" title="Remove from Salary Register">
                                    <i class="fas fa-trash-alt"></i> Remove
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────── -->
        <!-- VIEW 2: ALL 12 COLUMNS EXPANDED GRID (FOR FULL DETAILED VIEW)       -->
        <!-- ─────────────────────────────────────────────────────────────────── -->
        <div class="all-cols-box" id="allColsBox">
            <table class="all-cols-table" id="allColsTable">
                <thead>
                    <tr>
                        <th class="sticky-col" style="min-width: 180px; text-align: left;">Employee</th>
                        <th style="text-align: center; white-space: nowrap;">Shift (In - Out)</th>
                        <th style="text-align: left;">Department</th>
                        <th style="text-align: left;">Job Title</th>
                        <th style="text-align: right;">Base Salary</th>
                        <th style="text-align: right;">Allowances</th>
                        <th style="text-align: right;">Gross Pay</th>
                        <th style="text-align: right;">Tax</th>
                        <th style="text-align: right;">Advance</th>
                        <th style="text-align: right;">Deductions</th>
                        <th style="text-align: right;">Pending</th>
                        <th style="text-align: right;">Net Salary</th>
                        <th style="text-align: right;">Paid (PKR)</th>
                        <th style="text-align: right;">Balance Due</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="allColsTableBody">
                    <?php if (empty($salaries)): ?>
                    <tr>
                        <td colspan="16" style="text-align:center; padding: 40px 20px; color:#94a3b8;">
                            <strong>No payroll records found for <?= date('F Y', strtotime($filter_month . '-01')) ?>.</strong>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($salaries as $sal): 
                        $user_deductions = $deductions_map[$sal['user_id']] ?? [];
                        $clean_status = str_replace(' ', '_', $sal['payment_status']);
                    ?>
                    <tr class="payroll-row-all">
                        <!-- 1. Employee Name & ID (Sticky) -->
                        <td class="sticky-col">
                            <div style="font-weight: 700; color: #0f172a; font-size: 12.5px;">
                                <?= htmlspecialchars($sal['user_name']) ?>
                            </div>
                            <div style="font-size: 10.5px; color: #64748b; font-family: monospace; margin-top: 1px;">
                                <?= htmlspecialchars($sal['employee_id'] ?: 'EMP-' . str_pad($sal['user_id'], 3, '0', STR_PAD_LEFT)) ?>
                                <span style="font-family: sans-serif; color: #94a3b8;">· @<?= htmlspecialchars($sal['username']) ?></span>
                            </div>
                        </td>

                        <!-- 2. Shift Timing (In - Out) -->
                        <td style="text-align: center;">
                            <span style="font-family: monospace; font-size: 11px; background: #f8fafc; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0; color: #334155; white-space: nowrap;">
                                <?= date('h:i A', strtotime($sal['in_time'] ?: '09:00:00')) ?> - <?= date('h:i A', strtotime($sal['out_time'] ?: '18:00:00')) ?>
                            </span>
                        </td>

                        <!-- 2. Department -->
                        <td>
                            <span style="color: #475569; font-size: 11.5px;"><?= htmlspecialchars($sal['department'] ?: 'General') ?></span>
                        </td>

                        <!-- 3. Job Title -->
                        <td>
                            <span style="font-weight: 600; color: #1e293b; font-size: 11.5px;"><?= htmlspecialchars($sal['job_title'] ?: 'Staff') ?></span>
                        </td>

                        <!-- 4. Base Salary -->
                        <td style="text-align: right; font-variant-numeric: tabular-nums;">
                            <?= number_format($sal['base_salary']) ?>
                        </td>

                        <!-- 5. Allowances -->
                        <td style="text-align: right; color: #64748b; font-variant-numeric: tabular-nums;">
                            <?= number_format($sal['allowances']) ?>
                        </td>

                        <!-- 6. Gross Pay -->
                        <td style="text-align: right; font-weight: 700; color: #0f172a; font-variant-numeric: tabular-nums;">
                            <?= number_format($sal['gross_pay']) ?>
                        </td>

                        <!-- 7. Tax -->
                        <td style="text-align: right; color: #64748b; font-variant-numeric: tabular-nums;">
                            <?= number_format($sal['tax']) ?>
                        </td>

                        <!-- 8. Advance -->
                        <td style="text-align: right; color: #64748b; font-variant-numeric: tabular-nums;">
                            <?= number_format($sal['advance']) ?>
                        </td>

                        <!-- 9. Deductions (RED BUTTON) -->
                        <td style="text-align: right;">
                            <?php if ($sal['deductions'] > 0): ?>
                                <button type="button" class="deduction-chip-red" onclick="viewDeductions(<?= htmlspecialchars(json_encode($sal)) ?>, <?= htmlspecialchars(json_encode($user_deductions)) ?>)" title="View deduction breakdown">
                                    <i class="fas fa-receipt"></i> -<?= number_format($sal['deductions']) ?>
                                </button>
                            <?php else: ?>
                                <span style="color: #cbd5e1; font-size: 11.5px;">0</span>
                            <?php endif; ?>
                        </td>

                        <!-- 10. Previous Pending Arrears -->
                        <td style="text-align: right; color: <?= $sal['previous_pending'] > 0 ? '#15803d' : '#94a3b8' ?>; font-variant-numeric: tabular-nums;">
                            <?= number_format($sal['previous_pending']) ?>
                        </td>

                        <!-- 11. Net Salary -->
                        <td style="text-align: right; font-weight: 800; color: #0f172a; font-size: 13px; font-variant-numeric: tabular-nums;">
                            PKR <?= number_format($sal['net_salary']) ?>
                        </td>

                        <!-- 12. Paid Amount -->
                        <td style="text-align: right; font-weight: 700; color: <?= ($sal['amount_paid'] > 0) ? '#166534' : '#94a3b8' ?>; font-variant-numeric: tabular-nums;">
                            <?= number_format($sal['amount_paid']) ?>
                        </td>

                        <!-- 13. Balance Due -->
                        <td style="text-align: right; font-weight: 700; color: <?= (($sal['net_salary'] - $sal['amount_paid']) > 0) ? '#c2410c' : '#94a3b8' ?>; font-variant-numeric: tabular-nums;">
                            <?= number_format(max(0, $sal['net_salary'] - $sal['amount_paid'])) ?>
                        </td>

                        <!-- 14. Payment Status -->
                        <td style="text-align: center;">
                            <span class="status-clean <?= $clean_status ?>"><?= htmlspecialchars($sal['payment_status']) ?></span>
                        </td>

                        <!-- 15. Actions (Edit, Penalty, Remove) -->
                        <td style="text-align: center;">
                            <div style="display: inline-flex; gap: 4px; align-items: center;">
                                <button type="button" class="btn-table-edit" onclick="openEditSalaryModal(<?= htmlspecialchars(json_encode($sal)) ?>)" title="Edit Salary">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button type="button" class="btn-table-penalty" onclick="openAddDeductionModal(<?= $sal['user_id'] ?>, '<?= htmlspecialchars(addslashes($sal['user_name'])) ?>')" title="Add Penalty / Deduction">
                                    <i class="fas fa-minus-circle"></i> Penalty
                                </button>
                                <a href="generate_salary_pdf.php?salary_id=<?= $sal['id'] ?>" target="_blank" class="btn-table-slip" title="Print Salary Slip (mPDF)">
                                    <i class="fas fa-file-invoice"></i> Slip
                                </a>
                                <button type="button" class="btn-table-remove" onclick="confirmRemoveSalary(<?= $sal['id'] ?>, <?= $sal['user_id'] ?>, '<?= htmlspecialchars(addslashes($sal['user_name'])) ?>')" title="Remove from Salary Register">
                                    <i class="fas fa-trash-alt"></i> Remove
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ─────────────────────────────────────────────────────────────────── -->
        <!-- VIEW 3: RESPONSIVE CARDS VIEW (< 860px FOR TABLETS & MOBILES)       -->
        <!-- ─────────────────────────────────────────────────────────────────── -->
        <div class="payroll-cards-container" id="payrollCardsContainer">
            <?php foreach ($salaries as $sal): 
                $user_deductions = $deductions_map[$sal['user_id']] ?? [];
                $clean_status = str_replace(' ', '_', $sal['payment_status']);
            ?>
            <div class="payroll-emp-card payroll-row-card">
                <div class="payroll-emp-card-header">
                    <div>
                        <div style="font-weight: 700; color: #0f172a; font-size: 13.5px;"><?= htmlspecialchars($sal['user_name']) ?></div>
                        <div style="font-size: 11px; color: #64748b;">
                            <span style="font-family: monospace; font-weight: 600;"><?= htmlspecialchars($sal['employee_id'] ?: 'EMP-' . str_pad($sal['user_id'], 3, '0', STR_PAD_LEFT)) ?></span>
                            · <?= htmlspecialchars($sal['job_title'] ?: 'Staff') ?> (<?= htmlspecialchars($sal['department'] ?: 'General') ?>)
                        </div>
                    </div>
                    <div>
                        <span class="status-clean <?= $clean_status ?>"><?= htmlspecialchars($sal['payment_status']) ?></span>
                    </div>
                </div>

                <div class="payroll-emp-card-grid">
                    <div class="card-data-item">
                        <div class="card-lbl">Gross Pay</div>
                        <div class="card-val">PKR <?= number_format($sal['gross_pay']) ?></div>
                        <div style="font-size: 10px; color: #64748b;">Base: <?= number_format($sal['base_salary']) ?> + Allow: <?= number_format($sal['allowances']) ?></div>
                    </div>

                    <div class="card-data-item">
                        <div class="card-lbl">Net Salary</div>
                        <div class="card-val" style="color: #024442;">PKR <?= number_format($sal['net_salary']) ?></div>
                        <?php if ($sal['payment_status'] === 'Partially Paid'): ?>
                            <div style="font-size: 10px; color: #c2410c; font-weight: 700; margin-top: 1px;">
                                Paid: <?= number_format($sal['amount_paid']) ?> · Due: <?= number_format(max(0, $sal['net_salary'] - $sal['amount_paid'])) ?>
                            </div>
                        <?php elseif ($sal['payment_status'] === 'Paid'): ?>
                            <div style="font-size: 10px; color: #15803d; font-weight: 600; margin-top: 1px;">
                                <i class="fas fa-check-circle"></i> Paid in Full
                            </div>
                        <?php else: ?>
                            <div style="font-size: 10px; color: #64748b;">Tax: <?= number_format($sal['tax']) ?> · Adv: <?= number_format($sal['advance']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="card-data-item">
                        <div class="card-lbl">Shift Timing</div>
                        <div class="card-val" style="font-size: 11px; font-weight: 600; color: #334155;">
                            <i class="far fa-clock" style="color:#024442;"></i> <?= date('h:i A', strtotime($sal['in_time'] ?: '09:00:00')) ?> - <?= date('h:i A', strtotime($sal['out_time'] ?: '18:00:00')) ?>
                        </div>
                    </div>
                </div>

                <div class="payroll-emp-card-footer">
                    <div>
                        <?php if ($sal['deductions'] > 0): ?>
                            <button type="button" class="deduction-chip-red" onclick="viewDeductions(<?= htmlspecialchars(json_encode($sal)) ?>, <?= htmlspecialchars(json_encode($user_deductions)) ?>)">
                                <i class="fas fa-receipt"></i> -PKR <?= number_format($sal['deductions']) ?>
                            </button>
                        <?php else: ?>
                            <span style="font-size: 11px; color: #94a3b8;">0 Deductions</span>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; gap: 4px;">
                        <button type="button" class="btn-table-edit" onclick="openEditSalaryModal(<?= htmlspecialchars(json_encode($sal)) ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button type="button" class="btn-table-penalty" onclick="openAddDeductionModal(<?= $sal['user_id'] ?>, '<?= htmlspecialchars(addslashes($sal['user_name'])) ?>')">
                            <i class="fas fa-minus-circle"></i> Penalty
                        </button>
                        <a href="generate_salary_pdf.php?salary_id=<?= $sal['id'] ?>" target="_blank" class="btn-table-slip" title="Print Salary Slip (mPDF)">
                            <i class="fas fa-file-invoice"></i> Slip
                        </a>
                        <button type="button" class="btn-table-remove" onclick="confirmRemoveSalary(<?= $sal['id'] ?>, <?= $sal['user_id'] ?>, '<?= htmlspecialchars(addslashes($sal['user_name'])) ?>')">
                            <i class="fas fa-trash-alt"></i> Remove
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<!-- Modal 1: Itemized Deductions View -->
<div id="deductionsModal" class="modal-overlay" onclick="if(event.target===this)closeModal('deductionsModal')">
    <div class="modal-content">
        <div class="modal-header">
            <h4>Itemized Deduction Breakdown</h4>
            <button type="button" onclick="closeModal('deductionsModal')" style="background:none;border:none;color:#64748b;font-size:20px;cursor:pointer;">&times;</button>
        </div>
        <div class="modal-body">
            <div id="deductionEmployeeHeader" style="background:#f8fafc; padding:10px 12px; border-radius:6px; margin-bottom:12px; border:1px solid #e2e8f0;"></div>
            <div id="deductionsListContainer"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-clean-secondary" onclick="closeModal('deductionsModal')">Close</button>
        </div>
    </div>
</div>

<!-- Modal 2: Edit Salary & Parameters -->
<div id="editSalaryModal" class="modal-overlay" onclick="if(event.target===this)closeModal('editSalaryModal')">
    <div class="modal-content">
        <div class="modal-header">
            <h4>Edit Employee Salary & Allowances</h4>
            <button type="button" onclick="closeModal('editSalaryModal')" style="background:none;border:none;color:#64748b;font-size:20px;cursor:pointer;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_salary">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="modal-body">
                <div style="margin-bottom:12px;">
                    <strong id="edit_employee_name" style="font-size:13px; color:#0f172a;"></strong>
                    <div style="font-size:11px; color:#64748b;">Month: <?= date('F Y', strtotime($filter_month . '-01')) ?></div>
                </div>

                <!-- Shift In/Out Time Section for Edit Modal -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; margin-bottom: 12px;">
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #024442; letter-spacing: 0.5px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="far fa-clock"></i> Shift & Attendance Timing</span>
                        <span id="edit_shift_duration_pill" style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; background: #e0f2fe; color: #0369a1;">Standard: 9.0 hrs</span>
                    </div>
                    <div class="form-grid">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>In Time (Check-In) <span style="color:#ef4444;">*</span></label>
                            <input type="time" name="in_time" id="edit_in_time" value="09:00" required onchange="recalculateEditSalary()">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Out Time (Check-Out) <span style="color:#ef4444;">*</span></label>
                            <input type="time" name="out_time" id="edit_out_time" value="18:00" required onchange="recalculateEditSalary()">
                        </div>
                    </div>
                    <div id="edit_timing_calc_alert" style="margin-top: 8px; padding: 6px 10px; border-radius: 6px; font-size: 11px; display: none;"></div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Employee ID</label>
                        <input type="text" name="employee_id" id="edit_emp_id">
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" id="edit_department">
                    </div>
                </div>

                <div class="form-group">
                    <label>Job Title</label>
                    <input type="text" name="job_title" id="edit_job_title">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Base Salary (PKR) <span style="color:#ef4444;">*</span></label>
                        <input type="number" step="0.01" name="base_salary" id="edit_base_salary" required>
                    </div>
                    <div class="form-group">
                        <label>Allowances (PKR)</label>
                        <input type="number" step="0.01" name="allowances" id="edit_allowances">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Income Tax (PKR)</label>
                        <input type="number" step="0.01" name="tax" id="edit_tax">
                    </div>
                    <div class="form-group">
                        <label>Salary Advance (PKR)</label>
                        <input type="number" step="0.01" name="advance" id="edit_advance">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Previous Pending Arrears (PKR)</label>
                        <input type="number" step="0.01" name="previous_pending" id="edit_previous_pending">
                    </div>
                    <div class="form-group">
                        <label>Payment Status</label>
                        <select name="payment_status" id="edit_payment_status" onchange="handlePaymentStatusChange()">
                            <option value="Pending">Pending</option>
                            <option value="Paid">Paid</option>
                            <option value="Partially Paid">Partially Paid</option>
                            <option value="On Hold">On Hold</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="group_amount_paid" style="display: none; background: #fff7ed; padding: 12px; border-radius: 6px; border: 1px solid #fed7aa; margin-top: 4px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <label style="font-weight: 700; color: #9a3412; font-size: 11.5px; margin: 0;">
                            <i class="fas fa-coins"></i> Amount Paid so far (PKR) <span style="color:#ef4444;">*</span>
                        </label>
                        <span id="partial_balance_hint" style="font-size: 11px; font-weight: 700; color: #c2410c;"></span>
                    </div>
                    <input type="number" step="0.01" name="amount_paid" id="edit_amount_paid" placeholder="Enter partial payment amount" oninput="calculateBalanceHint()" style="background:#fff; border-color:#fdba74;">
                    <div style="font-size: 10.5px; color: #9a3412; margin-top: 4px;">
                        Specify the exact partial amount paid. The remainder will be recorded as pending balance.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-clean-secondary" onclick="closeModal('editSalaryModal')">Cancel</button>
                <button type="submit" class="btn-clean-secondary" style="background:#024442; color:#fff; border-color:#024442;"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 4: Add Employee to Salary Register -->
<div id="addSalaryModal" class="modal-overlay" onclick="if(event.target===this)closeModal('addSalaryModal')">
    <div class="modal-content" style="max-width:620px;">
        <div class="modal-header" style="background: #024442; color: #ffffff;">
            <h4 style="color:#fff;"><i class="fas fa-user-plus"></i> Add Employee to Salary Register</h4>
            <button type="button" onclick="closeModal('addSalaryModal')" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;">&times;</button>
        </div>
        <form method="POST" id="addSalaryForm">
            <input type="hidden" name="action" value="add_salary">
            <input type="hidden" name="employee_id" id="add_employee_id">
            <input type="hidden" name="department" id="add_department">
            <input type="hidden" name="job_title" id="add_job_title">
            <input type="hidden" name="gender" id="add_gender" value="Male">

            <div class="modal-body">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding: 8px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <span style="font-size: 12px; font-weight: 600; color: #334155;">
                        <i class="far fa-calendar-alt" style="color:#024442;"></i> Register Month:
                    </span>
                    <strong style="color: #024442; font-size: 13px;"><?= date('F Y', strtotime($filter_month . '-01')) ?></strong>
                </div>

                <!-- 1. Active User Selection Dropdown (Active users only, strictly excluding students) -->
                <div class="form-group">
                    <label style="font-weight: 700; color: #0f172a;">Select Staff Member (Active Employees Only) <span style="color:#ef4444;">*</span></label>
                    <select name="user_id" id="add_user_id" onchange="onSelectUserForSalary()" required>
                        <option value="">— Choose an Employee —</option>
                        <?php if (!empty($active_staff_users)): ?>
                            <optgroup label="Available to Add">
                                <?php foreach ($active_staff_users as $u): 
                                    if (in_array(intval($u['user_id']), $registered_user_ids)) continue;
                                ?>
                                    <option value="<?= $u['user_id'] ?>" 
                                        data-name="<?= htmlspecialchars($u['name']) ?>"
                                        data-username="<?= htmlspecialchars($u['username']) ?>"
                                        data-emp-id="<?= htmlspecialchars($u['employee_id'] ?: 'EMP-' . str_pad($u['user_id'], 3, '0', STR_PAD_LEFT)) ?>"
                                        data-dept="<?= htmlspecialchars($u['department'] ?: 'General') ?>"
                                        data-title="<?= htmlspecialchars($u['job_title'] ?: 'Staff') ?>"
                                        data-base="<?= floatval($u['base_salary'] ?: 50000) ?>"
                                        data-allow="<?= floatval($u['allowances'] ?: 0) ?>"
                                        data-gender="<?= htmlspecialchars($u['gender'] ?: 'Male') ?>">
                                        <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['employee_id'] ?: 'EMP-' . str_pad($u['user_id'], 3, '0', STR_PAD_LEFT)) ?> · <?= htmlspecialchars($u['job_title'] ?: 'Staff') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Already in Register">
                                <?php foreach ($active_staff_users as $u): 
                                    if (!in_array(intval($u['user_id']), $registered_user_ids)) continue;
                                ?>
                                    <option value="<?= $u['user_id'] ?>" 
                                        data-name="<?= htmlspecialchars($u['name']) ?>"
                                        data-username="<?= htmlspecialchars($u['username']) ?>"
                                        data-emp-id="<?= htmlspecialchars($u['employee_id'] ?: 'EMP-' . str_pad($u['user_id'], 3, '0', STR_PAD_LEFT)) ?>"
                                        data-dept="<?= htmlspecialchars($u['department'] ?: 'General') ?>"
                                        data-title="<?= htmlspecialchars($u['job_title'] ?: 'Staff') ?>"
                                        data-base="<?= floatval($u['base_salary'] ?: 50000) ?>"
                                        data-allow="<?= floatval($u['allowances'] ?: 0) ?>"
                                        data-gender="<?= htmlspecialchars($u['gender'] ?: 'Male') ?>"
                                        data-registered="1">
                                        <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['employee_id'] ?: 'EMP-' . str_pad($u['user_id'], 3, '0', STR_PAD_LEFT)) ?>) [Already in Register]
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- User Meta preview pill -->
                <div id="add_user_meta_badge" style="display: none; margin-bottom: 12px; padding: 8px 12px; border-radius: 6px; background: #f0fdf4; border: 1px solid #bbf7d0; font-size: 11.5px; color: #166534;">
                    <i class="fas fa-id-badge"></i> <span id="add_meta_text"></span>
                </div>

                <!-- 2. Shift In & Out Time -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #024442; letter-spacing: 0.5px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="far fa-clock"></i> Shift & Attendance Timing</span>
                        <span id="add_shift_duration_pill" style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; background: #e0f2fe; color: #0369a1;">Standard: 9.0 hrs</span>
                    </div>

                    <div class="form-grid">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>In Time (Check-In) <span style="color:#ef4444;">*</span></label>
                            <input type="time" name="in_time" id="add_in_time" value="09:00" required onchange="recalculateAddSalary()">
                            <small id="add_in_time_hint" style="font-size: 10px; color: #64748b; display: block; margin-top: 3px;">
                                Official Start: 09:00 AM (Male Grace: 10m, Female Grace: 30m)
                            </small>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Out Time (Check-Out) <span style="color:#ef4444;">*</span></label>
                            <input type="time" name="out_time" id="add_out_time" value="18:00" required onchange="recalculateAddSalary()">
                            <small style="font-size: 10px; color: #64748b; display: block; margin-top: 3px;">
                                Standard Exit: 05:00 PM / 06:00 PM (8-9 hrs shift)
                            </small>
                        </div>
                    </div>

                    <!-- Live Timing Status Alert Box -->
                    <div id="add_timing_calc_alert" style="margin-top: 10px; padding: 8px 12px; border-radius: 6px; font-size: 11.5px; display: none;"></div>
                </div>

                <!-- 3. Base Salary & Allowances -->
                <div class="form-grid">
                    <div class="form-group">
                        <label>Base Salary (PKR) <span style="color:#ef4444;">*</span></label>
                        <input type="number" step="0.01" name="base_salary" id="add_base_salary" placeholder="50000" required oninput="recalculateAddSalary()">
                    </div>
                    <div class="form-group">
                        <label>Allowances (PKR)</label>
                        <input type="number" step="0.01" name="allowances" id="add_allowances" value="0" oninput="recalculateAddSalary()">
                    </div>
                </div>

                <!-- 4. Tax, Advance & Arrears -->
                <div class="form-grid">
                    <div class="form-group">
                        <label>Income Tax (PKR)</label>
                        <input type="number" step="0.01" name="tax" id="add_tax" value="0" oninput="recalculateAddSalary()">
                    </div>
                    <div class="form-group">
                        <label>Salary Advance (PKR)</label>
                        <input type="number" step="0.01" name="advance" id="add_advance" value="0" oninput="recalculateAddSalary()">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Previous Pending Arrears (PKR)</label>
                        <input type="number" step="0.01" name="previous_pending" id="add_previous_pending" value="0" oninput="recalculateAddSalary()">
                    </div>
                    <div class="form-group">
                        <label>Payment Status</label>
                        <select name="payment_status" id="add_payment_status" onchange="handleAddPaymentStatusChange()">
                            <option value="Pending" selected>Pending</option>
                            <option value="Paid">Paid</option>
                            <option value="Partially Paid">Partially Paid</option>
                            <option value="On Hold">On Hold</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="add_group_amount_paid" style="display: none; background: #fff7ed; padding: 10px 12px; border-radius: 6px; border: 1px solid #fed7aa;">
                    <label style="font-weight: 700; color: #9a3412; font-size: 11.5px; margin: 0 0 3px 0;">
                        <i class="fas fa-coins"></i> Amount Paid so far (PKR)
                    </label>
                    <input type="number" step="0.01" name="amount_paid" id="add_amount_paid" placeholder="Enter partial amount paid">
                </div>

                <!-- 5. Dynamic Live Calculation Card -->
                <div id="add_live_summary_box" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-top: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-bottom: 8px;">
                        <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #475569;">Calculated Payroll Breakdown</span>
                        <span style="font-size: 10.5px; color: #64748b;" id="add_summary_wage_rate">Daily: PKR 0 · Hourly: PKR 0</span>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; text-align: center; font-size: 11.5px;">
                        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:6px 4px;">
                            <div style="font-size:10px; color:#64748b; text-transform:uppercase;">Gross Earnings</div>
                            <strong style="color:#0f172a; font-size:13px;" id="add_calc_gross">PKR 0</strong>
                        </div>
                        <div style="background:#fff; border:1px solid #fecaca; border-radius:6px; padding:6px 4px;">
                            <div style="font-size:10px; color:#dc2626; text-transform:uppercase;">Total Deductions</div>
                            <strong style="color:#dc2626; font-size:13px;" id="add_calc_deductions">-PKR 0</strong>
                        </div>
                        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:6px 4px;">
                            <div style="font-size:10px; color:#166534; text-transform:uppercase;">Net Salary</div>
                            <strong style="color:#166534; font-size:14px;" id="add_calc_net">PKR 0</strong>
                        </div>
                    </div>

                    <div id="add_calc_detail_notes" style="font-size: 10.5px; color: #64748b; margin-top: 8px; line-height: 1.4;"></div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn-clean-secondary" onclick="closeModal('addSalaryModal')">Cancel</button>
                <button type="submit" class="btn-add-salary" style="border:none;"><i class="fas fa-check"></i> Add to Salary Register</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 5: Confirm Remove Salary -->
<div id="removeSalaryModal" class="modal-overlay" onclick="if(event.target===this)closeModal('removeSalaryModal')">
    <div class="modal-content" style="max-width: 440px;">
        <div class="modal-header modal-header-red">
            <h4 style="color:#fff;"><i class="fas fa-trash-alt"></i> Remove from Salary Register</h4>
            <button type="button" onclick="closeModal('removeSalaryModal')" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="remove_salary">
            <input type="hidden" name="salary_id" id="remove_salary_id">
            <input type="hidden" name="user_id" id="remove_user_id">
            <div class="modal-body">
                <div style="font-size: 13px; color: #1e293b; line-height: 1.5;">
                    Are you sure you want to remove <strong id="remove_employee_name"></strong> from the salary register for <strong><?= date('F Y', strtotime($filter_month . '-01')) ?></strong>?
                </div>
                <div style="margin-top: 10px; font-size: 11.5px; color: #64748b; background: #fef2f2; border: 1px solid #fee2e2; padding: 8px 12px; border-radius: 6px;">
                    <i class="fas fa-info-circle" style="color: #dc2626;"></i> This deletes this month's payroll entry from the register. You can re-add them at any time using "+ Add Salary".
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-clean-secondary" onclick="closeModal('removeSalaryModal')">Cancel</button>
                <button type="submit" class="btn-red-deduction" style="border:none;"><i class="fas fa-trash-alt"></i> Remove Now</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Add Manual Deduction / Penalty (Admin / HR) -->
<div id="addDeductionModal" class="modal-overlay" onclick="if(event.target===this)closeModal('addDeductionModal')">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header modal-header-red">
            <h4 style="color:#fff;"><i class="fas fa-minus-circle"></i> Add Deduction / Penalty</h4>
            <button type="button" onclick="closeModal('addDeductionModal')" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_custom_deduction">
            <div class="modal-body">
                <div class="form-group">
                    <label>Select Employee <span style="color:#ef4444;">*</span></label>
                    <select name="user_id" id="add_ded_user_id" required>
                        <option value="">— Select Employee —</option>
                        <?php foreach ($users_for_deduction as $u): ?>
                            <option value="<?= $u['user_id'] ?>">
                                <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['employee_id'] ?: 'EMP-' . str_pad($u['user_id'], 3, '0', STR_PAD_LEFT)) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Deduction / Penalty Type <span style="color:#ef4444;">*</span></label>
                    <select name="deduction_type" required>
                        <option value="Late Arrival Penalty">Late Arrival Penalty</option>
                        <option value="Excess Grace Penalty">Excess Grace Period Penalty</option>
                        <option value="Disciplinary Fine">Disciplinary Fine</option>
                        <option value="Unpaid Leave">Unpaid Leave / Absenteeism</option>
                        <option value="Advance Repayment">Advance Salary Repayment</option>
                        <option value="Asset Loss">Damaged Equipment / Asset Loss</option>
                        <option value="Other">Other Deduction</option>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Amount (PKR) <span style="color:#ef4444;">*</span></label>
                        <input type="number" step="0.01" name="amount" placeholder="e.g. 100" required>
                    </div>
                    <div class="form-group">
                        <label>Date Incurred <span style="color:#ef4444;">*</span></label>
                        <input type="date" name="date_incurred" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Reason / Explanation</label>
                    <textarea name="description" placeholder="Specify details..." rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-clean-secondary" onclick="closeModal('addDeductionModal')">Cancel</button>
                <button type="submit" class="btn-red-deduction" style="border:none;"><i class="fas fa-check"></i> Apply Deduction</button>
            </div>
        </form>
    </div>
</div>

<div id="toastContainer" style="position:fixed; bottom:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:8px;"></div>

<script>
// ── Mode Switcher Logic (Fit Screen vs All Columns) ──
function setViewMode(mode) {
    const fitBox = document.getElementById('fitTableBox');
    const allBox = document.getElementById('allColsBox');
    const btnFit = document.getElementById('btnModeFit');
    const btnAll = document.getElementById('btnModeAll');

    if (mode === 'all') {
        if (fitBox) fitBox.style.display = 'none';
        if (allBox) allBox.style.display = 'block';
        if (btnFit) btnFit.classList.remove('active');
        if (btnAll) btnAll.classList.add('active');
        localStorage.setItem('payroll_view_mode', 'all');
    } else {
        if (fitBox) fitBox.style.display = 'block';
        if (allBox) allBox.style.display = 'none';
        if (btnFit) btnFit.classList.add('active');
        if (btnAll) btnAll.classList.remove('active');
        localStorage.setItem('payroll_view_mode', 'fit');
    }
}

// Search Filter across all views
function filterPayrollRecords() {
    const search = (document.getElementById('payrollSearchInput').value || '').toLowerCase().trim();
    
    // 1. Filter Fit-Screen rows
    document.querySelectorAll('.payroll-row-fit').forEach(row => {
        row.style.display = (!search || row.textContent.toLowerCase().includes(search)) ? '' : 'none';
    });

    // 2. Filter All-Columns rows
    document.querySelectorAll('.payroll-row-all').forEach(row => {
        row.style.display = (!search || row.textContent.toLowerCase().includes(search)) ? '' : 'none';
    });

    // 3. Filter Mobile Card rows
    document.querySelectorAll('.payroll-row-card').forEach(card => {
        card.style.display = (!search || card.textContent.toLowerCase().includes(search)) ? '' : 'none';
    });
}

function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
    document.body.style.overflow = '';
}

function viewDeductions(salary, deductions) {
    const header = document.getElementById('deductionEmployeeHeader');
    header.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <strong style="font-size:13px; color:#0f172a;">${escapeHtml(salary.user_name)}</strong>
                <div style="font-size:11px; color:#64748b;">${escapeHtml(salary.job_title || 'Staff')} · ${escapeHtml(salary.department || 'General')}</div>
            </div>
            <div style="text-align:right;">
                <span style="font-size:10px; color:#64748b; font-weight:600; text-transform:uppercase;">Total Deductions</span>
                <div style="font-size:15px; font-weight:700; color:#dc2626;">-PKR ${Number(salary.deductions).toLocaleString()}</div>
            </div>
        </div>
    `;

    const container = document.getElementById('deductionsListContainer');
    if (!deductions || deductions.length === 0) {
        container.innerHTML = `<div style="text-align:center; padding:18px; color:#94a3b8; font-size:12px;">No itemized deductions found for this month.</div>`;
    } else {
        let html = `
            <table style="width:100%; border-collapse:collapse; font-size:11.5px;">
                <thead>
                    <tr style="background:#f8fafc; color:#475569; text-transform:uppercase; font-size:10px; font-weight:700;">
                        <th style="padding:6px 8px; text-align:left;">Date</th>
                        <th style="padding:6px 8px; text-align:left;">Reason</th>
                        <th style="padding:6px 8px; text-align:right;">Amount</th>
                        <th style="padding:6px 8px; text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
        `;
        deductions.forEach(d => {
            let statusBadge = '';
            if (d.status === 'applied') {
                statusBadge = `<span style="font-size:10px; color:#64748b; font-weight:600;">Applied</span>`;
            } else if (d.status === 'disputed') {
                statusBadge = `<a href="deduction_tickets.php" style="color:#b45309; background:#fef3c7; border:1px solid #fde68a; padding:2px 7px; border-radius:4px; text-decoration:none; font-size:10px; font-weight:700;"><i class="fas fa-ticket-alt"></i> Disputed (Pending)</a>`;
            } else if (d.status === 'waived') {
                statusBadge = `<span style="font-size:10px; color:#15803d; background:#dcfce7; border:1px solid #bbf7d0; padding:2px 7px; border-radius:4px; font-weight:700;"><i class="fas fa-check"></i> Waived</span>`;
            }
            html += `
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:6px 8px; color:#475569; white-space:nowrap;">${escapeHtml(d.date_incurred)}</td>
                    <td style="padding:6px 8px;">
                        <strong style="color:#0f172a; display:block;">${escapeHtml(d.deduction_type)}</strong>
                        <small style="color:#64748b;">${escapeHtml(d.description)}</small>
                        ${d.task_id && Number(d.task_id) > 0 ? `<div><a href="task_details.php?id=${d.task_id}" target="_blank" style="display:inline-flex; align-items:center; gap:4px; margin-top:3px; font-size:11px; color:#024442; font-weight:600; text-decoration:underline;"><i class="fas fa-external-link-alt"></i> View Task #${d.task_id}</a></div>` : ''}
                    </td>
                    <td style="padding:6px 8px; text-align:right; font-weight:600; color:#dc2626; white-space:nowrap;">-PKR ${Number(d.amount).toLocaleString()}</td>
                    <td style="padding:6px 8px; text-align:center;">${statusBadge}</td>
                </tr>
            `;
        });
        html += `</tbody></table>`;
        container.innerHTML = html;
    }

    openModal('deductionsModal');
}

// Active Staff and Deduction Data Maps
const activeStaffUsers = <?= json_encode($active_staff_users) ?>;
const registeredUserIds = <?= json_encode($registered_user_ids) ?>;
const existingDeductionsMap = <?= json_encode($existing_deductions_sums) ?>;

function timeToSeconds(t) {
    if (!t) return 0;
    const parts = t.split(':');
    return (Number(parts[0]) || 0) * 3600 + (Number(parts[1]) || 0) * 60 + (Number(parts[2]) || 0);
}

// ── Unified Attendance, Shift & Deduction Calculator ─────────────────────────
function calculateAttendanceAndDeduction(baseSalary, allowances, inTimeStr, outTimeStr, gender, existingDeductions, tax, advance, arrears) {
    baseSalary = Number(baseSalary) || 0;
    allowances = Number(allowances) || 0;
    existingDeductions = Number(existingDeductions) || 0;
    tax = Number(tax) || 0;
    advance = Number(advance) || 0;
    arrears = Number(arrears) || 0;

    const dailyWage  = Math.round((baseSalary / 30) * 100) / 100;
    const hourlyWage = Math.round((dailyWage / 8) * 100) / 100;

    const isFemale = (String(gender).toLowerCase() === 'female');
    const graceLimit = isFemale ? '09:30:00' : '09:10:00';

    let inTimeFull  = inTimeStr ? (inTimeStr.length === 5 ? inTimeStr + ':00' : inTimeStr) : '09:00:00';
    let outTimeFull = outTimeStr ? (outTimeStr.length === 5 ? outTimeStr + ':00' : outTimeStr) : '18:00:00';

    let lateDeduction = 0;
    let lateStatus = 'On Time';
    let isLate = false;
    let lateMins = 0;

    if (inTimeFull <= '09:00:00') {
        lateStatus = 'On Time (Check-in before 09:00 AM)';
        isLate = false;
    } else if (inTimeFull <= graceLimit) {
        lateStatus = `Within Grace Period (Cutoff: ${isFemale ? '09:30' : '09:10'} AM for ${isFemale ? 'Female' : 'Male'})`;
        isLate = false;
    } else {
        isLate = true;
        const inSecs = timeToSeconds(inTimeFull);
        const baseSecs = timeToSeconds('09:00:00');
        lateMins = Math.max(0, Math.round((inSecs - baseSecs) / 60));
        lateDeduction = Math.max(100, Math.round((lateMins / 60) * hourlyWage));
        lateStatus = `Late Arrival: ${lateMins} mins late (Past ${isFemale ? '09:30' : '09:10'} AM) → Penalty: -PKR ${lateDeduction.toLocaleString()}`;
    }

    const inSec = timeToSeconds(inTimeFull);
    const outSec = timeToSeconds(outTimeFull);
    let workedHours = Math.max(0, Math.round(((outSec - inSec) / 3600) * 100) / 100);

    let shortfallDeduction = 0;
    let hoursStatus = 'Full Shift Completed (≥ 8 hrs)';
    let isShortfall = false;

    if (workedHours < 4.0) {
        isShortfall = true;
        shortfallDeduction = Math.round(dailyWage * 0.5);
        hoursStatus = `Half-Day Attendance (< 4 hrs worked: ${workedHours} hrs) → -PKR ${shortfallDeduction.toLocaleString()}`;
    } else if (workedHours < 8.0) {
        isShortfall = true;
        const shortHrs = Math.round((8.0 - workedHours) * 100) / 100;
        shortfallDeduction = Math.round(shortHrs * hourlyWage);
        hoursStatus = `Short Hours (${workedHours} hrs worked, shortfall ${shortHrs} hrs) → -PKR ${shortfallDeduction.toLocaleString()}`;
    } else {
        hoursStatus = `Full Shift Completed (${workedHours} hrs worked)`;
    }

    const timingDeductions = lateDeduction + shortfallDeduction;
    const totalDeductions  = existingDeductions + timingDeductions;
    const grossPay         = baseSalary + allowances;
    const netSalary        = Math.max(0, grossPay - tax - advance - totalDeductions + arrears);

    return {
        dailyWage,
        hourlyWage,
        workedHours,
        isLate,
        lateStatus,
        lateDeduction,
        isShortfall,
        hoursStatus,
        shortfallDeduction,
        timingDeductions,
        existingDeductions,
        totalDeductions,
        grossPay,
        netSalary
    };
}

// ── Open Add Salary Modal ────────────────────────────────────────────────────
function openAddSalaryModal() {
    const form = document.getElementById('addSalaryForm');
    if (form) form.reset();

    document.getElementById('add_user_id').value = '';
    document.getElementById('add_user_meta_badge').style.display = 'none';
    document.getElementById('add_in_time').value = '09:00';
    document.getElementById('add_out_time').value = '18:00';
    document.getElementById('add_base_salary').value = '50000';
    document.getElementById('add_allowances').value = '0';
    document.getElementById('add_tax').value = '0';
    document.getElementById('add_advance').value = '0';
    document.getElementById('add_previous_pending').value = '0';
    document.getElementById('add_payment_status').value = 'Pending';
    document.getElementById('add_amount_paid').value = '0';
    document.getElementById('add_group_amount_paid').style.display = 'none';

    recalculateAddSalary();
    openModal('addSalaryModal');
}

// When employee is picked in Add Salary dropdown
function onSelectUserForSalary() {
    const sel = document.getElementById('add_user_id');
    const opt = sel.options[sel.selectedIndex];

    if (!opt || !opt.value) {
        document.getElementById('add_user_meta_badge').style.display = 'none';
        recalculateAddSalary();
        return;
    }

    const name   = opt.dataset.name || '';
    const empId  = opt.dataset.empId || '';
    const dept   = opt.dataset.dept || 'General';
    const title  = opt.dataset.title || 'Staff';
    const base   = Number(opt.dataset.base) || 50000;
    const allow  = Number(opt.dataset.allow) || 0;
    const gender = opt.dataset.gender || 'Male';
    const isReg  = opt.dataset.registered === '1';

    document.getElementById('add_employee_id').value = empId;
    document.getElementById('add_department').value  = dept;
    document.getElementById('add_job_title').value   = title;
    document.getElementById('add_gender').value      = gender;
    document.getElementById('add_base_salary').value = base;
    document.getElementById('add_allowances').value  = allow;

    const badge = document.getElementById('add_user_meta_badge');
    const badgeText = document.getElementById('add_meta_text');
    badgeText.innerHTML = `<strong>${escapeHtml(name)}</strong> (${empId}) · ${escapeHtml(title)} · ${escapeHtml(dept)} · <span style="text-decoration:underline;">${gender} (${gender === 'Female' ? '30m' : '10m'} grace)</span>` + (isReg ? ` <span style="color:#b45309; font-weight:700;">[Already in this month's register - will update]</span>` : '');
    badge.style.display = 'block';

    recalculateAddSalary();
}

// Live calculation for Add Salary modal
function recalculateAddSalary() {
    const userId = Number(document.getElementById('add_user_id').value) || 0;
    const base = Number(document.getElementById('add_base_salary').value) || 0;
    const allow = Number(document.getElementById('add_allowances').value) || 0;
    const inTime = document.getElementById('add_in_time').value || '09:00';
    const outTime = document.getElementById('add_out_time').value || '18:00';
    const gender = document.getElementById('add_gender').value || 'Male';
    const tax = Number(document.getElementById('add_tax').value) || 0;
    const advance = Number(document.getElementById('add_advance').value) || 0;
    const arrears = Number(document.getElementById('add_previous_pending').value) || 0;
    const existingDeds = existingDeductionsMap[userId] || 0;

    const calc = calculateAttendanceAndDeduction(base, allow, inTime, outTime, gender, existingDeds, tax, advance, arrears);

    // Update Shift Duration Pill
    const pill = document.getElementById('add_shift_duration_pill');
    if (pill) {
        pill.textContent = calc.workedHours + ' hrs shift';
        pill.style.background = (calc.workedHours >= 8.0) ? '#e0f2fe' : (calc.workedHours < 4.0 ? '#fee2e2' : '#fef3c7');
        pill.style.color = (calc.workedHours >= 8.0) ? '#0369a1' : (calc.workedHours < 4.0 ? '#991b1b' : '#92400e');
    }

    // Update Alert Box
    const alertBox = document.getElementById('add_timing_calc_alert');
    if (alertBox) {
        if (calc.isLate || calc.isShortfall) {
            alertBox.style.display = 'block';
            alertBox.style.background = '#fef2f2';
            alertBox.style.border = '1px solid #fee2e2';
            alertBox.style.color = '#991b1b';
            alertBox.innerHTML = `
                <div><i class="fas fa-exclamation-triangle" style="color:#dc2626;"></i> <strong>Attendance Impact:</strong></div>
                ${calc.isLate ? `<div style="margin-top:2px; font-weight:600;">• ${escapeHtml(calc.lateStatus)}</div>` : ''}
                ${calc.isShortfall ? `<div style="margin-top:2px; font-weight:600;">• ${escapeHtml(calc.hoursStatus)}</div>` : ''}
                <div style="margin-top:4px; font-weight:700; color:#dc2626;">Total Timing Deductions: -PKR ${calc.timingDeductions.toLocaleString()}</div>
            `;
        } else {
            alertBox.style.display = 'block';
            alertBox.style.background = '#f0fdf4';
            alertBox.style.border = '1px solid #bbf7d0';
            alertBox.style.color = '#166534';
            alertBox.innerHTML = `<i class="fas fa-check-circle" style="color:#16a34a;"></i> <strong>Perfect Attendance:</strong> ${escapeHtml(calc.lateStatus)} · ${escapeHtml(calc.hoursStatus)} (0 timing deductions).`;
        }
    }

    // Update Summary Card
    const wageRate = document.getElementById('add_summary_wage_rate');
    if (wageRate) wageRate.textContent = `Daily: PKR ${calc.dailyWage.toLocaleString()} · Hourly: PKR ${calc.hourlyWage.toLocaleString()}`;

    const grossEl = document.getElementById('add_calc_gross');
    if (grossEl) grossEl.textContent = 'PKR ' + calc.grossPay.toLocaleString();

    const dedEl = document.getElementById('add_calc_deductions');
    if (dedEl) dedEl.textContent = '-PKR ' + calc.totalDeductions.toLocaleString();

    const netEl = document.getElementById('add_calc_net');
    if (netEl) netEl.textContent = 'PKR ' + calc.netSalary.toLocaleString();

    const detailNotes = document.getElementById('add_calc_detail_notes');
    if (detailNotes) {
        detailNotes.innerHTML = `
            Shift: <strong>${calc.workedHours} hrs</strong> · 
            Timing Deductions: <strong>-PKR ${calc.timingDeductions.toLocaleString()}</strong> · 
            Itemized Month Penalties: <strong>-PKR ${calc.existingDeductions.toLocaleString()}</strong>
            ${tax > 0 ? ` · Tax: -PKR ${tax.toLocaleString()}` : ''}
            ${advance > 0 ? ` · Advance: -PKR ${advance.toLocaleString()}` : ''}
            ${arrears > 0 ? ` · Arrears: +PKR ${arrears.toLocaleString()}` : ''}
        `;
    }

    window.currentAddNetSalary = calc.netSalary;
    handleAddPaymentStatusChange();
}

function handleAddPaymentStatusChange() {
    const status = document.getElementById('add_payment_status').value;
    const group = document.getElementById('add_group_amount_paid');
    const input = document.getElementById('add_amount_paid');
    const net = window.currentAddNetSalary || 0;

    if (status === 'Partially Paid') {
        group.style.display = 'block';
    } else if (status === 'Paid') {
        group.style.display = 'none';
        input.value = net;
    } else {
        group.style.display = 'none';
        input.value = 0;
    }
}

// ── Open Edit Salary Modal ───────────────────────────────────────────────────
function openEditSalaryModal(s) {
    document.getElementById('edit_user_id').value = s.user_id;
    document.getElementById('edit_employee_name').textContent = s.user_name + ' (@' + s.username + ')';
    document.getElementById('edit_emp_id').value = s.employee_id || '';
    document.getElementById('edit_department').value = s.department || 'General';
    document.getElementById('edit_job_title').value = s.job_title || 'Staff';
    document.getElementById('edit_in_time').value = s.in_time ? s.in_time.substring(0, 5) : '09:00';
    document.getElementById('edit_out_time').value = s.out_time ? s.out_time.substring(0, 5) : '18:00';
    document.getElementById('edit_base_salary').value = s.base_salary;
    document.getElementById('edit_allowances').value = s.allowances;
    document.getElementById('edit_tax').value = s.tax;
    document.getElementById('edit_advance').value = s.advance;
    document.getElementById('edit_previous_pending').value = s.previous_pending;
    document.getElementById('edit_payment_status').value = s.payment_status;
    document.getElementById('edit_amount_paid').value = s.amount_paid || 0;

    window.currentEditingGender = s.gender || 'Male';
    window.currentEditingExistingDeductions = existingDeductionsMap[s.user_id] || 0;

    recalculateEditSalary();
    handlePaymentStatusChange();
    openModal('editSalaryModal');
}

function recalculateEditSalary() {
    const base = Number(document.getElementById('edit_base_salary').value) || 0;
    const allow = Number(document.getElementById('edit_allowances').value) || 0;
    const inTime = document.getElementById('edit_in_time').value || '09:00';
    const outTime = document.getElementById('edit_out_time').value || '18:00';
    const gender = window.currentEditingGender || 'Male';
    const tax = Number(document.getElementById('edit_tax').value) || 0;
    const advance = Number(document.getElementById('edit_advance').value) || 0;
    const arrears = Number(document.getElementById('edit_previous_pending').value) || 0;
    const existingDeds = window.currentEditingExistingDeductions || 0;

    const calc = calculateAttendanceAndDeduction(base, allow, inTime, outTime, gender, existingDeds, tax, advance, arrears);

    window.currentEditingNetSalary = calc.netSalary;

    const pill = document.getElementById('edit_shift_duration_pill');
    if (pill) {
        pill.textContent = calc.workedHours + ' hrs shift';
        pill.style.background = (calc.workedHours >= 8.0) ? '#e0f2fe' : (calc.workedHours < 4.0 ? '#fee2e2' : '#fef3c7');
        pill.style.color = (calc.workedHours >= 8.0) ? '#0369a1' : (calc.workedHours < 4.0 ? '#991b1b' : '#92400e');
    }

    const alertBox = document.getElementById('edit_timing_calc_alert');
    if (alertBox) {
        if (calc.isLate || calc.isShortfall) {
            alertBox.style.display = 'block';
            alertBox.style.background = '#fef2f2';
            alertBox.style.border = '1px solid #fee2e2';
            alertBox.style.color = '#991b1b';
            alertBox.innerHTML = `
                <div><i class="fas fa-exclamation-triangle" style="color:#dc2626;"></i> <strong>Attendance Impact:</strong></div>
                ${calc.isLate ? `<div>• ${escapeHtml(calc.lateStatus)}</div>` : ''}
                ${calc.isShortfall ? `<div>• ${escapeHtml(calc.hoursStatus)}</div>` : ''}
                <div style="margin-top:2px; font-weight:700; color:#dc2626;">Timing Deductions: -PKR ${calc.timingDeductions.toLocaleString()} (Total Deductions: -PKR ${calc.totalDeductions.toLocaleString()})</div>
            `;
        } else {
            alertBox.style.display = 'block';
            alertBox.style.background = '#f0fdf4';
            alertBox.style.border = '1px solid #bbf7d0';
            alertBox.style.color = '#166534';
            alertBox.innerHTML = `<i class="fas fa-check-circle" style="color:#16a34a;"></i> <strong>Perfect Attendance:</strong> ${escapeHtml(calc.lateStatus)} · ${escapeHtml(calc.hoursStatus)} (0 timing deductions).`;
        }
    }

    calculateBalanceHint();
}

// ── Open Remove Confirmation Modal ───────────────────────────────────────────
function confirmRemoveSalary(salaryId, userId, userName) {
    document.getElementById('remove_salary_id').value = salaryId;
    document.getElementById('remove_user_id').value = userId;
    document.getElementById('remove_employee_name').textContent = userName;
    openModal('removeSalaryModal');
}

function handlePaymentStatusChange() {
    const status = document.getElementById('edit_payment_status').value;
    const groupPaid = document.getElementById('group_amount_paid');
    const inputPaid = document.getElementById('edit_amount_paid');
    const net = window.currentEditingNetSalary || 0;

    if (status === 'Partially Paid') {
        groupPaid.style.display = 'block';
        if (Number(inputPaid.value) <= 0 || Number(inputPaid.value) >= net) {
            // Keep current value if valid, or focus
        }
        calculateBalanceHint();
    } else if (status === 'Paid') {
        groupPaid.style.display = 'none';
        inputPaid.value = net;
    } else {
        groupPaid.style.display = 'none';
        inputPaid.value = 0;
    }
}

function calculateBalanceHint() {
    const net = window.currentEditingNetSalary || 0;
    const paid = Number(document.getElementById('edit_amount_paid').value) || 0;
    const balance = Math.max(0, net - paid);
    const hint = document.getElementById('partial_balance_hint');
    if (hint) {
        hint.textContent = 'Remaining: PKR ' + balance.toLocaleString();
    }
}

function openAddDeductionModal(userId, userName) {
    const sel = document.getElementById('add_ded_user_id');
    if (userId) {
        sel.value = userId;
    } else {
        sel.value = "";
    }
    openModal('addDeductionModal');
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.style.cssText = `background:#fff;border:1px solid #e2e8f0;border-radius:6px;padding:10px 14px;display:flex;align-items:center;gap:8px;box-shadow:0 4px 12px rgba(0,0,0,.06);font-size:12px;min-width:220px;border-left:3px solid ${type==='success'?'#024442':'#dc2626'};`;
    t.innerHTML = `<span style="font-weight:700;color:${type==='success'?'#024442':'#dc2626'}">${type==='success'?'<i class="fas fa-check"></i>':'<i class="fas fa-times"></i>'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3500);
}

document.addEventListener('DOMContentLoaded', function() {
    // Check saved view preference (default is 'fit')
    const savedMode = localStorage.getItem('payroll_view_mode') || 'fit';
    setViewMode(savedMode);

    if (localStorage.getItem('open_add_penalty') === '1') {
        localStorage.removeItem('open_add_penalty');
        openAddDeductionModal();
    }
});
</script>

<?php if ($toast_message): ?>
<script>
document.addEventListener('DOMContentLoaded', () => showToast(<?= json_encode($toast_message) ?>, <?= json_encode($toast_type) ?>));
</script>
<?php endif; ?>

<?php include('dashboard_footer.php'); ?>
