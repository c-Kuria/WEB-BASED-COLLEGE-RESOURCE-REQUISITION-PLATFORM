<?php

include '../includes/session.php';

if($_SESSION['role']!="admin"){
    header("Location:../login.php");
    exit();
}

include '../config/db.php';

$message="";

if(isset($_POST['save'])){

    $club_name=trim($_POST['club_name']);
    $description=trim($_POST['description']);

    $check=mysqli_prepare($conn,"
    SELECT club_id
    FROM clubs
    WHERE club_name=?");

    mysqli_stmt_bind_param($check,"s",$club_name);

    mysqli_stmt_execute($check);

    mysqli_stmt_store_result($check);

    if(mysqli_stmt_num_rows($check)>0){

        $message="<p style='color:red;'>Club already exists.</p>";

    }else{

        $stmt=mysqli_prepare($conn,"
        INSERT INTO clubs
        (club_name,description)
        VALUES(?,?)");

        mysqli_stmt_bind_param(
        $stmt,
        "ss",
        $club_name,
        $description
        );

        mysqli_stmt_execute($stmt);

        header("Location:clubs.php");
        exit();

    }

}

include '../includes/header.php';
include '../includes/sidebar.php';

?>

<div class="main">

<h1>Add Club</h1>

<?= $message; ?>

<div class="form-container">

<form method="POST">

<div class="form-group">

<label>Club Name</label>

<input
type="text"
name="club_name"
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

Save Club

</button>

</form>

</div>

</div>

<?php include '../includes/footer.php'; ?>