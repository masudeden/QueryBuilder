<?php

class Database
{
    private static $pdo;

    public static function connect()
    {
        // Ensure a single database connection throughout the class
        if (!isset(self::$pdo)) {
            extract(require 'dbconfig.php');
            $dsn = "mysql:host=$host;dbname=$dbname";
            
            try {
                // Create a new PDO instance
                self::$pdo = new PDO($dsn, $username, $password);

                // Set PDO in exception mode
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                // Throw an exception for handling in calling code
                throw new RuntimeException("Database connection error: " . $e->getMessage());
            }
        }

        return self::$pdo;
    }

    public static function table($tableName)
    {
        return new QueryBuilder(self::connect(), $tableName);
    }
}
