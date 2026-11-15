<?php
/**
 * film.php — Single Film Detail & Reviews Page.
 *
 * Purpose:     Displays metadata, poster styling, and user reviews for a
 *              specific film identified by `?id=`.
 * Connections: Uses includes/DataBaseFunctions.php (getFilmById, getReviewsByFilm,
 *              getPosterData) and renders templates/film.html.php inside
 *              templates/layout.html.php.
 * Security:    Casts `?id` to integer and redirects invalid or missing film IDs
 *              safely to reviews.php.
 */

session_start(); // Start or resume the session to read flash messages and user state
require_once __DIR__ . '/includes/DataBaseFunctions.php'; // Load database query helpers and poster utilities

// Parse and validate film ID from query string
$id = (int)($_GET['id'] ?? 0); // Cast the query parameter `id` to an integer to prevent injection
if (!$id) { // Redirect if the film ID is missing or zero
    header('Location: reviews.php'); // Send user back to the reviews listing
    exit; // Stop execution after redirect
}

// Fetch film record with genre metadata; redirect if not found
$film = getFilmById($id); // Query the database for the requested film record
if (!$film) { // Redirect if no matching film exists in the database
    header('Location: reviews.php'); // Send user back to the reviews listing
    exit; // Stop execution after redirect
}

// Fetch associated reviews and poster fallback styling data
$reviews   = getReviewsByFilm($id); // Retrieve all reviews written for this film
$pd        = getPosterData($film['title'], $film['genre_name'] ?? ''); // Generate fallback poster colors/initials
$pageTitle = $film['title']; // Use the film title as the page heading/tab title

// Retrieve and clear one-time flash message from session
$flashMessage = $_SESSION['flash']      ?? ''; // Read pending flash notification text
$flashType    = $_SESSION['flash_type'] ?? 'success'; // Default flash type to 'success'
unset($_SESSION['flash'], $_SESSION['flash_type']); // Clear flash keys so the alert is not repeated

// Render film detail view inside shared layout
ob_start(); // Start output buffering to capture the film detail template
require __DIR__ . '/templates/film.html.php'; // Render the film metadata and reviews view
$content = ob_get_clean(); // Capture buffered HTML into $content

require __DIR__ . '/templates/layout.html.php'; // Wrap $content inside the master site layout
