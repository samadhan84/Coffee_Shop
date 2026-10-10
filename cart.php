<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/orders.php';

$user = require_role('customer');
$customer = customer_for_user($user);

// Packing charge added to online orders.
const ONLINE_PACKING_CHARGE = 20;

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'update' && post('remove') !== '') {
        cart_set((int) post('remove'), 0);
    } elseif ($action === 'update') {
        foreach ((array) post('qty', []) as $id => $qty) {
            cart_set((int) $id, (int) $qty);
        }
        flash('success', 'Cart updated.');
    } elseif ($action === 'checkout') {
        try {
            $orderId = create_order($_SESSION['cart'] ?? [], [
                'customer_id'    => $customer['id'],
                'order_type'     => 'online',
                'extra_charges'  => ONLINE_PACKING_CHARGE,
                'payment_method' => post('payment_method') ?: null,
                'payment_status' => 'unpaid',
                'notes'          => mb_substr(post('notes'), 0, 255),
                'created_by'     => $user['id'],
            ]);
            $_SESSION['cart'] = [];
            flash('success', 'Order placed! Pay at pickup or via UPI when it is ready.');
            redirect('receipt.php?id=' . $orderId);
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
    }
    redirect('cart.php');
}

$lines = cart_lines();
$bill = calculate_bill(cart_subtotal($lines), (float) setting('tax_rate'), 0, $lines ? ONLINE_PACKING_CHARGE : 0);

page_head('My Cart', 'app-body');
customer_nav('cart');
?>
<main class="container app-content">
    <?= render_flash() ?>
    <section class="panel">
        <h2>🛒 My Cart</h2>
        <?php if (!$lines): ?>
            <p class="empty">Your cart is empty. <a href="<?= e(base_url('dashboard.php#menu')) ?>">Browse the menu</a>.</p>
        <?php else: ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Item</th><th>Price</th><th>Qty</th><th class="right">Total</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($lines as $l): ?>
                            <tr>
                                <td><?= e($l['icon']) ?> <?= e($l['name']) ?></td>
                                <td><?= e(money($l['price'])) ?></td>
                                <td><input class="qty-input" type="number" min="0" max="50" name="qty[<?= (int) $l['id'] ?>]" value="<?= (int) $l['qty'] ?>"></td>
                                <td class="right"><?= e(money($l['line_total'])) ?></td>
                                <td><button class="link-btn danger" form="remove-form" name="remove" value="<?= (int) $l['id'] ?>">Remove</button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-outline btn-sm">Update quantities</button>
            </form>
            <form method="post" id="remove-form"><?= csrf_field() ?><input type="hidden" name="action" value="update"></form>

            <div class="checkout-grid">
                <div class="bill-box">
                    <div><span>Subtotal</span><span><?= e(money($bill['subtotal'])) ?></span></div>
                    <div><span>Tax (<?= e(+$bill['tax_rate']) ?>%)</span><span><?= e(money($bill['tax_amount'])) ?></span></div>
                    <div><span>Packing charge</span><span><?= e(money($bill['extra_charges'])) ?></span></div>
                    <div class="bill-total"><span>Total</span><span><?= e(money($bill['total'])) ?></span></div>
                </div>
                <form method="post" class="form-stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="checkout">
                    <label>Preferred payment
                        <select name="payment_method">
                            <option value="cash">Cash at pickup</option>
                            <option value="upi">UPI</option>
                            <option value="card">Card at pickup</option>
                        </select>
                    </label>
                    <label>Notes for the barista
                        <input type="text" name="notes" maxlength="255" placeholder="e.g. less sugar">
                    </label>
                    <button class="btn btn-yellow btn-block">Place Order</button>
                </form>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php
page_end();
