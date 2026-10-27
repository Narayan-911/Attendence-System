-- ====================================================================
-- College Attendance Management Platform - Production Database Schema
-- Compatible with AWS RDS MySQL 8.x / 5.7 & Local MySQL
-- ====================================================================

CREATE DATABASE IF NOT EXISTS attendance_db;
USE attendance_db;

-- 1. Teachers / Users table with Role-Based Access Control
CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'teacher'
);

-- 2. Students table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    roll_number VARCHAR(20) UNIQUE NOT NULL,
    class VARCHAR(50) NOT NULL
);

-- 3. Subjects table (Teacher-owned courses)
CREATE TABLE IF NOT EXISTS subjects (
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
);

-- 4. Attendance table
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    date DATE NOT NULL,
    subject VARCHAR(100) NOT NULL DEFAULT 'General',
    status ENUM('Present', 'Absent') NOT NULL,
    teacher_id INT NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_subject_date (student_id, date, subject)
);
