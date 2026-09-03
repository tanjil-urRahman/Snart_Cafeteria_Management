<?php
session_start();
require 'db.php';

$sql = "SELECT f.FoodID, f.FoodName, f.Category, f.Price, c.CounterName, csf.AvailableQuantity
        FROM FOOD f
        INNER JOIN COUNTER_SERVES_FOOD csf ON f.FoodID = csf.FoodID
        INNER JOIN COUNTER c ON csf.CounterID = c.CounterID
        WHERE csf.AvailableQuantity > 0
        ORDER BY f.Category, f.FoodName ASC";

$stmt = $pdo->query($sql);
$menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cafeteria Live Menu</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f8f9fa; }
        .header { background: #343a40; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .header a { color: #ffc107; text-decoration: none; font-weight: bold; padding: 8px 15px; border: 1px solid #ffc107; border-radius: 4px; }
        .container { max-width: 900px; margin: 30px auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f1f1f1; }
        .badge { background: #28a745; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
    </style>
</head>
<body>

<div class="header">
    <h2>Smart Cafeteria Menu</h2>
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="dashboard.php">Go to Dashboard</a>
    <?php else: ?>
        <a href="login.php">Log In to Order</a>
    <?php endif; ?>
</div>

<div class="container">
    <h3>Available Items Today</h3>
    <table>
        <thead>
            <tr>
                <th>Food Item</th>
                <th>Category</th>
                <th>Counter Location</th>
                <th>Price</th>
                <th>In Stock</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($menuItems as $item): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($item['FoodName']) ?></strong></td>
                    <td><?= htmlspecialchars($item['Category']) ?></td>
                    <td><?= htmlspecialchars($item['CounterName']) ?></td>
                    <td><?= number_format($item['Price'], 2) ?> Tk</td>
                    <td><span class="badge"><?= $item['AvailableQuantity'] ?> left</span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>