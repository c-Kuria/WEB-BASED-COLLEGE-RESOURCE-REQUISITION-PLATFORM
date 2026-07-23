<?php

include '../includes/session.php';

if ($_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

include '../config/db.php';

include '../includes/header.php';
include '../includes/sidebar.php';

/* Statistics */

$totalUsers = mysqli_fetch_assoc(
    mysqli_query($conn, "
SELECT COUNT(*) total
FROM users")
)['total'];

$totalClubs = mysqli_fetch_assoc(
    mysqli_query($conn, "
SELECT COUNT(*) total
FROM clubs")
)['total'];

$totalResources = mysqli_fetch_assoc(
    mysqli_query($conn, "
SELECT COUNT(*) total
FROM resources")
)['total'];

$totalOfficers = mysqli_fetch_assoc(
    mysqli_query($conn, "
SELECT COUNT(*) total
FROM approving_officers")
)['total'];

$pending = mysqli_fetch_assoc(
    mysqli_query($conn, "
SELECT COUNT(*) total
FROM requisitions
WHERE status='Pending'")
)['total'];

$approved = mysqli_fetch_assoc(
    mysqli_query($conn, "
SELECT COUNT(*) total
FROM requisitions
WHERE status='Approved'")
)['total'];
?>

<div class="main">

    <h1>System Reports</h1>

    <div class="cards">

        <div class="card">

            <h3>Total Users</h3>

            <h2><?= $totalUsers ?></h2>

        </div>

        <div class="card">

            <h3>Total Clubs</h3>

            <h2><?= $totalClubs ?></h2>

        </div>

        <div class="card">

            <h3>Total Resources</h3>

            <h2><?= $totalResources ?></h2>

        </div>

        <div class="card">

            <h3>Approving Officers</h3>

            <h2><?= $totalOfficers ?></h2>

        </div>

        <div class="card">

            <h3>Pending Requests</h3>

            <h2><?= $pending ?></h2>

        </div>

        <div class="card">

            <h3>Approved Requests</h3>

            <h2><?= $approved ?></h2>

        </div>

    </div>

    <?php

$recent=mysqli_query($conn,"

SELECT

r.requisition_number,

c.club_name,

res.resource_name,

r.status,

r.submitted_at

FROM requisitions r

JOIN clubs c

ON r.club_id=c.club_id

JOIN resources res

ON r.resource_id=res.resource_id

ORDER BY submitted_at DESC

LIMIT 10

");

?>

<h2>Latest Requisitions</h2>

<table>

<tr>

<th>Number</th>

<th>Club</th>

<th>Resource</th>

<th>Status</th>

<th>Date</th>

</tr>

<?php while($row=mysqli_fetch_assoc($recent)){ ?>

<tr>

<td><?= $row['requisition_number']; ?></td>

<td><?= htmlspecialchars($row['club_name']); ?></td>

<td><?= htmlspecialchars($row['resource_name']); ?></td>

<td><?= $row['status']; ?></td>

<td><?= $row['submitted_at']; ?></td>

</tr>

<?php } ?>

</table>

<?php

$usage=mysqli_query($conn,"

SELECT

resources.resource_name,

COUNT(requisitions.resource_id) total

FROM resources

LEFT JOIN requisitions

ON resources.resource_id=requisitions.resource_id

GROUP BY resources.resource_id

ORDER BY total DESC

");

?>

<h2>Resource Usage</h2>

<table>

<tr>

<th>Resource</th>

<th>Times Requested</th>

</tr>

<?php while($row=mysqli_fetch_assoc($usage)){ ?>

<tr>

<td><?= htmlspecialchars($row['resource_name']); ?></td>

<td><?= $row['total']; ?></td>

</tr>

<?php } ?>

</table>

<?php

$clubs=mysqli_query($conn,"

SELECT

clubs.club_name,

COUNT(requisitions.club_id) total

FROM clubs

LEFT JOIN requisitions

ON clubs.club_id=requisitions.club_id

GROUP BY clubs.club_id

ORDER BY total DESC

");

?>

<h2>Club Activity</h2>

<table>

<tr>

<th>Club</th>

<th>Requests</th>

</tr>

<?php while($row=mysqli_fetch_assoc($clubs)){ ?>

<tr>

<td><?= htmlspecialchars($row['club_name']); ?></td>

<td><?= $row['total']; ?></td>

</tr>

<?php } ?>

</table>