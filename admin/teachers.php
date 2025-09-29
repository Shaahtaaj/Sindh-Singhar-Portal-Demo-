<?php
require_once "../includes/header.php";

if (!isAdmin()) {
    redirectWithMessage("../login.php", "You are not authorized to access this page.", "danger");
}

$schoolId = isset($_SESSION['school_id']) ? (int)$_SESSION['school_id'] : 0;

// Handle teacher creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_teacher'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $assignedSchoolId = isset($_POST['school_id']) ? (int)$_POST['school_id'] : 0;

    if (empty($name) || empty($email) || empty($password)) {
        redirectWithMessage('teachers.php?action=add', 'All fields are required to create a teacher.', 'warning');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirectWithMessage('teachers.php?action=add', 'Please provide a valid email address.', 'warning');
    }

    if ($schoolId > 0) {
        $assignedSchoolId = $schoolId;
    }

    if ($assignedSchoolId <= 0) {
        redirectWithMessage('teachers.php?action=add', 'Please select a school for the teacher.', 'warning');
    }

    // Ensure email is unique
    $stmtCheck = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
    if ($stmtCheck) {
        mysqli_stmt_bind_param($stmtCheck, 's', $email);
        mysqli_stmt_execute($stmtCheck);
        mysqli_stmt_store_result($stmtCheck);
        if (mysqli_stmt_num_rows($stmtCheck) > 0) {
            mysqli_stmt_close($stmtCheck);
            redirectWithMessage('teachers.php?action=add', 'Email already exists. Choose a different one.', 'danger');
        }
        mysqli_stmt_close($stmtCheck);
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmtInsert = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role, school_id, created_at) VALUES (?, ?, ?, 'teacher', ?, NOW())");
    if ($stmtInsert) {
        mysqli_stmt_bind_param($stmtInsert, 'sssi', $name, $email, $hashedPassword, $assignedSchoolId);
        if (mysqli_stmt_execute($stmtInsert)) {
            mysqli_stmt_close($stmtInsert);
            redirectWithMessage('teachers.php', 'Teacher account created successfully. Share credentials securely.', 'success');
        }
        mysqli_stmt_close($stmtInsert);
    }

    redirectWithMessage('teachers.php?action=add', 'Failed to create teacher. Please try again.', 'danger');
}

// Fetch schools for dropdowns
$schools = [];
$schoolSql = "SELECT id, name FROM schools";
if ($schoolId > 0) {
    $schoolSql .= " WHERE id = $schoolId";
}
$schoolSql .= " ORDER BY name";
$schoolResult = mysqli_query($conn, $schoolSql);
if ($schoolResult) {
    while ($row = mysqli_fetch_assoc($schoolResult)) {
        $schools[] = $row;
    }
}

// Fetch teacher list
$teachers = [];
$teacherSql = "SELECT u.*, s.name AS school_name FROM users u LEFT JOIN schools s ON u.school_id = s.id WHERE u.role = 'teacher'";
if ($schoolId > 0) {
    $teacherSql .= " AND u.school_id = $schoolId";
}
$teacherSql .= " ORDER BY u.created_at DESC";
$teacherResult = mysqli_query($conn, $teacherSql);
if ($teacherResult) {
    while ($row = mysqli_fetch_assoc($teacherResult)) {
        $teachers[] = $row;
    }
}

$action = $_GET['action'] ?? '';

?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1>Teachers</h1>
    <div>
        <a href="teachers.php?action=add" class="btn btn-success"><i class="fas fa-user-plus"></i> Add Teacher</a>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php if ($action === 'add') { ?>
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-user-plus"></i> Create Teacher Account</div>
        <div class="card-body">
            <form method="post" action="teachers.php">
                <input type="hidden" name="create_teacher" value="1">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="name">Full Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="email">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="password">Password *</label>
                        <input type="text" class="form-control" id="password" name="password" placeholder="Provide temporary password" required>
                    </div>
                </div>
                <?php if ($schoolId === 0) { ?>
                    <div class="form-group">
                        <label for="school_id">Assign School *</label>
                        <select class="form-control" id="school_id" name="school_id" required>
                            <option value="">Select school</option>
                            <?php foreach ($schools as $school) { ?>
                                <option value="<?php echo (int)$school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } else { ?>
                    <input type="hidden" name="school_id" value="<?php echo $schoolId; ?>">
                <?php } ?>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Teacher</button>
                <a href="teachers.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
<?php } ?>

<div class="card">
    <div class="card-header"><i class="fas fa-users"></i> Teacher Directory</div>
    <div class="card-body">
        <?php if (!empty($teachers)) { ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>School</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $teacher) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($teacher['name']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['school_name'] ?? 'N/A'); ?></td>
                                <td><?php echo formatDate($teacher['created_at'], 'd M Y'); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <p class="text-muted mb-0">No teachers added yet. Use the "Add Teacher" button to create accounts.</p>
        <?php } ?>
    </div>
</div>

<?php
require_once "../includes/footer.php";
?>
