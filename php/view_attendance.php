<?php
require_once 'includes/auth.php';
require_once 'db.php';

require_login();

define('PAGE_TITLE', 'Attendance Records');

$user_id = get_user_id();
$user_role = get_user_role();
$username = get_username();

// Filter & Search Parameters
$filter_date = trim($_GET['date'] ?? '');
$filter_subject = trim($_GET['subject'] ?? 'all');
$filter_status = trim($_GET['status'] ?? 'all');
$filter_search = trim($_GET['search'] ?? '');

// Build Query dynamically with prepared statements
$sql = "SELECT a.id, s.name, s.roll_number, s.class, a.date, COALESCE(a.subject, 'General') AS subject, a.status 
        FROM attendance a 
        JOIN students s ON a.student_id = s.id";
$types = "";
$params = [];

if ($user_role === 'student') {
    $sql .= " WHERE (s.roll_number = ? OR s.name = ?)";
    $types .= "ss";
    $params[] = $username;
    $params[] = $username;
} elseif ($user_role !== 'admin') {
    $sql .= " WHERE a.teacher_id = ?";
    $types .= "i";
    $params[] = $user_id;
} else {
    $sql .= " WHERE 1=1";
}

if (!empty($filter_date)) {
    $sql .= " AND a.date = ?";
    $types .= "s";
    $params[] = $filter_date;
}

if (!empty($filter_subject) && $filter_subject !== 'all') {
    $sql .= " AND a.subject = ?";
    $types .= "s";
    $params[] = $filter_subject;
}

if ($filter_status === 'Present' || $filter_status === 'Absent') {
    $sql .= " AND a.status = ?";
    $types .= "s";
    $params[] = $filter_status;
}

if (!empty($filter_search)) {
    $sql .= " AND (s.name LIKE ? OR s.roll_number LIKE ?)";
    $types .= "ss";
    $search_wildcard = "%" . $filter_search . "%";
    $params[] = $search_wildcard;
    $params[] = $search_wildcard;
}

$sql .= " ORDER BY a.date DESC, a.subject ASC, s.roll_number ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$records = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}
$stmt->close();

// Fetch distinct subjects for filter dropdown
if ($user_role === 'admin') {
    $subj_sql = "SELECT DISTINCT COALESCE(subject, 'General') AS subject FROM attendance ORDER BY subject ASC";
    $subj_res = $conn->query($subj_sql);
} elseif ($user_role === 'student') {
    $subj_sql = "SELECT DISTINCT COALESCE(subject, 'General') AS subject FROM attendance a JOIN students s ON a.student_id = s.id WHERE s.roll_number = ? OR s.name = ? ORDER BY subject ASC";
    $stmt_sub = $conn->prepare($subj_sql);
    $stmt_sub->bind_param("ss", $username, $username);
    $stmt_sub->execute();
    $subj_res = $stmt_sub->get_result();
} else {
    $subj_sql = "SELECT DISTINCT COALESCE(subject, 'General') AS subject FROM attendance WHERE teacher_id = ? ORDER BY subject ASC";
    $stmt_sub = $conn->prepare($subj_sql);
    $stmt_sub->bind_param("i", $user_id);
    $stmt_sub->execute();
    $subj_res = $stmt_sub->get_result();
}
$available_subjects = [];
if ($subj_res) {
    while ($sub = $subj_res->fetch_assoc()) {
        $available_subjects[] = $sub['subject'];
    }
}

