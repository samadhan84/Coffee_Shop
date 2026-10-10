<?php
// Inventory: stock levels, low-stock flags, restocking (staff + admin) and item setup (admin).
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/orders.php';

$user = require_role('admin', 'staff');
$isAdmin = $user['role'] === 'admin';
$pdo = db();

if (is_post()) {
    verify_csrf();
    $action = post('action');
    try {
        if ($action === 'restock') {
            $qty = (float) post('quantity');
            if ($qty <= 0) {
                throw new RuntimeException('Enter a quantity greater than zero.');
            }
            adjust_stock((int) post('id'), $qty, 'Restock' . (post('note') !== '' ? ': ' . mb_substr(post('note'), 0, 80) : ''), $user['id']);
            flash('success', 'Stock added.');
        } elseif ($action === 'adjust' && $isAdmin) {
            // Physical stock count: set the exact quantity, logging the difference.
            $stmt = $pdo->prepare('SELECT quantity FROM ingredients WHERE id = ?');
            $stmt->execute([(int) post('id')]);
            $diff = max(0, (float) post('quantity')) - (float) $stmt->fetchColumn();
            if ($diff != 0) {
                adjust_stock((int) post('id'), $diff, 'Stock count adjustment', $user['id']);
            }
            flash('success', 'Stock adjusted.');
        } elseif ($action === 'save' && $isAdmin) {
            if (post('name') === '' || post('unit') === '') {
                throw new RuntimeException('Name and unit are required.');
            }
            $data = [post('name'), post('unit'), max(0, (float) post('reorder_level')), max(0, (float) post('cost_per_unit'))];
            if ($id = (int) post('id')) {
                $pdo->prepare('UPDATE ingredients SET name = ?, unit = ?, reorder_level = ?, cost_per_unit = ? WHERE id = ?')->execute([...$data, $id]);
            } else {
                $pdo->prepare('INSERT INTO ingredients (name, unit, reorder_level, cost_per_unit, quantity) VALUES (?, ?, ?, ?, 0)')->execute($data);
                if ((float) post('quantity') > 0) {
                    adjust_stock((int) $pdo->lastInsertId(), (float) post('quantity'), 'Opening stock', $user['id']);
                }
            }
            flash('success', 'Ingredient saved.');
        } elseif ($action === 'delete' && $isAdmin) {
            $pdo->prepare('DELETE FROM ingredients WHERE id = ?')->execute([(int) post('id')]);
            flash('success', 'Ingredient deleted.');
        }
    } catch (PDOException $e) {
        flash('error', $e->getCode() === '23000' ? 'An ingredient with that name already exists.' : 'Database error.');
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/inventory.php');
}

$filter = $_GET['filter'] ?? '';
$ingredients = $pdo->query('SELECT * FROM ingredients' . ($filter === 'low' ? ' WHERE quantity <= reorder_level' : '') . ' ORDER BY name')->fetchAll();
$movements = $pdo->query('SELECT s.*, i.name, i.unit, u.name AS user_name FROM stock_movements s
                          JOIN ingredients i ON i.id = s.ingredient_id LEFT JOIN users u ON u.id = s.created_by
                          ORDER BY s.created_at DESC, s.id DESC LIMIT 25')->fetchAll();
$stockValue = array_sum(array_map(fn($i) => $i['quantity'] * $i['cost_per_unit'], $ingredients));
$edit = null;
if ($isAdmin && isset($_GET['edit'])) {
    foreach ($ingredients as $i) {
        if ($i['id'] == $_GET['edit']) $edit = $i;
    }
}

admin_start('Inventory', 'inventory');
?>
<div class="quick-actions">
    <a class="btn <?= $filter === 'low' ? 'btn-outline' : 'btn-brown' ?> btn-sm" href="<?= e(base_url('admin/inventory.php')) ?>">All items</a>
    <a class="btn <?= $filter === 'low' ? 'btn-brown' : 'btn-outline' ?> btn-sm" href="<?= e(base_url('admin/inventory.php?filter=low')) ?>">Low stock only</a>
    <span class="muted">Stock value: <strong><?= e(money($stockValue)) ?></strong></span>
</div>

<section class="panel">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Ingredient</th><th>In stock</th><th>Reorder level</th><th>Cost / unit</th><th>Restock</th><?php if ($isAdmin): ?><th>Stock count</th><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($ingredients as $i): $low = $i['quantity'] <= $i['reorder_level']; ?>
                <tr class="<?= $low ? 'row-warn' : '' ?>">
                    <td><strong><?= e($i['name']) ?></strong> <?= $low ? '<span class="badge badge-cancelled">Low</span>' : '' ?></td>
                    <td><?= e(+$i['quantity']) ?> <?= e($i['unit']) ?></td>
                    <td><?= e(+$i['reorder_level']) ?> <?= e($i['unit']) ?></td>
                    <td><?= e(money($i['cost_per_unit'])) ?></td>
                    <td>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?><input type="hidden" name="action" value="restock"><input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                            <input type="number" name="quantity" step="0.001" min="0" placeholder="+ qty" class="qty-input" required>
                            <input type="text" name="note" placeholder="Supplier / note" class="note-input">
                            <button class="btn btn-yellow btn-sm">Add</button>
                        </form>
                    </td>
                    <?php if ($isAdmin): ?>
                        <td>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?><input type="hidden" name="action" value="adjust"><input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                                <input type="number" name="quantity" step="0.001" min="0" value="<?= e(+$i['quantity']) ?>" class="qty-input">
                                <button class="btn btn-outline btn-sm">Set</button>
                            </form>
                        </td>
                        <td class="nowrap">
                            <a href="<?= e(base_url('admin/inventory.php?edit=' . $i['id'])) ?>#ingredient-form">Edit</a>
                            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                                <button class="link-btn danger" data-confirm="Delete <?= e($i['name']) ?>? It will be removed from recipes too.">Delete</button></form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$ingredients): ?><tr><td colspan="7" class="empty">Nothing to show.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="two-col">
    <?php if ($isAdmin): ?>
        <section class="panel" id="ingredient-form">
            <h2><?= $edit ? 'Edit ' . e($edit['name']) : 'Add Ingredient' ?></h2>
            <form method="post" class="form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                <label>Name <input type="text" name="name" value="<?= e($edit['name'] ?? '') ?>" required></label>
                <label>Unit <input type="text" name="unit" value="<?= e($edit['unit'] ?? '') ?>" placeholder="g, ml, pcs" required></label>
                <label>Reorder level <input type="number" step="0.001" min="0" name="reorder_level" value="<?= e(+($edit['reorder_level'] ?? 0)) ?>"></label>
                <label>Cost per unit <input type="number" step="0.01" min="0" name="cost_per_unit" value="<?= e(+($edit['cost_per_unit'] ?? 0)) ?>"></label>
                <?php if (!$edit): ?><label>Opening stock <input type="number" step="0.001" min="0" name="quantity" value="0"></label><?php endif; ?>
                <div class="span-2"><button class="btn btn-brown">Save</button>
                    <?php if ($edit): ?><a class="btn btn-outline" href="<?= e(base_url('admin/inventory.php')) ?>">Cancel</a><?php endif; ?></div>
            </form>
        </section>
    <?php endif; ?>
    <section class="panel">
        <h2>Recent Stock Movements</h2>
        <div class="table-wrap">
            <table class="table compact">
                <thead><tr><th>When</th><th>Ingredient</th><th>Change</th><th>Reason</th><th>By</th></tr></thead>
                <tbody>
                <?php foreach ($movements as $m): ?>
                    <tr><td><?= e(date('d M, H:i', strtotime($m['created_at']))) ?></td><td><?= e($m['name']) ?></td>
                        <td class="<?= $m['change_qty'] < 0 ? 'neg' : 'pos' ?>"><?= $m['change_qty'] > 0 ? '+' : '' ?><?= e(+$m['change_qty']) ?> <?= e($m['unit']) ?></td>
                        <td><?= e($m['reason']) ?></td><td><?= e($m['user_name'] ?? '—') ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$movements): ?><tr><td colspan="5" class="empty">No movements yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php
admin_end();
