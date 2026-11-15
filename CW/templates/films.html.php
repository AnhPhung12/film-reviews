<?php
/**
 * films.html.php — Paginated Film Catalogue Template.
 * Purpose: Renders multi-criteria filter bar (title, genre, country), genre spotlight banner,
 *          film showcase cards with trailer/watch actions, pagination, and a YouTube trailer modal.
 * Connections:
 *   - Rendered by: films.php; wrapped inside templates/layout.html.php.
 *   - Expects: $films, $genres, $countries, $search, $selectedGenre, $selectedCountry,
 *              $selectedGenreObj, $totalFilms, $totalPages, $page, $paginationHtml.
 * OOP/Patterns: View Template (MVC) with delegated JS event listener for trailer modal playback.
 * Data Structures & Algorithms:
 *   - Computes deterministic poster palettes per film via getPosterData().
 *   - Trailer modal appends autoplay=1 to embed URLs and clears iframe.src on close to stop playback.
 *
 * @var array      $films
 * @var array      $genres
 * @var array      $countries
 * @var string     $search
 * @var int|string $selectedGenre
 * @var string     $selectedCountry
 * @var array|null $selectedGenreObj
 * @var int        $totalFilms
 * @var int        $totalPages
 * @var int        $page
 * @var string     $paginationHtml
 */
?>

