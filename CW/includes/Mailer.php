<?php
/**
 * Mailer.php — Native PHP SMTP Email Client.
 * Purpose: Sends multipart (plain-text + HTML) emails over SMTP with STARTTLS/SSL without Composer dependencies.
 * Connections:
 *   - Called by: forgot_password.php to deliver password reset links.
 *   - Reads SMTP credentials from: mail_config.php.
 * OOP/Patterns: Static Utility Facade wrapping PHP stream sockets.
 * Data Structures & Algorithms:
 *   - Implements RFC-compliant SMTP handshake (EHLO -> STARTTLS -> AUTH LOGIN -> MAIL FROM -> RCPT TO -> DATA -> QUIT).
 *   - Encodes MIME multipart/alternative body with Base64 transfer encoding and dot-stuffing for SMTP DATA.
 */
class Mailer
{
    /** Checks whether an HTTPS mail API (Brevo / Webhook) or SMTP credentials are configured. */
    public static function isConfigured(): bool
    {
        if (trim((string)(getenv('CW_BREVO_API_KEY') ?: '')) !== '' || trim((string)(getenv('CW_MAIL_WEBHOOK_URL') ?: '')) !== '') {
            return true;
        }
        $cfg = self::loadConfig(); // Load SMTP configuration array
        return trim((string)($cfg['host'] ?? '')) !== '' // Verify host is non-empty
            && trim((string)($cfg['username'] ?? '')) !== '' // Verify username is non-empty
            && trim((string)($cfg['password'] ?? '')) !== ''; // Verify password is non-empty
    }

