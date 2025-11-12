<?php
// /includes/db.php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';          // XAMPP default is empty
$DB_NAME = 'sports_apparel';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
  http_response_code(500);
  die("DB connection failed: " . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');
