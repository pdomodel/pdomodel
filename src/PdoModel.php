<?php

namespace PdoModel;

use PdoModel\Builder\CreateTableBuilder;
use PdoModel\Builder\SelectorBuilder;

class PdoModel
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected \PDO $connection;

    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
    }

    public function setTable($tableName)
    {
        $this->table = $tableName;
        return $this;
    }

    public function setPrimaryKey($key)
    {
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
            throw new PdoModelException("Database table name can't be empty");
        }

        return $this->table;
    }
    const MAX_PREPARED_STMT_COUNT = 60000;

    public function select(array|string $columns = '*'): SelectorBuilder
    {
        return (new SelectorBuilder($this->getTable(), $this->connection))->columns($columns);
    }

    public function find($id)
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE {$this->getPrimaryKey()} = ? LIMIT 1";
        $sth = $this->execute($sql, [$id]);
        return $sth->fetch(\PDO::FETCH_ASSOC);
    }

    public function max($column = 'id'): int
    {
        $column = $this->getPrimaryKey();
        $sql = "SELECT MAX({$column}) FROM {$this->getTable()}";
        $sth = $this->execute($sql, $criteria['values'] ?? []);
        $result = $sth->fetch();
        return (int)reset($result);
    }

    public function min(string $column = 'id'): ?int
    {
        $column = $this->getPrimaryKey();
        $sql = "SELECT MIN({$column}) FROM {$this->getTable()}";
        $sth = $this->execute($sql, $criteria['values'] ?? []);
        $result = $sth->fetch();
        return (int)reset($result);
    }

    public function sum($column): int
    {
        $sql = "SELECT SUM({$column}) FROM {$this->getTable()}";
        $sth = $this->execute($sql);
        $result = $sth->fetch(\PDO::FETCH_ASSOC);
        return (int)reset($result);
    }

    public function insert(array $data, bool $ignore = false): int
    {
        $ignoreSql = '';
        if ($ignore) {
            $ignoreSql = 'IGNORE ';
        }
        $insertData = $this->prepareInsertData($data);
        $sql = 'INSERT ' . $ignoreSql . "INTO `{$this->getTable()}` (" . $insertData['columns'] . ") VALUES (" . $insertData['params'] . ")";
        $this->execute($sql, $insertData['values'] ?? []);
        return $this->getLastInsertId();
    }

    public function replace(array $data): int
    {
        $insertData = $this->prepareInsertData($data);
        $sql = "REPLACE INTO `{$this->getTable()}` (" . $insertData['columns'] . ") VALUES (" . $insertData['params'] . ")";
        $this->execute($sql, $insertData['values'] ?? []);
        return $this->getLastInsertId();
    }

    public function insertBatch(array $arraysOfData, bool $ignore = false)
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

    protected function insertBatchRaw(array $keys, array $values, bool $ignore = false)
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
        $table = $this->getTable();
        $valuesOffset = 0;

        $res = false;

        $ignoreSql = '';
        if ($ignore) {
            $ignoreSql = 'IGNORE ';
        }
        foreach (array_chunk($valuesSql, $valuesSqlChunkSize) as $valuesSqlPart) {
            $valuesPart = array_slice($values, $valuesOffset * $valuesSqlChunkSize * $keysCount, $valuesChunkSize);
            $valuesOffset++;
            $valuesSqlPart = implode(',', $valuesSqlPart);
            $sql = 'INSERT ' . $ignoreSql . "INTO `{$table}` ({$keys}) VALUES {$valuesSqlPart}";
            $res = $this->execute($sql, $valuesPart ?? []);
        }
        return $res;
    }

    public function insertUpdate(array $insert, array $update, bool $raw = false)
    {
        if (empty($insert) || empty($update)) {
            return false;
        }
        $insertData = $this->prepareUpdateData($insert, $raw);
        $updateData = $this->prepareUpdateData($update, $raw);
        $values = array_merge($insertData['values'], $updateData['values']);

        $sql = "INSERT INTO `{$this->getTable()}` SET {$insertData['set']} ON DUPLICATE KEY UPDATE {$updateData['set']}";
        if ($raw) {
            $result = $this->execute($sql);
        } else {
            $result = $this->execute($sql, $values ?? []);
        }
        return $result;
    }

    public function insertUpdateBatch(array $insertRows, array $updateColumns = [], array $incrementColumns = [])
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

    protected function insertUpdateBatchRaw(array $insertKeys, array $insertValues, string $updateSql)
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
        $table = $this->getTable();
        $valuesOffset = 0;

        $res = false;
        foreach (array_chunk($valuesSql, $valuesSqlChunkSize) as $valuesSqlPart) {
            $valuesPart = array_slice($insertValues, $valuesOffset * $valuesSqlChunkSize * $keysCount, $valuesChunkSize);
            $valuesOffset++;
            $valuesSqlPart = implode(',', $valuesSqlPart);
            $sql = "INSERT INTO `{$table}` ({$keys}) VALUES {$valuesSqlPart} ON DUPLICATE KEY UPDATE {$updateSql}";

            $res = $this->execute($sql, $valuesPart ?? []);
        }
        return $res;
    }

    public function increment($id, $column, $amount = 1)
    {
        $sql = "UPDATE {$this->getTable()} SET {$column} = {$column} + {$amount} WHERE id = ?";
        return $this->execute($sql, [$id]);
    }

    public function update($id, array $data)
    {
        if (empty($data)) {
            return false;
        }
        $updateData = $this->prepareUpdateData($data);
        $values = array_merge($updateData['values'], [$id]);
        $sql = "UPDATE `{$this->getTable()}` SET " . $updateData['set'] . " WHERE id = ?";
        return $this->execute($sql, $values ?? []);
    }

    public function delete($id)
    {
        $sql = "DELETE FROM {$this->getTable()} WHERE id = ?";
        return $this->execute($sql, [$id]);
    }

    protected function prepareUpdateData(array $data, bool $raw = false)
    {
        $updateData = [];
        $pairs = [];
        $values = [];
        foreach ($data as $k => $v) {
            if ($raw) {
                $pairs[] = "$k = $v";
            } else {
                $pairs[] = "`{$k}` = ?";
            }
            $values[] = $v;
        }
        $updateData['set'] = implode(', ', $pairs);
        $updateData['values'] = $values;

        return $updateData;
    }

    protected function prepareInsertData(array $data)
    {
        $insertData = [];
        $params = [];
        $values = [];
        $columns = [];

        foreach ($data as $k => $v) {
            if ($k === 0) {
                throw new PdoModelException('Insert keys must be column names. Got number instead.');
            }
            $columns[] = "`$k`";
            $params[] = "?";
            $values[] = $v;
        }
        $insertData['values'] = $values;
        $insertData['columns'] = implode(', ', $columns);
        $insertData['params'] = implode(', ', $params);
        return $insertData;
    }

    public function getLastInsertId($sequenceName = null)
    {
        return $this->connection->lastInsertId($sequenceName);
    }

    public function createTable(string $name): CreateTableBuilder
    {
        return new CreateTableBuilder($name, $this->connection);
    }

    protected function execute(string $query, array $data = []): \PDOStatement
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
        return $sth;
    }
}
