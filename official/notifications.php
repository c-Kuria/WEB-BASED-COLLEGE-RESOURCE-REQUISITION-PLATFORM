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

$pageTitle = 'Notifications';

/*
|--------------------------------------------------------------------------
| Get official profile
|--------------------------------------------------------------------------
*/

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
    die('Official profile not found.');
}

$admNo =
    $official['admNo'];

/*
|--------------------------------------------------------------------------
| Validate notification filter
|--------------------------------------------------------------------------
*/

$filter =
    strtolower(
        trim($_GET['filter'] ?? 'all')
    );

$allowedFilters = [
    'all',
    'unread',
    'read'
];

if (
    !in_array(
        $filter,
        $allowedFilters,
        true
    )
) {
    $filter = 'all';
}

/*
|--------------------------------------------------------------------------
| Get notification counts
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT
        COUNT(*) AS totalNotifications,

        SUM(
            CASE
                WHEN isRead = 'No'
                THEN 1
                ELSE 0
            END
        ) AS unreadNotifications,

        SUM(
            CASE
                WHEN isRead = 'Yes'
                THEN 1
                ELSE 0
            END
        ) AS readNotifications

    FROM notifications

    WHERE recipientAdmNo = ?
";

$countStmt =
    mysqli_prepare(
        $conn,
        $countSql
    );

mysqli_stmt_bind_param(
    $countStmt,
    's',
    $admNo
);

mysqli_stmt_execute(
    $countStmt
);

$countResult =
    mysqli_stmt_get_result(
        $countStmt
    );

$notificationCounts =
    mysqli_fetch_assoc(
        $countResult
    );

mysqli_stmt_close(
    $countStmt
);

$totalNotifications =
    (int) (
        $notificationCounts[
            'totalNotifications'
        ] ?? 0
    );

$unreadNotifications =
    (int) (
        $notificationCounts[
            'unreadNotifications'
        ] ?? 0
    );

$readNotifications =
    (int) (
        $notificationCounts[
            'readNotifications'
        ] ?? 0
    );

/*
|--------------------------------------------------------------------------
| Get notifications
|--------------------------------------------------------------------------
*/

$notificationSql = "
    SELECT
        n.notifID,
        n.approvalNumber,
        n.requisitionID,
        n.notifDescription,
        n.isRead,
        n.createdAt,

        r.requisitionNumber,
        r.status AS requisitionStatus,

        rs.resourceName,
        rs.resourceCategory,

        a.status AS approvalStatus,
        a.approvalOrder,
        a.actedBy,

        actingOfficer.officerName
            AS actingOfficerName,

        actingOfficer.officerRole
            AS actingOfficerRole

    FROM notifications n

    INNER JOIN requisitions r
        ON n.requisitionID =
           r.requisitionID

    INNER JOIN resources rs
        ON r.resourceID =
           rs.resourceID

    LEFT JOIN approvals a
        ON n.approvalNumber =
           a.approvalNumber

    LEFT JOIN officers actingOfficer
        ON a.actedBy =
           actingOfficer.officerStaffNo

    WHERE n.recipientAdmNo = ?
";

if ($filter === 'unread') {

    $notificationSql .= "
        AND n.isRead = 'No'
    ";

} elseif ($filter === 'read') {

    $notificationSql .= "
        AND n.isRead = 'Yes'
    ";
}

$notificationSql .= "
    ORDER BY n.createdAt DESC
";

$notificationStmt =
    mysqli_prepare(
        $conn,
        $notificationSql
    );

if (!$notificationStmt) {
    die('Unable to prepare notifications query.');
}

mysqli_stmt_bind_param(
    $notificationStmt,
    's',
    $admNo
);

mysqli_stmt_execute(
    $notificationStmt
);

$notificationResult =
    mysqli_stmt_get_result(
        $notificationStmt
    );

require_once '../includes/header.php';
?>

<div class="page-header">

    <div>

        <h1>Notifications</h1>

        <p>
            View updates about your submitted requisitions.
        </p>

    </div>

    <?php if ($unreadNotifications > 0): ?>

        <form
            method="POST"
            action="mark_all_notifications_read.php"
        >

            <button
                type="submit"
                class="btn btn-secondary"
            >
                Mark All as Read
            </button>

        </form>

    <?php endif; ?>

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

<div class="notification-summary-grid">

    <a
        href="notifications.php?filter=all"
        class="notification-summary-card <?= $filter ===
        'all'
            ? 'active'
            : ''; ?>"
    >

        <div class="notification-summary-icon">
            A
        </div>

        <div>

            <span>All Notifications</span>

            <strong>
                <?= $totalNotifications; ?>
            </strong>

        </div>

    </a>

    <a
        href="notifications.php?filter=unread"
        class="notification-summary-card notification-summary-unread <?= $filter ===
        'unread'
            ? 'active'
            : ''; ?>"
    >

        <div class="notification-summary-icon">
            U
        </div>

        <div>

            <span>Unread</span>

            <strong>
                <?= $unreadNotifications; ?>
            </strong>

        </div>

    </a>

    <a
        href="notifications.php?filter=read"
        class="notification-summary-card notification-summary-read <?= $filter ===
        'read'
            ? 'active'
            : ''; ?>"
    >

        <div class="notification-summary-icon">
            R
        </div>

        <div>

            <span>Read</span>

            <strong>
                <?= $readNotifications; ?>
            </strong>

        </div>

    </a>

</div>

