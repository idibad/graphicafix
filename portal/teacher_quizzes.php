<?php
include('dashboard_header.php');

$user_id = intval($_SESSION['user_id'] ?? 0);

// Handle Create Quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quiz'])) {
    $cid = intval($_POST['course_id']);
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    
    $stmt = $conn->prepare("INSERT INTO course_quizzes (course_id, teacher_id, title, description, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiss", $cid, $user_id, $title, $desc);
    $stmt->execute();
    header("Location: teacher_quizzes.php?created=1"); exit;
}

// Handle Add Question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $qid = intval($_POST['quiz_id']);
    $qtext = trim($_POST['question_text']);
    $opa = trim($_POST['option_a']);
    $opb = trim($_POST['option_b']);
    $opc = trim($_POST['option_c']);
    $opd = trim($_POST['option_d']);
    $cop = trim($_POST['correct_option']); // A, B, C, D
    
    $stmt = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $qid, $qtext, $opa, $opb, $opc, $opd, $cop);
    $stmt->execute();
    header("Location: teacher_quizzes.php?added=1"); exit;
}

// Fetch instructor courses
$my_courses_res = $conn->query("SELECT id, title FROM courses WHERE instructor_id = $user_id OR instructor_id IS NULL ORDER BY title ASC");

// Fetch quizzes
$quizzes = $conn->query("
    SELECT q.*, c.title as course_title,
        (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as q_cnt,
        (SELECT COUNT(*) FROM student_quiz_results WHERE quiz_id = q.id) as attempt_cnt
    FROM course_quizzes q
    JOIN courses c ON q.course_id = c.id
    WHERE q.teacher_id = $user_id OR c.instructor_id = $user_id
    ORDER BY q.id DESC
");
?>

<div class="height-100">
    <div class="main-header mb-4">
        <div>
            <h3 style="font-weight:800; color:#024442; margin:0;"><i class="fas fa-question-circle"></i> Course Quizzes & Assessments</h3>
            <p style="color:#64748b; font-size:14px; margin:4px 0 0;">Create multiple-choice assessments and populate test banks.</p>
        </div>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#newQuizModal"><i class="fas fa-plus"></i> New Quiz</button>
    </div>

    <?php if(isset($_GET['created'])): ?><div class="alert alert-success fw-bold">Assessment created! Add questions below.</div><?php endif; ?>
    <?php if(isset($_GET['added'])): ?><div class="alert alert-success fw-bold">Question added to quiz!</div><?php endif; ?>

    <div class="row g-4">
        <?php if($quizzes && $quizzes->num_rows > 0): while($qz = $quizzes->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-4">
                <div class="d-card h-100 d-flex flex-column" style="padding:24px; border:1px solid #cbd5e1;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($qz['course_title']) ?></span>
                        <span class="badge bg-info text-white"><?= $qz['q_cnt'] ?> Questions</span>
                    </div>

                    <h4 style="font-weight:800; color:#0f172a; margin:0 0 6px;"><?= htmlspecialchars($qz['title']) ?></h4>
                    <p style="font-size:13px; color:#64748b; margin-bottom:16px; flex:1;"><?= htmlspecialchars($qz['description'] ?? '') ?></p>

                    <div style="background:#f8fafc; padding:10px; border-radius:8px; font-size:12px; color:#475569; margin-bottom:16px;">
                        <i class="fas fa-users text-success"></i> Student Attempts: <strong><?= $qz['attempt_cnt'] ?></strong>
                    </div>

                    <button class="btn btn-primary-custom" style="width:100%; justify-content:center;" onclick="openAddModal(<?= $qz['id'] ?>, '<?= htmlspecialchars(addslashes($qz['title'])) ?>')"><i class="fas fa-plus-circle"></i> Add MCQ Question</button>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div class="col-12 text-center py-5 text-muted">
                <i class="fas fa-question fs-1 mb-3"></i>
                <h4>No Quizzes Created</h4>
                <p>Build interactive tests to assess student learning.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- New Quiz Modal -->
<div class="modal fade" id="newQuizModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px;">
            <div class="modal-header bg-dark text-white p-4">
                <h5 class="modal-title fw-bold">Create Assessment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="create_quiz" value="1">
                    <div class="form-group mb-3">
                        <label class="fw-bold fs-6 mb-2">Select Course</label>
                        <select name="course_id" class="form-control" required>
                            <?php if($my_courses_res && $my_courses_res->num_rows > 0): while($cr = $my_courses_res->fetch_assoc()): ?>
                                <option value="<?= $cr['id'] ?>"><?= htmlspecialchars($cr['title']) ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="fw-bold fs-6 mb-2">Quiz Title</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. Module 1 Mastery Check">
                    </div>
                    <div class="form-group">
                        <label class="fw-bold fs-6 mb-2">Instructions / Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Explain passing threshold..."></textarea>
                    </div>
                </div>
                <div class="modal-footer p-4 pt-0 border-0">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Save Quiz &rarr;</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Question Modal -->
<div class="modal fade" id="addQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px;">
            <div class="modal-header" style="background:#024442; color:white; padding:20px;">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus"></i> Add Question to: <span id="modal_q_title" style="color:#b8f35a;"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="add_question" value="1">
                    <input type="hidden" name="quiz_id" id="modal_quiz_id">
                    
                    <div class="form-group mb-3">
                        <label class="fw-bold fs-6 mb-2">Question Prompt</label>
                        <textarea name="question_text" class="form-control" rows="3" required placeholder="Enter question text..."></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="fw-bold fs-6 mb-1 text-primary">Option A</label>
                            <input type="text" name="option_a" class="form-control" required placeholder="Answer choice A">
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold fs-6 mb-1 text-primary">Option B</label>
                            <input type="text" name="option_b" class="form-control" required placeholder="Answer choice B">
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold fs-6 mb-1 text-primary">Option C</label>
                            <input type="text" name="option_c" class="form-control" required placeholder="Answer choice C">
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold fs-6 mb-1 text-primary">Option D</label>
                            <input type="text" name="option_d" class="form-control" required placeholder="Answer choice D">
                        </div>
                    </div>

                    <div class="form-group bg-light p-3 rounded border">
                        <label class="fw-bold fs-6 mb-2 text-success"><i class="fas fa-check-circle"></i> Correct Answer Key</label>
                        <select name="correct_option" class="form-control" required>
                            <option value="A">Option A is Correct</option>
                            <option value="B">Option B is Correct</option>
                            <option value="C">Option C is Correct</option>
                            <option value="D">Option D is Correct</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer p-4 pt-0 border-0">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Add Question &rarr;</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddModal(qid, qtitle) {
    document.getElementById('modal_quiz_id').value = qid;
    document.getElementById('modal_q_title').textContent = qtitle;
    new bootstrap.Modal(document.getElementById('addQuestionModal')).show();
}
</script>

<?php include('dashboard_footer.php'); ?>
