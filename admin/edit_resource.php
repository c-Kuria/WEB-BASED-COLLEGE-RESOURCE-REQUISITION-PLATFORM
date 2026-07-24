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

$resourceID = filter_input(
    INPUT_GET,
    'resourceID',
    FILTER_VALIDATE_INT
);

if (!$resourceID) {
    $_SESSION['error'] = 'Invalid resource selected.';
    header('Location: resources.php');
    exit();
}

$selectSql = "
    SELECT
        resourceID,
        resourceName,
        resourceCategory,
        resourceDescription,
        resourceQuantityTotal,
        resourceQuantityRemaining,
        status
    FROM resources
    WHERE resourceID = ?
    LIMIT 1
";

$selectStmt = mysqli_prepare(
    $conn,
    $selectSql
);

if (!$selectStmt) {
    die(
        'Unable to prepare resource query: ' .
        mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $selectStmt,
    'i',
    $resourceID
);

mysqli_stmt_execute($selectStmt);

$result = mysqli_stmt_get_result($selectStmt);
$resource = mysqli_fetch_assoc($result);

mysqli_stmt_close($selectStmt);

if (!$resource) {
    $_SESSION['error'] = 'Resource not found.';
    header('Location: resources.php');
    exit();
}

$resourceName = $resource['resourceName'];
$resourceCategory = $resource['resourceCategory'];
$resourceDescription =
    $resource['resourceDescription'] ?? '';
$resourceQuantityTotal =
    (int) $resource['resourceQuantityTotal'];
$resourceQuantityRemaining =
    (int) $resource['resourceQuantityRemaining'];
$status = $resource['status'];

$originalTotal =
    (int) $resource['resourceQuantityTotal'];

$originalRemaining =
    (int) $resource['resourceQuantityRemaining'];

$errors = [];

$categories = [
    'Transport',
    'Venue',
    'Equipment',
    'Finance',
    'ICT',
    'Other'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $resourceName = trim(
        $_POST['resourceName'] ?? ''
    );

    $resourceCategory = trim(
        $_POST['resourceCategory'] ?? ''
    );

    $resourceDescription = trim(
        $_POST['resourceDescription'] ?? ''
    );

    $resourceQuantityTotal = filter_input(
        INPUT_POST,
        'resourceQuantityTotal',
        FILTER_VALIDATE_INT
    );

    $status = $_POST['status'] ?? 'Active';

    if ($resourceName === '') {
        $errors[] = 'Resource name is required.';
    }

    if (strlen($resourceName) > 100) {
        $errors[] =
            'Resource name cannot exceed 100 characters.';
    }

    if (!in_array(
        $resourceCategory,
        $categories,
        true
    )) {
        $errors[] = 'Select a valid resource category.';
    }

    if (
        $resourceQuantityTotal === false ||
        $resourceQuantityTotal < 1
    ) {
        $errors[] =
            'Total quantity must be at least 1.';
    }

    if (!in_array(
        $status,
        ['Active', 'Inactive'],
        true
    )) {
        $errors[] = 'Invalid resource status.';
    }

    /*
     * Calculate how many units are currently in use.
     */
    $quantityInUse =
        $originalTotal - $originalRemaining;

    if ($resourceQuantityTotal < $quantityInUse) {
        $errors[] =
            'Total quantity cannot be lower than the number of units currently in use (' .
            $quantityInUse .
            ').';
    }

    /*
     * Prevent duplicate resource names.
     */
    if (empty($errors)) {

        $checkSql = "
            SELECT resourceID
            FROM resources
            WHERE resourceName = ?
              AND resourceID <> ?
            LIMIT 1
        ";

        $checkStmt = mysqli_prepare(
            $conn,
            $checkSql
        );

        if (!$checkStmt) {
            $errors[] =
                'Unable to validate the resource name.';
        } else {

            mysqli_stmt_bind_param(
                $checkStmt,
                'si',
                $resourceName,
                $resourceID
            );

            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);

            if (mysqli_stmt_num_rows($checkStmt) > 0) {
                $errors[] =
                    'Another resource already uses this name.';
            }

            mysqli_stmt_close($checkStmt);
        }
    }

    if (empty($errors)) {

        /*
         * Preserve the quantity currently in use.
         *
         * Example:
         * Original total = 10
         * Remaining = 7
         * In use = 3
         *
         * New total = 15
         * New remaining = 12
         */
        $resourceQuantityRemaining =
            $resourceQuantityTotal - $quantityInUse;

        $updateSql = "
            UPDATE resources
            SET
                resourceName = ?,
                resourceCategory = ?,
                resourceDescription = ?,
                resourceQuantityTotal = ?,
                resourceQuantityRemaining = ?,
                status = ?
            WHERE resourceID = ?
        ";

        $updateStmt = mysqli_prepare(
            $conn,
            $updateSql
        );

        if (!$updateStmt) {

            $errors[] =
                'Unable to prepare the resource update.';

        } else {

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

            if (mysqli_stmt_execute($updateStmt)) {

                $_SESSION['success'] =
                    'Resource updated successfully.';

                mysqli_stmt_close($updateStmt);

                header('Location: resources.php');
                exit();

            } else {

                $errors[] =
                    'Unable to update the resource: ' .
                    mysqli_stmt_error($updateStmt);

                mysqli_stmt_close($updateStmt);
            }
        }
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>Edit Resource</h1>
            <p>Update resource details and quantity.</p>
        </div>

        <a href="resources.php" class="btn btn-secondary">
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

    <div class="card form-card">

        <form method="POST" action="">

            <div class="form-grid">

                <div class="form-group">

                    <label for="resourceName">
                        Resource Name
                        <span class="required">*</span>
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
                        <span class="required">*</span>
                    </label>

                    <select
                        id="resourceCategory"
                        name="resourceCategory"
                        required
                    >

                        <option value="">
                            Select Category
                        </option>

                        <?php foreach ($categories as $category): ?>

                            <option
                                value="<?= htmlspecialchars($category); ?>"
                                <?= $resourceCategory === $category
                                    ? 'selected'
                                    : ''; ?>
                            >
                                <?= htmlspecialchars($category); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label for="resourceQuantityTotal">
                        Total Quantity
                        <span class="required">*</span>
                    </label>

                    <input
                        type="number"
                        id="resourceQuantityTotal"
                        name="resourceQuantityTotal"
                        min="1"
                        value="<?= (int) $resourceQuantityTotal; ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>
                        Currently Remaining
                    </label>

                    <input
                        type="text"
                        value="<?= (int) $resourceQuantityRemaining; ?>"
                        disabled
                    >

                    <small class="text-muted">
                        Remaining quantity is adjusted automatically
                        when the total quantity changes.
                    </small>

                </div>

                <div class="form-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
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

                <div class="form-group full-width">

                    <label for="resourceDescription">
                        Description
                    </label>

                    <textarea
                        id="resourceDescription"
                        name="resourceDescription"
                        rows="5"
                    ><?= htmlspecialchars(
                        $resourceDescription
                    ); ?></textarea>

                </div>

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Update Resource
                </button>

                <a
                    href="resources.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>