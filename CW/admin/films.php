<?php

/**
 * admin/films.php — Admin Films CRUD & Poster/Trailer Management.
 *
 * Purpose:     Provides create, read, update, and delete (CRUD) operations for
 *              films, including poster image uploads, YouTube trailer URL
 *              normalization, and TinyMCE rich-text synopsis editing.
 * Connections: Protected by Login/auth.php; uses ../includes/DataBaseFunctions.php
 *              (createFilm, updateFilm, deleteFilm, getFilmPoster, getAllFilms,
 *              getAllGenres, getFilmByIdForAdmin, normalizeYouTubeEmbedUrl) and
 *              renders inside admin/layout.php.
 * Security:    Validates uploaded image MIME type with finfo_file() and size
 *              (<= 2MB), removes superseded poster files from disk, escapes output
 *              with htmlspecialchars(), and applies PRG redirects after POST.
 */

require_once __DIR__ . '/Login/auth.php'; // Enforce admin authentication guard
require_once __DIR__ . '/../includes/DataBaseFunctions.php'; // Load film CRUD, genre, and URL normalization helpers

// Page configuration
$pageTitle  = 'Films Management'; // Set page title in admin topbar
$activePage = 'films'; // Highlight Films item in sidebar navigation
$flash      = ''; // Initialize flash notification message
$flashType  = 'success'; // Default flash banner style
$editFilm   = null; // Holds film record when editing an existing film

