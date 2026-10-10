<?php
// Role-based access control: manage login accounts (admin only).
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$admin = require_role('admin');
$pdo = db();

if (is_post()) {
    verify_csrf();
    $id = (int) post('id');
    if ($id === (int) $admin['id']) {
        flash('error', 'You cannot change your own role or status here.');
    } elseif (post('action') === 'update') {
        $role = in_array(post('role'), ['admin', 'staff', 'customer'], true) ? post('role') : 'customer';
        $pdo->prepare('UPDATE users SET role = ?, is_active = ? WHERE id = ?')->execute([$role, post('is_active') ? 1 : 0, $id]);
        flash('success', 'Account updated.');
    } elseif (post('action') === 'reset') {
        if (strlen(post('password')) < 6) {
            flash('error', 'Password must be at least 6 characters.');
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash(post('password'), PASSWORD_DEFAULT), $id]);
            flash('success', 'Password reset.');
        }
    }
    redirect('admin/users.php');
}

$users = $pdo->query('SELECT * FROM users ORDER BY FIELD(role, "admin", "staff", "customer"), name')->fetchAll();

admin_start('User Accounts', 'users');
?>
<section class="panel">
    <p class="muted"><strong>Admin</strong>: full access · <strong>Staff</strong>: POS, orders, inventory restock, customers ·
        <strong>Customer</strong>: online menu, cart and own orders.</p>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Role & status</th><th>Reset password</th><th>Joined</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): $self = $u['id'] == $admin['id']; ?>
                <tr>
                    <td><strong><?= e($u['name']) ?></strong><?= $self ? ' <small>(you)</small>' : '' ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td>
                        <?php if ($self): ?>
                            <?= e(ucfirst($u['role'])) ?>
                        <?php else: ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <select name="role">
                                    <?php foreach (['admin', 'staff', 'customer'] as $r): ?><option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option><?php endforeach; ?>
                                </select>
                                <label class="check"><input type="checkbox" name="is_active" value="1" <?= $u['is_active'] ? 'checked' : '' ?>> Active</label>
                                <button class="btn btn-outline btn-sm">Save</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$self): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <input type="password" name="password" placeholder="New password" minlength="6" class="note-input" required>
                                <button class="btn btn-outline btn-sm">Reset</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
admin_end();
