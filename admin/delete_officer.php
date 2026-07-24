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
    header('Location: officers.php');
    exit();
}

$staffNo = trim($_POST['staffNo'] ?? '');

if ($staffNo === '') {
    $_SESSION['error'] = 'Invalid officer selected.';
    header('Location: officers.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Find the officer and related user account
|--------------------------------------------------------------------------
*/

$officerSql = "
    SELECT
        officerStaffNo,
        userID,
        officerName
    FROM officers
    WHERE officerStaffNo = ?
    LIMIT 1
";

$officerStmt = mysqli_prepare($conn, $officerSql);

if (!$officerStmt) {
    $_SESSION['error'] = 'Unable to prepare the officer query.';
    header('Location: officers.php');
    exit();
}

mysqli_stmt_bind_param(
    $officerStmt,
    's',
    $staffNo
);

mysqli_stmt_execute($officerStmt);

$officerResult = mysqli_stmt_get_result($officerStmt);
$officer = mysqli_fetch_assoc($officerResult);

mysqli_stmt_close($officerStmt);

if (!$officer) {
    $_SESSION['error'] = 'Officer not found.';
    header('Location: officers.php');
    exit();
}

$userID = (int) $officer['userID'];
$officerName = $officer['officerName'];

/*
|--------------------------------------------------------------------------
| Prevent deletion when approval records exist
|--------------------------------------------------------------------------
*/

$approvalSql = "
    SELECT COUNT(*) AS total
    FROM approvals
    WHERE officerStaffNo = ?
       OR actedBy = ?
";

$approvalStmt = mysqli_prepare($conn, $approvalSql);

if (!$approvalStmt) {
    $_SESSION['error'] = 'Unable to check the officer approval history.';
    header('Location: officers.php');
    exit();
}

mysqli_stmt_bind_param(
    $approvalStmt,
    'ss',
    $staffNo,
    $staffNo
);

mysqli_stmt_execute($approvalStmt);

$approvalResult = mysqli_stmt_get_result($approvalStmt);
$approvalCount = mysqli_fetch_assoc($approvalResult);

mysqli_stmt_close($approvalStmt);

if ((int) $approvalCount['total'] > 0) {
    $_SESSION['error'] =
        'This officer cannot be deleted because they are linked to approval records. Set the account to Inactive instead.';

    header('Location: officers.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Delete inside a transaction
|--------------------------------------------------------------------------
*/

mysqli_begin_transaction($conn);

try {

    /*
     * Remove this officer as a proxy from other officers.
     */
    $proxySql = "
        UPDATE officers
        SET proxyOfficerStaffNo = NULL
        WHERE proxyOfficerStaffNo = ?
    ";

    $proxyStmt = mysqli_prepare($conn, $proxySql);

    if (!$proxyStmt) {
        throw new Exception(
            'Unable to prepare proxy officer cleanup.'
        );
    }

    mysqli_stmt_bind_param(
        $proxyStmt,
        's',
        $staffNo
    );

    if (!mysqli_stmt_execute($proxyStmt)) {
        throw new Exception(
            'Unable to remove the officer from proxy assignments.'
        );
    }

    mysqli_stmt_close($proxyStmt);

    /*
     * Delete the officer profile.
     */
    $deleteOfficerSql = "
        DELETE FROM officers
        WHERE officerStaffNo = ?
    ";

    $deleteOfficerStmt = mysqli_prepare(
        $conn,
        $deleteOfficerSql
    );

    if (!$deleteOfficerStmt) {
        throw new Exception(
            'Unable to prepare officer deletion.'
        );
    }

    mysqli_stmt_bind_param(
        $deleteOfficerStmt,
        's',
        $staffNo
    );

    if (!mysqli_stmt_execute($deleteOfficerStmt)) {
        throw new Exception(
            'Unable to delete the officer profile: ' .
            mysqli_stmt_error($deleteOfficerStmt)
        );
    }

    if (mysqli_stmt_affected_rows($deleteOfficerStmt) === 0) {
        throw new Exception('Officer profile was not found.');
    }

    mysqli_stmt_close($deleteOfficerStmt);

    /*
     * Delete the related login account.
     */
    $deleteUserSql = "
        DELETE FROM users
        WHERE userID = ?
          AND role = 'officer'
    ";

    $deleteUserStmt = mysqli_prepare(
        $conn,
        $deleteUserSql
    );

    if (!$deleteUserStmt) {
        throw new Exception(
            'Unable to prepare login account deletion.'
        );
    }

    mysqli_stmt_bind_param(
        $deleteUserStmt,
        'i',
        $userID
    );

    if (!mysqli_stmt_execute($deleteUserStmt)) {
        throw new Exception(
            'Unable to delete the officer login account.'
        );
    }

    mysqli_stmt_close($deleteUserStmt);

    mysqli_commit($conn);

    $_SESSION['success'] =
        $officerName . ' was deleted successfully.';

} catch (Throwable $exception) {

    mysqli_rollback($conn);

    $_SESSION['error'] = $exception->getMessage();
}

header('Location: officers.php');
exit();