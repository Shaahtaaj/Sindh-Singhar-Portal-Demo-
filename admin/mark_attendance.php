<?php
// Include header
require_once "../includes/header.php";

// Check if user is admin
if(!isAdmin()){
    redirectWithMessage("../login.php", "You are not authorized to access this page.", "danger");
}

// Check if form is submitted
if(isset($_POST['mark_attendance'])) {
    // Get form data
    $class_id = intval($_POST['class_id']);
    $date = sanitizeInput($_POST['date']);
    $statuses = $_POST['status'];
    $remarks = isset($_POST['remarks']) ? $_POST['remarks'] : [];
    
    // Validate input
    if(empty($class_id) || empty($date)) {
        redirectWithMessage("attendance.php", "Class and date are required.", "danger");
    }
    
    // Check if attendance already exists for this class and date
    $sql = "SELECT id FROM attendance WHERE class_id = ? AND date = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $class_id, $date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if($result && mysqli_num_rows($result) > 0) {
        // Attendance already exists, update it
        $attendance = mysqli_fetch_assoc($result);
        $attendance_id = $attendance['id'];
        
        // Delete existing attendance details
        $sql = "DELETE FROM attendance_details WHERE attendance_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $attendance_id);
        mysqli_stmt_execute($stmt);
    } else {
        // Create new attendance record
        $teacher_id = $_SESSION['user_id'];
        
        $sql = "INSERT INTO attendance (class_id, date, teacher_id) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isi", $class_id, $date, $teacher_id);
        
        if(!mysqli_stmt_execute($stmt)) {
            redirectWithMessage("attendance.php", "Error creating attendance record: " . mysqli_error($conn), "danger");
        }
        
        $attendance_id = mysqli_insert_id($conn);
    }
    
    // Insert attendance details
    $success_count = 0;
    $error_count = 0;
    
    foreach($statuses as $student_id => $status) {
        $remark = isset($remarks[$student_id]) ? sanitizeInput($remarks[$student_id]) : '';
        
        $sql = "INSERT INTO attendance_details (attendance_id, student_id, status, remarks) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiss", $attendance_id, $student_id, $status, $remark);
        
        if(mysqli_stmt_execute($stmt)) {
            $success_count++;
        } else {
            $error_count++;
        }
    }
    
    // Redirect with message
    if($success_count > 0) {
        $message = "Attendance marked successfully for $success_count students.";
        if($error_count > 0) {
            $message .= " $error_count errors occurred.";
        }
        redirectWithMessage("attendance.php?class_id=$class_id&date=$date", $message, ($error_count > 0) ? "warning" : "success");
    } else {
        redirectWithMessage("attendance.php?class_id=$class_id&date=$date", "Error marking attendance.", "danger");
    }
} else {
    // If not submitted via form, redirect to attendance page
    redirectWithMessage("attendance.php", "Invalid request.", "danger");
}
?>