<?php
// add_to_cart.php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// TODO: replace with real logged-in user ID when auth is done
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

header('Content-Type: application/json; charset=utf-8');

// Useful error reporting while developing
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$variantId = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
$qty       = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

if ($variantId <= 0 || $qty <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid variant or quantity']);
    exit;
}

try {
    $mysqli->begin_transaction();

    // 1) Find latest cart for this user
    $cartId = null;
    $stmt = $mysqli->prepare("SELECT cart_id FROM carts WHERE user_id = ? ORDER BY date_created DESC LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($cartId);
    $stmt->fetch();
    $stmt->close();

    // 2) If no cart yet, create one
    if (!$cartId) {
        $stmt = $mysqli->prepare("INSERT INTO carts (user_id, date_created) VALUES (?, NOW())");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $cartId = $stmt->insert_id;
        $stmt->close();
    }

    // 3) Check if this variant already exists in cart_items
    $cartItemId = null;
    $stmt = $mysqli->prepare("
        SELECT cart_item_id
        FROM cart_items
        WHERE cart_id = ? AND variant_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('ii', $cartId, $variantId);
    $stmt->execute();
    $stmt->bind_result($cartItemId);
    $stmt->fetch();
    $stmt->close();

    if ($cartItemId) {
        // 3a) Increase quantity
        $stmt = $mysqli->prepare("
            UPDATE cart_items
            SET quantity = quantity + ?
            WHERE cart_item_id = ?
        ");
        $stmt->bind_param('ii', $qty, $cartItemId);
        $stmt->execute();
        $stmt->close();
    } else {
        // 3b) Insert new row
        $stmt = $mysqli->prepare("
            INSERT INTO cart_items (cart_id, variant_id, quantity)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param('iii', $cartId, $variantId, $qty);
        $stmt->execute();
        $stmt->close();
    }

    // 4) Recompute total item count in this cart
    $totalItems = 0;
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE cart_id = ?");
    $stmt->bind_param('i', $cartId);
    $stmt->execute();
    $stmt->bind_result($totalItems);
    $stmt->fetch();
    $stmt->close();

    $mysqli->commit();

    echo json_encode([
        'ok'          => true,
        'cart_id'     => (int)$cartId,
        'total_items' => (int)$totalItems,
    ]);
} catch (Throwable $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'message' => 'DB error: ' . $e->getMessage(),
    ]);
}
