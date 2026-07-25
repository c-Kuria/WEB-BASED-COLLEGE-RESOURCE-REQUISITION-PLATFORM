<?php

if (!isset($baseUrl)) {
    $baseUrl =
        '/resource_requisition';
}

if (!isset($currentPage)) {
    $currentPage =
        basename($_SERVER['PHP_SELF']);
}

if (!isset($currentRole)) {
    $currentRole =
        $_SESSION['role'] ?? '';
}

function sidebarActive(
    string $currentPage,
    string $targetPage
): string {
    return $currentPage === $targetPage
        ? 'active'
        : '';
}
?>

<aside
    class="sidebar"
    id="sidebar"
>

    <div class="sidebar-header">

        <a
            href="<?= $baseUrl; ?>/<?= htmlspecialchars(
                $currentRole
            ); ?>/dashboard.php"
            class="sidebar-logo"
        >

            <span class="sidebar-logo-mark">
                RR
            </span>

            <span class="sidebar-logo-text">
                <strong>Resource Requisition</strong>
                <small>College System</small>
            </span>

        </a>

        <button
            type="button"
            class="sidebar-close-button"
            id="sidebarCloseButton"
            aria-label="Close navigation"
        >
            ×
        </button>

    </div>

    <div class="sidebar-content">

        <nav class="sidebar-navigation">

            <div class="sidebar-navigation-main">

                <p class="sidebar-section-label">
                    Main Menu
                </p>

                <?php if ($currentRole === 'admin'): ?>

                    <a
                        href="<?= $baseUrl; ?>/admin/admin_dashboard.php"
                        class="sidebar-link <?= sidebarActive(
                            $currentPage,
                            'dashboard.php'
                        ); ?>"
                    >
                        <span class="sidebar-icon">
                            ▦
                        </span>

                        <span>Dashboard</span>
                    </a>

                    <a
                        href="<?= $baseUrl; ?>/admin/clubs.php"
                        class="sidebar-link <?= sidebarActive(
                            $currentPage,
                            'clubs.php'
                        ); ?>"
                    >
                        <span class="sidebar-icon">
                            ◉
                        </span>

                        <span>Clubs</span>
                    </a>

                    <a
                        href="<?= $baseUrl; ?>/admin/officials.php"
                        class="sidebar-link <?= sidebarActive(
                            $currentPage,
                            'officials.php'
                        ); ?>"
                    >
                        <span class="sidebar-icon">
                            ♟
                        </span>

                        <span>Club Officials</span>
                    </a>

                    <a
                        href="<?= $baseUrl; ?>/admin/officers.php"
                        class="sidebar-link <?= sidebarActive(
                            $currentPage,
                            'officers.php'
                        ); ?>"
                    >
                        <span class="sidebar-icon">
                            ♙
                        </span>

                        <span>Officers</span>
                    </a>

                    <a
                        href="<?= $baseUrl; ?>/admin/resources.php"
                        class="sidebar-link <?= sidebarActive(
                            $currentPage,
                            'resources.php'
                        ); ?>"
                    >
                        <span class="sidebar-icon">
                            ▣
                        </span>

                        <span>Resources</span>
                    </a>

                    <a
                        href="<?= $baseUrl; ?>/admin/reports.php"
                        class="sidebar-link <?= sidebarActive(
                            $currentPage,
                            'reports.php'
                        ); ?>"
                    >
                        <span class="sidebar-icon">
                            ◫
                        </span>

                        <span>Reports</span>
                    </a>

                <?php elseif ($currentRole === 'official'): ?>

                    <a
                        href="<?= $baseUrl; ?>/official/dashboard.php"
                        class="sidebar-link <?= sidebarActive(
                            $currentPage,
                            'dashboard.php'
                        ); ?>"
                    >
                        <span class="sidebar-icon">
                            ▦
                        </span>

                        <span>Dashboard</span>
                    </a>

                    <a
                        href="<?= $baseUrl; ?>/official/create_requisition.php"
                        class="sidebar-link <?= sidebarActive(
                            $currentPage,
                            'create_requisition.php'
                        ); ?>"
                    >
                        <span class="sidebar-icon">
                            ＋
                        </span>

                        <span>Create Requisition</span>
                    </a>

                    <a
                        href="<?= $baseUrl; ?>/official/my_requisitions.php"
                        class="sidebar-link <?= sidebarActive(
                            $currentPage,
                            'my_requisitions.php'
                        ); ?>"
                    >
                        <span class="sidebar-icon">
                            ☷
                        </span>

                        <span>My Requisitions</span>
                    </a>

                <?php elseif ($currentRole === 'officer'): ?>

                    <a
                        href="<?= $baseUrl; ?>/officer/dashboard.php"
                        class="sidebar-link <?= sidebarActive(
                            $currentPage,
                            'dashboard.php'
                        ); ?>"
                    >
                        <span class="sidebar-icon">
                            ▦
                        </span>

                        <span>Dashboard</span>
                    </a>

                    <a
                        href="<?= $baseUrl; ?>/officer/pending_requests.php"
                        class="sidebar-link <?= sidebarActive(
                            $currentPage,
                            'pending_requests.php'
                        ); ?>"
                    >
                        <span class="sidebar-icon">
                            ◷
                        </span>

                        <span>Pending Requests</span>
                    </a>

                    <a
                        href="<?= $baseUrl; ?>/officer/delegated_requests.php"
                        class="sidebar-link <?= sidebarActive(
                            $currentPage,
                            'delegated_requests.php'
                        ); ?>"
                    >
                        <span class="sidebar-icon">
                            ⇄
                        </span>

                        <span>Delegated Requests</span>
                    </a>

                <?php endif; ?>

            </div>

            <div class="sidebar-navigation-footer">

                <p class="sidebar-section-label">
                    Account
                </p>

                <?php if ($currentRole === 'official'): ?>

                    <a
                        href="<?= $baseUrl; ?>/official/notifications.php"
                        class="sidebar-link <?= sidebarActive(
                            $currentPage,
                            'notifications.php'
                        ); ?>"
                    >
                        <span class="sidebar-icon">
                            ◔
                        </span>

                        <span>Notifications</span>

                        <?php if (
                            isset($unreadNotificationCount) &&
                            $unreadNotificationCount > 0
                        ): ?>

                            <span class="sidebar-count">
                                <?= (int) $unreadNotificationCount; ?>
                            </span>

                        <?php endif; ?>

                    </a>

                <?php endif; ?>

                <a
                    href="<?= $baseUrl; ?>/<?= htmlspecialchars(
                        $currentRole
                    ); ?>/profile.php"
                    class="sidebar-link <?= sidebarActive(
                        $currentPage,
                        'profile.php'
                    ); ?>"
                >
                    <span class="sidebar-icon">
                        ◎
                    </span>

                    <span>My Profile</span>
                </a>

                <a
                    href="<?= $baseUrl; ?>/logout.php"
                    class="sidebar-link sidebar-logout"
                >
                    <span class="sidebar-icon">
                        ↪
                    </span>

                    <span>Logout</span>
                </a>

            </div>

        </nav>

    </div>

</aside>

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>