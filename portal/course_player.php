<?php
include('dashboard_header.php');

$user_id = intval($_SESSION['user_id'] ?? 0);
$course_id = intval($_GET['course_id'] ?? 0);

if (!$course_id) {
    echo "<script>window.location='student_courses.php';</script>"; exit;
}

// Fetch course details
$c_res = $conn->query("SELECT c.*, u.name as instructor_name FROM courses c LEFT JOIN users u ON c.instructor_id = u.user_id WHERE c.id = $course_id");
$course = $c_res ? $c_res->fetch_assoc() : null;
if (!$course) {
    die("Course not found");
}

// Handle Q&A post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_qa'])) {
    $q_text = trim($_POST['query_text']);
    if ($q_text) {
        $stmt = $conn->prepare("INSERT INTO course_queries (course_id, student_id, query_text) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $course_id, $user_id, $q_text);
        $stmt->execute();
        header("Location: course_player.php?course_id=$course_id&tab=qa&sent=1"); exit;
    }
}

// Fetch all approved lectures
$lectures_res = $conn->query("SELECT * FROM recorded_lectures WHERE course_id = $course_id AND status = 'approved' ORDER BY id ASC");
$lectures = [];
while ($row = $lectures_res->fetch_assoc()) {
    $lectures[] = $row;
}

// Fetch completed lectures for this student
$completed_ids = [];
$prog_res = $conn->query("SELECT lecture_id FROM student_video_progress WHERE course_id = $course_id AND student_id = $user_id AND is_completed = 1");
if ($prog_res) {
    while ($p = $prog_res->fetch_assoc()) {
        $completed_ids[] = intval($p['lecture_id']);
    }
}

// Determine current active lecture
$active_lec_id = intval($_GET['lecture_id'] ?? 0);
$current_lec = null;

if (empty($lectures)) {
    $no_lectures = true;
} else {
    $no_lectures = false;
    if ($active_lec_id > 0) {
        foreach ($lectures as $l) {
            if (intval($l['id']) === $active_lec_id) {
                $current_lec = $l; break;
            }
        }
    }
    // If not found or not specified, pick first uncompleted or very first
    if (!$current_lec) {
        foreach ($lectures as $l) {
            if (!in_array(intval($l['id']), $completed_ids)) {
                $current_lec = $l; break;
            }
        }
        if (!$current_lec) $current_lec = $lectures[0];
    }
}

$tot_cnt = count($lectures);
$com_cnt = count($completed_ids);
$prog_pct = $tot_cnt > 0 ? round(($com_cnt / $tot_cnt) * 100) : 0;
$is_cur_completed = $current_lec ? in_array(intval($current_lec['id']), $completed_ids) : false;

// Fetch resources
$res_query = $conn->query("SELECT * FROM course_resources WHERE course_id = $course_id ORDER BY id DESC");

// Fetch queries
$qa_query = $conn->query("SELECT q.*, u.name as sname FROM course_queries q JOIN users u ON q.student_id = u.user_id WHERE q.course_id = $course_id ORDER BY q.created_at DESC");

// Fetch quizzes
$quizzes_res = $conn->query("SELECT * FROM course_quizzes WHERE course_id = $course_id ORDER BY id DESC");
?>

