<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin','Staff'], true)) {
    header("Location: login.php"); exit();
}

$message=''; $error='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if ($_SESSION['role']==='Admin' && isset($_POST['assign_task'])) {
            $staffId=(int)($_POST['staff_id']??0);
            $desc=trim($_POST['description']??'');
            $type=$_POST['task_type']??'';
            $date=$_POST['assigned_date']??date('Y-m-d');
            $allowed=['Food Prep','Counter Service','Stock Refill','Cleaning','Event Service'];

            if($staffId<=0 || $desc==='' || !in_array($type,$allowed,true)) throw new Exception("Please enter valid task information.");

            $stmt=$pdo->prepare("
                INSERT INTO STAFF_TASK(StaffID,TaskDescription,TaskType,AssignedDate,Status)
                VALUES(?,?,?,?, 'Pending')
            ");
            $stmt->execute([$staffId,$desc,$type,$date]);
            $message="Task assigned.";
        }

        if (isset($_POST['update_status'])) {
            $taskId=(int)($_POST['task_id']??0);
            $status=$_POST['status']??'';
            if(!in_array($status,['Pending','In Progress','Completed'],true)) throw new Exception("Invalid status.");

            if($_SESSION['role']==='Staff'){
                $stmt=$pdo->prepare("
                    UPDATE STAFF_TASK
                    SET Status=?, CompletedAt=IF(?='Completed',NOW(),NULL)
                    WHERE TaskID=? AND StaffID=?
                ");
                $stmt->execute([$status,$status,$taskId,$_SESSION['user_id']]);
            }else{
                $stmt=$pdo->prepare("
                    UPDATE STAFF_TASK
                    SET Status=?, CompletedAt=IF(?='Completed',NOW(),NULL)
                    WHERE TaskID=?
                ");
                $stmt->execute([$status,$status,$taskId]);
            }
            $message="Task status updated.";
        }
    } catch(Exception $e){$error=$e->getMessage();}
}

$staff=$pdo->query("SELECT st.UserID,u.Name FROM STAFF st JOIN `USER` u ON st.UserID=u.UserID ORDER BY u.Name")->fetchAll(PDO::FETCH_ASSOC);

if($_SESSION['role']==='Staff'){
    $stmt=$pdo->prepare("
        SELECT t.*,u.Name FROM STAFF_TASK t JOIN `USER` u ON t.StaffID=u.UserID
        WHERE t.StaffID=? ORDER BY t.AssignedDate DESC,t.TaskID DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $tasks=$stmt->fetchAll(PDO::FETCH_ASSOC);
}else{
    $tasks=$pdo->query("
        SELECT t.*,u.Name FROM STAFF_TASK t JOIN `USER` u ON t.StaffID=u.UserID
        ORDER BY t.AssignedDate DESC,t.TaskID DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Staff Tasks</title>
<style>
body{font-family:Arial;background:#f4f6f9}.container{width:94%;max-width:1100px;margin:30px auto}.card{background:white;padding:20px;margin-bottom:20px}
table{width:100%;border-collapse:collapse}th,td{padding:10px;border:1px solid #ddd}th{background:#343a40;color:white}input,select{padding:8px;margin:4px}button{padding:8px 12px;background:#007bff;color:white;border:0}.ok{background:#d4edda;padding:10px}.err{background:#f8d7da;padding:10px}
</style></head><body><div class="container">
<p><a href="<?= $_SESSION['role']==='Admin'?'admin_dashboard.php':'staff_dashboard.php' ?>">← Dashboard</a></p>
<div class="card"><h1>Task Management</h1>
<?php if($message):?><div class="ok"><?=htmlspecialchars($message)?></div><?php endif;?>
<?php if($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($_SESSION['role']==='Admin'):?>
<form method="post">
<select name="staff_id" required><option value="">Select Staff</option><?php foreach($staff as $s):?><option value="<?=$s['UserID']?>"><?=htmlspecialchars($s['Name'])?></option><?php endforeach;?></select>
<input name="description" placeholder="Task description" required>
<select name="task_type" required><option>Food Prep</option><option>Counter Service</option><option>Stock Refill</option><option>Cleaning</option><option>Event Service</option></select>
<input type="date" name="assigned_date" value="<?=date('Y-m-d')?>" required>
<button name="assign_task">Assign Task</button>
</form>
<?php endif;?>
</div>
<div class="card"><table>
<tr><th>ID</th><th>Staff</th><th>Task</th><th>Type</th><th>Date</th><th>Status</th><th>Update</th></tr>
<?php foreach($tasks as $t):?><tr>
<td><?=$t['TaskID']?></td><td><?=htmlspecialchars($t['Name'])?></td><td><?=htmlspecialchars($t['TaskDescription'])?></td><td><?=htmlspecialchars($t['TaskType'])?></td><td><?=$t['AssignedDate']?></td><td><?=$t['Status']?></td>
<td><form method="post"><input type="hidden" name="task_id" value="<?=$t['TaskID']?>"><select name="status"><option <?=$t['Status']==='Pending'?'selected':''?>>Pending</option><option <?=$t['Status']==='In Progress'?'selected':''?>>In Progress</option><option <?=$t['Status']==='Completed'?'selected':''?>>Completed</option></select><button name="update_status">Save</button></form></td>
</tr><?php endforeach;?>
</table></div></div></body></html>
