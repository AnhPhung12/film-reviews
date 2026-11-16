<?php
/**
 * admin/reviewers.php — Admin Reviewers Management Controller.
 *
 * Purpose:     Lists registered reviewers with search and sorting, and enables
 *              administrators to edit account details, suspend/reinstate users,
 *              or permanently delete reviewers and their reviews.
 * Connections: Protected by Login/auth.php; uses ReviewerController (OOP),
 *              CsrfHelper, and PresentationHelper::avatarHtml(); renders inside
 *              admin/layout.php.
 * Security:    Verifies CSRF tokens via CsrfHelper::verify() on all POST actions,
 *              validates sort keys against an allowlist, and escapes dynamic
 *              HTML and JS modal arguments safely.
 */

require_once __DIR__ . '/Login/auth.php'; // Enforce admin authentication guard
require_once __DIR__ . '/../includes/classes/ReviewerController.php'; // Load ReviewerController class
require_once __DIR__ . '/../includes/classes/CsrfHelper.php'; // Load CSRF token helper

$reviewerCtrl = new ReviewerController(); // Instantiate reviewer controller

// Page configuration
$pageTitle  = 'Reviewers'; // Set page title in admin topbar
$activePage = 'reviewers'; // Highlight Reviewers item in sidebar navigation

// ── HANDLE POST ACTIONS (EDIT / SUSPEND / DELETE) ──
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') { // Process reviewer management actions on HTTP POST
    $action = $_POST['action'] ?? ''; // Determine requested admin action
    $query  = http_build_query([ // Preserve current search and sort parameters across redirect
        'search' => trim($_GET['search'] ?? ''), // Keep active search keyword
        'sort'   => $_GET['sort'] ?? 'registered_desc', // Keep active sort order
    ]);
    $redirectUrl = 'reviewers.php' . ($query !== '' ? '?' . $query : ''); // Build PRG redirect URL

    // Verify CSRF token before processing any state change
    if (!CsrfHelper::verify($_POST['_csrf'] ?? null)) { // Reject requests missing a valid CSRF token
        $_SESSION['flash']      = 'Security token expired. Please try again.'; // Set CSRF error flash
        $_SESSION['flash_type'] = 'error'; // Style flash as error
        header('Location: ' . $redirectUrl); // Redirect back with preserved filters
        exit; // Stop execution
    }

    if ($action === 'edit') { // Update reviewer name, email, and optional password
        $id       = (int)($_POST['id'] ?? 0); // Cast target reviewer ID to integer
        $fullname = trim($_POST['fullname'] ?? ''); // Read and trim updated full name
        $email    = trim($_POST['email'] ?? ''); // Read and trim updated email address
        $password = $_POST['password'] ?? ''; // Read optional new password

        $res = $reviewerCtrl->adminUpdate($id, $fullname, $email, $password !== '' ? $password : null); // Persist admin edits (only update password if non-empty)
        if ($res === true) { // Check if update succeeded
            $_SESSION['flash']      = 'Reviewer updated successfully.'; // Set success flash
            $_SESSION['flash_type'] = 'success'; // Style flash as success
        } else {
            $_SESSION['flash']      = is_string($res) ? $res : 'Could not update reviewer.'; // Display specific validation/DB error
            $_SESSION['flash_type'] = 'error'; // Style flash as error
        }
        header('Location: ' . $redirectUrl); // Redirect via PRG pattern
        exit; // Stop execution
    } elseif ($action === 'toggle_suspend') { // Suspend or reinstate reviewer login access
        $id        = (int)($_POST['id'] ?? 0); // Cast target reviewer ID to integer
        $suspended = (int)($_POST['suspended'] ?? 0) === 1; // Convert submitted flag to boolean
        $ok        = $reviewerCtrl->adminToggleSuspend($id, $suspended); // Toggle is_suspended status in database
        $_SESSION['flash'] = $ok // Set outcome flash message
            ? ($suspended ? 'User account suspended. They can no longer log in.' : 'User account reinstated.')
            : 'Could not update this user account.';
        $_SESSION['flash_type'] = $ok ? 'success' : 'error'; // Style flash based on operation result
        header('Location: ' . $redirectUrl); // Redirect via PRG pattern
        exit; // Stop execution
    } elseif ($action === 'delete') { // Permanently delete reviewer and their reviews
        $id                     = (int)($_POST['id'] ?? 0); // Cast target reviewer ID to integer
        $ok                     = $reviewerCtrl->adminDelete($id); // Delete reviewer account and cascade reviews
        $_SESSION['flash']      = $ok ? 'Reviewer and all their reviews have been deleted.' : 'Could not delete reviewer.'; // Set outcome flash
        $_SESSION['flash_type'] = $ok ? 'success' : 'error'; // Style flash based on result
        header('Location: reviewers.php'); // Redirect to reviewers list
        exit; // Stop execution
    }
}

