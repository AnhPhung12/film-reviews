<?php
require_once __DIR__ . '/../DataBaseFunctions.php'; // Load CinemaRepository and URL helpers

/**
 * FilmController.php — OOP Controller for Film Catalogue & Admin CRUD.
 * Purpose: Handles multi-criteria filtering (search, genre, country), pagination, and film management.
 * Connections:
 *   - Called by: films.php (paginated catalogue), film.php, admin/films.php.
 *   - Delegates SQL queries to: CinemaRepository (PDO prepared statements).
 * OOP/Patterns: Controller / Service Wrapper with optional Dependency Injection.
 * Data Structures & Algorithms:
 *   - Pagination uses LIMIT/OFFSET slicing: offset = (page - 1) * limit.
 *   - Normalizes YouTube URLs into safe embed URLs on create/update.
 */
class FilmController
{
    private CinemaRepository $cinema; // Repository handling film SQL queries

    /** Injects CinemaRepository or instantiates a default repository. */
    public function __construct(?CinemaRepository $cinema = null)
    {
        $this->cinema = $cinema ?? new CinemaRepository(); // Use injected repository or create default
    }

    /** Counts total films matching the optional search, genre, and country filters. */
    public function countFiltered(string $search = '', string|int $genreId = '', string $country = ''): int
    {
        return $this->cinema->countFilms($search, $genreId, $country); // Delegate filtered film count
    }

    /** Returns a paginated, filtered list of films with aggregated review metrics. */
    public function listFiltered(string $search = '', string|int $genreId = '', string $country = '', int $limit = 0, int $offset = 0): array
    {
        return $this->cinema->getAllFilms($search, $genreId, $country, $limit, $offset); // Delegate paginated film query
    }

    /** Returns distinct film countries for dropdown filters. */
    public function getCountries(): array
    {
        return $this->cinema->getAllCountries(); // Fetch distinct country names
    }

    /** Fetches full film details (with genre info and rating summary) for public view. */
    public function getDetail(int $id): ?array
    {
        return $this->cinema->getFilmById($id); // Fetch film with aggregated stats
    }

    /** Fetches raw film columns for the admin edit form. */
    public function getForAdmin(int $id): ?array
    {
        return $this->cinema->getFilmByIdForAdmin($id); // Fetch raw film columns
    }

    /** Retrieves the stored poster filename/path for a film. */
    public function getPoster(int $id): ?string
    {
        return $this->cinema->getFilmPoster($id); // Fetch poster path for file cleanup
    }

    /** Creates a new film record after normalizing its YouTube trailer URL. */
    public function create(
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
        return $this->cinema->createFilm( // Persist film with normalized YouTube embed URL
            $title,
            $year,
            $duration,
            $genreId,
            $poster,
            $country,
            $synopsis,
            $cast,
            normalizeYouTubeEmbedUrl($trailerUrl), // Convert watch/short URL to /embed/ format
            $watchUrl
        );
    }

    /** Updates an existing film record and normalizes its YouTube trailer URL. */
    public function update(
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
        return $this->cinema->updateFilm( // Update film record with normalized YouTube embed URL
            $id,
            $title,
            $year,
            $duration,
            $genreId,
            $poster,
            $country,
            $synopsis,
            $cast,
            normalizeYouTubeEmbedUrl($trailerUrl), // Convert watch/short URL to /embed/ format
            $watchUrl
        );
    }

    /** Deletes a film and its associated reviews, returning the old poster path for cleanup. */
    public function delete(int $id): ?string
    {
        return $this->cinema->deleteFilm($id); // Delete film transactionally and return poster path
    }
}
