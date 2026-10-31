<?php
/**
 * core/task_delay_helper.php
 * Comprehensive Engine for Task Delay Deductions, Extension Requests & Payroll Synchronization
 * Graphicafix Operational HR & Project Management
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/task_delay_schema.php';
require_once __DIR__ . '/attendance_schema.php';
require_once __DIR__ . '/attendance_helper.php';

// Ensure required tables & columns exist
if (isset($conn) && ($conn instanceof mysqli)) {
    ensure_task_delay_schema($conn);
    ensure_attendance_schema($conn);
}

/**
 * Returns configuration settings for task delay deductions.
 */
function getTaskDelaySettings($conn) {
    static $cached_settings = null;
    if ($cached_settings !== null) return $cached_settings;

    $defaults = [
        'tiers' => [
            'Low'    => ['daily' => 100.00, 'cap' => 500.00,  'grace_hours' => 24],
            'Medium' => ['daily' => 200.00, 'cap' => 1000.00, 'grace_hours' => 24],
            'High'   => ['daily' => 400.00, 'cap' => 2000.00, 'grace_hours' => 24]
        ],
        'monthly_free_graces' => 1,
        'max_monthly_salary_deduction_percent' => 15.00
    ];

    if (!$conn || !($conn instanceof mysqli)) return $defaults;

    try {
        $res = $conn->query("SELECT * FROM task_delay_settings");
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $p = ucfirst(strtolower($row['priority']));
                $defaults['tiers'][$p] = [
                    'daily'       => floatval($row['daily_penalty_pkr']),
                    'cap'         => floatval($row['max_cap_pkr']),
                    'grace_hours' => intval($row['grace_hours'])
                ];
                $defaults['monthly_free_graces'] = intval($row['monthly_free_graces']);
                $defaults['max_monthly_salary_deduction_percent'] = floatval($row['max_monthly_salary_deduction_percent']);
            }
        }
    } catch (Throwable $e) {
        error_log("getTaskDelaySettings error: " . $e->getMessage());
    }

    $cached_settings = $defaults;
    return $cached_settings;
}

/**
 * Counts how many tasks received a monthly free grace for this employee in a given month.
 */
function countTaskDelayGracesInMonth($conn, $user_id, $salary_month, $exclude_task_id = null) {
    if (!$conn || empty($user_id) || empty($salary_month)) return 0;
    try {
        $sql = "SELECT COUNT(*) as cnt FROM salary_deductions WHERE user_id = ? AND salary_month = ? AND deduction_type = 'Task Delay Grace'";
        if ($exclude_task_id) {
            $sql .= " AND task_id != " . intval($exclude_task_id);
        }
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("is", $user_id, $salary_month);
            $stmt->execute();
            $cnt = intval($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
            $stmt->close();
            return $cnt;
        }
    } catch (Throwable $e) {
        error_log("countTaskDelayGracesInMonth error: " . $e->getMessage());
    }
    return 0;
}

/**
 * Calculates task delay days and penalty deductions based on due date, completion status, priority, and safety caps.
 *
 * @param mysqli $conn Database connection
 * @param array|int $task Task row associative array or task ID
 * @param string|null $as_of_date YYYY-MM-DD or null for CURDATE
 * @return array
 */
