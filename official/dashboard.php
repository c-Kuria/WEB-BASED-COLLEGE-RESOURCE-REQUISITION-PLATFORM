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

$pageTitle = 'Official Dashboard';

$profileSql = "
    SELECT
        co.admNo,
        co.officialName,
        co.position,
        co.email,
        co.phone,
        co.clubNumber,
        co.isChair,

        c.clubName,

        u.status AS accountStatus

    FROM club_officials co

    INNER JOIN clubs c
        ON co.clubNumber = c.clubNumber

    INNER JOIN users u
        ON co.userID = u.userID

    WHERE co.userID = ?

    LIMIT 1
";

$profileStmt =
    mysqli_prepare(
        $conn,
        $profileSql
    );

mysqli_stmt_bind_param(
    $profileStmt,
    'i',
    $userID
);

mysqli_stmt_execute(
    $profileStmt
);

$profileResult =
    mysqli_stmt_get_result(
        $profileStmt
    );

$official =
    mysqli_fetch_assoc(
        $profileResult
    );

mysqli_stmt_close(
    $profileStmt
);

if (!$official) {
    die('Official profile not found.');
}

$admNo =
    $official['admNo'];

function getOfficialRequisitionCount(
    mysqli $conn,
    string $admNo,
    string $status = ''
): int {

    if ($status === '') {

        $sql = "
            SELECT COUNT(*)
            FROM requisitions
            WHERE submittedByAdmNo = ?
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
            SELECT COUNT(*)
            FROM requisitions
            WHERE submittedByAdmNo = ?
              AND status = ?
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
            $status
        );
    }

    mysqli_stmt_execute($stmt);

    $result =
        mysqli_stmt_get_result($stmt);

    $row =
        mysqli_fetch_row($result);

    mysqli_stmt_close($stmt);

    return (int) ($row[0] ?? 0);
}

$totalCount =
    getOfficialRequisitionCount(
        $conn,
        $admNo
    );

$pendingCount =
    getOfficialRequisitionCount(
        $conn,
        $admNo,
        'Pending'
    );

$approvedCount =
    getOfficialRequisitionCount(
        $conn,
        $admNo,
        'Approved'
    );

$rejectedCount =
    getOfficialRequisitionCount(
        $conn,
        $admNo,
        'Rejected'
    );

$latestSql = "
    SELECT
        r.requisitionID,
        r.requisitionNumber,
        r.quantityRequested,
        r.startDate,
        r.endDate,
        r.requestTime,
        r.status,

        rs.resourceName,
        rs.resourceCategory

    FROM requisitions r

    INNER JOIN resources rs
        ON r.resourceID = rs.resourceID

    WHERE r.submittedByAdmNo = ?

    ORDER BY r.requestTime DESC

    LIMIT 5
";

$latestStmt =
    mysqli_prepare(
        $conn,
        $latestSql
    );

mysqli_stmt_bind_param(
    $latestStmt,
    's',
    $admNo
);

mysqli_stmt_execute(
    $latestStmt
);

$latestResult =
    mysqli_stmt_get_result(
        $latestStmt
    );

require_once '../includes/header.php';
?>

<div class="page-header">

    <div>
        <h1>Dashboard</h1>

        <!-- <p>
            Submit and monitor your club resource requests.
        </p> -->
    </div>

    <a
        href="create_requisition.php"
        class="btn btn-primary"
    >
        New Requisition
    </a>

</div>

<div class="dashboard-welcome">

    <div>

        <span class="dashboard-welcome-label">
            Club official workspace
        </span>

        <!-- <h2>
            Welcome,
            <?= htmlspecialchars(
                $official['officialName']
            ); ?>
        </h2> -->

        <!-- <p>
            Submit resource requests and follow each approval
            stage from your dashboard.
        </p> -->

    </div>

</div>

<div class="dashboard-profile-strip">

    <div class="dashboard-profile-main">

        <div class="dashboard-profile-avatar">
            <?= htmlspecialchars(
                strtoupper(
                    substr(
                        $official['officialName'],
                        0,
                        1
                    )
                )
            ); ?>
        </div>

        <div>

            <h2>
                <?= htmlspecialchars(
                    $official['officialName']
                ); ?>
            </h2>

            <p>
                <?= htmlspecialchars(
                    $official['position']
                ); ?>
            </p>

        </div>

    </div>

    <div class="dashboard-profile-details">

        <div class="dashboard-detail-item">

            <span>Admission Number</span>

            <strong>
                <?= htmlspecialchars(
                    $official['admNo']
                ); ?>
            </strong>

        </div>

        <div class="dashboard-detail-item">

            <span>Club</span>

            <strong>
                <?= htmlspecialchars(
                    $official['clubName']
                ); ?>
            </strong>

        </div>

        <div class="dashboard-detail-item">

            <span>Position</span>

            <strong>
                <?= htmlspecialchars(
                    $official['position']
                ); ?>
            </strong>

        </div>

        <div class="dashboard-detail-item">

            <span>Account Status</span>

            <strong>

                <span
                    class="badge <?= $official[
                        'accountStatus'
                    ] === 'Active'
                        ? 'badge-success'
                        : 'badge-danger'; ?>"
                >
                    <?= htmlspecialchars(
                        $official[
                            'accountStatus'
                        ]
                    ); ?>
                </span>

            </strong>

        </div>

    </div>

