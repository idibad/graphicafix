<?php
/**
 * core/task_assignees_helper.php
 * Unified Multi-Member / Grouped Tasks Helper
 *
 * Manages the `task_user` junction table alongside `tasks.assigned_to`
 * (primary assignee / lead) for 100% backward compatibility across Kanban,
 * List, Calendar, Table, Project Tasks, Details, Notifications, and Cron Jobs.
 */

if (!function_exists('ensureTaskUserSchema')) {
    function ensureTaskUserSchema($conn) {
        static $checked = false;
        if ($checked || !$conn) return;
        $checked = true;

        try {
            $res = @$conn->query("SHOW TABLES LIKE 'task_user'");
            if (!$res || $res->num_rows === 0) {
                @$conn->query("CREATE TABLE IF NOT EXISTS task_user (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    task_id BIGINT UNSIGNED NOT NULL,
                    user_id INT NOT NULL,
                    role VARCHAR(50) DEFAULT 'member',
                    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    assigned_by INT DEFAULT NULL,
                    INDEX idx_tu_task (task_id),
                    INDEX idx_tu_user (user_id),
                    UNIQUE KEY uniq_task_user (task_id, user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }
        } catch (Throwable $e) {
            error_log('ensureTaskUserSchema error: ' . $e->getMessage());
        }
    }
}

/**
 * Parses assignee IDs from POST array (`assignees[]`), comma string, or single `assigned_to`.
 * Preserves order (first ID becomes primary lead) and deduplicates positive integers.
 */
if (!function_exists('parseAssigneeIdsInput')) {
    function parseAssigneeIdsInput($assignees_input = null, $fallback_single = 0) {
        $raw = [];
        if (is_array($assignees_input)) {
            $raw = $assignees_input;
        } elseif (is_string($assignees_input) && trim($assignees_input) !== '') {
            $raw = explode(',', $assignees_input);
        } elseif (is_numeric($assignees_input) && intval($assignees_input) > 0) {
            $raw = [intval($assignees_input)];
        }

        $ids = [];
        foreach ($raw as $val) {
            $id = intval($val);
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        $fb = intval($fallback_single);
        if (empty($ids) && $fb > 0) {
            $ids[] = $fb;
        }

        return $ids;
    }
}

/**
 * Syncs multiple members to `task_user` and keeps `tasks.assigned_to` synced to the first (lead) member.
 * Returns metadata including newly added member IDs for targeted notifications.
 */
if (!function_exists('syncTaskAssignees')) {
    function syncTaskAssignees($conn, $task_id, $assignee_ids, $assigned_by = 0) {
        $task_id     = intval($task_id);
        $assigned_by = intval($assigned_by) ?: null;
        $clean_ids   = parseAssigneeIdsInput($assignee_ids, 0);

        $result = [
            'primary_id'  => !empty($clean_ids) ? $clean_ids[0] : null,
            'all_ids'     => $clean_ids,
            'added_ids'   => [],
            'removed_ids' => [],
            'is_group'    => count($clean_ids) > 1
        ];

        if (!$conn || $task_id <= 0) return $result;
        ensureTaskUserSchema($conn);

        // 1. Read previous assignees (from task_user + legacy assigned_to)
        $prev_ids = [];
        try {
            $st_prev = $conn->prepare("SELECT user_id FROM task_user WHERE task_id = ?");
            if ($st_prev) {
                $st_prev->bind_param("i", $task_id);
                $st_prev->execute();
                $res_prev = $st_prev->get_result();
                while ($r = $res_prev->fetch_assoc()) {
                    $uid = intval($r['user_id']);
                    if ($uid > 0 && !in_array($uid, $prev_ids, true)) $prev_ids[] = $uid;
                }
                $st_prev->close();
            }
            $st_leg = $conn->prepare("SELECT assigned_to FROM tasks WHERE id = ? LIMIT 1");
            if ($st_leg) {
                $st_leg->bind_param("i", $task_id);
                $st_leg->execute();
                if ($lr = $st_leg->get_result()->fetch_assoc()) {
                    $luid = intval($lr['assigned_to'] ?? 0);
                    if ($luid > 0 && !in_array($luid, $prev_ids, true)) $prev_ids[] = $luid;
                }
                $st_leg->close();
            }
        } catch (Throwable $e) {}

        $result['added_ids']   = array_values(array_diff($clean_ids, $prev_ids));
        $result['removed_ids'] = array_values(array_diff($prev_ids, $clean_ids));

        // 2. Replace rows in task_user cleanly
        try {
            $del = $conn->prepare("DELETE FROM task_user WHERE task_id = ?");
            if ($del) {
                $del->bind_param("i", $task_id);
                $del->execute();
                $del->close();
            }

            if (!empty($clean_ids)) {
                $ins = $conn->prepare("INSERT IGNORE INTO task_user (task_id, user_id, role, assigned_by) VALUES (?, ?, ?, ?)");
                if ($ins) {
                    foreach ($clean_ids as $idx => $uid) {
                        $role_val = ($idx === 0) ? 'primary_assignee' : 'member';
                        $ins->bind_param("iisi", $task_id, $uid, $role_val, $assigned_by);
                        $ins->execute();
                    }
                    $ins->close();
                }
            }

            // 3. Keep legacy tasks.assigned_to synced with primary lead
            $primary_id = $result['primary_id'];
            $upd = $conn->prepare("UPDATE tasks SET assigned_to = ? WHERE id = ?");
            if ($upd) {
                $upd->bind_param("ii", $primary_id, $task_id);
                $upd->execute();
                $upd->close();
            }
        } catch (Throwable $e) {
            error_log('syncTaskAssignees error: ' . $e->getMessage());
        }

        return $result;
    }
}

/**
 * Fetches ordered assignees for a single task (primary lead first, then members).
 */
if (!function_exists('getTaskAssignees')) {
    function getTaskAssignees($conn, $task_id) {
        $task_id = intval($task_id);
        if (!$conn || $task_id <= 0) return [];
        $map = getAssigneesMapForTasks($conn, [$task_id]);
        return $map[$task_id] ?? [];
    }
}

/**
 * Bulk-fetches assignees for an array of task IDs in a single SQL query (zero N+1).
 * Automatically merges legacy `tasks.assigned_to` if not present in `task_user`.
 */
if (!function_exists('getAssigneesMapForTasks')) {
    function getAssigneesMapForTasks($conn, $task_ids) {
        $map = [];
        if (!$conn || empty($task_ids)) return $map;
        ensureTaskUserSchema($conn);

        $clean_tids = [];
        foreach ($task_ids as $tid) {
            $id = intval($tid);
            if ($id > 0) {
                $clean_tids[$id] = $id;
                $map[$id] = [];
            }
        }
        if (empty($clean_tids)) return $map;

        $in_clause = implode(',', array_values($clean_tids));
        $seen = [];

        // 1. Fetch from task_user (primary_assignee first)
        try {
            $sql = "
                SELECT tu.task_id, tu.user_id, tu.role AS task_role,
                       u.name, u.email, u.role AS user_role, u.department
                FROM task_user tu
                INNER JOIN users u ON tu.user_id = u.user_id
                WHERE tu.task_id IN ($in_clause)
                ORDER BY (tu.role = 'primary_assignee') DESC, tu.id ASC
            ";
            $res = @$conn->query($sql);
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $tid = intval($row['task_id']);
                    $uid = intval($row['user_id']);
                    if (!isset($seen[$tid][$uid])) {
                        $seen[$tid][$uid] = true;
                        $map[$tid][] = [
                            'user_id'    => $uid,
                            'name'       => $row['name'] ?? '',
                            'email'      => $row['email'] ?? '',
                            'task_role'  => $row['task_role'] ?: 'member',
                            'user_role'  => $row['user_role'] ?? '',
                            'department' => $row['department'] ?? ''
                        ];
                    }
                }
            }
        } catch (Throwable $e) {}

        // 2. Merge legacy tasks.assigned_to if not already in task_user
        try {
            $sql2 = "
                SELECT t.id AS task_id, u.user_id, u.name, u.email, u.role AS user_role, u.department
                FROM tasks t
                INNER JOIN users u ON t.assigned_to = u.user_id
                WHERE t.id IN ($in_clause) AND t.assigned_to IS NOT NULL AND t.assigned_to > 0
            ";
            $res2 = @$conn->query($sql2);
            if ($res2) {
                while ($row = $res2->fetch_assoc()) {
                    $tid = intval($row['task_id']);
                    $uid = intval($row['user_id']);
                    if (!isset($seen[$tid][$uid])) {
                        $seen[$tid][$uid] = true;
                        array_unshift($map[$tid], [
                            'user_id'    => $uid,
                            'name'       => $row['name'] ?? '',
                            'email'      => $row['email'] ?? '',
                            'task_role'  => 'primary_assignee',
                            'user_role'  => $row['user_role'] ?? '',
                            'department' => $row['department'] ?? ''
                        ]);
                    }
                }
            }
        } catch (Throwable $e) {}

        return $map;
    }
}

/**
 * Checks whether $user_id is assigned to $task_id (either primary or group member).
 */
if (!function_exists('isUserAssignedToTask')) {
    function isUserAssignedToTask($conn, $task_id, $user_id) {
        $task_id = intval($task_id);
        $user_id = intval($user_id);
        if (!$conn || $task_id <= 0 || $user_id <= 0) return false;
        ensureTaskUserSchema($conn);

        try {
            $st = $conn->prepare("
                SELECT 1 FROM tasks WHERE id = ? AND assigned_to = ?
                UNION
                SELECT 1 FROM task_user WHERE task_id = ? AND user_id = ?
                LIMIT 1
            ");
            if ($st) {
                $st->bind_param("iiii", $task_id, $user_id, $task_id, $user_id);
                $st->execute();
                $found = ($st->get_result()->num_rows > 0);
                $st->close();
                return $found;
            }
        } catch (Throwable $e) {}
        return false;
    }
}

/**
 * SQL condition granting task visibility to primary assignee, creator, or any group member.
 */
if (!function_exists('userTaskAccessSql')) {
    function userTaskAccessSql($user_id, $alias = 't') {
        $uid = intval($user_id);
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        $prefix = $a ? "{$a}." : "tasks.";
        return "({$prefix}assigned_to = {$uid} OR {$prefix}created_by = {$uid} OR EXISTS (SELECT 1 FROM task_user tu_acc WHERE tu_acc.task_id = {$prefix}id AND tu_acc.user_id = {$uid}))";
    }
}

/**
 * SQL condition filtering tasks assigned to a specific user (primary or group member).
 */
if (!function_exists('assigneeFilterSql')) {
    function assigneeFilterSql($assignee_id, $alias = 't') {
        $aid = intval($assignee_id);
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        $prefix = $a ? "{$a}." : "tasks.";
        return "({$prefix}assigned_to = {$aid} OR EXISTS (SELECT 1 FROM task_user tu_flt WHERE tu_flt.task_id = {$prefix}id AND tu_flt.user_id = {$aid}))";
    }
}

/**
 * SQL condition filtering ONLY grouped tasks (tasks with 2 or more assigned members).
 */
if (!function_exists('groupedTasksOnlySql')) {
    function groupedTasksOnlySql($alias = 't') {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        $prefix = $a ? "{$a}." : "tasks.";
        return "((SELECT COUNT(DISTINCT tu_g.user_id) FROM task_user tu_g WHERE tu_g.task_id = {$prefix}id) > 1)";
    }
}

/**
 * Generates deterministic soft pastel avatar colors & initials for a member name.
 */
if (!function_exists('getTaskMemberAvatarMeta')) {
    function getTaskMemberAvatarMeta($name) {
        $name = trim($name ?? '');
        $palettes = [
            ['bg' => '#e0f2fe', 'color' => '#0369a1'],
            ['bg' => '#dcfce7', 'color' => '#15803d'],
            ['bg' => '#fef3c7', 'color' => '#b45309'],
            ['bg' => '#f3e8ff', 'color' => '#7e22ce'],
            ['bg' => '#fce7f3', 'color' => '#be185d'],
            ['bg' => '#e6f4f2', 'color' => '#024442'],
        ];
        $idx = $name !== '' ? (abs(crc32(strtolower($name))) % count($palettes)) : 0;
        $parts = preg_split('/\s+/', $name);
        $initials = '?';
        if (!empty($parts[0])) {
            $initials = strtoupper(substr($parts[0], 0, 1));
            if (count($parts) > 1 && !empty($parts[count($parts) - 1])) {
                $initials .= strtoupper(substr($parts[count($parts) - 1], 0, 1));
            }
        }
        return [
            'bg'       => $palettes[$idx]['bg'],
            'color'    => $palettes[$idx]['color'],
            'initials' => $initials
        ];
    }
}

/**
 * Renders a compact overlapping avatar stack for 1 or multiple task members.
 */
if (!function_exists('renderTaskAvatarStackHtml')) {
    function renderTaskAvatarStackHtml($assignees, $max = 3, $size = 26) {
        $base = 'width:'.$size.'px;height:'.$size.'px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;line-height:1;';
        if (empty($assignees)) {
            return '<div class="task-avatar-circle" style="'.$base.'font-size:10px;background:#f1f5f9;color:#64748b;" title="Unassigned">?</div>';
        }
        $total = count($assignees);
        $all_names = implode(', ', array_map(function($a) { return $a['name'] ?? ''; }, $assignees));
        $shown = array_slice($assignees, 0, $max);
        $extra = $total - count($shown);
        $font  = max(9, intval($size * 0.38));

        $html = '<div class="gfx-avatar-stack" title="' . htmlspecialchars($all_names) . '" style="display:inline-flex;align-items:center;">';
        foreach ($shown as $i => $m) {
            $meta = getTaskMemberAvatarMeta($m['name'] ?? '');
            $ml = ($i > 0) ? 'margin-left:-8px;' : '';
            $role_lbl = ($m['task_role'] ?? '') === 'primary_assignee' && $total > 1 ? ' (Lead)' : '';
            $html .= '<div class="task-avatar-circle" style="'.$base.'font-size:'.$font.'px;background:'.$meta['bg'].';color:'.$meta['color'].';border:2px solid #fff;box-shadow:0 1px 2px rgba(0,0,0,0.08);'.$ml.'" title="'.htmlspecialchars(($m['name'] ?? '') . $role_lbl).'">'.$meta['initials'].'</div>';
        }
        if ($extra > 0) {
            $html .= '<div class="task-avatar-circle" style="'.$base.'font-size:'.$font.'px;background:#024442;color:#fff;border:2px solid #fff;margin-left:-8px;" title="'.htmlspecialchars($all_names).'">+'.$extra.'</div>';
        }
        $html .= '</div>';
        return $html;
    }
}
