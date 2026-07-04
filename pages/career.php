<?php
require_once __DIR__ . '/../core/config.php';
include __DIR__ . '/../templates/header.php';

// ── Handle application submission ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $name       = trim(htmlspecialchars($_POST['name']       ?? ''));
    $email      = trim(htmlspecialchars($_POST['email']      ?? ''));
    $phone      = trim(htmlspecialchars($_POST['phone']      ?? ''));
    $position   = trim(htmlspecialchars($_POST['position']   ?? ''));
    $skills     = trim(htmlspecialchars($_POST['skills']     ?? ''));
    $portfolio  = trim(htmlspecialchars($_POST['portfolio']  ?? ''));
    $experience = trim(htmlspecialchars($_POST['experience'] ?? ''));
    $bio        = trim(htmlspecialchars($_POST['bio']        ?? ''));

    // CV upload
    $cvPath = null;
    if (!empty($_FILES['cv']['name']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/cvs/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
        $ext     = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf','doc','docx'];
        if (in_array($ext, $allowed) && $_FILES['cv']['size'] <= 5 * 1024 * 1024) {
            $newName = uniqid('cv_', true) . '.' . $ext;
            $target  = $uploadDir . $newName;
            if (move_uploaded_file($_FILES['cv']['tmp_name'], $target)) $cvPath = $target;
        }
    }

    $stmt = $conn->prepare("INSERT INTO career_applications (name, email, phone, position, skills, portfolio, experience, cv, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssss", $name, $email, $phone, $position, $skills, $portfolio, $experience, $cvPath, $bio);
    $submitted = $stmt->execute();
}

// ── Fetch open positions ──────────────────────────────────────────────────────
$keyword = trim($_GET['keyword'] ?? '');
if ($keyword) {
    $like = "%$keyword%";
    $stmt = $conn->prepare("SELECT * FROM career_positions WHERE status='open' AND (position LIKE ? OR department LIKE ? OR location LIKE ?) ORDER BY created_at DESC");
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $positions = $stmt->get_result();
} else {
    $positions = $conn->query("SELECT * FROM career_positions WHERE status='open' ORDER BY created_at DESC");
}

