<?php

session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!in_array($_SESSION['role'], ['Admin', 'Staff'], true)) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];

$message = '';
$error = '';


/*
|--------------------------------------------------------------------------
| ADD NEW INGREDIENT
|--------------------------------------------------------------------------
*/

if (isset($_POST['add_ingredient'])) {

    $ingName = trim($_POST['ingredient_name'] ?? '');
    $qty = (float)($_POST['ingredient_qty'] ?? 0);
    $unit = trim($_POST['ingredient_unit'] ?? '');

    if ($ingName === '' || $unit === '' || $qty < 0) {

        $error = "Please provide a valid ingredient name, quantity and unit.";

    } else {

        $stmt = $pdo->prepare(
            "INSERT INTO INGREDIENT (IngredientName, AvailQty, Unit)
             VALUES (?, ?, ?)"
        );

        $stmt->execute([$ingName, $qty, $unit]);

        $message = "Ingredient added.";
    }
}


/*
|--------------------------------------------------------------------------
| ADD NEW SUPPLIER
|--------------------------------------------------------------------------
*/

if (isset($_POST['add_supplier'])) {

    $supplierName = trim($_POST['supplier_name'] ?? '');
    $phone = trim($_POST['supplier_phone'] ?? '');
    $address = trim($_POST['supplier_address'] ?? '');

    if ($supplierName === '' || $phone === '' || $address === '') {

        $error = "Please fill in all supplier fields.";

    } else {

        $stmt = $pdo->prepare(
            "INSERT INTO SUPPLIER (SupplierName, Phone, Address)
             VALUES (?, ?, ?)"
        );

        $stmt->execute([$supplierName, $phone, $address]);

        $message = "Supplier added.";
    }
}


/*
|--------------------------------------------------------------------------
| RECORD A SUPPLIER DELIVERY (increases ingredient stock)
|--------------------------------------------------------------------------
*/

