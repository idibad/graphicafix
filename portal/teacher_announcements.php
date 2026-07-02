<?php
include 'dashboard_header.php';

$user_id = intval($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_notice'])) {
    $title = trim($_POST['title']);
    $msg = trim($_POST['message']);
    $by = ($_SESSION['name'] ?? 'Instructor') . " (#$user_id)";
    
    $stmt = $conn->prepare("INSERT INTO notices (notice_title, content, `date`, created_at, created_by) VALUES (?, ?, CURDATE(), NOW(), ?)");
    $stmt->bind_param("sss", $title, $msg, $by);
    $stmt->execute();
    header("Location: teacher_announcements.php?posted=1"); exit;
}

if (isset($_GET['del'])) {
    $del = intval($_GET['del']);
    @$conn->query("DELETE FROM notices WHERE notice_id = $del");
    header("Location: teacher_announcements.php?deleted=1"); exit;
}

$notices = $conn->query("SELECT *, notice_id as id, notice_title as title, content as message, created_at FROM notices ORDER BY created_at DESC");
?>

<div class="height-100">
    <div class="main-header mb-4">
        <div>
            <h3 style="font-weight:800; color:#024442; margin:0;"><i class="fas fa-bullhorn"></i> Campus Announcements Broadcast</h3>
            <p style="color:#64748b; font-size:14px; margin:4px 0 0;">Broadcast news updates and schedule adjustments to all enrolled students.</p>
        </div>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#noticeModal"><i class="fas fa-plus"></i> New Broadcast</button>
    </div>

    <?php if(isset($_GET['posted'])): ?><div class="alert alert-success fw-bold">Broadcast sent to campus noticeboard!</div><?php endif; ?>

    <div class="row g-4">
        <?php if($notices && $notices->num_rows > 0): while($n = $notices->fetch_assoc()): ?>
            <div class="col-md-6">
                <div class="d-card p-4 h-100 d-flex flex-column border">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <span class="badge bg-success text-white"><i class="fas fa-satellite-dish"></i> Active Broadcast</span>
                        <a href="teacher_announcements.php?del=<?= $n['id'] ?>" class="text-danger" onclick="return confirm('Delete broadcast?')"><i class="fas fa-trash"></i></a>
                    </div>
                    <h4 style="font-weight:800; color:#0f172a; margin:4px 0 12px;"><?= htmlspecialchars($n['title']) ?></h4>
                    <div style="color:#334155; font-size:14px; line-height:1.6; flex:1;"><?= nl2br(htmlspecialchars($n['message'])) ?></div>
                    <div style="font-size:11.5px; color:#94a3b8; margin-top:16px; border-top:1px solid #f1f5f9; padding-top:10px;">Posted on <?= date('M d, Y @ H:i', strtotime($n['created_at'])) ?></div>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div class="col-12 text-center py-5 text-muted">
                <i class="fas fa-bullhorn fs-1 mb-3"></i>
                <h4>No Broadcasts Sent</h4>
                <p>Keep your students informed about course updates.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="noticeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px;">
            <div class="modal-header bg-dark text-white p-4">
                <h5 class="modal-title fw-bold"><i class="fas fa-bullhorn text-warning"></i> Post Campus Notice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="post_notice" value="1">
                    <div class="form-group mb-3">
                        <label class="fw-bold fs-6 mb-2">Headline / Subject</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. Server Maintenance tonight at 10 PM">
                    </div>
                    <div class="form-group">
                        <label class="fw-bold fs-6 mb-2">Notice Message Body</label>
                        <textarea name="message" class="form-control" rows="5" required placeholder="Type broadcast message..."></textarea>
                    </div>
                </div>
                <div class="modal-footer p-4 pt-0 border-0">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Broadcast &rarr;</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('dashboard_footer.php'); ?>
