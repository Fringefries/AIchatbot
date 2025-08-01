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
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                <button type="button" class="btn btn-sm btn-outline-secondary">Print</button>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <?php foreach ($groupedStudents as $year => $yearStudents): ?>
            <div class="col-md-3 mb-3">
                <div class="card border-left-primary h-100">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    <?php echo htmlspecialchars($year); ?> Students</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo count($yearStudents); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-people fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs" id="yearTabs" role="tablist">
        <?php $first = true; ?>
        <?php foreach ($groupedStudents as $year => $yearStudents): ?>
            <?php if (!empty($yearStudents)): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $first ? 'active' : ''; ?>" id="tab-<?php echo str_replace(' ', '-', strtolower($year)); ?>" 
                            data-bs-toggle="tab" data-bs-target="#<?php echo str_replace(' ', '-', strtolower($year)); ?>" 
                            type="button" role="tab" aria-controls="<?php echo str_replace(' ', '-', strtolower($year)); ?>" 
                            aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
                        <?php echo htmlspecialchars($year); ?>
                        <span class="badge bg-primary rounded-pill"><?php echo count($yearStudents); ?></span>
                    </button>
                </li>
                <?php $first = false; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content p-3 border border-top-0 rounded-bottom" id="yearTabsContent">
        <?php $first = true; ?>
        <?php foreach ($groupedStudents as $year => $yearStudents): ?>
            <?php if (!empty($yearStudents)): ?>
                <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" 
                     id="<?php echo str_replace(' ', '-', strtolower($year)); ?>" 
                     role="tabpanel" 
                     aria-labelledby="tab-<?php echo str_replace(' ', '-', strtolower($year)); ?>">
                    
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary"><?php echo htmlspecialchars($year); ?> Students</h6>
                            <div>
                                <button class="btn btn-sm btn-outline-primary me-2">
                                    <i class="bi bi-download"></i> Export
                                </button>
                                <button class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-printer"></i> Print
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Student ID</th>
                                            <th>Name</th>
                                            <th>Section</th>
                                            <th>Academic Year</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $counter = 1; ?>
                                        <?php foreach ($yearStudents as $student): ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                                <td><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></td>
                                                <td><?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($student['academic_year'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" title="View Profile">
                                                        <i class="bi bi-person-lines"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-success" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer text-muted">
                            Showing <?php echo count($yearStudents); ?> student<?php echo count($yearStudents) !== 1 ? 's' : ''; ?>
                        </div>
                    </div>
                </div>
                <?php $first = false; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<!-- Initialize Bootstrap Tabs -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tabElms = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabElms.forEach(function(tabEl) {
        tabEl.addEventListener('click', function (e) {
            e.preventDefault();
            var tab = new bootstrap.Tab(tabEl);
            tab.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
