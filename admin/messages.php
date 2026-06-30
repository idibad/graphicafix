<?php
include('dashboard_header.php');

// ── Handle delete ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_contact'])) {
    $del_id = intval($_POST['contact_id'] ?? 0);
    if ($del_id) {
        $stmt = $conn->prepare("DELETE FROM contacts WHERE id = ?");
        $stmt->bind_param("i", $del_id);
        $stmt->execute();
    }
    header('Location: contacts.php?deleted=1'); exit;
}

$contacts = $conn->query("SELECT * FROM contacts ORDER BY created_at DESC");
$total    = $contacts ? $contacts->num_rows : 0;

function contactAvatar($name) {
    $colors = ['pink', 'blue', 'green', 'orange', 'purple'];
    return $colors[ord(strtoupper($name[0])) % count($colors)];
}

function contactTimeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)         return $diff . "s ago";
    elseif ($diff < 3600)   return round($diff/60)   . "m ago";
    elseif ($diff < 86400)  return round($diff/3600)  . "h ago";
    elseif ($diff < 604800) return round($diff/86400) . "d ago";
    else return date("M j, Y", strtotime($datetime));
}
?>

<div class="height-100">
    <div class="main-card">
        <div class="main-header">
            <h3><i class="fas fa-envelope-open-text"></i> Contact Messages</h3>
            <span style="font-size:13px;color:#888;"><?= $total ?> message<?= $total != 1 ? 's' : '' ?></span>
        </div>

        <div class="main-table">
            <div class="table-head" style="grid-template-columns:1.8fr 1.5fr 2.5fr 1fr 1fr;">
                <span>Name</span>
                <span>Email</span>
                <span>Message</span>
                <span>Date</span>
                <span>Actions</span>
            </div>

            <?php if ($total > 0): while ($contact = $contacts->fetch_assoc()):
                $short = strlen($contact['message']) > 100
                    ? substr($contact['message'], 0, 97) . '…'
                    : $contact['message'];
                $formattedDate = (new DateTime($contact['created_at']))->format('M d, Y H:i');
            ?>
            <div class="table-row" style="grid-template-columns:1.8fr 1.5fr 2.5fr 1fr 1fr;">

                <div class="client">
                    <div class="avatar <?= contactAvatar($contact['name']) ?>">
                        <?= strtoupper($contact['name'][0] ?? 'A') ?>
                    </div>
                    <div>
                        <strong><?= htmlspecialchars($contact['name']) ?></strong>
                    </div>
                </div>

                <span data-label="Email" style="font-size:13.5px;color:#666;"><?= htmlspecialchars($contact['email']) ?></span>

                <span data-label="Message" style="font-size:13.5px;color:#666;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;">
                    <?= htmlspecialchars($short) ?>
                </span>

                <span data-label="Date" style="font-size:13px;color:#888;"><?= contactTimeAgo($contact['created_at']) ?></span>

                <div style="display:flex;align-items:center;gap:4px;">
                    <!-- View -->
                    <button class="dots view-details" title="View message"
                        data-name="<?= htmlspecialchars($contact['name']) ?>"
                        data-email="<?= htmlspecialchars($contact['email']) ?>"
                        data-message="<?= htmlspecialchars($contact['message']) ?>"
                        data-date="<?= $formattedDate ?>"><i class="fas fa-eye"></i>️</button>

                    <!-- Reply -->
                    <a class="dots" href="mailto:<?= htmlspecialchars($contact['email']) ?>" title="Reply" style="text-decoration:none;"><i class="fas fa-envelope"></i>️</a>

                    <!-- Delete -->
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this message?');">
                        <input type="hidden" name="delete_contact" value="1">
                        <input type="hidden" name="contact_id"     value="<?= $contact['id'] ?>">
                        <button type="submit" class="dots" title="Delete" style="color:#ef4444;"><i class="fas fa-trash"></i>️</button>
                    </form>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div style="text-align:center;padding:60px 24px;color:#888;">
                <div style="font-size:36px;margin-bottom:8px;"><i class="fas fa-envelope-open"></i></div>
                No contact messages yet.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="contactModal" class="modal-overlay" onclick="if(event.target===this)closeContactModal()">
    <div class="modal-content">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon"><i class="fas fa-envelope-open-text"></i></div>
                <span>Message Details</span>
            </h4>
            <button class="modal-close" onclick="closeContactModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-icon"><i class="fas fa-user"></i></div>
                    <div class="info-content">
                        <div class="info-label">From</div>
                        <div class="info-value" id="modalName"></div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-icon"><i class="fas fa-envelope"></i></div>
                    <div class="info-content">
                        <div class="info-label">Email</div>
                        <div class="info-value" id="modalEmail"></div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="info-content">
                        <div class="info-label">Received</div>
                        <div class="info-value" id="modalDate"></div>
                    </div>
                </div>
            </div>
            <div class="message-box">
                <span class="message-label"><i class="fas fa-comment"></i> Message</span>
                <div class="message-content" id="modalMessage"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary-custom" onclick="closeContactModal()">Close</button>
            <a href="#" id="replyLink" class="btn btn-primary-custom"><i class="fas fa-envelope"></i>️ Reply via Email</a>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.view-details').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('modalName').textContent    = this.dataset.name;
        document.getElementById('modalDate').textContent    = this.dataset.date;
        document.getElementById('modalMessage').textContent = this.dataset.message;
        document.getElementById('modalEmail').innerHTML     = `<a href="mailto:${this.dataset.email}" style="color:var(--primary);">${this.dataset.email}</a>`;
        document.getElementById('replyLink').href           = 'mailto:' + this.dataset.email;
        document.getElementById('contactModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    });
});

function closeContactModal() {
    document.getElementById('contactModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeContactModal();
});

<?php if (isset($_GET['deleted'])): ?>
document.addEventListener('DOMContentLoaded', () => {
    const t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:200px;border-left:4px solid #10b981;z-index:9999;';
    t.innerHTML = '<span style="color:#10b981;font-weight:700;"><i class="fas fa-check"></i></span><span>Message deleted.</span>';
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3000);
});
<?php endif; ?>
</script>

<?php include('dashboard_footer.php'); ?>