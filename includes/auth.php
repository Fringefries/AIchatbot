<?php
require_once __DIR__ . '/config.php';

class Auth {
    private $db;
    private $user = null;

    public function __construct($db) {
        $this->db = $db;
        $this->startSession();
    }

    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Load user from session if logged in
        if (isset($_SESSION['user_id'])) {
            $this->loadUser($_SESSION['user_id']);
        }
    }

    /**
     * Get user by ID
     */
    /**
     * Get grades per subject for a student
     * @param int $studentId The ID of the student
     * @return array Array of grades grouped by subject and semester
     */
    public function getStudentGrades($studentId = null) {
        if ($studentId === null && $this->isLoggedIn()) {
            $studentId = $_SESSION['user_id'];
        }
        
        if (!$studentId) {
            return [];
        }
        
        // Get current academic year and term
        $currentYear = date('Y');
        $currentMonth = date('n');
        $currentTerm = ($currentMonth >= 8 || $currentMonth <= 1) ? '1st Semester' : '2nd Semester';
        $currentAcademicYear = ($currentMonth >= 8) ? $currentYear . '-' . ($currentYear + 1) : ($currentYear - 1) . '-' . $currentYear;
        
        // Fetch all grades for the student
        $stmt = $this->db->prepare(
            "SELECT g.*, 
                    s.name as subject_name,
                    s.code as subject_code,
                    CONCAT(g.term, ' ', g.academic_year) as semester
             FROM grades g
             LEFT JOIN subjects s ON g.subject = s.code
             WHERE g.student_id = ?
             ORDER BY g.academic_year DESC, FIELD(g.term, '1st Semester', '2nd Semester', 'Summer'), g.subject"
        );
        $stmt->execute([$studentId]);
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize grades by subject and semester
        $organizedGrades = [];
        $subjects = [];
        
        foreach ($grades as $grade) {
            $subjectCode = $grade['subject_code'] ?? $grade['subject'];
            $subjectName = $grade['subject_name'] ?? $grade['subject'];
            $semester = $grade['semester'];
            
            // Initialize subject if not exists
            if (!isset($organizedGrades[$subjectCode])) {
                $organizedGrades[$subjectCode] = [
                    'name' => $subjectName,
                    'code' => $subjectCode,
                    'semesters' => []
                ];
            }
            
            // Add grade to the corresponding semester
            if (!isset($organizedGrades[$subjectCode]['semesters'][$semester])) {
                $organizedGrades[$subjectCode]['semesters'][$semester] = [];
            }
            
            $organizedGrades[$subjectCode]['semesters'][$semester][] = [
                'grade' => $grade['grade'],
                'term' => $grade['term'],
                'academic_year' => $grade['academic_year'],
                'comments' => $grade['comments'],
                'created_at' => $grade['created_at']
            ];
        }
        
        // Add current semester at the top if it doesn't have grades yet
        $currentSemester = $currentTerm . ' ' . $currentAcademicYear;
        foreach ($organizedGrades as &$subject) {
            if (!isset($subject['semesters'][$currentSemester])) {
                $subject['semesters'][$currentSemester] = [];
            }
            // Reorder semesters to have current semester first
            if (isset($subject['semesters'][$currentSemester])) {
                $current = [$currentSemester => $subject['semesters'][$currentSemester]];
                unset($subject['semesters'][$currentSemester]);
                $subject['semesters'] = $current + $subject['semesters'];
            }
        }
        
        return $organizedGrades;
    }
    
    /**
     * Get user by ID
     */
    private function getUserById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Login user
     * @param string $username Username or email
     * @param string $password User's password
     * @param bool $isAdmin Whether logging in as admin
     * @return array User data
     * @throws Exception If login fails
     */
    public function login($username, $password, $isAdmin = false) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            throw new Exception('Invalid username or password');
        }

        // Check admin access if required
        if ($isAdmin && $user['role'] !== 'admin') {
            throw new Exception('Admin access required');
        }

        // Update last login
        $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                ->execute([$user['id']]);

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $this->user = $user;

        return $user;
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Check if current user is admin
     */
    public function isAdmin() {
        return $this->isLoggedIn() && isset($this->user['role']) && $this->user['role'] === 'admin';
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser() {
        if (!$this->user && $this->isLoggedIn()) {
            $success = $this->loadUser($_SESSION['user_id']);
            if (!$success) {
                // If we couldn't load the user, log them out
                $this->logout();
                return null;
            }
        }
        return $this->user;
    }
    
    /**
     * Register a new student
     * @param array $userData User data (username, email, password, first_name, last_name, student_id)
     * @return int New user ID
     * @throws Exception If registration fails
     */
    public function registerStudent($userData) {
        // Validate required fields
        $required = ['username', 'email', 'password', 'first_name', 'last_name', 'student_id'];
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                throw new Exception("$field is required");
            }
        }
        
        // Check if username or email already exists
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$userData['username'], $userData['email']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Username or email already exists');
        }
        
        // Hash password
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password, first_name, last_name, role, student_id)
            VALUES (?, ?, ?, ?, ?, 'student', ?)
        ");
        
        try {
            $this->db->beginTransaction();
            
            // Insert user
            $stmt->execute([
                $userData['username'],
                $userData['email'],
                $hashedPassword,
                $userData['first_name'],
                $userData['last_name'],
                $userData['student_id']
            ]);
            
            $userId = $this->db->lastInsertId();
            
            // Insert student data
            $stmt = $this->db->prepare("
                INSERT INTO student_data (user_id, college_year, section, academic_year)
                VALUES (?, ?, ?, ?)
            
            ");
            
            $stmt->execute([
                $userId,
                $userData['college_year'] ?? null,
                $userData['section'] ?? null,
                $userData['academic_year'] ?? date('Y') . '-' . (date('Y') + 1)
            ]);
            
            $this->db->commit();
            return $userId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception('Registration failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        // Unset all session variables
        $_SESSION = [];
        
        // Delete the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy the session
        session_destroy();
        
        $this->user = null;
    }
    
    /**
     * Require admin access
     * @throws Exception If user is not an admin
     */
    public function requireAdmin() {
        if (!$this->isAdmin()) {
            header('HTTP/1.0 403 Forbidden');
            throw new Exception('Access denied. Admin privileges required.');
        }
        // Update last login timestamp in users table
        $user = $this->getCurrentUser();
        if ($user) {
            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
        }
    }
    
    /**
     * Require admin to be logged in
     */
    public function requireLogin($redirect = 'login.php') {
        if (!$this->isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header("Location: $redirect");
            exit;
        }
    }

    private function loadUser($userId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([$userId]);
            $this->user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$this->user) {
                error_log("User not found or inactive: " . $userId);
                $this->logout();
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error loading user: " . $e->getMessage());
            $this->user = null;
            return false;
        }
    }

    /**
     * Change user password
     * @param int $userId The ID of the user
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return bool True if password was changed successfully, false otherwise
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        // Get current password hash
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            return false;
        }
        
        // Hash new password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$newPasswordHash, $userId]);
    }
}

// Initialize auth
$auth = new Auth($db);