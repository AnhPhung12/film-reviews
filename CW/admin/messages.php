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
require_once '../includes/Mailer.php'; // Load SMTP/API mailer for admin replies

// Page configuration
$pageTitle  = 'Messages'; // Set page title in admin topbar
$activePage = 'messages'; // Highlight Messages link in sidebar navigation

// Handle POST admin reply action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {
    $id    = (int)($_POST['id'] ?? 0);
    $reply = trim((string)($_POST['reply'] ?? ''));

    if ($id > 0 && $reply !== '') {
        $msgRow = (new CinemaRepository())->replyToMessage($id, $reply);
        if ($msgRow) {
            if (!empty($msgRow['email']) && Mailer::isConfigured()) {
                $subject = 'Re: [Cine] ' . ($msgRow['subject'] ?? 'Your inquiry');
                $textBody = "Hello " . ($msgRow['fullname'] ?? 'Reviewer') . ",\n\n"
                    . "An administrator replied to your message:\n"
                    . "----------------------------------------\n"
                    . $reply . "\n"
                    . "----------------------------------------\n\n"
                    . "Original message (" . ($msgRow['subject'] ?? '') . "):\n"
                    . ($msgRow['content'] ?? '');
                $htmlBody = nl2br(htmlspecialchars($textBody, ENT_QUOTES, 'UTF-8'));
                Mailer::send($msgRow['email'], $msgRow['fullname'] ?? 'Reviewer', $subject, $htmlBody, $textBody);
            }
            $_SESSION['flash']      = 'Reply saved and sent to reviewer.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash']      = 'Message not found.';
            $_SESSION['flash_type'] = 'error';
        }
    } else {
        $_SESSION['flash']      = 'Please enter a reply message.';
        $_SESSION['flash_type'] = 'error';
    }

    header('Location: messages.php');
    exit;
}

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
                        <th>#</th>
                        <th>Sender</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $idx => $msg): ?>
                        <tr>
                            <td class="mono dim" style="font-size:.78rem">
                                <?= $idx + 1 ?>
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
                            <td style="max-width:240px;font-size:.82rem;color:var(--text-dim)">
                                <?= htmlspecialchars(mb_substr($msg['content'], 0, 100)) ?>
                                <?= mb_strlen($msg['content']) > 100 ? '…' : '' ?>
                                <?php if (!empty($msg['admin_reply'])): ?>
                                    <div style="margin-top:0.35rem;padding:0.3rem 0.5rem;border-radius:6px;background:rgba(56,189,248,0.12);border-left:2px solid #38bdf8;font-size:.76rem;color:#bae6fd;">
                                        <strong>Reply:</strong> <?= htmlspecialchars(mb_substr($msg['admin_reply'], 0, 70)) ?><?= mb_strlen($msg['admin_reply']) > 70 ? '…' : '' ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="mono dim" style="font-size:.72rem;white-space:nowrap"
                                data-local-dt="<?= htmlspecialchars(date('Y-m-d\TH:i:s+07:00', strtotime($msg['sent_at'])), ENT_QUOTES, 'UTF-8') ?>" data-local-fmt="short-dt">
                                <?= date('M j, Y H:i', strtotime($msg['sent_at'])) ?>
                            </td>

                            <td>
                                <div style="display:flex;gap:0.4rem;align-items:center;">
                                    <button type="button" class="btn btn-ghost btn-sm" title="Reply to client"
                                        data-id="<?= (int)$msg['id'] ?>"
                                        data-sender="<?= htmlspecialchars($msg['fullname'] ?? 'User', ENT_QUOTES, 'UTF-8') ?>"
                                        data-email="<?= htmlspecialchars($msg['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-subject="<?= htmlspecialchars($msg['subject'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-content="<?= htmlspecialchars($msg['content'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-reply="<?= htmlspecialchars($msg['admin_reply'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="openReplyModal(this)">💬</button>
                                    <form method="POST"
                                        onsubmit="return confirm('Delete this message?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                                        <button type="submit"
                                            class="btn btn-danger btn-sm">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ADMIN REPLY MODAL -->
<div id="replyModal" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;">
    <div onclick="closeReplyModal()" style="position:absolute;inset:0;background:rgba(0,0,0,0.75);backdrop-filter:blur(5px);"></div>
    <div style="position:relative;z-index:2;width:min(540px,92vw);background:var(--bg2,#1f1820);border:1px solid var(--border,#3b3252);border-radius:12px;padding:1.25rem;box-shadow:0 24px 48px rgba(0,0,0,0.65);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.85rem;">
            <h3 style="margin:0;font-size:1.05rem;font-weight:700;">💬 Reply to <span id="replySenderName"></span></h3>
            <button type="button" class="btn btn-ghost btn-sm" onclick="closeReplyModal()">✕</button>
        </div>
        <div style="font-size:0.82rem;color:var(--text-dim);margin-bottom:0.75rem;padding:0.65rem;border-radius:8px;background:rgba(255,255,255,0.03);border:1px solid var(--border,#3b3252);">
            <div><strong>Email:</strong> <span id="replySenderEmail"></span></div>
            <div><strong>Subject:</strong> <span id="replyMsgSubject"></span></div>
            <div id="replyMsgContent" style="margin-top:0.4rem;white-space:pre-line;"></div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="id" id="replyMsgId" value="">
            <label class="form-label" style="display:block;margin-bottom:0.4rem;">Your Reply (saved to client profile &amp; emailed)</label>
            <textarea name="reply" id="replyTextarea" class="form-input" rows="4" required placeholder="Write your response to the reviewer..." style="width:100%;resize:vertical;"></textarea>
            <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:0.9rem;">
                <button type="button" class="btn btn-ghost btn-sm" onclick="closeReplyModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Send Reply</button>
            </div>
        </form>
    </div>
</div>
<script>
function openReplyModal(btn) {
    document.getElementById('replyMsgId').value = btn.dataset.id || '';
    document.getElementById('replySenderName').textContent = btn.dataset.sender || 'Reviewer';
    document.getElementById('replySenderEmail').textContent = btn.dataset.email || '';
    document.getElementById('replyMsgSubject').textContent = btn.dataset.subject || '';
    document.getElementById('replyMsgContent').textContent = btn.dataset.content || '';
    document.getElementById('replyTextarea').value = btn.dataset.reply || '';
    document.getElementById('replyModal').style.display = 'flex';
}
function closeReplyModal() {
    document.getElementById('replyModal').style.display = 'none';
}
</script>

<?php
$content = ob_get_clean(); // Capture buffered messages table HTML into $content
require 'layout.php'; // Render $content inside the shared admin layout shell
