<?php
/**
 * Global Footer Component
 */
?>
        <!-- Footer Area -->
        <footer style="margin-top: auto; padding: 1.5rem; text-align: center; font-size: 0.82rem; color: var(--text-muted); border-top: 1px solid var(--border-color); background: #ffffff;">
            <span>College Attendance Management Platform &copy; <?php echo date('Y'); ?>. All Rights Reserved.</span>
        </footer>
    </div><!-- End .main-wrapper -->
</div><!-- End .app-container -->

<script src="../js/script.js"></script>
<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('appSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('open');
    }
</script>
</body>
</html>
