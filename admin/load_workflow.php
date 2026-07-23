<?php

require_once '../includes/session.php';
require_once '../config/db.php';

if ($_SESSION['role'] != 'admin') {
    exit();
}

if (!isset($_GET['category_id'])) {
    exit();
}

$category_id = (int)$_GET['category_id'];

$stmt = mysqli_prepare($conn, "
SELECT
    aw.position_id,
    aw.approval_order,
    p.position_name
FROM approval_workflow aw
JOIN positions p
ON aw.position_id = p.position_id
WHERE aw.category_id=?
ORDER BY aw.approval_order
");

mysqli_stmt_bind_param($stmt,"i",$category_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$workflow = [];

while($row=mysqli_fetch_assoc($result)){
    $workflow[] = $row;
}

header('Content-Type: application/json');
echo json_encode($workflow);