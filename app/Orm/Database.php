<?php

namespace App\Orm;

use PDO;
use PDOException;
use RuntimeException;
class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $host     = env('DB_HOST', '127.0.0.1');
        $port     = env('DB_PORT', '3306');
        $dbname   = env('DB_DATABASE', 'orm_db');
        $username = env('DB_USERNAME', 'root');
        $password = env('DB_PASSWORD', '');
        $charset  = env('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Veritabanı bağlantısı kurulamadı: " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
    public static function reset(): void
    {
        self::$instance = null;
    }
    private function __clone() {}
}
