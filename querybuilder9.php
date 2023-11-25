<?php

class Database
{
    private static $pdo;

    public static function connect()
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

        return self::$pdo;
    }

    public static function table($tableName)
    {
        return new QueryBuilder(self::connect(), $tableName);
    }
}


class QueryBuilder
{
    private $pdo;
    private $table;
    private $where = [];
    private $select = [];
    private $limit;
    

    public function __construct(PDO $pdo, $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function where($column, $operator, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = compact('column', 'operator', 'value');
        return $this;
    }

    public function select(...$columns)
    {
        $this->select = $columns;
        return $this;
    }

    public function find($id)
    {
        $this->where('id', $id);
        $result = $this->get();

        return !empty($result) ? $result[0] : null;
    }

    public function first()
    {
        $result = $this->limit(1)->get();

        return !empty($result) ? $result[0] : null;
    }

    public function value($column)
    {
        $this->select($column);
        $result = $this->limit(1)->get();

        return isset($result[0][$column]) ? $result[0][$column] : null;
    }

    public function count()
    {
        $this->select('COUNT(*) as count');
        $result = $this->limit(1)->get();

        return isset($result[0]['count']) ? (int) $result[0]['count'] : 0;
    }

    public function max($column)
    {
        $this->select("MAX($column) as max");
        $result = $this->limit(1)->get();

        return isset($result[0]['max']) ? $result[0]['max'] : null;
    }

    public function avg($column)
    {
        $this->select("AVG($column) as avg");
        $result = $this->limit(1)->get();

        return isset($result[0]['avg']) ? $result[0]['avg'] : null;
    }

    public function limit($count)
    {
        $this->limit = $count;
        return $this;
    }

    public function get()
    {
        $sql = "SELECT ";

        // Add selected columns
        if (!empty($this->select)) {
            $sql .= implode(', ', $this->select);
        } else {
            $sql .= '*';
        }

        $sql .= " FROM {$this->table}";
        $params = [];

        if (!empty($this->where)) {
            $sql .= ' WHERE';
            foreach ($this->where as $condition) {
                $sql .= " {$condition['column']} {$condition['operator']} :{$condition['column']} AND";
                $params[":{$condition['column']}"] = $condition['value'];
            }
            $sql = rtrim($sql, ' AND');

            // Reset $where after conditions are used
            $this->where = [];
        }

        if (isset($this->limit)) {
            $sql .= " LIMIT {$this->limit}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert($data)
    {
        if (!is_array(reset($data))) {
            $data = [$data];
        }

        $columns = array_keys($data[0]);

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES ";

        $params = [];
        $values = [];

        foreach ($data as $row) {
            $placeholders = [];
            foreach ($row as $column => $value) {
                $param = ':' . $column . '_' . count($params);
                $params[$param] = $value;
                $placeholders[] = $param;
            }
            $values[] = '(' . implode(', ', $placeholders) . ')';
        }

        $sql .= implode(', ', $values);

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }

        $stmt->execute();

        return $stmt->rowCount();
    }

    public function insertGetId($data)
    {
        if (!is_array(reset($data))) {
            $data = [$data];
        }

        $columns = array_keys($data[0]);

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES ";

        $params = [];
        $values = [];

        foreach ($data as $row) {
            $placeholders = [];
            foreach ($row as $column => $value) {
                $param = ':' . $column . '_' . count($params);
                $params[$param] = $value;
                $placeholders[] = $param;
            }
            $values[] = '(' . implode(', ', $placeholders) . ')';
        }

        $sql .= implode(', ', $values);

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }

        $stmt->execute();

        return $this->pdo->lastInsertId();
    }

    public function upsert($values, $uniqueKeys, $updateColumns)
    {
        if (!is_array(reset($values))) {
            $values = [$values];
        }

        $columns = array_keys($values[0]);

        // Build the INSERT part of the query
        $insertSql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES ";

        $insertParams = [];
        $insertValues = [];

        foreach ($values as $row) {
            $placeholders = [];
            foreach ($row as $column => $value) {
                $param = ':' . $column . '_' . count($insertParams);
                $insertParams[$param] = $value;
                $placeholders[] = $param;
            }
            $insertValues[] = '(' . implode(', ', $placeholders) . ')';
        }

        $insertSql .= implode(', ', $insertValues);

        // Build the ON DUPLICATE KEY UPDATE part of the query
        $updateSql = " ON DUPLICATE KEY UPDATE ";

        foreach ($updateColumns as $column) {
            $param = ':' . $column . '_update';
            $updateSql .= "$column = VALUES($column), ";
        }

        $updateSql = rtrim($updateSql, ', ');

        // Combine the INSERT and UPDATE parts of the query
        $sql = $insertSql . $updateSql;

        // Execute the query
        $stmt = $this->pdo->prepare($sql);

        foreach ($insertParams as $param => $value) {
            $stmt->bindValue($param, $value);
        }

        $stmt->execute();
    }

    public function update($data)
    {
        if (empty($this->where)) {
            throw new RuntimeException('Update query must have a WHERE clause.');
        }

        $updateColumns = [];

        foreach ($data as $column => $value) {
            $updateColumns[] = "$column = :$column";
        }

        $updateColumns = implode(', ', $updateColumns);

        $whereClause = $this->buildWhereClause();

        $query = "UPDATE {$this->table} SET $updateColumns $whereClause";

        $stmt = $this->pdo->prepare($query);

        $this->bindValues($stmt, $data);
        $this->bindWhereValues($stmt);

        $stmt->execute();

        return $stmt->rowCount();
    }

    private function buildWhereClause()
    {
        if (!empty($this->where)) {
            $whereClauses = [];

            foreach ($this->where as $condition) {
                $whereClauses[] = "{$condition['column']} {$condition['operator']} :{$condition['column']}";
            }

            return 'WHERE ' . implode(' AND ', $whereClauses);
        }

        return '';
    }

    private function bindValues($stmt, $data)
    {
        foreach ($data as $column => $value) {
            $stmt->bindValue(":$column", $value, $this->getDataType($value));
        }
    }

    private function bindWhereValues($stmt)
    {
        foreach ($this->where as $condition) {
            $stmt->bindValue(":{$condition['column']}", $condition['value'], $this->getDataType($condition['value']));
        }
    }

    private function getDataType($value)
    {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            return PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            return PDO::PARAM_NULL;
        } else {
            return PDO::PARAM_STR;
        }
    }

