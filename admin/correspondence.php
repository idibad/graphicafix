<?php
// Prevent PHP from crashing the page visually
error_reporting(0);
ini_set('display_errors', '0');

include('dashboard_header.php');

// ── Auto-patch contacts table safely (No fetch_all) ───────────────────────────
$cCols = array();
$cResult = @$conn->query("SHOW COLUMNS FROM contacts");
if ($cResult) {
    while ($row = $cResult->fetch_assoc()) {
        $cCols[] = $row['Field'];
    }
}
if (!in_array('is_read', $cCols)) { @$conn->query("ALTER TABLE contacts ADD COLUMN is_read TINYINT(1) DEFAULT 0"); }
if (!in_array('is_starred', $cCols)) { @$conn->query("ALTER TABLE contacts ADD COLUMN is_starred TINYINT(1) DEFAULT 0"); }

// ── Create/Patch letters table safely ─────────────────────────────────────────
@$conn->query("CREATE TABLE IF NOT EXISTS letters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    letter_type VARCHAR(50) DEFAULT 'custom',
    recipient_name VARCHAR(150), 
    recipient_company VARCHAR(150),
    recipient_email VARCHAR(200),
    recipient_address TEXT, 
    subject VARCHAR(255),
    body_html LONGTEXT, 
    footer_note VARCHAR(500),
    status VARCHAR(50) DEFAULT 'draft',
    created_by INT, 
    created_at DATETIME DEFAULT NOW(), 
    updated_at DATETIME DEFAULT NOW() ON UPDATE NOW()
)");

$lCols = array();
$lResult = @$conn->query("SHOW COLUMNS FROM letters");
if ($lResult) {
    while ($row = $lResult->fetch_assoc()) {
        $lCols[] = $row['Field'];
    }
}
if (!in_array('recipient_company', $lCols)) {
    @$conn->query("ALTER TABLE letters ADD COLUMN recipient_company VARCHAR(150) AFTER recipient_name");
}

