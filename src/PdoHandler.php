<?php

namespace PdoModel;

class PdoHandler
{
    private $table;
    private $primaryKey = 'id';
    private $connection;

    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
    }

    public function setTable($tableName)
    {
        $this->table = $tableName;
        return $this;
    }

    public function setPrimaryKey($key){
        $this->primaryKey = $key;
        return $this;
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    public function getTable()
    {
        if (!$this->table) {
            throw new \Exception("Database table name can't be empty");
        }

        return $this->table;
    }

    public function prepare($query, $options = [])
    {
        try {
            $result = $this->connection->prepare($query, $options);
        } catch (\PDOException $e) {
            error_log('Mysql prepare error, ' . 'message: ' . $e->getMessage() . ', table: ' . static::getTable());
            throw $e;
        }
        return $result;
    }

    /**
     * @return int
     */
    public function getLastInsertId($sequenceName = null)
    {
        return $this->connection->lastInsertId($sequenceName);
    }
}