<?php
/**
 * logoutUser.php — Public User Logout Handler.
 *
 * Purpose:     Clears the authenticated user's session keys and redirects back
 *              to the homepage.
 * Connections: Redirects to index.php after clearing $_SESSION user keys.
 */

session_start(); // Resume the active session so user session keys can be cleared
unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email']); // Remove authenticated user data without destroying admin session state
header('Location: index.php'); // Redirect the user back to the public homepage
exit; // Terminate script execution immediately after redirect
