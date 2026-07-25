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

$pageTitle = 'Manage Clubs';
$errors = [];

/*
|--------------------------------------------------------------------------
| Process club actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        trim($_POST['action'] ?? '');

    $clubNumber =
        filter_input(
            INPUT_POST,
            'clubNumber',
            FILTER_VALIDATE_INT
        );

    $allowedActions = [
        'activate',
        'deactivate',
        'delete'
    ];

    if (
        !$clubNumber ||
        $clubNumber <= 0
    ) {
        $errors[] =
            'Invalid club selected.';
    }

    if (
        !in_array(
            $action,
            $allowedActions,
            true
        )
    ) {
        $errors[] =
            'Invalid club action selected.';
    }

    if (empty($errors)) {

        mysqli_begin_transaction($conn);

        try {

            /*
            |--------------------------------------------------------------------------
            | Lock and fetch club
            |--------------------------------------------------------------------------
            */

            $clubSql = "
                SELECT
                    clubNumber,
                    clubName,
                    status

                FROM clubs

                WHERE clubNumber = ?

                FOR UPDATE
            ";

            $clubStmt =
                mysqli_prepare(
                    $conn,
                    $clubSql
                );

            if (!$clubStmt) {
                throw new RuntimeException(
                    'Unable to prepare the club lookup.'
                );
            }

            mysqli_stmt_bind_param(
                $clubStmt,
                'i',
                $clubNumber
            );

            mysqli_stmt_execute(
                $clubStmt
            );

            $clubResult =
                mysqli_stmt_get_result(
                    $clubStmt
                );

            $club =
                mysqli_fetch_assoc(
                    $clubResult
                );

            mysqli_stmt_close(
                $clubStmt
            );

            if (!$club) {
                throw new RuntimeException(
                    'The selected club was not found.'
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

                $updateSql = "
                    UPDATE clubs

                    SET status = ?

                    WHERE clubNumber = ?
                ";

                $updateStmt =
                    mysqli_prepare(
                        $conn,
                        $updateSql
                    );

                if (!$updateStmt) {
                    throw new RuntimeException(
                        'Unable to prepare the club status update.'
                    );
                }

                mysqli_stmt_bind_param(
                    $updateStmt,
                    'si',
                    $newStatus,
                    $clubNumber
                );

                if (
                    !mysqli_stmt_execute(
                        $updateStmt
                    )
                ) {
                    throw new RuntimeException(
                        'Unable to update the club status.'
                    );
                }

                mysqli_stmt_close(
                    $updateStmt
                );

                mysqli_commit($conn);

                $_SESSION['success'] =
                    $newStatus === 'Active'
                        ? 'Club activated successfully.'
                        : 'Club deactivated successfully.';

                header('Location: clubs.php');
                exit();
            }

            /*
            |--------------------------------------------------------------------------
            | Check whether club has officials
            |--------------------------------------------------------------------------
            */

            $usageSql = "
                SELECT COUNT(*) AS officialCount

                FROM club_officials

                WHERE clubNumber = ?
            ";

            $usageStmt =
                mysqli_prepare(
                    $conn,
                    $usageSql
                );

            if (!$usageStmt) {
                throw new RuntimeException(
                    'Unable to check club usage.'
                );
            }

            mysqli_stmt_bind_param(
                $usageStmt,
                'i',
                $clubNumber
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

            $officialCount =
                (int) (
                    $usage['officialCount'] ?? 0
                );

            /*
            |--------------------------------------------------------------------------
            | Deactivate referenced club
            |--------------------------------------------------------------------------
            */

            if ($officialCount > 0) {

                $deactivateSql = "
                    UPDATE clubs

                    SET status = 'Inactive'

                    WHERE clubNumber = ?
                ";

                $deactivateStmt =
                    mysqli_prepare(
                        $conn,
                        $deactivateSql
                    );

                if (!$deactivateStmt) {
                    throw new RuntimeException(
                        'Unable to prepare club deactivation.'
                    );
                }

                mysqli_stmt_bind_param(
                    $deactivateStmt,
                    'i',
                    $clubNumber
                );

                if (
                    !mysqli_stmt_execute(
                        $deactivateStmt
                    )
                ) {
                    throw new RuntimeException(
                        'Unable to deactivate the club.'
                    );
                }

                mysqli_stmt_close(
                    $deactivateStmt
                );

                mysqli_commit($conn);

                $_SESSION['success'] =
                    'The club has related official records, so it was deactivated instead of permanently deleted.';

                header('Location: clubs.php');
                exit();
            }

            /*
            |--------------------------------------------------------------------------
            | Permanently delete unused club
            |--------------------------------------------------------------------------
            */

            $deleteSql = "
                DELETE FROM clubs

                WHERE clubNumber = ?
            ";

            $deleteStmt =
                mysqli_prepare(
                    $conn,
                    $deleteSql
                );

            if (!$deleteStmt) {
                throw new RuntimeException(
                    'Unable to prepare club deletion.'
                );
            }

            mysqli_stmt_bind_param(
                $deleteStmt,
                'i',
                $clubNumber
            );

            if (
                !mysqli_stmt_execute(
                    $deleteStmt
                )
            ) {
                throw new RuntimeException(
                    'Unable to delete the club.'
                );
            }

            if (
                mysqli_stmt_affected_rows(
                    $deleteStmt
                ) !== 1
            ) {
                throw new RuntimeException(
                    'The club was not deleted.'
                );
            }

            mysqli_stmt_close(
                $deleteStmt
            );

            mysqli_commit($conn);

            $_SESSION['success'] =
                'Club permanently deleted successfully.';

            header('Location: clubs.php');
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
| Fetch clubs
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        c.clubNumber,
        c.clubName,
        c.clubDescription,
        c.status,
        c.createdAt,

        COUNT(co.admNo) AS officialCount

    FROM clubs c

    LEFT JOIN club_officials co
        ON c.clubNumber = co.clubNumber

    GROUP BY
        c.clubNumber,
        c.clubName,
        c.clubDescription,
        c.status,
        c.createdAt

    ORDER BY
        c.status DESC,
        c.clubName ASC
";

$result =
    mysqli_query($conn, $sql);

require_once '../includes/header.php';
?>

<div class="page-header">

    <div>

        <h1>Manage Clubs</h1>

        <p>
            Register clubs, manage their status and maintain
            club information.
        </p>

    </div>

    <a
        href="add_club.php"
        class="btn btn-primary"
    >
        Add Club
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
                    <th>Club Number</th>
                    <th>Club Name</th>
                    <th>Description</th>
                    <th>Officials</th>
                    <th>Status</th>
                    <th>Created</th>
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
                        $club =
                            mysqli_fetch_assoc($result)
                    ): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= (int) $club[
                                        'clubNumber'
                                    ]; ?>
                                </strong>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $club['clubName']
                                    ); ?>
                                </strong>
                            </td>

                            <td>

                                <?= !empty(
                                    $club['clubDescription']
                                )
                                    ? htmlspecialchars(
                                        $club[
                                            'clubDescription'
                                        ]
                                    )
                                    : 'No description'; ?>

                            </td>

                            <td>
                                <?= (int) $club[
                                    'officialCount'
                                ]; ?>
                            </td>

                            <td>

                                <span
                                    class="badge <?= $club[
                                        'status'
                                    ] === 'Active'
                                        ? 'badge-success'
                                        : 'badge-secondary'; ?>"
                                >
                                    <?= htmlspecialchars(
                                        $club['status']
                                    ); ?>
                                </span>

                            </td>

                            <td>
                                <?= date(
                                    'd M Y',
                                    strtotime(
                                        $club['createdAt']
                                    )
                                ); ?>
                            </td>

                            <td>

                                <div class="table-actions">

                                    <a
                                        href="edit_club.php?id=<?= (int) $club[
                                            'clubNumber'
                                        ]; ?>"
                                        class="btn btn-secondary btn-small"
                                    >
                                        Edit
                                    </a>

                                    <?php if (
                                        $club['status'] ===
                                        'Active'
                                    ): ?>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm(
                                                'Deactivate this club?'
                                            );"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="deactivate"
                                            >

                                            <input
                                                type="hidden"
                                                name="clubNumber"
                                                value="<?= (int) $club[
                                                    'clubNumber'
                                                ]; ?>"
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
                                                'Reactivate this club?'
                                            );"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="activate"
                                            >

                                            <input
                                                type="hidden"
                                                name="clubNumber"
                                                value="<?= (int) $club[
                                                    'clubNumber'
                                                ]; ?>"
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
                                            'Permanently delete this club? If it has related records, it will be deactivated instead.'
                                        );"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="clubNumber"
                                            value="<?= (int) $club[
                                                'clubNumber'
                                            ]; ?>"
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
                            colspan="7"
                            class="empty-state"
                        >
                            No clubs have been registered.
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