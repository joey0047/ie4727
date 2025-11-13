<?php
session_start();

// Define DIR constant for includes
define('DIR', __DIR__);

// Prevent access if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: logInPage.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Connect to DB
$conn = new mysqli("localhost", "root", "", "sports_apparel");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$stmt = $conn->prepare("
    SELECT first_name, last_name, email, delivery_address 
    FROM users 
    WHERE user_id = ?
");

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Check if user data was found
if (!$user) {
    die("User not found. Please log in again.");
}

$firstName = $user['first_name']; // only showing first name at top

// Close database connection
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Daey</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="stylesheet.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- Header -->
    <?php include DIR . '/partials/header.php'; ?>

    <!-- Profile Section -->
    <section class="profile-section">
        <div class="profile-header">
            <div class="profile-avatar"></div>
            <div class="profile-info">
                <h2 class="profile-name">
                    <?php echo htmlspecialchars($firstName); ?>
                </h2>
                <p class="profile-joined">Joined On: 11/11/2025</p>
            </div>
            <div class="profile-header-right">
                <a href="logout.php" class="sign-out-btn">Sign Out</a>
            </div>
        </div>

        <!-- Profile Details -->
        <div class="profile-details-container">
            <form action="updateProfile.php" method="POST">
                <div class="profile-details-grid">
                    
                    <!-- USER DETAILS (READ ONLY) -->
                    <div class="profile-details-column">
                        <h3>User Details</h3>
                        
                        <div class="profile-form-group">
                            <label class="profile-form-label">First Name</label>
                            <input type="text" class="profile-form-input"
                                   value="<?php echo htmlspecialchars($user['first_name']); ?>"
                                   readonly>
                        </div>

                        <div class="profile-form-group">
                            <label class="profile-form-label">Last Name</label>
                            <input type="text" class="profile-form-input"
                                   value="<?php echo htmlspecialchars($user['last_name']); ?>"
                                   readonly>
                        </div>

                        <!-- Submit button -->
                        <button type="submit" class="profile-submit-btn">Update Changes</button>
                    </div>

                    <!-- ACCOUNT DETAILS -->
                    <div class="profile-details-column">
                        <h3>Account Details</h3>

                        <div class="profile-form-group">
                            <label class="profile-form-label">Email</label>
                            <input type="email" class="profile-form-input"
                                   value="<?php echo htmlspecialchars($user['email']); ?>"
                                   readonly>
                        </div>

                        <div class="profile-form-group">
                            <label for="password" class="profile-form-label">New Password</label>
                            <input type="password" id="password" name="password" class="profile-form-input" placeholder="Enter new password">
                        </div>
                    </div>

                    <!-- DELIVERY ADDRESS (EDITABLE) -->
                    <div class="profile-details-column">
                        <h3>Delivery Address</h3>

                        <div class="profile-form-group">
                            <label for="address" class="profile-form-label">Address</label>
                            <input type="text" id="address" name="address" class="profile-form-input"
       value="<?php echo htmlspecialchars($user['delivery_address']); ?>"
       placeholder="Enter delivery address">

                        </div>
                    </div>

                </div>
            </form>
        </div>

        <!-- Orders Section -->
        <div class="orders-section">
            <h2 class="orders-title">My Orders</h2>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Dates</th>
                        <th>Total Price</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="4" class="empty-orders">No orders yet</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <?php include DIR . '/partials/footer.php'; ?>

<?php include DIR . '/cart.php'; ?>

</body>
</html>
