<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

include 'dashboard_header.php';

// We REMOVED the $_SESSION['email'] and $_SESSION['user_id'] overrides here.
// They are already safely loaded from the database inside dashboard_header.php!

// Safely escape and format the email for the database
$safe_email = mysqli_real_escape_string($conn, strtolower(trim($email)));

// Handle Ask Question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ask_query'])) {
    $cid = intval($_POST['course_id']);
    $query_text = trim($_POST['query_text']);
    $stmt = $conn->prepare("INSERT INTO course_queries (course_id, student_id, query_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $cid, $user_id, $query_text);
    $stmt->execute();
    header("Location: student_courses.php?query_sent=1"); exit;
}

// Fetch ALL Enrollments using highly forgiving email matching
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
    <div class="main-header" style="margin-bottom: 20px;">
        <h3><i class="fas fa-book-open"></i> My Learning</h3>
        <p style="color:#666; font-size:14px;">Access your active courses or check the status of your registrations.</p>
    </div>

    <?php if(isset($_GET['query_sent'])): ?>
        <div style="background:#10b981; color:#fff; padding:10px 20px; border-radius:8px; margin-bottom:20px; font-weight:600;"><i class="fas fa-check-circle"></i> Question sent to your instructor!</div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if($my_courses && $my_courses->num_rows > 0): while($c = $my_courses->fetch_assoc()): 
            
            // Force status to lowercase to avoid 'Active' vs 'active' bugs
            $status = strtolower(trim($c['enroll_status']));
            $is_active = ($status === 'active');
            $is_completed = ($status === 'completed');
            $is_pending = in_array($status, ['pending', 'reviewing']);
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="d-card" style="padding:0; overflow:hidden; height:100%; display:flex; flex-direction:column; border:1px solid <?= $is_active ? 'var(--primary)' : '#eee' ?>;">
                <div style="height: 160px; background: #e0e0e0; position:relative; filter: <?= $is_pending ? 'grayscale(80%)' : 'none' ?>;">
                    <?php if(!empty($c['thumbnail'])): ?>
                        <img src="<?= htmlspecialchars($c['thumbnail']) ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:3rem; background:var(--primary);"><i class="fas fa-graduation-cap"></i></div>
                    <?php endif; ?>
                    
                    <?php if($is_completed): ?>
                        <span style="position:absolute; top:10px; right:10px; background:#10b981; color:#fff; font-size:11px; font-weight:800; padding:4px 10px; border-radius:20px;">COMPLETED</span>
                    <?php elseif($is_pending): ?>
                        <span style="position:absolute; top:10px; right:10px; background:#fef9c3; color:#a16207; font-size:11px; font-weight:800; padding:4px 10px; border-radius:20px;">PENDING APPROVAL</span>
                    <?php elseif($status === 'rejected' || $status === 'cancelled'): ?>
                        <span style="position:absolute; top:10px; right:10px; background:#fee2e2; color:#ef4444; font-size:11px; font-weight:800; padding:4px 10px; border-radius:20px;">CANCELLED</span>
                    <?php else: ?>
                        <span style="position:absolute; top:10px; right:10px; background:#e0f2fe; color:#0284c7; font-size:11px; font-weight:800; padding:4px 10px; border-radius:20px;">ACTIVE</span>
                    <?php endif; ?>
                </div>
                <div style="padding: 20px; display:flex; flex-direction:column; flex-grow:1;">
                    <h4 style="font-size:1.1rem; font-weight:700; color:var(--primary); margin-bottom:5px;"><?= htmlspecialchars($c['title']) ?></h4>
                    <div style="font-size:12px; color:#888; margin-bottom:15px;">Instructor: <?= htmlspecialchars($c['instructor_name'] ?? 'Graphicafix') ?></div>
                    
                    <div style="margin-top:auto; display:flex; gap:10px;">
                        <?php if($is_active || $is_completed): ?>
                            <button class="btn btn-primary-custom" style="flex:1; font-size:13px; padding:10px;">Continue Learning</button>
                            <button class="btn btn-secondary-custom" style="padding:10px;" onclick="openAskModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['title'])) ?>')" title="Ask a Question"><i class="fas fa-question-circle"></i></button>
                        <?php elseif($is_pending): ?>
                            <div style="background:#f9fbfc; padding:10px; text-align:center; border-radius:8px; font-size:12px; color:#666; width:100%; border:1px dashed #ccc;">
                                Your registration is being reviewed by our team.
                            </div>
                        <?php else: ?>
                            <div style="background:#fef2f2; padding:10px; text-align:center; border-radius:8px; font-size:12px; color:#ef4444; width:100%; border:1px dashed #fca5a5;">
                                Registration issue. Please contact support.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
            <div class="col-12 text-center" style="padding: 50px 0; color:#888;">
                <p>You haven't registered for any courses yet. <br><small>(Logged in as: <strong><?= htmlspecialchars($safe_email) ?></strong>)</small></p>
                <a href="courses.php" class="btn btn-primary-custom" style="display:inline-block; margin-top:10px; text-decoration:none;">Browse Courses</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="askModal" class="modal-overlay" onclick="if(event.target===this)closeAskModal()">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h4><div class="modal-header-icon"><i class="fas fa-comment"></i></div><span>Ask Instructor</span></h4>
            <button class="modal-close" onclick="closeAskModal()">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="ask_query" value="1">
                <input type="hidden" name="course_id" id="askCourseId">
                <p style="font-size:13px; color:#666; margin-bottom:15px;">Course: <strong id="askCourseTitle" style="color:var(--primary);"></strong></p>
                <textarea name="query_text" required placeholder="What are you struggling with?" style="width:100%; padding:15px; border:1.5px solid #e0e0e0; border-radius:10px; font-size:14px; outline:none; resize:vertical; min-height:100px; font-family:inherit;"></textarea>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary-custom" style="width:100%;">Send Question</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAskModal(id, title) {
    document.getElementById('askCourseId').value = id;
    document.getElementById('askCourseTitle').textContent = title;
    document.getElementById('askModal').classList.add('active');
}
function closeAskModal() { document.getElementById('askModal').classList.remove('active'); }
</script>

<?php include('dashboard_footer.php'); ?>