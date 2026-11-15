<?php
/**
 * loginUser.html.php — Reviewer Login Form Template.
 * Purpose: Renders email/password authentication form with links to password reset and registration.
 * Connections:
 *   - Rendered by: loginUser.php; wrapped inside templates/layout.html.php.
 *   - Links to: forgot_password.php and register.php.
 * OOP/Patterns: View Template (MVC).
 * Data Structures & Algorithms: Standard POST form submission handled by loginUser.php.
 */
?>
<!-- Login Form -->
<div class="form-card" style="max-width: 400px; margin: 2rem auto;">
    <!-- Form Title -->
    <div class="form-card-title">Welcome Back</div>
    <!-- Form Fields -->
    <form method="POST"> <!-- Form submission will be handled by the same page -->
        <div class="form-grid">
            <div class="form-group full">
                <!-- Email Input -->
                <label class="form-label">Email Address</label>
                <input class="form-input" type="email" name="email" required>
            </div>
            <div class="form-group full">
                <!-- Password Input -->
                <div style="display:flex; justify-content:space-between; align-items:center; gap:0.5rem;">
                    <label class="form-label" style="margin:0;">Password</label>
                    <a href="forgot_password.php" style="font-size:0.78rem; color:var(--gold);">Forgot password?</a>
                </div>
                <input class="form-input" type="password" name="password" required>
            </div>
        </div>
        <!-- Form Footer -->
        <div class="form-footer" style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Login</button>
        </div>
        <!-- Registration Link -->
        <div style="text-align:center; margin-top:1rem; font-size:0.9rem;">
            Don't have an account? <a href="register.php" style="color:var(--gold)">Register here</a>
        </div>
    </form>
</div>
