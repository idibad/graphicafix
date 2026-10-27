<?php
include 'dashboard_header.php';

// Verify role access
if ($role !== 'client') {
    header("Location: index.php");
    exit;
}

$client_id = intval($data['client_id'] ?? 0);
if ($client_id <= 0) {
    echo "<div class='alert alert-warning' style='margin: 40px;'>Your account is not linked to any client company. Please contact the administrator.</div>";
    include 'dashboard_footer.php';
    exit;
}

// ── Handle Payment Proof Upload ──────────────────────────────────────────────
if (isset($_POST['upload_proof']) && isset($_POST['invoice_id'])) {
    $inv_id = intval($_POST['invoice_id']);
    
    // Verify invoice belongs to client
    $check = $conn->prepare("SELECT id FROM invoices WHERE id = ? AND client_id = ?");
    $check->bind_param("ii", $inv_id, $client_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        if (!empty($_FILES['proof_screenshot']['name'])) {
            $upload_dir = "uploads/payments/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_name = time() . "_" . basename($_FILES['proof_screenshot']['name']);
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['proof_screenshot']['tmp_name'], $file_path)) {
                $stmt_upd = $conn->prepare("UPDATE invoices SET payment_screenshot = ?, payment_uploaded_at = NOW() WHERE id = ?");
                $stmt_upd->bind_param("si", $file_path, $inv_id);
                if ($stmt_upd->execute()) {
                    // Send notification to admin/PM
                    require_once __DIR__ . '/../core/task_email_helper.php';
                    // Fetch admins
                    $admins = $conn->query("SELECT email FROM users WHERE role = 'admin' OR role = 'pm'");
                    $subject = "Payment Receipt Uploaded: Invoice #{$inv_id}";
                    $heading = "New Payment Proof Uploaded";
                    $body = "<p>Client contact <strong>" . htmlspecialchars($name) . "</strong> has uploaded a payment proof screenshot for Invoice #<strong>" . intval($inv_id) . "</strong>.</p>
                             <p>Please review and approve the payment in the admin panel.</p>";
                    while ($adm = $admins->fetch_assoc()) {
                        if (!empty($adm['email'])) {
                            sendClientEmail($adm['email'], $subject, $heading, $body, 0);
                        }
                    }
                    echo "<script>alert('Payment proof uploaded successfully! Our team will review and approve it shortly.'); window.location='client_invoices.php';</script>";
                    exit;
                }
            }
        }
    }
}

