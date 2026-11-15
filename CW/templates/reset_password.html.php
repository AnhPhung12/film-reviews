<?php
/**
 * reset_password.html.php — Password Reset Form Template.
 * Purpose: Displays either an invalid/expired token notice or the new-password confirmation form.
 * Connections:
 *   - Rendered by: reset_password.php; wrapped inside templates/layout.html.php.
 *   - Expects: $tokenValid (bool), $token (string), and $resetUser (array with email) from controller.
 * OOP/Patterns: View Template (MVC).
 * Data Structures & Algorithms: Conditional branch on $tokenValid; passes hidden $token to POST handler.
 *
 * @var bool       $tokenValid
 * @var string     $token
 * @var array|null $resetUser
 */
?>
<section class="section page-section">
    <div class="form-card glass" style="max-width: 430px; margin: 2rem auto;">
        <div class="form-card-title">Reset Password</div>

        <?php if (!$tokenValid): ?>
            <!-- Invalid or expired reset token state -->
            <p style="color: var(--text-dim); font-size: 0.92rem; margin-bottom: 1.5rem;">
                This password reset link is invalid or has expired. Please request a new one.
            </p>
            <a href="forgot_password.php" class="btn btn-primary" style="width: 100%; justify-content: center;">
                Request New Reset Link
            </a>
            <div style="text-align: center; margin-top: 1rem; font-size: 0.9rem;">
                <a href="loginUser.php" style="color: var(--gold);">← Back to Login</a>
            </div>
        <?php else: ?>
            <!-- Valid token: allow setting a new password -->
            <p style="color: var(--text-dim); font-size: 0.9rem; margin-bottom: 1.25rem;">
                Setting a new password for <strong style="color: var(--text);"><?= htmlspecialchars($resetUser['email'], ENT_QUOTES, 'UTF-8') ?></strong>.
            </p>

            <form method="POST" action="reset_password.php">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label" for="password">New Password</label>
                        <input class="form-input" id="password" type="password" name="password"
                            minlength="6" required autocomplete="new-password" placeholder="At least 6 characters">
                    </div>
                    <div class="form-group full">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input class="form-input" id="confirm_password" type="password" name="confirm_password"
                            minlength="6" required autocomplete="new-password" placeholder="Re-enter your new password">
                    </div>
                </div>

                <div class="form-footer" style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                        Update Password
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
