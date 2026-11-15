<?php
/**
 * admin/genres.php — Admin Genres CRUD Controller.
 *
 * Purpose:     Allows administrators to add, edit, and delete film genres while
 *              displaying assigned film counts per genre.
 * Connections: Protected by Login/auth.php; uses GenreController (OOP) and
 *              DataBaseFunctions helpers (getGenreById, getAllGenresWithFilmCount);
 *              renders inside admin/layout.php.
 * Security:    Catches duplicate-name PDOException errors, prevents deletion of
 *              genres with assigned films, escapes output via htmlspecialchars(),
 *              and uses PRG redirects.
 */

require_once __DIR__ . '/Login/auth.php'; // Enforce admin authentication guard
require_once __DIR__ . '/../includes/classes/GenreController.php'; // Load GenreController class
$genreCtrl = new GenreController(); // Instantiate genre controller for CRUD operations

$pageTitle  = 'Genres'; // Set page title in admin topbar
$activePage = 'genres'; // Highlight Genres item in sidebar navigation

// Retrieve and clear one-time flash message from session
$flash     = $_SESSION['flash']      ?? ''; // Read pending flash notification
$flashType = $_SESSION['flash_type'] ?? 'success'; // Default flash type to 'success'
unset($_SESSION['flash'], $_SESSION['flash_type']); // Remove flash keys so alert appears once

// Handle POST CRUD actions (add, edit, delete)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') { // Process genre mutations on HTTP POST
    $action = $_POST['action'] ?? ''; // Determine requested CRUD operation

    if ($action === 'add') { // Create a new genre record
        $name        = trim($_POST['name'] ?? ''); // Read and trim submitted genre name
        $description = trim($_POST['description'] ?? ''); // Read and trim optional genre description

        if ($name === '') { // Require non-empty genre name
            $_SESSION['flash']      = 'Genre name cannot be empty.'; // Set validation error flash
            $_SESSION['flash_type'] = 'error'; // Style flash as error
        } else {
            try {
                if ($genreCtrl->create($name, $description)) { // Persist new genre via GenreController
                    $_SESSION['flash']      = 'Genre "' . htmlspecialchars($name) . '" has been created successfully.'; // Set success flash
                    $_SESSION['flash_type'] = 'success'; // Style flash as success
                } else {
                    $_SESSION['flash']      = 'Could not create genre.'; // Set generic failure flash
                    $_SESSION['flash_type'] = 'error'; // Style flash as error
                }
            } catch (PDOException $e) { // Catch unique constraint violation on duplicate genre name
                $_SESSION['flash']      = 'A genre with this name already exists.'; // Report duplicate genre name
                $_SESSION['flash_type'] = 'error'; // Style flash as error
            }
        }
        header('Location: genres.php'); // Redirect using PRG pattern
        exit; // Stop script execution after redirect
    } elseif ($action === 'edit') { // Update an existing genre record
        $id          = (int)($_POST['id'] ?? 0); // Cast genre ID to integer
        $name        = trim($_POST['name'] ?? ''); // Read and trim updated genre name
        $description = trim($_POST['description'] ?? ''); // Read and trim updated description

        if ($id <= 0 || $name === '') { // Validate positive ID and non-empty name
            $_SESSION['flash']      = 'Please enter a valid genre name.'; // Set validation error flash
            $_SESSION['flash_type'] = 'error'; // Style flash as error
        } else {
            try {
                if ($genreCtrl->update($id, $name, $description)) { // Update genre row in database
                    $_SESSION['flash']      = 'Genre updated successfully.'; // Set success flash
                    $_SESSION['flash_type'] = 'success'; // Style flash as success
                } else {
                    $_SESSION['flash']      = 'Could not update genre.'; // Set generic update failure flash
                    $_SESSION['flash_type'] = 'error'; // Style flash as error
                }
            } catch (PDOException $e) { // Catch unique constraint violation on duplicate genre name
                $_SESSION['flash']      = 'A genre with this name already exists.'; // Report duplicate genre name
                $_SESSION['flash_type'] = 'error'; // Style flash as error
            }
        }
        header('Location: genres.php'); // Redirect using PRG pattern
        exit; // Stop script execution after redirect
    } elseif ($action === 'delete') { // Delete a genre if it has no assigned films
        $id = (int)($_POST['id'] ?? 0); // Cast target genre ID to integer
        if ($id <= 0) { // Reject invalid or zero genre ID
            $_SESSION['flash']      = 'Invalid genre ID.'; // Set invalid ID flash
            $_SESSION['flash_type'] = 'error'; // Style flash as error
        } else {
            if ($genreCtrl->delete($id)) { // Attempt deletion (blocked by controller if films reference it)
                $_SESSION['flash']      = 'Genre deleted successfully.'; // Set deletion success flash
                $_SESSION['flash_type'] = 'success'; // Style flash as success
            } else {
                $_SESSION['flash']      = 'Cannot delete this genre because there are films currently assigned to it. Please reassign or delete those films first.'; // Explain referential integrity block
                $_SESSION['flash_type'] = 'error'; // Style flash as error
            }
        }
        header('Location: genres.php'); // Redirect using PRG pattern
        exit; // Stop script execution after redirect
    }
}