// ── HANDLE POST ACTIONS (ADD / EDIT / DELETE) ──
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') { // Process film CRUD submissions on HTTP POST
    $action   = $_POST['action']        ?? ''; // Determine requested action ('add', 'edit', or 'delete')
    $title    = trim($_POST['title']    ?? ''); // Read and trim film title
    $year     = (int)($_POST['release_year'] ?? 0); // Cast release year to integer
    $dur      = (int)($_POST['duration_min'] ?? 0); // Cast duration in minutes to integer
    $genreId  = (int)($_POST['genreid']  ?? 0); // Cast selected genre ID to integer
    $country  = trim($_POST['country']   ?? 'United Kingdom'); // Read and trim country name
    $cast     = trim($_POST['cast']      ?? ''); // Read and trim cast list
    $synopsis = trim($_POST['synopsis']  ?? ''); // Read and trim TinyMCE synopsis HTML
    $trailer  = normalizeYouTubeEmbedUrl(trim($_POST['trailer_url'] ?? '')); // Convert YouTube watch/share URLs into embed URLs
    $watch    = trim($_POST['watch_url'] ?? ''); // Read and trim full movie watch URL

    // Add or Edit film record
    if ($action === 'add' || $action === 'edit') { // Handle film creation or update
        if (!$title || !$genreId) { // Require both film title and genre selection
            $flash     = 'Title and genre are required.'; // Set validation error message
            $flashType = 'error'; // Style flash as error
        } else {
            $posterFilename = null; // Initialize poster path as null
            $posterBlob     = null; // Initialize optional binary poster payload

            // Preserve existing poster path when editing unless replaced
            if ($action === 'edit') { // Load current poster path before checking for new upload
                $eid            = (int)($_POST['id'] ?? 0); // Cast edited film ID to integer
                $posterFilename = getFilmPoster($eid); // Fetch existing poster path from DB
            }

            // Validate and read uploaded poster image bytes if provided
            if (!empty($_FILES['poster']['name'])) { // Check if an admin uploaded a new poster image
                $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']; // Whitelist of allowed image MIME types
                $finfo   = finfo_open(FILEINFO_MIME_TYPE); // Open Fileinfo handle to inspect file bytes
                $mime    = finfo_file($finfo, $_FILES['poster']['tmp_name']); // Detect actual MIME type
                finfo_close($finfo); // Close Fileinfo handle

                if (!in_array($mime, $allowed)) { // Reject disallowed file formats
                    $flash     = 'Only JPG, PNG, WEBP, and GIF images are allowed.'; // Set MIME error message
                    $flashType = 'error'; // Style flash as error
                } elseif ($_FILES['poster']['size'] > 2 * 1024 * 1024) { // Enforce 2MB maximum upload size
                    $flash     = 'Image must be smaller than 2MB.'; // Set file size error message
                    $flashType = 'error'; // Style flash as error
                } else {
                    $bytes = file_get_contents($_FILES['poster']['tmp_name']); // Read uploaded poster bytes for DB BLOB storage
                    if ($bytes === false || $bytes === '') { // Verify image bytes were read successfully
                        $flash     = 'Failed to read uploaded image.'; // Report read failure
                        $flashType = 'error'; // Style flash as error
                    } else {
                        $posterBlob = ['bytes' => $bytes, 'mime' => $mime]; // Stage poster binary payload for MySQL BLOB
                    }
                }
            }

            // Persist film record if no upload errors occurred
            if (!$flash) { // Proceed with DB write only when no validation/upload error was set
                $repo = new CinemaRepository(); // Instantiate repository for film & BLOB persistence
                if ($action === 'add') { // Insert new film record
                    $repo->createFilm($title, $year, $dur, $genreId, $posterFilename ?? '', $country, $synopsis, $cast, $trailer, $watch); // Create film row in DB
                    if ($posterBlob !== null) { // Save uploaded poster into MySQL BLOB and set stream URL
                        $newFilmId = $repo->getLastInsertedFilmId(); // Read newly created film ID
                        $repo->updateFilmPosterBlob($newFilmId, $posterBlob['bytes'], $posterBlob['mime'], 'image.php?type=poster&id=' . $newFilmId); // Store BLOB in DB
                    }
                    $flash = "Film \"{$title}\" added successfully."; // Set creation success message
                } else {
                    $id = (int)($_POST['id'] ?? 0); // Cast edited film ID to integer
                    $repo->updateFilm($id, $title, $year, $dur, $genreId, $posterFilename, $country, $synopsis, $cast, $trailer, $watch); // Update film row in DB
                    if ($posterBlob !== null) { // Save replacement poster into MySQL BLOB and set stream URL
                        $repo->updateFilmPosterBlob($id, $posterBlob['bytes'], $posterBlob['mime'], 'image.php?type=poster&id=' . $id); // Store BLOB in DB
                    }
                    $flash = "Film \"{$title}\" updated successfully."; // Set update success message
                }
            }
        }
    } elseif ($action === 'delete') { // Delete film and its associated poster
        // Delete film record and remove its poster image from disk
        $id        = (int)($_POST['id'] ?? 0); // Cast target film ID to integer
        $oldPoster = deleteFilm($id); // Delete film (and cascade reviews), returning old poster path
        if ($oldPoster) { // Remove orphaned poster file from disk if present
            $oldPath = $_SERVER['DOCUMENT_ROOT'] . $oldPoster; // Resolve absolute path to poster file
            if (file_exists($oldPath)) { // Confirm file exists on disk
                unlink($oldPath); // Delete poster image file
            }
        }
        $flash = 'Film and all its reviews have been deleted.'; // Set deletion confirmation message
    }

    $_SESSION['flash']      = $flash; // Persist flash message across redirect
    $_SESSION['flash_type'] = $flashType; // Persist flash type across redirect
    header('Location: films.php'); // Redirect using PRG pattern
    exit; // Stop script execution after redirect
}

// Retrieve and clear one-time flash message from session
$flash     = $_SESSION['flash']      ?? ''; // Read pending flash notification
$flashType = $_SESSION['flash_type'] ?? 'success'; // Default flash type to 'success'
unset($_SESSION['flash'], $_SESSION['flash_type']); // Remove flash keys so alert appears once

