<?php
// Include header
require_once "../includes/header.php";

// Check if user is admin
if(!isAdmin()){
    redirectWithMessage("../login.php", "You are not authorized to access this page.", "danger");
}

// Check if result ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage("results.php", "Please select a result to edit.", "warning");
}

$result_id = intval($_GET['id']);

// Get result details
$result_details = null;
$sql = "SELECT r.*, s.name as student_name, s.father_name, e.name as exam_name, e.total_marks, e.exam_date, 
               e.class_id, c.name as class_name, c.section, sc.name as school_name
        FROM results r 
        JOIN students s ON r.student_id = s.id 
        JOIN exams e ON r.exam_id = e.id
        JOIN classes c ON e.class_id = c.id
        JOIN schools sc ON e.school_id = sc.id
        WHERE r.id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $result_id);
mysqli_stmt_execute($stmt);
$query_result = mysqli_stmt_get_result($stmt);

if($query_result && mysqli_num_rows($query_result) > 0) {
    $result_details = mysqli_fetch_assoc($query_result);
} else {
    redirectWithMessage("results.php", "Result not found.", "danger");
}

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $obtained_marks = intval($_POST['obtained_marks']);
    $remarks = sanitizeInput($_POST['remarks']);
    
    // Validate marks
    if($obtained_marks < 0 || $obtained_marks > $result_details['total_marks']) {
        $error = "Invalid marks. Marks must be between 0 and {$result_details['total_marks']}.";
    } else {
        // Update result
        $update_sql = "UPDATE results SET obtained_marks = ?, remarks = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "isi", $obtained_marks, $remarks, $result_id);
        
        if(mysqli_stmt_execute($update_stmt)) {
            redirectWithMessage("results.php?exam_id={$result_details['exam_id']}", "Result updated successfully.", "success");
        } else {
            $error = "Error updating result: " . mysqli_error($conn);
        }
    }
}
?>

<div class="page-header">
    <h1>Edit Result</h1>
</div>

<!-- Result Details -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-info-circle"></i> Details
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Student:</strong> <?php echo htmlspecialchars($result_details['student_name']); ?></p>
                <p><strong>Father's Name:</strong> <?php echo htmlspecialchars($result_details['father_name']); ?></p>
                <p><strong>Class:</strong> <?php echo htmlspecialchars($result_details['class_name'] . ' ' . $result_details['section']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Exam:</strong> <?php echo htmlspecialchars($result_details['exam_name']); ?></p>
                <p><strong>Exam Date:</strong> <?php echo formatDate($result_details['exam_date']); ?></p>
                <p><strong>Total Marks:</strong> <?php echo $result_details['total_marks']; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Edit Form -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-edit"></i> Edit Result
    </div>
    <div class="card-body">
        <?php if(isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form action="edit_result.php?id=<?php echo $result_id; ?>" method="post">
            <div class="form-group">
                <label for="obtained_marks">Obtained Marks (out of <?php echo $result_details['total_marks']; ?>)</label>
                <input type="number" class="form-control" id="obtained_marks" name="obtained_marks" 
                       min="0" max="<?php echo $result_details['total_marks']; ?>" required
                       value="<?php echo $result_details['obtained_marks']; ?>">
            </div>
            
            <div class="form-group">
                <label for="remarks">Remarks</label>
                <textarea class="form-control" id="remarks" name="remarks" rows="3"><?php echo htmlspecialchars($result_details['remarks']); ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Update Result</button>
            <a href="results.php?exam_id=<?php echo $result_details['exam_id']; ?>" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php
// Include footer
require_once "../includes/footer.php";
?>