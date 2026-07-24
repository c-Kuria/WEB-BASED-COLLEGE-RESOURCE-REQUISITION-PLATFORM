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

/*
|--------------------------------------------------------------------------
| Get the logged-in user's ID
|--------------------------------------------------------------------------
*/

$userID = (int) ($_SESSION['userID'] ?? 0);

if ($userID <= 0) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Retrieve the club official profile
|--------------------------------------------------------------------------
*/

$officialSql = "
    SELECT
        co.admNo,
        co.officialName,
        co.position,
        co.email,
        co.phone,
        co.clubNumber,
        co.isChair,
        c.clubName,
        c.clubDescription,
        u.username,
        u.status AS accountStatus
    FROM club_officials co

    INNER JOIN clubs c
        ON co.clubNumber = c.clubNumber

    INNER JOIN users u
        ON co.userID = u.userID

    WHERE co.userID = ?
    LIMIT 1
";

$officialStmt = mysqli_prepare(
    $conn,
    $officialSql
);

if (!$officialStmt) {
    die(
        'Unable to prepare official profile query: ' .
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
| Dashboard totals
|--------------------------------------------------------------------------
*/

$totalSql = "
    SELECT
        COUNT(*) AS totalRequisitions,

        SUM(
            CASE
                WHEN status = 'Pending'
                THEN 1
                ELSE 0
            END
        ) AS pendingRequisitions,

        SUM(
            CASE
                WHEN status = 'Approved'
                THEN 1
                ELSE 0
            END
        ) AS approvedRequisitions,

        SUM(
            CASE
                WHEN status = 'Rejected'
                THEN 1
                ELSE 0
            END
        ) AS rejectedRequisitions,

        SUM(
            CASE
                WHEN status = 'Cancelled'
                THEN 1
                ELSE 0
            END
        ) AS cancelledRequisitions

    FROM requisitions
    WHERE submittedByAdmNo = ?
";

$totalStmt = mysqli_prepare(
    $conn,
    $totalSql
);

if (!$totalStmt) {
    die(
        'Unable to prepare requisition statistics: ' .
        mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $totalStmt,
    's',
    $admNo
);

mysqli_stmt_execute($totalStmt);

$totalResult =
    mysqli_stmt_get_result($totalStmt);

$totals =
    mysqli_fetch_assoc($totalResult);

mysqli_stmt_close($totalStmt);

$totalRequisitions =
    (int) ($totals['totalRequisitions'] ?? 0);

$pendingRequisitions =
    (int) ($totals['pendingRequisitions'] ?? 0);

$approvedRequisitions =
    (int) ($totals['approvedRequisitions'] ?? 0);

$rejectedRequisitions =
    (int) ($totals['rejectedRequisitions'] ?? 0);

$cancelledRequisitions =
    (int) ($totals['cancelledRequisitions'] ?? 0);

/*
|--------------------------------------------------------------------------
| Unread notification count
|--------------------------------------------------------------------------
*/

$notificationSql = "
    SELECT COUNT(*) AS total
    FROM notifications
    WHERE recipientAdmNo = ?
      AND isRead = 'No'
";

$notificationStmt = mysqli_prepare(
    $conn,
    $notificationSql
);

if (!$notificationStmt) {
    die(
        'Unable to prepare notification count: ' .
        mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $notificationStmt,
    's',
    $admNo
);

mysqli_stmt_execute($notificationStmt);

$notificationResult =
    mysqli_stmt_get_result($notificationStmt);

$notificationRow =
    mysqli_fetch_assoc($notificationResult);

$unreadNotifications =
    (int) ($notificationRow['total'] ?? 0);

mysqli_stmt_close($notificationStmt);

/*
|--------------------------------------------------------------------------
| Recent requisitions
|--------------------------------------------------------------------------
*/

$recentSql = "
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
        res.resourceCategory
    FROM requisitions r

    INNER JOIN resources res
        ON r.resourceID = res.resourceID

    WHERE r.submittedByAdmNo = ?

    ORDER BY r.requestTime DESC

    LIMIT 5
";

$recentStmt = mysqli_prepare(
    $conn,
    $recentSql
);

if (!$recentStmt) {
    die(
        'Unable to prepare recent requisitions query: ' .
        mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $recentStmt,
    's',
    $admNo
);

mysqli_stmt_execute($recentStmt);

$recentResult =
    mysqli_stmt_get_result($recentStmt);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>Club Official Dashboard</h1>

            <p>
                Welcome,
                <?= htmlspecialchars(
                    $official['officialName']
                ); ?>.
                Manage your club's resource requisitions.
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

    <!-- Club information -->

    <div class="card official-club-card">

        <div class="section-header">
            <h2>
                <?= htmlspecialchars(
                    $official['clubName']
                ); ?>
            </h2>

            <p>
                <?= htmlspecialchars(
                    $official['clubDescription'] ??
                    'No club description available.'
                ); ?>
            </p>
        </div>

        <div class="official-details-grid">

            <div>
                <strong>Admission Number</strong>

                <p>
                    <?= htmlspecialchars(
                        $official['admNo']
                    ); ?>
                </p>
            </div>

            <div>
                <strong>Position</strong>

                <p>
                    <?= htmlspecialchars(
                        $official['position']
                    ); ?>
                </p>
            </div>

            <div>
                <strong>Chairperson</strong>

                <p>
                    <?= htmlspecialchars(
                        $official['isChair']
                    ); ?>
                </p>
            </div>

            <div>
                <strong>Club Number</strong>

                <p>
                    <?= (int) $official['clubNumber']; ?>
                </p>
            </div>

        </div>

    </div>

    <!-- Dashboard cards -->

    <div class="stats-grid">

        <div class="stat-card">

            <h3>Total Requisitions</h3>

            <div class="stat-number">
                <?= $totalRequisitions; ?>
            </div>

        </div>

        <div class="stat-card">

            <h3>Pending</h3>

            <div class="stat-number">
                <?= $pendingRequisitions; ?>
            </div>

        </div>

        <div class="stat-card">

            <h3>Approved</h3>

            <div class="stat-number">
                <?= $approvedRequisitions; ?>
            </div>

        </div>

        <div class="stat-card">

            <h3>Rejected</h3>

            <div class="stat-number">
                <?= $rejectedRequisitions; ?>
            </div>

        </div>

        <div class="stat-card">

            <h3>Cancelled</h3>

            <div class="stat-number">
                <?= $cancelledRequisitions; ?>
            </div>

        </div>

        <div class="stat-card">

            <h3>Unread Notifications</h3>

            <div class="stat-number">
                <?= $unreadNotifications; ?>
            </div>

            <a
                href="notifications.php"
                class="card-link"
            >
                View notifications
            </a>

        </div>

    </div>

    <!-- Recent requisitions -->

    <div class="card">

        <div class="section-header section-header-row">

            <div>
                <h2>Recent Requisitions</h2>

                <p>
                    Your five most recently submitted requests.
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

            <table class="data-table">

                <thead>
                    <tr>
                        <th>Requisition</th>
                        <th>Resource</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Required Dates</th>
                        <th>Submitted</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (
                        mysqli_num_rows($recentResult) > 0
                    ): ?>

                        <?php while (
                            $row = mysqli_fetch_assoc(
                                $recentResult
                            )
                        ): ?>

                            <?php
                            $statusClass =
                                'badge-secondary';

                            if (
                                $row['status'] === 'Approved'
                            ) {
                                $statusClass =
                                    'badge-success';
                            } elseif (
                                $row['status'] === 'Rejected'
                            ) {
                                $statusClass =
                                    'badge-danger';
                            } elseif (
                                $row['status'] === 'Pending'
                            ) {
                                $statusClass =
                                    'badge-warning';
                            }
                            ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?php
                                        if (
                                            !empty(
                                                $row[
                                                    'requisitionNumber'
                                                ]
                                            )
                                        ) {
                                            echo htmlspecialchars(
                                                $row[
                                                    'requisitionNumber'
                                                ]
                                            );
                                        } else {
                                            echo 'RQ-' .
                                                str_pad(
                                                    $row[
                                                        'requisitionID'
                                                    ],
                                                    4,
                                                    '0',
                                                    STR_PAD_LEFT
                                                );
                                        }
                                        ?>
                                    </strong>

                                    <?php if (
                                        !empty($row['purpose'])
                                    ): ?>

                                        <br>

                                        <small class="text-muted">
                                            <?= htmlspecialchars(
                                                $row['purpose']
                                            ); ?>
                                        </small>

                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['resourceName']
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row[
                                            'resourceCategory'
                                        ]
                                    ); ?>
                                </td>

                                <td>
                                    <?= (int) $row[
                                        'quantityRequested'
                                    ]; ?>
                                </td>

                                <td>
                                    <?= date(
                                        'd M Y',
                                        strtotime(
                                            $row['startDate']
                                        )
                                    ); ?>

                                    <br>

                                    <small class="text-muted">
                                        to
                                        <?= date(
                                            'd M Y',
                                            strtotime(
                                                $row['endDate']
                                            )
                                        ); ?>
                                    </small>
                                </td>

                                <td>
                                    <?= date(
                                        'd M Y H:i',
                                        strtotime(
                                            $row['requestTime']
                                        )
                                    ); ?>
                                </td>

                                <td>
                                    <span
                                        class="badge <?= $statusClass; ?>"
                                    >
                                        <?= htmlspecialchars(
                                            $row['status']
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
                                You have not submitted any
                                requisitions yet.

                                <br><br>

                                <a
                                    href="create_requisition.php"
                                    class="btn btn-primary"
                                >
                                    Create First Requisition
                                </a>
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php

mysqli_stmt_close($recentStmt);

require_once '../includes/footer.php';

?>