<div class="notification-toolbar">

    <div>

        <h2>

            <?php if ($filter === 'unread'): ?>

                Unread Notifications

            <?php elseif ($filter === 'read'): ?>

                Read Notifications

            <?php else: ?>

                All Notifications

            <?php endif; ?>

        </h2>

        <p>

            <?php if ($filter === 'unread'): ?>

                Notifications that still require your attention.

            <?php elseif ($filter === 'read'): ?>

                Notifications you have already reviewed.

            <?php else: ?>

                All updates related to your requisitions.

            <?php endif; ?>

        </p>

    </div>

    <div class="notification-filter-buttons">

        <a
            href="notifications.php?filter=all"
            class="filter-button <?= $filter ===
            'all'
                ? 'active'
                : ''; ?>"
        >
            All
        </a>

        <a
            href="notifications.php?filter=unread"
            class="filter-button <?= $filter ===
            'unread'
                ? 'active'
                : ''; ?>"
        >
            Unread
        </a>

        <a
            href="notifications.php?filter=read"
            class="filter-button <?= $filter ===
            'read'
                ? 'active'
                : ''; ?>"
        >
            Read
        </a>

    </div>

</div>

<div class="notification-list">

    <?php if (
        mysqli_num_rows(
            $notificationResult
        ) > 0
    ): ?>

        <?php while (
            $notification =
                mysqli_fetch_assoc(
                    $notificationResult
                )
        ): ?>

            <?php

            $isUnread =
                $notification['isRead'] === 'No';

            $status =
                $notification[
                    'requisitionStatus'
                ];

            $statusClass =
                match ($status) {

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

            $notificationTypeClass =
                match ($status) {

                    'Approved' =>
                        'notification-success',

                    'Rejected' =>
                        'notification-danger',

                    'Cancelled' =>
                        'notification-neutral',

                    default =>
                        'notification-pending'
                };

            ?>

            <article
                class="notification-card <?= $isUnread
                    ? 'unread'
                    : 'read'; ?> <?= $notificationTypeClass; ?>"
            >

                <div class="notification-card-marker">

                    <?php if ($status === 'Approved'): ?>

                        ✓

                    <?php elseif ($status === 'Rejected'): ?>

                        !

                    <?php elseif ($status === 'Cancelled'): ?>

                        ×

                    <?php else: ?>

                        i

                    <?php endif; ?>

                </div>

                <div class="notification-card-body">

                    <div class="notification-card-header">

                        <div>

                            <div class="notification-title-row">

                                <h3>
                                    <?= htmlspecialchars(
                                        $notification[
                                            'requisitionNumber'
                                        ]
                                    ); ?>
                                </h3>

                                <?php if ($isUnread): ?>

                                    <span
                                        class="notification-unread-dot"
                                        title="Unread notification"
                                    ></span>

                                <?php endif; ?>

                            </div>

                            <div class="notification-meta-row">

                                <span>
                                    <?= htmlspecialchars(
                                        $notification[
                                            'resourceName'
                                        ]
                                    ); ?>
                                </span>

                                <span class="notification-meta-separator">
                                    •
                                </span>

                                <span>
                                    <?= htmlspecialchars(
                                        $notification[
                                            'resourceCategory'
                                        ]
                                    ); ?>
                                </span>

                                <span class="notification-meta-separator">
                                    •
                                </span>

                                <span>
                                    <?= date(
                                        'd M Y, H:i',
                                        strtotime(
                                            $notification[
                                                'createdAt'
                                            ]
                                        )
                                    ); ?>
                                </span>

                            </div>

                        </div>

                        <span
                            class="badge <?= $statusClass; ?>"
                        >
                            <?= htmlspecialchars(
                                $status
                            ); ?>
                        </span>

                    </div>

                    <p class="notification-message">
                        <?= htmlspecialchars(
                            $notification[
                                'notifDescription'
                            ]
                        ); ?>
                    </p>

                    <?php if (
                        !empty(
                            $notification[
                                'actingOfficerName'
                            ]
                        )
                    ): ?>

                        <div class="notification-officer">

                            <span>Performed by</span>

                            <strong>
                                <?= htmlspecialchars(
                                    $notification[
                                        'actingOfficerName'
                                    ]
                                ); ?>
                            </strong>

                            <?php if (
                                !empty(
                                    $notification[
                                        'actingOfficerRole'
                                    ]
                                )
                            ): ?>

                                <small>
                                    <?= htmlspecialchars(
                                        $notification[
                                            'actingOfficerRole'
                                        ]
                                    ); ?>
                                </small>

                            <?php endif; ?>

                        </div>

                    <?php endif; ?>

                    <div class="notification-card-actions">

                        <div class="notification-card-actions-left">

                            <a
                                href="view_requisition.php?id=<?= (int) $notification[
                                    'requisitionID'
                                ]; ?>"
                                class="btn btn-secondary btn-small"
                            >
                                View Requisition
                            </a>

                        </div>

                        <?php if ($isUnread): ?>

                            <div class="notification-card-actions-right">

                                <form
                                    method="POST"
                                    action="mark_notification_read.php"
                                >

                                    <input
                                        type="hidden"
                                        name="notifID"
                                        value="<?= (int) $notification[
                                            'notifID'
                                        ]; ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-primary btn-small"
                                    >
                                        Mark as Read
                                    </button>

                                </form>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </article>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="card notification-empty-state">

            <div class="notification-empty-icon">
                ✓
            </div>

            <h2>No notifications found</h2>

            <p>

                <?php if ($filter === 'unread'): ?>

                    You have no unread notifications.

                <?php elseif ($filter === 'read'): ?>

                    You have no read notifications.

                <?php else: ?>

                    You have not received any notifications yet.

                <?php endif; ?>

            </p>

        </div>

    <?php endif; ?>

</div>

<?php

mysqli_stmt_close(
    $notificationStmt
);

require_once '../includes/footer.php';

?>