<?php
// Prevent visual crash
error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/core/config.php';

// ── Auto-provision client_questionnaires table ────────────────────────────────
if ($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS client_questionnaires (
        id INT AUTO_INCREMENT PRIMARY KEY,
        submission_id VARCHAR(50) NOT NULL UNIQUE,
        client_name VARCHAR(150) DEFAULT NULL,
        designation VARCHAR(150) DEFAULT NULL,
        organization VARCHAR(200) DEFAULT NULL,
        email VARCHAR(150) DEFAULT NULL,
        phone VARCHAR(100) DEFAULT NULL,
        confirmation_date DATE DEFAULT NULL,
        primary_objective VARCHAR(255) DEFAULT NULL,
        primary_goals TEXT DEFAULT NULL,
        target_audience TEXT DEFAULT NULL,
        budget_range VARCHAR(100) DEFAULT NULL,
        preferred_launch_date VARCHAR(100) DEFAULT NULL,
        decision_maker VARCHAR(150) DEFAULT NULL,
        status ENUM('new', 'reviewed', 'in_progress', 'proposal_sent', 'archived') DEFAULT 'new',
        admin_notes TEXT DEFAULT NULL,
        form_data LONGTEXT NOT NULL,
        ip_address VARCHAR(50) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Soft update in case table was previously created with NOT NULL
    $conn->query("ALTER TABLE client_questionnaires MODIFY client_name VARCHAR(150) NULL DEFAULT NULL, MODIFY email VARCHAR(150) NULL DEFAULT NULL, MODIFY phone VARCHAR(100) NULL DEFAULT NULL");
}

// ── Handle Form Submission ───────────────────────────────────────────────────
$submission_success = false;
$submitted_id = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_submit'])) {
    $organization = trim($_POST['client_organization'] ?? '');
    if (empty($organization)) {
        $organization = trim($_POST['sec_1_1_official_name'] ?? '');
    }
    $client_name  = trim($_POST['client_name'] ?? '');
    if (empty($client_name)) {
        $client_name = trim($_POST['sec_21_6_decision_maker'] ?? '') ?: ($organization ?: 'Client Submission');
    }
    $designation  = trim($_POST['client_designation'] ?? '');
    $email        = trim($_POST['client_email'] ?? '');
    $phone        = trim($_POST['client_phone'] ?? '');
    $conf_date    = !empty($_POST['client_date']) ? date('Y-m-d', strtotime($_POST['client_date'])) : date('Y-m-d');

    $primary_obj_arr = $_POST['sec_1_2_objectives'] ?? [];
    $primary_objective = is_array($primary_obj_arr) ? implode(', ', $primary_obj_arr) : (string)$primary_obj_arr;
    if (!empty($_POST['sec_1_2_other'])) {
        $primary_objective .= ($primary_objective ? ', ' : '') . 'Other: ' . trim($_POST['sec_1_2_other']);
    }

    $goals_arr = $_POST['sec_2_1_goals'] ?? [];
    $primary_goals = is_array($goals_arr) ? implode(', ', $goals_arr) : (string)$goals_arr;

    $audience_arr = $_POST['sec_3_1_audience'] ?? [];
    $target_audience = is_array($audience_arr) ? implode(', ', $audience_arr) : (string)$audience_arr;

    $budget_range = trim($_POST['sec_21_4_budget'] ?? '');
    $preferred_launch_date = trim($_POST['sec_21_2_launch_date'] ?? '');
    $decision_maker = trim($_POST['sec_21_6_decision_maker'] ?? '');

    if (empty($organization)) {
        $error_msg = "Please provide the official institution or organization name in Section 1.1.";
    } else {
        $submission_id = 'DISC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(mt_rand(), true), -5));

        $form_payload = $_POST;
        unset($form_payload['form_submit']);
        $json_data = json_encode($form_payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt = $conn->prepare("INSERT INTO client_questionnaires 
            (submission_id, client_name, designation, organization, email, phone, confirmation_date, primary_objective, primary_goals, target_audience, budget_range, preferred_launch_date, decision_maker, status, form_data, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', ?, ?, ?)");

        if ($stmt) {
            $stmt->bind_param(
                "ssssssssssssssss",
                $submission_id,
                $client_name,
                $designation,
                $organization,
                $email,
                $phone,
                $conf_date,
                $primary_objective,
                $primary_goals,
                $target_audience,
                $budget_range,
                $preferred_launch_date,
                $decision_maker,
                $json_data,
                $ip_address,
                $user_agent
            );

            if ($stmt->execute()) {
                $submission_success = true;
                $submitted_id = $submission_id;
            } else {
                $error_msg = "Database error: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_msg = "Database statement error.";
        }
    }
}

if (!empty($_GET['submitted'])) {
    $submission_success = true;
    $submitted_id = htmlspecialchars($_GET['submitted']);
}

$page_title = "Discovery Questionnaire | Graphicafix";
include 'templates/header.php';
?>

<style>
/* ── Clean & Simple Form Aesthetics ── */
:root {
    --primary: #024442;
    --primary-dark: #012827;
    --accent: #b8f35a;
    --border: #e2e8f0;
    --bg-page: #f8fafc;
    --text: #1e293b;
    --muted: #64748b;
}

body {
    background-color: var(--bg-page) !important;
    font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--text);
}

.form-page-container {
    max-width: 900px;
    margin: 40px auto 80px;
    padding: 0 20px;
}

/* Page Intro */
.form-header-box {
    background: #ffffff;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 32px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}

.form-header-box h1 {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary);
    margin: 0 0 6px;
}

.form-header-box h2 {
    font-size: 1.15rem;
    font-weight: 500;
    color: var(--muted);
    margin: 0 0 16px;
}

.form-header-box p {
    font-size: 0.92rem;
    line-height: 1.6;
    color: #475569;
    margin: 0 0 20px;
}

.autosave-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 16px;
    border-top: 1px solid #f1f5f9;
    font-size: 0.85rem;
}

.autosave-status {
    color: #059669;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-reset-draft {
    background: transparent;
    border: 1px solid #cbd5e1;
    color: var(--muted);
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-reset-draft:hover {
    background: #fee2e2;
    border-color: #fca5a5;
    color: #dc2626;
}

/* Simple Section Card */
.form-section {
    background: #ffffff;
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 28px 30px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}

.form-section-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 22px;
    padding-bottom: 12px;
    border-bottom: 1.5px solid #f1f5f9;
}

/* Question Rows */
.q-block {
    margin-bottom: 22px;
}

.q-block:last-child {
    margin-bottom: 0;
}

.q-label {
    display: block;
    font-size: 0.95rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 8px;
    line-height: 1.45;
}

.q-sub {
    font-size: 0.82rem;
    color: var(--muted);
    margin-top: -4px;
    margin-bottom: 10px;
}

/* Inputs */
.form-control, .form-select {
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 0.92rem;
    color: #1e293b;
    background-color: #ffffff;
    transition: border-color 0.2s;
    width: 100%;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.1);
}

textarea.form-control {
    min-height: 90px;
    resize: vertical;
}

/* Simple Checkbox & Radio Grid */
.choice-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 10px;
    margin-top: 6px;
}

