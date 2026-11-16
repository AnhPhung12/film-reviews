<?php
/**
 * admin/reviews.php — Admin Reviews Moderation, Multi-Column Sort & Filter.
 *
 * Purpose:     Displays all user reviews with film-title search, genre filtering,
 *              ordered multi-column sorting (rating, date, time), and review
 *              deletion.
 * Connections: Protected by Login/auth.php; uses ../includes/DataBaseFunctions.php
 *              (deleteReview, getAllGenres, getAllReviewsFiltered) and renders
 *              inside admin/layout.php.
 * Algorithm:   Preserves multi-column sort priority by reversing submitted
 *              `sort[]` arrays and deduplicating by field prefix so the most
 *              recently changed column becomes the primary sort key.
 * Security:    Validates genre IDs and sort keys against strict allowlists,
 *              escapes all dynamic output via htmlspecialchars(), and uses PRG.
 */

require_once 'Login/auth.php'; // Enforce admin authentication guard
require_once '../includes/DataBaseFunctions.php'; // Load review moderation and query helpers

// Page configuration
$pageTitle  = 'Reviews'; // Set page title in admin topbar
$activePage = 'reviews'; // Highlight Reviews item in sidebar navigation
$flash      = ''; // Initialize flash message
$flashType  = 'success'; // Default flash type
$editRev    = null; // Placeholder for highlighted review row if applicable

/**
 * Render an inline column sort dropdown while preserving other active filters
 * and lower-priority sort criteria.
 */
function renderReviewSortDropdown(string $field, array $criteria, string $search, string|int $genreId): string
{
    $options = [ // Map sortable column names to their ascending/descending option labels
        'rating' => ['rating_desc' => 'High to low', 'rating_asc' => 'Low to high'],
        'date'   => ['date_desc' => 'Newest first', 'date_asc' => 'Oldest first'],
        'time'   => ['time_desc' => 'Latest first', 'time_asc' => 'Earliest first'],
    ][$field] ?? []; // Default to empty options if unknown field is passed

    $renderSelect = static function (?string $selected) use ($field, $options): void { // Closure to render the <select> element
        echo '<select class="column-sort-select" name="sort[]" aria-label="Sort by ' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . '" onchange="this.form.submit()">'; // Auto-submit form on selection change
        echo '<option value=""' . ($selected === null ? ' selected' : '') . '>Sort ' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . '</option>'; // Default neutral option
        foreach ($options as $value => $label) { // Render each sort direction option
            echo '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . ($selected === $value ? ' selected' : '') . '>'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        echo '</select>'; // Close select element
    };

    $currentCriterion = null; // Active sort criterion for this specific column
    $previousCriteria = []; // Active sort criteria for all other columns
    foreach ($criteria as $criterion) { // Partition criteria between current column and others
        if (str_starts_with($criterion, $field . '_')) { // Match criterion belonging to this field
            $currentCriterion = $criterion; // Store as current column's selected value
        } else {
            $previousCriteria[] = $criterion; // Keep other column criteria as hidden inputs
        }
    }

    // Submit earlier priorities first and the currently edited field last;
    // the GET handler reverses this so the changed field becomes primary.
    $previousCriteria = array_reverse($previousCriteria); // Reverse so priority order is preserved after GET reversal

    ob_start(); // Buffer the form HTML to return as a string
    ?>
    <form method="GET" class="column-sort-form">
        <input type="hidden" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($genreId !== ''): ?><input type="hidden" name="genre" value="<?= (int)$genreId ?>"><?php endif; ?>
        <?php foreach ($previousCriteria as $criterion): ?>
            <input type="hidden" name="sort[]" value="<?= htmlspecialchars($criterion, ENT_QUOTES, 'UTF-8') ?>">
        <?php endforeach; ?>
        <?php $renderSelect($currentCriterion); ?>
    </form>
    <?php
    return trim(ob_get_clean()); // Return trimmed HTML string for the column header
}

// ── HANDLE POST ACTIONS (DELETE REVIEW) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Process review deletion on HTTP POST
    $action = $_POST['action'] ?? ''; // Read submitted action

    if ($action === 'delete') { // Permanently delete the selected review
        $id = (int)($_POST['id'] ?? 0); // Cast review ID to integer
        deleteReview($id); // Delete review record from database
        $_SESSION['flash']      = 'Review deleted successfully.'; // Set confirmation flash
        $_SESSION['flash_type'] = 'success'; // Style flash as success
    }

    // Preserve active search, genre, and sort state across PRG redirect
    $allowedSorts = ['rating_desc', 'rating_asc', 'date_desc', 'date_asc', 'time_desc', 'time_asc']; // Whitelist of valid sort keys
    $postCriteria = $_POST['sort'] ?? []; // Read preserved sort criteria from hidden inputs
    if (!is_array($postCriteria)) { // Normalize scalar sort input to array
        $postCriteria = [$postCriteria]; // Wrap single sort value in array
    }
    $postCriteria   = array_values(array_filter($postCriteria, static fn($value) => is_string($value) && in_array($value, $allowedSorts, true))); // Filter against allowlist
    $redirectParams = ['search' => trim($_POST['search'] ?? '')]; // Preserve search keyword
    if (isset($_POST['genre']) && ctype_digit((string)$_POST['genre'])) { // Preserve numeric genre filter if set
        $redirectParams['genre'] = (int)$_POST['genre']; // Append genre ID to redirect query
    }
    if ($postCriteria) { // Preserve sort criteria if any are active
        $redirectParams['sort'] = $postCriteria; // Append sort array to redirect query
    }
    $redirectQuery = http_build_query($redirectParams); // Encode redirect query string
    header('Location: reviews.php' . ($redirectQuery !== '' ? '?' . $redirectQuery : '')); // Redirect using PRG pattern
    exit; // Stop execution after redirect
}

