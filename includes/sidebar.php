<ul>
    
<?php if ($_SESSION['role'] === 'admin'): ?>

    <a href="../admin/admin_dashboard.php">
        Dashboard
    </a>

    <a href="../admin/clubs.php">
        Manage Clubs
    </a>

    <a href="../admin/officials.php">
        Manage Club Officials
    </a>

    <a href="../admin/officers.php">
        Manage Officers
    </a>

    <a href="../admin/resources.php">
        Manage Resources
    </a>

    <a href="../admin/reports.php">
        Reports
    </a>

<?php endif; ?>

<li><a href="../logout.php">Logout</a></li>

</ul>