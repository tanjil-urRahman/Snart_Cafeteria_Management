<?php

session_start();
require 'db.php';


// CSRF token (generate once per session)

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $batch = trim($_POST['batch'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';


    // VALIDATION


    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {

        $error = "Your session has expired. Please try again.";

    } elseif (
        empty($name) ||
        empty($email) ||
        empty($phone) ||
        empty($department) ||
        empty($batch) ||
        empty($password) ||
        empty($confirmPassword)
    ) {

        $error = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif ($password !== $confirmPassword) {

        $error = "Passwords do not match.";

    } elseif (strlen($password) < 6) {

        $error = "Password must contain at least 6 characters.";

    } else {

        try {

            // Check whether email already exists
            $stmt = $pdo->prepare(
                "SELECT UserID FROM `USER` WHERE Email = ?"
            );

            $stmt->execute([$email]);

            if ($stmt->fetch()) {

                $error = "An account with this email already exists.";

            } else {

            
                // START DATABASE TRANSACTION
                

                $pdo->beginTransaction();

                // Hash password
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                
                // CREATE USER
                

                $stmt = $pdo->prepare(
                    "INSERT INTO `USER`
                    (Name, Email, Phone, Password, Role)
                    VALUES (?, ?, ?, ?, 'Student')"
                );

                $stmt->execute([
                    $name,
                    $email,
                    $phone,
                    $hashedPassword
                ]);

                // Get newly created UserID
                $userID = $pdo->lastInsertId();

                
                // CREATE STUDENT
                

                $stmt = $pdo->prepare(
                    "INSERT INTO STUDENT
                    (UserID, Department, Batch, WalletBalance)
                    VALUES (?, ?, ?, 0.00)"
                );

                $stmt->execute([
                    $userID,
                    $department,
                    $batch
                ]);

                // Finish transaction
                $pdo->commit();

                $success =
                    "Registration successful! You can now log in.";

            }

        } catch (PDOException $e) {

            // If something goes wrong, undo everything
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Handle a duplicate-email race condition gracefully
            if ($e->getCode() === '23000') {
                $error = "An account with this email already exists.";
            } else {
                error_log("register.php DB error: " . $e->getMessage());
                $error = "Registration failed. Please try again later.";
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Smart Cafeteria - Register</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;

            display: flex;
            justify-content: center;
            align-items: center;

            min-height: 100vh;
        }

        .register-card {
            background: white;

            width: 420px;
            max-width: 90%;

            padding: 30px;

            border-radius: 10px;

            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        h2 {
            text-align: center;
            margin-bottom: 5px;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
        }

        label {
            display: block;

            margin-top: 14px;
            margin-bottom: 5px;

            font-weight: bold;
        }

        input,
        select {

            width: 100%;

            padding: 11px;

            border: 1px solid #ccc;

            border-radius: 5px;

            font-size: 14px;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #007bff;
        }

        button {

            width: 100%;

            margin-top: 22px;

            padding: 12px;

            background: #28a745;

            color: white;

            border: none;

            border-radius: 5px;

            font-size: 16px;

            cursor: pointer;
        }

        button:hover {
            background: #218838;
        }

        .error {

            background: #f8d7da;

            color: #721c24;

            padding: 10px;

            border-radius: 5px;

            text-align: center;
        }

        .success {

            background: #d4edda;

            color: #155724;

            padding: 10px;

            border-radius: 5px;

            text-align: center;
        }

        .links {

            text-align: center;

            margin-top: 20px;
        }

        .links a {

            color: #007bff;

            text-decoration: none;
        }

        .links a:hover {
            text-decoration: underline;
        }

    </style>

</head>

<body>

<div class="register-card">

    <h2>Smart Cafeteria</h2>

    <p class="subtitle">
        Create Student Account
    </p>

    <?php if ($error): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <?php if ($success): ?>

        <div class="success">
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>


    <form method="POST" action="register.php">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
        >

        <label>Full Name</label>

        <input
            type="text"
            name="name"
            placeholder="Enter your full name"
            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
            required
        >


        <label>Email</label>

        <input
            type="email"
            name="email"
            placeholder="student@example.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            required
        >


        <label>Phone</label>

        <input
            type="text"
            name="phone"
            placeholder="01XXXXXXXXX"
            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
            required
        >


        <label>Department</label>

        <input
            type="text"
            name="department"
            placeholder="CSE"
            value="<?= htmlspecialchars($_POST['department'] ?? '') ?>"
            required
        >


        <label>Batch</label>

        <input
            type="text"
            name="batch"
            placeholder="52"
            value="<?= htmlspecialchars($_POST['batch'] ?? '') ?>"
            required
        >


        <label>Password</label>

        <input
            type="password"
            name="password"
            placeholder="Minimum 6 characters"
            required
        >


        <label>Confirm Password</label>

        <input
            type="password"
            name="confirm_password"
            placeholder="Enter password again"
            required
        >


        <button type="submit">
            Create Account
        </button>

    </form>


    <div class="links">

        <p>
            Already have an account?
            <a href="login.php">Log In</a>
        </p>

        <p>
            <a href="index.php">← Back to Menu</a>
        </p>

    </div>

</div>

</body>

</html>