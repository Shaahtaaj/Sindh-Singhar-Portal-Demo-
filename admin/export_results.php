<?php
// Include header
require_once "../includes/header.php";

// Check if user is admin
if(!isAdmin()){
    redirectWithMessage("../login.php", "You are not authorized to access this page.", "danger");
}

// Check if exam_id is provided
if(!isset($_GET['exam_id']) || empty($_GET['exam_id'])) {
    redirectWithMessage("results.php", "Please select an exam first.", "warning");
    exit;
}

$exam_id = intval($_GET['exam_id']);
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';

// Get exam details
$exam_details = null;
$sql = "SELECT e.*, c.name as class_name, c.section, s.name as school_name 
        FROM exams e 
        JOIN classes c ON e.class_id = c.id 
        JOIN schools s ON e.school_id = s.id
        WHERE e.id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $exam_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if($result && mysqli_num_rows($result) > 0) {
    $exam_details = mysqli_fetch_assoc($result);
} else {
    redirectWithMessage("results.php", "Exam not found.", "danger");
    exit;
}

// Get results
$results = [];
$sql = "SELECT r.*, s.name as student_name, s.father_name, s.roll_number
        FROM results r 
        JOIN students s ON r.student_id = s.id 
        WHERE r.exam_id = ?
        ORDER BY s.name";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $exam_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $results[] = $row;
    }
}

// Calculate statistics
$total_students = count($results);
$total_passed = 0;
$total_failed = 0;
$highest_marks = 0;
$lowest_marks = $exam_details['total_marks'];
$total_obtained = 0;

foreach($results as $result) {
    $percentage = ($result['obtained_marks'] / $exam_details['total_marks']) * 100;
    
    if($percentage >= 33) {
        $total_passed++;
    } else {
        $total_failed++;
    }
    
    if($result['obtained_marks'] > $highest_marks) {
        $highest_marks = $result['obtained_marks'];
    }
    
    if($result['obtained_marks'] < $lowest_marks) {
        $lowest_marks = $result['obtained_marks'];
    }
    
    $total_obtained += $result['obtained_marks'];
}

$average_marks = $total_students > 0 ? $total_obtained / $total_students : 0;
$pass_percentage = $total_students > 0 ? ($total_passed / $total_students) * 100 : 0;

// Generate filename
$filename = "Results_" . preg_replace('/[^A-Za-z0-9]/', '_', $exam_details['name']) . "_" . date('Y-m-d');

