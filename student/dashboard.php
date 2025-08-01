<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session and initialize auth
if (session_status() === PHP_SESSION_NONE) session_start();
$db = getDBConnection();
$auth = new Auth($db);

// Check login and role
if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'student') {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

// Get student data
$studentId = $_SESSION['user_id'];
$student = $auth->getUserById($studentId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar min-vh-100">
                <div class="text-center my-4">
                    <img src="<?php echo !empty($student['profile_pic']) ? htmlspecialchars($student['profile_pic']) : 'https://via.placeholder.com/100'; ?>" 
                         class="rounded-circle" width="80" height="80" alt="Profile">
                    <h6 class="my-2"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                </div>
                
                <ul class="nav flex-column px-2">
                    <li class="nav-item mb-2">
                        <a class="nav-link active" href="<?php echo SITE_URL; ?>/student/dashboard.php">
                            <i class="bi bi-speedometer2 me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/grades.php">
                            <i class="bi bi-journal-text me-2"></i> My Grades
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/schedule.php">
                            <i class="bi bi-calendar-week me-2"></i> Class Schedule
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/documents.php">
                            <i class="bi bi-file-earmark-text me-2"></i> Document Requests
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/chatbot.php">
                            <i class="bi bi-robot me-2"></i> AI Assistant
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link text-danger" href="<?php echo SITE_URL; ?>/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h2 class="h3 mb-4">Welcome back, <?php echo htmlspecialchars($student['first_name']); ?>!</h2>
                
                <?php
                // Include the grades calculation function from grades.php
                function calculateGPA($grades) {
                    if (empty($grades)) return 0.0;
                    
                    $totalGradePoints = 0;
                    $totalUnits = 0;
                    
                    foreach ($grades as $grade) {
                        $gradeValue = is_array($grade) ? $grade['grade'] : $grade->grade;
                        $units = is_array($grade) ? $grade['units'] : $grade->units;
                        
                        // Convert percentage grade to 4.0 scale
                        if ($gradeValue >= 90) $gpaPoints = 4.0;
                        elseif ($gradeValue >= 85) $gpaPoints = 3.7;
                        elseif ($gradeValue >= 80) $gpaPoints = 3.3;
                        elseif ($gradeValue >= 75) $gpaPoints = 3.0;
                        elseif ($gradeValue >= 70) $gpaPoints = 2.7;
                        elseif ($gradeValue >= 65) $gpaPoints = 2.3;
                        elseif ($gradeValue >= 60) $gpaPoints = 2.0;
                        elseif ($gradeValue >= 55) $gpaPoints = 1.7;
                        elseif ($gradeValue >= 50) $gpaPoints = 1.0;
                        else $gpaPoints = 0.0;
                        
                        $totalGradePoints += $gpaPoints * $units;
                        $totalUnits += $units;
                    }
                    
                    return $totalUnits > 0 ? round($totalGradePoints / $totalUnits, 2) : 0.0;
                }
                
                // Sample grades data (in a real app, this would come from the database)
                $grades = [
                    ['subject_code' => 'MATH101', 'subject_name' => 'Mathematics 101', 'units' => 3, 'grade' => 92.5],
                    ['subject_code' => 'CS201', 'subject_name' => 'Computer Science 201', 'units' => 3, 'grade' => 88.0],
                    ['subject_code' => 'PHYS101', 'subject_name' => 'Physics 101', 'units' => 4, 'grade' => 95.0]
                ];
                
                $gpa = calculateGPA($grades);
                ?>
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h6 class="card-title">Current GPA</h6>
                                <h3 class="mb-0"><?php echo $gpa; ?></h3>
                                <small>Out of 4.0</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h6 class="card-title">Enrolled Units</h6>
                                <h3 class="mb-0">18</h3>
                                <small>This Semester</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h6 class="card-title">Pending Requests</h6>
                                <h3 class="mb-0">2</h3>
                                <small>Documents</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h6 class="card-title">Class Today</h6>
                                <h3 class="mb-0">3</h3>
                                <small>Classes remaining</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Schedule -->
                    <div class="col-md-8 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Today's Schedule</h5>
                                <a href="schedule.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Subject</th>
                                                <th>Room</th>
                                                <th>Instructor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>08:00 AM - 09:30 AM</td>
                                                <td>Mathematics 101</td>
                                                <td>Room 302</td>
                                                <td>Dr. Smith</td>
                                            </tr>
                                            <tr>
                                                <td>10:00 AM - 11:30 AM</td>
                                                <td>Computer Science 201</td>
                                                <td>Lab 105</td>
                                                <td>Prof. Johnson</td>
                                            </tr>
                                            <tr>
                                                <td>01:00 PM - 02:30 PM</td>
                                                <td>Physics 101</td>
                                                <td>Room 201</td>
                                                <td>Dr. Williams</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Deadlines -->
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Upcoming Deadlines</h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Math Assignment 3</h6>
                                            <small class="text-muted">Due: Aug 5, 2023</small>
                                        </div>
                                        <span class="badge bg-warning rounded-pill">Math 101</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">CS Project Proposal</h6>
                                            <small class="text-muted">Due: Aug 7, 2023</small>
                                        </div>
                                        <span class="badge bg-info rounded-pill">CS 201</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Physics Lab Report</h6>
                                            <small class="text-muted">Due: Aug 10, 2023</small>
                                        </div>
                                        <span class="badge bg-success rounded-pill">Physics 101</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Grades -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Grades</h5>
                                <a href="grades.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Grade</th>
                                                <th>Semester</th>
                                                <th>Academic Year</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($grades as $grade): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                                <td><?php echo $grade['grade']; ?></td>
                                                <td>1st Semester</td>
                                                <td>2023-2024</td>
                                                <td>
                                                    <span class="badge bg-success">Passed</span>
                                                    <a href="grades.php#<?php echo strtolower($grade['subject_code']); ?>" class="btn btn-sm btn-outline-primary ms-2">View Details</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
