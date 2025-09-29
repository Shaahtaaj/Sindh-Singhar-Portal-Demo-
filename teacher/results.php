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

$selectedExamId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

// Handle exam creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exam'])) {
    $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    $name = sanitizeInput($_POST['name'] ?? '');
    $examDate = $_POST['exam_date'] ?? '';
    $totalMarks = isset($_POST['total_marks']) ? (int)$_POST['total_marks'] : 0;
    $description = sanitizeInput($_POST['description'] ?? '');

    if ($classId === 0 || !in_array($classId, $classIds, true)) {
        redirectWithMessage("results.php", "Invalid class selection.", "danger");
    }

    if (empty($name) || empty($examDate) || $totalMarks <= 0) {
        redirectWithMessage("results.php?class_id={$classId}", "Please provide exam name, date, and total marks.", "warning");
    }

    $classRow = $classesById[$classId] ?? null;
    if (!$classRow) {
        redirectWithMessage("results.php", "Class not found.", "danger");
    }

    $schoolId = (int)($classRow['school_id'] ?? 0);
    $examDateObj = DateTime::createFromFormat('Y-m-d', $examDate);
    if (!$examDateObj) {
        redirectWithMessage("results.php?class_id={$classId}", "Invalid exam date format.", "danger");
    }
    $examDate = $examDateObj->format('Y-m-d');

    $stmt = mysqli_prepare($conn, "INSERT INTO exams (name, class_id, school_id, teacher_id, exam_date, total_marks, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "siiissi", $name, $classId, $schoolId, $teacherId, $examDate, $totalMarks, $description);
        if (mysqli_stmt_execute($stmt)) {
            $newExamId = mysqli_insert_id($conn);
            redirectWithMessage("results.php?class_id={$classId}&exam_id={$newExamId}", "Exam created successfully. You can now enter results.");
        }
        mysqli_stmt_close($stmt);
    }

    redirectWithMessage("results.php?class_id={$classId}", "Failed to create exam. Please try again.", "danger");
}

// Handle results submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results'])) {
    $examId = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : 0;
    $marksInput = $_POST['marks'] ?? [];
    $remarksInput = $_POST['remarks'] ?? [];

    if ($examId === 0) {
        redirectWithMessage("results.php", "Invalid exam selection.", "danger");
    }

    $examQuery = "SELECT e.*, c.teacher_id, c.class_id AS class_id FROM exams e JOIN classes c ON e.class_id = c.id WHERE e.id = ? AND e.teacher_id = ?";
    $stmtExam = mysqli_prepare($conn, $examQuery);
    if ($stmtExam) {
        mysqli_stmt_bind_param($stmtExam, "ii", $examId, $teacherId);
        mysqli_stmt_execute($stmtExam);
        $examResult = mysqli_stmt_get_result($stmtExam);
        if ($examResult && mysqli_num_rows($examResult) > 0) {
            $examRow = mysqli_fetch_assoc($examResult);
            $classId = (int)$examRow['class_id'];
            $totalMarks = (int)$examRow['total_marks'];
        } else {
            redirectWithMessage("results.php", "Exam not found or not assigned to you.", "danger");
        }
        mysqli_stmt_close($stmtExam);
    } else {
        redirectWithMessage("results.php", "Failed to load exam details.", "danger");
    }

    $students = getStudentsByClass($classId);
    if (empty($students)) {
        redirectWithMessage("results.php?class_id={$classId}", "No students found for this class.", "warning");
    }

    mysqli_begin_transaction($conn);
    $transactionOk = true;

    $stmtUpsert = mysqli_prepare($conn, "INSERT INTO results (exam_id, student_id, obtained_marks, grade, remarks, created_at)
                                       VALUES (?, ?, ?, ?, ?, NOW())
                                       ON DUPLICATE KEY UPDATE obtained_marks = VALUES(obtained_marks), grade = VALUES(grade), remarks = VALUES(remarks)");

    if (!$stmtUpsert) {
        $transactionOk = false;
    }

    if ($transactionOk) {
        foreach ($students as $student) {
            $studentId = (int)$student['id'];
            $marks = isset($marksInput[$studentId]) ? (int)$marksInput[$studentId] : null;
            if ($marks === null || $marks < 0 || $marks > $totalMarks) {
                $transactionOk = false;
                $errorStudent = htmlspecialchars($student['name']);
                $errorMessage = "Invalid marks for {$errorStudent}. Must be between 0 and {$totalMarks}.";
                break;
            }

            $percentage = $totalMarks > 0 ? ($marks / $totalMarks) * 100 : 0;
            if ($percentage >= 90) {
                $grade = 'A+';
            } elseif ($percentage >= 80) {
                $grade = 'A';
            } elseif ($percentage >= 70) {
                $grade = 'B';
            } elseif ($percentage >= 60) {
                $grade = 'C';
            } elseif ($percentage >= 50) {
                $grade = 'D';
            } else {
                $grade = 'F';
            }

            $remarks = sanitizeInput($remarksInput[$studentId] ?? '');

            mysqli_stmt_bind_param($stmtUpsert, "iiiss", $examId, $studentId, $marks, $grade, $remarks);
            if (!mysqli_stmt_execute($stmtUpsert)) {
                $transactionOk = false;
                break;
            }
        }
    }

    if ($transactionOk) {
        mysqli_commit($conn);
        redirectWithMessage("results.php?class_id={$classId}&exam_id={$examId}", "Results saved successfully.");
    } else {
        mysqli_rollback($conn);
        $message = isset($errorMessage) ? $errorMessage : "Failed to save results. Please try again.";
        redirectWithMessage("results.php?class_id={$classId}&exam_id={$examId}", $message, "danger");
    }
}