<style>
    .player-container {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 24px;
        align-items: start;
    }
    @media (max-width: 1199px) {
        .player-container { grid-template-columns: 1fr; }
    }
    .video-frame-box {
        background: #000;
        border-radius: 16px;
        overflow: hidden;
        aspect-ratio: 16 / 9;
        position: relative;
        box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        border: 1px solid rgba(255,255,255,0.1);
    }
    .playlist-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
        max-height: calc(100vh - 120px);
        position: sticky;
        top: 90px;
    }
    .playlist-header {
        background: #0f172a;
        color: white;
        padding: 18px 20px;
        font-weight: 800;
        font-size: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .playlist-scroll {
        overflow-y: auto;
        flex: 1;
    }
    .lec-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 18px;
        border-bottom: 1px solid #f1f5f9;
        text-decoration: none;
        color: #334155;
        transition: all 0.2s ease;
    }
    .lec-item:hover { background: #f8fafc; color: #0f172a; }
    .lec-item.active {
        background: rgba(184, 243, 90, 0.15);
        border-left: 4px solid #024442;
        color: #024442;
        font-weight: 800;
    }
    .lec-status {
        width: 26px; height: 26px;
        border-radius: 50%;
        border: 2px solid #cbd5e1;
        display: flex; align-items: center; justify-content: center;
        font-size: 12px; color: transparent;
        flex-shrink: 0;
        transition: all 0.2s;
    }
    .lec-item.completed .lec-status {
        background: #10b981;
        border-color: #10b981;
        color: white;
    }
    .lec-title { font-size: 13.5px; flex: 1; line-height: 1.4; }
    
    .tab-nav-custom {
        display: flex;
        gap: 8px;
        border-bottom: 2px solid #e2e8f0;
        margin-top: 24px;
        margin-bottom: 20px;
    }
    .tab-btn-custom {
        background: none;
        border: none;
        padding: 12px 20px;
        font-size: 14px;
        font-weight: 700;
        color: #64748b;
        cursor: pointer;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        transition: all 0.2s;
    }
    .tab-btn-custom:hover { color: #0f172a; }
    .tab-btn-custom.active {
        color: #024442;
        border-color: #024442;
    }
    
    .cert-banner {
        background: linear-gradient(135deg, #d4af37 0%, #aa8418 100%);
        color: #fff;
        padding: 16px 24px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
        box-shadow: 0 10px 25px rgba(212,175,55,0.3);
        animation: pulseGold 2s infinite;
    }
    @keyframes pulseGold {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.01); }
    }
</style>

<div class="height-100">
    <!-- Top Navigation Bar -->
    <div class="d-card mb-4" style="padding:16px 24px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px;">
        <div style="display:flex; align-items:center; gap:16px;">
            <a href="student_courses.php" class="btn btn-secondary-custom" style="padding:8px 16px; font-size:13px;"><i class="fas fa-arrow-left"></i> My Courses</a>
            <div>
                <h4 style="font-size:1.2rem; font-weight:800; color:#0f172a; margin:0;"><?= htmlspecialchars($course['title']) ?></h4>
                <div style="font-size:12px; color:#64748b;">Instructor: <?= htmlspecialchars($course['instructor_name'] ?? 'Graphicafix Team') ?></div>
            </div>
        </div>

        <!-- Completion Indicator -->
        <div style="display:flex; align-items:center; gap:16px; min-width:260px;">
            <div style="flex:1;">
                <div style="display:flex; justify-content:space-between; font-size:11.5px; font-weight:800; color:#334155; margin-bottom:4px;">
                    <span>Course Completion</span>
                    <span id="topProgText"><?= $prog_pct ?>%</span>
                </div>
                <div style="width:100%; height:10px; background:#e2e8f0; border-radius:10px; overflow:hidden;">
                    <div id="topProgBar" style="width:<?= $prog_pct ?>%; height:100%; background:#10b981; border-radius:10px; transition:width .4s;"></div>
                </div>
            </div>

            <?php if($prog_pct >= 100 && $tot_cnt > 0): ?>
                <a href="student_certificates.php?claim=<?= $course_id ?>" class="btn btn-primary-custom" style="background:#d4af37; color:#fff; font-weight:800; padding:10px 18px; box-shadow:0 4px 12px rgba(212,175,55,0.4);"><i class="fas fa-certificate"></i> Get Certificate</a>
            <?php else: ?>
                <button class="btn btn-secondary-custom" disabled style="opacity:0.6; padding:10px 18px; font-size:12px;"><i class="fas fa-lock"></i> Certificate</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if($no_lectures): ?>
        <div class="d-card text-center py-5">
            <div style="font-size:3.5rem; color:#cbd5e1; margin-bottom:16px;"><i class="fas fa-video-slash"></i></div>
            <h3>No Videos Uploaded Yet</h3>
            <p style="color:#64748b;">The instructor is currently preparing curriculum materials for this course.</p>
        </div>
    <?php else: ?>

        <div class="player-container">
            <!-- Left Main Area -->
            <div>
                <div class="video-frame-box">
                    <?php 
                        $vtype = strtolower(trim($current_lec['video_type'] ?? 'mp4'));
                        $vurl  = trim($current_lec['video_url'] ?? '');
                        $fpath = trim($current_lec['file_path'] ?? '');
                        $vid   = trim($current_lec['vimeo_id'] ?? '');

                        // Extract Vimeo ID from URL if vimeo_id column is empty
                        if ($vtype === 'vimeo' || preg_match('/vimeo\.com/i', $fpath . $vurl)) {
                            $target = !empty($vid) ? $vid : (!empty($vurl) ? $vurl : $fpath);
                            if (preg_match('/(?:vimeo\.com\/|video\/)(\d+)/i', $target, $m)) {
                                $vid = $m[1];
                            } elseif (is_numeric($target)) {
                                $vid = $target;
                            }
                        }
                    ?>

                    <?php if(!empty($vid)): ?>
                        <!-- Official Vimeo Player iframe -->
                        <div id="vimeoEmbedWrap" style="width:100%; height:100%;">
                            <iframe src="https://player.vimeo.com/video/<?= htmlspecialchars($vid) ?>?autoplay=1&title=0&byline=0&portrait=0" 
                                style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;" 
                                allow="autoplay; fullscreen; picture-in-picture" allowfullscreen id="vimeoIframe"></iframe>
                        </div>
                    <?php elseif($vtype === 'external' && preg_match('/(?:drive\.google|dailymotion|loom|bunny|player|embed|preview)/i', $vurl)): ?>
                        <!-- External Free Cloud CDN Iframe Embed -->
                        <div style="width:100%; height:100%; position:absolute; inset:0;">
                            <iframe src="<?= htmlspecialchars($vurl) ?>" style="width:100%; height:100%; border:none;" allow="autoplay; fullscreen" allowfullscreen></iframe>
                        </div>
                    <?php else: ?>
                        <!-- HTML5 Video Player -->
                        <video id="html5VideoPlayer" controls autoplay style="width:100%; height:100%; background:#000;">
                            <source src="<?= htmlspecialchars(!empty($vurl) ? $vurl : (preg_match('/^http/i', $fpath) ? $fpath : '../admin/' . $fpath)) ?>">
                            Your browser does not support HTML5 video.
                        </video>
                    <?php endif; ?>
                </div>

                <!-- Video Controls & Mark Complete Bar -->
                <div class="d-card mt-3" style="padding:20px 24px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px;">
                    <div>
                        <span style="font-size:12px; font-weight:800; color:#024442; text-transform:uppercase; letter-spacing:0.5px;">Lesson <?= array_search($current_lec, $lectures) + 1 ?> of <?= $tot_cnt ?></span>
                        <h3 style="font-size:1.3rem; font-weight:800; color:#0f172a; margin:4px 0 0;"><?= htmlspecialchars($current_lec['title']) ?></h3>
                    </div>

                    <div>
                        <button type="button" id="markCompleteBtn" onclick="toggleComplete(<?= $current_lec['id'] ?>, <?= $course_id ?>)" 
                            class="btn <?= $is_cur_completed ? 'btn-success' : 'btn-secondary-custom' ?>" 
                            style="padding:12px 24px; font-weight:800; font-size:14px; <?= $is_cur_completed ? 'background:#10b981; color:white; border:none;' : '' ?>">
                            <i class="fas <?= $is_cur_completed ? 'fa-check-circle' : 'fa-circle' ?>" id="completeIcon"></i> 
                            <span id="completeBtnText"><?= $is_cur_completed ? 'Completed' : 'Mark as Complete' ?></span>
                        </button>
                    </div>
                </div>

                <!-- Classroom Tabs Navigation -->
                <div class="tab-nav-custom">
                    <button class="tab-btn-custom active" onclick="switchTab('overview')"><i class="fas fa-file-alt"></i> Lesson Overview</button>
                    <button class="tab-btn-custom" onclick="switchTab('resources')"><i class="fas fa-folder-open"></i> Resources (<?= $res_query ? $res_query->num_rows : 0 ?>)</button>
                    <button class="tab-btn-custom" onclick="switchTab('qa')"><i class="fas fa-comments"></i> Q&A Discussion</button>
                    <button class="tab-btn-custom" onclick="switchTab('quizzes')"><i class="fas fa-tasks"></i> Course Quizzes</button>
                </div>

                <!-- Tab 1: Overview -->
                <div id="tab_overview" class="tab-pane-custom">
                    <div class="d-card" style="padding:24px;">
                        <h4 style="font-weight:800; color:#024442; margin-bottom:12px;">Lesson Notes</h4>
                        <?php if(!empty($current_lec['description'])): ?>
                            <div style="color:#475569; font-size:14.5px; line-height:1.7;"><?= nl2br(htmlspecialchars($current_lec['description'])) ?></div>
                        <?php else: ?>
                            <p class="text-muted">No specific instructor notes provided for this lecture.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tab 2: Resources -->
                <div id="tab_resources" class="tab-pane-custom" style="display:none;">
                    <div class="d-card" style="padding:24px;">
                        <h4 style="font-weight:800; color:#024442; margin-bottom:16px;"><i class="fas fa-download"></i> Downloadable Course Assets</h4>
                        <div style="display:flex; flex-direction:column; gap:12px;">
                            <?php if($res_query && $res_query->num_rows > 0): while($r = $res_query->fetch_assoc()): ?>
                                <div style="display:flex; align-items:center; justify-content:space-between; padding:16px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc;">
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <div style="width:40px; height:40px; border-radius:10px; background:#024442; color:#b8f35a; display:flex; align-items:center; justify-content:center; font-size:18px;"><i class="fas fa-file-archive"></i></div>
                                        <span style="font-weight:700; font-size:14.5px; color:#0f172a;"><?= htmlspecialchars($r['title']) ?></span>
                                    </div>
                                    <a href="<?= htmlspecialchars(preg_match('/^http/i', $r['file_path']) ? $r['file_path'] : '../admin/' . $r['file_path']) ?>" target="_blank" class="btn btn-primary-custom" style="padding:8px 18px; font-size:13px;"><i class="fas fa-download"></i> Download</a>
                                </div>
                            <?php endwhile; else: ?>
                                <p class="text-muted">No downloadable assets attached to this course.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Q&A -->
                <div id="tab_qa" class="tab-pane-custom" style="display:none;">
                    <div class="d-card mb-4" style="padding:24px;">
                        <h4 style="font-weight:800; color:#024442; margin-bottom:16px;">Ask a Question</h4>
                        <?php if(isset($_GET['sent'])): ?>
                            <div class="alert alert-success">Your question was posted! The instructor will reply shortly.</div>
                        <?php endif; ?>
                        <form method="POST">
                            <input type="hidden" name="post_qa" value="1">
                            <div class="form-group mb-3">
                                <textarea name="query_text" class="form-control" rows="3" required placeholder="Confused about something in this video? Ask here..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary-custom">Submit Doubt</button>
                        </form>
                    </div>

                    <div class="d-card" style="padding:24px;">
                        <h4 style="font-weight:800; color:#024442; margin-bottom:16px;">Community Discussions</h4>
                        <div style="display:flex; flex-direction:column; gap:16px;">
                            <?php if($qa_query && $qa_query->num_rows > 0): while($qa = $qa_query->fetch_assoc()): ?>
                                <div style="padding:16px; border:1px solid #f1f5f9; border-radius:14px; background:#fff;">
                                    <div style="display:flex; justify-content:space-between; font-size:12px; color:#64748b; margin-bottom:6px;">
                                        <strong style="color:#0f172a;"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($qa['sname']) ?></strong>
                                        <span><?= date('M d, Y', strtotime($qa['created_at'])) ?></span>
                                    </div>
                                    <div style="font-size:14px; color:#334155; margin-bottom:10px; font-weight:600;"><?= nl2br(htmlspecialchars($qa['query_text'])) ?></div>
                                    
                                    <?php if(!empty($qa['answer_text'])): ?>
                                        <div style="background:#f0fdf4; border-left:3px solid #10b981; padding:12px; border-radius:0 8px 8px 0; margin-top:8px;">
                                            <div style="font-size:11.5px; font-weight:800; color:#166534; margin-bottom:4px;"><i class="fas fa-check-circle"></i> INSTRUCTOR REPLY</div>
                                            <div style="font-size:13.5px; color:#15803d;"><?= nl2br(htmlspecialchars($qa['answer_text'])) ?></div>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Pending instructor reply</span>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; else: ?>
                                <p class="text-muted">No questions asked yet. Be the first!</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tab 4: Quizzes -->
                <div id="tab_quizzes" class="tab-pane-custom" style="display:none;">
                    <div class="d-card" style="padding:24px;">
                        <h4 style="font-weight:800; color:#024442; margin-bottom:16px;"><i class="fas fa-tasks"></i> Interactive Assessments</h4>
                        <div style="display:flex; flex-direction:column; gap:16px;">
                            <?php if($quizzes_res && $quizzes_res->num_rows > 0): while($qz = $quizzes_res->fetch_assoc()): 
                                $qid = intval($qz['id']);
                                $q_cnt = $conn->query("SELECT COUNT(*) as c FROM quiz_questions WHERE quiz_id = $qid")->fetch_assoc()['c'] ?? 0;
                                $s_res = $conn->query("SELECT score, total_questions FROM student_quiz_results WHERE quiz_id = $qid AND student_id = $user_id ORDER BY id DESC LIMIT 1");
                                $best = $s_res ? $s_res->fetch_assoc() : null;
                            ?>
                                <div style="padding:20px; border:1px solid #e2e8f0; border-radius:14px; background:#fff; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px;">
                                    <div>
                                        <h5 style="font-weight:800; color:#0f172a; margin:0 0 6px;"><?= htmlspecialchars($qz['title']) ?></h5>
                                        <p style="font-size:13px; color:#64748b; margin:0;"><?= htmlspecialchars($qz['description'] ?? "$q_cnt multiple choice questions") ?></p>
                                    </div>
                                    <div>
                                        <?php if($best): ?>
                                            <span style="font-weight:800; color:#10b981; margin-right:12px;">Score: <?= $best['score'] ?>/<?= $best['total_questions'] ?></span>
                                            <a href="take_quiz.php?quiz_id=<?= $qid ?>" class="btn btn-secondary-custom" style="padding:8px 16px; font-size:13px;"><i class="fas fa-redo"></i> Retake</a>
                                        <?php else: ?>
                                            <a href="take_quiz.php?quiz_id=<?= $qid ?>" class="btn btn-primary-custom" style="padding:10px 20px; font-size:13px;"><i class="fas fa-play"></i> Start Quiz</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; else: ?>
                                <p class="text-muted">No assessments created for this course.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Playlist Accordion -->
            <div class="playlist-card">
                <div class="playlist-header">
                    <span>Course Content (<?= $tot_cnt ?> videos)</span>
                    <span style="color:#b8f35a; font-size:12px;"><?= $com_cnt ?>/<?= $tot_cnt ?> Done</span>
                </div>
                <div class="playlist-scroll">
                    <?php foreach($lectures as $index => $l): 
                        $is_com = in_array(intval($l['id']), $completed_ids);
                        $is_act = ($current_lec && intval($l['id']) === intval($current_lec['id']));
                    ?>
                        <a href="course_player.php?course_id=<?= $course_id ?>&lecture_id=<?= $l['id'] ?>" 
                           class="lec-item <?= $is_act ? 'active' : '' ?> <?= $is_com ? 'completed' : '' ?>">
                            <div class="lec-status"><i class="fas fa-check"></i></div>
                            <div class="lec-title">
                                <strong style="font-size:12px; display:block; color:#94a3b8; font-weight:700;">Lesson <?= $index + 1 ?></strong>
                                <?= htmlspecialchars($l['title']) ?>
                            </div>
                            <?php if($is_act): ?>
                                <div style="color:#024442; font-size:14px;"><i class="fas fa-volume-up"></i></div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<script>
function switchTab(tname) {
    document.querySelectorAll('.tab-pane-custom').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn-custom').forEach(el => el.classList.remove('active'));
    document.getElementById('tab_' + tname).style.display = 'block';
    event.currentTarget.classList.add('active');
}

// Check if tab parameter passed in URL
window.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    if(params.has('tab')) {
        const t = params.get('tab');
        const btn = document.querySelector(`button[onclick="switchTab('${t}')"]`);
        if(btn) btn.click();
    }
});

