-- Disable foreign key checks temporarily to allow dropping tables in any order
SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables if they exist (in reverse order of dependency)
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `student_assignments`;
DROP TABLE IF EXISTS `assignments`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `grades`;
DROP TABLE IF EXISTS `student_data`;
DROP TABLE IF EXISTS `chat_messages`;
DROP TABLE IF EXISTS `chat_feedback`;
DROP TABLE IF EXISTS `inquiries`;
DROP TABLE IF EXISTS `document_requests`;
DROP TABLE IF EXISTS `document_types`;
DROP TABLE IF EXISTS `faqs`;
DROP TABLE IF EXISTS `inquiry_categories`;
DROP TABLE IF EXISTS `class_schedule`;
DROP TABLE IF EXISTS `user_sessions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `system_settings`;
DROP TABLE IF EXISTS `admins`;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- System Settings (needed early for any system configuration)
CREATE TABLE system_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT,
  description TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Users Table with enhanced fields (combines users and admins from simplified schema)
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  role ENUM('student', 'parent', 'teacher', 'admin') NOT NULL,
  student_id VARCHAR(50) NULL,
  parent_of_student_id VARCHAR(50) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  last_login DATETIME DEFAULT NULL,
  reset_token VARCHAR(64) DEFAULT NULL,
  reset_token_expires DATETIME DEFAULT NULL,
  is_active BOOLEAN DEFAULT TRUE
);

-- User Sessions
CREATE TABLE user_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  session_token VARCHAR(255) NOT NULL,
  ip_address VARCHAR(45),
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Inquiry Categories
CREATE TABLE inquiry_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  is_active BOOLEAN DEFAULT TRUE
);

-- Inquiries table (combined from both schemas)
CREATE TABLE inquiries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  session_id VARCHAR(100) NOT NULL,
  category_id INT,
  message TEXT NOT NULL,
  response TEXT NOT NULL,
  status ENUM('open', 'in_progress', 'resolved', 'escalated') DEFAULT 'open',
  assigned_to INT NULL,
  priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
  user_ip VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  is_resolved TINYINT(1) NOT NULL DEFAULT '0',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (category_id) REFERENCES inquiry_categories(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  KEY `session_id` (`session_id`),
  KEY `is_resolved` (`is_resolved`)
);

-- FAQ Management
CREATE TABLE faqs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question TEXT NOT NULL,
  answer TEXT NOT NULL,
  category_id INT,
  keywords TEXT,
  is_active BOOLEAN DEFAULT TRUE,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES inquiry_categories(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Document Types
CREATE TABLE document_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  required_fields JSON,
  processing_days INT DEFAULT 1,
  is_active BOOLEAN DEFAULT TRUE
);

-- Document Requests
CREATE TABLE document_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  document_type_id INT NOT NULL,
  request_data JSON,
  status ENUM('pending', 'processing', 'approved', 'rejected', 'ready_for_pickup', 'completed') DEFAULT 'pending',
  admin_notes TEXT,
  rejection_reason TEXT,
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  processed_at TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (document_type_id) REFERENCES document_types(id)
);

-- Class Schedule
CREATE TABLE class_schedule (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  subject VARCHAR(100) NOT NULL,
  day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday') NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  room_number VARCHAR(20),
  teacher_name VARCHAR(100),
  academic_year VARCHAR(20) NOT NULL,
  term VARCHAR(20) NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Chat Messages (from inquiries)
CREATE TABLE chat_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  inquiry_id INT NOT NULL,
  sender_id INT,
  message TEXT NOT NULL,
  is_from_user BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Student Data
CREATE TABLE student_data (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  grade_level VARCHAR(20),
  section VARCHAR(20),
  academic_year VARCHAR(20),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Grades
CREATE TABLE grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  subject VARCHAR(100) NOT NULL,
  grade VARCHAR(5) NOT NULL,
  term VARCHAR(20) NOT NULL,
  academic_year VARCHAR(20) NOT NULL,
  comments TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Attendance
CREATE TABLE attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  date DATE NOT NULL,
  status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
  subject VARCHAR(100),
  notes TEXT,
  recorded_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Homework/Assignments
CREATE TABLE assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  subject VARCHAR(100) NOT NULL,
  due_date DATETIME NOT NULL,
  assigned_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Student Assignments (submissions)
CREATE TABLE student_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT NOT NULL,
  student_id INT NOT NULL,
  submission TEXT,
  submitted_at TIMESTAMP NULL,
  grade VARCHAR(5) NULL,
  feedback TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  is_read BOOLEAN DEFAULT FALSE,
  type ENUM('info', 'warning', 'success', 'error') DEFAULT 'info',
  action_url VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Chat Feedback
CREATE TABLE chat_feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  inquiry_id INT NOT NULL,
  rating TINYINT CHECK (rating BETWEEN 1 AND 5),
  feedback TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE
);

-- Insert default inquiry categories
INSERT INTO inquiry_categories (name, description) VALUES
('Admissions', 'Questions about admission process, requirements, and deadlines'),
('Academics', 'Course information, curriculum, and academic policies'),
('Fees & Payments', 'Tuition fees, payment methods, and financial aid'),
('Examinations', 'Exam schedules, results, and procedures'),
('Student Services', 'Counseling, career guidance, and student support'),
('Facilities', 'Library, labs, sports, and other facilities'),
('General', 'Other general inquiries');

-- Insert default admin user (password: admin123 - should be hashed in production)
INSERT INTO users (username, password, email, first_name, last_name, role, is_active) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@school.edu', 'System', 'Administrator', 'admin', TRUE);