    public function delete()
    {
        $sql = "DELETE FROM {$this->table}";

        if (!empty($this->where)) {
            $whereClause = implode(" AND ", array_map(fn($condition) => "$condition[column] $condition[operator] :$condition[column]", $this->where));
            $sql .= " WHERE " . $whereClause;
        }

        // Prepare and execute the query
        $stmt = $this->pdo->prepare($sql);

        // Bind parameters for the WHERE clause
        foreach ($this->where as $condition) {
            $stmt->bindValue(":$condition[column]", $condition['value']);
        }

        // Execute the query
        $stmt->execute();

        // Clear the where conditions for the next query
        $this->where = [];

        // Return the number of deleted rows
        return $stmt->rowCount();
    }

    public static function __callStatic($method, $args)
    {
        throw new BadMethodCallException("Method {$method} does not exist");
    }

    public function __call($method, $args)
    {
        throw new BadMethodCallException("Method {$method} does not exist");
    }
}

// Example Usage
$users = Database::table('users');

/* $filteredRowsById1 = $users->where('id', 2)->get();
print_r($filteredRowsById1); */

/* $filteredRowsById2 = $users->where('id', '=', 2)->get();
print_r($filteredRowsById2); */

/* $filteredRowsById3 = $users->where('id', '>', 2)->get();
print_r($filteredRowsById3); */

/* $filteredRowsById4 = $users->where('status', '=', 'active')->get(); 
print_r($filteredRowsById4); */


// Retrieve all rows without any conditions
/* $allRows = $users->get();
print_r($allRows); */

// Retrieve selected columns
/* $allSelectedRows = $users->select('name', 'email')->get();
print_r($allSelectedRows); */

// Find a specific row by ID
/* $user = $users->find(3);
print_r($user); */

// Retrieve the first result of a query
/* $firstUser = $users->where('name', 'John Doe')->first();
print_r($firstUser); */

// Retrieve a specific value from the first result of a query
/* $email = $users->where('name', 'John Doe')->value('email');
echo $email ?? 'Email not found'; */


// Count the number of rows in the 'users' table
/* $userCount = $users->count();
echo $userCount; */

// Find the maximum price in the 'orders' table
/* $maxAge = $users->max('age');
echo $maxAge; */

// Retrieve the average age in the 'users' table
/* $avgAge = $users->avg('age');
echo $avgAge; */


/* $usersWithcondition=$users->where('status', '=', 'active')
    ->where('age', '>', 20)
    ->get();

print_r($usersWithcondition); */

/* Database::table('users')->insert([
    'name' => 'Kayla Amela',
    'email' => 'kayla@example.com',
    'age' => 30,
    'status' => 'active',
]); */

/* $affected_rows=Database::table('users')->insert([
    ['name' => 'Poga Arnald', 'email' => 'picard@example.com', 'age' => 24, 'status' => 'active'],
    ['name' => 'Karma Rumor', 'email' => 'janeway@example.com', 'age' => 25, 'status'=> 'inactive'],
]); 
echo "Total row Inserted:". $affected_rows;
*/

/* $id = Database::table('users')->insertGetId([
    'name' => 'Sumona Pinta',
    'email' => 'john@example.com',
    'age' => 26,
    'status' =>'active'
]);

echo "Inserted ID: $id\n"; */
/* 
// Call the 'table' method on the 'Database' class to get a 'QueryBuilder' instance for the 'flights' table
Database::table('flights')

    // Call the 'upsert' method on the 'QueryBuilder' instance
    ->upsert(

        // Array of values to insert or update in the 'flights' table
        [
            ['departure' => 'Oakland', 'destination' => 'San Diego', 'price' => 129],
            ['departure' => 'Chicago', 'destination' => 'New York', 'price' => 135]
        ],

        // Array of column(s) that uniquely identify records within the 'flights' table
        ['departure', 'destination'],

        // Array of columns to be updated if a matching record already exists in the 'flights' table
        ['price']
    ); */

/*     $affected = Database::table('users')
    ->where('id', 1)
    ->update(['age' => 25]);

    echo $affected; */

    //$deletedAll = Database::table('users')->delete();

    //$deletedWithCondition = Database::table('users')->where('age', '=', 23)->delete();
    