// Retrieve and clear one-time flash message
$flash     = $_SESSION['flash']      ?? ''; // Read pending flash notification
$flashType = $_SESSION['flash_type'] ?? 'success'; // Default flash type to 'success'
unset($_SESSION['flash'], $_SESSION['flash_type']); // Remove flash keys so alert displays once

// Parse search, genre filter, and ordered multi-column sort criteria
$search         = trim($_GET['search'] ?? ''); // Read and trim film title search keyword
$allowedSorts   = ['rating_desc', 'rating_asc', 'date_desc', 'date_asc', 'time_desc', 'time_asc']; // Whitelist of allowed sort keys
$genres         = getAllGenres(); // Fetch all genres for column filter dropdown
$genreId        = ''; // Default genre filter to empty (all genres)
$requestedGenre = $_GET['genre'] ?? ''; // Read genre filter from query string
if (is_string($requestedGenre) && ctype_digit($requestedGenre)) { // Validate genre ID is numeric digits
    $genreIds = array_map(static fn($genre) => (int)$genre['id'], $genres); // Extract valid genre IDs
    if (in_array((int)$requestedGenre, $genreIds, true)) { // Verify requested genre exists in database
        $genreId = (int)$requestedGenre; // Set validated genre ID filter
    }
}

$requestedCriteria = $_GET['sort'] ?? []; // Read submitted multi-column sort array
if (!is_array($requestedCriteria)) { // Normalize scalar sort parameter to array
    $requestedCriteria = [$requestedCriteria]; // Wrap single criterion in array
}
$requestedCriteria = array_reverse($requestedCriteria); // Reverse so the most recently changed column has highest priority
$sortCriteria      = []; // Final deduplicated list of sort criteria
$seenSortFields    = []; // Track which columns have already been added
foreach ($requestedCriteria as $criterion) { // Deduplicate and validate each sort criterion
    if (!is_string($criterion) || !in_array($criterion, $allowedSorts, true)) { // Skip invalid sort keys
        continue; // Ignore untrusted input
    }
    $field = strtok($criterion, '_'); // Extract column prefix ('rating', 'date', or 'time')
    if (isset($seenSortFields[$field])) { // Keep only highest-priority sort per column
        continue; // Skip older duplicate for the same column
    }
    $seenSortFields[$field] = true; // Mark column as seen
    $sortCriteria[]         = $criterion; // Append validated criterion
}

