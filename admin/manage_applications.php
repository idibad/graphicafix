<?php
// Hide errors to prevent 500 crashes
error_reporting(0);
ini_set('display_errors', '0');

include 'dashboard_header.php';

if ($role !== 'admin' && $role !== 'hrm') {
    header("Location: index.php?error=unauthorized");
    exit;
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'ms-applications';

// ── Handle actions ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine which tab to redirect back to based on the action
    $redirect_tab = isset($_POST['add_position']) || isset($_POST['edit_position']) || isset($_POST['toggle_position']) || isset($_POST['delete_position']) ? 'ms-positions' : 'ms-applications';

    // Update application status
    if (isset($_POST['update_status'])) {
        $aid     = isset($_POST['app_id']) ? intval($_POST['app_id']) : 0;
        $status  = isset($_POST['status']) ? $_POST['status'] : '';
        $allowed = ['new','reviewing','shortlisted','rejected'];
        if ($aid && in_array($status, $allowed)) {
            $stmt = $conn->prepare("UPDATE career_applications SET status=? WHERE id=?");
            $stmt->bind_param("si", $status, $aid);
            $stmt->execute();
        }
        header("Location: manage_applications.php?updated=1&tab=" . $redirect_tab); exit;
    }

    // Delete application
    if (isset($_POST['delete_app'])) {
        $aid = isset($_POST['app_id']) ? intval($_POST['app_id']) : 0;
        if ($aid) {
            $stmt = $conn->prepare("DELETE FROM career_applications WHERE id=?");
            $stmt->bind_param("i", $aid);
            $stmt->execute();
        }
        header("Location: manage_applications.php?deleted=1&tab=" . $redirect_tab); exit;
    }

    // Add position
    if (isset($_POST['add_position'])) {
        $pos   = trim($_POST['pos_title']        ?? '');
        $dept  = trim($_POST['pos_department']   ?? '');
        $loc   = trim($_POST['pos_location']     ?? '');
        $type  = trim($_POST['pos_type']         ?? '');
        $sal   = trim($_POST['pos_salary']       ?? '');
        $desc  = trim($_POST['pos_description']  ?? '');
        $req   = trim($_POST['pos_requirements'] ?? '');
        if ($pos) {
            $stmt = $conn->prepare("INSERT INTO career_positions (position, department, location, job_type, salary_range, description, requirements, status) VALUES (?,?,?,?,?,?,?,'open')");
            $stmt->bind_param("sssssss", $pos, $dept, $loc, $type, $sal, $desc, $req);
            $stmt->execute();
        }
        header("Location: manage_applications.php?pos_added=1&tab=" . $redirect_tab); exit;
    }

    // Edit position
    if (isset($_POST['edit_position'])) {
        $pid   = isset($_POST['pos_id']) ? intval($_POST['pos_id']) : 0;
        $pos   = trim($_POST['pos_title']        ?? '');
        $dept  = trim($_POST['pos_department']   ?? '');
        $loc   = trim($_POST['pos_location']     ?? '');
        $type  = trim($_POST['pos_type']         ?? '');
        $sal   = trim($_POST['pos_salary']       ?? '');
        $desc  = trim($_POST['pos_description']  ?? '');
        $req   = trim($_POST['pos_requirements'] ?? '');
        $stat  = trim($_POST['pos_status']       ?? 'open');
        if ($pid && $pos) {
            $stmt = $conn->prepare("UPDATE career_positions SET position=?,department=?,location=?,job_type=?,salary_range=?,description=?,requirements=?,status=? WHERE id=?");
            $stmt->bind_param("ssssssssi", $pos, $dept, $loc, $type, $sal, $desc, $req, $stat, $pid);
            $stmt->execute();
        }
        header("Location: manage_applications.php?pos_updated=1&tab=" . $redirect_tab); exit;
    }

    // Toggle position status
    if (isset($_POST['toggle_position'])) {
        $pid    = isset($_POST['pos_id']) ? intval($_POST['pos_id']) : 0;
        $status = isset($_POST['pos_status']) ? $_POST['pos_status'] : 'open';
        $new    = $status === 'open' ? 'closed' : 'open';
        if ($pid) $conn->query("UPDATE career_positions SET status='$new' WHERE id=$pid");
        header("Location: manage_applications.php?pos_updated=1&tab=" . $redirect_tab); exit;
    }

    // Delete position
    if (isset($_POST['delete_position'])) {
        $pid = isset($_POST['pos_id']) ? intval($_POST['pos_id']) : 0;
        if ($pid) $conn->query("DELETE FROM career_positions WHERE id=$pid");
        header("Location: manage_applications.php?pos_deleted=1&tab=" . $redirect_tab); exit;
    }
}

