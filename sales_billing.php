<?php

session_start();
require_once 'db.php';


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


if (!isset($_SESSION['bill'])) {
    $_SESSION['bill'] = [];
}


$error = "";


/* =================================================
   SELECT COUNTER
================================================= */

$counter_id =
    isset($_GET['counter'])
    ? intval($_GET['counter'])
    : 0;



/* =================================================
   ADD FOOD TO BILL
================================================= */

if (isset($_POST['add_food'])) {

    $counter_id =
        intval($_POST['counter_id']);

    $food_id =
        intval($_POST['food_id']);

    $qty =
        intval($_POST['qty']);


    $stmt = $pdo->prepare("
        SELECT

            f.FoodID,
            f.FoodName,
            f.Price,

            csf.AvailableQuantity

        FROM COUNTER_SERVES_FOOD csf

        INNER JOIN FOOD f
            ON csf.FoodID = f.FoodID

        WHERE csf.CounterID = ?
        AND csf.FoodID = ?
    ");


    $stmt->execute([
        $counter_id,
        $food_id
    ]);


    $food =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$food) {

        $error =
            "Food is not available at this counter.";

    } elseif (
        $qty <= 0 ||
        $qty > $food['AvailableQuantity']
    ) {

        $error =
            "Invalid quantity.";

    } else {


        $found = false;


        foreach (
            $_SESSION['bill']
            as $index => $item
        ) {


            if (
                $item['FoodID'] ==
                $food['FoodID']
            ) {


                $new_qty =
                    $item['Qty'] + $qty;


                if (
                    $new_qty >
                    $food['AvailableQuantity']
                ) {

                    $error =
                        "Not enough stock.";

                } else {

                    $_SESSION['bill'][$index]['Qty']
                        = $new_qty;


                    $_SESSION['bill'][$index]['Subtotal']
                        =
                        $new_qty
                        *
                        $item['Price'];
                }


                $found = true;

                break;
            }
        }


        if (!$found) {

            $_SESSION['bill'][] = [

                'FoodID' =>
                    $food['FoodID'],

                'FoodName' =>
                    $food['FoodName'],

                'Price' =>
                    $food['Price'],

                'Qty' =>
                    $qty,

                'Subtotal' =>
                    $food['Price'] * $qty
            ];
        }
    }
}



/* =================================================
   REMOVE BILL ITEM
================================================= */

if (isset($_GET['remove'])) {

    $index =
        intval($_GET['remove']);


    if (
        isset($_SESSION['bill'][$index])
    ) {

        unset(
            $_SESSION['bill'][$index]
        );


        $_SESSION['bill'] =
            array_values(
                $_SESSION['bill']
            );
    }


    header(
        "Location: sales_billing.php?counter="
        . $counter_id
    );

    exit();
}



/* =================================================
   COMPLETE SALE
================================================= */

