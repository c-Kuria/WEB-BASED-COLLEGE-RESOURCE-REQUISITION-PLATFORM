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
        co.admNo,
        co.officialName,
        co.position,
        co.email,
        co.phone,
        co.isChair,
        co.createdAt,
        c.clubName,
        u.username,
        u.status
    FROM club_officials co
    INNER JOIN users u
        ON co.userID = u.userID
    INNER JOIN clubs c
        ON co.clubNumber = c.clubNumber
    ORDER BY c.clubName, co.officialName
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die('Unable to retrieve officials: ' . mysqli_error($conn));
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">
        <div>
            <h1>Manage Club Officials</h1>
            <p>Register and manage authorized club representatives.</p>
        </div>

        <a href="add_official.php" class="btn btn-primary">
            Add Official
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
                        <th>Admission No.</th>
                        <th>Name</th>
                        <th>Club</th>
                        <th>Position</th>
                        <th>Username</th>
                        <th>Contact</th>
                        <th>Chairperson</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (mysqli_num_rows($result) > 0): ?>

                        <?php while ($official = mysqli_fetch_assoc($result)): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($official['admNo']); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($official['officialName']); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($official['clubName']); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($official['position']); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($official['username']); ?>
                                </td>

                                <td>
                                    <div>
                                        <?= htmlspecialchars($official['email'] ?: 'No email'); ?>
                                    </div>

                                    <small class="text-muted">
                                        <?= htmlspecialchars($official['phone'] ?: 'No phone'); ?>
                                    </small>
                                </td>

                                <td>
                                    <?php if ($official['isChair'] === 'Yes'): ?>
                                        <span class="badge badge-success">Yes</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">No</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($official['status'] === 'Active'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>

                                <td class="action-buttons">

                                    <a
                                        href="edit_official.php?admNo=<?= urlencode($official['admNo']); ?>"
                                        class="btn btn-small btn-secondary"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        action="delete_official.php"
                                        method="POST"
                                        class="inline-form"
                                        onsubmit="return confirm('Delete this club official and their login account?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="admNo"
                                            value="<?= htmlspecialchars($official['admNo']); ?>"
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
                                No club officials have been registered.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>