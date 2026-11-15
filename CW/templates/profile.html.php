<?php
/**
 * profile.html.php — User Profile Hub Template.
 * Purpose: Displays account banner, interactive avatar picker (presets, custom upload, or initial fallback),
 *          4 activity stat cards, and the user's personal film reviews with edit/delete actions.
 * Connections:
 *   - Rendered by: profile.php; wrapped inside templates/layout.html.php.
 *   - Expects: $user, $presetAvatars, $reviewCount, $avgRating, $messageCount, $userReviews, $_SESSION['profile_token'].
 * OOP/Patterns: View Template (MVC) with CSRF token hidden input and FileReader live preview.
 * Data Structures & Algorithms:
 *   - Converts $user['registered_at'] to formatted date/time strings via strtotime()/date().
 *   - Client-side JS manages mutually exclusive avatar selection states (preset vs. upload vs. reset).
 *
 * @var array $user
 * @var array $presetAvatars
 * @var int   $reviewCount
 * @var float $avgRating
 * @var int   $messageCount
 * @var array $userReviews
 */

$currentAvatar = $user['avatar'] ?? ''; // Currently saved avatar path or empty string
$userInitial   = strtoupper(mb_substr(trim($user['fullname'] ?? 'U') ?: 'U', 0, 1)); // Fallback initial letter for avatar badge
$registeredAt  = $user['registered_at'] ?? ''; // Account registration timestamp
$registeredTimestamp = !empty($registeredAt) ? strtotime($registeredAt) : time(); // Parse timestamp or default to current time
$joinedDateStr = date('Y-m-d', $registeredTimestamp); // Format join date (YYYY-MM-DD)
$joinedTimeStr = date('H:i:s', $registeredTimestamp); // Format join time (HH:MM:SS)
?>