$total_open_query = $conn->query("SELECT COUNT(*) AS c FROM career_positions WHERE status='open'");
$total_open = $total_open_query ? ($total_open_query->fetch_assoc()['c'] ?? 0) : 0;
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --primary: #024442;
        --primary-dark: #012d2b;
        --accent: #b8f35a;
        --bg-light: #f7f9f5;
        --text-gray: #555555;
    }

    body { font-family: 'Poppins', sans-serif; background-color: #fcfcfc; }

    /* ── Hero Section ── */
    .career-hero {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        padding: 120px 0 80px;
        color: #fff;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .career-hero::after {
        content: ''; position: absolute; width: 300px; height: 300px;
        background: radial-gradient(circle, rgba(184,243,90,0.1) 0%, transparent 70%);
        top: -50px; right: -50px; border-radius: 50%;
    }
    .career-hero h1 { font-size: clamp(2.5rem, 4vw, 3.5rem); font-weight: 800; margin-bottom: 16px; }
    .career-hero p { font-size: 1.1rem; max-width: 600px; margin: 0 auto; color: rgba(255,255,255,0.8); line-height: 1.6; }

    /* ── Search Bar ── */
    .search-wrapper {
        background: #fff; padding: 12px; border-radius: 50px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05); display: flex; gap: 10px;
        max-width: 500px; width: 100%; border: 1px solid #eee;
    }
    .search-wrapper input {
        border: none; background: transparent; padding: 10px 20px;
        width: 100%; outline: none; font-family: inherit; font-size: 0.95rem;
    }
    .search-wrapper button {
        background: var(--primary); color: #fff; border: none; padding: 10px 25px;
        border-radius: 50px; font-weight: 600; cursor: pointer; transition: all 0.2s;
    }
    .search-wrapper button:hover { background: var(--accent); color: var(--primary); }

    /* ── Job Cards ── */
    .job-card {
        background: #fff; border: 1px solid #f0f0f0; border-radius: 16px;
        padding: 24px 30px; margin-bottom: 16px; display: flex;
        justify-content: space-between; align-items: center;
        transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.02);
    }
    .job-card:hover {
        border-color: var(--primary); transform: translateY(-3px);
        box-shadow: 0 15px 35px rgba(2,68,66,0.08);
    }
    .job-title-group h3 { font-size: 1.25rem; font-weight: 700; color: var(--primary); margin: 0 0 6px 0; }
    .job-meta { display: flex; gap: 16px; flex-wrap: wrap; align-items: center; }
    .job-meta span { font-size: 0.85rem; color: var(--text-gray); display: flex; align-items: center; gap: 6px; }
    
    .job-badge {
        padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700;
    }
    .badge-full-time { background: #dcfce7; color: #16a34a; }
    .badge-part-time { background: #fef9c3; color: #a16207; }
    .badge-internship { background: #f3e8ff; color: #7e22ce; }
    .badge-remote { background: #e0f2fe; color: #0284c7; }
    .badge-freelance { background: #ffedd5; color: #c2410c; }
    .badge-default { background: #f3f4f6; color: #4b5563; }

    .job-actions { display: flex; gap: 12px; align-items: center; }
    .btn-outline {
        padding: 8px 20px; border: 1.5px solid #e5e7eb; border-radius: 50px;
        background: #fff; color: var(--text-gray); font-weight: 600; font-size: 0.85rem;
        cursor: pointer; transition: all 0.2s;
    }
    .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
    .btn-apply {
        padding: 9px 24px; border-radius: 50px; background: var(--primary);
        color: #fff; font-weight: 600; font-size: 0.85rem; text-decoration: none;
        transition: all 0.2s; display: inline-block; border: none; cursor: pointer;
    }
    .btn-apply:hover { background: var(--accent); color: var(--primary); }

    /* ── Why Work With Us ── */
    .wcu-section { padding: 100px 0; background: var(--bg-light); border-top: 1px solid #eee; border-bottom: 1px solid #eee; }
    .wcu-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; margin-top: 50px; }
    .wcu-card {
        background: #fff; padding: 40px 30px; border-radius: 20px;
        text-align: center; border: 1px solid #f0f0f0; transition: all 0.3s ease;
    }
    .wcu-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(0,0,0,0.05); }
    .wcu-card i { font-size: 2.5rem; color: var(--primary); margin-bottom: 20px; }
    .wcu-card h3 { font-size: 1.2rem; font-weight: 700; color: var(--primary); margin-bottom: 12px; }
    .wcu-card p { font-size: 0.9rem; color: var(--text-gray); line-height: 1.6; margin: 0; }

    /* ── Application Form ── */
    .career-form-section { padding: 100px 0; }
    .form-wrapper {
        background: #fff; border-radius: 24px; padding: 50px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.06); border: 1px solid #f0f0f0;
        max-width: 900px; margin: 0 auto;
    }
    .form-label { font-size: 0.9rem; font-weight: 600; color: var(--primary); margin-bottom: 8px; }
    .form-control, .form-select {
        border: 1.5px solid #e5e7eb; border-radius: 12px; padding: 12px 16px;
        font-size: 0.95rem; background: #f9fafb; transition: all 0.2s;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary); box-shadow: 0 0 0 4px rgba(2,68,66,0.1); background: #fff;
    }

    /* ── Modal Styling ── */
    .job-modal-overlay {
        display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
        backdrop-filter: blur(5px); z-index: 1050; align-items: center; justify-content: center; padding: 20px;
    }
    .job-modal-content {
        background: #fff; border-radius: 24px; width: 100%; max-width: 650px;
        max-height: 85vh; overflow: hidden; display: flex; flex-direction: column;
        box-shadow: 0 24px 64px rgba(0,0,0,0.2); animation: slideUp 0.3s ease;
    }
    @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    
    .job-modal-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        padding: 30px; position: relative; color: #fff; flex-shrink: 0;
    }
    .job-modal-close {
        position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.1);
        border: none; width: 36px; height: 36px; border-radius: 50%; color: #fff;
        font-size: 1.2rem; cursor: pointer; transition: background 0.2s;
    }
    .job-modal-close:hover { background: rgba(255,255,255,0.2); }
    .job-modal-body { padding: 30px; overflow-y: auto; flex: 1; font-size: 0.95rem; color: var(--text-gray); line-height: 1.7; }
    .job-modal-footer {
        padding: 20px 30px; border-top: 1px solid #f0f0f0; background: #f9fafb;
        display: flex; gap: 12px; justify-content: flex-end; flex-shrink: 0;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .job-card { flex-direction: column; align-items: flex-start; gap: 20px; padding: 20px; }
        .job-actions { width: 100%; justify-content: stretch; }
        .job-actions button, .job-actions a { flex: 1; text-align: center; justify-content: center; }
        .form-wrapper { padding: 30px 20px; }
    }
</style>

<section class="career-hero">
    <div class="container">
        <h1>Join Our Team</h1>
        <p>We're looking for passionate, creative individuals to grow with us. Explore our open positions and find the role where you belong.</p>
    </div>
</section>

<section class="py-5" data-aos="fade-up" style="margin-top: -40px; position: relative; z-index: 10;">
    <div class="container">
        
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-5">
            <div>
                <h2 class="fw-700 mb-1" style="font-size:1.8rem;color:var(--primary);">Open Positions</h2>
                <span style="font-size:.9rem;color:var(--text-gray);"><?php echo $total_open; ?> position<?php echo $total_open != 1 ? 's' : ''; ?> available</span>
            </div>
            <form method="GET" action="" class="search-wrapper">
                <input type="text" name="keyword" placeholder="Search roles, departments..." value="<?php echo htmlspecialchars($keyword); ?>">
                <button type="submit">Search</button>
            </form>
        </div>

        <?php if ($positions && $positions->num_rows > 0): ?>
            <div class="job-list-container">
                <?php while ($row = $positions->fetch_assoc()): 
                    $typeClass = 'badge-default';
                    $jobTypeRaw = strtolower($row['job_type'] ?? '');
                    if (str_contains($jobTypeRaw, 'full')) $typeClass = 'badge-full-time';
                    elseif (str_contains($jobTypeRaw, 'part')) $typeClass = 'badge-part-time';
                    elseif (str_contains($jobTypeRaw, 'intern')) $typeClass = 'badge-internship';
                    elseif (str_contains($jobTypeRaw, 'remote')) $typeClass = 'badge-remote';
                    elseif (str_contains($jobTypeRaw, 'freelance')) $typeClass = 'badge-freelance';
                ?>
                <div class="job-card">
                    <div class="job-info">
                        <div class="job-title-group">
                            <h3><?php echo htmlspecialchars($row['position']); ?></h3>
                        </div>
                        <div class="job-meta">
                            <?php if (!empty($row['department'])): ?>
                                <span><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($row['department']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($row['location'])): ?>
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['location']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($row['salary_range'])): ?>
                                <span style="color: #16a34a; font-weight: 600;"><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars($row['salary_range']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($row['job_type'])): ?>
                                <span class="job-badge <?php echo $typeClass; ?>"><?php echo htmlspecialchars($row['job_type']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="job-actions">
                        <button class="btn-outline" onclick="openPositionModal(<?php echo $row['id']; ?>)">View Details</button>
                        <a href="#apply-form" class="btn-apply" onclick="document.querySelector('[name=position]').value='<?php echo addslashes($row['position']); ?>'">Apply Now</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center;padding:80px 24px;background:#fff;border-radius:24px;border:1px dashed #e5e7eb;">
                <div style="font-size:3rem;margin-bottom:16px;">📭</div>
                <div style="font-size:1.2rem;font-weight:700;color:var(--primary);margin-bottom:8px;">
                    <?php echo $keyword ? "No positions matching \"".htmlspecialchars($keyword)."\"" : "No open positions right now"; ?>
                </div>
                <div style="color:var(--text-gray);">Check back soon or submit a general application below.</div>
                <a class="main-btn mt-2" href="#apply-form">Submit Application</a>
            </div>
        <?php endif; ?>

    </div>
</section>

<section class="wcu-section">
    <div class="container">
        <div class="text-center">
            <span style="color:var(--primary);font-size:0.85rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;">Benefits</span>
            <h2 style="font-size:2.2rem;font-weight:800;color:var(--primary);margin-top:8px;">Why Work With Us</h2>
        </div>
        <div class="wcu-grid">
            <div class="wcu-card" data-aos="fade-up">
                <i class="fas fa-lightbulb"></i>
                <h3>Creative Freedom</h3>
                <p>We encourage ideas, experimentation, and original thinking. Your creativity is valued and trusted.</p>
            </div>
            <div class="wcu-card" data-aos="fade-up" data-aos-delay="100">
                <i class="fas fa-rocket"></i>
                <h3>Growth Opportunities</h3>
                <p>Learn, evolve, and build your career with real projects that push your skills forward.</p>
            </div>
            <div class="wcu-card" data-aos="fade-up" data-aos-delay="200">
                <i class="fas fa-users"></i>
                <h3>Supportive Team</h3>
                <p>Work alongside designers, developers, and strategists who respect collaboration and teamwork.</p>
            </div>
            <div class="wcu-card" data-aos="fade-up" data-aos-delay="300">
                <i class="fas fa-briefcase"></i>
                <h3>Meaningful Work</h3>
                <p>Contribute to real brands and businesses, and see the impact of your work in the real world.</p>
            </div>
        </div>
    </div>
</section>

<section class="career-form-section" id="apply-form">
    <div class="container">
        <div class="form-wrapper" data-aos="zoom-in">

            <?php if (!empty($submitted)): ?>
            <div style="text-align:center;padding:40px 0;">
                <div style="font-size:3rem;margin-bottom:16px;">🎉</div>
                <h3 style="color:var(--primary);font-weight:800;font-size:1.8rem;margin-bottom:12px;">Application Submitted!</h3>
                <p style="color:var(--text-gray);font-size:1rem;">Thank you for applying. We have received your details and our team will review your application shortly.</p>
                <a href="careers.php" class="btn-apply mt-3">Back to Careers</a>
            </div>
            <?php else: ?>

            <div class="text-center mb-5">
                <h2 style="font-size:2rem;font-weight:800;color:var(--primary);">Submit Your Application</h2>
                <p style="color:var(--text-gray);">Fill in your details below and attach your CV to apply.</p>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="submit_application" value="1">
                <div class="row g-4">

                    <div class="col-md-6">
                        <label class="form-label">Full Name <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="Name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email Address <span style="color:#ef4444;">*</span></label>
                        <input type="email" name="email" class="form-control" required placeholder="mail@example.com">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Phone Number <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="phone" class="form-control" required placeholder="+92 234 567 890">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Position Applying For <span style="color:#ef4444;">*</span></label>
                        <select name="position" class="form-select" required>
                            <option value="" disabled selected>Select Position</option>
                            <?php
                            $pos_opts = $conn->query("SELECT position FROM career_positions WHERE status='open' ORDER BY position");
                            if ($pos_opts && $pos_opts->num_rows > 0):
                                while ($p = $pos_opts->fetch_assoc()):
                            ?>
                            <option><?php echo htmlspecialchars($p['position']); ?></option>
                            <?php endwhile; else: ?>
                            <option>Graphic Designer</option>
                            <option>Video Editor</option>
                            <option>Web Developer</option>
                            <option>Content Writer</option>
                            <option>Social Media Manager</option>
                            <option>Internship</option>
                            <option>General Application</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Key Skills</label>
                        <input type="text" name="skills" class="form-control" placeholder="e.g. Photoshop, Illustrator, Figma, React">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Portfolio Link</label>
                        <input type="url" name="portfolio" class="form-control" placeholder="https://yourportfolio.com">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Experience Level</label>
                        <select name="experience" class="form-select">
                            <option value="" disabled selected>Select</option>
                            <option>Beginner (0-1 Years)</option>
                            <option>Intermediate (1-3 Years)</option>
                            <option>Expert (3+ Years)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Upload CV <span style="color:#ef4444;">*</span></label>
                        <input type="file" name="cv" class="form-control" accept=".pdf,.doc,.docx" required style="background:#fff;">
                        <div style="font-size:0.75rem;color:#888;margin-top:6px;">PDF, DOC or DOCX — max 5MB</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Short Bio / Cover Letter</label>
                        <textarea name="bio" class="form-control" rows="5" placeholder="Tell us about yourself, your passion, and why you'd be a great fit for the team."></textarea>
                    </div>

                    <div class="col-12 text-center mt-4">
                        <button class="btn-apply" type="submit" style="padding: 14px 40px; font-size: 1rem;">Submit Application</button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<div id="positionModal" class="job-modal-overlay" onclick="if(event.target===this)closePosModal()">
    <div class="job-modal-content">
        <div class="job-modal-header">
            <button onclick="closePosModal()" class="job-modal-close">×</button>
            <div style="font-size:0.75rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--accent);margin-bottom:8px;" id="pmDept"></div>
            <div style="font-size:1.8rem;font-weight:800;margin-bottom:12px;" id="pmTitle"></div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;" id="pmMeta"></div>
        </div>
        <div class="job-modal-body">
            <div id="pmDescription"></div>
            <div id="pmRequirements" style="margin-top: 24px;"></div>
        </div>
        <div class="job-modal-footer">
            <button onclick="closePosModal()" class="btn-outline">Close</button>
            <a id="pmApplyBtn" href="#apply-form" onclick="closePosModal()" class="btn-apply">Apply for this Role →</a>
        </div>
    </div>
</div>

<?php
// Encode position data for JS safely
$pos_data = [];
$all_pos = $conn->query("SELECT * FROM career_positions WHERE status='open'");
if ($all_pos) {
    while ($p = $all_pos->fetch_assoc()) {
        $pos_data[$p['id']] = $p;
    }
}
?>
<script>
const posData = <?php echo json_encode($pos_data); ?>;

function openPositionModal(id) {
    const p = posData[id]; 
    if (!p) return;
    
    document.getElementById('pmDept').textContent  = p.department || 'General';
    document.getElementById('pmTitle').textContent = p.position;

    // Meta chips
    const chips = [];
    if (p.location) chips.push(`<span style="background:rgba(255,255,255,0.15);padding:4px 12px;border-radius:20px;font-size:0.75rem;font-weight:600;"><i class="fas fa-map-marker-alt"></i> ${p.location}</span>`);
    if (p.job_type) chips.push(`<span style="background:var(--accent);color:var(--primary);padding:4px 12px;border-radius:20px;font-size:0.75rem;font-weight:700;">${p.job_type}</span>`);
    if (p.salary_range) chips.push(`<span style="background:rgba(255,255,255,0.15);padding:4px 12px;border-radius:20px;font-size:0.75rem;font-weight:600;"><i class="fas fa-money-bill-wave"></i> ${p.salary_range}</span>`);
    document.getElementById('pmMeta').innerHTML = chips.join('');

    // Description
    document.getElementById('pmDescription').innerHTML = p.description
        ? p.description.replace(/\n/g, '<br>')
        : '<em style="color:#aaa;">No description provided.</em>';

    // Requirements
    const req = document.getElementById('pmRequirements');
    if (p.requirements && p.requirements.trim() !== '') {
        req.innerHTML = `
            <div style="font-size:1rem;font-weight:700;color:var(--primary);margin-bottom:12px;">Requirements</div>
            <div style="color:var(--text-gray);">${p.requirements.replace(/\n/g,'<br>')}</div>
        `;
    } else {
        req.innerHTML = '';
    }

    // Wire apply button
    document.getElementById('pmApplyBtn').onclick = function() {
        closePosModal();
        const sel = document.querySelector('[name="position"]');
        if (sel) sel.value = p.position;
        const formEl = document.getElementById('apply-form');
        if (formEl) formEl.scrollIntoView({behavior:'smooth'});
    };

    const modal = document.getElementById('positionModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closePosModal() {
    document.getElementById('positionModal').style.display = 'none';
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { 
    if (e.key === 'Escape') closePosModal(); 
});
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>