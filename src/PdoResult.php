<?php

namespace PdoModel;

use \PDOStatement;

/**
 * @property \PDOStatement statement
 */
class PdoResult
{
    /**
     * @var PDOStatement
     */
    protected $statement;

    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    /**
     * Fetch the first row from a result set
     * @param int $fetchStyle
     * @return mixed
     * @see \PDOStatement::fetch()
     */
    public function first($fetchStyle = \PDO::FETCH_ASSOC)
    {
        return $this->statement->fetch($fetchStyle);
    }

    /**
     * Fetch the value of first row from a result set
     * @param $columnName
     * @return mixed
     * @see \PDOStatement::fetch()
     */
    public function value($columnName)
    {
        return $this->first()[$columnName];
    }

    public function column($columnNumber = 0)
    {
        return $this->statement->fetchAll(\PDO::FETCH_COLUMN, $columnNumber);
    }

    public function all($fetchStyle = \PDO::FETCH_ASSOC)
    {
        return $this->statement->fetchAll($fetchStyle);
    }
}