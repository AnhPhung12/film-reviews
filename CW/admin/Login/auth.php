<?php
/**
 * admin/Login/auth.php — Admin Session Authentication Guard.
 *
 * Purpose:     Protects all admin pages by verifying an active admin login session.
 * Connections: Included at the top of every admin controller (index.php, films.php,
 *              genres.php, reviews.php, reviewers.php, messages.php).
 * Security:    Redirects unauthenticated requests to Login/login.php and exits
 *              immediately to prevent unauthorized access.
 */

// Start the session if one isn't already active
if (session_status() === PHP_SESSION_NONE) { // Check whether a PHP session has already been started
    session_start(); // Initialize session to access admin authentication state
}

// Redirect to admin login page if not authenticated
if (empty($_SESSION['admin_logged_in'])) { // Verify the admin session flag is set and truthy
    header('Location: ' . dirname($_SERVER['PHP_SELF']) . '/Login/login.php'); // Redirect unauthenticated visitor to admin login
    exit; // Halt script execution immediately to protect the admin page
}

// Ensure admin profile session metadata is populated for existing sessions
$_SESSION['admin_username'] ??= 'phung';
$_SESSION['admin_email']    ??= 'jy261205@gmail.com';
$_SESSION['admin_login_at'] ??= date('c');
