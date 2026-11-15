<?php
require_once __DIR__ . '/DatabaseConnection.php'; // Load Singleton PDO connector

/**
 * UserRepository.php — Repository Pattern for the `reviewer` Table.
 * Purpose: Centralizes all SQL operations for user accounts, authentication, profiles, and password resets.
 * Connections:
 *   - Used by: ReviewerController, forgot_password.php, reset_password.php, profile.php, settings.php, templates/layout.html.php.
 *   - Connects to DB via: DatabaseConnection::getInstance() (Singleton PDO).
 * OOP/Patterns: Repository Pattern with Dependency Injection support and idempotent schema migration.
 * Data Structures & Algorithms:
 *   - Password hashing: password_hash($pw, PASSWORD_DEFAULT) + password_verify().
 *   - Password reset: CSPRNG token (random_bytes(32)) -> stored as SHA-256 hash with 30-min TTL.
 *   - Account suspension: authenticate() checks `is_suspended == 0` before allowing login.
 *   - Atomic deletion: delete() wraps review + reviewer removal in a PDO transaction (beginTransaction/rollBack).
 */
class UserRepository
{
    private PDO $db; // Shared PDO database handle
    private static bool $schemaChecked = false; // Request-scoped guard so ALTER TABLE checks run once

    /** Accepts an optional PDO instance or falls back to the shared Singleton connection. */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DatabaseConnection::getInstance()->getConnection(); // Use injected PDO or Singleton
        self::ensureSchemaColumns($this->db); // Ensure required columns exist on legacy schemas
    }

    /** Backward-compatible alias that ensures avatar and password-reset columns exist on `reviewer`. */
    public static function ensureAvatarColumn(PDO $db): void
    {
        self::ensureSchemaColumns($db); // Delegate to unified schema migration helper
    }

    /** Adds missing profile/reset/moderation columns to `reviewer` for older database imports. */
    public static function ensureSchemaColumns(PDO $db): void
    {
        if (self::$schemaChecked) { // Skip if already verified in this request
            return;
        }
        $columns = array_column($db->query("SHOW COLUMNS FROM reviewer")->fetchAll(), 'Field'); // Inspect existing table columns
        if (!in_array('avatar', $columns, true)) { // Add avatar path column if missing
            $db->exec("ALTER TABLE reviewer ADD COLUMN avatar VARCHAR(255) DEFAULT NULL AFTER password");
        }
        if (!in_array('reset_token_hash', $columns, true)) { // Add SHA-256 reset token column if missing
            $db->exec("ALTER TABLE reviewer ADD COLUMN reset_token_hash VARCHAR(255) DEFAULT NULL AFTER avatar");
        }
        if (!in_array('reset_expires_at', $columns, true)) { // Add reset token expiration timestamp if missing
            $db->exec("ALTER TABLE reviewer ADD COLUMN reset_expires_at DATETIME DEFAULT NULL AFTER reset_token_hash");
        }
        // Older accounts keep NULL because their original registration time is unknown.
        if (!in_array('registered_at', $columns, true)) { // Add registration timestamp column if missing
            $db->exec("ALTER TABLE reviewer ADD COLUMN registered_at DATETIME DEFAULT NULL");
        }
        if (!in_array('is_suspended', $columns, true)) { // Add moderation suspension flag if missing
            $db->exec("ALTER TABLE reviewer ADD COLUMN is_suspended TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!in_array('avatar_data', $columns, true)) { // Add binary avatar BLOB column for persistent cloud storage
            $db->exec("ALTER TABLE reviewer ADD COLUMN avatar_data LONGBLOB DEFAULT NULL AFTER avatar");
        }
        if (!in_array('avatar_mime', $columns, true)) { // Add MIME type column for binary avatar streaming
            $db->exec("ALTER TABLE reviewer ADD COLUMN avatar_mime VARCHAR(64) DEFAULT NULL AFTER avatar_data");
        }
        $db->exec("CREATE TABLE IF NOT EXISTS avatar_presets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(64) NOT NULL UNIQUE,
            label VARCHAR(100) NOT NULL DEFAULT '',
            data LONGBLOB NOT NULL,
            mime VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); // Ensure preset avatar BLOB storage table exists
        self::$schemaChecked = true; // Mark schema check complete for this request
    }

    /** Fetches public profile fields for a reviewer by primary key ID. */
    public function findById(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT id, fullname, email, avatar, is_suspended, registered_at FROM reviewer WHERE id = ?'); // Prepare ID lookup excluding password hash
        $statement->execute([$id]); // Bind reviewer ID
        return $statement->fetch() ?: null; // Return user row or null
    }

    /** Looks up a reviewer record by email address. */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->db->prepare('SELECT id, fullname, email FROM reviewer WHERE email = ?'); // Prepare email lookup
        $statement->execute([$email]); // Bind email address
        return $statement->fetch() ?: null; // Return user row or null
    }

    /** Registers a new reviewer with a hashed password and default preset avatar. */
    public function create(string $fullname, string $email, string $password): bool|string
    {
        $check = $this->db->prepare('SELECT id FROM reviewer WHERE email = ?'); // Check for duplicate email
        $check->execute([$email]); // Execute uniqueness check
        if ($check->fetch()) { // Reject registration if email already exists
            return 'Email is already registered.';
        }
        $insert = $this->db->prepare('INSERT INTO reviewer (fullname, email, password, avatar, reset_token_hash, reset_expires_at, registered_at) VALUES (?, ?, ?, ?, ?, ?, NOW())'); // Prepare account insert
        return $insert->execute([$fullname, $email, password_hash($password, PASSWORD_DEFAULT), 'image.php?type=preset&name=male-1', '', '2000-01-01 00:00:00']) // Hash password with bcrypt/argon2 and assign DB-backed default preset avatar
            ? true : 'Database error occurred.';
    }

    /** Returns built-in preset avatar options backed by the `avatar_presets` table. */
    public function getPresetAvatars(): array
    {
        $rows = $this->db->query('SELECT name, label FROM avatar_presets ORDER BY name ASC')->fetchAll(); // Fetch preset slugs and labels from database
        if (!empty($rows)) { // Use database presets when populated
            $presets = []; // Initialize preset list
            foreach ($rows as $row) { // Format each row for profile picker
                $name = (string)($row['name'] ?? ''); // Extract preset slug
                if ($name === '') { // Skip malformed rows
                    continue;
                }
                $label = trim((string)($row['label'] ?? '')); // Extract display label
                $presets[] = [
                    'path'  => 'image.php?type=preset&name=' . rawurlencode($name),
                    'label' => $label !== '' ? $label : ucwords(str_replace('-', ' ', $name)),
                ];
            }
            return $presets;
        }
        $defaults = ['female-1', 'female-2', 'female-3', 'female-4', 'female-5', 'male-1', 'male-2', 'male-3', 'male-4', 'male-5']; // Fallback list before migration
        return array_map(static function (string $name): array { // Map each slug to image.php URL and readable label
            return [
                'path'  => 'image.php?type=preset&name=' . $name,
                'label' => ucwords(str_replace('-', ' ', $name)),
            ];
        }, $defaults);
    }

    /** Verifies email + password and rejects suspended accounts. */
    public function authenticate(string $email, string $password): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM reviewer WHERE email = ?'); // Fetch account by email
        $statement->execute([$email]); // Execute lookup
        $user = $statement->fetch(); // Retrieve user row
        return $user && empty($user['is_suspended']) && !empty($user['password']) && password_verify($password, $user['password']) ? $user : null; // Verify active status and password hash
    }

    /** Checks whether a plain-text password matches the stored hash for a given user ID. */
    public function verifyPassword(int $id, string $password): bool
    {
        $statement = $this->db->prepare('SELECT password FROM reviewer WHERE id = ?'); // Fetch stored password hash
        $statement->execute([$id]); // Bind user ID
        $hash = $statement->fetchColumn(); // Read hash string
        return is_string($hash) && $hash !== '' && password_verify($password, $hash); // Compare plain password against hash
    }

    /** Updates a user's password hash. */
    public function updatePassword(int $id, string $newPassword): bool
    {
        $statement = $this->db->prepare('UPDATE reviewer SET password = ? WHERE id = ?'); // Prepare password update
        $statement->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]); // Store new salted password hash
        return $statement->rowCount() >= 0; // Return true when query succeeds
    }

    /** Generates a 64-char CSPRNG reset token, stores its SHA-256 hash with TTL, and returns the raw token. */
    public function createPasswordResetToken(int $userId, int $ttlMinutes = 30): string
    {
        $rawToken = bin2hex(random_bytes(32)); // Generate 256-bit cryptographically secure token
        $tokenHash = hash('sha256', $rawToken); // Hash token before storing in DB
        $statement = $this->db->prepare(
            'UPDATE reviewer SET reset_token_hash = ?, reset_expires_at = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?'
        ); // Prepare token hash and expiry update
        $statement->execute([$tokenHash, $ttlMinutes, $userId]); // Persist hashed token with TTL
        return $rawToken; // Return raw token to be emailed to user
    }

    /** Invalidates any active password reset token for the given user. */
    public function clearPasswordResetToken(int $userId): void
    {
        $this->db->prepare("UPDATE reviewer SET reset_token_hash = '', reset_expires_at = '2000-01-01 00:00:00' WHERE id = ?") // Clear token hash and backdate expiry
            ->execute([$userId]);
    }

    /** Validates a 64-hex-char reset token against its SHA-256 hash and expiration timestamp. */
    public function findValidResetUser(string $rawToken): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $rawToken)) { // Reject malformed token format
            return null;
        }
        $tokenHash = hash('sha256', $rawToken); // Compute SHA-256 digest to match DB column
        $statement = $this->db->prepare(
            'SELECT id, fullname, email FROM reviewer WHERE reset_token_hash = ? AND reset_expires_at > NOW()'
        ); // Query user with matching unexpired token
        $statement->execute([$tokenHash]); // Execute token lookup
        return $statement->fetch() ?: null; // Return user record or null if invalid/expired
    }

    /** Atomically updates the user's password and consumes the reset token if still valid. */
    public function resetPasswordWithToken(string $rawToken, string $newPassword): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $rawToken)) { // Reject malformed token format
            return false;
        }
        $tokenHash = hash('sha256', $rawToken); // Hash raw token for comparison
        $statement = $this->db->prepare(
            "UPDATE reviewer
             SET password = ?, reset_token_hash = '', reset_expires_at = '2000-01-01 00:00:00'
             WHERE reset_token_hash = ? AND reset_expires_at > NOW()"
        ); // Update password and invalidate token in one atomic statement
        $statement->execute([password_hash($newPassword, PASSWORD_DEFAULT), $tokenHash]); // Execute atomic reset
        return $statement->rowCount() === 1; // Return true only if a valid unexpired token matched
    }

    /** Dynamically builds an UPDATE query for name, email, optional password, and optional avatar. */
    public function update(
        int $id,
        string $fullname,
        string $email,
        ?string $password = null,
        ?string $avatar = null,
        bool $clearAvatar = false
    ): bool|string {
        // Ensure email uniqueness across other accounts
        $check = $this->db->prepare('SELECT id FROM reviewer WHERE email = ? AND id <> ?'); // Check for email collision with other users
        $check->execute([$email, $id]); // Execute uniqueness check
        if ($check->fetch()) { // Abort if another account uses this email
            return 'Email is already used by another account.';
        }

        $fields = ['fullname = ?', 'email = ?']; // Always update name and email
        $params = [$fullname, $email]; // Corresponding bound values

        if ($password !== null && $password !== '') { // Optionally update password if provided
            $fields[] = 'password = ?';
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }

        if ($clearAvatar) { // Reset avatar path and BLOB data if requested
            $fields[] = 'avatar = NULL';
            $fields[] = 'avatar_data = NULL';
            $fields[] = 'avatar_mime = NULL';
        } elseif ($avatar !== null) { // Otherwise update avatar path (and clear BLOB when switching to preset)
            $fields[] = 'avatar = ?';
            $params[] = $avatar;
            if (!str_starts_with($avatar, 'image.php?')) {
                $fields[] = 'avatar_data = NULL';
                $fields[] = 'avatar_mime = NULL';
            }
        }

        $params[] = $id; // Append target user ID for WHERE clause
        $sql = 'UPDATE reviewer SET ' . implode(', ', $fields) . ' WHERE id = ?'; // Assemble dynamic UPDATE statement
        $statement = $this->db->prepare($sql); // Prepare dynamic query
        $statement->execute($params); // Execute update
        return $statement->rowCount() >= 0; // Return true on success
    }

    /** Stores an uploaded avatar image directly in MySQL as a BLOB alongside its stream URL. */
    public function updateAvatarBlob(int $id, string $bytes, string $mime, string $avatarUrl): bool
    {
        $statement = $this->db->prepare('UPDATE reviewer SET avatar = ?, avatar_data = ?, avatar_mime = ? WHERE id = ?'); // Prepare BLOB update
        return $statement->execute([$avatarUrl, $bytes, $mime, $id]); // Persist path, raw bytes, and MIME type
    }

    /** Deletes a reviewer and their reviews inside a transaction, then cleans up any custom avatar file. */
    public function delete(int $id): bool
    {
        $this->db->beginTransaction(); // Start transaction to keep review/user deletion atomic
        try {
            $avStmt = $this->db->prepare('SELECT avatar FROM reviewer WHERE id = ?'); // Fetch avatar path before deleting row
            $avStmt->execute([$id]); // Execute lookup
            $avatarPath = $avStmt->fetchColumn(); // Store avatar path for post-commit cleanup

            // Reviews have a restrictive foreign key, so remove the user's own reviews first.
            $this->db->prepare('DELETE FROM review WHERE reviewerid = ?')->execute([$id]); // Delete user's reviews
            $this->db->prepare('DELETE FROM reviewer WHERE id = ?')->execute([$id]); // Delete reviewer account
            $this->db->commit(); // Commit database changes

            // Remove custom uploaded avatar from disk while preserving shared preset avatars
            if (is_string($avatarPath) && str_starts_with($avatarPath, 'avatars/') && !str_starts_with($avatarPath, 'avatars/presets/')) {
                $fullPath = dirname(__DIR__) . '/' . $avatarPath; // Resolve absolute file path
                if (is_file($fullPath)) { // Delete file if it exists on disk
                    @unlink($fullPath);
                }
            }

            return true; // Report successful deletion
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) { // Roll back on any failure
                $this->db->rollBack();
            }
            throw $error; // Re-throw exception to caller
        }
    }
}
