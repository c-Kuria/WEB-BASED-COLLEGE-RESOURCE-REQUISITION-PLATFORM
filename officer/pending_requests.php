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

/* Get officer ID */

$stmt = mysqli_prepare($conn,"
SELECT officer_id
FROM approving_officers
WHERE user_id=?
");

mysqli_stmt_bind_param($stmt,"i",$user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$officer = mysqli_fetch_assoc($result);

$officer_id = $officer['officer_id'];

/* Pending requests */

$query = mysqli_query($conn,"

SELECT

ra.approval_id,

r.requisition_id,

r.requisition_number,

r.submitted_at,

c.club_name,

res.resource_name,

p.position_name

FROM requisition_approvals ra

JOIN requisitions r
ON ra.requisition_id=r.requisition_id

JOIN clubs c
ON r.club_id=c.club_id

JOIN resources res
ON r.resource_id=res.resource_id

JOIN approving_officers ao
ON ra.officer_id=ao.officer_id

JOIN positions p
ON ao.position_id=p.position_id

WHERE

ra.officer_id=$officer_id

AND

ra.status='Pending'

ORDER BY r.submitted_at ASC

");

?>

<div class="main">

<h1>Pending Requests</h1>

<input
type="text"
id="searchInput"
placeholder="Search...">

<table id="searchTable">

<tr>

<th>Requisition</th>

<th>Club</th>

<th>Resource</th>

<th>Approval Stage</th>

<th>Date</th>

<th>Action</th>

</tr>

<?php while($row=mysqli_fetch_assoc($query)){ ?>

<tr>

<td><?= htmlspecialchars($row['requisition_number']) ?></td>

<td><?= htmlspecialchars($row['club_name']) ?></td>

<td><?= htmlspecialchars($row['resource_name']) ?></td>

<td><?= htmlspecialchars($row['position_name']) ?></td>

<td><?= date('d M Y',strtotime($row['submitted_at'])) ?></td>

<td>

<a
class="btn"
href="review_request.php?id=<?= $row['approval_id']; ?>">

Review

</a>

</td>

</tr>

<?php } ?>

</table>

</div>

<?php include '../includes/footer.php'; ?>