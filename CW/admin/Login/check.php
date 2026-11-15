<?php
/**
 * admin/Login/check.php — Admin Credential Verification Handler.
 *
 * Purpose:     Validates submitted admin login credentials and establishes the
 *              authenticated admin session.
 * Connections: Receives POST submissions from admin/Login/login.php and
 *              redirects to admin/index.php on success or login.php?error=1 on failure.
 * Security:    Enforces POST-only submission before checking credentials.
 */

session_start(); // Start session so we can persist the admin login flag

// Define administrator credentials
define('ADMIN_USER', 'phung'); // Hardcoded admin username for coursework demo
define('ADMIN_PASS', 'phung123'); // Hardcoded admin password for coursework demo

// Reject non-POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { // Ensure credentials are only accepted via POST
    header('Location: login.php'); // Redirect direct GET requests back to the login form
    exit; // Stop script execution after redirect
}

// Retrieve and trim submitted credentials
$user = trim($_POST['username'] ?? ''); // Read and trim submitted admin username
$pass = trim($_POST['password'] ?? ''); // Read and trim submitted admin password

// Validate credentials against defined admin constants
if ($user === ADMIN_USER && $pass === ADMIN_PASS) { // Compare submitted credentials with admin constants
    $_SESSION['admin_logged_in'] = true; // Mark session as authenticated administrator
    header('Location: ../index.php'); // Redirect to the admin dashboard
    exit; // Stop script execution after redirect
}

// Redirect back with error flag when authentication fails
header('Location: login.php?error=1'); // Return to login page with error query flag
exit; // Stop script execution after redirect
