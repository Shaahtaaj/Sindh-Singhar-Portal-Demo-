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
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

if ($selectedClassId > 0 && !in_array($selectedClassId, $classIds, true)) {
    $selectedClassId = 0;
}

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    $attendanceDate = $_POST['date'] ?? date('Y-m-d');
    $statuses = $_POST['status'] ?? [];
    $remarksInput = $_POST['remarks'] ?? [];

    if ($classId === 0 || !in_array($classId, $classIds, true)) {
        redirectWithMessage("attendance.php", "Invalid class selection.", "danger");
    }

    $attendanceDateObj = DateTime::createFromFormat('Y-m-d', $attendanceDate);
    if (!$attendanceDateObj) {
        redirectWithMessage("attendance.php?class_id={$classId}", "Invalid date format.", "danger");
    }
    $attendanceDate = $attendanceDateObj->format('Y-m-d');

    $students = getStudentsByClass($classId);
    if (empty($students)) {
        redirectWithMessage("attendance.php?class_id={$classId}&date={$attendanceDate}", "No students found for the selected class.", "warning");
    }

    mysqli_begin_transaction($conn);
    $transactionOk = true;

    // Check if attendance record already exists
    $attendanceId = null;
    $stmt = mysqli_prepare($conn, "SELECT id FROM attendance WHERE class_id = ? AND date = ? AND teacher_id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "isi", $classId, $attendanceDate, $teacherId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $existingId);
        if (mysqli_stmt_fetch($stmt)) {
            $attendanceId = (int)$existingId;
        }
        mysqli_stmt_close($stmt);
    } else {
        $transactionOk = false;
    }

    if ($transactionOk && $attendanceId === null) {
        $stmtInsert = mysqli_prepare($conn, "INSERT INTO attendance (class_id, date, present_count, absent_count, details, teacher_id) VALUES (?, ?, 0, 0, NULL, ?)");
        if ($stmtInsert) {
            mysqli_stmt_bind_param($stmtInsert, "isi", $classId, $attendanceDate, $teacherId);
            if (mysqli_stmt_execute($stmtInsert)) {
                $attendanceId = mysqli_insert_id($conn);
            } else {
                $transactionOk = false;
            }
            mysqli_stmt_close($stmtInsert);
        } else {
            $transactionOk = false;
        }
    }

    if ($transactionOk && $attendanceId !== null) {
        $stmtDelete = mysqli_prepare($conn, "DELETE FROM attendance_details WHERE attendance_id = ?");
        if ($stmtDelete) {
            mysqli_stmt_bind_param($stmtDelete, "i", $attendanceId);
            mysqli_stmt_execute($stmtDelete);
            mysqli_stmt_close($stmtDelete);
        } else {
            $transactionOk = false;
        }
    }

    $presentCount = 0;
    $absentCount = 0;
    $leaveCount = 0;

    if ($transactionOk && $attendanceId !== null) {
        $stmtDetail = mysqli_prepare($conn, "INSERT INTO attendance_details (attendance_id, student_id, status, remarks) VALUES (?, ?, ?, ?)");
        if ($stmtDetail) {
            foreach ($students as $student) {
                $studentId = (int)$student['id'];
                $status = isset($statuses[$studentId]) ? strtolower($statuses[$studentId]) : 'present';
                if (!in_array($status, ['present', 'absent', 'leave'], true)) {
                    $status = 'present';
                }
                $remarks = isset($remarksInput[$studentId]) ? trim($remarksInput[$studentId]) : null;

                if ($status === 'present') {
                    $presentCount++;
                } elseif ($status === 'absent') {
                    $absentCount++;
                } else {
                    $leaveCount++;
                }

                mysqli_stmt_bind_param($stmtDetail, "iiss", $attendanceId, $studentId, $status, $remarks);
                if (!mysqli_stmt_execute($stmtDetail)) {
                    $transactionOk = false;
                    break;
                }
            }
            mysqli_stmt_close($stmtDetail);
        } else {
            $transactionOk = false;
        }
    }

    if ($transactionOk && $attendanceId !== null) {
        $summary = json_encode([
            'present' => $presentCount,
            'absent' => $absentCount,
            'leave' => $leaveCount,
        ]);
        $stmtUpdate = mysqli_prepare($conn, "UPDATE attendance SET present_count = ?, absent_count = ?, details = ? WHERE id = ?");
        if ($stmtUpdate) {
            mysqli_stmt_bind_param($stmtUpdate, "iisi", $presentCount, $absentCount, $summary, $attendanceId);
            if (!mysqli_stmt_execute($stmtUpdate)) {
                $transactionOk = false;
            }
            mysqli_stmt_close($stmtUpdate);
        } else {
            $transactionOk = false;
        }
    }

    if ($transactionOk) {
        mysqli_commit($conn);
        redirectWithMessage("attendance.php?class_id={$classId}&date={$attendanceDate}", "Attendance saved successfully.");
    } else {
        mysqli_rollback($conn);
        redirectWithMessage("attendance.php?class_id={$classId}&date={$attendanceDate}", "Failed to save attendance. Please try again.", "danger");
    }
}

$attendanceRecord = null;
$attendanceDetails = [];
$studentsInClass = [];
$attendanceDetailMap = [];

