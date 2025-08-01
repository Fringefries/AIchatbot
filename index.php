<?php
// Include configuration and initialize auth
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// Check if user is logged in, redirect to login if not
if (!$auth->isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

// Redirect admin users to the admin dashboard
if ($auth->isAdmin()) {
    header('Location: ' . SITE_URL . '/admin/admin_dashboard.php');
    exit();
}

// Get current user data
$user = $auth->getCurrentUser();

// Get user's class schedule
$scheduleByDay = [];
$stmt = $db->prepare("SELECT * FROM class_schedule WHERE user_id = ? ORDER BY day_of_week, start_time");
$stmt->execute([$user['id']]);
$schedules = $stmt->fetchAll();

foreach ($schedules as $schedule) {
    $scheduleByDay[strtolower($schedule['day_of_week'])][] = $schedule;
}

// Get user's recent grades
$stmt = $db->prepare("SELECT * FROM grades 
                     WHERE student_id = ? 
                     ORDER BY created_at DESC 
                     LIMIT 5");
$stmt->execute([$user['id']]);
$grades = $stmt->fetchAll();

// Get attendance
$stmt = $db->prepare("SELECT * FROM attendance WHERE student_id = ? ORDER BY date DESC LIMIT 5");
$stmt->execute([$user['id']]);
$attendance = $stmt->fetchAll();

// Get assignments
$stmt = $db->prepare("SELECT a.*, u.first_name, u.last_name 
                     FROM assignments a
                     JOIN users u ON a.assigned_by = u.id
                     WHERE a.id IN (
                         SELECT assignment_id 
                         FROM student_assignments 
                         WHERE student_id = ?
                     ) 
                     AND a.due_date >= CURDATE() 
                     ORDER BY a.due_date ASC 
                     LIMIT 5");
$stmt->execute([$user['id']]);
$assignments = $stmt->fetchAll();

// Get notifications
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

// Get student data for the dashboard
$stmt = $db->prepare("
    SELECT sd.*, u.first_name, u.last_name, u.student_id 
    FROM student_data sd 
    JOIN users u ON u.id = sd.user_id 
    WHERE u.id = ?
");
$stmt->execute([$user['id']]);
$student = $stmt->fetch();

// Define base URL for assets
$assets_url = SITE_URL . '/assets';

// Get today's schedule
$today = strtolower(date('l'));
$todaySchedule = $scheduleByDay[$today] ?? [];

if (!isset($_SESSION['chat_session_id'])) {
    $_SESSION['chat_session_id'] = uniqid('chat_');
}

$pageTitle = 'SPCC Chatbot';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo $assets_url; ?>/css/style.css">
    <style>
        /* Chatbot Styles */
        .floating-chat-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #4a6cf7;
            color: white;
            border: none;
            border-radius: 30px;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .floating-chat-button i {
            font-size: 20px;
        }

        .floating-chat-button:hover {
            background: #3a5bd9;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        }

        .chat-container {
            position: fixed;
            bottom: 100px;
            right: 30px;
            width: 380px;
            height: 600px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            z-index: 1001;
            transform: translateY(20px);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .chat-container.visible {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
        }

        .chat-header {
            background: #4a6cf7;
            color: white;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-header h5 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .minimize-button {
            background: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .minimize-button:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background-color: #f8f9fa;
        }

        .message {
            max-width: 80%;
            padding: 10px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.5;
            position: relative;
            word-wrap: break-word;
        }

        .bot-message {
            align-self: flex-start;
            background: white;
            color: #212529;
            border-bottom-left-radius: 4px;
        }

        .user-message {
            align-self: flex-end;
            background: #4a6cf7;
            color: white;
            border-bottom-right-radius: 4px;
        }

        .welcome-message {
            text-align: center;
            padding: 20px 0;
            color: #6c757d;
        }

        .welcome-message i {
            font-size: 40px;
            margin-bottom: 10px;
            display: block;
        }

        .welcome-message h5 {
            margin-bottom: 8px;
            color: #212529;
        }

        .welcome-message p {
            margin: 0;
            font-size: 15px;
            line-height: 1.5;
        }

        .chat-input-container {
            display: flex;
            padding: 15px;
            border-top: 1px solid #e9ecef;
            background: #f8f9fa;
        }

        .chat-input {
            flex: 1;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            padding: 10px 20px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .chat-input:focus {
            border-color: #4a6cf7;
        }

        .send-button {
            background: #4a6cf7;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-left: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
        }

        .send-button:hover {
            background: #3a5bd9;
        }

        .typing-indicator {
            display: flex;
            gap: 4px;
            padding: 10px 16px;
            background: #f1f3f5;
            border-radius: 18px;
            align-self: flex-start;
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            background-color: #6c757d;
            border-radius: 50%;
            animation: typingAnimation 1.4s infinite ease-in-out;
        }

        .typing-dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typingAnimation {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-5px); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .chat-container {
                margin: 0;
                height: 100vh;
                max-height: none;
                border-radius: 0;
                right: 0;
                bottom: 0;
                width: 100%;
            }
            
            .message {
                max-width: 85%;
            }
            
            .floating-chat-button {
                bottom: 20px;
                right: 20px;
            }
        }

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
        .main-content {
            padding: 2rem;
        }
        .welcome-card {
            background: linear-gradient(135deg, #4e73df, #224abe);
            color: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 600;
        }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f4f7f9;
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        /* Base Styles */
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f0f2f5;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Chat Container */
        .chat-container {
            width: 100%;
            max-width: 800px;
            height: 90vh;
            max-height: 900px;
            margin: 0 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Chat Header */
        .chat-header {
            background: #007bff;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .chat-header div:first-child {
            font-size: 18px;
            font-weight: 600;
        }

        .admin-login a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .admin-login a:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Chat Messages */
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
        }
        
        .suggested-questions {
            margin-top: 20px;
            padding: 15px;
            border-radius: 10px;
        }
        
        .suggestion-title {
            font-size: 14px;
            color: #4a5568;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .suggestion-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .suggestion-btn {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 8px 16px;
            font-size: 14px;
            color: #2d3748;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
            max-width: 100%;
        }
        
        .suggestion-btn:hover {
            background: #edf2f7;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .suggestion-btn:active {
            transform: translateY(0);
            background: #e2e8f0;
        }

        /* Messages */
        .message {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 18px;
            font-size: 15px;
            line-height: 1.4;
            position: relative;
            word-wrap: break-word;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .user-message {
            align-self: flex-end;
            background: #007bff;
            color: white;
            border-bottom-right-radius: 4px;
        }

        .bot-message {
            align-self: flex-start;
            background: white;
            color: #333;
            border: 1px solid #e1e4e8;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            margin-top: 5px;
        }
        
        /* Add extra margin to the first bot message after user message */
        .user-message + .bot-message {
            margin-top: 10px;
        }

        /* Typing Indicator */
        .typing-indicator {
            display: none;
            padding: 0 20px 15px 20px;
            justify-content: flex-start;
            align-items: center;
            margin-bottom: 5px;
        }

        .typing-dots {
            display: inline-flex;
            align-items: center;
            background: #f0f0f0;
            padding: 10px 15px;
            border-radius: 18px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .typing-dots span {
            width: 8px;
            height: 8px;
            background: #a0a0a0;
            border-radius: 50%;
            display: inline-block;
            margin: 0 2px;
            animation: typing 1.4s infinite ease-in-out both;
        }

        .typing-dots span:nth-child(1) { animation-delay: -0.32s; }
        .typing-dots span:nth-child(2) { animation-delay: -0.16s; }

        @keyframes typing {
            0%, 80%, 100% { 
                transform: translateY(0);
                opacity: 0.5;
            }
            40% { 
                transform: translateY(-5px);
                opacity: 1;
            }
        }

        /* Chat Input */
        .chat-input {
            padding: 15px;
            background: #fff;
            border-top: 1px solid #e1e4e8;
            position: relative;
        }
        
        .chat-input form {
            display: flex;
            position: relative;
            width: 100%;
        }
        
        .chat-input input {
            width: 100%;
            padding: 12px 60px 12px 15px;
            border: 1px solid #e1e4e8;
            border-radius: 24px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        
        .chat-input input:focus {
            border-color: #4a6cf7;
        }
        
        .chat-input button[type="submit"] {
            position: absolute;
            right: 6px;
            top: 5px;
            width: 38px;
            height: 38px;
            border: none;
            border-radius: 50%;
            background: #4a6cf7;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            padding: 0;
        }
        
        .chat-input button[type="submit"]:hover {
            background: #3a5bd9;
        }
        .chat-input button:active {
            transform: scale(0.95);
        }

        /* Welcome Message */
        .welcome-message {
            text-align: center;
            padding: 30px 20px;
            color: #666;
        }

        .welcome-message i {
            font-size: 36px;
            margin-bottom: 15px;
            color: #007bff;
            opacity: 0.8;
        }

        .welcome-message p {
            margin: 0;
            font-size: 15px;
            line-height: 1.5;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .chat-container {
                margin: 0;
                height: 100vh;
                max-height: none;
                border-radius: 0;
            }

            .message {
                max-width: 85%;
            }
        }
        /* Chat Toggle Button */
        .floating-chat-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #4a6cf7;
            color: white;
            border: none;
            border-radius: 30px;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .floating-chat-button i {
            font-size: 20px;
        }
        
        .floating-chat-button:hover {
            background: #3a5bd9;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        }
        
        /* Chat Container */
        .chat-container {
            position: fixed;
            bottom: 100px;
            right: 30px;
            width: 380px;
            height: 600px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            z-index: 1001;
            transform: translateY(20px);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .chat-container.visible {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
        }
        
        /* Header Buttons */
        .minimize-button {
            background: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-left: 5px;
        }
        
        .faq-header-button:hover, .minimize-button:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.1);
        }
        
        .faq-panel {
            position: absolute;
            top: 60px;
            right: 10px;
            width: 300px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            z-index: 999;
            overflow: hidden;
            transform: translateY(20px);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            max-height: 70vh;
            display: flex;
            flex-direction: column;
        }
        
        .faq-panel.visible {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
        }
        
        .faq-header {
            padding: 15px;
            background: #4a6cf7;
            color: white;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .faq-close {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }
        
        .faq-content {
            padding: 0;
            margin: 0;
            list-style: none;
            overflow-y: auto;
            flex-grow: 1;
        }
        
        .faq-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .faq-item:last-child {
            border-bottom: none;
        }
        
        .faq-item:hover {
            background: #f8f9fa;
        }
        
        .faq-item:active {
            background: #f1f3f5;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="d-flex flex-column p-3">
                    <a href="#" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                        <span class="fs-4"><?php echo APP_NAME; ?></span>
                    </a>
                    <hr>
                    <ul class="nav nav-pills flex-column mb-auto">
                        <li class="nav-item">
                            <a href="#" class="nav-link active">
                                <i class="bi bi-speedometer2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo SITE_URL; ?>/student/grades.php" class="nav-link">
                                <i class="bi bi-journal-text"></i>
                                My Grades
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo SITE_URL; ?>/schedule.php" class="nav-link">
                                <i class="bi bi-calendar3"></i>
                                My Schedule
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo SITE_URL; ?>/documents.php" class="nav-link">
                                <i class="bi bi-file-earmark-text"></i>
                                Document Requests
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo SITE_URL; ?>/student/profile.php" class="nav-link">
                                <i class="bi bi-person"></i>
                                My Profile
                            </a>
                        </li>
                        <?php if ($auth->isAdmin()): ?>
                        <li class="mt-4">
                            <div class="text-uppercase small fw-bold text-white-50 mb-2">Admin</div>
                            <a href="<?php echo SITE_URL; ?>/admin/admin_dashboard.php" class="nav-link">
                                <i class="bi bi-shield-lock"></i>
                                Admin Panel
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <hr>
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-2"></i>
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

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="welcome-card">
                    <h2>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h2>
                    <p class="lead">Here's what's happening with your account today.</p>
                </div>

                <div class="row">
                    <!-- Quick Stats -->
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-journal-text fs-1 text-primary"></i>
                                <h5 class="card-title mt-2">Current GPA</h5>
                                <h2 class="mb-0"><?php echo $gpa ?? 'N/A'; ?></h2>
                                <small class="text-muted">Based on current term</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-calendar-check fs-1 text-success"></i>
                                <h5 class="card-title mt-2">Today's Classes</h5>
                                <h2 class="mb-0">3</h2>
                                <small class="text-muted">Classes remaining</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-file-earmark-text fs-1 text-warning"></i>
                                <h5 class="card-title mt-2">Pending Requests</h5>
                                <h2 class="mb-0">2</h2>
                                <small class="text-muted">Document requests</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Classes -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-calendar3 me-2"></i>
                                Today's Schedule
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($todaySchedule)): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($todaySchedule as $class): ?>
                                            <li class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($class['subject']); ?></h6>
                                                        <small class="text-muted">
                                                            <?php 
                                                            $start = new DateTime($class['start_time']);
                                                            $end = new DateTime($class['end_time']);
                                                            echo $start->format('g:i A') . ' - ' . $end->format('g:i A');
                                                            ?>
                                                        </small>
                                                    </div>
                                                    <span class="badge bg-primary rounded-pill">
                                                        <?php echo htmlspecialchars($class['room_number']); ?>
                                                    </span>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="text-center p-4 text-muted">
                                        <i class="bi bi-emoji-smile fs-1 d-block mb-2"></i>
                                        No classes scheduled for today!
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Grades -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-journal-check me-2"></i>
                                Recent Grades
                            </div>
                            <div class="card-body p-0">
                                <?php 
                                // Calculate GPA
                                $gpa = 0;
                                $totalCredits = 0;
                                $gradePoints = 0;
                                $currentYear = date('Y');
                                $currentMonth = date('n');
                                $currentTerm = ($currentMonth >= 8 || $currentMonth <= 1) ? '1st Semester' : '2nd Semester';
                                $currentAcademicYear = ($currentMonth >= 8) ? $currentYear . '-' . ($currentYear + 1) : ($currentYear - 1) . '-' . $currentYear;
                                
                                if (!empty($grades)): 
                                    // Get unique subjects with their latest grade
                                    $uniqueGrades = [];
                                    foreach ($grades as $grade) {
                                        $subject = $grade['subject'];
                                        if (!isset($uniqueGrades[$subject]) || 
                                            strtotime($grade['created_at']) > strtotime($uniqueGrades[$subject]['created_at'])) {
                                            $uniqueGrades[$subject] = $grade;
                                        }
                                        
                                        // For GPA calculation (only current term)
                                        if ($grade['term'] === $currentTerm && $grade['academic_year'] === $currentAcademicYear) {
                                            // Simple GPA calculation (assuming all courses have equal weight)
                                            $gradeValue = (float)$grade['grade'];
                                            $gradePoints += $gradeValue;
                                            $totalCredits++;
                                        }
                                    }
                                    
                                    // Calculate GPA (4.0 scale)
                                    $gpa = $totalCredits > 0 ? ($gradePoints / $totalCredits) / 25 : 0;
                                    $gpa = min(4.0, number_format($gpa, 2));
                                    
                                    // Sort by most recent first
                                    usort($uniqueGrades, function($a, $b) {
                                        return strtotime($b['created_at']) - strtotime($a['created_at']);
                                    });
                                    ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Subject</th>
                                                    <th>Grade</th>
                                                    <th>Term</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_slice($uniqueGrades, 0, 5) as $grade): 
                                                    $gradeClass = 'grade-' . strtoupper(substr($grade['grade'], 0, 1));
                                                ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($grade['subject']); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo ($grade['grade'] >= 90) ? 'success' : 
                                                                    (($grade['grade'] >= 75) ? 'primary' : 'danger'); 
                                                            ?> grade-value <?php echo $gradeClass; ?>">
                                                                <?php echo htmlspecialchars($grade['grade']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars(ucfirst($grade['term'])); ?></td>
                                                        <td>
                                                            <a href="<?php echo SITE_URL; ?>/student/grades.php#<?php echo strtolower(urlencode($grade['subject'])); ?>" 
                                                               class="btn btn-sm btn-outline-primary"
                                                               title="View details">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center p-2 border-top">
                                        <div class="text-muted small">
                                            <strong>Current GPA:</strong> <?php echo $gpa; ?>/4.0
                                        </div>
                                        <a href="<?php echo SITE_URL; ?>/student/grades.php" class="btn btn-sm btn-outline-primary">
                                            View All Grades <i class="bi bi-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center p-4 text-muted">
                                        <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
                                        No grades available yet.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
</div>
</div>

<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chatbot Toggle Button -->
<button class="floating-chat-button" id="chatbotToggle">
    <i class="bi bi-robot"></i> SPCC Chatbot
</button>

<!-- Chatbot Container -->
<div class="chat-container" id="chatbotContainer">
    <div class="chat-header">
        <div class="d-flex align-items-center">
            <i class="bi bi-robot me-2"></i>
            <h5 class="mb-0">SPCC Chatbot</h5>
        </div>
        <div>
            <button class="minimize-button" id="minimizeChatbot">
                <i class="bi bi-dash"></i>
            </button>
        </div>
    </div>
    <div class="chat-messages" id="chatMessages">
        <div class="welcome-message">
            <i class="bi bi-robot fs-1 text-primary mb-2"></i>
            <h5>Hello! I'm the SPCC Chatbot</h5>
            <p>How can I help you today? You can ask me about your schedule, grades, or any school-related questions.</p>
        </div>
    </div>
    <div class="chat-input-container">
        <input type="text" class="chat-input" id="userMessage" placeholder="Type your message here...">
        <button class="send-button" id="sendMessage">
            <i class="bi bi-send"></i>
        </button>
    </div>
</div>
<script>
// Chatbot functionality
document.addEventListener('DOMContentLoaded', function() {
    const chatToggle = document.getElementById('chatbotToggle');
    const chatbotContainer = document.getElementById('chatbotContainer');
    const minimizeButton = document.getElementById('minimizeChatbot');
    const chatMessages = document.getElementById('chatMessages');
    const userInput = document.getElementById('userMessage');
    const sendButton = document.getElementById('sendMessage');
    
    let isChatOpen = false;
    
    // Toggle chat window
    chatToggle.addEventListener('click', function() {
        isChatOpen = !isChatOpen;
        if (isChatOpen) {
            chatbotContainer.classList.add('visible');
            chatToggle.innerHTML = '<i class="bi bi-x"></i> Close Chat';
        } else {
            chatbotContainer.classList.remove('visible');
            chatToggle.innerHTML = '<i class="bi bi-robot"></i> SPCC Chatbot';
        }
    });
    
    // Minimize chat
    minimizeButton.addEventListener('click', function() {
        chatbotContainer.classList.remove('visible');
        chatToggle.innerHTML = '<i class="bi bi-robot"></i> SPCC Chatbot';
        isChatOpen = false;
    });
    
    // Send message on button click
    sendButton.addEventListener('click', sendMessage);
    
    // Send message on Enter key
    userInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
    
    function sendMessage() {
        const message = userInput.value.trim();
        if (message === '') return;
        
        // Add user message to chat
        addMessage(message, 'user');
        userInput.value = '';
        
        // Show typing indicator
        const typingIndicator = addTypingIndicator();
        
        // Send message to the server
        fetch('api/ask.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `message=${encodeURIComponent(message)}`
        })
        .then(response => response.json())
        .then(data => {
            // Remove typing indicator
            chatMessages.removeChild(typingIndicator);
            
            // Add bot response
            if (data.reply) {
                addMessage(data.reply, 'bot');
            } else {
                addMessage('I apologize, but I encountered an error processing your request.', 'bot');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            chatMessages.removeChild(typingIndicator);
            addMessage('Sorry, I am unable to process your request at the moment. Please try again later.', 'bot');
        });
    }
    
    function addTypingIndicator() {
        const typingDiv = document.createElement('div');
        typingDiv.className = 'typing-indicator';
        typingDiv.innerHTML = `
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
        `;
        chatMessages.appendChild(typingDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        return typingDiv;
    }
    
    function addMessage(text, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}-message`;
        messageDiv.textContent = text;
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});

// Enable Bootstrap tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Enable Bootstrap popovers
var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
    return new bootstrap.Popover(popoverTriggerEl);
});
</script>
</body>
</html>
