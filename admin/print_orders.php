<?php
/**
 * admin/print_orders.php
 * Print Vendor Production Orders, Status Tracking & PO Management
 * Styled with Graphicafix Global Design System
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/print_db.php';

// Authentication check
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? '';
$user_id  = $_SESSION['user_id'] ?? 0;
$role     = strtolower($_SESSION['role'] ?? 'user');

// Ensure tables exist
if (isset($conn) && $conn instanceof mysqli) {
    init_print_database($conn);
}

// Handle Status Update
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_status = trim($_POST['order_status'] ?? '');
    $allowed = ['assigned', 'acknowledged', 'in_production', 'qc_ready', 'dispatched', 'delivered', 'cancelled'];
    if ($order_id > 0 && in_array($new_status, $allowed)) {
        $stmt = $conn->prepare("UPDATE print_vendor_orders SET order_status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $order_id);
        $stmt->execute();
        $msg = "Order status updated to " . ucfirst(str_replace('_', ' ', $new_status));
    }
}

// Query Orders
$status_filter = trim($_GET['status'] ?? 'all');
$where = "1=1";
if ($status_filter !== 'all' && !empty($status_filter)) {
    $where .= " AND pvo.order_status = '" . $conn->real_escape_string($status_filter) . "'";
}

$orders_query = "SELECT pvo.*, pv.company_name AS vendor_name, pv.whatsapp AS vendor_whatsapp, pv.city AS vendor_city
                 FROM print_vendor_orders pvo
                 JOIN print_vendors pv ON pv.id = pvo.vendor_id
                 WHERE {$where}
                 ORDER BY pvo.id DESC";
$orders_res = $conn->query($orders_query);
$orders = [];
$total_po_value = 0;
$total_margin_value = 0;

while ($o = $orders_res->fetch_assoc()) {
    $orders[] = $o;
    $total_po_value += floatval($o['vendor_total_cost']);
    $total_margin_value += floatval($o['gross_margin_amount']);
}

include 'dashboard_header.php';
?>

<div class="height-100">

    <?php if ($msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius:10px;">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 style="font-size:24px; font-weight:800; color:#111; margin:0 0 4px 0;">
                <i class="fas fa-boxes" style="color:var(--primary, #024442);"></i> Print Production Orders (POs)
            </h1>
            <p style="font-size:13.5px; color:#6b7280; margin:0;">
                Track purchase orders assigned to print vendors, production deadlines, and gross profit margins.
            </p>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <a href="print_vendors.php" class="btn btn-secondary">
                <i class="fas fa-warehouse"></i> Print Vendors
            </a>
            <a href="print_procurement.php" class="btn btn-primary" style="font-weight:700;">
                <i class="fas fa-search-dollar"></i> New Procurement / Order
            </a>
        </div>
    </div>

    <!-- Quick Stats Grid (Global Graphicafix Style) -->
    <div class="inv-stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom:20px;">
        <div class="inv-stat-card">
            <div class="inv-stat-icon" style="background:#f0f9f8; color:#024442;"><i class="fas fa-file-invoice"></i></div>
            <div>
                <div class="inv-stat-num"><?= count($orders) ?></div>
                <div class="inv-stat-label">Total Purchase Orders</div>
                <div class="inv-stat-sub">Assigned to vendors</div>
            </div>
        </div>
        <div class="inv-stat-card">
            <div class="inv-stat-icon" style="background:#eff6ff; color:#0284c7;"><i class="fas fa-truck-loading"></i></div>
            <div>
                <div class="inv-stat-num" style="color:#0284c7;">PKR <?= number_format($total_po_value) ?></div>
                <div class="inv-stat-label">Vendor Commitments</div>
                <div class="inv-stat-sub">Total vendor cost</div>
            </div>
        </div>
        <div class="inv-stat-card">
            <div class="inv-stat-icon" style="background:#f0fff4; color:#22c55e;"><i class="fas fa-chart-line"></i></div>
            <div>
                <div class="inv-stat-num" style="color:#16a34a;">PKR <?= number_format($total_margin_value) ?></div>
                <div class="inv-stat-label">Gross Margin Realized</div>
                <div class="inv-stat-sub">Agency profit earned</div>
            </div>
        </div>
    </div>

    <!-- Status Filters Bar -->
    <div class="inv-filter-bar" style="margin-bottom:14px;">
        <?php foreach (['all'=>'All Orders', 'assigned'=>'Assigned', 'in_production'=>'In Production', 'qc_ready'=>'QC Ready', 'delivered'=>'Delivered', 'cancelled'=>'Cancelled'] as $k => $label): ?>
            <a href="?status=<?= $k ?>" class="inv-filter-btn <?= $status_filter===$k ? 'active' : '' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Orders Table Card -->
    <div class="card" style="padding:0; overflow:hidden; border-radius:14px; border:1px solid #e5e7eb; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-bottom:24px; background:#fff;">
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>PO Number & Date</th>
                        <th>Client Reference</th>
                        <th>Print Vendor</th>
                        <th>Product & Specs</th>
                        <th>Financials</th>
                        <th>Production Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:45px 20px; color:#888;">
                                <i class="fas fa-inbox fa-2x" style="color:#ccc; margin-bottom:10px; display:block;"></i>
                                No purchase orders found in this view. Open <a href="print_procurement.php" style="color:#024442; font-weight:700;">Print Procurement</a> to search rates and click "Click to Order".
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): 
                            $specs = json_decode($o['specifications_json'], true) ?: [];
                            $wa_num = preg_replace('/[^0-9]/', '', $o['vendor_whatsapp']);

                            // Match native Graphicafix badge classes
                            $st = $o['order_status'];
                            $stBadge = match($st) {
                                'assigned'      => 'status-in-progress',
                                'acknowledged'  => 'status-in-progress',
                                'in_production' => 'status-on-hold',
                                'qc_ready'      => 'status-on-hold',
                                'dispatched'    => 'status-completed',
                                'delivered'     => 'status-completed',
                                'cancelled'     => 'status-not-started',
                                default         => 'status-default'
                            };
                        ?>
                            <tr>
                                <td>
                                    <span class="inv-number-link"><?= htmlspecialchars($o['po_number']) ?></span>
                                    <div style="font-size:11.5px; color:#888;">
                                        <?= date('M d, Y', strtotime($o['created_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:700; color:#111; font-size:13.5px;"><?= htmlspecialchars($o['client_name']) ?></div>
                                    <?php if ($o['quotation_id']): ?>
                                        <div style="font-size:11.5px; margin-top:2px;">
                                            <a href="quotation_details.php?id=<?= $o['quotation_id'] ?>" style="color:#0284c7; text-decoration:none; font-weight:600;">
                                                <i class="fas fa-file-invoice"></i> Linked Quote
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight:600; color:#333; font-size:13px;"><?= htmlspecialchars($o['vendor_name']) ?></div>
                                    <div style="font-size:11.5px; color:#888;">
                                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($o['vendor_city']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:700; color:#024442; font-size:13px;"><?= htmlspecialchars($o['product_name']) ?></div>
                                    <div style="font-size:11.5px; color:#666;">
                                        Qty: <strong><?= number_format($o['quantity']) ?></strong> &bull; <?= htmlspecialchars($specs['paper_stock'] ?? '') ?>
                                    </div>
                                    <div style="font-size:11px; color:#d97706; margin-top:2px; font-weight:600;">
                                        Due: <strong><?= date('M d, Y', strtotime($o['delivery_deadline'])) ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:13.5px; color:#444;">Vendor: <strong style="color:#111;">PKR <?= number_format($o['vendor_total_cost']) ?></strong></div>
                                    <div style="font-size:12px; color:#16a34a; font-weight:700;">+PKR <?= number_format($o['gross_margin_amount']) ?> (<?= $o['gross_margin_percentage'] ?>%)</div>
                                </td>
                                <td>
                                    <span class="badge <?= $stBadge ?>">
                                        <i class="fas fa-circle" style="font-size:6px;"></i> <?= ucfirst(str_replace('_', ' ', $st)) ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex; gap:6px;">
                                        <?php if ($wa_num): ?>
                                            <a href="https://wa.me/<?= $wa_num ?>" target="_blank" class="action-btn" style="color:#25d366;" title="Chat Vendor on WhatsApp">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick='openStatusModal(<?= $o['id'] ?>, "<?= $st ?>")' style="padding:3px 9px; font-size:11.5px; font-weight:600;" title="Update Status">
                                            <i class="fas fa-tasks"></i> Status
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius:14px; border:none; box-shadow:0 15px 35px rgba(0,0,0,0.18);">
            <div class="modal-header" style="background:#024442; color:#fff; padding:12px 18px;">
                <h6 class="modal-title" style="font-size:14px; font-weight:700;"><i class="fas fa-tasks"></i> Update PO Status</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="update_order_status" value="1">
                <input type="hidden" name="order_id" id="modalOrderId" value="0">
                <div class="modal-body" style="padding:16px;">
                    <label class="form-label" style="font-weight:700; font-size:12px; color:#555;">Select New Status:</label>
                    <select name="order_status" id="modalOrderStatus" class="form-select form-select-sm" style="font-weight:600;">
                        <option value="assigned">Assigned to Vendor</option>
                        <option value="acknowledged">Vendor Acknowledged</option>
                        <option value="in_production">In Production</option>
                        <option value="qc_ready">QC & Ready for Dispatch</option>
                        <option value="dispatched">Dispatched / Out for Delivery</option>
                        <option value="delivered">Delivered & Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div style="padding:10px 16px 16px; border-top:1px solid #eee; background:#fafafa;">
                    <button type="submit" class="btn btn-primary btn-sm w-100" style="font-weight:700; background:#024442; border-color:#024442;">
                        Save Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Page Header */