// ── Fetch data ────────────────────────────────────────────────────────────────
$apps      = $conn->query("SELECT * FROM career_applications ORDER BY applied_at DESC");
$total     = $apps ? $apps->num_rows : 0;
$positions = $conn->query("SELECT * FROM career_positions ORDER BY status ASC, created_at DESC");
$total_pos = $positions ? $positions->num_rows : 0;

$new_res = $conn->query("SELECT COUNT(*) AS c FROM career_applications WHERE status='new'");
$total_new = $new_res ? $new_res->fetch_assoc()['c'] : 0;

$short_res = $conn->query("SELECT COUNT(*) AS c FROM career_applications WHERE status='shortlisted'");
$total_shortlisted = $short_res ? $short_res->fetch_assoc()['c'] : 0;

$rej_res = $conn->query("SELECT COUNT(*) AS c FROM career_applications WHERE status='rejected'");
$total_rejected = $rej_res ? $rej_res->fetch_assoc()['c'] : 0;

$pos_res = $conn->query("SELECT COUNT(*) AS c FROM career_positions WHERE status='open'");
$total_open_pos = $pos_res ? $pos_res->fetch_assoc()['c'] : 0;

// Encode positions for JS modal
$pos_js = [];
if ($positions && $total_pos > 0) {
    while ($p = $positions->fetch_assoc()) $pos_js[$p['id']] = $p;
    $positions->data_seek(0);
}

function appAvatar($name) {
    $colors = ['pink','blue','green','orange','purple'];
    $char = isset($name[0]) ? strtoupper($name[0]) : 'A';
    return $colors[ord($char) % count($colors)];
}
function appTimeAgo($dt) {
    $diff = time() - strtotime($dt);
    if ($diff < 60)         return $diff."s ago";
    elseif ($diff < 3600)   return round($diff/60)."m ago";
    elseif ($diff < 86400)  return round($diff/3600)."h ago";
    elseif ($diff < 604800) return round($diff/86400)."d ago";
    return date("M j, Y", strtotime($dt));
}
function appStatusBadge($s) {
    $status = $s ? $s : 'new';
    switch($status) {
        case 'new':         return ['label'=>'New',         'bg'=>'#e0f2fe','color'=>'#0284c7'];
        case 'reviewing':   return ['label'=>'Reviewing',   'bg'=>'#fef9c3','color'=>'#a16207'];
        case 'shortlisted': return ['label'=>'Shortlisted', 'bg'=>'#d7f8b8','color'=>'#2b7a2b'];
        case 'rejected':    return ['label'=>'Rejected',    'bg'=>'#ffe5e5','color'=>'#d63031'];
        default:            return ['label'=>ucfirst($s),   'bg'=>'#f0f0f0','color'=>'#555'];
    }
}
?>

<style>
/* ── Tab & Stats Styling ── */
.manage-services-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 32px; gap: 20px; flex-wrap: wrap;
}
.header-content h1 { font-size: 32px; font-weight: 700; color: var(--dark); margin-bottom: 6px; }
.header-content p { color: var(--gray-600); font-size: 15px; margin: 0; }
.header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.btn {
    padding: 11px 20px; border: none; border-radius: var(--radius); font-size: 14px;
    font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex;
    align-items: center; gap: 8px; white-space: nowrap; text-decoration: none;
}
.btn-primary { background: var(--primary); color: white; }
.btn-primary:hover { background: #035b58; transform: translateY(-1px); box-shadow: var(--shadow-md); }

.stats-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px; margin-bottom: 32px;
}
.stat-card {
    background: white; padding: 24px; border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); transition: all 0.3s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.stat-label { font-size: 14px; color: var(--gray-600); font-weight: 500; }
