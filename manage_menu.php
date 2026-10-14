<?php
session_start();
require_once 'db.php';

/* ==================================================
   LOGIN CHECK
================================================== */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (
    $_SESSION['role'] !== 'Staff' &&
    $_SESSION['role'] !== 'Admin'
) {
    header("Location: login.php");
    exit();
}

$message = "";
$error = "";

$isAdmin = $_SESSION['role'] === 'Admin';
$isStaff = $_SESSION['role'] === 'Staff';
$userId  = (int)$_SESSION['user_id'];

/* ==================================================
   FIXED CATEGORY -> COUNTER MAP
================================================== */
$categoryCounterMap = [
    'Snacks'   => 1,
    'Meals'    => 2,
    'Drinks'   => 3,
    'Desserts' => 4
];

/* ==================================================
   GET STAFF ASSIGNED COUNTER
================================================== */
$assignedCounterID = null;
$assignedCounterName = null;

if ($isStaff) {
    $stmt = $pdo->prepare("
        SELECT
            st.CounterID,
            c.CounterName
        FROM STAFF st
        LEFT JOIN COUNTER c
            ON st.CounterID = c.CounterID
        WHERE st.UserID = ?
    ");
    $stmt->execute([$userId]);
    $staffCounter = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($staffCounter && !empty($staffCounter['CounterID'])) {
        $assignedCounterID = (int)$staffCounter['CounterID'];
        $assignedCounterName = $staffCounter['CounterName'];
    }
}

/* ==================================================
   ADMIN: ADD NEW FOOD
   CATEGORY AUTOMATICALLY DECIDES COUNTER
================================================== */
if ($isAdmin && isset($_POST['add_food'])) {

    $food_name = trim($_POST['food_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $cost_price = floatval($_POST['cost_price'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);

    if (
        $food_name === "" ||
        !isset($categoryCounterMap[$category]) ||
        $price <= 0 ||
        $cost_price < 0 ||
        $quantity < 0
    ) {
        $error = "Please enter valid food information.";
    } else {

        $counter_id = $categoryCounterMap[$category];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO FOOD
                (
                    FoodName,
                    Category,
                    Price,
                    CostPrice
                )
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $food_name,
                $category,
                $price,
                $cost_price
            ]);

            $food_id = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO COUNTER_SERVES_FOOD
                (
                    CounterID,
                    FoodID,
                    AvailableQuantity
                )
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $counter_id,
                $food_id,
                $quantity
            ]);

            $pdo->commit();

            $message =
                "New food added successfully and automatically assigned to " .
                $category . " counter.";

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("Add food error: " . $e->getMessage());
            $error = "Could not add food.";
        }
    }
}

/* ==================================================
   STAFF: ADD STOCK
   ONLY TO FOOD ALREADY ASSIGNED TO THEIR COUNTER
================================================== */
if ($isStaff && isset($_POST['add_stock'])) {

    $food_id = intval($_POST['stock_food_id'] ?? 0);
    $quantity = intval($_POST['stock_quantity'] ?? 0);

    if (!$assignedCounterID) {

        $error = "You are not assigned to any counter.";

    } elseif ($food_id <= 0 || $quantity <= 0) {

        $error = "Please enter valid stock information.";

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
                $food_id
            ]);

            $existing = $stmt->fetchColumn();

            if ($existing === false) {

                $error =
                    "This food does not belong to your assigned counter.";

            } else {

                $stmt = $pdo->prepare("
                    UPDATE COUNTER_SERVES_FOOD
                    SET AvailableQuantity =
                        AvailableQuantity + ?
                    WHERE CounterID = ?
                      AND FoodID = ?
                ");

                $stmt->execute([
                    $quantity,
                    $assignedCounterID,
                    $food_id
                ]);

                $message =
                    "Stock added successfully to " .
                    $assignedCounterName . ".";
            }

        } catch (Exception $e) {

            error_log("Add stock error: " . $e->getMessage());
            $error = "Could not add stock.";
        }
    }
}

