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

$message = "";

/* Update Profile */

if(isset($_POST['update_profile'])){

    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    $stmt = mysqli_prepare($conn,"
        UPDATE users
        SET full_name=?, email=?, phone=?
        WHERE user_id=?
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "sssi",
        $full_name,
        $email,
        $phone,
        $user_id
    );

    if(mysqli_stmt_execute($stmt)){

        $_SESSION['full_name'] = $full_name;

        $message = "<div class='success'>
        Profile updated successfully.
        </div>";

    }else{

        $message = "<div class='error'>
        Failed to update profile.
        </div>";

    }

}

/* Change Password */

if(isset($_POST['change_password'])){

    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $stmt = mysqli_prepare($conn,"
        SELECT password
        FROM users
        WHERE user_id=?
    ");

    mysqli_stmt_bind_param($stmt,"i",$user_id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if(!password_verify($current,$user['password'])){

        $message = "<div class='error'>
        Current password is incorrect.
        </div>";

    }elseif($new != $confirm){

        $message = "<div class='error'>
        New passwords do not match.
        </div>";

    }elseif(strlen($new) < 6){

        $message = "<div class='error'>
        Password must be at least 6 characters.
        </div>";

    }else{

        $hash = password_hash($new,PASSWORD_DEFAULT);

        $stmt = mysqli_prepare($conn,"
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

        $message = "<div class='success'>
        Password changed successfully.
        </div>";

    }

}

/* Load User */

$stmt = mysqli_prepare($conn,"
SELECT *
FROM users
WHERE user_id=?
");

mysqli_stmt_bind_param($stmt,"i",$user_id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$user = mysqli_fetch_assoc($result);

?>

<div class="main">

<h1>My Profile</h1>

<?= $message; ?>

<div class="card">

<h2>Profile Information</h2>

<form method="POST">

<div class="form-group">

<label>Full Name</label>

<input
type="text"
name="full_name"
value="<?= htmlspecialchars($user['full_name']) ?>"
required>

</div>

<div class="form-group">

<label>Username</label>

<input
type="text"
value="<?= htmlspecialchars($user['username']) ?>"
readonly>

</div>

<div class="form-group">

<label>Email</label>

<input
type="email"
name="email"
value="<?= htmlspecialchars($user['email']) ?>"
required>

</div>

<div class="form-group">

<label>Phone</label>

<input
type="text"
name="phone"
value="<?= htmlspecialchars($user['phone']) ?>">

</div>

<button
class="btn"
name="update_profile">

Update Profile

</button>

</form>

</div>

<br>

<div class="card">

<h2>Change Password</h2>

<form method="POST">

<div class="form-group">

<label>Current Password</label>

<input
type="password"
name="current_password"
required>

</div>

<div class="form-group">

<label>New Password</label>

<input
type="password"
name="new_password"
required>

</div>

<div class="form-group">

<label>Confirm Password</label>

<input
type="password"
name="confirm_password"
required>

</div>

<button
class="btn"
name="change_password">

Change Password

</button>

</form>

</div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>