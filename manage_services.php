<?php

include('dashboard_header.php');
// ------------------ STATS ------------------

// Active Services
$result = mysqli_query($conn, "SELECT COUNT(*) AS active_services, SUM(packages_count) AS total_packages, SUM(orders_count) AS total_orders 
                               FROM services WHERE status='Active'");
$service_stats = mysqli_fetch_assoc($result);

// Active Discounts
$result = mysqli_query($conn, "SELECT COUNT(*) AS active_discounts FROM discounts WHERE status='Active'");
$discount_stats = mysqli_fetch_assoc($result);

// Monthly Revenue
$result = mysqli_query($conn, "SELECT SUM(orders_count * starting_price) AS monthly_revenue FROM services");
$revenue = mysqli_fetch_assoc($result)['monthly_revenue'];

/* ------------------ FETCH SERVICES ------------------ */
$services = [];
$result = mysqli_query($conn, "SELECT * FROM services ORDER BY id DESC");
$services_count = mysqli_num_rows($result);
while ($row = mysqli_fetch_assoc($result)) {
    $services[] = $row;
}


/* ------------------ FETCH DISCOUNTS ------------------ */$discounts = [];
$result = $conn->query("SELECT * FROM discounts ORDER BY id DESC");
$discounts_count = mysqli_num_rows($result);
while ($row = $result->fetch_assoc()) {
    $discounts[] = $row;
}

/* ------------------ FETCH ANNOUNCEMENTS ------------------ */
$announcements = [];
$result = mysqli_query($conn, "SELECT * FROM announcements ORDER BY posted_on DESC");
$announcements_count = mysqli_num_rows($result);
while ($row = mysqli_fetch_assoc($result)) {
    $announcements[] = $row;
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
        .tabs-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            margin-bottom: 24px;
            overflow: hidden;
        }

        .tabs {
            display: flex;
            border-bottom: 1px solid var(--gray-200);
            overflow-x: auto;
            scrollbar-width: none;
        }

        .tabs::-webkit-scrollbar {
            display: none;
        }

        .tab {
            padding: 16px 24px;
            background: none;
            border: none;
            width: 33.33%;
            color: var(--gray-600);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .tab:hover {
            color: var(--primary);
            background: var(--gray-50);
        }

        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--accent);
            background: var(--light);
        }

        .tab-badge {
            display: inline-block;
            background: var(--gray-200);
            color: var(--gray-700);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 8px;
        }

        .tab.active .tab-badge {
            background: var(--accent);
            color: var(--dark);
        }

        .tab-content {
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

        /* Announcements Section */
        .announcements-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .announcement-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            border-left: 4px solid var(--accent);
            transition: all 0.2s;
        }

        .announcement-card:hover {
            box-shadow: var(--shadow-md);
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .announcement-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 6px;
        }

        .announcement-date {
            font-size: 13px;
            color: var(--gray-500);
        }

        .announcement-body {
            color: var(--gray-600);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .announcement-actions {
            display: flex;
            gap: 8px;
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
        }

        .discount-card::after {
            content: '🎉';
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 64px;
            opacity: 0.2;
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
        }

        .discount-description {
            opacity: 0.9;
            margin-bottom: 16px;
        }

        .discount-meta {
            display: flex;
            gap: 24px;
            font-size: 14px;
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .empty-description {
            color: var(--gray-600);
            margin-bottom: 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard {
                padding: 20px 16px;
            }

            .manage-services-header {
                flex-direction: column;
                align-items: stretch;
            }

            .header-actions {
                width: 100%;
            }

            .btn {
                flex: 1;
                justify-content: center;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .header-content h1 {
                font-size: 24px;
            }

            .tabs {
                padding: 0;
            }

            .tab {
                font-size: 13px;
                padding: 14px 16px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .service-meta {
                flex-direction: column;
                gap: 8px;
            }
        }
    </style>
<div class="height-100" >
   <div class="services-page">

    <!-- Header -->
        <div class="manage-services-header">
            <div class="header-content">
                <h1>Services Management</h1>
                <p>Manage services, packages, discounts, and announcements</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="openModal('announcement')">
                    <span class="btn-icon">📢</span>
                    New Announcement
                </button>
                <button class="btn btn-accent" onclick="openModal('discount')">
                    <span class="btn-icon">🏷️</span>
                    Create Discount
                </button>
                <button class="btn btn-primary" onclick="openModal('service')">
                    <span class="btn-icon">+</span>
                    Add Service
                </button>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <!-- Active Services -->
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Active Services</span>
                    <div class="stat-icon" style="background: var(--success-light); color: var(--success);">📦</div>
                </div>
                <div class="stat-value"><?php echo $service_stats['active_services'] ?? 0; ?></div>
                <div class="stat-change positive">↑ <?php echo $service_stats['total_orders'] ?? 0; ?> orders</div>
            </div>

            <!-- Total Packages -->
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Total Packages</span>
                    <div class="stat-icon" style="background: var(--light); color: var(--primary);">📋</div>
                </div>
                <div class="stat-value"><?php echo $service_stats['total_packages'] ?? 0; ?></div>
                <div class="stat-change positive">↑ <?php echo $service_stats['total_packages'] ?? 0; ?> new packages</div>
            </div>

            <!-- Active Discounts -->
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Active Discounts</span>
                    <div class="stat-icon" style="background: var(--danger-light); color: var(--danger);">🎯</div>
                </div>
                <div class="stat-value"><?php echo $discount_stats['active_discounts'] ?? 0; ?></div>
                <div class="stat-change"><?php echo $discount_stats['active_discounts'] ?? 0; ?> ending soon</div>
            </div>

            <!-- Revenue (Month) -->
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Revenue (Month)</span>
                    <div class="stat-icon" style="background: var(--warning-light); color: var(--warning);">💰</div>
                </div>
                <div class="stat-value">PKR <?php echo number_format($revenue ?? 0); ?></div>
                <div class="stat-change positive">↑ 24% vs last month</div>
            </div>
        </div>


        <!-- Tabs -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab active" onclick="switchTab(event, 'services')">
                    Services <span class="tab-badge">12</span>
                </button>
                <button class="tab" onclick="switchTab(event, 'discounts')">
                    Discounts <span class="tab-badge">5</span>
                </button>
                <button class="tab" onclick="switchTab(event, 'announcements')">
                    Announcements <span class="tab-badge">3</span>
                </button>
            </div>

            <!-- Services Tab -->
            <div id="services" class="tab-content">
                <div class="services-grid">
                    <?php foreach($services as $service): ?>
                        <div class="service-card">
                            <div class="service-header">
                                <div class="service-icon" 
                                    style="background: <?php echo $service['icon_bg'] ?? '#eee'; ?>">
                                    <?php echo $service['icon'] ?? '🎨'; ?>
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
                                <?php if($service['status'] == 'Active'): ?>
                                    <span class="tag tag-active">Active</span>
                                <?php elseif($service['status'] == 'Draft'): ?>
                                    <span class="tag tag-draft">Draft</span>
                                <?php endif; ?>
                                
                                <?php if($service['discount_percent'] > 0): ?>
                                    <span class="tag tag-discount"><?php echo $service['discount_percent']; ?>% OFF</span>
                                <?php endif; ?>
                            </div>
                            <div class="service-footer">
                                <button class="btn btn-outline btn-sm">Edit</button>
                                <button class="btn btn-primary btn-sm">View Packages</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>

            </div>

            <!-- Discounts Tab -->
            <div id="discounts" class="tab-content" style="display: none;">
                <?php foreach ($discounts as $discount): ?>
                    <div class="discount-card" style="<?php echo !empty($discount['bg_color']) ? "background: {$discount['bg_color']};" : ''; ?>">
                        <div class="discount-header">
                            <div>
                                <div class="discount-title"><?php echo htmlspecialchars($discount['title']); ?></div>
                                <div class="discount-description"><?php echo htmlspecialchars($discount['description']); ?></div>
                            </div>
                            <span class="discount-badge"><?php echo strtoupper($discount['status']); ?></span>
                        </div>
                        <div class="discount-meta">
                            <span>Code: <strong><?php echo htmlspecialchars($discount['code']); ?></strong></span>
                            <span>Applied: <strong><?php echo $discount['applied_count']; ?> times</strong></span>
                            <span>Expires: <strong>
                                <?php 
                                    echo $discount['expires_on'] ? date('M d, Y', strtotime($discount['expires_on'])) : 'Ongoing'; 
                                ?></strong>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>

                
            </div>

            <div id="announcements" class="tab-content" style="display: none;">
                <div class="announcements-list">
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="announcement-card">
                            <div class="announcement-header">
                                <div>
                                    <div class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                                    <div class="announcement-date">
                                        Posted on: <?php echo date('M d, Y', strtotime($announcement['posted_on'])); ?>
                                    </div>
                                </div>
                                <?php if ($announcement['is_urgent'] ?? false): ?>
                                    <span class="tag tag-new">Urgent</span>
                                <?php endif; ?>
                            </div>
                            <div class="announcement-body">
                                <?php echo nl2br(htmlspecialchars($announcement['body'])); ?>
                            </div>
                            <div class="announcement-actions">
                                <button class="btn btn-outline btn-sm">Edit</button>
                                <button class="btn btn-primary btn-sm">Pin to Top</button>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="empty-state" style="display: none;">
                        <div class="empty-icon">📭</div>
                        <h3 class="empty-title">No active announcements</h3>
                        <p class="empty-description">Create an announcement to notify your clients about updates or holidays.</p>
                        <button class="btn btn-primary" onclick="openModal('announcement')">Create Now</button>
                    </div>
                </div>
            </div>
</div>
</div>


<script>
    /**
     * Tab Switching Logic
     */
    function switchTab(event, tabId) {
        // 1. Get all tab content elements and hide them
        const contents = document.querySelectorAll('.tab-content');
        contents.forEach(content => content.style.display = 'none');

        // 2. Get all tabs and remove 'active' class
        const tabs = document.querySelectorAll('.tab');
        tabs.forEach(tab => tab.classList.remove('active'));

        // 3. Show the selected tab content
        document.getElementById(tabId).style.display = 'block';

        // 4. Add 'active' class to the clicked button
        event.currentTarget.classList.add('active');
    }

    /**
     * Modal Handling Logic
     */
    function openModal(type) {
        // You can use the 'type' to change modal titles or form fields dynamically
        console.log("Opening modal for: " + type);
        
        // Example: Logic to show a modal (assuming you have a modal element with class 'modal')
        const modal = document.querySelector('.modal');
        if(modal) {
            modal.classList.add('active');
        } else {
            alert("Modal functionality for '" + type + "' coming soon! Link your form here.");
        }
    }

    function closeModal() {
        const modal = document.querySelector('.modal');
        if(modal) {
            modal.classList.remove('active');
        }
    }

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        const modal = document.querySelector('.modal');
        if (event.target == modal) {
            closeModal();
        }
    }
</script>


<?php

include('dashboard_footer.php');
?>