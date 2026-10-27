<?php
require_once 'includes/auth.php';
require_once 'db.php';

require_login();

define('PAGE_TITLE', 'Dashboard');

$user_id = get_user_id();
$user_role = get_user_role();
$username = get_username();

// Gather role-based statistics
if ($user_role === 'admin') {
    // Admin stats
    $tot_teachers = $conn->query("SELECT COUNT(*) as total FROM teachers WHERE role = 'teacher'")->fetch_assoc()['total'] ?? 0;
    $tot_students = $conn->query("SELECT COUNT(*) as total FROM students")->fetch_assoc()['total'] ?? 0;
    $tot_subjects = $conn->query("SELECT COUNT(*) as total FROM subjects WHERE status = 'active'")->fetch_assoc()['total'] ?? 0;
    $tot_records  = $conn->query("SELECT COUNT(*) as total FROM attendance")->fetch_assoc()['total'] ?? 0;
    
    // Overall Present rate
    $all_att = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) as present FROM attendance")->fetch_assoc();
    $overall_rate = ($all_att['total'] > 0) ? round(($all_att['present'] / $all_att['total']) * 100) . '%' : 'N/A';
} elseif ($user_role === 'student') {
    // Student stats (match student by username or name)
    $stu_find = $conn->prepare("SELECT id, name, roll_number FROM students WHERE roll_number = ? OR name = ?");
    $stu_find->bind_param("ss", $username, $username);
    $stu_find->execute();
    $student_record = $stu_find->get_result()->fetch_assoc();
    $stu_find->close();

    $stu_id = $student_record['id'] ?? 0;
    $stu_att = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) as present FROM attendance WHERE student_id = " . (int)$stu_id)->fetch_assoc();
    $total_classes = (int)($stu_att['total'] ?? 0);
    $attended_classes = (int)($stu_att['present'] ?? 0);
    $overall_rate = ($total_classes > 0) ? round(($attended_classes / $total_classes) * 100) . '%' : 'N/A';
} else {
    // Teacher stats
    $students_query = $conn->query("SELECT COUNT(*) AS total FROM students");
    $tot_students = $students_query ? ($students_query->fetch_assoc()['total'] ?? 0) : 0;

    $stmt_subs = $conn->prepare("SELECT COUNT(*) AS total FROM subjects WHERE teacher_id = ? AND status = 'active'");
    $stmt_subs->bind_param("i", $user_id);
    $stmt_subs->execute();
    $tot_subjects = $stmt_subs->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt_subs->close();

    $stmt_records = $conn->prepare("SELECT COUNT(*) AS total FROM attendance WHERE teacher_id = ?");
    $stmt_records->bind_param("i", $user_id);
    $stmt_records->execute();
    $tot_records = $stmt_records->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt_records->close();

    // Today's stats
    $today = date('Y-m-d');
    $stmt_today = $conn->prepare("SELECT 
        COUNT(*) AS total_today,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS absent_count
        FROM attendance 
        WHERE teacher_id = ? AND date = ?");
    $stmt_today->bind_param("is", $user_id, $today);
    $stmt_today->execute();
    $today_stats = $stmt_today->get_result()->fetch_assoc();
    $stmt_today->close();

    $today_total = (int)($today_stats['total_today'] ?? 0);
    $today_present = (int)($today_stats['present_count'] ?? 0);
    $today_absent = (int)($today_stats['absent_count'] ?? 0);
    $today_rate = $today_total > 0 ? round(($today_present / $today_total) * 100) . '%' : 'No entries';

    // Recent 5 attendance logs
    $recent_stmt = $conn->prepare("SELECT a.date, a.subject, a.status, s.name as student_name, s.roll_number 
                                   FROM attendance a 
                                   JOIN students s ON a.student_id = s.id 
                                   WHERE a.teacher_id = ? 
                                   ORDER BY a.id DESC LIMIT 5");
    $recent_stmt->bind_param("i", $user_id);
    $recent_stmt->execute();
    $recent_logs = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $recent_stmt->close();
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="content-container">
    <!-- Welcome Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Welcome back, <?php echo e($username); ?>!</h1>
            <p class="page-subtitle">College Attendance & Academic Overview &bull; <?php echo date('l, F j, Y'); ?></p>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <?php if ($user_role === 'teacher' || $user_role === 'admin'): ?>
                <a href="manage_subjects.php" class="btn btn-secondary">+ Add Subject</a>
                <a href="mark_attendance.php" class="btn btn-primary">+ Mark Attendance</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Role-Based Statistics Widgets -->
    <?php if ($user_role === 'admin'): ?>
        <div class="stats-grid">
            <div class="stat-widget">
                <span class="stat-label">Total Faculty</span>
                <span class="stat-number"><?php echo $tot_teachers; ?></span>
                <span class="stat-hint">Active instructors</span>
            </div>
            <div class="stat-widget">
                <span class="stat-label">Total Enrolled Students</span>
                <span class="stat-number"><?php echo $tot_students; ?></span>
                <span class="stat-hint">Across all departments</span>
            </div>
            <div class="stat-widget">
                <span class="stat-label">Active Courses</span>
                <span class="stat-number"><?php echo $tot_subjects; ?></span>
                <span class="stat-hint">Authorized subjects</span>
            </div>
            <div class="stat-widget success">
                <span class="stat-label">Overall Attendance Rate</span>
                <span class="stat-number"><?php echo $overall_rate; ?></span>
                <span class="stat-hint"><?php echo $tot_records; ?> total records logged</span>
            </div>
        </div>
    <?php elseif ($user_role === 'student'): ?>
        <div class="stats-grid">
            <div class="stat-widget success">
                <span class="stat-label">Attendance Rate</span>
                <span class="stat-number"><?php echo $overall_rate; ?></span>
                <span class="stat-hint"><?php echo $attended_classes; ?> of <?php echo $total_classes; ?> classes attended</span>
            </div>
            <div class="stat-widget">
                <span class="stat-label">Total Sessions Logged</span>
                <span class="stat-number"><?php echo $total_classes; ?></span>
                <span class="stat-hint">Roll call records</span>
            </div>
        </div>
    <?php else: /* Teacher */ ?>
        <div class="stats-grid">
            <div class="stat-widget">
                <span class="stat-label">My Active Subjects</span>
                <span class="stat-number" style="color: var(--primary);"><?php echo $tot_subjects; ?></span>
                <span class="stat-hint">Courses taught by you</span>
            </div>
            <div class="stat-widget">
                <span class="stat-label">Total Students</span>
                <span class="stat-number"><?php echo $tot_students; ?></span>
                <span class="stat-hint">College enrollment</span>
            </div>
            <div class="stat-widget success">
                <span class="stat-label">Today's Present</span>
                <span class="stat-number" style="color: var(--success);"><?php echo $today_present; ?></span>
                <span class="stat-hint"><?php echo $today_absent; ?> absent today</span>
            </div>
            <div class="stat-widget warning">
                <span class="stat-label">Today's Attendance Rate</span>
                <span class="stat-number"><?php echo $today_rate; ?></span>
                <span class="stat-hint"><?php echo $today_total; ?> students recorded today</span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Quick Action Grid -->
    <?php if ($user_role === 'teacher' || $user_role === 'admin'): ?>
        <div style="margin-bottom: 2rem;">
            <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--navy-dark); margin-bottom: 1rem;">
                Core Operations
            </h2>
            <div class="action-grid">
                <a href="mark_attendance.php" class="action-card">
                    <div class="action-card-header">
                        <span class="action-card-icon">✏️</span>
                        <span>Mark Attendance</span>
                    </div>
                    <p>Conduct roll call for your authorized subjects with interactive student cards and instant saving.</p>
                </a>

                <a href="manage_subjects.php" class="action-card">
                    <div class="action-card-header">
                        <span class="action-card-icon">📚</span>
                        <span>Subject Management</span>
                    </div>
                    <p>Create new academic subjects, manage course codes, and review enrolled syllabus data.</p>
                </a>

                <a href="view_attendance.php" class="action-card">
                    <div class="action-card-header">
                        <span class="action-card-icon">📊</span>
                        <span>Reports & CSV Export</span>
                    </div>
                    <p>Search, filter records by course or date, and download official attendance reports.</p>
                </a>

                <a href="add_student.php" class="action-card">
                    <div class="action-card-header">
                        <span class="action-card-icon">👤</span>
                        <span>Enroll New Student</span>
                    </div>
                    <p>Register incoming students with unique roll numbers and class assignments.</p>
                </a>
            </div>
        </div>

        <!-- Recent Activity Feed -->
        <?php if (!empty($recent_logs)): ?>
            <div class="card">
                <div class="card-header-clean">
                    <h2 class="card-title">Recent Attendance Logs</h2>
                    <a href="view_attendance.php" class="btn btn-sm btn-secondary">View All Records &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Roll Number</th>
                                <th>Course / Subject</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): ?>
                                <tr>
                                    <td><strong><?php echo e($log['student_name']); ?></strong></td>
                                    <td><?php echo e($log['roll_number']); ?></td>
                                    <td>
                                        <span class="student-class-badge" style="background: var(--primary-light); color: var(--primary);">
                                            <?php echo e($log['subject']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo e(date('M d, Y', strtotime($log['date']))); ?></td>
                                    <td>
                                        <?php if ($log['status'] === 'Present'): ?>
                                            <span class="badge badge-present">Present</span>
                                        <?php else: ?>
                                            <span class="badge badge-absent">Absent</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