// Retrieve and clear one-time flash message
$flash     = $_SESSION['flash']      ?? ''; // Read pending flash notification
$flashType = $_SESSION['flash_type'] ?? 'success'; // Default flash type to 'success'
unset($_SESSION['flash'], $_SESSION['flash_type']); // Remove flash keys so alert appears once

// Parse search query and validate sort option against allowlist
$search      = trim($_GET['search'] ?? ''); // Read and trim search keyword
$sort        = $_GET['sort'] ?? 'registered_desc'; // Read requested sort key
$sortOptions = ['registered_desc', 'registered_asc', 'rating_desc', 'rating_asc']; // Allowed sort keys whitelist
if (!in_array($sort, $sortOptions, true)) { // Fall back to default sort if an invalid key is supplied
    $sort = 'registered_desc'; // Default to newest registrations first
}
$reviewers = $reviewerCtrl->listFiltered($search, $sort); // Fetch filtered and sorted reviewer list

ob_start(); // Start output buffering to capture reviewers table HTML
?>

<!-- SEARCH FORM -->
<div class="form-card" style="padding:1.25rem">
    <form method="GET" class="form-filter" style="align-items:flex-end;gap:.75rem;flex-wrap:wrap">
        <div class="form-group grow">
            <label class="form-label">Search by name or email</label>
            <input class="form-input" type="text" name="search"
                value="<?= htmlspecialchars($search) ?>" placeholder="Name or email…">
        </div>
        <div class="form-group" style="min-width:250px">
            <label class="form-label" for="reviewer-sort">Sort users</label>
            <select class="form-select" id="reviewer-sort" name="sort">
                <option value="registered_desc" <?= $sort === 'registered_desc' ? 'selected' : '' ?>>Newest registration</option>
                <option value="registered_asc" <?= $sort === 'registered_asc' ? 'selected' : '' ?>>Oldest registration</option>
                <option value="rating_desc" <?= $sort === 'rating_desc' ? 'selected' : '' ?>>Rating: high to low</option>
                <option value="rating_asc" <?= $sort === 'rating_asc' ? 'selected' : '' ?>>Rating: low to high</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Search / Sort</button>
        <?php if ($search || $sort !== 'registered_desc'): ?><a href="reviewers.php" class="btn btn-ghost">Clear</a><?php endif; ?>
    </form>
</div>

