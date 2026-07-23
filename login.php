<?php
session_start();
include 'config/db.php';

$error = "";

if(isset($_POST['login'])){

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email=? LIMIT 1";

    $stmt = mysqli_prepare($conn,$sql);

    mysqli_stmt_bind_param($stmt,"s",$email);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if(mysqli_num_rows($result)==1){

        $user=mysqli_fetch_assoc($result);

        if($user['status']!="Active"){

            $error="Account has been deactivated.";

        }

        elseif(password_verify($password,$user['password'])){

            $_SESSION['user_id']=$user['user_id'];
            $_SESSION['full_name']=$user['full_name'];
            $_SESSION['role']=$user['role'];

            switch($user['role']){

                case "admin":
                    header("Location: admin/dashboard.php");
                    break;

                case "secretary":
                    header("Location: secretary/dashboard.php");
                    break;

                case "officer":
                    header("Location: officer/dashboard.php");
                    break;

            }

            exit();

        }else{

            $error="Incorrect password.";

        }

    }else{

        $error="User not found.";

    }

}

?>

<!DOCTYPE html>
<html>
<head>
<title>Login</title>
<link rel="stylesheet" href="/resource_requisition/assets/css/style.css?v=20260722">
</head>

<body>

<h2>Login</h2>

<?php
if($error!=""){
    echo "<p style='color:red;'>$error</p>";
}
?>

<form method="POST">

<input
type="email"
name="email"
placeholder="Email"
required>

<br><br>

<input
type="password"
name="password"
placeholder="Password"
required>

<br><br>

<button
type="submit"
name="login">

Login

</button>

</form>

</body>

</html>