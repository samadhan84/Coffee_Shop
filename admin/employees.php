<?php
// Employee records, roles, shifts, working hours and activity (admin only).
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$admin = require_role('admin');
$pdo = db();

if (is_post()) {
    verify_csrf();
    $action = post('action');
    try {
        if ($action === 'save') {
            $id = (int) post('id');
            $email = post('email');
            if (post('name') === '' || post('position') === '') {
                throw new RuntimeException('Name and position are required.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Invalid email address.');
            }
            $data = [post('name'), post('phone') ?: null, $email ?: null, post('position'), max(0, (float) post('salary')),
                     post('shift_start') ?: null, post('shift_end') ?: null, post('join_date') ?: null,
                     post('status') === 'inactive' ? 'inactive' : 'active'];
            $pdo->beginTransaction();
            if ($id) {
                $pdo->prepare('UPDATE employees SET name = ?, phone = ?, email = ?, position = ?, salary = ?, shift_start = ?,
                               shift_end = ?, join_date = ?, status = ? WHERE id = ?')->execute([...$data, $id]);
            } else {
                $pdo->prepare('INSERT INTO employees (name, phone, email, position, salary, shift_start, shift_end, join_date, status)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute($data);
                $id = (int) $pdo->lastInsertId();
            }
            // Optional system login for this employee (staff role).
            if (post('create_login') && post('password') !== '') {
                if ($email === '' || strlen(post('password')) < 6) {
                    throw new RuntimeException('A login needs an email and a password of at least 6 characters.');
                }
                $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'staff')")
                    ->execute([post('name'), $email, post('phone') ?: null, password_hash(post('password'), PASSWORD_DEFAULT)]);
                $pdo->prepare('UPDATE employees SET user_id = ? WHERE id = ?')->execute([(int) $pdo->lastInsertId(), $id]);
            }
            // Inactive employees cannot log in.
            $pdo->prepare('UPDATE users u JOIN employees e ON e.user_id = u.id SET u.is_active = ? WHERE e.id = ? AND u.id <> ?')
                ->execute([$data[8] === 'active' ? 1 : 0, $id, $admin['id']]);
            $pdo->commit();
            flash('success', 'Employee saved.');
        } elseif ($action === 'hours') {
            $hours = (float) post('hours_worked');
            if ($hours <= 0 || $hours > 24) {
                throw new RuntimeException('Hours must be between 0 and 24.');
            }
            $pdo->prepare('INSERT INTO attendance (employee_id, work_date, hours_worked, notes) VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE hours_worked = VALUES(hours_worked), notes = VALUES(notes)')
                ->execute([(int) post('employee_id'), post('work_date') ?: date('Y-m-d'), $hours, post('notes') ?: null]);
            flash('success', 'Working hours recorded.');
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $e->getCode() === '23000' ? 'That email already has a login.' : 'Database error.');
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $e->getMessage());
    }
    redirect('admin/employees.php' . (post('id') ? '?edit=' . (int) post('id') : ''));
}

