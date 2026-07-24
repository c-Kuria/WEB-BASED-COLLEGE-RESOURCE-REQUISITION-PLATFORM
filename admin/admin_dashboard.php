<?php

// error_reporting(E_ALL);
// ini_set('display_errors', '1')

require_once '../includes/session.php';

if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

/* Dashboard Statistics */

$clubs = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) total FROM clubs"))['total'];

$students = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) total FROM club_officials"))['total'];

$officers = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) total FROM officers"))['total'];

$resources = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) total FROM resources"))['total'];

$pending = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) total
FROM requisitions
WHERE status='Pending'"))['total'];

$approved = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) total
FROM requisitions
WHERE status='Approved'"))['total'];

$recent = mysqli_query(
    $conn,
    "
        SELECT
            r.requisitionID,
            r.requisitionNumber,
            r.requestTime,
            r.status,
            co.officialName,
            c.clubName,
            res.resourceName
        FROM requisitions r

        INNER JOIN club_officials co
            ON r.submittedByAdmNo = co.admNo

        INNER JOIN clubs c
            ON co.clubNumber = c.clubNumber

        INNER JOIN resources res
            ON r.resourceID = res.resourceID

        ORDER BY r.requestTime DESC
        LIMIT 5
    "
);

if (!$recent) {
    die('Recent requisitions query failed: ' . mysqli_error($conn));
}

?>

<div class="main">

<h1>Admin Dashboard</h1>

<div class="dashboard-grid">

<div class="card">
<h3>Clubs</h3>
<h1><?= $clubs ?></h1>
</div>

<div class="card">
    <h3>Club Officials</h3>
    <h1><?= (int) $officials; ?></h1>
</div>

<div class="card">
<h3>Officers</h3>
<h1><?= $officers ?></h1>
</div>

<div class="card">
<h3>Resources</h3>
<h1><?= $resources ?></h1>
</div>

<div class="card">
<h3>Pending Requisitions</h3>
<h1><?= $pending ?></h1>
</div>

<div class="card">
    <h3>Rejected Requisitions</h3>
    <h1><?= (int) $rejected; ?></h1>
</div>

<div class="card">
<h3>Approved Requisitions</h3>
<h1><?= $approved ?></h1>
</div>

</div>

<br>

<div class="card">

<h2>Recent Requisitions</h2>

<table class="data-table">

    <thead>
        <tr>
            <th>Requisition</th>
            <th>Club</th>
            <th>Submitted By</th>
            <th>Resource</th>
            <th>Status</th>
            <th>Date</th>
        </tr>
    </thead>

    <tbody>

        <?php if (mysqli_num_rows($recent) > 0): ?>

            <?php while ($row = mysqli_fetch_assoc($recent)): ?>

                <tr>
                    <td>
                        <?php
                        if (!empty($row['requisitionNumber'])) {
                            echo htmlspecialchars($row['requisitionNumber']);
                        } else {
                            echo 'RQ-' . str_pad(
                                $row['requisitionID'],
                                3,
                                '0',
                                STR_PAD_LEFT
                            );
                        }
                        ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['clubName']); ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['officialName']); ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['resourceName']); ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['status']); ?>
                    </td>

                    <td>
                        <?= date(
                            'd M Y',
                            strtotime($row['requestTime'])
                        ); ?>
                    </td>
                </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="6" class="empty-state">
                    No requisitions have been submitted yet.
                </td>
            </tr>

        <?php endif; ?>

    </tbody>

</table>

</div>

</div>

<?php require_once '../includes/footer.php'; ?>