</div>

<div class="dashboard-stat-grid dashboard-stat-grid-four">

    <a
        href="my_requisitions.php"
        class="dashboard-stat-tile"
    >

        <div class="dashboard-stat-icon">
            T
        </div>

        <div class="dashboard-stat-content">

            <span class="dashboard-stat-label">
                Total Requests
            </span>

            <strong class="dashboard-stat-value">
                <?= $totalCount; ?>
            </strong>

            <small>
                All requisitions
            </small>

        </div>

    </a>

    <a
        href="my_requisitions.php?status=Pending"
        class="dashboard-stat-tile"
    >

        <div class="dashboard-stat-icon dashboard-icon-warning">
            P
        </div>

        <div class="dashboard-stat-content">

            <span class="dashboard-stat-label">
                Pending
            </span>

            <strong class="dashboard-stat-value">
                <?= $pendingCount; ?>
            </strong>

            <small>
                Under approval
            </small>

        </div>

    </a>

    <a
        href="my_requisitions.php?status=Approved"
        class="dashboard-stat-tile"
    >

        <div class="dashboard-stat-icon dashboard-icon-success">
            A
        </div>

        <div class="dashboard-stat-content">

            <span class="dashboard-stat-label">
                Approved
            </span>

            <strong class="dashboard-stat-value">
                <?= $approvedCount; ?>
            </strong>

            <small>
                Successfully approved
            </small>

        </div>

    </a>

    <a
        href="my_requisitions.php?status=Rejected"
        class="dashboard-stat-tile"
    >

        <div class="dashboard-stat-icon dashboard-icon-danger">
            R
        </div>

        <div class="dashboard-stat-content">

            <span class="dashboard-stat-label">
                Rejected
            </span>

            <strong class="dashboard-stat-value">
                <?= $rejectedCount; ?>
            </strong>

            <small>
                Declined requests
            </small>

        </div>

    </a>

</div>

<div class="card dashboard-table-card">

    <div class="section-header-row">

        <div class="section-header">

            <h2>Recent Requisitions</h2>

            <p>
                Your most recently submitted requests.
            </p>

        </div>

        <a
            href="my_requisitions.php"
            class="btn btn-secondary btn-small"
        >
            View All
        </a>

    </div>

    <div class="table-responsive">

        <table class="data-table dashboard-data-table">

            <thead>

                <tr>
                    <th>Requisition</th>
                    <th>Resource</th>
                    <th>Quantity</th>
                    <th>Required Period</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th class="actions-heading">
                        Action
                    </th>
                </tr>

            </thead>

            <tbody>

                <?php if (
                    mysqli_num_rows(
                        $latestResult
                    ) > 0
                ): ?>

                    <?php while (
                        $requisition =
                            mysqli_fetch_assoc(
                                $latestResult
                            )
                    ): ?>

                        <?php
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

                                <strong>
                                    <?= htmlspecialchars(
                                        $requisition[
                                            'requisitionNumber'
                                        ]
                                    ); ?>
                                </strong>

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
                                <?= date(
                                    'd M Y',
                                    strtotime(
                                        $requisition[
                                            'startDate'
                                        ]
                                    )
                                ); ?>

                                <span class="table-date-separator">
                                    –
                                </span>

                                <?= date(
                                    'd M Y',
                                    strtotime(
                                        $requisition[
                                            'endDate'
                                        ]
                                    )
                                ); ?>
                            </td>

                            <td>
                                <?= date(
                                    'd M Y, H:i',
                                    strtotime(
                                        $requisition[
                                            'requestTime'
                                        ]
                                    )
                                ); ?>
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

                                <div class="table-actions">

                                    <a
                                        href="my_requisitions.php?id=<?= (int) $requisition[
                                            'requisitionID'
                                        ]; ?>"
                                        class="btn btn-secondary btn-small"
                                    >
                                        View
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="7"
                            class="empty-state"
                        >
                            You have not submitted any
                            requisitions yet.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php

mysqli_stmt_close(
    $latestStmt
);

require_once '../includes/footer.php';

?>