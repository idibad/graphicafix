<?php
/**
 * Graphica Fix - Official Employee Verification & Profile Page
 * Accessed via QR Code scan or direct link (verify-employee.php?id=EMP0033)
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/team_schema.php';
ensureTeamSchema($conn);

// Retrieve search query or parameter
$raw_id = trim($_GET['id'] ?? ($_GET['emp'] ?? ($_GET['code'] ?? '')));
$employee = null;
$searched = false;
$not_found = false;

if (!empty($raw_id)) {
    $searched = true;
    
    // Check available columns defensively
    $existing_cols = [];
    try {
        $c_res = $conn->query("SHOW COLUMNS FROM `team`");
        if ($c_res) {
            while ($cr = $c_res->fetch_assoc()) {
                $existing_cols[strtolower($cr['Field'])] = true;
            }
        }
    } catch (Throwable $e) {}

    $where_parts = [];
    $types = '';
    $params = [];

    if (isset($existing_cols['employee_id'])) {
        $where_parts[] = "employee_id = ?";
        $types .= 's';
        $params[] = $raw_id;
    }
    if (isset($existing_cols['verification_code'])) {
        $where_parts[] = "verification_code = ?";
        $types .= 's';
        $params[] = $raw_id;
    }
    if (is_numeric($raw_id)) {
        $where_parts[] = "id = ?";
        $types .= 'i';
        $params[] = intval($raw_id);
    }
    if (isset($existing_cols['member_name'])) {
        $where_parts[] = "member_name LIKE ?";
        $types .= 's';
        $params[] = "%" . $raw_id . "%";
    }

    if (!empty($where_parts)) {
        try {
            $stmt = $conn->prepare("SELECT * FROM team WHERE " . implode(" OR ", $where_parts) . " LIMIT 1");
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $employee = $res->fetch_assoc();
                } else {
                    $not_found = true;
                }
                $stmt->close();
            }
        } catch (Throwable $e) {
            $not_found = true;
        }
    } else {
        $not_found = true;
    }
}

$page_title = $employee 
    ? htmlspecialchars($employee['member_name']) . " - Employee Verification | Graphicafix" 
    : "Employee Credential Verification | Graphicafix";

include __DIR__ . '/../templates/header.php';

// Prepare data if employee found
if ($employee) {
    $emp_id       = htmlspecialchars($employee['employee_id'] ?? ('EMP' . str_pad($employee['id'], 4, '0', STR_PAD_LEFT)));
    $name         = htmlspecialchars($employee['member_name']);
    $role         = htmlspecialchars($employee['role'] ?? 'Team Member');
    $dept         = htmlspecialchars($employee['department'] ?? 'Operations & Creative');
    $email        = htmlspecialchars($employee['email'] ?? 'contact@graphicafix.com');
    $phone        = htmlspecialchars($employee['phone'] ?? '0345 4568986');
    $bio          = htmlspecialchars($employee['bio'] ?? 'Dedicated member of the Graphica Fix team.');
    $gender       = htmlspecialchars($employee['gender'] ?? 'Male');
    $status       = strtolower($employee['status'] ?? 'active');
    $blood_group  = htmlspecialchars($employee['blood_group'] ?? 'N/A');
    $joined_at    = !empty($employee['joined_at']) ? date('M d, Y', strtotime($employee['joined_at'])) : 'Verified Record';
    $vcode        = htmlspecialchars($employee['verification_code'] ?? hash('sha256', $emp_id));
    $photo_path   = !empty($employee['photo']) ? BASE_URL . ltrim($employee['photo'], '/') : '';
    
}
?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --gf-primary: #024442;
        --gf-primary-dark: #012b2a;
        --gf-primary-deep: #031e1c;
        --gf-accent: #b6f763;
        --gf-accent-hover: #9ef133;
        --gf-accent-glow: rgba(182, 247, 99, 0.25);
        --gf-surface: #ffffff;
        --gf-surface-muted: #f4f8f5;
        --gf-border: #e2ece5;
        --gf-text-dark: #082824;
        --gf-text-muted: #526b66;
        --card-radius: 24px;
    }

    body {
        font-family: 'Plus Jakarta Sans', 'Poppins', sans-serif;
        background: #f7faf8;
        color: var(--gf-text-dark);
    }

    /* ── Hero / Top Header ── */
    .verify-hero {
        background: linear-gradient(145deg, var(--gf-primary-dark) 0%, var(--gf-primary) 100%);
        padding: 120px 0 70px;
        position: relative;
        overflow: hidden;
        color: #ffffff;
        text-align: center;
    }
    .verify-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image: radial-gradient(rgba(182, 247, 99, 0.12) 1.5px, transparent 1.5px);
        background-size: 28px 28px;
        opacity: 0.6;
    }
    .verify-hero::after {
        content: '';
        position: absolute;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(182, 247, 99, 0.15) 0%, transparent 70%);
        top: -100px;
        right: -100px;
        pointer-events: none;
    }
    .verify-badge-top {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(182, 247, 99, 0.15);
        border: 1px solid rgba(182, 247, 99, 0.35);
        color: var(--gf-accent);
        padding: 6px 18px;
        border-radius: 50px;
        font-size: 0.82rem;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 18px;
        position: relative;
        backdrop-filter: blur(8px);
    }
    .verify-hero h1 {
        font-size: clamp(2.1rem, 4vw, 3.2rem);
        font-weight: 800;
        margin-bottom: 14px;
        position: relative;
        color: #ffffff;
        letter-spacing: -0.5px;
    }
    .verify-hero h1 span {
        color: var(--gf-accent);
    }
    .verify-hero p {
        font-size: 1.05rem;
        color: rgba(255, 255, 255, 0.82);
        max-width: 600px;
        margin: 0 auto;
        position: relative;
        font-weight: 300;
    }

    /* ── Search Bar Floating ── */
    .verify-search-wrap {
        max-width: 580px;
        margin: 30px auto 0;
        position: relative;
    }
    .verify-search-form {
        display: flex;
        align-items: center;
        background: #ffffff;
        border-radius: 50px;
        padding: 6px 8px 6px 20px;
        box-shadow: 0 16px 40px rgba(1, 43, 42, 0.25);
        border: 2px solid transparent;
        transition: all 0.3s;
    }
    .verify-search-form:focus-within {
        border-color: var(--gf-accent);
        box-shadow: 0 16px 40px rgba(182, 247, 99, 0.2);
    }
    .verify-search-input {
        flex: 1;
        border: none;
        outline: none;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--gf-text-dark);
        background: transparent;
    }
    .verify-search-input::placeholder {
        color: #99aba6;
        font-weight: 400;
    }
    .verify-search-btn {
        background: var(--gf-primary);
        color: var(--gf-accent);
        border: none;
        padding: 10px 24px;
        border-radius: 50px;
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s;
    }
    .verify-search-btn:hover {
        background: var(--gf-accent);
        color: var(--gf-primary-dark);
        transform: translateY(-1px);
    }

    /* ── Main Content Container ── */
    .verify-container {
        max-width: 1140px;
        margin: -40px auto 90px;
        padding: 0 20px;
        position: relative;
        z-index: 10;
    }

    /* ── Verification Result Card ── */
    .auth-card {
        background: #ffffff;
        border-radius: var(--card-radius);
        box-shadow: 0 24px 60px rgba(2, 68, 66, 0.08);
        border: 1px solid var(--gf-border);
        overflow: hidden;
        position: relative;
    }

    /* Header banner inside card */
    .auth-card-top-bar {
        background: linear-gradient(90deg, #024442 0%, #085a57 100%);
        padding: 18px 32px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 15px;
        color: #ffffff;
        border-bottom: 2px solid var(--gf-accent);
    }
    .auth-stamp {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .auth-stamp-icon {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: rgba(182, 247, 99, 0.18);
        color: var(--gf-accent);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        border: 1.5px solid var(--gf-accent);
        box-shadow: 0 0 15px rgba(182, 247, 99, 0.35);
        animation: pulseGlow 2.5s infinite;
    }
    @keyframes pulseGlow {
        0%, 100% { box-shadow: 0 0 10px rgba(182, 247, 99, 0.3); }
        50% { box-shadow: 0 0 25px rgba(182, 247, 99, 0.65); }
    }
    .auth-stamp-title {
        font-size: 1rem;
        font-weight: 800;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: var(--gf-accent);
        line-height: 1.2;
    }
    .auth-stamp-sub {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.75);
    }
    .auth-id-badge {
        background: rgba(0, 0, 0, 0.3);
        border: 1px dashed var(--gf-accent);
        border-radius: 10px;
        padding: 6px 16px;
        font-family: monospace;
        font-size: 0.95rem;
        font-weight: 700;
        color: #ffffff;
        letter-spacing: 1px;
    }
    .auth-id-badge span {
        color: var(--gf-accent);
    }

    /* Card Main Body */
    .auth-card-body {
        padding: 40px;
    }

    /* Profile Header Section */
    .emp-profile-head {
        display: flex;
        align-items: center;
        gap: 35px;
        padding-bottom: 35px;
        border-bottom: 1px solid var(--gf-border);
        flex-wrap: wrap;
    }
    .emp-avatar-stage {
        position: relative;
        flex-shrink: 0;
    }
    .emp-avatar-outer {
        width: 170px;
        height: 170px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--gf-accent) 0%, #87bd0a 100%);
        padding: 6px;
        box-shadow: 0 12px 30px rgba(2, 68, 66, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    .emp-avatar-inner {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        overflow: hidden;
        background: var(--gf-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        border: 4px solid #ffffff;
    }
    .emp-avatar-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .emp-avatar-placeholder {
        font-size: 4rem;
        color: var(--gf-accent);
        font-weight: 800;
        text-transform: uppercase;
    }
    .emp-verified-pill {
        position: absolute;
        bottom: 5px;
        right: 5px;
        background: #10b981;
        color: #ffffff;
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        border: 3px solid #ffffff;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }

    .emp-meta {
        flex: 1;
        min-width: 280px;
    }
    .emp-name {
        font-size: clamp(1.8rem, 3vw, 2.4rem);
        font-weight: 800;
        color: var(--gf-primary);
        margin-bottom: 8px;
        letter-spacing: -0.5px;
        line-height: 1.2;
    }
    .emp-role-tag {
        display: inline-block;
        background: var(--gf-surface-muted);
        border: 1px solid var(--gf-border);
        color: var(--gf-primary);
        font-weight: 700;
        font-size: 0.95rem;
        padding: 5px 16px;
        border-radius: 50px;
        margin-bottom: 12px;
    }
    .emp-role-tag i {
        color: var(--gf-accent);
        background: var(--gf-primary);
        border-radius: 50%;
        padding: 4px;
        font-size: 0.75rem;
        margin-right: 6px;
    }
    .emp-department {
        font-size: 1.05rem;
        color: var(--gf-text-muted);
        font-weight: 500;
        margin-bottom: 16px;
    }
    .emp-bio {
        font-size: 0.95rem;
        color: #4b635e;
        line-height: 1.65;
        max-width: 650px;
        margin-bottom: 0;
    }

    /* Verification Status Banner */
    .verify-status-box {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px 24px;
        border-radius: 16px;
        margin-top: 30px;
        margin-bottom: 35px;
    }
    .verify-status-box.active {
        background: #ecfdf5;
        border: 1.5px solid #a7f3d0;
        color: #065f46;
    }
    .verify-status-box.on_leave {
        background: #fffbeb;
        border: 1.5px solid #fde68a;
        color: #92400e;
    }
    .verify-status-box.inactive {
        background: #fef2f2;
        border: 1.5px solid #fecaca;
        color: #991b1b;
    }
    .verify-status-icon {
        font-size: 1.8rem;
    }
    .verify-status-text h4 {
        margin: 0 0 2px 0;
        font-size: 1.05rem;
        font-weight: 800;
    }
    .verify-status-text p {
        margin: 0;
        font-size: 0.85rem;
        opacity: 0.9;
    }

    /* Information Grid */
    .emp-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 35px;
    }
    .emp-info-item {
        background: var(--gf-surface-muted);
        border: 1px solid var(--gf-border);
        border-radius: 16px;
        padding: 18px 20px;
        transition: transform 0.2s, border-color 0.2s;
    }
    .emp-info-item:hover {
        transform: translateY(-2px);
        border-color: var(--gf-accent);
    }
    .emp-info-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--gf-text-muted);
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .emp-info-label i {
        color: var(--gf-primary);
        font-size: 0.85rem;
    }
    .emp-info-value {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--gf-text-dark);
        word-break: break-word;
    }
    .emp-info-value a {
        color: var(--gf-primary);
        text-decoration: none;
    }
    .emp-info-value a:hover {
        color: #085a57;
        text-decoration: underline;
    }

    /* ── Action Buttons ── */
    .id-btn {
        background: #ffffff;
        border: 1.5px solid var(--gf-border);
        color: var(--gf-primary);
        font-weight: 700;
        font-size: 0.85rem;
        padding: 8px 18px;
        border-radius: 50px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s;
        text-decoration: none;
    }
    .id-btn:hover {
        background: var(--gf-primary);
        color: var(--gf-accent);
        border-color: var(--gf-primary);
    }
    .id-btn.primary {
        background: var(--gf-primary);
        color: var(--gf-accent);
        border-color: var(--gf-primary);
    }
    .id-btn.primary:hover {
        background: var(--gf-primary-dark);
        color: #ffffff;
    }

    /* ── Security Footer Notice ── */
    .auth-security-footer {
        background: var(--gf-surface-muted);
        border-top: 1px solid var(--gf-border);
        padding: 24px 35px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 15px;
        font-size: 0.85rem;
        color: var(--gf-text-muted);
    }
    .auth-security-footer a {
        color: var(--gf-primary);
        font-weight: 700;
        text-decoration: none;
    }

    /* ── Not Found / Blank State ── */
    .not-found-card {
        background: #ffffff;
        border-radius: var(--card-radius);
        padding: 60px 40px;
        text-align: center;
        border: 1px solid var(--gf-border);
        box-shadow: 0 20px 50px rgba(0,0,0,0.06);
    }
    .not-found-icon {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        background: #fef2f2;
        color: #ef4444;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.8rem;
        margin: 0 auto 24px;
        border: 2px dashed #fca5a5;
    }

    /* Print Styles */
    @media print {
        header, footer, .verify-hero, .verify-search-wrap, .auth-security-footer {
            display: none !important;
        }
        body, .verify-container {
            background: #ffffff !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .auth-card {
            box-shadow: none !important;
            border: 1px solid #ccc !important;
        }
    }

    @media (max-width: 768px) {
        .verify-hero { padding: 90px 15px 50px; }
        .auth-card-body { padding: 25px 20px; }
        .emp-profile-head { flex-direction: column; text-align: center; gap: 20px; }
        .emp-bio { margin: 0 auto; }
        .emp-role-tag { margin: 0 auto 12px; }
    }
</style>

<div class="verify-page-wrapper">

    <!-- ── Hero Banner ── -->
    <section class="verify-hero">
        <div class="container">
            <div class="verify-badge-top">
                <i class="fas fa-shield-alt"></i> Official Security Portal
            </div>
            <h1>Employee Identity <span>Verification</span></h1>
            <p>Scan an official Graphica Fix ID badge QR code or enter an employee identification code below to verify authenticated credentials.</p>

            <!-- Search Form -->
            <div class="verify-search-wrap">
                <form action="<?= BASE_URL ?>verify-employee.php" method="GET" class="verify-search-form">
                    <i class="fas fa-search text-muted me-2" style="font-size: 1.1rem; color: #8ba19b;"></i>
                    <input type="text" name="id" class="verify-search-input" 
                           placeholder="Enter Employee ID (e.g. EMP0033 or EMP0001)..." 
                           value="<?= htmlspecialchars($raw_id) ?>" required>
                    <button type="submit" class="verify-search-btn">
                        <span>Verify</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- ── Main Verification Results ── -->
    <div class="verify-container">

        <?php if ($employee): ?>
            <!-- ── EMPLOYEE VERIFIED STATE ── -->
            <div class="auth-card" id="printableCard">
                
                <!-- Top Status Header -->
                <div class="auth-card-top-bar">
                    <div class="auth-stamp">
                        <div class="auth-stamp-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <div class="auth-stamp-title">Credential Authenticated</div>
                            <div class="auth-stamp-sub">Official Graphica Fix Verified Personnel</div>
                        </div>
                    </div>
                    <div class="auth-id-badge">
                        <span>ID:</span> <?= $emp_id ?>
                    </div>
                </div>

                <div class="auth-card-body">
                    
                    <!-- Head: Avatar + Core Meta -->
                    <div class="emp-profile-head">
                        <div class="emp-avatar-stage">
                            <div class="emp-avatar-outer">
                                <div class="emp-avatar-inner">
                                    <?php if (!empty($photo_path)): ?>
                                        <img src="<?= $photo_path ?>" alt="<?= $name ?>" class="emp-avatar-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="emp-avatar-placeholder" style="display:none;"><?= strtoupper(substr($name, 0, 1)) ?></div>
                                    <?php else: ?>
                                        <div class="emp-avatar-placeholder"><?= strtoupper(substr($name, 0, 1)) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="emp-verified-pill" title="Graphica Fix Authenticated">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>

                        <div class="emp-meta">
                            <h2 class="emp-name"><?= $name ?></h2>
                            <div class="emp-role-tag">
                                <i class="fas fa-id-badge"></i>
                                <?= $role ?>
                            </div>
                            <div class="emp-department">
                                <i class="fas fa-building text-muted me-1"></i> <?= $dept ?>
                            </div>
                            <p class="emp-bio"><?= $bio ?></p>
                        </div>
                    </div>

                    <!-- Live Verification Status Bar -->
                    <div class="verify-status-box <?= $status ?>">
                        <div class="verify-status-icon">
                            <?php if ($status === 'active'): ?>
                                <i class="fas fa-shield-check text-success"></i>
                            <?php elseif ($status === 'on_leave'): ?>
                                <i class="fas fa-user-clock text-warning"></i>
                            <?php else: ?>
                                <i class="fas fa-user-slash text-danger"></i>
                            <?php endif; ?>
                        </div>
                        <div class="verify-status-text">
                            <h4>
                                <?php if ($status === 'active'): ?>
                                    Active Verified Personnel
                                <?php elseif ($status === 'on_leave'): ?>
                                    Authorized Personnel (Currently on Official Leave)
                                <?php else: ?>
                                    Former Staff Member / Inactive Credential
                                <?php endif; ?>
                            </h4>
                            <p>
                                Identity record confirmed via Graphica Fix Security Authentication Service. Verified on <?= date('F d, Y \a\t h:i A') ?>.
                            </p>
                        </div>
                    </div>

                    <!-- Details Grid -->
                    <div class="emp-info-grid">
                        <div class="emp-info-item">
                            <div class="emp-info-label"><i class="fas fa-fingerprint"></i> Employee Code</div>
                            <div class="emp-info-value font-monospace" style="color:var(--gf-primary); font-weight:800;"><?= $emp_id ?></div>
                        </div>

                        <div class="emp-info-item">
                            <div class="emp-info-label"><i class="fas fa-briefcase"></i> Designation / Role</div>
                            <div class="emp-info-value"><?= $role ?></div>
                        </div>

                        <div class="emp-info-item">
                            <div class="emp-info-label"><i class="fas fa-sitemap"></i> Department</div>
                            <div class="emp-info-value"><?= $dept ?></div>
                        </div>

                        <div class="emp-info-item">
                            <div class="emp-info-label"><i class="fas fa-envelope"></i> Official Email</div>
                            <div class="emp-info-value">
                                <a href="mailto:<?= $email ?>"><?= $email ?></a>
                            </div>
                        </div>

                        <div class="emp-info-item">
                            <div class="emp-info-label"><i class="fas fa-phone-alt"></i> Official Phone</div>
                            <div class="emp-info-value">
                                <a href="tel:<?= preg_replace('/[^0-9+]/', '', $phone) ?>"><?= $phone ?></a>
                            </div>
                        </div>

                        <div class="emp-info-item">
                            <div class="emp-info-label"><i class="fas fa-calendar-check"></i> Date of Joining</div>
                            <div class="emp-info-value"><?= $joined_at ?></div>
                        </div>

                        <div class="emp-info-item">
                            <div class="emp-info-label"><i class="fas fa-tint"></i> Blood Group</div>
                            <div class="emp-info-value"><?= $blood_group ?: 'Not Disclosed' ?></div>
                        </div>

                        <div class="emp-info-item">
                            <div class="emp-info-label"><i class="fas fa-key"></i> Security Token Hash</div>
                            <div class="emp-info-value font-monospace" style="font-size:0.8rem; color:#607d77;">
                                <?= substr($vcode, 0, 16) ?>...
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Card Security Footer -->
                <div class="auth-security-footer">
                    <div>
                        <i class="fas fa-shield-alt text-success me-1"></i>
                        Digitally verified by Graphica Fix Human Resources System.
                    </div>
                    <div>
                        Need assistance? Contact <a href="mailto:contact@graphicafix.com">contact@graphicafix.com</a>
                    </div>
                </div>

            </div>

        <?php elseif ($not_found): ?>
            <!-- ── NOT FOUND STATE ── -->
            <div class="not-found-card">
                <div class="not-found-icon">
                    <i class="fas fa-user-times"></i>
                </div>
                <h2 style="font-weight: 800; color: var(--gf-primary); margin-bottom: 12px;">Employee Record Not Found</h2>
                <p style="color: var(--gf-text-muted); max-width: 550px; margin: 0 auto 30px; font-size: 1.05rem;">
                    We could not locate any active or archived employee record with the ID or verification code: 
                    <strong class="text-danger font-monospace"><?= htmlspecialchars($raw_id) ?></strong>.
                </p>
                <div style="display:flex; justify-content:center; gap:15px; flex-wrap:wrap;">
                    <a href="verify-employee.php" class="id-btn primary" style="padding: 12px 28px; font-size:0.95rem;">
                        <i class="fas fa-redo"></i> Try Another ID
                    </a>
                    <a href="contact.php" class="id-btn" style="padding: 12px 28px; font-size:0.95rem;">
                        <i class="fas fa-headset"></i> Contact Support
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- ── INITIAL IDLE LOOKUP PORTAL ── -->
            <div class="auth-card" style="padding: 60px 40px; text-align: center;">
                <div style="width: 80px; height: 80px; background: rgba(182, 247, 99, 0.25); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: var(--gf-primary); font-size: 2.2rem;">
                    <i class="fas fa-id-card-alt"></i>
                </div>
                <h2 style="font-weight: 800; color: var(--gf-primary); margin-bottom: 10px;">Graphica Fix Identity Verification</h2>
                <p style="color: var(--gf-text-muted); max-width: 600px; margin: 0 auto 30px; font-size: 1rem;">
                    Please scan the QR code printed on any official Graphica Fix staff card with your smartphone camera, or enter an Employee ID in the search bar above.
                </p>
                <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
                    <a href="verify-employee.php?id=EMP0033" class="id-btn primary" style="padding: 10px 24px;">
                        <i class="fas fa-eye"></i> View Sample Verification (EMP0033)
                    </a>
                    <a href="about.php" class="id-btn" style="padding: 10px 24px;">
                        <i class="fas fa-users"></i> Meet Our Team
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </div>

</div>



<?php include __DIR__ . '/../templates/footer.php'; ?>
