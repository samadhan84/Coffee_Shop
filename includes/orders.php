<?php
// Order, billing and stock logic shared by the POS, customer checkout and order screens.

const ORDER_STATUSES  = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];
const ORDER_TYPES     = ['dine_in' => 'Dine-in', 'takeaway' => 'Takeaway', 'online' => 'Online'];
const PAYMENT_METHODS = ['cash' => 'Cash', 'card' => 'Card', 'upi' => 'UPI'];

/**
 * Bill calculation: discount applies to the subtotal, tax applies to the
 * discounted amount, then extra charges (packing, delivery...) are added.
 */
function calculate_bill(float $subtotal, float $taxRate, float $discountPercent, float $extra): array
{
    $discountPercent = max(0, min(100, $discountPercent));
    $discount = round($subtotal * $discountPercent / 100, 2);
    $taxable  = $subtotal - $discount;
    $tax      = round($taxable * $taxRate / 100, 2);
    return [
        'subtotal'         => round($subtotal, 2),
        'tax_rate'         => $taxRate,
        'tax_amount'       => $tax,
        'discount_percent' => $discountPercent,
        'discount_amount'  => $discount,
        'extra_charges'    => round(max(0, $extra), 2),
        'total'            => round($taxable + $tax + max(0, $extra), 2),
    ];
}

/** Order lines priced from the database: [[menu_item_id, name, price, qty, line_total], ...]. */
function price_lines(array $items): array
{
    $items = array_filter(array_map('intval', $items), fn($q) => $q > 0);
    if (!$items) {
        throw new RuntimeException('Add at least one item to the order.');
    }
    $ids = array_keys($items);
    $stmt = db()->prepare('SELECT id, name, price FROM menu_items WHERE is_available = 1 AND id IN ('
        . implode(',', array_fill(0, count($ids), '?')) . ')');
    $stmt->execute($ids);
    $menu = array_column($stmt->fetchAll(), null, 'id');

    $lines = [];
    foreach ($items as $id => $qty) {
        if (!isset($menu[$id])) {
            throw new RuntimeException('One of the selected items is no longer available.');
        }
        $lines[] = [$id, $menu[$id]['name'], $menu[$id]['price'], min($qty, 100), $menu[$id]['price'] * min($qty, 100)];
    }
    return $lines;
}

/** Deducts recipe ingredients for the given lines, failing if any ingredient is short. */
function consume_stock(array $lines, string $reason, ?int $userId): void
{
    $qtyByItem = array_column($lines, 3, 0);
    $ids = array_keys($qtyByItem);
    $stmt = db()->prepare('SELECT r.ingredient_id, r.menu_item_id, r.quantity_used, i.name, i.quantity, i.unit
                           FROM menu_item_ingredients r JOIN ingredients i ON i.id = r.ingredient_id
                           WHERE r.menu_item_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') FOR UPDATE');
    $stmt->execute($ids);
    $need = [];
    foreach ($stmt as $r) {
        $need[$r['ingredient_id']] ??= ['qty' => 0, 'stock' => $r['quantity'], 'name' => $r['name'], 'unit' => $r['unit']];
        $need[$r['ingredient_id']]['qty'] += $r['quantity_used'] * $qtyByItem[$r['menu_item_id']];
    }
    foreach ($need as $n) {
        if ($n['qty'] > $n['stock']) {
            throw new RuntimeException(sprintf('Not enough %s in stock (need %s %s, have %s %s).',
                $n['name'], +$n['qty'], $n['unit'], +$n['stock'], $n['unit']));
        }
    }
    foreach ($need as $ingredientId => $n) {
        adjust_stock($ingredientId, -$n['qty'], $reason, $userId);
    }
}

/** Puts an order's ingredients back into stock (cancel or modify). */
function restore_stock(int $orderId, string $reason, ?int $userId): void
{
    $stmt = db()->prepare('SELECT r.ingredient_id, SUM(r.quantity_used * oi.quantity) AS qty
                           FROM order_items oi JOIN menu_item_ingredients r ON r.menu_item_id = oi.menu_item_id
                           WHERE oi.order_id = ? GROUP BY r.ingredient_id');
    $stmt->execute([$orderId]);
    foreach ($stmt->fetchAll() as $r) {
        adjust_stock((int) $r['ingredient_id'], (float) $r['qty'], $reason, $userId);
    }
}

function save_order_lines(int $orderId, array $lines): void
{
    db()->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$orderId]);
    $stmt = db()->prepare('INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity, line_total)
                           VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($lines as $line) {
        $stmt->execute([$orderId, ...$line]);
    }
}

