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

$pageTitle = 'Manage Resources';
$errors = [];

/*
|--------------------------------------------------------------------------
| Process resource actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        trim($_POST['action'] ?? '');

    $resourceID =
        filter_input(
            INPUT_POST,
            'resourceID',
            FILTER_VALIDATE_INT
        );

    $allowedActions = [
        'activate',
        'deactivate',
        'delete'
    ];

    if (
        !$resourceID ||
        $resourceID <= 0
    ) {
        $errors[] =
            'Invalid resource selected.';
    }

    if (
        !in_array(
            $action,
            $allowedActions,
            true
        )
    ) {
        $errors[] =
            'Invalid resource action selected.';
    }

    if (empty($errors)) {

        mysqli_begin_transaction($conn);

        try {

            /*
            |--------------------------------------------------------------------------
            | Lock and fetch resource
            |--------------------------------------------------------------------------
            */

            $resourceSql = "
                SELECT
                    resourceID,
                    resourceName,
                    status

                FROM resources

                WHERE resourceID = ?

                FOR UPDATE
            ";

            $resourceStmt =
                mysqli_prepare(
                    $conn,
                    $resourceSql
                );

            if (!$resourceStmt) {
                throw new RuntimeException(
                    'Unable to prepare the resource lookup.'
                );
            }

            mysqli_stmt_bind_param(
                $resourceStmt,
                'i',
                $resourceID
            );

            mysqli_stmt_execute(
                $resourceStmt
            );

            $resourceResult =
                mysqli_stmt_get_result(
                    $resourceStmt
                );

            $resource =
                mysqli_fetch_assoc(
                    $resourceResult
                );

            mysqli_stmt_close(
                $resourceStmt
            );

            if (!$resource) {
                throw new RuntimeException(
                    'The selected resource was not found.'
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
                    UPDATE resources

                    SET status = ?

                    WHERE resourceID = ?
                ";

                $updateStmt =
                    mysqli_prepare(
                        $conn,
                        $updateSql
                    );

                if (!$updateStmt) {
                    throw new RuntimeException(
                        'Unable to prepare the resource status update.'
                    );
                }

                mysqli_stmt_bind_param(
                    $updateStmt,
                    'si',
                    $newStatus,
                    $resourceID
                );

                if (
                    !mysqli_stmt_execute(
                        $updateStmt
                    )
                ) {
                    throw new RuntimeException(
                        'Unable to update the resource status.'
                    );
                }

                mysqli_stmt_close(
                    $updateStmt
                );

                mysqli_commit($conn);

                $_SESSION['success'] =
                    $newStatus === 'Active'
                        ? 'Resource activated successfully.'
                        : 'Resource deactivated successfully.';

                header('Location: resources.php');
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

                WHERE resourceID = ?
            ";

            $usageStmt =
                mysqli_prepare(
                    $conn,
                    $usageSql
                );

            if (!$usageStmt) {
                throw new RuntimeException(
                    'Unable to check resource usage.'
                );
            }

            mysqli_stmt_bind_param(
                $usageStmt,
                'i',
                $resourceID
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
            | Deactivate referenced resource
            |--------------------------------------------------------------------------
            */

            if ($requisitionCount > 0) {

                $deactivateSql = "
                    UPDATE resources

                    SET status = 'Inactive'

                    WHERE resourceID = ?
                ";

                $deactivateStmt =
                    mysqli_prepare(
                        $conn,
                        $deactivateSql
                    );

                if (!$deactivateStmt) {
                    throw new RuntimeException(
                        'Unable to prepare resource deactivation.'
                    );
                }

                mysqli_stmt_bind_param(
                    $deactivateStmt,
                    'i',
                    $resourceID
                );

                if (
                    !mysqli_stmt_execute(
                        $deactivateStmt
                    )
                ) {
                    throw new RuntimeException(
                        'Unable to deactivate the resource.'
                    );
                }

                mysqli_stmt_close(
                    $deactivateStmt
                );

                mysqli_commit($conn);

                $_SESSION['success'] =
                    'The resource has requisition history, so it was deactivated instead of permanently deleted.';

                header('Location: resources.php');
                exit();
            }

            /*
            |--------------------------------------------------------------------------
            | Permanently delete unused resource
            |--------------------------------------------------------------------------
            */

            $deleteSql = "
                DELETE FROM resources

                WHERE resourceID = ?
            ";

            $deleteStmt =
                mysqli_prepare(
                    $conn,
                    $deleteSql
                );

            if (!$deleteStmt) {
                throw new RuntimeException(
                    'Unable to prepare resource deletion.'
                );
            }

            mysqli_stmt_bind_param(
                $deleteStmt,
                'i',
                $resourceID
            );

            if (
                !mysqli_stmt_execute(
                    $deleteStmt
                )
            ) {
                throw new RuntimeException(
                    'Unable to delete the resource.'
                );
            }

            if (
                mysqli_stmt_affected_rows(
                    $deleteStmt
                ) !== 1
            ) {
                throw new RuntimeException(
                    'The resource was not deleted.'
                );
            }

            mysqli_stmt_close(
                $deleteStmt
            );

            mysqli_commit($conn);

            $_SESSION['success'] =
                'Resource permanently deleted successfully.';

            header('Location: resources.php');
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
| Fetch resources
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        resourceID,
        resourceName,
        resourceCategory,
        resourceDescription,
        resourceQuantityTotal,
        resourceQuantityRemaining,
        status,
        createdAt

    FROM resources

    ORDER BY
        status DESC,
        resourceCategory ASC,
        resourceName ASC
";

$result =
    mysqli_query($conn, $sql);

require_once '../includes/header.php';
?>

<div class="page-header">

    <div>

        <h1>Manage Resources</h1>

        <p>
            Register institutional resources, manage quantities
            and control availability.
        </p>

    </div>

    <a
        href="add_resource.php"
        class="btn btn-primary"
    >
        Add Resource
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
                    <th>ID</th>
                    <th>Resource</th>
                    <th>Category</th>
                    <th>Total</th>
                    <th>Remaining</th>
                    <th>Availability</th>
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
                        $resource =
                            mysqli_fetch_assoc($result)
                    ): ?>

                        <?php

                        $total =
                            (int) $resource[
                                'resourceQuantityTotal'
                            ];

                        $remaining =
                            (int) $resource[
                                'resourceQuantityRemaining'
                            ];

                        if (
                            $resource['status'] ===
                            'Inactive'
                        ) {

                            $availabilityText =
                                'Inactive';

                            $availabilityClass =
                                'badge-secondary';

                        } elseif ($remaining <= 0) {

                            $availabilityText =
                                'Unavailable';

                            $availabilityClass =
                                'badge-danger';

                        } elseif ($remaining < $total) {

                            $availabilityText =
                                'Partly Available';

                            $availabilityClass =
                                'badge-warning';

                        } else {

                            $availabilityText =
                                'Available';

                            $availabilityClass =
                                'badge-success';
                        }

                        ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= (int) $resource[
                                        'resourceID'
                                    ]; ?>
                                </strong>
                            </td>

                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $resource[
                                            'resourceName'
                                        ]
                                    ); ?>
                                </strong>

                                <small class="table-subtext">

                                    <?= !empty(
                                        $resource[
                                            'resourceDescription'
                                        ]
                                    )
                                        ? htmlspecialchars(
                                            $resource[
                                                'resourceDescription'
                                            ]
                                        )
                                        : 'No description'; ?>

                                </small>

                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $resource[
                                        'resourceCategory'
                                    ]
                                ); ?>
                            </td>

                            <td>
                                <?= $total; ?>
                            </td>

                            <td>
                                <?= $remaining; ?>
                            </td>

                            <td>

                                <span
                                    class="badge <?= $availabilityClass; ?>"
                                >
                                    <?= $availabilityText; ?>
                                </span>

                            </td>

                            <td>

                                <span
                                    class="badge <?= $resource[
                                        'status'
                                    ] === 'Active'
                                        ? 'badge-success'
                                        : 'badge-secondary'; ?>"
                                >
                                    <?= htmlspecialchars(
                                        $resource['status']
                                    ); ?>
                                </span>

                            </td>

                            <td>

                                <div class="table-actions">

                                    <a
                                        href="edit_resource.php?id=<?= (int) $resource[
                                            'resourceID'
                                        ]; ?>"
                                        class="btn btn-secondary btn-small"
                                    >
                                        Edit
                                    </a>

                                    <?php if (
                                        $resource['status'] ===
                                        'Active'
                                    ): ?>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm(
                                                'Deactivate this resource?'
                                            );"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="deactivate"
                                            >

                                            <input
                                                type="hidden"
                                                name="resourceID"
                                                value="<?= (int) $resource[
                                                    'resourceID'
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
                                                'Reactivate this resource?'
                                            );"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="activate"
                                            >

                                            <input
                                                type="hidden"
                                                name="resourceID"
                                                value="<?= (int) $resource[
                                                    'resourceID'
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
                                            'Permanently delete this resource? If requisition history exists, it will be deactivated instead.'
                                        );"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="resourceID"
                                            value="<?= (int) $resource[
                                                'resourceID'
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
                            colspan="8"
                            class="empty-state"
                        >
                            No resources have been registered.
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