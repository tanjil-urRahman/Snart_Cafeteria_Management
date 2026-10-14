<?php
session_start();
require_once 'db.php';

/* =========================
   CHECK LOGIN
   ========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Student') {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['user_id'];

$message = "";
$error = "";

/* =========================
   GET STUDENT INFORMATION
   ========================= */
$stmt = $pdo->prepare("
    SELECT 
        U.Name,
        U.Email,
        S.WalletBalance
    FROM USER U
    JOIN STUDENT S ON U.UserID = S.UserID
    WHERE U.UserID = ?
");

$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student information not found.");
}

/* =========================
   PLACE ORDER
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {

    $counter_id = intval($_POST['counter_id'] ?? 0);
    $pickup_time = $_POST['pickup_time'] ?? '';
    $payment_method = $_POST['payment_method'] ?? 'Mobile Banking';

    /*
     * Food IDs and quantities come from the form.
     */
    $food_ids = $_POST['food_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    if ($counter_id <= 0) {
        $error = "Please select a counter.";
    } elseif (empty($food_ids)) {
        $error = "Please select at least one food item.";
    } elseif (empty($pickup_time)) {
        $error = "Please select a pickup time.";
    } else {

        try {

            /* =========================
               START TRANSACTION
               ========================= */
            $pdo->beginTransaction();

            $order_items = [];
            $total_amount = 0;

            /* =========================
               CHECK EACH FOOD
               ========================= */
            for ($i = 0; $i < count($food_ids); $i++) {

                $food_id = intval($food_ids[$i]);
                $qty = intval($quantities[$i] ?? 0);

                if ($food_id <= 0 || $qty <= 0) {
                    continue;
                }

                /*
                 * Get food price and stock.
                 *
                 * IMPORTANT:
                 * The food must belong to the selected counter.
                 */
                $stmt = $pdo->prepare("
                    SELECT
                        F.FoodID,
                        F.FoodName,
                        F.Price,
                        C.AvailableQuantity
                    FROM COUNTER_SERVES_FOOD C
                    JOIN FOOD F ON C.FoodID = F.FoodID
                    WHERE C.CounterID = ?
                    AND C.FoodID = ?
                    FOR UPDATE
                ");

                $stmt->execute([$counter_id, $food_id]);
                $food = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$food) {
                    throw new Exception(
                        "One of the selected foods is not available at this counter."
                    );
                }

                /*
                 * Check stock.
                 */
                if ($qty > $food['AvailableQuantity']) {
                    throw new Exception(
                        $food['FoodName'] .
                        " has only " .
                        $food['AvailableQuantity'] .
                        " available."
                    );
                }

                /*
                 * Calculate subtotal.
                 */
                $subtotal = $food['Price'] * $qty;

                $total_amount += $subtotal;

                $order_items[] = [
                    'FoodID' => $food['FoodID'],
                    'FoodName' => $food['FoodName'],
                    'Qty' => $qty,
                    'Price' => $food['Price'],
                    'Subtotal' => $subtotal
                ];
            }

            if (empty($order_items)) {
                throw new Exception("Please select at least one food item.");
            }

            /* =========================
               CHECK WALLET
               ========================= */

            /*
             * Get the latest wallet balance.
             */
            $stmt = $pdo->prepare("
                SELECT WalletBalance
                FROM STUDENT
                WHERE UserID = ?
                FOR UPDATE
            ");

            $stmt->execute([$student_id]);
            $wallet_balance = $stmt->fetchColumn();

            if ($wallet_balance === false) {
                throw new Exception("Student wallet not found.");
            }

            if ($wallet_balance < $total_amount) {
                throw new Exception(
                    "Insufficient wallet balance. " .
                    "You need ৳" . number_format($total_amount, 2) .
                    " but your balance is ৳" .
                    number_format($wallet_balance, 2) . "."
                );
            }

            /* =========================
               CREATE SALE
               ========================= */

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
                    ?,
                    CURDATE(),
                    CURTIME(),
                    ?,
                    ?,
                    'Pending',
                    ?,
                    ?,
                    0.00,
                    ?
                )
            ");

            $stmt->execute([
                $student_id,
                $counter_id,
                $pickup_time,
                $total_amount,
                $total_amount,
                $payment_method
            ]);

            /*
             * Get the newly created SaleID.
             */
            $sale_id = $pdo->lastInsertId();

            /* =========================
               INSERT SALE ITEMS
               ========================= */

            $item_no = 1;

            foreach ($order_items as $item) {

                $stmt = $pdo->prepare("
                    INSERT INTO SALE_ITEM
                    (
                        SaleID,
                        ItemNo,
                        FoodID,
                        QtyPurchased,
                        Subtotal
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");

                $stmt->execute([
                    $sale_id,
                    $item_no,
                    $item['FoodID'],
                    $item['Qty'],
                    $item['Subtotal']
                ]);

                $item_no++;
            }

            /* =========================
               REDUCE FOOD STOCK
               ========================= */

            foreach ($order_items as $item) {

                $stmt = $pdo->prepare("
                    UPDATE COUNTER_SERVES_FOOD
                    SET AvailableQuantity = AvailableQuantity - ?
                    WHERE CounterID = ?
                    AND FoodID = ?
                ");

                $stmt->execute([
                    $item['Qty'],
                    $counter_id,
                    $item['FoodID']
                ]);
            }

            /* =========================
               DEDUCT WALLET BALANCE
               ========================= */

            $stmt = $pdo->prepare("
                UPDATE STUDENT
                SET WalletBalance = WalletBalance - ?
                WHERE UserID = ?
            ");

            $stmt->execute([
                $total_amount,
                $student_id
            ]);

            /* =========================
               WALLET TRANSACTION
               ========================= */

            $stmt = $pdo->prepare("
                INSERT INTO WALLET_TRANSACTION
                (
                    StudentID,
                    Amount,
                    TransactionType
                )
                VALUES
                (
                    ?,
                    ?,
                    'Payment'
                )
            ");

            $stmt->execute([
                $student_id,
                $total_amount
            ]);

            /* =========================
               EVERYTHING SUCCESSFUL
               ========================= */

            $pdo->commit();

            $message =
                "Order placed successfully! Your Order ID is #" .
                $sale_id .
                ". Total: ৳" .
                number_format($total_amount, 2);

            /*
             * Update displayed wallet balance.
             */
            $student['WalletBalance'] =
                $wallet_balance - $total_amount;

        } catch (Exception $e) {

            /*
             * If something goes wrong,
             * undo everything.
             */
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}

/* =========================
   GET COUNTERS
   ========================= */
$stmt = $pdo->query("
    SELECT
        CounterID,
        CounterName,
        Status
    FROM COUNTER
    WHERE Status = 'Active'
    ORDER BY CounterID
");

$counters = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   GET FOOD AVAILABLE AT COUNTERS
   ========================= */
$stmt = $pdo->query("
    SELECT
        C.CounterID,
        C.AvailableQuantity,
        F.FoodID,
        F.FoodName,
        F.Category,
        F.Price
    FROM COUNTER_SERVES_FOOD C
    JOIN FOOD F
        ON C.FoodID = F.FoodID
    JOIN COUNTER CT
        ON C.CounterID = CT.CounterID
    WHERE CT.Status = 'Active'
    ORDER BY C.CounterID, F.Category, F.FoodName
");

$foods = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * Group food by counter.
 */
$foods_by_counter = [];

foreach ($foods as $food) {

    $counter_id = $food['CounterID'];

    if (!isset($foods_by_counter[$counter_id])) {
        $foods_by_counter[$counter_id] = [];
    }

    $foods_by_counter[$counter_id][] = $food;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Order Food - Smart Cafeteria</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            color: #333;
        }

        .header {
            background: #1f2937;
            color: white;
            padding: 18px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .header a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
        }

        .container {
            width: 90%;
            max-width: 1100px;
            margin: 30px auto;
        }

        .top-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .wallet {
            font-size: 22px;
            font-weight: bold;
            margin-top: 10px;
        }

        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .counter-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .counter-card h2 {
            margin-top: 0;
        }

        .food-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .food-table th,
        .food-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        .food-table th {
            background: #f3f4f6;
        }

        .quantity-input {
            width: 80px;
            padding: 8px;
        }

        .out-of-stock {
            color: #dc2626;
            font-weight: bold;
        }

        .available {
            color: #059669;
        }

        .order-box {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-top: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        select,
        input[type="time"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        .submit-btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 13px 25px;
            border-radius: 7px;
            font-size: 16px;
            cursor: pointer;
        }

        .submit-btn:hover {
            background: #1d4ed8;
        }

        .note {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #2563eb;
            text-decoration: none;
        }

    </style>

</head>

<body>

<!-- =========================
     HEADER
     ========================= -->

<div class="header">

    <h1>Smart Cafeteria</h1>

    <div>
        Welcome, <?php echo htmlspecialchars($student['Name']); ?>

        <a href="student_dashboard.php">
            Dashboard
        </a>

        <a href="logout.php">
            Logout
        </a>
    </div>

</div>


<div class="container">

    <!-- =========================
         STUDENT WALLET
         ========================= -->

    <div class="top-card">

        <h2>🍽️ Order Food</h2>

        <p>
            Select the food you want to order from the available counters.
        </p>

        <div class="wallet">

            Wallet Balance:
            ৳<?php echo number_format($student['WalletBalance'], 2); ?>

        </div>

    </div>


    <!-- =========================
         SUCCESS MESSAGE
         ========================= -->

    <?php if ($message): ?>

        <div class="success">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>


    <!-- =========================
         ERROR MESSAGE
         ========================= -->

    <?php if ($error): ?>

        <div class="error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <form method="POST">


        <!-- =========================
             COUNTER SELECTION
             ========================= -->

        <div class="order-box">

            <h2>1. Select Pickup Counter</h2>

            <div class="form-group">

                <label for="counter_id">
                    Pickup Counter
                </label>

                <select
                    name="counter_id"
                    id="counter_id"
                    required
                >

                    <option value="">
                        -- Select Counter --
                    </option>

                    <?php foreach ($counters as $counter): ?>

                        <option
                            value="<?php echo $counter['CounterID']; ?>"
                        >

                            <?php echo htmlspecialchars($counter['CounterName']); ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>


        <!-- =========================
             FOOD LIST
             ========================= -->

        <div class="order-box">

            <h2>2. Select Food</h2>

            <p class="note">
                Enter the quantity you want for each food item.
                Leave it as 0 if you don't want that item.
            </p>


            <?php foreach ($counters as $counter): ?>

                <?php

                $counter_id = $counter['CounterID'];

                $counter_foods =
                    $foods_by_counter[$counter_id] ?? [];

                ?>

                <div
                    class="counter-card"
                    data-counter="<?php echo $counter_id; ?>"
                >

                    <h2>
                        <?php
                        echo htmlspecialchars(
                            $counter['CounterName']
                        );
                        ?>
                    </h2>


                    <?php if (empty($counter_foods)): ?>

                        <p>
                            No food is currently assigned to this counter.
                        </p>

                    <?php else: ?>

                        <table class="food-table">

                            <thead>

                                <tr>

                                    <th>Food</th>

                                    <th>Category</th>

                                    <th>Price</th>

                                    <th>Available</th>

                                    <th>Quantity</th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($counter_foods as $food): ?>

                                    <tr>

                                        <td>
                                            <?php
                                            echo htmlspecialchars(
                                                $food['FoodName']
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo htmlspecialchars(
                                                $food['Category']
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            ৳<?php
                                            echo number_format(
                                                $food['Price'],
                                                2
                                            );
                                            ?>
                                        </td>

                                        <td>

                                            <?php
                                            $available =
                                                intval(
                                                    $food['AvailableQuantity']
                                                );
                                            ?>

                                            <?php if ($available <= 0): ?>

                                                <span class="out-of-stock">
                                                    Out of Stock
                                                </span>

                                            <?php else: ?>

                                                <span class="available">
                                                    <?php
                                                    echo $available;
                                                    ?>
                                                    available
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>

                                            <?php if ($available > 0): ?>

                                                <input
                                                    type="hidden"
                                                    name="food_id[]"
                                                    value="<?php
                                                    echo $food['FoodID'];
                                                    ?>"
                                                >

                                                <input
                                                    type="number"
                                                    name="quantity[]"
                                                    class="quantity-input"
                                                    value="0"
                                                    min="0"
                                                    max="<?php
                                                    echo $available;
                                                    ?>"
                                                >

                                            <?php else: ?>

                                                <span>
                                                    -
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>


        <!-- =========================
             PICKUP TIME
             ========================= -->

        <div class="order-box">

            <h2>3. Pickup Information</h2>

            <div class="form-group">

                <label for="pickup_time">
                    Pickup Time
                </label>

                <input
                    type="time"
                    name="pickup_time"
                    id="pickup_time"
                    required
                >

            </div>


            <div class="form-group">

                <label for="payment_method">
                    Payment Method
                </label>

                <select
                    name="payment_method"
                    id="payment_method"
                    required
                >

                    <option value="Mobile Banking">
                        Mobile Banking / Wallet
                    </option>

                </select>

            </div>

        </div>


        <!-- =========================
             SUBMIT
             ========================= -->

        <div class="order-box">

            <h2>4. Place Order</h2>

            <p>
                Your wallet will be charged after the order is successfully
                placed.
            </p>

            <button
                type="submit"
                name="place_order"
                class="submit-btn"
            >
                Place Order
            </button>

        </div>

    </form>


    <a
        href="student_dashboard.php"
        class="back-link"
    >
        ← Back to Student Dashboard
    </a>

</div>

</body>

</html>