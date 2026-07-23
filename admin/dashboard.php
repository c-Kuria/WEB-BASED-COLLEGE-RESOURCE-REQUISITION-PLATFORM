<?php

include '../includes/session.php';

if ($_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

include '../config/db.php';

include '../includes/header.php';

include '../includes/sidebar.php';

$totalUsers = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM users"));

$totalResources = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM resources"));

$totalRequisitions = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM requisitions"));
?>

<div class="main">

    <h1>

        <!-- Welcome, -->
        <?php echo $_SESSION['full_name']; ?>

    </h1>

    <br>

    <div class="card">

        <h3>

            Users

        </h3>

        <h1>

            <?php echo $totalUsers; ?>

        </h1>

    </div>

    <div class="card">

        <h3>

            Resources

        </h3>

        <h1>

            <?php echo $totalResources; ?>

        </h1>

    </div>

    <div class="card">

        <h3>

            Requisitions

        </h3>

        <h1>

            <?php echo $totalRequisitions; ?>

        </h1>

    </div>

</div>

<?php

include '../includes/footer.php';

?>