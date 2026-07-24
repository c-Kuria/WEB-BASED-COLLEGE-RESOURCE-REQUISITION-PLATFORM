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

$userID = (int) ($_SESSION['userID'] ?? 0);

if ($userID <= 0) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Retrieve the logged-in club official
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

$officialStmt = mysqli_prepare(
    $conn,
    $officialSql
);

if (!$officialStmt) {
    die(
        'Unable to prepare the official profile query: ' .
        mysqli_error($conn)
    );
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
    die(
        'Your club official profile could not be found. ' .
        'Contact the administrator.'
    );
}

$admNo = $official['admNo'];

/*
|--------------------------------------------------------------------------
| Status filter
|--------------------------------------------------------------------------
*/

$statusFilter = trim(
    $_GET['status'] ?? ''
);

$allowedStatuses = [
    'Pending',
    'Approved',
    'Rejected',
    'Cancelled'
];

if (
    $statusFilter !== '' &&
    !in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {
    $statusFilter = '';
}

/*
|--------------------------------------------------------------------------
| Summary counts
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT
        COUNT(*) AS total,

        SUM(
            CASE
                WHEN status = 'Pending'
                THEN 1
                ELSE 0
            END
        ) AS pending,

        SUM(
            CASE
                WHEN status = 'Approved'
                THEN 1
                ELSE 0
            END
        ) AS approved,

        SUM(
            CASE
                WHEN status = 'Rejected'
                THEN 1
                ELSE 0
            END
        ) AS rejected,

        SUM(
            CASE
                WHEN status = 'Cancelled'
                THEN 1
                ELSE 0
            END
        ) AS cancelled

    FROM requisitions

    WHERE submittedByAdmNo = ?
";

$countStmt = mysqli_prepare(
    $conn,
    $countSql
);

if (!$countStmt) {
    die(
        'Unable to prepare requisition totals: ' .
        mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $countStmt,
    's',
    $admNo
);

mysqli_stmt_execute($countStmt);

$countResult =
    mysqli_stmt_get_result($countStmt);

$counts =
    mysqli_fetch_assoc($countResult);

mysqli_stmt_close($countStmt);

$totalCount =
    (int) ($counts['total'] ?? 0);

$pendingCount =
    (int) ($counts['pending'] ?? 0);

$approvedCount =
    (int) ($counts['approved'] ?? 0);

$rejectedCount =
    (int) ($counts['rejected'] ?? 0);

$cancelledCount =
    (int) ($counts['cancelled'] ?? 0);

/*
|--------------------------------------------------------------------------
| Retrieve requisitions
|--------------------------------------------------------------------------
*/

$requisitionSql = "
    SELECT
        r.requisitionID,
        r.requisitionNumber,
        r.purpose,
        r.quantityRequested,
        r.startDate,
        r.endDate,
        r.requestTime,
        r.status,

        res.resourceName,
        res.resourceCategory,

        (
            SELECT COUNT(*)
            FROM approvals allStages
            WHERE allStages.requisitionID =
                  r.requisitionID
        ) AS totalStages,

        (
            SELECT COUNT(*)
            FROM approvals approvedStages
            WHERE approvedStages.requisitionID =
                  r.requisitionID
              AND approvedStages.status = 'Approved'
        ) AS completedStages,

        (
            SELECT MIN(currentStage.approvalOrder)
            FROM approvals currentStage
            WHERE currentStage.requisitionID =
                  r.requisitionID
              AND currentStage.status IN (
                  'Pending',
                  'Delegated'
              )
        ) AS currentApprovalOrder

    FROM requisitions r

    INNER JOIN resources res
        ON r.resourceID = res.resourceID

    WHERE r.submittedByAdmNo = ?
";

if ($statusFilter !== '') {
    $requisitionSql .= "
        AND r.status = ?
    ";
}

$requisitionSql .= "
    ORDER BY r.requestTime DESC
";

$requisitionStmt = mysqli_prepare(
    $conn,
    $requisitionSql
);

if (!$requisitionStmt) {
    die(
        'Unable to prepare requisitions query: ' .
        mysqli_error($conn)
    );
}

if ($statusFilter !== '') {

    mysqli_stmt_bind_param(
        $requisitionStmt,
        'ss',
        $admNo,
        $statusFilter
    );

} else {

    mysqli_stmt_bind_param(
        $requisitionStmt,
        's',
        $admNo
    );
}

mysqli_stmt_execute($requisitionStmt);

$requisitionResult =
    mysqli_stmt_get_result($requisitionStmt);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>My Requisitions</h1>

            <p>
                View and track resource requests submitted for
                <?= htmlspecialchars(
                    $official['clubName']
                ); ?>.
            </p>
        </div>

        <a
            href="create_requisition.php"
            class="btn btn-primary"
        >
            New Requisition
        </a>

    </div>

    <?php if (isset($_SESSION['success'])): ?>

        <div class="alert alert-success">
            <?= htmlspecialchars(
                $_SESSION['success']
            ); ?>
        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars(
                $_SESSION['error']
            ); ?>
        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>

    <!-- Summary cards -->

    <div class="stats-grid">

        <a
            href="my_requisitions.php"
            class="stat-card requisition-stat-link"
        >
            <h3>All Requisitions</h3>

            <div class="stat-number">
                <?= $totalCount; ?>
            </div>
        </a>

        <a
            href="my_requisitions.php?status=Pending"
            class="stat-card requisition-stat-link"
        >
            <h3>Pending</h3>

            <div class="stat-number">
                <?= $pendingCount; ?>
            </div>
        </a>

        <a
            href="my_requisitions.php?status=Approved"
            class="stat-card requisition-stat-link"
        >
            <h3>Approved</h3>

            <div class="stat-number">
                <?= $approvedCount; ?>
            </div>
        </a>

        <a
            href="my_requisitions.php?status=Rejected"
            class="stat-card requisition-stat-link"
        >
            <h3>Rejected</h3>

            <div class="stat-number">
                <?= $rejectedCount; ?>
            </div>
        </a>

        <a
            href="my_requisitions.php?status=Cancelled"
            class="stat-card requisition-stat-link"
        >
            <h3>Cancelled</h3>

            <div class="stat-number">
                <?= $cancelledCount; ?>
            </div>
        </a>

    </div>

    <!-- Filter -->

    <div class="card requisition-filter-card">

        <form
            method="GET"
            action="my_requisitions.php"
            class="requisition-filter-form"
        >

            <div class="form-group">

                <label for="status">
                    Filter by Status
                </label>

                <select
                    id="status"
                    name="status"
                    onchange="this.form.submit();"
                >

                    <option value="">
                        All Requisitions
                    </option>

                    <?php foreach (
                        $allowedStatuses as $status
                    ): ?>

                        <option
                            value="<?= htmlspecialchars($status); ?>"
                            <?= $statusFilter === $status
                                ? 'selected'
                                : ''; ?>
                        >
                            <?= htmlspecialchars($status); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <?php if ($statusFilter !== ''): ?>

                <a
                    href="my_requisitions.php"
                    class="btn btn-secondary btn-small"
                >
                    Clear Filter
                </a>

            <?php endif; ?>

        </form>

    </div>

    <!-- Requisitions -->

    <?php if (
        mysqli_num_rows($requisitionResult) > 0
    ): ?>

        <?php while (
            $requisition =
                mysqli_fetch_assoc(
                    $requisitionResult
                )
        ): ?>

            <?php

            $requisitionID =
                (int) $requisition['requisitionID'];

            $totalStages =
                (int) $requisition['totalStages'];

            $completedStages =
                (int) $requisition['completedStages'];

            $currentApprovalOrder =
                $requisition['currentApprovalOrder']
                    !== null
                    ? (int) $requisition[
                        'currentApprovalOrder'
                    ]
                    : null;

            $progressPercentage = 0;

            if ($totalStages > 0) {
                $progressPercentage = round(
                    (
                        $completedStages /
                        $totalStages
                    ) * 100
                );
            }

            if (
                $requisition['status'] === 'Approved'
            ) {
                $progressPercentage = 100;
            }

            $statusClass = 'badge-secondary';

            if (
                $requisition['status'] === 'Pending'
            ) {
                $statusClass = 'badge-warning';

            } elseif (
                $requisition['status'] === 'Approved'
            ) {
                $statusClass = 'badge-success';

            } elseif (
                $requisition['status'] === 'Rejected'
            ) {
                $statusClass = 'badge-danger';
            }

            /*
             * Load approval stages for this requisition.
             */
            $approvalSql = "
                SELECT
                    a.approvalNumber,
                    a.approvalOrder,
                    a.status,
                    a.assignedAs,
                    a.actedBy,
                    a.comments,
                    a.approvalTime,

                    primaryOfficer.officerName
                        AS primaryOfficerName,

                    primaryOfficer.officerRole
                        AS primaryOfficerRole,

                    proxyOfficer.officerName
                        AS proxyOfficerName,

                    proxyOfficer.officerRole
                        AS proxyOfficerRole

                FROM approvals a

                INNER JOIN officers primaryOfficer
                    ON a.officerStaffNo =
                       primaryOfficer.officerStaffNo

                LEFT JOIN officers proxyOfficer
                    ON a.actedBy =
                       proxyOfficer.officerStaffNo

                WHERE a.requisitionID = ?

                ORDER BY a.approvalOrder
            ";

            $approvalStmt = mysqli_prepare(
                $conn,
                $approvalSql
            );

            if (!$approvalStmt) {
                die(
                    'Unable to prepare approval tracking: ' .
                    mysqli_error($conn)
                );
            }

            mysqli_stmt_bind_param(
                $approvalStmt,
                'i',
                $requisitionID
            );

            mysqli_stmt_execute($approvalStmt);

            $approvalResult =
                mysqli_stmt_get_result($approvalStmt);

            ?>

            <div class="card requisition-card">

                <div class="requisition-card-header">

                    <div>

                        <h2>
                            <?= htmlspecialchars(
                                $requisition[
                                    'requisitionNumber'
                                ] ??
                                (
                                    'REQ-' .
                                    str_pad(
                                        $requisitionID,
                                        4,
                                        '0',
                                        STR_PAD_LEFT
                                    )
                                )
                            ); ?>
                        </h2>

                        <p class="text-muted">
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
                        class="badge <?= $statusClass; ?>"
                    >
                        <?= htmlspecialchars(
                            $requisition['status']
                        ); ?>
                    </span>

                </div>

                <div class="requisition-details-grid">

                    <div>
                        <strong>Resource</strong>

                        <p>
                            <?= htmlspecialchars(
                                $requisition[
                                    'resourceName'
                                ]
                            ); ?>
                        </p>
                    </div>

                    <div>
                        <strong>Category</strong>

                        <p>
                            <?= htmlspecialchars(
                                $requisition[
                                    'resourceCategory'
                                ]
                            ); ?>
                        </p>
                    </div>

                    <div>
                        <strong>Quantity</strong>

                        <p>
                            <?= (int) $requisition[
                                'quantityRequested'
                            ]; ?>
                        </p>
                    </div>

                    <div>
                        <strong>Required Period</strong>

                        <p>
                            <?= date(
                                'd M Y',
                                strtotime(
                                    $requisition['startDate']
                                )
                            ); ?>

                            to

                            <?= date(
                                'd M Y',
                                strtotime(
                                    $requisition['endDate']
                                )
                            ); ?>
                        </p>
                    </div>

                    <div class="full-width">
                        <strong>Purpose</strong>

                        <p>
                            <?= nl2br(
                                htmlspecialchars(
                                    $requisition['purpose']
                                )
                            ); ?>
                        </p>
                    </div>

                </div>

                <!-- Approval progress -->

                <div class="approval-progress-section">

                    <div class="approval-progress-header">

                        <strong>Approval Progress</strong>

                        <span>
                            <?= $completedStages; ?>
                            of
                            <?= $totalStages; ?>
                            stage(s) completed
                        </span>

                    </div>

                    <div class="progress-bar">

                        <div
                            class="progress-bar-fill"
                            style="width: <?= $progressPercentage; ?>%;"
                        ></div>

                    </div>

                    <?php if (
                        $requisition['status'] === 'Pending' &&
                        $currentApprovalOrder !== null
                    ): ?>

                        <small class="text-muted">
                            Currently at approval stage
                            <?= $currentApprovalOrder; ?>.
                        </small>

                    <?php elseif (
                        $requisition['status'] === 'Approved'
                    ): ?>

                        <small class="text-muted">
                            All approval stages have been completed.
                        </small>

                    <?php elseif (
                        $requisition['status'] === 'Rejected'
                    ): ?>

                        <small class="text-muted">
                            This requisition was rejected during
                            the approval process.
                        </small>

                    <?php elseif (
                        $requisition['status'] === 'Cancelled'
                    ): ?>

                        <small class="text-muted">
                            This requisition was cancelled.
                        </small>

                    <?php endif; ?>

                </div>

                <!-- Approval stages -->

                <details class="approval-details">

                    <summary>
                        View Approval Stages
                    </summary>

                    <div class="approval-timeline">

                        <?php if (
                            mysqli_num_rows(
                                $approvalResult
                            ) > 0
                        ): ?>

                            <?php while (
                                $approval =
                                    mysqli_fetch_assoc(
                                        $approvalResult
                                    )
                            ): ?>

                                <?php

                                $approvalStatusClass =
                                    'badge-secondary';

                                if (
                                    $approval['status'] ===
                                    'Pending'
                                ) {
                                    $approvalStatusClass =
                                        'badge-warning';

                                } elseif (
                                    $approval['status'] ===
                                    'Approved'
                                ) {
                                    $approvalStatusClass =
                                        'badge-success';

                                } elseif (
                                    $approval['status'] ===
                                    'Rejected'
                                ) {
                                    $approvalStatusClass =
                                        'badge-danger';

                                } elseif (
                                    $approval['status'] ===
                                    'Delegated'
                                ) {
                                    $approvalStatusClass =
                                        'badge-info';
                                }

                                $displayOfficerName =
                                    $approval[
                                        'primaryOfficerName'
                                    ];

                                $displayOfficerRole =
                                    $approval[
                                        'primaryOfficerRole'
                                    ];

                                $isProxy =
                                    $approval['assignedAs'] ===
                                    'Proxy' &&
                                    !empty(
                                        $approval[
                                            'proxyOfficerName'
                                        ]
                                    );

                                if ($isProxy) {
                                    $displayOfficerName =
                                        $approval[
                                            'proxyOfficerName'
                                        ];

                                    $displayOfficerRole =
                                        $approval[
                                            'proxyOfficerRole'
                                        ];
                                }

                                ?>

                                <div class="approval-stage">

                                    <div
                                        class="approval-stage-number"
                                    >
                                        <?= (int) $approval[
                                            'approvalOrder'
                                        ]; ?>
                                    </div>

                                    <div
                                        class="approval-stage-content"
                                    >

                                        <div
                                            class="approval-stage-header"
                                        >

                                            <div>

                                                <strong>
                                                    <?= htmlspecialchars(
                                                        $displayOfficerRole
                                                    ); ?>
                                                </strong>

                                                <p>
                                                    <?= htmlspecialchars(
                                                        $displayOfficerName
                                                    ); ?>

                                                    <?php if (
                                                        $isProxy
                                                    ): ?>

                                                        <span
                                                            class="proxy-label"
                                                        >
                                                            Proxy
                                                        </span>

                                                    <?php endif; ?>

                                                </p>

                                            </div>

                                            <span
                                                class="badge <?= $approvalStatusClass; ?>"
                                            >
                                                <?= htmlspecialchars(
                                                    $approval[
                                                        'status'
                                                    ]
                                                ); ?>
                                            </span>

                                        </div>

                                        <?php if ($isProxy): ?>

                                            <p class="text-muted">
                                                Acting on behalf of
                                                <?= htmlspecialchars(
                                                    $approval[
                                                        'primaryOfficerName'
                                                    ]
                                                ); ?>,
                                                <?= htmlspecialchars(
                                                    $approval[
                                                        'primaryOfficerRole'
                                                    ]
                                                ); ?>.
                                            </p>

                                        <?php endif; ?>

                                        <?php if (
                                            !empty(
                                                $approval['comments']
                                            )
                                        ): ?>

                                            <div
                                                class="approval-comment"
                                            >
                                                <strong>
                                                    Comments
                                                </strong>

                                                <p>
                                                    <?= nl2br(
                                                        htmlspecialchars(
                                                            $approval[
                                                                'comments'
                                                            ]
                                                        )
                                                    ); ?>
                                                </p>
                                            </div>

                                        <?php endif; ?>

                                        <?php if (
                                            !empty(
                                                $approval[
                                                    'approvalTime'
                                                ]
                                            )
                                        ): ?>

                                            <small
                                                class="text-muted"
                                            >
                                                Action taken on
                                                <?= date(
                                                    'd M Y, H:i',
                                                    strtotime(
                                                        $approval[
                                                            'approvalTime'
                                                        ]
                                                    )
                                                ); ?>
                                            </small>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <p class="empty-state">
                                No approval stages were found for
                                this requisition.
                            </p>

                        <?php endif; ?>

                    </div>

                </details>

                <!-- Actions -->

                <?php if (
                    $requisition['status'] === 'Pending' &&
                    $completedStages === 0
                ): ?>

                    <div class="requisition-actions">

                        <form
                            method="POST"
                            action="cancel_requisition.php"
                            onsubmit="return confirm(
                                'Are you sure you want to cancel this requisition?'
                            );"
                        >

                            <input
                                type="hidden"
                                name="requisitionID"
                                value="<?= $requisitionID; ?>"
                            >

                            <button
                                type="submit"
                                class="btn btn-danger btn-small"
                            >
                                Cancel Requisition
                            </button>

                        </form>

                    </div>

                <?php endif; ?>

            </div>

            <?php mysqli_stmt_close($approvalStmt); ?>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="card empty-state">

            <h2>No requisitions found</h2>

            <p>
                <?php if ($statusFilter !== ''): ?>

                    You do not have any
                    <?= htmlspecialchars(
                        strtolower($statusFilter)
                    ); ?>
                    requisitions.

                <?php else: ?>

                    You have not submitted any requisitions yet.

                <?php endif; ?>
            </p>

            <a
                href="create_requisition.php"
                class="btn btn-primary"
            >
                Create Requisition
            </a>

        </div>

    <?php endif; ?>

</div>

<?php

mysqli_stmt_close($requisitionStmt);

require_once '../includes/footer.php';

?>