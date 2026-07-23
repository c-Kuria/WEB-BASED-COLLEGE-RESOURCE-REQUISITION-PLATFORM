<?php

include '../includes/session.php';

if($_SESSION['role']!="admin"){
    header("Location: ../login.php");
    exit();
}

include '../config/db.php';
include '../includes/header.php';
include '../includes/sidebar.php';

$result=mysqli_query($conn,"
SELECT *
FROM resource_categories
ORDER BY category_name ASC
");

?>

<div class="main">

<div class="page-header">

<h1>Manage Resource Categories</h1>

<div>

<input
type="text"
id="searchInput"
placeholder="Search category..."
class="search-box">

<a href="add_resource_category.php" class="btn">
+ Add Category
</a>

</div>

</div>

<table id="dataTable">

<tr>

<th>ID</th>

<th>Category</th>

<th>Description</th>

<th>Status</th>

<th>Actions</th>

</tr>

<?php while($category=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?= $category['category_id']; ?></td>

<td><?= htmlspecialchars($category['category_name']); ?></td>

<td><?= htmlspecialchars($category['description']); ?></td>

<td>

<span class="badge <?= strtolower($category['status']); ?>">

<?= $category['status']; ?>

</span>

</td>

<td>

<a
class="btn-edit"
href="edit_resource_category.php?id=<?= $category['category_id']; ?>">
Edit
</a>

|

<a
class="btn-toggle"
onclick="return confirm('Change category status?');"
href="toggle_resource_category.php?id=<?= $category['category_id']; ?>">

<?= $category['status']=="Active" ? "Deactivate":"Activate"; ?>

</a>

</td>

</tr>

<?php } ?>

</table>

</div>

<script src="../assets/js/search.js"></script>

<?php include '../includes/footer.php'; ?>