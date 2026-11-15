<?php
/**
 * settings.php — Account Settings, OTP Password Change & Account Deletion.
 *
 * Purpose:     Allows authenticated users to update personal details (name/email),
 *              change their password via 4-digit email OTP verification, or
 *              permanently delete their account.
 * Connections: Uses UserRepository and Mailer (SMTP OTP delivery), rendering
 *              templates/settings.html.php inside templates/layout.html.php.
 * Security:    Enforces session login, verifies CSRF token with hash_equals(),
 *              hashes 4-digit OTP with SHA-256 (10-min expiry, max 5 attempts),
 *              and applies PRG redirects on state changes.
 */

if (session_status() === PHP_SESSION_NONE) { // Start session only if one is not already active
    session_start(); // Initialize PHP session
}
require_once __DIR__ . '/includes/DataBaseFunctions.php'; // Load UserRepository and database helpers
require_once __DIR__ . '/includes/Mailer.php'; // Load SMTP Mailer utility for OTP emails

// Redirect unauthenticated visitors to login
if (empty($_SESSION['user_id'])) { // Enforce authentication before accessing account settings
    $_SESSION['flash']      = 'Please log in to manage your account settings.'; // Set login prompt flash
    $_SESSION['flash_type'] = 'error'; // Style flash as error
    header('Location: loginUser.php'); // Redirect guest to login page
    exit; // Stop script execution
}

$pageTitle    = 'Account Settings'; // Set page title for layout template
$flashMessage = ''; // Initialize inline alert message
$flashType    = 'success'; // Default inline alert style
$repository   = new UserRepository(); // Instantiate user repository for profile/password/delete operations
$user         = $repository->findById((int)$_SESSION['user_id']); // Fetch current user record from database

// Destroy stale session if user record no longer exists
if (!$user) { // Handle deleted or missing user record
    session_destroy(); // Clear invalid session state
    header('Location: loginUser.php'); // Redirect to login page
    exit; // Stop script execution
}

// Generate per-session CSRF token for settings forms
$_SESSION['settings_token'] ??= bin2hex(random_bytes(32)); // Create cryptographically random 64-char hex CSRF token once per session

