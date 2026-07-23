<?php

require_once '../includes/session.php';

if($_SESSION['role']!="officer"){
    header("Location: ../login.php");
    exit();
}

require_once '../config/db.php';
require_once '../includes/header.php';
require_once '../includes/officer_sidebar.php';

$user_id=$_SESSION['user_id'];

/* Load profile */

$stmt=mysqli_prepare($conn,"

SELECT

u.*,

ao.availability_status,

ao.proxy_officer_id

FROM users u

JOIN approving_officers ao

ON u.user_id=ao.user_id

WHERE u.user_id=?

");

mysqli_stmt_bind_param($stmt,"i",$user_id);

mysqli_stmt_execute($stmt);

$result=mysqli_stmt_get_result($stmt);

$user=mysqli_fetch_assoc($result);

/* Load possible proxies */

$proxyQuery=mysqli_prepare($conn,"

SELECT

ao.officer_id,

u.full_name

FROM approving_officers ao

JOIN users u

ON ao.user_id=u.user_id

WHERE

ao.user_id<>?

ORDER BY u.full_name

");

mysqli_stmt_bind_param($proxyQuery,"i",$user_id);

mysqli_stmt_execute($proxyQuery);

$proxies=mysqli_stmt_get_result($proxyQuery);

if(isset($_POST['save'])){

    $name=trim($_POST['full_name']);
    $email=trim($_POST['email']);
    $phone=trim($_POST['phone']);

    $availability=$_POST['availability'];

    $proxy=!empty($_POST['proxy_officer'])
        ?intval($_POST['proxy_officer'])
        :NULL;

    /* Validation */

    if($availability=="Unavailable" && empty($proxy)){

        $_SESSION['error']="Select a proxy officer.";

    }else{

        mysqli_begin_transaction($conn);

        try{

            /* Update user */

            $stmt=mysqli_prepare($conn,"
            UPDATE users

            SET

            full_name=?,

            email=?,

            phone=?

            WHERE user_id=?
            ");

            mysqli_stmt_bind_param(

                $stmt,

                "sssi",

                $name,

                $email,

                $phone,

                $user_id

            );

            mysqli_stmt_execute($stmt);

            /* Update officer */

            $stmt=mysqli_prepare($conn,"
            UPDATE approving_officers

            SET

            availability_status=?,

            proxy_officer_id=?

            WHERE user_id=?
            ");

            mysqli_stmt_bind_param(

                $stmt,

                "sii",

                $availability,

                $proxy,

                $user_id

            );

            mysqli_stmt_execute($stmt);

                /* Password */

            if(!empty($_POST['new_password'])){

                if(!password_verify(

                    $_POST['current_password'],

                    $user['password']

                )){

                    throw new Exception("Current password is incorrect.");

                }

                if($_POST['new_password']!=$_POST['confirm_password']){

                    throw new Exception("Passwords do not match.");

                }

                $hash=password_hash(

                    $_POST['new_password'],

                    PASSWORD_DEFAULT

                );

                $stmt=mysqli_prepare($conn,"
                UPDATE users

                SET password=?

                WHERE user_id=?
                ");

                mysqli_stmt_bind_param(

                    $stmt,

                    "si",

                    $hash,

                    $user_id

                );

                mysqli_stmt_execute($stmt);

            }

            mysqli_commit($conn);

            $_SESSION['success']="Profile updated successfully.";

            header("Location: profile.php");

            exit();

        }catch(Exception $e){

            mysqli_rollback($conn);

            $_SESSION['error']=$e->getMessage();

        }

    }

}
?>

<div class="main">

<h1>My Profile</h1>

<?php require_once __DIR__ . '/../includes/flash.php'; ?>

<form method="POST">

<div class="card">

<h2>Personal Information</h2>

<label>Full Name</label>

<input
type="text"
name="full_name"
value="<?= htmlspecialchars($user['full_name']); ?>"
required>

<label>Email</label>

<input
type="email"
name="email"
value="<?= htmlspecialchars($user['email']); ?>"
required>

<label>Phone</label>

<input
type="text"
name="phone"
value="<?= htmlspecialchars($user['phone']); ?>">

</div>

<br>

<div class="card">

<h2>Availability</h2>

<label>

<input
type="radio"
name="availability"
value="Available"

<?=($user['availability_status']=="Available")?"checked":"";?>>

Available

</label>

<br>

<label>

<input
type="radio"
name="availability"
value="Unavailable"

<?=($user['availability_status']=="Unavailable")?"checked":"";?>>

Unavailable

</label>

<br><br>

<label>

Proxy Officer

</label>

<select name="proxy_officer">

<option value="">None</option>

<?php while($proxy=mysqli_fetch_assoc($proxies)){ ?>

<option

value="<?= $proxy['officer_id']; ?>"

<?=($proxy['officer_id']==$user['proxy_officer_id'])?"selected":"";?>>

<?= htmlspecialchars($proxy['full_name']); ?>

</option>

<?php } ?>

</select>

</div>

<br>

<div class="card">

<h2>Change Password</h2>

<label>

Current Password

</label>

<input
type="password"
name="current_password">

<label>

New Password

</label>

<input
type="password"
name="new_password">

<label>

Confirm Password

</label>

<input
type="password"
name="confirm_password">

</div>

<br>

<button
class="btn"
name="save">

Save Changes

</button>

</form>

</div>