<?php
/**
 * NexaTech Solutions — Database Class (PDO Singleton)
 */

require_once __DIR__ . '/config.php';

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    /**
     * Private constructor — use Database::getInstance()
     */
    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error without exposing credentials
            error_log('[NexaTech DB] Connection failed: ' . $e->getMessage());
            // In production, show a friendly error page
            http_response_code(503);
            die(json_encode([
                'success' => false,
                'error'   => 'Service temporarily unavailable. Please try again later.'
            ]));
        }
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prepare and execute a statement, return the PDOStatement
     *
     * @param  string $sql
     * @param  array  $params
     * @return PDOStatement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('[NexaTech DB] Query error: ' . $e->getMessage() . ' | SQL: ' . $sql);
            throw new RuntimeException('Database query failed.');
        }
    }

    /**
     * Fetch all rows from a SELECT query
     *
     * @param  string $sql
     * @param  array  $params
     * @return array
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Fetch a single row from a SELECT query
     *
     * @param  string     $sql
     * @param  array      $params
     * @return array|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Execute an INSERT/UPDATE/DELETE statement
     * Returns last insert ID for INSERTs, affected row count for others
     *
     * @param  string     $sql
     * @param  array      $params
     * @return int|string Last insert ID or affected rows
     */
    public function execute(string $sql, array $params = []): int|string
    {
        $stmt = $this->query($sql, $params);

        // Detect INSERT statements
        $verb = strtoupper(substr(ltrim($sql), 0, 6));
        if ($verb === 'INSERT') {
            return $this->pdo->lastInsertId();
        }

        return $stmt->rowCount();
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Roll back a transaction
     */
    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    // Prevent cloning and unserialization of the singleton
    private function __clone() {}
    public function __wakeup(): never
    {
        throw new RuntimeException('Cannot unserialize a singleton.');
    }
}
