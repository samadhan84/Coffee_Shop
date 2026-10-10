<?php
// Daily / weekly / monthly sales and inventory reports (admin only).
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/orders.php';

require_role('admin');
$pdo = db();

$period = $_GET['period'] ?? 'weekly';
$valid = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
switch ($period) {
    case 'daily':   $from = $to = date('Y-m-d'); break;
    case 'monthly': $from = date('Y-m-01'); $to = date('Y-m-d'); break;
    case 'custom':
        $from = $valid($_GET['from'] ?? null) ? $_GET['from'] : date('Y-m-01');
        $to   = $valid($_GET['to'] ?? null) ? $_GET['to'] : date('Y-m-d');
        if ($from > $to) [$from, $to] = [$to, $from];
        break;
    default:        $period = 'weekly'; $from = date('Y-m-d', strtotime('-6 days')); $to = date('Y-m-d');
}
$range = [$from . ' 00:00:00', $to . ' 23:59:59'];
// Sales figures count paid, non-cancelled orders only.
$paidWhere = "o.created_at BETWEEN ? AND ? AND o.status <> 'cancelled' AND o.payment_status = 'paid'";

if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $pdo->prepare("SELECT o.order_no, o.created_at, o.order_type, c.name AS customer, o.status, o.payment_status,
                                  o.payment_method, o.subtotal, o.discount_amount, o.tax_amount, o.extra_charges, o.total
                           FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
                           WHERE o.created_at BETWEEN ? AND ? ORDER BY o.created_at");
    $stmt->execute($range);
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"sales_{$from}_to_{$to}.csv\"");
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order No', 'Date', 'Type', 'Customer', 'Status', 'Payment', 'Method', 'Subtotal', 'Discount', 'Tax', 'Extra', 'Total']);
    foreach ($stmt as $row) {
        fputcsv($out, $row);
    }
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) AS orders, COALESCE(SUM(total), 0) AS sales, COALESCE(SUM(tax_amount), 0) AS tax,
                              COALESCE(SUM(discount_amount), 0) AS discounts, COALESCE(AVG(total), 0) AS avg_order
                       FROM orders o WHERE $paidWhere");
$stmt->execute($range);
$summary = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE o.created_at BETWEEN ? AND ? AND o.status = 'cancelled'");
$stmt->execute($range);
$cancelled = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT DATE(o.created_at) AS day, SUM(o.total) AS sales, COUNT(*) AS orders FROM orders o
                       WHERE $paidWhere GROUP BY day ORDER BY day");
$stmt->execute($range);
$byDay = $stmt->fetchAll(PDO::FETCH_UNIQUE);
// Fill missing days so the chart shows the whole range (capped at 62 bars).
$days = [];
for ($d = strtotime($from), $n = 0; $d <= strtotime($to) && $n < 62; $d = strtotime('+1 day', $d), $n++) {
    $key = date('Y-m-d', $d);
    $days[$key] = (float) ($byDay[$key]['sales'] ?? 0);
}
$maxDay = max($days ?: [0]) ?: 1;

