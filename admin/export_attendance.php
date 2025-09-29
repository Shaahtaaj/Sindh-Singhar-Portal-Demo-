<?php
// Include database connection
require_once "../config/database.php";
require_once "../includes/functions.php";
require_once '../vendor/autoload.php'; // Add autoloader for PhpSpreadsheet

// Import PhpSpreadsheet classes
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\IOFactory;

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
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';

// Validate input
if(empty($filter_class) || empty($filter_date)) {
    redirectWithMessage("attendance.php", "Class and date are required for export.", "danger");
}

// Get class details
$class_sql = "SELECT c.name, c.section, s.name as school_name 
              FROM classes c 
              JOIN schools s ON c.school_id = s.id 
              WHERE c.id = ?";
$stmt = mysqli_prepare($conn, $class_sql);
mysqli_stmt_bind_param($stmt, "i", $filter_class);
mysqli_stmt_execute($stmt);
$class_result = mysqli_stmt_get_result($stmt);

if(!$class_result || mysqli_num_rows($class_result) == 0) {
    redirectWithMessage("attendance.php", "Class not found.", "danger");
}

$class_info = mysqli_fetch_assoc($class_result);
$class_name = $class_info['name'] . " " . $class_info['section'];
$school_name = $class_info['school_name'];

// Check if attendance exists for this class and date
$sql = "SELECT a.*, u.name as teacher_name 
        FROM attendance a 
        JOIN users u ON a.teacher_id = u.id 
        WHERE a.class_id = ? AND a.date = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "is", $filter_class, $filter_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if(!$result || mysqli_num_rows($result) == 0) {
    redirectWithMessage("attendance.php?class_id=$filter_class&date=$filter_date", "No attendance record found for this class on this date.", "warning");
}

$attendance = mysqli_fetch_assoc($result);
$teacher_name = $attendance['teacher_name'];

// Get attendance details
$sql = "SELECT ad.*, s.name as student_name, s.father_name 
        FROM attendance_details ad 
        JOIN students s ON ad.student_id = s.id 
        WHERE ad.attendance_id = ? 
        ORDER BY s.name";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $attendance['id']);
mysqli_stmt_execute($stmt);
$details_result = mysqli_stmt_get_result($stmt);

$attendance_records = [];
if($details_result) {
    while($row = mysqli_fetch_assoc($details_result)) {
        $attendance_records[] = $row;
    }
}

// Format filename
$filename = "Attendance_" . str_replace(' ', '_', $school_name) . "_" . str_replace(' ', '_', $class_name) . "_" . $filter_date;

