<?php
/**
 * image.php — Binary Image Streaming Endpoint (MySQL BLOB Delivery).
 * Purpose: Serves user avatars (`reviewer.avatar_data`), preset avatars (`avatar_presets.data`),
 *          and film posters (`film.poster_data`) stored directly in MySQL so all images persist
 *          across stateless cloud deploys without relying on local disk folders.
 * Connections: Uses DatabaseConnection (Singleton PDO) and sets safe caching/MIME headers.
 * Security: Whitelists `type` ('avatar' | 'poster' | 'preset'), casts `id` to int or validates `name`, and sets `nosniff`.
 */

require_once __DIR__ . '/includes/DatabaseConnection.php'; // Load Singleton PDO connector

$type = $_GET['type'] ?? ''; // Read requested asset type ('avatar', 'poster', or 'preset')
$id   = (int)($_GET['id'] ?? 0); // Cast record ID to integer
$name = trim((string)($_GET['name'] ?? '')); // Read preset slug when type=preset

if (!in_array($type, ['avatar', 'poster', 'preset'], true)) { // Reject unsupported asset type
    http_response_code(400); // Send HTTP 400 Bad Request
    exit; // Stop execution
}

$db = DatabaseConnection::getInstance()->getConnection(); // Acquire shared PDO connection

if ($type === 'preset') { // Query bundled preset avatar BLOB by slug name
    if ($name === '' || !preg_match('/^[a-z0-9-]+$/i', $name)) { // Enforce safe alphanumeric/hyphen slug
        http_response_code(400); // Send HTTP 400 Bad Request
        exit; // Stop execution
    }
    $stmt = $db->prepare('SELECT data, mime FROM avatar_presets WHERE name = ? LIMIT 1'); // Prepare preset lookup
    $stmt->execute([$name]); // Execute parameterized query
} else {
    if ($id <= 0) { // Reject invalid numeric ID
        http_response_code(400); // Send HTTP 400 Bad Request
        exit; // Stop execution
    }
    if ($type === 'avatar') { // Query reviewer avatar BLOB
        $stmt = $db->prepare('SELECT avatar_data AS data, avatar_mime AS mime FROM reviewer WHERE id = ? LIMIT 1'); // Prepare avatar lookup
    } else { // Query film poster BLOB
        $stmt = $db->prepare('SELECT poster_data AS data, poster_mime AS mime FROM film WHERE id = ? LIMIT 1'); // Prepare poster lookup
    }
    $stmt->execute([$id]); // Execute parameterized query
}

$row = $stmt->fetch(); // Fetch binary payload and MIME type

if (!$row || empty($row['data'])) { // Return 404 when no binary image is stored
    http_response_code(404); // Send HTTP 404 Not Found
    exit; // Stop execution
}

$mime = is_string($row['mime']) && $row['mime'] !== '' ? $row['mime'] : 'image/jpeg'; // Fallback MIME type
header('Content-Type: ' . $mime); // Declare actual image MIME type
header('Content-Length: ' . strlen($row['data'])); // Declare exact byte length
header('Cache-Control: public, max-age=86400'); // Cache image in browser for 24 hours
header('X-Content-Type-Options: nosniff'); // Prevent browser MIME-type sniffing
echo $row['data']; // Stream raw image bytes to client
