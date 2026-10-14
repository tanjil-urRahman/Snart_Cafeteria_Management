<?php

session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Admin gets the full picture, Staff gets an operational view of the same data
if (!in_array($_SESSION['role'], ['Admin', 'Staff'], true)) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];


/*
|--------------------------------------------------------------------------
| 1. REVENUE KPIs
|--------------------------------------------------------------------------
*/

$kpi = $pdo->query(
    "SELECT
        COALESCE(SUM(CASE WHEN SaleDate = CURDATE() THEN TotalAmount END), 0) AS TodayRevenue,
        COALESCE(SUM(CASE WHEN SaleDate >= CURDATE() - INTERVAL 6 DAY THEN TotalAmount END), 0) AS WeekRevenue,
        COALESCE(SUM(CASE WHEN SaleDate >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN TotalAmount END), 0) AS MonthRevenue,
        COALESCE(SUM(TotalAmount), 0) AS AllTimeRevenue,
        COUNT(*) AS TotalOrders,
        COUNT(CASE WHEN SaleDate = CURDATE() THEN 1 END) AS TodayOrders
     FROM SALE
     WHERE OrderStatus <> 'Cancelled'"
)->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| 2. PROFIT (Revenue - Cost of Goods Sold)
|--------------------------------------------------------------------------
*/

$profit = $pdo->query(
    "SELECT
        COALESCE(SUM(si.Subtotal), 0) AS Revenue,
        COALESCE(SUM(si.QtyPurchased * f.CostPrice), 0) AS Cost,
        COALESCE(SUM(si.Subtotal) - SUM(si.QtyPurchased * f.CostPrice), 0) AS Profit
     FROM SALE_ITEM si
     INNER JOIN SALE s ON si.SaleID = s.SaleID
     INNER JOIN FOOD f ON si.FoodID = f.FoodID
     WHERE s.OrderStatus <> 'Cancelled'"
)->fetch(PDO::FETCH_ASSOC);

$profitMargin = ($profit['Revenue'] > 0)
    ? round(($profit['Profit'] / $profit['Revenue']) * 100, 1)
    : 0;


/*
|--------------------------------------------------------------------------
| 3. PEAK HOUR ANALYSIS  (uses vw_peak_hours if the migration ran,
|    falls back to a raw query otherwise)
|--------------------------------------------------------------------------
*/

