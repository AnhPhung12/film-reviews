<?php
require_once __DIR__ . '/DatabaseConnection.php';
require_once __DIR__ . '/UserRepository.php';

/**
 * DataBaseFunctions.php — Presentation Helpers, CinemaRepository & Procedural Facades.
 * Purpose: Provides HTML rendering helpers (stars, avatars, posters, pagination, XSS sanitization)
 *          and centralizes database queries for films, reviews, genres, contact messages, and stats.
 * Connections:
 *   - Included by: All public and admin controllers/pages and OOP controller classes.
 *   - Connects to DB via: DatabaseConnection::getInstance() (Singleton PDO) and UserRepository.
 * OOP/Patterns:
 *   - PresentationHelper (Static Utility class) + CinemaRepository (Repository Pattern).
 *   - Procedural wrapper functions at bottom preserve backward compatibility with legacy page scripts.
 * Data Structures & Algorithms:
 *   - Polynomial rolling hash (base 31) in getPosterData() maps film titles to deterministic gradient palettes.
 *   - Dynamic SQL query builders with parameterized WHERE clauses and allowlisted ORDER BY mappings.
 *   - Transactional film/reviewer deletion (beginTransaction/commit/rollBack) to satisfy FK constraints.
 */

/** Returns the shared Singleton PDO connection. */
function getDB(): PDO
{
    return DatabaseConnection::getInstance()->getConnection(); // Return shared PDO handle
}

/** Pure helpers for rendering presentation details safely in templates. */
class PresentationHelper
{
    /** Renders 5-star rating HTML with filled and empty star spans. */
    public static function starHtml(int $rating, string $size = 'md'): string
    {
        $filled = min(max($rating, 0), 5); // Clamp value between 0 and 5
        $empty  = 5 - $filled; // Compute remaining empty stars
        $cls    = 'stars stars--' . $size; // Build size modifier CSS class
        return '<span class="' . $cls . '">' // Assemble star container HTML
            . str_repeat('<span class="star filled">★</span>', $filled)
            . str_repeat('<span class="star empty">☆</span>', $empty)
            . '</span>';
    }

    /** Generates a deterministic two-color poster gradient and up to 2-letter initials from a film title. */
    public static function getPosterData(string $title, string $genreName = ''): array
    {
        // Predefined color palettes
        $palettes = [
            ['#1a1a2e', '#e94560'],
            ['#0f3460', '#533483'],
            ['#16213e', '#0f3460'],
            ['#2d1b69', '#11998e'],
            ['#373b44', '#4286f4'],
            ['#1f1c2c', '#928dab'],
            ['#3a1c71', '#d76d77'],
            ['#0f0c29', '#302b63'],
            ['#1d2b64', '#f8cdda'],
            ['#232526', '#c94b4b'],
            ['#1a1a1a', '#c79f3a'],
            ['#0d0d0d', '#2c7873'],
        ]; // Twelve curated gradient pairs for fallback film posters
        // Generate polynomial rolling hash from title to pick a consistent palette
        $hash = 0; // Initialize rolling hash accumulator
        for ($i = 0; $i < strlen($title); $i++) { // Iterate over each byte of title
            $hash = ($hash * 31 + ord($title[$i])) % count($palettes); // Base-31 hash modulo palette count
        }
        // Extract initials from title
        $words = preg_split('/\s+/', trim($title)); // Split title into whitespace-separated words
        $initials = strtoupper(mb_substr($words[0], 0, 1)); // Take uppercase first letter of first word
        // Add second letter if meaningful word exists (not common stop words)
        if (isset($words[1]) && !in_array(strtolower($words[1]), ['the', 'a', 'an', 'of'])) {
            $initials .= strtoupper(mb_substr($words[1], 0, 1)); // Append second word initial
        }
        return ['color1' => $palettes[$hash][0], 'color2' => $palettes[$hash][1], 'initials' => $initials]; // Return gradient colors and initials
    }

