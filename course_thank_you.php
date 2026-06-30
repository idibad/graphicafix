<?php
// Hide strict errors to prevent visual 500 crashes
error_reporting(0);
ini_set('display_errors', '0');

include('header.php');

$eid        = isset($_GET['eid']) ? intval($_GET['eid']) : 0;
$courseName = isset($_GET['course']) ? htmlspecialchars($_GET['course']) : 'Your Course';
$is_already = isset($_GET['already']) && $_GET['already'] == 1;
$pay_url    = isset($_GET['pay_url']) ? $_GET['pay_url'] : '';

// ── FORMAT THE ID FOR DISPLAY ──
$display_eid = $eid ? "B" . date('y') . strtoupper(substr(date('F'), 0, 1)) . $eid : '';
$icon    = $is_already ? '<i class="fas fa-hand-paper" style="color: var(--accent);"></i>' : '<i class="fas fa-check-circle" style="color: var(--accent);"></i>';
$title   = $is_already ? 'Already Enrolled!' : 'Registration Successful!';

if (!empty($pay_url)) {
    $message = "Your enrollment has been registered! A payment window will open automatically. Please complete your card payment to activate your enrollment.";
} elseif ($is_already) {
    $message = "It looks like you have already submitted a registration for this course. Your application is either currently under review, or your access is already active!";
} else {
    $message = "Thank you for registering! Your enrollment has been successfully received. Our team will review your details (and payment proof if applicable) within 24 hours. Keep an eye on your inbox for updates.";
}
?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
.ty-page { background: #f7f9f5; min-height: 75vh; display: flex; align-items: center; justify-content: center; padding: 80px 20px; font-family: 'Poppins', Helvetica, Arial, sans-serif; }
.ty-card { background: #fff; max-width: 540px; width: 100%; border-radius: 24px; box-shadow: 0 16px 40px rgba(2, 68, 66, 0.08); overflow: hidden; text-align: center; border: 1.5px solid #ebebeb; animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.ty-header { background: linear-gradient(135deg, var(--primary, #024442), #035b58); color: #fff; padding: 48px 32px 32px; position: relative; overflow: hidden; }
.ty-header::after { content: ''; position: absolute; top: -50px; right: -50px; width: 180px; height: 180px; background: rgba(184, 243, 90, 0.1); border-radius: 50%; }
.ty-icon { font-size: 4.5rem; line-height: 1; margin-bottom: 16px; position: relative; z-index: 1; }
.ty-title { font-size: 1.8rem; font-weight: 800; margin: 0; line-height: 1.2; position: relative; z-index: 1; }
.ty-body { padding: 40px 32px; }
.ty-message { font-size: 0.95rem; color: #555; line-height: 1.7; margin-bottom: 24px; }
.ty-course-box { background: rgba(184, 243, 90, 0.12); border: 1.5px dashed rgba(2, 68, 66, 0.2); border-radius: 16px; padding: 20px; margin-bottom: 24px; }
.ty-course-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #666; margin-bottom: 6px; }
.ty-course-name { font-size: 1.15rem; font-weight: 800; color: var(--primary, #024442); margin: 0 0 12px 0; }
.ty-eid { display: inline-block; background: #fff; padding: 6px 14px; border-radius: 30px; font-size: 0.8rem; font-weight: 600; color: #777; border: 1px solid #e0e0e0; }
.ty-eid span { color: var(--primary, #024442); font-weight: 800; }

.ty-actions { display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px; }
.ty-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: var(--primary, #024442); color: #fff; padding: 14px 36px; border-radius: 50px; font-size: .9rem; font-weight: 700; text-decoration: none; transition: all 0.25s; border: none; cursor: pointer; }
.ty-btn:hover { background: #035b58; color: #fff; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(2, 68, 66, 0.25); }
.ty-btn-outline { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: transparent; color: var(--primary); padding: 12px 36px; border-radius: 50px; font-size: .9rem; font-weight: 700; text-decoration: none; transition: all 0.25s; border: 2px solid var(--primary); cursor: pointer; }
.ty-btn-outline:hover { background: rgba(2,68,66,.05); }
.ty-btn-pay { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: #10b981; color: #fff; padding: 16px 36px; border-radius: 50px; font-size: 1rem; font-weight: 700; text-decoration: none; transition: all 0.25s; border: none; cursor: pointer; animation: pulse-pay 2s infinite; }
.ty-btn-pay:hover { background: #059669; color: #fff; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(16, 185, 129, 0.35); }
@keyframes pulse-pay { 0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); } 50% { box-shadow: 0 0 0 12px rgba(16, 185, 129, 0); } }

.ty-pay-notice { background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 12px; padding: 16px; margin-bottom: 20px; font-size: 0.85rem; color: #92400e; line-height: 1.6; }
.ty-pay-notice i { color: #f59e0b; margin-right: 6px; }

.ty-support { font-size: 0.8rem; color: #aaa; }
.ty-support a { color: var(--primary, #024442); font-weight: 600; text-decoration: none; }
.ty-support a:hover { text-decoration: underline; }
</style>

<div class="ty-page">
    <div class="ty-card">
        <div class="ty-header">
            <div class="ty-icon"><?php echo $icon; ?></div>
            <h1 class="ty-title"><?php echo $title; ?></h1>
        </div>
        
        <div class="ty-body">
            <p class="ty-message"><?php echo $message; ?></p>
            
            <?php if ($courseName): ?>
            <div class="ty-course-box">
                <div class="ty-course-label">Selected Course</div>
                <h2 class="ty-course-name"><?php echo $courseName; ?></h2>
                
                <?php if ($eid): ?>
                <div class="ty-eid">Enrollment ID: <span><?php echo htmlspecialchars($display_eid); ?></span></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($pay_url)): ?>
            <div class="ty-pay-notice">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Payment Required:</strong> Your enrollment is saved but will remain <strong>pending</strong> until card payment is completed. Click below to pay now.
            </div>
            <div class="ty-actions">
                <a href="<?php echo htmlspecialchars($pay_url); ?>" target="_blank" class="ty-btn-pay" id="payBtn">
                    <i class="fas fa-credit-card"></i> Complete Card Payment Now
                </a>
            <?php else: ?>
            <div class="ty-actions">
            <?php endif; ?>
            
                <?php if ($eid): ?>
                <a href="download_form.php?eid=<?php echo $eid; ?>" target="_blank" class="ty-btn-outline">
                    <i class="fas fa-file-pdf"></i> Download Application Form
                </a>
                <?php endif; ?>

                <a href="courses.php" class="ty-btn">
                    <i class="fas fa-arrow-left"></i> Browse More Courses
                </a>
            </div>
            
            <div class="ty-support">
                Need help? <a href="contact.php">Contact Support</a>
            </div>
        </div>
    </div>
</div>



<?php include('footer.php'); ?>