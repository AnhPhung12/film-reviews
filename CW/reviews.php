<?php
/**
 * reviews.php — Public Reviews Feed with Filtering, Sorting & Pagination.
 *
 * Purpose:     Displays published film reviews with search, genre filtering,
 *              sorting, and pagination (6 reviews per page).
 * Connections: Uses ReviewController, FilmController, GenreController (OOP),
 *              and renderPagination(), rendering templates/reviews.html.php
 *              inside templates/layout.html.php.
 * Algorithm:   Computes total matching reviews -> clamps current page within
 *              [1, totalPages] -> calculates SQL OFFSET = (page - 1) * limit.
 */

session_start(); // Start or resume session for flash messages and authenticated UI state
require_once __DIR__ . '/includes/classes/ReviewController.php'; // Load ReviewController for review queries
require_once __DIR__ . '/includes/classes/FilmController.php'; // Load FilmController for matching films list
require_once __DIR__ . '/includes/classes/GenreController.php'; // Load GenreController for genre filters

$reviewCtrl = new ReviewController(); // Instantiate review controller
$filmCtrl   = new FilmController(); // Instantiate film controller
$genreCtrl  = new GenreController(); // Instantiate genre controller

$pageTitle = 'Reviews'; // Set page title for layout template

// Parse GET filter, sort, and pagination parameters
$search          = trim($_GET['search'] ?? ''); // Read and trim search keyword
$selectedGenre   = (isset($_GET['genre']) && $_GET['genre'] !== '') ? (int)$_GET['genre'] : ''; // Cast genre ID to integer when set
$selectedCountry = trim($_GET['country'] ?? ''); // Read and trim country filter
$sort            = $_GET['sort'] ?? ''; // Read sort option (e.g., 'newest', 'highest')
$page            = max(1, (int)($_GET['page'] ?? 1)); // Clamp page number to at least 1
$limit           = 6; // 6 reviews per page

// Fetch genres, countries, and matching films for sidebar/filter controls
$genres    = $genreCtrl->listAll(); // Load all genres for filter chips/dropdown
$countries = $filmCtrl->listCountries(); // Load distinct film countries for dropdown
$films     = $filmCtrl->listFiltered($search, $selectedGenre, $selectedCountry); // Fetch films matching current search/genre/country

// Resolve selected genre record for display heading if filtered
$selectedGenreObj = null; // Initialize selected genre object as null
if ($selectedGenre !== '') { // Query genre details only when a genre filter is active
    $selectedGenreObj = $genreCtrl->find($selectedGenre); // Fetch genre row by ID
}

// Compute pagination bounds and fetch current page of reviews
$totalReviews = $reviewCtrl->countFiltered($search, $selectedGenre, $selectedCountry); // Count total matching reviews
$totalPages   = max(1, (int)ceil($totalReviews / $limit)); // Calculate total pages (minimum 1)
$page         = min($page, $totalPages); // Clamp current page to valid upper bound
$offset       = ($page - 1) * $limit; // Calculate SQL OFFSET for pagination

$reviews = $reviewCtrl->listFiltered($search, $selectedGenre, $sort ? [$sort] : [], $limit, $offset, $selectedCountry); // Fetch paginated and sorted reviews

// Preserve active query filters in pagination links
$paginationParams = []; // Collect active query parameters for pagination URLs
if ($search !== '') { // Preserve search keyword across pages
    $paginationParams['search'] = $search; // Add search parameter
}
if ($selectedGenre !== '') { // Preserve genre filter across pages
    $paginationParams['genre'] = $selectedGenre; // Add genre parameter
}
if ($selectedCountry !== '') { // Preserve country filter across pages
    $paginationParams['country'] = $selectedCountry; // Add country parameter
}
if ($sort !== '') { // Preserve sort order across pages
    $paginationParams['sort'] = $sort; // Add sort parameter
}

$paginationHtml = renderPagination($page, $totalPages, 'reviews.php', $paginationParams); // Generate pagination HTML links

// Retrieve and clear one-time flash message from session
$flashMessage = $_SESSION['flash']      ?? ''; // Read pending flash notification
$flashType    = $_SESSION['flash_type'] ?? 'success'; // Default flash type to 'success'
unset($_SESSION['flash'], $_SESSION['flash_type']); // Remove flash keys after reading

// Render reviews feed inside shared layout
ob_start(); // Start output buffering to capture the reviews template
require __DIR__ . '/templates/reviews.html.php'; // Render the reviews feed view
$content = ob_get_clean(); // Capture buffered HTML into $content

require __DIR__ . '/templates/layout.html.php'; // Wrap $content inside the shared site layout
