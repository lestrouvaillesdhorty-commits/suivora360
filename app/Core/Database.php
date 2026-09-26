<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $driver = Env::get('DB_CONNECTION', 'mysql');

        try {
            if ($driver === 'sqlite') {
                $path = Env::get('DB_SQLITE_PATH', 'storage/suivora360.sqlite');
                if (!str_starts_with($path, '/')) {
                    $path = dirname(__DIR__, 2) . '/' . $path;
                }
                $pdo = new PDO('sqlite:' . $path);
                $pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                $host = Env::get('DB_HOST', 'localhost');
                $port = Env::get('DB_PORT', '3306');
                $db = Env::get('DB_DATABASE', '');
                $user = Env::get('DB_USERNAME', '');
                $pass = Env::get('DB_PASSWORD', '');
                $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass);
            }

            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$instance = $pdo;

            return $pdo;
        } catch (PDOException $e) {
            http_response_code(500);
            if (Env::bool('APP_DEBUG', false)) {
                die('Erreur de connexion à la base de données : ' . $e->getMessage());
            }
            die('Impossible de se connecter à la base de données. Vérifiez la configuration dans le fichier .env.');
        }
    }

    public static function driver(): string
    {
        return Env::get('DB_CONNECTION', 'mysql');
    }

    /**
     * Retourne la syntaxe "auto-incrément clé primaire" adaptée au moteur actif.
     */
    public static function idColumnType(): string
    {
        return self::driver() === 'sqlite'
            ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'INT AUTO_INCREMENT PRIMARY KEY';
    }
}
