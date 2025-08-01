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

// Get grades data
$grades = $auth->getStudentGrades($studentId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .grade-card {
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: transform 0.2s;
            margin-bottom: 1.5rem;
        }
        .grade-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .grade-value {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .grade-A { color: #198754; }
        .grade-B { color: #0d6efd; }
        .grade-C { color: #ffc107; }
        .grade-D { color: #fd7e14; }
        .grade-F { color: #dc3545; }
        .semester-header {
            background-color: #f8f9fa;
            border-left: 4px solid #0d6efd;
            padding: 0.5rem 1rem;
            margin: 1.5rem 0 1rem;
            font-weight: 600;
        }
        .subject-card {
            border-left: 4px solid #0d6efd;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar min-vh-100">
                <div class="text-center my-4">
                    <img src="<?php echo !empty($student['profile_pic']) ? htmlspecialchars($student['profile_pic']) : 'https://ui-avatars.com/api/?name=' . urlencode($student['first_name'] . ' ' . $student['last_name']) . '&background=random' ?>" 
                         class="rounded-circle" width="80" height="80" alt="Profile">
                    <h6 class="my-2"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                </div>
                
                <ul class="nav flex-column px-2">
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/dashboard.php">
                            <i class="bi bi-speedometer2 me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link active" href="<?php echo SITE_URL; ?>/student/grades.php">
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
                    <li class="nav-item mt-4">
                        <a class="nav-link text-danger" href="<?php echo SITE_URL; ?>/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">My Grades</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="bi bi-printer me-1"></i> Print
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (empty($grades)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i> No grades found for your account.
                    </div>
                <?php else: ?>
                    <?php foreach ($grades as $subjectCode => $subject): ?>
                        <div class="card mb-4" id="<?php echo strtolower($subjectCode); ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?php echo htmlspecialchars($subject['name']); ?></h5>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($subjectCode); ?></span>
                            </div>
                            <div class="card-body">
                                <?php 
                                // Get current semester
                                $currentYear = date('Y');
                                $currentMonth = date('n');
                                $currentTerm = ($currentMonth >= 8 || $currentMonth <= 1) ? '1st Semester' : '2nd Semester';
                                $currentAcademicYear = ($currentMonth >= 8) ? $currentYear . '-' . ($currentYear + 1) : ($currentYear - 1) . '-' . $currentYear;
                                $currentSemester = $currentTerm . ' ' . $currentAcademicYear;
                                
                                // Display current semester first if it exists
                                if (isset($subject['semesters'][$currentSemester])): 
                                    $semesterGrades = $subject['semesters'][$currentSemester];
                                    unset($subject['semesters'][$currentSemester]);
                                    $subject['semesters'] = [$currentSemester => $semesterGrades] + $subject['semesters'];
                                endif;
                                ?>
                                
                                <?php foreach ($subject['semesters'] as $semester => $semesterGrades): ?>
                                    <div class="mb-4">
                                        <h6 class="semester-header">
                                            <?php echo htmlspecialchars($semester); ?>
                                            <?php if ($semester === $currentSemester): ?>
                                                <span class="badge bg-success">Current</span>
                                            <?php endif; ?>
                                        </h6>
                                        
                                        <?php if (empty($semesterGrades)): ?>
                                            <div class="alert alert-warning mb-0">
                                                <i class="bi bi-exclamation-triangle me-2"></i> No grades recorded for this semester.
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover align-middle">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Assessment</th>
                                                            <th>Grade</th>
                                                            <th>Date</th>
                                                            <th>Comments</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($semesterGrades as $grade): 
                                                            $gradeClass = 'grade-' . strtoupper(substr($grade['grade'], 0, 1));
                                                        ?>
                                                            <tr>
                                                                <td>Final Grade</td>
                                                                <td class="grade-value <?php echo $gradeClass; ?>">
                                                                    <?php echo htmlspecialchars($grade['grade']); ?>
                                                                </td>
                                                                <td><?php echo date('M d, Y', strtotime($grade['created_at'])); ?></td>
                                                                <td><?php echo !empty($grade['comments']) ? htmlspecialchars($grade['comments']) : 'No comments'; ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth' });
                    // Add highlight class
                    targetElement.classList.add('highlight');
                    // Remove highlight after animation
                    setTimeout(() => {
                        targetElement.classList.remove('highlight');
                    }, 2000);
                }
            });
        });

        // Highlight the current subject when navigating from dashboard
        if (window.location.hash) {
            const targetElement = document.querySelector(window.location.hash);
            if (targetElement) {
                targetElement.classList.add('highlight');
                setTimeout(() => {
                    targetElement.scrollIntoView({ behavior: 'smooth' });
                }, 100);
                setTimeout(() => {
                    targetElement.classList.remove('highlight');
                }, 2000);
            }
        }
    </script>
    <style>
        .highlight {
            animation: highlight 2s ease-out;
        }
        @keyframes highlight {
            0% { background-color: rgba(13, 110, 253, 0.1); }
            100% { background-color: transparent; }
        }
    </style>
</body>
</html>