// FEATURE: Export to CSV (with UTF-8 BOM for Excel)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'Attendance_Report_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    // UTF-8 BOM
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['#', 'Student Name', 'Roll Number', 'Class', 'Course / Subject', 'Date', 'Status']);
    
    $index = 1;
    foreach ($records as $rec) {
        fputcsv($output, [
            $index++,
            $rec['name'],
            $rec['roll_number'],
            $rec['class'],
            $rec['subject'],
            $rec['date'],
            $rec['status']
        ]);
    }
    fclose($output);
    exit();
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="content-container">
    <div class="page-header">
        <div>
            <h1 class="page-title">Attendance Records & Reports</h1>
            <p class="page-subtitle">Showing <?php echo count($records); ?> roll call entries matching your criteria.</p>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <?php if ($user_role === 'teacher' || $user_role === 'admin'): ?>
                <a href="mark_attendance.php" class="btn btn-primary">+ Mark Attendance</a>
            <?php endif; ?>
            <?php 
                $query_params = $_GET;
                $query_params['export'] = 'csv';
                $export_url = 'view_attendance.php?' . http_build_query($query_params);
            ?>
            <a href="<?php echo e($export_url); ?>" class="btn btn-secondary" title="Export matching records to CSV">
                📥 Export CSV
            </a>
        </div>
    </div>

    <!-- Filter & Search Toolbar Card -->
    <div class="card" style="padding: 1.25rem;">
        <form method="GET" action="view_attendance.php" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
            <div class="form-group" style="margin-bottom: 0; min-width: 160px; flex: 1;">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" value="<?php echo e($filter_date); ?>">
            </div>

            <div class="form-group" style="margin-bottom: 0; min-width: 180px; flex: 1;">
                <label for="subject">Course / Subject</label>
                <select id="subject" name="subject">
                    <option value="all">All Subjects</option>
                    <?php foreach ($available_subjects as $s): ?>
                        <option value="<?php echo e($s); ?>" <?php echo ($filter_subject === $s) ? 'selected' : ''; ?>>
                            <?php echo e($s); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0; min-width: 150px; flex: 1;">
                <label for="status">Attendance Status</label>
                <select id="status" name="status">
                    <option value="all" <?php echo ($filter_status === 'all') ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="Present" <?php echo ($filter_status === 'Present') ? 'selected' : ''; ?>>Present Only</option>
                    <option value="Absent" <?php echo ($filter_status === 'Absent') ? 'selected' : ''; ?>>Absent Only</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0; min-width: 200px; flex: 1.5;">
                <label for="search">Student Name or Roll Number</label>
                <input type="text" id="search" name="search" placeholder="Search..." value="<?php echo e($filter_search); ?>">
            </div>

            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.2rem;">Filter</button>
                <?php if (!empty($filter_date) || ($filter_subject !== 'all' && !empty($filter_subject)) || $filter_status !== 'all' || !empty($filter_search)): ?>
                    <a href="view_attendance.php" class="btn btn-secondary" style="padding: 0.65rem 1rem;">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Attendance Table Card -->
    <div class="card">
        <?php if (empty($records)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <h3>No Attendance Records Found</h3>
                <p style="margin: 0.5rem 0 1.25rem;">
                    <?php if (!empty($filter_date) || $filter_status !== 'all' || !empty($filter_search)): ?>
                        No records matched your search filters. Try clearing your filter criteria.
                    <?php else: ?>
                        No attendance logs have been recorded in the database yet.
                    <?php endif; ?>
                </p>
                <?php if (!empty($filter_date) || $filter_status !== 'all' || !empty($filter_search)): ?>
                    <a href="view_attendance.php" class="btn btn-secondary btn-sm">Clear Filters</a>
                <?php elseif ($user_role === 'teacher' || $user_role === 'admin'): ?>
                    <a href="mark_attendance.php" class="btn btn-primary btn-sm">Take Roll Call Now</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Roll Number</th>
                            <th>Class</th>
                            <th>Course / Subject</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($records as $row): ?>
                            <tr>
                                <td style="color: var(--text-muted); font-size: 0.85rem;"><?php echo $i++; ?></td>
                                <td><strong style="color: var(--navy-dark);"><?php echo e($row['name']); ?></strong></td>
                                <td><?php echo e($row['roll_number']); ?></td>
                                <td><?php echo e($row['class']); ?></td>
                                <td>
                                    <span class="student-class-badge" style="background: var(--primary-light); color: var(--primary);">
                                        <?php echo e($row['subject']); ?>
                                    </span>
                                </td>
                                <td><?php echo e(date('M d, Y', strtotime($row['date']))); ?></td>
                                <td>
                                    <?php if ($row['status'] === 'Present'): ?>
                                        <span class="badge badge-present">🟢 Present</span>
                                    <?php else: ?>
                                        <span class="badge badge-absent">🔴 Absent</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
