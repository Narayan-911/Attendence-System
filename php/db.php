<?php
/**
 * Database Connection & Environment Configuration
 * Compatible with AWS RDS MySQL & Local MySQL (XAMPP/WAMP)
 */

// Load database configuration from Environment variables (AWS RDS) with safe local fallbacks
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: (getenv('DB_PASS') ?: '');
$db   = getenv('DB_NAME') ?: 'attendance_db';
$port = (int)(getenv('DB_PORT') ?: 3306);

// Establish MySQL connection
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    error_log("Database connection failure: " . $conn->connect_error);
    die("Database service is currently unavailable. Please check configuration or try again shortly.");
}

$conn->set_charset("utf8mb4");

// ====================================================================
// Idempotent Auto-Migration: Ensures tables & columns exist seamlessly
// ====================================================================

// 1. Ensure 'role' column exists in 'teachers' table
$role_check = $conn->query("SHOW COLUMNS FROM teachers LIKE 'role'");
if ($role_check && $role_check->num_rows === 0) {
    $conn->query("ALTER TABLE teachers ADD COLUMN role ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'teacher' AFTER email");
}

// 2. Ensure 'subjects' table exists with complete schema
$conn->query("CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(30) NOT NULL,
    description TEXT NULL,
    class_or_semester VARCHAR(50) NULL,
    academic_year VARCHAR(20) NULL,
    status ENUM('active', 'archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_subject_code (teacher_id, subject_code)
)");

// Backward compatibility: If old 'subjects' had column 'name' instead of 'subject_name'
$check_name_col = $conn->query("SHOW COLUMNS FROM subjects LIKE 'subject_name'");
if ($check_name_col && $check_name_col->num_rows === 0) {
    $check_old_col = $conn->query("SHOW COLUMNS FROM subjects LIKE 'name'");
    if ($check_old_col && $check_old_col->num_rows > 0) {
        $conn->query("ALTER TABLE subjects CHANGE COLUMN name subject_name VARCHAR(100) NOT NULL");
    }
}
$check_code_col = $conn->query("SHOW COLUMNS FROM subjects LIKE 'subject_code'");
if ($check_code_col && $check_code_col->num_rows === 0) {
    $check_old_code = $conn->query("SHOW COLUMNS FROM subjects LIKE 'code'");
    if ($check_old_code && $check_old_code->num_rows > 0) {
        $conn->query("ALTER TABLE subjects CHANGE COLUMN code subject_code VARCHAR(30) NOT NULL");
    }
}

// Ensure class_or_semester and academic_year exist in subjects
$check_sem = $conn->query("SHOW COLUMNS FROM subjects LIKE 'class_or_semester'");
if ($check_sem && $check_sem->num_rows === 0) {
    $conn->query("ALTER TABLE subjects ADD COLUMN class_or_semester VARCHAR(50) NULL AFTER description");
}
$check_year = $conn->query("SHOW COLUMNS FROM subjects LIKE 'academic_year'");
if ($check_year && $check_year->num_rows === 0) {
    $conn->query("ALTER TABLE subjects ADD COLUMN academic_year VARCHAR(20) NULL AFTER class_or_semester");
}
$check_status = $conn->query("SHOW COLUMNS FROM subjects LIKE 'status'");
if ($check_status && $check_status->num_rows === 0) {
    $conn->query("ALTER TABLE subjects ADD COLUMN status ENUM('active', 'archived') NOT NULL DEFAULT 'active' AFTER academic_year");
}

// 3. Ensure 'subject' column exists in 'attendance' table
$col_check = $conn->query("SHOW COLUMNS FROM attendance LIKE 'subject'");
if ($col_check && $col_check->num_rows === 0) {
    $conn->query("ALTER TABLE attendance ADD COLUMN subject VARCHAR(100) NOT NULL DEFAULT 'General' AFTER date");
}
?>