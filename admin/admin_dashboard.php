<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session and initialize auth
if (session_status() === PHP_SESSION_NONE) session_start();
$db = getDBConnection();
$auth = new Auth($db);

// Check login and admin role
if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

// Get admin data
$adminId = $_SESSION['user_id'];
$admin = $auth->getUserById($adminId);

// Get statistics
$stats = [
    'total_students' => $db->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1")->fetchColumn(),
    'total_requests' => $db->query("SELECT COUNT(*) FROM document_requests")->fetchColumn(),
    'pending_requests' => $db->query("SELECT COUNT(*) FROM document_requests WHERE status = 'pending'")->fetchColumn(),
    'approved_requests' => $db->query("SELECT COUNT(*) FROM document_requests WHERE status = 'approved'")->fetchColumn(),
    'recent_users' => $db->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn()
];

// Get recent document requests
$recentRequests = $db->query("
    SELECT dr.*, u.first_name, u.last_name, u.student_id, dt.name as document_type
    FROM document_requests dr
    JOIN users u ON u.id = dr.user_id
    JOIN document_types dt ON dt.id = dr.document_type_id
    ORDER BY dr.requested_at DESC
    LIMIT 5
")->fetchAll();

// Get recent user registrations
$recentUsers = $db->query("
    SELECT id, first_name, last_name, email, role, created_at 
    FROM users 
    WHERE is_active = 1
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

// Set page title for the header
$pageTitle = 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
            margin-bottom: 1rem;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card.primary { border-color: #4e73df; }
        .stat-card.success { border-color: #1cc88a; }
        .stat-card.warning { border-color: #f6c23e; }
        .stat-card.danger { border-color: #e74a3b; }
        .sidebar {
            background-color: #212529;
            color: white;
        }
        .nav-pills .nav-link {
            color: rgba(255, 255, 255, 0.8);
        }
        .nav-pills .nav-link.active {
            background-color: #4e73df;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar min-vh-100">
                <div class="text-center my-4">
                    <img src="<?php echo !empty($admin['profile_pic']) ? htmlspecialchars($admin['profile_pic']) : 'https://ui-avatars.com/api/?name=' . urlencode($admin['first_name'] . ' ' . $admin['last_name']) . '&background=random'; ?>" 
                         class="rounded-circle" width="80" height="80" alt="Profile">
                    <h6 class="my-2 text-white"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></h6>
                    <span class="badge bg-primary">Administrator</span>
                </div>
                
                <ul class="nav flex-column px-2">
                    <li class="nav-item mb-2">
                        <a class="nav-link active" href="<?php echo SITE_URL; ?>/admin/admin_dashboard.php">
                            <i class="bi bi-speedometer2 me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/admin/document_requests.php">
                            <i class="bi bi-journal-text me-2"></i> Document Requests
                            <?php if ($stats['pending_requests'] > 0): ?>
                                <span class="badge bg-danger rounded-pill float-end"><?php echo $stats['pending_requests']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/admin/manage_students.php">
                            <i class="bi bi-people me-2"></i> Student Roster
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/admin/users.php">
                            <i class="bi bi-person-lines-fill me-2"></i> User Accounts
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h1 class="h2">Admin Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print
                        </button>
                    </div>
                </div>
                
                <!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2 stat-card primary">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Users</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_users']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-people fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2 stat-card success">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Total Requests</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_requests']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-file-earmark-text fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2 stat-card warning">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Pending Requests</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_requests']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-clock-history fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2 stat-card info">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Processed Requests</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $stats['approved_requests'] + $stats['rejected_requests']; ?>
                            <small class="text-muted">
                                (<?php echo $stats['approved_requests']; ?> ✓ / <?php echo $stats['rejected_requests']; ?> ✗)
                            </small>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-check2-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
                <!-- Stats Row -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Students</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_students']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-people fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Requests</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_requests']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-file-earmark-text fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pending Requests</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_requests']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-hourglass-split fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            New Users (7 days)</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['recent_users']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-person-plus fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Document Requests -->
                    <div class="col-lg-8 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Recent Document Requests</h6>
                <a href="document_requests.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Requester</th>
                                <th>Document Type</th>
                                <th>Requested</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentRequests as $request): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                        <?php if (!empty($request['student_id'])): ?>
                                            <br><small class="text-muted">ID: <?php echo htmlspecialchars($request['student_id']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['document_type']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($request['requested_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $request['status'] === 'approved' ? 'success' : 
                                                 ($request['status'] === 'rejected' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="document_requests.php?view=<?php echo $request['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentRequests)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No recent document requests</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Users -->
    <div class="col-md-4 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Recently Registered Users</h6>
                <a href="users.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($recentUsers as $user): ?>
                        <div class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h6>
                                <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?> rounded-pill">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </div>
                            <p class="mb-1"><?php echo htmlspecialchars($user['email']); ?></p>
                            <small class="text-muted">
                                Registered <?php echo time_elapsed_string($user['created_at']); ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($recentUsers)): ?>
                        <div class="text-center text-muted py-3">No recent users</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Helper function to show time elapsed
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Enable tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
</script>
</body>
</html>