try {
    $peakHours = $pdo->query(
        "SELECT HourOfDay, OrderCount, Revenue
         FROM vw_peak_hours
         ORDER BY HourOfDay ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $peakHours = $pdo->query(
        "SELECT HOUR(SaleTime) AS HourOfDay, COUNT(*) AS OrderCount, SUM(TotalAmount) AS Revenue
         FROM SALE
         WHERE OrderStatus <> 'Cancelled'
         GROUP BY HOUR(SaleTime)
         ORDER BY HourOfDay ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

$maxHourOrders = 1;
$busiestHour = null;

foreach ($peakHours as $h) {
    if ($h['OrderCount'] > $maxHourOrders) {
        $maxHourOrders = $h['OrderCount'];
    }
    if ($busiestHour === null || $h['OrderCount'] > $busiestHour['OrderCount']) {
        $busiestHour = $h;
    }
}


/*
|--------------------------------------------------------------------------
| 4. TOP SELLING FOOD ITEMS
|--------------------------------------------------------------------------
*/

$topFoods = $pdo->query(
    "SELECT f.FoodName, f.Category,
            SUM(si.QtyPurchased) AS TotalSold,
            SUM(si.Subtotal) AS Revenue
     FROM SALE_ITEM si
     INNER JOIN SALE s ON si.SaleID = s.SaleID
     INNER JOIN FOOD f ON si.FoodID = f.FoodID
     WHERE s.OrderStatus <> 'Cancelled'
     GROUP BY f.FoodID
     ORDER BY TotalSold DESC
     LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| 5. REVENUE BY COUNTER
|--------------------------------------------------------------------------
*/

$counterRevenue = $pdo->query(
    "SELECT c.CounterName,
            COUNT(s.SaleID) AS Orders,
            SUM(s.TotalAmount) AS Revenue
     FROM SALE s
     INNER JOIN COUNTER c ON s.PickupCounterID = c.CounterID
     WHERE s.OrderStatus <> 'Cancelled'
     GROUP BY c.CounterID
     ORDER BY Revenue DESC"
)->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| 6. REVENUE TREND (last 14 days)
|--------------------------------------------------------------------------
*/

$dailyRevenue = $pdo->query(
    "SELECT SaleDate, COUNT(*) AS Orders, SUM(TotalAmount) AS Revenue
     FROM SALE
     WHERE SaleDate >= CURDATE() - INTERVAL 13 DAY
     AND OrderStatus <> 'Cancelled'
     GROUP BY SaleDate
     ORDER BY SaleDate ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$maxDayRevenue = 1;
foreach ($dailyRevenue as $d) {
    if ($d['Revenue'] > $maxDayRevenue) {
        $maxDayRevenue = $d['Revenue'];
    }
}


/*
|--------------------------------------------------------------------------
| 7. LOW STOCK ALERT
|--------------------------------------------------------------------------
*/

$lowStock = $pdo->query(
    "SELECT f.FoodName, c.CounterName, csf.AvailableQuantity
     FROM COUNTER_SERVES_FOOD csf
     INNER JOIN FOOD f ON csf.FoodID = f.FoodID
     INNER JOIN COUNTER c ON csf.CounterID = c.CounterID
     WHERE csf.AvailableQuantity <= 10
     ORDER BY csf.AvailableQuantity ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$lowIngredients = $pdo->query(
    "SELECT IngredientName, AvailQty, Unit
     FROM INGREDIENT
     WHERE AvailQty < 20
     ORDER BY AvailQty ASC"
)->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| 8. WASTE REPORT
|--------------------------------------------------------------------------
*/

$wasteReport = $pdo->query(
    "SELECT f.FoodName, SUM(w.Quantity) AS TotalWasted, COUNT(*) AS Incidents
     FROM WASTE w
     INNER JOIN FOOD f ON w.FoodID = f.FoodID
     GROUP BY f.FoodID
     ORDER BY TotalWasted DESC"
)->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| 9. STAFF ATTENDANCE SUMMARY
|--------------------------------------------------------------------------
*/

$staffAttendance = $pdo->query(
    "SELECT u.Name, st.Position,
            SUM(CASE WHEN sa.Status = 'Present' THEN 1 ELSE 0 END) AS Present,
            SUM(CASE WHEN sa.Status = 'Late' THEN 1 ELSE 0 END) AS Late,
            SUM(CASE WHEN sa.Status = 'Absent' THEN 1 ELSE 0 END) AS Absent
     FROM STAFF_ATTENDANCE sa
     INNER JOIN STAFF st ON sa.StaffID = st.UserID
     INNER JOIN `USER` u ON st.UserID = u.UserID
     GROUP BY st.UserID
     ORDER BY u.Name"
)->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| 10. WALLET CASH FLOW
|--------------------------------------------------------------------------
*/

$walletFlow = $pdo->query(
    "SELECT TransactionType, SUM(Amount) AS Total, COUNT(*) AS Cnt
     FROM WALLET_TRANSACTION
     GROUP BY TransactionType"
)->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<title>Business Analytics - Smart Cafeteria</title>

<style>

* { box-sizing: border-box; }

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

.header a {
    color: white;
    background: #007bff;
    text-decoration: none;
    padding: 9px 15px;
    border-radius: 5px;
}

.container {
    width: 94%;
    max-width: 1300px;
    margin: 30px auto;
}

.kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.kpi {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    text-align: center;
}

.kpi .value {
    font-size: 1.6rem;
    font-weight: bold;
    color: #007bff;
}

.kpi.profit .value { color: #28a745; }

.kpi .label {
    color: #666;
    font-size: 0.85rem;
    margin-top: 5px;
}

.two-column {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
    gap: 20px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.card h2 {
    margin-top: 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

th {
    background: #343a40;
    color: white;
    padding: 8px 10px;
    text-align: left;
    font-size: 0.9rem;
}

td {
    padding: 8px 10px;
    border-bottom: 1px solid #eee;
    font-size: 0.9rem;
}

.bar-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
}

.bar-label {
    width: 70px;
    font-size: 0.85rem;
    color: #555;
}

.bar-track {
    flex: 1;
    background: #eee;
    border-radius: 4px;
    height: 18px;
    overflow: hidden;
}

.bar-fill {
    background: #007bff;
    height: 100%;
}

.bar-fill.peak {
    background: #dc3545;
}

.bar-value {
    width: 90px;
    text-align: right;
    font-size: 0.85rem;
    color: #333;
}

.badge {
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.8rem;
}

.badge-danger { background: #f8d7da; color: #721c24; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-success { background: #d4edda; color: #155724; }

.empty { color: #888; }

.notice {
    background: #e7f3ff;
    color: #084298;
    padding: 12px 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}

</style>

</head>
<body>

<div class="header">
    <h2>📊 Business Analytics <?= $role === 'Staff' ? '(Staff View)' : '' ?></h2>
    <a href="<?= $role === 'Admin' ? 'admin_dashboard.php' : 'staff_dashboard.php' ?>">
        ← Dashboard
    </a>
</div>

<div class="container">

    <?php if ($busiestHour): ?>
        <div class="notice">
            🔥 Busiest hour so far: <strong>
                <?= date('g A', mktime((int)$busiestHour['HourOfDay'], 0)) ?>
            </strong>
            with <strong><?= $busiestHour['OrderCount'] ?></strong> orders
            (৳<?= number_format($busiestHour['Revenue'], 2) ?> revenue).
            Consider extra staff on counters during this window.
        </div>
    <?php endif; ?>

    <!-- ==========================================
         REVENUE + PROFIT KPIs
    ========================================== -->

    <div class="kpis">

        <div class="kpi">
            <div class="value">৳<?= number_format($kpi['TodayRevenue'], 2) ?></div>
            <div class="label">Today's Revenue (<?= $kpi['TodayOrders'] ?> orders)</div>
        </div>

        <div class="kpi">
            <div class="value">৳<?= number_format($kpi['WeekRevenue'], 2) ?></div>
            <div class="label">Last 7 Days Revenue</div>
        </div>

        <div class="kpi">
            <div class="value">৳<?= number_format($kpi['MonthRevenue'], 2) ?></div>
            <div class="label">This Month's Revenue</div>
        </div>

        <div class="kpi">
            <div class="value">৳<?= number_format($kpi['AllTimeRevenue'], 2) ?></div>
            <div class="label">All-Time Revenue (<?= $kpi['TotalOrders'] ?> orders)</div>
        </div>

        <div class="kpi profit">
            <div class="value">৳<?= number_format($profit['Profit'], 2) ?></div>
            <div class="label">Total Profit (<?= $profitMargin ?>% margin)</div>
        </div>

        <div class="kpi">
            <div class="value">৳<?= number_format($profit['Cost'], 2) ?></div>
            <div class="label">Cost of Goods Sold</div>
        </div>

    </div>


    <div class="two-column">

        <!-- ==========================================
             PEAK HOUR ANALYSIS
        ========================================== -->

        <div class="card">

            <h2>⏰ Peak Hour Analysis</h2>

            <?php if (empty($peakHours)): ?>

                <p class="empty">No sales data yet.</p>

            <?php else: ?>

                <?php foreach ($peakHours as $h): ?>

                    <?php $pct = round(($h['OrderCount'] / $maxHourOrders) * 100); ?>

                    <div class="bar-row">
                        <div class="bar-label">
                            <?= date('g A', mktime((int)$h['HourOfDay'], 0)) ?>
                        </div>
                        <div class="bar-track">
                            <div
                                class="bar-fill <?= (
                                    $busiestHour &&
                                    $h['HourOfDay'] == $busiestHour['HourOfDay']
                                ) ? 'peak' : '' ?>"
                                style="width: <?= $pct ?>%;"
                            ></div>
                        </div>
                        <div class="bar-value">
                            <?= $h['OrderCount'] ?> orders
                        </div>
                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>


        <!-- ==========================================
             REVENUE TREND (last 14 days)
        ========================================== -->

        <div class="card">

            <h2>📈 Revenue Trend (Last 14 Days)</h2>

            <?php if (empty($dailyRevenue)): ?>

                <p class="empty">No sales data yet.</p>

            <?php else: ?>

                <?php foreach ($dailyRevenue as $d): ?>

                    <?php $pct = round(($d['Revenue'] / $maxDayRevenue) * 100); ?>

                    <div class="bar-row">
                        <div class="bar-label">
                            <?= date('d M', strtotime($d['SaleDate'])) ?>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: <?= $pct ?>%;"></div>
                        </div>
                        <div class="bar-value">
                            ৳<?= number_format($d['Revenue'], 0) ?>
                        </div>
                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>


    <div class="two-column">

        <!-- ==========================================
             TOP SELLING FOOD ITEMS
        ========================================== -->

        <div class="card">

            <h2>🏆 Top Selling Food Items</h2>

            <?php if (empty($topFoods)): ?>
                <p class="empty">No sales data yet.</p>
            <?php else: ?>
                <table>
                    <tr><th>Food</th><th>Category</th><th>Sold</th><th>Revenue</th></tr>
                    <?php foreach ($topFoods as $f): ?>
                        <tr>
                            <td><?= htmlspecialchars($f['FoodName']) ?></td>
                            <td><?= htmlspecialchars($f['Category']) ?></td>
                            <td><?= $f['TotalSold'] ?></td>
                            <td>৳<?= number_format($f['Revenue'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

        </div>


        <!-- ==========================================
             REVENUE BY COUNTER
        ========================================== -->

        <div class="card">

            <h2>🏪 Revenue by Counter</h2>

            <?php if (empty($counterRevenue)): ?>
                <p class="empty">No sales data yet.</p>
            <?php else: ?>
                <table>
                    <tr><th>Counter</th><th>Orders</th><th>Revenue</th></tr>
                    <?php foreach ($counterRevenue as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['CounterName']) ?></td>
                            <td><?= $c['Orders'] ?></td>
                            <td>৳<?= number_format($c['Revenue'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

        </div>

    </div>


    <div class="two-column">

        <!-- ==========================================
             LOW STOCK ALERTS
        ========================================== -->

        <div class="card">

            <h2>⚠️ Low Stock Alerts</h2>

            <h3 style="margin-top:0;">Counter Stock (≤ 10 left)</h3>

            <?php if (empty($lowStock)): ?>
                <p class="empty">All counters are well stocked.</p>
            <?php else: ?>
                <table>
                    <tr><th>Food</th><th>Counter</th><th>Remaining</th></tr>
                    <?php foreach ($lowStock as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['FoodName']) ?></td>
                            <td><?= htmlspecialchars($s['CounterName']) ?></td>
                            <td>
                                <span class="badge <?= $s['AvailableQuantity'] <= 5 ? 'badge-danger' : 'badge-warning' ?>">
                                    <?= $s['AvailableQuantity'] ?> left
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <h3>Raw Ingredients (&lt; 20 units)</h3>

            <?php if (empty($lowIngredients)): ?>
                <p class="empty">Ingredient stock looks healthy.</p>
            <?php else: ?>
                <table>
                    <tr><th>Ingredient</th><th>Available</th></tr>
                    <?php foreach ($lowIngredients as $i): ?>
                        <tr>
                            <td><?= htmlspecialchars($i['IngredientName']) ?></td>
                            <td>
                                <span class="badge badge-warning">
                                    <?= $i['AvailQty'] ?> <?= htmlspecialchars($i['Unit']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <p style="margin-top:10px;">
                <a href="inventory_suppliers.php">Manage inventory & suppliers →</a>
            </p>

        </div>


        <!-- ==========================================
             WASTE REPORT
        ========================================== -->

        <div class="card">

            <h2>🗑️ Waste Report</h2>

            <?php if (empty($wasteReport)): ?>
                <p class="empty">No waste recorded.</p>
            <?php else: ?>
                <table>
                    <tr><th>Food</th><th>Units Wasted</th><th>Incidents</th></tr>
                    <?php foreach ($wasteReport as $w): ?>
                        <tr>
                            <td><?= htmlspecialchars($w['FoodName']) ?></td>
                            <td><?= $w['TotalWasted'] ?></td>
                            <td><?= $w['Incidents'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

        </div>

    </div>


    <!-- ==========================================
         STAFF ATTENDANCE
    ========================================== -->

    <div class="card">

        <h2>🧑‍💼 Staff Attendance Summary</h2>

        <?php if (empty($staffAttendance)): ?>
            <p class="empty">No attendance records yet.</p>
        <?php else: ?>
            <table>
                <tr><th>Staff</th><th>Position</th><th>Present</th><th>Late</th><th>Absent</th></tr>
                <?php foreach ($staffAttendance as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['Name']) ?></td>
                        <td><?= htmlspecialchars($a['Position']) ?></td>
                        <td><span class="badge badge-success"><?= $a['Present'] ?></span></td>
                        <td><span class="badge badge-warning"><?= $a['Late'] ?></span></td>
                        <td><span class="badge badge-danger"><?= $a['Absent'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

    </div>


    <!-- ==========================================
         WALLET CASH FLOW
    ========================================== -->

    <div class="card">

        <h2>💰 Wallet Cash Flow</h2>

        <?php if (empty($walletFlow)): ?>
            <p class="empty">No wallet activity yet.</p>
        <?php else: ?>
            <table>
                <tr><th>Type</th><th>Total Amount</th><th>Transactions</th></tr>
                <?php foreach ($walletFlow as $w): ?>
                    <tr>
                        <td><?= htmlspecialchars($w['TransactionType']) ?></td>
                        <td>৳<?= number_format($w['Total'], 2) ?></td>
                        <td><?= $w['Cnt'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

    </div>

</div>

</body>
</html>