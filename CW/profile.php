<?php

/**
 * profile.php — Authenticated User Profile & Avatar Management Controller.
 *
 * Purpose:     Displays the user's profile, personal review history, and stats,
 *              and handles avatar updates (custom image upload, preset avatar
 *              selection, or avatar removal).
 * Connections: Uses UserRepository and DatabaseConnection (PDO) directly; renders
 *              templates/profile.html.php inside templates/layout.html.php.
 * Security:    Enforces session login, verifies CSRF token via hash_equals(),
 *              validates uploaded image MIME type with finfo_file() and size
 *              (<= 2MB), and cleans up replaced custom avatar files on disk.
 */

if (session_status() === PHP_SESSION_NONE) { // Start session only if not already active
    session_start(); // Initialize PHP session
}
require_once __DIR__ . '/includes/DataBaseFunctions.php'; // Load UserRepository and DatabaseConnection

// Redirect unauthenticated visitors to login
if (empty($_SESSION['user_id'])) { // Enforce authentication before viewing profile
    $_SESSION['flash']      = 'Please log in to view your profile.'; // Set login prompt flash
    $_SESSION['flash_type'] = 'error'; // Style flash as error
    header('Location: loginUser.php'); // Redirect guest to login page
    exit; // Stop script execution
}
// Profile
$pageTitle    = 'My Profile'; // Set page title for layout template
$flashMessage = ''; // Initialize inline alert message
$flashType    = 'success'; // Default inline alert style
$repository   = new UserRepository(); // Instantiate user repository
$presetAvatars      = $repository->getPresetAvatars(); // Load DB-backed preset avatar options
$allowedPresetPaths = array_column($presetAvatars, 'path'); // Extract valid preset paths for strict allowlist checks
$user         = $repository->findById((int)$_SESSION['user_id']); // Fetch current reviewer record

// Destroy stale session if user record no longer exists
if (!$user) { // Handle deleted or missing user record
    session_destroy(); // Clear invalid session
    header('Location: loginUser.php'); // Redirect to login page
    exit; // Stop execution
}

// Generate per-session CSRF token for profile forms
$_SESSION['profile_token'] ??= bin2hex(random_bytes(32)); // Create 64-char hex CSRF token once per session

