<?php
include('dashboard_header.php');

$user_id = intval($_SESSION['user_id'] ?? 0);

// Handle Document Upload (Preserving admin approval workflow)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_doc'])) {
    $title = trim($_POST['title']);
    $type  = trim($_POST['doc_type']); // ID Proof, Degree, Certificate, Contract, Other
    
    if (!empty($_FILES['doc_file']['name'])) {
        // Physical save to admin directory so admin review tools find it seamlessly
        $target_dir = '../admin/uploads/teacher_docs/' . date('Y_m');
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        
        $fn = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '', $_FILES['doc_file']['name']);
        
        if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $target_dir . '/' . $fn)) {
            // Store DB path relative to admin root exactly as admin_teacher_docs expects
            $db_path = 'uploads/teacher_docs/' . date('Y_m') . '/' . $fn;
            
            $stmt = $conn->prepare("INSERT INTO teacher_documents (teacher_id, title, doc_type, file_path, status, uploaded_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
            $stmt->bind_param("isss", $user_id, $title, $type, $db_path);
            $stmt->execute();
            header("Location: teacher_documents.php?uploaded=1"); exit;
        } else {
            $error = "Failed to upload file physical binary.";
        }
    } else {
        $error = "Please select a file to upload.";
    }
}

if (isset($_GET['del'])) {
    $del = intval($_GET['del']);
    // Only allow deletion if pending or rejected
    $conn->query("DELETE FROM teacher_documents WHERE id = $del AND teacher_id = $user_id AND status != 'approved'");
    header("Location: teacher_documents.php?deleted=1"); exit;
}

$docs = $conn->query("SELECT * FROM teacher_documents WHERE teacher_id = $user_id ORDER BY uploaded_at DESC");
?>

<div class="height-100">
    <div class="main-header mb-4">
        <div>
            <h3 style="font-weight:800; color:#024442; margin:0;"><i class="fas fa-folder-open"></i> Instructor Verification Vault</h3>
            <p style="color:#64748b; font-size:14px; margin:4px 0 0;">Submit qualification records and ID verifications for administrative review.</p>
        </div>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#docModal"><i class="fas fa-upload"></i> Upload Credential</button>
    </div>

    <?php if(isset($_GET['uploaded'])): ?><div class="alert alert-success fw-bold">Document uploaded! Administrative review in progress.</div><?php endif; ?>
    <?php if(isset($_GET['deleted'])): ?><div class="alert alert-warning fw-bold">Document removed from vault.</div><?php endif; ?>
    <?php if(isset($error)): ?><div class="alert alert-danger fw-bold"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="d-card mb-4" style="background:#f8fafc; border-left:4px solid #024442; padding:18px;">
        <div style="display:flex; align-items:center; gap:12px;">
            <i class="fas fa-shield-alt fs-3 text-primary"></i>
            <div>
                <strong style="color:#0f172a; font-size:14.5px;">Verification & Compliance Notice</strong>
                <p style="color:#475569; font-size:13px; margin:0;">All uploaded documents are securely routed directly to the administrative audit queue. Approved documents cannot be modified or deleted without contacting administration.</p>
            </div>
        </div>
    </div>

    <div class="d-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr style="font-size:13px;">
                        <th>Document Title</th>
                        <th>Category</th>
                        <th>File Binary</th>
                        <th>Verification Status</th>
                        <th>Admin Comments</th>
                        <th>Uploaded Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($docs && $docs->num_rows > 0): while($d = $docs->fetch_assoc()): ?>
                        <tr style="font-size:13.5px;">
                            <td><strong style="color:#0f172a;"><i class="fas fa-file-contract text-secondary me-2"></i> <?= htmlspecialchars($d['title']) ?></strong></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($d['doc_type']) ?></span></td>
                            <td>
                                <a href="../admin/<?= htmlspecialchars($d['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt"></i> View</a>
                            </td>
                            <td>
                                <?php if($d['status'] === 'approved'): ?>
                                    <span class="badge bg-success"><i class="fas fa-check-circle"></i> Approved</span>
                                <?php elseif($d['status'] === 'rejected'): ?>
                                    <span class="badge bg-danger"><i class="fas fa-times-circle"></i> Rejected</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Pending Audit</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#dc2626; font-size:12.5px; font-weight:600;"><?= htmlspecialchars($d['admin_notes'] ?? 'None') ?></td>
                            <td style="color:#64748b; font-size:12.5px;"><?= date('M d, Y', strtotime($d['uploaded_at'])) ?></td>
                            <td>
                                <?php if($d['status'] !== 'approved'): ?>
                                    <a href="teacher_documents.php?del=<?= $d['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove document?')" title="Delete"><i class="fas fa-trash"></i></a>
                                <?php else: ?>
                                    <i class="fas fa-lock text-muted" title="Locked (Approved)"></i>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No documents uploaded to your vault yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="docModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px;">
            <div class="modal-header bg-dark text-white p-4">
                <h5 class="modal-title fw-bold"><i class="fas fa-shield-alt text-success"></i> Upload Compliance Document</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <input type="hidden" name="upload_doc" value="1">
                    <div class="form-group mb-3">
                        <label class="fw-bold fs-6 mb-2">Document Title</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. Master of Arts in Design Degree">
                    </div>
                    <div class="form-group mb-3">
                        <label class="fw-bold fs-6 mb-2">Credential Category</label>
                        <select name="doc_type" class="form-control" required>
                            <option value="Government ID Proof">Government ID Proof</option>
                            <option value="University Degree">University Degree</option>
                            <option value="Professional Certificate">Professional Certificate</option>
                            <option value="Employment Contract">Employment Contract</option>
                            <option value="Other">Other Record</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="fw-bold fs-6 mb-2">Select Document File (PDF, PNG, JPG, DOCX)</label>
                        <input type="file" name="doc_file" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer p-4 pt-0 border-0">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Transmit to Audit &rarr;</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('dashboard_footer.php'); ?>
