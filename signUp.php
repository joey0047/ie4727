<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sports_apparel";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$firstName = trim($_POST['firstName']);
$lastName  = trim($_POST['lastName']);
$email     = trim($_POST['email']);
$password  = trim($_POST['password']);

if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
    echo "<script>alert('Please fill in all fields.'); window.history.back();</script>";
    exit();
}

$check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();
if ($result->num_rows > 0) {
    echo "<script>alert('Email already registered! Please log in.'); window.location.href='logIn.html';</script>";
    exit();
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO users (first_name, last_name, email, password_hash, date_created)
    VALUES (?, ?, ?, ?, NOW())
");
$stmt->bind_param("ssss", $firstName, $lastName, $email, $passwordHash);

if ($stmt->execute()) {
    // AUTO LOGIN NEW USER
    $_SESSION['user_id'] = $conn->insert_id;
    $_SESSION['first_name'] = $firstName;

    echo "<script>alert('Account created successfully! Welcome to Daey.'); 
    window.location.href='homepage.html';</script>";
    exit();
} else {
    echo "<script>alert('Error creating account.'); window.history.back();</script>";
}

$stmt->close();
$conn->close();
?>
