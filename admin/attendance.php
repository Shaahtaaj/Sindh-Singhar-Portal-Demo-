<?php
// Include header
require_once "../includes/header.php";

// Check if user is admin
if(!isAdmin()){
    redirectWithMessage("../login.php", "You are not authorized to access this page.", "danger");
}

// Get filter parameters
$filter_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
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

// Get attendance records based on filter
$attendance_records = [];
$students = [];

if($filter_class > 0) {
    // Check if attendance exists for this class and date
    $sql = "SELECT a.*, u.name as teacher_name 
            FROM attendance a 
            JOIN users u ON a.teacher_id = u.id 
            WHERE a.class_id = ? AND a.date = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $filter_class, $filter_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if($result && mysqli_num_rows($result) > 0) {
        $attendance = mysqli_fetch_assoc($result);
        
        // Get attendance details
        $sql = "SELECT ad.*, s.name as student_name, s.father_name 
                FROM attendance_details ad 
                JOIN students s ON ad.student_id = s.id 
                WHERE ad.attendance_id = ? 
                ORDER BY s.name";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $attendance['id']);
        mysqli_stmt_execute($stmt);
        $details_result = mysqli_stmt_get_result($stmt);
        
        if($details_result) {
            while($row = mysqli_fetch_assoc($details_result)) {
                $attendance_records[] = $row;
            }
        }
    } else {
        // No attendance record found, get students for this class
        $sql = "SELECT s.id, s.name, s.father_name 
                FROM students s 
                WHERE s.class_id = ? 
                ORDER BY s.name";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $filter_class);
        mysqli_stmt_execute($stmt);
        $students_result = mysqli_stmt_get_result($stmt);
        
        if($students_result) {
            while($row = mysqli_fetch_assoc($students_result)) {
                $students[] = $row;
            }
        }
    }
}

// Get class name for display
$class_name = "";
if($filter_class > 0) {
    foreach($classes as $class) {
        if($class['id'] == $filter_class) {
            $class_name = isset($class['school_name']) 
                ? $class['school_name'] . ' - ' . $class['name'] . ' ' . $class['section']
                : $class['name'] . ' ' . $class['section'];
            break;
        }
    }
}
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1>Attendance Management</h1>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-filter"></i> Filter Attendance
    </div>
    <div class="card-body">
        <form action="attendance.php" method="get">
            <div class="form-row">
                <div class="form-group col-md-5">
                    <label for="class_id">Class</label>
                    <select class="form-control" id="class_id" name="class_id" required>
                        <option value="">Select Class</option>
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
                    <label for="date">Date</label>
                    <input type="date" class="form-control" id="date" name="date" value="<?php echo $filter_date; ?>" required>
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

<?php if($filter_class > 0): ?>
    <!-- Attendance Records -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-clipboard-list"></i> 
            Attendance for <?php echo htmlspecialchars($class_name); ?> on <?php echo formatDate($filter_date); ?>
        </div>
        <div class="card-body">
            <?php if(count($attendance_records) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="attendanceTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Father's Name</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($attendance_records as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['father_name']); ?></td>
                                    <td>
                                        <?php if($record['status'] == 'present'): ?>
                                            <span class="badge badge-success">Present</span>
                                        <?php elseif($record['status'] == 'absent'): ?>
                                            <span class="badge badge-danger">Absent</span>
                                        <?php elseif($record['status'] == 'leave'): ?>
                                            <span class="badge badge-warning">Leave</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Unknown</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['remarks']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <a href="export_attendance.php?class_id=<?php echo $filter_class; ?>&date=<?php echo $filter_date; ?>" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </a>
                    <a href="export_attendance.php?class_id=<?php echo $filter_class; ?>&date=<?php echo $filter_date; ?>&format=pdf" class="btn btn-danger">
                        <i class="fas fa-file-pdf"></i> Export to PDF
                    </a>
                </div>
            <?php elseif(count($students) > 0): ?>
                <div class="alert alert-info">
                    No attendance record found for this class on <?php echo formatDate($filter_date); ?>. 
                    You can mark attendance below.
                </div>
                
                <form action="mark_attendance.php" method="post">
                    <input type="hidden" name="class_id" value="<?php echo $filter_class; ?>">
                    <input type="hidden" name="date" value="<?php echo $filter_date; ?>">
                    
                    <div class="table-responsive">
                        <table class="table table-bordered" id="markAttendanceTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Father's Name</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                                        <td>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="status[<?php echo $student['id']; ?>]" id="present_<?php echo $student['id']; ?>" value="present" checked>
                                                <label class="form-check-label" for="present_<?php echo $student['id']; ?>">Present</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="status[<?php echo $student['id']; ?>]" id="absent_<?php echo $student['id']; ?>" value="absent">
                                                <label class="form-check-label" for="absent_<?php echo $student['id']; ?>">Absent</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="status[<?php echo $student['id']; ?>]" id="leave_<?php echo $student['id']; ?>" value="leave">
                                                <label class="form-check-label" for="leave_<?php echo $student['id']; ?>">Leave</label>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control" name="remarks[<?php echo $student['id']; ?>]" placeholder="Optional remarks">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="form-group mt-3">
                        <button type="submit" name="mark_attendance" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Attendance
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">
                    No students found in this class. Please add students first.
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        Please select a class and date to view or mark attendance.
    </div>
<?php endif; ?>

<script>
    $(document).ready(function() {
        $('#attendanceTable').DataTable();
    });
</script>

<?php
// Include footer
require_once "../includes/footer.php";
?>