.stat-icon {
    width: 40px; height: 40px; border-radius: var(--radius); display: flex;
    align-items: center; justify-content: center; font-size: 20px;
}
.stat-value { font-size: 32px; font-weight: 700; color: var(--dark); margin-bottom: 0; }

.ms-tabs-container {
    background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
    border: 1px solid var(--gray-200); margin-bottom: 24px; overflow: hidden;
}
.ms-tabs { display: flex; border-bottom: 1px solid var(--gray-200); overflow-x: auto; scrollbar-width: none; }
.ms-tabs::-webkit-scrollbar { display: none; }
.ms-tab {
    padding: 16px 24px; background: none; border: none; width: 50%; color: var(--gray-600);
    font-size: 14px; font-weight: 500; cursor: pointer; border-bottom: 3px solid transparent;
    transition: all 0.2s; white-space: nowrap; text-align: center; outline: none;
}
.ms-tab:hover { color: var(--primary); background: var(--gray-50); }
.ms-tab.active { color: var(--primary); border-bottom-color: var(--accent); background: var(--light); }
.ms-tab-badge {
    display: inline-block; background: var(--gray-200); color: var(--gray-700);
    padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 8px;
}
.ms-tab.active .ms-tab-badge { background: var(--accent); color: var(--dark); }
.ms-tab-content { padding: 0; /* Let main-table handle padding */ }

/* Table Overrides for Tabs */
.ms-tab-content .main-header { display: none; } /* Hide internal table headers */
.ms-tab-content .main-card { box-shadow: none; border: none; margin-bottom: 0; border-radius: 0; }
</style>

