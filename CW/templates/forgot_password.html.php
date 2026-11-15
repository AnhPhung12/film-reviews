<?php
/**
 * forgot_password.html.php — 3-Step 4-Digit OTP Password Reset Template.
 *
 * @var string     $oldEmail
 * @var bool       $otpPending
 * @var bool       $otpVerified
 * @var array|null $otpSession
 * @var int        $resendRemaining
 */
?>
<div class="form-card" style="max-width: 430px; margin: 2rem auto; padding: 2rem;">
    <?php if (!$otpPending && !$otpVerified): ?>
        <!-- Step 1: Enter Email -->
        <div class="form-card-title">Forgot Password</div>
        <p style="color: var(--text-dim); font-size: 0.9rem; margin-bottom: 1.25rem; line-height: 1.5;">
            Enter your Gmail / email address and we'll send a 4-digit OTP code to verify your account.
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
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; border-radius: 999px;">
                    Send 4-Digit OTP
                </button>
            </div>

            <div style="text-align: center; margin-top: 1rem; font-size: 0.9rem;">
                Remember your password? <a href="loginUser.php" style="color: var(--gold);">Back to Login</a>
            </div>
        </form>

    <?php elseif ($otpPending): ?>
        <!-- Step 2: 4-Box OTP Verification UI -->
        <div class="form-card-title" style="margin-bottom: 0.5rem;">OTP verification</div>
        <p style="color: var(--text-dim); font-size: 0.9rem; margin-bottom: 1.6rem; line-height: 1.5;">
            Please enter the OTP (One-Time Password) sent to <strong style="color: var(--text);"><?= htmlspecialchars($otpSession['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong> to complete your verification.
        </p>

        <form method="POST" action="forgot_password.php" id="forgotOtpForm">
            <input type="hidden" name="otp_code" id="forgotOtpHidden" value="">

            <div class="otp-box-row" style="display: flex; justify-content: center; gap: 0.85rem; margin-bottom: 1.25rem;">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <input type="text" inputmode="numeric" maxlength="1" pattern="\d"
                        class="form-input otp-digit-box"
                        aria-label="OTP digit <?= $i + 1 ?>"
                        autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
                        style="width: 58px; height: 58px; text-align: center; font-size: 1.5rem; font-weight: 700; border-radius: 12px; padding: 0;">
                <?php endfor; ?>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.84rem; color: var(--text-dim); margin-bottom: 1.6rem; gap: 0.5rem; flex-wrap: wrap;">
                <span>Remaining time: <strong id="forgotCountdownText" style="color: var(--gold); font-variant-numeric: tabular-nums;">00:<?= str_pad((string)(int)$resendRemaining, 2, '0', STR_PAD_LEFT) ?>s</strong></span>
                <span>
                    Didn't get the code?
                    <button type="submit" name="action" value="resend_otp" formnovalidate id="forgotResendBtn"
                        style="background: none; border: none; padding: 0; font-size: 0.84rem; font-weight: 700; cursor: <?= $resendRemaining > 0 ? 'not-allowed' : 'pointer' ?>; color: <?= $resendRemaining > 0 ? 'var(--text-dim)' : 'var(--gold)' ?>; opacity: <?= $resendRemaining > 0 ? '0.55' : '1' ?>;"
                        <?= $resendRemaining > 0 ? 'disabled' : '' ?>>Resend</button>
                </span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <button type="submit" name="action" value="verify_otp" class="btn btn-primary"
                    style="width: 100%; justify-content: center; border-radius: 999px; padding: 0.8rem 1.2rem;">
                    Verify
                </button>
                <button type="submit" name="action" value="cancel_otp" formnovalidate class="btn btn-ghost"
                    style="width: 100%; justify-content: center; border-radius: 999px; padding: 0.8rem 1.2rem;">
                    Cancel
                </button>
            </div>
        </form>

        <script>
        (function () {
            var boxes = Array.prototype.slice.call(document.querySelectorAll('#forgotOtpForm .otp-digit-box'));
            var hidden = document.getElementById('forgotOtpHidden');
            var form = document.getElementById('forgotOtpForm');
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
                    if (e.key === 'Backspace' && !box.value && idx > 0) {
                        boxes[idx - 1].focus();
                    }
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

            var btn = document.getElementById('forgotResendBtn');
            var cdText = document.getElementById('forgotCountdownText');
            var remaining = <?= (int)$resendRemaining ?>;
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
        <div class="form-card-title">Set New Password</div>
        <p style="color: var(--text-dim); font-size: 0.9rem; margin-bottom: 1.25rem; line-height: 1.5;">
            OTP verified for <strong style="color: var(--text);"><?= htmlspecialchars($otpSession['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>. Choose a new password (minimum 6 characters).
        </p>

        <form method="POST" action="forgot_password.php">
            <div class="form-grid">
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

            <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="submit" name="action" value="set_password" class="btn btn-primary"
                    style="width: 100%; justify-content: center; border-radius: 999px; padding: 0.8rem 1.2rem;">
                    Update Password
                </button>
                <button type="submit" name="action" value="cancel_otp" formnovalidate class="btn btn-ghost"
                    style="width: 100%; justify-content: center; border-radius: 999px; padding: 0.8rem 1.2rem;">
                    Cancel
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>
