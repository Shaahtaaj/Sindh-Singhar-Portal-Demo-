<?php
require_once "../includes/header.php";

if (!isAdmin()) {
    redirectWithMessage("../login.php", "You are not authorized to access this page.", "danger");
}

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Handle school creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_school'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $location = sanitizeInput($_POST['location'] ?? '');

    if (empty($name)) {
        redirectWithMessage('schools.php?action=add', 'School name is required.', 'warning');
    }

    // Prevent duplicate names (basic check)
    $stmtCheck = mysqli_prepare($conn, "SELECT id FROM schools WHERE name = ? LIMIT 1");
    if ($stmtCheck) {
        mysqli_stmt_bind_param($stmtCheck, 's', $name);
        mysqli_stmt_execute($stmtCheck);
        mysqli_stmt_store_result($stmtCheck);
        if (mysqli_stmt_num_rows($stmtCheck) > 0) {
            mysqli_stmt_close($stmtCheck);
            redirectWithMessage('schools.php?action=add', 'A school with that name already exists.', 'danger');
        }
        mysqli_stmt_close($stmtCheck);
    }

    $stmtInsert = mysqli_prepare($conn, "INSERT INTO schools (name, location, created_by, created_at) VALUES (?, ?, ?, NOW())");
    if ($stmtInsert) {
        mysqli_stmt_bind_param($stmtInsert, 'ssi', $name, $location, $userId);
        if (mysqli_stmt_execute($stmtInsert)) {
            mysqli_stmt_close($stmtInsert);
            redirectWithMessage('schools.php', 'School added successfully.', 'success');
        }
        mysqli_stmt_close($stmtInsert);
    }

    redirectWithMessage('schools.php?action=add', 'Failed to add school. Please try again.', 'danger');
}

// Fetch schools list
$schools = [];
$schoolSql = "SELECT s.*, u.name AS created_by_name FROM schools s LEFT JOIN users u ON s.created_by = u.id ORDER BY s.created_at DESC";
$schoolResult = mysqli_query($conn, $schoolSql);
if ($schoolResult) {
    while ($row = mysqli_fetch_assoc($schoolResult)) {
        $schools[] = $row;
    }
}

$action = $_GET['action'] ?? '';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <h1>Schools</h1>
    <div>
        <a href="schools.php?action=add" class="btn btn-success"><i class="fas fa-school"></i> Add School</a>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php if ($action === 'add') { ?>
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-plus"></i> Create School</div>
        <div class="card-body">
            <form method="post" action="schools.php">
                <input type="hidden" name="create_school" value="1">
                <div class="form-group">
                    <label for="name">School Name *</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="location">Location / Address</label>
                    <textarea class="form-control" id="location" name="location" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save School</button>
                <a href="schools.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
<?php } ?>

<div class="card">
    <div class="card-header"><i class="fas fa-list"></i> School Directory</div>
    <div class="card-body">
        <?php if (!empty($schools)) { ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Location</th>
                            <th>Created By</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schools as $school) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($school['name']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($school['location'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($school['created_by_name'] ?? 'System'); ?></td>
                                <td><?php echo formatDate($school['created_at'], 'd M Y'); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <p class="text-muted mb-0">No schools added yet. Use the "Add School" button to create one.</p>
        <?php } ?>
    </div>
</div>

<?php
require_once "../includes/footer.php";
?>
