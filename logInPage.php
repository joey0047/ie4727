<?php
// Define DIR constant for includes
define('DIR', __DIR__);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In - Daey</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="stylesheet.css">
</head>
<body>
    <!-- Header -->
    <?php include DIR . '/partials/header.php'; ?>

    <!-- Login Section -->
    <section class="login-section">
        <div class="login-container">
            <h1 class="login-title">Log In</h1>
            <p class="login-description">Welcome back to Daey. Please enter your details.</p>
            
            <form class="login-form" action="login.php" method="POST">
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="Email" required>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Password" required>
                    <a href="#" class="forgot-password">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn btn-teal btn-login">Log In</button>
            </form>
            
            <p class="create-account-text">
                Don't have an account? <a href="signUp.html" class="create-account-link">Create an account</a>
            </p>
        </div>
    </section>

    <?php include DIR . '/partials/footer.php'; ?>

<?php include DIR . '/cart.php'; ?>

</body>
</html>

