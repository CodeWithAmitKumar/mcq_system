<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require 'vendor/autoload.php'; // Make sure you have PhpSpreadsheet installed

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
$quiz = getQuizById($quiz_id);

if (!$quiz) {
    header("Location: manage_quizzes.php");
    exit;
}

try {
    $conn = getDbConnection();
    
    // Get results with usernames
    $stmt = $conn->prepare("
        SELECT r.*, u.username 
        FROM quiz_results r
        JOIN users u ON r.user_id = u.id
        WHERE r.quiz_id = ?
        ORDER BY r.completion_time DESC
    ");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $results = $result->fetch_all(MYSQLI_ASSOC);
    
    // Create new spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set headers
    $sheet->setCellValue('A1', 'Student');
    $sheet->setCellValue('B1', 'Score');
    $sheet->setCellValue('C1', 'Time Taken');
    $sheet->setCellValue('D1', 'Completion Date');
    
    // Add data
    $row = 2;
    foreach ($results as $result) {
        $sheet->setCellValue('A' . $row, $result['username']);
        $sheet->setCellValue('B' . $row, $result['score']);
        $sheet->setCellValue('C' . $row, formatTime($result['time_taken']));
        $sheet->setCellValue('D' . $row, date('Y-m-d H:i:s', strtotime($result['completion_time'])));
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', 'D') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Set filename
    $filename = 'quiz_results_' . $quiz_id . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Save file
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error exporting results: " . $e->getMessage();
    header("Location: view_results.php?quiz_id=" . $quiz_id);
}

exit;