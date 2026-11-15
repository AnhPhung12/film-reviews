<?php
/**
 * add_review.php — Create New Film Review Entry Point.
 *
 * Purpose:     Displays the review submission form and persists new film reviews
 *              submitted by authenticated users.
 * Connections: Uses ReviewController (OOP) and DataBaseFunctions helpers
 *              (getAllGenres, getAllFilms), rendering templates/add_review.html.php
 *              inside templates/layout.html.php.
 * Security:    Enforces session authentication (redirects guests to loginUser.php),
 *              validates rating bounds (1-5) and text length, and sanitizes HTML
 *              with a strict tag whitelist via strip_tags().
 */

session_start(); // Start session to access user identity and flash messages
require_once __DIR__ . '/includes/classes/ReviewController.php'; // Load ReviewController for persisting reviews
$reviewCtrl = new ReviewController(); // Instantiate review controller

// Redirect unauthenticated visitors to the login page
if (empty($_SESSION['user_id'])) { // Require active user session before writing a review
    $_SESSION['flash']      = 'Please log in to write a review.'; // Inform guest why login is needed
    $_SESSION['flash_type'] = 'error'; // Set flash style to error
    header('Location: loginUser.php'); // Redirect guest to login page
    exit; // Stop script execution after redirect
}

// Page configuration and dropdown datasets
$pageTitle       = 'Write a Review'; // Set page title for layout header
$genres          = getAllGenres(); // Fetch all genres for optional filtering
$films           = getAllFilms();  // Fetch all films for the film selector
$preselectedFilm = $_GET['film'] ?? ''; // Pre-select film when linked from film.php
$errors          = []; // Collect field-level validation errors
$old             = []; // Store submitted values to repopulate form on validation failure
$flashMessage    = ''; // Initialize inline alert message
$flashType       = 'success'; // Default inline alert style

// Handle POST form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Process review creation only on POST requests
    $old = $_POST; // Preserve submitted inputs for form repopulation on error

    // Retrieve authenticated reviewer ID from session
    $reviewer_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0; // Resolve logged-in user's ID

    // Collect and trim submitted form fields
    $filmId        = (int)($_POST['filmid']        ?? 0); // Cast selected film ID to integer
    $reviewerName  = trim($_POST['reviewer_name']  ?? ''); // Trim submitted reviewer display name
    $reviewerEmail = trim($_POST['reviewer_email'] ?? ''); // Trim submitted reviewer email address
    $reviewText    = trim($_POST['review_text']    ?? ''); // Trim submitted review body HTML/text
    $rating        = (int)($_POST['rating']        ?? 0); // Cast star rating to integer

    // --- Input Validation ---
    if (!$reviewer_id) { // Verify session user ID is present
        $errors['auth'] = 'You must be logged in to post a review.'; // Record authentication error
    }
    if (!$filmId) { // Ensure a valid film was selected
        $errors['filmid'] = 'Please select a film.'; // Record film selection error
    }
    if (empty($reviewerName)) { // Ensure reviewer name is provided
        $errors['reviewer_name'] = 'Name is required.'; // Record name validation error
    }
    if (empty($reviewerEmail) || !filter_var($reviewerEmail, FILTER_VALIDATE_EMAIL)) { // Validate email format
        $errors['reviewer_email'] = 'A valid email is required.'; // Record email validation error
    }

    // Strip HTML tags temporarily to measure actual plain-text character length
    $plainText = trim(strip_tags($reviewText)); // Extract plain text without formatting tags
    if (empty($plainText) || mb_strlen($plainText) < 5) { // Require at least 5 visible characters
        $errors['review_text'] = 'Review must be at least 5 characters.'; // Record review text length error
    }
    if ($rating < 1 || $rating > 5) { // Enforce rating range between 1 and 5
        $errors['rating'] = 'Please select a star rating (1-5).'; // Record rating validation error
    }

    // Sanitize rich-text review content to an allowed formatting tag whitelist
    $reviewText = strip_tags($reviewText, '<p><br><strong><b><em><i><u><ul><ol><li><blockquote><span>'); // Strip disallowed HTML/script tags

    if (empty($errors)) { // Attempt database insert only when all validations pass
        try {
            // Persist review via ReviewController using prepared statements
            if ($reviewCtrl->create((int)$filmId, (int)$reviewer_id, $reviewText, $rating)) { // Insert new review record
                $_SESSION['flash']      = 'Your review has been published!'; // Set success flash message
                $_SESSION['flash_type'] = 'success'; // Mark flash as success
                header('Location: reviews.php'); // Redirect to reviews feed (PRG pattern)
                exit; // Stop script execution after redirect
            } else {
                $flashMessage = 'Could not save your review. Please try again.'; // Show generic save failure message
                $flashType    = 'error'; // Style alert as error
            }
        } catch (Exception $e) { // Catch unexpected PDO/database exceptions
            $flashMessage = 'Database Error: ' . $e->getMessage(); // Surface database error message
            $flashType    = 'error'; // Style alert as error
        }
    } else {
        $flashMessage = 'Please fix the highlighted errors below.'; // Prompt user to fix form validation errors
        $flashType    = 'error'; // Style alert as error
    }
}

// Render view inside the shared site layout
ob_start(); // Begin output buffering to capture add-review template
require __DIR__ . '/templates/add_review.html.php'; // Render the review submission form
$content = ob_get_clean(); // Capture rendered HTML into $content
require __DIR__ . '/templates/layout.html.php'; // Wrap $content inside the shared site layout