// ══════════════════════════════════════════════════════════════════════════════
//  HANDLE POST ACTIONS
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Delete contact message
    if (isset($_POST['delete_contact'])) {
        $id = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
        if ($id) { 
            $s = @$conn->prepare("DELETE FROM contacts WHERE id=?"); 
            if($s) { $s->bind_param("i", $id); $s->execute(); }
        }
        header('Location: correspondence.php?tab=inbox&deleted=1'); exit;
    }

    // Mark read / star
    if (isset($_POST['mark_read'])) {
        $id = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0; 
        $val = isset($_POST['val']) ? intval($_POST['val']) : 1;
        if ($id) @$conn->query("UPDATE contacts SET is_read=$val WHERE id=$id");
        header('Location: correspondence.php?tab=inbox'); exit;
    }
    if (isset($_POST['mark_star'])) {
        $id = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0; 
        $val = isset($_POST['val']) ? intval($_POST['val']) : 1;
        if ($id) @$conn->query("UPDATE contacts SET is_starred=$val WHERE id=$id");
        header('Location: correspondence.php?tab=inbox'); exit;
    }

    // Add Letter
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'add_letter') {
        $title    = isset($_POST['l_title']) ? trim($_POST['l_title']) : '';
        $ltype    = isset($_POST['l_type']) ? trim($_POST['l_type']) : 'custom';
        $rname    = isset($_POST['l_rname']) ? trim($_POST['l_rname']) : '';
        $rcomp    = isset($_POST['l_rcompany']) ? trim($_POST['l_rcompany']) : '';
        $remail   = isset($_POST['l_remail']) ? trim($_POST['l_remail']) : '';
        $raddr    = isset($_POST['l_raddress']) ? trim($_POST['l_raddress']) : '';
        $subj     = isset($_POST['l_subject']) ? trim($_POST['l_subject']) : '';
        $body     = isset($_POST['l_body']) ? $_POST['l_body'] : '';
        $footer   = isset($_POST['l_footer']) ? trim($_POST['l_footer']) : '';
        $status   = isset($_POST['l_status']) ? trim($_POST['l_status']) : 'draft';
        $uid      = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        
        $allowed_types = array('offer_letter','experience_letter','noc','warning_letter','appointment','custom');
        if (!in_array($ltype, $allowed_types)) $ltype = 'custom';
        if (!$title) $title = 'Untitled Document';
        
        $s = @$conn->prepare("INSERT INTO letters (title,letter_type,recipient_name,recipient_company,recipient_email,recipient_address,subject,body_html,footer_note,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        if ($s) {
            $s->bind_param("ssssssssssi", $title, $ltype, $rname, $rcomp, $remail, $raddr, $subj, $body, $footer, $status, $uid);
            $s->execute();
        }
        
        header("Location: correspondence.php?tab=letters&letter_added=1"); exit;
    }

    // Edit Letter
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'edit_letter') {
        $lid      = isset($_POST['letter_id']) ? intval($_POST['letter_id']) : 0;
        $title    = isset($_POST['l_title']) ? trim($_POST['l_title']) : '';
        $ltype    = isset($_POST['l_type']) ? trim($_POST['l_type']) : 'custom';
        $rname    = isset($_POST['l_rname']) ? trim($_POST['l_rname']) : '';
        $rcomp    = isset($_POST['l_rcompany']) ? trim($_POST['l_rcompany']) : '';
        $remail   = isset($_POST['l_remail']) ? trim($_POST['l_remail']) : '';
        $raddr    = isset($_POST['l_raddress']) ? trim($_POST['l_raddress']) : '';
        $subj     = isset($_POST['l_subject']) ? trim($_POST['l_subject']) : '';
        $body     = isset($_POST['l_body']) ? $_POST['l_body'] : '';
        $footer   = isset($_POST['l_footer']) ? trim($_POST['l_footer']) : '';
        $status   = isset($_POST['l_status']) ? trim($_POST['l_status']) : 'draft';
        
        $allowed_types = array('offer_letter','experience_letter','noc','warning_letter','appointment','custom');
        if (!in_array($ltype, $allowed_types)) $ltype = 'custom';
        if (!$title) $title = 'Untitled Document';
        
        if ($lid) {
            $s = @$conn->prepare("UPDATE letters SET title=?, letter_type=?, recipient_name=?, recipient_company=?, recipient_email=?, recipient_address=?, subject=?, body_html=?, footer_note=?, status=?, updated_at=NOW() WHERE id=?");
            if ($s) {
                $s->bind_param("ssssssssssi", $title, $ltype, $rname, $rcomp, $remail, $raddr, $subj, $body, $footer, $status, $lid);
                $s->execute();
            }
        }
        header("Location: correspondence.php?tab=letters&updated=1"); exit;
    }

    // Delete letter
    if (isset($_POST['delete_letter'])) {
        $id = isset($_POST['letter_id']) ? intval($_POST['letter_id']) : 0;
        if ($id) { 
            $s = @$conn->prepare("DELETE FROM letters WHERE id=?"); 
            if($s) { $s->bind_param("i", $id); $s->execute(); }
        }
        header('Location: correspondence.php?tab=letters&deleted=1'); exit;
    }
}

// ── Tab state ─────────────────────────────────────────────────────────────────
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'inbox';
if (!in_array($tab, array('inbox','letters'))) $tab = 'inbox';

// ── Fetch inbox ───────────────────────────────────────────────────────────────
$contacts = @$conn->query("SELECT * FROM contacts ORDER BY is_starred DESC, created_at DESC");
$total    = $contacts ? $contacts->num_rows : 0;

$unreadRes = @$conn->query("SELECT COUNT(*) AS c FROM contacts WHERE is_read=0");
$unread    = ($unreadRes && $row = $unreadRes->fetch_assoc()) ? (int)$row['c'] : 0;

$starredRes = @$conn->query("SELECT COUNT(*) AS c FROM contacts WHERE is_starred=1");
$starred    = ($starredRes && $row = $starredRes->fetch_assoc()) ? (int)$row['c'] : 0;

// ── Fetch letters ─────────────────────────────────────────────────────────────
$letters       = @$conn->query("SELECT * FROM letters ORDER BY updated_at DESC");
$letter_total  = $letters ? $letters->num_rows : 0;

