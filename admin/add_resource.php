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

$resourceName = '';
$resourceCategory = '';
$resourceDescription = '';
$resourceQuantityTotal = 1;
$status = 'Active';

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
     * Prevent duplicate resource names.
     */
    if (empty($errors)) {

        $checkSql = "
            SELECT resourceID
            FROM resources
            WHERE resourceName = ?
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
                's',
                $resourceName
            );

            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);

            if (mysqli_stmt_num_rows($checkStmt) > 0) {
                $errors[] =
                    'A resource with this name already exists.';
            }

            mysqli_stmt_close($checkStmt);
        }
    }

    if (empty($errors)) {

        /*
         * A newly created resource starts with all
         * units available.
         */
        $resourceQuantityRemaining =
            $resourceQuantityTotal;

        $sql = "
            INSERT INTO resources (
                resourceName,
                resourceCategory,
                resourceDescription,
                resourceQuantityTotal,
                resourceQuantityRemaining,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            $errors[] =
                'Unable to prepare the resource record.';
        } else {

            mysqli_stmt_bind_param(
                $stmt,
                'sssiis',
                $resourceName,
                $resourceCategory,
                $resourceDescription,
                $resourceQuantityTotal,
                $resourceQuantityRemaining,
                $status
            );

            if (mysqli_stmt_execute($stmt)) {

                $_SESSION['success'] =
                    'Resource created successfully.';

                mysqli_stmt_close($stmt);

                header('Location: resources.php');
                exit();

            } else {

                $errors[] =
                    'Unable to create the resource: ' .
                    mysqli_stmt_error($stmt);

                mysqli_stmt_close($stmt);
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
            <h1>Add Resource</h1>
            <p>Register a new college resource.</p>
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
                    Save Resource
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