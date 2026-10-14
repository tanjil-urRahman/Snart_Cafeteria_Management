<?php
session_start();
require 'db.php';

/* =========================================================
   LOGIN / ROLE CHECK
========================================================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Staff') {
    if (($_SESSION['role'] ?? '') === 'Student') {
        header("Location: dashboard.php");
    } elseif (($_SESSION['role'] ?? '') === 'Admin') {
        header("Location: admin_dashboard.php");
    } elseif (($_SESSION['role'] ?? '') === 'Faculty') {
        header("Location: faculty_dashboard.php");
    } else {
        header("Location: login.php");
    }
    exit();
}

$staffID   = (int)$_SESSION['user_id'];
$staffName = $_SESSION['name'] ?? 'Staff';

$error   = '';
$success = '';
$receipt = null;

/* =========================================================
   GET STAFF INFORMATION + ASSIGNED COUNTER
========================================================= */
$stmt = $pdo->prepare("
    SELECT
        st.UserID,
        st.Position,
        st.Shift,
        st.CounterID,
        c.CounterName,
        c.Status AS CounterStatus,
        c.Location
    FROM STAFF st
    LEFT JOIN COUNTER c
        ON st.CounterID = c.CounterID
    WHERE st.UserID = ?
");
$stmt->execute([$staffID]);
$staffInfo = $stmt->fetch(PDO::FETCH_ASSOC);

$assignedCounterID = 0;

if ($staffInfo && !empty($staffInfo['CounterID'])) {
    $assignedCounterID = (int)$staffInfo['CounterID'];
}

/* =========================================================
   CURRENT BILL - SEPARATE FOR EACH STAFF
========================================================= */
$billSessionKey = 'current_bill_staff_' . $staffID;

if (!isset($_SESSION[$billSessionKey])) {
    $_SESSION[$billSessionKey] = [];
}

$bill = &$_SESSION[$billSessionKey];

/* If admin changes the staff counter, clear old-counter bill */
if ($assignedCounterID > 0 && !empty($bill)) {
    foreach ($bill as $item) {
        if ((int)$item['CounterID'] !== $assignedCounterID) {
            $_SESSION[$billSessionKey] = [];
            $bill = &$_SESSION[$billSessionKey];
            $success = "Your counter assignment changed. Previous bill cleared.";
            break;
        }
    }
}

/* =========================================================
   STAFF COUNTER OPERATIONS
========================================================= */
if ($assignedCounterID > 0) {

    /* ---------------------------------------------------------
       ADD STOCK - ASSIGNED COUNTER ONLY
    --------------------------------------------------------- */
    if (isset($_POST['add_stock'])) {

        $foodID   = (int)($_POST['stock_food_id'] ?? 0);
        $quantity = (int)($_POST['stock_quantity'] ?? 0);

        if ($foodID <= 0 || $quantity <= 0) {
            $error = "Please select food and enter a valid quantity.";
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    SELECT AvailableQuantity
                    FROM COUNTER_SERVES_FOOD
                    WHERE CounterID = ?
                      AND FoodID = ?
                    FOR UPDATE
                ");
                $stmt->execute([$assignedCounterID, $foodID]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$existing) {
                    $pdo->rollBack();
                    $error = "This food is not assigned to your counter.";
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE COUNTER_SERVES_FOOD
                        SET AvailableQuantity = AvailableQuantity + ?
                        WHERE CounterID = ?
                          AND FoodID = ?
                    ");
                    $stmt->execute([
                        $quantity,
                        $assignedCounterID,
                        $foodID
                    ]);
                    $pdo->commit();
                    $success = "Stock added to your assigned counter successfully.";
                }

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Stock error: " . $e->getMessage());
                $error = "Could not update stock.";
            }
        }
    }

    /* ---------------------------------------------------------
       ADD FOOD TO BILL - ASSIGNED COUNTER ONLY
    --------------------------------------------------------- */
    if (isset($_POST['add_to_bill'])) {

        $foodID   = (int)($_POST['bill_food_id'] ?? 0);
        $quantity = (int)($_POST['bill_quantity'] ?? 0);

        if ($foodID <= 0 || $quantity <= 0) {
            $error = "Please select food and quantity.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    SELECT
                        f.FoodID,
                        f.FoodName,
                        f.Category,
                        f.Price,
                        csf.AvailableQuantity
                    FROM COUNTER_SERVES_FOOD csf
                    JOIN FOOD f
                        ON csf.FoodID = f.FoodID
                    WHERE csf.CounterID = ?
                      AND csf.FoodID = ?
                ");
                $stmt->execute([$assignedCounterID, $foodID]);
                $food = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$food) {
                    $error = "This food is not available at your assigned counter.";
                } elseif ((int)$food['AvailableQuantity'] < $quantity) {
                    $error = "Only " . (int)$food['AvailableQuantity'] . " item(s) available.";
                } else {

                    $found = false;

                    foreach ($bill as &$item) {
                        if ((int)$item['FoodID'] === $foodID) {

                            $newQuantity = (int)$item['Quantity'] + $quantity;

                            if ($newQuantity > (int)$food['AvailableQuantity']) {
                                $error = "You cannot add more than available stock.";
                            } else {
                                $item['Quantity'] = $newQuantity;
                                $item['Subtotal'] =
                                    $newQuantity * (float)$item['Price'];

                                $success = "Food quantity updated in current bill.";
                            }

                            $found = true;
                            break;
                        }
                    }

                    unset($item);

                    if (!$found && !$error) {
                        $bill[] = [
                            'CounterID' => $assignedCounterID,
                            'FoodID' => (int)$food['FoodID'],
                            'FoodName' => $food['FoodName'],
                            'Category' => $food['Category'],
                            'Price' => (float)$food['Price'],
                            'Quantity' => $quantity,
                            'Subtotal' => $quantity * (float)$food['Price']
                        ];

                        $success = "Food added to current bill.";
                    }
                }

            } catch (PDOException $e) {
                error_log("Bill add error: " . $e->getMessage());
                $error = "Could not add food to bill.";
            }
        }
    }

    /* ---------------------------------------------------------
       UPDATE BILL ITEM
    --------------------------------------------------------- */
    if (isset($_POST['update_item'])) {

        $index       = (int)($_POST['item_index'] ?? -1);
        $newQuantity = (int)($_POST['new_quantity'] ?? 0);

        if (!isset($bill[$index])) {
            $error = "Item not found.";
        } elseif ((int)$bill[$index]['CounterID'] !== $assignedCounterID) {
            $error = "This item does not belong to your assigned counter.";
        } elseif ($newQuantity <= 0) {
            unset($bill[$index]);
            $bill = array_values($bill);
            $success = "Item removed from bill.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    SELECT AvailableQuantity
                    FROM COUNTER_SERVES_FOOD
                    WHERE CounterID = ?
                      AND FoodID = ?
                ");
                $stmt->execute([
                    $assignedCounterID,
                    $bill[$index]['FoodID']
                ]);
                $stock = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$stock) {
                    $error = "Food is no longer available at your counter.";
                } elseif ($newQuantity > (int)$stock['AvailableQuantity']) {
                    $error = "Only " . (int)$stock['AvailableQuantity'] . " item(s) available.";
                } else {
                    $bill[$index]['Quantity'] = $newQuantity;
                    $bill[$index]['Subtotal'] =
                        $newQuantity * (float)$bill[$index]['Price'];

                    $success = "Bill quantity updated.";
                }

            } catch (PDOException $e) {
                $error = "Could not update quantity.";
            }
        }
    }

    /* ---------------------------------------------------------
       REMOVE ITEM
    --------------------------------------------------------- */
    if (isset($_POST['remove_item'])) {

        $index = (int)($_POST['item_index'] ?? -1);

        if (isset($bill[$index])) {
            unset($bill[$index]);
            $bill = array_values($bill);
            $success = "Item removed from bill.";
        }
    }

    /* ---------------------------------------------------------
       CLEAR BILL
    --------------------------------------------------------- */
    if (isset($_POST['clear_bill'])) {
        $_SESSION[$billSessionKey] = [];
        $bill = &$_SESSION[$billSessionKey];
        $success = "Current bill cleared.";
    }

    /* ---------------------------------------------------------
       COMPLETE SALE - ASSIGNED COUNTER ONLY
    --------------------------------------------------------- */
    if (isset($_POST['complete_sale'])) {

        $cashReceived = (float)($_POST['cash_received'] ?? 0);

        if (empty($bill)) {
            $error = "There are no items in the current bill.";
        } else {

            $total = 0;

            foreach ($bill as $item) {
                if ((int)$item['CounterID'] !== $assignedCounterID) {
                    $error = "Invalid bill. Item belongs to another counter.";
                    break;
                }

                $total += (float)$item['Subtotal'];
            }

            if (!$error && $cashReceived < $total) {
                $error =
                    "Insufficient cash. Total bill is ৳" .
                    number_format($total, 2);
            }

            if (!$error) {
                try {
                    $pdo->beginTransaction();

                    /* Recheck stock */
                    foreach ($bill as $item) {
                        $stmt = $pdo->prepare("
                            SELECT AvailableQuantity
                            FROM COUNTER_SERVES_FOOD
                            WHERE CounterID = ?
                              AND FoodID = ?
                            FOR UPDATE
                        ");
                        $stmt->execute([
                            $assignedCounterID,
                            $item['FoodID']
                        ]);

                        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$stock) {
                            throw new Exception(
                                $item['FoodName'] . " is no longer available."
                            );
                        }

                        if ((int)$stock['AvailableQuantity'] < (int)$item['Quantity']) {
                            throw new Exception(
                                "Not enough " . $item['FoodName'] . " available."
                            );
                        }
                    }

                    $saleDate = date('Y-m-d');
                    $saleTime = date('H:i:s');
                    $changeAmount = $cashReceived - $total;

                    $stmt = $pdo->prepare("
                        INSERT INTO SALE
                        (
                            StudentID,
                            SaleDate,
                            SaleTime,
                            PickupCounterID,
                            PickupTime,
                            OrderStatus,
                            TotalAmount,
                            AmountPaid,
                            ChangeAmount,
                            PaymentMethod
                        )
                        VALUES
                        (
                            NULL,
                            ?,
                            ?,
                            ?,
                            NULL,
                            'Completed',
                            ?,
                            ?,
                            ?,
                            'Cash'
                        )
                    ");

                    $stmt->execute([
                        $saleDate,
                        $saleTime,
                        $assignedCounterID,
                        $total,
                        $cashReceived,
                        $changeAmount
                    ]);

                    $saleID = (int)$pdo->lastInsertId();
                    $itemNo = 1;

                    foreach ($bill as $item) {

                        $stmt = $pdo->prepare("
                            INSERT INTO SALE_ITEM
                            (
                                SaleID,
                                ItemNo,
                                FoodID,
                                QtyPurchased,
                                Subtotal
                            )
                            VALUES (?, ?, ?, ?, ?)
                        ");

                        $stmt->execute([
                            $saleID,
                            $itemNo,
                            $item['FoodID'],
                            $item['Quantity'],
                            $item['Subtotal']
                        ]);

                        $stmt = $pdo->prepare("
                            UPDATE COUNTER_SERVES_FOOD
                            SET AvailableQuantity =
                                AvailableQuantity - ?
                            WHERE CounterID = ?
                              AND FoodID = ?
                        ");

                        $stmt->execute([
                            $item['Quantity'],
                            $assignedCounterID,
                            $item['FoodID']
                        ]);

                        $itemNo++;
                    }

                    $pdo->commit();

                    $receipt = [
                        'SaleID' => $saleID,
                        'Date' => $saleDate,
                        'Time' => $saleTime,
                        'CounterName' => $staffInfo['CounterName'],
                        'Items' => $bill,
                        'Total' => $total,
                        'Cash' => $cashReceived,
                        'Change' => $changeAmount
                    ];

                    $_SESSION[$billSessionKey] = [];
                    $bill = &$_SESSION[$billSessionKey];

                    $success =
                        "Sale completed successfully. Receipt #" .
                        $saleID;

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log("Sale error: " . $e->getMessage());
                    $error = $e->getMessage();
                }
            }
        }
    }
}

/* =========================================================
   STAFF FOOD LIST - ONLY FOODS ASSIGNED TO THIS COUNTER
========================================================= */
$foods = [];

if ($assignedCounterID > 0) {
    $stmt = $pdo->prepare("
        SELECT
            f.FoodID,
            f.FoodName,
            f.Category,
            f.Price,
            csf.AvailableQuantity
        FROM COUNTER_SERVES_FOOD csf
        JOIN FOOD f
            ON csf.FoodID = f.FoodID
        WHERE csf.CounterID = ?
        ORDER BY f.Category, f.FoodName
    ");
    $stmt->execute([$assignedCounterID]);
    $foods = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================================================
   ASSIGNED COUNTER STOCK ONLY
========================================================= */
$counterStock = [];

if ($assignedCounterID > 0) {
    $stmt = $pdo->prepare("
        SELECT
            csf.FoodID,
            f.FoodName,
            f.Category,
            f.Price,
            csf.AvailableQuantity
        FROM COUNTER_SERVES_FOOD csf
        JOIN FOOD f
            ON csf.FoodID = f.FoodID
        WHERE csf.CounterID = ?
        ORDER BY f.Category, f.FoodName
    ");
    $stmt->execute([$assignedCounterID]);
    $counterStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================================================
   RECENT SALES - ASSIGNED COUNTER ONLY
========================================================= */
$salesHistory = [];

if ($assignedCounterID > 0) {
    $stmt = $pdo->prepare("
        SELECT
            s.SaleID,
            s.SaleDate,
            s.SaleTime,
            s.TotalAmount,
            s.AmountPaid,
            s.ChangeAmount,
            c.CounterName
        FROM SALE s
        LEFT JOIN COUNTER c
            ON s.PickupCounterID = c.CounterID
        WHERE s.OrderStatus = 'Completed'
          AND s.PickupCounterID = ?
        ORDER BY s.SaleID DESC
        LIMIT 15
    ");
    $stmt->execute([$assignedCounterID]);
    $salesHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================================================
   BILL TOTAL
========================================================= */
$billTotal = 0;

foreach ($bill as $item) {
    $billTotal += (float)$item['Subtotal'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Dashboard - Smart Cafeteria</title>

<style>
*{box-sizing:border-box}

body{
    margin:0;
    font-family:Arial,sans-serif;
    background:#f4f6f9;
    color:#333;
}

.header{
    background:#343a40;
    color:white;
    padding:18px 30px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.header h2{margin:0}

.header-right{
    display:flex;
    align-items:center;
    gap:15px;
}

.logout,
.nav-link{
    color:white;
    text-decoration:none;
    padding:9px 13px;
    border-radius:5px;
}

.logout{background:#dc3545}
.nav-link{background:#007bff}

.container{
    width:95%;
    max-width:1200px;
    margin:25px auto;
}

.card,
.welcome{
    background:white;
    padding:20px;
    border-radius:8px;
    margin-bottom:20px;
    box-shadow:0 2px 8px rgba(0,0,0,.08);
}

.two-column{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
}

.message{
    padding:12px;
    border-radius:5px;
    margin-bottom:15px;
}

.success{
    background:#d4edda;
    color:#155724;
}

.error{
    background:#f8d7da;
    color:#721c24;
}

.warning{
    background:#fff3cd;
    color:#856404;
    border:1px solid #ffeeba;
}

.counter-box{
    padding:14px;
    background:#eef5ff;
    border-left:5px solid #007bff;
    margin:12px 0;
}

label{
    display:block;
    font-weight:bold;
    margin-top:10px;
    margin-bottom:5px;
}

input,
select{
    width:100%;
    padding:10px;
    border:1px solid #ccc;
    border-radius:5px;
    margin-bottom:10px;
}

button{
    margin-top:8px;
    padding:10px 14px;
    border:0;
    border-radius:5px;
    cursor:pointer;
    background:#007bff;
    color:white;
}

button.green{background:#28a745}
button.red{background:#dc3545}
button.gray{background:#6c757d}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:12px;
}

th,
td{
    padding:10px;
    border-bottom:1px solid #ddd;
    text-align:left;
}

th{
    background:#343a40;
    color:white;
}

.small-form{
    display:flex;
    gap:6px;
    align-items:center;
}

.small-form input{
    width:80px;
    margin:0;
}

.small-form button{
    margin:0;
}

.bill-total{
    font-size:24px;
    font-weight:bold;
    text-align:right;
    margin:15px 0;
}

.receipt{
    border:2px dashed #555;
}

.muted{
    color:#777;
}

@media(max-width:800px){
    .two-column{
        grid-template-columns:1fr;
    }
}
@media print {

    body * {
        visibility: hidden;
    }

    .receipt,
    .receipt * {
        visibility: visible;
    }

    .receipt {
        position: absolute;
        left: 0;
        top: 0;
        width: 350px;
        margin: 0;
        padding: 20px;
        border: none;
        box-shadow: none;
        background: white;
    }

    .receipt button {
        display: none;
    }
}
</style>
</head>

<body>

<div class="header">
    <h2>Smart Cafeteria — Staff</h2>

    <div class="header-right">
        <span><?= htmlspecialchars($staffName) ?></span>
        <a class="logout" href="logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="welcome">

        <h2>Welcome, <?= htmlspecialchars($staffName) ?></h2>

        <?php if ($staffInfo): ?>

            <p>
                Position:
                <strong><?= htmlspecialchars($staffInfo['Position'] ?? '-') ?></strong>

                &nbsp; | &nbsp;

                Shift:
                <strong><?= htmlspecialchars($staffInfo['Shift'] ?? '-') ?></strong>
            </p>

        <?php endif; ?>

        <?php if ($assignedCounterID > 0): ?>

            <div class="counter-box">

                <strong>Assigned Counter:</strong>
                <?= htmlspecialchars($staffInfo['CounterName']) ?>

                <br>

                <strong>Location:</strong>
                <?= htmlspecialchars($staffInfo['Location'] ?? '-') ?>

                <br>

                <strong>Status:</strong>
                <?= htmlspecialchars($staffInfo['CounterStatus'] ?? '-') ?>

            </div>

            <p>
                You can manage stock and make sales
                <strong>only from this assigned counter.</strong>
            </p>

            <div style="display:flex;flex-wrap:wrap;gap:8px;">

                <a class="nav-link" href="attendance.php">
                    My Attendance
                </a>

                <a class="nav-link" href="staff_tasks.php">
                    My Tasks
                </a>

                <a class="nav-link" href="order_management.php">
                    Online Orders
                </a>

                <a class="nav-link" href="seat_requests.php">
                    Seat Requests
                </a>

            </div>

        <?php else: ?>

            <div class="message warning">

                <strong>No counter assigned.</strong>

                <br><br>

                Your staff account exists, but Admin has not assigned
                Counter 1, Counter 2, Counter 3 or Counter 4 to this account.

                <br><br>

                Login as Admin and open
                <strong>Staff & Shift Management</strong>,
                then assign this staff member to one counter.

            </div>

        <?php endif; ?>

    </div>

    <?php if ($success): ?>

        <div class="message success">
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="message error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <?php if ($assignedCounterID > 0): ?>

    <!-- =====================================================
         STOCK
    ====================================================== -->
    <div class="two-column">

        <div class="card">

            <h2>📦 Add Food Stock</h2>

            <label>Assigned Counter</label>

            <input
                type="text"
                value="<?= htmlspecialchars($staffInfo['CounterName']) ?>"
                readonly
            >

            <form method="POST">

                <label>Food</label>

                <select name="stock_food_id" required>

                    <option value="">Select Food</option>

                    <?php foreach ($foods as $food): ?>

                        <option value="<?= (int)$food['FoodID'] ?>">

                            <?= htmlspecialchars($food['FoodName']) ?>

                            —

                            <?= htmlspecialchars($food['Category']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <label>Quantity to Add</label>

                <input
                    type="number"
                    name="stock_quantity"
                    min="1"
                    required
                >

                <button
                    type="submit"
                    name="add_stock"
                    class="green"
                >
                    Add Stock
                </button>

            </form>

        </div>

        <div class="card">

            <h2>
                🏪 <?= htmlspecialchars($staffInfo['CounterName']) ?> Stock
            </h2>

            <?php if (empty($counterStock)): ?>

                <p class="muted">
                    No food currently assigned to this counter.
                </p>

            <?php else: ?>

                <table>

                    <tr>
                        <th>Food</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Available</th>
                    </tr>

                    <?php foreach ($counterStock as $stock): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($stock['FoodName']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($stock['Category']) ?>
                            </td>

                            <td>
                                ৳<?= number_format($stock['Price'], 2) ?>
                            </td>

                            <td>
                                <?= (int)$stock['AvailableQuantity'] ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </table>

            <?php endif; ?>

        </div>

    </div>

    <!-- =====================================================
         SALES
    ====================================================== -->
    <div class="two-column">

        <div class="card">

            <h2>🧾 Create Bill</h2>

            <label>Assigned Counter</label>

            <input
                type="text"
                value="<?= htmlspecialchars($staffInfo['CounterName']) ?>"
                readonly
            >

            <form method="POST">

                <label>Food</label>

                <select name="bill_food_id" required>

                    <option value="">Select Food</option>

                    <?php foreach ($counterStock as $stock): ?>

                        <?php if ((int)$stock['AvailableQuantity'] > 0): ?>

                            <option value="<?= (int)$stock['FoodID'] ?>">

                                <?= htmlspecialchars($stock['FoodName']) ?>

                                — ৳<?= number_format($stock['Price'], 2) ?>

                                (<?= (int)$stock['AvailableQuantity'] ?> available)

                            </option>

                        <?php endif; ?>

                    <?php endforeach; ?>

                </select>

                <label>Quantity</label>

                <input
                    type="number"
                    name="bill_quantity"
                    min="1"
                    value="1"
                    required
                >

                <button type="submit" name="add_to_bill">
                    Add to Bill
                </button>

            </form>

        </div>

        <div class="card">

            <h2>🧾 Current Bill</h2>

            <?php if (empty($bill)): ?>

                <p class="muted">
                    No items added yet.
                </p>

            <?php else: ?>

                <table>

                    <tr>
                        <th>Food</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                        <th>Action</th>
                    </tr>

                    <?php foreach ($bill as $index => $item): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($item['FoodName']) ?>
                            </td>

                            <td>

                                <form method="POST" class="small-form">

                                    <input
                                        type="hidden"
                                        name="item_index"
                                        value="<?= $index ?>"
                                    >

                                    <input
                                        type="number"
                                        name="new_quantity"
                                        min="0"
                                        value="<?= (int)$item['Quantity'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="update_item"
                                    >
                                        ✓
                                    </button>

                                </form>

                            </td>

                            <td>
                                ৳<?= number_format($item['Price'], 2) ?>
                            </td>

                            <td>
                                ৳<?= number_format($item['Subtotal'], 2) ?>
                            </td>

                            <td>

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="item_index"
                                        value="<?= $index ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="remove_item"
                                        class="red"
                                    >
                                        Remove
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </table>

                <div class="bill-total">

                    Total:
                    ৳<?= number_format($billTotal, 2) ?>

                </div>

                <form method="POST">

                    <label>Cash Received</label>

                    <input
                        type="number"
                        name="cash_received"
                        min="<?= $billTotal ?>"
                        step="0.01"
                        required
                    >

                    <button
                        type="submit"
                        name="complete_sale"
                        class="green"
                    >
                        Complete Sale
                    </button>

                </form>

                <form method="POST">

                    <button
                        type="submit"
                        name="clear_bill"
                        class="gray"
                    >
                        Clear Bill
                    </button>

                </form>

            <?php endif; ?>

        </div>

    </div>

    <!-- =====================================================
         RECEIPT
    ====================================================== -->
    <?php if ($receipt): ?>

        <div class="card receipt">

            <h2>
                Receipt #<?= (int)$receipt['SaleID'] ?>
            </h2>

            <p>
                <?= htmlspecialchars($receipt['Date']) ?>
                <?= htmlspecialchars($receipt['Time']) ?>
            </p>

            <p>
                Counter:
                <strong>
                    <?= htmlspecialchars($receipt['CounterName']) ?>
                </strong>
            </p>

            <table>

                <tr>
                    <th>Food</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                </tr>

                <?php foreach ($receipt['Items'] as $item): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($item['FoodName']) ?>
                        </td>

                        <td>
                            <?= (int)$item['Quantity'] ?>
                        </td>

                        <td>
                            ৳<?= number_format($item['Subtotal'],2) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </table>

            <p>
                <strong>Total:</strong>
                ৳<?= number_format($receipt['Total'],2) ?>
            </p>

            <p>
                <strong>Cash:</strong>
                ৳<?= number_format($receipt['Cash'],2) ?>
            </p>

            <p>
                <strong>Change:</strong>
                ৳<?= number_format($receipt['Change'],2) ?>
            </p>

            <button onclick="window.print()">
                Print Receipt
            </button>

        </div>

    <?php endif; ?>

    <!-- =====================================================
         RECENT SALES
    ====================================================== -->
    <div class="card">

        <h2>
            📜 Recent Sales —
            <?= htmlspecialchars($staffInfo['CounterName']) ?>
        </h2>

        <?php if (empty($salesHistory)): ?>

            <p class="muted">
                No completed sales recorded for this counter yet.
            </p>

        <?php else: ?>

            <table>

                <tr>
                    <th>Receipt</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Total</th>
                    <th>Cash</th>
                    <th>Change</th>
                </tr>

                <?php foreach ($salesHistory as $sale): ?>

                    <tr>

                        <td>
                            #<?= (int)$sale['SaleID'] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($sale['SaleDate']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($sale['SaleTime']) ?>
                        </td>

                        <td>
                            ৳<?= number_format($sale['TotalAmount'],2) ?>
                        </td>

                        <td>
                            ৳<?= number_format($sale['AmountPaid'],2) ?>
                        </td>

                        <td>
                            ৳<?= number_format($sale['ChangeAmount'],2) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </table>

        <?php endif; ?>

    </div>

    <?php endif; ?>

</div>

</body>
</html>
