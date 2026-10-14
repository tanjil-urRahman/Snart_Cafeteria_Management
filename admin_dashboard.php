<?php

session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit();

}

/* Only Admins can access this dashboard */
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

$name = $_SESSION['name'];
$email = $_SESSION['email'];
$role = $_SESSION['role'];

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| HANDLE PENDING RESERVATION
|--------------------------------------------------------------------------
*/

$totalCafeteriaSeats = (int)$pdo->query("
    SELECT COALESCE(SUM(Capacity), 0)
    FROM CAFETERIA_TABLE
    WHERE Status = 'Active'
")->fetchColumn();

if (isset($_POST['reservation_action'])) {

    $reservationID = (int)($_POST['reservation_id'] ?? 0);
    $action = $_POST['reservation_action'] ?? '';

    if ($action === 'reject') {

        $stmt = $pdo->prepare("
            UPDATE SEAT_RESERVATION
            SET Status = 'Rejected'
            WHERE ReservationID = ?
              AND Status = 'Pending'
        ");

        $stmt->execute([$reservationID]);

        $success =
            $stmt->rowCount() > 0
            ? "Reservation rejected."
            : "Reservation was already processed.";

    } elseif ($action === 'confirm') {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT *
                FROM SEAT_RESERVATION
                WHERE ReservationID = ?
                  AND Status = 'Pending'
                FOR UPDATE
            ");

            $stmt->execute([$reservationID]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservation) {
                throw new Exception(
                    "Reservation not found or already processed."
                );
            }

            $canConfirm = false;

            if ($reservation['ReservationType'] === 'Group') {

                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM SEAT_RESERVATION
                    WHERE TableID = ?
                      AND ReservationDate = ?
                      AND Status = 'Confirmed'
                      AND ReservationID <> ?
                      AND StartTime < ?
                      AND EndTime > ?
                ");

                $stmt->execute([
                    $reservation['TableID'],
                    $reservation['ReservationDate'],
                    $reservationID,
                    $reservation['EndTime'],
                    $reservation['StartTime']
                ]);

                $canConfirm =
                    (int)$stmt->fetchColumn() === 0;

            } else {

                $stmt = $pdo->prepare("
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
                    WHERE R.ReservationDate = ?
                      AND R.Status = 'Confirmed'
                      AND R.ReservationID <> ?
                      AND R.StartTime < ?
                      AND R.EndTime > ?
                ");

                $stmt->execute([
                    $reservation['ReservationDate'],
                    $reservationID,
                    $reservation['EndTime'],
                    $reservation['StartTime']
                ]);

                $usedSeats = (int)$stmt->fetchColumn();

                $availableSeats =
                    max(0, $totalCafeteriaSeats - $usedSeats);

                $canConfirm =
                    $availableSeats >= (int)$reservation['SeatCount'];
            }

            if (!$canConfirm) {
                throw new Exception(
                    "Space is still not available for this reservation."
                );
            }

            $stmt = $pdo->prepare("
                UPDATE SEAT_RESERVATION
                SET Status = 'Confirmed'
                WHERE ReservationID = ?
                  AND Status = 'Pending'
            ");

            $stmt->execute([$reservationID]);

            $pdo->commit();

            $success =
                "Reservation confirmed successfully.";

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET PENDING RESERVATIONS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        R.ReservationID,
        U.Name AS StudentName,
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
    JOIN STUDENT S
        ON R.StudentID = S.UserID
    JOIN `USER` U
        ON S.UserID = U.UserID
    LEFT JOIN CAFETERIA_TABLE CT
        ON R.TableID = CT.TableID
    WHERE R.Status = 'Pending'
    ORDER BY
        R.ReservationDate ASC,
        R.StartTime ASC
");

$pendingReservations =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| CURRENT SEAT AVAILABILITY
|--------------------------------------------------------------------------
*/

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

$currentAvailableSeats =
    max(0, $totalCafeteriaSeats - $currentReservedSeats);


/*
|--------------------------------------------------------------------------
| QUICK KPI SNAPSHOT (live from the database)
|--------------------------------------------------------------------------
*/

$snapshot = $pdo->query(
    "SELECT
        COALESCE(SUM(CASE WHEN SaleDate = CURDATE() THEN TotalAmount END), 0) AS TodayRevenue,
        COUNT(CASE WHEN SaleDate = CURDATE() THEN 1 END) AS TodayOrders
     FROM SALE
     WHERE OrderStatus <> 'Cancelled'"
)->fetch(PDO::FETCH_ASSOC);

$lowStockCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM COUNTER_SERVES_FOOD WHERE AvailableQuantity <= 10"
)->fetchColumn();

$totalUsers = (int) $pdo->query(
    "SELECT COUNT(*) FROM `USER`"
)->fetchColumn();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Admin Dashboard - Smart Cafeteria</title>

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

            background: #91b6db;

            color: white;

            padding: 18px 30px;

            display: flex;

            justify-content: space-between;

            align-items: center;
        }

        .header h2 {
            margin: 0;
        }

        .logout {

            color: white;

            background: #dc3545;

            text-decoration: none;

            padding: 9px 15px;

            border-radius: 5px;
        }

        .container {

            width: 90%;

            max-width: 1100px;

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

        .cards {

            display: grid;

            grid-template-columns:
                repeat(auto-fit, minmax(220px, 1fr));

            gap: 20px;

            margin-top: 25px;
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

        .card a {

            display: inline-block;

            margin-top: 10px;

            background: #007bff;

            color: white;

            padding: 9px 14px;

            border-radius: 5px;

            text-decoration: none;
        }

        .info {

            color: #555;

            line-height: 1.8;
        }

        .section {

            background: white;

            padding: 25px;

            border-radius: 10px;

            margin-bottom: 25px;

            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        table {

            width: 100%;

            border-collapse: collapse;

            margin-top: 15px;
        }

        th {

            background: #343a40;

            color: white;

            padding: 10px;

            text-align: left;
        }

        td {

            padding: 10px;

            border-bottom: 1px solid #ddd;
        }

        .confirm {

            background: #28a745;

            color: white;

            border: none;

            padding: 7px 12px;

            border-radius: 5px;

            cursor: pointer;

            margin-right: 5px;
        }

        .reject {

            background: #dc3545;

            color: white;

            border: none;

            padding: 7px 12px;

            border-radius: 5px;

            cursor: pointer;
        }

        .success {

            background: #d4edda;

            color: #155724;

            padding: 12px;

            border-radius: 5px;

            margin-bottom: 20px;
        }

        .error {

            background: #f8d7da;

            color: #721c24;

            padding: 12px;

            border-radius: 5px;

            margin-bottom: 20px;
        }

        .notice {

            background: #fff3cd;

            color: #856404;

            padding: 15px;

            border-radius: 5px;

            margin-bottom: 15px;
        }

    </style>

</head>

<body>


<div class="header">

    <h2>Smart Cafeteria — Admin</h2>

    <a class="logout" href="logout.php">
        Logout
    </a>
    <a href="staff_management.php">Staff & Shift Management</a>
<a href="attendance.php">Attendance Management</a>
<a href="staff_tasks.php">Task Management</a>
<a href="staff_performance.php">Staff Performance</a>
<a href="order_management.php">Online Order Management</a>
</div>


<div class="container">


    <div class="welcome">

        <h1>
            Welcome, <?= htmlspecialchars($name) ?>!
        </h1>

        <div class="info">

            <strong>Email:</strong>
            <?= htmlspecialchars($email) ?>

            <br>

            <strong>Role:</strong>
            <?= htmlspecialchars($role) ?>

        </div>

    </div>


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
         QUICK KPI SNAPSHOT
    ====================================================== -->

    <div class="cards" style="margin-bottom:25px;">

        <div class="card">
            <h3>💰 Today's Revenue</h3>
            <p style="font-size:1.5rem;font-weight:bold;color:#28a745;">
                ৳<?= number_format($snapshot['TodayRevenue'], 2) ?>
            </p>
            <p><?= $snapshot['TodayOrders'] ?> orders today</p>
        </div>

        <div class="card">
            <h3>⚠️ Low Stock Items</h3>
            <p style="font-size:1.5rem;font-weight:bold;color:<?= $lowStockCount > 0 ? '#dc3545' : '#28a745' ?>;">
                <?= $lowStockCount ?>
            </p>
            <a href="admin_reports.php">View report</a>
        </div>

        <div class="card">
            <h3>🪑 Seats Available Now</h3>

            <p style="
                font-size:1.5rem;
                font-weight:bold;
                color:#28a745;
            ">
                <?= $currentAvailableSeats ?>
                /
                <?= $totalCafeteriaSeats ?>
            </p>

            <p>
                <?= $currentReservedSeats ?>
                seats currently reserved
            </p>
        </div>

        <div class="card">
            <h3>🔔 Pending Reservations</h3>
            <p style="font-size:1.5rem;font-weight:bold;">
                <?= count($pendingReservations) ?>
            </p>
        </div>

        <div class="card">
            <h3>👥 Total Users</h3>
            <p style="font-size:1.5rem;font-weight:bold;">
                <?= $totalUsers ?>
            </p>
        </div>

    </div>


    <!-- =====================================================
         SEAT RESERVATION MANAGEMENT
    ====================================================== -->

    <div class="section">

        <h2>
            🔔 Seat Reservation Requests
        </h2>

        <div class="notice">

            Pending requests appear when space was not available automatically. Confirm them later when space becomes available.

        </div>


        <?php if (empty($pendingReservations)): ?>

            <p>
                No pending seat reservations.
            </p>

        <?php else: ?>

            <table>

                <tr>
                    <th>Student</th>
                    <th>Type</th>
                    <th>Seats / Table</th>
                    <th>Date</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Purpose</th>
                    <th>Action</th>
                </tr>

                <?php foreach ($pendingReservations as $reservation): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($reservation['StudentName']) ?>
                        </td>

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

                        <td>

                            <form
                                method="POST"
                                style="display:inline;"
                            >

                                <input
                                    type="hidden"
                                    name="reservation_id"
                                    value="<?= (int)$reservation['ReservationID'] ?>"
                                >

                                <button
                                    type="submit"
                                    name="reservation_action"
                                    value="confirm"
                                    class="confirm"
                                >
                                    Confirm
                                </button>

                            </form>

                            <form
                                method="POST"
                                style="display:inline;"
                            >

                                <input
                                    type="hidden"
                                    name="reservation_id"
                                    value="<?= (int)$reservation['ReservationID'] ?>"
                                >

                                <button
                                    type="submit"
                                    name="reservation_action"
                                    value="reject"
                                    class="reject"
                                >
                                    Reject
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </table>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         EXISTING ADMIN FEATURES
    ====================================================== -->

    <div class="cards">


        <div class="card">

            <h3>👥 User Management</h3>

            <p>
                Create, view, and manage Admin and Staff accounts.
            </p>

            <a href="create_user.php">
                Manage Users
            </a>

        </div>


        <div class="card">

            <h3>🍽️ Food & Menu</h3>

            <p>
                Add or update food items, pricing and counter stock.
            </p>

            <a href="manage_menu.php">
                Manage Menu
            </a>

        </div>


        <div class="card">

            <h3>🏪 Counters</h3>

            <p>
                Add, edit, and remove cafeteria counters.
            </p>

            <a href="manage_counter.php">
                Manage Counters
            </a>

        </div>


        <div class="card">

            <h3>📦 Inventory & Suppliers</h3>

            <p>
                Track ingredients, prep batches, waste, and supplier deliveries.
            </p>

            <a href="inventory_suppliers.php">
                Manage Inventory
            </a>

        </div>


        <div class="card">

            <h3>📊 Business Analytics</h3>

            <p>
                Revenue, profit, peak-hour analysis, top sellers & more.
            </p>

            <a href="admin_reports.php">
                View Reports
            </a>

        </div>


        <div class="card">

            <h3>⭐ Reviews</h3>

            <p>
                Monitor food and staff reviews.
            </p>

            <a href="reviews_admin.php">
                View Reviews
            </a>

        </div>


        <div class="card">

            <h3>🧾 Sales & Billing</h3>

            <p>
                Run the checkout counter for walk-in sales.
            </p>

            <a href="sales_billing.php">
                Open Billing
            </a>

        </div>


        <div class="card">

            <h3>🪑 Seat Requests</h3>

            <p>
                Full list of seat reservation requests and history.
            </p>

            <a href="seat_requests.php">
                View Seat Requests
            </a>

        </div>


    </div>

</div>

</body>

</html>