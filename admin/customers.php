<?php
// Customer records and order history.
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/orders.php';

require_role('admin', 'staff');
$pdo = db();

if (is_post()) {
    verify_csrf();
    $name = post('name');
    $phone = post('phone');
    $email = post('email');
    if ($name === '') {
        flash('error', 'Customer name is required.');
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Invalid email address.');
    } else {
        if ($id = (int) post('id')) {
            $pdo->prepare('UPDATE customers SET name = ?, phone = ?, email = ? WHERE id = ?')->execute([$name, $phone ?: null, $email ?: null, $id]);
        } else {
            $pdo->prepare('INSERT INTO customers (name, phone, email) VALUES (?, ?, ?)')->execute([$name, $phone ?: null, $email ?: null]);
            $id = (int) $pdo->lastInsertId();
        }
        flash('success', 'Customer saved.');
        redirect('admin/customers.php?id=' . $id);
    }
    redirect('admin/customers.php');
}

$q = trim($_GET['q'] ?? '');
$stmt = $pdo->prepare("SELECT c.*, COUNT(o.id) AS orders, COALESCE(SUM(IF(o.payment_status = 'paid', o.total, 0)), 0) AS spent,
                              MAX(o.created_at) AS last_order
                       FROM customers c LEFT JOIN orders o ON o.customer_id = c.id AND o.status <> 'cancelled'
                       WHERE c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?
                       GROUP BY c.id ORDER BY c.name");
$stmt->execute(["%$q%", "%$q%", "%$q%"]);
$customers = $stmt->fetchAll();

$selected = null;
$history = [];
$favourites = [];
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $stmt->execute([(int) $_GET['id']]);
    if ($selected = $stmt->fetch()) {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 20');
        $stmt->execute([$selected['id']]);
        $history = $stmt->fetchAll();
        $stmt = $pdo->prepare("SELECT oi.item_name, SUM(oi.quantity) AS qty FROM order_items oi JOIN orders o ON o.id = oi.order_id
                               WHERE o.customer_id = ? AND o.status <> 'cancelled' GROUP BY oi.item_name ORDER BY qty DESC LIMIT 3");
        $stmt->execute([$selected['id']]);
        $favourites = $stmt->fetchAll();
    }
}

admin_start('Customers', 'customers');
?>
<div class="two-col wide-left">
    <section class="panel">
        <form class="filter-bar" method="get">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, phone, email">
            <button class="btn btn-brown btn-sm">Search</button>
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Phone</th><th>Orders</th><th>Total spent</th><th>Points</th><th>Last visit</th></tr></thead>
                <tbody>
                <?php foreach ($customers as $c): ?>
                    <tr class="<?= $selected && $selected['id'] == $c['id'] ? 'row-active' : '' ?>">
                        <td><a href="<?= e(base_url('admin/customers.php?id=' . $c['id'])) ?>"><?= e($c['name']) ?></a><?= $c['user_id'] ? ' <small title="Has an online account">🌐</small>' : '' ?></td>
                        <td><?= e($c['phone'] ?? '—') ?></td>
                        <td><?= (int) $c['orders'] ?></td>
                        <td><?= e(money($c['spent'])) ?></td>
                        <td><?= (int) $c['loyalty_points'] ?></td>
                        <td><?= $c['last_order'] ? e(date('d M Y', strtotime($c['last_order']))) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$customers): ?><tr><td colspan="6" class="empty">No customers found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div>
        <section class="panel">
            <h2><?= $selected ? 'Edit Customer' : 'Add Customer' ?></h2>
            <form method="post" class="form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) ($selected['id'] ?? 0) ?>">
                <label>Name <input type="text" name="name" value="<?= e($selected['name'] ?? '') ?>" required></label>
                <label>Phone <input type="tel" name="phone" value="<?= e($selected['phone'] ?? '') ?>"></label>
                <label>Email <input type="email" name="email" value="<?= e($selected['email'] ?? '') ?>"></label>
                <button class="btn btn-brown">Save</button>
                <?php if ($selected): ?><a href="<?= e(base_url('admin/customers.php')) ?>" class="muted">+ Add a new customer instead</a><?php endif; ?>
            </form>
        </section>
        <?php if ($selected): ?>
            <section class="panel">
                <h2>History — <?= e($selected['name']) ?></h2>
                <?php if ($favourites): ?>
                    <p><strong>Usual order:</strong> <?= e(implode(', ', array_map(fn($f) => $f['item_name'] . ' ×' . $f['qty'], $favourites))) ?></p>
                <?php endif; ?>
                <ul class="kv">
                    <?php foreach ($history as $h): ?>
                        <li><span><a href="<?= e(base_url('admin/order_view.php?id=' . $h['id'])) ?>"><?= e($h['order_no']) ?></a> · <?= e(date('d M Y', strtotime($h['created_at']))) ?></span>
                            <?= e(money($h['total'])) ?> <?= status_badge($h['status']) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!$history): ?><p class="muted">No orders yet.</p><?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php
admin_end();
