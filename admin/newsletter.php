<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

include 'dashboard_header.php';

$admin_id   = $_SESSION['user_id'] ?? 0;
$admin_name = $_SESSION['name'] ?? 'Admin';

// ══════════════════════════════════════════════════════════════════════════════
//  DATABASE AUTO-PATCH
// ══════════════════════════════════════════════════════════════════════════════
$conn->query("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS newsletter_broadcasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    admin_name VARCHAR(100) NULL,
    subject VARCHAR(255) NOT NULL,
    body_content TEXT NOT NULL,
    recipient_count INT DEFAULT 0,
    target_audience VARCHAR(50) DEFAULT 'Subscribers',
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$check_col = $conn->query("SHOW COLUMNS FROM newsletter_broadcasts LIKE 'target_audience'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE newsletter_broadcasts ADD COLUMN target_audience VARCHAR(50) DEFAULT 'Subscribers' AFTER recipient_count");
}

$check_cat = $conn->query("SHOW COLUMNS FROM newsletter_broadcasts LIKE 'category'");
if ($check_cat && $check_cat->num_rows == 0) {
    $conn->query("ALTER TABLE newsletter_broadcasts ADD COLUMN category VARCHAR(100) DEFAULT 'General Announcement' AFTER subject");
}

// ══════════════════════════════════════════════════════════════════════════════
//  EMAIL WRAPPER HELPER
// ══════════════════════════════════════════════════════════════════════════════
function buildBroadcastEmail($heading, $body_html, $category = 'Announcement') {
    $siteUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://graphicafix.com';
    $logoUrl = $siteUrl . '/assets/images/logo.png';
    return "<!DOCTYPE html><html><head><meta charset='utf-8'></head>
    <body style='margin:0;padding:0;background:#f4f6f8;font-family:-apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif;'>
    <div style='max-width:600px;margin:36px auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;box-shadow:0 6px 20px rgba(0,0,0,0.06);'>
        <div style='background:#024442;padding:32px 36px;text-align:center;'>
            <div style='margin-bottom:10px;'><img src='{$logoUrl}' alt='Graphicafix' style='max-height:48px;'></div>
            <div style='display:inline-block;background:rgba(255,255,255,0.15);color:#fff;font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;padding:3px 12px;border-radius:12px;margin-bottom:10px;'>".htmlspecialchars($category)."</div>
            <h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>".htmlspecialchars($heading)."</h1>
        </div>
        <div style='padding:36px 36px 28px 36px; font-size:15px; color:#334155; line-height:1.7;'>
            $body_html
        </div>
        <div style='background:#f8fafc;padding:18px 36px;text-align:center;border-top:1px solid #e2e8f0;'>
            <p style='margin:0;font-size:12px;color:#64748b;font-weight:600;'>Graphicafix Announcements &amp; Newsletter</p>
            <p style='margin:4px 0 0 0;font-size:11.5px;color:#94a3b8;'><a href='$siteUrl' style='color:#024442;text-decoration:none;'>$siteUrl</a></p>
        </div>
    </div></body></html>";
}