.choice-label {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 0.9rem;
    color: #334155;
    cursor: pointer;
    margin: 0;
    user-select: none;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 9px 12px;
    border-radius: 8px;
    transition: background 0.15s, border-color 0.15s;
}

.choice-label:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.choice-label input {
    cursor: pointer;
    accent-color: var(--primary);
    width: 16px;
    height: 16px;
    margin: 0;
}

/* Submit Button */

.btn-submit-main {
    background: var(--primary);
    color: #ffffff;
    border: none;
    padding: 14px 40px;
    font-size: 1.05rem;
    font-weight: 700;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
    width: 100%;
}

.btn-submit-main:hover {
    background: var(--primary-dark);
}

/* Success Card */
.success-box {
    background: #ffffff;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 50px 30px;
    text-align: center;
    max-width: 650px;
    margin: 60px auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}

.success-box h2 {
    color: var(--primary);
    font-size: 1.7rem;
    font-weight: 700;
    margin-bottom: 12px;
}

.ref-badge {
    display: inline-block;
    background: #f1f5f9;
    border: 1px dashed var(--primary);
    font-family: monospace;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary);
    padding: 10px 20px;
    border-radius: 8px;
    margin: 16px 0 24px;
}
</style>

<?php if ($submission_success): ?>
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  SUCCESS SCREEN                                                        -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="container">
    <div class="success-box">
        <h2>Questionnaire Submitted Successfully</h2>
        <p style="color:#64748b; font-size:0.95rem;">
            Thank you for completing the discovery questionnaire. Your information has been recorded and will be used to formulate your tailored project proposal.
        </p>
        <div class="ref-badge"><?= htmlspecialchars($submitted_id) ?></div>
        <div>
            <a href="<?= BASE_URL ?>questionnaire.php" class="btn btn-outline-secondary btn-sm me-2">Submit Another</a>
            <a href="<?= BASE_URL ?>" class="btn btn-primary btn-sm" style="background:var(--primary); border-color:var(--primary);">Return to Home</a>
        </div>
    </div>
</div>

<script>
localStorage.removeItem('simple_discovery_draft');
</script>