.page-header { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:14px; margin-bottom:20px; }
.page-header h1 { margin:0 0 4px; }
.page-header p  { margin:0; color:#888; font-size:14px; }

/* Filter bar */
.inv-filter-bar { display:flex; gap:8px; flex-wrap:wrap; }
.inv-filter-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:100px; border:1.5px solid #e0e0e0; background:transparent; font-size:12.5px; font-weight:600; color:#555; text-decoration:none; transition:all .2s; white-space:nowrap; }
.inv-filter-btn:hover, .inv-filter-btn.active { background:#024442; border-color:#024442; color:#fff; }

/* Stats Grid */
.inv-stats-grid { display:grid; gap:14px; }
.inv-stat-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px 18px; display:flex; align-items:center; gap:14px; box-shadow:0 1px 3px rgba(0,0,0,0.04); }
.inv-stat-icon { width:46px; height:46px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.inv-stat-num  { font-size:22px; font-weight:800; color:#111; line-height:1; }
.inv-stat-label{ font-size:12.5px; color:#6b7280; font-weight:600; margin-top:4px; }
.inv-stat-sub  { font-size:11.5px; color:#9ca3af; margin-top:2px; }

/* Data Table */
.data-table { width:100%; border-collapse:collapse; font-size:13.5px; }
.data-table th { background:#f9fafb; padding:12px 16px; text-align:left; font-size:11.5px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.06em; border-bottom:1px solid #ebebeb; white-space:nowrap; }
.data-table td { padding:14px 16px; border-bottom:1px solid #f5f5f5; color:#333; vertical-align:middle; }
.data-table tr:last-child td { border-bottom:none; }
.data-table tr:hover td { background:#fafafa; }

.inv-number-link { font-weight:700; color:#024442; text-decoration:none; font-family:'Courier New',monospace; font-size:13px; }
.inv-number-link:hover { text-decoration:underline; }

/* Badges */
.status-completed   { background:#d1fae5; color:#065f46; font-weight:700; font-size:11px; padding:4px 8px; border-radius:12px; }
.status-in-progress { background:#dbeafe; color:#1e40af; font-weight:700; font-size:11px; padding:4px 8px; border-radius:12px; }
.status-on-hold     { background:#fef3c7; color:#92400e; font-weight:700; font-size:11px; padding:4px 8px; border-radius:12px; }
.status-not-started { background:#fee2e2; color:#991b1b; font-weight:700; font-size:11px; padding:4px 8px; border-radius:12px; }
.status-default     { background:#f3f4f6; color:#6b7280; font-weight:700; font-size:11px; padding:4px 8px; border-radius:12px; }

.action-btn { width:28px; height:28px; border-radius:6px; border:1px solid #e5e7eb; background:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; text-decoration:none; transition:all .15s; cursor:pointer; color:#555; }
.action-btn:hover { background:#f0f5f4; border-color:#024442; color:#024442; }
</style>

<script>
function openStatusModal(orderId, currentStatus) {
    document.getElementById("modalOrderId").value = orderId;
    document.getElementById("modalOrderStatus").value = currentStatus;
    new bootstrap.Modal(document.getElementById("statusModal")).show();
}
</script>

<?php include 'dashboard_footer.php'; ?>