<div class="height-100">

    <div class="manage-services-header">
        <div class="header-content">
            <h1>Career Management</h1>
            <p>Manage job applications and open positions</p>
        </div>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="openPosModal('add')">
                <span class="btn-icon">+</span>
                Announce Position
            </button>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total Applications</span>
                <div class="stat-icon" style="background:#f3f4f6; color:#4b5563;"><i class="fas fa-file-alt"></i></div>
            </div>
            <div class="stat-value"><?php echo $total; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">New</span>
                <div class="stat-icon" style="background:#e0f2fe; color:#0284c7;">🆕</div>
            </div>
            <div class="stat-value"><?php echo $total_new; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Shortlisted</span>
                <div class="stat-icon" style="background:#dcfce7; color:#16a34a;"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="stat-value"><?php echo $total_shortlisted; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Open Positions</span>
                <div class="stat-icon" style="background:#f3e8ff; color:#9333ea;"><i class="fas fa-clipboard"></i></div>
            </div>
            <div class="stat-value"><?php echo $total_open_pos; ?></div>
        </div>
    </div>

    <div class="ms-tabs-container">
        
        <div class="ms-tabs">
            <button class="ms-tab <?php echo ($active_tab === 'ms-applications') ? 'active' : ''; ?>" onclick="switchTab(event, 'ms-applications')">
                Applications
                <span class="ms-tab-badge"><?php echo $total; ?></span>
            </button>

            <button class="ms-tab <?php echo ($active_tab === 'ms-positions') ? 'active' : ''; ?>" onclick="switchTab(event, 'ms-positions')">
                Positions
                <span class="ms-tab-badge"><?php echo $total_pos; ?></span>
            </button>
        </div>

        <div class="ms-tab-contents">
            
            <div id="ms-applications" class="ms-tab-content <?php echo ($active_tab === 'ms-applications') ? 'active' : ''; ?>" style="display: <?php echo ($active_tab === 'ms-applications') ? 'block' : 'none'; ?>;">
                <div class="main-card">
                    <div class="main-table">
                        <div class="table-head" style="grid-template-columns:2fr 1.5fr 1fr 1fr 1fr;">
                            <span>Applicant</span>
                            <span>Position</span>
                            <span>Status</span>
                            <span>Applied</span>
                            <span>Actions</span>
                        </div>

                        <?php if ($total > 0): while ($app = $apps->fetch_assoc()):
                            $badge = appStatusBadge(isset($app['status']) ? $app['status'] : 'new');
                            $name = isset($app['name']) ? $app['name'] : 'Unknown';
                            $email = isset($app['email']) ? $app['email'] : '';
                            $position = isset($app['position']) ? $app['position'] : '';
                            $date = isset($app['applied_at']) ? $app['applied_at'] : '';
                            $cv = isset($app['cv']) ? $app['cv'] : '';
                        ?>
                        <div class="table-row" style="grid-template-columns:2fr 1.5fr 1fr 1fr 1fr;">
                            <div class="client">
                                <div class="avatar <?php echo appAvatar($name); ?>"><?php echo strtoupper($name[0]); ?></div>
                                <div>
                                    <strong><?php echo htmlspecialchars($name); ?></strong>
                                    <small><?php echo htmlspecialchars($email); ?></small>
                                </div>
                            </div>
                            <span data-label="Position" style="font-size:13.5px;color:#666;"><?php echo htmlspecialchars($position); ?></span>
                            <span>
                                <span style="background:<?php echo $badge['bg']; ?>;color:<?php echo $badge['color']; ?>;padding:4px 12px;border-radius:20px;font-size:11.5px;font-weight:600;white-space:nowrap;">
                                    <?php echo $badge['label']; ?>
                                </span>
                            </span>
                            <span data-label="Applied" style="font-size:13px;color:#888;"><?php echo appTimeAgo($date); ?></span>
                            <div style="display:flex;align-items:center;gap:4px;">
                                <button class="dots view-app" title="View"
                                    data-id="<?php echo $app['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($name); ?>"
                                    data-email="<?php echo htmlspecialchars($email); ?>"
                                    data-position="<?php echo htmlspecialchars($position); ?>"
                                    data-status="<?php echo htmlspecialchars(isset($app['status']) ? $app['status'] : 'new'); ?>"
                                    data-date="<?php echo date('M j, Y g:i A', strtotime($date)); ?>"
                                    data-cv="<?php echo htmlspecialchars($cv); ?>"><i class="fas fa-eye"></i>️</button>
                                <a class="dots" href="mailto:<?php echo htmlspecialchars($email); ?>?subject=Re: your application for <?php echo urlencode($position); ?>" style="text-decoration:none;" title="Reply"><i class="fas fa-envelope"></i>️</a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="app_id"        value="<?php echo $app['id']; ?>">
                                    <input type="hidden" name="status"         value="shortlisted">
                                    <button type="submit" class="dots" title="Shortlist" style="color:#2b7a2b;"><i class="fas fa-check-circle"></i></button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this application?');">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="app_id"        value="<?php echo $app['id']; ?>">
                                    <input type="hidden" name="status"         value="rejected">
                                    <button type="submit" class="dots" title="Reject" style="color:#e17055;"><i class="fas fa-ban"></i></button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete permanently?');">
                                    <input type="hidden" name="delete_app" value="1">
                                    <input type="hidden" name="app_id"     value="<?php echo $app['id']; ?>">
                                    <button type="submit" class="dots" title="Delete" style="color:#ef4444;"><i class="fas fa-trash"></i>️</button>
                                </form>
                            </div>
                        </div>
                        <?php endwhile; else: ?>
                        <div style="text-align:center;padding:60px 24px;color:#888;">
                            <div style="font-size:2.5rem;margin-bottom:8px;"><i class="fas fa-envelope-open"></i></div>
                            No applications yet.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="ms-positions" class="ms-tab-content <?php echo ($active_tab === 'ms-positions') ? 'active' : ''; ?>" style="display: <?php echo ($active_tab === 'ms-positions') ? 'block' : 'none'; ?>;">
                <div class="main-card">
                    <div class="main-table">
                        <div class="table-head" style="grid-template-columns:2fr 1.2fr 1fr 1fr 0.8fr 1.2fr;">
                            <span>Position</span>
                            <span>Department</span>
                            <span>Location</span>
                            <span>Type</span>
                            <span>Status</span>
                            <span>Actions</span>
                        </div>

                        <?php if ($total_pos > 0): while ($pos = $positions->fetch_assoc()): 
                            $status = isset($pos['status']) ? $pos['status'] : 'open';
                        ?>
                        <div class="table-row" style="grid-template-columns:2fr 1.2fr 1fr 1fr 0.8fr 1.2fr;">
                            <div class="client">
                                <div class="avatar <?php echo $status === 'open' ? 'green' : 'orange'; ?>" style="font-size:1rem;">
                                    <?php echo $status === 'open' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-lock"></i>'; ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars(isset($pos['position']) ? $pos['position'] : 'Position'); ?></strong>
                                    <?php if (!empty($pos['salary_range'])): ?>
                                    <small><?php echo htmlspecialchars($pos['salary_range']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span data-label="Department" style="font-size:13.5px;color:#666;"><?php echo htmlspecialchars(isset($pos['department']) ? $pos['department'] : '—'); ?></span>
                            <span data-label="Location" style="font-size:13.5px;color:#666;"><?php echo htmlspecialchars(isset($pos['location']) ? $pos['location'] : '—'); ?></span>
                            <span data-label="Type" style="font-size:13px;">
                                <?php if (!empty($pos['job_type'])): ?>
                                <span class="status <?php echo strtolower($status)==='open'?'active':'inactive'; ?>" style="font-size:11px;">
                                    <?php echo htmlspecialchars($pos['job_type']); ?>
                                </span>
                                <?php else: echo '—'; endif; ?>
                            </span>
                            <span data-label="Status">
                                <span class="status <?php echo $status==='open'?'active':'inactive'; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </span>
                            <div style="display:flex;align-items:center;gap:4px;">
                                <button class="dots" title="View details" onclick="openPosViewModal(<?php echo $pos['id']; ?>)"><i class="fas fa-eye"></i>️</button>
                                <button class="dots" title="Edit" onclick="openPosModal('edit', <?php echo $pos['id']; ?>)"><i class="fas fa-pencil-alt"></i>️</button>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="toggle_position" value="1">
                                    <input type="hidden" name="pos_id"          value="<?php echo $pos['id']; ?>">
                                    <input type="hidden" name="pos_status"      value="<?php echo $status; ?>">
                                    <button type="submit" class="dots" title="<?php echo $status==='open'?'Close position':'Reopen position'; ?>">
                                        <?php echo $status==='open' ? '<i class="fas fa-lock"></i>' : '<i class="fas fa-unlock"></i>'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this position?');">
                                    <input type="hidden" name="delete_position" value="1">
                                    <input type="hidden" name="pos_id"          value="<?php echo $pos['id']; ?>">
                                    <button type="submit" class="dots" title="Delete" style="color:#ef4444;"><i class="fas fa-trash"></i>️</button>
                                </form>
                            </div>
                        </div>
                        <?php endwhile; else: ?>
                        <div style="text-align:center;padding:48px 24px;color:#888;">
                            <div style="font-size:2rem;margin-bottom:8px;"><i class="fas fa-clipboard"></i></div>
                            No positions yet. <a href="#" onclick="openPosModal('add');return false;">Announce one →</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

<div id="appModal" class="modal-overlay" onclick="if(event.target===this)closeAppModal()">
    <div class="modal-content">
        <div class="modal-header">
            <h4><div class="modal-header-icon"><i class="fas fa-file-alt"></i></div><span>Application Details</span></h4>
            <button class="modal-close" onclick="closeAppModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="info-grid">
                <div class="info-row"><div class="info-icon"><i class="fas fa-user"></i></div><div class="info-content"><div class="info-label">Full Name</div><div class="info-value" id="aName"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-envelope"></i>️</div><div class="info-content"><div class="info-label">Email</div><div class="info-value" id="aEmail"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-briefcase"></i></div><div class="info-content"><div class="info-label">Position</div><div class="info-value" id="aPosition"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-calendar-alt"></i></div><div class="info-content"><div class="info-label">Applied On</div><div class="info-value" id="aDate"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-tag"></i>️</div><div class="info-content"><div class="info-label">Status</div><div class="info-value" id="aStatus"></div></div></div>
            </div>
            <div class="message-box"><span class="message-label"><i class="fas fa-paperclip"></i> CV</span><div class="message-content" id="aCVWrap"></div></div>
            <div style="margin-top:20px;padding:16px;background:#f9fbfc;border-radius:12px;">
                <div style="font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">Update Status</div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;" id="statusBtns"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary-custom" onclick="closeAppModal()">Close</button>
            <a id="aReplyLink" href="#" class="btn btn-primary-custom"><i class="fas fa-envelope"></i>️ Reply via Email</a>
        </div>
    </div>
</div>

<div id="posModal" class="modal-overlay" onclick="if(event.target===this)closePosModal()">
    <div class="modal-content">
        <div class="modal-header">
            <h4><div class="modal-header-icon" id="posModalIcon"><i class="fas fa-clipboard"></i></div><span id="posModalTitle">Announce Position</span></h4>
            <button class="modal-close" onclick="closePosModal()">×</button>
        </div>
        <form method="POST" id="posForm">
            <input type="hidden" name="add_position"  id="posActionAdd"  value="1">
            <input type="hidden" name="edit_position" id="posActionEdit" value="" disabled>
            <input type="hidden" name="pos_id"        id="posFormId"     value="">
            <div class="modal-body">
                <div class="info-grid">
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Position Title <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="pos_title" id="posTitle" placeholder="e.g. Senior Graphic Designer" required
                               style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div class="form-group">
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Department</label>
                            <input type="text" name="pos_department" id="posDept" placeholder="e.g. Design"
                                   style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;">
                        </div>
                        <div class="form-group">
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Location</label>
                            <input type="text" name="pos_location" id="posLocation" placeholder="e.g. Islamabad / Remote"
                                   style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div class="form-group">
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Job Type</label>
                            <select name="pos_type" id="posType"
                                    style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;">
                                <option value="">Select</option>
                                <option value="Full-Time">Full-Time</option>
                                <option value="Part-Time">Part-Time</option>
                                <option value="Internship">Internship</option>
                                <option value="Remote">Remote</option>
                                <option value="Freelance">Freelance</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Salary Range</label>
                            <input type="text" name="pos_salary" id="posSalary" placeholder="e.g. Rs. 50k – 80k"
                                   style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;">
                        </div>
                    </div>
                    <div id="posStatusRow" style="display:none;" class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Status</label>
                        <select name="pos_status" id="posStatus"
                                style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;box-sizing:border-box;font-family:inherit;">
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Job Description</label>
                        <textarea name="pos_description" id="posDesc" rows="3" placeholder="Describe the role and responsibilities..."
                                  style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;resize:vertical;box-sizing:border-box;font-family:inherit;"></textarea>
                    </div>
                    <div class="form-group">
                        <label style="display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:6px;">Requirements</label>
                        <textarea name="pos_requirements" id="posReq" rows="3" placeholder="List the skills, qualifications required..."
                                  style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:14px;background:#f9fbfc;outline:none;resize:vertical;box-sizing:border-box;font-family:inherit;"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closePosModal()">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="posSubmitBtn"><i class="fas fa-clipboard"></i> Announce Position</button>
            </div>
        </form>
    </div>
</div>

<div id="posViewModal" class="modal-overlay" onclick="if(event.target===this)closePosViewModal()">
    <div class="modal-content">
        <div class="modal-header">
            <h4><div class="modal-header-icon"><i class="fas fa-eye"></i>️</div><span>Position Details</span></h4>
            <button class="modal-close" onclick="closePosViewModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="info-grid">
                <div class="info-row"><div class="info-icon"><i class="fas fa-briefcase"></i></div><div class="info-content"><div class="info-label">Position</div><div class="info-value" id="pvTitle"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-building"></i></div><div class="info-content"><div class="info-label">Department</div><div class="info-value" id="pvDept"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-map-marker-alt"></i></div><div class="info-content"><div class="info-label">Location</div><div class="info-value" id="pvLoc"></div></div></div>
                <div class="info-row"><div class="info-icon">⏰</div><div class="info-content"><div class="info-label">Type</div><div class="info-value" id="pvType"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-dollar-sign"></i></div><div class="info-content"><div class="info-label">Salary Range</div><div class="info-value" id="pvSalary"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-dot-circle"></i></div><div class="info-content"><div class="info-label">Status</div><div class="info-value" id="pvStatus"></div></div></div>
            </div>
            <div class="message-box"><span class="message-label"><i class="fas fa-edit"></i> Description</span><div class="message-content" id="pvDesc"></div></div>
            <div class="message-box" style="margin-top:12px;" id="pvReqBox"><span class="message-label"><i class="fas fa-check-circle"></i> Requirements</span><div class="message-content" id="pvReq"></div></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary-custom" onclick="closePosViewModal()">Close</button>
            <button class="btn btn-primary-custom" id="pvEditBtn" onclick=""><i class="fas fa-pencil-alt"></i>️ Edit Position</button>
        </div>
    </div>
</div>

<div id="toastContainer" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;"></div>

<script>
/**
 * Tab Switching Logic
 */
function switchTab(event, tabId) {
    // Update URL to persist tab state
    const url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    window.history.pushState({}, '', url);

    const contents = document.querySelectorAll('.ms-tab-content');
    contents.forEach(content => content.style.display = 'none');

    const tabs = document.querySelectorAll('.ms-tab');
    tabs.forEach(tab => tab.classList.remove('active'));

    document.getElementById(tabId).style.display = 'block';
    event.currentTarget.classList.add('active');
}

const posData = <?php echo json_encode($pos_js); ?>;

// ── Application modal ─────────────────────────────────────────────────────────
const statusStyles = {
    new:         { label:'Mark New',         bg:'#e0f2fe', color:'#0284c7' },
    reviewing:   { label:'Mark Reviewing',   bg:'#fef9c3', color:'#a16207' },
    shortlisted: { label:'Mark Shortlisted', bg:'#d7f8b8', color:'#2b7a2b' },
    rejected:    { label:'Mark Rejected',    bg:'#ffe5e5', color:'#d63031' },
};

document.querySelectorAll('.view-app').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id, email = this.dataset.email, position = this.dataset.position;
        const status = this.dataset.status || 'new', cv = this.dataset.cv;
        document.getElementById('aName').textContent     = this.dataset.name;
        document.getElementById('aDate').textContent     = this.dataset.date;
        document.getElementById('aPosition').textContent = position;
        document.getElementById('aEmail').innerHTML      = `<a href="mailto:${email}" style="color:var(--primary);">${email}</a>`;
        document.getElementById('aReplyLink').href       = `mailto:${email}?subject=Regarding your application for ${encodeURIComponent(position)}`;
        const s = statusStyles[status] || { label:status, bg:'#f0f0f0', color:'#555' };
        document.getElementById('aStatus').innerHTML =
            `<span style="background:${s.bg};color:${s.color};padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;">${s.label.replace('Mark ','')}</span>`;
        document.getElementById('aCVWrap').innerHTML = cv
            ? `<a href="/${cv}" target="_blank" class="btn btn-primary-custom" style="color:dark;display:inline-flex;margin-top:4px;"><i class="fas fa-file-alt"></i> View CV</a>`
            : '<span style="color:#aaa;">No CV uploaded</span>';
        
        const btns = document.getElementById('statusBtns');
        btns.innerHTML = '';
        Object.entries(statusStyles).forEach(([key, val]) => {
            const f = document.createElement('form');
            f.method = 'POST'; f.style.display = 'inline';
            f.innerHTML = `<input type="hidden" name="update_status" value="1"><input type="hidden" name="app_id" value="${id}"><input type="hidden" name="status" value="${key}">
                <button type="submit" style="background:${val.bg};color:${val.color};border:none;padding:7px 14px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;opacity:${key===status?'.4':'1'};${key===status?'pointer-events:none;':''}" ${key===status?'disabled':''}>
                ${val.label}</button>`;
            btns.appendChild(f);
        });
        document.getElementById('appModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    });
});

