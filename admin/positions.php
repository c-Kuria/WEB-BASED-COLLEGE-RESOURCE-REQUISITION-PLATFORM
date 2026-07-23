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
FROM positions
ORDER BY position_name ASC
");

?>

<div class="main">

<div class="page-header">

<h1>Manage Positions</h1>

<a href="add_position.php" class="btn">
+ Add Position
</a>

</div>

<table>

<tr>

<th>ID</th>

<th>Position Name</th>

<th>Description</th>

<th>Status</th>

<th>Actions</th>

</tr>

<?php

while($position=mysqli_fetch_assoc($result)){

?>

<tr>

<td><?= $position['position_id']; ?></td>

<td><?= htmlspecialchars($position['position_name']); ?></td>

<td><?= htmlspecialchars($position['description']); ?></td>

<td>

<span class="badge <?= strtolower($position['status']); ?>">

<?= $position['status']; ?>

</span>

</td>

<td>

<a
class="btn-edit"
href="edit_position.php?id=<?= $position['position_id']; ?>">
Edit
</a>

|

<a
class="btn-toggle"
href="toggle_position.php?id=<?= $position['position_id']; ?>"
onclick="return confirm('Are you sure you want to change this position status?');">

<?= $position['status']=="Active" ? "Deactivate":"Activate"; ?>

</a>

</td>

</tr>

<?php

}

?>

</table>

</div>

<?php include '../includes/footer.php'; ?>
