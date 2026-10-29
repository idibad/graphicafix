<?php
/**
 * core/attendance_helper.php
 * Core Business Logic for Geolocation Attendance, Gender-Based Grace Periods,
 * Late Pattern Detection, Medical Leave Limits, and Payroll Synchronization.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/attendance_schema.php';

// ── Geolocation & Office Configuration ─────────────────────────────────────────
// Office Coordinates: 33°30'21.6"N 73°02'47.7"E (Decimal: 33.506000, 73.046583)
if (!defined('OFFICE_LATITUDE')) {
    define('OFFICE_LATITUDE', floatval($_ENV['OFFICE_LAT'] ?? 33.506000));
}
if (!defined('OFFICE_LONGITUDE')) {
    define('OFFICE_LONGITUDE', floatval($_ENV['OFFICE_LNG'] ?? 73.046583));
}
if (!defined('OFFICE_RADIUS_METERS')) {
    define('OFFICE_RADIUS_METERS', intval($_ENV['OFFICE_RADIUS_METERS'] ?? 300)); // 300 meters geofence
}

/**
 * Calculates distance between two GPS coordinates using Haversine formula (meters)
 */
function calculateHaversineDistance($lat1, $lon1, $lat2, $lon2) {
    if (empty($lat1) || empty($lon1) || empty($lat2) || empty($lon2)) {
        return null;
    }
    $earthRadius = 6371000; // in meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earthRadius * $c, 1);
}

/**
 * Checks if an IP is within the office whitelist
 */
