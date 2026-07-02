<?php
include 'dashboard_header.php';

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
            <div>
                <h4 style="font-size:1.05rem; font-weight:700; color:#333; margin:0 0 6px;"><?= htmlspecialchars($n['notice_title']) ?></h4>
                <?php if(!empty($n['notice_body'])): ?>
                    <p style="font-size:14px; color:#555; margin:0 0 10px; line-height:1.6;"><?= nl2br(htmlspecialchars($n['notice_body'])) ?></p>
                <?php endif; ?>
                <div style="display:flex; gap:15px; font-size:12px; color:#888;">
                    <span><i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($n['date'])) ?></span>
                    <?php if(!empty($n['created_by'])): ?>
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($n['created_by']) ?></span>
                    <?php endif; ?>
                </div>
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
