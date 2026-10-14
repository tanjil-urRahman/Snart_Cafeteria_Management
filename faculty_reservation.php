<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Faculty') {
    header("Location: login.php"); exit();
}

$message=''; $error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $table=(int)($_POST['table_number']??0);
    $seat=(int)($_POST['seat_number']??0);
    $date=$_POST['reservation_date']??'';
    $start=$_POST['start_time']??'';
    $end=$_POST['end_time']??'';

    if($table<=0||$seat<=0||$date===''||$start===''||$end===''||$end<=$start){
        $error="Please enter valid reservation information.";
    }else{
        $error="Important: your current database only allows SEAT_RESERVATION.StudentID to reference STUDENT, so Faculty cannot safely insert reservations without a schema redesign.";
    }
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Faculty Seat Reservation</title></head><body>
<h2>Faculty Seat Reservation</h2>
<p><a href="faculty_dashboard.php">← Dashboard</a></p>
<p><strong>Database limitation:</strong> the current SEAT_RESERVATION table is tied only to STUDENT(UserID). Do not force faculty IDs into that column.</p>
<p>Recommended academic-safe solution: keep faculty reservation marked as future scope, or redesign reservations to use UserID instead of StudentID across the system.</p>
</body></html>
