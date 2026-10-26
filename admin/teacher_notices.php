<?php
include 'dashboard_header.php';

if (!in_array($role, ['teacher', 'super_admin', 'superadmin', 'admin', 'pm', 'manager', 'hrm', 'user'])) {
    header("Location: index.php?error=unauthorized");
    exit;
}

$notices = $conn->query("SELECT * FROM notices ORDER BY `date` DESC");
$total = $notices ? $notices->num_rows : 0;
?>
<div class="height-100">
    <div class="main-header" style="margin-bottom:20px;">
        <h3><i class="fas fa-bullhorn"></i> Notice Board</h3>
        <p style="color:#666; font-size:14px;">Important announcements and updates from the admin team.</p>
    </div>

    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clipboard"></i></div>
            <div class="stat-number"><?= $total ?></div>
            <div class="stat-label">Total Notices</div>
        </div>
    </div>

    <?php if($notices && $notices->num_rows > 0): while($n = $notices->fetch_assoc()): ?>
    <div class="d-card" style="margin-bottom:16px; border-left:4px solid var(--accent);">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
            <div style="flex:1; min-width:260px;">
                <a href="notice_details.php?id=<?= $n['notice_id'] ?>" style="text-decoration:none;"><h4 style="font-size:1.05rem; font-weight:700; color:var(--primary-color); margin:0 0 6px;"><?= htmlspecialchars($n['notice_title']) ?></h4></a>
                <?php 
                    $content_text = !empty($n['content']) ? $n['content'] : ($n['notice_body'] ?? '');
                    if(!empty($content_text)): 
                ?>
                    <p style="font-size:14px; color:#555; margin:0 0 10px; line-height:1.6;"><?= nl2br(htmlspecialchars($content_text)) ?></p>
                <?php endif; ?>
                <div style="display:flex; gap:15px; font-size:12px; color:#888;">
                    <span><i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($n['date'])) ?></span>
                    <?php if(!empty($n['created_by'])): ?>
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($n['created_by']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <a href="print_notice.php?id=<?= $n['notice_id'] ?>&auto=1" target="_blank" class="btn btn-sm btn-outline-secondary" style="display:inline-flex; align-items:center; gap:6px; font-size:12px; padding:6px 12px; border-radius:6px; text-decoration:none; color:var(--primary-color); border:1px solid #cbd5e1; background:#fff;" title="Print official notice into PDF">
                    <i class="fas fa-print"></i> Print (PDF)
                </a>
            </div>
        </div>
    </div>
    <?php endwhile; else: ?>
    <div class="d-card" style="text-align:center; padding:60px 20px; color:#888;">
        <i class="fas fa-bell-slash" style="font-size:48px; color:#ddd; margin-bottom:15px;"></i>
        <p style="font-size:16px; font-weight:600;">No notices posted yet.</p>
        <p style="font-size:13px;">Check back later for important updates.</p>
    </div>
    <?php endif; ?>
</div>
<?php include('dashboard_footer.php'); ?>
