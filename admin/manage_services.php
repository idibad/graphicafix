<?php
include('dashboard_header.php');

// ------------------ HANDLE DISCOUNT CRUD ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_discount' || $action === 'edit_discount') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $title = trim($_POST['title'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $discount_percent = floatval($_POST['discount_percent'] ?? 0);
        $status = trim($_POST['status'] ?? 'active');
        $expires_date = !empty($_POST['expires_date']) ? $_POST['expires_date'] : NULL;

        if ($action === 'add_discount') {
            $stmt = $conn->prepare("INSERT INTO discounts (title, name, description, code, discount_percent, status, expires_date, applied_count, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())");
            if($stmt) {
                $stmt->bind_param("ssssdss", $title, $name, $description, $code, $discount_percent, $status, $expires_date);
                $stmt->execute();
            }
        } else {
            $stmt = $conn->prepare("UPDATE discounts SET title=?, name=?, description=?, code=?, discount_percent=?, status=?, expires_date=?, updated_at=NOW() WHERE id=?");
            if($stmt) {
                $stmt->bind_param("ssssdssi", $title, $name, $description, $code, $discount_percent, $status, $expires_date, $id);
                $stmt->execute();
            }
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=ms-discounts");
        exit;
    } 
    
    if ($action === 'delete_discount') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM discounts WHERE id = ?");
            if($stmt) {
                $stmt->bind_param("i", $id);
                $stmt->execute();
            }
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=ms-discounts");
        exit;
    }
}

// Persist active tab after form submission
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'ms-services';


// ------------------ STATS ------------------

