<?php
/**
 * visitor_widget.php
 * Drop this anywhere in your admin dashboard.
 * Fetches its own stats — works even if visitor_counter.php returned early.
 */

$_today = date('Y-m-d');
$_month = date('Y-m');

$totalUnique   = $conn->query("SELECT SUM(unique_visits) AS t FROM visitor_stats")->fetch_assoc()['t'] ?? 0;
$todayUnique   = $conn->query("SELECT unique_visits FROM visitor_stats WHERE stat_date = '$_today'")->fetch_assoc()['unique_visits'] ?? 0;
$monthlyUnique = $conn->query("SELECT SUM(unique_visits) AS t FROM visitor_stats WHERE DATE_FORMAT(stat_date,'%Y-%m') = '$_month'")->fetch_assoc()['t'] ?? 0;

$last7  = [];
$_r7    = $conn->query("
    SELECT stat_date, unique_visits FROM visitor_stats
    WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    ORDER BY stat_date ASC
");
while ($_row = $_r7->fetch_assoc()) {
    $last7[$_row['stat_date']] = (int)$_row['unique_visits'];
}
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    if (!isset($last7[$d])) $last7[$d] = 0;
}
ksort($last7);
$sparkValues = array_values($last7);
?>
<div class="main-card" style="padding:24px;margin-bottom: 20px;">
    <div class="main-header" style="margin-bottom:20px;">
        <h3><i class="fas fa-eye"></i>️ Visitor Stats</h3>
        <span style="font-size:12px;color:#aaa;">Unique visitors only</span>
    </div>

    <!-- Stat chips -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px;">

        <div style="background:#f0fdf4;border-radius:14px;padding:16px 14px;text-align:center;border:1px solid #e2fbd4;">
            <div style="font-size:11px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Today</div>
            <div style="font-size:26px;font-weight:800;color:#024442;"><?= number_format($todayUnique) ?></div>
        </div>

        <div style="background:linear-gradient(135deg,#024442,#035b58);border-radius:14px;padding:16px 14px;text-align:center;">
            <div style="font-size:11px;font-weight:600;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">This Month</div>
            <div style="font-size:26px;font-weight:800;color:#B6F763;"><?= number_format($monthlyUnique) ?></div>
        </div>

        <div style="background:#f0fdf4;border-radius:14px;padding:16px 14px;text-align:center;border:1px solid #e2fbd4;">
            <div style="font-size:11px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">All Time</div>
            <div style="font-size:26px;font-weight:800;color:#024442;"><?= number_format($totalUnique) ?></div>
        </div>

    </div>

    <!-- 7-day sparkline -->
    <div>
        <div style="font-size:12px;font-weight:600;color:#888;margin-bottom:10px;">Last 7 Days</div>
        <canvas id="visitorSparkline" height="60" style="width:100%;display:block;"></canvas>
        <div style="display:flex;justify-content:space-between;margin-top:6px;">
            <?php
            $labels = [];
            for ($i = 6; $i >= 0; $i--) {
                $labels[] = date('D', strtotime("-$i days"));
            }
            foreach ($labels as $label): ?>
            <span style="font-size:11px;color:#aaa;"><?= $label ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
(function() {
    const values = <?= json_encode($sparkValues) ?>;
    const canvas = document.getElementById('visitorSparkline');
    if (!canvas) return;
    const ctx    = canvas.getContext('2d');

    // Hi-DPI
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width  = rect.width  * dpr || 400 * dpr;
    canvas.height = 60 * dpr;
    ctx.scale(dpr, dpr);

    const W    = canvas.width  / dpr;
    const H    = canvas.height / dpr;
    const max  = Math.max(...values, 1);
    const pad  = 6;
    const step = (W - pad * 2) / (values.length - 1);

    const points = values.map((v, i) => ({
        x: pad + i * step,
        y: H - pad - ((v / max) * (H - pad * 2))
    }));

    // Gradient fill
    const grad = ctx.createLinearGradient(0, 0, 0, H);
    grad.addColorStop(0,   'rgba(182,247,99,0.35)');
    grad.addColorStop(1,   'rgba(182,247,99,0)');

    ctx.beginPath();
    ctx.moveTo(points[0].x, points[0].y);
    for (let i = 1; i < points.length; i++) {
        const cp1x = (points[i-1].x + points[i].x) / 2;
        ctx.bezierCurveTo(cp1x, points[i-1].y, cp1x, points[i].y, points[i].x, points[i].y);
    }
    ctx.lineTo(points[points.length-1].x, H);
    ctx.lineTo(points[0].x, H);
    ctx.closePath();
    ctx.fillStyle = grad;
    ctx.fill();

    // Line
    ctx.beginPath();
    ctx.moveTo(points[0].x, points[0].y);
    for (let i = 1; i < points.length; i++) {
        const cp1x = (points[i-1].x + points[i].x) / 2;
        ctx.bezierCurveTo(cp1x, points[i-1].y, cp1x, points[i].y, points[i].x, points[i].y);
    }
    ctx.strokeStyle = '#024442';
    ctx.lineWidth   = 2.5;
    ctx.stroke();

    // Dots
    points.forEach((p, i) => {
        ctx.beginPath();
        ctx.arc(p.x, p.y, 3.5, 0, Math.PI * 2);
        ctx.fillStyle   = '#B6F763';
        ctx.strokeStyle = '#024442';
        ctx.lineWidth   = 1.5;
        ctx.fill();
        ctx.stroke();
    });
})();
</script>