$draftsRes     = @$conn->query("SELECT COUNT(*) AS c FROM letters WHERE status='draft'");
$letter_drafts = ($draftsRes && $row = $draftsRes->fetch_assoc()) ? (int)$row['c'] : 0;

// ── Store letters for JS Modal ────────────────────────────────────────────────
$letters_js = array();
if ($letters && $letters->num_rows > 0) { 
    while($l = $letters->fetch_assoc()) {
        $letters_js[(string)$l['id']] = $l; 
    }
    $letters->data_seek(0); 
}

function cTimeAgo($d){$s=time()-strtotime($d);if($s<60)return $s."s ago";if($s<3600)return round($s/60)."m ago";if($s<86400)return round($s/3600)."h ago";if($s<604800)return round($s/86400)."d ago";return date("M j, Y",strtotime($d));}
function cAvatar($n){$c=array('pink','blue','green','orange','purple'); $char = isset($n[0]) ? strtoupper($n[0]) : 'A'; return $c[ord($char)%5];}

$type_labels = array('offer_letter'=>'Offer Letter','experience_letter'=>'Experience Letter','noc'=>'NOC','warning_letter'=>'Warning Letter','appointment'=>'Appointment','custom'=>'Custom Document');
$type_icons  = array('offer_letter'=>'<i class="fas fa-handshake"></i>','experience_letter'=>'<i class="fas fa-scroll"></i>','noc'=>'<i class="fas fa-check-circle"></i>','warning_letter'=>'<i class="fas fa-exclamation-triangle"></i>️','appointment'=>'<i class="fas fa-clipboard"></i>','custom'=>'<i class="fas fa-file-alt"></i>');
?>

