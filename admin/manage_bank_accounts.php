<?php
/**
 * manage_bank_accounts.php
 * Bank accounts configuration dashboard for admin users.
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('location:login.php');
    exit;
}

// Fetch current user role
$username = $_SESSION['username'];
$user_stmt = $conn->prepare("SELECT role FROM users WHERE username = ?");
$user_stmt->bind_param("s", $username);
$user_stmt->execute();
$role_data = $user_stmt->get_result()->fetch_assoc();
$role = strtolower($role_data['role'] ?? 'user');

// Only allow super_admin users
if ($role !== 'super_admin' && $role !== 'superadmin') {
    header('location:index.php');
    exit;
}

// Supported Banks
$banks_list = [
    'Meezan Bank',
    'Habib Bank Limited (HBL)',
    'Bank Alfalah',
    'United Bank Limited (UBL)',
    'MCB Bank',
    'Allied Bank (ABL)',
    'Standard Chartered',
    'Faysal Bank',
    'JazzCash',
    'Easypaisa',
    'SadaPay',
    'NayaPay',
    'Other Bank'
];

function getBankLogoSvg($bank) {
    switch ($bank) {
        case 'Meezan Bank':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#8B2635" /><path d="M50,20 L50,80" stroke="#D4AF37" stroke-width="6" stroke-linecap="round" /><path d="M25,70 Q50,82 75,70" fill="none" stroke="#D4AF37" stroke-width="6" stroke-linecap="round" /><path d="M50,35 Q25,35 25,55 Q25,70 50,60" fill="none" stroke="#D4AF37" stroke-width="6" stroke-linecap="round" /><path d="M50,35 Q75,35 75,55 Q75,70 50,60" fill="none" stroke="#D4AF37" stroke-width="6" stroke-linecap="round" /></svg>';
        case 'Habib Bank Limited (HBL)':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><rect width="100" height="100" rx="15" fill="#005A5B" /><path d="M30,50 L50,30 L70,50 L50,70 Z" fill="#FFFFFF" /><circle cx="50" cy="50" r="8" fill="#FFD700" /></svg>';
        case 'Bank Alfalah':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#FFFFFF" stroke="#D2143A" stroke-width="4" /><path d="M30,50 C30,35 45,30 55,30 C65,30 70,38 70,45 C70,55 58,70 50,70 C42,70 30,60 30,50 Z" fill="#D2143A" /><path d="M45,45 C45,40 52,38 56,38 C60,38 62,42 62,45 C62,50 56,58 52,58 C48,58 45,50 45,45 Z" fill="#003366" /></svg>';
        case 'United Bank Limited (UBL)':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#0D47A1" /><circle cx="50" cy="50" r="25" fill="none" stroke="#FFD54F" stroke-width="8" /><line x1="50" y1="15" x2="50" y2="85" stroke="#FFD54F" stroke-width="8" /><line x1="15" y1="50" x2="85" y2="50" stroke="#FFD54F" stroke-width="8" /></svg>';
        case 'MCB Bank':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#FF8F00" /><path d="M30,70 Q45,30 70,30 Q65,65 30,70" fill="#2E7D32" /></svg>';
        case 'Allied Bank (ABL)':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#1565C0" /><text x="50" y="65" font-size="36" font-family="Arial" font-weight="900" fill="#FFFFFF" text-anchor="middle">ABL</text></svg>';
        case 'Standard Chartered':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#00838F" /><path d="M30,35 Q50,65 70,35" fill="none" stroke="#81C784" stroke-width="10" stroke-linecap="round" /><path d="M30,65 Q50,35 70,65" fill="none" stroke="#FFFFFF" stroke-width="10" stroke-linecap="round" /></svg>';
        case 'Faysal Bank':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#C62828" /><rect x="35" y="35" width="30" height="30" fill="#FFFFFF" transform="rotate(45 50 50)" /></svg>';
        case 'JazzCash':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#000000" /><circle cx="50" cy="50" r="30" fill="#FFC107" /><path d="M45,35 L55,35 L55,55 L45,55 Z" fill="#D32F2F" /></svg>';
        case 'Easypaisa':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#4CAF50" /><path d="M30,50 L45,65 L70,35" fill="none" stroke="#FFFFFF" stroke-width="12" stroke-linecap="round" stroke-linejoin="round" /></svg>';
        case 'SadaPay':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#FF5A5F" /><text x="50" y="65" font-size="32" font-family="Arial" font-weight="900" fill="#FFFFFF" text-anchor="middle">Sada</text></svg>';
        case 'NayaPay':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#00C853" /><text x="50" y="65" font-size="32" font-family="Arial" font-weight="900" fill="#FFFFFF" text-anchor="middle">Naya</text></svg>';
        default:
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#37474F" /><path d="M25,75 L75,75 L75,70 L25,70 Z M30,65 L35,65 L35,45 L30,45 Z M45,65 L50,65 L50,45 L45,45 Z M60,65 L65,65 L65,45 L60,45 Z M25,40 L75,40 L50,20 Z" fill="#FFFFFF" /></svg>';
    }
}

$toast_message = '';
$toast_type    = '';

if (isset($_SESSION['toast_msg'])) {
    $toast_message = $_SESSION['toast_msg'];
    $toast_type    = $_SESSION['toast_type'] ?? 'success';
    unset($_SESSION['toast_msg'], $_SESSION['toast_type']);
}

// ── Actions POST handling ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action         = $_POST['action']         ?? '';
    $account_id     = intval($_POST['account_id'] ?? 0);
    $bank_name      = trim($_POST['bank_name']      ?? '');
    $account_title  = trim($_POST['account_title']  ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $status         = trim($_POST['status']         ?? 'active');

    if ($action === 'add_account') {
        if (!$bank_name || !$account_title || !$account_number) {
            $_SESSION['toast_msg']  = 'Bank name, account title and account number/IBAN are required.';
            $_SESSION['toast_type'] = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO bank_accounts (bank_name, account_title, account_number, status, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("ssss", $bank_name, $account_title, $account_number, $status);
            if ($stmt->execute()) {
                $_SESSION['toast_msg']  = 'Bank account added successfully!';
                $_SESSION['toast_type'] = 'success';
            } else {
                $_SESSION['toast_msg']  = 'Failed to add bank account.';
                $_SESSION['toast_type'] = 'error';
            }
        }
        header("Location: manage_bank_accounts.php");
        exit;
    } elseif ($action === 'edit_account') {
        if (!$account_id || !$bank_name || !$account_title || !$account_number) {
            $_SESSION['toast_msg']  = 'Bank name, account title and account number/IBAN are required.';
            $_SESSION['toast_type'] = 'error';
        } else {
            $stmt = $conn->prepare("UPDATE bank_accounts SET bank_name=?, account_title=?, account_number=?, status=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("ssssi", $bank_name, $account_title, $account_number, $status, $account_id);
            if ($stmt->execute()) {
                $_SESSION['toast_msg']  = 'Bank account updated successfully!';
                $_SESSION['toast_type'] = 'success';
            } else {
                $_SESSION['toast_msg']  = 'Failed to update bank account.';
                $_SESSION['toast_type'] = 'error';
            }
        }
        header("Location: manage_bank_accounts.php");
        exit;
    } elseif ($action === 'delete_account') {
        if ($account_id) {
            $stmt = $conn->prepare("DELETE FROM bank_accounts WHERE id=?");
            $stmt->bind_param("i", $account_id);
            if ($stmt->execute()) {
                $_SESSION['toast_msg']  = 'Bank account deleted successfully!';
                $_SESSION['toast_type'] = 'success';
            } else {
                $_SESSION['toast_msg']  = 'Failed to delete bank account.';
                $_SESSION['toast_type'] = 'error';
            }
        }
        header("Location: manage_bank_accounts.php");
        exit;
    }
}

// GET Parameter handling (status toggle)
if (isset($_GET['action']) && $_GET['action'] === 'toggle') {
    $action_id = intval($_GET['id'] ?? 0);
    if ($action_id > 0) {
        $stat_stmt = $conn->prepare("SELECT status FROM bank_accounts WHERE id = ?");
        $stat_stmt->bind_param("i", $action_id);
        $stat_stmt->execute();
        $res = $stat_stmt->get_result()->fetch_assoc();
        if ($res) {
            $new_status = ($res['status'] === 'active') ? 'inactive' : 'active';
            $upd_stmt = $conn->prepare("UPDATE bank_accounts SET status = ?, updated_at = NOW() WHERE id = ?");
            $upd_stmt->bind_param("si", $new_status, $action_id);
            if ($upd_stmt->execute()) {
                $_SESSION['toast_msg']  = 'Account status updated.';
                $_SESSION['toast_type'] = 'success';
            } else {
                $_SESSION['toast_msg']  = 'Failed to update status.';
                $_SESSION['toast_type'] = 'error';
            }
        }
        header("Location: manage_bank_accounts.php");
        exit;
    }
}

// Fetch stats and lists
$total_accounts = $conn->query("SELECT COUNT(*) AS c FROM bank_accounts")->fetch_assoc()['c'] ?? 0;
$active_accounts = $conn->query("SELECT COUNT(*) AS c FROM bank_accounts WHERE status = 'active'")->fetch_assoc()['c'] ?? 0;

$accounts_result = $conn->query("SELECT * FROM bank_accounts ORDER BY id DESC");

function timeAgo($datetime) {
    if (!$datetime) return '—';
    $diff = time() - strtotime($datetime);
    if ($diff < 60)          return $diff . " seconds ago";
    elseif ($diff < 3600)    return round($diff/60)   . " minutes ago";
    elseif ($diff < 86400)   return round($diff/3600)  . " hours ago";
    elseif ($diff < 604800)  return round($diff/86400) . " days ago";
    else return date("M j, Y", strtotime($datetime));
}

include 'dashboard_header.php';
?>

<!-- Style Override Block -->
<style>
/* ── UI/UX OVERHAUL STYLES ── */
.main-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 10px 25px -3px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    border: 1px solid #f0f3f2;
    margin-bottom: 30px;
}
.main-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 28px;
    border-bottom: 1px solid #f4f5f4;
    background: #fff;
}
.main-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 8px;
}
.main-header button.add-bank {
    background-color: var(--primary-color);
    color: #ffffff;
    border: 1px solid var(--primary-color);
    padding: 10px 20px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 13.5px;
    cursor: pointer;
    transition: all 0.25s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
}
.main-header button.add-bank:hover {
    background-color: var(--primary-hover);
    border-color: var(--primary-hover);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(2, 68, 66, 0.25);
}
.main-table {
    background: #fff;
}
.table-head {
    background: #f8faf9;
    padding: 16px 28px;
    border-bottom: 2px solid rgba(0,0,0,0.03);
    font-size: 0.8rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #64748b;
    display: grid;
    gap: 16px;
}
.table-row {
    padding: 16px 28px;
    border-bottom: 1px solid #f1f5f9;
    display: grid;
    gap: 16px;
    align-items: center;
    transition: background 0.2s ease;
    font-size: 14px;
}
.table-row:hover {
    background: #fbfdfc;
}
.table-row span, .table-row div {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.table-row .bank-info {
    display: flex;
    align-items: center;
    gap: 12px;
}
.table-row .bank-info .logo-wrap {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 1px solid #e2e8f0;
}
.table-row .bank-info .text-wrap {
    display: flex;
    flex-direction: column;
}
.table-row .bank-info strong {
    color: var(--text-dark);
    font-weight: 700;
}
.table-row .bank-info small {
    color: var(--text-muted);
    font-size: 12px;
}
.badge-status {
    padding: 6px 12px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-radius: 50px;
    display: inline-block;
    text-align: center;
    cursor: pointer;
    border: none;
    width: fit-content;
}
.badge-status.active {
    background-color: #ecfdf5;
    color: #10b981;
}
.badge-status.inactive {
    background-color: #f1f5f9;
    color: #64748b;
}
.dots {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: #f1f5f9;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}
.dots:hover {
    background: #e2e8f0;
    color: #0f172a;
}

/* ── Stats block styling ── */
.stats-row {
    display: flex;
    gap: 20px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.stat-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    border: 1px solid #e2e8f0;
    flex: 1;
    min-width: 200px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
}
.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.stat-label {
    color: #64748b;
    font-size: 13.5px;
    font-weight: 600;
}
.stat-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}
.stat-number {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
}

/* ── Modal overlay and content ── */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-overlay.active {
    display: flex;
}
.modal-content {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    animation: modalSlideUp 0.3s ease-out;
}
@keyframes modalSlideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.modal-header {
    background: var(--primary-color);
    color: #fff;
    padding: 20px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header h4 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}
.modal-header-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: rgba(255,255,255,0.15);
    border-radius: 8px;
    font-size: 14px;
}
.modal-close {
    background: none;
    border: none;
    color: rgba(255,255,255,0.7);
    font-size: 24px;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    transition: color 0.2s;
}
.modal-close:hover {
    color: #fff;
}
.modal-body {
    padding: 24px;
}
.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: #fbfcfc;
}
.form-group {
    margin-bottom: 18px;
}
.form-group label {
    display: block;
    font-weight: 600;
    font-size: 13px;
    color: #334155;
    margin-bottom: 6px;
}
.form-group input, .form-group select {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    font-family: inherit;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s;
    background-color: #fff;
}
.form-group input:focus, .form-group select:focus {
    border-color: var(--primary-color);
}
</style>

