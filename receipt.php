<?php
session_start();
define('DIR', __DIR__);

// --- Default cart totals (temporary placeholder) ---
$subtotal = 0.00;
$shipping = 5.00; 
$total = $subtotal + $shipping;

// --- Checkout data ---
$firstName = $_POST['firstName'] ?? '';
$lastName  = $_POST['lastName'] ?? '';
$email     = $_POST['email'] ?? '';
$address   = $_POST['address'] ?? '';
$country   = $_POST['country'] ?? '';
$postal    = $_POST['postalCode'] ?? '';
$phone     = $_POST['phone'] ?? '';
$discount  = $_POST['discountCode'] ?? '';

// --- Prepare order receipt email message ---
$subject = "Your Daey Order Receipt";
$message = "
Hello $firstName,

Thank you for your order! Here is your receipt.

----------------------------
ORDER SUMMARY
----------------------------
Items: 0 items
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
        <p class="receipt-email"><strong><?php echo htmlspecialchars($email); ?></strong></p>

        <hr>

        <h2 class="receipt-subtitle">Order Summary</h2>

        <div class="receipt-summary-box">
            <p><strong>Items:</strong> 0 items</p>
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

</body>
</html>
