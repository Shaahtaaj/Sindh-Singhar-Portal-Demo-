<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if(!isset($_SESSION["user_id"])){
    header("location: ../login.php");
    exit;
}

// Include functions
require_once __DIR__ . "/../includes/functions.php";

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sindh Seenghar School Management Portal</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
            padding-top: 20px;
            position: fixed;
            width: 250px;
        }
        .sidebar .nav-link {
            color: #ced4da;
            padding: 10px 20px;
            margin: 5px 0;
        }
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: #495057;
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: #28a745;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
        }
        .navbar {
            background-color: #28a745;
        }
        .navbar-brand, .navbar-text {
            color: white !important;
        }
        .page-header {
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        .btn-primary {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-primary:hover {
            background-color: #218838;
            border-color: #218838;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <a class="navbar-brand" href="#">Sindh Seenghar Portal</a>
        <div class="collapse navbar-collapse" id="navbarText">
            <ul class="navbar-nav mr-auto">
            </ul>
            <span class="navbar-text">
                Welcome, <?php echo htmlspecialchars($_SESSION["name"]); ?> | 
                <a href="../logout.php" class="text-white"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </span>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <?php if(isAdmin()): ?>
                    <!-- Admin Menu -->
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'students.php') ? 'active' : ''; ?>" href="students.php">
                                <i class="fas fa-user-graduate"></i> Students
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'teachers.php') ? 'active' : ''; ?>" href="teachers.php">
                                <i class="fas fa-chalkboard-teacher"></i> Teachers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'classes.php') ? 'active' : ''; ?>" href="classes.php">
                                <i class="fas fa-school"></i> Classes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'attendance.php') ? 'active' : ''; ?>" href="attendance.php">
                                <i class="fas fa-calendar-check"></i> Attendance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'results.php') ? 'active' : ''; ?>" href="results.php">
                                <i class="fas fa-chart-bar"></i> Results
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                                <i class="fas fa-file-alt"></i> Reports
                            </a>
                        </li>
                    </ul>
                <?php else: ?>
                    <!-- Teacher Menu -->
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'attendance.php') ? 'active' : ''; ?>" href="attendance.php">
                                <i class="fas fa-calendar-check"></i> Attendance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'results.php') ? 'active' : ''; ?>" href="results.php">
                                <i class="fas fa-chart-bar"></i> Results
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                                <i class="fas fa-file-alt"></i> Reports
                            </a>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 content">
                <?php echo displayMessage(); ?>