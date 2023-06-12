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

    public function insert(array $data, bool $ignore = false): int
    {
        $ignoreSql = '';
        if ($ignore) {
            $ignoreSql = 'IGNORE ';
        }
        $insertData = $this->prepareInsertData($data);
        $sql = 'INSERT ' . $ignoreSql . "INTO `" . static::TABLE . "` (" . $insertData['columns'] . ") VALUES (" . $insertData['params'] . ")";
        $this->execute($sql, $insertData['values']);
        return $this->getLastInsertId();
    }

    public function replace(array $data): int
    {
        $insertData = $this->prepareInsertData($data);
        $sql = "REPLACE INTO `" . static::TABLE . "` (" . $insertData['columns'] . ") VALUES (" . $insertData['params'] . ")";
        $this->execute($sql, $insertData['values']);
        return $this->getLastInsertId();
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

    public function insertUpdate(array $insert, array $update, bool $raw = false): bool
    {
        if (empty($insert) || empty($update)) {
            return false;
        }
        $insertData = $this->prepareUpdateData($insert, $raw);
        $updateData = $this->prepareUpdateData($update, $raw);
        $values = array_merge($insertData['values'], $updateData['values']);

        $sql = "INSERT INTO `" . static::TABLE . "` SET {$insertData['set']} ON DUPLICATE KEY UPDATE {$updateData['set']}";
        if ($raw) {
            return $this->execute($sql);
        } else {
            return $this->execute($sql, $values ?? []);
        }
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

    public function update($id, array $data): bool
    {
        if (empty($data)) {
            return false;
        }
        $updateData = $this->prepareUpdateData($data);
        $values = array_merge($updateData['values'], [$id]);
        $sql = "UPDATE `" . static::TABLE . "` SET " . $updateData['set'] . " WHERE id = ?";
        return $this->execute($sql, $values);
    }

    public function delete($id): bool
    {
        $sql = "DELETE FROM `" . static::TABLE . "` WHERE id = ?";
        return $this->execute($sql, [$id]);
    }

    protected function prepareUpdateData(array $data, bool $raw = false): array
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

    protected function prepareInsertData(array $data): array
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