function closeAppModal() { document.getElementById('appModal').classList.remove('active'); document.body.style.overflow = ''; }

// ── Position add/edit modal ───────────────────────────────────────────────────
function openPosModal(mode, id) {
    const isEdit = mode === 'edit';
    document.getElementById('posModalTitle').textContent   = isEdit ? 'Edit Position' : 'Announce Position';
    document.getElementById('posModalIcon').textContent    = isEdit ? '<i class="fas fa-pencil-alt"></i>️' : '<i class="fas fa-clipboard"></i>';
    document.getElementById('posSubmitBtn').textContent    = isEdit ? '<i class="fas fa-pencil-alt"></i>️ Update Position' : '<i class="fas fa-clipboard"></i> Announce Position';
    document.getElementById('posStatusRow').style.display  = isEdit ? '' : 'none';

    // Toggle which hidden action field is active
    document.getElementById('posActionAdd').disabled  = isEdit;
    document.getElementById('posActionEdit').disabled = !isEdit;
    document.getElementById('posActionEdit').value    = isEdit ? '1' : '';
    document.getElementById('posFormId').value        = isEdit ? id : '';

    if (isEdit && posData[id]) {
        const p = posData[id];
        document.getElementById('posTitle').value    = p.position    || '';
        document.getElementById('posDept').value     = p.department  || '';
        document.getElementById('posLocation').value = p.location    || '';
        document.getElementById('posType').value     = p.job_type    || '';
        document.getElementById('posSalary').value   = p.salary_range|| '';
        document.getElementById('posDesc').value     = p.description || '';
        document.getElementById('posReq').value      = p.requirements|| '';
        document.getElementById('posStatus').value   = p.status      || 'open';
    } else {
        document.getElementById('posForm').reset();
    }

    document.getElementById('posModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closePosModal() { document.getElementById('posModal').classList.remove('active'); document.body.style.overflow = ''; }

// ── Position view modal ───────────────────────────────────────────────────────
function openPosViewModal(id) {
    const p = posData[id]; if (!p) return;
    document.getElementById('pvTitle').textContent   = p.position      || '—';
    document.getElementById('pvDept').textContent    = p.department    || '—';
    document.getElementById('pvLoc').textContent     = p.location      || '—';
    document.getElementById('pvType').textContent    = p.job_type      || '—';
    document.getElementById('pvSalary').textContent  = p.salary_range  || '—';
    document.getElementById('pvDesc').textContent    = p.description   || 'No description.';
    document.getElementById('pvReq').textContent     = p.requirements  || 'No requirements listed.';
    const statusColor = p.status === 'open' ? '#2b7a2b' : '#d63031';
    const statusBg    = p.status === 'open' ? '#d7f8b8' : '#ffe5e5';
    document.getElementById('pvStatus').innerHTML =
        `<span style="background:${statusBg};color:${statusColor};padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;">${p.status === 'open' ? '<i class="fas fa-circle text-success"></i> Open' : '<i class="fas fa-circle text-danger"></i> Closed'}</span>`;
    document.getElementById('pvEditBtn').onclick = function() { closePosViewModal(); openPosModal('edit', id); };
    document.getElementById('posViewModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closePosViewModal() { document.getElementById('posViewModal').classList.remove('active'); document.body.style.overflow = ''; }

// ── Toasts ────────────────────────────────────────────────────────────────────
function showToast(msg, type='success') {
    const t = document.createElement('div');
    t.style.cssText = `background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:200px;border-left:4px solid ${type==='success'?'#10b981':'#ef4444'};`;
    t.innerHTML = `<span style="font-weight:700;color:${type==='success'?'#10b981':'#ef4444'}">${type==='success'?'<i class="fas fa-check"></i>':'<i class="fas fa-times"></i>'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3000);
}

document.addEventListener('DOMContentLoaded', () => {
    <?php if (isset($_GET['updated'])):   ?> showToast('Status updated.'); <?php endif; ?>
    <?php if (isset($_GET['deleted'])):   ?> showToast('Application deleted.'); <?php endif; ?>
    <?php if (isset($_GET['pos_added'])): ?> showToast('Position announced!'); <?php endif; ?>
    <?php if (isset($_GET['pos_updated'])): ?> showToast('Position updated.'); <?php endif; ?>
    <?php if (isset($_GET['pos_deleted'])): ?> showToast('Position deleted.'); <?php endif; ?>
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeAppModal(); closePosModal(); closePosViewModal(); }
});
</script>

<?php include('dashboard_footer.php'); ?>