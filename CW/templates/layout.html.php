<?php
/**
 * layout.html.php — Master Page Layout Shell.
 * Purpose: Wraps all public views with shared HTML head, navbar, flash alerts, footer, and Cine AI chat widget.
 * Connections:
 *   - Included by: Every public page controller (index.php, films.php, film.php, reviews.php, etc.).
 *   - Uses: UserRepository (suspension check & nav avatar hydration) and PresentationHelper::avatarHtml().
 *   - Communicates via fetch() with: ai_chat.php (Cine AI conversation history & message endpoint).
 * OOP/Patterns: Two-Step View / Master Layout Template with active-session suspension guard.
 * Data Structures & Algorithms:
 *   - Client-side AI chat maintains a bounded `history` array (last 8 turns sent as context) and sessionStorage ID.
 *   - Markdown-lite parser (formatAiText) safely escapes HTML before rendering bold, italic, headings, and lists.
 *
 * @var string      $pageTitle
 * @var string|null $flashMessage
 * @var string|null $flashType
 * @var string      $content
 */

if (session_status() === PHP_SESSION_NONE) session_start(); // Ensure PHP session is active for auth & flash state
require_once __DIR__ . '/../includes/UserRepository.php'; // Load UserRepository for suspension check and nav avatar
// Enforce account suspension immediately on every page load for logged-in users
if (!empty($_SESSION['user_id'])) { // Only check when a user is logged in
    $activeUser = (new UserRepository())->findById((int)$_SESSION['user_id']); // Fetch fresh account status from DB
    if (!$activeUser || !empty($activeUser['is_suspended'])) { // Force logout if account was deleted or suspended
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_avatar']); // Clear user session keys
        $_SESSION['flash'] = 'Your account is suspended. Please contact the administrator.'; // Set suspension notice
        $_SESSION['flash_type'] = 'error'; // Style flash banner as error
        header('Location: loginUser.php'); // Redirect to login page
        exit; // Stop rendering protected layout
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Cine') ?> — Cine</title> <!-- Dynamic page title with fallback -->
    <link rel="preconnect" href="https://fonts.googleapis.com"> <!-- Preconnect for Google Fonts to improve loading -->
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin> <!-- Preconnect for Google Fonts with CORS for better performance -->
    <!-- Preconnect with font files -->
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Inter:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="icons/logo.png">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="reviews.css?v=21">
</head>

<body>

    <!-- Background Elements -->
    <div class="bg-ambient" aria-hidden="true">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>
    <!-- Noise Texture -->
    <div class="noise" aria-hidden="true"></div>

    <!-- Navigation -->
    <nav class="navbar glass" role="navigation" aria-label="Main navigation">
        <a href="index.php" class="nav-brand" aria-label="Cine home">
            <img src="icons/logo.png" alt="Cine Logo" class="brand-logo-img">
            <span class="brand-text">CINE></span>
        </a>

        <!-- Navigation Links -->
        <ul class="nav-links" id="navLinks" role="list">
            <!-- Navigation items -->
            <li><a href="index.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php'      ? 'active' : '' ?>">Home</a></li> <!-- Highlight active page -->
            <li><a href="films.php" class="nav-link <?= in_array(basename($_SERVER['PHP_SELF']), ['films.php', 'film.php'], true) ? 'active' : '' ?>">Films</a></li>
            <li><a href="reviews.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'reviews.php'    ? 'active' : '' ?>">Reviews</a></li>
            <li><a href="contact.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'contact.php' ? 'active' : '' ?>">Contact</a></li>
            <li><a href="add_review.php" class="nav-link nav-cta <?= basename($_SERVER['PHP_SELF']) === 'add_review.php' ? 'active' : '' ?>">+ Write Review</a></li>

            <!-- User Authentication -->
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php
                if (!array_key_exists('user_avatar', $_SESSION) && class_exists('UserRepository')) { // Lazy-load avatar path into session once
                    $navUser = (new UserRepository())->findById((int)$_SESSION['user_id']); // Query user avatar from DB
                    $_SESSION['user_avatar'] = $navUser['avatar'] ?? ''; // Cache avatar path in session
                }
                ?>
                <li>
                    <a href="profile.php" class="nav-link nav-profile <?= in_array(basename($_SERVER['PHP_SELF']), ['profile.php', 'settings.php'], true) ? 'active' : '' ?>">
                        <?= avatarHtml($_SESSION['user_avatar'] ?? null, $_SESSION['user_name'] ?? 'User', 'nav-avatar') ?>
                        <span>Hi, <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?> · Profile</span>
                    </a>
                </li>
                <li><a href="logoutUser.php" class="nav-link">Logout</a></li>
            <?php else: ?>
                <li><a href="loginUser.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'loginUser.php' ? 'active' : '' ?>">Login</a></li> <!-- Show login link if not authenticated -->
            <?php endif; ?>
        </ul>

        <!-- Mobile menu toggle button -->
        <button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false" aria-controls="navLinks">
        </button>
    </nav>

    <!-- Flash Message -->
    <?php if (!empty($flashMessage)): ?>
        <div class="flash flash-<?= htmlspecialchars($flashType ?? 'success') ?>" role="alert"> <!-- Display flash message with appropriate styling based on type -->
            <span><?= htmlspecialchars($flashMessage) ?></span>
            <button onclick="this.parentElement.remove()" aria-label="Dismiss">✕</button>
        </div>
    <?php endif; ?>

    <main class="main-content" id="main">
        <?= $content ?? '' ?>
    </main>

    <!-- Footer -->
    <footer class="footer glass" role="contentinfo">
        <div class="footer-main">
            <!-- Col 1: Brand & Course Info -->
            <div class="footer-col footer-col-brand">
                <a href="index.php" class="nav-brand" style="display:inline-flex;margin-bottom:1rem">
                    <img src="icons/logo.png" alt="Cine Logo" class="brand-logo-img">
                    <span class="brand-text">CINE<span class="accent">></span></span>
                </a>
                <p class="footer-desc">
                    A premier cinema discovery and review platform. Explore in-depth storylines, watch official trailers, stream full films, and share your reviews.
                </p>
                <div class="footer-badge">
                    Web Programming — University of Greenwich
                </div>
            </div>

            <!-- Col 2: Navigation -->
            <div class="footer-col">
                <h4 class="footer-col-title">Navigation</h4>
                <ul class="footer-list">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="films.php">Explore Films</a></li>
                    <li><a href="reviews.php">Film Reviews</a></li>
                    <li><a href="add_review.php">Write a Review</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                </ul>
            </div>

            <!-- Col 3: Categories -->
            <div class="footer-col">
                <h4 class="footer-col-title">Top Genres</h4>
                <ul class="footer-list">
                    <li><a href="films.php?genre=1">Drama</a></li>
                    <li><a href="films.php?genre=2">Crime &amp; Detective</a></li>
                    <li><a href="films.php?genre=3">Mystery &amp; Horror</a></li>
                    <li><a href="films.php?genre=4">Romance</a></li>
                    <li><a href="films.php?genre=5">War &amp; History</a></li>
                </ul>
            </div>

            <!-- Col 4: Regions & Portals -->
            <div class="footer-col">
                <h4 class="footer-col-title">Explore Cinema</h4>
                <ul class="footer-list">
                    <li><a href="films.php?country=Vietnam">Vietnamese Films</a></li>
                    <li><a href="films.php?country=United+Kingdom">British Films</a></li>
                    <li><a href="films.php?country=United+States">Hollywood Films</a></li>
                    <li><a href="admin/index.php">Admin Portal</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p class="footer-bottom-copy">
                © <?= date('Y') ?> Cine Film Review Hub. Built for Web Programming Coursework. All film rights belong to their respective studios.
            </p>
            <a href="#top" class="footer-back-top" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">
                ↑ Back to top
            </a>
        </div>
    </footer>

    <!-- Cine AI Floating Chat Widget -->
    <div class="ai-chat-root" id="aiChatRoot">
        <button type="button" class="ai-fab" id="aiFab" aria-label="Open Cine AI film assistant" aria-expanded="false" aria-controls="aiPanel">
            <span class="ai-fab-icon" aria-hidden="true"><img src="icons/robot.png" alt="Cine AI" class="ai-fab-robot"></span>
        </button>

        <section class="ai-panel glass" id="aiPanel" aria-label="Cine AI Film Assistant" hidden>
            <header class="ai-panel-head">
                <div class="ai-panel-brand">
                    <span class="ai-badge-icon" aria-hidden="true"><img src="icons/robot.png" alt="" class="ai-badge-robot"></span>
                    <div>
                        <div class="ai-panel-title">Cine AI</div>
                        <div class="ai-panel-sub">Ask about any film's story &amp; plot</div>
                    </div>
                </div>
                <div class="ai-panel-controls">
                    <button type="button" class="ai-icon-btn" id="aiHistoryBtn" title="Chat history" aria-label="Chat history">🕘</button>
                    <button type="button" class="ai-icon-btn" id="aiClearBtn" title="New chat" aria-label="New chat">+</button>
                    <button type="button" class="ai-icon-btn" id="aiCloseBtn" title="Close" aria-label="Close chat">✕</button>
                </div>
            </header>

            <div class="ai-history-view" id="aiHistoryView" hidden>
                <div class="ai-history-head">
                    <span class="ai-history-label">Past Conversations</span>
                    <button type="button" class="ai-history-back" id="aiBackToChatBtn">← Back to chat</button>
                </div>
                <div class="ai-history-list" id="aiHistoryList"></div>
            </div>

            <div class="ai-messages" id="aiMessages" role="log" aria-live="polite"></div>

            <div class="ai-suggestions" id="aiSuggestions">
                <button type="button" class="ai-chip" data-prompt="Tell me the story summary of The King's Speech">🎬 The King's Speech</button>
                <button type="button" class="ai-chip" data-prompt="Summarize the plot of 1917">⚔️ 1917</button>
                <button type="button" class="ai-chip" data-prompt="Recommend a great film on Cine and summarize its story">✨ Recommend a film</button>
            </div>

            <form class="ai-input-bar" id="aiChatForm">
                <textarea class="ai-input" id="aiInput" rows="1" maxlength="800"
                    placeholder="Ask about any film's story…" aria-label="Ask Cine AI about a film"></textarea>
                <button type="submit" class="ai-send-btn" id="aiSendBtn" aria-label="Send message">→</button>
            </form>
        </section>
    </div>

    <!-- Navigation Script -->
    <script>
        // JavaScript for mobile menu toggle and auto-dismiss flash messages
        (function() {
            const toggle = document.getElementById('navToggle'); // Get the mobile menu toggle button
            const links = document.getElementById('navLinks');
            // Toggle mobile menu visibility and update ARIA attributes for accessibility
            toggle.addEventListener('click', function() {
                const open = links.classList.toggle('open');
                toggle.classList.toggle('open', open);
                toggle.setAttribute('aria-expanded', String(open));
            });

            // auto-dismiss flash after 4 s
            const flash = document.querySelector('.flash');
            if (flash) setTimeout(() => {
                flash.style.opacity = '0';
                setTimeout(() => flash.remove(), 400);
            }, 4000);
        })();

        // Cine AI Chat Widget
        (function() {
            const fab = document.getElementById('aiFab');
            const panel = document.getElementById('aiPanel');
            const historyBtn = document.getElementById('aiHistoryBtn');
            const backToChatBtn = document.getElementById('aiBackToChatBtn');
            const historyView = document.getElementById('aiHistoryView');
            const historyList = document.getElementById('aiHistoryList');
            const closeBtn = document.getElementById('aiCloseBtn');
            const clearBtn = document.getElementById('aiClearBtn');
            const messagesEl = document.getElementById('aiMessages');
            const suggestionsEl = document.getElementById('aiSuggestions');
            const form = document.getElementById('aiChatForm');
            const input = document.getElementById('aiInput');
            const sendBtn = document.getElementById('aiSendBtn');
            const STORAGE_KEY = 'cine_ai_history_v1';
            const CONV_KEY = 'cine_ai_conv_id_v1';
            const IS_LOGGED_IN = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;

            let history = [];
            let activeConvId = null;
            let busy = false;

            if (!IS_LOGGED_IN) {
                fab.addEventListener('click', () => {
                    window.location.href = 'loginUser.php?notice=ai_chat';
                });
                window.openCineAiChat = function() {
                    window.location.href = 'loginUser.php?notice=ai_chat';
                };
                return;
            }

            try {
                const savedConv = Number(sessionStorage.getItem(CONV_KEY) || 0);
                if (savedConv > 0) activeConvId = savedConv;
            } catch (_) {}

            function escapeHtml(str) {
                return str
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function formatInline(str) {
                return str
                    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\*([^*\n]+?)\*/g, '<em>$1</em>');
            }

            function formatAiText(raw) {
                const lines = escapeHtml(raw).replace(/\r\n?/g, '\n').split('\n');
                const out = [];
                for (const line of lines) {
                    const trimmed = line.trim();
                    if (trimmed === '') {
                        out.push('<div class="ai-gap"></div>');
                    } else if (/^---+$/.test(trimmed)) {
                        out.push('<hr class="ai-hr">');
                    } else if (/^#{1,3}\s+(.+)$/.test(trimmed)) {
                        const title = trimmed.replace(/^#{1,3}\s+/, '');
                        out.push('<div class="ai-h3">' + formatInline(title) + '</div>');
                    } else if (/^[-*•]\s+(.+)$/.test(trimmed)) {
                        const item = trimmed.replace(/^[-*•]\s+/, '');
                        out.push('<div class="ai-li"><span class="ai-dot">•</span><span>' + formatInline(item) + '</span></div>');
                    } else {
                        out.push('<div class="ai-p">' + formatInline(trimmed) + '</div>');
                    }
                }
                return out.join('');
            }

            function appendBubble(role, text, isError = false) {
                const div = document.createElement('div');
                div.className = 'ai-msg ai-msg--' + role + (isError ? ' ai-msg--error' : '');
                div.innerHTML = role === 'assistant' ? formatAiText(text) : escapeHtml(text).replace(/\n/g, '<br>');
                messagesEl.appendChild(div);
                messagesEl.scrollTop = messagesEl.scrollHeight;
                return div;
            }

            function renderMessages() {
                messagesEl.innerHTML = '';
                if (history.length === 0) {
                    appendBubble('assistant', 'Hi there! I\'m **Cine AI** 🎬\nWhich film\'s story would you like me to summarize? Ask me about any movie!');
                    suggestionsEl.style.display = historyView.hidden ? 'flex' : 'none';
                } else {
                    suggestionsEl.style.display = 'none';
                    history.forEach(m => appendBubble(m.role, m.content));
                }
            }

            function saveLocalState() {
                try {
                    if (IS_LOGGED_IN) {
                        if (activeConvId) sessionStorage.setItem(CONV_KEY, String(activeConvId));
                        else sessionStorage.removeItem(CONV_KEY);
                    } else {
                        localStorage.setItem(STORAGE_KEY, JSON.stringify(history.slice(-16)));
                    }
                } catch (_) {}
            }

            function showChatView() {
                historyView.hidden = true;
                messagesEl.hidden = false;
                form.hidden = false;
                suggestionsEl.style.display = history.length === 0 ? 'flex' : 'none';
                historyBtn.classList.remove('is-active');
            }

            function showHistoryView() {
                historyView.hidden = false;
                messagesEl.hidden = true;
                suggestionsEl.style.display = 'none';
                form.hidden = true;
                historyBtn.classList.add('is-active');
                loadConversations();
            }

            async function loadConversations() {
                historyList.innerHTML = '<div class="ai-history-empty">Loading conversations...</div>';
                try {
                    const res = await fetch('ai_chat.php?action=list');
                    const data = await res.json();

                    if (!data.logged_in) {
                        historyList.innerHTML = `
                            <div class="ai-history-empty">
                                <p>Log in to save your AI film chats and view them anytime.</p>
                                <a href="loginUser.php" class="btn btn-primary btn-sm" style="margin-top:0.75rem;">Log in</a>
                            </div>`;
                        return;
                    }

                    const convs = Array.isArray(data.conversations) ? data.conversations : [];
                    if (convs.length === 0) {
                        historyList.innerHTML = '<div class="ai-history-empty">No saved conversations yet. Start asking about a film!</div>';
                        return;
                    }

                    historyList.innerHTML = '';
                    convs.forEach(conv => {
                        const row = document.createElement('div');
                        row.className = 'ai-history-item' + (Number(conv.id) === activeConvId ? ' is-current' : '');

                        const openBtn = document.createElement('button');
                        openBtn.type = 'button';
                        openBtn.className = 'ai-history-open';
                        openBtn.innerHTML = `
                            <span class="ai-history-title">${escapeHtml(conv.title || 'Film Chat')}</span>
                            <span class="ai-history-date">${escapeHtml(conv.updated_at || '')}</span>
                        `;
                        openBtn.addEventListener('click', () => openConversation(Number(conv.id)));

                        const delBtn = document.createElement('button');
                        delBtn.type = 'button';
                        delBtn.className = 'ai-history-del';
                        delBtn.title = 'Delete conversation';
                        delBtn.setAttribute('aria-label', 'Delete conversation');
                        delBtn.textContent = '✕';
                        delBtn.addEventListener('click', async e => {
                            e.stopPropagation();
                            await deleteConversation(Number(conv.id));
                        });

                        row.appendChild(openBtn);
                        row.appendChild(delBtn);
                        historyList.appendChild(row);
                    });
                } catch (_) {
                    historyList.innerHTML = '<div class="ai-history-empty">Could not load chat history.</div>';
                }
            }

            async function openConversation(convId) {
                try {
                    const res = await fetch('ai_chat.php?action=messages&conversation_id=' + encodeURIComponent(convId));
                    const data = await res.json();
                    if (data.ok && Array.isArray(data.messages)) {
                        activeConvId = convId;
                        history = data.messages.map(m => ({ role: m.role, content: m.content }));
                        saveLocalState();
                        showChatView();
                        renderMessages();
                    }
                } catch (_) {}
            }

            async function deleteConversation(convId) {
                try {
                    const res = await fetch('ai_chat.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete', conversation_id: convId })
                    });
                    const data = await res.json();
                    if (data.ok) {
                        if (activeConvId === convId) {
                            activeConvId = null;
                            history = [];
                            saveLocalState();
                            renderMessages();
                        }
                        loadConversations();
                    }
                } catch (_) {}
            }

            function openPanel() {
                panel.hidden = false;
                fab.setAttribute('aria-expanded', 'true');
                if (historyView.hidden) input.focus();
            }

            function closePanel() {
                panel.hidden = true;
                fab.setAttribute('aria-expanded', 'false');
            }

            async function sendMessage(text, filmId = null) {
                const clean = text.trim();
                if (!clean || busy) return;

                if (!historyView.hidden) showChatView();

                busy = true;
                sendBtn.disabled = true;
                suggestionsEl.style.display = 'none';

                const priorHistory = history.slice(-8);
                history.push({ role: 'user', content: clean });
                saveLocalState();
                appendBubble('user', clean);
                input.value = '';
                input.style.height = 'auto';

                const typing = document.createElement('div');
                typing.className = 'ai-msg ai-msg--assistant ai-typing';
                typing.innerHTML = '<span></span><span></span><span></span>';
                messagesEl.appendChild(typing);
                messagesEl.scrollTop = messagesEl.scrollHeight;

                try {
                    const res = await fetch('ai_chat.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'send',
                            message: clean,
                            film_id: filmId,
                            conversation_id: activeConvId,
                            history: priorHistory
                        })
                    });
                    const data = await res.json();
                    typing.remove();

                    if (data.ok && data.reply) {
                        if (data.conversation_id) {
                            activeConvId = Number(data.conversation_id);
                        }
                        history.push({ role: 'assistant', content: data.reply });
                        saveLocalState();
                        appendBubble('assistant', data.reply);
                    } else {
                        history.pop();
                        saveLocalState();
                        appendBubble('assistant', '⚠️ ' + (data.error || 'Could not get a response from AI.'), true);
                    }
                } catch (err) {
                    typing.remove();
                    history.pop();
                    saveLocalState();
                    appendBubble('assistant', '⚠️ Connection error. Please try again.', true);
                } finally {
                    busy = false;
                    sendBtn.disabled = false;
                    input.focus();
                }
            }

            fab.addEventListener('click', () => {
                if (panel.hidden) openPanel();
                else closePanel();
            });
            closeBtn.addEventListener('click', closePanel);
            historyBtn.addEventListener('click', () => {
                if (historyView.hidden) showHistoryView();
                else showChatView();
            });
            backToChatBtn.addEventListener('click', showChatView);

            clearBtn.addEventListener('click', () => {
                activeConvId = null;
                history = [];
                saveLocalState();
                showChatView();
                renderMessages();
                input.focus();
            });

            suggestionsEl.querySelectorAll('.ai-chip').forEach(btn => {
                btn.addEventListener('click', () => sendMessage(btn.dataset.prompt || btn.textContent));
            });

            form.addEventListener('submit', e => {
                e.preventDefault();
                sendMessage(input.value);
            });

            input.addEventListener('keydown', e => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage(input.value);
                }
            });

            window.openCineAiChat = function(promptText, filmId = null) {
                if (filmId) {
                    activeConvId = null;
                    history = [];
                    saveLocalState();
                    renderMessages();
                }
                showChatView();
                openPanel();
                if (promptText) {
                    sendMessage(promptText, filmId);
                }
            };

            if (IS_LOGGED_IN && activeConvId) {
                openConversation(activeConvId);
            } else {
                renderMessages();
            }
        })();

        // Automatically attach Show/Hide eye toggle to every password input across client pages
        (function () {
            var eyeOpen = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
            var eyeOff  = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
            document.querySelectorAll('input[type="password"]').forEach(function (input) {
                if (input.dataset.pwEnhanced) return;
                input.dataset.pwEnhanced = '1';
                var wrap = document.createElement('div');
                wrap.className = 'pw-wrap';
                input.parentNode.insertBefore(wrap, input);
                wrap.appendChild(input);

                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'pw-toggle';
                btn.setAttribute('aria-label', 'Show password');
                btn.innerHTML = eyeOpen;
                btn.addEventListener('click', function () {
                    var showing = input.type === 'text';
                    input.type = showing ? 'password' : 'text';
                    btn.innerHTML = showing ? eyeOpen : eyeOff;
                    btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                });
                wrap.appendChild(btn);
            });
        })();
    </script>
</body>

</html>