// Populate edit form when ?edit=ID is requested
if (isset($_GET['edit'])) { // Check if an edit film ID was passed in query string
    $eid      = (int)$_GET['edit']; // Cast edit ID to integer
    $editFilm = getFilmByIdForAdmin($eid); // Fetch film record for form pre-population
}

// Fetch paginated films (10 per page), countries, and genres for display
$repo          = new CinemaRepository();
$filterCountry = trim((string)($_GET['country'] ?? ''));
$filterGenre   = ($_GET['genre'] ?? '') !== '' ? (int)$_GET['genre'] : '';
$sortTitle     = in_array($_GET['sort'] ?? '', ['title_asc', 'title_desc'], true) ? (string)$_GET['sort'] : '';
$limit         = 10;
$totalFilms    = $repo->countFilms('', $filterGenre, $filterCountry);
$totalPages    = max(1, (int)ceil($totalFilms / $limit));
$page          = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);
$offset        = ($page - 1) * $limit;
$films         = $repo->getAllFilms('', $filterGenre, $filterCountry, $limit, $offset, $sortTitle); // Query 10 filtered/sorted films for the current admin page
$countries     = $repo->getAllCountries(); // Query distinct countries for header dropdown
$genres        = getAllGenres(); // Query all genres for the form & header dropdowns
$showForm      = isset($_GET['add']) || !empty($editFilm); // Show Add/Edit form page only when requested

ob_start(); // Begin output buffering to capture films management view
?>

