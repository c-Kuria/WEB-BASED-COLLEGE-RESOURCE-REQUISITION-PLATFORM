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

$userID =
    (int) ($_SESSION['userID'] ?? 0);

if ($userID <= 0) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

$pageTitle = 'View Requisition';

/*
|--------------------------------------------------------------------------
| Validate requisition ID
|--------------------------------------------------------------------------
*/

$requisitionID =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );

if (!$requisitionID || $requisitionID <= 0) {

    $_SESSION['error'] =
        'Invalid requisition selected.';

    header('Location: my_requisitions.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Get logged-in official
|--------------------------------------------------------------------------
*/

$officialSql = "
    SELECT
        co.admNo,
        co.officialName,
        co.position,
        co.clubNumber,
        c.clubName

    FROM club_officials co

    INNER JOIN clubs c
        ON co.clubNumber = c.clubNumber

    WHERE co.userID = ?

    LIMIT 1
";

$officialStmt =
    mysqli_prepare(
        $conn,
        $officialSql
    );

if (!$officialStmt) {
    die('Unable to prepare official profile query.');
}

mysqli_stmt_bind_param(
    $officialStmt,
    'i',
    $userID
);

mysqli_stmt_execute(
    $officialStmt
);

$officialResult =
    mysqli_stmt_get_result(
        $officialStmt
    );

$official =
    mysqli_fetch_assoc(
        $officialResult
    );

mysqli_stmt_close(
    $officialStmt
);

if (!$official) {

    $_SESSION['error'] =
        'Official profile not found.';

    header('Location: dashboard.php');
    exit();
}

$admNo =
    $official['admNo'];

/*
|--------------------------------------------------------------------------
| Get requisition
|--------------------------------------------------------------------------
|
| Ownership is checked directly in the query.
|
*/

$requisitionSql = "
    SELECT
        r.requisitionID,
        r.requisitionNumber,
        r.submittedByAdmNo,
        r.resourceID,
        r.purpose,
        r.quantityRequested,
        r.startDate,
        r.endDate,
        r.requestTime,
        r.status,

        rs.resourceName,
        rs.resourceCategory,
        rs.resourceDescription,
        rs.resourceQuantityTotal,
        rs.resourceQuantityRemaining,
        rs.status AS resourceStatus,

        co.officialName,
        co.position AS officialPosition,
        co.clubNumber,

        c.clubName

    FROM requisitions r

    INNER JOIN resources rs
        ON r.resourceID = rs.resourceID

    INNER JOIN club_officials co
        ON r.submittedByAdmNo = co.admNo

    INNER JOIN clubs c
        ON co.clubNumber = c.clubNumber

    WHERE r.requisitionID = ?
      AND r.submittedByAdmNo = ?

    LIMIT 1
";

$requisitionStmt =
    mysqli_prepare(
        $conn,
        $requisitionSql
    );

if (!$requisitionStmt) {
    die('Unable to prepare requisition query.');
}

mysqli_stmt_bind_param(
    $requisitionStmt,
    'is',
    $requisitionID,
    $admNo
);

mysqli_stmt_execute(
    $requisitionStmt
);

$requisitionResult =
    mysqli_stmt_get_result(
        $requisitionStmt
    );

$requisition =
    mysqli_fetch_assoc(
        $requisitionResult
    );

mysqli_stmt_close(
    $requisitionStmt
);

if (!$requisition) {

    $_SESSION['error'] =
        'The requisition was not found or does not belong to your account.';

    header('Location: my_requisitions.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Get approval stages
|--------------------------------------------------------------------------
*/

$approvalSql = "
    SELECT
        a.approvalNumber,
        a.requisitionID,
        a.officerStaffNo,
        a.approvalOrder,
        a.status,
        a.assignedAs,
        a.actedBy,
        a.comments,
        a.approvalTime,

        assignedOfficer.officerName
            AS assignedOfficerName,

        assignedOfficer.officerRole
            AS assignedOfficerRole,

        assignedOfficer.availability
            AS assignedOfficerAvailability,

        assignedOfficer.proxyOfficerStaffNo,

        proxyOfficer.officerName
            AS configuredProxyName,

        proxyOfficer.officerRole
            AS configuredProxyRole,

        actingOfficer.officerName
            AS actingOfficerName,

        actingOfficer.officerRole
            AS actingOfficerRole

    FROM approvals a

    INNER JOIN officers assignedOfficer
        ON a.officerStaffNo =
           assignedOfficer.officerStaffNo

    LEFT JOIN officers proxyOfficer
        ON assignedOfficer.proxyOfficerStaffNo =
           proxyOfficer.officerStaffNo

    LEFT JOIN officers actingOfficer
        ON a.actedBy =
           actingOfficer.officerStaffNo

    WHERE a.requisitionID = ?

    ORDER BY
        a.approvalOrder ASC,
        a.approvalNumber ASC
";

$approvalStmt =
    mysqli_prepare(
        $conn,
        $approvalSql
    );

if (!$approvalStmt) {
    die('Unable to prepare approval stages query.');
}

mysqli_stmt_bind_param(
    $approvalStmt,
    'i',
    $requisitionID
);

mysqli_stmt_execute(
    $approvalStmt
);

$approvalResult =
    mysqli_stmt_get_result(
        $approvalStmt
    );

$approvalStages = [];

while (
    $approval =
        mysqli_fetch_assoc(
            $approvalResult
        )
) {
    $approvalStages[] =
        $approval;
}

mysqli_stmt_close(
    $approvalStmt
);

/*
|--------------------------------------------------------------------------
| Calculate approval progress
|--------------------------------------------------------------------------
*/

$totalStages =
    count($approvalStages);

$completedStages = 0;
$currentStage = 0;
$rejectedStage = 0;

foreach ($approvalStages as $stage) {

    if ($stage['status'] === 'Approved') {
        $completedStages++;
    }

    if (
        $currentStage === 0 &&
        $stage['status'] === 'Pending'
    ) {
        $currentStage =
            (int) $stage['approvalOrder'];
    }

    if (
        $rejectedStage === 0 &&
        $stage['status'] === 'Rejected'
    ) {
        $rejectedStage =
            (int) $stage['approvalOrder'];
    }
}

$progressPercentage =
    $totalStages > 0
        ? min(
            100,
            ($completedStages / $totalStages) * 100
        )
        : 0;

/*
|--------------------------------------------------------------------------
| Requisition status class
|--------------------------------------------------------------------------
*/

$statusClass =
    match ($requisition['status']) {

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

/*
|--------------------------------------------------------------------------
| Can requisition be cancelled?
|--------------------------------------------------------------------------
|
| Cancellation is allowed only while:
| - requisition status is Pending
| - no approval stage has been approved
| - no approval stage has been rejected
|
*/

$canCancel =
    $requisition['status'] === 'Pending' &&
    $completedStages === 0 &&
    $rejectedStage === 0;

require_once '../includes/header.php';
?>

<div class="page-header">

    <div>

        <h1>View Requisition</h1>

        <p>
            Review the requisition details and approval
            progress.
        </p>

    </div>

    <a
        href="my_requisitions.php"
        class="btn btn-secondary"
    >
        Back to Requisitions
    </a>

</div>

<?php if (
    isset($_SESSION['success'])
): ?>

    <div class="alert alert-success">
        <?= htmlspecialchars(
            $_SESSION['success']
        ); ?>
    </div>

    <?php unset(
        $_SESSION['success']
    ); ?>

<?php endif; ?>

<?php if (
    isset($_SESSION['error'])
): ?>

    <div class="alert alert-danger">
        <?= htmlspecialchars(
            $_SESSION['error']
        ); ?>
    </div>

    <?php unset(
        $_SESSION['error']
    ); ?>

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
                        $requisition[
                            'requisitionNumber'
                        ]
                    ); ?>
                </h2>

                <p>
                    Submitted on
                    <?= date(
                        'd M Y, H:i',
                        strtotime(
                            $requisition[
                                'requestTime'
                            ]
                        )
                    ); ?>
                </p>

            </div>

            <span
                class="badge requisition-status-badge <?= $statusClass; ?>"
            >
                <?= htmlspecialchars(
                    $requisition['status']
                ); ?>
            </span>

        </div>

    </div>

    <div class="requisition-details-grid">

        <div class="requisition-detail-item">

            <span>Resource</span>

            <strong>
                <?= htmlspecialchars(
                    $requisition[
                        'resourceName'
                    ]
                ); ?>
            </strong>

        </div>

        <div class="requisition-detail-item">

            <span>Category</span>

            <strong>
                <?= htmlspecialchars(
                    $requisition[
                        'resourceCategory'
                    ]
                ); ?>
            </strong>

        </div>

        <div class="requisition-detail-item">

            <span>Quantity Requested</span>

            <strong>
                <?= (int) $requisition[
                    'quantityRequested'
                ]; ?>
            </strong>

        </div>

        <div class="requisition-detail-item">

            <span>Resource Availability</span>

            <strong>
                <?= (int) $requisition[
                    'resourceQuantityRemaining'
                ]; ?>
                of
                <?= (int) $requisition[
                    'resourceQuantityTotal'
                ]; ?>
                remaining
            </strong>

        </div>

        <div class="requisition-detail-item">

            <span>Start Date</span>

            <strong>
                <?= date(
                    'd M Y',
                    strtotime(
                        $requisition[
                            'startDate'
                        ]
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
                        $requisition[
                            'endDate'
                        ]
                    )
                ); ?>
            </strong>

        </div>

        <div class="requisition-detail-item">

            <span>Submitted By</span>

            <strong>
                <?= htmlspecialchars(
                    $requisition[
                        'officialName'
                    ]
                ); ?>
            </strong>

        </div>

        <div class="requisition-detail-item">

            <span>Club</span>

            <strong>
                <?= htmlspecialchars(
                    $requisition[
                        'clubName'
                    ]
                ); ?>
            </strong>

        </div>

    </div>

    <div class="requisition-purpose-section">

        <span>Purpose</span>

        <p>
            <?= nl2br(
                htmlspecialchars(
                    $requisition['purpose']
                )
            ); ?>
        </p>

    </div>

    <?php if (
        !empty(
            $requisition[
                'resourceDescription'
            ]
        )
    ): ?>

        <div class="requisition-purpose-section">

            <span>Resource Description</span>

            <p>
                <?= nl2br(
                    htmlspecialchars(
                        $requisition[
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

                <?php if (
                    $requisition['status'] ===
                    'Approved'
                ): ?>

                    All approval stages have been completed.

                <?php elseif (
                    $requisition['status'] ===
                    'Rejected'
                ): ?>

                    The requisition was rejected at approval
                    stage
                    <?= $rejectedStage; ?>.

                <?php elseif (
                    $requisition['status'] ===
                    'Cancelled'
                ): ?>

                    This requisition was cancelled.

                <?php elseif (
                    $currentStage > 0
                ): ?>

                    Currently awaiting approval at stage
                    <?= $currentStage; ?>.

                <?php else: ?>

                    Waiting for the first approval stage.

                <?php endif; ?>

            </p>

        </div>

        <?php if ($canCancel): ?>

            <div class="requisition-progress-actions">

                <form
                    method="POST"
                    action="cancel_requisition.php"
                    onsubmit="return confirm(
                        'Are you sure you want to cancel this requisition? This action cannot be undone.'
                    );"
                >

                    <input
                        type="hidden"
                        name="requisitionID"
                        value="<?= $requisitionID; ?>"
                    >

                    <button
                        type="submit"
                        class="btn btn-danger-outline"
                    >
                        Cancel Requisition
                    </button>

                </form>

            </div>

        <?php endif; ?>

    </div>

</div>

<div class="card approval-stages-card">

    <div class="section-header-row">

        <div class="section-header">

            <h2>Approval Stages</h2>

            <p>
                Current progress of the requisition approval route.
            </p>

        </div>

        <span class="approval-stage-total">
            <?= $totalStages; ?>
            stage<?= $totalStages === 1 ? '' : 's'; ?>
        </span>

    </div>

    <?php if ($totalStages > 0): ?>

        <div class="table-responsive">

            <table class="data-table approval-summary-table">

                <thead>

                    <tr>
                        <th>Officer</th>
                        <th>Status</th>
                        <th>Time</th>
                        <th>Performed By</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach (
                        $approvalStages as $stage
                    ): ?>

                        <?php

                        $stageStatus =
                            $stage['status'];

                        $stageBadgeClass =
                            match ($stageStatus) {

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

                        $performedBy = 'Not acted on';

                        if (
                            !empty(
                                $stage['actingOfficerName']
                            )
                        ) {
                            $performedBy =
                                $stage['actingOfficerName'];

                        } elseif (
                            !empty($stage['actedBy'])
                        ) {
                            $performedBy =
                                $stage['assignedOfficerName'];
                        }

                        ?>

                        <tr>

                            <td>

                                <div class="approval-officer-cell">

                                    <span class="approval-stage-number">
                                        <?= (int) $stage[
                                            'approvalOrder'
                                        ]; ?>
                                    </span>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $stage[
                                                'assignedOfficerRole'
                                            ]
                                        ); ?>
                                    </strong>

                                </div>

                            </td>

                            <td>

                                <span
                                    class="badge <?= $stageBadgeClass; ?>"
                                >
                                    <?= htmlspecialchars(
                                        $stageStatus
                                    ); ?>
                                </span>

                            </td>

                            <td>

                                <?php if (
                                    !empty(
                                        $stage['approvalTime']
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

                            <td>

                                <?php if (
                                    $performedBy !==
                                    'Not acted on'
                                ): ?>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $performedBy
                                        ); ?>
                                    </strong>

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

    <?php else: ?>

        <div class="empty-state">

            <strong>No approval stages found</strong>

            <p>
                The approval workflow was not created for this
                requisition.
            </p>

        </div>

    <?php endif; ?>

</div>

<?php
require_once '../includes/footer.php';
?>