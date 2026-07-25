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

$pageTitle = 'My Requisitions';

$officialSql = "
    SELECT
        admNo,
        officialName
    FROM club_officials
    WHERE userID = ?
    LIMIT 1
";

$officialStmt =
    mysqli_prepare(
        $conn,
        $officialSql
    );

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
    die('Official profile not found.');
}

$admNo =
    $official['admNo'];

$selectedStatus =
    trim($_GET['status'] ?? '');

$allowedStatuses = [
    'Pending',
    'Approved',
    'Rejected',
    'Cancelled'
];

if (
    $selectedStatus !== '' &&
    !in_array(
        $selectedStatus,
        $allowedStatuses,
        true
    )
) {
    $selectedStatus = '';
}

if ($selectedStatus === '') {

    $sql = "
        SELECT
            r.requisitionID,
            r.requisitionNumber,
            r.resourceID,
            r.purpose,
            r.quantityRequested,
            r.startDate,
            r.endDate,
            r.requestTime,
            r.status,

            rs.resourceName,
            rs.resourceCategory,

            COUNT(a.approvalNumber)
                AS totalStages,

            SUM(
                CASE
                    WHEN a.status = 'Approved'
                    THEN 1
                    ELSE 0
                END
            ) AS completedStages,

            MIN(
                CASE
                    WHEN a.status = 'Pending'
                    THEN a.approvalOrder
                    ELSE NULL
                END
            ) AS currentStage

        FROM requisitions r

        INNER JOIN resources rs
            ON r.resourceID = rs.resourceID

        LEFT JOIN approvals a
            ON r.requisitionID =
               a.requisitionID

        WHERE r.submittedByAdmNo = ?

        GROUP BY
            r.requisitionID,
            r.requisitionNumber,
            r.resourceID,
            r.purpose,
            r.quantityRequested,
            r.startDate,
            r.endDate,
            r.requestTime,
            r.status,
            rs.resourceName,
            rs.resourceCategory

        ORDER BY r.requestTime DESC
    ";

    $stmt =
        mysqli_prepare(
            $conn,
            $sql
        );

    mysqli_stmt_bind_param(
        $stmt,
        's',
        $admNo
    );

} else {

    $sql = "
        SELECT
            r.requisitionID,
            r.requisitionNumber,
            r.resourceID,
            r.purpose,
            r.quantityRequested,
            r.startDate,
            r.endDate,
            r.requestTime,
            r.status,

            rs.resourceName,
            rs.resourceCategory,

            COUNT(a.approvalNumber)
                AS totalStages,

            SUM(
                CASE
                    WHEN a.status = 'Approved'
                    THEN 1
                    ELSE 0
                END
            ) AS completedStages,

            MIN(
                CASE
                    WHEN a.status = 'Pending'
                    THEN a.approvalOrder
                    ELSE NULL
                END
            ) AS currentStage

        FROM requisitions r

        INNER JOIN resources rs
            ON r.resourceID = rs.resourceID

        LEFT JOIN approvals a
            ON r.requisitionID =
               a.requisitionID

        WHERE r.submittedByAdmNo = ?
          AND r.status = ?

        GROUP BY
            r.requisitionID,
            r.requisitionNumber,
            r.resourceID,
            r.purpose,
            r.quantityRequested,
            r.startDate,
            r.endDate,
            r.requestTime,
            r.status,
            rs.resourceName,
            rs.resourceCategory

        ORDER BY r.requestTime DESC
    ";

    $stmt =
        mysqli_prepare(
            $conn,
            $sql
        );

    mysqli_stmt_bind_param(
        $stmt,
        'ss',
        $admNo,
        $selectedStatus
    );
}

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);

require_once '../includes/header.php';
?>

<div class="page-header">

    <div>
        <h1>My Requisitions</h1>

        <p>
            View your submitted requests and approval progress.
        </p>
    </div>

    <a
        href="create_requisition.php"
        class="btn btn-primary"
    >
        New Requisition
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

<div class="card requisition-filter-card">

    <div class="requisition-filter-row">

        <div>

            <strong>Filter by status</strong>

            <p>
                Choose which requisitions to display.
            </p>

        </div>

        <div class="requisition-filter-buttons">

            <a
                href="my_requisitions.php"
                class="filter-button <?= $selectedStatus === ''
                    ? 'active'
                    : ''; ?>"
            >
                All
            </a>

            <a
                href="my_requisitions.php?status=Pending"
                class="filter-button <?= $selectedStatus ===
                'Pending'
                    ? 'active'
                    : ''; ?>"
            >
                Pending
            </a>

            <a
                href="my_requisitions.php?status=Approved"
                class="filter-button <?= $selectedStatus ===
                'Approved'
                    ? 'active'
                    : ''; ?>"
            >
                Approved
            </a>

            <a
                href="my_requisitions.php?status=Rejected"
                class="filter-button <?= $selectedStatus ===
                'Rejected'
                    ? 'active'
                    : ''; ?>"
            >
                Rejected
            </a>

            <a
                href="my_requisitions.php?status=Cancelled"
                class="filter-button <?= $selectedStatus ===
                'Cancelled'
                    ? 'active'
                    : ''; ?>"
            >
                Cancelled
            </a>

        </div>

    </div>

</div>

