<?php
require_once __DIR__ . '/../core/config.php';
include '../templates/dashboard_header.php';

// ── Inline email helper functions ─────────────────────────────────────────────
// (copy the sendStatusUpdateEmail + buildEmailWrapper functions inline here)
function buildEmailWrapper($icon,$heading,$body_html,$btn_text='',$btn_url=''){
    $siteUrl=defined('SITE_URL')?SITE_URL:'https://graphicafix.com';
    $btn=$btn_text?"<div style='text-align:center;margin-top:24px;'><a href='$btn_url' style='display:inline-block;background:#024442;color:#fff;padding:13px 30px;border-radius:50px;text-decoration:none;font-weight:700;font-size:.88rem;'>$btn_text →</a></div>":'';
    return "<!DOCTYPE html><html><head><meta charset='utf-8'></head><body style='margin:0;padding:0;background:#f4f6f8;font-family:Arial,sans-serif;'><div style='max-width:580px;margin:36px auto;'><div style='background:#024442;border-radius:16px 16px 0 0;padding:32px 40px;text-align:center;'><div style='font-size:2.2rem;margin-bottom:8px;'>$icon</div><h1 style='margin:0;color:#fff;font-size:1.45rem;font-weight:700;'>$heading</h1></div><div style='background:#fff;padding:32px 40px;'>$body_html$btn</div><div style='background:#f7f9f5;border-radius:0 0 16px 16px;padding:14px 40px;text-align:center;border-top:1px solid #e0e0e0;'><p style='margin:0;font-size:11.5px;color:#aaa;'>Graphicafix &middot; <a href='$siteUrl' style='color:#024442;'>$siteUrl</a></p></div></div></body></html>";
}
function sendStatusUpdateEmail($enrollment,$course,$status){
    $name=$enrollment['student_name'];$email=$enrollment['student_email'];
    $siteUrl=defined('SITE_URL')?SITE_URL:'https://graphicafix.com';
    $t=['active'=>['🎉','Enrollment Confirmed!',"Your enrollment in <strong>{$course['title']}</strong> is confirmed and access is now active.",'Start Learning',"$siteUrl/courses.php"],'reviewing'=>['🔍','Under Review',"We're reviewing your enrollment for <strong>{$course['title']}</strong>. We'll update you soon.",'View Courses',"$siteUrl/courses.php"],'rejected'=>['❌','Not Approved',"Your enrollment for <strong>{$course['title']}</strong> was not approved. Please contact us.",'Contact Us',"$siteUrl/contact.php"],'completed'=>['🏆','Course Completed!',"Congrats on completing <strong>{$course['title']}</strong>! Your certificate is ready.",'Get Certificate',"$siteUrl/courses.php"],'cancelled'=>['🚫','Cancelled',"Your enrollment in <strong>{$course['title']}</strong> has been cancelled.",'Contact Us',"$siteUrl/contact.php"]];
    if(!isset($t[$status]))return;
    [$icon,$heading,$msg,$btn,$btnUrl]=$t[$status];
    $body="<p style='font-size:15px;color:#333;margin:0 0 14px;'>Hi <strong>$name</strong>,</p><p style='font-size:14px;color:#555;line-height:1.7;'>$msg</p>";
    $html=buildEmailWrapper($icon,$heading,$body,$btn,$btnUrl);
    $h="MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: Graphicafix Courses <noreply@graphicafix.com>\r\nReply-To: info@graphicafix.com\r\n";
    mail($email,"$icon $heading — {$course['title']}",$html,$h);
}

