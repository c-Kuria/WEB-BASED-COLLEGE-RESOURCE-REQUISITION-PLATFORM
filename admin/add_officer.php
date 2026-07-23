<?php

require_once __DIR__ . '/../includes/session.php';

if($_SESSION['role']!="admin"){
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$message="";

/* Active positions */

$positions=mysqli_query($conn,"
SELECT position_id, position_name
FROM positions
ORDER BY position_name ASC
");

/* Existing officers for proxy */

$proxies=mysqli_query($conn,"
SELECT
ao.officer_id,
u.full_name

FROM approving_officers ao

JOIN users u
ON ao.user_id=u.user_id

ORDER BY u.full_name
");

if(isset($_POST['save'])){

    $full_name=trim($_POST['full_name']);
    $position_id=intval($_POST['position_id']);
    $availability=$_POST['availability_status'];

    $proxy=NULL;

    if(!empty($_POST['proxy_officer_id'])){

        $proxy=intval($_POST['proxy_officer_id']);

    }

    if($full_name === ''){
        $message="<p style='color:red;'>Officer name is required.</p>";
    }else{
        mysqli_begin_transaction($conn);

        try {
            $username=strtolower(str_replace(' ', '.', $full_name));
            $email=$username . '@example.com';
            $password=password_hash('password123', PASSWORD_DEFAULT);
            $phone='';
            $role='officer';
            $status='Active';

            $user_stmt=mysqli_prepare($conn,"
            INSERT INTO users
            (full_name, username, email, phone, password, role, status)
            VALUES(?,?,?,?,?,?,?)");

            mysqli_stmt_bind_param(
                $user_stmt,
                'sssssss',
                $full_name,
                $username,
                $email,
                $phone,
                $password,
                $role,
                $status
            );

            mysqli_stmt_execute($user_stmt);

            $user_id=mysqli_insert_id($conn);

            $stmt=mysqli_prepare($conn,"
            INSERT INTO approving_officers
            (
                user_id,
                position_id,
                availability_status,
                proxy_officer_id
            )
            VALUES(?,?,?,?)");

            mysqli_stmt_bind_param(
                $stmt,
                'iisi',
                $user_id,
                $position_id,
                $availability,
                $proxy
            );

            mysqli_stmt_execute($stmt);

            mysqli_commit($conn);

            $_SESSION['success']="Approving officer added successfully.";

            header("Location: officers.php");

            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message="<p style='color:red;'>Error creating officer.</p>";
        }
    }

}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

?>

<div class="main">

<h1>Add Approving Officer</h1>

<?php require_once __DIR__ . '/../includes/flash.php'; ?>

<?= $message ?>

<div class="form-container">

<form method="POST">

<div class="form-group">

<label>Officer Name</label>

<input
    type="text"
    name="full_name"
    required
    placeholder="Enter officer name">

</div>

<div class="form-group">

<label>Position</label>

<select
name="position_id"
required>

<option value="">Select Position</option>

<?php while($row=mysqli_fetch_assoc($positions)){ ?>

<option value="<?= $row['position_id']; ?>">

<?= htmlspecialchars($row['position_name']); ?>

</option>

<?php } ?>

</select>

</div>

<div class="form-group">

<label>Availability</label>

<select
name="availability_status">

<option value="Available">

Available

</option>

<option value="Unavailable">

Unavailable

</option>

</select>

</div>

<div class="form-group">

<label>Proxy Officer (Optional)</label>

<select
name="proxy_officer_id">

<option value="">None</option>

<?php while($row=mysqli_fetch_assoc($proxies)){ ?>

<option value="<?= $row['officer_id']; ?>">

<?= htmlspecialchars($row['full_name']); ?>

</option>

<?php } ?>

</select>

</div>

<button
class="btn"
name="save">

Save Officer

</button>

</form>

</div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>