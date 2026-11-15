<?php
/**
 * loginUser.php — Public User Login Controller.
 *
 * Purpose:     Authenticates registered reviewers by email and password and
 *              initializes their session state.
 * Connections: Uses ReviewerController (OOP) -> UserRepository, rendering
 *              templates/loginUser.html.php inside templates/layout.html.php.
 * Security:    Redirects already-authenticated users; delegates password
 *              verification (password_verify) and suspension checks to
 *              ReviewerController::login().
 */

session_start(); // Start or resume session to manage login state and flash alerts
require_once __DIR__ . '/includes/classes/ReviewerController.php'; // Load ReviewerController class
$reviewerCtrl = new ReviewerController(); // Instantiate controller for credential verification

// Redirect already-logged-in users to homepage
if (isset($_SESSION['user_id'])) { // Skip login page if user is already authenticated
    header('Location: index.php'); // Redirect to homepage
    exit; // Stop execution after redirect
}

// Initialize page title and one-time flash notification
$pageTitle    = 'Login'; // Set page title for layout template
$flashMessage = $_SESSION['flash']      ?? ''; // Retrieve any pending flash message from session
$flashType    = $_SESSION['flash_type'] ?? 'success'; // Default flash type to 'success'
unset($_SESSION['flash'], $_SESSION['flash_type']); // Clear flash keys after reading

// Display prompt when redirected from Cine AI chat widget
if ($flashMessage === '' && ($_GET['notice'] ?? '') === 'ai_chat') { // Check if user was sent here by the AI chat gate
    $flashMessage = 'Please log in to chat with Cine AI.'; // Prompt user to sign in before using AI chat
    $flashType    = 'error'; // Style notice as an error/warning banner
}

// Handle POST login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Process login form on HTTP POST
    $email    = trim($_POST['email'] ?? ''); // Read and trim submitted email address
    $password = $_POST['password'] ?? ''; // Read submitted password

    $user = $reviewerCtrl->login($email, $password); // Verify email, password hash, and active account status
    if ($user) { // Proceed if credentials are valid and account is not suspended
        // Populate session variables for authenticated browsing
        $_SESSION['user_id']     = $user['id']; // Store authenticated reviewer ID
        $_SESSION['user_name']   = $user['fullname']; // Store display name for header/navigation
        $_SESSION['user_email']  = $user['email']; // Store email for profile/contact prefill
        $_SESSION['user_avatar'] = $user['avatar'] ?? ''; // Store avatar path or empty string

        header('Location: index.php'); // Redirect authenticated user to homepage
        exit; // Terminate execution after redirect
    } else {
        $flashMessage = 'Invalid email or password.'; // Show generic error to avoid leaking account existence
        $flashType    = 'error'; // Style alert as error
    }
}

// Render login form inside shared layout
ob_start(); // Begin output buffering to capture login view
require __DIR__ . '/templates/loginUser.html.php'; // Render the login form template
$content = ob_get_clean(); // Capture rendered HTML into $content
require __DIR__ . '/templates/layout.html.php'; // Wrap $content inside the shared site layout
