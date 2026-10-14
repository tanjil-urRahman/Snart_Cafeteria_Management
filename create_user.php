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

    // Extra fields only needed when creating a Staff account
    $position = trim($_POST['position'] ?? '');
    $shift = $_POST['shift'] ?? '';
    $salary = $_POST['salary'] ?? '';
    $counterId = $_POST['counter_id'] ?? '';

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

    // Staff accounts need Position/Shift/Salary too, since STAFF
    // is a separate table linked 1-to-1 with USER.
    elseif (
        $role === 'Staff' &&
        ($position === '' || $shift === '' || $salary === '' || !is_numeric($salary))
    ) {
        $error = "Please fill in position, shift and a valid salary for a Staff account.";
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

                $pdo->beginTransaction();

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

                $newUserId = $pdo->lastInsertId();

                // A Staff account also needs a matching STAFF row
                if ($role === 'Staff') {

                    $stmt = $pdo->prepare(
                        "INSERT INTO STAFF (UserID, Position, Shift, Salary, CounterID)
                         VALUES (?, ?, ?, ?, ?)"
                    );

                    $stmt->execute([
                        $newUserId,
                        $position,
                        $shift,
                        $salary,
                        $counterId !== '' ? (int)$counterId : null
                    ]);
                }

                $pdo->commit();

                $message = $role . " account created successfully.";
            }

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e->getCode() === '23000') {
                $error = "An account with this email already exists.";
            } else {
                error_log("create_user.php DB error: " . $e->getMessage());
                $error = "Something went wrong. Please try again later.";
            }
        }
    }
}

// Counters for the Staff-assignment dropdown
$counterOptions = $pdo->query(
    "SELECT CounterID, CounterName FROM COUNTER ORDER BY CounterName"
)->fetchAll(PDO::FETCH_ASSOC);
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
    <select name="role" id="role" required onchange="toggleStaffFields()">
        <option value="">Select Role</option>
        <option value="Staff">Staff</option>
        <option value="Admin">Admin</option>
    </select>
    <br><br>

    <div id="staffFields" style="display:none;">

        <label>Position:</label><br>
        <input type="text" name="position" placeholder="Counter Cashier, Food Server...">
        <br><br>

        <label>Shift:</label><br>
        <select name="shift">
            <option value="">Select Shift</option>
            <option value="Morning">Morning</option>
            <option value="Evening">Evening</option>
            <option value="Night">Night</option>
        </select>
        <br><br>

        <label>Salary:</label><br>
        <input type="number" name="salary" step="0.01" min="0">
        <br><br>

        <label>Assigned Counter (optional):</label><br>
        <select name="counter_id">
            <option value="">-- Not assigned yet --</option>
            <?php foreach ($counterOptions as $c): ?>
                <option value="<?= $c['CounterID'] ?>">
                    <?= htmlspecialchars($c['CounterName']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <br><br>

    </div>

    <button type="submit">Create User</button>

</form>

<br>

<a href="admin_dashboard.php">Back to Admin Dashboard</a>

<script>
function toggleStaffFields() {
    var role = document.getElementById('role').value;
    document.getElementById('staffFields').style.display =
        (role === 'Staff') ? 'block' : 'none';
}
</script>

</body>
</html>