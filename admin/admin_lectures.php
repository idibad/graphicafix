<?php
include 'dashboard_header.php';

if ($role !== 'admin' && $role !== 'superadmin') {
    die("Access denied");
}

// Fetch stats
$total_lectures = $conn->query("SELECT COUNT(*) as c FROM recorded_lectures")->fetch_assoc()['c'] ?? 0;
$pending_lectures = $conn->query("SELECT COUNT(*) as c FROM recorded_lectures WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0;
$approved_lectures = $conn->query("SELECT COUNT(*) as c FROM recorded_lectures WHERE status = 'approved'")->fetch_assoc()['c'] ?? 0;

// All lectures
$lectures = $conn->query("
    SELECT l.*, 
           t.username as teacher_name,
           c.title as course_title
    FROM recorded_lectures l 
    JOIN users t ON l.teacher_id = t.user_id 
    JOIN courses c ON l.course_id = c.id
    ORDER BY CASE WHEN l.status = 'pending' THEN 1 ELSE 2 END, l.created_at DESC
");
?>
<div class="height-100">
    <div class="main-header" style="margin-bottom:20px;">
        <h3><i class="fas fa-video"></i> Manage Recorded Lectures</h3>
        <p style="color:#666; font-size:14px;">Review and approve lecture videos uploaded by teachers.</p>
    </div>

    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-list"></i></div>
            <div class="stat-number"><?= $total_lectures ?></div>
            <div class="stat-label">Total Uploaded</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?= $pending_lectures ?></div>
            <div class="stat-label">Pending Review</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?= $approved_lectures ?></div>
            <div class="stat-label">Approved</div>
        </div>
    </div>

    <div class="d-card">
        <div class="table-responsive">
            <table style="width:100%; text-align:left; border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:2px solid #eee; color:#666; font-size:13px; text-transform:uppercase;">
                        <th style="padding:12px;">Lecture Details</th>
                        <th style="padding:12px;">Teacher & Course</th>
                        <th style="padding:12px;">Upload Date</th>
                        <th style="padding:12px;">Status</th>
                        <th style="padding:12px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($lectures && $lectures->num_rows > 0): while($d = $lectures->fetch_assoc()): 
                        $badge_color = $d['status'] === 'approved' ? '#059669' : ($d['status'] === 'rejected' ? '#dc2626' : '#d97706');
                        $badge_bg = $d['status'] === 'approved' ? '#d1fae5' : ($d['status'] === 'rejected' ? '#fee2e2' : '#fef3c7');
                    ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:12px;">
                            <div style="font-weight:700; color:#333; margin-bottom:4px;"><?= htmlspecialchars($d['title']) ?></div>
                            <?php if(!empty($d['description'])): ?>
                                <div style="font-size:12px; color:#666; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($d['description']) ?>"><?= htmlspecialchars($d['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="padding:12px;">
                            <div style="font-weight:600; color:var(--primary);"><i class="fas fa-chalkboard-teacher"></i> <?= htmlspecialchars($d['teacher_name']) ?></div>
                            <div style="font-size:12px; color:#666; margin-top:2px;"><i class="fas fa-book"></i> <?= htmlspecialchars($d['course_title']) ?></div>
                        </td>
                        <td style="padding:12px; font-size:13px; color:#666;">
                            <?= date('M d, Y', strtotime($d['created_at'])) ?>
                        </td>
                        <td style="padding:12px;">
                            <span style="background:<?= $badge_bg ?>; color:<?= $badge_color ?>; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:800; text-transform:uppercase;">
                                <?= $d['status'] ?>
                            </span>
                        </td>
                        <td style="padding:12px;">
                            <div style="display:flex; gap:8px;">
                                <a href="<?= htmlspecialchars($d['file_path']) ?>" target="_blank" class="btn btn-secondary-custom" style="padding:6px 12px; font-size:12px;"><i class="fas fa-play"></i> Watch</a>
                                <?php if($d['status'] === 'pending'): ?>
                                    <button onclick="openReviewModal(<?= $d['id'] ?>, '<?= htmlspecialchars(addslashes($d['title'])) ?>')" class="btn btn-primary-custom" style="padding:6px 12px; font-size:12px;"><i class="fas fa-gavel"></i> Review</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#888;">No lectures found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div id="reviewModal" class="modal-overlay">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h4><i class="fas fa-gavel" style="color:var(--primary);"></i> Review Lecture</h4>
            <button onclick="closeReviewModal()" class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="rev_id">
            <div class="form-group">
                <label>Admin Notes / Feedback</label>
                <textarea id="rev_notes" rows="4" placeholder="Enter notes for the teacher..."></textarea>
            </div>
            
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button onclick="submitReview('approve')" class="btn btn-primary-custom" style="flex:1; background:#10b981;"><i class="fas fa-check"></i> Approve</button>
                <button onclick="submitReview('reject')" class="btn btn-primary-custom" style="flex:1; background:#ef4444;"><i class="fas fa-times"></i> Reject</button>
            </div>
        </div>
    </div>
</div>

<script>
function openReviewModal(id, title) {
    document.getElementById('rev_id').value = id;
    document.getElementById('rev_notes').value = '';
    document.getElementById('reviewModal').classList.add('active');
}
function closeReviewModal() {
    document.getElementById('reviewModal').classList.remove('active');
}
function submitReview(status) {
    let id = document.getElementById('rev_id').value;
    let notes = document.getElementById('rev_notes').value.trim();
    
    if(status === 'reject' && notes === '') {
        alert('Notes are required when rejecting a lecture.');
        return;
    }
    
    let fd = new FormData();
    fd.append('action', 'review');
    fd.append('id', id);
    fd.append('status', status);
    fd.append('notes', notes);
    
    fetch('lecture_action.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if(data.success) location.reload();
        else alert(data.message || 'Error saving review');
    })
    .catch(err => alert('Network error'));
}
</script>

<?php include('dashboard_footer.php'); ?>
