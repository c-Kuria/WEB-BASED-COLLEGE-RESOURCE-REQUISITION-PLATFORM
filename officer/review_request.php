<?php

require_once '../includes/session.php';
require_once '../config/db.php';

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'officer'
) {
    header('Location: ../login.php');
    exit();
}

$userID =
    (int) ($_SESSION['userID'] ?? 0);

if ($userID <= 0) {
    session_destroy();

    header('Location: ../login.php');
    exit();
}

$pageTitle = 'Review Request';

$errors = [];

/*
|--------------------------------------------------------------------------
| Get logged-in officer
|--------------------------------------------------------------------------
*/

$officerSql = "
    SELECT
        o.officerStaffNo,
        o.officerName,
        o.officerRole,
        o.availability,
        o.proxyOfficerStaffNo,

        u.status AS accountStatus

    FROM officers o

    INNER JOIN users u
        ON o.userID = u.userID

    WHERE o.userID = ?

    LIMIT 1
";

$officerStmt =
    mysqli_prepare(
        $conn,
        $officerSql
    );

if (!$officerStmt) {
    die('Unable to prepare officer query.');
}

mysqli_stmt_bind_param(
    $officerStmt,
    'i',
    $userID
);

mysqli_stmt_execute(
    $officerStmt
);

$officerResult =
    mysqli_stmt_get_result(
        $officerStmt
    );

$loggedOfficer =
    mysqli_fetch_assoc(
        $officerResult
    );

mysqli_stmt_close(
    $officerStmt
);

if (!$loggedOfficer) {
    die('Officer profile not found.');
}

$loggedOfficerStaffNo =
    $loggedOfficer['officerStaffNo'];

/*
|--------------------------------------------------------------------------
| Validate approval ID
|--------------------------------------------------------------------------
*/

$approvalNumber =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    $approvalNumber =
        filter_input(
            INPUT_POST,
            'approvalNumber',
            FILTER_VALIDATE_INT
        );
}

