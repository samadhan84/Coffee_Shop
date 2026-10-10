<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

if ($user = current_user()) {
    redirect(home_for($user));
}

$name = $email = $phone = '';
$errors = [];
if (is_post()) {
    verify_csrf();
    $name  = post('name');
    $email = post('email');
    $phone = post('phone');
    $password = post('password');

    if ($name === '') $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if ($phone !== '' && !preg_match('/^[0-9+\- ]{7,15}$/', $phone)) $errors[] = 'Enter a valid phone number.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== post('confirm')) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $stmt = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (!$errors) {
        // New sign-ups are always customers; staff accounts are created by the admin.
        db()->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'customer')")
            ->execute([$name, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT)]);
        $newUser = ['id' => (int) db()->lastInsertId(), 'name' => $name, 'email' => $email, 'phone' => $phone ?: null, 'role' => 'customer'];
        customer_for_user($newUser);
        login_user($newUser);
        flash('success', 'Welcome to Coffee Shop, ' . $name . '!');
        redirect('dashboard.php');
    }
}

page_head('Sign Up', 'auth-body');
?>
<div class="auth-card">
    <h1>☕ Create Account</h1>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" class="form-stack">
        <?= csrf_field() ?>
        <input type="text" name="name" placeholder="Full name" value="<?= e($name) ?>" required>
        <input type="email" name="email" placeholder="Email address" value="<?= e($email) ?>" required>
        <input type="tel" name="phone" placeholder="Phone (optional)" value="<?= e($phone) ?>">
        <input type="password" name="password" placeholder="Password (min 6 characters)" required minlength="6">
        <input type="password" name="confirm" placeholder="Confirm password" required>
        <button type="submit" class="btn btn-brown btn-block">Sign Up ☕</button>
    </form>
    <hr>
    <p>Already have an account? <a href="<?= e(base_url('login.php')) ?>" class="strong-link">Login Here</a></p>
    <p><a href="<?= e(base_url('index.php')) ?>" class="muted">← Back to Home</a></p>
</div>
<?php
page_end();
