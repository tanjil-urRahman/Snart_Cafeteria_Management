<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!in_array($_SESSION['role'], ['Staff', 'Admin'], true)) {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';

function restoreOrderStockAndRefund(PDO $pdo, int $saleId): void
{
    $stmt = $pdo->prepare("
        SELECT StudentID, OrderStatus, TotalAmount
        FROM SALE
        WHERE SaleID = ?
        FOR UPDATE
    ");
    $stmt->execute([$saleId]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        throw new Exception("Order not found.");
    }

    if ($sale['OrderStatus'] === 'Cancelled') {
        throw new Exception("Order is already cancelled.");
    }

    if ($sale['OrderStatus'] === 'Completed') {
        throw new Exception("Completed orders cannot be cancelled.");
    }

    $items = $pdo->prepare("
        SELECT FoodID, QtyPurchased
        FROM SALE_ITEM
        WHERE SaleID = ?
    ");
    $items->execute([$saleId]);

    foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $stock = $pdo->prepare("
            UPDATE COUNTER_SERVES_FOOD csf
            JOIN SALE s ON s.PickupCounterID = csf.CounterID
            SET csf.AvailableQuantity = csf.AvailableQuantity + ?
            WHERE s.SaleID = ?
              AND csf.FoodID = ?
        ");
        $stock->execute([
            $item['QtyPurchased'],
            $saleId,
            $item['FoodID']
        ]);
    }

    if (!empty($sale['StudentID']) && (float)$sale['TotalAmount'] > 0) {
        $wallet = $pdo->prepare("
            UPDATE STUDENT
            SET WalletBalance = WalletBalance + ?
            WHERE UserID = ?
        ");
        $wallet->execute([
            $sale['TotalAmount'],
            $sale['StudentID']
        ]);

        $tx = $pdo->prepare("
            INSERT INTO WALLET_TRANSACTION
            (StudentID, Amount, TransactionType)
            VALUES (?, ?, 'Refund')
        ");
        $tx->execute([
            $sale['StudentID'],
            $sale['TotalAmount']
        ]);
    }

    $upd = $pdo->prepare("
        UPDATE SALE
        SET OrderStatus = 'Cancelled'
        WHERE SaleID = ?
    ");
    $upd->execute([$saleId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $saleId = (int)($_POST['sale_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($saleId <= 0) {
        $error = "Invalid order.";
    } else {
        try {
            $pdo->beginTransaction();

            if ($action === 'prepare') {
                $stmt = $pdo->prepare("
                    UPDATE SALE
                    SET OrderStatus = 'Preparing'
                    WHERE SaleID = ?
                      AND OrderStatus = 'Pending'
                ");
                $stmt->execute([$saleId]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception("Only pending orders can move to Preparing.");
                }

                $message = "Order #{$saleId} is now Preparing.";

            } elseif ($action === 'ready') {
                $stmt = $pdo->prepare("
                    UPDATE SALE
                    SET OrderStatus = 'Ready'
                    WHERE SaleID = ?
                      AND OrderStatus = 'Preparing'
                ");
                $stmt->execute([$saleId]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception("Only Preparing orders can move to Ready.");
                }

                $message = "Order #{$saleId} is now Ready.";

            } elseif ($action === 'complete') {
                $stmt = $pdo->prepare("
                    UPDATE SALE
                    SET OrderStatus = 'Completed'
                    WHERE SaleID = ?
                      AND OrderStatus = 'Ready'
                ");
                $stmt->execute([$saleId]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception("Only Ready orders can be Completed.");
                }

                $message = "Order #{$saleId} completed.";

            } elseif ($action === 'cancel') {
                restoreOrderStockAndRefund($pdo, $saleId);
                $message = "Order #{$saleId} cancelled. Stock and wallet were restored.";

            } else {
                throw new Exception("Invalid action.");
            }

            $pdo->commit();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

$stmt = $pdo->query("
    SELECT
        s.SaleID,
        s.StudentID,
        u.Name AS StudentName,
        s.SaleDate,
        s.SaleTime,
        s.PickupTime,
        s.OrderStatus,
        s.TotalAmount,
        s.PaymentMethod,
        c.CounterName
    FROM SALE s
    LEFT JOIN STUDENT st ON s.StudentID = st.UserID
    LEFT JOIN `USER` u ON st.UserID = u.UserID
    LEFT JOIN COUNTER c ON s.PickupCounterID = c.CounterID
    WHERE s.StudentID IS NOT NULL
    ORDER BY
        FIELD(s.OrderStatus, 'Pending','Preparing','Ready','Completed','Cancelled'),
        s.SaleDate DESC,
        s.PickupTime ASC,
        s.SaleID DESC
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Online Order Management</title>
<style>
body{font-family:Arial;background:#f4f6f9;margin:0}
.container{width:94%;max-width:1200px;margin:30px auto}
.card{background:#fff;padding:22px;border-radius:8px;margin-bottom:20px}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left}
th{background:#343a40;color:#fff}
button{padding:7px 10px;border:0;border-radius:4px;cursor:pointer;margin:2px}
.prepare{background:#ffc107}.ready{background:#17a2b8;color:white}.complete{background:#28a745;color:white}.cancel{background:#dc3545;color:white}
.ok{background:#d4edda;color:#155724;padding:12px}.err{background:#f8d7da;color:#721c24;padding:12px}
.badge{font-weight:bold}
</style>
</head>
<body>
<div class="container">
    <p><a href="<?= $_SESSION['role']==='Admin' ? 'admin_dashboard.php' : 'staff_dashboard.php' ?>">← Dashboard</a></p>
    <div class="card">
        <h1>Online Pre-Order Queue</h1>
        <p>Flow: Pending → Preparing → Ready → Completed. Cancellation automatically restores stock and refunds the student's wallet.</p>
        <?php if($message): ?><div class="ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    </div>

    <div class="card">
        <table>
            <tr>
                <th>ID</th><th>Student</th><th>Counter</th><th>Pickup</th>
                <th>Total</th><th>Payment</th><th>Status</th><th>Action</th>
            </tr>
            <?php foreach($orders as $o): ?>
            <tr>
                <td>#<?= $o['SaleID'] ?></td>
                <td><?= htmlspecialchars($o['StudentName'] ?? 'Student') ?></td>
                <td><?= htmlspecialchars($o['CounterName'] ?? '-') ?></td>
                <td><?= htmlspecialchars(($o['SaleDate'] ?? '').' '.($o['PickupTime'] ?? '')) ?></td>
                <td>৳<?= number_format($o['TotalAmount'],2) ?></td>
                <td><?= htmlspecialchars($o['PaymentMethod'] ?? '') ?></td>
                <td class="badge"><?= htmlspecialchars($o['OrderStatus']) ?></td>
                <td>
                    <?php if($o['OrderStatus']==='Pending'): ?>
                        <form method="post" style="display:inline"><input type="hidden" name="sale_id" value="<?= $o['SaleID'] ?>"><button class="prepare" name="action" value="prepare">Preparing</button></form>
                    <?php endif; ?>
                    <?php if($o['OrderStatus']==='Preparing'): ?>
                        <form method="post" style="display:inline"><input type="hidden" name="sale_id" value="<?= $o['SaleID'] ?>"><button class="ready" name="action" value="ready">Ready</button></form>
                    <?php endif; ?>
                    <?php if($o['OrderStatus']==='Ready'): ?>
                        <form method="post" style="display:inline"><input type="hidden" name="sale_id" value="<?= $o['SaleID'] ?>"><button class="complete" name="action" value="complete">Complete</button></form>
                    <?php endif; ?>
                    <?php if(in_array($o['OrderStatus'], ['Pending','Preparing','Ready'], true)): ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('Cancel this order? Stock and wallet will be restored.');">
                            <input type="hidden" name="sale_id" value="<?= $o['SaleID'] ?>">
                            <button class="cancel" name="action" value="cancel">Cancel</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
</body>
</html>
