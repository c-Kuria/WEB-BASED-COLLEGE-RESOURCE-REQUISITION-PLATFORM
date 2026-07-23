<?php

require_once __DIR__ . '/../includes/session.php';

if ($_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../config/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: users.php');
    exit();
}

$user_id = (int) $_GET['id'];

$stmt = mysqli_prepare($conn, "SELECT status FROM users WHERE user_id=?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($user) {
    $new_status = $user['status'] == 'Active' ? 'Inactive' : 'Active';
    $update = mysqli_prepare($conn, "UPDATE users SET status=? WHERE user_id=?");
    mysqli_stmt_bind_param($update, 'si', $new_status, $user_id);
    mysqli_stmt_execute($update);
}

$_SESSION['success'] = 'User status updated.';
header('Location: users.php');
exit();
