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

    const INSERT = 'INSERT';
    const UPDATE = 'UPDATE';
    const SELECT = 'SELECT';
    const DELETE = 'DELETE';
    const INSERT_UPDATE = 'INSERT UPDATE';
    const MAX_PREPARED_STMT_COUNT = 60000;
    protected $changeListenerCallback;

    public function select(array|string $columns = '*'): SelectorBuilder
    {
        return (new SelectorBuilder($this->getTable(), $this->connection))->columns($columns);
    }

    public function find($id)
    {
        $timeStart = microtime(true);
        $sql = "SELECT * FROM {$this->getTable()} WHERE {$this->getPrimaryKey()} = ? LIMIT 1";
        $sth = $this->execute($sql, [$id]);
        $this->log(self::SELECT, $sql, $id, $timeStart);
        return $sth->fetch(\PDO::FETCH_ASSOC);
    }

    public function max($column = 'id'): int
    {
        $column = $this->getPrimaryKey();
        $timeStart = microtime(true);
        $sql = "SELECT MAX({$column}) FROM {$this->getTable()}";
        $sth = $this->execute($sql, $criteria['values'] ?? []);
        $result = $sth->fetch();
        $this->log(self::SELECT, $sql, [], $timeStart);
        return (int)reset($result);
    }

    public function min(string $column = 'id'): ?int
    {
        $column = $this->getPrimaryKey();
        $timeStart = microtime(true);
        $sql = "SELECT MIN({$column}) FROM {$this->getTable()}";
        $sth = $this->execute($sql, $criteria['values'] ?? []);
        $result = $sth->fetch();
        $this->log(self::SELECT, $sql, [], $timeStart);
        return (int)reset($result);
    }

    public function sum($column): int
    {
        $timeStart = microtime(true);
        $sql = "SELECT SUM({$column}) FROM {$this->getTable()}";
        $sth = $this->execute($sql);
        $result = $sth->fetch(\PDO::FETCH_ASSOC);
        $this->log(self::SELECT, $sql, [], $timeStart);
        return (int)reset($result);
    }

    public function insert(array $data, bool $ignore = false): int
    {
        $timeStart = microtime(true);
        $ignoreSql = '';
        if ($ignore) {
            $ignoreSql = 'IGNORE ';
        }
        $insertData = $this->prepareInsertData($data);
        $sql = 'INSERT ' . $ignoreSql . "INTO `{$this->getTable()}` (" . $insertData['columns'] . ") VALUES (" . $insertData['params'] . ")";
        $this->execute($sql, $insertData['values'] ?? []);
        $result = $this->getLastInsertId();
        if ($result && $record = $this->find($result)) {
            $this->changeListener($record[$this->getPrimaryKey()], $record);
        }
        $this->log(self::INSERT, $sql, $insertData['values'], $timeStart);

        return $result;
    }

    public function replace(array $data): int
    {
        $timeStart = microtime(true);
        $insertData = $this->prepareInsertData($data);
        $sql = "REPLACE INTO `{$this->getTable()}` (" . $insertData['columns'] . ") VALUES (" . $insertData['params'] . ")";
        $this->execute($sql, $insertData['values'] ?? []);
        $result = $this->getLastInsertId();
        if ($result) {
            $record = $this->find($result);
            $this->changeListener($record[$this->getPrimaryKey()], $record);
        }
        $this->log(self::INSERT, $sql, $insertData['values'], $timeStart);
        return $result;
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
            $id = $this->getLastInsertId();
            if ($id) {
                $record = $this->find($id);
                $this->changeListener($id, $record);
            }
        }
        return $res;
    }

    public function insertUpdate(array $insert, array $update, bool $raw = false)
    {
        if (empty($insert) || empty($update)) {
            return false;
        }
        $timeStart = microtime(true);

        $insertData = $this->prepareUpdateData($insert, $raw);
        $updateData = $this->prepareUpdateData($update, $raw);
        $values = array_merge($insertData['values'], $updateData['values']);

        $sql = "INSERT INTO `{$this->getTable()}` SET {$insertData['set']} ON DUPLICATE KEY UPDATE {$updateData['set']}";
        if ($raw) {
            $result = $this->execute($sql);
        } else {
            $result = $this->execute($sql, $values ?? []);
        }

        $this->log(self::INSERT_UPDATE, $sql, $values, $timeStart);
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

            $id = $this->getLastInsertId();
            if ($id) {
                $record = $this->find($id);
                $this->changeListener($id, $record);
            }
        }
        return $res;
    }

    public function increment($id, $column, $amount = 1)
    {
        $record = $this->find($id);

        $timeStart = microtime(true);
        $sql = "UPDATE {$this->getTable()} SET {$column} = {$column} + {$amount} WHERE id = ?";
        $res = $this->execute($sql, [$id]);
        if ($record) {
            $this->changeListener($id, $record);
        }
        $this->log(self::UPDATE, $sql, [], $timeStart);
        return $res;
    }

    public function update($id, array $data)
    {
        if (empty($data)) {
            return false;
        }

        $record = $this->find($id);

        $timeStart = microtime(true);
        $updateData = $this->prepareUpdateData($data);
        $values = array_merge($updateData['values'], [$id]);

        $sql = "UPDATE `{$this->getTable()}` SET " . $updateData['set'] . " WHERE id = ?";
        $result = $this->execute($sql, $values ?? []);
        if ($record) {
            $this->changeListener($id, $record);
        }
        $this->log(self::UPDATE, $sql, $values, $timeStart);
        return $result;
    }

    public function delete($id)
    {
        $record = $this->find($id);

        $timeStart = microtime(true);
        $sql = "DELETE FROM {$this->getTable()} WHERE id = ?";
        $result = $this->execute($sql, [$id]);

        if ($record) {
            $this->changeListener($id, $record);
        }
        $this->log(self::DELETE, $sql, [$id], $timeStart);
        return $result;
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

    protected function log($statement, $sql, $params, $timeStart)
    {
//        var_dump($statement, $sql, $params, $timeStart);
    }

    public function setChangeListener($callback)
    {
        $this->changeListenerCallback = $callback;
    }

    protected function changeListener($id, $data = [])
    {
        if (is_callable($this->changeListenerCallback)) {
            call_user_func_array($this->changeListenerCallback, [&$id, &$data]);
        }
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
