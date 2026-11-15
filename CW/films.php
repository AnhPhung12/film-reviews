<?php
/**
 * films.php — Paginated & Filtered Film Catalogue Entry Point.
 *
 * Purpose:     Lists films with search, genre, and country filtering plus
 *              server-side pagination (8 films per page).
 * Connections: Uses FilmController & GenreController (OOP), getGenreById(), and
 *              renderPagination(), rendering templates/films.html.php inside
 *              templates/layout.html.php.
 * Algorithm:   Computes total matching films -> clamps current page within
 *              [1, totalPages] -> calculates SQL OFFSET = (page - 1) * limit.
 */

session_start(); // Start or resume session for flash messages and user navbar state
require_once __DIR__ . '/includes/classes/FilmController.php'; // Load FilmController for film queries
require_once __DIR__ . '/includes/classes/GenreController.php'; // Load GenreController for genre filter list

$filmCtrl  = new FilmController(); // Instantiate film controller
$genreCtrl = new GenreController(); // Instantiate genre controller

$pageTitle = 'Films & Trailers'; // Set page title for layout template

// Parse GET filter and pagination parameters
$search          = trim($_GET['search'] ?? ''); // Read and trim keyword search query
$selectedGenre   = (isset($_GET['genre']) && $_GET['genre'] !== '') ? (int)$_GET['genre'] : ''; // Cast genre filter to int when provided
$selectedCountry = trim($_GET['country'] ?? ''); // Read and trim country filter value
$page            = max(1, (int)($_GET['page'] ?? 1)); // Ensure current page number is at least 1
$limit           = 8; // 8 films per page

// Fetch filter dropdown options
$genres    = $genreCtrl->listAll(); // Load all genres for the filter dropdown
$countries = $filmCtrl->getCountries(); // Load distinct film countries for the country dropdown

// Compute pagination bounds and query the current page slice
$totalFilms = $filmCtrl->countFiltered($search, $selectedGenre, $selectedCountry); // Count total films matching active filters
$totalPages = max(1, (int)ceil($totalFilms / $limit)); // Calculate total pages (minimum 1)
$page       = min($page, $totalPages); // Clamp requested page to valid upper bound
$offset     = ($page - 1) * $limit; // Compute SQL OFFSET for the current page

$films = $filmCtrl->listFiltered($search, $selectedGenre, $selectedCountry, $limit, $offset); // Fetch paginated film slice

// Preserve active filters in pagination links
$paginationParams = []; // Collect non-empty filter query parameters
if ($search !== '') { // Keep search keyword across page links
    $paginationParams['search'] = $search; // Append search query parameter
}
if ($selectedGenre !== '') { // Keep selected genre across page links
    $paginationParams['genre'] = $selectedGenre; // Append genre ID parameter
}
if ($selectedCountry !== '') { // Keep selected country across page links
    $paginationParams['country'] = $selectedCountry; // Append country parameter
}

$paginationHtml = renderPagination($page, $totalPages, 'films.php', $paginationParams); // Build HTML pagination controls

// Load selected genre metadata for header context if a genre filter is active
$selectedGenreObj = null; // Default selected genre object to null
if ($selectedGenre !== '') { // Look up genre details only when a genre filter is active
    $selectedGenreObj = getGenreById($selectedGenre); // Fetch genre row for display heading
}

// Retrieve and clear one-time flash message
$flashMessage = $_SESSION['flash']      ?? ''; // Read pending flash message
$flashType    = $_SESSION['flash_type'] ?? 'success'; // Default flash type to 'success'
unset($_SESSION['flash'], $_SESSION['flash_type']); // Clear flash keys so alert appears only once

// Render films catalogue view inside shared layout
ob_start(); // Start output buffering to capture films template
require __DIR__ . '/templates/films.html.php'; // Render the films catalogue view
$content = ob_get_clean(); // Store rendered HTML into $content

require __DIR__ . '/templates/layout.html.php'; // Wrap $content inside the shared site layout
