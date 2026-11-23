<?php
/**
 * admin/profile.php — Administrator Profile & Session Tracking Controller.
 *
 * Purpose:     Displays the administrator's account banner (username, Gmail,
 *              login date/time tracking, and live local time), platform summary
 *              stats, and session tracking details matching the client profile hub.
 * Connections: Protected by Login/auth.php; uses ../includes/DataBaseFunctions.php
 *              and renders inside admin/layout.php.
 */

require_once 'Login/auth.php';
require_once '../includes/DataBaseFunctions.php';

$pageTitle  = 'Admin Profile';
$activePage = 'profile';

$flash     = $_SESSION['flash']      ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash'], $_SESSION['flash_type']);

$adminUser    = $_SESSION['admin_username'] ?? 'phung';
$adminEmail   = $_SESSION['admin_email']    ?? 'jy261205@gmail.com';
$adminLoginAt = $_SESSION['admin_login_at'] ?? date('c');

$loginTs      = strtotime($adminLoginAt) ?: time();
$loginDateStr = date('Y-m-d', $loginTs);
$loginTimeStr = date('H:i:s', $loginTs);
$adminInitial = strtoupper(mb_substr(trim($adminUser) ?: 'A', 0, 1));

$stats = getStats();
$db    = DatabaseConnection::getInstance()->getConnection();
$messageCount = (int)$db->query('SELECT COUNT(*) FROM contact_us WHERE is_active = 1')->fetchColumn();

ob_start();
?>

<!-- ADMIN ACCOUNT BANNER (MATCHING CLIENT PROFILE) -->
<div class="admin-profile-banner">
    <div class="admin-profile-left">
        <div class="admin-avatar-badge" aria-hidden="true"><?= htmlspecialchars($adminInitial, ENT_QUOTES, 'UTF-8') ?></div>
        <div>
            <div class="admin-profile-eyebrow">ADMIN ACCOUNT</div>
            <div class="admin-profile-name"><?= htmlspecialchars(ucfirst($adminUser), ENT_QUOTES, 'UTF-8') ?> <span class="admin-pill" style="margin-left:0.4rem;vertical-align:middle;">ADMIN</span></div>
            <div class="admin-profile-email"><?= htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="admin-profile-tracking">
                <span class="admin-tracking-item">
                    <span aria-hidden="true">📅</span>
                    <span>Signed in <span data-local-dt="<?= htmlspecialchars($adminLoginAt, ENT_QUOTES, 'UTF-8') ?>" data-local-fmt="date-time-parens"><?= htmlspecialchars($loginDateStr . ' (' . $loginTimeStr . ')', ENT_QUOTES, 'UTF-8') ?></span></span>
                </span>
                <span class="admin-tracking-item">
                    <span aria-hidden="true">🕒</span>
                    <span>Current: <span id="adminLiveClock"><?= htmlspecialchars(date('Y-m-d (H:i:s)'), ENT_QUOTES, 'UTF-8') ?></span></span>
                </span>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:0.65rem;flex-wrap:wrap;">
        <a href="messages.php" class="btn btn-ghost btn-sm">View Messages</a>
        <a href="index.php" class="btn btn-primary btn-sm">← Back to Dashboard</a>
    </div>
</div>

<!-- SUMMARY STAT CARDS -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-label">Total Films</div>
        <div class="stat-num"><?= (int)$stats['films'] ?></div>
        <a href="films.php" class="stat-link">Manage films →</a>
    </div>

    <div class="stat-card">
        <div class="stat-label">Total Reviews</div>
        <div class="stat-num"><?= (int)$stats['reviews'] ?></div>
        <a href="reviews.php" class="stat-link">Manage reviews →</a>
    </div>

    <div class="stat-card">
        <div class="stat-label">Reviewers</div>
        <div class="stat-num"><?= (int)$stats['reviewers'] ?></div>
        <a href="reviewers.php" class="stat-link">View reviewers →</a>
    </div>

    <div class="stat-card">
        <div class="stat-label">Contact Messages</div>
        <div class="stat-num"><?= $messageCount ?></div>
        <a href="messages.php" class="stat-link">Open inbox →</a>
    </div>
</div>

<!-- ACCOUNT & TRACKING DETAILS TABLE -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Account &amp; Session Tracking</div>
    </div>
    <div class="table-wrap">
        <table>
            <tbody>
                <tr>
                    <th style="width:220px;">Username</th>
                    <td><strong><?= htmlspecialchars($adminUser, ENT_QUOTES, 'UTF-8') ?></strong></td>
                </tr>
                <tr>
                    <th>Gmail / Contact Email</th>
                    <td><?= htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <th>Role</th>
                    <td><span class="admin-pill">Administrator</span></td>
                </tr>
                <tr>
                    <th>Login Date</th>
                    <td><span data-local-dt="<?= htmlspecialchars($adminLoginAt, ENT_QUOTES, 'UTF-8') ?>" data-local-fmt="date"><?= htmlspecialchars($loginDateStr, ENT_QUOTES, 'UTF-8') ?></span></td>
                </tr>
                <tr>
                    <th>Login Time</th>
                    <td><span data-local-dt="<?= htmlspecialchars($adminLoginAt, ENT_QUOTES, 'UTF-8') ?>" data-local-fmt="time"><?= htmlspecialchars($loginTimeStr, ENT_QUOTES, 'UTF-8') ?></span></td>
                </tr>
                <tr>
                    <th>Live Local Time</th>
                    <td><span id="adminLiveClockDetail"><?= htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8') ?></span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    (function () {
        var pad = function (n) { return String(n).padStart(2, '0'); };
        function updateAdminClock() {
            var now = new Date();
            var d = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
            var t = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
            var el1 = document.getElementById('adminLiveClock');
            var el2 = document.getElementById('adminLiveClockDetail');
            if (el1) el1.textContent = d + ' (' + t + ')';
            if (el2) el2.textContent = d + ' ' + t;
        }
        updateAdminClock();
        setInterval(updateAdminClock, 1000);
    })();
</script>

<?php
$content = ob_get_clean();
require 'layout.php';
