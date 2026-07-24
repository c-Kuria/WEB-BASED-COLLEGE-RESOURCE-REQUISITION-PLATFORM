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
        resourceID,
        resourceName,
        resourceCategory,
        resourceDescription,
        resourceQuantityTotal,
        resourceQuantityRemaining,
        status,
        createdAt
    FROM resources
    ORDER BY resourceCategory, resourceName
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die(
        'Unable to retrieve resources: ' .
        mysqli_error($conn)
    );
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>Manage Resources</h1>
            <p>
                Register, update, and monitor college resources.
            </p>
        </div>

        <a href="add_resource.php" class="btn btn-primary">
            Add Resource
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
                        <th>ID</th>
                        <th>Resource</th>
                        <th>Category</th>
                        <th>Total Quantity</th>
                        <th>Remaining</th>
                        <th>Availability</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (mysqli_num_rows($result) > 0): ?>

                        <?php while ($resource = mysqli_fetch_assoc($result)): ?>

                            <?php
                            $totalQuantity =
                                (int) $resource['resourceQuantityTotal'];

                            $remainingQuantity =
                                (int) $resource['resourceQuantityRemaining'];

                            if ($remainingQuantity <= 0) {
                                $availabilityLabel = 'Unavailable';
                                $availabilityClass = 'badge-danger';
                            } elseif ($remainingQuantity < $totalQuantity) {
                                $availabilityLabel = 'Partially Available';
                                $availabilityClass = 'badge-warning';
                            } else {
                                $availabilityLabel = 'Available';
                                $availabilityClass = 'badge-success';
                            }
                            ?>

                            <tr>

                                <td>
                                    <?= (int) $resource['resourceID']; ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $resource['resourceName']
                                        ); ?>
                                    </strong>

                                    <?php if (
                                        !empty(
                                            $resource['resourceDescription']
                                        )
                                    ): ?>

                                        <br>

                                        <small class="text-muted">
                                            <?= htmlspecialchars(
                                                $resource[
                                                    'resourceDescription'
                                                ]
                                            ); ?>
                                        </small>

                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $resource['resourceCategory']
                                    ); ?>
                                </td>

                                <td>
                                    <?= $totalQuantity; ?>
                                </td>

                                <td>
                                    <?= $remainingQuantity; ?>
                                </td>

                                <td>
                                    <span
                                        class="badge <?= $availabilityClass; ?>"
                                    >
                                        <?= $availabilityLabel; ?>
                                    </span>
                                </td>

                                <td>

                                    <?php if (
                                        $resource['status'] === 'Active'
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

                                <td>
                                    <?= date(
                                        'd M Y',
                                        strtotime(
                                            $resource['createdAt']
                                        )
                                    ); ?>
                                </td>

                                <td class="action-buttons">

                                    <a
                                        href="edit_resource.php?resourceID=<?= (int) $resource['resourceID']; ?>"
                                        class="btn btn-small btn-secondary"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="delete_resource.php"
                                        class="inline-form"
                                        onsubmit="return confirm('Delete this resource?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="resourceID"
                                            value="<?= (int) $resource['resourceID']; ?>"
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
                            <td colspan="9" class="empty-state">
                                No resources have been registered.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>