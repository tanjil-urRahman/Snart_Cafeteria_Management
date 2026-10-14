<?php

session_start();
require 'db.php';


/* =========================================================
   ADMIN ONLY
========================================================= */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Admin'
) {
    header("Location: login.php");
    exit();
}


$message = '';
$error = '';


/* =========================================================
   UPDATE STAFF
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $staffId = (int)($_POST['staff_id'] ?? 0);

    $position = trim(
        $_POST['position'] ?? ''
    );

    $shift = $_POST['shift'] ?? '';

    $salary = (float)(
        $_POST['salary'] ?? 0
    );

    /*
    |--------------------------------------------------------------------------
    | COUNTER
    |--------------------------------------------------------------------------
    |
    | Counter can temporarily be Unassigned.
    | Otherwise ONLY Counter 1, 2, 3 or 4 is accepted.
    |
    */

    $counterId = null;

    if (
        isset($_POST['counter_id']) &&
        $_POST['counter_id'] !== ''
    ) {
        $counterId =
            (int)$_POST['counter_id'];
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($staffId <= 0) {

        $error =
            "Invalid staff account.";

    } elseif ($position === '') {

        $error =
            "Position is required.";

    } elseif (
        !in_array(
            $shift,
            [
                'Morning',
                'Evening',
                'Night'
            ],
            true
        )
    ) {

        $error =
            "Invalid shift.";

    } elseif ($salary < 0) {

        $error =
            "Salary cannot be negative.";

    } elseif (
        $counterId !== null &&
        !in_array(
            $counterId,
            [1, 2, 3, 4],
            true
        )
    ) {

        /*
        |--------------------------------------------------------------------------
        | VERY IMPORTANT BACKEND PROTECTION
        |--------------------------------------------------------------------------
        |
        | Even if someone changes HTML manually,
        | Counter 5, Counter 6, etc. cannot be assigned.
        |
        */

        $error =
            "Only Counter 1, Counter 2, Counter 3 or Counter 4 can be assigned.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | MAKE SURE STAFF EXISTS
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                "
                SELECT UserID
                FROM STAFF
                WHERE UserID = ?
                "
            );

            $stmt->execute([
                $staffId
            ]);

            if (!$stmt->fetch()) {

                $error =
                    "Staff account not found.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | IF COUNTER SELECTED, VERIFY IT REALLY EXISTS
                |--------------------------------------------------------------------------
                */

                if ($counterId !== null) {

                    $stmt = $pdo->prepare(
                        "
                        SELECT CounterID
                        FROM COUNTER
                        WHERE CounterID = ?
                          AND CounterID BETWEEN 1 AND 4
                        LIMIT 1
                        "
                    );

                    $stmt->execute([
                        $counterId
                    ]);

                    if (!$stmt->fetch()) {

                        throw new Exception(
                            "Selected counter does not exist."
                        );
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | UPDATE STAFF
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare(
                    "
                    UPDATE STAFF
                    SET
                        Position = ?,
                        Shift = ?,
                        Salary = ?,
                        CounterID = ?
                    WHERE UserID = ?
                    "
                );

                $stmt->execute([
                    $position,
                    $shift,
                    $salary,
                    $counterId,
                    $staffId
                ]);


                $message =
                    "Staff information and counter assignment updated successfully.";
            }

        } catch (Exception $e) {

            error_log(
                "Staff update error: " .
                $e->getMessage()
            );

            $error =
                $e->getMessage();
        }
    }
}


/* =========================================================
   GET ALL STAFF
========================================================= */

$stmt = $pdo->query(
    "
    SELECT
        st.UserID,
        u.Name,
        u.Email,
        st.Position,
        st.Shift,
        st.Salary,
        st.CounterID,
        c.CounterName
    FROM STAFF st

    JOIN `USER` u
        ON st.UserID = u.UserID

    LEFT JOIN COUNTER c
        ON st.CounterID = c.CounterID

    ORDER BY
        u.Name
    "
);

$staff =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/* =========================================================
   GET ONLY THE FOUR FIXED COUNTERS
========================================================= */

$stmt = $pdo->query(
    "
    SELECT
        CounterID,
        CounterName,
        Status,
        Location
    FROM COUNTER
    WHERE CounterID BETWEEN 1 AND 4
    ORDER BY CounterID
    "
);

$counters =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Staff & Shift Management
</title>


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    font-family:
        Arial,
        sans-serif;

    background:
        #f4f6f9;

    color:
        #333;
}


.container {

    width: 95%;

    max-width:
        1200px;

    margin:
        30px auto;
}


.card {

    background:
        white;

    padding:
        20px;

    margin-bottom:
        20px;

    border-radius:
        8px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,0.08);
}


.topbar {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    margin-bottom:
        20px;
}


.back {

    text-decoration:
        none;

    background:
        #343a40;

    color:
        white;

    padding:
        9px 14px;

    border-radius:
        5px;
}


table {

    width:
        100%;

    border-collapse:
        collapse;
}


th {

    background:
        #343a40;

    color:
        white;

    padding:
        11px;

    text-align:
        left;
}


td {

    padding:
        10px;

    border-bottom:
        1px solid #ddd;

    vertical-align:
        middle;
}


input,
select {

    width:
        100%;

    padding:
        8px;

    border:
        1px solid #ccc;

    border-radius:
        4px;
}


button {

    padding:
        9px 14px;

    background:
        #007bff;

    color:
        white;

    border:
        none;

    border-radius:
        5px;

    cursor:
        pointer;
}