$stmt = $pdo->prepare("SELECT oi.item_name, SUM(oi.quantity) AS qty, SUM(oi.line_total) AS revenue
                       FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE $paidWhere
                       GROUP BY oi.item_name ORDER BY qty DESC LIMIT 10");
$stmt->execute($range);
$topItems = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT o.payment_method, COUNT(*) AS orders, SUM(o.total) AS sales FROM orders o WHERE $paidWhere GROUP BY o.payment_method");
$stmt->execute($range);
$byMethod = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT o.order_type, COUNT(*) AS orders, SUM(o.total) AS sales FROM orders o WHERE $paidWhere GROUP BY o.order_type");
$stmt->execute($range);
$byType = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT i.name, i.unit, -SUM(s.change_qty) AS used, i.quantity, i.reorder_level
                       FROM stock_movements s JOIN ingredients i ON i.id = s.ingredient_id
                       WHERE s.created_at BETWEEN ? AND ? AND s.change_qty < 0
                       GROUP BY i.id ORDER BY i.name");
$stmt->execute($range);
$usage = $stmt->fetchAll();
$lowStock = $pdo->query('SELECT * FROM ingredients WHERE quantity <= reorder_level ORDER BY name')->fetchAll();

admin_start('Reports', 'reports');
?>
<form class="filter-bar panel no-print" method="get">
    <?php foreach (['daily' => 'Today', 'weekly' => 'Last 7 days', 'monthly' => 'This month', 'custom' => 'Custom'] as $k => $label): ?>
        <label class="chip-radio"><input type="radio" name="period" value="<?= $k ?>" <?= $period === $k ? 'checked' : '' ?> data-autosubmit> <?= $label ?></label>
    <?php endforeach; ?>
    <input type="date" name="from" value="<?= e($from) ?>">
    <input type="date" name="to" value="<?= e($to) ?>">
    <button class="btn btn-brown btn-sm" name="period" value="custom">Apply range</button>
    <a class="btn btn-outline btn-sm" href="?<?= e(http_build_query(['period' => 'custom', 'from' => $from, 'to' => $to, 'export' => 'csv'])) ?>">⬇ CSV</a>
    <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">🖨 Print</button>
</form>

<p class="muted">Showing <?= e(date('d M Y', strtotime($from))) ?> – <?= e(date('d M Y', strtotime($to))) ?> (paid orders only).</p>

<div class="stat-grid five">
    <div class="stat-card"><span class="stat-icon">💰</span><strong><?= e(money($summary['sales'])) ?></strong><small>Total Sales</small></div>
    <div class="stat-card"><span class="stat-icon">🧾</span><strong><?= (int) $summary['orders'] ?></strong><small>Orders</small></div>
    <div class="stat-card"><span class="stat-icon">📊</span><strong><?= e(money($summary['avg_order'])) ?></strong><small>Avg Order</small></div>
    <div class="stat-card"><span class="stat-icon">🏛️</span><strong><?= e(money($summary['tax'])) ?></strong><small>Tax Collected</small></div>
    <div class="stat-card"><span class="stat-icon">🏷️</span><strong><?= e(money($summary['discounts'])) ?></strong><small>Discounts · <?= $cancelled ?> cancelled</small></div>
</div>

<section class="panel">
    <h2>Sales by Day</h2>
    <div class="bar-chart" role="img" aria-label="Daily sales bar chart">
        <?php foreach ($days as $day => $amount): ?>
            <div class="bar-col" title="<?= e(date('d M', strtotime($day)) . ': ' . money($amount)) ?>">
                <span class="bar-value"><?= $amount ? e(number_format($amount)) : '' ?></span>
                <div class="bar" style="height: <?= round($amount / $maxDay * 100, 1) ?>%"></div>
                <span class="bar-label"><?= e(date(count($days) > 10 ? 'd' : 'D d', strtotime($day))) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="two-col">
    <section class="panel">
        <h2>Top Selling Items</h2>
        <table class="table compact">
            <thead><tr><th>#</th><th>Item</th><th>Qty</th><th class="right">Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($topItems as $i => $t): ?>
                <tr><td><?= $i + 1 ?></td><td><?= e($t['item_name']) ?></td><td><?= (int) $t['qty'] ?></td><td class="right"><?= e(money($t['revenue'])) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$topItems): ?><tr><td colspan="4" class="empty">No sales in this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
    <section class="panel">
        <h2>Sales Breakdown</h2>
        <table class="table compact">
            <thead><tr><th>Payment method</th><th>Orders</th><th class="right">Sales</th></tr></thead>
            <tbody>
            <?php foreach ($byMethod as $m): ?>
                <tr><td><?= e(PAYMENT_METHODS[$m['payment_method']] ?? 'Unspecified') ?></td><td><?= (int) $m['orders'] ?></td><td class="right"><?= e(money($m['sales'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <table class="table compact">
            <thead><tr><th>Order type</th><th>Orders</th><th class="right">Sales</th></tr></thead>
            <tbody>
            <?php foreach ($byType as $t): ?>
                <tr><td><?= e(ORDER_TYPES[$t['order_type']]) ?></td><td><?= (int) $t['orders'] ?></td><td class="right"><?= e(money($t['sales'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>

<div class="two-col">
    <section class="panel">
        <h2>Ingredient Usage</h2>
        <table class="table compact">
            <thead><tr><th>Ingredient</th><th>Used</th><th>In stock</th></tr></thead>
            <tbody>
            <?php foreach ($usage as $u): ?>
                <tr class="<?= $u['quantity'] <= $u['reorder_level'] ? 'row-warn' : '' ?>"><td><?= e($u['name']) ?></td><td><?= e(+$u['used']) ?> <?= e($u['unit']) ?></td><td><?= e(+$u['quantity']) ?> <?= e($u['unit']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$usage): ?><tr><td colspan="3" class="empty">No stock used in this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
    <section class="panel">
        <h2>Restock Needed</h2>
        <table class="table compact">
            <thead><tr><th>Ingredient</th><th>In stock</th><th>Reorder level</th></tr></thead>
            <tbody>
            <?php foreach ($lowStock as $l): ?>
                <tr class="row-warn"><td><?= e($l['name']) ?></td><td><?= e(+$l['quantity']) ?> <?= e($l['unit']) ?></td><td><?= e(+$l['reorder_level']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$lowStock): ?><tr><td colspan="3" class="empty">All stock levels are healthy.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
</div>
<?php
admin_end();
