<?php
include 'dashboard_header.php';

$user_id = intval($_SESSION['user_id'] ?? 0);
$quiz_id = intval($_GET['quiz_id'] ?? 0);

if (!$quiz_id) { header("Location: student_courses.php"); exit; }

$qz_res = $conn->query("SELECT q.*, c.title as course_title FROM course_quizzes q JOIN courses c ON q.course_id = c.id WHERE q.id = $quiz_id");
$quiz = $qz_res ? $qz_res->fetch_assoc() : null;
if (!$quiz) die("Quiz not found.");

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    $q_res = $conn->query("SELECT id, correct_option FROM quiz_questions WHERE quiz_id = $quiz_id");
    $total = 0; $score = 0;
    while ($row = $q_res->fetch_assoc()) {
        $total++;
        $qid = $row['id'];
        $ans = $_POST['ans_' . $qid] ?? '';
        if ($ans === $row['correct_option']) $score++;
    }
    $stmt = $conn->prepare("INSERT INTO student_quiz_results (quiz_id, student_id, score, total_questions, taken_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiii", $quiz_id, $user_id, $score, $total);
    $stmt->execute();
    header("Location: take_quiz.php?quiz_id=$quiz_id&result=$score&tot=$total"); exit;
}

$questions = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id = $quiz_id ORDER BY id ASC");
?>

<div class="height-100">
    <div class="d-card mb-4 bg-dark text-white p-4">
        <a href="course_player.php?course_id=<?= $quiz['course_id'] ?>&tab=quizzes" class="btn btn-sm btn-outline-light mb-3">&larr; Back to Classroom</a>
        <span class="badge bg-accent text-dark fw-bold mb-2"><?= htmlspecialchars($quiz['course_title']) ?></span>
        <h3 class="fw-bold text-white"><?= htmlspecialchars($quiz['title']) ?></h3>
        <p class="text-muted mb-0"><?= htmlspecialchars($quiz['description'] ?? '') ?></p>
    </div>

    <?php if(isset($_GET['result'])): 
        $sc = intval($_GET['result']); $t = intval($_GET['tot']);
        $pct = $t > 0 ? round(($sc/$t)*100) : 0;
        $passed = $pct >= 70;
    ?>
        <div class="d-card text-center py-5 <?= $passed ? 'bg-light border-success' : 'bg-light border-danger' ?>" style="border:2px solid;">
            <div style="font-size:4rem; color:<?= $passed ? '#10b981' : '#ef4444' ?>;"><i class="fas <?= $passed ? 'fa-trophy' : 'fa-times-circle' ?>"></i></div>
            <h2 class="fw-bold mt-2"><?= $passed ? 'Assessment Passed!' : 'Assessment Failed' ?></h2>
            <h1 class="display-3 fw-bold my-3" style="color:#024442;"><?= $sc ?> / <?= $t ?> <span class="fs-4 text-muted">(<?= $pct ?>%)</span></h1>
            <p class="text-muted mb-4"><?= $passed ? 'Great job! You have demonstrated solid comprehension of this module.' : 'You need at least 70% to pass. Review the video lectures and try again.' ?></p>
            <a href="take_quiz.php?quiz_id=<?= $quiz_id ?>" class="btn btn-primary-custom"><i class="fas fa-redo"></i> Retake Quiz</a>
        </div>
    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="submit_quiz" value="1">
            <div style="display:flex; flex-direction:column; gap:20px;">
                <?php if($questions && $questions->num_rows > 0): $idx = 1; while($q = $questions->fetch_assoc()): $qid = $q['id']; ?>
                    <div class="d-card p-4">
                        <h5 class="fw-bold text-dark mb-3"><span class="text-primary me-2"><?= $idx++ ?>.</span> <?= htmlspecialchars($q['question_text']) ?></h5>
                        <div class="row g-3">
                            <?php foreach(['A'=>$q['option_a'], 'B'=>$q['option_b'], 'C'=>$q['option_c'], 'D'=>$q['option_d']] as $opt => $val): ?>
                                <div class="col-md-6">
                                    <label class="p-3 border rounded-3 d-flex align-items-center gap-3 w-100 cursor-pointer bg-light hover-shadow transition">
                                        <input type="radio" name="ans_<?= $qid ?>" value="<?= $opt ?>" required class="form-check-input mt-0">
                                        <span class="fw-bold text-muted"><?= $opt ?>)</span>
                                        <span class="text-dark fw-semibold"><?= htmlspecialchars($val) ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary-custom px-5 py-3 fs-5 fw-bold"><i class="fas fa-paper-plane"></i> Submit Assessment</button>
                    </div>
                <?php else: ?>
                    <div class="d-card text-center py-5 text-muted">No questions in this quiz yet.</div>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include('dashboard_footer.php'); ?>
