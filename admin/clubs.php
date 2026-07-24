<?php
require_once '../includes/session.php';
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$sql = "
    SELECT
        c.clubNumber,
        c.clubName,
        c.clubDescription,
        c.createdAt,
        COUNT(co.admNo) AS totalOfficials,
        MAX(
            CASE
                WHEN co.isChair = 'Yes'
                THEN co.officialName
            END
        ) AS chairpersonName
    FROM clubs c
    LEFT JOIN club_officials co
        ON c.clubNumber = co.clubNumber
    GROUP BY
        c.clubNumber,
        c.clubName,
        c.clubDescription,
        c.createdAt
    ORDER BY c.clubName ASC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die('Unable to retrieve clubs: ' . mysqli_error($conn));
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">
        <div>
            <h1>Manage Clubs</h1>
            <p>Create and manage registered college clubs.</p>
        </div>

        <a href="add_club.php" class="btn btn-primary">
            Add Club
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
                        <th>Club No.</th>
                        <th>Club Name</th>
                        <th>Description</th>
                        <th>Chairperson</th>
                        <th>Officials</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (mysqli_num_rows($result) > 0): ?>

                        <?php while ($club = mysqli_fetch_assoc($result)): ?>

                            <tr>

                                <td>
                                    <?= (int) $club['clubNumber']; ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($club['clubName']); ?>
                                </td>

                                <td>
                                    <?php
                                    $description = $club['clubDescription'] ?? '';

                                    echo $description !== ''
                                        ? htmlspecialchars($description)
                                        : '<span class="text-muted">No description</span>';
                                    ?>
                                </td>

                                <td>
                                    <?php if (!empty($club['chairpersonName'])): ?>
                                        <?= htmlspecialchars($club['chairpersonName']); ?>
                                    <?php else: ?>
                                        <span class="badge badge-warning">
                                            Not assigned
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= (int) $club['totalOfficials']; ?>
                                </td>

                                <td>
                                    <?= date(
                                        'd M Y',
                                        strtotime($club['createdAt'])
                                    ); ?>
                                </td>

                                <td class="action-buttons">

                                    <a
                                        href="edit_club.php?id=<?= (int) $club['clubNumber']; ?>"
                                        class="btn btn-small btn-secondary"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        action="delete_club.php"
                                        method="POST"
                                        class="inline-form"
                                        onsubmit="return confirm('Are you sure you want to delete this club?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="clubNumber"
                                            value="<?= (int) $club['clubNumber']; ?>"
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
                            <td colspan="7" class="empty-state">
                                No clubs have been registered.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?> 