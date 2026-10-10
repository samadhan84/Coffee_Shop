<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/orders.php';

$user = require_role('customer');
$customer = customer_for_user($user);

if (is_post()) {
    verify_csrf();
    $rating = (int) post('rating');
    if ($rating >= 1 && $rating <= 5) {
        db()->prepare("UPDATE orders SET rating = ? WHERE id = ? AND customer_id = ? AND status = 'completed'")
            ->execute([$rating, (int) post('order_id'), $customer['id']]);
        flash('success', 'Thanks for your feedback!');
    }
    redirect('my_orders.php');
}

$stmt = db()->prepare('SELECT o.*, (SELECT GROUP_CONCAT(CONCAT(quantity, " × ", item_name) SEPARATOR ", ")
                                    FROM order_items WHERE order_id = o.id) AS summary
                       FROM orders o WHERE customer_id = ? ORDER BY created_at DESC');
$stmt->execute([$customer['id']]);
$orders = $stmt->fetchAll();

page_head('My Orders', 'app-body');
customer_nav('orders');
?>
<main class="container app-content">
    <?= render_flash() ?>
    <section class="panel">
        <h2>📦 My Orders</h2>
        <?php if (!$orders): ?>
            <p class="empty">No orders yet. <a href="<?= e(base_url('dashboard.php#menu')) ?>">Order something tasty</a>!</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th>Payment</th><th>Rating</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><strong><?= e($o['order_no']) ?></strong><br><small><?= e(ORDER_TYPES[$o['order_type']]) ?></small></td>
                            <td><?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?></td>
                            <td><?= e($o['summary']) ?></td>
                            <td><?= e(money($o['total'])) ?></td>
                            <td><?= status_badge($o['status']) ?></td>
                            <td><?= status_badge($o['payment_status']) ?></td>
                            <td>
                                <?php if ($o['rating']): ?>
                                    <?= str_repeat('⭐', (int) $o['rating']) ?>
                                <?php elseif ($o['status'] === 'completed'): ?>
                                    <form method="post" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                                        <select name="rating" aria-label="Rating">
                                            <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?> ★</option><?php endfor; ?>
                                        </select>
                                        <button class="btn btn-sm btn-yellow">Rate</button>
                                    </form>
                                <?php else: ?>–<?php endif; ?>
                            </td>
                            <td><a href="<?= e(base_url('receipt.php?id=' . $o['id'])) ?>">Receipt</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php
page_end();
