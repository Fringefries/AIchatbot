<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Admin Panel';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle . ' - ' . APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card.primary { border-color: #4e73df; }
        .stat-card.success { border-color: #1cc88a; }
        .stat-card.warning { border-color: #f6c23e; }
        .stat-card.danger { border-color: #e74a3b; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="d-flex flex-column p-3 h-100">
                    <div class="text-center mb-4">
                        <i class="bi bi-mortarboard-fill fs-1 text-white-50 mb-2 d-block"></i>
                        <h5 class="text-white"><?php echo APP_NAME; ?></h5>
                        <p class="text-white-50 small mb-0">Administration</p>
                    </div>
                    <hr class="border-secondary">
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link text-start <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="document_requests.php" class="nav-link text-start <?php echo basename($_SERVER['PHP_SELF']) === 'document_requests.php' ? 'active' : ''; ?>">
                                Document Requests
                                <?php
                                $pendingCount = $db->query("SELECT COUNT(*) FROM document_requests WHERE status = 'pending'")->fetchColumn();
                                if ($pendingCount > 0): ?>
                                    <span class="badge bg-danger rounded-pill float-end"><?php echo $pendingCount; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="manage_students.php" class="nav-link text-start <?php echo basename($_SERVER['PHP_SELF']) === 'manage_students.php' ? 'active' : ''; ?>">
                                Student Roster
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="users.php" class="nav-link text-start <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
                                User Accounts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link text-start">
                                Academic Records
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link text-start">
                                Class Schedules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="college_level.php" class="nav-link text-start <?php echo basename($_SERVER['PHP_SELF']) === 'college_level.php' ? 'active' : ''; ?>">
                                College Level
                            </a>
                        </li>
                    </ul>
                    
                    <!-- User dropdown at bottom -->
                    <div class="mt-auto">
                        <hr class="border-secondary">
                        <div class="dropdown">
                            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="me-2 bg-white text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                                <div class="d-flex flex-column">
                                    <small class="text-white-50">Logged in as</small>
                                    <strong class="small"><?php echo htmlspecialchars($auth->getCurrentUser()['first_name'] . ' ' . $auth->getCurrentUser()['last_name']); ?></strong>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                                <li><a class="dropdown-item" href="../profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                                <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
