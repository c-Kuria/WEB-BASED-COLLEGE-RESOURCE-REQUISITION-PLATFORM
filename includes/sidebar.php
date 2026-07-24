<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">

    <div class="sidebar-brand">
        <h2>Resource Requisition</h2>
    </div>

    <nav class="sidebar-nav">

        <?php if (
            isset($_SESSION['role']) &&
            $_SESSION['role'] === 'admin'
        ): ?>

            <a
                href="/resource_requisition/admin/dashboard.php"
                class="<?= $currentPage === 'dashboard.php'
                    ? 'active'
                    : ''; ?>"
            >
                Dashboard
            </a>

            <a
                href="/resource_requisition/admin/clubs.php"
                class="<?= $currentPage === 'clubs.php'
                    ? 'active'
                    : ''; ?>"
            >
                Manage Clubs
            </a>

            <a
                href="/resource_requisition/admin/officials.php"
                class="<?= $currentPage === 'officials.php'
                    ? 'active'
                    : ''; ?>"
            >
                Manage Club Officials
            </a>

            <a
                href="/resource_requisition/admin/officers.php"
                class="<?= $currentPage === 'officers.php'
                    ? 'active'
                    : ''; ?>"
            >
                Manage Officers
            </a>

            <a
                href="/resource_requisition/admin/resources.php"
                class="<?= $currentPage === 'resources.php'
                    ? 'active'
                    : ''; ?>"
            >
                Manage Resources
            </a>

            <a
                href="/resource_requisition/admin/reports.php"
                class="<?= $currentPage === 'reports.php'
                    ? 'active'
                    : ''; ?>"
            >
                Reports
            </a>

            <a
                href="/resource_requisition/admin/profile.php"
                class="<?= $currentPage === 'profile.php'
                    ? 'active'
                    : ''; ?>"
            >
                My Profile
            </a>

        <?php elseif (
            isset($_SESSION['role']) &&
            $_SESSION['role'] === 'official'
        ): ?>

            <a
                href="/resource_requisition/official/dashboard.php"
                class="<?= $currentPage === 'dashboard.php'
                    ? 'active'
                    : ''; ?>"
            >
                Dashboard
            </a>

            <a
                href="/resource_requisition/official/create_requisition.php"
                class="<?= $currentPage ===
                    'create_requisition.php'
                    ? 'active'
                    : ''; ?>"
            >
                New Requisition
            </a>

            <a
                href="/resource_requisition/official/my_requisitions.php"
                class="<?= $currentPage ===
                    'my_requisitions.php'
                    ? 'active'
                    : ''; ?>"
            >
                My Requisitions
            </a>

            <a
                href="/resource_requisition/official/notifications.php"
                class="<?= $currentPage ===
                    'notifications.php'
                    ? 'active'
                    : ''; ?>"
            >
                Notifications
            </a>

            <a
                href="/resource_requisition/official/profile.php"
                class="<?= $currentPage === 'profile.php'
                    ? 'active'
                    : ''; ?>"
            >
                My Profile
            </a>

        <?php elseif (
            isset($_SESSION['role']) &&
            $_SESSION['role'] === 'officer'
        ): ?>

            <a
                href="/resource_requisition/officer/dashboard.php"
                class="<?= $currentPage === 'dashboard.php'
                    ? 'active'
                    : ''; ?>"
            >
                Dashboard
            </a>

            <a
                href="/resource_requisition/officer/pending_requests.php"
                class="<?= $currentPage ===
                    'pending_requests.php'
                    ? 'active'
                    : ''; ?>"
            >
                Pending Requests
            </a>

            <a
                href="/resource_requisition/officer/delegated_requests.php"
                class="<?= $currentPage ===
                    'delegated_requests.php'
                    ? 'active'
                    : ''; ?>"
            >
                Delegated Requests
            </a>

            <a
                href="/resource_requisition/officer/profile.php"
                class="<?= $currentPage === 'profile.php'
                    ? 'active'
                    : ''; ?>"
            >
                My Profile
            </a>

        <?php endif; ?>

        <a
            href="/resource_requisition/logout.php"
            class="logout-link"
        >
            Logout
        </a>

    </nav>

</aside>