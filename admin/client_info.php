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

// ── Handle Update Submission ─────────────────────────────────────────────────
if (isset($_POST['update_client_info'])) {
    $client_name = trim($_POST['client_name']);
    $company     = trim($_POST['company']);
    $email       = trim($_POST['email']);
    $phone       = trim($_POST['phone']);
    $address     = trim($_POST['address']);
    
    // Validate required fields
    if (empty($client_name)) {
        $error = "Client Name is required.";
    } else {
        // Handle logo upload
        $logo_path = null;
        if (!empty($_FILES['logo']['name'])) {
            $upload_dir = "uploads/logos/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $logo_name = time() . "_" . basename($_FILES['logo']['name']);
            $logo_path = $upload_dir . $logo_name;
            move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path);
        }
        
        if ($logo_path) {
            $stmt_upd = $conn->prepare("UPDATE clients SET client_name = ?, company = ?, email = ?, phone = ?, address = ?, logo = ? WHERE id = ?");
            $stmt_upd->bind_param("ssssssi", $client_name, $company, $email, $phone, $address, $logo_path, $client_id);
        } else {
            $stmt_upd = $conn->prepare("UPDATE clients SET client_name = ?, company = ?, email = ?, phone = ?, address = ? WHERE id = ?");
            $stmt_upd->bind_param("sssssi", $client_name, $company, $email, $phone, $address, $client_id);
        }
        
        if ($stmt_upd->execute()) {
            echo "<script>alert('Company Profile Updated Successfully!'); window.location='client_info.php';</script>";
            exit;
        } else {
            $error = "Error updating database: " . $conn->error;
        }
    }
}

// Fetch current client info
$stmt_cli = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$stmt_cli->bind_param("i", $client_id);
$stmt_cli->execute();
$client = $stmt_cli->get_result()->fetch_assoc();
?>

<div class="height-100">
    <div class="projects-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title-section">
                <h1>Company Information</h1>
                <p>Submit and keep your corporate profile, contact details, and logo up to date</p>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Edit Profile Form -->
        <div class="card" style="padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; background: white; max-width: 800px; margin: 0 auto;">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="update_client_info" value="1">
                
                <h3 style="margin: 0 0 20px 0; font-size: 18px; font-weight: 700; color: #1e293b; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">
                    <i class="fas fa-building" style="color: #024442; margin-right: 8px;"></i> Company Profile Details
                </h3>

                <!-- Logo Section -->
                <div style="display: flex; gap: 24px; align-items: center; margin-bottom: 24px; flex-wrap: wrap; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #f1f5f9;">
                    <div style="flex-shrink: 0;">
                        <?php if (!empty($client['logo'])): ?>
                            <img src="<?= htmlspecialchars($client['logo']) ?>" alt="Current Logo" style="width: 100px; height: 100px; object-fit: contain; background: white; border-radius: 8px; border: 1px solid #e2e8f0; padding: 4px;">
                        <?php else: ?>
                            <div style="width: 100px; height: 100px; background: #e2e8f0; border-radius: 8px; border: 1px solid #cbd5e1; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:24px;">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 14px;">Upload Corporate Logo</label>
                        <input type="file" name="logo" accept="image/*" style="font-size: 13px;">
                        <small style="color: #64748b; display: block; margin-top: 4px;">PNG, JPG, or SVG. Max size 2MB.</small>
                    </div>
                </div>

                <!-- Form Fields -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 13.5px;">Client/Contact Name <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="client_name" value="<?= htmlspecialchars($client['client_name']) ?>" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; font-size: 14px; font-family: inherit;" required>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 13.5px;">Company Name</label>
                        <input type="text" name="company" value="<?= htmlspecialchars($client['company']) ?>" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; font-size: 14px; font-family: inherit;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group" style="margin: 0;">
                        <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 13.5px;">Contact Email Address</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($client['email']) ?>" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; font-size: 14px; font-family: inherit;">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 13.5px;">Contact Phone Number</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($client['phone']) ?>" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; font-size: 14px; font-family: inherit;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 13.5px;">Company Address</label>
                    <textarea name="address" rows="3" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; font-size: 14px; font-family: inherit;"><?= htmlspecialchars($client['address']) ?></textarea>
                </div>

                <div style="border-top: 1px solid #e2e8f0; padding-top: 20px; display: flex; justify-content: flex-end; gap: 12px;">
                    <button type="button" class="btn btn-secondary" onclick="window.location='index.php'" style="background: #f1f5f9; border: 1px solid #cbd5e1; color:#334155; padding: 8px 20px; border-radius:6px; font-weight:700;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #024442; border: none; padding: 8px 24px; border-radius: 6px; color: white; font-weight:700; cursor: pointer;">Save Company Info</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'dashboard_footer.php'; ?>
