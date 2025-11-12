<?php
// make_hash.php
$password = 'AiMori123!';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo 'Password: ' . $password . '<br>';
echo 'Hash: ' . $hash . '<br>';
echo 'Length: ' . strlen($hash) . '<br>';
?>

<?php
// make_hash.php
$password = 'SoratoAnraku1!';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo 'Password: ' . $password . '<br>';
echo 'Hash: ' . $hash . '<br>';
echo 'Length: ' . strlen($hash) . '<br>';
?>