<?php
error_reporting(0);
ini_set('display_errors', '0');

include 'dashboard_header.php';

// Auth check
if (!in_array($role, ['super_admin', 'superadmin'])) {
    header("Location: index.php?error=unauthorized");
    exit;
}

// ── Auto-provision table if needed ───────────────────────────────────────────
if ($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS client_questionnaires (
        id INT AUTO_INCREMENT PRIMARY KEY,
        submission_id VARCHAR(50) NOT NULL UNIQUE,
        client_name VARCHAR(150) DEFAULT NULL,
        designation VARCHAR(150) DEFAULT NULL,
        organization VARCHAR(200) DEFAULT NULL,
        email VARCHAR(150) DEFAULT NULL,
        phone VARCHAR(100) DEFAULT NULL,
        confirmation_date DATE DEFAULT NULL,
        primary_objective VARCHAR(255) DEFAULT NULL,
        primary_goals TEXT DEFAULT NULL,
        target_audience TEXT DEFAULT NULL,
        budget_range VARCHAR(100) DEFAULT NULL,
        preferred_launch_date VARCHAR(100) DEFAULT NULL,
        decision_maker VARCHAR(150) DEFAULT NULL,
        status ENUM('new', 'reviewed', 'in_progress', 'proposal_sent', 'archived') DEFAULT 'new',
        admin_notes TEXT DEFAULT NULL,
        form_data LONGTEXT NOT NULL,
        ip_address VARCHAR(50) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Soft update in case table was previously created with NOT NULL
    $conn->query("ALTER TABLE client_questionnaires MODIFY client_name VARCHAR(150) NULL DEFAULT NULL, MODIFY email VARCHAR(150) NULL DEFAULT NULL, MODIFY phone VARCHAR(100) NULL DEFAULT NULL");
}

// ── Handle Actions ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Delete
    if (isset($_POST['delete_submission'])) {
        $sub_id = intval($_POST['sub_id'] ?? 0);
        if ($sub_id) {
            $stmt = $conn->prepare("DELETE FROM client_questionnaires WHERE id = ?");
            $stmt->bind_param("i", $sub_id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: client_questionnaires.php?deleted=1');
        exit;
    }

    // 2. Status Update
    if (isset($_POST['update_status'])) {
        $sub_id = intval($_POST['sub_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? 'new';
        $allowed = ['new', 'reviewed', 'in_progress', 'proposal_sent', 'archived'];
        if ($sub_id && in_array($new_status, $allowed)) {
            $stmt = $conn->prepare("UPDATE client_questionnaires SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $sub_id);
            $stmt->execute();
            $stmt->close();
        }
        if (!empty($_POST['ajax_update'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        header('Location: client_questionnaires.php?tab=' . urlencode($_POST['current_tab'] ?? 'all') . '&status_updated=1');
        exit;
    }

    // 3. Admin Notes
    if (isset($_POST['save_notes'])) {
        $sub_id = intval($_POST['sub_id'] ?? 0);
        $notes  = trim($_POST['admin_notes'] ?? '');
        if ($sub_id) {
            $stmt = $conn->prepare("UPDATE client_questionnaires SET admin_notes = ? WHERE id = ?");
            $stmt->bind_param("si", $notes, $sub_id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: client_questionnaires.php?tab=' . urlencode($_POST['current_tab'] ?? 'all') . '&notes_saved=1');
        exit;
    }
}

// ── Active Tab & Search ───────────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'all';
$allowed_tabs = ['all', 'new', 'reviewed', 'in_progress', 'proposal_sent', 'archived'];
if (!in_array($tab, $allowed_tabs)) $tab = 'all';

$search = trim($_GET['search'] ?? '');

// Tab counts
function getQuestionnaireCount($conn, $status = 'all') {
    if ($status === 'all') {
        $res = $conn->query("SELECT COUNT(*) AS c FROM client_questionnaires");
    } else {
        $res = $conn->query("SELECT COUNT(*) AS c FROM client_questionnaires WHERE status = '$status'");
    }
    return $res ? (int)$res->fetch_assoc()['c'] : 0;
}

$count_all           = getQuestionnaireCount($conn, 'all');
$count_new           = getQuestionnaireCount($conn, 'new');
$count_reviewed      = getQuestionnaireCount($conn, 'reviewed');
$count_in_progress   = getQuestionnaireCount($conn, 'in_progress');
$count_proposal_sent = getQuestionnaireCount($conn, 'proposal_sent');
$count_archived      = getQuestionnaireCount($conn, 'archived');

// ── Query Builder ─────────────────────────────────────────────────────────────
$where_clauses = [];
$params = [];
$types = '';

if ($tab !== 'all') {
    $where_clauses[] = "status = ?";
    $params[] = $tab;
    $types .= 's';
}

if (!empty($search)) {
    $where_clauses[] = "(submission_id LIKE ? OR client_name LIKE ? OR organization LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sssss';
}

$sql = "SELECT * FROM client_questionnaires";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $submissions = $stmt->get_result();
} else {
    $submissions = $conn->query($sql);
}

$total_rows = $submissions ? $submissions->num_rows : 0;

function cleanPhoneForWa($p) {
    return preg_replace('/[^0-9]/', '', $p);
}
?>

<style>
/* ── Admin Discovery Questionnaire Styles ── */
:root {
    --primary: #024442;
    --primary-light: #036b68;
    --accent: #b8f35a;
    --accent-dark: #276749;
}

.client-admin-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
}

.client-admin-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.client-admin-title h2 {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary);
    margin: 0;
}

.client-admin-title .badge {
    background: rgba(2, 68, 66, 0.1);
    color: var(--primary);
    font-size: 0.82rem;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 50px;
}

/* ── Stats Row ── */
.stats-grid-client {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 26px;
}

.stat-box-client {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 20px 22px;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.02);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.25s ease;
}

.stat-box-client:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(2, 68, 66, 0.06);
    border-color: #cbd5e1;
}

.stat-icon-client {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}

.stat-val-client {
    font-size: 1.6rem;
    font-weight: 800;
    line-height: 1.1;
    color: #1e293b;
}

.stat-lbl-client {
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 2px;
}

/* ── Main Submissions Card ── */
.panel-card-client {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    overflow: hidden;
}

.panel-filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    padding: 16px 24px;
    border-bottom: 1px solid #f1f5f9;
}

.tabs-row-client {
    display: flex;
    align-items: center;
    gap: 6px;
    overflow-x: auto;
    padding-bottom: 4px;
}

.tab-link-client {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    color: #64748b;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.tab-link-client:hover {
    background: #f1f5f9;
    color: var(--primary);
}

.tab-link-client.active {
    background: var(--primary);
    color: #ffffff;
    border-color: var(--primary);
}

.tab-link-client .badge-count {
    background: rgba(0, 0, 0, 0.08);
    color: inherit;
    padding: 2px 7px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 700;
}

.tab-link-client.active .badge-count {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

/* Search input */
.search-input-wrap {
    position: relative;
    min-width: 260px;
}

.search-input-wrap i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 0.85rem;
}

.search-input-wrap input {
    padding-left: 38px;
    border-radius: 50px;
    font-size: 0.88rem;
    border: 1.5px solid #e2e8f0;
    background: #f8fafc;
    height: 38px;
    width: 100%;
}

.search-input-wrap input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.1);
    background: #fff;
    outline: none;
}

/* Table */
.table-client {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin: 0;
}

.table-client th {
    background: #f8fafc;
    color: #475569;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 14px 20px;
    border-bottom: 1.5px solid #e2e8f0;
    white-space: nowrap;
}

.table-client td {
    padding: 16px 20px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.9rem;
    color: #1e293b;
}

.table-client tr:hover td {
    background: #fafbfc;
}

.ref-pill {
    display: inline-block;
    font-family: monospace;
    font-weight: 700;
    font-size: 0.82rem;
    color: var(--primary);
    background: rgba(2, 68, 66, 0.08);
    padding: 4px 10px;
    border-radius: 8px;
    border: 1px solid rgba(2, 68, 66, 0.15);
}

.action-btn-group {
    display: flex;
    align-items: center;
    gap: 6px;
}

.action-btn {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
    cursor: pointer;
    text-decoration: none;
}

.action-btn:hover {
    background: var(--primary);
    color: #ffffff;
    border-color: var(--primary);
}

.action-btn-del:hover {
    background: #ef4444 !important;
    border-color: #ef4444 !important;
    color: #ffffff !important;
}

/* ── Modal Custom ── */
.modal-overlay-client {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(5px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
    box-sizing: border-box;
}

.modal-overlay-client.active {
    display: flex;
}

.modal-content-client {
    background: #ffffff;
    border-radius: 20px;
    max-width: 960px;
    width: 100%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
    overflow-y: auto !important;
    box-sizing: border-box;
    animation: modalPop 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.15);
}

@keyframes modalPop {
    from { opacity: 0; transform: scale(0.94); }
    to { opacity: 1; transform: scale(1); }
}

.modal-header-client {
    background: var(--primary);
    color: #ffffff;
    padding: 20px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-header-client h3 {
    font-size: 1.25rem;
    font-weight: 800;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-body-client {
    padding: 28px;
    overflow-y: auto;
}

.modal-footer-client {
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    padding: 16px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}

.modal-close-client {
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.8);
    font-size: 1.4rem;
    cursor: pointer;
    transition: all 0.2s;
}

.modal-close-client:hover {
    color: #ffffff;
    transform: scale(1.1);
}

/* Detail Section Card inside modal */
.detail-sec-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px 22px;
    margin-bottom: 20px;
}

.detail-sec-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.detail-q-row {
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px dashed #e2e8f0;
}

.detail-q-row:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.detail-q-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 4px;
}

.detail-q-val {
    font-size: 0.94rem;
    color: #1e293b;
    line-height: 1.5;
}

.chip-tag {
    display: inline-block;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: var(--primary);
    padding: 2px 10px;
    border-radius: 50px;
    font-size: 0.78rem;
    font-weight: 600;
    margin: 2px 4px 2px 0;
}

/* Print Styles */
@media print {
    body * { visibility: hidden; }
    #printableArea, #printableArea * { visibility: visible; }
    #printableArea { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>

<div class="container-fluid py-4 px-4">

    <!-- Header & Action Bar -->
    <div class="client-admin-header">
        <div class="client-admin-title">
            <div style="width:44px; height:44px; border-radius:12px; background:var(--primary); color:var(--accent); display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div>
                <h2>Client Discovery Questionnaires</h2>
                <div style="font-size:0.88rem; color:#64748b; margin-top:2px;">
                    Digital Platform & Website Redesign Client Submissions
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="<?= BASE_URL ?>questionnaire.php" target="_blank" class="btn btn-outline-primary" style="border-radius:50px; font-size:0.88rem; font-weight:600; padding:8px 18px;">
                <i class="fas fa-external-link-alt me-1"></i> Open Public Form
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid-client">
        <div class="stat-box-client">
            <div class="stat-icon-client" style="background:#e0f2fe; color:#0284c7;">
                <i class="fas fa-folder-open"></i>
            </div>
            <div>
                <div class="stat-val-client"><?= $count_all ?></div>
                <div class="stat-lbl-client">Total Submissions</div>
            </div>
        </div>
        <div class="stat-box-client">
            <div class="stat-icon-client" style="background:#fef3c7; color:#d97706;">
                <i class="fas fa-star"></i>
            </div>
            <div>
                <div class="stat-val-client"><?= $count_new ?></div>
                <div class="stat-lbl-client">New / Unreviewed</div>
            </div>
        </div>
        <div class="stat-box-client">
            <div class="stat-icon-client" style="background:#ede9fe; color:#7c3aed;">
                <i class="fas fa-search-plus"></i>
            </div>
            <div>
                <div class="stat-val-client"><?= $count_reviewed ?></div>
                <div class="stat-lbl-client">Reviewed</div>
            </div>
        </div>
        <div class="stat-box-client">
            <div class="stat-icon-client" style="background:#dcfce7; color:#16a34a;">
                <i class="fas fa-file-signature"></i>
            </div>
            <div>
                <div class="stat-val-client"><?= $count_proposal_sent ?></div>
                <div class="stat-lbl-client">Proposal Sent</div>
            </div>
        </div>
        <div class="stat-box-client">
            <div class="stat-icon-client" style="background:#f1f5f9; color:#64748b;">
                <i class="fas fa-archive"></i>
            </div>
            <div>
                <div class="stat-val-client"><?= $count_archived ?></div>
                <div class="stat-lbl-client">Archived</div>
            </div>
        </div>
    </div>

    <!-- Main Table Panel -->
    <div class="panel-card-client">

        <!-- Filters and Search -->
        <div class="panel-filter-bar">
            <!-- Filter Tabs -->
            <div class="tabs-row-client">
                <?php
                $tab_defs = [
                    'all'           => ['label' => 'All Submissions', 'count' => $count_all],
                    'new'           => ['label' => 'New',             'count' => $count_new],
                    'reviewed'      => ['label' => 'Reviewed',        'count' => $count_reviewed],
                    'in_progress'   => ['label' => 'In Progress',     'count' => $count_in_progress],
                    'proposal_sent' => ['label' => 'Proposal Sent',   'count' => $count_proposal_sent],
                    'archived'      => ['label' => 'Archived',        'count' => $count_archived],
                ];
                foreach ($tab_defs as $t_key => $t_info):
                    $is_active = ($tab === $t_key);
                ?>
                <a href="client_questionnaires.php?tab=<?= $t_key ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="tab-link-client <?= $is_active ? 'active' : '' ?>">
                    <span><?= $t_info['label'] ?></span>
                    <span class="badge-count"><?= $t_info['count'] ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Search Bar -->
            <form method="GET" action="client_questionnaires.php" class="d-flex align-items-center gap-2">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <div class="search-input-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, email, ref ID...">
                </div>
                <?php if (!empty($search)): ?>
                <a href="client_questionnaires.php?tab=<?= htmlspecialchars($tab) ?>" class="btn btn-sm btn-outline-secondary" style="border-radius:50px;" title="Clear search">
                    <i class="fas fa-times"></i>
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Submissions Table -->
        <div class="table-responsive">
            <table class="table-client">
                <thead>
                    <tr>
                        <th>Ref Code</th>
                        <th>Client / Organization</th>
                        <th>Contact Channels</th>
                        <th>Scope & Budget</th>
                        <th>Target Launch</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($total_rows > 0): ?>
                        <?php while ($r = $submissions->fetch_assoc()):
                            $cleanPhone = cleanPhoneForWa($r['phone']);
                            $status_label = ucwords(str_replace('_', ' ', $r['status']));
                        ?>
                        <tr>
                            <!-- Ref ID -->
                            <td>
                                <span class="ref-pill"><?= htmlspecialchars($r['submission_id']) ?></span>
                            </td>

                            <!-- Client Info -->
                            <td>
                                <strong style="color:var(--primary); font-size:0.95rem;">
                                    <?= htmlspecialchars($r['client_name'] ?: ($r['organization'] ?: 'Discovery Submission')) ?>
                                </strong>
                                <?php if (!empty($r['organization']) && $r['organization'] !== $r['client_name']): ?>
                                <div style="font-size:0.82rem; color:#64748b;">
                                    <?= htmlspecialchars($r['designation'] ? $r['designation'] . ' • ' : '') ?><strong><?= htmlspecialchars($r['organization']) ?></strong>
                                </div>
                                <?php elseif (!empty($r['designation'])): ?>
                                <div style="font-size:0.82rem; color:#64748b;"><?= htmlspecialchars($r['designation']) ?></div>
                                <?php endif; ?>
                            </td>

                            <!-- Contact Channels -->
                            <td>
                                <div class="d-flex flex-column gap-1" style="font-size:0.86rem;">
                                    <?php if (!empty($r['email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($r['email']) ?>" style="color:#0284c7; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                                        <i class="fas fa-envelope"></i> <?= htmlspecialchars($r['email']) ?>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (!empty($r['phone'])): ?>
                                    <a href="https://wa.me/<?= $cleanPhone ?>" target="_blank" style="color:#16a34a; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                                        <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($r['phone']) ?>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (empty($r['email']) && empty($r['phone'])): ?>
                                    <span class="text-muted" style="font-size:0.82rem; font-style:italic;">Not provided</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Scope & Budget -->
                            <td>
                                <span style="font-weight:600; color:#334155;">
                                    <?= htmlspecialchars($r['budget_range'] ?: 'Not specified') ?>
                                </span>
                                <div style="font-size:0.8rem; color:#64748b; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($r['primary_objective']) ?>">
                                    <?= htmlspecialchars($r['primary_objective'] ?: 'Full Redesign') ?>
                                </div>
                            </td>

                            <!-- Target Launch -->
                            <td>
                                <span style="font-size:0.86rem; color:#475569;">
                                    <i class="far fa-calendar-alt text-muted me-1"></i>
                                    <?= htmlspecialchars($r['preferred_launch_date'] ?: 'Flexible') ?>
                                </span>
                            </td>

                            <!-- Status Dropdown -->
                            <td>
                                <select class="form-select form-select-sm" style="width:145px; border-radius:8px; font-size:0.82rem; font-weight:700;" onchange="updateRowStatus(<?= $r['id'] ?>, this.value)">
                                    <option value="new" <?= $r['status'] === 'new' ? 'selected' : '' ?>>🆕 New</option>
                                    <option value="reviewed" <?= $r['status'] === 'reviewed' ? 'selected' : '' ?>>👁️ Reviewed</option>
                                    <option value="in_progress" <?= $r['status'] === 'in_progress' ? 'selected' : '' ?>>⚡ In Progress</option>
                                    <option value="proposal_sent" <?= $r['status'] === 'proposal_sent' ? 'selected' : '' ?>>📄 Proposal Sent</option>
                                    <option value="archived" <?= $r['status'] === 'archived' ? 'selected' : '' ?>>📁 Archived</option>
                                </select>
                            </td>

                            <!-- Submitted Date -->
                            <td>
                                <span style="font-size:0.85rem; color:#475569;" title="<?= $r['created_at'] ?>">
                                    <?= date('M d, Y', strtotime($r['created_at'])) ?><br>
                                    <small style="color:#94a3b8;"><?= date('h:i A', strtotime($r['created_at'])) ?></small>
                                </span>
                            </td>

                            <!-- Actions -->
                            <td style="text-align:right;">
                                <div class="action-btn-group justify-content-end">
                                    <!-- View Modal Button -->
                                    <button type="button" class="action-btn view-details-btn" title="View Full Questionnaire"
                                        data-id="<?= $r['id'] ?>"
                                        data-ref="<?= htmlspecialchars($r['submission_id']) ?>"
                                        data-name="<?= htmlspecialchars($r['client_name']) ?>"
                                        data-desig="<?= htmlspecialchars($r['designation'] ?? '') ?>"
                                        data-org="<?= htmlspecialchars($r['organization'] ?? '') ?>"
                                        data-email="<?= htmlspecialchars($r['email']) ?>"
                                        data-phone="<?= htmlspecialchars($r['phone']) ?>"
                                        data-date="<?= $r['created_at'] ?>"
                                        data-status="<?= $r['status'] ?>"
                                        data-notes="<?= htmlspecialchars($r['admin_notes'] ?? '') ?>"
                                        data-json='<?= htmlspecialchars($r['form_data'], ENT_QUOTES, 'UTF-8') ?>'>
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    <!-- Edit Notes Button -->
                                    <button type="button" class="action-btn" title="Admin Notes" onclick="openNotesModal(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['admin_notes'] ?? '')) ?>')">
                                        <i class="fas fa-sticky-note" style="<?= !empty($r['admin_notes']) ? 'color:#f59e0b;' : '' ?>"></i>
                                    </button>

                                    <!-- Delete Button -->
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete submission <?= htmlspecialchars($r['submission_id']) ?>? This cannot be undone.');">
                                        <input type="hidden" name="delete_submission" value="1">
                                        <input type="hidden" name="sub_id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="action-btn action-btn-del" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding:60px 20px; color:#94a3b8;">
                                <div style="font-size:2.8rem; margin-bottom:12px; color:#cbd5e1;">
                                    <i class="fas fa-folder-open"></i>
                                </div>
                                <h5 style="color:#64748b; font-weight:700;">No Questionnaire Submissions Found</h5>
                                <p style="font-size:0.9rem; margin:0;">Share the public discovery link with stakeholders to receive responses.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  VIEW FULL QUESTIONNAIRE MODAL                                         -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay-client" id="detailsModal">
    <div class="modal-content-client">
        <div class="modal-header-client">
            <h3>
                <i class="fas fa-file-invoice"></i>
                <span>Discovery Questionnaire Details • <span id="mRefCode" style="color:var(--accent);"></span></span>
            </h3>
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-sm btn-light" onclick="printQuestionnaireModal()" style="border-radius:20px; font-weight:600; font-size:0.8rem;">
                    <i class="fas fa-print me-1"></i> Print / PDF
                </button>
                <button class="modal-close-client" onclick="closeDetailsModal()">&times;</button>
            </div>
        </div>

        <div class="modal-body-client" id="printableArea">

            <!-- Client Identity Header -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:20px 24px; margin-bottom:24px;">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="detail-q-label">Client Name</div>
                        <div class="detail-q-val" id="mClientName" style="font-weight:700; color:var(--primary);"></div>
                    </div>
                    <div class="col-md-3">
                        <div class="detail-q-label">Designation & Org</div>
                        <div class="detail-q-val" id="mClientOrg"></div>
                    </div>
                    <div class="col-md-3">
                        <div class="detail-q-label">Contact Email</div>
                        <div class="detail-q-val" id="mClientEmail"></div>
                    </div>
                    <div class="col-md-3">
                        <div class="detail-q-label">Phone / WhatsApp</div>
                        <div class="detail-q-val" id="mClientPhone"></div>
                    </div>
                </div>
            </div>

            <!-- Dynamic 24 Section Answers Container -->
            <div id="mSectionsBreakdown"></div>
        </div>

        <div class="modal-footer-client">
            <div id="mSubmissionDate" style="font-size:0.85rem; color:#64748b;"></div>
            <button class="btn btn-secondary" onclick="closeDetailsModal()" style="border-radius:50px; padding:8px 24px;">
                Close
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  ADMIN NOTES MODAL                                                     -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay-client" id="notesModal">
    <div class="modal-content-client" style="max-width:550px;">
        <div class="modal-header-client" style="background:#1e293b;">
            <h3 style="font-size:1.15rem;"><i class="fas fa-sticky-note me-2 text-warning"></i> Internal Admin Notes</h3>
            <button class="modal-close-client" onclick="closeNotesModal()">&times;</button>
        </div>
        <form method="POST" action="client_questionnaires.php">
            <input type="hidden" name="save_notes" value="1">
            <input type="hidden" name="sub_id" id="notesSubId">
            <input type="hidden" name="current_tab" value="<?= htmlspecialchars($tab) ?>">
            
            <div class="modal-body-client">
                <label class="form-label" style="font-size:0.85rem; color:#64748b;">Keep internal observations, proposal notes, follow-up dates:</label>
                <textarea name="admin_notes" id="notesTextarea" class="form-control" style="min-height:160px;" placeholder="e.g. Discussed proposal requirements with client team..."></textarea>
            </div>
            <div class="modal-footer-client">
                <button type="button" class="btn btn-secondary" onclick="closeNotesModal()" style="border-radius:50px;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background:var(--primary); border-color:var(--primary); border-radius:50px; font-weight:700;">
                    Save Notes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Scripts for Modals & AJAX Status Updating -->
<script>
// ── AJAX Status Updater ──────────────────────────────────────────────────────
function updateRowStatus(subId, newStatus) {
    const formData = new FormData();
    formData.append('update_status', '1');
    formData.append('ajax_update', '1');
    formData.append('sub_id', subId);
    formData.append('new_status', newStatus);

    fetch('client_questionnaires.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Status updated successfully');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Failed to update status', 'warn');
    });
}

// ── Detail Modal View ────────────────────────────────────────────────────────
document.querySelectorAll('.view-details-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const ref    = this.dataset.ref;
        const name   = this.dataset.name;
        const desig  = this.dataset.desig;
        const org    = this.dataset.org;
        const email  = this.dataset.email;
        const phone  = this.dataset.phone;
        const date   = this.dataset.date;
        const rawJson = this.dataset.json;

        document.getElementById('mRefCode').innerText = ref;
        document.getElementById('mClientName').innerText = name || org || 'Discovery Submission';
        document.getElementById('mClientOrg').innerText = org ? (desig ? desig + ' • ' : '') + org : (desig || '—');
        document.getElementById('mClientEmail').innerText = email || '—';
        document.getElementById('mClientPhone').innerText = phone || '—';
        document.getElementById('mSubmissionDate').innerText = 'Submitted on ' + new Date(date).toLocaleString();

        let data = {};
        try {
            data = JSON.parse(rawJson);
        } catch(e) {
            console.error("JSON parse error:", e);
        }

        renderSectionsBreakdown(data);

        document.getElementById('detailsModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    });
});

function closeDetailsModal() {
    document.getElementById('detailsModal').classList.remove('active');
    document.body.style.overflow = '';
}

function renderSectionsBreakdown(data) {
    const container = document.getElementById('mSectionsBreakdown');
    container.innerHTML = '';

    const sectionConfigs = [
        {
            num: "01",
            title: "Institution & Business Overview",
            fields: [
                { key: "sec_1_1_official_name", label: "1.1 Official Institution Name" },
                { key: "sec_1_2_objectives", label: "1.2 Primary Website Objectives", isArray: true },
                { key: "sec_1_2_other", label: "1.2 Other Objective" },
                { key: "sec_1_3_first_30s", label: "1.3 First 30 Seconds Impression" },
                { key: "sec_1_4_differentiation", label: "1.4 What Makes Your Institution Different" },
                { key: "sec_1_5_top_achievements", label: "1.5 3–5 Strongest Achievements" },
                { key: "sec_1_6_year_est", label: "1.6 Year Established" },
                { key: "sec_1_6_num_campuses", label: "1.6 Number of Campuses" },
                { key: "sec_1_6_num_students", label: "1.6 Total Students" },
                { key: "sec_1_6_num_faculty", label: "1.6 Faculty & Staff" },
                { key: "sec_1_6_cities_served", label: "1.6 Cities / Areas Served" },
                { key: "sec_1_6_other_stats", label: "1.6 Other Key Stats" },
                { key: "sec_1_7_corrections", label: "1.7 Information to Remove or Correct" }
            ]
        },
        {
            num: "02",
            title: "Website Goals & Success Metrics",
            fields: [
                { key: "sec_2_1_goals", label: "2.1 Primary Website Goals", isArray: true },
                { key: "sec_2_2_success_criteria", label: "2.2 Project Success Criteria", isArray: true },
                { key: "sec_2_3_tracking", label: "2.3 Currently Tracking Visitors?" },
                { key: "sec_2_3_tracking_details", label: "2.3 Current Tracking Details" }
            ]
        },
        {
            num: "03",
            title: "Target Audience & Common Inquiries",
            fields: [
                { key: "sec_3_1_audience", label: "3.1 Primary Visitors", isArray: true },
                { key: "sec_3_2_greatest_focus", label: "3.2 Greatest Focus Audience" },
                { key: "sec_3_3_parent_questions", label: "3.3 What Parents Most Ask Before Admission" },
                { key: "sec_3_4_channel_questions", label: "3.4 Common Queries Received" }
            ]
        },
        {
            num: "04",
            title: "Admissions Workflow & Portal",
            fields: [
                { key: "sec_4_1_process", label: "4.1 Current Admission Process" },
                { key: "sec_4_2_current_admissions", label: "4.2 Classes Currently Accepting Admissions" },
                { key: "sec_4_3_campus_diff", label: "4.3 Campuses have Different Admission Procedures?" },
                { key: "sec_4_3_diff_notes", label: "4.3 Procedure Differences" },
                { key: "sec_4_4_fee_diff", label: "4.4 Campuses have Different Fee Structures?" },
                { key: "sec_4_4_fee_notes", label: "4.4 Fee Differences" },
                { key: "sec_4_5_online_submission", label: "4.5 Complete Online Application Submission?" },
                { key: "sec_4_6_fields_needed", label: "4.6 Application Fields Required" },
                { key: "sec_4_7_doc_upload", label: "4.7 Document Upload Required?" },
                { key: "sec_4_7_which_docs", label: "4.7 Which Documents" },
                { key: "sec_4_8_confirmations", label: "4.8 Automatic Confirmation Channels", isArray: true },
                { key: "sec_4_9_dashboard", label: "4.9 Admissions Dashboard" },
                { key: "sec_4_10_check_status", label: "4.10 Check Status Online" }
            ]
        },
        {
            num: "05",
            title: "Campus Directory & Multi-Campus Features",
            fields: [
                { key: "sec_5_1_campuses_list", label: "5.1 List of Campuses & Details" },
                { key: "sec_5_2_filter_campuses", label: "5.2 Search/Filter Campuses by City/Area?" },
                { key: "sec_5_3_dedicated_page", label: "5.3 Dedicated Webpage for Each Campus?" },
                { key: "sec_5_4_campus_apply", label: "5.4 Apply Specifically to a Selected Campus?" },
                { key: "sec_5_5_nearest_campus", label: "5.5 'Find Your Nearest Campus' Feature?" }
            ]
        },
        {
            num: "06",
            title: "Academic Structure",
            fields: [
                { key: "sec_6_1_levels", label: "6.1 Academic Levels Presented", isArray: true },
                { key: "sec_6_2_programs_list", label: "6.2 List of Programs Currently Offered" },
                { key: "sec_6_3_campus_programs_diff", label: "6.3 Programs Different Between Campuses?" },
                { key: "sec_6_4_program_pages", label: "6.4 Dedicated Page for Each Academic Program?" },
                { key: "sec_6_5_curriculum", label: "6.5 Curriculum / Boards Followed" },
                { key: "sec_6_6_strengths", label: "6.6 Major Academic Strengths" }
            ]
        },
        {
            num: "07",
            title: "Results & Achievements",
            fields: [
                { key: "sec_7_1_dedicated_section", label: "7.1 Dedicated Results Section?" },
                { key: "sec_7_2_board_positions", label: "7.2 Board Positions & High Achievers" },
                { key: "sec_7_2_scholarships", label: "7.2 Scholarships & University Admissions" },
                { key: "sec_7_2_competitions", label: "7.2 Science & Tech Awards" },
                { key: "sec_7_2_sports", label: "7.2 Sports Achievements" },
                { key: "sec_7_3_historical_data", label: "7.3 Historical Results Data Available?" },
                { key: "sec_7_4_org_preferences", label: "7.4 Organization Criteria" }
            ]
        },
        {
            num: "08",
            title: "Campus Life & Facilities",
            fields: [
                { key: "sec_8_1_facilities", label: "8.1 Facilities Highlighted", isArray: true },
                { key: "sec_8_2_photos", label: "8.2 Professional Photos/Videos Available?" },
                { key: "sec_8_3_media_production", label: "8.3 Photography/Video Production Needed?" },
                { key: "sec_8_4_activities", label: "8.4 Student Activities Highlighted", isArray: true }
            ]
        },
        {
            num: "09",
            title: "Content & Branding",
            fields: [
                { key: "sec_9_1_logo_branding", label: "9.1 Retain Logo & Branding?" },
                { key: "sec_9_2_guidelines", label: "9.2 Official Brand Guidelines Available?" },
                { key: "sec_9_3_colors", label: "9.3 Maintain Color Scheme?" },
                { key: "sec_9_4_visual_style", label: "9.4 Preferred Visual Style" },
                { key: "sec_9_5_favorite_sites", label: "9.5 Websites Liked" },
                { key: "sec_9_6_unwanted_styles", label: "9.6 Websites / Styles NOT Wanted" }
            ]
        },
        {
            num: "10-13",
            title: "News, Downloads, Portals & Communication",
            fields: [
                { key: "sec_10_1_news_manager", label: "10.1 Who Manages News Currently" },
                { key: "sec_10_2_self_publish", label: "10.2 Staff Publish News Directly?" },
                { key: "sec_10_3_modules", label: "10.3 Publishing Modules Required", isArray: true },
                { key: "sec_10_4_auto_archive", label: "10.4 Auto-Archive Old Notices?" },
                { key: "sec_11_1_downloads", label: "11.1 Downloadable Documents", isArray: true },
                { key: "sec_11_2_admin_upload", label: "11.2 Staff Upload via Admin?" },
                { key: "sec_12_1_existing_portal", label: "12.1 Existing Student/Parent Portal?" },
                { key: "sec_12_2_portal_link_or_api", label: "12.2 Portal Integration Details" },
                { key: "sec_12_3_portal_features", label: "12.3 Future Portal Features", isArray: true },
                { key: "sec_12_4_software_integration", label: "12.4 Software Integrations" },
                { key: "sec_13_1_channels", label: "13.1 Communication Channels Used", isArray: true },
                { key: "sec_13_2_auto_notify", label: "13.2 Auto-Notify Admissions Team?" },
                { key: "sec_13_3_receive_dest", label: "13.3 Enquiry Destinations", isArray: true },
                { key: "sec_13_4_centralized_crm", label: "13.4 Centralized Admissions CRM?" },
                { key: "sec_13_5_separate_campus_leads", label: "13.5 Campuses Receive Enquiries Separately?" },
                { key: "sec_16_1_platforms", label: "16.1 Active Social Platforms", isArray: true },
                { key: "sec_16_2_feed_display", label: "16.2 Social Media Feed Displayed?" },
                { key: "sec_16_3_share_buttons", label: "16.3 Social Sharing Buttons?" }
            ]
        },
        {
            num: "14-20",
            title: "Technical, Payments, SEO & Security",
            fields: [
                { key: "sec_14_1_support_payments", label: "14.1 Online Payments Supported?" },
                { key: "sec_14_2_payment_types", label: "14.2 Payment Types", isArray: true },
                { key: "sec_14_3_methods", label: "14.3 Payment Methods Used", isArray: true },
                { key: "sec_14_4_gateway_status", label: "14.4 Payment Gateway Status" },
                { key: "sec_15_1_seo_priority", label: "15.1 Google SEO a Priority?" },
                { key: "sec_15_2_target_locations", label: "15.2 Target Locations" },
                { key: "sec_15_3_keywords", label: "15.3 Target Keywords" },
                { key: "sec_15_4_google_tools", label: "15.4 Access to Google Tools", isArray: true },
                { key: "sec_15_5_seo_service", label: "15.5 Include SEO Services?" },
                { key: "sec_17_1_platform_choice", label: "17.1 Platform Preference" },
                { key: "sec_17_2_hosting_retained", label: "17.2 Retain Hosting?" },
                { key: "sec_17_3_access_items", label: "17.3 Existing Access Available", isArray: true },
                { key: "sec_17_4_must_remain_operational", label: "17.4 Systems to Keep Live" },
                { key: "sec_17_5_admin_cms", label: "17.5 Include CMS Admin?" },
                { key: "sec_17_6_admin_roles", label: "17.6 Admin Panel Roles", isArray: true },
                { key: "sec_18_1_languages", label: "18.1 Languages Supported" },
                { key: "sec_18_2_scope", label: "18.2 Bilingual Scope" },
                { key: "sec_18_3_independent_cms", label: "18.3 Manage Languages Independently?" },
                { key: "sec_19_1_mobile_pct", label: "19.1 Mobile Visitors %" },
                { key: "sec_19_2_mobile_priority", label: "19.2 Mobile First Priority" },
                { key: "sec_19_3_accessibility", label: "19.3 Accessibility Rules" },
                { key: "sec_20_1_sensitive_info", label: "20.1 Collect Sensitive Student Info?" },
                { key: "sec_20_2_retention", label: "20.2 Retention Requirements" },
                { key: "sec_20_3_security_controls", label: "20.3 Security Controls Needed", isArray: true }
            ]
        },
        {
            num: "21-24",
            title: "Project Scope, Timeline, Budget & Feedback",
            fields: [
                { key: "sec_21_1_scope", label: "21.1 Desired Project Scope" },
                { key: "sec_21_2_launch_date", label: "21.2 Target Launch Date" },
                { key: "sec_21_3_admission_season", label: "21.3 Admission Season Target" },
                { key: "sec_21_4_budget", label: "21.4 Budget Range" },
                { key: "sec_21_5_phasing", label: "21.5 Phasing Preference" },
                { key: "sec_21_6_decision_maker", label: "21.6 Decision Maker" },
                { key: "sec_22_1_maintenance_by", label: "22.1 Post Launch Maintenance" },
                { key: "sec_22_2_tech_maintenance", label: "22.2 Tech Maintenance Needed?" },
                { key: "sec_22_3_seo_retainer", label: "22.3 SEO Retainer Needed?" },
                { key: "sec_22_4_future_plans", label: "22.4 Long Term Features" },
                { key: "sec_23_1_current_likes", label: "23.1 What is Liked in Current Site" },
                { key: "sec_23_2_current_dislikes", label: "23.2 What is Disliked in Current Site" },
                { key: "sec_23_3_user_complaints", label: "23.3 Complaints Received" },
                { key: "sec_23_4_retain_parts", label: "23.4 Parts to Retain" },
                { key: "sec_23_5_remove_parts", label: "23.5 Parts to Remove" },
                { key: "sec_24_1_three_changes", label: "24.1 Top 3 Changes Desired" },
                { key: "sec_24_2_single_outcome", label: "24.2 Single Most Important Outcome" },
                { key: "sec_24_3_additional_notes", label: "24.3 Additional Institutional Notes" },
                { key: "sec_24_4_materials", label: "24.4 Materials Available to Provide", isArray: true }
            ]
        }
    ];

    sectionConfigs.forEach(sec => {
        const secCard = document.createElement('div');
        secCard.className = 'detail-sec-card';

        let innerHtml = `<div class="detail-sec-title"><span>#${sec.num}</span> <span>${sec.title}</span></div>`;
        let hasAnyField = false;

        sec.fields.forEach(f => {
            const rawVal = data[f.key];
            if (rawVal !== undefined && rawVal !== null && rawVal !== '') {
                hasAnyField = true;
                innerHtml += `<div class="detail-q-row"><div class="detail-q-label">${f.label}</div><div class="detail-q-val">`;

                if (Array.isArray(rawVal)) {
                    if (rawVal.length === 0) {
                        innerHtml += '<em class="text-muted">None selected</em>';
                    } else {
                        rawVal.forEach(chip => {
                            innerHtml += `<span class="chip-tag">${chip}</span>`;
                        });
                    }
                } else {
                    innerHtml += nl2br(escapeHtml(rawVal));
                }

                innerHtml += `</div></div>`;
            }
        });

        if (!hasAnyField) {
            innerHtml += `<div class="text-muted" style="font-size:0.85rem; font-style:italic;">No answers recorded for this section.</div>`;
        }

        secCard.innerHTML = innerHtml;
        container.appendChild(secCard);
    });
}

function escapeHtml(text) {
    if (typeof text !== 'string') return text;
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function nl2br(str) {
    if (typeof str !== 'string') return str;
    return str.replace(/\n/g, '<br>');
}

function printQuestionnaireModal() {
    window.print();
}

// ── Admin Notes Modal ────────────────────────────────────────────────────────
function openNotesModal(subId, notes) {
    document.getElementById('notesSubId').value = subId;
    document.getElementById('notesTextarea').value = notes || '';
    document.getElementById('notesModal').classList.add('active');
}

function closeNotesModal() {
    document.getElementById('notesModal').classList.remove('active');
}

// ── Toast Notification Helper ────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;bottom:24px;right:24px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 18px;display:flex;align-items:center;gap:12px;box-shadow:0 6px 20px rgba(0,0,0,.08);font-size:14px;font-weight:600;min-width:220px;border-left:5px solid ${type==='success'?'#10b981':'#f59e0b'};z-index:99999;`;
    t.innerHTML = `<span style="color:${type==='success'?'#10b981':'#f59e0b'}"><i class="fas ${type==='success'?'fa-check-circle':'fa-exclamation-triangle'}"></i></span><span>${msg}</span>`;
    document.body.appendChild(t);
    setTimeout(() => {
        t.style.opacity = '0';
        t.style.transition = 'opacity .3s';
        setTimeout(() => t.remove(), 300);
    }, 3500);
}

document.addEventListener('DOMContentLoaded', () => {
    <?php if (isset($_GET['deleted'])): ?> showToast('Submission deleted permanently.'); <?php endif; ?>
    <?php if (isset($_GET['status_updated'])): ?> showToast('Submission status updated.'); <?php endif; ?>
    <?php if (isset($_GET['notes_saved'])): ?> showToast('Internal admin notes saved.'); <?php endif; ?>
});
</script>

<?php include 'dashboard_footer.php'; ?>
