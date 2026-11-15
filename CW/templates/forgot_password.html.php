<?php
/**
 * forgot_password.html.php — Forgot Password Request Template.
 * Purpose: Renders the email submission form to request a password reset link.
 * Connections:
 *   - Rendered by: forgot_password.php; wrapped inside templates/layout.html.php.
 *   - Expects: optional $oldEmail string to repopulate the email input.
 * OOP/Patterns: View Template (MVC).
 * Data Structures & Algorithms: Escapes $oldEmail with htmlspecialchars() to prevent reflected XSS.
 *
 * @var string $oldEmail
 */
?>
<!-- Password reset request card -->
<div class="form-card" style="max-width: 430px; margin: 2rem auto; padding: 2rem;">
    <div class="form-card-title">Forgot Password</div>
    <p style="color: var(--text-dim); font-size: 0.9rem; margin-bottom: 1.25rem; line-height: 1.5;">
        Enter the email address associated with your account and we'll send you a link to reset your password.
    </p>

    <!-- Submits email to forgot_password.php for token generation & SMTP delivery -->
    <form method="POST" action="forgot_password.php">
        <div class="form-grid">
            <div class="form-group full">
                <label class="form-label" for="email">Email Address</label>
                <input class="form-input" id="email" type="email" name="email"
                    value="<?= htmlspecialchars($oldEmail ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="you@example.com" required autocomplete="email">
            </div>
        </div>

        <div class="form-footer" style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                Send Reset Link
            </button>
        </div>

        <!-- Link back to login -->
        <div style="text-align: center; margin-top: 1rem; font-size: 0.9rem;">
            Remember your password? <a href="loginUser.php" style="color: var(--gold);">Back to Login</a>
        </div>
    </form>
</div>
