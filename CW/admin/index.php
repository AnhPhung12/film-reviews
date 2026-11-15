<?php
/**
 * admin/index.php — Admin Dashboard Overview Controller.
 *
 * Purpose:     Displays summary statistics (films, reviews, reviewers, genres),
 *              top 5 rated films, and the 8 most recent reviews.
 * Connections: Protected by Login/auth.php; uses ../includes/DataBaseFunctions.php
 *              (getStats, getTopRatedFilms, getRecentReviews) and renders inside
 *              admin/layout.php.
 * Security:    Escapes all dynamic output via htmlspecialchars().
 */

require_once 'Login/auth.php'; // Enforce admin authentication guard
require_once '../includes/DataBaseFunctions.php'; // Load dashboard statistics and query helpers

// Page configuration
$pageTitle  = 'Dashboard'; // Set page title in admin topbar
$activePage = 'dashboard'; // Highlight Dashboard item in sidebar navigation

// Retrieve and clear one-time flash message from session
$flash     = $_SESSION['flash']      ?? ''; // Read any pending flash message
$flashType = $_SESSION['flash_type'] ?? 'success'; // Default flash type to 'success'
unset($_SESSION['flash'], $_SESSION['flash_type']); // Clear flash keys so alert appears once

// Fetch dashboard summary datasets
$stats      = getStats(); // Query aggregate counts for films, reviews, reviewers, and genres
$topFilms   = getTopRatedFilms(5); // Query top 5 films ranked by average star rating
$recentRevs = getRecentReviews(8); // Query 8 most recently submitted reviews

// Buffer dashboard HTML for injection into admin/layout.php
ob_start(); // Start output buffering to capture dashboard HTML
?>

<!-- STATISTICS SUMMARY CARDS -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-label">Total Films</div>
        <div class="stat-num"><?= $stats['films'] ?></div>
        <a href="films.php" class="stat-link">Manage films →</a>
    </div>

    <div class="stat-card">
        <div class="stat-label">Total Reviews</div>
        <div class="stat-num"><?= $stats['reviews'] ?></div>
        <a href="reviews.php" class="stat-link">Manage reviews →</a>
    </div>

    <div class="stat-card">
        <div class="stat-label">Reviewers</div>
        <div class="stat-num"><?= $stats['reviewers'] ?></div>
        <a href="reviewers.php" class="stat-link">View reviewers →</a>
    </div>

    <div class="stat-card">
        <div class="stat-label">Genres</div>
        <div class="stat-num"><?= $stats['genres'] ?></div>
        <a href="genres.php" class="stat-link">Manage genres →</a>
    </div>
</div>

<div class="two-col">
    <!-- TOP RATED FILMS TABLE -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><img src="icons/trophy.png" class="card-title-icon" alt=""> Top Rated Films</div>
            <a href="films.php" class="btn btn-ghost btn-sm">All films</a>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Genre</th>
                        <th>Rating</th>
                        <th>Reviews</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topFilms)): ?>
                        <tr class="empty-row">
                            <td colspan="5">No films yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topFilms as $i => $f): ?>
                            <tr>
                                <td class="mono dim" style="font-size:0.78rem">
                                    <?= $i + 1 ?>
                                </td>
                                <td>
                                    <a href="../film.php?id=<?= $f['id'] ?>" target="_blank"
                                        style="font-weight:600;color:var(--text);text-decoration:none;transition:color .2s"
                                        onmouseover="this.style.color='var(--gold)'"
                                        onmouseout="this.style.color='var(--text)'">
                                        <?= htmlspecialchars($f['title']) ?>
                                    </a>
                                    <div class="dim mono" style="font-size:0.7rem">
                                        <?= $f['release_year'] ?? '' ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge">
                                        <?= htmlspecialchars($f['genre_name'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="text-star mono" style="font-size:0.85rem">
                                    <?= number_format($f['avg_rating'], 1) ?>
                                </td>
                                <td class="dim mono" style="font-size:0.8rem">
                                    <?= $f['review_count'] ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- RECENT REVIEWS TABLE -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><img src="icons/reviews.png" class="card-title-icon" alt=""> Recent Reviews</div>
            <a href="reviews.php" class="btn btn-ghost btn-sm">All reviews</a>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Reviewer</th>
                        <th>Film</th>
                        <th>Stars</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentRevs)): ?>
                        <tr class="empty-row">
                            <td colspan="4">No reviews yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentRevs as $r): ?>
                            <tr>
                                <td style="font-weight:600">
                                    <?= htmlspecialchars($r['fullname']) ?>
                                </td>
                                <td style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                    <a href="../film.php?id=<?= $r['film_id'] ?>" target="_blank"
                                        style="color:var(--gold);text-decoration:none">
                                        <?= htmlspecialchars($r['film_title']) ?>
                                    </a>
                                </td>
                                <td class="text-star">
                                    <?= str_repeat('★', (int)$r['rating']) ?>
                                </td>
                                <td class="dim mono" style="font-size:0.72rem;white-space:nowrap">
                                    <?= $r['date'] ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean(); // Capture buffered dashboard HTML into $content
require 'layout.php'; // Render $content inside the shared admin layout shell
