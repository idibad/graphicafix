<?php

include('dashboard_header.php');

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
         status, priority, start_date, end_date, thumbnail, slug, visibility) 
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");

    $stmt->bind_param(
        "isssssssssss",
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
        $visibility
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

    echo "<script>alert('Project Added Successfully!');window.location='projects.php';</script>";
}
?>


<div class="dashboard">
    <!-- Page Header -->
    <div class="page-header">
        <h1>📋 Create New Project</h1>
        <p>Fill in the details below to add a new project to your workspace</p>
    </div>

    <!-- Tabs Navigation -->
    <div class="tabs-container">
        <div class="tab-buttons">
            <button type="button" class="active" onclick="showTab('basic')">
                <span class="tab-icon">📝</span> Basic Info
            </button>
            <button type="button" onclick="showTab('details')">
                <span class="tab-icon">📄</span> Details
            </button>
            <button type="button" onclick="showTab('media')">
                <span class="tab-icon">🖼️</span> Media
            </button>
            <button type="button" onclick="showTab('team')">
                <span class="tab-icon">👥</span> Team
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

                <div class="form-group">
                    <label>Visibility <span class="required">*</span></label>
                    <select name="visibility" required>
                        <option value="private">Private (Team Only)</option>
                        <option value="client">Client Visible</option>
                        <option value="public">Public</option>
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
                    <textarea name="description" placeholder="Internal project description for team members..."></textarea>
                </div>

                <div class="form-group">
                    <label>Public Description</label>
                    <textarea name="public_description" placeholder="Client-facing project description..."></textarea>
                </div>

                <div class="form-group">
                    <label>Internal Notes</label>
                    <textarea name="internal_notes" placeholder="Private notes, instructions, or important information..."></textarea>
                </div>
            </div>

            <!-- Media Tab -->
            <div id="media" class="tab">
                <h3>Project Media</h3>

                <div class="form-group">
                    <label>Project Thumbnail</label>
                    <div class="upload-area" onclick="document.getElementById('thumbnail').click()">
                        <div class="upload-icon">🖼️</div>
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
                        <div class="upload-icon">📸</div>
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
                    <div class="info-title">👥 Team Assignment</div>
                    <div class="info-text">Select team members who will be working on this project. They will receive notifications and have access based on their roles.</div>
                </div>

                <div class="team-grid">
                    <?php
                    $users = $conn->query("SELECT user_id, name FROM users");
                    while($u=$users->fetch_assoc()){
                        $initial = strtoupper(substr($u['name'], 0, 1));
                        echo "
                        <div class='team-member'>
                            <input type='checkbox' name='team_members[]' value='{$u['user_id']}' id='user_{$u['user_id']}'>
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
                    <span>✓</span> Create Project
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
    document.querySelectorAll('.tab-buttons button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabId).classList.add('active');
    
    // Add active class to clicked button
    const buttons = document.querySelectorAll('.tab-buttons button');
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
    document.querySelectorAll('.tab-buttons button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabId).classList.add('active');
    
    // Add active class to corresponding button
    const buttons = document.querySelectorAll('.tab-buttons button');
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
</script>

<?php 
include('dashboard_footer.php');
?>