$reviews = getAllReviewsFiltered($search, $genreId, $sortCriteria); // Query filtered and multi-sorted reviews

ob_start(); // Begin output buffering to capture reviews table HTML
?>

<!-- SEARCH FORM -->
<div class="form-card" style="padding:1.25rem">
    <form method="GET" class="form-filter">
        <div class="form-group grow">
            <label class="form-label">Search by film title</label>
            <input class="form-input" type="text" name="search"
                value="<?= htmlspecialchars($search) ?>" placeholder="Film title…">
        </div>
        <?php if ($genreId !== ''): ?><input type="hidden" name="genre" value="<?= (int)$genreId ?>"><?php endif; ?>
        <?php foreach (array_reverse($sortCriteria) as $criterion): ?>
            <input type="hidden" name="sort[]" value="<?= htmlspecialchars($criterion, ENT_QUOTES, 'UTF-8') ?>">
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search || $genreId !== '' || $sortCriteria): ?>
            <a href="reviews.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- REVIEWS TABLE -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <img src="icons/reviews.png" class="card-title-icon" alt=""> Reviews
            <span class="badge badge-gray"><?= count($reviews) ?></span>
        </div>
    </div>
    <div class="table-wrap" id="adminReviewsScroll" style="overflow-x:auto;overflow-y:auto;max-height:380px;">
        <table style="min-width:960px;">
            <thead style="position:sticky;top:0;z-index:5;background:rgba(36,28,34,0.96);backdrop-filter:blur(8px);">
                <tr>
                    <th>Reviewer</th>
                    <th>
                        <div class="sortable-heading">
                            <span>Film</span>
                            <form method="GET" class="column-sort-form">
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                                <?php foreach (array_reverse($sortCriteria) as $criterion): ?>
                                    <input type="hidden" name="sort[]" value="<?= htmlspecialchars($criterion, ENT_QUOTES, 'UTF-8') ?>">
                                <?php endforeach; ?>
                                <select class="column-sort-select" name="genre" aria-label="Filter reviews by film genre" onchange="this.form.submit()">
                                    <option value="" <?= $genreId === '' ? 'selected' : '' ?>>All genres</option>
                                    <?php foreach ($genres as $genre): ?>
                                        <option value="<?= (int)$genre['id'] ?>" <?= $genreId !== '' && $genreId === (int)$genre['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($genre['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </th>
                    <th>
                        <div class="sortable-heading">
                            <span>Rating</span>
                            <?= renderReviewSortDropdown('rating', $sortCriteria, $search, $genreId) ?>
                        </div>
                    </th>
                    <th>Review</th>
                    <th>
                        <div class="sortable-heading">
                            <span>Date</span>
                            <?= renderReviewSortDropdown('date', $sortCriteria, $search, $genreId) ?>
                        </div>
                    </th>
                    <th>
                        <div class="sortable-heading">
                            <span>Time</span>
                            <?= renderReviewSortDropdown('time', $sortCriteria, $search, $genreId) ?>
                        </div>
                    </th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reviews)): ?>
                    <tr class="empty-row">
                        <td colspan="7">No reviews found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                        <tr style="<?= (isset($editRev['id']) && $editRev['id'] == $r['id']) ? 'background:var(--glass-hover)' : '' ?>">
                            <!-- Reviewer info -->
                            <td>
                                <div style="font-weight:600"><?= htmlspecialchars($r['fullname']) ?></div>
                                <div class="mono dim" style="font-size:0.7rem"><?= htmlspecialchars($r['email']) ?></div>
                            </td>

                            <!-- Film info -->
                            <td>
                                <a href="../film.php?id=<?= $r['film_id'] ?>" target="_blank"
                                    style="color:var(--gold);text-decoration:none;font-weight:600">
                                    <?= htmlspecialchars($r['film_title']) ?>
                                </a>
                                <?php if (!empty($r['genre_name'])): ?>
                                    <div><span class="badge" style="font-size:0.65rem"><?= htmlspecialchars($r['genre_name']) ?></span></div>
                                <?php endif; ?>
                            </td>

                            <!-- Rating -->
                            <td style="white-space:nowrap">
                                <span class="text-star"><?= str_repeat('★', (int)$r['rating']) ?></span>
                                <span class="mono dim" style="font-size:0.72rem"> (<?= $r['rating'] ?>)</span>
                            </td>

                            <!-- Review text preview (strip HTML tags, truncated to 90 characters) -->
                            <td style="max-width:200px;font-size:0.82rem;color:var(--text-dim)">
                                <?php $plainPreview = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string)$r['text']), ENT_QUOTES, 'UTF-8'))); ?>
                                <?= htmlspecialchars(mb_substr($plainPreview, 0, 90), ENT_QUOTES, 'UTF-8') ?><?= mb_strlen($plainPreview) > 90 ? '…' : '' ?>
                            </td>

                            <!-- Date & Time -->
                            <?php $revIso = (!empty($r['date']) && !empty($r['review_time'])) ? ($r['date'] . 'T' . $r['review_time'] . '+07:00') : ''; ?>
                            <td class="mono dim" style="font-size:0.72rem;white-space:nowrap" <?= $revIso ? 'data-local-dt="' . htmlspecialchars($revIso, ENT_QUOTES, 'UTF-8') . '" data-local-fmt="date"' : '' ?>><?= $r['date'] ?></td>
                            <td class="mono dim" style="font-size:0.72rem;white-space:nowrap" <?= $revIso ? 'data-local-dt="' . htmlspecialchars($revIso, ENT_QUOTES, 'UTF-8') . '" data-local-fmt="time"' : '' ?>>
                                <?= !empty($r['review_time']) ? htmlspecialchars($r['review_time']) : '—' ?>
                            </td>

                            <!-- Actions -->
                            <td>
                                <div style="display:flex;gap:0.4rem">
                                    <form method="POST" onsubmit="return confirm('Permanently delete this review?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if ($genreId !== ''): ?><input type="hidden" name="genre" value="<?= (int)$genreId ?>"><?php endif; ?>
                                        <?php foreach (array_reverse($sortCriteria) as $criterion): ?>
                                            <input type="hidden" name="sort[]" value="<?= htmlspecialchars($criterion, ENT_QUOTES, 'UTF-8') ?>">
                                        <?php endforeach; ?>
                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($reviews) > 6): ?>
        <div id="adminReviewsScrollStatus" style="padding:0.75rem 1.25rem;border-top:1px solid var(--border);font-size:0.78rem;color:var(--text-dim);display:flex;justify-content:space-between;align-items:center;">
            <span>Showing <strong id="adminReviewsShownCount">6</strong> of <strong><?= count($reviews) ?></strong> reviews</span>
            <span id="adminReviewsScrollHint">Scroll down to load 6 more…</span>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var wrap = document.getElementById('adminReviewsScroll');
    if (!wrap) return;
    var rows = Array.prototype.slice.call(wrap.querySelectorAll('tbody tr:not(.empty-row)'));
    var batchSize = 6;
    var shown = Math.min(batchSize, rows.length);
    var countEl = document.getElementById('adminReviewsShownCount');
    var hintEl  = document.getElementById('adminReviewsScrollHint');

    function applyVisibility() {
        rows.forEach(function (tr, idx) {
            tr.style.display = idx < shown ? '' : 'none';
        });
        if (countEl) countEl.textContent = String(shown);
        if (hintEl) {
            hintEl.textContent = shown >= rows.length
                ? 'All ' + rows.length + ' reviews loaded'
                : 'Scroll down to load 6 more…';
        }
    }

    if (rows.length > batchSize) {
        applyVisibility();
        wrap.addEventListener('scroll', function () {
            if (shown >= rows.length) return;
            if (wrap.scrollTop + wrap.clientHeight >= wrap.scrollHeight - 24) {
                shown = Math.min(rows.length, shown + batchSize);
                applyVisibility();
            }
        });
    }
})();
</script>

<?php
$content = ob_get_clean(); // Capture buffered reviews HTML into $content
require 'layout.php'; // Render $content inside the shared admin layout shell