// Fetch invoices
$stmt = $conn->prepare("
    SELECT i.*, p.project_name 
    FROM invoices i
    LEFT JOIN projects p ON i.project_id = p.id
    WHERE i.client_id = ? AND i.status != 'draft'
    ORDER BY i.created_at DESC
");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$invoices_result = $stmt->get_result();
?>

<div class="height-100">
    <div class="projects-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title-section">
                <h1>Billing & Invoices</h1>
                <p>View your billing history, upload manual bank transfer receipts, and download paid receipts</p>
            </div>
        </div>

        <!-- Invoices List -->
        <div class="card" style="padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; background: white;">
            <?php if ($invoices_result->num_rows > 0): ?>
                <div style="overflow-x:auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse; font-size: 14.5px;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; color: #475569; font-weight:700;">
                                <th style="padding: 12px 10px;">Invoice #</th>
                                <th style="padding: 12px 10px;">Project</th>
                                <th style="padding: 12px 10px;">Issue Date</th>
                                <th style="padding: 12px 10px;">Due Date</th>
                                <th style="padding: 12px 10px;">Total Amount</th>
                                <th style="padding: 12px 10px;">Status</th>
                                <th style="padding: 12px 10px;">Payment Proof</th>
                                <th style="padding: 12px 10px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($inv = $invoices_result->fetch_assoc()): 
                                $statusLabel = ucfirst($inv['status']);
                                $statusStyle = ($inv['status'] === 'paid' ? 'background:#ecfdf5;color:#10b981;' : ($inv['status'] === 'overdue' ? 'background:#fef2f2;color:#ef4444;' : 'background:#fef3c7;color:#d97706;'));
                            ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 14px 10px; font-weight:700; color:#1e293b;"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                                    <td style="padding: 14px 10px; color:#475569;"><?= htmlspecialchars($inv['project_name'] ?? 'General Agency Account') ?></td>
                                    <td style="padding: 14px 10px;"><?= date('M d, Y', strtotime($inv['issue_date'])) ?></td>
                                    <td style="padding: 14px 10px;">
                                        <?= date('M d, Y', strtotime($inv['due_date'])) ?>
                                        <?php if ($inv['status'] === 'overdue'): ?>
                                            <div style="font-size:11px; color:#ef4444; font-weight:600; margin-top:2px;">Overdue</div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 14px 10px; font-weight:700; color:#1e293b;">
                                        <?= $inv['currency'] ?> <?= number_format($inv['total_amount'], 2) ?>
                                    </td>
                                    <td style="padding: 14px 10px;">
                                        <span class="badge" style="padding: 4px 10px; border-radius: 12px; font-size:11.5px; font-weight:700; <?= $statusStyle ?>"><?= $statusLabel ?></span>
                                    </td>
                                    <td style="padding: 14px 10px;">
                                        <?php if ($inv['payment_screenshot']): ?>
                                             <div style="display:flex; flex-direction:column; gap:4px;">
                                                 <a href="javascript:void(0)" onclick="showScreenshot('<?= htmlspecialchars($inv['payment_screenshot']) ?>')" style="color: #024442; font-weight: 700; text-decoration: underline;">
                                                     <i class="fas fa-image"></i> View Screenshot
                                                 </a>
                                                <span style="font-size:11px; color:#64748b;">Uploaded on <?= date('M d, Y', strtotime($inv['payment_uploaded_at'])) ?></span>
                                            </div>
                                        <?php elseif (in_array($inv['status'], ['sent', 'overdue'])): ?>
                                            <form method="POST" enctype="multipart/form-data" style="display:flex; gap:8px; align-items:center;">
                                                <input type="hidden" name="upload_proof" value="1">
                                                <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                                <input type="file" name="proof_screenshot" accept="image/*" style="font-size: 11.5px; max-width: 140px;" required>
                                                <button type="submit" class="btn btn-secondary btn-sm" style="font-size: 11.5px; padding: 4px 8px; background: #024442; color: white; border: none; border-radius:4px; font-weight:700;">Upload</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:#aaa; font-style:italic;">Closed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 14px 10px; text-align: center;">
                                        <a href="generate_invoice_pdf.php?id=<?= $inv['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" style="font-size:12.5px; padding:6px 12px; border-radius:6px; font-weight:700; color:#334155; border:1px solid #cbd5e1; background:#f8fafc; text-decoration:none;">
                                            <i class="fas fa-file-pdf"></i> Download PDF
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; color: #94a3b8; padding: 40px;">
                    <i class="fas fa-file-invoice-dollar" style="font-size:40px; color:#cbd5e1; margin-bottom:12px;"></i>
                    <p>No billing invoices are currently posted for your company.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showScreenshot(src) {
    document.getElementById('screenshotImg').style.display = 'none';
    document.getElementById('screenshotSpinner').style.display = 'flex';
    document.getElementById('screenshotImg').src = src;
    const modal = document.getElementById('screenshotModal');
    modal.style.display = 'flex';
}
function closeScreenshotModal() {
    document.getElementById('screenshotModal').style.display = 'none';
}
</script>

<!-- Screenshot Preview Modal -->
<div id="screenshotModal" class="modal-overlay" onclick="if(event.target===this)closeScreenshotModal()" style="display:none;">
    <div class="modal-content" style="padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:8px; margin-bottom:12px;">
            <h4 style="margin:0; color:#1e293b;"><i class="fas fa-image"></i> Payment Proof Screenshot</h4>
            <span style="font-size:24px; font-weight:bold; cursor:pointer;" onclick="closeScreenshotModal()">&times;</span>
        </div>
        <div style="text-align:center; position:relative; min-height:150px; display:flex; align-items:center; justify-content:center;">
            <div id="screenshotSpinner" style="display:flex; align-items:center; justify-content:center; position:absolute; left:0; right:0; top:0; bottom:0;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #024442;"></i>
            </div>
            <img id="screenshotImg" src="" alt="Screenshot Proof" style="max-width:100%; max-height:450px; object-fit:contain; border-radius:6px; border:1px solid #cbd5e1; display:none;" onload="document.getElementById('screenshotSpinner').style.display='none'; this.style.display='inline-block';">
        </div>
    </div>
</div>

<?php include 'dashboard_footer.php'; ?>