// ── Handle all POST actions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {

    // ── Enrollment status update
    if (isset($_POST['update_enrollment'])) {
        $eid    = intval($_POST['enrollment_id']??0);
        $status = $_POST['status']??'';
        $allowed= ['pending','reviewing','active','completed','rejected','cancelled'];
        if ($eid && in_array($status,$allowed)) {
            $conn->prepare("UPDATE course_enrollments SET status=? WHERE id=?")->execute() ||
            (function()use($conn,$status,$eid){ $s=$conn->prepare("UPDATE course_enrollments SET status=? WHERE id=?"); $s->bind_param("si",$status,$eid); $s->execute(); })();
            $u=$conn->prepare("UPDATE course_enrollments SET status=? WHERE id=?");
            $u->bind_param("si",$status,$eid); $u->execute();
            // Send email
            $es=$conn->prepare("SELECT e.*, c.title, c.slug FROM course_enrollments e JOIN courses c ON c.id=e.course_id WHERE e.id=?");
            $es->bind_param("i",$eid); $es->execute();
            $en=$es->get_result()->fetch_assoc();
            if ($en) sendStatusUpdateEmail($en,['title'=>$en['title'],'slug'=>$en['slug']],$status);
        }
        header("Location: manage_courses.php?tab=registrations&esub={$_POST['current_tab']}&updated=1"); exit;
    }

    // ── Delete enrollment
    if (isset($_POST['delete_enrollment'])) {
        $eid=intval($_POST['enrollment_id']??0);
        if ($eid) { $s=$conn->prepare("DELETE FROM course_enrollments WHERE id=?"); $s->bind_param("i",$eid); $s->execute(); }
        header("Location: manage_courses.php?tab=registrations&deleted=1"); exit;
    }

    // ── Add course
    if (isset($_POST['add_course'])) {
        $title   = trim($_POST['c_title']??'');
        $tagline = trim($_POST['c_tagline']??'');
        $desc    = trim($_POST['c_description']??'');
        $cat     = trim($_POST['c_category']??'');
        $level   = trim($_POST['c_level']??'Beginner');
        $price   = floatval($_POST['c_price']??0);
        $is_free = isset($_POST['c_is_free'])?1:0;
        $is_feat = isset($_POST['c_is_featured'])?1:0;
        $dur     = intval($_POST['c_duration']??0);
        if ($is_free) $price=0;
        $slug = strtolower(preg_replace('/[^a-z0-9]+/','-',trim($title,'-')));
        if ($conn->query("SELECT id FROM courses WHERE slug='$slug'")->num_rows) $slug.='-'.time();
        $s=$conn->prepare("INSERT INTO courses (title,slug,tagline,description,category,level,price,is_free,is_featured,is_published,duration_mins) VALUES (?,?,?,?,?,?,?,?,?,1,?)");
        $s->bind_param("ssssssdiiii",$title,$slug,$tagline,$desc,$cat,$level,$price,$is_free,$is_feat,$dur);
        $s->execute();
        header("Location: manage_courses.php?tab=courses&course_added=1"); exit;
    }

    // ── Edit course
    if (isset($_POST['edit_course'])) {
        $cid=$intval($_POST['course_id']??0);$cid=intval($_POST['course_id']??0);
        $title=trim($_POST['c_title']??'');$tagline=trim($_POST['c_tagline']??'');
        $desc=trim($_POST['c_description']??'');$cat=trim($_POST['c_category']??'');
        $level=trim($_POST['c_level']??'Beginner');$price=floatval($_POST['c_price']??0);
        $is_free=isset($_POST['c_is_free'])?1:0;$is_feat=isset($_POST['c_is_featured'])?1:0;
        $is_pub=isset($_POST['c_is_published'])?1:0;$dur=intval($_POST['c_duration']??0);
        if ($is_free) $price=0;
        if ($cid) {
            $s=$conn->prepare("UPDATE courses SET title=?,tagline=?,description=?,category=?,level=?,price=?,is_free=?,is_featured=?,is_published=?,duration_mins=? WHERE id=?");
            $s->bind_param("ssssssdiiiii",$title,$tagline,$desc,$cat,$level,$price,$is_free,$is_feat,$is_pub,$dur,$cid);
            $s->execute();
        }
        header("Location: manage_courses.php?tab=courses&updated=1"); exit;
    }

    // ── Toggle publish
    if (isset($_POST['toggle_publish'])) {
        $cid=intval($_POST['course_id']??0);$cur=intval($_POST['current_pub']??0);
        if ($cid) $conn->query("UPDATE courses SET is_published=".($cur?0:1)." WHERE id=$cid");
        header("Location: manage_courses.php?tab=courses&updated=1"); exit;
    }

    // ── Delete course
    if (isset($_POST['delete_course'])) {
        $cid=intval($_POST['course_id']??0);
        if ($cid) { $s=$conn->prepare("DELETE FROM courses WHERE id=?"); $s->bind_param("i",$cid); $s->execute(); }
        header("Location: manage_courses.php?tab=courses&deleted=1"); exit;
    }
}

// ── Tab state ─────────────────────────────────────────────────────────────────
$tab  = $_GET['tab']  ?? 'registrations';
if (!in_array($tab,['registrations','courses'])) $tab='registrations';
$esub = $_GET['esub'] ?? 'all';
$esub_allowed = ['all','pending','reviewing','active','completed','rejected','cancelled'];
if (!in_array($esub,$esub_allowed)) $esub='all';

// ── Counts ────────────────────────────────────────────────────────────────────
$ecnt=[];
foreach(['pending','reviewing','active','completed','rejected','cancelled'] as $s)
    $ecnt[$s]=$conn->query("SELECT COUNT(*) AS c FROM course_enrollments WHERE status='$s'")->fetch_assoc()['c']??0;
$ecnt['all']=array_sum($ecnt);
$total_courses     = $conn->query("SELECT COUNT(*) AS c FROM courses")->fetch_assoc()['c']??0;
$published_courses = $conn->query("SELECT COUNT(*) AS c FROM courses WHERE is_published=1")->fetch_assoc()['c']??0;
$total_revenue     = $conn->query("SELECT COALESCE(SUM(amount_paid),0) AS r FROM course_enrollments WHERE status IN ('active','completed')")->fetch_assoc()['r']??0;

// ── Fetch enrollments ─────────────────────────────────────────────────────────
$base_sql = "SELECT e.*, c.title AS course_title, c.slug AS course_slug
             FROM course_enrollments e
             JOIN courses c ON c.id=e.course_id";
