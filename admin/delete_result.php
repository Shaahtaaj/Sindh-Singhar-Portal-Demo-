<?php
// Include header
require_once "../includes/header.php";

// Check if user is admin
if(!isAdmin()){
    redirectWithMessage("../login.php", "You are not authorized to access this page.", "danger");
}

// Check if result ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage("results.php", "Please select a result to delete.", "warning");
}

$result_id = intval($_GET['id']);

// Get result details for redirection after deletion
$sql = "SELECT exam_id FROM results WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $result_id);
mysqli_stmt_execute($stmt);
$query_result = mysqli_stmt_get_result($stmt);

if($query_result && mysqli_num_rows($query_result) > 0) {
    $result_details = mysqli_fetch_assoc($query_result);
    $exam_id = $result_details['exam_id'];
    
    // Delete the result
    $delete_sql = "DELETE FROM results WHERE id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($delete_stmt, "i", $result_id);
    
    if(mysqli_stmt_execute($delete_stmt)) {
        redirectWithMessage("results.php?exam_id=$exam_id", "Result deleted successfully.", "success");
    } else {
        redirectWithMessage("results.php?exam_id=$exam_id", "Error deleting result: " . mysqli_error($conn), "danger");
    }
} else {
    redirectWithMessage("results.php", "Result not found.", "danger");
}
?>