/* ==================================================
   GET FIXED COUNTERS 1-4 ONLY
================================================== */
$stmt = $pdo->query("
    SELECT
        CounterID,
        CounterName,
        Status,
        Location
    FROM COUNTER
    WHERE CounterID BETWEEN 1 AND 4
    ORDER BY CounterID
");

$counters =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==================================================
   GET ALL FOODS
================================================== */
$stmt = $pdo->query("
    SELECT
        FoodID,
        FoodName,
        Category,
        Price,
        CostPrice
    FROM FOOD
    ORDER BY Category, FoodName
");

$foods =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==================================================
   STAFF FOOD LIST: ONLY ASSIGNED COUNTER FOODS
================================================== */
$staffFoods = [];

if ($isStaff && $assignedCounterID) {

    $stmt = $pdo->prepare("
        SELECT
            f.FoodID,
            f.FoodName,
            f.Category,
            f.Price,
            csf.AvailableQuantity
        FROM COUNTER_SERVES_FOOD csf
        INNER JOIN FOOD f
            ON csf.FoodID = f.FoodID
        WHERE csf.CounterID = ?
        ORDER BY f.Category, f.FoodName
    ");

    $stmt->execute([
        $assignedCounterID
    ]);

    $staffFoods =
        $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ==================================================
   GET CURRENT COUNTER STOCK
================================================== */
$stmt = $pdo->query("
    SELECT
        c.CounterID,
        c.CounterName,
        f.FoodID,
        f.FoodName,
        f.Category,
        f.Price,
        csf.AvailableQuantity
    FROM COUNTER_SERVES_FOOD csf
    INNER JOIN COUNTER c
        ON csf.CounterID = c.CounterID
    INNER JOIN FOOD f
        ON csf.FoodID = f.FoodID
    WHERE c.CounterID BETWEEN 1 AND 4
    ORDER BY
        c.CounterID,
        f.Category,
        f.FoodName
");

$stocks =
    $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Food & Stock Management</title>

<style>
body {
    font-family: Arial;
    background: #f4f6f9;
    margin: 0;
}

.container {
    width: 90%;
    max-width: 1100px;
    margin: 30px auto;
}

.box {
    background: white;
    padding: 25px;
    margin-bottom: 25px;
    border-radius: 6px;
}

input,
select {
    width: 100%;
    padding: 10px;
    margin-bottom: 12px;
    box-sizing: border-box;
}

button {
    background: #007bff;
    color: white;
    border: none;
    padding: 10px 16px;
    cursor: pointer;
    border-radius: 4px;
}

button:hover {
    background: #0056b3;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    border: 1px solid #ccc;
    padding: 10px;
    text-align: center;
}

th {
    background: #343a40;
    color: white;
}

.message {
    background: #d4edda;
    color: #155724;
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.error {
    background: #f8d7da;
    color: #721c24;
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.info {
    background: #e8f4fd;
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 4px;
}
</style>
</head>

<body>

<div class="container">

<p>
<a href="<?= $isAdmin
    ? 'admin_dashboard.php'
    : 'staff_dashboard.php' ?>">
← Back to Dashboard
</a>
</p>

<?php if ($message): ?>
<div class="message">
<?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="error">
<?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>


<?php if ($isAdmin): ?>

<!-- ==========================================
     ADMIN: ADD NEW FOOD
========================================== -->
<div class="box">

<h2>Add New Food</h2>

<p class="info">
Admin only adds the food and selects its category.
The system automatically assigns it to the correct counter:
<br><br>
<strong>
Snacks → Counter 1<br>
Meals → Counter 2<br>
Drinks → Counter 3<br>
Desserts → Counter 4
</strong>
</p>

<form method="POST">

<label>Food Name</label>
<input
    type="text"
    name="food_name"
    required
>

<label>Category</label>
<select
    name="category"
    required
>
<option value="">-- Select Category --</option>
<option value="Snacks">Snacks → Counter 1</option>
<option value="Meals">Meals → Counter 2</option>
<option value="Drinks">Drinks → Counter 3</option>
<option value="Desserts">Desserts → Counter 4</option>
</select>

<label>Selling Price</label>
<input
    type="number"
    name="price"
    step="0.01"
    min="0.01"
    required
>

<label>Cost Price</label>
<input
    type="number"
    name="cost_price"
    step="0.01"
    min="0"
    required
>

<label>Initial Quantity</label>
<input
    type="number"
    name="quantity"
    min="0"
    value="0"
    required
>

<button
    type="submit"
    name="add_food"
>
Add Food
</button>

</form>

</div>

<?php endif; ?>


<?php if ($isStaff): ?>

<!-- ==========================================
     STAFF: ADD STOCK ONLY TO ASSIGNED FOODS
========================================== -->
<div class="box">

<h2>Add Food Stock</h2>

<?php if (!$assignedCounterID): ?>

<p class="error">
You are not assigned to any counter.
Please contact Admin.
</p>

<?php else: ?>

<p class="info">
Assigned Counter:
<strong>
<?= htmlspecialchars($assignedCounterName) ?>
</strong>
<br>
You can only add stock to foods already designated to this counter.
</p>

<form method="POST">

<label>Select Food</label>

<select
    name="stock_food_id"
    required
>

<option value="">
-- Select Food --
</option>

<?php foreach ($staffFoods as $food): ?>

<option
    value="<?= $food['FoodID'] ?>"
>
<?= htmlspecialchars($food['FoodName']) ?>
-
<?= htmlspecialchars($food['Category']) ?>
-
Current: <?= (int)$food['AvailableQuantity'] ?>
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
>
Add Stock
</button>

</form>

<?php endif; ?>

</div>

<?php endif; ?>


<!-- ==========================================
    // CURRENT STOCK
========================================== -->
<div class="box">

<h2>Current Counter Stock</h2>

<table>

<tr>
<th>Counter</th>
<th>Food</th>
<th>Category</th>
<th>Price</th>
<th>Available Quantity</th>
</tr>

<?php if (empty($stocks)): ?>

<tr>
<td colspan="5">
No food has been assigned to any counter yet.
</td>
</tr>

<?php else: ?>

<?php foreach ($stocks as $stock): ?>

<tr>

<td>
<?= htmlspecialchars($stock['CounterName']) ?>
</td>

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
<strong>
<?= $stock['AvailableQuantity'] ?>
</strong>
</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</table>

</div>

</div>

</body>
</html>
