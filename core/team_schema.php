<?php
/**
 * Auto-migration & Schema synchronization helper for `team` table
 * Automatically executes on any environment (local, staging, production)
 * if any required column is missing, preventing "Unknown column" errors.
 */

function ensureTeamSchema($conn) {
    if (!$conn) return;

    // Check existing columns directly
    $existing = [];
    try {
        $cols_res = $conn->query("SHOW COLUMNS FROM `team`");
        if ($cols_res) {
            while ($r = $cols_res->fetch_assoc()) {
                $existing[] = strtolower($r['Field']);
            }
        }
    } catch (Throwable $e) {
        // Table probably does not exist yet, try to create it
    }

    if (empty($existing)) {
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `team` (
                `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `employee_id` varchar(50) DEFAULT NULL,
                `member_name` varchar(255) NOT NULL,
                `photo` varchar(255) DEFAULT NULL,
                `role` varchar(255) DEFAULT NULL,
                `department` varchar(100) DEFAULT NULL,
                `bio` text DEFAULT NULL,
                `gender` varchar(20) DEFAULT 'Male',
                `status` varchar(20) DEFAULT 'active',
                `show_on_about` tinyint(1) DEFAULT 1,
                `display_order` int(11) DEFAULT 0,
                `verification_code` varchar(64) DEFAULT NULL,
                `email` varchar(255) DEFAULT NULL,
                `phone` varchar(50) DEFAULT NULL,
                `linkedin` varchar(255) DEFAULT NULL,
                `github` varchar(255) DEFAULT NULL,
                `twitter` varchar(255) DEFAULT NULL,
                `blood_group` varchar(10) DEFAULT NULL,
                `emergency_contact` varchar(50) DEFAULT NULL,
                `address` varchar(255) DEFAULT NULL,
                `joined_at` date DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                UNIQUE KEY `idx_emp_id` (`employee_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (Throwable $e) {}

        // Re-read columns
        try {
            $cols_res = $conn->query("SHOW COLUMNS FROM `team`");
            if ($cols_res) {
                while ($r = $cols_res->fetch_assoc()) {
                    $existing[] = strtolower($r['Field']);
                }
            }
        } catch (Throwable $e) {}
    }

    $needed = [
        'employee_id'        => "VARCHAR(50) DEFAULT NULL",
        'photo'              => "VARCHAR(255) DEFAULT NULL",
        'department'         => "VARCHAR(100) DEFAULT NULL",
        'bio'                => "TEXT DEFAULT NULL",
        'gender'             => "VARCHAR(20) DEFAULT 'Male'",
        'status'             => "VARCHAR(20) DEFAULT 'active'",
        'show_on_about'      => "TINYINT(1) DEFAULT 1",
        'display_order'      => "INT DEFAULT 0",
        'verification_code'  => "VARCHAR(64) DEFAULT NULL",
        'linkedin'           => "VARCHAR(255) DEFAULT NULL",
        'github'             => "VARCHAR(255) DEFAULT NULL",
        'twitter'            => "VARCHAR(255) DEFAULT NULL",
        'blood_group'        => "VARCHAR(10) DEFAULT NULL",
        'emergency_contact'  => "VARCHAR(50) DEFAULT NULL",
        'address'            => "VARCHAR(255) DEFAULT NULL"
    ];

    $has_missing = false;
    foreach ($needed as $col => $def) {
        if (!in_array(strtolower($col), $existing)) {
            $has_missing = true;
            try {
                $conn->query("ALTER TABLE `team` ADD COLUMN `{$col}` {$def}");
                $existing[] = strtolower($col);
            } catch (Throwable $e) {
                // Column might already exist or host restricted ALTER
            }
        }
    }

    // Ensure id is AUTO_INCREMENT
    try {
        $id_info = $conn->query("SHOW COLUMNS FROM `team` WHERE Field = 'id'")->fetch_assoc();
        if ($id_info && strpos(strtolower($id_info['Extra'] ?? ''), 'auto_increment') === false) {
            $conn->query("ALTER TABLE `team` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT");
        }
    } catch (Throwable $e) {}

    // Backfill missing employee_ids and verification_codes if columns exist
    if (in_array('employee_id', $existing)) {
        try {
            $rows = $conn->query("SELECT id, employee_id, member_name FROM `team`");
            if ($rows) {
                while ($row = $rows->fetch_assoc()) {
                    $rid = intval($row['id']);
                    $updates = [];
                    if (empty($row['employee_id'])) {
                        $new_id = sprintf("EMP%04d", $rid);
                        $updates[] = "`employee_id` = '{$new_id}'";
                        if (in_array('verification_code', $existing)) {
                            $vhash = hash('sha256', 'GF_' . $new_id . '_' . $rid . '_salt');
                            $updates[] = "`verification_code` = '{$vhash}'";
                        }
                    }
                    if (in_array('status', $existing)) {
                        $updates[] = "`status` = IF(status IS NULL OR status = '', 'active', status)";
                    }
                    if (in_array('show_on_about', $existing)) {
                        $updates[] = "`show_on_about` = IF(show_on_about IS NULL, 1, show_on_about)";
                    }
                    if (!empty($updates)) {
                        $conn->query("UPDATE `team` SET " . implode(", ", $updates) . " WHERE `id` = {$rid}");
                    }
                }
            }
        } catch (Throwable $e) {}
    }

    // Ensure Team Member of the Month table exists
    ensureMemberOfMonthSchema($conn);
}

/**
 * Ensures the `team_member_of_month` table exists for featuring a Graphicafix Family spotlight.
 */
if (!function_exists('ensureMemberOfMonthSchema')) {
    function ensureMemberOfMonthSchema($conn) {
        static $checked = false;
        if ($checked || !$conn) return;
        $checked = true;

        try {
            $conn->query("CREATE TABLE IF NOT EXISTS `team_member_of_month` (
                `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `team_member_id` INT(11) DEFAULT NULL,
                `member_name` VARCHAR(255) NOT NULL,
                `member_role` VARCHAR(255) DEFAULT NULL,
                `member_department` VARCHAR(100) DEFAULT NULL,
                `member_photo` VARCHAR(255) DEFAULT NULL,
                `member_email` VARCHAR(255) DEFAULT NULL,
                `month_year` VARCHAR(7) NOT NULL COMMENT 'YYYY-MM format',
                `badge_title` VARCHAR(150) DEFAULT 'Team Member of the Month',
                `appreciation_note` TEXT DEFAULT NULL,
                `selected_by` INT(11) DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_motm_active` (`is_active`),
                KEY `idx_motm_month` (`month_year`),
                KEY `idx_motm_member` (`team_member_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (Throwable $e) {
            error_log('ensureMemberOfMonthSchema error: ' . $e->getMessage());
        }
    }
}

/**
 * Returns the currently featured Team Member of the Month (joined with live team profile data), or null.
 */
if (!function_exists('getActiveMemberOfMonth')) {
    function getActiveMemberOfMonth($conn) {
        if (!$conn) return null;
        ensureMemberOfMonthSchema($conn);

        try {
            $sql = "
                SELECT m.*,
                       COALESCE(NULLIF(t.member_name, ''), m.member_name) AS display_name,
                       COALESCE(NULLIF(t.role, ''), m.member_role) AS display_role,
                       COALESCE(NULLIF(t.department, ''), m.member_department) AS display_department,
                       COALESCE(NULLIF(t.photo, ''), m.member_photo) AS display_photo,
                       COALESCE(NULLIF(t.email, ''), m.member_email) AS display_email,
                       u.name AS selected_by_name
                FROM `team_member_of_month` m
                LEFT JOIN `team` t ON t.id = m.team_member_id
                LEFT JOIN `users` u ON u.user_id = m.selected_by
                WHERE m.is_active = 1
                ORDER BY m.updated_at DESC, m.id DESC
                LIMIT 1
            ";
            $res = @$conn->query($sql);
            if ($res && $row = $res->fetch_assoc()) {
                $my = trim($row['month_year'] ?? '');
                $row['month_display'] = preg_match('/^\d{4}-\d{2}$/', $my)
                    ? date('F Y', strtotime($my . '-01'))
                    : date('F Y');
                return $row;
            }
        } catch (Throwable $e) {
            error_log('getActiveMemberOfMonth error: ' . $e->getMessage());
        }
        return null;
    }
}

/**
 * Sets (or updates) the active Team Member of the Month from the `team` directory.
 */
if (!function_exists('setMemberOfMonth')) {
    function setMemberOfMonth($conn, $team_member_id, $month_year = '', $appreciation_note = '', $badge_title = 'Team Member of the Month', $selected_by = 0) {
        if (!$conn) return ['success' => false, 'message' => 'Database connection unavailable.'];
        ensureMemberOfMonthSchema($conn);

        $team_member_id = intval($team_member_id);
        if ($team_member_id <= 0) {
            return ['success' => false, 'message' => 'Please select a team member from the Graphicafix family.'];
        }

        $month_year = trim($month_year);
        if (!preg_match('/^\d{4}-\d{2}$/', $month_year)) {
            $month_year = date('Y-m');
        }

        $badge_title = trim($badge_title);
        if ($badge_title === '') {
            $badge_title = 'Team Member of the Month';
        }
        $appreciation_note = trim($appreciation_note);
        $selected_by_val = intval($selected_by) ?: null;

        try {
            $st = $conn->prepare("SELECT id, member_name, role, department, photo, email FROM `team` WHERE id = ? LIMIT 1");
            if (!$st) return ['success' => false, 'message' => 'Could not look up team member.'];
            $st->bind_param("i", $team_member_id);
            $st->execute();
            $member = $st->get_result()->fetch_assoc();
            $st->close();

            if (!$member) {
                return ['success' => false, 'message' => 'Selected team member was not found in the directory.'];
            }

            // Deactivate any previously active spotlight
            $conn->query("UPDATE `team_member_of_month` SET is_active = 0 WHERE is_active = 1");

            // Check if a record already exists for this month_year; update it if so, else insert fresh
            $chk = $conn->prepare("SELECT id FROM `team_member_of_month` WHERE month_year = ? ORDER BY id DESC LIMIT 1");
            $existing_id = 0;
            if ($chk) {
                $chk->bind_param("s", $month_year);
                $chk->execute();
                if ($r = $chk->get_result()->fetch_assoc()) {
                    $existing_id = intval($r['id']);
                }
                $chk->close();
            }

            $m_name  = $member['member_name'] ?? '';
            $m_role  = $member['role'] ?? '';
            $m_dept  = $member['department'] ?? '';
            $m_photo = $member['photo'] ?? null;
            $m_email = $member['email'] ?? null;

            if ($existing_id > 0) {
                $upd = $conn->prepare("
                    UPDATE `team_member_of_month`
                    SET team_member_id = ?, member_name = ?, member_role = ?, member_department = ?,
                        member_photo = ?, member_email = ?, badge_title = ?, appreciation_note = ?,
                        selected_by = ?, is_active = 1, updated_at = NOW()
                    WHERE id = ?
                ");
                $upd->bind_param("isssssssii", $team_member_id, $m_name, $m_role, $m_dept, $m_photo, $m_email, $badge_title, $appreciation_note, $selected_by_val, $existing_id);
                $ok = $upd->execute();
                $upd->close();
            } else {
                $ins = $conn->prepare("
                    INSERT INTO `team_member_of_month`
                        (team_member_id, member_name, member_role, member_department, member_photo, member_email, month_year, badge_title, appreciation_note, selected_by, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $ins->bind_param("issssssssi", $team_member_id, $m_name, $m_role, $m_dept, $m_photo, $m_email, $month_year, $badge_title, $appreciation_note, $selected_by_val);
                $ok = $ins->execute();
                $ins->close();
            }

            if ($ok) {
                $month_lbl = date('F Y', strtotime($month_year . '-01'));
                return ['success' => true, 'message' => "{$m_name} is now featured as Team Member of the Month ({$month_lbl}) on the dashboard!"];
            }
            return ['success' => false, 'message' => 'Could not save Team Member of the Month selection.'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Error saving spotlight: ' . $e->getMessage()];
        }
    }
}

/**
 * Hides the active Team Member of the Month card from the dashboard.
 */
if (!function_exists('clearMemberOfMonth')) {
    function clearMemberOfMonth($conn) {
        if (!$conn) return false;
        ensureMemberOfMonthSchema($conn);
        try {
            return (bool)$conn->query("UPDATE `team_member_of_month` SET is_active = 0 WHERE is_active = 1");
        } catch (Throwable $e) {
            return false;
        }
    }
}

/**
 * Returns recent Team Member of the Month history entries.
 */
if (!function_exists('getMemberOfMonthHistory')) {
    function getMemberOfMonthHistory($conn, $limit = 6) {
        $rows = [];
        if (!$conn) return $rows;
        ensureMemberOfMonthSchema($conn);
        $limit = max(1, intval($limit));
        try {
            $res = @$conn->query("
                SELECT m.*,
                       COALESCE(NULLIF(t.member_name, ''), m.member_name) AS display_name,
                       COALESCE(NULLIF(t.role, ''), m.member_role) AS display_role,
                       COALESCE(NULLIF(t.photo, ''), m.member_photo) AS display_photo
                FROM `team_member_of_month` m
                LEFT JOIN `team` t ON t.id = m.team_member_id
                ORDER BY m.month_year DESC, m.id DESC
                LIMIT {$limit}
            ");
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $my = trim($r['month_year'] ?? '');
                    $r['month_display'] = preg_match('/^\d{4}-\d{2}$/', $my)
                        ? date('F Y', strtotime($my . '-01'))
                        : $my;
                    $rows[] = $r;
                }
            }
        } catch (Throwable $e) {}
        return $rows;
    }
}