// Populate edit form when ?edit=ID is requested
$editId    = (int)($_GET['edit'] ?? 0); // Read optional edit genre ID from query string
$editGenre = null; // Default edit record to null
if ($editId > 0) { // Load genre row only when a valid edit ID is present
    $editGenre = getGenreById($editId); // Fetch genre details for pre-filling the form
}

// Fetch all genres with assigned film counts
$genres = getAllGenresWithFilmCount(); // Query all genres along with their assigned film counts

ob_start(); // Start output buffering to capture genres management view
?>

<!-- FLASH NOTIFICATION -->
<?php if (!empty($flash)): ?>
    <div class="alert alert-<?= $flashType === 'error' ? 'danger' : 'success' ?>">
        <?= htmlspecialchars($flash) ?>
    </div>
<?php endif; ?>

<!-- FORM: ADD OR EDIT GENRE -->
<div class="form-card" id="genreFormCard">
    <div class="form-title">
        <?= $editGenre ? '✏️ Edit Genre: ' . htmlspecialchars($editGenre['name']) : '➕ Add New Genre' ?>
    </div>
    <form method="POST" action="genres.php">
        <input type="hidden" name="action" value="<?= $editGenre ? 'edit' : 'add' ?>">
        <?php if ($editGenre): ?>
            <input type="hidden" name="id" value="<?= (int)$editGenre['id'] ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label" for="genre_name">Genre Name *</label>
                <input class="form-input" type="text" id="genre_name" name="name"
                    value="<?= htmlspecialchars($editGenre['name'] ?? '') ?>"
                    required placeholder="e.g. Science Fiction, Animation, Comedy" maxlength="100">
            </div>

            <div class="form-group grow">
                <label class="form-label" for="genre_desc">Description</label>
                <input class="form-input" type="text" id="genre_desc" name="description"
                    value="<?= htmlspecialchars($editGenre['description'] ?? '') ?>"
                    placeholder="Brief summary of the genre style and themes" maxlength="500">
            </div>
        </div>

        <div class="form-footer" style="margin-top:1.25rem">
            <?php if ($editGenre): ?>
                <a href="genres.php" class="btn btn-ghost">Cancel</a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">
                <?= $editGenre ? '💾 Save Changes' : '➕ Add Genre' ?>
            </button>
        </div>
    </form>
</div>

<!-- ALL GENRES DIRECTORY TABLE -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <img src="icons/genres.png" class="card-title-icon" alt=""> All Movie Genres
            <span class="badge badge-gray"><?= count($genres) ?></span>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:60px">ID</th>
                    <th>Genre Name</th>
                    <th>Description</th>
                    <th style="width:130px;text-align:center">Assigned Films</th>
                    <th style="width:160px;text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($genres)): ?>
                    <tr>
                        <td colspan="5" class="empty-state">No genres found in the database.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($genres as $g):
                        $fCount = (int)($g['film_count'] ?? 0);
                    ?>
                        <tr>
                            <td class="dim">#<?= (int)$g['id'] ?></td>
                            <td>
                                <strong style="color:var(--text);font-size:0.95rem">
                                    <?= htmlspecialchars($g['name']) ?>
                                </strong>
                            </td>
                            <td style="color:var(--text-dim);font-size:0.88rem;max-width:380px">
                                <?= !empty($g['description']) ? htmlspecialchars($g['description']) : '<span class="dim">—</span>' ?>
                            </td>
                            <td style="text-align:center">
                                <span class="badge <?= $fCount > 0 ? 'badge-primary' : 'badge-gray' ?>">
                                    <?= $fCount ?> <?= $fCount === 1 ? 'film' : 'films' ?>
                                </span>
                            </td>
                            <td style="text-align:center">
                                <div style="display:inline-flex;gap:0.4rem;align-items:center">
                                    <a href="genres.php?edit=<?= (int)$g['id'] ?>#genreFormCard"
                                        class="btn btn-ghost btn-sm" title="Edit genre">
                                        ✏️ Edit
                                    </a>
                                    <form method="POST" action="genres.php" style="display:inline"
                                        onsubmit="return confirm('Delete genre <?= htmlspecialchars(addslashes($g['name'])) ?>?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"
                                            <?= $fCount > 0 ? 'title="Cannot delete genre with assigned films"' : 'title="Delete genre"' ?>>
                                            🗑️ Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean(); // Capture buffered genres HTML into $content
require __DIR__ . '/layout.php'; // Render $content inside the shared admin layout shell