<?php else: ?>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  MAIN FORM                                                             -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="form-page-container">

    <!-- Header Box -->
    <div class="form-header-box">
        <h1>Website Redesign & Digital Platform</h1>
        <h2>Client Discovery Questionnaire</h2>
        <p>
            Please answer the questions as accurately as possible to help us understand your institution, current website challenges, goals, requirements, and expectations. If a question does not apply, simply write "N/A".
        </p>
        <div class="autosave-bar">
            <div class="autosave-status" id="draftStatus">
                <span>●</span> Auto-save enabled
            </div>
            <button type="button" class="btn-reset-draft" onclick="clearDraft()">Reset Draft</button>
        </div>
    </div>

    <?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger" role="alert" style="border-radius:8px;">
        <?= htmlspecialchars($error_msg) ?>
    </div>
    <?php endif; ?>

    <form id="discoveryForm" method="POST" action="questionnaire.php">
        <input type="hidden" name="form_submit" value="1">

        <!-- ── 1. Institution & Business Overview ── -->
        <div class="form-section">
            <div class="form-section-title">1. Institution & Business Overview</div>

            <div class="q-block">
                <label class="q-label">1.1 Please provide the official name(s) of the institution/education group that should appear on the website.</label>
                <input type="text" name="sec_1_1_official_name" class="form-control" placeholder="Official institution or education group name" required>
            </div>

            <div class="q-block">
                <label class="q-label">1.2 What is the primary objective of the website?</label>
                <div class="choice-grid">
                    <?php
                    $objectives = [
                        'General information', 'Student admissions', 'Parent communication',
                        'Campus information', 'Branding / institutional presence',
                        'Online applications', 'Student/parent portal', 'All of the above'
                    ];
                    foreach ($objectives as $obj): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_1_2_objectives[]" value="<?= $obj ?>"> <?= $obj ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-2">
                    <input type="text" name="sec_1_2_other" class="form-control" placeholder="Other objective (if applicable)...">
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">1.3 What are the most important things you want visitors to understand about your institution within the first 30 seconds of visiting the website?</label>
                <textarea name="sec_1_3_first_30s" class="form-control"></textarea>
            </div>

            <div class="q-block">
                <label class="q-label">1.4 What makes your institution different from other schools/colleges in Pakistan?</label>
                <textarea name="sec_1_4_differentiation" class="form-control"></textarea>
            </div>

            <div class="q-block">
                <label class="q-label">1.5 What are the 3–5 strongest achievements or facts about your institution that should be highlighted?</label>
                <textarea name="sec_1_5_top_achievements" class="form-control"></textarea>
            </div>

            <div class="q-block">
                <label class="q-label">1.6 Are the current figures published on the website accurate? Please provide updated figures:</label>
                <div class="row g-2">
                    <div class="col-sm-6 col-md-4">
                        <label class="small text-muted">Year established:</label>
                        <input type="text" name="sec_1_6_year_est" class="form-control">
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <label class="small text-muted">Number of campuses:</label>
                        <input type="text" name="sec_1_6_num_campuses" class="form-control">
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <label class="small text-muted">Number of students:</label>
                        <input type="text" name="sec_1_6_num_students" class="form-control">
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <label class="small text-muted">Number of faculty/staff:</label>
                        <input type="text" name="sec_1_6_num_faculty" class="form-control">
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <label class="small text-muted">Cities/areas served:</label>
                        <input type="text" name="sec_1_6_cities_served" class="form-control">
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <label class="small text-muted">Other important statistics:</label>
                        <input type="text" name="sec_1_6_other_stats" class="form-control">
                    </div>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">1.7 Are there any claims, statistics, programs, campuses, or information currently displayed on the website that should be removed or corrected?</label>
                <textarea name="sec_1_7_corrections" class="form-control"></textarea>
            </div>
        </div>

        <!-- ── 2. Website Goals ── -->
        <div class="form-section">
            <div class="form-section-title">2. Website Goals</div>

            <div class="q-block">
                <label class="q-label">2.1 What are the primary goals of the new website?</label>
                <div class="choice-grid">
                    <?php
                    $goals = [
                        'Increasing admissions', 'Increasing admission enquiries',
                        'Improving institutional credibility', 'Providing campus information',
                        'Providing fee information', 'Online admission applications',
                        'Parent/student communication', 'Showcasing academic achievements',
                        'Showcasing campus facilities', 'SEO / Google visibility', 'Online payments'
                    ];
                    foreach ($goals as $g): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_2_1_goals[]" value="<?= $g ?>"> <?= $g ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-2">
                    <input type="text" name="sec_2_1_other" class="form-control" placeholder="Other goal (if applicable)...">
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">2.2 What would make you consider the new website a successful project?</label>
                <div class="choice-grid">
                    <?php
                    $successes = [
                        'More admission enquiries', 'More online applications',
                        'Better Google rankings', 'More website visitors',
                        'Better parent feedback', 'Easier management for staff'
                    ];
                    foreach ($successes as $s): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_2_2_success_criteria[]" value="<?= $s ?>"> <?= $s ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-2">
                    <input type="text" name="sec_2_2_other" class="form-control" placeholder="Other success criteria...">
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">2.3 Do you currently track website visitors, enquiries, applications, or conversions?</label>
                <div class="d-flex gap-4 mb-2">
                    <label><input type="radio" name="sec_2_3_tracking" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_2_3_tracking" value="No"> No</label>
                </div>
                <input type="text" name="sec_2_3_tracking_details" class="form-control" placeholder="If yes, please provide available information...">
            </div>
        </div>

        <!-- ── 3. Target Audience ── -->
        <div class="form-section">
            <div class="form-section-title">3. Target Audience</div>

            <div class="q-block">
                <label class="q-label">3.1 Who are the primary visitors to the website?</label>
                <div class="choice-grid">
                    <?php
                    $auds = ['Parents', 'Students', 'Teachers', 'Existing students/parents', 'Prospective students', 'Alumni', 'Government/education authorities', 'General public'];
                    foreach ($auds as $a): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_3_1_audience[]" value="<?= $a ?>"> <?= $a ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">3.2 Which audience should receive the greatest focus?</label>
                <input type="text" name="sec_3_2_greatest_focus" class="form-control">
            </div>

            <div class="q-block">
                <label class="q-label">3.3 What information do parents most commonly ask for before admission?</label>
                <textarea name="sec_3_3_parent_questions" class="form-control"></textarea>
            </div>

            <div class="q-block">
                <label class="q-label">3.4 What are the most common questions received through phone calls, WhatsApp, Facebook, or in person?</label>
                <textarea name="sec_3_4_channel_questions" class="form-control"></textarea>
            </div>
        </div>

        <!-- ── 4. Admissions ── -->
        <div class="form-section">
            <div class="form-section-title">4. Admissions</div>

            <div class="q-block">
                <label class="q-label">4.1 Please describe your current admission process from initial enquiry to final admission.</label>
                <textarea name="sec_4_1_process" class="form-control"></textarea>
            </div>

            <div class="q-block">
                <label class="q-label">4.2 Which classes/programs are currently accepting admissions?</label>
                <input type="text" name="sec_4_2_current_admissions" class="form-control">
            </div>

            <div class="q-block">
                <label class="q-label">4.3 Do different campuses have different admission procedures?</label>
                <div class="d-flex gap-4 mb-2">
                    <label><input type="radio" name="sec_4_3_campus_diff" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_4_3_campus_diff" value="No"> No</label>
                </div>
                <input type="text" name="sec_4_3_diff_notes" class="form-control" placeholder="Details if yes...">
            </div>

            <div class="q-block">
                <label class="q-label">4.4 Do campuses have different fee structures?</label>
                <div class="d-flex gap-4 mb-2">
                    <label><input type="radio" name="sec_4_4_fee_diff" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_4_4_fee_diff" value="No"> No</label>
                </div>
                <input type="text" name="sec_4_4_fee_notes" class="form-control" placeholder="Details if yes...">
            </div>

            <div class="q-block">
                <label class="q-label">4.5 Would you like parents to be able to submit admission applications completely online?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_4_5_online_submission" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_4_5_online_submission" value="No"> No</label>
                    <label><input type="radio" name="sec_4_5_online_submission" value="Partially"> Partially</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">4.6 What information should be collected in an online admission application?</label>
                <textarea name="sec_4_6_fields_needed" class="form-control"></textarea>
            </div>

            <div class="q-block">
                <label class="q-label">4.7 Should applicants be able to upload documents?</label>
                <div class="d-flex gap-4 mb-2">
                    <label><input type="radio" name="sec_4_7_doc_upload" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_4_7_doc_upload" value="No"> No</label>
                </div>
                <input type="text" name="sec_4_7_which_docs" class="form-control" placeholder="If yes, which documents?">
            </div>

            <div class="q-block">
                <label class="q-label">4.8 Should applicants receive automatic confirmation after submitting an application?</label>
                <div class="choice-grid">
                    <?php foreach (['Email', 'SMS', 'WhatsApp', 'All'] as $c): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_4_8_confirmations[]" value="<?= $c ?>"> <?= $c ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">4.9 Should the admissions team have a dashboard to view and manage applications?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_4_9_dashboard" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_4_9_dashboard" value="No"> No</label>
                    <label><input type="radio" name="sec_4_9_dashboard" value="Not sure"> Not sure</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">4.10 Would you like applicants to be able to check their application status online?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_4_10_check_status" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_4_10_check_status" value="No"> No</label>
                    <label><input type="radio" name="sec_4_10_check_status" value="Future phase"> Future phase</label>
                </div>
            </div>
        </div>

        <!-- ── 5. Campus Information ── -->
        <div class="form-section">
            <div class="form-section-title">5. Campus Information</div>

            <div class="q-block">
                <label class="q-label">5.1 Please provide the complete list of current campuses (Campus name, City, Area, Address, Contact, Email, Programs, Principal name, Facilities, Hours, Google Maps):</label>
                <textarea name="sec_5_1_campuses_list" class="form-control" style="min-height:120px;"></textarea>
            </div>

            <div class="q-block">
                <label class="q-label">5.2 Should visitors be able to search/filter campuses by city or area?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_5_2_filter_campuses" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_5_2_filter_campuses" value="No"> No</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">5.3 Should every campus have its own dedicated webpage?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_5_3_dedicated_page" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_5_3_dedicated_page" value="No"> No</label>
                    <label><input type="radio" name="sec_5_3_dedicated_page" value="Not sure"> Not sure</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">5.4 Should visitors be able to contact/apply specifically for a selected campus?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_5_4_campus_apply" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_5_4_campus_apply" value="No"> No</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">5.5 Would you like a "Find Your Nearest Campus" feature?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_5_5_nearest_campus" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_5_5_nearest_campus" value="No"> No</label>
                </div>
            </div>
        </div>

        <!-- ── 6. Academic Structure ── -->
        <div class="form-section">
            <div class="form-section-title">6. Academic Structure</div>

            <div class="q-block">
                <label class="q-label">6.1 What academic levels should be presented on the website?</label>
                <div class="choice-grid">
                    <?php foreach (['Early Years / Montessori', 'Primary', 'Middle', 'Matric', 'Intermediate'] as $lvl): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_6_1_levels[]" value="<?= $lvl ?>"> <?= $lvl ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-2">
                    <input type="text" name="sec_6_1_other" class="form-control" placeholder="Other level...">
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">6.2 Please provide the complete list of programs/classes currently offered.</label>
                <textarea name="sec_6_2_programs_list" class="form-control"></textarea>
            </div>

            <div class="q-block">
                <label class="q-label">6.3 Are programs different between campuses?</label>
                <textarea name="sec_6_3_campus_programs_diff" class="form-control"></textarea>
            </div>

            <div class="q-block">
                <label class="q-label">6.4 Should each academic program have a dedicated page?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_6_4_program_pages" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_6_4_program_pages" value="No"> No</label>
                    <label><input type="radio" name="sec_6_4_program_pages" value="Not sure"> Not sure</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">6.5 What curriculum/education systems do you follow?</label>
                <input type="text" name="sec_6_5_curriculum" class="form-control">
            </div>

            <div class="q-block">
                <label class="q-label">6.6 What are the major academic strengths of your institution?</label>
                <textarea name="sec_6_6_strengths" class="form-control"></textarea>
            </div>
        </div>

        <!-- ── 7. Results & Achievements ── -->
        <div class="form-section">
            <div class="form-section-title">7. Results & Achievements</div>

            <div class="q-block">
                <label class="q-label">7.1 Do you want a dedicated "Results & Achievements" section?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_7_1_dedicated_section" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_7_1_dedicated_section" value="No"> No</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">7.2 Please provide available information about:</label>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="small text-muted">Board results & Position holders:</label>
                        <textarea name="sec_7_2_board_positions" class="form-control"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted">Scholarships & University admissions:</label>
                        <textarea name="sec_7_2_scholarships" class="form-control"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted">Competitions & Science/Tech achievements:</label>
                        <textarea name="sec_7_2_competitions" class="form-control"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted">Sports achievements & Other awards:</label>
                        <textarea name="sec_7_2_sports" class="form-control"></textarea>
                    </div>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">7.3 Do you have historical result data that could be published on the website?</label>
                <input type="text" name="sec_7_3_historical_data" class="form-control">
            </div>

            <div class="q-block">
                <label class="q-label">7.4 Would you like achievements to be organized by year/campus/class?</label>
                <input type="text" name="sec_7_4_org_preferences" class="form-control">
            </div>
        </div>

        <!-- ── 8. Campus Life & Facilities ── -->
        <div class="form-section">
            <div class="form-section-title">8. Campus Life & Facilities</div>

            <div class="q-block">
                <label class="q-label">8.1 Which facilities should be highlighted?</label>
                <div class="choice-grid">
                    <?php
                    $facs = [
                        'Science laboratories', 'Computer laboratories', 'Libraries',
                        'Sports facilities', 'Cafeterias', 'Transport', 'Security',
                        'Classrooms', 'Smart classrooms', 'Playgrounds'
                    ];
                    foreach ($facs as $f): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_8_1_facilities[]" value="<?= $f ?>"> <?= $f ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">8.2 Do you have professional photographs/videos of your campuses and facilities?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_8_2_photos" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_8_2_photos" value="No"> No</label>
                    <label><input type="radio" name="sec_8_2_photos" value="Some"> Some</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">8.3 Would you like photography/video production to be included in the project?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_8_3_media_production" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_8_3_media_production" value="No"> No</label>
                    <label><input type="radio" name="sec_8_3_media_production" value="Maybe"> Maybe</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">8.4 Which student activities should be highlighted?</label>
                <div class="choice-grid">
                    <?php
                    $acts = ['Sports', 'Events', 'Competitions', 'Science fairs', 'Cultural activities', 'Trips', 'Clubs', 'Student societies'];
                    foreach ($acts as $ac): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_8_4_activities[]" value="<?= $ac ?>"> <?= $ac ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ── 9. Content & Branding ── -->
        <div class="form-section">
            <div class="form-section-title">9. Content & Branding</div>

            <div class="q-block">
                <label class="q-label">9.1 Do you want to retain the current logo and branding?</label>
                <div class="d-flex flex-wrap gap-3">
                    <label><input type="radio" name="sec_9_1_logo_branding" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_9_1_logo_branding" value="No"> No</label>
                    <label><input type="radio" name="sec_9_1_logo_branding" value="Minor refinement"> Minor refinement</label>
                    <label><input type="radio" name="sec_9_1_logo_branding" value="Complete branding review"> Complete branding review</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">9.2 Are there official brand guidelines?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_9_2_guidelines" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_9_2_guidelines" value="No"> No</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">9.3 Should the new website maintain the current color scheme?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_9_3_colors" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_9_3_colors" value="No"> No</label>
                    <label><input type="radio" name="sec_9_3_colors" value="Open to recommendations"> Open to recommendations</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">9.4 What visual style do you prefer?</label>
                <div class="choice-grid">
                    <?php foreach (['Traditional / Educational', 'Modern / Premium', 'Corporate', 'Minimal', 'Bold / Contemporary'] as $vs): ?>
                    <label class="choice-label">
                        <input type="radio" name="sec_9_4_visual_style" value="<?= $vs ?>"> <?= $vs ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">9.5 Are there any school websites whose design or functionality you particularly like?</label>
                <textarea name="sec_9_5_favorite_sites" class="form-control"></textarea>
            </div>

            <div class="q-block">
                <label class="q-label">9.6 Are there any website designs/styles you specifically do NOT want?</label>
                <textarea name="sec_9_6_unwanted_styles" class="form-control"></textarea>
            </div>
        </div>

        <!-- ── 10. News, Events & Announcements ── -->
        <div class="form-section">
            <div class="form-section-title">10. News, Events & Announcements</div>

            <div class="q-block">
                <label class="q-label">10.1 Who currently manages website news and announcements?</label>
                <input type="text" name="sec_10_1_news_manager" class="form-control">
            </div>

            <div class="q-block">
                <label class="q-label">10.2 Would you like authorized staff to publish news without contacting the developer?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_10_2_self_publish" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_10_2_self_publish" value="No"> No</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">10.3 Should the website include:</label>
                <div class="choice-grid">
                    <?php foreach (['News', 'Events calendar', 'Announcements', 'Circulars', 'Exam schedules', 'Results', 'Notices', 'Downloads'] as $mi): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_10_3_modules[]" value="<?= $mi ?>"> <?= $mi ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">10.4 Should old news/events automatically move into an archive?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_10_4_auto_archive" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_10_4_auto_archive" value="No"> No</label>
                </div>
            </div>
        </div>

        <!-- ── 11. Downloads & Documents ── -->
        <div class="form-section">
            <div class="form-section-title">11. Downloads & Documents</div>

            <div class="q-block">
                <label class="q-label">11.1 What documents should parents be able to download?</label>
                <div class="choice-grid">
                    <?php foreach (['Prospectus', 'Admission forms', 'Fee structures', 'Academic calendars', 'Timetables', 'Examination schedules', 'Circulars', 'Policies'] as $doc): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_11_1_downloads[]" value="<?= $doc ?>"> <?= $doc ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">11.2 Should staff be able to upload/update these documents through an admin panel?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_11_2_admin_upload" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_11_2_admin_upload" value="No"> No</label>
                </div>
            </div>
        </div>

        <!-- ── 12. Parent & Student Services ── -->
        <div class="form-section">
            <div class="form-section-title">12. Parent & Student Services</div>

            <div class="q-block">
                <label class="q-label">12.1 Does your institution currently have a student/parent portal?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_12_1_existing_portal" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_12_1_existing_portal" value="No"> No</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">12.2 If yes, should the new website link to or integrate with it?</label>
                <input type="text" name="sec_12_2_portal_link_or_api" class="form-control">
            </div>

            <div class="q-block">
                <label class="q-label">12.3 Would you like the website to eventually include:</label>
                <div class="choice-grid">
                    <?php foreach (['Parent portal', 'Student portal', 'Attendance', 'Results', 'Fee payments', 'Homework', 'Timetable', 'Notifications', 'Online complaints/requests'] as $pf): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_12_3_portal_features[]" value="<?= $pf ?>"> <?= $pf ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">12.4 Are there any existing systems/software that the new website needs to connect with?</label>
                <textarea name="sec_12_4_software_integration" class="form-control"></textarea>
            </div>
        </div>

        <!-- ── 13. Communication & Lead Management ── -->
        <div class="form-section">
            <div class="form-section-title">13. Communication & Lead Management</div>

            <div class="q-block">
                <label class="q-label">13.1 Which communication channels do you currently use?</label>
                <div class="choice-grid">
                    <?php foreach (['Phone', 'Email', 'WhatsApp', 'Facebook', 'Instagram'] as $ch): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_13_1_channels[]" value="<?= $ch ?>"> <?= $ch ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">13.2 Would you like website enquiries to automatically reach the admissions team?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_13_2_auto_notify" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_13_2_auto_notify" value="No"> No</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">13.3 Where should enquiries be received?</label>
                <div class="choice-grid">
                    <?php foreach (['Email', 'WhatsApp', 'CRM', 'Admin dashboard'] as $ed): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_13_3_receive_dest[]" value="<?= $ed ?>"> <?= $ed ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">13.4 Would you like a centralized system for managing admission enquiries and applications?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_13_4_centralized_crm" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_13_4_centralized_crm" value="No"> No</label>
                    <label><input type="radio" name="sec_13_4_centralized_crm" value="Maybe"> Maybe</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">13.5 Should different campuses receive enquiries separately?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_13_5_separate_campus_leads" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_13_5_separate_campus_leads" value="No"> No</label>
                </div>
            </div>
        </div>

        <!-- ── 14. Payments ── -->
        <div class="form-section">
            <div class="form-section-title">14. Payments</div>

            <div class="q-block">
                <label class="q-label">14.1 Should the website support online payments?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_14_1_support_payments" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_14_1_support_payments" value="No"> No</label>
                    <label><input type="radio" name="sec_14_1_support_payments" value="Future phase"> Future phase</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">14.2 If yes, what payments should be supported?</label>
                <div class="choice-grid">
                    <?php foreach (['Admission fees', 'Application fees', 'Tuition fees', 'Other charges'] as $pt): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_14_2_payment_types[]" value="<?= $pt ?>"> <?= $pt ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">14.3 Which payment methods are currently used by your institution?</label>
                <div class="choice-grid">
                    <?php foreach (['Bank transfer', 'Easypaisa', 'JazzCash', 'Debit/Credit Card', 'Online banking'] as $pm): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_14_3_methods[]" value="<?= $pm ?>"> <?= $pm ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">14.4 Do you already have payment gateway accounts/API access?</label>
                <div class="d-flex flex-wrap gap-3">
                    <label><input type="radio" name="sec_14_4_gateway_status" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_14_4_gateway_status" value="In progress"> In progress</label>
                    <label><input type="radio" name="sec_14_4_gateway_status" value="No"> No</label>
                    <label><input type="radio" name="sec_14_4_gateway_status" value="Not sure"> Not sure</label>
                </div>
            </div>
        </div>

        <!-- ── 15. SEO & Google Presence ── -->
        <div class="form-section">
            <div class="form-section-title">15. SEO & Google Presence</div>

            <div class="q-block">
                <label class="q-label">15.1 Is increasing visibility on Google a priority?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_15_1_seo_priority" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_15_1_seo_priority" value="No"> No</label>
                    <label><input type="radio" name="sec_15_1_seo_priority" value="Not sure"> Not sure</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">15.2 Which locations should your institution primarily target?</label>
                <input type="text" name="sec_15_2_target_locations" class="form-control">
            </div>

            <div class="q-block">
                <label class="q-label">15.3 Are there specific keywords/search terms you want to rank for?</label>
                <input type="text" name="sec_15_3_keywords" class="form-control">
            </div>

            <div class="q-block">
                <label class="q-label">15.4 Do you currently have access to:</label>
                <div class="choice-grid">
                    <?php foreach (['Google Search Console', 'Google Analytics', 'Google Business Profiles', 'Google Tag Manager'] as $gt): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_15_4_google_tools[]" value="<?= $gt ?>"> <?= $gt ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">15.5 Should SEO optimization be included in the project?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_15_5_seo_service" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_15_5_seo_service" value="No"> No</label>
                </div>
            </div>
        </div>

        <!-- ── 16. Social Media Integration ── -->
        <div class="form-section">
            <div class="form-section-title">16. Social Media Integration</div>

            <div class="q-block">
                <label class="q-label">16.1 Which social media platforms do you actively use?</label>
                <div class="choice-grid">
                    <?php foreach (['Facebook', 'Instagram', 'YouTube', 'LinkedIn', 'TikTok', 'X/Twitter'] as $smp): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_16_1_platforms[]" value="<?= $smp ?>"> <?= $smp ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">16.2 Should social media content be displayed on the website?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_16_2_feed_display" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_16_2_feed_display" value="No"> No</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">16.3 Should visitors be able to share news/events/achievements directly to social media?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_16_3_share_buttons" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_16_3_share_buttons" value="No"> No</label>
                </div>
            </div>
        </div>

        <!-- ── 17. Technical Requirements ── -->
        <div class="form-section">
            <div class="form-section-title">17. Technical Requirements</div>

            <div class="q-block">
                <label class="q-label">17.1 Do you want the website to be:</label>
                <div class="d-flex flex-column gap-2">
                    <label><input type="radio" name="sec_17_1_platform_choice" value="Completely redesigned but using existing platform"> Completely redesigned but using existing platform</label>
                    <label><input type="radio" name="sec_17_1_platform_choice" value="Rebuilt on a new platform"> Rebuilt on a new platform</label>
                    <label><input type="radio" name="sec_17_1_platform_choice" value="Open to recommendation"> Open to recommendation</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">17.2 Is the current website hosting to be retained?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_17_2_hosting_retained" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_17_2_hosting_retained" value="No"> No</label>
                    <label><input type="radio" name="sec_17_2_hosting_retained" value="Open to recommendation"> Open to recommendation</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">17.3 Do you have access to the current:</label>
                <div class="choice-grid">
                    <?php foreach (['Hosting', 'Domain', 'Website source files', 'Database', 'Email accounts', 'DNS', 'Google Search Console', 'Google Analytics'] as $acc): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_17_3_access_items[]" value="<?= $acc ?>"> <?= $acc ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">17.4 Are there any existing systems that must remain operational during the redesign?</label>
                <input type="text" name="sec_17_4_must_remain_operational" class="form-control">
            </div>

            <div class="q-block">
                <label class="q-label">17.5 Should the new website include an admin/CMS system?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_17_5_admin_cms" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_17_5_admin_cms" value="No"> No</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">17.6 Who should have access to the administration panel?</label>
                <div class="choice-grid">
                    <?php foreach (['Main administrator', 'Admissions team', 'Campus administrators', 'Content team'] as $adm): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_17_6_admin_roles[]" value="<?= $adm ?>"> <?= $adm ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ── 18. Languages ── -->
        <div class="form-section">
            <div class="form-section-title">18. Languages</div>

            <div class="q-block">
                <label class="q-label">18.1 Which languages should the website support?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_18_1_languages" value="English"> English</label>
                    <label><input type="radio" name="sec_18_1_languages" value="Urdu"> Urdu</label>
                    <label><input type="radio" name="sec_18_1_languages" value="Both"> Both</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">18.2 Should the entire website be bilingual, or only selected sections?</label>
                <input type="text" name="sec_18_2_scope" class="form-control">
            </div>

            <div class="q-block">
                <label class="q-label">18.3 Should Urdu/English content be managed independently through the admin panel?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_18_3_independent_cms" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_18_3_independent_cms" value="No"> No</label>
                    <label><input type="radio" name="sec_18_3_independent_cms" value="N/A"> N/A</label>
                </div>
            </div>
        </div>

        <!-- ── 19. Mobile & Accessibility ── -->
        <div class="form-section">
            <div class="form-section-title">19. Mobile & Accessibility</div>

            <div class="q-block">
                <label class="q-label">19.1 What percentage of your visitors do you estimate come from mobile devices?</label>
                <input type="text" name="sec_19_1_mobile_pct" class="form-control">
            </div>

            <div class="q-block">
                <label class="q-label">19.2 Should the website be optimized specifically for mobile-first usage?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_19_2_mobile_priority" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_19_2_mobile_priority" value="No"> No</label>
                    <label><input type="radio" name="sec_19_2_mobile_priority" value="Critical"> Critical</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">19.3 Are there any accessibility requirements that should be considered?</label>
                <input type="text" name="sec_19_3_accessibility" class="form-control">
            </div>
        </div>

        <!-- ── 20. Security & Privacy ── -->
        <div class="form-section">
            <div class="form-section-title">20. Security & Privacy</div>

            <div class="q-block">
                <label class="q-label">20.1 Will the website collect sensitive student/parent information?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_20_1_sensitive_info" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_20_1_sensitive_info" value="No"> No</label>
                    <label><input type="radio" name="sec_20_1_sensitive_info" value="Not sure"> Not sure</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">20.2 Are there any specific privacy or data-retention requirements?</label>
                <input type="text" name="sec_20_2_retention" class="form-control">
            </div>

            <div class="q-block">
                <label class="q-label">20.3 Should the system include:</label>
                <div class="choice-grid">
                    <?php foreach (['Secure admin login', 'Role-based access', 'Application data protection', 'Automated backups', 'Activity logs'] as $sec): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_20_3_security_controls[]" value="<?= $sec ?>"> <?= $sec ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ── 21. Project Scope & Expectations ── -->
        <div class="form-section">
            <div class="form-section-title">21. Project Scope & Expectations</div>

            <div class="q-block">
                <label class="q-label">21.1 Are you looking for:</label>
                <div class="d-flex flex-column gap-2">
                    <?php
                    $scopes = [
                        'Website redesign only', 'Complete website redevelopment', 'Website + CMS',
                        'Website + admission system', 'Website + parent/student portal',
                        'Complete digital platform', 'Open to recommendation'
                    ];
                    foreach ($scopes as $sc): ?>
                    <label><input type="radio" name="sec_21_1_scope" value="<?= $sc ?>"> <?= $sc ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">21.2 Do you have a preferred launch date?</label>
                <input type="text" name="sec_21_2_launch_date" class="form-control">
            </div>

            <div class="q-block">
                <label class="q-label">21.3 Is there a specific admission season/deadline the new website must be ready for?</label>
                <input type="text" name="sec_21_3_admission_season" class="form-control">
            </div>

            <div class="q-block">
                <label class="q-label">21.4 Do you have an estimated budget range for the project?</label>
                <input type="text" name="sec_21_4_budget" class="form-control">
            </div>

            <div class="q-block">
                <label class="q-label">21.5 Would you prefer:</label>
                <div class="d-flex flex-column gap-2">
                    <label><input type="radio" name="sec_21_5_phasing" value="One complete project"> One complete project</label>
                    <label><input type="radio" name="sec_21_5_phasing" value="Phased development"> Phased development</label>
                    <label><input type="radio" name="sec_21_5_phasing" value="Initial website followed by additional features"> Initial website followed by additional features</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">21.6 Who will be the primary decision-maker/approver for the project?</label>
                <input type="text" name="sec_21_6_decision_maker" class="form-control">
            </div>
        </div>

        <!-- ── 22. Maintenance & Future Development ── -->
        <div class="form-section">
            <div class="form-section-title">22. Maintenance & Future Development</div>

            <div class="q-block">
                <label class="q-label">22.1 Who will maintain website content after launch?</label>
                <input type="text" name="sec_22_1_maintenance_by" class="form-control">
            </div>

            <div class="q-block">
                <label class="q-label">22.2 Would you like ongoing technical maintenance?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_22_2_tech_maintenance" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_22_2_tech_maintenance" value="No"> No</label>
                    <label><input type="radio" name="sec_22_2_tech_maintenance" value="Maybe"> Maybe</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">22.3 Would you like ongoing SEO/content management?</label>
                <div class="d-flex gap-4">
                    <label><input type="radio" name="sec_22_3_seo_retainer" value="Yes"> Yes</label>
                    <label><input type="radio" name="sec_22_3_seo_retainer" value="No"> No</label>
                    <label><input type="radio" name="sec_22_3_seo_retainer" value="Maybe"> Maybe</label>
                </div>
            </div>

            <div class="q-block">
                <label class="q-label">22.4 Are there any future systems/features you expect to add? Please describe any long-term plans.</label>
                <textarea name="sec_22_4_future_plans" class="form-control"></textarea>
            </div>
        </div>

        <!-- ── 23. Current Website Feedback ── -->
        <div class="form-section">
            <div class="form-section-title">23. Current Website Feedback</div>

            <div class="q-block">
                <label class="q-label">23.1 What do you currently like about the existing website?</label>
                <textarea name="sec_23_1_current_likes" class="form-control"></textarea>
            </div>

            <div class="q-block">
                <label class="q-label">23.2 What do you dislike about the existing website?</label>
                <textarea name="sec_23_2_current_dislikes" class="form-control"></textarea>
            </div>

            <div class="q-block">
                <label class="q-label">23.3 What complaints or feedback have you received from parents/students regarding the website?</label>
                <textarea name="sec_23_3_user_complaints" class="form-control"></textarea>
            </div>

            <div class="q-block">
                <label class="q-label">23.4 Which parts of the current website are most important to retain?</label>
                <textarea name="sec_23_4_retain_parts" class="form-control"></textarea>
            </div>

            <div class="q-block">
                <label class="q-label">23.5 Which parts should definitely be removed or replaced?</label>
                <textarea name="sec_23_5_remove_parts" class="form-control"></textarea>
            </div>
        </div>

        <!-- ── 24. Final Questions ── -->
        <div class="form-section">
            <div class="form-section-title">24. Final Questions</div>

            <div class="q-block">
                <label class="q-label">24.1 If you could change only three things about the current website, what would they be?</label>
                <textarea name="sec_24_1_three_changes" class="form-control"></textarea>
            </div>

            <div class="q-block">
                <label class="q-label">24.2 What is the single most important outcome you expect from the new website?</label>
                <textarea name="sec_24_2_single_outcome" class="form-control"></textarea>
            </div>

            <div class="q-block">
                <label class="q-label">24.3 Is there anything we haven't asked that you believe is important for this project?</label>
                <textarea name="sec_24_3_additional_notes" class="form-control"></textarea>
            </div>

            <div class="q-block">
                <label class="q-label">24.4 Please provide any existing materials that can help us understand your institution better:</label>
                <div class="choice-grid">
                    <?php
                    $mats = [
                        'Logo/brand guidelines', 'Campus photographs', 'Videos', 'Prospectus',
                        'Fee structures', 'Admission forms', 'Academic information', 'Results',
                        'Student achievements', 'Organizational information', 'Existing website analytics', 'Other documents'
                    ];
                    foreach ($mats as $m): ?>
                    <label class="choice-label">
                        <input type="checkbox" name="sec_24_4_materials[]" value="<?= $m ?>"> <?= $m ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>


        <!-- Submit Button -->
        <div class="text-center mt-4">
            <button type="submit" id="submitBtn" class="btn-submit-main">Submit Questionnaire</button>
        </div>

    </form>