<section class="section page-section" aria-labelledby="filmsHeading">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title" id="filmsHeading">Explore <span class="accent">Films</span></h1>
        <p class="page-sub">Discover movie storylines, watch official trailers, check star casts, and stream full films.</p>
    </div>

    <!-- FILTER BAR -->
    <form method="GET" action="films.php" class="filter-bar glass" role="search">
        <div class="filter-group filter-group--search">
            <label for="search" class="filter-label">Search Film</label>
            <div class="input-icon-wrap">
                <span class="input-icon">🔍</span>
                <input type="text" id="search" name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search by title... e.g. Oppenheimer, 1917"
                    class="filter-input" autocomplete="off">
            </div>
        </div>

        <div class="filter-group">
            <label for="genre" class="filter-label">Genre</label>
            <div class="custom-select-wrap">
                <select id="genre" name="genre" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Genres</option>
                    <?php foreach ($genres as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= $selectedGenre == $g['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="select-arrow">▾</span>
            </div>
        </div>

        <div class="filter-group">
            <label for="country" class="filter-label">Country</label>
            <div class="custom-select-wrap">
                <select id="country" name="country" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Countries</option>
                    <?php foreach ($countries as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= ($selectedCountry ?? '') === $c ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="select-arrow">▾</span>
            </div>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($search || $selectedGenre || !empty($selectedCountry)): ?>
                <a href="films.php" class="btn btn-ghost">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- GENRE SPOTLIGHT (If selected) -->
    <?php if ($selectedGenreObj): ?>
        <div class="genre-spotlight glass">
            <div class="gs-label">Selected Genre</div>
            <div class="gs-name"><?= htmlspecialchars($selectedGenreObj['name']) ?></div>
            <?php if (!empty($selectedGenreObj['description'])): ?>
                <p class="gs-desc"><?= htmlspecialchars($selectedGenreObj['description']) ?></p>
            <?php endif; ?>
            <div class="gs-count"><?= count($films) ?> film<?= count($films) != 1 ? 's' : '' ?> found</div>
        </div>
    <?php endif; ?>

    <!-- FILMS SHOWCASE LIST -->
    <div class="films-showcase-wrap">
        <div class="films-count-bar">
            <span>Showing <strong><?= count($films) ?></strong> of <strong><?= $totalFilms ?></strong> film<?= $totalFilms != 1 ? 's' : '' ?><?php if ($totalPages > 1): ?> · Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong><?php endif; ?></span>
        </div>

        <?php if (empty($films)): ?>
            <div class="glass empty-films-box">
                <div style="font-size:2.5rem;margin-bottom:0.8rem">🎬</div>
                <h3>No films found</h3>
                <p style="color:var(--text-dim);margin:0.5rem 0 1.2rem">Try adjusting your search keywords or clear genre filters.</p>
                <a href="films.php" class="btn btn-primary">Reset Filter</a>
            </div>
        <?php else: ?>
            <div class="films-grid">
                <?php foreach ($films as $f): 
                    $pd = getPosterData($f['title'], $f['genre_name'] ?? ''); // Generate deterministic fallback poster palette & initials
                    $posterSrc = posterUrl($f['poster'] ?? ''); // Resolve poster URL (MySQL BLOB stream or disk file)
                    $hasPoster = $posterSrc !== '' && (str_contains($posterSrc, 'image.php?') || is_file(dirname(__DIR__) . '/posters/' . basename($posterSrc))); // Verify DB BLOB URL or disk file
                    $trailerUrl = $f['trailer_url'] ?? ''; // Normalized YouTube embed URL
                    $watchUrl = $f['watch_url'] ?? ''; // External full-movie streaming URL
                    $cast = $f['cast'] ?? ''; // Comma-separated cast list
                    $synopsis = $f['synopsis'] ?? ''; // Film storyline summary
                ?>
                    <article class="film-catalog-card glass">
                        <!-- Poster Column -->
                        <div class="fcc-poster-wrap" style="--pc1:<?= $pd['color1'] ?>;--pc2:<?= $pd['color2'] ?>">
                            <?php if ($hasPoster): ?>
                                <img src="<?= htmlspecialchars($posterSrc, ENT_QUOTES, 'UTF-8') ?>" 
                                     alt="<?= htmlspecialchars($f['title']) ?> poster" 
                                     class="fcc-poster-img" loading="lazy">
                            <?php else: ?>
                                <div class="fcc-poster-fallback">
                                    <span class="fcc-initials"><?= htmlspecialchars($pd['initials']) ?></span>
                                    <span class="fcc-fallback-title"><?= htmlspecialchars($f['title']) ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($f['genre_name'])): ?>
                                <span class="fcc-genre-badge"><?= htmlspecialchars($f['genre_name']) ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Info Content Column -->
                        <div class="fcc-body">
                            <!-- Header with Title & Year -->
                            <div class="fcc-header">
                                <h2 class="fcc-title">
                                    <a href="film.php?id=<?= $f['id'] ?>"><?= htmlspecialchars($f['title']) ?></a>
                                </h2>
                                <div class="fcc-meta">
                                    <?php if (!empty($f['country'])): ?>
                                        <span class="fcc-chip fcc-chip--country">🌍 <?= htmlspecialchars($f['country']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($f['release_year'])): ?>
                                        <span class="fcc-chip">📅 <?= $f['release_year'] ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($f['duration_min'])): ?>
                                        <span class="fcc-chip">⏱ <?= $f['duration_min'] ?> mins</span>
                                    <?php endif; ?>
                                    <?php if (!empty($f['avg_rating'])): ?>
                                        <span class="fcc-chip text-star">★ <?= number_format($f['avg_rating'], 1) ?>/5 (<?= $f['review_count'] ?>)</span>
                                    <?php else: ?>
                                        <span class="fcc-chip text-star">★ New (<?= $f['review_count'] ?? 0 ?> reviews)</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Cast Section -->
                            <div class="fcc-section">
                                <div class="fcc-label">🎭 Starring / Cast:</div>
                                <div class="fcc-cast-text">
                                    <?= !empty($cast) ? htmlspecialchars($cast) : 'Cast details coming soon.' ?>
                                </div>
                            </div>

                            <!-- Synopsis Section -->
                            <div class="fcc-section fcc-synopsis-section">
                                <div class="fcc-label">📖 Synopsis / Storyline:</div>
                                <p class="fcc-synopsis-text">
                                    <?= !empty($synopsis) ? htmlspecialchars($synopsis) : 'No synopsis available for this film yet.' ?>
                                </p>
                            </div>

                            <!-- Action Buttons: Trailer, Watch Full, Details -->
                            <div class="fcc-actions">
                                <?php if (!empty($trailerUrl)): ?>
                                    <button type="button" class="btn btn-trailer js-trailer-btn" 
                                            data-title="<?= htmlspecialchars($f['title'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-url="<?= htmlspecialchars($trailerUrl, ENT_QUOTES, 'UTF-8') ?>">
                                        🎬 Watch Trailer
                                    </button>
                                <?php endif; ?>

                                <?php if (!empty($watchUrl)): ?>
                                    <a href="<?= htmlspecialchars($watchUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-watch">
                                        🍿 Watch Full Movie ↗
                                    </a>
                                <?php endif; ?>

                                <a href="film.php?id=<?= $f['id'] ?>" class="btn btn-ghost btn-details">
                                    💬 Read Reviews (<?= $f['review_count'] ?? 0 ?>)
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?= $paginationHtml ?>
        <?php endif; ?>
    </div>
</section>

<!-- MODAL POPUP FOR TRAILER VIDEO -->
<div id="trailerModal" class="trailer-modal" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="trailer-modal-backdrop" onclick="closeTrailerModal()"></div>
    <div class="trailer-modal-content glass">
        <div class="trailer-modal-header">
            <h3 id="trailerModalTitle" class="trailer-modal-title">Trailer</h3>
            <div style="display:flex;align-items:center;gap:0.6rem">
                <a id="trailerModalExternalLink" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm" style="font-size:0.82rem;padding:0.35rem 0.8rem">
                    Open on YouTube ↗
                </a>
                <button type="button" class="trailer-modal-close" onclick="closeTrailerModal()" aria-label="Close trailer">✕</button>
            </div>
        </div>
        <div class="trailer-video-container">
            <iframe id="trailerIframe" src="" title="Film Trailer" 
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                    allowfullscreen></iframe>
        </div>
    </div>
</div>

<script>
function openTrailerModal(title, url) {
    var modal = document.getElementById('trailerModal');
    var iframe = document.getElementById('trailerIframe');
    var titleEl = document.getElementById('trailerModalTitle');
    var extLink = document.getElementById('trailerModalExternalLink');

    // Add autoplay parameter if not present
    var embedUrl = url;
    if (embedUrl.indexOf('autoplay=1') === -1) {
        embedUrl += (embedUrl.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1';
    }

    // Direct YouTube link fallback
    var watchUrl = url.replace('https://www.youtube.com/embed/', 'https://www.youtube.com/watch?v=');
    if (extLink) {
        extLink.href = watchUrl;
    }

    titleEl.textContent = '🎬 Trailer: ' + title;
    iframe.src = embedUrl;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function closeTrailerModal() {
    var modal = document.getElementById('trailerModal');
    var iframe = document.getElementById('trailerIframe');
    iframe.src = '';
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

// Delegate click event for all trailer buttons
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.js-trailer-btn');
    if (btn) {
        e.preventDefault();
        var title = btn.getAttribute('data-title') || 'Film Trailer';
        var url = btn.getAttribute('data-url') || '';
        if (url) {
            openTrailerModal(title, url);
        }
    }
});

// Close modal when pressing ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('trailerModal').classList.contains('is-open')) {
        closeTrailerModal();
    }
});
</script>
