<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

/*
    Aggregate each one-to-many table separately first.
    This avoids inflated totals caused by joining attendance, tasks,
    and reviews directly in one large result set.
*/
$rows = $pdo->query("
SELECT
    st.UserID,
    u.Name,
    st.Position,
    st.Shift,

    COALESCE(a.PresentDays, 0) AS PresentDays,
    COALESCE(a.LateDays, 0) AS LateDays,
    COALESCE(a.AbsentDays, 0) AS AbsentDays,
    COALESCE(a.WorkedHours, 0) AS WorkedHours,

    COALESCE(t.TotalTasks, 0) AS TotalTasks,
    COALESCE(t.CompletedTasks, 0) AS CompletedTasks,

    r.AvgRating

FROM STAFF st
JOIN `USER` u
    ON st.UserID = u.UserID

LEFT JOIN (
    SELECT
        StaffID,
        SUM(CASE WHEN Status='Present' THEN 1 ELSE 0 END) AS PresentDays,
        SUM(CASE WHEN Status='Late' THEN 1 ELSE 0 END) AS LateDays,
        SUM(CASE WHEN Status='Absent' THEN 1 ELSE 0 END) AS AbsentDays,
        ROUND(SUM(
            CASE
                WHEN CheckIn IS NOT NULL AND CheckOut IS NOT NULL
                THEN TIME_TO_SEC(TIMEDIFF(CheckOut, CheckIn)) / 3600
                ELSE 0
            END
        ), 2) AS WorkedHours
    FROM STAFF_ATTENDANCE
    GROUP BY StaffID
) a
    ON st.UserID = a.StaffID

LEFT JOIN (
    SELECT
        StaffID,
        COUNT(*) AS TotalTasks,
        SUM(CASE WHEN Status='Completed' THEN 1 ELSE 0 END) AS CompletedTasks
    FROM STAFF_TASK
    GROUP BY StaffID
) t
    ON st.UserID = t.StaffID

LEFT JOIN (
    SELECT
        StaffID,
        ROUND(AVG(Rating), 2) AS AvgRating
    FROM STAFF_REVIEW
    GROUP BY StaffID
) r
    ON st.UserID = r.StaffID

ORDER BY u.Name
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Staff Performance</title>
<style>
body{font-family:Arial;background:#f4f6f9}
.container{width:95%;max-width:1200px;margin:30px auto}
.card{background:#fff;padding:20px}
table{width:100%;border-collapse:collapse}
th,td{padding:9px;border:1px solid #ddd;text-align:center}
th{background:#343a40;color:white}
</style>
</head>
<body>
<div class="container">
<p><a href="admin_dashboard.php">← Dashboard</a></p>
<div class="card">
<h1>Staff Performance Summary</h1>
<p>This report uses attendance, working hours, assigned/completed tasks, and student ratings.</p>

<table>
<tr>
    <th>Staff</th>
    <th>Position</th>
    <th>Shift</th>
    <th>Present</th>
    <th>Late</th>
    <th>Absent</th>
    <th>Worked Hours</th>
    <th>Tasks</th>
    <th>Completed</th>
    <th>Avg Rating</th>
</tr>

<?php foreach($rows as $r): ?>
<tr>
    <td><?= htmlspecialchars($r['Name']) ?></td>
    <td><?= htmlspecialchars($r['Position']) ?></td>
    <td><?= htmlspecialchars($r['Shift']) ?></td>
    <td><?= $r['PresentDays'] ?></td>
    <td><?= $r['LateDays'] ?></td>
    <td><?= $r['AbsentDays'] ?></td>
    <td><?= $r['WorkedHours'] ?></td>
    <td><?= $r['TotalTasks'] ?></td>
    <td><?= $r['CompletedTasks'] ?></td>
    <td><?= $r['AvgRating'] ?? '-' ?></td>
</tr>
<?php endforeach; ?>
</table>

</div>
</div>
</body>
</html>
