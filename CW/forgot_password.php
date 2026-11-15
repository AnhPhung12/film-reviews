<?php
/**
 * forgot_password.php — 4-Digit Email OTP Password Reset Controller.
 *
 * Purpose:     Step 1: Accepts a user's email and emails a 4-digit OTP.
 *              Step 2: Verifies the 4-digit OTP + new password (min 6 chars).
 *              If OTP mismatches, automatically issues & emails a fresh OTP with
 *              a 10-second resend cooldown.
 * Connections: Uses UserRepository and Mailer, rendering templates/forgot_password.html.php.
 */

session_start(); // Start or resume session for auth check, OTP state, and flash messages
require_once __DIR__ . '/includes/DataBaseFunctions.php'; // Load UserRepository and database helpers
require_once __DIR__ . '/includes/Mailer.php'; // Load Mailer utility

// Redirect authenticated users to homepage
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$pageTitle    = 'Forgot Password';
$flashMessage = $_SESSION['flash']      ?? '';
$flashType    = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash'], $_SESSION['flash_type']);

/** Helper to generate and email a fresh 4-digit OTP for password reset. */
function issueForgotOtp(array $user): array
{
    $otpCode = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $_SESSION['forgot_otp'] = [
        'user_id'             => (int)$user['id'],
        'email'               => $user['email'],
        'fullname'            => $user['fullname'],
        'code_hash'           => hash('sha256', $otpCode),
        'expires_at'          => time() + 600,
        'resend_available_at' => time() + 10,
    ];

    $safeName = htmlspecialchars($user['fullname'], ENT_QUOTES, 'UTF-8');
    $htmlBody = <<<HTML
<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:24px;background:#32292f;color:#f0f7f4;border-radius:12px;">
  <h2 style="margin:0 0 12px;color:#99e1d9;">Your Cine password reset OTP</h2>
  <p style="margin:0 0 14px;line-height:1.6;">Hi {$safeName},</p>
  <p style="margin:0 0 18px;line-height:1.6;">Use this 4-digit OTP code to reset your Cine password (valid for 10 minutes):</p>
  <div style="font-size:32px;font-weight:bold;letter-spacing:10px;text-align:center;padding:16px;background:rgba(153,225,217,0.14);border:1px solid rgba(153,225,217,0.35);border-radius:10px;color:#99e1d9;margin-bottom:18px;">{$otpCode}</div>
  <p style="margin:0;font-size:12px;color:#b5b5b5;">If you did not request this code, please ignore this email.</p>
</div>
HTML;
    $textBody = "Hi {$user['fullname']},\n\nYour 4-digit Cine password reset OTP is: {$otpCode}\nThis code expires in 10 minutes.";

    return Mailer::send($user['email'], $user['fullname'], 'Your Cine password reset OTP', $htmlBody, $textBody);
}

$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'send_otp';

    if ($action === 'cancel_otp') {
        unset($_SESSION['forgot_otp']);
        header('Location: forgot_password.php');
        exit;
    }

    if ($action === 'send_otp' || $action === 'resend_otp') {
        $otpSession = $_SESSION['forgot_otp'] ?? null;
        $email = $action === 'resend_otp' && $otpSession
            ? (string)$otpSession['email']
            : trim($_POST['email'] ?? '');
        $oldEmail = $email;

        if ($action === 'resend_otp' && $otpSession && time() < (int)($otpSession['resend_available_at'] ?? 0)) {
            $wait = (int)$otpSession['resend_available_at'] - time();
            $flashMessage = "Please wait {$wait}s before requesting a new OTP.";
            $flashType    = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flashMessage = 'Please enter a valid email address.';
            $flashType    = 'error';
        } elseif (!Mailer::isConfigured() && trim((string)ini_get('sendmail_path')) === '') {
            $flashMessage = 'Email sending is not configured yet.';
            $flashType    = 'error';
        } else {
            $repo = new UserRepository();
            $user = $repo->findByEmail($email);

            if ($user) {
                $mailResult = issueForgotOtp($user);
                if (!$mailResult['ok']) {
                    unset($_SESSION['forgot_otp']);
                    $flashMessage = 'Could not send OTP email: ' . $mailResult['error'];
                    $flashType    = 'error';
                } else {
                    $_SESSION['flash']      = 'A 4-digit OTP has been sent to ' . $user['email'] . '.';
                    $_SESSION['flash_type'] = 'success';
                    header('Location: forgot_password.php');
                    exit;
                }
            } else {
                $flashMessage = 'No account found with that email address.';
                $flashType    = 'error';
            }
        }
    } elseif ($action === 'verify_otp') {
        $otpData         = $_SESSION['forgot_otp'] ?? null;
        $otpInput        = trim($_POST['otp_code'] ?? '');
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!$otpData || time() > (int)$otpData['expires_at']) {
            unset($_SESSION['forgot_otp']);
            $flashMessage = 'Your OTP has expired. Please enter your email again.';
            $flashType    = 'error';
        } elseif (!preg_match('/^\d{4}$/', $otpInput) || !hash_equals($otpData['code_hash'], hash('sha256', $otpInput))) {
            // Wrong OTP: immediately issue a fresh 4-digit OTP and reset the 10s timer
            $mailResult = issueForgotOtp([
                'id'       => $otpData['user_id'],
                'email'    => $otpData['email'],
                'fullname' => $otpData['fullname'],
            ]);
            $flashMessage = $mailResult['ok']
                ? 'Incorrect OTP! A new 4-digit OTP has been sent to ' . $otpData['email'] . '.'
                : 'Incorrect OTP, and could not resend new OTP: ' . $mailResult['error'];
            $flashType = 'error';
        } elseif (strlen($newPassword) < 6) {
            $flashMessage = 'Password must be at least 6 characters.';
            $flashType    = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $flashMessage = 'Password confirmation does not match.';
            $flashType    = 'error';
        } else {
            $repo = new UserRepository();
            $repo->updatePassword((int)$otpData['user_id'], $newPassword);
            unset($_SESSION['forgot_otp']);
            $_SESSION['flash']      = 'Password reset successful! You can now log in with your new password.';
            $_SESSION['flash_type'] = 'success';
            header('Location: loginUser.php');
            exit;
        }
    }
}

$otpSession      = $_SESSION['forgot_otp'] ?? null;
$otpPending      = is_array($otpSession) && time() <= (int)($otpSession['expires_at'] ?? 0);
$resendRemaining = $otpPending ? max(0, (int)($otpSession['resend_available_at'] ?? 0) - time()) : 0;

ob_start();
require __DIR__ . '/templates/forgot_password.html.php';
$content = ob_get_clean();
require __DIR__ . '/templates/layout.html.php';
