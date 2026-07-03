<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/graphicafix/core/config.php';require_once 'dashboard_header.php';

$id = intval($_GET['id'] ?? 0);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_name       = $_POST['project_name'];
    $client_id          = $_POST['client_id'];
    $description        = $_POST['description'];
    $public_description = $_POST['public_description'];
    $internal_notes     = $_POST['internal_notes'];
    $status             = $_POST['status'];
    $priority           = $_POST['priority'];
    $start_date         = $_POST['start_date'];
    $end_date           = $_POST['end_date'];
    $visibility         = $_POST['visibility'];
    $category           = $_POST['category'] ?? '';
    $team_members       = $_POST['team_members'] ?? [];
    $slug               = strtolower(str_replace(' ', '-', $project_name));

    $thumbDir = "assets/images/uploads/projects";

    // Upload new thumbnail if provided
    $thumbPath = $_POST['existing_thumbnail'] ?? '';
    if (!empty($_FILES['thumbnail']['name'])) {
        $thumbDir = "assets/images/uploads/projects/" . time();
        mkdir($thumbDir, 0755, true);
        $thumbPath = $thumbDir . "/thumb_" . $_FILES['thumbnail']['name'];
        move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbPath);
    }

    // Update project
    $stmt = $conn->prepare("UPDATE projects SET 
        client_id=?, project_name=?, description=?, public_description=?, 
        internal_notes=?, status=?, priority=?, start_date=?, end_date=?, 
        thumbnail=?, slug=?, visibility=?, category=?
        WHERE id=?");
    $stmt->bind_param(
        "issssssssssssi",
        $client_id, $project_name, $description, $public_description,
        $internal_notes, $status, $priority, $start_date, $end_date,
        $thumbPath, $slug, $visibility, $category, $id
    );
    $stmt->execute();

    // Update team members
    $conn->query("DELETE FROM project_team WHERE project_id = $id");
    foreach ($team_members as $uid) {
        $stmt2 = $conn->prepare("INSERT INTO project_team (project_id, user_id) VALUES (?, ?)");
        $stmt2->bind_param("ii", $id, $uid);
        $stmt2->execute();
    }

    // Upload new gallery images
    if (!empty($_FILES['gallery']['tmp_name'][0])) {
        foreach ($_FILES['gallery']['tmp_name'] as $key => $tmp) {
            if (!empty($tmp)) {
                $imgPath = $thumbDir . "/img_" . $_FILES['gallery']['name'][$key];
                move_uploaded_file($tmp, $imgPath);
                $conn->query("INSERT INTO project_gallery (project_id, image_path, sort_order) VALUES ($id, '$imgPath', $key)");
                $conn->query("INSERT INTO project_files (project_id, file_path, file_type) VALUES ($id, '$imgPath', 'image')");
            }
        }
    }

    echo "<script>alert('Project Updated Successfully!');window.location='project_details.php?id=$id';</script>";
}

// Fetch project
$stmt = $conn->prepare("SELECT p.* FROM projects p WHERE p.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    echo "<script>alert('Project not found');window.location='projects.php';</script>";
    exit;
}

// Fetch assigned team members
$stmt = $conn->prepare("SELECT user_id FROM project_team WHERE project_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$assigned_members = [];
while ($row = $result->fetch_assoc()) {
    $assigned_members[] = $row['user_id'];
}

// ── FIXED: was using `id = ?` instead of `project_id = ?` ───────────────────
$stmt = $conn->prepare("SELECT * FROM project_gallery WHERE project_id = ? ORDER BY sort_order");
$stmt->bind_param("i", $id);
$stmt->execute();
$gallery      = $stmt->get_result();
$galleryImages = [];
while ($img = $gallery->fetch_assoc()) {
    $galleryImages[] = $img;
}
?>

<style>
/* ── Gallery grid ─────────────────────────────────────────────────────────── */
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 14px;
    margin-top: 12px;
}

