<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../includes/functions.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

if (!isAdmin()) {
    redirectWithMessage("../login.php", "You are not authorized to access this page.", "danger");
}

// Import PhpSpreadsheet classes
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Ensure template directory exists ahead of template downloads
$template_dir = "../templates";
if (!file_exists($template_dir)) {
    mkdir($template_dir, 0777, true);
}

// Handle Excel template download before rendering header output
if (isset($_GET['action']) && $_GET['action'] === 'create_template') {
    require_once '../vendor/autoload.php';

    try {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Student Name');
        $sheet->setCellValue('B1', 'Father Name');
        $sheet->setCellValue('C1', 'Gender');
        $sheet->setCellValue('D1', 'DOB');
        $sheet->setCellValue('E1', 'Class');
        $sheet->setCellValue('F1', 'Section');
        $sheet->setCellValue('G1', 'Admission Date');

        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '28A745'],
            ],
        ];

        $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $validation = $sheet->getCell('C2')->getDataValidation();
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
        $validation->setAllowBlank(false);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1('"Male,Female,Other"');

        for ($i = 3; $i <= 100; $i++) {
            $sheet->getCell('C' . $i)->setDataValidation(clone $validation);
        }

        $sheet->setCellValue('A2', 'John Doe');
        $sheet->setCellValue('B2', 'Robert Doe');
        $sheet->setCellValue('C2', 'Male');
        $sheet->setCellValue('D2', '2010-01-15');
        $sheet->setCellValue('E2', '5');
        $sheet->setCellValue('F2', 'A');
        $sheet->setCellValue('G2', date('Y-m-d'));

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="student_admission_template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
    } catch (Exception $e) {
        redirectWithMessage('students.php', 'Error creating template: ' . $e->getMessage(), 'danger');
    }

    exit;
}

// Include header after handling template downloads
require_once "../includes/header.php";

// Check if user is admin
if(!isAdmin()){
    redirectWithMessage("../login.php", "You are not authorized to access this page.", "danger");
}

