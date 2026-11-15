<?php

/**
 * DatabaseConnection.php — Singleton PDO Database Connector.
 * Purpose: Provides a single shared PDO MySQL connection across the entire application.
 * Connections: Required by UserRepository, AiChatRepository, and DataBaseFunctions.php.
 * OOP/Patterns: Singleton Pattern (private constructor + static getInstance()).
 * Data Structures & Algorithms: Lazy initialization of a single static PDO wrapper instance.
 */
class DatabaseConnection
{
    private static $instance = null; // Holds the single shared DatabaseConnection instance
    private $connection;             // Active PDO connection object

    // MySQL connection credentials (reads Render/cloud env vars first, falls back to local XAMPP)
    private string $host;     // Database server hostname
    private string $port;     // Database server TCP port
    private string $dbname;   // Target MySQL schema name
    private string $username; // Database user account
    private string $password; // Database password

    /** Private constructor prevents direct instantiation (Singleton pattern). */
    private function __construct()
    {
        // Resolve connection parameters from environment variables or local XAMPP defaults
        $this->host     = getenv('CW_DB_HOST') ?: 'localhost';
        $this->port     = getenv('CW_DB_PORT') ?: '3306';
        $this->dbname   = getenv('CW_DB_NAME') ?: 'film_db';
        $this->username = getenv('CW_DB_USER') ?: 'root';
        $this->password = getenv('CW_DB_PASS') ?: '';

        try {
            // Build Data Source Name with host, port, schema, and utf8mb4 charset
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4";
            // Configure PDO for exceptions, associative arrays, and native prepared statements
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on SQL errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Return rows as associative arrays
                PDO::ATTR_EMULATE_PREPARES   => false, // Use native MySQL prepared statements
            ];
            // Enable TLS/SSL automatically for cloud MySQL hosts such as TiDB Cloud
            if (!in_array($this->host, ['localhost', '127.0.0.1', '::1'], true)) {
                $caCandidates = array_filter([
                    getenv('CW_DB_SSL_CA') ?: null,
                    '/etc/ssl/certs/ca-certificates.crt',
                    ini_get('openssl.cafile') ?: null,
                    ini_get('curl.cainfo') ?: null,
                    'C:/xampp/apache/bin/curl-ca-bundle.crt',
                    'C:/xampp/php/extras/ssl/cacert.pem',
                ]);
                $caPath = null;
                foreach ($caCandidates as $candidate) {
                    if (is_file($candidate)) {
                        $caPath = $candidate;
                        break;
                    }
                }
                if ($caPath !== null) {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
                } else {
                    // Trigger TLS handshake in mysqlnd even when no CA bundle path is configured
                    $options[PDO::MYSQL_ATTR_SSL_CIPHER] = 'ECDHE-RSA-AES128-GCM-SHA256:AES128-GCM-SHA256';
                }
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
            // Use Vietnam timezone (+07:00) by default in PHP and DB session so NOW() matches local user time instead of UTC
            date_default_timezone_set(getenv('CW_TIMEZONE') ?: 'Asia/Ho_Chi_Minh');
            // Establish the PDO connection
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            // Disable ONLY_FULL_GROUP_BY per session so legacy GROUP BY f.id queries work on TiDB Cloud / MySQL 8
            $this->connection->exec("SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''), ',,', ','))");
            // Align MySQL/TiDB session timezone with PHP local timezone
            $this->connection->exec("SET time_zone = '+07:00'");
        } catch (PDOException $e) {
            // Halt execution and escape error output to prevent XSS
            die('<p style="color:red;font-family:monospace;padding:2rem">DB Error: ' . htmlspecialchars($e->getMessage()) . '</p>');
        }
    }

    /** Returns the single shared DatabaseConnection instance, creating it on first call. */
    public static function getInstance(): self
    {
        if (self::$instance === null) { // Instantiate once on first access
            self::$instance = new self();
        }
        return self::$instance; // Return shared Singleton instance
    }

    /** Exposes the underlying PDO connection for prepared statements. */
    public function getConnection(): PDO
    {
        return $this->connection; // Return active PDO handle
    }
}
