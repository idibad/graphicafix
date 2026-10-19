<?php
// admin/client_approval.php
include 'dashboard_header.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$user_id = intval($_SESSION['user_id']);
$task_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$task_id) {
    echo "<script>window.location='client_projects.php';</script>"; exit;
}

$is_admin = ($role === 'super_admin' || $role === 'superadmin' || $role === 'admin' || $role === 'pm');

// Fetch task details
$stmt = $conn->prepare("
    SELECT t.*, c.client_name, c.company AS client_company, p.project_name
    FROM tasks t
    LEFT JOIN clients c ON t.client_id = c.id
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE t.id = ?
");
$stmt->bind_param("i", $task_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();

if (!$task) {
    echo "<div class='container py-5'><div class='card border-0 shadow-sm text-center p-5'><h2 class='text-danger'>Task Not Found</h2><p class='text-muted'>This task does not exist.</p><a href='javascript:history.back()' class='btn btn-secondary btn-sm align-self-center mt-3'>Go Back</a></div></div>";
    include('dashboard_footer.php'); exit;
}

// Client ownership validation
$is_client = ($role === 'client' || $role === 'client_sub');
if ($is_client) {
    $client_id_user = intval($data['client_id'] ?? 0);
    if ($client_id_user !== intval($task['client_id'])) {
        echo "<div class='container py-5'><div class='card border-0 shadow-sm text-center p-5'><h2 class='text-danger'>Access Denied</h2><p class='text-muted'>You do not have permission to view this task.</p><a href='javascript:history.back()' class='btn btn-secondary btn-sm align-self-center mt-3'>Go Back</a></div></div>";
        include('dashboard_footer.php'); exit;
    }
}

// Fetch Client Deliverables (marked is_client_deliverable = 1) for the current revision
$rev = intval($task['current_revision']);
$files_res = $conn->query("
    SELECT * FROM task_files 
    WHERE task_id = $task_id 
      AND revision = $rev 
      AND is_client_deliverable = 1
    ORDER BY uploaded_at DESC
");
$deliverables = [];
while ($f = $files_res->fetch_assoc()) {
    $deliverables[] = $f;
}

// Fetch previous client reviews for comments history
$reviews_res = $conn->query("
    SELECT tr.*, u.name as reviewer_name 
    FROM task_reviews tr 
    LEFT JOIN users u ON tr.reviewer_id = u.user_id 
    WHERE tr.task_id = $task_id AND tr.review_type = 'Client'
    ORDER BY tr.created_at DESC
");
$client_reviews = [];
while ($r = $reviews_res->fetch_assoc()) {
    $client_reviews[] = $r;
}
?>

<div class="container-fluid py-4" style="background-color: var(--gray-50); min-height: 100vh;">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <span class="text-muted text-uppercase font-weight-bold" style="font-size: 11px; letter-spacing: 0.5px;">CLIENT REVIEW PORTAL</span>
            <h1 class="h3 font-weight-bold mb-1" style="color: var(--primary); font-weight: 800;"><?= htmlspecialchars($task['title']) ?></h1>
            <p class="text-muted small mb-0">Project: <strong><?= htmlspecialchars($task['project_name'] ?: 'General') ?></strong> | Current Revision: <strong><?= $rev ?></strong></p>
        </div>
        <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary" style="border-radius: var(--radius-sm);">← Back</a>
    </div>

    <!-- Client Status Banner -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: var(--radius); background-color: #f0f9ff; border: 1px solid #bae6fd !important;">
        <div class="card-body p-3">
            <div class="small text-info text-uppercase font-weight-bold" style="font-size: 10px; letter-spacing: 0.5px;">Current Approval Status</div>
            <div class="h5 font-weight-bold mt-1 mb-0" style="color: #0c4a6e; font-weight: 700;">
                <?php if ($task['stage'] === 'Client Approval'): ?>
                    ⏳ Waiting for your approval decision or feedback
                <?php else: ?>
                    ✓ Workflow Stage: <strong><?= htmlspecialchars($task['stage']) ?></strong> (<?= htmlspecialchars($task['client_approval_status']) ?>)
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column: Previews and Specifications -->
        <div class="col-12 col-lg-8 d-flex flex-column gap-4">
            <!-- Deliverables Files Previews -->
            <div class="card border-0 shadow-sm" style="border-radius: var(--radius);">
                <div class="card-body p-4">
                    <h3 class="h6 text-muted font-weight-bold text-uppercase mb-4" style="font-size: 11px; letter-spacing: 0.5px;">Deliverable Assets</h3>
                    
                    <?php if (empty($deliverables)): ?>
                        <div class="text-center text-muted py-4 italic" style="font-size:13.5px; font-style:italic;">No client deliverables are currently uploaded for Revision <?= $rev ?>.</div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($deliverables as $file): 
                                $ext = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION));
                                $is_image = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                            ?>
                                <div class="card border mb-1" style="border-radius: var(--radius-sm); overflow:hidden; background-color: var(--gray-50);">
                                    <?php if ($is_image): ?>
                                        <div class="text-center p-3 border-bottom" style="background:#eaede0;">
                                            <img src="../<?= htmlspecialchars($file['file_path']) ?>" alt="<?= htmlspecialchars($file['file_name']) ?>" class="shadow-sm rounded" style="max-width:100%; max-height:360px;">
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center bg-white" style="font-size:13px;">
                                        <div>
                                            <span class="font-weight-bold text-dark"><?= htmlspecialchars($file['file_name']) ?></span>
                                            <div class="text-muted small" style="font-size:11px;">Uploaded: <?= date('M d, Y', strtotime($file['uploaded_at'])) ?></div>
                                        </div>
                                        <a href="../<?= htmlspecialchars($file['file_path']) ?>" download class="btn btn-xs btn-outline-secondary" style="border-radius: var(--radius-sm); font-size:11.5px;"><i class="fas fa-download"></i> Download</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Deliverables Specs -->
            <div class="card border-0 shadow-sm" style="border-radius: var(--radius);">
                <div class="card-body p-4">
                    <h3 class="h6 text-muted font-weight-bold text-uppercase mb-4" style="font-size: 11px; letter-spacing: 0.5px;">Task Specifications</h3>
                    <div class="d-flex flex-column gap-3" style="font-size: 13.5px;">
                        <div>
                            <strong class="small text-muted text-uppercase d-block mb-1" style="font-size: 10px;">Requirements Brief</strong>
                            <div class="p-3 rounded border text-dark" style="background-color: var(--gray-50); line-height: 1.6; white-space: pre-line;"><?= htmlspecialchars($task['brief_deliverables'] ?: 'N/A') ?></div>
                        </div>
                        <div>
                            <strong class="small text-muted text-uppercase d-block mb-1" style="font-size: 10px;">Copy Caption Text</strong>
                            <div class="p-3 rounded border text-dark" style="background-color: var(--gray-50); line-height: 1.6; white-space: pre-line;"><?= htmlspecialchars($task['brief_copy'] ?: 'N/A') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Decision Portal & Comments History -->
        <div class="col-12 col-lg-4 d-flex flex-column gap-4">
            <!-- Approval Decision Card -->
            <?php if ($task['stage'] === 'Client Approval' && ($role === 'client' || $role === 'client_sub' || $is_admin)): ?>
                <div class="card border-0 shadow-sm" style="border-radius: var(--radius); border: 2px solid #bae6fd !important;">
                    <div class="card-body p-4">
                        <h3 class="h6 text-muted font-weight-bold text-uppercase mb-2" style="font-size: 11px; letter-spacing: 0.5px;">Approval Decision</h3>
                        <p class="text-muted small mb-3" style="line-height:1.5;">Please review the assets. You can approve this revision to move it to publication/delivery, or request changes with adjustments.</p>
                        
                        <form id="frmClientReview" onsubmit="submitClientReview(event)">
                            <div class="mb-3">
                                <label class="form-label text-muted small font-weight-bold text-uppercase mb-1" style="font-size: 9px;">Feedback Comments / Requests</label>
                                <textarea id="client-review-comment" rows="4" class="form-control" placeholder="Describe adjustments if requesting changes..."></textarea>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" onclick="setDecision('Approve')" class="btn btn-sm btn-success flex-grow-1"><i class="fas fa-check-circle"></i> Approve</button>
                                <button type="submit" onclick="setDecision('Request Changes')" class="btn btn-sm btn-outline-danger flex-grow-1"><i class="fas fa-undo"></i> Request Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Feedback History logs -->
            <div class="card border-0 shadow-sm" style="border-radius: var(--radius);">
                <div class="card-body p-4">
                    <h3 class="h6 text-muted font-weight-bold text-uppercase mb-3" style="font-size: 11px; letter-spacing: 0.5px;">Your Comments History</h3>
                    <div class="d-flex flex-column gap-2">
                        <?php if (empty($client_reviews)): ?>
                            <div class="text-center text-muted py-3 italic" style="font-size:13px; font-style:italic;">No comments logged yet.</div>
                        <?php else: ?>
                            <?php foreach ($client_reviews as $cr): 
                                $is_app = ($cr['decision'] === 'Approve');
                                $col = $is_app ? 'text-success':'text-danger';
                                $bg = $is_app ? 'var(--success-light)':'var(--danger-light)';
                            ?>
                                <div class="pb-3 border-bottom mb-2" style="font-size:13px; border-color: var(--gray-100) !important;">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="font-weight-bold text-dark">Revision <?= $cr['revision'] ?></span>
                                        <span class="badge" style="background-color: <?= $bg ?>; color: <?= $is_app ? 'var(--success)':'var(--danger)' ?>; font-size: 9px; font-weight:700; text-transform:uppercase;"><?= htmlspecialchars($cr['decision']) ?></span>
                                    </div>
                                    <div class="text-muted" style="line-height:1.5; font-style:italic;">"<?= htmlspecialchars($cr['comment'] ?: 'Approved.') ?>"</div>
                                    <div class="text-muted text-end mt-1" style="font-size: 10px;"><?= date('M d, Y h:i A', strtotime($cr['created_at'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let clientDecision = "";

function setDecision(decision) {
    clientDecision = decision;
}

function submitClientReview(event) {
    event.preventDefault();
    const comment = document.getElementById('client-review-comment').value;
    
    if (clientDecision === 'Request Changes' && comment.trim() === '') {
        alert('Please provide feedback comments describing the adjustments you require.');
        return;
    }
    
    if (!confirm('Are you sure you want to submit this review?')) return;
    
    const formData = new FormData();
    formData.append('action', 'submit_review');
    formData.append('task_id', <?= $task_id ?>);
    formData.append('review_type', 'Client');
    formData.append('decision', clientDecision);
    formData.append('comment', comment);
    
    fetch('task_action.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Review submitted successfully.');
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>

<?php include 'dashboard_footer.php'; ?>
