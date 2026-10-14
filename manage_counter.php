<?php

session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/*
    Only Staff and Admin can manage counters.
*/

if ($_SESSION['role'] !== 'Staff' && $_SESSION['role'] !== 'Admin') {

    switch ($_SESSION['role']) {

        case 'Student':
            header("Location: dashboard.php");
            exit();

        case 'Faculty':
            header("Location: faculty_dashboard.php");
            exit();

        default:
            header("Location: login.php");
            exit();
    }
}

$role = $_SESSION['role'];

$message = "";
$error = "";


/* =========================
   ADD COUNTER
========================= */







/* =========================
   GET COUNTERS
========================= */

$stmt = $pdo->query("
    SELECT
        CounterID,
        CounterName,
        Status,
        Location
    FROM COUNTER
    ORDER BY CounterID ASC
");

$counters = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<title>Manage Counters - Smart Cafeteria</title>

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

.header a {

    color: white;

    text-decoration: none;

    background: #dc3545;

    padding: 9px 15px;

    border-radius: 5px;
}

.container {

    width: 90%;

    max-width: 1100px;

    margin: 35px auto;
}

.card {

    background: white;

    padding: 25px;

    border-radius: 10px;

    margin-bottom: 25px;

    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

input,
select {

    width: 100%;

    padding: 10px;

    margin-top: 6px;

    margin-bottom: 15px;

    border: 1px solid #ccc;

    border-radius: 5px;
}

button {

    border: none;

    padding: 10px 15px;

    border-radius: 5px;

    cursor: pointer;
}

.add-btn {

    background: #007bff;

    color: white;
}

.delete-btn {

    background: #dc3545;

    color: white;
}

table {

    width: 100%;

    border-collapse: collapse;
}

th,
td {

    padding: 12px;

    border-bottom: 1px solid #ddd;

    text-align: left;
}

th {

    background: #f1f1f1;
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

.back {

    display: inline-block;

    margin-bottom: 20px;

    text-decoration: none;

    color: #007bff;
}

</style>

</head>

<body>


<div class="header">

    <h2>Smart Cafeteria — Counter Management</h2>

    <a href="logout.php">
        Logout
    </a>

</div>


<div class="container">

    <a
        class="back"
        href="<?= $role === 'Staff'
            ? 'staff_dashboard.php'
            : 'admin_dashboard.php' ?>"
    >
        ← Back to Dashboard
    </a>


    <?php if ($message): ?>

        <div class="success">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- ADD COUNTER -->

    <div class="card">

        <h2>➕ Add Counter</h2>

        <form method="POST">

            <label>
                Counter Name
            </label>

            <input
                type="text"
                name="counter_name"
                placeholder="Example: Main Counter"
                required
            >


            <label>
                Status
            </label>

            <select name="status">

                <option value="Active">
                    Active
                </option>

                <option value="Inactive">
                    Inactive
                </option>

                <option value="Maintenance">
                    Maintenance
                </option>

            </select>


            <label>
                Location
            </label>

            <input
                type="text"
                name="location"
                placeholder="Example: Ground Floor"
                required
            >


            <button
                type="submit"
                name="add_counter"
                class="add-btn"
            >
                Add Counter
            </button>

        </form>

    </div>


    <!-- CURRENT COUNTERS -->

    <div class="card">

        <h2>🏪 Current Counters</h2>

        <table>

            <tr>

                <th>ID</th>

                <th>Counter</th>

                <th>Status</th>

                <th>Location</th>

                <th>Action</th>

            </tr>


            <?php if (count($counters) > 0): ?>

                <?php foreach ($counters as $counter): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($counter['CounterID']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($counter['CounterName']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($counter['Status']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($counter['Location']) ?>
                        </td>

                        <td>

                            <form
                                method="POST"
                                onsubmit="return confirm('Remove this counter?');"
                            >

                                <input
                                    type="hidden"
                                    name="counter_id"
                                    value="<?= $counter['CounterID'] ?>"
                                >

                                <button
                                    type="submit"
                                    name="delete_counter"
                                    class="delete-btn"
                                >
                                    Remove
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td colspan="5">
                        No counters have been added yet.
                    </td>

                </tr>

            <?php endif; ?>

        </table>

    </div>

</div>

</body>

</html>