<?php
require_once __DIR__ . '/../includes/session.php';

if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$query = "
SELECT
    r.resource_id,
    r.resource_code,
    r.resource_name,
    rc.category_name,
    r.availability_status,
    r.status
FROM resources r
INNER JOIN resource_categories rc
    ON r.category_id = rc.category_id
ORDER BY r.resource_name ASC
";

$result = mysqli_query($conn, $query);
?>

<div class="main">

<div class="page-header">
    <h1>Manage Resources</h1>

    <div>
        <input
            type="text"
            id="searchInput"
            class="search-box"
            placeholder="Search resource...">

        <a href="add_resource.php" class="btn">
            + Add Resource
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/flash.php'; ?>

<table id="dataTable">

<tr>
    <th>Code</th>
    <th>Resource</th>
    <th>Category</th>
    <th>Availability</th>
    <th>Status</th>
    <th>Actions</th>
</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?= htmlspecialchars($row['resource_code']) ?></td>

<td><?= htmlspecialchars($row['resource_name']) ?></td>

<td><?= htmlspecialchars($row['category_name']) ?></td>

<td>

<span class="badge <?= strtolower($row['availability_status']) ?>">

<?= $row['availability_status'] ?>

</span>

</td>

<td>

<span class="badge <?= strtolower($row['status']) ?>">

<?= $row['status'] ?>

</span>

</td>

<td>

<a
class="btn-edit"
href="edit_resource.php?id=<?= $row['resource_id']; ?>">

Edit

</a>

|

<a
class="btn-toggle"
href="toggle_resource.php?id=<?= $row['resource_id']; ?>"
onclick="return confirm('Change resource status?');">

<?= $row['status']=="Active" ? "Deactivate":"Activate"; ?>

</a>

</td>

</tr>

<?php } ?>

</table>

</div>

<script src="../assets/js/search.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>