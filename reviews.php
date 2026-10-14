<?php

session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'Student') {
    header("Location: login.php");
    exit();
}

$studentID = $_SESSION['user_id'];
$name = $_SESSION['name'];

$error = '';
$success = '';


/*
|--------------------------------------------------------------------------
| SUBMIT FOOD REVIEW
|--------------------------------------------------------------------------
*/

if (isset($_POST['submit_food_review'])) {

    $foodId = (int)($_POST['food_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($foodId <= 0 || $rating < 1 || $rating > 5) {

        $error = "Please choose a food item and a rating between 1 and 5.";

    } else {

        // A student may only review food they have actually purchased
        $check = $pdo->prepare(
            "SELECT COUNT(*)
             FROM SALE_ITEM si
             INNER JOIN SALE s ON si.SaleID = s.SaleID
             WHERE s.StudentID = ? AND si.FoodID = ?"
        );

        $check->execute([$studentID, $foodId]);

        if ($check->fetchColumn() == 0) {

            $error = "You can only review food items you have purchased.";

        } else {

            $stmt = $pdo->prepare(
                "INSERT INTO FOOD_REVIEW
                    (StudentID, FoodID, Rating, ReviewComment, ReviewDate)
                 VALUES (?, ?, ?, ?, CURDATE())"
            );

            $stmt->execute([$studentID, $foodId, $rating, $comment ?: null]);

            $success = "Thanks for reviewing this food item!";
        }
    }
}


/*
|--------------------------------------------------------------------------
| SUBMIT STAFF REVIEW
|--------------------------------------------------------------------------
*/

if (isset($_POST['submit_staff_review'])) {

    $staffId = (int)($_POST['staff_id'] ?? 0);
    $rating = (int)($_POST['staff_rating'] ?? 0);
    $comment = trim($_POST['staff_comment'] ?? '');

    if ($staffId <= 0 || $rating < 1 || $rating > 5) {

        $error = "Please choose a staff member and a rating between 1 and 5.";

    } else {

        $stmt = $pdo->prepare(
            "INSERT INTO STAFF_REVIEW
                (StudentID, StaffID, Rating, ReviewComment, ReviewDate)
             VALUES (?, ?, ?, ?, CURDATE())"
        );

        $stmt->execute([$studentID, $staffId, $rating, $comment ?: null]);

        $success = "Thanks for reviewing our staff!";
    }
}


/*
|--------------------------------------------------------------------------
| FOODS THIS STUDENT HAS PURCHASED (eligible for review)
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT DISTINCT f.FoodID, f.FoodName
     FROM SALE_ITEM si
     INNER JOIN SALE s ON si.SaleID = s.SaleID
     INNER JOIN FOOD f ON si.FoodID = f.FoodID
     WHERE s.StudentID = ?
     ORDER BY f.FoodName"
);

$stmt->execute([$studentID]);
$purchasedFoods = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| ALL STAFF (for the staff review form)
|--------------------------------------------------------------------------
*/

$staffList = $pdo->query(
    "SELECT st.UserID, u.Name, st.Position
     FROM STAFF st
     INNER JOIN `USER` u ON st.UserID = u.UserID
     ORDER BY u.Name"
)->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| THIS STUDENT'S OWN REVIEW HISTORY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT f.FoodName, fr.Rating, fr.ReviewComment, fr.ReviewDate
     FROM FOOD_REVIEW fr
     INNER JOIN FOOD f ON fr.FoodID = f.FoodID
     WHERE fr.StudentID = ?
     ORDER BY fr.ReviewDate DESC"
);

$stmt->execute([$studentID]);
$myFoodReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    "SELECT u.Name AS StaffName, sr.Rating, sr.ReviewComment, sr.ReviewDate
     FROM STAFF_REVIEW sr
     INNER JOIN `USER` u ON sr.StaffID = u.UserID
     WHERE sr.StudentID = ?
     ORDER BY sr.ReviewDate DESC"
);

$stmt->execute([$studentID]);
$myStaffReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<title>Reviews - Smart Cafeteria</title>

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
    width: 90%;
    max-width: 1000px;
    margin: 35px auto;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.two-column {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 20px;
}

label {
    display: block;
    margin-top: 12px;
    margin-bottom: 5px;
    font-weight: bold;
}

input, select, textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
}

textarea { resize: vertical; min-height: 70px; }

