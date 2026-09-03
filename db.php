<?php
$host = 'localhost';
$dbname = 'Smart_Cafeteria_Management';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Log the real error for developers, never show it to the browser
    error_log("DB Connection Error: " . $e->getMessage());
    die("Sorry, we're having trouble connecting right now. Please try again later.");
}
?>