// Active Services
$result = mysqli_query($conn, "SELECT COUNT(*) AS active_services, SUM(packages_count) AS total_packages, SUM(orders_count) AS total_orders 
                               FROM services WHERE status='active'");
$service_stats = mysqli_fetch_assoc($result);

// Active Discounts
$result = mysqli_query($conn, "SELECT COUNT(*) AS active_discounts FROM discounts WHERE status='active'");
$discount_stats = mysqli_fetch_assoc($result);

/* ------------------ FETCH SERVICES ------------------ */
$services = [];
$result = mysqli_query($conn, "SELECT * FROM services ORDER BY id DESC");
$services_count = mysqli_num_rows($result);
while ($row = mysqli_fetch_assoc($result)) {
    $services[] = $row;
}

/* ------------------ FETCH DISCOUNTS ------------------ */
$discounts = [];
$result = $conn->query("SELECT * FROM discounts ORDER BY id DESC");
$discounts_count = mysqli_num_rows($result);
while ($row = $result->fetch_assoc()) {
    $discounts[] = $row;
}
?>

<style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .manage-services-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
            gap: 20px;
            flex-wrap: wrap;
        }

        .header-content h1 {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 6px;
        }

        .header-content p {
            color: var(--gray-600);
            font-size: 15px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 11px 20px;
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #035b58;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-accent {
            background: var(--accent);
            color: var(--dark);
        }

        .btn-accent:hover {
            background: #a8e856;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-secondary:hover {
            background: var(--light);
        }

        .btn-icon {
            font-size: 18px;
        }

        /* Quick Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--gray-600);
            font-weight: 500;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .stat-change {
            font-size: 13px;
            font-weight: 500;
        }

        .stat-change.positive {
            color: var(--success);
        }

        .stat-change.negative {
            color: var(--danger);
        }

        /* Tabs */
        .ms-tabs-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            margin-bottom: 24px;
            overflow: hidden;
        }

        .ms-tabs {
            display: flex;
            border-bottom: 1px solid var(--gray-200);
            overflow-x: auto;
            scrollbar-width: none;
        }

        .ms-tabs::-webkit-scrollbar {
            display: none;
        }

        .ms-tab {
            padding: 16px 24px;
            background: none;
            border: none;
            width: 50%;
            color: var(--gray-600);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .ms-tab:hover {
            color: var(--primary);
            background: var(--gray-50);
        }

        .ms-tab.active {
            color: var(--primary);
            border-bottom-color: var(--accent);
            background: var(--light);
        }

        .ms-tab-badge {
            display: inline-block;
            background: var(--gray-200);
            color: var(--gray-700);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 8px;
        }

        .ms-tab.active .ms-tab-badge {
            background: var(--accent);
            color: var(--dark);
        }

        .ms-tab-content {
            padding: 24px;
        }

        /* Services Grid */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .service-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .service-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary), #035b58);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 28px;
            box-shadow: var(--shadow-md);
        }

        .service-menu {
            background: none;
            border: none;
            color: var(--gray-400);
            font-size: 20px;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: var(--radius-sm);
            transition: all 0.2s;
        }

        .service-menu:hover {
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .service-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .service-description {
            color: var(--gray-600);
            font-size: 14px;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .service-meta {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--gray-200);
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .meta-label {
            font-size: 12px;
            color: var(--gray-500);
            font-weight: 500;
        }

        .meta-value {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
        }

        .service-tags {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .tag {
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
        }

        .tag-active {
            background: var(--success-light);
            color: var(--success);
        }

        .tag-draft {
            background: var(--warning-light);
            color: var(--warning);
        }

        .tag-discount {
            background: var(--danger-light);
            color: var(--danger);
        }

        .tag-new {
            background: var(--light);
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .service-footer {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
            border-radius: var(--radius);
        }

        .btn-outline {
            flex: 1;
            background: white;
            color: var(--primary);
            border: 1px solid var(--gray-300);
            transition: all 0.2s;
        }

        .btn-outline:hover {
            background: var(--light);
            border-color: var(--primary);
        }

        /* Discounts Section */
        .discount-card {
            background: linear-gradient(135deg, var(--primary) 0%, #035b58 100%);
            color: white;
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-bottom: 16px;
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .discount-card::after {
            content: '<i class="fas fa-tada"></i>';
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 64px;
            opacity: 0.1;
            pointer-events: none;
        }
        
        .discount-card-content {
            z-index: 1;
            width: 100%;
        }

        .discount-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .discount-badge {
            background: var(--accent);
            color: var(--dark);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 700;
        }

        .discount-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .discount-percent {
            font-size: 18px;
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 8px;
        }

        .discount-description {
            opacity: 0.9;
            margin-bottom: 16px;
        }

        .discount-meta {
            display: flex;
            gap: 24px;
            font-size: 14px;
            flex-wrap: wrap;
        }

        .discount-meta span {
            opacity: 0.9;
        }

        .discount-meta strong {
            opacity: 1;
            margin-left: 4px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: var(--radius-xl);
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--gray-400);
            cursor: pointer;
            padding: 4px;
            border-radius: var(--radius-sm);
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .modal-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius);
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
        }

        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard { padding: 20px 16px; }
            .manage-services-header { flex-direction: column; align-items: stretch; }
            .header-actions { width: 100%; }
            .btn { flex: 1; justify-content: center; }
            .services-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .form-row { grid-template-columns: 1fr; }
            .header-content h1 { font-size: 24px; }
            .ms-tab { width: 50%; text-align: center; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .service-meta { flex-direction: column; gap: 8px; }
            .discount-meta { flex-direction: column; gap: 8px; }
        }
</style>

<div class="height-100" >
   <div class="services-page">

    <div class="manage-services-header">
            <div class="header-content">
                <h1>Services Management</h1>
                <p>Manage services, packages, and discounts</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-accent" onclick="openDiscountModal()">
                    <span class="btn-icon"><i class="fas fa-tag"></i>️</span>
                    Create Discount
                </button>
                <button class="btn btn-primary" onclick="window.location='service_add.php'">
                    <span class="btn-icon">+</span>
                    Add Service
                </button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Active Services</span>
                    <div class="stat-icon" style="background: var(--success-light); color: var(--success);"><i class="fas fa-box"></i></div>
                </div>
                <div class="stat-value"><?php echo $service_stats['active_services'] ?? 0; ?></div>
                <div class="stat-change positive">↑ <?php echo $service_stats['total_orders'] ?? 0; ?> orders</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Total Packages</span>
                    <div class="stat-icon" style="background: var(--light); color: var(--primary);"><i class="fas fa-clipboard"></i></div>
                </div>
                <div class="stat-value"><?php echo $service_stats['total_packages'] ?? 0; ?></div>
                <div class="stat-change positive">↑ <?php echo $service_stats['total_packages'] ?? 0; ?> new packages</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Active Discounts</span>
                    <div class="stat-icon" style="background: var(--danger-light); color: var(--danger);"><i class="fas fa-bullseye"></i></div>
                </div>
                <div class="stat-value"><?php echo $discount_stats['active_discounts'] ?? 0; ?></div>
                <div class="stat-change"><?php echo $discount_stats['active_discounts'] ?? 0; ?> currently running</div>
            </div>
        </div>

        <div class="ms-tabs-container">
            <div class="ms-tabs">
                <button class="ms-tab <?php echo ($active_tab === 'ms-services') ? 'active' : ''; ?>" onclick="switchTab(event, 'ms-services')" >
                    Services
                    <span class="ms-tab-badge"><?php echo $service_stats['active_services'] ?? 0; ?></span>
                </button>

                <button class="ms-tab <?php echo ($active_tab === 'ms-discounts') ? 'active' : ''; ?>" onclick="switchTab(event, 'ms-discounts')">
                    Discounts
                    <span class="ms-tab-badge"><?php echo $discount_stats['active_discounts'] ?? 0; ?></span>
                </button>
            </div>

            <div class="ms-tab-contents">

                <div id="ms-services" class="ms-tab-content <?php echo ($active_tab === 'ms-services') ? 'active' : ''; ?>" style="display: <?php echo ($active_tab === 'ms-services') ? 'block' : 'none'; ?>;">
                    <div class="services-grid">
                        <?php foreach($services as $service): ?>
                            <div class="service-card">
                                <div class="service-header">
                                    <div class="service-icon" style="background: <?php echo $service['icon_style'] ?? '#eee'; ?>">
                                        <i class="fa <?php echo htmlspecialchars($service['icon']); ?>"></i>
                                    </div>
                                    <button class="service-menu">⋮</button>
                                </div>

                                <h3 class="service-title"><?php echo htmlspecialchars($service['title']); ?></h3>
                                <p class="service-description"><?php echo htmlspecialchars($service['description']); ?></p>

                                <div class="service-meta">
                                    <div class="meta-item">
                                        <span class="meta-label">Packages</span>
                                        <span class="meta-value"><?php echo $service['packages_count']; ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-label">Starting From</span>
                                        <span class="meta-value">PKR <?php echo number_format($service['starting_price']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-label">Orders</span>
                                        <span class="meta-value"><?php echo $service['orders_count']; ?></span>
                                    </div>
                                </div>

                                <div class="service-tags">
                                    <?php if($service['status'] == 'active'): ?>
                                        <span class="tag tag-active">Active</span>
                                    <?php elseif($service['status'] == 'draft'): ?>
                                        <span class="tag tag-draft">Draft</span>
                                    <?php endif; ?>

                                    <?php if($service['discount_percent'] > 0): ?>
                                        <span class="tag tag-discount"><?php echo $service['discount_percent']; ?>% OFF</span>
                                    <?php endif; ?>
                                </div>

                                <div class="service-footer">
                                    <button class="btn btn-outline btn-sm" onclick="window.location='edit_service.php?id=<?php echo $service['id']; ?>'">Edit</button>
                                    <button class="btn btn-primary btn-sm" onclick="window.location='service_details.php?id=<?php echo $service['id']; ?>'">View Details</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="ms-discounts" class="ms-tab-content <?php echo ($active_tab === 'ms-discounts') ? 'active' : ''; ?>" style="display: <?php echo ($active_tab === 'ms-discounts') ? 'block' : 'none'; ?>;">
                    <?php if (empty($discounts)): ?>
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-tag"></i>️</div>
                            <h3 class="empty-title">No Discounts Yet</h3>
                            <p class="empty-description">Create promotional codes to attract more clients.</p>
                            <button class="btn btn-accent" onclick="openDiscountModal()">Create Discount</button>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($discounts as $discount): ?>
                        <div class="discount-card" style="<?php echo !empty($discount['bg_color']) ? "background: {$discount['bg_color']};" : ''; ?>">
                            <div class="discount-card-content">
                                <div class="discount-header">
                                    <div>
                                        <div class="discount-title">
                                            <?php echo htmlspecialchars($discount['title']); ?>
                                            <span class="discount-percent"><?php echo floatval($discount['discount_percent']); ?>% OFF</span>
                                        </div>
                                        <div class="discount-description"><?php echo htmlspecialchars($discount['description']); ?></div>
                                    </div>
                                    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 10px;">
                                        <span class="discount-badge" style="background: <?php echo $discount['status'] === 'active' ? 'var(--accent)' : '#ccc'; ?>;">
                                            <?php echo strtoupper($discount['status']); ?>
                                        </span>
                                        <div style="display: flex; gap: 6px; z-index: 10;">
                                            <button class="btn btn-secondary btn-sm" style="padding: 6px 12px; font-size: 12px;" onclick='editDiscount(<?php echo json_encode($discount, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>Edit</button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this discount?');">
                                                <input type="hidden" name="action" value="delete_discount">
                                                <input type="hidden" name="id" value="<?php echo $discount['id']; ?>">
                                                <button type="submit" class="btn btn-primary btn-sm" style="background: #ef4444; border: none; padding: 6px 12px; font-size: 12px;">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="discount-meta">
                                    <span>Code: <strong><?php echo htmlspecialchars($discount['code']); ?></strong></span>
                                    <span>Internal Name: <strong><?php echo htmlspecialchars($discount['name']); ?></strong></span>
                                    <span>Applied: <strong><?php echo $discount['applied_count']; ?> times</strong></span>
                                    <span>Expires:
                                        <strong>
                                            <?php echo $discount['expires_date'] ? date('M d, Y', strtotime($discount['expires_date'])) : 'Ongoing'; ?>
                                        </strong>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>

    </div>
</div>

<div id="discountModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="discountModalTitle">Create Discount</h2>
            <button class="modal-close" onclick="closeModal('discountModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="discountAction" value="add_discount">
                <input type="hidden" name="id" id="discountId" value="">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Display Title</label>
                        <input type="text" name="title" id="discountTitle" class="form-input" placeholder="e.g. Summer Sale" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Internal Name</label>
                        <input type="text" name="name" id="discountName" class="form-input" placeholder="e.g. summer_2026" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="discountDesc" class="form-textarea" placeholder="Details about this discount..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Discount Code</label>
                        <input type="text" name="code" id="discountCode" class="form-input" placeholder="e.g. SUMMER20" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Discount Percent (%)</label>
                        <input type="number" step="0.01" name="discount_percent" id="discountPercent" class="form-input" placeholder="e.g. 20" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="discountStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expires Date</label>
                        <input type="date" name="expires_date" id="discountExpires" class="form-input">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('discountModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Discount</button>
            </div>
        </form>
    </div>
</div>

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

    /**
     * Modal Handling Logic
     */
    function openDiscountModal() {
        document.getElementById('discountModalTitle').textContent = 'Create Discount';
        document.getElementById('discountAction').value = 'add_discount';
        document.getElementById('discountId').value = '';
        document.getElementById('discountTitle').value = '';
        document.getElementById('discountName').value = '';
        document.getElementById('discountDesc').value = '';
        document.getElementById('discountCode').value = '';
        document.getElementById('discountPercent').value = '';
        document.getElementById('discountStatus').value = 'active';
        document.getElementById('discountExpires').value = '';
        
        document.getElementById('discountModal').classList.add('active');
    }

    function editDiscount(discount) {
        document.getElementById('discountModalTitle').textContent = 'Edit Discount';
        document.getElementById('discountAction').value = 'edit_discount';
        document.getElementById('discountId').value = discount.id;
        document.getElementById('discountTitle').value = discount.title || '';
        document.getElementById('discountName').value = discount.name || '';
        document.getElementById('discountDesc').value = discount.description || '';
        document.getElementById('discountCode').value = discount.code || '';
        document.getElementById('discountPercent').value = discount.discount_percent || '';
        document.getElementById('discountStatus').value = discount.status || 'active';
        
        // Format date properly for the HTML5 date input (YYYY-MM-DD)
        let expDate = '';
        if (discount.expires_date) {
            expDate = discount.expires_date.split(' ')[0];
        }
        document.getElementById('discountExpires').value = expDate;
        
        document.getElementById('discountModal').classList.add('active');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }

    window.onclick = function(event) {
        const modal = document.getElementById('discountModal');
        if (event.target == modal) {
            closeModal('discountModal');
        }
    }
</script>

<?php
include('dashboard_footer.php');
?>