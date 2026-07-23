<?php

require_once __DIR__ . '/../includes/session.php';

if($_SESSION['role']!="admin"){
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    header("Location: officers.php");
    exit();
}

$id=intval($_GET['id']);

$stmt=mysqli_prepare($conn,"
DELETE FROM approving_officers
WHERE officer_id=?");

mysqli_stmt_bind_param($stmt,"i",$id);

mysqli_stmt_execute($stmt);

$_SESSION['success']="Officer assignment removed.";

header("Location: officers.php");
exit();