<style>
/* ── Correspondence styles ── */
.corr-unread strong { color:var(--primary); }
.corr-unread .corr-row { background:#f0f7f4!important; }

.star-btn, .read-btn { background:none; border:none; cursor:pointer; font-size:1rem; padding:2px; line-height:1; transition:transform .15s; }
.star-btn:hover, .read-btn:hover { transform:scale(1.25); }

.letter-badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; }
.letter-badge.draft { background:#fef9c3; color:#a16207; }
.letter-badge.final { background:#d7f8b8; color:#2b7a2b; }

/* Quill in Modal fix */
#letterEditor { min-height: 250px; font-family: inherit; font-size: 14px; }
.ql-toolbar { border-radius: 8px 8px 0 0; background: #f9fbfc; border-color: #e0e0e0 !important; }
.ql-container { border-radius: 0 0 8px 8px; border-color: #e0e0e0 !important; background: #fff; }
</style>

<div class="height-100">

<div class="stats-row" style="margin-bottom:24px;">
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-envelope-open-text"></i></div><div class="stat-number"><?php echo $total; ?></div><div class="stat-label">Total Messages</div></div>
    <div class="stat-card"><div class="stat-icon" style="color:#ef4444;"><i class="fas fa-circle text-danger"></i></div><div class="stat-number" style="color:#ef4444;"><?php echo $unread; ?></div><div class="stat-label">Unread</div></div>
    <div class="stat-card"><div class="stat-icon" style="color:#f59e0b;"><i class="fas fa-star"></i></div><div class="stat-number" style="color:#f59e0b;"><?php echo $starred; ?></div><div class="stat-label">Starred</div></div>
    <div class="stat-card"><div class="stat-icon"><i class="fas fa-file-alt"></i></div><div class="stat-number"><?php echo $letter_total; ?></div><div class="stat-label">Letters</div></div>
    <div class="stat-card"><div class="stat-icon" style="color:#a16207;"><i class="fas fa-edit"></i></div><div class="stat-number" style="color:#a16207;"><?php echo $letter_drafts; ?></div><div class="stat-label">Drafts</div></div>
</div>

<div class="main-card">

    <div style="display:flex;border-bottom:2px solid #f0f0f0;padding:0 24px;overflow-x:auto;">
        <?php 
        $tabs = array(
            'inbox' => array('<i class="fas fa-envelope-open-text"></i>','Inbox',$total),
            'letters' => array('<i class="fas fa-file-alt"></i>','Letters & Docs',$letter_total)
        );
        foreach($tabs as $tk => $td): 
            $a = ($tab === $tk); 
        ?>
        <a href="correspondence.php?tab=<?php echo $tk; ?>"
           style="display:inline-flex;align-items:center;gap:7px;padding:14px 20px;font-size:13.5px;font-weight:700;text-decoration:none;white-space:nowrap;border-bottom:2px solid <?php echo $a?'var(--primary)':'transparent'; ?>;color:<?php echo $a?'var(--primary)':'#888'; ?>;margin-bottom:-2px;transition:color .2s;">
            <?php echo $td[0].' '.$td[1]; ?>
            <span style="background:<?php echo $a?'var(--primary)':'#f0f0f0'; ?>;color:<?php echo $a?'#fff':'#888'; ?>;padding:1px 8px;border-radius:20px;font-size:11px;font-weight:800;"><?php echo $td[2]; ?></span>
            <?php if ($tk === 'inbox' && $unread > 0): ?>
            <span style="background:#ef4444;color:#fff;padding:1px 7px;border-radius:20px;font-size:10px;font-weight:800;"><?php echo $unread; ?> new</span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($tab === 'inbox'): ?>
    <div class="main-header" style="margin-top:4px;">
        <h3><i class="fas fa-envelope-open-text"></i> Contact Messages</h3>
        <span style="font-size:13px;color:#888;"><?php echo $total; ?> message<?php echo $total!=1?'s':''; ?><?php echo $unread?' · <strong style="color:#ef4444;">'.$unread.' unread</strong>':''; ?></span>
    </div>

    <div class="main-table">
        <div class="table-head" style="grid-template-columns:.3fr 1.6fr 1.4fr 2.5fr 1fr 1fr;">
            <span></span>
            <span>Name</span>
            <span>Email</span>
            <span>Message</span>
            <span>Date</span>
            <span>Actions</span>
        </div>

        <?php if ($total > 0 && $contacts): while ($c = $contacts->fetch_assoc()):
            $short = mb_strlen($c['message']) > 100 ? mb_substr($c['message'],0,97).'…' : $c['message'];
            $fdate = date('M d, Y H:i',strtotime($c['created_at']));
            $unread_row = !$c['is_read'];
            $cName = isset($c['name']) ? $c['name'] : 'Unknown';
            $cEmail = isset($c['email']) ? $c['email'] : '';
            $cPhone = isset($c['phone']) ? $c['phone'] : '';
            $cSubject = isset($c['subject']) ? $c['subject'] : '';
        ?>
        <div class="table-row <?php echo $unread_row?'corr-unread':''; ?>" style="grid-template-columns:.3fr 1.6fr 1.4fr 2.5fr 1fr 1fr;">

            <div style="display:flex;flex-direction:column;gap:3px;align-items:center;">
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="mark_star"   value="1">
                    <input type="hidden" name="contact_id"  value="<?php echo $c['id']; ?>">
                    <input type="hidden" name="val"         value="<?php echo $c['is_starred']?0:1; ?>">
                    <button type="submit" class="star-btn" title="<?php echo $c['is_starred']?'Unstar':'Star'; ?>">
                        <?php echo $c['is_starred'] ? '<i class="fas fa-star"></i>' : '<i class="fas fa-star-o"></i>'; ?>
                    </button>
                </form>
                <?php if ($unread_row): ?>
                <span style="width:7px;height:7px;background:#ef4444;border-radius:50%;display:inline-block;" title="Unread"></span>
                <?php endif; ?>
            </div>

            <div class="client">
                <div class="avatar <?php echo cAvatar($cName); ?>"><?php echo strtoupper($cName[0]); ?></div>
                <div>
                    <strong style="<?php echo $unread_row?'color:var(--primary);':''; ?>"><?php echo htmlspecialchars($cName); ?></strong>
                </div>
            </div>

            <span data-label="Email" style="font-size:13.5px;color:#666;"><?php echo htmlspecialchars($cEmail); ?></span>

            <span data-label="Message" style="font-size:13.5px;color:#777;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;">
                <?php echo htmlspecialchars($short); ?>
            </span>

            <span data-label="Date" style="font-size:13px;color:#888;"><?php echo cTimeAgo($c['created_at']); ?></span>

            <div style="display:flex;align-items:center;gap:3px;">

                <button class="dots view-contact" title="View"
                    data-id="<?php echo $c['id']; ?>"
                    data-name="<?php echo htmlspecialchars($cName); ?>"
                    data-email="<?php echo htmlspecialchars($cEmail); ?>"
                    data-phone="<?php echo htmlspecialchars($cPhone); ?>"
                    data-subject="<?php echo htmlspecialchars($cSubject); ?>"
                    data-message="<?php echo htmlspecialchars($c['message']); ?>"
                    data-read="<?php echo $c['is_read']; ?>"
                    data-date="<?php echo $fdate; ?>"><i class="fas fa-eye"></i>️</button>

                <a class="dots" href="mailto:<?php echo htmlspecialchars($cEmail); ?>" title="Reply by email" style="text-decoration:none;"><i class="fas fa-envelope"></i>️</a>

                <form method="POST" style="display:inline;">
                    <input type="hidden" name="mark_read"  value="1">
                    <input type="hidden" name="contact_id" value="<?php echo $c['id']; ?>">
                    <input type="hidden" name="val"         value="<?php echo $c['is_read']?0:1; ?>">
                    <button type="submit" class="dots read-btn" title="<?php echo $c['is_read']?'Mark unread':'Mark read'; ?>">
                        <?php echo $c['is_read'] ? '<i class="fas fa-book-open"></i>' : '📬'; ?>
                    </button>
                </form>

                <button class="dots" title="Write a document" onclick="openLetterModal('add', null, '<?php echo htmlspecialchars(addslashes($cName)); ?>', '', '<?php echo htmlspecialchars(addslashes($cEmail)); ?>')"><i class="fas fa-edit"></i></button>

                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this message?');">
                    <input type="hidden" name="delete_contact" value="1">
                    <input type="hidden" name="contact_id"     value="<?php echo $c['id']; ?>">
                    <button type="submit" class="dots" title="Delete" style="color:#ef4444;"><i class="fas fa-trash"></i>️</button>
                </form>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div style="text-align:center;padding:60px 24px;color:#888;">
            <div style="font-size:2.5rem;margin-bottom:8px;"><i class="fas fa-envelope-open"></i></div>
            No messages yet.
        </div>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <div class="main-header" style="margin-top:4px;">
        <h3><i class="fas fa-file-alt"></i> Letters & Documents</h3>
        <button class="add-client" onclick="openLetterModal('add')">+ Create Document</button>
    </div>

    <div class="main-table">
        <div class="table-head" style="grid-template-columns:2fr 1.5fr 1fr 1fr 1fr 1.5fr;">
            <span>Title</span>
            <span>Recipient</span>
            <span>Type</span>
            <span>Status</span>
            <span>Last Updated</span>
            <span>Actions</span>
        </div>

        <?php if ($letter_total > 0 && $letters): while ($l = $letters->fetch_assoc()): 
            $lType = isset($l['letter_type']) ? $l['letter_type'] : 'custom';
            $icon = isset($type_icons[$lType]) ? $type_icons[$lType] : '<i class="fas fa-file-alt"></i>';
            $label = isset($type_labels[$lType]) ? $type_labels[$lType] : 'Custom';
        ?>
        <div class="table-row" style="grid-template-columns:2fr 1.5fr 1fr 1fr 1fr 1.5fr;">
            
            <div class="client">
                <div class="avatar blue" style="font-size: 1rem; background: #e0f2fe; color: #0284c7;">
                    <?php echo $icon; ?>
                </div>
                <div>
                    <strong style="font-size:13.5px;"><?php echo htmlspecialchars($l['title']); ?></strong>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; justify-content: center;">
                <span data-label="Recipient" style="font-size:13px;color:#333;font-weight:600;">
                    <?php echo !empty($l['recipient_name']) ? htmlspecialchars($l['recipient_name']) : '<i style="color:#aaa; font-weight: 400;">Unassigned</i>'; ?>
                </span>
                <?php if (!empty($l['recipient_company'])): ?>
                <span style="font-size: 11px; color: #888; margin-top: 2px;">
                    <?php echo htmlspecialchars($l['recipient_company']); ?>
                </span>
                <?php endif; ?>
            </div>

            <span data-label="Type" style="font-size:12.5px;color:#666;">
                <?php echo $label; ?>
            </span>

            <span data-label="Status">
                <span class="letter-badge <?php echo htmlspecialchars($l['status']); ?>">
                    <?php echo ucfirst(htmlspecialchars($l['status'])); ?>
                </span>
            </span>

            <span data-label="Date" style="font-size:12px;color:#888;"><?php echo cTimeAgo($l['updated_at']); ?></span>

            <div style="display:flex;align-items:center;gap:3px;">
                <a class="dots" title="Preview PDF" href="generate_letter_pdf.php?id=<?php echo $l['id']; ?>" target="_blank" style="text-decoration:none;"><i class="fas fa-eye"></i>️</a>
                
                <a class="dots" title="Download PDF" href="generate_letter_pdf.php?id=<?php echo $l['id']; ?>&dl=1" style="text-decoration:none; color:#2b7a2b;">⬇️</a>
                
                <button class="dots" title="Edit" onclick="openLetterModal('edit', '<?php echo $l['id']; ?>')"><i class="fas fa-pencil-alt"></i>️</button>
                
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this document permanently?');">
                    <input type="hidden" name="delete_letter" value="1">
                    <input type="hidden" name="letter_id"     value="<?php echo $l['id']; ?>">
                    <button type="submit" class="dots" title="Delete" style="color:#ef4444;"><i class="fas fa-trash"></i>️</button>
                </form>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div style="text-align:center;padding:60px 24px;color:#888;">
            <div style="font-size:2.5rem;margin-bottom:8px;"><i class="fas fa-file-alt"></i></div>
            No documents found. <a href="#" onclick="openLetterModal('add');return false;" style="color:var(--primary);font-weight:700;">Create one →</a>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>

</div></div>

<div id="contactModal" class="modal-overlay" onclick="if(event.target===this)closeContactModal()">
    <div class="modal-content">
        <div class="modal-header">
            <h4><div class="modal-header-icon"><i class="fas fa-envelope-open-text"></i></div><span>Message Details</span></h4>
            <button class="modal-close" onclick="closeContactModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="info-grid">
                <div class="info-row"><div class="info-icon"><i class="fas fa-user"></i></div><div class="info-content"><div class="info-label">From</div><div class="info-value" id="mName"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-envelope"></i>️</div><div class="info-content"><div class="info-label">Email</div><div class="info-value" id="mEmail"></div></div></div>
                <div class="info-row" id="mPhoneRow" style="display:none;"><div class="info-icon"><i class="fas fa-phone"></i></div><div class="info-content"><div class="info-label">Phone</div><div class="info-value" id="mPhone"></div></div></div>
                <div class="info-row" id="mSubjectRow" style="display:none;"><div class="info-icon"><i class="fas fa-comment"></i></div><div class="info-content"><div class="info-label">Subject</div><div class="info-value" id="mSubject"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-calendar-alt"></i></div><div class="info-content"><div class="info-label">Received</div><div class="info-value" id="mDate"></div></div></div>
            </div>
            <div class="message-box">
                <span class="message-label"><i class="fas fa-comment"></i> Message</span>
                <div class="message-content" id="mMessage"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary-custom" onclick="closeContactModal()">Close</button>
            <a href="#" id="mReplyLink" class="btn btn-primary-custom"><i class="fas fa-envelope"></i>️ Reply via Email</a>
        </div>
    </div>
</div>

<div id="letterModal" class="modal-overlay" onclick="if(event.target===this)closeLetterModal()">
    <div class="modal-content" style="max-width: 800px; width: 90%;">
        <div class="modal-header">
            <h4 id="lModalTitle">Add Document</h4>
            <button type="button" class="modal-close" onclick="closeLetterModal()">×</button>
        </div>
        <form method="POST" id="letterForm">
            
            <input type="hidden" name="action_type" id="lAction" value="add_letter">
            <input type="hidden" name="letter_id"   id="lId" value="">
            
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <div class="info-grid">
                    
                    <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;">
                        <div class="form-group">
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Document Title <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="l_title" id="lTitleInput" required placeholder="Document Title"
                                   style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;">
                        </div>
                        <div class="form-group">
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Type</label>
                            <select name="l_type" id="lTypeInput" style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;">
                                <?php foreach ($type_labels as $v=>$lbl): ?>
                                <option value="<?php echo $v; ?>"><?php echo $lbl; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px;">
                        <div class="form-group">
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Name</label>
                            <input type="text" name="l_rname" id="lRnameInput" placeholder="Name"
                                   style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;">
                        </div>
                        <div class="form-group">
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Company / ID</label>
                            <input type="text" name="l_rcompany" id="lRcompanyInput" placeholder="Company / ID"
                                   style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;">
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px;">
                        <div class="form-group">
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Email</label>
                            <input type="email" name="l_remail" id="lRemailInput" placeholder="Email"
                                   style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;">
                        </div>
                        <div class="form-group">
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Address</label>
                            <input type="text" name="l_raddress" id="lRaddressInput" placeholder="Address"
                                   style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:10px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Subject Line</label>
                        <input type="text" name="l_subject" id="lSubjectInput" placeholder="Subject Line"
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;">
                    </div>

                    <div class="form-group" style="margin-top:15px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Document Content</label>
                        <div id="letterEditor"></div>
                        <input type="hidden" name="l_body" id="lBodyInput">
                    </div>

                    <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-top:15px;">
                        <div class="form-group">
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Footer Note</label>
                            <input type="text" name="l_footer" id="lFooterInput" placeholder="Footer Note"
                                   style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;">
                        </div>
                        <div class="form-group">
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Save Status</label>
                            <select name="l_status" id="lStatusInput" style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#fef9c3;color:#a16207;outline:none;font-weight:700;">
                                <option value="draft">Draft</option>
                                <option value="final">Final</option>
                            </select>
                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer" style="justify-content: space-between;">
                <button type="button" class="btn btn-secondary-custom" onclick="closeLetterModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="lSubmitBtn"><i class="fas fa-save"></i> Save Document</button>
            </div>
        </form>
    </div>
</div>

<link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>

<script>
// ── Safely parse letters data with strict JSON Hex tags ───────────────────────
const lettersData = <?php echo json_encode($letters_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
let letterEditor = null;

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Quill for Letters
    if (document.getElementById('letterEditor')) {
        letterEditor = new Quill('#letterEditor', {
            modules: {
                toolbar: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ align: [] }],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['clean'],
                ],
            },
            theme: 'snow',
            placeholder: 'Document Content...',
        });
    }

    // Explicitly guarantee the editor contents are dumped into the hidden input before form submits
    document.getElementById('letterForm').addEventListener('submit', function(e) {
        if (letterEditor) {
            document.getElementById('lBodyInput').value = letterEditor.root.innerHTML;
        }
    });
});

