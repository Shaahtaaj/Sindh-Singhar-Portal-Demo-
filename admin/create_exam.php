<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../includes/functions.php";

if (!isLoggedIn()) {
    redirectWithMessage("../login.php", "Please log in to continue.", "danger");
}

if (!isAdmin()) {
    redirectWithMessage("../login.php", "You are not authorized to access this page.", "danger");
}

// Ensure exams table has expected columns
$expectedExamColumns = [
    'name' => "ALTER TABLE exams ADD COLUMN name VARCHAR(100) NOT NULL AFTER id",
    'school_id' => "ALTER TABLE exams ADD COLUMN school_id INT NULL AFTER class_id",
    'teacher_id' => "ALTER TABLE exams ADD COLUMN teacher_id INT NULL AFTER school_id",
    'total_marks' => "ALTER TABLE exams ADD COLUMN total_marks INT NOT NULL DEFAULT 100 AFTER exam_date",
    'description' => "ALTER TABLE exams ADD COLUMN description TEXT NULL AFTER total_marks"
];

$columnCheckSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'exams'";
$columnStmt = mysqli_prepare($conn, $columnCheckSql);
$schemaName = DB_NAME;
if ($columnStmt) {
    mysqli_stmt_bind_param($columnStmt, "s", $schemaName);
    mysqli_stmt_execute($columnStmt);
    $result = mysqli_stmt_get_result($columnStmt);
    if ($result) {
        $existingColumns = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $existingColumns[] = $row['COLUMN_NAME'];
        }
        foreach ($expectedExamColumns as $column => $alterSql) {
            if (!in_array($column, $existingColumns, true)) {
                mysqli_query($conn, $alterSql);
            }
        }
    }
}

// Get school ID from session
$school_id = isset($_SESSION["school_id"]) ? $_SESSION["school_id"] : 0;
    
    
    // Get teacher ID for the class
    $teacher_id = 0;
    $stmt = mysqli_prepare($conn, $teacher_query);
    mysqli_stmt_bind_param($stmt, "i", $class_id);
    mysqli_stmt_execute($stmt);
    $teacher_result = mysqli_stmt_get_result($stmt);
    
    if($row = mysqli_fetch_assoc($teacher_result)) {
        $teacher_id = $row['teacher_id'];
    }
    
    // Validation
    $errors = [];
    
    if(empty($name)) {
        $errors[] = "Exam name is required";
    }
    
    if($class_id <= 0) {
        $errors[] = "Please select a valid class";
    }
    
    if(empty($exam_date)) {
        $errors[] = "Exam date is required";
    }
    
    if($total_marks <= 0) {
        $errors[] = "Total marks must be greater than zero";
    }
    
    // If no errors, insert the exam
    if(empty($errors)) {
        $sql = "INSERT INTO exams (name, class_id, school_id, teacher_id, exam_date, total_marks, description, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "siiisis", $name, $class_id, $school_id, $teacher_id, $exam_date, $total_marks, $description);
        
        if(mysqli_stmt_execute($stmt)) {
            $exam_id = mysqli_insert_id($conn);
            redirectWithMessage("enter_results.php?exam_id=$exam_id", "Exam created successfully. Now you can enter results.", "success");
        } else {
            $errors[] = "Error creating exam: " . mysqli_error($conn);
        }
    }


// Get all classes for dropdown
$classes = [];
if($school_id > 0) {
    $classes = getClassesBySchool($school_id);
} else {
    $sql = "SELECT c.*, s.name as school_name FROM classes c JOIN schools s ON c.school_id = s.id ORDER BY s.name, c.name, c.section";
    $result = mysqli_query($conn, $sql);
    
    if($result) {
        while($row = mysqli_fetch_assoc($result)) {
            $classes[] = $row;
        }
    }
}

require_once "../includes/header.php";
?>

<div class="page-header">
    <h1>Create New Exam</h1>
</div>

<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-edit"></i> Exam Details
    </div>
    <div class="card-body">
        <?php if(isset($errors) && !empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form action="create_exam.php" method="post">
            <div class="form-group">
                <label for="name">Exam Name *</label>
                <input type="text" class="form-control" id="name" name="name" required 
                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="class_id">Class *</label>
                <select class="form-control" id="class_id" name="class_id" required>
                    <option value="">Select Class</option>
                    <?php foreach($classes as $class): ?>
                        <?php if(isset($class['school_name'])): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo ($class_id == $class['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['school_name'] . ' - ' . $class['name'] . ' ' . $class['section']); ?>
                            </option>
                        <?php else: ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo ($class_id == $class['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['name'] . ' ' . $class['section']); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="exam_date">Exam Date *</label>
                <input type="date" class="form-control" id="exam_date" name="exam_date" required
                       value="<?php echo isset($_POST['exam_date']) ? htmlspecialchars($_POST['exam_date']) : date('Y-m-d'); ?>">
            </div>
            
            <div class="form-group">
                <label for="total_marks">Total Marks *</label>
                <input type="number" class="form-control" id="total_marks" name="total_marks" required min="1"
                       value="<?php echo isset($_POST['total_marks']) ? intval($_POST['total_marks']) : 100; ?>">
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Create Exam</button>
            <a href="results.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php
// Include footer
require_once "../includes/footer.php";
?>