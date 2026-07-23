<?php
$unread = 0;

if (isset($_SESSION['user_id'])) {

    $uid = $_SESSION['user_id'];

    $q = mysqli_query($conn, "
    SELECT COUNT(*) total
    FROM notifications
    WHERE user_id=$uid
    AND is_read='No'
    ");

    $unread = mysqli_fetch_assoc($q)['total'];
}
?>

<div class="sidebar">
    <h2>Secretary</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="create_requisition.php">Create Requisition</a>
    <a href="my_requisitions.php">My Requisitions</a>
    <a href="notifications.php">

        Notifications

        <?php

        if ($unread > 0) {

            echo " ($unread)";
        }

        ?>

    </a>
    <a href="profile.php">Profile</a>
    <a href="../logout.php">Logout</a>
</div>