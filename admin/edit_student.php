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

// Handle form submission
if(isset($_POST['update_student'])) {
    $name = sanitizeInput($_POST['name']);
    $father_name = sanitizeInput($_POST['father_name']);
    $gender = sanitizeInput($_POST['gender']);
    $dob = sanitizeInput($_POST['dob']);
    $class_id = intval($_POST['class_id']);
    $admission_date = sanitizeInput($_POST['admission_date']);
    
    // Validate input
    if(empty($name)) {
        redirectWithMessage("edit_student.php?id=$student_id", "Student name is required.", "danger");
    }
    
    // Get class details to get school_id
    $class = getClassById($class_id);
    if(!$class) {
        redirectWithMessage("edit_student.php?id=$student_id", "Selected class does not exist.", "danger");
    }
    
    $school_id = $class['school_id'];
    
    // Update student in database
    $sql = "UPDATE students SET 
            name = ?, 
            father_name = ?, 
            gender = ?, 
            dob = ?, 
            class_id = ?, 
            admission_date = ?, 
            school_id = ? 
            WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssisii", $name, $father_name, $gender, $dob, $class_id, $admission_date, $school_id, $student_id);
    
    if(mysqli_stmt_execute($stmt)) {
        redirectWithMessage("students.php", "Student updated successfully.", "success");
    } else {
        redirectWithMessage("edit_student.php?id=$student_id", "Error updating student: " . mysqli_error($conn), "danger");
    }
    
    mysqli_stmt_close($stmt);
}

// Get student details
$sql = "SELECT * FROM students WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result) == 0) {
    redirectWithMessage("students.php", "Student not found.", "danger");
}

$student = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Get all classes for dropdown
$school_id = isset($_SESSION["school_id"]) ? $_SESSION["school_id"] : 0;
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
?>

<div class="page-header">
    <h1>Edit Student</h1>
</div>

<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-user-edit"></i> Edit Student Information
    </div>
    <div class="card-body">
        <form action="edit_student.php?id=<?php echo $student_id; ?>" method="post">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="name">Student Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($student['name']); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="father_name">Father's Name</label>
                    <input type="text" class="form-control" id="father_name" name="father_name" value="<?php echo htmlspecialchars($student['father_name']); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="gender">Gender</label>
                    <select class="form-control" id="gender" name="gender">
                        <option value="">Select Gender</option>
                        <option value="male" <?php echo ($student['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo ($student['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo ($student['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label for="dob">Date of Birth</label>
                    <input type="date" class="form-control" id="dob" name="dob" value="<?php echo $student['dob']; ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="admission_date">Admission Date</label>
                    <input type="date" class="form-control" id="admission_date" name="admission_date" value="<?php echo $student['admission_date']; ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="class_id">Class</label>
                <select class="form-control" id="class_id" name="class_id" required>
                    <option value="">Select Class</option>
                    <?php foreach($classes as $class): ?>
                        <?php if(isset($class['school_name'])): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo ($student['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['school_name'] . ' - ' . $class['name'] . ' ' . $class['section']); ?>
                            </option>
                        <?php else: ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo ($student['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['name'] . ' ' . $class['section']); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" name="update_student" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Student
                </button>
                <a href="students.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Students
                </a>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
require_once "../includes/footer.php";
?>