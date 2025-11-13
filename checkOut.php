<?php
define('DIR', __DIR__);
session_start();

/* -----------------------------------------------------------
   LOAD USER (IF LOGGED IN)
----------------------------------------------------------- */
$user = null;

if (isset($_SESSION['user_id'])) {
    $conn = new mysqli("localhost", "root", "", "sports_apparel");
    $uid = $_SESSION['user_id'];

    $result = $conn->query("
        SELECT first_name, last_name, email, delivery_address
        FROM users 
        WHERE user_id = $uid
    ");

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
    }
}

/* -----------------------------------------------------------
   LOAD CART FROM DATABASE
----------------------------------------------------------- */
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/config.php';

$cartItems = [];
$subtotal = 0.0;
$total_items = 0;

if (isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
    
    // Find latest cart for this user
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
            $subtotal += $row['line_total'];
            $total_items += (int)$row['quantity'];
            $cartItems[] = $row;
        }
        $stmt->close();
    }
}

$shipping = 5.00;
$total = $subtotal + $shipping;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Daey</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="stylesheet.css">
</head>
<body>

<!-- Header -->
<?php include DIR . '/partials/header.php'; ?>

<!-- Checkout Section -->
<section class="checkout-section">
    <div class="checkout-container">

        <!-- LEFT COLUMN -->
        <div class="checkout-form-column">
            <form class="checkout-form" action="receipt.php" method="POST">

                <!-- CONTACT SECTION (ONLY FOR GUESTS) -->
                <?php if (!$user): ?>
                <div class="checkout-section-block">
                    <div class="checkout-section-header">
                        <h2 class="checkout-section-title">Contact</h2>
                        <a href="logInPage.php" class="checkout-sign-in-link">Sign In</a>
                    </div>

                    <div class="form-group">
                        <input type="email" id="email" name="email" class="form-input"
                               placeholder="Email" required>
                    </div>
                </div>
                <?php else: ?>
                    <!-- Logged-in user's email -->
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                <?php endif; ?>


                <!-- DELIVERY SECTION -->
                <div class="checkout-section-block">

                    <?php if ($user): ?>
                        <p class="checkout-account-text" style="margin-bottom: 15px;">
                            Logged in as: <strong><?php echo htmlspecialchars($user['email']); ?></strong>
                        </p>
                    <?php endif; ?>

                    <h2 class="checkout-section-title">Delivery</h2>

                    <div class="form-group">
                        <input type="text" id="country" name="country" class="form-input"
                               placeholder="Country/Region" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <input type="text" id="firstName" name="firstName" class="form-input"
                                   placeholder="First Name" required
                                   value="<?php echo $user ? htmlspecialchars($user['first_name']) : ''; ?>">
                        </div>

                        <div class="form-group form-group-half">
                            <input type="text" id="lastName" name="lastName" class="form-input"
                                   placeholder="Last Name" required
                                   value="<?php echo $user ? htmlspecialchars($user['last_name']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <input type="text" id="address" name="address" class="form-input"
                               placeholder="Address" required
                               value="<?php echo $user ? htmlspecialchars($user['delivery_address']) : ''; ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <input type="text" id="apartment" name="apartment" class="form-input"
                                   placeholder="Apartment, suite, etc. (optional)">
                        </div>
                        <div class="form-group form-group-half">
                            <input type="text" id="postalCode" name="postalCode" class="form-input"
                                   placeholder="Postal Code" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <input type="tel" id="phone" name="phone" class="form-input"
                               placeholder="Phone" required>
                    </div>
                </div>


                <!-- SHIPPING -->
                <div class="checkout-section-block">
                    <h2 class="checkout-section-title">Shipping method</h2>

                    <div class="shipping-option">
                        <label class="shipping-option-label">
                            <input type="radio" name="shippingMethod" value="standard" checked class="shipping-radio">
                            <span class="shipping-option-text">Standard</span>
                            <span class="shipping-option-price">$5.00</span>
                        </label>
                    </div>
                </div>


                <!-- PAYMENT -->
                <div class="checkout-section-block checkout-payment-section">
                    <h2 class="checkout-section-title">Payment</h2>

                    <div class="form-group">
                        <input type="text" id="cardNumber" name="cardNumber" class="form-input"
                               placeholder="Card number" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-half">
                            <input type="text" id="expiry" name="expiry" class="form-input"
                                   placeholder="Expiration date (MM/YY)" required>
                        </div>
                        <div class="form-group form-group-half">
                            <input type="text" id="securityCode" name="securityCode" class="form-input"
                                   placeholder="Security code" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <input type="text" id="cardName" name="cardName" class="form-input"
                               placeholder="Name on card" required>
                    </div>
                </div>


                <!-- PASS TOTALS TO RECEIPT -->
                <input type="hidden" name="subtotal" value="<?php echo $subtotal; ?>">
                <input type="hidden" name="shipping" value="<?php echo $shipping; ?>">
                <input type="hidden" name="total" value="<?php echo $total; ?>">

                <!-- PAY BUTTON -->
                <button type="submit" class="btn btn-brown btn-pay-now">Pay Now</button>

            </form>
        </div>


        <!-- RIGHT COLUMN (ORDER SUMMARY) -->
        <div class="checkout-summary-column">
            <div class="order-summary">
                <h2 class="order-summary-title">Order Summary</h2>

                <div class="order-items" id="orderItems">
    <?php if (empty($cartItems)): ?>
        <p>No items in cart.</p>
    <?php else: ?>
        <?php foreach ($cartItems as $item): ?>
            <div class="order-item">
                <div class="order-item-image">
                    <img src="<?= htmlspecialchars($item['image_url'] ?: (BASE_URL . '/assets/images/tempimage.png')) ?>" 
                         alt="<?= htmlspecialchars($item['product_name']) ?>">
                </div>
                <div class="order-item-details">
                    <div class="order-item-name">
                        <?= htmlspecialchars($item['product_name']) ?>
                        <?php 
                          // Check if chalk bag (category_id = 5) and out of stock
                          $itemCategoryId = isset($item['category_id']) ? (int)$item['category_id'] : 0;
                          $itemStockQty = isset($item['stock_qty']) ? (int)$item['stock_qty'] : 0;
                          if ($itemCategoryId === 5 && $itemStockQty <= 0): 
                        ?>
                          <span class="order-item-sold-out">(Sold Out)</span>
                        <?php endif; ?>
                    </div>
                    <div class="order-item-specs">
                        <?php if (!empty($item['size'])): ?>
                            Size <?= htmlspecialchars(strtoupper($item['size'])) ?> | 
                        <?php endif; ?>
                        <?php if (!empty($item['color_hex'])): ?>
                            Color <?= htmlspecialchars(strtoupper($item['color_hex'])) ?>
                        <?php endif; ?>
                    </div>
                    <div class="order-item-price">
                        Qty: <?= (int)$item['quantity'] ?> × $<?= number_format($item['unit_price'], 2) ?> = 
                        $<?= number_format($item['line_total'], 2) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>


                <div class="cost-summary">
                    <div class="cost-row">
                        <span class="cost-label">Subtotal (<?php echo $total_items; ?> items)</span>
                        <span>$<?php echo number_format($subtotal, 2); ?></span>
                    </div>

                    <div class="cost-row">
                        <span class="cost-label">Shipping</span>
                        <span>$<?php echo number_format($shipping, 2); ?></span>
                    </div>

                    <div class="cost-row cost-row-total">
                        <span class="cost-label">Total</span>
                        <span>$<?php echo number_format($total, 2); ?></span>
                    </div>
                </div>

            </div>
        </div>

    </div>
</section>

<?php include DIR . '/partials/footer.php'; ?>

<?php include DIR . '/cart.php'; ?>

</body>
</html>
