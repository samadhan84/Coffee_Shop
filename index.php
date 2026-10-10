<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$roasts = db()->query("SELECT m.* FROM menu_items m JOIN categories c ON c.id = m.category_id
                       WHERE c.name = 'Coffee Beans' AND m.is_available = 1 ORDER BY m.price")->fetchAll();
$featured = db()->query('SELECT * FROM menu_items WHERE is_featured = 1 AND is_available = 1 ORDER BY name')->fetchAll();
$roastShades = ['#ffe0b2', '#d7b48a', '#8b4513'];
$user = current_user();
$startUrl = $user ? home_for($user) : base_url('signup.php');

page_head('Home');
public_nav('home', true);
?>
<section class="hero" id="home">
    <div class="container hero-inner">
        <div class="hero-text">
            <h1><span class="light">Our</span> Coffee Is</h1>
            <h2>A Never Ending Story</h2>
            <div class="hero-actions">
                <a href="#coffees" class="btn btn-yellow btn-lg">Explore Coffees</a>
                <a href="<?= e($startUrl) ?>" class="btn btn-outline-light btn-lg">Get Started</a>
            </div>
        </div>
        <img class="hero-img" src="<?= e(base_url('assets/images/hero-coffee.jpg')) ?>" alt="Friends sharing coffee">
    </div>
</section>

<section class="section section-cream" id="coffees">
    <div class="container">
        <h2 class="section-title">Our Signature Roasts</h2>
        <div class="card-grid three">
            <?php foreach ($roasts as $i => $r): ?>
                <article class="roast-card">
                    <div class="roast-icon" style="background: <?= e($roastShades[$i % 3]) ?>"><?= e($r['icon']) ?></div>
                    <h3><?= e($r['name']) ?></h3>
                    <p><?= e($r['description']) ?></p>
                    <span class="price-pill"><?= e(money($r['price'])) ?>/kg</span>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($featured): ?>
            <h2 class="section-title small">Café Favourites</h2>
            <div class="card-grid four">
                <?php foreach ($featured as $f): ?>
                    <article class="menu-card">
                        <div class="menu-icon"><?= e($f['icon']) ?></div>
                        <h4><?= e($f['name']) ?></h4>
                        <p><?= e($f['description']) ?></p>
                        <strong><?= e(money($f['price'])) ?></strong>
                    </article>
                <?php endforeach; ?>
            </div>
            <p class="center"><a href="<?= e($startUrl) ?>" class="btn btn-brown">Order Online</a></p>
        <?php endif; ?>
    </div>
</section>

<section class="section" id="about">
    <div class="container narrow center">
        <h2 class="section-title plain">About <?= e(setting('shop_name')) ?></h2>
        <p class="lead">Welcome to <?= e(setting('shop_name')) ?>! Founded in 2015, we source finest beans from Ethiopia,
            Colombia, and Brazil. Our expert roasters blend tradition with modern precision for perfect flavor.</p>
        <div class="hero-actions">
            <a href="<?= e($startUrl) ?>" class="btn btn-yellow btn-lg">Shop Now</a>
            <a href="#coffees" class="btn btn-brown btn-lg">Learn More</a>
        </div>
    </div>
</section>
<?php
public_footer();
page_end();