if (isset($_POST['record_delivery'])) {

    $supplierId = (int)($_POST['delivery_supplier_id'] ?? 0);
    $ingredientId = (int)($_POST['delivery_ingredient_id'] ?? 0);
    $qty = (float)($_POST['delivery_qty'] ?? 0);
    $price = (float)($_POST['delivery_price'] ?? 0);

    if ($supplierId <= 0 || $ingredientId <= 0 || $qty <= 0 || $price < 0) {

        $error = "Please select a supplier, ingredient and a valid quantity/price.";

    } else {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO SUPPLIER_SUPPLIES_INGREDIENT
                    (SupplierID, IngredientID, DeliveryDate, QuantityDelivered, PurchasePrice)
                 VALUES (?, ?, CURDATE(), ?, ?)
                 ON DUPLICATE KEY UPDATE
                    QuantityDelivered = QuantityDelivered + VALUES(QuantityDelivered),
                    PurchasePrice = PurchasePrice + VALUES(PurchasePrice)"
            );

            $stmt->execute([$supplierId, $ingredientId, $qty, $price]);

            $stmt = $pdo->prepare(
                "UPDATE INGREDIENT
                 SET AvailQty = AvailQty + ?
                 WHERE IngredientID = ?"
            );

            $stmt->execute([$qty, $ingredientId]);

            $pdo->commit();

            $message = "Delivery recorded and ingredient stock updated.";

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("Delivery error: " . $e->getMessage());

            $error = "Could not record delivery. " . $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| LOG FOOD PREP (consumes an ingredient)
|--------------------------------------------------------------------------
*/

if (isset($_POST['log_prep'])) {

    $foodId = (int)($_POST['prep_food_id'] ?? 0);
    $qtyPrepared = (int)($_POST['prep_qty'] ?? 0);
    $ingredientId = (int)($_POST['prep_ingredient_id'] ?? 0);
    $qtyUsed = (float)($_POST['prep_ingredient_qty'] ?? 0);

    if ($foodId <= 0 || $qtyPrepared <= 0 || $ingredientId <= 0 || $qtyUsed <= 0) {

        $error = "Please fill in all prep-room fields with valid values.";

    } else {

        try {

            $pdo->beginTransaction();

            // Make sure enough ingredient is on hand
            $stmt = $pdo->prepare(
                "SELECT AvailQty FROM INGREDIENT WHERE IngredientID = ? FOR UPDATE"
            );

            $stmt->execute([$ingredientId]);

            $available = $stmt->fetchColumn();

            if ($available === false || $available < $qtyUsed) {

                throw new Exception("Not enough of that ingredient in stock.");
            }

            $stmt = $pdo->prepare(
                "INSERT INTO PREP_ROOM (FoodID, PrepDate, QuantityPrepared)
                 VALUES (?, CURDATE(), ?)"
            );

            $stmt->execute([$foodId, $qtyPrepared]);

            $prepId = $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                "INSERT INTO PREP_ROOM_USES_INGREDIENT (PrepID, IngredientID, QuantityUsed)
                 VALUES (?, ?, ?)"
            );

            $stmt->execute([$prepId, $ingredientId, $qtyUsed]);

            $stmt = $pdo->prepare(
                "UPDATE INGREDIENT SET AvailQty = AvailQty - ? WHERE IngredientID = ?"
            );

            $stmt->execute([$qtyUsed, $ingredientId]);

            $pdo->commit();

            $message = "Prep batch logged and ingredient stock updated.";

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
| LOG WASTE (reduces counter stock and records the reason)
|--------------------------------------------------------------------------
*/

if (isset($_POST['log_waste'])) {

    $counterId = (int)($_POST['waste_counter_id'] ?? 0);
    $foodId = (int)($_POST['waste_food_id'] ?? 0);
    $qty = (int)($_POST['waste_qty'] ?? 0);
    $reason = trim($_POST['waste_reason'] ?? '');

    if ($counterId <= 0 || $foodId <= 0 || $qty <= 0 || $reason === '') {

        $error = "Please select counter, food, a valid quantity and a reason.";

    } else {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT AvailableQuantity
                 FROM COUNTER_SERVES_FOOD
                 WHERE CounterID = ? AND FoodID = ?
                 FOR UPDATE"
            );

            $stmt->execute([$counterId, $foodId]);

            $stock = $stmt->fetchColumn();

            if ($stock === false || $stock < $qty) {

                throw new Exception("Not enough stock at that counter to mark as waste.");
            }

            $stmt = $pdo->prepare(
                "UPDATE COUNTER_SERVES_FOOD
                 SET AvailableQuantity = AvailableQuantity - ?
                 WHERE CounterID = ? AND FoodID = ?"
            );

            $stmt->execute([$qty, $counterId, $foodId]);

            $stmt = $pdo->prepare(
                "INSERT INTO WASTE (FoodID, WasteDate, Quantity, Reason)
                 VALUES (?, CURDATE(), ?, ?)"
            );

            $stmt->execute([$foodId, $qty, $reason]);

            $pdo->commit();

            $message = "Waste logged and counter stock adjusted.";

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
| DATA FOR THE PAGE
|--------------------------------------------------------------------------
*/

$ingredients = $pdo->query(
    "SELECT * FROM INGREDIENT ORDER BY IngredientName"
)->fetchAll(PDO::FETCH_ASSOC);

$suppliers = $pdo->query(
    "SELECT * FROM SUPPLIER ORDER BY SupplierName"
)->fetchAll(PDO::FETCH_ASSOC);

$foods = $pdo->query(
    "SELECT FoodID, FoodName FROM FOOD ORDER BY FoodName"
)->fetchAll(PDO::FETCH_ASSOC);

$counters = $pdo->query(
    "SELECT CounterID, CounterName FROM COUNTER ORDER BY CounterName"
)->fetchAll(PDO::FETCH_ASSOC);

$deliveries = $pdo->query(
    "SELECT ssi.DeliveryDate, s.SupplierName, i.IngredientName, ssi.QuantityDelivered, ssi.PurchasePrice
     FROM SUPPLIER_SUPPLIES_INGREDIENT ssi
     INNER JOIN SUPPLIER s ON ssi.SupplierID = s.SupplierID
     INNER JOIN INGREDIENT i ON ssi.IngredientID = i.IngredientID
     ORDER BY ssi.DeliveryDate DESC
     LIMIT 15"
)->fetchAll(PDO::FETCH_ASSOC);

$prepLog = $pdo->query(
    "SELECT pr.PrepDate, f.FoodName, pr.QuantityPrepared
     FROM PREP_ROOM pr
     INNER JOIN FOOD f ON pr.FoodID = f.FoodID
     ORDER BY pr.PrepDate DESC
     LIMIT 15"
)->fetchAll(PDO::FETCH_ASSOC);

$wasteLog = $pdo->query(
    "SELECT w.WasteDate, f.FoodName, w.Quantity, w.Reason
     FROM WASTE w
     INNER JOIN FOOD f ON w.FoodID = f.FoodID
     ORDER BY w.WasteDate DESC
     LIMIT 15"
)->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<title>Inventory & Suppliers - Smart Cafeteria</title>

<style>
* { box-sizing: border-box; }
body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; }
.header { background: #343a40; color: white; padding: 18px 30px; display: flex; justify-content: space-between; align-items: center; }
.header a { color: white; background: #007bff; text-decoration: none; padding: 9px 15px; border-radius: 5px; }
.container { width: 94%; max-width: 1300px; margin: 30px auto; }
.two-column { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 20px; }
.card { background: white; padding: 25px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.card h2 { margin-top: 0; }
label { display: block; margin-top: 10px; margin-bottom: 5px; font-weight: bold; font-size: 0.9rem; }
input, select, textarea { width: 100%; padding: 9px; border: 1px solid #ccc; border-radius: 5px; }
button { margin-top: 14px; background: #007bff; color: white; border: none; padding: 9px 16px; border-radius: 5px; cursor: pointer; }
button.danger { background: #dc3545; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th { background: #343a40; color: white; padding: 8px; text-align: left; font-size: 0.85rem; }
td { padding: 8px; border-bottom: 1px solid #eee; font-size: 0.85rem; }
.success { background: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin-bottom: 20px; }
.error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px; margin-bottom: 20px; }
.badge-low { background: #f8d7da; color: #721c24; padding: 2px 8px; border-radius: 10px; }
.empty { color: #888; }
</style>

</head>
<body>

<div class="header">
    <h2>📦 Inventory & Suppliers</h2>
    <a href="<?= $role === 'Admin' ? 'admin_dashboard.php' : 'staff_dashboard.php' ?>">
        ← Dashboard
    </a>
</div>

<div class="container">

    <?php if ($message): ?>
        <div class="success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="two-column">

        <!-- INGREDIENTS -->
        <div class="card">

            <h2>🧂 Ingredients</h2>

            <table>
                <tr><th>Name</th><th>Available</th><th>Unit</th></tr>
                <?php foreach ($ingredients as $i): ?>
                    <tr>
                        <td><?= htmlspecialchars($i['IngredientName']) ?></td>
                        <td>
                            <?php if ($i['AvailQty'] < 20): ?>
                                <span class="badge-low"><?= $i['AvailQty'] ?></span>
                            <?php else: ?>
                                <?= $i['AvailQty'] ?>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($i['Unit']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <h3>Add Ingredient</h3>

            <form method="POST">
                <label>Name</label>
                <input type="text" name="ingredient_name" required>

                <label>Starting Quantity</label>
                <input type="number" step="0.01" min="0" name="ingredient_qty" required>

                <label>Unit</label>
                <input type="text" name="ingredient_unit" placeholder="kg, Ltr, Pcs..." required>

                <button type="submit" name="add_ingredient">Add Ingredient</button>
            </form>

        </div>

        <!-- SUPPLIERS -->
        <div class="card">

            <h2>🚚 Suppliers</h2>

            <table>
                <tr><th>Name</th><th>Phone</th><th>Address</th></tr>
                <?php foreach ($suppliers as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['SupplierName']) ?></td>
                        <td><?= htmlspecialchars($s['Phone']) ?></td>
                        <td><?= htmlspecialchars($s['Address']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <h3>Add Supplier</h3>

            <form method="POST">
                <label>Supplier Name</label>
                <input type="text" name="supplier_name" required>

                <label>Phone</label>
                <input type="text" name="supplier_phone" required>

                <label>Address</label>
                <input type="text" name="supplier_address" required>

                <button type="submit" name="add_supplier">Add Supplier</button>
            </form>

        </div>

    </div>


    <div class="two-column">

        <!-- RECORD DELIVERY -->
        <div class="card">

            <h2>📥 Record Supplier Delivery</h2>

            <p>Adding a delivery increases the ingredient's available stock.</p>

            <form method="POST">

                <label>Supplier</label>
                <select name="delivery_supplier_id" required>
                    <option value="">-- Select Supplier --</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['SupplierID'] ?>"><?= htmlspecialchars($s['SupplierName']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Ingredient</label>
                <select name="delivery_ingredient_id" required>
                    <option value="">-- Select Ingredient --</option>
                    <?php foreach ($ingredients as $i): ?>
                        <option value="<?= $i['IngredientID'] ?>"><?= htmlspecialchars($i['IngredientName']) ?> (<?= htmlspecialchars($i['Unit']) ?>)</option>
                    <?php endforeach; ?>
                </select>

                <label>Quantity Delivered</label>
                <input type="number" step="0.01" min="0.01" name="delivery_qty" required>

                <label>Purchase Price (৳)</label>
                <input type="number" step="0.01" min="0" name="delivery_price" required>

                <button type="submit" name="record_delivery">Record Delivery</button>

            </form>

            <h3>Recent Deliveries</h3>

            <?php if (empty($deliveries)): ?>
                <p class="empty">No deliveries recorded.</p>
            <?php else: ?>
                <table>
                    <tr><th>Date</th><th>Supplier</th><th>Ingredient</th><th>Qty</th><th>Price</th></tr>
                    <?php foreach ($deliveries as $d): ?>
                        <tr>
                            <td><?= htmlspecialchars($d['DeliveryDate']) ?></td>
                            <td><?= htmlspecialchars($d['SupplierName']) ?></td>
                            <td><?= htmlspecialchars($d['IngredientName']) ?></td>
                            <td><?= $d['QuantityDelivered'] ?></td>
                            <td>৳<?= number_format($d['PurchasePrice'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

        </div>

        <!-- LOG PREP -->
        <div class="card">

            <h2>👨‍🍳 Log Food Preparation</h2>

            <p>Logging a prep batch consumes the selected ingredient from stock.</p>

            <form method="POST">

                <label>Food Being Prepared</label>
                <select name="prep_food_id" required>
                    <option value="">-- Select Food --</option>
                    <?php foreach ($foods as $f): ?>
                        <option value="<?= $f['FoodID'] ?>"><?= htmlspecialchars($f['FoodName']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Quantity Prepared</label>
                <input type="number" min="1" name="prep_qty" required>

                <label>Ingredient Used</label>
                <select name="prep_ingredient_id" required>
                    <option value="">-- Select Ingredient --</option>
                    <?php foreach ($ingredients as $i): ?>
                        <option value="<?= $i['IngredientID'] ?>"><?= htmlspecialchars($i['IngredientName']) ?> (<?= $i['AvailQty'] ?> <?= htmlspecialchars($i['Unit']) ?> left)</option>
                    <?php endforeach; ?>
                </select>

                <label>Quantity of Ingredient Used</label>
                <input type="number" step="0.01" min="0.01" name="prep_ingredient_qty" required>

                <button type="submit" name="log_prep">Log Prep Batch</button>

            </form>

            <h3>Recent Prep Log</h3>

            <?php if (empty($prepLog)): ?>
                <p class="empty">No prep batches logged.</p>
            <?php else: ?>
                <table>
                    <tr><th>Date</th><th>Food</th><th>Qty Prepared</th></tr>
                    <?php foreach ($prepLog as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['PrepDate']) ?></td>
                            <td><?= htmlspecialchars($p['FoodName']) ?></td>
                            <td><?= $p['QuantityPrepared'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

        </div>

    </div>


    <!-- LOG WASTE -->
    <div class="card">

        <h2>🗑️ Log Waste</h2>

        <p>Logging waste removes it from the counter's available stock and records the reason for reporting.</p>

        <form method="POST" style="max-width:500px;">

            <label>Counter</label>
            <select name="waste_counter_id" required>
                <option value="">-- Select Counter --</option>
                <?php foreach ($counters as $c): ?>
                    <option value="<?= $c['CounterID'] ?>"><?= htmlspecialchars($c['CounterName']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Food</label>
            <select name="waste_food_id" required>
                <option value="">-- Select Food --</option>
                <?php foreach ($foods as $f): ?>
                    <option value="<?= $f['FoodID'] ?>"><?= htmlspecialchars($f['FoodName']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Quantity</label>
            <input type="number" min="1" name="waste_qty" required>

            <label>Reason</label>
            <input type="text" name="waste_reason" placeholder="Unsold, expired, spoiled, etc." required>

            <button type="submit" name="log_waste" class="danger">Log Waste</button>

        </form>

        <h3>Recent Waste Log</h3>

        <?php if (empty($wasteLog)): ?>
            <p class="empty">No waste logged.</p>
        <?php else: ?>
            <table>
                <tr><th>Date</th><th>Food</th><th>Quantity</th><th>Reason</th></tr>
                <?php foreach ($wasteLog as $w): ?>
                    <tr>
                        <td><?= htmlspecialchars($w['WasteDate']) ?></td>
                        <td><?= htmlspecialchars($w['FoodName']) ?></td>
                        <td><?= $w['Quantity'] ?></td>
                        <td><?= htmlspecialchars($w['Reason']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

    </div>

</div>

</body>
</html>