$employees = $pdo->query("SELECT e.*, u.role, u.email AS login_email,
            (SELECT COALESCE(SUM(hours_worked), 0) FROM attendance a WHERE a.employee_id = e.id
                 AND a.work_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS month_hours,
            (SELECT COUNT(*) FROM orders o WHERE o.created_by = e.user_id AND o.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS month_orders
        FROM employees e LEFT JOIN users u ON u.id = e.user_id ORDER BY e.status, e.name")->fetchAll();
$recentHours = $pdo->query('SELECT a.*, e.name FROM attendance a JOIN employees e ON e.id = a.employee_id
                            ORDER BY a.work_date DESC, e.name LIMIT 15')->fetchAll();
$edit = null;
foreach ($employees as $emp) {
    if (isset($_GET['edit']) && $emp['id'] == $_GET['edit']) $edit = $emp;
}

admin_start('Employees', 'employees');
?>
<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>Position</th><th>Shift</th><th>Salary</th><th>Hours (this month)</th><th>Orders handled</th><th>Login</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($employees as $emp): ?>
                <tr>
                    <td><strong><?= e($emp['name']) ?></strong><br><small><?= e($emp['phone'] ?? '') ?></small></td>
                    <td><?= e($emp['position']) ?></td>
                    <td><?= $emp['shift_start'] ? e(substr($emp['shift_start'], 0, 5) . '–' . substr($emp['shift_end'] ?? '', 0, 5)) : '—' ?></td>
                    <td><?= e(money($emp['salary'])) ?></td>
                    <td><?= e(+$emp['month_hours']) ?> h</td>
                    <td><?= (int) $emp['month_orders'] ?></td>
                    <td><?= $emp['role'] ? e(ucfirst($emp['role'])) : '<span class="muted">None</span>' ?></td>
                    <td><?= status_badge($emp['status'] === 'active' ? 'completed' : 'cancelled') ?></td>
                    <td><a href="<?= e(base_url('admin/employees.php?edit=' . $emp['id'])) ?>#emp-form">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="two-col">
    <section class="panel" id="emp-form">
        <h2><?= $edit ? 'Edit ' . e($edit['name']) : 'Add Employee' ?></h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <label>Name <input type="text" name="name" value="<?= e($edit['name'] ?? '') ?>" required></label>
            <label>Position
                <input type="text" name="position" list="positions" value="<?= e($edit['position'] ?? '') ?>" required>
                <datalist id="positions"><option>Manager</option><option>Cashier</option><option>Barista</option><option>Waiter</option><option>Cleaner</option></datalist>
            </label>
            <label>Phone <input type="tel" name="phone" value="<?= e($edit['phone'] ?? '') ?>"></label>
            <label>Email <input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></label>
            <label>Monthly salary <input type="number" name="salary" min="0" step="1" value="<?= e(+($edit['salary'] ?? 0)) ?>"></label>
            <label>Join date <input type="date" name="join_date" value="<?= e($edit['join_date'] ?? '') ?>"></label>
            <label>Shift start <input type="time" name="shift_start" value="<?= e(substr($edit['shift_start'] ?? '', 0, 5)) ?>"></label>
            <label>Shift end <input type="time" name="shift_end" value="<?= e(substr($edit['shift_end'] ?? '', 0, 5)) ?>"></label>
            <label>Status
                <select name="status">
                    <option value="active">Active</option>
                    <option value="inactive" <?= ($edit['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </label>
            <?php if (!($edit['user_id'] ?? null)): ?>
                <fieldset class="span-2">
                    <legend><label class="check"><input type="checkbox" name="create_login" value="1"> Give this employee a staff login</label></legend>
                    <label>Password (uses the email above) <input type="password" name="password" minlength="6"></label>
                </fieldset>
            <?php else: ?>
                <p class="span-2 muted">Login: <?= e($edit['login_email']) ?> (<?= e($edit['role']) ?>). Manage it under User Accounts.</p>
            <?php endif; ?>
            <div class="span-2">
                <button class="btn btn-brown">Save</button>
                <?php if ($edit): ?><a class="btn btn-outline" href="<?= e(base_url('admin/employees.php')) ?>">Cancel</a><?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Record Working Hours</h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="hours">
            <label>Employee
                <select name="employee_id">
                    <?php foreach ($employees as $emp): if ($emp['status'] !== 'active') continue; ?>
                        <option value="<?= (int) $emp['id'] ?>"><?= e($emp['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Date <input type="date" name="work_date" value="<?= date('Y-m-d') ?>"></label>
            <label>Hours <input type="number" name="hours_worked" step="0.25" min="0.25" max="24" required></label>
            <label>Notes <input type="text" name="notes" maxlength="255"></label>
            <div class="span-2"><button class="btn btn-yellow">Save hours</button></div>
        </form>
        <table class="table compact">
            <thead><tr><th>Date</th><th>Employee</th><th>Hours</th><th>Notes</th></tr></thead>
            <tbody>
            <?php foreach ($recentHours as $h): ?>
                <tr><td><?= e(date('d M', strtotime($h['work_date']))) ?></td><td><?= e($h['name']) ?></td><td><?= e(+$h['hours_worked']) ?></td><td><?= e($h['notes'] ?? '') ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
<?php
admin_end();
