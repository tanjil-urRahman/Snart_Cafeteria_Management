<?php
session_start();
require 'db.php';

// Only Admin can create Staff/Admin accounts
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'Admin') {
    switch ($_SESSION['role']) {
        case 'Student':
            header("Location: dashboard.php");
            break;

        case 'Staff':
            header("Location: staff_dashboard.php");
            break;

        case 'Faculty':
            header("Location: faculty_dashboard.php");
            break;

        default:
            header("Location: login.php");
            break;
    }

    exit();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = "Invalid request. Please try again.";
    }

    // Validation
    elseif ($name === '' || $email === '' || $password === '' || $role === '') {
        $error = "Please fill in all required fields.";
    }

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }

    elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    }

    elseif (!in_array($role, ['Admin', 'Staff'], true)) {
        $error = "Invalid account role.";
    }

    else {
        try {

            // Check duplicate email
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

                // Create account
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

                $message = $role . " account created successfully.";
            }

        } catch (PDOException $e) {

            if ($e->getCode() === '23000') {
                $error = "An account with this email already exists.";
            } else {
                error_log("create_user.php DB error: " . $e->getMessage());
                $error = "Something went wrong. Please try again later.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create User</title>
</head>

<body>

<h2>Create Staff/Admin Account</h2>

<?php if ($message): ?>
    <p style="color: green;">
        <?php echo htmlspecialchars($message); ?>
    </p>
<?php endif; ?>

<?php if ($error): ?>
    <p style="color: red;">
        <?php echo htmlspecialchars($error); ?>
    </p>
<?php endif; ?>

<form method="POST">

    <input
        type="hidden"
        name="csrf_token"
        value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
    >

    <label>Name:</label><br>
    <input type="text" name="name" required>
    <br><br>

    <label>Email:</label><br>
    <input type="email" name="email" required>
    <br><br>

    <label>Phone:</label><br>
    <input type="text" name="phone">
    <br><br>

    <label>Password:</label><br>
    <input type="password" name="password" required>
    <br><br>

    <label>Role:</label><br>
    <select name="role" required>
        <option value="">Select Role</option>
        <option value="Staff">Staff</option>
        <option value="Admin">Admin</option>
    </select>
    <br><br>

    <button type="submit">Create User</button>

</form>

<br>

<a href="admin_dashboard.php">Back to Admin Dashboard</a>

</body>
</html>