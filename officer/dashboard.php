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

$userID = (int) ($_SESSION['userID'] ?? 0);

if ($userID <= 0) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Retrieve logged-in officer
|--------------------------------------------------------------------------
*/

$officerSql = "
    SELECT
        o.officerStaffNo,
        o.officerName,
        o.officerRole,
        o.availability,
        o.proxyOfficerStaffNo,
        u.username,
        u.status AS accountStatus,
        proxy.officerName AS proxyOfficerName,
        proxy.officerRole AS proxyOfficerRole
    FROM officers o

    INNER JOIN users u
        ON o.userID = u.userID

    LEFT JOIN officers proxy
        ON o.proxyOfficerStaffNo = proxy.officerStaffNo

    WHERE o.userID = ?

    LIMIT 1
";

$officerStmt = mysqli_prepare($conn, $officerSql);

if (!$officerStmt) {
    die(
        'Unable to prepare officer profile query: ' .
        mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $officerStmt,
    'i',
    $userID
);

mysqli_stmt_execute($officerStmt);

$officerResult =
    mysqli_stmt_get_result($officerStmt);

$officer =
    mysqli_fetch_assoc($officerResult);

mysqli_stmt_close($officerStmt);

if (!$officer) {
    die(
        'Your officer profile could not be found. ' .
        'Contact the administrator.'
    );
}

$staffNo = $officer['officerStaffNo'];

/*
|--------------------------------------------------------------------------
| Dashboard totals
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT
        SUM(
            CASE
                WHEN a.officerStaffNo = ?
                 AND a.assignedAs = 'Primary'
                 AND a.status = 'Pending'
                THEN 1
                ELSE 0
            END
        ) AS pendingPrimary,

        SUM(
            CASE
                WHEN a.actedBy = ?
                 AND a.assignedAs = 'Proxy'
                 AND a.status IN ('Pending', 'Delegated')
                THEN 1
                ELSE 0
            END
        ) AS delegatedPending,

        SUM(
            CASE
                WHEN (
                    a.officerStaffNo = ?
                    OR a.actedBy = ?
                )
                AND a.status = 'Approved'
                THEN 1
                ELSE 0
            END
        ) AS approvedCount,

        SUM(
            CASE
                WHEN (
                    a.officerStaffNo = ?
                    OR a.actedBy = ?
                )
                AND a.status = 'Rejected'
                THEN 1
                ELSE 0
            END
        ) AS rejectedCount

    FROM approvals a
";

$countStmt = mysqli_prepare($conn, $countSql);

if (!$countStmt) {
    die(
        'Unable to prepare dashboard totals: ' .
        mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $countStmt,
    'ssssss',
    $staffNo,
    $staffNo,
    $staffNo,
    $staffNo,
    $staffNo,
    $staffNo
);

mysqli_stmt_execute($countStmt);

$countResult =
    mysqli_stmt_get_result($countStmt);

$countRow =
    mysqli_fetch_assoc($countResult);

mysqli_stmt_close($countStmt);

$pendingPrimary =
    (int) ($countRow['pendingPrimary'] ?? 0);

$delegatedPending =
    (int) ($countRow['delegatedPending'] ?? 0);

$approvedCount =
    (int) ($countRow['approvedCount'] ?? 0);

$rejectedCount =
    (int) ($countRow['rejectedCount'] ?? 0);

/*
|--------------------------------------------------------------------------
| Recent actionable requests
|--------------------------------------------------------------------------
*/

$recentSql = "
    SELECT
        a.approvalNumber,
        a.approvalOrder,
        a.status AS approvalStatus,
        a.assignedAs,
        a.actedBy,

        r.requisitionID,
        r.requisitionNumber,
        r.purpose,
        r.quantityRequested,
        r.startDate,
        r.endDate,
        r.requestTime,
        r.status AS requisitionStatus,

        res.resourceName,
        res.resourceCategory,

        co.officialName,
        co.admNo,

        c.clubName,

        primaryOfficer.officerName
            AS primaryOfficerName,
        primaryOfficer.officerRole
            AS primaryOfficerRole

    FROM approvals a

    INNER JOIN requisitions r
        ON a.requisitionID = r.requisitionID

    INNER JOIN resources res
        ON r.resourceID = res.resourceID

    INNER JOIN club_officials co
        ON r.submittedByAdmNo = co.admNo

    INNER JOIN clubs c
        ON co.clubNumber = c.clubNumber

    INNER JOIN officers primaryOfficer
        ON a.officerStaffNo =
           primaryOfficer.officerStaffNo

    WHERE r.status = 'Pending'
      AND a.status IN ('Pending', 'Delegated')
      AND (
          (
              a.officerStaffNo = ?
              AND a.assignedAs = 'Primary'
          )
          OR
          (
              a.actedBy = ?
              AND a.assignedAs = 'Proxy'
          )
      )

    ORDER BY r.requestTime ASC
    LIMIT 5
";

$recentStmt = mysqli_prepare($conn, $recentSql);

if (!$recentStmt) {
    die(
        'Unable to prepare recent requests: ' .
        mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $recentStmt,
    'ss',
    $staffNo,
    $staffNo
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
            <h1>Officer Dashboard</h1>

            <p>
                Welcome,
                <?= htmlspecialchars(
                    $officer['officerName']
                ); ?>.
                Review and process assigned requisitions.
            </p>
        </div>

        <a
            href="pending_requests.php"
            class="btn btn-primary"
        >
            View Pending Requests
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

    <div class="card officer-summary-card">

        <div class="section-header">
            <h2>
                <?= htmlspecialchars(
                    $officer['officerRole']
                ); ?>
            </h2>

            <p>
                Staff number:
                <?= htmlspecialchars($staffNo); ?>
            </p>
        </div>

        <div class="officer-details-grid">

            <div>
                <strong>Availability</strong>

                <p>
                    <span
                        class="badge <?= $officer['availability'] ===
                        'Available'
                            ? 'badge-success'
                            : 'badge-warning'; ?>"
                    >
                        <?= htmlspecialchars(
                            $officer['availability']
                        ); ?>
                    </span>
                </p>
            </div>

            <div>
                <strong>Account Status</strong>

                <p>
                    <span
                        class="badge <?= $officer['accountStatus'] ===
                        'Active'
                            ? 'badge-success'
                            : 'badge-danger'; ?>"
                    >
                        <?= htmlspecialchars(
                            $officer['accountStatus']
                        ); ?>
                    </span>
                </p>
            </div>

            <div>
                <strong>Proxy Officer</strong>

                <p>
                    <?php if (
                        !empty($officer['proxyOfficerName'])
                    ): ?>

                        <?= htmlspecialchars(
                            $officer['proxyOfficerName']
                        ); ?>

                        <br>

                        <small class="text-muted">
                            <?= htmlspecialchars(
                                $officer['proxyOfficerRole']
                            ); ?>
                        </small>

                    <?php else: ?>

                        No proxy assigned

                    <?php endif; ?>
                </p>
            </div>

        </div>

    </div>

    <div class="stats-grid">

        <a
            href="pending_requests.php"
            class="stat-card officer-stat-link"
        >
            <h3>Pending Requests</h3>

            <div class="stat-number">
                <?= $pendingPrimary; ?>
            </div>
        </a>

        <a
            href="delegated_requests.php"
            class="stat-card officer-stat-link"
        >
            <h3>Delegated to Me</h3>

            <div class="stat-number">
                <?= $delegatedPending; ?>
            </div>
        </a>

        <div class="stat-card">

            <h3>Approved</h3>

            <div class="stat-number">
                <?= $approvedCount; ?>
            </div>
        </div>

        <div class="stat-card">

            <h3>Rejected</h3>

            <div class="stat-number">
                <?= $rejectedCount; ?>
            </div>
        </div>

    </div>

    <div class="card">

        <div class="section-header section-header-row">

            <div>
                <h2>Requests Requiring Action</h2>

                <p>
                    Your latest primary and delegated approval
                    assignments.
                </p>
            </div>

            <a
                href="pending_requests.php"
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
                        <th>Club</th>
                        <th>Resource</th>
                        <th>Stage</th>
                        <th>Assignment</th>
                        <th>Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (
                        mysqli_num_rows($recentResult) > 0
                    ): ?>

                        <?php while (
                            $row =
                                mysqli_fetch_assoc(
                                    $recentResult
                                )
                        ): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $row[
                                                'requisitionNumber'
                                            ]
                                        ); ?>
                                    </strong>

                                    <br>

                                    <small class="text-muted">
                                        <?= htmlspecialchars(
                                            $row[
                                                'officialName'
                                            ]
                                        ); ?>
                                    </small>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['clubName']
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['resourceName']
                                    ); ?>

                                    <br>

                                    <small class="text-muted">
                                        <?= htmlspecialchars(
                                            $row[
                                                'resourceCategory'
                                            ]
                                        ); ?>
                                    </small>
                                </td>

                                <td>
                                    Stage
                                    <?= (int) $row[
                                        'approvalOrder'
                                    ]; ?>
                                </td>

                                <td>
                                    <?php if (
                                        $row['assignedAs'] ===
                                        'Proxy'
                                    ): ?>

                                        <span class="badge badge-info">
                                            Proxy
                                        </span>

                                        <br>

                                        <small class="text-muted">
                                            For
                                            <?= htmlspecialchars(
                                                $row[
                                                    'primaryOfficerName'
                                                ]
                                            ); ?>
                                        </small>

                                    <?php else: ?>

                                        <span
                                            class="badge badge-secondary"
                                        >
                                            Primary
                                        </span>

                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= date(
                                        'd M Y, H:i',
                                        strtotime(
                                            $row['requestTime']
                                        )
                                    ); ?>
                                </td>

                                <td>
                                    <a
                                        href="review_request.php?approval=<?= (int) $row[
                                            'approvalNumber'
                                        ]; ?>"
                                        class="btn btn-primary btn-small"
                                    >
                                        Review
                                    </a>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td
                                colspan="7"
                                class="empty-state"
                            >
                                You currently have no requests
                                requiring action.
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