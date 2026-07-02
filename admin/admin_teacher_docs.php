<?php
include 'dashboard_header.php';

// Admin only
if ($role !== 'admin') {
    echo "<script>alert('Access denied');window.location='index.php';</script>";
    exit;
}

// Stats
$total = $conn->query("SELECT COUNT(*) as c FROM teacher_documents")->fetch_assoc()['c'] ?? 0;
$pending = $conn->query("SELECT COUNT(*) as c FROM teacher_documents WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0;
$approved = $conn->query("SELECT COUNT(*) as c FROM teacher_documents WHERE status = 'approved'")->fetch_assoc()['c'] ?? 0;
$rejected = $conn->query("SELECT COUNT(*) as c FROM teacher_documents WHERE status = 'rejected'")->fetch_assoc()['c'] ?? 0;

// All documents with teacher name
$docs = $conn->query("
    SELECT d.*, u.name as teacher_name 
    FROM teacher_documents d 
    JOIN users u ON d.teacher_id = u.user_id 
    ORDER BY CASE WHEN d.status = 'pending' THEN 1 WHEN d.status = 'approved' THEN 2 ELSE 3 END, d.created_at DESC
");
?>
<div class="height-100">
    <div class="main-header" style="margin-bottom:20px;">
        <h3><i class="fas fa-file-signature"></i> Teacher Documents</h3>
        <p style="color:#666; font-size:14px;">Review and approve documents uploaded by teachers.</p>
    </div>

    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
            <div class="stat-number"><?= $total ?></div>
            <div class="stat-label">Total Documents</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?= $pending ?></div>
            <div class="stat-label">Pending Review</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?= $approved ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card red">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-number"><?= $rejected ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <div class="main-card">
        <div class="main-header" style="border-bottom:none; padding-bottom:10px;">
            <h4 style="font-size:1.1rem; font-weight:800; color:#333; margin:0;">Document Queue</h4>
            <div style="display:flex; gap:10px;">
                <button class="btn btn-secondary-custom filter-btn" onclick="filterDocs('all')" style="border-color:var(--accent);">All</button>
                <button class="btn btn-secondary-custom filter-btn" onclick="filterDocs('pending')">Pending</button>
                <button class="btn btn-secondary-custom filter-btn" onclick="filterDocs('approved')">Approved</button>
                <button class="btn btn-secondary-custom filter-btn" onclick="filterDocs('rejected')">Rejected</button>
            </div>
        </div>

        <div class="main-table">
            <div class="table-head" style="grid-template-columns:1.5fr 2fr 1fr 1fr 1.5fr;">
                <span>Teacher</span>
                <span>Document Details</span>
                <span>Date</span>
                <span>Status</span>
                <span>Actions</span>
            </div>
            
            <?php if($docs && $docs->num_rows > 0): while($d = $docs->fetch_assoc()): 
                $badge_bg = $d['status'] === 'approved' ? '#d1fae5' : ($d['status'] === 'rejected' ? '#fee2e2' : '#fef3c7');
                $badge_col = $d['status'] === 'approved' ? '#059669' : ($d['status'] === 'rejected' ? '#dc2626' : '#d97706');
            ?>
            <div class="table-row doc-row" data-status="<?= $d['status'] ?>" style="grid-template-columns:1.5fr 2fr 1fr 1fr 1.5fr; align-items:center;">
                <div style="font-weight:600; color:var(--primary);"><i class="fas fa-chalkboard-teacher" style="color:#aaa;"></i> <?= htmlspecialchars($d['teacher_name']) ?></div>
                
                <div>
                    <div style="font-weight:700; font-size:14px; color:#333;"><?= htmlspecialchars($d['title']) ?></div>
                    <a href="<?= htmlspecialchars($d['file_path']) ?>" target="_blank" style="font-size:12px; color:#0284c7; text-decoration:none;"><i class="fas fa-download"></i> Download File</a>
                </div>
                
                <span data-label="Date" style="font-size:13px; color:#666;"><?= date('M d, Y', strtotime($d['created_at'])) ?></span>
                
                <span data-label="Status">
                    <span style="background:<?= $badge_bg ?>; color:<?= $badge_col ?>; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:800; text-transform:uppercase;">
                        <?= $d['status'] ?>
                    </span>
                </span>
                
                <div data-label="Actions" style="display:flex; gap:8px; align-items:center;">
                    <?php if($d['status'] === 'pending'): ?>
                        <button onclick="openReview(<?= $d['id'] ?>)" class="btn btn-primary-custom" style="padding:6px 12px; font-size:12px;">Review</button>
                    <?php else: ?>
                        <button onclick="alert('Admin Notes:\n<?= htmlspecialchars(addslashes($d['admin_notes'])) ?>')" class="btn btn-secondary-custom" style="padding:6px 12px; font-size:12px;">View Notes</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; else: ?>
                <div style="text-align:center; padding:30px; color:#888;">No documents found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal-overlay" id="reviewModal" onclick="if(event.target===this)closeModal()">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h4 style="display:flex;align-items:center;gap:10px;"><div class="modal-header-icon"><i class="fas fa-search"></i></div> Review Document</h4>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="rev_id">
            <div class="form-group">
                <label>Admin Notes / Feedback</label>
                <textarea id="rev_notes" rows="4" placeholder="Enter notes for the teacher..."></textarea>
            </div>
            
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button onclick="submitReview('approve')" class="btn btn-primary-custom" style="flex:1; background:#10b981;"><i class="fas fa-check"></i> Approve</button>
                <button onclick="submitReview('reject')" class="btn btn-primary-custom" style="flex:1; background:#ef4444;"><i class="fas fa-times"></i> Reject</button>
            </div>
        </div>
    </div>
</div>

<script>
function filterDocs(status) {
    document.querySelectorAll('.filter-btn').forEach(b => b.style.borderColor = 'transparent');
    event.target.style.borderColor = 'var(--accent)';
    
    document.querySelectorAll('.doc-row').forEach(row => {
        if(status === 'all' || row.dataset.status === status) row.style.display = 'grid';
        else row.style.display = 'none';
    });
}

function openReview(id) {
    document.getElementById('rev_id').value = id;
    document.getElementById('rev_notes').value = '';
    document.getElementById('reviewModal').classList.add('active');
}

function closeModal() {
    document.getElementById('reviewModal').classList.remove('active');
}

function submitReview(action) {
    let id = document.getElementById('rev_id').value;
    let notes = document.getElementById('rev_notes').value;
    
    if(action === 'reject' && !notes.trim()) {
        alert('Please provide a reason for rejection in the notes.'); return;
    }
    
    let fd = new FormData();
    fd.append('action', action);
    fd.append('id', id);
    fd.append('notes', notes);
    
    let btn = event.target;
    btn.disabled = true;
    btn.innerText = 'Saving...';
    
    fetch('document_action.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if(data.success) location.reload();
        else { alert(data.message || 'Error'); btn.disabled = false; }
    })
    .catch(err => { alert('Network error'); btn.disabled = false; });
}
</script>

<?php include('dashboard_footer.php'); ?>