if ($selectedClassId > 0) {
    $stmtAttendance = mysqli_prepare($conn, "SELECT id, present_count, absent_count, details FROM attendance WHERE class_id = ? AND date = ? AND teacher_id = ? LIMIT 1");
    if ($stmtAttendance) {
        mysqli_stmt_bind_param($stmtAttendance, "isi", $selectedClassId, $selectedDate, $teacherId);
        mysqli_stmt_execute($stmtAttendance);
        $result = mysqli_stmt_get_result($stmtAttendance);
        if ($result && mysqli_num_rows($result) > 0) {
            $attendanceRecord = mysqli_fetch_assoc($result);
        }
        mysqli_stmt_close($stmtAttendance);
    }

    if ($attendanceRecord) {
        $stmtDetails = mysqli_prepare($conn, "SELECT ad.*, s.name AS student_name, s.father_name FROM attendance_details ad JOIN students s ON ad.student_id = s.id WHERE ad.attendance_id = ? ORDER BY s.name");
        if ($stmtDetails) {
            mysqli_stmt_bind_param($stmtDetails, "i", $attendanceRecord['id']);
            mysqli_stmt_execute($stmtDetails);
            $detailsResult = mysqli_stmt_get_result($stmtDetails);
            if ($detailsResult) {
                while ($row = mysqli_fetch_assoc($detailsResult)) {
                    $attendanceDetails[] = $row;
                    $attendanceDetailMap[(int)$row['student_id']] = $row;
                }
            }
            mysqli_stmt_close($stmtDetails);
        }
    } else {
        $studentsInClass = getStudentsByClass($selectedClassId);
    }
}

$isEditing = ($attendanceRecord && isset($_GET['edit']));
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1>Mark Attendance</h1>
    <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-filter"></i> Select Class & Date
    </div>
    <div class="card-body">
        <form class="form-inline" method="get" action="attendance.php">
            <div class="form-group mr-3">
                <label class="mr-2" for="class_id">Class</label>
                <select class="form-control" id="class_id" name="class_id" required>
                    <option value="">Select class</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo (int)$class['id']; ?>" <?php echo $selectedClassId === (int)$class['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['name'] . ' ' . ($class['section'] ?? '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mr-3">
                <label class="mr-2" for="date">Date</label>
                <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Load</button>
        </form>
    </div>
</div>

<?php if ($selectedClassId > 0): ?>
    <?php if ($attendanceRecord && !$isEditing): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-clipboard-list"></i> Attendance Recorded</span>
                <span class="badge badge-success">Saved</span>
            </div>
            <div class="card-body">
                <p class="mb-3">
                    <strong>Date:</strong> <?php echo formatDate($selectedDate); ?>
                    <span class="ml-3"><strong>Present:</strong> <?php echo (int)$attendanceRecord['present_count']; ?></span>
                    <span class="ml-3"><strong>Absent:</strong> <?php echo (int)$attendanceRecord['absent_count']; ?></span>
                </p>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Father's Name</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendanceDetails as $detail): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($detail['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($detail['father_name']); ?></td>
                                    <td>
                                        <?php if ($detail['status'] === 'present'): ?>
                                            <span class="badge badge-success">Present</span>
                                        <?php elseif ($detail['status'] === 'absent'): ?>
                                            <span class="badge badge-danger">Absent</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Leave</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($detail['remarks']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <a class="btn btn-outline-primary" href="attendance.php?class_id=<?php echo $selectedClassId; ?>&date=<?php echo $selectedDate; ?>&edit=1">
                        <i class="fas fa-edit"></i> Edit Attendance
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php
            if ($isEditing) {
                $studentsInClass = getStudentsByClass($selectedClassId);
            }
        ?>
        <?php if (!empty($studentsInClass)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user-check"></i>
                    <?php if ($isEditing): ?>
                        Edit Attendance for <?php echo formatDate($selectedDate); ?>
                    <?php else: ?>
                        Mark Attendance for <?php echo formatDate($selectedDate); ?>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="post" action="attendance.php">
                        <input type="hidden" name="class_id" value="<?php echo $selectedClassId; ?>">
                        <input type="hidden" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>">

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Father's Name</th>
                                        <th>Status</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($studentsInClass as $student): ?>
                                        <?php
                                            $studentId = (int)$student['id'];
                                            $existingDetail = $attendanceDetailMap[$studentId] ?? null;
                                            $existingStatus = $existingDetail['status'] ?? 'present';
                                            $existingRemarks = $existingDetail['remarks'] ?? '';
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                                            <td>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="status[<?php echo $studentId; ?>]" id="present_<?php echo $studentId; ?>" value="present" <?php echo ($existingStatus === 'present') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="present_<?php echo $studentId; ?>">Present</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="status[<?php echo $studentId; ?>]" id="absent_<?php echo $studentId; ?>" value="absent" <?php echo ($existingStatus === 'absent') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="absent_<?php echo $studentId; ?>">Absent</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="status[<?php echo $studentId; ?>]" id="leave_<?php echo $studentId; ?>" value="leave" <?php echo ($existingStatus === 'leave') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="leave_<?php echo $studentId; ?>">Leave</label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" name="remarks[<?php echo $studentId; ?>]" placeholder="Optional" value="<?php echo htmlspecialchars($existingRemarks); ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="text-right">
                            <button type="submit" name="mark_attendance" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo $isEditing ? 'Update Attendance' : 'Save Attendance'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No students available for this class.</div>
        <?php endif; ?>
    <?php endif; ?>
<?php else: ?>
    <div class="alert alert-info">Select a class and date to view or mark attendance.</div>
<?php endif; ?>

<?php
require_once "../includes/footer.php";
?>
