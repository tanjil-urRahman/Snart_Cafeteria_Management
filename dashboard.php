<?php

session_start();
require 'db.php';

/*
|--------------------------------------------------------------------------
| CHECK LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit();

}

/*
|--------------------------------------------------------------------------
| CHECK STUDENT
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Student') {

    switch ($_SESSION['role'] ?? '') {

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

$studentID = $_SESSION['user_id'];

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| ADD MONEY TO WALLET
|--------------------------------------------------------------------------
*/

if (isset($_POST['add_money'])) {

    $amount = $_POST['amount'] ?? '';

    if (!is_numeric($amount) || $amount <= 0) {

        $error = "Please enter a valid amount.";

    } else {

        try {

            $pdo->beginTransaction();

            /*
            |------------------------------------------------------------------
            | Update wallet balance
            |------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                "UPDATE STUDENT
                 SET WalletBalance = WalletBalance + ?
                 WHERE UserID = ?"
            );

            $stmt->execute([
                $amount,
                $studentID
            ]);

            /*
            |------------------------------------------------------------------
            | Save wallet transaction
            |------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                "INSERT INTO WALLET_TRANSACTION
                (StudentID, Amount, TransactionType)
                VALUES (?, ?, 'Deposit')"
            );

            $stmt->execute([
                $studentID,
                $amount
            ]);

            $pdo->commit();

            $success = "Money added successfully.";

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("Wallet error: " . $e->getMessage());

            $error = "Could not add money.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| SIMPLE SEAT RESERVATION
|--------------------------------------------------------------------------
| Individual = 1 to 3 seats
| Group      = one full cafeteria table
| If space is available -> Confirmed
| If not available       -> Pending
*/
$totalCafeteriaSeats = (int)$pdo->query("
SELECT COALESCE(SUM(Capacity), 0)
FROM CAFETERIA_TABLE
WHERE Status = 'Active'
")->fetchColumn();

if (isset($_POST['reserve_seat'])) {


$reservationType = $_POST['reservation_type'] ?? 'Individual';
$seatCount = (int)($_POST['seat_count'] ?? 1);
$tableID = !empty($_POST['table_id']) ? (int)$_POST['table_id'] : null;

$reservationDate = $_POST['reservation_date'] ?? '';
$startTime = $_POST['start_time'] ?? '';
$endTime = $_POST['end_time'] ?? '';
$purpose = $_POST['purpose'] ?? 'Regular Dining';

if (
    !in_array($reservationType, ['Individual', 'Group'], true) ||
    $reservationDate === '' ||
    $startTime === '' ||
    $endTime === ''
) {

    $error = "Please fill in all reservation fields.";

} elseif ($endTime <= $startTime) {

    $error = "End time must be after start time.";

} elseif ($reservationDate < date('Y-m-d')) {

    $error = "Please select a valid reservation date.";

} elseif (
    $reservationType === 'Individual' &&
    ($seatCount < 1 || $seatCount > 3)
) {

    $error = "Individual reservation allows maximum 3 seats.";

} elseif (
    $reservationType === 'Group' &&
    !$tableID
) {

    $error = "Please select a table for the group reservation.";

} else {

    try {

        $pdo->beginTransaction();

        if ($reservationType === 'Individual') {

            // Reservations are ALWAYS submitted as Pending.
            // Only an Admin can move a reservation to Confirmed
            // (see admin_dashboard.php), which re-checks capacity
            // at approval time.
            $status = 'Pending';

            $stmt = $pdo->prepare("
                INSERT INTO SEAT_RESERVATION
                (
                    StudentID,
                    ReservationType,
                    SeatCount,
                    TableID,
                    TableNumber,
                    SeatNumber,
                    ReservationDate,
                    StartTime,
                    EndTime,
                    Purpose,
                    Status
                )
                VALUES
                (
                    ?,
                    'Individual',
                    ?,
                    NULL,
                    NULL,
                    NULL,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $studentID,
                $seatCount,
                $reservationDate,
                $startTime,
                $endTime,
                $purpose,
                $status
            ]);

        } else {

            $stmt = $pdo->prepare("
                SELECT
                    TableID,
                    TableName,
                    Capacity
                FROM CAFETERIA_TABLE
                WHERE TableID = ?
                  AND Status = 'Active'
            ");

            $stmt->execute([
                $tableID
            ]);

            $table =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$table) {

                throw new Exception(
                    "Selected table is not available."
                );
            }

            $tableCapacity =
                (int)$table['Capacity'];

            // Reservations are ALWAYS submitted as Pending.
            // Only an Admin can move a reservation to Confirmed
            // (see admin_dashboard.php), which re-checks capacity
            // and table conflicts at approval time.
            $status = 'Pending';

            $stmt = $pdo->prepare("
                INSERT INTO SEAT_RESERVATION
                (
                    StudentID,
                    ReservationType,
                    SeatCount,
                    TableID,
                    TableNumber,
                    SeatNumber,
                    ReservationDate,
                    StartTime,
                    EndTime,
                    Purpose,
                    Status
                )
                VALUES
                (
                    ?,
                    'Group',
                    ?,
                    ?,
                    ?,
                    NULL,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $studentID,
                $tableCapacity,
                $tableID,
                $tableID,
                $reservationDate,
                $startTime,
                $endTime,
                $purpose,
                $status
            ]);
        }

        $pdo->commit();

        $success =
            "Your reservation request has been submitted and is now Pending admin approval.";

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {

            $pdo->rollBack();
        }

        error_log(
            "Reservation error: " .
            $e->getMessage()
        );

        $error =
            "Could not create reservation.";
    }
}


}




/*
|--------------------------------------------------------------------------
| GET STUDENT INFORMATION
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        U.Name,
        U.Email,
        U.Phone,
        S.Department,
        S.Batch,
        S.WalletBalance
     FROM `USER` U
     JOIN STUDENT S
        ON U.UserID = S.UserID
     WHERE U.UserID = ?"
);

$stmt->execute([
    $studentID
]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$student) {

    session_destroy();

    header("Location: login.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| GET ALL COUNTERS
|--------------------------------------------------------------------------
|
| Counter 1, Counter 2, Counter 3, Counter 4
|
*/

$stmt = $pdo->query(
    "SELECT
        CounterID,
        CounterName,
        Status
     FROM COUNTER
     ORDER BY CounterID"
);

$counters = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| GET FOOD FOR EACH COUNTER
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| CounterID comes from COUNTER.
|
| FoodName and Category come from FOOD.
|
| AvailableQuantity comes from COUNTER_SERVES_FOOD.
|
| Therefore the quantity shown on the student dashboard is the
| REAL quantity stored in the database.
|
*/

$counterFoods = [];

foreach ($counters as $counter) {

    $stmt = $pdo->prepare(
        "SELECT
            F.FoodID,
            F.FoodName,
            F.Category,
            F.Price,
            C.AvailableQuantity
         FROM COUNTER_SERVES_FOOD C
         JOIN FOOD F
            ON C.FoodID = F.FoodID
         WHERE C.CounterID = ?
         ORDER BY
            F.Category,
            F.FoodName"
    );

    $stmt->execute([
        $counter['CounterID']
    ]);

    $counterFoods[$counter['CounterID']] =
        $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| GET TODAY'S DEMAND FOR EACH COUNTER
|--------------------------------------------------------------------------
|
| Demand = total number of food items purchased today.
|
*/

$counterDemand = [];

foreach ($counters as $counter) {

    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(SI.QtyPurchased), 0) AS TotalDemand
         FROM SALE S
         JOIN SALE_ITEM SI
            ON S.SaleID = SI.SaleID
         WHERE S.PickupCounterID = ?
         AND S.SaleDate = CURDATE()
         AND S.OrderStatus = 'Completed'"
    );

    $stmt->execute([
        $counter['CounterID']
    ]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $counterDemand[$counter['CounterID']] =
        $result['TotalDemand'];
}


/*
|--------------------------------------------------------------------------
| GET PEAK HOUR FOR EACH COUNTER
|--------------------------------------------------------------------------
|
| Finds the hour with the highest number of food items
| sold today at each counter.
|
*/

$counterPeakHour = [];

foreach ($counters as $counter) {

    $stmt = $pdo->prepare(
        "SELECT
            HOUR(S.SaleTime) AS PeakHour,
            SUM(SI.QtyPurchased) AS TotalDemand
         FROM SALE S
         JOIN SALE_ITEM SI
            ON S.SaleID = SI.SaleID
         WHERE S.PickupCounterID = ?
         AND S.SaleDate = CURDATE()
         AND S.OrderStatus = 'Completed'
         GROUP BY HOUR(S.SaleTime)
         ORDER BY TotalDemand DESC
         LIMIT 1"
    );

    $stmt->execute([
        $counter['CounterID']
    ]);

    $counterPeakHour[$counter['CounterID']] =
        $stmt->fetch(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| CAFETERIA TABLES + LIVE AVAILABILITY
|--------------------------------------------------------------------------
*/

$cafeteriaTables = $pdo->query("
    SELECT
        TableID,
        TableName,
        Capacity,
        Status
    FROM CAFETERIA_TABLE
    WHERE Status = 'Active'
    ORDER BY TableID
")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT COALESCE(
        SUM(
            CASE
                WHEN R.ReservationType = 'Group'
                    THEN CT.Capacity
                ELSE R.SeatCount
            END
        ),
        0
    )
    FROM SEAT_RESERVATION R
    LEFT JOIN CAFETERIA_TABLE CT
        ON R.TableID = CT.TableID
    WHERE R.ReservationDate = CURDATE()
      AND R.Status = 'Confirmed'
      AND R.StartTime <= CURTIME()
      AND R.EndTime > CURTIME()
");

$currentReservedSeats = (int)$stmt->fetchColumn();
$currentAvailableSeats = max(0, $totalCafeteriaSeats - $currentReservedSeats);


/*
|--------------------------------------------------------------------------
| GET STUDENT RESERVATIONS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        R.ReservationID,
        R.ReservationType,
        R.SeatCount,
        R.TableID,
        CT.TableName,
        R.ReservationDate,
        R.StartTime,
        R.EndTime,
        R.Purpose,
        R.Status
    FROM SEAT_RESERVATION R
    LEFT JOIN CAFETERIA_TABLE CT
        ON R.TableID = CT.TableID
    WHERE R.StudentID = ?
    ORDER BY
        R.ReservationDate DESC,
        R.StartTime DESC
    LIMIT 10
");

$stmt->execute([$studentID]);

$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| GET WALLET TRANSACTIONS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        Amount,
        TransactionType,
        TransactionDate
     FROM WALLET_TRANSACTION
     WHERE StudentID = ?
     ORDER BY TransactionDate DESC
     LIMIT 5"
);

$stmt->execute([
    $studentID
]);

$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Dashboard - Smart Cafeteria</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            font-family: Arial, sans-serif;

            background: #f4f6f9;

            color: #333;
        }

        /* HEADER */

        .header {

            background: #007bff;

            color: white;

            padding: 18px 30px;

            display: flex;

            justify-content: space-between;

            align-items: center;
        }

        .header h2 {
            margin: 0;
        }

        .header-right {

            display: flex;

            align-items: center;

            gap: 20px;
        }

        .logout {

            background: white;

            color: #007bff;

            padding: 8px 15px;

            border-radius: 5px;

            text-decoration: none;

            font-weight: bold;
        }

        .logout:hover {
            background: #eeeeee;
        }

        /* MAIN CONTAINER */

        .container {

            width: 95%;

            max-width: 1200px;

            margin: 25px auto;
        }

        /* WELCOME */

        .welcome {

            background: white;

            padding: 20px;

            border-radius: 8px;

            margin-bottom: 20px;

            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .welcome h3 {

            margin-top: 0;

            margin-bottom: 10px;
        }

        .welcome p {

            margin: 6px 0;
        }

        /* MESSAGES */

        .success {

            background: #d4edda;

            color: #155724;

            padding: 12px;

            border-radius: 5px;

            margin-bottom: 15px;
        }

        .error {

            background: #f8d7da;

            color: #721c24;

            padding: 12px;

            border-radius: 5px;

            margin-bottom: 15px;
        }

        /* SECTIONS */

        .section {
            margin-top: 25px;
        }

        .section h2 {
            margin-bottom: 15px;
        }

        /* COUNTERS */

        .counter-container {

            display: grid;

            grid-template-columns: repeat(2, 1fr);

            gap: 20px;
        }

        .counter-card {

            background: white;

            padding: 20px;

            border-radius: 8px;

            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .counter-card h3 {

            margin-top: 0;

            color: #007bff;

            font-size: 22px;
        }

        .counter-info {

            line-height: 1.7;

            margin-bottom: 15px;
        }

        /* COUNTER STATUS */

        .active {

            color: green;

            font-weight: bold;
        }

        .inactive {

            color: red;

            font-weight: bold;
        }

        .maintenance {

            color: orange;

            font-weight: bold;
        }

        /* FOOD TABLE */

        table {

            width: 100%;

            border-collapse: collapse;

            background: white;
        }

        th {

            background: #007bff;

            color: white;

            padding: 10px;

            text-align: left;
        }

        td {

            padding: 10px;

            border-bottom: 1px solid #ddd;
        }

        /* FOOD AVAILABILITY */

        .available {

            color: green;

            font-weight: bold;
        }

        .low {

            color: orange;

            font-weight: bold;
        }

        .out {

            color: red;

            font-weight: bold;
        }

        /* CATEGORY */

        .category-title {

            margin-top: 15px;

            margin-bottom: 8px;

            font-size: 15px;

            font-weight: bold;

            color: #555;

            border-bottom: 1px solid #ddd;

            padding-bottom: 5px;
        }

        .category-title:first-child {
            margin-top: 0;
        }

        /* TWO COLUMNS */

        .two-column {

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 20px;
        }

        /* GENERAL CARD */

        .card {

            background: white;

            padding: 20px;

            border-radius: 8px;

            box-shadow: 0 2px 8px rgba(0,0,0,0.08);

            margin-bottom: 20px;
        }

        .card h2 {
            margin-top: 0;
        }

        /* WALLET */

        .balance {

            font-size: 30px;

            font-weight: bold;

            margin: 15px 0;

            color: #007bff;
        }

        /* FORM */

        input,
        select,
        textarea {

            width: 100%;

            padding: 10px;

            margin-top: 5px;

            margin-bottom: 12px;

            border: 1px solid #ccc;

            border-radius: 5px;
        }

        label {
            font-weight: bold;
        }

        button {

            background: #007bff;

            color: white;

            border: none;

            padding: 10px 18px;

            border-radius: 5px;

            cursor: pointer;
        }

        button:hover {
            background: #0069d9;
        }

        /* RESERVATION STATUS */

        .status {
            font-weight: bold;
        }

        /* FEEDBACK */

        textarea {

            height: 120px;

            resize: vertical;
        }

        /* MOBILE */

        @media (max-width: 900px) {

            .counter-container {
                grid-template-columns: 1fr;
            }

            .two-column {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>


<!-- =========================================================
     HEADER
========================================================= -->

<div class="header">

    <h2>
        Smart Cafeteria
    </h2>

    <div class="header-right">

        <span>
            <?= htmlspecialchars($student['Name']) ?>
        </span>

        <a
            class="logout"
            href="logout.php"
        >
            Logout
        </a>

    </div>

</div>


<div class="container">


    <!-- =====================================================
         MESSAGES
    ====================================================== -->

    <?php if ($success): ?>

        <div class="success">

            <?= htmlspecialchars($success) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         WELCOME
    ====================================================== -->

    <div class="welcome">

        <h3>

            Welcome,
            <?= htmlspecialchars($student['Name']) ?>!

        </h3>

        <p>

            <strong>Department:</strong>

            <?= htmlspecialchars($student['Department']) ?>

        </p>

        <p>

            <strong>Batch:</strong>

            <?= htmlspecialchars($student['Batch']) ?>

        </p>

        <p>

            <strong>Email:</strong>

            <?= htmlspecialchars($student['Email']) ?>

        </p>

        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:15px;">
            <a href="order.php" style="background:#28a745;color:white;padding:9px 14px;border-radius:5px;text-decoration:none;">🍽️ Order Food</a>
            <a href="reviews.php" style="background:#007bff;color:white;padding:9px 14px;border-radius:5px;text-decoration:none;">⭐ Reviews</a>
            <a href="wallet.php" style="background:#007bff;color:white;padding:9px 14px;border-radius:5px;text-decoration:none;">💰 Wallet History</a>
        </div>

    </div>


    <!-- =====================================================
         COUNTERS AND FOOD
    ====================================================== -->

    <div class="section">

        <h2>
            Cafeteria Counters
        </h2>

        <p>
            Food availability at each cafeteria counter.
        </p>


        <div class="counter-container">


            <?php foreach ($counters as $counter): ?>

                <div class="counter-card">


                    <!-- COUNTER NAME -->

                    <h3>

                        <?= htmlspecialchars(
                            $counter['CounterName']
                        ) ?>

                    </h3>


                    <!-- COUNTER STATUS -->

                    <div class="counter-info">

                        <strong>
                            Status:
                        </strong>


                        <?php if ($counter['Status'] === 'Active'): ?>

                            <span class="active">
                                Active
                            </span>

                        <?php elseif ($counter['Status'] === 'Maintenance'): ?>

                            <span class="maintenance">
                                Maintenance
                            </span>

                        <?php else: ?>

                            <span class="inactive">
                                Inactive
                            </span>

                        <?php endif; ?>

                        <br>


                        <strong>
                            Today's Demand:
                        </strong>

                        <?= htmlspecialchars(
                            $counterDemand[$counter['CounterID']]
                        ) ?>

                        items


                        <br>


                        <strong>
                            Today's Peak Hour:
                        </strong>


                        <?php

                        $peak =
                            $counterPeakHour[
                                $counter['CounterID']
                            ];

                        ?>


                        <?php if ($peak): ?>

                            <?= date(
                                "h:i A",
                                strtotime(
                                    $peak['PeakHour'] . ":00"
                                )
                            ) ?>

                            <br>

                            <strong>
                                Peak Demand:
                            </strong>

                            <?= htmlspecialchars(
                                $peak['TotalDemand']
                            ) ?>

                            items

                        <?php else: ?>

                            <span>
                                No sales data yet
                            </span>

                        <?php endif; ?>

                    </div>


                    <!-- =================================================
                         FOOD AT THIS COUNTER
                    ================================================== -->

                    <h4>
                        Available Food
                    </h4>


                    <?php

                    $foods =
                        $counterFoods[
                            $counter['CounterID']
                        ] ?? [];

                    ?>


                    <?php if (empty($foods)): ?>

                        <p>
                            No food is currently assigned to this counter.
                        </p>


                    <?php else: ?>


                        <?php

                        /*
                        |--------------------------------------------------------------------------
                        | GROUP FOOD BY CATEGORY
                        |--------------------------------------------------------------------------
                        |
                        | Example:
                        |
                        | Counter 1
                        |
                        | Snacks
                        | Samosa       10
                        | Singara      15
                        |
                        | Meals
                        | Khichuri     10
                        |
                        */

                        $foodsByCategory = [];

                        foreach ($foods as $food) {

                            $category =
                                $food['Category'] ?? 'Other';

                            $foodsByCategory[$category][] =
                                $food;
                        }

                        ?>


                        <?php foreach (
                            $foodsByCategory
                            as $category => $categoryFoods
                        ): ?>


                            <div class="category-title">

                                <?= htmlspecialchars(
                                    $category
                                ) ?>

                            </div>


                            <table>

                                <tr>

                                    <th>
                                        Food
                                    </th>

                                    <th>
                                        Price
                                    </th>

                                    <th>
                                        Quantity
                                    </th>

                                </tr>


                                <?php foreach (
                                    $categoryFoods
                                    as $food
                                ): ?>


                                    <tr>

                                        <td>

                                            <?= htmlspecialchars(
                                                $food['FoodName']
                                            ) ?>

                                        </td>


                                        <td>

                                            ৳<?= number_format(
                                                $food['Price'],
                                                2
                                            ) ?>

                                        </td>


                                        <td>

                                            <?php

                                            $quantity =
                                                (int)$food[
                                                    'AvailableQuantity'
                                                ];

                                            ?>


                                            <?php if ($quantity <= 0): ?>

                                                <span class="out">
                                                    Out of Stock
                                                </span>


                                            <?php elseif ($quantity <= 5): ?>

                                                <span class="low">

                                                    <?= $quantity ?>

                                                    left

                                                </span>


                                            <?php else: ?>

                                                <span class="available">

                                                    <?= $quantity ?>

                                                    available

                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>


                                <?php endforeach; ?>


                            </table>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>


            <?php endforeach; ?>


        </div>

    </div>


    <!-- =====================================================
         WALLET + SEAT RESERVATION
    ====================================================== -->

    <div class="section">

        <div class="two-column">


            <!-- =================================================
                 WALLET
            ================================================== -->

            <div class="card">

                <h2>
                    My Wallet
                </h2>

                <p>
                    Current Balance
                </p>

                <div class="balance">

                    ৳<?= number_format(
                        $student['WalletBalance'],
                        2
                    ) ?>

                </div>


                <h3>
                    Add Money
                </h3>


                <form method="POST">

                    <label>
                        Amount
                    </label>

                    <input
                        type="number"
                        name="amount"
                        step="0.01"
                        min="1"
                        placeholder="Enter amount"
                        required
                    >

                    <button
                        type="submit"
                        name="add_money"
                    >
                        Add Money
                    </button>

                </form>


                <h3>
                    Recent Transactions
                </h3>


                <?php if (empty($transactions)): ?>

                    <p>
                        No transactions yet.
                    </p>


                <?php else: ?>


                    <table>

                        <tr>

                            <th>
                                Type
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Date
                            </th>

                        </tr>


                        <?php foreach (
                            $transactions
                            as $transaction
                        ): ?>


                            <tr>

                                <td>

                                    <?= htmlspecialchars(
                                        $transaction['TransactionType']
                                    ) ?>

                                </td>


                                <td>

                                    ৳<?= number_format(
                                        $transaction['Amount'],
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    <?= date(
                                        "d M Y",
                                        strtotime(
                                            $transaction['TransactionDate']
                                        )
                                    ) ?>

                                </td>

                            </tr>


                        <?php endforeach; ?>


                    </table>


                <?php endif; ?>


            </div>


            <!-- =================================================
                 SEAT RESERVATION
            ================================================== -->

            <div class="card">

                <h2>Reserve Cafeteria Space</h2>

                <div style="
                    background:#e8f4fd;
                    padding:12px;
                    border-radius:6px;
                    margin-bottom:15px;
                ">
                    <strong>Total Seats:</strong>
                    <?= $totalCafeteriaSeats ?>

                    &nbsp; | &nbsp;

                    <strong>Reserved Now:</strong>
                    <?= $currentReservedSeats ?>

                    &nbsp; | &nbsp;

                    <strong>Available Now:</strong>
                    <?= $currentAvailableSeats ?>
                </div>

                <form method="POST">

                    <label>Reservation Type</label>

                    <select
                        name="reservation_type"
                        id="reservation_type"
                        onchange="toggleReservationType()"
                        required
                    >
                        <option value="Individual">
                            Individual (1-3 seats)
                        </option>

                        <option value="Group">
                            Group / Club / Meeting
                        </option>
                    </select>

                    <div id="individual_fields">

                        <label>Number of Seats</label>

                        <select name="seat_count">
                            <option value="1">1 Seat</option>
                            <option value="2">2 Seats</option>
                            <option value="3">3 Seats</option>
                        </select>

                    </div>

                    <div
                        id="group_fields"
                        style="display:none;"
                    >

                        <label>Select Table</label>

                        <select name="table_id">

                            <option value="">
                                -- Select Table --
                            </option>

                            <?php foreach ($cafeteriaTables as $table): ?>

                                <option value="<?= (int)$table['TableID'] ?>">

                                    <?= htmlspecialchars($table['TableName']) ?>

                                    (<?= (int)$table['Capacity'] ?> seats)

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <label>Reservation Date</label>

                    <input
                        type="date"
                        name="reservation_date"
                        min="<?= date('Y-m-d') ?>"
                        required
                    >

                    <label>Start Time</label>

                    <input
                        type="time"
                        name="start_time"
                        required
                    >

                    <label>End Time</label>

                    <input
                        type="time"
                        name="end_time"
                        required
                    >

                    <label>Purpose</label>

                    <select name="purpose">
                        <option value="Regular Dining">
                            Regular Dining
                        </option>

                        <option value="Club Activity">
                            Club Activity
                        </option>

                        <option value="Faculty Meeting">
                            Faculty Meeting
                        </option>

                        <option value="Event">
                            Event
                        </option>
                    </select>

                    <button
                        type="submit"
                        name="reserve_seat"
                    >
                        Reserve
                    </button>

                </form>

                <p style="
                    font-size:13px;
                    color:#666;
                    margin-top:12px;
                ">
                    All reservations are reviewed by an Admin before being
                    confirmed. You'll see the status update below once it's approved.
                </p>

            </div>

        </div>

    </div>


<!-- =====================================================
         MY RESERVATIONS
    ====================================================== -->

    <div class="section">

        <div class="card">

            <h2>
                My Seat Reservations
            </h2>


            <?php if (empty($reservations)): ?>

                <p>
                    You have no seat reservations.
                </p>


            <?php else: ?>


                <table>

                    <tr>
                        <th>Type</th>
                        <th>Seats / Table</th>
                        <th>Date</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Purpose</th>
                        <th>Status</th>
                    </tr>

                    <?php foreach ($reservations as $reservation): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($reservation['ReservationType']) ?>
                            </td>

                            <td>

                                <?php if ($reservation['ReservationType'] === 'Group'): ?>

                                    <?= htmlspecialchars($reservation['TableName'] ?? 'Table') ?>

                                    (<?= (int)$reservation['SeatCount'] ?> seats)

                                <?php else: ?>

                                    <?= (int)$reservation['SeatCount'] ?> seat(s)

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= htmlspecialchars($reservation['ReservationDate']) ?>
                            </td>

                            <td>
                                <?= date("h:i A", strtotime($reservation['StartTime'])) ?>
                            </td>

                            <td>
                                <?= date("h:i A", strtotime($reservation['EndTime'])) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($reservation['Purpose']) ?>
                            </td>

                            <td class="status">
                                <?= htmlspecialchars($reservation['Status']) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </table>


            <?php endif; ?>


        </div>

    </div>


    <!-- =====================================================
         GENERAL FEEDBACK
    ====================================================== -->

    <!-- =====================================================
     GENERAL FEEDBACK
====================================================== -->

<div class="section">

    <div class="card">

        <h2>
            General Feedback
        </h2>

        <p>
            We would like to hear your feedback about
            the cafeteria.
        </p>


        <a href="reviews.php" class="review-button" style="display:inline-block;background:#28a745;color:white;padding:10px 18px;border-radius:6px;text-decoration:none;">
            ⭐ Reviews
        </a>

        <p>
            <small>
                Your Feedback is valuable.
            </small>
        </p>

    </div>

</div>


</div>

<script>
function toggleReservationType() {

    const type =
        document.getElementById('reservation_type').value;

    document.getElementById('individual_fields').style.display =
        type === 'Individual'
        ? 'block'
        : 'none';

    document.getElementById('group_fields').style.display =
        type === 'Group'
        ? 'block'
        : 'none';
}
</script>

</body>

</html>