// ══════════════════════════════════════════════════════════════════════════════
//  HANDLE BROADCAST SUBMISSION
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_broadcast'])) {
    $subject = trim($_POST['subject'] ?? '');
    $category = trim($_POST['category'] ?? 'General Announcement');
    $message = trim($_POST['message_body'] ?? '');
    $target_audience = trim($_POST['target_audience'] ?? 'Subscribers');

    if (empty($subject) || empty($message) || $message === '<p><br></p>') {
        header("Location: newsletter.php?tab=broadcasts&error=empty");
        exit;
    }

    $emails = [];
    if ($target_audience === 'Subscribers') {
        $subs = $conn->query("SELECT email FROM newsletter_subscribers WHERE email IS NOT NULL AND email != ''");
        if ($subs) { while ($r = $subs->fetch_assoc()) $emails[] = $r['email']; }
    } elseif ($target_audience === 'All Users') {
        $subs = $conn->query("SELECT email FROM users WHERE email IS NOT NULL AND email != ''");
        if ($subs) { while ($r = $subs->fetch_assoc()) $emails[] = $r['email']; }
    } elseif ($target_audience === 'All Clients') {
        $subs = $conn->query("SELECT email FROM clients WHERE email IS NOT NULL AND email != '' UNION SELECT email FROM users WHERE role IN ('client', 'client_sub') AND email IS NOT NULL AND email != ''");
        if ($subs) { while ($r = $subs->fetch_assoc()) $emails[] = $r['email']; }
    } elseif ($target_audience === 'Staff & Team') {
        $subs = $conn->query("SELECT email FROM users WHERE role IN ('super_admin', 'superadmin', 'admin', 'pm', 'user', 'hrm', 'sales', 'manager') AND email IS NOT NULL AND email != ''");
        if ($subs) { while ($r = $subs->fetch_assoc()) $emails[] = $r['email']; }
    } else {
        $role_prefix = 'Role: ';
        if (strpos($target_audience, $role_prefix) === 0) {
            $role = substr($target_audience, strlen($role_prefix));
            $stmt_u = $conn->prepare("SELECT email FROM users WHERE role = ? AND email IS NOT NULL AND email != ''");
            $stmt_u->bind_param("s", $role);
            $stmt_u->execute();
            $res_u = $stmt_u->get_result();
            while ($r = $res_u->fetch_assoc()) {
                $emails[] = $r['email'];
            }
        }
    }

    $emails = array_unique(array_filter($emails));
    
    if (count($emails) > 0) {
        $sent_count = 0;
        $html_content = buildBroadcastEmail($subject, $message, $category);
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Graphicafix Announcements <noreply@graphicafix.com>\r\n";
        $headers .= "Reply-To: info@graphicafix.com\r\n";

        foreach ($emails as $email_addr) {
            @mail($email_addr, $subject, $html_content, $headers);
            $sent_count++;
        }

        // Store History in DB
        $stmt = $conn->prepare("INSERT INTO newsletter_broadcasts (admin_id, admin_name, subject, category, body_content, recipient_count, target_audience) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssis", $admin_id, $admin_name, $subject, $category, $message, $sent_count, $target_audience);
        $stmt->execute();

        header("Location: newsletter.php?tab=broadcasts&success=sent");
        exit;
    } else {
        header("Location: newsletter.php?tab=broadcasts&error=nosubs");
        exit;
    }
}

// ── STATE & STATS ──
$tab = $_GET['tab'] ?? 'broadcasts';
if (!in_array($tab, ['broadcasts', 'subscribers'])) $tab = 'broadcasts';

$total_subs = (int)($conn->query("SELECT COUNT(*) AS c FROM newsletter_subscribers")->fetch_assoc()['c'] ?? 0);
$total_broadcasts = (int)($conn->query("SELECT COUNT(*) AS c FROM newsletter_broadcasts")->fetch_assoc()['c'] ?? 0);

$thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
$new_30_days = (int)($conn->query("SELECT COUNT(*) AS c FROM newsletter_subscribers WHERE subscribed_at >= '$thirty_days_ago'")->fetch_assoc()['c'] ?? 0);

$seven_days_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
$new_7_days = (int)($conn->query("SELECT COUNT(*) AS c FROM newsletter_subscribers WHERE subscribed_at >= '$seven_days_ago'")->fetch_assoc()['c'] ?? 0);

function aColor($n) {
    $c=['pink','blue','green','orange','purple'];
    return $c[ord(strtoupper($n[0]??'A'))%5];
}