/** Runs $fn inside a DB transaction. */
function in_transaction(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Creates an order from [menu_item_id => quantity], using prices from the
 * database, and deducts the recipe ingredients from inventory.
 * Throws RuntimeException with a user-facing message on failure.
 */
function create_order(array $items, array $opts): int
{
    $type = $opts['order_type'] ?? 'dine_in';
    if (!isset(ORDER_TYPES[$type])) {
        throw new RuntimeException('Invalid order type.');
    }
    $method = $opts['payment_method'] ?? null;
    if ($method !== null && !isset(PAYMENT_METHODS[$method])) {
        throw new RuntimeException('Invalid payment method.');
    }

    return in_transaction(function () use ($items, $opts, $type, $method) {
        $pdo = db();
        $lines = price_lines($items);
        $bill = calculate_bill(array_sum(array_column($lines, 4)), (float) setting('tax_rate', '0'),
            (float) ($opts['discount_percent'] ?? 0), (float) ($opts['extra_charges'] ?? 0));
        $paid = ($opts['payment_status'] ?? 'unpaid') === 'paid';

        $stmt = $pdo->prepare('INSERT INTO orders (customer_id, order_type, table_no, status, subtotal, tax_rate, tax_amount,
                discount_percent, discount_amount, extra_charges, total, payment_method, payment_status, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            ($opts['customer_id'] ?? null) ?: null, $type, $type === 'dine_in' ? (($opts['table_no'] ?? '') ?: null) : null,
            $opts['status'] ?? 'pending', $bill['subtotal'], $bill['tax_rate'], $bill['tax_amount'],
            $bill['discount_percent'], $bill['discount_amount'], $bill['extra_charges'], $bill['total'],
            $method, $paid ? 'paid' : 'unpaid', ($opts['notes'] ?? '') ?: null, $opts['created_by'] ?? null,
        ]);
        $orderId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE orders SET order_no = ? WHERE id = ?')
            ->execute(['ORD-' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT), $orderId]);

        save_order_lines($orderId, $lines);
        consume_stock($lines, 'Order #' . $orderId, $opts['created_by'] ?? null);
        if ($paid) {
            award_points($orderId);
        }
        return $orderId;
    });
}

/**
 * Modifies an unpaid, open order: new item quantities, discount, extra
 * charges, table and notes. Stock is returned and re-deducted.
 */
function modify_order(array $order, array $items, array $opts, ?int $userId): void
{
    if ($order['payment_status'] !== 'unpaid' || in_array($order['status'], ['completed', 'cancelled'], true)) {
        throw new RuntimeException('Only open, unpaid orders can be modified.');
    }
    in_transaction(function () use ($order, $items, $opts, $userId) {
        restore_stock((int) $order['id'], 'Modified order #' . $order['id'], $userId);
        $lines = price_lines($items);
        consume_stock($lines, 'Modified order #' . $order['id'], $userId);
        save_order_lines((int) $order['id'], $lines);
        $bill = calculate_bill(array_sum(array_column($lines, 4)), (float) $order['tax_rate'],
            (float) $opts['discount_percent'], (float) $opts['extra_charges']);
        db()->prepare('UPDATE orders SET subtotal = ?, tax_amount = ?, discount_percent = ?, discount_amount = ?,
                       extra_charges = ?, total = ?, table_no = ?, notes = ? WHERE id = ?')
            ->execute([$bill['subtotal'], $bill['tax_amount'], $bill['discount_percent'], $bill['discount_amount'],
                $bill['extra_charges'], $bill['total'],
                $order['order_type'] === 'dine_in' ? ($opts['table_no'] ?: null) : null, $opts['notes'] ?: null, $order['id']]);
    });
}

function adjust_stock(int $ingredientId, float $change, string $reason, ?int $userId): void
{
    db()->prepare('UPDATE ingredients SET quantity = quantity + ? WHERE id = ?')->execute([$change, $ingredientId]);
    db()->prepare('INSERT INTO stock_movements (ingredient_id, change_qty, reason, created_by) VALUES (?, ?, ?, ?)')
        ->execute([$ingredientId, $change, $reason, $userId]);
}

/** 1 loyalty point per 100 spent, credited when the order is paid. */
function award_points(int $orderId): void
{
    db()->prepare('UPDATE customers c JOIN orders o ON o.customer_id = c.id
                   SET c.loyalty_points = c.loyalty_points + FLOOR(o.total / 100) WHERE o.id = ?')
        ->execute([$orderId]);
}

function get_order(int $id): ?array
{
    $stmt = db()->prepare('SELECT o.*, c.name AS customer_name, c.phone AS customer_phone, c.user_id AS customer_user_id,
                                  u.name AS created_by_name
                           FROM orders o
                           LEFT JOIN customers c ON c.id = o.customer_id
                           LEFT JOIN users u ON u.id = o.created_by
                           WHERE o.id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $stmt->execute([$id]);
    $order['items'] = $stmt->fetchAll();
    return $order;
}

function update_order_status(int $id, string $status, ?int $userId): void
{
    if (!in_array($status, ORDER_STATUSES, true)) {
        throw new RuntimeException('Invalid status.');
    }
    $order = get_order($id);
    if (!$order) {
        throw new RuntimeException('Order not found.');
    }
    if ($order['status'] === 'cancelled') {
        throw new RuntimeException('Cancelled orders cannot be changed.');
    }
    if ($status === 'cancelled') {
        cancel_order($order, $userId);
        return;
    }
    db()->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $id]);
}

/** Cancels an order and returns its ingredients to stock. */
function cancel_order(array $order, ?int $userId): void
{
    in_transaction(function () use ($order, $userId) {
        restore_stock((int) $order['id'], 'Cancelled order #' . $order['id'], $userId);
        db()->prepare("UPDATE orders SET status = 'cancelled',
                       payment_status = IF(payment_status = 'paid', 'refunded', payment_status) WHERE id = ?")
            ->execute([$order['id']]);
    });
}

function mark_order_paid(int $id, string $method): void
{
    if (!isset(PAYMENT_METHODS[$method])) {
        throw new RuntimeException('Invalid payment method.');
    }
    $stmt = db()->prepare("UPDATE orders SET payment_status = 'paid', payment_method = ?
                           WHERE id = ? AND payment_status = 'unpaid' AND status <> 'cancelled'");
    $stmt->execute([$method, $id]);
    if ($stmt->rowCount()) {
        award_points($id);
    }
}
