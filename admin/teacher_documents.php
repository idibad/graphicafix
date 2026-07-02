<?php
include 'dashboard_header.php';

$user_id = intval($_SESSION['user_id'] ?? 0);

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_doc'])) {
    $title = trim($_POST['doc_title'] ?? '');
    $desc = trim($_POST['doc_description'] ?? '');
    
    if (!$title || empty($_FILES['doc_file']['name'])) {
        $error = 'Title and file are required.';
    } else {
        $upload_dir = 'uploads/teacher_docs/' . date('Y_m');
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_name = time() . '_' . basename($_FILES['doc_file']['name']);
        $file_path = $upload_dir . '/' . $file_name;
        move_uploaded_file($_FILES['doc_file']['tmp_name'], $file_path);
        
        $stmt = $conn->prepare("INSERT INTO teacher_documents (teacher_id, title, description, file_path) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $title, $desc, $file_path);
        $stmt->execute();
        header("Location: teacher_documents.php?uploaded=1"); exit;
    }
}

// Stats
$total_docs = $conn->query("SELECT COUNT(*) as c FROM teacher_documents WHERE teacher_id = $user_id")->fetch_assoc()['c'] ?? 0;
$pending_docs = $conn->query("SELECT COUNT(*) as c FROM teacher_documents WHERE teacher_id = $user_id AND status = 'pending'")->fetch_assoc()['c'] ?? 0;
$approved_docs = $conn->query("SELECT COUNT(*) as c FROM teacher_documents WHERE teacher_id = $user_id AND status = 'approved'")->fetch_assoc()['c'] ?? 0;
$rejected_docs = $conn->query("SELECT COUNT(*) as c FROM teacher_documents WHERE teacher_id = $user_id AND status = 'rejected'")->fetch_assoc()['c'] ?? 0;

// Documents
$docs = $conn->query("SELECT * FROM teacher_documents WHERE teacher_id = $user_id ORDER BY created_at DESC");
?>
<div class="height-100">
    <div class="main-header" style="margin-bottom:20px;">
        <h3><i class="fas fa-folder-open"></i> My Documents</h3>
        <p style="color:#666; font-size:14px;">Upload documents for admin review and approval.</p>
    </div>

    <?php if(isset($_GET['uploaded'])): ?>
        <div style="background:#10b981; color:#fff; padding:12px 20px; border-radius:8px; margin-bottom:20px; font-weight:600;"><i class="fas fa-check-circle"></i> Document uploaded successfully!</div>
    <?php endif; ?>
    <?php if(isset($error)): ?>
        <div style="background:#fee2e2; color:#ef4444; padding:12px 20px; border-radius:8px; margin-bottom:20px; font-weight:600;"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
            <div class="stat-number"><?= $total_docs ?></div>
            <div class="stat-label">Total Uploaded</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?= $pending_docs ?></div>
            <div class="stat-label">Pending Review</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?= $approved_docs ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card red">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-number"><?= $rejected_docs ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <div class="grid-2-col">
        <!-- Upload Form -->
        <div class="d-card">
            <h3 class="d-card-title"><i class="fas fa-cloud-upload-alt" style="color:var(--primary);"></i> Upload New Document</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="upload_doc" value="1">
                
                <div class="form-group" style="margin-bottom:15px;">
                    <label>Document Title <span class="required">*</span></label>
                    <input type="text" name="doc_title" required placeholder="e.g. Graphic Design Syllabus 2026">
                </div>
                
                <div class="form-group" style="margin-bottom:15px;">
                    <label>Description (Optional)</label>
                    <textarea name="doc_description" rows="3" placeholder="Brief details about this document..."></textarea>
                </div>
                
                <div class="form-group" style="margin-bottom:20px;">
                    <label>Select File (PDF, DOCX, etc) <span class="required">*</span></label>
                    <input type="file" name="doc_file" required>
                </div>
                
                <button type="submit" class="btn btn-primary-custom" style="width:100%;"><i class="fas fa-upload"></i> Upload to Admin</button>
            </form>
        </div>

        <!-- Document List -->
        <div class="d-card" style="height:fit-content;">
            <h3 class="d-card-title"><i class="fas fa-history" style="color:#f59e0b;"></i> Upload History</h3>
            
            <?php if($docs && $docs->num_rows > 0): ?>
                <div style="display:flex; flex-direction:column; gap:12px;">
                <?php while($d = $docs->fetch_assoc()): 
                    $badge_class = $d['status'] === 'approved' ? 'bg-[#d1fae5] text-[#059669]' : ($d['status'] === 'rejected' ? 'bg-[#fee2e2] text-[#dc2626]' : 'bg-[#fef3c7] text-[#d97706]');
                    $badge_color = $d['status'] === 'approved' ? '#059669' : ($d['status'] === 'rejected' ? '#dc2626' : '#d97706');
                    $badge_bg = $d['status'] === 'approved' ? '#d1fae5' : ($d['status'] === 'rejected' ? '#fee2e2' : '#fef3c7');
                ?>
                    <div style="border:1px solid #eaeaea; border-radius:10px; padding:15px; position:relative;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                            <div>
                                <h4 style="margin:0 0 4px; font-size:14px; font-weight:700; color:#333;"><?= htmlspecialchars($d['title']) ?></h4>
                                <div style="font-size:11px; color:#888;"><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($d['created_at'])) ?></div>
                            </div>
                            <span style="background:<?= $badge_bg ?>; color:<?= $badge_color ?>; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">
                                <?= $d['status'] ?>
                            </span>
                        </div>
                        
                        <?php if($d['status'] === 'pending'): ?>
                            <button onclick="deleteDoc(<?= $d['id'] ?>)" class="btn btn-secondary-custom" style="padding:4px 10px; font-size:11px; color:#dc2626; border-color:#fee2e2; margin-top:5px;"><i class="fas fa-trash"></i> Delete</button>
                        <?php endif; ?>

                        <?php if(!empty($d['admin_notes'])): ?>
                            <div style="margin-top:12px; padding:10px; background:#f9fafb; border-radius:6px; border-left:3px solid <?= $badge_color ?>; font-size:13px;">
                                <strong style="display:block; margin-bottom:4px; font-size:11px; color:#666; text-transform:uppercase;">Admin Notes:</strong>
                                <?= htmlspecialchars($d['admin_notes']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center; padding:40px 20px; color:#888;">
                    <i class="fas fa-folder-open" style="font-size:40px; margin-bottom:15px; color:#ddd;"></i>
                    <p>No documents uploaded yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function deleteDoc(id) {
    if(confirm('Are you sure you want to delete this pending document?')) {
        let fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        
        fetch('document_action.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if(data.success) location.reload();
            else alert(data.message || 'Error deleting document');
        })
        .catch(err => alert('Network error'));
    }
}
</script>

<?php include('dashboard_footer.php'); ?>
