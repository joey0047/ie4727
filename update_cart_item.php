<?php
// update_cart_item.php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli->set_charset('utf8mb4');

// TODO: replace with real logged-in user
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$cartItemId = isset($_POST['cart_item_id']) ? (int)$_POST['cart_item_id'] : 0;
$action     = isset($_POST['action']) ? $_POST['action'] : '';

if ($cartItemId <= 0 || !in_array($action, ['plus','minus','remove'], true)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid cart item or action']);
    exit;
}

try {
    $mysqli->begin_transaction();

    // 1) Ensure this item belongs to the current user
    $cartId     = null;
    $currentQty = 0;

    $sql = "
      SELECT ci.cart_id, ci.quantity
      FROM cart_items ci
      JOIN carts c ON c.cart_id = ci.cart_id
      WHERE ci.cart_item_id = ? AND c.user_id = ?
      LIMIT 1
    ";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ii', $cartItemId, $userId);
    $stmt->execute();
    $stmt->bind_result($cartId, $currentQty);
    if (!$stmt->fetch()) {
        $stmt->close();
        throw new Exception('Cart item not found.');
    }
    $stmt->close();

    $removed = false;
    $newQty  = $currentQty;

    if ($action === 'plus') {
        $newQty = $currentQty + 1;
    } elseif ($action === 'minus') {
        $newQty = $currentQty - 1;
    } elseif ($action === 'remove') {
        $newQty = 0;
    }

    if ($newQty <= 0) {
        // Delete the row
        $stmt = $mysqli->prepare("DELETE FROM cart_items WHERE cart_item_id = ?");
        $stmt->bind_param('i', $cartItemId);
        $stmt->execute();
        $stmt->close();
        $removed = true;
    } else {
        // Update quantity
        $stmt = $mysqli->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
        $stmt->bind_param('ii', $newQty, $cartItemId);
        $stmt->execute();
        $stmt->close();
    }

    // 2) Recalculate subtotal for this cart
    $subtotal = 0.0;
    $sql = "
      SELECT 
        ci.quantity,
        (p.base_price - p.discount_flat) AS unit_price
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
        $unit = max((float)$row['unit_price'], 0);
        $subtotal += $unit * (int)$row['quantity'];
    }
    $stmt->close();

    $mysqli->commit();

    echo json_encode([
        'ok'                 => true,
        'cart_item_id'       => $cartItemId,
        'removed'            => $removed,
        'quantity'           => $removed ? 0 : $newQty,
        'subtotal'           => $subtotal,
        'subtotal_formatted' => '$' . number_format($subtotal, 2),
    ]);
} catch (Throwable $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'message' => 'DB error: ' . $e->getMessage(),
    ]);
}
