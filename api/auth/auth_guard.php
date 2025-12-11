<?php
/**
 * Authentication Guard
 * File: api/auth/auth_guard.php
 * 
 * Include this file at the top of every protected admin page
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['logged_in']) && 
           $_SESSION['logged_in'] === true && 
           isset($_SESSION['admin_id']) &&
           isset($_SESSION['username']);
}

/**
 * Check if session has expired
 */
function isSessionExpired() {
    if (!isset($_SESSION['last_activity'])) {
        return true;
    }
    
    $inactive_time = time() - $_SESSION['last_activity'];
    return $inactive_time > SESSION_TIMEOUT;
}

/**
 * Update last activity timestamp
 */
function updateActivity() {
    $_SESSION['last_activity'] = time();
}

/**
 * Destroy session and redirect to login
 */
function forceLogout($reason = 'Please log in to continue.') {
    // Store the original page they tried to access
    $current_page = $_SERVER['REQUEST_URI'];
    
    // Clear all session data
    $_SESSION = array();
    
    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy the session
    session_destroy();
    
    // Start new session for error message
    session_start();
    $_SESSION['login_error'] = $reason;
    $_SESSION['redirect_after_login'] = $current_page;
    
    // Redirect to login page - adjust path based on your structure
    header('Location: ../../pages/login/login.php');
    exit();
}

/**
 * Main authentication check
 * Call this at the top of every protected page
 */
function requireAuth() {
    // Check if user is authenticated
    if (!isAuthenticated()) {
        forceLogout('Please logged in to access this page.');
    }
    
    // Check if session has expired
    if (isSessionExpired()) {
        forceLogout('Your session has expired. Please log in again.');
    }
    
    // Update last activity time
    updateActivity();
}

// Automatically protect the page when this file is included
requireAuth();
?>