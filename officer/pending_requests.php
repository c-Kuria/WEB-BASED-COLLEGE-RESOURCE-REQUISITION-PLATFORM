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

$officerSql = "
    SELECT
        officerStaffNo,
        officerName,
        officerRole,
        availability
    FROM officers
    WHERE userID = ?
    LIMIT 1
";

$officerStmt = mysqli_prepare($conn, $officerSql);

if (!$officerStmt) {
    die('Unable to retrieve officer profile.');
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
    die('Officer profile not found.');
}

$staffNo = $officer['officerStaffNo'];

$requestSql = "
    SELECT
        a.approvalNumber,
        a.approvalOrder,
        a.status AS approvalStatus,

        r.requisitionID,
        r.requisitionNumber,
        r.purpose,
        r.quantityRequested,
        r.startDate,
        r.endDate,
        r.requestTime,

        res.resourceName,
        res.resourceCategory,
        res.resourceQuantityRemaining,

        co.officialName,
        co.admNo,
        co.position,

        c.clubName

    FROM approvals a

    INNER JOIN requisitions r
        ON a.requisitionID = r.requisitionID

    INNER JOIN resources res
        ON r.resourceID = res.resourceID

    INNER JOIN club_officials co
        ON r.submittedByAdmNo = co.admNo

    INNER JOIN clubs c
        ON co.clubNumber = c.clubNumber

    WHERE a.officerStaffNo = ?
      AND a.assignedAs = 'Primary'
      AND a.status = 'Pending'
      AND r.status = 'Pending'

    ORDER BY r.requestTime ASC
";

$requestStmt = mysqli_prepare($conn, $requestSql);

if (!$requestStmt) {
    die(
        'Unable to retrieve pending requests: ' .
        mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $requestStmt,
    's',
    $staffNo
);

mysqli_stmt_execute($requestStmt);

$requestResult =
    mysqli_stmt_get_result($requestStmt);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>Pending Requests</h1>

            <p>
                Requisitions assigned to you as the primary
                <?= htmlspecialchars(
                    $officer['officerRole']
                ); ?>.
            </p>
        </div>

        <a
            href="dashboard.php"
            class="btn btn-secondary"
        >
            Dashboard
        </a>

    </div>

    <?php if ($officer['availability'] === 'Unavailable'): ?>

        <div class="alert alert-danger">
            Your availability is currently set to unavailable.
            New requests may be assigned to your proxy officer.
        </div>

    <?php endif; ?>

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

    <div class="card">

        <div class="table-responsive">

            <table class="data-table">

                <thead>
                    <tr>
                        <th>Requisition</th>
                        <th>Club</th>
                        <th>Submitted By</th>
                        <th>Resource</th>
                        <th>Quantity</th>
                        <th>Required Dates</th>
                        <th>Stage</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (
                        mysqli_num_rows($requestResult) > 0
                    ): ?>

                        <?php while (
                            $row =
                                mysqli_fetch_assoc(
                                    $requestResult
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
                                        <?= date(
                                            'd M Y, H:i',
                                            strtotime(
                                                $row[
                                                    'requestTime'
                                                ]
                                            )
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
                                        $row['officialName']
                                    ); ?>

                                    <br>

                                    <small class="text-muted">
                                        <?= htmlspecialchars(
                                            $row['admNo']
                                        ); ?>
                                    </small>
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
                                    <?= (int) $row[
                                        'approvalOrder'
                                    ]; ?>
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
                                colspan="8"
                                class="empty-state"
                            >
                                No pending requests are currently
                                assigned to you.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php

mysqli_stmt_close($requestStmt);

require_once '../includes/footer.php';

?>