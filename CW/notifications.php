<?php
/**
 * notifications.php — Reviewer Notification Bell Action Handler.
 *
 * Purpose: Marks individual or all notifications as read for the logged-in
 *          reviewer and redirects safely to the target page.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/DataBaseFunctions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: loginUser.php');
    exit;
}

$reviewerId = (int)$_SESSION['user_id'];
$repo       = new CinemaRepository();
$action     = $_REQUEST['action'] ?? '';

if ($action === 'read') {
    $id     = (int)($_GET['id'] ?? 0);
    $target = $id > 0 ? $repo->markNotificationRead($id, $reviewerId) : null;
    // Allow only relative internal paths to prevent open redirects
    if (!$target || preg_match('#^(?:https?:)?//#i', $target)) {
        $target = 'profile.php';
    }
    header('Location: ' . $target);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'read_all') {
    $repo->markAllNotificationsRead($reviewerId);
    $back = $_SERVER['HTTP_REFERER'] ?? 'profile.php';
    $parsed = parse_url($back, PHP_URL_PATH);
    $safeBack = $parsed ? basename($parsed) : 'profile.php';
    header('Location: ' . ($safeBack ?: 'profile.php'));
    exit;
}

header('Location: profile.php');
exit;
