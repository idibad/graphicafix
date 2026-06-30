<?php
/**
 * generate_invoice_pdf.php
 * Requires mPDF: composer require mpdf/mpdf
 *
 * Usage:
 *   generate_invoice_pdf.php?id=5        → view in browser
 *   generate_invoice_pdf.php?id=5&dl=1   → force download
 */

require_once '../vendor/autoload.php';
include('../config.php');

$id = intval($_GET['id'] ?? 0);
if (!$id) die('Invalid ID.');

$stmt = $conn->prepare("SELECT i.*,c.client_name,c.company,c.email,c.phone,c.address FROM invoices i LEFT JOIN clients c ON i.client_id=c.id WHERE i.id=?");
$stmt->bind_param("i",$id); $stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();
if (!$inv) die('Invoice not found.');

$stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order");
$stmt->bind_param("i",$id); $stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$sc = match($inv['status']) {
    'paid'      => '#16a34a',
    'sent'      => '#3b82f6',
    'overdue'   => '#dc2626',
    'cancelled' => '#9ca3af',
    default     => '#6b7280',
};
$cur = htmlspecialchars($inv['currency']);

ob_start(); ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
}
body{
font-family: DejaVu Sans, sans-serif;
font-size:12px;
color:#2b2b2b;
background:#ffffff;
position:relative;
overflow:hidden;
}


/* HEADER */

.header{
padding:0px;
border-bottom:2px solid #024442;
}

.invoice-title{
float:left;
text-align:left;
padding:20px 30px;
}

.invoice-title h1{
font-size:32px;
color:#024442;
letter-spacing:2px;
}

.invoice-num{
font-size:12px;
color:#777;
}

/* STATUS */

.status{
position:absolute;
top:30px;
right:-60px;

background:<?= $sc ?>;
color:#fff;

padding:8px 80px;

font-size:11px;
font-weight:700;
letter-spacing:2px;
text-align:center;

transform:rotate(45deg);
box-shadow:0 2px 8px rgba(0,0,0,0.15);
}

/* META SECTION */

.meta{
padding:25px 40px;
display:table;
width:100%;
}

.meta-left{
display:table-cell;
width:55%;
}

.meta-right{
display:table-cell;
width:45%;
}

.section-label{
font-size:9px;
letter-spacing:1px;
color:#024442;
font-weight:700;
margin-bottom:5px;
text-transform:uppercase;
}

.client-name{
font-size:16px;
font-weight:700;
color:#111;
margin-bottom:3px;
}

.client-info{
font-size:12px;
color:#555;
line-height:1.6;
}

/* META TABLE */

.meta-table{
float:right;
border-collapse:collapse;
margin-top:10px;
}

.meta-table td{
padding:4px 10px;
font-size:12px;
}

.meta-label{
color:#777;
text-align:right;
}

.meta-value{
font-weight:600;
color:#111;
}

/* ITEMS TABLE */

.items{
padding:20px 40px;
}

.items table{
width:100%;
border-collapse:collapse;
}

.items thead{
background:#024442;
color:#fff;
}

.items th{
padding:10px;
font-size:10px;
text-transform:uppercase;
letter-spacing:.08em;
text-align:left;
}

.items th.r,
.items td.r{
text-align:right;
}

.items td{
padding:10px;
border-bottom:1px solid #eee;
}

.items tbody tr:nth-child(even){
background:#fafafa;
}

/* TOTALS */

.totals{
padding:10px 40px 30px 40px;
}

.total-table{
float:right;
width:260px;
border-collapse:collapse;
}

.total-table td{
padding:6px 8px;
font-size:12px;
}

.total-label{
color:#777;
text-align:right;
}

.total-value{
text-align:right;
font-weight:600;
}

.grand-total td{
background:#024442;
color:#fff;
font-weight:700;
font-size:13px;
}

.due-row td{
color:#dc2626;
font-weight:700;
}

/* NOTES */

.notes{
padding:0 40px 20px 40px;
}

.notes p{
margin-top:5px;
line-height:1.7;
color:#555;
}

/* TERMS */

.terms{
margin:0 40px 30px 40px;
padding:12px 15px;
background:#f8fafc;
border-left:3px solid #024442;
}

