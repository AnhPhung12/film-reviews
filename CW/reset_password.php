<?php
/**
 * reset_password.php — Token-Verified Password Reset Controller.
 *
 * Purpose:     Validates a password reset token from the URL/form and allows
 *              the user to set a new password.
 * Connections: Uses UserRepository methods findValidResetUser() and
 *              resetPasswordWithToken(), rendering templates/reset_password.html.php.
 * Security:    Redirects logged-in users; verifies token validity and expiry
 *              against SHA-256 hashed tokens in the database; enforces password
 *              length (>= 6) and confirmation match.
 */

session_start(); // Start or resume session to check login state and set flash messages
require_once __DIR__ . '/includes/DataBaseFunctions.php'; // Load UserRepository and database helpers

// Redirect already-authenticated users to homepage
if (isset($_SESSION['user_id'])) { // Logged-in users should change passwords via settings instead
    header('Location: index.php'); // Redirect authenticated user to homepage
    exit; // Stop script execution after redirect
}

$pageTitle    = 'Reset Password'; // Set page title for layout template
$flashMessage = ''; // Initialize alert message as empty
$flashType    = 'error'; // Default alert style to error
$token        = trim($_GET['token'] ?? $_POST['token'] ?? ''); // Read reset token from URL query or hidden POST input

// Validate token before showing or processing the reset form
$repo       = new UserRepository(); // Instantiate repository for token lookup and password updates
$resetUser  = $token !== '' ? $repo->findValidResetUser($token) : null; // Verify token exists and has not expired
$tokenValid = $resetUser !== null; // Boolean flag controlling whether the reset form is rendered

// Handle POST password update when token is valid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) { // Only process password reset if token is valid
    $password        = $_POST['password'] ?? ''; // Read new password input
    $confirmPassword = $_POST['confirm_password'] ?? ''; // Read password confirmation input

    if (strlen($password) < 6) { // Enforce minimum 6-character password length
        $flashMessage = 'Password must be at least 6 characters.'; // Set length validation error
    } elseif ($password !== $confirmPassword) { // Ensure both password fields match
        $flashMessage = 'Passwords do not match.'; // Set mismatch validation error
    } elseif ($repo->resetPasswordWithToken($token, $password)) { // Hash new password, save it, and clear the token
        $_SESSION['flash']      = 'Your password has been reset! Please log in with your new password.'; // Store success flash
        $_SESSION['flash_type'] = 'success'; // Mark flash as success
        header('Location: loginUser.php'); // Redirect user to login page
        exit; // Stop script execution after redirect
    } else {
        $tokenValid   = false; // Invalidate form view if token expired or failed during update
        $flashMessage = 'This password reset link is invalid or has expired.'; // Show token failure message
    }
}

// Render reset password view inside shared layout
ob_start(); // Start output buffering to capture the reset password template
require __DIR__ . '/templates/reset_password.html.php'; // Render the password reset view
$content = ob_get_clean(); // Save rendered HTML into $content
require __DIR__ . '/templates/layout.html.php'; // Wrap $content inside the shared site layout
