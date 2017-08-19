<?php

namespace PdoModel;

use \PDOStatement;

/**
 * @property \PDOStatement statement
 */
class PdoResult
{
    protected $statement;

    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    // Returns single row | order?
    public function first()
    {
        return $this->statement->fetch();
    }

    // Returns value of single row | order?
    public function value($columnName)
    {
        return $this->first()[$columnName];
    }

    public function column($columnName, $limit = 0, $order = false)
    {
        return $this->statement->fetchColumn(0);
    }

    public function all()
    {
        return $this->statement->fetchAll();
    }
}