<?php

/**
 * deletereview.php — Delete Owned Review Action Handler.
 *
 * Purpose:     Deletes a review belonging to the currently logged-in user and
 *              redirects back to a safe referrer page with a flash status.
 * Connections: Uses ReviewController (OOP) methods getForEditing() and deleteOwned().
 * Security:    Enforces session login, verifies review ownership before deletion,
 *              and validates the `ref` redirect target against an allowlist regex
 *              to prevent open-redirect vulnerabilities.
 */

session_start(); // Resume session to verify logged-in user and set flash feedback
require_once __DIR__ . '/includes/classes/ReviewController.php'; // Load ReviewController class
$reviewCtrl = new ReviewController(); // Instantiate controller for review lookup and deletion

// Require authenticated user session
if (empty($_SESSION['user_id'])) { // Block unauthenticated deletion attempts
    $_SESSION['flash']      = 'Please log in to delete your review.'; // Inform user why they were redirected
    $_SESSION['flash_type'] = 'error'; // Style flash alert as an error
    header('Location: loginUser.php'); // Redirect guest to login page
    exit; // Stop script execution immediately
}

$id  = (int)($_GET['id']  ?? 0); // Cast review ID from query string to integer
$ref = $_GET['ref'] ?? 'reviews.php'; // Read optional return URL, defaulting to reviews.php

// Restrict redirect target to known internal pages to prevent open redirects
if (!preg_match('#^(/film/CW/)?(reviews\.php|film\.php|profile\.php|index\.php)(\?.*)?$#i', $ref)) { // Validate referrer against allowlist pattern
    $ref = 'reviews.php'; // Fall back to safe internal page if referrer is untrusted
}

// Validate review existence and ownership before deleting
if ($id > 0) { // Ensure review ID is a positive integer
    $review = $reviewCtrl->getForEditing($id); // Fetch review record to check existence and owner
    if (!$review) { // Handle case where review ID does not exist
        $_SESSION['flash']      = 'Review not found.'; // Set not-found error flash
        $_SESSION['flash_type'] = 'error'; // Mark flash as error
    } elseif ((int)$review['reviewerid'] !== (int)$_SESSION['user_id']) { // Enforce ownership authorization check
        $_SESSION['flash']      = 'You can only delete your own reviews.'; // Set unauthorized error flash
        $_SESSION['flash_type'] = 'error'; // Mark flash as error
    } else {
        $ok                     = $reviewCtrl->deleteOwned($id, (int)$_SESSION['user_id']); // Delete the review scoped to the current user
        $_SESSION['flash']      = $ok ? 'Review deleted.' : 'Could not delete review.'; // Set outcome message
        $_SESSION['flash_type'] = $ok ? 'success' : 'error'; // Set flash style based on deletion result
    }
} else {
    $_SESSION['flash']      = 'Invalid review ID.'; // Set error flash for non-positive ID
    $_SESSION['flash_type'] = 'error'; // Mark flash as error
}

header('Location: ' . $ref); // Redirect back to the validated internal referrer page
exit; // Terminate script execution after redirect
