<?php
define('DIR', __DIR__);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Daey</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="stylesheet.css">
</head>
<body>

<?php include DIR . '/partials/header.php'; ?>

<!-- Contact Section -->
<section class="login-section">
    <div class="login-container">
        <h1 class="login-title">Contact Us</h1>
        <p class="login-description">Have a question or need help? We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
        
        <form class="login-form" action="#" method="POST">
            <div class="form-row">
                <div class="form-group form-group-half">
                    <label for="firstName" class="form-label">First Name</label>
                    <input type="text" id="firstName" name="firstName" class="form-input" placeholder="First Name" required>
                </div>
                <div class="form-group form-group-half">
                    <label for="lastName" class="form-label">Last Name</label>
                    <input type="text" id="lastName" name="lastName" class="form-input" placeholder="Last Name" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="Email" required>
            </div>
            
            <div class="form-group">
                <label for="subject" class="form-label">Subject</label>
                <input type="text" id="subject" name="subject" class="form-input" placeholder="Subject" required>
            </div>
            
            <div class="form-group">
                <label for="message" class="form-label">Message</label>
                <textarea id="message" name="message" class="form-input" placeholder="Your message" rows="6" required></textarea>
            </div>
            
            <button type="submit" class="btn btn-teal btn-login">Send Message</button>
        </form>
    </div>
</section>

<?php include DIR . '/partials/footer.php'; ?>

<?php include DIR . '/cart.php'; ?>

</body>
</html>

