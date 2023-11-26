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
    private $orWhere = [];
    private $whereBetween = [];
    private $whereNotBetween = [];
    private $whereBetweenColumns = [];
    private $whereNotBetweenColumns = [];
    private $whereIn = [];
    private $whereNotIn = [];
    private $whereNull = [];
    private $whereNotNull = [];
    private $whereDate = [];
    private $whereMonth = [];
    private $whereDay = [];
    private $whereYear = [];
    private $whereTime = [];
    private $whereColumn = [];
    private $select = [];
    private $orderBy = [];
    private $limit;
    private $offset;
    private $latestColumn;
    private $inRandomOrder;
    private $groupBy = [];
    private $having = [];


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
    public function orWhere($column, $operator, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->orWhere[] = compact('column', 'operator', 'value');
        return $this;
    }
    public function whereBetween($column, array $values)
    {
        $this->whereBetween[] = compact('column', 'values');
        return $this;
    }
    public function whereNotBetween($column, array $values)
    {
        $this->whereNotBetween[] = compact('column', 'values');
        return $this;
    }
    public function whereBetweenColumns($column, array $rangeColumns)
    {
        $this->whereBetweenColumns[] = compact('column', 'rangeColumns');
        return $this;
    }
    public function whereNotBetweenColumns($column, array $rangeColumns)
    {
        $this->whereNotBetweenColumns[] = compact('column', 'rangeColumns');
        return $this;
    }
    public function whereIn($column, array $values)
    {
        $this->whereIn[] = compact('column', 'values');
        return $this;
    }
    public function whereNotIn($column, array $values)
    {
        $this->whereNotIn[] = compact('column', 'values');
        return $this;
    }
    public function whereNull($column)
    {
        $this->whereNull[] = $column;
        return $this;
    }
    public function whereNotNull($column)
    {
        $this->whereNotNull[] = $column;
        return $this;
    }
    public function whereDate($column, $date)
    {
        $this->whereDate[] = compact('column', 'date');
        return $this;
    }
    public function whereMonth($column, $month)
    {
        $this->whereMonth[] = compact('column', 'month');
        return $this;
    }
    public function whereDay($column, $day)
    {
        $this->whereDay[] = compact('column', 'day');
        return $this;
    }
    public function whereYear($column, $year)
    {
        $this->whereYear[] = compact('column', 'year');
        return $this;
    }
    public function whereTime($column, $operator, $time)
    {
        $this->whereTime[] = compact('column', 'operator', 'time');
        return $this;
    }
    public function whereColumn($firstColumn, $operatorOrSecondColumn, $secondColumn = null)
    {
        if ($secondColumn === null) {
            $operator = '=';
            $secondColumn = $operatorOrSecondColumn;
        } else {
            $operator = $operatorOrSecondColumn;
        }

        $this->whereColumn[] = compact('firstColumn', 'operator', 'secondColumn');
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

    public function latest($column = 'created_at')
    {
        $this->orderBy($column, 'desc');
        $this->latestColumn = $column;
        return $this;
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

    public function orderBy($column, $direction = 'asc')
    {
        $this->orderBy[] = compact('column', 'direction');
        return $this;
    }

    public function inRandomOrder()
    {
        $this->inRandomOrder = true;
        return $this;
    }


    public function limit($count)
    {
        $this->limit = $count;
        return $this;
    }

    public function skip($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    public function take($count)
    {
        $this->limit($count);
        return $this;
    }

    public function groupBy(...$columns)
    {
        $this->groupBy = array_merge($this->groupBy, $columns);
        return $this;
    }

    public function having($column, $operator, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->having[] = compact('column', 'operator', 'value');
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

        if (!empty($this->orWhere)) {
            $sql .= ' OR';
            foreach ($this->orWhere as $condition) {
                $sql .= " {$condition['column']} {$condition['operator']} :{$condition['column']} OR";
                $params[":{$condition['column']}"] = $condition['value'];
            }
            $sql = rtrim($sql, ' OR');

            // Reset $where after conditions are used
            $this->orWhere = [];
        }

        if (!empty($this->whereBetween)) {
            $sql .= ' WHERE';
            foreach ($this->whereBetween as $condition) {
                $sql .= " {$condition['column']} BETWEEN :{$condition['column']}_start AND :{$condition['column']}_end AND";
                $params[":{$condition['column']}_start"] = $condition['values'][0];
                $params[":{$condition['column']}_end"] = $condition['values'][1];
            }
            $sql = rtrim($sql, ' AND');
        }

        if (!empty($this->whereNotBetween)) {
            $sql .= ' WHERE';
            foreach ($this->whereNotBetween as $condition) {
                $sql .= " {$condition['column']} NOT BETWEEN :{$condition['column']}_start AND :{$condition['column']}_end AND";
                $params[":{$condition['column']}_start"] = $condition['values'][0];
                $params[":{$condition['column']}_end"] = $condition['values'][1];
            }
            $sql = rtrim($sql, ' AND');
        }

        if (!empty($this->whereBetweenColumns)) {
            $sql .= ' WHERE';
            foreach ($this->whereBetweenColumns as $condition) {
                $sql .= " {$condition['column']} BETWEEN {$condition['rangeColumns'][0]} AND {$condition['rangeColumns'][1]} AND";
            }
            $sql = rtrim($sql, ' AND');
        }

        if (!empty($this->whereNotBetweenColumns)) {
            $sql .= ' WHERE';
            foreach ($this->whereNotBetweenColumns as $condition) {
                $sql .= " {$condition['column']} NOT BETWEEN {$condition['rangeColumns'][0]} AND {$condition['rangeColumns'][1]} AND";
            }
            $sql = rtrim($sql, ' AND');
        }

        if (!empty($this->whereIn)) {
            $sql .= ' WHERE';
            foreach ($this->whereIn as $condition) {
                $sql .= " {$condition['column']} IN (:" . implode(', :', $condition['values']) . ") AND";
                foreach ($condition['values'] as $value) {
                    $params[":$value"] = $value;
                }
            }
            $sql = rtrim($sql, ' AND');
        }

        if (!empty($this->whereNotIn)) {
            $sql .= ' WHERE';
            foreach ($this->whereNotIn as $condition) {
                $sql .= " {$condition['column']} NOT IN (:" . implode(', :', $condition['values']) . ") AND";
                foreach ($condition['values'] as $value) {
                    $params[":$value"] = $value;
                }
            }
            $sql = rtrim($sql, ' AND');
        }

        if (!empty($this->whereNull)) {
            $sql .= ' WHERE';
            foreach ($this->whereNull as $column) {
                $sql .= " {$column} IS NULL AND";
            }
            $sql = rtrim($sql, ' AND');
        }

        if (!empty($this->whereNotNull)) {
            $sql .= ' WHERE';
            foreach ($this->whereNotNull as $column) {
                $sql .= " {$column} IS NOT NULL AND";
            }
            $sql = rtrim($sql, ' AND');
        }

        if (!empty($this->whereDate)) {
            $sql .= ' WHERE';
            foreach ($this->whereDate as $condition) {
                $sql .= " DATE({$condition['column']}) = '{$condition['date']}' AND";
            }
            $sql = rtrim($sql, ' AND');
        }

        if (!empty($this->whereMonth)) {
            $sql .= ' WHERE';
            foreach ($this->whereMonth as $condition) {
                $sql .= " MONTH({$condition['column']}) = '{$condition['month']}' AND";
            }
            $sql = rtrim($sql, ' AND');
        }

        if (!empty($this->whereDay)) {
            $sql .= ' WHERE';
            foreach ($this->whereDay as $condition) {
                $sql .= " DAY({$condition['column']}) = '{$condition['day']}' AND";
            }
            $sql = rtrim($sql, ' AND');
        }

        if (!empty($this->whereYear)) {
            $sql .= ' WHERE';
            foreach ($this->whereYear as $condition) {
                $sql .= " YEAR({$condition['column']}) = '{$condition['year']}' AND";
            }
            $sql = rtrim($sql, ' AND');
        }

        if (!empty($this->whereTime)) {
            $sql .= ' WHERE';
            foreach ($this->whereTime as $condition) {
                $sql .= " TIME({$condition['column']}) {$condition['operator']} '{$condition['time']}' AND";
            }
            $sql = rtrim($sql, ' AND');
        }

        if (!empty($this->whereColumn)) {
            $sql .= ' WHERE';
            foreach ($this->whereColumn as $condition) {
                $sql .= " {$condition['firstColumn']} {$condition['operator']} {$condition['secondColumn']} AND";
            }
            $sql = rtrim($sql, ' AND');
        }


        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ';
            foreach ($this->orderBy as $order) {
                $sql .= "{$order['column']} {$order['direction']}, ";
            }
            $sql = rtrim($sql, ', ');
        }

        if ($this->inRandomOrder) {
            $sql .= ' ORDER BY RAND()';
        } elseif (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ';
            foreach ($this->orderBy as $order) {
                $sql .= "{$order['column']} {$order['direction']}, ";
            }
            $sql = rtrim($sql, ', ');
        }
    
        if (isset($this->limit)) {
            $sql .= " LIMIT {$this->limit}";
        }
    
        if (isset($this->offset)) {
            $sql .= " OFFSET {$this->offset}";
        }

        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        if (!empty($this->having)) {
            $sql .= ' HAVING';
            foreach ($this->having as $condition) {
                $sql .= " {$condition['column']} {$condition['operator']} :{$condition['column']} AND";
                $params[":{$condition['column']}"] = $condition['value'];
            }
            $sql = rtrim($sql, ' AND');
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
/* 
$filteredRowsById5 = $users->where('age', '>', 25)
                    ->where('status', 'active')
                    ->get();

print_r($filteredRowsById5); */


/* $filteredRowsById6 = $users->where('age', '>', 25)
                    ->orWhere('status', 'active')
                    ->get();

print_r($filteredRowsById6); */

/* $filteredRowsById7 = $users
                    ->whereBetween('age', [25, 30])
                    ->get();

print_r($filteredRowsById7); */

/* $filteredRowsById8 = $users
    ->whereNotBetween('age', [24, 26])
    ->get();

print_r($filteredRowsById8); */

/* $patients = Database::table('patients')
    ->whereBetweenColumns('weight', ['minimum_allowed_weight', 'maximum_allowed_weight'])
    ->get();

print_r($patients); */

/* $patients = Database::table('patients')
    ->whereNotBetweenColumns('weight', ['minimum_allowed_weight', 'maximum_allowed_weight'])
    ->get();

print_r($patients); */

/* $filteredRowsById9 = Database::table('patients')
    ->whereIn('id', [1, 3, 8, 10])
    ->get();

print_r($filteredRowsById9); */

/* $filteredRowsById10 = $users
    ->whereNotIn('id', [1, 2, 3])
    ->get();

print_r($filteredRowsById10); */


/* $whereNull=$users
    ->whereNull('email')
    ->get();
print_r($whereNull); */

/* $whereNotNull = $users
    ->whereNotNull('email')
    ->get();

print_r($whereNotNull); */

/* $whereDate = $users
    ->whereDate('created_at', '2016-12-31')
    ->get();

print_r($whereDate); */

/* $whereMonth = $users
    ->whereMonth('created_at', '12')
    ->get();

print_r($whereMonth); */

/* $whereDay = $users
    ->whereDay('created_at', '31')
    ->get();
print_r($whereDay); */

/* $whereYear = $users
    ->whereYear('created_at', '2016')
    ->get();

print_r($whereYear); */

/* $whereTime = $users
    ->whereTime('created_at', '=', '12:15:24')
    ->get();

print_r($whereTime); */

/* $whereColumn = $users
    ->whereColumn('created_at', 'updated_at')
    ->get();

print_r($whereColumn); */

/* $whereColumn = $users
->whereColumn('created_at', '>', 'updated_at')
->get();

print_r($whereColumn);  */

// Retrieve all rows without any conditions
/* $allRows = $users->get();
print_r($allRows); */

//Retrive all rows without any conditions and with orderBy ASC
/* $results = $users->orderBy('name')->get();
print_r($results); */

//Retrive all rows with orderBy DESC
/* $orderByDESC=$users->orderBy('name', 'desc')
                ->get();
print_r($orderByDESC); */

/* $orderByMultiColumn=$users
                ->orderBy('name', 'desc')
                ->orderBy('age', 'asc')
                ->get();

print_r($orderByMultiColumn);
 */

 // Retrieve the first 3 users
/* $limitedUsers = $users->limit(3)->get();
print_r($limitedUsers); */

// Retrieve users with a where condition and a limit of 10
/* $filteredLimitedUsers = $users->where('age', '>', 22)->limit(2)->get();
print_r($filteredLimitedUsers); */

// Skip the first 10 users and take the next 5
/* $limitedUsersWithSkip=$users->skip(2)->take(3)->get();
print_r($limitedUsersWithSkip);
 */

 // Retrieve a user with randomly
 /* $randomUser = $users->inRandomOrder()->first();
 print_r($randomUser);
 */

 // Retrieve a user with having and groupby
 /* $users=$users->groupBy('age')
    ->having('age', '>', 22)
    ->get();
print_r($users); */


// Retrieve selected columns
/* $allSelectedRows = $users->select('name', 'email')->get();
print_r($allSelectedRows); */

// Find a specific row by ID
/* $user = $users->find(3);
print_r($user); */

// Retrieve the first result of a query
/* $firstUser = $users->where('name', 'John Doe')->first();
print_r($firstUser); */

// Retrieve the latest first result of a query
/* $latest = $users->latest()->first();

print_r($latest);
 */

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
    
