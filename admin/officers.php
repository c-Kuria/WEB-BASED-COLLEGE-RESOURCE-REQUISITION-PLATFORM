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

$sql = "
    SELECT
        o.officerStaffNo,
        o.officerName,
        o.officerRole,
        o.availability,
        o.proxyOfficerStaffNo,
        o.createdAt,
        u.username,
        u.status AS accountStatus,
        proxy.officerName AS proxyOfficerName
    FROM officers o

    INNER JOIN users u
        ON o.userID = u.userID

    LEFT JOIN officers proxy
        ON o.proxyOfficerStaffNo = proxy.officerStaffNo

    ORDER BY o.officerRole, o.officerName
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die(
        'Unable to retrieve officers: ' .
        mysqli_error($conn)
    );
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>Manage Officers</h1>
            <p>
                Register approving officers, manage availability,
                and assign proxy officers.
            </p>
        </div>

        <a href="add_officer.php" class="btn btn-primary">
            Add Officer
        </a>

    </div>

    <?php if (isset($_SESSION['success'])): ?>

        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['success']); ?>
        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($_SESSION['error']); ?>
        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>

    <div class="card">

        <div class="table-responsive">

            <table class="data-table">

                <thead>
                    <tr>
                        <th>Staff Number</th>
                        <th>Officer</th>
                        <th>Role</th>
                        <th>Username</th>
                        <th>Availability</th>
                        <th>Proxy Officer</th>
                        <th>Account Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (mysqli_num_rows($result) > 0): ?>

                        <?php while ($officer = mysqli_fetch_assoc($result)): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        $officer['officerStaffNo']
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $officer['officerName']
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $officer['officerRole']
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $officer['username']
                                    ); ?>
                                </td>

                                <td>

                                    <?php if (
                                        $officer['availability'] ===
                                        'Available'
                                    ): ?>

                                        <span class="badge badge-success">
                                            Available
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-danger">
                                            Unavailable
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if (
                                        !empty(
                                            $officer['proxyOfficerName']
                                        )
                                    ): ?>

                                        <?= htmlspecialchars(
                                            $officer['proxyOfficerName']
                                        ); ?>

                                        <small class="text-muted">
                                            (
                                            <?= htmlspecialchars(
                                                $officer[
                                                    'proxyOfficerStaffNo'
                                                ]
                                            ); ?>
                                            )
                                        </small>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            Not assigned
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php if (
                                        $officer['accountStatus'] ===
                                        'Active'
                                    ): ?>

                                        <span class="badge badge-success">
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-secondary">
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td class="action-buttons">

                                    <a
                                        href="edit_officer.php?staffNo=<?= urlencode(
                                            $officer['officerStaffNo']
                                        ); ?>"
                                        class="btn btn-small btn-secondary"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="delete_officer.php"
                                        class="inline-form"
                                        onsubmit="return confirm(
                                            'Delete this officer and their login account?'
                                        );"
                                    >

                                        <input
                                            type="hidden"
                                            name="staffNo"
                                            value="<?= htmlspecialchars(
                                                $officer['officerStaffNo']
                                            ); ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-small btn-danger"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="empty-state">
                                No approving officers have been registered.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>