<?php
require_once 'includes/auth.php';
require_once 'db.php';

if (is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = in_array($_POST['role'] ?? '', ['teacher', 'student']) ? $_POST['role'] : 'teacher';

    if (empty($username) || empty($email) || empty($password)) {
        $error_msg = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Please provide a valid email address.";
    } elseif (strlen($password) < 6) {
        $error_msg = "Password must be at least 6 characters in length.";
    } else {
        // Verify unique username and email
        $check_stmt = $conn->prepare("SELECT id FROM teachers WHERE username = ? OR email = ?");
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result && $check_result->num_rows > 0) {
            $error_msg = "Username or email is already registered. Please choose another or log in.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO teachers (username, password, email, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $hashed_password, $email, $role);

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: login.php?registered=1");
                exit();
            } else {
                $error_msg = "Registration failed due to a database error. Please try again.";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - College Attendance Platform</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);">
                    🏛️
                </div>
                <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">Create Account</h1>
                <p style="font-size: 0.88rem; color: #64748b; margin-top: 4px;">Join the College Attendance Management Platform</p>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger">
                    <span><?php echo e($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <?php echo csrf_field(); ?>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Choose a unique username" value="<?php echo e($_POST['username'] ?? ''); ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="email">Institutional Email</label>
                    <input type="email" id="email" name="email" placeholder="faculty@college.edu" value="<?php echo e($_POST['email'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Password (Minimum 6 characters)</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label for="role">Register As</label>
                    <select id="role" name="role">
                        <option value="teacher" selected>Faculty / Teacher</option>
                        <option value="student">Student</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="padding: 0.75rem; font-size: 0.95rem; margin-top: 0.5rem;">
                    Complete Registration
                </button>
            </form>

            <div style="margin-top: 1.5rem; text-align: center; font-size: 0.85rem; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
                Already have an account? <a href="login.php" style="color: #2563eb; font-weight: 600; text-decoration: none;">Sign in here</a>
                <br>
                <a href="../index.html" style="display: inline-block; margin-top: 8px; color: #94a3b8; text-decoration: none;">&larr; Back to Home</a>
            </div>
        </div>
    </div>
    <script src="../js/script.js"></script>
</body>
</html>
