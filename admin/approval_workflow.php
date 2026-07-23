<?php

require_once __DIR__ . '/../includes/session.php';

if ($_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$query = "
SELECT
    aw.workflow_id,
    rc.category_name,
    p.position_name,
    aw.approval_order

FROM approval_workflow aw

INNER JOIN resource_categories rc
ON aw.category_id = rc.category_id

INNER JOIN positions p
ON aw.position_id = p.position_id

ORDER BY
rc.category_name,
aw.approval_order
";

$result = mysqli_query($conn, $query);

?>



<div class="main">

    <div class="page-header">

        <h1>Manage Approval Workflow</h1>

        <div>

            <input
                type="text"
                id="searchInput"
                class="search-box"
                placeholder="Search workflow...">

            <a
                href="manage_workflow.php"
                class="btn">

                + Add Workflow Step

            </a>

        </div>

    </div>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <?php

$currentCategory = "";

while ($row = mysqli_fetch_assoc($result)) {

    if ($currentCategory != $row['category_name']) {

        if ($currentCategory != "") {

            echo '
                </div>
            </div>
            ';
        }

        $currentCategory = $row['category_name'];

        ?>

        <div class="workflow-card">

            <h2><?= htmlspecialchars($currentCategory); ?></h2>

            <div class="workflow-flow">

        <?php
    }
?>

    <div class="workflow-step">

        <div class="step-circle">

            <?= $row['approval_order']; ?>

        </div>

        <div class="step-box">

            <?= htmlspecialchars($row['position_name']); ?>

        </div>

    </div>

<?php

}

if ($currentCategory != "") {

    echo '
        </div>
    </div>
    ';
}

?>

</div>

<script src="/resource_requisition/assets/js/search.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>