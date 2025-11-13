<?php
session_start();
define('DIR', __DIR__);
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/config.php';

// --- Checkout data ---
$firstName = $_POST['firstName'] ?? '';
$lastName  = $_POST['lastName'] ?? '';
// Get email from POST or from user session (for display purposes)
$emailDisplay = $_POST['email'] ?? '';
if (empty($emailDisplay) && isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
    $stmt = $mysqli->prepare("SELECT email FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($emailDisplay);
    $stmt->fetch();
    $stmt->close();
}
if (empty($emailDisplay)) {
    $emailDisplay = "testuser@localhost";
}

// Always use testuser@localhost for Mercury mail server
$email = "testuser@localhost";
$address   = $_POST['address'] ?? '';
$country   = $_POST['country'] ?? '';
$postal    = $_POST['postalCode'] ?? '';
$phone     = $_POST['phone'] ?? '';
$discount  = $_POST['discountCode'] ?? '';

// --- Get actual cart totals from POST or calculate from cart ---
$subtotal = isset($_POST['subtotal']) ? (float)$_POST['subtotal'] : 0.00;
$shipping = isset($_POST['shipping']) ? (float)$_POST['shipping'] : 5.00;
$total = isset($_POST['total']) ? (float)$_POST['total'] : ($subtotal + $shipping);
$total_items = 0;

// --- Load cart items for display ---
$cartItems = [];
if (isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
    $cartId = null;
    
    $stmt = $mysqli->prepare("SELECT cart_id FROM carts WHERE user_id = ? ORDER BY date_created DESC LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($cartId);
    $stmt->fetch();
    $stmt->close();
    
    if ($cartId) {
        $sql = "
          SELECT 
            ci.cart_item_id,
            ci.quantity,
            v.variant_id,
            v.size,
            v.color_hex,
            v.stock_qty,
            p.product_id,
            p.product_name,
            p.category_id,
            p.base_price,
            p.discount_flat,
            COALESCE(
              (SELECT image_url FROM product_images pi 
                 WHERE pi.product_id = p.product_id 
                   AND (pi.variant_id = v.variant_id OR pi.variant_id IS NULL)
                   AND pi.is_primary = 1
                 ORDER BY pi.sort_order ASC, pi.image_id ASC LIMIT 1),
              (SELECT image_url FROM product_images pi2 
                 WHERE pi2.product_id = p.product_id
                 ORDER BY pi2.is_primary DESC, pi2.sort_order ASC, pi2.image_id ASC LIMIT 1)
            ) AS image_url
          FROM cart_items ci
          JOIN variants v ON v.variant_id = ci.variant_id
          JOIN products p ON p.product_id = v.product_id
          WHERE ci.cart_id = ?
          ORDER BY ci.cart_item_id DESC
        ";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $cartId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $priceOrig = (float)$row['base_price'];
            $disc      = (float)$row['discount_flat'];
            $unitPrice = max($priceOrig - $disc, 0);
            $row['unit_price'] = $unitPrice;
            $row['line_total'] = $unitPrice * (int)$row['quantity'];
            $total_items += (int)$row['quantity'];
            $cartItems[] = $row;
        }
        $stmt->close();
        
        // Recalculate if not provided in POST
        if ($subtotal == 0 && !empty($cartItems)) {
            $subtotal = 0;
            foreach ($cartItems as $item) {
                $subtotal += $item['line_total'];
            }
            $total = $subtotal + $shipping;
        }
    }
}

// --- Prepare order receipt email message ---
$subject = "Your Daey Order Receipt";
$message = "
Hello $firstName,

Thank you for your order! Here is your receipt.

----------------------------
ORDER SUMMARY
----------------------------
Items: " . $total_items . " item(s)
Subtotal: $" . number_format($subtotal, 2) . "
Shipping: $" . number_format($shipping, 2) . "
TOTAL:    $" . number_format($total, 2) . "

----------------------------
DELIVERY DETAILS
----------------------------
Name: $firstName $lastName
Address: $address
Country: $country
Postal Code: $postal
Phone: $phone

We appreciate your purchase!
Daey Climbing Apparel
";

// --- Email headers ---
$from = "no-reply@localhost";   // your return email (local)
$headers =
    "From: $from\r\n" .
    "Reply-To: $from\r\n" .
    "X-Mailer: PHP/" . phpversion();

// --- Send email ---
mail($email, $subject, $message, $headers, "-f$from");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Receipt - Daey</title>
    <link rel="stylesheet" href="stylesheet.css">
</head>
<body>

<?php include DIR . '/partials/header.php'; ?>

<section class="receipt-section">
    <div class="receipt-container">

        <h1 class="receipt-title">Order Confirmation</h1>
        <p class="receipt-text">Thank you for your order! A confirmation email has been sent to:</p>
        <p class="receipt-email"><strong><?php echo htmlspecialchars($emailDisplay); ?></strong></p>

        <hr>

        <h2 class="receipt-subtitle">Order Summary</h2>

        <?php if (!empty($cartItems)): ?>
            <div class="receipt-items-list">
                <?php foreach ($cartItems as $item): ?>
                    <div class="receipt-item">
                        <div class="receipt-item-image">
                            <img src="<?= htmlspecialchars($item['image_url'] ?: (BASE_URL . '/assets/images/tempimage.png')) ?>" 
                                 alt="<?= htmlspecialchars($item['product_name']) ?>">
                        </div>
                        <div class="receipt-item-details">
                            <div class="receipt-item-name">
                                <?= htmlspecialchars($item['product_name']) ?>
                                <?php 
                                  // Check if chalk bag (category_id = 5) and out of stock
                                  $itemCategoryId = isset($item['category_id']) ? (int)$item['category_id'] : 0;
                                  $itemStockQty = isset($item['stock_qty']) ? (int)$item['stock_qty'] : 0;
                                  if ($itemCategoryId === 5 && $itemStockQty <= 0): 
                                ?>
                                  <span class="receipt-sold-out">(Sold Out)</span>
                                <?php endif; ?>
                            </div>
                            <div class="receipt-item-specs">
                                <?php if (!empty($item['size'])): ?>
                                    Size <?= htmlspecialchars(strtoupper($item['size'])) ?> | 
                                <?php endif; ?>
                                <?php if (!empty($item['color_hex'])): ?>
                                    Color <?= htmlspecialchars(strtoupper($item['color_hex'])) ?>
                                <?php endif; ?>
                            </div>
                            <div class="receipt-item-price">
                                Qty: <?= (int)$item['quantity'] ?> × $<?= number_format($item['unit_price'], 2) ?> = 
                                $<?= number_format($item['line_total'], 2) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="receipt-summary-box">
            <p><strong>Items:</strong> <?php echo $total_items; ?> item(s)</p>
            <p><strong>Subtotal:</strong> $<?php echo number_format($subtotal, 2); ?></p>
            <p><strong>Shipping:</strong> $<?php echo number_format($shipping, 2); ?></p>
            <p class="receipt-total">
                <strong>Total:</strong> $<?php echo number_format($total, 2); ?>
            </p>
        </div>

        <hr>

        <h2 class="receipt-subtitle">Delivery Details</h2>
        <div class="receipt-details-box">
            <p><strong>Name:</strong> <?php echo htmlspecialchars("$firstName $lastName"); ?></p>
            <p><strong>Address:</strong> <?php echo htmlspecialchars($address); ?></p>
            <p><strong>Country:</strong> <?php echo htmlspecialchars($country); ?></p>
            <p><strong>Postal Code:</strong> <?php echo htmlspecialchars($postal); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($phone); ?></p>
        </div>

        <hr>

        <a href="homepage.html" class="btn btn-brown" style="margin-top:20px;">Return to Home</a>

    </div>
</section>

<?php include DIR . '/partials/footer.php'; ?>

<?php include DIR . '/cart.php'; ?>

</body>
</html>
