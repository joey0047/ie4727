<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');

// Require user to be logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please log in to add items to cart']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid method']);
    exit;
}

$variantId = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

if ($variantId <= 0 || $quantity <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid variant or quantity']);
    exit;
}

// Verify variant exists and belongs to a valid product
$variantCheck = null;
$stmt = $mysqli->prepare("SELECT variant_id, product_id, stock_qty FROM variants WHERE variant_id = ? LIMIT 1");
$stmt->bind_param('i', $variantId);
$stmt->execute();
$res = $stmt->get_result();
$variantCheck = $res->fetch_assoc();
$stmt->close();

if (!$variantCheck) {
    echo json_encode(['ok' => false, 'message' => 'Invalid variant. Product may not have variants configured.']);
    exit;
}

// Check stock availability
if ($variantCheck['stock_qty'] < $quantity) {
    echo json_encode(['ok' => false, 'message' => 'Insufficient stock. Only ' . $variantCheck['stock_qty'] . ' items available.']);
    exit;
}

try {
    $mysqli->begin_transaction();

    // Get or create cart
    $cartId = null;
    $stmt = $mysqli->prepare("SELECT cart_id FROM carts WHERE user_id = ? ORDER BY date_created DESC LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($cartId);
    $stmt->fetch();
    $stmt->close();

    if (!$cartId) {
        $stmt = $mysqli->prepare("INSERT INTO carts (user_id, date_created) VALUES (?, NOW())");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $cartId = $stmt->insert_id;
        $stmt->close();
    }

    // Check if item already exists in cart
    $cartItemId = null;
    $stmt = $mysqli->prepare("SELECT cart_item_id, quantity FROM cart_items WHERE cart_id = ? AND variant_id = ? LIMIT 1");
    $stmt->bind_param('ii', $cartId, $variantId);
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = $res->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // Update quantity (check total doesn't exceed stock)
        $newQty = $existing['quantity'] + $quantity;
        if ($newQty > $variantCheck['stock_qty']) {
            $mysqli->rollback();
            echo json_encode(['ok' => false, 'message' => 'Cannot add more items. Only ' . $variantCheck['stock_qty'] . ' items available in stock.']);
            exit;
        }
        $stmt = $mysqli->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
        $stmt->bind_param('ii', $newQty, $existing['cart_item_id']);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insert new cart item
        $stmt = $mysqli->prepare("INSERT INTO cart_items (cart_id, variant_id, quantity) VALUES (?, ?, ?)");
        $stmt->bind_param('iii', $cartId, $variantId, $quantity);
        $stmt->execute();
        $stmt->close();
    }

    // Get total items in cart
    $totalItems = 0;
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = ?");
    $stmt->bind_param('i', $cartId);
    $stmt->execute();
    $stmt->bind_result($totalItems);
    $stmt->fetch();
    $stmt->close();

    $mysqli->commit();

    echo json_encode([
        'ok' => true,
        'message' => 'Item added to cart',
        'cart_id' => $cartId,
        'total_items' => $totalItems
    ]);
} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(['ok' => false, 'message' => 'Error adding to cart: ' . $e->getMessage()]);
}

