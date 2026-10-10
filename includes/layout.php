<?php
// Shared page chrome: <head>, public navbar, customer topbar, admin sidebar and footers.

function page_head(string $title, string $bodyClass = ''): void
{
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | <?= e(setting('shop_name', 'Coffee Shop')) ?></title>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<?php
}

function page_end(): void
{
    ?>
<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
</body>
</html>
<?php
}

function public_nav(string $active = 'home', bool $transparent = false): void
{
    $user  = current_user();
    $links = ['home' => ['Home', 'index.php'], 'coffees' => ['Our Coffees', 'index.php#coffees'],
              'about' => ['About', 'index.php#about']];
    ?>
<header class="navbar <?= $transparent ? 'navbar-transparent' : '' ?>" id="navbar">
    <div class="container nav-inner">
        <a class="logo" href="<?= e(base_url('index.php')) ?>"><span class="dot"></span>Coffee<br><span class="logo-white">Shop</span><span class="dot"></span></a>
        <button class="nav-toggle" aria-label="Menu" data-toggle="nav-links">☰</button>
        <nav class="nav-links" id="nav-links">
            <?php foreach ($links as $key => [$label, $href]): ?>
                <a href="<?= e(base_url($href)) ?>" class="<?= $key === $active ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <?php if ($user): ?>
                <a href="<?= e(home_for($user)) ?>">Dashboard</a>
                <a href="<?= e(base_url('logout.php')) ?>">Logout</a>
            <?php else: ?>
                <a href="<?= e(base_url('login.php')) ?>" class="<?= $active === 'login' ? 'active' : '' ?>">Login</a>
                <a href="<?= e(base_url('signup.php')) ?>" class="<?= $active === 'signup' ? 'active' : '' ?>">Sign Up</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<?php
}

function public_footer(): void
{
    ?>
<footer class="footer" id="contact">
    <div class="container footer-grid">
        <div>
            <h3><?= e(setting('shop_name')) ?></h3>
            <p><?= e(setting('tagline')) ?></p>
            <p class="muted-light"><?= e(setting('address')) ?><br><?= e(setting('phone')) ?></p>
        </div>
        <div>
            <h4>Quick Links</h4>
            <a href="<?= e(base_url('index.php#coffees')) ?>">Our Coffees</a>
            <a href="<?= e(base_url('index.php#about')) ?>">About</a>
            <a href="<?= e(base_url('index.php#contact')) ?>">Contact</a>
        </div>
        <div>
            <h4>Follow Us</h4>
            <div class="social"><span>📘</span><span>🐦</span><span>📷</span></div>
        </div>
    </div>
    <p class="copyright">© <?= date('Y') ?> <?= e(setting('shop_name')) ?>. All rights reserved.</p>
</footer>
<?php
}

/** Top bar for logged-in customers. */
function customer_nav(string $active = ''): void
{
    $user = current_user();
    $cartCount = array_sum($_SESSION['cart'] ?? []);
    $links = ['dashboard' => ['Menu', 'dashboard.php'], 'cart' => ['Cart', 'cart.php'],
              'orders' => ['My Orders', 'my_orders.php'], 'profile' => ['Profile', 'profile.php']];
    ?>
<header class="appbar">
    <div class="container nav-inner">
        <a class="logo-flat" href="<?= e(base_url('dashboard.php')) ?>">CoffeeShop</a>
        <button class="nav-toggle" aria-label="Menu" data-toggle="app-links">☰</button>
        <nav class="nav-links" id="app-links">
            <?php foreach ($links as $key => [$label, $href]): ?>
                <a href="<?= e(base_url($href)) ?>" class="<?= $key === $active ? 'active' : '' ?>">
                    <?= e($label) ?><?php if ($key === 'cart'): ?> <span class="cart-badge"><?= $cartCount ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
            <span class="avatar" title="<?= e($user['name']) ?>"><?= e(initials($user['name'])) ?></span>
            <a class="btn btn-ghost btn-sm" href="<?= e(base_url('logout.php')) ?>">Logout</a>
        </nav>
    </div>
</header>
<?php
}

/** Opens the admin/staff layout with sidebar. Admin-only links are hidden from staff. */
function admin_start(string $title, string $active): void
{
    $user = current_user();
    $menu = [
        'dashboard' => ['📊', 'Dashboard', 'admin/index.php', false],
        'pos'       => ['🧾', 'New Order (POS)', 'admin/pos.php', false],
        'orders'    => ['📦', 'Orders', 'admin/orders.php', false],
        'menu'      => ['☕', 'Menu', 'admin/menu.php', true],
        'inventory' => ['🏷️', 'Inventory', 'admin/inventory.php', false],
        'customers' => ['👥', 'Customers', 'admin/customers.php', false],
        'employees' => ['🧑‍🍳', 'Employees', 'admin/employees.php', true],
        'reports'   => ['📈', 'Reports', 'admin/reports.php', true],
        'users'     => ['🔐', 'User Accounts', 'admin/users.php', true],
        'settings'  => ['⚙️', 'Settings & Backup', 'admin/settings.php', true],
    ];
    page_head($title, 'admin-body');
    ?>
<div class="admin-layout">
    <aside class="sidebar" id="sidebar">
        <a class="logo-flat" href="<?= e(base_url('admin/index.php')) ?>">☕ CoffeeShop</a>
        <nav>
            <?php foreach ($menu as $key => [$icon, $label, $href, $adminOnly]):
                if ($adminOnly && $user['role'] !== 'admin') continue; ?>
                <a href="<?= e(base_url($href)) ?>" class="<?= $key === $active ? 'active' : '' ?>"><span><?= $icon ?></span><?= e($label) ?></a>
            <?php endforeach; ?>
            <a href="<?= e(base_url('index.php')) ?>"><span>🏠</span>View Website</a>
        </nav>
    </aside>
    <div class="admin-main">
        <header class="admin-topbar">
            <button class="nav-toggle dark" aria-label="Menu" data-toggle="sidebar">☰</button>
            <h1><?= e($title) ?></h1>
            <div class="topbar-user">
                <span class="avatar"><?= e(initials($user['name'])) ?></span>
                <div><strong><?= e($user['name']) ?></strong><small><?= e(ucfirst($user['role'])) ?></small></div>
                <a class="btn btn-outline btn-sm" href="<?= e(base_url('logout.php')) ?>">Logout</a>
            </div>
        </header>
        <main class="admin-content">
            <?= render_flash() ?>
<?php
}

function admin_end(): void
{
    ?>
        </main>
    </div>
</div>
<?php
    page_end();
}
