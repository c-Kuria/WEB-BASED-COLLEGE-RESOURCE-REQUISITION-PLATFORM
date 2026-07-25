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

$pageTitle = 'Officer Dashboard';

$profileSql = "
    SELECT
        o.officerStaffNo,
        o.officerName,
        o.officerRole,
        o.availability,
        o.proxyOfficerStaffNo,

        u.status AS accountStatus,

        proxy.officerName AS proxyOfficerName,
        proxy.officerRole AS proxyOfficerRole

    FROM officers o

    INNER JOIN users u
        ON o.userID = u.userID

    LEFT JOIN officers proxy
        ON o.proxyOfficerStaffNo =
           proxy.officerStaffNo

    WHERE o.userID = ?

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

$officer =
    mysqli_fetch_assoc(
        $profileResult
    );

mysqli_stmt_close(
    $profileStmt
);

if (!$officer) {
    die('Officer profile not found.');
}

$staffNo =
    $officer['officerStaffNo'];

function getOfficerApprovalCount(
    mysqli $conn,
    string $staffNo,
    string $status,
    string $assignedAs = ''
): int {

    if ($assignedAs !== '') {

        $sql = "
            SELECT COUNT(*)
            FROM approvals
            WHERE officerStaffNo = ?
              AND status = ?
              AND assignedAs = ?
        ";

        $stmt =
            mysqli_prepare(
                $conn,
                $sql
            );

        mysqli_stmt_bind_param(
            $stmt,
            'sss',
            $staffNo,
            $status,
            $assignedAs
        );
    } else {

        $sql = "
            SELECT COUNT(*)
            FROM approvals
            WHERE officerStaffNo = ?
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
            $staffNo,
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

$pendingPrimaryCount =
    getOfficerApprovalCount(
        $conn,
        $staffNo,
        'Pending',
        'Primary'
    );

$pendingProxyCount =
    getOfficerApprovalCount(
        $conn,
        $staffNo,
        'Pending',
        'Proxy'
    );

$approvedCount =
    getOfficerApprovalCount(
        $conn,
        $staffNo,
        'Approved'
    );

$rejectedCount =
    getOfficerApprovalCount(
        $conn,
        $staffNo,
        'Rejected'
    );

$latestSql = "
    SELECT
        a.approvalNumber,
        a.assignedAs,
        a.approvalOrder,
        a.status AS approvalStatus,

        r.requisitionID,
        r.requisitionNumber,
        r.requestTime,
        r.quantityRequested,
        r.startDate,
        r.endDate,

        rs.resourceName,
        rs.resourceCategory,

        co.officialName,
        c.clubName

    FROM approvals a

    INNER JOIN requisitions r
        ON a.requisitionID = r.requisitionID

    INNER JOIN resources rs
        ON r.resourceID = rs.resourceID

    INNER JOIN club_officials co
        ON r.submittedByAdmNo = co.admNo

    INNER JOIN clubs c
        ON co.clubNumber = c.clubNumber

    WHERE a.officerStaffNo = ?
      AND a.status = 'Pending'

    ORDER BY r.requestTime DESC

    LIMIT 6
";

$latestStmt =
    mysqli_prepare(
        $conn,
        $latestSql
    );

mysqli_stmt_bind_param(
    $latestStmt,
    's',
    $staffNo
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
            Review requests and manage your approval duties.
        </p> -->
    </div>

</div>

<div class="dashboard-welcome">

    <div>

        <span class="dashboard-welcome-label">
            Approval workspace
        </span>

        <!-- <h2>
            Welcome,
            <?= htmlspecialchars(
                $officer['officerName']
            ); ?>
        </h2> -->

        <p>
            Review pending and delegated requisitions assigned
            to your office.
        </p>

    </div>

</div>

<div class="dashboard-profile-strip">

    <div class="dashboard-profile-main">

        <div class="dashboard-profile-avatar">
            <?= htmlspecialchars(
                strtoupper(
                    substr(
                        $officer['officerName'],
                        0,
                        1
                    )
                )
            ); ?>
        </div>

        <div>

            <h2>
                <?= htmlspecialchars(
                    $officer['officerName']
                ); ?>
            </h2>

            <p>
                <?= htmlspecialchars(
                    $officer['officerRole']
                ); ?>
            </p>

        </div>

    </div>

    <div class="dashboard-profile-details">

        <div class="dashboard-detail-item">

            <span>Staff Number</span>

            <strong>
                <?= htmlspecialchars(
                    $officer['officerStaffNo']
                ); ?>
            </strong>

        </div>

        <div class="dashboard-detail-item">

            <span>Availability</span>

            <strong>

                <span
                    class="badge <?= $officer['availability'] === 'Available'
                                        ? 'badge-success'
                                        : 'badge-danger'; ?>">
                    <?= htmlspecialchars(
                        $officer['availability']
                    ); ?>
                </span>

            </strong>

        </div>

        <div class="dashboard-detail-item">

            <span>Account Status</span>

            <strong>

                <span
                    class="badge <?= $officer['accountStatus'] === 'Active'
                                        ? 'badge-success'
                                        : 'badge-danger'; ?>">
                    <?= htmlspecialchars(
                        $officer['accountStatus']
                    ); ?>
                </span>

            </strong>

        </div>

        <div class="dashboard-detail-item">

            <span>Proxy Officer</span>

            <strong>
                <?= !empty($officer['proxyOfficerName'])
                    ? htmlspecialchars(
                        $officer['proxyOfficerName']
                    )
                    : 'Not assigned'; ?>
            </strong>

            <?php if (
                !empty($officer['proxyOfficerRole'])
            ): ?>

                <small>
                    <?= htmlspecialchars(
                        $officer['proxyOfficerRole']
                    ); ?>
                </small>

            <?php endif; ?>

        </div>

    </div>

</div>

<div class="dashboard-stat-grid dashboard-stat-grid-four">

    <a
        href="pending_requests.php"
        class="dashboard-stat-tile">

        <div class="dashboard-stat-icon dashboard-icon-warning">
            P
        </div>

        <div class="dashboard-stat-content">

            <span class="dashboard-stat-label">
                Pending Requests
            </span>

            <strong class="dashboard-stat-value">
                <?= $pendingPrimaryCount; ?>
            </strong>

            <small>
                Primary approvals
            </small>

        </div>

    </a>

    <a
        href="delegated_requests.php"
        class="dashboard-stat-tile">

        <div class="dashboard-stat-icon">
            D
        </div>

        <div class="dashboard-stat-content">

            <span class="dashboard-stat-label">
                Delegated Requests
            </span>

            <strong class="dashboard-stat-value">
                <?= $pendingProxyCount; ?>
            </strong>

            <small>
                Proxy approvals
            </small>

        </div>

    </a>

    <div class="dashboard-stat-tile">

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
                Requests approved
            </small>

        </div>

    </div>

    <div class="dashboard-stat-tile">

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
                Requests rejected
            </small>

        </div>

    </div>

</div>

<div class="card dashboard-table-card">

    <div class="section-header-row">

        <div class="section-header">

            <h2>Requests Requiring Action</h2>

            <p>
                Requisitions currently awaiting your decision.
            </p>

        </div>

        <a
            href="pending_requests.php"
            class="btn btn-secondary btn-small">
            View All
        </a>

    </div>

    <div class="table-responsive">

        <table class="data-table dashboard-data-table">

            <thead>

                <tr>
                    <th>Requisition</th>
                    <th>Submitted By</th>
                    <th>Resource</th>
                    <th>Required Period</th>
                    <th>Assignment</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

                <?php if (
                    mysqli_num_rows(
                        $latestResult
                    ) > 0
                ): ?>

                    <?php while (
                        $request =
                        mysqli_fetch_assoc(
                            $latestResult
                        )
                    ): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $request['requisitionNumber']
                                    ); ?>
                                </strong>

                                <small class="table-subtext">
                                    <?= date(
                                        'd M Y, H:i',
                                        strtotime(
                                            $request['requestTime']
                                        )
                                    ); ?>
                                </small>

                            </td>

                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $request['officialName']
                                    ); ?>
                                </strong>

                                <small class="table-subtext">
                                    <?= htmlspecialchars(
                                        $request['clubName']
                                    ); ?>
                                </small>

                            </td>

                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $request['resourceName']
                                    ); ?>
                                </strong>

                                <small class="table-subtext">
                                    Quantity:
                                    <?= (int) $request['quantityRequested']; ?>
                                </small>

                            </td>

                            <td>
                                <?= date(
                                    'd M Y',
                                    strtotime(
                                        $request['startDate']
                                    )
                                ); ?>

                                <span class="table-date-separator">
                                    –
                                </span>

                                <?= date(
                                    'd M Y',
                                    strtotime(
                                        $request['endDate']
                                    )
                                ); ?>
                            </td>

                            <td>

                                <span
                                    class="badge <?= $request['assignedAs'] === 'Proxy'
                                                        ? 'badge-info'
                                                        : 'badge-secondary'; ?>">
                                    <?= htmlspecialchars(
                                        $request['assignedAs']
                                    ); ?>
                                </span>

                            </td>

                            <td>

                                <div class="table-actions">

                                    <a
                                        href="review_request.php?id=<?= (int) $request['approvalNumber']; ?>"
                                        class="btn btn-primary btn-small">
                                        Review
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="6"
                            class="empty-state">
                            You have no pending approval
                            requests.
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