function timeAgo($d) {
    $s=time()-strtotime($d);
    if($s<60)   return $s."s ago";
    if($s<3600) return round($s/60)."m ago";
    if($s<86400)return round($s/3600)."h ago";
    if($s<604800)return round($s/86400)."d ago";
    return date("M j, Y",strtotime($d));
}
?>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<style>
    /* Clean Quill Overrides to match modal */
    .ql-toolbar.ql-snow { border: 1.5px solid #e0e0e0 !important; border-bottom: none !important; border-radius: 10px 10px 0 0 !important; background: #f9fbfc; padding: 10px 14px !important; }
    .ql-container.ql-snow { border: 1.5px solid #e0e0e0 !important; border-radius: 0 0 10px 10px !important; font-family: inherit !important; font-size: 14px !important; background: #fff; }
</style>

<div class="height-100">

    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e0f2fe; color:#0284c7;"><i class="fas fa-bullhorn"></i></div>
            <div class="stat-number" style="color:#0f172a;"><?= number_format($total_broadcasts) ?></div>
            <div class="stat-label">Broadcasts Sent</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#f3e8ff; color:#8b5cf6;"><i class="fas fa-users"></i></div>
            <div class="stat-number" style="color:#8b5cf6;"><?= number_format($total_subs) ?></div>
            <div class="stat-label">Total Subscribers</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7; color:#10b981;"><i class="fas fa-chart-line"></i></div>
            <div class="stat-number" style="color:#10b981;">+<?= number_format($new_30_days) ?></div>
            <div class="stat-label">New (Last 30 Days)</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#ffedd5; color:#f59e0b;"><i class="fas fa-fire"></i></div>
            <div class="stat-number" style="color:#f59e0b;">+<?= number_format($new_7_days) ?></div>
            <div class="stat-label">New (Last 7 Days)</div>
        </div>
    </div>

    <div class="main-card">
        <div style="display:flex;border-bottom:2px solid #f0f0f0;padding:0 24px;overflow-x:auto;">
            <?php
            $tabs = [
                'broadcasts'  => ['<i class="fas fa-bullhorn"></i>', 'Announcements & Broadcasts', $total_broadcasts],
                'subscribers' => ['<i class="fas fa-users"></i>', 'Subscribers List', $total_subs],
            ];
            foreach ($tabs as $tk => $td): $a = ($tab === $tk); ?>
            <a href="newsletter.php?tab=<?= $tk ?>"
               style="display:inline-flex;align-items:center;gap:7px;padding:14px 20px;font-size:13.5px;font-weight:700;text-decoration:none;white-space:nowrap;border-bottom:2px solid <?= $a?'var(--primary)':'transparent' ?>;color:<?= $a?'var(--primary)':'#888' ?>;margin-bottom:-2px;transition:color .2s;">
                <?= $td[0] ?> <?= $td[1] ?>
                <span style="background:<?= $a?'var(--primary)':'#f0f0f0' ?>;color:<?= $a?'#fff':'#888' ?>;padding:1px 8px;border-radius:20px;font-size:11px;font-weight:800;"><?= $td[2] ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if ($tab === 'broadcasts'): ?>
        <div class="main-header" style="margin-top:4px;">
            <h3><i class="fas fa-bullhorn"></i> Announcement & Broadcast History</h3>
            <button class="add-client" onclick="openBroadcastModal()">+ New Announcement</button>
        </div>
        <div class="main-table">
            <div class="table-head" style="grid-template-columns: 2.2fr 1.2fr 1fr 1fr 1fr 0.5fr;">
                <span>Subject / Content</span>
                <span>Category</span>
                <span>Sent By</span>
                <span>Audience / Recipients</span>
                <span>Date Sent</span>
                <span>Actions</span>
            </div>
            <?php
            $broadcasts = $conn->query("SELECT * FROM newsletter_broadcasts ORDER BY sent_at DESC");
            if ($broadcasts && $broadcasts->num_rows > 0):
                while ($b = $broadcasts->fetch_assoc()): 
                    // Create a clean text preview
                    $preview = strip_tags($b['body_content']);
                    $preview = strlen($preview) > 60 ? substr($preview, 0, 60) . '...' : $preview;
            ?>
            <div class="table-row" style="grid-template-columns: 2.2fr 1.2fr 1fr 1fr 1fr 0.5fr;">
                <div style="display:flex; flex-direction:column; overflow:hidden;">
                    <strong style="font-size:14px; color:#333; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($b['subject']) ?></strong>
                    <small style="color:#888; font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($preview) ?></small>
                </div>
                <span data-label="Category">
                    <span style="background:#f1f5f9; color:#334155; padding:3px 10px; border-radius:12px; font-size:11.5px; font-weight:700; border:1px solid #cbd5e1;">
                        <?= htmlspecialchars($b['category'] ?? 'General') ?>
                    </span>
                </span>
                <span data-label="Sent By" style="font-size:13px; font-weight:600; color:var(--primary);">
                    <?= htmlspecialchars($b['admin_name']) ?>
                </span>
                <span data-label="Audience / Recipients">
                    <div style="font-size:12px; color:#666; font-weight:600; margin-bottom:4px;"><?= htmlspecialchars($b['target_audience'] ?? 'Subscribers') ?></div>
                    <span style="background:#e0f2fe; color:#0284c7; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:700;">
                        <i class="fas fa-users"></i> <?= number_format($b['recipient_count']) ?>
                    </span>
                </span>
                <span data-label="Date Sent" style="font-size:13px; color:#666;">
                    <?= date('M j, Y', strtotime($b['sent_at'])) ?><br>
                    <small style="color:#aaa;"><?= date('g:i A', strtotime($b['sent_at'])) ?></small>
                </span>
                <div style="display:flex; gap:6px;">
                    <div id="html_content_<?= $b['id'] ?>" style="display:none;"><?= htmlspecialchars($b['body_content']) ?></div>
                    <button class="dots" title="View Broadcast" onclick="openViewModal('<?= htmlspecialchars(addslashes($b['subject'])) ?>', <?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['admin_name'])) ?>', <?= $b['recipient_count'] ?>, '<?= date('M j, Y g:i A', strtotime($b['sent_at'])) ?>')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div style="text-align:center;padding:60px 24px;color:#888;">
                <div style="font-size:2.5rem;margin-bottom:8px;"><i class="fas fa-bullhorn"></i></div>
                No broadcasts sent yet.
            </div>
            <?php endif; ?>
        </div>

        <?php else: // Subscribers Tab ?>
        <div class="main-header" style="margin-top:4px;">
            <h3><i class="fas fa-users"></i> Active Subscribers</h3>
        </div>
        <div class="main-table">
            <div class="table-head" style="grid-template-columns: 2fr 1fr 1fr;">
                <span>Subscriber Email</span>
                <span>Date Joined</span>
                <span>Status</span>
            </div>
            <?php
            $subsList = $conn->query("SELECT * FROM newsletter_subscribers ORDER BY subscribed_at DESC");
            if ($subsList && $subsList->num_rows > 0):
                while ($sub = $subsList->fetch_assoc()): 
                    $initial = strtoupper(substr($sub['email'], 0, 1));
            ?>
            <div class="table-row" style="grid-template-columns: 2fr 1fr 1fr;">
                <div class="client">
                    <div class="avatar <?= aColor($initial) ?>"><?= $initial ?></div>
                    <div>
                        <strong style="font-size:14px; color:var(--primary);"><?= htmlspecialchars($sub['email']) ?></strong>
                    </div>
                </div>
                <span data-label="Date Joined" style="font-size:13px; color:#666;">
                    <?= date('M j, Y', strtotime($sub['subscribed_at'])) ?> <small style="color:#aaa;">(<?= timeAgo($sub['subscribed_at']) ?>)</small>
                </span>
                <span data-label="Status">
                    <span class="status active">Subscribed</span>
                </span>
            </div>
            <?php endwhile; else: ?>
            <div style="text-align:center;padding:60px 24px;color:#888;">
                <div style="font-size:2.5rem;margin-bottom:8px;"><i class="fas fa-envelope-open"></i></div>
                No subscribers yet.
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<div id="broadcastModal" class="modal-overlay" onclick="if(event.target===this)closeBroadcastModal()">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header">
            <h4><div class="modal-header-icon"><i class="fas fa-bullhorn"></i></div> Compose Broadcast</h4>
            <button type="button" class="modal-close" onclick="closeBroadcastModal()">×</button>
        </div>
        <form method="POST" id="broadcastForm" onsubmit="return prepareForm();">
            <input type="hidden" name="send_broadcast" value="1">
            <div class="modal-body">
                <div class="info-grid" style="grid-template-columns: 1fr;">
                    
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Target Audience <span style="color:#ef4444;">*</span></label>
                        <select name="target_audience" id="bAudience" required style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;font-family:inherit;">
                            <option value="Subscribers">Newsletter Subscribers</option>
                            <option value="All Clients">All Registered Clients</option>
                            <option value="Staff & Team">Agency Staff & Delivery Team</option>
                            <option value="Role: student">Enrolled Students Only</option>
                            <option value="Role: teacher">Course Instructors Only</option>
                            <option value="All Users">All Registered Users</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Announcement Category <span style="color:#ef4444;">*</span></label>
                        <select name="category" id="bCategory" required style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;font-family:inherit;">
                            <option value="General Announcement">General Announcement</option>
                            <option value="Company & Agency Update">Company & Agency Update</option>
                            <option value="Service & Feature Launch">Service & Feature Launch</option>
                            <option value="Operational & Holiday Notice">Operational & Holiday Notice</option>
                            <option value="Monthly Newsletter">Monthly Newsletter</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Announcement Subject <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="subject" id="bSubject" required placeholder="e.g. Schedule Update / New Feature Release"
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;font-family:inherit;">
                    </div>

                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Announcement Content <span style="color:#ef4444;">*</span></label>
                        <div id="emailEditor" style="height: 250px;"></div>
                        <input type="hidden" name="message_body" id="message_body">
                    </div>

                    <div style="background:#fff4e5; padding:12px 16px; border-radius:10px; border:1px solid #fed7aa; display:flex; gap:10px; align-items:flex-start;">
                        <span style="font-size:16px; color:#c2410c;"><i class="fas fa-exclamation-triangle"></i></span>
                        <div>
                            <strong style="font-size:13px; color:#9a3412; display:block; margin-bottom:2px;">Targeted Email Dispatch</strong>
                            <span style="font-size:12px; color:#c2410c;">Emails will be delivered strictly to members in the selected target group.</span>
                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeBroadcastModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="bSubmit"><i class="fas fa-paper-plane"></i> Send Announcement</button>
            </div>
        </form>
    </div>
</div>

<div id="viewModal" class="modal-overlay" onclick="if(event.target===this)closeViewModal()">
    <div class="modal-content" style="max-width:650px;">
        <div class="modal-header">
            <h4><div class="modal-header-icon"><i class="fas fa-eye"></i></div> View Broadcast</h4>
            <button type="button" class="modal-close" onclick="closeViewModal()">×</button>
        </div>
        <div class="modal-body">
            <h3 id="vSubject" style="margin: 0 0 8px 0; color: #0f172a; font-size: 1.2rem;"></h3>
            
            <div style="display:flex; flex-wrap:wrap; gap:15px; margin-bottom:20px; padding-bottom:15px; border-bottom:1px solid #f0f0f0;">
                <span style="font-size:12px; color:#666;"><i class="fas fa-user-circle"></i> Sent by: <strong id="vSender" style="color:var(--primary);"></strong></span>
                <span style="font-size:12px; color:#666;"><i class="fas fa-calendar-alt"></i> Date: <strong id="vDate"></strong></span>
                <span style="font-size:12px; color:#666;"><i class="fas fa-users"></i> Delivered to: <strong id="vCount"></strong></span>
            </div>

            <div style="font-size:13px; font-weight:700; color:#888; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px;">Email Content Preview</div>
            <div id="vContent" style="background:#f9fbfc; padding:20px; border-radius:10px; border:1px solid #e2e8f0; font-size:14px; line-height:1.7; color:#333; max-height:400px; overflow-y:auto;">
                </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary-custom" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<div id="toastContainer" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;"></div>

<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
// Initialize Quill
var quill = new Quill('#emailEditor', {
    theme: 'snow',
    placeholder: 'Write your email content here...',
    modules: {
        toolbar: [
            [{ 'header': [1, 2, 3, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            ['link'], ['clean']
        ]
    }
});

function openBroadcastModal() {
    document.getElementById('broadcastForm').reset();
    quill.root.innerHTML = '';
    document.getElementById('broadcastModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeBroadcastModal() {
    document.getElementById('broadcastModal').classList.remove('active');
    document.body.style.overflow = '';
}

function openViewModal(subject, id, sender, count, dateStr) {
    document.getElementById('vSubject').textContent = subject;
    document.getElementById('vSender').textContent = sender;
    document.getElementById('vCount').textContent = count;
    document.getElementById('vDate').textContent = dateStr;
    
    // Pull the raw HTML from the hidden div
    const htmlContent = document.getElementById('html_content_' + id).textContent;
    document.getElementById('vContent').innerHTML = htmlContent;

    document.getElementById('viewModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
    document.body.style.overflow = '';
}

function prepareForm() {
    var htmlContent = quill.root.innerHTML;
    if(htmlContent === '<p><br></p>' || htmlContent.trim() === '') {
        alert('Please write a message before sending.');
        return false;
    }
    
    document.getElementById('message_body').value = htmlContent;
    
    const btn = document.getElementById('bSubmit');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    btn.style.opacity = '0.7';
    btn.style.pointerEvents = 'none';
    
    return true;
}

// Toasts
function showToast(msg, type='success') {
    const color = type === 'success' ? '#10b981' : '#ef4444';
    const t = document.createElement('div');
    t.style.cssText = `background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:220px;border-left:4px solid ${color};z-index:9999;`;
    t.innerHTML = `<span style="font-weight:700;color:${color}">${type==='success'?'<i class="fas fa-check"></i>':'<i class="fas fa-times"></i>'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3500);
}

document.addEventListener('DOMContentLoaded', () => {
    <?php if (isset($_GET['success'])): ?> showToast('Broadcast sent successfully!'); <?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] == 'empty'): ?> showToast('Subject and body cannot be empty.', 'error'); <?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] == 'nosubs'): ?> showToast('You have no subscribers yet.', 'error'); <?php endif; ?>
});

// Escape key closes modals
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeBroadcastModal(); closeViewModal(); }
});
</script>

<?php include('dashboard_footer.php'); ?>