<?php if ($showForm): ?>
<!-- Form card for Add or Edit Film -->
<div class="form-card">
    <div class="form-card-title" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
        <span><?= $editFilm ? '✏️ Edit Film: ' . htmlspecialchars($editFilm['title']) : '➕ Add New Film' ?></span>
        <a href="films.php" class="btn btn-ghost btn-sm">← Back to Films</a>
    </div>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?= $editFilm ? 'edit' : 'add' ?>">
        <?php if ($editFilm): ?>
            <input type="hidden" name="id" value="<?= $editFilm['id'] ?>">
        <?php endif; ?>

        <div class="form-grid">
            <!-- Film Title -->
            <div class="form-group full">
                <label class="form-label">Film Title <span style="color:var(--red)">*</span></label>
                <input class="form-input" type="text" name="title" required placeholder="e.g. Oppenheimer, The White Silk Dress"
                    value="<?= htmlspecialchars($editFilm['title'] ?? '') ?>">
            </div>

            <!-- Genre Selection -->
            <div class="form-group">
                <label class="form-label">Genre <span style="color:var(--red)">*</span></label>
                <select class="form-select" name="genreid" required>
                    <option value="">Select genre…</option>
                    <?php foreach ($genres as $g): ?>
                        <option value="<?= $g['id'] ?>"
                            <?= ($editFilm['genreid'] ?? 0) == $g['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Country Selection -->
            <div class="form-group">
                <label class="form-label">Country</label>
                <input class="form-input" type="text" name="country" list="countryOptions"
                    placeholder="e.g. Vietnam, United Kingdom, United States"
                    value="<?= htmlspecialchars($editFilm['country'] ?? 'Vietnam') ?>">
                <datalist id="countryOptions">
                    <option value="Vietnam">
                    <option value="United Kingdom">
                    <option value="United States">
                    <option value="France">
                    <option value="South Korea">
                    <option value="Japan">
                </datalist>
            </div>

            <!-- Release Year -->
            <div class="form-group">
                <label class="form-label">Release Year</label>
                <input class="form-input" type="number" name="release_year" min="1900" max="2099"
                    placeholder="e.g. 2024"
                    value="<?= htmlspecialchars($editFilm['release_year'] ?? date('Y')) ?>">
            </div>

            <!-- Duration (minutes) -->
            <div class="form-group">
                <label class="form-label">Duration (minutes)</label>
                <input class="form-input" type="number" name="duration_min" min="1" max="999"
                    placeholder="e.g. 120"
                    value="<?= htmlspecialchars($editFilm['duration_min'] ?? 120) ?>">
            </div>

            <!-- Starring / Cast -->
            <div class="form-group full">
                <label class="form-label">Starring / Cast</label>
                <input class="form-input" type="text" name="cast"
                    placeholder="e.g. Cillian Murphy, Emily Blunt, Matt Damon, Robert Downey Jr."
                    value="<?= htmlspecialchars($editFilm['cast'] ?? '') ?>">
                <small style="color:var(--text-dimmer)">Enter main cast names separated by commas.</small>
            </div>

            <!-- Synopsis / Storyline -->
            <div class="form-group full">
                <label class="form-label">Synopsis / Storyline</label>
                <textarea class="form-input form-textarea tinymce-editor" name="synopsis" rows="3"
                    placeholder="Enter brief plot summary, background story, or director info..."><?= htmlspecialchars($editFilm['synopsis'] ?? '') ?></textarea>
            </div>

            <!-- Trailer URL -->
            <div class="form-group">
                <label class="form-label">🎬 YouTube Trailer URL</label>
                <?php
                $trailerInputVal = !empty($editFilm['trailer_url'])
                    ? str_replace('https://www.youtube.com/embed/', 'https://www.youtube.com/watch?v=', $editFilm['trailer_url'])
                    : '';
                ?>
                <input class="form-input" type="url" name="trailer_url"
                    placeholder="https://www.youtube.com/watch?v=... or /embed/..."
                    value="<?= htmlspecialchars($trailerInputVal) ?>">
                <small style="color:var(--text-dimmer)">Accepts standard YouTube watch link or embed link (automatically converted to embed format for the popup player).</small>
            </div>

            <!-- Watch Full Movie URL -->
            <div class="form-group">
                <label class="form-label">🍿 Watch Full Movie URL</label>
                <input class="form-input" type="url" name="watch_url"
                    placeholder="https://www.youtube.com/watch?v=... or streaming link"
                    value="<?= htmlspecialchars($editFilm['watch_url'] ?? '') ?>">
                <small style="color:var(--text-dimmer)">Direct link where users can stream the full movie or episode in a new tab.</small>
            </div>

            <!-- Poster Image Upload -->
            <div class="form-group full">
                <label class="form-label">Poster Image</label>
                <?php if (!empty($editFilm['poster'])): ?>
                    <div style="margin-bottom:0.6rem;display:flex;align-items:center;gap:1rem">
                        <img src="<?= htmlspecialchars(posterUrl($editFilm['poster'], '../'), ENT_QUOTES, 'UTF-8') ?>"
                            alt="Current poster"
                            style="height:110px;width:78px;border-radius:6px;object-fit:cover;border:1px solid var(--border)">
                        <div>
                            <div style="font-weight:600;font-size:0.85rem">Current Poster: <?= htmlspecialchars(basename($editFilm['poster'])) ?></div>
                            <div style="font-size:0.75rem;color:var(--text-dimmer);margin-top:0.2rem">
                                Select a new image below if you want to replace this poster.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <input class="form-input" type="file" name="poster" accept="image/*">
                <small style="color:var(--text-dimmer)">Supported formats: JPG, PNG, WEBP, GIF — maximum 2MB.</small>
            </div>
        </div>

        <!-- Form action buttons -->
        <div class="form-footer">
            <a href="films.php" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <?= $editFilm ? '💾 Save Changes' : '➕ Add Film' ?>
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if (!$showForm): ?>
<!-- ALL FILMS DIRECTORY TABLE -->
<div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
        <div class="card-title">
            <img src="icons/films.png" class="card-title-icon" alt=""> All Films Catalog
            <span class="badge badge-gray"><?= $totalFilms ?></span>
        </div>
        <a href="films.php?add=1" class="btn btn-primary btn-sm">➕ Add New Film</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Poster</th>
                    <th>
                        <div class="sortable-heading">
                            <span>Title</span>
                            <form method="GET" class="column-sort-form">
                                <?php if ($filterCountry !== ''): ?>
                                    <input type="hidden" name="country" value="<?= htmlspecialchars($filterCountry, ENT_QUOTES, 'UTF-8') ?>">
                                <?php endif; ?>
                                <?php if ($filterGenre !== ''): ?>
                                    <input type="hidden" name="genre" value="<?= (int)$filterGenre ?>">
                                <?php endif; ?>
                                <select class="column-sort-select" name="sort" aria-label="Sort films by title" onchange="this.form.submit()">
                                    <option value="" <?= $sortTitle === '' ? 'selected' : '' ?>>Sort title</option>
                                    <option value="title_asc" <?= $sortTitle === 'title_asc' ? 'selected' : '' ?>>A → Z</option>
                                    <option value="title_desc" <?= $sortTitle === 'title_desc' ? 'selected' : '' ?>>Z → A</option>
                                </select>
                            </form>
                        </div>
                    </th>
                    <th>
                        <div class="sortable-heading">
                            <span>Country</span>
                            <form method="GET" class="column-sort-form">
                                <?php if ($sortTitle !== ''): ?>
                                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortTitle, ENT_QUOTES, 'UTF-8') ?>">
                                <?php endif; ?>
                                <?php if ($filterGenre !== ''): ?>
                                    <input type="hidden" name="genre" value="<?= (int)$filterGenre ?>">
                                <?php endif; ?>
                                <select class="column-sort-select" name="country" aria-label="Filter films by country" onchange="this.form.submit()">
                                    <option value="" <?= $filterCountry === '' ? 'selected' : '' ?>>All countries</option>
                                    <?php foreach ($countries as $c): ?>
                                        <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>" <?= $filterCountry === $c ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </th>
                    <th>
                        <div class="sortable-heading">
                            <span>Genre</span>
                            <form method="GET" class="column-sort-form">
                                <?php if ($sortTitle !== ''): ?>
                                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortTitle, ENT_QUOTES, 'UTF-8') ?>">
                                <?php endif; ?>
                                <?php if ($filterCountry !== ''): ?>
                                    <input type="hidden" name="country" value="<?= htmlspecialchars($filterCountry, ENT_QUOTES, 'UTF-8') ?>">
                                <?php endif; ?>
                                <select class="column-sort-select" name="genre" aria-label="Filter films by genre" onchange="this.form.submit()">
                                    <option value="" <?= $filterGenre === '' ? 'selected' : '' ?>>All genres</option>
                                    <?php foreach ($genres as $g): ?>
                                        <option value="<?= (int)$g['id'] ?>" <?= $filterGenre !== '' && $filterGenre === (int)$g['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($g['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </th>
                    <th>Year / Dur</th>
                    <th>Media Links</th>
                    <th>Reviews</th>
                    <th>Rating</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($films)): ?>
                    <tr class="empty-row">
                        <td colspan="10">No films found in directory.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($films as $idx => $f): ?>
                        <tr style="<?= ($editFilm && $editFilm['id'] == $f['id']) ? 'background:var(--glass-hover)' : '' ?>">
                            <!-- Row number -->
                            <td class="mono dim" style="font-size:0.78rem"><?= $offset + $idx + 1 ?></td>

                            <!-- Poster -->
                            <td>
                                <?php if (!empty($f['poster'])): ?>
                                    <img src="<?= htmlspecialchars(posterUrl($f['poster'], '../'), ENT_QUOTES, 'UTF-8') ?>"
                                        alt=""
                                        style="height:52px;width:38px;object-fit:cover;border-radius:4px;border:1px solid var(--border)">
                                <?php else: ?>
                                    <span style="font-size:1.4rem;opacity:0.3">🎬</span>
                                <?php endif; ?>
                            </td>

                            <!-- Title -->
                            <td>
                                <strong><?= htmlspecialchars($f['title']) ?></strong>
                                <a href="../film.php?id=<?= $f['id'] ?>" target="_blank"
                                    style="font-size:0.72rem;color:var(--text-dimmer);margin-left:0.4rem;text-decoration:none"
                                    title="View film on public site">↗</a>
                            </td>

                            <!-- Country -->
                            <td>
                                <span class="badge badge-gray" style="font-size:0.75rem">
                                    <?= htmlspecialchars($f['country'] ?? 'United Kingdom') ?>
                                </span>
                            </td>

                            <!-- Genre -->
                            <td><span class="badge"><?= htmlspecialchars($f['genre_name'] ?? '—') ?></span></td>

                            <!-- Year & Duration -->
                            <td class="mono dim" style="font-size:0.8rem">
                                <?= $f['release_year'] ?: '—' ?><br>
                                <span style="font-size:0.74rem"><?= $f['duration_min'] ? $f['duration_min'] . 'm' : '—' ?></span>
                            </td>

                            <!-- Media: Trailer & Watch Links -->
                            <td>
                                <div style="display:flex;flex-direction:column;gap:0.35rem;font-size:0.75rem">
                                    <?php
                                    $tEmbed = $f['trailer_url'] ?? '';
                                    $tWatch = !empty($tEmbed) ? str_replace('https://www.youtube.com/embed/', 'https://www.youtube.com/watch?v=', $tEmbed) : '';
                                    $wUrl   = $f['watch_url'] ?? '';
                                    ?>
                                    <?php if (!empty($tEmbed)): ?>
                                        <button type="button" class="btn btn-ghost btn-sm js-admin-trailer-btn"
                                            data-title="<?= htmlspecialchars($f['title'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-url="<?= htmlspecialchars($tEmbed, ENT_QUOTES, 'UTF-8') ?>"
                                            style="padding:0.25rem 0.5rem;font-size:0.72rem;justify-content:flex-start;color:#60a5fa;border-color:rgba(96,165,250,0.3)">
                                            🎬 Preview Trailer
                                        </button>
                                        <a href="<?= htmlspecialchars($tWatch) ?>" target="_blank" rel="noopener noreferrer"
                                            style="color:#93c5fd;text-decoration:none;font-size:0.72rem;display:inline-flex;align-items:center;gap:0.2rem" title="Open YouTube video page">
                                            ↗ Trailer on YouTube
                                        </a>
                                    <?php else: ?>
                                        <span class="dim">No trailer</span>
                                    <?php endif; ?>

                                    <?php if (!empty($wUrl)): ?>
                                        <a href="<?= htmlspecialchars($wUrl) ?>" target="_blank" rel="noopener noreferrer"
                                            style="color:#34d399;text-decoration:none;font-size:0.72rem;display:inline-flex;align-items:center;gap:0.2rem" title="Open Full Movie Stream">
                                            🍿 Watch Full Movie ↗
                                        </a>
                                    <?php else: ?>
                                        <span class="dim">No full movie</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Number of reviews -->
                            <td class="mono" style="font-size:0.82rem"><?= $f['review_count'] ?></td>

                            <!-- Average rating -->
                            <td class="text-star mono" style="font-size:0.82rem">
                                <?= $f['avg_rating'] ? number_format($f['avg_rating'], 1) : '—' ?>
                            </td>

                            <!-- Actions -->
                            <td>
                                <div style="display:flex;gap:0.4rem">
                                    <a href="films.php?edit=<?= $f['id'] ?>" class="btn btn-ghost btn-sm" title="Edit Film">✏️</a>
                                    <form method="POST"
                                        onsubmit="return confirm('Delete &quot;<?= htmlspecialchars(addslashes($f['title'])) ?>&quot; and ALL its reviews?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete Film">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
        <?php
        $pagParams = array_filter([
            'edit'    => isset($_GET['edit']) ? (int)$_GET['edit'] : null,
            'sort'    => $sortTitle !== '' ? $sortTitle : null,
            'country' => $filterCountry !== '' ? $filterCountry : null,
            'genre'   => $filterGenre !== '' ? $filterGenre : null,
        ], static fn($v) => $v !== null);
        ?>
        <div style="padding:1rem 1.25rem;border-top:1px solid var(--border);">
            <?= renderPagination($page, $totalPages, 'films.php', $pagParams) ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ADMIN TRAILER MODAL POPUP -->
