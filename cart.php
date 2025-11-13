<?php
// cart.php
session_start();
require __DIR__ . '/_db.php'; // adjust path if your DB file is elsewhere

// ------------------------------------------------------
//  Helpers: Cart ID & Cart data
// ------------------------------------------------------

function getCartIdOrNull(): ?int
{
    return isset($_SESSION['cart_id']) ? (int)$_SESSION['cart_id'] : null;
}

function createCartAndStoreInSession(mysqli $db): int
{
    $userId = $_SESSION['user_id'] ?? null;

    if ($userId === null) {
        $sql = "INSERT INTO carts (user_id, date_created) VALUES (NULL, NOW())";
        $stmt = $db->prepare($sql);
    } else {
        $sql = "INSERT INTO carts (user_id, date_created) VALUES (?, NOW())";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userId);
    }

    $stmt->execute();
    $cartId = $stmt->insert_id;
    $stmt->close();

    $_SESSION['cart_id'] = $cartId;
    return $cartId;
}

function getOrCreateCartId(mysqli $db): int
{
    $cartId = getCartIdOrNull();
    if ($cartId) {
        return $cartId;
    }
    return createCartAndStoreInSession($db);
}

/**
 * Get cart items & subtotal from DB.
 * Returns [array $items, float $subtotal]
 */
function getCartData(mysqli $db): array
{
    $cartId = getCartIdOrNull();
    if (!$cartId) {
        return [[], 0.0];
    }

    $sql = "
        SELECT 
            ci.cart_item_id,
            ci.variant_id,
            ci.quantity,
            v.size,
            v.color_hex,
            v.product_id,
            p.product_name,
            p.base_price,
            COALESCE(p.discount_flat, 0) AS discount_flat,
            COALESCE(pi.image_url, '') AS image_url
        FROM cart_items ci
        INNER JOIN variant v       ON ci.variant_id = v.variant_id
        INNER JOIN products p      ON v.product_id = p.product_id
        LEFT JOIN product_images pi 
            ON pi.variant_id = v.variant_id AND pi.is_primary = 1
        WHERE ci.cart_id = ?
        ORDER BY ci.date_added DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $cartId);
    $stmt->execute();
    $res = $stmt->get_result();

    $items = [];
    $subtotal = 0.0;

    while ($row = $res->fetch_assoc()) {
        $unitPrice = (float)$row['base_price'] - (float)$row['discount_flat'];
        if ($unitPrice < 0) $unitPrice = 0;
        $row['unit_price'] = $unitPrice;
        $row['line_total'] = $unitPrice * (int)$row['quantity'];
        $items[] = $row;
        $subtotal += $row['line_total'];
    }

    $stmt->close();
    return [$items, $subtotal];
}

/**
 * Build the HTML for the items list, used for initial render & AJAX responses.
 */
function buildCartItemsHtml(array $items): string
{
    if (empty($items)) {
        return '<p style="padding: 1rem 0; color:#777;">Your cart is empty.</p>';
    }

    ob_start();
    foreach ($items as $item):
        $variantId  = (int)$item['variant_id'];
        $name       = htmlspecialchars($item['product_name']);
        $size       = htmlspecialchars($item['size'] ?? '');
        $colorHex   = htmlspecialchars($item['color_hex'] ?? '');
        $qty        = (int)$item['quantity'];
        $price      = number_format($item['unit_price'], 2);
        $imgUrl     = $item['image_url'] ?: '/images/placeholder-product.png';
        ?>
        <div class="cart-item" data-variant-id="<?= $variantId ?>">
            <div class="cart-item-img">
                <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= $name ?>">
            </div>
            <div class="cart-item-main">
                <div class="cart-item-title"><?= $name ?></div>
                <div class="cart-item-meta">
                    <?= $size ?>
                    <?= ($size && $colorHex) ? ' | ' : '' ?>
                    <?= $colorHex ? 'Color' : '' ?>
                </div>
                <div class="cart-item-price">$<?= $price ?></div>
            </div>
            <div class="cart-item-side">
                <button class="cart-remove-btn" type="button" data-variant-id="<?= $variantId ?>"></button>
                <div class="cart-qty-control">
                    <button class="cart-qty-btn" type="button"
                            data-variant-id="<?= $variantId ?>" data-direction="dec">−</button>
                    <span class="cart-qty-value"><?= $qty ?></span>
                    <button class="cart-qty-btn" type="button"
                            data-variant-id="<?= $variantId ?>" data-direction="inc">+</button>
                </div>
            </div>
        </div>
        <?php
    endforeach;
    return ob_get_clean();
}

