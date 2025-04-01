<?php
// config.php
$db_host = 'localhost';
$db_user = 'cannabis_menu';
$db_pass = 'your_password';
$db_name = 'cannabis_menu';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>