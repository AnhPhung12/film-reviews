<?php

/**
 * mail_config.php — SMTP Mailer Configuration.
 * Purpose: Stores SMTP host, port, encryption, and sender credentials for outgoing emails.
 * Connections: Loaded by Mailer::loadConfig() when sending password reset emails.
 * OOP/Patterns: Configuration array module.
 * Data Structures & Algorithms: Associative array of SMTP transport settings.
 *
 * For Gmail:
 * 1. Enable 2-Step Verification on your Google Account.
 * 2. Create an App Password at https://myaccount.google.com/apppasswords
 * 3. Put your Gmail address in 'username' / 'from_email' and the 16-char App Password in 'password'.
 */
return [
    // SMTP server hostname
    'host'       => getenv('CW_SMTP_HOST') ?: 'smtp.gmail.com',
    // SMTP port: 587 for 'tls' (STARTTLS), 465 for 'ssl'
    'port'       => (int)(getenv('CW_SMTP_PORT') ?: 587),
    // Transport encryption: 'tls', 'ssl', or ''
    'encryption' => getenv('CW_SMTP_ENCRYPTION') ?: 'tls',
    // SMTP authentication username (e.g. 'yourname@gmail.com')
    'username'   => getenv('CW_SMTP_USERNAME') ?: '',
    // SMTP password or 16-character App Password
    'password'   => getenv('CW_SMTP_PASSWORD') ?: '',
    // Sender email address (defaults to 'username' if left empty)
    'from_email' => getenv('CW_MAIL_FROM') ?: '',
    // Display name shown in the From header
    'from_name'  => getenv('CW_MAIL_FROM_NAME') ?: 'Cine Film Reviews',
];