let isCompletedState = <?= $is_cur_completed ? 'true' : 'false' ?>;

function toggleComplete(lid, cid) {
    const newState = isCompletedState ? 0 : 1;
    fetch('ajax_mark_complete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `lecture_id=${lid}&course_id=${cid}&state=${newState}`
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            isCompletedState = data.completed;
            const btn = document.getElementById('markCompleteBtn');
            const ico = document.getElementById('completeIcon');
            const txt = document.getElementById('completeBtnText');

            if(isCompletedState) {
                btn.className = 'btn btn-success';
                btn.style.background = '#10b981';
                btn.style.color = 'white';
                btn.style.border = 'none';
                ico.className = 'fas fa-check-circle';
                txt.textContent = 'Completed';
            } else {
                btn.className = 'btn btn-secondary-custom';
                btn.style.background = 'white';
                btn.style.color = '#666';
                btn.style.border = '2px solid #e0e0e0';
                ico.className = 'fas fa-circle';
                txt.textContent = 'Mark as Complete';
            }

            // Update top progress
            document.getElementById('topProgText').textContent = data.percentage + '%';
            document.getElementById('topProgBar').style.width = data.percentage + '%';

            if(data.certificate_ready) {
                location.reload(); // reload to unlock certificate button
            }
        }
    });
}

// Auto track playback ending
<?php if(!empty($vid)): ?>
    var vIframe = document.getElementById('vimeoIframe');
    if(vIframe && typeof Vimeo !== 'undefined') {
        var vPlayer = new Vimeo.Player(vIframe);
        vPlayer.on('ended', function() {
            if(!isCompletedState) {
                toggleComplete(<?= $current_lec['id'] ?>, <?= $course_id ?>);
            }
        });
    }
<?php else: ?>
    var hPlayer = document.getElementById('html5VideoPlayer');
    if(hPlayer) {
        hPlayer.onended = function() {
            if(!isCompletedState) {
                toggleComplete(<?= $current_lec['id'] ?>, <?= $course_id ?>);
            }
        };
    }
<?php endif; ?>
</script>

<?php include('dashboard_footer.php'); ?>
