```php
<?php
// wallet.php

session_start();
require 'db.php';

// Only logged-in students can access the wallet
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

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


// ===============================
// WALLET TOP-UP
// ===============================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topup_amount'])) {

    $amount = floatval($_POST['topup_amount']);

    if ($amount <= 0) {

        $error = "Please enter a valid positive top-up amount.";

    } else {

        try {

            // Start database transaction
            $pdo->beginTransaction();

            // Add money to wallet
            $stmt = $pdo->prepare(
                "UPDATE `WALLET`
                 SET Balance = Balance + ?
                 WHERE UserID = ?"
            );

            $stmt->execute([$amount, $userId]);


            // Record the transaction
            $tx = $pdo->prepare(
                "INSERT INTO `WALLET_TRANSACTION`
                 (UserID, Amount, Type)
                 VALUES (?, ?, 'Topup')"
            );

            $tx->execute([$userId, $amount]);


            // Both operations succeeded
            $pdo->commit();

            $message = "Wallet recharged with " .
                       number_format($amount, 2) .
                       " Tk.";

        } catch (PDOException $e) {

            // Undo changes if something failed
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("Wallet transaction error: " . $e->getMessage());

            $error = "Transaction failed. Please try again.";

        }
    }
}


// ===============================
// GET WALLET BALANCE
// ===============================

$wStmt = $pdo->prepare(
    "SELECT Balance, LastUpdated
     FROM `WALLET`
     WHERE UserID = ?"
);

$wStmt->execute([$userId]);

$wallet = $wStmt->fetch();

$balance = $wallet ? $wallet['Balance'] : 0.00;


// ===============================
// GET TRANSACTION HISTORY
// ===============================

$tStmt = $pdo->prepare(
    "SELECT TransactionID, Amount, Type, TransactionDate
     FROM `WALLET_TRANSACTION`
     WHERE UserID = ?
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


        /* Header */

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


        /* Main container */

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


        /* Wallet cards */

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


        /* Balance */

        .balance {
            font-size: 2.2rem;
            font-weight: bold;

            color: #28a745;

            margin: 15px 0;
        }


        /* Form */

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


        /* Messages */

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


        /* Transaction table */

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


<!-- Header -->

<div class="header">

    <h2>💰 Smart Cafeteria Wallet</h2>

    <a class="back" href="dashboard.php">
        ← Student Dashboard
    </a>

</div>


<div class="container">


    <!-- Welcome -->

    <div class="welcome">

        <h1>
            Welcome, <?= htmlspecialchars($name) ?>!
        </h1>

        <p>
            Manage your cafeteria wallet and view your recent transactions.
        </p>

    </div>


    <div class="grid-2">


        <!-- Wallet Balance -->

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


        <!-- Transaction History -->

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
                                        <?= $h['Type'] === 'Topup'
                                            ? 'badge-success'
                                            : 'badge-danger'
                                        ?>"
                                    >

                                        <?= htmlspecialchars($h['Type']) ?>

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