// ── Contact Modal Logic ───────────────────────────────────────────────────────
document.querySelectorAll('.view-contact').forEach(btn => {
    btn.addEventListener('click', function () {
        const d = this.dataset;
        document.getElementById('mName').textContent    = d.name;
        document.getElementById('mDate').textContent    = d.date;
        document.getElementById('mMessage').textContent = d.message;
        document.getElementById('mEmail').innerHTML     = `<a href="mailto:${d.email}" style="color:var(--primary);">${d.email}</a>`;
        document.getElementById('mReplyLink').href      = 'mailto:' + d.email;

        const phoneRow = document.getElementById('mPhoneRow');
        if (d.phone) { phoneRow.style.display=''; document.getElementById('mPhone').textContent=d.phone; }
        else phoneRow.style.display='none';

        const subjectRow = document.getElementById('mSubjectRow');
        if (d.subject) { subjectRow.style.display=''; document.getElementById('mSubject').textContent=d.subject; }
        else subjectRow.style.display='none';

        document.getElementById('contactModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    });
});

function closeContactModal() {
    document.getElementById('contactModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ── Letter Modal Logic ────────────────────────────────────────────────────────
function openLetterModal(mode, id = null, prefillName = '', prefillCompany = '', prefillEmail = '') {
    const isEdit = mode === 'edit';
    const sid = id ? String(id) : '';

    document.getElementById('lModalTitle').textContent = isEdit ? 'Edit Document' : 'Add Document';
    document.getElementById('lSubmitBtn').textContent  = isEdit ? '<i class="fas fa-save"></i> Update Document' : '<i class="fas fa-save"></i> Save Document';
    
    document.getElementById('lAction').value = isEdit ? 'edit_letter' : 'add_letter';
    document.getElementById('lId').value = sid;

    if (isEdit && lettersData[sid]) {
        const l = lettersData[sid];
        document.getElementById('lTitleInput').value    = l.title || '';
        document.getElementById('lTypeInput').value     = l.letter_type || 'custom';
        document.getElementById('lRnameInput').value    = l.recipient_name || '';
        document.getElementById('lRcompanyInput').value = l.recipient_company || '';
        document.getElementById('lRemailInput').value   = l.recipient_email || '';
        document.getElementById('lSubjectInput').value  = l.subject || '';
        document.getElementById('lRaddressInput').value = l.recipient_address || '';
        document.getElementById('lFooterInput').value   = l.footer_note || '';
        
        const statusEl = document.getElementById('lStatusInput');
        statusEl.value = l.status || 'draft';
        statusEl.style.background = l.status === 'final' ? '#d7f8b8' : '#fef9c3';
        statusEl.style.color = l.status === 'final' ? '#2b7a2b' : '#a16207';

        if (letterEditor) {
            // Direct and 100% reliable way to force HTML into Quill 
            letterEditor.root.innerHTML = l.body_html || '';
            document.getElementById('lBodyInput').value = l.body_html || '';
        }
    } else {
        document.getElementById('letterForm').reset();
        document.getElementById('lRnameInput').value = prefillName;
        document.getElementById('lRcompanyInput').value = prefillCompany;
        document.getElementById('lRemailInput').value = prefillEmail;
        
        const statusEl = document.getElementById('lStatusInput');
        statusEl.value = 'draft';
        statusEl.style.background = '#fef9c3';
        statusEl.style.color = '#a16207';

        if (letterEditor) {
            letterEditor.root.innerHTML = '';
            document.getElementById('lBodyInput').value = '';
        }
    }

    // Dynamic color for status dropdown
    document.getElementById('lStatusInput').addEventListener('change', function() {
        this.style.background = this.value === 'final' ? '#d7f8b8' : '#fef9c3';
        this.style.color = this.value === 'final' ? '#2b7a2b' : '#a16207';
    });

    document.getElementById('letterModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLetterModal() {
    document.getElementById('letterModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ── Global Handlers ───────────────────────────────────────────────────────────
document.addEventListener('keydown', e => { 
    if (e.key === 'Escape') {
        closeContactModal();
        closeLetterModal();
    } 
});

function showToast(msg, type='success') {
    const color = type==='success' ? '#10b981' : '#ef4444';
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;bottom:24px;right:24px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:200px;border-left:4px solid ${color};z-index:9999;`;
    t.innerHTML = `<span style="font-weight:700;color:${color}">${type==='success'?'<i class="fas fa-check"></i>':'<i class="fas fa-times"></i>'}</span><span>${msg}</span>`;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3500);
}

document.addEventListener('DOMContentLoaded', () => {
    <?php if(isset($_GET['deleted'])):      ?> showToast('Deleted.');               <?php endif; ?>
    <?php if(isset($_GET['letter_added'])): ?> showToast('Document created!');      <?php endif; ?>
    <?php if(isset($_GET['updated'])):      ?> showToast('Document updated!');      <?php endif; ?>
});
</script>

<?php include('dashboard_footer.php'); ?>