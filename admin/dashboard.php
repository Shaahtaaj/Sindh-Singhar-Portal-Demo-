<?php
// Include header
require_once "../includes/header.php";

// Check if user is admin
if(!isAdmin()){
    redirectWithMessage("../login.php", "You are not authorized to access this page.", "danger");
}

// Get statistics for dashboard
$school_id = isset($_SESSION["school_id"]) ? $_SESSION["school_id"] : 0;

// Count schools
$sql_schools = "SELECT COUNT(*) as total FROM schools";
$result_schools = mysqli_query($conn, $sql_schools);
$total_schools = ($result_schools) ? mysqli_fetch_assoc($result_schools)['total'] : 0;

// Count classes
$sql_classes = "SELECT COUNT(*) as total FROM classes";
if($school_id > 0) {
    $sql_classes .= " WHERE school_id = $school_id";
}
$result_classes = mysqli_query($conn, $sql_classes);
$total_classes = ($result_classes) ? mysqli_fetch_assoc($result_classes)['total'] : 0;

// Count teachers
$sql_teachers = "SELECT COUNT(*) as total FROM users WHERE role = 'teacher'";
if($school_id > 0) {
    $sql_teachers .= " AND school_id = $school_id";
}
$result_teachers = mysqli_query($conn, $sql_teachers);
$total_teachers = ($result_teachers) ? mysqli_fetch_assoc($result_teachers)['total'] : 0;

// Count students
$sql_students = "SELECT COUNT(*) as total FROM students";
if($school_id > 0) {
    $sql_students .= " WHERE school_id = $school_id";
}
$result_students = mysqli_query($conn, $sql_students);
$total_students = ($result_students) ? mysqli_fetch_assoc($result_students)['total'] : 0;

// Get recent reports
$sql_reports = "SELECT r.*, u.name as teacher_name, c.name as class_name, c.section 
                FROM reports r 
                JOIN users u ON r.teacher_id = u.id 
                JOIN classes c ON r.class_id = c.id 
                ORDER BY r.created_at DESC LIMIT 5";
$result_reports = mysqli_query($conn, $sql_reports);
?>

<div class="page-header">
    <h1>Admin Dashboard</h1>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="card bg-primary text-white mb-4">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-school"></i> Schools</h5>
                <h2 class="display-4"><?php echo $total_schools; ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="schools.php">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white mb-4">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-chalkboard"></i> Classes</h5>
                <h2 class="display-4"><?php echo $total_classes; ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="classes.php">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white mb-4">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-chalkboard-teacher"></i> Teachers</h5>
                <h2 class="display-4"><?php echo $total_teachers; ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="teachers.php">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white mb-4">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-user-graduate"></i> Students</h5>
                <h2 class="display-4"><?php echo $total_students; ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="students.php">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-file-alt"></i> Recent Reports
            </div>
            <div class="card-body">
                <?php if(mysqli_num_rows($result_reports) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Teacher</th>
                                    <th>Class</th>
                                    <th>Type</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($report = mysqli_fetch_assoc($result_reports)): ?>
                                <tr>
                                    <td><?php echo formatDate($report['date']); ?></td>
                                    <td><?php echo htmlspecialchars($report['teacher_name']); ?></td>
                                    <td><?php echo htmlspecialchars($report['class_name'] . ' ' . $report['section']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($report['type'])); ?></td>
                                    <td>
                                        <a href="view_report.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center">No reports submitted yet.</p>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="reports.php" class="btn btn-primary">View All Reports</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-download"></i> Quick Downloads
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="download.php?type=students" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-excel"></i> Export All Students
                    </a>
                    <a href="download.php?type=attendance" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-excel"></i> Export Attendance Summary
                    </a>
                    <a href="download.php?type=results" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-excel"></i> Export Exam Results
                    </a>
                    <a href="templates/student_admission_template.xlsx" class="list-group-item list-group-item-action">
                        <i class="fas fa-download"></i> Download Student Admission Template
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-tasks"></i> Quick Actions
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <a href="students.php?action=import" class="btn btn-success btn-block">
                            <i class="fas fa-file-import"></i> Import Students
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="teachers.php?action=add" class="btn btn-info btn-block">
                            <i class="fas fa-user-plus"></i> Add Teacher
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="classes.php?action=add" class="btn btn-warning btn-block">
                            <i class="fas fa-plus-circle"></i> Add Class
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="schools.php?action=add" class="btn btn-danger btn-block">
                            <i class="fas fa-school"></i> Add School
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once "../includes/footer.php";
?>