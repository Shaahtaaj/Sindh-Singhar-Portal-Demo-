<?php
// Include header
require_once "../includes/header.php";

// Check if user is admin
if(!isAdmin()){
    redirectWithMessage("../login.php", "You are not authorized to access this page.", "danger");
}

// Get filter parameters
$filter_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$filter_exam = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$school_id = isset($_SESSION["school_id"]) ? $_SESSION["school_id"] : 0;

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

// Get all exams for dropdown
$exams = [];
$exam_sql = "SELECT * FROM exams";
if($filter_class > 0) {
    $exam_sql .= " WHERE class_id = $filter_class";
} else if($school_id > 0) {
    $exam_sql .= " WHERE school_id = $school_id";
}
$exam_sql .= " ORDER BY exam_date DESC";
$exam_result = mysqli_query($conn, $exam_sql);

if($exam_result) {
    while($row = mysqli_fetch_assoc($exam_result)) {
        $exams[] = $row;
    }
}

// Get results based on filter
$results = [];
if($filter_exam > 0) {
    $sql = "SELECT r.*, s.name as student_name, s.father_name, e.name as exam_name, e.total_marks as exam_total_marks
            FROM results r 
            JOIN students s ON r.student_id = s.id 
            JOIN exams e ON r.exam_id = e.id
            WHERE r.exam_id = ?
            ORDER BY s.name";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $filter_exam);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if($result) {
        while($row = mysqli_fetch_assoc($result)) {
            $results[] = $row;
        }
    }
}

// Get exam details if selected
$exam_details = null;
if($filter_exam > 0) {
    $sql = "SELECT e.*, c.name as class_name, c.section, s.name as school_name, u.name as teacher_name
            FROM exams e 
            JOIN classes c ON e.class_id = c.id 
            JOIN schools s ON e.school_id = s.id
            LEFT JOIN users u ON e.teacher_id = u.id
            WHERE e.id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $filter_exam);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if($result && mysqli_num_rows($result) > 0) {
        $exam_details = mysqli_fetch_assoc($result);
    }
}
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1>Results Management</h1>
    <div>
        <a href="create_exam.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Create New Exam
        </a>
    </div>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-filter"></i> Filter Results
    </div>
    <div class="card-body">
        <form action="results.php" method="get">
            <div class="form-row">
                <div class="form-group col-md-5">
                    <label for="class_id">Class</label>
                    <select class="form-control" id="class_id" name="class_id" onchange="this.form.submit()">
                        <option value="">All Classes</option>
                        <?php foreach($classes as $class): ?>
                            <?php if(isset($class['school_name'])): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo ($filter_class == $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['school_name'] . ' - ' . $class['name'] . ' ' . $class['section']); ?>
                                </option>
                            <?php else: ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo ($filter_class == $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['name'] . ' ' . $class['section']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-5">
                    <label for="exam_id">Exam</label>
                    <select class="form-control" id="exam_id" name="exam_id" onchange="this.form.submit()">
                        <option value="">Select Exam</option>
                        <?php foreach($exams as $exam): ?>
                            <option value="<?php echo $exam['id']; ?>" <?php echo ($filter_exam == $exam['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam['name'] . ' (' . formatDate($exam['exam_date']) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if($exam_details): ?>
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
                    <p><strong>Teacher:</strong> <?php echo htmlspecialchars($exam_details['teacher_name']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Results List -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-clipboard-list"></i> Results
        </div>
        <div class="card-body">
            <?php if(count($results) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="resultsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Father's Name</th>
                                <th>Obtained Marks</th>
                                <th>Total Marks</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                                <th>Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($results as $result): ?>
                                <?php 
                                    $percentage = ($result['obtained_marks'] / $result['exam_total_marks']) * 100;
                                    $grade = '';
                                    
                                    if($percentage >= 90) {
                                        $grade = 'A+';
                                    } elseif($percentage >= 80) {
                                        $grade = 'A';
                                    } elseif($percentage >= 70) {
                                        $grade = 'B';
                                    } elseif($percentage >= 60) {
                                        $grade = 'C';
                                    } elseif($percentage >= 50) {
                                        $grade = 'D';
                                    } else {
                                        $grade = 'F';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($result['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($result['father_name']); ?></td>
                                    <td><?php echo $result['obtained_marks']; ?></td>
                                    <td><?php echo $result['exam_total_marks']; ?></td>
                                    <td><?php echo number_format($percentage, 2); ?>%</td>
                                    <td><?php echo $grade; ?></td>
                                    <td><?php echo htmlspecialchars($result['remarks']); ?></td>
                                    <td>
                                        <a href="edit_result.php?id=<?php echo $result['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete_result.php?id=<?php echo $result['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this result?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <a href="export_results.php?exam_id=<?php echo $filter_exam; ?>" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </a>
                    <a href="export_results.php?exam_id=<?php echo $filter_exam; ?>&format=pdf" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Export to PDF
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    No results found for this exam. You can enter results below.
                </div>
                
                <a href="enter_results.php?exam_id=<?php echo $filter_exam; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Enter Results
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php elseif($filter_class > 0): ?>
    <div class="alert alert-info">
        No exams found for this class. You can create a new exam.
    </div>
    
    <a href="create_exam.php?class_id=<?php echo $filter_class; ?>" class="btn btn-primary">
        <i class="fas fa-plus"></i> Create New Exam
    </a>
<?php else: ?>
    <div class="alert alert-info">
        Please select a class and exam to view or enter results.
    </div>
<?php endif; ?>

<script>
    $(document).ready(function() {
        $('#resultsTable').DataTable();
    });
</script>

<?php
// Include footer
require_once "../includes/footer.php";
?>