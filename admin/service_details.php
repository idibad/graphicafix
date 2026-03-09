<?php
include('dashboard_header.php');

$service_id = $_GET['id'] ?? 0;

// Fetch service
$stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();

if (!$service) {
    echo "<script>alert('Service not found');window.location='manage_services.php';</script>";
    exit;
}

// Fetch packages
$stmt = $conn->prepare("SELECT * FROM service_packages WHERE service_id = ? ORDER BY price ASC");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$packages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch features for each package
foreach ($packages as &$pkg) {
    $stmt = $conn->prepare("SELECT feature FROM service_package_features WHERE package_id = ? ORDER BY id ASC");
    $stmt->bind_param("i", $pkg['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $pkg['features'] = [];
    while ($row = $result->fetch_assoc()) {
        $pkg['features'][] = $row['feature'];
    }
}
unset($pkg);

function getServiceStatusClass($status) {
    return $status === 'active' ? 'status-completed' : 'status-on-hold';
}
?>

<div class="height-100">

    <!-- ── Header ───────────────────────────────────────────────────────── -->
    <div class="project-details-header">
        <div class="header-details-top">
            <div class="header-content">
                <h1>
                    <?php if (!empty($service['icon'])): ?>
                        <i class="<?= htmlspecialchars($service['icon']) ?>"
                           style="<?= htmlspecialchars($service['icon_style']) ?>;-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-right:8px;"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($service['title']) ?>
                </h1>
                <div class="client-name">
                    <span>📦</span>
                    <?= count($packages) ?> Package<?= count($packages) !== 1 ? 's' : '' ?> &nbsp;•&nbsp;
                    <span>🛒</span> <?= (int)$service['orders_count'] ?> Orders
                </div>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="window.location='manage_services.php'">
                    <span>←</span> Back
                </button>
                <button class="btn btn-secondary" onclick="window.location='edit_service.php?id=<?= $service_id ?>'">
                    <span>✏️</span> Edit
                </button>
                <button class="btn btn-primary" onclick="window.location='edit_service.php?id=<?= $service_id ?>'">
                    <span>➕</span> Add Package
                </button>
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-item">
                <span class="meta-label">Status</span>
                <span class="badge <?= getServiceStatusClass($service['status']) ?>" style="text-transform:capitalize;">
                    <?= htmlspecialchars($service['status']) ?>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Starting Price</span>
                <span class="meta-value">
                    <?= !empty($service['starting_price']) ? 'PKR ' . number_format($service['starting_price'], 0) : 'Not set' ?>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Discount</span>
                <span class="meta-value">
                    <?= !empty($service['discount_percent']) ? $service['discount_percent'] . '%' : 'None' ?>
                </span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Total Orders</span>
                <span class="meta-value"><?= (int)$service['orders_count'] ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Created</span>
                <span class="meta-value"><?= date('M d, Y', strtotime($service['created_at'])) ?></span>
            </div>
        </div>
    </div>

    <!-- ── Content Grid ──────────────────────────────────────────────────── -->
    <div class="content-grid">

        <!-- ── Main Content ─────────────────────────────────────────────── -->
        <div class="main-content">

            <!-- Description -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📝 Service Description</h2>
                </div>
                <?php if (!empty($service['description'])): ?>
                    <div class="description-text">
                        <?= nl2br(htmlspecialchars($service['description'])) ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📄</div>
                        <div>No description provided</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Packages -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📦 Packages</h2>
                    <span style="font-size:13px;color:#888;"><?= count($packages) ?> total</span>
                </div>

                <?php if (!empty($packages)): ?>
                    <div class="packages-grid">
                        <?php foreach ($packages as $pkg): ?>
                        <div class="pkg-detail-card <?= $pkg['is_featured'] ? 'pkg-featured' : '' ?>">

                            <?php if ($pkg['is_featured']): ?>
                                <div class="pkg-featured-ribbon">⭐ Featured</div>
                            <?php endif; ?>

                            <div class="pkg-detail-header">
                                <div>
                                    <div class="pkg-detail-name"><?= htmlspecialchars($pkg['name']) ?></div>
                                    <?php if (!empty($pkg['description'])): ?>
                                        <div class="pkg-detail-desc"><?= htmlspecialchars($pkg['description']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="pkg-detail-price">
                                    PKR <?= number_format($pkg['price'], 0) ?>
                                </div>
                            </div>

                            <?php if (!empty($pkg['features'])): ?>
                                <ul class="pkg-detail-features">
                                    <?php foreach ($pkg['features'] as $feat): ?>
                                        <li>
                                            <span class="feat-check">✓</span>
                                            <?= htmlspecialchars($feat) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="empty-state" style="padding:12px 0 0;">
                                    <div class="empty-icon">📋</div>
                                    <div>No features listed</div>
                                </div>
                            <?php endif; ?>

                            <div class="pkg-detail-footer">
                                <span class="badge <?= $pkg['status'] === 'active' ? 'status-completed' : 'status-on-hold' ?>"
                                      style="text-transform:capitalize;font-size:11px;">
                                    <?= htmlspecialchars($pkg['status']) ?>
                                </span>
                                <div style="display:flex;gap:8px;">
                                    <button class="btn btn-secondary"
                                            style="padding:5px 12px;font-size:12px;"
                                            onclick="window.location='edit_package.php?id=<?= $pkg['id'] ?>'">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-danger"
                                            style="padding:5px 12px;font-size:12px;"
                                            onclick="deletePackage(<?= $pkg['id'] ?>)">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📦</div>
                        <div>No packages added yet</div>
                        <a href="add_package.php?service_id=<?= $service_id ?>" class="btn btn-primary" style="margin-top:12px;display:inline-flex;">
                            ➕ Add First Package
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- ── Sidebar ───────────────────────────────────────────────────── -->
        <div class="sidebar">

            <!-- Stats -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📊 Stats</h2>
                </div>
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-dot" style="background:var(--success);"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Total Packages</div>
                            <div class="timeline-date"><?= count($packages) ?></div>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-dot" style="background:var(--info);"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Total Orders</div>
                            <div class="timeline-date"><?= (int)$service['orders_count'] ?></div>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-dot" style="background:var(--warning);"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Starting Price</div>
                            <div class="timeline-date">
                                PKR <?= !empty($service['starting_price']) ? number_format($service['starting_price'], 0) : '—' ?>
                            </div>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-dot" style="background:var(--danger, #e53e3e);"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Discount</div>
                            <div class="timeline-date">
                                <?= !empty($service['discount_percent']) ? $service['discount_percent'] . '%' : 'None' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📅 Timeline</h2>
                </div>
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-dot" style="background:var(--success);"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Service Created</div>
                            <div class="timeline-date"><?= date('M d, Y', strtotime($service['created_at'])) ?></div>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-dot" style="background:var(--info);"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Last Updated</div>
                            <div class="timeline-date"><?= date('M d, Y', strtotime($service['updated_at'])) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">⚡ Quick Actions</h2>
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <button class="btn btn-secondary" style="width:100%;justify-content:center;"
                            onclick="window.location='edit_service.php?id=<?= $service_id ?>'">
                        ✏️ Edit Service
                    </button>
                    <button class="btn btn-secondary" style="width:100%;justify-content:center;"
                            onclick="window.location='add_package.php?service_id=<?= $service_id ?>'">
                        ➕ Add Package
                    </button>
                    <button class="btn btn-secondary" style="width:100%;justify-content:center;"
                            onclick="window.location='manage_services.php'">
                        📋 All Services
                    </button>
                    <button class="btn btn-danger" style="width:100%;justify-content:center;"
                            onclick="deleteService(<?= $service_id ?>)">
                        🗑️ Delete Service
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ── Package grid & card styles ────────────────────────────────────────── -->
<style>
.packages-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 16px;
    margin-top: 4px;
}

.pkg-detail-card {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 20px;
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 14px;
    background: #fff;
    transition: box-shadow .2s;
}
.pkg-detail-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.07); }

.pkg-featured {
    border-color: #e8c97a;
    box-shadow: 0 0 0 1px #e8c97a;
}

.pkg-featured-ribbon {
    position: absolute;
    top: 12px;
    right: 12px;
    background: #e8c97a;
    color: #7a5800;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 100px;
    text-transform: uppercase;
    letter-spacing: .05em;
}

.pkg-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
}

.pkg-detail-name {
    font-size: 15px;
    font-weight: 700;
    color: #024442;
}

.pkg-detail-desc {
    font-size: 12px;
    color: #888;
    margin-top: 3px;
    line-height: 1.5;
}

.pkg-detail-price {
    font-size: 16px;
    font-weight: 800;
    color: #024442;
    white-space: nowrap;
}

.pkg-detail-features {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 7px;
    flex: 1;
}

.pkg-detail-features li {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 13px;
    color: #444;
    line-height: 1.4;
}

.feat-check {
    color: #01796f;
    font-weight: 700;
    flex-shrink: 0;
    margin-top: 1px;
}

.pkg-detail-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid #f0f0f0;
    padding-top: 12px;
    margin-top: auto;
}
</style>

<script>
function deletePackage(id) {
    if (!confirm('Are you sure you want to delete this package and all its features?')) return;
    window.location = 'delete_package.php?id=' + id + '&service_id=<?= $service_id ?>';
}

function deleteService(id) {
    if (!confirm('Are you sure you want to delete this service? This will also delete all packages and features.')) return;
    window.location = 'delete_service.php?id=' + id;
}
</script>

<?php include('dashboard_footer.php'); ?>