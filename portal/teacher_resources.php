<?php
include('dashboard_header.php');

$user_id = intval($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_resource'])) {
    $cid = intval($_POST['course_id']);
    $title = trim($_POST['title']);
    $fpath = trim($_POST['file_url'] ?? '');
    
    if (!empty($_FILES['res_file']['name'])) {
        $dir = '../admin/uploads/resources/' . date('Y_m');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fn = time() . '_' . basename($_FILES['res_file']['name']);
        if (move_uploaded_file($_FILES['res_file']['tmp_name'], $dir . '/' . $fn)) {
            $fpath = 'uploads/resources/' . date('Y_m') . '/' . $fn;
        }
    }
    
    if ($fpath) {
        $stmt = $conn->prepare("INSERT INTO course_resources (course_id, teacher_id, title, file_path, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiss", $cid, $user_id, $title, $fpath);
        $stmt->execute();
        header("Location: teacher_resources.php?added=1"); exit;
    } else {
        $error = "Please provide a file URL or upload a file.";
    }
}

if (isset($_GET['del'])) {
    $del = intval($_GET['del']);
    $conn->query("DELETE FROM course_resources WHERE id = $del AND teacher_id = $user_id");
    header("Location: teacher_resources.php?deleted=1"); exit;
}

$my_courses_res = $conn->query("SELECT id, title FROM courses WHERE instructor_id = $user_id OR instructor_id IS NULL ORDER BY title ASC");

$resources = $conn->query("
    SELECT r.*, c.title as course_title 
    FROM course_resources r 
    JOIN courses c ON r.course_id = c.id 
    WHERE r.teacher_id = $user_id OR c.instructor_id = $user_id 
    ORDER BY r.id DESC
");
?>

<div class="height-100">
    <div class="main-header mb-4">
        <div>
            <h3 style="font-weight:800; color:#024442; margin:0;"><i class="fas fa-file-archive"></i> Course Assets & Resources</h3>
            <p style="color:#64748b; font-size:14px; margin:4px 0 0;">Upload starter kits, documentation PDFs, and source files.</p>
        </div>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#resModal"><i class="fas fa-plus"></i> Add Resource</button>
    </div>

    <?php if(isset($_GET['added'])): ?><div class="alert alert-success fw-bold">Resource attached to course!</div><?php endif; ?>
    <?php if(isset($_GET['deleted'])): ?><div class="alert alert-warning fw-bold">Resource removed.</div><?php endif; ?>

    <div class="d-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr style="font-size:13px;">
                        <th>Asset Title</th>
                        <th>Course</th>
                        <th>File / Link</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($resources && $resources->num_rows > 0): while($r = $resources->fetch_assoc()): ?>
                        <tr style="font-size:13.5px;">
                            <td><strong style="color:#0f172a;"><i class="fas fa-file-download text-primary me-2"></i> <?= htmlspecialchars($r['title']) ?></strong></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['course_title']) ?></span></td>
                            <td><a href="<?= htmlspecialchars(preg_match('/^http/i',$r['file_path'])?$r['file_path']:'../admin/'.$r['file_path']) ?>" target="_blank" class="text-primary text-decoration-none"><i class="fas fa-external-link-alt"></i> Open File</a></td>
                            <td style="color:#64748b; font-size:12.5px;"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                            <td>
                                <a href="teacher_resources.php?del=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this resource?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No course assets uploaded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="resModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:20px;">
            <div class="modal-header bg-dark text-white p-4">
                <h5 class="modal-title fw-bold">Attach Resource</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <input type="hidden" name="add_resource" value="1">
                    <div class="form-group mb-3">
                        <label class="fw-bold fs-6 mb-2">Select Course</label>
                        <select name="course_id" class="form-control" required>
                            <?php if($my_courses_res && $my_courses_res->num_rows > 0): while($cr = $my_courses_res->fetch_assoc()): ?>
                                <option value="<?= $cr['id'] ?>"><?= htmlspecialchars($cr['title']) ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="fw-bold fs-6 mb-2">Asset Name</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. Figma UI Kit Starter File">
                    </div>
                    <div class="form-group mb-3">
                        <label class="fw-bold fs-6 mb-2">External Link URL (Optional)</label>
                        <input type="text" name="file_url" class="form-control" placeholder="https://drive.google.com/...">
                    </div>
                    <div class="form-group">
                        <label class="fw-bold fs-6 mb-2">Or Upload Local File</label>
                        <input type="file" name="res_file" class="form-control">
                    </div>
                </div>
                <div class="modal-footer p-4 pt-0 border-0">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom">Save Resource &rarr;</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('dashboard_footer.php'); ?>
