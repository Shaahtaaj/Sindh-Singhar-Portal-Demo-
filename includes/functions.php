<?php
// Include database configuration
require_once __DIR__ . '/../config/database.php';

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

// Function to check if user is teacher
function isTeacher() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'teacher';
}

// Function to redirect with message
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header("Location: $url");
    exit();
}

// Function to display message
function displayMessage() {
    if(isset($_SESSION['message'])) {
        $type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : 'info';
        $message = $_SESSION['message'];
        
        // Clear the message from session
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        
        return "<div class='alert alert-$type'>$message</div>";
    }
    return '';
}

// Function to sanitize input data
function sanitizeInput($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    if($conn) {
        $data = mysqli_real_escape_string($conn, $data);
    }
    return $data;
}

// Function to get user details by ID
function getUserById($userId) {
    global $conn;
    $sql = "SELECT * FROM users WHERE id = $userId";
    $result = mysqli_query($conn, $sql);
    
    if($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

// Function to get school details by ID
function getSchoolById($schoolId) {
    global $conn;
    $sql = "SELECT * FROM schools WHERE id = $schoolId";
    $result = mysqli_query($conn, $sql);
    
    if($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

// Function to get class details by ID
function getClassById($classId) {
    global $conn;
    $sql = "SELECT * FROM classes WHERE id = $classId";
    $result = mysqli_query($conn, $sql);
    
    if($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

// Function to get student details by ID
function getStudentById($studentId) {
    global $conn;
    $sql = "SELECT * FROM students WHERE id = $studentId";
    $result = mysqli_query($conn, $sql);
    
    if($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

// Function to get all classes for a school
function getClassesBySchool($schoolId) {
    global $conn;
    $sql = "SELECT * FROM classes WHERE school_id = $schoolId ORDER BY name, section";
    $result = mysqli_query($conn, $sql);
    
    $classes = [];
    if($result) {
        while($row = mysqli_fetch_assoc($result)) {
            $classes[] = $row;
        }
    }
    return $classes;
}

// Function to get all students in a class
function getStudentsByClass($classId) {
    global $conn;
    $sql = "SELECT * FROM students WHERE class_id = $classId ORDER BY name";
    $result = mysqli_query($conn, $sql);
    
    $students = [];
    if($result) {
        while($row = mysqli_fetch_assoc($result)) {
            $students[] = $row;
        }
    }
    return $students;
}

// Function to get teacher's classes
function getTeacherClasses($teacherId) {
    global $conn;
    $sql = "SELECT * FROM classes WHERE teacher_id = $teacherId ORDER BY name, section";
    $result = mysqli_query($conn, $sql);
    
    $classes = [];
    if($result) {
        while($row = mysqli_fetch_assoc($result)) {
            $classes[] = $row;
        }
    }
    return $classes;
}

// Function to format date for display
function formatDate($date, $format = 'd-m-Y') {
    return date($format, strtotime($date));
}
?>