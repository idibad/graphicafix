<?php
include 'dashboard_header.php';

$user_id = intval($_SESSION['user_id'] ?? 0);

// Ensure vimeo_api_token column exists in users table
$cols = array_column($conn->query("SHOW COLUMNS FROM users")->fetch_all(MYSQLI_ASSOC), 'Field');
if (!in_array('vimeo_api_token', $cols)) {
    @$conn->query("ALTER TABLE users ADD COLUMN vimeo_api_token VARCHAR(255) DEFAULT NULL");
}

// Save Vimeo Token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vimeo_token'])) {
    $token = trim($_POST['vimeo_token']);
    $stmt = $conn->prepare("UPDATE users SET vimeo_api_token = ? WHERE user_id = ?");
    $stmt->bind_param("si", $token, $user_id);
    $stmt->execute();
    header("Location: teacher_lectures.php?token_saved=1"); exit;
}

// Get current user Vimeo Token
$u_res = $conn->query("SELECT vimeo_api_token FROM users WHERE user_id = $user_id");
$user_vimeo_token = $u_res ? trim($u_res->fetch_assoc()['vimeo_api_token'] ?? '') : '';

// Handle Lecture Upload & 2-Way Vimeo API Push
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_lecture'])) {
    $cid = intval($_POST['course_id']);
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $upload_mode = $_POST['upload_mode']; // 'vimeo_link', 'vimeo_push', 'local_mp4'
    
    $vimeo_id = trim($_POST['vimeo_id'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    $file_path = '';
    $video_type = 'mp4';

    if ($upload_mode === 'vimeo_link') {
        $video_type = 'vimeo';
        // Extract ID if full link pasted
        if (preg_match('/(?:vimeo\.com\/|video\/)(\d+)/i', $vimeo_id . $video_url, $m)) {
            $vimeo_id = $m[1];
        }
    } elseif ($upload_mode === 'vimeo_push' && !empty($_FILES['video_file']['tmp_name'])) {
        $tmp_file = $_FILES['video_file']['tmp_name'];
        $file_size = filesize($tmp_file);
        
        if (empty($user_vimeo_token)) {
            $error = "Vimeo API Token is not configured. Please save your token above.";
        } else {
            // Step 1: Create upload ticket on Vimeo
            $ch = curl_init('https://api.vimeo.com/me/videos');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: bearer " . $user_vimeo_token,
                "Content-Type: application/json",
                "Accept: application/vnd.vimeo.*+json;version=3.4"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                "upload" => ["approach" => "post", "size" => $file_size],
                "name" => $title,
                "description" => $desc
            ]));
            $res_json = curl_exec($ch);
            curl_close($ch);
            
            $ticket = json_decode($res_json, true);
            
            if (!empty($ticket['upload']['upload_link'])) {
                // Step 2: Push video binary to Vimeo upload_link
                $ch2 = curl_init($ticket['upload']['upload_link']);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_POST, true);
                curl_setopt($ch2, CURLOPT_POSTFIELDS, file_get_contents($tmp_file));
                curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/offset+octet-stream"
                ]);
                curl_exec($ch2);
                curl_close($ch2);

                if (!empty($ticket['uri']) && preg_match('/\/videos\/(\d+)/', $ticket['uri'], $vm)) {
                    $vimeo_id = $vm[1];
                    $video_type = 'vimeo';
                    $success_msg = "Successfully uploaded and pushed directly to Vimeo account!";
                }
            } else {
                $error = "Vimeo API Error: " . ($ticket['error'] ?? 'Could not create upload ticket.');
            }
        }
    } elseif ($upload_mode === 'local_mp4' && !empty($_FILES['video_file']['name'])) {
        $dir = '../admin/uploads/lectures/' . date('Y_m');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fn = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '', $_FILES['video_file']['name']);
        if (move_uploaded_file($_FILES['video_file']['tmp_name'], $dir . '/' . $fn)) {
            $file_path = 'uploads/lectures/' . date('Y_m') . '/' . $fn;
            $video_type = 'mp4';
        } else {
            $error = "Failed to move uploaded MP4 file.";
        }
    } elseif ($upload_mode === 'external_free') {
        $video_type = 'external';
        if (preg_match('/drive\.google\.com\/file\/d\/([^\/]+)/i', $video_url, $dm)) {
            $video_url = "https://drive.google.com/file/d/" . $dm[1] . "/preview";
        }
    }

    if (!isset($error)) {
        $stmt = $conn->prepare("INSERT INTO recorded_lectures (course_id, teacher_id, title, description, file_path, video_type, vimeo_id, video_url, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("iissssss", $cid, $user_id, $title, $desc, $file_path, $video_type, $vimeo_id, $video_url);
        $stmt->execute();
        header("Location: teacher_lectures.php?uploaded=1"); exit;
    }
}

