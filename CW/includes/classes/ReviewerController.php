<?php
require_once __DIR__ . '/../DataBaseFunctions.php'; // Load CinemaRepository
require_once __DIR__ . '/../UserRepository.php'; // Load UserRepository

/**
 * ReviewerController.php — OOP Controller for Reviewer (Author) Lifecycle.
 * Purpose: Encapsulates sign-up, login, profile lookup, and admin user moderation (edit/suspend/delete).
 * Connections:
 *   - Called by: loginUser.php, register.php, admin/reviewers.php.
 *   - Delegates DB operations to: UserRepository (auth/hashing) & CinemaRepository (stats/suspension).
 * OOP/Patterns: Controller orchestrating multiple repositories via Dependency Injection.
 * Data Structures & Algorithms:
 *   - Passwords hashed via password_hash() and verified with password_verify().
 *   - Email validation via filter_var() and duplicate checks before persistence.
 */
class ReviewerController
{
    private UserRepository $users; // Repository for account CRUD and authentication
    private CinemaRepository $cinema; // Repository for reviewer stats and suspension

    /** Injects UserRepository and CinemaRepository or creates default instances. */
    public function __construct(?UserRepository $users = null, ?CinemaRepository $cinema = null)
    {
        $this->users = $users ?? new UserRepository(); // Use injected UserRepository or create default
        $this->cinema = $cinema ?? new CinemaRepository(); // Use injected CinemaRepository or create default
    }

    /** Validates registration input and creates a new reviewer account. */
    public function register(string $fullname, string $email, string $password): bool|string
    {
        $fullname = trim($fullname); // Trim whitespace from full name
        $email = trim($email); // Trim whitespace from email
        if ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) { // Validate required fields and password length
            return 'Valid name, email, and password (minimum 6 characters) are required.';
        }
        return $this->users->create($fullname, $email, $password); // Persist new reviewer account
    }

    /** Authenticates a reviewer and returns structured status ('ok', 'suspended', 'invalid'). */
    public function loginDetailed(string $email, string $password): array
    {
        return $this->users->authenticateDetailed(trim($email), $password);
    }

    /** Authenticates a reviewer by email and password. */
    public function login(string $email, string $password): ?array
    {
        return $this->users->authenticate(trim($email), $password); // Trim email and verify credentials
    }

    /** Fetches a reviewer profile by ID. */
    public function findById(int $id): ?array
    {
        return $this->users->findById($id); // Delegate profile lookup
    }

    /** Returns reviewers filtered by search query and sorted by registration or rating. */
    public function listFiltered(string $search = '', string $sort = 'registered_desc'): array
    {
        return $this->cinema->getReviewersFiltered($search, $sort); // Delegate filtered reviewer list
    }

    /** Validates and applies admin updates to a reviewer's name, email, and optional password. */
    public function adminUpdate(int $id, string $fullname, string $email, ?string $newPassword = null): bool|string
    {
        $fullname = trim($fullname); // Trim updated full name
        $email = trim($email); // Trim updated email address
        if ($id <= 0 || $fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { // Validate ID, name, and email format
            return 'Valid reviewer ID, full name, and email are required.';
        }
        if ($newPassword !== null && $newPassword !== '' && strlen($newPassword) < 6) { // Enforce minimum password length when changing password
            return 'New password must be at least 6 characters.';
        }
        return $this->users->update($id, $fullname, $email, $newPassword ?: null); // Apply validated updates
    }

    /** Toggles a reviewer's suspended status flag. */
    public function adminToggleSuspend(int $id, bool $suspended): bool
    {
        if ($id <= 0) { // Reject invalid user IDs
            return false;
        }
        return $this->cinema->setReviewerSuspended($id, $suspended); // Persist suspension flag
    }

    /** Permanently deletes a reviewer account and their reviews. */
    public function adminDelete(int $id): bool
    {
        if ($id <= 0) { // Reject invalid user IDs
            return false;
        }
        return $this->users->delete($id); // Atomically delete reviewer and reviews
    }
}
