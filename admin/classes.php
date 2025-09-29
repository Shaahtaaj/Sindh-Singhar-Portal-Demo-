<?php
require_once "../includes/header.php";

if (!isAdmin()) {
    redirectWithMessage("../login.php", "You are not authorized to access this page.", "danger");
}

$sessionSchoolId = isset($_SESSION['school_id']) ? (int)$_SESSION['school_id'] : 0;
$selectedSchoolId = $sessionSchoolId;

// Handle class creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_class'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $section = sanitizeInput($_POST['section'] ?? '');
    $schoolId = isset($_POST['school_id']) ? (int)$_POST['school_id'] : 0;
    $teacherId = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;

    if ($sessionSchoolId > 0) {
        $schoolId = $sessionSchoolId;
    }

    if (empty($name) || empty($section)) {
        redirectWithMessage('classes.php?action=add', 'Class name and section are required.', 'warning');
    }

    if ($schoolId <= 0) {
        redirectWithMessage('classes.php?action=add', 'Please select a school for the class.', 'warning');
    }

    // Validate teacher belongs to same school (if provided)
    if ($teacherId > 0) {
        $stmtCheckTeacher = mysqli_prepare($conn, "SELECT school_id FROM users WHERE id = ? AND role = 'teacher' LIMIT 1");
        if ($stmtCheckTeacher) {
            mysqli_stmt_bind_param($stmtCheckTeacher, 'i', $teacherId);
            mysqli_stmt_execute($stmtCheckTeacher);
            mysqli_stmt_bind_result($stmtCheckTeacher, $teacherSchoolId);
            if (!mysqli_stmt_fetch($stmtCheckTeacher) || (int)$teacherSchoolId !== $schoolId) {
                mysqli_stmt_close($stmtCheckTeacher);
                redirectWithMessage('classes.php?action=add', 'Selected teacher is not assigned to the chosen school.', 'warning');
            }
            mysqli_stmt_close($stmtCheckTeacher);
        }
    }

    $stmtInsert = mysqli_prepare($conn, "INSERT INTO classes (name, section, school_id, teacher_id, created_at) VALUES (?, ?, ?, ?, NOW())");
    if ($stmtInsert) {
        mysqli_stmt_bind_param($stmtInsert, 'ssii', $name, $section, $schoolId, $teacherId);
        if (mysqli_stmt_execute($stmtInsert)) {
            mysqli_stmt_close($stmtInsert);
            redirectWithMessage('classes.php', 'Class created successfully.', 'success');
        }
        mysqli_stmt_close($stmtInsert);
    }

    redirectWithMessage('classes.php?action=add', 'Failed to create class. Please try again.', 'danger');
}

// Fetch schools
$schools = [];
$schoolSql = "SELECT id, name FROM schools ORDER BY name";
$schoolResult = mysqli_query($conn, $schoolSql);
if ($schoolResult) {
    while ($row = mysqli_fetch_assoc($schoolResult)) {
        $schools[] = $row;
    }
}

// Determine school filter
if ($sessionSchoolId === 0) {
    $selectedSchoolId = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
}

// Fetch teachers for dropdown
$teachers = [];
$teacherSql = "SELECT id, name, school_id FROM users WHERE role = 'teacher'";
if ($sessionSchoolId > 0) {
    $teacherSql .= " AND school_id = $sessionSchoolId";
} elseif ($selectedSchoolId > 0) {
    $teacherSql .= " AND school_id = $selectedSchoolId";
}
$teacherSql .= " ORDER BY name";
$teacherResult = mysqli_query($conn, $teacherSql);
if ($teacherResult) {
    while ($row = mysqli_fetch_assoc($teacherResult)) {
        $teachers[] = $row;
    }
}

// Fetch classes
$classes = [];
$classSql = "SELECT c.*, s.name AS school_name, u.name AS teacher_name
             FROM classes c
             LEFT JOIN schools s ON c.school_id = s.id
             LEFT JOIN users u ON c.teacher_id = u.id
             WHERE 1 = 1";
if ($sessionSchoolId > 0) {
    $classSql .= " AND c.school_id = $sessionSchoolId";
} elseif ($selectedSchoolId > 0) {
    $classSql .= " AND c.school_id = $selectedSchoolId";
}
$classSql .= " ORDER BY s.name, c.name, c.section";
$classResult = mysqli_query($conn, $classSql);
if ($classResult) {
    while ($row = mysqli_fetch_assoc($classResult)) {
        $classes[] = $row;
    }
}

$action = $_GET['action'] ?? '';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1>Classes</h1>
    <div>
        <a href="classes.php?action=add" class="btn btn-success"><i class="fas fa-plus-circle"></i> Add Class</a>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="fas fa-filter"></i> Filter</div>
    <div class="card-body">
        <form class="form-inline" method="get" action="classes.php">
            <?php if ($sessionSchoolId === 0) { ?>
                <div class="form-group mr-3">
                    <label class="mr-2" for="school_id">School</label>
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
                <span>School: <strong><?php echo htmlspecialchars($_SESSION['school_name'] ?? 'Assigned School'); ?></strong></span>
            <?php } ?>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
        </form>
    </div>
</div>

<?php if ($action === 'add') { ?>
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-plus"></i> Create Class</div>
        <div class="card-body">
            <form method="post" action="classes.php">
                <input type="hidden" name="create_class" value="1">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="name">Class Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="section">Section *</label>
                        <input type="text" class="form-control" id="section" name="section" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="teacher_id">Class Teacher</label>
                        <select class="form-control" id="teacher_id" name="teacher_id">
                            <option value="">Select teacher</option>
                            <?php foreach ($teachers as $teacher) { ?>
                                <option value="<?php echo (int)$teacher['id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <?php if ($sessionSchoolId === 0) { ?>
                    <div class="form-group">
                        <label for="school_id_select">School *</label>
                        <select class="form-control" id="school_id_select" name="school_id" required>
                            <option value="">Select school</option>
                            <?php foreach ($schools as $school) { ?>
                                <option value="<?php echo (int)$school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } else { ?>
                    <input type="hidden" name="school_id" value="<?php echo $sessionSchoolId; ?>">
                <?php } ?>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Class</button>
                <a href="classes.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
<?php } ?>

<div class="card">
    <div class="card-header"><i class="fas fa-chalkboard"></i> Class Directory</div>
    <div class="card-body">
        <?php if (!empty($classes)) { ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Section</th>
                            <th>School</th>
                            <th>Teacher</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $class) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($class['name']); ?></td>
                                <td><?php echo htmlspecialchars($class['section']); ?></td>
                                <td><?php echo htmlspecialchars($class['school_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($class['teacher_name'] ?? 'Not assigned'); ?></td>
                                <td><?php echo formatDate($class['created_at'] ?? date('Y-m-d'), 'd M Y'); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <p class="text-muted mb-0">No classes found. Use the "Add Class" button to create one.</p>
        <?php } ?>
    </div>
</div>

<?php
require_once "../includes/footer.php";
?>
