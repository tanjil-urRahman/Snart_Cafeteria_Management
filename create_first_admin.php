<?php

require 'db.php';

$name = "System Admin";
$email = "admin@gmail.com";
$phone = "01712345678";
$password = "admin123";

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {

    $stmt = $pdo->prepare(
        "INSERT INTO `USER`
        (Name, Email, Phone, Password, Role)
        VALUES (?, ?, ?, ?, 'Admin')"
    );

    $stmt->execute([
        $name,
        $email,
        $phone,
        $hashedPassword
    ]);

    echo "Admin account created successfully.";

} catch (PDOException $e) {

    echo "Error: " . $e->getMessage();

}

?>