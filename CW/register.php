<?php
/**
 * register.php — New User Registration Controller.
 *
 * Purpose:     Validates registration inputs and creates new reviewer accounts.
 * Connections: Uses ReviewerController::register() (OOP) -> UserRepository,
 *              rendering templates/register.html.php inside templates/layout.html.php.
 * Security:    Redirects already-logged-in users; enforces minimum 6-character
 *              password length; password hashing is handled by UserRepository.
 */

session_start(); // Start or resume session to check authentication state and store flash messages
require_once __DIR__ . '/includes/classes/ReviewerController.php'; // Load the ReviewerController class
$reviewerCtrl = new ReviewerController(); // Instantiate controller for reviewer registration logic

// Redirect already-authenticated users to homepage
if (isset($_SESSION['user_id'])) { // Prevent logged-in users from registering a new account
    header('Location: index.php'); // Redirect authenticated user to homepage
    exit; // Halt script execution after redirect
}

$pageTitle    = 'Register'; // Set page title for the layout header
$flashMessage = ''; // Initialize feedback message as empty
$flashType    = 'error'; // Default feedback alert style to error

// Handle POST registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Process form only on HTTP POST requests
    $name     = trim($_POST['fullname'] ?? ''); // Read and trim submitted full name
    $email    = trim($_POST['email'] ?? ''); // Read and trim submitted email address
    $password = $_POST['password'] ?? ''; // Read raw password input (not trimmed to preserve intentional spaces)

    // Validate required fields and minimum password length
    if (!$name || !$email || strlen($password) < 6) { // Enforce non-empty name/email and >= 6 char password
        $flashMessage = 'All fields required. Password minimum 6 characters.'; // Set validation error message
    } else {
        $result = $reviewerCtrl->register($name, $email, $password); // Attempt to create the new reviewer account
        if ($result === true) { // Check if registration succeeded
            $_SESSION['flash']      = 'Registration successful! Please login.'; // Store success flash for login page
            $_SESSION['flash_type'] = 'success'; // Mark flash alert as success
            header('Location: loginUser.php'); // Redirect to login page after successful registration
            exit; // Terminate execution after redirect
        } else {
            $flashMessage = $result; // Display specific error string returned by ReviewerController
        }
    }
}

// Render registration form inside shared layout
ob_start(); // Start output buffering to capture the registration template
require __DIR__ . '/templates/register.html.php'; // Render the registration form view
$content = ob_get_clean(); // Save rendered HTML into $content
require __DIR__ . '/templates/layout.html.php'; // Render $content inside the shared site layout