// Export based on format
if($format == 'pdf') {
    // PDF Export
    require_once '../vendor/autoload.php'; // Make sure TCPDF is installed
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Sindh Seenghar School Management Portal');
    $pdf->SetAuthor('Admin');
    $pdf->SetTitle('Attendance Report');
    $pdf->SetSubject('Attendance Report');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'Sindh Seenghar School Management Portal', 'Attendance Report');
    
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
    
    // Report header
    $html = '<h2>Attendance Report</h2>
             <p><strong>School:</strong> ' . htmlspecialchars($school_name) . '</p>
             <p><strong>Class:</strong> ' . htmlspecialchars($class_name) . '</p>
             <p><strong>Date:</strong> ' . formatDate($filter_date) . '</p>
             <p><strong>Teacher:</strong> ' . htmlspecialchars($teacher_name) . '</p><br>';
    
    // Table header
    $html .= '<table border="1" cellpadding="5">
                <thead>
                    <tr style="background-color: #28A745; color: white;">
                        <th width="5%">No.</th>
                        <th width="30%">Student Name</th>
                        <th width="30%">Father\'s Name</th>
                        <th width="15%">Status</th>
                        <th width="20%">Remarks</th>
                    </tr>
                </thead>
                <tbody>';
    
    // Table data
    $i = 1;
    foreach($attendance_records as $record) {
        $status = ucfirst($record['status']);
        $status_color = '';
        
        if($record['status'] == 'present') {
            $status_color = 'color: green;';
        } elseif($record['status'] == 'absent') {
            $status_color = 'color: red;';
        } elseif($record['status'] == 'leave') {
            $status_color = 'color: orange;';
        }
        
        $html .= '<tr>
                    <td>' . $i . '</td>
                    <td>' . htmlspecialchars($record['student_name']) . '</td>
                    <td>' . htmlspecialchars($record['father_name']) . '</td>
                    <td style="' . $status_color . '">' . $status . '</td>
                    <td>' . htmlspecialchars($record['remarks']) . '</td>
                  </tr>';
        $i++;
    }
    
    $html .= '</tbody></table>';
    
    // Summary
    $present_count = 0;
    $absent_count = 0;
    $leave_count = 0;
    
    foreach($attendance_records as $record) {
        if($record['status'] == 'present') {
            $present_count++;
        } elseif($record['status'] == 'absent') {
            $absent_count++;
        } elseif($record['status'] == 'leave') {
            $leave_count++;
        }
    }
    
    $total_count = count($attendance_records);
    $present_percentage = ($total_count > 0) ? round(($present_count / $total_count) * 100, 2) : 0;
    
    $html .= '<br><h3>Summary</h3>
              <p>Total Students: ' . $total_count . '</p>
              <p>Present: ' . $present_count . ' (' . $present_percentage . '%)</p>
              <p>Absent: ' . $absent_count . '</p>
              <p>Leave: ' . $leave_count . '</p>';
    
    // Print content
    $pdf->writeHTML($html, true, false, false, false, '');
    
    // Close and output PDF
    $pdf->Output($filename . '.pdf', 'D');
    
} else {
    // Excel Export
    require_once '../vendor/autoload.php'; // Make sure PhpSpreadsheet is installed
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set title
    $sheet->setCellValue('A1', 'Attendance Report');
    $sheet->mergeCells('A1:E1');
    
    // Set report info
    $sheet->setCellValue('A2', 'School:');
    $sheet->setCellValue('B2', $school_name);
    $sheet->mergeCells('B2:E2');
    
    $sheet->setCellValue('A3', 'Class:');
    $sheet->setCellValue('B3', $class_name);
    $sheet->mergeCells('B3:E3');
    
    $sheet->setCellValue('A4', 'Date:');
    $sheet->setCellValue('B4', formatDate($filter_date));
    $sheet->mergeCells('B4:E4');
    
    $sheet->setCellValue('A5', 'Teacher:');
    $sheet->setCellValue('B5', $teacher_name);
    $sheet->mergeCells('B5:E5');
    
    // Style title and info
    $titleStyle = [
        'font' => [
            'bold' => true,
            'size' => 14,
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        ],
    ];
    
    $infoStyle = [
        'font' => [
            'bold' => true,
        ],
    ];
    
    $sheet->getStyle('A1:E1')->applyFromArray($titleStyle);
    $sheet->getStyle('A2:A5')->applyFromArray($infoStyle);
    
    // Set headers
    $sheet->setCellValue('A7', 'No.');
    $sheet->setCellValue('B7', 'Student Name');
    $sheet->setCellValue('C7', 'Father\'s Name');
    $sheet->setCellValue('D7', 'Status');
    $sheet->setCellValue('E7', 'Remarks');
    
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
    
    $sheet->getStyle('A7:E7')->applyFromArray($headerStyle);
    
    // Add data
    $row = 8;
    $i = 1;
    foreach($attendance_records as $record) {
        $sheet->setCellValue('A' . $row, $i);
        $sheet->setCellValue('B' . $row, $record['student_name']);
        $sheet->setCellValue('C' . $row, $record['father_name']);
        $sheet->setCellValue('D' . $row, ucfirst($record['status']));
        $sheet->setCellValue('E' . $row, $record['remarks']);
        
        // Style status cell
        if($record['status'] == 'present') {
            $sheet->getStyle('D' . $row)->getFont()->getColor()->setRGB('008800');
        } elseif($record['status'] == 'absent') {
            $sheet->getStyle('D' . $row)->getFont()->getColor()->setRGB('FF0000');
        } elseif($record['status'] == 'leave') {
            $sheet->getStyle('D' . $row)->getFont()->getColor()->setRGB('FFA500');
        }
        
        $row++;
        $i++;
    }
    
    // Add summary
    $present_count = 0;
    $absent_count = 0;
    $leave_count = 0;
    
    foreach($attendance_records as $record) {
        if($record['status'] == 'present') {
            $present_count++;
        } elseif($record['status'] == 'absent') {
            $absent_count++;
        } elseif($record['status'] == 'leave') {
            $leave_count++;
        }
    }
    
    $total_count = count($attendance_records);
    $present_percentage = ($total_count > 0) ? round(($present_count / $total_count) * 100, 2) : 0;
    
    $summary_row = $row + 2;
    
    $sheet->setCellValue('A' . $summary_row, 'Summary');
    $sheet->mergeCells('A' . $summary_row . ':E' . $summary_row);
    $sheet->getStyle('A' . $summary_row)->getFont()->setBold(true);
    $sheet->getStyle('A' . $summary_row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A' . ($summary_row + 1), 'Total Students:');
    $sheet->setCellValue('B' . ($summary_row + 1), $total_count);
    
    $sheet->setCellValue('A' . ($summary_row + 2), 'Present:');
    $sheet->setCellValue('B' . ($summary_row + 2), $present_count . ' (' . $present_percentage . '%)');
    
    $sheet->setCellValue('A' . ($summary_row + 3), 'Absent:');
    $sheet->setCellValue('B' . ($summary_row + 3), $absent_count);
    
    $sheet->setCellValue('A' . ($summary_row + 4), 'Leave:');
    $sheet->setCellValue('B' . ($summary_row + 4), $leave_count);
    
    // Auto size columns
    foreach(range('A', 'E') as $col) {
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