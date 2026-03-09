<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/graphicafix/config.php';

require_once('dashboard_header.php');


// Get project ID from URL
$id = $_GET['id'] ?? 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_name = $_POST['project_name'];
    $client_id = $_POST['client_id'];
    $description = $_POST['description'];
    $public_description = $_POST['public_description'];
    $internal_notes = $_POST['internal_notes'];
    $status = $_POST['status'];
    $priority = $_POST['priority'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $visibility = $_POST['visibility'];
    $team_members = $_POST['team_members'] ?? [];
    $slug = strtolower(str_replace(' ', '-', $project_name));


    $thumbDir = "images/uploads/projects";
    // Upload new thumbnail if provided
    $thumbPath = $_POST['existing_thumbnail'] ?? '';
    if (!empty($_FILES['thumbnail']['name'])) {
        $thumbDir = "images/uploads/projects" . time();
        mkdir($thumbDir, 0777, true);
        $thumbPath = $thumbDir . "/thumb_" . $_FILES['thumbnail']['name'];
        move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbPath);
    }

    // Update project
    $stmt = $conn->prepare("UPDATE projects SET 
        client_id = ?, project_name = ?, description = ?, public_description = ?, 
        internal_notes = ?, status = ?, priority = ?, start_date = ?, end_date = ?, 
        thumbnail = ?, slug = ?, visibility = ?
        WHERE id = ?");

    $stmt->bind_param(
        "isssssssssssi",
        $client_id, $project_name, $description, $public_description,
        $internal_notes, $status, $priority, $start_date, $end_date,
        $thumbPath, $slug, $visibility, $id
    );

    $stmt->execute();

    // Update team members
    $conn->query("DELETE FROM project_team WHERE project_id = $id");

foreach ($team_members as $uid) {
    $stmt = $conn->prepare("INSERT INTO project_team (project_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $id, $uid);
    $stmt->execute();
}

    // Upload new gallery images if provided
    if (!empty($_FILES['gallery']['tmp_name'][0])) {
        foreach ($_FILES['gallery']['tmp_name'] as $key => $tmp) {
            if (!empty($tmp)) {
                $imgPath = $thumbDir . "/img_" . $_FILES['gallery']['name'][$key];
                move_uploaded_file($tmp, $imgPath);

                $conn->query("INSERT INTO project_gallery (project_id, image_path, sort_order) 
                    VALUES ($id, '$imgPath', $key)");

                $conn->query("INSERT INTO project_files (project_id, file_path, file_type) 
                            VALUES ($id, '$imgPath', 'image')");
            }
        }
    }

    echo "<script>alert('Project Updated Successfully!');window.location='project_details.php?id=$id';</script>";
}


// Fetch project details
$project_query = "SELECT p.* FROM projects p WHERE p.id = ?";
$stmt = $conn->prepare($project_query);
$stmt->bind_param("i", $id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    echo "<script>alert('Project not found');window.location='projects.php';</script>";
    exit;
}

// Fetch assigned team members
$team_query = "SELECT user_id FROM project_team WHERE project_id = ?";

$stmt = $conn->prepare($team_query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$assigned_members = [];
while ($row = $result->fetch_assoc()) {
    $assigned_members[] = $row['user_id'];
}

// Fetch existing gallery images
$gallery_query = "SELECT * FROM project_gallery WHERE id = ? ORDER BY sort_order";
$stmt = $conn->prepare($gallery_query);
$stmt->bind_param("i", $id);
$stmt->execute();
$gallery = $stmt->get_result();
?>

<div class="dashboard">
    <!-- Page Header -->
    <div class="page-header">
        <h1>✏️ Edit Project</h1>
        <p>Update project information and settings</p>
    </div>

    <!-- Tabs Navigation -->
    <div class="mp-tabs-container">
        <div class="mp-tab-buttons">
            <button type="button" class="active" onclick="showTab('basic')">
                <span class="mp-tab-icon">📝</span> Basic Info
            </button>
            <button type="button" onclick="showTab('details')">
                <span class="mp-tab-icon">📄</span> Details
            </button>
            <button type="button" onclick="showTab('media')">
                <span class="mp-tab-icon">🖼️</span> Media
            </button>
            <button type="button" onclick="showTab('team')">
                <span class="mp-tab-icon">👥</span> Team
            </button>
        </div>
    </div>

    <!-- Form Container -->
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
                            while($row=$res->fetch_assoc()){
                                $selected = ($row['id'] == $project['client_id']) ? 'selected' : '';
                                echo "<option value='{$row['id']}' $selected>{$row['client_name']}</option>";
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
                            <option value="In Progress" <?= $project['status'] == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="Completed" <?= $project['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="On Hold" <?= $project['status'] == 'On Hold' ? 'selected' : '' ?>>On Hold</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Priority <span class="required">*</span></label>
                        <select name="priority" required>
                            <option value="Low" <?= $project['priority'] == 'Low' ? 'selected' : '' ?>>Low</option>
                            <option value="Medium" <?= $project['priority'] == 'Medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="High" <?= $project['priority'] == 'High' ? 'selected' : '' ?>>High</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Visibility <span class="required">*</span></label>
                    <select name="visibility" required>
                        <option value="private" <?= $project['visibility'] == 'private' ? 'selected' : '' ?>>Private (Team Only)</option>
                        <option value="client" <?= $project['visibility'] == 'client' ? 'selected' : '' ?>>Client Visible</option>
                        <option value="public" <?= $project['visibility'] == 'public' ? 'selected' : '' ?>>Public</option>
                    </select>
                </div>
            </div>

            <!-- Details Tab -->
            <div id="details" class="tab">
                <h3>Project Details</h3>

                <div class="info-box">
                    <div class="info-title">💡 Description Guidelines</div>
                    <div class="info-text">Team description is for internal use. Public description will be visible to clients and stakeholders.</div>
                </div>

                <div class="form-group">
                    <label>Team Description</label>
                    <textarea name="description" placeholder="Internal project description for team members..."><?= htmlspecialchars($project['description']) ?></textarea>
                </div>

                <!-- <div class="form-group">
                    <label>Public Description</label>
                    <textarea name="public_description" placeholder="Client-facing project description..."><?= htmlspecialchars($project['public_description']) ?></textarea>
                </div> -->

                <div class="form-group">
                    <label>Internal Notes</label>
                    <textarea name="internal_notes" placeholder="Private notes, instructions, or important information..."><?= htmlspecialchars($project['internal_notes']) ?></textarea>
                </div>

                <div class="form-group mb-4">
                <label class="form-label fw-bold text-secondary" style="letter-spacing: 1px; font-size: 0.85rem; text-transform: uppercase;">
                    Public Description
                </label>
                
                <div id="public-editor-wrapper" style="background: #fff; border-radius: 8px;">
                    <div id="public-editor" style="height: 350px; font-family: 'Outfit', sans-serif; font-size: 1.1rem;">
                        <?= isset($project['public_description']) ? $project['public_description'] : '' ?>
                    </div>
                </div>

                <input type="hidden" name="public_description" id="public_description_input">
                </div>
            </div>

            <!-- Media Tab -->
            <div id="media" class="tab">
                <h3>Project Media</h3>

                <div class="form-group">
                    <label>Project Thumbnail</label>
                    
                    <?php if (!empty($project['thumbnail'])): ?>
                    <div class="current-thumbnail">
                        <div class="current-thumbnail-label">Current Thumbnail:</div>
                        <div class="thumbnail-preview">
                            <img src="<?= htmlspecialchars($project['thumbnail']) ?>" alt="Current thumbnail">
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="upload-area" onclick="document.getElementById('thumbnail').click()">
                        <div class="upload-icon">🖼️</div>
                        <div class="upload-text">
                            <strong>Click to upload new thumbnail</strong> or drag and drop<br>
                            <small>PNG, JPG, GIF up to 10MB</small>
                        </div>
                        <input type="file" name="thumbnail" id="thumbnail" accept="image/*">
                    </div>
                </div>

                <?php if ($gallery->num_rows > 0): ?>
                <div class="form-group">
                    <label>Current Gallery Images</label>
                    <div class="gallery-preview">
                        <?php while ($img = $gallery->fetch_assoc()): ?>
                        <div class="gallery-item">
                            <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="Gallery image">
                            <button type="button" class="remove-btn" onclick="removeGalleryImage(<?= $img['id'] ?>)" title="Remove image">×</button>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Add New Gallery Images</label>
                    <div class="upload-area" onclick="document.getElementById('gallery').click()">
                        <div class="upload-icon">📸</div>
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
                    <div class="info-title">👥 Team Assignment</div>
                    <div class="info-text">Select team members who will be working on this project. They will receive notifications and have access based on their roles.</div>
                </div>

                <div class="team-grid">
                    <?php
                    $users = $conn->query("SELECT user_id, name FROM users");
                    while($u=$users->fetch_assoc()){
                        $initial = strtoupper(substr($u['name'], 0, 1));
                        $checked = in_array($u['user_id'], $assigned_members) ? 'checked' : '';
                        $selected = in_array($u['user_id'], $assigned_members) ? 'selected' : '';
                        echo "
                        <div class='team-member $selected'>
                            <input type='checkbox' name='team_members[]' value='{$u['user_id']}' id='user_{$u['user_id']}' $checked onchange='toggleTeamMember(this)'>
                            <div class='team-avatar'>{$initial}</div>
                            <label for='user_{$u['user_id']}'>{$u['name']}</label>
                        </div>
                        ";
                    }
                    ?>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <div style="display: flex; gap: 12px;">
                    <button type="button" class="btn btn-secondary" id="prevBtn" onclick="previousTab()" style="display: none;">
                        <span>←</span> Previous
                    </button>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button type="button" class="btn btn-secondary" onclick="window.location='project_details.php?id=<?= $id ?>'">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="nextBtn" onclick="nextTab()">
                        Next <span>→</span>
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn" style="display: none;">
                        <span>✓</span> Save Changes
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>
<script>
const tabs = ['basic', 'details', 'media', 'team'];
let currentTabIndex = 0;

function showTab(tabId) {
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    document.querySelectorAll('.mp-tab-buttons button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    document.getElementById(tabId).classList.add('active');
    
    const buttons = document.querySelectorAll('.mp-tab-buttons button');
    currentTabIndex = tabs.indexOf(tabId);
    buttons[currentTabIndex].classList.add('active');
    
    updateButtons();
}

function nextTab() {
    if (currentTabIndex < tabs.length - 1) {
        currentTabIndex++;
        showTabByIndex(currentTabIndex);
    }
}

function previousTab() {
    if (currentTabIndex > 0) {
        currentTabIndex--;
        showTabByIndex(currentTabIndex);
    }
}

function showTabByIndex(index) {
    const tabId = tabs[index];
    
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    document.querySelectorAll('.mp-tab-buttons button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    document.getElementById(tabId).classList.add('active');
    
    const buttons = document.querySelectorAll('.mp-tab-buttons button');
    buttons[index].classList.add('active');
    
    updateButtons();
}

function updateButtons() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    
    if (currentTabIndex === 0) {
        prevBtn.style.display = 'none';
    } else {
        prevBtn.style.display = 'inline-flex';
    }
    
    if (currentTabIndex === tabs.length - 1) {
        nextBtn.style.display = 'none';
        submitBtn.style.display = 'inline-flex';
    } else {
        nextBtn.style.display = 'inline-flex';
        submitBtn.style.display = 'none';
    }
}

// Toggle team member selection visual feedback
function toggleTeamMember(checkbox) {
    const teamMember = checkbox.closest('.team-member');
    if (checkbox.checked) {
        teamMember.classList.add('selected');
    } else {
        teamMember.classList.remove('selected');
    }
}

// Remove gallery image
function removeGalleryImage(imageId) {
    if (confirm('Are you sure you want to remove this image?')) {
        fetch('remove_gallery_image.php?id=' + imageId)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to remove image');
            }
        })
        .catch(error => {
            alert('An error occurred. Please try again.');
        });
    }
}

// File upload preview
document.getElementById('thumbnail')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const uploadArea = e.target.closest('.upload-area');
        uploadArea.querySelector('.upload-text').innerHTML = `
            <strong>✓ ${file.name}</strong><br>
            <small>Click to change</small>
        `;
    }
});

document.getElementById('gallery')?.addEventListener('change', function(e) {
    const files = e.target.files;
    if (files.length > 0) {
        const uploadArea = e.target.closest('.upload-area');
        uploadArea.querySelector('.upload-text').innerHTML = `
            <strong>✓ ${files.length} file(s) selected</strong><br>
            <small>Click to change</small>
        `;
    }
});

// Form validation
document.getElementById('projectForm').addEventListener('submit', function(e) {
    const projectName = document.querySelector('input[name="project_name"]').value;
    const clientId = document.querySelector('select[name="client_id"]').value;
    
    if (!projectName || !clientId) {
        e.preventDefault();
        alert('Please fill in all required fields (marked with *)');
        showTab('basic');
        return false;
    }
});

// Initialize button states on load
updateButtons();
</script>
<script>
    // 1. Initialize the Editor
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

    // 2. Sync with the Hidden Input
    var publicInput = document.getElementById('public_description_input');
    
    // Set initial value on load
    publicInput.value = publicEditor.root.innerHTML;

    // Update hidden input on every keystroke
    publicEditor.on('text-change', function() {
        publicInput.value = publicEditor.root.innerHTML;
    });
</script>
<?php
   include('dashboard_footer.php');
?>