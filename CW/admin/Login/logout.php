<?php
/**
 * admin/Login/logout.php — Admin Session Termination Handler.
 *
 * Purpose:     Destroys the active admin session and redirects to the admin
 *              login screen.
 * Connections: Linked from the Sign Out button in admin/layout.php.
 */

session_start(); // Resume the active admin session before destroying it
session_destroy(); // Destroy all session data to log out the administrator
header('Location: login.php'); // Redirect back to the admin login page
exit; // Terminate script execution immediately after redirect
