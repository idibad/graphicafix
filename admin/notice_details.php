<?php
// admin/notice_details.php
include 'dashboard_header.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$notice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$notice_id) {
    echo "<script>window.location='manage_notices.php';</script>"; exit;
}

// Fetch current notice
$stmt = $conn->prepare("SELECT * FROM notices WHERE notice_id = ?");
$stmt->bind_param("i", $notice_id);
$stmt->execute();
$notice = $stmt->get_result()->fetch_assoc();

if (!$notice) {
    echo "<div class='container py-5'><div class='card border-0 shadow-sm text-center p-5'><h2 class='text-danger'>Notice Not Found</h2><p class='text-muted'>This notice does not exist or has been deleted.</p><a href='manage_notices.php' class='btn btn-primary btn-sm align-self-center mt-3'>Back to Board</a></div></div>";
    include 'dashboard_footer.php'; exit;
}

// Fetch 5 other recent notices for sidebar
$recent_stmt = $conn->prepare("
    SELECT notice_id, notice_title, date 
    FROM notices 
    WHERE notice_id != ? 
    ORDER BY date DESC, notice_id DESC 
    LIMIT 5
");
$recent_stmt->bind_param("i", $notice_id);
$recent_stmt->execute();
$recent_notices = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="height-100">
<div class="tasks-container">

    <!-- Header -->
    <div class="td-header">
        <div class="td-header-left">
            <span class="td-id">NOTICE BOARD DETAILS</span>
            <h1 class="td-title"><?= htmlspecialchars($notice['notice_title']) ?></h1>
            <div class="td-subtitle">
                <span class="badge" style="background:var(--primary-color); color:white; font-size:10px;"><i class="fas fa-calendar-alt"></i> Posted: <?= date('M d, Y', strtotime($notice['date'])) ?></span>
                <?php if (!empty($notice['created_by'])): ?>
                    <span class="badge" style="background:#e2e8f0; color:#334155; font-size:10px; margin-left:6px;"><i class="fas fa-user"></i> By: <?= htmlspecialchars($notice['created_by']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="td-header-right" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <a href="print_notice.php?id=<?= $notice['notice_id'] ?>&auto=1" target="_blank" class="btn-primary-custom" style="display:inline-flex; align-items:center; gap:8px; text-decoration:none; padding:9px 18px; border-radius:10px; font-weight:700; font-size:13.5px; background:var(--primary-color); color:#fff; box-shadow:0 2px 8px rgba(0,0,0,0.12);">
                <i class="fas fa-print"></i> Print Notice (PDF)
            </a>
            <a href="print_notice.php?id=<?= $notice['notice_id'] ?>&pdf=1" class="btn-secondary-custom" style="display:inline-flex; align-items:center; gap:8px; text-decoration:none; padding:9px 16px; border-radius:10px; font-weight:600; font-size:13.5px;" title="Download Official PDF file directly">
                <i class="fas fa-file-pdf" style="color:#ef4444;"></i> Download PDF
            </a>
            <a href="manage_notices.php" class="btn-secondary-custom" style="display:inline-flex; align-items:center; gap:6px; text-decoration:none; padding:9px 16px; border-radius:10px; font-weight:600; font-size:13.5px;">
                <i class="fas fa-arrow-left"></i> Back to Board
            </a>
        </div>
    </div>

    <!-- Layout Grid -->
    <div class="td-grid">
        
        <!-- Left: Main Notice content -->
        <div class="td-main">
            <div class="sidebar-card">
                <div class="sidebar-header"><i class="fas fa-bullhorn"></i> Notice Description</div>
                <div class="td-description" style="background: transparent; border: none; padding: 0; min-height: unset; font-size: 14.5px; line-height: 1.8; color: #334155;">
                    <?= !empty($notice['content']) ? nl2br(htmlspecialchars($notice['content'])) : '<span class="text-muted italic">No content description provided.</span>' ?>
                </div>
            </div>
        </div>

        <!-- Right: Actions & Recent Notices Sidebar -->
        <div class="td-sidebar">
            <!-- Official Document Actions Card -->
            <div class="td-meta-card">
                <div class="td-section-title"><i class="fas fa-file-contract"></i> Official Notice Actions</div>
                <p style="font-size:12.5px; color:#64748b; margin:0 0 16px 0; line-height:1.5;">
                    Print an official notice document featuring the Graphicafix logo on top left, date, subject, description, and signature authorization.
                </p>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <a href="print_notice.php?id=<?= $notice['notice_id'] ?>&auto=1" target="_blank" class="btn-primary-custom" style="display:flex; justify-content:center; align-items:center; gap:8px; text-decoration:none; padding:10px 14px; border-radius:8px; font-weight:700; font-size:13px; background:var(--primary-color); color:#fff;">
                        <i class="fas fa-print"></i> Print Clean PDF
                    </a>
                    <a href="print_notice.php?id=<?= $notice['notice_id'] ?>&pdf=1" class="btn-secondary-custom" style="display:flex; justify-content:center; align-items:center; gap:8px; text-decoration:none; padding:9px 14px; border-radius:8px; font-weight:600; font-size:13px;">
                        <i class="fas fa-file-download" style="color:#0ea5e9;"></i> Download PDF (mPDF)
                    </a>
                </div>
            </div>

            <div class="td-meta-card">
                <div class="td-section-title"><i class="fas fa-history"></i> Recent Notices</div>
                <div style="display: flex; flex-direction: column; gap: 14px; margin-top: 10px;">
                    <?php if (empty($recent_notices)): ?>
                        <div class="text-muted small" style="font-style: italic;">No other recent notices.</div>
                    <?php else: ?>
                        <?php foreach ($recent_notices as $rn): 
                            $rn_date = date('M d, Y', strtotime($rn['date']));
                        ?>
                            <a href="notice_details.php?id=<?= $rn['notice_id'] ?>" style="display:block; text-decoration: none; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                                <strong style="font-size: 13px; color: var(--primary-color); display: block; line-height: 1.4;"><?= htmlspecialchars($rn['notice_title']) ?></strong>
                                <span class="text-muted" style="font-size: 10.5px; display: block; margin-top: 3px;"><i class="fas fa-calendar-alt" style="font-size: 9px; color: #94a3b8;"></i> <?= $rn_date ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>
</div>

<style>
/* ── Notice Details Layout ── */
.td-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color);
}
.td-title { font-size: 24px; font-weight: 800; color: var(--text-dark); margin: 0 0 10px 0; line-height: 1.3; }
.td-id { font-size: 13.5px; color: var(--text-muted); font-weight: 700; letter-spacing: 0.5px; }

.td-grid {
    display: grid; grid-template-columns: 2.5fr 1fr; gap: 30px; align-items: start;
}

.td-main { display: flex; flex-direction: column; }
.td-sidebar { display: flex; flex-direction: column; gap: 20px; }

.td-section-title {
    font-size: 14px; font-weight: 700; color: var(--text-dark);
    margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;
}
.td-description {
    background: var(--bg-light); padding: 20px; border-radius: 12px; border: 1px solid var(--border-color);
    font-size: 14px; color: var(--text-dark); line-height: 1.7; white-space: pre-line;
}

.td-meta-card {
    background: #ffffff; border: 1px solid var(--border-color); border-radius: 16px;
    padding: 24px; box-shadow: var(--shadow-sm);
}

.sidebar-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #f0f3f2; }
.sidebar-header { font-size: 15px; font-weight: 700; color: #333; margin-bottom: 15px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; display: flex; align-items: center; gap: 8px; }
.sidebar-header i { color: var(--primary-color); }

@media (max-width: 991px) {
    .td-grid { grid-template-columns: 1fr; }
    .td-header { flex-direction: column; gap: 15px; }
}
</style>

<?php include 'dashboard_footer.php'; ?>
