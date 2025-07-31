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
                INSERT INTO student_data (user_id, grade_level, section, academic_year)
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $userData['grade_level'] ?? null,
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
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$userId]);
        $this->user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Initialize auth
$auth = new Auth($db);