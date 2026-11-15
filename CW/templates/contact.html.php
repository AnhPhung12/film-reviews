<?php
/**
 * contact.html.php — Contact Us Form Template.
 * Purpose: Displays the authenticated user's sender badge and a validated subject/message form.
 * Connections:
 *   - Rendered by: contact.php; wrapped inside templates/layout.html.php.
 *   - Expects: $old, $errors from controller and $_SESSION['user_name'], $_SESSION['user_email'].
 * OOP/Patterns: View Template (MVC).
 * Data Structures & Algorithms: Associative arrays ($old, $errors) for sticky form repopulation and field errors.
 *
 * @var array $old
 * @var array $errors
 */

// Preserve old input values and validation errors (used after form submission fails)
$old    = $old    ?? []; // Associative array of old input values
$errors = $errors ?? []; // Associative array of validation errors

// Ensure session user data is available for display
$currentUserName  = $_SESSION['user_name'] ?? 'Guest'; // Fallback to 'Guest' if name is not set
$currentUserEmail = $_SESSION['user_email'] ?? ''; // Fallback to empty string if email is not set
?>

<!-- Contact Form -->
<section class="section page-section" aria-labelledby="contactHeading">

    <!-- Page header -->
    <div class="page-header">
        <h1 class="page-title" id="contactHeading">Contact <span class="accent">Us</span></h1>
        <p class="page-sub">Have a question or feedback? Send us a message and we'll get back to you.</p>
    </div>

    <div class="form-layout">

        <!-- Main form container -->
        <div class="form-main">
            <!-- Contact form -->
            <form method="POST" action="contact.php" class="review-form glass" novalidate> <!-- Disable default browser validation to use custom validation and error display -->

                <!-- Show current user information -->
                <div class="form-section">
                    <div class="form-step-label">
                        <span class="step-num" aria-hidden="true">01</span>
                        <h2 class="step-heading">Account Details</h2>
                    </div>

                    <!-- Display logged-in user information -->
                    <div class="user-info-badge glass" style="padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.03);">
                        <p style="margin: 0; font-size: 0.9rem; color: var(--text-dim);">Sending message as:</p> <!-- Label -->
                        <!-- User name -->
                        <p style="margin: 0.5rem 0 0; font-weight: 600; font-size: 1.1rem; color: var(--accent);">
                            <?= htmlspecialchars($currentUserName) ?>
                        </p>
                        <!-- User email -->
                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-dim); opacity: 0.8;">
                            <?= htmlspecialchars($currentUserEmail) ?>
                        </p>
                        <!-- Account switcher -->
                        <p style="margin-top: 1rem; font-size: 0.8rem; font-style: italic; color: var(--text-dim);">
                            Not you? <a href="logoutUser.php" style="color: var(--gold); text-decoration: underline;">Switch account</a>
                        </p>
                    </div>
                </div>

                <!-- Subject input -->
                <div class="form-section">
                    <div class="form-step-label">
                        <span class="step-num" aria-hidden="true">02</span>
                        <h2 class="step-heading">Subject</h2>
                    </div>
                    <div class="form-group">
                        <label for="subject" class="form-label">
                            Subject <span class="required" aria-label="required">*</span>
                        </label>
                        <!-- Subject input field -->
                        <input type="text" id="subject" name="subject"
                            value="<?= htmlspecialchars($old['subject'] ?? '') ?>"
                            placeholder="e.g. Question about a review" required
                            class="form-input <?= isset($errors['subject']) ? 'has-error' : '' ?>"
                            aria-required="true" aria-describedby="subject-err">
                        <!-- Display validation error for subject if it exists -->
                        <?php if (isset($errors['subject'])): ?>
                            <p class="field-error" id="subject-err" role="alert"><?= htmlspecialchars($errors['subject']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Message input -->
                <div class="form-section">
                    <div class="form-step-label">
                        <span class="step-num" aria-hidden="true">03</span>
                        <h2 class="step-heading">Message</h2>
                    </div>
                    <div class="form-group">
                        <label for="content" class="form-label">
                            Message <span class="required" aria-label="required">*</span>
                        </label>
                        <!-- Message textarea -->
                        <textarea id="content" name="content" rows="7"
                            placeholder="Write your message here…"
                            required minlength="10"
                            class="form-textarea <?= isset($errors['content']) ? 'has-error' : '' ?>"
                            aria-required="true" aria-describedby="content-err"><?= htmlspecialchars($old['content'] ?? '') ?></textarea>
                        <!-- Display validation error for content if it exists -->
                        <?php if (isset($errors['content'])): ?>
                            <p class="field-error" id="content-err" role="alert"><?= htmlspecialchars($errors['content']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Form actions footer -->
                <div class="form-footer">
                    <a href="index.php" class="btn btn-ghost">Cancel</a> <!-- Cancel button -->
                    <button type="submit" class="btn btn-primary">
                        Send Message <span aria-hidden="true">→</span>
                    </button> <!-- Submit button -->
                </div>

            </form>
        </div>

        <!-- Sidebar with contact tips -->
        <aside class="form-sidebar" aria-label="Contact info">
            <!-- Contact tips -->
            <div class="tip-card glass">
                <h3 class="tip-title">Get in Touch</h3>
                <ul class="tip-list">
                    <li>📧 We typically reply within 24 hours</li>
                    <li>🎬 Questions about films or reviews? We're happy to help</li>
                    <li>🐛 Found a bug? Let us know!</li>
                    <li>💡 Have a suggestion? We'd love to hear it</li>
                </ul>
            </div>
            <!-- Additional support options -->
            <div class="tip-card glass">
                <h3 class="tip-title">Account Support</h3>
                <ul class="tip-list">
                    <li>📝 Want to write a review?
                        <a href="add_review.php" style="color:var(--gold)">Click here</a>
                    </li>
                    <li>🎞️ Browse all films on the
                        <a href="reviews.php" style="color:var(--gold)">Reviews page</a>
                    </li>
                </ul>
            </div>
        </aside>

    </div>

</section>