<div class="height-100 fade-in-up">
    <!-- Header -->
    <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
        <div>
            <h1 style="font-size:24px; font-weight:800; color:var(--primary-color); margin:0;"><i class="fas fa-university" style="margin-right:8px;"></i> Bank Account Information</h1>
            <p style="color:var(--text-muted); margin:4px 0 0 0; font-size:14px;">Add and manage bank accounts displayed on invoices and receipts</p>
        </div>
        <a href="index.php" class="btn-secondary-custom">← Dashboard</a>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total Accounts</span>
                <div class="stat-icon" style="background:#e0f2fe;color:#0284c7;"><i class="fas fa-university"></i></div>
            </div>
            <div class="stat-number"><?= $total_accounts ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Active Accounts</span>
                <div class="stat-icon" style="background:#ecfdf5;color:#10b981;"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="stat-number"><?= $active_accounts ?></div>
        </div>
    </div>

    <!-- Main Grid List -->
    <div class="main-card">
        <div class="main-header">
            <h3><i class="fas fa-list"></i> Registered Accounts</h3>
            <button class="add-bank" onclick="openAddModal()">+ Add Bank Account</button>
        </div>

        <div class="main-table">
            <div class="table-head" style="grid-template-columns: 2fr 1.5fr 2fr 1fr 1.2fr 1fr;">
                <span>Bank</span>
                <span>Account Title</span>
                <span>Number / IBAN</span>
                <span>Status</span>
                <span>Added</span>
                <span>Actions</span>
            </div>

            <?php if ($total_accounts > 0): ?>
                <?php while ($row = $accounts_result->fetch_assoc()): ?>
                    <div class="table-row" style="grid-template-columns: 2fr 1.5fr 2fr 1fr 1.2fr 1fr;">
                        <div class="bank-info">
                            <div class="logo-wrap">
                                <?= getBankLogoSvg($row['bank_name']) ?>
                            </div>
                            <div class="text-wrap">
                                <strong><?= htmlspecialchars($row['bank_name']) ?></strong>
                            </div>
                        </div>
                        <span data-label="Account Title" style="font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($row['account_title']) ?></span>
                        <span data-label="Number / IBAN" style="font-family: monospace; font-size: 13.5px; color: var(--text-muted); letter-spacing: 0.5px;"><?= htmlspecialchars($row['account_number']) ?></span>
                        <div data-label="Status">
                            <a href="manage_bank_accounts.php?action=toggle&id=<?= $row['id'] ?>" style="text-decoration:none;">
                                <span class="badge-status <?= $row['status'] === 'active' ? 'active' : 'inactive' ?>">
                                    <?= htmlspecialchars($row['status']) ?>
                                </span>
                            </a>
                        </div>
                        <span data-label="Added" style="color: #888; font-size: 13px;"><?= timeAgo($row['created_at']) ?></span>
                        <div style="display:flex; gap:6px; align-items:center;">
                            <button class="dots" onclick="openViewModal(<?= $row['id'] ?>)" title="View"><i class="fas fa-eye"></i></button>
                            <button class="dots" onclick="openEditModal(<?= $row['id'] ?>)" title="Edit"><i class="fas fa-pencil-alt"></i></button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this bank account? This cannot be undone.');">
                                <input type="hidden" name="action" value="delete_account">
                                <input type="hidden" name="account_id" value="<?= $row['id'] ?>">
                                <button type="submit" class="dots" title="Delete" style="color:#ef4444;"><i class="fas fa-trash-alt"></i></button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align:center; padding:60px 24px; color:#888;">
                    No bank accounts registered yet. <a href="#" onclick="openAddModal(); return false;">Add your first account →</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add / Edit Modal -->
