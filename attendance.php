<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin','Staff'], true)) {
    header("Location: login.php"); exit();
}

$message=''; $error='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action=$_POST['action']??'';

    try {
        if ($_SESSION['role']==='Staff') {
            $staffId=(int)$_SESSION['user_id'];

            if ($action==='checkin') {
                $now=date('H:i:s');
                $status = $now > '09:15:00' ? 'Late' : 'Present';
                $stmt=$pdo->prepare("
                    INSERT INTO STAFF_ATTENDANCE(StaffID,DutyDate,CheckIn,Status)
                    VALUES(?,CURDATE(),CURTIME(),?)
                    ON DUPLICATE KEY UPDATE
                        CheckIn = COALESCE(CheckIn, VALUES(CheckIn)),
                        Status = IF(CheckIn IS NULL, VALUES(Status), Status)
                ");
                $stmt->execute([$staffId,$status]);
                $message="Check-in recorded.";
            }

            if ($action==='checkout') {
                $stmt=$pdo->prepare("
                    UPDATE STAFF_ATTENDANCE
                    SET CheckOut=CURTIME()
                    WHERE StaffID=? AND DutyDate=CURDATE() AND CheckIn IS NOT NULL
                ");
                $stmt->execute([$staffId]);
                $message=$stmt->rowCount() ? "Check-out recorded." : "Please check in first.";
            }
        }

        if ($_SESSION['role']==='Admin' && $action==='mark') {
            $staffId=(int)($_POST['staff_id']??0);
            $date=$_POST['duty_date']??date('Y-m-d');
            $status=$_POST['status']??'Present';

            if($staffId<=0 || !in_array($status,['Present','Absent','Late'],true)) throw new Exception("Invalid attendance data.");

            $stmt=$pdo->prepare("
                INSERT INTO STAFF_ATTENDANCE(StaffID,DutyDate,Status)
                VALUES(?,?,?)
                ON DUPLICATE KEY UPDATE Status=VALUES(Status)
            ");
            $stmt->execute([$staffId,$date,$status]);
            $message="Attendance marked.";
        }
    } catch(Exception $e){$error=$e->getMessage();}
}

$today = $pdo->query("
    SELECT u.Name,st.Position,sa.DutyDate,sa.CheckIn,sa.CheckOut,sa.Status
    FROM STAFF st
    JOIN `USER` u ON st.UserID=u.UserID
    LEFT JOIN STAFF_ATTENDANCE sa ON sa.StaffID=st.UserID AND sa.DutyDate=CURDATE()
    ORDER BY u.Name
")->fetchAll(PDO::FETCH_ASSOC);

$staff = $pdo->query("SELECT st.UserID,u.Name FROM STAFF st JOIN `USER` u ON st.UserID=u.UserID ORDER BY u.Name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Attendance</title>
<style>
body{font-family:Arial;background:#f4f6f9}.container{width:94%;max-width:1000px;margin:30px auto}.card{background:white;padding:20px;margin-bottom:20px}
table{width:100%;border-collapse:collapse}th,td{padding:10px;border:1px solid #ddd}th{background:#343a40;color:white}
button{padding:9px 14px;background:#007bff;color:white;border:0;margin:4px}.ok{background:#d4edda;padding:10px}.err{background:#f8d7da;padding:10px}
</style></head><body><div class="container">
<p><a href="<?= $_SESSION['role']==='Admin'?'admin_dashboard.php':'staff_dashboard.php' ?>">← Dashboard</a></p>
<div class="card"><h1>Attendance Management</h1>
<?php if($message):?><div class="ok"><?=htmlspecialchars($message)?></div><?php endif;?>
<?php if($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($_SESSION['role']==='Staff'):?>
<form method="post" style="display:inline"><button name="action" value="checkin">Check In</button></form>
<form method="post" style="display:inline"><button name="action" value="checkout">Check Out</button></form>
<?php else:?>
<form method="post">
<select name="staff_id" required><option value="">Select Staff</option><?php foreach($staff as $s):?><option value="<?=$s['UserID']?>"><?=htmlspecialchars($s['Name'])?></option><?php endforeach;?></select>
<input type="date" name="duty_date" value="<?=date('Y-m-d')?>" required>
<select name="status"><option>Present</option><option>Late</option><option>Absent</option></select>
<button name="action" value="mark">Mark Attendance</button>
</form>
<?php endif;?>
</div>
<div class="card"><h2>Today's Attendance</h2><table>
<tr><th>Staff</th><th>Position</th><th>Check In</th><th>Check Out</th><th>Status</th></tr>
<?php foreach($today as $r):?><tr><td><?=htmlspecialchars($r['Name'])?></td><td><?=htmlspecialchars($r['Position'])?></td><td><?=$r['CheckIn']?:'-'?></td><td><?=$r['CheckOut']?:'-'?></td><td><?=$r['Status']?:'Not Marked'?></td></tr><?php endforeach;?>
</table></div></div></body></html>