    /** Sends an email via HTTPS API (Render-compatible) or SMTP (fallback for local/VPS). */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody = ''
    ): array {
        $cfg = self::loadConfig(); // Read SMTP settings from mail_config.php
        $host = trim((string)($cfg['host'] ?? '')); // SMTP server hostname
        $port = (int)($cfg['port'] ?? 587); // SMTP port number (default 587)
        $encryption = strtolower(trim((string)($cfg['encryption'] ?? 'tls'))); // Encryption mode ('tls', 'ssl', or '')
        $username = trim((string)($cfg['username'] ?? '')); // SMTP auth username
        $password = (string)($cfg['password'] ?? ''); // SMTP auth password/app password
        $fromEmail = trim((string)($cfg['from_email'] ?? '')) ?: $username; // Sender address (falls back to username)
        $fromName = trim((string)($cfg['from_name'] ?? 'Cine')); // Sender display name

        // Auto-generate plain-text alternative from HTML if not provided
        if ($textBody === '') {
            $textBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody))); // Convert line-break tags to newlines and strip HTML
        }

        // 1. Prefer HTTPS Brevo API when CW_BREVO_API_KEY is set (bypasses Render Free outbound SMTP port blocks)
        $brevoKey = trim((string)(getenv('CW_BREVO_API_KEY') ?: ''));
        if ($brevoKey !== '') {
            $senderEmail = $fromEmail ?: 'noreply@example.com';
            $payload = json_encode([
                'sender'      => ['name' => $fromName, 'email' => $senderEmail],
                'to'          => [['email' => $toEmail, 'name' => $toName ?: $toEmail]],
                'subject'     => $subject,
                'htmlContent' => $htmlBody,
                'textContent' => $textBody,
            ], JSON_UNESCAPED_UNICODE);
            return self::postJson('https://api.brevo.com/v3/smtp/email', $payload, [
                'api-key: ' . $brevoKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ]);
        }

        // 2. Or use an HTTPS Mail Webhook (e.g. Google Apps Script Web App) when CW_MAIL_WEBHOOK_URL is set
        $webhookUrl = trim((string)(getenv('CW_MAIL_WEBHOOK_URL') ?: ''));
        if ($webhookUrl !== '') {
            $payload = json_encode([
                'to'       => $toEmail,
                'toName'   => $toName ?: $toEmail,
                'fromName' => $fromName,
                'subject'  => $subject,
                'html'     => $htmlBody,
                'text'     => $textBody,
            ], JSON_UNESCAPED_UNICODE);
            return self::postJson($webhookUrl, $payload, [
                'Content-Type: application/json',
                'Accept: application/json',
            ]);
        }

        // Fallback to native mail() when SMTP credentials are absent
        if ($host === '' || $username === '' || $password === '') {
            if (trim((string)ini_get('sendmail_path')) !== '' && $fromEmail !== '') { // Use sendmail if configured in php.ini
                $headers = implode("\r\n", [ // Build basic HTML email headers
                    'MIME-Version: 1.0',
                    'Content-Type: text/html; charset=UTF-8',
                    'From: ' . self::formatAddress($fromEmail, $fromName),
                ]);
                $sent = @mail($toEmail, self::encodeHeader($subject), $htmlBody, $headers); // Dispatch via PHP mail()
                return $sent
                    ? ['ok' => true, 'error' => ''] // Return success status
                    : ['ok' => false, 'error' => 'PHP mail() failed to send the email.']; // Return failure message
            }
            return [ // Abort when neither SMTP nor sendmail is configured
                'ok'    => false,
                'error' => 'SMTP is not configured yet. Please add your SMTP username and app password in CW/includes/mail_config.php.',
            ];
        }

        // Auto-generate plain-text alternative from HTML if not provided
        if ($textBody === '') {
            $textBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody))); // Convert line-break tags to newlines and strip HTML
        }

        // Open TCP or SSL socket to the SMTP server with strict peer verification
        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port; // Build socket transport URI
        $context = stream_context_create([ // Enforce TLS certificate verification
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
            ],
        ]);

        $errno = 0; // Socket error code output
        $errstr = ''; // Socket error message output
        $socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context); // Open connection with 15s timeout
        if (!$socket) { // Return error if TCP/SSL connection failed
            return ['ok' => false, 'error' => "Could not connect to SMTP server ({$host}:{$port}): {$errstr}"];
        }
        stream_set_timeout($socket, 15); // Set read/write timeout on socket

        try {
            // 1. Server greeting & EHLO handshake
            self::expectCode($socket, [220]); // Wait for initial 220 Service Ready banner

            $ehloHost = preg_replace('/[^a-zA-Z0-9.-]/', '', $_SERVER['SERVER_NAME'] ?? 'localhost') ?: 'localhost'; // Sanitize local hostname for EHLO
            self::sendCommand($socket, "EHLO {$ehloHost}", [250]); // Introduce client and request ESMTP capabilities

            // 2. Upgrade connection to TLS if STARTTLS is configured
            if ($encryption === 'tls') {
                self::sendCommand($socket, 'STARTTLS', [220]); // Request TLS upgrade
                $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT); // Negotiate TLS 1.2/1.3
                if (!$crypto) { // Throw if TLS handshake fails
                    throw new RuntimeException('Failed to establish TLS encryption with SMTP server.');
                }
                self::sendCommand($socket, "EHLO {$ehloHost}", [250]); // Re-send EHLO over encrypted channel
            }

            // 3. Authenticate using Base64-encoded AUTH LOGIN
            self::sendCommand($socket, 'AUTH LOGIN', [334]); // Initiate AUTH LOGIN sequence
            self::sendCommand($socket, base64_encode($username), [334]); // Send Base64-encoded username
            self::sendCommand($socket, base64_encode($password), [235]); // Send Base64-encoded password and expect 235 Auth Success

            // 4. Envelope & DATA initiation
            self::sendCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]); // Specify envelope sender
            self::sendCommand($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]); // Specify envelope recipient
            self::sendCommand($socket, 'DATA', [354]); // Begin message body transmission

            // 5. Build MIME multipart/alternative message
            $boundary = 'cine_' . bin2hex(random_bytes(12)); // Generate unique MIME boundary token
            $headers = [ // Assemble RFC 5322 message headers
                'Date: ' . date('r'),
                'From: ' . self::formatAddress($fromEmail, $fromName),
                'To: ' . self::formatAddress($toEmail, $toName),
                'Subject: ' . self::encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            ];

            $mimeBody = implode("\r\n", [ // Construct plain-text and HTML parts in Base64
                '--' . $boundary,
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                rtrim(chunk_split(base64_encode($textBody))),
                '--' . $boundary,
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                rtrim(chunk_split(base64_encode($htmlBody))),
                '--' . $boundary . '--',
            ]);

            // Normalize line endings, apply SMTP dot-stuffing, and append end-of-data marker
            $payload = implode("\r\n", $headers) . "\r\n\r\n" . $mimeBody; // Combine headers and MIME body
            $payload = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $payload)); // Escape leading dots per RFC 5321
            $payload = str_replace("\n", "\r\n", $payload) . "\r\n.\r\n"; // Restore CRLF endings and terminate DATA with <CRLF>.<CRLF>

            fwrite($socket, $payload); // Transmit full message payload
            self::expectCode($socket, [250]); // Confirm server accepted message for delivery
            @self::sendCommand($socket, 'QUIT', [221]); // Gracefully close SMTP session
            fclose($socket); // Close TCP/SSL socket

            return ['ok' => true, 'error' => '']; // Report successful delivery
        } catch (Throwable $e) {
            if (is_resource($socket)) { // Ensure socket is closed on error
                fclose($socket);
            }
            return ['ok' => false, 'error' => $e->getMessage()]; // Return error details
        }
    }

    /** Sends an HTTPS JSON POST request (used for Brevo API or Google Apps Script webhook). */
    private static function postJson(string $url, string $jsonPayload, array $headers): array
    {
        $ctx = stream_context_create([
            'http' => [
                'method'          => 'POST',
                'header'          => implode("\r\n", $headers),
                'content'         => $jsonPayload,
                'timeout'         => 15,
                'ignore_errors'   => true,
                'follow_location' => 1,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $ctx);
        $statusLine = $http_response_header[0] ?? '';
        $code = preg_match('/\s(\d{3})\s/', $statusLine, $m) ? (int)$m[1] : 0;
        if ($response !== false && $code >= 200 && $code < 300) {
            return ['ok' => true, 'error' => ''];
        }
        $detail = is_string($response) && $response !== '' ? trim(strip_tags($response)) : $statusLine;
        return ['ok' => false, 'error' => 'Mail API error (' . $code . '): ' . mb_substr($detail, 0, 200)];
    }

    /** Loads SMTP settings from mail_config.php. */
    private static function loadConfig(): array
    {
        $path = __DIR__ . '/mail_config.php'; // Resolve path to local mail config
        return is_file($path) ? (array)(require $path) : []; // Return config array or empty fallback
    }

    /** Encodes a header value using UTF-8 Base64 MIME B-encoding. */
    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?='; // Wrap UTF-8 string in RFC 2047 encoded-word
    }

    /** Formats an email address with an optional encoded display name (strips CR/LF to prevent header injection). */
    private static function formatAddress(string $email, string $name): string
    {
        $cleanName = trim(str_replace(["\r", "\n"], '', $name)); // Strip CR/LF to prevent SMTP header injection
        return $cleanName !== '' ? self::encodeHeader($cleanName) . ' <' . $email . '>' : '<' . $email . '>'; // Return formatted address
    }

    /** Writes an SMTP command and asserts the server responds with an expected status code. */
    private static function sendCommand($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n"); // Send command terminated by CRLF
        return self::expectCode($socket, $expectedCodes); // Read and validate server reply code
    }

    /** Reads multi-line SMTP responses until the final status line and validates the 3-digit code. */
    private static function expectCode($socket, array $expectedCodes): string
    {
        $response = ''; // Accumulate multi-line SMTP reply
        while (($line = fgets($socket, 515)) !== false) { // Read up to 515 bytes per RFC line limit
            $response .= $line; // Append line to full response
            // In SMTP, a space at index 3 indicates the final line of a reply
            if (isset($line[3]) && $line[3] === ' ') {
                break; // Stop reading after final status line
            }
        }
        $code = (int)substr($response, 0, 3); // Parse 3-digit numeric SMTP status code
        if (!in_array($code, $expectedCodes, true)) { // Throw exception on unexpected status code
            throw new RuntimeException('SMTP error (' . $code . '): ' . trim($response));
        }
        return $response; // Return full server response
    }
}
