<?php
/**
 * add_review.html.php — Write a Review Template.
 * Purpose: Renders the 4-step review creation form (film picker, reviewer details, star rating, TinyMCE editor).
 * Connections:
 *   - Rendered by: add_review.php; wrapped inside templates/layout.html.php.
 *   - Expects: $films, $genres, $preselectedFilm, $old, $errors from controller; $_SESSION user info if logged in.
 * OOP/Patterns: View Template (MVC separation of presentation from controller logic).
 * Data Structures & Algorithms:
 *   - Client-side genre filter scans <option data-genre> attributes in O(N) to hide non-matching films.
 *   - Interactive star-rating state machine (setStar/previewStar/resetStarPreview) synced to hidden input.
 *
 * @var array      $films
 * @var array      $genres
 * @var int|string $preselectedFilm
 * @var array      $old
 * @var array      $errors
 */

// Preserve old form data and validation errors (used after form submission fails)
$old    = $old    ?? []; // Default to empty array when no prior POST data exists
$errors = $errors ?? []; // Default to empty array when no validation errors exist
?>

<!-- Main review section -->
<section class="section page-section" aria-labelledby="addReviewHeading">

    <!-- Page header -->
    <div class="page-header">
        <h1 class="page-title" id="addReviewHeading">Write a <span class="accent">Review</span></h1>
        <p class="page-sub">Share your take and help the community discover great cinema.</p>
    </div>

    <div class="form-layout">

        <!-- Main form container -->
        <div class="form-main">
            <form method="POST" action="add_review.php" class="review-form glass" id="reviewForm" novalidate><!-- disable default browser validation -->
                <?= CsrfHelper::input() ?>

                <!-- Film selection with dynamic preview -->
                <div class="form-section">
                    <div class="form-step-label">
                        <span class="step-num" aria-hidden="true">01</span>
                        <h2 class="step-heading">Pick a Film</h2>
                    </div>

                    <div class="form-row">

                        <div class="form-group">
                            <label for="genre_filter" class="form-label">Filter by Genre</label>
                            <div class="custom-select-wrap">
                                <select id="genre_filter" class="form-select"
                                    onchange="filterFilms(this.value)"
                                    aria-label="Filter film list by genre">
                                    <option value="">All Genres</option>
                                    <!-- Loop through genres -->
                                    <?php foreach ($genres as $g): ?>
                                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <!-- Custom dropdown arrow -->
                                <span class="select-arrow" aria-hidden="true">▾</span>
                            </div>
                        </div>

                        <!-- Film selection dropdown -->
                        <div class="form-group">
                            <label for="filmid" class="form-label">Film <span class="required" aria-label="required">*</span></label>
                            <!-- Highlight error if exists -->
                            <div class="custom-select-wrap <?= isset($errors['filmid']) ? 'has-error' : '' ?>">
                                <select id="filmid" name="filmid" class="form-select" required
                                    aria-required="true" aria-describedby="filmid-err"
                                    onchange="onFilmSelect(this)">
                                    <option value="">Choose a film…</option>
                                    <!-- Loop through films for dropdown options -->
                                    <?php foreach ($films as $f): ?>
                                        <option value="<?= $f['id'] ?>"
                                            data-genre="<?= $f['genreid'] ?? '' ?>"
                                            data-year="<?= htmlspecialchars($f['release_year'] ?? '') ?>"
                                            data-dur="<?= htmlspecialchars($f['duration_min'] ?? '') ?>"
                                            data-genre-name="<?= htmlspecialchars($f['genre_name'] ?? '') ?>"
                                            <?= ($preselectedFilm == $f['id'] || ($old['filmid'] ?? '') == $f['id']) ? 'selected' : '' ?>><!-- Keep selected after validation -->
                                            <?= htmlspecialchars($f['title']) ?><?= !empty($f['release_year']) ? ' (' . $f['release_year'] . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="select-arrow" aria-hidden="true">▾</span>
                            </div>
                            <!-- Show validation error -->
                            <?php if (isset($errors['filmid'])): ?>
                                <p class="field-error" id="filmid-err" role="alert"><?= htmlspecialchars($errors['filmid']) ?></p>
                            <?php endif; ?>
                        </div>

                    </div>
                    <!-- Film preview card (hidden until a film is selected) -->
                    <div id="filmPreview" class="film-preview glass" style="display:none" aria-live="polite">
                        <span class="fp-genre" id="fpGenre"></span>
                        <span class="fp-title" id="fpTitle"></span>
                        <span class="fp-meta" id="fpMeta"></span>
                    </div>
                </div>

                <!-- User info -->
                <div class="form-section">
                    <div class="form-step-label">
                        <span class="step-num" aria-hidden="true">02</span>
                        <h2 class="step-heading">Your Details</h2>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="reviewer_name" class="form-label">Full Name <span class="required" aria-label="required">*</span></label>
                            <input type="text" id="reviewer_name" name="reviewer_name"
                                # Autofill with session data if available, otherwise use old input (after validation failure)
                                value="<?= isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : htmlspecialchars($old['reviewer_name'] ?? '') ?>"
                                <?= isset($_SESSION['user_name']) ? 'readonly style="background: rgba(255,255,255,0.05); color: var(--text-dim);"' : '' ?> # Make readonly if logged in
                                placeholder="Your name" required autocomplete="name"
                                class="form-input <?= isset($errors['reviewer_name']) ? 'has-error' : '' ?>"
                                aria-required="true" aria-describedby="name-err">
                            <!-- Show error -->
                            <?php if (isset($errors['reviewer_name'])): ?>
                                <p class="field-error" id="name-err" role="alert"><?= htmlspecialchars($errors['reviewer_name']) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Email input -->
                        <div class="form-group">
                            <label for="reviewer_email" class="form-label">Email <span class="required" aria-label="required">*</span></label>
                            <input type="email" id="reviewer_email" name="reviewer_email"
                                value="<?= isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : htmlspecialchars($old['reviewer_email'] ?? '') ?>"
                                <?= isset($_SESSION['user_email']) ? 'readonly style="background: rgba(255,255,255,0.05); color: var(--text-dim);"' : '' ?>
                                placeholder="you@example.com" required autocomplete="email"
                                class="form-input <?= isset($errors['reviewer_email']) ? 'has-error' : '' ?>"
                                aria-required="true" aria-describedby="email-err">
                            <?php if (isset($errors['reviewer_email'])): ?>
                                <p class="field-error" id="email-err" role="alert"><?= htmlspecialchars($errors['reviewer_email']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Rating input -->
                <div class="form-section">
                    <div class="form-step-label">
                        <span class="step-num" aria-hidden="true">03</span>
                        <h2 class="step-heading">Your Rating</h2>
                    </div>

                    <!-- Hidden input to store the selected rating value -->
                    <input type="hidden" name="rating" id="ratingInput" value="<?= (int)($old['rating'] ?? 0) ?>">

                    <!-- Star rating picker(button) -->
                    <div class="star-picker" role="group" aria-label="Rating: select 1 to 5 stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button"
                                class="star-btn <?= ($old['rating'] ?? 0) >= $i ? 'active' : '' ?> <?= ($old['rating'] ?? 0) == $i ? 'current' : '' ?>"
                                data-val="<?= $i ?>"
                                aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"
                                onclick="setStar(<?= $i ?>)"
                                onmouseenter="previewStar(<?= $i ?>)"
                                onmouseleave="resetStarPreview()">★</button>
                        <?php endfor; ?>
                    </div>

                    <!-- Dynamic label that updates based on selected rating -->
                    <?php $starLabels = ['', 'Poor', 'Fair', 'Good', 'Great', 'Masterpiece']; // Lookup map from 1-5 rating to label
                    $cur = (int)($old['rating'] ?? 0); // Currently selected rating value ?>
                    <div class="star-label" id="starLabel" aria-live="polite">
                        <?= $cur ? '<span class="sl-num">' . $cur . '</span><span class="sl-text"> — ' . $starLabels[$cur] . '</span>' : 'Tap a star to rate' ?>
                    </div>

                    <?php if (isset($errors['rating'])): ?>
                        <p class="field-error" role="alert"><?= htmlspecialchars($errors['rating']) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Review text area -->
                <div class="form-section">
                    <div class="form-step-label">
                        <span class="step-num" aria-hidden="true">04</span>
                        <h2 class="step-heading">Your Review</h2>
                    </div>

                    <div class="form-group">
                        <label for="review_text" class="form-label">Review <span class="required" aria-label="required">*</span></label>
                        <textarea id="review_text" name="review_text" rows="7"
                            placeholder="What did you think? Discuss the story, performances, direction…"
                            required minlength="5"
                            class="form-textarea tinymce-editor <?= isset($errors['review_text']) ? 'has-error' : '' ?>"
                            aria-required="true" aria-describedby="text-err char-counter"><?= htmlspecialchars($old['review_text'] ?? '') ?></textarea>
                        <!-- Character counter -->
                        <div id="char-counter" class="char-counter" aria-live="polite">
                            <span id="charCount">0</span> / 2000
                        </div>
                        <?php if (isset($errors['review_text'])): ?>
                            <p class="field-error" id="text-err" role="alert"><?= htmlspecialchars($errors['review_text']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Submit buttons -->
                <div class="form-footer">
                    <a href="reviews.php" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary btn-submit">
                        Publish Review <span aria-hidden="true">→</span>
                    </button>
                </div>

            </form>
        </div>

        <!-- Sidebar tips -->
        <aside class="form-sidebar" aria-label="Writing tips">
            <div class="tip-card glass">
                <h3 class="tip-title">Writing Tips</h3>
                <ul class="tip-list">
                    <li>📝 Be specific — what exactly worked or didn't?</li>
                    <li>🎭 Mention performances, direction, score</li>
                    <li>⚠️ Avoid major spoilers</li>
                    <li>🌍 Consider the film's cultural context</li>
                    <li>⚖️ Be honest, fair, and constructive</li>
                </ul>
            </div>

            <div class="rating-guide glass">
                <h3 class="tip-title">Star Guide</h3>
                <div class="rg-list">
                    <!-- Define rating guide items with corresponding star visuals and labels -->
                    <?php
                    $guideItems = [
                        [5, '★★★★★', 'Masterpiece', 'high'],
                        [4, '★★★★☆', 'Great',       'good'],
                        [3, '★★★☆☆', 'Good',        'mid'],
                        [2, '★★☆☆☆', 'Fair',        'low'],
                        [1, '★☆☆☆☆', 'Poor',        'bad'],
                    ];
                    // Loop through guide items to display rating descriptions
                    foreach ($guideItems as [$n, $stars, $label, $cls]):
                    ?>
                        <div class="rg-row" onclick="setStar(<?= $n ?>)" style="cursor:pointer">
                            <span class="rg-stars <?= $cls ?>"><?= $stars ?></span>
                            <span class="rg-label"><?= $label ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

    </div>
</section>

<script>
    // JavaScript for interactive star rating and film preview
    const starLabels = ['', 'Poor', 'Fair', 'Good', 'Great', 'Masterpiece'];
    let currentRating = <?= (int)($old['rating'] ?? 0) ?>; // Initialize with old rating if available

    // Update star button UI based on current rating or hover state
    function updateStarUI(val) {
        document.querySelectorAll('.star-btn').forEach((b, i) => {
            b.classList.toggle('active', i < val);
            b.classList.toggle('current', i === val - 1);
        });
    }

    // Set rating when a star is clicked
    function setStar(val) {
        currentRating = val; // Update global rating state
        document.getElementById('ratingInput').value = val; // Update hidden input for form submission
        updateStarUI(val); // Update star button styles
        // Update dynamic label with rating and description
        document.getElementById('starLabel').innerHTML =
            `<span class="sl-num">${val}</span><span class="sl-text"> — ${starLabels[val]}</span>`;
    }

    // Show star preview on hover (without changing actual rating)
    function previewStar(val) {
        updateStarUI(val);
    }

    // Reset star buttons to reflect actual rating when not hovering
    function resetStarPreview() {
        updateStarUI(currentRating);
    }

    // Filter films in the dropdown based on selected genre
    function filterFilms(genreId) {
        const sel = document.getElementById('filmid'); // Film select element
        // Loop through options and hide those that don't match the selected genre
        Array.from(sel.options).forEach(opt => {
            if (!opt.value) return; // Skip placeholder option
            opt.hidden = genreId ? opt.dataset.genre !== genreId : false;
        });
        // Reset film selection if now hidden
        if (sel.value && sel.selectedOptions[0]?.hidden) {
            sel.value = ''; // Clear selection
            document.getElementById('filmPreview').style.display = 'none'; // Hide preview if selected film is filtered out
        }
    }

    // Update film preview card when a film is selected
    function onFilmSelect(sel) {
        const opt = sel.selectedOptions[0]; // Get the selected option
        const prev = document.getElementById('filmPreview'); // Film preview card element
        // If no film is selected, hide the preview
        if (!sel.value) {
            prev.style.display = 'none';
            return;
        }

        // Update preview content with selected film's data attributes
        document.getElementById('fpGenre').textContent = opt.dataset.genreName || ''; // Show genre name if available
        document.getElementById('fpTitle').textContent = opt.text.replace(/\s*\(\d{4}\)$/, ''); // Remove year from title for cleaner display and show it in meta instead

        const year = opt.dataset.year ? '📅 ' + opt.dataset.year : ''; // opt means option
        const dur = opt.dataset.dur ? ' · ⏱ ' + opt.dataset.dur + ' min' : ''; // Show duration if available
        document.getElementById('fpMeta').textContent = year + dur; // Combine year and duration for meta info

        prev.style.display = 'flex'; // Show the preview card
    }

    // Char counter
    const ta = document.getElementById('review_text'); // Review textarea
    const cc = document.getElementById('charCount'); // Character count display element

    // Update character count and change color if approaching limit
    function updateCounter() {
        const n = ta.value.length; // Update count display
        // Change color if over 1900 chars to warn user they're approaching the 2000 char limit
        cc.textContent = n;
        cc.style.color = n > 1900 ? '#ff6b6b' : ''; // Reset to default color if under limit
    }
    ta.addEventListener('input', updateCounter); // Call updateCounter on every input event to keep count accurate in real-time
    updateCounter();

    // Initialize film preview if a film was preselected (e.g. after validation failure)
    window.addEventListener('DOMContentLoaded', () => {
        const sel = document.getElementById('filmid');
        if (sel.value) onFilmSelect(sel); // Show preview for preselected film
        if (currentRating) updateStarUI(currentRating); // Initialize star UI based on preselected rating
    });
</script>
<!-- TinyMCE Rich Text Editor -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
<script>
window.addEventListener('DOMContentLoaded', () => {
    tinymce.init({
        selector: 'textarea.tinymce-editor',
        menubar: false,
        plugins: 'lists link wordcount',
        toolbar: 'undo redo | bold italic underline | bullist numlist | removeformat',
        skin: 'oxide-dark',
        content_css: 'dark',
        height: 220,
        branding: false,
        promotion: false,
        placeholder: 'What did you think? Discuss the story, performances, direction… (Format with bold, italic, or lists!)',
        setup: function(editor) {
            editor.on('change keyup NodeChange', function() {
                editor.save();
                const text = editor.getContent({ format: 'text' }).trim();
                const cc = document.getElementById('charCount');
                if (cc) {
                    cc.textContent = text.length;
                    cc.style.color = text.length > 1900 ? '#ff6b6b' : '';
                }
            });
        }
    });
});
</script>
