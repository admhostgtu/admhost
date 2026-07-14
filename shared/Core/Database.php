<?php
/**
 * Singleton PDO pour la connexion à la base de données MySQL.
 */

declare(strict_types=1);

namespace Shared\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $name = env('DB_NAME', 'admhost');
            $user = db_user();
            $pass = db_password();

            $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                throw new PDOException(
                    'Erreur de connexion BDD (' . $user . '@' . $host . '/' . $name . ') : ' . $e->getMessage()
                );
            }
        }

        return self::$instance;
    }

    /** Réinitialise la connexion (tests / reconnexion). */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