<div id="accountModal" class="modal-overlay" onclick="if(event.target===this)closeModal('accountModal')">
    <div class="modal-content">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon" id="modalIcon"><i class="fas fa-university"></i></div>
                <span id="modalTitle">Add New Bank Account</span>
            </h4>
            <button class="modal-close" onclick="closeModal('accountModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" id="formAction" value="add_account">
            <input type="hidden" name="account_id" id="formAccountId" value="">
            <div class="modal-body">
                <!-- Bank Select Dropdown -->
                <div class="form-group">
                    <label>Select Bank <span style="color:#ef4444;">*</span></label>
                    <select name="bank_name" id="fBank" required>
                        <option value="">— Select Bank —</option>
                        <?php foreach ($banks_list as $bank): ?>
                            <option value="<?= htmlspecialchars($bank) ?>"><?= htmlspecialchars($bank) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Account Title -->
                <div class="form-group">
                    <label>Account Title <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="account_title" id="fTitle" placeholder="e.g. Graphicafix Creative Agency" required>
                </div>

                <!-- Account Number / IBAN -->
                <div class="form-group">
                    <label>Account Number / IBAN <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="account_number" id="fNumber" placeholder="e.g. PK89 MEZN 0012 3456 7890" style="font-family: monospace;" required>
                </div>

                <!-- Status -->
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="fStatus">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-custom" onclick="closeModal('accountModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-custom" id="saveBtn"><i class="fas fa-save"></i> Save Account</button>
            </div>
        </form>
    </div>
</div>

<!-- View Details Modal -->
<div id="viewModal" class="modal-overlay" onclick="if(event.target===this)closeModal('viewModal')">
    <div class="modal-content">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon"><i class="fas fa-eye"></i></div>
                <span>Bank Account Details</span>
            </h4>
            <button class="modal-close" onclick="closeModal('viewModal')">×</button>
        </div>
        <div class="modal-body">
            <div style="text-align:center; padding-bottom:20px; margin-bottom:20px; border-bottom:1px solid #f0f0f0;">
                <div id="vBankLogo" style="width:72px; height:72px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:#f8fafc; border:2px solid #e2e8f0; margin:0 auto 12px;"></div>
                <div style="font-size:20px; font-weight:800;" id="vBankName">—</div>
            </div>
            <div style="display:flex; flex-direction:column; gap:14px;">
                <div style="background:#f9fbfc; border-radius:10px; padding:14px; display:flex; align-items:center; gap:12px;">
                    <div style="font-size:18px; color:var(--primary-color);"><i class="fas fa-id-card"></i></div>
                    <div>
                        <div style="font-size:12px; color:#888; font-weight:600;">Account Title</div>
                        <div style="font-size:14px; font-weight:700; color:var(--text-dark);" id="vTitle">—</div>
                    </div>
                </div>
                <div style="background:#f9fbfc; border-radius:10px; padding:14px; display:flex; align-items:center; gap:12px;">
                    <div style="font-size:18px; color:var(--primary-color);"><i class="fas fa-university"></i></div>
                    <div>
                        <div style="font-size:12px; color:#888; font-weight:600;">Account Number / IBAN</div>
                        <div style="font-size:14.5px; font-weight:700; font-family:monospace; color:var(--text-dark); letter-spacing:0.5px;" id="vNumber">—</div>
                    </div>
                </div>
                <div style="background:#f9fbfc; border-radius:10px; padding:14px; display:flex; align-items:center; gap:12px;">
                    <div style="font-size:18px; color:var(--primary-color);"><i class="fas fa-toggle-on"></i></div>
                    <div>
                        <div style="font-size:12px; color:#888; font-weight:600;">Status</div>
                        <div style="margin-top:2px;"><span id="vStatus" class="badge-status active">ACTIVE</span></div>
                    </div>
                </div>
                <div style="background:#f9fbfc; border-radius:10px; padding:14px; display:flex; align-items:center; gap:12px;">
                    <div style="font-size:18px; color:var(--primary-color);"><i class="fas fa-calendar-alt"></i></div>
                    <div>
                        <div style="font-size:12px; color:#888; font-weight:600;">Date Added</div>
                        <div style="font-size:14px; font-weight:700; color:var(--text-dark);" id="vSince">—</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary-custom" onclick="closeModal('viewModal')">Close</button>
            <button type="button" class="btn btn-primary-custom" id="vEditBtn"><i class="fas fa-pencil-alt"></i> Edit Account</button>
        </div>
    </div>
</div>

<!-- Toast container -->
<div id="toastContainer" style="position:fixed; bottom:24px; right:24px; z-index:9999; display:flex; flex-direction:column; gap:8px;"></div>

<script>
// Data structure of all bank accounts
const accountData = {
    <?php
    $accounts_result->data_seek(0);
    $rows = [];
    while ($r = $accounts_result->fetch_assoc()) {
        $rows[] = $r['id'] . ': ' . json_encode($r);
    }
    echo implode(",\n    ", $rows);
    ?>
};

function getBankLogoJs(bank) {
    switch (bank) {
        case 'Meezan Bank':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#8B2635" /><path d="M50,20 L50,80" stroke="#D4AF37" stroke-width="6" stroke-linecap="round" /><path d="M25,70 Q50,82 75,70" fill="none" stroke="#D4AF37" stroke-width="6" stroke-linecap="round" /><path d="M50,35 Q25,35 25,55 Q25,70 50,60" fill="none" stroke="#D4AF37" stroke-width="6" stroke-linecap="round" /><path d="M50,35 Q75,35 75,55 Q75,70 50,60" fill="none" stroke="#D4AF37" stroke-width="6" stroke-linecap="round" /></svg>';
        case 'Habib Bank Limited (HBL)':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><rect width="100" height="100" rx="15" fill="#005A5B" /><path d="M30,50 L50,30 L70,50 L50,70 Z" fill="#FFFFFF" /><circle cx="50" cy="50" r="8" fill="#FFD700" /></svg>';
        case 'Bank Alfalah':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#FFFFFF" stroke="#D2143A" stroke-width="4" /><path d="M30,50 C30,35 45,30 55,30 C65,30 70,38 70,45 C70,55 58,70 50,70 C42,70 30,60 30,50 Z" fill="#D2143A" /><path d="M45,45 C45,40 52,38 56,38 C60,38 62,42 62,45 C62,50 56,58 52,58 C48,58 45,50 45,45 Z" fill="#003366" /></svg>';
        case 'United Bank Limited (UBL)':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#0D47A1" /><circle cx="50" cy="50" r="25" fill="none" stroke="#FFD54F" stroke-width="8" /><line x1="50" y1="15" x2="50" y2="85" stroke="#FFD54F" stroke-width="8" /><line x1="15" y1="50" x2="85" y2="50" stroke="#FFD54F" stroke-width="8" /></svg>';
        case 'MCB Bank':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#FF8F00" /><path d="M30,70 Q45,30 70,30 Q65,65 30,70" fill="#2E7D32" /></svg>';
        case 'Allied Bank (ABL)':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#1565C0" /><text x="50" y="65" font-size="36" font-family="Arial" font-weight="900" fill="#FFFFFF" text-anchor="middle">ABL</text></svg>';
        case 'Standard Chartered':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#00838F" /><path d="M30,35 Q50,65 70,35" fill="none" stroke="#81C784" stroke-width="10" stroke-linecap="round" /><path d="M30,65 Q50,35 70,65" fill="none" stroke="#FFFFFF" stroke-width="10" stroke-linecap="round" /></svg>';
        case 'Faysal Bank':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#C62828" /><rect x="35" y="35" width="30" height="30" fill="#FFFFFF" transform="rotate(45 50 50)" /></svg>';
        case 'JazzCash':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#000000" /><circle cx="50" cy="50" r="30" fill="#FFC107" /><path d="M45,35 L55,35 L55,55 L45,55 Z" fill="#D32F2F" /></svg>';
        case 'Easypaisa':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#4CAF50" /><path d="M30,50 L45,65 L70,35" fill="none" stroke="#FFFFFF" stroke-width="12" stroke-linecap="round" stroke-linejoin="round" /></svg>';
        case 'SadaPay':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#FF5A5F" /><text x="50" y="65" font-size="32" font-family="Arial" font-weight="900" fill="#FFFFFF" text-anchor="middle">Sada</text></svg>';
        case 'NayaPay':
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#00C853" /><text x="50" y="65" font-size="32" font-family="Arial" font-weight="900" fill="#FFFFFF" text-anchor="middle">Naya</text></svg>';
        default:
            return '<svg viewBox="0 0 100 100" width="24" height="24" style="vertical-align:middle;"><circle cx="50" cy="50" r="48" fill="#37474F" /><path d="M25,75 L75,75 L75,70 L25,70 Z M30,65 L35,65 L35,45 L30,45 Z M45,65 L50,65 L50,45 L45,45 Z M60,65 L65,65 L65,45 L60,45 Z M25,40 L75,40 L50,20 Z" fill="#FFFFFF" /></svg>';
    }
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Bank Account';
    document.getElementById('formAction').value       = 'add_account';
    document.getElementById('formAccountId').value    = '';
    document.getElementById('saveBtn').innerHTML      = '<i class="fas fa-save"></i> Save Account';
    
    // Clear inputs
    document.getElementById('fBank').value = '';
    document.getElementById('fTitle').value = '';
    document.getElementById('fNumber').value = '';
    document.getElementById('fStatus').value = 'active';
    
    openModal('accountModal');
}

function openEditModal(id) {
    const a = accountData[id];
    if (!a) return;
    
    document.getElementById('modalTitle').textContent = 'Edit Bank Account';
    document.getElementById('formAction').value       = 'edit_account';
    document.getElementById('formAccountId').value    = id;
    document.getElementById('saveBtn').innerHTML      = '<i class="fas fa-pencil-alt"></i> Update Account';
    
    // Populate form
    document.getElementById('fBank').value = a.bank_name || '';
    document.getElementById('fTitle').value = a.account_title || '';
    document.getElementById('fNumber').value = a.account_number || '';
    document.getElementById('fStatus').value = a.status || 'active';
    
    openModal('accountModal');
}

function openViewModal(id) {
    const a = accountData[id];
    if (!a) return;
    
    document.getElementById('vBankLogo').innerHTML   = getBankLogoJs(a.bank_name);
    document.getElementById('vBankName').textContent = a.bank_name;
    document.getElementById('vTitle').textContent    = a.account_title;
    document.getElementById('vNumber').textContent   = a.account_number;
    
    const statusEl = document.getElementById('vStatus');
    statusEl.textContent = a.status.toUpperCase();
    statusEl.className = 'badge-status ' + (a.status === 'active' ? 'active' : 'inactive');
    
    // Format Date Added
    if (a.created_at) {
        const parts = a.created_at.split(' ');
        const dateParts = parts[0].split('-');
        const d = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]);
        const formatted = d.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        document.getElementById('vSince').textContent = formatted;
    } else {
        document.getElementById('vSince').textContent = '—';
    }
    
    document.getElementById('vEditBtn').onclick = function() {
        closeModal('viewModal');
        openEditModal(id);
    };
    
    openModal('viewModal');
}

function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}

function showToast(msg, type = 'success') {
    const t = document.createElement('div');
    t.style.cssText = `background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);font-size:13.5px;min-width:240px;border-left:4px solid ${type==='success'?'#10b981':'#ef4444'};`;
    t.innerHTML = `<span style="font-weight:700;color:${type==='success'?'#10b981':'#ef4444'}">${type==='success'?'<i class="fas fa-check"></i>':'<i class="fas fa-times"></i>'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3000);
}
</script>

<?php if ($toast_message): ?>
<script>
document.addEventListener('DOMContentLoaded', () => showToast(<?= json_encode($toast_message) ?>, <?= json_encode($toast_type) ?>));
</script>
<?php endif; ?>

<?php include 'dashboard_footer.php'; ?>
