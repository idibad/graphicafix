<?php
include 'dashboard_header.php';

$user_id = intval($_SESSION['user_id'] ?? 0);
$safe_email = mysqli_real_escape_string($conn, strtolower(trim($email)));

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $aid = intval($_POST['assignment_id']);
    $notes = trim($_POST['notes'] ?? '');
    
    $file_path = '';
    if (!empty($_FILES['assignment_file']['name'])) {
        $upload_dir = 'uploads/assignments/' . date('Y_m');
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_name = time() . '_' . basename($_FILES['assignment_file']['name']);
        $file_path = $upload_dir . '/' . $file_name;
        move_uploaded_file($_FILES['assignment_file']['tmp_name'], $file_path);
    }
    
    $stmt = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, file_path, notes) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $aid, $user_id, $file_path, $notes);
    $stmt->execute();
    header("Location: student_assignments.php?submitted=1"); exit;
}

// Get enrolled course IDs
$enrolled = $conn->query("SELECT course_id FROM course_enrollments WHERE LOWER(TRIM(student_email)) = '$safe_email' AND status IN ('active','completed')");
$course_ids = [];
while($r = $enrolled->fetch_assoc()) $course_ids[] = $r['course_id'];

$assignments = [];
if (!empty($course_ids)) {
    $ids_str = implode(',', $course_ids);
    // Fetch assignments with submission status
    $res = $conn->query("
        SELECT a.*, c.title as course_title,
            s.id as sub_id, s.grade, s.feedback, s.submitted_at
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = $user_id
        WHERE a.course_id IN ($ids_str)
        ORDER BY a.due_date ASC
    ");
    if($res) { while($row = $res->fetch_assoc()) $assignments[] = $row; }
}

// Stats
$pending = 0; $completed = 0; $overdue = 0;
foreach($assignments as $a) {
    if ($a['sub_id']) $completed++;
    else {
        if (strtotime($a['due_date']) < time()) $overdue++;
        else $pending++;
    }
}
?>
<div class="height-100">
    <div class="main-header" style="margin-bottom:20px;">
        <h3><i class="fas fa-tasks"></i> My Assignments</h3>
        <p style="color:#666; font-size:14px;">Complete your coursework and track your grades.</p>
    </div>

    <?php if(isset($_GET['submitted'])): ?>
        <div style="background:#10b981; color:#fff; padding:12px 20px; border-radius:8px; margin-bottom:20px; font-weight:600;"><i class="fas fa-check-circle"></i> Assignment submitted successfully!</div>
    <?php endif; ?>

    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?= $pending ?></div>
            <div class="stat-label">To Do</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-check-double"></i></div>
            <div class="stat-number"><?= $completed ?></div>
            <div class="stat-label">Submitted</div>
        </div>
        <div class="stat-card red">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-number"><?= $overdue ?></div>
            <div class="stat-label">Overdue</div>
        </div>
    </div>

    <?php if(!empty($assignments)): ?>
        <div class="row g-4">
        <?php foreach($assignments as $a): 
            $is_sub = !empty($a['sub_id']);
            $is_overdue = !$is_sub && strtotime($a['due_date']) < time();
            $days_left = (strtotime($a['due_date']) - time()) / 86400;
            
            $status_color = $is_sub ? '#10b981' : ($is_overdue ? '#ef4444' : '#0984e3');
            $status_bg = $is_sub ? '#d1fae5' : ($is_overdue ? '#fee2e2' : '#e0f2fe');
            $status_text = $is_sub ? 'Submitted' : ($is_overdue ? 'Overdue' : 'Pending');
            $status_icon = $is_sub ? 'fa-check-circle' : ($is_overdue ? 'fa-exclamation-circle' : 'fa-clock');
        ?>
            <div class="col-md-6 col-lg-6">
                <div class="d-card" style="height:100%; border-top:4px solid <?= $status_color ?>;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                        <div>
                            <span style="font-size:11px; font-weight:800; color:var(--primary); text-transform:uppercase; letter-spacing:0.5px;"><i class="fas fa-book" style="color:#aaa;"></i> <?= htmlspecialchars($a['course_title']) ?></span>
                            <h4 style="margin:4px 0; font-size:1.1rem; font-weight:800; color:#333;"><?= htmlspecialchars($a['title']) ?></h4>
                        </div>
                        <span style="background:<?= $status_bg ?>; color:<?= $status_color ?>; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:800; text-transform:uppercase; white-space:nowrap;">
                            <i class="fas <?= $status_icon ?>"></i> <?= $status_text ?>
                        </span>
                    </div>
                    
                    <div style="font-size:13px; color:#555; margin-bottom:15px; line-height:1.5;">
                        <?= nl2br(htmlspecialchars($a['description'])) ?>
                    </div>
                    
                    <div style="display:flex; gap:15px; font-size:12px; color:#888; margin-bottom:20px; padding-bottom:15px; border-bottom:1px solid #eee;">
                        <span><strong style="color:#333;">Due:</strong> <span style="<?= $is_overdue ? 'color:#ef4444;font-weight:700;' : '' ?>"><?= date('l, M d, Y', strtotime($a['due_date'])) ?></span></span>
                        <?php if($is_sub): ?>
                            <span><strong style="color:#333;">Submitted:</strong> <?= date('M d, Y', strtotime($a['submitted_at'])) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if(!$is_sub): ?>
                        <div style="background:#f9fafb; padding:15px; border-radius:10px; border:1px dashed #ccc;">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="submit_assignment" value="1">
                                <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                                
                                <div class="form-group" style="margin-bottom:12px;">
                                    <label>Upload Your Work <span class="required">*</span></label>
                                    <input type="file" name="assignment_file" required>
                                </div>
                                
                                <div class="form-group" style="margin-bottom:15px;">
                                    <label>Notes for Teacher (Optional)</label>
                                    <textarea name="notes" rows="2" placeholder="Any comments..."></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary-custom" style="width:100%;"><i class="fas fa-paper-plane"></i> Submit Assignment</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <?php if($a['grade']): ?>
                            <div style="background:#f0fdf4; padding:15px; border-radius:10px; border:1px solid #bbf7d0;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                    <strong style="color:#166534; font-size:13px;"><i class="fas fa-star"></i> Graded</strong>
                                    <span style="font-size:1.2rem; font-weight:900; color:#15803d;"><?= htmlspecialchars($a['grade']) ?></span>
                                </div>
                                <?php if($a['feedback']): ?>
                                    <div style="font-size:13px; color:#166534; padding-top:8px; border-top:1px dashed #bbf7d0;">
                                        <strong>Feedback:</strong> <?= nl2br(htmlspecialchars($a['feedback'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div style="background:#fefce8; padding:15px; border-radius:10px; border:1px solid #fef08a; text-align:center;">
                                <i class="fas fa-hourglass-half" style="color:#ca8a04; font-size:24px; margin-bottom:10px;"></i>
                                <div style="color:#854d0e; font-size:13px; font-weight:600;">Your submission is being reviewed by the instructor.</div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="d-card" style="text-align:center; padding:60px 20px; color:#888;">
            <i class="fas fa-glass-cheers" style="font-size:48px; color:#ddd; margin-bottom:15px;"></i>
            <p style="font-size:16px; font-weight:600;">You have no assignments right now.</p>
            <p style="font-size:13px;">Enjoy your free time or continue learning in your active courses.</p>
        </div>
    <?php endif; ?>
</div>
<?php include('dashboard_footer.php'); ?>
