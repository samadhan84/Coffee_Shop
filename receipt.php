<?php
// Printable invoice / receipt. Staff and admin can see any order; customers only their own.
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/orders.php';

$user = require_login();
$order = get_order((int) ($_GET['id'] ?? 0));
if (!$order || ($user['role'] === 'customer' && (int) $order['customer_user_id'] !== (int) $user['id'])) {
    http_response_code(404);
    exit('Order not found.');
}
$back = $user['role'] === 'customer' ? base_url('my_orders.php') : base_url('admin/orders.php');

page_head('Receipt ' . $order['order_no'], 'receipt-body');
?>
<div class="receipt-actions no-print">
    <a href="<?= e($back) ?>" class="btn btn-outline btn-sm">← Back</a>
    <?php if ($user['role'] !== 'customer'): ?>
        <a href="<?= e(base_url('admin/order_view.php?id=' . $order['id'])) ?>" class="btn btn-outline btn-sm">Manage order</a>
    <?php endif; ?>
    <button class="btn btn-brown btn-sm" onclick="window.print()">🖨 Print</button>
</div>
<div class="receipt">
    <div class="no-print"><?= render_flash() ?></div>
    <div class="center">
        <h2>☕ <?= e(setting('shop_name')) ?></h2>
        <p><?= e(setting('address')) ?><br>Ph: <?= e(setting('phone')) ?></p>
        <h3>TAX INVOICE</h3>
    </div>
    <div class="receipt-meta">
        <div><span>Bill No:</span> <?= e($order['order_no']) ?></div>
        <div><span>Date:</span> <?= e(date('d-m-Y h:i A', strtotime($order['created_at']))) ?></div>
        <div><span>Type:</span> <?= e(ORDER_TYPES[$order['order_type']]) ?><?= $order['table_no'] ? ' (Table ' . e($order['table_no']) . ')' : '' ?></div>
        <div><span>Customer:</span> <?= e($order['customer_name'] ?? 'Walk-in') ?></div>
        <?php if ($order['created_by_name']): ?><div><span>Served by:</span> <?= e($order['created_by_name']) ?></div><?php endif; ?>
    </div>
    <table class="receipt-table">
        <thead><tr><th>Item</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead>
        <tbody>
        <?php foreach ($order['items'] as $it): ?>
            <tr><td><?= e($it['item_name']) ?></td><td><?= (int) $it['quantity'] ?></td>
                <td><?= e(number_format($it['unit_price'], 2)) ?></td><td><?= e(number_format($it['line_total'], 2)) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="bill-box">
        <div><span>Subtotal</span><span><?= e(money($order['subtotal'])) ?></span></div>
        <?php if ($order['discount_amount'] > 0): ?>
            <div><span>Discount (<?= e(+$order['discount_percent']) ?>%)</span><span>-<?= e(money($order['discount_amount'])) ?></span></div>
        <?php endif; ?>
        <div><span>Tax / GST (<?= e(+$order['tax_rate']) ?>%)</span><span><?= e(money($order['tax_amount'])) ?></span></div>
        <?php if ($order['extra_charges'] > 0): ?>
            <div><span>Extra charges</span><span><?= e(money($order['extra_charges'])) ?></span></div>
        <?php endif; ?>
        <div class="bill-total"><span>Grand Total</span><span><?= e(money($order['total'])) ?></span></div>
    </div>
    <p class="center">
        Payment: <?= status_badge($order['payment_status']) ?>
        <?= $order['payment_method'] ? '· ' . e(PAYMENT_METHODS[$order['payment_method']]) : '' ?>
        · Status: <?= status_badge($order['status']) ?>
    </p>
    <?php if ($order['notes']): ?><p class="center muted">Note: <?= e($order['notes']) ?></p><?php endif; ?>
    <p class="center thanks">Thank you! Visit again ☕</p>
</div>
<?php
page_end();
