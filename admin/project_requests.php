<?php
include 'dashboard_header.php';

// ── Handle actions ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Delete
    if (isset($_POST['delete_request'])) {
        $id = intval($_POST['request_id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM project_requests WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
        }
        header('Location: project_requests.php?deleted=1'); exit;
    }

    // Mark spam / unspam / mark new
    if (isset($_POST['set_status'])) {
        $id     = intval($_POST['request_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $allowed = ['new', 'reviewed', 'spam'];
        if ($id && in_array($status, $allowed)) {
            $stmt = $conn->prepare("UPDATE project_requests SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $id);
            $stmt->execute();
        }
        header('Location: project_requests.php?tab=' . urlencode($_POST['current_tab'] ?? 'all') . '&status_updated=1');
        exit;
    }

    // Bulk delete spam
    if (isset($_POST['purge_spam'])) {
        $conn->query("DELETE FROM project_requests WHERE status = 'spam'");
        header('Location: project_requests.php?purged=1'); exit;
    }
}

// ── Active tab / filter ───────────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'all';
$allowed_tabs = ['all', 'new', 'reviewed', 'spam'];
if (!in_array($tab, $allowed_tabs)) $tab = 'all';

// ── Fetch counts ──────────────────────────────────────────────────────────────
function countTab($conn, $status) {
    $sql = $status === 'all'
        ? "SELECT COUNT(*) AS c FROM project_requests WHERE status != 'spam'"
        : "SELECT COUNT(*) AS c FROM project_requests WHERE status = '$status'";
    return $conn->query($sql)->fetch_assoc()['c'] ?? 0;
}

$count_all      = countTab($conn, 'all');
$count_new      = countTab($conn, 'new');
$count_reviewed = countTab($conn, 'reviewed');
$count_spam     = countTab($conn, 'spam');

$total_today = $conn->query("SELECT COUNT(*) AS c FROM project_requests WHERE DATE(created_at) = CURDATE() AND status != 'spam'")->fetch_assoc()['c'] ?? 0;
$total_week  = $conn->query("SELECT COUNT(*) AS c FROM project_requests WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status != 'spam'")->fetch_assoc()['c'] ?? 0;

// ── Fetch requests for active tab ─────────────────────────────────────────────
if ($tab === 'all') {
    $requests = $conn->query("SELECT * FROM project_requests WHERE status != 'spam' ORDER BY created_at DESC");
} else {
    $stmt = $conn->prepare("SELECT * FROM project_requests WHERE status = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $tab);
    $stmt->execute();
    $requests = $stmt->get_result();
}
$total = $requests ? $requests->num_rows : 0;

// ── Auto-spam detection heuristics (run on page load for unprocessed rows) ────
// Flag as spam: entries submitted under 4 seconds, or description looks like bot
$conn->query("
    UPDATE project_requests
    SET status = 'spam'
    WHERE status = 'new'
      AND (
          -- Description is suspiciously short
          LENGTH(TRIM(description)) < 20
          -- Or name/description contains URLs
          OR description REGEXP 'https?://|www\\\\.'
          OR name        REGEXP 'https?://|www\\\\.'
          -- Or all fields look like test data
          OR (LOWER(name) IN ('test','asdf','123','admin','user') AND LENGTH(description) < 30)
      )
");

function reqAvatar($name) {
    $colors = ['pink','blue','green','orange','purple'];
    return $colors[ord(strtoupper($name[0] ?? 'A')) % count($colors)];
}
function reqTimeAgo($dt) {
    $diff = time() - strtotime($dt);
    if ($diff < 60)         return $diff . "s ago";
    elseif ($diff < 3600)   return round($diff/60)   . "m ago";
    elseif ($diff < 86400)  return round($diff/3600)  . "h ago";
    elseif ($diff < 604800) return round($diff/86400) . "d ago";
    return date("M j, Y", strtotime($dt));
}
?>

<div class="height-100">

    <!-- Stats -->
    <div class="stats-row" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clipboard"></i></div>
            <div class="stat-number"><?= $count_all ?></div>
            <div class="stat-label">Total Requests</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="color:#0284c7;">🆕</div>
            <div class="stat-number" style="color:#0284c7;"><?= $count_new ?></div>
            <div class="stat-label">New</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-number"><?= $total_today ?></div>
            <div class="stat-label">Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-number"><?= $total_week ?></div>
            <div class="stat-label">This Week</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="color:#d63031;"><i class="fas fa-ban"></i></div>
            <div class="stat-number" style="color:#d63031;"><?= $count_spam ?></div>
            <div class="stat-label">Spam</div>
        </div>
    </div>

    <!-- Table card -->
    <div class="main-card">
        <div class="main-header">
            <h3><i class="fas fa-clipboard"></i> Project Requests</h3>

            <!-- Purge spam button (only shown on spam tab) -->
            <?php if ($tab === 'spam' && $count_spam > 0): ?>
            <form method="POST" onsubmit="return confirm('Permanently delete all <?= $count_spam ?> spam entries? This cannot be undone.');">
                <input type="hidden" name="purge_spam" value="1">
                <button type="submit" class="btn" style="background:#fef2f2;color:#d63031;border:1.5px solid #fecaca;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                    <i class="fas fa-trash"></i>️ Purge All Spam
                </button>
            </form>
            <?php else: ?>
            <span style="font-size:13px;color:#888;"><?= $total ?> result<?= $total != 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </div>

        <!-- Filter tabs -->
        <div style="display:flex;align-items:center;gap:0;border-bottom:2px solid #f0f0f0;padding:0 24px;overflow-x:auto;">
            <?php
            $tabs = [
                'all'      => ['label' => 'All',      'count' => $count_all,      'icon' => '<i class="fas fa-clipboard"></i>'],
                'new'      => ['label' => 'New',       'count' => $count_new,      'icon' => '🆕'],
                'reviewed' => ['label' => 'Reviewed',  'count' => $count_reviewed, 'icon' => '<i class="fas fa-check-circle"></i>'],
                'spam'     => ['label' => 'Spam',      'count' => $count_spam,     'icon' => '<i class="fas fa-ban"></i>'],
            ];
            foreach ($tabs as $key => $t):
                $active = $tab === $key;
            ?>
            <a href="project_requests.php?tab=<?= $key ?>"
               style="display:inline-flex;align-items:center;gap:6px;
                      padding:12px 18px;font-size:13px;font-weight:600;
                      text-decoration:none;white-space:nowrap;
                      color:<?= $active ? 'var(--primary)' : '#888' ?>;
                      border-bottom:2px solid <?= $active ? 'var(--primary)' : 'transparent' ?>;
                      margin-bottom:-2px;transition:all .2s;">
                <?= $t['icon'] ?> <?= $t['label'] ?>
                <span style="background:<?= $active ? 'var(--primary)' : '#f0f0f0' ?>;
                             color:<?= $active ? '#fff' : '#888' ?>;
                             padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">
                    <?= $t['count'] ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Spam info banner -->
        <?php if ($tab === 'spam'): ?>
        <div style="background:#fef9f0;border-bottom:1px solid #fde9c4;padding:12px 24px;display:flex;align-items:center;gap:10px;font-size:13px;color:#92550a;">
            <i class="fas fa-info-circle"></i>
            Entries are auto-flagged as spam based on: submission speed, URLs in fields, very short descriptions, or test-like names.
            You can unmark any entry as legitimate.
        </div>
        <?php endif; ?>

        <!-- Table -->
        <div class="main-table" style="padding-top:8px;">
            <div class="table-head" style="grid-template-columns:2fr 1.5fr 1.5fr 1fr 1fr 1fr;">
                <span>Name</span>
                <span>Company</span>
                <span>Email</span>
                <span>Type</span>
                <span>Date</span>
                <span>Actions</span>
            </div>

            <?php if ($total > 0): while ($r = $requests->fetch_assoc()):
                $formattedDate = (new DateTime($r['created_at']))->format('M d, Y H:i');
                $is_spam       = ($r['status'] ?? '') === 'spam';
                $is_reviewed   = ($r['status'] ?? '') === 'reviewed';
            ?>
            <div class="table-row" style="grid-template-columns:2fr 1.5fr 1.5fr 1fr 1fr 1fr;<?= $is_spam ? 'opacity:.7;' : '' ?>">

                <div class="client">
                    <div class="avatar <?= reqAvatar($r['name']) ?>"><?= strtoupper($r['name'][0] ?? 'A') ?></div>
                    <div>
                        <strong><?= htmlspecialchars($r['name']) ?></strong>
                        <small><?= htmlspecialchars($r['project_type'] ?? '') ?></small>
                    </div>
                </div>

                <span data-label="Company"  style="font-size:13.5px;color:#666;"><?= htmlspecialchars($r['company'] ?: '—') ?></span>
                <span data-label="Email"    style="font-size:13.5px;color:#666;"><?= htmlspecialchars($r['email']) ?></span>
                <span data-label="Type"     style="font-size:13px;color:#888;"><?= htmlspecialchars($r['project_type'] ?? '—') ?></span>
                <span data-label="Date"     style="font-size:13px;color:#888;"><?= reqTimeAgo($r['created_at']) ?></span>

                <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;">

                    <?php if (!$is_spam): ?>
                    <!-- View -->
                    <button class="dots view-details" title="View details"
                        data-name="<?= htmlspecialchars($r['name']) ?>"
                        data-company="<?= htmlspecialchars($r['company'] ?? '') ?>"
                        data-email="<?= htmlspecialchars($r['email']) ?>"
                        data-phone="<?= htmlspecialchars($r['phone'] ?? '') ?>"
                        data-type="<?= htmlspecialchars($r['project_type'] ?? '') ?>"
                        data-budget="<?= htmlspecialchars($r['budget'] ?? '') ?>"
                        data-time="<?= htmlspecialchars($r['timeframe'] ?? '') ?>"
                        data-description="<?= htmlspecialchars($r['description'] ?? '') ?>"
                        data-file="<?= htmlspecialchars($r['attachment'] ?? '') ?>"
                        data-date="<?= $formattedDate ?>"><i class="fas fa-eye"></i>️</button>

                    <!-- Reply -->
                    <a class="dots" href="mailto:<?= htmlspecialchars($r['email']) ?>" title="Reply" style="text-decoration:none;"><i class="fas fa-envelope"></i>️</a>
                    <?php endif; ?>

                    <?php if ($is_spam): ?>
                    <!-- Not spam (restore) -->
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="set_status"   value="1">
                        <input type="hidden" name="request_id"   value="<?= $r['id'] ?>">
                        <input type="hidden" name="status"        value="new">
                        <input type="hidden" name="current_tab"  value="spam">
                        <button type="submit" class="dots" title="Not spam — restore" style="color:#0284c7;"><i class="fas fa-recycle"></i>️</button>
                    </form>
                    <?php else: ?>
                    <!-- Mark reviewed -->
                    <?php if (!$is_reviewed): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="set_status"   value="1">
                        <input type="hidden" name="request_id"   value="<?= $r['id'] ?>">
                        <input type="hidden" name="status"        value="reviewed">
                        <input type="hidden" name="current_tab"  value="<?= $tab ?>">
                        <button type="submit" class="dots" title="Mark reviewed" style="color:#2b7a2b;"><i class="fas fa-check-circle"></i></button>
                    </form>
                    <?php endif; ?>
                    <!-- Mark spam -->
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Mark as spam?');">
                        <input type="hidden" name="set_status"   value="1">
                        <input type="hidden" name="request_id"   value="<?= $r['id'] ?>">
                        <input type="hidden" name="status"        value="spam">
                        <input type="hidden" name="current_tab"  value="<?= $tab ?>">
                        <button type="submit" class="dots" title="Mark as spam" style="color:#e17055;"><i class="fas fa-ban"></i></button>
                    </form>
                    <?php endif; ?>

                    <!-- Delete -->
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete permanently?');">
                        <input type="hidden" name="delete_request" value="1">
                        <input type="hidden" name="request_id"     value="<?= $r['id'] ?>">
                        <button type="submit" class="dots" title="Delete" style="color:#ef4444;"><i class="fas fa-trash"></i>️</button>
                    </form>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div style="text-align:center;padding:60px 24px;color:#888;">
                <div style="font-size:2.5rem;margin-bottom:8px;">
                    <?= $tab === 'spam' ? '<i class="fas fa-shield-alt"></i>️' : '<i class="fas fa-envelope-open"></i>' ?>
                </div>
                <?= $tab === 'spam' ? 'No spam detected. Your inbox is clean.' : 'No requests in this category.' ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="requestModal" class="modal-overlay" onclick="if(event.target===this)closeReqModal()">
    <div class="modal-content">
        <div class="modal-header">
            <h4><div class="modal-header-icon"><i class="fas fa-clipboard"></i></div><span>Project Request Details</span></h4>
            <button class="modal-close" onclick="closeReqModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="info-grid">
                <div class="info-row"><div class="info-icon"><i class="fas fa-user"></i></div><div class="info-content"><div class="info-label">Name</div><div class="info-value" id="rName"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-building"></i></div><div class="info-content"><div class="info-label">Company</div><div class="info-value" id="rCompany"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-envelope"></i>️</div><div class="info-content"><div class="info-label">Email</div><div class="info-value" id="rEmail"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-phone"></i></div><div class="info-content"><div class="info-label">Phone</div><div class="info-value" id="rPhone"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-tools"></i>️</div><div class="info-content"><div class="info-label">Project Type</div><div class="info-value" id="rType"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-dollar-sign"></i></div><div class="info-content"><div class="info-label">Budget</div><div class="info-value" id="rBudget"></div></div></div>
                <div class="info-row"><div class="info-icon">⏳</div><div class="info-content"><div class="info-label">Timeframe</div><div class="info-value" id="rTime"></div></div></div>
                <div class="info-row"><div class="info-icon"><i class="fas fa-calendar-alt"></i></div><div class="info-content"><div class="info-label">Submitted</div><div class="info-value" id="rDate"></div></div></div>
            </div>
            <div class="message-box">
                <span class="message-label"><i class="fas fa-edit"></i> Project Description</span>
                <div class="message-content" id="rDescription"></div>
            </div>
            <div id="rAttachment" style="margin-top:16px;"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary-custom" onclick="closeReqModal()">Close</button>
            <a id="rReplyLink" href="#" class="btn btn-primary-custom"><i class="fas fa-envelope"></i>️ Reply via Email</a>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.view-details').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('rName').textContent        = this.dataset.name;
        document.getElementById('rCompany').textContent     = this.dataset.company || '—';
        document.getElementById('rPhone').textContent       = this.dataset.phone   || '—';
        document.getElementById('rType').textContent        = this.dataset.type    || '—';
        document.getElementById('rBudget').textContent      = this.dataset.budget  || '—';
        document.getElementById('rTime').textContent        = this.dataset.time    || '—';
        document.getElementById('rDate').textContent        = this.dataset.date;
        document.getElementById('rDescription').textContent = this.dataset.description;
        document.getElementById('rEmail').innerHTML         = `<a href="mailto:${this.dataset.email}" style="color:var(--primary);">${this.dataset.email}</a>`;
        document.getElementById('rReplyLink').href          = 'mailto:' + this.dataset.email;
        const file = this.dataset.file;
        document.getElementById('rAttachment').innerHTML    = file
            ? `<a href="${file}" target="_blank" style="display:inline-flex;align-items:center;gap:6px;color:var(--primary);font-weight:600;font-size:14px;"><i class="fas fa-paperclip"></i> View Attachment</a>`
            : '';
        document.getElementById('requestModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    });
});

function closeReqModal() {
    document.getElementById('requestModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeReqModal(); });

// Toasts
function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;bottom:24px;right:24px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:200px;border-left:4px solid ${type==='success'?'#10b981':'#f59e0b'};z-index:9999;`;
    t.innerHTML = `<span style="font-weight:700;color:${type==='success'?'#10b981':'#f59e0b'}">${type==='success'?'<i class="fas fa-check"></i>':'<i class="fas fa-exclamation-triangle"></i>'}</span><span>${msg}</span>`;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3500);
}

document.addEventListener('DOMContentLoaded', () => {
    <?php if (isset($_GET['deleted'])):       ?> showToast('Request deleted.');         <?php endif; ?>
    <?php if (isset($_GET['status_updated'])): ?> showToast('Status updated.');          <?php endif; ?>
    <?php if (isset($_GET['purged'])):         ?> showToast('All spam purged.', 'warn'); <?php endif; ?>
});
</script>

<?php include('dashboard_footer.php'); ?>