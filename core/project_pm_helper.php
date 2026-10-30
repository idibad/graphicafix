<?php
/**
 * core/project_pm_helper.php
 * Project Management Helper Functions: KPIs, Health Computation, and Mailers
 */

require_once __DIR__ . '/project_pm_schema.php';

if (!function_exists('getProjectKPIs')) {
    function getProjectKPIs($conn, $project_id) {
        $project_id = intval($project_id);
        $kpis = [
            'total_tasks' => 0,
            'completed_tasks' => 0,
            'in_progress_tasks' => 0,
            'overdue_tasks' => 0,
            'task_completion_rate' => 0,
            'total_milestones' => 0,
            'completed_milestones' => 0,
            'milestone_progress' => 0,
            'total_deliverables' => 0,
            'approved_deliverables' => 0,
            'pending_review_deliverables' => 0,
            'revision_requested_deliverables' => 0,
            'pending_client_requests' => 0,
            'blocker_client_requests' => 0,
            'open_client_queries' => 0,
            'health_status' => 'Healthy',
            'health_class' => 'text-success'
        ];

        if (!$conn || !($conn instanceof mysqli) || $project_id <= 0) {
            return $kpis;
        }

        // 1. Task metrics
        $stmt_tasks = $conn->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status NOT IN ('Completed', 'Cancelled') AND due_date IS NOT NULL AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue
            FROM tasks
            WHERE project_id = ?
        ");
        if ($stmt_tasks) {
            $stmt_tasks->bind_param("i", $project_id);
            $stmt_tasks->execute();
            $res = $stmt_tasks->get_result()->fetch_assoc();
            $stmt_tasks->close();
            if ($res) {
                $kpis['total_tasks'] = intval($res['total']);
                $kpis['completed_tasks'] = intval($res['completed']);
                $kpis['in_progress_tasks'] = intval($res['in_progress']);
                $kpis['overdue_tasks'] = intval($res['overdue']);
                $kpis['task_completion_rate'] = $kpis['total_tasks'] > 0 ? round(($kpis['completed_tasks'] / $kpis['total_tasks']) * 100) : 0;
            }
        }

        // 2. Milestone metrics
        $stmt_ms = $conn->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM project_milestones
            WHERE project_id = ?
        ");
        if ($stmt_ms) {
            $stmt_ms->bind_param("i", $project_id);
            $stmt_ms->execute();
            $res = $stmt_ms->get_result()->fetch_assoc();
            $stmt_ms->close();
            if ($res) {
                $kpis['total_milestones'] = intval($res['total']);
                $kpis['completed_milestones'] = intval($res['completed']);
                $kpis['milestone_progress'] = $kpis['total_milestones'] > 0 ? round(($kpis['completed_milestones'] / $kpis['total_milestones']) * 100) : 0;
            }
        }

        // 3. Deliverables metrics
        $stmt_del = $conn->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN signoff_status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN signoff_status = 'pending_review' THEN 1 ELSE 0 END) as pending_review,
                SUM(CASE WHEN signoff_status = 'revision_requested' THEN 1 ELSE 0 END) as revision_requested
            FROM project_deliverables
            WHERE project_id = ?
        ");
        if ($stmt_del) {
            $stmt_del->bind_param("i", $project_id);
            $stmt_del->execute();
            $res = $stmt_del->get_result()->fetch_assoc();
            $stmt_del->close();
            if ($res) {
                $kpis['total_deliverables'] = intval($res['total']);
                $kpis['approved_deliverables'] = intval($res['approved']);
                $kpis['pending_review_deliverables'] = intval($res['pending_review']);
                $kpis['revision_requested_deliverables'] = intval($res['revision_requested']);
            }
        }

        // 4. Pending Client Requests (Assets/Info)
        $stmt_req = $conn->prepare("
            SELECT
                COUNT(*) as pending_total,
                SUM(CASE WHEN priority = 'blocker' THEN 1 ELSE 0 END) as blockers
            FROM project_client_requests
            WHERE project_id = ? AND status IN ('requested', 'rejected')
        ");
        if ($stmt_req) {
            $stmt_req->bind_param("i", $project_id);
            $stmt_req->execute();
            $res = $stmt_req->get_result()->fetch_assoc();
            $stmt_req->close();
            if ($res) {
                $kpis['pending_client_requests'] = intval($res['pending_total']);
                $kpis['blocker_client_requests'] = intval($res['blockers']);
            }
        }

        // 5. Open Client Queries
        $stmt_q = $conn->prepare("
            SELECT COUNT(*) as open_queries
            FROM project_client_queries
            WHERE project_id = ? AND status IN ('open', 'in_progress')
        ");
        if ($stmt_q) {
            $stmt_q->bind_param("i", $project_id);
            $stmt_q->execute();
            $res = $stmt_q->get_result()->fetch_assoc();
            $stmt_q->close();
            if ($res) {
                $kpis['open_client_queries'] = intval($res['open_queries']);
            }
        }

        // 6. Overall Project Health computation (clean semantic status)
        if ($kpis['blocker_client_requests'] > 0 || $kpis['overdue_tasks'] >= 3) {
            $kpis['health_status'] = 'At Risk';
            $kpis['health_class'] = 'text-danger';
        } elseif ($kpis['overdue_tasks'] > 0 || $kpis['pending_client_requests'] > 0 || $kpis['open_client_queries'] > 0 || $kpis['revision_requested_deliverables'] > 0) {
            $kpis['health_status'] = 'Needs Attention';
            $kpis['health_class'] = 'text-warning';
        } else {
            $kpis['health_status'] = 'Healthy';
            $kpis['health_class'] = 'text-success';
        }

        return $kpis;
    }
}

if (!function_exists('notifyProjectClient')) {
    function notifyProjectClient($conn, $project_id, $subject, $heading, $bodyHtml) {
        if (!$conn || !($conn instanceof mysqli) || $project_id <= 0) return false;

        $stmt = $conn->prepare("
            SELECT p.project_name, p.client_id, c.client_name, c.email as client_email
            FROM projects p
            LEFT JOIN clients c ON p.client_id = c.id
            WHERE p.id = ?
        ");
        if (!$stmt) return false;
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $proj = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$proj) return false;

        $recipients = [];
        if (!empty($proj['client_email']) && filter_var($proj['client_email'], FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $proj['client_email'];
        }

        if (!empty($proj['client_id'])) {
            $u_stmt = $conn->prepare("SELECT email FROM users WHERE client_id = ? AND role IN ('client', 'client_sub') AND (status = 'active' OR status IS NULL)");
            if ($u_stmt) {
                $u_stmt->bind_param("i", $proj['client_id']);
                $u_stmt->execute();
                $u_res = $u_stmt->get_result();
                while ($u = $u_res->fetch_assoc()) {
                    if (!empty($u['email']) && filter_var($u['email'], FILTER_VALIDATE_EMAIL) && !in_array($u['email'], $recipients)) {
                        $recipients[] = $u['email'];
                    }
                }
                $u_stmt->close();
            }
        }

        if (empty($recipients)) return false;

        require_once __DIR__ . '/task_email_helper.php';
        foreach ($recipients as $email) {
            @sendClientEmail($email, $subject, $heading, $bodyHtml, $project_id);
        }
        return true;
    }
}
