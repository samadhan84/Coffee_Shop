<?php
// Shop settings and database backup (admin only).
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('admin');
$pdo = db();
$fields = ['shop_name' => 'Shop name', 'tagline' => 'Tagline', 'address' => 'Address', 'phone' => 'Phone',
           'tax_rate' => 'Tax / GST rate (%)', 'currency' => 'Currency symbol'];

if (is_post()) {
    verify_csrf();
    if (post('action') === 'backup') {
        send_backup($pdo);
    }
    $tax = (float) post('tax_rate');
    if ($tax < 0 || $tax > 50) {
        flash('error', 'Tax rate must be between 0 and 50%.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        foreach ($fields as $key => $label) {
            $stmt->execute([$key, mb_substr(post($key), 0, 255)]);
        }
        flash('success', 'Settings saved. The new tax rate applies to new orders.');
    }
    redirect('admin/settings.php');
}

/** Streams an SQL dump of every table (data only) for backup/restore. */
function send_backup(PDO $pdo): never
{
    $tables = ['settings', 'users', 'customers', 'employees', 'attendance', 'categories', 'menu_items', 'ingredients',
               'menu_item_ingredients', 'orders', 'order_items', 'stock_movements'];
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="coffeeshop_backup_' . date('Y-m-d_His') . '.sql"');
    echo "-- Coffee Shop data backup " . date('Y-m-d H:i:s') . "\n";
    echo "-- Restore: import database/coffeeshop.sql first (structure), then this file.\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n";
    foreach ($tables as $table) {
        echo "\nDELETE FROM `$table`;\n";
        foreach ($pdo->query("SELECT * FROM `$table`") as $row) {
            $values = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row));
            echo "INSERT INTO `$table` (`" . implode('`, `', array_keys($row)) . '`) VALUES (' . implode(', ', $values) . ");\n";
        }
    }
    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    exit;
}

$values = $pdo->query('SELECT name, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$counts = [];
foreach (['orders', 'customers', 'menu_items', 'ingredients', 'users'] as $t) {
    $counts[$t] = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
}

admin_start('Settings & Backup', 'settings');
?>
<div class="two-col">
    <section class="panel">
        <h2>Shop Settings</h2>
        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <?php foreach ($fields as $key => $label): ?>
                <label><?= e($label) ?>
                    <input type="<?= $key === 'tax_rate' ? 'number' : 'text' ?>" <?= $key === 'tax_rate' ? 'step="0.01" min="0" max="50"' : '' ?>
                           name="<?= e($key) ?>" value="<?= e($values[$key] ?? '') ?>">
                </label>
            <?php endforeach; ?>
            <button class="btn btn-brown">Save Settings</button>
        </form>
    </section>
    <section class="panel">
        <h2>💾 Data Backup</h2>
        <p>Download a full copy of the shop data (orders, customers, menu, inventory, employees and accounts) as an SQL file.
            Keep backups somewhere safe — they contain password hashes and customer details.</p>
        <ul class="kv">
            <?php foreach ($counts as $t => $n): ?><li><span><?= e(ucwords(str_replace('_', ' ', $t))) ?></span><?= $n ?></li><?php endforeach; ?>
        </ul>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="backup">
            <button class="btn btn-yellow">⬇ Download Backup</button>
        </form>
        <p class="muted">To restore: import <code>database/coffeeshop.sql</code> in phpMyAdmin, then import the backup file.</p>
    </section>
</div>
<?php
admin_end();