if ($esub==='all') {
    $enrollments=$conn->query($base_sql." ORDER BY e.enrolled_at DESC");
} else {
    $es=$conn->prepare($base_sql." WHERE e.status=? ORDER BY e.enrolled_at DESC");
    $es->bind_param("s",$esub); $es->execute();
    $enrollments=$es->get_result();
}

// ── Fetch courses ─────────────────────────────────────────────────────────────
$courses_result=$conn->query("
    SELECT c.*,
           i.name AS instructor_name,
           (SELECT COUNT(*) FROM course_enrollments WHERE course_id=c.id AND status IN ('active','completed')) AS enroll_count
    FROM courses c
    LEFT JOIN course_instructors i ON i.id=c.instructor_id
    ORDER BY c.is_published DESC, c.created_at DESC
");
$courses_js=[];
if ($courses_result) { while($c=$courses_result->fetch_assoc()) $courses_js[$c['id']]=$c; $courses_result->data_seek(0); }

function timeAgo($d){$s=time()-strtotime($d);if($s<60)return $s."s ago";if($s<3600)return round($s/60)."m ago";if($s<86400)return round($s/3600)."h ago";if($s<604800)return round($s/86400)."d ago";return date("M j, Y",strtotime($d));}
function eBadge($s){return match($s){'pending'=>['Pending','#fef9c3','#a16207'],'reviewing'=>['Reviewing','#e0f2fe','#0284c7'],'active'=>['Active','#d7f8b8','#2b7a2b'],'completed'=>['Completed','#dbeaff','#1b4ed8'],'rejected'=>['Rejected','#ffe5e5','#d63031'],'cancelled'=>['Cancelled','#f0f0f0','#666'],default=>[ucfirst($s),'#f0f0f0','#555']};}
function aColor($n){$c=['pink','blue','green','orange','purple'];return $c[ord(strtoupper($n[0]??'A'))%5];}
?>

<div class="height-100">

<!-- Stats row -->
<div class="stats-row" style="margin-bottom:24px;">
    <div class="stat-card"><div class="stat-icon">📚</div><div class="stat-number"><?= $total_courses ?></div><div class="stat-label">Total Courses</div></div>
    <div class="stat-card"><div class="stat-icon" style="color:#2b7a2b;">🌐</div><div class="stat-number" style="color:#2b7a2b;"><?= $published_courses ?></div><div class="stat-label">Published</div></div>
    <div class="stat-card"><div class="stat-icon">🎓</div><div class="stat-number"><?= $ecnt['all'] ?></div><div class="stat-label">Registrations</div></div>
    <div class="stat-card"><div class="stat-icon" style="color:#0284c7;">⏳</div><div class="stat-number" style="color:#0284c7;"><?= $ecnt['pending'] ?></div><div class="stat-label">Pending</div></div>
    <div class="stat-card"><div class="stat-icon" style="color:#7c3aed;">💰</div><div class="stat-number" style="color:#7c3aed;font-size:1.1rem;">Rs.<?= number_format($total_revenue) ?></div><div class="stat-label">Revenue</div></div>
</div>

<div class="main-card">

    <!-- ── Main tab bar ── -->
    <div style="display:flex;border-bottom:2px solid #f0f0f0;padding:0 24px;overflow-x:auto;">
        <?php foreach(['registrations'=>['🎓','Registrations',$ecnt['all']],'courses'=>['📚','Courses',$total_courses]] as $tk=>$td): $a=$tab===$tk; ?>
        <a href="manage_courses.php?tab=<?= $tk ?>&esub=<?= $esub ?>"
           style="display:inline-flex;align-items:center;gap:7px;padding:14px 20px;font-size:13.5px;font-weight:700;text-decoration:none;white-space:nowrap;border-bottom:2px solid <?= $a?'var(--primary)':'transparent' ?>;color:<?= $a?'var(--primary)':'#888' ?>;margin-bottom:-2px;transition:all .2s;">
            <?= $td[0] ?> <?= $td[1] ?>
            <span style="background:<?= $a?'var(--primary)':'#f0f0f0' ?>;color:<?= $a?'#fff':'#888' ?>;padding:1px 8px;border-radius:20px;font-size:11px;font-weight:800;"><?= $td[2] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($tab==='registrations'): ?>
    <!-- ═══════ REGISTRATIONS TAB ═══════ -->
    <div class="main-header" style="margin-top:4px;">
        <h3>🎓 Course Registrations</h3>
        <span style="font-size:13px;color:#888;"><?= $enrollments->num_rows ?> result<?= $enrollments->num_rows!=1?'s':'' ?></span>
    </div>

    <!-- Status sub-tabs -->
    <div style="display:flex;padding:0 24px;border-bottom:1px solid #f0f0f0;overflow-x:auto;scrollbar-width:none;gap:0;">
        <?php
        $esubs=['all'=>['All','📋'],'pending'=>['Pending','⏳'],'reviewing'=>['Reviewing','🔍'],'active'=>['Active','✅'],'completed'=>['Completed','🏆'],'rejected'=>['Rejected','❌'],'cancelled'=>['Cancelled','🚫']];
        foreach($esubs as $k=>$e): $a=$esub===$k; ?>
        <a href="manage_courses.php?tab=registrations&esub=<?= $k ?>"
           style="display:inline-flex;align-items:center;gap:5px;padding:10px 14px;font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap;color:<?= $a?'var(--primary)':'#999' ?>;border-bottom:2px solid <?= $a?'var(--primary)':'transparent' ?>;margin-bottom:-1px;">
            <?= $e[1] ?> <?= $e[0] ?>
            <span style="background:<?= $a?'var(--primary)':'#f0f0f0' ?>;color:<?= $a?'#fff':'#aaa' ?>;padding:1px 6px;border-radius:20px;font-size:10px;font-weight:800;"><?= $k==='all'?$ecnt['all']:($ecnt[$k]??0) ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Registrations table -->
    <div class="main-table" style="padding-top:8px;">
        <div class="table-head" style="grid-template-columns:2fr 2fr 1fr 1fr 1fr 1fr 1fr;">
            <span>Student</span>
            <span>Course</span>
            <span>Paid</span>
            <span>Payment</span>
            <span>Status</span>
            <span>Date</span>
            <span>Actions</span>
        </div>

        <?php if ($enrollments && $enrollments->num_rows>0): while($e=$enrollments->fetch_assoc()):
            $badge=eBadge($e['status']);
            $has_payment = !empty($e['payment_proof']);
        ?>
        <div class="table-row" style="grid-template-columns:2fr 2fr 1fr 1fr 1fr 1fr 1fr;">
            <div class="client">
                <div class="avatar <?= aColor($e['student_name']) ?>"><?= strtoupper($e['student_name'][0]) ?></div>
                <div>
                    <strong><?= htmlspecialchars($e['student_name']) ?></strong>
                    <small><?= htmlspecialchars($e['student_email']) ?></small>
                </div>
            </div>
            <span data-label="Course" style="font-size:13px;color:#555;font-weight:500;"><?= htmlspecialchars($e['course_title']) ?></span>
            <span data-label="Paid" style="font-weight:700;font-size:13.5px;color:<?= $e['amount_paid']>0?'var(--primary)':'#10b981' ?>;">
                <?= $e['amount_paid']>0 ? 'Rs.'.number_format($e['amount_paid']) : 'Free' ?>
            </span>
            <span data-label="Payment">
                <?php if ($has_payment): ?>
                <a href="<?= htmlspecialchars($e['payment_proof']) ?>" target="_blank"
                   style="display:inline-flex;align-items:center;gap:4px;background:#d7f8b8;color:#2b7a2b;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;text-decoration:none;">
                    📎 View
                </a>
                <?php elseif($e['amount_paid']<=0): ?>
                <span style="font-size:11px;color:#aaa;">—</span>
                <?php else: ?>
                <span style="background:#ffe5e5;color:#d63031;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;">Missing</span>
                <?php endif; ?>
            </span>
            <span>
                <span style="background:<?= $badge[1] ?>;color:<?= $badge[2] ?>;padding:4px 11px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;">
                    <?= $badge[0] ?>
                </span>
            </span>
            <span data-label="Date" style="font-size:12px;color:#888;"><?= timeAgo($e['enrolled_at']) ?></span>
            <div style="display:flex;align-items:center;gap:3px;flex-wrap:wrap;">
                <button class="dots view-enrollment" title="View details"
                    data-id="<?= $e['id'] ?>"
                    data-name="<?= htmlspecialchars($e['student_name']) ?>"
                    data-email="<?= htmlspecialchars($e['student_email']) ?>"
                    data-phone="<?= htmlspecialchars($e['student_phone']??'') ?>"
                    data-city="<?= htmlspecialchars($e['student_city']??'') ?>"
                    data-qual="<?= htmlspecialchars($e['student_qualification']??'') ?>"
                    data-exp="<?= htmlspecialchars($e['student_experience']??'') ?>"
                    data-motive="<?= htmlspecialchars($e['student_motivation']??'') ?>"
                    data-course="<?= htmlspecialchars($e['course_title']) ?>"
                    data-paid="<?= $e['amount_paid'] ?>"
                    data-code="<?= htmlspecialchars($e['discount_code']??'') ?>"
                    data-payment="<?= htmlspecialchars($e['payment_proof']??'') ?>"
                    data-status="<?= $e['status'] ?>"
                    data-date="<?= date('M j, Y g:i A',strtotime($e['enrolled_at'])) ?>"
                    data-occ="<?= htmlspecialchars($e['student_occupation']??'') ?>"
                    data-father="<?= htmlspecialchars($e['father_name']??'') ?>"
                    data-address="<?= htmlspecialchars($e['address']??'') ?>"
                    data-dob="<?= htmlspecialchars($e['dob']??'') ?>"
                    data-gender="<?= htmlspecialchars($e['gender']??'') ?>"
                    data-picture="<?= htmlspecialchars($e['student_picture']??'') ?>"
                    data-education="<?= htmlspecialchars($e['education_level']??'') ?>"
                    data-studentcard="<?= htmlspecialchars($e['student_card']??'') ?>">👁️</button>
                <a class="dots" href="mailto:<?= htmlspecialchars($e['student_email']) ?>" title="Email" style="text-decoration:none;">✉️</a>
                <?php if ($e['status']==='pending' || $e['status']==='reviewing'): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="update_enrollment" value="1">
                    <input type="hidden" name="enrollment_id"     value="<?= $e['id'] ?>">
                    <input type="hidden" name="status"             value="active">
                    <input type="hidden" name="current_tab"        value="<?= $esub ?>">
                    <button type="submit" class="dots" title="Approve" style="color:#2b7a2b;">✅</button>
                </form>
                <?php endif; ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this registration permanently?');">
                    <input type="hidden" name="delete_enrollment" value="1">
                    <input type="hidden" name="enrollment_id"     value="<?= $e['id'] ?>">
                    <button type="submit" class="dots" title="Delete" style="color:#ef4444;">🗑️</button>
                </form>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div style="text-align:center;padding:60px 24px;color:#888;">
            <div style="font-size:2.5rem;margin-bottom:8px;">📭</div>
            No registrations<?= $esub!=='all'?" with status \"$esub\"":'' ?> yet.
        </div>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ═══════ COURSES TAB ═══════ -->
    <div class="main-header" style="margin-top:4px;">
        <h3>📚 Manage Courses</h3>
        <button class="add-client" onclick="openCourseModal('add')">+ Add Course</button>
    </div>
    <div class="main-table">
        <div class="table-head" style="grid-template-columns:2.5fr 1.2fr 1fr .8fr 1fr 1fr 1fr;">
            <span>Course</span><span>Category</span><span>Price</span>
            <span>Level</span><span>Students</span><span>Status</span><span>Actions</span>
        </div>
        <?php if ($courses_result->num_rows>0): while($c=$courses_result->fetch_assoc()): ?>
        <div class="table-row" style="grid-template-columns:2.5fr 1.2fr 1fr .8fr 1fr 1fr 1fr;">
            <div class="client">
                <div class="avatar <?= $c['is_featured']?'orange':'blue' ?>" style="font-size:.85rem;"><?= $c['is_featured']?'⭐':'📚' ?></div>
                <div><strong style="font-size:13.5px;"><?= htmlspecialchars($c['title']) ?></strong><small><?= htmlspecialchars($c['instructor_name']??'Graphicafix') ?></small></div>
            </div>
            <span data-label="Category" style="font-size:13px;color:#666;"><?= htmlspecialchars($c['category']??'—') ?></span>
            <span style="font-size:13.5px;font-weight:700;color:<?= $c['is_free']?'#10b981':'var(--primary)' ?>;"><?= $c['is_free']?'Free':'Rs.'.number_format($c['price']) ?></span>
            <span style="font-size:12.5px;color:#888;"><?= $c['level'] ?></span>
            <span style="font-size:13.5px;font-weight:600;"><?= number_format($c['enroll_count']) ?></span>
            <span><span class="status <?= $c['is_published']?'active':'inactive' ?>"><?= $c['is_published']?'Published':'Draft' ?></span></span>
            <div style="display:flex;gap:3px;">
                <button class="dots" title="Edit" onclick="openCourseModal('edit',<?= $c['id'] ?>)">✏️</button>
                <form method="POST" style="display:inline;"><input type="hidden" name="toggle_publish" value="1"><input type="hidden" name="course_id" value="<?= $c['id'] ?>"><input type="hidden" name="current_pub" value="<?= $c['is_published'] ?>"><button type="submit" class="dots" title="<?= $c['is_published']?'Unpublish':'Publish' ?>"><?= $c['is_published']?'🙈':'🌐' ?></button></form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete course?')"><input type="hidden" name="delete_course" value="1"><input type="hidden" name="course_id" value="<?= $c['id'] ?>"><button type="submit" class="dots" style="color:#ef4444;">🗑️</button></form>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div style="text-align:center;padding:60px 24px;color:#888;">
            <div style="font-size:2.5rem;margin-bottom:8px;">📚</div>
            No courses yet. <a href="#" onclick="openCourseModal('add');return false;">Add your first →</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- /.main-card -->
</div>

<!-- ═══════ ENROLLMENT VIEW MODAL ═══════ -->
<div id="enrollModal" class="modal-overlay" onclick="if(event.target===this)closeEnrollModal()">
    <div class="modal-content" style="max-width:680px;">
        <div class="modal-header">
            <h4><div class="modal-header-icon">🎓</div><span>Registration Details</span></h4>
            <button class="modal-close" onclick="closeEnrollModal()">×</button>
        </div>
        <div class="modal-body">
            <div id="emPictureWrap" style="text-align:center;margin-bottom:18px;display:none;">
                <img id="emPictureImg" src="" style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid var(--primary);box-shadow:0 4px 12px rgba(0,0,0,.1);" onerror="this.parentElement.style.display='none'">
            </div>
            <div class="info-grid">
                <div class="info-row"><div class="info-icon">👤</div><div class="info-content"><div class="info-label">Student</div><div class="info-value" id="emName"></div></div></div>
                <div class="info-row" id="emFatherRow" style="display:none;"><div class="info-icon">👔</div><div class="info-content"><div class="info-label">Father / Husband</div><div class="info-value" id="emFather"></div></div></div>
                <div class="info-row"><div class="info-icon">✉️</div><div class="info-content"><div class="info-label">Email</div><div class="info-value" id="emEmail"></div></div></div>
                <div class="info-row"><div class="info-icon">📞</div><div class="info-content"><div class="info-label">Phone</div><div class="info-value" id="emPhone"></div></div></div>
                <div class="info-row" id="emAddressRow" style="display:none;"><div class="info-icon">📍</div><div class="info-content"><div class="info-label">Address</div><div class="info-value" id="emAddress"></div></div></div>
                <div class="info-row" id="emDobRow" style="display:none;"><div class="info-icon">🎂</div><div class="info-content"><div class="info-label">Date of Birth</div><div class="info-value" id="emDob"></div></div></div>
                <div class="info-row" id="emGenderRow" style="display:none;"><div class="info-icon">⚧</div><div class="info-content"><div class="info-label">Gender</div><div class="info-value" id="emGender"></div></div></div>
                <div class="info-row" id="emOccRow"><div class="info-icon">💼</div><div class="info-content"><div class="info-label">Occupation</div><div class="info-value" id="emOcc"></div></div></div>
                <div class="info-row" id="emEducationRow" style="display:none;"><div class="info-icon">🎓</div><div class="info-content"><div class="info-label">Education Level</div><div class="info-value" id="emEducation"></div></div></div>
                <div class="info-row"><div class="info-icon">📚</div><div class="info-content"><div class="info-label">Course</div><div class="info-value" id="emCourse"></div></div></div>
                <div class="info-row"><div class="info-icon">💰</div><div class="info-content"><div class="info-label">Amount Paid</div><div class="info-value" id="emPaid"></div></div></div>
                <div class="info-row"><div class="info-icon">🏷️</div><div class="info-content"><div class="info-label">Discount Code</div><div class="info-value" id="emCode"></div></div></div>
                <div class="info-row"><div class="info-icon">📅</div><div class="info-content"><div class="info-label">Registered On</div><div class="info-value" id="emDate"></div></div></div>
                <div class="info-row"><div class="info-icon">🔘</div><div class="info-content"><div class="info-label">Status</div><div class="info-value" id="emStatus"></div></div></div>
            </div>
            <!-- Motivation -->
            <div class="message-box" id="emMotivBox" style="margin-bottom:16px;">
                <span class="message-label">💬 Motivation / Goals</span>
                <div class="message-content" id="emMotiv"></div>
            </div>
            <!-- Student Card -->
            <div class="message-box" id="emStudentCardBox" style="margin-bottom:16px;display:none;">
                <span class="message-label">🪪 CNIC / Student Card</span>
                <div class="message-content" id="emStudentCardWrap"></div>
            </div>
            <!-- Payment proof -->
            <div class="message-box" id="emPaymentBox">
                <span class="message-label">📎 Payment Screenshot</span>
                <div class="message-content" id="emPayment"></div>
            </div>
            <!-- Status update panel -->
            <div style="margin-top:18px;padding:16px;background:#f9fbfc;border-radius:12px;">
                <div style="font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">Update Status (sends email automatically)</div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;" id="emStatusBtns"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary-custom" onclick="closeEnrollModal()">Close</button>
            <a id="emReplyLink" href="#" class="btn btn-primary-custom">✉️ Email Student</a>
        </div>
    </div>
</div>

<!-- ═══════ COURSE ADD/EDIT MODAL ═══════ -->
<div id="courseModal" class="modal-overlay" onclick="if(event.target===this)closeCourseModal()">
    <div class="modal-content">
        <div class="modal-header">
            <h4><div class="modal-header-icon" id="cIcon">📚</div><span id="cTitle">Add Course</span></h4>
            <button class="modal-close" onclick="closeCourseModal()">×</button>
        </div>
        <form method="POST" id="courseForm">
            <input type="hidden" name="add_course"  id="cAdd"  value="1">
            <input type="hidden" name="edit_course" id="cEdit" value="" disabled>
            <input type="hidden" name="course_id"   id="cId"   value="">
            <div class="modal-body">
                <div class="info-grid">
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Course Title <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="c_title" id="cFTitle" required placeholder="e.g. Complete Brand Design Masterclass"
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;">
                    </div>
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Tagline</label>
                        <input type="text" name="c_tagline" id="cFTagline" placeholder="Short compelling description"
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div><label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Category</label>
                        <input type="text" name="c_category" id="cFCat" placeholder="e.g. Branding"
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;"></div>
                        <div><label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Level</label>
                        <select name="c_level" id="cFLevel"
                                style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;">
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate">Intermediate</option>
                            <option value="Advanced">Advanced</option>
                        </select></div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div><label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Price (Rs.)</label>
                        <input type="number" name="c_price" id="cFPrice" placeholder="0" min="0"
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;"></div>
                        <div><label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Duration (mins)</label>
                        <input type="number" name="c_duration" id="cFDur" placeholder="e.g. 480"
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;"></div>
                    </div>
                    <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:center;">
                        <label style="display:flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:#333;cursor:pointer;">
                            <input type="checkbox" name="c_is_free"      id="cFree"    style="width:16px;height:16px;accent-color:var(--primary);"> Free Course</label>
                        <label style="display:flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:#333;cursor:pointer;">
                            <input type="checkbox" name="c_is_featured"  id="cFeat"    style="width:16px;height:16px;accent-color:var(--primary);"> Featured</label>
                        <label id="cPubRow" style="display:flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:#333;cursor:pointer;">
                            <input type="checkbox" name="c_is_published" id="cPub"     style="width:16px;height:16px;accent-color:var(--primary);"> Published</label>
                    </div>
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Description</label>
                        <textarea name="c_description" id="cFDesc" rows="3" placeholder="What will students learn?"
                                  style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;resize:vertical;box-sizing:border-box;font-family:inherit;"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeCourseModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="cSubmit">📚 Add Course</button>
            </div>
        </form>
    </div>
</div>

<div id="toastContainer" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;"></div>

<script>
const coursesData = <?= json_encode($courses_js) ?>;
const currentEsub = '<?= $esub ?>';

const enrollStatuses = {
    pending:   { label:'Mark Pending',   bg:'#fef9c3', color:'#a16207' },
    reviewing: { label:'Mark Reviewing', bg:'#e0f2fe', color:'#0284c7' },
    active:    { label:'Approve',        bg:'#d7f8b8', color:'#2b7a2b' },
    completed: { label:'Complete',       bg:'#dbeaff', color:'#1b4ed8' },
    rejected:  { label:'Reject',         bg:'#ffe5e5', color:'#d63031' },
    cancelled: { label:'Cancel',         bg:'#f0f0f0', color:'#666'    },
};

// ── Enrollment view modal ─────────────────────────────────────────────────────
document.querySelectorAll('.view-enrollment').forEach(btn => {
    btn.addEventListener('click', function() {
        const d      = this.dataset;
        const status = d.status;
        const email  = d.email;
        const paid   = parseFloat(d.paid)||0;

        document.getElementById('emName').textContent    = d.name;
        document.getElementById('emPhone').textContent   = d.phone   || '—';
        document.getElementById('emOcc').textContent     = d.occ     || '—';
        document.getElementById('emCourse').textContent  = d.course;
        document.getElementById('emCode').textContent    = d.code    || '—';
        document.getElementById('emDate').textContent    = d.date;
        document.getElementById('emMotiv').textContent   = d.motive  || 'Not provided.';
        document.getElementById('emEmail').innerHTML     = `<a href="mailto:${email}" style="color:var(--primary);">${email}</a>`;
        document.getElementById('emReplyLink').href      = `mailto:${email}?subject=Re: Course Registration`;

        // New fields
        const showField = (rowId, valId, val) => {
            if (val && val.trim()) { document.getElementById(rowId).style.display = ''; document.getElementById(valId).textContent = val; }
            else { document.getElementById(rowId).style.display = 'none'; }
        };
        showField('emFatherRow', 'emFather', d.father);
        showField('emAddressRow', 'emAddress', d.address);
        showField('emGenderRow', 'emGender', d.gender);
        showField('emEducationRow', 'emEducation', d.education);
        if (d.dob && d.dob.trim() && d.dob !== '0000-00-00') {
            document.getElementById('emDobRow').style.display = '';
            const dobDate = new Date(d.dob);
            document.getElementById('emDob').textContent = dobDate.toLocaleDateString('en-US', {year:'numeric',month:'long',day:'numeric'});
        } else { document.getElementById('emDobRow').style.display = 'none'; }

        // Student picture
        const picWrap = document.getElementById('emPictureWrap');
        if (d.picture && d.picture.trim()) {
            document.getElementById('emPictureImg').src = d.picture;
            picWrap.style.display = 'block';
        } else { picWrap.style.display = 'none'; }

        document.getElementById('emPaid').innerHTML      = paid>0
            ? `<strong>Rs. ${paid.toLocaleString()}</strong>`
            : `<span style="color:#10b981;font-weight:700;">Free</span>`;

        // Status badge
        const sb = enrollStatuses[status] || { label:status, bg:'#f0f0f0', color:'#555' };
        document.getElementById('emStatus').innerHTML =
            `<span style="background:${sb.bg};color:${sb.color};padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;">${sb.label.replace(/Mark |Approve|Complete|Reject|Cancel/,'').trim()||status}</span>`;

        // Payment proof
        const pBox = document.getElementById('emPaymentBox');
        const pWrap= document.getElementById('emPayment');
        if (d.payment) {
            pBox.style.display = '';
            pWrap.innerHTML = `<a href="${d.payment}" target="_blank" class="btn btn-primary-custom" style="display:inline-flex;margin-top:4px;align-items:center;gap:6px;">📎 View Screenshot</a>`;
        } else {
            pBox.style.display = paid > 0 ? '' : 'none';
            pWrap.innerHTML = paid > 0
                ? '<span style="color:#d63031;font-weight:600;">⚠️ Payment proof not uploaded</span>'
                : '<span style="color:#aaa;">Free course — no payment required</span>';
        }

        // Student Card preview
        const cardBox = document.getElementById('emStudentCardBox');
        const cardWrap = document.getElementById('emStudentCardWrap');
        if (d.studentcard && d.studentcard.trim() && d.occ === 'student') {
            cardBox.style.display = '';
            cardWrap.innerHTML = `<a href="${d.studentcard}" target="_blank" class="btn btn-primary-custom" style="display:inline-flex;margin-top:4px;align-items:center;gap:6px;">🪪 View ID Card</a>`;
        } else { cardBox.style.display = 'none'; }

        // Motivation box
        document.getElementById('emMotivBox').style.display = d.motive ? '' : 'none';

        // Status buttons
        const btns = document.getElementById('emStatusBtns');
        btns.innerHTML = '';
        const eid = d.id;
        Object.entries(enrollStatuses).forEach(([key,val]) => {
            const f = document.createElement('form');
            f.method='POST'; f.style.display='inline';
            f.innerHTML = `<input type="hidden" name="update_enrollment" value="1">
                <input type="hidden" name="enrollment_id" value="${eid}">
                <input type="hidden" name="status" value="${key}">
                <input type="hidden" name="current_tab" value="${currentEsub}">
                <button type="submit" style="background:${val.bg};color:${val.color};border:none;padding:7px 14px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;opacity:${key===status?'.4':'1'};${key===status?'pointer-events:none;':''}" ${key===status?'disabled':''}>
                    ${val.label}
                </button>`;
            btns.appendChild(f);
        });

        document.getElementById('enrollModal').classList.add('active');
        document.body.style.overflow='hidden';
    });
});
function closeEnrollModal(){ document.getElementById('enrollModal').classList.remove('active'); document.body.style.overflow=''; }

// ── Course modal ──────────────────────────────────────────────────────────────
function openCourseModal(mode, id) {
    const isEdit = mode==='edit';
    document.getElementById('cTitle').textContent   = isEdit ? 'Edit Course' : 'Add Course';
    document.getElementById('cIcon').textContent    = isEdit ? '✏️' : '📚';
    document.getElementById('cSubmit').textContent  = isEdit ? '✏️ Update Course' : '📚 Add Course';
    document.getElementById('cAdd').disabled        = isEdit;
    document.getElementById('cEdit').disabled       = !isEdit;
    document.getElementById('cEdit').value          = isEdit ? '1' : '';
    document.getElementById('cId').value            = isEdit ? id : '';
    document.getElementById('cPubRow').style.display= isEdit ? '' : 'none';

    if (isEdit && coursesData[id]) {
        const c = coursesData[id];
        document.getElementById('cFTitle').value   = c.title         || '';
        document.getElementById('cFTagline').value = c.tagline       || '';
        document.getElementById('cFCat').value     = c.category      || '';
        document.getElementById('cFLevel').value   = c.level         || 'Beginner';
        document.getElementById('cFPrice').value   = c.price         || '';
        document.getElementById('cFDur').value     = c.duration_mins || '';
        document.getElementById('cFDesc').value    = c.description   || '';
        document.getElementById('cFree').checked   = !!parseInt(c.is_free);
        document.getElementById('cFeat').checked   = !!parseInt(c.is_featured);
        document.getElementById('cPub').checked    = !!parseInt(c.is_published);
    } else {
        document.getElementById('courseForm').reset();
    }
    document.getElementById('courseModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeCourseModal(){ document.getElementById('courseModal').classList.remove('active'); document.body.style.overflow=''; }

// ── Toasts ────────────────────────────────────────────────────────────────────
function showToast(msg, type='success') {
    const t = document.createElement('div');
    t.style.cssText = `background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:220px;border-left:4px solid ${type==='success'?'#10b981':'#ef4444'};z-index:9999;`;
    t.innerHTML = `<span style="font-weight:700;color:${type==='success'?'#10b981':'#ef4444'}">${type==='success'?'✓':'✕'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(()=>{ t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3500);
}
document.addEventListener('DOMContentLoaded',()=>{
    <?php if(isset($_GET['updated'])):      ?> showToast('Status updated — email sent to student.'); <?php endif; ?>
    <?php if(isset($_GET['deleted'])):      ?> showToast('Deleted.');        <?php endif; ?>
    <?php if(isset($_GET['course_added'])): ?> showToast('Course added!');   <?php endif; ?>
});
document.addEventListener('keydown', e => { if (e.key==='Escape'){ closeEnrollModal(); closeCourseModal(); } });
</script>

<?php include('dashboard_footer.php'); ?>