<div class="card requisition-table-card">

    <div class="table-responsive">

        <table class="data-table requisition-table">

            <thead>

                <tr>
                    <th>Requisition</th>
                    <th>Resource</th>
                    <th>Quantity</th>
                    <th>Required Period</th>
                    <th>Purpose</th>
                    <th>Approval Progress</th>
                    <th>Status</th>
                    <th class="actions-heading">
                        Actions
                    </th>
                </tr>

            </thead>

            <tbody>

                <?php if (
                    mysqli_num_rows($result) > 0
                ): ?>

                    <?php while (
                        $requisition =
                            mysqli_fetch_assoc(
                                $result
                            )
                    ): ?>

                        <?php

                        $totalStages =
                            (int) (
                                $requisition[
                                    'totalStages'
                                ] ?? 0
                            );

                        $completedStages =
                            (int) (
                                $requisition[
                                    'completedStages'
                                ] ?? 0
                            );

                        $currentStage =
                            (int) (
                                $requisition[
                                    'currentStage'
                                ] ?? 0
                            );

                        $progressPercentage =
                            $totalStages > 0
                                ? min(
                                    100,
                                    (
                                        $completedStages /
                                        $totalStages
                                    ) * 100
                                )
                                : 0;

                        $statusClass =
                            match (
                                $requisition['status']
                            ) {
                                'Approved' =>
                                    'badge-success',

                                'Rejected' =>
                                    'badge-danger',

                                'Pending' =>
                                    'badge-warning',

                                'Cancelled' =>
                                    'badge-secondary',

                                default =>
                                    'badge-info'
                            };
                        ?>

                        <tr>

                            <td>

                                <strong class="requisition-number">
                                    <?= htmlspecialchars(
                                        $requisition[
                                            'requisitionNumber'
                                        ]
                                    ); ?>
                                </strong>

                                <small class="table-subtext">
                                    Submitted
                                    <?= date(
                                        'd M Y, H:i',
                                        strtotime(
                                            $requisition[
                                                'requestTime'
                                            ]
                                        )
                                    ); ?>
                                </small>

                            </td>

                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $requisition[
                                            'resourceName'
                                        ]
                                    ); ?>
                                </strong>

                                <small class="table-subtext">
                                    <?= htmlspecialchars(
                                        $requisition[
                                            'resourceCategory'
                                        ]
                                    ); ?>
                                </small>

                            </td>

                            <td>
                                <?= (int) $requisition[
                                    'quantityRequested'
                                ]; ?>
                            </td>

                            <td>

                                <span class="date-range-cell">

                                    <?= date(
                                        'd M Y',
                                        strtotime(
                                            $requisition[
                                                'startDate'
                                            ]
                                        )
                                    ); ?>

                                    <small>to</small>

                                    <?= date(
                                        'd M Y',
                                        strtotime(
                                            $requisition[
                                                'endDate'
                                            ]
                                        )
                                    ); ?>

                                </span>

                            </td>

                            <td>

                                <span
                                    class="table-purpose"
                                    title="<?= htmlspecialchars(
                                        $requisition[
                                            'purpose'
                                        ]
                                    ); ?>"
                                >
                                    <?= htmlspecialchars(
                                        $requisition[
                                            'purpose'
                                        ]
                                    ); ?>
                                </span>

                            </td>

                            <td>

                                <div class="table-progress">

                                    <div class="table-progress-header">

                                        <strong>
                                            <?= $completedStages; ?>
                                            of
                                            <?= $totalStages; ?>
                                        </strong>

                                        <span>
                                            stages
                                        </span>

                                    </div>

                                    <div class="table-progress-track">

                                        <span
                                            class="table-progress-fill"
                                            style="width: <?= $progressPercentage; ?>%;"
                                        ></span>

                                    </div>

                                    <small>

                                        <?php if (
                                            $requisition[
                                                'status'
                                            ] === 'Approved'
                                        ): ?>

                                            All stages completed

                                        <?php elseif (
                                            $requisition[
                                                'status'
                                            ] === 'Rejected'
                                        ): ?>

                                            Approval stopped

                                        <?php elseif (
                                            $requisition[
                                                'status'
                                            ] === 'Cancelled'
                                        ): ?>

                                            Requisition cancelled

                                        <?php elseif (
                                            $currentStage > 0
                                        ): ?>

                                            Current stage:
                                            <?= $currentStage; ?>

                                        <?php else: ?>

                                            Awaiting first approval

                                        <?php endif; ?>

                                    </small>

                                </div>

                            </td>

                            <td>

                                <span
                                    class="badge <?= $statusClass; ?>"
                                >
                                    <?= htmlspecialchars(
                                        $requisition[
                                            'status'
                                        ]
                                    ); ?>
                                </span>

                            </td>

                            <td>

                                <div class="table-actions requisition-actions">

                                    <a
                                        href="view_requisition.php?id=<?= (int) $requisition[
                                            'requisitionID'
                                        ]; ?>"
                                        class="btn btn-secondary btn-small"
                                    >
                                        View
                                    </a>

                                    <?php if (
                                        $requisition[
                                            'status'
                                        ] === 'Pending'
                                    ): ?>

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
                                                value="<?= (int) $requisition[
                                                    'requisitionID'
                                                ]; ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-danger-outline btn-small"
                                            >
                                                Cancel
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="8"
                            class="empty-state"
                        >

                            <strong>
                                No requisitions found
                            </strong>

                            <p>
                                There are no requisitions
                                matching the selected status.
                            </p>

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php

mysqli_stmt_close($stmt);

require_once '../includes/footer.php';

?>