.terms p{
margin-top:5px;
font-size:11px;
line-height:1.7;
color:#555;
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


<div class="header">

<div class="company">
<img src="<?= BASE_URL ?>images/letter_header.png" style="width:100%">

</div>

<div class="invoice-title">
<h1>INVOICE</h1>
<div class="invoice-num">#<?= htmlspecialchars($inv['invoice_number']) ?></div>
</div>

<div style="clear:both"></div>

</div>

<div class="status"><?= strtoupper($inv['status']) ?></div>


<div class="meta">

<div class="meta-left">

<div class="section-label">Bill To</div>

<div class="client-name">
<?= htmlspecialchars($inv['client_name']) ?>
</div>

<div class="client-info">

<?php if(!empty($inv['company'])) echo htmlspecialchars($inv['company'])."<br>"; ?>
<?php if(!empty($inv['email'])) echo htmlspecialchars($inv['email'])."<br>"; ?>
<?php if(!empty($inv['phone'])) echo htmlspecialchars($inv['phone'])."<br>"; ?>
<?php if(!empty($inv['address'])) echo nl2br(htmlspecialchars($inv['address'])); ?>

</div>

</div>


<div class="meta-right">

<table class="meta-table">

<tr>
<td class="meta-label">Issue Date</td>
<td class="meta-value"><?= date('M d, Y',strtotime($inv['issue_date'])) ?></td>
</tr>

<tr>
<td class="meta-label">Due Date</td>
<td class="meta-value"><?= date('M d, Y',strtotime($inv['due_date'])) ?></td>
</tr>

<tr>
<td class="meta-label">Currency</td>
<td class="meta-value"><?= $cur ?></td>
</tr>

</table>

</div>

</div>



<div class="items">

<table>

<thead>
<tr>
<th>#</th>
<th>Description</th>
<th class="r">Qty</th>
<th class="r">Unit Price</th>
<th class="r">Total</th>
</tr>
</thead>

<tbody>

<?php foreach ($items as $i=>$it): ?>

<tr>
<td><?= $i+1 ?></td>
<td><?= htmlspecialchars($it['description']) ?></td>
<td class="r"><?= $it['quantity'] ?></td>
<td class="r"><?= $cur ?> <?= number_format($it['unit_price'],2) ?></td>
<td class="r"><?= $cur ?> <?= number_format($it['total'],2) ?></td>
</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>



<div class="totals">

<table class="total-table">

<tr>
<td class="total-label">Subtotal</td>
<td class="total-value"><?= $cur ?> <?= number_format($inv['subtotal'],2) ?></td>
</tr>

<?php if ($inv['tax_percent']>0): ?>
<tr>
<td class="total-label">Tax (<?= $inv['tax_percent'] ?>%)</td>
<td class="total-value"><?= $cur ?> <?= number_format($inv['tax_amount'],2) ?></td>
</tr>
<?php endif; ?>

<?php if ($inv['discount_amount']>0): ?>
<tr>
<td class="total-label">Discount</td>
<td class="total-value">- <?= $cur ?> <?= number_format($inv['discount_amount'],2) ?></td>
</tr>
<?php endif; ?>

<tr class="grand-total">
<td class="total-label">TOTAL</td>
<td class="total-value"><?= $cur ?> <?= number_format($inv['total_amount'],2) ?></td>
</tr>

<?php if ($inv['amount_paid']>0): ?>
<tr>
<td class="total-label">Amount Paid</td>
<td class="total-value"><?= $cur ?> <?= number_format($inv['amount_paid'],2) ?></td>
</tr>
<?php endif; ?>

<?php if ($inv['amount_due']>0): ?>
<tr class="due-row">
<td class="total-label">Amount Due</td>
<td class="total-value"><?= $cur ?> <?= number_format($inv['amount_due'],2) ?></td>
</tr>
<?php endif; ?>

</table>

</div>



<?php if(!empty($inv['notes'])): ?>

<div class="notes">
<div class="section-label">Notes</div>
<p><?= htmlspecialchars($inv['notes']) ?></p>
</div>

<?php endif; ?>


<?php if(!empty($inv['terms'])): ?>

<div class="terms">
<div class="section-label">Terms & Conditions</div>
<p><?= $inv['terms'] ?></p>
</div>

<?php endif; ?>


<div class="footer">
<img src="<?= BASE_URL ?>images/letter_footer.png" style="width:100%">
</div>


</body>
</html>
<?php
$html = ob_get_clean();

$mpdf = new \Mpdf\Mpdf(['margin_top'=>0,'margin_right'=>0,'margin_bottom'=>0,'margin_left'=>0,'format'=>'A4']);
$mpdf->SetTitle('Invoice '.$inv['invoice_number']);
$mpdf->SetAuthor('Graphicafix');
$mpdf->WriteHTML($html);

$dest = isset($_GET['dl']) ? 'D' : 'I';
$mpdf->Output('Invoice-'.$inv['invoice_number'].'.pdf', $dest);
exit;
?>