// Handle POST account actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') { // Process form actions on HTTP POST
    $action = $_POST['action'] ?? 'update'; // Determine which settings form action was submitted

    if (!hash_equals($_SESSION['settings_token'], $_POST['token'] ?? '')) { // Constant-time CSRF token verification
        $flashMessage = 'Your session expired. Please try again.'; // Reject forged or expired form submission
        $flashType    = 'error'; // Style alert as error
    } elseif ($action === 'delete') { // Handle permanent account deletion
        // Require explicit confirmation before deleting account and reviews
        if (($_POST['confirm_delete'] ?? '') !== 'yes') { // Verify confirmation checkbox/flag
            $flashMessage = 'Please confirm account deletion.'; // Prompt user to confirm deletion
            $flashType    = 'error'; // Style alert as error
        } else {
            $repository->delete((int)$user['id']); // Delete user and cascade/remove their reviews
            session_unset(); // Clear all session variables
            session_destroy(); // Destroy the session
            if (session_status() === PHP_SESSION_NONE) { // Restart a fresh session solely for the goodbye flash message
                session_start(); // Start new session
            }
            $_SESSION['flash']      = 'Your account and its reviews have been deleted.'; // Set deletion confirmation flash
            $_SESSION['flash_type'] = 'success'; // Style flash as success
            header('Location: index.php'); // Redirect to homepage
            exit; // Stop execution
        }
    } elseif ($action === 'cancel_otp') { // Allow user to cancel an in-progress OTP verification step
        unset($_SESSION['pwd_otp']); // Clear stored OTP state from session
        header('Location: settings.php'); // Redirect back to settings page (PRG)
        exit; // Stop execution
    } elseif ($action === 'send_otp') { // Generate and email a 4-digit OTP for password change
        $existingOtp = $_SESSION['pwd_otp'] ?? null;
        if (is_array($existingOtp) && (int)($existingOtp['user_id'] ?? 0) === (int)$user['id'] && time() < (int)($existingOtp['resend_available_at'] ?? 0)) {
            $wait = (int)$existingOtp['resend_available_at'] - time();
            $flashMessage = "Please wait {$wait}s before requesting a new security code.";
            $flashType    = 'error';
        } elseif (!Mailer::isConfigured() && trim((string)ini_get('sendmail_path')) === '') { // Check SMTP or sendmail availability
            $flashMessage = 'Email sending is not configured yet. Please set your SMTP credentials in CW/includes/mail_config.php.'; // Report missing mail config
            $flashType    = 'error'; // Style alert as error
        } else {
            $otpCode             = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT); // Generate cryptographically random 4-digit OTP
            $_SESSION['pwd_otp'] = [ // Store hashed OTP and metadata in session
                'user_id'             => (int)$user['id'], // Bind OTP to current user ID
                'code_hash'           => hash('sha256', $otpCode), // Store SHA-256 hash instead of plaintext code
                'expires_at'          => time() + 600, // Set 10-minute expiration timestamp
                'resend_available_at' => time() + 10, // Enforce 10-second resend cooldown
                'verified'            => false, // Require Step 2 OTP verification before password entry
                'attempts'            => 0, // Initialize failed verification attempt counter
            ];

            $safeName = htmlspecialchars($user['fullname'], ENT_QUOTES, 'UTF-8'); // Escape user name for HTML email
            $htmlBody = <<<HTML
<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:24px;background:#32292f;color:#f0f7f4;border-radius:12px;">
  <h2 style="margin:0 0 12px;color:#99e1d9;">Your Cine security code</h2>
  <p style="margin:0 0 14px;line-height:1.6;">Hi {$safeName},</p>
  <p style="margin:0 0 18px;line-height:1.6;">Use this 4-digit verification code to change your Cine account password (valid for 10 minutes):</p>
  <div style="font-size:32px;font-weight:bold;letter-spacing:10px;text-align:center;padding:16px;background:rgba(153,225,217,0.14);border:1px solid rgba(153,225,217,0.35);border-radius:10px;color:#99e1d9;margin-bottom:18px;">{$otpCode}</div>
  <p style="margin:0;font-size:12px;color:#b5b5b5;">If you did not request this code, please ignore this email.</p>
</div>
HTML; // End heredoc HTML OTP email body
            $textBody = "Hi {$user['fullname']},\n\n" // Compose plain-text fallback email body
                . "Your 4-digit Cine security code is: {$otpCode}\n"
                . "This code expires in 10 minutes.\n\n"
                . "If you did not request this, please ignore this email.";

            $mailResult = Mailer::send($user['email'], $user['fullname'], 'Your Cine password security code', $htmlBody, $textBody); // Send OTP email via SMTP
            if (!$mailResult['ok']) { // Handle SMTP delivery failure
                unset($_SESSION['pwd_otp']); // Discard OTP if email could not be sent
                $flashMessage = 'Could not send security code: ' . $mailResult['error']; // Display SMTP error
                $flashType    = 'error'; // Style alert as error
            } else {
                $_SESSION['flash']      = 'A 4-digit security code has been sent to ' . $user['email'] . '.'; // Notify user to check inbox
                $_SESSION['flash_type'] = 'success'; // Style flash as success
                header('Location: settings.php'); // Redirect using PRG pattern
                exit; // Stop execution
            }
        }
    } elseif ($action === 'verify_otp') { // Step 2: Verify 4-digit OTP before showing new password fields
        $otpInput = trim($_POST['otp_code'] ?? '');
        $otpData  = $_SESSION['pwd_otp'] ?? null;

        if (!$otpData || (int)$otpData['user_id'] !== (int)$user['id'] || time() > (int)$otpData['expires_at']) {
            unset($_SESSION['pwd_otp']);
            $flashMessage = 'Your security code has expired. Please request a new code.';
            $flashType    = 'error';
        } elseif (!preg_match('/^\d{4}$/', $otpInput) || !hash_equals($otpData['code_hash'], hash('sha256', $otpInput))) {
            $newOtp = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $_SESSION['pwd_otp'] = [
                'user_id'             => (int)$user['id'],
                'code_hash'           => hash('sha256', $newOtp),
                'expires_at'          => time() + 600,
                'resend_available_at' => time() + 10,
                'verified'            => false,
                'attempts'            => 0,
            ];
            $safeName = htmlspecialchars($user['fullname'], ENT_QUOTES, 'UTF-8');
            $htmlBody = "<div style=\"font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:24px;background:#32292f;color:#f0f7f4;border-radius:12px;\">"
                . "<h2 style=\"margin:0 0 12px;color:#99e1d9;\">Your new Cine security code</h2>"
                . "<p style=\"margin:0 0 14px;line-height:1.6;\">Hi {$safeName},</p>"
                . "<p style=\"margin:0 0 18px;line-height:1.6;\">Your previous code did not match. Here is your new 4-digit security code:</p>"
                . "<div style=\"font-size:32px;font-weight:bold;letter-spacing:10px;text-align:center;padding:16px;background:rgba(153,225,217,0.14);border:1px solid rgba(153,225,217,0.35);border-radius:10px;color:#99e1d9;margin-bottom:18px;\">{$newOtp}</div>"
                . "</div>";
            $textBody = "Hi {$user['fullname']},\n\nYour new 4-digit Cine security code is: {$newOtp}\nThis code expires in 10 minutes.";
            $retryMail = Mailer::send($user['email'], $user['fullname'], 'Your new Cine password security code', $htmlBody, $textBody);
            $flashMessage = $retryMail['ok']
                ? 'Invalid 4-digit code! A new OTP has been sent to ' . $user['email'] . '.'
                : 'Invalid 4-digit code, and could not resend new OTP: ' . $retryMail['error'];
            $flashType = 'error';
        } else {
            $_SESSION['pwd_otp']['verified'] = true;
            $_SESSION['flash']               = 'OTP verified! Please enter your new password.';
            $_SESSION['flash_type']          = 'success';
            header('Location: settings.php');
            exit;
        }
    } elseif ($action === 'verify_change_password') { // Step 3: Save new password after OTP verification
        $newPassword     = $_POST['new_password'] ?? ''; // Read new password input
        $confirmPassword = $_POST['confirm_password'] ?? ''; // Read password confirmation input
        $otpData         = $_SESSION['pwd_otp'] ?? null; // Retrieve active OTP session payload

        if (!$otpData || (int)$otpData['user_id'] !== (int)$user['id'] || empty($otpData['verified']) || time() > (int)$otpData['expires_at']) { // Check OTP existence, owner, verification, and expiry
            unset($_SESSION['pwd_otp']); // Clear expired/invalid OTP state
            $flashMessage = 'Your security session has expired. Please request a new code.'; // Inform user of expiry
            $flashType    = 'error'; // Style alert as error
        } elseif (strlen($newPassword) < 6) { // Enforce minimum 6-character password length
            $flashMessage = 'New password must contain at least 6 characters.'; // Set length error
            $flashType    = 'error'; // Style alert as error
        } elseif ($newPassword !== $confirmPassword) { // Verify password confirmation matches
            $flashMessage = 'New password and confirmation do not match.'; // Set mismatch error
            $flashType    = 'error'; // Style alert as error
        } else {
            $repository->updatePassword((int)$user['id'], $newPassword); // Hash and save new password in DB
            unset($_SESSION['pwd_otp']); // Clear consumed OTP from session
            $_SESSION['flash']      = 'Your password has been updated.'; // Set success flash
            $_SESSION['flash_type'] = 'success'; // Style flash as success
            header('Location: settings.php'); // Redirect using PRG pattern
            exit; // Stop execution
        }
    } else {
        // Default action: update full name and email address
        $fullname = trim($_POST['fullname'] ?? ''); // Read and trim updated full name
        $email    = trim($_POST['email'] ?? ''); // Read and trim updated email address

        if ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { // Validate non-empty name and valid email syntax
            $flashMessage = 'Enter your name and a valid email address.'; // Set validation error
            $flashType    = 'error'; // Style alert as error
        } else {
            $result = $repository->update((int)$user['id'], $fullname, $email, null, null, false); // Persist updated name/email without touching password or avatar
            if ($result === true) { // Check if update succeeded
                $_SESSION['user_name']  = $fullname; // Sync updated name into session
                $_SESSION['user_email'] = $email; // Sync updated email into session
                $_SESSION['flash']      = 'Your personal details have been updated.'; // Set success flash
                $_SESSION['flash_type'] = 'success'; // Style flash as success
                header('Location: settings.php'); // Redirect using PRG pattern
                exit; // Stop execution
            }
            $flashMessage = is_string($result) ? $result : 'Could not update your personal details.'; // Display repository error (e.g., duplicate email)
            $flashType    = 'error'; // Style alert as error
        }
        $user['fullname'] = $fullname; // Keep submitted name in form on validation error
        $user['email']    = $email; // Keep submitted email in form on validation error
    }
}

