<?php

require_once __DIR__ . '/../includes/session.php';

if($_SESSION['role']!="admin"){
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

if(!isset($_GET['id'])){
    header("Location: resources.php");
    exit();
}

$id = intval($_GET['id']);

/* Load resource */

$stmt = mysqli_prepare($conn,"
SELECT *
FROM resources
WHERE resource_id=?");

mysqli_stmt_bind_param($stmt,"i",$id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result)==0){
    header("Location: resources.php");
    exit();
}

$resource = mysqli_fetch_assoc($result);

/* Load categories */

$categories=mysqli_query($conn,"
SELECT category_id, category_name
FROM resource_categories
ORDER BY category_name ASC
");

$message="";

if(isset($_POST['update'])){

    $resource_code = strtoupper(trim($_POST['resource_code']));
    $resource_name = trim($_POST['resource_name']);
    $category_id = intval($_POST['category_id']);
    $description = trim($_POST['description']);
    $availability = $_POST['availability_status'];

    /* Check duplicate code */

    $check=mysqli_prepare($conn,"
    SELECT resource_id
    FROM resources
    WHERE resource_code=?
    AND resource_id<>?");

    mysqli_stmt_bind_param(
        $check,
        "si",
        $resource_code,
        $id
    );

    mysqli_stmt_execute($check);

    mysqli_stmt_store_result($check);

    if(mysqli_stmt_num_rows($check)>0){

        $message="<p style='color:red;'>Resource code already exists.</p>";

    }else{

        $update=mysqli_prepare($conn,"
        UPDATE resources
        SET
            resource_code=?,
            resource_name=?,
            category_id=?,
            description=?,
            availability_status=?
        WHERE resource_id=?");

        mysqli_stmt_bind_param(

            $update,

            "ssissi",

            $resource_code,
            $resource_name,
            $category_id,
            $description,
            $availability,
            $id

        );

        mysqli_stmt_execute($update);

        $_SESSION['success']="Resource updated successfully.";

        header("Location: resources.php");
        exit();

    }

}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main">

<h1>Edit Resource</h1>

<?php require_once __DIR__ . '/../includes/flash.php'; ?>

<?= $message ?>

<div class="form-container">

<form method="POST">

<div class="form-group">

<label>Resource Code</label>

<input
type="text"
id="resource_code"
name="resource_code"
value="<?= htmlspecialchars($resource['resource_code']) ?>"
required>

</div>

<div class="form-group">

<label>Resource Name</label>

<input
type="text"
name="resource_name"
value="<?= htmlspecialchars($resource['resource_name']) ?>"
required>

</div>

<div class="form-group">

<label>Category</label>

<select name="category_id" required>

<?php while($cat=mysqli_fetch_assoc($categories)){ ?>

<option
value="<?= $cat['category_id']; ?>"
<?= ($cat['category_id']==$resource['category_id']) ? 'selected' : ''; ?>>

<?= htmlspecialchars($cat['category_name']); ?>

</option>

<?php } ?>

</select>

</div>

<div class="form-group">

<label>Description</label>

<textarea
name="description"
rows="4"><?= htmlspecialchars($resource['description']) ?></textarea>

</div>

<div class="form-group">

<label>Availability</label>

<select name="availability_status">

<option value="Available"
<?= ($resource['availability_status']=="Available") ? "selected" : ""; ?>>
Available
</option>

<option value="Unavailable"
<?= ($resource['availability_status']=="Unavailable") ? "selected" : ""; ?>>
Unavailable
</option>

</select>

</div>

<button class="btn" name="update">
Update Resource
</button>

</form>

</div>

</div>

<script>
document.getElementById("resource_code").addEventListener("keyup",function(){
    this.value=this.value.toUpperCase();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>