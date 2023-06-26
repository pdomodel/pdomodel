<?php

namespace PdoModel\Builder;

use PDO;
use PdoModel\PdoModelException;
use PdoModel\PdoStatementFetcher;
use PDOStatement;

class SelectorBuilder
{
    protected string $tableName;
    protected ?PDO $connection = null;
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected ?string $orderBy = null;
    protected ?string $groupBy = null;

    protected ?string $collate = null;

    protected array $whereParts = [];
    protected array $whereOrParts = [];
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

    public function orWhere(string $column, string $operand, string $value = null): static
    {
        $operand = trim(strtoupper($operand));
        if (!in_array($operand, ['>', '<', '=', '!=', '>=', '<=', 'LIKE', 'NOT LIKE', 'IS', 'IS NOT'])) {
            throw new PdoModelException('Wrong SQL operand ' . $operand);
        }
        $this->whereOrParts[] = "$column $operand ?";
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

    public function collate(string $value = 'utf8mb4_0900_ai_ci'): static
    {
        $this->collate = $value;
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
            $sql .= ' WHERE (' . implode(' AND ', $this->whereParts) . ') ';
        }
        if (!empty($this->whereOrParts)) {
            foreach ($this->whereOrParts as $statement) {
                $sql .= ' OR (' . $statement . ') ';
            }
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
        if ($this->collate !== null) {
            $sql .= " COLLATE " . $this->collate;
        }
        return $sql;
    }

    public function raw(string $sql, array $preparedParameterValues = []): PdoStatementFetcher
    {
        $sth = $this->connection->prepare($this->buildSql());
        if (!$sth) {
            throw new PdoModelException('Error in creating STH ' . $sth->errorInfo()[2]);
        }
        $succeed = $sth->execute($preparedParameterValues);
        if (!$succeed) {
            throw new PdoModelException($sth->errorInfo()[2]);
        }
        return new PdoStatementFetcher($sth);
    }

    public function getFirstRow($fetchStyle = \PDO::FETCH_ASSOC): bool|array
    {
        return $this->raw($this->buildSql(), $this->preparedParameterValues)->getFirstRow($fetchStyle);
    }

    public function getFirstValue($columnName = null): bool|int|string
    {
        return $this->raw($this->buildSql(), $this->preparedParameterValues)->getFirstValue($columnName);
    }

    public function getColumn($columnNumber = 0): bool|array
    {
        return $this->raw($this->buildSql(), $this->preparedParameterValues)->getColumn($columnNumber);
    }

    public function getAllRows($fetchStyle = \PDO::FETCH_ASSOC): bool|array
    {
        return $this->raw($this->buildSql(), $this->preparedParameterValues)->getAllRows($fetchStyle);
    }

}
