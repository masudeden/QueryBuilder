<?php

class QueryBuilder
{
    private static $pdo;
    private static $table;
    private $columns = ['*'];

    public function __construct()
    {
        // Ensure a single database connection throughout the class
        if (!isset(self::$pdo)) {
            $dbConfig = require 'dbconfig.php';
            $host = $dbConfig['host'];
            $dbname = $dbConfig['dbname'];
            $dsn = "mysql:host=$host;dbname=$dbname";
            $username = $dbConfig['username'];
            $password = $dbConfig['password'];

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
    }

    public function __call($method, $args)
    {
        throw new BadMethodCallException("Method $method does not exist.");
    }

    public static function __callStatic($method, $args)
    {
        if ($method === 'table') {
            self::$table = $args[0];
            return new self();
        }

        throw new BadMethodCallException("Method $method does not exist.");
    }

    public function all()
    {
        // Ensure a table name is set
        if (empty(self::$table)) {
            throw new RuntimeException("Table name is not set.");
        }

        // Build and execute the query
        $sql = "SELECT " . implode(', ', $this->columns) . " FROM " . self::$table;
        $statement = self::$pdo->query($sql);

        // Return all rows
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function select($columns)
    {
        // Ensure a table name is set
        if (empty(self::$table)) {
            throw new RuntimeException("Table name is not set.");
        }

        // Validate the columns to prevent SQL injection
        foreach ($columns as $column) {
            if (!is_string($column) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
                throw new InvalidArgumentException("Invalid column name: $column");
            }
        }

        // Set the columns to be selected
        $this->columns = $columns;

        // Return an instance of this class for method chaining
        return $this;
    }
}

// Usage example
try {
    // Set the table name and create an instance of the QueryBuilder class with the database configuration
    //$users = QueryBuilder::table("users")->select(['name', 'email'])->all();
    //$users = QueryBuilder::table("users")->all();
    //print_r($users);

    // Retrieve all columns and all rows of the previously selected table
/*  
    $users = QueryBuilder::table("users");   
    $allUsers = $users->all();
    print_r($allUsers); */

    // Retrieve all rows in the specified columns of the previously selected table
/*     $selectedColumns = $users->select(['name', 'email'])->all();
    print_r($selectedColumns); */

   // echo QueryBuilder::tables('test'); //to check unknow static method
   // echo QueryBuilder::table("users")->all(); //to check unknown non-static method

} catch (RuntimeException $e) {
    echo 'Error: ' . $e->getMessage();
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