<!-- REVIEWERS TABLE -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <img src="icons/reviewers.png" class="card-title-icon" alt=""> Reviewers
            <span class="badge badge-gray"><?= count($reviewers) ?></span>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Avatar</th>
                    <th>Email</th>
                    <th>Reviews</th>
                    <th>Avg Rating</th>
                    <th>Joined Date</th>
                    <th>Joined Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reviewers)): ?>
                    <tr class="empty-row">
                        <td colspan="10">No reviewers found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reviewers as $idx => $rv): ?>
                        <tr>
                            <td class="mono dim" style="font-size:0.78rem"><?= $idx + 1 ?></td>
                            <td>
                                <div style="font-weight:600"><?= htmlspecialchars($rv['fullname']) ?></div>
                            </td>
                            <td><?= PresentationHelper::avatarHtml($rv['avatar'] ?? null, $rv['fullname'], 'admin-reviewer-avatar', '../') ?></td>
                            <td class="mono" style="font-size:0.78rem;color:var(--text-dim)">
                                <?= htmlspecialchars($rv['email']) ?>
                            </td>
                            <td>
                                <?php if ($rv['review_count'] > 0): ?>
                                    <span class="badge badge-blue"><?= (int)$rv['review_count'] ?> review<?= $rv['review_count'] != 1 ? 's' : '' ?></span>
                                <?php else: ?>
                                    <span class="badge badge-gray">No reviews</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-star mono" style="font-size:0.82rem">
                                <?= $rv['avg_rating'] ? number_format((float)$rv['avg_rating'], 1) : '—' ?>
                            </td>
                            <td class="mono dim" style="font-size:0.72rem;white-space:nowrap"
                                <?php if (!empty($rv['registered_at'])): ?>data-local-dt="<?= htmlspecialchars(date('Y-m-d\TH:i:s+07:00', strtotime($rv['registered_at'])), ENT_QUOTES, 'UTF-8') ?>" data-local-fmt="date"<?php endif; ?>>
                                <?= !empty($rv['registered_at']) ? htmlspecialchars(date('Y-m-d', strtotime($rv['registered_at']))) : 'Unknown' ?>
                            </td>
                            <td class="mono dim" style="font-size:0.72rem;white-space:nowrap"
                                <?php if (!empty($rv['registered_at'])): ?>data-local-dt="<?= htmlspecialchars(date('Y-m-d\TH:i:s+07:00', strtotime($rv['registered_at'])), ENT_QUOTES, 'UTF-8') ?>" data-local-fmt="time"<?php endif; ?>>
                                <?= !empty($rv['registered_at']) ? htmlspecialchars(date('H:i:s', strtotime($rv['registered_at']))) : 'Unknown' ?>
                            </td>
                            <td>
                                <span class="badge <?= !empty($rv['is_suspended']) ? 'badge-red' : 'badge-green' ?>">
                                    <?= !empty($rv['is_suspended']) ? 'Suspended' : 'Active' ?>
                                </span>
                            </td>
                            <td>
                                <div class="reviewer-actions">
                                    <button type="button" class="btn btn-ghost btn-sm"
                                        onclick='openEditReviewerModal(<?= (int)$rv["id"] ?>, <?= json_encode($rv["fullname"]) ?>, <?= json_encode($rv["email"]) ?>)'>
                                        Edit
                                    </button>
                                    <form method="POST" action="reviewers.php?<?= htmlspecialchars(http_build_query(['search' => $search, 'sort' => $sort]), ENT_QUOTES, 'UTF-8') ?>"
                                        onsubmit="return confirm('<?= !empty($rv['is_suspended']) ? 'Reinstate' : 'Suspend' ?> <?= htmlspecialchars(addslashes($rv['fullname']), ENT_QUOTES, 'UTF-8') ?>?')">
                                        <?= CsrfHelper::input() ?>
                                        <input type="hidden" name="action" value="toggle_suspend">
                                        <input type="hidden" name="id" value="<?= (int)$rv['id'] ?>">
                                        <input type="hidden" name="suspended" value="<?= !empty($rv['is_suspended']) ? '0' : '1' ?>">
                                        <button type="submit" class="btn <?= !empty($rv['is_suspended']) ? 'btn-reinstate' : 'btn-suspend' ?> btn-sm">
                                            <?= !empty($rv['is_suspended']) ? 'Reinstate' : 'Suspend' ?>
                                        </button>
                                    </form>
                                    <form method="POST" action="reviewers.php?<?= htmlspecialchars(http_build_query(['search' => $search, 'sort' => $sort]), ENT_QUOTES, 'UTF-8') ?>"
                                        onsubmit="return confirm('Delete &quot;<?= htmlspecialchars(addslashes($rv['fullname'])) ?>&quot; and ALL their reviews? This cannot be undone.')">
                                        <?= CsrfHelper::input() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$rv['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete reviewer">🗑️</button>
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

<!-- EDIT REVIEWER MODAL (W3.CSS) -->
<div id="editReviewerModal" class="w3-modal" style="z-index:2000;background-color:rgba(10,8,12,0.75);">
    <div class="w3-modal-content w3-card-4 w3-animate-top" style="max-width:480px;background:#1e1820;color:#f0f7f4;border:1px solid rgba(255,255,255,0.14);border-radius:14px;padding:1.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.1rem;">
            <h3 style="margin:0;font-size:1.1rem;font-weight:700;color:#99e1d9;">Edit Reviewer</h3>
            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('editReviewerModal').style.display='none'">&times;</button>
        </div>
        <form method="POST" action="reviewers.php?<?= htmlspecialchars(http_build_query(['search' => $search, 'sort' => $sort]), ENT_QUOTES, 'UTF-8') ?>">
            <?= CsrfHelper::input() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-rv-id">
            <div class="form-group" style="margin-bottom:0.9rem;">
                <label class="form-label" for="edit-rv-fullname">Full Name *</label>
                <input class="form-input" id="edit-rv-fullname" type="text" name="fullname" required maxlength="150">
            </div>
            <div class="form-group" style="margin-bottom:0.9rem;">
                <label class="form-label" for="edit-rv-email">Email Address *</label>
                <input class="form-input" id="edit-rv-email" type="email" name="email" required maxlength="150">
            </div>
            <div class="form-group" style="margin-bottom:1.25rem;">
                <label class="form-label" for="edit-rv-password">New Password (leave blank to keep current)</label>
                <input class="form-input" id="edit-rv-password" type="password" name="password" minlength="6" placeholder="Optional">
            </div>
            <div style="display:flex;justify-content:flex-end;gap:0.6rem;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('editReviewerModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// Populate and display the Edit Reviewer modal
function openEditReviewerModal(id, fullname, email) {
    document.getElementById('edit-rv-id').value       = id;
    document.getElementById('edit-rv-fullname').value = fullname;
    document.getElementById('edit-rv-email').value    = email;
    document.getElementById('edit-rv-password').value = '';
    document.getElementById('editReviewerModal').style.display = 'block';
}
</script>

<?php
$content = ob_get_clean(); // Capture buffered reviewers HTML into $content
require __DIR__ . '/layout.php'; // Render $content inside the shared admin layout shell
