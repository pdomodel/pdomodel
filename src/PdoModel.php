<?php

namespace PdoModel;

use PdoModel\Builder\CreateTableBuilder;
use PdoModel\Builder\SelectorBuilder;

class PdoModel
{
    const TABLE = '';
    const PRIMARY_KEY = 'id';
    protected \PDO $connection;
    const MAX_PREPARED_STMT_COUNT = 60000;

    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;

        if (static::TABLE === '') {
            throw new PdoModelException('You should define table name by extending PDOModel class');
        }
    }

    public function select(array|string $columns = '*'): SelectorBuilder
    {
        return (new SelectorBuilder(static::TABLE, $this->connection))->columns($columns);
    }

    public function find(string $primaryKeyValue): array
    {
        return $this->select()->whereEqual(static::PRIMARY_KEY, $primaryKeyValue)->getFirstRow();
    }

    public function max(string $column = self::PRIMARY_KEY): int
    {
        return (int)$this->select('MAX(' . $column . ') as res')->getOneValue('res');
    }

    public function min(string $column = self::PRIMARY_KEY): int
    {
        return (int)$this->select('MIN(' . $column . ') as res')->getOneValue('res');
    }

    public function sum($column): int
    {
        return (int)$this->select('SUM(' . $column . ') as res')->getOneValue('res');
    }

    public function insert(array $data, bool $ignore = false, bool $replace = false): int
    {
        if (array_is_list($data)) {
            throw new PdoModelException('Data keys should be column names, not numbers: ' . json_encode($data));
        }
        $markers = [];
        $values = [];
        $columns = [];
        foreach ($data as $k => $v) {
            $columns[] = "`$k`";
            $markers[] = "?";
            $values[] = $v;
        }
        $sql = $replace ? 'REPLACE INTO ' : ($ignore ? 'INSERT IGNORE INTO ' : 'INSERT INTO ');
        $sql .= static::TABLE . " (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $markers) . ")";
        $this->execute($sql, $values);
        return $this->getLastInsertId();
    }

    public function replace(array $data): int
    {
        return $this->insert($data, replace: true);
    }

    public function insertBatch(array $arraysOfData, bool $ignore = false): bool
    {
        $keys = array_keys($arraysOfData[0]);
        $values = [];
        foreach ($arraysOfData as $data) {
            foreach ($data as $key => $value) {
                $values[] = $value;
            }
        }
        $res = $this->insertBatchRaw($keys, $values, $ignore);
        return $res;
    }

    protected function insertBatchRaw(array $keys, array $values, bool $ignore = false): bool
    {
        // Hard but fast
        $keysCount = count($keys);
        $valuesCount = count($values);
        $valuesSqlCount = $valuesCount / $keysCount;
        $valuesSqlChunkSize = floor(self::MAX_PREPARED_STMT_COUNT / $keysCount);
        $valuesChunkSize = $valuesSqlChunkSize * $keysCount;
        $placeHolders = array_fill(0, $keysCount, '?');
        $placeHolders = implode(',', $placeHolders);
        $valuesSql = array_fill(0, $valuesSqlCount, '(' . $placeHolders . ')');
        $keys = implode(',', $keys);
        $valuesOffset = 0;

        $ignoreSql = '';
        if ($ignore) {
            $ignoreSql = 'IGNORE ';
        }
        foreach (array_chunk($valuesSql, $valuesSqlChunkSize) as $valuesSqlPart) {
            $valuesPart = array_slice($values, $valuesOffset * $valuesSqlChunkSize * $keysCount, $valuesChunkSize);
            $valuesOffset++;
            $valuesSqlPart = implode(',', $valuesSqlPart);
            $sql = 'INSERT ' . $ignoreSql . "INTO `" . static::TABLE . "` ({$keys}) VALUES {$valuesSqlPart}";
            $this->execute($sql, $valuesPart ?? []);
        }
        return true;
    }

    public function insertUpdate(array $insertData, array $updateData): int
    {
        if (empty($insertData) || empty($updateData)) {
            return false;
        }
        $insertPairs = [];
        $updatePairs = [];
        $values = [];
        foreach ($insertData as $k => $v) {
            $insertPairs[] = "`{$k}` = ?";
            $values[] = $v;
        }
        foreach ($updateData as $k => $v) {
            $updatePairs[] = "`{$k}` = ?";
            $values[] = $v;
        }
        $sql = "INSERT INTO `" . static::TABLE . "` SET " . implode(', ', $insertPairs)
            . " ON DUPLICATE KEY UPDATE " . implode(', ', $updatePairs);
        $this->execute($sql, $values);
        return $this->getLastInsertId();
    }

    public function insertUpdateBatch(array $insertRows, array $updateColumns = [], array $incrementColumns = []): bool
    {
        $insertKeys = array_keys($insertRows[0]);
        $insertValues = [];
        foreach ($insertRows as $row) {
            foreach ($row as $key => $value) {
                $insertValues[] = $value;
            }
        }
        $updateSql = [];
        foreach ($updateColumns as $column) {
            $updateSql[] = "`{$column}` = `VALUES(`{$column}`)";
        }
        foreach ($incrementColumns as $column) {
            $updateSql[] = "`{$column}` = `{$column}` + VALUES(`{$column}`)";
        }
        $updateSql = join(',', $updateSql);

        $res = $this->insertUpdateBatchRaw($insertKeys, $insertValues, $updateSql);
        return $res;
    }

    protected function insertUpdateBatchRaw(array $insertKeys, array $insertValues, string $updateSql): bool
    {
        // Hard but fast
        $keysCount = count($insertKeys);
        $valuesCount = count($insertValues);
        $valuesSqlCount = $valuesCount / $keysCount;
        $valuesSqlChunkSize = floor(self::MAX_PREPARED_STMT_COUNT / $keysCount);
        $valuesChunkSize = $valuesSqlChunkSize * $keysCount;
        $placeHolders = array_fill(0, $keysCount, '?');
        $placeHolders = implode(',', $placeHolders);
        $valuesSql = array_fill(0, $valuesSqlCount, '(' . $placeHolders . ')');
        $keys = implode(',', $insertKeys);
        $valuesOffset = 0;

        $res = false;
        foreach (array_chunk($valuesSql, $valuesSqlChunkSize) as $valuesSqlPart) {
            $valuesPart = array_slice($insertValues, $valuesOffset * $valuesSqlChunkSize * $keysCount, $valuesChunkSize);
            $valuesOffset++;
            $valuesSqlPart = implode(',', $valuesSqlPart);
            $sql = "INSERT INTO `" . static::TABLE . "` ({$keys}) VALUES {$valuesSqlPart} ON DUPLICATE KEY UPDATE {$updateSql}";

            $this->execute($sql, $valuesPart ?? []);
        }
        return true;
    }

    public function increment($id, $column, $amount = 1): bool
    {
        $sql = "UPDATE `" . static::TABLE . "` SET {$column} = {$column} + {$amount} WHERE id = ?";
        return $this->execute($sql, [$id]);
    }

    public function update(string $primaryKeyValue, array $data): bool
    {
        if (empty($data)) {
            return false;
        }
        if (array_is_list($data)) {
            throw new PdoModelException('Data keys should be column names, not numbers: ' . json_encode($data));
        }
        $pairs = [];
        $values = [];
        foreach ($data as $k => $v) {
            $pairs[] = "`{$k}` = ?";
            $values[] = $v;
        }
        $values = array_merge($values, [$primaryKeyValue]);
        $sql = "UPDATE `" . static::TABLE . "` SET " . implode(', ', $pairs) . " WHERE " . static::PRIMARY_KEY . " = ?";
        return $this->execute($sql, $values);
    }

    public function delete($id): bool
    {
        $sql = "DELETE FROM `" . static::TABLE . "` WHERE id = ?";
        return $this->execute($sql, [$id]);
    }

    public function getLastInsertId($sequenceName = null): bool|string
    {
        return $this->connection->lastInsertId($sequenceName);
    }

    public function createTable(string $name): CreateTableBuilder
    {
        return new CreateTableBuilder($name, $this->connection);
    }

    protected function execute(string $query, array $data = []): bool
    {
        $sth = $this->connection->prepare($query);
        if (!$sth) {
            throw new PdoModelException('Error in creating STH ' . $sth->errorInfo()[2]);
        }
        if ($data && !array_is_list($data)) {
            throw new PdoModelException('Wrong statement values structure ' . json_encode($data));
        }
        $succeed = $sth->execute($data);
        if (!$succeed) {
            throw new PdoModelException($sth->errorInfo()[2]);
        }
        return $sth->rowCount() > 0;
    }
}
