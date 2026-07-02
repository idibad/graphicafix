<?php
/**
 * download_form.php
 * Requires mPDF: composer require mpdf/mpdf
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Assuming this file is in the root directory alongside course_thank_you.php
require_once 'vendor/autoload.php';
require_once 'core/config.php';

$eid = intval($_GET['eid'] ?? 0);
if (!$eid) die('Invalid Enrollment ID.');

$stmt = $conn->prepare("
    SELECT e.*, c.title AS course_title, c.category, c.level 
    FROM course_enrollments e 
    JOIN courses c ON e.course_id = c.id 
    WHERE e.id = ?
");
$stmt->bind_param("i", $eid); 
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();

if (!$inv) die('Enrollment record not found.');

// Define colors based on general enrollment status
$sc = match($inv['status']) {
    'active', 'completed' => '#16a34a', // Green
    'pending', 'reviewing' => '#f59e0b', // Yellow/Orange
    'rejected', 'cancelled' => '#dc2626', // Red
    default => '#6b7280', // Gray
};

// Define specific Payment verification status
$pay_status = in_array($inv['status'], ['active', 'completed']) ? 'VERIFIED' : 'PENDING';
$pay_color  = in_array($inv['status'], ['active', 'completed']) ? '#16a34a' : '#f59e0b';

// Fallback for BASE_URL if not defined in config
$baseUrl = defined('BASE_URL') ? BASE_URL : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/";

ob_start(); 
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 12px;
    color: #2b2b2b;
    background: #ffffff;
    position: relative;
    overflow: hidden;
}

/* HEADER */
.header {
    padding: 0px;
    border-bottom: 2px solid #024442;
}
.invoice-title {
    float: left;
    text-align: left;
    padding: 20px 30px;
}
.invoice-title h1 {
    font-size: 32px;
    color: #024442;
    letter-spacing: 2px;
}
.invoice-num {
    font-size: 12px;
    color: #777;
}

