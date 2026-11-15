<?php
require_once __DIR__ . '/../DataBaseFunctions.php'; // Load CinemaRepository and PresentationHelper

/**
 * ReviewController.php — OOP Controller for Film Reviews & Ownership Access Control.
 * Purpose: Manages review listing, pagination counts, creation, and ownership-verified updates/deletes.
 * Connections:
 *   - Called by: reviews.php, add_review.php, edit.php, deletereview.php, admin/reviews.php.
 *   - Delegates persistence to: CinemaRepository; HTML sanitization to PresentationHelper.
 * OOP/Patterns: Controller with ownership authorization guard and HTML allowlist sanitization.
 * Data Structures & Algorithms:
 *   - Enforces ownership (reviewerid === logged-in user_id) before edit/delete.
 *   - Sanitizes TinyMCE rich-text HTML via tag-allowlist (strip_tags) to prevent stored XSS.
 */
class ReviewController
{
    private CinemaRepository $cinema; // Repository handling review SQL queries

    /** Injects CinemaRepository or instantiates a default repository. */
    public function __construct(?CinemaRepository $cinema = null)
    {
        $this->cinema = $cinema ?? new CinemaRepository(); // Use injected repository or create default
    }

    /** Counts reviews matching optional film title search and genre filter. */
    public function countFiltered(string $search = '', string|int $genreId = ''): int
    {
        return $this->cinema->countReviewsFiltered($search, $genreId); // Delegate filtered review count
    }

    /** Returns filtered, sorted, and paginated reviews. */
    public function listFiltered(string $search = '', string|int $genreId = '', array|string $sort = [], int $limit = 0, int $offset = 0): array
    {
        return $this->cinema->getAllReviewsFiltered($search, $genreId, $sort, $limit, $offset); // Delegate filtered/sorted review list
    }

    /** Sanitizes review HTML and persists a new review for a film. */
    public function create(int $filmId, int $reviewerId, string $reviewText, int $rating): bool
    {
        $cleanHtml = PresentationHelper::safeHtmlContent($reviewText); // Strip unsafe tags to prevent stored XSS
        return $this->cinema->addReview($filmId, $reviewerId, $cleanHtml, $rating); // Persist sanitized review
    }

    /** Fetches a review only if the given user ID is its author. */
    public function getOwnedReview(int $reviewId, int $userId): ?array
    {
        $review = $this->cinema->getReviewForEditing($reviewId); // Fetch review record
        if (!$review || (int)$review['reviewerid'] !== $userId) { // Enforce author ownership check
            return null;
        }
        return $review; // Return owned review
    }

    /** Fetches a review for editing without an ownership filter (e.g., admin context). */
    public function getForEditing(int $reviewId): ?array
    {
        return $this->cinema->getReviewForEditing($reviewId); // Fetch review without ownership restriction
    }

    /** Verifies authorship, sanitizes updated HTML, and saves changes to the review. */
    public function updateOwned(int $reviewId, int $userId, string $reviewText, int $rating): bool
    {
        if (!$this->getOwnedReview($reviewId, $userId)) { // Abort if caller does not own the review
            return false;
        }
        $cleanHtml = PresentationHelper::safeHtmlContent($reviewText); // Sanitize updated rich-text HTML
        return $this->cinema->updateReview($reviewId, $cleanHtml, $rating); // Save updated review
    }

    /** Verifies authorship before deleting a user's review. */
    public function deleteOwned(int $reviewId, int $userId): bool
    {
        if (!$this->getOwnedReview($reviewId, $userId)) { // Abort if caller does not own the review
            return false;
        }
        return $this->cinema->deleteReview($reviewId); // Delete verified review
    }

    /** Allows an administrator to delete any review by ID. */
    public function adminDelete(int $reviewId): bool
    {
        if ($reviewId <= 0) { // Reject invalid review IDs
            return false;
        }
        return $this->cinema->deleteReview($reviewId); // Delete review as admin
    }
}
