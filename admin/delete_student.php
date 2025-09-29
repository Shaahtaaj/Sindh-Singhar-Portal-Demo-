<?php
// Include header
require_once "../includes/header.php";

// Check if user is admin
if(!isAdmin()){
    redirectWithMessage("../login.php", "You are not authorized to access this page.", "danger");
}

// Check if student ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage("students.php", "Student ID is required.", "danger");
}

$student_id = intval($_GET['id']);

// Get student details before deletion (for confirmation message)
$sql = "SELECT name FROM students WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result) == 0) {
    redirectWithMessage("students.php", "Student not found.", "danger");
}

$student = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Delete student
$sql = "DELETE FROM students WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $student_id);

if(mysqli_stmt_execute($stmt)) {
    // Also delete related records (attendance, results)
    $sql = "DELETE FROM attendance_details WHERE student_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    
    $sql = "DELETE FROM results WHERE student_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    
    redirectWithMessage("students.php", "Student '" . htmlspecialchars($student['name']) . "' deleted successfully.", "success");
} else {
    redirectWithMessage("students.php", "Error deleting student: " . mysqli_error($conn), "danger");
}

mysqli_stmt_close($stmt);
?>