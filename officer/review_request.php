<?php

include '../includes/session.php';

if($_SESSION['role']!="officer"){
    header("Location: ../login.php");
    exit();
}

include '../config/db.php';
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/workflow.php';

if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    die("Invalid approval.");
}

$approval_id = intval($_GET['id']);

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn,"

SELECT

ra.*,

r.requisition_number,
r.requisition_id,
r.purpose,
r.additional_notes,
r.start_date,
r.end_date,
r.status AS requisition_status,

c.club_name,

res.resource_name,

p.position_name

FROM requisition_approvals ra

JOIN requisitions r
ON ra.requisition_id=r.requisition_id

JOIN clubs c
ON r.club_id=c.club_id

JOIN resources res
ON r.resource_id=res.resource_id

JOIN approving_officers ao
ON ra.officer_id=ao.officer_id

JOIN positions p
ON ao.position_id=p.position_id

WHERE

ra.approval_id=?

AND

ao.user_id=?

");

mysqli_stmt_bind_param(
$stmt,
"ii",
$approval_id,
$user_id
);

mysqli_stmt_execute($stmt);

$result=mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result)==0){

die("Approval not found.");

}

$data=mysqli_fetch_assoc($result);

if ($data['status'] !== 'Pending') {
    die("This approval has already been processed or is not awaiting your action.");
}

if(isset($_POST['approve'])){

    $comments = trim($_POST['comments']);

    mysqli_begin_transaction($conn);

    try{

        $stmt = mysqli_prepare($conn,"
        UPDATE requisition_approvals

        SET

        status='Approved',

        comments=?,

        action_date=NOW(),

        acted_by_officer_id=?

        WHERE approval_id=?
        ");

        mysqli_stmt_bind_param(

            $stmt,

            "sii",

            $comments,

            $data['officer_id'],

            $approval_id

        );

        mysqli_stmt_execute($stmt);

        activateNextApproval(

            $conn,

            $data['requisition_id']

        );

        mysqli_commit($conn);

        $_SESSION['success']="Approval recorded.";

        header("Location: pending_requests.php");

        exit();

    }catch(Exception $e){

        mysqli_rollback($conn);

        die($e->getMessage());

    }

}

if(isset($_POST['reject'])){

    $comments = trim($_POST['comments']);

    mysqli_begin_transaction($conn);

    try{

        $stmt = mysqli_prepare($conn,"
        UPDATE requisition_approvals

        SET

        status='Rejected',

        comments=?,

        action_date=NOW(),

        acted_by_officer_id=?

        WHERE approval_id=?
        ");

        mysqli_stmt_bind_param(

            $stmt,

            "sii",

            $comments,

            $data['officer_id'],

            $approval_id

        );

        mysqli_stmt_execute($stmt);

        rejectRequisition(

            $conn,

            $data['requisition_id']

        );

        /* Notify secretary */

        $q=mysqli_prepare($conn,"
        SELECT secretary_id
        FROM requisitions
        WHERE requisition_id=?
        ");

        mysqli_stmt_bind_param(
            $q,
            "i",
            $data['requisition_id']
        );

        mysqli_stmt_execute($q);

        $r=mysqli_stmt_get_result($q);

        $row=mysqli_fetch_assoc($r);

        notifyUser(

            $conn,

            $row['secretary_id'],

            "Your requisition has been rejected.",

            $data['requisition_id']

        );

        mysqli_commit($conn);

        $_SESSION['success']="Requisition rejected.";

        header("Location: pending_requests.php");

        exit();

    }catch(Exception $e){

        mysqli_rollback($conn);

        die($e->getMessage());

    }

}
?>

<div class="main">

<h1>Review Requisition</h1>

<div class="card">

<table class="details-table">

<tr>

<th>Requisition</th>

<td><?= htmlspecialchars($data['requisition_number']) ?></td>

</tr>

<tr>

<th>Club</th>

<td><?= htmlspecialchars($data['club_name']) ?></td>

</tr>

<tr>

<th>Resource</th>

<td><?= htmlspecialchars($data['resource_name']) ?></td>

</tr>

<tr>

<th>Approval Stage</th>

<td><?= htmlspecialchars($data['position_name']) ?></td>

</tr>

<tr>

<th>Purpose</th>

<td><?= nl2br(htmlspecialchars($data['purpose'])) ?></td>

</tr>

<tr>

<th>Notes</th>

<td><?= nl2br(htmlspecialchars($data['additional_notes'])) ?></td>

</tr>

<tr>

<th>Event Dates</th>

<td>

<?= $data['start_date']; ?>

to

<?= $data['end_date']; ?>

</td>

</tr>

</table>

</div>

<div class="card">

<h2>Decision</h2>

<form method="POST">

<label>

Comments

</label>

<textarea

name="comments"

rows="5"

required></textarea>

<br><br>

<button

name="approve"

class="btn success-btn">

Approve

</button>

<button

name="reject"

class="btn danger-btn">

Reject

</button>

</form>

</div>

</div>

<?php include '../includes/footer.php'; ?>