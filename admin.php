<?php

session_start();
require 'db.php';

// --------------------------------
// Only logged-in Admins may access this page
// --------------------------------
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'Admin') {

    switch ($_SESSION['role']) {

        case 'Student':
            header("Location: dashboard.php");
            exit();

        case 'Staff':
            header("Location: staff_dashboard.php");
            exit();

        case 'Faculty':
            header("Location: faculty_dashboard.php");
            exit();

        default:
            header("Location: login.php");
            exit();
    }
}

// --------------------------------
// CSRF token (generate once per session)
// --------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Validation
    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {

        $error = "Your session has expired. Please try again.";

    } elseif (
        empty($name) ||
        empty($email) ||
        empty($phone) ||
        empty($password) ||
        empty($role)
    ) {

        $error = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 6) {

        $error = "Password must contain at least 6 characters.";

    } elseif (!in_array($role, ['Admin', 'Staff'])) {

        $error = "Invalid role selected.";

    } else {

        try {

            // Check if email already exists
            $stmt = $pdo->prepare(
                "SELECT UserID FROM `USER` WHERE Email = ?"
            );

            $stmt->execute([$email]);

            if ($stmt->fetch()) {

                $error = "An account with this email already exists.";

            } else {

                // Hash password
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Create Admin/Staff account
                $stmt = $pdo->prepare(
                    "INSERT INTO `USER`
                    (Name, Email, Phone, Password, Role)
                    VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $name,
                    $email,
                    $phone,
                    $hashedPassword,
                    $role
                ]);

                $success =
                    "$role account created successfully.";

            }

        } catch (PDOException $e) {

            // Handle a duplicate-email race condition gracefully
            if ($e->getCode() === '23000') {
                $error = "An account with this email already exists.";
            } else {
                error_log("create_admin.php DB error: " . $e->getMessage());
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Create Admin/Staff Account</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {

            font-family: Arial, sans-serif;

            background: #f4f6f9;

            margin: 0;

            min-height: 100vh;

            display: flex;

            justify-content: center;

            align-items: center;
        }

        .card {

            background: white;

            width: 420px;

            max-width: 90%;

            padding: 30px;

            border-radius: 10px;

            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        h2 {
            text-align: center;
        }

        label {

            display: block;

            margin-top: 15px;

            margin-bottom: 5px;

            font-weight: bold;
        }

        input,
        select {

            width: 100%;

            padding: 11px;

            border: 1px solid #ccc;

            border-radius: 5px;
        }

        button {

            width: 100%;

            margin-top: 22px;

            padding: 12px;

            background: #007bff;

            color: white;

            border: none;

            border-radius: 5px;

            font-size: 16px;

            cursor: pointer;
        }

        .error {

            background: #f8d7da;

            color: #721c24;

            padding: 10px;

            border-radius: 5px;

            margin-bottom: 15px;

            text-align: center;
        }

        .success {

            background: #d4edda;

            color: #155724;

            padding: 10px;

            border-radius: 5px;

            margin-bottom: 15px;

            text-align: center;
        }

        .back-link {

            display: block;

            text-align: center;

            margin-top: 18px;

            color: #007bff;

            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

    </style>

</head>

<body>

<div class="card">

    <h2>Create Admin / Staff</h2>

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

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
        >

        <label>Full Name</label>

        <input
            type="text"
            name="name"
            placeholder="Enter full name"
            required
        >

        <label>Email</label>

        <input
            type="email"
            name="email"
            placeholder="admin@example.com"
            required
        >

        <label>Phone</label>

        <input
            type="text"
            name="phone"
            placeholder="01XXXXXXXXX"
            required
        >

        <label>Role</label>

        <select name="role" required>

            <option value="">Select Role</option>

            <option value="Admin">Admin</option>

            <option value="Staff">Staff</option>

        </select>

        <label>Password</label>

        <input
            type="password"
            name="password"
            placeholder="Minimum 6 characters"
            required
        >

        <button type="submit">
            Create Account
        </button>

    </form>

    <a class="back-link" href="admin_dashboard.php">
        ← Back to Dashboard
    </a>

</div>

</body>

</html>