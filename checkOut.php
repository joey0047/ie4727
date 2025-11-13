<?php
define('DIR', __DIR__);
session_start();

// If logged in, fetch user details for autofill
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Daey</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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


                <!-- CONTACT SECTION (ONLY FOR GUEST USERS) -->
                <?php if (!$user): ?>
                <div class="checkout-section-block">
                    <div class="checkout-section-header">
                        <h2 class="checkout-section-title">Contact</h2>
                        <a href="logInPage.php" class="checkout-sign-in-link">Sign In</a>
                    </div>

                    <div class="form-group">
                        <input type="email" id="email" name="email" class="form-input" placeholder="Email" required>
                    </div>

                    <p class="checkout-account-text">Account: Guest checkout</p>
                </div>
                <?php else: ?>
                    <!-- Logged-in user email (hidden input) -->
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


                <!-- SHIPPING METHOD -->
                <div class="checkout-section-block">
                    <h2 class="checkout-section-title">Shipping method</h2>

                    <div class="shipping-option">
                        <label class="shipping-option-label">
                            <input type="radio" name="shipping" value="standard" checked class="shipping-radio">
                            <span class="shipping-option-text">Standard</span>
                            <span class="shipping-option-price">$5</span>
                        </label>
                    </div>
                </div>


                <!-- PAYMENT SECTION -->
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


                <!-- PAY BUTTON -->
                <button type="submit" class="btn btn-brown btn-pay-now">Pay Now</button>

            </form>
        </div>


        <!-- RIGHT COLUMN -->
        <div class="checkout-summary-column">
            <div class="order-summary">
                <h2 class="order-summary-title">Order Summary</h2>
                
                <div class="order-items" id="orderItems">
                    <!-- Items added dynamically later -->
                </div>

                <div class="discount-section">
                    <div class="discount-input-wrapper">
                        <input type="text" id="discountCode" name="discountCode" class="discount-input" placeholder="Enter Discount Code">
                        <button type="button" class="btn btn-brown btn-apply">Apply</button>
                    </div>
                </div>

                <div class="cost-summary">
                    <div class="cost-row">
                        <span class="cost-label">Subtotal - x items</span>
                    </div>
                    <div class="cost-row">
                        <span class="cost-label">Shipping</span>
                    </div>
                    <div class="cost-row cost-row-total">
                        <span class="cost-label">Total</span>
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
