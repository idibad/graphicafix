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
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// ══════════════════════════════════════════════════════════════════════════════
//  EMAIL WRAPPER HELPER
// ══════════════════════════════════════════════════════════════════════════════
function buildBroadcastEmail($heading, $body_html) {
    $siteUrl = 'https://graphicafix.com';
    return "<!DOCTYPE html><html><head><meta charset='utf-8'></head>
    <body style='margin:0;padding:0;background:#f4f6f8;font-family:Arial,sans-serif;'>
    <div style='max-width:580px;margin:36px auto;'>
        <div style='background:#024442;border-radius:16px 16px 0 0;padding:32px 40px;text-align:center;'>
            <div style='margin-bottom:8px;'><img src='$siteUrl/images/logo.png' alt='Logo' style='max-height:60px;'></div>
            <h1 style='margin:0;color:#fff;font-size:1.45rem;font-weight:700;'>$heading</h1>
        </div>
        <div style='background:#fff;padding:32px 40px; font-size:15px; color:#333; line-height:1.7;'>
            $body_html
        </div>
        <div style='background:#f7f9f5;border-radius:0 0 16px 16px;padding:14px 40px;text-align:center;border-top:1px solid #e0e0e0;'>
            <p style='margin:0;font-size:11.5px;color:#aaa;'>You are receiving this because you subscribed to Graphicafix.</p>
            <p style='margin:6px 0 0 0;font-size:11.5px;'><a href='$siteUrl' style='color:#024442;'>$siteUrl</a></p>
        </div>
    </div></body></html>";
}

// ══════════════════════════════════════════════════════════════════════════════
//  HANDLE BROADCAST SUBMISSION
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_broadcast'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message_body'] ?? '');

    if (empty($subject) || empty($message) || $message === '<p><br></p>') {
        header("Location: newsletter.php?tab=broadcasts&error=empty");
        exit;
    }

    $subs = $conn->query("SELECT email FROM newsletter_subscribers");
    
    if ($subs && $subs->num_rows > 0) {
        $sent_count = 0;
        $html_content = buildBroadcastEmail($subject, $message);
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Graphicafix <noreply@graphicafix.com>\r\n";
        $headers .= "Reply-To: info@graphicafix.com\r\n";

        while ($row = $subs->fetch_assoc()) {
            mail($row['email'], $subject, $html_content, $headers);
            $sent_count++;
        }

        // Store History in DB
        $stmt = $conn->prepare("INSERT INTO newsletter_broadcasts (admin_id, admin_name, subject, body_content, recipient_count) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssi", $admin_id, $admin_name, $subject, $message, $sent_count);
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
                'broadcasts'  => ['<i class="fas fa-bullhorn"></i>', 'Broadcast History', $total_broadcasts],
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
            <h3><i class="fas fa-bullhorn"></i> Broadcast History</h3>
            <button class="add-client" onclick="openBroadcastModal()">+ New Broadcast</button>
        </div>
        <div class="main-table">
            <div class="table-head" style="grid-template-columns: 2.5fr 1fr 1fr 1fr 0.5fr;">
                <span>Subject / Content</span>
                <span>Sent By</span>
                <span>Recipients</span>
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
            <div class="table-row" style="grid-template-columns: 2.5fr 1fr 1fr 1fr 0.5fr;">
                <div style="display:flex; flex-direction:column; overflow:hidden;">
                    <strong style="font-size:14px; color:#333; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($b['subject']) ?></strong>
                    <small style="color:#888; font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($preview) ?></small>
                </div>
                <span data-label="Sent By" style="font-size:13px; font-weight:600; color:var(--primary);">
                    <?= htmlspecialchars($b['admin_name']) ?>
                </span>
                <span data-label="Recipients">
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
                        <i class="fas fa-eye"></i>️
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
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Email Subject <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="subject" id="bSubject" required placeholder="e.g. New Masterclass Available!"
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;font-family:inherit;">
                    </div>

                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Message Content <span style="color:#ef4444;">*</span></label>
                        <div id="emailEditor" style="height: 250px;"></div>
                        <input type="hidden" name="message_body" id="message_body">
                    </div>

                    <div style="background:#fff4e5; padding:12px 16px; border-radius:10px; border:1px solid #fed7aa; display:flex; gap:10px; align-items:flex-start;">
                        <span style="font-size:18px;"><i class="fas fa-exclamation-triangle"></i>️</span>
                        <div>
                            <strong style="font-size:13px; color:#9a3412; display:block; margin-bottom:2px;">Warning: Bulk Action</strong>
                            <span style="font-size:12px; color:#c2410c;">Clicking send will instantly deliver this email to all <strong><?= number_format($total_subs) ?></strong> active subscribers. This action cannot be undone.</span>
                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeBroadcastModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="bSubmit"><i class="fas fa-rocket"></i> Send to <?= number_format($total_subs) ?> Subscribers</button>
            </div>
        </form>
    </div>
</div>

<div id="viewModal" class="modal-overlay" onclick="if(event.target===this)closeViewModal()">
    <div class="modal-content" style="max-width:650px;">
        <div class="modal-header">
            <h4><div class="modal-header-icon"><i class="fas fa-eye"></i>️</div> View Broadcast</h4>
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