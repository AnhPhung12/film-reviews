<?php
/**
 * register.html.php — New Account Registration Template.
 * Purpose: Renders sign-up form collecting full name, email, and password (min 6 chars).
 * Connections:
 *   - Rendered by: register.php; wrapped inside templates/layout.html.php.
 *   - Links to: loginUser.php for existing users.
 * OOP/Patterns: View Template (MVC).
 * Data Structures & Algorithms: HTML5 client-side constraints paired with server-side validation in register.php.
 */
?>
<!-- Registration Form -->
<div class="form-card" style="max-width: 400px; margin: 2rem auto;">
    <div class="form-card-title">Create an Account</div>
    <!-- Form Fields -->
    <form method="POST">
        <div class="form-grid">
            <!-- Full Name Input -->
            <div class="form-group full">
                <label class="form-label">Full Name</label>
                <input class="form-input" type="text" name="fullname" required>
            </div>
            <!-- Email Input -->
            <div class="form-group full">
                <label class="form-label">Email Address</label>
                <input class="form-input" type="email" name="email" required>
            </div>
            <!-- Password Input -->
            <div class="form-group full">
                <label class="form-label">Password</label>
                <input class="form-input" type="password" name="password" required minlength="6">
            </div>
        </div>
        <!-- Form Footer -->
        <div class="form-footer" style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; text-align: center;">Register</button>
        </div>
        <!-- Login Link -->
        <div style="text-align:center; margin-top:1rem; font-size:0.9rem;">
            Already have an account? <a href="loginUser.php" style="color:var(--gold)">Login here</a>
        </div>
    </form>
</div>