<div id="adminTrailerModal" class="trailer-modal" aria-hidden="true" role="dialog" aria-modal="true" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center">
    <div class="trailer-modal-backdrop" onclick="closeAdminTrailerModal()" style="position:absolute;inset:0;background:rgba(0,0,0,0.8);backdrop-filter:blur(6px)"></div>
    <div class="trailer-modal-content" style="position:relative;z-index:2;width:min(900px,94vw);background:var(--bg2,#1a1625);border:1px solid var(--border,#3b3252);border-radius:12px;overflow:hidden;box-shadow:0 25px 50px rgba(0,0,0,0.7)">
        <div class="trailer-modal-header" style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--border,#3b3252);background:rgba(255,255,255,0.03)">
            <h3 id="adminTrailerModalTitle" style="font-size:1.05rem;font-weight:700;margin:0;color:var(--text,#fff)">🎬 Trailer Preview</h3>
            <div style="display:flex;align-items:center;gap:0.75rem">
                <a id="adminTrailerModalExternalLink" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm" style="font-size:0.8rem">
                    Open on YouTube ↗
                </a>
                <button type="button" onclick="closeAdminTrailerModal()" style="background:none;border:none;color:var(--text-dim,#aaa);font-size:1.3rem;cursor:pointer;padding:0.2rem 0.5rem">✕</button>
            </div>
        </div>
        <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;background:#000">
            <iframe id="adminTrailerIframe" src="" title="Film Trailer"
                style="position:absolute;top:0;left:0;width:100%;height:100%;border:none"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowfullscreen></iframe>
        </div>
    </div>
