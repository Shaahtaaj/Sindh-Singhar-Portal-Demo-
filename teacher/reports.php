<?php
require_once "../includes/header.php";

if (!isTeacher()) {
    redirectWithMessage("../login.php", "You are not authorized to access the teacher area.", "danger");
}

$teacherId = $_SESSION["user_id"];
$classes = getTeacherClasses($teacherId);
$classIds = array_map("intval", array_column($classes, "id"));
$classesById = [];
foreach ($classes as $class) {
    $classesById[(int)$class['id']] = $class;
}

$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
if ($selectedClassId > 0 && !in_array($selectedClassId, $classIds, true)) {
    $selectedClassId = 0;
}

// Handle report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    $reportType = sanitizeInput($_POST['type'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $reportDate = $_POST['date'] ?? date('Y-m-d');

    if ($classId === 0 || !in_array($classId, $classIds, true)) {
        redirectWithMessage("reports.php", "Invalid class selection.", "danger");
    }

    if (empty($reportType) || empty($content)) {
        redirectWithMessage("reports.php?class_id={$classId}", "Please select a report type and provide content.", "warning");
    }

    $dateObj = DateTime::createFromFormat('Y-m-d', $reportDate);
    if (!$dateObj) {
        redirectWithMessage("reports.php?class_id={$classId}", "Invalid date format.", "danger");
    }
    $reportDate = $dateObj->format('Y-m-d');

    $stmt = mysqli_prepare($conn, "INSERT INTO reports (teacher_id, class_id, type, content, file_path, date, created_at) VALUES (?, ?, ?, ?, NULL, ?, NOW())");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iisss", $teacherId, $classId, $reportType, $content, $reportDate);
        if (mysqli_stmt_execute($stmt)) {
            redirectWithMessage("reports.php?class_id={$classId}", "Report submitted successfully. PDF generation available in future release.");
        }
        mysqli_stmt_close($stmt);
    }

    redirectWithMessage("reports.php", "Failed to submit report. Please try again.", "danger");
}

// Load recent reports for teacher
$reports = [];
$sqlReports = "SELECT r.*, c.name AS class_name, c.section FROM reports r JOIN classes c ON r.class_id = c.id WHERE r.teacher_id = $teacherId ORDER BY r.date DESC, r.created_at DESC";
$resultReports = mysqli_query($conn, $sqlReports);
if ($resultReports) {
    while ($row = mysqli_fetch_assoc($resultReports)) {
        $reports[] = $row;
    }
}
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1>Class Reports</h1>
    <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-file-alt"></i> Submit New Report
    </div>
    <div class="card-body">
        <form method="post" action="reports.php">
            <input type="hidden" name="submit_report" value="1">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="class_id">Class *</label>
                    <select class="form-control" id="class_id" name="class_id" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo (int)$class['id']; ?>" <?php echo $selectedClassId === (int)$class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['name'] . ' ' . ($class['section'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label for="type">Report Type *</label>
                    <select class="form-control" id="type" name="type" required>
                        <option value="">Select Type</option>
                        <option value="weekly">Weekly Report</option>
                        <option value="monthly">Monthly Report</option>
                        <option value="progress">Progress Report</option>
                        <option value="incident">Incident Report</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label for="date">Report Date *</label>
                    <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="content">Report Details *</label>
                <textarea class="form-control" id="content" name="content" rows="5" placeholder="Enter detailed report..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Report</button>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-history"></i> Recent Reports
    </div>
    <div class="card-body">
        <?php if (!empty($reports)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Class</th>
                            <th>Type</th>
                            <th>Summary</th>
                            <th>PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo formatDate($report['date']); ?></td>
                                <td><?php echo htmlspecialchars(($report['class_name'] ?? '') . ' ' . ($report['section'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($report['type'])); ?></td>
                                <td><?php echo htmlspecialchars(mb_strimwidth($report['content'], 0, 80, '...')); ?></td>
                                <td>
                                    <?php if (!empty($report['file_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($report['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Pending</span>
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

<?php
require_once "../includes/footer.php";
?>
