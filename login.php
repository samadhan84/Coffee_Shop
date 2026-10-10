<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

if ($user = current_user()) {
    redirect(home_for($user));
}

$email = '';
$error = '';
if (is_post()) {
    verify_csrf();
    $email = post('email');
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $found = $stmt->fetch();
    if (!$found || !password_verify(post('password'), $found['password_hash'])) {
        $error = 'Invalid email or password.';
    } elseif (!$found['is_active']) {
        $error = 'This account has been deactivated. Please contact the shop.';
    } else {
        login_user($found);
        redirect(home_for($found));
    }
}

page_head('Login', 'auth-body');
?>
<div class="auth-card">
    <h1>☕ Coffee Shop Login</h1>
    <?= render_flash() ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-stack">
        <?= csrf_field() ?>
        <input type="email" name="email" placeholder="Email address" value="<?= e($email) ?>" required autofocus>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" class="btn btn-brown btn-block">Login ☕</button>
    </form>
    <hr>
    <p>Don't have an account? <a href="<?= e(base_url('signup.php')) ?>" class="strong-link">Sign Up Here</a></p>
    <p><a href="<?= e(base_url('index.php')) ?>" class="muted">← Back to Home</a></p>
</div>
<?php
page_end();
