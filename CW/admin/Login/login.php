<?php
/**
 * admin/Login/login.php — Admin Login Page View.
 *
 * Purpose:     Renders the restricted administrator sign-in form.
 * Connections: Submits credentials to admin/Login/check.php; redirects
 *              already-authenticated admins to admin/index.php.
 * Security:    Checks $_SESSION['admin_logged_in'] to skip login when already
 *              authenticated and displays generic error feedback via ?error=.
 */

session_start(); // Resume session to check whether admin is already signed in

// Redirect already-authenticated admins directly to the dashboard
if (!empty($_SESSION['admin_logged_in'])) { // Skip login screen if admin session is active
    header('Location: ../index.php'); // Redirect to admin dashboard
    exit; // Stop script execution after redirect
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Cine</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:opsz,wght@9..40,400;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="../icons/logo.png">
    <link rel="stylesheet" href="login.css?v=2">
</head>

<body>
    <!-- Ambient Background Effects -->
    <div class="bg-ambient" aria-hidden="true">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>
    <div class="noise" aria-hidden="true"></div>

    <!-- Login Card Container -->
    <div class="login-wrap">
        <div class="login-card">
            <div class="login-brand">
                <img src="../icons/logo.png" alt="Cine Logo" class="brand-logo-img">
                <div class="login-title">CINE ADMIN</div>
                <div class="login-sub">Restricted area — authorised personnel only</div>
            </div>

            <!-- Display authentication failure notice if ?error is present -->
            <?php if (isset($_GET['error'])): ?>
                <div class="error">⚠️ Invalid username or password. Please try again.</div>
            <?php endif; ?>

            <!-- Admin Credentials Form -->
            <form method="POST" action="check.php">
                <div class="form-fields">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required
                            autocomplete="username" placeholder="Enter username">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required
                            autocomplete="current-password" placeholder="Enter password">
                    </div>
                </div>

                <button type="submit" class="btn-login">Sign In →</button>
            </form>

            <!-- Link back to public website -->
            <div class="back-link"><a href="../../index.php">← Back to website</a></div>
        </div>
    </div>
</body>

</html>
