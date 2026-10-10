<?php
// Point of sale: staff take dine-in / takeaway orders and generate the bill.
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/orders.php';

$user = require_role('admin', 'staff');

if (is_post()) {
    verify_csrf();
    try {
        $customerId = (int) post('customer_id') ?: null;
        // Quick-add a new walk-in customer from the POS screen.
        if (!$customerId && post('new_customer_name') !== '') {
            db()->prepare('INSERT INTO customers (name, phone) VALUES (?, ?)')
                ->execute([post('new_customer_name'), post('new_customer_phone') ?: null]);
            $customerId = (int) db()->lastInsertId();
        }
        $items = json_decode(post('items', '{}'), true);
        $orderId = create_order(is_array($items) ? $items : [], [
            'customer_id'      => $customerId,
            'order_type'       => post('order_type'),
            'table_no'         => mb_substr(post('table_no'), 0, 10),
            'discount_percent' => (float) post('discount_percent'),
            'extra_charges'    => (float) post('extra_charges'),
            'payment_method'   => post('payment_method') ?: null,
            'payment_status'   => post('paid') ? 'paid' : 'unpaid',
            'notes'            => mb_substr(post('notes'), 0, 255),
            'created_by'       => $user['id'],
        ]);
        flash('success', 'Order created successfully.');
        redirect('receipt.php?id=' . $orderId);
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        redirect('admin/pos.php');
    }
}

$menu = db()->query('SELECT m.id, m.name, m.price, m.icon, c.name AS category FROM menu_items m
                     JOIN categories c ON c.id = m.category_id WHERE m.is_available = 1 ORDER BY c.id, m.name')->fetchAll();
$customers = db()->query('SELECT id, name, phone FROM customers ORDER BY name')->fetchAll();
$categories = array_unique(array_column($menu, 'category'));

admin_start('New Order (POS)', 'pos');
?>
<div class="pos-layout">
    <section class="panel">
        <div class="pos-filters">
            <input type="search" id="pos-search" placeholder="Search menu…">
            <div class="chip-row">
                <button type="button" class="chip active" data-cat="">All</button>
                <?php foreach ($categories as $c): ?>
                    <button type="button" class="chip" data-cat="<?= e($c) ?>"><?= e($c) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="pos-grid" id="pos-grid">
            <?php foreach ($menu as $m): ?>
                <button type="button" class="pos-item" data-id="<?= (int) $m['id'] ?>" data-name="<?= e($m['name']) ?>"
                        data-price="<?= e($m['price']) ?>" data-cat="<?= e($m['category']) ?>">
                    <span class="pos-icon"><?= e($m['icon']) ?></span>
                    <span><?= e($m['name']) ?></span>
                    <strong><?= e(money($m['price'])) ?></strong>
                </button>
            <?php endforeach; ?>
        </div>
    </section>

    <form method="post" class="panel pos-bill" id="pos-form" data-tax="<?= e(setting('tax_rate')) ?>" data-currency="<?= e(setting('currency')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="items" id="pos-items" value="{}">
        <h2>Current Order</h2>
        <div class="seg">
            <label><input type="radio" name="order_type" value="dine_in" checked> Dine-in</label>
            <label><input type="radio" name="order_type" value="takeaway"> Takeaway</label>
        </div>
        <label id="table-field">Table no. <input type="text" name="table_no" maxlength="10" placeholder="e.g. T3"></label>
        <label>Customer
            <select name="customer_id" id="customer-select">
                <option value="">Walk-in / new customer</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?><?= $c['phone'] ? ' (' . e($c['phone']) . ')' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="form-row" id="new-customer">
            <input type="text" name="new_customer_name" placeholder="New customer name (optional)">
            <input type="tel" name="new_customer_phone" placeholder="Phone">
        </div>

        <ul class="pos-lines" id="pos-lines"><li class="empty">Tap menu items to add them.</li></ul>

        <div class="form-row">
            <label>Discount % <input type="number" name="discount_percent" id="discount" min="0" max="100" step="0.5" value="0"></label>
            <label>Extra charges <input type="number" name="extra_charges" id="extra" min="0" step="1" value="0"></label>
        </div>
        <div class="bill-box" id="bill-box"></div>
        <div class="form-row">
            <label>Payment
                <select name="payment_method">
                    <?php foreach (PAYMENT_METHODS as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="check"><input type="checkbox" name="paid" value="1" checked> Paid now</label>
        </div>
        <label>Notes <input type="text" name="notes" maxlength="255" placeholder="e.g. extra hot, no sugar"></label>
        <button class="btn btn-yellow btn-block" id="pos-submit" disabled>Place Order & Generate Bill</button>
    </form>
</div>
<?php
admin_end();
