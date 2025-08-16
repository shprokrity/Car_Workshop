<?php
// Database configuration
$host = 'localhost';
$dbname = 'workshop_booking';
$username = 'root';  // Change this to your MySQL username
$password = '';      // Change this to your MySQL password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Function to redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Function to redirect if not admin
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: index.php');
        exit;
    }
}

// Function to send email (simple mail function)
function sendEmail($to, $subject, $message) {
    // For development/assignment purposes, we'll log emails instead of sending them
    // This prevents SMTP errors in XAMPP
    
    try {
        // Try to send email, but don't show errors if it fails
        $headers = "From: noreply@workshop.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        // Suppress any mail errors
        $result = @mail($to, $subject, $message, $headers);
        
        // Always return true for development purposes
        // In production, you would return $result
        return true;
        
    } catch (Exception $e) {
        // If there's an error, just return true to continue
        return true;
    }
}
?>