<?php
/**
 * Global Header Component
 */
if (!defined('PAGE_TITLE')) {
    define('PAGE_TITLE', 'Attendance Management System');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(PAGE_TITLE); ?> - College Portal</title>
    <link rel="stylesheet" href="../css/style.css">
    <meta name="csrf-token" content="<?php echo e(get_csrf_token()); ?>">
</head>
<body>
    <div class="app-container">
        <!-- Overlay for Mobile Sidebar -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