/* STATUS */
.status {
    position: absolute;
    top: 30px;
    right: -60px;
    background: <?= $sc ?>;
    color: #fff;
    padding: 8px 80px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 2px;
    text-align: center;
    transform: rotate(45deg);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

/* META SECTION */
.meta {
    padding: 25px 40px;
    display: table;
    width: 100%;
}
.meta-left {
    display: table-cell;
    width: 55%;
}
.meta-right {
    display: table-cell;
    width: 45%;
}
.section-label {
    font-size: 9px;
    letter-spacing: 1px;
    color: #024442;
    font-weight: 700;
    margin-bottom: 5px;
    text-transform: uppercase;
}
.client-name {
    font-size: 16px;
    font-weight: 700;
    color: #111;
    margin-bottom: 3px;
}
.client-info {
    font-size: 12px;
    color: #555;
    line-height: 1.6;
}

/* META TABLE */
.meta-table {
    float: right;
    border-collapse: collapse;
    margin-top: 10px;
}
.meta-table td {
    padding: 4px 10px;
    font-size: 12px;
}
.meta-label {
    color: #777;
    text-align: right;
}
.meta-value {
    font-weight: 600;
    color: #111;
}

/* ITEMS TABLE */
.items {
    padding: 20px 40px;
}
.items table {
    width: 100%;
    border-collapse: collapse;
}
.items thead {
    background: #024442;
    color: #fff;
}
.items th {
    padding: 10px;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .08em;
    text-align: left;
}
.items th.r,
.items td.r {
    text-align: right;
}
.items td {
    padding: 10px;
    border-bottom: 1px solid #eee;
}
.items tbody tr:nth-child(even) {
    background: #fafafa;
}

/* TOTALS */
.totals {
    padding: 10px 40px 30px 40px;
}
.total-table {
    float: right;
    width: 260px;
    border-collapse: collapse;
}
.total-table td {
    padding: 8px 8px;
    font-size: 12px;
}
.total-label {
    color: #777;
    text-align: right;
}
.total-value {
    text-align: right;
    font-weight: 600;
}
.grand-total td {
    background: #024442;
    color: #fff;
    font-weight: 700;
    font-size: 13px;
}
.due-row td {
    color: #dc2626;
    font-weight: 700;
}

/* FOOTER */
.footer {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
}
</style>
</head>

<body>

<div class="header">
    <div class="company">
        <img src="<?= $baseUrl ?>images/letter_header.png" style="width:100%">
    </div>
    <div class="invoice-title">
        <h1>ENROLLMENT</h1>
        <div class="invoice-num">ID #<?= str_pad($inv['id'], 4, '0', STR_PAD_LEFT) ?></div>
    </div>
    <div style="clear:both"></div>
</div>

<div class="status"><?= strtoupper($inv['status']) ?></div>

<div class="meta">
    <div class="meta-left">
        <div class="section-label">Student Details</div>
        <div class="client-name">
            <?= htmlspecialchars($inv['student_name']) ?>
        </div>
        <div class="client-info">
            <?= htmlspecialchars($inv['student_email']) ?><br>
            <?= htmlspecialchars($inv['student_phone']) ?><br>
            <?php if(!empty($inv['father_name'])) echo "Father/Husband: ".htmlspecialchars($inv['father_name'])."<br>"; ?>
            <?php if(!empty($inv['address'])) echo htmlspecialchars($inv['address'])."<br>"; ?>
            <?php if(!empty($inv['student_city'])) echo htmlspecialchars($inv['student_city'])."<br>"; ?>
            <?php if(!empty($inv['dob']) && $inv['dob'] !== '0000-00-00') echo "DOB: ".date('M d, Y', strtotime($inv['dob']))."<br>"; ?>
            <?php if(!empty($inv['gender'])) echo "Gender: ".htmlspecialchars($inv['gender'])."<br>"; ?>
            <?php if(!empty($inv['student_occupation'])) echo "Occupation: <span style='text-transform:capitalize;'>".htmlspecialchars($inv['student_occupation'])."</span><br>"; ?>
            <?php if(!empty($inv['education_level'])) echo "Education: ".htmlspecialchars($inv['education_level'])."<br>"; ?>
            <?php if(!empty($inv['student_qualification'])) echo "Qualification: ".htmlspecialchars($inv['student_qualification']); ?>
        </div>
    </div>

    <div class="meta-right">
        <table class="meta-table">
            <tr>
                <td class="meta-label">Application Date</td>
                <td class="meta-value"><?= date('M d, Y', strtotime($inv['enrolled_at'])) ?></td>
            </tr>
            <tr>
                <td class="meta-label">Discount Applied</td>
                <td class="meta-value"><?= !empty($inv['discount_code']) ? htmlspecialchars($inv['discount_code']) : 'None' ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="items">
    <div class="section-label" style="margin-bottom:10px;">Course Information</div>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th>Course Name</th>
                <th class="r">Level</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= htmlspecialchars($inv['category']) ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($inv['course_title']) ?></td>
                <td class="r"><?= htmlspecialchars($inv['level']) ?></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="totals">
    <table class="total-table">
        <tr>
            <td class="total-label">Payment Status</td>
            <td class="total-value" style="color: <?= $pay_color ?>; font-weight: 800;">
                <?= $pay_status ?>
            </td>
        </tr>
        <tr class="grand-total">
            <td class="total-label">AMOUNT PAID</td>
            <td class="total-value">Rs. <?= number_format($inv['amount_paid'], 2) ?></td>
        </tr>
    </table>
</div>

<div class="footer">
    <img src="<?= $baseUrl ?>images/letter_footer.png" style="width:100%">
</div>

</body>
</html>
<?php
$html = ob_get_clean();

$mpdf = new \Mpdf\Mpdf([
    'margin_top' => 0,
    'margin_right' => 0,
    'margin_bottom' => 0,
    'margin_left' => 0,
    'format' => 'A4'
]);

$mpdf->SetTitle('Enrollment Application #' . $inv['id']);
$mpdf->SetAuthor('Graphicafix Edu');
$mpdf->WriteHTML($html);

$dest = isset($_GET['dl']) ? 'D' : 'I';
$mpdf->Output('Enrollment-Form-'. str_pad($inv['id'], 4, '0', STR_PAD_LEFT) .'.pdf', $dest);
exit;
?>