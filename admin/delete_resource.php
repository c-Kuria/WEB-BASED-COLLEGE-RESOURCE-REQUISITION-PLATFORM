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
    header('Location: resources.php');
    exit();
}

$resourceID = filter_input(
    INPUT_POST,
    'resourceID',
    FILTER_VALIDATE_INT
);

if (!$resourceID) {
    $_SESSION['error'] = 'Invalid resource selected.';
    header('Location: resources.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Confirm resource exists
|--------------------------------------------------------------------------
*/

$resourceSql = "
    SELECT
        resourceID,
        resourceName
    FROM resources
    WHERE resourceID = ?
    LIMIT 1
";

$resourceStmt = mysqli_prepare(
    $conn,
    $resourceSql
);

if (!$resourceStmt) {
    $_SESSION['error'] =
        'Unable to prepare the resource query.';

    header('Location: resources.php');
    exit();
}

mysqli_stmt_bind_param(
    $resourceStmt,
    'i',
    $resourceID
);

mysqli_stmt_execute($resourceStmt);

$resourceResult =
    mysqli_stmt_get_result($resourceStmt);

$resource =
    mysqli_fetch_assoc($resourceResult);

mysqli_stmt_close($resourceStmt);

if (!$resource) {
    $_SESSION['error'] = 'Resource not found.';
    header('Location: resources.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Check requisition history
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*) AS total
    FROM requisitions
    WHERE resourceID = ?
";

$countStmt = mysqli_prepare(
    $conn,
    $countSql
);

if (!$countStmt) {
    $_SESSION['error'] =
        'Unable to check resource requisitions.';

    header('Location: resources.php');
    exit();
}

mysqli_stmt_bind_param(
    $countStmt,
    'i',
    $resourceID
);

mysqli_stmt_execute($countStmt);

$countResult =
    mysqli_stmt_get_result($countStmt);

$countRow =
    mysqli_fetch_assoc($countResult);

mysqli_stmt_close($countStmt);

if ((int) $countRow['total'] > 0) {

    $_SESSION['error'] =
        'This resource cannot be deleted because it is linked to requisitions. Set it to Inactive instead.';

    header('Location: resources.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Delete resource
|--------------------------------------------------------------------------
*/

$deleteSql = "
    DELETE FROM resources
    WHERE resourceID = ?
";

$deleteStmt = mysqli_prepare(
    $conn,
    $deleteSql
);

if (!$deleteStmt) {
    $_SESSION['error'] =
        'Unable to prepare resource deletion.';

    header('Location: resources.php');
    exit();
}

mysqli_stmt_bind_param(
    $deleteStmt,
    'i',
    $resourceID
);

if (mysqli_stmt_execute($deleteStmt)) {

    if (mysqli_stmt_affected_rows($deleteStmt) > 0) {

        $_SESSION['success'] =
            $resource['resourceName'] .
            ' was deleted successfully.';

    } else {

        $_SESSION['error'] =
            'Resource was not found.';
    }

} else {

    $_SESSION['error'] =
        'Unable to delete the resource: ' .
        mysqli_stmt_error($deleteStmt);
}

mysqli_stmt_close($deleteStmt);

header('Location: resources.php');
exit();