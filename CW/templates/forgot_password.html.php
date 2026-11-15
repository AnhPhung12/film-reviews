<?php
/**
 * forgot_password.html.php — 4-Digit OTP Password Reset Template.
 * Purpose: Step 1: Enter email address to receive a 4-digit OTP.
 *          Step 2: Enter the 4-digit OTP + new password (min 6 chars) with a 10s resend countdown.
 *
 * @var string     $oldEmail
 * @var bool       $otpPending
 * @var array|null $otpSession
 * @var int        $resendRemaining
 */
?>
<!-- Password reset OTP card -->
<div class="form-card" style="max-width: 430px; margin: 2rem auto; padding: 2rem;">
    <div class="form-card-title">Forgot Password</div>

    <?php if (!$otpPending): ?>
        <p style="color: var(--text-dim); font-size: 0.9rem; margin-bottom: 1.25rem; line-height: 1.5;">
            Enter your Gmail / email address and we'll send a 4-digit OTP code to reset your password.
        </p>

        <form method="POST" action="forgot_password.php">
            <input type="hidden" name="action" value="send_otp">
            <div class="form-grid">
                <div class="form-group full">
                    <label class="form-label" for="email">Email Address</label>
                    <input class="form-input" id="email" type="email" name="email"
                        value="<?= htmlspecialchars($oldEmail ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="you@gmail.com" required autocomplete="email">
                </div>
            </div>

            <div class="form-footer" style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    Send 4-Digit OTP
                </button>
            </div>

            <div style="text-align: center; margin-top: 1rem; font-size: 0.9rem;">
                Remember your password? <a href="loginUser.php" style="color: var(--gold);">Back to Login</a>
            </div>
        </form>
    <?php else: ?>
        <p style="color: var(--text-dim); font-size: 0.9rem; margin-bottom: 1.25rem; line-height: 1.5;">
            Enter the 4-digit OTP sent to <strong style="color: var(--text);"><?= htmlspecialchars($otpSession['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong> and set your new password.
        </p>

        <form method="POST" action="forgot_password.php">
            <div class="form-grid">
                <div class="form-group full">
                    <label class="form-label" for="otp_code">4-Digit OTP Code</label>
                    <input class="form-input otp-input" id="otp_code" name="otp_code"
                        maxlength="4" pattern="\d{4}" inputmode="numeric" required
                        placeholder="••••" autocomplete="one-time-code"
                        style="text-align: center; font-size: 1.5rem; letter-spacing: 0.6rem; font-weight: 700;">
                </div>
                <div class="form-group full">
                    <label class="form-label" for="new_password">New Password</label>
                    <input class="form-input" id="new_password" type="password" name="new_password"
                        minlength="6" required autocomplete="new-password" placeholder="At least 6 characters">
                </div>
                <div class="form-group full">
                    <label class="form-label" for="confirm_password">Confirm New Password</label>
                    <input class="form-input" id="confirm_password" type="password" name="confirm_password"
                        minlength="6" required autocomplete="new-password" placeholder="Re-enter new password">
                </div>
            </div>

            <div class="form-footer" style="margin-top: 1.5rem;">
                <button type="submit" name="action" value="verify_otp" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    Verify OTP &amp; Reset Password
                </button>
            </div>

            <!-- 10-second OTP resend countdown & cancel option -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; gap: 0.75rem; font-size: 0.85rem;">
                <button type="submit" name="action" value="resend_otp" formnovalidate id="forgotResendBtn"
                    class="btn btn-ghost"
                    style="padding: 0.4rem 0.9rem; font-size: 0.82rem;"
                    <?= $resendRemaining > 0 ? 'disabled' : '' ?>>
                    <?= $resendRemaining > 0 ? 'Resend OTP (' . (int)$resendRemaining . 's)' : 'Resend OTP' ?>
                </button>
                <button type="submit" name="action" value="cancel_otp" formnovalidate
                    style="background: none; border: none; color: var(--gold); cursor: pointer; font-size: 0.85rem; padding: 0;">
                    Use another email
                </button>
            </div>
        </form>

        <script>
        (function () {
            var btn = document.getElementById('forgotResendBtn');
            var remaining = <?= (int)$resendRemaining ?>;
            if (!btn || remaining <= 0) return;
            var timer = setInterval(function () {
                remaining -= 1;
                if (remaining <= 0) {
                    clearInterval(timer);
                    btn.disabled = false;
                    btn.textContent = 'Resend OTP';
                } else {
                    btn.textContent = 'Resend OTP (' + remaining + 's)';
                }
            }, 1000);
        })();
        </script>
    <?php endif; ?>
</div>
