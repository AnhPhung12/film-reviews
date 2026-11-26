<?php
/**
 * edit.html.php — Edit Review Template.
 * Purpose: Renders the pre-populated review editing form with read-only film/reviewer metadata,
 *          radio-button star rating picker, and TinyMCE rich-text editor.
 * Connections:
 *   - Rendered by: edit.php; wrapped inside templates/layout.html.php.
 *   - Expects: $review (existing review row) and $errors (validation errors).
 * OOP/Patterns: View Template (MVC).
 * Data Structures & Algorithms: Indexed $starLabels lookup array mapping 1..5 ratings to descriptive labels.
 *
 * @var array $review
 * @var array $errors
 */

$starLabels = ['', 'Poor', 'Fair', 'Good', 'Great', 'Masterpiece']; // Labels for star ratings (index corresponds to rating value)
$curRating  = (int)($_POST['rating'] ?? $review['rating']); // Current rating value (from POST data or existing review)
?>

<!-- Edit Review Form -->
<section class="section page-section" aria-labelledby="editReviewHeading">

    <!-- Page header -->
    <div class="page-header">
        <h1 class="page-title" id="editReviewHeading">Edit <span class="accent">Review</span></h1>
        <!-- Page subtitle -->
        <p class="page-sub">Updating review for <strong><?= htmlspecialchars($review['film_title']) ?></strong></p>
    </div>

    <div class="form-layout">

        <!-- Main form -->
        <div class="form-main">
            <!-- Edit Review Form -->
            <form method="POST" action="edit.php?id=<?= $review['id'] ?>" class="review-form glass">
                <?= CsrfHelper::input() ?>

                <!-- Film (read-only) -->
                <div class="form-section">
                    <div class="form-step-label">
                        <span class="step-num" aria-hidden="true">01</span> <!-- Step number for visual hierarchy -->
                        <h2 class="step-heading">Film</h2>
                    </div>
                    <div class="film-preview glass" style="display:flex">
                        <span class="fp-title"><?= htmlspecialchars($review['film_title']) ?></span> <!-- Display film title -->
                    </div>
                </div>

                <!-- Reviewer (read-only) -->
                <div class="form-section">
                    <div class="form-step-label">
                        <span class="step-num" aria-hidden="true">02</span>
                        <h2 class="step-heading">Reviewer</h2>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <!-- Display reviewer's full name -->
                            <input type="text" class="form-input"
                                value="<?= htmlspecialchars($review['fullname']) ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <!-- Display reviewer's email -->
                            <input type="email" class="form-input"
                                value="<?= htmlspecialchars($review['email']) ?>" disabled> <!-- Disabled to prevent editing -->
                        </div>
                    </div>
                </div>

                <!-- Star rating — pure HTML radio buttons -->
                <div class="form-section">
                    <div class="form-step-label">
                        <span class="step-num" aria-hidden="true">03</span>
                        <h2 class="step-heading">Your Rating</h2>
                    </div>

                    <div class="radio-stars" role="group" aria-label="Select a rating">
                        <?php for ($i = 5; $i >= 1; $i--): ?> <!-- Loop from 5 to 1 to display star rating options in descending order -->
                            <label class="radio-star-label"> <!-- Label for each star rating option -->
                                <input type="radio" name="rating" value="<?= $i ?>"
                                    <?= $curRating === $i ? 'checked' : '' ?> required> <!-- Radio input for rating, checked if it matches current rating -->
                                <span class="radio-star-display">
                                    <?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?> <!-- $i means index of loop, so 5 stars minus current index gives number of empty stars -->
                                    <span class="radio-star-text"><?= $starLabels[$i] ?></span>
                                </span>
                            </label>
                        <?php endfor; ?>
                    </div>

                    <?php if (isset($errors['rating'])): ?> <!-- Display validation error for rating if it exists -->
                        <p class="field-error" role="alert"><?= htmlspecialchars($errors['rating']) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Review text -->
                <div class="form-section">
                    <div class="form-step-label">
                        <span class="step-num" aria-hidden="true">04</span>
                        <h2 class="step-heading">Your Review</h2>
                    </div>

                    <div class="form-group">
                        <label for="review_text" class="form-label">
                            Review <span class="required" aria-label="required">*</span>
                        </label>
                        <!-- Textarea for review text -->
                        <textarea id="review_text" name="review_text" rows="7"
                            placeholder="What did you think?"
                            required minlength="5"
                            class="form-textarea tinymce-editor <?= isset($errors['review_text']) ? 'has-error' : '' ?>"
                            aria-required="true"><?= htmlspecialchars($_POST['review_text'] ?? $review['reviewtext']) ?></textarea>
                        <?php if (isset($errors['review_text'])): ?>
                            <p class="field-error" role="alert"><?= htmlspecialchars($errors['review_text']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-footer">
                    <a href="film.php?id=<?= $review['filmid'] ?>" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes →</button>
                </div>

            </form>
        </div>

        <!-- SIDEBAR -->
        <aside class="form-sidebar" aria-label="Rating guide">
            <div class="rating-guide glass">
                <h3 class="tip-title">Star Guide</h3>
                <div class="rg-list">
                    <?php
                    $guideItems = [
                        [5, '★★★★★', 'Masterpiece', 'high'],
                        [4, '★★★★☆', 'Great',       'good'],
                        [3, '★★★☆☆', 'Good',        'mid'],
                        [2, '★★☆☆☆', 'Fair',        'low'],
                        [1, '★☆☆☆☆', 'Poor',        'bad'],
                    ];
                    foreach ($guideItems as [$n, $stars, $label, $cls]): // Loop through guide items to display rating descriptions
                    ?>
                        <div class="rg-row">
                            <span class="rg-stars <?= $cls ?>"><?= $stars ?></span>
                            <span class="rg-label"><?= $label ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

    </div>
</section>
<!-- TinyMCE Rich Text Editor -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
<script>
window.addEventListener('DOMContentLoaded', () => {
    tinymce.init({
        selector: 'textarea.tinymce-editor',
        menubar: false,
        plugins: 'lists link wordcount',
        toolbar: 'undo redo | bold italic underline | bullist numlist | removeformat',
        skin: 'oxide-dark',
        content_css: 'dark',
        height: 220,
        branding: false,
        promotion: false,
        placeholder: 'What did you think? Discuss the story, performances, direction… (Format with bold, italic, or lists!)',
        setup: function(editor) {
            editor.on('change keyup NodeChange', function() {
                editor.save();
                const text = editor.getContent({ format: 'text' }).trim();
                const cc = document.getElementById('charCount');
                if (cc) {
                    cc.textContent = text.length;
                    cc.style.color = text.length > 1900 ? '#ff6b6b' : '';
                }
            });
        }
    });
});
</script>