// Handle Delete Lecture
if (isset($_GET['delete_id'])) {
    $del = intval($_GET['delete_id']);
    $conn->query("DELETE FROM recorded_lectures WHERE id = $del AND teacher_id = $user_id");
    header("Location: teacher_lectures.php?deleted=1"); exit;
}

// Fetch instructor courses for dropdown
$my_courses_res = $conn->query("SELECT id, title FROM courses WHERE instructor_id = $user_id OR instructor_id IS NULL ORDER BY title ASC");

// Fetch lectures
$filter_cid = intval($_GET['filter_course'] ?? 0);
$where_c = $filter_cid > 0 ? "AND l.course_id = $filter_cid" : "";

$lectures = $conn->query("
    SELECT l.*, c.title as course_title 
    FROM recorded_lectures l 
    JOIN courses c ON l.course_id = c.id 
    WHERE l.teacher_id = $user_id $where_c 
    ORDER BY l.created_at DESC
");
?>

<div class="height-100">
    <div class="main-header mb-4">
        <div>
            <h3 style="font-weight:800; color:#024442; margin:0;"><i class="fas fa-video"></i> Video Lectures & Vimeo 2-Way Engine</h3>
            <p style="color:#64748b; font-size:14px; margin:4px 0 0;">Upload videos directly to your Vimeo account or web server, and manage classroom playback.</p>
        </div>
        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="fas fa-cloud-upload-alt"></i> Upload New Lecture</button>
    </div>

    <?php if(isset($_GET['uploaded'])): ?>
        <div class="alert alert-success fw-bold"><i class="fas fa-check-circle"></i> Lecture uploaded successfully! Pending admin verification.</div>
    <?php endif; ?>
    <?php if(isset($_GET['deleted'])): ?>
        <div class="alert alert-warning fw-bold"><i class="fas fa-trash"></i> Lecture removed.</div>
    <?php endif; ?>
    <?php if(isset($_GET['token_saved'])): ?>
        <div class="alert alert-success fw-bold"><i class="fas fa-key"></i> Vimeo Access Token saved! You can now use Direct 2-Way Vimeo Push.</div>
    <?php endif; ?>
    <?php if(isset($error)): ?>
        <div class="alert alert-danger fw-bold"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <!-- Vimeo 2-Way Connection Config Card -->
        <div class="col-lg-12">
            <div class="d-card" style="background:#0f172a; color:white; border:1px solid rgba(184,243,90,0.3);">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <span class="badge" style="background:#024442; color:#b8f35a; font-weight:800; letter-spacing:1px; margin-bottom:8px;"><i class="fab fa-vimeo-v"></i> 2-WAY VIMEO CLOUD API CONNECTION</span>
                        <h4 style="font-weight:800; color:white; margin:0 0 6px;">Automated Instructor Video Uploads</h4>
                        <p style="color:#94a3b8; font-size:13.5px; margin:0;">
                            Connect your Vimeo Access Token once. When you select a video file during lecture upload, Graphicafix will automatically push it to Vimeo servers via API and register the cloud embed ID without using local web server disk storage!
                        </p>
                    </div>
                    <div class="col-md-5 mt-3 mt-md-0">
                        <form method="POST" style="display:flex; gap:8px;">
                            <input type="hidden" name="save_vimeo_token" value="1">
                            <input type="password" name="vimeo_token" class="form-control" style="background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.2); color:white; font-size:13px;" placeholder="Paste Vimeo Personal Access Token..." value="<?= htmlspecialchars($user_vimeo_token) ?>">
                            <button type="submit" class="btn btn-primary-custom" style="white-space:nowrap; padding:10px 20px;"><i class="fas fa-save"></i> Connect</button>
                        </form>
                        <div style="font-size:11px; color:#64748b; margin-top:6px;">Status: <?= !empty($user_vimeo_token) ? '<strong style="color:#b8f35a;">✓ Connected to Vimeo Account</strong>' : '<strong class="text-warning">Disconnected (Links & Server MP4 only)</strong>' ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lectures List Table -->
    <div class="d-card">
        <div class="main-header mb-3">
            <h5 style="font-weight:800; color:#0f172a; margin:0;">My Uploaded Lectures</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr style="font-size:13px;">
                        <th>Lecture Title</th>
                        <th>Course</th>
                        <th>Hosting / Source</th>
                        <th>Vimeo ID / File</th>
                        <th>Admin Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($lectures && $lectures->num_rows > 0): while($l = $lectures->fetch_assoc()): 
                        $vtype = strtolower(trim($l['video_type'] ?? 'mp4'));
                    ?>
                        <tr style="font-size:13.5px;">
                            <td style="font-weight:800; color:#0f172a;">
                                <i class="fas <?= $vtype==='vimeo' ? 'fa-video text-info' : 'fa-film text-secondary' ?> me-2"></i>
                                <?= htmlspecialchars($l['title']) ?>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($l['course_title']) ?></span></td>
                            <td>
                                <?php if($vtype==='vimeo'): ?>
                                    <span class="badge" style="background:#1ab7ea; color:white;"><i class="fab fa-vimeo-v"></i> VIMEO CLOUD</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><i class="fas fa-server"></i> LOCAL SERVER</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-family:monospace; font-size:12.5px; color:#475569;">
                                <?= htmlspecialchars(!empty($l['vimeo_id']) ? $l['vimeo_id'] : ($l['file_path'] ?: 'N/A')) ?>
                            </td>
                            <td>
                                <?php if($l['status']==='approved'): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Approved</span>
                                <?php elseif($l['status']==='rejected'): ?>
                                    <span class="badge bg-danger"><i class="fas fa-times"></i> Rejected</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> In Review</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#64748b; font-size:12.5px;"><?= date('M d, Y', strtotime($l['created_at'])) ?></td>
                            <td>
                                <a href="course_player.php?course_id=<?= $l['course_id'] ?>&lecture_id=<?= $l['id'] ?>" class="btn btn-sm btn-light border" title="Preview Playback"><i class="fas fa-play text-success"></i></a>
                                <a href="teacher_lectures.php?delete_id=<?= $l['id'] ?>" class="btn btn-sm btn-light border" onclick="return confirm('Are you sure you want to delete this lecture?')" title="Delete"><i class="fas fa-trash text-danger"></i></a>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No video lectures uploaded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius:24px; border:none; overflow:hidden;">
            <div class="modal-header" style="background:#024442; color:white; padding:24px;">
                <h5 class="modal-title fw-bold"><i class="fas fa-cloud-upload-alt text-warning"></i> Upload & Register Video Lecture</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <input type="hidden" name="upload_lecture" value="1">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="fw-bold fs-6 mb-2">Target Course</label>
                            <select name="course_id" class="form-control" required>
                                <?php if($my_courses_res && $my_courses_res->num_rows > 0): while($cr = $my_courses_res->fetch_assoc()): ?>
                                    <option value="<?= $cr['id'] ?>" <?= $filter_cid == $cr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cr['title']) ?></option>
                                <?php endwhile; else: ?>
                                    <option value="">No courses assigned</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold fs-6 mb-2">Upload Strategy / Connection Mode</label>
                            <select name="upload_mode" id="uploadModeSelect" class="form-control" onchange="toggleUploadUI()">
                                <option value="vimeo_link">⚡ Option 1: Instant Vimeo Link / ID Paste</option>
                                <option value="vimeo_push" <?= !empty($user_vimeo_token) ? 'selected' : '' ?>>🚀 Option 2: Push MP4 Directly to Vimeo Cloud (2-Way API)</option>
                                <option value="local_mp4">📁 Option 3: Local Web Server MP4 Storage</option>
                                <option value="external_free">🌐 Option 4: Free Cloud Video Hosting (Google Drive / BunnyCDN / Direct Embed)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="fw-bold fs-6 mb-2">Lecture Title</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. Module 1: Introduction to Advanced Design Systems">
                    </div>

                    <!-- Mode UI 1: Link -->
                    <div id="ui_vimeo_link" class="mode-ui p-3 bg-light rounded-3 mb-3 border">
                        <label class="fw-bold fs-6 mb-2 text-dark"><i class="fab fa-vimeo-v text-info"></i> Vimeo Video URL or Video ID</label>
                        <input type="text" name="vimeo_id" class="form-control" placeholder="https://vimeo.com/123456789 or 123456789">
                        <small class="text-muted mt-1 d-block">Video will be instantly embedded with high-performance CDN streaming.</small>
                    </div>

                    <!-- Mode UI 2: API Push -->
                    <div id="ui_vimeo_push" class="mode-ui p-3 rounded-3 mb-3 border" style="background:#f0fdf4; border-color:#bbf7d0 !important; display:none;">
                        <label class="fw-bold fs-6 mb-2 text-success"><i class="fas fa-cloud-upload-alt"></i> Select MP4 Video File to Push to Vimeo Cloud</label>
                        <input type="file" name="video_file" id="filePush" class="form-control" accept="video/mp4,video/*">
                        <small class="text-success mt-1 d-block"><i class="fas fa-shield-alt"></i> Uses your saved Vimeo API Access Token. Zero disk space consumed on this web server!</small>
                    </div>

                    <!-- Mode UI 3: Local MP4 -->
                    <div id="ui_local_mp4" class="mode-ui p-3 bg-light rounded-3 mb-3 border" style="display:none;">
                        <label class="fw-bold fs-6 mb-2 text-dark"><i class="fas fa-server"></i> Upload MP4 to Local Web Server</label>
                        <input type="file" name="video_file" id="fileLocal" class="form-control" accept="video/mp4">
                        <small class="text-muted mt-1 d-block">File will be stored in `/admin/uploads/lectures/`.</small>
                    </div>

                    <!-- Mode UI 4: External Free Cloud -->
                    <div id="ui_external_free" class="mode-ui p-4 rounded-3 mb-3 border" style="background:#f8fafc; border-color:#cbd5e1 !important; display:none;">
                        <label class="fw-bold fs-6 mb-2" style="color:#024442;"><i class="fas fa-globe text-success me-2"></i> Free Cloud Video Link / CDN MP4 / Embed URL</label>
                        <input type="text" name="video_url" class="form-control mb-2" style="padding:12px; font-size:14px;" placeholder="e.g. https://drive.google.com/file/d/.../view or direct CDN MP4 link">
                        <div style="font-size:12px; color:#475569; line-height:1.5; background:#fff; padding:12px; border-radius:8px; border:1px solid #e2e8f0; margin-top:8px;">
                            <strong style="color:#024442;"><i class="fas fa-info-circle text-info me-1"></i> Free Video Hosting & Customization Guide:</strong><br>
                            • <strong>Google Drive (15GB Free):</strong> Upload video to Drive, set sharing to "Anyone with link", and paste here. Graphicafix auto-converts it to a high-speed player.<br>
                            • <strong>BunnyCDN / Cloudflare / Loom / Wistia:</strong> Paste your CDN link or iframe embed URL for zero ads, custom branding, and global CDN caching!
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="fw-bold fs-6 mb-2">Lecture Summary / Study Notes</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Add study notes, bullet points, or chapter breakdowns..."></textarea>
                    </div>
                </div>
                <div class="modal-footer p-4 pt-0 border-0">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom" style="padding:12px 28px;">Submit Lecture &rarr;</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleUploadUI() {
    document.querySelectorAll('.mode-ui').forEach(el => el.style.display = 'none');
    const m = document.getElementById('uploadModeSelect').value;
    document.getElementById('ui_' + m).style.display = 'block';
}
window.addEventListener('DOMContentLoaded', toggleUploadUI);
</script>

<?php include('dashboard_footer.php'); ?>