<section class="section page-section profile-section" aria-labelledby="profileTitle">
    <!-- Top Header with Settings Page Link -->
    <div class="profile-top-header">
        <div>
            <div class="profile-eyebrow">YOUR PROFILE</div>
            <h1 class="page-title profile-hero-title" id="profileTitle">My <span class="accent">Profile</span></h1>
            <p class="page-sub">Your personal movie hub, activity stats, and film reviews.</p>
        </div>
        <div class="profile-top-actions">
            <a href="settings.php" class="btn btn-ghost profile-settings-btn" title="Open Account Settings">
                <span class="settings-gear-icon" aria-hidden="true">⚙</span> Settings
            </a>
            <a href="index.php" class="btn btn-ghost profile-back-btn">← Back to home</a>
        </div>
    </div>

    <!-- Avatar Update Form & Account Banner -->
    <form method="post" action="profile.php" enctype="multipart/form-data" id="avatarForm">
        <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['profile_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="update_avatar">
        <input type="hidden" name="preset_avatar" id="presetAvatarInput" value="">
        <input type="hidden" name="remove_avatar" id="removeAvatarInput" value="0">

        <!-- Account Banner Card -->
        <div class="profile-banner glass">
            <div class="profile-banner-left">
                <div class="profile-avatar-wrap" id="bannerAvatarWrap" data-initial="<?= htmlspecialchars($userInitial, ENT_QUOTES, 'UTF-8') ?>">
                    <?= avatarHtml($currentAvatar, $user['fullname'] ?? 'User', 'profile-avatar') ?>
                </div>
                <div class="profile-banner-info">
                    <div class="profile-eyebrow">YOUR ACCOUNT</div>
                    <div class="profile-avatar-name"><?= htmlspecialchars($user['fullname'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="profile-avatar-email"><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="profile-avatar-joined">
                        <span class="joined-calendar-icon" aria-hidden="true">📅</span>
                        <span>Joined <?= htmlspecialchars($joinedDateStr, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($joinedTimeStr, ENT_QUOTES, 'UTF-8') ?>)</span>
                    </div>
                </div>
            </div>
            <div class="profile-banner-actions">
                <button type="button" class="btn btn-ghost profile-change-btn" id="toggleAvatarPicker" aria-expanded="false" aria-controls="avatarPickerCard">
                    Change photo
                </button>
            </div>
        </div>

        <!-- Avatar Picker Card (collapsed by default, opens on "Change photo") -->
        <div class="form-card glass avatar-picker-card is-collapsed" id="avatarPickerCard">
            <div class="avatar-picker-head">
                <div>
                    <div class="profile-eyebrow">CHOOSE YOUR LOOK</div>
                    <h2 class="avatar-picker-title">Pick an avatar</h2>
                    <p class="avatar-picker-sub">Choose a Cine character or upload your own image.</p>
                </div>
                <button type="button" class="avatar-picker-close" id="closeAvatarPicker" aria-label="Close avatar picker">✕</button>
            </div>

            <div class="avatar-picker-grid" role="radiogroup" aria-label="Preset avatars">
                <label for="avatarInput" class="avatar-upload-tile" id="uploadAvatarTile" title="Upload your own photo">
                    <span class="avatar-upload-plus">+</span>
                    <span class="avatar-upload-label">Upload your photo</span>
                    <input id="avatarInput" type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only-file">
                </label>

                <?php foreach ($presetAvatars as $preset):
                    $isSelected = ($currentAvatar === $preset['path']);
                ?>
                    <button type="button"
                        class="avatar-preset-btn <?= $isSelected ? 'is-selected' : '' ?>"
                        data-preset="<?= htmlspecialchars($preset['path'], ENT_QUOTES, 'UTF-8') ?>"
                        title="<?= htmlspecialchars($preset['label'], ENT_QUOTES, 'UTF-8') ?>"
                        aria-label="<?= htmlspecialchars($preset['label'], ENT_QUOTES, 'UTF-8') ?>"
                        aria-pressed="<?= $isSelected ? 'true' : 'false' ?>">
                        <img src="<?= htmlspecialchars($preset['path'], ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($preset['label'], ENT_QUOTES, 'UTF-8') ?>">
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="avatar-picker-footer">
                <div class="avatar-picker-meta">
                    <span class="profile-hint" id="avatarSelectionHint">JPG, PNG, WEBP, GIF — maximum 2MB</span>
                    <?php if (!empty($currentAvatar)): ?>
                        <button type="button" class="btn-reset-avatar" id="resetAvatarBtn">Use letter initial instead</button>
                    <?php endif; ?>
                </div>
                <button class="btn btn-primary" type="submit">Save avatar</button>
            </div>
        </div>
    </form>

    <!-- Profile Overview: Stats & User Reviews -->
    <div class="profile-overview-section" id="profileOverviewSection">
        <!-- 4 Premium Redesigned Stat Cards -->
        <div class="profile-stats-grid">
            <!-- Card 1: Reviews Written -->
            <div class="profile-stat-card glass">
                <div class="stat-card-label">Reviews Written</div>
                <div class="stat-main-val"><?= $reviewCount ?></div>
                <div class="stat-card-sub">Community contributions</div>
            </div>

            <!-- Card 2: Average Rating -->
            <div class="profile-stat-card glass">
                <div class="stat-card-label">Average Rating</div>
                <div class="stat-main-val">
                    <?= $reviewCount > 0 ? number_format($avgRating, 1) : '—' ?>
                    <?php if ($reviewCount > 0): ?>
                        <span class="stat-unit">/ 5.0</span>
                    <?php endif; ?>
                </div>
                <div class="stat-card-sub">Across all your reviews</div>
            </div>

            <!-- Card 3: Messages Sent (Replacing Member Since) -->
            <div class="profile-stat-card glass">
                <div class="stat-card-label">Messages</div>
                <div class="stat-main-val"><?= $messageCount ?></div>
                <div class="stat-card-sub">Inquiries &amp; feedback sent</div>
            </div>

            <!-- Card 4: Account Status -->
            <div class="profile-stat-card glass">
                <div class="stat-card-label">Account Status</div>
                <div class="stat-main-val">
                    <span class="stat-status-pill">Active</span>
                </div>
                <div class="stat-card-sub">Full reviewer access</div>
            </div>
        </div>

        <!-- User Reviews Block -->
        <div class="profile-reviews-block">
            <div class="profile-reviews-head">
                <div>
                    <div class="profile-eyebrow">YOUR CONTRIBUTIONS</div>
                    <h2 class="profile-block-title">My Film Reviews</h2>
                </div>
                <a href="films.php" class="btn btn-ghost btn-sm">+ Review another film</a>
            </div>

            <?php if (!empty($userReviews)): ?>
                <div class="profile-reviews-list">
                    <?php foreach ($userReviews as $rev): 
                        $ratingVal = (int)($rev['rating'] ?? 5); // Cast rating to integer (1-5)
                        $stars = str_repeat('★', $ratingVal) . str_repeat('☆', 5 - $ratingVal); // Build Unicode star string
                        $revDate = !empty($rev['date']) ? htmlspecialchars($rev['date']) : ''; // Escape review date
                        $revTime = !empty($rev['time']) ? substr(htmlspecialchars($rev['time']), 0, 5) : ''; // Format HH:MM from time column
                    ?>
                        <article class="profile-review-card glass">
                            <div class="profile-review-poster-col">
                                <?php if (!empty($rev['film_poster'])): ?>
                                    <img src="<?= htmlspecialchars(posterUrl($rev['film_poster']), ENT_QUOTES, 'UTF-8') ?>" 
                                         alt="<?= htmlspecialchars($rev['film_title'] ?? 'Film poster', ENT_QUOTES, 'UTF-8') ?>" 
                                         class="profile-review-poster" 
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="profile-review-poster-placeholder">🎬</div>
                                <?php endif; ?>
                            </div>
                            <div class="profile-review-body">
                                <div class="profile-review-top">
                                    <div>
                                        <h3 class="profile-review-film-title">
                                            <a href="films.php?genre=<?= urlencode((string)($rev['genreid'] ?? '')) ?>"><?= htmlspecialchars($rev['film_title'] ?? '', ENT_QUOTES, 'UTF-8') ?></a>
                                        </h3>
                                        <?php if (!empty($rev['genre_name'])): ?>
                                            <span class="profile-review-genre-badge"><?= htmlspecialchars($rev['genre_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="profile-review-rating-block">
                                        <span class="profile-review-stars" title="<?= $ratingVal ?> out of 5 stars"><?= $stars ?></span>
                                        <span class="profile-review-score"><?= $ratingVal ?>/5</span>
                                    </div>
                                </div>
                                <p class="profile-review-text"><?= safeHtmlContent($rev['text'] ?? '') ?></p>
                                <div class="profile-review-footer">
                                    <span class="profile-review-timestamp">
                                        🗓 <?= $revDate ?> <?= !empty($revTime) ? '· ' . $revTime : '' ?>
                                    </span>
                                    <div class="profile-review-actions">
                                        <a href="edit.php?id=<?= (int)$rev['id'] ?>" class="btn-review-action btn-review-edit" title="Edit your review">
                                            ✏️ Edit
                                        </a>
                                        <a href="deletereview.php?id=<?= (int)$rev['id'] ?>&ref=profile.php" class="btn-review-action btn-review-delete" 
                                           title="Delete your review" 
                                           onclick="return confirm('Delete this review permanently?');">
                                            🗑️ Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="profile-empty-reviews glass">
                    <div class="profile-empty-icon" aria-hidden="true">🎬</div>
                    <h3 class="profile-empty-title">No reviews yet</h3>
                    <p class="profile-empty-sub">You have not published any movie reviews yet. Watch a movie, share your thoughts, and help the community discover great cinema!</p>
                    <a href="films.php" class="btn btn-primary">Browse &amp; Review Films</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
(function() {
    const pickerCard = document.getElementById('avatarPickerCard');
    const toggleAvatarBtn = document.getElementById('toggleAvatarPicker');
    const closeAvatarBtn = document.getElementById('closeAvatarPicker');
    const presetInput = document.getElementById('presetAvatarInput');
    const removeInput = document.getElementById('removeAvatarInput');
    const fileInput = document.getElementById('avatarInput');
    const uploadTile = document.getElementById('uploadAvatarTile');
    const bannerWrap = document.getElementById('bannerAvatarWrap');
    const hintEl = document.getElementById('avatarSelectionHint');
    const resetBtn = document.getElementById('resetAvatarBtn');
    const presetBtns = document.querySelectorAll('.avatar-preset-btn');

    function setBannerImage(src) {
        bannerWrap.innerHTML = `<span class="profile-avatar has-avatar"><img src="${src}" alt="Selected avatar" class="avatar-img"></span>`;
    }

    function setBannerInitial() {
        const init = bannerWrap.dataset.initial || 'U';
        bannerWrap.innerHTML = `<span class="profile-avatar" aria-hidden="true">${init}</span>`;
    }

    function clearSelectedStates() {
        presetBtns.forEach(btn => {
            btn.classList.remove('is-selected');
            btn.setAttribute('aria-pressed', 'false');
        });
        if (uploadTile) uploadTile.classList.remove('is-selected');
    }

    if (toggleAvatarBtn && pickerCard) {
        toggleAvatarBtn.addEventListener('click', function() {
            const hidden = pickerCard.classList.toggle('is-collapsed');
            toggleAvatarBtn.setAttribute('aria-expanded', String(!hidden));
            if (!hidden) {
                pickerCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }

    if (closeAvatarBtn && pickerCard && toggleAvatarBtn) {
        closeAvatarBtn.addEventListener('click', function() {
            pickerCard.classList.add('is-collapsed');
            toggleAvatarBtn.setAttribute('aria-expanded', 'false');
        });
    }

    presetBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const path = btn.dataset.preset;
            clearSelectedStates();
            btn.classList.add('is-selected');
            btn.setAttribute('aria-pressed', 'true');
            if (presetInput) presetInput.value = path;
            if (removeInput) removeInput.value = '0';
            if (fileInput) fileInput.value = '';
            setBannerImage(path);
            if (hintEl) hintEl.textContent = 'Character selected — click "Save avatar" to apply.';
        });
    });

    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = fileInput.files && fileInput.files[0];
            if (!file) return;
            clearSelectedStates();
            if (uploadTile) uploadTile.classList.add('is-selected');
            if (presetInput) presetInput.value = '';
            if (removeInput) removeInput.value = '0';
            const reader = new FileReader();
            reader.onload = function(e) {
                setBannerImage(e.target.result);
            };
            reader.readAsDataURL(file);
            if (hintEl) hintEl.textContent = `Selected "${file.name}" — click "Save avatar" to upload.`;
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            clearSelectedStates();
            if (presetInput) presetInput.value = '';
            if (fileInput) fileInput.value = '';
            if (removeInput) removeInput.value = '1';
            setBannerInitial();
            if (hintEl) hintEl.textContent = 'Reset to letter initial — click "Save avatar" to apply.';
        });
    }
})();
</script>
