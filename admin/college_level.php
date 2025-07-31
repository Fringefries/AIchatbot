<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize Auth
$db = getDBConnection();
$auth = new Auth($db);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

require_once __DIR__ . '/includes/header.php';

// Initialize variables
$students = [];
$groupedStudents = [
    '1st Year' => [],
    '2nd Year' => [],
    '3rd Year' => [],
    '4th Year' => []
];

try {
    // Get database connection
    $db = getDBConnection();
    
    // Get all students grouped by college year
    $query = "SELECT u.id, u.first_name, u.last_name, u.student_id, 
                     s.college_year, s.section, s.academic_year
              FROM users u
              JOIN student_data s ON u.id = s.user_id
              WHERE u.role = 'student' AND s.college_year IS NOT NULL
              ORDER BY 
                CASE s.college_year
                    WHEN '1st Year' THEN 1
                    WHEN '2nd Year' THEN 2
                    WHEN '3rd Year' THEN 3
                    WHEN '4th Year' THEN 4
                    ELSE 5
                END,
                s.section,
                u.last_name, u.first_name";

    $stmt = $db->query($query);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group students by college year
    foreach ($students as $student) {
        if (isset($groupedStudents[$student['college_year']])) {
            $groupedStudents[$student['college_year']][] = $student;
        }
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "An error occurred while fetching student data.";
}


?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">College Level Students</h1>
    </div>

    <div class="row">
        <?php foreach ($groupedStudents as $year => $yearStudents): ?>
            <?php if (!empty($yearStudents)): ?>
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($year); ?> Students</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($yearStudents as $student): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?>
                                            </h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($student['student_id']); ?></small>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">
                                                Section: <?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?>
                                            </small>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($student['academic_year'] ?? ''); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <small class="text-muted">
                                Total: <?php echo count($yearStudents); ?> student<?php echo count($yearStudents) !== 1 ? 's' : ''; ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
