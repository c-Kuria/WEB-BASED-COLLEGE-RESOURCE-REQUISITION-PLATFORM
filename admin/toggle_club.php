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

$get=mysqli_prepare($conn,"
SELECT status
FROM clubs
WHERE club_id=?");

mysqli_stmt_bind_param($get,"i",$id);

mysqli_stmt_execute($get);

$result=mysqli_stmt_get_result($get);

$club=mysqli_fetch_assoc($result);

$newStatus = ($club['status']=="Active")
            ? "Inactive"
            : "Active";

$update=mysqli_prepare($conn,"
UPDATE clubs
SET status=?
WHERE club_id=?");

mysqli_stmt_bind_param(
$update,
"si",
$newStatus,
$id
);

mysqli_stmt_execute($update);

header("Location: clubs.php");
exit();

?>