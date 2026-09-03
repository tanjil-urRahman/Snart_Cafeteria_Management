<?php

session_start();
require 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // -----------------------------
    // VALIDATION
    // -----------------------------

    if (empty($email) || empty($password)) {

        $error = "Please enter email and password.";

    } else {

        try {

            // Find user
            $stmt = $pdo->prepare(
                "SELECT UserID, Name, Email, Password, Role
                 FROM `USER`
                 WHERE Email = ?"
            );

            $stmt->execute([$email]);

            $user = $stmt->fetch();

            if (!$user) {

                $error = "Invalid email or password.";

            } else {

                // Verify hashed password
                if (password_verify($password, $user['Password'])) {

                    // Prevent session fixation
                    session_regenerate_id(true);

                    // Store login information
                    $_SESSION['user_id'] = $user['UserID'];
                    $_SESSION['name'] = $user['Name'];
                    $_SESSION['email'] = $user['Email'];
                    $_SESSION['role'] = $user['Role'];

                    // --------------------------------
                    // ROLE BASED REDIRECTION
                    // --------------------------------

                    switch ($user['Role']) {

                        case 'Student':

                            header("Location: dashboard.php");
                            exit();

                        case 'Staff':

                            header("Location: staff_dashboard.php");
                            exit();

                        case 'Faculty':

                            header("Location: faculty_dashboard.php");
                            exit();

                        case 'Admin':

                            header("Location: admin_dashboard.php");
                            exit();

                        default:

                            $error = "Invalid account role.";
                    }

                } else {

                    $error = "Invalid email or password.";
                }
            }

        } catch (PDOException $e) {

    error_log("login.php DB error: " . $e->getMessage());

    $error = "Something went wrong. Please try again later.";
}
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Smart Cafeteria - Login</title>

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

        .login-card {

            background: white;

            width: 380px;
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

            margin-top: 15px;

            margin-bottom: 5px;

            font-weight: bold;
        }

        input {

            width: 100%;

            padding: 11px;

            border: 1px solid #ccc;

            border-radius: 5px;
        }

        input:focus {

            outline: none;

            border-color: #007bff;
        }

        button {

            width: 100%;

            padding: 12px;

            margin-top: 22px;

            background: #007bff;

            color: white;

            border: none;

            border-radius: 5px;

            font-size: 16px;

            cursor: pointer;
        }

        button:hover {

            background: #0069d9;
        }

        .error {

            background: #f8d7da;

            color: #721c24;

            padding: 10px;

            border-radius: 5px;

            text-align: center;

            margin-bottom: 15px;
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

<div class="login-card">

    <h2>Smart Cafeteria</h2>

    <p class="subtitle">
        Login to your account
    </p>


    <?php if ($error): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <form method="POST" action="login.php">

        <label>Email</label>

        <input
            type="email"
            name="email"
            placeholder="Enter your email"
            required
        >


        <label>Password</label>

        <input
            type="password"
            name="password"
            placeholder="Enter your password"
            required
        >


        <button type="submit">
            Log In
        </button>

    </form>


    <div class="links">

        <p>
            Don't have an account?
            <a href="register.php">
                Create Student Account
            </a>
        </p>

        <p>
            <a href="index.php">
                ← Back to Menu
            </a>
        </p>

    </div>

</div>

</body>

</html>