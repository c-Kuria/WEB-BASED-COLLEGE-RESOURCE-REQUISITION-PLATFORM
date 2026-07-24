<?php

require_once '../includes/session.php';
require_once '../config/db.php';

/*
|--------------------------------------------------------------------------
| Protect the page
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'official'
) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_requisitions.php');
    exit();
}

$userID = (int) ($_SESSION['userID'] ?? 0);

$requisitionID = filter_input(
    INPUT_POST,
    'requisitionID',
    FILTER_VALIDATE_INT
);

if ($userID <= 0 || !$requisitionID) {
    $_SESSION['error'] =
        'Invalid cancellation request.';

    header('Location: my_requisitions.php');
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
        'Unable to verify your club official account.';

    header('Location: my_requisitions.php');
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

    header('Location: my_requisitions.php');
    exit();
}

$admNo = $official['admNo'];

/*
|--------------------------------------------------------------------------
| Retrieve and verify requisition ownership
|--------------------------------------------------------------------------
*/

$requisitionSql = "
    SELECT
        r.requisitionID,
        r.requisitionNumber,
        r.status,
        res.resourceName
    FROM requisitions r

    INNER JOIN resources res
        ON r.resourceID = res.resourceID

    WHERE r.requisitionID = ?
      AND r.submittedByAdmNo = ?

    LIMIT 1
";

$requisitionStmt = mysqli_prepare(
    $conn,
    $requisitionSql
);

if (!$requisitionStmt) {
    $_SESSION['error'] =
        'Unable to verify the requisition.';

    header('Location: my_requisitions.php');
    exit();
}

mysqli_stmt_bind_param(
    $requisitionStmt,
    'is',
    $requisitionID,
    $admNo
);

mysqli_stmt_execute($requisitionStmt);

$requisitionResult =
    mysqli_stmt_get_result($requisitionStmt);

$requisition =
    mysqli_fetch_assoc($requisitionResult);

mysqli_stmt_close($requisitionStmt);

if (!$requisition) {
    $_SESSION['error'] =
        'The requisition was not found or does not belong to you.';

    header('Location: my_requisitions.php');
    exit();
}

if ($requisition['status'] !== 'Pending') {
    $_SESSION['error'] =
        'Only pending requisitions can be cancelled.';

    header('Location: my_requisitions.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Check whether approval has already begun
|--------------------------------------------------------------------------
*/

$approvalCountSql = "
    SELECT COUNT(*) AS total
    FROM approvals
    WHERE requisitionID = ?
      AND status IN (
          'Approved',
          'Rejected'
      )
";

$approvalCountStmt = mysqli_prepare(
    $conn,
    $approvalCountSql
);

if (!$approvalCountStmt) {
    $_SESSION['error'] =
        'Unable to check the approval progress.';

    header('Location: my_requisitions.php');
    exit();
}

mysqli_stmt_bind_param(
    $approvalCountStmt,
    'i',
    $requisitionID
);

mysqli_stmt_execute($approvalCountStmt);

$approvalCountResult =
    mysqli_stmt_get_result($approvalCountStmt);

$approvalCountRow =
    mysqli_fetch_assoc($approvalCountResult);

mysqli_stmt_close($approvalCountStmt);

if ((int) $approvalCountRow['total'] > 0) {
    $_SESSION['error'] =
        'This requisition cannot be cancelled because an officer has already acted on it.';

    header('Location: my_requisitions.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Cancel the requisition
|--------------------------------------------------------------------------
*/

mysqli_begin_transaction($conn);

try {

    /*
     * Change requisition status.
     */
    $updateRequisitionSql = "
        UPDATE requisitions
        SET status = 'Cancelled'
        WHERE requisitionID = ?
          AND submittedByAdmNo = ?
          AND status = 'Pending'
    ";

    $updateRequisitionStmt = mysqli_prepare(
        $conn,
        $updateRequisitionSql
    );

    if (!$updateRequisitionStmt) {
        throw new Exception(
            'Unable to prepare requisition cancellation.'
        );
    }

    mysqli_stmt_bind_param(
        $updateRequisitionStmt,
        'is',
        $requisitionID,
        $admNo
    );

    if (
        !mysqli_stmt_execute(
            $updateRequisitionStmt
        )
    ) {
        throw new Exception(
            'Unable to cancel the requisition.'
        );
    }

    if (
        mysqli_stmt_affected_rows(
            $updateRequisitionStmt
        ) === 0
    ) {
        throw new Exception(
            'The requisition could not be cancelled.'
        );
    }

    mysqli_stmt_close(
        $updateRequisitionStmt
    );

    /*
     * Approval stages are no longer actionable.
     *
     * They remain in the database for history.
     * Waiting and Pending are changed to Rejected,
     * with a system cancellation comment.
     */
    $approvalSql = "
        UPDATE approvals
        SET
            status = 'Rejected',
            comments = 'Requisition cancelled by the club official.',
            approvalTime = NOW()
        WHERE requisitionID = ?
          AND status IN (
              'Waiting',
              'Pending',
              'Delegated'
          )
    ";

    $approvalStmt = mysqli_prepare(
        $conn,
        $approvalSql
    );

    if (!$approvalStmt) {
        throw new Exception(
            'Unable to prepare approval cancellation.'
        );
    }

    mysqli_stmt_bind_param(
        $approvalStmt,
        'i',
        $requisitionID
    );

    if (!mysqli_stmt_execute($approvalStmt)) {
        throw new Exception(
            'Unable to close the approval stages.'
        );
    }

    mysqli_stmt_close($approvalStmt);

    /*
     * Create cancellation notification.
     */
    $message =
        'Your requisition ' .
        $requisition['requisitionNumber'] .
        ' for ' .
        $requisition['resourceName'] .
        ' was cancelled successfully.';

    $notificationSql = "
        INSERT INTO notifications (
            approvalNumber,
            requisitionID,
            recipientAdmNo,
            notifDescription,
            isRead
        )
        VALUES (
            NULL,
            ?,
            ?,
            ?,
            'No'
        )
    ";

    $notificationStmt = mysqli_prepare(
        $conn,
        $notificationSql
    );

    if (!$notificationStmt) {
        throw new Exception(
            'Unable to prepare the cancellation notification.'
        );
    }

    mysqli_stmt_bind_param(
        $notificationStmt,
        'iss',
        $requisitionID,
        $admNo,
        $message
    );

    if (
        !mysqli_stmt_execute(
            $notificationStmt
        )
    ) {
        throw new Exception(
            'Unable to create the cancellation notification.'
        );
    }

    mysqli_stmt_close($notificationStmt);

    mysqli_commit($conn);

    $_SESSION['success'] =
        'Requisition ' .
        $requisition['requisitionNumber'] .
        ' was cancelled successfully.';

} catch (Throwable $exception) {

    mysqli_rollback($conn);

    $_SESSION['error'] =
        $exception->getMessage();
}

header('Location: my_requisitions.php');
exit();