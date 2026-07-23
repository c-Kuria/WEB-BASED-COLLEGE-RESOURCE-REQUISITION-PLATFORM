<?php
include '../includes/session.php';

if($_SESSION['role']!="admin"){
    header("Location: ../login.php");
    exit();
}

include '../config/db.php';

if(!isset($_GET['id'])){
    header("Location: clubs.php");
    exit();
}

$id = intval($_GET['id']);

$stmt = mysqli_prepare($conn,"
SELECT * FROM clubs
WHERE club_id=?");

mysqli_stmt_bind_param($stmt,"i",$id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result)==0){
    header("Location: clubs.php");
    exit();
}

$club = mysqli_fetch_assoc($result);

$message="";

if(isset($_POST['update'])){

    $club_name = trim($_POST['club_name']);
    $description = trim($_POST['description']);

    $check=mysqli_prepare($conn,"
    SELECT club_id
    FROM clubs
    WHERE club_name=?
    AND club_id<>?");

    mysqli_stmt_bind_param(
        $check,
        "si",
        $club_name,
        $id
    );

    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);

    if(mysqli_stmt_num_rows($check)>0){

        $message="<p style='color:red;'>Club name already exists.</p>";

    }else{

        $update=mysqli_prepare($conn,"
        UPDATE clubs
        SET club_name=?, description=?
        WHERE club_id=?");

        mysqli_stmt_bind_param(
            $update,
            "ssi",
            $club_name,
            $description,
            $id
        );

        mysqli_stmt_execute($update);

        header("Location: clubs.php");
        exit();
    }

}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main">

<h1>Edit Club</h1>

<?= $message ?>

<div class="form-container">

<form method="POST">

<div class="form-group">

<label>Club Name</label>

<input
type="text"
name="club_name"
value="<?= htmlspecialchars($club['club_name']) ?>"
required>

</div>

<div class="form-group">

<label>Description</label>

<textarea
name="description"
rows="4"><?= htmlspecialchars($club['description']) ?></textarea>

</div>

<button
type="submit"
name="update"
class="btn">

Update Club

</button>

</form>

</div>

</div>

<?php include '../includes/footer.php'; ?>