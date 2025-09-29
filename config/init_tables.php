<?php
// Include database configuration
require_once 'database.php';

// Create users table
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher') NOT NULL,
    school_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Create schools table
$sql_schools = "CREATE TABLE IF NOT EXISTS schools (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Create classes table
$sql_classes = "CREATE TABLE IF NOT EXISTS classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    section VARCHAR(10),
    school_id INT,
    teacher_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Create students table
$sql_students = "CREATE TABLE IF NOT EXISTS students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    father_name VARCHAR(100),
    gender ENUM('male', 'female', 'other'),
    dob DATE,
    class_id INT,
    admission_date DATE,
    school_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Create attendance table
$sql_attendance = "CREATE TABLE IF NOT EXISTS attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT NOT NULL,
    date DATE NOT NULL,
    present_count INT DEFAULT 0,
    absent_count INT DEFAULT 0,
    details TEXT,
    teacher_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Create attendance_details table for individual student attendance
$sql_attendance_details = "CREATE TABLE IF NOT EXISTS attendance_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    attendance_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('present', 'absent', 'leave') NOT NULL,
    remarks VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Create exams table
$sql_exams = "CREATE TABLE IF NOT EXISTS exams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    class_id INT NOT NULL,
    school_id INT,
    teacher_id INT,
    exam_date DATE NOT NULL,
    total_marks INT NOT NULL DEFAULT 100,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Create results table
$sql_results = "CREATE TABLE IF NOT EXISTS results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    obtained_marks INT NOT NULL,
    grade VARCHAR(5),
    remarks VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Create reports table
$sql_reports = "CREATE TABLE IF NOT EXISTS reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    content TEXT,
    file_path VARCHAR(255),
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Execute the SQL statements
$tables = [
    'users' => $sql_users,
    'schools' => $sql_schools,
    'classes' => $sql_classes,
    'students' => $sql_students,
    'attendance' => $sql_attendance,
    'attendance_details' => $sql_attendance_details,
    'exams' => $sql_exams,
    'results' => $sql_results,
    'reports' => $sql_reports
];

$success = true;
$errors = [];

foreach ($tables as $table => $sql) {
    if (!mysqli_query($conn, $sql)) {
        $success = false;
        $errors[] = "Error creating $table table: " . mysqli_error($conn);
    }
}

// Create default admin user if users table is empty
$check_admin = mysqli_query($conn, "SELECT * FROM users WHERE role='admin' LIMIT 1");
if (mysqli_num_rows($check_admin) == 0) {
    // Default password: admin123
    $default_password = password_hash('admin123', PASSWORD_DEFAULT);
    $sql_default_admin = "INSERT INTO users (name, email, password, role) 
                         VALUES ('Admin', 'admin@seenghar.org', '$default_password', 'admin')";
    
    if (!mysqli_query($conn, $sql_default_admin)) {
        $success = false;
        $errors[] = "Error creating default admin user: " . mysqli_error($conn);
    }
}

// Output results
if ($success) {
    echo "Database tables created successfully!";
    echo "Default admin credentials: admin@seenghar.org / admin123";
} else {
    echo "Errors occurred during table creation:<br>";
    foreach ($errors as $error) {
        echo "- $error<br>";
    }
}
?>