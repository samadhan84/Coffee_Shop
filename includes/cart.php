<?php
// Session cart for online (customer) orders: $_SESSION['cart'] = [menu_item_id => quantity].

/** Returns false when the item does not exist or is unavailable. */
function cart_add(int $itemId, int $qty): bool
{
    $stmt = db()->prepare('SELECT 1 FROM menu_items WHERE id = ? AND is_available = 1');
    $stmt->execute([$itemId]);
    if (!$stmt->fetch() || $qty <= 0) {
        return false;
    }
    $_SESSION['cart'][$itemId] = min(50, ($_SESSION['cart'][$itemId] ?? 0) + $qty);
    return true;
}

function cart_set(int $itemId, int $qty): void
{
    if ($qty <= 0) {
        unset($_SESSION['cart'][$itemId]);
    } elseif (isset($_SESSION['cart'][$itemId])) {
        $_SESSION['cart'][$itemId] = min(50, $qty);
    }
}

/** Cart lines with current menu prices; unavailable items are dropped. */
function cart_lines(): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) {
        return [];
    }
    $ids = array_keys($cart);
    $stmt = db()->prepare('SELECT id, name, price, icon FROM menu_items WHERE is_available = 1 AND id IN ('
        . implode(',', array_fill(0, count($ids), '?')) . ')');
    $stmt->execute($ids);
    $lines = [];
    foreach ($stmt as $row) {
        $row['qty'] = $cart[$row['id']];
        $row['line_total'] = $row['price'] * $row['qty'];
        $lines[] = $row;
    }
    $_SESSION['cart'] = array_column($lines, 'qty', 'id');
    return $lines;
}

function cart_subtotal(array $lines): float
{
    return array_sum(array_column($lines, 'line_total'));
}
