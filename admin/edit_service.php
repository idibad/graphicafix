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

// Fetch existing packages with features
$stmt = $conn->prepare("SELECT * FROM service_packages WHERE service_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$packages_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($packages_raw as &$pkg) {
    $stmt = $conn->prepare("SELECT feature FROM service_package_features WHERE package_id = ? ORDER BY id ASC");
    $stmt->bind_param("i", $pkg['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $pkg['features'] = [];
    while ($row = $res->fetch_assoc()) {
        $pkg['features'][] = $row['feature'];
    }
}
unset($pkg);

// ── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title            = $_POST['title'];
    $description      = $_POST['description'];
    $icon             = $_POST['icon'];
    $icon_style       = $_POST['icon_style'];
    $status           = $_POST['status'];
    $starting_price   = $_POST['starting_price'];
    $discount_percent = $_POST['discount_percent'];

    // Update service
    $stmt = $conn->prepare("UPDATE services SET 
        title=?, description=?, icon=?, icon_style=?, status=?, 
        starting_price=?, discount_percent=?, updated_at=NOW() 
        WHERE id=?");
    $stmt->bind_param("sssssssi", $title, $description, $icon, $icon_style, $status, $starting_price, $discount_percent, $service_id);
    $stmt->execute();

    // ── Packages ─────────────────────────────────────────────────────────────
    $submitted_pkg_ids = []; // track which existing packages are kept

    if (!empty($_POST['packages']) && is_array($_POST['packages'])) {
        foreach ($_POST['packages'] as $pkg) {
            if (empty(trim($pkg['name']))) continue;

            $pkg_id          = intval($pkg['existing_id'] ?? 0);
            $pkg_name        = trim($pkg['name']);
            $pkg_desc        = trim($pkg['description'] ?? '');
            $pkg_price       = floatval($pkg['price'] ?? 0);
            $pkg_is_featured = isset($pkg['is_featured']) ? 1 : 0;
            $pkg_status      = $pkg['status'] ?? 'active';

            if ($pkg_id > 0) {
                // Update existing package
                $stmt = $conn->prepare("UPDATE service_packages SET 
                    name=?, description=?, price=?, is_featured=?, status=?, updated_at=NOW() 
                    WHERE id=? AND service_id=?");
                $stmt->bind_param("ssdisii", $pkg_name, $pkg_desc, $pkg_price, $pkg_is_featured, $pkg_status, $pkg_id, $service_id);
                $stmt->execute();
                $submitted_pkg_ids[] = $pkg_id;

                // Delete old features and re-insert
                $stmt = $conn->prepare("DELETE FROM service_package_features WHERE package_id=?");
                $stmt->bind_param("i", $pkg_id);
                $stmt->execute();

            } else {
                // Insert new package
                $stmt = $conn->prepare("INSERT INTO service_packages 
                    (service_id, name, description, price, is_featured, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param("issdis", $service_id, $pkg_name, $pkg_desc, $pkg_price, $pkg_is_featured, $pkg_status);
                $stmt->execute();
                $pkg_id = $conn->insert_id;
                $submitted_pkg_ids[] = $pkg_id;
            }

            // Insert features
            if (!empty($pkg['features']) && is_array($pkg['features'])) {
                foreach ($pkg['features'] as $feat) {
                    $feat = trim($feat);
                    if ($feat === '') continue;
                    $fstmt = $conn->prepare("INSERT INTO service_package_features (package_id, feature, created_at) VALUES (?, ?, NOW())");
                    $fstmt->bind_param("is", $pkg_id, $feat);
                    $fstmt->execute();
                }
            }
        }
    }

    // Delete packages that were removed in the UI
    if (!empty($submitted_pkg_ids)) {
        $ids_placeholder = implode(',', array_fill(0, count($submitted_pkg_ids), '?'));
        $types = str_repeat('i', count($submitted_pkg_ids) + 1);
        $params = array_merge([$service_id], $submitted_pkg_ids);

        // First delete their features
        $stmt = $conn->prepare("DELETE spf FROM service_package_features spf 
            JOIN service_packages sp ON spf.package_id = sp.id 
            WHERE sp.service_id=? AND sp.id NOT IN ($ids_placeholder)");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        // Then delete the packages
        $stmt = $conn->prepare("DELETE FROM service_packages WHERE service_id=? AND id NOT IN ($ids_placeholder)");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    } else {
        // No packages submitted — delete all
        $stmt = $conn->prepare("DELETE spf FROM service_package_features spf 
            JOIN service_packages sp ON spf.package_id = sp.id WHERE sp.service_id=?");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM service_packages WHERE service_id=?");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
    }

    echo "<script>alert('Service Updated Successfully!');window.location='service_details.php?id={$service_id}';</script>";
}
?>

<div class="height-100">
    <div class="page-header">
        <h1>✏️ Edit Service</h1>
        <p>Update the details for <strong><?= htmlspecialchars($service['title']) ?></strong></p>
    </div>

    <!-- Tabs Navigation -->
    <div class="mp-tabs-container">
        <div class="mp-tab-buttons">
            <button type="button" class="active" onclick="showTab('basic')">
                <span class="mp-tab-icon">📝</span> Basic Info
            </button>
            <button type="button" onclick="showTab('media')">
                <span class="mp-tab-icon">🖼️</span> Icon
            </button>
            <button type="button" onclick="showTab('packages')">
                <span class="mp-tab-icon">📦</span> Packages
            </button>
        </div>
    </div>

    <!-- Form Container -->
    <div class="form-container">
        <form method="POST" enctype="multipart/form-data" id="serviceForm">

            <!-- ── Basic Info Tab ─────────────────────────────────────────── -->
            <div id="basic" class="tab active">
                <h3>Basic Information</h3>

                <div class="form-group">
                    <label>Service Title <span class="required">*</span></label>
                    <input type="text" name="title" placeholder="Enter service title"
                           value="<?= htmlspecialchars($service['title']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Enter service description..."><?= htmlspecialchars($service['description']) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="active"   <?= $service['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $service['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Starting Price</label>
                        <input type="number" name="starting_price" placeholder="0.00" step="0.01"
                               value="<?= htmlspecialchars($service['starting_price']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Discount Percent</label>
                        <input type="number" name="discount_percent" placeholder="0" step="1" max="100"
                               value="<?= htmlspecialchars($service['discount_percent']) ?>">
                    </div>
                </div>
            </div>

            <!-- ── Icon Tab ───────────────────────────────────────────────── -->
            <div id="media" class="tab">
                <h3>Service Icon</h3>

                <div class="form-group">
                    <label>Icon</label>
                    <select name="icon" id="iconDropdown" required>
                        <option value="">Select an icon</option>
                        <?php
                        $icons = [
                            'fas fa-paint-brush',
                            'fas fa-code',
                            'fas fa-bullhorn',
                            'fas fa-chart-line',
                            'fas fa-lightbulb',
                            'fas fa-cogs',
                            'fas fa-camera',
                            'fas fa-pencil-alt',
                            'fas fa-laptop-code'
                        ];
                        foreach ($icons as $ic) {
                            $selected = $service['icon'] === $ic ? 'selected' : '';
                            echo "<option value='{$ic}' {$selected}>{$ic}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Icon Style (Gradient)</label>
                    <input type="text" name="icon_style" id="iconStyle" readonly
                           placeholder="Will be auto-filled"
                           value="<?= htmlspecialchars($service['icon_style']) ?>">
                </div>

                <div class="form-group">
                    <label>Preview</label>
                    <div id="iconPreview" style="font-size:40px;width:60px;height:60px;display:flex;align-items:center;justify-content:center;border-radius:8px;">
                        <?php if (!empty($service['icon'])): ?>
                            <i class="<?= htmlspecialchars($service['icon']) ?>"
                               style="background:<?= htmlspecialchars($service['icon_style']) ?>;-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── Packages Tab ───────────────────────────────────────────── -->
            <div id="packages" class="tab">
                <h3>Service Packages <span style="font-size:13px;font-weight:400;color:#888;">(optional)</span></h3>
                <p style="color:#888;font-size:14px;margin-bottom:20px;">Edit or remove existing packages, or add new ones.</p>

                <div id="packagesWrapper">
                    <?php foreach ($packages_raw as $i => $pkg): ?>
                    <div class="pkg-card" id="pkg-<?= $i ?>">
                        <div class="pkg-card-header">
                            <h4>📦 Package <?= $i + 1 ?></h4>
                            <button type="button" class="pkg-remove-btn" onclick="removePackage('pkg-<?= $i ?>')" title="Remove package">✕</button>
                        </div>

                        <!-- Hidden field to track existing package ID -->
                        <input type="hidden" name="packages[<?= $i ?>][existing_id]" value="<?= $pkg['id'] ?>">

                        <div class="pkg-grid">
                            <div class="form-group" style="margin-bottom:0">
                                <label>Package Name <span class="required">*</span></label>
                                <input type="text" name="packages[<?= $i ?>][name]"
                                       placeholder="e.g. Basic, Standard, Premium"
                                       value="<?= htmlspecialchars($pkg['name']) ?>">
                            </div>
                            <div class="form-group" style="margin-bottom:0">
                                <label>Price (PKR)</label>
                                <input type="number" name="packages[<?= $i ?>][price]"
                                       placeholder="0.00" step="0.01" min="0"
                                       value="<?= htmlspecialchars($pkg['price']) ?>">
                            </div>
                        </div>

                        <div class="form-group" style="margin-top:12px;margin-bottom:0">
                            <label>Package Description</label>
                            <textarea name="packages[<?= $i ?>][description]"
                                      placeholder="Short description of this package..."
                                      style="min-height:70px;"><?= htmlspecialchars($pkg['description']) ?></textarea>
                        </div>

                        <div class="pkg-grid" style="margin-top:12px;">
                            <div class="form-group" style="margin-bottom:0">
                                <label>Status</label>
                                <select name="packages[<?= $i ?>][status]">
                                    <option value="active"   <?= $pkg['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $pkg['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom:0;display:flex;align-items:flex-end;">
                                <label class="pkg-featured-row" style="margin:0;cursor:pointer;">
                                    <input type="checkbox" name="packages[<?= $i ?>][is_featured]" value="1"
                                           <?= $pkg['is_featured'] ? 'checked' : '' ?>>
                                    Mark as Featured <span class="featured-badge">⭐ Featured</span>
                                </label>
                            </div>
                        </div>

                        <span class="pkg-features-label">✅ Features</span>
                        <div id="features-<?= $i ?>">
                            <?php foreach ($pkg['features'] as $feat): ?>
                            <?php
                                $fi = isset($featCounts[$i]) ? $featCounts[$i]++ : ($featCounts[$i] = 1) - 1;
                            ?>
                            <div class="pkg-feature-row" id="feat-<?= $i ?>-<?= $fi ?>">
                                <input type="text"
                                       name="packages[<?= $i ?>][features][]"
                                       placeholder="e.g. Unlimited revisions"
                                       value="<?= htmlspecialchars($feat) ?>">
                                <button type="button" class="pkg-feature-remove"
                                        onclick="removeFeature('feat-<?= $i ?>-<?= $fi ?>')" title="Remove">✕</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="add-feature-btn" onclick="addFeature(<?= $i ?>)">＋ Add Feature</button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="btn btn-secondary" onclick="addPackage()" style="margin-top:4px;">
                    ＋ Add Package
                </button>
            </div>

            <!-- ── Form Actions ───────────────────────────────────────────── -->
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" id="prevBtn" onclick="previousTab()" style="display:none;">
                    <span>←</span> Previous
                </button>
                <button type="button" class="btn btn-secondary"
                        onclick="window.location='service_details.php?id=<?= $service_id ?>'">
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" id="nextBtn" onclick="nextTab()">
                    Next <span>→</span>
                </button>
                <button type="submit" class="btn btn-primary" id="submitBtn" style="display:none;">
                    <span>✓</span> Save Changes
                </button>
            </div>

        </form>
    </div>
</div>

<!-- ── Package styles (same as add_service) ──────────────────────────────── -->
<style>
.pkg-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 20px 20px 16px;
    margin-bottom: 16px;
    position: relative;
    transition: box-shadow .2s;
}
.pkg-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.07); }

.pkg-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.pkg-card-header h4 {
    font-size: 14px;
    font-weight: 700;
    color: #024442;
    margin: 0;
}

.pkg-remove-btn {
    background: none;
    border: none;
    color: #e53e3e;
    font-size: 18px;
    cursor: pointer;
    line-height: 1;
    padding: 2px 6px;
    border-radius: 4px;
    transition: background .15s;
}
.pkg-remove-btn:hover { background: #fff5f5; }

.pkg-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
@media(max-width:600px){ .pkg-grid { grid-template-columns: 1fr; } }

.pkg-featured-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 12px 0 4px;
    font-size: 14px;
    color: #444;
}
.pkg-featured-row input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #024442;
    cursor: pointer;
}

.pkg-features-label {
    font-size: 13px;
    font-weight: 600;
    color: #555;
    margin: 14px 0 8px;
    display: block;
}

.pkg-feature-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 7px;
}
.pkg-feature-row input[type="text"] {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 14px;
    outline: none;
    transition: border-color .2s;
}
.pkg-feature-row input[type="text"]:focus { border-color: #024442; }

.pkg-feature-remove {
    background: none;
    border: none;
    color: #aaa;
    font-size: 18px;
    cursor: pointer;
    padding: 2px 5px;
    border-radius: 4px;
    transition: color .15s, background .15s;
    line-height: 1;
}
.pkg-feature-remove:hover { color: #e53e3e; background: #fff5f5; }

.add-feature-btn {
    background: none;
    border: 1px dashed #024442;
    color: #024442;
    font-size: 13px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 6px;
    cursor: pointer;
    margin-top: 4px;
    transition: background .2s, color .2s;
}
.add-feature-btn:hover { background: #024442; color: #fff; }

.featured-badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    background: #e8c97a;
    color: #7a5800;
    padding: 2px 8px;
    border-radius: 100px;
    margin-left: 8px;
    text-transform: uppercase;
    letter-spacing: .05em;
}
</style>

<script>
/* ── Tab logic ───────────────────────────────────────────────────────────── */
const tabs = ['basic', 'media', 'packages'];
let currentTabIndex = 0;

function showTab(tabId) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.mp-tab-buttons button').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    currentTabIndex = tabs.indexOf(tabId);
    document.querySelectorAll('.mp-tab-buttons button')[currentTabIndex].classList.add('active');
    updateButtons();
}

function nextTab()     { if (currentTabIndex < tabs.length - 1) { currentTabIndex++; showTabByIndex(currentTabIndex); } }
function previousTab() { if (currentTabIndex > 0)               { currentTabIndex--; showTabByIndex(currentTabIndex); } }
function showTabByIndex(i) { showTab(tabs[i]); }

function updateButtons() {
    document.getElementById('prevBtn').style.display   = currentTabIndex === 0               ? 'none'        : 'inline-flex';
    document.getElementById('nextBtn').style.display   = currentTabIndex === tabs.length - 1 ? 'none'        : 'inline-flex';
    document.getElementById('submitBtn').style.display = currentTabIndex === tabs.length - 1 ? 'inline-flex' : 'none';
}

/* ── Form validation ─────────────────────────────────────────────────────── */
document.getElementById('serviceForm').addEventListener('submit', function(e) {
    const title = document.querySelector('input[name="title"]').value;
    if (!title) {
        e.preventDefault();
        alert('Please fill in the required field: Title');
        showTab('basic');
    }
});

/* ── Icon logic ──────────────────────────────────────────────────────────── */
function getRandomGradient() {
    const gradients = [
        "linear-gradient(135deg, #e0d4ff, #c4b5fd)",
        "linear-gradient(135deg, #ff9a9e, #fad0c4)",
        "linear-gradient(135deg, #a18cd1, #fbc2eb)",
        "linear-gradient(135deg, #fbc2eb, #a6c1ee)",
        "linear-gradient(135deg, #ffecd2, #fcb69f)",
        "linear-gradient(135deg, #f5f7fa, #c3cfe2)"
    ];
    return gradients[Math.floor(Math.random() * gradients.length)];
}

const iconDropdown   = document.getElementById('iconDropdown');
const iconStyleInput = document.getElementById('iconStyle');
const iconPreview    = document.getElementById('iconPreview');

iconDropdown.addEventListener('change', function () {
    const selectedIcon = this.value;
    if (!selectedIcon) return;
    const gradient = getRandomGradient();
    iconStyleInput.value = gradient;
    iconPreview.innerHTML = `<i class="${selectedIcon}" style="background:${gradient};-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"></i>`;
});

/* ── Package logic ───────────────────────────────────────────────────────── */
// Start pkgCount after existing PHP-rendered packages
let pkgCount = <?= count($packages_raw) ?>;

function addPackage() {
    const idx = pkgCount++;
    const wrapper = document.getElementById('packagesWrapper');

    const card = document.createElement('div');
    card.className = 'pkg-card';
    card.id = `pkg-${idx}`;

    card.innerHTML = `
        <div class="pkg-card-header">
            <h4>📦 Package ${idx + 1}</h4>
            <button type="button" class="pkg-remove-btn" onclick="removePackage('pkg-${idx}')" title="Remove package">✕</button>
        </div>

        <input type="hidden" name="packages[${idx}][existing_id]" value="0">

        <div class="pkg-grid">
            <div class="form-group" style="margin-bottom:0">
                <label>Package Name <span class="required">*</span></label>
                <input type="text" name="packages[${idx}][name]" placeholder="e.g. Basic, Standard, Premium">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Price (PKR)</label>
                <input type="number" name="packages[${idx}][price]" placeholder="0.00" step="0.01" min="0">
            </div>
        </div>

        <div class="form-group" style="margin-top:12px;margin-bottom:0">
            <label>Package Description</label>
            <textarea name="packages[${idx}][description]" placeholder="Short description of this package..." style="min-height:70px;"></textarea>
        </div>

        <div class="pkg-grid" style="margin-top:12px;">
            <div class="form-group" style="margin-bottom:0">
                <label>Status</label>
                <select name="packages[${idx}][status]">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;display:flex;align-items:flex-end;">
                <label class="pkg-featured-row" style="margin:0;cursor:pointer;">
                    <input type="checkbox" name="packages[${idx}][is_featured]" value="1">
                    Mark as Featured <span class="featured-badge">⭐ Featured</span>
                </label>
            </div>
        </div>

        <span class="pkg-features-label">✅ Features</span>
        <div id="features-${idx}"></div>
        <button type="button" class="add-feature-btn" onclick="addFeature(${idx})">＋ Add Feature</button>
    `;

    wrapper.appendChild(card);
    addFeature(idx);
    renumberPackages();
}

function removePackage(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
    renumberPackages();
}

function renumberPackages() {
    document.querySelectorAll('.pkg-card').forEach((card, i) => {
        const heading = card.querySelector('h4');
        if (heading) heading.textContent = `📦 Package ${i + 1}`;
    });
}

let featCounts = {};

function addFeature(pkgIdx) {
    if (!featCounts[pkgIdx]) featCounts[pkgIdx] = 0;
    const fi = featCounts[pkgIdx]++;

    const container = document.getElementById(`features-${pkgIdx}`);
    if (!container) return;

    const row = document.createElement('div');
    row.className = 'pkg-feature-row';
    row.id = `feat-${pkgIdx}-${fi}`;
    row.innerHTML = `
        <input type="text" name="packages[${pkgIdx}][features][]" placeholder="e.g. Unlimited revisions">
        <button type="button" class="pkg-feature-remove" onclick="removeFeature('feat-${pkgIdx}-${fi}')" title="Remove">✕</button>
    `;
    container.appendChild(row);
}

function removeFeature(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
}

// Init featCounts for PHP-rendered packages so JS-added features don't collide
<?php foreach ($packages_raw as $i => $pkg): ?>
featCounts[<?= $i ?>] = <?= count($pkg['features']) ?>;
<?php endforeach; ?>
</script>

<?php include('dashboard_footer.php'); ?>