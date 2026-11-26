<?php
/**
 * edit.php — Edit Owned Film Review Controller.
 *
 * Purpose:     Displays the edit form for an existing review and saves updated
 *              review text and star rating.
 * Connections: Uses ReviewController (OOP) methods getForEditing() and updateOwned(),
 *              rendering templates/edit.html.php inside templates/layout.html.php.
 * Security:    Requires active user session, enforces strict author ownership
 *              check ($review['reviewerid'] === $_SESSION['user_id']), validates
 *              rating (1-5) and length, and sanitizes HTML via strip_tags().
 */

session_start(); // Start or resume session to verify user identity and store flash messages
require_once __DIR__ . '/includes/classes/ReviewController.php'; // Load ReviewController class
require_once __DIR__ . '/includes/classes/CsrfHelper.php'; // Load CSRF helper for form protection
$reviewCtrl = new ReviewController(); // Instantiate controller for review lookup and update operations

// Require authenticated user session
if (empty($_SESSION['user_id'])) { // Redirect guests who attempt to access the edit page
    $_SESSION['flash']      = 'Please log in to edit your review.'; // Explain why login is required
    $_SESSION['flash_type'] = 'error'; // Set flash style to error
    header('Location: loginUser.php'); // Send unauthenticated user to login page
    exit; // Stop script execution after redirect
}

// Parse review ID from query string
$id = (int)($_GET['id'] ?? 0); // Cast the query parameter `id` to integer
if (!$id) { // Redirect if review ID is missing or invalid
    header('Location: reviews.php'); // Redirect back to reviews listing
    exit; // Terminate execution after redirect
}

$review = $reviewCtrl->getForEditing($id); // Fetch review record from the database

// Redirect if the requested review does not exist
if (!$review) { // Handle missing review ID
    $_SESSION['flash']      = 'Review not found.'; // Set not-found flash message
    $_SESSION['flash_type'] = 'error'; // Mark flash as error
    header('Location: reviews.php'); // Redirect back to reviews feed
    exit; // Stop script execution after redirect
}

// Enforce ownership: only the original author may edit this review
if ((int)$review['reviewerid'] !== (int)$_SESSION['user_id']) { // Compare review owner ID against logged-in user ID
    $_SESSION['flash']      = 'You can only edit your own reviews.'; // Inform user of ownership restriction
    $_SESSION['flash_type'] = 'error'; // Mark flash as error
    header('Location: film.php?id=' . (int)$review['filmid']); // Redirect back to the film detail page
    exit; // Stop script execution after redirect
}

// Form state variables
$errors       = []; // Store field-level validation error messages
$flashMessage = ''; // Initialize inline alert message
$flashType    = 'success'; // Default inline alert style

// Handle POST update submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Process submitted edits on HTTP POST
    if (!CsrfHelper::verify($_POST['_csrf'] ?? null)) { // Reject forged cross-site submissions
        $flashMessage = 'Security token expired or invalid. Please refresh and try again.';
        $flashType    = 'error';
    } else {
    $reviewText = trim($_POST['review_text'] ?? ''); // Read and trim submitted review HTML/text
    $rating     = (int)($_POST['rating']      ?? 0); // Cast submitted star rating to integer

    // Validate plain-text length and star rating bounds
    $plainText = trim(strip_tags($reviewText)); // Strip HTML tags to measure actual character count
    if (empty($plainText) || mb_strlen($plainText) < 5) { // Require at least 5 visible characters
        $errors['review_text'] = 'Review must be at least 5 characters.'; // Record review text validation error
    }
    if ($rating < 1 || $rating > 5) { // Ensure rating falls within 1 to 5 stars
        $errors['rating'] = 'Please select a star rating (1-5).'; // Record rating validation error
    }

    // Sanitize HTML tags against allowed formatting whitelist
    $reviewText = strip_tags($reviewText, '<p><br><strong><b><em><i><u><ul><ol><li><blockquote><span>'); // Remove unsafe HTML tags while keeping rich-text formatting

    if (empty($errors)) { // Proceed with database update only if validation passed
        if ($reviewCtrl->updateOwned($id, (int)$_SESSION['user_id'], $reviewText, $rating)) { // Persist changes scoped to the review owner
            $_SESSION['flash']      = 'Your review has been updated!'; // Set success flash message
            $_SESSION['flash_type'] = 'success'; // Style flash as success
            header('Location: film.php?id=' . $review['filmid']); // Redirect to the film page to view the updated review
            exit; // Terminate execution after redirect
        }
        $flashMessage = 'Something went wrong. Please try again.'; // Show generic error if update failed
        $flashType    = 'error'; // Style alert as error
    } else {
        $flashMessage = 'Please fix the highlighted errors below.'; // Prompt user to correct validation errors
        $flashType    = 'error'; // Style alert as error
    }
    }
}

$pageTitle = 'Edit Review'; // Set page title for layout template

// Render edit view inside shared layout
ob_start(); // Begin output buffering to capture edit template
require __DIR__ . '/templates/edit.html.php'; // Render the edit review form
$content = ob_get_clean(); // Capture rendered HTML into $content

require __DIR__ . '/templates/layout.html.php'; // Wrap $content inside the shared site layout
