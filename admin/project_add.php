<?php

include 'dashboard_header.php';

if ($role !== 'admin' && $role !== 'pm') {
    header("Location: manage_projects.php?error=unauthorized");
    exit;
}

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
    $category = $_POST['category'] ?? '';
    $team_members = $_POST['team_members'] ?? []; // array
    $slug = strtolower(str_replace(' ', '-', $project_name));

    // Upload thumbnail
    $thumbPath = "";
    if (!empty($_FILES['thumbnail']['name'])) {
        $thumbDir = "uploads/projects/" . time();
        mkdir($thumbDir, 0777, true);
        $thumbPath = $thumbDir . "/thumb_" . $_FILES['thumbnail']['name'];
        move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbPath);
    }

    // Insert into projects
    $stmt = $conn->prepare("INSERT INTO projects 
        (client_id, project_name, description, public_description, internal_notes, 
         status, priority, start_date, end_date, thumbnail, slug, visibility, category) 
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");

    $stmt->bind_param(
        "issssssssssss",
        $client_id,
        $project_name,
        $description,
        $public_description,
        $internal_notes,
        $status,
        $priority,
        $start_date,
        $end_date,
        $thumbPath,
        $slug,
        $visibility,
        $category
    );

    $stmt->execute();
    $project_id = $stmt->insert_id;

    // Insert team members
    foreach ($team_members as $uid) {
        $conn->query("INSERT INTO project_team (project_id, user_id) VALUES ($project_id, $uid)");
    }

    // Upload gallery images
    if (!empty($_FILES['gallery']['tmp_name'])) {
        foreach ($_FILES['gallery']['tmp_name'] as $key => $tmp) {
            if (!empty($tmp)) {
                $imgPath = $thumbDir . "/img_" . $_FILES['gallery']['name'][$key];
                move_uploaded_file($tmp, $imgPath);

                $conn->query("INSERT INTO project_gallery (project_id, image_path, sort_order) 
                              VALUES ($project_id, '$imgPath', $key)");

                $conn->query("INSERT INTO project_files (project_id, file_path, file_type) 
                              VALUES ($project_id, '$imgPath', 'image')");
            }
        }
    }

    // Insert thumbnail into project_files
    if ($thumbPath) {
        $conn->query("INSERT INTO project_files (project_id, file_path, file_type) 
                      VALUES ($project_id, '$thumbPath', 'thumbnail')");
    }

    echo "<script>alert('Project Added Successfully!');window.location='manage_projects.php';</script>";
}
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js"></script>

<div class="dashboard">
    <!-- Page Header -->
    <div class="page-header">
        <h1><i class="fas fa-clipboard color-dark"></i> Create New Project</h1>
        <p>Fill in the details below to add a new project to your workspace</p>
    </div>

    <!-- Tabs Navigation -->
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

    <!-- Form Container -->
    <div class="form-container">
        <form method="POST" enctype="multipart/form-data" id="projectForm">

            <!-- Basic Info Tab -->
            <div id="basic" class="tab active">
                <h3>Basic Information</h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Project Name <span class="required">*</span></label>
                        <input type="text" name="project_name" placeholder="Enter project name" required>
                    </div>

                    <div class="form-group">
                        <label>Client <span class="required">*</span></label>
                        <select name="client_id" required>
                            <option value="">Select a client</option>
                            <?php
                            $res = $conn->query("SELECT id, client_name FROM clients");
                            while($row=$res->fetch_assoc()){
                                echo "<option value='{$row['id']}'>{$row['client_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date">
                    </div>

                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Status <span class="required">*</span></label>
                        <select name="status" required>
                            <option value="Not Started">Not Started</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="On Hold">On Hold</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Priority <span class="required">*</span></label>
                        <select name="priority" required>
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Visibility <span class="required">*</span></label>
                        <select name="visibility" required>
                            <option value="private">Private (Team Only)</option>
                            <option value="client">Client Visible</option>
                            <option value="public">Public</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Category <span class="required">*</span></label>
                        <select name="category" required>
                            <option value="">Select a category</option>
                            <option value="branding">Branding</option>
                            <option value="web-design">Web Design</option>
                            <option value="social-media">Social Media</option>
                            <option value="marketing">Marketing</option>
                            <option value="packaging">Packaging</option>
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
                    <textarea name="description" placeholder="Internal project description for team members..."></textarea>
                </div>

                

                <div class="form-group">
                    <label>Internal Notes</label>
                    <textarea name="internal_notes" placeholder="Private notes, instructions, or important information..."></textarea>
                </div>

                <div class="form-group mb-4">
    <label class="form-label fw-bold text-secondary" style="letter-spacing: 1px; font-size: 0.85rem; text-transform: uppercase;">
        Public Description
    </label>
    
    <div id="public-editor-wrapper" style="background: #fff; border-radius: 8px;">
        <textarea id="public-editor" style="width: 100%; height: 350px; font-family: 'Outfit', sans-serif; font-size: 1.1rem;">
            <?= isset($project['public_description']) ? $project['public_description'] : '' ?>
        </textarea>
    </div>

    <input type="hidden" name="public_description" id="public_description_input">
</div>
            </div>

            <!-- Media Tab -->
            <div id="media" class="tab">
                <h3>Project Media</h3>

                <div class="form-group">
                    <label>Project Thumbnail</label>
                    <div class="upload-area" onclick="document.getElementById('thumbnail').click()">
                        <div class="upload-icon"><i class="fas fa-image"></i>️</div>
                        <div class="upload-text">
                            <strong>Click to upload</strong> or drag and drop<br>
                            <small>PNG, JPG, GIF up to 10MB</small>
                        </div>
                        <input type="file" name="thumbnail" id="thumbnail" accept="image/*">
                    </div>
                </div>

                <div class="form-group">
                    <label>Gallery Images</label>
                    <div class="upload-area" onclick="document.getElementById('gallery').click()">
                        <div class="upload-icon"><i class="fas fa-camera"></i></div>
                        <div class="upload-text">
                            <strong>Click to upload multiple images</strong><br>
                            <small>Select multiple files for project gallery</small>
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
                    <div class="info-text">Select team members who will be working on this project. They will receive notifications and have access based on their roles.</div>
                </div>

                <style>
                /* Premium Team Member Card Grid */
                .team-cards-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                    gap: 16px;
                    margin-top: 20px;
                }
                .team-card {
                    position: relative;
                    background: #ffffff;
                    border: 2px solid #e2e8f0;
                    border-radius: 16px;
                    padding: 20px;
                    text-align: center;
                    cursor: pointer;
                    transition: all 0.25s ease;
                    user-select: none;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 12px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
                }
                .team-card:hover {
                    transform: translateY(-3px);
                    border-color: #cbd5e1;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
                }
                .team-card.selected {
                    border-color: #024442;
                    background: rgba(2, 68, 66, 0.02);
                    box-shadow: 0 4px 12px rgba(2, 68, 66, 0.06);
                }
                .team-card-checkbox {
                    position: absolute;
                    top: 12px;
                    right: 12px;
                    width: 18px;
                    height: 18px;
                    border-radius: 4px;
                    cursor: pointer;
                    accent-color: #024442;
                }
                .team-card-avatar {
                    width: 56px;
                    height: 56px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #024442 0%, #036b68 100%);
                    color: #ffffff;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 20px;
                    font-weight: 700;
                    box-shadow: 0 4px 10px rgba(2, 68, 66, 0.15);
                    transition: transform 0.25s ease;
                }
                .team-card.selected .team-card-avatar {
                    transform: scale(1.05);
                    background: linear-gradient(135deg, #B6F763 0%, #87bd0a 100%);
                    color: #024442;
                    box-shadow: 0 4px 10px rgba(182, 247, 99, 0.25);
                }
                .team-card-name {
                    font-size: 14px;
                    font-weight: 700;
                    color: #0f172a;
                    margin: 0;
                    line-height: 1.3;
                    text-transform: capitalize;
                }
                .team-card-role {
                    font-size: 11px;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    color: #64748b;
                    font-weight: 600;
                    margin-top: -6px;
                }
                </style>

                <div class="team-cards-grid">
                    <?php
                    $users = $conn->query("SELECT user_id, name, role FROM users WHERE role != 'student' AND role != 'client' AND role != 'client_sub' AND status = 'active'");
                    while($u=$users->fetch_assoc()){
                        $initial = strtoupper(substr($u['name'], 0, 1));
                        echo "
                        <div class='team-card' onclick='selectTeamCard(this)'>
                            <input type='checkbox' name='team_members[]' value='{$u['user_id']}' class='team-card-checkbox' onclick='event.stopPropagation(); toggleTeamCardState(this.parentElement, this.checked);'>
                            <div class='team-card-avatar'>{$initial}</div>
                            <div class='team-card-name'>{$u['name']}</div>
                            <div class='team-card-role'>" . htmlspecialchars(ucfirst($u['role'])) . "</div>
                        </div>
                        ";
                    }
                    ?>
                </div>

                <script>
                function selectTeamCard(card) {
                    const checkbox = card.querySelector('.team-card-checkbox');
                    checkbox.checked = !checkbox.checked;
                    toggleTeamCardState(card, checkbox.checked);
                }
                function toggleTeamCardState(card, isChecked) {
                    card.classList.toggle('selected', isChecked);
                }
                </script>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" id="prevBtn" onclick="previousTab()" style="display: none;">
                    <span>←</span> Previous
                </button>
                <button type="button" class="btn btn-secondary" onclick="window.location='projects.php'">
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" id="nextBtn" onclick="nextTab()">
                    Next <span>→</span>
                </button>
                <button type="submit" class="btn btn-primary" id="submitBtn" style="display: none;">
                    <span><i class="fas fa-check"></i></span> Create Project
                </button>
            </div>

        </form>
    </div>
</div>

<script>
const tabs = ['basic', 'details', 'media', 'team'];
let currentTabIndex = 0;

function showTab(tabId) {
    // Hide all tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.mp-tab-buttons button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabId).classList.add('active');
    
    // Add active class to clicked button
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
    
    // Hide all tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.mp-tab-buttons button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabId).classList.add('active');
    
    // Add active class to corresponding button
    const buttons = document.querySelectorAll('.mp-tab-buttons button');
    buttons[index].classList.add('active');
    
    updateButtons();
}

function updateButtons() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    
    // Show/hide previous button
    if (currentTabIndex === 0) {
        prevBtn.style.display = 'none';
    } else {
        prevBtn.style.display = 'inline-flex';
    }
    
    // Show/hide next and submit buttons
    if (currentTabIndex === tabs.length - 1) {
        nextBtn.style.display = 'none';
        submitBtn.style.display = 'inline-flex';
    } else {
        nextBtn.style.display = 'inline-flex';
        submitBtn.style.display = 'none';
    }
}

// File upload preview
document.getElementById('thumbnail')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const uploadArea = e.target.closest('.upload-area');
        uploadArea.querySelector('.upload-text').innerHTML = `
            <strong><i class="fas fa-check"></i> ${file.name}</strong><br>
            <small>Click to change</small>
        `;
    }
});

document.getElementById('gallery')?.addEventListener('change', function(e) {
    const files = e.target.files;
    if (files.length > 0) {
        const uploadArea = e.target.closest('.upload-area');
        uploadArea.querySelector('.upload-text').innerHTML = `
            <strong><i class="fas fa-check"></i> ${files.length} file(s) selected</strong><br>
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
</script>
<script>
    // Initialize TinyMCE
    var publicInput = document.getElementById('public_description_input');
    
    tinymce.init({
        selector: '#public-editor',
        height: 350,
        menubar: false,
        plugins: 'image table link lists',
        toolbar: 'undo redo | blocks | bold italic underline blockquote | alignleft aligncenter alignright | bullist numlist | link image table | removeformat',
        setup: function(editor) {
            editor.on('init change keyup', function() {
                publicInput.value = editor.getContent();
            });
        }
    });
</script>
<?php 
include('dashboard_footer.php');
?>