// Export to Excel
if($format == 'excel') {
    // Require PhpSpreadsheet library
    require '../vendor/autoload.php';
    
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Style\Alignment;
    use PhpOffice\PhpSpreadsheet\Style\Border;
    use PhpOffice\PhpSpreadsheet\Style\Fill;
    
    // Create a new spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator("Sindh Singhar Portal")
        ->setLastModifiedBy("Sindh Singhar Portal")
        ->setTitle("Exam Results")
        ->setSubject("Exam Results")
        ->setDescription("Exam Results for " . $exam_details['name']);
    
    // Add header
    $sheet->setCellValue('A1', 'SINDH SINGHAR PORTAL - EXAM RESULTS');
    $sheet->mergeCells('A1:H1');
    
    // Add exam details
    $sheet->setCellValue('A3', 'Exam:');
    $sheet->setCellValue('B3', $exam_details['name']);
    $sheet->setCellValue('A4', 'Class:');
    $sheet->setCellValue('B4', $exam_details['class_name'] . ' ' . $exam_details['section']);
    $sheet->setCellValue('A5', 'School:');
    $sheet->setCellValue('B5', $exam_details['school_name']);
    $sheet->setCellValue('D3', 'Date:');
    $sheet->setCellValue('E3', formatDate($exam_details['exam_date']));
    $sheet->setCellValue('D4', 'Total Marks:');
    $sheet->setCellValue('E4', $exam_details['total_marks']);
    
    // Add statistics
    $sheet->setCellValue('A7', 'STATISTICS');
    $sheet->mergeCells('A7:H7');
    
    $sheet->setCellValue('A8', 'Total Students:');
    $sheet->setCellValue('B8', $total_students);
    $sheet->setCellValue('C8', 'Passed:');
    $sheet->setCellValue('D8', $total_passed);
    $sheet->setCellValue('E8', 'Failed:');
    $sheet->setCellValue('F8', $total_failed);
    $sheet->setCellValue('G8', 'Pass %:');
    $sheet->setCellValue('H8', number_format($pass_percentage, 2) . '%');
    
    $sheet->setCellValue('A9', 'Highest Marks:');
    $sheet->setCellValue('B9', $highest_marks);
    $sheet->setCellValue('C9', 'Lowest Marks:');
    $sheet->setCellValue('D9', $lowest_marks);
    $sheet->setCellValue('E9', 'Average Marks:');
    $sheet->setCellValue('F9', number_format($average_marks, 2));
    
    // Add results table headers
    $sheet->setCellValue('A11', 'S.No');
    $sheet->setCellValue('B11', 'Roll Number');
    $sheet->setCellValue('C11', 'Student Name');
    $sheet->setCellValue('D11', 'Father\'s Name');
    $sheet->setCellValue('E11', 'Obtained Marks');
    $sheet->setCellValue('F11', 'Total Marks');
    $sheet->setCellValue('G11', 'Percentage');
    $sheet->setCellValue('H11', 'Grade');
    
    // Add results data
    $row = 12;
    $counter = 1;
    
    foreach($results as $result) {
        $percentage = ($result['obtained_marks'] / $exam_details['total_marks']) * 100;
        $grade = '';
        
        if($percentage >= 90) {
            $grade = 'A+';
        } elseif($percentage >= 80) {
            $grade = 'A';
        } elseif($percentage >= 70) {
            $grade = 'B';
        } elseif($percentage >= 60) {
            $grade = 'C';
        } elseif($percentage >= 50) {
            $grade = 'D';
        } elseif($percentage >= 33) {
            $grade = 'E';
        } else {
            $grade = 'F';
        }
        
        $sheet->setCellValue('A' . $row, $counter);
        $sheet->setCellValue('B' . $row, $result['roll_number']);
        $sheet->setCellValue('C' . $row, $result['student_name']);
        $sheet->setCellValue('D' . $row, $result['father_name']);
        $sheet->setCellValue('E' . $row, $result['obtained_marks']);
        $sheet->setCellValue('F' . $row, $exam_details['total_marks']);
        $sheet->setCellValue('G' . $row, number_format($percentage, 2) . '%');
        $sheet->setCellValue('H' . $row, $grade);
        
        $row++;
        $counter++;
    }
    
    // Style the spreadsheet
    $styleArray = [
        'font' => [
            'bold' => true,
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
        ],
        'borders' => [
            'bottom' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => [
                'argb' => 'FFD9EAD3',
            ],
        ],
    ];
    
    $sheet->getStyle('A1:H1')->applyFromArray($styleArray);
    $sheet->getStyle('A7:H7')->applyFromArray($styleArray);
    $sheet->getStyle('A11:H11')->applyFromArray($styleArray);
    
    // Auto size columns
    foreach(range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    // Save Excel file
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
// Export to PDF
else if($format == 'pdf') {
    // Require TCPDF library
    require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Sindh Singhar Portal');
    $pdf->SetAuthor('Sindh Singhar Portal');
    $pdf->SetTitle('Exam Results');
    $pdf->SetSubject('Exam Results for ' . $exam_details['name']);
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'Sindh Singhar Portal', 'Exam Results', array(0,64,255), array(0,64,128));
    $pdf->setFooterData(array(0,64,0), array(0,64,128));
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array('helvetica', '', 10));
    $pdf->setFooterFont(Array('helvetica', '', 8));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont('courier');
    
    // Set margins
    $pdf->SetMargins(15, 27, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 25);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Title
    $pdf->Cell(0, 10, 'EXAM RESULTS', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    
    // Exam details
    $pdf->Cell(40, 7, 'Exam:', 0, 0);
    $pdf->Cell(60, 7, $exam_details['name'], 0, 0);
    $pdf->Cell(40, 7, 'Date:', 0, 0);
    $pdf->Cell(50, 7, formatDate($exam_details['exam_date']), 0, 1);
    
    $pdf->Cell(40, 7, 'Class:', 0, 0);
    $pdf->Cell(60, 7, $exam_details['class_name'] . ' ' . $exam_details['section'], 0, 0);
    $pdf->Cell(40, 7, 'Total Marks:', 0, 0);
    $pdf->Cell(50, 7, $exam_details['total_marks'], 0, 1);
    
    $pdf->Cell(40, 7, 'School:', 0, 0);
    $pdf->Cell(150, 7, $exam_details['school_name'], 0, 1);
    
    $pdf->Ln(5);
    
    // Statistics
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'STATISTICS', 0, 1);
    $pdf->SetFont('helvetica', '', 12);
    
    $pdf->Cell(40, 7, 'Total Students:', 0, 0);
    $pdf->Cell(20, 7, $total_students, 0, 0);
    $pdf->Cell(30, 7, 'Passed:', 0, 0);
    $pdf->Cell(20, 7, $total_passed, 0, 0);
    $pdf->Cell(30, 7, 'Failed:', 0, 0);
    $pdf->Cell(20, 7, $total_failed, 0, 0);
    $pdf->Cell(30, 7, 'Pass %:', 0, 0);
    $pdf->Cell(20, 7, number_format($pass_percentage, 2) . '%', 0, 1);
    
    $pdf->Cell(40, 7, 'Highest Marks:', 0, 0);
    $pdf->Cell(20, 7, $highest_marks, 0, 0);
    $pdf->Cell(30, 7, 'Lowest Marks:', 0, 0);
    $pdf->Cell(20, 7, $lowest_marks, 0, 0);
    $pdf->Cell(30, 7, 'Average Marks:', 0, 0);
    $pdf->Cell(20, 7, number_format($average_marks, 2), 0, 1);
    
    $pdf->Ln(5);
    
    // Results table
    $pdf->SetFont('helvetica', 'B', 12);
    
    // Table header
    $pdf->SetFillColor(217, 234, 211);
    $pdf->Cell(10, 7, 'S.No', 1, 0, 'C', 1);
    $pdf->Cell(20, 7, 'Roll No', 1, 0, 'C', 1);
    $pdf->Cell(40, 7, 'Student Name', 1, 0, 'C', 1);
    $pdf->Cell(40, 7, 'Father\'s Name', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Obtained', 1, 0, 'C', 1);
    $pdf->Cell(20, 7, 'Total', 1, 0, 'C', 1);
    $pdf->Cell(20, 7, 'Percent', 1, 0, 'C', 1);
    $pdf->Cell(15, 7, 'Grade', 1, 1, 'C', 1);
    
    // Table data
    $pdf->SetFont('helvetica', '', 10);
    $counter = 1;
    
    foreach($results as $result) {
        $percentage = ($result['obtained_marks'] / $exam_details['total_marks']) * 100;
        $grade = '';
        
        if($percentage >= 90) {
            $grade = 'A+';
        } elseif($percentage >= 80) {
            $grade = 'A';
        } elseif($percentage >= 70) {
            $grade = 'B';
        } elseif($percentage >= 60) {
            $grade = 'C';
        } elseif($percentage >= 50) {
            $grade = 'D';
        } elseif($percentage >= 33) {
            $grade = 'E';
        } else {
            $grade = 'F';
        }
        
        $pdf->Cell(10, 7, $counter, 1, 0, 'C');
        $pdf->Cell(20, 7, $result['roll_number'], 1, 0, 'C');
        $pdf->Cell(40, 7, $result['student_name'], 1, 0, 'L');
        $pdf->Cell(40, 7, $result['father_name'], 1, 0, 'L');
        $pdf->Cell(25, 7, $result['obtained_marks'], 1, 0, 'C');
        $pdf->Cell(20, 7, $exam_details['total_marks'], 1, 0, 'C');
        $pdf->Cell(20, 7, number_format($percentage, 2) . '%', 1, 0, 'C');
        $pdf->Cell(15, 7, $grade, 1, 1, 'C');
        
        $counter++;
        
        // Add a new page if needed
        if($counter % 25 == 0 && $counter < count($results)) {
            $pdf->AddPage();
            
            // Table header on new page
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetFillColor(217, 234, 211);
            $pdf->Cell(10, 7, 'S.No', 1, 0, 'C', 1);
            $pdf->Cell(20, 7, 'Roll No', 1, 0, 'C', 1);
            $pdf->Cell(40, 7, 'Student Name', 1, 0, 'C', 1);
            $pdf->Cell(40, 7, 'Father\'s Name', 1, 0, 'C', 1);
            $pdf->Cell(25, 7, 'Obtained', 1, 0, 'C', 1);
            $pdf->Cell(20, 7, 'Total', 1, 0, 'C', 1);
            $pdf->Cell(20, 7, 'Percent', 1, 0, 'C', 1);
            $pdf->Cell(15, 7, 'Grade', 1, 1, 'C', 1);
            
            $pdf->SetFont('helvetica', '', 10);
        }
    }
    
    // Output PDF
    $pdf->Output($filename . '.pdf', 'D');
    exit;
}
?>