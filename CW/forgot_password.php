<?php
/**
 * forgot_password.php — Secure Password Reset Request Controller.
 *
 * Purpose:     Validates a user's email, generates a time-limited reset token,
 *              and emails a password reset link via SMTP.
 * Connections: Uses UserRepository (generates SHA-256 hashed 30-min token) and
 *              Mailer (SMTP delivery), rendering templates/forgot_password.html.php.
 * Security:    Redirects already-logged-in users; returns an identical generic
 *              flash message whether the email exists or not to prevent user
 *              enumeration; escapes dynamic HTML email values with htmlspecialchars().
 */

session_start(); // Start or resume session for auth check and flash messages
require_once __DIR__ . '/includes/DataBaseFunctions.php'; // Load UserRepository and database helpers
require_once __DIR__ . '/includes/Mailer.php'; // Load SMTP Mailer utility

// Redirect authenticated users to homepage
if (isset($_SESSION['user_id'])) { // Logged-in users do not need the forgot-password flow
    header('Location: index.php'); // Redirect to homepage
    exit; // Stop script execution after redirect
}

$pageTitle    = 'Forgot Password'; // Set page title for layout template
$flashMessage = $_SESSION['flash']      ?? ''; // Retrieve any pending flash message
$flashType    = $_SESSION['flash_type'] ?? 'success'; // Default flash type to 'success'
unset($_SESSION['flash'], $_SESSION['flash_type']); // Clear flash keys after reading
$oldEmail = ''; // Initialize sticky email field value

// Handle POST password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Process reset request on HTTP POST
    $oldEmail = trim($_POST['email'] ?? ''); // Read and trim submitted email address

    if (!filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) { // Validate email syntax before querying database
        $flashMessage = 'Please enter a valid email address.'; // Set validation error message
        $flashType    = 'error'; // Style alert as error
    } elseif (!Mailer::isConfigured() && trim((string)ini_get('sendmail_path')) === '') { // Ensure SMTP or sendmail is available
        $flashMessage = 'Email sending is not configured yet. Please set your SMTP credentials in CW/includes/mail_config.php.'; // Report missing mail configuration
        $flashType    = 'error'; // Style alert as error
    } else {
        $repo       = new UserRepository(); // Instantiate repository for user lookup and token management
        $user       = $repo->findByEmail($oldEmail); // Look up reviewer account by email
        $sendFailed = false; // Track whether SMTP delivery encountered an error

        if ($user) { // Generate and email token only if a matching account exists
            // Generate a 30-minute reset token stored hashed in the database
            $rawToken = $repo->createPasswordResetToken((int)$user['id'], 30); // Create raw token and persist its SHA-256 hash

            // Construct absolute reset URL matching current scheme and host
            $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; // Detect HTTPS vs HTTP
            $host     = $_SERVER['HTTP_HOST'] ?? 'localhost'; // Read current server host
            $baseDir  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/'); // Normalize base directory path
            $resetUrl = $scheme . '://' . $host . $baseDir . '/reset_password.php?token=' . urlencode($rawToken); // Build full reset link

            $safeName = htmlspecialchars($user['fullname'], ENT_QUOTES, 'UTF-8'); // Escape user name for safe HTML email rendering
            $safeUrl  = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8'); // Escape reset URL for safe HTML attribute insertion

            // HTML email template
            $htmlBody = <<<HTML
<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:24px;background:#32292f;color:#f0f7f4;border-radius:12px;">
  <h2 style="margin:0 0 12px;color:#99e1d9;">Reset your Cine password</h2>
  <p style="margin:0 0 14px;line-height:1.6;">Hi {$safeName},</p>
  <p style="margin:0 0 18px;line-height:1.6;">We received a request to reset your Cine account password. Click the button below to choose a new password (this link expires in 30 minutes):</p>
  <p style="margin:0 0 20px;">
    <a href="{$safeUrl}" style="display:inline-block;padding:12px 22px;background:#99e1d9;color:#000;text-decoration:none;font-weight:bold;border-radius:8px;">Reset Password</a>
  </p>
  <p style="margin:0 0 8px;font-size:13px;color:#cfd8d4;">Or copy and paste this URL into your browser:</p>
  <p style="margin:0 0 16px;font-size:12px;word-break:break-all;color:#99e1d9;">{$safeUrl}</p>
  <p style="margin:0;font-size:12px;color:#b5b5b5;">If you did not request a password reset, you can safely ignore this email.</p>
</div>
HTML; // End heredoc HTML email body

            // Plain-text fallback email body
            $textBody = "Hi {$user['fullname']},\n\n" // Compose plain-text alternative for non-HTML mail clients
                . "We received a request to reset your Cine password.\n"
                . "Open this link within 30 minutes to set a new password:\n"
                . "{$resetUrl}\n\n"
                . "If you did not request this, you can safely ignore this email.";

            $result = Mailer::send($user['email'], $user['fullname'], 'Reset your Cine password', $htmlBody, $textBody); // Send reset email via SMTP
            if (!$result['ok']) { // Check if email delivery failed
                // Roll back token if email delivery fails
                $repo->clearPasswordResetToken((int)$user['id']); // Remove unused token so it cannot linger
                $flashMessage = 'Could not send reset email: ' . $result['error']; // Display SMTP error details
                $flashType    = 'error'; // Style alert as error
                $sendFailed   = true; // Prevent PRG redirect so user sees the error
            }
        }

        // PRG redirect with generic message to prevent email enumeration
        if (!$sendFailed) { // Redirect even when email wasn't found so attackers cannot probe registered emails
            $_SESSION['flash']      = 'If an account with that email exists, a password reset link has been sent to your inbox.'; // Generic non-enumerating confirmation
            $_SESSION['flash_type'] = 'success'; // Style flash as success
            header('Location: forgot_password.php'); // Redirect using PRG pattern
            exit; // Stop script execution after redirect
        }
    }
}

// Render forgot password view inside shared layout
ob_start(); // Start output buffering to capture forgot-password template
require __DIR__ . '/templates/forgot_password.html.php'; // Render the forgot-password form view
$content = ob_get_clean(); // Capture rendered HTML into $content
require __DIR__ . '/templates/layout.html.php'; // Wrap $content inside the shared site layout