// Handle avatar update POST submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') { // Process avatar changes on HTTP POST
    $action = $_POST['action'] ?? 'update_avatar'; // Read submitted form action

    if (!hash_equals($_SESSION['profile_token'], $_POST['token'] ?? '')) { // Constant-time CSRF token validation
        $flashMessage = 'Your session expired. Please try again.'; // Reject invalid or expired CSRF token
        $flashType    = 'error'; // Style alert as error
    } elseif ($action === 'update_message') { // Handle owner-only edit of a sent contact message
        $msgId   = (int)($_POST['message_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if ($msgId <= 0 || empty($subject) || mb_strlen($content) < 10) {
            $flashMessage = 'Please provide a subject and at least 10 characters for your message.';
            $flashType    = 'error';
        } else {
            try {
                $db   = DatabaseConnection::getInstance()->getConnection();
                $stmt = $db->prepare('UPDATE contact_us SET subject = ?, content = ? WHERE id = ? AND reviewer_id = ? AND is_active = 1');
                $stmt->execute([$subject, $content, $msgId, (int)$user['id']]);
                $_SESSION['flash']      = 'Your message has been updated.';
                $_SESSION['flash_type'] = 'success';
                header('Location: profile.php#mySentMessages');
                exit;
            } catch (Throwable $e) {
                $flashMessage = 'Could not update your message.';
                $flashType    = 'error';
            }
        }
    } elseif ($action === 'delete_message') { // Handle owner-only soft-delete of a sent contact message
        $msgId = (int)($_POST['message_id'] ?? 0);
        if ($msgId > 0) {
            try {
                $db   = DatabaseConnection::getInstance()->getConnection();
                $stmt = $db->prepare('UPDATE contact_us SET is_active = 0 WHERE id = ? AND reviewer_id = ?');
                $stmt->execute([$msgId, (int)$user['id']]);
                $_SESSION['flash']      = 'Your message has been deleted.';
                $_SESSION['flash_type'] = 'success';
                header('Location: profile.php#mySentMessages');
                exit;
            } catch (Throwable $e) {
                $flashMessage = 'Could not delete your message.';
                $flashType    = 'error';
            }
        }
    } else {
        $removeAvatar   = ($_POST['remove_avatar'] ?? '') === '1'; // Check if user requested avatar removal
        $selectedPreset = trim($_POST['preset_avatar'] ?? ''); // Read selected preset avatar path
        $newAvatarPath  = null; // Will hold new relative avatar path if uploaded or selected
        $uploadError    = ''; // Track file upload validation errors

        // Process custom file upload if provided
        if (!empty($_FILES['avatar']['name'])) { // Check if a custom avatar file was uploaded
            if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) { // Verify PHP upload completed without error
                $uploadError = 'Could not upload avatar image.'; // Set upload failure message
            } else {
                $allowed = [ // Map allowed image MIME types to safe file extensions
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                    'image/gif'  => 'gif',
                ];
                // Inspect actual file MIME type rather than trusting filename extension
                $finfo = finfo_open(FILEINFO_MIME_TYPE); // Open Fileinfo resource
                $mime  = finfo_file($finfo, $_FILES['avatar']['tmp_name']); // Detect true MIME type from file bytes
                finfo_close($finfo); // Close Fileinfo resource

                if (!isset($allowed[$mime])) { // Reject disallowed file types
                    $uploadError = 'Only JPG, PNG, WEBP, and GIF avatar images are allowed.'; // Set MIME validation error
                } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) { // Enforce 2MB maximum file size
                    $uploadError = 'Avatar image must be smaller than 2MB.'; // Set file size error
                } else {
                    $bytes = file_get_contents($_FILES['avatar']['tmp_name']); // Read uploaded image bytes for DB BLOB storage
                    if ($bytes === false || $bytes === '') { // Verify image bytes were read successfully
                        $uploadError = 'Could not read uploaded avatar image.'; // Report read failure
                    } else {
                        $newAvatarPath = 'image.php?type=avatar&id=' . (int)$user['id']; // Stream URL served directly from MySQL BLOB
                        $uploadedBlob  = ['bytes' => $bytes, 'mime' => $mime]; // Stage binary payload for DB persistence
                    }
                }
            }
        } elseif ($selectedPreset !== '' && in_array($selectedPreset, $allowedPresetPaths, true)) { // Accept preset only if in allowlist
            $newAvatarPath = $selectedPreset; // Use validated preset avatar path
        }

        if ($uploadError !== '') { // Abort update if upload validation failed
            $flashMessage = $uploadError; // Show upload error message
            $flashType    = 'error'; // Style alert as error
        } else {
            $clearAvatar = $removeAvatar && $newAvatarPath === null; // Clear avatar only when removal is requested without a replacement
            if (isset($uploadedBlob)) { // Persist custom uploaded avatar as MySQL BLOB
                $result = $repository->updateAvatarBlob((int)$user['id'], $uploadedBlob['bytes'], $uploadedBlob['mime'], $newAvatarPath);
            } else { // Persist preset path or clear avatar
                $result = $repository->update((int)$user['id'], $user['fullname'], $user['email'], null, $newAvatarPath, $clearAvatar);
            }

            if ($result === true) { // Proceed with cleanup and session sync on success
                // Remove old custom uploaded avatar file (preserve built-in presets)
                $oldAvatar     = $user['avatar'] ?? ''; // Read previous avatar path
                $changedAvatar = ($newAvatarPath !== null && $newAvatarPath !== $oldAvatar) || $clearAvatar; // Check if avatar actually changed
                if ($changedAvatar && is_string($oldAvatar) && str_starts_with($oldAvatar, 'avatars/') && !str_starts_with($oldAvatar, 'avatars/presets/')) { // Only delete user-uploaded files
                    $oldFullPath = __DIR__ . '/' . $oldAvatar; // Build absolute path to old avatar file
                    if (is_file($oldFullPath)) { // Confirm file exists on disk
                        @unlink($oldFullPath); // Delete replaced custom avatar file
                    }
                }

                // Sync updated avatar path into active session
                if ($clearAvatar) { // Reset session avatar when removed
                    $_SESSION['user_avatar'] = ''; // Clear session avatar key
                    $user['avatar']          = ''; // Update local user array
                } elseif ($newAvatarPath !== null) { // Update session avatar when changed
                    $_SESSION['user_avatar'] = $newAvatarPath; // Store new avatar path in session
                    $user['avatar']          = $newAvatarPath; // Update local user array
                }

                $_SESSION['flash']      = 'Your avatar has been updated.'; // Set success flash
                $_SESSION['flash_type'] = 'success'; // Style flash as success
                header('Location: profile.php'); // Redirect using PRG pattern
                exit; // Stop execution
            }

            // Clean up newly uploaded file if database update failed
            if ($newAvatarPath !== null && !str_starts_with($newAvatarPath, 'avatars/presets/') && is_file(__DIR__ . '/' . $newAvatarPath)) { // Avoid orphaned upload files
                @unlink(__DIR__ . '/' . $newAvatarPath); // Remove newly uploaded file on DB failure
            }
            $flashMessage = is_string($result) ? $result : 'Could not update your avatar.'; // Show repository error
            $flashType    = 'error'; // Style alert as error
        }
    }
}

