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

            <?php if (!$otpPending && empty($otpVerified)): ?>
                <!-- Step 1: Request 4-digit email OTP -->
                <p class="profile-intro">
                    We will send a four-digit verification code to <strong style="color:var(--text)"><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong> before allowing a password change.
                </p>
                <form method="post" action="settings.php">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['settings_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="profile-card-actions">
                        <button class="btn btn-primary" type="submit" name="action" value="send_otp" style="border-radius:999px;">Send security code</button>
                    </div>
                </form>
            <?php elseif ($otpPending): ?>
                <!-- Step 2: 4-Box OTP Verification UI -->
                <h3 style="margin:0 0 0.4rem;font-size:1.15rem;font-weight:700;">OTP verification</h3>
                <p class="profile-intro" style="margin-bottom:1.25rem;">
                    Please enter the OTP (One-Time Password) sent to <strong style="color:var(--text)"><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong> to complete your verification.
                </p>
                <?php if (!empty($_SESSION['pwd_otp']['local_otp'])): ?>
                    <div style="margin-bottom:1.1rem;padding:0.75rem 1rem;border-radius:10px;background:rgba(153,225,217,0.12);border:1px solid rgba(153,225,217,0.35);color:var(--gold);font-size:0.86rem;text-align:center;">
                        Local Test Mode (mail not configured) — Your OTP: <strong style="font-size:1.05rem;letter-spacing:3px;"><?= htmlspecialchars($_SESSION['pwd_otp']['local_otp'], ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                <?php endif; ?>
                <form method="post" action="settings.php" id="settingsOtpForm">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['settings_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="otp_code" id="settingsOtpHidden" value="">

                    <div style="display:flex;justify-content:center;gap:0.75rem;margin-bottom:1.1rem;">
                        <?php for ($i = 0; $i < 4; $i++): ?>
                            <input type="text" inputmode="numeric" maxlength="1" pattern="\d"
                                class="form-input otp-digit-box"
                                aria-label="OTP digit <?= $i + 1 ?>"
                                autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
                                style="width:52px;height:52px;text-align:center;font-size:1.4rem;font-weight:700;border-radius:12px;padding:0;">
                        <?php endfor; ?>
                    </div>

                    <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.82rem;color:var(--text-dim);margin-bottom:1.25rem;gap:0.5rem;flex-wrap:wrap;">
                        <span>Remaining time: <strong id="settingsCountdownText" style="color:var(--gold);font-variant-numeric:tabular-nums;">00:<?= str_pad((string)(int)($settingsResendRemaining ?? 0), 2, '0', STR_PAD_LEFT) ?>s</strong></span>
                        <span>
                            Didn't get the code?
                            <button type="submit" name="action" value="send_otp" formnovalidate id="settingsResendBtn"
                                style="background:none;border:none;padding:0;font-size:0.82rem;font-weight:700;cursor:<?= !empty($settingsResendRemaining) ? 'not-allowed' : 'pointer' ?>;color:<?= !empty($settingsResendRemaining) ? 'var(--text-dim)' : 'var(--gold)' ?>;opacity:<?= !empty($settingsResendRemaining) ? '0.55' : '1' ?>;"
                                <?= !empty($settingsResendRemaining) ? 'disabled' : '' ?>>Resend</button>
                        </span>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:0.65rem;">
                        <button class="btn btn-primary" type="submit" name="action" value="verify_otp" style="width:100%;justify-content:center;border-radius:999px;padding:0.75rem 1rem;">Verify</button>
                        <button class="btn btn-ghost" type="submit" name="action" value="cancel_otp" formnovalidate style="width:100%;justify-content:center;border-radius:999px;padding:0.75rem 1rem;">Cancel</button>
                    </div>
                </form>
                <script>
                (function () {
                    var boxes = Array.prototype.slice.call(document.querySelectorAll('#settingsOtpForm .otp-digit-box'));
                    var hidden = document.getElementById('settingsOtpHidden');
                    var form = document.getElementById('settingsOtpForm');
                    function sync() {
                        hidden.value = boxes.map(function (b) { return b.value.replace(/\D/g, ''); }).join('');
                    }
                    boxes.forEach(function (box, idx) {
                        box.addEventListener('input', function () {
                            box.value = box.value.replace(/\D/g, '').slice(-1);
                            sync();
                            if (box.value && idx < boxes.length - 1) boxes[idx + 1].focus();
                        });
                        box.addEventListener('keydown', function (e) {
                            if (e.key === 'Backspace' && !box.value && idx > 0) boxes[idx - 1].focus();
                        });
                        box.addEventListener('paste', function (e) {
                            var txt = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 4);
                            if (!txt) return;
                            e.preventDefault();
                            txt.split('').forEach(function (ch, j) { if (boxes[j]) boxes[j].value = ch; });
                            sync();
                            if (boxes[Math.min(txt.length, 3)]) boxes[Math.min(txt.length, 3)].focus();
                        });
                    });
                    if (boxes[0]) boxes[0].focus();
                    form.addEventListener('submit', sync);

                    var btn = document.getElementById('settingsResendBtn');
                    var cdText = document.getElementById('settingsCountdownText');
                    var remaining = <?= (int)($settingsResendRemaining ?? 0) ?>;
                    if (remaining > 0 && btn && cdText) {
                        var timer = setInterval(function () {
                            remaining -= 1;
                            if (remaining <= 0) {
                                clearInterval(timer);
                                cdText.textContent = '00:00s';
                                btn.disabled = false;
                                btn.style.cursor = 'pointer';
                                btn.style.color = 'var(--gold)';
                                btn.style.opacity = '1';
                            } else {
                                cdText.textContent = '00:' + (remaining < 10 ? '0' : '') + remaining + 's';
                            }
                        }, 1000);
                    }
                })();
                </script>
            <?php else: ?>
                <!-- Step 3: Set New Password (strictly >= 6 chars) -->
                <p class="profile-intro">
                    OTP verified! Enter your new password (minimum 6 characters).
                </p>
                <form method="post" action="settings.php">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['settings_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-grid">
                        <div class="form-group full">
                            <label class="form-label" for="new_password">New password</label>
                            <input class="form-input" id="new_password" type="password" name="new_password" minlength="6" required autocomplete="new-password" placeholder="At least 6 characters">
                        </div>
                        <div class="form-group full">
                            <label class="form-label" for="confirm_password">Confirm new password</label>
                            <input class="form-input" id="confirm_password" type="password" name="confirm_password" minlength="6" required autocomplete="new-password" placeholder="Re-enter new password">
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:0.65rem;margin-top:1.25rem;">
                        <button class="btn btn-primary" type="submit" name="action" value="verify_change_password" style="width:100%;justify-content:center;border-radius:999px;padding:0.75rem 1rem;">Update password</button>
                        <button class="btn btn-ghost" type="submit" name="action" value="cancel_otp" formnovalidate style="width:100%;justify-content:center;border-radius:999px;padding:0.75rem 1rem;">Cancel</button>
                    </div>
                </form>
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
