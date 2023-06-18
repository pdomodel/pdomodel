<?php

namespace PdoModel;

use PDOStatement;

class PdoStatementFetcher
{
    protected PDOStatement $statement;

    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    public function getFirstRow($fetchStyle = \PDO::FETCH_ASSOC): bool|array
    {
        $result = $this->statement->fetch($fetchStyle);
        return !empty($result) ? $result : false;
    }

    public function getOneValue($columnName)
    {
        return $this->getFirstRow()[$columnName];
    }

    public function getColumnValues($columnNumber = 0): bool|array
    {
        $result = $this->statement->fetchAll(\PDO::FETCH_COLUMN, $columnNumber);
        return !empty($result) ? $result : false;
    }

    public function getAllRows($fetchStyle = \PDO::FETCH_ASSOC): bool|array
    {
        $result = $this->statement->fetchAll($fetchStyle);
        return !empty($result) ? $result : false;
    }
}