    /** Renders a user avatar <img> (with relative path auto-detection) or an initial-letter fallback badge. */
    public static function avatarHtml(?string $avatar, string $name, string $class = 'reviewer-avatar', ?string $prefix = null): string
    {
        $initial = strtoupper(mb_substr(trim($name) ?: 'U', 0, 1)); // Fallback initial letter (defaults to 'U')
        $avatar = trim((string)$avatar); // Normalize avatar path string
        $cwDir = dirname(__DIR__); // Resolve CW root directory on disk
        $isDbBlob = $avatar !== '' && str_starts_with($avatar, 'image.php?'); // True for avatars streamed from MySQL BLOB
        $isDiskFile = $avatar !== '' && str_starts_with($avatar, 'avatars/') && is_file($cwDir . '/' . $avatar); // True for preset/legacy files on disk
        if ($isDbBlob || $isDiskFile) { // Render <img> when avatar is stored in DB BLOB or exists on disk
            if ($prefix === null) { // Auto-detect relative URL prefix based on script depth
                $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''); // Normalize Windows backslashes
                if (str_contains($script, '/admin/Login/')) { // Two levels deep in admin/Login/
                    $prefix = '../../';
                } elseif (str_contains($script, '/admin/')) { // One level deep in admin/
                    $prefix = '../';
                } else { // Public root scripts
                    $prefix = '';
                }
            }
            $src = htmlspecialchars($prefix . $avatar, ENT_QUOTES, 'UTF-8'); // Escape image src attribute
            $alt = htmlspecialchars($name . ' avatar', ENT_QUOTES, 'UTF-8'); // Escape image alt text
            return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . ' has-avatar">' // Render avatar image wrapper with fallback onerror handler
                . '<img src="' . $src . '" alt="' . $alt . '" class="avatar-img" onerror="this.style.display=\'none\';this.parentElement.classList.remove(\'has-avatar\');">'
                . '</span>';
        }
        return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true">' // Render initial-letter fallback badge
            . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8')
            . '</span>';
    }

    /** Resolves a film poster URL (MySQL BLOB stream or legacy disk file) relative to the caller directory. */
    public static function posterUrl(?string $poster, string $prefix = ''): string
    {
        $poster = trim((string)($poster ?? '')); // Normalize null/whitespace poster value
        if ($poster === '') { // Return empty string when no poster is configured
            return '';
        }
        $blobPos = strpos($poster, 'image.php?'); // Locate DB BLOB endpoint query string if present
        if ($blobPos !== false) { // Normalize any hardcoded folder prefix (/COMP1841/CW/ or /film/CW/) to relative URL
            return $prefix . substr($poster, $blobPos);
        }
        return $prefix . 'posters/' . basename($poster); // Fallback for legacy disk poster files
    }

    /** Builds accessible pagination HTML preserving existing query parameters. */
    public static function renderPagination(int $currentPage, int $totalPages, string $baseUrl, array $params = []): string
    {
        if ($totalPages <= 1) { // Omit pagination bar when everything fits on one page
            return '';
        }
        $html = '<nav class="pagination-nav" aria-label="Page navigation">'; // Open accessible pagination landmark
        $html .= '<ul class="pagination-list">'; // Open page link list

        $prevDisabled = ($currentPage <= 1); // Disable Prev on first page
        $prevPage = max(1, $currentPage - 1); // Clamp previous page number
        $prevUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $prevPage])); // Build Prev URL keeping active filters
        $html .= '<li class="pagination-item ' . ($prevDisabled ? 'is-disabled' : '') . '">'; // Open Prev list item
        if ($prevDisabled) { // Render non-clickable span when disabled
            $html .= '<span class="pagination-link" aria-disabled="true">« Prev</span>';
        } else { // Render clickable Prev link
            $html .= '<a href="' . htmlspecialchars($prevUrl, ENT_QUOTES, 'UTF-8') . '" class="pagination-link">« Prev</a>';
        }
        $html .= '</li>'; // Close Prev list item

        for ($i = 1; $i <= $totalPages; $i++) { // Render numbered page buttons
            $isActive = ($i === $currentPage); // Highlight current active page
            $pageUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $i])); // Build URL for page $i
            $html .= '<li class="pagination-item ' . ($isActive ? 'is-active' : '') . '">'; // Open page item
            if ($isActive) { // Render current page as aria-current span
                $html .= '<span class="pagination-link" aria-current="page">' . $i . '</span>';
            } else { // Render clickable link for other pages
                $html .= '<a href="' . htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') . '" class="pagination-link">' . $i . '</a>';
            }
            $html .= '</li>'; // Close page item
        }

        $nextDisabled = ($currentPage >= $totalPages); // Disable Next on last page
        $nextPage = min($totalPages, $currentPage + 1); // Clamp next page number
        $nextUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $nextPage])); // Build Next URL keeping active filters
        $html .= '<li class="pagination-item ' . ($nextDisabled ? 'is-disabled' : '') . '">'; // Open Next list item
        if ($nextDisabled) { // Render non-clickable span when disabled
            $html .= '<span class="pagination-link" aria-disabled="true">Next »</span>';
        } else { // Render clickable Next link
            $html .= '<a href="' . htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8') . '" class="pagination-link">Next »</a>';
        }
        $html .= '</li>'; // Close Next list item

        $html .= '</ul>'; // Close page link list
        $html .= '</nav>'; // Close pagination landmark
        return $html; // Return assembled pagination HTML
    }

    /** Strips disallowed HTML tags from rich-text review content to prevent stored XSS. */
    public static function safeHtmlContent(string $content): string
    {
        $trimmed = trim($content); // Remove leading/trailing whitespace
        if ($trimmed === '') { // Return empty string for blank input
            return '';
        }
        $safe = strip_tags($trimmed, '<p><br><strong><b><em><i><u><ul><ol><li><blockquote><span>'); // Allow only safe formatting tags
        if ($safe === strip_tags($safe)) { // Convert plain text newlines to <br> after escaping
            return nl2br(htmlspecialchars($safe, ENT_QUOTES, 'UTF-8'));
        }
        return $safe; // Return sanitized rich-text HTML
    }
}

// Procedural aliases for PresentationHelper methods
function starHtml(int $rating, string $size = 'md'): string { return PresentationHelper::starHtml($rating, $size); }
function avatarHtml(?string $avatar, string $name, string $class = 'reviewer-avatar', ?string $prefix = null): string { return PresentationHelper::avatarHtml($avatar, $name, $class, $prefix); }
function getPosterData(string $title, string $genreName = ''): array { return PresentationHelper::getPosterData($title, $genreName); }
function renderPagination(int $currentPage, int $totalPages, string $baseUrl, array $params = []): string { return PresentationHelper::renderPagination($currentPage, $totalPages, $baseUrl, $params); }
function safeHtmlContent(string $content): string { return PresentationHelper::safeHtmlContent($content); }

/** Encapsulates database queries for films, reviews, genres, contact messages, and dashboard statistics. */
class CinemaRepository
{
    private PDO $db; // Shared PDO database handle
    private static bool $reviewTimeSchemaChecked = false; // Request-scoped guard for reviewtime column check
    private static bool $filmSchemaChecked = false; // Request-scoped guard for film metadata columns check

