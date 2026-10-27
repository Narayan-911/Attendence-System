<?php
/**
 * Modern Navy Sidebar & Mobile Header Component
 */
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = get_user_role();
$username = get_username();

$name_parts = explode(' ', trim($username));
$initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
if (empty($initials)) {
    $initials = 'U';
}
?>

<!-- Sidebar Navigation -->
<aside class="sidebar" id="appSidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="sidebar-brand">
            <div class="sidebar-brand-icon">🏛️</div>
            <span>CampusRoll</span>
        </a>
    </div>

    <!-- User Profile Badge -->
    <div class="sidebar-user">
        <div class="user-avatar"><?php echo e($initials); ?></div>
        <div class="user-info">
            <span class="user-name" title="<?php echo e($username); ?>"><?php echo e($username); ?></span>
            <span class="user-role-badge <?php echo e($user_role); ?>"><?php echo e($user_role); ?></span>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <div class="nav-section-title">Main</div>
        <a href="dashboard.php" class="nav-item <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
            <span class="nav-item-icon">📊</span>
            <span>Dashboard</span>
        </a>

        <?php if ($user_role === 'teacher' || $user_role === 'admin'): ?>
            <div class="nav-section-title">Academic & Attendance</div>
            <a href="mark_attendance.php" class="nav-item <?php echo ($current_page === 'mark_attendance.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">✏️</span>
                <span>Mark Attendance</span>
            </a>
            <a href="manage_subjects.php" class="nav-item <?php echo ($current_page === 'manage_subjects.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">📚</span>
                <span><?php echo ($user_role === 'admin') ? 'All Subjects' : 'My Subjects'; ?></span>
            </a>
            <a href="view_attendance.php" class="nav-item <?php echo ($current_page === 'view_attendance.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">📑</span>
                <span>Attendance Records</span>
            </a>

            <div class="nav-section-title">Directory</div>
            <a href="add_student.php" class="nav-item <?php echo ($current_page === 'add_student.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">👤</span>
                <span>Add Student</span>
            </a>
        <?php else: /* Student role */ ?>
            <div class="nav-section-title">My Records</div>
            <a href="view_attendance.php" class="nav-item <?php echo ($current_page === 'view_attendance.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">📑</span>
                <span>My Attendance</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="nav-item logout">
            <span class="nav-item-icon">🚪</span>
            <span>Sign Out</span>
        </a>
    </div>
</aside>

<!-- Main Wrapper with Mobile Topbar -->
<div class="main-wrapper">
    <!-- Mobile Topbar Header -->
    <header class="mobile-topbar">
        <div style="display: flex; align-items: center; gap: 10px;">
            <button type="button" class="mobile-toggle-btn" onclick="toggleSidebar()" aria-label="Toggle Navigation">
                ☰
            </button>
            <span style="font-weight: 700; font-size: 1.05rem;">CampusRoll</span>
        </div>
        <span class="user-role-badge <?php echo e($user_role); ?>" style="font-size: 0.68rem;">
            <?php echo e($user_role); ?>
        </span>
    </header>
