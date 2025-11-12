<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: logIn.html");
    exit();
}

$conn = new mysqli("localhost", "root", "", "sports_apparel");

$userId = $_SESSION['user_id'];
$newPassword = trim($_POST['password']);
$newAddress  = trim($_POST['address']);

// Check if both fields are empty
if (empty($newPassword) && empty($newAddress)) {
    echo "<script>alert('Nothing to update'); window.location.href='profileMain.php';</script>";
    exit();
}

// Update address
if (!empty($newAddress)) {
    $stmt = $conn->prepare("UPDATE users SET delivery_address=? WHERE user_id=?");
    $stmt->bind_param("si", $newAddress, $userId);
    $stmt->execute();
}

// Update password
if (!empty($newPassword)) {
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password_hash=? WHERE user_id=?");
    $stmt->bind_param("si", $hash, $userId);
    $stmt->execute();
}

echo "<script>alert('Profile updated successfully!'); window.location.href='profileMain.php';</script>";
?>
