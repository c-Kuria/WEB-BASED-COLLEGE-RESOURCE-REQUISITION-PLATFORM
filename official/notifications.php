<?php

require_once '../includes/session.php';
require_once '../config/db.php';

/*
|--------------------------------------------------------------------------
| Protect page
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
| Retrieve logged-in official
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
| Filter
|--------------------------------------------------------------------------
*/

$filter = trim(
    $_GET['filter'] ?? 'all'
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
| Notification counts
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT
        COUNT(*) AS total,

        SUM(
            CASE
                WHEN isRead = 'No'
                THEN 1
                ELSE 0
            END
        ) AS unread,

        SUM(
            CASE
                WHEN isRead = 'Yes'
                THEN 1
                ELSE 0
            END
        ) AS readCount

    FROM notifications

    WHERE recipientAdmNo = ?
";

$countStmt = mysqli_prepare(
    $conn,
    $countSql
);

if (!$countStmt) {
    die(
        'Unable to prepare notification totals: ' .
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

$countRow =
    mysqli_fetch_assoc($countResult);

mysqli_stmt_close($countStmt);

$totalNotifications =
    (int) ($countRow['total'] ?? 0);

$unreadNotifications =
    (int) ($countRow['unread'] ?? 0);

$readNotifications =
    (int) ($countRow['readCount'] ?? 0);

/*
|--------------------------------------------------------------------------
| Retrieve notifications
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

        res.resourceName,

        a.status AS approvalStatus,
        a.comments AS approvalComments,
        a.approvalTime,

        o.officerName,
        o.officerRole

    FROM notifications n

    INNER JOIN requisitions r
        ON n.requisitionID = r.requisitionID

    INNER JOIN resources res
        ON r.resourceID = res.resourceID

    LEFT JOIN approvals a
        ON n.approvalNumber = a.approvalNumber

    LEFT JOIN officers o
        ON a.officerStaffNo = o.officerStaffNo

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

$notificationStmt = mysqli_prepare(
    $conn,
    $notificationSql
);

if (!$notificationStmt) {
    die(
        'Unable to prepare notifications query: ' .
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

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>Notifications</h1>

            <p>
                View updates for requisitions submitted for
                <?= htmlspecialchars(
                    $official['clubName']
                ); ?>.
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

    <!-- Notification totals -->

    <div class="stats-grid">

        <a
            href="notifications.php?filter=all"
            class="stat-card notification-stat-link"
        >
            <h3>All Notifications</h3>

            <div class="stat-number">
                <?= $totalNotifications; ?>
            </div>
        </a>

        <a
            href="notifications.php?filter=unread"
            class="stat-card notification-stat-link"
        >
            <h3>Unread</h3>

            <div class="stat-number">
                <?= $unreadNotifications; ?>
            </div>
        </a>

        <a
            href="notifications.php?filter=read"
            class="stat-card notification-stat-link"
        >
            <h3>Read</h3>

            <div class="stat-number">
                <?= $readNotifications; ?>
            </div>
        </a>

    </div>

    <!-- Filter tabs -->

    <div class="notification-tabs">

        <a
            href="notifications.php?filter=all"
            class="<?= $filter === 'all'
                ? 'active'
                : ''; ?>"
        >
            All
        </a>

        <a
            href="notifications.php?filter=unread"
            class="<?= $filter === 'unread'
                ? 'active'
                : ''; ?>"
        >
            Unread
        </a>

        <a
            href="notifications.php?filter=read"
            class="<?= $filter === 'read'
                ? 'active'
                : ''; ?>"
        >
            Read
        </a>

    </div>

    <!-- Notifications list -->

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

                $requisitionNumber =
                    !empty(
                        $notification[
                            'requisitionNumber'
                        ]
                    )
                        ? $notification[
                            'requisitionNumber'
                        ]
                        : 'REQ-' .
                          str_pad(
                              $notification[
                                  'requisitionID'
                              ],
                              4,
                              '0',
                              STR_PAD_LEFT
                          );

                $badgeClass =
                    'badge-secondary';

                if (
                    $notification[
                        'requisitionStatus'
                    ] === 'Pending'
                ) {
                    $badgeClass =
                        'badge-warning';

                } elseif (
                    $notification[
                        'requisitionStatus'
                    ] === 'Approved'
                ) {
                    $badgeClass =
                        'badge-success';

                } elseif (
                    $notification[
                        'requisitionStatus'
                    ] === 'Rejected'
                ) {
                    $badgeClass =
                        'badge-danger';
                }

                ?>

                <div
                    class="notification-card <?= $isUnread
                        ? 'notification-unread'
                        : ''; ?>"
                >

                    <div class="notification-indicator">

                        <?php if ($isUnread): ?>

                            <span
                                class="unread-dot"
                                title="Unread"
                            ></span>

                        <?php endif; ?>

                    </div>

                    <div class="notification-content">

                        <div class="notification-header">

                            <div>

                                <h3>
                                    <?= htmlspecialchars(
                                        $requisitionNumber
                                    ); ?>
                                </h3>

                                <p class="text-muted">
                                    <?= htmlspecialchars(
                                        $notification[
                                            'resourceName'
                                        ]
                                    ); ?>
                                </p>

                            </div>

                            <span
                                class="badge <?= $badgeClass; ?>"
                            >
                                <?= htmlspecialchars(
                                    $notification[
                                        'requisitionStatus'
                                    ]
                                ); ?>
                            </span>

                        </div>

                        <p class="notification-message">
                            <?= nl2br(
                                htmlspecialchars(
                                    $notification[
                                        'notifDescription'
                                    ]
                                )
                            ); ?>
                        </p>

                        <?php if (
                            !empty(
                                $notification[
                                    'officerName'
                                ]
                            )
                        ): ?>

                            <div class="notification-officer">

                                <strong>
                                    Officer
                                </strong>

                                <p>
                                    <?= htmlspecialchars(
                                        $notification[
                                            'officerName'
                                        ]
                                    ); ?>
                                    —
                                    <?= htmlspecialchars(
                                        $notification[
                                            'officerRole'
                                        ]
                                    ); ?>
                                </p>

                            </div>

                        <?php endif; ?>

                        <?php if (
                            !empty(
                                $notification[
                                    'approvalComments'
                                ]
                            )
                        ): ?>

                            <div class="notification-comments">

                                <strong>
                                    Approval Comments
                                </strong>

                                <p>
                                    <?= nl2br(
                                        htmlspecialchars(
                                            $notification[
                                                'approvalComments'
                                            ]
                                        )
                                    ); ?>
                                </p>

                            </div>

                        <?php endif; ?>

                        <div class="notification-footer">

                            <small class="text-muted">
                                <?= date(
                                    'd M Y, H:i',
                                    strtotime(
                                        $notification[
                                            'createdAt'
                                        ]
                                    )
                                ); ?>
                            </small>

                            <div class="notification-actions">

                                <a
                                    href="my_requisitions.php"
                                    class="btn btn-secondary btn-small"
                                >
                                    View Requisition
                                </a>

                                <?php if ($isUnread): ?>

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

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="card empty-state">

                <h2>No notifications found</h2>

                <p>
                    <?php if ($filter === 'unread'): ?>

                        You do not have any unread notifications.

                    <?php elseif ($filter === 'read'): ?>

                        You do not have any read notifications.

                    <?php else: ?>

                        Updates about your requisitions will appear
                        here.

                    <?php endif; ?>
                </p>

                <a
                    href="my_requisitions.php"
                    class="btn btn-primary"
                >
                    View My Requisitions
                </a>

            </div>

        <?php endif; ?>

    </div>

</div>

<?php

mysqli_stmt_close($notificationStmt);

require_once '../includes/footer.php';

?>