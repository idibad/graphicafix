<?php
include('dashboard_header.php');

$user_id = intval($_SESSION['user_id'] ?? 0);

// Handle Create Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assign'])) {
    $cid = intval($_POST['course_id']);
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $due = trim($_POST['due_date']);
    
    $stmt = $conn->prepare("INSERT INTO assignments (course_id, teacher_id, title, description, due_date, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisss", $cid, $user_id, $title, $desc, $due);
    $stmt->execute();
    header("Location: teacher_assignments.php?created=1"); exit;
}

// Handle Grade Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_sub'])) {
    $sid = intval($_POST['submission_id']);
    $grade = trim($_POST['grade']);
    $fb = trim($_POST['feedback']);
    
    $stmt = $conn->prepare("UPDATE assignment_submissions SET grade = ?, feedback = ? WHERE id = ?");
    $stmt->bind_param("ssi", $grade, $fb, $sid);
    $stmt->execute();
    header("Location: teacher_assignments.php?graded=1"); exit;
}

// Fetch instructor courses
$my_courses_res = $conn->query("SELECT id, title FROM courses WHERE instructor_id = $user_id OR instructor_id IS NULL ORDER BY title ASC");

// Fetch assignments and submission counts
$assignments = $conn->query("
    SELECT a.*, c.title as course_title,
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as sub_cnt
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    WHERE a.teacher_id = $user_id OR c.instructor_id = $user_id
    ORDER BY a.due_date DESC
");

// Fetch recent submissions needing grading
$submissions = $conn->query("
    SELECT sub.*, a.title as assign_title, u.name as student_name, c.title as course_title
    FROM assignment_submissions sub
    JOIN assignments a ON sub.assignment_id = a.id
    JOIN courses c ON a.course_id = c.id
    JOIN users u ON sub.student_id = u.user_id
    WHERE a.teacher_id = $user_id OR c.instructor_id = $user_id
    ORDER BY sub.submitted_at DESC LIMIT 15
");
?>

<div class="height-100">
    <div class="main-header mb-4">
        <div>
            <h3 style="font-weight:800; color:#024442; margin:0;"><i class="fas fa-tasks"></i> Assignments & Grading Center</h3>
            <p style="color:#64748b; font-size:14px; margin:4px 0 0;">Create project deliverables and evaluate student uploaded zip files.</p>
        </div>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#newAssignModal"><i class="fas fa-plus"></i> Create Assignment</button>
    </div>

    <?php if(isset($_GET['created'])): ?><div class="alert alert-success fw-bold">Assignment created successfully!</div><?php endif; ?>
    <?php if(isset($_GET['graded'])): ?><div class="alert alert-success fw-bold">Student submission graded!</div><?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="d-card h-100">
                <h5 style="font-weight:800; color:#0f172a; margin-bottom:16px;">Student Submissions Queue</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr style="font-size:13px;">
                                <th>Student</th>
                                <th>Assignment</th>
                                <th>File</th>
                                <th>Grade</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($submissions && $submissions->num_rows > 0): while($sub = $submissions->fetch_assoc()): ?>
                                <tr style="font-size:13.5px;">
                                    <td><strong style="color:#0f172a;"><?= htmlspecialchars($sub['student_name']) ?></strong></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($sub['assign_title']) ?></span></td>
                                    <td>
                                        <a href="../admin/<?= htmlspecialchars($sub['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i> Zip</a>
                                    </td>
                                    <td>
                                        <?php if(!empty($sub['grade'])): ?>
                                            <span class="badge bg-success"><?= htmlspecialchars($sub['grade']) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Ungraded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary-custom" style="padding:4px 12px; font-size:12px;" 
                                            onclick="openGradeModal(<?= $sub['id'] ?>, '<?= htmlspecialchars(addslashes($sub['student_name'])) ?>', '<?= htmlspecialchars(addslashes($sub['grade'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($sub['feedback'] ?? '')) ?>')">
                                            Grade
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No student submissions in queue.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="d-card h-100">
                <h5 style="font-weight:800; color:#0f172a; margin-bottom:16px;">Active Course Assignments</h5>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <?php if($assignments && $assignments->num_rows > 0): while($a = $assignments->fetch_assoc()): ?>
                        <div style="padding:14px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                <strong style="font-size:14px; color:#0f172a;"><?= htmlspecialchars($a['title']) ?></strong>
                                <span class="badge" style="background:#024442; color:#b8f35a;"><?= $a['sub_cnt'] ?> Submissions</span>
                            </div>
                            <div style="font-size:12px; color:#64748b;">Course: <?= htmlspecialchars($a['course_title']) ?></div>
                            <div style="font-size:12px; color:#ef4444; margin-top:2px;">Due: <?= date('M d, Y', strtotime($a['due_date'])) ?></div>
                        </div>
                    <?php endwhile; else: ?>
                        <p class="text-muted">No assignments created yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Assignment Modal -->
<div class="modal fade" id="newAssignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px;">
            <div class="modal-header bg-dark text-white p-4">
                <h5 class="modal-title fw-bold"><i class="fas fa-tasks text-accent"></i> New Course Assignment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="create_assign" value="1">
                    <div class="form-group mb-3">
                        <label class="fw-bold fs-6 mb-2">Select Course</label>
                        <select name="course_id" class="form-control" required>
                            <?php if($my_courses_res && $my_courses_res->num_rows > 0): while($cr = $my_courses_res->fetch_assoc()): ?>
                                <option value="<?= $cr['id'] ?>"><?= htmlspecialchars($cr['title']) ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="fw-bold fs-6 mb-2">Assignment Title</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. Build Responsive Wireframe Layouts">
                    </div>
                    <div class="form-group mb-3">
                        <label class="fw-bold fs-6 mb-2">Due Date</label>
                        <input type="date" name="due_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="fw-bold fs-6 mb-2">Deliverable Requirements</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Explain what files the student must upload..."></textarea>
                    </div>
                </div>
                <div class="modal-footer p-4 pt-0 border-0">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Post Task &rarr;</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Grade Modal -->
<div class="modal fade" id="gradeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px;">
            <div class="modal-header" style="background:#024442; color:white; padding:20px;">
                <h5 class="modal-title fw-bold">Evaluate Student Work</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="grade_sub" value="1">
                    <input type="hidden" name="submission_id" id="modal_sub_id">
                    <p class="text-muted mb-3">Student: <strong id="modal_student_name" class="text-dark"></strong></p>
                    <div class="form-group mb-3">
                        <label class="fw-bold fs-6 mb-2">Letter Grade / Score</label>
                        <input type="text" name="grade" id="modal_grade_val" class="form-control" required placeholder="e.g. A+, 95/100, Pass">
                    </div>
                    <div class="form-group">
                        <label class="fw-bold fs-6 mb-2">Instructor Feedback</label>
                        <textarea name="feedback" id="modal_fb_val" class="form-control" rows="3" placeholder="Provide constructive feedback..."></textarea>
                    </div>
                </div>
                <div class="modal-footer p-4 pt-0 border-0">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Save Grade &rarr;</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openGradeModal(sid, sname, curGrade, curFb) {
    document.getElementById('modal_sub_id').value = sid;
    document.getElementById('modal_student_name').textContent = sname;
    document.getElementById('modal_grade_val').value = curGrade;
    document.getElementById('modal_fb_val').value = curFb;
    new bootstrap.Modal(document.getElementById('gradeModal')).show();
}
</script>

<?php include('dashboard_footer.php'); ?>