// Load exams for dropdown
$exams = [];
if (!empty($classIds)) {
    $classList = implode(',', $classIds);
    $sqlExams = "SELECT * FROM exams WHERE class_id IN ($classList) AND teacher_id = $teacherId ORDER BY exam_date DESC";
    $resultExams = mysqli_query($conn, $sqlExams);
    if ($resultExams) {
        while ($row = mysqli_fetch_assoc($resultExams)) {
            $exams[] = $row;
        }
    }
}

// Ensure selected exam belongs to teacher
if ($selectedExamId > 0) {
    $validExam = false;
    foreach ($exams as $exam) {
        if ((int)$exam['id'] === $selectedExamId) {
            $selectedClassId = (int)$exam['class_id'];
            $validExam = true;
            break;
        }
    }
    if (!$validExam) {
        $selectedExamId = 0;
    }
}

// Build exams for selected class
$examsByClass = [];
foreach ($exams as $exam) {
    $classId = (int)$exam['class_id'];
    if (!isset($examsByClass[$classId])) {
        $examsByClass[$classId] = [];
    }
    $examsByClass[$classId][] = $exam;
}

$selectedExam = null;
$studentsForClass = [];
$existingResults = [];

if ($selectedClassId > 0) {
    $studentsForClass = getStudentsByClass($selectedClassId);
}

