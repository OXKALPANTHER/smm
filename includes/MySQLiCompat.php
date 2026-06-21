<?php
/**
 * MySQLi Compatibility Wrapper for PDO
 * Allows existing mysqli code to work with PDO connections
 */

class MySQLiCompatibility {
    private $pdo;
    public $error = '';
    public $errno = 0;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Execute a query (mimics mysqli->query)
     */
    public function query($sql) {
        try {
            $result = $this->pdo->query($sql);
            return new MySQLiCompatibleResult($result);
        } catch (Exception $e) {
            error_log("Query error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Prepare a statement (mimics mysqli->prepare)
     */
    public function prepare($sql) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $compat = new MySQLiCompatibleStatement($stmt);
            // Store reference to parent for error access
            $compat->parent = $this;
            return $compat;
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            error_log("Prepare error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get affected rows
     */
    public function affected_rows() {
        return $this->pdo->lastRowCount ?? 0;
    }
    
    /**
     * Begin transaction
     */
    public function begin_transaction() {
        $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        $this->pdo->rollBack();
    }
    
    /**
     * Escape string
     */
    public function real_escape_string($string) {
        return addslashes($string);
    }
    
    /**
     * Get last insert id (driver-aware: Postgres needs lastval()).
     */
    public function insert_id() {
        try {
            $id = $this->pdo->lastInsertId();
            if ($id) return $id;
        } catch (Exception $e) { /* fall through */ }

        try {
            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                return $this->pdo->query('SELECT lastval()')->fetchColumn();
            }
        } catch (Exception $e) { /* ignore */ }

        return 0;
    }
    
    /**
     * Passthrough to underlying PDO for raw access
     */
    public function __call($name, $args) {
        if (method_exists($this->pdo, $name)) {
            return call_user_func_array([$this->pdo, $name], $args);
        }
        throw new Exception("Method $name not found");
    }
}

/**
 * MySQLi Compatible Result
 */
class MySQLiCompatibleResult {
    private $result;
    private $rows = [];
    private $position = 0;
    
    public function __construct($result) {
        $this->result = $result;
        
        // Cache all rows
        if ($result) {
            $this->rows = $result->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    /**
     * Fetch one row as associative array
     */
    public function fetch_assoc() {
        if ($this->position < count($this->rows)) {
            return $this->rows[$this->position++];
        }
        return null;
    }
    
    /**
     * Fetch all rows as associative array
     */
    public function fetch_all($type = null) {
        return $this->rows;
    }
    
    /**
     * Get number of rows
     */
    public function num_rows() {
        return count($this->rows);
    }
    
    /**
     * Reset row pointer
     */
    public function data_seek($offset) {
        if ($offset >= 0 && $offset < count($this->rows)) {
            $this->position = $offset;
        }
    }
}

/**
 * MySQLi Compatible Statement
 */
class MySQLiCompatibleStatement {
    private $stmt;
    private $lastResult;
    private $types = '';
    private $bindings = [];
    public $error = '';
    public $errno = 0;
    public $parent = null;
    
    public function __construct($stmt) {
        $this->stmt = $stmt;
    }
    
    /**
     * Bind parameters (mimics bind_param)
     */
    public function bind_param($types, &...$vars) {
        $this->types = $types;
        $this->bindings = [];
        
        // For PDO, we need to bind using 1-based numeric indices for ? placeholders
        $typeArray = str_split($types);
        foreach ($vars as $i => $var) {
            // PDO uses 1-based indices for positional parameters
            $this->bindings[$i + 1] = &$vars[$i];
        }
        
        return true;
    }
    
    /**
     * Execute statement
     */
    public function execute() {
        try {
            // Build parameter array from bindings
            // PDO with ? placeholders expects 0-based array indices
            $params = [];
            for ($i = 1; $i <= count($this->bindings); $i++) {
                if (isset($this->bindings[$i])) {
                    $params[$i - 1] = $this->bindings[$i]; // Convert to 0-based
                }
            }
            
            $result = $this->stmt->execute($params);
            
            // Store affected rows
            $this->stmt->rowCount();
            
            return $result;
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            // Also propagate error to parent connection object
            if ($this->parent) {
                $this->parent->error = $e->getMessage();
            }
            error_log("Execute error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get result set
     */
    public function get_result() {
        $this->stmt->setFetchMode(PDO::FETCH_ASSOC);
        return new MySQLiCompatibleResult($this->stmt);
    }
    
    /**
     * Fetch single row
     */
    public function fetch() {
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get number of rows
     */
    public function num_rows() {
        return $this->stmt->rowCount();
    }
}

?>
