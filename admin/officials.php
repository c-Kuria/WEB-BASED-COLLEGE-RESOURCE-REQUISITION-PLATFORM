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

$pageTitle = 'Manage Club Officials';
$errors = [];

/*
|--------------------------------------------------------------------------
| Process official actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        trim($_POST['action'] ?? '');

    $admNo =
        trim($_POST['admNo'] ?? '');

    $allowedActions = [
        'activate',
        'deactivate',
        'delete'
    ];

    if ($admNo === '') {
        $errors[] =
            'Invalid official selected.';
    }

    if (
        !in_array(
            $action,
            $allowedActions,
            true
        )
    ) {
        $errors[] =
            'Invalid official action selected.';
    }

    if (empty($errors)) {

        mysqli_begin_transaction($conn);

        try {

            /*
            |--------------------------------------------------------------------------
            | Lock and fetch official
            |--------------------------------------------------------------------------
            */

            $officialSql = "
                SELECT
                    co.admNo,
                    co.userID,
                    co.officialName,
                    u.status AS accountStatus

                FROM club_officials co

                INNER JOIN users u
                    ON co.userID = u.userID

                WHERE co.admNo = ?

                FOR UPDATE
            ";

            $officialStmt =
                mysqli_prepare(
                    $conn,
                    $officialSql
                );

            if (!$officialStmt) {
                throw new RuntimeException(
                    'Unable to prepare the official lookup.'
                );
            }

            mysqli_stmt_bind_param(
                $officialStmt,
                's',
                $admNo
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
                throw new RuntimeException(
                    'The selected official was not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Activate or deactivate
            |--------------------------------------------------------------------------
            */

            if (
                $action === 'activate' ||
                $action === 'deactivate'
            ) {

                $newStatus =
                    $action === 'activate'
                        ? 'Active'
                        : 'Inactive';

                $statusSql = "
                    UPDATE users

                    SET status = ?

                    WHERE userID = ?
                      AND role = 'official'
                ";

                $statusStmt =
                    mysqli_prepare(
                        $conn,
                        $statusSql
                    );

                if (!$statusStmt) {
                    throw new RuntimeException(
                        'Unable to prepare the official status update.'
                    );
                }

                mysqli_stmt_bind_param(
                    $statusStmt,
                    'si',
                    $newStatus,
                    $official['userID']
                );

                if (
                    !mysqli_stmt_execute(
                        $statusStmt
                    )
                ) {
                    throw new RuntimeException(
                        'Unable to update the official account.'
                    );
                }

                mysqli_stmt_close(
                    $statusStmt
                );

                mysqli_commit($conn);

                $_SESSION['success'] =
                    $newStatus === 'Active'
                        ? 'Official account activated successfully.'
                        : 'Official account deactivated successfully.';

                header('Location: officials.php');
                exit();
            }

            /*
            |--------------------------------------------------------------------------
            | Check requisition history
            |--------------------------------------------------------------------------
            */

            $usageSql = "
                SELECT COUNT(*) AS requisitionCount

                FROM requisitions

                WHERE submittedByAdmNo = ?
            ";

            $usageStmt =
                mysqli_prepare(
                    $conn,
                    $usageSql
                );

            if (!$usageStmt) {
                throw new RuntimeException(
                    'Unable to check official requisitions.'
                );
            }

            mysqli_stmt_bind_param(
                $usageStmt,
                's',
                $admNo
            );

            mysqli_stmt_execute(
                $usageStmt
            );

            $usageResult =
                mysqli_stmt_get_result(
                    $usageStmt
                );

            $usage =
                mysqli_fetch_assoc(
                    $usageResult
                );

            mysqli_stmt_close(
                $usageStmt
            );

            $requisitionCount =
                (int) (
                    $usage['requisitionCount'] ?? 0
                );

            /*
            |--------------------------------------------------------------------------
            | Deactivate official with history
            |--------------------------------------------------------------------------
            */

            if ($requisitionCount > 0) {

                $deactivateSql = "
                    UPDATE users

                    SET status = 'Inactive'

                    WHERE userID = ?
                      AND role = 'official'
                ";

                $deactivateStmt =
                    mysqli_prepare(
                        $conn,
                        $deactivateSql
                    );

                if (!$deactivateStmt) {
                    throw new RuntimeException(
                        'Unable to prepare official deactivation.'
                    );
                }

                mysqli_stmt_bind_param(
                    $deactivateStmt,
                    'i',
                    $official['userID']
                );

                if (
                    !mysqli_stmt_execute(
                        $deactivateStmt
                    )
                ) {
                    throw new RuntimeException(
                        'Unable to deactivate the official.'
                    );
                }

                mysqli_stmt_close(
                    $deactivateStmt
                );

                mysqli_commit($conn);

                $_SESSION['success'] =
                    'The official has requisition history, so the account was deactivated instead of permanently deleted.';

                header('Location: officials.php');
                exit();
            }

            /*
            |--------------------------------------------------------------------------
            | Permanently delete unused official
            |--------------------------------------------------------------------------
            */

            $deleteOfficialSql = "
                DELETE FROM club_officials

                WHERE admNo = ?
            ";

            $deleteOfficialStmt =
                mysqli_prepare(
                    $conn,
                    $deleteOfficialSql
                );

            if (!$deleteOfficialStmt) {
                throw new RuntimeException(
                    'Unable to prepare official deletion.'
                );
            }

            mysqli_stmt_bind_param(
                $deleteOfficialStmt,
                's',
                $admNo
            );

            if (
                !mysqli_stmt_execute(
                    $deleteOfficialStmt
                )
            ) {
                throw new RuntimeException(
                    'Unable to delete the official profile.'
                );
            }

            if (
                mysqli_stmt_affected_rows(
                    $deleteOfficialStmt
                ) !== 1
            ) {
                throw new RuntimeException(
                    'The official profile was not deleted.'
                );
            }

            mysqli_stmt_close(
                $deleteOfficialStmt
            );

            $deleteUserSql = "
                DELETE FROM users

                WHERE userID = ?
                  AND role = 'official'
            ";

            $deleteUserStmt =
                mysqli_prepare(
                    $conn,
                    $deleteUserSql
                );

            if (!$deleteUserStmt) {
                throw new RuntimeException(
                    'Unable to prepare account deletion.'
                );
            }

            mysqli_stmt_bind_param(
                $deleteUserStmt,
                'i',
                $official['userID']
            );

            if (
                !mysqli_stmt_execute(
                    $deleteUserStmt
                )
            ) {
                throw new RuntimeException(
                    'Unable to delete the official account.'
                );
            }

            mysqli_stmt_close(
                $deleteUserStmt
            );

            mysqli_commit($conn);

            $_SESSION['success'] =
                'Official permanently deleted successfully.';

            header('Location: officials.php');
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
| Fetch officials
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        co.admNo,
        co.officialName,
        co.position,
        co.email,
        co.phone,
        co.clubNumber,
        co.isChair,

        c.clubName,

        u.username,
        u.status AS accountStatus

    FROM club_officials co

    INNER JOIN clubs c
        ON co.clubNumber = c.clubNumber

    INNER JOIN users u
        ON co.userID = u.userID

    ORDER BY
        u.status DESC,
        c.clubName ASC,
        co.officialName ASC
";

$result =
    mysqli_query($conn, $sql);

require_once '../includes/header.php';
?>

<div class="page-header">

    <div>

        <h1>Manage Club Officials</h1>

        <p>
            Register officials, manage their accounts and
            control system access.
        </p>

    </div>

    <a
        href="add_official.php"
        class="btn btn-primary"
    >
        Add Official
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
                    <th>Admission No.</th>
                    <th>Official</th>
                    <th>Club</th>
                    <th>Position</th>
                    <th>Username</th>
                    <th>Contact</th>
                    <th>Status</th>
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
                        $official =
                            mysqli_fetch_assoc($result)
                    ): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $official['admNo']
                                    ); ?>
                                </strong>
                            </td>

                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $official[
                                            'officialName'
                                        ]
                                    ); ?>
                                </strong>

                                <?php if (
                                    $official['isChair'] ===
                                    'Yes'
                                ): ?>

                                    <small class="table-subtext">
                                        Club chairperson
                                    </small>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $official['clubName']
                                ); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $official['position']
                                ); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $official['username']
                                ); ?>
                            </td>

                            <td>

                                <?= !empty(
                                    $official['email']
                                )
                                    ? htmlspecialchars(
                                        $official['email']
                                    )
                                    : 'No email'; ?>

                                <small class="table-subtext">

                                    <?= !empty(
                                        $official['phone']
                                    )
                                        ? htmlspecialchars(
                                            $official['phone']
                                        )
                                        : 'No phone'; ?>

                                </small>

                            </td>

                            <td>

                                <span
                                    class="badge <?= $official[
                                        'accountStatus'
                                    ] === 'Active'
                                        ? 'badge-success'
                                        : 'badge-secondary'; ?>"
                                >
                                    <?= htmlspecialchars(
                                        $official[
                                            'accountStatus'
                                        ]
                                    ); ?>
                                </span>

                            </td>

                            <td>

                                <div class="table-actions">

                                    <a
                                        href="edit_official.php?admNo=<?= urlencode(
                                            $official['admNo']
                                        ); ?>"
                                        class="btn btn-secondary btn-small"
                                    >
                                        Edit
                                    </a>

                                    <?php if (
                                        $official[
                                            'accountStatus'
                                        ] === 'Active'
                                    ): ?>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm(
                                                'Deactivate this official account?'
                                            );"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="deactivate"
                                            >

                                            <input
                                                type="hidden"
                                                name="admNo"
                                                value="<?= htmlspecialchars(
                                                    $official['admNo']
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
                                                'Reactivate this official account?'
                                            );"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="activate"
                                            >

                                            <input
                                                type="hidden"
                                                name="admNo"
                                                value="<?= htmlspecialchars(
                                                    $official['admNo']
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
                                            'Permanently delete this official? If requisition history exists, the account will be deactivated instead.'
                                        );"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="admNo"
                                            value="<?= htmlspecialchars(
                                                $official['admNo']
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
                            No club officials have been registered.
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