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

// Get current user data
$userId = $_SESSION['user_id'];
$user = $auth->getUserById($userId);
$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/profile_pictures/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExt = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExt, $allowedExts)) {
            $newFileName = 'user_' . $userId . '_' . time() . '.' . $fileExt;
            $targetPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
                // Update database with new profile picture path
                $relativePath = '/uploads/profile_pictures/' . $newFileName;
                $stmt = $db->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                if ($stmt->execute([$relativePath, $userId])) {
                    $user['profile_picture'] = $relativePath;
                    $success = 'Profile picture updated successfully.';
                } else {
                    $error = 'Failed to update profile picture in database.';
                }
            } else {
                $error = 'Failed to upload file.';
            }
        } else {
            $error = 'Invalid file type. Only JPG, JPEG, PNG & GIF are allowed.';
        }
    }
    // Handle password change
    elseif (isset($_POST['current_password']) && !empty($_POST['current_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New password and confirm password do not match.';
        } elseif ($auth->changePassword($userId, $currentPassword, $newPassword)) {
            $success = 'Password changed successfully.';
        } else {
            $error = 'Current password is incorrect.';
        }
    }
    // Handle profile information update
    else {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        
        $stmt = $db->prepare("UPDATE users SET email = ?, phone = ?, address = ? WHERE id = ?");
        if ($stmt->execute([$email, $phone, $address, $userId])) {
            $user['email'] = $email;
            $user['phone'] = $phone;
            $user['address'] = $address;
            $success = 'Profile updated successfully.';
        } else {
            $error = 'Failed to update profile.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .profile-picture {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .profile-card {
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .nav-pills .nav-link.active {
            background-color: #4a6cf7;
        }
        .form-control:disabled, .form-control[readonly] {
            background-color: #f8f9fa;
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="d-flex flex-column p-3 bg-dark text-white min-vh-100">
                    <a href="#" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                        <span class="fs-4"><?php echo APP_NAME; ?></span>
                    </a>
                    <hr>
                    <ul class="nav nav-pills flex-column mb-auto">
                        <li class="nav-item">
                            <a href="<?php echo SITE_URL; ?>/student/dashboard.php" class="nav-link">
                                <i class="bi bi-speedometer2 me-2"></i> Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo SITE_URL; ?>/student/grades.php" class="nav-link">
                                <i class="bi bi-journal-text me-2"></i> My Grades
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo SITE_URL; ?>/student/schedule.php" class="nav-link">
                                <i class="bi bi-calendar3 me-2"></i> My Schedule
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo SITE_URL; ?>/student/documents.php" class="nav-link">
                                <i class="bi bi-file-earmark-text me-2"></i> Document Requests
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo SITE_URL; ?>/student/profile.php" class="nav-link active">
                                <i class="bi bi-person me-2"></i> My Profile
                            </a>
                        </li>
                    </ul>
                    <hr>
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?php echo !empty($user['profile_picture']) ? SITE_URL . $user['profile_picture'] : 'https://ui-avatars.com/api/?name=' . urlencode($user['first_name'] . ' ' . $user['last_name']) . '&background=random'; ?>" 
                                 alt="Profile" width="32" height="32" class="rounded-circle me-2">
                            <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/student/profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/logout.php">Sign out</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">My Profile</h1>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-4">
                        <div class="card profile-card mb-4">
                            <div class="card-body text-center">
                                <img src="<?php echo !empty($user['profile_picture']) ? SITE_URL . $user['profile_picture'] : 'https://ui-avatars.com/api/?name=' . urlencode($user['first_name'] . ' ' . $user['last_name']) . '&background=random&size=200'; ?>" 
                                     alt="Profile Picture" class="profile-picture mb-3">
                                <h4 class="mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                                <p class="text-muted mb-3">Student</p>
                                
                                <!-- Profile Picture Upload Form -->
                                <form method="post" enctype="multipart/form-data" class="mb-3">
                                    <div class="input-group">
                                        <input type="file" class="form-control form-control-sm" id="profile_picture" name="profile_picture" accept="image/*" required>
                                        <button class="btn btn-primary btn-sm" type="submit">
                                            <i class="bi bi-upload"></i> Upload
                                        </button>
                                    </div>
                                    <div class="form-text">JPG, PNG or GIF (Max 2MB)</div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card profile-card mb-4">
                            <div class="card-body">
                                <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
                                            Personal Info
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">
                                            Change Password
                                        </button>
                                    </li>
                                </ul>

                                <div class="tab-content" id="profileTabsContent">
                                    <!-- Personal Information Tab -->
                                    <div class="tab-pane fade show active" id="personal" role="tabpanel">
                                        <form method="post">
                                            <div class="row mb-3">
                                                <div class="col-md-6 mb-3">
                                                    <label for="studentId" class="form-label">Student ID</label>
                                                    <input type="text" class="form-control" id="studentId" 
                                                           value="<?php echo htmlspecialchars($user['student_id']); ?>" readonly>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="course" class="form-label">Course/Year Level</label>
                                                    <input type="text" class="form-control" id="course" 
                                                           value="<?php echo htmlspecialchars($user['course'] ?? 'BSIT - 3'); ?>" disabled>
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-6 mb-3">
                                                    <label for="firstName" class="form-label">First Name</label>
                                                    <input type="text" class="form-control" id="firstName" 
                                                           value="<?php echo htmlspecialchars($user['first_name']); ?>" readonly>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="lastName" class="form-label">Last Name</label>
                                                    <input type="text" class="form-control" id="lastName" 
                                                           value="<?php echo htmlspecialchars($user['last_name']); ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($user['email']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label for="phone" class="form-label">Phone</label>
                                                <input type="tel" class="form-control" id="phone" name="phone" 
                                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label for="address" class="form-label">Address</label>
                                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Update Profile</button>
                                        </form>
                                    </div>

                                    <!-- Change Password Tab -->
                                    <div class="tab-pane fade" id="password" role="tabpanel">
                                        <form method="post">
                                            <div class="mb-3">
                                                <label for="current_password" class="form-label">Current Password</label>
                                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="new_password" class="form-label">New Password</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                                <div class="form-text">Password must be at least 8 characters long.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Change Password</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show file name when selected
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'Choose file';
            const nextSibling = e.target.nextElementSibling;
            nextSibling.innerText = fileName;
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>
