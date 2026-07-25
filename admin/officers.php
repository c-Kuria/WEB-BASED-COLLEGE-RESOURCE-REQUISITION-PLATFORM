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

$pageTitle = 'Manage Officers';
$errors = [];

/*
|--------------------------------------------------------------------------
| Process officer actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        trim($_POST['action'] ?? '');

    $officerStaffNo =
        trim(
            $_POST['officerStaffNo'] ?? ''
        );

    $allowedActions = [
        'activate',
        'deactivate',
        'delete'
    ];

    if ($officerStaffNo === '') {
        $errors[] =
            'Invalid officer selected.';
    }

    if (
        !in_array(
            $action,
            $allowedActions,
            true
        )
    ) {
        $errors[] =
            'Invalid officer action selected.';
    }

    if (empty($errors)) {

        mysqli_begin_transaction($conn);

        try {

            /*
            |--------------------------------------------------------------------------
            | Lock and fetch officer
            |--------------------------------------------------------------------------
            */

            $officerSql = "
                SELECT
                    o.officerStaffNo,
                    o.userID,
                    o.officerName,
                    o.availability,
                    o.proxyOfficerStaffNo,

                    u.status AS accountStatus

                FROM officers o

                INNER JOIN users u
                    ON o.userID = u.userID

                WHERE o.officerStaffNo = ?

                FOR UPDATE
            ";

            $officerStmt =
                mysqli_prepare(
                    $conn,
                    $officerSql
                );

            if (!$officerStmt) {
                throw new RuntimeException(
                    'Unable to prepare the officer lookup.'
                );
            }

            mysqli_stmt_bind_param(
                $officerStmt,
                's',
                $officerStaffNo
            );

            mysqli_stmt_execute(
                $officerStmt
            );

            $officerResult =
                mysqli_stmt_get_result(
                    $officerStmt
                );

            $officer =
                mysqli_fetch_assoc(
                    $officerResult
                );

            mysqli_stmt_close(
                $officerStmt
            );

            if (!$officer) {
                throw new RuntimeException(
                    'The selected officer was not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Activate officer
            |--------------------------------------------------------------------------
            */

            if ($action === 'activate') {

                $activateUserSql = "
                    UPDATE users

                    SET status = 'Active'

                    WHERE userID = ?
                      AND role = 'officer'
                ";

                $activateUserStmt =
                    mysqli_prepare(
                        $conn,
                        $activateUserSql
                    );

                if (!$activateUserStmt) {
                    throw new RuntimeException(
                        'Unable to prepare officer activation.'
                    );
                }

                mysqli_stmt_bind_param(
                    $activateUserStmt,
                    'i',
                    $officer['userID']
                );

                if (
                    !mysqli_stmt_execute(
                        $activateUserStmt
                    )
                ) {
                    throw new RuntimeException(
                        'Unable to activate the officer account.'
                    );
                }

                mysqli_stmt_close(
                    $activateUserStmt
                );

                $activateOfficerSql = "
                    UPDATE officers

                    SET availability = 'Available'

                    WHERE officerStaffNo = ?
                ";

                $activateOfficerStmt =
                    mysqli_prepare(
                        $conn,
                        $activateOfficerSql
                    );

                if (!$activateOfficerStmt) {
                    throw new RuntimeException(
                        'Unable to prepare officer availability update.'
                    );
                }

                mysqli_stmt_bind_param(
                    $activateOfficerStmt,
                    's',
                    $officerStaffNo
                );

                mysqli_stmt_execute(
                    $activateOfficerStmt
                );

                mysqli_stmt_close(
                    $activateOfficerStmt
                );

                mysqli_commit($conn);

                $_SESSION['success'] =
                    'Officer account activated successfully.';

                header('Location: officers.php');
                exit();
            }

            /*
            |--------------------------------------------------------------------------
            | Deactivate officer
            |--------------------------------------------------------------------------
            */

            if ($action === 'deactivate') {

                $deactivateUserSql = "
                    UPDATE users

                    SET status = 'Inactive'

                    WHERE userID = ?
                      AND role = 'officer'
                ";

                $deactivateUserStmt =
                    mysqli_prepare(
                        $conn,
                        $deactivateUserSql
                    );

                if (!$deactivateUserStmt) {
                    throw new RuntimeException(
                        'Unable to prepare officer deactivation.'
                    );
                }

                mysqli_stmt_bind_param(
                    $deactivateUserStmt,
                    'i',
                    $officer['userID']
                );

                if (
                    !mysqli_stmt_execute(
                        $deactivateUserStmt
                    )
                ) {
                    throw new RuntimeException(
                        'Unable to deactivate the officer account.'
                    );
                }

                mysqli_stmt_close(
                    $deactivateUserStmt
                );

                $deactivateOfficerSql = "
                    UPDATE officers

                    SET availability = 'Unavailable'

                    WHERE officerStaffNo = ?
                ";

                $deactivateOfficerStmt =
                    mysqli_prepare(
                        $conn,
                        $deactivateOfficerSql
                    );

                if (!$deactivateOfficerStmt) {
                    throw new RuntimeException(
                        'Unable to update officer availability.'
                    );
                }

                mysqli_stmt_bind_param(
                    $deactivateOfficerStmt,
                    's',
                    $officerStaffNo
                );

                mysqli_stmt_execute(
                    $deactivateOfficerStmt
                );

                mysqli_stmt_close(
                    $deactivateOfficerStmt
                );

                /*
                 * Remove this officer as a proxy from other
                 * officer profiles.
                 */

                $removeProxySql = "
                    UPDATE officers

                    SET proxyOfficerStaffNo = NULL

                    WHERE proxyOfficerStaffNo = ?
                ";

                $removeProxyStmt =
                    mysqli_prepare(
                        $conn,
                        $removeProxySql
                    );

                if (!$removeProxyStmt) {
                    throw new RuntimeException(
                        'Unable to remove proxy assignments.'
                    );
                }

                mysqli_stmt_bind_param(
                    $removeProxyStmt,
                    's',
                    $officerStaffNo
                );

                mysqli_stmt_execute(
                    $removeProxyStmt
                );

                mysqli_stmt_close(
                    $removeProxyStmt
                );

                mysqli_commit($conn);

                $_SESSION['success'] =
                    'Officer account deactivated successfully.';

                header('Location: officers.php');
                exit();
            }

            /*
            |--------------------------------------------------------------------------
            | Check approval history
            |--------------------------------------------------------------------------
            */

            $approvalSql = "
                SELECT COUNT(*) AS approvalCount

                FROM approvals

                WHERE officerStaffNo = ?
                   OR actedBy = ?
            ";

            $approvalStmt =
                mysqli_prepare(
                    $conn,
                    $approvalSql
                );

            if (!$approvalStmt) {
                throw new RuntimeException(
                    'Unable to check officer approval history.'
                );
            }

            mysqli_stmt_bind_param(
                $approvalStmt,
                'ss',
                $officerStaffNo,
                $officerStaffNo
            );

            mysqli_stmt_execute(
                $approvalStmt
            );

            $approvalResult =
                mysqli_stmt_get_result(
                    $approvalStmt
                );

            $approvalUsage =
                mysqli_fetch_assoc(
                    $approvalResult
                );

            mysqli_stmt_close(
                $approvalStmt
            );

            $approvalCount =
                (int) (
                    $approvalUsage['approvalCount'] ?? 0
                );

            $proxyUsageSql = "
                SELECT COUNT(*) AS proxyCount

                FROM officers

                WHERE proxyOfficerStaffNo = ?
            ";

            $proxyUsageStmt =
                mysqli_prepare(
                    $conn,
                    $proxyUsageSql
                );

            if (!$proxyUsageStmt) {
                throw new RuntimeException(
                    'Unable to check officer proxy usage.'
                );
            }

            mysqli_stmt_bind_param(
                $proxyUsageStmt,
                's',
                $officerStaffNo
            );

            mysqli_stmt_execute(
                $proxyUsageStmt
            );

            $proxyUsageResult =
                mysqli_stmt_get_result(
                    $proxyUsageStmt
                );

            $proxyUsage =
                mysqli_fetch_assoc(
                    $proxyUsageResult
                );

            mysqli_stmt_close(
                $proxyUsageStmt
            );

            $proxyCount =
                (int) (
                    $proxyUsage['proxyCount'] ?? 0
                );

            /*
            |--------------------------------------------------------------------------
            | Deactivate referenced officer
            |--------------------------------------------------------------------------
            */

            if (
                $approvalCount > 0 ||
                $proxyCount > 0
            ) {

                $deactivateUserSql = "
                    UPDATE users

                    SET status = 'Inactive'

                    WHERE userID = ?
                      AND role = 'officer'
                ";

                $deactivateUserStmt =
                    mysqli_prepare(
                        $conn,
                        $deactivateUserSql
                    );

                if (!$deactivateUserStmt) {
                    throw new RuntimeException(
                        'Unable to prepare officer deactivation.'
                    );
                }

                mysqli_stmt_bind_param(
                    $deactivateUserStmt,
                    'i',
                    $officer['userID']
                );

                mysqli_stmt_execute(
                    $deactivateUserStmt
                );

                mysqli_stmt_close(
                    $deactivateUserStmt
                );

                $deactivateOfficerSql = "
                    UPDATE officers

                    SET
                        availability = 'Unavailable',
                        proxyOfficerStaffNo = NULL

                    WHERE officerStaffNo = ?
                ";

                $deactivateOfficerStmt =
                    mysqli_prepare(
                        $conn,
                        $deactivateOfficerSql
                    );

                if (!$deactivateOfficerStmt) {
                    throw new RuntimeException(
                        'Unable to update officer availability.'
                    );
                }

                mysqli_stmt_bind_param(
                    $deactivateOfficerStmt,
                    's',
                    $officerStaffNo
                );

                mysqli_stmt_execute(
                    $deactivateOfficerStmt
                );

                mysqli_stmt_close(
                    $deactivateOfficerStmt
                );

                $removeProxySql = "
                    UPDATE officers

                    SET proxyOfficerStaffNo = NULL

                    WHERE proxyOfficerStaffNo = ?
                ";

                $removeProxyStmt =
                    mysqli_prepare(
                        $conn,
                        $removeProxySql
                    );

                if (!$removeProxyStmt) {
                    throw new RuntimeException(
                        'Unable to clear proxy assignments.'
                    );
                }

                mysqli_stmt_bind_param(
                    $removeProxyStmt,
                    's',
                    $officerStaffNo
                );

                mysqli_stmt_execute(
                    $removeProxyStmt
                );

                mysqli_stmt_close(
                    $removeProxyStmt
                );

                mysqli_commit($conn);

                $_SESSION['success'] =
                    'The officer has approval or proxy history, so the account was deactivated instead of permanently deleted.';

                header('Location: officers.php');
                exit();
            }

            /*
            |--------------------------------------------------------------------------
            | Permanently delete unused officer
            |--------------------------------------------------------------------------
            */

            $deleteOfficerSql = "
                DELETE FROM officers

                WHERE officerStaffNo = ?
            ";

            $deleteOfficerStmt =
                mysqli_prepare(
                    $conn,
                    $deleteOfficerSql
                );

            if (!$deleteOfficerStmt) {
                throw new RuntimeException(
                    'Unable to prepare officer deletion.'
                );
            }

            mysqli_stmt_bind_param(
                $deleteOfficerStmt,
                's',
                $officerStaffNo
            );

            if (
                !mysqli_stmt_execute(
                    $deleteOfficerStmt
                )
            ) {
                throw new RuntimeException(
                    'Unable to delete the officer profile.'
                );
            }

            if (
                mysqli_stmt_affected_rows(
                    $deleteOfficerStmt
                ) !== 1
            ) {
                throw new RuntimeException(
                    'The officer profile was not deleted.'
                );
            }

            mysqli_stmt_close(
                $deleteOfficerStmt
            );

            $deleteUserSql = "
                DELETE FROM users

                WHERE userID = ?
                  AND role = 'officer'
            ";

            $deleteUserStmt =
                mysqli_prepare(
                    $conn,
                    $deleteUserSql
                );

            if (!$deleteUserStmt) {
                throw new RuntimeException(
                    'Unable to prepare officer account deletion.'
                );
            }

            mysqli_stmt_bind_param(
                $deleteUserStmt,
                'i',
                $officer['userID']
            );

            if (
                !mysqli_stmt_execute(
                    $deleteUserStmt
                )
            ) {
                throw new RuntimeException(
                    'Unable to delete the officer account.'
                );
            }

            mysqli_stmt_close(
                $deleteUserStmt
            );

            mysqli_commit($conn);

            $_SESSION['success'] =
                'Officer permanently deleted successfully.';

            header('Location: officers.php');
            exit();

        } catch (Throwable $exception) {

            mysqli_rollback($conn);

            $errors[] =
                $exception->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Fetch officers
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        o.officerStaffNo,
        o.officerName,
        o.officerRole,
        o.availability,
        o.proxyOfficerStaffNo,

        u.username,
        u.status AS accountStatus,

        proxy.officerName AS proxyOfficerName

    FROM officers o

    INNER JOIN users u
        ON o.userID = u.userID

    LEFT JOIN officers proxy
        ON o.proxyOfficerStaffNo =
           proxy.officerStaffNo

    ORDER BY
        u.status DESC,
        o.officerRole ASC,
        o.officerName ASC
";

$result =
    mysqli_query($conn, $sql);

require_once '../includes/header.php';
?>

<div class="page-header">

    <div>

        <h1>Manage Officers</h1>

        <p>
            Register approving officers, manage availability
            and assign proxy officers.
        </p>

    </div>

    <a
        href="add_officer.php"
        class="btn btn-primary"
    >
        Add Officer
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

<?php if (!empty($errors)): ?>

    <div class="alert alert-danger">

        <?php foreach ($errors as $error): ?>

            <p>
                <?= htmlspecialchars($error); ?>
            </p>

        <?php endforeach; ?>

    </div>

<?php endif; ?>

<div class="card table-card">

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
                    <th class="actions-heading">
                        Actions
                    </th>
                </tr>

            </thead>

            <tbody>

                <?php if (
                    $result &&
                    mysqli_num_rows($result) > 0
                ): ?>

                    <?php while (
                        $officer =
                            mysqli_fetch_assoc($result)
                    ): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $officer[
                                            'officerStaffNo'
                                        ]
                                    ); ?>
                                </strong>
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

                                <span
                                    class="badge <?= $officer[
                                        'availability'
                                    ] === 'Available'
                                        ? 'badge-success'
                                        : 'badge-secondary'; ?>"
                                >
                                    <?= htmlspecialchars(
                                        $officer[
                                            'availability'
                                        ]
                                    ); ?>
                                </span>

                            </td>

                            <td>

                                <?php if (
                                    !empty(
                                        $officer[
                                            'proxyOfficerName'
                                        ]
                                    )
                                ): ?>

                                    <?= htmlspecialchars(
                                        $officer[
                                            'proxyOfficerName'
                                        ]
                                    ); ?>

                                    <small class="table-subtext">
                                        <?= htmlspecialchars(
                                            $officer[
                                                'proxyOfficerStaffNo'
                                            ]
                                        ); ?>
                                    </small>

                                <?php else: ?>

                                    <span class="table-muted">
                                        Not assigned
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <span
                                    class="badge <?= $officer[
                                        'accountStatus'
                                    ] === 'Active'
                                        ? 'badge-success'
                                        : 'badge-secondary'; ?>"
                                >
                                    <?= htmlspecialchars(
                                        $officer[
                                            'accountStatus'
                                        ]
                                    ); ?>
                                </span>

                            </td>

                            <td>

                                <div class="table-actions">

                                    <a
                                        href="edit_officer.php?staffNo=<?= urlencode(
                                            $officer[
                                                'officerStaffNo'
                                            ]
                                        ); ?>"
                                        class="btn btn-secondary btn-small"
                                    >
                                        Edit
                                    </a>

                                    <?php if (
                                        $officer[
                                            'accountStatus'
                                        ] === 'Active'
                                    ): ?>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm(
                                                'Deactivate this officer account?'
                                            );"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="deactivate"
                                            >

                                            <input
                                                type="hidden"
                                                name="officerStaffNo"
                                                value="<?= htmlspecialchars(
                                                    $officer[
                                                        'officerStaffNo'
                                                    ]
                                                ); ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-warning btn-small"
                                            >
                                                Deactivate
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm(
                                                'Reactivate this officer account?'
                                            );"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="activate"
                                            >

                                            <input
                                                type="hidden"
                                                name="officerStaffNo"
                                                value="<?= htmlspecialchars(
                                                    $officer[
                                                        'officerStaffNo'
                                                    ]
                                                ); ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-success btn-small"
                                            >
                                                Activate
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                    <form
                                        method="POST"
                                        onsubmit="return confirm(
                                            'Permanently delete this officer? If approval history exists, the account will be deactivated instead.'
                                        );"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="officerStaffNo"
                                            value="<?= htmlspecialchars(
                                                $officer[
                                                    'officerStaffNo'
                                                ]
                                            ); ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-danger-outline btn-small"
                                        >
                                            Delete
                                        </button>

                                    </form>

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
                            No officers have been registered.
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