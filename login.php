<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";        // change if your MySQL uses another username
$password = "";            // change if your MySQL has a password
$dbname = "sports_apparel"; // ✅ your database name

// Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form input safely
$email = $_POST['email'];
$password = $_POST['password'];

// Prepare statement to avoid SQL injection
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // Verify password hash
    if (password_verify($password, $user['password_hash'])) {
        // Create session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['first_name'] = $user['first_name'];

        // Redirect to homepage (or dashboard)
        header("Location: homepage.html");
        exit();
    } else {
        echo "<script>alert('❌ Incorrect password.'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('❌ No account found with that email.'); window.history.back();</script>";
}

// Close connections
$stmt->close();
$conn->close();
?>