.gallery-grid-item {
    position: relative;
    border-radius: 14px;
    overflow: hidden;
    aspect-ratio: 1;
    background: #f1f5f9;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
    transition: transform .2s, box-shadow .2s;
}
.gallery-grid-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,.13);
}

.gallery-grid-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

/* Delete button */
.gallery-delete-btn {
    position: absolute;
    top: 7px;
    right: 7px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(239,68,68,.9);
    border: 2px solid #fff;
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transform: scale(0.8);
    transition: opacity .2s, transform .2s;
}
.gallery-grid-item:hover .gallery-delete-btn {
    opacity: 1;
    transform: scale(1);
}
.gallery-delete-btn:hover {
    background: #dc2626;
}

/* Overlay on hover */
.gallery-grid-item::after {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,.15);
    opacity: 0;
    transition: opacity .2s;
    pointer-events: none;
}
.gallery-grid-item:hover::after {
    opacity: 1;
}

/* Empty gallery state */
.gallery-empty {
    text-align: center;
    padding: 32px 20px;
    color: #aaa;
    background: #f8fafc;
    border-radius: 14px;
    border: 2px dashed #e2e8f0;
    font-size: 14px;
}
.gallery-empty-icon { font-size: 32px; margin-bottom: 8px; }

/* Fade-out animation for deleted items */
@keyframes galleryFadeOut {
    to { opacity: 0; transform: scale(0.85); }
}
.gallery-grid-item.removing {
    animation: galleryFadeOut .3s ease forwards;
}
</style>

