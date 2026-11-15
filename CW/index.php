<?php
/**
 * index.php — Public Homepage Controller.
 *
 * Purpose:     Displays site-wide statistics, top-rated films, recent reviews,
 *              and genre browse links on the Cine landing page.
 * Connections: Uses includes/DataBaseFunctions.php (getStats, getTopRatedFilms,
 *              getRecentReviews, getAllGenres) and renders templates/home.html.php
 *              inside templates/layout.html.php.
 */

session_start(); // Start or resume the user session to access flash messages and login state
require_once __DIR__ . '/includes/DataBaseFunctions.php'; // Load shared database helper functions

// Page configuration and dashboard data queries
$pageTitle     = 'Home'; // Set the browser tab title for the homepage
$stats         = getStats();         // Aggregate counts (films, reviews, reviewers, genres)
$topFilms      = getTopRatedFilms(5); // Top 5 highest-rated films
$recentReviews = getRecentReviews(6); // Latest 6 published reviews
$genres        = getAllGenres();      // All available genres for quick browsing

// Retrieve and clear one-time flash message from session
$flashMessage = $_SESSION['flash']      ?? ''; // Read any pending flash notification text
$flashType    = $_SESSION['flash_type'] ?? 'success'; // Default flash style to 'success' if unset
unset($_SESSION['flash'], $_SESSION['flash_type']); // Remove flash keys so the message only displays once

// Render homepage view inside shared layout
ob_start(); // Begin output buffering to capture the homepage template
require __DIR__ . '/templates/home.html.php'; // Render the homepage content view
$content = ob_get_clean(); // Store captured HTML and clean the output buffer

require __DIR__ . '/templates/layout.html.php'; // Inject $content into the main site layout template