    /** Accepts an optional PDO instance and ensures required columns exist on legacy schemas. */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DatabaseConnection::getInstance()->getConnection(); // Use injected PDO or Singleton
        UserRepository::ensureAvatarColumn($this->db); // Ensure reviewer table schema is up to date
        self::ensureReviewTimeColumn($this->db); // Ensure reviewtime column exists on review table
        self::ensureFilmColumns($this->db); // Ensure synopsis/cast/URL columns exist on film table
    }

    /** Adds `reviewtime` column to `review` if missing on older database imports. */
    private static function ensureReviewTimeColumn(PDO $db): void
    {
        if (self::$reviewTimeSchemaChecked) { // Skip if already checked during this request
            return;
        }
        $columns = array_column($db->query('SHOW COLUMNS FROM review')->fetchAll(), 'Field'); // Read column names from review table
        if (!in_array('reviewtime', $columns, true)) { // Add reviewtime column if absent
            $db->exec('ALTER TABLE review ADD COLUMN reviewtime TIME DEFAULT NULL AFTER reviewdate');
        }
        self::$reviewTimeSchemaChecked = true; // Mark review schema check complete
    }

    /** Adds `synopsis`, `cast`, `trailer_url`, and `watch_url` columns to `film` if missing. */
    private static function ensureFilmColumns(PDO $db): void
    {
        if (self::$filmSchemaChecked) { // Skip if already checked during this request
            return;
        }
        $columns = array_column($db->query('SHOW COLUMNS FROM film')->fetchAll(), 'Field'); // Read column names from film table
        if (!in_array('synopsis', $columns, true)) { // Add synopsis column if absent
            $db->exec("ALTER TABLE film ADD COLUMN synopsis TEXT NOT NULL DEFAULT '' AFTER duration_min");
        }
        if (!in_array('cast', $columns, true)) { // Add cast column if absent
            $db->exec("ALTER TABLE film ADD COLUMN cast VARCHAR(500) NOT NULL DEFAULT '' AFTER synopsis");
        }
        if (!in_array('trailer_url', $columns, true)) { // Add trailer_url column if absent
            $db->exec("ALTER TABLE film ADD COLUMN trailer_url VARCHAR(500) NOT NULL DEFAULT '' AFTER poster");
        }
        if (!in_array('watch_url', $columns, true)) { // Add watch_url column if absent
            $db->exec("ALTER TABLE film ADD COLUMN watch_url VARCHAR(500) NOT NULL DEFAULT '' AFTER trailer_url");
        }
        if (!in_array('poster_data', $columns, true)) { // Add binary poster BLOB column for persistent cloud storage
            $db->exec("ALTER TABLE film ADD COLUMN poster_data LONGBLOB DEFAULT NULL AFTER poster");
        }
        if (!in_array('poster_mime', $columns, true)) { // Add MIME type column for binary poster streaming
            $db->exec("ALTER TABLE film ADD COLUMN poster_mime VARCHAR(64) DEFAULT NULL AFTER poster_data");
        }
        self::$filmSchemaChecked = true; // Mark film schema check complete
    }

    /** Returns all genres ordered alphabetically by name. */
    public function getAllGenres(): array
    {
        return $this->db->query("SELECT * FROM genre ORDER BY name")->fetchAll(); // Query and return all genre rows
    }

    /** Returns all genres with their associated film count via LEFT JOIN. */
    public function getAllGenresWithFilmCount(): array
    {
        $sql = "SELECT g.*, COUNT(f.id) AS film_count
                FROM genre g
                LEFT JOIN film f ON f.genreid = g.id
                GROUP BY g.id, g.name, g.description
                ORDER BY g.name ASC"; // Aggregate film count per genre
        return $this->db->query($sql)->fetchAll(); // Execute and return genre list with counts
    }

    /** Fetches a single genre record by ID. */
    public function getGenreById(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM genre WHERE id = ?"); // Prepare genre lookup by primary key
        $st->execute([$id]); // Bind genre ID
        return $st->fetch() ?: null; // Return genre row or null if not found
    }

    /** Inserts a new genre record. */
    public function addGenre(string $name, string $description = ''): bool
    {
        $st = $this->db->prepare("INSERT INTO genre (name, description) VALUES (?, ?)"); // Prepare genre insert
        return $st->execute([$name, $description]); // Execute insert and return success boolean
    }

    /** Updates an existing genre's name and description. */
    public function updateGenre(int $id, string $name, string $description = ''): bool
    {
        $st = $this->db->prepare("UPDATE genre SET name = ?, description = ? WHERE id = ?"); // Prepare genre update
        return $st->execute([$name, $description, $id]); // Execute update with bound values
    }

    /** Deletes a genre only when no films reference it (foreign-key safety guard). */
    public function deleteGenre(int $id): bool
    {
        $check = $this->db->prepare("SELECT COUNT(*) FROM film WHERE genreid = ?"); // Count films referencing this genre
        $check->execute([$id]); // Execute reference check
        if ((int)$check->fetchColumn() > 0) { // Abort deletion if any film still uses this genre
            return false;
        }
        $st = $this->db->prepare("DELETE FROM genre WHERE id = ?"); // Prepare safe genre delete
        return $st->execute([$id]); // Execute deletion
    }

    /** Returns a sorted list of distinct non-empty film countries. */
    public function getAllCountries(): array
    {
        $st = $this->db->query("SELECT DISTINCT country FROM film WHERE country <> '' ORDER BY country ASC"); // Query unique non-empty country names
        return $st->fetchAll(PDO::FETCH_COLUMN); // Return flat array of country strings
    }

    /** Counts films matching optional title search, genre ID, and country filters. */
    public function countFilms(string $search = '', string|int $genreId = '', string $country = ''): int
    {
        $sql = "SELECT COUNT(DISTINCT f.id) FROM film f WHERE 1=1"; // Base count query with dynamic WHERE
        $params = []; // Bound parameter list
        if ($search !== '') { // Filter by partial title match
            $sql .= " AND f.title LIKE ?";
            $params[] = "%$search%";
        }
        if ($genreId !== '') { // Filter by exact genre ID
            $sql .= " AND f.genreid = ?";
            $params[] = (int)$genreId;
        }
        if ($country !== '') { // Filter by exact country name
            $sql .= " AND f.country = ?";
            $params[] = $country;
        }
        $st = $this->db->prepare($sql); // Prepare dynamic count query
        $st->execute($params); // Execute with collected filter parameters
        return (int)$st->fetchColumn(); // Return integer film count
    }

    /** Fetches filtered films with genre name, review count, average rating, and optional pagination/sorting. */
    public function getAllFilms(string $search = '', string|int $genreId = '', string $country = '', int $limit = 0, int $offset = 0, string $sort = ''): array
    {
        $db = $this->db; // Local reference to PDO handle
        $sql = "SELECT f.*, g.name AS genre_name, COUNT(r.id) AS review_count, ROUND(AVG(r.rating), 1) AS avg_rating
                FROM film f LEFT JOIN genre g ON f.genreid = g.id LEFT JOIN review r ON r.filmid = f.id WHERE 1=1"; // Join genre and aggregate review stats
        $params = []; // Bound parameter list
        // Search by title
        if ($search !== '') { // Append title LIKE filter when search is non-empty
            $sql .= " AND f.title LIKE ?";
            $params[] = "%$search%";
        }
        // Filter by genre
        if ($genreId !== '') { // Append genre ID filter
            $sql .= " AND f.genreid = ?";
            $params[] = (int)$genreId;
        }
        // Filter by country
        if ($country !== '') { // Append country filter
            $sql .= " AND f.country = ?";
            $params[] = $country;
        }
        $orderBy = match ($sort) {
            'title_asc'  => 'f.title ASC, f.id DESC',
            'title_desc' => 'f.title DESC, f.id DESC',
            default      => 'f.release_year DESC, f.id DESC',
        };
        $sql .= " GROUP BY f.id ORDER BY {$orderBy}"; // Group per film and apply whitelisted sort order
        if ($limit > 0) { // Apply integer-cast LIMIT/OFFSET when pagination is requested
            $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }
        $st = $db->prepare($sql); // Prepare filtered film query
        $st->execute($params); // Execute with bound filter values
        return $st->fetchAll(); // Return matching film rows
    }

    /** Fetches a single film with its genre details and aggregated review stats. */
    public function getFilmById(int $id): ?array
    {
        $st = $this->db->prepare("SELECT f.*, g.name AS genre_name, g.description AS genre_desc, COUNT(r.id) AS review_count, ROUND(AVG(r.rating), 1) AS avg_rating
                                FROM film f LEFT JOIN genre g ON f.genreid = g.id LEFT JOIN review r ON r.filmid = f.id WHERE f.id = ? GROUP BY f.id"); // Query single film with genre and review aggregates
        $st->execute([$id]); // Bind film ID
        return $st->fetch() ?: null; // Return film row or null if missing
    }

    /** Returns top-rated films (that have at least one review) ordered by average rating. */
    public function getTopRatedFilms(int $limit = 5): array
    {
        $st = $this->db->prepare("SELECT f.*, g.name AS genre_name, COUNT(r.id) AS review_count, ROUND(AVG(r.rating), 1) AS avg_rating
                                FROM film f LEFT JOIN genre g ON f.genreid = g.id LEFT JOIN review r ON r.filmid = f.id
                                GROUP BY f.id HAVING review_count > 0 ORDER BY avg_rating DESC, review_count DESC LIMIT ?"); // Rank reviewed films by average rating
        $st->bindValue(1, $limit, PDO::PARAM_INT); // Bind limit as integer
        $st->execute(); // Execute query
        return $st->fetchAll(); // Return top-rated film rows
    }

    /** Returns the most recent community reviews joined with author and film metadata. */
    public function getRecentReviews(int $limit = 6): array
    {
        $st = $this->db->prepare("SELECT r.*, r.reviewtext AS text, r.reviewdate AS date, rv.fullname, rv.avatar, f.title AS film_title, f.id AS film_id
                                FROM review r JOIN reviewer rv ON r.reviewerid = rv.id JOIN film f ON r.filmid = f.id
                                ORDER BY r.reviewdate DESC LIMIT ?"); // Fetch latest reviews with reviewer and film info
        $st->bindValue(1, $limit, PDO::PARAM_INT); // Bind limit as integer
        $st->execute(); // Execute query
        return $st->fetchAll(); // Return recent review rows
    }

    /** Returns all reviews for a specific film ordered newest first. */
    public function getReviewsByFilm(int $filmId): array
    {
        $st = $this->db->prepare("SELECT r.*, r.reviewtext AS text, r.reviewdate AS date, rv.fullname, rv.email, rv.avatar
                                FROM review r
                                JOIN reviewer rv ON r.reviewerid = rv.id
                                WHERE r.filmid = ?
                                ORDER BY r.reviewdate DESC"); // Query all reviews for a film
        $st->execute([$filmId]); // Bind film ID
        return $st->fetchAll(); // Return film reviews
    }

    /** Counts reviews matching optional film title search, genre filter, and country filter. */
    public function countReviewsFiltered(string $search = '', string|int $genreId = '', string $country = ''): int
    {
        $sql = "SELECT COUNT(r.id)
                FROM review r
                JOIN reviewer rv ON r.reviewerid = rv.id
                JOIN film f ON r.filmid = f.id
                WHERE 1=1"; // Base review count query
        $params = []; // Bound parameter list
        if ($search !== '') { // Filter by film title substring
            $sql .= " AND f.title LIKE ?";
            $params[] = "%$search%";
        }
        if ($genreId !== '') { // Filter by film genre ID
            $sql .= " AND f.genreid = ?";
            $params[] = (int)$genreId;
        }
        if ($country !== '') { // Filter by film country
            $sql .= " AND f.country = ?";
            $params[] = $country;
        }
        $st = $this->db->prepare($sql); // Prepare count query
        $st->execute($params); // Execute with filters
        return (int)$st->fetchColumn(); // Return integer review count
    }

    /** Returns filtered, multi-column sorted, and paginated reviews using an allowlisted ORDER BY map. */
    public function getAllReviewsFiltered(string $search = '', string|int $genreId = '', array|string $sort = [], int $limit = 0, int $offset = 0, string $country = ''): array
    {
        $db = $this->db; // Local reference to PDO handle
        $sql = "SELECT r.*, r.reviewtext AS text, r.reviewdate AS date, r.reviewtime AS review_time, rv.fullname, rv.email, rv.avatar,
                       f.title AS film_title, f.id AS film_id, f.poster, g.name AS genre_name
                FROM review r
                JOIN reviewer rv ON r.reviewerid = rv.id
                JOIN film f ON r.filmid = f.id
                LEFT JOIN genre g ON f.genreid = g.id
                WHERE 1=1"; // Base query joining reviewer, film, and genre

        $params = []; // Bound parameter list
        if ($search !== '') { // Filter by film title substring
            $sql .= " AND f.title LIKE ?";
            $params[] = "%$search%";
        }
        if ($genreId !== '') { // Filter by film genre ID
            $sql .= " AND f.genreid = ?";
            $params[] = (int)$genreId;
        }
        if ($country !== '') { // Filter by film country
            $sql .= " AND f.country = ?";
            $params[] = $country;
        }

        // Allowlist of supported sort keys to prevent SQL injection in ORDER BY
        $sortOptions = [
            'rating_desc' => 'r.rating DESC',
            'rating_asc'  => 'r.rating ASC',
            'date_desc'   => 'r.reviewdate DESC',
            'date_asc'    => 'r.reviewdate ASC',
            'time_desc'   => 'r.reviewtime IS NULL ASC, r.reviewtime DESC',
            'time_asc'    => 'r.reviewtime IS NULL ASC, r.reviewtime ASC',
        ];
        $sortCriteria = is_array($sort) ? $sort : [$sort]; // Normalize single string or array of sort keys
        $orderBy = []; // Ordered list of SQL ORDER BY clauses
        foreach ($sortCriteria as $criterion) { // Map each valid sort key to its SQL expression
            if (is_string($criterion) && isset($sortOptions[$criterion])) {
                $orderBy[] = $sortOptions[$criterion];
            }
        }
        // Append deterministic fallback sort columns if not already specified
        $fallbacks = ['r.reviewdate DESC', 'r.reviewtime DESC', 'r.id DESC']; // Default tie-breaker order
        foreach ($fallbacks as $fb) {
            $col = strtok($fb, ' '); // Extract column name for duplicate check
            $alreadyIncluded = false; // Track whether column is already in $orderBy
            foreach ($orderBy as $existing) {
                if (str_contains($existing, $col)) {
                    $alreadyIncluded = true;
                    break;
                }
            }
            if (!$alreadyIncluded) { // Append fallback column if not already sorted
                $orderBy[] = $fb;
            }
        }

        $sql .= ' ORDER BY ' . implode(', ', $orderBy); // Assemble final ORDER BY clause
        if ($limit > 0) { // Apply pagination LIMIT/OFFSET when specified
            $sql .= ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
        } else { // Safety cap when unpaginated
            $sql .= ' LIMIT 100';
        }

        $st = $db->prepare($sql); // Prepare filtered and sorted review query
        $st->execute($params); // Execute with filter parameters
        return $st->fetchAll(); // Return matching review rows
    }

    /** Inserts a new review if the reviewer's account is not suspended. */
    public function addReview(int $filmId, int $reviewerId, string $text, int $rating): bool
    {
        $account = $this->db->prepare('SELECT is_suspended FROM reviewer WHERE id = ?'); // Check reviewer suspension status
        $account->execute([$reviewerId]); // Execute status lookup
        if ((int)$account->fetchColumn() === 1) { // Block suspended accounts from posting reviews
            return false;
        }
        $now = new DateTimeImmutable(); // Capture current date and time atomically
        $st = $this->db->prepare("INSERT INTO review (reviewtext, reviewdate, reviewtime, reviewerid, filmid, rating) VALUES (?, ?, ?, ?, ?, ?)"); // Prepare review insert
        return $st->execute([$text, $now->format('Y-m-d'), $now->format('H:i:s'), $reviewerId, $filmId, $rating]); // Persist review with date and time
    }

    /** Deletes a review by ID. */
    public function deleteReview(int $id): bool
    {
        return $this->db->prepare("DELETE FROM review WHERE id = ?")->execute([$id]); // Delete single review row
    }

    /** Stores a contact-us message from an active (non-suspended) reviewer. */
    public function addContactMessage(int $reviewer_id, string $subject, string $content): bool
    {
        $account = $this->db->prepare('SELECT is_suspended FROM reviewer WHERE id = ?'); // Check reviewer suspension status
        $account->execute([$reviewer_id]); // Execute status lookup
        if ((int)$account->fetchColumn() === 1) { // Block suspended accounts from sending messages
            return false;
        }
        $sql = "INSERT INTO contact_us (reviewer_id, subject, content, sent_at) VALUES (?, ?, ?, NOW())"; // Prepare contact message insert
        return $this->db->prepare($sql)->execute([$reviewer_id, $subject, $content]); // Store message with current timestamp
    }

    /** Ensures admin_reply and replied_at columns exist on contact_us. */
    public function ensureContactReplyColumns(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;
        $cols = $this->db->query("SHOW COLUMNS FROM contact_us")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('admin_reply', $cols, true)) {
            $this->db->exec("ALTER TABLE contact_us ADD COLUMN admin_reply TEXT NULL AFTER content");
        }
        if (!in_array('replied_at', $cols, true)) {
            $this->db->exec("ALTER TABLE contact_us ADD COLUMN replied_at DATETIME NULL AFTER admin_reply");
        }
    }

    /** Returns all active (non-archived) contact messages ordered newest first. */
    public function getAllMessages(): array
    {
        $this->ensureContactReplyColumns();
        return $this->db->query("SELECT c.*, r.fullname, r.email
                               FROM contact_us as c
                               JOIN reviewer as r ON c.reviewer_id = r.id
                               WHERE c.is_active = 1
                               ORDER BY c.sent_at DESC")->fetchAll(); // Fetch active messages with sender info
    }

    /** Saves an administrator reply to a contact message and returns sender details. */
    public function replyToMessage(int $id, string $reply): ?array
    {
        $this->ensureContactReplyColumns();
        $upd = $this->db->prepare("UPDATE contact_us SET admin_reply = ?, replied_at = NOW() WHERE id = ? AND is_active = 1");
        $upd->execute([$reply, $id]);

        $st = $this->db->prepare("SELECT c.*, r.fullname, r.email FROM contact_us c JOIN reviewer r ON c.reviewer_id = r.id WHERE c.id = ? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** Soft-deletes a contact message by setting is_active = 0. */
    public function deleteMessage(int $id): bool
    {
        return $this->db->prepare("UPDATE contact_us SET is_active = 0 WHERE id = ?")->execute([$id]); // Archive message via soft-delete flag
    }

    /** Returns aggregate entity counts for the admin dashboard and home page. */
    public function getStats(): array
    {
        $db = $this->db; // Local reference to PDO handle
        return [ // Query total counts across all four core tables
            'films'     => (int)$db->query("SELECT COUNT(*) FROM film")->fetchColumn(),
            'reviews'   => (int)$db->query("SELECT COUNT(*) FROM review")->fetchColumn(),
            'reviewers' => (int)$db->query("SELECT COUNT(*) FROM reviewer")->fetchColumn(),
            'genres'    => (int)$db->query("SELECT COUNT(*) FROM genre")->fetchColumn(),
        ];
    }

    /** Fetches a review joined with author and film title for the edit form. */
    public function getReviewForEditing(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT r.*, rv.fullname, rv.email, rv.avatar, f.title AS film_title
             FROM review r JOIN reviewer rv ON r.reviewerid = rv.id
             JOIN film f ON r.filmid = f.id WHERE r.id = ?'
        ); // Prepare review edit lookup
        $statement->execute([$id]); // Bind review ID
        return $statement->fetch() ?: null; // Return review row or null
    }

    /** Updates a review's text and star rating. */
    public function updateReview(int $id, string $text, int $rating): bool
    {
        return $this->db->prepare('UPDATE review SET reviewtext = ?, rating = ? WHERE id = ?') // Prepare review update
            ->execute([$text, $rating, $id]); // Save updated text and rating
    }

    /** Fetches raw film columns for admin editing. */
    public function getFilmByIdForAdmin(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM film WHERE id = ?'); // Prepare raw film lookup
        $statement->execute([$id]); // Bind film ID
        return $statement->fetch() ?: null; // Return film row or null
    }

    /** Fetches only the poster path/filename for a film. */
    public function getFilmPoster(int $id): ?string
    {
        $statement = $this->db->prepare('SELECT poster FROM film WHERE id = ?'); // Query poster column only
        $statement->execute([$id]); // Bind film ID
        $poster = $statement->fetchColumn(); // Fetch poster filename/path
        return $poster === false ? null : $poster; // Return null if film does not exist
    }

    /** Inserts a new film record into the catalog. */
    public function createFilm(
        string $title,
        int $year,
        int $duration,
        int $genreId,
        ?string $poster,
        string $country = 'United Kingdom',
        string $synopsis = '',
        string $cast = '',
        string $trailerUrl = '',
        string $watchUrl = ''
    ): bool {
        $posterVal = $poster ?? ''; // Convert null poster to empty string for NOT NULL column
        return $this->db->prepare('INSERT INTO film (title, release_year, duration_min, genreid, poster, country, synopsis, cast, trailer_url, watch_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)') // Prepare film insert
            ->execute([$title, $year, $duration, $genreId, $posterVal, $country, $synopsis, $cast, $trailerUrl, $watchUrl]); // Execute insert with all metadata fields
    }

    /** Returns the auto-increment ID of the most recently inserted film record. */
    public function getLastInsertedFilmId(): int
    {
        return (int)$this->db->lastInsertId(); // Read last inserted primary key from PDO
    }

    /** Stores an uploaded film poster image directly in MySQL as a BLOB alongside its stream URL. */
    public function updateFilmPosterBlob(int $filmId, string $bytes, string $mime, string $posterUrl): bool
    {
        return $this->db->prepare('UPDATE film SET poster = ?, poster_data = ?, poster_mime = ? WHERE id = ?') // Prepare poster BLOB update
            ->execute([$posterUrl, $bytes, $mime, $filmId]); // Persist stream URL, raw image bytes, and MIME type
    }

    /** Updates an existing film record, preserving the existing poster if $poster is null. */
    public function updateFilm(
        int $id,
        string $title,
        int $year,
        int $duration,
        int $genreId,
        ?string $poster,
        string $country = 'United Kingdom',
        string $synopsis = '',
        string $cast = '',
        string $trailerUrl = '',
        string $watchUrl = ''
    ): bool {
        if ($poster !== null) { // Update poster column when a new poster was uploaded or cleared
            return $this->db->prepare('UPDATE film SET title = ?, release_year = ?, duration_min = ?, genreid = ?, poster = ?, country = ?, synopsis = ?, cast = ?, trailer_url = ?, watch_url = ? WHERE id = ?')
                ->execute([$title, $year, $duration, $genreId, $poster, $country, $synopsis, $cast, $trailerUrl, $watchUrl, $id]);
        } else { // Preserve existing poster column when no new file was supplied
            return $this->db->prepare('UPDATE film SET title = ?, release_year = ?, duration_min = ?, genreid = ?, country = ?, synopsis = ?, cast = ?, trailer_url = ?, watch_url = ? WHERE id = ?')
                ->execute([$title, $year, $duration, $genreId, $country, $synopsis, $cast, $trailerUrl, $watchUrl, $id]);
        }
    }

    /** Atomically deletes a film's reviews and the film itself, returning the poster path for file cleanup. */
    public function deleteFilm(int $id): ?string
    {
        $this->db->beginTransaction(); // Start transaction so reviews and film delete together
        try {
            $poster = $this->getFilmPoster($id); // Read poster path before deleting record
            $this->db->prepare('DELETE FROM review WHERE filmid = ?')->execute([$id]); // Remove dependent reviews first
            $this->db->prepare('DELETE FROM film WHERE id = ?')->execute([$id]); // Remove film record
            $this->db->commit(); // Commit transaction
            return $poster; // Return poster path for disk cleanup
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) { // Roll back on any SQL failure
                $this->db->rollBack();
            }
            throw $error; // Re-throw exception to caller
        }
    }

    /** Lists reviewers with aggregated review counts and average ratings, supporting search and sorting. */
    public function getReviewersFiltered(string $search = '', string $sort = 'registered_desc'): array
    {
        $sql = 'SELECT rv.id, rv.fullname, rv.email, rv.avatar, COUNT(r.id) AS review_count,
                       ROUND(AVG(r.rating), 1) AS avg_rating,
                       rv.registered_at, rv.is_suspended
                FROM reviewer rv LEFT JOIN review r ON r.reviewerid = rv.id'; // Join reviews to compute per-user stats
        $params = []; // Bound parameter list
        if ($search !== '') { // Filter by name or email substring
            $sql .= ' WHERE rv.fullname LIKE ? OR rv.email LIKE ?';
            $params = ["%$search%", "%$search%"];
        }
        $sortOptions = [ // Allowlisted ORDER BY expressions for reviewer management
            'registered_desc' => 'rv.registered_at IS NULL ASC, rv.registered_at DESC, rv.id DESC',
            'registered_asc'  => 'rv.registered_at IS NULL ASC, rv.registered_at ASC, rv.id ASC',
            'rating_desc'     => 'avg_rating IS NULL ASC, avg_rating DESC, rv.id DESC',
            'rating_asc'      => 'avg_rating IS NULL ASC, avg_rating ASC, rv.id ASC',
        ];
        $sql .= ' GROUP BY rv.id ORDER BY ' . ($sortOptions[$sort] ?? $sortOptions['registered_desc']); // Group per reviewer and apply safe sort
        $statement = $this->db->prepare($sql); // Prepare reviewer query
        $statement->execute($params); // Execute with search parameters
        return $statement->fetchAll(); // Return reviewer list
    }

    /** Atomically deletes a reviewer's reviews and account inside a transaction. */
    public function deleteReviewer(int $id): bool
    {
        $this->db->beginTransaction(); // Start transaction for atomic user deletion
        try {
            $this->db->prepare('DELETE FROM review WHERE reviewerid = ?')->execute([$id]); // Delete reviewer's reviews first
            $this->db->prepare('DELETE FROM reviewer WHERE id = ?')->execute([$id]); // Delete reviewer row
            $this->db->commit(); // Commit transaction
            return true; // Report success
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) { // Roll back on failure
                $this->db->rollBack();
            }
            throw $error; // Re-throw exception
        }
    }

    /** Updates a reviewer's suspension flag (1 = suspended, 0 = active). */
    public function setReviewerSuspended(int $id, bool $suspended): bool
    {
        return $this->db->prepare('UPDATE reviewer SET is_suspended = ? WHERE id = ?') // Prepare suspension flag update
            ->execute([$suspended ? 1 : 0, $id]); // Persist integer 1/0 flag
    }
}

// Backward-compatible procedural entry points used by existing page controllers and templates.
function getAllGenres(): array { return (new CinemaRepository())->getAllGenres(...func_get_args()); }
function getGenreById(int $id): ?array { return (new CinemaRepository())->getGenreById(...func_get_args()); }
function addGenre(string $name, string $description = ''): bool { return (new CinemaRepository())->addGenre($name, $description); }
function updateGenre(int $id, string $name, string $description = ''): bool { return (new CinemaRepository())->updateGenre($id, $name, $description); }
function deleteGenre(int $id): bool { return (new CinemaRepository())->deleteGenre($id); }
function getAllGenresWithFilmCount(): array { return (new CinemaRepository())->getAllGenresWithFilmCount(); }
function countFilms(string $search = '', string|int $genreId = '', string $country = ''): int { return (new CinemaRepository())->countFilms(...func_get_args()); }
function getAllFilms(string $search = '', string|int $genreId = '', string $country = ''): array { return (new CinemaRepository())->getAllFilms(...func_get_args()); }
function countReviewsFiltered(string $search = '', string|int $genreId = ''): int { return (new CinemaRepository())->countReviewsFiltered(...func_get_args()); }
function getAllCountries(): array { return (new CinemaRepository())->getAllCountries(); }
function getFilmById(int $id): ?array { return (new CinemaRepository())->getFilmById(...func_get_args()); }
function getTopRatedFilms(int $limit = 5): array { return (new CinemaRepository())->getTopRatedFilms(...func_get_args()); }
function getRecentReviews(int $limit = 6): array { return (new CinemaRepository())->getRecentReviews(...func_get_args()); }
function getReviewsByFilm(int $filmId): array { return (new CinemaRepository())->getReviewsByFilm(...func_get_args()); }
function getAllReviewsFiltered(string $search = '', string|int $genreId = '', array|string $sort = []): array { return (new CinemaRepository())->getAllReviewsFiltered(...func_get_args()); }
function addReview(int $filmId, int $reviewerId, string $text, int $rating): bool { return (new CinemaRepository())->addReview(...func_get_args()); }
function deleteReview(int $id): bool { return (new CinemaRepository())->deleteReview(...func_get_args()); }
function addContactMessage(int $reviewer_id, string $subject, string $content): bool { return (new CinemaRepository())->addContactMessage(...func_get_args()); }
function getAllMessages(): array { return (new CinemaRepository())->getAllMessages(...func_get_args()); }
function deleteMessage(int $id): bool { return (new CinemaRepository())->deleteMessage(...func_get_args()); }
function getStats(): array { return (new CinemaRepository())->getStats(...func_get_args()); }
function getReviewForEditing(int $id): ?array { return (new CinemaRepository())->getReviewForEditing($id); }
function updateReview(int $id, string $text, int $rating): bool { return (new CinemaRepository())->updateReview($id, $text, $rating); }
function getFilmByIdForAdmin(int $id): ?array { return (new CinemaRepository())->getFilmByIdForAdmin($id); }
function getFilmPoster(int $id): ?string { return (new CinemaRepository())->getFilmPoster($id); }

/** Converts standard YouTube watch/short URLs into /embed/ URLs for iframe playback. */
function normalizeYouTubeEmbedUrl(string $url): string
{
    $url = trim($url); // Trim surrounding whitespace
    if ($url === '') { // Return empty string if no URL provided
        return '';
    }
    if (strpos($url, 'youtube.com/embed/') !== false) { // Keep already-normalized embed URLs unchanged
        return $url;
    }
    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) { // Extract 11-char YouTube video ID
        return 'https://www.youtube.com/embed/' . $m[1]; // Build canonical YouTube embed URL
    }
    return $url; // Return original URL if not a recognized YouTube format
}

function createFilm(string $title, int $year, int $duration, int $genreId, ?string $poster, string $country = 'United Kingdom', string $synopsis = '', string $cast = '', string $trailerUrl = '', string $watchUrl = ''): bool {
    return (new CinemaRepository())->createFilm($title, $year, $duration, $genreId, $poster, $country, $synopsis, $cast, $trailerUrl, $watchUrl); // Delegate to CinemaRepository::createFilm
}
function updateFilm(int $id, string $title, int $year, int $duration, int $genreId, ?string $poster, string $country = 'United Kingdom', string $synopsis = '', string $cast = '', string $trailerUrl = '', string $watchUrl = ''): bool {
    return (new CinemaRepository())->updateFilm($id, $title, $year, $duration, $genreId, $poster, $country, $synopsis, $cast, $trailerUrl, $watchUrl); // Delegate to CinemaRepository::updateFilm
}
function deleteFilm(int $id): ?string { return (new CinemaRepository())->deleteFilm($id); }
function getReviewersFiltered(string $search = '', string $sort = 'registered_desc'): array { return (new CinemaRepository())->getReviewersFiltered($search, $sort); }
function deleteReviewer(int $id): bool { return (new CinemaRepository())->deleteReviewer($id); }
function setReviewerSuspended(int $id, bool $suspended): bool { return (new CinemaRepository())->setReviewerSuspended($id, $suspended); }
function registerUser(string $fullname, string $email, string $password): bool|string { return (new UserRepository())->create($fullname, $email, $password); }
function loginUser(string $email, string $password): ?array { return (new UserRepository())->authenticate($email, $password); }
function posterUrl(?string $poster, string $prefix = ''): string { return PresentationHelper::posterUrl($poster, $prefix); }
