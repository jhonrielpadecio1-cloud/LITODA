<?php
// auth_check.php - Include this file on protected pages
session_start();

function requireLogin($redirectPath = '../login/login.php') {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        $_SESSION['login_error'] = 'Please log in to access this page.';
        header('Location: ' . $redirectPath);
        exit();
    }
    
    // Optional: Check if session is expired (implement session timeout)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
        // Session expired after 1 hour of inactivity
        session_destroy();
        $_SESSION = array();
        $_SESSION['login_error'] = 'Your session has expired. Please log in again.';
        header('Location: ' . $redirectPath);
        exit();
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}

function getCurrentUser() {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        return [
            'id' => $_SESSION['admin_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'login_time' => $_SESSION['login_time'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null
        ];
    }
    return null;
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function updateLastActivity() {
    if (isLoggedIn()) {
        $_SESSION['last_activity'] = time();
    }
}

function getSessionInfo() {
    return [
        'session_id' => session_id(),
        'logged_in' => isLoggedIn(),
        'user' => getCurrentUser(),
        'session_start' => $_SESSION['login_time'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null
    ];
}
?>