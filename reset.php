<?php

require_once 'config/db.php';

$username = 'admin';
$newPassword = 'admin123';
$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

$sql = "
    UPDATE users
    SET
        password = ?,
        role = 'admin',
        status = 'Active'
    WHERE username = ?
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    'ss',
    $passwordHash,
    $username
);

if (mysqli_stmt_execute($stmt)) {
    echo 'Admin password reset successfully.';
} else {
    echo 'Password reset failed: ' . mysqli_stmt_error($stmt);
}