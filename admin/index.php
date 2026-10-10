<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/orders.php';

require_role('admin', 'staff');
$pdo = db();

$today = $pdo->query("SELECT COUNT(*) AS orders, COALESCE(SUM(IF(payment_status = 'paid', total, 0)), 0) AS sales
                      FROM orders WHERE DATE(created_at) = CURDATE() AND status <> 'cancelled'")->fetch();
$active = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','preparing','ready')")->fetchColumn();
$unpaid = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'unpaid' AND status <> 'cancelled'")->fetchColumn();
$lowStock = $pdo->query('SELECT * FROM ingredients WHERE quantity <= reorder_level ORDER BY quantity / NULLIF(reorder_level, 0)')->fetchAll();
$recent = $pdo->query('SELECT o.*, c.name AS customer_name FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
                       ORDER BY o.created_at DESC LIMIT 8')->fetchAll();
$top = $pdo->query("SELECT oi.item_name, SUM(oi.quantity) AS qty FROM order_items oi JOIN orders o ON o.id = oi.order_id
                    WHERE o.status <> 'cancelled' AND o.created_at >= CURDATE() - INTERVAL 30 DAY
                    GROUP BY oi.item_name ORDER BY qty DESC LIMIT 5")->fetchAll();

admin_start('Dashboard', 'dashboard');
?>
<div class="stat-grid">
    <div class="stat-card"><span class="stat-icon">💰</span><strong><?= e(money($today['sales'])) ?></strong><small>Today's Sales (paid)</small></div>
    <div class="stat-card"><span class="stat-icon">🧾</span><strong><?= (int) $today['orders'] ?></strong><small>Today's Orders</small></div>
    <div class="stat-card"><span class="stat-icon">⏳</span><strong><?= $active ?></strong><small>Active Orders</small></div>
    <div class="stat-card <?= $lowStock ? 'stat-warn' : '' ?>"><span class="stat-icon">⚠️</span><strong><?= count($lowStock) ?></strong><small>Low-stock Items</small></div>
</div>

<div class="quick-actions">
    <a class="btn btn-yellow" href="<?= e(base_url('admin/pos.php')) ?>">+ New Order</a>
    <a class="btn btn-brown" href="<?= e(base_url('admin/orders.php?status=active')) ?>">Kitchen Queue (<?= $active ?>)</a>
    <a class="btn btn-outline" href="<?= e(base_url('admin/orders.php?payment=unpaid')) ?>">Unpaid Bills (<?= $unpaid ?>)</a>
</div>

<div class="two-col wide-left">
    <section class="panel">
        <h2>Recent Orders</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Order</th><th>Customer</th><th>Type</th><th>Total</th><th>Status</th><th>Payment</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $o): ?>
                    <tr>
                        <td><a href="<?= e(base_url('admin/order_view.php?id=' . $o['id'])) ?>"><?= e($o['order_no']) ?></a></td>
                        <td><?= e($o['customer_name'] ?? 'Walk-in') ?></td>
                        <td><?= e(ORDER_TYPES[$o['order_type']]) ?></td>
                        <td><?= e(money($o['total'])) ?></td>
                        <td><?= status_badge($o['status']) ?></td>
                        <td><?= status_badge($o['payment_status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recent): ?><tr><td colspan="6" class="empty">No orders yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <div>
        <section class="panel">
            <h2>⚠️ Low Stock Alerts</h2>
            <?php if (!$lowStock): ?>
                <p class="muted">All ingredients are above their reorder level.</p>
            <?php else: ?>
                <ul class="alert-list">
                    <?php foreach ($lowStock as $i): ?>
                        <li><strong><?= e($i['name']) ?></strong> — <?= e(+$i['quantity']) ?> <?= e($i['unit']) ?> left
                            <small>(reorder at <?= e(+$i['reorder_level']) ?>)</small></li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?= e(base_url('admin/inventory.php')) ?>">Restock now →</a>
            <?php endif; ?>
        </section>
        <section class="panel">
            <h2>🔥 Popular (30 days)</h2>
            <ol class="top-list">
                <?php foreach ($top as $t): ?><li><?= e($t['item_name']) ?> <span><?= (int) $t['qty'] ?> sold</span></li><?php endforeach; ?>
            </ol>
            <?php if (!$top): ?><p class="muted">No sales yet.</p><?php endif; ?>
        </section>
    </div>
</div>
<?php
admin_end();