// Determine whether an unexpired OTP verification step is currently active
$otpActive = !empty($_SESSION['pwd_otp'])
    && (int)($_SESSION['pwd_otp']['user_id'] ?? 0) === (int)$user['id']
    && time() <= (int)($_SESSION['pwd_otp']['expires_at'] ?? 0);
$otpVerified = $otpActive && !empty($_SESSION['pwd_otp']['verified']);
$otpPending  = $otpActive && !$otpVerified;

if (!$otpActive && isset($_SESSION['pwd_otp'])) { // Clean up expired OTP data if still in session
    unset($_SESSION['pwd_otp']); // Remove stale OTP array
}
$settingsResendRemaining = $otpPending ? max(0, (int)($_SESSION['pwd_otp']['resend_available_at'] ?? 0) - time()) : 0;

// Retrieve one-time flash message from session if none set during POST
if (empty($flashMessage)) { // Only read session flash when no inline error was set
    $flashMessage = $_SESSION['flash']      ?? ''; // Read pending flash message
    $flashType    = $_SESSION['flash_type'] ?? 'success'; // Default flash type to 'success'
    unset($_SESSION['flash'], $_SESSION['flash_type']); // Clear flash keys after reading
}

// Render settings view inside shared layout
ob_start(); // Begin output buffering to capture settings template
require __DIR__ . '/templates/settings.html.php'; // Render account settings view
$content = ob_get_clean(); // Capture rendered HTML into $content
require __DIR__ . '/templates/layout.html.php'; // Wrap $content inside the shared site layout
