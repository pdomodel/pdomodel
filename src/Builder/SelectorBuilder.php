<?php

namespace PdoModel\Builder;

use PDO;
use PdoModel\PdoModelException;
use PDOStatement;

class SelectorBuilder
{
    protected string $tableName;
    protected ?PDO $connection = null;
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected ?string $orderBy = null;
    protected ?string $groupBy = null;

    protected array $whereParts = [];
    protected array $preparedParameterValues = [];

    protected string $columns = '*';

    public function __construct(string $tableName, PDO $connection)
    {
        $this->tableName = $tableName;
        $this->connection = $connection;
    }

    public function where(string $column, string $operand, string $value = null): static
    {
        $operand = trim(strtoupper($operand));
        if (!in_array($operand, ['>', '<', '=', '!=', '>=', '<=', 'LIKE', 'NOT LIKE', 'IS', 'IS NOT'])) {
            throw new PdoModelException('Wrong SQL operand ' . $operand);
        }
        $this->whereParts[] = "$column $operand ?";
        $this->preparedParameterValues[] = $value;
        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        $this->whereParts[] = "$column IN (" . implode(',', $values) . ")";
        return $this;
    }

    public function whereNotIn(string $column, array $values): static
    {
        $this->whereParts[] = "$column NOT IN (" . implode(',', $values) . ")";
        return $this;
    }

    public function whereIsNull(string $column): static
    {
        $this->whereParts[] = "$column IS NULL";
        return $this;
    }

    public function whereIsNotNull(string $column): static
    {
        $this->whereParts[] = "$column IS NOT NULL";
        return $this;
    }

    public function whereEqual(string $column, string $value): static
    {
        $this->whereParts[] = "$column = ?";
        $this->preparedParameterValues[] = $value;
        return $this;
    }

    public function groupBy(string $value): static
    {
        $this->groupBy = $value;
        return $this;
    }

    public function orderBy(string $value): static
    {
        $this->orderBy = $value;
        return $this;
    }

    public function limit(int $value): static
    {
        $this->limit = $value;
        return $this;
    }

    public function offset(int $value): static
    {
        $this->offset = $value;
        return $this;
    }

    public function columns(array|string $columns): static
    {
        if (is_array($columns)) {
            if (!array_is_list($columns)) {
                throw new PdoModelException('Wrong column names structure ' . json_encode($columns));
            }
            $columns = implode(',', $columns);
        }
        $this->columns = $columns;
        return $this;
    }

    public function buildSql(): string
    {
        $sql = "SELECT $this->columns FROM $this->tableName ";
        if (!empty($this->whereParts)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->whereParts) . ' ';
        }
        if ($this->groupBy !== null) {
            $sql .= " GROUP BY " . $this->groupBy;
        }
        if ($this->orderBy !== null) {
            $sql .= " ORDER BY " . $this->orderBy;
        }
        if ($this->offset !== null && $this->limit !== null) {
            $sql .= " LIMIT $this->offset , $this->limit ";
        } elseif ($this->limit !== null) {
            $sql .= " LIMIT " . $this->limit;
        }
        return $sql;
    }

    protected function execute(): PDOStatement
    {
        $sth = $this->connection->prepare($this->buildSql());
        if (!$sth) {
            throw new PdoModelException('Error in creating STH ' . $sth->errorInfo()[2]);
        }
        $succeed = $sth->execute($this->preparedParameterValues);
        if (!$succeed) {
            throw new PdoModelException($sth->errorInfo()[2]);
        }
        return $sth;
    }

    public function getFirstRow($fetchStyle = \PDO::FETCH_ASSOC): bool|array
    {
        return $this->execute()->fetch($fetchStyle);
    }

    public function getOneValue($columnName)
    {
        return $this->getFirstRow()[$columnName];
    }

    public function getColumnValues($columnNumber = 0): bool|array
    {
        return $this->execute()->fetchAll(\PDO::FETCH_COLUMN, $columnNumber);
    }

    public function getAllRows($fetchStyle = \PDO::FETCH_ASSOC): bool|array
    {
        return $this->execute()->fetchAll($fetchStyle);
    }

}