// ------------------------------------------------------
//  AJAX endpoint (add/update/remove/fetch)
// ------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action   = $_POST['action'];
    $variantId = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

    if (!in_array($action, ['add', 'update', 'remove', 'fetch'], true)) {
        echo json_encode(['ok' => false, 'message' => 'Invalid action']);
        exit;
    }

    try {
        if ($action === 'add') {
            if ($variantId <= 0) {
                throw new Exception('Missing variant ID.');
            }

            $cartId = getOrCreateCartId($mysqli);

            // Check if item exists
            $sql = "SELECT cart_item_id, quantity FROM cart_items WHERE cart_id = ? AND variant_id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('ii', $cartId, $variantId);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($row = $res->fetch_assoc()) {
                $newQty = (int)$row['quantity'] + $quantity;
                $update = $mysqli->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
                $update->bind_param('ii', $newQty, $row['cart_item_id']);
                $update->execute();
                $update->close();
            } else {
                $insert = $mysqli->prepare("
                    INSERT INTO cart_items (cart_id, variant_id, quantity, date_added)
                    VALUES (?, ?, ?, NOW())
                ");
                $insert->bind_param('iii', $cartId, $variantId, $quantity);
                $insert->execute();
                $insert->close();
            }
            $stmt->close();
        }

        if ($action === 'update') {
            if ($variantId <= 0) throw new Exception('Missing variant ID.');
            $cartId = getCartIdOrNull();
            if (!$cartId) throw new Exception('No active cart.');

            if ($quantity <= 0) {
                $del = $mysqli->prepare("DELETE FROM cart_items WHERE cart_id = ? AND variant_id = ?");
                $del->bind_param('ii', $cartId, $variantId);
                $del->execute();
                $del->close();
            } else {
                $upd = $mysqli->prepare("
                    UPDATE cart_items 
                    SET quantity = ? 
                    WHERE cart_id = ? AND variant_id = ?
                ");
                $upd->bind_param('iii', $quantity, $cartId, $variantId);
                $upd->execute();
                $upd->close();
            }
        }

        if ($action === 'remove') {
            if ($variantId <= 0) throw new Exception('Missing variant ID.');
            $cartId = getCartIdOrNull();
            if ($cartId) {
                $del = $mysqli->prepare("DELETE FROM cart_items WHERE cart_id = ? AND variant_id = ?");
                $del->bind_param('ii', $cartId, $variantId);
                $del->execute();
                $del->close();
            }
        }

        // For all actions we return updated cart data:
        [$items, $subtotal] = getCartData($mysqli);
        $html = buildCartItemsHtml($items);

        echo json_encode([
            'ok'       => true,
            'html'     => $html,
            'subtotal' => '$' . number_format($subtotal, 2),
        ]);
        exit;

    } catch (Throwable $e) {
        echo json_encode([
            'ok'      => false,
            'message' => $e->getMessage(),
        ]);
        exit;
    }
}

// ------------------------------------------------------
//  Drawer renderer (use in products.php)
// ------------------------------------------------------

function renderCartDrawer(mysqli $db): void
{
    [$items, $subtotal] = getCartData($db);
    $itemsHtml = buildCartItemsHtml($items);
    $subtotalFormatted = '$' . number_format($subtotal, 2);
    ?>

    <!-- Cart Overlay & Drawer -->
    <div id="cart-overlay" class="cart-overlay" onclick="closeCartDrawer()"></div>

    <aside id="cart-drawer" class="cart-drawer" aria-hidden="true">
        <header class="cart-header">
            <h2 class="cart-title">Your Cart</h2>
            <button class="cart-close-btn" type="button"
                    onclick="closeCartDrawer()" aria-label="Close cart">
                &times;
            </button>
        </header>

        <div id="cart-items" class="cart-items">
            <?= $itemsHtml ?>
        </div>

        <footer class="cart-footer">
            <div class="cart-subtotal-row">
                <span>Subtotal</span>
                <span id="cart-subtotal"><?= $subtotalFormatted ?></span>
            </div>
            <button class="cart-checkout-btn" type="button" onclick="handleCheckout()">
                Check Out
            </button>
        </footer>
    </aside>

    <!-- Cart Drawer Styles -->
    <style>
        .cart-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.35);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease;
            z-index: 998;
        }
        .cart-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .cart-drawer {
            position: fixed;
            top: 0;
            right: 0;
            height: 100vh;
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            box-shadow: -4px 0 25px rgba(0, 0, 0, 0.15);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            z-index: 999;
            display: flex;
            flex-direction: column;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .cart-drawer.open {
            transform: translateX(0);
        }
        .cart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid #f1f1f1;
        }
        .cart-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        .cart-close-btn {
            border: none;
            background: transparent;
            font-size: 1.8rem;
            cursor: pointer;
            line-height: 1;
        }
        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 0.75rem 1.5rem 1rem;
        }
        .cart-item {
            display: flex;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f3f3;
        }
        .cart-item-img {
            width: 80px;
            height: 80px;
            flex-shrink: 0;
            border-radius: 0.5rem;
            overflow: hidden;
            background: #f6f6f6;
        }
        .cart-item-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .cart-item-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .cart-item-title {
            font-size: 0.98rem;
            font-weight: 500;
            color: #222;
            margin-bottom: 0.25rem;
        }
        .cart-item-meta {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 0.35rem;
        }
        .cart-item-price {
            font-size: 0.95rem;
            font-weight: 500;
        }
        .cart-item-side {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
            gap: 0.4rem;
        }
        .cart-remove-btn {
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 1rem;
        }
        .cart-remove-btn::before {
            content: "🗑";
        }
        .cart-qty-control {
            display: inline-flex;
            align-items: center;
            gap:
