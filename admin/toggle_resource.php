<?php

include '../includes/session.php';

if($_SESSION['role']!="admin"){
    header("Location: ../login.php");
    exit();
}

include '../config/db.php';

if(!isset($_GET['id'])){
    header("Location: resources.php");
    exit();
}

$id=intval($_GET['id']);

$get=mysqli_prepare($conn,"
SELECT status
FROM resources
WHERE resource_id=?");

mysqli_stmt_bind_param($get,"i",$id);

mysqli_stmt_execute($get);

$result=mysqli_stmt_get_result($get);

$row=mysqli_fetch_assoc($result);

$newStatus=($row['status']=="Active")
            ? "Inactive"
            : "Active";

$update=mysqli_prepare($conn,"
UPDATE resources
SET status=?
WHERE resource_id=?");

mysqli_stmt_bind_param(
    $update,
    "si",
    $newStatus,
    $id
);

mysqli_stmt_execute($update);

$_SESSION['success']="Resource status updated.";

header("Location: resources.php");
exit();