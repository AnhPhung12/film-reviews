<?php
/**
 * reviews.html.php — Community Reviews & Film Browser Template.
 * Purpose: Renders search/genre filter bar, genre spotlight, matching films grid,
 *          and paginated community reviews with owner-only edit/delete actions.
 * Connections:
 *   - Rendered by: reviews.php; wrapped inside templates/layout.html.php.
 *   - Expects: $films, $genres, $reviews, $search, $selectedGenre, $selectedGenreObj,
 *              $totalReviews, $totalPages, $page, $paginationHtml.
 * OOP/Patterns: View Template (MVC) with owner-scoped authorization checks ($isOwner).
 * Data Structures & Algorithms:
 *   - Renders posters via getPosterData() and safe rich text via safeHtmlContent().
 *   - Preserves current request URI in delete confirmation links for seamless redirect back.
 *
 * @var array      $films
 * @var array      $genres
 * @var array      $reviews
 * @var string     $search
 * @var int|string $selectedGenre
 * @var array|null $selectedGenreObj
 * @var int        $totalReviews
 * @var int        $totalPages
 * @var int        $page
 * @var string     $paginationHtml
 */

$currentUserId = (int)($_SESSION['user_id'] ?? 0); // Logged-in user ID for review ownership checks
?>

<!-- Reviews Section -->
<section class="section page-section" aria-labelledby="reviewsHeading">

    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title" id="reviewsHeading">Film <span class="accent">Reviews</span></h1>
        <p class="page-sub">Search films, filter by genre, and read honest community reviews.</p>
    </div>

    <!-- FILTER BAR -->
    <form method="GET" action="reviews.php" class="filter-bar glass" role="search">
        <div class="filter-group filter-group--search">
            <label for="search" class="filter-label">Search Film</label>
            <div class="input-icon-wrap">
                <span class="input-icon">🔍</span>
                <input type="text" id="search" name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Title… e.g. Inception, Dune"
                    class="filter-input" autocomplete="off"> <!-- Search input for film titles -->
            </div>
        </div>
        <!-- Genre Filter -->
        <div class="filter-group">
            <label for="genre" class="filter-label">Genre</label>
            <div class="custom-select-wrap">
                <select id="genre" name="genre" class="filter-select">
                    <option value="">All Genres</option>
                    <!-- Genre options -->
                    <?php foreach ($genres as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= $selectedGenre == $g['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="select-arrow">▾</span>
            </div>
        </div>
        <!-- Filter Actions -->
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Filter</button>
            <!-- Clear Filters Link -->
            <?php if ($search || $selectedGenre): ?>
                <a href="reviews.php" class="btn btn-ghost">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- GENRE SPOTLIGHT -->
    <?php if ($selectedGenreObj): ?>
        <div class="genre-spotlight glass">
            <div class="gs-label">Genre</div>
            <div class="gs-name"><?= htmlspecialchars($selectedGenreObj['name']) ?></div>
            <!-- Genre Description -->
            <?php if (!empty($selectedGenreObj['description'])): ?>
                <p class="gs-desc"><?= htmlspecialchars($selectedGenreObj['description']) ?></p>
                <!-- Genre Description -->
            <?php endif; ?>
            <!-- Film Count -->
            <div class="gs-count"><?= count($films) ?> film<?= count($films) != 1 ? 's' : '' ?> in this genre</div>
        </div>
    <?php endif; ?>

    <!-- FILMS GRID -->
    <div class="table-section">
        <div class="table-header-row">
            <h2 class="sub-title">
                <?php if ($search): ?>Results for "<em><?= htmlspecialchars($search) ?></em>"
                <?php elseif ($selectedGenreObj): ?><?= htmlspecialchars($selectedGenreObj['name']) ?> Films
                <?php else: ?>All Films<?php endif; ?>
                <span class="count-badge"><?= count($films) ?></span>
            </h2>
            <a href="add_review.php" class="btn btn-sm-top">+ Write Review</a>
        </div>

        <!-- Empty State -->
        <?php if (empty($films)): ?>
            <div class="empty-state glass">
                <div class="empty-icon">🎬</div>
                <p>No films found.</p>
                <a href="reviews.php" class="btn btn-ghost">Clear filters</a>
            </div>
        <?php else: ?>
            <!-- Films Grid -->
            <div class="films-poster-grid">
                <!-- Individual Film Cards -->
                <?php foreach ($films as $film):
                    $pd = getPosterData($film['title'], $film['genre_name'] ?? ''); // Compute fallback poster palette & initials
                ?>
                    <a href="film.php?id=<?= $film['id'] ?>" class="film-list-card glass"> <!-- Link to film details page -->

                        <!-- Film Poster -->
                        <div class="flc-poster" style="--pc1:<?= $pd['color1'] ?>;--pc2:<?= $pd['color2'] ?>"> <!-- Poster background with dynamic colors -->
                            <!-- Film Poster Image -->
                            <?php if (!empty($film['poster'])): ?>
                                <img src="<?= htmlspecialchars(posterUrl($film['poster']), ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($film['title']) ?> poster"
                                    class="flc-poster-img">
                            <?php else: ?>
                                <!-- Fallback for films without posters -->
                                <div class="flc-initials"><?= htmlspecialchars($pd['initials']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Film Information -->
                        <div class="flc-info">
                            <div class="flc-title"><?= htmlspecialchars($film['title']) ?></div>
                            <div class="flc-meta">
                                <!-- Genre Badge -->
                                <?php if (!empty($film['genre_name'])): ?>
                                    <span class="genre-badge"><?= htmlspecialchars($film['genre_name']) ?></span>
                                <?php endif; ?>
                                <!-- Release Year -->
                                <?php if (!empty($film['release_year'])): ?>
                                    <span class="flc-year"><?= $film['release_year'] ?></span>
                                <?php endif; ?>
                                <!-- Duration -->
                                <?php if (!empty($film['duration_min'])): ?>
                                    <span class="flc-dur">⏱ <?= $film['duration_min'] ?>m</span>
                                <?php endif; ?>
                            </div>
                            <!-- Rating -->
                            <div class="flc-rating">
                                <?php if ($film['avg_rating']): ?>
                                    <?= starHtml((int)round($film['avg_rating']), 'sm') ?> <!-- Display average rating as stars -->
                                    <span class="flc-score"><?= number_format($film['avg_rating'], 1) ?></span> <!-- Numeric average rating -->
                                    <span class="flc-count">(<?= (int)$film['review_count'] ?> review<?= $film['review_count'] != 1 ? 's' : '' ?>)</span>
                                <?php else: ?>
                                    <span class="no-rating">No reviews yet</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flc-arrow">→</div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- REVIEWS LIST -->
    <div class="reviews-section" aria-labelledby="reviewListHeading">
        <h2 class="sub-title" id="reviewListHeading">
            <!-- Reviews Section Title -->
            <?= ($search || $selectedGenre) ? 'Matching Reviews' : 'Recent Reviews' ?>
            <span class="count-badge"><?= count($reviews) ?> of <?= $totalReviews ?><?php if ($totalPages > 1): ?> · Page <?= $page ?>/<?= $totalPages ?><?php endif; ?></span>
        </h2>

        <!-- Empty State -->
        <?php if (empty($reviews)): ?>
            <div class="empty-state glass">
                <div class="empty-icon">✍️</div>
                <p>No reviews yet for these films.</p>
                <a href="add_review.php" class="btn btn-primary">Be First to Review</a>
            </div>
        <?php else: ?>
            <!-- Reviews List -->
            <div class="reviews-list">
                <?php foreach ($reviews as $rev):
                    $isOwner = $currentUserId > 0 && (int)($rev['reviewerid'] ?? 0) === $currentUserId; // Check whether current user authored this review
                ?>
                    <article class="review-card review-card-full glass">
                        <div class="review-header">
                            <!-- Reviewer Information -->
                            <?= avatarHtml($rev['avatar'] ?? null, $rev['fullname']) ?> <!-- Avatar with user photo or initial -->
                            <div class="reviewer-info">
                                <span class="reviewer-name">
                                    <?= htmlspecialchars($rev['fullname']) ?>
                                    <?php if ($isOwner): ?>
                                        <span class="owner-badge">You</span>
                                    <?php endif; ?>
                                </span>
                                <span class="reviewer-email"><?= htmlspecialchars($rev['email']) ?></span>
                                <time class="review-date" datetime="<?= $rev['date'] ?>"><?= date('F j, Y', strtotime($rev['date'])) ?></time>
                            </div>
                            <!-- Review Stars -->
                            <div class="review-stars-wrap review-stars-wrap--right">
                                <?= starHtml((int)$rev['rating'], 'lg') ?>
                            </div>
                            <?php if ($isOwner): ?>
                                <!-- Review Actions (only shown to review author) -->
                                <div class="review-actions">
                                    <!-- Edit Review Button -->
                                    <a href="edit.php?id=<?= $rev['id'] ?>"
                                        class="btn-edit" title="Edit review" aria-label="Edit review">✎</a>
                                    <!-- Delete Review Button -->
                                    <a href="deletereview.php?id=<?= $rev['id'] ?>&ref=<?= urlencode($_SERVER['REQUEST_URI'] ?? 'reviews.php') ?>"
                                        class="btn-delete"
                                        title="Delete review"
                                        onclick="return confirm('Delete this review?')"
                                        aria-label="Delete review">✕</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- Film Information -->
                        <div class="review-film-badge">
                            <?php if (!empty($rev['poster'])): ?>
                                <img src="<?= htmlspecialchars(posterUrl($rev['poster']), ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($rev['film_title']) ?> poster"
                                    class="review-film-poster">
                            <?php else: ?>
                                <span aria-hidden="true">🎬</span>
                            <?php endif; ?>
                            <a href="film.php?id=<?= $rev['film_id'] ?>" class="review-film-link">
                                <strong><?= htmlspecialchars($rev['film_title']) ?></strong> <!-- Link to film details page -->
                            </a>
                            <!-- Genre Badge -->
                            <?php if (!empty($rev['genre_name'])): ?>
                                <span class="genre-badge genre-badge--sm"><?= htmlspecialchars($rev['genre_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="review-text review-text-full"><?= safeHtmlContent($rev['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
            <?= $paginationHtml ?>
        <?php endif; ?>
    </div>

</section>