<div class="dashboard">
    <div class="page-header">
        <h1><i class="fas fa-pencil-alt"></i>️ Edit Project</h1>
        <p>Update project information and settings</p>
    </div>

    <div class="mp-tabs-container">
        <div class="mp-tab-buttons">
            <button type="button" class="active" onclick="showTab('basic')">
                <span class="mp-tab-icon"><i class="fas fa-edit"></i></span> Basic Info
            </button>
            <button type="button" onclick="showTab('details')">
                <span class="mp-tab-icon"><i class="fas fa-file-alt"></i></span> Details
            </button>
            <button type="button" onclick="showTab('media')">
                <span class="mp-tab-icon"><i class="fas fa-image"></i>️</span> Media
            </button>
            <button type="button" onclick="showTab('team')">
                <span class="mp-tab-icon"><i class="fas fa-users"></i></span> Team
            </button>
        </div>
    </div>

    <div class="form-container">
        <form method="POST" enctype="multipart/form-data" id="projectForm">
            <input type="hidden" name="existing_thumbnail" value="<?= htmlspecialchars($project['thumbnail']) ?>">

            <!-- Basic Info Tab -->
            <div id="basic" class="tab active">
                <h3>Basic Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Project Name <span class="required">*</span></label>
                        <input type="text" name="project_name" value="<?= htmlspecialchars($project['project_name']) ?>" placeholder="Enter project name" required>
                    </div>
                    <div class="form-group">
                        <label>Client <span class="required">*</span></label>
                        <select name="client_id" required>
                            <option value="">Select a client</option>
                            <?php
                            $res = $conn->query("SELECT id, client_name FROM clients");
                            while ($row = $res->fetch_assoc()) {
                                $sel = ($row['id'] == $project['client_id']) ? 'selected' : '';
                                echo "<option value='{$row['id']}' $sel>{$row['client_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($project['start_date']) ?>">
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($project['end_date']) ?>">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Status <span class="required">*</span></label>
                        <select name="status" required>
                            <option value="Not Started" <?= $project['status'] == 'Not Started' ? 'selected' : '' ?>>Not Started</option>
                            <option value="In Progress"  <?= $project['status'] == 'In Progress'  ? 'selected' : '' ?>>In Progress</option>
                            <option value="Completed"    <?= $project['status'] == 'Completed'    ? 'selected' : '' ?>>Completed</option>
                            <option value="On Hold"      <?= $project['status'] == 'On Hold'      ? 'selected' : '' ?>>On Hold</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority <span class="required">*</span></label>
                        <select name="priority" required>
                            <option value="Low"    <?= $project['priority'] == 'Low'    ? 'selected' : '' ?>>Low</option>
                            <option value="Medium" <?= $project['priority'] == 'Medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="High"   <?= $project['priority'] == 'High'   ? 'selected' : '' ?>>High</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Visibility <span class="required">*</span></label>
                        <select name="visibility" required>
                            <option value="private" <?= $project['visibility'] == 'private' ? 'selected' : '' ?>>Private (Team Only)</option>
                            <option value="client"  <?= $project['visibility'] == 'client'  ? 'selected' : '' ?>>Client Visible</option>
                            <option value="public"  <?= $project['visibility'] == 'public'  ? 'selected' : '' ?>>Public</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Category <span class="required">*</span></label>
                        <select name="category" required>
                            <option value="">Select a category</option>
                            <option value="branding" <?= isset($project['category']) && $project['category'] == 'branding' ? 'selected' : '' ?>>Branding</option>
                            <option value="web-design" <?= isset($project['category']) && $project['category'] == 'web-design' ? 'selected' : '' ?>>Web Design</option>
                            <option value="social-media" <?= isset($project['category']) && $project['category'] == 'social-media' ? 'selected' : '' ?>>Social Media</option>
                            <option value="marketing" <?= isset($project['category']) && $project['category'] == 'marketing' ? 'selected' : '' ?>>Marketing</option>
                            <option value="packaging" <?= isset($project['category']) && $project['category'] == 'packaging' ? 'selected' : '' ?>>Packaging</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Details Tab -->
            <div id="details" class="tab">
                <h3>Project Details</h3>
                <div class="info-box">
                    <div class="info-title"><i class="fas fa-lightbulb"></i> Description Guidelines</div>
                    <div class="info-text">Team description is for internal use. Public description will be visible to clients and stakeholders.</div>
                </div>
                <div class="form-group">
                    <label>Team Description</label>
                    <textarea name="description" placeholder="Internal project description for team members..."><?= htmlspecialchars($project['description']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Internal Notes</label>
                    <textarea name="internal_notes" placeholder="Private notes, instructions, or important information..."><?= htmlspecialchars($project['internal_notes']) ?></textarea>
                </div>
                <div class="form-group mb-4">
                    <label class="form-label fw-bold text-secondary" style="letter-spacing:1px;font-size:.85rem;text-transform:uppercase;">Public Description</label>
                    <div id="public-editor-wrapper" style="background:#fff;border-radius:8px;">
                        <div id="public-editor" style="height:350px;font-family:'Outfit',sans-serif;font-size:1.1rem;">
                            <?= isset($project['public_description']) ? $project['public_description'] : '' ?>
                        </div>
                    </div>
                    <input type="hidden" name="public_description" id="public_description_input">
                </div>
            </div>

            <!-- Media Tab -->
            <div id="media" class="tab">
                <h3>Project Media</h3>

                <!-- Thumbnail -->
                <div class="form-group">
                    <label>Project Thumbnail</label>
                    <?php if (!empty($project['thumbnail'])): ?>
                    <div class="current-thumbnail" style="margin-bottom:12px;">
                        <div class="current-thumbnail-label" style="font-size:12px;color:#888;margin-bottom:6px;">Current Thumbnail:</div>
                        <img src="<?= htmlspecialchars($project['thumbnail']) ?>" alt="Current thumbnail"
                             style="width:120px;height:80px;object-fit:cover;border-radius:10px;border:2px solid #e2e8f0;">
                    </div>
                    <?php endif; ?>
                    <div class="upload-area" onclick="document.getElementById('thumbnail').click()">
                        <div class="upload-icon"><i class="fas fa-image"></i>️</div>
                        <div class="upload-text">
                            <strong>Click to upload new thumbnail</strong> or drag and drop<br>
                            <small>PNG, JPG, GIF up to 10MB</small>
                        </div>
                        <input type="file" name="thumbnail" id="thumbnail" accept="image/*">
                    </div>
                </div>

                <!-- Existing Gallery -->
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;">
                        Gallery Images
                        <span style="background:#e2e8f0;color:#64748b;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;">
                            <?= count($galleryImages) ?> image<?= count($galleryImages) !== 1 ? 's' : '' ?>
                        </span>
                    </label>

                    <?php if (empty($galleryImages)): ?>
                    <div class="gallery-empty">
                        <div class="gallery-empty-icon"><i class="fas fa-image"></i>️</div>
                        No gallery images yet. Upload some below.
                    </div>
                    <?php else: ?>
                    <div class="gallery-grid" id="galleryGrid">
                        <?php foreach ($galleryImages as $img): ?>
                        <div class="gallery-grid-item" id="gallery-item-<?= $img['id'] ?>">
                            <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="Gallery image" loading="lazy">
                            <button type="button" class="gallery-delete-btn"
                                onclick="removeGalleryImage(<?= $img['id'] ?>)"
                                title="Delete image">×</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Upload new -->
                <div class="form-group">
                    <label>Add New Gallery Images</label>
                    <div class="upload-area" onclick="document.getElementById('gallery').click()">
                        <div class="upload-icon"><i class="fas fa-camera"></i></div>
                        <div class="upload-text">
                            <strong>Click to upload additional images</strong><br>
                            <small>Select multiple files to add to gallery</small>
                        </div>
                        <input type="file" name="gallery[]" id="gallery" multiple accept="image/*">
                    </div>
                </div>
            </div>

            <!-- Team Tab -->
            <div id="team" class="tab">
                <h3>Assign Team Members</h3>
                <div class="info-box">
                    <div class="info-title"><i class="fas fa-users"></i> Team Assignment</div>
                    <div class="info-text">Select team members who will be working on this project.</div>
                </div>
                <div class="team-grid">
                    <?php
                    $users = $conn->query("SELECT user_id, name FROM users");
                    while ($u = $users->fetch_assoc()) {
                        $initial  = strtoupper(substr($u['name'], 0, 1));
                        $checked  = in_array($u['user_id'], $assigned_members) ? 'checked' : '';
                        $selected = in_array($u['user_id'], $assigned_members) ? 'selected' : '';
                        echo "
                        <div class='team-member $selected'>
                            <input type='checkbox' name='team_members[]' value='{$u['user_id']}' id='user_{$u['user_id']}' $checked onchange='toggleTeamMember(this)'>
                            <div class='team-avatar'>{$initial}</div>
                            <label for='user_{$u['user_id']}'>{$u['name']}</label>
                        </div>";
                    }
                    ?>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <div style="display:flex;gap:12px;">
                    <button type="button" class="btn btn-secondary" id="prevBtn" onclick="previousTab()" style="display:none;">
                        <span>←</span> Previous
                    </button>
                </div>
                <div style="display:flex;gap:12px;">
                    <button type="button" class="btn btn-secondary" onclick="window.location='project_details.php?id=<?= $id ?>'">Cancel</button>
                    <button type="button" class="btn btn-primary" id="nextBtn" onclick="nextTab()">Next <span>→</span></button>
                    <button type="submit" class="btn btn-primary" id="submitBtn" style="display:none;"><span><i class="fas fa-check"></i></span> Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const tabs = ['basic', 'details', 'media', 'team'];
let currentTabIndex = 0;

function showTab(tabId) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.mp-tab-buttons button').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    currentTabIndex = tabs.indexOf(tabId);
    document.querySelectorAll('.mp-tab-buttons button')[currentTabIndex].classList.add('active');
    updateButtons();
}

function nextTab() {
    if (currentTabIndex < tabs.length - 1) { currentTabIndex++; showTabByIndex(currentTabIndex); }
}
function previousTab() {
    if (currentTabIndex > 0) { currentTabIndex--; showTabByIndex(currentTabIndex); }
}
function showTabByIndex(index) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.mp-tab-buttons button').forEach(b => b.classList.remove('active'));
    document.getElementById(tabs[index]).classList.add('active');
    document.querySelectorAll('.mp-tab-buttons button')[index].classList.add('active');
    updateButtons();
}
function updateButtons() {
    document.getElementById('prevBtn').style.display   = currentTabIndex === 0 ? 'none' : 'inline-flex';
    document.getElementById('nextBtn').style.display   = currentTabIndex === tabs.length - 1 ? 'none' : 'inline-flex';
    document.getElementById('submitBtn').style.display = currentTabIndex === tabs.length - 1 ? 'inline-flex' : 'none';
}

