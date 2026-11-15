<?php
/**
 * film.html.php — Single Film Detail & Reviews Template.
 * Purpose: Displays film hero (poster/fallback, metadata, cast, synopsis, average rating),
 *          embedded YouTube trailer, and community reviews with owner edit/delete controls.
 * Connections:
 *   - Rendered by: film.php; wrapped inside templates/layout.html.php.
 *   - Expects: $film, $reviews, $pd (poster palette/initials) and uses PresentationHelper functions.
 * OOP/Patterns: View Template (MVC) with owner-scoped UI authorization checks.
 * Data Structures & Algorithms:
 *   - Aggregates mean rating in O(N) via array_sum(array_column($reviews, 'rating')) / count($reviews).
 *   - Escapes all outputs with htmlspecialchars() and sanitizes review HTML via safeHtmlContent().
 *
 * @var array $film
 * @var array $reviews
 * @var array $pd
 */

$heroPoster = $film['poster'] ?? ''; // Stored poster path or empty string
$currentUserId = (int)($_SESSION['user_id'] ?? 0); // Logged-in user ID for review ownership checks
?>

<!-- Film Detail Page -->
<div class="film-detail-page section page-section">
    <div class="film-hero glass">
        <div class="film-poster-wrap"
            style="--pc1:<?= $pd['color1'] ?>;--pc2:<?= $pd['color2'] ?>"> <!-- Use palette colors for dynamic styling -->
            <!-- Film poster -->
            <?php if (!empty($heroPoster)): ?>
                <!-- Display the film poster -->
                <img src="<?= htmlspecialchars(posterUrl($heroPoster), ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($film['title']) ?> poster"
                    class="film-poster-real-img">
            <?php else: ?>
                <div class="film-poster-placeholder">
                    <div class="fpp-initials"><?= htmlspecialchars($pd['initials']) ?></div> <!-- Show film title initials if no poster -->
                    <div class="fpp-title"><?= htmlspecialchars($film['title']) ?></div>
                    <?php if (!empty($film['release_year'])): ?>
                        <div class="fpp-year"><?= $film['release_year'] ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Film hero information -->
        <div class="film-hero-info">
            <!-- Genre tag -->
            <?php if (!empty($film['genre_name'])): ?>
                <div class="film-genre-tag"><?= htmlspecialchars($film['genre_name']) ?></div>
            <?php endif; ?>

            <h1 class="film-hero-title"><?= htmlspecialchars($film['title']) ?></h1>

            <!-- Film metadata -->
            <div class="film-meta-row">
                <?php if (!empty($film['country'])): ?>
                    <span class="film-meta-chip">🌍 <?= htmlspecialchars($film['country']) ?></span>
                <?php endif; ?>
                <?php if (!empty($film['release_year'])): ?>
                    <!-- Release year -->
                    <span class="film-meta-chip">📅 <?= $film['release_year'] ?></span>
                <?php endif; ?>
                <!-- Duration -->
                <?php if (!empty($film['duration_min'])): ?>
                    <!-- Film duration -->
                    <span class="film-meta-chip">⏱ <?= $film['duration_min'] ?> min</span>
                <?php endif; ?>
                <!-- Review count -->
                <?php if (!empty($film['review_count'])): ?>
                    <!-- Display review count -->
                    <span class="film-meta-chip">💬 <?= $film['review_count'] ?> review<?= $film['review_count'] != 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </div>

            <!-- Genre description -->
            <?php if (!empty($film['genre_desc'])): ?>
                <p class="film-genre-desc"><?= htmlspecialchars($film['genre_desc']) ?></p>
            <?php endif; ?>

            <!-- Cast Section -->
            <?php if (!empty($film['cast'])): ?>
                <div class="film-detail-block">
                    <span class="detail-label">🎭 Starring / Cast</span>
                    <span class="detail-val"><?= htmlspecialchars($film['cast']) ?></span>
                </div>
            <?php endif; ?>

            <!-- Synopsis Section -->
            <?php if (!empty($film['synopsis'])): ?>
                <div class="film-detail-block">
                    <span class="detail-label">📖 Storyline & Synopsis</span>
                    <p class="film-synopsis-p"><?= htmlspecialchars($film['synopsis']) ?></p>
                </div>
            <?php endif; ?>

            <!-- Average rating -->
            <?php if (!empty($reviews)): ?>
                <?php
                // Calculate average rating and total reviews for display in the aggregate rating section
                $avg   = round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1); // Compute mean rating rounded to 1 decimal
                $total = count($reviews); // Total number of reviews for this film
                ?>
                <!-- Aggregate rating display -->
                <div class="film-agg-rating">
                    <?= starHtml((int)round($avg), 'lg') ?> <!-- Display average rating as stars -->
                    <div class="agg-numbers">
                        <!-- Display aggregate rating numbers -->
                        <span class="agg-score"><?= number_format($avg, 1) ?></span>
                        <span class="agg-out">/5</span>
                        <!-- Display review count -->
                        <span class="agg-count">· <?= $total ?> review<?= $total != 1 ? 's' : '' ?></span> <!-- Show total reviews with proper pluralization -->
                    </div>
                </div>
            <?php else: ?>
                <p class="no-reviews-yet">No reviews yet — be the first!</p>
            <?php endif; ?>

            <!-- Hero actions -->
            <div class="film-hero-actions">
                <?php if (!empty($film['watch_url'])): ?>
                    <a href="<?= htmlspecialchars($film['watch_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-watch">🍿 Watch Full Movie ↗</a>
                <?php endif; ?>
                <a href="add_review.php?film=<?= $film['id'] ?>" class="btn btn-primary">✍️ Write a Review</a>
                <button type="button" class="btn btn-ghost" onclick="window.openCineAiChat('Tell me the story summary of ' + <?= htmlspecialchars(json_encode($film['title']), ENT_QUOTES, 'UTF-8') ?>, <?= (int)$film['id'] ?>)"><img src="icons/robot.png" alt="" class="btn-ai-robot">Ask AI Story</button>
                <a href="films.php" class="btn btn-ghost">← All Films</a>
            </div>
        </div>
    </div>

    <!-- Official Trailer Section -->
    <?php if (!empty($film['trailer_url'])): ?>
        <div class="film-trailer-section glass">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem">
                <h2 class="sub-title" style="margin-bottom:0">🎬 Official Trailer: <?= htmlspecialchars($film['title']) ?></h2>
                <?php
                    $ytUrl = str_replace('https://www.youtube.com/embed/', 'https://www.youtube.com/watch?v=', $film['trailer_url']);
                ?>
                <a href="<?= htmlspecialchars($ytUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm" style="font-size:0.85rem">
                    Watch on YouTube ↗
                </a>
            </div>
            <div class="trailer-video-container">
                <iframe src="<?= htmlspecialchars($film['trailer_url']) ?>" 
                        title="<?= htmlspecialchars($film['title']) ?> Trailer" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                        allowfullscreen></iframe>
            </div>
        </div>
    <?php endif; ?>

    <!-- Reviews section -->
    <div class="film-reviews-section">
        <h2 class="sub-title">
            Reviews
            <span class="count-badge"><?= count($reviews) ?></span> <!-- Show number of reviews in badge -->
        </h2>

        <!-- Empty state -->
        <?php if (empty($reviews)): ?>
            <div class="empty-state glass">
                <div class="empty-icon">🎬</div>
                <p>No reviews yet for this film.</p>
                <a href="add_review.php?film=<?= $film['id'] ?>" class="btn btn-primary">Be First to Review</a>
            </div>
        <?php else: ?>
            <!-- Reviews list -->
            <div class="reviews-list">
                <?php foreach ($reviews as $rev):
                    $isOwner = $currentUserId > 0 && (int)($rev['reviewerid'] ?? 0) === $currentUserId;
                ?>
                    <article class="review-card review-card-full glass">
                        <div class="review-header">
                            <?= avatarHtml($rev['avatar'] ?? null, $rev['fullname']) ?>
                            <div class="reviewer-info">
                                <span class="reviewer-name">
                                    <?= htmlspecialchars($rev['fullname']) ?>
                                    <?php if ($isOwner): ?>
                                        <span class="owner-badge">You</span>
                                    <?php endif; ?>
                                </span>
                                <span class="reviewer-email"><?= htmlspecialchars($rev['email']) ?></span>
                                <time class="review-date" datetime="<?= $rev['date'] ?>">
                                    <?= date('F j, Y', strtotime($rev['date'])) ?> <!-- Format review date for display -->
                                </time>
                            </div>
                            <div class="review-stars-wrap review-stars-wrap--right">
                                <?= starHtml((int)$rev['rating'], 'lg') ?> <!-- Display review rating as stars -->
                            </div>
                            <?php if ($isOwner): ?>
                                <!-- Review actions (only shown to review author) -->
                                <div class="review-actions">
                                    <!-- Edit review link -->
                                    <a href="edit.php?id=<?= $rev['id'] ?>"
                                        class="btn-edit"
                                        title="Edit review"
                                        aria-label="Edit review">✎</a>
                                    <!-- Delete review link -->
                                    <a href="deletereview.php?id=<?= $rev['id'] ?>&ref=<?= urlencode('film.php?id=' . $film['id']) ?>"
                                        class="btn-delete"
                                        title="Delete review"
                                        onclick="return confirm('Delete this review?')"
                                        aria-label="Delete review">✕</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="review-text review-text-full"><?= safeHtmlContent($rev['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
