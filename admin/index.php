<?php
require_once '../includes/config.php';
require_once '../includes/Auth.php';

$db = getDBConnection();
$auth = new Auth($db);
$auth->requireLogin();
$admin = $auth->getCurrentAdmin();

// Get stats
$stats = [
    'total_inquiries' => $db->query("SELECT COUNT(*) FROM inquiries")->fetchColumn(),
    'pending_inquiries' => $db->query("SELECT COUNT(*) FROM inquiries WHERE is_resolved = 0")->fetchColumn()
];

// Get recent inquiries
$recentInquiries = $db->query("SELECT * FROM inquiries ORDER BY created_at DESC LIMIT 5")->fetchAll();

$pageTitle = 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/chatbot/school_inquiry_chatbot_php/admin/"><?= APP_NAME ?> Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="/chatbot/school_inquiry_chatbot_php/admin/">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/chatbot/school_inquiry_chatbot_php/admin/inquiries.php">Inquiries</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-shield me-1"></i> <?= htmlspecialchars($admin['username']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/chatbot/school_inquiry_chatbot_php/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Dashboard</h2>
        
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card text-white bg-success mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">TOTAL INQUIRIES</h6>
                                <h2 class="mb-0"><?= number_format($stats['total_inquiries']) ?></h2>
                            </div>
                            <i class="fas fa-comments fa-3x opacity-50"></i>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <a href="inquiries.php" class="text-white">View All <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-white bg-warning mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">PENDING INQUIRIES</h6>
                                <h2 class="mb-0"><?= number_format($stats['pending_inquiries']) ?></h2>
                            </div>
                            <i class="fas fa-clock fa-3x opacity-50"></i>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <a href="inquiries.php?status=pending" class="text-white">View Pending <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Inquiries -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Inquiries</h5>
                <a href="inquiries.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (count($recentInquiries) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentInquiries as $inquiry): ?>
                            <tr>
                                <td>#<?= $inquiry['id'] ?></td>
                                <td><?= htmlspecialchars(substr($inquiry['message'], 0, 50)) ?>...</td>
                                <td>
                                    <span class="badge bg-<?= $inquiry['is_resolved'] ? 'success' : 'warning' ?>">
                                        <?= $inquiry['is_resolved'] ? 'Resolved' : 'Pending' ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y h:i A', strtotime($inquiry['created_at'])) ?></td>
                                <td>
                                    <a href="view_inquiry.php?id=<?= $inquiry['id'] ?>" class="btn btn-sm btn-info">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">No inquiries found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
