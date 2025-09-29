<?php
require_once "../includes/header.php";

// Restrict access to teachers only
if (!isTeacher()) {
    redirectWithMessage("../login.php", "You are not authorized to access the teacher area.", "danger");
}

$teacherId = $_SESSION["user_id"];
$teacherName = $_SESSION["name"] ?? "Teacher";

// Fetch classes assigned to the teacher
$classes = getTeacherClasses($teacherId);
$totalClasses = count($classes);

// Helper to build IN clause safely for numeric IDs
$classIds = array_map("intval", array_column($classes, "id"));
$classIdsList = !empty($classIds) ? implode(",", $classIds) : "";

// Total students across assigned classes
$totalStudents = 0;
if ($classIdsList !== "") {
    $sqlStudents = "SELECT COUNT(*) AS total FROM students WHERE class_id IN ($classIdsList)";
    $resultStudents = mysqli_query($conn, $sqlStudents);
    if ($resultStudents) {
        $rowStudents = mysqli_fetch_assoc($resultStudents);
        $totalStudents = (int)($rowStudents["total"] ?? 0);
    }
}

// Attendance submissions by this teacher
$sqlAttendanceCount = "SELECT COUNT(*) AS total FROM attendance WHERE teacher_id = $teacherId";
$resultAttendanceCount = mysqli_query($conn, $sqlAttendanceCount);
$totalAttendanceSubmissions = ($resultAttendanceCount)
    ? (int)(mysqli_fetch_assoc($resultAttendanceCount)["total"] ?? 0)
    : 0;

// Attendance submitted today
$today = date("Y-m-d");
$sqlTodayAttendance = "SELECT COUNT(*) AS total FROM attendance WHERE teacher_id = $teacherId AND date = '$today'";
$resultTodayAttendance = mysqli_query($conn, $sqlTodayAttendance);
$todayAttendance = ($resultTodayAttendance)
    ? (int)(mysqli_fetch_assoc($resultTodayAttendance)["total"] ?? 0)
    : 0;

// Reports created by this teacher
$sqlReportsCount = "SELECT COUNT(*) AS total FROM reports WHERE teacher_id = $teacherId";
$resultReportsCount = mysqli_query($conn, $sqlReportsCount);
$totalReports = ($resultReportsCount)
    ? (int)(mysqli_fetch_assoc($resultReportsCount)["total"] ?? 0)
    : 0;

// Fetch recent reports
$recentReports = [];
$sqlRecentReports = "SELECT r.*, c.name AS class_name, c.section
                     FROM reports r
                     JOIN classes c ON r.class_id = c.id
                     WHERE r.teacher_id = $teacherId
                     ORDER BY r.date DESC, r.created_at DESC
                     LIMIT 5";
$resultRecentReports = mysqli_query($conn, $sqlRecentReports);
if ($resultRecentReports) {
    while ($report = mysqli_fetch_assoc($resultRecentReports)) {
        $recentReports[] = $report;
    }
}

// Fetch upcoming / recent exams for the teacher's classes
$upcomingExams = [];
if ($classIdsList !== "") {
    $sqlUpcomingExams = "SELECT e.*, c.name AS class_name, c.section
                         FROM exams e
                         JOIN classes c ON e.class_id = c.id
                         WHERE e.class_id IN ($classIdsList)
                         ORDER BY e.exam_date DESC
                         LIMIT 5";
    $resultUpcomingExams = mysqli_query($conn, $sqlUpcomingExams);
    if ($resultUpcomingExams) {
        while ($exam = mysqli_fetch_assoc($resultUpcomingExams)) {
            $upcomingExams[] = $exam;
        }
    }
}
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>Teacher Dashboard</h1>
        <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($teacherName); ?>.</p>
    </div>
    <div>
        <a href="../logout.php" class="btn btn-outline-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-chalkboard"></i> Assigned Classes</h5>
                <h2 class="display-4 mb-0"><?php echo $totalClasses; ?></h2>
            </div>
            <div class="card-footer">
                Manage your sections efficiently.
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-user-graduate"></i> Students</h5>
                <h2 class="display-4 mb-0"><?php echo $totalStudents; ?></h2>
            </div>
            <div class="card-footer">
                Total learners across your classes.
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card bg-warning text-white h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-calendar-check"></i> Attendance Logged</h5>
                <h2 class="display-4 mb-0"><?php echo $totalAttendanceSubmissions; ?></h2>
            </div>
            <div class="card-footer">
                <?php if ($todayAttendance > 0): ?>
                    Marked for today
                <?php else: ?>
                    Pending for today
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card bg-danger text-white h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-file-alt"></i> Reports Submitted</h5>
                <h2 class="display-4 mb-0"><?php echo $totalReports; ?></h2>
            </div>
            <div class="card-footer">
                Keep sharing progress updates.
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-bolt"></i> Quick Actions</div>
            <div class="card-body">
                <div class="list-group">
                    <a href="attendance.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-plus"></i> Mark Attendance
                    </a>
                    <a href="results.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-bar"></i> Enter Exam Results
                    </a>
                    <a href="reports.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-upload"></i> Submit Progress Report
                    </a>
                    <a href="../admin/export_students.php?teacher_id=<?php echo $teacherId; ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-excel"></i> Download Student List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-chalkboard-teacher"></i> Assigned Classes</div>
            <div class="card-body">
                <?php if ($totalClasses > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>School</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($class["name"] ?? ""); ?></td>
                                        <td><?php echo htmlspecialchars($class["section"] ?? "-"); ?></td>
                                        <td><?php echo htmlspecialchars($class["school_name"] ?? ""); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No classes assigned yet. Please contact the administrator.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-clipboard-list"></i> Recent Reports</div>
            <div class="card-body">
                <?php if (!empty($recentReports)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Class</th>
                                    <th>Type</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentReports as $report): ?>
                                    <tr>
                                        <td><?php echo formatDate($report["date"]); ?></td>
                                        <td><?php echo htmlspecialchars(($report["class_name"] ?? "") . " " . ($report["section"] ?? "")); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($report["type"] ?? "")); ?></td>
                                        <td>
                                            <?php if (!empty($report["file_path"])): ?>
                                                <a href="<?php echo htmlspecialchars($report["file_path"]); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                    <i class="fas fa-download"></i> View
                                                </a>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Pending PDF</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No reports submitted yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-hourglass-half"></i> Recent Exams</div>
            <div class="card-body">
                <?php if (!empty($upcomingExams)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Exam Date</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingExams as $exam): ?>
                                    <tr>
                                        <td><?php echo formatDate($exam["exam_date"]); ?></td>
                                        <td><?php echo htmlspecialchars(($exam["class_name"] ?? "") . " " . ($exam["section"] ?? "")); ?></td>
                                        <td><?php echo htmlspecialchars($exam["subject"] ?? ""); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($exam["type"] ?? "")); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No exams recorded yet. Add an exam when entering results.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once "../includes/footer.php";
?>
