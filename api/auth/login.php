<?php
session_start();
require_once '../../database/db.php';

// Function to log login attempts
function logLoginAttempt($conn, $admin_id, $username, $status, $failure_reason = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $stmt = $conn->prepare("INSERT INTO login_logs (admin_id, username, status, ip_address, user_agent, failure_reason) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $admin_id, $username, $status, $ip_address, $user_agent, $failure_reason);
    $stmt->execute();
    $stmt->close();
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../pages/login/login.php');
    exit();
}

// Get form data
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validate input
if (empty($username) || empty($password)) {
    logLoginAttempt($conn, null, $username, 'Failed', 'Empty credentials');
    $_SESSION['login_error'] = 'Please fill in all fields.';
    $_SESSION['old_username'] = $username;
    header('Location: ../../pages/login/login.php');
    exit();
}

try {
    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, username, password, firstname, middlename, lastname FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Check if password is bcrypt hashed (starts with $2y$)
        $is_hashed = (strpos($user['password'], '$2y$') === 0);
        
        // Verify password - supports both plain text and bcrypt
        $password_valid = false;
        if ($is_hashed) {
            // Use bcrypt verification for hashed passwords
            $password_valid = password_verify($password, $user['password']);
        } else {
            // Use plain text comparison for non-hashed passwords
            $password_valid = ($password === $user['password']);
        }
        
        if ($password_valid) {
            // Password is correct, log successful login
            logLoginAttempt($conn, $user['id'], $user['username'], 'Success');
            
            // Start session
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['middlename'] = $user['middlename'];
            $_SESSION['lastname'] = $user['lastname'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            // Clear old username if exists
            if (isset($_SESSION['old_username'])) {
                unset($_SESSION['old_username']);
            }
            
            // Redirect to dashboard on successful login
            header('Location: ../../pages/dashboard/dashboard.php');
            exit();
        } else {
            // Invalid password, log failed attempt
            logLoginAttempt($conn, $user['id'], $user['username'], 'Failed', 'Invalid password');
            $_SESSION['login_error'] = 'Invalid username or password.';
            $_SESSION['old_username'] = $username;
        }
    } else {
        // User not found, log failed attempt
        logLoginAttempt($conn, null, $username, 'Failed', 'User not found');
        $_SESSION['login_error'] = 'Invalid username or password.';
        $_SESSION['old_username'] = $username;
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Login error: " . $e->getMessage());
    logLoginAttempt($conn, null, $username, 'Failed', 'System error: ' . $e->getMessage());
    $_SESSION['login_error'] = 'An error occurred. Please try again.';
    $_SESSION['old_username'] = $username;
}

$conn->close();

// Redirect back to login page with error
header('Location: ../../pages/login/login.php');
exit();
?>