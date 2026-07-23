<?php

include '../includes/session.php';

if($_SESSION['role'] != "admin"){
    header("Location: ../login.php");
    exit();
}

include '../config/db.php';
include '../includes/header.php';
include '../includes/sidebar.php';

$result = mysqli_query($conn,"
SELECT *
FROM users
ORDER BY full_name ASC
");

?>

<div class="main">

<h1>Manage Users</h1>

<br>

<a href="add_user.php" class="btn">
+ Add New User
</a>

<br><br>

<table>

<tr>

<th>ID</th>

<th>Photo</th>

<th>Name</th>

<th>Email</th>

<th>Role</th>

<th>Status</th>

<th>Action</th>

</tr>

<?php

while($row=mysqli_fetch_assoc($result)){

?>

<tr>

<td><?= $row['user_id']; ?></td>

<td>

<img
src="../assets/images/<?= $row['profile_photo']; ?>"
width="45">

</td>

<td><?= $row['full_name']; ?></td>

<td><?= $row['email']; ?></td>

<td><?= ucfirst($row['role']); ?></td>

<td><?= $row['status']; ?></td>

<td>

<a href="edit_user.php?id=<?= $row['user_id']; ?>">Edit</a>

|

<a href="toggle_user.php?id=<?= $row['user_id']; ?>">

<?= $row['status']=="Active" ? "Deactivate" : "Activate"; ?>

</a>

</td>

</tr>

<?php

}

?>

</table>

</div>

<?php

include '../includes/footer.php';

?>