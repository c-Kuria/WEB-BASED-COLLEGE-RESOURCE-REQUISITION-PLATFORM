<?php
include '../includes/session.php';

if ($_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

include '../config/db.php';
include '../includes/header.php';
include '../includes/sidebar.php';

$query = "
SELECT
    ao.officer_id,
    u.full_name,
    p.position_name,
    ao.availability_status,
    proxy_user.full_name AS proxy_name

FROM approving_officers ao

INNER JOIN users u
    ON ao.user_id = u.user_id

INNER JOIN positions p
    ON ao.position_id = p.position_id

LEFT JOIN approving_officers proxy
    ON ao.proxy_officer_id = proxy.officer_id

LEFT JOIN users proxy_user
    ON proxy.user_id = proxy_user.user_id

ORDER BY u.full_name ASC
";

$result = mysqli_query($conn, $query);
?>

<div class="main">

    <div class="page-header">

        <h1>Manage Approving Officers</h1>

        <div>

            <input
                type="text"
                id="searchInput"
                class="search-box"
                placeholder="Search officer...">

            <a href="add_officer.php" class="btn">
                + Add Officer
            </a>

        </div>

    </div>

    <?php include '../includes/flash.php'; ?>

    <table id="dataTable">

        <tr>
            <th>Officer</th>
            <th>Position</th>
            <th>Availability</th>
            <th>Proxy Officer</th>
            <th>Actions</th>
        </tr>

        <?php while($row = mysqli_fetch_assoc($result)){ ?>

        <tr>

            <td><?= htmlspecialchars($row['full_name']) ?></td>

            <td><?= htmlspecialchars($row['position_name']) ?></td>

            <td>

                <span class="badge <?= strtolower($row['availability_status']) ?>">

                    <?= htmlspecialchars($row['availability_status']) ?>

                </span>

            </td>

            <td>

                <?= $row['proxy_name']
                    ? htmlspecialchars($row['proxy_name'])
                    : "<em>None Assigned</em>"; ?>

            </td>

            <td>

                <a
                    href="edit_officer.php?id=<?= $row['officer_id']; ?>"
                    class="btn-edit">

                    Edit

                </a>

                |

                <a
                    href="toggle_officer.php?id=<?= $row['officer_id']; ?>"
                    class="btn-toggle"
                    onclick="return confirm('Remove this officer assignment?');">

                    Remove

                </a>

            </td>

        </tr>

        <?php } ?>

    </table>

</div>

<script src="../assets/js/search.js"></script>

<?php include '../includes/footer.php'; ?>