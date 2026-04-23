<?php

require_once 'config.php';

class Database {
    private static $pdo = null;

    public static function getConnection() {
        if (self::$pdo == null) {
            $dbPath = Config::DB_PATH;
            $createDb = !file_exists($dbPath);

            self::$pdo = new PDO("sqlite:$dbPath");
            // Выбрасываем исключения при ошибках
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Проверка ключей для sqlite
            self::$pdo->exec("PRAGMA foreign_keys = ON;");

            if ($createDb) {
                self::initDatabase();
            }
        }
        return self::$pdo;
    }

    private static function initDatabase() {
        $schema = file_get_contents(Config::SCHEMA_PATH);
        self::$pdo->exec($schema);
    }
}

?>