<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

include('header.php');

$slug = $_GET['slug'] ?? '';
$enrollment_id = intval($_GET['eid'] ?? 0); // passed after enrollment

if (!$slug) { header('Location: courses.php'); exit; }

// ── Fetch course ──────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT c.*, i.name AS instructor_name, i.bio AS instructor_bio, i.avatar AS instructor_avatar
    FROM courses c
    LEFT JOIN course_instructors i ON i.id = c.instructor_id
    WHERE c.slug = ? AND c.is_published = 1 LIMIT 1
");
$stmt->bind_param("s", $slug);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();

if (!$course) { header('Location: courses.php'); exit; }

$cid = $course['id'];

// ── Fetch curriculum ──────────────────────────────────────────────────────────
$sections = $conn->query("
    SELECT s.*, 
           COUNT(l.id) AS lecture_count,
           SUM(l.duration_mins) AS section_duration
    FROM course_sections s
    LEFT JOIN course_lectures l ON l.section_id = s.id
    WHERE s.course_id = $cid
    GROUP BY s.id ORDER BY s.sort_order
")->fetch_all(MYSQLI_ASSOC);

$lectures_by_section = [];
$all_lectures = $conn->query("
    SELECT * FROM course_lectures WHERE course_id = $cid ORDER BY sort_order
")->fetch_all(MYSQLI_ASSOC);
foreach ($all_lectures as $lec) {
    $lectures_by_section[$lec['section_id']][] = $lec;
}

// ── Active lecture ────────────────────────────────────────────────────────────
$active_lid = intval($_GET['lid'] ?? 0);
if (!$active_lid && !empty($all_lectures)) {
    $active_lid = $all_lectures[0]['id'];
}
$active_lecture = null;
foreach ($all_lectures as $l) {
    if ($l['id'] === $active_lid) { $active_lecture = $l; break; }
}

// ── Progress (if enrolled) ────────────────────────────────────────────────────
$progress_map   = []; // lecture_id => is_completed
$total_done     = 0;
$total_lectures = count($all_lectures);
$enrolled       = false;

if ($enrollment_id) {
    $pstmt = $conn->prepare("SELECT lecture_id, is_completed FROM course_progress WHERE enrollment_id = ?");
    $pstmt->bind_param("i", $enrollment_id);
    $pstmt->execute();
    $prows = $pstmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($prows as $pr) {
        $progress_map[$pr['lecture_id']] = $pr['is_completed'];
        if ($pr['is_completed']) $total_done++;
    }
    $enrolled = true;
}

$progress_pct = $total_lectures > 0 ? round($total_done / $total_lectures * 100) : 0;

function formatMins($mins) {
    $h = floor($mins/60); $m = round(fmod($mins,60));
    return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
}
function starRating($r) {
    $out = '';
    for ($i=1;$i<=5;$i++) $out .= $i<=$r?'<i class="fas fa-star" style="color:#f59e0b;font-size:.75rem;"></i>':'<i class="far fa-star" style="color:#ddd;font-size:.75rem;"></i>';
    return $out;
}
?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
.cdp * { font-family:'Poppins',Helvetica,Arial,sans-serif; box-sizing:border-box; }

/* ── Top bar ── */
.cdp-topbar {
    background:var(--primary); padding:12px 0;
    position:sticky; top:0; z-index:200;
    box-shadow:0 2px 12px rgba(0,0,0,.3);
}
.cdp-topbar-inner { display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
.cdp-back { color:rgba(255,255,255,.7); text-decoration:none; font-size:.8rem; font-weight:600; display:flex; align-items:center; gap:6px; transition:color .2s; white-space:nowrap; }
.cdp-back:hover { color:#fff; }
.cdp-topbar-title { font-size:.88rem; font-weight:700; color:#fff; flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.cdp-progress-mini { display:flex; align-items:center; gap:10px; margin-left:auto; flex-shrink:0; }
.cdp-progress-bar-wrap { width:120px; height:6px; background:rgba(255,255,255,.15); border-radius:3px; overflow:hidden; }
.cdp-progress-fill { height:100%; background:var(--accent); border-radius:3px; transition:width .5s; }
.cdp-progress-txt { font-size:.72rem; font-weight:700; color:var(--accent); white-space:nowrap; }

/* ── Layout ── */
.cdp-layout { display:flex; height:calc(100vh - 56px); overflow:hidden; }

/* ── Video panel ── */
.cdp-main { flex:1; overflow-y:auto; background:#111; }

.cdp-video-wrap {
    position:relative; background:#000;
    aspect-ratio:16/9; max-height:62vh;
}
.cdp-video-wrap iframe, .cdp-video-wrap video {
    width:100%; height:100%; border:none; display:block;
}
.cdp-video-placeholder {
    width:100%; height:100%; display:flex; flex-direction:column;
    align-items:center; justify-content:center;
    background:linear-gradient(135deg,#0a1628,var(--primary));
    color:rgba(255,255,255,.4); gap:12px;
}
.cdp-video-placeholder i { font-size:3rem; color:rgba(255,255,255,.2); }

/* Lecture info panel (below video) */
.cdp-lecture-info { background:#fff; padding:28px 32px; border-bottom:1px solid #f0f0f0; }
.cdp-lecture-title { font-size:1.3rem; font-weight:700; color:#111; margin-bottom:8px; }
.cdp-lecture-meta  { display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
.cdp-lec-meta-item { font-size:.78rem; color:#888; display:flex; align-items:center; gap:5px; }

.cdp-complete-btn {
    display:inline-flex; align-items:center; gap:8px;
    background:var(--primary); color:#fff; border:none;
    padding:10px 22px; border-radius:50px; font-size:.82rem; font-weight:700;
    cursor:pointer; transition:all .25s; font-family:'Poppins',Helvetica,sans-serif;
}
.cdp-complete-btn:hover { background:#035b58; box-shadow:0 4px 14px rgba(2,68,66,.25); }
.cdp-complete-btn.completed { background:#10b981; }
.cdp-complete-btn.completed:hover { background:#059669; }

/* Nav buttons */
.cdp-nav-btns { display:flex; gap:10px; align-items:center; margin-left:auto; }
.cdp-nav-btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:9px 18px; border-radius:50px; font-size:.78rem; font-weight:600;
    border:1.5px solid #e0e0e0; background:#fff; color:#555; cursor:pointer;
    transition:all .2s; text-decoration:none; font-family:'Poppins',Helvetica,sans-serif;
}
.cdp-nav-btn:hover { border-color:var(--primary); color:var(--primary); }
.cdp-nav-btn.disabled { opacity:.35; pointer-events:none; }

/* Course info tabs */
.cdp-tabs { background:#fff; border-bottom:1px solid #e8e8e8; }
.cdp-tab-list { display:flex; gap:0; padding:0 32px; }
.cdp-tab {
    padding:14px 20px; font-size:.83rem; font-weight:600; color:#888;
    border-bottom:2px solid transparent; cursor:pointer; transition:all .2s;
    white-space:nowrap;
}
.cdp-tab.active { color:var(--primary); border-bottom-color:var(--primary); }
.cdp-tab-content { display:none; padding:28px 32px; background:#fff; }
.cdp-tab-content.active { display:block; }

.cdp-about-title { font-size:1rem; font-weight:700; color:#111; margin-bottom:10px; }
.cdp-about-text  { font-size:.88rem; font-weight:300; color:#555; line-height:1.8; }
.cdp-what-list   { list-style:none; padding:0; margin:0; display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.cdp-what-list li { display:flex; gap:10px; font-size:.84rem; color:#444; align-items:flex-start; }
.cdp-what-list li i { color:#10b981; margin-top:3px; flex-shrink:0; }

/* Instructor card */
.cdp-instructor { display:flex; gap:16px; align-items:flex-start; margin-top:20px; }
.cdp-inst-avatar { width:60px; height:60px; border-radius:50%; background:linear-gradient(135deg,var(--primary),#035b58); display:flex; align-items:center; justify-content:center; font-size:1.4rem; font-weight:800; color:#fff; flex-shrink:0; }
.cdp-inst-name { font-size:1rem; font-weight:700; color:#111; margin-bottom:4px; }
.cdp-inst-bio  { font-size:.83rem; font-weight:300; color:#666; line-height:1.7; }

/* ── Sidebar (curriculum) ── */
.cdp-sidebar {
    width:340px; min-width:340px; background:#fafafa;
    border-left:1px solid #e8e8e8; overflow-y:auto;
    display:flex; flex-direction:column;
}
.cdp-sidebar-head {
    background:#fff; padding:18px 20px; border-bottom:1px solid #e8e8e8;
    position:sticky; top:0; z-index:10;
}
.cdp-sidebar-title { font-size:.95rem; font-weight:700; color:#111; margin-bottom:6px; }
.cdp-sidebar-meta  { font-size:.74rem; color:#888; display:flex; gap:12px; }

/* Progress bar in sidebar */
.cdp-sidebar-progress { margin-top:10px; }
.cdp-sidebar-pbar-wrap { height:5px; background:#e8e8e8; border-radius:3px; overflow:hidden; margin-bottom:4px; }
.cdp-sidebar-pbar      { height:100%; background:var(--accent); border-radius:3px; transition:width .5s; }
.cdp-sidebar-pct       { font-size:.7rem; font-weight:700; color:var(--primary); }

/* Section accordion */
.cdp-section-head {
    padding:14px 20px; background:#fff; border-bottom:1px solid #ebebeb;
    cursor:pointer; display:flex; justify-content:space-between; align-items:center;
    transition:background .2s; user-select:none;
}
.cdp-section-head:hover { background:#f7f9f5; }
.cdp-section-name  { font-size:.83rem; font-weight:700; color:#222; flex:1; }
.cdp-section-info  { font-size:.7rem; color:#aaa; white-space:nowrap; margin-left:8px; }
.cdp-section-arrow { font-size:.7rem; color:#aaa; transition:transform .25s; }
.cdp-section-head.open .cdp-section-arrow { transform:rotate(180deg); }

.cdp-section-body { display:none; }
.cdp-section-body.open { display:block; }

/* Lecture row */
.cdp-lec-row {
    display:flex; align-items:center; gap:10px;
    padding:10px 20px 10px 30px; border-bottom:1px solid #f0f0f0;
    cursor:pointer; transition:background .15s; text-decoration:none;
}
.cdp-lec-row:hover { background:#f0f7f4; }
.cdp-lec-row.active { background:rgba(184,243,90,.12); border-left:3px solid var(--accent); padding-left:27px; }

.cdp-lec-check { width:18px; height:18px; border-radius:50%; border:2px solid #ddd; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:.65rem; transition:all .2s; }
.cdp-lec-check.done { background:var(--primary); border-color:var(--primary); color:#fff; }
.cdp-lec-info { flex:1; min-width:0; }
.cdp-lec-name { font-size:.78rem; font-weight:500; color:#333; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:2px; }
.cdp-lec-row.active .cdp-lec-name { color:var(--primary); font-weight:700; }
.cdp-lec-dur  { font-size:.67rem; color:#aaa; }
.cdp-lec-preview { font-size:.6rem; font-weight:700; color:var(--primary); background:rgba(184,243,90,.2); padding:2px 6px; border-radius:10px; margin-left:auto; flex-shrink:0; }

/* ── Enroll CTA (if not enrolled) ── */
.cdp-enroll-cta {
    background:linear-gradient(135deg,var(--primary),#035b58);
    padding:24px 20px; text-align:center; border-top:1px solid rgba(255,255,255,.1);
}
.cdp-enroll-cta-price { font-size:1.8rem; font-weight:800; color:var(--accent); margin-bottom:6px; }
.cdp-enroll-cta-sub   { font-size:.75rem; color:rgba(255,255,255,.6); margin-bottom:14px; }
.cdp-enroll-cta-btn {
    display:block; width:100%; padding:13px; border-radius:50px;
    background:var(--accent); color:var(--primary); border:none; cursor:pointer;
    font-size:.88rem; font-weight:800; transition:all .25s;
    font-family:'Poppins',Helvetica,sans-serif;
}
.cdp-enroll-cta-btn:hover { background:#a8e835; box-shadow:0 6px 20px rgba(0,0,0,.2); }

/* ── Mobile ── */
@media (max-width:991px) {
    .cdp-layout { flex-direction:column; height:auto; overflow:visible; }
    .cdp-main { overflow:visible; }
    .cdp-sidebar { width:100%; min-width:0; max-height:none; }
    .cdp-video-wrap { max-height:56vw; }
    .cdp-lecture-info { padding:20px; }
    .cdp-tab-list { overflow-x:auto; scrollbar-width:none; }
    .cdp-tab-list::-webkit-scrollbar { display:none; }
    .cdp-tab-content { padding:20px; }
    .cdp-what-list { grid-template-columns:1fr; }
    .cdp-topbar-inner { flex-wrap:nowrap; }
    .cdp-progress-mini { display:none; }
}
</style>

<div class="cdp">

<!-- ── Sticky topbar ── -->
<div class="cdp-topbar">
    <div class="container">
        <div class="cdp-topbar-inner">
            <a href="courses.php" class="cdp-back"><i class="fas fa-arrow-left"></i> Courses</a>
            <div class="cdp-topbar-title"><?= htmlspecialchars($course['title']) ?></div>
            <?php if ($enrolled): ?>
            <div class="cdp-progress-mini">
                <div class="cdp-progress-bar-wrap">
                    <div class="cdp-progress-fill" style="width:<?= $progress_pct ?>%"></div>
                </div>
                <span class="cdp-progress-txt"><?= $progress_pct ?>% done</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Main layout ── -->
<div class="cdp-layout">

    <!-- ── LEFT: Video + info ── -->
    <div class="cdp-main">

        <!-- Video -->
        <div class="cdp-video-wrap">
            <?php if ($active_lecture && !empty($active_lecture['video_url'])): ?>
                <?php
                // Detect YouTube, Vimeo or raw mp4
                $vurl = $active_lecture['video_url'];
                $is_yt = str_contains($vurl, 'youtube.com') || str_contains($vurl, 'youtu.be');
                $is_vm = str_contains($vurl, 'vimeo.com');
                ?>
                <?php if ($is_yt || $is_vm): ?>
                <iframe src="<?= htmlspecialchars($vurl) ?>" allowfullscreen allow="autoplay; encrypted-media"
                        title="<?= htmlspecialchars($active_lecture['title']) ?>"></iframe>
                <?php else: ?>
                <video controls preload="metadata" id="mainVideo"
                       onended="markComplete(<?= $active_lecture['id'] ?>)">
                    <source src="<?= htmlspecialchars($vurl) ?>" type="video/mp4">
                </video>
                <?php endif; ?>
            <?php elseif (!$enrolled && ($active_lecture && !$active_lecture['is_preview'])): ?>
            <div class="cdp-video-placeholder">
                <i class="fas fa-lock"></i>
                <div style="font-size:.95rem;font-weight:600;color:rgba(255,255,255,.5);">Enroll to unlock this lecture</div>
            </div>
            <?php else: ?>
            <div class="cdp-video-placeholder">
                <i class="fas fa-play-circle"></i>
                <div>Select a lecture to begin</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Lecture info -->
        <div class="cdp-lecture-info">
            <div class="cdp-lecture-title"><?= htmlspecialchars($active_lecture['title'] ?? 'Welcome to the Course') ?></div>
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-top:10px;">
                <div class="cdp-lecture-meta">
                    <?php if ($active_lecture): ?>
                    <span class="cdp-lec-meta-item"><i class="fas fa-clock"></i> <?= formatMins($active_lecture['duration_mins']) ?></span>
                    <?php if ($active_lecture['is_preview']): ?>
                    <span class="cdp-lec-meta-item" style="color:var(--primary);font-weight:600;"><i class="fas fa-eye"></i> Free Preview</span>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <?php
                    // Prev/Next lecture
                    $prev_lec = $next_lec = null;
                    $found = false;
                    foreach ($all_lectures as $l) {
                        if ($found) { $next_lec = $l; break; }
                        if ($l['id'] === $active_lid) { $found = true; continue; }
                        $prev_lec = $l;
                    }
                    $eid_param = $enrollment_id ? "&eid=$enrollment_id" : '';
                    ?>
                    <?php if ($prev_lec): ?>
                    <a href="course_detail.php?slug=<?= urlencode($slug) ?>&lid=<?= $prev_lec['id'] ?><?= $eid_param ?>" class="cdp-nav-btn"><i class="fas fa-step-backward"></i> Prev</a>
                    <?php endif; ?>
                    <?php if ($next_lec): ?>
                    <a href="course_detail.php?slug=<?= urlencode($slug) ?>&lid=<?= $next_lec['id'] ?><?= $eid_param ?>" class="cdp-nav-btn">Next <i class="fas fa-step-forward"></i></a>
                    <?php endif; ?>

                    <?php if ($enrolled && $active_lecture): ?>
                    <?php $is_done = !empty($progress_map[$active_lid]); ?>
                    <button class="cdp-complete-btn <?= $is_done?'completed':'' ?>" id="completeBtn"
                            onclick="markComplete(<?= $active_lid ?>)">
                        <i class="fas fa-<?= $is_done?'check-circle':'circle' ?>"></i>
                        <?= $is_done ? 'Completed' : 'Mark Complete' ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Info tabs -->
        <div class="cdp-tabs">
            <div class="cdp-tab-list">
                <div class="cdp-tab active" onclick="showTab('about')">About</div>
                <div class="cdp-tab" onclick="showTab('outcomes')">What You'll Learn</div>
                <div class="cdp-tab" onclick="showTab('instructor')">Instructor</div>
                <?php if ($enrolled): ?>
                <div class="cdp-tab" onclick="showTab('progress')">My Progress</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="cdp-tab-content active" id="tab-about">
            <div class="cdp-about-title"><?= htmlspecialchars($course['title']) ?></div>
            <p class="cdp-about-text"><?= nl2br(htmlspecialchars($course['description'])) ?></p>
            <div style="display:flex;gap:20px;margin-top:20px;flex-wrap:wrap;">
                <div style="text-align:center;background:#f7f9f5;border-radius:12px;padding:14px 20px;">
                    <div style="font-size:1.4rem;font-weight:800;color:var(--primary);"><?= formatMins($course['duration_mins']) ?></div>
                    <div style="font-size:.7rem;color:#aaa;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Duration</div>
                </div>
                <div style="text-align:center;background:#f7f9f5;border-radius:12px;padding:14px 20px;">
                    <div style="font-size:1.4rem;font-weight:800;color:var(--primary);"><?= $total_lectures ?></div>
                    <div style="font-size:.7rem;color:#aaa;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Lectures</div>
                </div>
                <div style="text-align:center;background:#f7f9f5;border-radius:12px;padding:14px 20px;">
                    <div style="font-size:1.4rem;font-weight:800;color:var(--primary);"><?= $course['level'] ?></div>
                    <div style="font-size:.7rem;color:#aaa;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Level</div>
                </div>
                <div style="text-align:center;background:#f7f9f5;border-radius:12px;padding:14px 20px;">
                    <div style="font-size:1.4rem;font-weight:800;color:var(--primary);"><?= number_format($course['total_students']) ?></div>
                    <div style="font-size:.7rem;color:#aaa;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Students</div>
                </div>
            </div>
        </div>

        <div class="cdp-tab-content" id="tab-outcomes">
            <div class="cdp-about-title">What You'll Learn</div>
            <ul class="cdp-what-list" style="margin-top:16px;">
                <li><i class="fas fa-check-circle"></i> Professional industry-level skills</li>
                <li><i class="fas fa-check-circle"></i> Real-world projects and case studies</li>
                <li><i class="fas fa-check-circle"></i> Tools and software used by professionals</li>
                <li><i class="fas fa-check-circle"></i> Build a portfolio-ready project</li>
                <li><i class="fas fa-check-circle"></i> Freelancing and client management tips</li>
                <li><i class="fas fa-check-circle"></i> Certificate of completion</li>
            </ul>
        </div>

        <div class="cdp-tab-content" id="tab-instructor">
            <div class="cdp-about-title">Your Instructor</div>
            <div class="cdp-instructor">
                <div class="cdp-inst-avatar"><?= strtoupper(substr($course['instructor_name']??'G',0,1)) ?></div>
                <div>
                    <div class="cdp-inst-name"><?= htmlspecialchars($course['instructor_name'] ?? 'Graphicafix Team') ?></div>
                    <div style="display:flex;align-items:center;gap:5px;margin-bottom:8px;">
                        <?= starRating(5) ?>
                        <span style="font-size:.75rem;color:#888;">Instructor Rating</span>
                    </div>
                    <div class="cdp-inst-bio"><?= htmlspecialchars($course['instructor_bio'] ?? 'Expert instructor with years of real-world experience.') ?></div>
                </div>
            </div>
        </div>

        <?php if ($enrolled): ?>
        <div class="cdp-tab-content" id="tab-progress">
            <div class="cdp-about-title">Course Progress</div>
            <div style="margin:16px 0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:.88rem;font-weight:600;color:#333;"><?= $total_done ?> of <?= $total_lectures ?> lectures completed</span>
                    <span style="font-size:1.2rem;font-weight:800;color:var(--primary);"><?= $progress_pct ?>%</span>
                </div>
                <div style="height:10px;background:#f0f0f0;border-radius:5px;overflow:hidden;">
                    <div style="height:100%;width:<?= $progress_pct ?>%;background:var(--accent);border-radius:5px;transition:width .5s;"></div>
                </div>
            </div>
            <?php if ($progress_pct === 100): ?>
            <div style="background:#d7f8b8;border:1px solid #10b981;border-radius:12px;padding:16px;text-align:center;margin-top:16px;">
                <div style="font-size:1.5rem;margin-bottom:6px;">🎉</div>
                <div style="font-weight:700;color:#2b7a2b;font-size:.95rem;">Course Completed!</div>
                <div style="font-size:.82rem;color:#555;margin-top:4px;">You've finished all lectures. Download your certificate.</div>
                <a href="certificate.php?eid=<?= $enrollment_id ?>" style="display:inline-flex;align-items:center;gap:7px;margin-top:12px;background:var(--primary);color:#fff;padding:9px 20px;border-radius:30px;font-size:.8rem;font-weight:700;text-decoration:none;">
                    <i class="fas fa-certificate"></i> Get Certificate
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div><!-- /.cdp-main -->

    <!-- ── RIGHT: Sidebar curriculum ── -->
    <div class="cdp-sidebar">
        <div class="cdp-sidebar-head">
            <div class="cdp-sidebar-title">Course Content</div>
            <div class="cdp-sidebar-meta">
                <span><?= count($sections) ?> sections</span>
                <span>&middot;</span>
                <span><?= $total_lectures ?> lectures</span>
                <span>&middot;</span>
                <span><?= formatMins($course['duration_mins']) ?></span>
            </div>
            <?php if ($enrolled): ?>
            <div class="cdp-sidebar-progress">
                <div class="cdp-sidebar-pbar-wrap">
                    <div class="cdp-sidebar-pbar" id="sidebarPbar" style="width:<?= $progress_pct ?>%"></div>
                </div>
                <div class="cdp-sidebar-pct" id="sidebarPct"><?= $progress_pct ?>% complete</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sections -->
        <?php foreach ($sections as $section): ?>
        <?php $sec_lecs = $lectures_by_section[$section['id']] ?? []; ?>
        <div class="cdp-section">
            <div class="cdp-section-head open" onclick="toggleSection(this)">
                <div class="cdp-section-name"><?= htmlspecialchars($section['title']) ?></div>
                <span class="cdp-section-info"><?= count($sec_lecs) ?> lec · <?= formatMins($section['section_duration'] ?? 0) ?></span>
                <span class="cdp-section-arrow"><i class="fas fa-chevron-down"></i></span>
            </div>
            <div class="cdp-section-body open">
                <?php foreach ($sec_lecs as $lec):
                    $is_active_lec = ($lec['id'] === $active_lid);
                    $is_done_lec   = !empty($progress_map[$lec['id']]);
                    $accessible    = $enrolled || $lec['is_preview'];
                    $href = $accessible
                        ? "course_detail.php?slug=".urlencode($slug)."&lid={$lec['id']}".($enrollment_id?"&eid=$enrollment_id":'')
                        : '#';
                ?>
                <a href="<?= $href ?>"
                   class="cdp-lec-row <?= $is_active_lec?'active':'' ?>"
                   <?= !$accessible ? 'onclick="return showLockMsg()"' : '' ?>>
                    <div class="cdp-lec-check <?= $is_done_lec?'done':'' ?>"
                         id="lec-check-<?= $lec['id'] ?>">
                        <?= $is_done_lec ? '<i class="fas fa-check"></i>' : '' ?>
                    </div>
                    <div class="cdp-lec-info">
                        <div class="cdp-lec-name" title="<?= htmlspecialchars($lec['title']) ?>"><?= htmlspecialchars($lec['title']) ?></div>
                        <div class="cdp-lec-dur"><i class="fas fa-play-circle" style="font-size:.62rem;"></i> <?= formatMins($lec['duration_mins']) ?></div>
                    </div>
                    <?php if ($lec['is_preview'] && !$enrolled): ?>
                    <span class="cdp-lec-preview">Preview</span>
                    <?php elseif (!$accessible): ?>
                    <i class="fas fa-lock" style="font-size:.7rem;color:#ccc;flex-shrink:0;"></i>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Enroll CTA if not enrolled -->
        <?php if (!$enrolled): ?>
        <div class="cdp-enroll-cta" style="margin-top:auto;">
            <?php if ($course['is_free']): ?>
            <div class="cdp-enroll-cta-price">Free</div>
            <div class="cdp-enroll-cta-sub">Start learning immediately at no cost</div>
            <?php else: ?>
            <div class="cdp-enroll-cta-price">Rs. <?= number_format($course['price']) ?></div>
            <div class="cdp-enroll-cta-sub">Full lifetime access · Certificate included</div>
            <?php endif; ?>
            <button class="cdp-enroll-cta-btn"
                onclick="window.location='courses.php?enroll=<?= $cid ?>'">
                <?= $course['is_free'] ? 'Enroll for Free' : 'Enroll Now' ?>
            </button>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /.cdp-layout -->
</div><!-- /.cdp -->

<script>
const enrollmentId = <?= $enrollment_id ?: 0 ?>;
const courseSlug   = '<?= addslashes($slug) ?>';

// ── Tab switcher ──────────────────────────────────────────────────────────────
function showTab(id) {
    document.querySelectorAll('.cdp-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.cdp-tab-content').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('tab-' + id).classList.add('active');
}

// ── Section accordion ─────────────────────────────────────────────────────────
function toggleSection(el) {
    el.classList.toggle('open');
    el.nextElementSibling.classList.toggle('open');
}

// ── Mark lecture complete (AJAX) ──────────────────────────────────────────────
function markComplete(lectureId) {
    if (!enrollmentId) return;
    const btn   = document.getElementById('completeBtn');
    const check = document.getElementById('lec-check-' + lectureId);
    const isDone = btn && btn.classList.contains('completed');

    fetch('course_progress.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `enrollment_id=${enrollmentId}&lecture_id=${lectureId}&completed=${isDone?0:1}`
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;

        const nowDone = !isDone;

        // Update button
        if (btn) {
            btn.classList.toggle('completed', nowDone);
            btn.innerHTML = nowDone
                ? '<i class="fas fa-check-circle"></i> Completed'
                : '<i class="fas fa-circle"></i> Mark Complete';
        }

        // Update sidebar checkbox
        if (check) {
            check.classList.toggle('done', nowDone);
            check.innerHTML = nowDone ? '<i class="fas fa-check"></i>' : '';
        }

        // Update progress bars
        const pct = data.progress_pct;
        document.querySelectorAll('.cdp-progress-fill, .cdp-sidebar-pbar').forEach(el => el.style.width = pct + '%');
        document.querySelectorAll('.cdp-progress-txt').forEach(el => el.textContent = pct + '% done');
        const spc = document.getElementById('sidebarPct');
        if (spc) spc.textContent = pct + '% complete';

        // Auto-advance to next lecture when completed
        if (nowDone) {
            const nextLink = document.querySelector('.cdp-nav-btn:last-of-type');
            if (nextLink && nextLink.href.includes('lid=')) {
                setTimeout(() => { window.location = nextLink.href; }, 800);
            }
        }
    });
}

function showLockMsg() {
    alert('Enroll in this course to access this lecture.');
    return false;
}

// Auto-mark complete when mp4 ends
const vid = document.getElementById('mainVideo');
if (vid) {
    vid.addEventListener('ended', () => markComplete(<?= $active_lid ?: 0 ?>));
}
</script>

<?php include('footer.php'); ?>
