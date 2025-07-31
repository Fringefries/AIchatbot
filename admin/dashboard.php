<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin access
$auth->requireAdmin();

// Get statistics
$stats = [
    'total_users' => $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
    'total_requests' => $db->query("SELECT COUNT(*) FROM document_requests")->fetchColumn(),
    'pending_requests' => $db->query("SELECT COUNT(*) FROM document_requests WHERE status = 'pending'")->fetchColumn(),
    'approved_requests' => $db->query("SELECT COUNT(*) FROM document_requests WHERE status = 'approved'")->fetchColumn(),
    'rejected_requests' => $db->query("SELECT COUNT(*) FROM document_requests WHERE status = 'rejected'")->fetchColumn(),
];

// Get recent document requests
$recentRequests = $db->query("
    SELECT dr.*, u.first_name, u.last_name, u.student_id, dt.name as document_type
    FROM document_requests dr
    JOIN users u ON u.id = dr.user_id
    JOIN document_types dt ON dt.id = dr.document_type_id
    ORDER BY dr.requested_at DESC
    LIMIT 10
")->fetchAll();

// Get recent user registrations
$recentUsers = $db->query("
    SELECT id, first_name, last_name, email, role, created_at 
    FROM users 
    WHERE is_active = 1
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

$pageTitle = "Admin Dashboard";
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
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
<div class="row">
    <!-- Recent Document Requests -->
    <div class="col-md-8 mb-4">
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

    $string = [
        'y' => 'year',
        'm' => 'month',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    ];
    
    // Calculate weeks from days
    $days = $diff->d;
    if ($days >= 7) {
        $weeks = floor($days / 7);
        $days = $days % 7;
        $string = ['w' => 'week'] + $string;
        $diff->w = $weeks;
    }
    
    $parts = [];
    foreach ($string as $k => $v) {
        if (isset($diff->$k) && $diff->$k) {
            $parts[] = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        }
    }

    if (!$full) {
        $parts = array_slice($parts, 0, 1);
    }
    
    return $parts ? implode(', ', $parts) . ' ago' : 'just now';
}

// ... (rest of the file remains the same)
?>

<?php include __DIR__ . '/includes/footer.php'; ?>
