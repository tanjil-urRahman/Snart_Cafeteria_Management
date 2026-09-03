```php
<?php
// wallet.php

session_start();
require 'db.php';

// Only logged-in users can access this page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Only Students can use the wallet
if ($_SESSION['role'] !== 'Student') {

    switch ($_SESSION['role']) {

        case 'Admin':
            header("Location: admin_dashboard.php");
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

$userId = $_SESSION['user_id'];
$name = $_SESSION['name'];

$message = '';
$error = '';


// ==========================================
// WALLET TOP-UP
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topup_amount'])) {

    $amount = floatval($_POST['topup_amount']);

    if ($amount <= 0) {

        $error = "Please enter a valid positive top-up amount.";

    } else {

        try {

            // Start transaction
            $pdo->beginTransaction();


            // Add money to student's wallet
            $stmt = $pdo->prepare(
                "UPDATE STUDENT
                 SET WalletBalance = WalletBalance + ?
                 WHERE UserID = ?"
            );

            $stmt->execute([$amount, $userId]);


            // Record the wallet transaction
            $tx = $pdo->prepare(
                "INSERT INTO WALLET_TRANSACTION
                 (StudentID, Amount, TransactionType)
                 VALUES (?, ?, 'Deposit')"
            );

            $tx->execute([$userId, $amount]);


            // Commit both operations
            $pdo->commit();

            $message = "Wallet recharged with "
                     . number_format($amount, 2)
                     . " Tk.";

        } catch (PDOException $e) {

            // Roll back if anything fails
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("Wallet transaction error: " . $e->getMessage());

            $error = "Transaction failed. Please try again.";
        }
    }
}


// ==========================================
// GET CURRENT WALLET BALANCE
// ==========================================

$wStmt = $pdo->prepare(
    "SELECT WalletBalance
     FROM STUDENT
     WHERE UserID = ?"
);

$wStmt->execute([$userId]);

$student = $wStmt->fetch();

$balance = $student ? $student['WalletBalance'] : 0.00;


// ==========================================
// GET TRANSACTION HISTORY
// ==========================================

$tStmt = $pdo->prepare(
    "SELECT TransactionID,
            Amount,
            TransactionType,
            TransactionDate
     FROM WALLET_TRANSACTION
     WHERE StudentID = ?
     ORDER BY TransactionDate DESC
     LIMIT 10"
);

$tStmt->execute([$userId]);

$history = $tStmt->fetchAll();

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Wallet - Smart Cafeteria</title>


    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
        }

        .header {
            background: #343a40;
            color: white;

            padding: 18px 30px;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h2 {
            margin: 0;
        }

        .back {
            color: white;
            background: #007bff;

            text-decoration: none;

            padding: 9px 15px;

            border-radius: 5px;
        }

        .container {
            width: 90%;
            max-width: 1000px;

            margin: 35px auto;
        }

        .welcome {
            background: white;

            padding: 25px;

            border-radius: 10px;

            margin-bottom: 25px;

            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .welcome h1 {
            margin-top: 0;
        }

        .grid-2 {
            display: grid;

            grid-template-columns:
                repeat(auto-fit, minmax(300px, 1fr));

            gap: 20px;
        }

        .card {
            background: white;

            padding: 25px;

            border-radius: 10px;

            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .card h3 {
            margin-top: 0;
        }

        .balance {
            font-size: 2.2rem;

            font-weight: bold;

            color: #28a745;

            margin: 15px 0;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;

            margin-bottom: 7px;

            font-weight: bold;
        }

        .form-group input {
            width: 100%;

            padding: 10px;

            border: 1px solid #ccc;

            border-radius: 5px;

            font-size: 1rem;
        }

        .btn {
            border: none;

            padding: 10px 16px;

            border-radius: 5px;

            cursor: pointer;

            font-size: 1rem;
        }

        .btn-success {
            background: #28a745;

            color: white;
        }

        .alert {
            padding: 12px;

            border-radius: 5px;

            margin-bottom: 15px;
        }

        .alert-success {
            background: #d4edda;

            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;

            color: #721c24;
        }

        table {
            width: 100%;

            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;

            border-bottom: 1px solid #eee;

            text-align: left;
        }

        th {
            background: #f8f9fa;
        }

        .badge {
            padding: 5px 9px;

            border-radius: 12px;

            font-size: 0.85rem;
        }

        .badge-success {
            background: #d4edda;

            color: #155724;
        }

        .badge-danger {
            background: #f8d7da;

            color: #721c24;
        }

        .empty {
            text-align: center;

            color: #888;
        }

    </style>

</head>


<body>


<div class="header">

    <h2>💰 Smart Cafeteria Wallet</h2>

    <a class="back" href="dashboard.php">
        ← Student Dashboard
    </a>

</div>


<div class="container">


    <div class="welcome">

        <h1>
            Welcome, <?= htmlspecialchars($name) ?>!
        </h1>

        <p>
            Manage your cafeteria wallet and view your recent transactions.
        </p>

    </div>


    <div class="grid-2">


        <!-- WALLET BALANCE -->

        <div class="card">

            <h3>💰 Wallet Balance</h3>

            <p class="balance">

                <?= number_format($balance, 2) ?>

                <span style="font-size:1.1rem; color:#555;">
                    Tk
                </span>

            </p>


            <?php if ($message): ?>

                <div class="alert alert-success">

                    <?= htmlspecialchars($message) ?>

                </div>

            <?php endif; ?>


            <?php if ($error): ?>

                <div class="alert alert-danger">

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>


            <hr style="border:0; border-top:1px solid #eee; margin:20px 0;">


            <h4>Recharge Wallet</h4>

            <p style="color:#666;">
                This is a mock payment system for the project.
            </p>


            <form method="POST">

                <div class="form-group">

                    <label for="topup_amount">
                        Amount (BDT)
                    </label>

                    <input
                        type="number"
                        id="topup_amount"
                        name="topup_amount"
                        step="10"
                        min="20"
                        placeholder="e.g. 200"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="btn btn-success"
                >
                    Add Funds
                </button>

            </form>

        </div>


        <!-- TRANSACTION HISTORY -->

        <div class="card">

            <h3>🧾 Recent Wallet Activity</h3>


            <table>

                <thead>

                    <tr>

                        <th>Type</th>

                        <th>Amount</th>

                        <th>Date</th>

                    </tr>

                </thead>


                <tbody>


                    <?php if (empty($history)): ?>

                        <tr>

                            <td
                                colspan="3"
                                class="empty"
                            >
                                No transactions found.
                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach ($history as $h): ?>

                            <tr>

                                <td>

                                    <span class="badge
                                        <?= $h['TransactionType'] === 'Deposit'
                                            ? 'badge-success'
                                            : 'badge-danger'
                                        ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $h['TransactionType']
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?= number_format(
                                        $h['Amount'],
                                        2
                                    ) ?>

                                    Tk

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $h['TransactionDate']
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>


                    <?php endif; ?>


                </tbody>

            </table>

        </div>


    </div>


</div>


</body>

</html>
```
