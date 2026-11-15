<?php
/**
 * home.html.php — Public Home Page Template.
 * Purpose: Renders the hero banner, live platform statistics, Top Rated Films poster grid,
 *          Browse by Genre cards, and Latest Community Reviews feed.
 * Connections:
 *   - Rendered by: index.php; wrapped inside templates/layout.html.php.
 *   - Expects: $stats, $topFilms, $recentReviews, $genres and uses PresentationHelper functions.
 * OOP/Patterns: View Template (MVC).
 * Data Structures & Algorithms:
 *   - Cycles through a 10-color $palette array via modulo indexing ($gi % count($palette)).
 *   - Truncates review previews to 200 multibyte characters (mb_substr) for uniform card heights.
 *
 * @var array $stats
 * @var array $topFilms
 * @var array $recentReviews
 * @var array $genres
 */
?>

<!-- Hero Section -->
<section class="hero" aria-labelledby="heroHeading">
    <h1 class="hero-title" id="heroHeading">
        <span class="tl">Discover</span>
        <span class="tl accent">Cinema</span>
    </h1>
    <!-- Hero subtitle -->
    <p class="hero-sub">Unfiltered reviews from real film lovers. Dive deep into the stories that matter.</p>
    <div class="hero-actions">
        <a href="reviews.php" class="btn btn-primary">Browse Films</a>
        <a href="add_review.php" class="btn btn-ghost">Write a Review</a>
    </div>
    <!-- Statistics Bar -->
    <div class="stats-bar glass" role="list">
        <!-- Statistic items -->
        <?php $si = [['num' => $stats['films'] ?? 0, 'label' => 'Films'], ['num' => $stats['reviews'] ?? 0, 'label' => 'Reviews'], ['num' => $stats['reviewers'] ?? 0, 'label' => 'Critics'], ['num' => $stats['genres'] ?? 0, 'label' => 'Genres']]; ?> <!-- Prepare statistics data for display -->
        <!-- Display each statistic -->
        <?php foreach ($si as $i => $s): ?> <!-- Loop through each statistic to display number and label -->
            <?php if ($i > 0): ?><div class="stat-divider"></div><?php endif; ?> <!-- Add divider between stats except before the first one -->
            <!-- Statistic item -->
            <div class="stat-item" role="listitem">
                <span class="stat-num"><?= number_format($s['num']) ?></span> <!-- Display statistic number with formatting -->
                <span class="stat-label"><?= $s['label'] ?></span> <!-- Display statistic label -->
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Top Rated Films Section -->
<section class="section" aria-labelledby="topRatedHeading">
    <div class="section-header">
        <h2 class="section-title" id="topRatedHeading">Top Rated <span class="accent">Films</span></h2>
        <a href="reviews.php" class="section-link">View all →</a>
    </div>

    <!-- Top Rated Films Grid -->
    <?php if (empty($topFilms)): ?>
        <div class="empty-state glass">
            <div class="empty-icon">🎬</div>
            <p>No rated films yet.</p>
            <a href="add_review.php" class="btn btn-primary">Write First Review</a>
        </div>
    <?php else: ?>
        <div class="poster-grid">
            <!-- loop through top films -->
            <?php foreach ($topFilms as $idx => $film):
                $pd = getPosterData($film['title'], $film['genre_name'] ?? '');
            ?>
                <a href="film.php?id=<?= $film['id'] ?>" class="poster-card">

                    <div class="poster-img-wrap"
                        style="--pc1:<?= $pd['color1'] ?>;--pc2:<?= $pd['color2'] ?>">

                        <!-- Film poster -->
                        <?php if (!empty($film['poster'])): ?>
                            <img src="<?= htmlspecialchars(posterUrl($film['poster']), ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($film['title']) ?> poster"
                                class="poster-real-img">
                        <?php else: ?>
                            <!-- Poster placeholder -->
                            <div class="poster-placeholder">
                                <div class="pp-initials"><?= htmlspecialchars($pd['initials']) ?></div>
                                <div class="pp-title"><?= htmlspecialchars($film['title']) ?></div>
                                <!-- Release year -->
                                <?php if (!empty($film['release_year'])): ?>
                                    <div class="pp-year"><?= $film['release_year'] ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Rank -->
                        <div class="poster-rank">#<?= $idx + 1 ?></div> <!-- Display film rank based on index in topFilms array -->
                        <div class="poster-overlay">
                            <!-- Film genre -->
                            <div class="po-genre"><?= htmlspecialchars($film['genre_name'] ?? '') ?></div>
                            <div class="po-rating">
                                <?= starHtml((int)round($film['avg_rating']), 'sm') ?>
                                <span class="po-score"><?= number_format($film['avg_rating'], 1) ?>/5</span>
                            </div> <!-- Display average rating as stars and numeric score -->
                            <div class="po-reviews"><?= $film['review_count'] ?> review<?= $film['review_count'] != 1 ? 's' : '' ?></div>
                            <div class="po-cta">Read Reviews →</div>
                        </div>
                    </div>

                    <!-- Film title and metadata -->
                    <div class="poster-footer">
                        <div class="poster-title"><?= htmlspecialchars($film['title']) ?></div>
                        <div class="poster-meta">
                            <span class="poster-genre"><?= htmlspecialchars($film['genre_name'] ?? '') ?></span>
                            <span class="poster-year"><?= $film['release_year'] ?? '' ?></span>
                        </div>
                        <?= starHtml((int)round($film['avg_rating']), 'sm') ?>
                    </div>

                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Genre Browse Section -->
