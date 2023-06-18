<?php

namespace PdoModel\Builder;

use PDO;
use PdoModel\PdoModel;
use function PHPUnit\Framework\throwException;

class CreateTableBuilder
{
    protected string $tableName;
    protected bool $ifNotExists = true;
    protected array $columns;
    protected ?string $engine = null;
    protected ?PDO $connection = null;

    public function __construct(string $tableName, PDO $connection = null)
    {
        $this->tableName = $tableName;
        if ($connection) {
            $this->connection = $connection;
        }
    }

    public function column(
        string $name,
        string $type = 'int',
        bool   $autoIncrement = false,
        bool   $primaryKey = false,
        bool   $notNull = false,
        bool   $unique = false,
    ): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => $type,
            'AUTO_INCREMENT' => $autoIncrement,
            'PRIMARY KEY' => $primaryKey,
            'NOT NULL' => $notNull,
            'UNIQUE' => $unique,
        ];

        return $this;
    }

    public function ifNotExists(bool $value = true)
    {
        $this->ifNotExists = $value;
        return $this;
    }

    public function setEngine(string $engineName = 'INNODB')
    {
        $this->engine = $engineName;
        return $this;
    }

    public function buildSql(): string
    {
        $sql = "CREATE TABLE ";
        if ($this->ifNotExists) {
            $sql .= 'IF NOT EXISTS ';
        }
        $sql .= $this->tableName;
        $columnStrings = [];
        foreach ($this->columns as $column) {
            $parts = [$column['name']];
            $parts[] = $column['type'];
            foreach ([
                         'AUTO_INCREMENT',
                         'PRIMARY KEY',
                         'NOT NULL',
                         'UNIQUE',
                     ] as $constraint) {
                if ($column[$constraint]) {
                    $parts[] = $constraint;
                }
            }
            $columnStrings[] = join(' ', $parts);
        }
        $sql .= ' (' . join(',', $columnStrings) . ') ';
        if ($this->engine) {
            $sql .= 'ENGINE=' . $this->engine;
        }
        return $sql;
    }

    public function execute(): bool|int
    {
        if (!$this->connection) {
            throw new \Exception('You should pass PDO instance for using this method');
        }
        return $this->connection->exec($this->buildSql());
    }
}
