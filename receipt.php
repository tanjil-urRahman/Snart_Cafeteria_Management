<?php

session_start();
require_once 'db.php';


if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit();
}


if (!isset($_GET['sale_id'])) {

    header("Location: sales_billing.php");
    exit();
}


$sale_id =
    intval($_GET['sale_id']);




  // GET SALE FROM DATABASE


$stmt = $pdo->prepare("
    SELECT

        s.SaleID,
        s.StudentID,
        s.SaleDate,
        s.SaleTime,
        s.TotalAmount,

        c.CounterName

    FROM SALE s

    LEFT JOIN COUNTER c
        ON s.PickupCounterID =
           c.CounterID

    WHERE s.SaleID = ?
");


$stmt->execute([
    $sale_id
]);


$sale =
    $stmt->fetch(PDO::FETCH_ASSOC);


if (!$sale) {

    die("Sale not found.");
}

/* Only the student who placed the order, or Staff/Admin, may view it */
if (
    $_SESSION['role'] === 'Student' &&
    (int)$sale['StudentID'] !== (int)$_SESSION['user_id']
) {
    die("You are not authorized to view this receipt.");
}




  // GET SALE ITEMS


$stmt = $pdo->prepare("
    SELECT

        f.FoodName,
        f.Price,

        si.QtyPurchased,
        si.Subtotal

    FROM SALE_ITEM si

    INNER JOIN FOOD f
        ON si.FoodID = f.FoodID

    WHERE si.SaleID = ?

    ORDER BY si.ItemNo
");


$stmt->execute([
    $sale_id
]);


$items =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html>

<head>

<title>Receipt</title>

<style>

body {
    font-family: Arial;
}

.receipt {
    width: 350px;
    margin: 30px auto;
}

.center {
    text-align: center;
}

table {
    width: 100%;
}

td,
th {
    padding: 6px;
}

.right {
    text-align: right;
}

.total {
    font-size: 20px;
    font-weight: bold;
    text-align: right;
}

button {
    width: 100%;
    padding: 10px;
    margin-top: 15px;
}

@media print {

    button {
        display: none;
    }

}

</style>

</head>


<body>


<div class="receipt">


<div class="center">

<h2>SMART CAFETERIA</h2>

<p>University Cafeteria</p>

</div>


<hr>


<p>

Bill No:
<strong>
<?= $sale['SaleID'] ?>
</strong>

<br>


Counter:
<strong>

<?= htmlspecialchars(
    $sale['CounterName']
) ?>

</strong>

<br>


Date:
<?= $sale['SaleDate'] ?>

<br>


Time:
<?= $sale['SaleTime'] ?>

</p>


<hr>


<table>


<tr>

<th>Food</th>

<th>Qty</th>

<th>Amount</th>

</tr>


<?php foreach ($items as $item): ?>


<tr>


<td>

<?= htmlspecialchars(
    $item['FoodName']
) ?>

</td>


<td>

<?= $item['QtyPurchased'] ?>

</td>


<td class="right">

৳<?= number_format(
    $item['Subtotal'],
    2
) ?>

</td>


</tr>


<?php endforeach; ?>


</table>


<hr>


<div class="total">

TOTAL:

৳<?= number_format(
    $sale['TotalAmount'],
    2
) ?>

</div>


<hr>


<div class="center">

Thank You!

</div>


<button
onclick="window.print()"
>

Print Receipt

</button>


<button
onclick="
window.location.href='sales_billing.php'
"
>

New Sale

</button>


</div>


</body>

</html>