function calculateTaskDelayDeduction($conn, $task, $as_of_date = null) {
    $result = [
        'task_id'            => 0,
        'user_id'            => 0,
        'is_delayed'         => false,
        'delay_days'         => 0,
        'penalty_amount'     => 0.00,
        'is_grace_period'    => false,
        'is_monthly_grace'   => false,
        'status_category'    => 'on_time',
        'reason'             => ''
    ];

    if (is_numeric($task)) {
        if (!$conn) return $result;
        $t_id = intval($task);
        $t_stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ? LIMIT 1");
        if ($t_stmt) {
            $t_stmt->bind_param("i", $t_id);
            $t_stmt->execute();
            $task = $t_stmt->get_result()->fetch_assoc();
            $t_stmt->close();
        }
    }

    if (!$task || empty($task['due_date'])) {
        $result['reason'] = 'No due date set';
        return $result;
    }

    $result['task_id'] = intval($task['id']);
    $result['user_id'] = intval($task['assigned_to'] ?? 0);
    $priority = ucfirst(strtolower($task['priority'] ?? 'Medium'));
    if (!in_array($priority, ['Low', 'Medium', 'High'])) $priority = 'Medium';

    $settings = getTaskDelaySettings($conn);
    $tier = $settings['tiers'][$priority] ?? ['daily' => 200.00, 'cap' => 1000.00, 'grace_hours' => 24];

    // Check if task is explicitly marked as exempted or extended
    if (($task['delay_status'] ?? '') === 'exempted') {
        $result['status_category'] = 'exempted';
        $result['reason'] = 'Task delay officially exempted by administration';
        return $result;
    }

    $due_date_str = $task['due_date']; // YYYY-MM-DD
    $due_timestamp = strtotime($due_date_str . ' 23:59:59');

    // ── Stop-clock rule ────────────────────────────────────────────────────────
    // If status is In Review or Completed, delay stops at submission/completion time
    $task_status = $task['status'] ?? 'Pending';
    $cutoff_timestamp = $as_of_date ? strtotime($as_of_date . ' 23:59:59') : time();

    if ($task_status === 'In Review' && !empty($task['submitted_for_review_at'])) {
        $cutoff_timestamp = strtotime($task['submitted_for_review_at']);
    } elseif ($task_status === 'Completed' && !empty($task['completed_at'])) {
        $cutoff_timestamp = strtotime($task['completed_at']);
    } elseif (in_array($task_status, ['In Review', 'Completed']) && !empty($task['updated_at'])) {
        $cutoff_timestamp = strtotime($task['updated_at']);
    }

    // If delivered on or before due date
    if ($cutoff_timestamp <= $due_timestamp) {
        $result['status_category'] = 'on_time';
        $result['reason'] = 'Completed or submitted on time';
        return $result;
    }

    // ── 24-Hour Grace Period ──────────────────────────────────────────────────
    $grace_seconds = ($tier['grace_hours'] ?? 24) * 3600;
    if ($cutoff_timestamp <= ($due_timestamp + $grace_seconds)) {
        $result['is_delayed'] = true;
        $result['is_grace_period'] = true;
        $result['delay_days'] = 1;
        $result['status_category'] = 'grace_period';
        $result['reason'] = 'Within standard ' . ($tier['grace_hours'] ?? 24) . '-hour post-deadline grace buffer';
        return $result;
    }

    // ── Calculate Overdue Business Days (Exclude Sundays) ─────────────────────
    $start_day = strtotime($due_date_str . ' +1 day 00:00:00');
    $current_scan = $start_day;
    $raw_delay_days = 0;

    while ($current_scan <= $cutoff_timestamp) {
        $day_of_week = date('w', $current_scan);
        if ($day_of_week != 0) { // Exclude Sundays (0 = Sunday)
            $raw_delay_days++;
        }
        $current_scan = strtotime('+1 day', $current_scan);
    }

    if ($raw_delay_days <= 0) {
        $result['status_category'] = 'on_time';
        $result['reason'] = 'Within weekend/holiday off-day allowance';
        return $result;
    }

    $result['is_delayed'] = true;
    $result['delay_days'] = $raw_delay_days;

    // ── Monthly Free Delay Grace Check ────────────────────────────────────────
    $salary_month = date('Y-m', $cutoff_timestamp);
    $user_id = $result['user_id'];
    $allowed_free_graces = $settings['monthly_free_graces'] ?? 1;

    if ($allowed_free_graces > 0 && $user_id > 0 && $conn) {
        $graces_used = countTaskDelayGracesInMonth($conn, $user_id, $salary_month, $result['task_id']);
        if ($graces_used < $allowed_free_graces && $raw_delay_days <= 2) {
            $result['is_monthly_grace'] = true;
            $result['status_category'] = 'monthly_grace';
            $result['penalty_amount'] = 0.00;
            $result['reason'] = "Monthly free task grace applied (Used {$graces_used}/{$allowed_free_graces})";
            return $result;
        }
    }

    // ── Compute Penalty with Per-Task Cap ──────────────────────────────────────
    $daily_rate = $tier['daily'];
    $task_cap   = $tier['cap'];
    $calculated_penalty = min($task_cap, $raw_delay_days * $daily_rate);

    // ── Apply Monthly Safety Cap (Max 15% of Base Salary) ─────────────────────
    if ($user_id > 0 && $conn) {
        $u_stmt = $conn->prepare("SELECT base_salary FROM users WHERE user_id = ? LIMIT 1");
        if ($u_stmt) {
            $u_stmt->bind_param("i", $user_id);
            $u_stmt->execute();
            $base_salary = floatval($u_stmt->get_result()->fetch_assoc()['base_salary'] ?? 50000.00);
            $u_stmt->close();

            $max_salary_percent = $settings['max_monthly_salary_deduction_percent'] ?? 15.00;
            $max_total_delay_deductions = round(($base_salary * $max_salary_percent) / 100, 2);

            // Sum other task delay deductions already applied for this user this month
            $d_stmt = $conn->prepare("SELECT IFNULL(SUM(amount), 0) as existing_task_ded 
                FROM salary_deductions 
                WHERE user_id = ? AND salary_month = ? AND deduction_type = 'Task Delay Penalty' AND COALESCE(task_id, 0) != ? AND status IN ('applied', 'disputed')");
            if ($d_stmt) {
                $d_stmt->bind_param("isi", $user_id, $salary_month, $result['task_id']);
                $d_stmt->execute();
                $existing_task_ded = floatval($d_stmt->get_result()->fetch_assoc()['existing_task_ded'] ?? 0);
                $d_stmt->close();

                $remaining_allowance = max(0.00, $max_total_delay_deductions - $existing_task_ded);
                $calculated_penalty = min($calculated_penalty, $remaining_allowance);
            }
        }
    }

    $result['penalty_amount'] = round($calculated_penalty, 2);
    $result['status_category'] = 'penalized';
    $result['reason'] = "Delayed by {$raw_delay_days} business day(s) at PKR {$daily_rate}/day (Capped at PKR {$calculated_penalty})";

    return $result;
}

/**
 * Evaluates a task and applies or updates the corresponding record in `salary_deductions`.
 * Automatically invokes syncUserMonthlySalary() to update the employee's payroll.
 */
function applyTaskDelayPenalty($conn, $task_id, $as_of_date = null) {
    if (!$conn || empty($task_id)) return false;

    try {
        $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$task || empty($task['assigned_to'])) return false;

        $user_id = intval($task['assigned_to']);
        $salary_month = $as_of_date ? substr($as_of_date, 0, 7) : date('Y-m');
        $date_incurred = $as_of_date ?: date('Y-m-d');

        $calc = calculateTaskDelayDeduction($conn, $task, $as_of_date);

        // Update task table delay tracking columns
        $upd_task = $conn->prepare("UPDATE tasks SET delay_days = ?, delay_deduction_amount = ?, delay_status = ?, delay_last_calculated_at = NOW() WHERE id = ?");
        if ($upd_task) {
            $upd_task->bind_param("idsi", $calc['delay_days'], $calc['penalty_amount'], $calc['status_category'], $task_id);
            $upd_task->execute();
            $upd_task->close();
        }

        // If penalized
        if ($calc['status_category'] === 'penalized' && $calc['penalty_amount'] > 0) {
            $description = "Task Delay Penalty: Task #{$task_id} - " . mb_strimwidth($task['title'], 0, 45, '...') . " ({$calc['delay_days']} days overdue)";

            // Check if deduction record already exists for this task
            $chk_ded = $conn->prepare("SELECT id, status FROM salary_deductions WHERE task_id = ? AND salary_month = ? AND deduction_type = 'Task Delay Penalty' LIMIT 1");
            if ($chk_ded) {
                $chk_ded->bind_param("is", $task_id, $salary_month);
                $chk_ded->execute();
                $existing = $chk_ded->get_result()->fetch_assoc();
                $chk_ded->close();

                if ($existing) {
                    // If HR previously waived it, do not re-apply penalty
                    if ($existing['status'] === 'waived') {
                        return true;
                    }
                    // Update existing deduction amount and overdue days
                    $upd_ded = $conn->prepare("UPDATE salary_deductions SET amount = ?, delay_days_count = ?, description = ?, date_incurred = ? WHERE id = ?");
                    if ($upd_ded) {
                        $upd_ded->bind_param("disi", $calc['penalty_amount'], $calc['delay_days'], $description, $date_incurred, $existing['id']);
                        $upd_ded->execute();
                        $upd_ded->close();
                    }
                } else {
                    // Insert new deduction
                    $ins_ded = $conn->prepare("INSERT INTO salary_deductions 
                        (user_id, salary_month, task_id, delay_days_count, deduction_type, amount, description, date_incurred, status) 
                        VALUES (?, ?, ?, ?, 'Task Delay Penalty', ?, ?, ?, 'applied')");
                    if ($ins_ded) {
                        $ins_ded->bind_param("isiidss", $user_id, $salary_month, $task_id, $calc['delay_days'], $calc['penalty_amount'], $description, $date_incurred);
                        $ins_ded->execute();
                        $ins_ded->close();
                    }
                }
            }

            // Sync employee's monthly salary
            syncUserMonthlySalary($conn, $user_id, $salary_month);
            return true;
        } 
        
        // If monthly grace was applied, record a 0 PKR informational entry
        if ($calc['status_category'] === 'monthly_grace') {
            $chk_grace = $conn->prepare("SELECT id FROM salary_deductions WHERE task_id = ? AND salary_month = ? AND deduction_type = 'Task Delay Grace' LIMIT 1");
            if ($chk_grace) {
                $chk_grace->bind_param("is", $task_id, $salary_month);
                $chk_grace->execute();
                $has_grace = $chk_grace->get_result()->fetch_assoc();
                $chk_grace->close();

                if (!$has_grace) {
                    $grace_desc = "Task Delay Grace: Task #{$task_id} - " . mb_strimwidth($task['title'], 0, 45, '...') . " (Overdue grace applied)";
                    $ins_grace = $conn->prepare("INSERT INTO salary_deductions 
                        (user_id, salary_month, task_id, delay_days_count, deduction_type, amount, description, date_incurred, status) 
                        VALUES (?, ?, ?, ?, 'Task Delay Grace', 0.00, ?, ?, 'applied')");
                    if ($ins_grace) {
                        $ins_grace->bind_param("isiiss", $user_id, $salary_month, $task_id, $calc['delay_days'], $grace_desc, $date_incurred);
                        $ins_grace->execute();
                        $ins_grace->close();
                    }
                }
            }
            return true;
        }

        // If on time or within grace, ensure no spurious active penalty remains
        if ($calc['status_category'] === 'on_time') {
            $del_ded = $conn->prepare("DELETE FROM salary_deductions WHERE task_id = ? AND deduction_type = 'Task Delay Penalty' AND status = 'applied'");
            if ($del_ded) {
                $del_ded->bind_param("i", $task_id);
                $del_ded->execute();
                if ($del_ded->affected_rows > 0) {
                    syncUserMonthlySalary($conn, $user_id, $salary_month);
                }
                $del_ded->close();
            }
        }

        return true;
    } catch (Throwable $e) {
        error_log("applyTaskDelayPenalty error: " . $e->getMessage());
    }
    return false;
}

/**
 * Stamps task milestone timestamps when status changes.
 */
function recordTaskStatusTimestamp($conn, $task_id, $new_status) {
    if (!$conn || empty($task_id)) return;
    try {
        if ($new_status === 'In Review') {
            $conn->query("UPDATE tasks SET submitted_for_review_at = COALESCE(submitted_for_review_at, NOW()) WHERE id = " . intval($task_id));
        } elseif ($new_status === 'Completed') {
            $conn->query("UPDATE tasks SET completed_at = COALESCE(completed_at, NOW()) WHERE id = " . intval($task_id));
        }

        // Re-evaluate task delay deduction to lock in final amount
        applyTaskDelayPenalty($conn, $task_id);
    } catch (Throwable $e) {
        error_log("recordTaskStatusTimestamp error: " . $e->getMessage());
    }
}

/**
 * Submits a deadline extension request for a task.
 */
function submitTaskExtensionRequest($conn, $task_id, $user_id, $requested_date, $reason_category, $reason) {
    if (!$conn || empty($task_id) || empty($requested_date)) {
        return ['success' => false, 'message' => 'Missing required extension parameters.'];
    }

    try {
        $stmt = $conn->prepare("SELECT id, due_date, assigned_to FROM tasks WHERE id = ? LIMIT 1");
        if (!$stmt) return ['success' => false, 'message' => 'Database error.'];
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$task) return ['success' => false, 'message' => 'Task not found.'];

        $orig_due = $task['due_date'] ?: date('Y-m-d');

        if (strtotime($requested_date) <= strtotime($orig_due)) {
            return ['success' => false, 'message' => 'Requested extension date must be after current due date (' . date('M j, Y', strtotime($orig_due)) . ').'];
        }

        // Record in task_extension_history
        $ins = $conn->prepare("INSERT INTO task_extension_history 
            (task_id, user_id, original_due_date, requested_due_date, reason_category, reason, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        if ($ins) {
            $ins->bind_param("iissss", $task_id, $user_id, $orig_due, $requested_date, $reason_category, $reason);
            $ins->execute();
            $ins->close();
        }

        // Update task record
        $upd = $conn->prepare("UPDATE tasks SET 
            extension_status = 'pending', 
            extension_requested_date = ?, 
            extension_reason_category = ?,
            extension_reason = ? 
            WHERE id = ?");
        if ($upd) {
            $upd->bind_param("sssi", $requested_date, $reason_category, $reason, $task_id);
            $upd->execute();
            $upd->close();
        }

        return ['success' => true, 'message' => 'Extension request submitted successfully! Awaiting manager approval.'];
    } catch (Throwable $e) {
        return ['success' => false, 'message' => 'Extension request failed: ' . $e->getMessage()];
    }
}

/**
 * Approves or rejects a task deadline extension request.
 */
function reviewTaskExtensionRequest($conn, $task_id, $admin_id, $decision, $admin_notes = '') {
    if (!$conn || empty($task_id) || !in_array($decision, ['approved', 'rejected'])) {
        return ['success' => false, 'message' => 'Invalid parameters.'];
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ? LIMIT 1");
        if (!$stmt) return ['success' => false, 'message' => 'Database error.'];
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$task) return ['success' => false, 'message' => 'Task not found.'];

        $user_id = intval($task['assigned_to']);
        $salary_month = date('Y-m');

        if ($decision === 'approved') {
            $new_due_date = $task['extension_requested_date'];
            if (!$new_due_date) {
                return ['success' => false, 'message' => 'No requested extension date found on task.'];
            }

            // Update task
            $upd = $conn->prepare("UPDATE tasks SET 
                due_date = ?, 
                extension_status = 'approved', 
                extension_reviewed_by = ?,
                extension_admin_notes = ?,
                delay_status = 'extended',
                delay_days = 0,
                delay_deduction_amount = 0.00
                WHERE id = ?");
            if ($upd) {
                $upd->bind_param("sisi", $new_due_date, $admin_id, $admin_notes, $task_id);
                $upd->execute();
                $upd->close();
            }

            // Waive any existing delay deduction for this task
            $waive_ded = $conn->prepare("UPDATE salary_deductions 
                SET status = 'waived', description = CONCAT(description, ' [Waived: Extension Approved]') 
                WHERE task_id = ? AND deduction_type = 'Task Delay Penalty' AND status = 'applied'");
            if ($waive_ded) {
                $waive_ded->bind_param("i", $task_id);
                $waive_ded->execute();
                $waive_ded->close();
            }

            // Update history
            $upd_hist = $conn->prepare("UPDATE task_extension_history SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), admin_notes = ? WHERE task_id = ? AND status = 'pending'");
            if ($upd_hist) {
                $upd_hist->bind_param("isi", $admin_id, $admin_notes, $task_id);
                $upd_hist->execute();
                $upd_hist->close();
            }

            // Recalculate payroll
            syncUserMonthlySalary($conn, $user_id, $salary_month);

            return ['success' => true, 'message' => 'Extension approved. Due date updated to ' . date('M j, Y', strtotime($new_due_date)) . ' and delay penalties waived.'];
        } else {
            // Rejected
            $upd = $conn->prepare("UPDATE tasks SET extension_status = 'rejected', extension_reviewed_by = ?, extension_admin_notes = ? WHERE id = ?");
            if ($upd) {
                $upd->bind_param("isi", $admin_id, $admin_notes, $task_id);
                $upd->execute();
                $upd->close();
            }

            $upd_hist = $conn->prepare("UPDATE task_extension_history SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), admin_notes = ? WHERE task_id = ? AND status = 'pending'");
            if ($upd_hist) {
                $upd_hist->bind_param("isi", $admin_id, $admin_notes, $task_id);
                $upd_hist->execute();
                $upd_hist->close();
            }

            return ['success' => true, 'message' => 'Extension request rejected.'];
        }
    } catch (Throwable $e) {
        return ['success' => false, 'message' => 'Review error: ' . $e->getMessage()];
    }
}

/**
 * Exempts a task delay and waives any associated deductions directly.
 */
function exemptTaskDelayPenalty($conn, $task_id, $admin_id, $admin_notes = '') {
    if (!$conn || empty($task_id)) return false;

    try {
        $stmt = $conn->prepare("SELECT id, assigned_to FROM tasks WHERE id = ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$task) return false;

        $user_id = intval($task['assigned_to']);
        $salary_month = date('Y-m');

        // Mark task as exempted with audit notes
        $admin_id_val = intval($admin_id);
        $escaped_notes = $conn->real_escape_string($admin_notes);
        $conn->query("UPDATE tasks SET delay_status = 'exempted', delay_deduction_amount = 0.00, delay_exempted_by = {$admin_id_val}, delay_exemption_reason = '{$escaped_notes}' WHERE id = " . intval($task_id));

        // Waive deduction in salary_deductions
        $conn->query("UPDATE salary_deductions SET status = 'waived', description = CONCAT(description, ' [Admin Exemption]') WHERE task_id = " . intval($task_id) . " AND status IN ('applied', 'disputed')");

        // Recalculate payroll
        syncUserMonthlySalary($conn, $user_id, $salary_month);
        return true;
    } catch (Throwable $e) {
        error_log("exemptTaskDelayPenalty error: " . $e->getMessage());
    }
    return false;
}


// ─────────────────────────────────────────────────────────────────────────────
// WORKFLOW STAGING, PERMISSION ENFORCEMENT, SUBTASKS & COLLABORATION
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Checks whether the user role has administrative/managerial permission to complete tasks.
 * Strictly restricted to Super Admins, Admins, Project Managers (PM), and Managers.
 */
function canUserCompleteTask($role) {
    $clean_role = strtolower(trim($role ?? ''));
    return in_array($clean_role, ['super_admin', 'superadmin', 'admin', 'pm', 'manager']);
}

/**
 * Advances a task to a target workflow stage with permission checks and history logging.
 * The 7 Stages: Brief -> Assignment -> Production -> Internal Review -> Client Approval -> Publication/Delivery -> Reporting
 */
function advanceTaskStage($conn, $task_id, $target_stage, $user_id, $user_role, $comment = '') {
    if (!$conn || empty($task_id) || empty($target_stage)) {
        return ['success' => false, 'message' => 'Missing task ID or target stage.'];
    }

    $valid_stages = [
        'Brief', 
        'Assignment', 
        'Production', 
        'Internal Review', 
        'Client Approval', 
        'Publication/Delivery', 
        'Reporting'
    ];

    if (!in_array($target_stage, $valid_stages)) {
        return ['success' => false, 'message' => "Invalid workflow stage: {$target_stage}."];
    }

    // STRICT PERMISSION CHECK:
    // Only Super Admins, Admins, and PMs can advance to final delivery/reporting or complete a task!
    if (in_array($target_stage, ['Publication/Delivery', 'Reporting'])) {
        if (!canUserCompleteTask($user_role)) {
            return [
                'success' => false, 
                'message' => 'Restricted: Only Super Admins, Admins, or Project Managers can approve final delivery and completion. Please advance your task to "Internal Review" for manager sign-off.'
            ];
        }
    }

    try {
        $stmt = $conn->prepare("SELECT id, stage, status, assigned_to FROM tasks WHERE id = ? LIMIT 1");
        if (!$stmt) return ['success' => false, 'message' => 'Database error.'];
        $stmt->bind_param("i", $task_id);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$task) return ['success' => false, 'message' => 'Task not found.'];

        $from_stage = $task['stage'] ?: 'Brief';

        // Map stage to status if appropriate
        $new_status = $task['status'];
        if ($target_stage === 'Production') {
            $new_status = 'In Progress';
        } elseif ($target_stage === 'Internal Review') {
            $new_status = 'In Review';
        } elseif ($target_stage === 'Reporting') {
            $new_status = 'Completed';
        }

        // Update task record
        $upd_sql = "UPDATE tasks SET stage = ?, status = ?, updated_at = NOW()";
        if ($target_stage === 'Internal Review') {
            $upd_sql .= ", submitted_for_review_at = COALESCE(submitted_for_review_at, NOW())";
        } elseif ($target_stage === 'Reporting' || $new_status === 'Completed') {
            $upd_sql .= ", completed_at = COALESCE(completed_at, NOW())";
        }
        $upd_sql .= " WHERE id = ?";

        $upd = $conn->prepare($upd_sql);
        if ($upd) {
            $upd->bind_param("ssi", $target_stage, $new_status, $task_id);
            $upd->execute();
            $upd->close();
        }

        // Log to task_workflow_history
        $hist = $conn->prepare("INSERT INTO task_workflow_history (task_id, from_stage, to_stage, changed_by, comment) VALUES (?, ?, ?, ?, ?)");
        if ($hist) {
            $hist->bind_param("issis", $task_id, $from_stage, $target_stage, $user_id, $comment);
            $hist->execute();
            $hist->close();
        }

        // Re-evaluate delay penalties
        applyTaskDelayPenalty($conn, $task_id);

        return [
            'success'   => true, 
            'from_stage'=> $from_stage,
            'to_stage'  => $target_stage,
            'new_status'=> $new_status,
            'message'   => "Task advanced to {$target_stage} successfully."
        ];
    } catch (Throwable $e) {
        return ['success' => false, 'message' => 'Stage update error: ' . $e->getMessage()];
    }
}

/**
 * Returns all subtasks for a task.
 */
function getTaskSubtasks($conn, $task_id) {
    if (!$conn || empty($task_id)) return [];
    try {
        $stmt = $conn->prepare("SELECT s.*, u.name as assignee_name 
            FROM task_subtasks s 
            LEFT JOIN users u ON s.assigned_to = u.user_id 
            WHERE s.task_id = ? 
            ORDER BY s.id ASC");
        if ($stmt) {
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $rows;
        }
    } catch (Throwable $e) {
        error_log("getTaskSubtasks error: " . $e->getMessage());
    }
    return [];
}

/**
 * Adds or toggles completion of a subtask.
 */
function toggleSubtaskStatus($conn, $subtask_id, $new_status) {
    if (!$conn || empty($subtask_id)) return false;
    $status = ($new_status === 'Completed') ? 'Completed' : 'Pending';
    try {
        $stmt = $conn->prepare("UPDATE task_subtasks SET status = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $status, $subtask_id);
            $stmt->execute();
            $stmt->close();
            return true;
        }
    } catch (Throwable $e) {
        error_log("toggleSubtaskStatus error: " . $e->getMessage());
    }
    return false;
}

/**
 * Creates a new subtask.
 */
function createSubtask($conn, $task_id, $title, $assigned_to = null, $due_date = null) {
    if (!$conn || empty($task_id) || empty($title)) return false;
    try {
        $stmt = $conn->prepare("INSERT INTO task_subtasks (task_id, title, assigned_to, due_date, status) VALUES (?, ?, ?, ?, 'Pending')");
        if ($stmt) {
            $stmt->bind_param("isis", $task_id, $title, $assigned_to, $due_date);
            $stmt->execute();
            $new_id = $conn->insert_id;
            $stmt->close();
            return $new_id;
        }
    } catch (Throwable $e) {
        error_log("createSubtask error: " . $e->getMessage());
    }
    return false;
}

/**
 * Returns all discussion comments for a task.
 */
function getTaskComments($conn, $task_id) {
    if (!$conn || empty($task_id)) return [];
    try {
        $stmt = $conn->prepare("SELECT c.*, u.name as user_name, u.role, u.department 
            FROM task_comments c 
            JOIN users u ON c.user_id = u.user_id 
            WHERE c.task_id = ? 
            ORDER BY c.created_at ASC");
        if ($stmt) {
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $rows;
        }
    } catch (Throwable $e) {
        error_log("getTaskComments error: " . $e->getMessage());
    }
    return [];
}

/**
 * Posts a comment to a task.
 */
function addTaskComment($conn, $task_id, $user_id, $comment_text) {
    if (!$conn || empty($task_id) || empty($user_id) || empty(trim($comment_text))) return false;
    try {
        $stmt = $conn->prepare("INSERT INTO task_comments (task_id, user_id, comment_text) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iis", $task_id, $user_id, $comment_text);
            $stmt->execute();
            $new_id = $conn->insert_id;
            $stmt->close();
            return $new_id;
        }
    } catch (Throwable $e) {
        error_log("addTaskComment error: " . $e->getMessage());
    }
    return false;
}

/**
 * Returns all files/deliverables attached to a task.
 */
function getTaskFiles($conn, $task_id) {
    if (!$conn || empty($task_id)) return [];
    try {
        $stmt = $conn->prepare("SELECT f.*, u.name as uploader_name 
            FROM task_files f 
            LEFT JOIN users u ON f.uploaded_by = u.user_id 
            WHERE f.task_id = ? 
            ORDER BY f.uploaded_at DESC");
        if ($stmt) {
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $rows;
        }
    } catch (Throwable $e) {
        error_log("getTaskFiles error: " . $e->getMessage());
    }
    return [];
}

/**
 * Returns workflow stage transition history for a task.
 */
function getTaskWorkflowHistory($conn, $task_id) {
    if (!$conn || empty($task_id)) return [];
    try {
        $stmt = $conn->prepare("SELECT h.*, u.name as changer_name 
            FROM task_workflow_history h 
            LEFT JOIN users u ON h.changed_by = u.user_id 
            WHERE h.task_id = ? 
            ORDER BY h.created_at ASC");
        if ($stmt) {
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $rows;
        }
    } catch (Throwable $e) {
        error_log("getTaskWorkflowHistory error: " . $e->getMessage());
    }
    return [];
}
