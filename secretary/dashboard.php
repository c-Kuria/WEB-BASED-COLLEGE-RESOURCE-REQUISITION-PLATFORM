<?php

require_once __DIR__ . '/../includes/session.php';

if ($_SESSION['role'] != "secretary") {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/secretary_sidebar.php';

$user_id = $_SESSION['user_id'];

/* Statistics */

$total = mysqli_fetch_assoc(mysqli_query($conn, "
SELECT COUNT(*) total
FROM requisitions
WHERE secretary_id=$user_id"))['total'];

$pending = mysqli_fetch_assoc(mysqli_query($conn, "
SELECT COUNT(*) total
FROM requisitions
WHERE secretary_id=$user_id
AND status='Pending'"))['total'];

$approved = mysqli_fetch_assoc(mysqli_query($conn, "
SELECT COUNT(*) total
FROM requisitions
WHERE secretary_id=$user_id
AND status='Approved'"))['total'];

$rejected = mysqli_fetch_assoc(mysqli_query($conn, "
SELECT COUNT(*) total
FROM requisitions
WHERE secretary_id=$user_id
AND status='Rejected'"))['total'];

/* Notifications */

$notifications = mysqli_query($conn, "
SELECT *

FROM notifications

WHERE user_id=$user_id

ORDER BY created_at DESC

LIMIT 5");

/* Recent requisitions */

$recent = mysqli_query($conn, "

SELECT

requisition_number,

requisitions.status AS status,

submitted_at,

resources.resource_name

FROM requisitions

JOIN resources

ON requisitions.resource_id=resources.resource_id

WHERE secretary_id=$user_id

ORDER BY submitted_at DESC

LIMIT 5

");

?>

<div class="main">

    <h1>

        Welcome,

        <?= htmlspecialchars($_SESSION['full_name']); ?>

    </h1>

    <div class="cards">

        <div class="card">

            <h3>Total Requisitions</h3>

            <h2><?= $total ?></h2>

        </div>

        <div class="card">

            <h3>Pending</h3>

            <h2><?= $pending ?></h2>

        </div>

        <div class="card">

            <h3>Approved</h3>

            <h2><?= $approved ?></h2>

        </div>

        <div class="card">

            <h3>Rejected</h3>

            <h2><?= $rejected ?></h2>

        </div>

    </div>

    <h2>Recent Notifications</h2>

    <table>

        <tr>

            <th>Notification</th>

            <th>Date</th>

        </tr>

        <?php while ($row = mysqli_fetch_assoc($notifications)) { ?>

            <tr>

                <td><?= htmlspecialchars($row['message']); ?></td>

                <td><?= $row['created_at']; ?></td>

            </tr>

        <?php } ?>

    </table>

    <h2>Recent Requisitions</h2>

    <table>

        <tr>

            <th>Requisition No.</th>

            <th>Resource</th>

            <th>Status</th>

            <th>Date</th>

        </tr>

        <?php while ($row = mysqli_fetch_assoc($recent)) { ?>

            <tr>

                <td><?= $row['requisition_number']; ?></td>

                <td><?= htmlspecialchars($row['resource_name']); ?></td>

                <td>

                    <span class="badge <?= strtolower($row['status']); ?>">

                        <?= $row['status']; ?>

                    </span>

                </td>

                <td><?= $row['submitted_at']; ?></td>

            </tr>

        <?php } ?>

    </table>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>