</div>

<!-- Simple Autosave Script -->
<script>
const DRAFT_KEY = 'simple_discovery_draft';
const statusEl = document.getElementById('draftStatus');

// Autosave function
let saveTimer = null;
function saveDraft() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
        const form = document.getElementById('discoveryForm');
        const formData = new FormData(form);
        const dataObj = {};

        for (let [k, v] of formData.entries()) {
            if (k === 'form_submit') continue;
            if (k.endsWith('[]')) {
                if (!dataObj[k]) dataObj[k] = [];
                dataObj[k].push(v);
            } else {
                dataObj[k] = v;
            }
        }

        localStorage.setItem(DRAFT_KEY, JSON.stringify(dataObj));
        if (statusEl) {
            statusEl.innerHTML = '<span>●</span> Saved ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
    }, 500);
}

// Restore function
function restoreDraft() {
    try {
        const raw = localStorage.getItem(DRAFT_KEY);
        if (!raw) return;
        const data = JSON.parse(raw);

        for (let k in data) {
            const val = data[k];
            if (Array.isArray(val)) {
                val.forEach(item => {
                    const el = document.querySelector(`[name="${k}"][value="${item}"]`);
                    if (el) el.checked = true;
                });
            } else {
                const el = document.querySelector(`[name="${k}"]`);
                if (el) {
                    if (el.type === 'radio') {
                        const r = document.querySelector(`[name="${k}"][value="${val}"]`);
                        if (r) r.checked = true;
                    } else if (el.type === 'checkbox') {
                        el.checked = (val === el.value || val === '1' || val === true);
                    } else {
                        el.value = val;
                    }
                }
            }
        }
        if (statusEl) {
            statusEl.innerHTML = '<span>●</span> Draft restored';
        }
    } catch(e) {
        console.error(e);
    }
}

// Reset draft
function clearDraft() {
    if (confirm('Are you sure you want to reset all answers and clear the draft?')) {
        localStorage.removeItem(DRAFT_KEY);
        document.getElementById('discoveryForm').reset();
        if (statusEl) {
            statusEl.innerHTML = '<span>●</span> Draft cleared';
        }
    }
}

// Attach listeners
document.addEventListener('DOMContentLoaded', () => {
    restoreDraft();

    document.querySelectorAll('#discoveryForm input, #discoveryForm textarea, #discoveryForm select').forEach(input => {
        input.addEventListener('input', saveDraft);
        input.addEventListener('change', saveDraft);
    });
});
</script>

<?php endif; ?>

<?php include 'templates/footer.php'; ?>