</div>

<script>
    // Open trailer preview modal with YouTube autoplay enabled
    function openAdminTrailerModal(title, url) {
        var modal = document.getElementById('adminTrailerModal');
        var iframe = document.getElementById('adminTrailerIframe');
        var titleEl = document.getElementById('adminTrailerModalTitle');
        var extLink = document.getElementById('adminTrailerModalExternalLink');

        var embedUrl = url;
        if (embedUrl.indexOf('autoplay=1') === -1) {
            embedUrl += (embedUrl.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1';
        }

        var watchUrl = url.replace('https://www.youtube.com/embed/', 'https://www.youtube.com/watch?v=');
        if (extLink) extLink.href = watchUrl;

        titleEl.textContent = '🎬 Trailer: ' + title;
        iframe.src = embedUrl;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    // Close trailer modal and stop playback by clearing iframe src
    function closeAdminTrailerModal() {
        var modal = document.getElementById('adminTrailerModal');
        var iframe = document.getElementById('adminTrailerIframe');
        iframe.src = '';
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.js-admin-trailer-btn');
        if (btn) {
            e.preventDefault();
            var title = btn.getAttribute('data-title') || 'Trailer';
            var url = btn.getAttribute('data-url') || '';
            if (url) openAdminTrailerModal(title, url);
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('adminTrailerModal').style.display === 'flex') {
            closeAdminTrailerModal();
        }
    });
</script>

<?php if ($showForm): ?>
<!-- TinyMCE Rich Text Editor for Admin -->
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
            height: 180,
            branding: false,
            promotion: false,
            placeholder: 'Enter plot summary, backstory, or awards...',
            setup: function(editor) {
                editor.on('change keyup NodeChange', function() {
                    editor.save();
                });
            }
        });
    });
</script>
<?php endif; ?>

<?php
$content = ob_get_clean(); // Capture buffered films management HTML into $content
require __DIR__ . '/layout.php'; // Render $content inside the shared admin layout shell
