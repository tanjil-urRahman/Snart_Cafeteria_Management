<?php

session_start();

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit();

}

// Only Faculty can access this dashboard
if ($_SESSION['role'] !== 'Faculty') {

    switch ($_SESSION['role']) {

        case 'Student':
            header("Location: dashboard.php");
            exit();

        case 'Staff':
            header("Location: staff_dashboard.php");
            exit();

        case 'Admin':
            header("Location: admin_dashboard.php");
            exit();

        default:
            header("Location: login.php");
            exit();
    }
}

$name = $_SESSION['name'];
$email = $_SESSION['email'];
$role = $_SESSION['role'];

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Faculty Dashboard - Smart Cafeteria</title>

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

        .logout {

            color: white;

            background: #dc3545;

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

        .cards {

            display: grid;

            grid-template-columns:
                repeat(auto-fit, minmax(220px, 1fr));

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

    </style>

</head>

<body>

<div class="header">

    <h2>Smart Cafeteria — Faculty</h2>

    <a class="logout" href="logout.php">
        Logout
    </a>

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


    <div class="cards">

        <div class="card">

            <h3>🍛 Food Menu</h3>

            <p>
                View available food and prices.
            </p>

            <a href="index.php">
                View Menu
            </a>

        </div>


        <div class="card">

            <h3>🪑 Seat Reservation</h3>

            <p>
                Reserve a seat for meetings or events.
            </p>

            <a href="#">
                Coming Soon
            </a>

        </div>


        <div class="card">

            <h3>⭐ Reviews</h3>

            <p>
                Review food and cafeteria staff.
            </p>

            <a href="#">
                Coming Soon
            </a>

        </div>

    </div>

</div>

</body>

</html>