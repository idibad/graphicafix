<?php
/**
 * generate_letter_pdf.php
 * Requires mPDF: composer require mpdf/mpdf
 */

// Hide errors to prevent 500 crashes
error_reporting(0);
ini_set('display_errors', '0');

require_once '../vendor/autoload.php';
include '../core/config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) die('Invalid Document ID.');

$stmt = $conn->prepare("SELECT * FROM letters WHERE id=?");
$stmt->bind_param("i", $id); 
$stmt->execute();
$l = $stmt->get_result()->fetch_assoc();
if (!$l) die('Document not found.');

ob_start(); 
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">

<style>
/* ── BASE TYPOGRAPHY ── */
body {
    font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
    color: #333333;
    font-size: 14px;
    line-height: 1.6;
    margin: 0;
    padding: 0;
}
table { width: 100%; border-collapse: collapse; }
td { vertical-align: top; }

/* FIX mPDF LINE HEIGHT GLITCH FOR BOLD TEXT */
strong, b {
    line-height: inherit;
}
p {
    line-height: 1.6;
    margin-top: 0;
    margin-bottom: 10px;
}
</style>
</head>
<body>

   <htmlpageheader name="pageHeader">
        <img src="<?php echo __DIR__; ?>/../images/letter_header.png" style="width: 100%;" />
    </htmlpageheader>
    <sethtmlpageheader name="pageHeader" page="O" value="on" show-this-page="1" />

    <htmlpagefooter name="pageFooter">
        <img src="<?php echo __DIR__; ?>/../images/letter_footer.png" style="width: 100%;" />
    </htmlpagefooter>
    <sethtmlpagefooter name="pageFooter" page="O" value="on" show-this-page="1" />

    <div style="padding: 20px 40px;">
        
        <table>
            <tr>
                <td style="text-align: right; color: #555555; font-size: 13px;">
                    <strong>Date:</strong> <?php echo date('F j, Y', strtotime($l['updated_at'])); ?>
                </td>
            </tr>
        </table>

        <?php if (!empty($l['recipient_name']) || !empty($l['recipient_company']) || !empty($l['recipient_email']) || !empty($l['recipient_address'])): ?>
        <div style="margin-top: 30px; font-size: 14px; line-height: 1.6;">
            <?php if (!empty($l['recipient_name'])): ?>
                <div style="font-weight: bold; font-size: 16px; color: #111111;">To: <?php echo htmlspecialchars($l['recipient_name']); ?></div>
            <?php endif; ?>
            <?php if (!empty($l['recipient_company'])): ?>
                <div style="font-weight: bold; color: #333333; font-size: 15px;"><?php echo htmlspecialchars($l['recipient_company']); ?></div>
            <?php endif; ?>
            <?php if (!empty($l['recipient_address'])): ?>
                <div style="color: #444444; white-space: pre-line; margin-top: 4px;"><?php echo htmlspecialchars($l['recipient_address']); ?></div>
            <?php endif; ?>
            <?php if (!empty($l['recipient_email'])): ?>
                <div style="color: #0284c7; margin-top: 2px;"><?php echo htmlspecialchars($l['recipient_email']); ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($l['subject'])): ?>
        <div style="margin-top: 30px; font-weight: bold; font-size: 15px; color: #111111; text-decoration: underline;">
            Subject: <?php echo htmlspecialchars($l['subject']); ?>
        </div>
        <?php endif; ?>

        <div style="margin-top: 30px; font-size: 14px; color: #333333; line-height: 1.6; min-height: 100mm;">
            <?php echo $l['body_html']; ?>
        </div>

        <?php if (!empty($l['footer_note'])): ?>
        <div style="margin-top: 50px; font-size: 12px; color: #888888; text-align: center; font-weight: bold;">
            <?php echo htmlspecialchars($l['footer_note']); ?>
        </div>
        <?php endif; ?>

    </div>

</body>
</html>
<?php
$html = ob_get_clean();

// mPDF configuration for absolute edge-to-edge rendering of headers/footers
$mpdf = new \Mpdf\Mpdf([
    'margin_top' => 35,    // Leave space for the header graphic
    'margin_bottom' => 35, // Leave space for the footer graphic
    'margin_left' => 0,
    'margin_right' => 0,
    'format' => 'A4'
]);

// ── Perfect File Name Generation ──
$rawTitle = !empty($l['title']) ? $l['title'] : 'Document';
$safeTitle = preg_replace('/[^a-zA-Z0-9]/', '_', $rawTitle);
$safeTitle = trim(preg_replace('/_+/', '_', $safeTitle), '_');
$finalFileName = $safeTitle . '_ID' . $l['id'] . '.pdf';

$mpdf->SetTitle($rawTitle);
$mpdf->SetAuthor('Graphicafix');
$mpdf->WriteHTML($html);

// Save copy to disk securely
$dir = '../letters/';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$file = $dir . $finalFileName;
$mpdf->Output($file, 'F');

// Explicitly send headers if downloading to force the browser to respect the name
$dest = isset($_GET['dl']) ? 'D' : 'I';

if ($dest === 'D') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $finalFileName . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
}

$mpdf->Output($finalFileName, $dest);
exit;
?>