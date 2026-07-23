<?php

include '../includes/session.php';

if($_SESSION['role']!="admin"){
    header("Location:../login.php");
    exit();
}

include '../config/db.php';

$message="";

if(isset($_POST['save'])){

    $position_name=trim($_POST['position_name']);
    $description=trim($_POST['description']);

    $check=mysqli_prepare($conn,"
    SELECT position_id
    FROM positions
    WHERE position_name=?");

    mysqli_stmt_bind_param($check,"s",$position_name);

    mysqli_stmt_execute($check);

    mysqli_stmt_store_result($check);

    if(mysqli_stmt_num_rows($check)>0){

        $message="<p style='color:red;'>Position already exists.</p>";

    }else{

        $stmt=mysqli_prepare($conn,"
        INSERT INTO positions
        (position_name,description)
        VALUES(?,?)");

        mysqli_stmt_bind_param(
        $stmt,
        "ss",
        $position_name,
        $description
        );

        mysqli_stmt_execute($stmt);

        header("Location:positions.php");
        exit();

    }

}

include '../includes/header.php';
include '../includes/sidebar.php';

?>

<div class="main">

<h1>Add Position</h1>

<?= $message; ?>

<div class="form-container">

<form method="POST">

<div class="form-group">

<label>Position Name</label>

<input
type="text"
name="position_name"
required>

</div>

<div class="form-group">

<label>Description</label>

<textarea
name="description"
rows="4"></textarea>

</div>

<button
class="btn"
name="save">

Save Position

</button>

</form>

</div>

</div>

<?php include '../includes/footer.php'; ?>