<section class="section" aria-labelledby="genreHeading">
    <div class="section-header">
        <h2 class="section-title" id="genreHeading">Browse by <span class="accent">Genre</span></h2>
    </div>
    <div class="genre-grid">
        <!-- Genre cards -->
        <?php
        $palette = ['#ff6b6b', '#ffd93d', '#6bcb77', '#4d96ff', '#c77dff', '#ff9f43', '#00d2d3', '#ff6b9d', '#a8edea', '#fed6e3']; // Accent colors cycled across genre cards
        foreach ($genres as $gi => $g): // Iterate over all genres
            $clr = $palette[$gi % count($palette)]; // Pick accent color deterministically via modulo
        ?>
            <!-- Genre card -->
            <a href="reviews.php?genre=<?= $g['id'] ?>" class="genre-card glass" style="--gc:<?= $clr ?>">
                <span class="genre-name"><?= htmlspecialchars($g['name']) ?></span>
                <?php if (!empty($g['description'])): ?>
                    <span class="genre-desc"><?= htmlspecialchars($g['description']) ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Latest Reviews Section -->
<section class="section" aria-labelledby="latestHeading">
    <div class="section-header">
        <h2 class="section-title" id="latestHeading">Latest <span class="accent">Reviews</span></h2>
        <a href="reviews.php" class="section-link">All reviews →</a>
    </div>
    <!-- Recent Reviews Feed -->
    <?php if (empty($recentReviews)): ?>
        <div class="empty-state glass">
            <div class="empty-icon">✍️</div>
            <p>No reviews yet.</p>
            <a href="add_review.php" class="btn btn-primary">Write First Review</a>
        </div>
    <?php else: ?>
        <!-- Recent Reviews -->
        <div class="reviews-feed">
            <?php foreach ($recentReviews as $rev): ?>
                <article class="review-card glass">
                    <div class="review-header">
                        <?= avatarHtml($rev['avatar'] ?? null, $rev['fullname']) ?> <!-- Avatar with user photo or initial -->
                        <div class="reviewer-info">
                            <span class="reviewer-name"><?= htmlspecialchars($rev['fullname']) ?></span>
                            <time class="review-date" datetime="<?= $rev['date'] ?>"><?= date('M j, Y', strtotime($rev['date'])) ?></time>
                        </div>
                        <div class="review-stars-wrap"><?= starHtml((int)$rev['rating'], 'md') ?></div>
                    </div>
                    <a href="film.php?id=<?= $rev['film_id'] ?>" class="review-film-link">
                        🎬 <?= htmlspecialchars($rev['film_title']) ?>
                    </a>
                    <?php $plainReview = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string)$rev['text']), ENT_QUOTES, 'UTF-8'))); ?>
                    <p class="review-text"><?= htmlspecialchars(mb_substr($plainReview, 0, 200), ENT_QUOTES, 'UTF-8') ?><?= mb_strlen($plainReview) > 200 ? '…' : '' ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>