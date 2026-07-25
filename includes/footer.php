        </main>

        <footer class="app-footer">

            <p>
                &copy;
                <?= date('Y'); ?>
                College Resource Requisition System
            </p>

            <span>
                Secure digital approval platform
            </span>

        </footer>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const sidebar =
        document.getElementById('sidebar');

    const overlay =
        document.getElementById('sidebarOverlay');

    const menuButton =
        document.getElementById('mobileMenuButton');

    const closeButton =
        document.getElementById('sidebarCloseButton');

    function openSidebar() {

        if (!sidebar || !overlay) {
            return;
        }

        sidebar.classList.add('show');
        overlay.classList.add('show');

        document.body.classList.add(
            'navigation-open'
        );
    }

    function closeSidebar() {

        if (!sidebar || !overlay) {
            return;
        }

        sidebar.classList.remove('show');
        overlay.classList.remove('show');

        document.body.classList.remove(
            'navigation-open'
        );
    }

    if (menuButton) {
        menuButton.addEventListener(
            'click',
            openSidebar
        );
    }

    if (closeButton) {
        closeButton.addEventListener(
            'click',
            closeSidebar
        );
    }

    if (overlay) {
        overlay.addEventListener(
            'click',
            closeSidebar
        );
    }

    window.addEventListener(
        'resize',
        function () {

            if (window.innerWidth > 900) {
                closeSidebar();
            }
        }
    );
});
</script>

</body>
</html>