// Retrieve one-time flash message if none set during POST
if (empty($flashMessage)) { // Only read session flash if no inline error is active
    $flashMessage = $_SESSION['flash']      ?? ''; // Read pending flash message
    $flashType    = $_SESSION['flash_type'] ?? 'success'; // Default flash type to 'success'
    unset($_SESSION['flash'], $_SESSION['flash_type']); // Clear flash keys after reading
}

// Fetch user's published reviews and active sent contact messages
$userReviews  = []; // Initialize user's review list
$userMessages = []; // Initialize user's sent messages list
$messageCount = 0; // Initialize sent contact message counter
try {
    $db = DatabaseConnection::getInstance()->getConnection(); // Acquire shared PDO connection

    // User's reviews joined with film and genre metadata
    $revStmt = $db->prepare("
        SELECT r.*, r.reviewtext AS text, r.reviewdate AS date, r.reviewtime AS time, 
               f.title AS film_title, f.poster AS film_poster, f.id AS film_id,
               g.name AS genre_name
        FROM review r
        JOIN film f ON r.filmid = f.id
        LEFT JOIN genre g ON f.genreid = g.id
        WHERE r.reviewerid = ?
        ORDER BY r.reviewdate DESC, r.reviewtime DESC
    "); // Prepare query for user's reviews ordered newest first
    $revStmt->execute([(int)$user['id']]); // Execute prepared statement bound to user ID
    $userReviews = $revStmt->fetchAll(PDO::FETCH_ASSOC); // Fetch all matching review rows

    // Active contact messages sent by this user (newest first)
    (new CinemaRepository())->ensureContactReplyColumns();
    $msgStmt = $db->prepare("SELECT id, subject, content, sent_at, admin_reply, replied_at FROM contact_us WHERE reviewer_id = ? AND is_active = 1 ORDER BY sent_at DESC");
    $msgStmt->execute([(int)$user['id']]);
    $userMessages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
    $messageCount = count($userMessages);
} catch (Throwable $e) { // Gracefully degrade if profile stats query fails
    $userReviews  = []; // Reset reviews list on error
    $userMessages = []; // Reset messages list on error
    $messageCount = 0; // Reset message count on error
}

// Compute profile summary metrics
$reviewCount = count($userReviews); // Count total reviews authored by this user
$avgRating   = $reviewCount > 0 ? round(array_sum(array_column($userReviews, 'rating')) / $reviewCount, 1) : 0; // Compute average star rating rounded to 1 decimal

// Render profile view inside shared layout
ob_start(); // Begin output buffering to capture profile template
require __DIR__ . '/templates/profile.html.php'; // Render user profile view
$content = ob_get_clean(); // Capture rendered HTML into $content
require __DIR__ . '/templates/layout.html.php'; // Wrap $content inside the shared site layout