if ($selectedExamId > 0) {
    $selectedExam = null;
    foreach ($exams as $exam) {
        if ((int)$exam['id'] === $selectedExamId) {
            $selectedExam = $exam;
            break;
        }
    }

    if ($selectedExam) {
        $stmtResults = mysqli_prepare($conn, "SELECT * FROM results WHERE exam_id = ?");
        if ($stmtResults) {
            mysqli_stmt_bind_param($stmtResults, "i", $selectedExamId);
            mysqli_stmt_execute($stmtResults);
            $resultsData = mysqli_stmt_get_result($stmtResults);
            if ($resultsData) {
                while ($row = mysqli_fetch_assoc($resultsData)) {
                    $existingResults[(int)$row['student_id']] = $row;
                }
            }
            mysqli_stmt_close($stmtResults);
        }
    }
}
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1>Exam Results</h1>
    <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-filter"></i> Select Class & Exam
    </div>
    <div class="card-body">
        <form class="form-row" method="get" action="results.php">
            <div class="form-group col-md-4">
                <label for="class_id">Class</label>
                <select class="form-control" id="class_id" name="class_id" onchange="this.form.submit()">
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo (int)$class['id']; ?>" <?php echo $selectedClassId === (int)$class['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['name'] . ' ' . ($class['section'] ?? '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-4">
                <label for="exam_id">Exam</label>
                <select class="form-control" id="exam_id" name="exam_id" onchange="this.form.submit()">
                    <option value="">Select Exam</option>
                    <?php if ($selectedClassId > 0 && isset($examsByClass[$selectedClassId])): ?>
                        <?php foreach ($examsByClass[$selectedClassId] as $exam): ?>
                            <option value="<?php echo (int)$exam['id']; ?>" <?php echo $selectedExamId === (int)$exam['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(($exam['name'] ?? 'Exam') . ' (' . formatDate($exam['exam_date']) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedClassId > 0): ?>
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-plus"></i> Create Exam
        </div>
        <div class="card-body">
            <form method="post" action="results.php">
                <input type="hidden" name="create_exam" value="1">
                <input type="hidden" name="class_id" value="<?php echo $selectedClassId; ?>">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="exam_name">Exam Name *</label>
                        <input type="text" class="form-control" id="exam_name" name="name" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="exam_date">Exam Date *</label>
                        <input type="date" class="form-control" id="exam_date" name="exam_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="total_marks">Total Marks *</label>
                        <input type="number" class="form-control" id="total_marks" name="total_marks" min="1" value="100" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Description / Notes</label>
                    <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Create Exam</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($selectedExam && !empty($studentsForClass)): ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-clipboard-list"></i> Enter Results for <?php echo htmlspecialchars($selectedExam['name']); ?></span>
            <span>Total Marks: <?php echo (int)$selectedExam['total_marks']; ?></span>
        </div>
        <div class="card-body">
            <form method="post" action="results.php?class_id=<?php echo $selectedClassId; ?>&exam_id=<?php echo $selectedExamId; ?>">
                <input type="hidden" name="save_results" value="1">
                <input type="hidden" name="exam_id" value="<?php echo $selectedExamId; ?>">

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Father's Name</th>
                                <th>Marks (out of <?php echo (int)$selectedExam['total_marks']; ?>)</th>
                                <th>Grade</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentsForClass as $student): ?>
                                <?php
                                    $studentId = (int)$student['id'];
                                    $existing = $existingResults[$studentId] ?? null;
                                    $marksValue = $existing['obtained_marks'] ?? '';
                                    $gradeValue = $existing['grade'] ?? '';
                                    $remarksValue = $existing['remarks'] ?? '';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                                    <td style="width: 180px;">
                                        <input type="number" class="form-control" name="marks[<?php echo $studentId; ?>]"
                                               min="0" max="<?php echo (int)$selectedExam['total_marks']; ?>"
                                               value="<?php echo htmlspecialchars($marksValue); ?>" required>
                                    </td>
                                    <td style="width: 100px;">
                                        <span class="badge badge-info"><?php echo !empty($gradeValue) ? htmlspecialchars($gradeValue) : '-'; ?></span>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" name="remarks[<?php echo $studentId; ?>]" value="<?php echo htmlspecialchars($remarksValue); ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="text-right">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Results</button>
                </div>
            </form>
        </div>
    </div>
<?php elseif ($selectedClassId > 0 && empty($studentsForClass)): ?>
    <div class="alert alert-info">No students found for the selected class.</div>
<?php elseif ($selectedClassId > 0 && $selectedExamId === 0): ?>
    <div class="alert alert-info">Select an exam to enter results or create a new one above.</div>
<?php endif; ?>

<?php if ($selectedExamId > 0 && !empty($existingResults)): ?>
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-table"></i> Existing Results</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Marks</th>
                            <th>Grade</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($studentsForClass as $student): ?>
                            <?php $existing = $existingResults[(int)$student['id']] ?? null; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo $existing ? (int)$existing['obtained_marks'] : '-'; ?></td>
                                <td><?php echo $existing ? htmlspecialchars($existing['grade']) : '-'; ?></td>
                                <td><?php echo $existing ? htmlspecialchars($existing['remarks']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
require_once "../includes/footer.php";
?>
