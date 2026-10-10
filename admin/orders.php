<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/orders.php';

$user = require_role('admin', 'staff');

// Quick status change from the list.
if (is_post()) {
    verify_csrf();
    try {
        update_order_status((int) post('order_id'), post('status'), $user['id']);
        flash('success', 'Order status updated.');
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/orders.php?' . post('query'));
}

$status  = $_GET['status'] ?? '';
$type    = $_GET['type'] ?? '';
$payment = $_GET['payment'] ?? '';
$date    = $_GET['date'] ?? '';
$q       = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($status === 'active') {
    $where[] = "o.status IN ('pending','preparing','ready')";
} elseif (in_array($status, ORDER_STATUSES, true)) {
    $where[] = 'o.status = ?';
    $params[] = $status;
}
if (isset(ORDER_TYPES[$type])) {
    $where[] = 'o.order_type = ?';
    $params[] = $type;
}
if (in_array($payment, ['unpaid', 'paid', 'refunded'], true)) {
    $where[] = 'o.payment_status = ?';
    $params[] = $payment;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $where[] = 'DATE(o.created_at) = ?';
    $params[] = $date;
}
if ($q !== '') {
    $where[] = '(o.order_no LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$sql = 'SELECT o.*, c.name AS customer_name,
               (SELECT GROUP_CONCAT(CONCAT(quantity, "× ", item_name) SEPARATOR ", ") FROM order_items WHERE order_id = o.id) AS summary
        FROM orders o LEFT JOIN customers c ON c.id = o.customer_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY o.created_at DESC LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
$query = http_build_query(array_filter(compact('status', 'type', 'payment', 'date', 'q')));

admin_start('Orders', 'orders');
?>
<form class="filter-bar panel" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Order no, customer or phone">
    <select name="status">
        <option value="">All statuses</option>
        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active (kitchen queue)</option>
        <?php foreach (ORDER_STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
    </select>
    <select name="type">
        <option value="">All types</option>
        <?php foreach (ORDER_TYPES as $k => $label): ?><option value="<?= e($k) ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
    <select name="payment">
        <option value="">Any payment</option>
        <?php foreach (['unpaid', 'paid', 'refunded'] as $p): ?><option value="<?= $p ?>" <?= $payment === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option><?php endforeach; ?>
    </select>
    <input type="date" name="date" value="<?= e($date) ?>">
    <button class="btn btn-brown btn-sm">Filter</button>
    <a href="<?= e(base_url('admin/orders.php')) ?>" class="btn btn-outline btn-sm">Reset</a>
</form>

<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Order</th><th>Time</th><th>Customer</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><strong><?= e($o['order_no']) ?></strong><br><small><?= e(ORDER_TYPES[$o['order_type']]) ?><?= $o['table_no'] ? ' · ' . e($o['table_no']) : '' ?></small></td>
                    <td><?= e(date('d M, h:i A', strtotime($o['created_at']))) ?></td>
                    <td><?= e($o['customer_name'] ?? 'Walk-in') ?></td>
                    <td class="summary"><?= e($o['summary']) ?></td>
                    <td><?= e(money($o['total'])) ?></td>
                    <td><?= status_badge($o['payment_status']) ?></td>
                    <td>
                        <?php if ($o['status'] === 'cancelled'): ?>
                            <?= status_badge('cancelled') ?>
                        <?php else: ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                                <input type="hidden" name="query" value="<?= e($query) ?>">
                                <select name="status" class="status-select" data-autosubmit
                                        data-confirm-value="cancelled" data-confirm="Cancel this order and return stock?">
                                    <?php foreach (ORDER_STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
                                </select>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td class="nowrap">
                        <a href="<?= e(base_url('admin/order_view.php?id=' . $o['id'])) ?>">Open</a> ·
                        <a href="<?= e(base_url('receipt.php?id=' . $o['id'])) ?>">Bill</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?><tr><td colspan="8" class="empty">No orders match these filters.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
admin_end();
