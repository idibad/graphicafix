<?php
/**
 * generate_quotation_pdf.php
 * Requires mPDF: composer require mpdf/mpdf
 *
 * Usage:
 *   generate_quotation_pdf.php?id=5            → view in browser
 *   generate_quotation_pdf.php?id=5&dl=1       → force download
 */

require_once '../vendor/autoload.php';
include('../config.php');

$id = intval($_GET['id'] ?? 0);
if (!$id) die('Invalid ID.');

$stmt = $conn->prepare("SELECT * FROM quotations WHERE id=?");
$stmt->bind_param("i",$id); $stmt->execute();
$q = $stmt->get_result()->fetch_assoc();
if (!$q) die('Quotation not found.');

$stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id=? ORDER BY sort_order");
$stmt->bind_param("i",$id); $stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$statusColors = ['draft'=>'#6b7280','sent'=>'#3b82f6','accepted'=>'#16a34a','rejected'=>'#dc2626','expired'=>'#f97316'];
$sc = $statusColors[$q['status']] ?? '#6b7280';
$cur = htmlspecialchars($q['currency']);

ob_start(); ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">

<style>

*{margin:0;padding:0;box-sizing:border-box}

body{
font-family:'DejaVu Sans',sans-serif;
background:#f6f8fa;
font-size:13px;
color:#333;
padding:40px;
}

/* MAIN WRAPPER */

.wrapper{
background:#fff;
border-radius:10px;
overflow:hidden;
box-shadow:0 10px 35px rgba(0,0,0,0.08);
}

/* HEADER */

.header{
background:#024442;
padding:35px 45px;
color:#fff;
display:flex;
justify-content:space-between;
align-items:center;
position:relative;
}

.company{
font-size:22px;
font-weight:700;
color:#e8c97a;
}

.company-sub{
font-size:11px;
opacity:.8;
margin-top:4px;
}

.doc-title{
text-align:left;
padding:10px 30px; 
}

.doc-title h1{
font-size:28px;
letter-spacing:3px;
}

.doc-number{
font-size:11px;
color:#e8c97a;
margin-top:4px;
}

/* STATUS BADGE */

.status{
position:absolute;
top:20px;
right:-45px;
transform:rotate(45deg);
background:<?= $sc ?>;
color:#fff;
font-size:10px;
padding:6px 60px;
font-weight:700;
letter-spacing:2px;
}

/* META */

.meta{
padding:40px 45px;
display:flex;
justify-content:space-between;
border-bottom:1px solid #eee;
}

.client h3{
font-size:12px;
color:#024442;
letter-spacing:1px;
margin-bottom:8px;
text-transform:uppercase;
}

.client-name{
font-size:16px;
font-weight:700;
}

.client-info{
font-size:12px;
color:#666;
margin-top:4px;
line-height:1.6;
}

.meta-table{
font-size:12px;
}

.meta-table td{
padding:4px 10px;
}

.meta-table .label{
color:#777;
text-align:right;
}

.meta-table .value{
font-weight:600;
}

/* SECTIONS */

.section{
padding:35px 45px;
}

.section-title{
font-size:12px;
font-weight:700;
letter-spacing:1px;
color:#024442;
margin-bottom:15px;
text-transform:uppercase;
}

/* NOTES */

.notes{
color:#555;
line-height:1.7;
white-space:pre-line;
}

/* TABLE */

.table{
width:100%;
border-collapse:collapse;
font-size:13px;
}

.table thead{
background:#024442;
color:#fff;
}

.table th{
padding:11px;
font-size:11px;
text-transform:uppercase;
letter-spacing:.08em;
text-align:left;
}

.table th.r,
.table td.r{
text-align:right;
}

.table td{
padding:12px;
border-bottom:1px solid #eee;
}

.table tbody tr:nth-child(even){
background:#fafafa;
}

.total-row{
background:#024442;
color:#fff;
font-weight:700;
}

.total-row td{
border:none;
}

.total-row td:last-child{
color:#e8c97a;
font-size:15px;
}

/* TERMS */

.terms{
background:#f8f9fb;
padding:20px;
border-left:4px solid #024442;
border-radius:6px;
font-size:12px;
line-height:1.7;
color:#555;
}

/* SIGNATURE */

.signatures{
display:flex;
justify-content:space-between;
padding:40px 45px;
border-top:1px solid #eee;
}

.sign-block{
width:40%;
}

.sign-line{
height:1px;
background:#222;
margin-top:50px;
margin-bottom:6px;
}

.sign-name{
font-weight:700;
}

.sign-title{
font-size:11px;
color:#666;
}

/* FOOTER */


.footer{
position:fixed;
bottom:0;
left:0;
width:100%;
}
</style>
</head>

<body>

<div class="wrapper">

<div class="letter-header">
    <img src="../images/letter_header.png">
</div>

<div class="doc-title">
<h1>QUOTATION</h1>
<div class="doc-number"><?= htmlspecialchars($q['quotation_number']) ?></div>
</div>

<div class="status"><?= strtoupper($q['status']) ?></div>

</div>


<!-- META -->

<div class="meta">

<div class="client">

<h3>Prepared For</h3>

<div class="client-name"><?= htmlspecialchars($q['client_name']) ?></div>

<?php if (!empty($q['client_company'])): ?>
<div><?= htmlspecialchars($q['client_company']) ?></div>
<?php endif; ?>

