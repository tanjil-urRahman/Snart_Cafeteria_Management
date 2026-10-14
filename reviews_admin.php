<?php

session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$message = '';


/*
|--------------------------------------------------------------------------
| DELETE A REVIEW (moderation)
|--------------------------------------------------------------------------
*/

if (isset($_POST['delete_food_review'])) {

    $id = (int)($_POST['review_id'] ?? 0);

    $stmt = $pdo->prepare("DELETE FROM FOOD_REVIEW WHERE ReviewID = ?");
    $stmt->execute([$id]);

    $message = "Food review removed.";
}

if (isset($_POST['delete_staff_review'])) {

    $id = (int)($_POST['review_id'] ?? 0);

    $stmt = $pdo->prepare("DELETE FROM STAFF_REVIEW WHERE ReviewID = ?");
    $stmt->execute([$id]);

    $message = "Staff review removed.";
}


/*
|--------------------------------------------------------------------------
| AVERAGE RATING PER FOOD ITEM
|--------------------------------------------------------------------------
*/

$avgFood = $pdo->query(
    "SELECT f.FoodID, f.FoodName,
            ROUND(AVG(fr.Rating), 2) AS AvgRating,
            COUNT(*) AS ReviewCount
     FROM FOOD_REVIEW fr
     INNER JOIN FOOD f ON fr.FoodID = f.FoodID
     GROUP BY f.FoodID, f.FoodName
     ORDER BY AvgRating DESC"
)->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| ALL FOOD REVIEWS
|--------------------------------------------------------------------------
*/

$foodReviews = $pdo->query(
    "SELECT fr.ReviewID, u.Name AS StudentName, f.FoodName,
            fr.Rating, fr.ReviewComment, fr.ReviewDate
     FROM FOOD_REVIEW fr
     INNER JOIN STUDENT s ON fr.StudentID = s.UserID
     INNER JOIN `USER` u ON s.UserID = u.UserID
     INNER JOIN FOOD f ON fr.FoodID = f.FoodID
     ORDER BY fr.ReviewDate DESC"
)->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| AVERAGE RATING PER STAFF MEMBER
|--------------------------------------------------------------------------
*/

$avgStaff = $pdo->query(
    "SELECT st.UserID, u.Name,
            ROUND(AVG(sr.Rating), 2) AS AvgRating,
            COUNT(*) AS ReviewCount
     FROM STAFF_REVIEW sr
     INNER JOIN STAFF st ON sr.StaffID = st.UserID
     INNER JOIN `USER` u ON st.UserID = u.UserID
     GROUP BY st.UserID, u.Name
     ORDER BY AvgRating DESC"
)->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| ALL STAFF REVIEWS
|--------------------------------------------------------------------------
*/

$staffReviews = $pdo->query(
    "SELECT sr.ReviewID, u.Name AS StudentName, us.Name AS StaffName,
            sr.Rating, sr.ReviewComment, sr.ReviewDate
     FROM STAFF_REVIEW sr
     INNER JOIN STUDENT s ON sr.StudentID = s.UserID
     INNER JOIN `USER` u ON s.UserID = u.UserID
     INNER JOIN `USER` us ON sr.StaffID = us.UserID
     ORDER BY sr.ReviewDate DESC"
)->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<title>Reviews - Admin</title>

<style>
* { box-sizing: border-box; }
body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; }
.header { background: #343a40; color: white; padding: 18px 30px; display: flex; justify-content: space-between; align-items: center; }
.header a { color: white; background: #007bff; text-decoration: none; padding: 9px 15px; border-radius: 5px; }
.container { width: 92%; max-width: 1200px; margin: 35px auto; }
.card { background: white; padding: 25px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.two-column { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th { background: #343a40; color: white; padding: 10px; text-align: left; }
td { padding: 10px; border-bottom: 1px solid #eee; }
.stars { color: #f1a300; font-weight: bold; }
.delete-btn { background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 5px; cursor: pointer; }
.notice { background: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin-bottom: 20px; }
.empty { color: #888; }
</style>

</head>
<body>

<div class="header">
    <h2>⭐ Reviews & Ratings - Admin</h2>
    <a href="admin_dashboard.php">← Dashboard</a>
</div>

<div class="container">

    <?php if ($message): ?>
        <div class="notice"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="two-column">

        <div class="card">
            <h2>🍽️ Average Food Ratings</h2>
            <?php if (empty($avgFood)): ?>
                <p class="empty">No food reviews yet.</p>
            <?php else: ?>
                <table>
                    <tr><th>Food</th><th>Avg Rating</th><th>Reviews</th></tr>
                    <?php foreach ($avgFood as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['FoodName']) ?></td>
                            <td><?= $r['AvgRating'] ?> / 5</td>
                            <td><?= $r['ReviewCount'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>👤 Average Staff Ratings</h2>
            <?php if (empty($avgStaff)): ?>
                <p class="empty">No staff reviews yet.</p>
            <?php else: ?>
                <table>
                    <tr><th>Staff</th><th>Avg Rating</th><th>Reviews</th></tr>
                    <?php foreach ($avgStaff as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['Name']) ?></td>
                            <td><?= $r['AvgRating'] ?> / 5</td>
                            <td><?= $r['ReviewCount'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

    </div>

    <div class="card">
        <h2>🍽️ All Food Reviews</h2>
        <?php if (empty($foodReviews)): ?>
            <p class="empty">No food reviews yet.</p>
        <?php else: ?>
            <table>
                <tr><th>Student</th><th>Food</th><th>Rating</th><th>Comment</th><th>Date</th><th></th></tr>
                <?php foreach ($foodReviews as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['StudentName']) ?></td>
                        <td><?= htmlspecialchars($r['FoodName']) ?></td>
                        <td class="stars"><?= str_repeat('⭐', (int)$r['Rating']) ?></td>
                        <td><?= htmlspecialchars($r['ReviewComment'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['ReviewDate']) ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Remove this review?');">
                                <input type="hidden" name="review_id" value="<?= $r['ReviewID'] ?>">
                                <button type="submit" name="delete_food_review" class="delete-btn">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>👤 All Staff Reviews</h2>
        <?php if (empty($staffReviews)): ?>
            <p class="empty">No staff reviews yet.</p>
        <?php else: ?>
            <table>
                <tr><th>Student</th><th>Staff</th><th>Rating</th><th>Comment</th><th>Date</th><th></th></tr>
                <?php foreach ($staffReviews as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['StudentName']) ?></td>
                        <td><?= htmlspecialchars($r['StaffName']) ?></td>
                        <td class="stars"><?= str_repeat('⭐', (int)$r['Rating']) ?></td>
                        <td><?= htmlspecialchars($r['ReviewComment'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['ReviewDate']) ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Remove this review?');">
                                <input type="hidden" name="review_id" value="<?= $r['ReviewID'] ?>">
                                <button type="submit" name="delete_staff_review" class="delete-btn">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

</div>

</body>
</html>