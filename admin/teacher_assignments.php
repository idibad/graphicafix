<?php
include('dashboard_header.php');

$user_id = intval($_SESSION['user_id'] ?? 0);

// Fetch teacher's courses for dropdown
$courses = $conn->query("SELECT id, title FROM courses WHERE instructor_id = $user_id");

// Stats
$total_assignments = $conn->query("SELECT COUNT(*) as c FROM assignments WHERE teacher_id = $user_id")->fetch_assoc()['c'] ?? 0;
$ungraded = $conn->query("SELECT COUNT(*) as c FROM assignment_submissions s JOIN assignments a ON s.assignment_id = a.id WHERE a.teacher_id = $user_id AND s.grade IS NULL")->fetch_assoc()['c'] ?? 0;
$graded = $conn->query("SELECT COUNT(*) as c FROM assignment_submissions s JOIN assignments a ON s.assignment_id = a.id WHERE a.teacher_id = $user_id AND s.grade IS NOT NULL")->fetch_assoc()['c'] ?? 0;

// Fetch assignments
$assignments = $conn->query("
    SELECT a.*, c.title as course_title,
        (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id) as total_subs,
        (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.grade IS NULL) as pending_subs
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    WHERE a.teacher_id = $user_id
    ORDER BY a.created_at DESC
");
?>
<div class="height-100">
    <div class="main-header" style="margin-bottom:20px;">
        <h3><i class="fas fa-tasks"></i> Manage Assignments</h3>
        <p style="color:#666; font-size:14px;">Create assignments and grade student submissions.</p>
    </div>

    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-list-alt"></i></div>
            <div class="stat-number"><?= $total_assignments ?></div>
            <div class="stat-label">Total Assignments</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?= $ungraded ?></div>
            <div class="stat-label">Ungraded Subs</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?= $graded ?></div>
            <div class="stat-label">Graded Subs</div>
        </div>
    </div>

    <div class="main-card">
        <div class="main-header" style="border-bottom:none; padding-bottom:10px;">
            <h4 style="font-size:1.1rem; font-weight:800; color:#333; margin:0;">All Assignments</h4>
            <button onclick="document.getElementById('createModal').classList.add('active')" class="btn btn-primary-custom" style="padding:8px 16px;"><i class="fas fa-plus"></i> New Assignment</button>
        </div>

        <div class="main-table">
            <div class="table-head" style="grid-template-columns:2fr 1.5fr 1fr 1fr 1fr;">
                <span>Title</span>
                <span>Course</span>
                <span>Due Date</span>
                <span>Submissions</span>
                <span>Actions</span>
            </div>
            
            <?php if($assignments && $assignments->num_rows > 0): while($a = $assignments->fetch_assoc()): 
                $is_overdue = strtotime($a['due_date']) < time();
            ?>
            <div class="table-row" style="grid-template-columns:2fr 1.5fr 1fr 1fr 1fr; align-items:center;">
                <div>
                    <div style="font-weight:700; font-size:14px; color:#333; margin-bottom:4px;"><?= htmlspecialchars($a['title']) ?></div>
                </div>
                
                <span data-label="Course" style="font-size:13px; color:#666;"><?= htmlspecialchars($a['course_title']) ?></span>
                
                <span data-label="Due Date" style="font-size:13px; color:<?= $is_overdue ? '#ef4444' : '#666' ?>; font-weight:<?= $is_overdue ? '700' : '400' ?>;">
                    <?= date('M d, Y', strtotime($a['due_date'])) ?>
                </span>
                
                <span data-label="Submissions">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="background:#e0f2fe; color:#0284c7; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:800;"><?= $a['total_subs'] ?> Total</span>
                        <?php if($a['pending_subs'] > 0): ?>
                            <span style="background:#fef3c7; color:#d97706; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:800;"><?= $a['pending_subs'] ?> New</span>
                        <?php endif; ?>
                    </div>
                </span>
                
                <div data-label="Actions" style="display:flex; gap:8px; align-items:center;">
                    <button onclick="viewSubs(<?= $a['id'] ?>)" class="btn btn-secondary-custom" style="padding:6px 12px; font-size:12px;">View Subs</button>
                    <button onclick="deleteAssign(<?= $a['id'] ?>)" class="btn btn-secondary-custom" style="padding:6px 10px; font-size:12px; color:#ef4444;"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <?php endwhile; else: ?>
                <div style="text-align:center; padding:30px; color:#888;">No assignments created yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Modal -->
<div class="modal-overlay" id="createModal" onclick="if(event.target===this)closeModal('createModal')">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h4 style="display:flex;align-items:center;gap:10px;"><div class="modal-header-icon"><i class="fas fa-plus"></i></div> Create Assignment</h4>
            <button class="modal-close" onclick="closeModal('createModal')">×</button>
        </div>
        <div class="modal-body">
            <form id="createForm" onsubmit="submitCreate(event)">
                <div class="form-group" style="margin-bottom:15px;">
                    <label>Select Course <span class="required">*</span></label>
                    <select name="course_id" required>
                        <?php if($courses): $courses->data_seek(0); while($c = $courses->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom:15px;">
                    <label>Assignment Title <span class="required">*</span></label>
                    <input type="text" name="title" required placeholder="e.g. Week 1 Project">
                </div>
                
                <div class="form-group" style="margin-bottom:15px;">
                    <label>Description / Instructions</label>
                    <textarea name="description" rows="4" placeholder="What should the students do?"></textarea>
                </div>
                
                <div class="form-group" style="margin-bottom:20px;">
                    <label>Due Date <span class="required">*</span></label>
                    <input type="date" name="due_date" required>
                </div>
                
                <button type="submit" class="btn btn-primary-custom" style="width:100%;"><i class="fas fa-plus"></i> Create Assignment</button>
            </form>
        </div>
    </div>
</div>

<!-- Submissions Modal -->
<div class="modal-overlay" id="subsModal" onclick="if(event.target===this)closeModal('subsModal')">
    <div class="modal-content" style="max-width:700px; width:95%;">
        <div class="modal-header">
            <h4 style="display:flex;align-items:center;gap:10px;"><div class="modal-header-icon"><i class="fas fa-users"></i></div> Submissions</h4>
            <button class="modal-close" onclick="closeModal('subsModal')">×</button>
        </div>
        <div class="modal-body" style="background:#f9f9f9; padding:20px; max-height:60vh; overflow-y:auto;" id="subsContainer">
            <!-- Loaded via AJAX -->
            <div style="text-align:center; padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
    </div>
</div>

<!-- Grade Modal -->
<div class="modal-overlay" id="gradeModal" style="z-index:1100;" onclick="if(event.target===this)closeModal('gradeModal')">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header" style="padding:20px;">
            <h4 style="font-size:1.1rem; margin:0;"><i class="fas fa-check-circle" style="color:var(--accent);"></i> Grade Submission</h4>
            <button class="modal-close" onclick="closeModal('gradeModal')">×</button>
        </div>
        <div class="modal-body" style="padding:20px;">
            <form id="gradeForm" onsubmit="submitGrade(event)">
                <input type="hidden" name="submission_id" id="g_sub_id">
                
                <div class="form-group" style="margin-bottom:15px;">
                    <label>Grade (e.g. A, B+, 95/100) <span class="required">*</span></label>
                    <input type="text" name="grade" id="g_grade" required>
                </div>
                
                <div class="form-group" style="margin-bottom:20px;">
                    <label>Feedback Notes (Optional)</label>
                    <textarea name="feedback" id="g_feedback" rows="3" placeholder="Great job on..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary-custom" style="width:100%;"><i class="fas fa-save"></i> Save Grade</button>
            </form>
        </div>
    </div>
</div>

<script>
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function submitCreate(e) {
    e.preventDefault();
    let fd = new FormData(e.target);
    fd.append('action', 'create');
    
    let btn = e.target.querySelector('button');
    btn.disabled = true; btn.innerText = 'Creating...';
    
    fetch('assignment_action.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if(data.success) location.reload();
        else { alert(data.message || 'Error'); btn.disabled = false; btn.innerText = 'Create Assignment'; }
    });
}

function deleteAssign(id) {
    if(confirm('Delete this assignment and all submissions?')) {
        let fd = new FormData();
        fd.append('action', 'delete'); fd.append('id', id);
        fetch('assignment_action.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { if(data.success) location.reload(); else alert('Error'); });
    }
}

let currentAid = 0;
function viewSubs(aid) {
    currentAid = aid;
    document.getElementById('subsModal').classList.add('active');
    document.getElementById('subsContainer').innerHTML = '<div style="text-align:center; padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    let fd = new FormData();
    fd.append('action', 'get_submissions'); fd.append('assignment_id', aid);
    fetch('assignment_action.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            let html = '';
            if(data.data.length === 0) html = '<div style="text-align:center; color:#888; padding:20px;">No submissions yet.</div>';
            else {
                data.data.forEach(s => {
                    let gradeBadge = s.grade ? `<span style="background:#d1fae5; color:#059669; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:700;">Graded: ${s.grade}</span>` : `<span style="background:#fef3c7; color:#d97706; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:700;">Needs Grading</span>`;
                    html += `
                    <div style="background:#fff; border:1px solid #eee; border-radius:10px; padding:15px; margin-bottom:12px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <div style="font-weight:700; color:var(--primary);"><i class="fas fa-user-circle"></i> ${s.student_name}</div>
                            ${gradeBadge}
                        </div>
                        <div style="font-size:12px; color:#888; margin-bottom:10px;"><i class="fas fa-clock"></i> Submitted: ${s.submitted_at}</div>
                        
                        <div style="background:#f9fafb; padding:10px; border-radius:6px; margin-bottom:10px;">
                            <a href="${s.file_path}" target="_blank" style="color:#0284c7; text-decoration:none; font-weight:600; font-size:13px; display:inline-block; margin-bottom:5px;"><i class="fas fa-file-download"></i> Download File</a>
                            ${s.notes ? `<div style="font-size:13px; color:#555; border-top:1px solid #eee; padding-top:5px; margin-top:5px;"><strong>Notes:</strong> ${s.notes}</div>` : ''}
                        </div>
                        
                        <button onclick="openGrade(${s.id}, '${s.grade||''}', '${(s.feedback||'').replace(/'/g,"\\'").replace(/\n/g,"\\n")}')" class="btn btn-secondary-custom" style="padding:6px 12px; font-size:12px;">
                            ${s.grade ? '<i class="fas fa-edit"></i> Edit Grade' : '<i class="fas fa-check"></i> Give Grade'}
                        </button>
                    </div>`;
                });
            }
            document.getElementById('subsContainer').innerHTML = html;
        } else {
            document.getElementById('subsContainer').innerHTML = '<div style="color:red; text-align:center;">Failed to load.</div>';
        }
    });
}

function openGrade(sid, grade, feedback) {
    document.getElementById('g_sub_id').value = sid;
    document.getElementById('g_grade').value = grade;
    document.getElementById('g_feedback').value = feedback;
    document.getElementById('gradeModal').classList.add('active');
}

function submitGrade(e) {
    e.preventDefault();
    let fd = new FormData(e.target);
    fd.append('action', 'grade');
    
    let btn = e.target.querySelector('button');
    btn.disabled = true; btn.innerText = 'Saving...';
    
    fetch('assignment_action.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false; btn.innerText = 'Save Grade';
        if(data.success) {
            closeModal('gradeModal');
            viewSubs(currentAid); // reload subs list
        } else alert('Error saving grade');
    });
}
</script>

<?php include('dashboard_footer.php'); ?>