<div class="client-info">

<?php if (!empty($q['client_email'])) echo htmlspecialchars($q['client_email']).'<br>'; ?>

<?php if (!empty($q['client_phone'])) echo htmlspecialchars($q['client_phone']).'<br>'; ?>

<?php if (!empty($q['client_address'])) echo nl2br(htmlspecialchars($q['client_address'])); ?>

</div>

</div>


<table class="meta-table">

<tr>
<td class="label">Quotation #</td>
<td class="value"><?= htmlspecialchars($q['quotation_number']) ?></td>
</tr>

<tr>
<td class="label">Issue Date</td>
<td class="value"><?= date('M d, Y',strtotime($q['issue_date'])) ?></td>
</tr>

<tr>
<td class="label">Valid Until</td>
<td class="value"><?= date('M d, Y',strtotime($q['valid_until'])) ?></td>
</tr>

<tr>
<td class="label">Currency</td>
<td class="value"><?= $cur ?></td>
</tr>

<tr>
<td class="label"><strong>Total</strong></td>
<td class="value"><strong><?= $cur ?> <?= number_format($q['total_amount'],2) ?></strong></td>
</tr>

</table>

</div>


<!-- NOTES -->

<?php if (!empty($q['notes'])): ?>

<div class="section">

<div class="section-title">Scope & Description</div>

<div class="notes"><?= htmlspecialchars($q['notes']) ?></div>

</div>

<?php endif; ?>


<!-- ITEMS -->

<div class="section">

<div class="section-title">Pricing Breakdown</div>

<table class="table">

<thead>
<tr>
<th>#</th>
<th>Description</th>
<th class="r">Qty</th>
<th class="r">Unit</th>
<th class="r">Total</th>
</tr>
</thead>

<tbody>

<?php foreach ($items as $i => $it): ?>

<tr>

<td><?= $i+1 ?></td>

<td><?= htmlspecialchars($it['description']) ?></td>

<td class="r"><?= $it['quantity'] ?></td>

<td class="r"><?= $cur ?> <?= number_format($it['unit_price'],2) ?></td>

<td class="r"><?= $cur ?> <?= number_format($it['total'],2) ?></td>

</tr>

<?php endforeach; ?>

</tbody>

<tfoot>

<tr>
<td colspan="3"></td>
<td class="r">Subtotal</td>
<td class="r"><?= $cur ?> <?= number_format($q['subtotal'],2) ?></td>
</tr>

<?php if ($q['tax_percent']>0): ?>

<tr>
<td colspan="3"></td>
<td class="r">Tax (<?= $q['tax_percent'] ?>%)</td>
<td class="r"><?= $cur ?> <?= number_format($q['tax_amount'],2) ?></td>
</tr>

<?php endif; ?>

<?php if ($q['discount_amount']>0): ?>

<tr>
<td colspan="3"></td>
<td class="r">Discount</td>
<td class="r">- <?= $cur ?> <?= number_format($q['discount_amount'],2) ?></td>
</tr>

<?php endif; ?>

<tr class="total-row">
<td colspan="3"></td>
<td class="r">TOTAL</td>
<td class="r"><?= $cur ?> <?= number_format($q['total_amount'],2) ?></td>
</tr>

</tfoot>

</table>

</div>


<!-- TERMS -->

<?php if (!empty($q['terms'])): ?>

<div class="section">

<div class="section-title">Terms & Conditions</div>

<div class="terms"><?= htmlspecialchars($q['terms']) ?></div>

</div>

<?php endif; ?>


<!-- SIGNATURES -->

<div class="signatures">

<div class="sign-block">

<div class="section-title">Authorized By</div>

<div class="sign-line"></div>

<div class="sign-name"><?= htmlspecialchars($q['signature_name'] ?: 'Graphicafix Team') ?></div>

<div class="sign-title"><?= htmlspecialchars($q['signature_title'] ?? 'Authorized Representative') ?></div>

</div>


<div class="sign-block">

<div class="section-title">Client Acceptance</div>

<div class="sign-line"></div>

<div class="sign-title">Client Signature</div>

</div>

</div>


<!-- FOOTER -->

<div class="footer">
<div class="letter-footer">
    <img src="../images/letter_footer.png">
</div>

</div>

</div>

</body>
</html>
<?php
$html = ob_get_clean();

$mpdf = new \Mpdf\Mpdf(['margin_top'=>0,'margin_right'=>0,'margin_bottom'=>0,'margin_left'=>0,'format'=>'A4']);
$mpdf->SetTitle('Quotation '.$q['quotation_number']);
$mpdf->SetAuthor('Graphicafix');
$mpdf->WriteHTML($html);

// Save to disk
$dir = '../quotations/';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$file = $dir . $q['quotation_number'] . '.pdf';
$path = 'quotations/' . $q['quotation_number'] . '.pdf';
$mpdf->Output($file, 'F');

// Update pdf_path in DB
// $conn->prepare("UPDATE quotations SET pdf_path=? WHERE id=?")->execute() ;
$s=$conn->prepare("UPDATE quotations SET pdf_path=? WHERE id=?");
$s->bind_param("si",$path,$id); $s->execute();

$dest = isset($_GET['dl']) ? 'D' : 'I';
$mpdf->Output('Quotation-'.$q['quotation_number'].'.pdf', $dest);
exit;
?>