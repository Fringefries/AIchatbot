<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

// Start or resume session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get database connection
$db = getDBConnection();

// Get the message and session ID from the request
$message = trim($_POST['message'] ?? '');
$sessionId = $_POST['session_id'] ?? session_id();

// Get user IP and user agent for tracking
$userIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

// Simple response logic (you can replace this with more sophisticated AI)
$response = getChatbotResponse($message);

// Save the conversation
if (!empty($message)) {
    try {
        $stmt = $db->prepare("INSERT INTO inquiries (session_id, user_ip, user_agent, message, response, is_resolved) 
                            VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->execute([$sessionId, $userIp, $userAgent, $message, $response]);
    } catch (Exception $e) {
        // Log error but don't show to user
        error_log("Error saving inquiry: " . $e->getMessage());
    }
}
 
// Return the response
echo json_encode(['reply' => $response]);

/**
 * Simple rule-based chatbot response generator
 */
function getChatbotResponse($message) {
    $message = strtolower(trim($message));
    
    // Common greetings
    if (preg_match('/(hi|hello|hey|greetings|good\s*(morning|afternoon|evening|day))/', $message)) {
        return "Hello! How can I help you today?";
    }
    
    // Convert both message and search strings to lowercase for case-insensitive matching
    $message = strtolower($message);
    
    // Common questions
    if (strpos($message, 'what are the school hours') !== false) {
        return "Monday to Saturday kahit ano oras mo gusto.";
    }
    
    if (strpos($message, 'how do i enroll') !== false || strpos($message, 'how to enroll') !== false) {
        return "To enroll as magdala ka lang goodmoral pati single tita.";
    }
    
    if (strpos($message, 'when are the examination dates') !== false) {
        return "depende kung kelan ka available.";
    }
    
    if (strpos($message, 'how can i contact the school') !== false) {
        return "You can contact us at: \n- Email: alegrepatrick9098@gmail.com\n- Phone: 096969696969\n- Address: 10th ave cal city";
    }
    
    if (strpos($message, 'how can i request my report card') !== false || strpos($message, 'academic records') !== false) {
        return "punta ka registrar.";
    }
    
    if (strpos($message, 'thank you') !== false) {
        return "You're welcome! Is there anything else I can help you with?";
    }
    
    if (strpos($message, 'bye') !== false || strpos($message, 'goodbye') !== false) {
        return "Goodbye! Have a great day!";
    }
    
    // Default response
    return "I'm sorry, I didn't understand that. Could you please rephrase your question or contact the school office for assistance?";
}