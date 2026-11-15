<?php
/**
 * settings.html.php — Account Settings & Security Template.
 * Purpose: Renders 2-column settings grid (personal details update + 4-digit OTP password change)
 *          and a permanent account deletion card.
 * Connections:
 *   - Rendered by: settings.php; wrapped inside templates/layout.html.php.
 *   - Expects: $user, $otpPending (bool), and $_SESSION['settings_token'] (CSRF token).
 * OOP/Patterns: View Template (MVC) with CSRF-protected multi-action POST forms.
 * Data Structures & Algorithms:
 *   - Toggles between "Send security code" and OTP verification form via $otpPending state flag.
 *   - Requires explicit checkbox confirmation + JS confirm() dialog before account deletion.
 *
 * @var array $user
 * @var bool  $otpPending
 */
?>
<section class="section page-section profile-section" aria-labelledby="settingsHeading">
    <!-- Header with Back to Profile -->
    <div class="profile-top-header">
        <div>
            <div class="profile-eyebrow">ACCOUNT SETTINGS</div>
            <h1 class="page-title profile-hero-title" id="settingsHeading">Account <span class="accent">Settings</span></h1>
            <p class="page-sub">Update your personal details, secure your password, and manage your account.</p>
        </div>
        <div class="profile-top-actions">
            <a href="profile.php" class="btn btn-ghost profile-back-btn">← Back to Profile</a>
        </div>
    </div>

    <!-- 2-Column Main Settings Grid (Personal Details + Security) -->
    <div class="profile-two-col">
        <!-- Left Card: Personal details -->
        <div class="form-card profile-card glass">
            <div class="profile-card-badge" aria-hidden="true">🎬</div>
            <div class="profile-eyebrow">PROFILE</div>
            <h2 class="avatar-picker-title">Personal details</h2>
            <p class="profile-intro">This is how the Cine community sees you.</p>

            <form method="post" action="settings.php">
                <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['settings_token'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label" for="fullname">Full name</label>
                        <input class="form-input" id="fullname" name="fullname" maxlength="150" required value="<?= htmlspecialchars($user['fullname'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group full">
                        <label class="form-label" for="email">Email address</label>
                        <input class="form-input" id="email" type="email" name="email" maxlength="150" required value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="profile-card-actions">
                    <button class="btn btn-primary" type="submit" name="action" value="update">Save profile</button>
                </div>
            </form>
        </div>

        <!-- Right Card: Security / Change password via 4-digit OTP -->
        <div class="form-card profile-card glass">
            <div class="profile-card-badge" aria-hidden="true">🔐</div>
            <div class="profile-eyebrow">SECURITY</div>
            <h2 class="avatar-picker-title">Change password</h2>

            <?php if (!$otpPending): ?>
                <!-- Step 1: Request 4-digit email OTP -->
                <p class="profile-intro">
                    We will send a four-digit verification code to <strong style="color:var(--text)"><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong> before allowing a password change.
                </p>
                <form method="post" action="settings.php">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['settings_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="profile-card-actions">
                        <button class="btn btn-ghost" type="submit" name="action" value="send_otp">Send security code</button>
                    </div>
                </form>
            <?php else: ?>
                <!-- Step 2: Verify OTP and submit new password -->
                <p class="profile-intro">
                    Enter the 4-digit verification code sent to <strong style="color:var(--text)"><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong> and choose your new password.
                </p>
                <form method="post" action="settings.php">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['settings_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-grid">
                        <div class="form-group full">
                            <label class="form-label" for="otp_code">4-digit security code</label>
                            <input class="form-input otp-input" id="otp_code" name="otp_code" maxlength="4" pattern="\d{4}" inputmode="numeric" required placeholder="••••" autocomplete="one-time-code">
                        </div>
                        <div class="form-group full">
                            <label class="form-label" for="new_password">New password</label>
                            <input class="form-input" id="new_password" type="password" name="new_password" minlength="6" required autocomplete="new-password" placeholder="At least 6 characters">
                        </div>
                        <div class="form-group full">
                            <label class="form-label" for="confirm_password">Confirm new password</label>
                            <input class="form-input" id="confirm_password" type="password" name="confirm_password" minlength="6" required autocomplete="new-password" placeholder="Re-enter new password">
                        </div>
                    </div>
                    <div class="profile-card-actions profile-otp-actions">
                        <button class="btn btn-primary" type="submit" name="action" value="verify_change_password">Verify &amp; update</button>
                        <button class="btn btn-ghost" type="submit" name="action" value="send_otp" formnovalidate id="settingsResendBtn" <?= !empty($settingsResendRemaining) ? 'disabled' : '' ?>>
                            <?= !empty($settingsResendRemaining) ? 'Resend code (' . (int)$settingsResendRemaining . 's)' : 'Resend code' ?>
                        </button>
                        <button class="btn-reset-avatar" type="submit" name="action" value="cancel_otp" formnovalidate>Cancel</button>
                    </div>
                </form>
                <script>
                (function () {
                    var btn = document.getElementById('settingsResendBtn');
                    var remaining = <?= (int)($settingsResendRemaining ?? 0) ?>;
                    if (!btn || remaining <= 0) return;
                    var timer = setInterval(function () {
                        remaining -= 1;
                        if (remaining <= 0) {
                            clearInterval(timer);
                            btn.disabled = false;
                            btn.textContent = 'Resend code';
                        } else {
                            btn.textContent = 'Resend code (' + remaining + 's)';
                        }
                    }, 1000);
                })();
                </script>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Account Card -->
    <div class="form-card profile-delete-card glass">
        <h2>Delete account</h2>
        <p>Deleting your account also removes your reviews and contact messages. This cannot be undone.</p>
        <form method="post" action="settings.php" onsubmit="return confirm('Delete your account and its reviews? This cannot be undone.');">
            <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['settings_token'], ENT_QUOTES, 'UTF-8') ?>">
            <label class="profile-confirm"><input type="checkbox" name="confirm_delete" value="yes" required> I understand and want to delete my account.</label>
            <button class="btn profile-delete-button" type="submit" name="action" value="delete">Delete my account</button>
        </form>
    </div>
</section>
