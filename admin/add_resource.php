<?php

require_once __DIR__ . '/../includes/session.php';

if($_SESSION['role']!="admin"){
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$message="";

/* Load active categories */
$categories=mysqli_query($conn,"
SELECT category_id, category_name
FROM resource_categories
ORDER BY category_name ASC
");

if(isset($_POST['save'])){

    $resource_code=strtoupper(trim($_POST['resource_code']));
    $resource_name=trim($_POST['resource_name']);
    $category_id=intval($_POST['category_id']);
    $description=trim($_POST['description']);
    $availability_status=$_POST['availability_status'];

    /* Check duplicate code */

    $check=mysqli_prepare($conn,"
    SELECT resource_id
    FROM resources
    WHERE resource_code=?");

    mysqli_stmt_bind_param($check,"s",$resource_code);

    mysqli_stmt_execute($check);

    mysqli_stmt_store_result($check);

    if(mysqli_stmt_num_rows($check)>0){

        $message="<p style='color:red;'>Resource code already exists.</p>";

    }else{

        $stmt=mysqli_prepare($conn,"
        INSERT INTO resources
        (
            resource_code,
            resource_name,
            category_id,
            description,
            availability_status
        )
        VALUES(?,?,?,?,?)");

        mysqli_stmt_bind_param(

            $stmt,

            "ssiss",

            $resource_code,
            $resource_name,
            $category_id,
            $description,
            $availability_status

        );

        mysqli_stmt_execute($stmt);

        $_SESSION['success']="Resource added successfully.";

        header("Location: resources.php");

        exit();

    }

}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

?>

<div class="main">

<h1>Add Resource</h1>

<?php require_once __DIR__ . '/../includes/flash.php'; ?>

<?= $message; ?>

<div class="form-container">

<form method="POST">

<div class="form-group">

<label>Resource Code</label>

<input
type="text"
name="resource_code"
id="resource_code"
required>

</div>

<div class="form-group">

<label>Resource Name</label>

<input
type="text"
name="resource_name"
required>

</div>

<div class="form-group">

<label>Category</label>

<select
name="category_id"
required>

<option value="">-- Select Category --</option>

<?php

while($cat=mysqli_fetch_assoc($categories)){

?>

<option
value="<?= $cat['category_id']; ?>">

<?= htmlspecialchars($cat['category_name']); ?>

</option>

<?php

}

?>

</select>

</div>

<div class="form-group">

<label>Description</label>

<textarea
name="description"
rows="4"></textarea>

</div>

<div class="form-group">

<label>Availability</label>

<select
name="availability_status">

<option value="Available">
Available
</option>

<option value="Unavailable">
Unavailable
</option>

</select>

</div>

<button
class="btn"
name="save">

Save Resource

</button>

</form>

</div>

</div>

<script>

/* Automatically convert code to uppercase */

document.getElementById("resource_code")
.addEventListener("keyup",function(){

this.value=this.value.toUpperCase();

});

</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>