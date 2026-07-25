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

$pageTitle = 'Edit Resource';
$errors = [];

/*
|--------------------------------------------------------------------------
| Validate resource ID
|--------------------------------------------------------------------------
*/

$resourceID =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $resourceID =
        filter_input(
            INPUT_POST,
            'resourceID',
            FILTER_VALIDATE_INT
        );
}

if (!$resourceID || $resourceID <= 0) {

    $_SESSION['error'] =
        'Invalid resource selected.';

    header('Location: resources.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Fetch resource
|--------------------------------------------------------------------------
*/

function getResource(
    mysqli $conn,
    int $resourceID
): ?array {

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

        WHERE resourceID = ?

        LIMIT 1
    ";

    $stmt =
        mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'i',
        $resourceID
    );

    mysqli_stmt_execute($stmt);

    $result =
        mysqli_stmt_get_result($stmt);

    $resource =
        mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return $resource ?: null;
}

$resource =
    getResource(
        $conn,
        $resourceID
    );

if (!$resource) {

    $_SESSION['error'] =
        'The selected resource was not found.';

    header('Location: resources.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Default form values
|--------------------------------------------------------------------------
*/

$resourceName =
    $resource['resourceName'];

$resourceCategory =
    $resource['resourceCategory'];

$resourceDescription =
    $resource['resourceDescription'] ?? '';

$resourceQuantityTotal =
    (int) $resource[
        'resourceQuantityTotal'
    ];

$resourceQuantityRemaining =
    (int) $resource[
        'resourceQuantityRemaining'
    ];

$status =
    $resource['status'];

/*
|--------------------------------------------------------------------------
| Update resource
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $resourceName =
        trim($_POST['resourceName'] ?? '');

    $resourceCategory =
        trim($_POST['resourceCategory'] ?? '');

    $resourceDescription =
        trim(
            $_POST['resourceDescription'] ?? ''
        );

    $resourceQuantityTotal =
        filter_input(
            INPUT_POST,
            'resourceQuantityTotal',
            FILTER_VALIDATE_INT
        );

    $resourceQuantityRemaining =
        filter_input(
            INPUT_POST,
            'resourceQuantityRemaining',
            FILTER_VALIDATE_INT
        );

    $status =
        trim($_POST['status'] ?? '');

    $allowedCategories = [
        'Transport',
        'Venue',
        'Equipment',
        'Finance',
        'ICT',
        'Other'
    ];

    $allowedStatuses = [
        'Active',
        'Inactive'
    ];

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($resourceName === '') {

        $errors[] =
            'Resource name is required.';

    } elseif (
        mb_strlen($resourceName) > 100
    ) {

        $errors[] =
            'Resource name must not exceed 100 characters.';
    }

    if (
        !in_array(
            $resourceCategory,
            $allowedCategories,
            true
        )
    ) {
        $errors[] =
            'Select a valid resource category.';
    }

    if (
        $resourceDescription !== '' &&
        mb_strlen($resourceDescription) > 2000
    ) {
        $errors[] =
            'Resource description must not exceed 2000 characters.';
    }

    if (
        $resourceQuantityTotal === false ||
        $resourceQuantityTotal < 1
    ) {
        $errors[] =
            'Total quantity must be at least 1.';
    }

    if (
        $resourceQuantityRemaining === false ||
        $resourceQuantityRemaining < 0
    ) {
        $errors[] =
            'Remaining quantity cannot be negative.';
    }

    if (
        $resourceQuantityTotal !== false &&
        $resourceQuantityRemaining !== false &&
        $resourceQuantityRemaining >
        $resourceQuantityTotal
    ) {
        $errors[] =
            'Remaining quantity cannot exceed the total quantity.';
    }

    if (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {
        $errors[] =
            'Select a valid resource status.';
    }

    /*
    |--------------------------------------------------------------------------
    | Check duplicate resource name
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $duplicateSql = "
            SELECT resourceID

            FROM resources

            WHERE resourceName = ?
              AND resourceID <> ?

            LIMIT 1
        ";

        $duplicateStmt =
            mysqli_prepare(
                $conn,
                $duplicateSql
            );

        if (!$duplicateStmt) {

            $errors[] =
                'Unable to validate the resource name.';

        } else {

            mysqli_stmt_bind_param(
                $duplicateStmt,
                'si',
                $resourceName,
                $resourceID
            );

            mysqli_stmt_execute(
                $duplicateStmt
            );

            $duplicateResult =
                mysqli_stmt_get_result(
                    $duplicateStmt
                );

            if (
                mysqli_fetch_assoc(
                    $duplicateResult
                )
            ) {
                $errors[] =
                    'Another resource already uses that name.';
            }

            mysqli_stmt_close(
                $duplicateStmt
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save changes
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        mysqli_begin_transaction($conn);

        try {

            $updateSql = "
                UPDATE resources

                SET
                    resourceName = ?,
                    resourceCategory = ?,
                    resourceDescription =
                        NULLIF(?, ''),
                    resourceQuantityTotal = ?,
                    resourceQuantityRemaining = ?,
                    status = ?

                WHERE resourceID = ?
            ";

            $updateStmt =
                mysqli_prepare(
                    $conn,
                    $updateSql
                );

            if (!$updateStmt) {
                throw new RuntimeException(
                    'Unable to prepare the resource update.'
                );
            }

            mysqli_stmt_bind_param(
                $updateStmt,
                'sssiisi',
                $resourceName,
                $resourceCategory,
                $resourceDescription,
                $resourceQuantityTotal,
                $resourceQuantityRemaining,
                $status,
                $resourceID
            );

            if (
                !mysqli_stmt_execute(
                    $updateStmt
                )
            ) {
                throw new RuntimeException(
                    'Unable to update the resource.'
                );
            }

            mysqli_stmt_close(
                $updateStmt
            );

            mysqli_commit($conn);

            $_SESSION['success'] =
                'Resource updated successfully.';

            header('Location: resources.php');
            exit();

        } catch (Throwable $exception) {

            mysqli_rollback($conn);

            $errors[] =
                $exception->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="page-header">

    <div>

        <h1>Edit Resource</h1>

        <p>
            Update resource information, quantities and
            account status.
        </p>

    </div>

    <a
        href="resources.php"
        class="btn btn-secondary"
    >
        Back to Resources
    </a>

</div>

<?php if (!empty($errors)): ?>

    <div class="alert alert-danger">

        <?php foreach ($errors as $error): ?>

            <p>
                <?= htmlspecialchars($error); ?>
            </p>

        <?php endforeach; ?>

    </div>

<?php endif; ?>

<div class="card">

    <div class="section-header">

        <h2>Resource Information</h2>

        <p>
            Fields marked as required must be completed.
        </p>

    </div>

    <form method="POST">

        <input
            type="hidden"
            name="resourceID"
            value="<?= (int) $resourceID; ?>"
        >

        <div class="form-grid">

            <div class="form-group">

                <label for="resourceName">
                    Resource Name
                </label>

                <input
                    type="text"
                    id="resourceName"
                    name="resourceName"
                    maxlength="100"
                    value="<?= htmlspecialchars(
                        $resourceName
                    ); ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label for="resourceCategory">
                    Resource Category
                </label>

                <select
                    id="resourceCategory"
                    name="resourceCategory"
                    required
                >

                    <option value="">
                        Select category
                    </option>

                    <?php
                    $categories = [
                        'Transport',
                        'Venue',
                        'Equipment',
                        'Finance',
                        'ICT',
                        'Other'
                    ];
                    ?>

                    <?php foreach (
                        $categories as $category
                    ): ?>

                        <option
                            value="<?= htmlspecialchars(
                                $category
                            ); ?>"
                            <?= $resourceCategory ===
                            $category
                                ? 'selected'
                                : ''; ?>
                        >
                            <?= htmlspecialchars(
                                $category
                            ); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label for="resourceQuantityTotal">
                    Total Quantity
                </label>

                <input
                    type="number"
                    id="resourceQuantityTotal"
                    name="resourceQuantityTotal"
                    min="1"
                    value="<?= htmlspecialchars(
                        (string) $resourceQuantityTotal
                    ); ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label for="resourceQuantityRemaining">
                    Remaining Quantity
                </label>

                <input
                    type="number"
                    id="resourceQuantityRemaining"
                    name="resourceQuantityRemaining"
                    min="0"
                    value="<?= htmlspecialchars(
                        (string) $resourceQuantityRemaining
                    ); ?>"
                    required
                >

                <small class="form-help">
                    This value cannot exceed the total quantity.
                </small>

            </div>

            <div class="form-group">

                <label for="status">
                    Resource Status
                </label>

                <select
                    id="status"
                    name="status"
                    required
                >

                    <option
                        value="Active"
                        <?= $status === 'Active'
                            ? 'selected'
                            : ''; ?>
                    >
                        Active
                    </option>

                    <option
                        value="Inactive"
                        <?= $status === 'Inactive'
                            ? 'selected'
                            : ''; ?>
                    >
                        Inactive
                    </option>

                </select>

            </div>

            <div class="form-group">

                <label>
                    Created On
                </label>

                <input
                    type="text"
                    value="<?= date(
                        'd M Y, H:i',
                        strtotime(
                            $resource['createdAt']
                        )
                    ); ?>"
                    disabled
                >

            </div>

            <div class="form-group full-width">

                <label for="resourceDescription">
                    Description
                </label>

                <textarea
                    id="resourceDescription"
                    name="resourceDescription"
                    maxlength="2000"
                    placeholder="Describe the resource..."
                ><?= htmlspecialchars(
                    $resourceDescription
                ); ?></textarea>

            </div>

        </div>

        <div class="form-actions">

            <a
                href="resources.php"
                class="btn btn-secondary"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Save Resource
            </button>

        </div>

    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const totalInput =
        document.getElementById(
            'resourceQuantityTotal'
        );

    const remainingInput =
        document.getElementById(
            'resourceQuantityRemaining'
        );

    function validateQuantities() {

        if (
            !totalInput ||
            !remainingInput
        ) {
            return;
        }

        const total =
            Number(totalInput.value);

        const remaining =
            Number(remainingInput.value);

        if (
            Number.isFinite(total) &&
            Number.isFinite(remaining) &&
            remaining > total
        ) {
            remainingInput.setCustomValidity(
                'Remaining quantity cannot exceed total quantity.'
            );

        } else {

            remainingInput.setCustomValidity('');
        }
    }

    if (totalInput) {
        totalInput.addEventListener(
            'input',
            validateQuantities
        );
    }

    if (remainingInput) {
        remainingInput.addEventListener(
            'input',
            validateQuantities
        );
    }
});
</script>

<?php
require_once '../includes/footer.php';
?>