// Create directory for uploads if it doesn't exist
$upload_dir = "../uploads/excel";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle Excel import
if(isset($_POST['import_excel'])) {
    // Check if file was uploaded without errors
    if(isset($_FILES["excel_file"]) && $_FILES["excel_file"]["error"] == 0) {
        $allowed = array("xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", "xls" => "application/vnd.ms-excel");
        $filename = $_FILES["excel_file"]["name"];
        $filetype = $_FILES["excel_file"]["type"];
        $filesize = $_FILES["excel_file"]["size"];
        
        // Verify file extension
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if(!array_key_exists($ext, $allowed)) {
            redirectWithMessage("students.php", "Error: Please select a valid Excel file format.", "danger");
        }
        
        // Verify file size - 5MB maximum
        $maxsize = 5 * 1024 * 1024;
        if($filesize > $maxsize) {
            redirectWithMessage("students.php", "Error: File size is larger than the allowed limit (5MB).", "danger");
        }
        
        // Verify MIME type of the file
        if(in_array($filetype, $allowed)) {
            // Check if class exists
            $class_id = sanitizeInput($_POST['class_id']);
            $class = getClassById($class_id);
            
            if(!$class) {
                redirectWithMessage("students.php", "Error: Selected class does not exist.", "danger");
            }
            
            // Save file to uploads directory
            $new_filename = uniqid() . "." . $ext;
            $target_file = $upload_dir . "/" . $new_filename;
            
            if(move_uploaded_file($_FILES["excel_file"]["tmp_name"], $target_file)) {
                // Process Excel file
                require_once '../vendor/autoload.php'; // Make sure PhpSpreadsheet is installed
                
                try {
                    $reader = IOFactory::createReaderForFile($target_file);
                    $spreadsheet = $reader->load($target_file);
                    $worksheet = $spreadsheet->getActiveSheet();
                    
                    $highestRow = $worksheet->getHighestRow();
                    $highestColumn = $worksheet->getHighestColumn();
                    
                    // Start from row 2 (assuming row 1 is header)
                    $success_count = 0;
                    $error_count = 0;
                    $errors = [];
                    
                    for ($row = 2; $row <= $highestRow; $row++) {
                        $student_name = $worksheet->getCell('A' . $row)->getValue();
                        $father_name = $worksheet->getCell('B' . $row)->getValue();
                        $gender = strtolower($worksheet->getCell('C' . $row)->getValue());
                        $dob = $worksheet->getCell('D' . $row)->getValue();
                        $section = $worksheet->getCell('F' . $row)->getValue();
                        $admission_date = $worksheet->getCell('G' . $row)->getValue();
                        
                        // Validate data
                        if(empty($student_name)) {
                            $errors[] = "Row $row: Student name is required.";
                            $error_count++;
                            continue;
                        }
                        
                        // Format dates
                        try {
                            if(!empty($dob)) {
                                $dob = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$dob)->format('Y-m-d');

                            } else {
                                $dob = null;
                            }
                            
                            if(!empty($admission_date)) {
                                $admission_date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($admission_date)->format('Y-m-d');
                            } else {
                                $admission_date = date('Y-m-d'); // Default to today
                            }
                        } catch(Exception $e) {
                            $errors[] = "Row $row: Invalid date format.";
                            $error_count++;
                            continue;
                        }
                        
                        // Validate gender
                        if(!empty($gender) && !in_array($gender, ['male', 'female', 'other'])) {
                            $errors[] = "Row $row: Gender must be Male, Female, or Other.";
                            $error_count++;
                            continue;
                        }
                        
                        // Insert student into database
                        $school_id = $class['school_id'];
                        
                        $sql = "INSERT INTO students (name, father_name, gender, dob, class_id, admission_date, school_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                        
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "ssssssi", $student_name, $father_name, $gender, $dob, $class_id, $admission_date, $school_id);
                        
                        if(mysqli_stmt_execute($stmt)) {
                            $success_count++;
                        } else {
                            $errors[] = "Row $row: " . mysqli_error($conn);
                            $error_count++;
                        }
                        
                        mysqli_stmt_close($stmt);
                    }
                    
                    // Set message based on results
                    if($success_count > 0) {
                        $message = "$success_count students imported successfully.";
                        if($error_count > 0) {
                            $message .= " $error_count errors occurred.";
                            $_SESSION['import_errors'] = $errors;
                        }
                        redirectWithMessage("students.php", $message, ($error_count > 0) ? "warning" : "success");
                    } else {
                        $_SESSION['import_errors'] = $errors;
                        redirectWithMessage("students.php", "No students were imported. Please check the errors.", "danger");
                    }
                    
                } catch(Exception $e) {
                    redirectWithMessage("students.php", "Error processing Excel file: " . $e->getMessage(), "danger");
                }
                
            } else {
                redirectWithMessage("students.php", "Error uploading file.", "danger");
            }
        } else {
            redirectWithMessage("students.php", "Error: There was a problem with the uploaded file.", "danger");
        }
    } else {
        redirectWithMessage("students.php", "Error: No file uploaded or file upload error.", "danger");
    }
}

// Get all classes for dropdown
$school_id = isset($_SESSION["school_id"]) ? $_SESSION["school_id"] : 0;
$classes = [];

if($school_id > 0) {
    $classes = getClassesBySchool($school_id);
} else {
    $sql = "SELECT c.*, s.name as school_name FROM classes c JOIN schools s ON c.school_id = s.id ORDER BY s.name, c.name, c.section";
    $result = mysqli_query($conn, $sql);
    
    if($result) {
        while($row = mysqli_fetch_assoc($result)) {
            $classes[] = $row;
        }
    }
}

// Get students based on filter
$filter_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$students = [];

$sql = "SELECT s.*, c.name as class_name, c.section, sc.name as school_name 
        FROM students s 
        JOIN classes c ON s.class_id = c.id 
        JOIN schools sc ON s.school_id = sc.id";

