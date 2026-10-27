<?php
require_once 'includes/auth.php';
require_once 'db.php';

if (is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}

$error_msg = '';
$success_msg = '';

if (isset($_GET['registered'])) {
    $success_msg = "Account created successfully! Please sign in with your credentials.";
}
if (isset($_GET['logout'])) {
    $success_msg = "You have been logged out securely.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_msg = "Please enter both username and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role FROM teachers WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Prevent session fixation
                session_regenerate_id(true);

                $_SESSION['teacher_id'] = $user['id'];
                $_SESSION['teacher_username'] = $user['username'];
                $_SESSION['role'] = !empty($user['role']) ? $user['role'] : 'teacher';

                header("Location: dashboard.php");
                exit();
            } else {
                $error_msg = "Invalid password. Please check your credentials.";
            }
        } else {
            $error_msg = "No account found with that username.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - College Attendance Platform</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);">
                    🏛️
                </div>
                <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">CampusRoll Portal</h1>
                <p style="font-size: 0.88rem; color: #64748b; margin-top: 4px;">Sign in to access your attendance dashboard</p>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger">
                    <span><?php echo e($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success">
                    <span><?php echo e($success_msg); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <?php echo csrf_field(); ?>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" value="<?php echo e($_POST['username'] ?? ''); ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="padding: 0.75rem; font-size: 0.95rem; margin-top: 0.5rem;">
                    Sign In
                </button>
            </form>

            <div style="margin-top: 1.5rem; text-align: center; font-size: 0.85rem; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
                New faculty member? <a href="register.php" style="color: #2563eb; font-weight: 600; text-decoration: none;">Create an account</a>
                <br>
                <a href="../index.html" style="display: inline-block; margin-top: 8px; color: #94a3b8; text-decoration: none;">&larr; Back to Home</a>
            </div>
        </div>
    </div>
    <script src="../js/script.js"></script>
</body>
</html>
