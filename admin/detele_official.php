<?php

require_once '../includes/session.php';
require_once '../config/db.php';

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: officials.php');
    exit();
}

$admNo = trim($_POST['admNo'] ?? '');

if ($admNo === '') {
    $_SESSION['error'] = 'Invalid club official selected.';
    header('Location: officials.php');
    exit();
}

/*
 * An official who has already submitted requisitions
 * should not be deleted because their records form part
 * of the system audit trail.
 */
$countSql = "
    SELECT COUNT(*) AS total
    FROM requisitions
    WHERE submittedByAdmNo = ?
";

$countStmt = mysqli_prepare($conn, $countSql);

if (!$countStmt) {
    $_SESSION['error'] =
        'Unable to check the official requisition records.';

    header('Location: officials.php');
    exit();
}

mysqli_stmt_bind_param(
    $countStmt,
    's',
    $admNo
);

mysqli_stmt_execute($countStmt);

$countResult = mysqli_stmt_get_result($countStmt);
$countRow = mysqli_fetch_assoc($countResult);

mysqli_stmt_close($countStmt);

if ((int) $countRow['total'] > 0) {

    $_SESSION['error'] =
        'This official cannot be deleted because they have submitted requisitions. Set their account to Inactive instead.';

    header('Location: officials.php');
    exit();
}

$userSql = "
    SELECT userID
    FROM club_officials
    WHERE admNo = ?
    LIMIT 1
";

$userStmt = mysqli_prepare($conn, $userSql);

if (!$userStmt) {
    $_SESSION['error'] = 'Unable to retrieve the official account.';
    header('Location: officials.php');
    exit();
}

mysqli_stmt_bind_param(
    $userStmt,
    's',
    $admNo
);

mysqli_stmt_execute($userStmt);

$userResult = mysqli_stmt_get_result($userStmt);
$official = mysqli_fetch_assoc($userResult);

mysqli_stmt_close($userStmt);

if (!$official) {
    $_SESSION['error'] = 'Club official not found.';
    header('Location: officials.php');
    exit();
}

/*
 * Deleting the user automatically deletes the related
 * club_officials row because of ON DELETE CASCADE.
 */
$deleteSql = "
    DELETE FROM users
    WHERE userID = ?
";

$deleteStmt = mysqli_prepare($conn, $deleteSql);

if (!$deleteStmt) {
    $_SESSION['error'] = 'Unable to prepare the deletion.';
    header('Location: officials.php');
    exit();
}

$userID = (int) $official['userID'];

mysqli_stmt_bind_param(
    $deleteStmt,
    'i',
    $userID
);

if (mysqli_stmt_execute($deleteStmt)) {

    if (mysqli_stmt_affected_rows($deleteStmt) > 0) {
        $_SESSION['success'] =
            'Club official and login account deleted successfully.';
    } else {
        $_SESSION['error'] = 'Club official account was not found.';
    }

} else {
    $_SESSION['error'] = 'Unable to delete the club official.';
}

mysqli_stmt_close($deleteStmt);

header('Location: officials.php');
exit();