if($filter_class > 0) {
    $sql .= " WHERE s.class_id = $filter_class";
} else if($school_id > 0) {
    $sql .= " WHERE s.school_id = $school_id";
}

$sql .= " ORDER BY s.name";
$result = mysqli_query($conn, $sql);

if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
}
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h1>Students Management</h1>
    <div>
        <a href="?action=create_template" class="btn btn-success">
            <i class="fas fa-download"></i> Download Excel Template
        </a>
    </div>
</div>

<!-- Import Excel Form -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-file-import"></i> Import Students from Excel
    </div>
    <div class="card-body">
        <form action="students.php" method="post" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="excel_file">Excel File</label>
                    <input type="file" class="form-control-file" id="excel_file" name="excel_file" required>
                    <small class="form-text text-muted">Upload Excel file with student data. Use the template for correct format.</small>
                </div>
                <div class="form-group col-md-6">
                    <label for="class_id">Assign to Class</label>
                    <select class="form-control" id="class_id" name="class_id" required>
                        <option value="">Select Class</option>
                        <?php foreach($classes as $class): ?>
                            <?php if(isset($class['school_name'])): ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo htmlspecialchars($class['school_name'] . ' - ' . $class['name'] . ' ' . $class['section']); ?>
                                </option>
                            <?php else: ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo htmlspecialchars($class['name'] . ' ' . $class['section']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" name="import_excel" class="btn btn-primary">
                <i class="fas fa-upload"></i> Import Students
            </button>
        </form>
        
        <?php if(isset($_SESSION['import_errors'])): ?>
            <div class="mt-3">
                <h5>Import Errors:</h5>
                <ul class="text-danger">
                    <?php foreach($_SESSION['import_errors'] as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['import_errors']); ?>
        <?php endif; ?>
    </div>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-filter"></i> Filter Students
    </div>
    <div class="card-body">
        <form action="students.php" method="get">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="filter_class">Filter by Class</label>
                    <select class="form-control" id="filter_class" name="class_id">
                        <option value="">All Classes</option>
                        <?php foreach($classes as $class): ?>
                            <?php if(isset($class['school_name'])): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo ($filter_class == $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['school_name'] . ' - ' . $class['name'] . ' ' . $class['section']); ?>
                                </option>
                            <?php else: ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo ($filter_class == $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['name'] . ' ' . $class['section']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="students.php" class="btn btn-secondary">
                        <i class="fas fa-sync"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Students List -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-user-graduate"></i> Students List
    </div>
    <div class="card-body">
        <?php if(count($students) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered" id="studentsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Father's Name</th>
                            <th>Gender</th>
                            <th>DOB</th>
                            <th>Class</th>
                            <th>School</th>
                            <th>Admission Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($students as $student): ?>
                            <tr>
                                <td><?php echo $student['id']; ?></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($student['gender'])); ?></td>
                                <td><?php echo !empty($student['dob']) ? formatDate($student['dob']) : ''; ?></td>
                                <td><?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section']); ?></td>
                                <td><?php echo htmlspecialchars($student['school_name']); ?></td>
                                <td><?php echo formatDate($student['admission_date']); ?></td>
                                <td>
                                    <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_student.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this student?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-center">No students found. Import students using Excel or add them manually.</p>
        <?php endif; ?>
    </div>
    <div class="card-footer">
        <a href="export_students.php<?php echo $filter_class ? "?class_id=$filter_class" : ""; ?>" class="btn btn-success">
            <i class="fas fa-file-excel"></i> Export to Excel
        </a>
        <a href="export_students.php?format=pdf<?php echo $filter_class ? "&class_id=$filter_class" : ""; ?>" class="btn btn-danger">
            <i class="fas fa-file-pdf"></i> Export to PDF
        </a>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#studentsTable').DataTable();
    });
</script>

<?php
// Include footer
require_once "../includes/footer.php";
?>