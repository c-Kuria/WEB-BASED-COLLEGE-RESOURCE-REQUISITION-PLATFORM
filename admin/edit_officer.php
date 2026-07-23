<?php

require_once __DIR__ . '/../includes/session.php';

if($_SESSION['role']!="admin"){
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

if(!isset($_GET['id'])){
    header("Location: officers.php");
    exit();
}

$id = intval($_GET['id']);

/* Load officer */

$stmt=mysqli_prepare($conn,"
SELECT
ao.*,
u.full_name

FROM approving_officers ao

JOIN users u
ON ao.user_id=u.user_id

WHERE officer_id=?");

mysqli_stmt_bind_param($stmt,"i",$id);

mysqli_stmt_execute($stmt);

$result=mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result)==0){

    header("Location: officers.php");
    exit();

}

$officer=mysqli_fetch_assoc($result);

/* Load positions */

$positions=mysqli_query($conn,"
SELECT
position_id,
position_name

FROM positions

ORDER BY position_name");

/* Load proxy officers except current */

$proxies=mysqli_query($conn,"
SELECT

ao.officer_id,

u.full_name

FROM approving_officers ao

JOIN users u
ON ao.user_id=u.user_id

WHERE ao.officer_id <> $id

ORDER BY u.full_name");

if(isset($_POST['update'])){

    $position_id=intval($_POST['position_id']);

    $availability=$_POST['availability_status'];

    $proxy=NULL;

    if(!empty($_POST['proxy_officer_id'])){

        $proxy=intval($_POST['proxy_officer_id']);

    }

    $update=mysqli_prepare($conn,"
    UPDATE approving_officers

    SET

    position_id=?,

    availability_status=?,

    proxy_officer_id=?

    WHERE officer_id=?");

    mysqli_stmt_bind_param(

        $update,

        "isii",

        $position_id,

        $availability,

        $proxy,

        $id

    );

    mysqli_stmt_execute($update);

    $_SESSION['success']="Officer updated successfully.";

    header("Location: officers.php");

    exit();

}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

?>

<div class="main">

<h1>Edit Approving Officer</h1>

<?php require_once __DIR__ . '/../includes/flash.php'; ?>

<div class="form-container">

<form method="POST">

<div class="form-group">

<label>Officer</label>

<input
type="text"
value="<?= htmlspecialchars($officer['full_name']) ?>"
readonly>

</div>

<div class="form-group">

<label>Position</label>

<select
name="position_id"
required>

<?php

while($row=mysqli_fetch_assoc($positions)){

?>

<option

value="<?= $row['position_id']; ?>"

<?= ($row['position_id']==$officer['position_id']) ? "selected" : ""; ?>>

<?= htmlspecialchars($row['position_name']); ?>

</option>

<?php

}

?>

</select>

</div>

<div class="form-group">

<label>Availability</label>

<select name="availability_status">

<option

value="Available"

<?= ($officer['availability_status']=="Available") ? "selected" : ""; ?>>

Available

</option>

<option

value="Unavailable"

<?= ($officer['availability_status']=="Unavailable") ? "selected" : ""; ?>>

Unavailable

</option>

</select>

</div>

<div class="form-group">

<label>Proxy Officer</label>

<select name="proxy_officer_id">

<option value="">None</option>

<?php

while($row=mysqli_fetch_assoc($proxies)){

?>

<option

value="<?= $row['officer_id']; ?>"

<?= ($row['officer_id']==$officer['proxy_officer_id']) ? "selected" : ""; ?>>

<?= htmlspecialchars($row['full_name']); ?>

</option>

<?php

}

?>

</select>

</div>

<button

class="btn"

name="update">

Update Officer

</button>

</form>

</div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>