if (isset($_POST['complete_sale'])) {


    $counter_id =
        intval($_POST['counter_id']);


    if (
        empty($_SESSION['bill'])
    ) {

        $error = "Bill is empty.";

    } else {


        try {


            $pdo->beginTransaction();


            $total = 0;


            /* Check actual database stock */

            foreach (
                $_SESSION['bill']
                as $item
            ) {


                $stmt = $pdo->prepare("
                    SELECT AvailableQuantity

                    FROM COUNTER_SERVES_FOOD

                    WHERE CounterID = ?
                    AND FoodID = ?

                    FOR UPDATE
                ");


                $stmt->execute([

                    $counter_id,

                    $item['FoodID']

                ]);


                $stock =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (!$stock) {

                    throw new Exception(
                        "Food not found."
                    );
                }


                if (
                    $item['Qty']
                    >
                    $stock['AvailableQuantity']
                ) {

                    throw new Exception(
                        "Not enough stock for "
                        . $item['FoodName']
                    );
                }


                $total +=
                    $item['Subtotal'];
            }



            /* ==================================
               INSERT SALE
            ================================== */


            $stmt = $pdo->prepare("
                INSERT INTO SALE
                (
                    StudentID,
                    SaleDate,
                    SaleTime,
                    PickupCounterID,
                    PickupTime,
                    OrderStatus,
                    TotalAmount
                )

                VALUES
                (
                    NULL,
                    CURDATE(),
                    CURTIME(),
                    ?,
                    CURTIME(),
                    'Completed',
                    ?
                )
            ");


            $stmt->execute([
                $counter_id,
                $total
            ]);


            $sale_id =
                $pdo->lastInsertId();



            /* ==================================
               INSERT ITEMS AND REDUCE STOCK
            ================================== */


            $item_no = 1;


            foreach (
                $_SESSION['bill']
                as $item
            ) {


                $stmt =
                    $pdo->prepare("
                        INSERT INTO SALE_ITEM
                        (
                            SaleID,
                            ItemNo,
                            FoodID,
                            QtyPurchased,
                            Subtotal
                        )

                        VALUES
                        (?, ?, ?, ?, ?)
                    ");


                $stmt->execute([

                    $sale_id,

                    $item_no,

                    $item['FoodID'],

                    $item['Qty'],

                    $item['Subtotal']

                ]);



                /* REDUCE ACTUAL DATABASE STOCK */

                $stmt =
                    $pdo->prepare("
                        UPDATE COUNTER_SERVES_FOOD

                        SET AvailableQuantity =
                            AvailableQuantity - ?

                        WHERE CounterID = ?

                        AND FoodID = ?
                    ");


                $stmt->execute([

                    $item['Qty'],

                    $counter_id,

                    $item['FoodID']

                ]);


                $item_no++;
            }


            $pdo->commit();


            $_SESSION['bill'] = [];


            header(
                "Location: receipt.php?sale_id="
                . $sale_id
            );


            exit();


        } catch (Exception $e) {


            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            $error =
                $e->getMessage();
        }
    }
}



/* =================================================
   GET COUNTERS
================================================= */

$stmt =
    $pdo->query("
        SELECT
            CounterID,
            CounterName

        FROM COUNTER

        WHERE Status = 'Active'

        ORDER BY CounterID
    ");


$counters =
    $stmt->fetchAll(PDO::FETCH_ASSOC);



/* =================================================
   GET FOOD FOR SELECTED COUNTER
================================================= */

$foods = [];


if ($counter_id > 0) {


    $stmt =
        $pdo->prepare("
            SELECT

                f.FoodID,
                f.FoodName,
                f.Price,

                csf.AvailableQuantity

            FROM COUNTER_SERVES_FOOD csf

            INNER JOIN FOOD f
                ON csf.FoodID = f.FoodID

            WHERE csf.CounterID = ?

            AND csf.AvailableQuantity > 0

            ORDER BY f.FoodName
        ");


    $stmt->execute([
        $counter_id
    ]);


    $foods =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}



/* TOTAL */

$total = 0;


foreach (
    $_SESSION['bill']
    as $item
) {

    $total +=
        $item['Subtotal'];
}

?>

<!DOCTYPE html>

<html>

<head>

<title>Sales & Billing</title>

<style>

body {
    font-family: Arial;
    background: #f4f6f9;
}

.container {
    width: 900px;
    margin: 30px auto;
}

.box {
    background: white;
    padding: 20px;
    margin-bottom: 20px;
}

.counter {
    display: inline-block;
    background: #007bff;
    color: white;
    padding: 12px;
    margin: 5px;
    text-decoration: none;
}

input,
select {
    padding: 10px;
}

button {
    padding: 10px 15px;
    background: #28a745;
    color: white;
    border: none;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    border: 1px solid #ccc;
    padding: 10px;
}

th {
    background: #343a40;
    color: white;
}

.total {
    font-size: 22px;
    text-align: right;
    margin-top: 15px;
}

</style>

</head>


<body>


<div class="container">


<a href="<?= $_SESSION['role'] === 'Admin' ? 'admin_dashboard.php' : 'staff_dashboard.php' ?>">
← Dashboard
</a>


<h1>Sales & Billing</h1>



<?php if ($error): ?>

<p style="color:red">

<?= htmlspecialchars($error) ?>

</p>

<?php endif; ?>



<!-- COUNTERS -->

<div class="box">

<h2>Select Counter</h2>


<?php foreach ($counters as $counter): ?>


<a
class="counter"
href="sales_billing.php?counter=<?= $counter['CounterID'] ?>"
>

<?= htmlspecialchars(
    $counter['CounterName']
) ?>

</a>


<?php endforeach; ?>


</div>



<?php if ($counter_id > 0): ?>


<!-- FOOD -->

<div class="box">


<h2>Add Food</h2>


<form method="POST">


<input
type="hidden"
name="counter_id"
value="<?= $counter_id ?>"
>


<select name="food_id" required>


<option value="">
Select Food
</option>


<?php foreach ($foods as $food): ?>


<option
value="<?= $food['FoodID'] ?>"
>

<?= htmlspecialchars(
    $food['FoodName']
) ?>

|

৳<?= $food['Price'] ?>

|

Stock:
<?= $food['AvailableQuantity'] ?>

</option>


<?php endforeach; ?>


</select>


<input
type="number"
name="qty"
min="1"
placeholder="Quantity"
required
>


<button
type="submit"
name="add_food"
>

Add

</button>


</form>

</div>


<?php endif; ?>



<!-- BILL -->

<div class="box">


<h2>Current Bill</h2>


<table>


<tr>

<th>Food</th>

<th>Qty</th>

<th>Price</th>

<th>Subtotal</th>

<th>Action</th>

</tr>


<?php foreach (
    $_SESSION['bill']
    as $index => $item
): ?>


<tr>


<td>
<?= htmlspecialchars(
    $item['FoodName']
) ?>
</td>


<td>
<?= $item['Qty'] ?>
</td>


<td>
৳<?= number_format(
    $item['Price'],
    2
) ?>
</td>


<td>
৳<?= number_format(
    $item['Subtotal'],
    2
) ?>
</td>


<td>

<a href="
sales_billing.php?counter=
<?= $counter_id ?>
&remove=
<?= $index ?>
">

Remove

</a>

</td>


</tr>


<?php endforeach; ?>


</table>


<div class="total">

Total:
৳<?= number_format(
    $total,
    2
) ?>

</div>


<?php if (
    !empty($_SESSION['bill'])
): ?>


<form method="POST">


<input
type="hidden"
name="counter_id"
value="<?= $counter_id ?>"
>


<button
type="submit"
name="complete_sale"
>

Complete Sale & Print Receipt

</button>


</form>


<?php endif; ?>


</div>


</div>

</body>

</html>