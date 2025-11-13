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
   LOAD CART
   Cart example format:
   $_SESSION['cart'] = [
       ['name'=>'Shirt A','price'=>29.90,'qty'=>2],
       ['name'=>'Shorts B','price'=>45.50,'qty'=>1]
   ];
----------------------------------------------------------- */
// Load dummy cart from session (until real cart system is ready)
$cart = $_SESSION['cart'] ?? [];

$subtotal = 0;
$total_items = 0;

foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['qty'];
    $total_items += $item['qty'];
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

                <div class="order-items">
    <?php if (empty($cart)): ?>
        <p>No items in cart.</p>
    <?php else: ?>
        <?php foreach ($cart as $item): ?>
            <div class="order-item-row">
                <p><strong><?php echo htmlspecialchars($item['name']); ?></strong></p>
                <p>Qty: <?php echo $item['qty']; ?></p>
                <p>$<?php echo number_format($item['price'] * $item['qty'], 2); ?></p>
            </div>
            <hr>
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

<!-- FOOTER -->
<footer class="footer">
    <div class="footer-top">
        <div class="footer-left">
            <h2 class="footer-logo">Daey</h2>
            <p class="footer-tagline">Climbing Apparel Store</p>
        </div>
        <nav class="footer-nav">
            <a href="homepage.html" class="footer-link">Home</a>
            <a href="productlist.html" class="footer-link">Shop</a>
            <a href="aboutus.php" class="footer-link">About Us</a>
            <a href="#" class="footer-link">Contact Us</a>
        </nav>
    </div>
</footer>

</body>
</html>
