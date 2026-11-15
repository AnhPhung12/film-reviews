<?php
/**
 * admin/messages.php — Admin Contact Messages Management.
 *
 * Purpose:     Lists contact messages sent by users and supports soft-deleting
 *              messages from the admin inbox.
 * Connections: Protected by Login/auth.php; uses ../includes/DataBaseFunctions.php
 *              (getAllMessages, deleteMessage) and renders inside admin/layout.php.
 * Security:    Escapes all user-supplied sender names, emails, subjects, and
 *              message previews via htmlspecialchars(); uses PRG redirects.
 */

require_once 'Login/auth.php'; // Enforce admin authentication before accessing messages
require_once '../includes/DataBaseFunctions.php'; // Load message query and soft-delete helpers

// Page configuration
$pageTitle  = 'Messages'; // Set page title in admin topbar
$activePage = 'messages'; // Highlight Messages link in sidebar navigation

// Handle POST soft-delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') { // Process message deletion on POST
    $id = (int)($_POST['id'] ?? 0); // Cast target message ID to integer

    // Soft-delete message (sets is_active = 0 in database)
    deleteMessage($id); // Mark message as inactive instead of hard-deleting

    $_SESSION['flash']      = 'Message deleted.'; // Store confirmation flash message
    $_SESSION['flash_type'] = 'success'; // Style flash banner as success

    // Redirect via PRG pattern to prevent duplicate form submissions
    header('Location: messages.php'); // Redirect back to messages list
    exit; // Stop execution after redirect
}

// Retrieve and clear one-time flash message
$flash     = $_SESSION['flash']      ?? ''; // Read pending flash notification
$flashType = $_SESSION['flash_type'] ?? 'success'; // Default flash style to 'success'
unset($_SESSION['flash'], $_SESSION['flash_type']); // Remove flash keys so alert displays once

// Fetch all active contact messages joined with sender details
$messages = getAllMessages(); // Query active contact_us rows ordered newest first

ob_start(); // Begin output buffering to capture table HTML for admin/layout.php
?>

<!-- MESSAGES TABLE -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <img src="icons/messages.png" class="card-title-icon" alt=""> Contact Messages
            <span class="badge badge-gray"><?= count($messages) ?></span>
        </div>
    </div>

    <?php if (empty($messages)): ?>
        <div style="padding:3rem;text-align:center;color:var(--text-dimmer);font-style:italic">
            No messages yet.
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Sender</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $msg): ?>
                        <tr>
                            <td class="mono dim" style="font-size:.78rem">
                                <?= $msg['id'] ?>
                            </td>

                            <td style="font-weight:600">
                                <?= htmlspecialchars($msg['fullname'] ?? 'Unknown User') ?>
                                <br>
                            </td>

                            <td class="mono" style="font-size:.78rem;color:var(--text-dim)">
                                <a href="mailto:<?= htmlspecialchars($msg['email']) ?>"
                                    style="color:var(--blue);text-decoration:none">
                                    <?= htmlspecialchars($msg['email']) ?>
                                </a>
                            </td>

                            <td style="font-weight:600;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                <?= htmlspecialchars($msg['subject']) ?>
                            </td>

                            <!-- Message preview truncated to 100 UTF-8 characters -->
                            <td style="max-width:220px;font-size:.82rem;color:var(--text-dim)">
                                <?= htmlspecialchars(mb_substr($msg['content'], 0, 100)) ?>
                                <?= mb_strlen($msg['content']) > 100 ? '…' : '' ?>
                            </td>

                            <td class="mono dim" style="font-size:.72rem;white-space:nowrap">
                                <?= date('M j, Y H:i', strtotime($msg['sent_at'])) ?>
                            </td>

                            <td>
                                <form method="POST"
                                    onsubmit="return confirm('Delete this message?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                                    <button type="submit"
                                        class="btn btn-danger btn-sm">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean(); // Capture buffered messages table HTML into $content
require 'layout.php'; // Render $content inside the shared admin layout shell