function toggleTeamMember(checkbox) {
    checkbox.closest('.team-member').classList.toggle('selected', checkbox.checked);
}

// ── Gallery delete ────────────────────────────────────────────────────────────
function removeGalleryImage(imageId) {
    if (!confirm('Remove this image from the gallery?')) return;

    const item = document.getElementById('gallery-item-' + imageId);
    if (item) {
        item.classList.add('removing');
    }

    fetch('remove_gallery_image.php?id=' + imageId)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                setTimeout(() => {
                    item?.remove();
                    // Update count badge
                    const remaining = document.querySelectorAll('.gallery-grid-item').length;
                    const badge = document.querySelector('.gallery-grid')?.previousElementSibling?.querySelector('span');
                    if (badge) badge.textContent = remaining + ' image' + (remaining !== 1 ? 's' : '');
                    // Show empty state if none left
                    if (remaining === 0) {
                        document.getElementById('galleryGrid').outerHTML =
                            `<div class="gallery-empty"><div class="gallery-empty-icon"><i class="fas fa-image"></i>️</div>No gallery images yet. Upload some below.</div>`;
                    }
                }, 300);
            } else {
                item?.classList.remove('removing');
                alert(data.message || 'Failed to remove image');
            }
        })
        .catch(() => {
            item?.classList.remove('removing');
            alert('An error occurred. Please try again.');
        });
}

