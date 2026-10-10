<?php
// Menu management (admin): items, prices, categories and recipes.
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('admin');
$pdo = db();

if (is_post()) {
    verify_csrf();
    $action = post('action');
    try {
        if ($action === 'save_item') {
            $id = (int) post('id');
            $name = post('name');
            $price = (float) post('price');
            if ($name === '' || $price <= 0) {
                throw new RuntimeException('Name and a positive price are required.');
            }
            $data = [(int) post('category_id'), $name, post('description') ?: null, $price,
                     mb_substr(post('icon') ?: '☕', 0, 4), post('is_available') ? 1 : 0, post('is_featured') ? 1 : 0];
            if ($id) {
                $pdo->prepare('UPDATE menu_items SET category_id = ?, name = ?, description = ?, price = ?, icon = ?,
                               is_available = ?, is_featured = ? WHERE id = ?')->execute([...$data, $id]);
            } else {
                $pdo->prepare('INSERT INTO menu_items (category_id, name, description, price, icon, is_available, is_featured)
                               VALUES (?, ?, ?, ?, ?, ?, ?)')->execute($data);
                $id = (int) $pdo->lastInsertId();
            }
            // Recipe: ingredient quantity used per item sold.
            $pdo->prepare('DELETE FROM menu_item_ingredients WHERE menu_item_id = ?')->execute([$id]);
            $ins = $pdo->prepare('INSERT INTO menu_item_ingredients (menu_item_id, ingredient_id, quantity_used) VALUES (?, ?, ?)');
            foreach ((array) post('recipe', []) as $ingredientId => $qty) {
                if ((float) $qty > 0) {
                    $ins->execute([$id, (int) $ingredientId, (float) $qty]);
                }
            }
            flash('success', 'Menu item saved.');
        } elseif ($action === 'toggle') {
            $pdo->prepare('UPDATE menu_items SET is_available = 1 - is_available WHERE id = ?')->execute([(int) post('id')]);
            flash('success', 'Availability updated.');
        } elseif ($action === 'delete_item') {
            $pdo->prepare('DELETE FROM menu_items WHERE id = ?')->execute([(int) post('id')]);
            flash('success', 'Menu item removed. Past bills keep their item names.');
        } elseif ($action === 'add_category' && post('category') !== '') {
            $pdo->prepare('INSERT INTO categories (name) VALUES (?)')->execute([post('category')]);
            flash('success', 'Category added.');
        } elseif ($action === 'delete_category') {
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([(int) post('id')]);
            flash('success', 'Category deleted.');
        }
    } catch (PDOException $e) {
        flash('error', $e->getCode() === '23000' ? 'That name already exists, or the category still has items.' : 'Database error.');
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/menu.php');
}

$categories = $pdo->query('SELECT c.*, COUNT(m.id) AS items FROM categories c LEFT JOIN menu_items m ON m.category_id = c.id
                           GROUP BY c.id ORDER BY c.id')->fetchAll();
$items = $pdo->query('SELECT m.*, c.name AS category FROM menu_items m JOIN categories c ON c.id = m.category_id ORDER BY c.id, m.name')->fetchAll();
$ingredients = $pdo->query('SELECT id, name, unit FROM ingredients ORDER BY name')->fetchAll();

$edit = null;
$recipe = [];
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM menu_items WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
    if ($edit) {
        $stmt = $pdo->prepare('SELECT ingredient_id, quantity_used FROM menu_item_ingredients WHERE menu_item_id = ?');
        $stmt->execute([$edit['id']]);
        $recipe = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
$showForm = $edit || isset($_GET['new']);

admin_start('Menu Management', 'menu');
?>
<div class="quick-actions">
    <a class="btn btn-yellow" href="<?= e(base_url('admin/menu.php?new=1')) ?>">+ Add Menu Item</a>
</div>

<?php if ($showForm): ?>
    <section class="panel">
        <h2><?= $edit ? 'Edit ' . e($edit['name']) : 'New Menu Item' ?></h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_item">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <label>Name <input type="text" name="name" value="<?= e($edit['name'] ?? '') ?>" required></label>
            <label>Category
                <select name="category_id">
                    <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= ($edit['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Price (<?= e(setting('currency')) ?>) <input type="number" name="price" step="0.01" min="0.01" value="<?= e($edit['price'] ?? '') ?>" required></label>
            <label>Icon (emoji) <input type="text" name="icon" maxlength="4" value="<?= e($edit['icon'] ?? '☕') ?>"></label>
            <label class="span-2">Description <input type="text" name="description" maxlength="255" value="<?= e($edit['description'] ?? '') ?>"></label>
            <label class="check"><input type="checkbox" name="is_available" value="1" <?= ($edit['is_available'] ?? 1) ? 'checked' : '' ?>> Available</label>
            <label class="check"><input type="checkbox" name="is_featured" value="1" <?= ($edit['is_featured'] ?? 0) ? 'checked' : '' ?>> Featured on home page</label>
            <fieldset class="span-2">
                <legend>Recipe — ingredients used per item (leave blank if none)</legend>
                <div class="recipe-grid">
                    <?php foreach ($ingredients as $ing): ?>
                        <label><?= e($ing['name']) ?> (<?= e($ing['unit']) ?>)
                            <input type="number" step="0.001" min="0" name="recipe[<?= (int) $ing['id'] ?>]" value="<?= isset($recipe[$ing['id']]) ? e(+$recipe[$ing['id']]) : '' ?>">
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <div class="span-2">
                <button class="btn btn-brown">Save Item</button>
                <a class="btn btn-outline" href="<?= e(base_url('admin/menu.php')) ?>">Cancel</a>
            </div>
        </form>
    </section>
<?php endif; ?>

<div class="two-col wide-left">
    <section class="panel">
        <h2>Menu Items (<?= count($items) ?>)</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Item</th><th>Category</th><th>Price</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($items as $m): ?>
                    <tr>
                        <td><?= e($m['icon']) ?> <strong><?= e($m['name']) ?></strong><?= $m['is_featured'] ? ' <span class="badge badge-ready">Featured</span>' : '' ?><br><small><?= e($m['description']) ?></small></td>
                        <td><?= e($m['category']) ?></td>
                        <td><?= e(money($m['price'])) ?></td>
                        <td><?= $m['is_available'] ? status_badge('completed') : status_badge('cancelled') ?></td>
                        <td class="nowrap">
                            <a href="<?= e(base_url('admin/menu.php?edit=' . $m['id'])) ?>">Edit</a>
                            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                <button class="link-btn"><?= $m['is_available'] ? 'Hide' : 'Show' ?></button></form>
                            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="delete_item"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                <button class="link-btn danger" data-confirm="Delete <?= e($m['name']) ?>?">Delete</button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted">Status labels: <?= status_badge('completed') ?> = available, <?= status_badge('cancelled') ?> = hidden from menu.</p>
    </section>
    <section class="panel">
        <h2>Categories</h2>
        <ul class="kv">
            <?php foreach ($categories as $c): ?>
                <li><span><?= e($c['name']) ?> (<?= (int) $c['items'] ?>)</span>
                    <?php if (!$c['items']): ?>
                        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="link-btn danger">Delete</button></form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" class="form-row">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_category">
            <input type="text" name="category" placeholder="New category" required>
            <button class="btn btn-brown btn-sm">Add</button>
        </form>
    </section>
</div>
<?php
admin_end();
