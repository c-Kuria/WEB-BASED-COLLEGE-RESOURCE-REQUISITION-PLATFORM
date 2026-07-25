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

$pageTitle = 'Admin Dashboard';

function getDashboardCount(
    mysqli $conn,
    string $sql
): int {

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_row($result);

    return (int) ($row[0] ?? 0);
}

$clubCount = getDashboardCount(
    $conn,
    "SELECT COUNT(*) FROM clubs"
);

$officialCount = getDashboardCount(
    $conn,
    "SELECT COUNT(*) FROM club_officials"
);

$officerCount = getDashboardCount(
    $conn,
    "SELECT COUNT(*) FROM officers"
);

$resourceCount = getDashboardCount(
    $conn,
    "SELECT COUNT(*) FROM resources"
);

$pendingCount = getDashboardCount(
    $conn,
    "
        SELECT COUNT(*)
        FROM requisitions
        WHERE status = 'Pending'
    "
);

$approvedCount = getDashboardCount(
    $conn,
    "
        SELECT COUNT(*)
        FROM requisitions
        WHERE status = 'Approved'
    "
);

$rejectedCount = getDashboardCount(
    $conn,
    "
        SELECT COUNT(*)
        FROM requisitions
        WHERE status = 'Rejected'
    "
);

$latestSql = "
    SELECT
        r.requisitionID,
        r.requisitionNumber,
        r.requestTime,
        r.status,
        r.quantityRequested,

        rs.resourceName,
        rs.resourceCategory,

        co.officialName,
        c.clubName

    FROM requisitions r

    INNER JOIN resources rs
        ON r.resourceID = rs.resourceID

    INNER JOIN club_officials co
        ON r.submittedByAdmNo = co.admNo

    INNER JOIN clubs c
        ON co.clubNumber = c.clubNumber

    ORDER BY r.requestTime DESC

    LIMIT 6
";

$latestResult = mysqli_query(
    $conn,
    $latestSql
);

require_once '../includes/header.php';
?>

<div class="page-header">

    <div>
        <h1>Dashboard</h1>

        <p>
            Monitor resources, users and requisition activity.
        </p>
    </div>

</div>

<div class="dashboard-welcome">

    <div>

        <span class="dashboard-welcome-label">
            Administration overview
        </span>

        <!-- <h2>
            Welcome back,
            <?= htmlspecialchars(
                $_SESSION['username'] ?? 'Administrator'
            ); ?>
        </h2> -->

        <!-- <p>
            Manage institutional resources and monitor
            requisition approvals from one place.
        </p> -->

    </div>

</div>

<div class="dashboard-stat-grid dashboard-stat-grid-admin">

    <a
        href="clubs.php"
        class="dashboard-stat-tile"
    >

        <div class="dashboard-stat-icon">
            C
        </div>

        <div class="dashboard-stat-content">

            <span class="dashboard-stat-label">
                Clubs
            </span>

            <strong class="dashboard-stat-value">
                <?= $clubCount; ?>
            </strong>

            <small>
                Registered clubs
            </small>

        </div>

    </a>

    <a
        href="officials.php"
        class="dashboard-stat-tile"
    >

        <div class="dashboard-stat-icon">
            CO
        </div>

        <div class="dashboard-stat-content">

            <span class="dashboard-stat-label">
                Club Officials
            </span>

            <strong class="dashboard-stat-value">
                <?= $officialCount; ?>
            </strong>

            <small>
                Registered officials
            </small>

        </div>

    </a>

    <a
        href="officers.php"
        class="dashboard-stat-tile"
    >

        <div class="dashboard-stat-icon">
            O
        </div>

        <div class="dashboard-stat-content">

            <span class="dashboard-stat-label">
                Officers
            </span>

            <strong class="dashboard-stat-value">
                <?= $officerCount; ?>
            </strong>

            <small>
                Approval officers
            </small>

        </div>

    </a>

    <a
        href="resources.php"
        class="dashboard-stat-tile"
    >

        <div class="dashboard-stat-icon">
            R
        </div>

        <div class="dashboard-stat-content">

            <span class="dashboard-stat-label">
                Resources
            </span>

            <strong class="dashboard-stat-value">
                <?= $resourceCount; ?>
            </strong>

            <small>
                Managed resources
            </small>

        </div>

    </a>

    <a
        href="reports.php?status=Pending"
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
                Awaiting approval
            </small>

        </div>

    </a>

    <a
        href="reports.php?status=Approved"
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
                Completed approvals
            </small>

        </div>

    </a>

    <a
        href="reports.php?status=Rejected"
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

            <h2>Latest Requisitions</h2>

            <p>
                Most recently submitted resource requests.
            </p>

        </div>

        <a
            href="reports.php"
            class="btn btn-secondary btn-small"
        >
            View Reports
        </a>

    </div>

    <div class="table-responsive">

        <table class="data-table dashboard-data-table">

            <thead>

                <tr>
                    <th>Requisition</th>
                    <th>Official</th>
                    <th>Club</th>
                    <th>Resource</th>
                    <th>Quantity</th>
                    <th>Submitted</th>
                    <th>Status</th>
                </tr>

            </thead>

            <tbody>

                <?php if (
                    $latestResult &&
                    mysqli_num_rows($latestResult) > 0
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
                                <?= htmlspecialchars(
                                    $requisition[
                                        'officialName'
                                    ]
                                ); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $requisition[
                                        'clubName'
                                    ]
                                ); ?>
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

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td
                            colspan="7"
                            class="empty-state"
                        >
                            No requisitions have been
                            submitted yet.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php
require_once '../includes/footer.php';
?>