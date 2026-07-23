<?php

require_once __DIR__ . '/session.php';

if ($_SESSION['role'] != "secretary") {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/header.php';

if ($_SESSION['role'] == 'secretary') {
    require_once __DIR__ . '/secretary_sidebar.php';
} else {
    require_once __DIR__ . '/sidebar.php';
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid requisition.");
}

$requisition_id = intval($_GET['id']);

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "

SELECT

r.*,

c.club_name,

res.resource_name,

rc.category_name

FROM requisitions r

JOIN clubs c
ON r.club_id=c.club_id

JOIN resources res
ON r.resource_id=res.resource_id

JOIN resource_categories rc
ON res.category_id=rc.category_id

WHERE

r.requisition_id=?

AND

r.secretary_id=?

");

mysqli_stmt_bind_param(

    $stmt,

    "ii",

    $requisition_id,

    $user_id

);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {

    die("Requisition not found.");
}

$req = mysqli_fetch_assoc($result);

$progress = mysqli_query($conn, "

SELECT

ra.*,

u.full_name,

p.position_name

FROM requisition_approvals ra

JOIN approving_officers ao

ON ra.officer_id=ao.officer_id

JOIN users u

ON ao.user_id=u.user_id

JOIN positions p

ON ao.position_id=p.position_id

WHERE

ra.requisition_id=$requisition_id

ORDER BY approval_order

");
?>

<div class="main">

    <h1>Requisition Details</h1>

    <div class="card">

        <table class="details-table">

            <tr>

                <th>Requisition Number</th>

                <td><?= htmlspecialchars($req['requisition_number']) ?></td>

            </tr>

            <tr>

                <th>Club</th>

                <td><?= htmlspecialchars($req['club_name']) ?></td>

            </tr>

            <tr>

                <th>Resource</th>

                <td><?= htmlspecialchars($req['resource_name']) ?></td>

            </tr>

            <tr>

                <th>Category</th>

                <td><?= htmlspecialchars($req['category_name']) ?></td>

            </tr>

            <tr>

                <th>Purpose</th>

                <td><?= nl2br(htmlspecialchars($req['purpose'])) ?></td>

            </tr>

            <tr>

                <th>Additional Notes</th>

                <td>

                    <?= nl2br(htmlspecialchars($req['additional_notes'])) ?>

                </td>

            </tr>

            <tr>

                <th>Start Date</th>

                <td><?= $req['start_date'] ?></td>

            </tr>

            <tr>

                <th>End Date</th>

                <td><?= $req['end_date'] ?></td>

            </tr>

            <tr>

                <th>Status</th>

                <td>

                    <span class="badge <?= strtolower($req['status']) ?>">

                        <?= $req['status'] ?>

                    </span>

                </td>

            </tr>

        </table>

    </div>

    <h2>Approval Progress</h2>

    <table>

        <tr>

            <th>Stage</th>

            <th>Officer</th>

            <th>Status</th>

            <th>Assigned As</th>

            <th>Action Date</th>

            <th>Comments</th>

        </tr>

        <?php while ($row = mysqli_fetch_assoc($progress)) { ?>

            <tr>

                <td>

                    <?= htmlspecialchars($row['position_name']) ?>

                </td>

                <td>

                    <?= htmlspecialchars($row['full_name']) ?>

                </td>

                <td>

                    <span class="badge <?= strtolower($row['status']) ?>">

                        <?= $row['status'] ?>

                    </span>

                </td>

                <td>

                    <?= $row['assigned_as'] ?>

                </td>

                <td>

                    <?= $row['action_date'] ?: "-" ?>

                </td>

                <td>

                    <?= nl2br(htmlspecialchars($row['comments'] ?: "-")) ?>

                </td>

            </tr>

        <?php } ?>

    </table>

    <br>

    <a

        href="my_requisitions.php"

        class="btn">

        ← Back to My Requisitions

    </a>

</div>

<?php include '../includes/footer.php'; ?>