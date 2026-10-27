<?php
require_once 'includes/auth.php';
require_once 'db.php';

require_role(['teacher', 'admin']);

define('PAGE_TITLE', 'Add Student');

$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $name = trim($_POST['name'] ?? '');
    $roll_number = strtoupper(trim($_POST['roll_number'] ?? ''));
    $class = trim($_POST['class'] ?? '');

    if (empty($name) || empty($roll_number) || empty($class)) {
        $error_msg = "Please fill in all student details.";
    } else {
        // Check if roll number already exists
        $check_stmt = $conn->prepare("SELECT id FROM students WHERE roll_number = ?");
        $check_stmt->bind_param("s", $roll_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result && $check_result->num_rows > 0) {
            $error_msg = "A student with Roll Number '" . e($roll_number) . "' already exists in the system.";
        } else {
            $stmt = $conn->prepare("INSERT INTO students (name, roll_number, class) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $roll_number, $class);
            if ($stmt->execute()) {
                $success_msg = "Student <strong>" . e($name) . "</strong> (" . e($roll_number) . ") has been enrolled successfully!";
                $_POST = [];
            } else {
                $error_msg = "Could not enroll student: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Fetch total count of students
$count_res = $conn->query("SELECT COUNT(*) as total FROM students");
$total_students = $count_res ? ($count_res->fetch_assoc()['total'] ?? 0) : 0;

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="content-container">
    <div class="page-header">
        <div>
            <h1 class="page-title">Enroll Student</h1>
            <p class="page-subtitle">Register new students with roll numbers and department/class assignments.</p>
        </div>
        <div>
            <span class="badge badge-active" style="padding: 0.5rem 1rem; font-size: 0.88rem;">
                Total Enrolled: <?php echo $total_students; ?> Students
            </span>
        </div>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger">
            <span><?php echo $error_msg; ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success">
            <span><?php echo $success_msg; ?></span>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width: 650px;">
        <div class="card-header-clean">
            <h2 class="card-title">Student Registration Form</h2>
        </div>

        <form method="POST" action="add_student.php">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label for="name">Student Full Name *</label>
                <input type="text" id="name" name="name" placeholder="e.g. Alexander Mitchell" value="<?php echo e($_POST['name'] ?? ''); ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="roll_number">Institutional Roll Number *</label>
                <input type="text" id="roll_number" name="roll_number" placeholder="e.g. CS-2026-042" value="<?php echo e($_POST['roll_number'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="class">Class / Section / Semester *</label>
                <input type="text" id="class" name="class" placeholder="e.g. B.Tech CS - Sem 5" value="<?php echo e($_POST['class'] ?? ''); ?>" required>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" style="padding: 0.7rem 1.5rem;">
                    Enroll Student
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