button {
    margin-top: 15px;
    background: #28a745;
    color: white;
    border: none;
    padding: 10px 18px;
    border-radius: 5px;
    cursor: pointer;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

th, td {
    padding: 10px;
    border-bottom: 1px solid #eee;
    text-align: left;
}

th { background: #f8f9fa; }

.success {
    background: #d4edda;
    color: #155724;
    padding: 12px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.error {
    background: #f8d7da;
    color: #721c24;
    padding: 12px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.stars { color: #f1a300; font-weight: bold; }

.empty { color: #888; }

</style>

</head>
<body>

<div class="header">
    <h2>⭐ Reviews & Feedback</h2>
    <a href="dashboard.php">← Dashboard</a>
</div>

<div class="container">

    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="two-column">

        <!-- FOOD REVIEW FORM -->
        <div class="card">

            <h2>🍽️ Review a Food Item</h2>

            <?php if (empty($purchasedFoods)): ?>

                <p class="empty">
                    You haven't purchased any food yet — order something
                    from the <a href="order.php">menu</a> first!
                </p>

            <?php else: ?>

                <form method="POST">

                    <label>Food Item</label>
                    <select name="food_id" required>
                        <option value="">-- Select food you purchased --</option>
                        <?php foreach ($purchasedFoods as $food): ?>
                            <option value="<?= $food['FoodID'] ?>">
                                <?= htmlspecialchars($food['FoodName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label>Rating</label>
                    <select name="rating" required>
                        <option value="">-- Select rating --</option>
                        <option value="5">⭐⭐⭐⭐⭐ Excellent</option>
                        <option value="4">⭐⭐⭐⭐ Good</option>
                        <option value="3">⭐⭐⭐ Average</option>
                        <option value="2">⭐⭐ Poor</option>
                        <option value="1">⭐ Very Poor</option>
                    </select>

                    <label>Comment (optional)</label>
                    <textarea name="comment" placeholder="What did you think?"></textarea>

                    <button type="submit" name="submit_food_review">
                        Submit Food Review
                    </button>

                </form>

            <?php endif; ?>

        </div>

        <!-- STAFF REVIEW FORM -->
        <div class="card">

            <h2>👤 Review a Staff Member</h2>

            <form method="POST">

                <label>Staff Member</label>
                <select name="staff_id" required>
                    <option value="">-- Select staff --</option>
                    <?php foreach ($staffList as $staff): ?>
                        <option value="<?= $staff['UserID'] ?>">
                            <?= htmlspecialchars($staff['Name']) ?>
                            (<?= htmlspecialchars($staff['Position']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Rating</label>
                <select name="staff_rating" required>
                    <option value="">-- Select rating --</option>
                    <option value="5">⭐⭐⭐⭐⭐ Excellent</option>
                    <option value="4">⭐⭐⭐⭐ Good</option>
                    <option value="3">⭐⭐⭐ Average</option>
                    <option value="2">⭐⭐ Poor</option>
                    <option value="1">⭐ Very Poor</option>
                </select>

                <label>Comment (optional)</label>
                <textarea name="staff_comment" placeholder="How was the service?"></textarea>

                <button type="submit" name="submit_staff_review">
                    Submit Staff Review
                </button>

            </form>

        </div>

    </div>


    <!-- MY REVIEW HISTORY -->
    <div class="card">

        <h2>📜 My Food Reviews</h2>

        <?php if (empty($myFoodReviews)): ?>
            <p class="empty">No food reviews yet.</p>
        <?php else: ?>
            <table>
                <tr><th>Food</th><th>Rating</th><th>Comment</th><th>Date</th></tr>
                <?php foreach ($myFoodReviews as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['FoodName']) ?></td>
                        <td class="stars"><?= str_repeat('⭐', (int)$r['Rating']) ?></td>
                        <td><?= htmlspecialchars($r['ReviewComment'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['ReviewDate']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

    </div>

    <div class="card">

        <h2>📜 My Staff Reviews</h2>

        <?php if (empty($myStaffReviews)): ?>
            <p class="empty">No staff reviews yet.</p>
        <?php else: ?>
            <table>
                <tr><th>Staff</th><th>Rating</th><th>Comment</th><th>Date</th></tr>
                <?php foreach ($myStaffReviews as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['StaffName']) ?></td>
                        <td class="stars"><?= str_repeat('⭐', (int)$r['Rating']) ?></td>
                        <td><?= htmlspecialchars($r['ReviewComment'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['ReviewDate']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

    </div>

</div>

</body>
</html>