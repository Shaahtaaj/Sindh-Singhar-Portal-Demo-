<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirectWithMessage("../login.php", "Please log in to continue.", "danger");
}

if (!isAdmin()) {
    redirectWithMessage("../login.php", "You are not authorized to access this page.", "danger");
}

// Ensure results table has expected columns
$expectedResultColumns = [
    'obtained_marks' => "ALTER TABLE results ADD COLUMN obtained_marks INT NOT NULL AFTER student_id",
    'remarks' => "ALTER TABLE results ADD COLUMN remarks VARCHAR(255) NULL AFTER obtained_marks"
];

$columnCheckSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'results'";
$columnStmt = mysqli_prepare($conn, $columnCheckSql);
$schemaName = DB_NAME;
if ($columnStmt) {
    mysqli_stmt_bind_param($columnStmt, "s", $schemaName);
    mysqli_stmt_execute($columnStmt);
    $columnResult = mysqli_stmt_get_result($columnStmt);
    if ($columnResult) {
        $existingColumns = [];
        while ($columnRow = mysqli_fetch_assoc($columnResult)) {
            $existingColumns[] = $columnRow['COLUMN_NAME'];
        }

        $hasMarksColumn = in_array('marks', $existingColumns, true);
        $hasObtainedMarks = in_array('obtained_marks', $existingColumns, true);

        if ($hasMarksColumn && !$hasObtainedMarks) {
            mysqli_query($conn, "ALTER TABLE results CHANGE COLUMN marks obtained_marks INT NOT NULL");
            $existingColumns[] = 'obtained_marks';
        }

        foreach ($expectedResultColumns as $column => $alterSql) {
            if (!in_array($column, $existingColumns, true)) {
                mysqli_query($conn, $alterSql);
            }
        }
    }
    mysqli_stmt_close($columnStmt);
}

require_once "../includes/header.php";

// Check if exam_id is provided
if(!isset($_GET['exam_id']) || empty($_GET['exam_id'])) {
    redirectWithMessage("results.php", "Please select an exam first.", "warning");
}

$exam_id = intval($_GET['exam_id']);

// Get exam details
$exam_details = null;
$sql = "SELECT e.*, c.name as class_name, c.section, s.name as school_name 
        FROM exams e 
        JOIN classes c ON e.class_id = c.id 
        JOIN schools s ON e.school_id = s.id
        WHERE e.id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $exam_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if($result && mysqli_num_rows($result) > 0) {
    $exam_details = mysqli_fetch_assoc($result);
} else {
    redirectWithMessage("results.php", "Exam not found.", "danger");
}

// Get students in the class
$students = [];
$sql = "SELECT * FROM students WHERE class_id = ? ORDER BY name";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $exam_details['class_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
}

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_ids = $_POST['student_id'];
    $obtained_marks = $_POST['obtained_marks'];
    $remarks = $_POST['remarks'];
    
    $success_count = 0;
    $error_count = 0;
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Insert results for each student
        for($i = 0; $i < count($student_ids); $i++) {
            $student_id = intval($student_ids[$i]);
            $marks = intval($obtained_marks[$i]);
            $remark = sanitizeInput($remarks[$i]);
            
            // Validate marks
            if($marks < 0 || $marks > $exam_details['total_marks']) {
                throw new Exception("Invalid marks for student ID $student_id. Marks must be between 0 and {$exam_details['total_marks']}.");
            }
            
            // Check if result already exists
            $check_sql = "SELECT id FROM results WHERE exam_id = ? AND student_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "ii", $exam_id, $student_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if(mysqli_num_rows($check_result) > 0) {
                // Update existing result
                $update_sql = "UPDATE results SET obtained_marks = ?, remarks = ? WHERE exam_id = ? AND student_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "isii", $marks, $remark, $exam_id, $student_id);
                
                if(mysqli_stmt_execute($update_stmt)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } else {
                // Insert new result
                $insert_sql = "INSERT INTO results (exam_id, student_id, obtained_marks, remarks, created_at) VALUES (?, ?, ?, ?, NOW())";
                $insert_stmt = mysqli_prepare($conn, $insert_sql);
                mysqli_stmt_bind_param($insert_stmt, "iiis", $exam_id, $student_id, $marks, $remark);
                
                if(mysqli_stmt_execute($insert_stmt)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        if($error_count == 0) {
            redirectWithMessage("results.php?exam_id=$exam_id", "Results saved successfully for $success_count students.", "success");
        } else {
            redirectWithMessage("enter_results.php?exam_id=$exam_id", "Saved results for $success_count students. Failed for $error_count students.", "warning");
        }
    } catch(Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $error_message = $e->getMessage();
    }
}

// Check if results already exist for this exam
$existing_results = [];
$sql = "SELECT student_id, obtained_marks, remarks FROM results WHERE exam_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $exam_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $existing_results[$row['student_id']] = $row;
    }
}
?>

<div class="page-header">
    <h1>Enter Exam Results</h1>
</div>

<!-- Exam Details -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-info-circle"></i> Exam Details
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Exam Name:</strong> <?php echo htmlspecialchars($exam_details['name']); ?></p>
                <p><strong>Class:</strong> <?php echo htmlspecialchars($exam_details['class_name'] . ' ' . $exam_details['section']); ?></p>
                <p><strong>School:</strong> <?php echo htmlspecialchars($exam_details['school_name']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Exam Date:</strong> <?php echo formatDate($exam_details['exam_date']); ?></p>
                <p><strong>Total Marks:</strong> <?php echo $exam_details['total_marks']; ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($exam_details['description']); ?></p>
            </div>
        </div>
    </div>
</div>

<?php if(isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<!-- Results Entry Form -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-edit"></i> Enter Results
    </div>
    <div class="card-body">
        <?php if(count($students) > 0): ?>
            <form action="enter_results.php?exam_id=<?php echo $exam_id; ?>" method="post">
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Father's Name</th>
                                <th>Obtained Marks (out of <?php echo $exam_details['total_marks']; ?>)</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($students as $student): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($student['name']); ?>
                                        <input type="hidden" name="student_id[]" value="<?php echo $student['id']; ?>">
                                    </td>
                                    <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                                    <td>
                                        <input type="number" class="form-control" name="obtained_marks[]" 
                                               min="0" max="<?php echo $exam_details['total_marks']; ?>" required
                                               value="<?php echo isset($existing_results[$student['id']]) ? $existing_results[$student['id']]['obtained_marks'] : ''; ?>">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" name="remarks[]"
                                               value="<?php echo isset($existing_results[$student['id']]) ? htmlspecialchars($existing_results[$student['id']]['remarks']) : ''; ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Save Results</button>
                    <a href="results.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-info">
                No students found in this class. Please add students to the class first.
            </div>
            <a href="students.php" class="btn btn-primary">Manage Students</a>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
require_once "../includes/footer.php";
?>