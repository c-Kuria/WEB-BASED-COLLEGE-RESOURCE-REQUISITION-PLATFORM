<?php
require_once '../includes/session.php';
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: clubs.php');
    exit();
}

$clubNumber = filter_input(
    INPUT_POST,
    'clubNumber',
    FILTER_VALIDATE_INT
);

if (!$clubNumber) {
    $_SESSION['error'] = 'Invalid club selected.';
    header('Location: clubs.php');
    exit();
}

/*
 * Prevent deleting a club that still has officials.
 * This also avoids foreign-key constraint errors.
 */
$countSql = "
    SELECT COUNT(*) AS total
    FROM club_officials
    WHERE clubNumber = ?
";

$countStmt = mysqli_prepare($conn, $countSql);

if (!$countStmt) {
    $_SESSION['error'] = 'Unable to check the club records.';
    header('Location: clubs.php');
    exit();
}

mysqli_stmt_bind_param(
    $countStmt,
    'i',
    $clubNumber
);

mysqli_stmt_execute($countStmt);

$countResult = mysqli_stmt_get_result($countStmt);
$countRow = mysqli_fetch_assoc($countResult);

mysqli_stmt_close($countStmt);

if ((int) $countRow['total'] > 0) {
    $_SESSION['error'] =
        'This club cannot be deleted because it has registered officials.';

    header('Location: clubs.php');
    exit();
}

$deleteSql = "
    DELETE FROM clubs
    WHERE clubNumber = ?
";

$deleteStmt = mysqli_prepare($conn, $deleteSql);

if (!$deleteStmt) {
    $_SESSION['error'] = 'Unable to prepare the deletion.';
    header('Location: clubs.php');
    exit();
}

mysqli_stmt_bind_param(
    $deleteStmt,
    'i',
    $clubNumber
);

if (mysqli_stmt_execute($deleteStmt)) {

    if (mysqli_stmt_affected_rows($deleteStmt) > 0) {
        $_SESSION['success'] = 'Club deleted successfully.';
    } else {
        $_SESSION['error'] = 'Club not found.';
    }

} else {
    $_SESSION['error'] = 'Unable to delete the club.';
}

mysqli_stmt_close($deleteStmt);

header('Location: clubs.php');
exit();