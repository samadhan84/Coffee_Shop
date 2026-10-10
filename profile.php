<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$user = require_role('customer');
$customer = customer_for_user($user);

if (is_post()) {
    verify_csrf();
    if (post('action') === 'profile') {
        $name = post('name');
        $phone = post('phone');
        if ($name === '') {
            flash('error', 'Name is required.');
        } else {
            db()->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?')->execute([$name, $phone ?: null, $user['id']]);
            db()->prepare('UPDATE customers SET name = ?, phone = ? WHERE id = ?')->execute([$name, $phone ?: null, $customer['id']]);
            flash('success', 'Profile updated.');
        }
    } elseif (post('action') === 'password') {
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        if (!password_verify(post('current'), $stmt->fetchColumn())) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen(post('new')) < 6 || post('new') !== post('confirm')) {
            flash('error', 'New passwords must match and be at least 6 characters.');
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash(post('new'), PASSWORD_DEFAULT), $user['id']]);
            flash('success', 'Password changed.');
        }
    }
    redirect('profile.php');
}

page_head('My Profile', 'app-body');
customer_nav('profile');
?>
<main class="container app-content">
    <?= render_flash() ?>
    <div class="two-col">
        <section class="panel">
            <h2>👤 My Profile</h2>
            <p class="muted"><?= e($user['email']) ?> · <?= (int) $customer['loyalty_points'] ?> loyalty points</p>
            <form method="post" class="form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profile">
                <label>Name <input type="text" name="name" value="<?= e($user['name']) ?>" required></label>
                <label>Phone <input type="tel" name="phone" value="<?= e($user['phone']) ?>"></label>
                <button class="btn btn-brown">Save</button>
            </form>
        </section>
        <section class="panel">
            <h2>🔒 Change Password</h2>
            <form method="post" class="form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <label>Current password <input type="password" name="current" required></label>
                <label>New password <input type="password" name="new" minlength="6" required></label>
                <label>Confirm new password <input type="password" name="confirm" required></label>
                <button class="btn btn-brown">Change Password</button>
            </form>
        </section>
    </div>
</main>
<?php
page_end();
