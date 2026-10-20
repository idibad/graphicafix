<?php
include 'dashboard_header.php';

$safe_email = mysqli_real_escape_string($conn, strtolower(trim($_SESSION['email'] ?? '')));
$user_id = intval($_SESSION['user_id'] ?? 0);

// Handle Ask Question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ask_query'])) {
    $cid = intval($_POST['course_id']);
    $query_text = trim($_POST['query_text']);
    $stmt = $conn->prepare("INSERT INTO course_queries (course_id, student_id, query_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $cid, $user_id, $query_text);
    $stmt->execute();
    header("Location: student_courses.php?query_sent=1"); exit;
}

$my_courses = $conn->query("
    SELECT e.status as enroll_status, c.id, c.title, c.thumbnail, c.duration_mins, u.name as instructor_name
    FROM course_enrollments e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN users u ON c.instructor_id = u.user_id
    WHERE LOWER(TRIM(e.student_email)) = '$safe_email'
    ORDER BY e.enrolled_at DESC
");
?>

<div class="height-100">
    <div class="main-header mb-4">
        <div>
            <h3 style="font-weight:800; color:#024442; margin:0;"><i class="fas fa-graduation-cap"></i> My Learning Enclosures</h3>
            <p style="color:#64748b; font-size:14px; margin:4px 0 0;">Access your interactive classroom environments and track video completions.</p>
        </div>
    </div>

    <?php if(isset($_GET['query_sent'])): ?>
        <div style="background:#10b981; color:#fff; padding:12px 20px; border-radius:12px; margin-bottom:20px; font-weight:700;"><i class="fas fa-check-circle"></i> Question sent to your instructor!</div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if($my_courses && $my_courses->num_rows > 0): while($c = $my_courses->fetch_assoc()): 
            $status = strtolower(trim($c['enroll_status']));
            $is_active = ($status === 'active');
            $is_completed = ($status === 'completed');
            $is_pending = in_array($status, ['pending', 'reviewing']);

            $cid = intval($c['id']);
            $tot_q = $conn->query("SELECT COUNT(*) as c FROM recorded_lectures WHERE course_id = $cid AND status = 'approved'")->fetch_assoc()['c'] ?? 0;
            $com_q = $conn->query("SELECT COUNT(*) as c FROM student_video_progress WHERE course_id = $cid AND student_id = $user_id AND is_completed = 1")->fetch_assoc()['c'] ?? 0;
            $prog_pct = $tot_q > 0 ? round(($com_q / $tot_q) * 100) : 0;
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="d-card" style="padding:0; overflow:hidden; height:100%; display:flex; flex-direction:column; border:1px solid <?= $is_active ? 'var(--primary-color)' : 'var(--border-color)' ?>;">
                <div style="height: 180px; background: var(--bg-dark); position:relative; filter: <?= $is_pending ? 'grayscale(80%)' : 'none' ?>;">
                    <?php if(!empty($c['thumbnail'])): ?>
                        <img src="<?= htmlspecialchars($c['thumbnail']) ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:3.5rem; background:linear-gradient(135deg, var(--primary-color), var(--accent-color)); color:#fff;"><i class="fas fa-laptop-code"></i></div>
                    <?php endif; ?>
                    
                    <?php if($is_completed): ?>
                        <span class="badge bg-success" style="position:absolute; top:12px; right:12px; box-shadow:0 4px 12px rgba(0,0,0,0.2);">COMPLETED</span>
                    <?php elseif($is_pending): ?>
                        <span class="badge bg-warning text-dark" style="position:absolute; top:12px; right:12px;">PENDING APPROVAL</span>
                    <?php elseif($status === 'rejected' || $status === 'cancelled'): ?>
                        <span class="badge bg-danger" style="position:absolute; top:12px; right:12px;">CANCELLED</span>
                    <?php else: ?>
                        <span class="badge" style="position:absolute; top:12px; right:12px; background:var(--primary-color); color:var(--accent-color); border:1px solid rgba(184,243,90,0.4);">ENROLLED</span>
                    <?php endif; ?>
                </div>

                <div style="padding: 24px; display:flex; flex-direction:column; flex-grow:1;">
                    <h4 style="font-size:1.15rem; font-weight:800; color:var(--text-dark); margin:0 0 6px;"><?= htmlspecialchars($c['title']) ?></h4>
                    <div style="font-size:13px; color:var(--text-muted); margin-bottom:16px;"><i class="fas fa-chalkboard-teacher"></i> <?= htmlspecialchars($c['instructor_name'] ?? 'Graphicafix Team') ?></div>
                    
                    <?php if($is_active || $is_completed): ?>
                        <div style="margin-bottom:18px;">
                            <div style="display:flex; justify-content:space-between; font-size:12px; font-weight:700; color:var(--text-muted); margin-bottom:6px;">
                                <span>Curriculum Progress</span>
                                <span style="color:var(--primary-color); font-weight:800;"><?= $prog_pct ?>%</span>
                            </div>
                            <div style="width:100%; height:8px; background:var(--border-color); border-radius:10px; overflow:hidden;">
                                <div style="width:<?= $prog_pct ?>%; height:100%; background:var(--primary-color); border-radius:10px;"></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top:auto; display:flex; gap:10px;">
                        <?php if($is_active || $is_completed): ?>
                            <a href="course_player.php?course_id=<?= $c['id'] ?>" class="btn btn-primary-custom" style="flex:1; justify-content:center; padding:12px; font-size:13.5px; font-weight:800;"><i class="fas fa-play"></i> Enter Classroom</a>
                            <button class="btn btn-secondary-custom" style="padding:12px;" onclick="openAskModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['title'])) ?>')" title="Ask a Question"><i class="fas fa-question-circle"></i></button>
                        <?php elseif($is_pending): ?>
                            <div style="background:var(--bg-light); padding:12px; text-align:center; border-radius:10px; font-size:12.5px; color:var(--text-muted); width:100%; border:1px dashed var(--border-color);">
                                <i class="fas fa-clock"></i> Enrollment verification pending.
                            </div>
                        <?php else: ?>
                            <div style="background:#fef2f2; padding:12px; text-align:center; border-radius:10px; font-size:12.5px; color:#ef4444; width:100%; border:1px dashed #fca5a5;">
                                Registration inactive. Contact support.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
            <div class="col-12 text-center py-5">
                <div style="font-size:4rem; color:#cbd5e1; margin-bottom:16px;"><i class="fas fa-book-open"></i></div>
                <h4 style="font-weight:800; color:#334155;">No Courses Found</h4>
                <p style="color:#64748b;">You haven't registered for any courses yet.</p>
                <a href="<?= MAIN_SITE_URL ?>courses.php" class="btn btn-primary-custom mt-2">Browse Course Catalog</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Ask Modal -->
<div class="modal fade" id="askModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px; border:none; box-shadow:0 20px 50px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background:#024442; color:white; border-radius:20px 20px 0 0; padding:20px 24px;">
                <h5 class="modal-title" style="font-weight:800; color:white;"><i class="fas fa-question-circle" style="color:#b8f35a;"></i> Ask Instructor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="ask_query" value="1">
                    <input type="hidden" name="course_id" id="modal_course_id">
                    <p style="font-size:13.5px; color:#64748b; margin-bottom:16px;">Course: <strong id="modal_course_title" style="color:#0f172a;"></strong></p>
                    <div class="form-group">
                        <label style="font-weight:700; font-size:13px; color:#334155; margin-bottom:8px;">Your Question / Doubt</label>
                        <textarea name="query_text" class="form-control" rows="4" required placeholder="Describe your question in detail..."></textarea>
                    </div>
                </div>
                <div class="modal-footer px-4 pb-4 pt-0 border-0">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Send Question &rarr;</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAskModal(cid, ctitle) {
    document.getElementById('modal_course_id').value = cid;
    document.getElementById('modal_course_title').textContent = ctitle;
    var myModal = new bootstrap.Modal(document.getElementById('askModal'));
    myModal.show();
}
</script>

<?php include('dashboard_footer.php'); ?>
