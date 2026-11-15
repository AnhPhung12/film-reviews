<?php
require_once __DIR__ . '/../DataBaseFunctions.php'; // Load CinemaRepository

/**
 * GenreController.php — OOP Controller for Genre CRUD.
 * Purpose: Manages film categories (list with film counts, create, update, safe delete).
 * Connections:
 *   - Called by: films.php, reviews.php, admin/genres.php.
 *   - Delegates SQL operations to: CinemaRepository.
 * OOP/Patterns: Controller / Service Wrapper with optional Dependency Injection.
 * Data Structures & Algorithms: Foreign-key guard prevents deleting genres still referenced by films.
 */
class GenreController
{
    private CinemaRepository $cinema; // Repository handling genre SQL queries

    /** Injects CinemaRepository or instantiates a default repository. */
    public function __construct(?CinemaRepository $cinema = null)
    {
        $this->cinema = $cinema ?? new CinemaRepository(); // Use injected repository or create default
    }

    /** Returns all genres sorted alphabetically. */
    public function listAll(): array
    {
        return $this->cinema->getAllGenres(); // Fetch alphabetically sorted genres
    }

    /** Returns all genres along with the number of films assigned to each. */
    public function listWithFilmCount(): array
    {
        return $this->cinema->getAllGenresWithFilmCount(); // Fetch genres with film counts
    }

    /** Finds a single genre by its primary key ID. */
    public function find(int $id): ?array
    {
        return $this->cinema->getGenreById($id); // Fetch single genre row
    }

    /** Creates a new genre after trimming whitespace from name and description. */
    public function create(string $name, string $description = ''): bool
    {
        return $this->cinema->addGenre(trim($name), trim($description)); // Trim inputs and insert genre
    }

    /** Updates an existing genre's name and description. */
    public function update(int $id, string $name, string $description = ''): bool
    {
        return $this->cinema->updateGenre($id, trim($name), trim($description)); // Trim inputs and update genre
    }

    /** Deletes a genre only if no films reference it. */
    public function delete(int $id): bool
    {
        return $this->cinema->deleteGenre($id); // Delete genre with FK safety check
    }
}
