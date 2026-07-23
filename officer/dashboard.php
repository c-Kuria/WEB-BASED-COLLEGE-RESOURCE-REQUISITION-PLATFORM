<?php

include '../includes/session.php';

if($_SESSION['role']!="officer"){
    header("Location: ../login.php");
    exit();
}

include '../config/db.php';
include '../includes/header.php';
include '../includes/officer_sidebar.php';

$user_id = $_SESSION['user_id'];

/* Find logged-in officer */

$stmt = mysqli_prepare($conn,"
SELECT officer_id
FROM approving_officers
WHERE user_id=?");

mysqli_stmt_bind_param($stmt,"i",$user_id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$officer = mysqli_fetch_assoc($result);

$officer_id = $officer['officer_id'];

/* Pending */

$pending = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM requisition_approvals
WHERE officer_id=$officer_id
AND status='Pending'
"))['total'];

/* Approved */

$approved = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM requisition_approvals
WHERE officer_id=$officer_id
AND status='Approved'
"))['total'];

/* Rejected */

$rejected = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM requisition_approvals
WHERE officer_id=$officer_id
AND status='Rejected'
"))['total'];

/* Delegated */

$delegated = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM requisition_approvals
WHERE officer_id=$officer_id
AND assigned_as='Proxy'
"))['total'];

$requests = mysqli_query($conn,"

SELECT

r.requisition_number,

res.resource_name,

ra.approval_id

FROM requisition_approvals ra

JOIN requisitions r

ON ra.requisition_id=r.requisition_id

JOIN resources res

ON r.resource_id=res.resource_id

WHERE

ra.officer_id=$officer_id

AND

ra.status='Pending'

ORDER BY r.submitted_at DESC

LIMIT 5

");
?>

<div class="cards">

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

<div class="card">
<h3>Delegated</h3>
<h2><?= $delegated ?></h2>
</div>

</div>

<h2>Pending Requests</h2>

<table>

<tr>

<th>Requisition</th>

<th>Resource</th>

<th>Action</th>

</tr>

<?php while($row=mysqli_fetch_assoc($requests)){ ?>

<tr>

<td><?= $row['requisition_number']; ?></td>

<td><?= htmlspecialchars($row['resource_name']); ?></td>

<td>

<a class="btn"

href="review_request.php?id=<?= $row['approval_id']; ?>">

Review

</a>

</td>

</tr>

<?php } ?>

</table>

<?php include '../includes/footer.php'; ?>