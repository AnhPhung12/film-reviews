<?php
/**
 * CsrfHelper.php — Security & Cross-Site Request Forgery Protection.
 * Purpose: Generates per-session cryptographic tokens and validates POST submissions.
 * Connections: Used by admin/reviewers.php and form handlers to prevent forged requests.
 * OOP/Patterns: Static Security Helper.
 * Data Structures & Algorithms:
 *   - Uses CSPRNG (random_bytes(32)) hex-encoded into a 64-char token stored in $_SESSION.
 *   - Uses hash_equals() for constant-time string comparison (mitigates timing attacks).
 */
class CsrfHelper
{
    private const SESSION_KEY = '_cine_csrf_token'; // Session slot holding the active CSRF token

    /** Returns the current session CSRF token, generating a 64-hex-char CSPRNG token if missing. */
    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) { // Start PHP session if not already active
            session_start();
        }
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) { // Generate a new 256-bit token if absent
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY]; // Return active session CSRF token
    }

    /** Validates a submitted token against the session token using constant-time comparison. */
    public static function verify(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) { // Ensure session is available before reading token
            session_start();
        }
        $expected = $_SESSION[self::SESSION_KEY] ?? ''; // Retrieve stored session token
        return is_string($token) && is_string($expected) && $expected !== '' && hash_equals($expected, $token); // Constant-time comparison mitigates timing attacks
    }

    /** Renders a hidden HTML form input containing the escaped CSRF token. */
    public static function input(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">'; // Output XSS-safe hidden CSRF field
    }
}
