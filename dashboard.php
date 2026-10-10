<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/cart.php';

$user = require_role('customer');
$customer = customer_for_user($user);

if (is_post()) {
    verify_csrf();
    cart_add((int) post('item_id'), max(1, (int) post('qty', 1)));
    flash('success', 'Item added to your cart.');
    redirect('dashboard.php#menu');
}

$lines = cart_lines();
$stmt = db()->prepare('SELECT COUNT(*) FROM orders WHERE customer_id = ?');
$stmt->execute([$customer['id']]);
$totalOrders = (int) $stmt->fetchColumn();
$avgRating = db()->query('SELECT ROUND(AVG(rating), 1) FROM orders WHERE rating IS NOT NULL')->fetchColumn() ?: '–';

$menu = db()->query('SELECT m.*, c.name AS category FROM menu_items m JOIN categories c ON c.id = m.category_id
                     WHERE m.is_available = 1 ORDER BY c.id, m.name')->fetchAll(PDO::FETCH_GROUP);

page_head('Dashboard', 'app-body');
customer_nav('dashboard');
?>
<main class="container app-content">
    <?= render_flash() ?>
    <section class="welcome-card">
        <h1>Welcome Back!</h1>
        <p><?= e($user['name']) ?> · <?= (int) $customer['loyalty_points'] ?> loyalty points</p>
        <div class="hero-actions">
            <a href="#menu" class="btn btn-yellow">Continue Shopping ☕</a>
            <a href="<?= e(base_url('profile.php')) ?>" class="btn btn-glass">View Profile</a>
        </div>
    </section>

    <section class="stat-grid">
        <div class="stat-card"><span class="stat-icon">🛒</span><strong><?= array_sum(array_column($lines, 'qty')) ?></strong><small>Cart Items</small></div>
        <div class="stat-card"><span class="stat-icon">💰</span><strong><?= e(money(cart_subtotal($lines))) ?></strong><small>Cart Total</small></div>
        <div class="stat-card"><span class="stat-icon">📦</span><strong><?= $totalOrders ?></strong><small>Total Orders</small></div>
        <div class="stat-card"><span class="stat-icon">⭐</span><strong><?= e($avgRating) ?></strong><small>Avg Rating</small></div>
    </section>

    <section id="menu" class="panel">
        <h2>Our Menu</h2>
        <?php foreach ($menu as $category => $items): ?>
            <h3 class="category-title"><?= e($category) ?></h3>
            <div class="card-grid four">
                <?php foreach ($items as $item): ?>
                    <article class="menu-card">
                        <div class="menu-icon"><?= e($item['icon']) ?></div>
                        <h4><?= e($item['name']) ?></h4>
                        <p><?= e($item['description']) ?></p>
                        <strong><?= e(money($item['price'])) ?></strong>
                        <form method="post" class="add-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                            <input type="number" name="qty" value="1" min="1" max="50" aria-label="Quantity">
                            <button class="btn btn-brown btn-sm">Add</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </section>
</main>
<?php
page_end();