function isOfficeIp($user_ip) {
    $office_ips = [
        '154.91.*',           // Office Fiber / Wi-Fi subnet (e.g. 154.91.165.75)
        '149.40.*',           // Office Broadband subnet (e.g. 149.40.212.65)
        '154.192.*',          // Office DSL / secondary line (e.g. 154.192.223.181)
        '2402:e000:*',        // Office IPv6
        '127.0.0.1',
        '::1'
    ];
    $user_ip = trim($user_ip ?? '');
    if (empty($user_ip)) return false;

    foreach ($office_ips as $allowed) {
        if ($allowed === $user_ip) return true;
        if (strpos($allowed, '*') !== false) {
            $prefix = str_replace('*', '', $allowed);
            if (strpos($user_ip, $prefix) === 0) return true;
        }
    }

    // Dynamic office check: did another employee verify office presence with GPS from this exact IP today?
    global $conn;
    if (!empty($conn) && $conn instanceof mysqli) {
        try {
            $chk = $conn->prepare("SELECT id FROM attendance_records 
                WHERE attendance_date = CURDATE() 
                  AND ip_address = ? 
                  AND location_type = 'onsite' 
                  AND latitude IS NOT NULL 
                  AND (distance_meters IS NULL OR distance_meters <= 350) 
                LIMIT 1");
            if ($chk) {
                $chk->bind_param("s", $user_ip);
                $chk->execute();
                $res = $chk->get_result();
                $is_office = ($res && $res->num_rows > 0);
                $chk->close();
                if ($is_office) return true;
            }
        } catch (Throwable $e) {}
    }

    return false;
}

/**
 * Retrieves user profile metadata (gender, employee_id, department, title, salary)
 */
function getUserAttendanceMeta($conn, $user_id) {
    $meta = [
        'user_id'     => $user_id,
        'name'        => '',
        'username'    => '',
        'role'        => 'user',
        'gender'      => 'Male',
        'employee_id' => 'EMP-' . str_pad($user_id, 3, '0', STR_PAD_LEFT),
        'department'  => 'General',
        'job_title'   => 'Staff',
        'base_salary' => 50000.00,
        'allowances'  => 0.00
    ];

    if (!$conn || empty($user_id)) return $meta;

    try {
        $stmt = $conn->prepare("SELECT user_id, name, username, role, gender, work_mode, employee_id, department, job_title, base_salary, allowances FROM users WHERE user_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $meta['name']        = $row['name'] ?? '';
                $meta['username']    = $row['username'] ?? '';
                $meta['role']        = $row['role'] ?? 'user';
                $meta['gender']      = !empty($row['gender']) ? ucfirst(strtolower(trim($row['gender']))) : 'Male';
                $meta['work_mode']   = (!empty($row['work_mode']) && strtolower(trim($row['work_mode'])) === 'onsite') ? 'onsite' : 'remote';
                $meta['employee_id'] = !empty($row['employee_id']) ? $row['employee_id'] : $meta['employee_id'];
                $meta['department']  = !empty($row['department']) ? $row['department'] : 'General';
                $meta['job_title']   = !empty($row['job_title']) ? $row['job_title'] : 'Staff';
                $meta['base_salary'] = floatval($row['base_salary'] ?? 50000);
                $meta['allowances']  = floatval($row['allowances'] ?? 0);
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
        // Resilient fallback if custom columns are missing
        try {
            $stmt_b = $conn->prepare("SELECT user_id, name, username, role FROM users WHERE user_id = ? LIMIT 1");
            if ($stmt_b) {
                $stmt_b->bind_param("i", $user_id);
                $stmt_b->execute();
                if ($brow = $stmt_b->get_result()->fetch_assoc()) {
                    $meta['name']     = $brow['name'] ?? '';
                    $meta['username'] = $brow['username'] ?? '';
                    $meta['role']     = $brow['role'] ?? 'user';
                }
                $stmt_b->close();
            }
        } catch (Throwable $e2) {}
    }

    // Fallback from `team` table if employee_id, department or job_title is default/empty
    if (empty($meta['employee_id']) || $meta['department'] === 'General' || $meta['job_title'] === 'Staff') {
        try {
            $stmt_team = $conn->prepare("SELECT employee_id, gender, department, role FROM team WHERE LOWER(TRIM(member_name)) = LOWER(TRIM(?)) OR LOWER(TRIM(employee_id)) = LOWER(TRIM(?)) LIMIT 1");
            if ($stmt_team) {
                $stmt_team->bind_param("ss", $meta['name'], $meta['employee_id']);
                $stmt_team->execute();
                $t_res = $stmt_team->get_result();
                if ($t_row = $t_res->fetch_assoc()) {
                    if (!empty($t_row['gender']) && $meta['gender'] === 'Male') $meta['gender'] = ucfirst(strtolower(trim($t_row['gender'])));
                    if (!empty($t_row['employee_id'])) $meta['employee_id'] = $t_row['employee_id'];
                    if (!empty($t_row['department']) && $meta['department'] === 'General') $meta['department'] = $t_row['department'];
                    if (!empty($t_row['role']) && $meta['job_title'] === 'Staff') $meta['job_title'] = $t_row['role'];
                }
                $stmt_team->close();
            }
        } catch (Throwable $e_team) {
            // Silently continue if team table does not exist or schema differs
        }
    }

    return $meta;
}

/**
 * Returns today's attendance record for a user (or null)
 */
function getTodayAttendanceRecord($conn, $user_id, $date = null) {
    if (!$conn || empty($user_id)) return null;
    $date = $date ?: date('Y-m-d');

    try {
        $stmt = $conn->prepare("SELECT * FROM attendance_records WHERE user_id = ? AND attendance_date = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("is", $user_id, $date);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row;
        }
    } catch (Throwable $e) {
        error_log("getTodayAttendanceRecord notice: " . $e->getMessage());
    }
    return null;
}

/**
 * Counts how many times this user has arrived in the grace period in the given month
 */
function countGracePeriodArrivalsInMonth($conn, $user_id, $month, $exclude_date = null) {
    if (!$conn || empty($user_id)) return 0;
    $month = $month ?: date('Y-m');
    $m_start = $month . '-01';
    $m_end   = date('Y-m-t', strtotime($m_start));

    try {
        $sql = "SELECT COUNT(*) as cnt FROM attendance_records 
                WHERE user_id = ? 
                  AND attendance_date BETWEEN ? AND ? 
                  AND is_grace_period = 1";
        if ($exclude_date) {
            $sql .= " AND attendance_date != ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) $stmt->bind_param("isss", $user_id, $m_start, $m_end, $exclude_date);
        } else {
            $stmt = $conn->prepare($sql);
            if ($stmt) $stmt->bind_param("iss", $user_id, $m_start, $m_end);
        }

        $count = 0;
        if ($stmt && $stmt->execute()) {
            $res = $stmt->get_result()->fetch_assoc();
            $count = intval($res['cnt'] ?? 0);
            $stmt->close();
        }
        return $count;
    } catch (Throwable $e) {
        error_log("countGracePeriodArrivalsInMonth notice: " . $e->getMessage());
        return 0;
    }
}

/**
 * Counts medical leaves taken by a user in the given month (Limit: 1 per month)
 */
function countMedicalLeavesInMonth($conn, $user_id, $month, $exclude_date = null) {
    if (!$conn || empty($user_id)) return 0;
    $month = $month ?: date('Y-m');
    $m_start = $month . '-01';
    $m_end   = date('Y-m-t', strtotime($m_start));

    $count = 0;
    try {
        // Count from attendance_records
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM attendance_records 
            WHERE user_id = ? 
              AND attendance_date BETWEEN ? AND ? 
              AND (leave_type = 'medical' OR status = 'on_leave')
              " . ($exclude_date ? "AND attendance_date != ?" : ""));
        if ($stmt) {
            if ($exclude_date) {
                $stmt->bind_param("isss", $user_id, $m_start, $m_end, $exclude_date);
            } else {
                $stmt->bind_param("iss", $user_id, $m_start, $m_end);
            }
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $count += intval($res['cnt'] ?? 0);
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log("countMedicalLeaves records notice: " . $e->getMessage());
    }

    try {
        // Count from attendance_overrides
        $stmt2 = $conn->prepare("SELECT COUNT(*) as cnt FROM attendance_overrides 
            WHERE user_id = ? 
              AND attendance_date BETWEEN ? AND ? 
              AND attendance_type = 'leave' 
              AND (leave_category = 'medical' OR LOWER(reason) LIKE '%medical%' OR LOWER(reason) LIKE '%sick%')
              " . ($exclude_date ? "AND attendance_date != ?" : ""));
        if ($stmt2) {
            if ($exclude_date) {
                $stmt2->bind_param("isss", $user_id, $m_start, $m_end, $exclude_date);
            } else {
                $stmt2->bind_param("iss", $user_id, $m_start, $m_end);
            }
            $stmt2->execute();
            $res2 = $stmt2->get_result()->fetch_assoc();
            $count += intval($res2['cnt'] ?? 0);
            $stmt2->close();
        }
    } catch (Throwable $e) {
        error_log("countMedicalLeaves overrides notice: " . $e->getMessage());
    }

    return $count;
}

/**
 * Main Check-In Engine:
 * - Checks location coordinates vs office or marks denied
 * - Computes late vs on time based on gender grace periods:
 *     - Male: 10 mins grace (cutoff 09:10:00)
 *     - Female: 30 mins grace (cutoff 09:30:00)
 * - Pattern detection:
 *     - Max 3 grace arrivals allowed per month
 *     - 4th+ arrival in grace period is penalized (100 PKR) & flagged as habitual late pattern
 * - If late past grace period -> 100 PKR penalty
 * - Automatically registers penalty in `salary_deductions` and updates `salaries`
 */
function recordDailyCheckIn($conn, $user_id, $lat = null, $lng = null, $accuracy = null, $location_denied = false, $notes = '') {
    try {
        ensure_attendance_schema($conn);

        $date_today = date('Y-m-d');
        $time_now   = date('H:i:s');
        $month_now  = date('Y-m');

        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        // Check if already checked in today
        $existing = getTodayAttendanceRecord($conn, $user_id, $date_today);
        if ($existing && !empty($existing['check_in_time']) && (!empty($existing['latitude']) || !empty($existing['location_denied']))) {
            return [
                'success' => true,
                'message' => 'Already checked in today',
                'record'  => $existing
            ];
        }

        $user_meta = getUserAttendanceMeta($conn, $user_id);
        $gender    = $user_meta['gender']; // 'Male' or 'Female'

        // ── Location Resolution & Policy Check ────────────────────────────────────
        $work_mode = strtolower(trim($user_meta['work_mode'] ?? 'remote'));
        if ($work_mode !== 'onsite') {
            $work_mode = 'remote';
        }

        $location_type   = 'unknown';
        $distance_meters = null;
        $loc_denied_flag = $location_denied ? 1 : 0;
        $loc_penalty     = 0.00;
        $loc_penalty_reason = null;

        if ($work_mode === 'remote') {
            // Remote employee: exempt from office radius / location verification
            $location_type = 'remote';
            if (!empty($lat) && !empty($lng)) {
                $distance_meters = calculateHaversineDistance($lat, $lng, OFFICE_LATITUDE, OFFICE_LONGITUDE);
            }
            // Remote employees never receive location penalties
            $loc_penalty = 0.00;
        } else {
            // On-site employee: office presence verification
            if (isOfficeIp($ip_address)) {
                // Connected to physical office Wi-Fi / broadband gateway!
                $location_type = 'onsite';
                $loc_denied_flag = 0; // Overridden: verified via verified office network
                $loc_penalty = 0.00;
                if (!empty($lat) && !empty($lng)) {
                    $distance_meters = calculateHaversineDistance($lat, $lng, OFFICE_LATITUDE, OFFICE_LONGITUDE);
                }
            } elseif (!empty($lat) && !empty($lng)) {
                $distance_meters = calculateHaversineDistance($lat, $lng, OFFICE_LATITUDE, OFFICE_LONGITUDE);
                if ($distance_meters !== null && $distance_meters <= OFFICE_RADIUS_METERS) {
                    $location_type = 'onsite';
                    $loc_penalty = 0.00;
                } else {
                    $location_type = 'remote'; // Checked in offsite
                    $loc_penalty = 100.00;
                    $loc_penalty_reason = 'Off-site check-in (' . round($distance_meters) . 'm from office)';
                }
            } elseif ($location_denied) {
                // Denied location access AND not connected to office network
                $location_type = 'denied';
                $loc_penalty = 100.00;
                $loc_penalty_reason = 'Location access denied (On-site employee)';
            } else {
                // Hardware without GPS and not on office network
                $location_type = 'denied';
                $loc_denied_flag = 1;
                $loc_penalty = 100.00;
                $loc_penalty_reason = 'Location unverified outside office network';
            }
        }

        // ── Grace Period & Late Logic ─────────────────────────────────────────────
        // Base official time: 09:00:00 AM
        // Male grace: 10 minutes -> 09:10:00
        // Female grace: 30 minutes -> 09:30:00
        $base_time = defined('OFFICE_START_TIME') ? OFFICE_START_TIME : ($_ENV['OFFICE_START_TIME'] ?? '09:00:00');
        $male_grace_mins = intval($_ENV['MALE_GRACE_MINUTES'] ?? 10);
        $female_grace_mins = intval($_ENV['FEMALE_GRACE_MINUTES'] ?? 30);
        $grace_mins = ($gender === 'Female') ? $female_grace_mins : $male_grace_mins;
        $grace_cutoff = date('H:i:s', strtotime($base_time) + ($grace_mins * 60));

        $is_late             = 0;
        $is_grace_period     = 0;
        $grace_count_month   = 0;
        $late_penalty        = 0.00;
        $late_penalty_reason = null;
        $status              = 'present';
        $late_minutes        = 0;

        $seconds_now = strtotime($time_now);
        $seconds_base = strtotime($base_time);
        $seconds_cutoff = strtotime($grace_cutoff);

        if ($seconds_now <= $seconds_base) {
            // Exactly on time or early
            $status          = 'present';
            $is_late         = 0;
            $is_grace_period = 0;
        } elseif ($seconds_now > $seconds_base && $seconds_now <= $seconds_cutoff) {
            // Arrived within grace period!
            $is_grace_period = 1;
            $prev_grace_count = countGracePeriodArrivalsInMonth($conn, $user_id, $month_now, $date_today);
            $grace_count_month = $prev_grace_count + 1;

            if ($grace_count_month <= 3) {
                // Within allowed grace arrivals this month
                $status              = 'present';
                $is_late             = 0;
                $late_penalty        = 0.00;
                $late_penalty_reason = null;
            } else {
                // Pattern detected!
                $status              = 'late';
                $is_late             = 1;
                $late_penalty        = 100.00;
                $late_penalty_reason = "Excess grace arrival (Arrival #{$grace_count_month} this month)";
                $late_minutes        = max(0, round(($seconds_now - $seconds_base) / 60));
            }
        } else {
            // Arrived past cutoff
            $status              = 'late';
            $is_late             = 1;
            $late_penalty        = 100.00;
            $late_penalty_reason = "Late arrival: Signed in at " . date('h:i A', $seconds_now);
            $late_minutes        = max(0, round(($seconds_now - $seconds_base) / 60));
        }

        // ── Consolidate Penalties ─────────────────────────────────────────────────
        $penalty_amount = 0.00;
        $penalty_reasons = [];

        if ($is_late && $late_penalty > 0) {
            $penalty_amount += $late_penalty;
            $penalty_reasons[] = $late_penalty_reason;
        }
        if ($loc_penalty > 0) {
            $penalty_amount += $loc_penalty;
            $penalty_reasons[] = $loc_penalty_reason;
        }

        $penalty_reason = !empty($penalty_reasons) ? implode('; ', $penalty_reasons) : null;

        // ── Save or Update `attendance_records` ────────────────────────────────────
        $stmt = $conn->prepare("INSERT INTO attendance_records 
            (user_id, attendance_date, check_in_time, last_heartbeat, status, is_late, late_minutes, is_grace_period, grace_count_month, penalty_amount, penalty_reason, location_type, latitude, longitude, location_accuracy, distance_meters, location_denied, ip_address, user_agent, notes)
            VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                check_in_time = IF(check_in_time IS NULL OR check_in_time = '' OR (latitude IS NULL AND location_denied = 0), VALUES(check_in_time), check_in_time),
                last_heartbeat = NOW(),
                status = VALUES(status),
                is_late = VALUES(is_late),
                late_minutes = VALUES(late_minutes),
                is_grace_period = VALUES(is_grace_period),
                grace_count_month = VALUES(grace_count_month),
                penalty_amount = VALUES(penalty_amount),
                penalty_reason = VALUES(penalty_reason),
                location_type = VALUES(location_type),
                latitude = VALUES(latitude),
                longitude = VALUES(longitude),
                location_accuracy = VALUES(location_accuracy),
                distance_meters = VALUES(distance_meters),
                location_denied = VALUES(location_denied),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent)");

        if ($stmt) {
            $stmt->bind_param(
                "isssiiiidssddddisss",
                $user_id,
                $date_today,
                $time_now,
                $status,
                $is_late,
                $late_minutes,
                $is_grace_period,
                $grace_count_month,
                $penalty_amount,
                $penalty_reason,
                $location_type,
                $lat,
                $lng,
                $accuracy,
                $distance_meters,
                $loc_denied_flag,
                $ip_address,
                $user_agent,
                $notes
            );
            $stmt->execute();
            $att_id = $stmt->insert_id ?: ($existing['id'] ?? 0);
            $stmt->close();

            // ── Apply Penalty Deduction to Salary Deductions if penalized ─────────
            if ($penalty_amount > 0) {
                if ($is_late && $loc_penalty > 0) {
                    $ded_type = 'Late Arrival & Location Penalty';
                } elseif ($loc_penalty > 0) {
                    $ded_type = 'Location Verification Penalty';
                } elseif ($is_grace_period && $grace_count_month > 3) {
                    $ded_type = 'Excess Grace Penalty';
                } else {
                    $ded_type = 'Late Arrival Penalty';
                }
                $ded_desc = "Penalty on {$date_today}: " . ($penalty_reason ?: 'Attendance infraction penalty');
                
                // Check if deduction already logged today for this user
                $check_ded = $conn->prepare("SELECT id FROM salary_deductions WHERE user_id = ? AND date_incurred = ? AND deduction_type LIKE '%Penalty%' LIMIT 1");
                if ($check_ded) {
                    $check_ded->bind_param("is", $user_id, $date_today);
                    $check_ded->execute();
                    $ded_res = $check_ded->get_result();
                    if ($ded_res->num_rows === 0) {
                        $ins_ded = $conn->prepare("INSERT INTO salary_deductions (user_id, salary_month, attendance_id, deduction_type, amount, description, date_incurred, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'applied')");
                        if ($ins_ded) {
                            $ins_ded->bind_param("isisdss", $user_id, $month_now, $att_id, $ded_type, $penalty_amount, $ded_desc, $date_today);
                            $ins_ded->execute();
                            $ins_ded->close();
                        }
                    } else {
                        // Update existing deduction amount if changed
                        $existing_ded = $ded_res->fetch_assoc();
                        $ded_id = $existing_ded['id'];
                        $upd_ded = $conn->prepare("UPDATE salary_deductions SET amount = ?, deduction_type = ?, description = ? WHERE id = ?");
                        if ($upd_ded) {
                            $upd_ded->bind_param("dssi", $penalty_amount, $ded_type, $ded_desc, $ded_id);
                            $upd_ded->execute();
                            $upd_ded->close();
                        }
                    }
                    $check_ded->close();
                }

                // Sync employee salary for this month
                syncUserMonthlySalary($conn, $user_id, $month_now);
            }

            return [
                'success'           => true,
                'message'           => 'Attendance recorded successfully',
                'status'            => $status,
                'is_late'           => $is_late,
                'penalty_amount'    => $penalty_amount,
                'penalty_reason'    => $penalty_reason,
                'location_type'     => $location_type,
                'location_denied'   => $loc_denied_flag,
                'distance_meters'   => $distance_meters,
                'grace_count_month' => $grace_count_month,
                'gender'            => $gender,
                'work_mode'         => $work_mode,
                'check_in_time'     => date('h:i A', strtotime($time_now))
            ];
        }
    } catch (Throwable $e) {
        error_log("recordDailyCheckIn fatal error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Check-in error: ' . $e->getMessage()];
    }

    return ['success' => false, 'message' => 'Database error while saving attendance'];
}

/**
 * Records Sign-out / Check-out time
 */
function recordDailyCheckOut($conn, $user_id, $date = null) {
    try {
        ensure_attendance_schema($conn);
        $date = $date ?: date('Y-m-d');
        $time_now = date('H:i:s');

        $stmt = $conn->prepare("UPDATE attendance_records 
            SET check_out_time = ?, last_heartbeat = NOW() 
            WHERE user_id = ? AND attendance_date = ?");
        if ($stmt) {
            $stmt->bind_param("sis", $time_now, $user_id, $date);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            return [
                'success'        => true,
                'check_out_time' => date('h:i A', strtotime($time_now)),
                'updated'        => ($affected > 0)
            ];
        }
    } catch (Throwable $e) {
        error_log("recordDailyCheckOut error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Checkout error: ' . $e->getMessage()];
    }
    return ['success' => false, 'message' => 'Database error recording checkout'];
}

/**
 * Lightweight periodic heartbeat (keeps last_heartbeat updated)
 */
function recordAttendanceHeartbeat($conn, $user_id) {
    if (!$conn || empty($user_id)) return false;
    $date_today = date('Y-m-d');

    try {
        $stmt = $conn->prepare("UPDATE attendance_records 
            SET last_heartbeat = NOW() 
            WHERE user_id = ? AND attendance_date = ?");
        if ($stmt) {
            $stmt->bind_param("is", $user_id, $date_today);
            $stmt->execute();
            $stmt->close();
            return true;
        }
    } catch (Throwable $e) {
        error_log("recordAttendanceHeartbeat error: " . $e->getMessage());
    }
    return false;
}

/**
 * Recalculates and synchronizes monthly salary record for a user
 */
function syncUserMonthlySalary($conn, $user_id, $salary_month) {
    if (!$conn || empty($user_id) || empty($salary_month)) return false;

    try {
        ensure_attendance_schema($conn);

        $user_meta = getUserAttendanceMeta($conn, $user_id);
        $employee_id = $user_meta['employee_id'];
        $name        = $user_meta['name'];
        $department  = $user_meta['department'];
        $job_title   = $user_meta['job_title'];
        $base_salary = $user_meta['base_salary'];
        $allowances  = $user_meta['allowances'];
        $gross_pay   = $base_salary + $allowances;

        // Sum deductions for this month (both applied and pending dispute remain deducted until waived)
        $stmt_ded = $conn->prepare("SELECT IFNULL(SUM(amount), 0) as total_ded FROM salary_deductions WHERE user_id = ? AND salary_month = ? AND status IN ('applied', 'disputed')");
        $total_deductions = 0.00;
        if ($stmt_ded) {
            $stmt_ded->bind_param("is", $user_id, $salary_month);
            $stmt_ded->execute();
            $row = $stmt_ded->get_result()->fetch_assoc();
            $total_deductions = floatval($row['total_ded'] ?? 0);
            $stmt_ded->close();
        }

        // Fetch existing salary row to preserve previous_pending, advance, tax, payment_status, amount_paid
        $tax = 0.00;
        $advance = 0.00;
        $previous_pending = 0.00;
        $payment_status = 'Pending';
        $amount_paid = 0.00;

        $stmt_cur = $conn->prepare("SELECT tax, advance, previous_pending, payment_status, amount_paid FROM salaries WHERE user_id = ? AND salary_month = ? LIMIT 1");
        if ($stmt_cur) {
            $stmt_cur->bind_param("is", $user_id, $salary_month);
            $stmt_cur->execute();
            $cur = $stmt_cur->get_result()->fetch_assoc();
            if ($cur) {
                $tax              = floatval($cur['tax'] ?? 0);
                $advance          = floatval($cur['advance'] ?? 0);
                $previous_pending = floatval($cur['previous_pending'] ?? 0);
                $payment_status   = $cur['payment_status'] ?? 'Pending';
                $amount_paid      = floatval($cur['amount_paid'] ?? 0);
            }
            $stmt_cur->close();
        }

        $net_salary = max(0, $gross_pay - $tax - $advance - $total_deductions + $previous_pending);
        if ($payment_status === 'Paid' && $amount_paid <= 0) {
            $amount_paid = $net_salary;
        }

        $stmt_ins = $conn->prepare("INSERT INTO salaries 
            (user_id, salary_month, employee_id, name, department, job_title, base_salary, allowances, gross_pay, tax, advance, deductions, previous_pending, net_salary, payment_status, amount_paid)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                employee_id = VALUES(employee_id),
                name = VALUES(name),
                department = VALUES(department),
                job_title = VALUES(job_title),
                base_salary = VALUES(base_salary),
                allowances = VALUES(allowances),
                gross_pay = VALUES(gross_pay),
                deductions = VALUES(deductions),
                net_salary = VALUES(net_salary),
                amount_paid = VALUES(amount_paid)");

        if ($stmt_ins) {
            $stmt_ins->bind_param(
                "isssssddddddddsd",
                $user_id,
                $salary_month,
                $employee_id,
                $name,
                $department,
                $job_title,
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
            $stmt_ins->execute();
            $stmt_ins->close();
            return true;
        }
    } catch (Throwable $e) {
        error_log("syncUserMonthlySalary error: " . $e->getMessage());
    }
    return false;
}

/**
 * Grants or updates an attendance override / leave (supports single day or date range)
 * Synchronizes with attendance_records, manages paid vs unpaid status and salary deductions
 */
function grantLeaveOrOverride($conn, $target_user_id, $start_date, $end_date, $attendance_type, $leave_category = 'casual', $reason = '', $is_paid = 1, $leave_duration = 'full_day', $approved_by = null) {
    if (!$conn || empty($target_user_id) || empty($start_date)) {
        return ['success' => false, 'message' => 'Missing required leave parameters'];
    }

    ensure_attendance_schema($conn);
    $end_date = !empty($end_date) ? $end_date : $start_date;
    if (strtotime($end_date) < strtotime($start_date)) {
        $temp = $start_date;
        $start_date = $end_date;
        $end_date = $temp;
    }

    $approved_by = $approved_by ?: ($_SESSION['user_id'] ?? 1);
    $user_meta = getUserAttendanceMeta($conn, $target_user_id);
    $base_salary = floatval($user_meta['base_salary'] ?? 50000);
    $daily_wage = round($base_salary / 30, 2);

    $current_ts = strtotime($start_date);
    $end_ts = strtotime($end_date);
    $applied_count = 0;

    while ($current_ts <= $end_ts) {
        $cur_date = date('Y-m-d', $current_ts);
        $month_cur = date('Y-m', $current_ts);
        $current_ts = strtotime('+1 day', $current_ts);

        // 1. Upsert attendance_overrides
        $ov_stmt = $conn->prepare("INSERT INTO attendance_overrides 
            (user_id, attendance_date, attendance_type, leave_category, is_paid, leave_duration, reason, approved_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                attendance_type = VALUES(attendance_type),
                leave_category = VALUES(leave_category),
                is_paid = VALUES(is_paid),
                leave_duration = VALUES(leave_duration),
                reason = VALUES(reason),
                approved_by = VALUES(approved_by)");
        if ($ov_stmt) {
            $ov_stmt->bind_param("isssissi", $target_user_id, $cur_date, $attendance_type, $leave_category, $is_paid, $leave_duration, $reason, $approved_by);
            $ov_stmt->execute();
            $ov_stmt->close();
        }

        // 2. Synchronize with attendance_records
        if ($attendance_type === 'leave') {
            $status_code = ($leave_duration === 'full_day') ? 'on_leave' : 'half_day';
            $med_count = ($leave_category === 'medical') ? (countMedicalLeavesInMonth($conn, $target_user_id, $month_cur, $cur_date) + 1) : 0;

            $att_stmt = $conn->prepare("INSERT INTO attendance_records 
                (user_id, attendance_date, status, leave_type, medical_leave_month_count, is_late, penalty_amount, penalty_reason, notes, updated_at)
                VALUES (?, ?, ?, ?, ?, 0, 0.00, NULL, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    status = VALUES(status),
                    leave_type = VALUES(leave_type),
                    medical_leave_month_count = VALUES(medical_leave_month_count),
                    is_late = 0,
                    penalty_amount = 0.00,
                    penalty_reason = NULL,
                    notes = VALUES(notes)");
            if ($att_stmt) {
                $notes = "Approved {$leave_category} leave" . (!empty($reason) ? ": {$reason}" : "");
                $att_stmt->bind_param("isssis", $target_user_id, $cur_date, $status_code, $leave_category, $med_count, $notes);
                $att_stmt->execute();
                $att_stmt->close();
            }

            // Remove any existing late penalty deduction on that day
            $del_ded = $conn->prepare("DELETE FROM salary_deductions WHERE user_id = ? AND date_incurred = ? AND deduction_type LIKE '%Penalty%'");
            if ($del_ded) {
                $del_ded->bind_param("is", $target_user_id, $cur_date);
                $del_ded->execute();
                $del_ded->close();
            }

            // Handle Unpaid Leave Deduction
            if (empty($is_paid)) {
                $ded_amount = ($leave_duration === 'full_day') ? $daily_wage : round($daily_wage / 2, 2);
                $ded_type = 'Unpaid Leave';
                $ded_desc = "Unpaid {$leave_category} leave on {$cur_date}" . (!empty($reason) ? " ({$reason})" : "");
                
                $chk_ded = $conn->prepare("SELECT id FROM salary_deductions WHERE user_id = ? AND date_incurred = ? AND deduction_type = 'Unpaid Leave' LIMIT 1");
                if ($chk_ded) {
                    $chk_ded->bind_param("is", $target_user_id, $cur_date);
                    $chk_ded->execute();
                    if ($chk_ded->get_result()->num_rows === 0) {
                        $ins_ded = $conn->prepare("INSERT INTO salary_deductions (user_id, salary_month, deduction_type, amount, description, date_incurred, status) VALUES (?, ?, ?, ?, ?, ?, 'applied')");
                        if ($ins_ded) {
                            $ins_ded->bind_param("issdss", $target_user_id, $month_cur, $ded_type, $ded_amount, $ded_desc, $cur_date);
                            $ins_ded->execute();
                            $ins_ded->close();
                        }
                    }
                    $chk_ded->close();
                }
            } else {
                // If paid, remove any unpaid deduction if it was previously logged
                $del_unpaid = $conn->prepare("DELETE FROM salary_deductions WHERE user_id = ? AND date_incurred = ? AND deduction_type = 'Unpaid Leave'");
                if ($del_unpaid) {
                    $del_unpaid->bind_param("is", $target_user_id, $cur_date);
                    $del_unpaid->execute();
                    $del_unpaid->close();
                }
            }
        }

        $applied_count++;
    }

    // Sync salary
    syncUserMonthlySalary($conn, $target_user_id, date('Y-m', strtotime($start_date)));

    return [
        'success' => true,
        'message' => "Successfully applied {$applied_count} day(s) of " . ucfirst($attendance_type) . ($attendance_type === 'leave' ? " ({$leave_category})" : "") . "!",
        'count'   => $applied_count
    ];
}