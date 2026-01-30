<?php
class DbConnection {
    public static function connect() {
        $host = 'localhost';
        $port = 3306;
        $db   = 'book';
        $user = 'root';
        $pass = '';
        $charset = 'utf8mb4';

        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            return $pdo;
        } catch (PDOException $e) {
            // Throw exception instead of die() to allow graceful handling
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
}
