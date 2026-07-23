<?php

include '../includes/session.php';

if($_SESSION['role']!="admin"){
    header("Location:../login.php");
    exit();
}

include '../config/db.php';
include '../includes/header.php';
include '../includes/sidebar.php';

$result=mysqli_query($conn,"
SELECT *
FROM clubs
ORDER BY club_name ASC
");

?>

<div class="main">

<div class="page-header">

<h1>Manage Clubs</h1>

<a href="add_club.php" class="btn">
+ Add Club
</a>

</div>

<table>

<tr>

<th>ID</th>

<th>Club Name</th>

<th>Description</th>

<th>Status</th>

<th>Actions</th>

</tr>

<?php

while($club=mysqli_fetch_assoc($result)){

?>

<tr>

<td><?= $club['club_id']; ?></td>

<td><?= htmlspecialchars($club['club_name']); ?></td>

<td><?= htmlspecialchars($club['description']); ?></td>

<td><?= $club['status']; ?><td>

<td>

<a
class="btn-edit"
href="edit_club.php?id=<?= $club['club_id']; ?>">
Edit
</a>

|

<a
class="btn-toggle"
href="toggle_club.php?id=<?= $club['club_id']; ?>">

<?= $club['status']=="Active" ? "Deactivate":"Activate"; ?>

</a>

</td>

</tr>

<?php

}

?>

</table>

</div>

<?php include '../includes/footer.php'; ?>