// File upload previews
document.getElementById('thumbnail')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        e.target.closest('.upload-area').querySelector('.upload-text').innerHTML =
            `<strong><i class="fas fa-check"></i> ${file.name}</strong><br><small>Click to change</small>`;
    }
});
document.getElementById('gallery')?.addEventListener('change', function(e) {
    const files = e.target.files;
    if (files.length > 0) {
        e.target.closest('.upload-area').querySelector('.upload-text').innerHTML =
            `<strong><i class="fas fa-check"></i> ${files.length} file(s) selected</strong><br><small>Click to change</small>`;
    }
});

// Form validation
document.getElementById('projectForm').addEventListener('submit', function(e) {
    const name   = document.querySelector('input[name="project_name"]').value;
    const client = document.querySelector('select[name="client_id"]').value;
    if (!name || !client) {
        e.preventDefault();
        alert('Please fill in all required fields (marked with *)');
        showTab('basic');
    }
});

updateButtons();
</script>

<script>
var publicEditor = new Quill('#public-editor', {
    modules: {
        toolbar: [
            [{ 'header': [2, 3, false] }],
            ['bold', 'italic', 'underline', 'blockquote'],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            ['link', 'clean']
        ]
    },
    placeholder: 'Write a compelling narrative for this project...',
    theme: 'snow'
});

var publicInput = document.getElementById('public_description_input');
publicInput.value = publicEditor.root.innerHTML;
publicEditor.on('text-change', function() {
    publicInput.value = publicEditor.root.innerHTML;
});
</script>

<?php include('dashboard_footer.php'); ?>