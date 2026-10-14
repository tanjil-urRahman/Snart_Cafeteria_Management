<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!in_array($_SESSION['role'], ['Staff','Admin'], true)) {
    header("Location: login.php");
    exit();
}

$message='';
$error='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id=(int)($_POST['reservation_id']??0);
    $action=$_POST['action']??'';

    try {
        $pdo->beginTransaction();

        $stmt=$pdo->prepare("
            SELECT ReservationID,TableNumber,SeatNumber,ReservationDate,StartTime,EndTime,Status
            FROM SEAT_RESERVATION
            WHERE ReservationID=?
            FOR UPDATE
        ");
        $stmt->execute([$id]);
        $r=$stmt->fetch(PDO::FETCH_ASSOC);

        if(!$r || $r['Status']!=='Pending') throw new Exception("Reservation is not pending.");

        if($action==='reject'){
            $stmt=$pdo->prepare("UPDATE SEAT_RESERVATION SET Status='Rejected' WHERE ReservationID=?");
            $stmt->execute([$id]);
            $message="Reservation rejected.";
        } elseif($action==='approve') {
            $check=$pdo->prepare("
                SELECT ReservationID
                FROM SEAT_RESERVATION
                WHERE TableNumber=?
                  AND SeatNumber=?
                  AND ReservationDate=?
                  AND Status='Confirmed'
                  AND ReservationID<>?
                  AND StartTime < ?
                  AND EndTime > ?
                LIMIT 1
                FOR UPDATE
            ");
            $check->execute([
                $r['TableNumber'],$r['SeatNumber'],$r['ReservationDate'],
                $id,$r['EndTime'],$r['StartTime']
            ]);

            if($check->fetch()) throw new Exception("Seat is already occupied for this time.");

            $stmt=$pdo->prepare("UPDATE SEAT_RESERVATION SET Status='Confirmed' WHERE ReservationID=?");
            $stmt->execute([$id]);
            $message="Reservation confirmed.";
        } else {
            throw new Exception("Invalid action.");
        }

        $pdo->commit();
    }catch(Exception $e){
        if($pdo->inTransaction())$pdo->rollBack();
        $error=$e->getMessage();
    }
}

$reservations=$pdo->query("
SELECT sr.*,u.Name
FROM SEAT_RESERVATION sr
JOIN STUDENT s ON sr.StudentID=s.UserID
JOIN `USER` u ON s.UserID=u.UserID
ORDER BY sr.ReservationDate DESC,sr.StartTime DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Seat Requests</title>
<style>body{font-family:Arial}.container{width:94%;margin:25px auto}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:9px}th{background:#343a40;color:white}button{padding:7px 10px}.ok{background:#d4edda;padding:10px}.err{background:#f8d7da;padding:10px}</style></head><body>
<div class="container">
<p><a href="<?= $_SESSION['role']==='Admin'?'admin_dashboard.php':'staff_dashboard.php' ?>">← Dashboard</a></p>
<h1>Seat Reservation Requests</h1>
<?php if($message):?><div class="ok"><?=htmlspecialchars($message)?></div><?php endif;?>
<?php if($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?>
<table><tr><th>ID</th><th>Student</th><th>Table</th><th>Seat</th><th>Date</th><th>Time</th><th>Purpose</th><th>Status</th><th>Action</th></tr>
<?php foreach($reservations as $r):?><tr>
<td><?=$r['ReservationID']?></td><td><?=htmlspecialchars($r['Name'])?></td><td><?=$r['TableNumber']?></td><td><?=$r['SeatNumber']?></td><td><?=$r['ReservationDate']?></td><td><?=$r['StartTime']?> - <?=$r['EndTime']?></td><td><?=htmlspecialchars($r['Purpose'])?></td><td><?=$r['Status']?></td>
<td><?php if($r['Status']==='Pending'):?>
<form method="post" style="display:inline"><input type="hidden" name="reservation_id" value="<?=$r['ReservationID']?>"><button name="action" value="approve">Approve</button></form>
<form method="post" style="display:inline"><input type="hidden" name="reservation_id" value="<?=$r['ReservationID']?>"><button name="action" value="reject">Reject</button></form>
<?php else:?>Done<?php endif;?></td>
</tr><?php endforeach;?></table>
</div></body></html>
