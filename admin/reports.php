<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../includes/functions.php";

if (!isLoggedIn()) {
    redirectWithMessage("../login.php", "Please log in to continue.", "danger");
}

if (!isAdmin()) {
    redirectWithMessage("../login.php", "You are not authorized to access this page.", "danger");
}

require_once "../includes/header.php";

$sessionSchoolId = isset($_SESSION['school_id']) ? (int)$_SESSION['school_id'] : 0;

// Fetch schools list for filtering
$schools = [];
$schoolSql = "SELECT id, name FROM schools ORDER BY name";
$schoolResult = mysqli_query($conn, $schoolSql);
if ($schoolResult) {
    while ($row = mysqli_fetch_assoc($schoolResult)) {
        $schools[] = $row;
    }
}

// Determine selected filter values
$selectedSchoolId = $sessionSchoolId > 0 ? $sessionSchoolId : (isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0);
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedTeacherId = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;

// Fetch classes based on filter
$classes = [];
$classSql = "SELECT c.id, c.name, c.section, c.school_id FROM classes c";
$classWhere = [];
if ($sessionSchoolId > 0) {
    $classWhere[] = "c.school_id = $sessionSchoolId";
} elseif ($selectedSchoolId > 0) {
    $classWhere[] = "c.school_id = $selectedSchoolId";
}
if (!empty($classWhere)) {
    $classSql .= " WHERE " . implode(' AND ', $classWhere);
}
$classSql .= " ORDER BY c.name, c.section";
$classResult = mysqli_query($conn, $classSql);
if ($classResult) {
    while ($row = mysqli_fetch_assoc($classResult)) {
        $classes[] = $row;
    }
}

// Fetch teachers based on filter
$teachers = [];
$teacherSql = "SELECT id, name, school_id FROM users WHERE role = 'teacher'";
$teacherWhere = [];
if ($sessionSchoolId > 0) {
    $teacherWhere[] = "school_id = $sessionSchoolId";
} elseif ($selectedSchoolId > 0) {
    $teacherWhere[] = "school_id = $selectedSchoolId";
}
if (!empty($teacherWhere)) {
    $teacherSql .= " AND " . implode(' AND ', $teacherWhere);
}
$teacherSql .= " ORDER BY name";
$teacherResult = mysqli_query($conn, $teacherSql);
if ($teacherResult) {
    while ($row = mysqli_fetch_assoc($teacherResult)) {
        $teachers[] = $row;
    }
}

// Fetch reports
$reports = [];
$reportSql = "SELECT r.*, u.name AS teacher_name, c.name AS class_name, c.section, s.name AS school_name
              FROM reports r
              JOIN users u ON r.teacher_id = u.id
              JOIN classes c ON r.class_id = c.id
              JOIN schools s ON c.school_id = s.id
              WHERE 1 = 1";
if ($sessionSchoolId > 0) {
    $reportSql .= " AND c.school_id = $sessionSchoolId";
} elseif ($selectedSchoolId > 0) {
    $reportSql .= " AND c.school_id = $selectedSchoolId";
}
if ($selectedClassId > 0) {
    $reportSql .= " AND c.id = $selectedClassId";
}
if ($selectedTeacherId > 0) {
    $reportSql .= " AND r.teacher_id = $selectedTeacherId";
}
$reportSql .= " ORDER BY r.date DESC, r.created_at DESC";
$reportResult = mysqli_query($conn, $reportSql);
if ($reportResult) {
    while ($row = mysqli_fetch_assoc($reportResult)) {
        $reports[] = $row;
    }
}
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1>Teacher Reports</h1>
    <div>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="fas fa-filter"></i> Filter Reports</div>
    <div class="card-body">
        <form class="form-row" method="get" action="reports.php">
            <?php if ($sessionSchoolId === 0) { ?>
                <div class="form-group col-md-4">
                    <label for="school_id">School</label>
                    <select class="form-control" id="school_id" name="school_id" onchange="this.form.submit()">
                        <option value="">All Schools</option>
                        <?php foreach ($schools as $school) { ?>
                            <option value="<?php echo (int)$school['id']; ?>" <?php echo ($selectedSchoolId === (int)$school['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($school['name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            <?php } else { ?>
                <input type="hidden" name="school_id" value="<?php echo $sessionSchoolId; ?>">
            <?php } ?>
            <div class="form-group col-md-4">
                <label for="class_id">Class</label>
                <select class="form-control" id="class_id" name="class_id" onchange="this.form.submit()">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $class) { ?>
                        <option value="<?php echo (int)$class['id']; ?>" <?php echo ($selectedClassId === (int)$class['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['name'] . ' ' . ($class['section'] ?? '')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-group col-md-4">
                <label for="teacher_id">Teacher</label>
                <select class="form-control" id="teacher_id" name="teacher_id" onchange="this.form.submit()">
                    <option value="">All Teachers</option>
                    <?php foreach ($teachers as $teacher) { ?>
                        <option value="<?php echo (int)$teacher['id']; ?>" <?php echo ($selectedTeacherId === (int)$teacher['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($teacher['name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="fas fa-file-alt"></i> Report Submissions</div>
    <div class="card-body">
        <?php if (!empty($reports)) { ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Teacher</th>
                            <th>Class</th>
                            <th>Type</th>
                            <th>School</th>
                            <th>Content</th>
                            <th>PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report) { ?>
                            <tr>
                                <td><?php echo formatDate($report['date']); ?></td>
                                <td><?php echo htmlspecialchars($report['teacher_name']); ?></td>
                                <td><?php echo htmlspecialchars(($report['class_name'] ?? '') . ' ' . ($report['section'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($report['type'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($report['school_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(mb_strimwidth($report['content'], 0, 100, '...')); ?></td>
                                <td>
                                    <?php if (!empty($report['file_path'])) { ?>
                                        <a href="<?php echo htmlspecialchars($report['file_path']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-download"></i> View
                                        </a>
                                    <?php } else { ?>
                                        <span class="badge badge-secondary">Pending</span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <p class="text-muted mb-0">No reports submitted yet for the selected filters.</p>
        <?php } ?>
    </div>
</div>

<?php
require_once "../includes/footer.php";
?>
