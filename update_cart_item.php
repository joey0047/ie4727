<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Require user to be logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please log in']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid method']);
    exit;
}

$cartItemId = isset($_POST['cart_item_id']) ? (int)$_POST['cart_item_id'] : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if ($cartItemId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid cart item ID']);
    exit;
}

if (!in_array($action, ['plus', 'minus', 'remove'])) {
    echo json_encode(['ok' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    $mysqli->begin_transaction();

    // 1) Ensure this item belongs to the current user
    $cartId = null;
    $currentQty = 0;
    $variantId = null;
    $stockQty = 0;

    $sql = "
        SELECT ci.cart_id, ci.quantity, ci.variant_id, v.stock_qty
        FROM cart_items ci
        JOIN carts c ON c.cart_id = ci.cart_id
        JOIN variants v ON v.variant_id = ci.variant_id
        WHERE ci.cart_item_id = ? AND c.user_id = ?
        LIMIT 1
    ";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ii', $cartItemId, $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $item = $res->fetch_assoc();
    $stmt->close();

    if (!$item) {
        $mysqli->rollback();
        echo json_encode(['ok' => false, 'message' => 'Cart item not found or does not belong to you']);
        exit;
    }

    $cartId = $item['cart_id'];
    $currentQty = (int)$item['quantity'];
    $variantId = (int)$item['variant_id'];
    $stockQty = (int)$item['stock_qty'];

    // 2) Handle action
    if ($action === 'remove') {
        // Delete the item
        $stmt = $mysqli->prepare("DELETE FROM cart_items WHERE cart_item_id = ?");
        $stmt->bind_param('i', $cartItemId);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'plus') {
        // Increase quantity (check stock)
        if ($currentQty >= $stockQty) {
            $mysqli->rollback();
            echo json_encode(['ok' => false, 'message' => 'Cannot add more. Only ' . $stockQty . ' items available in stock.']);
            exit;
        }
        $newQty = $currentQty + 1;
        $stmt = $mysqli->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
        $stmt->bind_param('ii', $newQty, $cartItemId);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'minus') {
        // Decrease quantity (remove if reaches 0)
        if ($currentQty <= 1) {
            // Remove item if quantity would be 0
            $stmt = $mysqli->prepare("DELETE FROM cart_items WHERE cart_item_id = ?");
            $stmt->bind_param('i', $cartItemId);
            $stmt->execute();
            $stmt->close();
        } else {
            $newQty = $currentQty - 1;
            $stmt = $mysqli->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
            $stmt->bind_param('ii', $newQty, $cartItemId);
            $stmt->execute();
            $stmt->close();
        }
    }

    // 3) Get updated totals
    $totalItems = 0;
    $subtotal = 0.0;
    
    $sql = "
        SELECT 
            ci.quantity,
            p.base_price,
            p.discount_flat
        FROM cart_items ci
        JOIN variants v ON v.variant_id = ci.variant_id
        JOIN products p ON p.product_id = v.product_id
        WHERE ci.cart_id = ?
    ";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $cartId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $price = max((float)$row['base_price'] - (float)$row['discount_flat'], 0);
        $qty = (int)$row['quantity'];
        $subtotal += $price * $qty;
        $totalItems += $qty;
    }
    $stmt->close();

    $mysqli->commit();

    echo json_encode([
        'ok' => true,
        'total_items' => $totalItems,
        'subtotal' => number_format($subtotal, 2)
    ]);
} catch (Throwable $e) {
    $mysqli->rollback();
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