if (
    !$approvalNumber ||
    $approvalNumber <= 0
) {
    $_SESSION['error'] =
        'Invalid approval request selected.';

    header('Location: pending_requests.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Helper: fetch approval and requisition details
|--------------------------------------------------------------------------
*/

function getReviewRequest(
    mysqli $conn,
    int $approvalNumber,
    string $loggedOfficerStaffNo
): ?array {

    $sql = "
        SELECT
            a.approvalNumber,
            a.requisitionID,
            a.officerStaffNo,
            a.approvalOrder,
            a.status AS approvalStatus,
            a.assignedAs,
            a.actedBy,
            a.comments,
            a.approvalTime,

            r.requisitionNumber,
            r.submittedByAdmNo,
            r.resourceID,
            r.purpose,
            r.quantityRequested,
            r.startDate,
            r.endDate,
            r.requestTime,
            r.status AS requisitionStatus,

            rs.resourceName,
            rs.resourceCategory,
            rs.resourceDescription,
            rs.resourceQuantityTotal,
            rs.resourceQuantityRemaining,
            rs.status AS resourceStatus,

            co.officialName,
            co.position AS officialPosition,
            co.clubNumber,

            c.clubName,

            assignedOfficer.officerName
                AS assignedOfficerName,

            assignedOfficer.officerRole
                AS assignedOfficerRole,

            assignedOfficer.availability
                AS assignedOfficerAvailability,

            actingOfficer.officerName
                AS actingOfficerName,

            actingOfficer.officerRole
                AS actingOfficerRole

        FROM approvals a

        INNER JOIN requisitions r
            ON a.requisitionID =
               r.requisitionID

        INNER JOIN resources rs
            ON r.resourceID =
               rs.resourceID

        INNER JOIN club_officials co
            ON r.submittedByAdmNo =
               co.admNo

        INNER JOIN clubs c
            ON co.clubNumber =
               c.clubNumber

        INNER JOIN officers assignedOfficer
            ON a.officerStaffNo =
               assignedOfficer.officerStaffNo

        LEFT JOIN officers actingOfficer
            ON a.actedBy =
               actingOfficer.officerStaffNo

        WHERE a.approvalNumber = ?
          AND a.officerStaffNo = ?

        LIMIT 1
    ";

    $stmt =
        mysqli_prepare(
            $conn,
            $sql
        );

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'is',
        $approvalNumber,
        $loggedOfficerStaffNo
    );

    mysqli_stmt_execute($stmt);

    $result =
        mysqli_stmt_get_result($stmt);

    $request =
        mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return $request ?: null;
}

$request =
    getReviewRequest(
        $conn,
        $approvalNumber,
        $loggedOfficerStaffNo
    );

if (!$request) {
    $_SESSION['error'] =
        'The approval request was not found or is not assigned to you.';

    header('Location: pending_requests.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Process officer action
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    $decision =
        trim($_POST['decision'] ?? '');

    $comments =
        trim($_POST['comments'] ?? '');

    if (
        !in_array(
            $decision,
            ['approve', 'reject'],
            true
        )
    ) {
        $errors[] =
            'Select a valid approval decision.';
    }

    if (
        $decision === 'reject' &&
        $comments === ''
    ) {
        $errors[] =
            'Comments are required when rejecting a requisition.';
    }

    if (
        mb_strlen($comments) > 1000
    ) {
        $errors[] =
            'Comments must not exceed 1000 characters.';
    }

    if (
        $request['approvalStatus'] !== 'Pending'
    ) {
        $errors[] =
            'This approval stage is no longer awaiting action.';
    }

    if (
        $request['requisitionStatus'] !== 'Pending'
    ) {
        $errors[] =
            'This requisition is no longer pending.';
    }

    if (
        $loggedOfficer['accountStatus'] !== 'Active'
    ) {
        $errors[] =
            'Your account is not active.';
    }

    if (
        $loggedOfficer['availability'] !== 'Available'
    ) {
        $errors[] =
            'You must be available before acting on this request.';
    }

    if (empty($errors)) {

        mysqli_begin_transaction($conn);

        try {

            /*
            |--------------------------------------------------------------------------
            | Lock the approval row
            |--------------------------------------------------------------------------
            */

            $lockSql = "
                SELECT
                    approvalNumber,
                    requisitionID,
                    officerStaffNo,
                    approvalOrder,
                    status,
                    assignedAs

                FROM approvals

                WHERE approvalNumber = ?
                  AND officerStaffNo = ?

                FOR UPDATE
            ";

            $lockStmt =
                mysqli_prepare(
                    $conn,
                    $lockSql
                );

            if (!$lockStmt) {
                throw new RuntimeException(
                    'Unable to prepare approval lock.'
                );
            }

            mysqli_stmt_bind_param(
                $lockStmt,
                'is',
                $approvalNumber,
                $loggedOfficerStaffNo
            );

            mysqli_stmt_execute($lockStmt);

            $lockResult =
                mysqli_stmt_get_result($lockStmt);

            $lockedApproval =
                mysqli_fetch_assoc($lockResult);

            mysqli_stmt_close($lockStmt);

            if (!$lockedApproval) {
                throw new RuntimeException(
                    'The approval stage is not assigned to you.'
                );
            }

            if (
                $lockedApproval['status'] !== 'Pending'
            ) {
                throw new RuntimeException(
                    'This approval stage has already been processed.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Lock the requisition row
            |--------------------------------------------------------------------------
            */

            $requisitionLockSql = "
                SELECT
                    requisitionID,
                    requisitionNumber,
                    submittedByAdmNo,
                    resourceID,
                    quantityRequested,
                    status

                FROM requisitions

                WHERE requisitionID = ?

                FOR UPDATE
            ";

            $requisitionLockStmt =
                mysqli_prepare(
                    $conn,
                    $requisitionLockSql
                );

            if (!$requisitionLockStmt) {
                throw new RuntimeException(
                    'Unable to prepare requisition lock.'
                );
            }

            mysqli_stmt_bind_param(
                $requisitionLockStmt,
                'i',
                $lockedApproval['requisitionID']
            );

            mysqli_stmt_execute(
                $requisitionLockStmt
            );

            $requisitionLockResult =
                mysqli_stmt_get_result(
                    $requisitionLockStmt
                );

            $lockedRequisition =
                mysqli_fetch_assoc(
                    $requisitionLockResult
                );

            mysqli_stmt_close(
                $requisitionLockStmt
            );

            if (!$lockedRequisition) {
                throw new RuntimeException(
                    'The requisition could not be found.'
                );
            }

            if (
                $lockedRequisition['status'] !==
                'Pending'
            ) {
                throw new RuntimeException(
                    'The requisition is no longer pending.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Approve
            |--------------------------------------------------------------------------
            */

            if ($decision === 'approve') {

                $approvalUpdateSql = "
                    UPDATE approvals

                    SET
                        status = 'Approved',
                        actedBy = ?,
                        comments = NULLIF(?, ''),
                        approvalTime = NOW()

                    WHERE approvalNumber = ?
                      AND status = 'Pending'
                ";

                $approvalUpdateStmt =
                    mysqli_prepare(
                        $conn,
                        $approvalUpdateSql
                    );

                if (!$approvalUpdateStmt) {
                    throw new RuntimeException(
                        'Unable to prepare approval update.'
                    );
                }

                mysqli_stmt_bind_param(
                    $approvalUpdateStmt,
                    'ssi',
                    $loggedOfficerStaffNo,
                    $comments,
                    $approvalNumber
                );

                if (
                    !mysqli_stmt_execute(
                        $approvalUpdateStmt
                    )
                ) {
                    throw new RuntimeException(
                        'Unable to approve the requisition.'
                    );
                }

                if (
                    mysqli_stmt_affected_rows(
                        $approvalUpdateStmt
                    ) !== 1
                ) {
                    throw new RuntimeException(
                        'The approval stage was not updated.'
                    );
                }

                mysqli_stmt_close(
                    $approvalUpdateStmt
                );

                /*
                |--------------------------------------------------------------------------
                | Find next waiting approval
                |--------------------------------------------------------------------------
                */

                $nextApprovalSql = "
                    SELECT
                        approvalNumber,
                        approvalOrder

                    FROM approvals

                    WHERE requisitionID = ?
                      AND approvalOrder > ?
                      AND status = 'Waiting'

                    ORDER BY approvalOrder ASC

                    LIMIT 1

                    FOR UPDATE
                ";

                $nextApprovalStmt =
                    mysqli_prepare(
                        $conn,
                        $nextApprovalSql
                    );

                if (!$nextApprovalStmt) {
                    throw new RuntimeException(
                        'Unable to prepare the next approval query.'
                    );
                }

                mysqli_stmt_bind_param(
                    $nextApprovalStmt,
                    'ii',
                    $lockedApproval['requisitionID'],
                    $lockedApproval['approvalOrder']
                );

                mysqli_stmt_execute(
                    $nextApprovalStmt
                );

                $nextApprovalResult =
                    mysqli_stmt_get_result(
                        $nextApprovalStmt
                    );

                $nextApproval =
                    mysqli_fetch_assoc(
                        $nextApprovalResult
                    );

                mysqli_stmt_close(
                    $nextApprovalStmt
                );

                if ($nextApproval) {

                    $activateNextSql = "
                        UPDATE approvals

                        SET status = 'Pending'

                        WHERE approvalNumber = ?
                          AND status = 'Waiting'
                    ";

                    $activateNextStmt =
                        mysqli_prepare(
                            $conn,
                            $activateNextSql
                        );

                    if (!$activateNextStmt) {
                        throw new RuntimeException(
                            'Unable to prepare the next stage update.'
                        );
                    }

                    mysqli_stmt_bind_param(
                        $activateNextStmt,
                        'i',
                        $nextApproval['approvalNumber']
                    );

                    if (
                        !mysqli_stmt_execute(
                            $activateNextStmt
                        )
                    ) {
                        throw new RuntimeException(
                            'Unable to activate the next approval stage.'
                        );
                    }

                    mysqli_stmt_close(
                        $activateNextStmt
                    );

                    $notificationMessage =
                        'Your requisition ' .
                        $lockedRequisition[
                            'requisitionNumber'
                        ] .
                        ' was approved at stage ' .
                        $lockedApproval[
                            'approvalOrder'
                        ] .
                        ' and has moved to stage ' .
                        $nextApproval[
                            'approvalOrder'
                        ] .
                        '.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Final approval: update requisition
                    |--------------------------------------------------------------------------
                    */

                    $completeRequisitionSql = "
                        UPDATE requisitions

                        SET status = 'Approved'

                        WHERE requisitionID = ?
                          AND status = 'Pending'
                    ";

                    $completeRequisitionStmt =
                        mysqli_prepare(
                            $conn,
                            $completeRequisitionSql
                        );

                    if (!$completeRequisitionStmt) {
                        throw new RuntimeException(
                            'Unable to prepare the final approval update.'
                        );
                    }

                    mysqli_stmt_bind_param(
                        $completeRequisitionStmt,
                        'i',
                        $lockedApproval['requisitionID']
                    );

                    if (
                        !mysqli_stmt_execute(
                            $completeRequisitionStmt
                        )
                    ) {
                        throw new RuntimeException(
                            'Unable to complete the requisition.'
                        );
                    }

                    mysqli_stmt_close(
                        $completeRequisitionStmt
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Deduct approved resource quantity
                    |--------------------------------------------------------------------------
                    */

                    $resourceUpdateSql = "
                        UPDATE resources

                        SET resourceQuantityRemaining =
                            resourceQuantityRemaining - ?

                        WHERE resourceID = ?
                          AND resourceQuantityRemaining >= ?
                    ";

                    $resourceUpdateStmt =
                        mysqli_prepare(
                            $conn,
                            $resourceUpdateSql
                        );

                    if (!$resourceUpdateStmt) {
                        throw new RuntimeException(
                            'Unable to prepare the resource quantity update.'
                        );
                    }

                    mysqli_stmt_bind_param(
                        $resourceUpdateStmt,
                        'iii',
                        $lockedRequisition[
                            'quantityRequested'
                        ],
                        $lockedRequisition[
                            'resourceID'
                        ],
                        $lockedRequisition[
                            'quantityRequested'
                        ]
                    );

                    if (
                        !mysqli_stmt_execute(
                            $resourceUpdateStmt
                        )
                    ) {
                        throw new RuntimeException(
                            'Unable to update the remaining resource quantity.'
                        );
                    }

                    if (
                        mysqli_stmt_affected_rows(
                            $resourceUpdateStmt
                        ) !== 1
                    ) {
                        throw new RuntimeException(
                            'The resource no longer has enough quantity available.'
                        );
                    }

                    mysqli_stmt_close(
                        $resourceUpdateStmt
                    );

                    $notificationMessage =
                        'Your requisition ' .
                        $lockedRequisition[
                            'requisitionNumber'
                        ] .
                        ' has completed all approval stages and is now approved.';
                }

                $successMessage =
                    'Requisition approved successfully.';
            }

            /*
            |--------------------------------------------------------------------------
            | Reject
            |--------------------------------------------------------------------------
            */

            else {

                $rejectApprovalSql = "
                    UPDATE approvals

                    SET
                        status = 'Rejected',
                        actedBy = ?,
                        comments = ?,
                        approvalTime = NOW()

                    WHERE approvalNumber = ?
                      AND status = 'Pending'
                ";

                $rejectApprovalStmt =
                    mysqli_prepare(
                        $conn,
                        $rejectApprovalSql
                    );

                if (!$rejectApprovalStmt) {
                    throw new RuntimeException(
                        'Unable to prepare rejection update.'
                    );
                }

                mysqli_stmt_bind_param(
                    $rejectApprovalStmt,
                    'ssi',
                    $loggedOfficerStaffNo,
                    $comments,
                    $approvalNumber
                );

                if (
                    !mysqli_stmt_execute(
                        $rejectApprovalStmt
                    )
                ) {
                    throw new RuntimeException(
                        'Unable to reject the requisition.'
                    );
                }

                if (
                    mysqli_stmt_affected_rows(
                        $rejectApprovalStmt
                    ) !== 1
                ) {
                    throw new RuntimeException(
                        'The approval stage was not updated.'
                    );
                }

                mysqli_stmt_close(
                    $rejectApprovalStmt
                );

                $rejectRequisitionSql = "
                    UPDATE requisitions

                    SET status = 'Rejected'

                    WHERE requisitionID = ?
                      AND status = 'Pending'
                ";

                $rejectRequisitionStmt =
                    mysqli_prepare(
                        $conn,
                        $rejectRequisitionSql
                    );

                if (!$rejectRequisitionStmt) {
                    throw new RuntimeException(
                        'Unable to prepare requisition rejection.'
                    );
                }

                mysqli_stmt_bind_param(
                    $rejectRequisitionStmt,
                    'i',
                    $lockedApproval['requisitionID']
                );

                if (
                    !mysqli_stmt_execute(
                        $rejectRequisitionStmt
                    )
                ) {
                    throw new RuntimeException(
                        'Unable to reject the requisition.'
                    );
                }

                mysqli_stmt_close(
                    $rejectRequisitionStmt
                );

                /*
                |--------------------------------------------------------------------------
                | Stop future approval stages
                |--------------------------------------------------------------------------
                */

                $stopFutureStagesSql = "
                    UPDATE approvals

                    SET status = 'Rejected'

                    WHERE requisitionID = ?
                      AND approvalOrder > ?
                      AND status = 'Waiting'
                ";

                $stopFutureStagesStmt =
                    mysqli_prepare(
                        $conn,
                        $stopFutureStagesSql
                    );

                if (!$stopFutureStagesStmt) {
                    throw new RuntimeException(
                        'Unable to prepare future stage update.'
                    );
                }

                mysqli_stmt_bind_param(
                    $stopFutureStagesStmt,
                    'ii',
                    $lockedApproval['requisitionID'],
                    $lockedApproval['approvalOrder']
                );

                if (
                    !mysqli_stmt_execute(
                        $stopFutureStagesStmt
                    )
                ) {
                    throw new RuntimeException(
                        'Unable to stop future approval stages.'
                    );
                }

                mysqli_stmt_close(
                    $stopFutureStagesStmt
                );

                $notificationMessage =
                    'Your requisition ' .
                    $lockedRequisition[
                        'requisitionNumber'
                    ] .
                    ' was rejected at approval stage ' .
                    $lockedApproval[
                        'approvalOrder'
                    ] .
                    '. Reason: ' .
                    $comments;

                $successMessage =
                    'Requisition rejected successfully.';
            }

            /*
            |--------------------------------------------------------------------------
            | Notify club official
            |--------------------------------------------------------------------------
            */

            $notificationSql = "
                INSERT INTO notifications (
                    approvalNumber,
                    requisitionID,
                    recipientAdmNo,
                    notifDescription,
                    isRead,
                    createdAt
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    'No',
                    NOW()
                )
            ";

            $notificationStmt =
                mysqli_prepare(
                    $conn,
                    $notificationSql
                );

            if (!$notificationStmt) {
                throw new RuntimeException(
                    'Unable to prepare the notification.'
                );
            }

            mysqli_stmt_bind_param(
                $notificationStmt,
                'iiss',
                $approvalNumber,
                $lockedApproval['requisitionID'],
                $lockedRequisition['submittedByAdmNo'],
                $notificationMessage
            );

            if (
                !mysqli_stmt_execute(
                    $notificationStmt
                )
            ) {
                throw new RuntimeException(
                    'Unable to create the notification.'
                );
            }

            mysqli_stmt_close(
                $notificationStmt
            );

            mysqli_commit($conn);

            $_SESSION['success'] =
                $successMessage;

            if (
                $request['assignedAs'] === 'Proxy'
            ) {
                header(
                    'Location: delegated_requests.php'
                );
            } else {
                header(
                    'Location: pending_requests.php'
                );
            }

            exit();

        } catch (Throwable $exception) {

            mysqli_rollback($conn);

            $errors[] =
                $exception->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Refresh request after unsuccessful POST
|--------------------------------------------------------------------------
*/

$request =
    getReviewRequest(
        $conn,
        $approvalNumber,
        $loggedOfficerStaffNo
    );

if (!$request) {
    $_SESSION['error'] =
        'The approval request could not be loaded.';

    header('Location: pending_requests.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Get all approval stages
|--------------------------------------------------------------------------
*/

$stagesSql = "
    SELECT
        a.approvalNumber,
        a.approvalOrder,
        a.status,
        a.assignedAs,
        a.comments,
        a.approvalTime,

        assignedOfficer.officerName,
        assignedOfficer.officerRole,

        actingOfficer.officerName
            AS actingOfficerName,

        actingOfficer.officerRole
            AS actingOfficerRole

    FROM approvals a

    INNER JOIN officers assignedOfficer
        ON a.officerStaffNo =
           assignedOfficer.officerStaffNo

    LEFT JOIN officers actingOfficer
        ON a.actedBy =
           actingOfficer.officerStaffNo

    WHERE a.requisitionID = ?

    ORDER BY
        a.approvalOrder ASC,
        a.approvalNumber ASC
";

$stagesStmt =
    mysqli_prepare(
        $conn,
        $stagesSql
    );

mysqli_stmt_bind_param(
    $stagesStmt,
    'i',
    $request['requisitionID']
);

mysqli_stmt_execute(
    $stagesStmt
);

$stagesResult =
    mysqli_stmt_get_result(
        $stagesStmt
);

$approvalStages = [];

while (
    $stage =
        mysqli_fetch_assoc(
            $stagesResult
        )
) {
    $approvalStages[] =
        $stage;
}

mysqli_stmt_close(
    $stagesStmt
);

$totalStages =
    count($approvalStages);

$completedStages = 0;

foreach ($approvalStages as $stage) {
    if ($stage['status'] === 'Approved') {
        $completedStages++;
    }
}

$progressPercentage =
    $totalStages > 0
        ? min(
            100,
            ($completedStages / $totalStages) * 100
        )
        : 0;

$requisitionStatusClass =
    match (
        $request['requisitionStatus']
    ) {
        'Approved' =>
            'badge-success',

        'Rejected' =>
            'badge-danger',

        'Cancelled' =>
            'badge-secondary',

        'Pending' =>
            'badge-warning',

        default =>
            'badge-info'
    };

$canAct =
    $request['approvalStatus'] === 'Pending' &&
    $request['requisitionStatus'] === 'Pending' &&
    $loggedOfficer['accountStatus'] === 'Active' &&
    $loggedOfficer['availability'] === 'Available';

$backPage =
    $request['assignedAs'] === 'Proxy'
        ? 'delegated_requests.php'
        : 'pending_requests.php';

require_once '../includes/header.php';
?>

<div class="page-header">

    <div>

        <h1>Review Request</h1>

        <p>
            Review the requisition and record your decision.
        </p>

    </div>

    <a
        href="<?= htmlspecialchars(
            $backPage
        ); ?>"
        class="btn btn-secondary"
    >
        Back to Requests
    </a>

</div>

<?php if (!empty($errors)): ?>

    <div class="alert alert-danger">

        <?php foreach ($errors as $error): ?>

            <p>
                <?= htmlspecialchars($error); ?>
            </p>

        <?php endforeach; ?>

    </div>

<?php endif; ?>

<div class="card requisition-details-card">

    <div class="requisition-details-header">

        <div class="requisition-title-row">

            <div>

                <span class="requisition-header-label">
                    Requisition Number
                </span>

                <h2>
                    <?= htmlspecialchars(
                        $request[
                            'requisitionNumber'
                        ]
                    ); ?>
                </h2>

                <p>
                    Submitted on
                    <?= date(
                        'd M Y, H:i',
                        strtotime(
                            $request[
                                'requestTime'
                            ]
                        )
                    ); ?>
                </p>

            </div>

            <div class="review-header-badges">

                <span
                    class="badge <?= $requisitionStatusClass; ?>"
                >
                    <?= htmlspecialchars(
                        $request[
                            'requisitionStatus'
                        ]
                    ); ?>
                </span>

                <span
                    class="badge <?= $request[
                        'assignedAs'
                    ] === 'Proxy'
                        ? 'badge-info'
                        : 'badge-secondary'; ?>"
                >
                    <?= htmlspecialchars(
                        $request['assignedAs']
                    ); ?>
                    Assignment
                </span>

            </div>

        </div>

    </div>

    <div class="requisition-details-grid">

        <div class="requisition-detail-item">

            <span>Submitted By</span>

            <strong>
                <?= htmlspecialchars(
                    $request['officialName']
                ); ?>
            </strong>

        </div>

        <div class="requisition-detail-item">

            <span>Club</span>

            <strong>
                <?= htmlspecialchars(
                    $request['clubName']
                ); ?>
            </strong>

        </div>

        <div class="requisition-detail-item">

            <span>Official Position</span>

            <strong>
                <?= htmlspecialchars(
                    $request[
                        'officialPosition'
                    ]
                ); ?>
            </strong>

        </div>

        <div class="requisition-detail-item">

            <span>Approval Stage</span>

            <strong>
                Stage
                <?= (int) $request[
                    'approvalOrder'
                ]; ?>
                of
                <?= $totalStages; ?>
            </strong>

        </div>

        <div class="requisition-detail-item">

            <span>Resource</span>

            <strong>
                <?= htmlspecialchars(
                    $request[
                        'resourceName'
                    ]
                ); ?>
            </strong>

        </div>

        <div class="requisition-detail-item">

            <span>Category</span>

            <strong>
                <?= htmlspecialchars(
                    $request[
                        'resourceCategory'
                    ]
                ); ?>
            </strong>

        </div>

        <div class="requisition-detail-item">

            <span>Quantity Requested</span>

            <strong>
                <?= (int) $request[
                    'quantityRequested'
                ]; ?>
            </strong>

        </div>

        <div class="requisition-detail-item">

            <span>Quantity Remaining</span>

            <strong>
                <?= (int) $request[
                    'resourceQuantityRemaining'
                ]; ?>
                of
                <?= (int) $request[
                    'resourceQuantityTotal'
                ]; ?>
            </strong>

        </div>

        <div class="requisition-detail-item">

            <span>Start Date</span>

            <strong>
                <?= date(
                    'd M Y',
                    strtotime(
                        $request['startDate']
                    )
                ); ?>
            </strong>

        </div>

        <div class="requisition-detail-item">

            <span>End Date</span>

            <strong>
                <?= date(
                    'd M Y',
                    strtotime(
                        $request['endDate']
                    )
                ); ?>
            </strong>

        </div>

        <div class="requisition-detail-item">

            <span>Your Role</span>

            <strong>
                <?= htmlspecialchars(
                    $loggedOfficer[
                        'officerRole'
                    ]
                ); ?>
            </strong>

        </div>

        <div class="requisition-detail-item">

            <span>Approval Status</span>

            <strong>

                <span
                    class="badge <?= $request[
                        'approvalStatus'
                    ] === 'Pending'
                        ? 'badge-warning'
                        : (
                            $request[
                                'approvalStatus'
                            ] === 'Approved'
                                ? 'badge-success'
                                : (
                                    $request[
                                        'approvalStatus'
                                    ] === 'Rejected'
                                        ? 'badge-danger'
                                        : 'badge-secondary'
                                )
                        ); ?>"
                >
                    <?= htmlspecialchars(
                        $request[
                            'approvalStatus'
                        ]
                    ); ?>
                </span>

            </strong>

        </div>

    </div>

    <div class="requisition-purpose-section">

        <span>Purpose</span>

        <p>
            <?= nl2br(
                htmlspecialchars(
                    $request['purpose']
                )
            ); ?>
        </p>

    </div>

    <?php if (
        !empty(
            $request[
                'resourceDescription'
            ]
        )
    ): ?>

        <div class="requisition-purpose-section">

            <span>Resource Description</span>

            <p>
                <?= nl2br(
                    htmlspecialchars(
                        $request[
                            'resourceDescription'
                        ]
                    )
                ); ?>
            </p>

        </div>

    <?php endif; ?>

    <div class="requisition-progress-card">

        <div class="requisition-progress-content">

            <h3>
                Approval Progress

                <span class="approval-progress-count">
                    <?= $completedStages; ?>
                    of
                    <?= $totalStages; ?>
                    stage(s) completed
                </span>
            </h3>

            <div class="approval-progress-bar">

                <span
                    class="approval-progress-bar-fill"
                    style="width: <?= $progressPercentage; ?>%;"
                ></span>

            </div>

            <p>
                You are reviewing approval stage
                <?= (int) $request[
                    'approvalOrder'
                ]; ?>.
            </p>

        </div>

    </div>

</div>

<div class="review-request-layout">

    <div class="card review-decision-card">

        <div class="section-header">

            <h2>Record Decision</h2>

            <p>
                Approve the request or provide a reason for
                rejection.
            </p>

        </div>

        <?php if ($canAct): ?>

            <form
                method="POST"
                id="reviewDecisionForm"
            >

                <input
                    type="hidden"
                    name="approvalNumber"
                    value="<?= $approvalNumber; ?>"
                >

                <input
                    type="hidden"
                    name="decision"
                    id="reviewDecision"
                    value=""
                >

                <div class="form-group">

                    <label for="comments">
                        Comments
                    </label>

                    <textarea
                        id="comments"
                        name="comments"
                        maxlength="1000"
                        placeholder="Add comments about your decision..."
                    ><?= htmlspecialchars(
                        $_POST['comments'] ?? ''
                    ); ?></textarea>

                    <small class="form-help">
                        Comments are optional for approval and
                        required for rejection.
                    </small>

                </div>

                <div class="review-decision-actions">

                    <div class="review-decision-primary">

                        <button
                            type="button"
                            class="btn btn-success"
                            id="approveRequestButton"
                        >
                            Approve Request
                        </button>

                    </div>

                    <div class="review-decision-danger">

                        <button
                            type="button"
                            class="btn btn-danger-outline"
                            id="rejectRequestButton"
                        >
                            Reject Request
                        </button>

                    </div>

                </div>

            </form>

        <?php else: ?>

            <div class="review-locked-state">

                <strong>
                    This request cannot currently be acted on.
                </strong>

                <p>

                    <?php if (
                        $request[
                            'approvalStatus'
                        ] !== 'Pending'
                    ): ?>

                        This approval stage has already been
                        processed.

                    <?php elseif (
                        $request[
                            'requisitionStatus'
                        ] !== 'Pending'
                    ): ?>

                        The requisition is no longer pending.

                    <?php elseif (
                        $loggedOfficer[
                            'accountStatus'
                        ] !== 'Active'
                    ): ?>

                        Your account is not active.

                    <?php elseif (
                        $loggedOfficer[
                            'availability'
                        ] !== 'Available'
                    ): ?>

                        Your availability status is currently
                        unavailable.

                    <?php endif; ?>

                </p>

            </div>

        <?php endif; ?>

    </div>

    <div class="card review-officer-card">

        <div class="section-header">

            <h2>Your Assignment</h2>

            <p>
                Officer details for this approval stage.
            </p>

        </div>

        <div class="review-officer-summary">

            <div class="review-officer-avatar">
                <?= htmlspecialchars(
                    strtoupper(
                        substr(
                            $loggedOfficer[
                                'officerName'
                            ],
                            0,
                            1
                        )
                    )
                ); ?>
            </div>

            <div>

                <strong>
                    <?= htmlspecialchars(
                        $loggedOfficer[
                            'officerName'
                        ]
                    ); ?>
                </strong>

                <span>
                    <?= htmlspecialchars(
                        $loggedOfficer[
                            'officerRole'
                        ]
                    ); ?>
                </span>

            </div>

        </div>

        <div class="review-officer-details">

            <div>

                <span>Staff Number</span>

                <strong>
                    <?= htmlspecialchars(
                        $loggedOfficer[
                            'officerStaffNo'
                        ]
                    ); ?>
                </strong>

            </div>

            <div>

                <span>Assignment</span>

                <strong>
                    <?= htmlspecialchars(
                        $request['assignedAs']
                    ); ?>
                </strong>

            </div>

            <div>

                <span>Availability</span>

                <strong>

                    <span
                        class="badge <?= $loggedOfficer[
                            'availability'
                        ] === 'Available'
                            ? 'badge-success'
                            : 'badge-danger'; ?>"
                    >
                        <?= htmlspecialchars(
                            $loggedOfficer[
                                'availability'
                            ]
                        ); ?>
                    </span>

                </strong>

            </div>

        </div>

    </div>

</div>

<div class="card approval-stages-card">

    <div class="section-header-row">

        <div class="section-header">

            <h2>Approval Route</h2>

            <p>
                Current status of every approval stage.
            </p>

        </div>

        <span class="approval-stage-total">
            <?= $totalStages; ?>
            stage<?= $totalStages === 1 ? '' : 's'; ?>
        </span>

    </div>

    <div class="table-responsive">

        <table class="data-table review-stage-table">

            <thead>

                <tr>
                    <th>Stage</th>
                    <th>Officer</th>
                    <th>Role</th>
                    <th>Assignment</th>
                    <th>Status</th>
                    <th>Action Time</th>
                </tr>

            </thead>

            <tbody>

                <?php foreach (
                    $approvalStages as $stage
                ): ?>

                    <?php
                    $stageBadgeClass =
                        match (
                            $stage['status']
                        ) {
                            'Approved' =>
                                'badge-success',

                            'Rejected' =>
                                'badge-danger',

                            'Pending' =>
                                'badge-warning',

                            'Delegated' =>
                                'badge-info',

                            default =>
                                'badge-secondary'
                        };
                    ?>

                    <tr>

                        <td>
                            <strong>
                                Stage
                                <?= (int) $stage[
                                    'approvalOrder'
                                ]; ?>
                            </strong>
                        </td>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $stage[
                                        'officerName'
                                    ]
                                ); ?>
                            </strong>

                            <?php if (
                                !empty(
                                    $stage[
                                        'actingOfficerName'
                                    ]
                                ) &&
                                $stage[
                                    'actingOfficerName'
                                ] !==
                                $stage[
                                    'officerName'
                                ]
                            ): ?>

                                <small class="table-subtext">
                                    Acted by
                                    <?= htmlspecialchars(
                                        $stage[
                                            'actingOfficerName'
                                        ]
                                    ); ?>
                                </small>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $stage[
                                    'officerRole'
                                ]
                            ); ?>
                        </td>

                        <td>

                            <span
                                class="badge <?= $stage[
                                    'assignedAs'
                                ] === 'Proxy'
                                    ? 'badge-info'
                                    : 'badge-secondary'; ?>"
                            >
                                <?= htmlspecialchars(
                                    $stage[
                                        'assignedAs'
                                    ]
                                ); ?>
                            </span>

                        </td>

                        <td>

                            <span
                                class="badge <?= $stageBadgeClass; ?>"
                            >
                                <?= htmlspecialchars(
                                    $stage['status']
                                ); ?>
                            </span>

                        </td>

                        <td>

                            <?php if (
                                !empty(
                                    $stage[
                                        'approvalTime'
                                    ]
                                )
                            ): ?>

                                <?= date(
                                    'd M Y, H:i',
                                    strtotime(
                                        $stage[
                                            'approvalTime'
                                        ]
                                    )
                                ); ?>

                            <?php else: ?>

                                <span class="table-muted">
                                    Not acted on
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const form =
        document.getElementById(
            'reviewDecisionForm'
        );

    const decisionInput =
        document.getElementById(
            'reviewDecision'
        );

    const comments =
        document.getElementById(
            'comments'
        );

    const approveButton =
        document.getElementById(
            'approveRequestButton'
        );

    const rejectButton =
        document.getElementById(
            'rejectRequestButton'
        );

    if (
        !form ||
        !decisionInput
    ) {
        return;
    }

    if (approveButton) {

        approveButton.addEventListener(
            'click',
            function () {

                const confirmed =
                    window.confirm(
                        'Approve this requisition and move it to the next approval stage?'
                    );

                if (!confirmed) {
                    return;
                }

                decisionInput.value =
                    'approve';

                form.submit();
            }
        );
    }

    if (rejectButton) {

        rejectButton.addEventListener(
            'click',
            function () {

                if (
                    !comments ||
                    comments.value.trim() === ''
                ) {
                    window.alert(
                        'Enter a reason before rejecting the requisition.'
                    );

                    if (comments) {
                        comments.focus();
                    }

                    return;
                }

                const confirmed =
                    window.confirm(
                        'Reject this requisition? This will stop the remaining approval stages.'
                    );

                if (!confirmed) {
                    return;
                }

                decisionInput.value =
                    'reject';

                form.submit();
            }
        );
    }
});
</script>

<?php
require_once '../includes/footer.php';
?>