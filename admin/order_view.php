<?php
// Single order: track status, take payment, modify items or cancel.
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/orders.php';

$user = require_role('admin', 'staff');
$id = (int) ($_GET['id'] ?? 0);
$order = get_order($id);
if (!$order) {
    flash('error', 'Order not found.');
    redirect('admin/orders.php');
}

if (is_post()) {
    verify_csrf();
    try {
        switch (post('action')) {
            case 'status':
                update_order_status($id, post('status'), $user['id']);
                flash('success', 'Status updated.');
                break;
            case 'pay':
                mark_order_paid($id, post('payment_method'));
                flash('success', 'Payment recorded.');
                break;
            case 'modify':
                $items = [];
                foreach ((array) post('qty', []) as $itemId => $qty) {
                    $items[(int) $itemId] = (int) $qty;
                }
                if ((int) post('add_item') && (int) post('add_qty') > 0) {
                    $items[(int) post('add_item')] = ($items[(int) post('add_item')] ?? 0) + (int) post('add_qty');
                }
                modify_order($order, $items, [
                    'discount_percent' => (float) post('discount_percent'),
                    'extra_charges'    => (float) post('extra_charges'),
                    'table_no'         => mb_substr(post('table_no'), 0, 10),
                    'notes'            => mb_substr(post('notes'), 0, 255),
                ], $user['id']);
                flash('success', 'Order updated and bill recalculated.');
                break;
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/order_view.php?id=' . $id);
}

$editable = $order['payment_status'] === 'unpaid' && !in_array($order['status'], ['completed', 'cancelled'], true);
$menu = $editable ? db()->query('SELECT id, name, price FROM menu_items WHERE is_available = 1 ORDER BY name')->fetchAll() : [];

admin_start('Order ' . $order['order_no'], 'orders');
?>
<div class="two-col wide-left">
    <section class="panel">
        <h2>Items</h2>
        <?php if ($editable): ?>
            <form method="post" class="form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="modify">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Item</th><th>Rate</th><th>Qty (0 = remove)</th><th class="right">Amount</th></tr></thead>
                        <tbody>
                        <?php foreach ($order['items'] as $it): ?>
                            <tr><td><?= e($it['item_name']) ?></td><td><?= e(money($it['unit_price'])) ?></td>
                                <td><input class="qty-input" type="number" min="0" max="100" name="qty[<?= (int) $it['menu_item_id'] ?>]" value="<?= (int) $it['quantity'] ?>"></td>
                                <td class="right"><?= e(money($it['line_total'])) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="form-row">
                    <label>Add item
                        <select name="add_item">
                            <option value="">—</option>
                            <?php foreach ($menu as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?> (<?= e(money($m['price'])) ?>)</option><?php endforeach; ?>
                        </select>
                    </label>
                    <label>Qty <input type="number" name="add_qty" min="1" max="100" value="1"></label>
                </div>
                <div class="form-row">
                    <label>Discount % <input type="number" name="discount_percent" min="0" max="100" step="0.5" value="<?= e(+$order['discount_percent']) ?>"></label>
                    <label>Extra charges <input type="number" name="extra_charges" min="0" step="1" value="<?= e(+$order['extra_charges']) ?>"></label>
                    <?php if ($order['order_type'] === 'dine_in'): ?>
                        <label>Table <input type="text" name="table_no" maxlength="10" value="<?= e($order['table_no']) ?>"></label>
                    <?php endif; ?>
                </div>
                <label>Notes <input type="text" name="notes" maxlength="255" value="<?= e($order['notes']) ?>"></label>
                <button class="btn btn-brown">Save changes & recalculate bill</button>
            </form>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Item</th><th>Rate</th><th>Qty</th><th class="right">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($order['items'] as $it): ?>
                        <tr><td><?= e($it['item_name']) ?></td><td><?= e(money($it['unit_price'])) ?></td><td><?= (int) $it['quantity'] ?></td><td class="right"><?= e(money($it['line_total'])) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="muted">Paid, completed or cancelled orders can no longer be modified.</p>
            <?php if ($order['notes']): ?><p>Note: <?= e($order['notes']) ?></p><?php endif; ?>
        <?php endif; ?>
    </section>

    <div>
        <section class="panel">
            <h2>Summary</h2>
            <ul class="kv">
                <li><span>Type</span><?= e(ORDER_TYPES[$order['order_type']]) ?><?= $order['table_no'] ? ' · Table ' . e($order['table_no']) : '' ?></li>
                <li><span>Customer</span><?= e($order['customer_name'] ?? 'Walk-in') ?></li>
                <li><span>Placed</span><?= e(date('d M Y, h:i A', strtotime($order['created_at']))) ?></li>
                <li><span>By</span><?= e($order['created_by_name'] ?? '—') ?></li>
                <li><span>Status</span><?= status_badge($order['status']) ?></li>
                <li><span>Payment</span><?= status_badge($order['payment_status']) ?> <?= $order['payment_method'] ? e(PAYMENT_METHODS[$order['payment_method']]) : '' ?></li>
            </ul>
            <div class="bill-box">
                <div><span>Subtotal</span><span><?= e(money($order['subtotal'])) ?></span></div>
                <div><span>Discount (<?= e(+$order['discount_percent']) ?>%)</span><span>-<?= e(money($order['discount_amount'])) ?></span></div>
                <div><span>Tax (<?= e(+$order['tax_rate']) ?>%)</span><span><?= e(money($order['tax_amount'])) ?></span></div>
                <div><span>Extra</span><span><?= e(money($order['extra_charges'])) ?></span></div>
                <div class="bill-total"><span>Total</span><span><?= e(money($order['total'])) ?></span></div>
            </div>
            <a class="btn btn-outline btn-block" href="<?= e(base_url('receipt.php?id=' . $id)) ?>">🖨 View / Print Bill</a>
        </section>

        <?php if ($order['status'] !== 'cancelled'): ?>
            <section class="panel">
                <h2>Update</h2>
                <form method="post" class="form-row">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="status">
                    <select name="status">
                        <?php foreach (ORDER_STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn btn-brown btn-sm" data-confirm-select="cancelled" data-confirm="Cancel this order and return stock?">Set status</button>
                </form>
                <?php if ($order['payment_status'] === 'unpaid'): ?>
                    <form method="post" class="form-row">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="pay">
                        <select name="payment_method">
                            <?php foreach (PAYMENT_METHODS as $k => $label): ?><option value="<?= e($k) ?>" <?= $order['payment_method'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                        </select>
                        <button class="btn btn-yellow btn-sm">Mark as paid</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php
admin_end();
