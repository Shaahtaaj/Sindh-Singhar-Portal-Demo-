<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirectWithMessage('../login.php', 'Please log in to continue.', 'danger');
}

if (!isAdmin()) {
    redirectWithMessage('../login.php', 'You are not authorized to access this page.', 'danger');
}

$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($reportId <= 0) {
    redirectWithMessage('reports.php', 'Invalid report selection.', 'warning');
}

$reportDetails = null;
$sql = "SELECT r.*, 
               u.name AS teacher_name, 
               u.email AS teacher_email,
               c.name AS class_name,
               c.section AS class_section,
               s.name AS school_name
        FROM reports r
        JOIN users u ON r.teacher_id = u.id
        JOIN classes c ON r.class_id = c.id
        JOIN schools s ON c.school_id = s.id
        WHERE r.id = ?";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $reportId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) > 0) {
        $reportDetails = mysqli_fetch_assoc($result);
    }
    mysqli_stmt_close($stmt);
}

if (!$reportDetails) {
    redirectWithMessage('reports.php', 'Report not found or already removed.', 'warning');
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>Report Details</h1>
        <p class="text-muted mb-0">Submitted by <?php echo htmlspecialchars($reportDetails['teacher_name']); ?> on <?php echo formatDate($reportDetails['date']); ?></p>
    </div>
    <div>
        <a href="reports.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Reports</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="fas fa-user"></i> Teacher Information</div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Name:</strong> <?php echo htmlspecialchars($reportDetails['teacher_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($reportDetails['teacher_email']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Class:</strong> <?php echo htmlspecialchars(($reportDetails['class_name'] ?? '') . ' ' . ($reportDetails['class_section'] ?? '')); ?></p>
                <p><strong>School:</strong> <?php echo htmlspecialchars($reportDetails['school_name'] ?? ''); ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="fas fa-file-alt"></i> Report Summary</div>
    <div class="card-body">
        <p><strong>Type:</strong> <?php echo htmlspecialchars(ucfirst($reportDetails['type'] ?? '')); ?></p>
        <p><strong>Date:</strong> <?php echo formatDate($reportDetails['date']); ?></p>
        <p><strong>Submitted At:</strong> <?php echo formatDate($reportDetails['created_at'], 'd-m-Y H:i'); ?></p>
        <div class="mt-3">
            <h5>Report Content</h5>
            <p class="mb-0" style="white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($reportDetails['content'])); ?></p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="fas fa-paperclip"></i> Attachments</div>
    <div class="card-body">
        <?php if (!empty($reportDetails['file_path'])): ?>
            <a href="<?php echo htmlspecialchars($reportDetails['file_path']); ?>" target="_blank" class="btn btn-outline-primary">
                <i class="fas fa-download"></i> View Attachment
            </a>
        <?php else: ?>
            <span class="text-muted">No attachment uploaded for this report.</span>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
