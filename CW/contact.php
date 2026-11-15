<?php
/**
 * contact.php — Contact Us Form & Admin Notification Controller.
 *
 * Purpose:     Allows logged-in users to send support/feedback messages to
 *              site administrators.
 * Connections: Uses includes/DataBaseFunctions.php (addContactMessage) and
 *              renders templates/contact.html.php inside templates/layout.html.php.
 * Security:    Requires active user session, validates subject/message length,
 *              and applies the Post/Redirect/Get (PRG) pattern to prevent
 *              duplicate form submissions.
 */

session_start(); // Start or resume session to verify login and store flash messages
require_once __DIR__ . '/includes/DataBaseFunctions.php'; // Load database helper functions including addContactMessage()

// Require login before accessing the contact form
if (!isset($_SESSION['user_id'])) { // Restrict contact form to authenticated users
    $_SESSION['flash']      = 'Please log in to send us a message.'; // Inform guest why login is required
    $_SESSION['flash_type'] = 'error'; // Set flash type to error
    header('Location: loginUser.php'); // Redirect guest to login page
    exit; // Stop script execution after redirect
}

// Page state and configuration
$pageTitle    = 'Contact'; // Set page title for layout template
$errors       = []; // Collect field-level validation errors
$old          = []; // Store submitted form values for repopulation on error
$flashMessage = ''; // Initialize inline alert message
$flashType    = 'success'; // Default inline alert style

define('ADMIN_EMAIL', 'admin@cine.com'); // Define recipient email address for admin notifications

// Handle POST form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Process contact message only on HTTP POST
    $old = $_POST; // Preserve submitted inputs for form repopulation

    // Pull authenticated sender details from session
    $reviewer_id = $_SESSION['user_id']; // Read logged-in user's ID
    $name        = $_SESSION['user_name']  ?? 'User #' . $reviewer_id; // Fallback display name if session key is missing
    $email       = $_SESSION['user_email'] ?? 'unknown@cine.com'; // Fallback email if session key is missing

    // Trim submitted subject and message body
    $subject = trim($_POST['subject'] ?? ''); // Read and trim message subject
    $content = trim($_POST['content'] ?? ''); // Read and trim message body

    // Validate required fields
    if (empty($subject)) { // Require a non-empty subject line
        $errors['subject'] = 'Please enter a subject.'; // Record subject validation error
    }
    if (empty($content) || mb_strlen($content) < 10) { // Enforce minimum 10-character message body
        $errors['content'] = 'Message must be at least 10 characters.'; // Record message body validation error
    }

    if (empty($errors)) { // Proceed only when validation succeeds
        // Persist contact message to the database
        $saved = addContactMessage($reviewer_id, $subject, $content); // Insert message into contact_us table

        if ($saved) { // Send email notification and redirect if DB insert succeeded
            // Build plain-text email notification for the administrator
            $headers = implode("\r\n", [ // Join SMTP headers with CRLF
                'From: ' . $name . ' <' . $email . '>', // Set sender header
                'Reply-To: ' . $email, // Allow admin to reply directly to user
                'Content-Type: text/plain; charset=UTF-8', // Specify UTF-8 plain text encoding
            ]);

            $body = "New message from Cine contact form (Logged-in User).\n\n" // Compose notification email body
                . "Reviewer ID: " . $reviewer_id . "\n" // Include sender's reviewer ID
                . "Name:        " . $name        . "\n" // Include sender's name
                . "Email:       " . $email       . "\n" // Include sender's email
                . "Subject:     " . $subject     . "\n\n" // Include message subject
                . "Message:\n"   . $content; // Include full message content

            // Attempt mail delivery (suppressed if local SMTP is unconfigured)
            @mail(ADMIN_EMAIL, '[Cine] ' . $subject, $body, $headers); // Dispatch notification email without failing if local mail server is absent

            // Redirect using PRG pattern with success flash
            $_SESSION['flash']      = 'Your message has been sent!'; // Store success flash message
            $_SESSION['flash_type'] = 'success'; // Style flash as success
            header('Location: contact.php'); // Redirect to prevent duplicate form submission on refresh
            exit; // Stop script execution after redirect
        }

        $flashMessage = 'Something went wrong. Please try again.'; // Show error if database insert failed
        $flashType    = 'error'; // Style alert as error
    } else {
        $flashMessage = 'Please fix the highlighted errors below.'; // Prompt user to fix validation errors
        $flashType    = 'error'; // Style alert as error
    }
}

// Retrieve one-time flash message from session if none set during POST
if (empty($flashMessage)) { // Only read session flash when no inline POST error is active
    $flashMessage = $_SESSION['flash']      ?? ''; // Read pending flash message
    $flashType    = $_SESSION['flash_type'] ?? 'success'; // Default flash type to 'success'
    unset($_SESSION['flash'], $_SESSION['flash_type']); // Clear flash keys after reading
}

// Render contact view inside shared layout
ob_start(); // Start output buffering to capture contact template
require __DIR__ . '/templates/contact.html.php'; // Render the contact form view
$content = ob_get_clean(); // Capture rendered HTML into $content

require __DIR__ . '/templates/layout.html.php'; // Wrap $content inside the shared site layout
