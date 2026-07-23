<?php
include '../includes/session.php';

if($_SESSION['role']!="admin"){
    header("Location: ../login.php");
    exit();
}

include '../config/db.php';

if(!isset($_GET['id'])){
    header("Location: resource_categories.php");
    exit();
}

$id = intval($_GET['id']);

$stmt = mysqli_prepare($conn,"
SELECT * FROM resource_categories
WHERE category_id=?");
    
mysqli_stmt_bind_param($stmt,"i",$id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result)==0){
    header("Location: resource_categories.php");
    exit();
}

$position = mysqli_fetch_assoc($result);

$message="";

if(isset($_POST['update'])){

    $position_name = trim($_POST['position_name']);
    $description = trim($_POST['description']);

    $check=mysqli_prepare($conn,"
    SELECT category_id
    FROM resource_categories
    WHERE category_name=?
    AND category_id<>?");

    mysqli_stmt_bind_param(
        $check,
        "si",
        $position_name,
        $id
    );

    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);

    if(mysqli_stmt_num_rows($check)>0){

        $message="<p style='color:red;'>Category name already exists.</p>";

    }else{

        $update=mysqli_prepare($conn,"
        UPDATE resource_categories
        SET category_name=?, description=?
        WHERE category_id=?");

        mysqli_stmt_bind_param(
            $update,
            "ssi",
            $position_name,
            $description,
            $id
        );

        mysqli_stmt_execute($update);

        header("Location: resource_categories.php");
        exit();
    }

}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main">

<h1>Edit Category</h1>

<?= $message ?>

<div class="form-container">

<form method="POST">

<div class="form-group">

<label>Category Name</label>

<input
type="text"
name="category_name"
value="<?= htmlspecialchars($position['category_name']) ?>"
required>

</div>

<div class="form-group">

<label>Description</label>

<textarea
name="description"
rows="4"><?= htmlspecialchars($position['description']) ?></textarea>

</div>

<button
type="submit"
name="update"
class="btn">

Update Category

</button>

</form>

</div>

</div>

<?php include '../includes/footer.php'; ?>