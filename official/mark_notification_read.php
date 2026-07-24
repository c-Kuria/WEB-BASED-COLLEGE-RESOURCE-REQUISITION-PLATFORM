<?php

require_once '../includes/session.php';
require_once '../config/db.php';

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'official'
) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: notifications.php');
    exit();
}

$userID = (int) ($_SESSION['userID'] ?? 0);

$notifID = filter_input(
    INPUT_POST,
    'notifID',
    FILTER_VALIDATE_INT
);

if ($userID <= 0 || !$notifID) {
    $_SESSION['error'] =
        'Invalid notification request.';

    header('Location: notifications.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Retrieve official admission number
|--------------------------------------------------------------------------
*/

$officialSql = "
    SELECT admNo
    FROM club_officials
    WHERE userID = ?
    LIMIT 1
";

$officialStmt = mysqli_prepare(
    $conn,
    $officialSql
);

if (!$officialStmt) {
    $_SESSION['error'] =
        'Unable to verify your account.';

    header('Location: notifications.php');
    exit();
}

mysqli_stmt_bind_param(
    $officialStmt,
    'i',
    $userID
);

mysqli_stmt_execute($officialStmt);

$officialResult =
    mysqli_stmt_get_result($officialStmt);

$official =
    mysqli_fetch_assoc($officialResult);

mysqli_stmt_close($officialStmt);

if (!$official) {
    $_SESSION['error'] =
        'Club official profile not found.';

    header('Location: notifications.php');
    exit();
}

$admNo = $official['admNo'];

/*
|--------------------------------------------------------------------------
| Mark the user's notification as read
|--------------------------------------------------------------------------
*/

$updateSql = "
    UPDATE notifications
    SET isRead = 'Yes'
    WHERE notifID = ?
      AND recipientAdmNo = ?
      AND isRead = 'No'
";

$updateStmt = mysqli_prepare(
    $conn,
    $updateSql
);

if (!$updateStmt) {
    $_SESSION['error'] =
        'Unable to update the notification.';

    header('Location: notifications.php');
    exit();
}

mysqli_stmt_bind_param(
    $updateStmt,
    'is',
    $notifID,
    $admNo
);

if (!mysqli_stmt_execute($updateStmt)) {

    $_SESSION['error'] =
        'The notification could not be marked as read.';

} elseif (
    mysqli_stmt_affected_rows(
        $updateStmt
    ) > 0
) {

    $_SESSION['success'] =
        'Notification marked as read.';

} else {

    $_SESSION['error'] =
        'Notification not found or already marked as read.';
}

mysqli_stmt_close($updateStmt);

header('Location: notifications.php');
exit();