button:hover {

    background:
        #0069d9;
}


.ok {

    background:
        #d4edda;

    color:
        #155724;

    padding:
        12px;

    border-radius:
        5px;

    margin-top:
        12px;
}


.err {

    background:
        #f8d7da;

    color:
        #721c24;

    padding:
        12px;

    border-radius:
        5px;

    margin-top:
        12px;
}


.counter-info {

    background:
        #eef5ff;

    border-left:
        4px solid #007bff;

    padding:
        12px;

    margin-top:
        15px;
}


.unassigned {

    color:
        #dc3545;

    font-weight:
        bold;
}


.assigned {

    color:
        #28a745;

    font-weight:
        bold;
}


.small {

    font-size:
        12px;

    color:
        #666;
}


@media
(max-width: 900px) {

    .card {

        overflow-x:
            auto;
    }


    table {

        min-width:
            1000px;
    }
}

</style>

</head>


<body>


<div class="container">


    <div class="topbar">

        <h1>
            Staff & Shift Management
        </h1>

        <a
            href="admin_dashboard.php"
            class="back"
        >
            ← Admin Dashboard
        </a>

    </div>


    <div class="card">

        <h2>
            Staff Counter Assignment
        </h2>

        <p>
            Each staff member can be assigned to
            <strong>
                Counter 1, Counter 2,
                Counter 3 or Counter 4.
            </strong>
        </p>

        <p>
            After assignment, that staff member can
            manage stock and create sales only from
            their assigned counter.
        </p>


        <div class="counter-info">

            <strong>
                Fixed Cafeteria Counters
            </strong>

            <br><br>

            <?php foreach ($counters as $counter): ?>

                <?= htmlspecialchars(
                    $counter['CounterName']
                ) ?>

                —

                <?= htmlspecialchars(
                    $counter['Location']
                ) ?>

                —

                <?= htmlspecialchars(
                    $counter['Status']
                ) ?>

                <br>

            <?php endforeach; ?>

        </div>


        <?php if ($message): ?>

            <div class="ok">

                <?= htmlspecialchars(
                    $message
                ) ?>

            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="err">

                <?= htmlspecialchars(
                    $error
                ) ?>

            </div>

        <?php endif; ?>

    </div>



    <div class="card">


        <?php if (empty($staff)): ?>

            <p>
                No staff accounts found.
            </p>

        <?php else: ?>


            <table>


                <tr>

                    <th>
                        Name
                    </th>

                    <th>
                        Email
                    </th>

                    <th>
                        Position
                    </th>

                    <th>
                        Shift
                    </th>

                    <th>
                        Salary
                    </th>

                    <th>
                        Counter Assignment
                    </th>

                    <th>
                        Action
                    </th>

                </tr>



                <?php foreach ($staff as $s): ?>


                    <tr>


                        <form method="POST">


                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $s['Name']
                                    ) ?>

                                </strong>


                                <input
                                    type="hidden"
                                    name="staff_id"
                                    value="<?= (int)$s['UserID'] ?>"
                                >


                                <br>


                                <?php if (empty($s['CounterID'])): ?>

                                    <span class="unassigned">

                                        Not Assigned

                                    </span>

                                <?php else: ?>

                                    <span class="assigned">

                                        <?= htmlspecialchars(
                                            $s['CounterName']
                                        ) ?>

                                    </span>

                                <?php endif; ?>


                            </td>



                            <td>

                                <?= htmlspecialchars(
                                    $s['Email']
                                ) ?>

                            </td>



                            <td>

                                <input
                                    type="text"
                                    name="position"
                                    value="<?= htmlspecialchars(
                                        $s['Position']
                                    ) ?>"
                                    required
                                >

                            </td>



                            <td>


                                <select
                                    name="shift"
                                    required
                                >


                                    <?php

                                    foreach (
                                        [
                                            'Morning',
                                            'Evening',
                                            'Night'
                                        ]
                                        as $shift
                                    ):

                                    ?>


                                        <option
                                            value="<?= $shift ?>"
                                            <?= $s['Shift'] === $shift
                                                ? 'selected'
                                                : ''
                                            ?>
                                        >

                                            <?= $shift ?>

                                        </option>


                                    <?php endforeach; ?>


                                </select>


                            </td>



                            <td>


                                <input
                                    type="number"
                                    name="salary"
                                    step="0.01"
                                    min="0"
                                    value="<?= htmlspecialchars(
                                        $s['Salary']
                                    ) ?>"
                                    required
                                >


                            </td>



                            <td>


                                <select
                                    name="counter_id"
                                >


                                    <option value="">

                                        Unassigned

                                    </option>


                                    <?php foreach ($counters as $counter): ?>


                                        <option

                                            value="<?= (int)$counter['CounterID'] ?>"

                                            <?= (int)$s['CounterID'] ===
                                                (int)$counter['CounterID']
                                                ? 'selected'
                                                : ''
                                            ?>

                                        >

                                            <?= htmlspecialchars(
                                                $counter['CounterName']
                                            ) ?>

                                            —

                                            <?= htmlspecialchars(
                                                $counter['Location']
                                            ) ?>

                                        </option>


                                    <?php endforeach; ?>


                                </select>


                                <span class="small">

                                    Only Counter 1–4
                                    can be assigned.

                                </span>


                            </td>



                            <td>


                                <button
                                    type="submit"
                                >
                                    Save
                                </button>


                            </td>


                        </form>


                    </tr>


                <?php endforeach; ?>


            </table>


        <?php endif; ?>


    </div>


</div>


</body>

</html>