<?php
include '../includes/session.php';

if($_SESSION['role']!="admin"){
    header("Location: ../login.php");
    exit();
}

include '../config/db.php';

$message="";

/* SAVE USER */

if(isset($_POST['save'])){

    $fullname=trim($_POST['full_name']);
    $username=trim($_POST['username']);
    $email=trim($_POST['email']);
    $phone=trim($_POST['phone']);
    $password=password_hash($_POST['password'],PASSWORD_DEFAULT);

    $role=$_POST['role'];

    mysqli_begin_transaction($conn);

    try{

        $stmt=mysqli_prepare($conn,"
        INSERT INTO users
        (full_name,username,email,phone,password,role)
        VALUES(?,?,?,?,?,?)");

        mysqli_stmt_bind_param(
            $stmt,
            "ssssss",
            $fullname,
            $username,
            $email,
            $phone,
            $password,
            $role
        );

        mysqli_stmt_execute($stmt);

        $user_id=mysqli_insert_id($conn);

        if($role=="secretary"){

            $reg=$_POST['reg_no'];
            $club=$_POST['club'];

            $stmt=mysqli_prepare($conn,"
            INSERT INTO students
            (user_id,reg_no,club_id)
            VALUES(?,?,?)");

            mysqli_stmt_bind_param(
                $stmt,
                "isi",
                $user_id,
                $reg,
                $club
            );

            mysqli_stmt_execute($stmt);

        }

        if($role=="officer"){

            $position_id=intval($_POST['position_id']);
            $availability_status='Available';

            $stmt=mysqli_prepare($conn,"
            INSERT INTO approving_officers
            (user_id,position_id,availability_status)
            VALUES(?,?,?)");

            mysqli_stmt_bind_param(
                $stmt,
                "iis",
                $user_id,
                $position_id,
                $availability_status
            );

            mysqli_stmt_execute($stmt);

        }

        mysqli_commit($conn);

        $message="User created successfully.";

    }catch(Exception $e){

        mysqli_rollback($conn);

        $message="Error creating user.";

    }

}

$clubs=mysqli_query($conn,"
SELECT *
FROM clubs
ORDER BY club_name");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main">

<h1>Add User</h1>

<?php

if($message!=""){

echo "<p style='color:green;'>$message</p>";

}

?>

<form method="POST">

<label>Full Name</label>

<input
type="text"
name="full_name"
required>

<label>Username</label>

<input
type="text"
name="username"
required>

<label>Email</label>

<input
type="email"
name="email"
required>

<label>Phone</label>

<input
type="text"
name="phone">

<label>Password</label>

<input
type="password"
name="password"
required>

<label>Role</label>

<select
name="role"
id="role"
required>

<option value="">Select Role</option>

<option value="admin">Administrator</option>

<option value="secretary">Secretary</option>

<option value="officer">Approving Officer</option>

</select>

<div id="studentFields" style="display:none;">

<label>Registration Number</label>

<input
type="text"
name="reg_no">

<label>Club</label>

<select name="club">

<option value="">Select Club</option>

<?php

while($club=mysqli_fetch_assoc($clubs)){

?>

<option value="<?= $club['club_id']; ?>">

<?= $club['club_name']; ?>

</option>

<?php

}

?>

</select>

</div>

<div id="officerFields" style="display:none;">

<label>Position</label>

<select name="position_id">

<?php

$positions=mysqli_query($conn,"
SELECT *
FROM positions
ORDER BY position_name");

while($p=mysqli_fetch_assoc($positions)){

?>

<option value="<?= $p['position_id']; ?>">

<?= $p['position_name']; ?>

</option>

<?php

}

?>

</select>

</div>

<br>

<button
type="submit"
name="save"
class="btn">

Save User

</button>

</form>

</div>

<script>

const role=document.getElementById("role");

const student=document.getElementById("studentFields");

const officer=document.getElementById("officerFields");

role.addEventListener("change",function(){

student.style.display="none";

officer.style.display="none";

if(this.value==="secretary"){

student.style.display="block";

}

if(this.value==="officer"){

officer.style.display="block";

}

});

</script>

<?php

include '../includes/footer.php';

?>