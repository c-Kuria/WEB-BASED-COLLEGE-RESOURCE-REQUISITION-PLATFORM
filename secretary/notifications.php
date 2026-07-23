<?php

require_once __DIR__ . '/../includes/session.php';

if($_SESSION['role']!="secretary"){
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/secretary_sidebar.php';

$user_id = $_SESSION['user_id'];

/* Mark all unread notifications as read */

$stmt = mysqli_prepare($conn,"
UPDATE notifications
SET is_read='Yes'
WHERE user_id=?
AND is_read='No'
");

mysqli_stmt_bind_param($stmt,"i",$user_id);
mysqli_stmt_execute($stmt);

/* Load notifications */

$stmt = mysqli_prepare($conn,"

SELECT

notification_id,
requisition_id,
message,
is_read,
created_at

FROM notifications

WHERE user_id=?

ORDER BY created_at DESC

");

mysqli_stmt_bind_param($stmt,"i",$user_id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

?>

<div class="main">

<h1>Notifications</h1>

<?php if(mysqli_num_rows($result)==0){ ?>

<div class="card">

<p>No notifications available.</p>

</div>

<?php }else{ ?>

<table>

<tr>

<th>Message</th>

<th>Date</th>

<th>Status</th>

<th>Action</th>

</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr class="<?= $row['is_read']=="No" ? "unread-row" : ""; ?>">

<td>

<?= htmlspecialchars($row['message']); ?>

</td>

<td>

<?= date(
"d M Y H:i",
strtotime($row['created_at'])
); ?>

</td>

<td>

<?php

if($row['is_read']=="No"){

echo "<span class='badge pending'>Unread</span>";

}else{

echo "<span class='badge approved'>Read</span>";

}

?>

</td>

<td>

<?php

if($row['requisition_id']){

?>

<a

class="btn"

href="view_requisition.php?id=<?= $row['requisition_id']; ?>">

View

</a>

<?php

}else{

echo "-";

}

?>

</td>

</tr>

<?php } ?>

</table>

<?php } ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>