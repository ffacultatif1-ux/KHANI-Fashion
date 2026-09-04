<?php
/**
 * Classe Database - Singleton PDO multi-driver (MySQL / PostgreSQL)
 *
 * Le driver actif est défini par DB_DRIVER dans le fichier .env :
 * - 'pgsql' → Supabase (PostgreSQL)
 * - 'mysql' → base locale XAMPP
 */
class Database {
    private static ?Database $instance = null;
    private PDO $pdo;
    private string $driver;

    private function __construct() {
        $this->driver = DB_DRIVER;

        if ($this->driver === 'pgsql') {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_SSLMODE
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                // Compatible avec PgBouncer (poolers Supabase)
                PDO::PGSQL_ATTR_DISABLE_PREPARES => true,
            ];
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
        }

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die('Erreur de connexion BDD : ' . htmlspecialchars($e->getMessage()));
            }
            die('Erreur de connexion à la base de données.');
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }

    public function getDriver(): string {
        return $this->driver;
    }

    /**
     * Exécute un INSERT et retourne l'identifiant généré de la ligne créée.
     * - PostgreSQL : ajoute "RETURNING <idColumn>" à la requête
     * - MySQL : utilise lastInsertId()
     */
    public function insertAndGetId(string $sql, array $params = [], string $idColumn = 'id'): int {
        if ($this->driver === 'pgsql') {
            $stmt = $this->pdo->prepare($sql . ' RETURNING ' . $idColumn);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // Empêche le clonage
    private function __clone() {}
}

