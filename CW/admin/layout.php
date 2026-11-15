<?php
/**
 * admin/layout.php — Shared Admin Panel Layout Shell.
 *
 * Purpose:     Provides the persistent sidebar navigation, topbar, flash banner,
 *              and main content wrapper for all admin pages.
 * Connections: Required at the end of every admin page (index.php, films.php,
 *              genres.php, reviews.php, reviewers.php, messages.php), receiving
 *              $pageTitle, $activePage, $flash, $flashType, and $content.
 * Security:    Escapes dynamic page titles and flash messages via htmlspecialchars().
 *
 * @var string      $pageTitle
 * @var string      $activePage
 * @var string|null $flash
 * @var string|null $flashType
 * @var string      $content
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Dynamic page title -->
    <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> — Cine Admin</title>

    <!-- Google Fonts & Admin Stylesheets -->
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:opsz,wght@9..40,400;9..40,600;9..40,700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="icons/logo.png">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="layout.css?v=10">
</head>

<body>
    <!-- Ambient Background Effects -->
    <div class="bg-ambient" aria-hidden="true">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>
    <div class="noise" aria-hidden="true"></div>

    <!-- SIDEBAR NAVIGATION -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="icons/logo.png" alt="Cine Logo" class="brand-logo-img">
            <div>
                <div class="brand-name">CINE</div>
                <div class="brand-sub">Admin Panel</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">Overview</div>
            <a href="index.php"
                class="nav-item <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
                <span class="ni"><img src="icons/dashboard.png" class="ni-img" alt=""></span> Dashboard
            </a>

            <div class="nav-section">Manage</div>
            <a href="films.php"
                class="nav-item <?= ($activePage ?? '') === 'films' ? 'active' : '' ?>">
                <span class="ni"><img src="icons/films.png" class="ni-img" alt=""></span> Films
            </a>

            <a href="reviews.php"
                class="nav-item <?= ($activePage ?? '') === 'reviews' ? 'active' : '' ?>">
                <span class="ni"><img src="icons/reviews.png" class="ni-img" alt=""></span> Reviews
            </a>

            <a href="reviewers.php"
                class="nav-item <?= ($activePage ?? '') === 'reviewers' ? 'active' : '' ?>">
                <span class="ni"><img src="icons/reviewers.png" class="ni-img" alt=""></span> Reviewers
            </a>

            <a href="messages.php"
                class="nav-item <?= ($activePage ?? '') === 'messages' ? 'active' : '' ?>">
                <span class="ni"><img src="icons/messages.png" class="ni-img" alt=""></span> Messages
            </a>

            <a href="genres.php"
                class="nav-item <?= ($activePage ?? '') === 'genres' ? 'active' : '' ?>">
                <span class="ni"><img src="icons/genres.png" class="ni-img" alt=""></span> Genres
            </a>

            <div class="nav-section">Site</div>
            <a href="../index.php" class="nav-item" target="_blank">
                <span class="ni"><img src="icons/website.png" class="ni-img" alt=""></span> View Website
            </a>
        </nav>

        <!-- Admin Sign Out -->
        <div class="sidebar-footer">
            <a href="Login/logout.php" class="btn-logout">
                <span></span> Sign Out
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <div class="main-wrap">
        <div class="topbar">
            <div class="topbar-left">
                <div class="topbar-title">
                    <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
                </div>
            </div>

            <div class="topbar-right">
                <span class="admin-pill">ADMIN</span>
                <a href="../index.php" class="topbar-link" target="_blank">
                    ← Back to site
                </a>
            </div>
        </div>

        <div class="page-body">
            <!-- One-time flash notification -->
            <?php if (!empty($flash)): ?>
                <div class="flash flash-<?= htmlspecialchars($flashType ?? 'success') ?>">
                    <?= $flashType === 'success' ? '✅' : '⚠️' ?>
                    <?= htmlspecialchars($flash) ?>
                </div>
            <?php endif; ?>

            <!-- Page-specific content injected here -->
            <?= $content ?? '' ?>
        </div>
    </div>
</body>

</html>
