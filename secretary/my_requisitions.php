<?php

require_once __DIR__ . '/../includes/session.php';

if ($_SESSION['role'] != "secretary") {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/secretary_sidebar.php';

$user_id = $_SESSION['user_id'];

/* Get requisitions */

$query = mysqli_query($conn, "

SELECT

r.requisition_id,
r.requisition_number,
r.status,
r.submitted_at,

c.club_name,

res.resource_name,

(
SELECT p.position_name
FROM requisition_approvals ra

JOIN approving_officers ao
ON ra.officer_id = ao.officer_id

JOIN positions p
ON ao.position_id = p.position_id

WHERE ra.requisition_id = r.requisition_id

AND ra.status='Pending'

LIMIT 1

) AS current_stage

FROM requisitions r

JOIN clubs c
ON r.club_id=c.club_id

JOIN resources res
ON r.resource_id=res.resource_id

WHERE r.secretary_id=$user_id

ORDER BY r.submitted_at DESC

");

?>

<div class="main">

    <h1>My Requisitions</h1>

    <?php

    if (isset($_SESSION['success'])) {

        echo "<div class='success'>" . $_SESSION['success'] . "</div>";

        unset($_SESSION['success']);
    }

    ?>

    <input
        type="text"
        id="searchInput"
        placeholder="Search requisitions...">

    <table id="searchTable">

        <tr>

            <th>Requisition No.</th>

            <th>Club</th>

            <th>Resource</th>

            <th>Submitted</th>

            <th>Status</th>

            <th>Current Stage</th>

            <th>Action</th>

        </tr>

        <?php while ($row = mysqli_fetch_assoc($query)) { ?>

            <tr>

                <td><?= htmlspecialchars($row['requisition_number']) ?></td>

                <td><?= htmlspecialchars($row['club_name']) ?></td>

                <td><?= htmlspecialchars($row['resource_name']) ?></td>

                <td><?= date('d M Y H:i', strtotime($row['submitted_at'])) ?></td>

                <td>

                    <span class="badge <?= strtolower($row['status']) ?>">

                        <?= $row['status'] ?>

                    </span>

                </td>

                <td>

                    <?php

                    if ($row['status'] == "Approved") {

                        echo "<span style='color:green;'>Completed</span>";
                    } elseif ($row['status'] == "Rejected") {

                        echo "<span style='color:red;'>Rejected</span>";
                    } else {

                        echo htmlspecialchars($row['current_stage']);
                    }

                    ?>

                </td>

                <td>

                    <a
                        class="btn"
                        href="view_requisition.php?id=<?= $row['requisition_id']; ?>">

                        View

                    </a>

                </td>

            </tr>

        <?php } ?>

    </table>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>