<?php
// Include database connection
require_once "../config/database.php";
require_once "../includes/functions.php";

// Check if user is admin
session_start();
if(!isAdmin()){
    redirectWithMessage("../login.php", "You are not authorized to access this page.", "danger");
}

// Create directory for exports if it doesn't exist
$export_dir = "../exports";
if (!file_exists($export_dir)) {
    mkdir($export_dir, 0777, true);
}

// Get filter parameters
$filter_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$school_id = isset($_SESSION["school_id"]) ? $_SESSION["school_id"] : 0;
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';

// Build query based on filters
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

$students = [];
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
}

// Get class and school info for filename
$class_name = "All_Classes";
$school_name = "All_Schools";

if($filter_class > 0) {
    $class_sql = "SELECT c.name, c.section, s.name as school_name FROM classes c JOIN schools s ON c.school_id = s.id WHERE c.id = $filter_class";
    $class_result = mysqli_query($conn, $class_sql);
    if($class_result && mysqli_num_rows($class_result) > 0) {
        $class_info = mysqli_fetch_assoc($class_result);
        $class_name = $class_info['name'] . "_" . $class_info['section'];
        $school_name = $class_info['school_name'];
    }
} else if($school_id > 0) {
    $school_sql = "SELECT name FROM schools WHERE id = $school_id";
    $school_result = mysqli_query($conn, $school_sql);
    if($school_result && mysqli_num_rows($school_result) > 0) {
        $school_info = mysqli_fetch_assoc($school_result);
        $school_name = $school_info['name'];
    }
}

// Format filename
$filename = "Students_" . $school_name . "_" . $class_name . "_" . date('Y-m-d');
$filename = str_replace(' ', '_', $filename);

// Export based on format
if($format == 'pdf') {
    // PDF Export
    require_once '../vendor/autoload.php'; // Make sure TCPDF is installed
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Sindh Seenghar School Management Portal');
    $pdf->SetAuthor('Admin');
    $pdf->SetTitle('Students List');
    $pdf->SetSubject('Students List');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'Sindh Seenghar School Management Portal', 'Students List - ' . str_replace('_', ' ', $school_name) . ' - ' . str_replace('_', ' ', $class_name));
    
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
    $pdf->SetFont('helvetica', '', 10);
    
    // Table header
    $html = '<table border="1" cellpadding="5">
                <thead>
                    <tr style="background-color: #28A745; color: white;">
                        <th width="5%">ID</th>
                        <th width="20%">Name</th>
                        <th width="20%">Father\'s Name</th>
                        <th width="10%">Gender</th>
                        <th width="10%">DOB</th>
                        <th width="15%">Class</th>
                        <th width="20%">School</th>
                    </tr>
                </thead>
                <tbody>';
    
    // Table data
    foreach($students as $student) {
        $html .= '<tr>
                    <td>' . $student['id'] . '</td>
                    <td>' . htmlspecialchars($student['name']) . '</td>
                    <td>' . htmlspecialchars($student['father_name']) . '</td>
                    <td>' . htmlspecialchars(ucfirst($student['gender'])) . '</td>
                    <td>' . (!empty($student['dob']) ? formatDate($student['dob']) : '') . '</td>
                    <td>' . htmlspecialchars($student['class_name'] . ' ' . $student['section']) . '</td>
                    <td>' . htmlspecialchars($student['school_name']) . '</td>
                  </tr>';
    }
    
    $html .= '</tbody></table>';
    
    // Print table
    $pdf->writeHTML($html, true, false, false, false, '');
    
    // Close and output PDF
    $pdf->Output($filename . '.pdf', 'D');
    
} else {
    // Excel Export
    require_once '../vendor/autoload.php'; // Make sure PhpSpreadsheet is installed
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set headers
    $sheet->setCellValue('A1', 'ID');
    $sheet->setCellValue('B1', 'Student Name');
    $sheet->setCellValue('C1', 'Father Name');
    $sheet->setCellValue('D1', 'Gender');
    $sheet->setCellValue('E1', 'DOB');
    $sheet->setCellValue('F1', 'Class');
    $sheet->setCellValue('G1', 'School');
    $sheet->setCellValue('H1', 'Admission Date');
    
    // Style headers
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
    
    $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
    
    // Add data
    $row = 2;
    foreach($students as $student) {
        $sheet->setCellValue('A' . $row, $student['id']);
        $sheet->setCellValue('B' . $row, $student['name']);
        $sheet->setCellValue('C' . $row, $student['father_name']);
        $sheet->setCellValue('D' . $row, ucfirst($student['gender']));
        $sheet->setCellValue('E' . $row, !empty($student['dob']) ? $student['dob'] : '');
        $sheet->setCellValue('F' . $row, $student['class_name'] . ' ' . $student['section']);
        $sheet->setCellValue('G' . $row, $student['school_name']);
        $sheet->setCellValue('H' . $row, $student['admission_date']);
        $row++;
    }
    
    // Auto size columns
    foreach(range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Create Excel file
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
}

exit;
?>